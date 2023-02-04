<?php

namespace Cijber\Uranium\Waker;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Time\Instant;
use Cijber\Uranium\Time\Timer;


class TimerWaker extends Waker {
    public function __construct(
      private Instant $when,
      private ?Timer $timer = null,
      ?Loop $loop = null,
    ) {
        parent::__construct($loop);
    }

    public function getWhen(): Instant {
        return $this->when;
    }

    public function done() {
        $this->timer?->removeWaker($this);

        if ($this->timer?->isRepeating()) {
            $this->timer?->retrigger();
        }
    }
}