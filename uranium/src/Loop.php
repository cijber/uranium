<?php

namespace Cijber\Uranium;


use Cijber\Uranium\EventLoop\EventLoop;
use Cijber\Uranium\EventLoop\SelectEventLoop;
use Cijber\Uranium\Executor\Executor;
use Cijber\Uranium\Executor\FiberExecutor;
use Cijber\Uranium\Executor\NestedExecutor;
use Cijber\Uranium\Monitor\Monitor;
use Cijber\Uranium\Task\CallbackTask;
use Cijber\Uranium\Task\LoopTask;
use Cijber\Uranium\Task\Task;
use Cijber\Uranium\Task\TaskQueue;
use Cijber\Uranium\Timer\Duration;
use Cijber\Uranium\Timer\Timer;
use Cijber\Uranium\Timer\TimerCollection;
use Cijber\Uranium\Utils\ContinuationException;
use Cijber\Uranium\Utils\StringUtils;
use Cijber\Uranium\Waker\TaskWaker;
use Cijber\Uranium\Waker\Waker;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;


class Loop implements LoggerAwareInterface {
    use LoggerAwareTrait;


    private static ?Loop $instance = null;

    public static function get(): Loop {
        if (static::$instance === null) {
            return static::$instance = new Loop();
        }

        return static::$instance;
    }

    private EventLoop $eventLoop;
    private TaskQueue $queue;
    private Executor $executor;
    private TimerCollection $timers;
    private Duration $timerLookAhead;
    private ?Monitor $monitor = null;
    private Task $loopTask;
    /** @var TaskWaker[] */
    private array $taskWakers = [];

    public function __construct(?EventLoop $eventLoop = null, bool $monitor = false, ?LoggerInterface $logger = null) {
        $this->initLogger($logger);
        $this->timerLookAhead = Duration::seconds(1);
        $this->queue          = new TaskQueue();
        $this->timers         = new TimerCollection();

        if ($monitor) {
            $this->monitor = new Monitor();
        }

        $this->loopTask = new LoopTask($this);
        $this->selectExecutor();
        $this->selectEventLoop($eventLoop);
    }

    public function getLoopTask(): LoopTask|Task {
        return $this->loopTask;
    }

    public function setTimerLookAhead(Duration $timerLookAhead): void {
        $this->timerLookAhead = $timerLookAhead;
    }

    private function initLogger(?LoggerInterface $logger = null) {
        if ($logger !== null) {
            $this->logger = $logger;

            return;
        }

        $logger = new Logger('Uranium');
        $logger->pushHandler(new StreamHandler("php://stdout"));
        $this->logger = $logger;
    }

    private function selectEventLoop(?EventLoop $eventLoop = null) {
        if ($eventLoop === null) {
            $eventLoop = new SelectEventLoop();
        }

        $eventLoop->setLoop($this);
        $this->eventLoop = $eventLoop;
    }

    private function selectExecutor() {
        $fiberAvailable     = class_exists('Fiber', false);
        $notDisabledByEnv   = false === getenv('CIJBER_URANIUM_NO_FIBERS');
        $notDisabledByConst = ! defined('CIJBER_URANIUM_NO_FIBERS');

        if ($fiberAvailable && $notDisabledByConst && $notDisabledByEnv) {
            $this->logger->debug("Selected Fiber executor");
            $executor = new FiberExecutor();
        } else {
            $reasons = [];
            if ( ! $fiberAvailable) {
                $reasons[] = "Fiber's are not available";
            }

            if ( ! $notDisabledByEnv) {
                $reasons[] = "Fiber executor is disabled by environment variable";
            }

            if ( ! $notDisabledByConst) {
                $reasons[] = "Fiber executor is disabled by constant";
            }

            $this->logger->debug("Selected Nested executor: " . StringUtils::englishJoin($reasons));

            if ( ! defined('CIJBER_URANIUM_NESTED_OK')) {
                $this->logger->warning("Nested executor is being used, because of the amount of nested calls this generates it might be unstable (defined `CIJBER_URANIUM_NESTED_OK` to silence this warning)");
            }

            $executor = new NestedExecutor();
        }

        $executor->setLoop($this);
        $this->executor = $executor;
    }

    public function poll() {
        $this->monitor?->switchTask($this->getLoopTask());

        /** @var Timer $timer */
        foreach ($this->timers->getSleepingTimersTriggeredWithin($this->timerLookAhead) as $timer) {
            $this->eventLoop->addWaker($timer->createWaker());
            $timer->setQueued();
        }

        $this->eventLoop->poll();

        while (($task = $this->queue->dequeue()) !== null) {
            $this->executor->execute($task);

            if ($task->isFinished()) {
                if (isset($this->taskWakers[$task->getId()])) {
                    $wakers = $this->taskWakers[$task->getId()];
                    unset($this->taskWakers[$task->getId()]);

                    foreach ($wakers as $waker) {
                        $this->wake($waker);
                    }
                }
            }
        }
    }

    public function queue(Task|callable $func): Task {
        if (is_callable($func)) {
            $task = new CallbackTask($func);
        } else {
            $task = $func;
        }
        $this->queue->queue($task);

        return $task;
    }

    public function block() {
        try {
            while ( ! $this->queue->isEmpty() || ! $this->eventLoop->isEmpty()) {
                $this->poll();
            }
        } catch (ContinuationException $e) {
            return;
        }
    }

    private function addWaker(Waker $waker) {
        if ($waker instanceof TaskWaker) {
            // Task waker is handled by loop itself, as it's in charge of the tasks
            if ( ! isset($this->taskWakers[$waker->getTaskId()])) {
                $this->taskWakers[$waker->getTaskId()] = [];
            }

            $this->taskWakers[$waker->getTaskId()][] = $waker;

            return;
        }

        $this->eventLoop->addWaker($waker);
    }

    public function suspend(Waker $waker) {
        $task = $this->executor->current();
        $this->addWaker($waker);

        if ($task === null) {
            $waker->setAction('throw');
            $this->block();
        } else {
            $waker->setAction('task', $task);
            $this->executor->suspend();
        }
    }

    public function wake(Waker $waker) {
        [$action, $args] = $waker->getAction() + ['<undefined>', null];

        switch ($action) {
            case 'spawn':
                if ($args instanceof Task) {
                    $args = clone $args;
                }

                $this->queue($args);
                break;
            case 'task':
                $this->queue->queue($args);

                break;
            case 'throw':
                $waker->done();
                throw new ContinuationException();
            default:
                throw new \RuntimeException("No waker action " . $action);
        }

        $waker->done();
    }

    public function interval(Duration $duration, \Closure|Task $func, bool $enabled = true): Timer {
        $timer = new Timer(['spawn', $func], $duration, true);

        if ($enabled) {
            $timer->enable();
        }

        $this->timers->add($timer);

        return $timer;
    }

    public function getMonitor(): ?Monitor {
        return $this->monitor;
    }

    public function wait(Waker $waker) {
        $this->suspend($waker);
    }
}