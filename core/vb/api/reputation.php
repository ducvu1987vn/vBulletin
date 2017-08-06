<?php

/**
 * vB_Api_Reputation
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Reputation extends vB_Api
{
	/**
	 * @var vB_dB_Assertor
	 */
	protected $assertor;

	protected $nodevotescache = array();
	protected $notVoted = array();
	protected $library = false;

	public function __construct()
	{
		parent::__construct();

		$this->assertor = vB::getDbAssertor();
	}

	/**
	 * Vote a node
	 *
	 * @param int $nodeid Node ID.
	 * @return array New Node info.
	 * @see vB_Api_Node::getNode()
	 * @throws vB_Exception_Api
	 */
	public function vote($nodeid)
	{
		$node = vB_Api::instanceInternal('node')->getNodeFullContent($nodeid);
		$node = $node[$nodeid];

		$this->checkCanUseRep($node);

		$loginuser = &vB::getCurrentSession()->fetch_userinfo();
		if ($node['userid'] == $loginuser['userid'])
		{
			// Can't vote own node
			throw new vB_Exception_Api('reputationownpost');
		}

		$score = $this->fetchReppower($loginuser['userid']);
		// Check if the user has already reputation this node
		$check = $this->assertor->getRow('vBForum:reputation', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'nodeid' => $node['nodeid'],
			'whoadded' => $loginuser['userid'],
		));
		if (!empty($check))
		{
			throw new vB_Exception_Api('reputationsamepost');
		}

		$userinfo = vB_Api::instanceInternal('user')->fetchUserinfo($node['userid']);
		if (!$userinfo['userid'])
		{
			throw new vB_Exception_Api('invalidid', 'User');
		}

		$usergroupcache = vB::getDatastore()->getValue('usergroupcache');
		$bf_ugp_genericoptions = vB::getDatastore()->getValue('bf_ugp_genericoptions');
		if (!($usergroupcache["$userinfo[usergroupid]"]['genericoptions'] & $bf_ugp_genericoptions['isnotbannedgroup']))
		{
			throw new vB_Exception_Api('reputationbanned');
		}

		$usercontext = &vB::getUserContext($loginuser['userid']);
		$vboptions = vB::getDatastore()->getValue('options');
		$userinfo['reputation'] += $score;

		// Determine this user's reputationlevelid.
		$reputationlevelid = $this->assertor->getField('vBForum:reputation_userreputationlevel', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'reputation' => $userinfo['reputation'],
		));

		// init user data manager
		$userdata = new vB_Datamanager_User(NULL, vB_DataManager_Constants::ERRTYPE_STANDARD);
		$userdata->set_existing($userinfo);
		$userdata->set('reputation', $userinfo['reputation']);
		$userdata->set('reputationlevelid', intval($reputationlevelid));
		$userdata->pre_save();

		/*insert query*/
		$this->assertor->assertQuery('vBForum:reputation', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERTIGNORE,
			'nodeid' => $node['nodeid'],
			'reputation' => $score,
			'userid' => $userinfo['userid'],
			'whoadded' => $loginuser['userid'],
			'dateline' => vB::getRequest()->getTimeNow(),
		));
		if ($this->assertor->affected_rows() == 0)
		{
			// attempt Zat a flood!
			throw new vB_Exception_Api('reputationsamepost');
		}

		$userdata->save();

		$condition = array('nodeid' => $nodeid);
		$this->assertor->assertQuery('vBForum:updateNodeVotes', $condition);

		// Sending notifications
		$messageLibrary = vB_Library::instance('Content_Privatemessage');
		$notification = array('about' => vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_RATE,
				'aboutid' => $nodeid, 'userid' => $node['userid']);
		// call sendNotifications instead of add() so that it'll go through the
		// notification settings check in library content->sendNotifications()
		$messageLibrary->sendNotifications(array($notification));

		// Expire node cache so this like displays correctly
		vB_Cache::instance()->allCacheEvent('nodeChg_' . $nodeid);

		$votesCount = $this->assertor->getField('vBForum:node', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::COLUMNS_KEY => array('votes'),
			vB_dB_Query::CONDITIONS_KEY => $condition
		));

		return array('nodeid' => $nodeid, 'votes' => $votesCount);
	}

	/**
	 * Unvote a node
	 *
	 * @param int $nodeid Node ID.
	 * @return array New Node info.
	 * @see vB_Api_Node::getNode()
	 * @throws vB_Exception_Api
	 */
	public function unvote($nodeid)
	{
		$node = vB_Api::instanceInternal('node')->getNodeFullContent($nodeid);
		$node = $node[$nodeid];

		$this->checkCanUseRep($node);

		$loginuser = &vB::getCurrentSession()->fetch_userinfo();
		if ($node['userid'] == $loginuser['userid'])
		{
			// Can't vote own node
			throw new vB_Exception_Api('reputationownpost');
		}

		// Check if the user has already reputation this node
		$existingreputation = $this->assertor->getRow('vBForum:reputation', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'nodeid' => $node['nodeid'],
			'whoadded' => $loginuser['userid'],
		));

		if (!$existingreputation)
		{
			throw new vB_Exception_Api('reputationnovote');
		}

		$userinfo = vB_Api::instanceInternal('user')->fetchUserinfo($node['userid']);
		if (!$userinfo['userid'])
		{
			throw new vB_Exception_Api('invalidid', 'User');
		}

		$usergroupcache = vB::getDatastore()->getValue('usergroupcache');
		$bf_ugp_genericoptions = vB::getDatastore()->getValue('bf_ugp_genericoptions');
		if (!($usergroupcache["$userinfo[usergroupid]"]['genericoptions'] & $bf_ugp_genericoptions['isnotbannedgroup']))
		{
			throw new vB_Exception_Api('reputationbanned');
		}

		$userinfo['reputation'] -= $existingreputation['reputation'];

		// Determine this user's reputationlevelid.
		$reputationlevelid = $this->assertor->getField('vBForum:reputation_userreputationlevel', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'reputation' => $userinfo['reputation'],
		));

		// init user data manager
		$userdata = new vB_Datamanager_User(NULL, vB_DataManager_Constants::ERRTYPE_STANDARD);
		$userdata->set_existing($userinfo);
		$userdata->set('reputation', $userinfo['reputation']);
		$userdata->set('reputationlevelid', intval($reputationlevelid));
		$userdata->pre_save();

		// Delete existing vote
		$this->assertor->assertQuery('vBForum:reputation', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'reputationid' => $existingreputation['reputationid'],
		));

		$userdata->save();

		$condition = array('nodeid' => $nodeid);
		$this->assertor->assertQuery('vBForum:updateNodeVotes', $condition);

		$votesCount = $this->assertor->getField('vBForum:node', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::COLUMNS_KEY => array('votes'),
			vB_dB_Query::CONDITIONS_KEY => $condition
		));

		if ($votesCount == 0)
		{
			// we need to remove the notification
			$notificationInfo = array(
				'about' => vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_RATE,
				'aboutid' => $nodeid,
				'userid' => $node['userid']
			);
			$notification = $this->assertor->getRow('vBForum:fetchNotification', $notificationInfo);

			if ($notification)
			{
				vB_Library::instance('Content_Privatemessage')->deleteMessage($notification['nodeid'], $node['userid']);
			}
		}

		// Expire node cache so this like displays correctly
		vB_Cache::instance()->allCacheEvent('nodeChg_' . $nodeid);

		return array('nodeid' => $nodeid, 'votes' => $votesCount);
	}

	/**
	 * Fetch Reputation Power of an user
	 *
	 * @param int $userid User ID
	 * @return int|mixed|string Reputation Power
	 */
	public function fetchReppower($userid)
	{
		$userinfo = vB_Api::instanceInternal('user')->fetchUserinfo($userid);

		if (!$userinfo['userid'])
		{
			throw new vB_Exception_Api('invalidid', 'User');
		}

		if (!$this->library)
		{
			$this->library = vB_Library::instance('reputation');
		}

		return $this->library->fetchReppower($userinfo);
	}

	/**
	 * Fetch whovoted a node
	 * @param $nodeid
	 * @param $private_message false => return only who voted on that node,
	 * 		  true  => return node voters plus the current loged user link to the voters (subscribed/notsubscribed)
	 *
	 * @return array Array of users. An user is also an array contains userid, username, isadmin, ismoderator
	 */
	public function fetchWhovoted($nodeid, $private_message = false)
	{
		$node = vB_Api::instanceInternal('node')->getNodeFullContent($nodeid);
		$node = $node[$nodeid];

		$this->checkCanUseRep($node);

		if (!vB::getUserContext()->hasPermission('genericpermissions', 'canseewholiked'))
		{
			throw new vB_Exception_Api('no_permission');
		}

		if ($private_message)
		{
			$currentUser = vB::getCurrentSession()->get('userid');
			$users = $this->assertor->getRows('vBForum:reputation_privatemsg_fetchwhovoted', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'nodeid' => $node['nodeid'],
				'currentuser' => $currentUser,
			));
		}
		else
		{
			$users = $this->assertor->getRows('vBForum:reputation_fetchwhovoted', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'nodeid' => $node['nodeid'],
			));
		}

		foreach ($users as &$user)
		{
			$user['ismoderator'] = false;
			$user['isadmin'] = false;
			$userinfo = vB_User::fetchUserinfo($user['userid']);
			$user['usertitle'] = $userinfo['usertitle'];

			$usercontext = &vB::getUserContext($user['userid']);

			if ($usercontext->hasAdminPermission('ismoderator'))
			{
				$user['ismoderator'] = true;
			}
			if ($usercontext->hasAdminPermission('cancontrolpanel'))
			{
				$user['isadmin'] = true;
			}
			$user['avatarurl'] = vB_Api::instanceInternal('user')->fetchAvatar($user['userid']);
			$user['avatarurl'] = $user['avatarurl']['avatarpath'];
			$user['profileurl'] =  vB5_Route::buildUrl('profile|nosession', array('userid'=>$user['userid']));
			$user['musername'] = vB_Api::instanceInternal("user")->fetchMusername($user);
		}

		return $users;
	}

	/**
	 * Fetch vote count for a node
	 * @param int $nodeid Node ID
	 * @return int Vote count
	 */
	public function fetchVotecount($nodeid)
	{
		$node = vB_Api::instanceInternal('node')->getNodeFullContent($nodeid);
		$node = $node[$nodeid];

		$this->checkCanUseRep($node);

		return intval($this->assertor->getField('vBForum:reputation_votecount', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'nodeid' => $node['nodeid'],
		)));
	}

	/**
	 * Supplemental cache for node votes
	 *
	 * @param array $nodeIds A list of Nodes to be checked
	 */
	public function cacheNodeVotes(array $nodeIds)
	{
		foreach ($nodeIds AS $nodeid => $vote)
		{
			if ($vote)
			{
				$this->nodevotescache[$nodeid] = $nodeid;
			}
			else
			{
				$this->notVoted[$nodeid] = $nodeid;
			}
		}
	}

	/**
	 * Check a list of nodes and see whether the user has voted them
	 *
	 * @param array $nodeIds A list of Nodes to be checked
	 * @param int $userid User ID to be checked. If not there, currently logged-in user will be checked.
	 * @return array Node IDs that the user has voted.
	 */
	public function fetchNodeVotes(array $nodeIds, $userid = 0)
	{
		if (!$userid)
		{
			$userid = vB::getCurrentSession()->get('userid');

			// TODO: implement guest votes?
			if ($userid == 0)
			{
				return $nodeIds;
			}
		}

		$nodeIds = array_diff($nodeIds, $this->nodevotescache, $this->notVoted);

		if ($nodeIds)
		{
			$nodes = $this->assertor->assertQuery('vBForum:getNodeVotes', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'userid' => $userid,
					'nodeid' => $nodeIds
				));
			foreach ($nodes as $node)
			{
				$this->nodevotescache[$node['nodeid']] = $node['nodeid'];
				$key = array_search($node['nodeid'], $nodeIds);

				if ($key !== false)
				{
					unset ($nodeIds[$key]);
				}

			}
		}

		//If we have any nodeIds left, those are nodes for which this user has not voted.
		// Let's store that to prevent additional queries.
		if (!empty($nodeIds))
		{
			$this->notVoted = array_merge($this->notVoted, $nodeIds);
		}


		return $this->nodevotescache;
	}

	/**
	 * Fetch reputation image info for displaying it in a node
	 * Ported from vB4's fetch_reputation_image() function
	 *
	 * @param int $userid User ID
	 * @return array Contains 3 items:
	 *               1) type  - image type. Possible values: balance, neg, highneg, pos, highpos, off
	 *               2) level - Reputation level's phrase name
	 *               3) bars  - Number of image bars to be displayed. Maximum 10.
	 */
	public function fetchReputationImageInfo($userid)
	{
		$vboptions = vB::getDatastore()->getValue('options');

		$userinfo = vB_Api::instanceInternal('user')->fetchUserinfo($userid);

		if (!$userinfo['userid'])
		{
			throw new vB_Exception_Api('invalidid', 'User');
		}

		if (!$this->library)
		{
			$this->library = vB_Library::instance('reputation');
		}

		return $this->library->fetchReputationImageInfo($userinfo);
	}

	protected function checkCanUseRep($node)
	{
		$loginuser = &vB::getCurrentSession()->fetch_userinfo();
		if (!$loginuser['userid'])
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		$usercontext = &vB::getUserContext($loginuser['userid']);

		// TODO: Better permission check
//		if (!$usercontext->getChannelPermission('forumpermissions', 'canview', $node['channelid']) OR !$usercontext->getChannelPermission('forumpermissions', 'canviewthreads', $node['channelid']))
//		{
//			throw new vB_Exception_Api('no_permission');
//		}
//		if (!($usercontext->getChannelPermission('forumpermissions', 'canviewothers', $node['channelid']) AND $node['userid'] != $loginuser['userid']))
//		{
//			throw new vB_Exception_Api('no_permission');
//		}
		if (!$usercontext->hasPermission('genericpermissions', 'canuserep'))
		{
			throw new vB_Exception_Api('no_permission');
		}

		$contenttypes = vB_Types::instance()->getContentTypes();

		if (!in_array($node['contenttypeid'], array(
			$contenttypes['vBForum_Text']['id'],
			$contenttypes['vBForum_Gallery']['id'],
			$contenttypes['vBForum_Video']['id'],
			$contenttypes['vBForum_Link']['id'],
			$contenttypes['vBForum_Poll']['id'],
		)))
		{
			// Only allow to vote on above content types.
			throw new vB_Exception_Api('invalid_vote_node');
		}
	}
}
