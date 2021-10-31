<?php

namespace Cijber\Uranium;

use Cijber\Uranium\Task\Task;
use Cijber\Uranium\Timer\Duration;
use Cijber\Uranium\Timer\Timer;


class Uranium {
    static function queue(callable|Task $task): Task {
        $loop = Loop::get();
        return $loop->queue($task);
    }

    static function wait(callable|Task $task): mixed {
        $loop = Loop::get();
        $task = $loop->queue($task);
        $loop->wait($task->createWaker());

        return $task->return();
    }

    static function app(callable|Task $task): mixed {
        $loop = Loop::get();
        $task = $loop->queue($task);
        $loop->block();

        return $task->return();
    }

    static function interval(Duration $duration, callable|Task $task): Timer {
        $loop = Loop::get();
        return $loop->interval($duration, $task);
    }
}