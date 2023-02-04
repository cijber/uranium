<?php

namespace Cijber\Uranium\Dns\Session;

use Cijber\Collections\Iter;
use Cijber\Uranium\Dns\Message;


abstract class Session extends Iter {
    private ?Message $current = null;

    public function getMaxSize(): ?int {
        return null;
    }

    abstract public function close();

    abstract public function isClosed(): bool;

    abstract public function read(): ?Message;

    abstract public function write(Message $message);

    public function current() {
        return $this->current;
    }

    public function next() {
        if ($this->isClosed()) {
            $this->current = null;

            return;
        }

        $this->current = $this->read();
    }

    public function key() {
        return null;
    }

    public function valid() {
        return $this->current !== null;
    }

    protected function getBytes(Message $message): string {
        return $message->toBytes($this->getMaxSize());
    }

    abstract public function addr(): string;
}