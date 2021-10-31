<?php

namespace Cijber\Uranium\Executor;

use Cijber\Uranium\Loop;
use Cijber\Uranium\Task\Task;


interface Executor {
    public function setLoop(Loop $loop);
    public function execute(Task $task);
    public function current(): ?Task;
    public function suspend();
    public function finish();
}