<?php

namespace Cijber\Uranium\Task\Helper;

use Cijber\Collections\Iter;
use Cijber\Uranium\Channel\Rendezvous;
use Cijber\Uranium\Loop;


class SelectLoop extends Iter {
    private $current = null;
    private $currentKey = null;
    private $tasks = [];

    private Rendezvous $channel;
    private Loop $loop;

    public function __construct(private array $funcs = [], ?Loop $loop = null) {
        $this->loop    = $loop ?: Loop::get();
        $this->channel = new Rendezvous();
    }

    public function current() {
        return $this->current;
    }

    public function pushTake(callable $func) {
        $this->funcs[] = $func;
    }

    public function addTask(string $key, callable $func) {
        $this->funcs[$key] = $func;
    }

    public function next() {
        foreach ($this->funcs as $key => $task) {
            if ( ! isset($this->tasks[$key])) {
                $this->tasks[$key] = $this->loop->queue(function() use ($key, $task) {
                    while (1) {
                        $value = $task();
                        $this->channel->write([$key, $value]);
                    }
                });
            }
        }

        [$key, $value] = $this->channel->read();

        $this->currentKey = $key;
        $this->current    = $value;
    }

    public function key() {
        return $this->currentKey;
    }

    public function valid() {
        return $this->currentKey !== null;
    }
}