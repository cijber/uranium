<?php

namespace Cijber\Uranium\Dns\Resolver\Handler;

use Cijber\Uranium\Dns\Resolver\Handler;
use Cijber\Uranium\Dns\Resolver\Request;
use Cijber\Uranium\Dns\Resolver\Response;
use Cijber\Uranium\Dns\Resolver\Stack;


class Router extends Handler {
    function handle(Request $request, ?Handler $failOver = null): ?Response {
        // TODO: Implement handle() method.
    }

    public static function fromConfig(array $config, Stack $stack, string $cwd, \Cijber\Uranium\Loop $loop): static {
        // TODO: Implement fromConfig() method.
    }
}