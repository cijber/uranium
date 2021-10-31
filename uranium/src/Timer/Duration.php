<?php

namespace Cijber\Uranium\Timer;

class Duration {
    const NANOSECONDS_IN_SECS = 1_000_000_000;

    public static function seconds(int $secs) {
        return new Duration($secs, 0);
    }

    public static function milliseconds(int $milliseconds) {
        return new Duration(floor($milliseconds / 1_000), ($milliseconds % 1_000) * 1_000_000);
    }

    public function __construct(
      private int $seconds = 0,
      private int $nanoseconds = 0,
    ) {
        $this->normalize();
    }

    public function normalize() {
        if (abs($this->nanoseconds) > Duration::NANOSECONDS_IN_SECS) {
            $this->seconds     += floor($this->nanoseconds / Duration::NANOSECONDS_IN_SECS);
            $this->nanoseconds += $this->nanoseconds % Duration::NANOSECONDS_IN_SECS;
        }

        if ($this->seconds > 0 && $this->nanoseconds < 0) {
            $this->seconds     -= 1;
            $this->nanoseconds = Duration::NANOSECONDS_IN_SECS + $this->nanoseconds;
        }
    }

    public static function fromNow(Instant $to) {
        return Instant::diff(Instant::now(), $to);
    }

    public function getNanoseconds(): int {
        return $this->nanoseconds;
    }

    public function getSeconds(): int {
        return $this->seconds;
    }

    public function getMicroseconds(): int {
        return (int)floor($this->nanoseconds / 1000);
    }

    public function add(Duration $duration): Duration {
        $this->nanoseconds += $duration->nanoseconds;
        $this->seconds     += $duration->seconds;
        $this->normalize();

        return $this;
    }

    public function sub(Duration $duration) {
        $this->nanoseconds -= $duration->nanoseconds;
        $this->seconds     -= $duration->seconds;
        $this->normalize();

        return $this;
    }
}