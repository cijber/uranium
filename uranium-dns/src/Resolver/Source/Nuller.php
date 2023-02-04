<?php

namespace Cijber\Uranium\Dns\Resolver\Source;

use Cijber\Uranium\Dns\Resolver\Request;
use Cijber\Uranium\Dns\Resolver\Response;
use Cijber\Uranium\Dns\Resolver\Source;
use Cijber\Uranium\Dns\Resolver\Stack;


class Nuller extends Source {
    function handle(Request $request): ?Response {
        return null;
    }

    public static function fromConfig(array $config, Stack $stack, string $cwd, \Cijber\Uranium\Loop $loop): static {
        return new Nuller();
    }
}