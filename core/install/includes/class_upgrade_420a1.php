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

class vB_Upgrade_420a1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '420a1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.2.0 Alpha 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.1.12';

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
	 * omitted. We could use skip_message(), but that takes up a redirect and, in the cli upgrade, a recursion. We would rather
	 * avoid those. So we have removed those steps,
	 * step 1 in the original is not needed because we don't use the navigation table in vB5
	 * step 2- Not needed, this was part of the changes for sending mail by vb cron, this functionality wont currently exist in vb5
	 * Step 3- We don't use the user.newrepcount field in vB5.
	 * Step 4- we never query anything sorted by user.lastactivity or join on it.
	 * Step 5- we don't use the contentread table in vB5
	 * Step 6- we don't use the ipdate table in vB5
	 * Step 7- Not needed, all products are zapped by vB5 anyway.
	 * Step 8- We don't use the block table
	 * Step 9- Not needed, this was for double post prevention added in 4.2, it doesnt exist in vb5.
	 * Step 10- We don't use the forum table
	 * Step 12- We don't use the activitystreamtype table
	 *  Step 13, 14 - We don't use the activitystream table. We have a similar concept in vB5 but handled differently
	 *  Step 15, 16- Since we don't use activitystream we don't need the phrase group or type
	 *  Step 17, 18, 19- We don't use the picturecomment table. The hierarchy is handled completely differently in vB5
	 *  Step 20- We don't use the thread table
	 *  Step 21- We don't use activitystream approach
	 *  Step 22- this inserts a cron job to keep the activitystream up to date- but we don't use that approach.
	 *
	 * So we have some use for step 11
	 */

	/*
	  Step 1 - Add Index to Upgrade Log
	*/
	function step_1() //Was Step 11
	{
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'script', 'upgradelog'),
			'upgradelog',
			'script',
			'script'
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
