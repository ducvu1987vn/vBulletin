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

class vB_Upgrade_500b23 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500b23';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Beta 23';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Beta 22';

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
		/* Clear old widget definitions */
		vB::getDbAssertor()->assertQuery('vBInstall:updateWidgetDefs');
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], 'widgetdefinition'));
	}

	/**
	 * Add the table for thread redirects
	 */
	public function step_2()
	{
		if (!$this->tableExists('redirect'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'redirect'),
				"
				CREATE TABLE " . TABLE_PREFIX . "redirect (
					nodeid INT UNSIGNED NOT NULL,
					tonodeid INT UNSIGNED NOT NULL,
					UNIQUE KEY (nodeid)
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

	/**
	 * Add content type for thread redirects
	 */
	public function step_3()
	{
		$this->add_contenttype('vbulletin', 'vBForum', 'Redirect');
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
