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

function do_get_subscriptions()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if ($userinfo['userid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'page' => vB_Cleaner::TYPE_UINT,
		'perpage' => vB_Cleaner::TYPE_UINT,
		'previewtype' => vB_Cleaner::TYPE_UINT,
	));

	$cleaned['page'] = $cleaned['page'] ? $cleaned['page'] : 1;
	$cleaned['perpage'] = $cleaned['perpage'] ? $cleaned['perpage'] : 10;
	$cleaned['previewtype'] = $cleaned['previewtype'] ? $cleaned['previewtype'] : 1;

	$subscribed_nodes = vB_Api::instance('follow')->getFollowing(
		$userinfo['userid'],
		vB_Api_Follow::FOLLOWTYPE_CONTENT,
		array(
			vB_Api_Follow::FOLLOWFILTERTYPE_SORT => vB_Api_Follow::FOLLOWFILTER_SORTALL,
			vB_Api_Follow::FOLLOWTYPE => vB_Api_Follow::FOLLOWTYPE_CONTENT,
		),
		null,
		array(
			'perpage' => $cleaned['perpage'],
			'page' => $cleaned['page'],
		)
	);

	if(empty($subscribed_nodes) || !empty($subscribed_nodes['errors'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	$threads = array();
	foreach ($subscribed_nodes['results'] as $result) {
		$node = vB_Api::instance('node')->getFullContentforNodes(array($result['keyval']));
		if (empty($node)) {
			continue;
		}
		$threads[] = fr_parse_thread($node[0], $cleaned['previewtype']);
	}

	return array(
		'total_threads' => $subscribed_nodes['totalcount'],
		'threads' => $threads,
	);
}

function do_unsubscribe_thread()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if ($userinfo['userid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'threadid' => vB_Cleaner::TYPE_UINT,
	));

	if (empty($cleaned['threadid'])) {
		return json_error(ERR_INVALID_SUB);
	}

	$result = vB_Api::instance('follow')->delete($cleaned['threadid'], vB_Api_Follow::FOLLOWTYPE_CONTENT);

	if (!$result) {
		return json_error(ERR_INVALID_SUB);
	}

	return true;
}

function do_subscribe_thread()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if ($userinfo['userid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'threadid' => vB_Cleaner::TYPE_UINT,
	));

	if (empty($cleaned['threadid'])) {
		return json_error(ERR_INVALID_SUB);
	}

	$result = vB_Api::instance('follow')->add($cleaned['threadid'], vB_Api_Follow::FOLLOWTYPE_CONTENT);

	if (empty($result) || !empty($result['errors'])) {
		return json_error(ERR_INVALID_SUB);
	}

	return true;
}
