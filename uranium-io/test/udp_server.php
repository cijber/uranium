<?php

include __DIR__ . "/../vendor/autoload.php";

use Cijber\Uranium\IO\Net\UdpSocket;


$socket = UdpSocket::listen("[::]:5400");

while (true) {
    [$data, $from] = $socket->receiveFrom();
    echo "from $from: $data\n";
    $socket->sendTo("get fucked losers\n", $from);
}
