<?php

namespace Cijber\Uranium\Helper;

use Cijber\Uranium\Loop;


interface LoopAwareInterface {
    public function setLoop(Loop $loop): void;
}