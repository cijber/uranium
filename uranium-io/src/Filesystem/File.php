<?php

namespace Cijber\Uranium\IO\Filesystem;

use Cijber\Uranium\IO\PhpStream;
use Cijber\Uranium\Loop;


class File extends PhpStream
{
    public function __construct($stream, private string $path, ?Loop $loop = null)
    {
        parent::__construct($stream, $loop);
    }
}