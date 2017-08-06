<?php
/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 5.0.0
  || # ---------------------------------------------------------------- # ||
  || # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

/**
* Class to handle shutdown
*
* @package	vBulletin
* @version	$Revision: 43471 $
* @author	vBulletin Development Team
* @date		$Date: 2011-05-12 14:10:01 -0300 (Thu, 12 May 2011) $
*/
class vB_Shutdown
{
	/**
	 * A reference to the singleton instance
	 *
	 * @var vB_Cache_Observer
	 */
	protected static $instance;

	/**
	 * An array of shutdown callbacks to call on shutdown
	 */
	protected $callbacks;

	protected $called = false;
	/**
	 * Constructor protected to enforce singleton use.
	 * @see instance()
	 */
	protected function __construct(){}

	/**
	 * Returns singleton instance of self.
	 *
	 * @return vB_Shutdown
	 */
	public static function instance()
	{
		if (!isset(self::$instance))
		{
			$class = __CLASS__;
			self::$instance = new $class();
		}

		return self::$instance;
	}

	/**
	* Add callback to be executed at shutdown
	*
	* @param array $callback					- Call back to call on shutdown
	*/
	public function add($callback)
	{
		if (!is_array($this->callbacks))
		{
			$this->callbacks = array();
		}

		$this->callbacks[] = $callback;
	}

	// only called when an object is destroyed, so $this is appropriate
	public function shutdown()
	{
		if ($this->called)
		{
			return; // Already called once.
		}

		$session = vB::getCurrentSession();
		if (is_object($session))
		{
			$session->save();
		}

		if (sizeof($this->callbacks))
		{
			foreach ($this->callbacks AS $callback)
			{
				call_user_func($callback);
			}

			unset($this->callbacks);
		}

		$this->setCalled();
	}

	public function __wakeup()
	{
		unset($this->callbacks);
	}

	public function setCalled()
	{
		$this->called = true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
