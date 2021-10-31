<?php

namespace Cijber\Uranium\Waker;

use Cijber\Uranium\Task\Task;
use JetBrains\PhpStorm\Pure;


class TaskWaker extends Waker {
    public function __construct(
      private Task $task
    ) {
    }

    #[Pure]
    public function getTaskId(): int {
        return $this->task->getId();
    }
}