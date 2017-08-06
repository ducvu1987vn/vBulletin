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

class vB_Upgrade_418b1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '418b1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.1.8 Beta 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.1.7';

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
	/** In general, upgrade files between 4.1.5 and 500a1 are likely to be different in vB5 from their equivalent in vB4.
	 *  Since large portions of vB4 code were removed in vB5, the upgrades to ensure that code works is unnecessary. If
	 *  there are actual errors that affect vB5, those must be included of course. If there are changes whose absence would
	 *  break a later step, those are required.
	 *
	 * But since these files will only be used to upgrade to versions after 5.0.0 alpha 1, most of the upgrade steps can be
	 * omitted.
	 *
	 * Steps 3 & 4- not needed. Both were for the mobile style, which doesnt exist in vb5, these particular changes were superceeded as well.
	 * 	 *
	 */

	/*
	  Step 1 - VBIV-6641 : Add cache index for expires.
	*/
	function step_1()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'cache', 1, 1),
			'cache',
			'expires',
			array('expires')
		);
	}

	/*
	  Step 2 - VBIV-6641 : Clean out expired events in cache and cacheevent tables.
	*/
	function step_2()
	{
		$this->run_query(
			sprintf($this->phrase['core']['cache_update']),
				'DELETE cache, cacheevent FROM ' . TABLE_PREFIX . 'cache as cache
				INNER JOIN ' . TABLE_PREFIX . 'cacheevent as cacheevent USING (cacheid)
				WHERE expires BETWEEN 1 and ' . TIMENOW
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
