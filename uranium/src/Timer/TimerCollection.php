<?php

namespace Cijber\Uranium\Timer;

use Generator;


class TimerCollection {
    private array $disabledTimers = [];
    private array $ownedTimers = [];
    private TimerHeap $heap;


    public function __construct() {
        $this->heap = new TimerHeap();
    }

    public function add(Timer $timer) {
        $this->ownedTimers[spl_object_id($timer)] = $timer;
        $timer->setOwner($this);
        if ($timer->isEnabled()) {
            $this->heap->insert($timer);
            $timer->setSleeping();
        } else {
            $this->disabledTimers[spl_object_id($timer)] = $timer;
        }
    }

    public function queue(Timer $timer) {
        $id = spl_object_id($timer);
        if ( ! isset($this->ownedTimers[$id])) {
            return;
        }

        if ( ! isset($this->disabledTimers[$id])) {
            $timer->setSleeping();

            return;
        }

        unset($this->disabledTimers[$id]);
        $this->heap->insert($timer);
        $timer->setSleeping();
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