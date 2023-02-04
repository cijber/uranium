<?php

namespace Cijber\Uranium\Task\Helper;

use Cijber\Collections\Iter;
use Cijber\Uranium\Loop;


class PrefetchMap extends Iter {
    const DEFAULT_CONCURRENCY = 5;
    private int $concurrent;
    private array $running = [];

    private array $results = [];
    private int $writeOffset = 0;
    private int $readOffset = 0;

    private $current = null;
    private $currentKey = null;
    private Iter $source;
    private bool $rewound = false;

    private Loop $loop;

    public function __construct(
      iterable $input,
      private $map,
      ?int $concurrent = null,
      ?Loop $loop = null,
    ) {
        $this->source = Iter::from($input)->rekey();

        $this->concurrent = $concurrent ?: self::DEFAULT_CONCURRENCY;

        if ($this->concurrent < 1) {
            $this->concurrent = 1;
        }

        $this->loop = $loop ?: Loop::get();
    }

    public function current() {
        return $this->current;
    }

    public function next() {
        if ( ! $this->rewound) {
            $this->rewound = true;
            $this->source->next();
        }

        while (count($this->running) < $this->concurrent && $this->source->valid()) {
            $i                 = $this->writeOffset++;
            $value             = $this->source->current();
            $key               = $this->source->key();
            $this->running[$i] = $this->loop->queue(fn() => $this->results[$i] = [$key, ($this->map)($value)]);
            $this->source->next();
        }

        if (count($this->running) === 0) {
            return;
        }

        try {
            $this->loop->suspend($this->running[$this->readOffset]->createWaker());
            [$this->currentKey, $this->current] = $this->results[$this->readOffset];
            unset($this->results[$this->readOffset]);
            unset($this->running[$this->readOffset]);
        } finally {
            $this->readOffset += 1;
        }
    }

    public function key() {
        return $this->currentKey;
    }

    public function valid() {
        return $this->currentKey !== null;
    }

    public function setConcurrent(int $concurrent): void {
        $this->concurrent = $concurrent;
    }
}