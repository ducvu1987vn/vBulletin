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

class vB_Upgrade_500a20 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a20';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 20';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 19';

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



	/** Make Attach contenttype not searchable*/
	public function step_1()
	{
		$this->skip_message();
	}

	/** Adding inserting new Blog Phrase Type **/
	public function step_2()
	{
		$existing = vB::getDbAssertor()->getRow('phrasetype', array('fieldname' => 'vb5blog'));


		if (!$existing OR !empty($existing['errors']))
		{

			$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'phrasetype'),
			"INSERT INTO " . TABLE_PREFIX . "phrasetype (fieldname, title, editrows, special)
			VALUES ('vb5blog', 'Blogs', 3, 0)"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Add a blog channel permission.**/
	public function step_3()
	{
		try
		{
			//see if there is one now.
			$nodeid = vB_Api::instanceInternal('blog')->getBlogChannel();
			$showMessage = true;
			if (!empty($nodeid))
			{
				$assertor = vB::getDbAssertor();
				$existing = $assertor->getRow('vBForum:permission', array('groupid' => 2, 'nodeid' => $nodeid));
				if (empty($existing) OR !empty($existing['errors']))
				{
					$this->show_message($this->phrase['version']['500a20']['adding_blog_channel_permission']);
					$assertor->assertQuery('permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
					'groupid' => 2, 'nodeid' => $nodeid, 'forumpermissions' => 74461201,  'moderatorpermissions' => 0,
					'createpermissions' => 520195,  'edit_time' => 24,  'require_moderate' => 0,  'maxtags' => 6,  'maxstartertags' => 3,  'maxothertags' => 3,
					'maxattachments' => 5));
					$showMessage = false;
				}
			}

			if ($showMessage)
			{
				$this->skip_message();
			}
		}
		catch (vB_Exception_Api $e)
		{
			$this->skip_message();
		}
	}

	/** Add enum for invite members **/
	public function step_4()
	{
		if ($this->field_exists('privatemessage', 'about'))
		{
			$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'privatemessage', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "privatemessage MODIFY COLUMN about ENUM('vote','vote_reply','rate','reply','follow','vm','comment','owner_to','moderator_to','owner_from','moderator','member', 'member_to', 'subscribe_content')
				"
			);
		}
		else
		{
			$this->skip_message();
		}

		$this->long_next_step();
	}

	/**
	 * Fixing blog page routes (VBV-618)
	 */
	public function step_5()
	{
		$this->skip_message();
		$this->long_next_step();
	}

	/**
	 * Fixing blogs to be moved when blog parent is modified  (VBV-529)
	 */
	public function step_6()
	{
		try
		{
			$blogChannel = vB_Api::instanceInternal('blog')->getBlogChannel();
			if (!empty($blogChannel))
			{
				$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], 'node'));
				$channelContentTypeId = vB_Types::instance()->getContentTypeID('vBForum_Channel');
				vB::getDbAssertor()->update('vBForum:node', array('inlist' => 1), array('parentid' => $blogChannel, 'contenttypeid' => $channelContentTypeId));
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

	/** removing redundant field in moderatorlog table */
	public function step_7()
	{
		if ($this->field_exists('moderatorlog', 'pollid'))
		{
			$this->drop_field(
					sprintf($this->phrase['core']['altering_x_table'], 'moderatorlog', 1, 3),
					'moderatorlog',
					'pollid'
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** removing redundant field in moderatorlog table */
	public function step_8()
	{
		if ($this->field_exists('moderatorlog', 'attachmentid'))
		{
			$this->drop_field(
					sprintf($this->phrase['core']['altering_x_table'], 'moderatorlog', 2, 3),
					'moderatorlog',
					'attachmentid'
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** removing redundant CRC32 field */
	public function step_9()
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
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
