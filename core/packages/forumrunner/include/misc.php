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

//	FIXME: Does nothing if $options['threadmarking'] == 0
function do_mark_read()
{
	$vbulletin = vB::get_registry();
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'forumid' => vB_Cleaner::TYPE_INT
	));

	$forumid = $cleaned['forumid'];
	if($forumid == 0) {
		return json_error(ERR_INVALID_FORUM);
	}

	$foruminfo = vB_Api::instance('node')->getFullContentforNodes(array($forumid));

	if(empty($foruminfo) OR $foruminfo[0]['contenttypeclass'] != 'Channel') {
		return json_error(ERR_INVALID_FORUM);
	}

	if (!$userinfo['userid']) {
		return json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
	}

	$forums_marked = vB_Api::instance('node')->markChannelsRead($forumid > 0 ? $forumid : 0);

	$tableinfo = $vbulletin->db->query_first("
		SHOW TABLES LIKE '" . TABLE_PREFIX . "forumrunner_push_data'
		");

	if ($tableinfo) {
		if ($forumid > 0) {
			$vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX . "forumrunner_push_data AS forumrunner_push_data
				LEFT JOIN " . TABLE_PREFIX . "node AS thread
				ON thread.nodeid = forumrunner_push_data.vb_threadid
				SET forumrunner_push_data.vb_subsent = 0, forumrunner_push_data.vb_threadread = " . vB::getRequest()->getTimeNow() . "
				WHERE forumrunner_push_data.vb_userid = {$userinfo['userid']} AND thread.parentid IN (" . join(',', $forums_marked) . ")
				");
		} else {
			$vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX . "forumrunner_push_data
				SET vb_subsent = 0, vb_threadread = " . vB::getRequest()->getTimeNow() . "
				WHERE vb_userid = {$userinfo['userid']} AND vb_threadid > 0
				");
		}
	}
	return true;
}

function do_get_new_updates()
{
	include_once(MCWD . '/include/login.php');
	do_login();

	$out = array(
		'pm_notices' => get_pm_unread(),
		'sub_notices' => get_sub_thread_updates(),
	);

	vB_User::processLogout();
	return $out;
}

function do_remove_fr_user()
{
	$vbulletin = vB::get_registry();
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'fr_username' => vB_Cleaner::TYPE_STR,
	));

	if (!$cleaned['fr_username'] || !$userinfo['userid']) {
		return json_error(ERR_NO_PERMISSION);
	}

	$tableinfo = $vbulletin->db->query_first("
		SHOW TABLES LIKE '" . TABLE_PREFIX . "forumrunner_push_users'
		");
	if ($tableinfo) {
		$vbulletin->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "forumrunner_push_users
			WHERE fr_username = '" . $vbulletin->db->escape_string($cleaned['fr_username']) . "' AND vb_userid = {$userinfo['userid']}
			");
	}

	return true;
}

function do_version()
{
	global $fr_version, $fr_platform;

	if (file_exists(MCWD . '/sitekey.php')) {
		require_once(MCWD . '/sitekey.php');
	} else if (file_exists(MCWD . '/vb_sitekey.php')) {
		require_once(MCWD . '/vb_sitekey.php');
	}

	$push = Api_InterfaceAbstract::instance()->callApi('cron', 'fetchByVarName', array('forumrunnerpush'));

	$push_enabled = $push['active'] && $push['product'] == 'forumrunner';
	return array(
		'version' => $fr_version,
		'platform' => $fr_platform,
		'push_enabled' => $push_enabled,
		'charset' => get_local_charset(),
		'sitekey_setup' => (!$mykey || $mykey == '') ? false : true,
	);
}

function do_stats()
{
	$user_counts = vB_Api::instance('wol')->fetchCounts();

	if (empty($user_counts) || !empty($user_counts['errors'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	$top = vB_Api::instance('content_channel')->fetchTopLevelChannelIds();
	$forumid = $top['forum'];

	$contenttypeid = vB_Api::instance('contenttype')->fetchContentTypeIdFromClass('Text');
	$all_texts = vB_Api::instance('node')->listNodeFullContent($forumid, 1, 10000000000, 10000, $contenttypeid, false);

	$total_threads = 0;
	$total_posts = 0;

	foreach ($all_texts as $node) {
		if ($node['starter'] == $node['nodeid']) {
			$total_threads++;
		}
		$total_posts++;
	}

	$total_members = vB_dB_Assertor::instance()->getRow('ForumRunner:countMembers');
	$newuser = vB_dB_Assertor::instance()->getRow('ForumRunner:getNewestUser');
	$total_members = $total_members['count'];
	$newuser = $newuser['username'];

	$out = array(
		'threads' => $total_threads,
		'posts' => $total_posts,
		'members' => $total_members,
		'newuser' => $newuser,
		'record_users' => $user_counts['recordusers'],
		'record_date' => $user_counts['recorddate'],
		'online_members' => $user_counts['members'],
		'online_guests' => $user_counts['guests'],
	);

	return $out;
}

function do_report()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if(!$userinfo['userid']) {
		return json_error(ERR_INVALID_LOGGEDIN);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST,array(
		'postid' => vB_Cleaner::TYPE_INT,
		'reason' => vB_Cleaner::TYPE_STR
	));

	$reportData = array(
		'rawtext' => $cleaned['reason'],
		'reportnodeid' => $cleaned['postid'],
		'userid' => $userinfo['userid'],
		'created' => time(),
	);

	$report = vB_Api::instance('content_report')->add($reportData);

	if ($report === null || !empty($report['errors'])) {
		return false;
	}

	return true;
}

function do_set_push_token()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if($userinfo['userid'] < 1) {
		return json_error(ERR_INVALID_LOGGEDIN);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'token' => vB_Cleaner::TYPE_STR
	));

	fr_update_push_user('', 1, $cleaned['token']);

	return true;
}

function do_like()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if($userinfo['userid'] < 1) {
		return json_error(ERR_INVALID_LOGGEDIN);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'postid' => vB_Cleaner::TYPE_UINT,
	));

	if (empty($cleaned['postid'])) {
		return false;
	}

	$result = vB_Api::instance('reputation')->vote($cleaned['postid']);

	if (!empty($result['errors']) AND $result['errors'][0][0] != 'reputationownpost') {
		return false;
	}

	return true;
}
