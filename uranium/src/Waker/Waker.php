<?php

namespace Cijber\Uranium\Waker;

abstract class Waker {
    protected mixed $action;

    public function setAction($action, $args = null) {
        $this->action = [$action, $args];
    }

    public function getAction(): mixed {
        return $this->action;
    }

    public function done() {
    }
}