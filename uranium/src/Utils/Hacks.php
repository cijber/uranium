<?php

namespace Cijber\Uranium\Utils;

use Cijber\Uranium\Time\Duration;
use Cijber\Uranium\Time\Instant;
use FFI;
use Throwable;


class Hacks {
    private static FFI $libc;

    public static bool $ffi = false;
    public static FFI\CType $timespec;

    public static function load() {
        Hacks::$ffi = class_exists('FFI', false);
        if (static::$ffi) {
            static::$libc = FFI::cdef(
              <<<HEADER

struct timespec {
   long int tv_sec;
   long int tv_nsec;
};

int clock_gettime(int32_t clk_id, struct timespec *tp);
HEADER
            );

            static::$timespec = static::$libc->type('struct timespec');
        }
    }

    public static function time(): Instant {
        if (static::$ffi) {
            $time = FFI::new(static::$timespec);
            static::$libc->clock_gettime(1, FFI::addr($time));

            return new Instant($time->tv_sec, $time->tv_nsec);
        } else {
            $time = microtime(true);

            return new Instant(floor($time), ($time % 1) * Duration::NANOSECONDS_IN_SECS);
        }
    }

    public static function errorHandler(callable $fn, ?Throwable &$error = null, bool $throw = false) {
        set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$error) {
            $error = new \ErrorException(
              $errstr,
              0,
              $errno,
              $errfile,
              $errline
            );
        });

        $data = $fn();

        restore_error_handler();

        if ($error !== null && $throw) {
            throw $error;
        }

        return $data;
    }
}

Hacks::load();