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

class vB_Upgrade_500b20 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500b20';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Beta 20';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Beta 19';

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
	
	/**
	 * Step 1
	 */
	public function step_1()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'widget', 1, 1),
				'widget',
				'canbemultiple',
				'TINYINT',
				array('length' => 3, 'default' => '1')
		);
	}
	
	/** Add threadcomment notification types to about in privatemessage */
	public function step_2()
	{
		if ($this->field_exists('privatemessage', 'about'))
		{
			$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'privatemessage', 1, 1),
				"ALTER TABLE " . TABLE_PREFIX . "privatemessage MODIFY COLUMN about ENUM('vote','vote_reply','rate','reply',
				'follow','vm','comment','threadcomment','owner_to','moderator_to','owner_from','moderator','member', 'member_to', 'subscriber', 'subscriber_to', 'sg_subscriber', 'sg_subscriber_to')"
			);
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
