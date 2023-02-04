<?php

namespace Cijber\Uranium\IO;


use Cijber\Uranium\IO\Filesystem\File;
use Cijber\Uranium\Loop;


class Filesystem {
    public static function open(string $path, string $mode = 'r', ?Loop $loop = null): Stream {
        $fp = fopen($path, $mode);

        if (str_contains($path, "://")) {
            return new PhpStream($fp, $loop);
        }

        return new File($fp, $path, $loop);
    }

    public static function slurp(string $path, ?Loop $loop = null): string {
        return static::open($path, loop: $loop)->slurp();
    }

    // TODO: maybe allow abstraction or smth
    public static function exists(string $path): bool {
        return file_exists($path);
    }
}