<?php

namespace Cijber\Uranium\Monitor;

use Cijber\Uranium\Task\LoopTask;
use Cijber\Uranium\Task\Task;
use Cijber\Uranium\Timer\Duration;
use Cijber\Uranium\Timer\Instant;


class Monitor {
    private $usage = [];
    private $lastSystem = null;
    private $lastUser = null;
    private $lastSwitch = null;
    public ?Task $lastTask = null;

    public function __construct() {
    }

    public function switchTask(?Task $to) {
        if ($this->lastTask === $to) {
            return;
        }

        $from           = $this->lastTask;
        $this->lastTask = $to;

        echo "Switching from " . ($from === null ? "ROOT" : $from->getName()) . " to " . ($to === null ? "ROOT" : $to->getName()) . "\n";

        $data       = getrusage(0);
        $systemTime = Duration::milliseconds($data['ru_stime.tv_usec']);
        $userTime   = new Duration($data['ru_utime.tv_sec'], $data['ru_utime.tv_usec'] * 1000);;

        if ($this->lastSystem === null) {
            $this->lastSystem = $systemTime;
            $this->lastUser   = $userTime;
            $this->lastSwitch = Instant::now();

            return;
        }


        if ($from === null) {
            $key = 'ROOT';
        } elseif ($from instanceof LoopTask) {
            $key = 'LOOP';
        } else {
            $key = 'Task#' . $from->getId();
        }

        if ( ! isset($this->usage[$key])) {
            $this->usage[$key] = new TaskUsage();
        }

        $now            = Instant::now();
        $lastSwitchDiff = Instant::diff($this->lastSwitch, $now);
        $systemTimeDiff = (clone $systemTime)->sub($this->lastSystem);
        $userTimeDiff   = (clone $userTime)->sub($this->lastUser);

        /** @var TaskUsage $usage */
        $usage = $this->usage[$key];

        $usage->addRealTime($lastSwitchDiff);
        $usage->addSystemTime($systemTimeDiff);
        $usage->addUserTime($userTimeDiff);

        $this->lastSystem = $systemTime;
        $this->lastUser   = $userTime;
        $this->lastSwitch = $now;
    }
}