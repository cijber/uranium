<?php

namespace Cijber\Collections;

use Iterator;


trait IteratorFromAggregateTrait {
    protected ?Iterator $iterator = null;

    abstract function getIterator();

    private function useIterator(): Iterator {
        if ($this->iterator !== null) {
            return $this->iterator;
        }

        return $this->iterator = $this->getIterator();
    }

    public function current() {
        return $this->useIterator()->current();
    }

    public function next() {
        $this->useIterator()->next();
    }

    public function key() {
        return $this->useIterator()->key();
    }

    public function valid() {
        return $this->useIterator()->valid();
    }

    public function reset() {
        $this->iterator = null;
        $this->useIterator()->rewind();
    }
}