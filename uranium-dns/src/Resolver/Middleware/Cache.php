<?php

namespace Cijber\Uranium\Dns\Resolver\Middleware;

use Cijber\Uranium\Dns\Internal\CachedResponse;
use Cijber\Uranium\Dns\Message;
use Cijber\Uranium\Dns\Record\CNAME;
use Cijber\Uranium\Dns\Resolver\Middleware;
use Cijber\Uranium\Dns\Resolver\Request;
use Cijber\Uranium\Dns\Resolver\Response;
use Cijber\Uranium\Dns\Resolver\Stack;
use Cijber\Uranium\Dns\ResourceClass;
use Cijber\Uranium\Dns\ResourceType;
use Cijber\Uranium\Sync\Once;
use Composer\InstalledVersions;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use RuntimeException;
use Stash\Driver\Ephemeral;
use Stash\Driver\Redis;
use Stash\Pool;


if ( ! InstalledVersions::isInstalled("tedivm/stash")) {
    return;
}

class Cache extends Middleware implements LoggerAwareInterface
{
    use LoggerAwareTrait;


    /**
     * @var Once[]
     */
    private array $running = [];

    public function __construct(private Pool $pool, private bool $intercept = true)
    {
    }

    function handle(Request $request, callable $next): ?Response
    {
        $question = $request->message->getQuestion();

        if ($question === null) {
            return null;
        }

        $canonLabelString = strtoupper($question->getLabelString());
        $poolKey          = ($request->message->isRecursionDesired() ? 'RECURSE' : 'DIRECT') . '.' . (ResourceClass::BY_CLASS[$question->class] ?? $question->class) . '.' . (ResourceType::BY_TYPE[$question->type][0] ?? $question->type) . '#' . $canonLabelString;
        $item             = $this->pool->getItem($poolKey);

        if ($item->isHit()) {
            $this->logger?->debug("Message[{$request->message->getId()}] Cache hit  [$poolKey]");

            /** @var CachedResponse $value */
            $value = $item->get();
            if ($value === null) {
                return null;
            }


            $value->updateTtl();

            $response = Message::empty($request->message);
            $response->setResponseRecords($value->getResponseRecords());
            $response->setAuthoritativeRecords($value->getNameserverRecords());
            $response->setId($request->message->getId());

            return new Response($response);
        } else {
            $canIntercept = isset($this->running[$poolKey]);
            $this->logger?->debug("Message[{$request->message->getId()}] Cache miss " . ($this->intercept && $canIntercept ? "but intercepting " : "") . "[$poolKey]");

            if ($canIntercept) {
                /** @var Response $result */
                $result = $this->running[$poolKey]->get();
                if ($result !== null) {
                    $result = clone $result->message;
                    $result->setId($request->message->getId());
                    $result = new Response($result);
                }

                // The other request will do the actual caching
                return $result;
            } else {
                $this->running[$poolKey] = new Once();
                /** @var ?Response $result */
                $result = $next();
                $this->running[$poolKey]->set($result);
            }

            $ttl = 3600;

            $cachedResponse = null;
            if ($result !== null) {
                if ($result->message->isTruncated()) {
                    return $result;
                }

                $records = [];
                $cnames  = [];

                foreach ($result->message->getResponseRecords() as $record) {
                    if ($record->class !== $question->class || $record->getLabels() !== $question->getLabels()) {
                        continue;
                    }

                    if ($record->type !== $question->type && $record->type !== ResourceType::CNAME) {
                        continue;
                    }

                    if ($record instanceof CNAME) {
                        $cnames[] = $record->getDomain();
                    }

                    $ttl       = min($record->ttl, $ttl);
                    $records[] = $record;
                }

                // Add the CNAME answer section
                if (count($cnames) > 0) {
                    foreach ($result->message->getResponseRecords() as $record) {
                        if ($record->class !== $question->class || ! in_array($record->getLabels(), $cnames) || $record->type !== $question->type) {
                            continue;
                        }

                        $ttl       = min($record->ttl, $ttl);
                        $records[] = $record;
                    }
                }

                $nameservers = [];
                foreach ($result->message->getAuthoritativeRecords() as $record) {
                    $ttl           = min($record->ttl, $ttl);
                    $nameservers[] = $record;
                }

                $cachedResponse = new CachedResponse($records, $nameservers);
            }

            if ($result->message->getResponseCode() !== Message::R_OK && count($cachedResponse->getNameserverRecords()) === 0) {
                $ttl = 300;
            }

            $item->expiresAfter($ttl);
            $item->set($cachedResponse);
            $item->save();


            unset($this->running[$poolKey]);

            return $result;
        }
    }

    public static function fromConfig(array $config, Stack $stack, string $cwd, \Cijber\Uranium\Loop $loop): static
    {
        $driverName = $config['properties']->driver ?? "memory";
        $driver     = match ($driverName) {
            "redis" => new Redis((array)$config['properties']),
            "memory", "ephemeral" => new Ephemeral(array_merge(["maxItems" => 256], (array)$config['properties'])),
            default => throw new RuntimeException("No cache driver known by '" . $driverName . "'")
        };

        $pool = new Pool($driver);

        return new Cache($pool);
    }
}