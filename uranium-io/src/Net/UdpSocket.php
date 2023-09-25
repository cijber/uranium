<?php

namespace Cijber\Uranium\IO\Net;

use Cijber\Uranium\Dns\Client;
use Cijber\Uranium\IO\PhpStream;
use Cijber\Uranium\IO\Stream;
use Cijber\Uranium\Loop;
use Cijber\Uranium\Utils\Hacks;
use Cijber\Uranium\Waker\StreamWaker;


class UdpSocket extends PhpStream
{
    private string $peerName;
    private bool $dualStack = false;

    public function __construct($stream, ?Loop $loop = null)
    {
        parent::__construct($stream, $loop);
        $this->peerName = stream_socket_get_name($stream, true);
    }

    /**
     * @return string[]
     */
    public function receiveFrom(int $size = Stream::CHUNK_SIZE): array
    {
        while (1) {
            $address = null;
            $data    = Hacks::errorHandler(function () use (&$address, $size) {
                return stream_socket_recvfrom($this->stream, $size, 0, $address);
            }, $error);

            if (($data === false || $data === "") && $error != null) {
                throw new \RuntimeException("Failed to read data from socket", previous: $error);
            }

            if (($data === false || $data === "") && ! feof($this->stream)) {
                $this->loop->suspend(StreamWaker::read($this->stream));
                continue;
            }

            return [$data, $address];
        }
    }

    public function sendTo(string $data, string $address): int
    {
        while (1) {
            $written = Hacks::errorHandler(fn() => stream_socket_sendto($this->stream, $data, 0, $address), $error);

            if ($error != null) {
                throw new \RuntimeException("Failed to read data from socket", previous: $error);
            }

            if ($written === false || $written === -1) {
                $this->loop->suspend(StreamWaker::write($this->stream));
                continue;
            }

            return $written;
        }
    }

    public static function listen(string|Address $address, bool $dualStack = true, ?Loop $loop = null): UdpSocket
    {
        Address::ensure($address);

        $loop = $loop ?: Loop::get();

        $context = stream_context_create(
          [
            "socket" => [
              "ipv6_v6only" => ! $dualStack,
            ],
          ]
        );

        $socket = Hacks::errorHandler(fn() => stream_socket_server("udp://" . $address->url(), $_, $_, STREAM_SERVER_BIND, $context), $_, throw: true);

        return new UdpSocket($socket, $loop);
    }

    public static function connect(string|Address $address, ?Loop $loop = null, ?Client $dns = null): UdpSocket
    {
        $dns       ??= Client::instance($loop);
        $addresses = $dns->resolve($address);
        $address   = $addresses[array_rand($addresses)];

        $socket = Hacks::errorHandler(fn() => stream_socket_client("udp://" . $address->url(), flags: STREAM_CLIENT_ASYNC_CONNECT), throw: true);

        return new UdpSocket($socket, loop: $loop);
    }

    public function getPeerName(): string
    {
        return $this->peerName;
    }
}