<?php

namespace Cijber\Uranium\Dns\Parser;

class ZoneCursor {
    protected int $line = 0;

    public function __construct(
      private string $data,
      private int $offset = 0,
    ) {
    }

    public function position(): int {
        return $this->offset;
    }

    public function eof(): bool {
        return $this->position() >= strlen($this->data);
    }

    public function peek(): string {
        if ($this->eof()) {
            return "";
        }

        return $this->data[$this->offset];
    }

    public function line(): int {
        return $this->line;
    }

    public function read(): string {
        if ($this->eof()) {
            return "";
        }

        $v = $this->data[$this->offset++];
        if ($v === "\n") {
            $this->line++;
        }

        return $v;
    }

    public function readTillSpace(): string {
        return $this->readTillCharacters([' ']);
    }

    public function readTillBlank(bool $newline = false): string {
        $items = ["\t", " "];

        if ($newline) {
            $items[] = "\n";
        }

        return $this->readTillCharacters($items);
    }

    public function readTillCharacters(array $characters): string {
        $old   = $this->position();
        $eofed = false;
        $data  = "";
        while ( ! in_array($item = $this->read(), $characters)) {
            if ($item === "\\" && ! $this->eof()) {
                $data .= $this->read();
            } else {
                $data .= $item;
            }

            if ($this->eof()) {
                $eofed = true;
                break;
            }
        }

        if ( ! $eofed) {
            $this->rewind();
        }

        return $data;
    }

    public function skipCharacters(array $characters) {
        if ($this->eof()) {
            return;
        }

        while (in_array($this->read(), $characters)) {
            if ($this->eof()) {
                return;
            }
        }

        $this->offset--;
    }

    public function skipBlank(bool $newline = false) {
        $items = ["\t", " "];

        if ($newline) {
            $items[] = "\n";
        }

        $this->skipCharacters($items);
    }

    public function readDelimited(): string {
        $d = $this->read();

        $escaped = false;
        $pos     = $this->position();
        while ( ! $this->eof() && (($c = $this->read()) !== $d && ! $escaped)) {
            if ($c === "\n") {
                // fuck you
            }

            if ($c === "\\") {
                $escaped = true;
            }
        }

        return substr($this->data, $pos, ($this->position() - $pos) - 1);
    }

    public function readField(): string {
        if ($this->peek() === '"') {
            return $this->readDelimited();
        } else {
            return $this->readTillCharacters(["\n", " ", ";", "\t", ")"]);
        }
    }

    public function readFields(): array {
        while ( ! $this->eof() && $this->peek() === "\n") {
            $this->read();
        }

        $parentheses = false;

        $fields = [];
        while ( ! $this->eof() && ($this->peek() !== "\n" || $parentheses)) {
            switch ($this->peek()) {
                case '(':
                    $this->read();
                    $parentheses = true;
                    break;
                case ')':
                    $this->read();
                    $parentheses = false;
                    break;
                case ';':
                    $this->skipTo("\n");
                    break;
                case "\n":
                    $this->read();
                    break;
                default:
                    $fields[] = $this->readField();
                    break;
            }

            $this->skipBlank();
        }

        return $fields;
    }

    private function rewind(int $amount = 1) {
        $this->offset -= $amount;
    }

    private function skipTo(string $needle) {
        $v = strpos($this->data, $needle, $this->offset);
        if ($v === false) {
            $this->offset = strlen($this->data);
        } else {
            $this->offset = $v;
        }
    }
}