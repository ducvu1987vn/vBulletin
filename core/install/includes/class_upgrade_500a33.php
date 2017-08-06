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

class vB_Upgrade_500a33 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a33';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 33';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 32';

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
		$this->skip_message();
	}

	/**
	* Step #2 - Retire existing vB4 styles
	*
	*/
	function step_2()
	{
		$this->run_query(
			$this->phrase['version']['500a33']['updating_styles'],
			"UPDATE " . TABLE_PREFIX . "style
			SET userselect = 0,	displayorder = 65432,
			title = IF(title LIKE '%Incompatible%', title, CONCAT(title, ' " . $this->db->escape_string($this->phrase['version']['500a33']['incompatible']) . "'))
			WHERE NOT (dateline = 99999999 OR title LIKE '%" . $this->db->escape_string($this->phrase['version']['500a33']['default_style']) . "%')
		");
	}

	/**
	* Step #3 - Create new vB5 style
	*
	*/
	function step_3()
	{
		$check = $this->db->query_first("
			SELECT styleid FROM " . TABLE_PREFIX . "style WHERE dateline = 99999999
			OR title LIKE '%" . $this->db->escape_string($this->phrase['version']['500a33']['default_style']) . "%'
		");

		if ($check['styleid'])
		{
			$this->skip_message();
		}
		else
		{
			$this->db->query("
				INSERT INTO " . TABLE_PREFIX . "style
					(title,	parentid, userselect, displayorder, dateline)
					VALUES
					('" . $this->db->escape_string($this->phrase['version']['500a33']['default_style']) . "', -1, 1, 1, 99999999)
			");

			$styleid = $this->db->insert_id();

			$this->run_query(
				$this->phrase['version']['500a33']['creating_default_style'],
				"UPDATE " . TABLE_PREFIX . "style
				SET parentlist = '" . intval($styleid) . ",-1'
				WHERE styleid = " . intval($styleid)
			);

			$this->run_query(
				$this->phrase['version']['500a33']['updating_style'],
				"UPDATE " . TABLE_PREFIX . "setting
				SET value = '" . intval($styleid) . "'
				WHERE varname = 'styleid'
			");
		}
	}

	/**
	* Step #4 - Update some settings
	*
	*/
	function step_4()
	{
		/* Update the bburl path, this is still used
		by the backend atm and needs to point to the core */
		if ($this->caller == 'cli')
		{
			/* CLI, so just append /core to what exists */
			$this->run_query(
				$this->phrase['version']['500a33']['updating_options'],
				"UPDATE " . TABLE_PREFIX . "setting
				SET value = IF(value LIKE '%/core',	value, CONCAT(value, '/core'))
				WHERE varname = 'bburl'
			");
		}
		else // ajax //
		{
			/* WEB, so try and rebuild it from scratch */
			$port = intval($_SERVER['SERVER_PORT']);
			$port = in_array($port, array(80, 443)) ? '' : ':' . $port;
			$scheme = (($port == ':443') OR (isset($_SERVER['HTTPS']) AND $_SERVER['HTTPS'] AND ($_SERVER['HTTPS'] != 'off'))) ? 'https://' : 'http://';
			$path = $scheme . $_SERVER['SERVER_NAME'] . $port . substr(SCRIPTPATH, 0, strpos(SCRIPTPATH, '/install/'));

			$this->run_query(
				$this->phrase['version']['500a33']['updating_options'],
				"UPDATE " . TABLE_PREFIX . "setting
				SET value = '$path'
				WHERE varname = 'bburl'
			");
		}
	}

	/**
	* Step #5 - Update modcp route
	*
	*/
	function step_5()
	{

		$this->show_message($this->phrase['version']['500a33']['fix_modcp_route']);
		vB::getDbAssertor()->update('routenew', array('prefix' => 'modcp'), array('regex' => 'modcp/(?P<file>[a-zA-Z0-9_.-]*)'));
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
