<?php

namespace Cijber\Uranium\Dns\Record;

use Cijber\Uranium\Dns\ResourceClass;
use Cijber\Uranium\Dns\ResourceRecord;
use Cijber\Uranium\Dns\ResourceType;
use Cijber\Uranium\IO\Net\Address;
use RuntimeException;


class A extends ResourceRecord
{
    private Address $address;

    public static function create(array $labels, string|array|Address $address, int $ttl = 300, int $class = ResourceClass::IN): A
    {
        $item = new A($labels, ResourceType::A, $class, $ttl, "");
        $item->setAddress($address);

        return $item;
    }

    public function process(string $packet, int $dataOffset)
    {
        $this->address = Address::fromBytes($this->data);
    }

    public function setAddress(array|Address|string $address): void
    {
        Address::ensure($address);

        if ($address->getVersion() !== 4) {
            throw new RuntimeException("Non IPv4 given for A record");
        }

        $this->address = $address;
        $this->data    = $address->getBinary();
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function __debugInfo(): ?array
    {
        return ["data" => $this->getAddress()] + parent::__debugInfo();
    }

    public function processFields(array $fields)
    {
        $this->setAddress(Address::parse($fields[0]));
    }
}