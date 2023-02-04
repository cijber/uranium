<?php

namespace Cijber\Uranium\Dns\Session;

use Cijber\Uranium\Dns\Message;
use Cijber\Uranium\Dns\Parser\MessageParser;
use Cijber\Uranium\IO\Net\Address;
use Cijber\Uranium\IO\Net\TcpStream;
use Cijber\Uranium\Loop;


class StreamSession extends Session {
    private string $buffer = "";

    public function __construct(private TcpStream $stream) {
    }

    public static function connectTcp(string|Address $address, ?Loop $loop = null): StreamSession {
        Address::ensure($address);

        return new StreamSession(TcpStream::connect($address->ensurePort(53), loop: $loop));
    }

    public function close() {
        $this->stream->close();
    }

    public function isClosed(): bool {
        return $this->stream->eof();
    }

    public function read(): ?Message {
        while (strlen($this->buffer) < 2 && ! $this->stream->eof()) {
            $this->buffer .= $this->stream->read();
        }

        if ($this->stream->eof()) {
            return null;
        }

        $length = (ord($this->buffer[0]) << 8) + ord($this->buffer[1]);
        $length += 2;

        while (strlen($this->buffer) < $length && ! $this->stream->eof()) {
            $this->buffer .= $this->stream->read();
        }

        if ($this->stream->eof()) {
            return null;
        }

        $packet       = substr($this->buffer, 2, $length - 2);
        $this->buffer = substr($this->buffer, $length);

        return MessageParser::parse($packet);
    }

    public function write(Message $message) {
        $data = $message->toBytes();
        $data = chr((strlen($data) >> 8) & 255) . chr(strlen($data) & 255) . $data;
        $this->stream->writeAll($data);
    }

    public function addr(): string {
        return "tcp+dns://" . $this->stream->getPeerName();
    }
}