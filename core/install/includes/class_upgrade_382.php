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
class vB_Upgrade_382 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '382';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.8.2';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.8.1';

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
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'subscribegroup', 1, 1),
			'subscribegroup',
			'emailupdate',
			'enum',
			array('attributes' => "('daily', 'weekly', 'none')", 'null' => false, 'default' => 'none')
		);
	}
}
/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 13568 $
|| ####################################################################
\*======================================================================*/
?>
