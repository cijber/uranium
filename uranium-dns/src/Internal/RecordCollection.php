<?php

namespace Cijber\Uranium\Dns\Internal;

use ArrayIterator;
use Cijber\Collections\Iter;
use Cijber\Collections\IteratorFromAggregateTrait;
use Cijber\Uranium\Dns\ResourceRecord;


class RecordCollection extends Iter {
    use IteratorFromAggregateTrait;


    public array $labels = [];
    public array $byType = [];


    public function add(ResourceRecord $record) {
        if ( ! isset($this->byType[$record->type])) {
            $this->byType[$record->type] = [];
        }

        $this->byType[$record->type][] = $record;
    }

    public function forType(int $type): array {
        return $this->byType[$type] ?? [];
    }

    public function all(): array {
        return array_merge(...$this->byType);
    }

    function getIterator() {
        return new ArrayIterator($this->all());
    }
}