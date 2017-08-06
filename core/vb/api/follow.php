<?php
if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
   || #################################################################### ||
   || # vBulletin 5.0.0
   || # ---------------------------------------------------------------- # ||
   || # Copyright  2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
   || # This file may not be redistributed in whole or significant part. # ||
   || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
   || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
   || #################################################################### ||
   \*======================================================================*/


/**
 * vB_Api_Follow
 *
 * @package vBApi
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Api_Follow extends vB_Api
{
	const FOLLOWTYPE = 'type';
	const FOLLOWTYPE_USERS = 'follow_members'; //This means display content posted by this user.
	const FOLLOWTYPE_CONTENT = 'follow_contents'; //This means follow content posted against a node which is not a channel
	const FOLLOWTYPE_CHANNELS = 'follow_channel'; //This means follow content posted against a node which is a channel
	const FOLLOWTYPE_ALL = 'follow_all'; //This means users + content + channels
	const FOLLOWTYPE_ACTIVITY = 'follow_all_activity'; // This means channel + content

	const FOLLOWFILTERTYPE_SORT = 'filter_sort'; // needed for profile subscribed tab and subscriptions widget
	const FOLLOWFILTER_SORTALL = 'all'; // subscriptions widgets
	const FOLLOWFILTER_SORTMOST = 'mostactive'; // subscriptions widgets
	const FOLLOWFILTER_SORTLEAST = 'leastactive'; // subscriptions widgets

	const FOLLOWFILTER_SORTMOSTRECENT = 'sort_recent'; // profile subscribed tab filter
	const FOLLOWFILTER_SORTPOPULAR = 'sort_popular'; // profile subscribed tab filter

	const FOLLOWFILTERTYPE_TIME = 'filter_time'; // profile subscribed tab filter
	const FOLLOWFILTER_LASTDAY = 'time_today'; // profile subscribed tab filter
	const FOLLOWFILTER_LASTWEEK = 'time_lastweek'; // profile subscribed tab filter
	const FOLLOWFILTER_LASTMONTH = 'time_lastmonth'; // profile subscribed tab filter
	const FOLLOWFILTER_ALLTIME = 'time_all'; // profile subscribed tab filter

	const FOLLOWFILTERTYPE_FOLLOW = 'filter_follow';

	// following status
	const FOLLOWING_NO = 0;
	const FOLLOWING_YES = 1;
	const FOLLOWING_PENDING = 2;

	protected $followers = array();
	protected $blocked = array();
	protected $subscriptions = array();
	// cache for getFollowers
	protected $userFollowers = array();
	protected $userFollowing = array();

	protected $userListCache = array();

	protected function __construct()
	{
		parent::__construct();
		$this->assertor = vB::getDbAssertor();
	}

	/** This gets the followers.
	*
	*	@param		int		the userid
	 *	@param		string	type- following($userid field) or followed($relationid)
	 *	@param		string	type- follow, pending or blocked
	*
	* 	@return		mixed	array of userlist records
	**/
	public function getUserList($userid, $direction = 'followed', $type = 'follow')
	{
		if (isset($this->followers[$userid]) AND isset($this->followers[$userid][$direction])
			 AND isset($this->followers[$userid][$direction][$type]))
		{
			return $this->followers[$userid][$direction][$type];
		}

		if (!isset($this->followers[$userid]))
		{
			$this->followers[$userid] = array();
		}

		if (!isset($this->followers[$userid][$direction]))
		{
			$this->followers[$userid][$direction] = array();
		}

		if (!isset($this->followers[$userid][$direction][$type]))
		{
			$this->followers[$userid][$direction][$type] = array();
		}

		$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT);

		if ($direction == 'followed')
		{
			$params['relationid'] = intval($userid);
		}
		else
		{
			$params['userid'] = intval($userid);
		}

		if ($type == 'follow')
		{
			$params['type']	 = 'follow';
			$params['friend'] = 'yes';
		}
		else if ($type == 'ignore')
		{
			$params['type'] = 'ignore';
			$params['friend'] =	'denied';
		}
		else if ($type == 'pending')
		{
			$params['type']	 = 'follow';
			$params['friend'] = 'pending';
		}
		else
		{
			throw new vB_Exception_Api('invalid_data');
		}

		ksort($params);
		$cacheKey = md5(json_encode($params));

		if (!isset($this->userListCache[$cacheKey]))
		{
			$this->userListCache[$cacheKey] = $this->assertor->getRows('userlist', $params);
		}

		if (is_array($this->userListCache[$cacheKey]))
		{
			foreach ($this->userListCache[$cacheKey] AS $result)
			{
				if ($direction == 'following')
				{
					$this->followers[$userid][$direction][$type][$result['relationid']] = $result;
				}
				else
				{
					$this->followers[$userid][$direction][$type][$result['userid']] = $result;
				}
			}
		}

		return $this->followers[$userid][$direction];
	}

	/** This gets the subscriptions to content nodes
	*
	*	@param		int		the userid
	*
	* 	@return		mixed	array of subscribediscussion records
	**/
	protected function getSubscribedDiscussion($userid)
	{
		if (isset($this->subscriptions[$userid]))
		{
			return $this->subscriptions[$userid];
		}

		$this->subscriptions[$userid] = array();
		$result = $this->assertor->getRows('vBForum:subscribediscussion', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'userid' => $userid
			)
		);

		foreach ($result AS $subscribed)
		{
			$this->subscriptions[$userid][$subscribed['discussionid']] = $subscribed['discussionid'];
		}

		return $this->subscriptions[$userid];
	}

	/** This lists all the current followers, based userid param or current users's userid
	*
	* 	@param	int		userid- optional
	* 	@param 	mixed	Array of options to filter the user followers. User for pagination at the moment.
	*
	*	@return	array of user records
	***/
	public function getFollowers($userid = false, $options = array())
	{
		//First- what userid?
		if (!$userid)
		{
			$userid = vB::getUserContext()->fetchUserId();
		}

		if (!$userid)
		{
			throw new vB_Exception_Api('invalid_userid');
		}

		$sortBy = (isset($options[vB_Api_Follow::FOLLOWFILTERTYPE_SORT]) AND in_array($options[vB_Api_Follow::FOLLOWFILTERTYPE_SORT], array(vB_Api_Follow::FOLLOWFILTER_SORTMOST, vB_Api_Follow::FOLLOWFILTER_SORTLEAST, vB_Api_Follow::FOLLOWFILTER_SORTALL)))
			? $options[vB_Api_Follow::FOLLOWFILTERTYPE_SORT] : vB_Api_Follow::FOLLOWFILTER_SORTALL;
		$limitNumber = (isset($options['perpage']) AND !empty($options['perpage'])) ? $options['perpage'] : 100;
		$currentPage = (isset($options['page']) AND !empty($options['page'])) ? $options['page'] : 1;

		$queryData = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'userid' => $userid,
			vB_dB_Query::PARAM_LIMITPAGE => $currentPage,
			vB_dB_Query::PARAM_LIMIT => $limitNumber,
			vB_Api_Follow::FOLLOWFILTERTYPE_SORT => $sortBy
		);

		$cacheKey = crc32(serialize($queryData));
		if (isset($this->userFollowers["$userid"]) AND isset($this->userFollowers["$userid"]["$cacheKey"]))
		{
			$result = $this->userFollowers["$userid"]["$cacheKey"];
		}
		else
		{
		$followers = $this->assertor->getRows('vBForum:getUserFollowers', $queryData);
		$result = array();
		$result['results'] = $followers;
		$result['totalcount'] = count($result['results']);
		if (!$followers or !empty($followers['errors']))
		{
			$followers = array('totalcount' => 0);
		}

			$userList = $this->getUserList($userid, 'followed', 'follow');
			$totalCount = (isset($userList['follow'])) ? count($userList['follow']) : count($userList);

		$result['paginationInfo'] = $this->getPaginationInfo(array('totalCount' => $totalCount, 'routeName' => 'subscription',
			'queryParams' => array('userid' => $userid, 'page' => $currentPage), 'page' => $currentPage,
			'perPage' => $limitNumber, 'tab' => 'subscribers'));
			$this->userFollowers["$userid"]["$cacheKey"] = $result;
		}

		$resultAux = array();
		foreach($result['results'] AS $user)
		{
			if(is_array($user) AND isset($user))
			{
				$user['musername'] = vB_Api::instanceInternal("user")->fetchMusername($user);
			}

			$resultAux[] = $user;
		}
		if(!empty($resultAux))
		{
			$result['results'] = $resultAux;
		}
		return $result;
	}

	/**
	 * Gets the followers for the current user
	 *
	 * @param	mixed	Array of options to filters the user followers. User for pagination at the moment.
	 */
	public function getFollowersForCurrentUser($options = array())
	{
		return $this->getFollowers(vB::getUserContext()->fetchUserId(), $options);
	}


	/** This lists what a user is following- the parameters, not the content
	 *
	 *	@param	int		User id. If not specified will grab from $_REQUEST or current user.
	 *	@param	string	The type of following to get . Use class constants for this, available are:
	 *					vB_Api_Follow::FOLLOWTYPE_ALL, vB_Api_Follow::FOLLOWTYPE_USERS, vB_Api_Follow::FOLLOWTYPE_CONTENT, vB_Api_Follow::FOLLOWTYPE_CHANNEL
	 *	@param	mixed	Settings to filter the following. Could be sort or/and type. Availables  are:
	 *					vB_Api_Follow::FOLLOWFILTERTYPE_SORT, vB_Api_Follow::FOLLOWFILTER_SORTMOST, vB_Api_Follow::FOLLOWFILTER_SORTLEAST, vB_Api_Follow::FOLLOWFILTER_SORTALL
	 *					vB_Api_Follow::FOLLOWTYPE, vB_Api_Follow::FOLLOWTYPE_USERS, vB_Api_Follow::FOLLOWTYPE_CONTENT, vB_Api_Follow::FOLLOWTYPE_CHANNELS, vB_Api_Follow::FOLLOWFILTER_TYPEALL
	 *	@param	mixed	Content types classes to filter the following. It can be a simple string or an array. The classes should contain 'vBForum_' prefix.
	 *	@param	mixed	Array of options to the following.
	 *
	 *	@return	array with 2 elements- users and nodes.
	 ***/
	public function getFollowing(
		$userid  = false,
		$type    = vB_Api_Follow::FOLLOWTYPE_ALL,
		$filters = array(
			vB_Api_Follow::FOLLOWFILTERTYPE_SORT => vB_Api_Follow::FOLLOWFILTER_SORTALL
		),
		$contenttypeclass = null,
		$options          = array()
	)
	{
		//First- what userid?
		if (!$userid)
		{
			$userid = intval(vB::getUserContext()->fetchUserId());
		}

		if ($userid < 1)
		{
			throw new vB_Exception_Api('invalid_userid');
		}

		if (!in_array($type, array(vB_Api_Follow::FOLLOWTYPE_ALL, vB_Api_Follow::FOLLOWTYPE_USERS, vB_Api_Follow::FOLLOWTYPE_CONTENT, vB_Api_Follow::FOLLOWTYPE_CHANNELS)))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		// if is not set, then set default value
		if (isset($filters[vB_Api_Follow::FOLLOWFILTERTYPE_SORT]))
		{
			switch ($filters[vB_Api_Follow::FOLLOWFILTERTYPE_SORT])
			{
				case vB_Api_Follow::FOLLOWFILTER_SORTMOST:
					$sorts = array('lastactivity' => 'DESC', 'title' => 'ASC');
					break;
				case vB_Api_Follow::FOLLOWFILTER_SORTLEAST:
					$sorts = array('lastactivity' => 'ASC', 'title' => 'ASC');
					break;
				default:
					$sorts = array('title' => 'ASC');
					break;
			}
		}
		else
		{
			$sorts = array('title' => 'ASC');
		}

		$resultsLimit = (isset($options['perpage']) AND intval($options['perpage']))? intval($options['perpage']) : 100;
		$currentPage = (isset($options['page']) AND intval($options['page'])) ? intval($options['page']) : 1;

		$follows = array();
		$totalCount = 0;
		$queryParams = array();
		$cacheKey = crc32(serialize(array($userid, $type, $resultsLimit, $currentPage, $sorts)));
		if (isset($this->userFollowing["$userid"]) AND isset($this->userFollowing["$userid"][$cacheKey]))
		{
			$result = $this->userFollowing["$userid"]["$cacheKey"];
		}
		else
		{
			$contenttypeid = array();
			if (in_array($type, array(vB_Api_Follow::FOLLOWTYPE_CONTENT, vB_Api_Follow::FOLLOWTYPE_CHANNELS)))
			{
				if (($filters[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_CONTENT) AND !empty($contenttypeclass))
				{
					if (is_array($contenttypeclass))
					{
						foreach ($contenttypeclass as $type)
						{
							if ($typeId = vB_Types::instance()->getContentTypeId(trim($type)))
							{
								$contenttypeid[] = $typeId;
								list($prefix, $queryParams['content']) = explode('_', $type);
							}
						}
					}
					else if ($typeId = vB_Types::instance()->getContentTypeId(trim($contenttypeclass)))
					{
						$contenttypeid[] = $typeId;
						list($prefix, $queryParams['content']) = explode('_', $contenttypeclass);
					}
				}
			}

			$follows['results'] = $this->assertor->getRows('vBForum:getUserFollowing',
				array('userid' => $userid, vB_Api_Follow::FOLLOWTYPE => $type,
					vB_Api_Follow::FOLLOWFILTERTYPE_SORT => $sorts, vB_dB_Query::PARAM_LIMITPAGE => $currentPage,
					vB_dB_Query::PARAM_LIMIT => $resultsLimit, 'contenttypeid' => $contenttypeid
				)
			);

			// @TODO using one query to get all total count cases now. We might want to use userlist and subscribediscussion in conjunction to get the total count as we used to before.
			$totalCount = $this->assertor->getField('vBForum:getUserFollowingCount', array('userid' => $userid, vB_Api_Follow::FOLLOWTYPE => $type, 'contenttypeid' => $contenttypeid));

			$queryParams[vB_Api_Follow::FOLLOWFILTERTYPE_SORT] = $filters[self::FOLLOWFILTERTYPE_SORT];
			$queryParams['userid'] = $userid;
			$queryParams['page'] = $currentPage;
			$queryParams[vB_Api_Follow::FOLLOWTYPE] = $type;

			$result = array();
			$result['results'] = $follows['results'];
			$result['totalcount'] = count($follows['results']);
			$result['paginationInfo'] = $this->getPaginationInfo(array('queryParams' => $queryParams,
				'routeName' => 'subscription', 'totalCount' => $totalCount, 'pageUrl' => 'following',
				'userid' => $userid, 'page' => $currentPage, 'perPage' => $resultsLimit, 'tab' => 'subscriptions'));

			$this->userFollowing["$userid"]["$cacheKey"] = $result;
		}

		return $result;
	}

	/** This lists the content for the user's set parameters
	 *
	 *	@param	int		User id. If not specified will grab from $_REQUEST or current user.
	 *	@param	string	The type of following to get . Use class constants for this, available are:
	 *					vB_Api_Follow::FOLLOWTYPE_ALL, vB_Api_Follow::FOLLOWTYPE_USERS, vB_Api_Follow::FOLLOWTYPE_CONTENT, vB_Api_Follow::FOLLOWTYPE_CHANNEL
	 *	@param	mixed	Settings to filter the following. Could be sort or/and type. Availables  are:
	 *					vB_Api_Follow::FOLLOWFILTERTYPE_SORT, vB_Api_Follow::FOLLOWFILTER_SORTMOST, vB_Api_Follow::FOLLOWFILTER_SORTLEAST, vB_Api_Follow::FOLLOWFILTER_SORTALL
	 *					vB_Api_Follow::FOLLOWTYPE, vB_Api_Follow::FOLLOWTYPE_USERS, vB_Api_Follow::FOLLOWTYPE_CONTENT, vB_Api_Follow::FOLLOWTYPE_CHANNELS, vB_Api_Follow::FOLLOWTYPE_ALL
	 *	@param	mixed	Content types classes to filter the following. It can be a simple string or an array. The classes should contain 'vBForum_' prefix.
	 *	@param	mixed	Array of options to the following.
	 *
	 *	@return	array with 2 elements- users and nodes.
	 ***/
	public function getFollowingContent($userid = false, $type = self::FOLLOWTYPE_ALL,
			$filters = array(self::FOLLOWFILTERTYPE_SORT => self::FOLLOWFILTER_SORTALL), $contenttypeclass = null, $options = array())
	{
		//First- what userid?
		if (!$userid)
		{
			$userid = intval(vB::getUserContext()->fetchUserId());
		}

		if ($userid < 1)
		{
			throw new vB_Exception_Api('invalid_userid');
		}

		// set time filter if not specified...
		if (empty($filters[self::FOLLOWFILTERTYPE_TIME]) OR !in_array($filters[self::FOLLOWFILTERTYPE_TIME],
			array(self::FOLLOWFILTER_ALLTIME, self::FOLLOWFILTER_LASTDAY, self::FOLLOWFILTER_LASTWEEK, self::FOLLOWFILTER_LASTMONTH))
		)
		{
			$filters[self::FOLLOWFILTERTYPE_TIME] = self::FOLLOWFILTER_ALLTIME;
		}

		//if it's the simple values let's cache for five minutes.
		$hashKey = false;
		//if (($type == self::FOLLOWTYPE_ALL) AND empty($contenttypeclass))
		if (in_array($type, array(self::FOLLOWTYPE_ALL, self::FOLLOWTYPE_ACTIVITY)) AND empty($contenttypeclass)
			AND ($filters[self::FOLLOWFILTERTYPE_TIME] == self::FOLLOWFILTER_LASTWEEK))
		{
			$cacheOpts = array_merge($options, array('filters' => $filters, 'type' => $type));
			$hashKey = "vB_UserFollowDefault_$userid" . md5(serialize($cacheOpts));
			$content = vB_Cache::instance(vB_Cache::CACHE_LARGE)->read($hashKey);
			if ($content !== false)
			{
				return $content;
			}
		}

		switch ($filters[self::FOLLOWFILTERTYPE_SORT])
		{
			case self::FOLLOWFILTER_SORTPOPULAR:
				$filters[self::FOLLOWFILTERTYPE_SORT] = array('votes' => 'DESC', 'publishdate' => 'DESC', 'title' => 'ASC');
				break;
			default:
				// default should be mostrecent
				$filters[self::FOLLOWFILTERTYPE_SORT] = array('publishdate' => 'DESC', 'title' => 'ASC');
				break;
		}

		$queryData = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			self::FOLLOWTYPE => $type,
			'followerid' => $userid,
			self::FOLLOWFILTERTYPE_TIME => $filters[self::FOLLOWFILTERTYPE_TIME],
			self::FOLLOWFILTERTYPE_SORT => $filters[self::FOLLOWFILTERTYPE_SORT]
		);

		// check for parentid
		if (!empty($options['parentid']))
		{
			$queryData['parentid'] = intval($options['parentid']);
		}

		// check if there's pagesemore in $options
		if (isset($options['pageseemore']))
		{
			$queryData['pageseemore'] = (isset($options['pageseemore']) AND !empty($options['pageseemore']) AND (intval($options['pageseemore']))) ? $options['pageseemore'] : 1;
		}
		else if (empty($options['page']))
		{
			$queryData[vB_dB_Query::PARAM_LIMITPAGE] = (isset($options['page']) AND !empty($options['page']) AND (intval($options['page']))) ? $options['page'] : 1;
		}
		else
		{
			$queryData[vB_dB_Query::PARAM_LIMITPAGE] = 1;
		}
		$queryData[vB_dB_Query::PARAM_LIMIT] = (isset($options['perpage']) AND !empty($options['perpage']) AND intval($options['perpage'])) ? $options['perpage'] : 30;

		// if we have a contenttype class...
		$contentTypes = array();
		$typeText = "";
		if (isset($contenttypeclass) AND !empty($contenttypeclass))
		{
			if (is_array($contenttypeclass))
			{
				foreach ($contenttypeclass as $type)
				{
					// if prefix is not set
					$typeText = (strpos($type, 'vBForum_') === false) ? 'vBForum_' . $type : $type;
					$contentTypes[] = vB_Types::instance()->getContentTypeId($typeText);
				}
			}
			else
			{
				// if prefix is not set
				$typeText = (strpos($contenttypeclass, 'vBForum_') === false) ? 'vBForum_' . $contenttypeclass : $contenttypeclass;
				$contentTypes[] = vB_Types::instance()->getContentTypeId($typeText);
			}
			$queryData['contenttypeid'] = $contentTypes;
		}

		// get Following total count
		$followQry = $this->assertor->assertQuery('vBForum:getFollowingContent' , $queryData);
		$follows = array();
		foreach ($followQry AS $node)
		{
			$follows[] = $node;
		}

		$events = array('userPerms_' . $userid, 'followChg_' . $userid);
		$users = $this->getUserList($userid, 'following', 'follow');

		// we might get the whole userlist
		if (isset($users['follow']))
		{
			$users = $users['follow'];
		}

		foreach ($users AS $following)
		{
			$events[] = 'fUserContentChg_' . $following['relationid'];
		}

		$discussions = $this->getSubscribedDiscussion($userid);
		foreach ($discussions AS $node)
		{
			$events[] = 'nodeChg_' . $node;
		}

		$nodes = array();
		$nodeList = array();
		foreach ($follows as $node)
		{
			// check whether to use the starter node or the last response.
			// added second condition (right side of the OR), $node['lastcontenttypeid'] should never be empty when $node['lastcontentid'] is set but due to a bug it happens so this is to avoid the side effects until it gets fixed
			if (empty($node['lastcontentid']) OR empty($node['lastcontenttypeid']))
			{
				$nodeid = $node['nodeid'];
			}
			else
			{
				$nodeid = $node['lastcontentid'];
			}

			$nodes[$node['nodeid']] = $node;
			$nodeList[$nodeid] = $nodeid;
		}

		if (!empty($nodes))
		{
			$contentList = vB_Library::instance('node')->getFullContentforNodes($nodeList, array('withParent' => 1, 'showVM' => 1));
			foreach ($nodes as $nodeid => $node)
			{
				// check whether to use the starter node or the last response.
				// added second condition (right side of the OR), $node['lastcontenttypeid'] should never be empty when $node['lastcontentid'] is set but due to a bug it happens so this is to avoid the side effects until it gets fixed
				if (empty($node['lastcontentid']) OR empty($node['lastcontenttypeid']))
				{
					$nodes[$nodeid]['content'] = $contentList[$nodeid]['content'];
				}
				else
				{
					$nodes[$nodeid]['content'] = $contentList[$node['lastcontentid']]['content'];
				}
			}

			$cacheData =  array('nodes' => $nodes, 'totalcount' => count($nodes));
		}
		else
		{
			$cacheData =  array('nodes' => array(), 'totalcount' => 0);
		}

		if (!empty($hashKey))
		{
			vB_Cache::instance(vB_Cache::CACHE_LARGE)->write($hashKey, $cacheData, 10, array_unique($events));
		}

		return $cacheData;
	}


	/** Lists the users that are following the given content
	 *
	 *	@param	int		Nodeid
	 *	@param	int		The page (for pagination)
	 *	@param	int		The number of users per page
	 *	@param	bool	Include the user info in the result
	 *
	 *	@return	array with 2 elements- totalcount (total number of users following the content) and one page of users.
	 ***/
	public function getContentFollowers($nodeid, $currentPage = 1, $perpage = 100, $includeFollowInfo = false)
	{
		if (empty($nodeid))
		{
			throw new vB_Exception_Api('invalid_nodeid');
		}

		// if it is not set, then set default value
		if (!isset($filters[vB_Api_Follow::FOLLOWFILTERTYPE_SORT]))
		{
			$filters[vB_Api_Follow::FOLLOWFILTERTYPE_SORT] = vB_Api_Follow::FOLLOWFILTER_SORTALL;
		}

		$follows = array();
		$totalCount = 0;
		$queryParams = array();
		$currentPage = max($currentPage, 1);
		$page = ($currentPage - 1) * $perpage;

		$result = array();

		$result['results'] = $this->assertor->getRows('vBForum:getNodeFollowers', array(
				'nodeid' => $nodeid,
				vB_dB_Query::PARAM_LIMITSTART => $page,
				vB_dB_Query::PARAM_LIMIT => $perpage
			),
			false,
			'userid'
		);

		$count = $this->assertor->getField('vBForum:getNodeFollowersCount', array('nodeid' => $nodeid));
		$result['totalcount'] = $count;
		$result['paginationInfo'] = $this->getPaginationInfo(array('routeName' => 'subscription', 'totalCount' => $count, 'page' => $currentPage, 'perPage' => $perpage, 'tab' => 'subscriptions'));
		if ($count > 0 AND $includeFollowInfo AND ($userid = vB::getCurrentSession()->get('userid')))
		{
			$userQry = $this->assertor->assertQuery('userlist', array(
					'type' => 'follow',
					'userid' => $userid,
					'relationid' => array_keys($result['results'])
			));
			foreach ($userQry as $follower)
			{
				$follow_status = self::FOLLOWING_NO;
				switch ($follower['friend'])
				{
					case 'yes':
						$follow_status = self::FOLLOWING_YES;
					break;
					case 'pending':
						$follow_status = self::FOLLOWING_PENDING;
					break;
				}
				$result['results'][$follower['relationid']]['follow_status'] = $follow_status;
			}
			$result['current_userid'] = $userid;
		}
		return $result;
	}

	/**
	 * Same as getFollowingContent but implements the 'seemore' button logic.
	 * So basically will let the user know if there are more nodes to display using a 'seemore' flag.
	 *
	 *	@param	string	The type of following to get . Use class constants for this, available are:
	 *					vB_Api_Follow::FOLLOWTYPE_ALL, vB_Api_Follow::FOLLOWTYPE_USERS, vB_Api_Follow::FOLLOWTYPE_CONTENT, vB_Api_Follow::FOLLOWTYPE_CHANNEL
	 *	@param	mixed	Settings to filter the following. Could be sort or/and type. Availables  are:
	 *					vB_Api_Follow::FOLLOWFILTERTYPE_SORT, vB_Api_Follow::FOLLOWFILTER_SORTMOST, vB_Api_Follow::FOLLOWFILTER_SORTLEAST, vB_Api_Follow::FOLLOWFILTER_SORTALL
	 *					vB_Api_Follow::FOLLOWTYPE, vB_Api_Follow::FOLLOWTYPE_USERS, vB_Api_Follow::FOLLOWTYPE_CONTENT, vB_Api_Follow::FOLLOWTYPE_CHANNELS, vB_Api_Follow::FOLLOWTYPE_ALL
	 *	@param	mixed	Content types classes to filter the following. It can be a simple string or an array. The classes should contain 'vBForum_' prefix.
	 *	@param	mixed	Array of options to the following.
	 *
	 *	@return	array with 3 elements- users and nodes and paginationinfo.
	 */
	public function getFollowingContentForTab($userid = false, $type = self::FOLLOWTYPE_ALL,
			$filters = array(self::FOLLOWFILTERTYPE_SORT => self::FOLLOWFILTER_SORTALL), $contenttypeclass = null, $options = array())
	{
		// get pagination info
		$pageOptions = array();
		$pageOptions['perpage'] = (isset($options['perpage']) AND !empty($options['perpage']) AND (intval($options['perpage']))) ? $options['perpage'] : 30;
		$pageOptions['pageseemore'] = (isset($options['page']) AND !empty($options['page']) AND (intval($options['page']))) ? $options['page'] : 1;

		// used to check if we are showing a next page (used for subscribed tab)
		$pageOptions['perpage']++;
		// check for parentid
		if (isset($options['parentid']))
		{
			$pageOptions['parentid'] = $options['parentid'];
		}
		$result = $this->getFollowingContent($userid, $type, $filters, $contenttypeclass, $pageOptions);

		// get the paginationInfo pages
		$showSeeMore = ($result['totalcount'] < $pageOptions['perpage']) ? false : true;

		// and get rid of that last element
		if ($showSeeMore)
		{
			array_pop($result['nodes']);
			$result['totalcount']--;
		}

		// and set the right pagination info
		$result['paginationInfo'] = array('currentpage' => $pageOptions['pageseemore'], 'showseemore' => $showSeeMore);
		return $result;
	}

	/**
	 * Gets the following for the current user. Uses $this->getFollowing
	 *
	 * @param String	Indicates the type of the following to fetch. vB_Api_Follow::FOLLOWTYPE_USERS or vB_Api_Follow::FOLLOWTYPE_ALL
	 * @param mixed		Array with options. Used for pagination and sorting at the moment.
	 *
	 * @return mixed	Array with the following info for the current user.
	 */
	public function getFollowingForCurrentUser($type, $options = array())
	{
		// Ensure will be users or all
		$type = ($type == vB_Api_Follow::FOLLOWTYPE_USERS ? vB_Api_Follow::FOLLOWTYPE_USERS : vB_Api_Follow::FOLLOWTYPE_ALL);
		$ftype = ($type == vB_Api_Follow::FOLLOWTYPE_USERS ? vB_Api_Follow::FOLLOWTYPE_USERS : vB_Api_Follow::FOLLOWTYPE_ALL);
		$sort = (isset($options[vB_Api_Follow::FOLLOWFILTERTYPE_SORT]) AND !empty($options[vB_Api_Follow::FOLLOWFILTERTYPE_SORT])) ? $options[vB_Api_Follow::FOLLOWFILTERTYPE_SORT] : vB_Api_Follow::FOLLOWFILTER_SORTALL;

		return $this->getFollowing(vB::getUserContext()->fetchUserId(), $type, array(vB_Api_Follow::FOLLOWFILTERTYPE_SORT => $sort), false, $options);
	}

	/** This adds a following.- ie. the current user will now follow a user, passed
	 *
	 * 	@param	int	follower	The follow item id. (could be either a user or node id)
	 * 	@param	string type- 	The type of the follow add action. USERS, CHANNELS or CONTENT types.
	 * 	@param	int				An optional user id which we will be adding the following item.
	 * 							This will be only applied if we are doing a CHANNELS or CONTENT type subscription and the current user has enough channel permissions to grant the subscription.
	 *
	 *
	 *	@return	int
	 ***/
	public function add($follower, $type = vB_Api_Follow::FOLLOWTYPE_USERS, $followuser = false)
	{
		$userId = vB::getUserContext()->fetchUserId();
		if ($userId <= 0)
		{
			throw new vB_Exception_Api('invalid_user_permissions');
		}

		if (empty($follower))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		// trying to add content subscription with no permissions...
		$followuser = intval($followuser);
		if (!$this->validateFollowInformation(array(
			'userid' => ($followuser) ? $followuser : $userId, 'follow_item' => $follower, vB_Api_Follow::FOLLOWTYPE => $type, 'verify_request' => true
			))
		)
		{
			throw new vB_Exception_Api('no_permission');
		}

		switch ($type)
		{
			case vB_Api_Follow::FOLLOWTYPE_USERS:
				$this->addFollow($follower);
				$this->updateUser($userId);
				break;
			case vB_Api_Follow::FOLLOWTYPE_CHANNELS:
			case vB_Api_Follow::FOLLOWTYPE_CONTENT:
				$this->addSubscription($follower, $followuser);
				break;
			default:
				//just ignore
				break;
		}

		//if we got here, we already have a record.
		if ($type == vB_Api_Follow::FOLLOWTYPE_USERS)
		{
			$this->clearFollowCache(array($userId, $follower));
			$returnVal = $this->isFollowingUser($follower);
		}
		else
		{
			$userId = ($followuser) ? $followuser : $userId;
			unset($this->subscriptions[$userId]);
			unset($this->userFollowing[$userId]);
			$returnVal = vB_Api_Follow::FOLLOWING_YES;
		}

		vB_Cache::allCacheEvent("followChg_$userId");
		//@TODO  purge cache for different user if $followeruser is set and valid...
		vB_Api::instanceInternal('search')->purgeCacheForCurrentUser();

		return $returnVal;
	}

	protected function addFollow($userId)
	{
		$valid = $this->validate($userId);
		if ($valid['canproceed'] == true)
		{
			$userInfo = vB_User::fetchUserinfo($userId);
			$bitfields = vB::getDatastore()->get_value('bf_misc');

			if ($valid['hasRelation'])
			{
				$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					vB_dB_Query::CONDITIONS_KEY => array('userid' => vB::getUserContext()->fetchUserId(), 'relationid' => $userId),
					'type' => 'follow');
			}
			else
			{
				$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
					'type' => 'follow', 'userid' => vB::getUserContext()->fetchUserId(), 'relationid' => $userId);
			}

			if ($userInfo['options'] & $bitfields['useroptions']['moderatefollowers'] )
			{
				$userInfo = vB::getCurrentSession()->fetch_userinfo();
				$request = vB_Library::instance('content_privatemessage')->addMessageNoFlood(array('msgtype' => 'request',
					'sentto' => $userId, 'aboutid' => vB::getCurrentSession()->get('userid'), 'about' => 'follow', 'sender' => $userInfo['userid']));

				$params['friend'] = 'pending' ;

			}
			else
			{
				$params['friend'] = 'yes';
				vB_Library::instance('Content_Privatemessage')->sendNotifications(array(array('about' => vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_FOLLOWING,
					'aboutid' => vB::getUserContext()->fetchUserId(), 'userid' => $userId)));
			}

			$this->assertor->assertQuery('userlist', $params);

			/** Needed for followers subscriptions */
			$currentUser = vB::getUserContext()->fetchUserId();
			$followingRec = $this->assertor->getRow('userlist', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					vB_dB_Query::CONDITIONS_KEY => array('userid' => $userId, 'relationid' => $currentUser, 'type' => 'follow')
				)
			);
			if ($followingRec AND !empty($followingRec) AND $followingRec['friend'] == 'no')
			{
				$this->assertor->update('userlist', array('friend' => 'yes'),
					array('userid' => $userId, 'relationid' => $currentUser, 'type' => 'follow')
				);
			}
		}
		else
		{
			foreach ($valid['errors'] as $error)
			{
				throw new vB_Exception_Api($error);
			}
		}
	}

	protected function addSubscription($nodeId, $followuser = false)
	{
		$valid = $this->validate($nodeId, vB_Api_Follow::FOLLOWTYPE_CONTENT);
		if ($valid['canproceed'] == true)
		{
			$userid = ($followuser) ? $followuser : vB::getUserContext()->fetchUserId();
			$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'userid' => $userid, 'discussionid' => $nodeId);
			$this->assertor->assertQuery('vBForum:subscribediscussion', $params);
		}
		else if (isset($valid['errors']))
		{
			foreach ($valid['errors'] as $error)
			{
				throw new vB_Exception_Api($error);
			}
		}
	}

	protected function validate($followId, $type = vB_Api_Follow::FOLLOWTYPE_USERS)
	{
		/** If we have a follow record then do nothing */
		$return = array('canproceed' => false);
		$userContext = vB::getUserContext();

		if ($type == vB_Api_Follow::FOLLOWTYPE_USERS)
		{
			if ($followId == $userContext->fetchUserId())
			{
				// Same user
				$return['errors'][] = 'invalid_data';
			}
			if (!$userContext->hasPermission('genericpermissions2', 'canusefriends'))
			{
				// The follower doesn't have Can Use Friends List permission
				$return['errors'][] = 'no_permission_subscribe_user';
			}
		}

		// get table
		$table = "";
		switch ($type)
		{
			case vB_Api_Follow::FOLLOWTYPE_CONTENT:
			case vB_Api_Follow::FOLLOWTYPE_CHANNELS:
				$table = 'node';
				break;
			default:
				$table = 'user';
				break;
		}

		/** Let's see if the record exists */
		$queryData = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, vB_dB_Query::CONDITIONS_KEY => array($table . 'id' => $followId));

		$existing = $this->assertor->getRow((($table == 'node') ? ('vBForum:' . $table) : $table), $queryData);
		if (empty($existing) OR !empty($existing['errors']))
		{
			$return['errors'][] = 'invalid_data';
		}

		/** Check if is ignoring me */
		if ($type == vB_Api_Follow::FOLLOWTYPE_USERS)
		{
			$ignored = $this->getUserList($userContext->fetchUserId(), 'followed', 'ignore');

			if ($ignored AND !empty($ignored[$followId]) )
			{
				$return['errors'][] = 'ignored_by_user';
			}
		}

		/** Now let's see if there's a relation between */
		$queryData = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT);
		switch ($type)
		{
			case vB_Api_Follow::FOLLOWTYPE_USERS:
				$queryData[vB_dB_Query::CONDITIONS_KEY] = array('userid' => $userContext->fetchUserId(), 'relationid' => $followId);
				$table = 'userlist';
				break;
			case vB_Api_Follow::FOLLOWTYPE_CONTENT:
				$queryData[vB_dB_Query::CONDITIONS_KEY] = array('userid' => $userContext->fetchUserId(), 'discussionid' => $followId);
				$table = 'subscribediscussion';
			default:
				// just ignore
				break;
		}

		$existing = $this->assertor->getRow((($table == 'subscribediscussion') ? ('vBForum:' . $table) : $table), $queryData);
		if ($existing AND empty($existing['errors']) AND is_array($existing) AND $type == vB_Api_Follow::FOLLOWTYPE_USERS AND empty($return['errors']))
		{
			$return['hasRelation'] = true;
			$return['canproceed'] = true;
		}
		else
		{
			$return['hasRelation'] = false;
		}

		if ((!$existing AND empty($existing['errors'])) AND empty($return['errors']))
		{
			$return['canproceed'] = true;
		}

		return $return;
	}


	/** This deletes a follower. needs userid and followerid, passed or taken from current session
	 *
	 * 	@param	int	follower- optional
	 *
	 *	@return	int
	 ***/
	public function delete($follower = false, $type = vB_Api_Follow::FOLLOWTYPE_USERS, $userid = false)
	{
		if ($userid === false)
		{
			$userid = vB::getUserContext()->fetchUserId();
		}

		if (!intval($userid))
		{
			throw new vB_Exception_Api('invalid_user_permissions');
		}

		if (!intval($follower))
		{
			throw new vB_Exception_Api('insufficient_data');
		}

		if (!$this->validateFollowInformation(array(
			'userid' => $userid, 'follow_item' => $follower, vB_Api_Follow::FOLLOWTYPE => $type
			))
		)
		{
			throw new vB_Exception_Api('no_permission');
		}

		$canProceed = true;
		$isContent = false;
		switch ($type)
		{
			case vB_Api_Follow::FOLLOWTYPE_CHANNELS:
			case vB_Api_Follow::FOLLOWTYPE_CONTENT:
			$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
					vB_dB_Query::CONDITIONS_KEY => array('userid' => $userid, 'discussionid' => $follower)
				);
				$table = 'vBForum:subscribediscussion';
				$isContent = true;
				break;
			case vB_Api_Follow::FOLLOWTYPE_USERS:
				$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
					vB_dB_Query::CONDITIONS_KEY => array('type' => 'follow', 'userid' => $userid, 'relationid' => $follower, 'friend' => 'yes')
				);
				$table = 'userlist';
				break;
			default:
				// just ignore
				$canProceed = false;
				break;
		}

		$response = ($canProceed) ? $this->assertor->assertQuery($table, $params) : false;
		if (!empty($response['errors']) AND is_array($response['errors']))
		{
			return false;
		}

		// delete cached values
		vB_Cache::allCacheEvent("followChg_$userid");
		vB_Api::instanceInternal('search')->purgeCacheForCurrentUser();
		if ($isContent)
		{
			unset($this->subscriptions[$userid]);
		}

		$this->clearFollowCache(array($userid, $follower));

		return 1;
	}

	/**
	 * Validates the follow information pased.
	 * Needed for add/delete methods. We need to do some perm check and validations on the types,
	 * follow item and user.
	 *
	 * @param	Array	Follow information. Such a userid, follow type and follow item.
	 * 					For content and channel follow types you can also pass a flag 'verify_request'
	 * 					to verify that current user has in fact requested subscription to the follow item (useful for add action).
	 *
	 * @return	Bool	Flag indicating if the information is valid or not.
	**/
	protected function validateFollowInformation($data)
	{
		$perm = false;
		$usercontext = vB::getUserContext();
		if ($usercontext->isSuperAdmin())
		{
			$perm = true;
		}
		else if ($data['userid'] == $usercontext->fetchUserId())
		{
			$perm = true;
		}
		else if (in_array($data[vB_Api_Follow::FOLLOWTYPE], array(vB_Api_Follow::FOLLOWTYPE_CONTENT, vB_Api_Follow::FOLLOWTYPE_CHANNELS))
			AND ($usercontext->getChannelPermission('moderatorpermissions', 'canmoderateposts', $data['follow_item']))
		)
		{

			if (isset($data['verify_request']) AND $data['verify_request'])
			{
				$requestid = vB::getDbAssertor()->getRows('vBForum:verifySubscriberRequest',
					array('nodeid' => array($data['follow_item']), 'about' => array(vB_Api_Node::REQUEST_SG_GRANT_SUBSCRIBER), 'userid' => $data['userid'])
				);

				if (!empty($requestid) AND !isset($requestid['errors']))
				{
					$perm = true;
				}
			}
			else
			{
				$perm = true;
			}
		}

		return $perm;
	}

	/**
	 * Removes following from channels or users including all posts related.
	 *
	 * @param	int		The subscription id to remove (userid or nodeid).
	 * @param	int		The current user id. Will be dragged from user context if needed.
	 * @param	string	The follow type of the item we are removing. Might be:
	 * 							vB_Api_Follow::FOLLOWTYPE_USERS, FOLLOWTYPE_CONTENT, FOLLOWTYPE_CHANNELS
	 *
	 * @param	mixed	DB assertor flag indicating if changes were succesfully done or false.
	 */
	public function removeFollowing($followingId = false, $userId = false, $type = self::FOLLOWTYPE_USERS)
	{
		if (!$userId)
		{
			$userId = vB::getUserContext()->fetchUserId();
		}

		if (intval($userId) <= 0)
		{
			throw new vB_Exception_Api('invalid_user_permissions');
		}

		if (!$followingId OR !$userId)
		{
			throw new vB_Exception_Api('insufficient_data');
		}

		//At this point we can delete
		switch ($type) {
			case vB_Api_Follow::FOLLOWTYPE_USERS:
				$response = $this->delete($followingId);
				if ($response AND empty($response['errors']))
				{
					$queryData = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
						'userid' => $userId,
						'memberid' => $followingId
					);

					return $this->assertor->assertQuery('vBForum:deleteMemberFollowing', $queryData);
				}
				break;
			case vB_Api_Follow::FOLLOWTYPE_CONTENT:
			case vB_Api_Follow::FOLLOWTYPE_CHANNELS:
				$response = $this->delete($followingId, vB_Api_Follow::FOLLOWTYPE_CONTENT);
				if ($response AND empty($response['errors']))
				{
					$queryData = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
							'userid' => $userId,
							'channelid' => $followingId
					);

					return $this->assertor->assertQuery('vBForum:deleteChannelFollowing', $queryData);
				}
				break;
			default:
			// just ignore
				break;
		}

		return false;
	}

	/**
	 * This removes a follower from user.
	 *
	 * @param	int		The id from the follower being removed.
	 *
	 * @return	bool	Indicates if the removal succeeded.
	 */
	public function removeFollower($follower = false)
	{
		$userId = intval(vB::getUserContext()->fetchUserId());
		if ($userId <= 0)
		{
			throw new vB_Exception_Api('invalid_user_permissions');
		}

		if ($userId == $follower)
		{
			throw new vB_Exception_Api('removing_user_itself');
		}

		if (!$userId)
		{
			throw new vB_Exception_Api('missing_userid');
		}

		if (!$follower)
		{
			throw new vB_Exception_Api('missing_followerid');
		}

		/** let's block the follower record */
		$result = $this->assertor->update('userlist',
			array('friend' => 'no'),
			array('userid' => $follower, 'relationid' => $userId, 'type' => 'follow', 'friend' => 'yes')
		);

		unset($this->followers[$userId]);
		/** If for some reason delete returns error */
		if (!empty($result['errors']))
		{
			return $result['errors'];
		}

		/** now let's change to ignore */
		return $this->denyFollowing($follower);
	}

	/**
	 * This adds a user follower
	 *
	 * @param	int		Follower id.
	 */
	public function addFollower($follower = false)
	{
		$userId = vB::getUserContext()->fetchUserId();
		if ($userId <= 0)
		{
			throw new vB_Exception_Api('invalid_user_permissions');
		}

		/** if not followerId */
		if (!$follower)
		{
			throw new vB_Exception_Api('missing_followerid');
		}

		if ($this->validateFollower($follower))
		{
			$existing = $this->getUserList($userId, 'followed', 'follow');

			if (array_key_exists($follower, $existing))
			{
				$existing = $existing[$follower];
			}
			else
			{
				$existing = false;
			}

			/** Insert follower record */
			$result = '';
			if ($existing AND empty($existing['errors']))
			{
				$result = $this->assertor->update('userlist',
					array('friend' => 'yes'),
					array('userid' => $follower, 'relationid' => $userId, 'type' => 'follow')
				);
			}
			else
			{
				$result = $this->assertor->insert('userlist', array(
						'userid' => $follower, 'relationid' => $userId, 'type' => 'follow', 'friend' => 'yes'
					)
				);
			}

			if (is_array($result['errors']) AND !empty($result['errors']))
			{
				return $result;
			}

			/** Delete ignore record */
			$result = $this->assertor->delete('userlist', array(
				'userid' => $userId,
				'relationid' => $follower,
				'type' => 'ignore',
				'friend' => 'denied'
			));
			unset($this->followers[$userId]);

			if (is_array($result['errors']) AND !empty($result['errors']))
			{
				return $result;
			}
		}

		$this->clearFollowCache(array($userId, $follower));
		return $this->updateUser($userId);
	}

	protected function validateFollower($followerId)
	{
		$userContext = vB::getUserContext();
		if ($followerId == $userContext->fetchUserId())
		{
			throw new vB_Exception_Api('following_user_itself');
		}

		/** Let's see if the record exists */
		$queryData = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, vB_dB_Query::CONDITIONS_KEY => array('userid' => $followerId));
		$existing = $this->assertor->getRow('user', $queryData);
		if (empty($existing) OR !empty($existing['errors']))
		{
			throw new vB_Exception_Api('inexistent_follower_record');
		}

		/** And check if user is following us */
		$queryData = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array(
				'type' => 'follow', 'friend' => 'yes',
				'relationid' => $userContext->fetchUserId(), 'userid' => $followerId
			)
		);

		$existing = $this->assertor->getRow('userlist', $queryData);
		if (empty($existing) AND empty($existing['errors']))
		{
			return true;
		}
	}


	/** This approves a following request made for the current user.
	*
	*	@param	int		the follower's id
	*
	*	@return bool
	*
	***/

	public function approveFollowing($followerid)
	{
		//validate that we are logged in.
		$userInfo =  vB::getCurrentSession()->fetch_userinfo();
		$userid = $userInfo['userid'];

		if (!intval($userid))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		//Is this a valid follower?
		$follower = $this->assertor->getRow('user', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array('userid' => $followerid)));

		if (!$follower OR !empty($follower['errors']))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		//is there an existing record?
		$existing = $this->assertor->getRow('userlist', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array('userid' => $followerid, 'relationid' => $userid)));

		if ($existing AND empty($existing['errors']))
		{
			$this->assertor->assertQuery('userlist', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			vB_dB_Query::CONDITIONS_KEY => array('userid' => $followerid, 'relationid' => $userid),
			'type' => 'follow', 'friend' => 'yes'));
		}
		else
		{
			$this->assertor->assertQuery('userlist', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'userid' => $followerid, 'relationid' => $userid,
			'type' => 'follow', 'friend' => 'yes'));
		}

		$this->clearFollowCache(array($followerid, $userid));
		return $this->updateUser($userid);
	}

	/** This denies a following request made for the current user.
	 *
	 *	@param	int		the follower's id
	 *
	 *	@return bool
	 *
	 ***/

	public function denyFollowing($followerid)
	{
		//validate that we are logged in.
		$userInfo =  vB::getCurrentSession()->fetch_userinfo();
		$userid = $userInfo['userid'];

		if (!intval($userid))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		//Is this a valid follower?
		$follower = $this->assertor->getRow('user', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array('userid' => $followerid)));

		if (!$follower OR !empty($follower['errors']))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		//is there an existing record?
		$existing = $this->assertor->getRow('userlist', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array('userid' => $userid, 'relationid' => $followerid)));

		if ($existing AND empty($existing['errors']))
		{
			$this->assertor->assertQuery('userlist', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			vB_dB_Query::CONDITIONS_KEY => array('userid' => $userid, 'relationid' => $followerid),
			'type' => 'ignore', 'friend' => 'denied'));
		}
		else
		{
			$this->assertor->assertQuery('userlist', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'userid' => $userid, 'relationid' => $followerid,
			'type' => 'ignore', 'friend' => 'denied'));
		}

		$this->clearFollowCache(array($userid, $followerid));
		vB_Cache::allCacheEvent("followChg_$followerid");
		return $this->updateUser($userid);

	}

	protected function updateUser($userid)
	{
		$query = $this->assertor->assertQuery('userlist', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'userid' => $userid));
		$ignore = array();
		$follow = array();
		foreach ($query as $record)
		{
			if (($record['type'] == 'ignore'))
			{
				$ignore[] = $record['relationid'];
			}
			if (($record['type'] == 'follow') AND ($record['friend'] == 'yes'))
			{
				$follow[] = $record['relationid'];
			}
		}

		$userInfo =  vB::getCurrentSession()->fetch_userinfo();
		$userdata = new vB_Datamanager_User();
		$userdata->set_existing($userInfo);
		$userdata->set('buddylist', $follow);
		$userdata->set('ignorelist', $ignore);
		return $userdata->save();
	}

	/**
	 * This indicates if user is following user's profile page.
	 * @param	int		Id from the user's profile page.
	 *
	 * @return	int		Used to indicate the following status between users.
	 * 					Values meaning:
	 * 					0 - Not following
	 * 					1 - Following
	 * 					2 - Pending
	 */
	public function isFollowingUser($profileUser = 0)
	{
		$profileUser = intval($profileUser);
		if ($profileUser < 1)
		{
			throw new vB_Exception_Api('invalid_profile_userid', array($profileUser));
		}

		$currentUser = intval(vB::getUserContext()->fetchUserId());
		if ($currentUser < 1)
		{
			return vB_Api_Follow::FOLLOWING_NO;
		}

		// we might get the whole userlist...
		$follow = $this->getUserList($profileUser, 'followed', 'follow');
		if (isset($follow['follow']))
		{
			$follow = $follow['follow'];
		}

		$pending = $this->getUserList($profileUser, 'followed', 'pending');
		if (isset($pending['pending']))
		{
			$pending = $pending['pending'];
		}

		$return = '';
		if (array_key_exists($currentUser, $follow) AND ($follow[$currentUser]['friend'] == 'yes'))
		{
			$return = vB_Api_Follow::FOLLOWING_YES;
		}
		else if (array_key_exists($currentUser, $pending) AND ($pending[$currentUser]['friend'] == 'pending'))
		{
			$return = vB_Api_Follow::FOLLOWING_PENDING;
		}
		else
		{
			$return = vB_Api_Follow::FOLLOWING_NO;
		}

		return $return;
	}

	/**
	 * This indicates if user is following content.
	 * @param	int		Id from conent
	 *
	 * @return	int		Used to indicate the following status between user and content.
	 * 					Values meaning:
	 * 					0 - Not following
	 * 					1 - Following
	 */
	public function isFollowingContent($contentId)
	{
		$contentId = intval($contentId);
		if (!$contentId)
		{
			throw new vB_Exception_Api('invalid_contentid', array($contentId));
		}

		$currentUser = intval(vB::getUserContext()->fetchUserId());
		if (!$currentUser)
		{
			throw new vB_Exception_Api('invalid_current_userid', array($currentUser));
		}

		$return = vB_Api_Follow::FOLLOWING_NO;
		$subscriptions = $this->getSubscribedDiscussion($currentUser);
		if ($subscriptions AND array_key_exists($contentId, $subscriptions))
		{
			$return = vB_Api_Follow::FOLLOWING_YES;
		}
		else
		{
			$existingCheck = vB::getDbAssertor()->getRows('vBForum:getNodePendingRequest', array('userid' => array($currentUser),
				'nodeid' => array($contentId), 'request' => array(vB_Api_Node::REQUEST_GRANT_SUBSCRIBER, vB_Api_Node::REQUEST_SG_GRANT_SUBSCRIBER)));
			if (!empty($existingCheck))
			{
				$return = vB_Api_Follow::FOLLOWING_PENDING;
			}
		}

		return $return;
	}

	/**
	 * This gets the params needed for the getFollowing method from the pageInfo array data (query params).
	 * Will use class constants
	 *
	 * @param	mixed	Pageinfo dragged from the query params.
	 *
	 * @return	mixed	Array with the params needed.
	 */
	public function getFollowingInfo($pageInfo)
	{
		switch ($pageInfo['type'])
		{
			case vB_Api_Follow::FOLLOWTYPE_USERS:
				$ftype = vB_Api_Follow::FOLLOWTYPE_USERS;
				$type = vB_Api_Follow::FOLLOWTYPE_USERS;
				break;
			case vB_Api_Follow::FOLLOWTYPE_CONTENT:
				$ftype = vB_Api_Follow::FOLLOWTYPE_CONTENT;
				$type = vB_Api_Follow::FOLLOWTYPE_CONTENT;
				break;
			case vB_Api_Follow::FOLLOWTYPE_CHANNELS:
				$ftype = vB_Api_Follow::FOLLOWTYPE_CHANNELS;
				$type = $type = vB_Api_Follow::FOLLOWTYPE_CONTENT;
				break;
			default:
				$ftype = vB_Api_Follow::FOLLOWTYPE_ALL;
				$type = vB_Api_Follow::FOLLOWTYPE_ALL;
				break;
		}

		switch ($pageInfo['sortby'])
		{
			case vB_Api_Follow::FOLLOWFILTER_SORTMOST:
				$sort = vB_Api_Follow::FOLLOWFILTER_SORTMOST;
				break;
			case vB_Api_Follow::FOLLOWFILTER_SORTLEAST:
				$sort = vB_Api_Follow::FOLLOWFILTER_SORTLEAST;
				break;
			default:
				$sort = vB_Api_Follow::FOLLOWFILTER_SORTALL;
				break;
		}

		$contentClass = 'all';
		//@TODO add link when it gets properly implemented
		if (in_array(strtolower($pageInfo['content']), array('text', 'gallery', 'video', 'poll', 'link')))
		{
			$contentClass = 'vBForum_' . ucfirst($pageInfo['content']);
		}

		$page = (isset($pageInfo['page']) AND !empty($pageInfo['page'])) ? $pageInfo['page'] : 1;
		$perPage = (isset($pageInfo['perpage']) AND !empty($pageInfo['perpage'])) ? $pageInfo['perpage'] : 100;
		$return = array(
			'type' => $type,
			'filters' => array(vB_Api_Follow::FOLLOWFILTERTYPE_SORT => $sort, vB_Api_Follow::FOLLOWTYPE => $ftype),
			'contenttypeclass' => $contentClass,
			'options' => array('page' => $page, 'perpage' => $perPage)
		);

		return $return;
	}

	protected function getPaginationInfo($params)
	{
		$startCount = $endCount = 0;
		//we use these values outside of the total count block below so we need to make
		//sure they get set (otherwise we can get a divide by 0 error)
		if (!isset($params['page']))
		{
			$params['page'] = 1;
		}

		$params['page'] = intval($params['page']);

		if (!isset($params['perPage']))
		{
			$params['perPage'] = 20;
		}

		$params['perPage'] = intval($params['perPage']);

		if ($params['totalCount'] > 0)
		{
			$startCount = ($params['page'] * $params['perPage']) - $params['perPage'] + 1;
			$endCount = $params['page'] * $params['perPage'];
			if ($endCount > $params['totalCount'])
			{
				$endCount = $params['totalCount'];
			}
		}
		$name = $params['routeName'];

		if (empty($params['userid']) AND !empty($params['queryParams']['userid']))
		{
			$params['userid'] = $params['queryParams']['userid'];
		}
		$totalPages = ceil($params['totalCount']/$params['perPage']);
		if ($totalPages < 1) $totalPages = 1;
		$prevPage = ($params['page'] > 1) ? ($params['page'] - 1) : '';
		$nextUrl = ($params['page'] < $totalPages) ? ($params['page'] + 1) : '';
		$return = array(
			'startcount' => $startCount,
			'endcount' => $endCount,
			'totalcount' => $params['totalCount'],
			'currentpage' => $params['page'],
			'page' => $params['page'],
			'prevurl' => $prevPage,
			'nexturl' => $nextUrl,
			'totalpages' => $totalPages,
			'name' => $name,
			'tab' => $params['tab'],
			'userid' => $params['queryParams']['userid']
			//'queryParams' => $params['queryParams']
		);

		return $return;
	}

	/**
	 * Gets the message type to be displayed for the unsubscribe overlay
	 *
	 * @param	int		ContentId user is subscribed to.
	 * @param	int		UserId the user is subscribed to.
	 * @param	int		ChannelId user is subscribed to.
	 *
	 * @return	int		type number to identify the message to display.
	 */
	public function getUnsubscribeText($isFollowingContent, $isFollowingMember, $isFollowingChannel)
	{
		$isFollowingContent = ($isFollowingContent) ? 1 : 0;
		$isFollowingMember = ($isFollowingMember) ? 2 : 0;
		$isFollowingChannel = ($isFollowingChannel) ? 4 : 0;
		$messageType = $isFollowingContent | $isFollowingMember | $isFollowingChannel;

		$showAll = false;
		$item = false;
		switch ($messageType)
		{
			case 1:
				$messageText = 'one';
				$item = vB_Api_Follow::FOLLOWTYPE_CONTENT;
				break;
			case 2:
				$messageText = 'two';
				$item = vB_Api_Follow::FOLLOWTYPE_USERS;
				break;
			case 3:
				$messageText = 'four';
				$showAll = true;
				break;
			case 4:
				$messageText = 'one';
				$item = vB_Api_Follow::FOLLOWTYPE_CHANNELS;
				break;
			case 5:
				$messageText = 'three';
				$showAll = true;
				break;
			case 6:
				$messageText = 'four';
				$showAll = true;
				break;
			case 7:
				$messageText = 'five';
				$showAll = true;
				break;
			default:
				$messageText = '';
				break;
		}

		return array('messageType' => $messageText, 'showAll' => $showAll, 'item' => $item);
	}

	/**
	 * Unsubscribe items from user.
	 *
	 * @param	mixed	An array of items to unsubscribe. They should contain the type (which might be users, nodes and channels) and the item id.
	 *
	 * @return	boolean	A flag to indicate if the unsubscribe was successfully done.
	 *
	 */
	public function unsubscribeItems($unsubscribeItems)
	{
		$userInfo =  vB::getCurrentSession()->fetch_userinfo();
		$userId = $userInfo['userid'];

		foreach ($unsubscribeItems as $item)
		{
			if (intval($item['itemId']))
			{
				switch ($item['type'])
				{
					case vB_Api_Follow::FOLLOWTYPE_USERS:
						$this->removeFollowing($item['itemId'], $userId, $item['type']);
						break;
					case vB_Api_Follow::FOLLOWTYPE_CONTENT:
						$this->delete($item['itemId'], $item['type']);
						break;
					case vB_Api_Follow::FOLLOWTYPE_CHANNELS:
						$this->removeFollowing($item['itemId'], $userId, $item['type']);
						break;
					default:
						//ignore any other values
						break;
				}
			}
		}

		return true;
	}

	/**
	 * Clears follow in class cache
	 */
	public function clearFollowCache($userIds)
	{
		foreach ($userIds AS $user)
		{
			unset($this->followers[$user]);
			unset($this->userFollowers[$user]);
			unset($this->userFollowing[$user]);
			unset($this->subscriptions[$user]);
			vB_Cache::allCacheEvent("followChg_$user");
		}

		$this->userListCache = array();
	}


	/** This gets the following parameters- not the content
	*
	*	@param		int		option userid
	*
	*	@return		mixed 	array of content, user, member. Each is an array of integers.
	***/
	public function getFollowingParameters($userid = false)
	{
		//Must have a userid
		if (!$userid OR !intval($userid))
		{
			$userid = vB::getCurrentSession()->get('userid');
		}

		$result = array('content' => array(), 'user' => array(), 'member' => array());

		if ($userid < 1)
		{
			return $result;
		}

		$hashKey = "flwParams_$userid";
		$cacheResult = vB_Cache::instance(vB_Cache::CACHE_FAST)->read($hashKey);
		if ($cacheResult)
		{
			return $cacheResult;
		}

		$assertor = vB::getDbAssertor();
		//First content
		if (isset($this->subscriptions[$userid]))
		{
			foreach($this->subscriptions[$userid] as $discussionid)
			{
				$result['content'][] = $discussionid;
			}
		}
		else
		{
			$this->subscriptions[$userid] = array();
			$qry = $assertor->assertQuery('vBForum:subscribediscussion', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'userid' => $userid));
			foreach($qry as $follow)
			{
				$result['content'][] = $follow['discussionid'];
				$this->subscriptions[$userid][$follow['discussionid']] = $follow['discussionid'];
			}
		}

		//Next users
		$params = array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'userid' => intval($userid),
			'type'   => 'follow',
			'friend' => 'yes',
		);

		ksort($params);
		$cacheKey = md5(json_encode($params));

		if (!isset($this->userListCache[$cacheKey]))
		{
			$this->userListCache[$cacheKey] = $this->assertor->getRows('userlist', $params);
		}

		foreach($this->userListCache[$cacheKey] AS $follow)
		{
			$result['user'][] = $follow['relationid'];
		}

		//Now blogs or social groups where you are a member.
		$members = vB_Library::instance('user')->getGroupInTopic($userid);
		foreach($members AS $member)
		{
			$result['member'][] = $member['nodeid'];
		}

		vB_Cache::instance(vB_Cache::CACHE_FAST)->write($hashKey, $result, 1440, "followChg_$userid", "userChg_$userid");
		return $result;
	}

	/**
	 * Return all the subscribers from a given nodeid.
	 *
	 * @param	int		Nodeid we are fetching subscribers from
	 * @param	mixed	Array of options to the node subscribers such as page, perpage,
	 *
	 * @return	mixed	Array of the subscribers with their information. Such as userid, username, avatar
	 */
	public function getNodeSubscribers($nodeid, $options = array())
	{
		if (!is_numeric($nodeid) OR ($nodeid < 1))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		if (!vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canaddowners', $nodeid))
		{
			throw new vB_Exception_Api('no_permission');
		}

		$data = array('nodeid' => $nodeid);
		$data[vB_dB_Query::PARAM_LIMIT] = (isset($options['perpage']) AND is_numeric($options['perpage']) AND ($options['perpage'] > 0)) ? $options['perpage'] : 20;
		$data[vB_dB_Query::PARAM_LIMITPAGE] = (isset($options['page']) AND is_numeric($options['page']) AND ($options['page'] > 0)) ? $options['page'] : 1;
		$data['sort'] = array('username' => 'ASC');

		$subscribers = vB::getDbAssertor()->getRows('vBForum:fetchNodeSubscribers', $data);
		$total = vB::getDbAssertor()->getRow('vBForum:getNodeSubscribersTotalCount');

		$result = array('subscribers' => array(), 'totalcount' => $total['total']);
		$ids = array();
		if (!empty($subscribers))
		{
			foreach ($subscribers AS $subscriber)
			{
				$result['subscribers'][$subscriber['userid']] = array('userid' => $subscriber['userid'], 'username' => $subscriber['username']);
				$ids[] = $subscriber['userid'];
			}

			$avatars = vB_Api::instanceInternal('user')->fetchAvatars($ids);
			foreach ($avatars AS $uid => $avatar)
			{
				$result['subscribers'][$uid]['avatarpath'] = $avatar['avatarpath'];
			}
		}

		// paginationinfo
		$pages = ceil($total['total'] / $data[vB_dB_Query::PARAM_LIMIT]);
		$result['pageinfo'] = array(
			'page' => $data[vB_dB_Query::PARAM_LIMITPAGE],
			'pages' => $pages,
			'nextpage' => ($data[vB_dB_Query::PARAM_LIMITPAGE] < $pages) ? ($data[vB_dB_Query::PARAM_LIMITPAGE] + 1) : 0,
			'prevpage' => ($data[vB_dB_Query::PARAM_LIMITPAGE] > 1) ? ($data[vB_dB_Query::PARAM_LIMITPAGE] - 1) : 0
		);

		return $result;
	}
}
