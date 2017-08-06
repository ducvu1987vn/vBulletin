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
 * vB_Api_Vb4_subscription
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Vb4_subscription extends vB_Api
{
	public function viewsubscription()
	{
		$userinfo = vB_Api::instance('user')->fetchUserinfo();
		if ($userinfo['userid'] <= 0)
		{
			return array('response' => array('errormessage' => 'nopermission_loggedout'));
		}

		$cleaner = vB::getCleaner();

		$top = vB_Api::instance('content_channel')->fetchTopLevelChannelIds();
		$forumid = $top['forum'];

		$subscribed = vB_Api::instance('follow')->getFollowingContent(
			$userinfo['userid'],
			vB_Api_Follow::FOLLOWTYPE_CHANNELS,
			array(vB_Api_Follow::FOLLOWFILTERTYPE_SORT => vB_Api_Follow::FOLLOWFILTER_SORTALL),
			null,
			array('parentid' => $forumid)
		);

		if (!empty($subscribed['errors']))
		{
			return array('response' => array('errormessage' => 'invalidid'));
		}

		$nodes = array();
		foreach (array_keys($subscribed['nodes']) as $nodeid)
		{
			$node = vB_Api::instance('node')->getFullContentforNodes(array($nodeid));
			$nodes[] = $node[0];
		}

		$threads = array();
		foreach ($nodes as $node)
		{
			$threads[] = vB_Library::instance('vb4_functions')->parseThread(array_merge($node, $node['content']));
		}

		$out = array(
			'response' => array(
				'HTML' => array(
					'threadbits' => $threads,
				),
			),
		);
		return $out;
	}
	public function removesubscription($threadid = "", $forumid = "")
	{
		$userinfo = vB_Api::instance('user')->fetchUserinfo();
		if ($userinfo['userid'] <= 0)
		{
			return array('response' => array('errormessage' => 'nopermission_loggedout'));
		}

		$cleaner = vB::getCleaner();
		$threadid = $cleaner->clean($threadid, vB_Cleaner::TYPE_UINT);
		$forumid = $cleaner->clean($forumid, vB_Cleaner::TYPE_UINT);

		if ($threadid > 0)
		{
			$nodeid = $threadid;
		}
		else if ($forumid > 0)
		{
			$nodeid = $forumid;
		}
		else
		{
			return array('response' => array('errormessage' => 'invalidid'));
		}

		$success = vB_Api::instance('follow')->delete($nodeid, vB_Api_Follow::FOLLOWTYPE_CONTENT);
		if (!empty($success['errors']))
		{
			return array('response' => array('errormessage' => 'invalidid'));
		}
		return array('response' => array('errormessage' => 'redirect_subsremove_thread'));
		return null;
	}

	public function addsubscription()
	{
		return array('response' => array('HTML' => array('emailselected' => array(0))));
	}

	public function doaddsubscription($threadid = "", $forumid = "")
	{
		$userinfo = vB_Api::instance('user')->fetchUserinfo();
		if ($userinfo['userid'] <= 0)
		{
			return array('response' => array('errormessage' => 'nopermission_loggedout'));
		}

		$cleaner = vB::getCleaner();
		$threadid = $cleaner->clean($threadid, vB_Cleaner::TYPE_UINT);
		$forumid = $cleaner->clean($forumid, vB_Cleaner::TYPE_UINT);

		if ($threadid > 0)
		{
			$nodeid = $threadid;
		}
		else if ($forumid > 0)
		{
			$nodeid = $forumid;
		}
		else
		{
			return array('response' => array('errormessage' => 'invalidid'));
		}

		$success = vB_Api::instance('follow')->add($nodeid, vB_Api_Follow::FOLLOWTYPE_CONTENT, $userinfo['userid']);
		if (!empty($success['errors']))
		{
			return array('response' => array('errormessage' => 'invalidid'));
		}
		return array('response' => array('errormessage' => 'redirect_subsadd_thread'));
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
