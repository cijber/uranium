<?php

namespace Cijber\Uranium\IO\Net;

use Cijber\Uranium\Dns\Client;
use Cijber\Uranium\IO\PhpStream;
use Cijber\Uranium\Loop;


class TcpStream extends PhpStream {
    public function __construct($stream, protected ?string $peerName = null, ?Loop $loop = null) {
        parent::__construct($stream, $loop);

        if ($this->peerName === null) {
            $this->peerName = stream_socket_get_name($this->stream, true);
        }
    }

    public function getPeerName(): ?string {
        return $this->peerName;
    }

    public static function connect(string|Address $address, ?Loop $loop = null, bool $dns = false): TcpStream {
        if (class_exists(Client::class)) {
            $dns       ??= Client::instance($loop);
            $addresses = $dns->resolve($address);
            $address   = $addresses[array_rand($addresses)];
        } else {
        }

        $socket = stream_socket_client("tcp://" . $address->url(), flags: STREAM_CLIENT_ASYNC_CONNECT);

        return new TcpStream($socket, loop: $loop);
    }
}