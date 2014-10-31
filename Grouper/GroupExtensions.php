<?php
/**
 * Created by PhpStorm.
 * User: Luzius Hausammann
 * Date: 23.10.14
 * Time: 22:48
 */

namespace ArrayGrouper\Grouper;


class GroupExtensions {
    // TODO: This must be implemented recursively for nested groups.
    public function avg(Group $group)
    {
        throw new \Exception("Not implemented yet.");
    }

    public function min($group, $field)
    {
        return $this->groupFn($group, $field, 'min');
    }

    public function max($group, $field)
    {
        return $this->groupFn($group, $field, 'max');
    }

    public function sum($group, $field) {
        return $this->groupFn($group, $field, 'sum');
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

    protected function sumEvaluation($a, $b)
    {
        throw new \Exception("Not implemented");
    }

    protected function groupFn(Group $group, $field, $evaluation)
    {
        $call = $evaluation . 'Evaluation';
        $current = false;
        $children = $group->getChildren();
        $isLeaf = $group->isLeaf();
        foreach($children as $child) {
            if ($isLeaf === false) {
               $current =  $this->$call($current, $this->groupFn($child, $field, $evaluation));
            } else {
                $nodes = $group->getNodes();
                foreach ($nodes as $node) {
                    $value = $group->getValue($field, $node);
                    $current =  $this->$call($current, $value, $evaluation);
                }
            }
        }

        return $current;
    }
}
