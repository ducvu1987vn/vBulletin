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

function do_online()
{
	$userinfo = vB_Api::instance('user')->fetchUserInfo();
	$result = vB_Api::instance('wol')->fetchAll();
	$options = vB::get_datastore()->get_value('options');

	if (is_null($result) || isset($result['errors'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	$user_counts = vB_Api::instance('wol')->fetchCounts();

	if (is_null($user_counts) || isset($user_counts['errors'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	$users = array();
	foreach ($result as $user) {
		$user_final = array(
			'username' => $user['username'],
			'userid' => $user['userid'],
			'avatarurl' => $options['bburl'] . '/' . $user['avatarpath'],
		);
		if (!empty($userinfo) && ($user['userid'] === $userinfo['userid'])) {
			$user_final['me'] = true;
		}
		$users[] = $user_final;
	}

	return array(
		'users' => $users,
		'num_guests' => $user_counts['guests'],
	);
}
