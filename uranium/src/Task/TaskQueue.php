<?php

namespace Cijber\Uranium\Task;

use SplQueue;


class TaskQueue {
    private SplQueue $queue;

    public function __construct() {
        $this->queue = new SplQueue();
    }

    public function queue(Task $task) {
        if ($task->getStatus() === Task::SLEEPING) {
            $task->wakeUp();
        }

        $this->queue->enqueue($task);
    }

    public function dequeue(): ?Task {
        if ($this->queue->isEmpty()) {
            return null;
        }

        return $this->queue->dequeue();
    }

    public function isEmpty() {
        return $this->queue->isEmpty();
    }
}