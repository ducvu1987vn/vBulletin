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
 * vB_Api_Vb4_forumdisplay
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Vb4_forumdisplay extends vB_Api
{
	public function call($forumid, $perpage = 20, $pagenumber = 1)
	{
		$contenttype = vB_Api::instance('contenttype')->fetchContentTypeIdFromClass('Channel');
		$forum = vB_Api::instance('node')->getNodeFullContent($forumid);
		if(empty($forum) OR isset($forum['errors']))
		{
			return array("response" => array("errormessage" => array("invalidid")));
		}

		$forum = $forum[$forumid];
		$modPerms = vB::getUserContext()->getModeratorPerms($forum);

		$foruminfo = array(
			'forumid' 		=> $forum['nodeid'],
			'title'			=> $forum['title'],
			'description'	=> $forum['description'],
			'title_clean'	=> $forum['htmltitle'],
			'description_clean'	=> strip_tags($forum['description']),
			'prefixrequired' => 0
		);

		$nodes = vB_Api::instance('node')->fetchChannelNodeTree($forumid, 3, 1, 100);
		$channels = array();

		if (!empty($nodes) AND empty($nodes['errors']))
		{
			foreach ($nodes['channels'] AS $node)
			{
				$channels[] = vB_Library::instance('vb4_functions')->parseForum($node);
			}
		}
		$forumbits = $channels;

		$topics = array();
		$topics_sticky = array();

		$page_nav = vB_Library::instance('vb4_functions')->pageNav(1, $perpage, 1);

		$search = array("channel" => $forumid);
		$search['view'] = vB_Api_Search::FILTER_VIEW_TOPIC;
		$search['depth'] = 1;
		$search['include_sticky'] = true;
		$search['sort']['lastcontent'] = 'desc';
		$search['nolimit'] = 1;
		$topic_search = vB_Api::instanceInternal('search')->getInitialResults($search, $perpage, $pagenumber, true);

		if (!isset($topic_search['errors']) AND !empty($topic_search['results']))
		{
			foreach ($topic_search['results'] AS $key => $node)
			{
				if ($node['content']['contenttypeclass'] == 'Channel' OR $node['content']['starter'] != $node['content']['nodeid'])
				{
					unset($topic_search['results'][$key]);
				}
				else
				{
					$topic = vB_Library::instance('vb4_functions')->parseThread($node);
					if($topic['thread']['sticky'])
					{
						$topics_sticky[] = $topic;
					}
					else
					{
						$topics[] = $topic;
					}
				}
			}

			$page_nav = vB_Library::instance('vb4_functions')->pageNav($topic_search['pagenumber'], $perpage, $topic_search['totalRecords']);
		}

		$inlinemod = $forum['canmoderate'] ? 1 : 0;
		$subscribed = vB_Api::instance('follow')->isFollowingContent($forum['nodeid']);
		$subscribed = $subscribed ? 1 : 0;
		$forumsearch = vB::getUserContext()->hasPermission('forumpermissions', 'cansearch');
		$response = array();
		$response['response']['forumbits'] = $forumbits;
		$response['response']['foruminfo'] = $foruminfo;
		$response['response']['threadbits'] = $topics;
		$response['response']['threadbits_sticky'] = $topics_sticky;
		$response['response']['pagenav'] = $page_nav;
		$response['response']['pagenumber'] = intval($pagenumber);
		$response['show'] = array(
			'subscribed_to_forum' => $subscribed,
			'inlinemod' => $inlinemod,
			'spamctrls' => $modPerms['candeleteposts'] > 0 ? 1 : 0,
			'openthread' => $modPerms['canopenclose'] > 0 ? 1 : 0,
			'approvethread' => $modPerms['canmoderateposts'] > 0 ? 1 : 0,
			'movethread' => $modPerms['canmassmove'] > 0 ? 1 : 0,
			'forumsearch' => $forumsearch,
		);

		return $response;
	}

	private function removeChannelKeys(&$channel)
	{
		if(is_array($channel['subforums']))
		{
			foreach($channel['subforums'] as $channel1)
			{
				$this->removeChannelKeys($channel1);
			}
			$channel['subforums'] =& array_values($channel['subforums']);
		}
		return $channel;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
