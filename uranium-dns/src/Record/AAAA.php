<?php

namespace Cijber\Uranium\Dns\Record;

use Cijber\Uranium\Dns\ResourceClass;
use Cijber\Uranium\Dns\ResourceRecord;
use Cijber\Uranium\Dns\ResourceType;
use Cijber\Uranium\IO\Net\Address;
use RuntimeException;


class AAAA extends ResourceRecord {
    private Address $address;

    public static function create(array $labels, string|array|Address $address, int $ttl = 300, int $class = ResourceClass::IN): AAAA {
        $item = new AAAA($labels, ResourceType::AAAA, $class, $ttl, "");
        $item->setAddress($address);

        return $item;
    }

    public function getAddress(): Address {
        return $this->address;
    }

    public function process(string $packet, int $dataOffset) {
        $this->address = Address::fromBytes($this->data);
    }

    public function __debugInfo(): ?array {
        return [
            "data" => $this->getAddress()->getAddress(),
          ] + parent::__debugInfo();
    }

    private function setAddress(Address|array|string $address) {
        Address::ensure($address);

        if ($address->getVersion() !== 6) {
            throw new RuntimeException("Non IPv6 given for AAAA record");
        }

        $this->address = $address;
        $this->data    = $address->getBinary();
    }

    public function processFields(array $fields) {
        $this->setAddress(Address::parse($fields[0]));
    }
}