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

class vB_Upgrade_500a11 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a11';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 11';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 10';

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

	function step_2()
	{
		$this->skip_message();
	}

	/**
	 * Report / Flag
	 */
	function step_3()
	{
		// Reports Channel
		$reportChannel = $this->db->query_first("
			SELECT node.nodeid, node.oldcontenttypeid
			FROM " . TABLE_PREFIX . "node AS node
			INNER JOIN " . TABLE_PREFIX . "channel AS channel ON (node.nodeid = channel.nodeid)
			WHERE channel.guid = '" . vB_Channel::REPORT_CHANNEL . "'");
		$oldContentTypeId = 9997;
		if (!empty($reportChannel) AND $reportChannel['oldcontenttypeid'] != $oldContentTypeId)
		{
			// Set the oldcontenttypeid and oldid if they're not set. The channel should've already been created in 500a1.
			$query = "
			UPDATE " . TABLE_PREFIX . "node
			SET oldid = 1, oldcontenttypeid = " . $oldContentTypeId . "
			WHERE nodeid = " . $reportChannel['nodeid'];
			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], 'node'));
			$this->db->query_write(
				$query);
		}
		else
		{
			$this->skip_message();
		}

		$contenttype = $this->db->query_first("
			SELECT contenttypeid FROM " . TABLE_PREFIX . "contenttype
			WHERE class = 'Report'");
		if (empty($contenttype) OR empty($contenttype['contenttypeid']))
		{
			$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'contenttype'),
			"INSERT INTO " . TABLE_PREFIX . "contenttype(class,
			packageid,	canplace,	cansearch,	cantag,	canattach,	isaggregator)
			SELECT 'Report', packageid, '0', '0', '0', '0', '0'  FROM " . TABLE_PREFIX . "package where class = 'vBForum';");
		}
		else
		{
			$this->skip_message();
		}

		$this->run_query(
		sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'link'),
		"
			CREATE TABLE " . TABLE_PREFIX . "report (
				nodeid INT UNSIGNED NOT NULL,
				reportnodeid INT UNSIGNED NOT NULL DEFAULT '0',
				closed SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (nodeid),
				KEY (reportnodeid, closed)
			) ENGINE = " . $this->hightrafficengine . "
		",
		self::MYSQL_ERROR_TABLE_EXISTS
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/