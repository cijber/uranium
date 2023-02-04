<?php

namespace Cijber\Uranium\IO\Filesystem;

use Cijber\Uranium\IO\PhpStream;


class File extends PhpStream {
    public function __construct($stream, private string $path) {
        parent::__construct($stream);
    }
}