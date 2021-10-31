<?php

namespace Cijber\Uranium\EventLoop;

use Cijber\Uranium\Waker\Waker;


interface EventLoop {
    public function addWaker(Waker $waker);

    public function poll();

    public function isEmpty();
}