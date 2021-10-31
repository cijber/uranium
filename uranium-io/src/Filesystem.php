<?php

namespace Cijber\Uranium\IO;


class Filesystem {
    public static function open(string $path, string $mode = 'r'): Stream {
        $fp = fopen($path, $mode);

        return new Stream($fp);
    }

    public static function slurp(string $path): string {
        return static::open($path)->slurp();
    }
}