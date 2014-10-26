<?php
/**
 * Utility class to sort a Collection for a listing.
 */
namespace Grouper\Collection;

/**
 * This class allows to group a list of data.
 * TODO: Provide a better sort algorithm which can use ASC/DESC sort orders and orders by fields instead of by one compound key.
 */

class Collection
{
    protected $result = null;
    protected $applied = false;
    protected $orderBys = array();

    private $groupings = array();
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
    public function groupBy($caption, array $fields)
    {
        $groupings = array();

        foreach ($fields as $field) {

            $groupings[] = $field;
        }
        $this->groupings[$caption] = $groupings;
        return $this;
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

        return $this->result = $groupings;
    }

    /**
     * Registers a function to use for grouping by providing a key.
     * @param $name The name to use when using groupBy.
     * @param $group The Root Entity which is accessed by the function.
     * TODO: Make this more clear, e.g. use movie.releaseDate instead of just movie
     * @param $fn The callback function which must generate and return the key for grouping. The current show is given as parameter.
     */
    public function registerGroupingFunction($name, $group, $fn) {
        $this->allowedFields[] = $name;
        if (!in_array($group, $this->allowedFields)) {
        }
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
                $key .= $this->fns[$orderBy]($data) . '-';
            } elseif (is_array($data)) {
                $key .= $data[$orderBy];
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

        throw new CinergyCommonException("Could not convert to string value: " . gettype($var) . is_object($var) ? get_class($var) : '');
    }

    /**
     * Takes a flat array as input, and groups it recursively, taking subgroups into account.
     *
     * @param $structure array $shows.
     * @param $groupArray The groups array. E.g array('first' => array('title'), 'secound' => array('date', 'time'))
     * @return ShowtimesGroup A grouped tree.
     */
    private function groupIt($structure, $groupArray)
    {
        // current grouping fields
        $groupValues = array_shift($groupArray);
        $group = new ShowtimesGroup(implode('.', $groupValues), $groupValues, ShowtimesGroup::GROUP);
        $groupings = array();
        // group the flat structure
        foreach ($structure as &$show) {
            $key = strtolower($this->createOrderKey($show, $groupValues)); // remove strtolower if you need to distinguish between upper/lowercase
            if (isset($groupings[$key])) {
                $groupings[$key][] = $show;
            } else {
                $groupings[$key] = array($show);
            }
        }

        // sort structure by key
        uksort($groupings, 'strnatcmp');
        // group entries recursively if we have grouping criteria left
        if (count($groupArray)) {
            foreach ($groupings as $key => $values) {
                $group->addChild($c = $this->groupIt($values, $groupArray, $this->container));
                $c->setKey($key);
            }
        } else {
            // No group criteria left, create leave nodes.
            foreach ($groupings as $key => $g) {
                $child = new ShowtimesGroup(implode('-',$groupValues), $groupValues, ShowtimesGroup::LEAF);
                $child->replaceChildren(array_values($g));
                $child->setKey($key);
                $child->setOrderBys($this->orderBys);
                $child->setParent($group);
                $child->setContainer($container);
                $group->addChild($child);
            }
        }

        return $group;
    }
}
