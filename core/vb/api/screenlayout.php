<?php if (!defined('VB_ENTRY')) die('Access denied.');
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
 * vB_Api_ScreenLayout
 *
 * @package vBApi
 * @access public
 */
class vB_Api_ScreenLayout extends vB_Api
{
	/*
	 * Cache for screen layouts
	 */
	var $cache = null;

	protected function __construct()
	{
		parent::__construct();
	}

	/**
	 * Returns a list of all screen layouts.
	 *
	 * @param	bool	Force reload
	 * @return	array
	 */
	public function fetchScreenLayoutList($skipcache = false)
	{
		if (!is_array($this->cache) OR $skipcache)
		{
			$db = vB::getDbAssertor();
			$screenLayouts = $db->getRows('screenlayout', array(), array('displayorder', 'title'));

			if ($screenLayouts)
			{
				$this->cache = $screenLayouts;
			}
			else
			{
				$this->cache = array();
			}
		}

		return $this->cache;
	}

}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
