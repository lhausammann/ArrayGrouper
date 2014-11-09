<?php
require_once('vendor/autoload.php');
set_time_limit(-1);
error_reporting(E_ALL);
ini_set('display_errors', true);

function setUp2Levels()
{

    $array = range(0, 10000);
    $shuffleTime = 0;
    $time = microtime(true);
    $collection = new \ArrayGrouper\Grouper\Collection($array);
    $collection->registerGroupingFunction('byRandom', function($element) { return rand(0,20); });
    $collection->registerGroupingFunction('evenUneven', function($element) { return $element % 2; });
    for ($i = 0; $i < 1; $i++) {
        shuffle($array);
        $collection->groupByDescending('key', array('byRandom'));
        $collection->groupBy('testGroup', array('evenUneven'));
        //$collection->groupByDescending('key2', array('byRandom'));
         $c = $collection->apply($array);
    }

    return microtime(true) - $time - $shuffleTime;
}


echo 'setting up  1000 times a structured group using two functions took:' . setUp2Levels() . ' seconds';
echo '<hr />';
echo 'getting the first node took:' . '';