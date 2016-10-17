<?php
namespace ArrayGrouper\Grouper;
use Symfony\Component\EventDispatcher\EventDispatcher;
/**
 * Private helper class.
 * The only reason for this class to exist is to share functions and extensions accross all groups.
 *  For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license ../LICENSE.md
 * MIT -  For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


class GroupInfo {
	public $fns = array(); // registered functions to execute on a node.
    public $groupExtension = null; // registered extensions to execute on a node
    public $dispatcher = null;

    /** events of the form:
     * groupName => groupCaption
     */
    public $events = array();

    public function __construct() {
    	$this->dispatcher = new EventDispatcher();
    }
}