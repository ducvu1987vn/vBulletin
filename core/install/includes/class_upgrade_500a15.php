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

class vB_Upgrade_500a15 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a15';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 15';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 14';

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

	/** Adding soft delete fields */
	// Update old announcement table
	function step_1()
	{
		if (!$this->field_exists('announcement', 'nodeid'))
		{
			// Add nodeid field
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'announcement', 1, 5),
				'announcement',
				'nodeid',
				'INT',
				array(
					'attributes' => '',
					'null'       => false,
					'default'    => 0,
					'extra'      => ''
				)
			);

		}
		else
		{
			$this->skip_message();
		}
	}

	// Update old announcement table
	function step_2()
	{
		// Drop old indexes
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'announcement', 2, 5),
			'announcement',
			'forumid'
		);
	}

	// Update old announcement table
	function step_3()
	{
			$this->drop_index(
				sprintf($this->phrase['core']['altering_x_table'], 'announcement', 3, 5),
				'announcement',
				'startdate'
			);
	}

	// Update old announcement table
	function step_4()
	{
		// Add new indices
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'announcement', 4, 5),
			'announcement',
			'nodeid',
			array('nodeid')
		);
	}

	// Update old announcement table
	function step_5()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'announcement', 5, 5),
			'announcement',
			'startdate',
			array('enddate', 'nodeid', 'startdate')
		);

	}

	// Update old announcement table
	function step_6()
	{
		if ($this->field_exists('announcement', 'forumid'))
		{
			$forumTypeid = vB_Types::instance()->getContentTypeID('vBForum_Forum');
			// Convert the old forumid into new nodeid
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], 'announcement'),
				"UPDATE " . TABLE_PREFIX . "announcement as announcement
				SET nodeid = (
					SELECT nodeid FROM " . TABLE_PREFIX . "node as node
					WHERE node.oldid = announcement.forumid AND node.oldcontenttypeid = $forumTypeid
					LIMIT 1
				)
				WHERE nodeid = 0 AND forumid > 0
				"
			);
			// Old forumid may be -1. If so we copy it to nodeid
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], 'announcement'),
				"UPDATE " . TABLE_PREFIX . "announcement as announcement
				SET nodeid = -1
				WHERE nodeid = 0 AND forumid = -1
				"
			);

		}
		else
		{
			$this->skip_message();
		}
	}

	// This step is to fix VBV-176. The issue only happens for older versions before Alpha 15. New installation isn't affected.
	public function step_7()
	{
		$check = $this->db->query_first("SELECT routeid, class FROM " . TABLE_PREFIX . "routenew WHERE name = 'advanced_search'");

		if (!$check OR $check['class'] != 'vB5_Route_Search')
		{
			$this->skip_message();
			return;
		}

		if ($check['class'] == 'vB5_Route_Search')
		{
			// We need to perform the fix

			$page = $this->db->query_first("
				SELECT pageid
				FROM " . TABLE_PREFIX . "page
				WHERE guid = 'vbulletin-4ecbdac82efb61.17736147'
			");

			if ($page)
			{
				$this->db->query_write("
					UPDATE " . TABLE_PREFIX . "routenew
					SET
						class = 'vB5_Route_Page',
						arguments = '" . serialize(array('pageid' => $page['pageid'])) . "',
						contentid = " . $page['pageid'] . ",
						guid = 'vbulletin-4ecbdacd6a8335.81846640'
					WHERE routeid = $check[routeid]
				");

				$this->db->query_write("
					UPDATE " . TABLE_PREFIX . "page
					SET routeid = $check[routeid]
					WHERE guid = 'vbulletin-4ecbdac82efb61.17736147'
				");
			}
		}

		$check = $this->db->query_first("SELECT routeid, class FROM " . TABLE_PREFIX . "routenew WHERE name = 'search'");

		if ($check AND $check['class'] == 'vB5_Route_Search')
		{
			// We need to perform the fix
			$this->skip_message();

			$page = $this->db->query_first("
				SELECT pageid
				FROM " . TABLE_PREFIX . "page
				WHERE guid = 'vbulletin-4ecbdac82f2815.04471586'
			");

			if ($page)
			{
				$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], 'routenew'),"
					UPDATE " . TABLE_PREFIX . "routenew
					SET
						class = 'vB5_Route_Page',
						arguments = '" . serialize(array('pageid' => $page['pageid'])) . "',
						contentid = " . $page['pageid'] . ",
						guid = 'vbulletin-4ecbdacd6aa3b7.75359902'
					WHERE routeid = $check[routeid]
				");

				$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], 'page'),"
					UPDATE " . TABLE_PREFIX . "page
					SET routeid = $check[routeid]
					WHERE guid = 'vbulletin-4ecbdac82f2815.04471586'
				");
			}
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
