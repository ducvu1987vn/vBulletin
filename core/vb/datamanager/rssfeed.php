<?php
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
* Class to do data save/delete operations for RSS Feeds
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 16:38:49 -0200 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_RSSFeed extends vB_DataManager
{
	/**
	* Array of recognised and required fields for RSS feeds, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'rssfeedid'         => array(vB_Cleaner::TYPE_UINT,			vB_DataManager_Constants::REQ_INCR, 'return ($data > 0);'),
		'title'             => array(vB_Cleaner::TYPE_STR,			vB_DataManager_Constants::REQ_YES),
		'url'               => array(vB_Cleaner::TYPE_STR,			vB_DataManager_Constants::REQ_YES),
		'ttl'               => array(vB_Cleaner::TYPE_UINT,			vB_DataManager_Constants::REQ_YES, vB_DataManager_Constants::VF_METHOD),
		'maxresults'        => array(vB_Cleaner::TYPE_UINT,			vB_DataManager_Constants::REQ_NO),
		'userid'            => array(vB_Cleaner::TYPE_UINT,			vB_DataManager_Constants::REQ_YES, vB_DataManager_Constants::VF_METHOD),
		'nodeid'           => array(vB_Cleaner::TYPE_UINT,			vB_DataManager_Constants::REQ_YES, vB_DataManager_Constants::VF_METHOD),
		'iconid'            => array(vB_Cleaner::TYPE_UINT,			vB_DataManager_Constants::REQ_NO),
		'titletemplate'     => array(vB_Cleaner::TYPE_STR,			vB_DataManager_Constants::REQ_YES),
		'bodytemplate'      => array(vB_Cleaner::TYPE_STR,			vB_DataManager_Constants::REQ_YES),
		'searchwords'       => array(vB_Cleaner::TYPE_STR,			vB_DataManager_Constants::REQ_NO),
		'itemtype'          => array(vB_Cleaner::TYPE_STR,			vB_DataManager_Constants::REQ_YES, vB_DataManager_Constants::VF_METHOD),
		'topicactiondelay' => array(vB_Cleaner::TYPE_UINT,			vB_DataManager_Constants::REQ_NO),
		'endannouncement'   => array(vB_Cleaner::TYPE_UINT,			vB_DataManager_Constants::REQ_NO),
		'searchwords'       => array(vB_Cleaner::TYPE_STR,			vB_DataManager_Constants::REQ_NO),
		'lastrun'           => array(vB_Cleaner::TYPE_UINT,			vB_DataManager_Constants::REQ_AUTO),
		'options'           => array(vB_Cleaner::TYPE_NOCLEAN,	vB_DataManager_Constants::REQ_NO),
		'prefixid'          => array(vB_Cleaner::TYPE_NOHTML,		vB_DataManager_Constants::REQ_NO)
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array('options' => 'bf_misc_feedoptions');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'vBForum:rssfeed';

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('rssfeedid = %1$d', 'rssfeedid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_RSSFeed(&$registry, $errtype = vB_DataManager_Constants::ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		// Legacy Hook 'rssfeeddata_start' Removed //
	}

	/**
	* Verifies that the specified forumid is valid
	*
	* @param	integer	Forum ID (allow -1 = all forums)
	*
	* @return	boolean
	*/
	function verify_nodeid(&$forumid)
	{
		try
		{
			$node = vB_Api::instanceInternal('node')->getNodeContent($forumid);
			$node = $node[$forumid];
		}
		catch (vB_Exception_Api $ex)
		{
			return false;
		}

		if ($forumid != -1 AND empty($node))
		{
			$this->error('invalid_forum_specified');
			return false;
		}

		if (empty($node['options']['cancontainthreads']))
		{
			$this->error('forum_is_a_category_allow_posting');
			return false;
		}

		return true;
	}

	/**
	* Accepts a username and converts it into the appropriate user id
	*
	* @param	string	Username
	*
	* @return	boolean
	*/
	function set_user_by_name($username)
	{
		//if ($username != '' AND $user = $this->dbobject->query_first("SELECT userid, username FROM " . TABLE_PREFIX . "user WHERE username = '" . $this->dbobject->escape_string($username) . "'"))
		if ($username != '' AND $user = $this->assertor->getRow('user', array('username' => $username)))
		{
			$this->do_set('userid', $user['userid']);
			return true;
		}
		else
		{
			$this->error('invalid_user_specified');
			return false;
		}
	}

	/**
	* Verifies that a user id is valid and exists
	*
	* @param	integer	User ID
	*
	* @return	boolean
	*/
	function verify_userid(&$userid)
	{
		//if ($userid AND $user = $this->dbobject->query_first("SELECT userid, username FROM " . TABLE_PREFIX . "user WHERE userid = " . intval($userid)))
		if ($userid AND $user = $this->assertor->getRow('user', array('userid' => intval($userid))))
		{
			return true;
		}
		else
		{
			$this->error('invalid_user_specified');
			return false;
		}
	}

	/**
	* Ensures that the given TTL (time to live) value is sane
	*
	* @param	integer	TTL in seconds
	*
	* @return	boolean
	*/
	function verify_ttl(&$ttl)
	{
		switch ($ttl)
		{
			case 43200: // every 12 hours
			case 36000: // every 10 hours
			case 28800: // every 8 hours
			case 21600: // every 6 hours
			case 14400: // every 4 hours
			case 7200: // every 2 hours
			case 3600: // every hour
			case 1800: // every half-hour
			case 1200: // every 20 minutes
			case 600: // every 10 minutes
				return true;

			default:
				$ttl = 1800;
				return true;
		}
	}

	/**
	* Ensures that the given itemtype is acceptable
	*
	* @param	string	Item Type
	*
	* @return	boolean
	*/
	function verify_itemtype(&$itemtype)
	{
		switch ($itemtype)
		{
			case 'topic':
			case 'announcement':
				return true;

			default:
				$itemtype = 'thread';
				return true;
		}
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

		$return_value = true;
		// Legacy Hook 'rssfeeddata_presave' Removed //

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
		// Legacy Hook 'rssfeeddata_postsave' Removed //

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$this->db_delete(TABLE_PREFIX, 'rsslog', 'rssfeedid = ' . $this->existing['rssfeedid']);
		// Legacy Hook 'rssfeeddata_delete' Removed //
		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/

