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
 * vB_Api_Vb4_profile
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Vb4_profile extends vB_Api
{
	public function doaddlist($userid, $userlist)
	{
		$userinfo = vB_Api::instance('user')->fetchUserinfo();
		if ($userinfo['userid'] <= 0)
		{
			return array('response' => array('errormessage' => 'nopermission_loggedout'));
		}

		$cleaner = vB::getCleaner();
		$userid = $cleaner->clean($userid, vB_Cleaner::TYPE_STR);
		$userlist = $cleaner->clean($userlist, vB_Cleaner::TYPE_STR);

		if ($userid <= 0 || $userlist != 'friend')
		{
			return array('response' => array('errormessage' => 'invalidid'));
		}

		$success = vB_Api::instance('follow')->add($userid);
		if (!empty($success['errors']))
		{
			return array('response' => array('errormessage' => 'invalidid'));
		}
		return array('response' => array('errormessage' => array('redirect_friendadded')));
	}

	public function updateavatar($avatarid = 0, $avatarurl = null, $upload = null)
	{
		$userinfo = vB_Api::instance('user')->fetchUserinfo();
		if ($userinfo['userid'] <= 0)
		{
			return array('response' => array('errormessage' => 'nopermission_loggedout'));
		}

		$cleaner = vB::getCleaner();
		$avatarid = $cleaner->clean($avatarid, vB_Cleaner::TYPE_UINT);
		$avatarurl = $cleaner->clean($avatarurl, vB_Cleaner::TYPE_STR);
		$upload = $cleaner->clean($upload, vB_Cleaner::TYPE_FILE);

		if ($avatarurl == null AND $upload == null)
		{
			return array('response' => array('errormessage' => 'invalidid'));
		}

		if (!empty($avatarurl))
		{
			$success = vB_Api::instance('profile')->uploadUrl($avatarurl);
			if (!empty($success['errors']))
			{
				return array('response' => array('errormessage' => 'invalidid'));
			}
		}
		else if (!empty($upload))
		{
			$success = vB_Api::instance('profile')->upload($upload);
			if (!empty($success['errors']))
			{
				return array('response' => array('errormessage' => 'invalidid'));
			}

		}
		return array('response' => array('errormessage' => array('redirect_updatethanks')));
	}

	public function buddylist()
	{
		$userinfo = vB_Api::instance('user')->fetchUserinfo();
		if ($userinfo['userid'] <= 0)
		{
			return array('response' => array('errormessage' => 'nopermission_loggedout'));
		}

		$followers = vB_Api::instance('follow')->getFollowers($userid, array('page' => 1, 'perpage' => 100));

		if (!empty($followers['errors']))
		{
			return array('response' => array('errormessage' => 'invalidid'));
		}

		$out = array();

		$friends = array();
		foreach ($followers['results'] as $friend)
		{
			$friendinfo = vB_Api::instance('user')->fetchUserinfo($friend['userid']);
			$friends[] = array(
				'friendcheck_checked' => 1,
				'user' => array(
					'userid' => $friend['userid'],
					'username' => $friend['username'],
					'usertitle' => $friendinfo['usertitle'],
					'avatarurl' => vB_Library::instance('vb4_functions')->avatarUrl($friend['userid']),
				),
			);
		}
		$out['response']['HTML']['buddylist'] = $friends;


		$requests = vB_Api::instance('content_privatemessage')->listRequests(array('pageNum' => 1, 'perpage' => 100));
		$req_friends = array();
		foreach ($requests as $friend)
		{
			$friendinfo = vB_Api::instance('user')->fetchUserinfo($friend['userid']);
			$req_friends[] = array(
				'user' => array(
					'friendcheck_checked' => 0,
					'userid' => $friend['userid'],
					'username' => $friendinfo['username'],
					'usertitle' => $friendinfo['usertitle'],
					'avatarurl' => vB_Library::instance('vb4_functions')->avatarUrl($friend['userid']),
				),
			);
		}
		$out['response']['HTML']['buddylist'] = array_merge($friends, $req_friends);
		$out['response']['HTML']['incominglist'] = $req_friends;

		$pagenav = vB_Library::instance('vb4_functions')->pageNav(1, 100, count($out['response']['HTML']['buddylist']));
		$out['response']['HTML']['pagenav'] = $pagenav;

		return $out;
	}

	public function updatelist($listbits, $incomingaction)
	{
		$userinfo = vB_Api::instance('user')->fetchUserinfo();
		if ($userinfo['userid'] <= 0)
		{
			return array('response' => array('errormessage' => 'nopermission_loggedout'));
		}

		$cleaner = vB::getCleaner();
		$listbits = $cleaner->clean($listbits, vB_Cleaner::TYPE_ARRAY);
		$incomingaction = $cleaner->clean($incomingaction, vB_Cleaner::TYPE_STR);

		if (empty($listbits) || empty($listbits['incoming']) || empty($incomingaction))
		{
			return array('response' => array('errormessage' => 'invalidid'));
		}
		$follower = array_pop($listbits['incoming']);
		$requests = vB_Api::instance('content_privatemessage')->listRequests(array('pageNum' => 1, 'perpage' => 100));
		foreach ($requests as $nodeid => $node)
		{
			if ($node['userid'] == $follower)
			{
				break;
			}
		}
		if ($incomingaction == 'accept')
		{
			$success = vB_Api::instance('content_privatemessage')->acceptRequest($nodeid);
		}
		else if ($incomingaction == 'decline')
		{
			$success = vB_Api::instance('content_privatemessage')->denyRequest($nodeid);
		}
		else
		{
			return array('response' => array('errormessage' => 'invalidid'));
		}
		return array('response' => array('errormessage' => array('updatelist_incoming')));
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
