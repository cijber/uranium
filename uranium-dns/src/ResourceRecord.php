<?php

namespace Cijber\Uranium\Dns;

use RuntimeException;


class ResourceRecord extends PartialRecord
{
    static $isCompressed = false;


    const QCLASS_ANY = 255;

    public function __construct(
      array $labels,
      int $type,
      int $class,
      public int $ttl,
      protected ?string $data = null,
    ) {
        parent::__construct($labels, $type, $class);
    }

    public function getData(): string
    {
        return $this->data;
    }

    function process(string $packet, int $dataOffset)
    {
    }

    function getCompressedData(string $target = ""): string
    {
        return $this->data;
    }

    protected function writeHeader(string &$target = "")
    {
        $this->writeLabels($target);
        $target .= chr(($this->type >> 8) & 255) . chr($this->type & 255);
        $target .= chr(($this->class >> 8) & 255) . chr($this->class & 255);
        $target .= chr(($this->ttl >> 24) & 255) . chr(($this->ttl >> 16) & 255) . chr(($this->ttl >> 8) & 255) . chr($this->ttl & 255);
    }

    function toBytes(string &$target = ""): string
    {
        $this->writeHeader($target);
        if (static::$isCompressed) {
            $target         .= "\xFF\xFF";
            $compressedData = $this->getCompressedData($target);
            $dataLen        = strlen($compressedData);
            $target         .= substr($target, 0, strlen($target) - 2) . chr(($dataLen >> 8) & 255) . chr($dataLen & 255);
            $target         .= $compressedData;
        } else {
            $data   = $this->getData();
            $target .= chr((strlen($data) >> 8) & 255) . chr(strlen($data) & 255);
            $target .= $data;
        }

        return $target;
    }

    public function __debugInfo(): ?array
    {
        return [
          "ttl"    => $this->ttl,
          "labels" => $this->labels,
          "data"   => $this->data,
          "type"   => $this->type,
          "class"  => $this->class,
          "*type"  => (ResourceType::BY_TYPE[$this->type] ?? ["N/A"])[0],
          "*class" => (ResourceClass::BY_CLASS[$this->class] ?? "N/A"),
        ];
    }

    function processFields(array $fields)
    {
        throw new RuntimeException("No parsing support yet for $this->type (" . (ResourceType::BY_TYPE[$this->type] ?? ["N/A"])[0] . ")");
    }
}