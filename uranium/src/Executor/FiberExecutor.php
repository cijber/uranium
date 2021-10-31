<?php

namespace Cijber\Uranium\Executor;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Task\Task;
use Fiber;


class FiberExecutor implements Executor {
    private Loop $loop;
    private array $fibers = [];
    private ?Task $current = null;
    private array $onion = [];

    public function execute(Task $task) {
        $task->setExecutor($this);
        $id = spl_object_id($task);

        if (isset($this->fibers[$id])) {
            $fiber = $this->fibers[$id];
        } else {
            $fiber                              = new Fiber(fn() => $task->run());
            $this->fibers[spl_object_id($task)] = $fiber;
        }

        if ($task === $this->current) {
            return;
        }

        if ($this->current !== null) {
            array_push($this->onion, $this->current);
        }

        $this->loop->getMonitor()?->switchTask($task);

        $this->current = $task;
        if ($fiber->isStarted()) {
            $fiber->resume();
        } else {
            $fiber->start();
        }

        if ($fiber->isTerminated()) {
            $task->return();
        }
    }

    public function current(): ?Task {
        return $this->current;
    }

    public function suspend() {
        $task = $this->current();
        $task->putToSleep();
        $this->current = array_pop($this->onion);
        Fiber::suspend();
    }


    public function setLoop(Loop $loop): void {
        $this->loop = $loop;
    }

    public function finish() {
        if ($this->current() === null) {
            return;
        }

        $task = $this->current();

        unset($this->fibers[spl_object_id($task)]);
        $this->current = array_pop($this->onion);
        $this->loop->getMonitor()?->switchTask($this->current);
    }
}