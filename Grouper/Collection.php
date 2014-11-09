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
    const GROUP_ASCENDING = 1;
    const GROUP_DESCENDING = 2;

    protected $result = null;
    protected $applied = false;

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
    public function apply($data = array(), $isOrdered = false)
    {
        if ($this->applied && ! $data) {
            return $this->result; // do not apply more than once
        }

        if ($data) {
            $this->applied = false;
            $this->data = $data;
        } elseif (! $this->data) {

            throw new \Exception('data must be set to group accordingly.');
        }

        // reverse the data for faster processing
        //$this->data = array_reverse($this->data, true);

        $this->applied = true;
        if ($isOrdered == false) {
            $this->data = $this->orderIt($this->data);
        }
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
    public function createOrderKey(&$data, &$keys)
    {
        $key = '';
        foreach ($keys as $orderBy) {
            if (isset($this->fns[$orderBy] )) {
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
     * Order structure first by group fields, then by order fields.
     * @param $structure
     * @return mixed
     */
    private function orderIt(&$structure)
    {
        $self = $this;
        // for ordering, include the order bys.
        $groupings = array_merge($this->groupings, $this->orderBys);
        $groupOrderBys = $this->groupOrderBys;
        usort($structure, function($a,$b) use ($self, $groupings, $groupOrderBys) {
            // sort by al levels:
            foreach($groupings as $caption => $orderBys) {
                if (($ka = $self->createOrderKey($a, $orderBys)) === ($kb = $self->createOrderKey($b, $orderBys))) {
                    continue;
                } else {
                    // we have the diff. Sort according to asc/desc
                    if ($groupOrderBys[$caption] === Collection::GROUP_DESCENDING) {
                        return -strnatcmp($ka, $kb);
                    } else {
                        return strnatcmp($ka, $kb);
                    }
                }
            }

            return 0;
        });

        return $structure;
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
        $groupValues = $groupArray[$caption];
        unset($groupArray[$caption]);

        $group = new Group($caption, Group::GROUP);
        $groupings = array();
        // group the flat structure

        $c = count($structure);
        $i = -1;
        while (++$i < $c) {
            $key = ($this->createOrderKey($structure[$i], $groupValues));
            if (isset($groupings[$key])) {
                $groupings[$key][] = & $structure[$i];
            } else {
                $groupings[$key] = array($structure[$i]);
            }
        }

        if ($groupArray) //next grouping: take the grouped array and group each subgroup:
            while ($g = each($groupings))
                $group->addChild($this->groupIt($g[1], $groupArray));
        else  // the last group array is encapsulated into leaf nodes.
            while ($g = each($groupings))
                $group->addChild(new Group('', Group::LEAF, $g[1]));

        return $group;
    }
}

