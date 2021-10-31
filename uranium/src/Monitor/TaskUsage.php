<?php

namespace Cijber\Uranium\Monitor;

use Cijber\Uranium\Timer\Duration;


class TaskUsage {
    private Duration $realTime;
    private Duration $userTime;
    private Duration $systemTime;

    public function __construct() {
        $this->realTime   = new Duration();
        $this->userTime   = new Duration();
        $this->systemTime = new Duration();
    }

    public function addUserTime(Duration $duration) {
        $this->userTime->add($duration);
    }

    public function addSystemTime(Duration $duration) {
        $this->systemTime->add($duration);
    }

    public function addRealTime(Duration $duration) {
        $this->realTime->add($duration);
    }
}