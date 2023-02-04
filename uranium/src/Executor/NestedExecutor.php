<?php

namespace Cijber\Uranium\Executor;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Task\Task;
use Throwable;


class NestedExecutor implements Executor {
    private ?Loop $loop = null;
    private ?Task $current = null;
    private array $throwables = [];

    public function execute(Task $task) {
        $task->setExecutor($this);

        if ($task->getStatus() === Task::PENDING) {
            $this->loop->getMonitor()?->switchTask($task);
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
        $task = $this->current();

        $this->current = $this->loop->getLoopTask();

        $task->putToSleep();

        $throwable = null;

        while ( ! $task->isFinished() && $task->getStatus() !== Task::QUEUED) {
            $this->loop->poll();

            if (isset($this->throwables[$task->getId()])) {
                $throwable = $this->throwables[$task->getId()];
                unset($this->throwables[$task->getId()]);
                break;
            }
        }

        $this->loop->getMonitor()?->switchTask($task);
        $this->current = $task;
        if ($throwable !== null) {
            throw $throwable;
        }
    }

    public function setLoop(Loop $loop) {
        $this->loop = $loop;
    }

    public function finish() {
    }

    public function throw(Task $task, Throwable $throwable) {
        if ($this->current() === $task) {
            throw $throwable;
        }

        $this->throwables[$task->getId()] = $throwable;
    }
}