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

class vB_Upgrade_500a2 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a2';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 2';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 1';

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
		// insert two new config fields for the video module
		$exists = $this->db->query_first("
			SELECT name
			FROM " . TABLE_PREFIX . "widgetdefinition
			WHERE
				widgetid = 2
					AND
				name = 'provider'
		");
		if (!$exists)
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], 'widgetdefinition'),
				"
					INSERT INTO `" . TABLE_PREFIX . "widgetdefinition`
					(`widgetid`, `field`, `name`, `label`, `defaultvalue`, `isusereditable`, `isrequired`, `displayorder`, `validationtype`, `validationmethod`, `data`)
					VALUES
					(2, 'Text', 'title', 'Video Title', 'Video Title', 1, 1, 1, '', '', ''),
					(2, 'Select', 'provider', 'Provider', 'youtube', 1, 1, 2, '', '', 'a:2:{s:7:\"youtube\";s:7:\"YouTube\";s:11:\"dailymotion\";s:11:\"DailyMotion\";}')
				"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_2()
	{
		// change display order and label for a video module config field
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], 'widgetdefinition'),
			"
				UPDATE " . TABLE_PREFIX . "widgetdefinition
				SET
					label = 'Video ID',
					displayorder = 3
				WHERE
					widgetid = 2
						AND
					name = 'videoid'
			"
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
