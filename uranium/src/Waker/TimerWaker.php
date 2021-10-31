<?php

namespace Cijber\Uranium\Waker;

use Cijber\Uranium\Timer\Instant;
use Cijber\Uranium\Timer\Timer;


class TimerWaker extends Waker {
    public function __construct(
      private Instant $when,
      private Timer $timer,
    ) {
    }

    public function getWhen(): Instant {
        return $this->when;
    }

    public function done() {
        if ($this->timer->isRepeating()) {
            $this->timer->retrigger();
        }
    }
}