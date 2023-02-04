<?php

namespace Cijber\Uranium\Dns\Internal;

use Cijber\Uranium\Dns\ResourceRecord;


class CachedResponse {
    /**
     * @var ResourceRecord[]
     */
    private array $responseRecords = [];

    /**
     * @var ResourceRecord[]
     */
    private array $nameserverRecords = [];

    private int $when;

    public function __construct(
      array $responseRecords = [],
      array $nameserverRecords = [],
      ?int $when = null,
    ) {
        $this->responseRecords   = $responseRecords;
        $this->nameserverRecords = $nameserverRecords;
        $this->when              = $when === null ? time() : $when;
    }

    public function updateTtl(): void {
        $now = time();

        if ($now === $this->when) {
            return;
        }

        $timedPassed = $now - $this->when;
        foreach ($this->responseRecords as $record) {
            $record->ttl -= $timedPassed;
        }

        foreach ($this->nameserverRecords as $record) {
            $record->ttl -= $timedPassed;
        }

        $this->when = $now;
    }

    public function getResponseRecords(): array {
        return $this->responseRecords;
    }

    public function getNameserverRecords(): array {
        return $this->nameserverRecords;
    }
}