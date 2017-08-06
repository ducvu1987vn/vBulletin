<?php
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

function build_bbcode_video($checktable = false)
{
	if ($checktable)
	{
		try
		{
			vB::getDbAssertor()->assertQuery('bbcode_video', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT
			));
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	require_once(DIR . '/includes/class_xml.php');
	$xmlobj = new vB_XML_Parser(false, DIR . '/includes/xml/bbcode_video_vbulletin.xml');
	$data = $xmlobj->parse();

	if (is_array($data['provider']))
	{
		$insert = array();
		foreach ($data['provider'] AS $provider)
		{
			$items = array();
			$items['tagoption'] = $provider['tagoption'];
			$items['provider'] = $provider['title'];
			$items['url'] = $provider['url'];
			$items['regex_url'] = $provider['regex_url'];
			$items['regex_scrape'] = $provider['regex_scrape'];
			$items['embed'] = $provider['embed'];

			$insert[] = $items;
		}

		if (!empty($insert))
		{
			vB::getDbAssertor()->assertQuery('truncateTable', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'table' => 'bbcode_video'
			));
			vB::getDbAssertor()->assertQuery('bbcode_video', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_MULTIPLEINSERT,
				vB_dB_Query::FIELDS_KEY => array('tagoption', 'provider', 'url', 'regex_url', 'regex_scrape', 'embed'),
				vB_dB_Query::VALUES_KEY => $insert));
		}
	}

	$firsttag = '<vb:if condition="$provider == \'%1$s\'">';
	$secondtag = '<vb:elseif condition="$provider == \'%1$s\'" />';

	$template = array();
	$bbcodes = vB::getDbAssertor()->assertQuery('bbcode_video', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT
		),
		array('field' => array('priority'), 'direction' => array(vB_dB_Query::SORT_ASC))
	);

	foreach ($bbcodes as $bbcode)
	{
		if (empty($template))
		{
			$template[] = sprintf($firsttag, $bbcode['tagoption']);
		}
		else
		{
			$template[] = sprintf($secondtag, $bbcode['tagoption']);
		}
		$template[] = $bbcode['embed'];
	}
	$template[] = "</vb:if>";

	$final = implode("\r\n", $template);

	$exists = vB::getDbAssertor()->getRow('template', array(
			vB_dB_Query::CONDITIONS_KEY =>array(
				array('field' => 'title', 'value' => 'bbcode_video', 'operator' => vB_dB_Query::OPERATOR_EQ),
				array('field' => 'product', 'value' => array('', 'vbulletin'), 'operator' => vB_dB_Query::OPERATOR_EQ),
				array('field' => 'styleid', 'value' => -1, 'operator' => vB_dB_Query::OPERATOR_EQ)
			)
		));

	if ($exists)
	{
		try
		{
			vB_Api::instanceInternal('template')->update($exists['templateid'],'bbcode_video',$final,'vbulletin',false,false,'');
		}
		catch (Exception $e)
		{
			return false;
		}
	}
	else
	{
		vB_Api::instanceInternal('template')->insert(-1, 'bbcode_video', $final, 'vbulletin');
	}
	return true;
}

// ###################### Start updateusertextfields #######################
// takes the field type pmfolders/buddylist/ignorelist/signature in 'field'
// takes the value to insert in $value
function build_usertextfields($field, $value, $userid = 0)
{
	global $vbulletin;

	$userdata = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_STANDARD);

	if ($userid == 0)
	{
		$userdata->set_existing($vbulletin->userinfo);
	}
	else
	{
		$userinfo = array('userid' => $userid);
		$userdata->set_existing($userinfo);
	}

	$userdata->set($field, $value);
	$userdata->save();

	return 0;
}

// ###################### Start build_userlist #######################
// This forces the cache for X list to be rebuilt, only generally needed for modifications.
function build_userlist($userid, $lists = array())
{
	global $vbulletin;
	$userid = intval($userid);
	if ($userid == 0)
	{
		return false;
	}

	if (empty($lists))
	{
		$userlists = vB::getDbAssertor()->assertQuery('vBForum:fetchuserlists', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'userid' => $userid,
		));

		foreach ($userlists as $userlist)
		{
			$lists["$userlist[type]"][] = $userlist['userid'];
		}
	}

	$userdata = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_STANDARD);
	$existing = array('userid' => $userid);
	$userdata->set_existing($existing);

	foreach ($lists AS $listtype => $values)
	{
		$key = $listtype . 'list';
		if (isset($userdata->validfields["$key"]))
		{
			$userdata->set($key, implode(',', $values));
		}
	}

	/* Now to set the ones that weren't set. */
	foreach ($userdata->list_types AS $listtype)
	{
		$key = $listtype . 'list';
		if ($userdata->is_field_set($key))
		{
			$userdata->set($key, '');
		}
	}

	$userdata->save();

	return true;
}


// ###################### Start updateforumcount #######################
// updates forum counters and last post info
function build_forum_counters($forumid, $censor = false)
{
	global $vbulletin;

	$forumid = intval($forumid);
	$foruminfo = fetch_foruminfo($forumid);

	if (!$foruminfo)
	{
		// prevent fatal errors when a forum doesn't exist
		return;
	}

	require_once(DIR . '/includes/functions_bigthree.php');
	$coventry = fetch_coventry('string', true);

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "tachyforumcounter WHERE forumid = $forumid");
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "tachyforumpost WHERE forumid = $forumid");

	if ($coventry)
	{
		// Thread count
		$tachy_db = $vbulletin->db->query_read("
			SELECT thread.postuserid, COUNT(*) AS threadcount
			FROM " . TABLE_PREFIX . "thread AS thread
			WHERE thread.postuserid IN ($coventry)
				AND thread.visible = 1
				AND thread.open <> 10
				AND thread.forumid = $forumid
			GROUP BY thread.postuserid
		");

		$tachystats = array();

		while ($tachycounter = $vbulletin->db->fetch_array($tachy_db))
		{
			$tachystats["$tachycounter[postuserid]"]['threads'] = $tachycounter['threadcount'];
		}

		$tachy_db = $vbulletin->db->query_read("
			SELECT post.userid, COUNT(*) AS replycount
			FROM " . TABLE_PREFIX . "post AS post
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
			WHERE post.userid IN ($coventry)
				AND post.visible = 1
				AND thread.forumid = $forumid
			GROUP BY post.userid
		");

		while ($tachycounter = $vbulletin->db->fetch_array($tachy_db))
		{
			if (!isset($tachystats["$tachycounter[userid]"]))
			{
				$tachystats["$tachycounter[userid]"]['threads'] = 0;
			}

			$tachystats["$tachycounter[userid]"]['replies'] = $tachycounter['replycount'];
		}

		foreach ($tachystats AS $user => $stats)
		{
			$vbulletin->db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "tachyforumcounter
					(userid, forumid, threadcount, replycount)
				VALUES
					(" . intval($user) . ",
					" . intval($forumid) . ",
					" . intval($stats['threads']) . ",
					" . intval($stats['replies']) . ")
			");
		}
	}

	$totals = $vbulletin->db->query_first("
		SELECT
			COUNT(*) AS threads,
			SUM(thread.replycount) AS replies
		FROM " . TABLE_PREFIX . "thread AS thread
		WHERE thread.forumid = $forumid
			AND visible = 1
			AND open <> 10
			" . ($coventry ? " AND thread.postuserid NOT IN ($coventry)" : '')
	);

	$totals['replies'] += $totals['threads'];

	$lastthread = $vbulletin->db->query_first("
		SELECT thread.*
		FROM " . TABLE_PREFIX . "thread AS thread
		WHERE forumid = $forumid
			AND visible = 1
			AND open <> 10
			" . ($coventry ? "AND thread.postuserid NOT IN ($coventry)"  : '') ."
		ORDER BY lastpost DESC
		LIMIT 1
	");

	if ($coventry)
	{
		$tachy_posts = array();
		$tachy_db = $vbulletin->db->query_read("
			SELECT thread.*, tachythreadpost.*
			FROM " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost
			INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (tachythreadpost.threadid = thread.threadid)
			WHERE thread.forumid = $forumid
				AND tachythreadpost.lastpost > " . intval($lastthread['lastpost']) . "
				AND thread.visible = 1
				AND thread.open <> 10
			ORDER BY tachythreadpost.lastpost DESC
		");

		while ($tachy = $vbulletin->db->fetch_array($tachy_db))
		{
			if (!isset($tachy_posts["$tachy[userid]"]))
			{
				$tachy_posts["$tachy[userid]"] = $tachy;
			}
		}

		$tachy_replace = array();

		foreach ($tachy_posts AS $tachy)
		{
			if ($censor)
			{
				$tachy['title'] = fetch_censored_text($tachy['title']);
			}

			$tachy_replace[] = "
				($tachy[userid], $forumid, $tachy[lastpost],
				'" . $vbulletin->db->escape_string($tachy['lastposter']) ."',
				$tachy[lastposterid],
				'" . $vbulletin->db->escape_string($tachy['title']) . "',
				$tachy[threadid],
				$tachy[iconid],
				$tachy[lastpostid],
				'" . $vbulletin->db->escape_string($tachy['prefixid']) . "')
			";
		}

		if ($tachy_replace)
		{
			$vbulletin->db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "tachyforumpost
					(userid, forumid, lastpost, lastposter, lastposterid, lastthread, lastthreadid, lasticonid, lastpostid, lastprefixid)
				VALUES
					" . implode(', ', $tachy_replace)
			);
		}
	}

	//done, update the forum
	$forumdm =& datamanager_init('Forum', $vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
	$forumdm->set_existing($foruminfo);
	$forumdm->set_info('rebuild', 1);
	$forumdm->set('threadcount',  $totals['threads'], true, false);
	$forumdm->set('replycount',   $totals['replies'],true, false);
	$forumdm->set('lastpost',     $lastthread['lastpost'], true, false);
	$forumdm->set('lastposter',   $lastthread['lastposter'], true, false);
	$forumdm->set('lastposterid', $lastthread['lastposterid'], true, false);
	$forumdm->set('lastpostid',   $lastthread['lastpostid'], true, false);

	if ($censor)
	{
		$forumdm->set('lastthread', fetch_censored_text($lastthread['title']), true, false);
	}
	else
	{
		$forumdm->set('lastthread', $lastthread['title'], true, false);
	}

	$forumdm->set('lastthreadid', $lastthread['threadid'], true, false);
	$forumdm->set('lasticonid',   ($lastthread['pollid'] ? -1 : $lastthread['iconid']), true, false);
	$forumdm->set('lastprefixid', $lastthread['prefixid'], true, false);
	$forumdm->set_info('disable_cache_rebuild', true);
	$forumdm->save();
	unset($forumdm);
}

// ###################### Start updatethreadcount #######################
function build_thread_counters($threadid)
{
	global $vbulletin;

	$threadid = intval($threadid);

	require_once(DIR . '/includes/functions_bigthree.php');
	$coventry = fetch_coventry('string', true);

	$firstpost = $vbulletin->db->query_first("
		SELECT post.postid, post.userid, user.username, post.username AS postuser, post.dateline
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = post.userid)
		WHERE post.threadid = $threadid AND
			post.visible = 1
		ORDER BY dateline, postid
		LIMIT 1
	");

	if (!$firstpost)
	{
		// sanity -- this should never happen; one post should always be visible
		return;
	}

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "tachythreadcounter WHERE threadid = $threadid");
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "tachythreadpost WHERE threadid = $threadid");

	$replies = $vbulletin->db->query_first("
		SELECT
			SUM(IF(visible = 1, attach, 0)) AS attachsum,
			SUM(IF(visible = 1, 1, 0)) AS visible,
			SUM(IF(visible = 0, 1, 0)) AS hidden,
			SUM(IF(visible = 2, 1, 0)) AS deleted
		FROM " . TABLE_PREFIX . "post AS post
		WHERE threadid = $threadid
			" . ($coventry ? "AND post.userid NOT IN ($coventry)" : '') . "
	");

	if ($coventry)
	{
		// Build Tachy Counters
		$tachy_db = $vbulletin->db->query_read("
			SELECT post.userid, COUNT(*) AS replycount
			FROM " . TABLE_PREFIX . "post AS post
			WHERE post.userid IN ($coventry)
				AND post.visible = 1
				AND post.threadid = $threadid
				AND post.postid <> $firstpost[postid]
			GROUP BY userid
		");

		$tachystats = array();
		while ($tachycounter = $vbulletin->db->fetch_array($tachy_db))
		{
			$tachystats["$tachycounter[userid]"]['replycount'] = $tachycounter['replycount'];
		}

		if ($tachystats)
		{
			foreach ($tachystats as $user => $stats)
			{
				$vbulletin->db->query_write("
					INSERT INTO ". TABLE_PREFIX . "tachythreadcounter
						(userid, threadid, replycount)
					VALUES
						(" . intval($user) . ",
						" . intval($threadid) . ",
						" . intval($stats['replycount']) . ")
				");
			}
		}
	}

	$lastpost = $vbulletin->db->query_first("
		SELECT
			user.username,
			post.userid,
			post.username AS postuser,
			post.dateline,
			post.postid
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = post.userid)
		WHERE post.threadid = $threadid
			AND post.visible = 1
			" . ($coventry ? "AND post.userid NOT IN ($coventry)" : '') . "
		ORDER BY dateline DESC
		LIMIT 1
	");

	if ($lastpost AND $coventry)
	{
		// if we have a last post (by a non-tachy user) and coventry users,
		// look for a newer last post by a coventry user
		$tachy_db = $vbulletin->db->query_read("
			SELECT
				user.username,
				post.userid,
				post.username AS postuser,
				post.dateline,
				post.postid
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX ."user AS user ON (user.userid = post.userid)
			WHERE post.threadid = $threadid
				AND post.visible = 1
				AND post.userid IN ($coventry)
				AND post.dateline > $lastpost[dateline]
			ORDER BY dateline DESC
		");

		$tachy_posts = array();
		while ($tachy = $vbulletin->db->fetch_array($tachy_db))
		{
			if (!isset($tachy_posts["$tachy[userid]"]))
			{
				$tachy_posts["$tachy[userid]"] = $tachy;
			}
		}

		if ($tachy_posts)
		{
			$tachy_replace = array();
			foreach ($tachy_posts as $tachy)
			{
				$tachy_replace[] = "
					($tachy[userid], $threadid, " . intval($tachy['dateline']) . ",
					'" . $vbulletin->db->escape_string($tachy['postuser']) . "',
					$tachy[userid],
					'" . $vbulletin->db->escape_string($tachy['postid']) . "')
				";
			}

			if ($tachy_replace)
			{
				$vbulletin->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "tachythreadpost
						(userid, threadid, lastpost, lastposter, lastposterid, lastpostid)
					VALUES
						" . implode(', ', $tachy_replace)
				);
			}
		}
	}

	if ($lastpost)
	{
		$lastposter = (empty($lastpost['username']) ? $lastpost['postuser'] : $lastpost['username']);
		$lastposterid = $lastpost['userid'];
		$lastposttime = intval($lastpost['dateline']);
		$lastpostid = intval($lastpost['postid']);
	}
	else
	{
		// this will occur on a thread posted by a tachy user.
		// since only they will see the thread, the lastpost info can say their name
		$lastposter = (empty($firstpost['username']) ? $firstpost['postuser'] : $firstpost['username']);
		$lastposter = $firstpost['userid'];
		$lastposttime = intval($firstpost['dateline']);
		$lastpostid = intval($firstpost['postid']);
	}

	$firstposter = (empty($firstpost['username']) ? $firstpost['postuser'] : $firstpost['username']);
	$firstposterid = intval($firstpost['userid']);
	$firstpostid = intval($firstpost['postid']);
	$threadcreation = $firstpost['dateline'];

	$ratings = $vbulletin->db->query_first("
		SELECT
			COUNT(*) AS votenum,
			SUM(vote) AS votetotal
		FROM " . TABLE_PREFIX . "threadrate
		WHERE threadid = $threadid
	");

	$threadinfo = array('threadid' => $threadid);

	$threadman =& datamanager_init('Thread', $vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT, 'threadpost');
	$threadman->set_existing($threadinfo);
	$threadman->set_info('rebuild', true);
	$threadman->set('firstpostid',  $firstpostid, true, false);
	$threadman->set('postuserid',   $firstposterid, true, false);
	$threadman->set('postusername', $firstposter, true, false);
	$threadman->set('lastpost',     $lastposttime, true, false);
	$threadman->set('replycount',   $replies['visible'] - 1, true, false);
	$threadman->set('hiddencount',  $replies['hidden'], true, false);
	$threadman->set('deletedcount', $replies['deleted'], true, false);
	$threadman->set('attach',       $replies['attachsum'], true, false);
	$threadman->set('dateline',     $threadcreation, true, false);
	$threadman->set('lastposter',   $lastposter, true, false);
	$threadman->set('lastposterid', $lastposterid, true, false);
	$threadman->set('lastpostid',   $lastpostid, true, false);
	$threadman->set('votenum',      $ratings['votenum'], true, false);
	$threadman->set('votetotal',    intval($ratings['votetotal']), true, false);
	$threadman->save();

}


// ###################### Start indexword #######################
function is_index_word($word)
{
	return vB_Api::instanceInternal("Search")->is_index_word($word);
}

// ###################### Start searchtextstrip #######################
function fetch_postindex_text($text)
{
	static $find, $replace;
	global $vbulletin;

	// remove all bbcode tags
	$text = strip_bbcode($text);

	// there are no guarantees that any of the words will be delimeted by spaces so lets change that
	$text = implode(' ', split_string($text));

	// make lower case and pad with spaces
	//$text = strtolower(" $text ");
	$text = " $text ";

	if (!is_array($find))
	{
		$find = array(
			'#[()"\'!\#{};<>]|\\\\|:(?!//)#s',			// allow through +- for boolean operators and strip colons that are not part of URLs
			'#([.,?&/_]+)( |\.|\r|\n|\t)#s',			// \?\&\,
			'#\s+(-+|\++)+([^\s]+)#si',					// remove leading +/- characters
			'#(\s?\w*\*\w*)#s',							// remove words containing asterisks
			'#[ \r\n\t]+#s',							// whitespace to space
		);
		$replace = array(
			'',		// allow through +- for boolean operators and strip colons that are not part of URLs
			' ',	// \?\&\,
			' \2',	// remove leading +/- characters
			'',		// remove words containing asterisks
			' ',	// whitespace to space
		);
	}

	$text = strip_tags($text); // clean out HTML as it's probably not going to be indexed well anyway

	// use regular expressions above
	$text = preg_replace($find, $replace, $text);

	return trim(vbstrtolower($text));
}

// ###################### Start saveuserstats #######################
// Save user count & newest user into template
function build_user_statistics()
{
	$members = vB::getDbAssertor()->getRow('vBForum:fetchUserStats');

	// get newest member
	$newuser = vB::getDbAssertor()->getRow('vBForum:fetchnewuserstats',
		array('userid' => $members['maxid']));

	// make a little array with the data
	$values = array(
		'numbermembers' => $members['users'],
		'activemembers' => isset($members['active']) ? $members['active'] : 0,
		'newusername'   => $newuser['username'],
		'newuserid'     => $newuser['userid']
	);

	// update the special template
	vB::getDatastore()->build('userstats', serialize($values), 1);

	return $values;
}

// ###################### Start getbirthdays #######################
function build_birthdays()
{
	$storebirthdays = array();

	$serveroffset = date('Z', vB::getRequest()->getTimeNow()) / 3600;

	$fromdatestamp = vB::getRequest()->getTimeNow() + (-11 - $serveroffset) * 3600;
	$fromdate = getdate($fromdatestamp);
	$storebirthdays['day1'] = date('Y-m-d', $fromdatestamp);

	$todatestamp = vB::getRequest()->getTimeNow() + (13 - $serveroffset) * 3600;
	$todate = getdate($todatestamp);
	$storebirthdays['day2'] = date('Y-m-d', $todatestamp);

	$todayneggmt = date('m-d', $fromdatestamp);
	$todayposgmt = date('m-d', $todatestamp);

	$bdays = vB::getDbAssertor()->getRows('vBForum:fetchBirthdays', array(
		vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
		'todayneggmt' => $todayneggmt,
		'todayposgmt' => $todayposgmt,
	));


	$year = date('Y');
	$day1 = $day2 = array();

	foreach ($bdays as $birthday)
	{
		$username = $birthday['username'];
		$userid = $birthday['userid'];
		$day = explode('-', $birthday['birthday']);
		if ($year > $day[2] AND $day[2] != '0000' AND $birthday['showbirthday'] == 2)
		{
			$age = $year - $day[2];
		}
		else
		{
			unset($age);
		}
		if ($todayneggmt == $day[0] . '-' . $day[1])
		{
			$day1[] = array(
				'userid'   => $userid,
				'username' => $username,
				'age'      => $age
			);
		}
		else
		{
			$day2[] = array(
				'userid'   => $userid,
				'username' => $username,
				'age'      => $age
			);
		}
	}
	$storebirthdays['users1'] = $day1;
	$storebirthdays['users2'] = $day2;

	vB::getDatastore()->build('birthdaycache', serialize($storebirthdays), 1);

	return $storebirthdays;
}

// ############################### start do update thread subscriptions ###############################
function update_subscriptions($criteria)
{
	global $vbulletin;

	$sql = array();

	if (empty($criteria['threadids']) AND empty($criteria['userids']))
	{
		return;
	}

	// unsubscribe users who can't view the forum the threads are now in
	$users = vB::getDbAssertor()->getRows('vBForum:getSubscriptionUsers', array(
		vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
		'threadids' => $criteria['threadids'],
		'userids' => $criteria['userids'],
	));

	$deleteuser = array();
	$adduser = array();
	foreach ($users as $thisuser)
	{
		cache_permissions($thisuser, true, true);
		if (($thisuser['forumpermissions']["$thisuser[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canview']) AND ($thisuser['forumpermissions']["$thisuser[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) AND ($thisuser['postuserid'] == $thisuser['userid'] OR ($thisuser['forumpermissions']["$thisuser[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canviewothers'])))
		{
			// this user can now view this subscription
			if ($thisuser['canview'] == 0)
			{
				$adduser[] = $thisuser['subscribethreadid'];
			}
		}
		else
		{
			// this user can no longer view this subscription
			if ($thisuser['canview'] == 1)
			{
				$deleteuser[] = $thisuser['subscribethreadid'];
			}
		}
	}

	if (!empty($deleteuser) OR !empty($adduser))
	{
		vB::getDbAssertor()->assertQuery('vBForum:updateSubscriptionUsers', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			vB_dB_Query::CONDITIONS_KEY => array(
				'deleteuser' => $deleteuser,
				'adduser' => $adduser,
			),
		));
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 69791 $
|| ####################################################################
\*======================================================================*/
