<?php

namespace Cijber\Uranium\IO\Utils;

use Cijber\Uranium\IO\Stream;
use Iterator;


class LineReader implements Iterator {
    private string $buffer = "";
    private ?string $current = null;

    public function __construct(
      private Stream $stream,
      private bool $stripNewline = false,
    ) {
    }


    public function current() {
        return $this->current;
    }

    public function next() {
        while ( ! str_contains($this->buffer, "\n")) {
            $this->buffer .= $this->stream->read();
        }

        $pos          = strpos($this->buffer, "\n");
        $line         = substr($this->buffer, 0, $pos + ($this->stripNewline ? 0 : 1));
        $this->buffer = substr($this->buffer, $pos + 1);

        $this->current = $line;
    }

    public function key() {
        return null;
    }

    public function valid() {
        return $this->current !== null;
    }

    public function rewind() {
        // Rewind is called before entering a foreach, and to make sure we always have fresh data, just call next LOL
        $this->next();
    }
}