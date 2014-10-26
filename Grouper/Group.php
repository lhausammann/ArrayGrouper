<?php
/**
 * A grouped collection.
 *
 */
namespace Grouper;
Group;


use Countable;

class ShowtimesGroup implements \Countable
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

    /** tries to get the n-th-child. n starts at 0. */
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
        $this->addAllowedGetters($fields);
        $this->type = $type;
        self::$allowedFields[$caption] = $fields; // static access for speed.
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


    public function getChildren()
    {
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
     * @return Show.
     */
    public function getNode()
    {
        if ($this->isLeaf() && ($this->children)) {

            return $this->children[0];
        } else if (($this->children)) {

            return $this->children[0]->getShow();
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
        } else {
            $leaf = $this->children[0]->getLeaf();
            return $leaf;
        }
    }

    /**
     * Gets a caption which replaces placeholders with the current group field values.
     * E.g '%title% in %city%' will replace that string by e.g. 'Gone Girl in Zürich' if we grouped by title and cities,
     * and are in a group which allows to access title / city on parent.
     * @param $stringWithPlaceholders
     */
    public function formatCaption($stringWithPlaceholders)
    {
        $replacements = $stringWithPlaceholders;
        $allFields = $this->getAllAllowedFields();
        foreach ($allFields as $field) {
            if (strpos('%' . $field . '%', $replacements) !== false) {
                $fn = 'get' . ucfirst($field);
                $value = $this->{$fn}();
                $replacements = str_replace('%' . $field . '%', $value, $replacements);
            }
        }

        return strpos($replacements, '%') !== false ? preg_replace('/%(.)++%/', '', $replacements) : $replacements;
    }

    public function __call($name, $args)
    {
        if ($this->isLeaf()) {
            // if we have the method defined here, call it. call it otherwise on the data.
            if (method_exists($this->getNode(), $name)) {
                return call_user_func_array(array($this->getNode(), $name), $args);
            }  elseif ($this->children) {

                return call_user_func_array(array($this->children[0], $name), $args);
            }
        }

        return call_user_func_array(array($this->getLeaf(true), $name), $args);
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

    public function toString($child)
    {
        $string = "";
        if ($child instanceof ShowtimesGroup) {

            $string .= $child->__toString();
            return $string . $this->toString($child->getChildren(true));
        } else {
            return $this->asString($child);
        }
    }

    /**
     * Get shows returns all shows ordered by the the group order.
     */
    public function getNodes()
    {
        // start retrieval of nodes.
        return $this->retrieveNodes();
    }

    /**
     * Returns all leaves as a flat array, order by group order.
     * @return array
     */
    protected function retrieveNodes()
    {
        $data = array();
        $children = $this->getChildren(true);
        foreach ($children as $showOrGroup) {
            if (is_scalar($showOrGroup)) {
                $data[] = $showOrGroup;

            } elseif ($showOrGroup->getId()) {
                $data[] = $showOrGroup;
            } else {
                $merge = $showOrGroup->retrieveShows();
                $data = array_merge($data, $merge);
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
            if ($orderBy == 'date') {
                $key .= $show->getStartTime()->format('Y-m-d') . '-';
            } elseif ($orderBy == 'time') {
                $key .= $show->getStartTime()->format('H:i') . '-';
            } elseif ($orderBy == 'movieTitle') {
                $key .= $show->getMovie() ? $show->getMovie()->getTitle() : $show->getTitle();

            } else {
                $method = 'get' . ucfirst($orderBy);
                $key .= $this->toString($show->$method()) . '-';
            }
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
                throw new CinergyCommonException("Could not resolve var: " . get_class($var));
            }

        } elseif (is_array($var)) {
            // stop recursive displaying array values, use * instead..
            return '[' . str_repeat('*,', count($var)) . ']';
        }

        throw new CinergyCommonException("Could not convert to string value: " . gettype($var) . is_object($var) ? get_class($var) : '');
    }

    /**
     * Returns all fields which are allowed to call.
     * @return array
     */
    private function getAllAllowedFields()
    {

        $fields = self::$allowedFields[$this->caption];
        $parent = $this;
        while (($parent = $parent->parent)) {
            $fields = array_merge($fields, self::$allowedFields[$parent->caption]);
        }

        return array_merge(self::$allowedMethods, $fields);
    }
}