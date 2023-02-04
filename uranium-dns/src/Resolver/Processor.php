<?php

namespace Cijber\Uranium\Dns\Resolver;

use Cijber\Uranium\Helper\LoopAwareInterface;
use Cijber\Uranium\Helper\LoopAwareTrait;
use Cijber\Uranium\Loop;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;


abstract class Processor implements LoopAwareInterface, LoggerAwareInterface {
    use LoopAwareTrait;
    use LoggerAwareTrait;


    protected Stack $stack;

    public function getStack(): Stack {
        return $this->stack;
    }

    public function setStack(Stack $stack): void {
        $this->stack = $stack;
    }

    abstract public static function fromConfig(array $config, Stack $stack, string $cwd, Loop $loop): static;

}