<?php
/**
 * Exampple for grouping arrays
 */

namespace ArrayGrouper;
use ArrayGrouper\Grouper\Collection;

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';


$array = array(
    array('title' => 'The grand budapest hotel',    'director' => 'Wes Anderson',       'year' => '2014',  'rating' => 4.0),
    array('title' => 'Easy Rider',                  'director' => 'Dennis Hopper',      'year' => '1969',  'rating' => 4.2),
    array('title' => 'Coffee & Cigarettes',         'director' => 'Jim Jarmush',        'year' => '2004', 'rating'  => 3.7),
    array('title' => 'A life aquatic',              'director' => 'Wes Anderson',       'year' => '2014', 'rating'  => 4.1),
    array('title' => 'The royal tennenbaums',       'director' => 'Wes Anderson',       'year' => '2001', 'rating'  => 3.7),
    array('title' => 'The grand budapest hotel',    'director' => 'Wes Anderson',       'year' => '2014', 'rating'  => 4.1),
    array('title' => 'A really bad movie',          'director' => 'Max Maxxen',         'year' => '2014', 'rating'  => 0.1),
);

shuffle($array);


/** custom grouping */
$coll = new Collection($array);
$coll->registerGroupingFunction('century', function($arr) {

    return (int) ucfirst($arr['year'] / 100 ) + 1 . ' Jh.';

});



$coll->groupByDescending('centuryGroup', array('century'))
     ->groupBy('anotherKey', array('year','title'));
$groups = $coll->apply();

foreach($groups->getChildren() as $child) {
    echo $child->formatCaption('<h1>%century%</h1>'); // use it in caption
    foreach ($child->getChildren() as $node) {
        echo $node->formatCaption('%year% - %title% (%century%)<br />');
    }
}

echo "<hr />";
$coll = new Collection($array);
$coll->groupBy('key', array('year', 'title'));
$groups = $coll->apply();

/** @var $child \ArrayGrouper\Grouper\Group */
foreach($groups->getChildren() as $child) {
    echo $child->formatCaption('Im Jahr %year% entstand %title% <br />');
}

$coll = new Collection($array);
$coll->groupBy('aKey', array('year'))
     ->groupBy('anotherKey', array('title'));
$groups = $coll->apply();

/** @var $child \ArrayGrouper\Grouper\Group */
foreach($groups->getChildren() as $child) {
    echo $child->formatCaption('<h1>%year%</h1>');
    foreach ($child->getChildren() as $node) {
        echo $node->getValue('title') . $node->getValue('rating') . '---' .$node->count() . '<br />';
    }
    echo $child->formatCaption('Im Jahr %year% entstand %title% <br />');
}

$coll = new Collection($array);
$coll->groupBy('aKey', array('director'))
    ->groupBy('anotherkey', array('year', 'title'))
    ->orderByDescending('key', array('rating'));

$groups = $coll->apply();

/** @var $child \ArrayGrouper\Grouper\Group */
foreach($groups->getChildren() as $child) {
    echo $child->formatCaption('<h1>%director%</h1>');
    foreach ($child->getChildren() as $node) {
        echo $node->formatCaption('%title% - %year% - %rating%') . '<br />';
    }
}


$coll = new Collection($array);
$coll->groupByDescending('aKey', array('year'))
    ->orderByDescending('sortKey', array('rating'));
$groups = $coll->apply();

foreach($groups->getChildren() as $child) {
    echo $child->formatCaption('<h1>%year%</h1>'); // use it in caption

    foreach ($child->getChildren() as $arr) {
        echo $child->formatCaption('<h3>%rating%: %title%</h3>', $arr); // format the raw data
    }
}






