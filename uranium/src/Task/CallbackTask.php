<?php

namespace Cijber\Uranium\Task;

use Throwable;


class CallbackTask extends Task {
    public function __construct(
      private $callback
    ) {
        parent::__construct();
    }

    public function run() {
        $this->status = Task::RUNNING;

        try {
            $this->return = ($this->callback)();
            $this->status = Task::DONE;
        } catch (Throwable $e) {
            $this->thrown = $e;
            $this->status = Task::FAILED;
        }

        $this->finish();
    }
}