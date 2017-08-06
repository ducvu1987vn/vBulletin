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

$vbulletin = vB::get_registry();
if (!is_object($vbulletin->db)) {
	exit;
}

define('MCWD', DIR . '/packages/forumrunner');

require_once(MCWD . '/support/Snoopy.class.php');
if (file_exists(MCWD . '/sitekey.php')) {
	require_once(MCWD . '/sitekey.php');
} else if (file_exists(MCWD . '/vb_sitekey.php')) {
	require_once(MCWD . '/vb_sitekey.php');
}
require_once(MCWD . '/version.php');
require_once(MCWD . '/support/utils.php');

// You must have your valid Forum Runner forum site key.  This can be
// obtained from http://www.forumrunner.com in the Forum Manager.
if (!$mykey || $mykey == '') {
	exit;
}

// Clean up attachment tables

$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "forumrunner_attachment
	WHERE poststarttime < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 14 DAY))
	");



// First of all, expire all users who have not logged in for 2 weeks, so
// we don't keep spamming the server with their entries.
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "forumrunner_push_users
	WHERE last_login < DATE_SUB(NOW(), INTERVAL 14 DAY)
	");

// Get list of users to check for updates to push
$userids = $vbulletin->db->query_read_slave("
	SELECT vb_userid, fr_username, b
	FROM " . TABLE_PREFIX . "forumrunner_push_users
	");

$out_msg = array();

while ($user = $vbulletin->db->fetch_array($userids)) {
	$pms = array();
	$subs = array();

	// Check for new PMs for this user
	$unreadpms = vB_dB_Assertor::instance()->getRows('ForumRunner:getNewPmsForPushUser', array(
		'userid' => $user['vb_userid'], 
	));

	// Have some PMs.  Check em out.
	if (!empty($unreadpms)) {
		$pmids = array();
		foreach ($unreadpms as $pm) {
			$pms[$pm['nodeid']] = $pm;
			$pmids[] = $pm['nodeid'];
		}

		// We have our PM list.  Now lets see which ones we've already sent
		// and eliminate them.
		$sentpms = $vbulletin->db->query_read_slave("
			SELECT vb_pmid
			FROM " . TABLE_PREFIX . "forumrunner_push_data
			WHERE vb_userid = " . $user['vb_userid'] . " AND vb_pmid IN (" . implode(',', $pmids) . ")
			");

		while ($sentpm = $vbulletin->db->fetch_array($sentpms)) {
			unset($pms[$sentpm['vb_pmid']]);
		}

		unset($sentpms);

		// Save that we sent PM notices
		foreach ($pms as $pm) {
			$vbulletin->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "forumrunner_push_data
				(vb_userid, vb_pmid)
				VALUES
				({$user['vb_userid']}, {$pm['nodeid']})
				");
		}
	}

	unset($unreadpms);

	$subs = array();

	$sub_threads = vB_dB_Assertor::instance()->getRows('ForumRunner:getNewSubsForPushUser', array(
		'userid' => $user['vb_userid'], 
	));

	foreach ($sub_threads as $thread) {
		$push_threaddata = $vbulletin->db->query_first_slave("
			SELECT * FROM " . TABLE_PREFIX . "forumrunner_push_data
			WHERE vb_threadid = {$thread['nodeid']} AND vb_userid = {$user['vb_userid']}
			");
		if ($push_threaddata) {
			if ($push_threaddata['vb_threadread'] < $thread['lastupdate']) {
				if ($push_threaddata['vb_subsent']) {
					continue;
				}

				$vbulletin->db->query_write("
					UPDATE " . TABLE_PREFIX . "forumrunner_push_data
					SET vb_threadread = {$thread['lastupdate']}, vb_subsent = 1
					WHERE id = {$push_threaddata['id']}
					");

				$subs[] = array(
					'threadid' => $thread['nodeid'],
					'title' => $thread['title'],
				);

			}
		} else {
			$subs[] = array(
				'threadid' => $thread['nodeid'],
				'title' => $thread['title'],
			);

			$vbulletin->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "forumrunner_push_data
				(vb_userid, vb_threadid, vb_threadread, vb_subsent)
				VALUES ({$user['vb_userid']}, {$thread['nodeid']}, {$thread['lastupdate']}, 1)
				");
		}
		unset($push_threaddata);
	}
	unset($sub_threads);

	$total = count($pms) + count($subs);

	$haspm = (count($pms) > 0);
	$hassub = (count($subs) > 0);
	if (!$haspm && !$hassub) {
		continue;
	}

	$msgargs = array(base64_encode(prepare_utf8_string($vbulletin->options['bbtitle'])));

	$pmpart = 0;
	if ($haspm) {
		if (count($pms) > 1) {
			$msgargs[] = base64_encode(count($pms));
			$pmpart = 2;
		} else {
			$first_pm = array_shift($pms);
			$msgargs[] = base64_encode(prepare_utf8_string($first_pm['fromusername']));
			$pmpart = 1;
		}
	}

	$subpart = 0;
	if ($hassub) {
		if (count($subs) > 1) {
			$msgargs[] = base64_encode(count($subs));
			$subpart = 2;
		} else {
			$first_sub = array_shift($subs);
			$msgargs[] = base64_encode(prepare_utf8_string($first_sub['title']));
			$subpart = 1;
		}
	}

	$data = array(
		'b' => $user['b'],
		'pm' => $haspm,
		'subs' => $hassub,
		'm' => "__FR_PUSH_{$pmpart}PM_{$subpart}SUB",
		'a' => $msgargs,
		't' => $total,
	);

	if ($user['token']) {
		$data['token'] = $user['token'];
	} else if ($user['fr_username']) {
		$data['u'] = $user['fr_username'];
	}

	$out_msg[] = $data;

}

// Send our update to Forum Runner central push server.  Silently fail if
// necessary.
if (count($out_msg) > 0) {
	$snoopy = new snoopy();
	$snoopy->submit('http://push.forumrunner.com/push.php',
		array(
			'k' => $mykey,
			'm' => serialize($out_msg),
			'v' => $fr_version,
			'p' => $fr_platform,
		)
	);
}
