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
 * vB_Api_Vb4_newattachment
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Vb4_newattachment extends vB_Api
{
	//
	// $posthash contains the nodeid we are attaching to.
	// In the case of newthread_newthread, it is an unpublished
	// empty node which will be updated in newthread_postthread
	// and published. The semantics for clients do not change.
	//
	public function manageattach($posthash, $attachment)
	{
		$cleaner = vB::getCleaner();
		$posthash = $cleaner->clean($posthash, vB_Cleaner::TYPE_STR);
		$attach = $cleaner->clean($attachment, vB_Cleaner::TYPE_FILE);

		// vB5 doesn't understand multiple file uploads.
		// Manually split them.
		$attachments = array();
		foreach ($attach as $key => $value)
		{
			for($i = 0; $i < count($value); $i++)
			{
				$attachments[$i][$key] = $value[$i];
			}
		}

		unset($attach);
		foreach ($attachments as $attachment)
		{
			$result = vB_Api::instance('content_attach')->upload($attachment);
			if(empty($result) || !empty($result['errors']))
			{
				return vB_Library::instance('vb4_functions')->getErrorResponse($result);
			}
			vB_Library::instance('vb4_posthash')->addFiledataid($posthash, $result['filedataid']);
		}

		return array('response' => array());
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
