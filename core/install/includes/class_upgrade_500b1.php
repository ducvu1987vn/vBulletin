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

class vB_Upgrade_500b1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500b1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Beta 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 45';

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

	/*
	 *	We changed how we choose the table driver for "memory" tables to
	 *	favor Innodb over the memory engine.  Convert the engine here.
	 */
	public function step_1()
	{
		global $db;
		$memory = get_memory_engine($db);
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'cpsession', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "cpsession ENGINE = $memory"
		);

		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'session', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "session ENGINE = $memory"
		);
	}

	/**
	 * hide the create blog subnav item for usergroups that are not allowed to create blogs
	 */
	function step_2()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], 'site'));

		$assertor = vB::getDbAssertor();

		$forumpermissions = vB::getDatastore()->getValue('bf_ugp_forumpermissions');
		if (empty($forumpermissions['cancreateblog']))
		{
			$forumpermissions = array();
			$parsedRaw = vB_Xml_Import::parseFile(DIR . '/includes/xml/bitfield_vbulletin.xml');
			foreach ($parsedRaw['bitfielddefs']['group'] AS $group)
			{
				if ($group['name'] == 'ugp')
				{
					foreach($group['group'] AS $bfgroup)
					{
						if ($bfgroup['name'] == 'forumpermissions')
						{
							foreach ($bfgroup['bitfield'] AS $bitfield)
							{
								$forumpermissions[$bitfield['name']] = intval($bitfield['value']);
							}
						}
					}
				}
			}
		}
		//these are the user groups that are allowed to create blogs
		$groups = $assertor->getRows('usergroup', array(
				vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'forumpermissions', 'value' => $forumpermissions['cancreateblog'], 'operator' => vB_dB_Query::OPERATOR_AND)
				)
			),
			false,
			'usergroupid'
		);

		$sites = $assertor->assertQuery('vBForum:site');
		foreach ($sites AS $site)
		{
			$changed = false;
			$header = unserialize($site['headernavbar']);
			if (!empty($header))
			{
				foreach ($header as &$h)
				{
					if ($h['title'] == 'navbar_blogs' AND !empty($h['subnav']))
					{
						foreach ($h['subnav'] as &$sn)
						{
							if ($sn['title'] == 'navbar_create_a_new_blog')
							{
								$sn['usergroups'] = array_keys($groups);
								$changed = true;
								break;
							}
						}
					}
				}
			}
			if ($changed)
			{
				$assertor->update('vBForum:site', array('headernavbar' => serialize($header)), array('siteid' => $site['siteid']));
			}
		}
	}
}
/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
