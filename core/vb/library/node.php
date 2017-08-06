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
 * vB_Library_Node
 *
 * @package vBApi
 * @access public
 */

class vB_Library_Node extends vB_Library
{

	protected $nodeFields = array();
	protected $contentAPIs = array();

	protected $albumChannel = false;
	protected $VMChannel = false;
	protected $PMChannel = false;
	protected $ReportChannel = false;
	protected $forumChannel = false;

	protected $channelTypeId;

	protected function __construct()
	{

		$this->pmContenttypeid = vB_Types::instance()->getContentTypeId('vBForum_PrivateMessage');
		$structure = vB::getDbAssertor()->fetchTableStructure('vBForum:node');
		$this->nodeFields = $structure['structure'];
		$this->channelTypeId = vB_Types::instance()->getContentTypeID('vBForum_Channel');
	}

	/** Return the list of fields in the node table
	*
	*
	**/
	public function getNodeFields()
	{
		return $this->nodeFields;
	}


	/** This clear cache for all children of a node list
	*
	* 	@param	mixed	array of nodes
	**/
	public function clearChildCache($nodeids)
	{
		$childrenArray = $this->fetchClosurechildren($nodeids);
		$events = array();
		foreach ($childrenArray as $children)
		{
			foreach ($children as $child)
			$events[] = 'nodeChg_' . $child['child'];
		}
		vB_Cache::instance()->allCacheEvent($events);
	}


	/** opens a node for posting
	 *
	 * 	@param	mixed	integer or array of integers
	 *
	 *	@return	mixed	Either array 'errors' => error string or array of id's.
	 **/
	public function openNode($nodeids)
	{
		$loginfo = array();
		vB::getDbAssertor()->assertQuery('vBForum:node', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE, 'open' => 1, 'showopen' => 1, 'nodeid' => $nodeids));

		foreach ($nodeids as $nodeid)
		{
			$node = $this->getNode($nodeid);
			$result = vB::getDbAssertor()->assertQuery('vBForum:openNode', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'nodeid' => $nodeid));

			$loginfo[] = array(
				'nodeid'		=> $node['nodeid'],
				'nodetitle'		=> $node['title'],
				'nodeusername'	=> $node['authorname'],
				'nodeuserid'	=> $node['userid']
			);

			if (!empty($result['errors']))
			{
				break;
			}
		}

		$this->clearCacheEvents($nodeids);
		$this->clearChildCache($nodeids);

		if (!empty($result['errors']))
		{
			return array('errors' => $result['errors']);
		}

		vB_Library_Admin::logModeratorAction($loginfo, 'node_opened_by_x');

		return $nodeids;
	}

	/** Closes a node for posting. Closed nodes can still be viewed but nobody can reply to one.
	 *
	 * 	@param	mixed	integer or array of integers
	 *
	 *	@return	mixed	Either array 'errors' => error string or array of id's.
	 **/
	public function closeNode($nodeids)
	{
		$loginfo = array();
		vB::getDbAssertor()->assertQuery('vBForum:node', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE, 'open' => 0, 'showopen' => 0, 'nodeid' => $nodeids));

		foreach ($nodeids as $nodeid)
		{
			$node = $this->getNode($nodeid);
			$result = vB::getDbAssertor()->assertQuery('vBForum:closeNode', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'nodeid' => $nodeid));

			$loginfo[] = array(
				'nodeid'		=> $node['nodeid'],
				'nodetitle'		=> $node['title'],
				'nodeusername'	=> $node['authorname'],
				'nodeuserid'	=> $node['userid']
			);

			if (!empty($result['errors']))
			{
				break;
			}
		}

		$this->clearCacheEvents($nodeids);
		$this->clearChildCache($nodeids);

		if (!empty($result['errors']))
		{
			return array('errors' => $result['errors']);
		}

		vB_Library_Admin::logModeratorAction($loginfo, 'node_closed_by_x');

		return $nodeids;
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
		$params = array(vB_dB_Query::TYPE_KEY=> vB_dB_Query::QUERY_METHOD, 'contenttypeid' => $contenttypeid);

		foreach ($this->nodeFields as $fieldname)
		{
			if (isset($data[$fieldname]))
			{
				$params[$fieldname] = $data[$fieldname];
			}
		}

		$result = vB::getDbAssertor()->assertQuery('vBForum:addNode', $params);

		//If this is not a channel, we should set the lastcontentid to this nodeid,
		// and lastcontent to now.
		if (($data['contenttypeid'] <> vB_Types::instance()->getContentTypeID('vBForum_Channel')) AND empty($data['lastcontentid']))
		{
			vB::getDbAssertor()->assertQuery('vBForum:node', array(vB_dB_Query::TYPE_KEY=> vB_dB_Query::QUERY_UPDATE,
				'nodeid' => $result, 'lastcontent' => vB::getRequest()->getTimeNow(), 'lastcontentid' => $result));
		}

		if (!empty($result))
		{
			vB_Api::instanceInternal('Search')->index($result);

		}

		return($result);
	}

	/** Permanently deletes a node
	 *	@param	integer	The nodeid of the record to be deleted
	 *
	 *	@return	boolean
	 **/
	public function deleteNode($nodeid)
	{
		if (!intval($nodeid))
		{
			return false;
		}

		//If it's a protected channel, don't allow removal.
		$existing = $this->getNode($nodeid);

		if (empty($existing))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		if ($existing['protected'])
		{
			//O.K. if it's not a channel.
			if ($existing['contenttypeid'] == vB_Types::instance()->getContentTypeId('vBForum_Channel'))
			{
				throw new vB_Exception_Api('no_delete_permissions');
			}
		}

		$ancestorsId = array();
		$ancestors = $this->getParents($nodeid);

		$result = vB::getDbAssertor()->assertQuery('vBForum:deleteNode', array(vB_dB_Query::TYPE_KEY=> vB_dB_Query::QUERY_METHOD, 'nodeid' => $nodeid, 'delete_subnodes' => true));

		if(!empty($result))
		{
			vB_Api::instanceInternal('Search')->delete($nodeid);
			vB_Api::instanceInternal('Search')->purgeCacheForCurrentUser();
			$this->resetAncestorCounts($existing, $ancestors, true);
		}

		$route = 'profile';

		if ( class_exists('vB5_Cookie') AND vB5_Cookie::isEnabled())
		{
			// session is stored in cookies, so do not append it to url
			$route .= '|nosession';
		}

		$loginfo[] = array(
			'nodeid'		=> $existing['nodeid'],
			'nodetitle'		=> $existing['title'],
			'nodeusername'	=> $existing['authorname'],
			'nodeuserid'	=> $existing['userid']
		);

		vB_Library_Admin::logModeratorAction($loginfo, 'node_hard_deleted_by_x');

		return($result);
	}

	/**
	 * Updates the ancestors counts and last data from a given node being deleted.
	 * Counts and last data are info from the node table records:
	 * totalcount, totalunpubcount, textcount, textunpubcount
	 * lastcontentid, lastcontent, lastauthor, lastauthorid.
	 * Is critical that the ancestors are in DESC order so we can properly update.
	 *
	 * @param	array		The node being deleted information.
	 * @param	array		Information from the node's ancestors needed to update last (nodeid, contenttypeid needed).
	 * @param	bool		Flag indicating if we are soft/hard-deleting
	 *
	 */
	public function resetAncestorCounts($existing, $ancestorsData, $hard)
	{
		// now update last content and counts for parents
		$ancestorsId = array();
		$toUpdate = array();
		foreach ($ancestorsData AS $ancestor)
		{
			$ancestorsId[] = $ancestor['nodeid'];
			$toUpdate[$ancestor['nodeid']] = array('nodeid' => $ancestor['nodeid'], 'contenttypeid' => $ancestor['contenttypeid']);
		}

		// make sure we have unique ancestors and they're in the right order
		krsort($toUpdate);
		$ancestorsId = array_unique($ancestorsId);

		// reset last content for all parents that have the deleted node
		if ($existing['showpublished'] AND $hard)
		{
			$totalChange = -1 - $existing['totalcount'];
			$totalUnpubChange = 0;
			$textChange = -1;
			$textUnpubChange = 0;
		}
		else if (!$existing['showpublished'] AND $hard)
		{
			$totalChange = 0;
			$totalUnpubChange = -1 - $existing['totalunpubcount'];
			$textChange = 0;
			$textUnpubChange = -1;
		}
		else if ($existing['showpublished'] AND !$hard)
		{
			$totalChange = -1 - $existing['totalcount'];
			$totalUnpubChange = 1 + $existing['totalcount'];
			$textChange = -1;
			$textUnpubChange = 1;
		}
		else
		{
			$totalChange = 0;
			$totalUnpubChange = 0;
			$textChange = 0;
			$textUnpubChange = 0;

		}
		//Update total counts.
		vB::getDbAssertor()->assertQuery('vBForum:UpdateAncestorCount',
			array(
				'totalChange' => $totalChange,
				'totalUnpubChange' => $totalUnpubChange,
				'nodeid' => $ancestorsId)
		);
		//text counts for parent only.
		vB::getDbAssertor()->assertQuery('vBForum:UpdateParentTextCount',
			array(
				'textChange' => $textChange,
				'textUnpubChange' => $textUnpubChange,
				'nodeid' => $existing['parentid'])
		);

		//And the "last" data. We have to work bottom-to-top.
		foreach ($toUpdate AS $ancestor)
		{
			if ($ancestor['contenttypeid'] == $this->channelTypeId)
			{
				vB::getDbAssertor()->assertQuery('vBForum:fixNodeLast', array('nodeid' => $ancestor['nodeid']));
			}
			else
			{
				vB::getDbAssertor()->assertQuery('vBForum:updateLastData', array('parentid' => $ancestor['nodeid'], 'timenow' => vB::getRequest()->getTimeNow()));
			}

			vB_Cache::instance()->allCacheEvent("nodeChg_" . $ancestor['nodeid']);
		}

	}


	/** Permanently/Temporarily deletes a set of nodes
	 *	@param	array	The nodeids of the records to be deleted
	 *	@param	bool	hard/soft delete
	 *	@param	string	the reason for soft delete (not used for hard delete)
	 *	@param	bool	Log the deletes in moderator log
	 *
	 *	@return	array nodeids that were deleted
	 **/
	public function deleteNodes($deleteNodeIds, $hard, $reason, $ancestorsId, $starters, $modlog = true)
	{
		$loginfo = array();
		$starters = array();
		//if we are doing a hard delete we need to first delete the type-specific data.
		$nodes = $this->getNodes($deleteNodeIds, false);
		$needRebuild = false;

		if ($hard)
		{
			foreach ($nodes AS $node)
			{
				//see if we need a rebuild
				if ($node['contenttypeid'] == vB_Types::instance()->getContentTypeID('vBForum_Channel'))
				{
					$needRebuild = true;
				}

				try
				{
					$starters[] = $node['starter'];

					// content delete method handle counts updating
					vB_Api_Content::getContentApi($node['contenttypeid'])->delete($node['nodeid']);
				}
				//Note that if one of the nodes to be deleted is a child of a node we've already deleted, we'll get an exception here.
				catch(vB_Exception_Api $e)
				{
					//nothing to do.
				}

				if($modlog)
				{
					$loginfo[] = array(
						'nodeid'		=> $node['nodeid'],
						'nodetitle'		=> $node['title'],
						'nodeusername'	=> $node['authorname'],
						'nodeuserid'	=> $node['userid']
					);
				}
			}

			vB_Library_Admin::logModeratorAction($loginfo, 'node_hard_deleted_by_x');
		}
		else
		{
			$fields = array (
				'unpublishdate' => vB::getRequest()->getTimeNow(),
				'deletereason' => $reason,
				'deleteuserid' => vB::getCurrentSession()->get('userid')
			);
			$result = vB::getDbAssertor()->update('vBForum:node', $fields, array(array('field' => 'nodeid', 'value' => $deleteNodeIds, 'operator' => vB_dB_Query::OPERATOR_EQ)));

			$errors = array();
			foreach ($nodes as $node)
			{
				//see if we need a rebuild
				if ($node['contenttypeid'] == vB_Types::instance()->getContentTypeID('vBForum_Channel'))
				{
					$needRebuild = true;
				}
				$nodeid = $node['nodeid'];
				$ancestors = $this->getParents($nodeid);
				$starters[] = $node['starter'];

				$result = vB::getDbAssertor()->assertQuery('vBForum:unPublishNode', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
						'nodeid' => $nodeid));
				$this->resetAncestorCounts($node, $ancestors, $hard);

				if (!empty($result['errors']))
				{
					$errors[] = $result['errors'];
				}

				if($modlog)
				{
					$loginfo[] = array(
						'nodeid'		=> $node['nodeid'],
						'nodetitle'		=> $node['title'],
						'nodeusername'	=> $node['authorname'],
						'nodeuserid'	=> $node['userid']
					);
				}

				if (!empty($node['setfor']))
				{
					vB_Cache::instance()->allCacheEvent('fUserContentChg_' . $node['setfor']);
				}
			}

			if (!empty($errors))
			{
				return array('errors' => $errors);
			}

			vB_Library_Admin::logModeratorAction($loginfo, 'node_soft_deleted_by_x');\
			vB_Library_Content::getContentLib($node['contenttypeid'])->decrementUserPostCount($node['userid']);
		}

		$starters = array_unique($starters);
		// reset last content for all parents that have the deleted nodes
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$fastCache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$events = array();

		foreach ($starters AS $starter)
		{
			$events[] = "nodeChg_" . $starter;
		}

		foreach ($deleteNodeIds as $nodeid)
		{
			$events[] = "nodeChg_" . $nodeid;
		}
		$events = array_unique($events);
		$cache->allCacheEvent($events);
		vB_Api::instance('Search')->purgeCacheForCurrentUser();

		if ($needRebuild)
		{
			vB::getUserContext()->rebuildGroupAccess();
		}

		return $deleteNodeIds;
	}

	/** lists the nodes that should be displayed on a specific page. Include joinable content
	 *	@param	integer	The node id of the parent where we are listing
	 *	@param	integer	page number to return
	 *	@param	integer	items per page
	 *	@param	integer	depth- 0 means no stopping, otherwise 1= direct child, 2= grandchild, etc
	 *	@param	mixed	if desired, will only return specific content types.
	 *	@param	mixed	recognizes 'sort', 'exclude', 'userid', 'featured'
	 *	@param	mixed	array of filters (date - last day, last week, last month, all time or/and following - members, channels or both), showchannel (include channel title)
	 *
	 * 	@return	mixed	array of id's
	 **/
	public function listNodesWithContent($parentid, $page, $perpage, $depth, $contenttypeid, $options)
	{
		return $this->listNodes($parentid, $page, $perpage, $depth, $contenttypeid, $options, true);
	}

	/** lists the nodes that should be displayed on a specific page.
	 *	@param	integer	The node id of the parent where we are listing
	 *	@param	integer	page number to return
	 *	@param	integer	items per page
	 *	@param	integer	depth- 0 means no stopping, otherwise 1= direct child, 2= grandchild, etc
	 *	@param	mixed	if desired, will only return specific content types.
	 *	@param	mixed	recognizes 'sort', 'exclude', 'userid', 'featured'
	 *	@param	mixed	array of filters (date - last day, last week, last month, all time or/and following - members, channels or both), showchannel (include channel title)
	 *	@param	bool	Include joinable content
	 *
	 * 	@return	mixed	array of id's
	 **/
	public function listNodes($parentid, $page, $perpage, $depth, $contenttypeid, $options, $withJoinableContent = false)
	{
		//Let's see if we have a cached record.
		$options['parentid'] = $parentid;
		$options['depth'] = $depth;

		if ($contenttypeid)
		{
			$options['contenttypeid'] = $contenttypeid;
		}

		$searchApi = vB_Api::instanceInternal('search');
		$timestamp = vB::getRequest()->getTimeNow();
		$dayStart = $timestamp - ($timestamp % 86400);

		//now see if we have a cached value.
		$hashkey = 'SrchResults' . vB::getUserContext()->fetchUserId() . crc32(serialize($options)) ;

		if (($srchResultId = vB_Cache::instance()->read($hashkey)))
		{
			//Track Visits
			vB::getDbAssertor()->assertQuery('vBForum:trackNodeVisits', array(
				'userid' => vB::getCurrentSession()->get('userid'),
				'dateline' => $dayStart,
				'nodeid' => $parentid
			));

			$srchResults = $searchApi->getMoreNodes($srchResultId, $perpage, $page);
			if (!empty($srchResults['nodeIds']))
			{
				$resultNodes = $this->getNodes($srchResults['nodeIds'], $withJoinableContent);

				$result = array();
				if (is_array($resultNodes) AND !isset($resultNodes['errors']))
				{
					foreach ($srchResults['nodeIds'] AS $nodeid)
					{
						if (empty($resultNodes[$nodeid]))
						{
							continue;
						}
						$result[$nodeid] = $resultNodes[$nodeid];
					}
				}
				return $result;
			}
			else
			{
				return array();
			}
		}

		//We need to do a new search. We need a criteria object.
		$criteria = array();
		//Let's set the values.

		//contenttype
		if (intval($contenttypeid) > 0)
		{
			$criteria['contenttypeid'] = $contenttypeid;
		}

		//channel
		if ($parentid)
		{
			$criteria['channel'] = $parentid;
		}
		else if (!empty($options['channel']))
		{
			$criteria['channel'] = $parentid;
		}

		//exclude
		if (!empty($options['exclude']))
		{
			$criteria['exclude'] = $options['exclude'];
		}

		//depth
		if ($depth)
		{
			if (!empty($options['depth_exact']))
			{
				$criteria['depth_exact'] = 1;
			}
			$criteria['depth'] = $depth;
		}
		else if (!empty($options['channel']))
		{
			$criteria['channel'] = $parentid;
		}

		//featured
		if (!empty($options['featured']) AND (bool)$options['featured'])
		{
			$criteria['featured'] = 1;
		}

		//time filter
		if (!empty($options[vB_Api_Node::FILTER_TIME]))
		{
			//only allow a subset of the values that the search will accept for a from date. If it not one of
			//values we accept assume "all values" aka no date filter.
			switch ($options[vB_Api_Node::FILTER_TIME])
			{
				case vB_Api_Search::FILTER_LASTDAY:
				case vB_Api_Search::FILTER_LASTWEEK:
				case vB_Api_Search::FILTER_LASTMONTH:
				case vB_Api_Search::FILTER_LASTYEAR:

					$criteria['date'] = array('from' => $options[vB_Api_Node::FILTER_TIME]);
					break;
				;
			} // switch
		}

		if (isset($options['include_starter']))
		{
			$criteria['include_starter'] = $options['include_starter'];
		}

		if (isset($options['includeProtected']) AND !($options['includeProtected']))
		{
			$criteria['ignore_protected'] = 1;
		}

		$userdata = vB::getUserContext()->getReadChannels();

		$exclude = $userdata['cantRead'];

		if (isset($options[vB_Api_Search::FILTER_FOLLOW]))
		{
			if (empty($options['followerid']))
			{
				throw new vB_Exception_Api('invalid_request');
			}
			switch($options[vB_Api_Search::FILTER_FOLLOW])
			{
				case vB_Api_Search::FILTER_FOLLOWING_USERS:
					$criteria[vB_Api_Search::FILTER_FOLLOW] = array(vB_Api_Search::FILTER_FOLLOWING_USERS => $options['followerid']);
					break;
				case vB_Api_Search::FILTER_FOLLOWING_CHANNEL:
					$criteria[vB_Api_Search::FILTER_FOLLOW] = array(vB_Api_Search::FILTER_FOLLOWING_CHANNEL => $options['followerid']);
					break;
				case vB_Api_Search::FILTER_FOLLOWING_CONTENT:
					$criteria[vB_Api_Search::FILTER_FOLLOW] = array(vB_Api_Search::FILTER_FOLLOWING_CONTENT => $options['followerid']);
					break;
				case vB_Api_Search::FILTER_FOLLOWING_BOTH:
					$criteria[vB_Api_Search::FILTER_FOLLOW] = array(vB_Api_Search::FILTER_FOLLOWING_BOTH => $options['followerid']);
					break;
				default:
					throw new vB_Exception_Api('invalid_request');

			}
			$criteria['include_starter'] = 1;
		}

		//userid
		if (!empty($options['userid']))
		{
			$criteria['authorid'] = $options['userid'];
		}

		//sort order
		if (!empty($options['sort']))
		{
			$criteria['sort'] = $options['sort'];
		}

		if (!empty($options['nolimit']))
		{
			$criteria['nolimit'] = $options['nolimit'];
		}

		// we don't want to store a cached value into another cache
		$criteria['ignore_cache'] = true;
		$results = $searchApi->getInitialNodes($criteria, $perpage, $page);

		//cache the result id.
		vB_Cache::instance()->write($hashkey, $results['resultId'], 5, 'nodeChg_' . $parentid);

		if (!empty($results['nodeIds']))
		{
			//Track Visits
			vB::getDbAssertor()->assertQuery('vBForum:trackNodeVisits', array(
				'userid' => vB::getCurrentSession()->get('userid'),
				'dateline' => $dayStart,
				'nodeid' => $parentid
			));
			$nodes = $this->getNodes($results['nodeIds'], $withJoinableContent);

			foreach ($results['nodeIds'] as $nodeid)
			{
				if (empty($nodes[$nodeid]))
				{
					unset($results['nodeIds'][$nodeid]);
				}
				$results['nodeIds'][$nodeid] = $nodes[$nodeid];
			}
			return $results['nodeIds'];
		}
		else
		{
			return array();
		}

	}


	/** gets one node.
	 *	@param	integer The node id
	 *
	 *	@return	mixed	array of node records
	 **/
	public function getNodeBare($nodeid)
	{
		$node = vB_Library_Content::fetchFromCache($nodeid, vB_Library_Content::CACHELEVEL_NODE);

		if ($node AND $node['found'] AND $node['found'][$nodeid])
		{
			$node = $node['found'][$nodeid];
		}
		else
		{
			$node = vB::getDbAssertor()->getRow('vBForum:node', array('nodeid' => $nodeid));
			if (empty($node))
			{
				throw new vB_Exception_Api('invalid_node_id', array('nodeid' => $nodeid));
			}
			vB_Library_Content::writeToCache(array($node), vB_Library_Content::CACHELEVEL_NODE);
		}
		return $node;
	}

	/** gets one node with Joinable content
	 *	@param	integer The node id
	 *
	 *	@return	mixed	array of node records
	 **/
	public function getNodeBareWithJoinableContent($nodeid)
	{
		$node = vB_Library_Content::fetchFromCache($nodeid, vB_Library_Content::CACHELEVEL_JOINABLECONTENT);

		if ($node AND $node['found'] AND $node['found'][$nodeid])
		{
			$node = $node['found'][$nodeid];
		}
		else
		{
			$node = vB::getDbAssertor()->getRow(
				'vBForum:fetchNodeWithContent', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
					'nodeid' => $nodeid
				)
			);

			if (empty($node))
			{
				throw new vB_Exception_Api('invalid_node_id' );
			}
			vB_Library_Content::writeToCache(array($node), vB_Library_Content::CACHELEVEL_JOINABLECONTENT);
		}
		return $node;
	}

	/** gets one node.
	 *	@param	integer The node id
	 * @param	boolean Whether to include list of parents
	 * @param	boolean Whether to include attachments
	 * @param	boolean	Include joinable content
	 *
	 *	@return	mixed	array of node records, optionally including attachment and ancestry.
	 **/
	public function getNode($nodeid, $withParents = false, $withJoinableContent = false)
	{
		$node = $withJoinableContent? $this->getNodeBareWithJoinableContent($nodeid) : $this->getNodeBare($nodeid);

		if ($withParents)
		{
			$node['parents'] = $this->getNodeParents($nodeid);
			$node['parents_reversed'] = array_reverse($node['parents']);
		}

		foreach (vB_Api::instanceInternal('node')->getOptions() as $key => $bitmask)
		{
			$node[$key] = ($bitmask & $node['nodeoptions']) ? 1 : 0;
		}

		return $node;
	}

	/**
	 * get the ancestors of a node
	 * @param int $nodeid
	 * @return array
	 */
	public function getNodeParents($nodeid)
	{
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$hashKey = "nodeParents_$nodeid";
		$parents = $cache->read($hashKey);
		if (empty($parents))
		{
			$parents = array();
			$ancestors = $this->fetchClosureParent($nodeid);

			foreach ($ancestors AS $closure)
			{
				$parents[$closure['depth']] = $closure['parent'];
			}
			$cache->write($hashKey, $parents, 1440, 'nodeChg_' . $nodeid);

		}

		return $parents;
	}

	/** Gets the node info for a list of nodes
	 *	@param	array of node ids
	 *	@param	bool	Include joinable content
	 *
	 * 	@return	mixed	array of node records
	 **/
	public function getNodes($nodeList, $withJoinableContent = false)
	{
		static $cachedNodeList = array();

		if (empty($nodeList))
		{
			return array();
		}

		if (!is_array($nodeList))
		{
			$nodeList = array($nodeList);
		}
		//if we are passed options we can't precache.
		$cachedNodeList = array_unique(array_merge($cachedNodeList, $nodeList));
		vB_Api::instanceInternal('page')->registerPrecacheInfo('node', 'getNodes', $cachedNodeList);

		$cached = vB_Library_Content::fetchFromCache($nodeList, vB_Library_Content::CACHELEVEL_NODE);

		if (empty($cached['notfound']))
		{
			//We found everything, so we're done.
			return $cached['found'];
		}

		if ($withJoinableContent)
		{
			$indexed = vB::getDbAssertor()->getRows(
				'vBForum:fetchNodeWithContent', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
					'nodeid' => $cached['notfound']
				),
				false,
				'nodeid'
			);
		}
		else
		{
			$indexed = vB::getDbAssertor()->getRows(
				'vBForum:node',
				array('nodeid' => $cached['notfound']),
				false,
				'nodeid'
			);
		}

		vB_Library_Content::writeToCache($indexed, vB_Library_Content::CACHELEVEL_NODE);
		//now we need to merge and sort them.
		$merged = array();
		foreach ($nodeList AS $nodeid)
		{
			if (array_key_exists($nodeid, $cached['found']))
			{
				$merged[$nodeid] = $cached['found'][$nodeid];
			}
			else if (array_key_exists($nodeid, $indexed))
			{
				$merged[$nodeid] = $indexed[$nodeid];
			}
		}
		unset($cached, $indexed);
		return $merged;
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
	/** Sets the publishdate and (optionally) the unpublish date of a node
	 *	@param	integer	The node id
	 *	@param	integer	The timestamp for publish date
	 *	@param	integer	The timestamp for unpublish date if applicable
	 *
	 *	@return	boolean
	 **/
	public function setPublishDate($nodeid, $datefrom, $dateto = null)
	{
		$data = array(vB_dB_Query::TYPE_KEY=> vB_dB_Query::QUERY_UPDATE,
			'nodeid' => $nodeid, 'publishdate' => $datefrom);

		if (intval($dateto))
		{
			$data['unpublishdate'] = $dateto;
		}
		else
		{
			$data['unpublishdate'] = -1;
		}

		//We need to use the content object to set this because there may be
		// type-specific data needed.

		$node = $this->getNode($nodeid);
		if (empty($this->contentAPIs[$node['nodeid']]))
		{
			$this->contentAPIs[$node['nodeid']] =
				vB_Api::instanceInternal('Content_' . vB_Types::instance()->getContentTypeClass($node['contenttypeid']));
		}

		return $this->contentAPIs[$node['nodeid']]->update($nodeid, $data);
	}

	/** Sets the unpublish date
	 *	@param	integer	The node id
	 *	@param	integer	The timestamp for unpublish
	 *
	 *	@return	boolean
	 **/
	public function setUnPublishDate($nodeid, $dateto = false)
	{
		$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'nodeid' => $nodeid);

		if (intval($dateto))
		{
			$data['unpublishdate'] = $dateto;
		}
		else
		{
			$data['unpublishdate'] = vB::getRequest()->getTimeNow();
		}
		//We need to use the content object to set this because there may be
		// type-specific data needed.
		$node = $this->getNode($nodeid);
		if (empty($this->contentAPIs[$node['nodeid']]))
		{
			$this->contentAPIs[$node['nodeid']] =
				vB_Api::instanceInternal('Content_' . vB_Types::instance()->getContentTypeClass($node['contenttypeid']));
		}

		return $this->contentAPIs[$node['nodeid']]->update($nodeid, $data);
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

		$node = $this->getNode($nodeid);

		if (empty($this->contentAPIs[$node['nodeid']]))
		{
			$this->contentAPIs[$node['nodeid']] =
				vB_Api::instanceInternal('Content_' . vB_Types::instance()->getContentTypeClass($node['contenttypeid']));
		}

		$data = array('publishdate' => -1, 'showpublished' => 0);

		$this->clearCacheEvents(array_unique(array($nodeid, $node['parentid'], $node['starter'])));
		return $this->contentAPIs[$node['nodeid']]->update($nodeid, $data);
	}

	/*** sets a list of nodes to be featured
	 *	@param	array	The node ids
	 *	@param	boot	set or unset the featured flag
	 *
	 *	@return	array nodeids that have permission to be featured
	 **/
	public function setFeatured($nodeids, $set = true)
	{
		if (!$nodeids)
		{
			return array();
		}
		else if (!is_array($nodeids))
		{
			$nodeids = array($nodeids);
		}

		$loginfo = array();
		foreach ($nodeids as $nodeid)
		{
			$node = $this->getNode($nodeid);

			$loginfo[] = array(
				'nodeid'		=> $node['nodeid'],
				'nodetitle'		=> $node['title'],
				'nodeusername'	=> $node['authorname'],
				'nodeuserid'	=> $node['userid'],
			);
		}

		vB::getDbAssertor()->assertQuery('vBForum:node',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE, 'nodeid' => $nodeids, 'featured' => $set));

		$this->clearCacheEvents($nodeids);

		vB_Library_Admin::logModeratorAction($loginfo, ($set ? 'node_featured_by_x' : 'node_unfeatured_by_x'));

		return $nodeids;
	}

	/** clears the unpublishdate flag.
	 *	@param	integer	The node id
	 *
	 *	@return	boolean
	 **/
	public function clearUnpublishDate($nodeid)
	{
		$result = vB::getDbAssertor()->assertQuery('vBForum:node',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE, 'nodeid' => $nodeid, 'unpublishdate' => -1));

		$node = $this->getNode($nodeid);
		if (empty($this->contentAPIs[$node['nodeid']]))
		{
			$this->contentAPIs[$node['nodeid']] =
				vB_Api::instanceInternal('Content_' . vB_Types::instance()->getContentTypeClass($node['contenttypeid']));
		}

		$data = array('unpublishdate' => 0);

		return $this->contentAPIs[$node['nodeid']]->update($nodeid, $data);

	}
	/**This takes a list of nodes, and returns node records for all that are valid nodeids.
	*
	* 	@param		mixed		array of ints
	*	@param		bool		Include joinable content
	*
	*	@return		mixed		array of node records
	**/
	protected function cleanNodeList(&$nodeList, $withJoinableContent = false)
	{
		if (!is_array($nodeList))
		{
			$nodeList = array($nodeList);
		}

		//many of them may be in cache.
		$cached = vB_Library_Content::fetchFromCache($nodeList, vB_Library_Content::CACHELEVEL_NODE);
		$listIndex = array_flip($nodeList);

		if (!empty($cached['found']))
		{
			foreach ($cached['found'] as $node)
			{
				$nodeid = $node['nodeid'];
				if (isset($listIndex[$nodeid]))
				{
					$nodeList[$listIndex[$nodeid]] = $node;
					unset($listIndex[$nodeid]);
				}
			}
		}

		if (!empty($cached['notfound']))
		{
			if ($withJoinableContent)
			{
				$nodes = vB::getDbAssertor()->assertQuery(
					'vBForum:fetchNodeWithContent', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
						'nodeid' => $cached['notfound']
					)
				);
			}
			else
			{
				$nodes = vB::getDbAssertor()->assertQuery('vBForum:node', array('nodeid' => $cached['notfound']));
			}
			foreach ($nodes as $node)
			{
				$nodeid = $node['nodeid'];
				if (isset($listIndex[$nodeid]))
				{
					$nodeList[$listIndex[$nodeid]] = $node;
					unset($listIndex[$nodeid]);
				}
			}
		}

		//if we filtered out a node in the query, remove it from the list.
		foreach($listIndex as $key => $value)
		{
			unset($nodeList[$value]);
		}
	}


	/** Gets the content info for a list of nodes
	 *	@param	mixed	array of node ids
	 *
	 * 	@return	mixed	array of content records
	 **/
	public function getContentforNodes($nodeList, $options = array())
	{
		static $cachedNodeList = array();

		//if we are passed options we can't precache.
		if (empty($options))
		{
			if (!is_array($nodeList))
			{
				$nodeList = array($nodeList);
			}
			//if we are passed options we can't precache.
			$cachedNodeList = array_unique(array_merge($cachedNodeList, $nodeList));
			vB_Api::instanceInternal('page')->registerPrecacheInfo('node', 'getContentforNodes', $cachedNodeList);
		}

		$this->cleanNodeList($nodeList, true);
		return $this->addContentInfo($nodeList, $options);
	}

	/** Gets the content info for a list of nodes
	 *	@param array array of node ids
	 *
	 * 	@return array array of content records -- preserves the original keys
	 **/
	public function getFullContentforNodes($nodeList, $options = array())
	{
		static $cachedNodeList = array();

		//if we are passed options we can't precache.
		if (empty($options))
		{
			if (!is_array($nodeList))
			{
				$nodeList = array($nodeList);
			}
			//if we are passed options we can't precache.
			$cachedNodeList = array_unique(array_merge($cachedNodeList, $nodeList));
			vB_Api::instanceInternal('page')->registerPrecacheInfo('node', 'getFullContentforNodes', $cachedNodeList);
		}

		$this->cleanNodeList($nodeList, true);
		return $this->addFullContentInfo($nodeList, $options);
	}

	/**
	 * Cache a list of node votes
	 *
	 * @param array $nodeIds A list of Nodes to be cached
	 *
	 * @see vB_Api_Reputation::cacheNodeVotes()
	 */
	protected function cacheNodeVotes(array $nodeIds)
	{
		vB_Api::instanceInternal('reputation')->cacheNodeVotes($nodeIds);
	}

	/**	Gets the channel title and routeid
	 *	@param	int		The node id.
	 *	@return	mixed	Array of channel info
	 */
	public function getChannelInfoForNode($channelId)
	{
		$channelInfo = $this->getNodeBare($channelId);
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


	/** Adds optional content information. At the time of this writing it understands showVM and withParent
	 *
	 *	@param	mixed	the assembled array of node info
	 * 	@param	mixed	optional array of optional information
	 *
	 ***/
	protected function addOptionalContentInfo(&$nodeList, $options = false)
	{
		//We always need to add avatar information,
		$userApi = vB_Api::instanceInternal('user');
		$useridAvatarsToFetch = array();
		$userinfo = array();
		foreach($nodeList AS $key => $node)
		{
			if (empty($node['content']))
			{
				$nodeList[$key]['content'] = array();
			}

			if (!empty($node['userid']))
			{
				$useridAvatarsToFetch[] = $node['userid'];
			}

			if (!empty($node['lastauthorid']) AND $node['lastauthorid'] > 0)
			{
				$useridAvatarsToFetch[] = $node['lastauthorid'];
			}

			if (!empty($node['deleteuserid']) AND !isset($nodeList[$key]['content']['deleteusername']))
			{
				$nodeList[$key]['content']['deleteusername'] = $userApi->fetchUserName($node['deleteuserid']);
			}

			if (isset($node['content']['userinfo']['hascustomavatar']))
			{
				$userinfo[$node['content']['userinfo']['userid']] = $node['content']['userinfo'];
			}
		}

		$avatarsurl = $userApi->fetchAvatars($useridAvatarsToFetch, true, $userinfo);

		foreach ($nodeList AS $nodeKey => $nodeInfo)
		{

			if (!empty($nodeInfo['userid']))
			{
				$nodeList[$nodeKey]['content']['avatar'] = $avatarsurl[$nodeInfo['userid']];
			}

			if (!empty($nodeInfo['lastauthorid']) AND $nodeInfo['lastauthorid'] > 0 AND !empty($avatarsurl[$nodeInfo['lastauthorid']]))
			{
				$nodeList[$nodeKey]['content']['avatar_last_poster'] = $avatarsurl[$nodeInfo['lastauthorid']];
			}
		}

		if (!empty($options['showVM']) AND !empty($nodeList))
		{
			$nodeids = array();
			//We need to flag which are visitor messages
			foreach($nodeList AS $key => $node)
			{
				$nodeids[$node['nodeid']] = $node['nodeid'];
				$nodeList[$key]['content']['isVisitorMessage'] = 0;
			}
			//We have all the nodes. Now query for which are VM's
			$vMs = $this->fetchClosureParent(array_keys($nodeids), vB_Api::instanceInternal('node')->fetchVMChannel());
			foreach ($vMs AS $closureRecord)
			{
				//Remember the nodes are keys into nodeids, which are keys into nodeList.
				$key = $nodeids[$closureRecord['child']];
				$nodeList[$key]['content']['isVisitorMessage'] = 1;

				// comments/replies don't have a set for so we might want to get that from parent...
				if (!empty($nodeList[$key]['content']['setfor']))
				{
					$setfor = $nodeList[$key]['content']['setfor'];
				}
				else
				{
					$parentInfo = $this->getNode($nodeList[$key]['parentid']);
					$setfor = $parentInfo['setfor'];
				}

				$vm_userInfo = vB_User::fetchUserinfo($setfor);
				$vmAvatar = $userApi->fetchAvatar($setfor, true, $vm_userInfo);
				$vm_userInfo = array(
					'userid' => $vm_userInfo['userid'],
					'username' => $vm_userInfo['username'],
					'rank' => $vm_userInfo['rank'],
					'usertitle' => $vm_userInfo['usertitle'],
					'joindate' => $vm_userInfo['joindate'],
					'posts' => $vm_userInfo['posts'],
					'customtitle' => $vm_userInfo['customtitle'],
					'userfield' => array(),
				);
				$vm_userInfo = array_merge($vm_userInfo, $vmAvatar);
				$nodeList[$key]['content']['vm_userInfo'] = $vm_userInfo;
			}
		}

		if (!empty($options['withParent']) AND !empty($nodeList))
		{
			//We need to pull parent node information, but only for comments- not starters or replies:
			$parentids = array();
			$indexes = array();
			foreach($nodeList AS $key => $node)
			{
				//Note that we can't use an indexed array to lookup the same way as for showVM, because we
				// often will have multiple records with the same parent.
				if (($node['nodeid'] != $node['starter']) AND ($node['parentid'] != $node['starter']) AND ($node['contenttypeid'] != $this->channelTypeId))
				{
					$parentids[] = $node['parentid'];
				}
			}

			//If we had no comments in the list, we're done.
			if (!empty($parentids))
			{
				$parents = $this->getFullContentforNodes(array_unique($parentids));

				foreach ($parents AS $key => $parent)
				{
					$indexes[$parent['nodeid']] = $key;
				}
				foreach($nodeList AS $key => $node)
				{
					if (array_key_exists($node['parentid'], $indexes))
					{
						$parentKey = $indexes[$node['parentid']];
						$nodeList[$key]['content']['parentConversation'] = $parents[$parentKey];
					}
				}
			}
		}
	}

	/** Adds optional content information for a single node.
	 * 	At the time of this writing it understands showVM and withParent
	 *
	 *	@param	mixed	the assembled array of node info
	 * 	@param	mixed	optional array of optional information
	 *
	 ***/
	protected function addOptionalNodeContentInfo(&$node, $options = false)
	{
		//We always need to add avatar information
		$userApi = vB_Api::instanceInternal('user');
		$useridAvatarsToFetch = array();
		if (!empty($node['userid']))
		{
			$useridAvatarsToFetch[] = $node['userid'];
		}
		if (!empty($node['lastauthorid']) AND $node['lastauthorid'] > 0)
		{
			$useridAvatarsToFetch[] = $node['lastauthorid'];
		}

		if (!empty($node['deleteuserid']) AND !isset($node['deleteusername']))
		{
			$node['deleteusername'] = $userApi->fetchUserName($node['deleteuserid']);
		}

		if (!empty($useridAvatarsToFetch))
		{
			$avatarsurl = $userApi->fetchAvatars($useridAvatarsToFetch, true);

			if (!empty($node['userid']))
			{
				$node['avatar'] = $avatarsurl[$node['userid']];
			}

			if (!empty($node['lastauthorid']) AND $node['lastauthorid'] > 0 AND !empty($avatarsurl[$node['lastauthorid']]))
			{
				$node['avatar_last_poster'] = $avatarsurl[$node['lastauthorid']];
			}
		}

		if (!empty($options['showVM']) AND !empty($node))
		{
			//We have the node. Now query for which are VM's
			$vMs = $this->fetchClosureParent($node['nodeid'], vB_Api::instanceInternal('node')->fetchVMChannel());
			if (!empty($vMs))
			{
				foreach ($vMs AS $closureRecord)
				{
					$key = $closureRecord['child'];
					if ($key == $node['nodeid'])
					{
						$node['isVisitorMessage'] = 1;
						$vm_userInfo = vB_User::fetchUserinfo($node['setfor']);
						$node['vm_userInfo'] = array(
							'userid' => $vm_userInfo['userid'],
							'username' => $vm_userInfo['username'],
							'rank' => $vm_userInfo['rank'],
							'usertitle' => $vm_userInfo['usertitle'],
							'joindate' => $vm_userInfo['joindate'],
							'posts' => $vm_userInfo['posts'],
							'customtitle' => $vm_userInfo['customtitle'],
							'userfield' => array(),
						);
						$vmAvatar = $userApi->fetchAvatar($node['setfor'], true, $vm_userInfo);
						if (is_array($vmAvatar))
						{
							$node['vm_userInfo'] = array_merge($node['vm_userInfo'], $vmAvatar);
						}
					}
				}
			}
			else
			{
				$node['isVisitorMessage'] = 0;
				$node['vm_userInfo'] = array();
			}
		}

		if (!empty($options['withParent']) AND !empty($node))
		{
			$parentid = 0;

			if (($node['nodeid'] != $node['starter']) AND ($node['parentid'] != $node['starter']) AND ($node['contenttypeid'] != $this->channelTypeId))
			{
				$parentid = $node['parentid'];
			}

			//If we had no comments in the list, we're done.
			if (!empty($parentid))
			{
				$parent = $this->getNodeFullContent($parentid);
				$node['parentConversation'] = $parent;
			}
		}
	}

	/** This gets the attachment information filedata for a node. Which may be empty.
	*
	*	@param		int		the nodeid we are checking
	*
	*	@return		mixed	either false or an array of filedata.
	**/
	public function fetchAttachInfo($parentIds)
	{
		if (!is_array($parentIds))
		{
			$parentIds = array($parentIds);
		}

		//First let's see what we have in cache.
		$found = array();
		$notfound = array();
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);

		foreach ($parentIds AS $parentId)
		{
			$attachments = $cache->read("vBAtchInf_$parentId");
			if ($attachments === false)
			{
				$notfound[$parentId] = array() ;
			}
			else
			{
				$found = array_merge($found, $attachments);
			}
		}

		if (!empty($notfound))
		{
			try
			{
				$attachments = vB::getDbAssertor()->assertQuery('vBForum:fetchAttachInfo', array(
					'parentId' => array_keys($notfound)
				));

				foreach($attachments AS $attachment)
				{
					$found[] = $attachment;
					$notfound[$attachment['parentid']][] = $attachment;
				}

				//cache what we've found- but not false. Use empty array so we can distinguish
				// cached data from uncached.
				foreach ($notfound AS $parentId => $attachments)
				{
					$hashKey = "vBAtchInf_$parentId";

					if (empty($attachments))
					{
						$attachments = array();
					}
					$cache->write($hashKey, $attachments, 1440, "nodeChg_$parentId");
				}
			}
			catch(exception $e)
			{
				//this happens during upgrade from a vb3x site. Just continue;
			}
		}

		return $found;

	}


	/** This gets the attachment information for a node. Which may be empty.
	 *
	 *	@param		int		the nodeid we are checking
	 *
	 *	@return		mixed	either false or an array of attachments with the following fields:
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
	 *
	 * **
	 **/
	public function fetchNodeAttachments($parentids)
	{
		if (!is_array($parentids))
		{
			$parentids = array($parentids);
		}

		//First let's see what we have in cache.
		$found = array();
		$notfound = array();
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$parents = array();
		foreach ($parentids AS $parentid)
		{
			$attachments = $cache->read("vBAtchmnts_$parentid");

			if ($attachments === false)
			{
				$notfound[$parentid] = array() ;
			}
			else
			{
				$found = array_merge($found, $attachments);
			}
		}

		if (!empty($notfound))
		{
			try
			{
				$attachments = vB::getDbAssertor()->getRows('vBForum:fetchNodeAttachments',
					array('nodeid' => array_keys($notfound)), 'displayorder');
			}
			catch(exception $e)
			{
				//this can happen during a preload. Just continue;
				$attachments = array();
			}

			$updateFiledataInfo = array();
			foreach($attachments AS &$attachment)
			{
				$found[] =& $attachment;
				$notfound[$attachment['parentid']][] =& $attachment;

				if ($attachment['filedataid'] > 0)
				{
					$updateFiledataInfo[$attachment['filedataid']][] =& $attachment;
				}
			}

			//now fetch missing filedata info
			if(!empty($updateFiledataInfo))
			{
				$filedataInfo = vB::getDbAssertor()->assertQuery('vBForum:getFiledataWithThumb', array('filedataid' => array_keys($updateFiledataInfo)));
				if ($filedataInfo)
				{
					foreach($filedataInfo AS $filedata)
					{
						foreach($updateFiledataInfo[$filedata['filedataid']] AS &$attachment)
						{
							// these fields are required for text parsing
							$keys = array('userid', 'extension', 'filesize', 'dateline', 'resize_filesize', 'resize_dateline');
							foreach($keys AS $key)
							{
								$attachment[$key] = $filedata[$key];
							}

							// todo: is there a reason to not return the filename?
							// $attachment['filename'] = $filedata['filehash'] . '.' . $filedata['extension'];
							$attachment['counter'] = $filedata['refcount'];
							$attachment['hasthumbnail'] = ($filedata['resize_filesize'] > 0);
						}
					}
				}
			}

			//cache what we've found- but not false. Use empty array so we can distinguish
			// cached data from uncached.
			foreach ($notfound AS $parentid => $attachments)
			{
				$hashKey = "vBAtchmnts_$parentid";

				if (empty($attachments))
				{
					$attachments = array();
				}

				$cache->write($hashKey, $attachments, 1440, "nodeChg_$parentid");
			}
		}
		return $found;
	}

	/** Takes an array of node information and adds contentInfo
	 *	@param	integer	The node id of the parent where we are listing
	 *	@param	integer	page number to return
	 *	@param	integer	items per page
	 *	@param	integer	depth- 0 means no stopping, otherwise 1= direct child, 2= grandchild, etc
	 *	@param	mixed	if desired, will only return specific content types.
	 *	@param	mixed	'sort', or 'exclude' recognized.
	 *
	 * 	@return	mixed	array of id's
	 ***/
	public function addFullContentInfo($nodeList, $options = array())
	{
		//Now separate by content type	$contenttypes = array();
		if (empty($nodeList))
		{
			return array();
		}
		$nodeIds = array();
		$needVote = array();
		$cacheVote = array();
		$needRead = array();
		$parentids = array();
		$attachCounts = array();
		$photoCounts = array();
		$channels = array();

		$unloaded_desc_attach = $unloaded_desc_photo = array();
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);

		$userids = array();
		foreach ($nodeList AS $key => $node)
		{
			if (!isset($contenttypes[$node['contenttypeid']]))
			{
				$contenttypes[$node['contenttypeid']] = array();
			}
			$contenttypes[$node['contenttypeid']][$key] = $node['nodeid'];
			$nodeIds[] = $node['nodeid'];

			if (!isset($node['nodeVoted']))
			{
				$needVote[] = $node['nodeid'];
			}
			else
			{
				$cacheVote[$node['nodeid']] = $node['nodeVoted'];
			}

			if (!isset($node['readtime']))
			{
				$needRead[$node['nodeid']] = $node['nodeid'];
				$nodeList[$key]['readtime'] = 0;
			}
			$parentids[$node['parentid']] = $node['parentid'];
			$needRead = array_merge($parentids, $needRead);

			$cacheKey = 'vbDescendantAttachCount_' . $node['nodeid'];
			$attachCounts[$node['nodeid']] = $cache->read($cacheKey);

			if ($attachCounts[$node['nodeid']] === false)
			{
				$unloaded_desc_attach[] = $node['nodeid'];
			}
			$cacheKey = 'vbDescendantPhotoCount_' . $node['nodeid'];
			$photoCounts[$node['nodeid']] = $cache->read($cacheKey);

			if ($photoCounts[$node['nodeid']] === false)
			{
				$unloaded_desc_photo[] = $node['nodeid'];
			}

			if (!isset($userids[$node['userid']]))
			{
				$userids[$node['userid']] = $node['userid'];
		}
		}

		vB_Library::instance('user')->preloadUserInfo($userids);

		// pre-cache parents
		$parents = $this->getNodes($parentids);
		$parentrouteids = array();
		foreach ($parents as $parent)
		{
			$parentrouteids[] = $parent['routeid'];
		}

		//pre-load parent routes
		vB5_Route::preloadRoutes($parentrouteids);
		// get votes
		$nodeVotes = (empty($needVote)) ? array() : $this->getNodeVotes($needVote);

		if (!empty($cacheVote))
		{
			$this->cacheNodeVotes($cacheVote);
		}

		if (!empty($nodeIds))
		{
			$attachments = $this->fetchAttachInfo($nodeIds);

			$nodeAttachments = array();
			foreach ($attachments as $key => $attach)
			{
				$nodeAttachments[$attach['parentid']][$attach['filedataid']] = & $attachments[$key];
			}
		}

		// Fetch read marking data
		$threadmarking = vB::getDatastore()->getOption('threadmarking');
		$userid = vB::getCurrentSession()->get('userid');
		if ($threadmarking AND $userid AND !empty($nodeIds) AND !empty($needRead))
		{
			$reads = vB::getDbAssertor()->getRows('noderead', array(
				'userid' => $userid,
				'nodeid' => $needRead,
			));

			$parentsreads = array();
			foreach ($reads AS $read)
			{
				if (!empty($nodeList[$read['nodeid']]))
				{
					$nodeList[$read['nodeid']]['readtime'] = $read['readtime'];
				}
				else
				{
					$parentsreads[$read['nodeid']] = $read['readtime'];
				}
			}

			foreach ($nodeList as $nodeid => $node)
			{
				if (empty($parentsreads[$node['parentid']])) {
					$parentsreads[$node['parentid']] = 0;
				}
				$nodeList[$nodeid]['parentreadtime'] = $parentsreads[$node['parentid']];
			}

		}

		//For each type, get the content detail.

		if (!empty($unloaded_desc_attach))
		{
			$attachCountQry = vB::getDbAssertor()->getRows('vBForum:getDescendantAttachCount', array('nodeid' => $unloaded_desc_attach));
			foreach ($attachCountQry as $count)
			{
				$attachCounts[$count['nodeid']] = $count['count'];
				$cache->write('vbDescendantAttachCount_' . $count['nodeid'], $count['count'], 1440);
			}
		}
		if (!empty($unloaded_desc_photo))
		{
			$photoCountQry = vB::getDbAssertor()->getRows('vBForum:getDescendantPhotoCount', array('nodeid' => $unloaded_desc_photo,
				'photoTypeid' => vB_Types::instance()->getContentTypeID('vBForum_Photo')));
			foreach ($photoCountQry as $count)
			{
				$photoCounts[$count['nodeid']] = $count['count'];
				$cache->write('vbDescendantPhotoCount_' . $count['nodeid'], $count['count'], 1440);
			}
		}
		// precache closure
		$this->fetchClosureParent($nodeIds);
		$optionMask = vB_Api::instanceInternal('node')->getOptions();

		foreach ($contenttypes as $contenttypeid => $nodes)
		{
			if (!empty($nodes))
			{
				$contentLib = vB_Library_Content::getContentLib($contenttypeid);

				$contentList = $contentLib->getFullContent($nodes, array('forumpermissions' => array('canview'),
					'moderatorpermissions' => array('canmoderateposts')));

				foreach ($nodes as $key => $nodeid)
				{
					foreach ($contentList AS $key2 => $content)
					{
						if ($content['nodeid'] == $nodeid)
						{
							if (isset($nodeList[$key]['nodeVoted']))
							{	// node came into the function with nodeVoted already set
								$content['nodeVoted'] = $nodeList[$key]['nodeVoted'];
							}
							else
							{	// node came into this function w/o nodeVoted set so getNodeVotes retrieved it up there^
								$content['nodeVoted'] = in_array($nodeid, $nodeVotes) ? 1 : 0;
							}
							$nodeList[$key]['content'] = $content;

							if (!empty($content['contenttypeclass']))
							{
								$nodeList[$key]['contenttypeclass'] = $content['contenttypeclass'];
							}

							if ($content['contenttypeid'] == $this->channelTypeId)
							{
								$channels[$content['nodeid']] = $content['nodeid'];
							}
							else if (!empty($content['channelid']) AND !isset($channels[$content['channelid']]))
							{
								$channels[$content['channelid']] = $content['channelid'];
							}
							break;
						}
					}

					foreach ($optionMask as $bitname => $bitmask)
					{
						$nodeList[$key][$bitname] = ($bitmask & $node['nodeoptions']) ? 1 : 0;
					}

					if (isset($nodeAttachments[$nodeid]))
					{
						$nodeList[$key]['content']['attachments'] = & $nodeAttachments[$nodeid];
					}
					else
					{
						$nodeList[$key]['content']['attachments'] = array();
					}

					if (empty($attachCounts[$nodeid]))
					{
						$nodeList[$key]['attachcount'] = 0;
					}
					else
					{
						$nodeList[$key]['attachcount'] = $attachCounts[$nodeid];
					}

					if (!empty($photoCounts[$nodeid]))
					{
						$nodeList[$key]['attachcount'] += $photoCounts[$nodeid];
					}
				}
			}
		}

		$this->addOptionalContentInfo($nodeList, $options);
		//Note- it is essential that the parentids be passed along with the nodeList. This allows all the permissions to
		// be pulled in one function call, and saves a lot of processing in the usercontext object.
		$this->markSubscribed($nodeList);
		$this->markJoined($nodeList);
		return $nodeList;
	}

	/** Takes an array of node information and adds contentInfo
	 *	@param	integer	The node id of the parent where we are listing
	 *	@param	integer	page number to return
	 *	@param	integer	items per page
	 *	@param	integer	depth- 0 means no stopping, otherwise 1= direct child, 2= grandchild, etc
	 *	@param	mixed	if desired, will only return specific content types.
	 *	@param	mixed	'sort', or 'exclude' recognized.
	 *
	 * 	@return	mixed	array of id's
	 ***/
	public function addContentInfo($nodeList, $options = array())
	{
		return $this->addFullContentInfo($nodeList, $options);
	}

	/**
	* This gets a content record based on nodeid. Useful from ajax.
	*
	*	@param	int
	*	@param	int	optional
	*	@param	mixed options	Options to get optional info (showVM, withParent)
	*
	*	@return array. An array of node record arrays as $nodeid => $node
	*
	***/
	public function getNodeContent($nodeid, $contenttypeid = false, $options = array())
	{
		return $this->getNodeFullContent($nodeid, $contenttypeid, $options);
		}


	/** returns id of the Albums Channel
	 *
	 *	@return	integer		array including
	 ***/
	public function fetchAlbumChannel()
	{
		if ($this->albumChannel)
		{
			return $this->albumChannel;
		}
		$albumChannel = vB_Api::instanceInternal('Content_Channel')->fetchChannelByGUID(vB_Channel::ALBUM_CHANNEL);
		$this->albumChannel = $albumChannel['nodeid'];
		return $this->albumChannel;

	}

	/** returns id of the Private Message Channel
	 *
	 *	@return	integer		array including
	 ***/
	public function fetchPMChannel()
	{
		if ($this->PMChannel)
		{
			return $this->PMChannel;
		}
		$PMChannel = vB_Api::instanceInternal('Content_Channel')->fetchChannelByGUID(vB_Channel::PRIVATEMESSAGE_CHANNEL);
		$this->PMChannel = $PMChannel['nodeid'];
		return $this->PMChannel;

	}

	/** returns id of the Vistor Message Channel
	 *
	 *	@return	integer		array including
	 **/
	public function fetchVMChannel()
	{
		if ($this->VMChannel)
		{
			return $this->VMChannel;
		}
		$VMChannel = vB_Api::instanceInternal('Content_Channel')->fetchChannelByGUID(vB_Channel::VISITORMESSAGE_CHANNEL);
		$this->VMChannel = $VMChannel['nodeid'];
		return $this->VMChannel;

	}

	/** returns id of the Report Channel
	 *
	 *	@return	integer		array including
	 ***/
	public function fetchReportChannel()
	{
		if ($this->ReportChannel)
		{
			return $this->ReportChannel;
		}
		$ReportChannel = vB_Api::instanceInternal('Content_Channel')->fetchChannelByGUID(vB_Channel::REPORT_CHANNEL);
		$this->ReportChannel = $ReportChannel['nodeid'];
		return $this->ReportChannel;

	}

	/**
	 * Returns the nodeid of the root forums channel
	 *
	 * @return	integer	The nodeid for the root forums channel
	 */
	public function fetchForumChannel()
	{
		if ($this->forumChannel)
		{
			return $this->forumChannel;
		}

		$forumChannel = vB_Api::instanceInternal('Content_Channel')->fetchChannelByGUID(vB_Channel::DEFAULT_FORUM_PARENT);
		$this->forumChannel = $forumChannel['nodeid'];

		return $this->forumChannel;
	}

	/** This gets a content record based on nodeid including channel and starter information.
	 *
	 * 	@param	int
	 *	@param	int	optional
	 *	@param	bool optional
	 *
	 *	@return mixed
	 *
	 ***/
	public function getNodeFullContent($nodeid, $contenttypeid = false, $options = array())
	{
		$db = vB::getDbAssertor();

		if ($contenttypeid)
		{
			$contentLib = vB_Library_Content::getContentLib($contenttypeid);
		}
		else
		{
			$node = vB_Library::instance('node')->getNodeBare($nodeid);
			if (empty($node) OR !empty($node['errors']))
			{
				throw new vB_Exception_Api('invalid_data');
			}

			$contentLib = vB_Library_Content::getContentLib($node['contenttypeid']);
		}
		$result = $contentLib->getFullContent($nodeid);

		$totalphotocount = isset($result[$nodeid]['photo']) ? count($result[$nodeid]['photo']) : 0;
		if (!empty($options['attach_options']['perpage']) AND $result[$nodeid]['photocount'] > $options['attach_options']['perpage'])
		{
			$page = empty($options['attach_options']['page']) ? 1 : $options['attach_options']['page'];
			$from = ($page -1) * $options['attach_options']['perpage'];
			$result[$nodeid]['photo'] = array_slice($result[$nodeid]['photo'], $from, $options['attach_options']['perpage']);
			$result[$nodeid]['pagenav'] = array(
				'startcount' => $from,
				'totalcount' => $totalphotocount,
				'currentpage' => $page,
				'totalpages' => ceil($totalphotocount / $options['attach_options']['perpage']),
				'perpage' => $options['attach_options']['perpage']
			);

		}

		$attachments = $this->fetchAttachInfo($nodeid);

		$result[$nodeid]['attachments'] = array();
		foreach ($attachments AS $attachment)
		{
			$result[$nodeid]['attachments'][$attachment['filedataid']] = $attachment;
		}
		$totalattachcount = $result[$nodeid]['attachcount'] = count($result[$nodeid]['attachments']);

		if (!empty($options['attach_options']['perpage']) AND !empty($result[$nodeid]['attachcount']) AND
			$result[$nodeid]['attachcount'] > $options['attach_options']['perpage'])
		{
			$page = empty($options['attach_options']['page']) ? 1 : $options['attach_options']['page'];
			$from = ($page -1) * $options['attach_options']['perpage'];
			$result[$nodeid]['attachments'] = array_slice($result[$nodeid]['attachments'], $from, $options['attach_options']['perpage']);

			$result[$nodeid]['attachpagenav'] = array(
				'startcount' => $from,
				'totalcount' => $totalattachcount,
				'currentpage' => $page,
				'totalpages' => ceil($totalattachcount / $options['attach_options']['perpage']),
				'perpage' => $options['attach_options']['perpage']
			);
		}
		$this->addOptionalNodeContentInfo($result[$nodeid], $options);

		if ($result[$nodeid]['contenttypeid'] == $this->channelTypeId)
		{
			$channelid = $nodeid;
		}
		else
		{
			$channelid = $result[$nodeid]['channelid'];
		}
		$perms = vB::getUserContext()->fetchPermsForChannels(array($channelid));
		$thisPerms = $perms[$channelid];
		foreach ($perms['global'] AS $key => $perm)
		{
			$thisPerms[$key] = $perm;
		}
		$this->markSubscribed($result);
		$this->markJoined($result);

		return $result;
	}

	/** This returns all the albums in a channel. Those can be photogalleries or text with attachments.
	 *
	 *	@param		int
	 *
	 *	@return		mixed		array of node records. Each node includes the node content and userinfo, and attachment records.
	 **/
	public function getAlbums($nodeid)
	{
		//first query to get the id's.
		$nodeids = array();
		$nodeQry = vB::getDbAssertor()->assertQuery('vBForum:fetchNodesWithAttachments',
			array('channel' => $nodeid, 'contenttypeid' => array(vB_Types::instance()->getContentTypeId('vBForum_Attach'), vB_Types::instance()->getContentTypeId('vBForum_Photo'))) );

		foreach($nodeQry AS $node)
		{
			$nodeids[] = $node['nodeid'];
		}

		if (empty($nodeids))
		{
			return array();
		}

		$content = $this->getFullContentforNodes($nodeids, true);
		//let's set everything with key nodeid.
		//We want to know the difference between attachments and photos in the template.
		//The array of photos in the gallery is "photo". For text let's call it "album".
		$sortable = array();
		foreach ($content as $key => $node)
		{
			$sortable[$node['nodeid']] = $node;
			$sortable[$node['nodeid']]['album'] = array();
		}

		if (!empty($nodeids))
		{
			$attachments = $this->fetchAttachInfo($nodeids);

			foreach ($attachments as $key => $attach)
			{
				$sortable[$attach['parentid']]['album'][$attach['nodeid']] = $attach;
			}
		}

		foreach ($content as $key => &$node)
		{
			if (empty($node['attachments']))
			{
				$node['attachcount'] = 0;
			}
			else
			{
				$node['attachcount'] = count($node['attachments']);
			}
		}

		//now we need photocount
		foreach ($sortable as $key => $node)
		{
			$sortable[$key]['photocount'] = count($node['album']);
			$sortable[$key]['starteruserid'] = $node['content']['starteruserid'];
			$sortable[$key]['starterauthorname'] = $node['content']['starterauthorname'];
			$sortable[$key]['starterroute'] = $node['content']['starterroute'];
		}
		//Now galleries.
		$nodeQry = vB::getDbAssertor()->assertQuery('vBForum:fetchGalleriesInChannel',
			array('channel' => $nodeid, 'contenttypeid' => vB_Types::instance()->getContentTypeId('vBForum_Gallery')) );

		$nodeids = array();

		foreach($nodeQry AS $node)
		{
			$nodeids[] = $node['nodeid'];
		}

		if (!empty($nodeids))
		{
			$galleries = vB_Api::instanceInternal('content_gallery')->getFullContent($nodeids);

			//now let's merge and sort them.
			foreach ($galleries as $gallery)
			{
				//we only have text objects that we know have attachments, but there
				//could be a gallery with no photos.
				if (!empty($gallery['photo']))
				{
					$sortable[$gallery['nodeid']] = $gallery;
				}
			}
		}
		if (empty($sortable))
		{
			throw new vB_Exception_Api('invalid_data');
		}
		ksort($sortable);
		//Now we have a non-associative array of $content and an associative array of albums. We need to merge.
		return $sortable;
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
	public function listPendingPosts($userId = false, $options = array())
	{
		$userId = intval($userId);
		if (!$userId)
		{
			$userId = vB::getCurrentSession()->get('userid');
		}

		if (!$userId)
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		// let's get mod permissions
		$params = array();
		$moderateInfo = vB::getUserContext()->getCanModerate();
		$result = array();

		if (empty($moderateInfo['can']))
		{
			$result = array('nodes' => array());
		}
		else
		{
			// let's take pagination info first...
			$params[vB_dB_Query::PARAM_LIMITPAGE] = (isset($options['page']) AND intval($options['page'])) ? $options['page'] : 1;

			$params[vB_dB_Query::PARAM_LIMIT] = (isset($options['perpage']) AND intval($options['perpage'])) ? $options['perpage'] : 20;
			$params['canModerate'] = $moderateInfo['can'];

			$pendingPosts = vB::getDbAssertor()->assertQuery('vBForum:fetchPendingPosts',$params);

			$pending = array();
			foreach ($pendingPosts AS $post)
			{
				$pending[intval($post['nodeid'])] = array('nodeid' => intval($post['nodeid']), 'contenttypeid' => intval($post['contenttypeid']));
			}

			$result = array('nodes' => $pending);

			// if totalcount flag is set...
			if ($options['totalcount'])
			{
				$page = $params[vB_dB_Query::PARAM_LIMITPAGE];
				$perpage = $params[vB_dB_Query::PARAM_LIMIT];
				unset($params[vB_dB_Query::PARAM_LIMITPAGE]);
				unset($params[vB_dB_Query::PARAM_LIMIT]);
				$countInfo = vB::getDbAssertor()->getRow('vBForum:fetchPendingPostsCount',
					$params
				);

				$result['totalcount'] = intval($countInfo['ppCount']);
				$pagecount = ceil($result['totalcount']/$perpage);
				if ($page > 1)
				{
					$prevpage = $page - 1;
				}
				else
				{
					$prevpage = false;
				}

				if ($page < $pagecount)
				{
					$nextpage = $page + 1;
				}
				else
				{
					$nextpage =false;
				}

				$pageInfo = array('totalcount' => $result['totalcount'], 'pages' => $pagecount, 'nextpage' => $nextpage, 'prevpage' => $prevpage, 'perpage' => $perpage, 'currentpage' => $page);
				$result['pageInfo'] = $pageInfo;
			}
		}

		return $result;
	}

	/**
	 * Function wrapper for listPendingPosts but used for current user.
	 *
	 */
	public function listPendingPostsForCurrentUser($options = array())
	{
		$result = $this->listPendingPosts(vB::getUserContext()->fetchUserId(), $options);

		if (isset($result['totalcount']))
		{
			$totalCount = intval($result['totalcount']);
		}

		if (isset($result['pageInfo']))
		{
			$pageInfo = $result['pageInfo'];
		}


		$contenttypes = array();
		$nodes = array();

		foreach ($result['nodes'] AS $node)
		{
			$contenttypeid = $node['contenttypeid'];
			$nodeid = $node['nodeid'];

			if (!isset($contenttypes[$contenttypeid]))
			{
				$contenttypes[$contenttypeid] = array();
			}
			$contenttypes[$contenttypeid][] = $nodeid;
			$nodes[$nodeid] = $node;
		}

		//For each type, get the content detail.
		foreach ($contenttypes as $contenttypeid => $nodeList)
		{
			if (!empty($nodes))
			{
				$contentApi = vB_Api_Content::getContentApi($contenttypeid);
				$contentList = $contentApi->getFullContent($nodeList);
				foreach ($nodes as $nodeid => $node)
				{
					foreach ($contentList as $key => $content)
					{
						if ($content['nodeid'] == $nodeid)
						{
							$nodes[$nodeid]['content'] = $content;
							break;
						}
					}
				}
			}
		}

		$userApi = vB_Api::instanceInternal('user');
		$pmContentType = vB_Types::instance()->getContentTypeId('vBForum_PrivateMessage');
		//We need a list of parents for nodes that are neither starters nor replies.
		$parents = array();
		//add parent, visitormessage, and author information
		foreach ($nodes AS $nodeid => $node)
		{
			if (($node['content']['starter'] != $node['content']['nodeid']) AND ($node['content']['starter'] != $node['content']['parentid']))
			{
				$parents[$nodeid] = $node['content']['parentid'];
			}

			if ($node['content']['contenttypeid'] == $pmContentType)
			{
				$nodes[$nodeid]['isVisitorMessage'] = 1;
			}
			else
			{
				$nodes[$nodeid]['isVisitorMessage'] = 0;
			}

			$nodes[$nodeid]['userinfo'] = array(
				'avatar'	=> $userApi->fetchAvatar($node['content']['userid'], array('avatar'), $node['content']['userinfo']),
				'userid'	=> $node['content']['userid'],
				'username'	=> $node['content']['userinfo']['username']
			);
		}

		//See if we need to add some parent information
		if (!empty($parents))
		{
			$parentInfo = vB_Api::instanceInternal('node')->getNodes($parents);

			foreach ($parents AS $nodeid => $parentid)
			{
				foreach ($parentInfo AS $info)
				{
					if ($info['nodeid'] == $parentid)
					{
						$nodes[$nodeid]['parent'] = $info;
					}
				}
			}
		}

		$this->addOptionalContentInfo($nodes, $options);
		$this->markSubscribed($nodes);
		$return = array('nodes' => $nodes);
		if (isset($totalCount))
		{
			$return['totalcount'] = $totalCount;
		}
		else
		{
			$return['totalcount'] = count($nodes);
		}

		if (isset($pageInfo) AND !empty($pageInfo))
		{
			$return['pageInfo'] = $pageInfo;
		}
		return $return;
	}

	/**
	 * Sets or unsets the approved field
	 * @param array $nodeids
	 * @param boolean $approved - set or unset the approved field
	 * @throws vB_Exception_Api
	 * @return array - the nodeids that have the permission to be changed
	 */
	function setApproved($approveNodeIds, $approved = true)
	{
		if (empty($approveNodeIds))
		{
			return false;
		}

		$loginfo = array();
		$nodeIds = array();

		foreach ($approveNodeIds AS $idx => $id)
		{
			$nodeInfo = $this->getNode($id);
			if (!empty($nodeInfo['errors']))
			{
				continue;
			}

			if (!$nodeInfo['approved'] AND !$approved)
			{
				continue;
			}

			if ($nodeInfo['approved'] AND $approved)
			{
				continue;
			}

			$nodeIds[] = $nodeInfo['nodeid'];

			$loginfo[] = array(
				'nodeid'		=> $nodeInfo['nodeid'],
				'nodetitle'		=> $nodeInfo['title'],
				'nodeusername'	=> $nodeInfo['authorname'],
				'nodeuserid'	=> $nodeInfo['userid']
			);
		}

		if (empty($nodeIds))
		{
			return false;
		}

		$errors = array();
		$assertor = vB::getDbAssertor();

		$result = $assertor->update('vBForum:node', array('approved' => $approved), array(array('field' => 'nodeid', 'value' => $nodeIds, 'operator' => vB_dB_Query::OPERATOR_EQ)));

		if (!empty($result['errors']))
		{
			$errors[] = $result['errors'];
		}

		$method = empty($approved) ? 'unapproveNode' : 'approveNode';
		$result = $assertor->assertQuery('vBForum:' . $method, array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'nodeid' => $nodeIds));

		if (!empty($result['errors']))
		{
			$errors[] = $result['errors'];
		}

		$counts = $updates = array();
		$nodeIds = array_unique($nodeIds);

		foreach ($nodeIds as $node)
		{
			$parents = $this->fetchClosureParent($node);

			foreach ($parents as $parent)
			{
				$nodeInfo = $this->getNodeBare($parent['parent']);

				if ($nodeInfo['contenttypeid'] == $this->channelTypeId)
				{
					$result = $assertor->assertQuery('vBForum:fixNodeLast', array('nodeid' => $parent['parent']));
				}
				else
				{
					$result = $assertor->assertQuery('vBForum:updateLastData', array('parentid' => $parent['parent'], 'timenow' => vB::getRequest()->getTimeNow()));
				}

				if (!empty($result['errors']))
				{
					$errors[] = $result['errors'];
				}

				switch($parent['depth'])
				{
					case 0: // Actual node.
						vB_Node::fixNodeCount($parent['parent']);
					break;

					case 1: // Immediate parent.
						$parentinfo = $this->getNodeBare($parent['parent']);

						$counts = array(
							'totalcount' => $parentinfo['totalcount'],
							'totalunpubcount' => $parentinfo['totalunpubcount'],
						);

						vB_Node::fixNodeCount($parent['parent']);
						$parentinfo = $this->getNodeBare($parent['parent']);

						$counts = array(
							'totalcount' => $parentinfo['totalcount'] - $counts['totalcount'],
							'totalunpubcount' => $parentinfo['totalunpubcount'] - $counts['totalunpubcount'],
						);
					break;

					default: // Higher parents.
						$updates['totalcount'][$parent['parent']] = $counts['totalcount'];
						$updates['totalunpubcount'][$parent['parent']] = $counts['totalunpubcount'];
					break;
				}
			}

			$assertor->assertQuery('vBForum:updateNodeTotals', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD, 'updates' => $updates));
		}

		$this->clearCacheEvents($nodeIds);
		$this->clearChildCache($nodeIds);

		if (!empty($errors))
		{
			return array('errors' => $errors);
		}

		vB_Library_Admin::logModeratorAction($loginfo, ($approved ? 'node_approved_by_x' : 'node_unapproved_by_x'));

		return $nodeIds;
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
		if (empty($nodeIds))
		{
			return false;
		}

		if (!is_array($nodeIds))
		{
			$nodeIds = array($nodeIds);
		}

		$cachedNodes = array();
		$notCached = array();
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		foreach($nodeIds AS $nodeid)
		{
			$cachedNodes[] = 'nodeChg_' . $nodeid;

			$hashKey = 'node_' . $nodeid . "_lvl3data";
			$cached = $cache->read($hashKey);

			//We need the parent, starter, and channel. Let's see if we have those in fast cache.
			if ($cached)
			{
				foreach(array('starter', 'parentid', 'channelid') AS $field)
				{
					$cachedNodes['nodeChg_' . $cached[$field]] = 'nodeChg_' . $cached[$field];
				}
			}
			else
			{
				$notCached[] = $nodeid;
			}
		}

		if (!empty($notCached))
		{
			$parents = vB::getDbAssertor()->assertQuery('vBForum:getParents', array('nodeid' => $notCached));
			foreach ($parents AS $parent)
			{
				$cachedNodes[ 'nodeChg_' . $parent['nodeid']] = 'nodeChg_' . $parent['nodeid'];
			}
		}

		try
		{
			vB_Cache::allCacheEvent($cachedNodes);
		}
		catch (Exception $ex)
		{
			throw new vB_Exception_Api($ex->getMessage());
		}

		return true;
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
		$user = vB::getCurrentSession()->fetch_userinfo();

		if (!$user['userid'])
		{
			// Guest call
			return array();
		}

		$vboptions = vB::getDatastore()->getValue('options');

		if (!$vboptions['threadmarking'])
		{
			// Cookie based read marking. Handled by presentation.
			return array();
		}

		$node = $this->getNode($nodeid);

		$timenow = vB::getRequest()->getTimeNow();
		$channelcontenttypeid = vB_Types::instance()->getContentTypeId('vBForum_Channel');

		$nodes_marked = array($node['nodeid']);

		if ($node['contenttypeid'] == $channelcontenttypeid)
		{
			// Mark channel read
			vB::getDbAssertor()->assertQuery('node_markread', array(
				'nodeid' => $node['nodeid'],
				'userid' => $user['userid'],
				'readtime' => $timenow,
			));

			// check to see if any parent channels should be marked as read as well
			$parentchannels = vB::getDbAssertor()->getRows('vBForum:closure', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'child' => $node['nodeid']
			), array(
				'field' => array('depth'),
				'direction' => array(vB_dB_Query::SORT_ASC),
			));

			$parentarray = array();
			foreach ($parentchannels as $parent)
			{
				if ($parent['parent'] != $node['nodeid'] AND $parent['parent'] != 1)
				{
					$parentarray[] = $parent['parent'];
				}
			}

			if (!empty($parentarray))
			{
				// find the top most entry in the parent list -- we need its child list
				$top_parentid = end($parentarray);
				$top_parentchildren = $this->listNodes($top_parentid, 1, 100, 0, $channelcontenttypeid, array());
				$top_parentchildrenids = array($top_parentid);
				foreach ($top_parentchildren as $child)
				{
					$top_parentchildrenids[] = $child['nodeid'];
				}

				if (!$top_parentchildrenids)
				{
					return $nodes_marked;
				}

				// determine the read time for all forums that we need to consider
				$readtimes = array();
				$readtimedata = vB::getDbAssertor()->getRows('noderead', array(
					'userid' => $user['userid'],
					'nodeid' => $top_parentchildrenids
				));
				foreach ($readtimedata as $readtime)
				{
					if(isset($readtime["nodeid"]))
					{
						$readtimes[$readtime["nodeid"]] = $readtime['readtime'];
					}
				}

				$cutoff = ($timenow - ($vboptions['markinglimit'] * 86400));

				$parentscache = vB_Api::instanceInternal('node')->getNodes($parentarray);

				// now work through the parent, grandparent, etc of the forum we just marked
				// and mark it read only if all direct children are marked read
				$parentsquery = array();
				foreach ($parentarray AS $parentid)
				{
					if (empty($parentscache["$parentid"]))
					{
						continue;
					}

					$markread = true;
					$thischildren = vB_Api::instanceInternal('node')->listNodeContent($parentid, 1, 100, 0, $channelcontenttypeid);

					// now look through all the children and confirm they are all read
					foreach ($thischildren AS $thischild)
					{
						if(isset($readtimes[$thischild['nodeid']]))
						{
							$check = max($cutoff, $readtimes[$thischild['nodeid']]);
							if (
								($thischild['lastcontent'] AND $check < $thischild['lastcontent'])
								OR
								(!$thischild['lastcontent'] AND $check < $thischild['created'])
							)
							{
								$markread = false;
								break;
							}
						}
					}

					// if all children are read, make sure all the threads in this forum are read too
					if ($markread AND isset($readtimes["$parentid"]))
					{
						$noderead = intval(max($readtimes["$parentid"], $cutoff));

						$unread = vB::getDbAssertor()->getRow('node_checktopicread', array(
							'userid' => $user['userid'],
							'parentid' => $parentid,
							'noderead' => $noderead,
						));
						if ($unread['count'] > 0)
						{
							$markread = false;
						}
					}

					if ($markread)
					{
						// can mark as read
						$readtimes["$parentid"] = $timenow;
						$parentsquery[] = array($parentid, $user['userid']);
						$nodes_marked[] = $parentid;
					}
					else
					{
						// can't mark this as read, so we have no need to continue with further generations
						break;
					}
				}

				if ($parentsquery)
				{
					foreach ($parentsquery as $parent)
					{
						vB::getDbAssertor()->assertQuery('node_markread', array(
							'nodeid' => $parent[0],
							'userid' => $parent[1],
							'readtime' => $timenow,
						));
					}
				}
			}

			return $nodes_marked;
		}
		elseif ($node['nodeid'] == $node['starter'])
		{
			// Topic
			vB::getDbAssertor()->assertQuery('node_markread', array(
				'nodeid' => $node['nodeid'],
				'userid' => $user['userid'],
				'readtime' => $timenow,
			));

			// now if applicable search to see if this was the last topic requiring marking in this channel
			if ($vboptions['threadmarking'] == 2)
			{
				// channel can only be marked as read if all the children are read as well,
				// so determine which children "count"
				$channelchildren = $this->listNodes($node['parentid'], 1, 100, 0, $channelcontenttypeid, array());
				$channelchildrenids = array($node['parentid']);
				foreach ($channelchildren as $child)
				{
					$channelchildrenids[] = $child['nodeid'];
				}
				$children = array();
				$usercontext = vB::getUserContext($user['userid']);
				foreach ($channelchildrenids as $child_forum)
				{
					if (
						!$usercontext->getChannelPermission('forumpermissions', 'canview', $child_forum)
						OR
						!$usercontext->getChannelPermission('forumpermissions', 'canviewthreads', $child_forum)
						OR
						!$usercontext->getChannelPermission('forumpermissions', 'canviewothers', $child_forum)
					)
					{
						// invalid channel, can't be viewed, can't view topics, can't view others topics
						// means we can't include this when trying to mark a thread as read
						continue;
					}

					$children[] = $child_forum;
				}

				if ($children)
				{
					$cutoff = $timenow - ($vboptions['markinglimit'] * 86400);
					$unread = vB::getDbAssertor()->getRow('node_checktopicreadinchannels', array(
						'userid' => $user['userid'],
						'children' => $children,
						'cutoff' => $cutoff,
					));
					if ($unread['count'] == 0)
					{
						$this->markRead($node['parentid']);
						vB_Cache::instance()->expire('vB_ChannelTree_' . $user['userid']);
					}
				}
			}

			return $nodes_marked;
		}
		else
		{
			return array();
		}
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
		$user = vB::getCurrentSession()->fetch_userinfo();

		if (!$user['userid'])
		{
			// Guest call
			return array();
		}

		$vboptions = vB::getDatastore()->getValue('options');
		$timenow = vB::getRequest()->getTimeNow();
		$return_channels = array();

		if ($nodeid)
		{
			$node = $this->getNode($nodeid);

			$channelcontenttypeid = vB_Types::instance()->getContentTypeId('vBForum_Channel');

			if ($node['contenttypeid'] != $channelcontenttypeid)
			{
				return array();
			}

			$children = $this->listNodes($node['nodeid'], 1, 100, 0, $channelcontenttypeid, array());
			$childrenids = array($node['nodeid']);
			foreach ($children as $child)
			{
				$childrenids[] = $child['nodeid'];
			}
			$return_channels = $childrenids;

			if ($vboptions['threadmarking'] AND $user['userid'])
			{
				foreach ($return_channels as $channelid)
				{
					// mark the channel and all child channels read
					vB::getDbAssertor()->assertQuery('node_markread', array(
						'nodeid' => $channelid,
						'userid' => $user['userid'],
						'readtime' => $timenow,
					));
				}

				$parent_marks = $this->markRead($nodeid);
				if (is_array($parent_marks))
				{
					$return_channels = array_unique(array_merge($return_channels, $parent_marks));
				}
			}
		}
		else
		{
			// Mark all channels read
			if ($user['userid'])
			{
				// init user data manager
				$userdata = new vB_Datamanager_User(null, vB_DataManager_Constants::ERRTYPE_STANDARD);
				$userdata->set_existing($user);
				$userdata->set('lastactivity', $timenow);
				$userdata->set('lastvisit', $timenow - 1);
				$userdata->save();

				if ($vboptions['threadmarking'])
				{
					$channels = vB_Api::instanceInternal('search')->getChannels(true);
					foreach ($channels AS $nodeid => $channelinfo)
					{
						// mark the channel and all child channels read
						vB::getDbAssertor()->assertQuery('node_markread', array(
							'nodeid' => $nodeid,
							'userid' => $user['userid'],
							'readtime' => $timenow,
						));
						$return_channels[] = $nodeid;
					}
				}
			}
		}

		vB_Cache::instance()->expire('vB_ChannelTree_' . $user['userid']);
		return $return_channels;
	}


	/** marks nodes with "subscribed" true/false
	*
	*	@param	array	list of nodes, normally with a content array.
	**/
	public function markSubscribed(&$nodes)
	{
		$following = vB_Api::instanceInternal('follow')->getFollowingParameters();
		$check = array();
		foreach ($nodes AS $key => &$node)
		{
			if (array_key_exists('content', $node))
			{
				$node['content']['subscribed'] = 0;
			}
			else
			{
				$node['subscribed'] = 0;
			}
			$check[$node['nodeid']]	= $key;
		}

		//if this user isn't following anyone, we don't need to do this check.
		if (!empty($following['user']) )
		{
			foreach ($nodes AS $key => &$node)
			{
				if (in_array($node['userid'], $following['user']))
				{
					$node['content']['subscribed'] = 1;
				}
			}
		}

		//if there's nothing to check and no followed content, we're done.
		if (empty($check) or (empty($following['content']) AND empty($following['member'])))
		{
			return;
		}
		//We have both followed nodes and content, so we need to run a query and check.
		$followNodes = array_merge($following['content'], $following['member']);
		$clParents = $this->fetchClosureParent(array_keys($check));
		$parents = array();
		foreach ($clParents AS $parent)
		{
			//We have a child value in $closureRec['child'] is an index into $check, which gives an index into $nodes;
			if (in_array($parent['parent'], $followNodes))
			{
				$nodeKey = $check[$parent['child']];
				//This node is followed.
				if (array_key_exists('content', $nodes[$nodeKey]))
				{
					$nodes[$nodeKey]['content']['subscribed'] = 1;
				}
				else
				{
					$nodes[$nodeKey]['subscribed'] = 0;
				}
			}
		}
	}

	/** Returns closure table information given a child id
	 *
	 *	@param	mixed	child nodeid or array of nodeids
	 * 	@param	int		optional parent nodeid
	 *
	 * 	@return	mixed	array of closure table records
	 **/
	public function fetchClosureParent($childids, $parentid = false)
	{
		static $cachedChildIds = array();
		//find what we have in fastcache.
		if (!is_array($childids))
		{
			$childids = array($childids);
		}

		$cachedChildIds = array_unique(array_merge($cachedChildIds, $childids));
		vB_Api::instanceInternal('page')->registerPrecacheInfo('node', 'fetchClosureParent', $cachedChildIds);
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);

		$found = array();
		$notfound = array();
		foreach ($childids AS $childid)
		{
			$data = $cache->read("vBClParents_$childid");
			if ($data)
			{
				//we marked any id's that don't have data with zero. In that case we return no results.
				if ($data === 0 )
				{
					continue;
				}

				if (($parentid === false) OR !is_numeric($parentid))
				{
					$found = array_merge($found, $data);
				}
				else
				{
					foreach ($data as $closure)
					{
						if ($closure['parent'] == $parentid)
						{
							$found[] = $closure;
							break;
						}
					}
				}
			}
			else
			{
				$notfound[$childid] = $childid;
			}
		}

		//if we got everything, we're done.
		if (empty($notfound))
		{
			return $found;
		}

		//Search for what's left
		//Note that even if we were passed a parentid we still get the complete ancestry and cache it.
		$closureRecs = vB::getDbAssertor()->assertQuery('vBForum:closure', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'child' => $notfound), array('field' => (array('child', 'depth')), 'direction' => array(vB_dB_Query::SORT_ASC, vB_dB_Query::SORT_ASC)));

		//Now we build the results and cache the values;
		$thisChild = false;
		foreach ($closureRecs AS $closure)
		{
			//If we changed child id values and this isn't the first value, write to cache
			// and start building the ancestry for the new child.
			if ($thisChild != $closure['child'])
			{
				if ($thisChild)
				{
					$cache->write("vBClParents_$thisChild", $cacheValue, 1440, $cacheEvents);
					unset($notfound[$thisChild]);

					if (empty($parentid) OR ($closure['parent'] == $parentid))
					{
						foreach ($cacheValue as $value)
						{
							$found[] = $value;
						}
					}
				}

				$cacheValue = array();
				$cacheEvents = array();
				$thisChild = $closure['child'];
			}

			$cacheValue[] = $closure;
			$cacheEvents[] = 'nodeChg_' . $closure['parent'];
		}

		if ($thisChild)
		{
			$cache->write("vBClParents_$thisChild", $cacheValue, 1440, $cacheEvents);

			if (empty($parentid) OR ($closure['parent'] == $parentid))
			{
				foreach ($cacheValue as $value)
				{
					$found[] = $value;
				}
			}

			unset($notfound[$thisChild]);
		}

		//Any remaining records in $notfound are for nodes that aren't in the closure table.
		// we'll cache those with value zero so we don't query again.
		foreach ($notfound AS $childid)
		{
			$cache->write("vBClParents_$childid", 0, 1440, "nodeChg_$childid");
		}

		return $found;
	}


	/** Returns closure table information given a child id
	 *
	 *	@param	mixed	parent nodeid or array of nodeids
	 *
	 * 	@return	mixed	array of closure table records
	 **/
	public function fetchClosurechildren($parentids)
	{
		//find what we have in fastcache.
		if (!is_array($parentids))
		{
			$parentids = array($parentids);
		}
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$found = array();
		$notfound = array();
		foreach ($parentids AS $parentid)
		{
			$data = $cache->read("vBClChildren_$parentid");
			//we marked any id's that don't have data with zero. In that case we return no results.
			if ($data)
			{
				$found[$parentid] = $data;
			}
			else if ($data !== 0)
			{
				$notfound[$parentid] = $parentid;
			}
		}

		//if we got everything, we're done.
		if (empty($notfound))
		{
			return $found;
		}

		//Search for what's left
		//Note that even if we were passed a parentid we still get the complete ancestry and cache it.
		$closureRecs = vB::getDbAssertor()->assertQuery('vBForum:closure', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'parent' => $notfound), array('field' => array('parent', 'depth'),
			'direction' => array(vB_dB_Query::SORT_ASC, vB_dB_Query::SORT_DESC)));

		//Now we build the results and cache the values;
		$thisParent = false;
		foreach ($closureRecs AS $closure)
		{
			//If we changed child id values and this isn't the first value, write to cache
			// and start building the ancestry for the new child.
			if ($thisParent != $closure['parent'])
			{
				if ($thisParent)
				{
					$cache->write("vBClChildren_$thisParent", $cacheValue, 1440, $cacheEvents);
					unset($notfound[$thisParent]);
					$found[$thisParent] = $cacheValue;
				}

				$cacheValue = array();
				$cacheEvents = array();
				$thisParent = $closure['parent'];
			}

			$cacheValue[] = $closure;
			$cacheEvents[] = 'nodeChg_' . $closure['child'];
		}

		if ($thisParent)
		{
			$found[$thisParent] = $cacheValue;
			$cache->write("vBClChildren_$thisParent", $cacheValue, 1440, $cacheEvents);
			unset($notfound[$thisParent]);
		}


		//Any remaining records in $notfound are for nodes that aren't in the closure table.
		// we'll cache those with value zero so we don't query again.
		foreach ($notfound AS $parentid)
		{
			$cache->write("vBClChildren_$parentid", 0, 1440, "nodeChg_$parentid");
		}

		return $found;
	}

	/** This creates a request for access to a channel
	 *
	 *	@param	int		the nodeid of the channel to which access is requested.
	 *	@param	mixed	the userid/username of the member who will get the request
	 *	@param	string	the type of request-
	 *
	 **/
	public function requestChannel($channelid, $requestType, $recipient = 0, $recipientname = null, $skipFloodCheck = false)
	{
		$userInfo = vB::getCurrentSession()->fetch_userinfo();
		$userid = $userInfo['userid'];

		if (!($userid > 0))
		{
			throw new vB_Exception_API('not_logged_no_permission');
		}

		//make sure the parameters are valid
		if (!intval($channelid) OR !intval($channelid) OR
			!in_array($requestType,
				array(vB_Api_Node::REQUEST_TAKE_OWNER, vB_Api_Node::REQUEST_TAKE_MODERATOR, vB_Api_Node::REQUEST_TAKE_MEMBER,
					vB_Api_Node::REQUEST_GRANT_OWNER, vB_Api_Node::REQUEST_GRANT_MODERATOR, vB_Api_Node::REQUEST_GRANT_MEMBER,
					vB_Api_Node::REQUEST_SG_TAKE_OWNER, vB_Api_Node::REQUEST_SG_TAKE_MODERATOR, vB_Api_Node::REQUEST_SG_TAKE_MEMBER,
					vB_Api_Node::REQUEST_SG_GRANT_OWNER, vB_Api_Node::REQUEST_SG_GRANT_MODERATOR, vB_Api_Node::REQUEST_SG_GRANT_MEMBER,
					vB_Api_Node::REQUEST_TAKE_SUBSCRIBER, vB_Api_Node::REQUEST_GRANT_SUBSCRIBER, vB_Api_Node::REQUEST_SG_TAKE_SUBSCRIBER, vB_Api_Node::REQUEST_SG_GRANT_SUBSCRIBER
					))
		)
		{
			throw new vB_Exception_API('invalid_data');
		}

		if ($recipient <= 0)
		{
			if(!empty($recipientname))
			{
				// fetch by username
				$recipient = vB::getDbAssertor()->getField('user', array('username' => $recipientname));

				if (!$recipient)
				{
					throw new vB_Exception_API('invalid_data');
				}
			}
			else
			{
				throw new vB_Exception_API('invalid_data');
			}
		}

		$node = $this->getNode($channelid);

		if ($node['contenttypeid'] != vB_Types::instance()->getContentTypeId('vBForum_Channel'))
		{
			throw new vB_Exception_API('invalid_request');
		}

		//Let's make sure the user can grant this request.
		if (in_array($requestType, array(
				vB_Api_Node::REQUEST_TAKE_OWNER,
				vB_Api_Node::REQUEST_TAKE_MODERATOR,
				vB_Api_Node::REQUEST_TAKE_MEMBER,
				vB_Api_Node::REQUEST_SG_TAKE_OWNER,
				vB_Api_Node::REQUEST_SG_TAKE_MODERATOR,
				vB_Api_Node::REQUEST_SG_TAKE_MEMBER,
			)))
		{
			//Can we grant the transfer?
			$userContext = vB::getUserContext();
			if (!$userContext->getChannelPermission('moderatorpermissions', 'canaddowners', $channelid ))
			{
				throw new vB_Exception_API('no_permission');
			}
		}
		else
		{
			// join is not valid when invite only...
			if (in_array($requestType, array(vB_Api_Node::REQUEST_GRANT_MEMBER, vB_Api_Node::REQUEST_SG_GRANT_MEMBER)) AND
				(($node['nodeoptions'] & vB_Api_Node::OPTION_NODE_INVITEONLY) > 0))
			{
				throw new vB_Exception_Api('invalid_invite_only_request');
			}

			//if this is set to auto-approve we don't need to send a request.
			if (in_array($requestType, array(vB_Api_Node::REQUEST_GRANT_MEMBER, vB_Api_Node::REQUEST_SG_GRANT_MEMBER)) AND
				(($node['nodeoptions'] & vB_Api_Node::OPTION_AUTOAPPROVE_MEMBERSHIP) > 0))
			{
				$isBlog = vB_Api::instanceInternal('blog')->isBlogNode($channelid);
				$group = vB::getDbAssertor()->getRow('usergroup', array('systemgroupid' => vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID));
				if ($isBlog)
				{
					// clear follow cache
					vB_Api::instanceInternal('follow')->clearFollowCache(array($userid));
				}

				return vB_User::setGroupInTopic($userid, $channelid, $group['usergroupid']);
			}

			if (in_array($requestType, array(vB_Api_Node::REQUEST_GRANT_SUBSCRIBER, vB_Api_Node::REQUEST_SG_GRANT_SUBSCRIBER)))
			{
				// subscribe means join in blog's context
				try
				{
					//	@TODO check if using only the canview perms is fair enough... there might be cases where sg owner set canview perms for everyone that includes no joined members, even not logged users...
					if (!vB::getUserContext()->getChannelPermission('forumpermissions', 'canview', $channelid))
					{
						throw new vB_Exception_Api('invalid_special_channel_subscribe_request');
					}

					// check the auto accept first
					if (($node['nodeoptions'] & vB_Api_Node::OPTION_AUTOAPPROVE_SUBSCRIPTION) > 0)
					{
						return $response = vB_Api::instanceInternal('follow')->add($channelid, vB_Api_Follow::FOLLOWTYPE_CONTENT);
					}

					//see if this is set to invite only
					if (($node['nodeoptions'] & vB_Api_Node::OPTION_NODE_INVITEONLY) > 0  )
					{
						throw new vB_Exception_Api('invalid_special_channel_subscribe_request');
					}

					$owner = vB_Api::instanceInternal('blog')->fetchOwner($channelid);
					if (!$owner)
					{
						$recipient = $node['userid'];
					}
					else
					{
						$recipient = $owner;
					}
				}
				catch (vB_Exception_Api $ex)
				{
					throw $ex;
				}
			}

			//Can the recipient grant the transfer?
			$userContext = vB::getUserContext($recipient);
			if (!$userContext->getChannelPermission('moderatorpermissions', 'canaddowners', $channelid ))
			{
				throw new vB_Exception_API('no_permission');
			}
		}

		$messageLib = vB_Library::instance('content_privatemessage');
		$userInfo = vB::getCurrentSession()->fetch_userinfo();

		$data = array('msgtype' => 'request',
			'about' => $requestType,
			'sentto' => $recipient,
			'aboutid' => $channelid,
			'sender' => $userInfo['userid']);

		if ($skipFloodCheck)
		{
			return $messageLib->addMessageNoFlood($data);
		}
		else
		{
			return $messageLib->add($data);
		}
	}

	/**
	 * Adds the joined flag if the current user is member of content's parent.
	 *
	 * @params	array	Array of the content node list.
	 *
	 */
	protected function markJoined(&$nodes)
	{
		$userid = vB::getCurrentSession()->get('userid');

		foreach ($nodes AS $key => $node)
		{
			$nodes[$key]['joined'] = $nodes[$key]['content']['joined'] = false;
		}

		$parents = array();
		foreach ($nodes AS $key => $node)
		{
			if (empty($node['parents']))
			{
				$nodes[$key]['parents'] = $this->getNodeParents($node['nodeid']);
			}
		}

		// guests can't be members
		if ($userid < 1)
		{
			return false;
		}

		$joinedInfo = vB::getUserContext()->fetchGroupInTopic();
		foreach ($nodes AS $key => $node)
		{
			if (isset($joined[$node['nodeid']]))
			{
				$nodes[$key]['joined'] = $nodes[$key]['content']['joined'] = 1;
			}
			else if (!empty($node['parents']))
			{

				foreach($node['parents'] AS $parent)
				{
						//We get this information in two ways- parent can be an array or an integer.
					if (is_array($parent))
					{
						$parentid = $parent['parent'];
					}
					else if (is_numeric($parent))
					{
						$parentid = $parent;
					}
					else
					{
						continue;
					}

					if (isset($joinedInfo[$parentid]))
					{
						$nodes[$key]['joined'] = $nodes[$key]['content']['joined'] = 1;
						break;
					}
				}
			}
		}
	}


	/***Returns the ancestry
	 *
	 * 	@param	int		nodeid
	 * 	@return	mixed	array of partial node records
	 */
	public function getParents($nodeid)
	{
		$cacheKey = "vB_Parents_$nodeid";
		$parents = vB_Cache::instance(vB_Cache::CACHE_FAST)->read($cacheKey);
		if ($parents !== false)
		{
			return $parents;
		}

		$parents = vB::getDbAssertor()->getRows('vBForum:getParents', array('nodeid' => $nodeid));
		vB_Cache::instance(vB_Cache::CACHE_FAST)->write($cacheKey, $parents, 1440, "nodeChg_$nodeid");
		return $parents;
	}

	/***Gets parent information for a block of node ids.
	 *
	 * 	@param	mixed	array of integers
	 * 	@return	mixed	array of partial node records
	 */
	public function preLoadParents($nodeids)
	{
		if (!is_array($nodeids))
		{
			$nodeids = array($nodeids);
		}
		$results = array();
		$fastCache = vB_Cache::instance(vB_Cache::CACHE_FAST);

		foreach ($nodeids AS $key => $nodeid)
		{
			$parents = $fastCache->read("vB_Parents_$nodeid");

			if ($parents !== false)
			{
				$results[$nodeid] = $parents;
				unset($nodeids[$key]);
			}
		}

		if (empty($nodeids))
		{
			return $results;
		}

		$parents = vB::getDbAssertor()->assertQuery('vBForum:getParents', array('nodeid' => $nodeids));

		//The query is indexed by nodeid, depth.
		foreach($parents AS $parent)
		{
			if (empty($results[$parent['child']]))
			{
				$results[$parent['child']] = array();
			}
			$results[$parent['child']][$parent['depth']] = $parent;
		}

		foreach ($results AS $nodeid => $parents)
		{
			$fastCache->write("vB_Parents_$nodeid", $parents, 1440, "nodeChg_$nodeid");
		}
		return $results;
	}


	/**
	 * Check if the user has permission for edit the thread title also check the option editthreadtitlelimit
	 * if we pass the time and we are not moderators we can edit the thread title
	 * @param integer $nodeid
	 *
	 */
	public function canEditThreadTitle($nodeid, $node = false)
	{
		static $threadLimit = false;
		$userid = vB::getCurrentSession()->get('userid');

		if ($userid == 0)
		{
			return false;
		}

		$userContext = vB::getUserContext();

		if ($userContext->isSuperAdmin())
		{
			return true;
		}

		if ($threadLimit === false)
		{
			$threadLimit = vB::getDatastore()->getOption('editthreadtitlelimit');
		}

		//grab the options and the info of the node
		if (empty($node))
		{
			$node = $this->getNode($nodeid);
		}

		//check if user have moderator permissions or pass the time limit
		//The original creator can change for some period
		if (vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canmoderateposts', $nodeid))
		{
			return true;
		}
		else if (($node['userid'] == $userid)
			AND (($threadLimit == 0 ) OR ($node['publishdate'] + ($threadLimit * 60) > vB::getRequest()->getTimeNow()))
			)
		{
			return true;
		}

		return false;
	}

	/** Gets a list of the content types that change text type

	 	@return 	mixed	array of integers
	 */
	public function getTextChangeTypes()
	{
		static $changeTypes = false;

		if ($changeTypes)
		{
			return $changeTypes;
		}
		$hashKey = 'vb_textchangetypes';
		$changeTypes = vB_Cache::instance(vB_Cache::CACHE_LARGE)->read($hashKey);

		if (empty($changeTypes))
		{
			$changeTypes = array();
			$types = vB_Types::instance()->getContentTypes();
			foreach ($types AS $type)
			{
				try
				{
					$textCountChange = vB_Api_Content::getContentApi($type['id'])->getTextCountChange();

					if ($textCountChange > 0)
					{
						$changeTypes[] = $type['id'];
					}
				}
				catch (exception $e) //This is a normal occurence- just keep going.
				{}
			}
		}
		return $changeTypes;
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
			return;
		}

		// TODO: move getOptions to this library
		$optionsInfo = vB_Api::instanceInternal('node')->getOptions();
		if (is_numeric($options))
		{
			$newOptions = 0;
			//Still check each bitfield
			foreach ($optionsInfo as $key => $value)
			{
				if ($options & $value)
				{
					$newOptions += $value;
				}
			}
		}
		else
		{
			$current = $this->getNode($nodeid);
			$newOptions = $current['nodeoptions'];
			foreach ($optionsInfo as $key => $value)
			{
				if (isset($options[$key]))
				{
					if (intval($options[$key]))
					{
						$newOptions = $newOptions | $value;
					}
					else
					{
						$newOptions = $newOptions & ~intval($value);
					}
				}
			}
		}
		//And we set the value.
		$result = vB::getDbAssertor()->assertQuery('vBForum:node', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'nodeid' => $nodeid, 'nodeoptions' => $newOptions));
		$this->clearCacheEvents($nodeid);
		return $result;
	}

	/** gets the node option as an array of values
	 *
	 * 	@param	int		nodeid of the desired record
	 *
	 * 	@return	array 	associative array of bitfield name => 0 or 1
	 **/
	public function getNodeOptions($nodeid)
	{
		if (empty($nodeid) OR !intval($nodeid))
		{
			return;
		}

		$node = $this->getNode($nodeid);
		$options = array();

		$nodeOptionsBitfields = vB_Api::instanceInternal('node')->getOptions();
		foreach ($nodeOptionsBitfields  as $key => $value)
		{
			if ($node['nodeoptions'] & $value)
			{
				$options[$key] = 1;
			}
			else
			{
				$options[$key] = 0;
			}
		}

		return $options;
	}

	/**
	 * Gets the starter's parent (channel) node
	 * @param array/int $node the node or nodeid
	 */
	public function getChannelId($node)
	{
		if (is_numeric($node))
		{
			$node = $this->getNodeBare($node);
}
		// this is the channel
		if ($node['starter'] == 0)
		{
			return $node['nodeid'];
		}

		// this is the starter, so the channel is the parent
		if ($node['starter'] == $node['nodeid'])
		{
			return $node['parentid'];
		}

		// this must be a reply
		return $this->getChannelId($node['starter']);
	}

	/**
	 * Undelte a set of nodes
	 * @param array $nodeids
	 * @param boolean is rebuild needed
	 * @throws vB_Exception_Api
	 * @return array - the nodeids that have been deleted
	 */
	public function undeleteNodes($nodeids, $needRebuild = false)
	{
		if (empty($nodeids))
		{
			return false;
		}

		$errors = array();
		$events = array();
		$loginfo = array();

		$counts = $updates = array();
		$nodeids = array_unique($nodeids);

		$assertor = vB::getDbAssertor();

		$result = $assertor->assertQuery('vBForum:node', array(
			'nodeid' => $nodeids,
			'deleteuserid' => 0,
			'deletereason' => '',
			'unpublishdate' => 0,
			'showpublished' => 1,
			vB_db_Query::TYPE_KEY => vB_db_Query::QUERY_UPDATE,
			)
		);

		if (!empty($result['errors']))
		{
			$errors[] = $result['errors'];
		}

		foreach ($nodeids as $nodeid)
		{
			$events[] = $nodeid;
			$node = $this->getNode($nodeid);

			$result = $assertor->assertQuery('vBForum:publishNode', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'nodeid' => $nodeid));

			if (!empty($result['errors']))
			{
				$errors[] = $result['errors'];
			}
			else
			{
				vB_Library_Content::getContentLib($node['contenttypeid'])->incrementUserPostCount($node['userid']);
			}

			$loginfo[] = array(
				'nodeid'		=> $node['nodeid'],
				'nodetitle'		=> $node['title'],
				'nodeusername'	=> $node['authorname'],
				'nodeuserid'	=> $node['userid']
			);

			$parents = $this->fetchClosureParent($nodeid);

			foreach ($parents as $parent)
			{
				$nodeInfo = $this->getNodeBare($parent['parent']);

				if ($nodeInfo['contenttypeid'] == $this->channelTypeId)
				{
					$result = $assertor->assertQuery('vBForum:fixNodeLast', array('nodeid' => $parent['parent']));
				}
				else
				{
					$result = $assertor->assertQuery('vBForum:updateLastData', array('parentid' => $parent['parent'], 'timenow' => vB::getRequest()->getTimeNow()));
				}

				if (!empty($result['errors']))
				{
					$errors[] = $result['errors'];
				}

				switch($parent['depth'])
				{
					case 0: // Actual node.
						vB_Node::fixNodeCount($parent['parent']);
					break;

					case 1: // Immediate parent.
						$parentinfo = $this->getNodeBare($parent['parent']);

						$counts = array(
							'totalcount' => $parentinfo['totalcount'],
							'totalunpubcount' => $parentinfo['totalunpubcount'],
						);

						vB_Node::fixNodeCount($parent['parent']);
						$parentinfo = $this->getNodeBare($parent['parent']);

						$counts = array(
							'totalcount' => $parentinfo['totalcount'] - $counts['totalcount'],
							'totalunpubcount' => $parentinfo['totalunpubcount'] - $counts['totalunpubcount'],
						);
					break;

					default: // Higher parents.
						$updates['totalcount'][$parent['parent']] = $counts['totalcount'];
						$updates['totalunpubcount'][$parent['parent']] = $counts['totalunpubcount'];
					break;
				}
			}

			$assertor->assertQuery('vBForum:updateNodeTotals', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD, 'updates' => $updates));
		}

		vB_Api::instance('search')->purgeCacheForCurrentUser();

		if ($needRebuild)
		{
			vB::getUserContext()->rebuildGroupAccess();
		}

		$this->clearCacheEvents($nodeids);
		$this->clearChildCache($nodeids);

		if (!empty($errors))
		{
			return array('errors' => $errors);
		}

		vB_Library_Admin::logModeratorAction($loginfo, 'node_restored_by_x');

		return $nodeids;
	}
}
