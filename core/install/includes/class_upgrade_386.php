<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions, Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
class vB_Upgrade_386 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '386';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.8.6';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.8.5';

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
	* Step #1
	*
	*/
	function step_1()
	{
		// Update Admins and Super Mods to have the "canignorequota" perm in case they don't have it...
		$canignorequotaperm = intval($vbulletin->bf_ugp_pmpermissions['canignorequota']);
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 1),
			"UPDATE " . TABLE_PREFIX . "usergroup
			SET pmpermissions = pmpermissions + $canignorequotaperm
			WHERE usergroupid IN (5, 6) AND NOT (pmpermissions & $canignorequotaperm)
		");
	}
}
/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 13568 $
|| ####################################################################
\*======================================================================*/
?>
