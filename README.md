![alt text](https://travis-ci.org/lhausammann/ArrayGrouper.svg "Build status")


ArrayGrouper
============

Group structured array entries on multiple levels.

*Example*

```php
$array = array(
    array('title' => 'The grand budapest hotel',    'director' => 'Wes Anderson',       'year' => '2014',  'rating' => 4.0),
    array('title' => 'Easy Rider',                  'director' => 'Dennis Hopper',      'year' => '1969',  'rating' => 4.2),
    array('title' => 'Coffee & Cigarettes',         'director' => 'Jim Jarmush',        'year' => '2004', 'rating'  => 3.7),
    array('title' => 'A life aquatic',              'director' => 'Wes Anderson',       'year' => '2014', 'rating'  => 4.1),
    array('title' => 'The royal tennenbaums',       'director' => 'Wes Anderson',       'year' => '2001', 'rating'  => 3.7),
    array('title' => 'The grand budapest hotel',    'director' => 'Wes Anderson',       'year' => '2014', 'rating'  => 4.1),
    array('title' => 'A really bad movie',          'director' => 'Max Maxxen',         'year' => '2014', 'rating'  => 0.1),

);

$coll = new Collection($array);
$coll->groupBy('aKey', array('director'))
     ->groupBy('anotherkey', array('year', 'title'));

$groups = $coll->apply();

/** @var $child \ArrayGrouper\Grouper\Group */
foreach($groups->getChildren() as $child) {
    echo $child->formatCaption('<h1>%director%</h1>');
    foreach ($child->getChildren() as $node) {
        echo $node->formatCaption('%title% - %year% - %rating%') . '<br />';
    }
}
```


