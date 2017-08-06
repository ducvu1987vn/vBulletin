<?php

/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 5.0.0
  || # ---------------------------------------------------------------- # ||
  || # Copyright 2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

class vB5_Frontend_Controller_Activity extends vB5_Frontend_Controller
{
	public function __construct()
	{
		parent::__construct();
	}

	protected function fetchChannelNodes($parent, $page, $perpage, $depth = 3, $contentype = 0, $options = false)
	{
		$api = Api_InterfaceAbstract::instance();
		$optionsArray = array();
		if (!$contentype)
		{
			$contentype = vB_Types::instance()->getContentTypeID('vBForum_Channel');
		}

		$params = array(
			'parentid'		=> $parent,
			'page'			=> $page,
			'perpage'		=> $perpage,
			'depth'			=> $depth,
			'contenttypeid'		=> $contentype,
			'options'		=> $options
		);
		return $api->callApi('node', 'listNodeFullContent', $params);
	}

	public function actionGet()
	{
		$filters = isset($_POST['filters']) ? $_POST['filters'] : array();
		if (empty($filters['view']))
		{
			$result = array('error' => 'invalid_request');
			$this->sendAsJson($result);
			return;
		}
		$search = array();
		$stickySearchOptions = array('sticky_only' => 1);
		$stickynodes = array();
		if (isset($filters['q']) AND trim($filters['q']) != '')
		{
			$search['keywords'] = $filters['q'];
		}
		else
		{
			$filters['q'] = false;
		}

		if (!empty($filters['exclude_type']))
		{
			$search['exclude_type'] = $filters['exclude_type'];
		}

		if (!empty($filters['userid']))
		{
			$search['authorid'] = $filters['userid'];
		}

		if (isset($filters['filter_prefix']))
		{
			if (!empty($filters['filter_prefix']))
			{
				if ($filters['filter_prefix'] == '-1')
				{
					$search['no_prefix'] = 1;
				}
				else if ($filters['filter_prefix'] == '-2')
				{
					$search['has_prefix'] = 1;
				}
				else
				{
					$search['prefix'] = $filters['filter_prefix'];
				}
			}
			else
			{
				// Any thread, regardless of prefix, don't set $search['prefix']
			}
		}
		if (isset($filters['nodeid']) AND intval($filters['nodeid']) > 0)
		{
			$search['channel'] = $filters['nodeid'];
		}

		switch ($filters['view'])
		{
			case 'topic':
				$search['view'] = vB_Api_Search::FILTER_VIEW_TOPIC;
				$search['exclude_sticky'] = true;
				$search['nolimit'] = !empty($filters['nolimit']);
				if (!empty($filters['depth']))
				{
					$search['depth'] = $filters['depth'];
				}
				$search['depth_exact'] = !empty($filters['depth_exact']);
				break;
			case 'channel': //Channel view is the same with Activity view except that Channel view's search scope is within that channel only as specified by the channel nodeid in the 'channel' filter
				$search['include_sticky'] = true;
			case 'activity':
				//Per Product, if New Topics filter in activity stream is ON, display latest starters only.
				//if OFF, display latest starter, reply or comment per topic
				if (isset($filters['filter_new_topics']) AND $filters['filter_new_topics'] == 'new_topics_on')
				{
					$search['starter_only'] = true;
				}
				$search['view'] = vB_Api_Search::FILTER_VIEW_ACTIVITY;
				break;
			case 'stream':
				$search['view'] = vB_Api_Search::FILTER_VIEW_CONVERSATION_STREAM;
				$search['include_starter'] = true;
				$search['depth'] = 2;
				break;
			case 'thread':
				$search['view'] = vB_Api_Search::FILTER_VIEW_CONVERSATION_THREAD;
				$search['include_sticky'] = true;
				$search['include_starter'] = true;
				$search['depth'] = 1;
				$search['nolimit'] = !empty($filters['nolimit']);
				if ($filters['q'])
				{
					$search['view'] = vB_Api_Search::FILTER_VIEW_CONVERSATION_THREAD_SEARCH;
				}
			break;
		}

		if (!empty($filters[vB_Api_Node::FILTER_DEPTH]))
		{
			$search['depth'] = intval($filters[vB_Api_Node::FILTER_DEPTH]);
		}

		if (isset($filters[vB_Api_Node::FILTER_SORT]))
		{
			switch($filters[vB_Api_Node::FILTER_SORT])
			{
				case vB_Api_Node::FILTER_SORTFEATURED:
					$search['featured'] = 1;
					break;
				case vB_Api_Node::FILTER_SORTPOPULAR:
					$search['sort']['votes'] = 'desc';
					break;
				case vB_Api_Node::FILTER_SORTOLDEST:
					if (isset($filters['view']) AND $filters['view'] == 'topic')
					{
						$search['sort']['lastcontent'] = 'asc';
					}
					else
					{
						$search['sort']['created'] = 'asc';
					}
					break;
				case vB_Api_Node::FILTER_SORTMOSTRECENT:
				default:
					if (empty($filters[vB_Api_Node::FILTER_ORDER]))
					{
						$filters[vB_Api_Node::FILTER_ORDER] = 'desc';
					}

					if (isset($filters['view']) AND $filters['view'] == 'topic')
					{
						$search['sort'][$filters[vB_Api_Node::FILTER_SORT]] = $filters[vB_Api_Node::FILTER_ORDER];
					}
					else
					{
						$search['sort']['created'] = 'desc';
					}
					break;
			}
		}
		elseif ($filters['view'] == 'thread')
		{
			$search['sort']['created'] = 'asc';
		}
		elseif ($filters['view'] == 'topic')
		{
			$search['sort']['lastcontent'] = 'desc';
		}

		if (isset($filters['checkSince']) AND is_numeric($filters['checkSince']))
		{
			$search['date']['from'] = $filters['checkSince'] + 1;
		}
		elseif (isset($filters['date']) OR isset($filters['filter_time']))
		{
			$date_filter = empty($filters['date']) ? $filters['filter_time'] : $filters['date'];
			switch($date_filter)
			{
				case 'time_today':
					$search['date']['from'] = 'lastDay';//vB_Api_Search::FILTER_LASTDAY;
				break;
				case 'time_lastweek':
					$search['date']['from'] = 'lastWeek';//vB_Api_Search::FILTER_LASTWEEK;
				break;
				case 'time_lastmonth':
					$search['date']['from'] = 'lastMonth';//vB_Api_Search::FILTER_LASTMONTH;
				break;
				case 'time_lastyear':
					$search['date']['from'] = 'lastYear';//vB_Api_Search::FILTER_LASTYEAR;
				break;
				case 'time_all':
				default:
					$search['date'] = 'all';
				break;
			}
		}

		if (isset($filters[vB_Api_Node::FILTER_SHOW]) AND strcasecmp($filters[vB_Api_Node::FILTER_SHOW], vB_Api_Node::FILTER_SHOWALL) != 0)
		{
			$search['type'] = $filters[vB_Api_Node::FILTER_SHOW];
		}

		$search['ignore_protected'] = 1;

		$nodes = Api_InterfaceAbstract::instance()->callApi('search', 'getInitialResults', array(
				$search,
				empty($filters['per-page']) ? false : $filters['per-page'],
				empty($filters['pagenum']) ? false : $filters['pagenum'],
				true
		));

		if (!empty($nodes) AND !empty($nodes['errors']))
		{
			$result = array('error' => $nodes['errors'][0][0]);
			$this->sendAsJson($result);
			return;
		}

		//the same selected search filters except 'exclude_sticky' should also be applied when fetching sticky topics
		if (($filters['view'] == 'topic') AND (empty($filters['pagenum']) OR $filters['pagenum'] == 1 OR vB::getDatastore()->getOption('showstickies')))
		{
			$stickySearchOptions = array_merge($search, $stickySearchOptions);
			unset($stickySearchOptions['exclude_sticky']);
			$stickynodes = Api_InterfaceAbstract::instance()->callApi('search', 'getInitialResults', array($stickySearchOptions));
		}

		if (empty($filters['maxpages']))
		{
			$filters['maxpages'] = 0;
		}

		switch($filters['view'])
		{
			case 'activity':
				$result = $this->processActivityStream($nodes, true, $filters['maxpages']);
				break;
			case 'thread':
			case 'stream':
				$result = $this->processConversationDetail($nodes, $filters, $filters['maxpages']);
				break;
			case 'topic':
				$result = $this->processTopics($nodes, $stickynodes, $filters['maxpages']);
				break;
			case 'channel':
			default:
				$result = $this->processActivityStream($nodes, false, $filters['maxpages']);
				break;
		}

		if (!$result['lastDate'])
		{
			$result['lastDate'] = time();
		}
		$this->sendAsJson($result);
	}

	public function actionBlogFilter()
	{
	}

	public function actionBloglist()
	{
		$channels = $channelHierarchy = array();
		$api = Api_InterfaceAbstract::instance();
		$blogChannel = $api->callApi('blog', 'getBlogChannel', array());
		$nodes = $this->fetchChannelNodes(9, 1, 100);
		$templater = new vB5_Template('display_Forums');

		if (!empty($nodes) AND empty($nodes['errors']))
		{

			foreach ($nodes AS $node)
			{
				$channels[$node['nodeid']] = array(
					'nodeid' 		=> $node['nodeid'],
					'routeid' 		=> $node['routeid'],
					'title'			=> $node['title'],
					'description'		=> $node['description'],
					'parentid' 		=> $node['parentid'],
					'textcount'		=> $node['textcount'],
					'totalcount'		=> $node['totalcount'],
					'viewing'		=> 0, //@TODO: is the number of 'viewing' users implemented in api?
					'lastcontent' 	=> array(
						'nodeid'	=> $node['lastcontentid'],
						'title'		=> '',
						'authorname'	=> $node['lastcontentauthor'],
						'userid'	=> $node['lastauthorid'],
						'starter'	=> array(),
					),
					'subchannels'		=> array(),
				);
			}

			foreach ($channels as $channel)
			{
				$nodeId = $channel['nodeid'];
				$parentId = $channel['parentid'];
				if ($channel['lastcontent']['nodeid'] > 0)
				{
					$node = $api->callApi('node', 'getFullContentforNodes', array(array($channel['lastcontent']['nodeid'])));
					if (is_array($node))
					{
						$node = array_pop($node);
					}
					$channels[$nodeId]['lastcontent']['title'] = $node['content']['title'];
					$channels[$nodeId]['lastcontent']['created'] = $node['content']['created'];
					$channels[$nodeId]['lastcontent']['parentid'] = $node['content']['parentid'];
					$channels[$nodeId]['lastcontent']['starter']['nodeid'] = $node['content']['starter'];
					$channels[$nodeId]['lastcontent']['starter']['routeid'] = $node['content']['starterroute'];
					$channels[$nodeId]['lastcontent']['starter']['title'] = $node['content']['startertitle'];
				}

				if (isset($channels[$parentId]))
				{
					// assign by reference, so subchannels can be filled in later
					$channels[$parentId]['subchannels'][$nodeId] =& $channels[$nodeId];
				}
				else
				{
					// assign by reference, so subchannels can be filled in later
					$channelHierarchy[$nodeId] =& $channels[$nodeId];
				}
			}
			$templater->register('channels', $channelHierarchy);
		}
		else
		{
			$templater->register('channels', $nodes);
		}

		$this->outputPage($templater->render());
	}

	protected function processActivityStream($nodes, $showChannelInfo, $maxpages = 0)
	{
		$result = array(
			'total'				=> 0,
			'total_with_sticky'	=> 0,
			'lastDate'			=> 0,
			'template'	=>		'',
			'pageinfo'	=>		array(
				'pagenumber'	=> 1,
				'totalpages'	=> 1
			),
		);
		if(!isset($nodes['errors']) AND !empty($nodes['results']))
		{
			$api = Api_InterfaceAbstract::instance();

			foreach ($nodes['results'] AS $node)
			{
				if (empty($node['content']))
				{
					$conversation = $node;
				}
				else
				{
					$conversation = $node['content'];
				}

				$templateName = 'display_contenttype_conversationreply_' . $conversation['contenttypeclass'];
				$templater = new vB5_Template($templateName);
				$templater->register('conversation', $conversation);
				$templater->register('reportActivity', true);
				$templater->register('showChannelInfo', $showChannelInfo);

				$nodeParents = (isset($conversation['parents']) AND count($conversation['parents']) > 3) ? $conversation['parents'] : false;
				if ($nodeParents)
				{
					if (!isset($blogChannelId))
					{
						try
						{
							$blogChannelId = $api->callApi('blog', 'getBlogChannel');
						}
						catch (Exception $e)
						{
							$blogChannelId = false;
						}
					}
					$firstParent = current($nodeParents);
					if (is_array($firstParent))
					{
						if (array_key_exists('nodeid', $firstParent))
						{
							foreach ($nodeParents as $parent)
							{
								$nodeids[] = $parent['nodeid'];
							}
							$nodeParents = $nodeids;
							unset($nodeids); // clear var for next node. Otherwise nodes start "sharing" parents
						}
					}
					$currentNodeIsBlog = (!empty($blogChannelId) AND in_array($blogChannelId, $nodeParents));
				}
				else
				{
					$currentNodeIsBlog = $api->callApi('blog', 'isBlogNode', array($conversation['nodeid']));
				}

				$templater->register('currentNodeIsBlog', $currentNodeIsBlog);

				$result['template'] .= "\n" . $templater->render() . "\n";
				$result['total']++;
				$result['lastDate'] = max($result['lastDate'], $node['publishdate']);
			}
			$result['pageinfo']['pagenumber'] = $nodes['pagenumber'];
			$result['pageinfo']['totalpages'] = (!empty($maxpages) AND $maxpages < $nodes['totalpages']) ? $maxpages : $nodes['totalpages'];
			$result['pageinfo']['resultId'] = $nodes['resultId'];
		}
		$result['total_with_sticky'] = $result['total'];
		$result['nodes'] = $nodes['results'];
		return $result;
	}

	protected function processConversationDetail($nodes, $filters, $maxpages = 0)
	{
		$view = $filters['view'];
		$result = array(
			'total' 	=> 0,
			'lastDate'	=> 0,
			'template'	=> '',
			'pageinfo'	=> array(
				'pagenumber'	=> 1,
				'totalpages'	=> 1,
			),
		);
		if(!isset($nodes['errors']) AND !empty($nodes['results']))
		{
			$showInlineMod = ($view == 'thread');
			if ($view == 'thread')
			{
				$showInlineMod =  true;
				$templateSuffix = 'starter';
				$pagingInfo = array(
					'currentpage'	=> $filters['pagenum'],
					'perpage'		=> $filters['per-page']
				);
			}
			else
			{
				$showInlineMod = false;
				$templateSuffix = 'reply';
				$pagingInfo = array();
			}
			$baseTemplateName = 'display_contenttype_' . ($view == 'stream' ? 'conversation%s_' : 'conversation%s_threadview_');
			$postIndex =  0;
			foreach ($nodes['results'] AS $node)
			{
				$updateIndex = true;
				$templateName = $baseTemplateName;
				if ($node['content']['starter'] == $node['content']['nodeid'])
				{
					$templateName = sprintf($templateName, $templateSuffix);
					$templateName .= $node['content']['contenttypeclass'];
					$conversation = $node['content'];
					$postIndex = 1;
					$updateIndex = false;
				}
				elseif ($view == 'thread' AND $node['content']['parentid'] != $node['content']['starter'])
				{
					//we don't need comments for thread view
					continue;
				}
				else
				{
					$templateName = sprintf($templateName, 'reply');
					$templateName .= $node['content']['contenttypeclass'];
					$conversation = $node['content'];
				}

				$templater = new vB5_Template($templateName);
				$templater->register('nodeid', $conversation['nodeid']);
				$templater->register('conversation', $conversation);
				$templater->register('reportActivity', false);
				$templater->register('showInlineMod', $showInlineMod);
				$templater->register('pagingInfo', $pagingInfo);
				if ($conversation['unpublishdate'])
				{
					$templater->register('hidePostIndex', true);
					$templater->register('postIndex', null);
				}
				else
				{
					$templater->register('postIndex', $postIndex);
					if ($updateIndex)
					{
						$postIndex++;
					}
				}

				$result['template'] .= "\n" . $templater->render() . "\n";
				$result['total']++;
				$result['lastDate'] = max($result['lastDate'], $node['publishdate']);
			}
			$result['pageinfo']['pagenumber'] = $nodes['pagenumber'];
			$result['pageinfo']['totalpages'] = (!empty($maxpages) AND $maxpages < $nodes['totalpages']) ? $maxpages : $nodes['totalpages'];
			$result['pageinfo']['resultId'] = isset($nodes['resultId']) ? $nodes['resultId'] : null;
		}
		$result['total_with_sticky'] = $result['total'];
		return $result;
	}

	protected function processTopics($nodes, $stickynodes, $maxpages = 0)
	{
		$result = array(
			'total' 			=> 0,
			'total_with_sticky'	=> 0,
			'lastDate'			=> 0,
			'template'			=> '',
			'pageinfo'	=> array(
				'pagenumber'	=> 1,
				'totalpages'	=> 1,
			),
		);

		$templater = new vB5_Template('display_Topics');
		$canmoderate  = false;
		if (!isset($nodes['errors']) AND !empty($nodes['results']))
		{
			foreach ($nodes['results'] AS $key => $node)
			{
				//only include the starter
				if ($node['content']['contenttypeclass'] == 'Channel' OR $node['content']['starter'] != $node['content']['nodeid'])
				{
					unset($nodes['results'][$key]);
				}
				else
				{
					$result['lastDate'] = max($result['lastDate'], $node['content']['publishdate']);
				}
				if (!empty($node['content']['permissions']['canmoderate']) AND !$canmoderate)
				{
					$canmoderate = 1;
					$templater->register('canmoderate', $canmoderate);
				}
			}

			$templater->register('topics', $nodes['results']);

			$result['total_with_sticky'] = $result['total'] = count($nodes['results']);
			$result['pageinfo']['pagenumber'] = $nodes['pagenumber'];
			$result['pageinfo']['totalpages'] = (!empty($maxpages) AND $maxpages < $nodes['totalpages']) ? $maxpages : $nodes['totalpages'];
			$result['pageinfo']['resultId'] = $nodes['resultId'];
		}
		elseif (isset($nodes['errors']))
		{
			$templater->register('topics', $nodes);
		}

		if (!isset($stickynodes['errors']) AND !empty($stickynodes['results']))
		{
			$result['total_with_sticky'] = $result['total'] + count ($stickynodes['results']);
			$sticky_templater = new vB5_Template('display_Topics');
			$sticky_templater->register('topics', $stickynodes['results']);
			$sticky_templater->register('topic_list_class', 'sticky-list');

			if (!$canmoderate AND empty($nodes['results']))
			{
				//It is safe to assume that if user has canmoderate permission for the first topic node in a forum, he/she has the same permission for all the nodes.
				$firstTopic = reset($stickynodes['results']);
				$canmoderate = $firstTopic['content']['permissions']['canmoderate'];
			}
			$sticky_templater->register('canmoderate', $canmoderate);

			$result['template'] .= "\n" . $sticky_templater->render() . "\n";
			$templater->register('no_header', 1);
		}

		if (!empty($nodes['results']) OR empty($stickynodes['results']))
		{
			$result['template'] .= "\n" . $templater->render() . "\n";
		}
		return $result;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
