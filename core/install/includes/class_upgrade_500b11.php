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

class vB_Upgrade_500b11 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500b11';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Beta 11';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Beta 10';

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
	 * add subscribediscussion.oldid
	 * To import subscriptions
	 *
	 */
	public function step_1()
	{
		if ($this->tableExists('subscribediscussion') AND !$this->field_exists('subscribediscussion', 'oldid'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'subscribediscussion', 1, 1),
				'subscribediscussion',
				'oldid',
				'INT',
				array('length' => 10)
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * add subscribediscussion.oldtypeid
	 * To import subscriptions
	 *
	 */
	public function step_2()
	{
		if ($this->tableExists('subscribediscussion') AND !$this->field_exists('subscribediscussion', 'oldtypeid'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'subscribediscussion', 1, 3),
				'subscribediscussion',
				'oldtypeid',
				'INT',
				array('length' => 10)
			);

			$this->drop_index(
				sprintf($this->phrase['core']['altering_x_table'], 'subscribediscussion', 2, 3),
				'subscribediscussion',
				'userdiscussion'
			);

			$this->add_index(
				sprintf($this->phrase['core']['altering_x_table'], 'subscribediscussion', 3, 3),
				'subscribediscussion',
				'userdiscussion_type',
				array('userid', 'discussionid', 'oldtypeid')
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Import group discussion subscriptions
	 */
	public function step_3($data = null)
	{
		if ($this->tableExists('subscribediscussion') AND $this->tableExists('node') AND $this->tableExists('discussion'))
		{
			$startat = intval($data['startat']);
			$assertor = vB::getDbAssertor();
			$discussionTypeid = vB_Types::instance()->getContentTypeID('vBForum_SocialGroupDiscussion');
			$batchsize = 5000;

			if ($startat == 0)
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxImportedSubscription', array(
					'oldtypeid' => $discussionTypeid
				));
				$startat = intval($maxvB5['maxid']);
			}

			if (!empty($data['maxvB4']))
			{
				$maxvB4 = intval($data['maxvB4']);
			}
			else
			{
				$maxvB4 = $assertor->getRow('vBInstall:getMaxGroupDiscussionSubscriptionId', array('discussiontypeid' => $discussionTypeid));
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

			$this->show_message(sprintf($this->phrase['version']['500b11']['importing_x_subscriptions'], 'Group Discussions'));
			$assertor->assertQuery('vBInstall:importDiscussionSubscriptions',
				array('startat' => $startat, 'batchsize' => $batchsize,'discussiontypeid' => $discussionTypeid));

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
			return array('startat' => ($startat + $batchsize));
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Remove no longer needed records
	 */
	public function step_4()
	{
		$this->show_message($this->phrase['core']['may_take_some_time']);
		$this->show_message($this->phrase['version']['500b11']['cleaning_subscribediscussion_table']);
		$assertor = vB::getDbAssertor();
		$discussionTypeid = vB_Types::instance()->getContentTypeID('vBForum_SocialGroupDiscussion');
		$maxvB4 = $assertor->getRow('vBInstall:getMaxGroupDiscussionSubscriptionId', array('discussiontypeid' => $discussionTypeid));
		if ($maxvB4 < 1)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}

		vB::getDbAssertor()->assertQuery('vBInstall:deleteGroupSubscribedDiscussion', array('discussiontypeid' => $discussionTypeid));
		$this->show_message(sprintf($this->phrase['core']['process_done']));
	}

	/**
	 * Import forum subscriptions
	 */
	public function step_5($data = null)
	{
		if ($this->tableExists('subscribediscussion') AND $this->tableExists('node') AND $this->tableExists('forum') AND $this->tableExists('subscribeforum'))
		{
			$startat = intval($data['startat']);
			$assertor = vB::getDbAssertor();
			$forumtypeid = vB_Types::instance()->getContentTypeID('vBForum_Forum');
			$batchsize = 5000;

			if ($startat == 0)
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxImportedSubscription', array(
					'oldtypeid' => $forumtypeid
				));
				$startat = intval($maxvB5['maxid']);
			}

			if (!empty($data['maxvB4']))
			{
				$maxvB4 = intval($data['maxvB4']);
			}
			else
			{
				$maxvB4 = $assertor->getRow('vBInstall:getMaxForumSubscriptionId', array('forumtypeid' => $forumtypeid));
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

			$this->show_message(sprintf($this->phrase['version']['500b11']['importing_x_subscriptions'], 'Forum'));
			$assertor->assertQuery('vBInstall:importForumSubscriptions',
				array('startat' => $startat, 'batchsize' => $batchsize, 'forumtypeid' => $forumtypeid));

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
			return array('startat' => ($startat + $batchsize));
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Import thread subscriptions
	 */
	public function step_6($data = null)
	{
		if ($this->tableExists('subscribediscussion') AND $this->tableExists('node') AND $this->tableExists('thread') AND $this->tableExists('subscribethread'))
		{
			$startat = intval($data['startat']);
			$assertor = vB::getDbAssertor();
			$threadtypeid = vB_Types::instance()->getContentTypeID('vBForum_Thread');
			$batchsize = 5000;

			if ($startat == 0)
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxImportedSubscription', array(
					'oldtypeid' => $threadtypeid
				));
				$startat = intval($maxvB5['maxid']);
			}

			if (!empty($data['maxvB4']))
			{
				$maxvB4 = intval($data['maxvB4']);
			}
			else
			{
				$maxvB4 = $assertor->getRow('vBInstall:getMaxThreadSubscriptionId', array('threadtypeid' => $threadtypeid));
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

			$this->show_message(sprintf($this->phrase['version']['500b11']['importing_x_subscriptions'], 'Thread'));
			$assertor->assertQuery('vBInstall:importThreadSubscriptions',
				array('startat' => $startat, 'batchsize' => $batchsize, 'threadtypeid' => $threadtypeid));

			$this->show_message(sprintf($this->phrase['core']['processed_records_x_y'], $startat + 1, $startat + $batchsize - 1));
			return array('startat' => ($startat + $batchsize));
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Import group subscriptions
	 */
	public function step_7($data = null)
	{
		if ($this->tableExists('subscribediscussion') AND $this->tableExists('node') AND $this->tableExists('socialgroup') AND $this->tableExists('subscribegroup'))
		{
			$startat = intval($data['startat']);
			$assertor = vB::getDbAssertor();
			$grouptypeid = vB_Types::instance()->getContentTypeID('vBForum_SocialGroup');
			$batchsize = 5000;

			if ($startat == 0)
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxImportedSubscription', array(
					'oldtypeid' => $grouptypeid
				));
				$startat = intval($maxvB5['maxid']);
			}

			if (!empty($data['maxvB4']))
			{
				$maxvB4 = intval($data['maxvB4']);
			}
			else
			{
				$maxvB4 = $assertor->getRow('vBInstall:getMaxGroupSubscriptionId', array('grouptypeid' => $grouptypeid));
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

			$this->show_message(sprintf($this->phrase['version']['500b11']['importing_x_subscriptions'], 'Social Group'));
			$assertor->assertQuery('vBInstall:importGroupSubscriptions',
				array('startat' => $startat, 'batchsize' => $batchsize, 'grouptypeid' => $grouptypeid));

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
			return array('startat' => ($startat + $batchsize));
		}
		else
		{
			$this->skip_message();
		}
	}

	public function step_8($data = array())
	{
		if ($this->tableExists('blog_text'))
		{
			$this->show_message(sprintf($this->phrase['vbphrase']['importing_from_x'], 'blog_text'));
			if (empty($data['startat']))
			{
				$startat = 0;
			}
			else
			{
				$startat = $data['startat'];
			}
			$assertor = vB::getDbAssertor();
			$userid = $assertor->getRow('vBInstall:getNextBlogUserid', array('startat' => $startat));

			if (empty($userid) OR !empty($userid['errors']) or empty($userid['userid']))
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}
			$userid = $userid['userid'];

			$maxNodeId = $assertor->getRow('vBInstall:getMaxNodeid', array());
			$maxNodeId = $maxNodeId['maxid'];
			$missingQry = $assertor->assertQuery('vBInstall:getMissedBlogStarters', array('userid' => $userid));

			if (!$missingQry->valid())
			{
				return array('startat' => $userid);
			}

			$blogtextids = array();
			$parentid = 0;

			foreach($missingQry AS $blogInfo)
			{
				$blogtextids[] = $blogInfo['blogtextid'];

				if (!$parentid)
				{
					$parentid = $blogInfo['nodeid'];
					$routeid =  $blogInfo['routeid'];
				}
			}
			$texttype = vB_Types::instance()->getContentTypeID('vBForum_Text');
			$assertor->assertQuery('vBInstall:importMissingBlogStarters',
				array('texttype' => $texttype, 'parentid' => $parentid, 'blogtextids' => $blogtextids, 'routeid' => $routeid));
			$reccount = $assertor->getRow('vBInstall:getProcessedCount', array());

			if (empty($reccount) OR !empty($reccount['errors']) or empty($reccount['recs']))
			{
				return array('startat' => $userid);
			}

			$assertor->assertQuery('vBInstall:fixMissingBlogStarter', array('startnodeid' => $maxNodeId));
			$assertor->assertQuery('vBInstall:importMissingBlogResponses',
				array('texttype' => $texttype, 'blogtextids' => $blogtextids));
			$assertor->assertQuery('vBInstall:importMissingBlogText', array('startnodeid' => $maxNodeId));
			$assertor->assertQuery('vBInstall:createMissingBlogClosureSelf', array('startnodeid' => $maxNodeId));
			$assertor->assertQuery('vBInstall:createMissingBlogClosurefromParent', array('startnodeid' => $maxNodeId,
				'oldcontenttypeid' => 9985));
			$assertor->assertQuery('vBInstall:createMissingBlogClosurefromParent', array('startnodeid' => $maxNodeId,
				'oldcontenttypeid' => 9984));

			return array('startat' => $userid);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**  Fix the blog starter counts */
	public function step_9()
	{
		if ($this->tableExists('blog_text'))
		{
			$assertor = vB::getDbAssertor();
			$this->show_message(sprintf($this->phrase['version']['500b11']['updating_blog_summary_step_x'], 1));

			$assertor->assertQuery('vBInstall:fixBlogStarterLast', array());
		}
		else
		{
			$this->skip_message();
		}
	}

	/**  Fix the blog counts */
	public function step_10()
	{
		if ($this->tableExists('blog_text'))
		{
			$assertor = vB::getDbAssertor();
			$this->show_message(sprintf($this->phrase['version']['500b11']['updating_blog_summary_step_x'], 2));

			$assertor->assertQuery('vBInstall:fixBlogChannelCount', array());
		}
		else
		{
			$this->skip_message();
		}
	}

	/**  Fix the blog last date */
	public function step_11()
	{
		if ($this->tableExists('blog_text'))
		{
			$assertor = vB::getDbAssertor();
			$this->show_message(sprintf($this->phrase['version']['500b11']['updating_blog_summary_step_x'], 3));

			$assertor->assertQuery('vBInstall:fixBlogChannelLast', array());
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Update Channel counts**/
	public function step_12($data = NULL)
	{
		//Here we run until we aren't changing anything. In essence each time we run we ascend one time up the hierarchy.
		$assertor = vB::getDbAssertor();
		$startat = intval($data['startat']);

		if ($startat > 10)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}

		$this->show_message(sprintf($this->phrase['version']['500b11']['correcting_channel_counts'], TABLE_PREFIX . 'node'));
		$assertor->assertQuery('vBInstall:updateChannelCounts',
			array('channelTypeid' => vB_Types::instance()->getContentTypeID('vBForum_Channel'),
				'textTypeid' => vB_Types::instance()->getContentTypeID('vBForum_Text'),
				'pollTypeid' =>  vB_Types::instance()->getContentTypeID('vBForum_Poll')));
		$processed = $assertor->getRow('vBInstall:getProcessedCount', array());
		$processed = $processed['recs'];
		if (empty($processed))
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}

		return array('startat' => ($startat + 1));
	}

	/**
	 * Import user's blog subscriptions
	 */
	public function step_13($data = null)
	{
		if ($this->tableExists('subscribediscussion') AND $this->tableExists('node') AND $this->tableExists('blog_subscribeuser'))
		{
			$startat = intval($data['startat']);
			$assertor = vB::getDbAssertor();
			$channeltypeid = vB_Types::instance()->getContentTypeID('vBForum_Channel');
			$membergid = $assertor->getRow('usergroup', array(
				'systemgroupid' => vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID
			));
			$groupid = $membergid['usergroupid'];
			$batchsize = 5000;

			if ($startat == 0)
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxImportedBlogUserSubscriptionId', array(
					'channeltypeid' => $channeltypeid,
					'membergroupid' => $groupid
				));
				$startat = intval($maxvB5['maxid']);
			}

			if (!empty($data['maxvB4']))
			{
				$maxvB4 = intval($data['maxvB4']);
			}
			else
			{
				$maxvB4 = $assertor->getRow('vBInstall:getMaxBlogUserSubscriptionId', array('channeltypeid' => $channeltypeid));
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

			$this->show_message(sprintf($this->phrase['version']['500b11']['importing_x_subscriptions'], 'Blog User'));
			$assertor->assertQuery('vBInstall:importBlogUserSubscriptions',
				array('startat' => $startat, 'batchsize' => $batchsize,'channeltypeid' => $channeltypeid, 'membergroupid' => $groupid));

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
			return array('startat' => ($startat + $batchsize));
		}
		else
		{
			$this->skip_message();
		}
	}
	
	/**
	 * Import blog entries subscriptions
	 */
	public function step_14($data = null)
	{
		if ($this->tableExists('subscribediscussion') AND $this->tableExists('node') AND $this->tableExists('blog_subscribeentry'))
		{
			$startat = intval($data['startat']);
			$assertor = vB::getDbAssertor();
			$batchsize = 5000;
			if ($startat == 0)
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxImportedSubscription', array(
					'oldtypeid' => 9985
				));
				$startat = intval($maxvB5['maxid']);
			}

			if (!empty($data['maxvB4']))
			{
				$maxvB4 = intval($data['maxvB4']);
			}
			else
			{
				$maxvB4 = $assertor->getRow('vBInstall:getMaxBlogEntrySubscriptionId');
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

			$this->show_message(sprintf($this->phrase['version']['500b11']['importing_x_subscriptions'], 'Blog Entries'));
			$assertor->assertQuery('vBInstall:importBlogEntrySubscriptions',
				array('startat' => $startat, 'batchsize' => $batchsize, 'blogentryid' => 9985));

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
			return array('startat' => ($startat + $batchsize));
		}
		else
		{
			$this->skip_message();
		}
	}

	/**Add an enumerated value to private message types **/
	public function step_15()
	{
		vB_Upgrade::createAdminSession();
		$types = "'" . implode("','", array_merge(vB_Library::instance('content_privatemessage')->fetchNotificationTypes(),
				vB_Library::instance('content_privatemessage')->getChannelRequestTypes())) .  "'";
		$this->run_query(

				sprintf($this->phrase['core']['altering_x_table'], 'privatemessage', 1, 1),
				"ALTER TABLE " . TABLE_PREFIX . "privatemessage CHANGE about about ENUM($types); "
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/