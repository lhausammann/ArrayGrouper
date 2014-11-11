<?php
require_once('vendor/autoload.php');
set_time_limit(-1);
error_reporting(E_ALL);
ini_set('display_errors', true);

function setUp2Levels()
{
    $array = range(0, 1000);
    $shuffleTime = 0;
    $time = microtime(true);
    $collection = new \ArrayGrouper\Grouper\Collection($array);
    $collection->registerGroupingFunction('byRandom', function($element) { return rand(0,5); });
    $collection->registerGroupingFunction('evenUneven', function($element) { return $element % 2; });
    for ($i = 0; $i < 100; $i++) {
        shuffle($array);
        $collection->groupByDescending('key', array('byRandom'));
        $collection->groupBy('testGroup', array('evenUneven'));
        $collection->groupByDescending('key2', array('byRandom'));
        $collection->apply($array, false);
    }

    return microtime(true) - $time - $shuffleTime;
}

echo 'setting up  1000 times a structured group using two functions took:' . setUp2Levels() . ' seconds';
echo '<hr />';
echo 'getting the first node took:' . '';