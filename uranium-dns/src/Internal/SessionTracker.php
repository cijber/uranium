<?php

namespace Cijber\Uranium\Dns\Internal;

class SessionTracker
{
    public function __construct(private string $handle, private string $type, private int $connections = 0)
    {
    }

    public function inc(): void
    {
        $this->connections++;
    }

    public function connections(): int
    {
        return $this->connections;
    }

    public function dec(): void
    {
        $this->connections--;
    }

    public function handle(): string
    {
        return $this->handle;
    }

    public function type(): string
    {
        return $this->type;
    }
}