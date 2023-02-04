<?php


include __DIR__ . "/../vendor/autoload.php";

use Cijber\Uranium\IO\Stream;
use Cijber\Uranium\Time\Duration;
use Cijber\Uranium\Uranium;


$stdin = Stream::stdin();

Uranium::interval(Duration::seconds(2), function() {
    echo "Hello fellow async libraries!\n";
});

foreach ($stdin->lines() as $line) {
    echo "You said: " . $line;
}