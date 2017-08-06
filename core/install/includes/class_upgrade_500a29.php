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

class vB_Upgrade_500a29 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a29';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 29';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 28';

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

	/**We need an index ont node(oldid)
	 * **/
	public function step_1()
	{
		$this->skip_message();
	}
	/** Import social group categories**/
	public function step_2()
	{
		if ($this->tableExists('socialgroup') AND $this->tableExists('discussion') AND $this->tableExists('groupmessage'))
		{
			$this->show_message($this->phrase['version']['500a28']['importing_socialgroup_categories']);
			$assertor = vB::getDbAssertor();
			$categories = $assertor->assertQuery('vBInstall:getMissingGroupCategories', array());
			vB_Upgrade::createAdminSession();
			$channelLib = vB_Library::instance('content_channel');
			$sgChannel = vB_Api::instanceInternal('socialgroup')->getSGChannel();
			foreach ($categories as $category)
			{
				$channel = array('parentid' => $sgChannel, 'oldid' => $category['groupid'],
					'title' => $category['title'], 'description' =>  $category['description'],
					'urlident' => $channelLib->getUniqueUrlIdent($category['title']),
					'oldid' => $category['socialgroupcategoryid'], 'oldcontenttypeid' => 9988);
				$nodeid = $channelLib->add($channel , array('skipNotifications' => true, 'skipFloodCheck' => true, 'skipDupCheck' => true));
			}
		}
		else
		{
			$this->skip_message();
		}
	}


	/** Import social groups **/
	public function step_3($data = null)
	{
		if ($this->tableExists('socialgroup') AND $this->tableExists('discussion') AND $this->tableExists('groupmessage'))
		{
			$startat = intval($data['startat']);
			$assertor = vB::getDbAssertor();
			$process = 200;
			$oldContentType = vB_Types::instance()->getContentTypeID('vBForum_SocialGroup');
			$countQry = $assertor->getRow('vBInstall:getSocialGroupsCount', array('socialgroupType' => $oldContentType));
			$total = $countQry['total'];

			if (!$total AND !$startat)
			{
				$this->skip_message();
				return;
			}
			else if (!$total)
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}
			else if (!$startat)
			{
				$this->show_message($this->phrase['version']['500a28']['importing_socialgroups']);
				$this->show_message(sprintf($this->phrase['core']['processing_records_x'], $total));
				return array('startat' => 1); // Go back and actually process
			}

			$batchSize = min($total, $process);
			vB_Upgrade::createAdminSession();
			$groups = $assertor->assertQuery('vBInstall:getMissingSocialGroups', array('socialgroupType' => $oldContentType, 'batch_size' => $batchSize));
			$contentLib = vB_Library::instance('content_channel');
			$uncategorized = $assertor->getRow('vBForum:channel', array('guid' => vB_Channel::DEFAULT_UNCATEGORIZEDGROUPS_PARENT));
			foreach ($groups as $group)
			{
				$data = array('parentid' => $group['categoryid'], 'oldid' => $group['groupid'],
				'oldcontenttypeid' => $oldContentType, 'title' => $group['name'], 'description' => $group['description']);

				if (intval($group['transferuserid']))
				{
					$data['userid'] = $group['transferuserid'];
					$data['authorname'] = $group['transferusername'];
				}
				else
				{
					$data['userid'] = $group['userid'];
					$data['authorname'] = $group['username'];
				}

				if (empty($group['routeid']))
				{
					$data['parentid'] = $uncategorized['nodeid'];
				}
				$data['urlident'] = $contentLib->getUniqueUrlIdent($group['title']);

				$nodeid = $contentLib->add($data, array('skipNotifications' => true, 'skipFloodCheck' => true, 'skipDupCheck' => true));

				// @TODO translate old group options to the new nodeoptions
				// import the group type properly

				$updates = array();
				switch ($group['type'])
				{
					case 'public':
						$updates = array('approve_membership' => 1, 'invite_only' => 0);
						break;
					case 'moderated':
						$updates = array('approve_membership' => 0, 'invite_only' => 0);
						break;
					case 'inviteonly':
						$updates = array('approve_membership' => 0, 'invite_only' => 1);
						break;
				}

				vB_Api::instanceInternal('node')->setNodeOptions($nodeid, $updates);
			}

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchSize));
			if ($total < $process)
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
			}
			else
			{
				return array('startat' => ($startat + 1));
			}
		}
		else
		{
			$this->skip_message();
		}
	}



	/** assign group owners  **/
	public function step_4()
	{
		if($this->tableExists('socialgroup') AND $this->tableExists('discussion') AND $this->tableExists('groupmessage'))
		{
			$assertor = vB::getDbAssertor();
			$oldContentType = vB_Types::instance()->getContentTypeID('vBForum_SocialGroup');
			vB_Api::instanceInternal('usergroup')->fetchUsergroupList(true);
			//we just added the new usergroups and the usergroup cache hasn't been rebuilt. So we can't use it.
			$group = $assertor->getRow('usergroup', array('systemgroupid' => vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID));
			$this->show_message($this->phrase['version']['500a28']['assigning_group_owners']);
			$assertor->assertQuery('vBInstall:addGroupOwners', array('groupid' => $group['usergroupid'], 'socialgroupType' => $oldContentType));
		}
		else
		{
			$this->skip_message();
		}

	}

	/** assign group members */
	public function step_5()
	{
		if($this->tableExists('socialgroup') AND $this->tableExists('discussion') AND $this->tableExists('groupmessage'))
		{
			$assertor = vB::getDbAssertor();
			$oldContentType = vB_Types::instance()->getContentTypeID('vBForum_SocialGroup');
			$group = $assertor->getRow('usergroup', array('systemgroupid' => vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID));
			$this->show_message($this->phrase['version']['500a28']['assigning_group_members']);
			$assertor->assertQuery('vBInstall:addGroupMembers', array('groupid' => $group['usergroupid'], 'socialgroupType' => $oldContentType));
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Import discussions **/
	public function step_6($data = null)
	{
		if ($this->tableExists('socialgroup') AND $this->tableExists('discussion') AND $this->tableExists('groupmessage'))
		{
			$startat = intval($data['startat']);
			$assertor = vB::getDbAssertor();
			$groupTypeid = vB_Types::instance()->getContentTypeID('vBForum_SocialGroup');
			$discussionTypeid = vB_Types::instance()->getContentTypeID('vBForum_SocialGroupDiscussion');
			$batchsize = 2000;

			if ($startat == 0)
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxImportedPost', array('contenttypeid' => $discussionTypeid));
				$startat = intval($maxvB5['maxid']);
			}

			if (!empty($data['maxvB4']))
			{
				$maxvB4 = intval($data['maxvB4']);
			}
			else
			{
				$maxvB4 = $assertor->getRow('vBInstall:getMaxSGDiscussionID', array());
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

			$this->show_message($this->phrase['version']['500a28']['importing_discussions']);
			$assertor->assertQuery('vBInstall:importSGDiscussions',
				array('textTypeid' =>  vB_Types::instance()->getContentTypeID('vBForum_Text'),
				'startat' => $startat, 'batchsize' => $batchsize,'discussionTypeid' => $discussionTypeid,
				'grouptypeid' => $groupTypeid));

			$assertor->assertQuery('vBInstall:importSGDiscussionText',
				array('textTypeid' =>  vB_Types::instance()->getContentTypeID('vBForum_SocialGroupDiscussion'),
					'startat' => $startat, 'batchsize' => $batchsize,'discussionTypeid' => $discussionTypeid,
					'grouptypeid' => $groupTypeid));

			$assertor->assertQuery('vBInstall:addClosureSelf',
				array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => $discussionTypeid));

			$assertor->assertQuery('vBInstall:addClosureParents',
				array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => $discussionTypeid));
			$assertor->assertQuery('vBInstall:setPMStarter',
				array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => $discussionTypeid));
			$assertor->assertQuery('vBInstall:updateChannelRoutes',
				array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => $discussionTypeid));

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
			return array('startat' => ($startat + $batchsize));

		}
		else
		{
		$this->skip_message();
		}
	}

	/** Import Group Messages **/
	public function step_7($data = NULL)
	{
		if ($this->tableExists('socialgroup') AND $this->tableExists('discussion') AND $this->tableExists('groupmessage'))
		{
			$messageTypeid = vB_Types::instance()->getContentTypeID('vBForum_SocialGroupMessage');
			$discussionTypeid = vB_Types::instance()->getContentTypeID('vBForum_SocialGroupDiscussion');
			$assertor = vB::getDbAssertor();
			$batchsize = 2000;
			$startat = intval($data['startat']);

			if ($startat == 0)
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxImportedPost', array('contenttypeid' => $messageTypeid));
				$startat = intval($maxvB5['maxid']);
			}

			if (!empty($data['maxvB4']))
			{
				$maxvB4 = intval($data['maxvB4']);
			}
			else
			{
				$maxvB4 = $assertor->getRow('vBInstall:getMaxSGPost', array());
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

			$this->show_message($this->phrase['version']['500a28']['importing_discussions']);
			$assertor->assertQuery('vBInstall:importSGPosts',
				array('textTypeid' =>  vB_Types::instance()->getContentTypeID('vBForum_Text'),
					'startat' => $startat, 'batchsize' => $batchsize,'discussionTypeid' => $discussionTypeid,
					'messageTypeid' => $messageTypeid));

			$assertor->assertQuery('vBInstall:importSGPostText',
				array('textTypeid' =>  vB_Types::instance()->getContentTypeID('vBForum_SocialGroupDiscussion'),
					'startat' => $startat, 'batchsize' => $batchsize,'discussionTypeid' => $messageTypeid,
					'messageTypeid' => $messageTypeid));

			$assertor->assertQuery('vBInstall:addClosureSelf',
				array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => $messageTypeid));

			$assertor->assertQuery('vBInstall:addClosureParents',
				array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => $messageTypeid));

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
			return array('startat' => ($startat + $batchsize));
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Create Gallery from SG **/
	public function step_8($data = null)
	{
		if ($this->tableExists('socialgroup') AND $this->tableExists('node') AND $this->tableExists('attachment') AND $this->tableExists('gallery'))
		{
			$startat = intval($data['startat']);
			$assertor = vB::getDbAssertor();
			$groupTypeid = vB_Types::instance()->getContentTypeID('vBForum_SocialGroup');
			$galleryTypeid = vB_Types::instance()->getContentTypeID('vBForum_Gallery');
			$batchsize = 2000;
			if ($startat == 0)
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxImportedPost', array(
					'contenttypeid' => 9983
				));
				$startat = intval($maxvB5['maxid']);
			}

			if (!empty($data['maxvB4']))
			{
				$maxvB4 = intval($data['maxvB4']);
			}
			else
			{
				$maxvB4 = $assertor->getRow('vBInstall:getMaxSGGallery', array('grouptypeid' => $groupTypeid));
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

			$this->show_message($this->phrase['version']['500a29']['importing_socialgroup_galleries']);
			$assertor->assertQuery('vBInstall:importSGGalleryNode',
				array('gallerytypeid' =>  $galleryTypeid,
				'startat' => $startat, 'batchsize' => $batchsize, 'grouptypeid' => $groupTypeid
			));

			$assertor->assertQuery('vBInstall:importSGGallery',
				array('startat' => $startat, 'batchsize' => $batchsize, 'grouptypeid' => $groupTypeid,
					'caption' => $this->phrase['version']['500a29']['imported_socialgroup_galleries']
			));
			$assertor->assertQuery('vBInstall:importSGText',
				array('startat' => $startat, 'batchsize' => $batchsize, 'grouptypeid' => $groupTypeid,
					'caption' => $this->phrase['version']['500a29']['imported_socialgroup_galleries']
				));

			$assertor->assertQuery('vBInstall:addClosureSelf',
				array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9983));

			$assertor->assertQuery('vBInstall:addClosureParents',
				array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9983));
			$assertor->assertQuery('vBInstall:setPMStarter',
				array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9983));
			$assertor->assertQuery('vBInstall:updateChannelRoutes',
				array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9983));

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
			return array('startat' => ($startat + $batchsize));
		}
	}

	/** Create Gallery Post from SG Photos **/
	public function step_9($data = null)
	{
		if ($this->tableExists('socialgroup') AND $this->tableExists('node') AND $this->tableExists('attachment') AND $this->tableExists('photo'))
		{
			$startat = intval($data['startat']);
			$assertor = vB::getDbAssertor();
			$groupTypeid = vB_Types::instance()->getContentTypeID('vBForum_SocialGroup');
			$photoTypeid = vB_Types::instance()->getContentTypeID('vBForum_Photo');
			$batchsize = 2000;

			if ($startat == 0)
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxImportedPost', array(
					'contenttypeid' => 9987
				));
				$startat = intval($maxvB5['maxid']);
			}

			if (!empty($data['maxvB4']))
			{
				$maxvB4 = intval($data['maxvB4']);
			}
			else
			{
				$maxvB4 = $assertor->getRow('vBInstall:getMaxSGPhotoID', array('grouptypeid' => $groupTypeid));
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
			$assertor->assertQuery('vBInstall:importSGPhotoNodes',
				array('startat' => $startat, 'batchsize' => $batchsize,'phototypeid' => $photoTypeid,
				'grouptypeid' => $groupTypeid));

			$assertor->assertQuery('vBInstall:importSGPhotos',
				array('textTypeid' =>  vB_Types::instance()->getContentTypeID('vBForum_SocialGroupDiscussion'),
					'startat' => $startat, 'batchsize' => $batchsize, 'grouptypeid' => $groupTypeid));

			$assertor->assertQuery('vBInstall:addClosureSelf',
				array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9987));

			$assertor->assertQuery('vBInstall:addClosureParents',
				array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9987));

			$assertor->assertQuery('vBInstall:fixLastGalleryData',
				array('startat' => $startat, 'batchsize' => $batchsize
			));

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
			return array('startat' => ($startat + $batchsize));
		}
		else
		{
			$this->skip_message();
		}
	}

	public function step_10()
	{
		$this->skip_message();
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'channel'),
			"UPDATE " . TABLE_PREFIX . "channel AS c SET c.category = 0;"
		);
	}

	/* Add widgetid index */
	function step_11()
	{
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'widgetid', TABLE_PREFIX . 'widgetdefinition'),
			'widgetdefinition',
			'widgetid',
			array('widgetid')
		);
	}

	/**Add an enumerated value to private message types **/
	public function step_12()
	{
		vB_Upgrade::createAdminSession();
		$types = "'" . implode("','", array_merge(vB_Library::instance('content_privatemessage')->fetchNotificationTypes(),
		vB_Library::instance('content_privatemessage')->getChannelRequestTypes())) .  "'";
		$this->run_query(

			sprintf($this->phrase['core']['altering_x_table'], 'privatemessage', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "privatemessage CHANGE about about ENUM($types); "
		);
	}


	public function step_13()
	{
		$this->skip_message();
	}

	public function step_14()
	{
			$bf_ugp = vB::getDatastore()->getValue('bf_ugp_adminpermissions');
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'usergroup'),
				"UPDATE " . TABLE_PREFIX . "usergroup AS usergroup SET adminpermissions = adminpermissions | " . $bf_ugp['canadminstyles'] . " WHERE usergroupid = 6;"
			);
	}


	/* Add userid index to node table */
	function step_15()
	{
		$this->skip_message();
	}

	/** Set category field based on cancontainthreads forum options only for imported channels **/
	public function step_16()
	{
		if ($this->tableExists('forum'))
		{
			// Forum options were imported in 500a23 step 5
			$options = vB::getDatastore()->getValue('bf_misc_forumoptions');
			$forumType = vB_Types::instance()->getContentTypeID('vBForum_Forum');
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'channel'),
				"UPDATE " . TABLE_PREFIX . "channel c
				INNER JOIN " . TABLE_PREFIX . "node n ON n.nodeid = c.nodeid
				INNER JOIN " . TABLE_PREFIX . "forum f ON f.forumid = n.oldid AND oldcontenttypeid = $forumType
				SET c.category = if(f.options & {$options['cancontainthreads']}, 0, 1)
				WHERE c.category = 0;"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Update the modcp link in the footer **/
	public function step_17()
	{
		$assertor = vB::getDbAssertor();
		//TODO: This whould need to change (siteid) when multiple site will be supported
		$queryParams = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array(array('field' => 'siteid', 'value' => 1)));
		$footer = $assertor->getRow('vBForum:site', $queryParams);

		$footernavbar = unserialize($footer['footernavbar']);

		foreach ($footernavbar as $key => $item)
		{
			if ($item['url'] == 'modcp')
			{
				$item['url'] = 'modcp/';
				$footernavbar[$key] = $item;
			}
		}

		$footernavbar = serialize($footernavbar);

		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'site'));
		$queryParams = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE, vB_dB_Query::CONDITIONS_KEY =>
				array(array('field' => 'siteid', 'value' => 1)), 'footernavbar' => $footernavbar);
		$assertor->assertQuery('vBForum:site', $queryParams);
	}

	/* Add fulltext index on phrase table */
	function step_18()
	{
		if ($this->tableExists('phrase'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'phrase', 1, 1),
				"ALTER TABLE " . TABLE_PREFIX . "phrase ENGINE=MYISAM"
			);

			if ($this->field_exists('phrase', 'text'))
			{
				$this->add_index(
					sprintf($this->phrase['version']['380a2']['fulltext_index_on_x'], TABLE_PREFIX . 'phrase'),
					'phrase',
					'pt_ft',
					array('text'),
					'fulltext'
				);
			}
			else
			{
				$this->skip_message();
			}
		}
	}

	/**
	 * Update lastcontentid data for socialgroups
	 *
	 */
	function step_19($data = NULL)
	{
		$messageTypeid = vB_Types::instance()->getContentTypeID('vBForum_SocialGroupMessage');
		$discussionTypeid = vB_Types::instance()->getContentTypeID('vBForum_SocialGroupDiscussion');
		$batchsize = 10000;
		$startat = intval($data['startat']);
		$assertor = vB::getDbAssertor();

		$maxvB5 = $assertor->getRow('vBInstall:getMaxImportedPost', array('contenttypeid' => $messageTypeid));
		$maxvB5 = intval($maxvB5['maxid']);

		if ($startat == 0)
		{
			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'));
		}
		else if ($startat >= $maxvB5)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}
		$assertor->assertQuery('vBInstall:updateDiscussionLastContentId', array('messageTypeid' => $messageTypeid,
			'discussionTypeid' => $discussionTypeid,'startat' => $startat, 'batchsize' => $batchsize));
		$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
		return array('startat' => $startat + $batchsize);
	}

	/** Update Last data for non-category channels **/
	public function step_20($data = NULL)
	{
		$channelTypeid = vB_Types::instance()->getContentTypeID('vBForum_Channel');
		$assertor = vB::getDbAssertor();
		$batchsize = 40000;
		$startat = intval($data['startat']);

		$maxNodeid = $assertor->getRow('vBInstall:getMaxNodeid', array());
		$maxNodeid = intval($maxNodeid['maxid']);

		if ($maxNodeid < $startat)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}

		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'));
		$assertor->assertQuery('vBInstall:updateChannelLast',
			array('channeltypeid' =>  $channelTypeid,
				'startat' => $startat, 'batchsize' => $batchsize));

		$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
		return array('startat' => ($startat + $batchsize));
	}


	/** Update Last data for category channels **/
	public function step_21($data = NULL)
	{
		$channelTypeid = vB_Types::instance()->getContentTypeID('vBForum_Channel');
		$assertor = vB::getDbAssertor();
		$batchsize = 40000;
		$startat = intval($data['startat']);

		if (!empty($data['maxvB4']))
		{
			$maxvB4 = intval($data['maxvB4']);
		}
		else
		{
			$maxvB4 = $assertor->getRow('vBInstall:getMaxNodeid', array());
		}

		if ($maxvB4 <= $startat)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}

		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'));
		$assertor->assertQuery('vBInstall:updateCategoryLast',
			array('channeltypeid' =>  $channelTypeid));
	}


	// There was a step 22- Update Channel counts. But this was not quite right so we corrected it and moved to beta 11.

}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
