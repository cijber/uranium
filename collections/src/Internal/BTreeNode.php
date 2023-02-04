<?php

namespace Cijber\Collections\Internal;

class BTreeNode {
    public function __construct(public ?BTreeNode $parent = null) {
    }

    public array $branches = [];

    public array $leaves = [];
    public array $values = [];
}