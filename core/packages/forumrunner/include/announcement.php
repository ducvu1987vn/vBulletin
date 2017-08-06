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

function do_get_announcement()
{
	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'forumid' => vB_Cleaner::TYPE_UINT,
	));

	if (!isset($cleaned['forumid']) || $cleaned['forumid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$result = vB_Api::instance('announcement')->fetch($cleaned['forumid']);

	if ($result === null || isset($result['errors'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	$posts = array();
	foreach ($result as $ann) {
		$posts[] =  fr_parse_post($ann);
	}

	return array('posts' => $posts, 'total_posts' => count($posts));
}
