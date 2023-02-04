<?php

namespace Cijber\Uranium\Time;

use Cijber\Uranium\Utils\Hacks;


class Instant {
    public function __construct(
      private int $seconds = 0,
      private int $nanoseconds = 0,
    ) {
    }

    public function getSeconds(): int {
        return $this->seconds;
    }

    public function getNanoseconds(): int {
        return $this->nanoseconds;
    }

    public function add(Duration $duration): self {
        $this->seconds += $duration->getSeconds();
        $nano          = $duration->getNanoseconds() + $this->nanoseconds;
        if ($nano >= Duration::NANOSECONDS_IN_SECS) {
            $this->seconds     += floor($nano / Duration::NANOSECONDS_IN_SECS);
            $this->nanoseconds = $nano % Duration::NANOSECONDS_IN_SECS;
        } else {
            $this->nanoseconds = $nano;
        }

        return $this;
    }

    public function sub(Duration $duration): self {
        $this->seconds -= $duration->getSeconds();
        if ($duration->getNanoseconds() > $this->nanoseconds) {
            $this->seconds     -= 1;
            $this->nanoseconds = Duration::NANOSECONDS_IN_SECS - ($duration->getNanoseconds() - $this->nanoseconds);
        }

        return $this;
    }

    public static function diff(Instant $from, Instant $to): Duration {
        return new Duration($to->getSeconds() - $from->getSeconds(), $to->getNanoseconds() - $from->getNanoseconds());
    }

    public static function now() {
        return Hacks::time();
    }

    public function __debugInfo(): ?array {
        return [
          'seconds' => $this->seconds,
          'nano'    => $this->nanoseconds,
          'float'   => rtrim(sprintf("%d.%06d", $this->seconds, $this->nanoseconds), '0'),
        ];
    }
}