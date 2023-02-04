<?php

namespace Cijber\Uranium\Task;


use Cijber\Uranium\Executor\Executor;
use Cijber\Uranium\Utils\CancellationException;
use Cijber\Uranium\Waker\TaskWaker;
use Cijber\Uranium\Waker\Waker;
use JsonSerializable;
use Throwable;


abstract class Task implements JsonSerializable
{
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

    /** @var Waker[] */
    protected array $relatedWakers = [];

    protected string $name;

    protected ?Executor $executor = null;

    public function __construct(?string $name = null)
    {
        $this->id   = static::$idTracker++;
        $this->name = "Task#" . $this->id;

        if ($name !== null) {
            $this->name = $name . " (" . $this->name . ")";
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function isFinished(): bool
    {
        return $this->status > Task::QUEUED;
    }

    public function setExecutor(?Executor $executor): void
    {
        $this->executor = $executor;
    }

    /**
     * @internal
     */
    public function wakeUp()
    {
        $this->status = Task::QUEUED;
    }

    /**
     * @internal
     */
    public function putToSleep()
    {
        $this->status = Task::SLEEPING;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function return(): mixed
    {
        switch ($this->getStatus()) {
            case Task::DONE:
                return $this->return;
            case Task::FAILED:
                throw $this->thrown;
            default:
                throw new \RuntimeException("Task has not finished yet");
        }
    }

    public function __debugInfo(): ?array
    {
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

    public function throw(Throwable $throwable): void
    {
        $this->executor->throw($this, $throwable);
    }

    public function cancel(): void
    {
        $this->throw(new CancellationException());
    }

    public function finish()
    {
        $this->executor->finish();
    }

    public static function named(string $name, callable $func): Task
    {
        return (new CallbackTask($func))->name($name);
    }

    public function getName()
    {
        return $this->name;
    }

    public function __clone(): void
    {
        $this->id       = static::$idTracker++;
        $this->name     = "Task#" . $this->id . ' (copied from ' . $this->name . ')';
        $this->status   = Task::PENDING;
        $this->executor = null;
        $this->return   = null;
        $this->thrown   = null;
    }

    public function createWaker(): TaskWaker
    {
        return new TaskWaker($this);
    }

    public function addWaker(Waker $waker)
    {
        $this->relatedWakers[] = $waker;
    }

    public function getRelatedWakers(): array
    {
        return $this->relatedWakers;
    }

    public function jsonSerialize(): mixed
    {
        return $this->__debugInfo();
    }
}