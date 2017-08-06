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

class vB_Upgrade_500a9 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a9';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 9';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 8';

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
	 * Add default header navbar items
	 */
	function step_1()
	{
		/* This step was no longer needed 
		as we no longer add the Profile Tab */
		$this->skip_message();
	}

	/**
	 * Change subscribed/subscribers routenew.class name
	 */
	function step_2()
	{
		if ($this->field_exists('routenew', 'name'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "routenew"),
				"UPDATE " . TABLE_PREFIX . "routenew
				SET class = 'vB5_Route_Page'
				WHERE name = 'following' OR name = 'followers'
				"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Change subscribed routenew.arguments
	 */
	function step_3()
	{
		if ($this->field_exists('routenew', 'arguments') AND $this->field_exists('routenew', 'name'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "routenew"),
				"UPDATE " . TABLE_PREFIX . "routenew
				SET arguments = '" . serialize(array('pageid' => 9)) . "'
				WHERE name = 'following'
				"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Change subscribers routenew.arguments
	 */
	function step_4()
	{
		if ($this->field_exists('routenew', 'arguments') AND $this->field_exists('routenew', 'name'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "routenew"),
				"UPDATE " . TABLE_PREFIX . "routenew
				SET arguments = '" . serialize(array('pageid' => 10)) . "'
				WHERE name = 'followers'
				"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Link
	 */
	function step_5()
	{
		$contenttype = $this->db->query_first("
			SELECT contenttypeid FROM " . TABLE_PREFIX . "contenttype
			WHERE class = 'Link'");
		if (empty($contenttype) OR empty($contenttype['contenttypeid']))
		{
			$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'contenttype'),
			"INSERT INTO " . TABLE_PREFIX . "contenttype(class,
			packageid,	canplace,	cansearch,	cantag,	canattach,	isaggregator)
			SELECT 'Link', packageid, '1', '1', '1', '1', '0'  FROM " . TABLE_PREFIX . "package where class = 'vBForum';");
		}
		else
		{
			$this->skip_message();
		}

		$this->run_query(
		sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'link'),
		"
			CREATE TABLE " . TABLE_PREFIX . "link (
				nodeid INT UNSIGNED NOT NULL,
				filedataid INT UNSIGNED NOT NULL DEFAULT '0',
				url VARCHAR(255),
				url_title VARCHAR(255),
				meta MEDIUMTEXT,
				PRIMARY KEY (nodeid),
				KEY (filedataid)
			) ENGINE = " . $this->hightrafficengine . "
		",
		self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	 * make search widget clonable
	 */
	function step_6()
	{
		$skip_message = false;
		$search_results_widget = $this->db->query_first("
			SELECT widgetid FROM " . TABLE_PREFIX . "widget
			WHERE template = 'widget_search'");
		if (!empty($search_results_widget['widgetid']) AND empty($search_results_widget['cloneable']))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widget'),
				"
				UPDATE `" . TABLE_PREFIX . "widget` SET cloneable = '1' WHERE widgetid = '$search_results_widget[widgetid]'
				"
			);
		}
		else
		{
			$skip_message = true;
		}
	}

	/**
	 * add data in permission table for link and video
	 *
	 */
	function step_7()
	{
		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'permission'),
		"UPDATE " . TABLE_PREFIX . "permission
		SET createpermissions = createpermissions | 131072 |262144 WHERE createpermissions > 1;");
	}

}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
