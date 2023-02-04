<?php

namespace Cijber\Uranium\Compat;

use Cijber\Uranium\Sync\Once;
use Cijber\Uranium\Waker\ManualWaker;
use React\Promise\PromiseInterface;


function await(PromiseInterface $promise)
{
    $once = new Once();

    $promise->then(function ($data) use ($once) {
        $once->set([true, $data]);
    }, function ($err) use ($once) {
        $once->set([false, $err]);
    });

    [$success, $data] = $once->get();

    if ($success) {
        return $data;
    }

    throw $data;
}