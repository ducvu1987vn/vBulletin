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

class vB_Upgrade_4111a1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '4111a1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.1.11 Alpha 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.1.10';

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

	/** In general, upgrade files between 4.1.5 and 500a1 are likely to be different in vB5 from their equivalent in vB4.
	 *  Since large portions of vB4 code were removed in vB5, the upgrades to ensure that code works is unnecessary. If
	 *  there are actual errors that affect vB5, those must be included of course. If there are changes whose absence would
	 *  break a later step, those are required.
	 *
	 * But since these files will only be used to upgrade to versions after 5.0.0 alpha 1, most of the upgrade steps can be
	 * omitted. We could use skip_message(), but that takes up a redirect and, in the cli upgrade, a recursion. We would rather
	 * avoid those. So we have removed those steps,
	 * steps 1 and 2 the original are bad. Since a new install or upgrade from an early vB5 alpha install would not have this,
	 *	we would have different data properties in the wild.
	 * Steps 4,5,6 are not needed because vB4 mobile styles are not used in vB5, and wouldn't work anyway.
	 *
	 * Not certain about Step 4, so let's leave that in.
	 */

	/*
	  Step 1 - Drop primary key on stylevardfn
	*/
	function step_1()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'stylevardfn', 1, 2),
			"ALTER TABLE " . TABLE_PREFIX . "stylevardfn DROP PRIMARY KEY",
			self::MYSQL_ERROR_DROP_KEY_COLUMN_MISSING
		);
	}

	/*
	  Step 2 - Add primary key that allows stylevardfn per styleid
	*/
	function step_2()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'stylevardfn', 2, 2),
			"ALTER IGNORE TABLE " . TABLE_PREFIX . "stylevardfn ADD PRIMARY KEY (stylevarid, styleid)",
			self::MYSQL_ERROR_PRIMARY_KEY_EXISTS
		);
	}

	/*
	  Step 3 - Make sure there is no -2 styles in style
	*/
	function step_3()
	{
		if ($this->registry->db->query_first("SELECT styleid FROM " . TABLE_PREFIX . "style WHERE styleid = -2"))
		{
			$max = $this->registry->db->query_first("
				SELECT MAX(styleid) AS styleid FROM " . TABLE_PREFIX . "style
			");

			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'style', 1, 2),
				"UPDATE " . TABLE_PREFIX . "style SET
					styleid = " . ($max['styleid'] + 1) . ",
					parentlist = '" . ($max['styleid'] + 1) . ",-1'
				WHERE styleid = -2"
			);

			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'style', 2, 2),
				"ALTER IGNORE TABLE  " . TABLE_PREFIX . "style CHANGE styleid styleid INT UNSIGNED NOT NULL AUTO_INCREMENT"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/*
	 * Step 2 - Updating the default mime type for bmp images.
	 */
	function step_4() //Was step 7
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], "attachmenttype"),
			"UPDATE " . TABLE_PREFIX . "attachmenttype
			SET mimetype = '" . $this->db->escape_string(serialize(array('Content-type: image/bmp'))) . "'
			WHERE extension = 'bmp'
		");
	}


}



/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
