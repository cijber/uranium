<?php

namespace Cijber\Uranium\Dns\Session;

use Cijber\Uranium\Dns\Message;
use Cijber\Uranium\Dns\Parser\MessageParser;
use Cijber\Uranium\IO\Net\UdpSocket;


class UdpServerSession extends Session {
    private bool $gotRead = false;
    private int $maxSize = 512;

    public function __construct(private UdpSocket $socket, private string $addr, private string $data) {
    }

    public function getMaxSize(): ?int {
        return $this->maxSize;
    }

    public function close() {
        // no-op
    }

    public function isClosed(): bool {
        return $this->gotRead;
    }

    public function read(): ?Message {
        if ($this->gotRead) {
            return null;
        }

        $this->gotRead = true;

        return MessageParser::parse($this->data);
    }

    public function write(Message $message) {
        $this->socket->sendTo($this->getBytes($message), $this->addr);
    }

    public function addr(): string {
        return "udp+dns://" . $this->addr;
    }
}