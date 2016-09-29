<?php
/**
 * Created by PhpStorm.
 * User: Luzius Hausammann
 * Date: 07.11.14
 * Time: 19:44
 */

namespace ObjectGrouper\Tests;
use ArrayGrouper\Grouper\Collection;
use ArrayGrouper\Exception\GroupingException;
class ObjectCollectionTestt extends \PHPUnit_Framework_TestCase  {

    public function getSetup() {
        $init = array(
            array('title' => 'The grand budapest hotel',    'director' => 'Wes Anderson',       'year' => '2014',  'rating' => 4.1),
            array('title' => 'Easy Rider',                  'director' => 'Dennis Hopper',      'year' => '1969',  'rating' => 4.2),
            array('title' => 'Coffee & Cigarettes',         'director' => 'Jim Jarmush',        'year' => '2004', 'rating'  => 3.7),
            array('title' => 'A life aquatic',              'director' => 'Wes Anderson',       'year' => '2014', 'rating'  => 4.1),
            array('title' => 'The royal tennenbaums',       'director' => 'Wes Anderson',       'year' => '2001', 'rating'  => 3.7),
            array('title' => 'The grand budapest hotel',    'director' => 'Wes Anderson',       'year' => '2014', 'rating'  => 4.1),
            array('title' => 'A really bad movie',          'director' => 'Max Maxxen',         'year' => '2014', 'rating'  => 0.1),
        );

        $arr = array();
        foreach ($init as $i => $data) {
            $arr[] = new Movie($data);
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
            $groups = $coll->apply(null, true);


            $expectedYears = array(1969, 2001, 2004, 2014, 2014);
            $expectedTitles = array('Easy Rider', 'The royal tennenbaums', 
                'Coffee & Cigarettes', 'A life aquatic', 
                'A really bad movie', 'The grand budapest hotel');

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
                        $okTitle = $okTitle & $node->getTitle() == $title;
                        $okYear = $okYear & $node->getYear() == $year;
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
         $coll->registerGroupingFunction("az", function ($element) { return strtoupper($element->getTitle());} );
         $coll->groupBy("az", array("az"))
            ->groupBy('aKey', array('year'))
            ->groupBy('anotherKey', array('title'))
            ->orderBy('sortKey', array('rating'));
        $groups = $coll->apply(null, false);
        foreach ($groups->getChildren() as $group) {
            echo $group["az"] . "\n";
            echo $group->az();
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

            return (int) ucfirst($arr->getYear() / 100 ) + 1 ; // 20 / 21 (century)
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

    public function testRetrieveFromGroupOneLvelMustGetAllElements() {
        $coll =  new Collection($this->getSetup());
        $group = $coll->groupBy(23,array("year"))->apply();
        $elems = $group->getElements();
        $this->assertEquals($group->getStuff(), "stuff"); // call an element function must be possible (using call)
        //echo $group;
        $this->assertTrue($group->count() == count($elems));
        try {
            $group->ab();
        } catch(GroupingException $e) {
            $this->assertContains("failed", $e->getMessage());
        }
    }

    public function testRetrieveFromGroup2LevelsMustGetAllElements() {
        $coll =  new Collection($this->getSetup());
        $isOk = true;
        $coll->registerGroupingFunction('customGetYear', function($i) { return $i->getYear();});
        $group = $coll->groupByDescending(0, array("customGetYear"))
            ->groupByDescending(1,array("title"))
            ->apply();


        // getElements must return the actual class, no grouping wrapper
        $this->assertContainsOnlyInstancesOf(Movie::class, $group->getElements());
        $this->assertTrue(count($this->getSetup()) == count($group->getElements()));
        $itemBefore = null;

        /* because we grouped by toString, getElements must return same toString values for each group, ordered descending. */
        foreach ($group->getElements() as $i => $item) {
             if ($itemBefore) {

                if ($itemBefore->getYear() < $item->getYear()) {
                    $isOk = false;
                    break;
                }
             }

             $itemBefore = $item;

        }

        
        $this->assertTrue($isOk, "Round $i: item::getYear() " . $itemBefore->__toString() . ' was not sorted descending: item::getYear() after was: ' . $item->__toString());
    }

    public function testFunctionCallOnGroup() {
        $coll =  new Collection($this->getSetup());
        $isOk = true;
        $group = $coll->groupByDescending(5 ,array("year"))->apply();
        $this->assertEquals("stuff", $group->getStuff()); // propagate function call to first object
    }

    public function testArrayAccessOnGroupSimple() {
        $arr = ["2014", "2004", "2001", "1969"];
        $coll =  new Collection($this->getSetup());
        $isOk = true;
        $group = $coll->groupByDescending(5 ,array("year"))
            ->groupBy(6, array("title"))
            ->apply();
        echo $group->debugFields();
        foreach ($group->getChildren() as $yearGroup) {
            // we must have access to year.
            $this->assertEquals(array_shift($arr), $yearGroup["year"]);
            // fetching title is not possible on that level
            try {
                $yearGroup["title"];
                $yearGroup->debugFields();

                $this->fail("must not be possible to retrieve a non-group field title.");
            } catch(GroupingException $e) {
                // catch the excepted exception
                $this->assertTrue(true);
            } catch(\Exception $e) {
                // fail on unexpected exceptions.
                $this->fail("Exception " .$e->getMessage() . $e->getTraceAsString() . " detected.");
            }
            foreach ($yearGroup->getChildren() as $titleGroup) {
                // on the title level it must be possible to fetch title
                try {
                   $title = $titleGroup["title"];

                } catch (\Exception $e) {
                    $this->fail("It must be possible to get title from titleGroup. Msg: " .$e->getMessage() );
                }

            }
        }
    }


    public function testArrayAccessOnGroupUsingGroupingFunction() {
        $arr = ["2014", "2004", "2001", "1969"];
        $coll =  new Collection($this->getSetup());
        $coll->registerGroupingFunction("customTitle", function($object) {return strtolower($object->getTitle());}); 
        $isOk = true;
        $group = $coll->groupByDescending(5 ,array("year"))
            ->groupBy(6, array("customTitle"))
            ->apply();
        foreach ($group->getChildren() as $yearGroup) {
            // we must have access to year.
            $this->assertEquals(array_shift($arr), $yearGroup["year"]);
            // fetching title is not possible on that level
            try {
                $yearGroup["title"];
                $this->fail("must not be possible to retrieve a non-group field title.");
            } catch(GroupingException $e) {
                // catch the excepted exception
                $this->assertTrue(true);
            } catch(\Exception $e) {
                // fail on unexpected exceptions.
                $this->fail("Exception " .$e->getMessage() . $e->getTraceAsString() . " detected.");
            }
            foreach ($yearGroup->getChildren() as $titleGroup) {
                // on the title level it must be possible to fetch customTitle, but not title.
                try {
                   $title = $titleGroup["customTitle"];
                   $this->assertEquals($title, strtolower($titleGroup->getNode()->getTitle()));

                } catch (\Exception $e) {
                    $this->fail("It must be possible to get title from titleGroup. Msg: " .$e->getMessage() );
                }
                    
            }
        }
    }


    /**
     * @ExpectException(GroupingException)
     */
    public function testMissingOrderAndGroupThrows() {
        try {
            $coll =  new Collection($this->getSetup());
            $coll->apply(); 
        } catch(GroupingException $e) {
            $this->assertTrue(true);
            $this->assertContains("group", $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * @ExpectException(GroupingException)
     */
    public function testMissingFieldThrows() {
        $coll =  new Collection($this->getSetup());
        $coll->groupBy("year", array("year"));
        
        $group = $coll->apply(); 
        try {
            $group->getValue("notExisting");
        } catch (GroupingException $e) {
            $this->assertContains("must exist", $e->getMessage());
        }

    }
        
}



class Movie {
    private $director;
    private $title;
    private $year;
    private $rating;

    public function __construct($arr) {
        $this->title = $arr["title"];
        $this->year = $arr["year"];
        $this->rating = $arr["rating"];
        $this->director = $arr["director"];
    }
    public function getTitle() { return $this->title; }
    public function getDirector() { return $this->director; }
    public function getYear() { return $this->year;  }  
    public function getRating() { return $this->rating; } 
    public function getStuff() { return "stuff"; }
    public function __toString() { return sprintf("Movie '%s' (%s) is directed by %s and has rating %s.", $this->title, $this->year, $this->director, $this->rating);
    }
    
}

