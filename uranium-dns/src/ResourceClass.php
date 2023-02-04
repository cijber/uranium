<?php

namespace Cijber\Uranium\Dns;

class ResourceClass {
    public const IN = 1;
    public const CS = 2;
    public const CH = 3;
    public const HS = 4;

    const BY_CLASS = [
      self::IN => "IN",
      self::CS => "CS",
      self::CH => "CH",
      self::HS => "HS",
    ];

    const BY_NAME = [
      "IN" => self::IN,
      "CS" => self::CS,
      "CH" => self::CH,
      "HS" => self::HS,
    ];

    public static function getName(int $class): string {
        return self::BY_CLASS[$class] ?? "*N/A";
    }
}