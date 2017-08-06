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

function do_get_pm_folders ()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if (!$userinfo['userid']) {
		return json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
	}

	$pmfolders = vB_Api::instanceInternal('content_privatemessage')->listFolders();

	$blacklist = array(
		'Notifications', 
		'Pending Posts', 
		'Trash',
		'Requests',
	);

	foreach ($pmfolders as $key => $folder) {
		if (in_array($folder, $blacklist)) {
			unset($pmfolders[$key]);
		}
	}

	return array(
		'folders' => $pmfolders,
	);
}

function do_get_pms ()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'folderid' => vB_Cleaner::TYPE_INT,
		'page' => vB_Cleaner::TYPE_UINT,
		'perpage' => vB_Cleaner::TYPE_UINT
	));

	if (!$userinfo['userid']) {
		return json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
	}

	$pm_out = array();
	$totalmessages = 0;
	$folderid = $cleaned['folderid'] ? $cleaned['folderid'] : 0;
	$page = $cleaned['page'] ? $cleaned['page'] : 1;
	$perpage = $cleaned['perpage'] ? $cleaned['perpage'] : 10;


	//
	//  vB4 folders are:
	//      0   = Inbox
	//      -1  = Sent
	//      N   = Custom
	//

	$folders = vB_Api::instanceInternal('content_privatemessage')->listFolders();

	if($folderid == -1)
	{
		$folderid = array_search("Sent Items", $folders);
	}
	else if($folderid == 0)
	{
		$folderid = array_search("Inbox", $folders);
	}
	else
	{
		if(!array_key_exists($folderid, $folders))
		{
			return json_error("Invalid PM Folder");
		}
		if($folders[$folderid] == "Trash" ||
			$folders[$folderid] == "Requests" ||
			$folders[$folderid] == "Pending Posts" ||
			$folders[$folderid] == "Notifications")
		{
			return json_error("Invalid PM Folder");
		}
	}

	$order = "desc";

	$messages = vB_Api::instanceInternal('content_privatemessage')->listMessages(array(
		"folderid" => $folderid,
		"page" => $page,
		"perpage" => $perpage,
		"sortDir" => $order
	));

	if($messages == false)
	{
		json_error("Invalid PM Folder");
	}

	$totalmessages = vB_Api::instance('content_privatemessage')->getFolderMsgCount($folderid);
	$totalmessages = $totalmessages['count'];

	foreach ($messages as $message) {
		$pm_out[] = fr_parse_pm($message);
	}

	return array(
		'pms' => $pm_out,
		'total_pms' => $totalmessages,
	);
}

function do_get_pm ()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'pmid' => vB_Cleaner::TYPE_INT,
	));

	if (!$userinfo['userid']) {
		return json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
	}

	$pm = vB_Api::instance('content_privatemessage')->getMessage($cleaned['pmid']);

	if (empty($pm)) {
		return json_error(ERR_INVALID_PM);
	}

	$pm = $pm['message'];

	$out = fr_parse_pm($pm, true);
	return fr_parse_pm_extra($pm, $out);
}

function do_send_pm ()
{
	// get user info
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	// check if the user is loggued
	if (!$userinfo['userid']) {
		return json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
	}

	// cleaning data
	$cleaned = vB::getCleaner()->cleanArray($_REQUEST,array(
		'recipients' => vB_Cleaner::TYPE_STR,
		'title' => vB_Cleaner::TYPE_STR,
		'message' => vB_Cleaner::TYPE_STR
	));

	// replace semicolon with comma
	$cleaned['recipients'] = str_replace(';', ',', $cleaned['recipients']);

	// removes first and last comma if it has
	if (preg_match('/^,/', $cleaned['recipients'])) {
		$cleaned['recipients'] = substr($cleaned['recipients'], 1);
	}
	if (preg_match('/,$/', $cleaned['recipients'])) {
		$cleaned['recipients'] = substr($cleaned['recipients'], 0, -1);
	}

	// parsing data
	$data = array(
		'msgRecipients'=>$cleaned['recipients'],
		'title'=>$cleaned['title'],
		'rawtext'=>$cleaned['message']
	);

	$pm = vB_Api::instance('content_privatemessage')->add($data);

	if (isset($pm['errors'])) {
		return json_error(ERR_INVALID_PM);
	}

	return true;
}

function do_delete_pm()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if (!$userinfo['userid']) {
		return json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'pm' => vB_Cleaner::TYPE_INT,
	));

	if (empty($cleaned['pm'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	vB_Api::instance('content_privatemessage')->deleteMessage($cleaned['pm']);

	return true;
}

function do_add_pm(){

}

function fr_parse_pm_extra($message, $partial)
{
	$partial['pm_unread'] = $partial['new_pm'];
	unset($partial['new_pm']);
	$partial['userid'] = $message['userid'];
	$partial['quotable'] = $message['rawtext'];
	$userinfo = vB_Api::instance('user')->fetchUserInfo($message['userid']);
	$partial['online'] = fr_get_user_online($userinfo['lastactivity']);
	$partial['username'] = $userinfo['username'];

	// XXX: Pass fr_images here if they are ever implemented for PM's

	return $partial;
}

function fr_parse_pm($message, $bbcode = false)
{
	$participants = vB_Api::instance('content_privatemessage')->fetchParticipants($message['nodeid']);
	$users = array();
	if(!empty($participants))
	{
		foreach($participants as $recipient)
		{
			$users[] = $recipient['username'];
		}
	}
	$message_text = $message['rawtext'];
	if($bbcode)
	{
		$message_text = fr_parse_pm_bbcode($message_text);
	}
	else
	{
		$message_text = strip_bbcode($message_text);
	}

	$out = array(
		'id' => $message['nodeid'],
		'username' => $message['username'],
		'to_usernames' => implode('; ', $users),
		'message' => $message_text,
		'pm_timestamp' => fr_date($message['publishdate']),
		'title' => $message['title'] ? $message['title'] : $message['previewtext'],
		'new_pm' => $message['msgread'] ? false : true,
	);

	if($avatarurl = fr_find_avatarurl($message))
	{
		$out['avatarurl'] = $avatarurl;
	}
	return $out;
}

function fr_parse_pm_bbcode($bbcode, $smilies = true)
{
	require_once(DIR . '/includes/class_core.php');
	require_once(DIR . '/includes/class_bbcode.php');

	$bbcode_parser = new vB_BbCodeParser(vB::get_registry(), fetch_tag_list());
	return $bbcode_parser->parse($bbcode, 'privatemessage', $smilies);
}
