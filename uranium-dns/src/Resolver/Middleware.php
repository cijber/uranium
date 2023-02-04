<?php

namespace Cijber\Uranium\Dns\Resolver;

use Cijber\Uranium\Helper\LoopAwareInterface;
use Cijber\Uranium\Helper\LoopAwareTrait;


abstract class Middleware extends Processor implements LoopAwareInterface {
    use LoopAwareTrait;


    abstract function handle(Request $request, callable $next): ?Response;
}