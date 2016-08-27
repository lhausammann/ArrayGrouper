<?php
/**
 * Created by PhpStorm.
 * User: Luzius Hausammann
 * Date: 23.10.14
 * Time: 22:48
 */

namespace ArrayGrouper\Grouper;
use ArrayGrouper\Exception\GroupingException;

class GroupExtensions {

    public function min($group, $field)
    {
        return $this->groupFn($group, $field, 'min');
    }

    public function max($group, $field)
    {
        return $this->groupFn($group, $field, 'max');
    }

    public function sum(Group $group, $field) {
        $sum = 0;
        $leafs = $group->getLeafNodes();
        foreach($leafs as $i =>  $leaf) {
            foreach($leaf->getChildren() as $child) {
                $sum += $leaf->getValue($field, $child);
            }
        }

        return $sum;
    }

    public function avg(Group $group, $field)
    {

        return $group->sum($group, $field) / $group->count($group, $field);

    }

    protected function minEvaluation($a, $b) {
       if ($a === false) return $b * 1;
       if ($b === false) return $a * 1;

       return $a <= $b ? $a : $b;
    }

    protected function maxEvaluation($a, $b) {
        if ($a === false) $a = 0;
        if ($b === false) $b = 0;

        return $a > $b ? $a : $b;
    }

    protected function groupFn(Group $group, $field, $evaluation)
    {
        $call = $evaluation . 'Evaluation';
        $current = false;
        $children = $group->getChildren();
        $isLeaf = $group->isLeaf();
        foreach($children as $child) {
            if ($isLeaf === false) {
               $current =  $this->$call($current, $this->groupFn($child, $field, $evaluation), $group);
            } else {
                $nodes = $group->getElements();
                foreach ($nodes as $node) {
                    $value = $group->getValue($field, $node);
                    $current =  $this->$call($current, $value, $group);
                }
            }
        }

        return $current;
    }
}
