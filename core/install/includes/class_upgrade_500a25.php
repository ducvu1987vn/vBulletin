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

class vB_Upgrade_500a25 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a25';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 25';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 24';

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


	public function step_1()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'cron'));
		vB::getDbAssertor()->delete('cron', array(
						array('field'=>'varname', 'value' => array('threadviews', 'attachmentviews'), vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ)
				));
	}

	/**
	 * Porting rssfeed table. (Using nodeid and changing enum field)
	 */
	public function step_2()
	{
		$alterSql = array();
		if ($this->field_exists('rssfeed', 'forumid'))
		{
			$alterSql[] = "CHANGE COLUMN forumid nodeid SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0'";
		}

		if ($this->field_exists('rssfeed', 'threadactiondelay'))
		{
			$alterSql[] = "CHANGE COLUMN threadactiondelay topicactiondelay SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0'";
		}

		if ($this->field_exists('rssfeed', 'itemtype'))
		{
			$alterSql[] = "CHANGE COLUMN itemtype itemtype ENUM('topic','announcement') NOT NULL DEFAULT 'topic'";
		}

		if (!empty($alterSql))
		{
			$sql = implode(', ' , $alterSql);
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'rssfeed'),
				"ALTER TABLE " . TABLE_PREFIX . "rssfeed $sql"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Update with new rssfeed enum value
	 */
	public function step_3()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'rssfeed'));
		vB::getDbAssertor()->update('vBForum:rssfeed', array('itemtype' => 'topic'), array('itemtype' => ''));
	}

	/**
	 * Update rsslog with new values
	 */
	public function step_4()
	{
		$alterSql = array();
		if ($this->field_exists('rsslog', 'threadactiontime'))
		{
			$alterSql[] = "CHANGE COLUMN threadactiontime topicactiontime INT(10) UNSIGNED NOT NULL DEFAULT '0'";
		}

		if ($this->field_exists('rsslog', 'threadactioncomplete'))
		{
			$alterSql[] = "CHANGE COLUMN threadactioncomplete topicactioncomplete INT(10) UNSIGNED NOT NULL DEFAULT '0'";
		}

		if ($this->field_exists('rsslog', 'itemtype'))
		{
			$alterSql[] = "CHANGE COLUMN itemtype itemtype ENUM('topic','announcement') NOT NULL DEFAULT 'topic'";
		}

		if (!empty($alterSql))
		{
			$sql = implode(', ' , $alterSql);
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'rsslog'),
				"ALTER TABLE " . TABLE_PREFIX . "rsslog $sql"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Update with new rsslog enum value
	 */
	public function step_5()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], 'rsslog'));
		vB::getDbAssertor()->update('vBForum:rsslog', array('itemtype' => 'topic'), array('itemtype' => ''));
	}
	/**
	 * Update session table
	 */
	public function step_6()
	{
		// Clear all sessions first, otherwise we can fail with "table full" error.
		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], 'session'),
			"TRUNCATE TABLE " . TABLE_PREFIX . "session"
		);

		if ($this->field_exists('session', 'nodeid'))
		{
			$this->drop_field(
				sprintf($this->phrase['core']['altering_x_table'], 'session', 1, 4),
				'session',
				'nodeid'
			);
		}
		else
		{
			$this->skip_message();
		}

		if ($this->field_exists('session', 'pageid'))
		{
			$this->drop_field(
				sprintf($this->phrase['core']['altering_x_table'], 'session', 2, 4),
				'session',
				'pageid'
			);
		}
		else
		{
			$this->skip_message();
		}

		if (!$this->field_exists('session', 'pagekey'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'session', 3, 4),
				'session',
				'pagekey',
				'VARCHAR',
				array('null' => false, 'length' => 255)
			);
			$this->add_index(
				sprintf($this->phrase['core']['altering_x_table'], 'session', 4, 4),
				'session',
				'pagekey',
				array('pagekey')
			);
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
