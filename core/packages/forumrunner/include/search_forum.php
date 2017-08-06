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

function do_search_getnew()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if ($userinfo['userid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'do' => vB_Cleaner::TYPE_STR,
		'page' => vB_Cleaner::TYPE_UINT,
		'days' => vB_Cleaner::TYPE_UINT,
		'perpage' => vB_Cleaner::TYPE_UINT,
		'previewtype' => vB_Cleaner::TYPE_UINT,
	));

	$cleaned['page'] = empty($cleaned['page']) ? 1 : $cleaned['page'];
	$cleaned['perpage'] = empty($cleaned['perpage']) ? 10 : $cleaned['perpage'];
	$cleaned['perviewtype'] = empty($cleaned['previewtype']) ? 1 : $cleaned['previewtype'];

	if (empty($cleaned['do'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	$searchJSON = array(
		'type' => 'vBForum_Text',
		'starter_only' => 1,
		'date' => array('from' => 30),
		'sort' => array('relevance' => 'desc'),
		'view' => 'topic',
	);

	if (!empty($cleaned['days'])) {
		$searchJSON['date']['from'] = $cleaned['days'];
	}

	if (!empty($cleaned['getnew'])) {
		$searchJSON['date']['from'] = $userinfo['lastactivity'];
	}

	$resultid = vB_Api::instance('search')->getSearchResult($searchJSON);

	if (empty($resultid) || !empty($resultid['errors'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	return fr_search_results($resultid['resultId'], $cleaned['page'], $cleaned['perpage'], $cleaned['previewtype']);
}

function do_search()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if ($userinfo['userid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'query' => vB_Cleaner::TYPE_STR,
		'page' => vB_Cleaner::TYPE_UINT,
		'perpage' => vB_Cleaner::TYPE_UINT,
		'previewtype' => vB_Cleaner::TYPE_UINT,
		'userid' => vB_Cleaner::TYPE_UINT,
		'showposts' => vB_Cleaner::TYPE_BOOL,
		'searchdate' => vB_Cleaner::TYPE_UINT,
		'searchuser' => vB_Cleaner::TYPE_STR,
		'sortby' => vB_Cleaner::TYPE_STR,
		'starteronly' => vB_Cleaner::TYPE_BOOL,
		'titleonly' => vB_Cleaner::TYPE_BOOL,
	));

	$cleaned['page'] = empty($cleaned['page']) ? 1 : $cleaned['page'];
	$cleaned['perpage'] = empty($cleaned['perpage']) ? 10 : $cleaned['perpage'];
	$cleaned['previewtype'] = empty($cleaned['previewtype']) ? 1 : $cleaned['previewtype'];

	$searchJSON = array(
		'keywords' => $cleaned['query'],
		'type' => 'vBForum_Text',
		'date' => array('from' => 30),
		'sort' => array('relevance' => 'desc'),
	);

	if (!empty($cleaned['searchdate'])) {
		$searchJSON['date']['from'] = $cleaned['searchdate'];
	}
	if (!empty($cleaned['userid'])) {
		$searchJSON['authorid'] = $cleaned['userid'];
	}
	if (isset($cleaned['searchuser'])) {
		$searchJSON['author'] = $cleaned['searchuser'];
		$searchJSON['exactname'] = 1;
	}
	if (!empty($cleaned['sortby'])) {
		$searchJSON['sort'][$cleaned['sortby']] = 'desc';
	}

	$resultid = vB_Api::instance('search')->getSearchResult($searchJSON);

	if (empty($resultid) || !empty($resultid['errors'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	return fr_search_results($resultid['resultId'], $cleaned['page'], $cleaned['perpage'], $cleaned['previewtype']);
}

function do_search_finduser()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if ($userinfo['userid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'userid' => vB_Cleaner::TYPE_STR,
		'showposts' => vB_Cleaner::TYPE_BOOL,
		'page' => vB_Cleaner::TYPE_UINT,
		'perpage' => vB_Cleaner::TYPE_UINT,
		'previewtype' => vB_Cleaner::TYPE_UINT,
	));

	$cleaned['page'] = empty($cleaned['page']) ? 1 : $cleaned['page'];
	$cleaned['perpage'] = empty($cleaned['perpage']) ? 10 : $cleaned['perpage'];
	$cleaned['previewtype'] = empty($cleaned['previewtype']) ? 1 : $cleaned['previewtype'];

	if (empty($cleaned['showposts'])) {
		$cleaned['showposts'] = false;
	}

	if (empty($cleaned['userid'])) {
		$cleaned['userid'] = $userinfo['userid'];
	}

	$searchJSON = array(
		'authorid' => $cleaned['userid'],
		'type' => 'vBForum_Text',
	);

	if (!$cleaned['showposts']) {
		$searchJSON['starter_only'] = 1;
	}

	$resultid = vB_Api::instance('search')->getSearchResult($searchJSON);

	if (empty($resultid) || !empty($resultid['errors'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	return fr_search_results($resultid['resultId'], $cleaned['page'], $cleaned['perpage'], $cleaned['previewtype'], $cleaned['showposts']);
}

function do_search_searchid()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if ($userinfo['userid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'searchid' => vB_Cleaner::TYPE_UINT,
		'page' => vB_Cleaner::TYPE_UINT,
		'perpage' => vB_Cleaner::TYPE_UINT,
		'previewtype' => vB_Cleaner::TYPE_UINT,
	));

	$cleaned['page'] = empty($cleaned['page']) ? 1 : $cleaned['page'];
	$cleaned['perpage'] = empty($cleaned['perpage']) ? 10 : $cleaned['perpage'];
	$cleaned['previewtype'] = empty($cleaned['previewtype']) ? 1 : $cleaned['previewtype'];

	if (empty($cleaned['searchid'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	return fr_search_results($cleaned['searchid'], $cleaned['page'], $cleaned['perpage'], $cleaned['previewtype']);
}

function fr_search_results($searchid, $page, $perpage, $previewtype = 1, $showposts = false)
{
	$result = vB_Api::instance('search')->getMoreNodes($searchid, $perpage, $page);

	if (empty($result) || !empty($result['error'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	$complete_starters = array();
	$threads = array();
	foreach ($result['nodeIds'] as $nodeid) {
		$node = vB_Api::instance('node')->getFullContentforNodes(array($nodeid));
		if (empty($node) || !empty($node['errors'])) {
			continue;
		}
		$node = $node[0];
		if (!$showposts) {
			if ($node['nodeid'] != $node['starter'] && !in_array($node['starter'], $complete_starters)) {
				$node = vB_Api::instance('node')->getFullContentforNodes(array($node['starter']));
				$node = $node[0];
				$complete_starters[] = $node['starter'];
			}
		}

		$threads[] = fr_parse_thread($node, $previewtype);
	}

	$out = array(
		'threads' => $threads,
		'total_threads' => $result['totalRecords'],
		'searchid' => $searchid,
	);

	return $out;
}
