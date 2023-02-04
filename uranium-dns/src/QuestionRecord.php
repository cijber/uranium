<?php

namespace Cijber\Uranium\Dns;

class QuestionRecord extends PartialRecord {
    function toBytes(string &$target = ""): string {
        $this->writeLabels($target);
        $target .= chr(($this->getType() >> 8) & 255) . chr($this->getType() & 255);
        $target .= chr(($this->getClass() >> 8) & 255) . chr($this->getClass() & 255);

        return $target;
    }


    public function __toString(): string {
        return ResourceType::getName($this->type) . " " . ResourceClass::getName($this->class) . ' ' . $this->getLabelString();
    }
}