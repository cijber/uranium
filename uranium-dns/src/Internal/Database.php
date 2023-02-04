<?php

namespace Cijber\Uranium\Dns\Internal;

use Cijber\Collections\BTreeMap;
use Cijber\Uranium\Dns\QuestionRecord;
use Cijber\Uranium\Dns\ResourceClass;
use Cijber\Uranium\Dns\ResourceRecord;
use Cijber\Uranium\Dns\ResourceType;


class Database {
    private BTreeMap $zone;

    public function __construct() {
        $this->zone = new BTreeMap();
    }

    private function normalizeLabels(array $labels) {
        return array_map('strtolower', $labels);
    }

    public function add(ResourceRecord $record) {
        $collection = $this->zone->or($this->normalizeLabels($record->getLabels()), fn() => new RecordCollection());
        $collection->add($record);
    }

    public function get(QuestionRecord $record, ?bool &$found = false): array {
        if ($record->class !== ResourceClass::IN) {
            $found = false;

            return [];
        }

        /** @var RecordCollection $data */
        $data = $this->zone->get($this->normalizeLabels($record->getLabels()), $found);
        if ( ! $found) {
            return [];
        }

        if ($record->type === ResourceType::ANY) {
            return $data->all();
        }

        return $data->forType($record->type);
    }
}