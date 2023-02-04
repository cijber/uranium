<?php

namespace Cijber\Uranium\Dns\Resolver;


use Cijber\Uranium\Helper\LoopAwareInterface;
use Cijber\Uranium\Helper\LoopAwareTrait;


abstract class Handler extends Processor implements LoopAwareInterface
{
    use LoopAwareTrait;


    abstract function handle(Request $request): ?Response;
}