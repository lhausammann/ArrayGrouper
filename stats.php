<?php
require_once('vendor/autoload.php');
set_time_limit(-1);
error_reporting(E_ALL);
ini_set('display_errors', true);

function setUp2Levels()
{

    $array = range(0, 1000);
    //shuffle($array);
    $time = microtime(true);
    $collection = new \ArrayGrouper\Grouper\Collection($array);
    $collection->registerGroupingFunction('byRandom', function($element) { return rand(0,20); });
    $collection->registerGroupingFunction('evenUneven', function($element) { return $element % 2; });
    for ($i = 0; $i < 10; $i++) {
        $collection->groupBy('testGroup', array('evenUneven'));
        $collection->groupByDescending('key', array('byRandom'));
        $collection->groupByDescending('key2', array('byRandom'));
        $collection->apply($array);
    }

    unset($group);
    return microtime(true) - $time;
}

function setUp3Levels()
{
    $array = array();
    $array = range(0,10000);
    shuffle($array);
    $time = microtime(true);
    for ($i = 0; $i < 1000; $i++) {
        $collection = new \ArrayGrouper\Grouper\Collection($array);
        $collection->registerGroupingFunction('byRandom', function($element) { return rand(0,100); });
        $collection->groupBy('testGroup', array('evenUneven'));
        $collection->groupByDescending('key', array('byRandom'));
        $g = $collection->apply();
    }
    echo $g;
    return microtime(true) - $time;
}

echo 'setting up  1000 times a structured group using two functions took:' . setUp2Levels() . ' seconds';
echo '<hr />';
echo 'getting the first node took:' . '';