<?php

namespace Cijber\Uranium\Timer;

use Cijber\Uranium\Waker\TimerWaker;
use Cijber\Uranium\Waker\Waker;


class Timer {
    const DISABLED = 0;
    const ORPHANED = 1;
    const SLEEPING = 2;
    const QUEUED   = 3;

    private ?Instant $nextTrigger = null;
    private ?TimerCollection $owner = null;
    private int $status = Timer::DISABLED;

    public function __construct(
      private array $action,
      private Duration $time,
      private bool $repeating,
    ) {
    }

    public function setOwner(?TimerCollection $owner): void {
        $this->owner = $owner;
    }

    public function enable() {
        $this->nextTrigger = Instant::now()->add($this->time);
        $this->status      = Timer::ORPHANED;
        // TimerCollection will update Timer accordingly
        $this->owner?->queue($this);
    }

    public function retrigger() {
        $next = $this->nextTrigger->add($this->time);
        $now  = Instant::now();
        if ($next < $now) {
            $this->nextTrigger = $now;
        } else {
            $this->nextTrigger = $next;
        }

        $this->owner?->queue($this);
    }

    public function isRepeating(): bool {
        return $this->repeating;
    }

    public function getNext(): ?Instant {
        return $this->nextTrigger;
    }

    public function isEnabled(): bool {
        return $this->status !== Timer::DISABLED;
    }

    public function setQueued() {
        $this->status = Timer::QUEUED;
    }

    public function setSleeping() {
        $this->status = Timer::SLEEPING;
    }

    public function createWaker(): Waker {
        $waker = new TimerWaker($this->getNext(), $this);
        $waker->setAction(...$this->action);
        return $waker;
    }

    public function isSleeping() {
        return $this->status === Timer::SLEEPING;
    }
}