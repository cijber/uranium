<?php

namespace Cijber\Uranium\Dns;


use RuntimeException;


class ResourceType {
    const        BY_TYPE = [
      self::A      => ["A", Record\A::class],
      self::NS     => ["NS", Record\NS::class],
      self::MD     => ["MD", ResourceRecord::class],
      self::MF     => ["MF", ResourceRecord::class],
      self::CNAME  => ["CNAME", Record\CNAME::class],
      self::SOA    => ["SOA", Record\SOA::class],
      self::MB     => ["MB", ResourceRecord::class],
      self::MG     => ["MG", ResourceRecord::class],
      self::MR     => ["MR", ResourceRecord::class],
      self::NULL   => ["NULL", ResourceRecord::class],
      self::WKS    => ["WKS", ResourceRecord::class],
      self::PTR    => ["PTR", ResourceRecord::class],
      self::HINFO  => ["HINFO", Record\HINFO::class],
      self::MINFO  => ["MINFO", ResourceRecord::class],
      self::MX     => ["MX", Record\MX::class],
      self::TXT    => ["TXT", Record\TXT::class],
      # RFC 2535
      self::KEY    => ["KEY", ResourceRecord::class],
      # RFC 2671
      self::OPT    => ["OPT", ResourceRecord::class],
      # RFC 3596
      self::AAAA   => ["AAAA", Record\AAAA::class],
      # RFC 4034
      self::DNSKEY => ["DNSKEY", ResourceRecord::class],
      self::RRSIG  => ["RRSIG", ResourceRecord::class],
      self::NSEC   => ["NSEC", ResourceRecord::class],
      self::DS     => ["DS", ResourceRecord::class],
      # QTYPE's
      self::AXFR   => ["AXFR", QuestionRecord::class],
      self::MAILA  => ["MAILA", QuestionRecord::class],
      self::MAILB  => ["MAILB", QuestionRecord::class],
      self::ANY    => ["ANY", QuestionRecord::class],
    ];

    const BY_NAME = [
      "A"      => self::A,
      "NS"     => self::NS,
      "MD"     => self::MD,
      "MF"     => self::MF,
      "CNAME"  => self::CNAME,
      "SOA"    => self::SOA,
      "MB"     => self::MB,
      "MG"     => self::MG,
      "MR"     => self::MR,
      "NULL"   => self::NULL,
      "WKS"    => self::WKS,
      "PTR"    => self::PTR,
      "HINFO"  => self::HINFO,
      "MINFO"  => self::MINFO,
      "MX"     => self::MX,
      "TXT"    => self::TXT,

      # RFC 2535
      "KEY"    => self::KEY,
      # RFC 2671
      "OPT"    => self::OPT,
      # RFC 3596
      "AAAA"   => self::AAAA,
      # RFC 4034
      "DNSKEY" => self::DNSKEY,
      "RRSIG"  => self::RRSIG,
      "NSEC"   => self::NSEC,
      "DS"     => self::DS,

      # QTYPE's
      "AXFR"   => self::AXFR,
      "MAILA"  => self::MAILA,
      "MAILB"  => self::MAILB,
      "ANY"    => self::ANY,
    ];

    public const A     = 1;
    public const NS    = 2;
    public const MD    = 3;
    public const MF    = 4;
    public const CNAME = 5;
    public const SOA   = 6;
    public const MB    = 7;
    public const MG    = 8;
    public const MR    = 9;
    public const NULL  = 10;
    public const WKS   = 11;
    public const PTR   = 12;
    public const HINFO = 13;
    public const MINFO = 14;
    public const MX    = 15;
    public const TXT   = 16;

    # RFC 2535
    public const KEY = 25;

    # RFC 2671
    public const OPT = 41;

    # RFC 3596
    public const AAAA = 28;

    # RFC 4034
    public const DNSKEY = 48;
    public const RRSIG  = 46;
    public const NSEC   = 47;
    public const DS     = 43;

    public static function getName(int $type): string {
        return self::BY_TYPE[$type][0] ?? "*N/A";
    }

    public static function parseRecord(array $labels, string $class, int $ttl, string $type, array $fields): ?ResourceRecord {
        if ($class !== "IN") {
            throw new RuntimeException("We are cowards and only support IN records");
        }

        if ( ! isset(static::BY_NAME[$type])) {
            throw new RuntimeException("No support yet for $type records");
        }


        $resource = new (static::BY_TYPE[static::BY_NAME[$type]][1])($labels, static::BY_NAME[$type], ResourceClass::BY_NAME[$class], $ttl, "");
        $resource->processFields($fields);

        return $resource;
    }


    const AXFR  = 252;
    const MAILB = 253;
    const MAILA = 254;
    const ANY   = 255;
}