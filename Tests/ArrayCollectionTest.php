<?php
/**
 * Created by PhpStorm.
 * User: Luzius Hausammann
 * Date: 07.11.14
 * Time: 19:44
 */
namespace ArrayGrouper\s;
use ArrayGrouper\Grouper\Collection;
class ArrayCollectionTestt extends \PHPUnit_Framework_TestCase  {

    public function getSetup() {
        $arr = array(
            array('title' => 'The grand budapest hotel',    'director' => 'Wes Anderson',       'year' => '2014',  'rating' => 4.1),
            array('title' => 'Easy Rider',                  'director' => 'Dennis Hopper',      'year' => '1969',  'rating' => 4.2),
            array('title' => 'Coffee & Cigarettes',         'director' => 'Jim Jarmush',        'year' => '2004', 'rating'  => 3.7),
            array('title' => 'A life aquatic',              'director' => 'Wes Anderson',       'year' => '2014', 'rating'  => 4.1),
            array('title' => 'The royal tennenbaums',       'director' => 'Wes Anderson',       'year' => '2001', 'rating'  => 3.7),
            array('title' => 'The grand budapest hotel',    'director' => 'Wes Anderson',       'year' => '2014', 'rating'  => 4.1),
            array('title' => 'A really bad movie',          'director' => 'Max Maxxen',         'year' => '2014', 'rating'  => 0.1),
        );


        for ($i = 0; $i<50000;$i++) {
            $arr[] = array('title' => 'The grand budapest hotel',    'director' => 'Wes Anderson',       'year' => '2014',  'rating' => 4.1);  
        }

        shuffle($arr);
        return $arr;
        
    }

    public function testCaptionByYearAndTitle() {
        for($x = 0;$x<10;$x++) {
            $coll = new Collection($this->getSetup());
            $coll->groupBy('aKey', array('year'))
                 ->groupBy('anotherKey', array('title'))
                 ->orderBy('sortKey', array('rating'));
            $groups = $coll->apply(null,false);


            $expectedYears = array(1969,2001,2004,2014,2014);
            $expectedTitles = array('Easy Rider', 'The royal tennenbaums', 'Coffee & Cigarettes', 'A life aquatic', 'A really bad movie', 'The grand budapest hotel');

            /** @var $yearGroup \ArrayGrouper\Grouper\Group */
            foreach($groups->getChildren() as $yearGroup) {
                $yearIdx = array_search($year = $yearGroup->formatCaption('%year%'), $expectedYears);
                if ($yearIdx !== false)
                    $year = array_splice($expectedYears, $yearIdx)[0];
                // we also can access the year by directly calling
                $this->assertEquals($yearGroup["year"], $year);
                /** @var $titleNode \ArrayGrouper\Grouper\Group */
                foreach($yearGroup->getChildren() as $titleNode) {
                    //$this->assertEquals($title = array_shift($expectedTitles), $titleNode->getValue('title'));
                    $titleIdx = array_search($title = $titleNode->getValue('title'), $expectedTitles);
                    if ($titleIdx !== false) {
                        $title = array_splice($expectedTitles, $titleIdx)[0];
                    }
                    $this->assertEquals($titleNode["title"], $title);

                    $okYear = true; $okTitle = true;
                    foreach($titleNode->getElements() as $node)
                    {
                        echo $node["title"] . ": " . $node["year"];
                        $okTitle = $okTitle & $node["title"] == $title;
                        $okYear = $okYear & $node["year"] == $year;
                        //$this->assertEquals($title, $node['title']);
                        //$this->assertEquals($year, $node['year']);
                    }

                    $this->assertTrue($okYear == true, "not every year in group was: " . $year);
                    $this->assertTrue($okTitle == true, "not every title in group was: " . $title);
                }
            }
        }
    }

    public function testGroupByAZTitleYear() {
        $data = $this->getSetup();
         $coll = new Collection($this->getSetup());
         $coll->registerGroupingFunction("az", function ($element) { return strtoupper($element["title"]);} );
         $coll->groupBy("az", array("az"))
            ->groupBy('aKey', array('year'))
            ->groupBy('anotherKey', array('title'))
            ->orderBy('sortKey', array('rating'));
        $groups = $coll->apply(null, false);
        foreach ($groups->getChildren() as $group) {
            echo $group["az"] . "\n";
        }
        $this->assertTrue(true);
    }

    public function testDynamicCenturyFieldShouldCreateCenturyGroup() {
        /** custom grouping */
        $arr = array(
            array('title' => 'The grand budapest hotel',    'director' => 'Wes Anderson',       'year' => '2014',  'rating' => 4.1),
            array('title' => 'Easy Rider',                  'director' => 'Dennis Hopper',      'year' => '1969',  'rating' => 4.2),
            array('title' => 'Coffee & Cigarettes',         'director' => 'Jim Jarmush',        'year' => '2004', 'rating'  => 3.7),
            array('title' => 'A life aquatic',              'director' => 'Wes Anderson',       'year' => '2014', 'rating'  => 4.1),
            array('title' => 'The royal tennenbaums',       'director' => 'Wes Anderson',       'year' => '2001', 'rating'  => 3.7),
            array('title' => 'The grand budapest hotel',    'director' => 'Wes Anderson',       'year' => '2014', 'rating'  => 4.1),
            array('title' => 'A really bad movie',          'director' => 'Max Maxxen',         'year' => '2014', 'rating'  => 0.1),
        );
        $arr = $this->getSetup();
        $coll = new Collection($arr);

        $coll->registerGroupingFunction('century', function($arr) {

            return (int) ucfirst($arr['year'] / 100 ) + 1 ; // 20 / 21 (century)
        });

        $expectedCenturies = array(21,20);

        $coll->groupByDescending('centuryGroup', array('century'))
             ->groupBy('anotherKey', array('year','title'));
        $groups = $coll->apply();

        foreach($groups->getChildren() as $child) {
            $expected = array_shift($expectedCenturies);
            $this->assertTrue($expected == $child['century']);
            
        }
    }
        
}