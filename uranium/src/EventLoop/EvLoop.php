<?php

namespace Cijber\Uranium\EventLoop;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Time\Duration;
use Cijber\Uranium\Waker\StreamWaker;
use Cijber\Uranium\Waker\TimerWaker;
use Cijber\Uranium\Waker\Waker;
use Ev;
use EvIo;
use Psr\Log\LoggerAwareTrait;


class EvLoop implements EventLoop
{
    use LoggerAwareTrait;


    private array $io = [];
    /**
     * @var EvIo[]
     */
    private array $ev = [];

    private array $wakeUp = [];

    private array $timers = [];

    private Loop $loop;

    private \EvLoop $evLoop;

    public function __construct()
    {
        $this->evLoop = new \EvLoop();
    }

    public function hasNativeTimers(): bool
    {
        return true;
    }

    public function addWaker(Waker $waker)
    {
        if ($waker instanceof StreamWaker) {
            $fd = (int)$waker->getStream();

            if ( ! isset($this->io[$fd])) {
                $this->io[$fd] = [];
            }

            $this->io[$fd][] = $waker;

            $this->updateIOEvent($fd, $waker->getStream());
        }

        if ($waker instanceof TimerWaker) {
            $duration                            = Duration::fromNow($waker->getWhen());
            $timer                               = $this->evLoop->timer($duration->asFloat(), 0.0, fn($w) => $this->wakeTimer($w->data), $waker);
            $this->timers[spl_object_id($waker)] = $timer;
        }
    }

    public function updateIOEvent(int $fd, mixed $stream)
    {
        $eventMask = 0;
        foreach ($this->io[$fd] as $waker) {
            $eventMask |= ($waker->getEvent() & StreamWaker::READ) ? Ev::READ : 0;
            $eventMask |= ($waker->getEvent() & StreamWaker::WRITE) ? Ev::WRITE : 0;

            if ($eventMask === (Ev::READ | Ev::WRITE)) {
                break;
            }
        }

        if ( ! isset($this->ev[$fd]) && $eventMask > 0) {
            $this->ev[$fd] = $this->evLoop->io($stream, $eventMask, fn($watcher, $revents) => $this->wakeStream($fd, $revents));
        } else {
            if ($eventMask === 0) {
                $this->ev[$fd]->stop();

                if (count($this->io[$fd]) > 0) {
                    $this->logger?->warning("");
                }

                unset($this->io[$fd]);
                unset($this->ev[$fd]);
            } elseif ($this->ev[$fd]->events != $eventMask) {
                $this->ev[$fd]->set($stream, $eventMask);
            }
        }

//        $x = [];
//
//        foreach ($this->io as $fd => $value) {
//            $x[$fd] = count($value);
//        }
    }

    public function poll()
    {
        $this->evLoop->run(Ev::RUN_ONCE);

        while (($item = array_shift($this->wakeUp)) !== null) {
            $this->loop->wake($item);
        }
    }

    protected function wakeTimer(TimerWaker $waker)
    {
        unset($this->timers[spl_object_id($waker)]);
        array_push($this->wakeUp, $waker);
    }

    protected function wakeStream(int $fd, int $event)
    {
        $io = $this->io[$fd];

        $eventMask = ($event & Ev::READ) ? StreamWaker::READ : 0;
        $eventMask |= ($event & Ev::WRITE) ? StreamWaker::WRITE : 0;

        $new = [];

        $stream = null;
        /** @var StreamWaker $waker */
        foreach ($io as $waker) {
            $stream = $waker->getStream();
            if (($waker->getEvent() & $eventMask) === 0) {
                $new[] = $waker;
                continue;
            }

            array_push($this->wakeUp, $waker);
        }


        $this->io[$fd] = $new;
        $this->updateIOEvent($fd, $stream);
    }

    public function isEmpty()
    {
        return count($this->ev) === 0 && count($this->timers) === 0;
    }

    public function setLoop(Loop $loop): void
    {
        $this->loop = $loop;
    }

    public function removeWaker(Waker $waker)
    {
        if ($waker instanceof StreamWaker) {
            $fd = (int)$waker->getStream();

            if ( ! isset($this->io[$fd])) {
                return;
            }

            $id = array_search($waker, $this->io[$fd]);
            if ($id === false) {
                return;
            }

            unset($this->io[$fd][$id]);
            $this->updateIOEvent($fd, $waker->getStream());

            return;
        }

        if ($waker instanceof TimerWaker) {
            return;
        }
        // ????
    }
}
