<?php

namespace Cijber\Uranium\IO\Net;

interface DomainResolver {
    public function resolve(string $domain): array;
}