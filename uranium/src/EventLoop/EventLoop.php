<?php

namespace Cijber\Uranium\EventLoop;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Waker\Waker;
use Psr\Log\LoggerAwareInterface;


interface EventLoop extends LoggerAwareInterface {

    public function addWaker(Waker $waker);

    public function poll();

    public function isEmpty();

    public function setLoop(Loop $loop): void;

    public function hasNativeTimers(): bool;

    public function removeWaker(Waker $waker);
}