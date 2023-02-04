<?php

namespace Cijber\Uranium\Dns\Record;

use Cijber\Uranium\Dns\Parser\RecordParser;
use Cijber\Uranium\Dns\ResourceRecord;
use RuntimeException;


class SOA extends ResourceRecord
{
    public static $isCompressed = true;

    private array $mname = [];
    private array $rname = [];
    private int $serial = 0;
    private int $refresh = 0;
    private int $retry = 0;
    private int $expire = 0;
    private int $minimum = 0;

    public function process(string $packet, int $dataOffset)
    {
        $this->mname   = RecordParser::parseLabels($packet, $dataOffset);
        $this->rname   = RecordParser::parseLabels($packet, $dataOffset);
        $this->serial  = (ord($packet[$dataOffset++]) << 24) + (ord($packet[$dataOffset++]) << 16) + (ord($packet[$dataOffset++]) << 8) + ord($packet[$dataOffset++]);
        $this->refresh = (ord($packet[$dataOffset++]) << 24) + (ord($packet[$dataOffset++]) << 16) + (ord($packet[$dataOffset++]) << 8) + ord($packet[$dataOffset++]);
        $this->retry   = (ord($packet[$dataOffset++]) << 24) + (ord($packet[$dataOffset++]) << 16) + (ord($packet[$dataOffset++]) << 8) + ord($packet[$dataOffset++]);
        $this->expire  = (ord($packet[$dataOffset++]) << 24) + (ord($packet[$dataOffset++]) << 16) + (ord($packet[$dataOffset++]) << 8) + ord($packet[$dataOffset++]);
        $this->minimum = (ord($packet[$dataOffset++]) << 24) + (ord($packet[$dataOffset++]) << 16) + (ord($packet[$dataOffset++]) << 8) + ord($packet[$dataOffset++]);
    }

    public function getCompressedData(string $target = ""): string
    {
        $data = "";
        $this->writeLabels($data, $target . $data, $this->mname);
        $this->writeLabels($data, $target . $data, $this->rname);
        $data .= chr(($this->serial >> 24) & 255) . chr(($this->serial >> 16) & 255) . chr(($this->serial >> 8) & 255) . chr($this->serial & 255);
        $data .= chr(($this->refresh >> 24) & 255) . chr(($this->refresh >> 16) & 255) . chr(($this->refresh >> 8) & 255) . chr($this->refresh & 255);
        $data .= chr(($this->retry >> 24) & 255) . chr(($this->retry >> 16) & 255) . chr(($this->retry >> 8) & 255) . chr($this->retry & 255);
        $data .= chr(($this->expire >> 24) & 255) . chr(($this->expire >> 16) & 255) . chr(($this->expire >> 8) & 255) . chr($this->expire & 255);
        $data .= chr(($this->minimum >> 24) & 255) . chr(($this->minimum >> 16) & 255) . chr(($this->minimum >> 8) & 255) . chr($this->minimum & 255);

        return $data;
    }

    public function __debugInfo(): ?array
    {
        return [
            "data" => [
              "mname"   => $this->mname,
              "rname"   => $this->rname,
              "serial"  => $this->serial,
              "refresh" => $this->refresh,
              "retry"   => $this->retry,
              "expire"  => $this->expire,
              "minimum" => $this->minimum,
            ],
          ] + parent::__debugInfo();
    }

    function processFields(array $fields)
    {
        if (count($fields) !== 7) {
            throw new RuntimeException("SOA record requires 7 items: mname, rname, serial, refresh, retry, expire and minimum");
        }

        $this->mname = explode(".", $fields[0]);
        $this->rname = explode(".", $fields[1]);

        $this->serial  = intval($fields[2]);
        $this->refresh = intval($fields[3]);
        $this->retry   = intval($fields[4]);
        $this->expire  = intval($fields[5]);
        $this->minimum = intval($fields[6]);
    }
}