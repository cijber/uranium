<?php

namespace Cijber\Uranium\EventLoop;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Time\Duration;
use Cijber\Uranium\Time\Instant;
use Cijber\Uranium\Utils\TimerWakerHeap;
use Cijber\Uranium\Waker\StreamWaker;
use Cijber\Uranium\Waker\TimerWaker;
use Cijber\Uranium\Waker\Waker;
use Psr\Log\LoggerAwareTrait;
use RuntimeException;
use SplHeap;


class SelectEventLoop implements EventLoop {
    use LoggerAwareTrait;


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
            $fd = (int)($waker->getStream());

            if ($waker->getEvent() & StreamWaker::READ) {
                $this->read[$fd] = $waker->getStream();
            }

            if ($waker->getEvent() & StreamWaker::WRITE) {
                $this->write[$fd] = $waker->getStream();
            }

            if ( ! isset($this->wakers[$fd])) {
                $this->wakers[$fd] = [];
            }

            $this->wakers[$fd][spl_object_id($waker)] = $waker;

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

    public function hasNativeTimers(): bool {
        return false;
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
        $timeout = $this->getTimeout();

        if (count($this->read) > 0 || count($this->write) > 0 || count($this->except) > 0) {
            do {

                $read   = array_values($this->read);
                $write  = array_values($this->write);
                $except = array_values($this->except);
            } while (false === stream_select($read, $write, $except, $timeout->getSeconds(), $timeout->getMicroseconds()));

            foreach (array_merge($read, $write, $except) as $item) {
                $wakers = $this->wakers[(int)$item];

                unset($this->read[(int)$item]);
                unset($this->write[(int)$item]);
                unset($this->wakers[(int)$item]);

                foreach ($wakers as $waker) {
                    $this->loop->wake($waker);
                }
            }
        } else {
            usleep($timeout->toMicroseconds());
        }
    }

    public function isEmpty() {
        return count($this->wakers) === 0 && $this->timerWakerHeap->isEmpty();
    }

    public function removeWaker(Waker $waker) {
        if ($waker instanceof StreamWaker) {
            $stream = $waker->getStream();
            $fd     = (int)$stream;

            $id = spl_object_id($waker);

            if ( ! isset($this->wakers[$fd][$id])) {
                return;
            }

            unset($this->wakers[$fd][$id]);

            if (count($this->wakers[$fd]) > 0) {
                $isWrite = false;
                $isRead  = false;

                /** @var StreamWaker $waker */
                foreach ($this->wakers[$fd] as $waker) {
                    $isRead  = $isRead || ($waker->getEvent() & StreamWaker::READ) > 0;
                    $isWrite = $isWrite || ($waker->getEvent() & StreamWaker::WRITE) > 0;

                    if ($isRead && $isWrite) {
                        break;
                    }
                }

                if ($isWrite) {
                    $this->write[$fd] = $stream;
                } else {
                    unset($this->write[$fd]);
                }

                if ($isRead) {
                    $this->read[$fd] = $stream;
                } else {
                    unset($this->read[$fd]);
                }
            } else {
                unset($this->wakers[$fd]);
                unset($this->read[$fd]);
                unset($this->write[$fd]);
            }

            return;
        }
    }
}