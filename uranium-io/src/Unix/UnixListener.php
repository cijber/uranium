<?php

namespace Cijber\Uranium\IO\Unix;

use Cijber\Uranium\IO\SocketListener;
use Cijber\Uranium\IO\Stream;
use Cijber\Uranium\Loop;


class UnixListener extends SocketListener {
    protected function createStream($socket, ?string $peerName, Loop $loop): Stream {
        return new UnixStream($socket, $peerName, $loop);
    }

    public static function listen(string $path): UnixListener {
        $socket = stream_socket_server("unix://" . $path);

        return new UnixListener($socket);
    }
}