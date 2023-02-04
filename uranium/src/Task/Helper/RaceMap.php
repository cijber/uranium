<?php

namespace Cijber\Uranium\Task\Helper;

use ArrayIterator;
use Cijber\Collections\Iter;
use Cijber\Uranium\Channel\Bounded;
use Cijber\Uranium\Channel\Channel;
use Cijber\Uranium\Loop;
use JetBrains\PhpStorm\Pure;


class RaceMap extends Iter {
    const DEFAULT_CONCURRENCY = 5;
    private int $concurrent;

    private int $running = 0;

    private Bounded $channel;

    private Loop $loop;

    private iterable $input;

    public function __construct(
      iterable $input,
      private $map,
      ?int $concurrent = null,
      ?int $queueSize = null,
      ?Loop $loop = null,
    ) {
        if (is_array($input)) {
            $this->input = new ArrayIterator($input);
        } else {
            $this->input = $input;
        }

        $this->concurrent = $concurrent ?: self::DEFAULT_CONCURRENCY;

        if ($this->concurrent < 1) {
            $this->concurrent = 1;
        }

        $this->loop = $loop ?: Loop::get();
        $this->channel = Channel::bounded($queueSize ?: $this->concurrent, $this->loop);
    }

    public function current() {
        $data = $this->channel->current();
        if ($data === null) {
            return null;
        }

        return $data[1];
    }

    public function next() {
        if ($this->loop->getExecutor()->current() === null) {
            $this->loop->wait(fn() => $this->next());

            return;
        }

        if ($this->channel->isEmpty() && $this->channel->isClosed()) {
            return;
        }

        while ($this->running < $this->concurrent && ! $this->channel->isFull() && $this->input->valid()) {
            $data = $this->input->current();
            $key  = $this->input->key();
            $this->loop->queue(fn() => $this->channel->write([$key, ($this->map)($data)]));
            $this->running++;
            $this->input->next();
        }


        if ($this->running === 0 && ! $this->channel->isClosed()) {
            $this->channel->close();
            $this->channel->next();

            return;
        }

        if ($this->running > 0) {
            $this->channel->next();
            $this->running--;
        }
    }

    #[Pure]
    public function key() {
        $data = $this->channel->current();
        if ($data === null) {
            return null;
        }

        return $data[0];
    }

    public function valid() {
        return $this->channel->valid();
    }
}