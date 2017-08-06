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

class vB_Upgrade_500b17 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500b17';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Beta 17';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Beta 16';

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
	 * Add missing text records for albums
	 */
	function step_1($data = null)
	{
		if ($this->tableExists('album'))
		{
			$assertor = vB::getDbAssertor();
			$batchSize = 1000;
			$startat = intval($data['startat']);
			$albumTypeid = vB_Types::instance()->getContentTypeID('vBForum_Album');

			if ($startat == 0)
			{
				$this->show_message($this->phrase['version']['500b17']['adding_album_textrecords']);
				$maxvB5 = $assertor->getRow('vBInstall:getMaxvB5AlbumText', array('albumtypeid' => $albumTypeid));
				$startat = intval($maxvB5['maxid']);
			}

			if (!empty($data['maxvB4']))
			{
				$maxvB4 = intval($data['maxvB4']);
			}
			else
			{
				$maxvB4 = $assertor->getRow('vBInstall:getMaxvB4AlbumMissingText', array('albumtypeid' => $albumTypeid));
				$maxvB4 = intval($maxvB4['maxid']);

				//If we don't have any posts, we're done.
				if ($maxvB4 < 1)
				{
					$this->skip_message();
					return;
				}
			}

			if ($maxvB4 <= $startat)
			{
				$this->show_message($this->phrase['core']['process_done']);
				return;
			}

			$assertor->assertQuery('vBInstall:addMissingTextAlbumRecords',
				array('albumtypeid' => $albumTypeid, 'startat' => $startat, 'batchsize' => $batchSize));

			// and set starter
			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchSize));
			return array('startat' => ($startat + $batchSize), 'maxvB4' => $maxvB4);
		}
		else
		{
			$this->skip_message();
		}

	}

	function step_2()
	{
		if ($this->tableExists('album'))
		{
			$assertor = vB::getDbAssertor();
			$albumTypeid = vB_Types::instance()->getContentTypeID('vBForum_Album');

			$oldid = $assertor->getRow('vBInstall:getMinvB5AlbumMissingStarter', array('albumtypeid' => $albumTypeid));
			if (empty($oldid['minid']))
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}
			$this->show_message(sprintf($this->phrase['version']['500b17']['setting_x_starters'], 'Album'));
			$assertor->assertQuery('vBInstall:setStarter', array('contenttypeid' => $albumTypeid, 'startat' => ($oldid['minid'] - 1)));
		}
		else
		{
			$this->skip_message();
		}
	}

	/** This set the moderator permissions */
	public function step_3()
	{
		if ($this->field_exists('moderator', 'forumid'))
		{
			$this->show_message($this->phrase['version']['500b17']['updating_moderator_permissions']);
			$assertor = vB::getDbAssertor();
			$assertor->assertQuery('vBForum:moderator', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE, 'nodeid' => 1,
				vB_dB_Query::CONDITIONS_KEY => array('forumid' => -1)));
			$assertor->assertQuery('vBInstall:updateModeratorNodeid',
				array('forumtype' => vB_Types::instance()->getContentTypeID('vBForum_Forum')));
		}
		else
		{
			$this->skip_message();
		}
	}
    
	function step_4()
	{
		if (!$this->tableExists('mapiposthash'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'mapiposthash'),
				"
				CREATE TABLE " . TABLE_PREFIX . "mapiposthash (
					posthashid INT UNSIGNED NOT NULL AUTO_INCREMENT,
					posthash VARCHAR(32) NOT NULL DEFAULT '',
					filedataid INT UNSIGNED NOT NULL DEFAULT '0',
					dateline INT UNSIGNED NOT NULL DEFAULT '0',
					PRIMARY KEY (posthashid),
					KEY posthash (posthash)
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
	
	function step_5($data = null)
	{
		if ($this->tableExists('customavatar'))
		{
			$assertor = vB::getDbAssertor();
			$batchSize = 1000;
			
			if (!intval($data['startat']))
			{
				$this->show_message($this->phrase['version']['500b17']['fixing_custom_avatars']);
			}
			
			$fixId = $assertor->getRow('vBInstall:getMinCustomAvatarToFix');
			$startat = intval($fixId['minid']);
			
			if (empty($startat))
			{
				$this->skip_message();
				return;
			}
			
			$assertor->assertQuery('vBInstall:fixCustomAvatars',
				array('startat' => ($startat - 1), 'batchsize' => $batchSize));

			// and set starter
			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchSize));
			return array('startat' => ($startat + $batchSize));
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
