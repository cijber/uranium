<?php

namespace Cijber\Uranium\IO;

use Cijber\Collections\Iter;
use Cijber\Uranium\IO\Net\TcpStream;
use Cijber\Uranium\Loop;
use Cijber\Uranium\Waker\StreamWaker;


abstract class SocketListener extends Iter {
    private ?Stream $lastStream = null;
    private Loop $loop;

    public function __construct(
      private $socket,
      ?Loop $loop = null,
    ) {
        stream_set_blocking($this->socket, false);
        $this->loop = $loop ?: Loop::get();
    }

    /**
     * @return Stream|TcpStream
     */
    public function accept(): ?Stream {
        while (1) {
            $this->waitAccept();
            $socket = stream_socket_accept($this->socket, 1, $peerName);
            if ($socket === false) {
                continue;
            }

            return $this->lastStream = $this->createStream($socket, $peerName, $this->loop);
        }
    }

    abstract protected function createStream($socket, ?string $peerName, Loop $loop): Stream;

    public function waitAccept() {
        $this->loop->suspend(StreamWaker::read($this->socket));
    }

    public function current() {
        return $this->lastStream;
    }

    public function next() {
        $this->accept();
    }

    public function key() {
        return null;
    }

    public function valid() {
        return $this->lastStream !== null;
    }
}