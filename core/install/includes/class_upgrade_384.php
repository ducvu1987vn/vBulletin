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
class vB_Upgrade_384 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '384';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.8.4';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.8.3';

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
			sprintf($this->phrase['core']['altering_x_table'], 'thread', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "thread CHANGE lastposter lastposter VARCHAR(100) NOT NULL default ''"
		);
	}
}
/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 13568 $
|| ####################################################################
\*======================================================================*/
?>
