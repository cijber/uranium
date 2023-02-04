<?php

namespace Cijber\Uranium\Dns\Resolver;

use Cijber\Uranium\Dns\Resolver\Handler\FailOver;
use Cijber\Uranium\Dns\Resolver\Handler\Onion;
use Cijber\Uranium\Dns\Resolver\Handler\Router;
use Cijber\Uranium\Dns\Resolver\Middleware\Cache;
use Cijber\Uranium\Dns\Resolver\Middleware\Logger;
use Cijber\Uranium\Dns\Resolver\Source\Forward;
use Cijber\Uranium\Dns\Resolver\Source\HostsFile;
use Cijber\Uranium\Dns\Resolver\Source\Nuller;
use Cijber\Uranium\Dns\Resolver\Source\Recursor;
use Cijber\Uranium\Dns\Resolver\Source\Zone;
use Cijber\Uranium\Loop;
use RuntimeException;


class StackBuilder {
    private static array $nodes = [
        // Compound
        "onion"     => Onion::class,
        "fail-over" => FailOver::class,
        "router"    => Router::class,

        // Source
        "hosts"     => HostsFile::class,
        "zone"      => Zone::class,
        "forward"   => Forward::class,
        "recursor"  => Recursor::class,
        "nuller"    => Nuller::class,

        // Middleware
        "cache"     => Cache::class,
        "logger"    => Logger::class,
    ];


    public static function register(string $name, string $class) {
        static::$nodes[$name] = $class;
    }

    public static function fromConfig(array $config, ?string $cwd = null, ?Loop $loop = null): ?Stack {
        if ($config["name"] !== "stack") {
            return null;
        }

        $loop ??= Loop::get();

        $stack = new Stack(new Nuller(), loop: $loop);

        $node = null;

        foreach ($config["children"] as $item) {
            $node = static::nodeFromConfig($item, $stack, $loop, $cwd);
            break;
        }

        if ( ! $node instanceof Handler) {
            throw new RuntimeException("Stack root node should be a Handler, " . get_class($node) . " is not a Handler");
        }

        $stack->setHandler($node);
        $node->setLoop($loop);

        return $stack;
    }

    public static function nodeFromConfig(array $config, Stack $stack, Loop $loop, ?string $cwd = null): Processor {
        $className = $config["name"];
        if (isset(static::$nodes[$className])) {
            $className = static::$nodes[$className];
        }

        if ( ! class_exists($className) || ! is_subclass_of($className, Processor::class)) {
            throw new RuntimeException("Can't find Processor for name $className");
        }

        return $className::fromConfig($config, $stack, $cwd ?: getcwd(), $loop);
    }
}