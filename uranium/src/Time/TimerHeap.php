<?php

namespace Cijber\Uranium\Time;


use Generator;
use SplHeap;


class TimerHeap extends SplHeap
{

    /**
     * @param  Timer  $timerA
     * @param  Timer  $timerB
     *
     * @return int|void
     */
    protected function compare($timerA, $timerB): int
    {
        $nextA = $timerA->getNext();
        $nextB = $timerB->getNext();

        return ($nextB <=> $nextA);
    }

    public function getTriggeredWithin(Duration $duration): Generator
    {
        return $this->getTriggeredBefore(Instant::now()->add($duration));
    }

    public function getTriggeredBefore(Instant $time): Generator
    {
        $temp = clone $this;
        foreach ($temp as $timer) {
            if ($timer->getNext() < $time) {
                yield $timer;
            } else {
                break;
            }
        }
    }
}