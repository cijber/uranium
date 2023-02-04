<?php

namespace Cijber\Collections;

use Cijber\Collections\Iterator\ChainIterator;
use Cijber\Collections\Iterator\FilterIter;
use Cijber\Collections\Iterator\FinishIter;
use Cijber\Collections\Iterator\IteratorIter;
use Cijber\Collections\Iterator\KeyEnumerationIter;
use Cijber\Collections\Iterator\MapIter;
use Cijber\Collections\Iterator\TraversableIter;
use Iterator;
use RuntimeException;


abstract class Iter implements Iterator {
    public static function empty() {
        return self::from([]);
    }

    public function map(callable $mapper): Iter {
        return new MapIter($this, $mapper);
    }

    public function filter(callable $filter): Iter {
        return new FilterIter($this, $filter);
    }

    public function finish(callable $finish): Iter {
        return new FinishIter($this, $finish);
    }

    public function flatten(): Iter {
        return new ChainIterator($this);
    }

    public function flatMap(callable $mapper): Iter {
        return $this->map($mapper)->flatten();
    }

    public function rekey(): Iter {
        return new KeyEnumerationIter($this);
    }

    public function collect(bool $retainKeys = false): array {
        $target = [];
        if ($retainKeys) {
            foreach ($this as $key => $value) {
                $target[$key] = $value;
            }
        } else {
            foreach ($this as $value) {
                $target[] = $value;
            }
        }

        return $target;
    }

    public function consume(): void {
        while ($this->valid()) {
            $this->next();
        }
    }

    public function reset() {
        throw new RuntimeException(get_class($this) . " does not support ->reset()");
    }

    public function rewind() {
        $this->next();
    }

    public static function from(iterable $source): Iter {
        if ($source instanceof Iter) {
            return $source;
        }

        if ($source instanceof Iterator) {
            return new IteratorIter($source);
        }

        return new TraversableIter($source);
    }

    public static function generate(callable $generator): Iter {
        $generator = $generator();

        return new IteratorIter($generator);
    }


    public static function range(int $from, ?int $to = null, int $step = 1): Iter {
        return Iter::generate(function() use ($from, $to, $step) {
            $to ??= PHP_INT_MAX;

            for ($i = $from; $i < $to; $i += $step) {
                yield $i;
            }
        });
    }

    private static array $extensions = [];

    public function __call(string $name, array $arguments) {
        if ( ! isset(Iter::$extensions[$name])) {
            throw new RuntimeException("Function $name does not exist on " . get_class($this));
        }

        return (Iter::$extensions[$name])($this, ...$arguments);
    }

    public static function registerExtension(string $name, callable $function) {
        if (isset(Iter::$extensions[$name])) {
            throw new RuntimeException("Extension for Iter by the name $name already exists");
        }

        Iter::$extensions[$name] = $function;
    }
}