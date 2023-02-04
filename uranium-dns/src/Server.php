<?php

namespace Cijber\Uranium\Dns;

use Cijber\Uranium\Dns\Session\Session;
use Cijber\Uranium\Dns\Session\StreamSession;
use Cijber\Uranium\Dns\Session\UdpServerSession;
use Cijber\Uranium\IO\Net\Address;
use Cijber\Uranium\IO\Net\TcpListener;
use Cijber\Uranium\IO\Net\UdpSocket;
use Cijber\Uranium\IO\SocketListener;
use Cijber\Uranium\Loop;
use Cijber\Uranium\Task\Helper\SelectLoop;
use Cijber\Uranium\Task\Task;
use Iterator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;


class Server implements Iterator, LoggerAwareInterface {
    use LoggerAwareTrait;

    private ?Task $udpTask = null;
    private ?Task $tcpTask = null;
    protected Loop $loop;
    protected SelectLoop $select;

    private ?Session $current = null;

    public function __construct(
      protected UdpSocket $udpSocket,
      ?Loop $loop = null,
    ) {
        $this->loop   = $loop ?: Loop::get();
        $this->select = new SelectLoop();
        $this->addUdpSocket($this->udpSocket);
    }

    public static function listen(int $port = 53, string|Address $addr = "[::]", ?Loop $loop = null) {
        Address::ensure($addr);
        $addr->setPort($port);

        $listener = TcpListener::listen($addr);
        $socket   = UdpSocket::listen($addr);
        $server   = new Server($socket, $loop);
        $server->addListener($listener);

        return $server;
    }

    public function addUdpSocket(UdpSocket $socket) {
        $this->select->pushTake(function() use ($socket) {
            [$data, $addr] = $socket->receiveFrom();

            return new UdpServerSession($socket, $addr, $data);
        });
    }

    public function addListener(SocketListener $listener) {
        $this->select->pushTake(function() use ($listener) {
            $stream = $listener->accept();
            if ($stream === null) {
                return null;
            }

            return new StreamSession($stream);
        });
    }

    public function accept(): Session {
        $this->select->next();

        return $this->current = $this->select->current();
    }

    private function sendTo(Message $message, string|Address $address): Message {
        Address::ensure($address);

        $this->udpSocket->sendTo($message->toBytes(), $address->getAddress());
    }

    public function current() {
        return $this->current;
    }

    public function next() {
        $this->accept();
    }

    public function key() {
        return null;
    }

    public function valid() {
        return $this->current !== null;
    }

    public function rewind() {
        $this->accept();
    }
}