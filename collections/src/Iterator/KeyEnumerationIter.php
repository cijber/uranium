<?php

namespace Cijber\Collections\Iterator;

use Cijber\Collections\Iter;


class KeyEnumerationIter extends Iter {
    private int $key = -1;
    private Iter $source;

    public function __construct(iterable $source) {
        $this->source = Iter::from($source);
    }

    public function current() {
        return $this->source->current();
    }

    public function next() {
        $this->source->next();
        $this->key++;
    }

    public function key() {
        return $this->source->valid() ? $this->key : null;
    }

    public function valid() {
        return $this->source->valid();
    }
}