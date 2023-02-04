<?php

include __DIR__ . "/../vendor/autoload.php";

use Cijber\Uranium\IO\Net\UdpSocket;


$sock = UdpSocket::listen("[2001:980:e4b6:1:62e1:1fd5:e82:c17a]:3123");

$x = $sock->sendTo(str_repeat("x", 3400), "[ff02::1]:3243");
