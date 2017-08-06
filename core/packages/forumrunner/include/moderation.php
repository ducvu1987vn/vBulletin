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

function do_delete_post()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if ($userinfo['userid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'postid' => vB_Cleaner::TYPE_INT,
		'reason' => vB_Cleaner::TYPE_STR
	));

	$nodeids = array($cleaned['postid']);

	$post = vB_Api::instance('node')->deleteNodes($nodeids, false, $cleaned['reason']);

	if ($post === null || isset($post['errors'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	return true;
}

function do_moderation()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if(!$userinfo['userid']) {
		return false;
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST,array(
		'do' => vB_Cleaner::TYPE_STR,
		'threadid' => vB_Cleaner::TYPE_INT,
		'postids' => vB_Cleaner::TYPE_STR,
		'deletetype' => vB_Cleaner::TYPE_INT,
		'deletereason' => vB_Cleaner::TYPE_STR,
		'banusers' => vB_Cleaner::TYPE_BOOL,
		'deleteother' => vB_Cleaner::TYPE_BOOL,
		'usergroupid' => vB_Cleaner::TYPE_INT,
		'period' => vB_Cleaner::TYPE_INT,
		'reason' => vB_Cleaner::TYPE_STR,
		'title' => vB_Cleaner::TYPE_STR,
		'destforumid' => vB_Cleaner::TYPE_INT,
		'redirect' => vB_Cleaner::TYPE_BOOL,
	));

	if (empty($cleaned['do'])) {
		return false;
	}

	switch ($cleaned['do']) {
	case 'unlock':
		if (empty($cleaned['threadid'])) {
			return false;
		}
		$unlock = vB_Api::instance('node')->openNode($cleaned['threadid']);
		if ($unlock === null || !empty($unlock['errors'])) {
			return false;
		}
		break;

	case 'lock':
		if (empty($cleaned['threadid'])) {
			return false;
		}
		$lock = vB_Api::instance('node')->closeNode($cleaned['threadid']);
		if($lock === null || !empty($lock['errors'])) {
			return false;
		}
		break;

	case 'stick':
		if (empty($cleaned['threadid'])) {
			return false;
		}
		$stick = vB_Api::instance('node')->setSticky(array($cleaned['threadid']));
		if($stick === null || !empty($stick['errors'])) {
			return false;
		}
		break;

	case 'unstick':
		if(empty($cleaned['threadid'])) {
			return false;
		}
		$unstick = vB_Api::instance('node')->unsetSticky(array($cleaned['threadid']));
		if($unstick === null || !empty($unstick['errors'])) {
			return false;
		}
		break;

	case 'dodeleteposts':
		if (empty($cleaned['postids']) || empty($cleaned['deletetype'])) {
			return false;
		}
		$nodeids = explode(',', $cleaned['postids']);
		($cleaned['deletetype'] == 2) ? $hard = true : $hard = false;
		$delete = vB_Api::instance('node')->deleteNodes($nodeids, $hard, $cleaned['deletereason']);
		if($delete === null || !$delete) {
			return false;
		}
		break;

	case 'undeleteposts':
		if (empty($cleaned['postids'])) {
			return false;
		}
		$nodeids = explode(',', $cleaned['postids']);
		$delete = vB_Api::instance('node')->undeleteNodes($nodeids);
		if ($delete === null || !empty($delete['errors'])) {
			return false;
		}
		break;

	case 'dodeletespam':
		if (empty($cleaned['postids']) && empty($cleaned['threadid'])) {
			return false;
		} else {
			$nodeids = array();
			if (!empty($cleaned['threadid'])) {
				$nodeids[] = $cleaned['threadid'];
			}
			if (!empty($cleaned['postids'])) {
				$nodeids = array_merge($nodeids,  explode(',', $cleaned['postids']));
			}
		}

		$deleteNodes = vB_Api::instance('node')->deleteNodes($nodeids, false, $cleaned['reason']);
		if ($deleteNodes === null || !empty($deleteNodes['errors']) || !$deleteNodes) {
			return false;
		}

		if ($cleaned['banusers']) {
			$banusers = array();
			foreach ($nodeids as $nodeid) {
				$node = vB_Api::instance('node')->getNode($nodeid);
				$banusers[] = $node['userid'];
			}
			($cleaned['usergroupid'] == '') ? $banusergroupid = 8 : $banusergroupid = $cleaned['usergroupid'];
			$user = vB_Api::instance('user')->banUsers($banusers, $banusergroupid, $cleaned['period'], $cleaned['reason']);
			if ($user === null || !empty($user['errors'])) {
				return false;
			}
		}

		if ($cleaned['deleteother']) {
			$nodes = array();
			$userActivity = vB_Api::instance('node')->fetchActivity(array('userid'=>$node['userid']));
			if (!empty($userActivity)) {
				foreach ($userActivity as $item) {
					$nodes[] = $item['nodeid'];
				}

				$deleteAllUserNodes = vB_Api::instance('node')->deleteNodes($nodes, false, $cleaned['reason']);

				if (empty($deleteAllUserNodes)) {
					return false;
				}
			}
		}

		return true;
		break;

	case 'dodeletethread':
		if (empty($cleaned['threadid']) || empty($cleaned['deletetype'])) {
			return false;
		}
		($cleaned['deletetype'] == 2) ? $hard = true : $hard = false;
		$delete = vB_Api::instance('node')->deleteNodes(array($cleaned['threadid']), $hard, $cleaned['deletereason']);
		if(empty($delete)) {
			return false;
		}
		break;

	case 'domovethread':
		if (empty($cleaned['threadid']) || empty($cleaned['destforumid'])) {
			return false;
		}
		$moved = vB_Api::instance('node')->moveNodes(array($cleaned['threadid']), $cleaned['destforumid'], true);
		if($moved === null || isset($moved['errors'])) {
			return false;
		}
		break;

	case 'getforums':
		$top = vB_Api::instance('content_channel')->fetchTopLevelChannelIds();
		$forumid = $top['forum'];
		$contenttypeid = vB_Api::instance('contenttype')->fetchContentTypeIdFromClass('Channel');
		$forums = vB_Api::instance('node')->listNodeFullContent($forumid, 1, 100000, 1000, $contenttypeid, false);

		if (empty($forums)) {
			return false;
		}

		$forums_out = array();
		foreach ($forums as $forum) {
			$forums_out[] = array(
				'id' => $forum['nodeid'],
				'title' => $forum['title']
			);
		}

		return array(
			'forums' => $forums_out,
		);
		break;

	default:
		return false;
		break;
	}

	return true;
}

function do_get_spam_data()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if ($userinfo['userid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'threadid' => vB_Cleaner::TYPE_UINT,
		'postids' => vB_Cleaner::TYPE_STR,
	));

	if (empty($cleaned['threadid']) && empty($cleaned['postids'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	$nodeids = array();

	if (!empty($cleaned['postids'])) {
		$nodeids = array_map('intval', explode(',', $cleaned['postids']));
	}
	if (!empty($cleaned['threadid'])) {
		$nodeids[] = $cleaned['threadid'];
	}

	if (empty($nodeids)) {
		return json_error(ERR_NO_PERMISSION);
	}

	$results = vB_Api::instance('node')->getFullContentforNodes($nodeids);

	if ($results === null || isset($results['errors'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	$punitive = true;

	$usernames = array();
	$userids = array();
	$ips = array();

	foreach ($results as $node) {
		if (!$node['content']['permissions']['canmoderate']) {
			return false;
		}

		$ips[] = $node['ipaddress'];
		$usernames[] = $node['authorname'];
		$userids[] = $node['userid'];
	}

	return array(
		'userids' => $userids,
		'users' => $usernames,
		'ips' => $ips,
		'punitive' => $punitive,
	);
}

function do_get_ban_data()
{
	$result = vB_Api::instance('usergroup')->fetchBannedUsergroups();

	if ($result === null || !empty($result['errors'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	return $result;
}

function do_ban_user()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if ($userinfo['userid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'userid' => vB_Cleaner::TYPE_UINT,
		'usergroupid' => vB_Cleaner::TYPE_UINT,
		'period' => vB_Cleaner::TYPE_STR,
		'reason' => vB_Cleaner::TYPE_STR,
	));

	if (!isset($cleaned['userid']) || !isset($cleaned['period'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	(!isset($cleaned['usergroupid']) || $cleaned['usergroupid'] < 1) ? $banusergroupid = 8 : $banusergroupid = $cleaned['usergroupid'];
	$user = vB_Api::instance('user')->banUsers(array($cleaned['userid']), $banusergroupid, $cleaned['period'], $cleaned['reason']);
	if ($user === null || isset($user['errors'])) {
		return false;
	}

	return true;
}
