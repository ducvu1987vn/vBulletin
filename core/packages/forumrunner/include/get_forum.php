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

function do_get_forum()
{
	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'index' => vB_Cleaner::TYPE_BOOL,
		'forumid' => vB_Cleaner::TYPE_INT,
		'previewtype' => vB_Cleaner::TYPE_INT,
		'page' => vB_Cleaner::TYPE_UINT,
		'perpage' => vB_Cleaner::TYPE_UINT
	));

	$previewtype = $cleaned['previewtype'];
	if (!isset($cleaned['previewtype']) || $previewtype < 1) {
		$previewtype = 1;
	}

	$forumbits = array();
	$thread_data = array();
	$thread_data_sticky = array();
	$total_threads = 0;
	$permissions = array();

	if (empty($cleaned['forumid']) || $cleaned['index']) {
		$top = vB_Api::instance('content_channel')->fetchTopLevelChannelIds();
		$forumid = $top['forum'];
		$permissions = fr_get_forum_permissions($forumid);
	} else {
		// Otherwise, use passed in forum ID
		$forumid = $cleaned['forumid'];
		$threads = fr_get_threads_for_forum($forumid, $cleaned['page'], $cleaned['perpage'], $previewtype);
		$thread_data = $threads['threads'];
		$thread_data_sticky = $threads['stickies'];
		$total_threads = $threads['total_threads'];
		$permissions = fr_get_forum_permissions($forumid);
	}

	$contenttypeid = vB_Api::instance('contenttype')->fetchContentTypeIdFromClass('Channel');
	$foruminfo = vB_Api::instance('node')->listNodeFullContent($forumid, 1, 100, 1, $contenttypeid, false);

	foreach ($foruminfo as $forumid => $data) {
		$forumbits[] = fr_get_and_parse_forum($forumid, $data);
	}

	$out = array();
	if (is_array($thread_data) && count($thread_data) > 0) {
		$out['threads'] = $thread_data;
		$out['total_threads'] = $total_threads;
	} else {
		$out['threads'] = array();
		$out['total_threads'] = 0;
	}
	if (is_array($thread_data_sticky) && count($thread_data_sticky) > 0) {
		$out['threads_sticky'] = $thread_data_sticky;
		$out['total_sticky_threads'] = count($thread_data_sticky);
	} else {
		$out['threads_sticky'] = array();
		$out['total_sticky_threads'] = 0;
	}

	if ($forumbits) {
		$out['forums'] = $forumbits;
	} else {
		$out['forums'] = array();
	}

	if(!empty($permissions))
	{
		$out['canpost'] = $permissions['canpost'];
		$out['canattach'] = $permissions['canattach'];
	}

	return $out;
}

function do_get_forum_data()
{
	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'forumids' => vB_Cleaner::TYPE_STR,
	));

	if (!isset($cleaned['forumids']) || strlen($cleaned['forumids']) == 0) {
		return array('forums' => array());
	}

	$forumids = explode(',', $cleaned['forumids']);

	$forum_data = array();

	foreach ($forumids AS $forumid) {
		$forum = fr_get_and_parse_forum($forumid);
		if($forum != null) {
			$forum_data[] = $forum;
		}
	}

	if(!empty($forum_data)) {
		return array('forums' => $forum_data);
	} else {
		return null;
	}
}

function fr_get_forum_permissions($forumid, $foruminfo = false)
{
	if(!$foruminfo) {
		$foruminfo = vB_Api::instance('node')->getFullContentforNodes(array($forumid));
		if (empty($foruminfo)) {
			return null;
		}
		$foruminfo = $foruminfo[0];
	}
	if(!$foruminfo) {
		return null;
	}

	$permissions = array();

	$permissions['canpost'] = $foruminfo['content']['createpermissions']['vbforum_text'];
	$permissions['canattach'] = $foruminfo['content']['createpermissions']['vbforum_attach'];

	if ($permissions['canpost'] === null) {
		$permissions['canpost'] = 0;
	}
	if ($permissions['canattach'] === null) {
		$permissions['canattach'] = 0;
	}

	return $permissions;
}

function fr_get_and_parse_forum($forumid, $foruminfo = false)
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();
	$options = vB::get_datastore()->get_value('options');

	if(!$foruminfo) {
		$foruminfo = vB_Api::instance('node')->getFullContentforNodes(array($forumid));
		if (empty($foruminfo)) {
			return null;
		}
		$foruminfo = $foruminfo[0];
	}
	if(!$foruminfo) {
		return null;
	}

	$type = 'old';

	if ($options['threadmarking'] AND $userinfo['userid']) {
		$userlastvisit = (!empty($foruminfo['readtime']) ? $foruminfo['readtime'] : (vB::getRequest()->getTimeNow() - ($options['markinglimit'] * 86400)));
	} else {
		$lastvisit = vB5_Cookie::get('lastvisit', vB5_Cookie::TYPE_UINT);
		$forumview = fr_fetch_bbarray_cookie('channel_view', $foruminfo['nodeid']);

		//use which one produces the highest value, most likely cookie
		$userlastvisit = ($forumview > $lastvisit ? $forumview : $lastvisit);
	}

	if ($foruminfo['lastcontent'] AND $userlastvisit < $foruminfo['lastcontent']) {
		$type = 'new';
	} else {
		$type = 'old';
	}

	$out = array(
		'id' => $foruminfo['nodeid'],
		'new' => $type == 'new' ? true : false,
		'name' => strip_tags($foruminfo['title']),
		'password' => false, // No forum passwords in vB5
	);

	$icon = fr_get_forum_icon($foruminfo['nodeid'], $foruminfo == 'new');
	if ($icon) {
		$out['icon'] = $icon;
	}

	if ($foruminfo['description'] != '') {
		$desc = strip_tags($foruminfo['description']);
		if (strlen($desc) > 0) {
			$out['desc'] = $desc;
		}
	}
	return $out;
}

function fr_get_threads_for_forum($forumid, $pagenumber, $perpage, $previewtype = 1)
{
	$topics = array();
	$topics_sticky = array();
	$total_threads = 0;

	$search = array("channel" => $forumid);
	$search['view'] = vB_Api_Search::FILTER_VIEW_TOPIC;
	$search['depth'] = 1;
	$search['include_sticky'] = true;
	$search['sort']['lastcontent'] = 'desc';
	$topic_search = vB_Api::instanceInternal('search')->getInitialResults($search, $perpage, $pagenumber, true);

	if (!isset($topic_search['errors']) AND !empty($topic_search['results']))
	{
		foreach ($topic_search['results'] AS $key => $node)
		{
			if ($node['content']['contenttypeclass'] == 'Text' && $node['content']['starter'] == $node['content']['nodeid'])
			{
				$topic = fr_parse_thread($node, $previewtype);
				if($node['sticky'])
				{
					$topics_sticky[] = $topic;
				}
				else
				{
					$topics[] = $topic;
				}
			}
		}
		$total_threads = $topic_search['totalRecords'] - count($topics_sticky);
	}
	return array('threads' => $topics, 'stickies' => $topics_sticky, 'total_threads' => $total_threads);
}
