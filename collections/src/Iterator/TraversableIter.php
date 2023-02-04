<?php

namespace Cijber\Collections\Iterator;

use Cijber\Collections\Iter;
use IteratorAggregate;


class TraversableIter extends Iter {
    private int|string|null $key = null;
    private bool $rewound = false;

    public function __construct(private $source) {
        if ($this->source instanceof IteratorAggregate) {
            $this->source = $this->source->getIterator();
        }
    }

    public function current() {
        return $this->source[$this->key];
    }

    public function next() {
        if (!$this->rewound) {
            $this->rewound = true;
            reset($this->source);
        } else {
            next($this->source);
        }

        $this->key = key($this->source);
    }

    public function key() {
        return $this->key;
    }

    public function valid() {
        return $this->key !== null;
    }

    public function reset() {
        reset($this->source);
        $this->key = key($this->source);
    }
}