<?php

use Cijber\Collections\Iter;
use Cijber\Uranium\Loop;
use Cijber\Uranium\Task\Helper\PrefetchMap;
use Cijber\Uranium\Task\Helper\RaceMap;


Iter::registerExtension('race', fn(Iter $iter, callable $fn, ?int $concurrent = null, ?int $queueSize = null, ?Loop $loop = null) => new RaceMap($iter, $fn, $concurrent, $queueSize, $loop));
Iter::registerExtension('prefetch', fn(Iter $iter, callable $fn, ?int $concurrent = null, ?Loop $loop = null) => new PrefetchMap($iter, $fn, $concurrent, $loop));