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
 * vB_Api_Vb4_newreply
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Vb4_newreply extends vB_Api
{
	public function postreply($threadid, $message, $posthash = null, $subject = null)
	{
		$cleaner = vB::getCleaner();
		$threadid = $cleaner->clean($threadid, vB_Cleaner::TYPE_UINT);
		$message = $cleaner->clean($message, vB_Cleaner::TYPE_STR);
		$subject = $cleaner->clean($subject, vB_Cleaner::TYPE_STR);
		$posthash = $cleaner->clean($posthash, vB_Cleaner::TYPE_STR);

		if (empty($threadid) || empty($message))
		{
			return array("response" => array("errormessage" => array("invalidid")));
		}

        $hv = vB_Library::instance('vb4_functions')->getHVToken();
		$data = array(
			'parentid' => $threadid,
			'title' => !empty($subject) ? $subject : '(Untitled)',
			'rawtext' => $message,
			'created' => vB::getRequest()->getTimeNow(),
			'hvinput' => $hv,
		);
		$result = vB_Api::instance('content_text')->add($data);
		if (empty($result) || !empty($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		vB_Library::instance('vb4_posthash')->appendAttachments($result, $posthash);
		return array('response' => array(
			'errormessage' => 'redirect_postthanks',
			'show' => array(
				'threadid' => $result,
				'postid' => $result,
			),
		));
	}

	public function newreply($threadid)
	{
		$cleaner = vB::getCleaner();
		$threadid = $cleaner->clean($threadid, vB_Cleaner::TYPE_UINT);

		$thread = vB_Api::instance('node')->getFullContentforNodes(array($threadid));
		if(empty($thread))
		{
			return array("response" => array("errormessage" => array("invalidid")));
		}
		$thread = $thread[0];

		$prefixes = vB_Library::instance('vb4_functions')->getPrefixes($threadid);
		$options = vB::getDatastore()->getValue('options');
		$postattachment = $thread['content']['createpermissions']['vbforum_attach'];
		$postattachment = empty($postattachment) ? 0 : intval($postattachment);

		$out = array(
			'show' => array(
				'tag_option' => 1,
			),
			'vboptions' => array(
				'postminchars' => $options['postminchars'],
				'titlemaxchars' => $options['titlemaxchars'],
			),
			'response' => array(
				'title' => '',
				'forumrules' => array(
					'can' => array(
						'postattachment' => $postattachment,
					),
				),
				'prefix_options' => $prefixes,
				'poststarttime' => 0,
				'posthash' => vB_Library::instance('vb4_posthash')->getNewPosthash(),
			),
		);
		return $out;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
