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
/*
if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}
*/

class vB_Upgrade_500a23 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a23';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 23';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 22';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '';

	// Change user's moderatefollowers option enabled by default
	function step_1()
	{
		$useroptions = vB::getDatastore()->getValue('bf_misc_useroptions');

		if (isset($useroptions['moderatefollowers']))
		{
			$moderatefollowers = $useroptions['moderatefollowers'];
		}
		else
		{
			$moderatefollowers = 67108864;
		}

		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'user'),
			"UPDATE " . TABLE_PREFIX . "user
			SET options = options | " . $moderatefollowers);
	}

	// Add moderatefollowers to defaultregoptions
	function step_2()
	{
		$regoptions = vB::getDatastore()->getValue('bf_misc_regoptions');

		if (isset($regoptions['moderatefollowers']))
		{
			$moderatefollowers = $regoptions['moderatefollowers'];
		}
		else
		{
			$moderatefollowers = 134217728;
		}

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'setting'),
			"UPDATE " . TABLE_PREFIX . "setting SET
			value = value | " . $moderatefollowers . "
			WHERE varname = 'defaultregoptions'"
		);
	}

	/** Adding styleid field for channels **/
	public function step_3()
	{
		$this->skip_message();
	}

	/** modifying default value for options field in channel **/
	public function step_4()
	{
		$this->skip_message();
	}

	/** migrating forum styleid and options **/
	public function step_5()
	{
		if ($this->tableExists('forum'))
		{
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'channel'),
				'UPDATE ' . TABLE_PREFIX . 'channel c
				INNER JOIN ' . TABLE_PREFIX . 'node n ON n.nodeid = c.nodeid
				INNER JOIN ' . TABLE_PREFIX . 'forum f ON f.forumid = n.oldid
				SET c.styleid = f.styleid, c.options = f.options');
		}
		else
		{
			$this->skip_message();
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/