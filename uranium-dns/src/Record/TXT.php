<?php

namespace Cijber\Uranium\Dns\Record;

use Cijber\Uranium\Dns\ResourceRecord;


class TXT extends ResourceRecord
{
    private array $items = [];

    public function getItems(): array
    {
        return $this->items;
    }

    public function getData(): string
    {
        $data = "";
        foreach ($this->items as $item) {
            $data .= chr(strlen($item));
            $data .= $item;
        }

        return $data;
    }

    public function process(string $packet, int $dataOffset)
    {
        $offset = 0;
        while ($offset < strlen($this->data)) {
            $i = chr($this->data[$offset++]);
            $this->items[] = substr($this->data, $offset, $i);
            $offset += $i;
        }
    }

    public function __debugInfo(): ?array
    {
        return [
                "data" => $this->getItems(),
            ] + parent::__debugInfo();
    }

    public function processFields(array $fields)
    {
        $this->items = $fields;
    }
}