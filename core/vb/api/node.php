<?php
if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
   || #################################################################### ||
   || # vBulletin 5.0.0
   || # ---------------------------------------------------------------- # ||
   || # Copyright 2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
   || # This file may not be redistributed in whole or significant part. # ||
   || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
   || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
   || #################################################################### ||
   \*======================================================================*/


/**
 * vB_Api_Node
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Node extends vB_Api
{

	/** @TODO: this is duplicated info which is already declared in querydef. We should read it from there.*/
	protected $contentAPIs = array();

	/**
	 *
	 * @var vB_Library_Node
	 */
	protected $library;

	const FILTER_SOURCEALL = 'source_all';
	const FILTER_SOURCEUSER = 'source_user';
	const FILTER_SOURCEVM = 'source_vm';

	const FILTER_SORTMOSTRECENT = 'sort_recent';
	const FILTER_SORTPOPULAR = 'sort_popular';
	const FILTER_SORTFEATURED = 'sort_featured';
	const FILTER_SORTOLDEST = 'sort_oldest';

	const FILTER_SHOWALL = 'show_all';
	const FILTER_TIME = 'date';
	const FILTER_SOURCE = 'filter_source';
	const FILTER_SORT = 'sort';
	const FILTER_ORDER = 'order';
	const FILTER_SHOW = 'filter_show';
	const FILTER_FOLLOW = 'filter_follow';
	const FILTER_DEPTH= 'filter_depth';

	// requests for blogs
	const REQUEST_TAKE_OWNER = 'owner_to';// ask the recipient to assume ownership;
	const REQUEST_TAKE_MODERATOR = 'moderator_to';// ask the recipient to assume moderation;
	const REQUEST_TAKE_MEMBER = 'member_to';// ask the recipient to become a member;
	// @TODO for inviting subscriber functionality
	const REQUEST_TAKE_SUBSCRIBER = 'subscriber_to';// ask the recipient to become a member;
	const REQUEST_GRANT_OWNER = 'owner_from';// ask the recipient to grant ownership;
	const REQUEST_GRANT_MODERATOR = 'moderator';// ask the recipient to grant moderation;
	const REQUEST_GRANT_MEMBER = 'member';// ask the recipient to grant membership;
	const REQUEST_GRANT_SUBSCRIBER = 'subscriber';// ask the recipient to become a subscriber;

	// requests for social groups
	const REQUEST_SG_TAKE_OWNER = 'sg_owner_to';// ask the recipient to assume ownership;
	const REQUEST_SG_TAKE_MODERATOR = 'sg_moderator_to';// ask the recipient to assume moderation;
	const REQUEST_SG_TAKE_MEMBER = 'sg_member_to';// ask the recipient to become a member;
	// @TODO for inviting subscriber functionality
	const REQUEST_SG_TAKE_SUBSCRIBER = 'sg_subscriber_to';// ask the recipient to become a member;
	const REQUEST_SG_GRANT_OWNER = 'sg_owner_from';// ask the recipient to grant ownership;
	const REQUEST_SG_GRANT_MODERATOR = 'sg_moderator';// ask the recipient to grant moderation;
	const REQUEST_SG_GRANT_MEMBER = 'sg_member';// ask the recipient to grant membership;
	const REQUEST_SG_GRANT_SUBSCRIBER = 'sg_subscriber';// ask the recipient to become a subscriber;

	const OPTION_ALLOW_POST = 2;
	const OPTION_MODERATE_COMMENTS = 4;
	const OPTION_AUTOAPPROVE_MEMBERSHIP = 8;
	const OPTION_NODE_INVITEONLY = 16;
	const OPTION_NODE_PARSELINKS = 32;
	const OPTION_NODE_DISABLE_SMILIES = 64;
	const OPTION_AUTOAPPROVE_SUBSCRIPTION = 128;
	const OPTION_MODERATE_TOPICS = 256;
	const DATE_RANGE_DAILY = 'daily';
	const DATE_RANGE_MONTHLY = 'monthly';

	protected $options = array('allow_post' => self::OPTION_ALLOW_POST, 'moderate_comments' => self::OPTION_MODERATE_COMMENTS,
		'approve_membership' => self::OPTION_AUTOAPPROVE_MEMBERSHIP, 'invite_only' => self::OPTION_NODE_INVITEONLY,
		'autoparselinks' => self::OPTION_NODE_PARSELINKS, 'disablesmilies' => self::OPTION_NODE_DISABLE_SMILIES,
		'approve_subscription' => self::OPTION_AUTOAPPROVE_SUBSCRIPTION, 'moderate_topics' => self::OPTION_MODERATE_TOPICS);

	protected function __construct()
	{
		parent::__construct();
		$this->library = vB_Library::instance('node');
		$this->pmContenttypeid = vB_Types::instance()->getContentTypeId('vBForum_PrivateMessage');	}

	/** Return the list of fields in the node table
	*
	*
	**/
	public function getNodeFields()
	{
		return $this->library->getNodeFields();
	}

	/** opens a node for posting
	 *
	 * 	@param	mixed	integer or array of integers
	 *
	 *	@return	mixed	Either array 'errors' => error string or array of id's.
	 **/
	public function openNode($nodeid)
	{
		$userContext = vB::getUserContext();
		if($userContext->isModerator())
		{
			$this->inlinemodAuthCheck();
		}


		//we need to handle a single nodeid or an array of nodeids
		if (!is_array($nodeid))
		{
			$nodeids = array($nodeid);
		}
		else
		{
			$nodeids = $nodeid;
		}
		//First check permissions of course.
		foreach ($nodeids as $nodeid)
		{
			//this can be approved through moderator permissions, or because the node is the current user's and they have forumpermission canopenclose
			if (!vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canopenclose', $nodeid))
			{
				$node = $this->library->getNode($nodeid);

				if(($node['userid'] != vB::getCurrentSession()->get('userid')) OR
					!vB::getUserContext()->getChannelPermission('forumpermissions', 'canopenclose', $nodeid))
				{
					throw new vB_Exception_Api('no_permission');
				}
			}
		}

		return $this->library->openNode($nodeids);
	}

	/** Closes a node for posting. Closed nodes can still be viewed but nobody can reply to one.
	 *
	 * 	@param	mixed	integer or array of integers
	 *
	 *	@return	mixed	Either array 'errors' => error string or array of id's.
	 **/
	public function closeNode($nodeid)
	{
		$userContext = vB::getUserContext();
		if($userContext->isModerator())
		{
			$this->inlinemodAuthCheck();
		}

		//we need to handle a single nodeid or an array of nodeids
		if (!is_array($nodeid))
		{
			$nodeids = array($nodeid);
		}
		else
		{
			$nodeids = $nodeid;
		}

		//First check permissions of course.
		foreach ($nodeids as $nodeid)
		{
			//this can be approved through moderator permissions, or because the node is the current user's and they have forumpermission canopenclose
			if (!vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canopenclose', $nodeid))
			{
				$node = $this->library->getNode($nodeid);

				if(($node['userid'] != vB::getCurrentSession()->get('userid')) OR
					!vB::getUserContext()->getChannelPermission('forumpermissions', 'canopenclose', $nodeid))
				{
					throw new vB_Exception_Api('no_permission');
				}
			}
		}

		return $this->library->closeNode($nodeids);
	}

	/** Adds a new node. The record must already exist as an individual content item.
	 *
	 *	@param	integer	The id in the primary table
	 *	@param	integer	The content type id of the record to be added
	 *	@param	mixed		Array of field => value pairs which define the record.
	 *
	 * 	@return	boolean
	 **/
	public function addNode($contenttypeid, $data)
	{
		if (empty($data['parentid']))
		{
			throw new Exception('cannot_create_node');
		}

		if (! vB::getUserContext()->getChannelPermission('createpermissions', $contenttypeid, $data['parentid']))
		{
			throw new Exception('no_create_permissions');
		}

		return $this->library->addNode($contenttypeid, $data);
	}

	/** Permanently/Temporarily deletes a set of nodes
	 *	@param	array	The nodeids of the records to be deleted
	 *	@param	bool	hard/soft delete
	 *	@param	string	the reason for soft delete (not used for hard delete)
	 *	@param	bool	Log the deletes in moderator log
	 *
	 *	@return	array nodeids that were deleted
	 **/
	public function deleteNodes($nodeids, $hard = true, $reason = '', $modlog = true)
	{
		if (empty($nodeids))
		{
			return false;
		}
		//If it's a protected channel, don't allow removal.
		$existing = vB_Library::instance('node')->getNodes($nodeids);
		// need to see if we require authentication
		$currentUserId = vB::getCurrentSession()->get('userid');
		$need_auth = false;
		$moderateInfo = vB::getUserContext()->getCanModerate();
		$allowToDelete = array();
		foreach ($existing as $node)
		{
			// this is a Visitor Message
			if (!empty($node['setfor']) AND ($node['setfor'] == $currentUserId))
			{
				$canModerateOwn = vB::getUserContext()->hasPermission('visitormessagepermissions', 'canmanageownprofile');
				if ($canModerateOwn)
				{
					$allowToDelete[$node['nodeid']] = $node['nodeid'];
					continue;
				}
			}
			else
			{
				$canModerateOwn = vB::getUserContext()->getChannelPermission('forumpermissions2', 'canmanageownchannels', $node['nodeid']);
			}
			// check if this is the owner of a blog that needs to moderate the comments
			if (!empty($moderateInfo['can']) OR ($canModerateOwn))
			{
				// let's get the channel node
				$channelid = vB_Library::instance('node')->getChannelId($node);
				if ($channelid == $node['nodeid'])
				{
					$channel = $node;
				}
				else
				{
					$channel = vB_Library::instance('node')->getNodeBare($channelid);
				}

				// this channel was created by the current user so we don't need the auth check
				if ( (in_array($channelid, $moderateInfo['can']) OR $canModerateOwn) AND ($channel['userid'] == $currentUserId))
				{
					$allowToDelete[$node['nodeid']] = $node['nodeid'];
					continue;
				}
			}

			if ($node['userid'] != $currentUserId)
			{
				$need_auth = true;
				break;
			}
		}

		if ($need_auth)
		{
			$this->inlinemodAuthCheck();
		}

		$userinfo = vB::getCurrentSession()->fetch_userinfo();
		$userContext = vB::getUserContext();
		$deleteNodeIds = array();
		$ancestorsId = $starters = array();
		$vmChannel = $this->fetchVMChannel();
		$contenttype_Channel = vB_Types::instance()->getContentTypeId('vBForum_Channel');
		foreach ($existing as $node)
		{
			//Check for protected- O.K. if it's not a channel.
			if ($node['protected'] AND ($node['contenttypeid'] == $contenttype_Channel))
			{
				throw new vB_Exception_Api('invalid_request');
			}

			//allow the owner to delete his own post
			if (!array_key_exists($node['nodeid'], $allowToDelete) AND
				!$userContext->getChannelPermission('moderatorpermissions', 'canremoveposts', $node['nodeid']) AND
				!(($node['userid'] == $currentUserId) AND ($node['starter'] == $node['nodeid']) AND empty($hard) AND
					$userContext->getChannelPermission('forumpermissions', 'candeletethread', $node['nodeid'])) AND
				!(($node['userid'] == $currentUserId) AND ($node['starter'] != $node['nodeid']) AND empty($hard))
			)
			{
				throw new vB_Exception_Api('no_permission');
			}
			if ($node['parentid'] == $vmChannel AND $node['setfor'] == $currentUserId)
			{
				$vm_user = vB_User::fetchUserinfo($node['setfor']);
				if (!vB::getUserContext($vm_user['userid'])->hasPermission('genericpermissions', 'canviewmembers'))
				{
					throw new vB_Exception_Api('no_permission');
				}
			}

			array_push($deleteNodeIds, $node['nodeid']);

			$route = 'profile';
			if (class_exists('vB5_Cookie') AND vB5_Cookie::isEnabled())
			{
				// session is stored in cookies, so do not append it to url
				$route .= '|nosession';
			}

			if (!empty($node['starter']))
			{
				$starters[] = $node['starter'];
			}

			$parents = $this->library->fetchClosureParent($node['nodeid']);
			foreach ($parents as $parent)
			{
				if ($parent['depth'] > 0)
				{
					$ancestorsId[] = $parent['parent'];
				}
			}
		}
		$ancestorsId = array_unique($ancestorsId);

		if (empty($deleteNodeIds))
		{
			return array();
		}

		return $this->library->deleteNodes($deleteNodeIds, $hard, $reason, $ancestorsId, $starters, $modlog);
	}


	/**
	 * Delete nodes as spam
	 *
	 * @param array	$nodeids The nodeids of the records to be deleted
	 * @param array $userids Selected userids who are being applied punitive action to
	 * @param bool $hard hard/soft delete
	 * @param string $reason
	 * @param bool $deleteother Whether to delete other posts and threads started by the affected users
	 * @param bool $banusers Whether to ban the affected users
	 * @param int $banusergroupid Which banned usergroup to move the users to
	 * @param string $period Ban period
	 * @param string $banreason Ban reason
	 */
	public function deleteNodesAsSpam($nodeids, $userids = array(), $hard = true, $reason = "",
		$deleteother = false, $banusers = false, $banusergroupid = 0, $period = 'PERMANENT', $banreason = ''
	)
	{
		$this->inlinemodAuthCheck();

		// Permission check
		$loginuser = &vB::getCurrentSession()->fetch_userinfo();
		$usercontext = &vB::getUserContext();
		if ($banusers AND !$usercontext->hasPermission('moderatorpermissions', 'canbanusers'))
		{
			$forumHome = vB_Library::instance('content_channel')->getForumHomeChannel();
			throw new vB_Exception_Api('nopermission_loggedin',
				$loginuser['username'],
				vB_Template_Runtime::fetchStyleVar('right'),
				vB::getCurrentSession()->get('sessionurl'),
				$loginuser['securitytoken'],
				vB5_Route::buildUrl($forumHome['routeid'] . '|nosession|fullurl')
			);
		}

		foreach ((array)$nodeids as $k => $nodeid)
		{
			if ($usercontext->getChannelPermission('moderatorpermissions', 'canmassprune', $nodeid))
			{
				unset($nodeids[$k]);
			}
		}

		$checkuserids = $this->fetchUseridsFromNodeids($nodeids);

		$userids = array_intersect($userids, $checkuserids);

		if ($deleteother)
		{
			$search_api = vB_Api::instanceInternal('search');
			$search_json = json_encode(array(
				'authorid'		=> $userids,
				'ignore_cache'	=> true,
				'exclude_type'  => array('vBForum_Channel', 'vBForum_PrivateMessage', 'vBForum_Report')
			));

			$result = $search_api->getSearchResult($search_json);

			$othernodeids = array();
			do
			{
				$othernodes = $search_api->getMoreNodes($result['resultId']);

				if ($othernodeids == array_values($othernodes['nodeIds']))
				{
					break;
				}
				$othernodeids = array_values($othernodes['nodeIds']);

				if (!empty($othernodeids))
				{
					$this->deleteNodes($othernodeids, $hard, $reason);
				}
			} while (!empty($othernodeids));
		}
		else
		{
			$this->deleteNodes($nodeids, $hard, $reason);
		}

		if ($banusers)
		{
			$user_api = vB_Api::instanceInternal('user');
			$user_api->banUsers($userids, $banusergroupid, $period, $banreason);
		}

		return true;
	}

	/** undeletes a set of nodes
	 *	@param	array	The nodeids of the records to be deleted
	*
	*	@return	array nodeids that were deleted
	**/
	public function undeleteNodes($nodeids)
	{
		if (empty($nodeids))
		{
			return false;
		}

		$assertor = vB::getDbAssertor();
		//If it's a protected channel, don't allow removal.
		$existing = vB_Library::instance('node')->getNodes($nodeids);
		// need to see if we require authentication
		$currentUserid = vB::getCurrentSession()->get('userid');
		$userContext = vB::getUserContext();
		//If any of the nodes are channels we need to rebuild
		$needRebuild = false;
		foreach ($existing as $node)
		{
			if ($node['userid'] != $currentUserid)
			{
				$this->inlinemodAuthCheck();
				break;
			}

			if ($node['contenttypeid'] == vB_Types::instance()->getContentTypeID('vBForum_Channel'))
			{
				$needRebuild = true;
			}

			if (!$userContext->getChannelPermission('moderatorpermissions', 'canremoveposts', $node['nodeid']))
			{
				//one check for channels
				if (($node['starter'] == 0) AND $userContext->getChannelPermission('forumpermissions2', 'canmanageownchannels', $node['nodeid']))
				{

					$starter = vB_Api::instanceInternal('node')->getNode($node['starter']);
					$channel = vB_Api::instanceInternal('node')->getNode($starter['parentid']);

					if ($channel['userid'] != $currentUserid)
					{
						throw new vB_Exception_Api('no_delete_permissions');
					}
				}
				//another for starters
				else if (($node['userid'] == $currentUserid) AND ($node['starter'] == $node['nodeid']) AND
					$userContext->getChannelPermission('forumpermissions', 'candeletethread', $node['nodeid'], false, $node['parentid']))
				{
					continue;
				}
				//another for replies
				else if (($node['userid'] == $currentUserid) AND ($node['starter'] != $node['nodeid']) AND
					$userContext->getChannelPermission('forumpermissions', 'candeletepost', $node['nodeid'], false, $node['parentid']))
				{
					continue;
				}
				else
				{
					throw new vB_Exception_Api('no_delete_permissions');
				}
			}
		}

		if (!is_array($nodeids))
		{
			$nodeids = array($nodeids);
		}
		return $this->library->undeleteNodes($nodeids, $needRebuild);
	}

	/**
	 * Moves nodes to a new parent
	 *
	 * @param	array	Node ids
	 * @param	int	New parent node id
	 * @param	bool	Make topic
	 * @param	bool	New title
	 * @param	bool	Mod log
	 * @param	array	Information to leave a thread redirect. If empty, no redirect is created.
	 *			If not empty, should contain these items:
	 *				redirect (string) - perm|expires	Permanent or expiring redirect
	 *				frame (string) - h|d|w|m|y	Hours, days, weeks, months, years (valid only for expiring redirects)
	 *				period (int) - 1-10	How many hours, days, weeks etc, for the expiring redirect
	 *
	 * @return
	 */
	public function moveNodes($nodeids, $to_parent, $makeTopic = false, $newtitle = false, $modlog = true, array $leaveRedirectData = array())
	{
		if(vB::getUserContext()->isModerator())
		{
			$this->inlinemodAuthCheck();
		}

		$movedNodes = array();
		$to_parent = $this->assertNodeidStr($to_parent);
		$userContext = vB::getUserContext();

		$currentUserid = vB::getCurrentSession()->get('userid');

		$channelAPI = vB_Api::instanceInternal('content_channel');

		$newparent = $this->getNode($to_parent);

		//If the current user has can moderator canmove on the current nodes , or if the user can create in the new channel and is the owner of the moved nodes
		// and has forum canmove, then they can move
		if (
			!$userContext->getChannelPermission('forumpermissions', 'canmove', $to_parent)
			AND
			!$userContext->getChannelPermission('moderatorpermissions', 'canmassmove', $to_parent)
		)
		{
			throw new vB_Exception_Api('no_permission_1');
		}

		$nodes = vB::getDbAssertor()->getRows('vBForum:node',
			array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'nodeid' => $nodeids,
			),
			array(
				'field' => array('publishdate'),
				'direction' => array(vB_dB_Query::SORT_ASC)
			),
			'nodeid'
		);

		$needRebuild = false;
		$firstTitle = false;

		$loginfo = array();
		$parent = $this->getNodeFullContent($to_parent);
		$parent = $parent[$to_parent];
		$cacheEvents = array($to_parent);
		$oldparents = array();
		$newparents = array();
		$channelTypeid = vB_Types::instance()->getContentTypeId('vBForum_Channel');

		foreach ($nodes as $node)
		{
			if ($node['contenttypeid'] == $channelTypeid)
			{
				// If any of the moved nodes are channels, the target must be a channel.
				if ($newparent['contenttypeid'] != $channelTypeid)
				{
					throw new vB_Exception_Api('invalid_request');
				}
				// We should not allow the moving of channels from one root channel to another.
				if ($channelAPI->getTopLevelChannel($newparent['nodeid']) != $channelAPI->getTopLevelChannel($node['nodeid']))
				{
					throw new vB_Exception_Api('cant_change_top_level');
				}
			}

			//Only channels can be moved to categories, UI shouldn't allow this
			if ($parent['contenttypeid'] == $channelTypeid)
			{
				$newrouteid = vB_Api::instanceInternal('route')->getChannelConversationRoute($to_parent);
				if (($node['contenttypeid'] != $channelTypeid) AND (empty($newrouteid) OR !empty($parent['category'])))
				{
					// The node we want to move is not a channel and the parent cannot have conversations (e.g. categories, the root blog channel, the root forum channel)
					throw new vB_Exception_Api('invalid_request');
				}
			}

			if ($node['contenttypeid'] == vB_Types::instance()->getContentTypeID('vBForum_Channel'))
			{
				$needRebuild = true;
			}

			if ($userContext->getChannelPermission('moderatorpermissions', 'canmassmove', $node['nodeid']) OR
				(($currentUserid == $node['userid']) AND $userContext->getChannelPermission('forumpermissions', 'canmove', $node['nodeid'], false, $node['parentid'])))
			{
				if (empty($movedNodes))
				{
					if (empty($node['title']))
					{
						$firstTitle = $node['startertitle'];
					}
					else
					{
						$firstTitle = $node['title'];
					}
				}

				$movedNodes[] = $node['nodeid'];
				$oldparents[$node['nodeid']] = $node['parentid'];
				$newparents[$node['nodeid']] = $to_parent;
				$this->contentAPIs[$node['nodeid']] = vB_Api::instanceInternal('Content_' . vB_Types::instance()->getContentTypeClass($node['contenttypeid']));

				if ($modlog)
				{
					$oldparent = $this->getNode($node['parentid']);

					$extra = array(
						'fromnodeid'	=> $oldparent['nodeid'],
						'fromtitle'		=> $oldparent['title'],
						'tonodeid'		=> $newparent['nodeid'],
						'totitle'		=> $newparent['title'],
					);

					$loginfo[] = array(
						'nodeid'		=> $node['nodeid'],
						'nodetitle'		=> $node['title'],
						'nodeusername'	=> $node['authorname'],
						'nodeuserid'	=> $node['userid'],
						'action'		=> $extra,
					);
				}

				if (!in_array($node['parentid'], $cacheEvents) )
				{
					$cacheEvents[] = $node['parentid'];
				}

				if (!in_array($node['starter'], $cacheEvents) AND intval($node['starter']))
				{
					$cacheEvents[] = $node['starter'];
				}
			}
			else
			{
				throw new vB_Exception_Api('no_permission');
			}
		}

		if (empty($movedNodes))
		{
			return false;
		}

		if (($parent['contenttypeid'] == $channelTypeid) AND $makeTopic)
		{

			if (empty($newtitle))
			{
				if (!empty($firstTitle))
				{
					$newtitle = $firstTitle;
				}
				else
				{
					throw new vB_Exception_Api('notitle');
				}
			}

			$result = vB::getDbAssertor()->assertQuery('vBForum:moveNodes', array(
				'nodeids' =>  array($movedNodes[0]), 'to_parent' => $to_parent));
			// We need to promote give the new node the correct title
			vB::getDbAssertor()->assertQuery('vBForum:node', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'routeid' => vB_Api::instanceInternal('route')->getChannelConversationRoute($to_parent),
				'title' => $newtitle,
				'htmltitle' => vB_String::htmlSpecialCharsUni(vB_String::stripTags($newtitle), false),
				'urlident' => vB_String::getUrlIdent($newtitle),
				'description' => $newtitle,
				vB_dB_Query::CONDITIONS_KEY => array(
					'nodeid' => $movedNodes[0],
				)
			));

			if (count($movedNodes) > 1)
			{
				$grandchildren = array_slice($movedNodes, 1);
				$result = vB::getDbAssertor()->assertQuery('vBForum:moveNodes', array(
					'nodeids' => $grandchildren, 'to_parent' => $movedNodes[0]));
				foreach ($grandchildren as $grandchild)
				{
					$newparents[$grandchild] = $movedNodes[0];
				}
			}
		}
		else
		{
			$result = vB::getDbAssertor()->assertQuery('vBForum:moveNodes', array(
				'nodeids' => $movedNodes, 'to_parent' => $to_parent));
		}

		$userid = vB::getCurrentSession()->get('userid');

		// afterMove requires some ancestors info which we just changed above, let's clear cache before updating
		foreach ($movedNodes as $nodeid)
		{
			vB_Cache::instance()->allCacheEvent('nodeChg_' . $nodeid);
			$this->contentAPIs[$nodeid]->afterMove($nodeid, $oldparents[$nodeid], $newparents[$nodeid]);
		}

		// Leave a thread redirect if required
		// Note: UI only allows leaving a redirect when moving a thread which is one node
		if (!empty($leaveRedirectData) AND count($movedNodes) == 1 AND count($nodes) == 1)
		{
			$node = reset($nodes);

			$redirectData = array(
				'title' => $node['title'],
				'urlident' => $node['urlident'],
				'parentid' => $node['parentid'],
				'tonodeid' => $node['nodeid'],
				'userid' => $node['userid'],
				'publishdate' => $node['publishdate'],
				'created' => $node['created'],
			);

			// handle expiring redirects
			if (isset($leaveRedirectData['redirect']) AND $leaveRedirectData['redirect'] == 'expires')
			{
				$period = (int) isset($leaveRedirectData['period']) ? $leaveRedirectData['period'] : 1;
				$frame = (string) isset($leaveRedirectData['frame']) ? $leaveRedirectData['frame'] : 'm';

				$period = max(min($period, 10), 1);
				$frame = in_array($frame, array('h', 'd', 'w', 'm', 'y'), true) ? $frame : 'm';

				$frames = array(
					'h' => 3600,
					'd' => 86400,
					'w' => 86400 * 7,
					'm' => 86400 * 30,
					'y' => 86400 * 365,
				);

				$redirectData['unpublishdate'] = vB::getRequest()->getTimeNow() + ($period * $frames[$frame]);
			}

			vB_Library::instance('content_redirect')->add($redirectData);
		}

		vB_Api::instance('Search')->purgeCacheForCurrentUser();
		vB_Library_Admin::logModeratorAction($loginfo, 'node_moved_by_x');

		$cacheEvents = array_unique(array_merge($movedNodes, $cacheEvents, array($to_parent)));
		$this->library->clearChildCache($cacheEvents);
		$this->library->clearCacheEvents($cacheEvents);

		if ($needRebuild)
		{
			vB::getUserContext()->rebuildGroupAccess();
		}

		return $movedNodes;
	}

	/**
	 * DEPRECATED Move multiple posts to a new topic or to a new channel
	 *
	 * @param $nodeids
	 * @param string|int $to_parent Parent node id. If to_parent is a string, it should be a route path to the node
	 * @param string $newtitle If parent is a channel, the oldest post will be promoted to a Thread with the new title.
	 * @return array Moved node ids
	 */
	public function movePosts($nodeids, $to_parent, $newtitle = '')
	{
		//You should just call the moveNodes method
		return $this->moveNodes($nodeids, $to_parent, true, $newtitle);
	}

	/**
	 * Clone Nodes and their children deeply into a new parent Node.
	 *
	 * @param array $nodeids Source nodeIDs
	 * @param string|int $to_parent Parent node id. If to_parent is a string, it should be a route path to the node
	 * @param string $newtitle If parent is a channel, the oldest post will be promoted to a Thread with the new title.
	 * @return mixed array of origional nodeids as keys, cloned nodeids as values
	 */
	public function cloneNodes($nodeids, $to_parent, $newtitle = '')
	{
		$userContext = vB::getUserContext();
		if($userContext->isModerator())
		{
			$this->inlinemodAuthCheck();
		}

		$parentid = $this->assertNodeidStr($to_parent);

		$loginfo = array();
		$parent = $this->getNodeFullContent($parentid);
		$parent = $parent[$parentid];
		$channelTypeid = vB_Types::instance()->getContentTypeId('vBForum_Channel');

		$nodes = vB::getDbAssertor()->getRows('vBForum:node',
		array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'nodeid' => $nodeids,
			),
		array(
			'field' => array('publishdate'),
			'direction' => array(vB_dB_Query::SORT_ASC)
			),
			'nodeid'
		);

		foreach ($nodes as $node)
		{
			//Only channels can be moved to categories, UI shouldn't allow this
			if (($node['contenttypeid'] != $channelTypeid) AND (!empty($parent['category'])))
			{
				throw new vB_Exception_Api('invalid_request');
			}

			$oldparent = $this->getNode($node['parentid']);

			$extra = array(
				'fromnodeid'	=> $oldparent['nodeid'],
				'fromtitle'		=> $oldparent['title'],
				'tonodeid'		=> $parent['nodeid'],
				'totitle'		=> $parent['title'],
			);

			$loginfo[] = array(
				'nodeid'		=> $node['nodeid'],
				'nodetitle'		=> $node['title'],
				'nodeusername'	=> $node['authorname'],
				'nodeuserid'	=> $node['userid'],
				'action'		=> $extra,
			);
		}

		$retval = vB::getDbAssertor()->assertQuery('vBForum:cloneNodes', array(
			'nodeids' => $nodeids,
			'parentid' => $parentid,
			'newtitle' => trim($newtitle),
		));

		vB_Library_Admin::logModeratorAction($loginfo, 'node_copied_by_x');

		return $retval;
	}

	/**
	 * Convert node path or id string to node id.
	 *
	 * @param string|int $nodestring Node String. If $nodestring is a string, it should be a route path to the node
	 * @return int Node ID
	 */
	protected function assertNodeidStr($nodestring)
	{
		if (!is_numeric($nodestring))
		{
			// $to_parent is a string. So we think it's a path to the node.
			// We need to convert it back to nodeid.
			$route = vB_Api::instanceInternal('route')->getRoute($nodestring, '');
			if (!empty($route['arguments']['nodeid']))
			{
				$nodestring = $route['arguments']['nodeid'];
			}
			elseif (!empty($route['redirect']))
			{
				$route2 = vB_Api::instanceInternal('route')->getRoute(substr($route['redirect'], 1), '');
				if (!empty($route2['arguments']['nodeid']))
				{
					$nodestring = $route2['arguments']['nodeid'];
				}else if (!empty($route2['arguments']['contentid']))
				{
					$nodestring = $route2['arguments']['contentid'];
				}
			}

			unset($route, $route2);
		}
		else
		{
			$nodestring = intval($nodestring);
		}

		return $nodestring;
	}

	/**
	 * Merge several topics into a target topic
	 *
	 * @param array $nodeids Source topic node IDs
	 * @param int $targetnodeid Target topic node ID
	 *
	 * @return array
	 */
	public function mergeTopics($nodeids, $targetnodeid)
	{
		$this->inlinemodAuthCheck();

		$mergedNodes = array();
		$sourceNodes = array();

		if (count($nodeids) < 2)
		{
			throw new vB_Exception_Api('not_much_would_be_accomplished_by_merging');
		}

		if (!in_array($targetnodeid, $nodeids))
		{
			throw new vB_Exception_Api('invalid_target');
		}

		$userContext = vB::getUserContext();

		$nodes = vB::getDbAssertor()->getRows('vBForum:node',
			array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'nodeid' => $nodeids,
			),
			array(
				'field' => array('publishdate'),
				'direction' => array(vB_dB_Query::SORT_ASC)
			)
		);

		$loginfo = array();
		$targetnode = $this->getNode($targetnodeid);

		foreach ($nodes as $node)
		{
			if (intval($node['inlist']) AND !intval($node['protected'])
				AND
				(
					$userContext->getChannelPermission('forumpermissions', 'canview', $node['nodeid'], false, $node['parentid'])
				)
				AND $node['nodeid'] == $node['starter'] // Node must be a topic
			)
			{
				$mergedNodes[] = $node['nodeid'];

				if ($node['nodeid'] != $targetnodeid)
				{
					$sourceNodes[] = $node['nodeid'];

					$extra = array(
						'targetnodeid'	=> $targetnode['nodeid'],
						'targettitle'	=> $targetnode['title'],
					);

					$loginfo[] = array(
						'nodeid'		=> $node['nodeid'],
						'nodetitle'		=> $node['title'],
						'nodeusername'	=> $node['authorname'],
						'nodeuserid'	=> $node['userid'],
						'action'		=> $extra,
					);
				}
			}
		}

		if (count($mergedNodes) < 2)
		{
			throw new vB_Exception_Api('not_much_would_be_accomplished_by_merging');
		}

		if ($mergedNodes == $sourceNodes)
		{
			// Something wrong with target node
			throw new vB_Exception_Api('invalid_target');
		}

		$this->moveNodes($sourceNodes, $targetnodeid, false, false, false); // Dont log the individual moves

		// We need to promote the replies of the sourcenodes to the replies of the targetnode instead of being comments of the sourcenodes.
		foreach ($sourceNodes as $sourcenodeid)
		{
			$sourcereplies = vB::getDbAssertor()->getRows('vBForum:node',
				array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'parentid' => $sourcenodeid,
				),
				array(
					'field' => array('publishdate'),
					'direction' => array(vB_dB_Query::SORT_ASC)
				)
			);

			$replyNodes = array();
			foreach ($sourcereplies as $replies)
			{
				$replyNodes[] = $replies['nodeid'];
			}

			if ($replyNodes)
			{
				// Move the replies to the targetnode
				$this->moveNodes($replyNodes, $targetnodeid, false, false, false);
			}
		}

		// If source node is a poll, we need to delete it
		// Here all source nodes' children have been promoted so they won't be deleted
		foreach ($nodes as $node)
		{
			if ($node['nodeid'] != $targetnodeid AND $node['contenttypeid'] == vB_Types::instance()->getContentTypeId('vBForum_Poll'))
			{
				$this->library->deleteNode($node['nodeid']);
			}
		}

		vB_Library_Admin::logModeratorAction($loginfo, 'node_merged_by_x');

		$clearCacheNodes = array_unique(array_merge($mergedNodes, $sourceNodes, array($targetnodeid)));
		$this->library->clearChildCache($clearCacheNodes);
		$this->clearCacheEvents($clearCacheNodes);

		return array($mergedNodes, $sourceNodes, $targetnodeid);
	}

	/**  Sets the publishdate and (optionally) the unpublish date of a node
	 *	@param	integer	The node id
	 *	@param	integer	The timestamp for publish date
	 *	@param	integer	The timestamp for unpublish date if applicable
	 *
	 *	@return	boolean
	 **/
	public function setPublishDate($nodeid, $datefrom, $dateto = null)
	{
		if (!intval($nodeid) OR !intval($datefrom))
		{
			throw new Exception('invalid_node_id');
		}

		if (!vB::getUserContext()->getChannelPermission('forumpermissions2', 'canpublish', $nodeid))
		{
			throw new Exception('no_publish_permissions');
		}

		return $this->library->setPublishDate($nodeid, $datefrom, $dateto);
	}

	/** Sets the unpublish date
	 *	@param	integer	The node id
	 *	@param	integer	The timestamp for unpublish
	 *
	 *	@return	boolean
	 **/
	public function setUnPublishDate($nodeid, $dateto = false)
	{
		if (!intval($nodeid))
		{
			throw new Exception('invalid_node_id');
		}

		if (!vB::getUserContext()->getChannelPermission('forumpermissions2', 'canpublish', $nodeid))
		{
			throw new Exception('no_publish_permissions');
		}

		return $this->library->setUnPublishDate($nodeid, $dateto);
	}

	/** sets a node to not published
	 *	@param	integer	The node id
	 *
	 *	@return	boolean
	 **/
	public function setUnPublished($nodeid)
	{
		if (!intval($nodeid))
		{
			throw new Exception('invalid_node_id');
		}

		if (!vB::getUserContext()->getChannelPermission('forumpermissions2', 'canpublish', $nodeid))
		{
			throw new Exception('no_publish_permissions');
		}

		return $this->library->setUnPublished($nodeid);
	}

	/*** sets a list of nodes to be featured
	 *	@param	array	The node ids
	 *	@param	boot	set or unset the featured flag
	 *
	 *	@return	array nodeids that have permission to be featured
	 **/
	public function  setFeatured($nodeids, $set = true)
	{
		$this->inlinemodAuthCheck();

		if (!is_array($nodeids))
		{
			$nodeids = array($nodeids);
		}
		$featureIds = array();
		foreach ($nodeids as $nodeid)
		{
			if (!vB::getUserContext()->getChannelPermission('moderatorpermissions', 'cansetfeatured', $nodeid))
			{
				continue;
			}
			$featureIds[] = $nodeid;
		}
		//If this user doesn't have the featured permission and they are trying to set it,
		//throw an exception
		if (empty($featureIds))
		{
			throw new Exception('no_featured_permissions');
		}

		return $this->library->setFeatured($featureIds, $set);
	}

	/*** sets a node list to be not featured
	 *	@param	array	The node ids
	 *
	 *	@return	array nodeids that have permission to be featured
	 **/
	public function  setUnFeatured($nodeids)
	{
		return $this->setFeatured($nodeids, false);
	}


	/** clears the unpublishdate flag.
	 *	@param	integer	The node id
	 *
	 *	@return	boolean
	 **/
	public function  clearUnpublishDate($nodeid)
	{
		if (!intval($nodeid))
		{
			throw new Exception('invalid_node_id');
		}

		if (!vB::getUserContext()->getChannelPermission('forumpermissions2', 'canpublish', $nodeid))
		{
			throw new Exception('no_publish_permissions');
		}

		return $this->library->clearUnpublishDate($nodeid);
	}

	/** gets one node.
	 *	@param	integer The node id
	 *  @param	boolean Whether to include list of parents
	 *  @param	boolean Whether to include joinable content
	 *
	 *	@return	mixed	array of node records, optionally including attachment and ancestry.
	 **/
	public function getNode($nodeid, $withParents = false, $withJoinableContent = false)
	{
		$node = $this->library->getNode($nodeid, $withParents, $withJoinableContent);

		//check permissions.
		$approved = $this->validateNodeList(array($nodeid => $node));

		if (!empty($approved['errors']))
		{
			throw new vB_Exception_Api('invalid_request');
		}
		else if (empty($approved) OR empty($approved[$nodeid]))
		{
			throw new vB_Exception_Api('no_permission');
		}
		else
		{
			return $approved[$nodeid];
		}
	}

	/** Gets the node info for a list of nodes
	 *	@param	array of node ids
	*
	* 	@return	mixed	array of node records
	**/
	public function getNodes($nodeList)
	{
		if (empty($nodeList))
		{
			return array();
		}

		$nodes = $this->library->getNodes($nodeList);
		$nodes = $this->validateNodeList($nodes);
		return $nodes;
	}

	/**
	 * Gets the attachment information for a node. Which may be empty.
	 * @param int $nodeid
	 * @return mixed	either false or an array of attachments with the following fields:
	 *						** attach fields **
	 *						- filedataid
	 *						- nodeid
	 *						- parentid
	 *						- visible
	 *						- counter
	 *						- posthash
	 *						- filename
	 *						- caption
	 *						- reportthreadid
	 *						- settings
	 *						- hasthumbnail
	 *
	 *						** filedata fields **
	 *						- userid
	 *						- extension
	 *						- filesize
	 *						- thumbnail_filesize
	 *						- dateline
	 *						- thumbnail_dateline
	 *
	 *						** link info **
	 *						- url
	 *						- urltitle
	 *						- meta
	 *
	 *						** photo info **
	 *						- caption
	 *						- height
	 *						- width
	 *						- style
	 */
	function getNodeAttachments($nodeid)
	{
		$attachments = array();
		if (empty($nodeid))
		{
			return array();
		}

		//See if we have a cached record
		if (is_numeric($nodeid))
		{
			$hashKey = "attach_$nodeid";
			$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
			$result = $cache->read($hashKey);

			if ($result)
			{
				return $result;
			}
		}
		$rawAttach = $this->library->fetchNodeAttachments($nodeid);

		foreach ($rawAttach AS $attach)
		{
			$attachments[$attach['nodeid']] = $attach;
		}

		if (is_numeric($nodeid))
		{
			$cache->write($hashKey, $attachments, 1440, 'nodeChg_' . $nodeid);
		}
		return $attachments;
	}


	/** lists the nodes that should be displayed on a specific page including content detail.
	 *	@param	integer	The node id of the parent where we are listing
	 *	@param	integer	page number to return
	 *	@param	integer	items per page
	 *	@param	integer	depth- 0 means no stopping, otherwise 1= direct child, 2= grandchild, etc
	 *	@param	mixed	if desired, will only return specific content types.
	 *	@param	mixed	'sort', or 'exclude' recognized..
	 *							Options flags:
	 *							showVm => appends visitor message node info.
	 *							Such as isVisitorMessage flag indicating if node is visitor message and vm_userInfo from the user the visitor message was posted for.
	 *  						withParent => appends information from the parent. This info will append the 'parentConversation' info if the node is a comment.
	 *
	 * 	@return	mixed	array of id's
	 **/
	public function listNodeContent($parentid, $page = 1, $perpage = 20, $depth = 0, $contenttypeid = null, $options = false)
	{
		return $this->listNodeFullContent($parentid, $page, $perpage, $depth, $contenttypeid, $options);
	}

	/** lists the nodes that should be displayed on a specific page including content detail.
	 *	@param	integer	The node id of the parent where we are listing
	 *	@param	integer	page number to return
	 *	@param	integer	items per page
	 *	@param	integer	depth- 0 means no stopping, otherwise 1= direct child, 2= grandchild, etc
	 *	@param	mixed	if desired, will only return specific content types.
	 *	@param	mixed	'sort', or 'exclude' recognized.
	 *							Options flags:
	 *							showVm => appends visitor message node info.
	 *							Such as isVisitorMessage flag indicating if node is visitor message and vm_userInfo from the user the visitor message was posted for.
	 *  						withParent => appends information from the parent. This info will append the 'parentConversation' info if the node is a comment.
	 *
	 * 	@return	mixed	array of id's
	 **/
	public function listNodeFullContent($parentid, $page = 1, $perpage = 20, $depth = 0, $contenttypeid = null, $options = false)
	{
		//First get the node list
		$nodeList = $this->library->listNodes($parentid, $page, $perpage, $depth, $contenttypeid, $options);
		$nodeList = $this->library->addFullContentInfo($nodeList, $options);
		return $this->validateNodeList($nodeList);
	}

	/** Gets the content info for a list of nodes
	 *	@param	mixed	array of node ids
	 *	@param 	mixed	array of options.
	 *						Options flags:
	 *							showVm => appends visitor message node info.
	 *							Such as isVisitorMessage flag indicating if node is visitor message and vm_userInfo from the user the visitor message was posted for.
	 *  						withParent => appends information from the parent. This info will append the 'parentConversation' info if the node is a comment.
	 *
	 * 	@return	mixed	array of content records
	 **/
	public function getContentforNodes($nodeList, $options = false)
	{
		if (empty($nodeList))
		{
			return array();
		}
		$content = $this->library->getContentforNodes($nodeList, $options);
		$content = $this->validateNodeList($content);
		return $content;
	}

	/** Gets the content info for a list of nodes
	 *	@param array array of node ids
	 *	@param 	mixed	array of options.
	 *						Options flags:
	 *							showVm => appends visitor message node info.
	 *							Such as isVisitorMessage flag indicating if node is visitor message and vm_userInfo from the user the visitor message was posted for.
	 *  						withParent => appends information from the parent. This info will append the 'parentConversation' info if the node is a comment.
	 *
	 * 	@return array array of content records -- preserves the original keys
	 **/
	public function getFullContentforNodes($nodeList)
	{
		if (empty($nodeList))
		{
			return array();
		}

		$content = $this->library->getFullContentforNodes($nodeList);
		$content = $this->validateNodeList($content);
		//@todo look over this to see if we can reduce the number of queries.
		return $content;
		}

	/** Given a list of nodes, removes those the user can't see.
	*
	* 	@param	mixed	array of integers
	*
	* 	@return	mixed	array of integers
	**/
	protected function validateNodeList($nodes)
	{
		//@todo.  Let's look into how this works.
		$userid = vB::getCurrentSession()->get('userid');
		$pmIds = array();
		$nodeMap = array();
		//First check permissions
		foreach ($nodes as $key => $node)
		{
			//If they are the author they can see it.
			if ($node['userid'] == $userid)
			{
				continue;
			}
			//if it's a private message, the user can only see if they have a record in sentto or are the originator.
			else if ($node['contenttypeid'] == $this->pmContenttypeid)
			{

				$pmIds[] = $node['nodeid'];
				$nodeMap[$node['nodeid']] = $key;
			}
			else if (!vB::getUserContext()->getChannelPermission('forumpermissions', 'canview', $node['nodeid'], false, $node['parentid']))
			{
				unset($nodes[$key]);
			}
			else if (!vb::getUserContext()->getChannelPermission('forumpermissions', 'canviewthreads', $node['nodeid']))
			{
				//The user can't see content. We hide the content and the "last" data
				unset($nodes[$key]['content']);
				$nodes[$key]['content'] = $node;
				$nodes[$key]['lastcontent'] = $node['publishdate'];
				$nodes[$key]['lastcontentid'] = $node['nodeid'];
				$nodes[$key]['lastcontentauthor'] = $node['authorname'];
				$nodes[$key]['lastauthorid'] = $node['userid'];
				unset($nodes[$key]['photo']);
				unset($nodes[$key]['attach']);
			}
		}

		if (!empty($pmIds))
		{
			//The user can see it if there's a record in sentto
			$sentQry = vB::getDbAssertor()->assertQuery('vBForum:sentto', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			 'nodeid' => $pmIds, 'userid' => $userid));
			$cansee = array();
			foreach ($sentQry as $sentto)
			{
				$cansee[] = $sentto['nodeid'];
			}

			$pmIds = array_diff($pmIds, $cansee);

			foreach ($pmIds as $nodeid)
			{
				$index = $nodeMap[$nodeid];

				if ($index !== false)
				{
					unset($nodes[$index]);
				}
			}
		}

		return $nodes;
	}

	/**	Gets the channel title and routeid
	 *	@param	int		The node id.
	 *	@return	mixed	Array of channel info
	 */
	public function getChannelInfoForNode($channelId)
	{
		$channelInfo = vB_Library::instance('node')->getNodeBare($channelId);
		return array('title' => $channelInfo['title'], 'routeid' => $channelInfo['routeid']);
	}

	/**
	 * Check a list of nodes and see whether the user has voted them
	 *
	 * @param array $nodeIds A list of Nodes to be checked
	 * @param int $userid User ID to be checked. If not there, currently logged-in user will be checked.
	 * @return array Node IDs that the user has voted.
	 * @see vB_Api_Reputation::fetchNodeVotes()
	 */
	protected function getNodeVotes(array $nodeIds, $userid = 0)
	{
		return vB_Api::instanceInternal('reputation')->fetchNodeVotes($nodeIds, $userid);
	}

	/**
	* This gets a content record based on nodeid. Useful from ajax.
	*
	*	@param	int
	*	@param	int	optional
	*	@param	bool optional. 	Options flags:
	*							showVm => appends visitor message node info.
	*							Such as isVisitorMessage flag indicating if node is visitor message and vm_userInfo from the user the visitor message was posted for.
	*  							withParent => appends information from the parent. This info will append the 'parentConversation' info if the node is a comment.
	*
	*	@return array.  An array of node record arrays as $nodeid => $node
	*
	***/
	public function getNodeContent($nodeid, $contenttypeid = false, $options = array())
	{
		return $this->getNodeFullContent($nodeid, $contenttypeid, $options);
	}


	/** This gets a content record based on nodeid including channel and starter information.
	 *
	 * 	@param	int
	 *	@param	int	optional
	 *	@param	bool optional	Options flags:
	*							showVm => appends visitor message node info.
	*							Such as isVisitorMessage flag indicating if node is visitor message and vm_userInfo from the user the visitor message was posted for.
	*  							withParent => appends information from the parent. This info will append the 'parentConversation' info if the node is a comment.
	 *
	 *	@return mixed
	 *
	 ***/
	public function getNodeFullContent($nodeid, $contenttypeid = false, $options = array())
	{
		if (!is_numeric($nodeid))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		$nodeid = intval($nodeid);
		$result = $this->library->getNodeFullContent($nodeid, $contenttypeid, $options);

		foreach ($result AS $node)
		{
			$contentApi = vB_Api_Content::getContentApi($node['contenttypeid']);

			if (!$contentApi->validate($node, vB_Api_Content::ACTION_VIEW, $node['nodeid'], array($node['nodeid'] => $node)))
			{
				throw new vB_Exception_Api('no_permission');
			}
		}

		return $result;
	}

	/** This gets a content record based on nodeid including channel and starter information.
	 *
	 * 	@param	int
	 *	@return mixed
	 *
	 ***/
	public function getQuoteFullContent($quoteId)
	{
		if (!is_array($quoteId))
		{
			$quoteId = array($quoteId);
		}

		$nodeIds = $postIds = $translatedIds = array();
		foreach ($quoteId AS $id)
		{
			if (preg_match('#^n(\d+)$#', $id, $matches))
			{
				// it's a node id
				$nodeIds[] = $matches[1];
			}
			else
			{
				// it's a postid from vB4, we need to translate
				$postIds[] = $id;
			}
		}

		if (!empty($postIds))
		{
			// check cache first
			$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
			$cacheKey = 'vB4_QuoteIds';
			$cached = $cache->read($cacheKey);

			if ($cached)
			{
				foreach($postIds AS $key => $oldId)
				{
					if (isset($cached[$oldId]))
					{
						$translatedIds[$cached[$oldId]] = $oldId;
						unset($postIds[$key]);
					}
				}
			}
			else
			{
				$cached = array();
			}

			// do we still need to translate?
			if (!empty($postIds))
			{
				$nodes = vB::getDbAssertor()->assertQuery('vBForum:fetchLegacyPostIds', array(
					'oldids' => $postIds,
					'postContentTypeId' => vB_Types::instance()->getContentTypeID('vBForum_Post'),
				));

				if ($nodes)
				{
					foreach($nodes as $node)
					{
						$translatedIds[$node['nodeid']] = $node['oldid'];
						$cached[$node['oldid']] = $node['nodeid'];
					}

					$cache->write($cacheKey, $cached, 14400);
				}
			}
		}

		// use the the ids originally requested as keys
		$result = array();
		foreach (array_unique($nodeIds + array_keys($translatedIds)) as $nodeId)
		{
			$info = $this->getNodeFullContent($nodeId);
			$info = $info[$nodeId];

			if (in_array($nodeId, $nodeIds))
			{
				$result["n$nodeId"] =& $info;
			}

			if (isset($translatedIds[$nodeId]))
			{
				$result[$translatedIds[$nodeId]] =& $info;
			}
		}

		return $result;
	}

	/** Validates permission and sets a node to published status
	 *
	 *	@param	mixed	nodeid- integer or array of integers
	 *
	 **/
	public function publish($nodes)
	{
		if (!is_array($nodes))
		{
			$nodes = array($nodes);
		}

		$timeNow = vB::getRequest()->getTimeNow();
		foreach ($nodes as $nodeid)
		{
			if (vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canmoderateposts', $nodeid))
			{
				$this->setPublishDate($nodeid, $timeNow);
			}
		}
	}

	/** Validates permission and sets a node to unpublished status
	 *
	 *	@param	mixed	nodeid- integer or array of integers
	 *
	 **/
	public function unPublish($nodes, $permanent = false)
	{
		if (!is_array($nodes))
		{
			$nodes = array($nodes);
		}

		$approved = array();

		foreach ($nodes as $nodeid)
		{
			if (vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canmoderateposts', $nodeid, false))
			{
				$approved[] = $nodeid;
			}

		}


		if ($permanent)
		{
			$publishdate = -1;
		}
		else
		{
			$publishdate = 0;
		}
		$approvedNodes = vB_Library::instance('node')->getNodes($approved);
		$parents = $starters = array();
		$events = array();
		foreach ($approvedNodes as $approvedNode)
		{
			if (empty($approvedNode['starter']) OR !empty($starters[$approvedNode['starter']]))
			{
				continue;
			}
			$starters[$approvedNode['starter']] = 1;
			$events[] = "nodeChg_" . $approvedNode['starter'];
		}

		if (!empty($starters))
		{
			$starterNodes = vB_Library::instance('node')->getNodes(array_keys($starters));
			foreach ($starterNodes as $starterNode)
			{
				if (empty($starterNode['parentid']) OR !empty($parents[$starterNode['parentid']]))
				{
					continue;
				}
				$parents[$starterNode['parentid']] = 1;
				$events[] = "nodeChg_" . $starterNode['parentid'];
			}
		}
		vB_Cache::instance()->allCacheEvent($events);
		foreach ($approved as $nodeid)
		{
			$updates = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'nodeid' => $nodeid, 'publishdate' => $publishdate, 'unpublishdate' => 0);

			vB::getDbAssertor()->assertQuery('vBForum:node', $updates);
		}
		vB_Api::instance('Search')->purgeCacheForCurrentUser();
	}

	/**
	 *	@param	mixed	array, which can include a channelid, contenttypeid, userid, sort, vB_dB_Query::PARAM_LIMIT and 'fetchall'=>true to get posts which were already flagged
	 *
	 *	@return	mixed	array of nodeid, title, created
	 **/
	public function getModeration($options = array())
	{
		/** defaults are :
		 *	all channels
		 * 	all content types
		 *	maxCount = 100
		 *	fetchall = false
		 *  userid	= all
		 *	sort	created asc (oldest first)
		 *			can be username, created, contenttypeid, or title. If you want other than asc,
		 *			pass array('sortby'=>'username', 'direction' => 'asc' or 'desc'
		 *
		 */

		//If we got passed a channel- first make sure they have permission. If not, we're done.
		if (!empty($options['channelid']))
		{
			if (!vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canmoderateposts', $options['channelid']))
			{
				return false;
			}
			$getModerate = vB::getUserContext()->getCanModerate();

			$parameters = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD, 'canModerate' => $options['channelid'],
				'noModerate' => $getModerate['cannot']);
		}
		else
		{
			$getModerate = vB::getUserContext()->getCanModerate();
			if (empty($getModerate['can']))
			{
				throw new Exception('no_moderate_permissions');
			}
			$parameters = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD, 'canModerate' => $getModerate['can'],
				'noModerate' => $getModerate['cannot']);
		}

		foreach (array('channelid','contenttypeid','sort', vB_dB_Query::PARAM_LIMIT,'fetchall', 'userid') as $optionName)
		{
			if (isset($options[$optionName]))
			{
				$parameters[$optionName] = $options[$optionName];
			}
		}


		$query = vB::getDbAssertor()->assertQuery('vBForum:getModeration', $parameters);

		if (!$query OR !$query->valid())
		{
			return false;
		}

		$results = array();
		$node = $query->current();
		while ($query->valid())
		{
			$results[$node['nodeid']] = $node;
			$node = $query->next();
		}

		return $results;
	}

	/** Adds one or more attachments
	 *
	 * 	@param 	int
	 *	@param mixed	array of attachment info
	 *
	 *	@return	int		an attachid
	 *
	 **/
	public function addAttachment($nodeid, $data)
	{
		/* data must include a filedataid, and possibly
		   any combination of visible,counter,posthash,filename,caption, reportthreadid, settings
		*/
		if (empty($nodeid) OR empty($data['filedataid']))
		{
			throw new Exception('incorrect_attach_data');
		}
		//we need the current node data.
		$node = $this->getNode($nodeid);

		if (empty($node))
		{
			throw new Exception('incorrect_attach_data');
		}

		$maxattachments = vB::getUserContext()->getChannelLimitPermission('forumpermissions', 'maxattachments', $nodeid);

		//check the permission.
		$canAdd = false;
		if (($node['userid'] == vB::getCurrentSession()->get('userid')) AND vB::getUserContext()->getChannelPermission('forumpermissions', 'canpostattachment', $nodeid))
		{
			$canAdd = true;
		}

		if (!$canAdd AND vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canmoderateattachments', $nodeid))
		{
			$canAdd = true;
		}

		if (!$canAdd)
		{
			throw new Exception('no_permission');
		}

		if (isset($node['attachments'])AND ($maxattachments > 0) AND count($node['attachments']) >= $maxattachments)
		{
			throw new Exception('max_attachments_reached');
		}

		$params = array('nodeid' => $nodeid,
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT);

		$fields = array('filedataid','visible','counter','posthash','filename','caption', 'reportthreadid', 'settings');
		foreach ($fields as $field)
		{
			if (isset($data[$field]))
			{
				$params[$field] = $data[$field];
			}
		}


		$attachApi = vB_Api::InstanceInternal('content_attach');
		$data['parentid'] = $nodeid;
		$attachid = $attachApi->add($data);

		//update the refcount in filedata
		if ($attachid)
		{
			 vB::getDbAssertor()->assertQuery('updateFiledataRefCount',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'countChange' => 1, 'filedataid' => $data['filedataid']));
		}
		return is_array($attachid) ? $attachid[0] : $attachid;
	}

	/** delete one or more attachments
	 *
	 * 	@param 	int
	 *	@param 	mixed	an attachment id, or an array of either attach id's, or an array of filedataids
	 *	@param	bool	whether to delete all attachments for this node
	 *
	 *
	 ***/
	public function removeAttachment($nodeid, $data, $all = false)
	{
		/* data must include a filedataid, and possibly
		   any combination of visible,counter,posthash,filename,caption, reportthreadid, settings
		*/
		if (empty($nodeid))
		{
			throw new Exception('incorrect_attach_data');
		}

		//check the permissions.
		$node = vB_Library::instance('node')->getNodeBare($nodeid);
		if (empty($node))
		{
			throw new Exception('incorrect_attach_data');
		}

		if (!($node['userid'] == vB::getCurrentSession()->get('userid')) OR
			!vB::getUserContext()->getChannelPermission('forumpermissions', 'canpostattachment', $nodeid))
		{
			if (! vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canmoderateattachments', $nodeid))
			{
				throw new Exception('no_permission');
			}
		}

		//If we got here we have permission.
		//If all is, we delete all attachments for this node.
		if ($all)
		{
			$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				vB_dB_Query::CONDITIONS_KEY => array('nodeid' => $nodeid));

			return  vB::getDbAssertor()->assertQuery('vBForum:attach', $params);
		}

		if (isset($data['attachid']))
		{
			$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'attachid' => $data['attachid']);

			return  vB::getDbAssertor()->assertQuery('vBForum:attach', $params);
		}


		if (isset($data['filedataid']))
		{
			$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				vB_dB_Query::CONDITIONS_KEY => array());
			if (is_array($data['filedataid']))
			{
				foreach($data['filedataid'] AS $id)
				{
					$params[vB_dB_Query::CONDITIONS_KEY][] = array('filedataid' => $id,
						'nodeid' => $nodeid);
				}
			}
			else
			{
				$params[vB_dB_Query::CONDITIONS_KEY] = array('nodeid' => $nodeid,
				'filedataid' => $data['filedataid']);
			}

			vB::getDbAssertor()->assertQuery('updateFiledataRefCount',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'countChange' => -1, 'filedataid' => $data['filedataid']));

			return  vB::getDbAssertor()->assertQuery('vBForum:attach', $params);
		}

		//if we got here we don't have enough data
		throw new Exception('incorrect_attach_data');
	}

	/** returns id of the Albums Channel
	 *
	 *	@return	integer		array including
	 ***/
	public function fetchAlbumChannel()
	{
		return $this->library->fetchAlbumChannel();
	}

	/** returns id of the Private Message Channel
	 *
	 *	@return	integer		array including
	 ***/
	public function fetchPMChannel()
	{
		return $this->library->fetchPMChannel();
	}

	/** returns id of the Vistor Message Channel
	 *
	 *	@return	integer		array including
	 **/
	public function fetchVMChannel()
	{
		return $this->library->fetchVMChannel();
	}

	/** returns id of the Report Channel
	 *
	 *	@return	integer		array including
	 ***/
	public function fetchReportChannel()
	{
		return $this->library->fetchReportChannel();
	}

	/**
	 * Returns the nodeid of the root forum channel
	 *
	 * @return	integer	The nodeid for the root forum channel
	 */
	public function fetchForumChannel()
	{
		return $this->library->fetchForumChannel();
	}

	/** This returns a list of a user's albums
	*
	*
	*
	**/
	public function listAlbums($userid = false, $page = 1, $perpage = 100, $options= array())
	{
		if (!$userid)
		{
			if (empty($_REQUEST['userid']))
			{
				$userid = vB::getUserContext()->fetchUserId();
	}
			else
			{
				$userid = $_REQUEST['userid'];
			}
		}
		$albumChannel = $this->fetchAlbumChannel();
		$contenttypeid = vB_Types::instance()->getContentTypeId('vBForum_Gallery');
		$nodeList = $this->library->listNodes($albumChannel, $page, $perpage, 0, $contenttypeid, array('includeProtected' => 1,
			'userid' => $userid));
		return $this->library->addContentInfo($nodeList, $options);
	}


	/** returns array of all node content for a user's activity
	 *
	 *	@param	mixed	array- can include userid, sento, date flag, count, page, and  content type.
	 *
	 *	@return	integer		array including
	 **/
	public function fetchActivity($params)
	{
		$userdata = vB::getUserContext()->getReadChannels();
		//It's possible we have a permission record for the current node.
		if (empty($userdata['canRead']) OR !in_array(1, $userdata['canRead']))
		{
			throw new Exception('no_read_permission');
		}
		$exclude = $userdata['cantRead'];

		$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD);

		/** Time filter */
		if (!empty($params[self::FILTER_TIME]))
		{
			$data[self::FILTER_TIME] = $params[self::FILTER_TIME];
		}

		if (!empty($params['contenttypeid']))
		{
			$data['contenttypeid'] = intval($params['contenttypeid']);
		}

		if (!empty($params['contenttypeid']))
		{
			$data['contenttypeid'] = intval($params['contenttypeid']);
		}

		if (!empty($params[self::FILTER_SOURCE]))
		{
			$data[self::FILTER_SOURCE] = $params[self::FILTER_SOURCE];
		}

		//We must have userid
		if (!empty($params['userid']))
		{
			$data['userid'] = intval($params['userid']);
		}
		else
		{
			$userinfo = vB::getCurrentSession()->fetch_userinfo();
			$data['userid'] = $userinfo['userid'];
		}

		if (!empty($params['perpage']))
		{
			$data[vB_dB_Query::PARAM_LIMIT] = intval($params['perpage']);
		}

		if (!empty($params['page']))
		{
			$data[vB_dB_Query::PARAM_LIMITPAGE] = intval($params['page']);
		}

		$results = vB::getDbAssertor()->assertQuery('vBForum:getActivity',
			$data);

		if (!$results->valid())
		{
			return array();
		}

		$nodeids = array();
		foreach ($results as $result)
		{
			$nodeids[] = $result['nodeid'];
		}
		$results = $this->getFullContentforNodes($nodeids);
		return $results;
	}

	/** This returns all the albums in a channel. Those can be photogalleries or text with attachments.
	 *
	 *	@param		int
	 *
	 *	@mixed		array of node records. Each node includes the node content and userinfo, and attachment records.
	 **/
	public function getAlbums($nodeid)
	{

		if (empty($nodeid))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		if (!vB::getUserContext()->getChannelPermission('forumpermissions', 'canview', $nodeid))
		{
			throw new vB_Exception_Api('no_permission');
		}

		return $this->library->getAlbums($nodeid);
	}

	/**
	 * Sets or unsets the sticky field
	 * @param array $nodeids
	 * @param boolean $stick - set or unset the sticky field
	 * @throws vB_Exception_Api
	 * @return array - the nodeids that have the permission to be changed
	 */
	function setSticky($nodeids, $stick = true)
	{
		$this->inlinemodAuthCheck();

		if (empty($nodeids))
		{
			return false;
		}

		if (!is_array($nodeids))
		{
			$nodeids = array($nodeids);
		}

		if (!is_array($nodeids))
		{
			$nodeids = array($nodeids);
		}

		$loginfo = array();
		$stickyNodeIds = array();

		foreach ($nodeids as $nodeid)
		{
			if (!vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canmanagethreads', $nodeid))
			{
				continue;
			}

			$node = $this->getNode($nodeid);

			$loginfo[] = array(
				'nodeid'		=> $node['nodeid'],
				'nodetitle'		=> $node['title'],
				'nodeusername'	=> $node['authorname'],
				'nodeuserid'	=> $node['userid']
			);

			array_push($stickyNodeIds, $nodeid);
		}

		if (empty($stickyNodeIds))
		{
			return false;
		}


		$result = vB::getDbAssertor()->update('vBForum:node', array('sticky' => $stick), array(array('field' => 'nodeid', 'value' => $stickyNodeIds, 'operator' => vB_dB_Query::OPERATOR_EQ)));

		// we need to purge the cache so it is immediately shown
		vB_Api::instance('Search')->purgeCacheForCurrentUser();

		vB_Library_Admin::logModeratorAction($loginfo, ($stick ? 'node_stuck_by_x' : 'node_unstuck_by_x'));

		return $stickyNodeIds;
	}

	/**
	 * Unsets sticky field
	 * @param array $nodeids
	 * @throws vB_Exception_Api
	 * @return array - the nodeids that have the permission to be changed
	 */
	function unsetSticky($nodeids)
	{
		return $this->setSticky($nodeids, false);
	}

	/**
	 * Sets or unsets the approved field
	 * @param array $nodeids
	 * @param boolean $approved - set or unset the approved field
	 * @throws vB_Exception_Api
	 * @return array - the nodeids that have the permission to be changed
	 */
	function setApproved($nodeids, $approved = true)
	{
		if (empty($nodeids))
		{
			return false;
		}

		$currentUserid = vB::getCurrentSession()->get('userid');
		if (empty($currentUserid))
		{
			return false;
		}

		$existing = vB_Library::instance('node')->getNodes($nodeids);
		if (empty($existing))
		{
			return false;
		}

		// need to see if we require authentication
		$userContext = vB::getUserContext();
		$approveNodeIds = array();
		//allow unapproving of VMs by the recipient that has canmanageownprofile
		$need_auth = false;
		$moderateInfo = vB::getUserContext()->getCanModerate();
		foreach ($existing as $node)
		{
			$canModerateOwn = $userContext->getChannelPermission('forumpermissions2', 'canmanageownchannels', $node['nodeid']);

			// check if this is the owner of a blog that needs to moderate the comments
			if (!empty($moderateInfo['can']) OR ($canModerateOwn))
			{
				// let's get the channel node
				$channelid = vB_Library::instance('node')->getChannelId($node);
				if ($channelid == $node['nodeid'])
				{
					$channel = $node;
				}
				else
				{
					$channel = vB_Library::instance('node')->getNodeBare($channelid);
				}

				// this channel was created by the current user so we don't need the auth check
				if ((in_array($channelid, $moderateInfo['can'])) OR ($canModerateOwn AND ($channel['userid'] == $currentUserid)))
				{
					array_push($approveNodeIds, $node['nodeid']);
					continue;
				}
			}

			// don't check permissions if the user is the recipient of the VM
			if (!empty($node['setfor']) AND ($node['setfor'] == $currentUserid) AND $userContext->hasPermission('visitormessagepermissions', 'canmanageownprofile'))
			{
				array_push($approveNodeIds, $node['nodeid']);
			}
			else
			{
				$need_auth = true;
			}
		}
		if ($need_auth)
		{
			$this->inlinemodAuthCheck();
		}

		if (!is_array($nodeids))
		{
			$nodeids = array($nodeids);
		}

		foreach ($nodeids as $nodeid)
		{
			if (!$userContext->getChannelPermission('moderatorpermissions', 'canmanagethreads', $nodeid))
			{
				continue;
			}
			array_push($approveNodeIds, $nodeid);
		}

		if (empty($approveNodeIds))
		{
			return false;
		}

		return $this->library->setApproved($approveNodeIds, $approved);
	}

	/**
	 * Sets the approved field
	 * @param array $nodeids
	 * @throws vB_Exception_Api
	 * @return array - the nodeids that have the permission to be changed
	 */
	function approve($nodeids)
	{
		return $this->setApproved($nodeids, true);
	}

	/**
	 * Unsets the approved field
	 * @param array $nodeids
	 * @throws vB_Exception_Api
	 * @return array - the nodeids that have the permission to be changed
	 */
	function unapprove($nodeids)
	{
		return $this->setApproved($nodeids, false);
	}

	/** This creates a request for access to a channel
	*
	*	@param	int		the nodeid of the channel to which access is requested.
	 *	@param	mixed	the userid/username of the member who will get the request
	 *	@param	string	the type of request-
	*
	**/
	public function requestChannel($channelid, $requestType, $recipient = 0, $recipientname = null)
	{
		if (!vB::getUserContext()->getChannelPermission('forumpermissions', 'canjoin', $channelid)
			OR !vB::getUserContext()->getChannelPermission('forumpermissions', 'canview', $channelid))
		{
			throw new vB_Exception_Api('no_permission');
		}

		return $this->library->requestChannel($channelid, $requestType, $recipient, $recipientname, vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canaddowners', $channelid));

	}
	/**
	 * @uses Fetch statistical indicators for Nodes by given interval
	 * @param int $nodeId The node we are fetching info of
	 * @param int $startdate Start range value, unix timestamp; DEFAULT to 2 months ago
	 * @param int $enddate End range value, unix timestamp; DEFAULT to now
	 * @param string $interval Representation of interval; {daily, monthly}
	 * @return array $response Array containing result statistics needed, values inside 'stats' key
	 */
	public function fetchNodeStats($nodeid, $interval = self::DATE_RANGE_DAILY, $page = 1)
	{
		$assertor = vB::getDbAssertor();
		$response = array();
		if (!vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canmoderateposts', $nodeid))
		{
			throw new vB_Exception_Api('no_permission');
		}
		$timestamp = vB::getRequest()->getTimeNow();
		$perpage = vB::getDatastore()->getOption('maxposts');
		if (empty($perpage))
		{
			$perpage = 20;
		}
		$cutdate = mktime(0,0,0,date('n') - 2, 1, date("Y"));
		if ($interval == self::DATE_RANGE_DAILY)
		{
			$total = vB::getDbAssertor()->getRow('vBForum:nodestats', array(
					vB_dB_Query::CONDITIONS_KEY=> array(
						array('field'=>'nodeid', 'value' => $nodeid, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ),
						array('field'=>'dateline', 'value' => $cutdate, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_GTE)
					),
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_COUNT
			));
			if (($page - 1) * $perpage > $total['count'])
			{
				$page = ceil($total['count'] / $perpage);
			}
			$stats = array(
				'pagingInfo' => array(
						'page' => $page,
						'records' => $total['count'],
						'perpage' => $perpage,
						'totalpages' => ceil($total['count'] / $perpage)
				),
				'stats' => array()
			);
			$qry = vB::getDbAssertor()->assertQuery('vBForum:nodestats', array(
					vB_dB_Query::CONDITIONS_KEY=> array(
						array('field'=>'nodeid', 'value' => $nodeid, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ),
						array('field'=>'dateline', 'value' => $cutdate, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_GTE)
					),
					vB_dB_Query::PARAM_LIMITSTART => ($page - 1) * $perpage, vB_dB_Query::PARAM_LIMIT => $perpage,
					//vB_dB_Query::DEBUG_QUERY => true
				),
				array('field' => 'dateline', 'direction' => vB_dB_Query::SORT_DESC)
			);
			foreach ($qry as $stat)
			{
				$day = date('m-d', $stat['dateline']);
				if (!empty($stats['stats'][$day]))
				{
					$stats['stats'][$day]['replies'] += $stat['replies'];
					$stats['stats'][$day]['visitors'] += $stat['visitors'];
				}
				else
				{
					$stats['stats'][$day] = $stat;
				}
			}
			return $stats;
		}
		else
		{
			$qry = vB::getDbAssertor()->assertQuery('vBForum:nodestats', array(
					vB_dB_Query::CONDITIONS_KEY=> array(
							array('field'=>'nodeid', 'value' => $nodeid, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ),
							array('field'=>'dateline', 'value' => $cutdate, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_GTE)
					),
				),
				array('field' => 'dateline', 'direction' => vB_dB_Query::SORT_DESC)
			);
			$thismonth = date('Y-m');
			foreach ($qry as $stat)
			{
				$month = date('Y-m', $stat['dateline']);
				if (!empty($stats['stats'][$month]))
				{
					$stats['stats'][$month]['replies'] += $stat['replies'];
					$stats['stats'][$month]['visitors'] += $stat['visitors'];
				}
				else
				{
					$stats['stats'][$month] = $stat;
				}

				if ($month == $thismonth)
				{
					$stats['stats'][$thismonth]['upto'] = empty($stats['stats'][$month]['upto']) ? $stat['dateline'] : max($stat['dateline'], $stats['stats'][$month]['upto']);
				}
			}
			return $stats;
		}
	}

	/** Approves a channel request.
	*
	*  	@param	int		id of a request private message.
	*
	**/
	public function approveChannelRequest($messageId)
	{
		$userInfo =  vB::getCurrentSession()->fetch_userinfo();
		$userid = $userInfo['userid'];
		$assertor = vB::getDbAssertor();

		if (!intval($userid))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		$sentto = $assertor->getRow('vBForum:sentto', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'nodeid' => $messageId, 'userid' => $userid));

		if (!$sentto OR !empty($sentto['errors']))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		$messageApi = vB_Api::instanceInternal('content_privatemessage');
		$message = $messageApi->getContent($messageId);
		$message = $message[$messageId];

		if (($message['msgtype'] != 'request' ) OR empty($message['aboutid']))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		switch ($message['about'])
		{
			case self::REQUEST_TAKE_MODERATOR:
				//Can we grant the transfer?
				$userContext = vB::getUserContext($message['userid']);
				if (!$userContext->getChannelPermission('moderatorpermissions', 'canaddowners', $message['aboutid'] ))
				{
					throw new vB_Exception_API('no_permission');
				}
				$usergroupInfo = vB_Api::instanceInternal('usergroup')->fetchUsergroupBySystemID(vB_Api_UserGroup::CHANNEL_MODERATOR_SYSGROUPID);
				$usergroupid = $usergroupInfo['usergroupid'];
				$permUserid = $userid;
				break;

			case self::REQUEST_SG_TAKE_MODERATOR:
				//Can we grant the transfer?
				$userContext = vB::getUserContext($message['userid']);
				if (!$userContext->getChannelPermission('moderatorpermissions', 'canaddowners', $message['aboutid'] ))
				{
					throw new vB_Exception_API('no_permission');
				}
				$usergroupInfo = vB_Api::instanceInternal('usergroup')->fetchUsergroupBySystemID(vB_Api_UserGroup::CHANNEL_MODERATOR_SYSGROUPID);
				$usergroupid = $usergroupInfo['usergroupid'];
				$permUserid = $userid;
				break;

			case self::REQUEST_TAKE_MEMBER:
				$userContext = vB::getUserContext($message['userid']);
				if (!$userContext->getChannelPermission('moderatorpermissions', 'canaddowners', $message['aboutid'] ))
				{
					throw new vB_Exception_API('no_permission');
				}
				$usergroupInfo = vB_Api::instanceInternal('usergroup')->fetchUsergroupBySystemID(vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID);
				$usergroupid = $usergroupInfo['usergroupid'];
				$permUserid = $userid;
				break;

			case self::REQUEST_SG_TAKE_MEMBER:
				$userContext = vB::getUserContext($message['userid']);
				if (!$userContext->getChannelPermission('moderatorpermissions', 'canaddowners', $message['aboutid'] ))
				{
					throw new vB_Exception_API('no_permission');
				}
				$usergroupInfo = vB_Api::instanceInternal('usergroup')->fetchUsergroupBySystemID(vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID);
				$usergroupid = $usergroupInfo['usergroupid'];
				$permUserid = $userid;
				break;

			case self::REQUEST_TAKE_OWNER:
				//Can we grant the transfer?
				$userContext = vB::getUserContext($message['userid']);
				if (!$userContext->getChannelPermission('moderatorpermissions', 'canaddowners', $message['aboutid'] ))
				{
					throw new vB_Exception_API('no_permission');
				}
				//We can't use the user api, because that checks the permissions.

				//let's get the current channels in which the user already is set for that group.
				//Then remove any for which they already are set.
				$usergroupInfo = vB_Api::instanceInternal('usergroup')->fetchUsergroupBySystemID(vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID);

				$groupInTopic = vB_Api::instanceInternal('user')->getGroupInTopic($userid, $message['aboutid']);

				if ($groupInTopic AND in_array($usergroupInfo, $groupInTopic))
				{
					//This user already has this right
					$result = true;
					break;
				}
				//There is only one owner at a time, so we delete the current user;
				$result = $assertor->assertQuery('vBForum:groupintopic', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
					'nodeid' => $message['aboutid'], 'groupid' => $usergroupInfo['usergroupid']));

				//and do the inserts
				$result = $assertor->assertQuery('vBForum:groupintopic', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
					'userid' => $userid, 'nodeid' => $message['aboutid'], 'groupid' => $usergroupInfo['usergroupid']));

				//replace the old owner in the node table as well
				$assertor->update('vBForum:node', array('userid' => $userid), array('nodeid' => $message['aboutid']));
				$myUserContext = vB::getUserContext();
				vB_Cache::instance()->allCacheEvent("userPerms_$userid" );
				$myUserContext->clearChannelPermissions();
				$myUserContext->reloadGroupInTopic();
				$senderUserContext =  vB::getUserContext($message['userid']);
				$senderUserContext->clearChannelPermissions();
				$senderUserContext->reloadGroupInTopic();
				return true;
				break;

			case self::REQUEST_SG_TAKE_OWNER:
				//Can we grant the transfer?
				$userContext = vB::getUserContext($message['userid']);
				if (!$userContext->getChannelPermission('moderatorpermissions', 'canaddowners', $message['aboutid'] ))
				{
					throw new vB_Exception_API('no_permission');
				}
				//We can't use the user api, because that checks the permissions.

				//let's get the current channels in which the user already is set for that group.
				//Then remove any for which they already are set.
				$usergroupInfo = vB_Api::instanceInternal('usergroup')->fetchUsergroupBySystemID(vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID);

				$groupInTopic = vB_Api::instanceInternal('user')->getGroupInTopic($userid, $message['aboutid']);

				if ($groupInTopic AND in_array($usergroupInfo, $groupInTopic))
				{
					//This user already has this right
					$result = true;
					break;
				}
				//There is only one owner at a time, so we delete the current user;
				$result = $assertor->assertQuery('vBForum:groupintopic', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
					'nodeid' => $message['aboutid'], 'groupid' => $usergroupInfo['usergroupid']));

				//and do the inserts
				$result = $assertor->assertQuery('vBForum:groupintopic', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
					'userid' => $userid, 'nodeid' => $message['aboutid'], 'groupid' => $usergroupInfo['usergroupid']));

				//replace the old owner in the node table as well
				$assertor->update('vBForum:node', array('userid' => $userid), array('nodeid' => $message['aboutid']));
				$myUserContext = vB::getUserContext();
				vB_Cache::instance()->allCacheEvent("userPerms_$userid" );
				$myUserContext->clearChannelPermissions();
				$myUserContext->reloadGroupInTopic();
				$senderUserContext =  vB::getUserContext($message['userid']);
				$senderUserContext->clearChannelPermissions();
				$senderUserContext->reloadGroupInTopic();
				return true;
				break;

			case self::REQUEST_GRANT_OWNER:
			case self::REQUEST_SG_GRANT_OWNER:
				$usergroupInfo = vB_Api::instanceInternal('usergroup')->fetchUsergroupBySystemID(vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID);
				$usergroupid = $usergroupInfo['usergroupid'];
				$permUserid = $message['userid'];
				break;
			case self::REQUEST_GRANT_MODERATOR:
			case self::REQUEST_SG_GRANT_MODERATOR:
				$usergroupInfo = vB_Api::instanceInternal('usergroup')->fetchUsergroupBySystemID(vB_Api_UserGroup::CHANNEL_MODERATOR_SYSGROUPID);
				$usergroupid = $usergroupInfo['usergroupid'];
				$permUserid = $message['userid'];
				break;
			case self::REQUEST_GRANT_MEMBER:
			case self::REQUEST_SG_GRANT_MEMBER:
				$usergroupInfo = vB_Api::instanceInternal('usergroup')->fetchUsergroupBySystemID(vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID);
				$usergroupid = $usergroupInfo['usergroupid'];
				$permUserid = $message['userid'];
				break;
			default:
				throw new vB_Exception_API('invalid_data');
		} // switch

		$result = vB_User::setGroupInTopic($permUserid, $message['aboutid'], $usergroupid);

		//last item- if we just granted owner to a new member we should remove anyone else.
		if (($message['userid'] != $userid) AND ($message['about'] == self::REQUEST_GRANT_OWNER))
		{
			$result = $assertor->assertQuery('vBForum:groupintopic', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'nodeid', 'value'=> $message['aboutid'], 'operator' => vB_dB_Query::OPERATOR_EQ),
					array('field' => 'groupid', 'value'=> $usergroupid, 'operator' => vB_dB_Query::OPERATOR_EQ),
					array('field' => 'userid', 'value'=> $message['userid'], 'operator' => vB_dB_Query::OPERATOR_NE))
				));

			//reset the recipient's permissions.
			$myUserContext = vB::getUserContext();
			vB_Cache::instance()->allCacheEvent("userPerms_$userid" );
			$myUserContext->clearChannelPermissions();
			$myUserContext->reloadGroupInTopic();
		}
		vB_Api::instanceInternal('user')->clearChannelPerms($userid);
		return true;
	}



	/** Set the node options
	*
	* 	@param	mixed	options- can be an integer or an array
	*
	* 	@return	either 1 or an error message.
	**/
	public function setNodeOptions($nodeid, $options = false)
	{
		if (empty($nodeid) OR !intval($nodeid) OR ($options === false))
		{
			throw new vB_Exception_Api('invalid_request');
		}

		if (!vB::getUserContext()->getChannelPermission('forumpermissions2', 'canconfigchannel', $nodeid))
		{
			throw new vB_Exception_Api('no_permission');
		}

		return $this->library->setNodeOptions($nodeid, $options);
	}


	/** Set the node special permissions
	*
	 * 	@param	mixed	array with 'viewperms' and/or 'commentperms'
	*
	 * 	@return	either 1 or an error message.
	**/
	public function setNodePerms($nodeid, $perms = array())
	{
		if (empty($nodeid) OR !intval($nodeid) OR
			(!isset($perms['viewperms']) AND !isset($perms['commentperms'])))
		{
			throw new vB_Exception_Api('invalid_request');
		}

		if (!vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canaddowners', $nodeid))
		{
			throw new vB_Exception_Api('no_permission');
		}

		$updates = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'nodeid' => $nodeid);
		$node = $this->getNode($nodeid);

		if (isset($perms['viewperms']) AND is_numeric($perms['viewperms']) AND in_array($perms['viewperms'], array(0,1,2)))
		{
			$updates['viewperms'] = $perms['viewperms'];
		}
		else
		{
			$updates['viewperms'] = $node['viewperms'];
		}


		if (isset($perms['commentperms']) AND is_numeric($perms['commentperms']) AND in_array($perms['commentperms'], array(0,1,2)))
		{
			$updates['commentperms'] = $perms['commentperms'];
		}
		else
		{
			$updates['commentperms'] = $node['commentperms'];
		}
		$result = vB::getDbAssertor()->assertQuery('vBForum:updateNodePerms', $updates);
		$this->clearCacheEvents($nodeid);
		$this->library->clearChildCache($nodeid);
		return $result;
	}

	/**
	 * Validates whether nodes can be merged and returns the merging info. If nodes cannot be merged, an error is returned.
	 * @param array $nodeIds
	 * @return array
	 */
	protected function validateMergePosts($nodeIds)
	{
		$nodes = $this->getContentForNodes($nodeIds);

		$response['contenttypeclass'] = 'Text';
		// check content type constraints
		foreach ($nodes as $node)
		{
			$contentTypeClass = $node['contenttypeclass'];
			$contentTypes[$contentTypeClass] = isset($contentTypes[$contentTypeClass]) ? ($contentTypes[$contentTypeClass] + 1) : 1;

			// currently we cannot merge multiple links or videos
			if ($contentTypes[$contentTypeClass] > 1 AND !in_array($contentTypeClass, array('Text', 'Gallery')))
			{
				return array('error' => 'merge_invalid_contenttypes_multiple');
			}

			// meanwhile, gather the info from it...
			if ($contentTypeClass != 'Text')
			{
				$response['contenttypeclass'] = $contentTypeClass;
			}

			$response['destnodes'][$node['nodeid']] = $node;
			$response['destauthors'][$node['content']['userid']] = $node['content']['authorname'];
			// The mergeContentInfo method is checking view permissions to the $response
			$response['nodeid'] = $node['nodeid'];

			vB_Api::instanceInternal('content_' . $contentTypeClass)->mergeContentInfo($response, $node['content']);
		}

		if (count($contentTypes) > 1)
		{
			// we are merging different contenttypes.
			// If there are two, one must be Text.
			if (count($contentTypes) > 2 || !array_key_exists('Text', $contentTypes))
			{
				return array('error' => 'merge_invalid_contenttypes');
			}
		}

		// we are good to continue...
		asort($response['destauthors']);

		return $response;
	}

	/**
	 * Validates whether nodes can be merged and returns merging info.
	 * @param mixed $nodeIds
	 * @return array
	 */
	public function getMergePostsInfo($nodeIds)
	{
		if (empty($nodeIds))
		{
			throw new vB_Exception_Api('please_select_at_least_one_post');
		}
		else if (is_string($nodeIds))
		{
			$nodeIds = explode(',', $nodeIds);
		}

		$response = $this->validateMergePosts($nodeIds);

		if (isset($response['error']))
		{
			throw new vB_Exception_Api($response['error']);
		}
		else
		{
			return $response;
		}
	}

	/**
	 * Performs the actual merging, using edited input from UI.
	 * @param type $data - Contains pairs (value, name) from edit form in addition to the following fields:
	 *						* mergePosts - posts to be merged
	 *						* destnodeid - target post
	 *						* destauthorid - author to be used
	 *						* contenttype - target contenttype
	 */
	public function mergePosts($input)
	{
		$this->inlinemodAuthCheck();

		foreach ($input as $i)
		{
			if (isset($data[$i['name']]))
			{
				if (!is_array($data[$i['name']]))
				{
					$data[$i['name']] = array($data[$i['name']]);
				}

				$data[$i['name']][] = $i['value'];
			}
			else
			{
				$data[$i['name']] = $i['value'];
			}
		}

		if (empty($data['mergePosts']))
		{
			throw new vB_Exception_Api('please_select_at_least_one_post');
		}
		else if (is_string($data['mergePosts']))
		{
			$data['mergePosts'] = explode(',', $data['mergePosts']);
		}

		// validate that selected nodes can be merged
		$mergeInfo = $this->validateMergePosts($data['mergePosts']);
		if (isset($mergeInfo['error']))
		{
			throw new vB_Exception_Api($mergeInfo['error']);
		}

		// validate form fields
		if (empty($data['destnodeid']) || !array_key_exists($data['destnodeid'], $mergeInfo['destnodes']))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		if (empty($data['destauthorid']) || !array_key_exists($data['destauthorid'], $mergeInfo['destauthors']))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		if (empty($data['contenttype']))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		// if it's a comment, do not use tags
		$destnode =& $mergeInfo['destnodes'][$data['destnodeid']];

		if ($destnode['starter'] != $destnode['nodeid'] AND $destnode['starter'] != $destnode['parentid'])
		{
			if (isset($data['tags']))
			{
				unset($data['tags']);
			}
		}

		$response = vB_Api::instanceInternal("content_{$data['contenttype']}")->mergeContent($data);

		if ($response)
		{
			$sources = array_diff($data['mergePosts'], array($data['destnodeid']));

			$origDestnode = $destnode;
			$destnode = $this->getNode($data['destnodeid']);

			$loginfo = array(
				'nodeid'		=> $destnode['nodeid'],
				'nodetitle'		=> $destnode['title'],
				'nodeusername'	=> $destnode['authorname'],
				'nodeuserid'	=> $destnode['userid']
			);

			// move children to target node
			$children = vB::getDbAssertor()->assertQuery('vBForum:closure', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'parent' => $sources,
				'depth' => 1
			));

			$childrenIds = array();
			foreach ($children AS $child)
			{
				$childrenIds[] = $child['child'];
			}
			if (!empty($childrenIds))
			{
				$this->moveNodes($childrenIds, $data['destnodeid'], false, false, false);
			}

			// remove merged nodes
			$this->deleteNodes($sources, true, null, false); //  Dont log the deletes

			$loginfo['action'] = array('merged_nodes' => implode(',' , $sources));
			$vboptions = vB::getDatastore()->getValue('options');
			if (
				(
					vB_Api::instanceInternal('user')->hasPermissions('genericoptions', 'showeditedby')
						AND
					$destnode['publishdate'] > 0
						AND
					$destnode['publishdate'] < (vB::getRequest()->getTimeNow() - ($vboptions['noeditedbytime'] * 60))
				)
					OR
				!empty($data['reason'])
			)
			{

				$userinfo = vB::getCurrentSession()->fetch_userinfo();
				if ($vboptions['postedithistory'])
				{
					$record = vB::getDbAssertor()->getRow('vBForum:postedithistory',
						array(
							vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
							'original' => 1,
							'nodeid'   => $destnode['nodeid']
					));
					// insert original post on first edit
					if (empty($record))
					{
						vB::getDbAssertor()->assertQuery('vBForum:postedithistory', array(
							vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
							'nodeid'   => $origDestnode['nodeid'],
							'userid'   => $origDestnode['userid'],
							'username' => $origDestnode['authorname'],
							'dateline' => $origDestnode['publishdate'],
							'pagetext' => $origDestnode['content']['rawtext'],
							'original' => 1,
						));
					}
					// insert the new version
					vB::getDbAssertor()->assertQuery('vBForum:postedithistory', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
						'nodeid'   => $destnode['nodeid'],
						'userid'   => $userinfo['userid'],
						'username' => $userinfo['username'],
						'dateline' => vB::getRequest()->getTimeNow(),
						'reason'   => isset($data['reason']) ? vB5_String::htmlSpecialCharsUni($data['reason']) : '',
						'pagetext' => isset($data['text']) ? $data['text'] : ''
					));
				}

				vB::getDbAssertor()->assertQuery('editlog_replacerecord', array(
					'nodeid'     => $destnode['nodeid'],
					'userid'     => $userinfo['userid'],
					'username'   => $userinfo['username'],
					'timenow'    => vB::getRequest()->getTimeNow(),
					'reason'     => isset($data['reason']) ? vB5_String::htmlSpecialCharsUni($data['reason']) : '',
					'hashistory' => intval($vboptions['postedithistory'])
				));
			}

			vB_Library_Admin::logModeratorAction($loginfo, 'node_merged_by_x');

			return true;
		}

	}

	/** gets the node option bitfields
	 *
	 * 	@return	array 	associative array of bitfield name => value
	 **/
	public function getOptions()
	{
		return $this->options;
	}


	/**
	 * Gets the list of unapproved posts for the current user
	 *
	 * @param	int		User id. If not specified will take current User
	 * @param	mixed	Options used for pagination:
	 * 						page 		int		number of the current page
	 * 						perpage		int		number of the results expected per page.
	 * 						totalcount	bool	flag to indicate if we need to get the pending posts totalcount
	 *
	 * @return	mixed	Array containing the pending posts nodeIds with contenttypeid associated.
	 */
	public function listPendingPosts($userid = false, $options = array())
	{
		return $this->library->listPendingPosts($userid, $options);
	}

	/**
	 * Function wrapper for listPendingPosts but used for current user.
	 *
	 */
	public function listPendingPostsForCurrentUser($options = array())
	{
		return $this->library->listPendingPostsForCurrentUser($options);
	}

	/**
	 * Approves a post. Since the publish date might be affected user will need moderate and
	 * publish posts permissions.
	 *
	 * @param	int		Id from the node we are approving.
	 * @param	int		Boolean used to set or unset the approved value
	 *
	 * @return	bool	Flag to indicate if approving went succesfully done (true/false).
	 */
	public function setApprovedPost($nodeid = false, $approved = false)
	{
		// @TODO remove and uncomment this when implementing VBV-585
		return $this->setApproved($nodeid, $approved);
		/*if (!intval($nodeid))
		{
			throw new vB_Exception_Api('invalid_node_id');
		}

		// let's check that node really exists
		$node = $this->getNode($nodeid);

		// and call the content api class to set approve
		if (empty($this->contentAPIs[$node['nodeid']]))
		{
			$this->contentAPIs[$node['nodeid']] =
				vB_Api::instanceInternal('Content_' . vB_Types::instance()->getContentTypeClass($node['contenttypeid']));
		}
		return $this->contentAPIs[$node['nodeid']]->setApproved($node, $approved);*/
	}

	/**
	 * Clears the cache events from a given list of nodes.
	 * Useful to keep search results updated due node changes.
	 *
	 * @param	array		List of node ids to clear cached results.
	 *
	 * @return
	 */
	public function clearCacheEvents($nodeIds)
	{
		return $this->library->clearCacheEvents($nodeIds);
	}
	/**
	* Marks a node as read using the appropriate method.
	*
	* @param int $nodeid The ID of node being marked
	*
	* @return	array	Returns an array of nodes that were marked as read
	*/
	public function markRead($nodeid)
	{
		return $this->library->markRead($nodeid);
	}


	/**
	* Marks a channel, its child channels and all contained topics as read
	*
	* @param int $nodeid The node ID of channel being marked. If 0, all channels will be marked as read
	*
	* @return	array	Returns an array of channel ids that were marked as read
	*/
	public function markChannelsRead($nodeid = 0)
	{
		return $this->library->markChannelsRead($nodeid);
	}

	/**
	 * Fetches the moderator logs for a node
	 * @param int $nodeid
	 * @return array $logs list of log records
	 */
	public function fetchModLogs($nodeid)
	{
		if (!vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canmoderateposts', $nodeid))
		{
			$node = $this->getNode($nodeid);
			if ($node['userid'] != vB::getCurrentSession()->get('userid'))
			{
				throw new vB_Exception_Api('no_permission');
			}
			// do not throw an error if the author requests logs, just don't show them to it.
			else
			{
				return array();
			}
		}

		$logs = array();
		$log_res = vB::getDbAssertor()->assertQuery('getModLogs', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'nodeid' => $nodeid));

		foreach ($log_res as $log)
		{
			$phrase_name = vB_Library_Admin::GetModlogAction($log['type']);
			$phrase = vB_Api::instanceInternal('phrase')->fetch($phrase_name);
			if (!isset($phrase[$phrase_name]))
			{
				continue;
			}
			$phrase = $phrase[$phrase_name];
			$log['action'] = vsprintf($phrase, $log['username']);
			$logs[] = $log;
		}
		return $logs;
	}


	public function manageDeletedNode($nodeid, $params)
	{
		$currentnode = vB_Api::instanceInternal('node')->getNode($nodeid);
		if (empty($currentnode))
		{
			throw new vB_Exception_Api('invalid_target');
		}

		if ($currentnode['userid'] != vB::getCurrentSession()->get('userid'))
		{
			$this->inlinemodAuthCheck();
			if (!vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canmoderateposts', $nodeid))
			{
				throw new vB_Exception_Api('no_permission');
			}
		}

		// hard deleting the node. There is no point doing anything else beyond this
		if (!empty($params['deletetype']) AND $params['deletetype'] == 3)
		{
			$this->deleteNodes($nodeid, true, empty($params['reason']) ? '' : $params['reason']);
			return;
		}


		$is_topic = ($currentnode['starter'] == $currentnode['nodeid']);
		$is_reply = ($currentnode['starter'] == $currentnode['parentid']);

		$nodeFields = array();

		if (!empty($params['reason']))
		{
			$nodeFields['deletereason'] = $params['reason'];
		}

		if ($is_topic AND !empty($params['topictitle']) AND $params['topictitle'] != $currentnode['title'])
		{
			$nodeFields['title'] = $params['topictitle'];
		}
		//updating the node if needed
		if (!empty($nodeFields))
		{
			vB_Api::instanceInternal('Content_' . vB_Types::instance()->getContentTypeClass($currentnode['contenttypeid']))->update($nodeid, $nodeFields);
		}

		// add moderation note
		if ($is_topic AND !empty($params['moderator_notes']))
		{
			$userinfo = vB::getCurrentSession()->fetch_userinfo();
			$route = 'profile';
			if (class_exists('vB5_Cookie') AND vB5_Cookie::isEnabled())
			{
				// session is stored in cookies, so do not append it to url
				$route .= '|nosession';
			}
		}

		// undelete
		if (!empty($params['deletetype']) AND $params['deletetype'] == 2)
		{
			$this->undeleteNodes($nodeid);
			if ($is_topic)
			{
				if (!empty($params['option_open']) AND !$currentnode['open'])
				{
					$this->openNode($nodeid);
				}
				elseif (empty($params['option_open']) AND $currentnode['open'])
				{
					$this->closeNode($nodeid);
				}

				if (!empty($params['option_sticky']) AND !$currentnode['sticky'])
				{
					$this->setSticky($nodeid, true);
				}
				elseif (empty($params['option_sticky']) AND $currentnode['sticky'])
				{
					$this->setSticky($nodeid, false);
				}
			}
			return;
		}

		//delete attachments
		if (!empty($params['deletetype']) AND $params['deletetype'] == 1 AND empty($params['keep_attachments']))
		{
			$attachments = $this->getNodeAttachments($nodeid);
			if (!empty($attachments))
			{
				$this->deleteNodes(array_keys($attachments), true, empty($params['reason']) ? '' : $params['reason']);
			}
		}

	}

	/**
	 * Check whether current logged-in user is "authenticated" for moderation actions.
	 * If the user is a moderator but not "authenticated", this will throw an exception.
	 * If the user is not a moderator, this won't throw the exception!
	 *
	 * @throws vB_Exception_Api inlinemodauth_required if we need user to login again
	 */
	protected function inlinemodAuthCheck()
	{
		$session = vB::getCurrentSession();

		if (!$session->validateCpsession())
		{
			throw new vB_Exception_Api('inlinemodauth_required');
		}
	}

	/**
	 * Checks if ip should be shown
	 *
	 * @return	bool	If the ip of the poster should be posted or not
	 */
	public function showIp($nodeid)
	{
		$logip = vB::getDatastore()->getOption('logip');
		if ($logip == 2)
		{
			return true;
		}
		else if ($logip == 1 AND vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canviewips', $nodeid))
		{
			return true;
		}

		return false;
	}

	public function getHostName($ip)
	{
		return @gethostbyaddr($ip);
	}

	/**
	 * Fetch userids from given nodeids
	 * @param array $nodeids Node IDs
	 * @return array User IDs.
	 */
	public function fetchUseridsFromNodeids($nodeids)
	{
		foreach ($nodeids as &$nodeid)
		{
			$nodeid = intval($nodeid);
		}
		$nodes = vB_Library::instance('node')->getNodes($nodeids);

		$userids = array();
		foreach ($nodes as $node)
		{
			if ($node['inlist'] == 1 AND $node['protected'] == 0 AND !in_array($node['userid'], $userids))
			{
				$userids[] = $node['userid'];
			}
		}

		return $userids;
	}


	/**
	 * Fetch node tree structure of the specified parent id as the root. Root is excluded from the tree structure.
	 * If parentid is not specified or less than 1, it is automatically set to the top-most level special category "Forum"
	 *
	 * @param integer $parentid
	 * @param integer $depth
	 * @param integer $pagenum
	 * @param integer $perpage
	 * @param integer $options
	 */
	public function fetchChannelNodeTree($parentid = 0, $depth = 3, $pagenum = 1, $perpage = 20)
	{
		//the default and most complex, which is pulling for the home page, is worth caching.
		if ($parentid < 1 AND $depth == 3)
		{
			$cacheKey = 'vB_ChannelTree_' . vB::getCurrentSession()->get('userid');
			$cached = vB_Cache::instance(vB_Cache::CACHE_LARGE)->read($cacheKey);

			if ($cached)
			{
				return $cached;
			}
		}

		$channelApi = vB_Api::instanceInternal('content_channel');
		$channels = $channelHierarchy = $channelTree = array();

		if ($parentid < 1)
		{
			$roots = $channelApi->fetchTopLevelChannelIds();
			$parentid = $roots['forum'];
		}

		$channelTypeid = vB_Api::instanceInternal('ContentType')->fetchContentTypeIdFromClass('Channel');
		$nodeQry = vB::getDbAssertor()->assertQuery('vBForum:getChannelTree', array('parentid' => $parentid,
			'channelType' => $channelTypeid, 'depth' => $depth));

		$nodesOnly = array();
		$userContext = vB::getUserContext();
		foreach($nodeQry AS $node)
		{
			if ($userContext->getChannelPermission('forumpermissions', 'canview', $node['nodeid']))
			{
				$nodeList[$node['nodeid']] = $node['nodeid'];

				if (!$userContext->getChannelPermission('forumpermissions', 'canviewthreads', $node['nodeid'])
				OR !$userContext->getChannelPermission('forumpermissions', 'canviewothers', $node['nodeid']))
				{
					$nodesOnly[$node['nodeid']] = $node['nodeid'];
				}
			}
		}
		$nodes = $this->getFullContentforNodes($nodeList);

		if (!empty($nodes) AND empty($nodes['errors']))
		{
			//We want some detail on the lastcontent record
			$lastIds = array();
			foreach ($nodes AS $node)
			{
				//There might be some channels for which this user can't see content.
				if (empty($nodesOnly) OR array_key_exists($node['nodeid'], $nodesOnly))
				{
					if (($node['lastcontentid'] > 0) AND !array_key_exists($node['lastcontentid'], $lastIds))
					{
						$lastIds[$node['lastcontentid']] = $node['lastcontentid'];
					}

					$channels[$node['nodeid']] = array(
						'nodeid' 		=> $node['nodeid'],
						'routeid' 		=> $node['routeid'],
						'title'			=> $node['title'],
						'description'	=> $node['description'],
						'parentid' 		=> $node['parentid'],
						'textcount'		=> $node['textcount'],
						'totalcount'	=> $node['totalcount'],
						'viewing'		=> 0, //@TODO: is the number of 'viewing' users implemented in api?
						'readtime' 		=> isset($node['readtime']) ? $node['readtime'] : null,
						'is_new'		=> empty($node['readtime']) OR (!empty($node['lastcontent']) AND $node['lastcontent'] > $node['readtime']),
						'category' 	    => $node['content']['category'],
						'displayorder'  => $node['displayorder'],
						'parents'  		=> $node['parents'],
						'subchannels' 	=> array(),
					);

					if (array_key_exists($node['nodeid'], $nodesOnly))
					{
						$channels[$node['nodeid']]['lastcontent'] = array(
						'nodeid'	=> 0,
						'title'		=> '',
						'authorname'=> '',
						'userid'	=> '',
						'starter'	=> array());

					}
					else
					{
						$channels[$node['nodeid']]['lastcontent'] = array(
						'nodeid'	=> $node['lastcontentid'],
						'title'		=> '',
						'authorname'=> $node['lastcontentauthor'],
						'userid'	=> $node['lastauthorid'],
						'starter'	=> array());
					}
				}
				else
				{
					$channels[$node['nodeid']] = array(
						'nodeid' 		=> $node['nodeid'],
						'routeid' 		=> $node['routeid'],
						'title'			=> $node['title'],
						'description'	=> $node['description'],
						'parentid' 		=> $node['parentid'],
						'textcount'		=> $node['textcount'],
						'totalcount'	=> $node['totalcount'],
						'viewing'		=> 0, //@TODO: is the number of 'viewing' users implemented in api?
						'readtime' 		=> isset($node['readtime']) ? $node['readtime'] : null,
						'is_new'		=> empty($node['readtime']) OR (!empty($node['lastcontent']) AND $node['lastcontent'] > $node['readtime']),
						'category' 	    => $node['content']['category'],
						'displayorder'  => $node['displayorder'],
						'parents'  		=> $node['parents'],
						'subchannels' 	=> array(),
					);
				}
				if (!empty($node['readtime']))
				{
					$channels[$node['nodeid']]['readtime'] = $node['readtime'];
				}
			}


			unset($nodes);
			//first we get the lastcontent record.
			$nodes = $this->getNodes($lastIds);

			// Check if we have any prefixes
			$phrasevars = array();
			foreach ($nodes as $node)
			{
				if (!empty($node['prefixid']))
				{
					$phrasevars[] = 'prefix_' .  $node['prefixid'] . '_title_plain';
					$phrasevars[] = 'prefix_' .  $node['prefixid'] . '_title_rich';
				}
			}
			$phrases = array();
			if ($phrasevars)
			{
				$phrases = vB_Api::instanceInternal('phrase')->fetch($phrasevars);
			}

			$lastIds = array();
			foreach ($channels AS $key => $channel)
			{
				$nodeid = $channel['lastcontent']['nodeid'];

				if (isset($nodes[$nodeid]))
				{
					$node =& $nodes[$nodeid];
					$channels[$key]['lastcontent']['title'] = $node['title'];
					$channels[$key]['lastcontent']['created'] = $node['created'];
					$channels[$key]['lastcontent']['parentid'] = $node['parentid'];
					//if this is a starter we have all the information we need.
					$channels[$key]['lastcontent']['starter']['nodeid'] = $node['starter'];

					if ($node['starter'] == $node['nodeid'])
					{
						$channels[$key]['lastcontent']['starter']['routeid'] = $node['routeid'];
						$channels[$key]['lastcontent']['starter']['title'] = $node['title'];
					}
					else
					{
						//We need another query
						$lastIds[$node['starter']] = $node['starter'];
					}

					if (!empty($node['prefixid']))
					{
						$channels[$key]['lastcontent']['starter']['prefixid'] = $node['prefixid'];
						if (!empty($phrases['prefix_' .  $node['prefixid'] . '_title_plain']))
						{
							$node['prefix_plain'] = $phrases['prefix_' .  $node['prefixid'] . '_title_plain'];
						}
						if (!empty($phrases['prefix_' .  $node['prefixid'] . '_title_rich']))
						{
							$node['prefix_rich'] = $phrases['prefix_' .  $node['prefixid'] . '_title_rich'];
						}
					}

					if (!empty($node['prefix_rich']))
					{
						$channels[$key]['lastcontent']['starter']['prefix_rich'] = $node['prefix_rich'];
					}

					if (!empty($node['prefix_plain']))
					{
						$channels[$key]['lastcontent']['starter']['prefix_plain'] = $node['prefix_plain'];
					}
				}
			}

			//Now get any lastcontent starter information we need
			if (!empty ($lastIds))
			{
				$nodes = $this->getNodes($lastIds);
				foreach ($channels AS $channelId => $channel)
				{
					$nodeid = $channels[$channelId]['lastcontent']['starter']['nodeid'];
					if (isset($nodes[$nodeid]))
					{
						$node =& $nodes[$nodeid];
						$channels[$channelId]['lastcontent']['starter']['routeid'] = $node['routeid'];
						$channels[$channelId]['lastcontent']['starter']['title'] = $node['title'];
					}
				}
			}

			foreach ($channels as $nodeId => $channel)
			{
				$thisParentid = $channel['parentid'];
				if (isset($channels[$thisParentid]))
				{
					// assign by reference, so subchannels can be filled in later
					$channels[$thisParentid]['subchannels'][$nodeId] =& $channels[$nodeId];
				}
				else
				{
					// assign by reference, so subchannels can be filled in later
					$channelHierarchy[$nodeId] =& $channels[$nodeId];
				}
			}

			$channelTree['channels'] = $channelHierarchy;
			$channelTree['root'] = $parentid;
		}
		else if (!empty($nodes) AND !empty($nodes['errors']))
		{
			$channelTree = $nodes;
		}

		if (!empty($channelTree['channels']))
		{
			$totals = array();
			$this->processTotals($totals, $channelTree['channels']);
			$this->addTotalsToTree($totals, $channelTree['channels']);
		}

		//cache the value
		if (isset($cacheKey))
		{
			vB_Cache::instance(vB_Cache::CACHE_LARGE)->write($cacheKey, $channelTree, 1, array('vB_ChannelStructure_chg', 'perms_changed'));
		}

		return $channelTree;
	}

	/* Process channel topic & post counts,
	   taking into account totals for sub forums */
	private function processTotals(&$totals, $channels)
	{
		foreach($channels AS $channel)
		{
			$parents = $channel['parents'];
			$subchannels = $channel['subchannels'];

			/* Remove top levels, they wont do any harm
			but we dont really need them, so no need to count them */
			array_pop($parents); // Overall Root
			array_pop($parents); // Froum Root Channel

			foreach($parents AS $parent)
			{
				$totals[$parent]['topics'] += $channel['textcount'];
				$totals[$parent]['posts'] += $channel['totalcount'];

				if (!isset($totals[$parent]['title']))
				{ // Not strictly necessary, helps with debugging
					$totals[$parent]['title'] = $channel['title'];
				}
			}

			if ($subchannels)
			{
				$this->processTotals($totals, $subchannels);
			}
		}
	}

	/* Add the processed topic & post counts,
	   back to the original channel tree array */
	private function addTotalsToTree($totals, &$channels)
	{
		foreach($channels AS &$channel)
		{
			$subchannels =& $channel['subchannels'];
			$channel['posts'] = $totals[$channel['nodeid']]['posts'];
			$channel['topics'] = $totals[$channel['nodeid']]['topics'];

			if ($subchannels)
			{
				$this->addTotalsToTree($totals, $subchannels);
			}
		}
	}

	/**
	 * Returns the node read time for the current user and the given nodeid
	 *
	 * @param	int	Node id
	 *
	 * @return	int	Read time for the node
	 */
	public function getNodeReadTime($nodeid)
	{
		$nodeid = (int) $nodeid;

		$user = vB::getCurrentSession()->fetch_userinfo();

		$nodeRead = vB::getDbAssertor()->getRow('noderead', array(
			'userid' => $user['userid'],
			'nodeid' => $nodeid,
		));

		return $nodeRead['readtime'];
	}

	/**
	 * Returns the first immediate child node of the given node that was created
	 * after the given timestamp
	 *
	 * @param	int	Parent Node ID
	 * @param	int	Time stamp
	 *
	 * @return	int	Node ID
	 */
	public function getFirstChildAfterTime($parentNodeId, $timeStamp)
	{
		$parentNodeId = (int) $parentNodeId;
		$timeStamp = (int) $timeStamp;

		while(true)
		{
			$newReplies = vB::getDbAssertor()->getRows('vBForum:getRepliesAfterCutoff', array(
				'starter' => $parentNodeId,
				'cutoff' => $timeStamp,
			));

			if (empty($newReplies))
			{
				// topic has no more replies
				break;
			}

			foreach ($newReplies AS $newReply)
			{
				if (vB::getUserContext()->getChannelPermission('forumpermissions', 'canview', $newReply['nodeid']))
				{
					return $newReply['nodeid'];
				}
				$timeStamp = $newReply['publishdate'];
			}
		}

		return false;
	}

}
