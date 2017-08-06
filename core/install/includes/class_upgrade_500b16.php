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

class vB_Upgrade_500b16 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500b16';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Beta 16';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Beta 15';

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

	/* Step #1
	 *
	 * Drop the parent index from closure as we need to extend it with more fields. This may be painful
	 */
	public function step_1()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'closure', 1, 3),
			'closure',
			'parent'
		);
	}

	/*Step #2
	 *
	 * Add more fields to the parent index on closure for the updateLastData query
	 */
	public function step_2()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'closure', 2, 3),
			'closure',
			'parent_2',
			array('parent', 'depth', 'publishdate', 'child')
		);
	}

	/*Step #3
	 *
	 * Add index on publishdate for the updateLastData query
	 */
	public function step_3()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'closure', 3, 3),
			'closure',
			'publishdate',
			array('publishdate', 'child')
		);
	}

	/** Add a message about counts */
	public function step_4()
	{
		$this->add_adminmessage('after_upgrade_from_4',
			array('dismissable' => 1,
			'status'  => 'undone',));
	}

	/*Step #5
	 *
	 * Add index on node for selecting by nodeid and ordering by contenttypeid
	 */
	public function step_5()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'node', 1, 1),
			'node',
			'nodeid',
			array('nodeid', 'contenttypeid')
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/