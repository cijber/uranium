<?php


use Cijber\Uranium\Dns\Resolver\Handler;
use Cijber\Uranium\Dns\Resolver\Middleware;
use Cijber\Uranium\Dns\Resolver\Source;


return [
  Handler\Onion::class => [
    Middleware\Logger::class,
    Middleware\Cache::class,
    Handler\FailOver::class => [
      Source\Zone::class      => "/etc/zones",
      Source\HostsFile::class => "/etc/hosts",
      Source\Forward::class   => ["127.0.0.1"],
    ],
  ],
];