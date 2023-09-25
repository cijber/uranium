<?php

namespace Cijber\Uranium\Dns\Record;

use Cijber\Uranium\Dns\Parser\RecordParser;
use Cijber\Uranium\Dns\ResourceRecord;
use Cijber\Uranium\Dns\Utils;


class MX extends ResourceRecord
{
    private int $priority = 0;
    private array $domain = [];

    public static $isCompressed = true;

    public function getDomain(): array
    {
        return $this->domain;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getData(): string
    {
        $data = chr($this->priority >> 8) . chr($this->priority & 255);
        $data .= self::writeUncompressedLabels($this->domain);
        return $data;
    }

    public function getCompressedData(string $target = ""): string
    {
        $data = chr($this->priority >> 8) . chr($this->priority & 255);
        $this->writeLabels($data, $target, $this->domain);

        return $data;
    }

    public function process(string $packet, int $dataOffset)
    {
        $this->priority = (ord($this->data[0]) << 8) + ord($this->data[1]);
        $offset = $dataOffset + 2;
        $this->domain = RecordParser::parseLabels($packet, $offset);
    }

    public function __debugInfo(): ?array
    {
        return [
                "data" => [
                    "priority" => $this->priority,
                    "domain" => $this->domain,
                ],
            ] + parent::__debugInfo();
    }

    public function processFields(array $fields)
    {
        $this->priority = intval($fields[0]);
        $this->domain = Utils::parseDomainField($fields[1], $this->labels);
    }
}