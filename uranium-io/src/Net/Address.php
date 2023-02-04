<?php

namespace Cijber\Uranium\IO\Net;

use Cijber\Uranium\Loop;
use Composer\InstalledVersions;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use RuntimeException;


class Address {
    /** @var array<string,DomainResolver> */
    private static array $resolver = [];

    public static function getResolver(?Loop $loop = null): DomainResolver {
        $loop ??= Loop::get();
        $id   = spl_object_id($loop);

        if ( ! isset(static::$resolver[$id])) {
            if (InstalledVersions::isInstalled("cijber/uranium-dns")) {
                $dns = new DomainResolver\UraniumDns();
            } else {
                $dns = new DomainResolver\SyncNative();
            }

            static::$resolver[$id] = $dns;
        }

        return self::$resolver[$id];
    }

    private int $version;
    private ?array $parts = null;
    private ?string $binary = null;
    private string $address;
    private ?int $port = null;

    private function __construct() {
    }

    public static function resolve(Address|string|array $address, ?Loop $loop = null): array {
        $loop ??= Loop::get();

        if (is_array($address)) {
            return [Address::fromParts($address)];
        }

        if ($address instanceof Address) {
            return [$address];
        }

        $port      = null;
        $lastColon = strrpos($address, ":");
        if ($lastColon !== false && (strpos($address, ":") === $lastColon || ($address[0] === "["))) {
            $port = intval(substr($address, $lastColon + 1));
            $address = substr($address, 0, $lastColon - 1);
        }

        $address = trim($address, "[]");

        $addr = inet_pton($address);
        if ($addr !== false) {
            return [Address::fromBytes($addr, $port)];
        }

        return array_map(fn(Address $address) => $address->withPort($port, false), static::getResolver($loop)->resolve($address));
    }

    public static function ensure(Address|string|array &$address) {
        if (is_string($address)) {
            $address = Address::parse($address);
        }

        if (is_array($address)) {
            $address = Address::fromParts($address);
        }
    }

    public static function parse(string $address, ?int $port = null): Address {
        if (strpos($address, ':') !== strrpos($address, ':')) {
            [$address, $port] = explode("]:", $address) + ["::", $port];

            $address = trim($address, "[]");
            $port    = is_string($port) ? intval($port) : $port;

            $version = 6;
        } elseif (str_contains($address, ".")) {
            [$address, $port] = explode(":", $address) + ["127.0.0.1", $port];
            $port    = is_string($port) ? intval($port) : $port;
            $version = 4;
        } else {
            throw new RuntimeException("Couldn't parse IP $address");
        }

        $item          = new static();
        $item->address = $address;
        $item->binary  = inet_pton($address);
        $item->version = $version;
        $item->port    = $port;

        return $item;
    }

    public static function fromParts(array $parts, ?int $port = null): Address {
        if (count($parts) === 4) {
            $item        = static::fromBytes(implode("", array_map('chr', $parts)), $port);
            $item->parts = $parts;

            return $item;
        }

        if (count($parts) === 8) {
            $item        = static::fromBytes(implode("", array_map(fn($v) => chr(($v >> 8) & 255) . chr($v & 255), $parts)), $port);
            $item->parts = $parts;

            return $item;
        }
    }

    public static function fromBytes(string $data, ?int $port = null): Address {
        if (strlen($data) === 4) {
            $item          = new static();
            $item->version = 4;
            $item->binary  = $data;
            $item->address = inet_ntop($data);
            $item->port    = $port;

            return $item;
        }

        if (strlen($data) === 16) {
            $item          = new static();
            $item->version = 6;
            $item->binary  = $data;
            $item->address = inet_ntop($data);
            $item->port    = $port;

            return $item;
        }
    }

    public function url(): string {
        if ($this->version === 6) {
            $url = "[" . $this->address . "]";
        } else {
            $url = $this->address;
        }

        if ($this->port !== null) {
            $url .= ':' . $this->port;
        }

        return $url;
    }

    public function getPort(): ?int {
        return $this->port;
    }

    public function getAddress(): string {
        return $this->address;
    }

    public function getVersion(): int {
        return $this->version;
    }

    public function withPort(?int $port, bool $clone = true): static {
        if ($clone) {
            $x = clone $this;
        } else {
            $x = $this;
        }

        $x->port = $port;

        return $x;
    }

    #[Pure]
    public function ensurePort(int $port): static {
        if ($this->port === null) {
            return $this->withPort($port);
        }

        return $this;
    }

    public function setPort(?int $port): void {
        $this->port = $port;
    }

    public function getParts(): array {
        if ($this->parts !== null) {
            return $this->parts;
        }

        if ($this->version === 4) {
            $this->parts = array_map('intval', explode(".", $this->address));

            return $this->parts;
        }

        if ($this->version === 6) {
            [$left, $right] = explode("::", $this->address) + [null, null];

            if ($left !== "") {
                $leftParts = array_map(fn($v) => intval($v, 16), explode(":", $left));
            } else {
                $leftParts = [];
            }

            if ($right !== null && $right !== "") {
                $rightParts = array_map(fn($v) => intval($v, 16), explode(":", $left));
            } else {
                $rightParts = [];
            }

            $parts = [0, 0, 0, 0, 0, 0, 0, 0];
            array_splice($parts, 0, count($leftParts), $leftParts);
            array_splice($parts, count($parts) - count($rightParts), count($rightParts), $rightParts);

            return $this->parts = $parts;
        }
    }

    public function getBinary(): string {
        if ($this->binary !== null) {
            return $this->binary;
        }

        if ($this->version === 4) {
            $parts = $this->getParts();

            return $this->binary = chr($parts[0]) . chr($parts[1]) . chr($parts[2]) . chr($parts[3]);
        }

        if ($this->version === 6) {
            $parts  = $this->getParts();
            $binary = "";

            foreach ($parts as $part) {
                $binary .= chr(($part >> 8) & 255) . chr($part & 255);
            }

            return $this->binary = $binary;
        }
    }

    #[Pure]
    public function __toString(): string {
        return $this->url();
    }

    #[Pure]
    #[ArrayShape(["address" => "string", "version" => "int", "port" => "int|null"])]
    public function __debugInfo(): ?array {
        return ["address" => $this->getAddress(), "version" => $this->version, "port" => $this->port];
    }
}