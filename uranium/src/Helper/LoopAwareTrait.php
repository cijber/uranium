<?php

namespace Cijber\Uranium\Helper;

use Cijber\Uranium\Loop;


trait LoopAwareTrait {
    protected Loop $loop;

    public function setLoop(Loop $loop): void {
        $this->loop = $loop;
    }

    public function getLoop(): Loop {
        return $this->loop;
    }
}