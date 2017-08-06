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

function do_get_conversations ()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'page' => vB_Cleaner::TYPE_UINT,
		'perpage' => vB_Cleaner::TYPE_UINT,
	));

	if (!$userinfo['userid']) {
		return json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
	}

	$pm_out = array();
	$totalmessages = 0;
	$page = $cleaned['page'] ? $cleaned['page'] : 1;
	$perpage = $cleaned['perpage'] ? $cleaned['perpage'] : 10;


	$folders = vB_Api::instanceInternal('content_privatemessage')->listFolders();
	$folderid = array_search("Inbox", $folders);

	$order = "desc";

	$messages = vB_Api::instanceInternal('content_privatemessage')->listMessages(array(
		"folderid" => $folderid,
		"page" => $page,
		"perpage" => $perpage,
		"sortDir" => $order
	));

	if ($messages == false) {
		json_error("Invalid PM Folder");
	}

	$totalmessages = vB_Api::instance('content_privatemessage')->getFolderMsgCount($folderid);
	$totalmessages = $totalmessages['count'];

	foreach ($messages as $message) {
		$pm_out[] = fr_parse_conversation($message);
	}

	return array(
		'conversations' => $pm_out,
		'total_conversations' => $totalmessages,
		'canstart' => true,
	);
}

function do_get_conversation ()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'conversationid' => vB_Cleaner::TYPE_INT,
		'signature' => vB_Cleaner::TYPE_BOOL,
		'page' => vB_Cleaner::TYPE_UINT,
	));

	if(empty($cleaned['conversationid'])) {
		return json_error(ERR_INVALID_PM);
	}

	if (!$userinfo['userid']) {
		return json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
	}

	$convo = vB_Api::instance('content_privatemessage')->getMessage($cleaned['conversationid']);

	if (empty($convo)) {
		return json_error(ERR_INVALID_PM);
	}

	$do_first_unread = false;
	if ($cleaned['page'] == FR_LAST_POST) {
		$do_first_unread = true;
		foreach ($convo['messages'] as $message) {
			$first_unread_post = $message['nodeid'];
			if (!$message['msgread']) {
				break; 
			}
		}
	}

	$participants = vB_Api::instance('content_privatemessage')->fetchParticipants($cleaned['conversationid']);

	$replies = array();
	foreach ($convo['messages'] as $message) {
		$replies[] = fr_parse_conversation_reply($message, $cleaned['conversationid']);
	}


	$recipients = array();
	foreach ($participants as $participant) {
		$recipients[] = $participant['username'];
	}

	$out = array(
		'posts' => $replies,
		'total_posts' => count($replies),
		'page' => 1,
		'canpost' => true,
		'recipients' => implode('; ', $recipients),
		'title' => $message['title'] ? $message['title'] : remove_bbcode($message['pagetext']),
	);

	if ($do_first_unread) {
		$out['gotolastpost'] = $first_unread_post;
	}
	return $out;
}

function do_leave_conversation()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if (!$userinfo['userid']) {
		return json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST,array(
		'conversationid' => vB_Cleaner::TYPE_UINT,
	));

	if(empty($cleaned['conversationid'])) {
		return json_error(ERR_INVALID_PM);
	}

	$success = vB_Api::instance('content_privatemessage')->toTrashcan($cleaned['conversationid']);

	return true;
}

function do_reply_conversation()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if (!$userinfo['userid']) {
		return json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST,array(
		'conversationid' => vB_Cleaner::TYPE_UINT,
		'message' => vB_Cleaner::TYPE_STR,
	));

	if(empty($cleaned['conversationid'])) {
		return json_error(ERR_INVALID_PM);
	}

	$data = array(
		'title' => '(Untitiled)', // vB5 convention
		'respondto' => $cleaned['conversationid'],
		'rawtext' => $cleaned['message'],
		'msgtype' => 'message',
	);

	$pm = vB_Api::instance('content_privatemessage')->add($data);

	if (isset($pm['errors'])) {
		return json_error(ERR_INVALID_PM);
	}

	return true;
}

function do_start_conversation ()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();

	if (!$userinfo['userid']) {
		return json_error(ERR_INVALID_LOGGEDIN, RV_NOT_LOGGED_IN);
	}

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST,array(
		'recipients' => vB_Cleaner::TYPE_STR,
		'title' => vB_Cleaner::TYPE_STR,
		'message' => vB_Cleaner::TYPE_STR
	));

	$cleaned['recipients'] = str_replace(';', ',', $cleaned['recipients']);

	if (preg_match('/^,/', $cleaned['recipients'])) {
		$cleaned['recipients'] = substr($cleaned['recipients'], 1);
	}
	if (preg_match('/,$/', $cleaned['recipients'])) {
		$cleaned['recipients'] = substr($cleaned['recipients'], 0, -1);
	}

	$data = array(
		'msgRecipients' => $cleaned['recipients'],
		'title' => $cleaned['title'],
		'rawtext' => $cleaned['message']
	);

	$pm = vB_Api::instance('content_privatemessage')->add($data);

	if (isset($pm['errors'])) {
		return json_error(ERR_INVALID_PM);
	}

	return true;
}

function fr_parse_conversation_reply($message, $conversation_id)
{	
	$userinfo = vB_Api::instance('user')->fetchUserinfo($message['userid']);
	list($parsed_text, , ) = parse_post($message['rawtext']);
	$out = array(
		'post_id' => $message['nodeid'],
		'thread_id' => $conversation_id,
		'title' => $message['title'] ? $message['title'] : remove_bbcode($message['pagetext']),
		'userid' => $message['userid'],
		'username' => $message['authorname'],
		'usertitle' => $userinfo['usertitle'],
		'numposts' => $userinfo['posts'],
		'joindate' => fr_date($userinfo['joindate']),
		'online' => fr_get_user_online($userinfo['lastactivity']),
		'text' => $parsed_text,
		'quotable' => $message['rawtext'],
	);

	return $out;
}

function fr_parse_conversation($message, $bbcode = false)
{
	$message_tree = vB_Api::instance('content_privatemessage')->getMessage($message['nodeid']);

	$new_posts = false;
	$message_ids = array();
	foreach ($message_tree['messages'] as $message) {
		if (!$message['msgread']) {
			$message_ids[] = $message['nodeid'];
			$new_posts = true;
		}
	}

	if ($new_posts) {
		vB_Api::instance('content_privatemessage')->setRead($message_ids, 0);
	}

	$message = $message_tree['message'];

	$out = array(
		'conversation_id' => $message['nodeid'],
		'title' => $message['title'] ? $message['title'] : remove_bbcode($message['pagetext']),
		'new_posts' => $new_posts,
		'total_messages' => count($message_tree['messages']),
	);

	if ($message['lastcontentid'] > 0) {
		$lastcontent = $message_tree['messages'][$message['lastcontentid']];
		$out['userid'] = $message['lastauthorid'];
		$out['username'] = $message['lastauthorid'] > 0 ? $message['lastcontentauthor'] : ((string)new vB_Phrase('global', 'guest'));
		$out['preview'] = make_preview($lastcontent['rawtext']);
		$out['lastmessagetime'] = fr_date($message['lastcontent']);
	} else {
		$out['userid'] = $message['userid'];
		$out['username'] = $message['authorname'];
		$out['preview'] = make_preview($message['rawtext']);
		$out['lastmessagetime'] = fr_date($message['publishdate']);
	}

	return $out;
}
