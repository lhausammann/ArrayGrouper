<?php
/**
 * Created by PhpStorm.
 * User: Luzius Hausammann
 * Date: 28.10.14
 * Time: 20:48
 */

namespace ArrayGrouper\Tests;
use ArrayGrouper\Grouper\Collection;

class CollectionTest extends \PHPUnit_Framework_TestCase {

    public function testOneLevelGrouping()
    {
        $array = array(1,2,3,4,5,6,7,8,9,0);
        shuffle($array);
        $collection = new Collection($array);
        $collection->registerGroupingFunction('evenUneven', function($element) {return $element % 2; });
        $collection->groupBy('testGroup', array('evenUneven'));
        $groupings = $collection->apply();
        $z = 0;
        foreach ($groupings->getChildren() as $i => $group) {
            $elements = $group->getElements();
            foreach ($elements as $element) {
                $z++;
                $this->assertTrue($element % 2 == $i, $element . ' modulo ' . $i);
            }
        }
        $this->assertEquals(2, $groupings->count(false));
        $this->assertEquals(10, $groupings->count());
        $this->assertEquals(0, $groupings->min(), 'Group: ' . $groupings);
    }

    public function testGroupFnsRandomly()
    {
        $array = array();
        for ($j = 0; $j < 1000; $j++) {
           $array[$j] = mt_rand(-100, 200);
        }
        $count = count($array);
        $sum = array_sum($array);
        $min = min($array);
        $max = max($array);
        $avg = $sum / $count;

        $collection = new Collection($array);
        $collection->registerGroupingFunction('evenUneven', function($element) {return $element % 2; });
        $collection->registerGroupingFunction('byRandom', function($element) { return rand(0,20); });
        $collection->groupBy('testGroup', array('evenUneven'));
        $collection->groupByDescending('key', array('byRandom'));
        $groupings = $collection->apply();
        $this->assertEquals($count, $groupings->count());
        $this->assertEquals($min, $groupings->min(), 'values on round  '  . ': ' . $groupings);
        $this->assertEquals($max, $groupings->max());
        $this->assertEquals($sum, $groupings->sum(), 'sequence was: ' . implode(', ', $array));
        $this->assertEquals($avg, $groupings->avg());
    }
}
 