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

class vB_Upgrade_500a28 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a28';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 28';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 27';

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
	* Add oldfolderid field to messagefolder table
	*/
	public function step_1()
	{
		if (!$this->field_exists('messagefolder', 'oldfolderid'))
		{

			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'messagefolder ', 1, 2),
				'messagefolder',
				'oldfolderid',
				'tinyint',
				array('null' => true, 'default' => NULL)
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Add UNIQUE index to the userid, oldfolderid pair on the messagefolder table 
	* For ensuring no duplicate imports from vb4 custom folders
	*/ 
	public function step_2()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'messagefolder', 2, 2),
			'messagefolder',
			'userid_oldfolderid',
			array('userid', 'oldfolderid'),
			'unique'
		);
	}

	/**
	 * Importing custom folders
	 */
	public function step_3($data = array())
	{
		$assertor = vB::getDbAssertor();
		$batchsize = 1000;
		$startat = intval($data['startat']);

		// Check if any users have custom folders
		if (!empty($data['totalUsers']))
		{
			$totalUsers = $data['totalUsers'];
		}
		else
		{
			// Get the number of users that has custom pm folders
			$totalUsers = $assertor->getRow('vBInstall:getTotalUsersWithFolders');
			$totalUsers = intval($totalUsers['totalusers']);

			if (intval($totalUsers) < 1)
			{
				$this->skip_message();
				return;
			}
			else
			{
				$this->show_message($this->phrase['version']['500b27']['importing_custom_folders']);
			}
		}

		if ($startat >= $totalUsers)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}
		// Get the users for import
		$this->show_message(sprintf($this->phrase['core']['processing_records_x'], $batchsize));
		$users = $assertor->getRows('vBInstall:getUsersWithFolders', array('startat' => $startat, 'batchsize' => $batchsize));
		$inserValues = array();
		foreach ($users as $user)
		{
			$pmFolders = unserialize($user['pmfolders']);

			foreach ($pmFolders as $folderid => $title)
			{
				$inserValues[] = array(
					'userid' => $user['userid'],
					'title' => $title,
					'oldfolderid' => $folderid,
				);
			}
		}
		$assertor->assertQuery('insertignoreValues', array('table' => 'messagefolder', 'values' => $inserValues));
		return array('startat' => ($startat + $batchsize), 'totalUsers' => $totalUsers);
	}

	/** Create the "sent" private message folders*/
	public function step_4($data = array())
	{
		if($this->tableExists('pm') AND $this->tableExists('pmtext'))
		{
			$assertor = vB::getDbAssertor();
			$startat = intval($data['startat']);
			$batchsize = 5000;
			$this->show_message($this->phrase['version']['500a28']['importing_privatemessages_1']);
			$this->show_message(sprintf($this->phrase['core']['processing_records_x'], $batchsize));

			//First see if we need to do something. Maybe we're O.K.
			if (!empty($data['maxvB4']))
			{
				$maxPMTid = $data['maxvB4'];
			}
			else
			{
				$maxPMTid = $assertor->getRow('vBInstall:getMaxPMSenderid', array());
				$maxPMTid = intval($maxPMTid['maxid']);
				//If we don't have any threads, we're done.
				if (intval($maxPMTid) < 1)
				{
					$this->skip_message();
					return;
				}
			}

			if ($startat == 0)
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxPMFolderUser', array('titlephrase' => 'sent_items'));

				if (!empty($maxvB5) AND !empty($maxvB5['maxid']))
				{
					$startat = $maxvB5['maxid'];
				}
			}

			if ($startat >= $maxPMTid)
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}

			$assertor->assertQuery('vBInstall:createPMFoldersSent', array('startat' => $startat, 'batchsize' => $batchsize));
			return array('startat' => ($startat + $batchsize), 'maxvB4' => $maxPMTid);
		}
		else
		{
			$this->skip_message();
		}

	}

	/** Create the "messages" private message folders*/
	public function step_5($data = array())
	{
		if($this->tableExists('pm') AND $this->tableExists('pmtext'))
		{
			$assertor = vB::getDbAssertor();
			$batchsize = 5000;
			$this->show_message($this->phrase['version']['500a28']['importing_privatemessages_2']);
			$this->show_message(sprintf($this->phrase['core']['processing_records_x'], $batchsize));
			$startat = intval($data['startat']);

			//First see if we need to do something. Maybe we're O.K.
			if (!empty($data['maxvB4']))
			{
				$maxPMTid = $data['maxvB4'];
			}
			else
			{
				$maxPMTid = $assertor->getRow('vBInstall:getMaxPMRecipient', array());
				$maxPMTid = intval($maxPMTid['maxid']);
				//If we don't have any threads, we're done.
				if (intval($maxPMTid) < 1)
				{
					$this->skip_message();
					return;
				}
			}

			if ($startat == 0)
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxPMFolderUser', array('titlephrase' => 'messages'));

				if (!empty($maxvB5) AND !empty($maxvB5['maxid']))
				{
					$startat = $maxvB5['maxid'];
				}
			}

			if ($startat >= $maxPMTid)
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}

			$assertor->assertQuery('vBInstall:createPMFoldersMsg', array('startat' => $startat, 'batchsize' => $batchsize));
			return array('startat' => ($startat + $batchsize), 'maxvB4' => $maxPMTid);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Import private messages with no starters */
	public function step_6($data = array())
	{
		if($this->tableExists('pm') AND $this->tableExists('pmtext'))
		{
			$assertor = vB::getDbAssertor();
			$batchsize = 5000;
			$this->show_message($this->phrase['version']['500a28']['importing_privatemessages_3']);
			$this->show_message(sprintf($this->phrase['core']['processing_records_x'], $batchsize));
			$startat = intval($data['startat']);

			//First see if we need to do something. Maybe we're O.K.
			if (!empty($data['maxvB4']))
			{
				$maxPMTid = $data['maxvB4'];
			}
			else
			{
				$maxPMTid = $assertor->getRow('vBInstall:getMaxPMStarter', array());
				$maxPMTid = intval($maxPMTid['maxid']);
				//If we don't have any threads, we're done.
				if (intval($maxPMTid) < 1)
				{
					$this->skip_message();
					return;
				}
			}

			if ($startat == 0)
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxImportedPost', array('contenttypeid' => 9989));

				if (!empty($maxvB5) AND !empty($maxvB5['maxid']))
				{
					$startat = $maxvB5['maxid'];
				}
			}

			if ($startat >= $maxPMTid)
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}
			$nodeLib = vB_Library::instance('node');
			$pmHomeid = $nodeLib->fetchPMChannel();
			$pmHome = $nodeLib->getNode($pmHomeid);
			$assertor->assertQuery('vBInstall:importPMStarter', array('startat' => $startat, 'batchsize' => $batchsize,
			'pmRouteid' => $pmHome['routeid'], 'privatemessageType' => vB_Types::instance()->getContentTypeID('vBForum_PrivateMessage'),
			'privateMessageChannel' => $pmHomeid));
			$assertor->assertQuery('vBInstall:setPMStarter', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9989));
			$assertor->assertQuery('vBInstall:importPMText', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9989));
			$assertor->assertQuery('vBInstall:importPMMessage', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9989));

			$assertor->assertQuery('vBInstall:importPMSent', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9989));
			$assertor->assertQuery('vBInstall:importPMInbox', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9989));
			$assertor->assertQuery('vBInstall:addClosureSelf', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9989));
			$assertor->assertQuery('vBInstall:addClosureParents', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9989));
			$assertor->assertQuery('vBInstall:updateChannelRoutes', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9989));
			return array('startat' => ($startat + $batchsize), 'maxvB4' => $maxPMTid);
		}
		else
		{
			$this->skip_message();
		}
	}


	/** Import private messages with starters*/
	public function step_7($data = array())
	{
		if($this->tableExists('pm') AND $this->tableExists('pmtext'))
		{
			/** Here we iterate for two reasons:
			 The outer loop is for the standard reason- to limit the number of queries and make sure we don't timeout.
			 *
			 * But also- here we are importing a hierarchical structure, which we need to maintain. So if we're importing
			 * pmtextid's 5,000- 10,000, but node 9999 may be a child of 9997 which is a child of 9996, etc.
			 *
			 * Simple example: A sends emails to B and C.
			 * B replies to A and C, C replies to B and A
			 * C replies to B, A replies to B
			 * B replies to A
			 *
			 * Now at each step we record the highest node id, and at the next import query we only want children of
			 * parent nodes higher than that.
			 *
			 * The highest existing pm nodeid is 1000. We run and import A's email
			 * Max existing pmid is now 1001. Second run skips A but imports B's and C's replies
			 * Max existing pmid is now 1003. Third run skips the three imported nodes and imports C's and A's replies
			 * Max existing pmid is now 1005. Fourth run skips the five existing nodes and run imports B's reply
			 * Max existing pmid is now 1006. Fifth run imports nothing, so the updates at the end of the group run and
			 * 	we run the queries at the end.
			 *
			 * Often the parentid will be outside the current block. But since it will have already been imported, and the
			 * range limit is on the child, that won't result in lost data.
			 */

			$assertor = vB::getDbAssertor();
			$batchsize = 2000;
			$this->show_message($this->phrase['version']['500a28']['importing_privatemessages_4']);

			if (isset($data['startat']))
			{
				$startat = $data['startat'];
			}

			//First see if we need to do something. Maybe we're O.K.
			if (!empty($data['maxvB4']))
			{
				$maxPMTid = $data['maxvB4'];
			}
			else
			{
				$maxPMTid = $assertor->getRow('vBInstall:getMaxPMResponse', array());
				$maxPMTid = intval($maxPMTid['maxid']);
				//If we don't have any threads, we're done.
				if (intval($maxPMTid) < 1)
				{
					$this->skip_message();
					return;
				}
			}

			if (!isset($startat))
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxImportedPost', array('contenttypeid' => 9981));

				if (!empty($maxvB5) AND !empty($maxvB5['maxid']))
				{
					$startat = $maxvB5['maxid'];
				}
				else
				{
					$startat = 1;
				}
			}

			if ($startat >= $maxPMTid)
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}

			//See if we have any nodes to import in this block.
			$lastMaxId = 0;
			$processed = array('recs' => 1);
			$processedCount = -1;
			while (!empty($processed) AND !empty($processed['recs']))
			{
				$processedCount += $processed['recs'];
				//We have to see if we have more to import.(empty($maxNode) OR !empty($maxNode['errors']))
				$maxNode = $assertor->getRow('vBInstall:getMaxPMNodeid', array());

				if (empty($maxNode) OR !empty($maxNode['errors']))
				{
					$maxNodeid = 0;
				}
				else
				{
					$maxNodeid = $maxNode['maxid'];
				}
				$assertor->assertQuery('vBInstall:importPMResponse', array('startat' => $startat, 'batchsize' => $batchsize,
					'privatemessageType' => vB_Types::instance()->getContentTypeID('vBForum_PrivateMessage'),
					'maxNodeid' => $lastMaxId));
				$processed = $assertor->getRow('vBInstall:getProcessedCount', array());
				$lastMaxId = $maxNodeid;
			}

			//If we didn't import any records, don't bother to run these queries
			if ($processed > 0)
			{
				$assertor->assertQuery('vBInstall:setResponseStarter', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9981));
				$assertor->assertQuery('vBInstall:importPMText', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9981));
				$assertor->assertQuery('vBInstall:importPMMessage', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9981));

				$assertor->assertQuery('vBInstall:importPMSent', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9981));
				$assertor->assertQuery('vBInstall:importPMInbox', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9981));
				$assertor->assertQuery('vBInstall:addClosureSelf', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9981));
				$assertor->assertQuery('vBInstall:addClosureParents', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9981));
				$this->show_message(sprintf($this->phrase['core']['processing_records_x'], $processedCount));
			}
			return array('startat' => ($startat + $batchsize), 'maxvB4' => $maxPMTid);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Drop plugins column **/
	public function step_8()
	{
		if ($this->field_exists('language', 'phrasegroup_plugins'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'language', 1, 2),
				"ALTER TABLE " . TABLE_PREFIX . "language DROP COLUMN phrasegroup_plugins"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Add hooks column **/
	public function step_9()
	{
		if (!$this->field_exists('language', 'phrasegroup_hooks'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'language', 2, 2),
				"ALTER TABLE " . TABLE_PREFIX . "language ADD COLUMN phrasegroup_hooks MEDIUMTEXT NULL"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Update phrases **/
	public function step_10()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'phrase', 1, 1),
			"UPDATE " . TABLE_PREFIX . "phrase SET fieldname = 'hooks' WHERE fieldname = 'plugins'"
		);
	}

	/** Update phrasetypes **/
	public function step_11()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'phrasetype', 1, 2),
			"DELETE FROM " . TABLE_PREFIX . "phrasetype WHERE fieldname = 'plugins'"
		);
	}

	/** Update phrasetypes **/
	public function step_12()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'phrasetype', 2, 2),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "phrasetype (fieldname, title, editrows) VALUES ('hooks', 'Hooks System', 3)"
		);
	}

	/** Add additional request types.
	 *
	 */
	public function step_13()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'privatemessage', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "privatemessage CHANGE about about ENUM('vote', 'vote_reply', 'rate', 'reply', 'follow', 'vm', 'comment',
				'" . vB_Api_Node::REQUEST_TAKE_OWNER ."',
				'" . vB_Api_Node::REQUEST_TAKE_MODERATOR ."',
				'" . vB_Api_Node::REQUEST_GRANT_OWNER ."',
				'" . vB_Api_Node::REQUEST_GRANT_MODERATOR ."',
				'" . vB_Api_Node::REQUEST_GRANT_MEMBER ."',
				'" . vB_Api_Node::REQUEST_TAKE_MEMBER ."',
				'" . vB_Api_Node::REQUEST_SG_TAKE_OWNER ."',
				'" . vB_Api_Node::REQUEST_SG_TAKE_MODERATOR ."',
				'" . vB_Api_Node::REQUEST_SG_GRANT_OWNER ."',
				'" . vB_Api_Node::REQUEST_SG_GRANT_MODERATOR ."',
				'" . vB_Api_Node::REQUEST_SG_GRANT_MEMBER ."',
				'" . vB_Api_Node::REQUEST_SG_TAKE_MEMBER ."'); "
		);
	}

	public function step_14()
	{
		$this->skip_message();
		return;
	}

	/** make sure we have a social group channel */
	public function step_15()
	{
		if ($this->tableExists('socialgroup') AND $this->tableExists('discussion') AND $this->tableExists('groupmessage'))
		{
			//Make sure we have a session
			vB_Upgrade::createAdminSession();
			$guid = vB_Channel::DEFAULT_SOCIALGROUP_PARENT;
			$assertor = vB::getDbAssertor();
			$existing = $assertor->getRow('vBForum:channel', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'guid' => $guid));
			if (empty($existing) OR !empty($existing['errors']))
			{
				$this->show_message($this->phrase['version']['500a28']['creating_socialgroup_channel']);
				$channelLib = vB_Library::instance('content_channel');
				$data = array('parentid'=> 1, 'oldid' => 2, 'oldcontenttypeid' => 9994, 'guid' => $guid, 'title' => 'Social Group');
				$options = array('skipNotifications' => true, 'skipFloodCheck' => true, 'skipDupCheck' => true);
				$channelId = $channelLib->add($data, $options);
			}
			else
			{
				$this->skip_message();
			}
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Importing Visitor Messages **/
	public function step_16($data = NULL)
	{
		if ($this->tableExists('visitormessage'))
		{
			$assertor = vB::getDbAssertor();
			$batchsize = 5000;
			$this->show_message($this->phrase['version']['500a28']['importing_visitor_messages']);
			$this->show_message(sprintf($this->phrase['core']['processing_records_x'], $batchsize));
			$startat = intval($data['startat']);
			$textTypeid = vB_Types::instance()->getContentTypeID('vBForum_Text');
			$vMTypeid = vB_Types::instance()->getContentTypeID('vBForum_VisitorMessage');

			//First see if we need to do something. Maybe we're O.K.
			if (!empty($data['maxvB4']))
			{
				$max4VM = $data['maxvB4'];
			}
			else
			{
				$max4VM = $assertor->getRow('vBInstall:getMax4VM', array());
				$max4VM = intval($max4VM['maxid']);
				//If we don't have any threads, we're done.
				if (intval($max4VM) < 1)
				{
					$this->skip_message();
					return;
				}
			}

			if ($startat == 0)
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxImportedPost', array('contenttypeid' => $vMTypeid));

				if (!empty($maxvB5) AND !empty($maxvB5['maxid']))
				{
					$startat = $maxvB5['maxid'];
				}
			}

			if ($startat >= $max4VM)
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}

			$nodeLib = vB_Library::instance('node');
			$vmHomeid = $nodeLib->fetchVMChannel();
			$vmHome = $nodeLib->getNode($vmHomeid);
			$assertor->assertQuery('vBInstall:ImportVisitorMessages', array('startat' => $startat, 'batchsize' => $batchsize,
				'vmRouteid' => $vmHome['routeid'], 'visitorMessageType' => $vMTypeid,
				'vmChannel' => $vmHomeid, 'texttypeid' => $textTypeid));
			$assertor->assertQuery('vBInstall:importVMText', array('startat' => $startat, 'batchsize' => $batchsize, 'visitorMessageType' => $vMTypeid));
			$assertor->assertQuery('vBInstall:addClosureSelf', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => $vMTypeid));
			$assertor->assertQuery('vBInstall:addClosureParents', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => $vMTypeid));
			$assertor->assertQuery('vBInstall:updateChannelRoutes', array('contenttypeid' => $vMTypeid, 'startat' => $startat, 'batchsize' => $batchsize));
			$assertor->assertQuery('vBInstall:setStarter', array('contenttypeid' => $vMTypeid, 'startat' => $startat));
			return array('startat' => ($startat + $batchsize), 'maxvB4' => $max4VM);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Importing Albums **/
	public function step_17($data = NULL)
	{
		if ($this->tableExists('album') AND $this->tableExists('attachment')  AND $this->tableExists('filedata'))
		{
			$assertor = vB::getDbAssertor();
			$batchSize = 1000;
			$startat = intval($data['startat']);
			$albumTypeid = vB_Types::instance()->getContentTypeID('vBForum_Album');

			if ($startat == 0)
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxvB5Album', array('albumtypeid' => $albumTypeid));
				$startat = intval($maxvB5['maxid']);
			}

			if (!empty($data['maxvB4']))
			{
				$maxvB4 = intval($data['maxvB4']);
			}
			else
			{
				$maxvB4 = $assertor->getRow('vBInstall:getMaxvB4Album', array());
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
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}

			$this->show_message($this->phrase['version']['500a28']['importing_albums']);
			$albumChannel = vB_Api::instanceInternal('node')->fetchAlbumChannel();
			$album = $assertor->getRow('vBForum:node', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'nodeid' => $albumChannel));

			$assertor->assertQuery('vBInstall:importAlbumNodes',
				array('albumtype' => $albumTypeid, 'startat' => $startat, 'batchsize' => $batchSize,
				'gallerytype' => vB_Types::instance()->getContentTypeID('vBForum_Gallery'),
				'albumChannel' => $albumChannel, 'routeid' => $album['routeid']));

			$assertor->assertQuery('vBInstall:importAlbums2Gallery',
				array('albumtype' => $albumTypeid, 'startat' => $startat, 'batchsize' => $batchSize));

			$assertor->assertQuery('vBInstall:addClosureSelf',
				array('startat' => $startat, 'batchsize' => $batchSize, 'contenttypeid' => $albumTypeid));

			$assertor->assertQuery('vBInstall:addClosureParents',
				array('startat' => $startat, 'batchsize' => $batchSize, 'contenttypeid' => $albumTypeid));

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchSize));
				return array('startat' => ($startat + $batchSize));
			}
		else
		{
			$this->skip_message();
		}
	}

	/** Importing Photos **/
	public function step_18($data = NULL)
	{
		if ($this->tableExists('album') AND $this->tableExists('attachment') AND $this->tableExists('filedata'))
		{
			$assertor = vB::getDbAssertor();
			$batchSize = 5000;
			$startat = intval($data['startat']);
			$photoTypeid = vB_Types::instance()->getContentTypeID('vBForum_Photo');
			$albumTypeid = vB_Types::instance()->getContentTypeID('vBForum_Album');

			if ($startat == 0)
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxImportedPost', array('contenttypeid' => 9986));
				$startat = intval($maxvB5['maxid']);
			}

			if (!empty($data['maxvB4']))
			{
				$maxvB4 = intval($data['maxvB4']);
			}
			else
			{
				$maxvB4 = $assertor->getRow('vBInstall:getMaxvB4Photo', array('albumtype' => $albumTypeid));
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
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}
			$this->show_message($this->phrase['version']['500a28']['importing_photos']);
			$assertor->assertQuery('vBInstall:importPhotoNodes',
				array('albumtype' => $albumTypeid, 'startat' => $startat, 'batchsize' => $batchSize,
					'phototype' => $photoTypeid));

			$assertor->assertQuery('vBInstall:importPhotos2Gallery',
				array('albumtype' => $albumTypeid, 'startat' => $startat, 'batchsize' => $batchSize,
					'gallerytype' => vB_Types::instance()->getContentTypeID('vBForum_Gallery')));

			$assertor->assertQuery('vBInstall:addClosureSelf',
				array('startat' => $startat, 'batchsize' => $batchSize, 'contenttypeid' => 9986));

			$assertor->assertQuery('vBInstall:addClosureParents',
				array('startat' => $startat, 'batchsize' => $batchSize, 'contenttypeid' => 9986));


			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchSize));

			return array('startat' => ($startat + $batchSize));
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Add subscribe message types to about */
	public function step_19()
	{
		if ($this->field_exists('privatemessage', 'about'))
		{
			$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'privatemessage', 1, 1),
				"ALTER TABLE " . TABLE_PREFIX . "privatemessage MODIFY COLUMN about ENUM('vote','vote_reply','rate','reply','follow','vm','comment','owner_to','moderator_to','owner_from','moderator','member', 'member_to', 'subscriber', 'subscriber_to', 'sg_subscriber', 'sg_subscriber_to')"
			);
		}
		else
		{
			$this->skip_message();
		}

		$this->long_next_step();
	}

	/* Change any pagetemplates that use the 50/50 screenlayout to use the 70/30 screenlayout
	*/
	public function step_20()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'pagetemplate', 1, 1),
			"UPDATE " . TABLE_PREFIX . "pagetemplate SET screenlayoutid = 2 WHERE screenlayoutid = 3"
		);
	}

	/**
	 * Remove the 50/50 screenlayout (screenlayout 3)
	 */
	public function step_21()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'screenlayout', 1, 1),
			"DELETE FROM " . TABLE_PREFIX . "screenlayout WHERE screenlayoutid = 3"
		);
	}

	/**
	 * Move default annoucement modules to the top section
	 */
	public function step_22()
	{
		$widgetId = $this->db->query_first("SELECT widgetid FROM " . TABLE_PREFIX . "widget WHERE guid = 'vbulletin-widget_announcement-4eb423cfd6dea7.34930845'");
		$widgetId = (int) $widgetId['widgetid'];

		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'widgetinstance', 1, 1),
			"UPDATE " . TABLE_PREFIX . "widgetinstance SET displaysection = 2 WHERE widgetid = $widgetId"
		);
	}

	/**
	 * Add latest group topics widget to pagetemplates
	 */
	public function step_23()
	{
		$widgetId = $this->db->query_first("
			SELECT widgetid
			FROM " . TABLE_PREFIX . "widget
			WHERE guid = 'vbulletin-widget_sgsidebar-4eb423cfd6dea7.34930861'"
		);

		if (!empty($widgetId['widgetid']))
		{
			$widgetId = $widgetId['widgetid'];
		}
		else
		{
			$this->skip_message();
			return;
		}

		$templateIds = $this->db->query_read("
			SELECT pagetemplateid, guid
			FROM " . TABLE_PREFIX . "pagetemplate
			WHERE guid IN ('vbulletin-4ecbdac93742a5.43676037', 'vbulletin-sgtopic93742a5.43676039', 'vbulletin-sgcatlist93742a5.43676040')
		");

		$records = array();
		$updates = array();
		$viewValues = array(
			'vbulletin-4ecbdac93742a5.43676037' => array('starter_only' => 1),
			'vbulletin-sgtopic93742a5.43676039' => array('view' => 'activity'),
			'vbulletin-sgcatlist93742a5.43676040' => array('starter_only' => 1)
		);
		$defaultVals = array(
			"searchTitle" => "Latest Group Topics", "resultsPerPage" => 60,
			"searchJSON" => array(
				"type" => array("vBForum_Text","vBForum_Poll","vBForum_Gallery","vBForum_Video","vBForum_Link"),
				"channel" => array("param" => "channelid"),
				"sort" => array("relevance" => "desc")
			)
		);

		while ($templateId = $this->db->fetch_array($templateIds))
		{
			$widgetinstanceIds = $this->db->query_read("
				SELECT widgetinstanceid, displaysection, displayorder, widgetid
				FROM " . TABLE_PREFIX . "widgetinstance
				WHERE pagetemplateid = " . $templateId['pagetemplateid'] . "
			");

			// check if we have a widgetinstance...
			$add = true;
			$displayOrder = 0;
			while ($instance = $this->db->fetch_array($widgetinstanceIds))
			{
				if ($instance['widgetid'] == $widgetId)
				{
					$add = false;
				}
				else if ($instance['displaysection'] == 1)
				{
					$displayOrder = $instance['displayorder'];
				}
			}

			if ($add)
			{
				$records[] = array('displayorder' => ($displayOrder + 1), 'widgetid' => $widgetId, 'pagetemplateid' => $templateId['pagetemplateid'], 'templateguid' => $templateId['guid']);
			}
			else
			{
				$updates[] = array('id' => $templateId['pagetemplateid'], 'templateguid' => $templateId['guid']);
			}
		}

		$inserts = array();
		foreach ($records AS $rec)
		{
			$adminConfig = (!empty($viewValues[$rec['templateguid']])) ? $viewValues[$rec['templateguid']] : array();
			$defaultVals['searchJSON'] = array_merge($adminConfig, $defaultVals['searchJSON']);
			$inserts[] = $rec['pagetemplateid'] . ", " . $rec['widgetid'] . ", 1, " . $rec['displayorder'] . ", '" . serialize($defaultVals) . "'";
		}

		// insert if needed
		$counter = 0;
		if (!empty($inserts))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'widgetinstance', 1, 1),
				"INSERT INTO " . TABLE_PREFIX . "widgetinstance
				(pagetemplateid, widgetid, displaysection, displayorder, adminconfig)
				VALUES
				(" . implode("), (", $inserts) . ")
			");
			$counter++;
		}

		// update admin default config if needed
		foreach ($updates AS $value)
		{
			$counter++;
			$tmp = $defaultVals;
			$adminConfig = (!empty($viewValues[$value['templateguid']])) ? $viewValues[$value['templateguid']] : array();
			$tmp['searchJSON'] = array_merge($adminConfig, $tmp['searchJSON']);
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'widgetinstance', $counter, $counter),
				"UPDATE " . TABLE_PREFIX . "widgetinstance
				SET adminconfig = '" . serialize($tmp) . "'
				WHERE widgetid = '" . $widgetId . "' AND pagetemplateid = " . $value['id'] . " AND adminconfig = ''
			");
		}
	}

	/**
	 * Change default Admin CP style 1
	 */
	public function step_24()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'setting', 1, 2),
			"
				UPDATE " . TABLE_PREFIX . "setting
				SET
					value = 'vBulletin_5_Default',
					defaultvalue = 'vBulletin_5_Default'
				WHERE varname = 'cpstylefolder'
			"
		);
	}

	/**
	 * Change default Admin CP style 2
	 */
	public function step_25()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'setting', 2, 2),
			"
				UPDATE " . TABLE_PREFIX . "setting
				SET
					value = 'png',
					defaultvalue = 'png'
				WHERE varname = 'cpstyleimageext'
			"
		);
	}

	/**
	 * Change default Admin CP style 3
	 */
	public function step_26()
	{
		// update all admins to use the new style
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'administrator', 1, 1),
			"
				UPDATE " . TABLE_PREFIX . "administrator
				SET cssprefs = 'vBulletin_5_Default'
				WHERE cssprefs <> ''
			"
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
