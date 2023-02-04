<?php

namespace Cijber\Uranium\Waker;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Task\Task;
use JetBrains\PhpStorm\Pure;


class TaskWaker extends Waker {
    public function __construct(
      private Task $task,
      ?Loop $loop = null,
    ) {
        parent::__construct($loop);
    }

    #[Pure]
    public function getTaskId(): int {
        return $this->task->getId();
    }
}