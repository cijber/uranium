<?php

namespace Cijber\Uranium\IO\Net;

use Cijber\Uranium\IO\SocketListener;
use Cijber\Uranium\IO\Stream;
use Cijber\Uranium\Loop;
use Cijber\Uranium\Utils\Hacks;


/**
 * @phpstan-type Iterator<TcpStream>
 */
class TcpListener extends SocketListener {

    protected function createStream($socket, ?string $peerName, Loop $loop): Stream {
        return new TcpStream($socket, $peerName, $loop);
    }

    public static function listen(string|Address $address, bool $dualStack = true): TcpListener {
        Address::ensure($address);

        $context = stream_context_create([
                                           "socket" => [
                                             "so_reuseport" => true,
                                             "ipv6_v6only"  => ! $dualStack,
                                           ],
                                         ]);

        $socket = Hacks::errorHandler(fn() => stream_socket_server("tcp://" . $address->url(), context: $context), throw: true);

        return new TcpListener($socket);
    }
}