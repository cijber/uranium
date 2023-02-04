<?php

namespace Cijber\Uranium\Channel;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Waker\ManualWaker;
use JetBrains\PhpStorm\Pure;
use SplFixedArray;


class Bounded extends Channel {
    private bool $closed = false;
    private SplFixedArray $slots;
    private int $readOffset = 0;
    private bool $readLooped = false;
    private int $writeOffset = 0;
    private bool $writeLooped = false;

    private ?ManualWaker $readWaker = null;
    private ?ManualWaker $writeWaker = null;

    public function __construct(private int $size, ?Loop $loop = null) {
        $this->slots = new SplFixedArray($this->size);
        $this->loop  = $loop ?: Loop::get();
    }

    function waitWritable(): bool {
        while ($this->occupiedSlots() === $this->size && ! $this->closed) {
            if ($this->writeWaker === null) {
                $this->writeWaker = new ManualWaker();
            }

            $this->loop->suspend($this->writeWaker);
        }

        return ! $this->closed;
    }

    public function slots(): int {
        return $this->size;
    }

    #[Pure]
    public function freeSlots(): int {
        return $this->size - $this->occupiedSlots();
    }

    public function occupiedSlots(): int {
        if ($this->readLooped === $this->writeLooped) {
            return $this->writeOffset - $this->readOffset;
        } else {
            return ($this->size + $this->writeOffset) - $this->readOffset;
        }
    }

    function write(mixed $data) {
        if ($this->closed) {
            throw new ChannelClosedException($this);
        }

        $this->waitWritable();

        if ($this->closed) {
            throw new ChannelClosedException($this);
        }

        $this->writeValue($data);
    }

    function tryWrite(mixed $data): bool {
        if ($this->occupiedSlots() < $this->size) {
            $this->writeValue($data);

            return true;
        }

        return false;
    }

    private function writeValue(mixed $data) {
        $this->slots[$this->writeOffset++] = $data;
        if ($this->writeOffset >= $this->size) {
            $this->writeLooped = ! $this->writeLooped;
            $this->writeOffset = 0;
        }

        $this->loop->queue(function() {
            $this->readWaker?->wake();
            $this->readWaker = null;
        });
    }

    private function readValue(): mixed {
        $data = $this->slots[$this->readOffset];
        // Clear it for reference count purposes
        $this->slots[$this->readOffset++] = null;
        if ($this->readOffset >= $this->size) {
            $this->readLooped = ! $this->readLooped;
            $this->readOffset = 0;
        }

        $this->loop->queue(function() {
            $this->writeWaker?->wake();
            $this->writeWaker = null;
        });

        return $data;
    }

    function waitReadable(): bool {
        while ($this->occupiedSlots() === 0 && ! $this->closed) {
            if ($this->readWaker === null) {
                $this->readWaker = new ManualWaker();
            }

            $this->loop->suspend($this->readWaker);
        }

        return $this->occupiedSlots() > 0;
    }

    function read(): mixed {
        if ($this->occupiedSlots() === 0 && $this->closed) {
            throw new ChannelClosedException($this);
        }

        $this->waitReadable();

        if ($this->occupiedSlots() === 0 && $this->closed) {
            throw new ChannelClosedException($this);
        }

        return $this->readValue();
    }

    function tryRead(?bool &$found = false): mixed {
        if ($this->occupiedSlots() > 0) {
            $found = true;

            return $this->readValue();
        }

        $found = false;

        return null;
    }

    function close(): void {
        if ($this->closed) {
            throw new ChannelClosedException($this);
        }

        $this->closed = true;
        $this->writeWaker?->wake();
        $this->readWaker?->wake();
    }

    public function __destruct()
    {
        if (!$this->closed) {
            $this->close();
        }
    }

    function isClosed(): bool {
        return $this->closed;
    }

    function isEmpty(): bool {
        return $this->occupiedSlots() === 0;
    }

    function isFull(): bool {
        return $this->occupiedSlots() === $this->size;
    }

    function isPeekable(): bool {
        return true;
    }

    function peek(?bool &$found = false): mixed {
        if ($this->occupiedSlots() === 0) {
            $found = false;

            return null;
        }

        $found = true;

        return $this->slots[$this->readOffset];
    }
}