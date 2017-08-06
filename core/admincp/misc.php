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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
@set_time_limit(0);
ignore_user_abort(1);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 71084 $');
define('NOZIP', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbphrase, $vbulletin;
$phrasegroups = array('maintenance');
if ($_POST['do'] == 'rebuildstyles')
{
	$phrasegroups[] = 'style';
}
$specialtemplates = array('ranks');


// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/functions_databuild.php');

require_once (DIR ."/vb/vb.php");
vB::init();
// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminmaintain'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'chooser';
}

$vbulletin->input->clean_array_gpc('r', array(
	'perpage' => vB_Cleaner::TYPE_UINT,
	'startat' => vB_Cleaner::TYPE_UINT
));

// ###################### Start clear cache ########################
if ($_REQUEST['do'] == 'clear_cache')
{
	print_cp_header($vbphrase['clear_system_cache']);
	vB_Cache::resetCache();
	vB::getDatastore()->resetCache();
	print_cp_message($vbphrase['cache_cleared']);
}
else
{
	print_cp_header($vbphrase['maintenance']);
}

// ###################### Rebuild all style info #######################
if ($_POST['do'] == 'rebuildstyles')
{
	require_once(DIR . '/includes/adminfunctions_template.php');

	$vbulletin->input->clean_array_gpc('p', array(
		'renumber' => vB_Cleaner::TYPE_BOOL,
		'install'  => vB_Cleaner::TYPE_BOOL
	));

	build_all_styles($vbulletin->GPC['renumber'], $vbulletin->GPC['install'], 'misc.php?' . vB::getCurrentSession()->get('sessionurl') . 'do=chooser#style');

	print_stop_message2('updated_styles_successfully');
}

// ###################### Start emptying the index #######################
if ($_REQUEST['do'] == 'emptyindex')
{
	print_form_header('misc', 'doemptyindex');
	print_table_header($vbphrase['confirm_deletion_gcpglobal']);
	print_description_row($vbphrase['are_you_sure_empty_index']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
}

// ###################### Start emptying the index #######################
if ($_POST['do'] == 'doemptyindex')
{
	vB_Api::instanceInternal('search')->emptyIndex();
	$args = array();
	parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
	$args['do'] = 'chooser';
	print_stop_message2('emptied_search_index_successfully', 'misc', $args);

}

// ###################### Start build search index #######################
if ($_REQUEST['do'] == 'doindextypes')
{
	require_once(DIR . '/includes/functions_misc.php');
	//require_once(DIR . '/vb/search/core.php');

	$vbulletin->input->clean_array_gpc('r', array(
		//'doprocess'    => vB_Cleaner::TYPE_UINT,
		'autoredirect' => vB_Cleaner::TYPE_BOOL,
		'totalitems'   => vB_Cleaner::TYPE_UINT,
		'indextypes'   => vB_Cleaner::TYPE_NOHTML
	));
	//vB_Api::instanceInternal('search')->indexAll();
	$starttime = microtime();
	$end = false;
	//	Init Search & get the enabled types to be re-indexed
	//$types = vB_Search_Core::get_instance();
	//$indexed_types = $types->get_indexed_types();
	$filters = false;
	if (!empty($vbulletin->GPC['indextypes']))
	{
		$filters = array('type' => $vbulletin->GPC['indextypes']);
	}

	$startat = 0;
	$perpage = false;
	if (!empty($vbulletin->GPC['startat']) OR !empty($vbulletin->GPC['perpage']))
	{
		$startat = empty($vbulletin->GPC['startat']) ? 0 : $vbulletin->GPC['startat'];
		if (!empty($vbulletin->GPC['perpage']))
		{
			$options = vB::getDatastore()->get_value('options');
			$perpage = $vbulletin->GPC['perpage'];
			if (!empty($options['maxresults']))
			{
				$perpage = min($vbulletin->GPC['perpage'], $options['maxresults']);
			}
		}
	}
	echo '<p>' . $vbphrase['building_search_index'] . ' ' .
				 (empty($vbulletin->GPC['indextypes']) ? '' : (vB_Types::instance()->getContentTypeClass($vbulletin->GPC['indextypes']) . ' ' )) .
				 $startat  . ' :: ' .
				 ($startat + $perpage) . '</p>';
	vbflush();
	$hasmore = vB_Api::instanceInternal('search')->indexRange($startat, $perpage, $filters);
	$pagetime = vb_number_format(fetch_microtime_difference($starttime), 2);

	echo '</p><p><b>' . construct_phrase($vbphrase['processing_time_x'], $pagetime) . '<br />' . construct_phrase($vbphrase['total_items_processed_x'], $startat + $perpage) . '</b></p>';
	vbflush();

	// There is more to do of that type
	if ($hasmore)
	{
		if ($vbulletin->GPC['autoredirect'] == 1)
		{
			$args = array();
			parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
			$args['do'] = 'doindextypes';
			$args['startat'] = $startat + $perpage;
			$args['pp'] = $perpage;
			$args['autoredirect'] = $vbulletin->GPC['autoredirect'];
			//$args['doprocess'] = $vbulletin->GPC['doprocess'];
			$args['totalitems'] = $vbulletin->GPC['totalitems'];
			$args['indextypes'] = $vbulletin->GPC['indextypes'];
			print_cp_redirect2('misc', $args);
		}

		echo "<p><a href=\"misc.php?" . vB::getCurrentSession()->get('sessionurl') .
			"do=doindextypes&amp;startat=" . ($startat + $perpage) . "&amp;pp=$perpage" .
			"&amp;autoredirect=" . $vbulletin->GPC['autoredirect'] .
			//"&amp;doprocess=" . $vbulletin->GPC['doprocess'] .
			"&amp;totalitems=" . $vbulletin->GPC['totalitems'] .
			"&amp;indextypes=" . $vbulletin->GPC['indextypes'] . "\">" .
			$vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		$args = array();
		parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
		$args['do'] = 'chooser';

		print_stop_message2('rebuilt_search_index_successfully', 'misc', $args);
	}
}

// ###################### Start update post counts ################
if ($_REQUEST['do'] == 'updateposts')
{
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 1000;
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	$topChannels = vB_Api::instanceInternal('content_channel')->fetchTopLevelChannelIds();
	$checkChannels = array(
		$topChannels['forum'],
		$topChannels['blog'],
		$topChannels['groups'],
	);
	$channelContentType = vB_Types::instance()->getContentTypeID('vBForum_Channel');

	echo '<p>' . $vbphrase['updating_post_counts'] . '</p>';

	$gotforums = '';
	foreach ($checkChannels as $checkChannel)
	{
		$forums = $vbulletin->db->query_read("
			SELECT node.nodeid
			FROM " . TABLE_PREFIX . "node AS node
			INNER JOIN " . TABLE_PREFIX . "closure AS cl ON cl.parent = $checkChannel AND cl.child = node.nodeid
			WHERE node.contenttypeid = $channelContentType
			AND node.nodeid <> $checkChannel
		");
		while ($forum = $vbulletin->db->fetch_array($forums))
		{
			$gotforums .= ',' . $forum['nodeid'];
		}
	}

	$users = $vbulletin->db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "user
		WHERE userid >= " . $vbulletin->GPC['startat'] . "
		ORDER BY userid
		LIMIT " . $vbulletin->GPC['perpage']
	);

	$finishat = $vbulletin->GPC['startat'];

	while ($user = $vbulletin->db->fetch_array($users))
	{
		$starterCount = $vbulletin->db->query_first("
			SELECT COUNT(*) AS count
			FROM " . TABLE_PREFIX . "node AS thread
			WHERE thread.userid = " . $user['userid'] . "
			AND thread.parentid IN (0$gotforums)
			AND thread.starter = thread.nodeid
			AND thread.publishdate IS NOT NULL AND thread.approved = 1
			AND thread.contenttypeid <> " . intval($channelContentType) . "
		");

		$replyCount = $vbulletin->db->query_first("
			SELECT COUNT(*) AS count
			FROM " . TABLE_PREFIX . "node AS post
			INNER JOIN " . TABLE_PREFIX . "node AS thread ON (thread.nodeid = post.parentid)
			WHERE post.userid = " . $user['userid'] . "
			AND thread.parentid IN (0$gotforums)
			AND thread.publishdate IS NOT NULL AND thread.approved = 1
			AND post.publishdate IS NOT NULL AND post.approved = 1
			AND post.contenttypeid <> " . intval($channelContentType) . "
		");

		$totalPosts = (int) $starterCount['count'] + $replyCount['count'];

		$userdm = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_CP);
		$userdm->set_existing($user);
		$userdm->set('posts', $totalPosts);
		$userdm->set_ladder_usertitle($totalposts['posts']);
		$userdm->save();
		unset($userdm);

		echo construct_phrase($vbphrase['processing_x'], $user['userid']) . "<br />\n";
		vbflush();

		$finishat = ($user['userid'] > $finishat ? $user['userid'] : $finishat);
	}

	$finishat++;

	if ($checkmore = $vbulletin->db->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE userid >= $finishat LIMIT 1"))
	{
		$args = array();
		parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
		$args['do'] = 'updateposts';
		$args['startat'] = $finishat;
		$args['pp'] = $vbulletin->GPC['perpage'];
		print_cp_redirect2('misc', $args);
		echo "<p><a href=\"misc.php?" . vB::getCurrentSession()->get('sessionurl') . "do=updateposts&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		print_stop_message2('updated_post_counts_successfully', 'misc');
	}
}

// ###################### Start update user #######################
if ($_REQUEST['do'] == 'updateuser')
{
	require_once(DIR . '/includes/functions_infractions.php');

	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 1000;
	}

	echo '<p>' . $vbphrase['updating_user_info'] . '</p>';
	$tmp_usergroup_cache = array();

	$infractiongroups = array();
	$groups = $vbulletin->db->query_read("
		SELECT usergroupid, orusergroupid, pointlevel, override
		FROM " . TABLE_PREFIX . "infractiongroup
		ORDER BY pointlevel
	");
	while ($group = $vbulletin->db->fetch_array($groups))
	{
		$infractiongroups["$group[usergroupid]"]["$group[pointlevel]"][] = array(
			'orusergroupid' => $group['orusergroupid'],
			'override'      => $group['override'],
		);
	}

	$users = $vbulletin->db->query_read("
		SELECT user.*, usertextfield.rank,
		IF(user.displaygroupid=0, user.usergroupid, user.displaygroupid) AS displaygroupid
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield USING (userid)
		WHERE user.userid >= " . $vbulletin->GPC['startat'] . "
		ORDER BY user.userid
		LIMIT " . $vbulletin->GPC['perpage']
	);

	$finishat = $vbulletin->GPC['startat'];

	while ($user = $vbulletin->db->fetch_array($users))
	{
		$userdm = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_CP);
		$userdm->set_existing($user);
		cache_permissions($user, false);

		$userdm->set_usertitle(
			($user['customtitle'] ? $user['usertitle'] : ''),
			false,
			$vbulletin->usergroupcache["$user[displaygroupid]"],
			($user['customtitle'] == 1 OR $user['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusecustomtitle']) ? true : false,
			($user['customtitle'] == 1) ? true : false
		);

		if ($lastpost = $vbulletin->db->query_first("SELECT MAX(publishdate) AS dateline FROM " . TABLE_PREFIX . "node WHERE userid = $user[userid]"))
		{
			$lastpost['dateline'] = intval($lastpost['dateline']);
		}
		else
		{
			$lastpost['dateline'] = 0;
		}

		$infractioninfo = fetch_infraction_groups($infractiongroups, $user['userid'], $user['ipoints'], $user['usergroupid']);
		$userdm->set('infractiongroupids', $infractioninfo['infractiongroupids']);
		$userdm->set('infractiongroupid', $infractioninfo['infractiongroupid']);

		$userdm->set('posts', $user['posts']); // This will activate the rank update
		$userdm->set('lastpost', $lastpost['dateline']);
		$userdm->save();
		unset($userdm);

		echo construct_phrase($vbphrase['processing_x'], $user['userid']) . "<br />\n";
		vbflush();

		$finishat = ($user['userid'] > $finishat ? $user['userid'] : $finishat);
	}

	$finishat++;

	if ($checkmore = $vbulletin->db->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE userid >= $finishat LIMIT 1"))
	{
		$args = array();
		parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
		$args['do'] = 'updateuser';
		$args['startat'] = $finishat;
		$args['pp'] = $vbulletin->GPC['perpage'];
		print_cp_redirect2('misc', $args);
		echo "<p><a href=\"misc.php?" . vB::getCurrentSession()->get('sessionurl') . "do=updateuser&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		print_stop_message2('updated_user_titles_successfully', 'misc');
	}
}

// ###################### Start update usernames #######################
if ($_REQUEST['do'] == 'updateusernames')
{
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 1000;
	}

	echo '<p>' . $vbphrase['updating_usernames'] . '</p>';
	$users = $vbulletin->db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "user
		WHERE userid >= " . $vbulletin->GPC['startat'] . "
		ORDER BY userid
		LIMIT " . $vbulletin->GPC['perpage']
	);

	$finishat = $vbulletin->GPC['startat'];
	while ($user = $vbulletin->db->fetch_array($users))
	{
		$userman = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
		$userman->set_existing($user);
		$userman->update_username($user['userid'], $user['username']);
		unset($userman);

		echo construct_phrase($vbphrase['processing_x'], $user['userid']) . "<br />\n";
		vbflush();

		$finishat = ($user['userid'] > $finishat ? $user['userid'] : $finishat);
	}

	$finishat++; // move past the last processed user

	if ($checkmore = $vbulletin->db->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE userid >= $finishat LIMIT 1"))
	{
		$args = array();
		parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
		$args['do'] = 'updateusernames';
		$args['startat'] = $finishat;
		$args['pp'] = $vbulletin->GPC['perpage'];
		print_cp_redirect2('misc', $args);
		echo "<p><a href=\"misc.php?" . vB::getCurrentSession()->get('sessionurl') . "do=updateusernames&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		print_stop_message2('updated_usernames_successfully', 'misc');
	}
}


// ###################### Start update forum #######################
if ($_REQUEST['do'] == 'updateforum')
{
	if (empty($vbulletin->GPC['startat']))
	{
		$vbulletin->GPC['startat'] = 0;
	}
	$processed = 0;
	$vbulletin->input->clean_gpc('r', 'processed', vB_Cleaner::TYPE_UINT);

	if ($vbulletin->GPC_exists['processed'])
	{
		$processed = $vbulletin->GPC['processed'];
	}

	$channelTypeid = vB_Types::instance()->getContentTypeID('vBForum_Channel');
	$assertor = vB::getDbAssertor();
	$maxChannel = $assertor->getRow('vBAdmincp:getMaxChannel', array());
	$maxChannel = $maxChannel['maxid'];
	echo '<p>' . $vbphrase['updating_forums'] . '</p>';
	echo '<p>' . $vbphrase['forum_update_runs_multiple'] . '</p>';

	if ($vbulletin->GPC['startat'] > $maxChannel)
	{
		if ($processed == 0)
		{
			print_stop_message2('updated_forum_successfully', 'misc');
		}
		else
		{
			$args = array('do' => 'updateforum', 'startat' => 0, 'processed' => 0, 'perpage' => $vbulletin->GPC['perpage'] );
			print_cp_redirect2('misc', $args);
			echo "<p><a href=\"misc.php?" . vB::getCurrentSession()->get('sessionurl') . "do=updateforum&amp;startat=0&amp;processed=0&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
		}
	}
	else
	{
		$end =  $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'] - 1;
		echo '<p>' . construct_phrase($vbphrase['x_to_y_of_z'],  $vbulletin->GPC['startat'], $end, $maxChannel) . '</p>';
		$channels = $assertor->assertQuery('vBAdmincp:getNextChannels', array('startat' => $vbulletin->GPC['startat'], 'blocksize' => $vbulletin->GPC['perpage']));
		$nodeids = array();
		if ($channels->valid())
		{
			foreach ($channels AS $channel)
			{
				$nodeids[] = $channel['nodeid'];
			}
		}
		if (empty($nodeids))
		{
			if ($processed == 0)
			{
				die('no more nodes ' . $vbulletin->GPC['startat'] . ', ' . $vbulletin->GPC['perpage']);
				print_stop_message2('updated_forum_successfully', 'misc');
			}
			else
			{
				$args = array('do' => 'updateforum', 'startat' => 0, 'processed' => 0,
					'sessionurl' => vB::getCurrentSession()->get('sessionurl'), 'perpage' => $vbulletin->GPC['perpage']);
				print_cp_redirect2('misc', $args);
				echo "<p><a href=\"misc.php?" . vB::getCurrentSession()->get('sessionurl') . "do=updateforum&amp;startat=0&amp;processed=0&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
			}
		}
		else
		{
			$assertor->assertQuery('vBAdmincp:updateChannelCounts',
				array('nodeids' => $nodeids));
			$count = $assertor->getRow('vBAdmincp:rows_affected', array());

			if (!empty($count) AND empty($count['errors']) AND !empty($count['qty']))
			{
				$processed += $count['qty'];
			}

			$assertor->assertQuery('vBAdmincp:updateChannelLast',
				array('nodeids' => $nodeids));
			$startat = max($nodeids) + 1;

			$args = array('do' => 'updateforum', 'processed' => $processed, 'sessionurl' => vB::getCurrentSession()->get('sessionurl'),
				'startat' => $startat, 'perpage' => $vbulletin->GPC['perpage'] );
			print_cp_redirect2('misc', $args);
			echo "<p><a href=\"misc.php?" . vB::getCurrentSession()->get('sessionurl') . "do=updateforum&amp;startat=$startat&amp;processed=$processed&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
		}

	}
}

// ###################### Start update threads #######################
if ($_REQUEST['do'] == 'updatethread')
{
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 2000;
	}

	echo '<p>' . $vbphrase['updating_threads'] . '</p>';

	$assertor = vB::getDbAssertor();
	$maxstarter = $assertor->getRow('vBAdmincp:getMaxStarter', array());
	$maxstarter = $maxstarter['maxstarter'];

	if ($maxstarter <= $vbulletin->GPC['startat'])
	{
		print_stop_message2('updated_threads_successfully', 'misc');
	}
	else
	{
		$excludeTypes = array(
			vB_Types::instance()->getContentTypeID('vBForum_Channel'),
			vB_Types::instance()->getContentTypeID('vBForum_Photo'),
			vB_Types::instance()->getContentTypeID('vBForum_Attach'),
		);
		$end =  $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'] - 1;
		echo '<p>' . construct_phrase($vbphrase['x_to_y_of_z'],  $vbulletin->GPC['startat'], $end, $maxstarter) . '</p>';
		$assertor->assertQuery('vBAdmincp:updateThreadCounts', array('start' => $vbulletin->GPC['startat'],
			'end' => $end, 'nonTextTypes' => $excludeTypes));
		$assertor->assertQuery('vBAdmincp:updateThreadLast', array('start' => $vbulletin->GPC['startat'],
			'end' => $end, 'nonTextTypes' => $excludeTypes));
		$startat = $assertor->getRow('vBAdmincp:getNextStarter', array('startat' => $end));

		if (empty($startat) OR !empty($startat['errors']) OR ($end >= $maxstarter))
		{
			print_stop_message2('updated_threads_successfully', 'misc');
		}
		else
		{
			$startat = $startat['next'];
			$args = array();
			parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
			$args['do'] = 'updatethread';
			$args['startat'] = $startat;
			$args['pp'] = $vbulletin->GPC['perpage'];
			print_cp_redirect2('misc', $args);
			echo "<p><a href=\"misc.php?" . vB::getCurrentSession()->get('sessionurl') . "do=updatethread&amp;startat=$startat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
		}
	}
}

// ################## Start rebuilding user reputation ######################
if ($_POST['do'] == 'rebuildreputation')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'reputation_base' => vB_Cleaner::TYPE_INT,
	));

	$users = $vbulletin->db->query_read("
		SELECT reputation.userid, SUM(reputation.reputation) AS totalrep
		FROM " . TABLE_PREFIX . "reputation AS reputation
		GROUP BY reputation.userid
	");

	$userrep = array();
	while ($user = $vbulletin->db->fetch_array($users))
	{
		$user['totalrep'] += $vbulletin->GPC['reputation_base'];
		$userrep["$user[totalrep]"] .= ",$user[userid]";
	}

	if (!empty($userrep))
	{
		foreach ($userrep AS $reputation => $ids)
		{
			$usercasesql .= " WHEN userid IN (0$ids) THEN $reputation";
		}
	}

	if ($usercasesql)
	{
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET reputation =
				CASE
					$usercasesql
					ELSE " . $vbulletin->GPC['reputation_base'] . "
				END
		");
	}
	else // there is no reputation
	{
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET reputation = " . $vbulletin->GPC['reputation_base'] . "
		");
	}

	require_once(DIR . '/includes/adminfunctions_reputation.php');
	build_reputationids();

	print_stop_message2('rebuilt_user_reputation_successfully', 'misc');

}

// ################## Start rebuilding avatar thumbnails ################
if ($_REQUEST['do'] == 'rebuildavatars')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'autoredirect' => vB_Cleaner::TYPE_BOOL,
	));

	if (($memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < 256 * 1024 * 1024 AND $memory_limit > 0)
	{
		@ini_set('memory_limit', 256 * 1024 * 1024);
	}

	if ($vbulletin->options['imagetype'] != 'Magick' AND !function_exists('imagetypes'))
	{
		//print_stop_message2('your_version_no_image_support', 'misc');
	}

	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 20;
	}

	if (!$vbulletin->GPC['startat'])
	{
		$firstattach = $vbulletin->db->query_first("SELECT MIN(userid) AS min FROM " . TABLE_PREFIX . "customavatar");
		$vbulletin->GPC['startat'] = intval($firstattach['min']);
	}

	echo '<p>' . construct_phrase($vbphrase['building_avatar_thumbnails'], "misc.php?" . vB::getCurrentSession()->get('sessionurl') . "do=rebuildavatars&startat=" . $vbulletin->GPC['startat'] . "&pp=" . $vbulletin->GPC['perpage'] . "&autoredirect=" . $vbulletin->GPC['autoredirect']) . '</p>';

	$avatars = $vbulletin->db->query_read("
		SELECT user.userid, user.avatarrevision, customavatar.filedata, customavatar.filename, customavatar.dateline, customavatar.width, customavatar.height
		FROM " . TABLE_PREFIX . "customavatar AS customavatar
		INNER JOIN " . TABLE_PREFIX . "user AS user ON(user.userid=customavatar.userid)
		WHERE customavatar.userid >= " . $vbulletin->GPC['startat'] . "
		ORDER BY customavatar.userid
		LIMIT " . $vbulletin->GPC['perpage']
	);

	$finishat = $vbulletin->GPC['startat'];

	while ($avatar = $vbulletin->db->fetch_array($avatars))
	{
		echo construct_phrase($vbphrase['processing_x'], "$vbphrase[avatar] : $avatar[userid] (" . file_extension($avatar['filename']) . ') ');

		if ($vbulletin->options['usefileavatar'])
		{
			$avatarurl = $vbulletin->options['avatarurl'] . "/avatar$avatar[userid]_$avatar[avatarrevision].gif";
			$avatar['filedata'] = @file_get_contents($avatarurl);
		}

		if (!empty($avatar['filedata']))
		{
			$dataman = new vB_Datamanager_Userpic_Avatar($vbulletin, vB_DataManager_Constants::ERRTYPE_STANDARD, 'userpic');
			$dataman->set_existing($avatar);
			$dataman->save();
			unset($dataman);
		}

		echo '<br />';
		vbflush();

		$finishat = ($avatar['userid'] > $finishat ? $avatar['userid'] : $finishat);
	}

	$finishat++;

	if ($checkmore = $vbulletin->db->query_first("SELECT userid FROM " . TABLE_PREFIX . "customavatar WHERE userid >= $finishat LIMIT 1"))
	{
		if ($vbulletin->GPC['autoredirect'] == 1)
		{
			$args = array();
			parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
			$args['do'] = 'rebuildavatars';
			$args['startat'] = $finishat;
			$args['pp'] = $vbulletin->GPC['perpage'];
			$args['autoredirect'] = 1;
			print_cp_redirect2('misc', $args);
		}
		echo "<p><a href=\"misc.php?" . vB::getCurrentSession()->get('sessionurl') . "do=rebuildavatars&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . '">' . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		print_stop_message2('rebuilt_avatar_thumbnails_successfully', 'misc');
	}
}

// ################## Start rebuilding admin avatar thumbnails ################
if ($_REQUEST['do'] == 'rebuildadminavatars')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'autoredirect' => vB_Cleaner::TYPE_BOOL,
	));

	if (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < 256 * 1024 * 1024 AND $current_memory_limit > 0)
	{
		@ini_set('memory_limit', 256 * 1024 * 1024);
	}

	if ($vbulletin->options['imagetype'] != 'Magick' AND !function_exists('imagetypes'))
	{
		//print_stop_message2('your_version_no_image_support', 'misc');
	}

	$avatarpath = DIR . '/images/avatars/thumbs';

	if (!is_writable($avatarpath))
	{
		print_stop_message2('avatarpath_not_writable');
	}

	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 20;
	}

	if (!$vbulletin->GPC['startat'])
	{
		$firstavatar = $vbulletin->db->query_first("SELECT MIN(avatarid) AS min FROM " . TABLE_PREFIX . "avatar");
		$vbulletin->GPC['startat'] = intval($firstavatar['min']);
	}

	echo '<p>' . construct_phrase($vbphrase['building_avatar_thumbnails'], "misc.php?" . vB::getCurrentSession()->get('sessionurl') . "do=rebuildadminavatars&startat=" . $vbulletin->GPC['startat'] . "&pp=" . $vbulletin->GPC['perpage'] . "&autoredirect=" . $vbulletin->GPC['autoredirect']) . '</p>';

	$avatars = $vbulletin->db->query_read("
		SELECT avatarid, avatarpath, title
		FROM " . TABLE_PREFIX . "avatar
		WHERE avatarid >= " . $vbulletin->GPC['startat'] . "
		ORDER BY avatarid
		LIMIT " . $vbulletin->GPC['perpage']
	);

	$finishat = $vbulletin->GPC['startat'];

	while ($avatar = $vbulletin->db->fetch_array($avatars))
	{
		$finishat = ($avatar['avatarid'] > $finishat ? $avatar['avatarid'] : $finishat);

		echo construct_phrase($vbphrase['processing_x'], "$vbphrase[avatar] : $avatar[avatarid] ($avatar[title])");
		$imagepath = $avatar['avatarpath'];
		$destination = $avatarpath . '/' . $avatar['avatarid'] . '.gif';
		$remotefile = false;

		if (is_file($avatar['avatarpath']))
		{
			$imagepath = $avatar['avatarpath'];
		}
		else
		{
			$location = dirname(__FILE__) . '/../' . $avatar['avatarpath'] ;
			if (is_file($location))
			{
				$imagepath = $location;
			}
			else if ($avatar['avatarpath'][0] == '/')
			{
				// absolute web path -- needs to be translated into a full path and handled that way
				$avatar['avatarpath'] = create_full_url($avatar['avatarpath']);
			}
		}

		if (substr($avatar['avatarpath'], 0, 7) == 'http://')
		{
			if ($vbulletin->options['safeupload'])
			{
				$imagepath = $vbulletin->options['tmppath'] . '/' . md5(uniqid(microtime()) . $avatar['avatarid']);
			}
			else
			{
				$imagepath = tempnam(ini_get('upload_tmp_dir'), 'vbthumb');
			}
			if ($filenum = @fopen($imagepath, 'wb'))
			{
				require_once(DIR . '/includes/class_vurl.php');
				$vurl = new vB_vURL($vbulletin);
				$vurl->set_option(VURL_URL, $avatar['avatarpath']);
				$vurl->set_option(VURL_HEADER, true);
				$vurl->set_option(VURL_RETURNTRANSFER, true);
				if ($result = $vurl->exec())
				{
					@fwrite($filenum, $result['body']);
				}
				unset($vurl);
				@fclose($filenum);
				$remotefile = true;
			}
		}

		if (!file_exists($imagepath))
		{
			echo " ... <span class=\"modsincethirtydays\">$vbphrase[unable_to_read_avatar]</span><br />\n";
			vbflush();
			continue;
		}

		$image =& vB_Image::instance();
		$imageinfo = $image->fetchImageInfo($imagepath);
		if ($imageinfo[0] > FIXED_SIZE_AVATAR_WIDTH OR $imageinfo[1] > FIXED_SIZE_AVATAR_HEIGHT)
		{
			$file = 'file.' . ($imageinfo[2] == 'JPEG' ? 'jpg' : strtolower($imageinfo[2]));
			$thumbnail = $image->fetchThumbnail($file, $imagepath, FIXED_SIZE_AVATAR_WIDTH, FIXED_SIZE_AVATAR_HEIGHT);
			if ($thumbnail['filedata'] AND $filenum = @fopen($destination, 'wb'))
			{
				@fwrite($filenum, $thumbnail['filedata']);
				@fclose($filenum);
			}
			unset($thumbnail);
		}
		else if ($filenum = fopen($destination, 'wb'))
		{
			@fwrite($filenum, file_get_contents($imagepath));
			fclose($filenum);
		}

		if ($remotefile)
		{
			@unlink($imagepath);
		}

		echo "<br />\n";
		vbflush();
	}

	$finishat++;

	if ($checkmore = $vbulletin->db->query_first("SELECT avatarid FROM " . TABLE_PREFIX . "avatar WHERE avatarid >= $finishat LIMIT 1"))
	{
		if ($vbulletin->GPC['autoredirect'] == 1)
		{
			$args = array();
			parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
			$args['do'] = 'rebuildadminavatars';
			$args['startat'] = $finishat;
			$args['pp'] = $vbulletin->GPC['perpage'];
			$args['autoredirect'] = 1;
			print_cp_redirect2('misc', $args);
		}
		echo "<p><a href=\"misc.php?" . vB::getCurrentSession()->get('sessionurl') . "do=rebuildadminavatars&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . '">' . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		print_stop_message2('rebuilt_avatar_thumbnails_successfully', 'misc');
	}

}

if ($_POST['do'] == 'truncatesigcache')
{
	$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "sigparsed");

	print_stop_message2('updated_signature_cache_successfully', 'misc');
}

// ###################### Start remove dupe #######################
if ($_REQUEST['do'] == 'removedupe')
{
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 500;
	}

	echo '<p>' . $vbphrase['removing_duplicate_threads'] . '</p>';

	$channelContentType = vB_Types::instance()->getContentTypeID('vBForum_Channel');

	$topLevelChannels = vB_Api::instanceInternal('content_channel')->fetchTopLevelChannelIds();
	$specialChannelNodeId  = (int) $topLevelChannels['special'];

	if ($specialChannelNodeId < 1)
	{
		print_stop_message2('invalid_special_channel');
	}

	$threads = $vbulletin->db->query_read("
		SELECT nodeid, title, parentid, authorname, publishdate
		FROM " . TABLE_PREFIX . "node
		WHERE nodeid >= " . $vbulletin->GPC['startat'] . "
			AND contenttypeid != " . $channelContentType . "
		ORDER BY nodeid
		LIMIT " . $vbulletin->GPC['perpage']
	);

	$finishat = $vbulletin->GPC['startat'];
	$nodeApi = vB_Api::instance('node');
	$deletedNodeIds = array();

	while ($thread = $vbulletin->db->fetch_array($threads))
	{
		// Skip any threads we have already deleted
		if (in_array($thread['nodeid'], $deletedNodeIds))
		{
			echo construct_phrase($vbphrase['skipping_x'], $thread['nodeid']) . "<br />\n";
			continue;
		}

		// Skip anything in the 'special' channel
		$node = $nodeApi->getNode($thread['nodeid'], true);
		if (isset($node['errors']))
		{
			// Invalid node, we can safely skip it
			$errorPhrase = isset($node['errors'][0][0]) ? $node['errors'][0][0] : '';
			echo construct_phrase($vbphrase['skipping_x'], $thread['nodeid']) . ' ' . (isset($vbphrase[$errorPhrase]) ? $vbphrase[$errorPhrase] : $errorPhrase) . "<br />\n";
			continue;
		}
		else if (in_array($specialChannelNodeId, $node['parents']))
		{
			echo construct_phrase($vbphrase['skipping_x'], $thread['nodeid']) . "<br />\n";
			continue;
		}

		// Skip anything whose parent is not a channel (this means it's not a thread, it's a reply, comment, etc.)
		$parentinfo = $vbulletin->db->query_first("
			SELECT nodeid, parentid, contenttypeid
			FROM " . TABLE_PREFIX . "node
			WHERE nodeid = " . intval($thread['parentid']) . "
		");
		if ($parentinfo['contenttypeid'] != $channelContentType)
		{
			echo construct_phrase($vbphrase['skipping_x'], $thread['nodeid']) . "<br />\n";
			continue;
		}

		echo construct_phrase($vbphrase['processing_x'], $thread['nodeid'] . ' "' . htmlspecialchars($thread['title']) . '"') . "<br />\n";
		vbflush();

		$deletethreads = $vbulletin->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "node
			WHERE title = '" . $vbulletin->db->escape_string($thread['title']) . "' AND
				parentid = $thread[parentid] AND
				authorname = '" . $vbulletin->db->escape_string($thread['authorname']) . "' AND
				publishdate = $thread[publishdate] AND
				nodeid > $thread[nodeid] AND
				contenttypeid != " . $channelContentType . "
		");
		while ($deletethread = $vbulletin->db->fetch_array($deletethreads))
		{
			vB_Api::instanceInternal('node')->deleteNodes($deletethread['nodeid']);
			$deletedNodeIds[] = $deletethread['nodeid'];
			echo "&nbsp;&nbsp;&nbsp; ".construct_phrase($vbphrase['delete_x'], $deletethread['nodeid'] . ' "' . htmlspecialchars($deletethread['title']) . '"') . "<br />";
			vbflush();
		}

		$finishat = ($thread['nodeid'] > $finishat ? $thread['nodeid'] : $finishat);
	}

	$finishat++;

	if ($checkmore = $vbulletin->db->query_first("SELECT nodeid FROM " . TABLE_PREFIX . "node WHERE nodeid >= $finishat LIMIT 1"))
	{
		$args = array();
		parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
		$args['do'] = 'removedupe';
		$args['startat'] = $finishat;
		$args['pp'] = $vbulletin->GPC['perpage'];
		print_cp_redirect2('misc', $args);
		echo "<p><a href=\"misc.php?" . vB::getCurrentSession()->get('sessionurl') . "do=removedupe&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		print_stop_message2('deleted_duplicate_threads_successfully', 'misc');
	}

}

// ###################### Start find lost users #######################
if ($_POST['do'] == 'lostusers')
{
	$users = $vbulletin->db->query_read("
		SELECT user.userid
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield USING(userid)
		WHERE userfield.userid IS NULL
	");

	$userids = array();
	while ($user = $vbulletin->db->fetch_array($users))
	{
		$userids[] = $user['userid'];
	}

	if (!empty($userids))
	{
		/*insert query*/
		$vbulletin->db->query_write("INSERT INTO " . TABLE_PREFIX . "userfield (userid) VALUES (" . implode('),(', $userids) . ")");
	}

	$users = $vbulletin->db->query_read("
		SELECT user.userid
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield USING(userid)
		WHERE usertextfield.userid IS NULL
	");

	$userids = array();
	while ($user = $vbulletin->db->fetch_array($users))
	{
		$userids[] = $user['userid'];
	}

	if (!empty($userids))
	{
		/*insert query*/
		$vbulletin->db->query_write("INSERT INTO " . TABLE_PREFIX . "usertextfield (userid) VALUES (" . implode('),(', $userids) . ")");
	}

	print_stop_message2('user_records_repaired', 'misc');
}

// ###################### Start add missing keywords #######################
if ($_REQUEST['do'] == 'addmissingkeywords')
{
	require_once(DIR . '/includes/functions_newpost.php');

	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 50;
	}

	$finishat = intval($vbulletin->GPC['startat']);

	$excludeTypes =vB_Types::instance()->getContentTypeID('vBForum_Channel') . ', '
		. vB_Types::instance()->getContentTypeID('vBForum_Photo') . ', '
		. vB_Types::instance()->getContentTypeID('vBForum_Attach') . ', '
		. vB_Types::instance()->getContentTypeID('vBForum_PrivateMessage');

	$threads = $vbulletin->db->query_read($query = "
		SELECT thread.nodeid, thread.taglist, thread.prefixid, thread.title, post.rawtext AS firstpost
		FROM " . TABLE_PREFIX . "node AS thread
		LEFT JOIN " . TABLE_PREFIX . "text AS post ON(post.nodeid = thread.nodeid)
		WHERE thread.keywords IS NULL
		AND thread.contenttypeid NOT IN ($excludeTypes)
		AND thread.starter = thread.nodeid
		ORDER BY thread.nodeid ASC
		LIMIT " . $vbulletin->GPC['startat'] . ", " . $vbulletin->GPC['perpage'] . "
	");
	while ($thread = $vbulletin->db->fetch_array($threads))
	{
		$gotsome = true;
		$threadinfo = vB_Api::instanceInternal('node')->getNode($thread['nodeid']);
		if (!$threadinfo)
		{
			$finishat++;
			continue;
		}

		$keywords = fetch_keywords_list($threadinfo, $thread['firstpost']);

		vB::getDbAssertor()->assertQuery('vBForum:node', array(
			vB_dB_Query::TYPE_KEY =>vB_dB_Query::QUERY_UPDATE,
			'keywords' => $keywords,
			vB_dB_Query::CONDITIONS_KEY => array(
				'nodeid' => intval($thread['nodeid']),
			)
		));

		echo construct_phrase($vbphrase['processing_x'], $thread['threadid'])."<br />\n";
		vbflush();
	}

	if ($gotsome)
	{
		$args = array();
		parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
		$args['do'] = 'addmissingkeywords';
		$args['startat'] = $finishat;
		$args['pp'] = $vbulletin->GPC['perpage'];
		print_cp_redirect2('misc', $args);
		echo "<p><a href=\"misc.php?" . vB::getCurrentSession()->get('sessionurl') . "do=addmissingkeywords&amp;pp=" . $vbulletin->GPC['perpage'] . "&amp;startat=$finishat\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		print_stop_message2('added_missing_keywords_successfully', 'misc');
	}

}

// ###################### Start build statistics #######################
if ($_REQUEST['do'] == 'buildstats')
{
	$timestamp =& $vbulletin->GPC['startat'];
	$vbulletin->GPC['perpage'] = 10 * 86400;

	if (empty($timestamp))
	{
		// this is the first page of a stat rebuild
		// so let's clear out the old stats
		$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "stats");

		// and select a suitable start time
		$timestamp = $vbulletin->db->query_first("SELECT MIN(joindate) AS start FROM " . TABLE_PREFIX . "user WHERE joindate > 0");
		if ($timestamp['start'] == 0 OR $timestamp['start'] < 915166800)
		{ // no value found or its before 1999 lets just make it the year 2000
			$timestamp['start'] = 946684800;
		}
		$month = date('n', $timestamp['start']);
		$day = date('j', $timestamp['start']);
		$year = date('Y', $timestamp['start']);

		$timestamp = mktime(0, 0, 0, $month, $day, $year);
	}

	if ($timestamp + $vbulletin->GPC['perpage'] >= TIMENOW)
	{
		$endstamp = TIMENOW;
	}
	else
	{
		$endstamp = $timestamp + $vbulletin->GPC['perpage'];
	}

	$topChannels = vB_Api::instanceInternal('content_channel')->fetchTopLevelChannelIds();
	$forumChannel = $topChannels['forum'];
	$channelContentType = vB_Types::instance()->getContentTypeID('vBForum_Channel');

	while ($timestamp <= $endstamp)
	{
		// new users
		$newusers = $vbulletin->db->query_first('SELECT COUNT(userid) AS total FROM ' . TABLE_PREFIX . 'user WHERE joindate >= ' . $timestamp . ' AND joindate < ' . ($timestamp + 86400));

		// new threads
		$newthreads = $vbulletin->db->query_first('SELECT COUNT(nodeid) AS total FROM ' . TABLE_PREFIX . 'node AS node INNER JOIN ' . TABLE_PREFIX . 'closure AS cl ON cl.parent = ' . $forumChannel . ' WHERE node.nodeid = node.starter AND cl.child = node.nodeid AND node.publishdate >= ' . $timestamp . ' AND node.publishdate < ' . ($timestamp + 86400));

		// new posts
		$newposts = $vbulletin->db->query_first('SELECT COUNT(nodeid) AS total FROM ' . TABLE_PREFIX . 'node AS node INNER JOIN ' . TABLE_PREFIX . 'closure as cl ON cl.parent = ' . $forumChannel . ' WHERE node.nodeid != node.starter AND cl.child = node.nodeid AND node.contenttypeid != ' . $channelContentType . ' AND node.publishdate >= ' . $timestamp . ' AND node.publishdate < ' . ($timestamp + 86400));

		// active users
		$activeusers = $vbulletin->db->query_first('SELECT COUNT(userid) AS total FROM ' . TABLE_PREFIX . 'user WHERE lastactivity >= ' . $timestamp . ' AND lastactivity < ' . ($timestamp + 86400));

		$inserts[] = "($timestamp, $newusers[total], $newthreads[total], $newposts[total], $activeusers[total])";

		echo $vbphrase['done'] . " $timestamp <br />\n";
		vbflush();

		$timestamp += 3600 * 24;

	}

	if (!empty($inserts))
	{
		/*insert query*/
		$vbulletin->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "stats
				(dateline, nuser, nthread, npost, ausers)
			VALUES
				" . implode(',', $inserts) . "
		");
		$args = array();
		parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
		$args['do'] = 'buildstats';
		$args['startat'] = $timestamp;
		print_cp_redirect2('misc', $args);

	}
	else
	{
		print_stop_message2('rebuilt_statistics_successfully', 'misc');
	}
}

// ###################### Start remove dupe threads #######################
if ($_REQUEST['do'] == 'removeorphanthreads')
{
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 50;
	}

	$result = fetch_adminutil_text('orphanthread');

	if ($result == 'done')
	{
		build_adminutil_text('orphanthread');
		print_stop_message2('deleted_orphan_threads_successfully_gmaintenance', 'misc');
	}
	else if ($result != '')
	{
		$threadarray = unserialize($result);
	}
	else
	{
		$excludeTypes = array(
			vB_Types::instance()->getContentTypeID('vBForum_Channel'),
			vB_Types::instance()->getContentTypeID('vBForum_Photo'),
			vB_Types::instance()->getContentTypeID('vBForum_Attach'),
			vB_Types::instance()->getContentTypeID('vBForum_PrivateMessage')
		);
		$channelContentType = vB_Types::instance()->getContentTypeID('vBForum_Channel');

		$threadarray = array();
		// Fetch IDS
		$threads = $vbulletin->db->query_read("
			SELECT thread.nodeid
			FROM " . TABLE_PREFIX . "node AS thread
			LEFT JOIN " . TABLE_PREFIX . "node AS forum ON forum.nodeid = thread.parentid AND forum.contenttypeid = $channelContentType
			WHERE forum.nodeid IS NULL
			AND thread.contenttypeid NOT IN (" . implode(',', $excludeTypes) . ")
			AND thread.starter = thread.nodeid
		");
		while ($thread = $vbulletin->db->fetch_array($threads))
		{
			$threadarray[] = $thread['threadid'];
			$count++;
		}
	}

	echo '<p>' . $vbphrase['removing_orphan_threads'] . '</p>';

	while ($threadid = array_pop($threadarray) AND $count < $vbulletin->GPC['perpage'])
	{
		vB_Api::instanceInternal('node')->deleteNodes($threadid);
		echo construct_phrase($vbphrase['processing_x'], $threadid)."<br />\n";
		vbflush();
		$count++;
	}

	if (empty($threadarray))
	{
		build_adminutil_text('orphanthread', 'done');
	}
	else
	{
		build_adminutil_text('orphanthread', serialize($threadarray));
	}

	$args = array();
	parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
	$args['do'] = 'removeorphanthreads';
	$args['pp'] = $vbulletin->GPC['perpage'];
	print_cp_redirect2('misc', $args);

	echo "<p><a href=\"misc.php?" . vB::getCurrentSession()->get('sessionurl') . "do=removeorphanthreads&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";

}

// ###################### Start remove posts #######################
if ($_REQUEST['do'] == 'removeorphanposts')
{
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 50;
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	$topChannelIds = vB_Api::instanceInternal('Content_Channel')->fetchTopLevelChannelIds();

	$excludeTypes = array(
		vB_Types::instance()->getContentTypeID('vBForum_Channel'),
		vB_Types::instance()->getContentTypeID('vBForum_Photo'),
		vB_Types::instance()->getContentTypeID('vBForum_Attach'),
		vB_Types::instance()->getContentTypeID('vBForum_PrivateMessage')
	);

	$posts = $vbulletin->db->query_read("
		SELECT post.nodeid
		FROM " . TABLE_PREFIX . "node AS post
		INNER JOIN " . TABLE_PREFIX . "closure AS cl ON cl.parent = " . $topChannelIds['forum'] . " AND cl.child = post.nodeid
		LEFT JOIN " . TABLE_PREFIX . "node AS thread ON post.parentid = thread.nodeid AND thread.nodeid = thread.starter
		WHERE thread.nodeid IS NULL
		AND post.nodeid != post.starter
		AND post.contenttypeid NOT IN (" . implode(',', $excludeTypes) . ")
		LIMIT " . $vbulletin->GPC['startat'] . ", " . $vbulletin->GPC['perpage'] . "
	");
	while ($post = $vbulletin->db->fetch_array($posts))
	{
		vB_Api::instanceInternal('node')->deleteNodes($post['nodeid']);
		echo construct_phrase($vbphrase['processing_x'], $post['postid'])."<br />\n";
		vbflush();
		$gotsome = true;
	}

	if($gotsome)
	{
		$args = array();
		parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
		$args['do'] = 'removeorphanposts';
		$args['startat'] = $finishat;
		$args['pp'] = $vbulletin->GPC['perpage'];
		print_cp_redirect2('misc', $args);
		echo "<p><a href=\"misc.php?" . vB::getCurrentSession()->get('sessionurl') . "do=removeorphanposts&amp;pp=" . $vbulletin->GPC['perpage'] . "&amp;startat=$finishat\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		print_stop_message2('deleted_orphan_posts_successfully', 'misc');
	}
}

// ###################### Anonymous Survey Code #######################
if ($_REQUEST['do'] == 'survey')
{
	// first we'd like extra phrase groups from the cphome
	fetch_phrase_group('cphome');

	/*
	All the functions are prefixed with @ to supress errors, this allows us to get feedback from hosts which have almost everything
	useful disabled
	*/

	// What operating system is the webserver running
	$os = @php_uname('s');

	// Using 32bit or 64bit
	$architecture = @php_uname('m');//php_uname('r') . ' ' . php_uname('v') . ' ' . //;

	// Webserver Signature
	$web_server = $_SERVER['SERVER_SOFTWARE'];

	// PHP Web Server Interface
	$sapi_name = @php_sapi_name();

	// If Apache is used, what sort of modules, mod_security?
	if (function_exists('apache_get_modules'))
	{
		$apache_modules = @apache_get_modules();
	}
	else
	{
		$apache_modules = null;
	}

	// Check to see if a recent version is being used
	$php = PHP_VERSION;

	// Check for common PHP Extensions
	$php_extensions = @get_loaded_extensions();

	// Various configuration options regarding PHP
	$php_safe_mode = SAFEMODE ? $vbphrase['on'] : $vbphrase['off'];
	$php_open_basedir = ((($bd = @ini_get('open_basedir')) AND $bd != '/') ? $vbphrase['on'] : $vbphrase['off']);
	$php_memory_limit = ((function_exists('memory_get_usage') AND ($limit = @ini_get('memory_limit'))) ? htmlspecialchars($limit) : $vbphrase['off']);

	// what version of MySQL
	$mysql = $vbulletin->db->query_first("SELECT VERSION() AS version");
	$mysql = $mysql['version'];

	// Post count
	$posts = $vbulletin->db->query_first("SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "post");
	$posts = $posts['total'];

	// User Count
	$users = $vbulletin->db->query_first("SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "user");
	$users = $users['total'];

	// Forum Count
	$forums = $vbulletin->db->query_first("SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "forum");
	$forums = $forums['total'];

	// Usergroup Count
	$usergroups = $vbulletin->db->query_first("SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "usergroup");
	$usergroups = $usergroups['total'];

	// First Forum Post
	$firstpost = $vbulletin->db->query_first("SELECT MIN(dateline) AS firstpost FROM " . TABLE_PREFIX . "post");
	$firstpost = $firstpost['firstpost'];

	// Last upgrade performed
	$lastupgrade = $vbulletin->db->query_first("SELECT MAX(dateline) AS lastdate FROM " . TABLE_PREFIX . "upgradelog");
	$lastupgrade = $lastupgrade['lastdate'];

	// percentage of users not using linear mode
	$nonlinear = $vbulletin->db->query_first("SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "user WHERE threadedmode <> 0");
	$nonlinear = number_format(100 * ($nonlinear['total'] / $users), 2, '.', '');

	// character sets in use within all languages
	$charsets_result = $vbulletin->db->query_read("SELECT DISTINCT charset AS charset FROM " . TABLE_PREFIX . "language");
	$charsets = array();
	while ($charset = $vbulletin->db->fetch_array($charsets_result))
	{
		$charset_name = trim(htmlspecialchars($charset['charset']));
		if ($charset_name != '')
		{
			$charsets["$charset_name"] = $charset_name;
		}
	}
	$vbulletin->db->free_result($charsets_result);

	?>
	<style type="text/css">
	.infotable td { font-size: smaller; }
	.infotable tr { vertical-align: top; }
	.hcell { font-weight: bold; white-space: nowrap; width: 200px; }
	</style>
	<form action="" method="post">
	<?php

	$apache_modules_html = '';
	if (is_array($apache_modules))
	{
		$apache_modules = array_map('htmlspecialchars', $apache_modules);

		foreach ($apache_modules AS $apache_module)
		{
			$apache_modules_html .= "<input type=\"hidden\" name=\"apache_module[]\" value=\"$apache_module\" />";
		}
	}

	$php_extensions_html = '';
	if (is_array($php_extensions))
	{
		$php_extensions = array_map('htmlspecialchars', $php_extensions);

		foreach ($php_extensions AS $php_extension)
		{
			$php_extensions_html .= "<input type=\"hidden\" name=\"php_extension[]\" value=\"$php_extension\" />";
		}
	}

	$charsets_html = '';
	if (is_array($charsets))
	{
		$charsets = array_map('htmlspecialchars', $charsets);

		foreach ($charsets AS $charset)
		{
			$charsets_html .= "<input type=\"hidden\" name=\"charset[]\" value=\"$charset\" />";
		}
	}

	print_table_start();
	print_table_header($vbphrase['anon_server_survey']);
	print_description_row($vbphrase['anon_server_survey_desc']);
	print_table_header('<img src="' . vB::getDatastore()->getOption('bburl') . '/' . $vbulletin->options['cleargifurl'] . '" width="1" height="1" alt="" />');
	print_description_row("
		<table cellpadding=\"0\" cellspacing=\"6\" border=\"0\" class=\"infotable\">
		<tr><td class=\"hcell\">$vbphrase[vbulletin_version_gmaintenance]</td><td>" . $vbulletin->options['templateversion'] . "</td></tr>
		<tr><td class=\"hcell\">$vbphrase[server_type]</td><td>$os</td></tr>
		<tr><td class=\"hcell\">$vbphrase[system_architecture]</td><td>$architecture</td></tr>
		<tr><td class=\"hcell\">$vbphrase[mysql_version]</td><td>$mysql</td></tr>
		<tr><td class=\"hcell\">$vbphrase[web_server]</td><td>$web_server</td></tr>
		<tr><td class=\"hcell\">SAPI</td><td>$sapi_name</td></tr>" . (is_array($apache_modules) ? "
		<tr><td class=\"hcell\">$vbphrase[apache_modules]</td><td>" . implode(', ', $apache_modules) . "</td></tr>" : '') . "
		<tr><td class=\"hcell\">PHP</td><td>$php</td></tr>
		<tr><td class=\"hcell\">$vbphrase[php_extensions]</td><td>" . implode(', ', $php_extensions) . "</td></tr>
		<tr><td class=\"hcell\">$vbphrase[php_memory_limit]</td><td>$php_memory_limit</td></tr>
		<tr><td class=\"hcell\">$vbphrase[php_safe_mode]</td><td>$php_safe_mode</td></tr>
		<tr><td class=\"hcell\">$vbphrase[php_openbase_dir]</td><td>$php_open_basedir</td></tr>
		<tr><td class=\"hcell\">$vbphrase[character_sets_usage]</td><td>" . implode(', ', $charsets) . "</td></tr>
		</table>");

	print_table_header($vbphrase['optional_info']);

	print_description_row("
		<table cellpadding=\"0\" cellspacing=\"6\" border=\"0\" class=\"infotable\">
		<tr><td class=\"hcell\">$vbphrase[total_posts_gmaintenance]</td><td>
			<label for=\"cb_posts\"><input type=\"checkbox\" name=\"posts\" id=\"cb_posts\" value=\"$posts\" checked=\"checked\" />" . vb_number_format($posts) . "</label></td></tr>
		<tr><td class=\"hcell\">$vbphrase[total_users]</td><td>
			<label for=\"cb_users\"><input type=\"checkbox\" name=\"users\" id=\"cb_users\" value=\"$users\" checked=\"checked\" />" . vb_number_format($users) . "</label></td></tr>
		<tr><td class=\"hcell\">$vbphrase[threaded_mode_usage]</td><td>
			<label for=\"cb_nonlinear\"><input type=\"checkbox\" name=\"nonlinear\" id=\"cb_nonlinear\" value=\"$nonlinear\" checked=\"checked\" />" . vb_number_format($nonlinear, 2) . "%</label></td></tr>
		<tr><td class=\"hcell\">$vbphrase[total_forums]</td><td>
			<label for=\"cb_forums\"><input type=\"checkbox\" name=\"forums\" id=\"cb_forums\" value=\"$forums\" checked=\"checked\" />" . vb_number_format($forums) . "</label></td></tr>
		<tr><td class=\"hcell\">$vbphrase[total_usergroups]</td><td>
			<label for=\"cb_usergroups\"><input type=\"checkbox\" name=\"usergroups\" id=\"cb_usergroups\" value=\"$usergroups\" checked=\"checked\" />" . vb_number_format($usergroups) . "</label></td></tr>
		" . ($firstpost > 0 ? "<tr><td class=\"hcell\">$vbphrase[first_post_date]</td><td>
			<label for=\"cb_firstpost\"><input type=\"checkbox\" name=\"firstpost\" id=\"cb_firstpost\" value=\"$firstpost\" checked=\"checked\" />" . vbdate($vbulletin->options['dateformat'], $firstpost) . "</label></td></tr>" : '') .
		 	($lastupgrade > 0 ? "<tr><td class=\"hcell\">$vbphrase[last_upgrade_date]</td><td>
			<label for=\"cb_lastupgrade\"><input type=\"checkbox\" name=\"lastupgrade\" id=\"cb_lastupgrade\" value=\"$lastupgrade\" checked=\"checked\" />" . vbdate($vbulletin->options['dateformat'], $lastupgrade) . "</label></td></tr>" : '') . "
		</table>
		<input type=\"hidden\" name=\"vbversion\" value=\"" . SIMPLE_VERSION . "\" />
		<input type=\"hidden\" name=\"os\" value=\"$os\" />
		<input type=\"hidden\" name=\"architecture\" value=\"$architecture\" />
		<input type=\"hidden\" name=\"mysql\" value=\"$mysql\" />
		<input type=\"hidden\" name=\"web_server\" value=\"$web_server\" />
		<input type=\"hidden\" name=\"sapi_name\" value=\"$sapi_name\" />
			$apache_modules_html
		<input type=\"hidden\" name=\"php\" value=\"$php\" />
			$php_extensions_html
		<input type=\"hidden\" name=\"php_memory_limit\" value=\"$php_memory_limit\" />
		<input type=\"hidden\" name=\"php_safe_mode\" value=\"$php_safe_mode\" />
		<input type=\"hidden\" name=\"php_open_basedir\" value=\"$php_open_basedir\" />
			$charsets_html
	");
	print_submit_row($vbphrase['send_info'], '');
	print_table_footer();
}

// ###################### Start user choices #######################
if ($_REQUEST['do'] == 'chooser')
{
	print_form_header('misc', 'updateuser');
	print_table_header($vbphrase['update_user_titles'], 2, 0);
	print_input_row($vbphrase['number_of_users_to_process_per_cycle_gmaintenance'], 'perpage', 1000);
	print_submit_row($vbphrase['update_user_titles']);

	print_form_header('misc', 'updatethread');
	print_table_header($vbphrase['rebuild_thread_information'], 2, 0);
	print_input_row($vbphrase['number_of_threads_to_process_per_cycle'], 'perpage', 2000);
	print_submit_row($vbphrase['rebuild_thread_information']);

	print_form_header('misc', 'updateforum');
	print_table_header($vbphrase['rebuild_forum_information'], 2, 0);
	print_input_row($vbphrase['number_of_forums_to_process_per_cycle'], 'perpage', 100);
	print_submit_row($vbphrase['rebuild_forum_information']);

	print_form_header('misc', 'addmissingkeywords');
	print_table_header($vbphrase['add_missing_thread_keywords']);
	print_input_row($vbphrase['number_of_threads_to_process_per_cycle'], 'perpage', 1000);
	print_submit_row($vbphrase['add_keywords']);

	print_form_header('misc', 'lostusers');
	print_table_header($vbphrase['fix_broken_user_profiles']);
	print_description_row($vbphrase['finds_users_without_complete_entries']);
	print_submit_row($vbphrase['fix_broken_user_profiles'],NULL);

	print_form_header('misc', 'doindextypes');
	print_table_header($vbphrase['rebuild_search_index'], 2, 0);
	print_description_row(construct_phrase($vbphrase['note_reindexing_empty_indexes_x'], vB::getCurrentSession()->get('sessionurl')));

	//don't use array_merge, it will (incorrectly) assume that the keys are index values
	//instead of meaningful numeric keys and renumber them.
	$contenttypes = vB_Types::instance()->getSearchableContentTypes();
	$types = array ( 0 => $vbphrase['all']);
	foreach ($contenttypes as $key => $type) {
		$types[$key] = $type['class'];
	}
	print_select_row($vbphrase['search_content_type_to_index'], 'indextypes', $types);
	print_input_row($vbphrase['search_items_batch'], 'perpage', 250);
	print_input_row($vbphrase['search_start_item_id'], 'startat', 0);
	//print_input_row($vbphrase['search_items_to_process'], 'doprocess', 0);
	print_yes_no_row($vbphrase['include_automatic_javascript_redirect'], 'autoredirect', 1);
	print_description_row($vbphrase['note_server_intensive']);
	print_submit_row($vbphrase['rebuild_search_index']);
/*
	if ($vbulletin->options['cachemaxage'] > 0)
	{
		print_form_header('misc', 'buildpostcache');
		print_table_header($vbphrase['rebuild_post_cache']);
		print_input_row($vbphrase['number_of_posts_to_process_per_cycle'], 'perpage', 1000);
		print_submit_row($vbphrase['rebuild_post_cache']);
	}
*/
	print_form_header('misc', 'truncatesigcache');
	print_table_header($vbphrase['empty_signature_cache']);
	print_description_row($vbphrase['change_output_signatures_empty_cache']);
	print_submit_row($vbphrase['empty_signature_cache'],NULL);

	print_form_header('misc', 'buildstats');
	print_table_header($vbphrase['rebuild_statistics'], 2, 0);
	print_description_row($vbphrase['rebuild_statistics_warning']);
	print_submit_row($vbphrase['rebuild_statistics'],NULL);
/*
	print_form_header('misc', 'updatesimilar');
	print_table_header($vbphrase['rebuild_similar_threads']);
	print_description_row($vbphrase['note_rebuild_similar_thread_list']);
	print_input_row($vbphrase['number_of_threads_to_process_per_cycle'], 'perpage', 100);
	print_submit_row($vbphrase['rebuild_similar_threads']);
*/
	print_form_header('misc', 'removedupe');
	print_table_header($vbphrase['delete_duplicate_threads'], 2, 0);
	print_description_row($vbphrase['note_duplicate_threads_have_same']);
	print_input_row($vbphrase['number_of_threads_to_process_per_cycle'], 'perpage', 500);
	print_submit_row($vbphrase['delete_duplicate_threads']);

	print_form_header('misc', 'rebuildavatars');
	print_table_header($vbphrase['rebuild_custom_avatar_thumbnails'], 2, 0);
	#print_description_row($vbphrase['function_rebuilds_avatars']);
	print_input_row($vbphrase['number_of_avatars_to_process_per_cycle'], 'perpage', 25);
	print_yes_no_row($vbphrase['include_automatic_javascript_redirect'], 'autoredirect', 1);
	print_submit_row($vbphrase['rebuild_custom_avatar_thumbnails']);

	print_form_header('misc', 'rebuildadminavatars');
	print_table_header($vbphrase['rebuild_avatar_thumbnails'], 2, 0);
	#print_description_row($vbphrase['function_rebuilds_avatars']);
	print_input_row($vbphrase['number_of_avatars_to_process_per_cycle'], 'perpage', 25);
	print_yes_no_row($vbphrase['include_automatic_javascript_redirect'], 'autoredirect', 1);
	print_submit_row($vbphrase['rebuild_avatar_thumbnails']);
/*
	print_form_header('misc', 'rebuildsgicons');
	print_table_header($vbphrase['rebuild_sgicon_thumbnails'], 2, 0);
	print_input_row($vbphrase['number_of_icons_to_process_per_cycle'], 'perpage', 25);
	$quality = intval($vbulletin->options['thumbquality']);
	if ($quality <= 0 OR $quality > 100)
	{
		$quality = 75;
	}
	print_input_row($vbphrase['thumbnail_quality_gmaintenance'], 'quality', $quality);
	print_yes_no_row($vbphrase['include_automatic_javascript_redirect'], 'autoredirect', 1);
	print_submit_row($vbphrase['rebuild_sgicon_thumbnails']);
*/
/*
	print_form_header('misc', 'rebuildalbumupdates');
	print_table_header($vbphrase['rebuild_recently_updated_albums_list'], 1, 0);
	print_description_row($vbphrase['rebuild_recently_updated_albums_description']);
	print_submit_row($vbphrase['rebuild_album_updates'],NULL);
*/
	print_form_header('misc', 'rebuildreputation');
	print_table_header($vbphrase['rebuild_user_reputation'], 2, 0);
	print_description_row($vbphrase['function_rebuilds_reputation']);
	print_input_row($vbphrase['reputation_base'], 'reputation_base', $vbulletin->options['reputationdefault']);
	print_submit_row($vbphrase['rebuild_user_reputation']);

	print_form_header('misc', 'updateusernames');
	print_table_header($vbphrase['update_usernames']);
	print_input_row($vbphrase['number_of_users_to_process_per_cycle_gmaintenance'], 'perpage', 1000);
	print_submit_row($vbphrase['update_usernames']);

	print_form_header('misc', 'updateposts');
	print_table_header($vbphrase['update_post_counts'], 2, 0);
	print_description_row($vbphrase['recalculate_users_post_counts_warning']);
	print_input_row($vbphrase['number_of_users_to_process_per_cycle_gmaintenance'], 'perpage', 1000);
	print_submit_row($vbphrase['update_post_counts']);

	print_form_header('misc', 'rebuildstyles');
	print_table_header($vbphrase['rebuild_styles'], 2, 0, 'style');
	print_description_row($vbphrase['function_allows_rebuild_all_style_info']);
	print_yes_no_row($vbphrase['check_styles_no_parent'], 'install', 1);
	print_yes_no_row($vbphrase['renumber_all_templates_from_one'], 'renumber', 0);
	print_submit_row($vbphrase['rebuild_styles'], 0);

	build_adminutil_text('orphanthread');
	print_form_header('misc', 'removeorphanthreads');
	print_table_header($vbphrase['remove_orphan_threads']);
	print_description_row($vbphrase['function_removes_orphan_threads']);
	print_input_row($vbphrase['number_of_threads_to_process_per_cycle'], 'perpage', 50);
	print_submit_row($vbphrase['remove_orphan_threads']);

	print_form_header('misc', 'removeorphanposts');
	print_table_header($vbphrase['remove_orphan_posts']);
	print_description_row($vbphrase['function_removes_orphan_posts']);
	print_input_row($vbphrase['number_of_posts_to_process_per_cycle'], 'perpage', 50);
	print_submit_row($vbphrase['remove_orphan_posts']);
}

// Legacy Hook 'admin_maintenance' Removed //

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 71084 $
|| ####################################################################
\*======================================================================*/
