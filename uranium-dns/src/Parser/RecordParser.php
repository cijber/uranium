<?php

namespace Cijber\Uranium\Dns\Parser;

use Cijber\Uranium\Dns\QuestionRecord;
use Cijber\Uranium\Dns\ResourceRecord;
use Cijber\Uranium\Dns\ResourceType;
use RuntimeException;


class RecordParser {
    public static function parseLabels(string $data, int &$offset = 0, bool $allowPointer = true): array {
        $labels = [];
        while (($l = ord($data[$offset++])) !== 0) {
            if ($l >= 192) {
                // Pointer
                $ptr = ($l & ~192) << 8;
                $ptr += ord($data[$offset++]);

                if ($ptr >= strlen($data)) {
                    throw new RuntimeException("Invalid label pointer");
                }

                return array_merge($labels, static::parseLabels($data, $ptr, false));
            } else {
                $labels[] = substr($data, $offset, $l);
                $offset   += $l;
            }
        }

        return $labels;
    }

    public static function parsePartial(string $data, int &$offset = 0): array {
        $labels = static::parseLabels($data, $offset);
        $type   = (ord($data[$offset++]) << 8) + ord($data[$offset++]);
        $class  = (ord($data[$offset++]) << 8) + ord($data[$offset++]);

        return [$labels, $type, $class];
    }

    public static function parseQuestion(string $data, int &$offset = 0): QuestionRecord {
        [$labels, $type, $class] = static::parsePartial($data, $offset);

        return new QuestionRecord($labels, $type, $class);
    }

    public static function parseResource(string $data, int &$offset = 0): ResourceRecord {
        [$labels, $type, $class] = static::parsePartial($data, $offset);
        $ttl        = (ord($data[$offset++]) << 24) + (ord($data[$offset++]) << 16) + (ord($data[$offset++]) << 8) + ord($data[$offset++]);
        $dataLength = (ord($data[$offset++]) << 8) + ord($data[$offset++]);
        $recordData = substr($data, $offset, $dataLength);
        $dataOffset = $offset;
        $offset     += $dataLength;

        $recordClass = ResourceRecord::class;

        if (isset(ResourceType::BY_TYPE[$type])) {
            $recordClass = ResourceType::BY_TYPE[$type][1];
        }

        /** @var ResourceRecord $record */
        $record = new ($recordClass)($labels, $type, $class, $ttl, $recordData);
        $record->process($data, $dataOffset);

        return $record;
    }
}