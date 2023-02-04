<?php

namespace Cijber\Uranium\Channel;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Waker\ManualWaker;


class Rendezvous extends Channel {
    private bool $occupied = false;
    private mixed $value = null;
    private ?ManualWaker $readWaker = null;
    private ?ManualWaker $writeWaker = null;
    private bool $closed = false;

    public function __construct(
      ?Loop $loop = null
    ) {
        $this->loop = $loop ?: Loop::get();
    }

    function write(mixed $data) {
        if ($this->closed) {
            throw new ChannelClosedException($this);
        }

        $this->waitWritable();

        if ($this->closed) {
            throw new ChannelClosedException($this);
        }

        $this->setValue($data);
    }

    private function setValue($data): void {
        $this->value    = $data;
        $this->occupied = true;
        $this->loop->queue(function() {
            $this->readWaker?->wake();
            $this->readWaker = null;
        });
    }

    private function getValue(): mixed {
        $data           = $this->value;
        $this->value    = null;
        $this->occupied = false;

        $this->loop->queue(function() {
            $this->writeWaker?->wake();
            $this->writeWaker = null;
        });

        return $data;
    }

    function read(): mixed {
        if ($this->closed && ! $this->occupied) {
            throw new ChannelClosedException($this);
        }

        $this->waitReadable();

        if ($this->closed && ! $this->occupied) {
            throw new ChannelClosedException($this);
        }

        return $this->getValue();
    }

    function tryWrite(mixed $data): bool {
        if ($this->occupied || $this->closed) {
            return false;
        }

        $this->setValue($data);

        return true;
    }

    function tryRead(?bool &$found = false): mixed {
        if ( ! $this->occupied && $this->closed) {
            $found = false;

            return null;
        }

        $found = true;

        return $this->getValue();
    }

    function isEmpty(): bool {
        return ! $this->occupied;
    }

    function isFull(): bool {
        return $this->occupied;
    }

    function waitReadable(): bool {
        while ( ! $this->occupied && ! $this->closed) {
            if ($this->readWaker === null) {
                $this->readWaker = new ManualWaker();
            }

            $this->loop->suspend($this->readWaker);
        }

        return ! $this->closed;
    }

    function waitWritable(): bool {
        while ($this->occupied && ! $this->closed) {
            if ($this->writeWaker === null) {
                $this->writeWaker = new ManualWaker();
            }

            $this->loop->suspend($this->writeWaker);
        }

        return ! $this->closed;
    }

    function close(): void {
        if ($this->closed) {
            throw new ChannelClosedException($this);
        }

        $this->closed = true;
        $this->readWaker?->wake();
        $this->writeWaker?->wake();
    }

    function isClosed(): bool {
        return $this->closed;
    }

    function isPeekable(): bool {
        return true;
    }

    function peek(?bool &$found = false): mixed {
        if ( ! $this->occupied) {
            $found = false;

            return null;
        }

        return $this->value;
    }
}