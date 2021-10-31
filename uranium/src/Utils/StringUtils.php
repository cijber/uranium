<?php

namespace Cijber\Uranium\Utils;

class StringUtils {
    static function englishJoin(array $items) {
        $last = array_pop($items);
        if (count($items) > 0) {
            return implode(", ", $items) . ' and ' . $last;
        } else {
            return $last;
        }
    }
}