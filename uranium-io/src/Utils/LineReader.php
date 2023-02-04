<?php

namespace Cijber\Uranium\IO\Utils;

use Cijber\Uranium\IO\Stream;
use Iterator;


class LineReader implements Iterator {
    private string $buffer = "";
    private bool $hasInput = false;
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
        while ( ! str_contains($this->buffer, "\n") && ! $this->stream->eof()) {
            $this->buffer   .= $this->stream->read();
            $this->hasInput = true;
        }

        $pos = strpos($this->buffer, "\n");
        if ($pos === false) {
            $this->current  = $this->hasInput ? $this->buffer : null;
            $this->buffer   = null;
            $this->hasInput = false;

            return;
        }

        for ($i = $pos; $i < strlen($this->buffer); $i++) {
            if ($this->buffer[$i] !== "\n") {
                break;
            }
        }

        $newLines = $i - $pos;

        $line         = substr($this->buffer, 0, $pos + ($this->stripNewline ? 0 : $newLines));
        $this->buffer = substr($this->buffer, $pos + $newLines);

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