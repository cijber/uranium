<?php

namespace Cijber\Uranium\Task;

use Cijber\Uranium\Loop;


class LoopTask extends Task {
    public function __construct(private Loop $loop) {
        parent::__construct();
        $this->name = "LOOP";
    }

    public function run() {
        $this->loop->block();
    }
}