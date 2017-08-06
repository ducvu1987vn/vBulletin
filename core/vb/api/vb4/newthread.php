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
 * vB_Api_Vb4_newthread
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Vb4_newthread extends vB_Api
{
	public function postthread($forumid, $message, $subject, $posthash = null, $prefixid = null, $taglist = null)
	{
		$cleaner = vB::getCleaner();
		$forumid = $cleaner->clean($forumid, vB_Cleaner::TYPE_UINT);
		$posthash = $cleaner->clean($posthash, vB_Cleaner::TYPE_STR);
		$message = $cleaner->clean($message, vB_Cleaner::TYPE_STR);
		$subject = $cleaner->clean($subject, vB_Cleaner::TYPE_STR);
		$prefixid = $cleaner->clean($prefixid, vB_Cleaner::TYPE_STR);
		$taglist = $cleaner->clean($taglist, vB_Cleaner::TYPE_STR);

		if (empty($forumid) || empty($subject) || empty($message))
		{
			return array("response" => array("errormessage" => array("invalidid")));
		}
		$data = array(
			'parentid' => $forumid,
			'title' => $subject,
			'rawtext' => $message,
			'created' => vB::getRequest()->getTimeNow(),
		);
		if (!empty($prefixid))
		{
			$data['prefixid'] = $prefixid;
		}
		if (!empty($taglist))
		{
			$data['tags'] = $taglist;
		}

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

	public function newthread($forumid)
	{
		$cleaner = vB::getCleaner();
		$forumid = $cleaner->clean($forumid, vB_Cleaner::TYPE_UINT);

		$forum = vB_Api::instance('node')->getFullContentforNodes(array($forumid));
		if(empty($forum))
		{
			return array("response" => array("errormessage" => array("invalidid")));
		}
		$forum = $forum[0];

		$foruminfo = vB_Library::instance('vb4_functions')->parseForumInfo($forum);
		$prefixes = vB_Library::instance('vb4_functions')->getPrefixes($forumid);
		$options = vB::getDatastore()->getValue('options');
		$postattachment = $forum['content']['createpermissions']['vbforum_attach'];
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
				'forumrules' => array(
					'can' => array(
						'postattachment' => $postattachment,
					),
				),
				'prefix_options' => $prefixes,
				'foruminfo' => $foruminfo,
				'poststarttime' => vB::getRequest()->getTimeNow(),
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
