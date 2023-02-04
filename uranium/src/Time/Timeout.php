<?php

namespace Cijber\Uranium\Time;

use Cijber\Uranium\Channel\Rendezvous;
use Cijber\Uranium\Helper\LoopAwareInterface;
use Cijber\Uranium\Helper\LoopAwareTrait;
use Cijber\Uranium\Loop;


class Timeout implements LoopAwareInterface
{
    use LoopAwareTrait;


    private $fn;

    private bool $executed = false;
    private bool $timedOut = false;
    private mixed $value = null;
    private Rendezvous $rendezvous;
    private Timer $timer;

    public function __construct(private Duration $duration, callable $fn, ?Loop $loop = null)
    {
        $this->fn         = $fn;
        $this->loop       = $loop ?: Loop::get();
        $this->rendezvous = new Rendezvous();
    }

    public function run(?bool $timedOut = null): mixed
    {
        $task = $this->loop->queue(function () {
            $data = ($this->fn)();

            if ($this->timer->isSleeping()) {
                $this->timer->cancel();
            }

            if ( ! $this->rendezvous->isClosed()) {
                $this->rendezvous->write([false, $data]);
            }
        });

        $this->timer = $this->loop->after($this->duration, function () use ($task) {
            if ( ! $this->rendezvous->isClosed()) {
                $this->rendezvous->write([true, null]);
            }
            if ( ! $task->isFinished()) {
                $task->cancel();
            }
        });

        [$this->timedOut, $this->value] = $this->rendezvous->read();
        $timedOut = $this->timedOut;

        return $this->value;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function isExecuted(): bool
    {
        return $this->executed;
    }

    public function timedOut(): bool
    {
        return $this->timedOut;
    }
}