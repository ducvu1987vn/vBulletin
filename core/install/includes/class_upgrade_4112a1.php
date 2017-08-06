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

class vB_Upgrade_4112a1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '4112a1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.1.12 Alpha 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.1.11';

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
	 * step 2 is not needed because it creates an index on the post table, which we no longer use.
	 */

	/**
	* Adds index to the dateline field in tagsearch table for tag search improvment
	*
	*/
	function step_1()
	{
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'dateline', 'tagsearch'),
			'tagsearch',
			'dateline',
			'dateline'
		);

		$this->long_next_step();
	}

}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
