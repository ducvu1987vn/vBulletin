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
 * vB_Api_Vb4_editpost
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Vb4_editpost extends vB_Api
{
	public function updatepost($postid, $message, $title = null, $posthash = null)
	{
		$cleaner = vB::getCleaner();
		$postid = $cleaner->clean($postid, vB_Cleaner::TYPE_UINT);
		$message = $cleaner->clean($message, vB_Cleaner::TYPE_STR);
		$subject = $cleaner->clean($title, vB_Cleaner::TYPE_STR);
		$posthash = $cleaner->clean($posthash, vB_Cleaner::TYPE_STR);

		if (empty($postid) || empty($message))
		{
			return array("response" => array("errormessage" => array("invalidid")));
		}

		$data = array(
			'rawtext' => $message,
		);
		if (!empty($subject))
		{
			$data['title'] = $subject;
		}
		$result = vB_Api::instance('content_text')->update($postid, $data);

		if (empty($result) || !empty($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		vB_Library::instance('vb4_posthash')->appendAttachments($postid, $posthash);
		return array('response' => array(
			'errormessage' => 'redirect_editthanks',
			'show' => array(
				'threadid' => $postid,
				'postid' => $postid,
			),
		));
	}

	public function editpost($postid)
	{
		$cleaner = vB::getCleaner();
		$postid = $cleaner->clean($postid, vB_Cleaner::TYPE_UINT);

		$post = vB_Api::instance('node')->getFullContentforNodes(array($postid));
		if(empty($post))
		{
			return array("response" => array("errormessage" => array("invalidid")));
		}
		$post = $post[0];

		$prefixes = vB_Library::instance('vb4_functions')->getPrefixes($postid);
		$options = vB::getDatastore()->getValue('options');

		$out = array(
			'show' => array(
				'tag_option' => 1,
			),
			'vboptions' => array(
				'postminchars' => $options['postminchars'],
				'titlemaxchars' => $options['titlemaxchars'],
			),
			'response' => array(
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
