<?php


namespace ArrayGrouper\Grouper;

use \ArrayGrouper\Exception\GroupingException;

/**
 * class allows to group a list of data.
 *
 * <code>
 * $movies = array(
 *           array('title' => 'The grand budapest hotel',    'director' => 'Wes Anderson',       'year' => '2014',  'rating' => 4.1),
 *           array('title' => 'Easy Rider',                  'director' => 'Dennis Hopper',      'year' => '1969',  'rating' => 4.2),
 *           array('title' => 'Coffee & Cigarettes',         'director' => 'Jim Jarmush',        'year' => '2004', 'rating'  => 3.7),
 *           array('title' => 'A life aquatic',              'director' => 'Wes Anderson',       'year' => '2014', 'rating'  => 4.1),
 *           array('title' => 'The royal tennenbaums',       'director' => 'Wes Anderson',       'year' => '2001', 'rating'  => 3.7),
 *           array('title' => 'The grand budapest hotel',    'director' => 'Wes Anderson',       'year' => '2014', 'rating'  => 4.1),
 *           array('title' => 'A really bad movie',          'director' => 'Max Maxxen',         'year' => '2014', 'rating'  => 0.1),
 *       );
 * $coll = new Collection($showtimes);
 * $coll->groupBy('title', year); 
 * $coll->groupBy('director');
 * $nodes = $collection->apply();
 * foreach($nodes as $node) {
 *     echo $node["title"]; // can access title on that level
 *      foreach ($node as $director) {
 *           echo $director['director'];
 *           foreach ($director->getElements() as $element) {
 *               echo $element["rating"]; // here we are on the actual node.
 *           }
 *      }   
 *}
 * </code>
 *
 * @license ../LICENSE.md
 * MIT - For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Collection
{
    const GROUP_ASCENDING = 1;
    const GROUP_DESCENDING = 2;

    protected $result = null;
    protected $applied = false;
    protected $groupInfo = null;


    private $orderBys = array(); //sort fields not used for grouping
    private $groupOrderBys = array(); // group order (asc, desc), also containig orderBys order.


    private $groupings = array(); // group fields
    private $fns = array(); // callback grouping functions
    private $data = array(); // the data to group

    public function __construct($data = array())
    {

        $this->data = $data;
    }

    /**
     * @param array $fields. More than one grouping is allowed.
     * @return Collection
     */
    public function groupBy($caption, array $fields, $orderBy = self::GROUP_ASCENDING)
    {
        $this->groupings[$caption] = $fields;
        $this->groupOrderBys[$caption] = $orderBy;

        return $this;
    }

    public function groupByDescending($caption, array $fields)
    {
        return $this->groupBy($caption, $fields, self::GROUP_DESCENDING);
    }


    public function orderBy($caption, array $fields, $orderBy = self::GROUP_ASCENDING)
    {
        $this->orderBys[$caption] = $fields;
        $this->groupOrderBys[$caption] = $orderBy;

        return $this;
    }

    public function orderByDescending($caption, array $fields)
    {
        return $this->orderBy($caption, $fields, self::GROUP_DESCENDING);
    }

    /**
     * @desc groups and orders all items.
     * @return array
     */
    public function apply($data = array(), $order = true)
    {
        if ($this->applied && !$data) {
            return $this->result; // do not apply more than once
        }
        if (! $this->groupings) {
            throw new GroupingException("Must have at least one group or order field. Use groupBy, groupByDescending, orderBy, orderByDescending before calling apply.");
        }

        if ($data) {
            $this->data = $data;
        } elseif (! $this->data) {

            throw new \Exception('Array of data must be set. Set it in group constructor or pass it as parameter to apply.');
        }

        $this->applied = true;
        $this->groupInfo = new GroupInfo;
        if ($order) {
            $groupings = $this->groupIt($this->data, $this->groupings, array());
        } else {
            $groupings = $this->groupItWithoutSorting($this->data, $this->groupings, array());
        }

        $groupings->registerFunctions($this->fns);
        $groupings->registerExtension(new GroupExtensions());
        return $this->result = $groupings;
    }

    /**
     * Registers a function to use for grouping by providing a key.
     * @param $name The name to use when using groupBy.
     * @param $fn The callback function which must generate and return the key for grouping. The current show is given as parameter.
     */
    public function registerGroupingFunction($name, $fn)
    {
        $this->fns[$name] = $fn;
        return $this;
    }

    public function createOrderKey2(&$data, &$keys)
    {
        $key = '';
        foreach($keys as &$orderBy) {
            if (isset($this->fns[$orderBy])) {
                $key .= '-' . $this->fns[$orderBy]($data);
                continue;
            } elseif (is_array($data)) {
                $key .= '-' . $data[$orderBy] ?: '0';
                continue;
            } elseif (is_object($data)) {
                $method = 'get' . ucfirst($orderBy);
                $key .= '-' . $this->toString($data->$method());
                continue;
            }                
        }

        return $key;
    }

    protected function toString($var)
    {
        if (! $var) {
            return '';

        } elseif (is_scalar($var)) {
            return $var;

        } elseif (is_object($var)) {
            if (method_exists($var, 'getName')) {
                return $var->getName();
            } elseif (method_exists($var, 'getId')) {
                return $var->getId() . ',';
            } elseif (get_class($var) === 'Date' || get_class($var) === 'DateTime') {
                return $var->format('Y-m-d H:i:s');
            } else {
                throw new Exception("Could not resolve var: " . get_class($var));
            }

        } elseif (is_array($var)) {
            // stop recursive displaying array values, use * instead..
            return '[' . str_repeat('*,',count($var)) . ']';
        }

        throw new GroupingException("Could not convert to string value: " . gettype($var) . is_object($var) ? get_class($var) : '');
    }

    /**
     * Takes a flat array as input, and groups it recursively.
     * @param $structure array $data.
     * @param $groupArray The groups array. E.g array('first' => array('title'), 'second' => array('date', 'time'))
     * @param $processedGroupings The processed fields so far. Needed to check access on queried fields.
     * @return ShowtimesGroup A grouped tree.
     */
    private function groupIt(&$structure, $groupArray, $processedGroupings)
    {
        //$groupInfo = new GroupInfo();
        $this->groupInfo->groupings = $groupArray;
        // current grouping fields
        $caption = key($groupArray);
        $groupValues = $groupArray[$caption];
        unset($groupArray[$caption]);

        $group = new Group($caption, Group::GROUP, null, $this->groupInfo, $processedGroupings);
        $processedGroupings = array_merge($groupValues, $processedGroupings);

        $groupings = array();
        // group the flat structure

        $c = count($structure);
        $i = -1;
        while (++$i < $c) {
            $key = ($this->createOrderKey2($structure[$i], $groupValues));

            if (isset($groupings[$key])) {
                $groupings[$key][] = &$structure[$i];
            } else {
                $groupings[$key] = array($structure[$i]);
            }
        }

        // sort structure by generated key using group order
        // TODO: Maybe we can presort the input?
        $this->groupOrderBys[$caption] === 1 ? uksort($groupings, 'strnatcmp') : uksort($groupings, function($a, $b) {return -strnatcmp($a, $b);});


        if ($groupArray) { //next grouping: take the grouped array and group each subgroup:
            while ($g = each($groupings))
                $group->addChild($this->groupIt($g[1], $groupArray, $processedGroupings));
            return $group;
        // the last group array is encapsulated into leaf nodes
        } else {
            while ($g = each($groupings))
                $group->addChild(new Group('', Group::LEAF, $g[1], $this->groupInfo, $processedGroupings));
            return $group;
        }

        return $group;
    }
    
    /**
     * Takes a flat array as input, and groups it recursively, but does not order the elements.
     * @param $structure array $data.
     * @param $groupArray The groups array. E.g array('first' => array('title'), 'second' => array('date', 'time'))
     * @return ShowtimesGroup A grouped tree.
     */
    private function groupItWithoutSorting(&$structure, $groupArray, $processedGroupings)
    {
        // current grouping fields
        $caption = key($groupArray);
        $groupValues = $groupArray[$caption];
        unset($groupArray[$caption]);
        //$groupInfo = new GroupInfo();
        $processedGroupings = array_merge($processedGroupings, $groupValues);


        $group = new Group($caption, Group::GROUP, null, $this->groupInfo, $processedGroupings);
        $this->groupInfo->groupings = $groupArray;

        $groupings = array();
        // group the flat structure

        $c = count($structure);
        $i = -1;
        while (++$i < $c) {
            $key = ($this->createOrderKey2($structure[$i], $groupValues));

            if (isset($groupings[$key])) {
                $groupings[$key][] = &$structure[$i];
            } else {
                $groupings[$key] = array($structure[$i]);
            }
        }

        if ($groupArray) { //next grouping: take the grouped array and group each subgroup:
            while ($g = each($groupings))
                $group->addChild($this->groupIt($g[1], $groupArray, $processedGroupings), $this->groupInfo);
            return $group;
        // the last group array is encapsulated into leaf nodes
        } else {
            while ($g = each($groupings))
                $group->addChild(new Group('', Group::LEAF, $g[1], $processedGroupings), $this->groupInfo);
            return $group;
        }

        return $group;
    }
}
