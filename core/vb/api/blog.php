<?php if (!defined('VB_ENTRY')) die('Access denied.');
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

/**
 * vB_Api_Blog
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Blog extends vB_Api
{
	protected $blogChannel = false;
	protected $db;
	protected $library;

	protected function __construct()
	{
		parent::__construct();
		$this->assertor = vB::getDbAssertor();
		$this->library = vB_Library::instance('blog');
	}

	public function createBlog($input)
	{
		$this->canCreateBlog($this->getBlogChannel());
		return $this->createChannel($input, $this->getBlogChannel(), vB_Page::getBlogConversPageTemplate(), vB_Page::getBlogChannelPageTemplate(), vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID);
	}

	protected function createChannel($input, $channelid, $channelConvTemplateid, $channelPgTemplateId, $ownerSystemGroupId)
	{
		// Check user is logged in
		$currentSession = vB::getCurrentSession();
		$userid = $currentSession->get('userid');
		$userid = intval($userid);
		if (!$channelid)
		{
			$channelid = $this->getBlogChannel();
		}

		if ($userid <= 0 || !vB::getUserContext()->getChannelPermission('createpermissions', 'vBForum_Channel', $channelid))
		{
			throw new vB_Exception_Api('no_permission');
		}

		// Check input is valid
		$errors = array();

		$input['title'] = isset($input['title']) ? trim($input['title']) : '';
		$input['description'] = isset($input['description']) ? trim($input['description']) : '';
		$input['parentid'] = $channelid;

		if (empty($input['title']))
		{
			if (isset($this->sgChannel))
			{
				$errors[] = 'content_no_title';
			}
			else //For Blogs, blank title should default to <username>'s Blog
			{
				$userInfo = $currentSession->fetch_userinfo();
				$input['title'] = (string) new vB_Phrase('global', 'x_blog', $userInfo['username']);
			}
		}

		if (isset($input['filedataid']) AND
			!vB::getUserContext()->getChannelPermission('forumpermissions', 'canuploadchannelicon', $input['parentid']))
		{
			unset($input['filedataid']);
		}

		//blank title may have been auto-filled for Blog, so let's check for title again
		if (!empty($input['title']))
		{
			if (empty($input['urlident']))
			{
				$input['urlident'] = $this->toSeoFriendly($input['title']);
			}

			// verify prefixes do not collide
			$newPrefix = vB5_Route_Channel::createPrefix($channelid, $input['urlident'], true);
			if (vB5_Route::isPrefixUsed($newPrefix) !== FALSE)
			{
				$errors[] = (isset($this->sgChannel)) ? 'sg_title_exists' : 'blog_title_exists';
			}
		}

		//Product says description is not required for Blogs
		if (empty($input['description']) AND isset($this->sgChannel))
		{
			$errors[] = 'content_no_description';
		}

		if (!empty($errors))
		{
			$e = new vB_Exception_Api();
			foreach ($errors as $error)
			{
				$e->add_error($error);
			}

			throw $e;
		}

		vB_Api::instanceInternal('content_channel')->cleanInput($input, $channelid);
		return $this->library->createChannel($input, $channelid, $channelConvTemplateid, $channelPgTemplateId, $ownerSystemGroupId);
	}

	/**
	 * @uses fetch the id of the global Blog Channel
	 * @return int nodeid of actual Main Blog Channel
	 */
	public function getBlogChannel()
	{
		return $this->library->getBlogChannel();
	}

	/**
	 * Determines if the given node is a blog-related node (blog entry).
	 *
	 * @param	int	$nodeid
	 * @return	bool
	 */
	public function isBlogNode($nodeId, $node = false)
	{
		$nodeId = (int) $nodeId;

		if ($nodeId < 0)
		{
			return false;
		}

		$blogChannelId = (int) $this->getBlogChannel();

		if (empty($node))
		{
			$node = vB_Library::instance('node')->getNode($nodeId, true, false);
		}

		if (empty($node['parents']))
		{
			$parents = vB_Library::instance('node')->getParents($nodeId);
			foreach ($parents as $parent)
			{
				if ($parent['nodeid'] == $blogChannelId)
				{
					return true;
				}
			}
			return false;
		}
		return in_array($blogChannelId, $node['parents']);
	}

	/**
  * @uses Get info on every Blog Channel
  * @param mixed same parameter $options as listNodeContent from node API
  * @return mixed array containing the blog channel info we need
	 */
	public function getBlogInfo($from = 1, $perpage = 20)
	{
		$response = array();
		$nodeApi = vB_Api::instanceInternal('node');
		$blogParentChannel = $this->getBlogChannel();
		$channelContentType  = vB_Types::instance()->getContentTypeId('vBForum_Channel');

		//Get base data
		$options = array('channel' => $blogParentChannel, 'depth' => 1, 'type' => 'vBForum_Channel', 'nolimit' => 1);
		$nodeContent = vB_Api::instanceInternal('search')->getInitialResults($options, $perpage, $from);
		//We need the nodeids to collect some data
		$lastids = array();
		$channelids = array();

		$pageKeyInfo = array();
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$remaining_channelids = array();

		foreach ($nodeContent['results'] AS $key => $node)
		{
			if ($node['lastcontentid'] > 0)
			{
				$lastids[] = $node['lastcontentid'];
			}
			$channelids[] = $node['nodeid'];
			$hashKey = 'vbRouteContentConversation_'. $node['nodeid'];
			$routenew = $cache->read($hashKey);
			if ($routenew !== false)
			{
				$route = vB5_Route_Channel::getRoute($routenew['routeid'], @unserialize($routenew['arguments']));
				if ($route AND ($pageKey = $route->getPageKey()))
				{
					$pageKeyInfo[$pageKey] = $routenew['contentid'];
				}
			}
			else
			{
				$remaining_channelids[] = $node['nodeid'];
			}
		}

		if (empty($channelids))
		{
			return array();
		}

		$lastNodes = vB_Api::instanceInternal('node')->getNodes($lastids);

		//Get contributors
		$contributorQry = vB::getDbAssertor()->assertQuery('vBForum:groupintopic', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'nodeid' => $channelids,
			'groupid' => vB_Api::instanceInternal('usergroup')->getModeratorGroupId()
		));
		$userApi = vB_Api::instanceInternal('user');
		$contributors = array();
		$contributorsAvatarToFetch = array();
		$contributorsInfo = array();
		foreach ($contributorQry as $contributor)
		{
			if (!isset($contributors[$contributor['nodeid']]))
			{
				$contributors[$contributor['nodeid']] = array();
			}
			$userInfo = $userApi->fetchUserinfo($contributor['userid'], array(vB_Api_User::USERINFO_AVATAR));
			$contributorsAvatarToFetch[] = $userInfo['userid'];
			$contributorsInfo[$userInfo['userid']] = $userInfo;
			$contributors[$contributor['nodeid']][$contributor['userid']] = $userInfo;
		}

		// Fetching and setting avatar url for contributors
		$avatarsurl = $userApi->fetchAvatars($contributorsAvatarToFetch, true, $contributorsInfo);
		foreach ($contributors as $nodeid => $nodeContributors)
		{
			foreach ($nodeContributors as $contributorid => $contributor)
			{
				if (isset($avatarsurl[$contributorid]))
				{
					$contributors[$nodeid][$contributorid]['avatar'] = $avatarsurl[$contributorid];
				}
			}
		}

		if (!empty($remaining_channelids))
		{
			$routes = vB::getDbAssertor()->assertQuery('routenew', array('class' => 'vB5_Route_Channel', 'contentid' =>$remaining_channelids));
			foreach ($routes as $record)
			{
				$route = vB5_Route_Channel::getRoute($record['routeid'], @unserialize($record['arguments']));
				$hashKey = 'vbRouteContentConversation_'. $record['contentid'];
				$cache->write($hashKey, $route, 1440, array('routeChg_' . $record['routeid'], 'nodeChg_' . $record['contentid']));
				if ($route AND ($pageKey = $route->getPageKey()))
				{
					$pageKeyInfo[$pageKey] = $record['contentid'];
				}
			}
		}
		// ... now obtain visits
		$viewingQry = vB::getDbAssertor()->getRows('session',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'pagekey' => array_keys($pageKeyInfo))
		);
		$viewing = array();
		foreach ($viewingQry as $viewingUser)
		{
			$nodeId = $pageKeyInfo[$viewingUser['pagekey']];
			if (!isset($viewing[$nodeId]))
			{
				$viewing[$nodeId] = 0;
			}
			$viewing[$nodeId]++;
		}

		foreach ($nodeContent['results'] AS $index => $channel)
		{
			$nodeid = $channel['nodeid'];
			$nodeContent['results'][$index]['contributors'] = $contributors[$nodeid] ? $contributors[$nodeid] : 0;
			$nodeContent['results'][$index]['viewing'] = $viewing[$nodeid] ? $viewing[$nodeid] : 0 ;
			if (!empty($lastNodes[$channel['lastcontentid']]))
			{
				$nodeContent['results'][$index]['lastposttitle'] = $lastNodes[$channel['lastcontentid']]['title'];
				$nodeContent['results'][$index]['lastpost'] = $lastNodes[$channel['lastcontentid']];
			}
		}
		return $nodeContent;
	}

	/**
	 * Returns an array of candidates for blog channel parents.
	 * @return array
	 */
	public static function getBlogChannelParents()
	{
		$results = array();
		// Blog parent should not be be one of its child channels
		$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'defBlogParent' => vB_Channel::DEFAULT_BLOG_PARENT);
		$channels = vB::getDbAssertor()->getRows('vBForum:getAvailableBlogChannelParents', $data);
		// Format the result array for display of select in admincp
		foreach ($channels as $channel)
		{
			$results[$channel['nodeid']] = $channel['title'];
		}
		return $results;
	}

	public function getChannelAdminPerms($nodeid)
	{
		$userContext = vB::getUserContext();

		if ($userContext->fetchUserId() == 0)
		{
			return array('canadmin' => 0, 'canmoderate' => 0);
		}

		if ($userContext->getChannelPermission('moderatorpermissions', 'canaddowners', $nodeid))
		{
			return array('canadmin' => 1, 'canmoderate' => 1);
		}
		else if ($userContext->getChannelPermission('moderatorpermissions', 'canmoderateposts', $nodeid))
		{
			return array('canadmin' => 0, 'canmoderate' => 1);
		}
		else
		{
			return array('canadmin' => 0, 'canmoderate' => 0);
		}
	}

	public function fetchOwner($nodeid)
	{
		$contributors = vB::getDbAssertor()->assertQuery('vBForum:groupintopic', array(
				'nodeid' => $nodeid,
				'groupid' => array(vB_Api::instanceInternal('usergroup')->getOwnerGroupId(vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID))
			)
		);

		if ($contributors->valid())
		{
			$owner = $contributors->current();
			return $owner['userid'];
		}
		return false;
	}

	public function fetchContributors($nodeid, $count = false)
	{
		$contributors = vB::getDbAssertor()->getRows('vBForum:groupintopic', array(
			'nodeid' => $nodeid,
			'groupid' => vB_Api::instanceInternal('usergroup')->getModeratorGroupId(),
			vB_dB_Query::PARAM_LIMIT => $count
			),
			false,
			'userid'
		);
		return empty ($contributors) ? array() : array_keys($contributors);
	}

	/** fetch information about subscribers for a node.
	*
	* @param	int		the nodeid
	*	@param	int		page for which we want data
	*	@param	int		items per page
	*
	*	@return	mixed	array with 'count'=> total subscriber count, 'members'=> array of userid, name, avatar.
	***/
	public function fetchSubscribers($nodeid, $pageno = 1, $perpage = 20, $thumb = false)
	{
		if (!$nodeid OR !(intval($nodeid) > 0) OR !$pageno OR !(intval($pageno) > 0) OR
			!$perpage OR !(intval($perpage) > 0))
		{
			throw new vB_Exception_Api('invalid_data');
		}
		$userContext = vB::getUserContext();

		if (!$userContext->getChannelPermission('moderatorpermissions', 'canmoderateposts', $nodeid))
		{
			throw new vB_Exception_Api('no_permission');
		}

		$userApi = vB_Api::instanceInternal('user');
		$assertor = vB::getDbAssertor();
		$memberGroup = vB_Api::instanceInternal('usergroup')->fetchUsergroupBySystemID(vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID);
		$memberCount = $assertor->getRow('vBForum:groupintopicCount', array('groupid' => $memberGroup['usergroupid'], 'nodeid' => intval($nodeid)));
		$results = array('count' => $memberCount['count'], 'pagecount' => ceil($memberCount['count']/$perpage),  'groupid' => $memberGroup['usergroupid'],
			'members' => array());
		$offset = (intval($pageno) -1) * intval($perpage);
		$members = $assertor->assertQuery('vBForum:groupintopicPage',
			array('groupid' => $memberGroup['usergroupid'], 'nodeid' => intval($nodeid),
			vB_dB_Query::PARAM_LIMITSTART => $offset, vB_dB_Query::PARAM_LIMIT => intval($perpage)));
		foreach ($members as $member)
		{
			$avatarUrl = $userApi->fetchAvatar($member['userid'], $thumb, $member);
			if (empty($avatarUrl))
			{
				$member['avatarUrl'] = 0;
			}
			else
			{
				$member['avatarUrl'] = $avatarUrl['avatarpath'];
			}

			$results['members'][] = $member;
		}

		return $results;
	}

	/**
	 * Handles subscription in special channels for the current user.
	 * This is used basically for social groups subscription handling the join/subscribe logical but we are
	 * implementing here in case requirements change and join/subscribe gets into blogs too.
	 *
	 * @param	int		The channel id we are subscribing to
	 *
	 * @param	bool	Flag indicating if subscription went well.
	 */
	public function subscribeChannel($channelId)
	{
		if (!intval($channelId))
		{
			throw new vB_Exception_Api('invalid_node_id');
		}

		$userId = vB::getUserContext()->fetchUserId();
		if (empty($userId))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		try
		{
			$nodeInfo = vB_Api::instanceInternal('node')->getNode($channelId);
		}
		catch (vB_Exception_Api $ex)
		{
			throw $ex;
		}

		// validate that we have joined this
		try
		{
			$result = vB_Api::instanceInternal('user')->getGroupInTopic(vB::getUserContext()->fetchUserId(), $channelId);
			$result = array_pop($result);
		}
		catch (vB_Exception_Api $ex)
		{
			throw $ex;
		}

		// validate the record
		if (!empty($result) AND ($result['nodeid'] == $channelId))
		{
			try
			{
				$response = vB_Api::instanceInternal('follow')->add($channelId, vB_Api_Follow::FOLLOWTYPE_CHANNELS);
			}
			catch (vB_Exception_Api $ex)
			{
				throw $ex;
			}

			return $response;
		}
		else
		{
			throw new vB_Exception_Api('invalid_special_channel_subscribe_request');
		}
	}

	/**
	 * Handles leave in special channels for the current user.
	 * This is used basically for social groups handling the join/subscribe logical but we are
	 * implementing here in case requirements change and join/subscribe gets into blogs too.
	 *
	 * @param	int		The channel id we are subscribing to
	 *
	 * @param	bool	Flag indicating if subscription went well.
	 */
	public function leaveChannel($channelId)
	{
		$userId = vB::getUserContext()->fetchUserId();
		if (!$this->isChannelModerator($channelId))
		{
			throw new vB_Exception_Api('no_leave_channel_permission');
		}

		// unfollow first...
		try
		{
			// @TODO change this to use removeFollowing if unsubscribing will use the same logic as regular channels
			// we are only removing the subscribediscussion record here...
			vB_Api::instanceInternal('follow')->delete($channelId, vB_Api_Follow::FOLLOWTYPE_CHANNELS);
			$memberGroup = vB_Api::instanceInternal('usergroup')->getMemberGroupId(vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID);

			if (!$memberGroup)
			{
				throw new vB_Exception_Api('invalid_membergroup_id');
			}

			$result = vB_Api::instanceInternal('user')->unsetGroupInTopic($userId, $channelId, $memberGroup);

			// check if we have a pending request to remove...
			$existingCheck = vB::getDbAssertor()->getRows('vBForum:getNodePendingRequest', array('userid' => array($userId),
				'nodeid' => array($channelId), 'request' => array(vB_Api_Node::REQUEST_GRANT_SUBSCRIBER, vB_Api_Node::REQUEST_SG_GRANT_SUBSCRIBER)));

			if (!empty($existingCheck) AND is_array($existingCheck))
			{
				$nodeIds = array();
				foreach ($existingCheck AS $rec)
				{
					$nodeIds[] = $rec['nodeid'];
				}

				vB::getDbAssertor()->assertQuery('vBForum:node', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
					'nodeid' => $nodeIds));
				vB::getDbAssertor()->assertQuery('vBForum:privatemessage', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
					'nodeid' => $nodeIds));
				vB::getDbAssertor()->assertQuery('vBForum:sentto', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
					'nodeid' => $nodeIds));
				vB::getDbAssertor()->assertQuery('vBForum:text', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
					'nodeid' => $nodeIds));
			}
		}
		catch (vB_Exception_Api $ex)
		{
			throw $ex;
		}

		return $result;
	}

	/**
	 * Indicates if the current user is owner or mod from given channel.
	 * Owner can't leave the channel ditto for mods (managers or contributors).
	 * Also used to indicate if current user can leave a special channel or not (social group or blog).
	 *
	 * @param	int		The channel id we are checking.
	 *
	 * @param	bool	Flag indicating if user is owner/mod
	 */
	public function isChannelModerator($channelId)
	{
		$userId = vB::getUserContext()->fetchUserId();

		$channelMembers = vB::getDbAssertor()->assertQuery('vBForum:groupintopic', array(
				'nodeid' => $channelId,
				'groupid' => array(vB_Api_UserGroup::CHANNEL_MODERATOR_SYSGROUPID, vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID)
			)
		);

		$granted = true;
		foreach ($channelMembers AS $record)
		{
			if ($userId == $record['userid'])
			{
				$granted = false;
			}
		}

		return $granted;
	}

	/**
	 * Indicates if the current user is member of a given channelid
	 *
	 * @param	int		The channel id we are checking.
	 *
	 * @return	int		Values of the member status.
	 * 					0 = no member
	 * 					1 = member
	 * 					2 = request pending
	 */
	public function isChannelMember($channelId)
	{
		if (!intval($channelId))
		{
			throw new vB_Exception_Api('invalid_node_id');
		}

		$userId = vB::getUserContext()->fetchUserId();
		if (empty($userId))
		{
			throw new vB_Exception_Api('invalid_current_userid', array($userId));
		}

		try
		{
			$result = vB_Api::instanceInternal('user')->getGroupInTopic(vB::getUserContext()->fetchUserId(), $channelId);
		}
		catch (vB_Exception_Api $ex)
		{
			throw $ex;
		}

		$memberStatus = 0;
		// try to check if we have a pending request...
		if (empty($result))
		{
			$pending = vB::getDbAssertor()->getRow('vBForum:getNodePendingRequest', array(
				'nodeid' => $channelId, 'userid' => $userId, 'request' => array(vB_Api_Node::REQUEST_SG_GRANT_MEMBER, vB_Api_Node::REQUEST_GRANT_MEMBER)
			));
			if (!empty($pending))
			{
				$memberStatus = 2;
			}
		}
		else
		{
			$memberStatus = 1;
		}

		return $memberStatus;
	}

	/**
	 * Returns whether current user can comment in the blog or not
	 *
	 * @param	int		The channel id we are checking.
	 *
	 * @return	int		User can comment?
	 *					0 : No (Commenting is disabled for the blog post or the blog channel)
	 *					-1: No (User is not logged in and Guests have no permission to comment
	 *					-2: No (User is logged in but is not subscribed to the blog post - permission to comment is set for subscribers only)
	 *					1 : Yes
	 */
	public function userCanComment($conversation)
	{
		$channelId = $conversation['parentid'];
		if (!intval($channelId))
		{
			throw new vB_Exception_Api('invalid_node_id');
		}

		$userId = vB::getUserContext()->fetchUserId();
		$channel = vB_Library::instance('node')->getNode($channelId, true, false);
		$commentperms = $channel['commentperms'];

		// If comments are off, don't bother checking the rest.
		// added latter to match comment control panel display behavior - see conversation_footer template
		// The comment box still doesn't match the button behavior for owners and admins.
		if ($channel['allow_post'] == 0 OR !($conversation['can_comment'] > 0))
		{
			return 0;
		}

		// everyone can comment
		if ($commentperms == 2)
		{
			return 1;
		}
		else
		{
			// if they're not logged in, then they can't comment
			if ($userId == 0)
			{
				return -1;
			}

			// if they're logged in, and registered users can comment
			if ($commentperms == 1 AND ($conversation['nodeoptions'] & vB_Api_Node::OPTION_ALLOW_POST))
			{
				return 1;
			}
			else if ($this->isChannelMember($channelId) == 1)
			{
				// otherwise user must be subscribed to the blog
				return 1;
			}
			else
			{
				// user is logged in but is not a member of the blog
				return -2;
			}
		}
	}

	/**
	 * Returns the widget instances that are used for blog sidebar.
	 * This method should be used only for owner configuration of the blog, not rendering
	 */
	public function getBlogSidebarModules($channelId = 0)
	{
		$channelId = intval($channelId);

		$widgetApi = vB_Api::instance('widget');

		// We assume there's only one container in blog pagetemplate. If this is no longer the case, we may need to implement GUID for widgetinstances
		$blogTemplate = vB_Page::getBlogChannelPageTemplate();

		$modules = vB::getDbAssertor()->getRows('getBlogSidebarModules', array('blogPageTemplate' => $blogTemplate));

		$results = $parentConfig = $sortAgain = array();
		foreach($modules AS $module)
		{
			$title = $module['title'];

			//Temporarily removing the Blog Categories module as it is not implemented yet (VBV-4247)
			//@TODO: Remove this when this module gets implemented.
			//@TODO: It would be great if we have a way to globally disable any module and not display it anywhere to avoid this kind of fix.
			if ($module['guid'] == 'vbulletin-widget_blogcategories-4eb423cfd6dea7.34930850')
			{
				continue;
			}
			//END

			if (isset($module['adminconfig']) AND !empty($module['adminconfig']))
			{
				// search for custom title
				$adminConfig = @unserialize($module['adminconfig']);
				if (is_array($adminConfig))
				{
					foreach($adminConfig AS $key=>$val)
					{
						if (stripos($key, 'title') !== FALSE)
						{
							$title = $val;
							break;
						}
					}
				}
			}

			if (!isset($parentConfig[$module['parent']]))
			{
				$parentConfig[$module['parent']] = $widgetApi->fetchConfig($module['parent'], 0, $channelId);
				if (isset($parentConfig[$module['parent']]['display_order']))
				{
					$sortAgain[] = $module['parent'];
				}
			}
			if (isset($parentConfig[$module['parent']]['display_modules']) AND !empty($parentConfig[$module['parent']]['display_modules']))
			{
				$hidden = in_array($module['widgetinstanceid'], $parentConfig[$module['parent']]['display_modules']) ? 0 : 1;
			}
			else
			{
				$hidden = 0;
			}

			$results[$module['widgetinstanceid']] = array(
				'title' => $title,
				'widgetid' => $module['widgetid'],
				'widgetinstanceid' => $module['widgetinstanceid'],
				'hidden' => $hidden
			);
		}

		if (!empty($sortAgain))
		{
			$newOrder = array();
			foreach($sortAgain AS $parent)
			{
				if (is_array($parentConfig[$parent]['display_order']))
				{
					foreach($parentConfig[$parent]['display_order'] AS $widgetInstanceId)
					{
						$newOrder[$widgetInstanceId] = $results[$widgetInstanceId];
						unset($results[$widgetInstanceId]);
					}
				}

				// append remaining items
				$newOrder += $results;
			}

			return $newOrder;
		}
		else
		{
			return $results;
		}
	}

	/**
	 * Saves channel configuration for blog sidebar
	 * @param int $blogId
	 * @param array $modules	An array in which each element contains:
	 *								- widgetinstanceid (int)
	 *								- hide (bool)
	 * @throws vB_Exception_Api
	 */
	public function saveBlogSidebarModules($blogId, $modules)
	{
		if (empty($blogId) OR empty($modules))
		{
			return;
		}

		// check the user is owner
		$userid = vB::getCurrentSession()->get('userid');
		if ($userid != $this->fetchOwner($blogId))
		{
			throw new vB_Exception_Api('no_permission');
		}

		$config = array(
			'display_order' => array(),
			'display_modules' => array()
		);
		foreach($modules AS $module)
		{
			$config['display_order'][] = $module['widgetinstanceid'];
			if (!$module['hide'])
			{
				$config['display_modules'][] = $module['widgetinstanceid'];
			}
		}

		// get parent
		$parent = vB::getDbAssertor()->getField('widgetinstance', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::COLUMNS_KEY => array('parent'),
			vB_dB_Query::CONDITIONS_KEY => array(
				'widgetinstanceid' => $config['display_order']
			)
		));

		vB_Api::instance('widget')->saveChannelConfig($parent, $blogId, $config);
	}

	/** gets the number of members for the given channel
	 *
	 *	@param	int		nodeid
 	 * 	@return int 	number of members
	 */
	public function getMembersCount($nodeid)
	{
		if (!intval($nodeid) OR !$this->isSGNode($nodeid))
		{
			throw new vB_Exception_Api('invalid_node_id');
		}

		$groups = vB::getDbAssertor()->getColumn('vBForum:usergroup', 'usergroupid', array('systemgroupid' => array(
				vB_Api_UserGroup::CHANNEL_MODERATOR_SYSGROUPID,
				vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID,
				vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID
			)),
			false,
			'systemgroupid'
		);
		// get the members count
		$countRecords = vB::getDbAssertor()->assertQuery('vBForum:getChannelMembersCount', array(
			'nodeid' => array($nodeid),
			'groupid' => $groups
		));

		$result = 0;
		foreach ($countRecords AS $count)
		{
			$result = $count['members'];
		}

		return $result;
	}

	/** lists channel members
	 *
	 * @param int		nodeid of the channel to be checked
	 *
	 * @return mixed	array of userid, username.
	 */
	public function fetchMembers($nodeid, $pageno = 1, $perpage = 20, $thumb = false)
	{
		if (!$nodeid OR !(intval($nodeid) > 0) OR !$pageno OR !(intval($pageno) > 0) OR
			!$perpage OR !(intval($perpage) > 0))
		{
			throw new vB_Exception_Api('invalid_data');
		}
		$userContext = vB::getUserContext();

		if (!$userContext->getChannelPermission('moderatorpermissions', 'canmoderateposts', $nodeid))
		{
			throw new vB_Exception_Api('no_permission');
		}
		$assertor = vB::getDbAssertor();
		$pageno = max($pageno, 1);
		$group = vB_Api::instanceInternal('usergroup')->fetchUsergroupBySystemID(vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID);
		$memberQry = $assertor->assertQuery('vBForum:groupintopicPage', array(vB_dB_Query::PARAM_LIMIT => $perpage,
		vB_dB_Query::PARAM_LIMITSTART => $perpage * ($pageno - 1), 'nodeid' => $nodeid, 'groupid' => $group['usergroupid']));

		$members = array();
		foreach ($memberQry as $member)
		{
			$members[$member['userid']] = array();
		}

		$userApi = vB_Api::instanceInternal('user');

		if (empty($members))
		{
			return array('count' => 0, 'members' => array(), 'pagecount' => 1);

		}
		$userQry = $assertor->assertQuery('user', array(vB_dB_Query::QUERY_SELECT,
			'userid' => array_keys($members)));
		foreach ($userQry as $user)
		{
			$avatarUrl = $userApi->fetchAvatar($user['userid'], $thumb, $user);
			if (empty($avatarUrl))
			{
				$user['avatarUrl'] = 0;
			}
			else
			{
				$user['avatarUrl'] = $avatarUrl['avatarpath'];
			}
			$members[$user['userid']]= array('userid' => $user['userid'],
				'username' => $user['username'], 'avatarUrl' => $user['avatarUrl'], 'groupid' => $group['usergroupid']);
		}
		$count = $assertor->getRow('vBForum:groupintopicCount', array('groupid' => $group['usergroupid'], 'nodeid' => $nodeid));
		$count = $count['count'];
		$pagecount = ceil($count/$perpage);

		return array('count' => $count, 'members' => $members, 'pagecount' => $count);
	}

	/** Checks if the user can create a new Blog
	 *
	 * @param int parentid of the blog parent
	 *
	 * @return bool
	 */
	public function canCreateBlog($parentid)
	{
		if (empty($parentid))
		{
			throw new vB_Exception_Api('invalid_blog_parent');
}
		$blogNode = vB_Api::instanceInternal('node')->getNode(intval($parentid));
		if (empty($blogNode) OR $blogNode['nodeid'] != $this->getBlogChannel())
		{
			throw new vB_Exception_Api('invalid_blog_parent');
		}

		// Check for the permissions
		$check = vB_Api::instance('content_channel')->canAddChannel($blogNode['nodeid']);
		if (!$check['can'] AND $check['exceeded'])
		{
			throw new vB_Exception_Api('you_can_only_create_x_blogs', array($check['exceeded']));
		}
		else if(!$check['can'])
		{
			throw new vB_Exception_Api($check['error']);
		}

		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
