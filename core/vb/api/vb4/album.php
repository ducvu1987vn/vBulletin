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
 * vB_Api_Vb4_album
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Vb4_album extends vB_Api
{
	public function updatealbum($description, $title, $albumtype, $albumid = null)
	{
		$cleaner = vB::getCleaner();
		$description = $cleaner->clean($description, vB_Cleaner::TYPE_STR);
		$title = $cleaner->clean($title, vB_Cleaner::TYPE_STR);
		$albumtype = $cleaner->clean($albumtype, vB_Cleaner::TYPE_STR);
		$albumid = $cleaner->clean($albumid, vB_Cleaner::TYPE_UINT);

		// TODO: Implement when vB5 is more well defined on this feature.

		$result = array();
		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		return array(
			'response' => array(
				'errormessage' => 'album_added_edited',
			),
		);
	}

	public function user($pagenumber = 1, $userid = null)
	{
		$cleaner = vB::getCleaner();
		$pagenumber = $cleaner->clean($pagenumber, vB_Cleaner::TYPE_UINT);
		$userid = $cleaner->clean($userid, vB_Cleaner::TYPE_UINT);

		if ($userid < 1)
		{
			$userinfo = vB_Api::instance('user')->fetchUserinfo();
			$userid = $userinfo['userid'];
		}
		else
		{
			$userinfo = vB_Api::instance('user')->fetchUserinfo($userid);
		}

		$result = vB_Api::instance('profile')->fetchMedia(array('userId' => $userid), $pagenumber);
		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		$albumbits = array();

		// TODO: Implement when vB5 is more well defined on this feature.

		return array(
			'response' => array(
				'userinfo' => vB_Library::instance('vb4_functions')->filterUserInfo($userinfo),
				'albumbits' => $albumbits,
			),
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
