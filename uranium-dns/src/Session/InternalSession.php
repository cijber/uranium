<?php

namespace Cijber\Uranium\Dns\Session;

use Cijber\Uranium\Dns\Message;


class InternalSession extends Session {
    public function __construct(private $id = "default") {
    }

    public function close() {
    }

    public function isClosed(): bool {
        return true;
    }

    public function read(): ?Message {
        return null;
    }

    public function write(Message $message) {
    }

    public function addr(): string {
        return "internal:" . $this->id;
    }
}