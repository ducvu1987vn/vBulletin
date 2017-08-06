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

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 68365 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbulletin, $vbphrase;
$phrasegroups = array('stats');
$specialtemplates = array('userstats', 'maxloggedin');

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/adminfunctions_stats.php');

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################
$assertor = vB::getDbAssertor();
print_cp_header($vbphrase['statistics_gcpglobal']);

if (empty($_REQUEST['do']) OR $_REQUEST['do'] == 'index' OR $_REQUEST['do'] == 'top')
{
	print_form_header('stats', 'index');
	print_table_header($vbphrase['statistics_gcpglobal']);
	print_label_row(construct_link_code($vbphrase['top_statistics'], 'stats.php?do=top'), '');
	print_label_row(construct_link_code($vbphrase['registration_statistics'], 'stats.php?do=reg'), '');
	print_label_row(construct_link_code($vbphrase['user_activity_statistics'], 'stats.php?do=activity'), '');
	print_label_row(construct_link_code($vbphrase['new_thread_statistics'], 'stats.php?do=thread'), '');
	print_label_row(construct_link_code($vbphrase['new_post_statistics'], 'stats.php?do=post'), '');
	print_table_footer();
}

// Find most popular things below
if ($_REQUEST['do'] == 'top')
{
	// max logged in users
	$recorddate = vbdate($vbulletin->options['dateformat'], $vbulletin->maxloggedin['maxonlinedate'], 1);
	$recordtime = vbdate($vbulletin->options['timeformat'], $vbulletin->maxloggedin['maxonlinedate']);

	// Most Posts
	$maxposts = $assertor->getRow('vBForum:getMaxPosts', array());

	// Largest Thread
	$maxthread = $assertor->getRow('vBForum:getMaxThread', array());

	// Most Popular Thread
	/** @TODO
	 * There is no actual way to retrive this
	 */
//	$mostpopular = $assertor->getRow('vBForum:getMostPopularThread', array()); //Not tracking!!!!!!

	// Most Popular Forum
	$popularforum = $assertor->getRow('vBForum:getMostPopularForum', array());

	print_form_header('');
	print_table_header($vbphrase['top']);

	print_label_row($vbphrase['newest_member'], construct_link_code($vbulletin->userstats['newusername'], "user.php?do=edit&u=" . $vbulletin->userstats['newuserid']));
	print_label_row($vbphrase['record_online_users'], "{$vbulletin->maxloggedin[maxonline]} ($recorddate $recordtime)");

	/** @todo this won't work!!!!!!*/
	print_label_row($vbphrase['top_poster'], construct_link_code("$maxposts[username] - $maxposts[posts]",
		"user.php?do=edit&u=$maxposts[userid]"));
	print_label_row($vbphrase['most_replied_thread'], construct_link_code($maxthread['title'],
		vB5_Route::buildUrl("{$maxthread['routeid']}|fullurl|nosession", $maxthread), true));
//	print_label_row($vbphrase['most_viewed_thread'], construct_link_code($mostpopular['title'],
//		fetch_seo_url('thread|bburl', $mostpopular), true));
	print_label_row($vbphrase['most_popular_forum'], construct_link_code($popularforum['title'],
		vB5_Route::buildUrl("{$popularforum['routeid']}|fullurl|nosession"), true));
	print_table_footer();

}

$vbulletin->input->clean_array_gpc('r', array(
	'start'     => vB_Cleaner::TYPE_ARRAY_INT,
	'end'       => vB_Cleaner::TYPE_ARRAY_INT,
	'scope'     => vB_Cleaner::TYPE_STR,
	'sort'      => vB_Cleaner::TYPE_STR,
	'nullvalue' => vB_Cleaner::TYPE_BOOL,
));

// Default View Values
if (empty($vbulletin->GPC['start']))
{
	$vbulletin->GPC['start'] = TIMENOW - 3600 * 24 * 30;
}

if (empty($vbulletin->GPC['end']))
{
	$vbulletin->GPC['end'] = TIMENOW;
}


switch ($_REQUEST['do'])
{

	case 'reg':
		$type = 'nuser';
		print_statistic_code($vbphrase['registration_statistics'], 'reg', $vbulletin->GPC['start'], $vbulletin->GPC['end'], $vbulletin->GPC['nullvalue'], $vbulletin->GPC['scope'], $vbulletin->GPC['sort']);
		break;
	case 'thread':
		$type = 'nthread';
		print_statistic_code($vbphrase['new_thread_statistics'], 'thread', $vbulletin->GPC['start'], $vbulletin->GPC['end'], $vbulletin->GPC['nullvalue'], $vbulletin->GPC['scope'], $vbulletin->GPC['sort']);
		break;
	case 'post':
		$type = 'npost';
		print_statistic_code($vbphrase['new_post_statistics'], 'post', $vbulletin->GPC['start'], $vbulletin->GPC['end'], $vbulletin->GPC['nullvalue'], $vbulletin->GPC['scope'], $vbulletin->GPC['sort']);
		break;
	case 'activity':
		$type = 'ausers';
		print_statistic_code($vbphrase['user_activity_statistics'], 'activity', $vbulletin->GPC['start'], $vbulletin->GPC['end'], $vbulletin->GPC['nullvalue'], $vbulletin->GPC['scope'], $vbulletin->GPC['sort']);
		break;
}

if (!empty($vbulletin->GPC['scope']))
{ // we have a submitted form
	$start_time = intval(mktime(0, 0, 0, $vbulletin->GPC['start']['month'], $vbulletin->GPC['start']['day'], $vbulletin->GPC['start']['year']));
	$end_time = intval(mktime(0, 0, 0, $vbulletin->GPC['end']['month'], $vbulletin->GPC['end']['day'], $vbulletin->GPC['end']['year']));
	if ($start_time >= $end_time)
	{
		print_stop_message2('start_date_after_end_gstats');
	}

	if ($type == 'activity')
	{
		$vbulletin->GPC['scope'] = 'daily';
	}

	switch ($vbulletin->GPC['scope'])
	{
		case 'weekly':
			$sqlformat = '%U %Y';
			$phpformat = '# (! Y)';
			break;
		case 'monthly':
			$sqlformat = '%m %Y';
			$phpformat = '! Y';
			break;
		default:
			$sqlformat = '%w %U %m %Y';
			$phpformat = '! d, Y';
			break;
	}

	$statistics = $assertor->assertQuery('vBForum:fetchStats',
		array('type' => $type,
			'sqlformat' => $sqlformat,
			'start_time' => $start_time,
			'end_time' => $end_time,
			'nullvalue' => $vbulletin->GPC['nullvalue'],
			'orderby' => $vbulletin->GPC['sort']
		)
	);


	if ($statistics AND $statistics->valid())
	{ // we will now have each days total of the type picked and we can sort through it
		foreach ($statistics AS $stats)
		{
			$month = strtolower(date('F', $stats['dateline']));
			$dates[] = str_replace(' ', '&nbsp;', str_replace('#', $vbphrase['week'] . '&nbsp;' . strftime('%U', $stats['dateline']), str_replace('!', $vbphrase["$month"], date($phpformat, $stats['dateline']))));
			$results[] = $stats['total'];
		}
	}

	if (!sizeof($results))
	{
		//print_array($results);
		print_stop_message2('no_matches_found_gerror');
	}

	// we'll need a poll image
	$style = $assertor->getRow('vBForum:fetchStylesById', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'styleid' => $vbulletin->options['styleid']));
	$vbulletin->stylevars = unserialize($style['newstylevars']);
	fetch_stylevars($style, $vbulletin->userinfo);

	print_form_header('');
	print_table_header($vbphrase['results'], 3);
	print_cells_row(array($vbphrase['date'], '&nbsp;', $vbphrase['total']), 1);
	$maxvalue = max($results);
	$i = 0;
	foreach ($results as $key => $value)
	{
		$i++;
		$bar = ($i % 6) + 1;
		if ($maxvalue == 0)
		{
			$percentage = 100;
		}
		else
		{
			$percentage = ceil(($value/$maxvalue) * 100);
		}
		print_statistic_result($dates["$key"], $bar, $value, $percentage);
	}
	print_table_footer(3);
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>
