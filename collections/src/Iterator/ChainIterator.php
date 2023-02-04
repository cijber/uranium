<?php

namespace Cijber\Collections\Iterator;

use Cijber\Collections\Iter;
use Iterator;


class ChainIterator extends Iter {
    private ?Iterator $current = null;
    private Iter $iterators;

    public function __construct(?iterable $iterators = null) {
        if ($iterators !== null) {
            $this->iterators = Iter::from($iterators);
        } else {
            $this->iterators = Iter::empty();
        }
    }

    public function current() {
        return $this->current?->current();
    }

    public function next() {
        if ($this->current === null) {
            if ( ! $this->nextIterator()) {
                return;
            }
        }

        while (1) {
            $this->current->next();

            if ($this->current->valid()) {

                return;
            }

            if ( ! $this->nextIterator()) {
                return;
            }
        }
    }

    protected function nextIterator(): bool {
        $this->iterators->next();

        if ( ! $this->iterators->valid()) {
            $this->current = null;

            return false;
        }

        $this->current = Iter::from($this->iterators->current());

        return true;
    }


    public function key() {
        return $this->current?->key();
    }

    public function valid() {
        return $this->current !== null && $this->current->valid();
    }
}