![alt text](https://travis-ci.org/lhausammann/ArrayGrouper.svg "Build status")


ArrayGrouper
============

Group structured array entries on multiple levels. Supported types are objects and arrays (and values when using custom grouping).
Each node data is encapsulated in a class which provides methods for formatting and ArrayAccess methods.

*Example simple array grouping*

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
shuffle($array);


$coll = new Collection($array);
$coll->groupBy('firstLevel', array('director'))
     ->groupBy('secondLevel', array('year', 'title'))
     ->orderByDescending('rating');

$groups = $coll->apply();

/** @var $child \ArrayGrouper\Grouper\Group */
foreach($groups->getChildren() as $child) {
    echo $child->formatCaption('<h1>Directed by %director%</h1>');
    foreach ($child->getChildren() as $node) {
        echo $node->formatCaption('%title% - %year%) . '<br />'; // also possible: $node["title"]
    }
}
```

*Outputs:*

# Dennis Hopper

Easy Rider - 1969 - 4.2

# Jim Jarmush

Coffee & Cigarettes - 2004 - 3.7

#Max Maxxen

A really bad movie - 2014 - 0.1

#Wes Anderson

The royal tennenbaums - 2001 - 3.7
A life aquatic - 2014 - 4.1
The grand budapest hotel - 2014 - 4


*Example custom grouping*


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

shuffle($array);


/** custom grouping using a registered function */
$coll = new Collection($array);
$coll->registerGroupingFunction('century', function($arr) {

    return (int) ucfirst($arr['year'] / 100 ) + 1 . ' Jh.';

});

*Outputs:*

# 21 Jh.

2001 - The royal tennenbaums (21 Jh.)
2004 - Coffee & Cigarettes (21 Jh.)
2014 - A life aquatic (21 Jh.)
2014 - A really bad movie (21 Jh.)
2014 - The grand budapest hotel (21 Jh.)

# 20 Jh.

1969 - Easy Rider (20 Jh.)


$coll->groupByDescending('centuryGroup', array('century'))
     ->groupBy('anotherKey', array('year','title'));
$groups = $coll->apply();

foreach($groups->getChildren() as $child) {
    echo $child->formatCaption('<h1>%century%</h1>'); // use it in caption
    foreach ($child->getChildren() as $node) {
        echo $node->formatCaption('%year% - %title% (%century%)<br />');
    }
}

```
