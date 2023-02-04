<?php

namespace Cijber\Uranium\Executor;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Task\Task;
use Cijber\Uranium\Utils\CancellationException;
use Fiber;
use RuntimeException;
use Throwable;


class FiberExecutor implements Executor
{
    private Loop $loop;
    /** @var Fiber[] */
    private array $fibers = [];
    private ?Task $current = null;
    private $fiberTasks = [];
    private array $onion = [];

    public function dumpTasks()
    {
        file_put_contents("/tmp/dump.json", json_encode($this->fiberTasks, JSON_PRETTY_PRINT));

    }

    public function execute(Task $task)
    {
        $task->setExecutor($this);
        $id = spl_object_id($task);

        if (isset($this->fibers[$id])) {
            $fiber = $this->fibers[$id];
        } else {
            $fiber                              = new Fiber(fn() => $task->run());
            $this->fibers[$id] = $fiber;
        }

        $this->fiberTasks[$id] = $task;

        if ($task === $this->current) {
            return;
        }

        if ($this->current !== null) {
            array_push($this->onion, $this->current);
        }

        $this->loop->getMonitor()?->switchTask($task);

        $this->current = $task;
        if ($fiber->isSuspended()) {
            $fiber->resume();
        }

        if ( ! $fiber->isStarted()) {
            $fiber->start();
        }

        if ($fiber->isTerminated()) {
            try {
                $task->return();
            } catch (CancellationException $c) {
                $task->finish();
                // ignore
            }
        }
    }

    public function current(): ?Task
    {
        return $this->current;
    }

    public function suspend()
    {
        $task = $this->current();
        $task->putToSleep();
        $this->current = array_pop($this->onion);
        Fiber::suspend();
    }


    public function setLoop(Loop $loop): void
    {
        $this->loop = $loop;
    }

    public function finish()
    {
        if ($this->current() === null) {
            return;
        }

        $this->cleanTask($this->current());

        $this->current = array_pop($this->onion);
        $this->loop->getMonitor()?->switchTask($this->current);
    }

    public function throw(Task $task, Throwable $throwable)
    {
        if ($this->current() === $task) {
            throw $throwable;
        }

        if ( ! isset($this->fibers[spl_object_id($task)])) {
            throw new RuntimeException(":)");
        }

        $this->fibers[spl_object_id($task)]->throw($throwable);
    }

    public function openFibers(): int
    {
        return count($this->fibers);
    }

    private function cleanTask(Task $task): void
    {
        $this->loop->removeWakersForTask($task);

        unset($this->fibers[spl_object_id($task)]);
        unset($this->fiberTasks[spl_object_id($task)]);
    }
}