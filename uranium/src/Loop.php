<?php

namespace Cijber\Uranium;


use Cijber\Uranium\EventLoop\EventLoop;
use Cijber\Uranium\EventLoop\EvLoop;
use Cijber\Uranium\EventLoop\SelectEventLoop;
use Cijber\Uranium\Executor\Executor;
use Cijber\Uranium\Executor\FiberExecutor;
use Cijber\Uranium\Executor\NestedExecutor;
use Cijber\Uranium\Monitor\Monitor;
use Cijber\Uranium\Task\CallbackTask;
use Cijber\Uranium\Task\Helper\PrefetchMap;
use Cijber\Uranium\Task\Helper\RaceMap;
use Cijber\Uranium\Task\LoopTask;
use Cijber\Uranium\Task\Task;
use Cijber\Uranium\Task\TaskQueue;
use Cijber\Uranium\Time\Duration;
use Cijber\Uranium\Time\Instant;
use Cijber\Uranium\Time\Timeout;
use Cijber\Uranium\Time\Timer;
use Cijber\Uranium\Time\TimerCollection;
use Cijber\Uranium\Utils\ContinuationException;
use Cijber\Uranium\Utils\StringUtils;
use Cijber\Uranium\Waker\ManualWaker;
use Cijber\Uranium\Waker\TaskWaker;
use Cijber\Uranium\Waker\TimerWaker;
use Cijber\Uranium\Waker\Waker;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use RuntimeException;


class Loop implements LoggerAwareInterface
{
    use LoggerAwareTrait;


    private static ?Loop $instance = null;
    private EventLoop $eventLoop;
    private TaskQueue $queue;
    private Executor $executor;
    private TimerCollection $timers;
    private Duration $timerLookAhead;
    private ?Monitor $monitor = null;
    private Task $loopTask;
    /** @var TaskWaker[] */
    private array $taskWakers = [];

    public function __construct(?EventLoop $eventLoop = null, bool $monitor = false, ?LoggerInterface $logger = null)
    {
        $this->initLogger($logger);
        $this->timerLookAhead = Duration::seconds(1);
        $this->queue          = new TaskQueue();

        if ($monitor) {
            $this->monitor = new Monitor();
        }

        $this->loopTask = new LoopTask($this);
        $this->selectExecutor();
        $this->selectEventLoop($eventLoop);

        $this->timers = new TimerCollection($this->eventLoop);
    }

    private function initLogger(?LoggerInterface $logger = null)
    {
        if ($logger !== null) {
            $this->logger = $logger;

            return;
        }

        $logger = new Logger('Uranium');
        $logger->pushHandler(new StreamHandler("php://stdout"));
        $this->logger = $logger;
    }

    private function selectExecutor()
    {
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

    private function selectEventLoop(?EventLoop $eventLoop = null)
    {
        if ($eventLoop === null) {
            if (class_exists('Ev')) {
                $eventLoop = new EvLoop();
            } else {
                $eventLoop = new SelectEventLoop();
            }
        }

        $eventLoop->setLogger($this->logger);
        $eventLoop->setLoop($this);
        $this->eventLoop = $eventLoop;
    }

    public static function addMonitor(?Loop $loop = null)
    {
        $loop          = $loop ?: Loop::get();
        $loop->monitor = new Monitor($loop);
    }

    public static function get(): Loop
    {
        if (static::$instance === null) {
            return static::$instance = new Loop();
        }

        return static::$instance;
    }

    public function setTimerLookAhead(Duration $timerLookAhead): void
    {
        $this->timerLookAhead = $timerLookAhead;
    }

    public function interval(Duration $duration, callable|Task $func, bool $enabled = true): Timer
    {
        $timer = new Timer(['spawn', $func], $duration, true);

        if ($enabled) {
            $timer->enable();
        }

        $this->addTimer($timer);

        return $timer;
    }

    protected function addTimer(Timer $timer)
    {
        $this->timers->add($timer);

        if ($this->eventLoop->hasNativeTimers() && $timer->isEnabled()) {
            $this->addWaker($timer->createWaker());
            $timer->setQueued();
        }
    }

    private function addWaker(Waker $waker)
    {
        if ($waker instanceof ManualWaker) {
            // Manual wakers need to be waked manually
            return;
        }

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

    public function getMonitor(): ?Monitor
    {
        return $this->monitor;
    }

    public function getExecutor(): Executor
    {
        return $this->executor;
    }

    public function wait(Waker|callable|Task $target)
    {
        $task = null;
        if (is_callable($target)) {
            $target = $this->queue($target);
        }

        if ($target instanceof Task) {
            $task   = $target;
            $target = $target->createWaker();
        }

        $this->suspend($target);

        return $task?->return();
    }

    public function suspend(Waker $waker)
    {
        $task = $this->executor->current();
        $this->addWaker($waker);

        if ($task === null) {
            $waker->addAction('throw');
            $this->block();
        } else {
            $waker->addAction('task', $task);
            $this->executor->suspend();
        }
    }

    public function block()
    {
        try {
            while ( ! $this->queue->isEmpty() || ! $this->eventLoop->isEmpty()) {
                $this->poll();
            }
        } catch (ContinuationException) {
            return;
        }
    }

    public function poll()
    {
        $this->monitor?->switchTask($this->getLoopTask());

        if ( ! $this->eventLoop->hasNativeTimers()) {
            /** @var Timer $timer */
            foreach ($this->timers->getSleepingTimersTriggeredWithin($this->timerLookAhead) as $timer) {
                $this->eventLoop->addWaker($timer->createWaker());
                $timer->setQueued();
            }
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

    public function dumpTasks()
    {
        $this->executor->dumpTasks();
    }

    public function getLoopTask(): LoopTask|Task
    {
        return $this->loopTask;
    }

    public function wake(Waker $waker)
    {
        try {
            foreach ($waker->getActions() as [$action, $args]) {
                $this->runAction($action, $args);
            }
        } finally {
            $waker->done();
        }
    }

    public function runAction(string $action, mixed $args)
    {
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
                throw new ContinuationException();
            default:
                throw new RuntimeException("No waker action " . $action);
        }
    }

    public function queue(Task|callable $func): Task
    {
        if (is_callable($func)) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

            $trace = $bt[0];
            if ($bt[0]['file'] === __DIR__ . '/Uranium.php' && count($bt) > 0) {
                $trace = $bt[1];
            }

            $task = new CallbackTask($func, $trace['file'] . ':' . $trace['line']);
        } else {
            $task = $func;
        }

        $this->queue->queue($task);

        return $task;
    }

    public function after(Duration $duration, callable|Task $func, bool $enabled = true)
    {
        $timer = new Timer(['spawn', $func], $duration, false);

        if ($enabled) {
            $timer->enable();
        }

        $this->addTimer($timer);

        return $timer;
    }

    public function sleep(float|int|Duration $duration)
    {
        Duration::ensure($duration);

        $timerWaker = new TimerWaker(Instant::now()->add($duration), loop: $this);
        $this->suspend($timerWaker);
    }

    /** @internal */
    public function removeWakersForTask(Task $task)
    {
        $wakers = $task->getRelatedWakers();

        foreach ($wakers as $waker) {
            $waker->removeActionsFor($task);

            if ( ! $waker->hasActions()) {
                $this->removeWaker($waker);
            }
        }
    }

    public function removeWaker(Waker $waker)
    {
        $this->eventLoop->removeWaker($waker);
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function map(iterable $input, callable $map, ?int $concurrent = null): PrefetchMap
    {
        return new PrefetchMap($input, $map, $concurrent);
    }

    public function race(iterable $input, callable $map, ?int $concurrent = null): RaceMap
    {
        return new RaceMap($input, $map, $concurrent);
    }

    public function timeout(float|int|Duration $duration, callable $fn, ?bool &$timedOut = null): mixed
    {
        Duration::ensure($duration);

        $to = new Timeout($duration, $fn, $this);

        return $to->run($timedOut);
    }
}