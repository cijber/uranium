<?php

namespace Cijber\Uranium\Dns\Parser;

use Cijber\Uranium\IO\Filesystem;
use Cijber\Uranium\IO\Net\Address;


class SystemConfig {
    const HOSTS_FILE       = "/etc/hosts";
    const RESOLV_CONF_FILE = "/etc/resolv.conf";

    /**
     * @return Address[]
     */
    public static function fetchSystemResolvers(?string $resolvConf = null): array {
        $resolvConf ??= static::RESOLV_CONF_FILE;

        if ( ! Filesystem::exists($resolvConf)) {
            return [];
        }

        $file  = Filesystem::slurp($resolvConf);
        $lines = explode("\n", $file);

        $resolvers = [];
        foreach ($lines as $line) {
            [$name, $value] = preg_split("/\s+/", trim($line), 2) + [null, null];

            if ($name === "nameserver") {
                $resolvers[] = Address::parse(trim($value));
            }
        }

        return $resolvers;
    }

    /**
     * @param  string  $source
     *
     * @return array<string,Address[]>
     */
    public static function fetchStaticHosts(string $source = self::HOSTS_FILE): array {
        if ( ! Filesystem::exists($source)) {
            return [];
        }

        $file  = Filesystem::slurp($source);
        $lines = explode("\n", $file);

        $hosts = [];

        foreach ($lines as $line) {
            $line = trim($line);


            [$line, $comment] = preg_split("/\s*[;#]/", $line, 2) + ["", null];

            if (strlen($line) === 0) {
                continue;
            }

            $parts = preg_split("/\s+/", $line);
            $ip    = array_shift($parts);

            foreach ($parts as $host) {
                $host = rtrim($host, '.') . '.';

                if ( ! isset($hosts[$host])) {
                    $hosts[$host] = [];
                }

                $hosts[$host][] = Address::parse($ip);
            }
        }

        return $hosts;
    }
}