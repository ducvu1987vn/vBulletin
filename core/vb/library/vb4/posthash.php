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
 * vB_Library_VB4_Posthash
 *
 * @package vBApi
 * @access public
 */
class vB_Library_VB4_Posthash extends vB_Library
{
	function getNewPosthash()
	{
		return fetch_random_password(32);
	}

	function getFiledataids($posthash)
	{
		$result = vB_dB_Assertor::instance()->getRows('vBMAPI:getPosthashFiledataids', array(
			'posthash' => $posthash,
		));
		return $result;
	}

	function addFiledataid($posthash, $filedataid)
	{
		$result = vB_dB_Assertor::instance()->assertQuery('vBMAPI:insertPosthashFiledataid', array(
			'posthash' => $posthash,
			'filedataid' => $filedataid,
			'dateline' => vB::getRequest()->getTimeNow(),
		));
		return $result;
	}

	function appendAttachments($nodeid, $posthash)
	{
		if (!empty($posthash) AND !empty($nodeid))
		{
			$filedataids = vB_Library::instance('vb4_posthash')->getFiledataids($posthash);
			foreach ($filedataids as $filedataid)
			{
				$result = vB_Api::instance('node')->addAttachment($nodeid, array('filedataid' => $filedataid['filedataid']));
				if (empty($result) || !empty($result['errors']))
				{
					// Ignore attachment errors
				}
			}
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
