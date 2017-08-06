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
class vB_Upgrade_381 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '381';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.8.1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.8.0';

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
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 1, 4),
			"ALTER TABLE " . TABLE_PREFIX . "reputation CHANGE postid postid INT UNSIGNED NOT NULL DEFAULT '1'"
		);
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 2, 4),
			"ALTER TABLE " . TABLE_PREFIX . "reputation CHANGE userid userid INT UNSIGNED NOT NULL DEFAULT '1'"
		);
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 3, 4),
			"ALTER TABLE " . TABLE_PREFIX . "reputation CHANGE whoadded whoadded INT UNSIGNED NOT NULL DEFAULT '0'"
		);
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 4, 4),
			"ALTER TABLE " . TABLE_PREFIX . "reputation CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0'"
		);
	}
}
/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 13568 $
|| ####################################################################
\*======================================================================*/
?>
