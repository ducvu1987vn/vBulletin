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

function do_post_message()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if ($userinfo['userid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'forumid' => vB_Cleaner::TYPE_UINT,
		'subject' => vB_Cleaner::TYPE_STR,
		'message' => vB_Cleaner::TYPE_STR,
		'prefixid' => vB_Cleaner::TYPE_UINT,
		'sig' => vB_Cleaner::TYPE_STR,
		'poststarttime' => vB_Cleaner::TYPE_UINT,
	));

	if (   empty($cleaned['forumid'])
		|| empty($cleaned['subject'])
		|| empty($cleaned['message'])
	) {
		return json_error(ERR_NO_PERMISSION);
	}

	if (!empty($cleaned['sig'])) {
		$cleaned['message'] = $cleaned['message'] . "\n\n" . $cleaned['sig'];
	}

	$result = vB_Api::instance('content_text')->add(array(
		'parentid' => $cleaned['forumid'],
		'title' => $cleaned['subject'],
		'rawtext' => $cleaned['message'],
		'created' => vB::getRequest()->getTimeNow(),
		'hvinput' => fr_get_hvtoken(),
	));

	if (empty($result) || !empty($result['errors'])) {
		return json_error(ERR_INVALID_THREAD);
	}

	if (!fr_do_attachment($result, $cleaned['poststarttime'])) {
		return json_error(ERR_CANT_ATTACH);
	}

	$result = vB_Api::instance('content_text')->update($result, array(
		'rawtext' => fr_process_message($cleaned['message']),
	));

	if (empty($result) || !empty($result['errors'])) {
		return json_error(ERR_INVALID_THREAD);
	}

	return true;
}

function do_post_reply()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if ($userinfo['userid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'postid' => vB_Cleaner::TYPE_UINT,
		'threadid' => vB_Cleaner::TYPE_UINT,
		'message' => vB_Cleaner::TYPE_STR,
		'sig' => vB_Cleaner::TYPE_STR,
		'poststarttime' => vB_Cleaner::TYPE_UINT,
	));

	if (empty($cleaned['message']))
	{
		return json_error(ERR_NO_PERMISSION);
	}
	if (empty($cleaned['threadid']))
	{
		if (!empty($cleaned['postid']))
		{
			$post = vB_Api::instance('node')->getNode($cleaned['postid']);
			if (empty($post['errors']))
			{
				$cleaned['threadid'] = $post['starter'];
			}
			else
			{
				return json_error(ERR_NO_PERMISSION);
			}
		}
	}

	if (!empty($cleaned['sig'])) {
		$cleaned['message'] = $cleaned['message'] . "\n\n" . $cleaned['sig'];
	}

	$result = vB_Api::instance('content_text')->add(array(
		'parentid' => $cleaned['threadid'],
		'title' => '(Untitled)',
		'rawtext' => $cleaned['message'],
		'created' => vB::getRequest()->getTimeNow(),
		'hvinput' => fr_get_hvtoken(),
	));

	if (empty($result) || !empty($result['errors'])) {
		return json_error(ERR_INVALID_THREAD);
	}

	if (!fr_do_attachment($result, $cleaned['poststarttime'])) {
		return json_error(ERR_CANT_ATTACH);
	}

	$result = vB_Api::instance('content_text')->update($result, array(
		'rawtext' => fr_process_message($cleaned['message']),
	));

	if (empty($result) || !empty($result['errors'])) {
		return json_error(ERR_INVALID_THREAD);
	}

	return true;
}

function do_post_edit()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if ($userinfo['userid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'postid' => vB_Cleaner::TYPE_UINT,
		'message' => vB_Cleaner::TYPE_STR,
		'poststarttime' => vB_Cleaner::TYPE_UINT,
		'hvinput' => fr_get_hvtoken(),
	));

	if (   empty($cleaned['postid'])
		|| empty($cleaned['message'])
	) {
		return json_error(ERR_NO_PERMISSION);
	}

	fr_do_attachment($cleaned['postid'], $cleaned['poststarttime']);

	$result = vB_Api::instance('content_text')->update($cleaned['postid'], array(
		'rawtext' => fr_process_message($cleaned['message']),
	));

	if (empty($result) || !empty($result['errors'])) {
		return json_error(ERR_INVALID_THREAD);
	}

	return true;
}

function fr_do_attachment($nodeid, $poststarttime)
{
	if (!empty($nodeid) && !empty($poststarttime)) {
		$userinfo = vB_Api::instance('user')->fetchUserInfo();
		$fr_attach = vB_dB_Assertor::instance()->getRows('ForumRunner:getAttachmentMarker', array(
			'userid' => $userinfo['userid'],
			'poststarttime' => $poststarttime,
		));
		foreach ($fr_attach as $attach_row) {
			$attachid = vB_Api::instance('node')->addAttachment($nodeid, array('filedataid' => $attach_row['filedataid']));
			if ($attachid === null || isset($attachid['errors'])) {
				return false;
			} else {
				$result = vB_dB_Assertor::instance()->assertQuery('ForumRunner:updateAttachmentMarker', array(
					'attachmentid' => $attachid, 
					'id' => $attach_row['id'],
				));
				if ($result === null || isset($result['errors'])) {
					return false;
				}
			}
		}
		return true;
	}
	return false;
}

function fr_process_message($message)
{
	$message = preg_replace_callback("/\[ATTACH]([0-9]+)\[\/ATTACH\]/i", 'fr_process_message_callback', $message);
	$message = nl2br($message);
	return $message;
}

function fr_process_message_callback($matches)
{
	if (count($matches) > 1) {
		$fr_attach = vB_dB_Assertor::instance()->getRow('ForumRunner:getAttachmentMarkerById', array('id' => $matches[1]));
		if (!empty($fr_attach)) {
			return '[IMG]' . fr_base_url() . 'filedata/fetch?id=' . $fr_attach['attachmentid'] . '[/IMG]';
		}
	}
	return '';
}
