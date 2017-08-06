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

class vB_Upgrade_500b28 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500b28';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Beta 28';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Beta 27';

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
	 * Step 1 - create postedithistory if it doesn't exist because this forum started on vB 5
	 */
	public function step_1()
	{
			$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'postedithistory'),
			"CREATE TABLE " . TABLE_PREFIX . "postedithistory (
				postedithistoryid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				postid INT UNSIGNED NOT NULL DEFAULT '0',
				nodeid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				username VARCHAR(100) NOT NULL DEFAULT '',
				title VARCHAR(250) NOT NULL DEFAULT '',
				iconid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				reason VARCHAR(200) NOT NULL DEFAULT '',
				original SMALLINT NOT NULL DEFAULT '0',
				pagetext MEDIUMTEXT,
				PRIMARY KEY  (postedithistoryid),
				KEY nodeid (nodeid,userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/*
	 * Step 2 - Add postedithistory.nodeid
	 */
	public function step_2()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'postedithistory', 1, 5),
			'postedithistory',
			'nodeid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/*
	 * Step 3 - Add index on postedithistory.nodeid
	 */
	public function step_3()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'postedithistory', 2, 5),
			'postedithistory',
			'nodeid',
			array('nodeid', 'userid')
		);
	}

	/*
	 * Step 4 - Update postedithistory.nodeid -- this will get non first posts.
	 */
	public function step_4()
	{
		$this->show_message(sprintf($this->phrase['core']['altering_x_table'], 'postedithistory', 3, 5));
		$postTypeId = vB_Types::instance()->getContentTypeID('vBForum_Post');
		vB::getDbAssertor()->assertQuery('vBInstall:500b28_updatePostHistory1', array('posttypeid' => $postTypeId));
	}

	/*
	 * Step 5 - Update postedithistory.nodeid -- this will get first posts, which are now saved as thread type in vB5.
	 * We can't use oldcontenttypeid to tie these directly back to the postedithistory data so we use the thread_post table to get the threadid.
	 */
	public function step_5()
	{
		$this->show_message(sprintf($this->phrase['core']['altering_x_table'], 'postedithistory', 4, 5));
		$threadTypeId = vB_Types::instance()->getContentTypeID('vBForum_Thread');
		vB::getDbAssertor()->assertQuery('vBInstall:500b28_updatePostHistory2', array('threadtypeid' => $threadTypeId));
	}

	/*
	 * Step 6 - We may have some orphan logs that reference a non-existant post/thread. These logs have nodeids of 0.
	 * Remove them.
	 */
	public function step_6()
	{
		$this->show_message(sprintf($this->phrase['core']['altering_x_table'], 'postedithistory', 5, 5));
		vB::getDbAssertor()->assertQuery('vBInstall:500b28_updatePostHistory3');
	}

	/*
	 * Step 7 - Remove not needed index if it exists from messagefolder table
	 */
	public function step_7()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'messagefolder', 1, 1),
			'messagefolder',
			'userid_title_titlephrase'
		);
	}

	/*
	 * Step 8 - fix starter on imported album photos
	 */
	public function step_8($data = array())
	{
		$assertor = vB::getDbAssertor();
		$batchsize = 2000;
		$this->show_message($this->phrase['version']['500b28']['fixing_aphoto_records']);

		if (isset($data['startat']))
		{
			$startat = $data['startat'];
		}

		if (!empty($data['maxvB4']))
		{
			$maxPMid = $data['maxvB4'];
		}
		else
		{
			$maxid = $assertor->getRow('vBInstall:getMaxNodeRecordToFix', array('contenttypeid' => 9986));
			$maxid = intval($maxid['maxid']);

			//If there are no records to fix...
			if (intval($maxid) < 1)
			{
				$this->skip_message();
				return;
			}
		}

		if (!isset($startat))
		{
			$maxvB5 = $assertor->getRow('vBInstall:getMaxNodeRecordFixed', array('contenttypeid' => 9986));

			if (!empty($maxvB5) AND !empty($maxvB5['maxid']))
			{
				$startat = $maxvB5['maxid'];
			}
			else
			{
				$startat = 1;
			}
		}

		if ($startat >= $maxid)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}

		// fix starter from album photos
		$assertor->assertQuery('vBInstall:setResponseStarter', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9986));
		return array('startat' => ($startat + $batchsize), 'maxvB4' => $maxid);
	}

	/*
	 * Step 9 Change nodeoption 'moderate_comments' to 'moderate_topics' in groups
	 *
	 */
	public function step_9()
	{
		$sgChannel = vB_Api::instanceInternal('socialgroup')->getSGChannel();
		$options = vB_Api::instanceInternal('node')->getOptions();
		$moderate_comments = $options['moderate_comments'];
		$moderate_topics = $options['moderate_topics'];

		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'node', 1, 1),
				"UPDATE " . TABLE_PREFIX . "node AS n
				 INNER JOIN " . TABLE_PREFIX . "closure cl on n.nodeid = cl.child
				 INNER JOIN " . TABLE_PREFIX . "channel ch on n.nodeid = ch.nodeid
				 SET n.nodeoptions = n.nodeoptions - $moderate_comments + $moderate_topics
				 where cl.parent = $sgChannel AND ch.category = 0 AND n.nodeoptions & $moderate_comments
		");
	}

	/**
	 * Handle customized values for stylevars that have been renamed
	 */
	public function step_10()
	{
		$mapper = new vB_Stylevar_Mapper();

		// Add mappings
		$mapper->addMapping('filter_bar_button_border', 'toolbar_button_border');
		$mapper->addMapping('filter_bar_form_field_background', 'toolbar_form_field_background');
		$mapper->addMapping('filter_bar_form_field_border', 'toolbar_form_field_border');
		$mapper->addMapping('filter_bar_form_field_placeholder_text_color', 'toolbar_form_field_placeholder_text_color');
		$mapper->addMapping('filter_bar_text_color', 'toolbar_text_color');
		$mapper->addMapping('filter_dropdown_background_gradient_end', 'toolbar_dropdown_background_gradient_end');
		$mapper->addMapping('filter_dropdown_background_gradient_start', 'toolbar_dropdown_background_gradient_start');
		$mapper->addMapping('filter_dropdown_border', 'toolbar_dropdown_border');
		$mapper->addMapping('filter_dropdown_divider_color', 'toolbar_dropdown_divider_color');
		$mapper->addMapping('filter_dropdown_text_color', 'toolbar_dropdown_text_color');
		$mapper->addMapping('filter_dropdown_text_color_active', 'toolbar_dropdown_text_color_active');

		// Do the processing
		if ($mapper->load() AND $mapper->process())
		{
			$this->show_message($this->phrase['version']['408']['mapping_customized_stylevars']);
			//$mapper->displayResults(); // Debug only
			$mapper->processResults();
		}
		else
		{
			$this->skip_message();
		}
	}

	/*
	 * Step 11 - Add filedataresize table
	 */
	public function step_11()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'filedataresize'),
			"CREATE TABLE " . TABLE_PREFIX . "filedataresize (
				filedataid INT UNSIGNED NOT NULL,
				resize_type ENUM('icon', 'thumb', 'small', 'medium', 'large') NOT NULL DEFAULT 'thumb',
				resize_filedata MEDIUMBLOB,
				resize_filesize INT UNSIGNED NOT NULL DEFAULT '0',
				resize_dateline INT UNSIGNED NOT NULL DEFAULT '0',
				resize_width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				resize_height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				reload TINYINT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (filedataid, resize_type),
				KEY type (resize_type)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/*
	 * Step 12 - Convert filedata
	 */
	function step_12($data = NULL)
	{
		if ($this->field_exists('filedata', 'thumbnail'))
		{
			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'filedata'));
			$process = 500;
			$startat = intval($data['startat']);

			if (!empty($data['maxvB4']))
			{
				$maxvB4 = $data['maxvB4'];
			}
			else
			{
				$maxvB4 = vB::getDbAssertor()->getRow('vBInstall:500b28_updateFiledata1');
				$maxvB4 = $maxvB4['maxid'];

				//If we don't have any more filedata, we're done.
				if (intval($maxvB4) < 1)
				{
					$this->skip_message();
					return;
				}

			}
			$maxvB5 = vB::getDbAssertor()->getRow('vBInstall:500b28_updateFiledata2');
			if (!empty($maxvB5) AND !empty($maxvB5['maxid']))
			{
				$maxvB5 = $maxvB5['maxid'];
			}
			else
			{
				$maxvB5 = 0;
			}

			$maxvB5 = max($startat, $maxvB5);
			if (($maxvB4 <= $maxvB5) AND !$startat)
			{
				$this->skip_message();
				return;
			}
			else if ($maxvB4 <= $maxvB5)
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}
			vB::getDbAssertor()->assertQuery('vBInstall:500b28_updateFiledata3', array('maxvB5' => $maxvB5, 'process' => $maxvB5 + $process));
			$this->show_message(sprintf($this->phrase['core']['processed_records_x_y'], $maxvB5 + 1, $maxvB5 + $process - 1));

			return array('startat' => ($maxvB5 + $process - 1), 'maxvB4' => $maxvB4);
		}
		else
		{
			$this->skip_message();
		}
	}

	/*
	 * Drop thumbnail fields
	 */
	public function step_13()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'filedata', 1, 5),
			'filedata',
			'thumbnail'
		);
	}

	/*
	 * Drop thumbnail fields
	 */
	public function step_14()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'filedata', 2, 5),
			'filedata',
			'thumbnail_filesize'
		);
	}

	/*
	 * Drop thumbnail fields
	 */
	public function step_15()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'filedata', 3, 5),
			'filedata',
			'thumbnail_width'
		);
	}

	/*
	 * Drop thumbnail fields
	 */
	public function step_16()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'filedata', 4, 5),
			'filedata',
			'thumbnail_height'
		);
	}

	/*
	 * Drop thumbnail fields
	 */
	public function step_17()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'filedata', 5, 5),
			'filedata',
			'thumbnail_dateline'
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/