<?php

namespace Cijber\Uranium\Dns\Internal\Resolver;

use Cijber\Uranium\Dns\Resolver\Handler\Onion;
use Cijber\Uranium\Dns\Resolver\Request;
use Cijber\Uranium\Dns\Resolver\Response;


class OnionExecutor {
    public function __construct(
      private Onion $onion,
      private Request $request,
    ) {
    }

    public function next(?Request $request = null, int $layer = 0): Response {
        $request = $request ?: $this->request;

        if (count($this->onion->layers) > $layer) {
            return $this->onion->layers[$layer]->handle($request, fn(?Request $request = null) => $this->next($request, $layer + 1));
        } else {
            return $this->onion->core->handle($request);
        }
    }
}