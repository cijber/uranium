<?php

namespace Cijber\Collections;

use Cijber\Collections\Internal\BTreeNode;
use JetBrains\PhpStorm\Pure;


class BTreeMap {
    private BTreeNode $root;
    private int $branches;
    private int $side;

    #[Pure]
    public function __construct(
      private int $width = 5,
    ) {
        $this->branches = $this->width + 1;
        $this->side     = (int)floor($this->branches / 2);
        $this->root     = new BTreeNode();
    }

    public function or(mixed $key, callable $default): mixed {
        $data = $this->get($key, $found);
        if ($found) {
            return $data;
        }

        $data = $default();
        $this->set($key, $data);

        return $data;
    }

    public function get(mixed $key, ?bool &$found = null): mixed {
        $current = $this->root;

        while ($current !== null) {
            for ($i = 0; $i < count($current->leaves); $i++) {
                $d = $current->leaves[$i] <=> $key;
                if ($d === 0) {
                    $found = true;

                    return $current->values[$i];
                }

                if ($d === 1) {
                    break;
                }
            }

            if ( ! isset($current->branches[$i])) {
                $found = false;

                return null;
            }

            $current = $current->branches[$i];
        }
    }

    public function has(mixed $key): bool {
        $this->get($key, $found);

        return $found;
    }

    public function set(mixed $key, mixed $value, ?bool &$overwritten = false): mixed {
        $overwritten = false;
        $current     = $this->root;

        while ($current !== null) {
            foreach ($current->branches as $branch) {
                if ($current !== $branch->parent) {
                    throw new \RuntimeException("ah");
                }
            }

            for ($i = 0; $i < count($current->leaves); $i++) {
                $d = $current->leaves[$i] <=> $key;
                if ($d === 0) {
                    $overwritten = true;

                    $data                = $current->values[$i];
                    $current->values[$i] = $value;

                    return $data;
                }

                if ($d === 1) {
                    break;
                }
            }


            if ( ! isset($current->branches[$i])) {
                break;
            }

            $current = $current->branches[$i];
        }


        array_splice($current->leaves, $i, 0, [$key]);
        array_splice($current->values, $i, 0, [$value]);
        $overwritten = true;

        if (count($current->leaves) > $this->width) {
            $this->split($current);
        }

        return null;
    }

    private function split(BTreeNode $node) {
        $right           = new BTreeNode($node->parent);
        $right->branches = array_splice($node->branches, $this->side + 1);
        $right->leaves   = array_splice($node->leaves, $this->side + 1);
        $right->values   = array_splice($node->values, $this->side + 1);

        foreach ($right->branches as $branch) {
            $branch->parent = $right;
        }

        $parent = $node->parent;

        if ($parent === null) {
            $parent            = new BTreeNode();
            $parent->values[0] = array_pop($node->values);
            $parent->leaves[0] = array_pop($node->leaves);

            $node->parent  = $parent;
            $right->parent = $parent;

            $parent->branches = [$node, $right];

            $this->root = $parent;

            return;
        }

        $middleLeave = array_pop($node->leaves);
        $middleValue = array_pop($node->values);

        for ($i = 0; $i < count($parent->leaves); $i++) {
            if ($parent->leaves[$i] > $middleLeave) {
                break;
            }
        }

        array_splice($parent->leaves, $i, 0, [$middleLeave]);
        array_splice($parent->values, $i, 0, [$middleValue]);
        array_splice($parent->branches, $i + 1, 0, [$right]);

        if (count($parent->leaves) > $this->width) {
            $this->split($parent);
        }
    }
}