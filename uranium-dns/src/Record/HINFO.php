<?php

namespace Cijber\Uranium\Dns\Record;

use Cijber\Uranium\Dns\ResourceRecord;

class HINFO extends ResourceRecord
{
    public string $cpu;
    public string $os;

    public function process(string $packet, int $dataOffset)
    {
        $offset = 0;
        $cpuLen = ord($this->data[$offset++]);
        $this->cpu = substr($this->data, $offset, $cpuLen);
        $osLen = ord($this->data[$offset++]);
        $this->os = substr($this->data, $offset, $osLen);
    }

    public function getData(): string
    {
        $data = chr(strlen($this->cpu));
        $data .= $this->cpu;
        $data .= chr(strlen($this->os));
        $data .= $this->os;

        return $data;
    }

    /**
     * @return string
     */
    public function getCpu(): string
    {
        return $this->cpu;
    }

    /**
     * @return string
     */
    public function getOs(): string
    {
        return $this->os;
    }

    public function __debugInfo(): ?array
    {
        return [
                "cpu" => $this->getCpu(),
                "os" => $this->getOs(),
            ] + parent::__debugInfo();
    }

    public function processFields(array $fields)
    {
        $this->cpu = $fields[0];
        $this->os = $fields[1];
    }
}