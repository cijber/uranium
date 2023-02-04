<?php

namespace Cijber\Uranium\Compat;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Time\Duration;
use Cijber\Uranium\Waker\StreamWaker;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;


class UraniumLoop implements LoopInterface
{

    private array $subscribedWakers = [];
    /**
     * @var StreamWaker[][]
     */
    private array $openWakers = [];

    private Loop $loop;

    public function __construct(?Loop $loop = null)
    {
        $this->loop = $loop ?: Loop::get();
    }

    public function addReadStream($stream, $listener)
    {
        $this->addStream('read', $stream, $listener);
    }


    public function addWriteStream($stream, $listener)
    {
        $this->addStream('write', $stream, $listener);
    }

    private function addStream(string $type, $stream, $listener)
    {
        $resId                          = $type . ':' . get_resource_id($stream);
        $this->subscribedWakers[$resId] = true;
        $this->loop->queue(function () use ($stream, $listener, $resId, $type) {
            while (isset($this->subscribedWakers[$resId])) {
                $waker                      = StreamWaker::$type($stream);
                $this->openWakers[$resId]   ??= [];
                $this->openWakers[$resId][] = $waker;
                $this->loop->suspend($waker);

                if ( ! isset($this->subscribedWakers[$resId])) {
                    break;
                }

                ($listener)($stream);
            }
        });
    }

    public function removeReadStream($stream)
    {
        $this->removeStream('read', $stream);
    }

    public function removeWriteStream($stream)
    {
        $this->removeStream('write', $stream);
    }

    private function removeStream(string $type, $stream)
    {
        $resId = $type . ':' . get_resource_id($stream);

        if (isset($this->subscribedWakers[$resId])) {
            unset($this->subscribedWakers[$resId]);
        }

        if (isset($this->openWakers[$resId])) {
            foreach ($this->openWakers[$resId] as $waker) {
                $this->loop->wake($waker);
                $this->loop->removeWaker($waker);
            }

            unset($this->openWakers[$resId]);
        }
    }

    public function addTimer($interval, $callback)
    {
        return $this->loop->after(Duration::fromFloat($interval), $callback);
    }

    public function addPeriodicTimer($interval, $callback)
    {
        return $this->loop->interval(Duration::fromFloat($interval), $callback);
    }

    public function cancelTimer(TimerInterface $timer)
    {
        $timer->cancel();
    }

    public function futureTick($listener)
    {
        $this->loop->queue($listener);
    }

    public function addSignal($signal, $listener)
    {
        // TODO: Implement addSignal() method.
    }

    public function removeSignal($signal, $listener)
    {
        // TODO: Implement removeSignal() method.
    }

    public function run()
    {
        $this->loop->block();
    }

    public function stop()
    {
    }
}