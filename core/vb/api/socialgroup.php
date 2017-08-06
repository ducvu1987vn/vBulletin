<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * vB_Api_Socialgroup
 *
 * @package vBApi
 * @access public
 */
class vB_Api_SocialGroup extends vB_Api_Blog
{

	protected $sgChannel = false;

	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * @uses fetch the id of the global Social Group Channel
	 * @return int nodeid of actual Main Social Group Channel
	 */
	public function getSGChannel()
	{
		if (!empty($this->sgChannel))
		{
			return $this->sgChannel;
		}
		// use default pagetemplate for social groups
		$sgChannel = vB_Api::instanceInternal('Content_Channel')->fetchChannelByGUID(vB_Channel::DEFAULT_SOCIALGROUP_PARENT);
		if (empty($sgChannel))
		{
			throw new vB_Exception_Api('invalid_channel_requested');
		}
		$this->sgChannel = $sgChannel['nodeid'];
		return $this->sgChannel;
	}

	/**
	 * Determines if the given node is a blog-related node (blog entry).
	 *
	 * @param	int	$nodeid
	 * @return	bool
	 */
	public function isSGNode($nodeId, $node = false)
	{
		$nodeId = (int) $nodeId;

		if ($nodeId < 0)
		{
			return false;
		}

		$sgChannelId = (int) $this->getSGChannel();

		if (empty($node))
		{
			$nodeLib = vB_Library::instance('node');
			$node = $nodeLib->getNode($nodeId, true, false);
		}

		if (!empty($node['parents']))
		{
			$parents = $node['parents'];
		}
		else
		{
			$nodeLib = vB_Library::instance('node');
			$parents = $nodeLib->getParents($nodeId);
		}

		if (is_numeric(current($parents)))
		{
			return in_array($sgChannelId, $parents);
		}

		foreach ($parents as $parent)
		{

			if ($parent['nodeid'] == $sgChannelId)
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Determines if the given node is a Social group channel.
	 *
	 * @param	int	$nodeid
	 * @return	bool
	 */
	public function isSGChannel($nodeid)
	{
		if (!intval($nodeid))
		{
			return false;
		}
		$nodeInfo = vB_Api::instance('node')->getNodeContent($nodeid);
		if ($this->isSGNode($nodeid)
			AND ($nodeInfo[$nodeid]['contenttypeid'] == vB_Types::instance()->getContentTypeId('vBForum_Channel')))
		{
			return true;
		}
		return false;
	}

	/** gets Get info on every SG Channel
	 *
	 *	@param	array	Array of options to filter the info (used to show all/my groups).
 	 * 	@return mixed 	array containing the blog channel info we need
	 */
	public function getSGInfo($options = array())
	{
		$response = array();
		$nodeApi = vB_Api::instanceInternal('node');

		$sgParentChannel = $this->getSGChannel();
		if (!empty($options['sgparent']) AND intval($options['sgparent']) AND (intval($options['sgparent'] != $sgParentChannel)))
		{
			$sgParent = intval($options['sgparent']);
			$depth = 1;
		}
		else
		{
			$sgParent = $sgParentChannel;
			$depth = 2;
		}

		// category check
		if (!$this->isSGNode($sgParent))
		{
			throw new vB_Exception_Api('invalid_sg_parent');
		}


		//Get base data
		$channelContentType = vB_Types::instance()->getContentTypeId('vBForum_Channel');
		$params = array('starter_only' => 1, 'view' => 'activity', 'depth_exact' => 1, 'nolimit' =>1);
		$queryParams = array('sgParentChannel' => $sgParent, 'depth' => $depth);
		if (!empty($options['userid']))
		{
			$queryParams['userid'] = $params['userid'] = intval($options['userid']);
		}

		$page = (!empty($options['page']) AND intval($options['page'])) ? intval($options['page']) : 1;
		$perpage = (!empty($options['perpage']) AND intval($options['perpage'])) ? intval($options['perpage']) : 20;
		$cacheParams = array_merge($params, array('page' => $page, 'perpage' => $perpage, 'sgparent' => $sgParent, 'depth' => $depth));
		$cacheKey = 'sgResults_' . (vB::getUserContext()->fetchUserId() ? vB::getUserContext()->fetchUserId() : 0) . crc32(serialize($cacheParams));
		if ($result = vB_Cache::instance(vB_Cache::CACHE_FAST)->read($cacheKey) OR !vB::getUserContext()->hasPermission('socialgrouppermissions', 'canviewgroups'))
		{
			return $result;
		}
		$nodeContent = $nodeApi->listNodeContent($sgParent, $page, $perpage, $depth, $channelContentType, $params);
		$totalCount = vB::getDbAssertor()->getRow('vBForum:getSocialGroupsTotalCount', $queryParams);

		//We need the nodeids to collect some data
		$cacheEvents = array('nodeChg_' . $sgParent);
		$lastids = array();
		$lastNodes = array();
		$channelids = array();
		$categories = array();
		$contributorIds = array();
		$sgCategories = array_keys($this->getCategories());
		$sgParentChannel = $this->getSGChannel();
		foreach ($nodeContent AS $key => $node)
		{
			if ($node['parentid'] == $sgParentChannel)
			{
				$categories[] = $node['nodeid'];
				unset($nodeContent[$node['nodeid']]);
			}
			else
			{
				if ($node['lastcontentid'] > 0)
				{
					$lastids[] = $node['lastcontentid'];
				}
				if (in_array($node['parentid'], $sgCategories))
				{
					$categories[] = $node['parentid'];
				}
				$channelids[] = $node['nodeid'];
				$contributorIds[] = $node['userid'];
				$cacheEvents[] = 'nodeChg_' . $node['nodeid'];
			}
		}
		$categories = array_unique($categories);

		if (empty($channelids))
		{
			//for display purposes, we set totalpages to 1 even if there are no records because we don't want the UI to display Page 1 of 0
			$result = array('results' => array(), 'totalcount' => 0, 'pageInfo' => array('currentpage' => $page, 'perpage' => $perpage, 'nexturl' => 0, 'prevurl' => 0, 'totalpages' => 1, 'totalrecords' => 0, 'sgparent' => $sgParent));
			vB_Cache::instance(vB_Cache::CACHE_FAST)->write($cacheKey, $result, 60, array_unique($cacheEvents));
			return $result;
		}

		$mergedNodes = vB_Library::instance('node')->getNodes($lastids + $categories);
		foreach ($lastids as $lastid)
		{
			if (empty($mergedNodes[$lastid]))
			{
				continue;
			}
			$lastNodes[$lastid] = $mergedNodes[$lastid];
		}
		foreach ($categories as $category)
		{
			if (empty($mergedNodes[$category]))
			{
				continue;
			}
			$categoriesInfo[$category] = $mergedNodes[$category];
		}

		// update category info
		foreach ($nodeContent AS $key => $node)
		{
			// add category info
			if (isset($categoriesInfo[$node['parentid']]))
			{
				$nodeContent[$key]['content']['channeltitle'] = $categoriesInfo[$node['parentid']]['title'];
				$nodeContent[$key]['content']['channelroute'] = $categoriesInfo[$node['parentid']]['routeid'];
				$cacheEvents[] = 'nodeChg_' . $node['parentid'];
			}
		}

		$lastTitles = $lastInfo = array();
		$lastIds = array();
		foreach ($lastNodes as $lastnode)
		{
			$lastInfo[$lastnode['nodeid']]['starter'] = $lastnode['starter'];
			if ($lastnode['starter'] == $lastnode['nodeid'])
			{
				$lastInfo[$lastnode['nodeid']]['title'] = $lastnode['title'];
				$lastInfo[$lastnode['nodeid']]['routeid'] = $lastnode['routeid'];
				$contributorIds[] = $lastnode['userid'];
			}
			else
			{
				//We need another query
				$lastIds[$lastnode['starter']] = $lastnode['starter'];
			}
		}

		//Now get any lastcontent starter information we need
		if (!empty ($lastIds))
		{
			$nodes = vB_Library::instance('node')->getNodes($lastIds);
			foreach ($nodeContent AS $index => $channel)
			{
				$nodeid = $lastInfo[$channel['lastcontentid']]['starter'];
				if (isset($nodes[$nodeid]))
				{
					$node =& $nodes[$nodeid];
					$lastInfo[$channel['lastcontentid']]['routeid'] = $node['routeid'];
					$lastInfo[$channel['lastcontentid']]['title'] = $node['title'];
				}
			}
		}

		if (!empty($options['contributors']))
		{

			//Get contributors
			$groups = vB::getDbAssertor()->getColumn('vBForum:usergroup', 'usergroupid', array('systemgroupid' => array(
					vB_Api_UserGroup::CHANNEL_MODERATOR_SYSGROUPID,
					vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID,
					vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID
				)),
				false,
				'systemgroupid'
			);

			$membersQry = vB::getDbAssertor()->assertQuery('vBForum:groupintopic', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'nodeid' => $channelids,
				'groupid' => $groups
			));

			$groupManagers = array();
			$contributors = array();
			foreach ($membersQry AS $record)
			{
				if ($record['groupid'] == $groups[vB_Api_UserGroup::CHANNEL_MODERATOR_SYSGROUPID])
				{
					$groupManagers[] = $record;
				}
				$contributorIds[$record['userid']] = $record['userid'];
				$cacheEvents[] = 'sgMemberChg_' . $record['userid'];
			}

			$userApi = vB_Api::instanceInternal('user');
			$avatarInfo = vB_Api::instanceInternal('user')->fetchAvatars($contributorIds);
			foreach ($groupManagers as $index => $contributor)
			{
				if (!isset($contributors[$contributor['nodeid']]))
				{
					$contributors[$contributor['nodeid']] = array();
				}
				$userInfo = $userApi->fetchUserinfo($contributor['userid']);
				$contributors[$contributor['nodeid']][$contributor['userid']] = $userInfo;
				$contributors[$contributor['nodeid']][$contributor['userid']]['avatar'] = $avatarInfo[$contributor['userid']];
			}
		}
		// Obtain keys for sg pages
		$pageKeyInfo = array();
		$routes = vB::getDbAssertor()->getRows('routenew', array('class' => 'vB5_Route_Channel', 'contentid' =>$channelids),false,'routeid');
		vB5_Route::preloadRoutes(array_keys($routes));
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		foreach ($routes as $record)
		{
			$cache->write('vbRouteContentConversation_' . $record['contentid'], $record, 1440, array('routeChg_' . $record['routeid'], 'nodeChg_' . $record['contentid']));
			$route = vB5_Route_Channel::getRoute($record['routeid'], @unserialize($record['arguments']));
			if ($route AND ($pageKey = $route->getPageKey()))
			{
				$pageKeyInfo[$pageKey] = $record['contentid'];
			}
		}

		$viewingQry = vB::getDbAssertor()->getRows('session',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'pagekey' => array_keys($pageKeyInfo))
		);

		$viewing = array();

		foreach ($viewingQry as $viewingUser)
		{
			if (!isset($viewing[$viewingUser['nodeid']]))
			{
				$viewing[$viewingUser['nodeid']] = 0;
			}
			$viewing[$viewingUser['nodeid']]++;
		}

		// get the members count
		$countRecords = vB::getDbAssertor()->assertQuery('vBForum:getChannelMembersCount', array(
			'nodeid' => $channelids,
			'groupid' => $groups
		));

		$membersCount = array();
		foreach ($countRecords AS $count)
		{
			$membersCount[$count['nodeid']] = $count;
		}

		foreach ($nodeContent AS $index => $channel)
		{
			$nodeid = $channel['nodeid'];
			if (!empty($options['contributors']))
			{
				$nodeContent[$index]['contributors'] = !empty($contributors[$nodeid]) ? $contributors[$nodeid] : 0;
				$nodeContent[$index]['contributorscount'] = !empty($contributors[$nodeid]) ? count($contributors[$nodeid]) : 0;
			}
			$nodeContent[$index]['members'] = !empty($membersCount[$nodeid]) ? $membersCount[$nodeid]['members'] : 0;
			$nodeContent[$index]['viewing'] = !empty($viewing[$nodeid]) ? $viewing[$nodeid] : 0 ;
			$nodeContent[$index]['lastposttitle'] = !empty($lastInfo[$channel['lastcontentid']]['title']) ? $lastInfo[$channel['lastcontentid']]['title'] : 0;
			$nodeContent[$index]['lastpostrouteid'] = !empty($lastInfo[$channel['lastcontentid']]['routeid']) ? $lastInfo[$channel['lastcontentid']]['routeid'] : 0;

			$nodeContent[$index]['owner_avatar'] = $avatarInfo[$nodeContent[$index]['userid']];
			$nodeContent[$index]['lastauthor_avatar'] = $avatarInfo[$nodeContent[$index]['lastauthorid']];
		}

		$total = $totalCount['totalcount'];
		if ($total > 0)
		{
			$pages = ceil($total/$perpage);
		}
		else
		{
			$pages = 1; //we don't want the UI to display Page 1 of 0
		}

		$nextPage = ($page < $pages) ? ($page + 1) : 0;
		$prevPage = ($page - 1) >= 1 ? ($page - 1) : 0;

		$result = array('results' => $nodeContent, 'totalcount' => count($nodeContent), 'pageInfo' => array('currentpage' => $page, 'perpage' => $perpage, 'nexturl' => $nextPage, 'prevurl' => $prevPage, 'totalpages' => $pages, 'totalrecords' => $total, 'sgparent' => $sgParent));
		vB_Cache::instance(vB_Cache::CACHE_FAST)->write($cacheKey, $result, 60, array_unique($cacheEvents));

		return $result;
	}

	/** Get the current user's permissions for own stuff
	 * (eg. Own groups, own discussions, own messages)
	*
	*	@param int	the nodeid to check
 	*	@return	array of permissions set to yes
	*/
	public function getSGOwnerPerms($nodeid = false)
	{
		$userContext = vB::getUserContext();
		$perms = array();
		if ($userContext->hasPermission('socialgrouppermissions', 'canmanageowngroups'))
		{
			$perms['canmanageowngroups'] = 1;
		}
		if ($userContext->hasPermission('socialgrouppermissions', 'caneditowngroups'))
		{
			$perms['caneditowngroups'] = 1;
		}
		if ($userContext->hasPermission('socialgrouppermissions', 'candeleteowngroups'))
		{
			$perms['candeleteowngroups'] = 1;
		}
		if ($userContext->hasPermission('socialgrouppermissions', 'canmanagediscussions'))
		{
			$perms['canmanagediscussions'] = 1;
		}
		if ($userContext->hasPermission('socialgrouppermissions', 'canmanagemessages'))
		{
			$perms['canmanagemessages'] = 1;
		}

		return $perms;
	}

	/** returns the category list- direct children of the social group channel
	 *
	 * @return mixed	array of nodeid => title
	 */
	public function getCategories()
	{
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$categories = $cache->read('vbSGChannels');
		if (!empty($categories))
		{
			return $categories;
		}
		$sgChannel = $this->getSGChannel();
		$categories = vB::getDbAssertor()->getRows('vBForum:node', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'parentid' => $sgChannel, 'contenttypeid' => vB_Types::instance()->getContentTypeID('vBForum_Channel')),
			'title','nodeid');

		$return = array();
		$userContext = vB::getUserContext();
		$events = array();
		vB_Library::instance('node')->fetchClosureParent(array_keys($categories));
		foreach ($categories as $category)
		{
			if ($userContext->getChannelPermission( 'forumpermissions', 'canview', $category['nodeid'], false, $sgChannel))
			{
				$return[$category['nodeid']] = array(
					'title' => $category['title'],
					'htmltitle' => $category['htmltitle'],
					'routeid' => $category['routeid'],
					'content' => $category['content'],
				);
				$events[] = 'routeChg_' . $category['routeid'];
				$events[] = 'nodeChg_' . $category['content'];
			}
			vB_Library_Content::writeToCache(array($category), vB_Library_Content::CACHELEVEL_NODE);
		}
		$cache->write('vbSGChannels', $return, 1440, $events);
		return $return;
	}

	/** creates a new social group
	 *
	 * @param mixed	array which must include parentid, title. Should also have various options and a description.
	 *
	 * @return int nodeid of the created group/channel
	 */
	public function createSocialGroup($input)
	{
		if (empty($input['parentid']))
		{
			throw new vB_Exception_Api('invalid_sg_parent');
		}
		$sgParent = intval($input['parentid']);
		$catNode = vB_Api::instanceInternal('node')->getNode($sgParent);
		if (empty($catNode) OR $catNode['parentid'] != $this->getSGChannel())
		{
			throw new vB_Exception_Api('invalid_sg_parent');
		}

		// Check for the permissions
		$check = vB_Api::instance('content_channel')->canAddChannel($this->getSGChannel());
		if (!$check['can'] AND $check['exceeded'])
		{
			throw new vB_Exception_Api('you_can_only_create_x_groups_delete', array($check['exceeded']));
		}
		else if(!$check['can'])
		{
			throw new vB_Exception_Api($check['error']);
		}

		// social group type, we allow post by default while creating social group
		$input['nodeoptions'] = 2;
		switch ($input['group_type'])
		{
			case 2:
				$input['nodeoptions'] |= vB_Api_Node::OPTION_NODE_INVITEONLY;
				break;
			case 1:
				break;
			default:
				$input['nodeoptions'] |= vB_Api_Node::OPTION_AUTOAPPROVE_MEMBERSHIP;
				break;
		}

		return $this->createChannel($input, $sgParent, vB_Page::getSGConversPageTemplate(), vB_Page::getSGChannelPageTemplate(), vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID);
	}

	/** creates a new category
	 *
	 *	@param	int
	 * @param mixed	array which must include title and optionally parentid
	 *
	 * @return int nodeid of the created category
	 */
	public function saveCategory($nodeId, $input)
	{
		$channelApi = vB_Api::instanceInternal('content_channel');

		$nodeId = (int) $nodeId;

		// force social group channel as parent id (categories cannot be nested)
		$input['parentid'] = $this->getSGChannel();
		$input['category'] = 1; // force channel to be a category
		$input['templates']['vB5_Route_Channel'] = vB_Page::getSGCategoryPageTemplate();
		$input['templates']['vB5_Route_Conversation'] = vB_Page::getSGCategoryConversPageTemplate();

		// TODO: this code is similar to vB_Api_Widget::saveChannel, add a library method with it?
		if ($nodeId > 0)
		{
			// this call won't update parentid
			$channelApi->update($nodeId, $input);
		}
		else
		{
			$nodeId = $channelApi->add($input);
		}

		return $nodeId;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
