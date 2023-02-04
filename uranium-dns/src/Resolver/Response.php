<?php

namespace Cijber\Uranium\Dns\Resolver;

use Cijber\Uranium\Dns\Message;
use Cijber\Uranium\IO\Net\Address;


class Response {

    public function __construct(
      public Message $message,
      public ?Address $source = null,
    ) {
    }

    public static function nxdomain(Request $request) {
        return new Response(Message::nxdomain($request->message));
    }

    public static function ok(Request $request, array $responseRecords = [], array $authoritativeRecords = [], array $additionalRecords = []) {
        $resp = Message::empty($request->message);
        $resp->setResponseRecords($responseRecords);
        $resp->setAuthoritativeRecords($authoritativeRecords);
        $resp->setAdditionalRecords($additionalRecords);

        return new Response($resp);
    }
}