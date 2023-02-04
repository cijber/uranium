<?php

namespace Cijber\Uranium\IO\Net\DomainResolver;

use Cijber\Uranium\Dns\Client;
use Cijber\Uranium\IO\Net\DomainResolver;
use Cijber\Uranium\Loop;


class UraniumDns implements DomainResolver {
    private Client $client;
    private Loop $loop;

    public function __construct(?Client $client = null, ?Loop $loop = null) {
        $this->loop   = $loop ?: ($client ? $client->getLoop() : Loop::get());
        $this->client = $client ?: Client::instance($this->loop);
    }

    public function resolve(string $domain): array {
        return $this->client->getAddress($domain);
    }
}