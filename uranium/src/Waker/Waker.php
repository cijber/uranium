<?php

namespace Cijber\Uranium\Waker;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Task\Task;


abstract class Waker {
    protected Loop $loop;
    protected array $actions = [];

    public string $source;
    public array $backtrace;

    public function __construct(?Loop $loop = null) {
        $this->backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $this->source    = $this->backtrace[2]["file"] . ':' . $this->backtrace[2]["line"];

        $this->loop = $loop ?: Loop::get();
    }

    public function addAction($action, $args = null) {
        $this->actions[] = [$action, $args];
    }

    public function getActions(): array {
        return $this->actions;
    }

    public function hasActions(): bool {
        return count($this->actions) > 0;
    }

    public function done() {
    }

    public function removeActionsFor(Task $task) {
        $actions = [];

        foreach ($this->actions as [$type, $args]) {
            if ( ! ($type === 'task' && $args === $task)) {
                $actions[] = [$type, $args];
            }
        }

        $this->actions = $actions;
    }
}