<?php

namespace Cijber\Uranium\Channel;

use Cijber\Collections\Iter;
use Cijber\Uranium\Helper\LoopAwareInterface;
use Cijber\Uranium\Helper\LoopAwareTrait;
use Cijber\Uranium\Loop;


abstract class Channel extends Iter implements LoopAwareInterface {
    use LoopAwareTrait;


    protected mixed $iteratorCurrent = null;
    protected int $iteratorKey = 0;
    protected bool $iteratorValid = false;

    abstract function waitWritable(): bool;

    abstract function write(mixed $data);

    abstract function tryWrite(mixed $data): bool;

    abstract function waitReadable(): bool;

    abstract function read(): mixed;

    abstract function tryRead(?bool &$found = false): mixed;

    abstract function close(): void;

    abstract function isClosed(): bool;

    abstract function isEmpty(): bool;

    abstract function isFull(): bool;

    abstract function isPeekable(): bool;

    abstract function peek(?bool &$found = false): mixed;

    public function current() {
        return $this->iteratorCurrent;
    }

    public function next() {
        $data = $this->tryRead($found);
        if ( ! $found) {
            if ($this->loop->getExecutor()->current() === null) {
                $this->loop->wait(fn() => $this->next());

                return;
            }

            do {
                $this->waitReadable();
                $data = $this->tryRead($found);
            } while ( ! $this->isClosed() && ! $found);
        }

        if ($this->isClosed()) {
            $this->iteratorValid   = false;
            $this->iteratorCurrent = null;

            return;
        }

        $this->iteratorKey++;
        $this->iteratorValid   = true;
        $this->iteratorCurrent = $data;
    }

    public function key(): ?int {
        return $this->iteratorValid ? $this->iteratorKey : null;
    }

    public function valid(): bool {
        return $this->iteratorValid;
    }

    public static function bounded(int $size, ?Loop $loop = null): Bounded {
        return new Bounded($size, $loop);
    }

    public static function rendezvous(?Loop $loop = null): Rendezvous {
        return new Rendezvous($loop);
    }
}