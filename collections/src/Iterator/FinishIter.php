<?php

namespace Cijber\Collections\Iterator;

use Cijber\Collections\Iter;


class FinishIter extends Iter {
    private $fn;
    private Iter $source;
    private bool $valid = false;

    public function __construct(iterable $iterable, callable $fn) {
        $this->fn     = $fn;
        $this->source = Iter::from($iterable);
    }

    public function current() {
        return $this->source->current();
    }

    public function next() {
        $this->source->next();
        $this->valid = $this->source->valid();

        if ( ! $this->valid) {
            ($this->fn)();
        }
    }

    public function key() {
        return $this->source->key();
    }

    public function valid() {
        return $this->valid;
    }
}