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
            $elements = $group->getNodes();
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
        for ($i = 0; $i < 50; $i++) { // 100 test runs
            $array = array();
            for ($j = 0; $j < 100; $j++) {
               $array[$j] = mt_rand(-1,200);
            }
            $count = count($array);
            $sum = array_sum($array);
            $min = min($array);
            $max = max($array);
            $collection = new Collection($array);
            $collection->registerGroupingFunction('evenUneven', function($element) {return $element % 2; });
            $collection->registerGroupingFunction('byRandom', function($element) { return rand(7,9); });
            $collection->groupBy('testGroup', array('evenUneven'));
            $collection->groupByDescending('key', array('byRandom'));
            $groupings = $collection->apply();
            $this->assertEquals($count, $groupings->count());
            $this->assertEquals($min, $groupings->min(), 'values on round  ' . $i . ': ' . $groupings);
            $this->assertEquals($max, $groupings->max());
            // $this->assertEquals(17, $groupings->max(), 'sequence was: ' . implode(', ', $array));
            // $this->assertEquals( $sum, $groupings->sum());
        }
    }
}
 