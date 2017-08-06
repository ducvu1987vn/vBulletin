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
 * vB_Api_Vb4_showthread
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Vb4_showthread extends vB_Api
{
	public function call($threadid, $perpage = 10, $pagenumber = 1)
	{
		$thread = vB_Api::instance('node')->getFullContentforNodes(array($threadid));
		if(empty($thread))
		{
			return array("response" => array("errormessage" => array("invalidid")));
		}
		$thread = $thread[0];
		$modPerms = vB::getUserContext()->getModeratorPerms($thread);

		$posts = array();
		$page_nav = vB_Library::instance('vb4_functions')->pageNav(1, $perpage, 1);

		$search = array("channel" => $threadid);
		$search['view'] = vB_Api_Search::FILTER_VIEW_CONVERSATION_THREAD;
		$search['depth'] = 1;
		$search['include_starter'] = true;
		$search['sort']['lastcontent'] = 'asc';
		$search['nolimit'] = 1;
		$post_search = vB_Api::instanceInternal('search')->getInitialResults($search, $perpage, $pagenumber, true);

		if (!isset($post_search['errors']) AND !empty($post_search['results']))
		{
			foreach ($post_search['results'] AS $key => $node)
			{
				if (in_array($node['contenttypeclass'], array('Link', 'Text', 'Gallery', 'Poll')))
				{
					$posts[] = vB_Library::instance('vb4_functions')->parsePost($node);
				}
			}

			$page_nav = vB_Library::instance('vb4_functions')->pageNav($post_search['pagenumber'], $perpage, $post_search['totalRecords']);
		}

		// BEWARE content->subscribed LIES, it will be true if we are following
		// the author so make this call to get the proper data.

		$userinfo = vB_Api::instance('user')->fetchUserinfo();
		if ($userinfo['userid'] > 0)
		{
			$subscribed = vB_Api::instance('follow')->isFollowingContent($thread['nodeid']);
		}
		else
		{
			$subscribed = 0;
		}
		$response = array();
		$response['response']['thread'] = vB_Library::instance('vb4_functions')->parseThreadInfo($thread);
		$response['response']['postbits'] = $posts;
		$response['response']['pagenav'] = $page_nav;
		$response['response']['pagenumber'] = intval($pagenumber);
		$response['show'] = array(
			'inlinemod' => $thread['content']['canmoderate'] ? 1 : 0,
			'spamctrls' => $modPerms['candeleteposts'] > 0 ? 1 : 0,
			'openclose' => $modPerms['canopenclose'] > 0 ? 1 : 0,
			'approvepost' => $modPerms['canmoderateposts'] > 0 ? 1 : 0,
			'deleteposts' => $modPerms['candeleteposts'] > 0 ? 1 : 0,
			'subscribed' => $subscribed,
		);
		return $response;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
