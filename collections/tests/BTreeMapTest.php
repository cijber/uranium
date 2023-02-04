<?php

namespace Cijber\Collections\Tests;

use Cijber\Collections\BTreeMap;
use PHPUnit\Framework\TestCase;


class BTreeMapTest extends TestCase {
    public function testAdd() {
        $btree = new BTreeMap();

        for ($i = 0; $i < 100; $i++) {
            $btree->set($i, $i);

            for ($j = 0; $j < $i; $j++) {
                $v = $btree->get($j, $found);
                if (!$found) {
                    $this->assertFalse(true);
                }
            }
        }
    }
}