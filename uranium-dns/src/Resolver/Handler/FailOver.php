<?php

namespace Cijber\Uranium\Dns\Resolver\Handler;

use Cijber\Uranium\Dns\Message;
use Cijber\Uranium\Dns\Resolver\Handler;
use Cijber\Uranium\Dns\Resolver\Request;
use Cijber\Uranium\Dns\Resolver\Response;
use Cijber\Uranium\Dns\Resolver\Stack;
use Cijber\Uranium\Dns\Resolver\StackBuilder;
use Cijber\Uranium\Helper\LoopAwareInterface;
use Cijber\Uranium\Helper\LoopAwareTrait;
use Cijber\Uranium\Loop;
use Psr\Log\LoggerInterface;
use RuntimeException;


class FailOver extends Handler implements LoopAwareInterface {
    use LoopAwareTrait;


    /**
     * @param  Handler[]  $handlers
     * @param  bool  $prefetch  If set to true, it will not wait for the first _N_ handlers to fail, but instantly try to resolve on all handlers, priority is preserved
     */
    public function __construct(
      public array $handlers = [],
      private bool $prefetch = false,
      private int $prefetchConcurrency = 5,
    ) {
    }

    public function setStack(Stack $stack): void {
        $this->stack = $stack;

        foreach ($this->handlers as $handler) {
            $handler->setStack($stack);
        }
    }

    public function setLoop(Loop $loop): void {
        $this->loop = $loop;

        foreach ($this->handlers as $handler) {
            $handler->setLoop($loop);
        }
    }

    public function setLogger(?LoggerInterface $logger): void {
        $this->logger = $logger;

        foreach ($this->handlers as $handler) {
            $handler->setLogger($logger);
        }
    }

    function handle(Request $request, ?Handler $failOver = null): ?Response {
        if ($this->prefetch) {
            $resp = $this->handlePrefetched($request);
        } else {
            $resp = $this->handleLazily($request);
        }

        return $resp ?: Response::nxdomain($request);
    }

    protected function isValidResponse(?Response $response): bool {
        return $response !== null && $response->message->getResponseCode() === Message::R_OK && count($response->message->getResponseRecords()) > 0;
    }

    protected function handlePrefetched(Request $request): ?Response {
        $mapper = $this->loop->map($this->handlers, fn(Handler $handler) => $handler->handle($request), $this->prefetchConcurrency);

        /** @var Response $item */
        foreach ($mapper as $item) {
            if ($this->isValidResponse($item)) {
                return $item;
            }
        }

        return null;
    }

    protected function handleLazily(Request $request): ?Response {
        for ($i = 0; $i < count($this->handlers); $i++) {
            $item = $this->handlers[$i]->handle($request);

            if ($this->isValidResponse($item)) {
                return $item;
            }
        }

        return null;
    }

    public static function fromConfig(array $config, Stack $stack, string $cwd, Loop $loop): static {
        $handlers            = [];
        $prefetch            = $config["properties"]->prefetch ?? false;
        $prefetchConcurrency = $config["properties"]->concurrency ?? 5;
        foreach ($config["children"] as $node) {
            $node = StackBuilder::nodeFromConfig($node, $stack, $loop, $cwd);

            if ( ! $node instanceof Handler) {
                throw new RuntimeException("FailOver only accepts Handler's as children");
            }

            $handlers[] = $node;
        }

        return new FailOver($handlers, $prefetch, $prefetchConcurrency);
    }
}