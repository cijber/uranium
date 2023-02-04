<?php

namespace Cijber\Collections\Iterator;

use Cijber\Collections\Iter;
use Iterator;


class FilterIter extends Iter {
    private $filter;
    private mixed $current = null;
    private bool $filtered = false;

    public function __construct(private Iterator $child, callable $filter) {
        $this->filter = $filter;
    }

    public function current() {
        return $this->current;
    }

    public function next() {
        $this->filtered = false;
        $this->current  = null;

        while (1) {
            $this->child->next();
            if ( ! $this->child->valid()) {
                break;
            }

            $value = $this->child->current();
            if (($this->filter)($value)) {
                $this->filtered = true;
                $this->current  = $value;
                break;
            }
        }
    }

    public function key() {
        return $this->child->key();
    }

    public function valid() {
        return $this->filtered;
    }
}