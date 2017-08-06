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

function get_sub_thread_updates()
{
	$userid = vB::getUserContext()->fetchUserId();
	$count = vB_dB_Assertor::instance()->getRow('ForumRunner:subscribedContentUpdateCount', array('userid' => $userid));
	return intval($count['qty']);
}

function get_pm_unread()
{
	$count = vB_Api::instance('content_privatemessage')->fetchSummary();
	return intval($count['folders']['messages']['qty']);
}

function fr_update_push_user($username, $fr_b = false)
{
	$vbulletin = vB::get_registry();
	$userinfo = vB_Api::instance('user')->fetchUserInfo();
	$tableinfo = $vbulletin->db->query_first("
		SHOW TABLES LIKE '" . TABLE_PREFIX . "forumrunner_push_users'
		");

	if ($tableinfo && $userinfo['userid'])
	{
		if ($username)
		{
			// There can be only one FR user associated with this vb_userid and fr_username
			$vb_user = $vbulletin->db->query_read_slave("
				SELECT id FROM " . TABLE_PREFIX . "forumrunner_push_users
				WHERE vb_userid = {$userinfo['userid']}
				");

			if ($vbulletin->db->num_rows($vb_user) > 1)
			{
				// Multiple vb_userids.  Nuke em.
				$vbulletin->db->query_write("
					DELETE FROM " . TABLE_PREFIX . "forumrunner_push_users
					WHERE vb_userid = {$userinfo['userid']}
					");
			}

			$fr_user = $vbulletin->db->query_first("
				SELECT id FROM " . TABLE_PREFIX . "forumrunner_push_users
				WHERE fr_username = '" . $vbulletin->db->escape_string($username) . "'
				");

			if ($fr_user)
			{
				$vbulletin->db->query_write("
					UPDATE " . TABLE_PREFIX . "forumrunner_push_users
					SET vb_userid = {$userinfo['userid']}, last_login = NOW(), b = " . ($fr_b ? 1 : 0) . "
					WHERE id = {$fr_user['id']}
					");
			}
			else
			{
				$vbulletin->db->query_write("
					INSERT INTO " . TABLE_PREFIX . "forumrunner_push_users
					(vb_userid, fr_username, b, last_login)
					VALUES ({$userinfo['userid']}, '" . $vbulletin->db->escape_string($username) . "', " . ($fr_b ? 1 : 0) . ", NOW())
					");
			}
		}
		else
		{
			// Nuke any old entries of them being logged in
			$vbulletin->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "forumrunner_push_users
				WHERE vb_userid = {$userinfo['userid']}
				");
		}
	}
}

function fr_update_subsent($threadid, $threadread)
{
	$vbulletin = vB::get_registry();

	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	$tableinfo = $vbulletin->db->query_first("
		SHOW TABLES LIKE '" . TABLE_PREFIX . "forumrunner_push_data'
		");

	if ($tableinfo)
	{
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "forumrunner_push_data
			SET vb_subsent = 0, vb_threadread = $threadread
			WHERE vb_userid = {$userinfo['userid']} AND vb_threadid = $threadid
			");
	}
}

function fr_show_ad()
{
	$options = vB::get_datastore()->get_value('options');
	if (!$options || $options['forumrunner_googleads_onoff'] == 0) {
		return 0;
	}

	$adgids = explode(',', $options['forumrunner_googleads_usergroups']);
	$exclude_adgids = explode(',', $options['forumrunner_googleads_exclude_usergroups']);

	$userinfo = vB_Api::instance('user')->fetchUserInfo();
	$mgids[] = $userinfo['usergroupid'];
	if ($userinfo['membergroupids'] && $userinfo['membergroupids'] != '') {
		$mgids = array_merge($mgids, explode(',', $userinfo['membergroupids']));
	}

	if (is_array($adgids)) {
		for ($i = 0; $i < count($adgids); $i++) {
			$adgids[$i] = trim($adgids[$i]);
		}
	}

	if (is_array($exclude_adgids)) {
		for ($i = 0; $i < count($exclude_adgids); $i++) {
			$exclude_adgids[$i] = trim($exclude_adgids[$i]);
		}
	}

	// See if they are included
	if (count(array_intersect($adgids, $mgids)) == 0) {
		return 0;
	}

	// See if they are excluded
	if (count(array_intersect($exclude_adgids, $mgids))) {
		return 0;
	}

	$ad = 0;
	if ($options['forumrunner_googleads_threadlist']) {
		$ad += FR_AD_THREADLIST;
	}
	if ($options['forumrunner_googleads_topthread']) {
		$ad += FR_AD_TOPTHREAD;
	}
	if ($options['forumrunner_googleads_bottomthread']) {
		$ad += FR_AD_BOTTOMTHREAD;
	}

	return $ad;
}

function fr_standard_error($error = '')
{
	json_error(prepare_utf8_string(strip_tags($error)));
}

function fr_set_bbarray_cookie($name, $id, $value)
{
	$cookie = vB5_Cookie::get($name, vB5_Cookie::TYPE_STRING);
	$cookie = preg_replace('/\./', '"', $cookie);
	$cookie = preg_replace('/-/', ':', $cookie);
	$cookie = preg_replace('/_/', ';', $cookie);
	$cookie = json_decode($cookie, true);
	$cookie[$id] = $value;
	$cookie = json_encode($cookie, true);
	$cookie = preg_replace('/"/', '.', $cookie);
	$cookie = preg_replace('/:/', '-', $cookie);
	$cookie = preg_replace('/;/', '_', $cookie);
	$cookie = vB5_Cookie::set($name, $cookie, 365);
}

function fr_fetch_bbarray_cookie($name, $id)
{
	$cookie = vB5_Cookie::get($name, vB5_Cookie::TYPE_STRING);
	$cookie = preg_replace('/\./', '"', $cookie);
	$cookie = preg_replace('/-/', ':', $cookie);
	$cookie = preg_replace('/_/', ';', $cookie);
	$cookie = json_decode($cookie, true);
	return @$cookie[$id];
}

function fr_date($timestamp)
{
	$options = vB::get_datastore()->get_value('options');
	return vbdate($options['dateformat'] . ' ' . $options['timeformat'], intval($timestamp));
}

function fr_get_user_online($lastactivity)
{
	$options = vB::get_datastore()->get_value('options');
	return ($lastactivity > (vB::getRequest()->getTimeNow() - (2 * $options['cookietimeout'])));
}

function fr_find_avatarurl($node)
{
	if (!empty($node['content'])) {
		$node = $node['content'];
	}
	if (!empty($node['avatar']['avatarpath'])) {
		return $node['avatar']['avatarpath'];
	}
	if (!empty($node['avatar']['avatarurl']['avatarpath'])) {
		return $node['avatar']['avatarurl']['avatarpath'];
	}
	return null;
}

function fr_parse_thread($node, $previewtype = 1)
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();
	$options = vB::get_datastore()->get_value('options');

	$topic = array(
		'thread_id' => $node['nodeid'],
		'thread_title' => $node['title'],
		'forum_id' => $node['parentid'],
		'forum_title' => $node['content']['channeltitle'],
		'post_username' => $node['userid'] > 0 ? $node['authorname'] : ((string)new vB_Phrase('global', 'guest')),
		'post_userid' => $node['userid'],
		'post_lastposttime' => fr_date($node['lastupdate']),
		'total_posts' => $node['textcount'],
	);
	$did_lastcontent = false;
	if($node['lastcontentid'] > 0 && $node['lastcontentid'] != $node['nodeid'] && $previewtype == 2)
	{
		$lastcontent = vB_Api::instance('node')->getFullContentforNodes(array($node['lastcontentid']));
		$lastcontent = $lastcontent[0];
		if ($lastcontent['parentid'] == $lastcontent['starter']) {
			if (in_array($lastcontent['content']['contenttypeclass'], array('Text', 'Photo', 'Link', 'Video'))) {
				$topic['post_userid'] = $node['lastauthorid'];
				$topic['post_username'] = $node['lastauthorid'] > 0 ? $node['lastcontentauthor'] : ((string)new vB_Phrase('global', 'guest'));
				$topic['thread_preview'] = make_preview($lastcontent['content']['rawtext']);
				if($avatarurl = fr_find_avatarurl($lastcontent))
				{
					$topic['avatarurl'] = $options['bburl'] . '/' . $avatarurl;
				}
				$did_lastcontent = true;
			}
		}
	}
	if (!$did_lastcontent) {
		$topic['thread_preview'] = make_preview($node['content']['rawtext']);
		if($avatarurl = fr_find_avatarurl($node))
		{
			$topic['avatarurl'] = $options['bburl'] . '/' . $avatarurl;
		}
	}

	if ($options['threadmarking'] AND $userinfo['userid']) {
		$userlastvisit = (!empty($node['readtime']) ? $node['readtime'] : (vB::getRequest()->getTimeNow() - ($options['markinglimit'] * 86400)));
	} else {
		$lastvisit = vB5_Cookie::get('lastvisit', vB5_Cookie::TYPE_UINT);
		$forumview = fr_fetch_bbarray_cookie('discussion-view', $node['nodeid']);

		//use which one produces the highest value, most likely cookie
		$userlastvisit = ($forumview > $lastvisit ? $forumview : $lastvisit);
	}

	if (!empty($node['content']['prefix_plain'])) {
		$topic['prefix'] = $node['content']['prefix_plain'];
	}

	$topic['new_posts'] = 0;
	if ($node['lastupdate'] AND $userlastvisit < $node['lastupdate']) {
		$topic['new_posts'] = 1;
	}

	return $topic;
}

function fr_parse_post($node, $signature = true, $html = true)
{
	$userinfo = vB_Api::instance('user')->fetchUserinfo($node['userid']);
	$options = vB::get_datastore()->get_value('options');
	$post = array(
		'post_id' => $node['nodeid'],
		'thread_id' => $node['starter'],
		'post_timestamp' => fr_date($node['created']),
		'forum_id' => $node['content']['channelid'],
		'forum_title' => $node['content']['channeltitle'],
		'title' => $node['title'],
		'username' => $node['userid'] > 0 ? $node['authorname'] : ((string)new vB_Phrase('global', 'guest')),
		'userid' => $node['userid'],
		'joindate' => fr_date($userinfo['joindate']),
		'usertitle' => $userinfo['usertitle'],
		'numposts' => $userinfo['posts'],
		'online' => fr_get_user_online($userinfo['lastactivity']),
		'text' => strip_bbcode($node['content']['rawtext']),
		'quotable' => $node['content']['rawtext'],
		'edittext' => $node['content']['rawtext'],
		'canedit' => $node['content']['permissions']['canedit'],
		'candelete' => $node['content']['permissions']['canmoderate'],
		'canlike' => $node['content']['permissions']['canvote'] > 0,
		'likes' => $node['content']['nodeVoted'] ? true : false,
	);

	if(!empty($node['deleteuserid']))
	{
		$post['deleted'] = true;
		$del_userinfo = vB_Api::instance('user')->fetchUserInfo($node['deleteuserid']);
		$post['del_username'] = $del_userinfo['username'];
		$post['del_reason'] = $node['deletereason'];
	}

	if($avatarurl = fr_find_avatarurl($node))
	{
		$post['avatarurl'] = $options['bburl'] . '/' . $avatarurl;
	}

	$inline_images = array();
	if($signature || $html)
	{
		$bbcode = fr_post_to_bbcode($node, $html);
		if($signature)
		{
			$post['signature'] = $bbcode['signature'];
		}
		if($html)
		{
			$post['text'] = $bbcode['html'];
			$post['html'] = $bbcode['html'];
		}
		$inline_images  = $bbcode['images'];
	}

	if (!empty($node['content']['attach'])) {
		$fr_images = array();
		foreach ($node['content']['attach'] as $attachment) {
			if ($attachment['visible'] > 0) {
				$image = fr_base_url() . 'filedata/fetch?id=' . $attachment['nodeid'];
				$fr_images[] = array(
					'img' => $image,
				);
				if (!in_array($image, $inline_images)) {
					$post['text'] .= "<img src=\"$image\"/>";
					$post['html'] .= "<img src=\"$image\"/>";
				}
			}
		}
		$post['fr_images'] = $fr_images;
	}

	return $post;
}

function fr_post_to_bbcode($node)
{
	require_once(DIR . '/includes/class_core.php');
	require_once(DIR . '/includes/class_bbcode.php');

	$post = array();
	$bbcode_parser = new vB_BbCodeParser(vB::get_registry(), fetch_tag_list());

	$post['signature'] = '';

	if(!empty($node['content']['signature']['raw']))
	{
		$bbcode_parser->set_parse_userinfo($node['content']['userinfo']);
		$post['signature'] = $bbcode_parser->parse(
			$node['content']['signature']['raw'],
			'signature',
			true,
			false,
			'',
			$node['content']['signaturepic'],
			true
		);
		$sig = trim(remove_bbcode(strip_tags($post['signature']), true, true), '<a>');
		$sig = str_replace(array("\t", "\r"), array('', ''), $sig);
		$sig = str_replace("\n\n", "\n", $sig);	
		$post['signature'] = $sig;
	}

	list($text, , $images) = parse_post($node['content']['rawtext']);
	$post['html'] = $text;
	$post['images'] = $images;
	return $post;
}

function fr_base_url()
{
	return preg_replace("#forumrunner\/request.php#i", "", fr_public_url());
}

function fr_public_url()
{
	$s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
	$protocol = substr(strtolower($_SERVER["SERVER_PROTOCOL"]), 0, strpos(strtolower($_SERVER["SERVER_PROTOCOL"]), "/")) . $s;
	$port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]);
	return $protocol . "://" . $_SERVER['SERVER_NAME'] . $port . $_SERVER['SCRIPT_NAME'];
}

function fr_get_hvtoken()
{
	// XXX: This is a hack, we basically turn off hv with this
	require_once(DIR . '/includes/class_humanverify.php');
	$verify =& vB_HumanVerify::fetch_library(vB::get_registry());
	$token = $verify->generate_token();
	$ret = array('input' => $token['answer'], 'hash' => $token['hash']);
	return $ret;
}
