<?php

namespace Cijber\Uranium\Dns\Record;

use Cijber\Uranium\Dns\Parser\RecordParser;
use Cijber\Uranium\Dns\ResourceRecord;
use Cijber\Uranium\Dns\Utils;


abstract class DomainName extends ResourceRecord
{
    private array $domain = [];

    public static $isCompressed = true;


    public function getDomain(): array
    {
        return $this->domain;
    }

    public function getDomainString(): string
    {
        return implode(".", $this->domain) . ".";
    }

    public function process(string $packet, int $dataOffset)
    {
        $this->domain = RecordParser::parseLabels($packet, $dataOffset);
    }

    public function getCompressedData(string $target = ""): string
    {
        $data = "";
        $this->writeLabels($data, $target, $this->domain);

        return $data;
    }

    public function getData(): string
    {
        return self::writeUncompressedLabels($this->domain);
    }

    public function __debugInfo(): ?array
    {
        return [
                "data" => $this->getDomain(),
            ] + parent::__debugInfo();
    }

    public function processFields(array $fields)
    {
        $this->domain = Utils::parseDomainField($fields[0], $this->labels);
    }
}