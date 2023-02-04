<?php

namespace Cijber\Uranium\Compat;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Time\Timer;
use React\EventLoop\TimerInterface;


class UraniumTimer implements TimerInterface
{
    public function __construct(private Timer $timer, private Loop $loop)
    {
    }

    public function getInterval()
    {
        return $this->timer->getInterval()->asFloat();
    }

    public function getCallback()
    {
        return fn() => $this->loop->runAction(...$this->timer->getAction());
    }

    public function isPeriodic()
    {
        return $this->timer->isRepeating();
    }

    public function cancel()
    {
        $this->timer->cancel();
    }
}