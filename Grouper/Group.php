<?php
/**
 * A grouped collection.
 * TODOs
 *   - Rename nodes to 'Elements'
 */
namespace ArrayGrouper\Grouper;

use ArrayGrouper\Exception\GroupingException;
use Countable;

class Group implements \Countable
{
    const ROOT = 1;
    const GROUP = 2;
    const LEAF = 3;

    protected $orderBys = array();

    private $type;
    private $children = array();
    private $sorted = false;
    private $caption = '';
    private $key;
    private $parent;
    private static $fns = array();

    /** tries to get the nth-child. n starts at 0. */
    public function get($n)
    {
        $count = 0;
        if (count($this->children) - 1 < $n) {
            return null;
        }

        foreach ($this->children as $child) {
            if ($count == $n) {
                return $child;
            }

            $count++;
        }
    }

    /**
     * @param $caption
     * @param $fields
     * @param $what self::ROOT | self::GROUP | self::LEAF
     */
    public function __construct($caption, $fields, $type = self::GROUP)
    {
        $this->caption = $caption;
        $this->type = $type;
    }

    public function isRoot()
    {
        return $this->type == self::ROOT;
    }

    public function isGroup()
    {
        return $this->type == self::GROUP;
    }

    public function isLeaf()
    {
        return $this->type == self::LEAF;
    }

    public function orderBy()
    {
        if ($this->sorted) {
            return;
        }

        $this->sorted = true;
        $sort = array();
        foreach ($this->children as $child) {
            $key = $this->createOrderKey($child, $this->orderBys);
            $sort[$key] = $child;
        }

        uksort($sort, 'strnatcmp');
        $this->children = array_values($sort);
    }

    public function setParent($parent)
    {
        $this->parent = $parent;
    }

    public function getCaption()
    {
        return $this->caption;
    }

    public function replaceChildren(array $children)
    {

        $this->children = $children;
    }

    public function addChild($child)
    {
        $child->parent = $this;
        $this->children[] = $child;
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function setOrderBys($orderBys)
    {
        $this->orderBys = array_merge($this->orderBys, $orderBys);
    }

    /**
     * Gets the children of this group.
     * @param bool $internal wether or not its a call for internal use when constructing the group. Because we do not want
     * to set the the whole EntityContainer on each internal call.
     *
     * @return array|EntityContainer
     */
    public function getChildren($internal = false)
    {
        if (!$this->orderBys) {
        } else {
            $this->orderBy($this->orderBys, null);
            $this->orderBys = null;
        }

        return $this->children;
    }


    public function count()
    {
        if ($this->isLeaf()) {

            return count($this->children);
        }

        $total = 0;
        foreach ($this->children as &$group) {
            $total += $group->count();
        }

        return $total;
    }

    /**
     * getNode returns the _first_ node of a group, allowing querying group properties from it.
     * E.g if the group is grouped by title, group->getTitle() is allowed.
     * @return data.
     */
    public function getNode()
    {
        if ($this->isLeaf() && ($this->children)) {

            return $this->children[0];
        } else if (($this->children)) {

            return $this->children[0]->getNode();
        }

        return null;
    }

    /**
     * Gets the leaf of a group.
     * A leaf node contains the sorted data.
     * @return ShowtimesGroup
     */
    public function getLeaf()
    {
        if ($this->isLeaf()) {
            return $this;
        } else if ($this->children){
            $leaf = $this->children[0]->getLeaf();
            return $leaf;
        }
    }

    /**
     * Gets a caption which replaces placeholders with the current group field values.
     * E.g '%title% in %city%' will replace that string by e.g. 'Gone Girl in Zürich' if we grouped by title and cities,
     * @param $stringWithPlaceholders
     */
    public function formatCaption($stringWithPlaceholders)
    {
        $replacements = $stringWithPlaceholders;
        $pattern = '/%([a-z]++)%/i';
        $hasFn = count(self::$fns);
        $matches = array();
        preg_match_all($pattern, $stringWithPlaceholders, $matches);
        foreach ($matches[0] as $i => $field) {
            $node = $this->getNode();
            $value = $this->getField($node, $matches[1][$i]);
            $replacements = str_replace($field, $value, $replacements);
        }

        return strpos($replacements, '%') !== false ? preg_replace('/%()++%/', '', $replacements) : $replacements;
    }

    public function registerFunctions($fns)
    {
        self::$fns = $fns;
    }

    public function __call($name, $args)
    {
        if (isset(self::$fns[$name]))
        {
            return call_user_func_array(self::$fns[$name], array_merge(array($this->getNode(), $args)));
        }

        if ($this->isLeaf()) {
            if (method_exists($this->getNode(), $name)) {
                return call_user_func_array(array($this->getNode(), $name), $args);
            }  elseif ($this->children) {
                // call it on the first child.
                return call_user_func_array(array($this->children[0], $name), $args);
            }
        }
        $leaf = $this->getLeaf();

        return call_user_func_array(array($leaf, $name), $args);
    }

    public function __toString()
    {
        $string = 'Group ' . $this->caption;
        if ($this->children) {
            $string .= '[';
            foreach ($this->children as $child) {
                $string .= $this->toString($child);
            }

            $string .= ']';
        }

        return $string;
    }

    /**
     * Returns the value of this field.
     * @param $field
     */
    public function getValue($field)
    {
        return $this->getField($this->getNode(), $field);
    }


    public function toString($child)
    {
        $string = "";
        if ($child instanceof Group) {

            $string .= $child->__toString();
            return $string . $this->toString($child->getChildren(true));
        } else {
            return $this->asString($child);
        }
    }

    /**
     * Get shows returns all data ordered by the the group order.
     */
    public function getNodes()
    {
        // start it.
        return $this->retrieveNodes();
    }

    /**
     * Returns all nodes as a flat array, order by group order.
     * @return array
     */
    protected function retrieveNodes()
    {
        $data = array();
        $children = $this->getChildren(true);
        foreach ($children as $showOrGroup) {
            if (is_scalar($showOrGroup)) {
                $data[] = $showOrGroup;
            } elseif (is_object($showOrGroup) && get_class($showOrGroup) === 'Group') {
                $merge = $showOrGroup->retrieveNodes();
                $data = array_merge($data, $merge); // merge nodes
            } else {
                $data[] = $showOrGroup;
            }
        }

        return $data;
    }

    /**
     * Creates a key which is used for ordering and grouping all results.
     * @param $show
     * @param $keys
     * @return string
     */
    protected function createOrderKey($show, $keys)
    {
        $key = '';
        foreach ($keys as $orderBy) {
            $method =$this->getField($orderBy);
            $key .= $this->toString($show->$method()) . '-';
        }

        return $key;
    }

    protected function asString($var)
    {
        if (!$var) {

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
                throw new GroupingException("Could not resolve var: " . get_class($var));
            }
        } elseif (is_array($var)) {
            // stop recursive displaying array values, use * instead..
            return '[' . str_repeat('*,', count($var)) . ']';
        }

        throw new GroupingException("Could not convert to string value: " . gettype($var) . is_object($var) ? get_class($var) : '');
    }

    /**
     * Tries to get the 'field' from a group or a raw element.
     * If a group has registered functions on it, it accesses them as well.
     * @param $ojbOrArr
     * @param $field
     * @return mixed
     */
    private function getField($ojbOrArr, $field)
    {
        if (is_object($ojbOrArr)) {
            $getter = 'get' . ucfirst($field);
            return $ojbOrArr->{$getter}();
        } elseif (is_array($ojbOrArr) && isset($ojbOrArr[$field])) {
            return $ojbOrArr[$field];
        } elseif(isset(self::$fns[$field])) {
            $call = self::$fns[$field];
            return $call($ojbOrArr);
        }
    }
}
