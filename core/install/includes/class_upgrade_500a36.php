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

class vB_Upgrade_500a36 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a36';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 36';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 35';

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
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], 'site'));

		$assertor = vB::getDbAssertor();
		$phraseApi = vB_Api::instanceInternal('phrase');

		$sites = $assertor->assertQuery('vBForum:site', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT));

		$phrases = array();

		foreach ($sites AS $site)
		{
			$header = unserialize($site['headernavbar']);
			if (!empty($header))
			{
				foreach ($header as &$h)
				{
					$this->getNavbarPhrase($h, $phrases);
				}
			}

			$footer = unserialize($site['footernavbar']);
			if (!empty($footer))
			{
				foreach ($footer as &$f)
				{
					$this->getNavbarPhrase($f, $phrases);
				}
			}

			// remove phrases that were already created
			$existingPhrases = $phraseApi->fetch(array_keys($phrases));
			if (!empty($existingPhrases))
			{
				foreach($existingPhrases as $name => $phrase)
				{
					if ($name != $phrases[$name]['title'])
					{
						// replace title with phrase name
						$phrases[$name]['title'] = $name;
					}

					unset($phrases[$name]);
				}
			}

			if (!empty($phrases))
			{
				vB_Upgrade::createAdminSession();

				// create missing phrases
				foreach($phrases as $name => $data)
				{
					$phraseApi->save('navbarlinks', $name, array(
							'text' => array(0 => $data['title']),
							'oldvarname' => $name,
							'oldfieldname' => 'navbarlinks',
							't' => 0,
							'ismaster' => 0,
							'product' => 'vbulletin'
					));
					$phrases[$name]['title'] = $name;
				}

				// now update footer and header
				$assertor->update('vBForum:site', array(
					'headernavbar' => serialize($header),
					'footernavbar' => serialize($footer)
				), array('siteid' => $site['siteid']));
			}
		}
	}

	protected function getNavbarPhrase(&$navbar, &$phrases)
	{
		// Already processed ....
		if (substr($navbar['title'],0,7) == 'navbar_')
		{
			return;
		}

		$words = explode(' ', $navbar['title']);
		array_walk($words, 'trim');
		$phraseName = 'navbar_' . strtolower(implode('_', $words));

		// avoid duplicates
		$i = 1;
		$temp = $phraseName;
		while (isset($phrases[$temp]))
		{
			$temp = $phraseName . "_$i";
			$i++;
		}
		$phraseName = $temp;

		$phrases[$phraseName] =& $navbar;

		if (isset($navbar['subnav']) AND !empty($navbar['subnav']))
		{
			foreach ($navbar['subnav'] AS &$s)
			{
				$this->getNavbarPhrase($s, $phrases);
			}
		}
	}


	/**
	 * Forum prefix set update
	 */
	function step_2()
	{
		if (!$this->tableExists('channelprefixset'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'channelprefixset'),
				"
				CREATE TABLE " . TABLE_PREFIX . "channelprefixset (
					nodeid INT UNSIGNED NOT NULL DEFAULT '0',
					prefixsetid VARCHAR(25) NOT NULL DEFAULT '',
					PRIMARY KEY (nodeid, prefixsetid)
				) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_3()
	{
		// convert old forumprefixset table data and insert into the new channelprefixset table
		if ($this->tableExists('channelprefixset') AND $this->tableExists('forumprefixset'))
		{
			$prefixsets = $this->db->query_read("
				SELECT forumprefixset.forumid, forumprefixset.prefixsetid, node.nodeid
				FROM " . TABLE_PREFIX . "forumprefixset AS forumprefixset
				JOIN " . TABLE_PREFIX . "node AS node ON (forumprefixset.forumid = node.oldid AND node.oldcontenttypeid = 3)
			");

			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'channelprefixset'));

			while ($prefixset = $this->db->fetch_array($prefixsets))
			{
				$this->db->query_write("
					INSERT INTO " . TABLE_PREFIX . "channelprefixset
					(nodeid, prefixsetid)
					VALUES
					($prefixset[nodeid], '$prefixset[prefixsetid]')
				");
			}

			// Drop old forumprefixset table
			$this->run_query(
				sprintf($this->phrase['core']['dropping_old_table_x'], "forumprefixset"),
				"DROP TABLE IF EXISTS " . TABLE_PREFIX . "forumprefixset"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_4()
	{
		/* Adding of prefixid to node table removed. 
		Its part of the table creation in Alpha 1 Step 9, and also in the installs schema file
		No legitimate upgrades should ever need it adding here, as this version was never public */

		$this->skip_message();
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
