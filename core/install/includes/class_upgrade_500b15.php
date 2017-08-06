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

class vB_Upgrade_500b15 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500b15';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Beta 15';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Beta 14';

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
	 * Set systemgroupid for those groups
	 * Needed here due beta maintenance, we don't want to rerun old upgraders for this
	 */
	function step_1()
	{
		if ($this->field_exists('usergroup', 'systemgroupid'))
		{
			vB::getDbAssertor()->assertQuery('vBInstall:alterSystemgroupidField');
		}

		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'usergroup'));
		vB::getDbAssertor()->assertQuery('vBInstall:setDefaultUsergroups', array());
	}

	/**
	 * Set banned group as custom
	 */
	function step_2()
	{
		$this->show_message($this->phrase['version']['500b15']['setting_banned_ugp']);
		$ugpOptions = vB::getDatastore()->getValue('bf_ugp_genericoptions');
		vB::getDbAssertor()->assertQuery('vBInstall:setUgpAsDefault',
			array('ugpid' => 8, 'bf_value' => $ugpOptions['isnotbannedgroup'])
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/