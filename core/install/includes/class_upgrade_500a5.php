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

class vB_Upgrade_500a5 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a5';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 5';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 4';

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

	function step_1()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'routenew'),
			"
			INSERT IGNORE INTO `" . TABLE_PREFIX . "routenew`
			(`name`, `prefix`, `regex`, `class`, `controller`, `action`, `template`, `arguments`, `contentid`)
			VALUES
			('admincp', 'admincp', 'admincp/(?P<file>[a-zA-Z0-9_.-]*)', 'vB5_Route_Admincp', 'relay', 'admincp', '', 'a:1:{s:4:\"file\";s:5:\"\$file\";}', 0)
			"
		);
	}

	function step_2()
	{
		$route = $this->db->query_first("SELECT routeid FROM " . TABLE_PREFIX . "routenew WHERE name = 'profile'");
		$page = $this->db->query_first("SELECT pageid FROM " . TABLE_PREFIX . "page WHERE routeid = " . $route['routeid']);

		$query = "UPDATE " . TABLE_PREFIX . "page SET
			pagetype = '" . vB_Page::TYPE_CUSTOM . "'
			WHERE pageid = " . $page['pageid'];
		$this->run_query(
		sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'routenew'),$query);

		$query = "UPDATE " . TABLE_PREFIX . "routenew SET
			class = 'vB5_Route_Profile',
			prefix = '" . vB5_Route_Profile::DEFAULT_PREFIX . "',
			regex = '" . vB5_Route_Profile::DEFAULT_PREFIX . '/' . vB5_Route_Profile::REGEXP . "',
			arguments = '" . serialize(array('userid'=>'$userid', 'pageid'=>$page['pageid'])) . "'
			WHERE routeid = " . $route['routeid'];
		$this->run_query(
		sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'routenew'),$query);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
