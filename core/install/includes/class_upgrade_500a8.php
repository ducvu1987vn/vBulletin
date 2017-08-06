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

class vB_Upgrade_500a8 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a8';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 8';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 7';

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

	/** Change user.autosubscribe default from -1 to 0 */
	public function step_1()
	{
		if ($this->field_exists('user', 'autosubscribe'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'user'),
				"UPDATE " . TABLE_PREFIX . "user
				SET autosubscribe = '0'
				WHERE autosubscribe = '-1'
			");
		}
		else
		{
			$this->skip_message();
		}
	}

	/** And change autosubscribe column */
	public function step_2()
	{
		if ($this->field_exists('user', 'autosubscribe'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'user', 2, 2),
				"ALTER TABLE " . TABLE_PREFIX . "user CHANGE COLUMN autosubscribe autosubscribe SMALLINT(6) UNSIGNED NOT NULL DEFAULT '0'
				"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	// Fix duplicated phrase varnames for any custom languages in upgrade script
	// Add unique index on varname in phrase table (remove fieldname from current unique index)
	public function step_3()
	{
		// All languages including MASTER should be processed here.
		// Otherwise we can't add varname field as unique

		$phrase = array();
		$results = $this->db->query_read("
			SELECT varname
			FROM " . TABLE_PREFIX . "phrase
			GROUP BY varname
			HAVING COUNT(varname) > 1
		");
		while ($result = $this->db->fetch_array($results))
		{
			$phrase[] = $result['varname'];
		}

		if ($phrase)
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'phrase'),
				"UPDATE " . TABLE_PREFIX . "phrase SET
					varname = CONCAT(varname, '_g', fieldname)
				WHERE
					varname IN ('" . implode("', '", $phrase) . "')
						AND
					fieldname <> 'global'
			");
		}

		// Unique index
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'phrase', 1, 2),
			'phrase',
			'name_lang_type'
		);

		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'phrase', 2, 2),
			'phrase',
			'name_lang_type',
			array('varname', 'languageid'),
			'unique'
		);

	}
	/** Add user.privacy_options field */
	public function step_4()
	{
		$this->skip_message();
	}

}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
