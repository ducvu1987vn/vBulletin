<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Blog 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

require_once(DIR . '/includes/class_dm_deletionlog.php');

/**
* Class to do data save/delete operations for Blog/Blogtext
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 16:38:49 -0200 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_DeletionLog_Blog extends vB_DataManager_DeletionLog
{
	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'blog_deletionlog';

	/**
	* Valid types for 'type'. If type is unset, the first element of this array will be used
	* @var	array
	*
	*/
	var $types = array('blog', 'blogtext', 'usercommentid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Deletionlog_Blog(&$registry, $errtype = vB_DataManager_Constants::ERRTYPE_STANDARD)
	{
		parent::vB_DataManager_Deletionlog($registry, $errtype);

		// Legacy Hook 'blog_deletionlogdata_start' Removed //
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->fetch_field('dateline') AND !$this->condition)
		{
			$this->set('dateline', TIMENOW);
		}

		$return_value = true;
		// Legacy Hook 'blog_deletionlogdata_presave' Removed //

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		// Legacy Hook 'blog_deletionlogdata_postsave' Removed //
		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		// Legacy Hook 'blog_deletionlogdata_delete' Removed //
		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision: 32878 $
|| ####################################################################
\*======================================================================*/

