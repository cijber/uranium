<?php

namespace Cijber\Collections\Iterator;

use Cijber\Collections\Iter;
use Iterator;


class MapIter extends Iter {
    private $mapper;
    private mixed $current = null;
    private bool $mapped = false;

    public function __construct(private Iterator $child, callable $mapper) {
        $this->mapper = $mapper;
    }

    public function current() {
        if ( ! $this->mapped) {
            $this->current = ($this->mapper)($this->child->current());
            $this->mapped  = true;
        }

        return $this->current;
    }

    public function next() {
        $this->mapped  = false;
        $this->current = null;
        $this->child->next();
    }

    public function key() {
        return $this->child->key();
    }

    public function valid() {
        return $this->child->valid();
    }
}