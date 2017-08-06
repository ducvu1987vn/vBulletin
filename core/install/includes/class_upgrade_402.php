<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
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

class vB_Upgrade_402 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '402';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.0.2';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.0.1';

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
	* Step #1
	*
	*/
	function step_1()
	{
		$doads = array(
			'thread_first_post_content' => 1,
			'thread_last_post_content'  => 1
		);
		require_once(DIR . '/includes/adminfunctions_template.php');
		$ads = $this->db->query_read("
			SELECT adlocation, COUNT( * ) AS count
			FROM " . TABLE_PREFIX . "ad
			WHERE
				adlocation IN ('" . implode('\', \'', array_keys($doads)) . "')
					AND
				active = 1
			GROUP BY
				adlocation
		");
		while ($ad = $this->db->fetch_array($ads))
		{
			unset($doads[$ad['adlocation']]);
		}

		$count = 0;
		foreach (array_keys($doads) AS $ad)
		{
			$count++;
			$template_un = '';
			$template = compile_template($template_un);
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'template', $count, count($doads)),
				"UPDATE " . TABLE_PREFIX . "template
				SET
					template = '" . $this->db->escape_string($template) . "',
					template_un = '',
					dateline = " . TIMENOW . "
				WHERE
					styleid IN (-1,0)
						AND
					title = 'ad_" . $this->db->escape_string($ad) . "'
				"
			);
		}
		if (!$count)
		{
			$this->skip_message();
		}
	}

	/**
	* Step #2
	*
	*/
	function step_2()
	{
		$this->skip_message();
	}

	/**
	* Step #3 - change the standard icons to the new png images.
	*
	*/
	function step_3()
	{
		for ($i = 1; $i < 15; $i++)
		{
			$this->run_query(
				sprintf($this->phrase['version']['402']['update_icon'], $i, 14),
				"UPDATE " . TABLE_PREFIX . "icon SET iconpath = 'images/icons/icon$i.png'
				WHERE iconpath = 'images/icons/icon$i.gif' AND imagecategoryid = 2"
			);
		}

		require_once(DIR . '/includes/adminfunctions.php');
		build_image_cache('icon');
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
