<?php

namespace Cijber\Uranium\Dns\Resolver\Source;

use Cijber\Uranium\Dns\Parser\SystemConfig;
use Cijber\Uranium\Dns\Resolver\Handler;
use Cijber\Uranium\Dns\Resolver\Request;
use Cijber\Uranium\Dns\Resolver\Response;
use Cijber\Uranium\Dns\Resolver\Source;
use Cijber\Uranium\Dns\Resolver\Stack;
use Cijber\Uranium\Dns\SessionManager;
use Cijber\Uranium\EventLoop\EventLoop;
use Cijber\Uranium\Helper\LoopAwareInterface;
use Cijber\Uranium\Helper\LoopAwareTrait;
use Cijber\Uranium\IO\Net\Address;


class Forward extends Source
{

    public function __construct(private array $nameservers, private SessionManager $sessionManager, protected \Cijber\Uranium\Loop $loop)
    {
        foreach ($this->nameservers as &$nameserver) {
            Address::ensure($nameserver);
        }
    }

    function handle(Request $request, ?Handler $failOver = null): ?Response
    {
        if ($request->localOnly) {
            return null;
        }

        $result     = null;
        $nameserver = null;
        foreach ($this->nameservers as $nameserver) {
            $tries = 5;

            while ($tries-- > 0) {
                $result = $this->sessionManager->request($request->message, $nameserver);

                if ($result !== null) {
                    break 2;
                }

                $this->logger?->debug('sleeping for no result given from ' . $nameserver);
                $this->loop->sleep(1);
            }
        }

        if ($result === null) {
            return null;
        }

        $result->setId($request->message->getId());

        return new Response($result, $nameserver);
    }

    public static function fromConfig(array $config, Stack $stack, string $cwd, \Cijber\Uranium\Loop $loop): static
    {
        if (isset($config["properties"]->from)) {
            return new static(SystemConfig::fetchSystemResolvers($config["properties"]->from), $stack->getSessionManager(), $loop);
        }

        return new static($config["values"], $stack->getSessionManager(), $loop);
    }
}