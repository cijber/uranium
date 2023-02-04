<?php

namespace Cijber\Collections\Iterator;

use Cijber\Collections\Iter;
use Iterator;


class IteratorIter extends Iter {
    private bool $rewinded = false;

    public function __construct(private Iterator $iterator) {
    }

    public function current() {
        return $this->iterator->current();
    }

    public function next() {
        if ( ! $this->rewinded) {
            $this->rewinded = true;
            $this->iterator->rewind();
        } else {
            $this->iterator->next();
        }
    }

    public function key() {
        return $this->iterator->key();
    }

    public function valid() {
        return $this->iterator->valid();
    }
}