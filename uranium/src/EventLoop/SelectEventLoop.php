<?php

namespace Cijber\Uranium\EventLoop;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Timer\Duration;
use Cijber\Uranium\Timer\Instant;
use Cijber\Uranium\Utils\TimerWakerHeap;
use Cijber\Uranium\Waker\StreamWaker;
use Cijber\Uranium\Waker\TimerWaker;
use Cijber\Uranium\Waker\Waker;
use RuntimeException;
use SplHeap;


class SelectEventLoop implements EventLoop {
    public array $wakers = [];
    public array $read = [];
    public array $write = [];
    public array $except = [];

    /** @var SplHeap<TimerWaker> */
    public SplHeap $timerWakerHeap;

    public Loop $loop;

    public function __construct() {
        $this->timerWakerHeap = new TimerWakerHeap();
    }

    public function setLoop(Loop $loop): void {
        $this->loop = $loop;
    }

    public function addWaker(Waker $waker) {
        if ($waker instanceof StreamWaker) {
            $id = (int)($waker->getStream());

            if ($waker->getEvent() & StreamWaker::READ) {
                $this->read[$id] = $waker->getStream();
            }

            if ($waker->getEvent() & StreamWaker::WRITE) {
                $this->write[$id] = $waker->getStream();
            }

            $this->wakers[$id] = $waker;

            return;
        }

        if ($waker instanceof TimerWaker) {
            $this->timerWakerHeap->insert($waker);

            return;
        }

        throw new RuntimeException("Waker of type " . get_class($waker) . " not supported by " . get_class($this));
    }

    public function poll() {
        $this->handleTimers();
        $this->handleSelect();
    }

    public function handleTimers() {
        $start = Instant::now();
        while ( ! $this->timerWakerHeap->isEmpty() && $this->timerWakerHeap->top()->getWhen() < $start) {
            $waker = $this->timerWakerHeap->extract();
            $this->loop->wake($waker);
        }
    }

    public function getTimeout(): Duration {
        if ($this->timerWakerHeap->isEmpty()) {
            return Duration::milliseconds(15);
        }

        return Duration::fromNow($this->timerWakerHeap->top()->getWhen());
    }

    public function handleSelect() {
        if (count($this->read) > 0 || count($this->write) > 0 || count($this->except) > 0) {
            $timeout = $this->getTimeout();

            do {
                $read   = array_values($this->read);
                $write  = array_values($this->write);
                $except = array_values($this->except);
            } while (false === stream_select($read, $write, $except, $timeout->getSeconds(), $timeout->getMicroseconds()));

            foreach (array_merge($read, $write, $except) as $item) {
                $waker = $this->wakers[(int)$item];
                unset($this->read[(int)$item]);
                unset($this->write[(int)$item]);
                unset($this->wakers[(int)$item]);
                $this->loop->wake($waker);
            }
        }
    }

    public function isEmpty() {
        return count($this->wakers) === 0 && $this->timerWakerHeap->isEmpty();
    }
}