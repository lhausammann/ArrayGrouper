<?php
namespace ArrayGrouper\Grouper;

/**
 * Provides access to shared data between all groups of a collection.
 */

class GroupInfo {
	public $fns = array(); // registered functions to execute on a node.
    public $groupExtension = null; // registered extensions to execute on a noede
    public $groupings = null; // group data.
}