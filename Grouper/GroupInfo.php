<?php
namespace ArrayGrouper\Grouper;

/**
 * Private helper class.
 * Provides access to shared data between all grouped elements created by a collection.
 */

class GroupInfo {
	public $fns = array(); // registered functions to execute on a node.
    public $groupExtension = null; // registered extensions to execute on a noede
}