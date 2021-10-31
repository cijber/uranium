<?php

namespace Cijber\Uranium\Task;


use Cijber\Uranium\Executor\Executor;
use Cijber\Uranium\Waker\TaskWaker;


abstract class Task {
    const PENDING  = 0;
    const RUNNING  = 1;
    const SLEEPING = 3;
    const QUEUED   = 4;
    const DONE     = 5;
    const FAILED   = 6;

    private static int $idTracker = 0;

    protected int $status = Task::PENDING;
    protected mixed $return = null;
    protected mixed $thrown = null;
    protected int $id = 0;

    protected string $name;

    protected ?Executor $executor = null;

    public function __construct() {
        $this->id   = static::$idTracker++;
        $this->name = "Task#" . $this->id;
    }

    public function getId(): int {
        return $this->id;
    }

    public function name(string $name): self {
        $this->name = $name;

        return $this;
    }

    public function isFinished(): bool {
        return $this->status > Task::QUEUED;
    }

    public function setExecutor(?Executor $executor): void {
        $this->executor = $executor;
    }

    /**
     * @internal
     */
    public function wakeUp() {
        $this->status = Task::QUEUED;
    }

    /**
     * @internal
     */
    public function putToSleep() {
        $this->status = Task::SLEEPING;
    }

    public function getStatus(): int {
        return $this->status;
    }

    public function return(): mixed {
        switch ($this->getStatus()) {
            case Task::DONE:
                return $this->return;
            case Task::FAILED:
                throw $this->thrown;
            default:
                throw new \RuntimeException("Task has not finished yet");
        }
    }

    public function __debugInfo(): ?array {
        return [
          'id'     => $this->id,
          'name'   => $this->name,
          'status' => match ($this->getStatus()) {
              Task::DONE => "Done",
              Task::SLEEPING => "Sleeping",
              Task::QUEUED => "Queued",
              Task::FAILED => "Failed",
              Task::RUNNING => "Running",
              Task::PENDING => "Pending",
          },
          "return" => $this->return,
          "thrown" => $this->thrown,
        ];
    }

    public abstract function run();

    public function finish() {
        $this->executor->finish();
    }

    public static function named(string $name, callable $func): Task {
        return (new CallbackTask($func))->name($name);
    }

    public function getName() {
        return $this->name;
    }

    public function __clone(): void {
        $this->id       = static::$idTracker++;
        $this->name     = "Task#" . $this->id . ' (copied from ' . $this->name . ')';
        $this->status   = Task::PENDING;
        $this->executor = null;
        $this->return   = null;
        $this->thrown   = null;
    }

    public function createWaker() {
        return new TaskWaker($this);
    }
}