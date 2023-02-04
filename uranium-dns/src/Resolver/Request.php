<?php

namespace Cijber\Uranium\Dns\Resolver;

use Cijber\Uranium\Dns\Message;
use Cijber\Uranium\Dns\Session\Session;


class Request {
    const SOURCE_ORGANIC = 0;

    public function __construct(
      public Message $message,
      public Session $session,
      public bool $localOnly = false,
    ) {
    }
}