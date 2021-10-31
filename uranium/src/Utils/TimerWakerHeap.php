<?php

namespace Cijber\Uranium\Utils;

use SplHeap;


class TimerWakerHeap extends SplHeap {
    protected function compare($value1, $value2) {
        return $value2->getWhen() <=> $value1->getWhen();
    }
}

;