<?php

namespace Cijber\Uranium\Waker;

use Cijber\Uranium\Loop;
use JetBrains\PhpStorm\Pure;


class StreamWaker extends Waker {
    const READ  = 1;
    const WRITE = 2;

    public function __construct(
      private $stream,
      private int $event,
      ?Loop $loop = null,
    ) {
        parent::__construct($loop);
    }

    public function getStream() {
        return $this->stream;
    }

    public function getEvent(): int {
        return $this->event;
    }

    #[Pure]
    public static function read($stream): StreamWaker {
        return new StreamWaker($stream, StreamWaker::READ);
    }

    #[Pure]
    public static function write($stream): StreamWaker {
        return new StreamWaker($stream, StreamWaker::WRITE);
    }
}