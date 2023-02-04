<?php

namespace Cijber\Uranium\Dns\Session;

use Cijber\Uranium\Dns\Message;
use Cijber\Uranium\Dns\Parser\MessageParser;
use Cijber\Uranium\IO\Net\Address;
use Cijber\Uranium\IO\Net\UdpSocket;
use Cijber\Uranium\Loop;


class UdpSession extends Session
{
    public function __construct(
      private UdpSocket $socket,
      private int $maxSize = 512,
    ) {
    }

    public function getMaxSize(): ?int
    {
        return $this->maxSize;
    }

    public function close()
    {
        $this->socket->close();
    }

    public function isClosed(): bool
    {
        return $this->socket->eof();
    }

    public function read(): ?Message
    {
        $msg = $this->socket->read();

        if ($msg === "") {
            return null;
        }

        return MessageParser::parse($msg);
    }

    public function write(Message $message)
    {
        $this->socket->write($this->getBytes($message));
    }

    public function addr(): string
    {
        return "udp+dns://" . $this->socket->getPeerName();
    }

    public static function connect(string|Address $address, int $maxSize = 512, ?Loop $loop = null): UdpSession
    {
        Address::ensure($address);

        return new UdpSession(UdpSocket::connect($address->ensurePort(53), loop: $loop), $maxSize);
    }
}