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

class vB_Upgrade_500a37 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a37';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 37';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 36';

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

	/** fix screen layouts
	 *
	 */
	function step_1()
	{
		$screenLayOutRecords = $this->db->query_first("
			SELECT template 
			FROM " . TABLE_PREFIX . "screenlayout
			WHERE screenlayoutid = 1
		");

		if ($screenLayOutRecords['template'] == 'sb_screenlayout_1')
		{
			$this->db->query_write("
				TRUNCATE " . TABLE_PREFIX . "screenlayout
			");

			$this->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "screenlayout
				(screenlayoutid, varname, title, displayorder, columncount, template, admintemplate)
				VALUES
				(1, '100', '100', 4, 1, 'screenlayout_1', 'admin_screenlayout_1'),
				(2, '70-30', '70/30', 1, 2, 'screenlayout_2', 'admin_screenlayout_2'),
				(4, '30-70', '30/70', 3, 2, 'screenlayout_4', 'admin_screenlayout_4')
			");

			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], 'screenlayout'));

			$this->db->query_write("
				UPDATE " . TABLE_PREFIX . "widget 
				SET template = SUBSTR(template,4)
				WHERE template LIKE 'sb_%'
			");

			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], 'widget'));
		}
		else
		{
			$this->skip_message();
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
