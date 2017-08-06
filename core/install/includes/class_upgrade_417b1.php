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

class vB_Upgrade_417b1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '417b1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.1.7 Beta 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.1.6';

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
	 */


	/** In general, upgrade files between 4.1.5 and 500a1 are likely to be different in vB5 from their equivalent in vB4.
	 *  Since large portions of vB4 code were removed in vB5, the upgrades to ensure that code works is unnecessary. If
	 *  there are actual errors that affect vB5, those must be included of course. If there are changes whose absence would
	 *  break a later step, those are required.
	 *
	 * But since these files will only be used to upgrade to versions after 5.0.0 alpha 1, most of the upgrade steps can be
	 * omitted. We could use skip_message(), but that takes up a redirect and, in the cli upgrade, a recursion. We would rather
	 * avoid those. So we have removed those steps,
	 * step 1- Since we no longer use the thread table there's no reason to make changes to it
	 */
	/*
	  Steps 1 & 2 - VBIV-10514 : Add last_activity index.
	*/
	function step_1() //Was step 2
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'session', 1, 4),
			'ALTER TABLE ' . TABLE_PREFIX . 'session DROP INDEX last_activity',
			'1091'
		);
	}

	function step_2() //Was step 3
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'session', 2, 4),
			'ALTER TABLE ' . TABLE_PREFIX . 'session ADD INDEX last_activity USING BTREE (lastactivity)',
			'1061'
		);
	}


	/*
	  Steps 3 & 4 - VBIV-10514 : Rebuild user_activity index as BTREE.
	*/
	function step_3()  //Was step 4
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'session', 3, 4),
			'ALTER TABLE ' . TABLE_PREFIX . 'session DROP INDEX user_activity',
			'1091'
		);
	}

	function step_4() //Was step 5
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'session', 4, 4),
			'ALTER TABLE ' . TABLE_PREFIX . 'session ADD INDEX user_activity USING BTREE (userid, lastactivity)',
			'1061'
		);
	}

	/*
	  Step 6 & 7 - VBIV-10525 : Correct clienthash index.
	*/
	function step_5() //Was step 6
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'apiclient', 1, 2),
			'ALTER TABLE ' . TABLE_PREFIX . 'apiclient DROP INDEX clienthash',
			'1091'
		);
	}

	function step_6() //Was step 7
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'apiclient', 2, 2),
			'ALTER TABLE ' . TABLE_PREFIX . 'apiclient ADD INDEX clienthash (clienthash)',
			'1061'
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
