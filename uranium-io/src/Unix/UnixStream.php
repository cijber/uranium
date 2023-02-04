<?php

namespace Cijber\Uranium\IO\Unix;

use Cijber\Uranium\IO\Net\UdpSocket;
use Cijber\Uranium\IO\PhpStream;
use Cijber\Uranium\Loop;


class UnixStream extends PhpStream {
    public function __construct($stream, protected ?string $peerName = null, ?Loop $loop = null) {
        parent::__construct($stream, $loop);
    }

    public function getPeerName(): ?string {
        return $this->peerName;
    }

    public static function connect(string $path): UdpSocket {
        $socket = stream_socket_client("unix://" . $path, flags: STREAM_CLIENT_ASYNC_CONNECT);

        return new UdpSocket($socket);
    }
}