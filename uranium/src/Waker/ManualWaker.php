<?php

namespace Cijber\Uranium\Waker;

class ManualWaker extends Waker {
    public function wake() {
        $this->loop->wake($this);
    }
}