<?php

namespace Cijber\Uranium\Dns\Resolver\Middleware;

use Cijber\Uranium\Dns\Resolver\Middleware;
use Cijber\Uranium\Dns\Resolver\Request;
use Cijber\Uranium\Dns\Resolver\Response;
use Cijber\Uranium\Dns\Resolver\Stack;
use Cijber\Uranium\Dns\ResourceClass;
use Cijber\Uranium\Dns\ResourceType;


class Logger extends Middleware {
    function handle(Request $request, callable $next): Response {
        $message = $request->message;
        foreach ($request->message->getRequestRecords() as $question) {
            $this->logger?->info("Message[{$message->getId()}, session={$request->session->addr()}] Question: " . ResourceType::getName($question->type) . ' ' . ResourceClass::getName($question->class) . ' ' . implode(".", $question->getLabels()) . ". ");
        }

        return $next();
    }

    public static function fromConfig(array $config, Stack $stack, string $cwd, \Cijber\Uranium\Loop $loop): static {
        return new static();
    }
}