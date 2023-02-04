<?php

namespace Cijber\Uranium;

use Cijber\Uranium\Task\Task;
use Cijber\Uranium\Time\Duration;
use Cijber\Uranium\Time\Timer;
use Iterator;


class Uranium
{
    static function queue(callable|Task $task): Task
    {
        $loop = Loop::get();

        return $loop->queue($task);
    }

    static function wait(callable|Task $task): mixed
    {
        $loop = Loop::get();
        $task = $loop->queue($task);
        $loop->wait($task->createWaker());

        return $task->return();
    }

    static function app(null|callable|Task $task = null): mixed
    {
        $loop = Loop::get();
        if ($task !== null) {
            $task = $loop->queue($task);
        }


        while ( ! $task->isFinished()) {
            $loop->block();
        }

        return $task->return();
    }

    static function after(float|Duration $duration, callable|Task $task): Timer
    {
        $loop = Loop::get();

        if (is_float($duration)) {
            $duration = Duration::fromFloat($duration);
        }

        return $loop->after($duration, $task);
    }

    static function interval(float|Duration $duration, callable|Task $task): Timer
    {
        $loop = Loop::get();

        if (is_float($duration)) {
            $duration = Duration::fromFloat($duration);
        }

        return $loop->interval($duration, $task);
    }

    public static function map(iterable $input, callable $map, ?int $concurrent = null): Iterator
    {
        $loop = Loop::get();

        return $loop->map($input, $map, $concurrent);
    }

    public static function ensureTask(?Loop $loop = null)
    {
    }
}