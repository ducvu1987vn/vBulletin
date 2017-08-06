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

function do_upload_attachment()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if ($userinfo['userid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$cleaned = vB::getCleaner()->cleanArray($_FILES, array(
		'attachment' => vB_Cleaner::TYPE_FILE,
	));
	$cleaned2 = vB::getCleaner()->cleanArray($_REQUEST, array(
		'poststarttime' => vB_Cleaner::TYPE_UINT,
	));

	if (!isset($cleaned['attachment']) || !isset($cleaned2['poststarttime'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	$result = vB_Api::instance('content_attach')->upload($cleaned['attachment']);

	if(empty($result) || !empty($result['errors'])) {
		return json_error(ERR_ATTACH_TOO_LARGE);
	}

	$id = vB_dB_Assertor::instance()->assertQuery('ForumRunner:addAttachmentMarker', array(
		'userid' => $userinfo['userid'],
		'poststarttime' => $cleaned2['poststarttime'],
		'filedataid' => $result['filedataid'],
	));

	return array(
		'attachmentid' => $id,
	);
}

function do_delete_attachment()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if ($userinfo['userid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'attachmentid' => vB_Cleaner::TYPE_UINT,
	));

	if (empty($cleaned['attachmentid'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	$fr_attach = vB_dB_Assertor::instance()->assertQuery('ForumRunner:getAttachmentMarkerById', array(
		'id' => $cleaned['attachmentid'],
	));

	if (empty($fr_attach)) {
		return json_error(ERR_NO_PERMISSION);
	}

	$result = vB_Api::instance('content_attach')->deleteAttachment($fr_attach['attachmentid']);

	if(empty($result) || !empty($result['errors'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	vB_dB_Assertor::instance()->assertQuery('ForumRunner:deleteAttachmentMarker', array(
		'id' => $cleaned['attachmentid'],
	));

	return true;
}
