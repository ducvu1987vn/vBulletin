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

class vB_Upgrade_500a19 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a19';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 19';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 18';

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

	/** removing redundant CRC32 field */
	function step_1()
	{
		if ($this->field_exists('searchlog', 'CRC32'))
		{
			$this->drop_field(
					sprintf($this->phrase['core']['altering_x_table'], 'searchlog', 1, 1),
					'searchlog',
					'CRC32'
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** update nav bar blog link **/
	function step_2()
	{
		$this->show_message($this->phrase['version']['500a17']['adding_blog_navbar_link']);
		$assertor = vB::getDbAssertor();
		$sites = $assertor->getRows('vBForum:site', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT));
		foreach ($sites as $site)
		{
			$headerNav = unserialize($site['headernavbar'])	;
			foreach ($headerNav as $key => $nav)
			{
				if (($nav['title'] == 'Blogs') AND ($nav['url'] == '#'))
				{
					$headerNav[$key]['url'] = 'blogs';
					$assertor->assertQuery('vBForum:site', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					'siteid' => $site['siteid'], 'headernavbar' => serialize($headerNav)));
					break;
				}
			}

		}
	}

	/** Blog Posts were originally set to protected, but they shouldn't be. They should be visible. **/
	function step_3()
	{
		try
		{
			$blogChannel = vB_Api::instanceInternal('blog')->getBlogChannel();
			if (!empty($blogChannel))
			{
				$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'),
				"UPDATE " . TABLE_PREFIX . "node AS node INNER JOIN " . TABLE_PREFIX . "closure AS cl ON cl.child = node.nodeid
				AND cl.parent = $blogChannel
				SET node.protected = 0 ;");
			}
			else
			{
				$this->skip_message();
			}
		}
		catch (vB_Exception_Api $e)
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
