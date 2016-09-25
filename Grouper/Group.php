<?php
/**
 * A group reperesents a node which can be grouped.
 * Every group has children. If its a Group node, the child nodes are of the same type. If its a leave node, its children are an array * of the initial raw data (scalar, arrays or objects).
 * Each group node provides methods to access its (first) raw data by either function calls or array access interface.
 * 
 * TODOs
 *   - Rename nodes to 'Elements'
 */

namespace ArrayGrouper\Grouper;

use \ArrayGrouper\Exception\GroupingException;


class Group implements \Countable, \ArrayAccess
{
    const GROUP = 2;
    const LEAF = 3;

    protected $orderBys = array();

    private $type;
    private $children = array();
    private $caption = '';
    private $groupingFields = array(); // grouping fields contains allwowed fields to group (parent fieilds merged with current fields).

    private $groupInfo;
    //private static $fns = array();
    //private static $groupExtension = null;
    //private static $groupings = null;

    /** set the groupings. Only apply must use this */
    public function setGroups($groups) {
        $this->groupInfo->groupings = $groups;

        return $this; // chainable
    }

    /**
     * @param $caption The name of this group
     * @param $fields
     * @param $what self::ROOT | self::GROUP | self::LEAF
     * @param $data to set data direclty on this node. only on leafs.
     */
    public function __construct($caption, $type = self::GROUP, array $data = null, $groupInfo)
    {
        $this->caption = $caption;
        $this->children = $data;
        $this->type = $type;
        $this->groupInfo = $groupInfo;
    }

    public function isGroup()
    {
        return $this->type != self::LEAF;
    }

    public function isLeaf()
    {
        return $this->type == self::LEAF;
    }

    public function getCaption()
    {
        return $this->caption;
    }

    public function addChild($child, $key = '')
    {
        $this->children[] = $child;
        $child->key = $key;
        return $child;
    }

    /**
     * Gets the children of this group.
     * @param bool $internal wether or not its a call for internal use when constructing the group. Because we do not want
     * to set the the whole EntityContainer on each internal call.
     *
     * @return array|EntityContainer
     */
    public function getChildren()
    {

        return $this->children;
    }

    public function count($rec = true)
    {
        if ($this->type === self::LEAF || $rec == false) {

            return count($this->children);
        }

        $total = 0;
        foreach ($this->children as $group) {
            $total += $group->count();
        }

        return $total;
    }

    /**
     * getNode returns the _first_ element node of a group
     * @return data.
     */
    public function getNode()
    {
        if ($this->type === self::LEAF && $this->children) {

            return $this->children[0];
        } else {
            return $this->children[0]->getNode();
        }
    }

    /**
     * Gets the leaf of a group.
     * A leaf node contains the sorted data on its children property and contains the data.
     * @return Group
     */
    public function getLeaf()
    {
        if ($this->type === self::LEAF) {
            return $this;
        } else {
            return $this->children[0]->getLeaf();
        }
    }

    /**
     * Gets a caption which replaces placeholders with the current group field values.
     * E.g '%title% in %city%' will replace that string by e.g. 'Gone Girl in Zürich' if we grouped by title and cities,
     * @param $stringWithPlaceholders
     */
    public function formatCaption($stringWithPlaceholders, $node = null)
    {
        $node = $node ?: $this->getNode();
        $replacements = $stringWithPlaceholders;
        $pattern = '/%([a-z]++)%/i';
        $hasFn = count($this->groupInfo->fns);
        $matches = array();
        preg_match_all($pattern, $stringWithPlaceholders, $matches);
        foreach ($matches[0] as $i => $field) {
            $value = $this->getField($node, $matches[1][$i]);
            $replacements = str_replace($field, $value, $replacements);
        }

        return strpos($replacements, '%') !== false ? preg_replace('/%()++%/', '', $replacements) : $replacements;
    }

    public function registerFunctions($fns)
    {
        $this->groupInfo->fns = $fns;
    }

    public function registerExtension($extension)
    {
        $this->groupInfo->groupExtension = $extension;
    }


    /**
     * Prooagate call of a group to
     *  - registered group extensions
     *  - registered group functions
     *  - methods / fields on the first leaf array in the tree (assuming we call a grouped field)  
     */
    public function __call($name, $args)
    {

        // its a registered node extension then call it.
        if ($this->groupInfo->groupExtension && method_exists($this->groupInfo->groupExtension, $name)) {
            $argument = count($args) === 1 ? $args[0] : false;
            return  $this->groupInfo->groupExtension->{$name}($this, $argument);
        // its a registered function on the node. call it with the raw data (getNode()) as first argument.
        } elseif (isset($this->groupInfo->fns[$name]))
        {
            return call_user_func_array($this->groupInfo->fns[$name], array_merge(array($this->getNode(), $args)));

        } elseif ($this->type === self::LEAF) {
            if (method_exists($this->getNode(), $name)) {
                return call_user_func_array(array($this->getNode(), $name), $args);
            } else {

                throw new GroupingException(sprintf("Call to %s() failed (using __call): No registered extension function, field function, object method found.", $name));
            }
        }

        return $this->getLeaf()->$name($args); // recurse to __call again.
    }


    public function __toString()
    {
        $string = '{Group: '; 
        if ($this->children) {
            foreach ($this->children as $child) {
                $string .= $this->asString($child, true);
            }

            $string .= '}';
        }

        return $string;
    }

    /**
     * Returns the value of this field.
     * @param  scalar $field optional If  field is false, the current array object or plain value is given back. If a field is set, the current field from the object is returned.
     * @param  Group $node optional  the node on which the field should be retrieved from. If none is given, the first node from the group is taken.
     */
    public function getValue($field = false, $node = null)
    {

        $node = $node !== null ? $node : $this->getNode();
        return $this->getField($node, $field);
    }

    /**
     * Get all elements (raw data) from the group. If a group contains subgroups, all data of each subgroup are collected and returned.
     */
    public function getElements()
    {
        // start it.
        $a = array();
        return $this->type === self::LEAF ? $this->children : $this->retrieveElements($a);
    }

    

    /**
     * Returns all nodes as a flat array, order by group order.
     * @return array
     */
    protected function retrieveElements($data)
    {            
        $children = $this->children;
        foreach ($children as $child) {
            if ($child->isGroup()) {
                $data = $child->retrieveElements($data);
            } else { // leaf -- get the raw data.

                $data = array_merge($data, $child->children);
            }
        }

        return $data;
    }

    protected function asString($var)
    {
        if (is_scalar($var)) {
            return 's:' . $var;
        } elseif (is_object($var)) {
            
            //throw new GroupingException("Could not resolve var: " . get_class($var));
            if (method_exists($var, '__toString')) 
                return $var->__toString();
            return "class: " . get_class($var);
            
        } elseif (is_array($var)) {

           $r = 'arr: ';
            foreach ($var as $k => $v) {
               $r = $r . ' ' . $k . ": " . $v;
           }
           return '[' .  $r . ']';
        }
    }

    /**
     * Tries to get the 'field' from a group or a raw element.
     * If a group has registered functions on it, it accesses them as well.
     * @param $mixed  the value to check against. Could be scalar, array or object.
     * @param $field  the field to get from the value (if scalar, this must not be set.)
     * @return mixed  the value of the field which is accessed from $mixed.
     */
    private function getField($mixed, $field)
    {
        if ($field === false && is_scalar($mixed)) {
            return $mixed;
        } elseif (is_array($mixed) && isset($mixed[$field])) {
            return $mixed[$field];
        } elseif(isset($this->groupInfo->fns[$field])) {
            $call = $this->groupInfo->fns[$field];
            return $call($mixed);
        } elseif (is_object($mixed) && method_exists($mixed, $getter = 'get' . ucfirst($field))) {
            
            return $mixed->{$getter}();
        } else {
            throw new GroupingException(sprintf('Object of type %s must be array or object or field %s must exists as extension function', gettype($mixed), $field));
        }
    }


    /* -- Inherited functions from ArrayAccess -- */
    public function offsetSet($offstet, $value) {throw new GroupingException("Collection is read-only");}
    public function offsetUnset($offstet) {throw new GroupingException("Collection is read-only");}

    // FIXME
    public function offsetExists($offset) {
        // offset must exist in the group. Note that this checking is not strict because it doesn not the level take into account, but probably good enough.
        $ok = true;
        foreach($this->groupInfo->groupings as $g) {
            $ok =  ($ok || in_array($offset, $g));
        }
        if (! $ok) {
            throw new \Exception("key: " . $offset . ' must exist in ' .implode(self::$groupings, ","));
        }

        return true;
    }

    public function offsetGet($offset) {
        if ($this->offsetExists($offset)) {

            return $this->getField($this->getNode(), $offset);
        }
        return null;
    }
}
