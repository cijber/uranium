<?php

namespace Cijber\Uranium\Sync;

use Cijber\Uranium\Helper\LoopAwareInterface;
use Cijber\Uranium\Helper\LoopAwareTrait;
use Cijber\Uranium\Loop;
use Cijber\Uranium\Waker\ManualWaker;


class Once implements LoopAwareInterface
{
    use LoopAwareTrait;


    private bool $occupied = false;
    private mixed $value = null;

    private ManualWaker $waker;

    public function __construct(?Loop $loop = null)
    {
        $this->loop  = $loop ?: Loop::get();
        $this->waker = new ManualWaker($this->loop);
    }

    public function get()
    {
        if ($this->occupied) {
            return $this->value;
        }

        $this->loop->suspend($this->waker);

        return $this->value;
    }

    public function set(mixed $value)
    {
        if ($this->occupied) {
            throw new \RuntimeException("\\Cijber\\Uranium\\Sync\\Once can only be set once");
        }

        $this->occupied = true;
        $this->value    = $value;
        $this->waker->wake();
    }
}