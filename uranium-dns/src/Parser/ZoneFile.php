<?php

namespace Cijber\Uranium\Dns\Parser;

use Cijber\Uranium\Dns\ResourceType;
use Cijber\Uranium\IO\Filesystem;
use Throwable;


class ZoneFile {
    static function parse(string $data, null|array|string $origin = null, ?string $cwd = null): array {
        $rrs       = [];
        $cursor    = new ZoneCursor($data);
        $ttl       = 600;
        $class     = "IN";
        $lastLabel = null;

        if ($origin === null) {
            $origin = [];
        }

        if (is_string($origin)) {
            $origin = explode(".", rtrim($origin, "."));
        }

        while ( ! $cursor->eof()) {
            $line   = $cursor->line();
            $fields = $cursor->readFields();

            if (count($fields) === 0) {
                continue;
            }

            if (strlen($fields[0]) > 0) {
                switch ($fields[0]) {
                    case '$ORIGIN':
                        $origin = explode(".", rtrim($fields[1], "."));
                        break;
                    case '$INCLUDE':
                        if ($cwd !== null) {
                            $rrs = array_merge($rrs, static::read($cwd . '/' . $fields[1], $fields[2] ? explode(".", $fields[2]) : $origin));
                        }
                        break;
                    case '$TTL':
                        $ttl = intval($fields[1]);
                        break;
                    default:
                        $label = $fields[0];
                        if ($label === "@") {
                            $label = $origin;
                        } elseif ($label === ".") {
                            $label = [];
                        } elseif ( ! str_ends_with($label, ".") && $origin !== null) {
                            $label = array_merge(explode(".", $label), $origin);
                        } else {
                            $label = explode(".", rtrim($fields[0], "."));
                        }

                        $lastLabel = $label;
                        break;
                }
            }

            $label     = $lastLabel;
            $thisTtl   = $ttl;
            $thisClass = $class;
            $type      = null;
            for ($i = 1; $i < count($fields); $i++) {
                if (in_array($fields[$i], array_keys(ResourceType::BY_NAME))) {
                    $type = $fields[$i];
                    break;
                }

                if (ctype_digit($fields[$i])) {
                    $thisTtl = intval($fields[$i]);
                    continue;
                }

                $thisClass = $fields[$i];
            }

            if ($type === null) {
                continue;
            }

            try {
                $record = ResourceType::parseRecord($label, $thisClass, $thisTtl, $type, array_slice($fields, $i + 1));
                if ($record === null) {
                    continue;
                }

                $rrs[] = $record;
            } catch (Throwable $e) {
//                echo "Skipped record on line $line: " . $e->getMessage() . "\n";
            }
        }

        return $rrs;
    }

    static function read(string $filename, null|array|string $origin = null): array {
        $data = Filesystem::slurp($filename);

        return static::parse($data, $origin, dirname($filename));
    }

}