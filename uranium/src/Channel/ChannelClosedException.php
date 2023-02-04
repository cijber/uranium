<?php

namespace Cijber\Uranium\Channel;

use RuntimeException;


class ChannelClosedException extends RuntimeException {
    public function __construct(public Channel $channel) {
        parent::__construct("Channel closed");
    }
}