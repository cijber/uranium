<?php

namespace Cijber\Uranium\Dns;

class Utils {
    public static function parseDomainField(string $field, array $origin) {
        if (str_ends_with($field, ".")) {
            return explode(".", rtrim($field, "."));
        } else {
            return array_merge(explode(".", $field), $origin);
        }
    }
}