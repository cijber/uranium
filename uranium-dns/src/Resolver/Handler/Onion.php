<?php

namespace Cijber\Uranium\Dns\Resolver\Handler;

use Cijber\Uranium\Dns\Internal\Resolver\OnionExecutor;
use Cijber\Uranium\Dns\Resolver\Handler;
use Cijber\Uranium\Dns\Resolver\Middleware;
use Cijber\Uranium\Dns\Resolver\Request;
use Cijber\Uranium\Dns\Resolver\Response;
use Cijber\Uranium\Dns\Resolver\Stack;
use Cijber\Uranium\Dns\Resolver\StackBuilder;
use Cijber\Uranium\Loop;
use Psr\Log\LoggerInterface;
use RuntimeException;


class Onion extends Handler {
    public function __construct(
      public Handler $core,
      public array $layers = [],
    ) {
    }

    function handle(Request $request, ?Handler $failOver = null): ?Response {
        return (new OnionExecutor($this, $request))->next();
    }

    public function setStack(Stack $stack): void {
        $this->stack = $stack;

        foreach ($this->layers as $handler) {
            $handler->setStack($stack);
        }

        $this->core->setStack($stack);
    }

    public function setLogger(?LoggerInterface $logger): void {
        $this->logger = $logger;

        foreach ($this->layers as $handler) {
            $handler->setLogger($logger);
        }

        $this->core->setLogger($logger);
    }

    public function setLoop(Loop $loop): void {
        $this->loop = $loop;

        foreach ($this->layers as $handler) {
            $handler->setLoop($loop);
        }

        $this->core->setLoop($loop);
    }

    public static function fromConfig(array $config, Stack $stack, string $cwd, Loop $loop): static {
        $layers = [];

        $shouldBeLast = false;
        $core         = null;
        foreach ($config["children"] as $node) {
            if ($shouldBeLast) {
                throw new RuntimeException("Onion children should end with a handler");
            }

            $node = StackBuilder::nodeFromConfig($node, $stack, $loop, $cwd);
            if ($node instanceof Middleware) {
                $layers[] = $node;
                continue;
            }

            if ($node instanceof Handler) {
                $shouldBeLast = true;
                $core         = $node;
            }
        }

        if ($core === null) {
            throw new RuntimeException("Onion children should end with a handler");
        }

        return new Onion($core, $layers);
    }
}