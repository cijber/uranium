<?php

namespace Cijber\Uranium\IO\Net\DomainResolver;

use Cijber\Uranium\IO\Net\Address;
use Cijber\Uranium\IO\Net\DomainResolver;


class SyncNative implements DomainResolver {
    public function resolve(string $domain): array {
        $rrs = [];
        $a   = dns_get_record($domain, DNS_A);
        foreach ($a as $r) {
            $rrs[] = Address::parse($r["ip"]);
        }

        $aaaa = dns_get_record($domain, DNS_AAAA);
        foreach ($aaaa as $r) {
            $rrs[] = Address::parse($r["ipv6"]);
        }

        return $rrs;
    }
}