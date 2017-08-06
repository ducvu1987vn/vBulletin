<?php
/*
 * Forum Runner
 *
 * Copyright (c) 2010-2011 to End of Time Studios, LLC
 *
 * This file may not be redistributed in whole or significant part.
 *
 * http://www.forumrunner.com
 */

function do_get_thread()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'threadid' => vB_Cleaner::TYPE_INT,
		'signature' => vB_Cleaner::TYPE_BOOL,
		'page' => vB_Cleaner::TYPE_UINT,
		'perpage' => vB_Cleaner::TYPE_UINT
	));

	if (empty($cleaned['threadid'])) {
		return json_error(ERR_INVALID_TOP);
	}

	$threadinfo = vB_Api::instance('node')->getFullContentforNodes(array($cleaned['threadid']));

	if (empty($threadinfo)) {
		return json_error(ERR_INVALID_TOP);
	}

	$pagenumber = $cleaned['page'] ? $cleaned['page'] : 1;
	$perpage = $cleaned['perpage'] ? $cleaned['perpage'] : 10;

	$do_last_unread_search = false;
	if ($pagenumber == FR_LAST_POST) {
		$do_last_unread_search = true;
		$pagenumber = 1;
	}

	$threadinfo = $threadinfo[0];
	$mod = 0;
	$posts_out = array();
	$totalposts = 0;
	$pollid = null;
	$postid = null;

	$thread_link = rtrim(fr_base_url(), "/") . vB_Api::instance('route')->getUrl($threadinfo['routeid'] . '|nosession', $threadinfo, array());
	$foruminfo = vB_Api::instance('node')->getFullContentforNodes(array($threadinfo['parentid']));
	$foruminfo = $foruminfo[0];

	if($foruminfo['content']['permissions']['canmoderate'])
	{
		$mod |= MOD_DELETEPOST;
		if ($threadinfo['sticky']) {
			$mod |= MOD_UNSTICK;
		} else {
			$mod |= MOD_STICK;
		}
		$mod |= MOD_DELETETHREAD;
		if ($threadinfo['open']) {
			$mod |= MOD_CLOSE;
		} else {
			$mod |= MOD_OPEN;
		}
		$mod |= MOD_MOVETHREAD;
		$mod |= MOD_SPAM_CONTROLS;
	}

	$search = array("channel" => $cleaned['threadid']);
	$search['view'] = vB_Api_Search::FILTER_VIEW_CONVERSATION_THREAD;
	$search['depth'] = 1;
	$search['include_starter'] = true;
	$search['sort']['created'] = 'asc';
	$search_result = vB_Api::instanceInternal('search')->getSearchResult($search);

	$topic_search = vB_Api::instance('search')->getMoreResults($search_result, $perpage, $pagenumber, false);

	if ($do_last_unread_search) {
		while ($pagenumber < $topic_search['totalpages'] && !fr_last_unread_post_on_this_page($topic_search, $userinfo)) {
			$pagenumber = $pagenumber + 1;
			$topic_search = vB_Api::instance('search')->getMoreResults($search_result, $perpage, $pagenumber, false);
			if (isset($topic_search['errors'])) {
				break;
			}
		}
	}

	if (!isset($topic_search['errors']) AND !empty($topic_search['results'])) {
		foreach ($topic_search['results'] AS $node) {
			if ($node['content']['contenttypeclass'] == 'Poll') {
				$pollid = $node['nodeid'];
			}
			if ($node['content']['contenttypeclass'] != 'Channel') {
				$posts_out[] = fr_parse_post($node, $cleaned['signature']);
			}
		}
		$totalposts = $topic_search['totalRecords'];
	}

	$out = array(
		'thread_link' => $thread_link,
		'posts' => $posts_out,
		'total_posts' => $totalposts,
		'page' => $pagenumber,
		'canpost' => $threadinfo['content']['createpermissions']['vbforum_text'],
		'mod' => $mod,
		'subscribed' => $threadinfo['content']['subscribed'],
		'title' => $threadinfo['title'],
		'canattach' => $threadinfo['content']['createpermissions']['vbforum_attach'],
	);
	if ($postid) {
		$out['gotopostid'] = $postid;
	}

	if ($pollid) {
		$out['pollid'] = $pollid;
	}

	vB_Api::instance('node')->markRead($threadinfo['nodeid']);

	$options = vB::getDatastore()->getValue('options');
	if (!$options['threadmarking']) {
		$forumview = fr_set_bbarray_cookie('discussion-view', $threadinfo['nodeid'], vB::getRequest()->getTimeNow());
	}

	return $out;
}

function do_get_poll()
{
	//
	//	Note: this implementation assumes that the Poll is
	//	this thread's starter!
	//

	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'threadid' => vB_Cleaner::TYPE_INT,
	));

	if (empty($cleaned['threadid'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	$threadinfo = vB_Api::instance('content_poll')->getContent($cleaned['threadid']);

	if (empty($threadinfo) || !empty($threadinfo['errors'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	$threadinfo = $threadinfo[$cleaned['threadid']];

	if ($threadinfo['contenttypeclass'] != 'Poll' || empty($threadinfo['options'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	$options = array();

	foreach ($threadinfo['options'] as $option) {
		$voted = false;
		if (is_array($option['voters'])) {
			$voted = in_array($userinfo['userid'], $option['voters']);
		}
		$options[] = array(
			'optionid' => $option['polloptionid'],
			'percent' => intval($option['percentage']),
			'title' => $option['title'],
			'votes' => $option['votes'],
			'voted' => $voted,
		);
	}

	$out = array(
		'title' => $threadinfo['rawtext'],
		'pollstatus' => '',
		'total' => $threadinfo['poll_votes'],
		'multiple' => $threadinfo['multiple'] ? true : false,
		'canvote' => !$threadinfo['istimeout'] && !$threadinfo['voted'] ? true : false,
		'options' => $options,
	);

	return $out;
}

function do_vote_poll()
{
	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'threadid' => vB_Cleaner::TYPE_INT,
		'options' => vB_Cleaner::TYPE_STR,
	));

	if (empty($cleaned['threadid']) || empty($cleaned['options'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	$cleaned['options'] = array_map('intval', explode(',', $cleaned['options']));

	$result = vB_Api::instance('content_poll')->vote($cleaned['options']);

	return $result ? true : false;
}

function do_get_post ()
{
	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'postid' => vB_Cleaner::TYPE_INT,
		'type' => vB_Cleaner::TYPE_STR,
		'signature' => vB_Cleaner::TYPE_BOOL,
	));

	if (empty($cleaned['postid'])) {
		return json_error(ERR_INVALID_TOP);
	}

	$html = true;

	$postinfo = vB_Api::instance('node')->getFullContentforNodes(array($cleaned['postid']));
	if (!$postinfo) {
		return json_error(ERR_INVALID_TOP);
	}

	$postinfo = $postinfo[0];
	if ($postinfo['content']['contenttypeclass'] != 'Text') {
		return json_error(ERR_INVALID_TOP);
	}

	$post = fr_parse_post($postinfo, $cleaned['signature'], $html);
	if ($html) {
		if ($cleaned['type'] === 'facebook') {
			$post['html'] = strip_tags($post['html']);
			if (!empty($post['fr_images'])) {
				$post['image'] = $post['fr_images'][0]['img'];
			}
		} else {
			$css = '
				<style>
				* {
					font-family: sans-serif;
		}
		.quotedRoot, .quoted1, .quoted2, .quoted3 {
			border-radius: 6px;
			background: #F4F5F6;
			border: 1px solid gray;
			padding: 5px 13px 5px 13px;
		}
		</style>
			';
		$post['html'] = $css . $post['html'];
		}
	}

	$post['canpost'] = $postinfo['content']['createpermissions']['vbforum_text'] ? 1 : 0;
	return $post;
}

function fr_last_unread_post_on_this_page($topic_search, $userinfo)
{
	foreach ($topic_search['results'] AS $node) {
		if ($node['nodeid'] == $node['starter']) {
			continue;
		}
		if ($node['lastupdate'] > $userinfo['lastactivity']) {
			return true;
		}
	}
	return false;
}
