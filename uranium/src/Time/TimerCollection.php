<?php

namespace Cijber\Uranium\Time;

use Cijber\Uranium\EventLoop\EventLoop;
use Generator;


class TimerCollection {
    private array $disabledTimers = [];
    private array $ownedTimers = [];
    private array $nativeTimers = [];
    private TimerHeap $heap;


    public function __construct(
      public EventLoop $eventLoop
    ) {
        $this->heap = new TimerHeap();
    }

    public function add(Timer $timer) {
        $this->ownedTimers[spl_object_id($timer)] = $timer;
        $timer->setOwner($this);
        if ($timer->isEnabled()) {
            $this->insertTimer($timer);
            $timer->setSleeping();
        } else {
            $this->disabledTimers[spl_object_id($timer)] = $timer;
        }
    }

    private function insertTimer(Timer $timer) {
        if ($this->eventLoop->hasNativeTimers()) {
            $this->eventLoop->addWaker($timer->createWaker());
        } else {
            $this->heap->insert($timer);
        }
    }

    public function queue(Timer $timer) {
        $id = spl_object_id($timer);
        if ( ! isset($this->ownedTimers[$id])) {
            return;
        }

        if ( ! isset($this->disabledTimers[$id])) {
            $timer->setSleeping();

            if ($this->eventLoop->hasNativeTimers()) {
                $this->eventLoop->addWaker($timer->createWaker());
            }

            return;
        }

        unset($this->disabledTimers[$id]);

        $timer->setSleeping();
        $this->insertTimer($timer);
    }

    /**
     * @param  Duration  $duration
     *
     * @return Generator<Timer>
     */
    public function getSleepingTimersTriggeredWithin(Duration $duration): Generator {
        /** @var Timer $timer */
        foreach ($this->heap->getTriggeredWithin($duration) as $timer) {
            if ($timer->isSleeping()) {
                yield $timer;
            }
        }
    }
}