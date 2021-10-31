<?php

namespace Cijber\Uranium\Executor;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Task\Task;


class NestedExecutor implements Executor {
    private ?Loop $loop = null;
    private ?Task $current = null;

    public function execute(Task $task) {
        $task->setExecutor($this);

        if ($task->getStatus() === Task::PENDING) {
            $task->run();
            $task->return();
        } else {
            throw new \RuntimeException("Tried to execute a task that was no in pending but in " . $task->getStatus());
        }
    }

    public function current(): ?Task {
        return $this->current;
    }


    public function suspend() {
        $this->loop->getMonitor()?->switchTask($this->loop->getLoopTask());
        $task          = $this->current();
        $this->current = $this->loop->getLoopTask();

        $task->putToSleep();

        while ( ! $task->isFinished() && $task->getStatus() !== Task::QUEUED) {
            $this->loop->poll();
        }
    }

    public function setLoop(Loop $loop) {
        $this->loop = $loop;
    }

    public function finish() {
    }
}