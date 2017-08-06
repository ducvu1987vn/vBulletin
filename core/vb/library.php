<?php
if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
   || #################################################################### ||
   || # vBulletin 5.0.0
   || # ---------------------------------------------------------------- # ||
   || # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
   || # This file may not be redistributed in whole or significant part. # ||
   || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
   || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
   || #################################################################### ||
   \*======================================================================*/


/**
 * vB_Library
 *
 * @package vBForum
 * @access public
 */
class vB_Library
{
	protected static $instance = array();

	protected function __construct()
	{

	}

	/**
	 * Returns singleton instance of self.
	 *
	 * @return vB_PageCache		- Reference to singleton instance of the cache handler
	 */
	public static function instance($class)
	{

		$className = 'vB_Library_' . $class;
		if (!isset(self::$instance[$className]))
		{
			self::$instance[$className] = new $className();
		}

		return self::$instance[$className];
	}

	public static function getContentInstance($contenttypeid)
	{
		$contentType = vB_Types::instance()->getContentClassFromId($contenttypeid);
		$className = 'Content_' . $contentType['class'];

		return self::instance($className);
	}

	public static function clearCache()
	{
		self::$instance = array();
	}
}

