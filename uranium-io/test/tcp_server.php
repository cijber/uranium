<?php

include __DIR__ . "/../vendor/autoload.php";

use Cijber\Uranium\IO\Net\TcpListener;
use Cijber\Uranium\Uranium;


$listener = TcpListener::listen("[::]:5400");

foreach ($listener as $stream) {
    echo "New connection: " . $stream->getPeerName()."\n";
    $stream->write("no\n");
    $stream->close();
}

Uranium::app();