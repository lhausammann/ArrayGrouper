<?php
/**
 * Created by PhpStorm.
 * User: Luzius Hausammann
 * Date: 07.11.14
 * Time: 19:44
 */
namespace ArrayGrouper\Tests;
use ArrayGrouper\Grouper\Collection;
class ArrayCollectionTest extends \PHPUnit_Framework_TestCase  {

    public function getSetup() {
        $arr = array(
            array('title' => 'The grand budapest hotel',    'director' => 'Wes Anderson',       'year' => '2014',  'rating' => 4.1),
            array('title' => 'Easy Rider',                  'director' => 'Dennis Hopper',      'year' => '1969',  'rating' => 4.2),
            array('title' => 'Coffee & Cigarettes',         'director' => 'Jim Jarmush',        'year' => '2004', 'rating'  => 3.7),
            array('title' => 'A life aquatic',              'director' => 'Wes Anderson',      'year' => '2014', 'rating'  => 4.1),
            array('title' => 'The royal tennenbaums',       'director' => 'Wes Anderson',          'year' => '2001', 'rating' => 3.7),
            array('title' => 'The grand budapest hotel',    'director' => 'Wes Anderson', 'year' => '2014', 'rating' => 4.1),
            array('title' => 'A really bad movie',  'director' => 'Max Maxxen', 'year' => '2014', 'rating' => 0),
        );

        shuffle($arr);
        return $arr;
    }

    public function testCaptionByYearAndTitle() {
        for($x = 0;$x<10;$x++) {
            $coll = new Collection($this->getSetup());
            $coll->groupBy('aKey', array('year'))
                ->groupBy('anotherKey', array('title'))
                ->orderBy('sortKey', array('rating'));
            $groups = $coll->apply();

            $expectedYears = array(1969,2001,2004,2014,2014);
            $expectedTitles = array('Easy Rider', 'The royal tennenbaums', 'Coffee & Cigarettes', 'A life aquatic', 'A really bad movie', 'The grand budapest hotel');

            /** @var $yearGroup \ArrayGrouper\Grouper\Group */
            foreach($groups->getChildren() as $yearGroup) {
                $this->assertEquals ($year = array_shift($expectedYears), $yearGroup->formatCaption('%year%'));
                /** @var $titleNode \ArrayGrouper\Grouper\Group */

                foreach($yearGroup->getChildren() as $titleNode) {
                    $this->assertEquals($title = array_shift($expectedTitles), $titleNode->getValue('title'));
                    // check each node
                    foreach($titleNode->getElements() as $node)
                    {
                        $this->assertEquals($title, $node['title']);
                        $this->assertEquals($year, $node['year']);
                    }
                }
            }
        }
    }
}



/**


$coll = new Collection($array);
$coll->groupBy('aKey', array('director'))
    ->groupBy('anotherkey', array('year', 'title'))
    ->sortBy('rating');
$groups = $coll->apply();

foreach($groups->getChildren() as $child) {
    echo $child->formatCaption('<h1>%director%</h1>');
    foreach ($child->getChildren() as $node) {
        echo $node->formatCaption('%title% - %year% - %rating%') . '<br />';
    }
}


$coll = new Collection($array);
$coll->registerGroupingFunction('century', function($arr) {

    return (int) ucfirst($arr['year'] / 100 ) + 1 . ' Jh.';

});

$coll->groupByDescending('aKey', array('century'))
    ->groupBy('anotherKey', array('year','title'));
$groups = $coll->apply();

foreach($groups->getChildren() as $child) {
    echo $child->formatCaption('<h1>%century%</h1>'); // use it in caption
    var_dump($child->min('rating'));

    foreach ($child->getChildren() as $node) {
        echo $node->formatCaption('%year% - %title%');
        echo $node->century(); // and use it as object property
        $node->getElements();
    }
}
 */