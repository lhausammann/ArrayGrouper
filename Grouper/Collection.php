<?php
/**
* Utility class to sort a Collection for a listing.
* $coll = new Collection($showtimes);
* $coll->orderBy('title', projection);
*
*/

namespace ArrayGrouper\Grouper;
use ArrayGrouper\Exception\GroupingException;
use ArrayGrouper\Grouper\GroupExtensions;
use ArrayGrouper\Grouper\Group;
/**
 * This class allows to group a list of data.
 */

class Collection
{
    const GROUP_ASCENDING = 'asc';
    const GROUP_DESCENDING = 'desc';

    protected $result = null;
    protected $applied = false;
    protected $orderBys = array(); // children order

    private $groupOrderBys = array(); // group order
    private $fns = array(); // callback grouping functions
    private $data = array();

    public function __construct($data = array())
    {

        $this->data = $data;
    }

    public function sortBy($orderBys)
    {
        if (is_array($orderBys)) {
            $this->orderBys = array_merge($this->orderBys, $orderBys);
        } else {
            $this->orderBys[] = $orderBys;
        }

        return $this;
    }

    /**
     * @param array $fields. More than one grouping is allowed.
     * @return Collection
     */
    public function groupBy($caption, array $fields, $orderBy = self::GROUP_ASCENDING)
    {
        $groupings = array();

        foreach ($fields as $field) {

            $groupings[] = $field;
        }
        $this->groupings[$caption] = $groupings;
        $this->groupOrderBys[$caption] = $orderBy;

        return $this;
    }

    public function groupByDescending($caption, array $fields)
    {
        return $this->groupBy($caption, $fields, self::GROUP_DESCENDING);
    }

    /**
     * @desc groups and orders all items.
     * @return array
     */
    public function apply()
    {
        if (! $this->data) {

            throw new \Exception('data must be set to group accordingly.');
        }

        if ($this->applied) {
            return $this->result; // do not apply more than once
        }

        $this->applied = true;
        $groupings = $this->groupIt($this->data, $this->groupings);
        // function can be evaluated by groups as well if they call it.
        $groupings->registerFunctions($this->fns);
        $groupings->registerExtension(new GroupExtensions());
        return $this->result = $groupings;
    }

    /**
     * Registers a function to use for grouping by providing a key.
     * @param $name The name to use when using groupBy.
     * @param $group The Root Entity which is accessed by the function.
     * TODO: Make this more clear, e.g. use movie.releaseDate instead of just movie
     * @param $fn The callback function which must generate and return the key for grouping. The current show is given as parameter.
     */
    public function registerGroupingFunction($name, $fn)
    {
        $this->fns[$name] = $fn;
        return $this;
    }

    /**
     * Creates a key which is used for ordering and grouping all results.
     * @param $data
     * @param $keys
     * @return string
     */
    protected function createOrderKey($data, $keys)
    {
        $key = '';
        foreach ($keys as $orderBy) {

            if (isset($this->fns[$orderBy] )) {
                // do we have a custom registered grouping fn?
                $key .= '-' . $this->fns[$orderBy]($data);
            } elseif (is_array($data)) {
                $key .= '-' . $data[$orderBy] ?: '0';
            } elseif (is_object($data)) {
                $method = 'get' . ucfirst($orderBy);
                $key .= $this->toString($data->$method()) . '-';
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
     * @return ShowtimesGroup A grouped tree.
     */
    private function groupIt($structure, $groupArray)
    {
        // current grouping fields
        $caption = key($groupArray);
        $groupValues = array_shift($groupArray);
        $group = new Group($caption, $groupValues, Group::GROUP);
        $groupings = array();
        // group the flat structure
        foreach ($structure as $data) {
            $key = strtolower($this->createOrderKey($data, $groupValues)); // remove strtolower if you need to distinguish between upper/lowercase
            if (isset($groupings[$key])) {
                $groupings[$key][] = $data;
            } else {
                $groupings[$key] = array($data);
            }
        }

        // sort structure by generated key using group order
        $order = $this->groupOrderBys[$caption];
        if ($order == 'asc') {
            uksort($groupings, 'strnatcmp');
        } else if ($order == 'desc') {
            uksort($groupings, function($a, $b) {return -strnatcmp($a, $b);});
        } else {
            throw new GroupingException("Expected desc or asc [defautl] for grouping, but given was: " . $order);
        }

        // group entries recursively if we have grouping criteria left
        if (count($groupArray)) {
            foreach ($groupings as $key => $values) {
                $group->addChild($c = $this->groupIt($values, $groupArray));
                $c->setKey($key);
            }
        } else {


            // No group criteria left, create leave node.
            foreach ($groupings as $key => $g) {
                $child = new Group(implode('-',$groupValues), $groupValues, Group::LEAF);
                $child->replaceChildren(array_values($g));
                $child->setKey($key);
                $child->setOrderBys($this->orderBys);
                $child->setParent($group);
                $group->addChild($child);
            }
        }

        return $group;
    }
}

