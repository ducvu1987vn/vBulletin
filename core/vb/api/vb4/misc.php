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
 * vB_Api_Vb4_misc
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Vb4_misc extends vB_Api
{
	public function buddylist()
	{
		$followers = vB_Api::instance('follow')->getFollowers($userid, array('page' => $pagenumber, 'perpage' => $perpage));
		if ($followers === null || isset($followers['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($followers);
		}

		$friends = array();

		foreach($followers['results'] as $friend) {
			$friends[] = array(
				'buddy' => array(
					'userid' => $friend['userid'],
					'username' => $friend['username'],
				),
			);
		}

		return array('response' => array('offlineusers' => $friends));
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
