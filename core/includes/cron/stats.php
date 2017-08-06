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
if (!is_object($vbulletin->db))
{
	exit;
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

// all these stats are for that day
$timestamp = vB::getRequest()->getTimeNow() - 3600 * 23;
// note: we only subtract 23 hours from the current time to account for Spring DST. Bug id 2673.

$month = date('n', $timestamp);
$day = date('j', $timestamp);
$year = date('Y', $timestamp);

$timestamp = mktime(0, 0, 0, $month, $day, $year);
// new users
$newusers = vB::getDbAssertor()->getRow('user', array(vB_dB_Query::CONDITIONS_KEY=> array(
			array('field'=>'joindate', 'value' => $timestamp, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_GTE)
		),
		vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_COUNT
));
$newusers['total'] = intval($newusers['count']);

// active users
$activeusers = vB::getDbAssertor()->getRow('user', array(vB_dB_Query::CONDITIONS_KEY=> array(
			array('field'=>'lastactivity', 'value' => $timestamp, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_GTE)
		),
		vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_COUNT
));
$activeusers['total'] = intval($activeusers['count']);

// new nodes
$newnodes = vB::getDbAssertor()->getRow('vBForum:node', array(vB_dB_Query::CONDITIONS_KEY=> array(
			array('field'=>'publishdate', 'value' => $timestamp, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_GTE)
		),
		vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_COUNT
));

$newnodes['total'] = intval($newnodes['count']);


// also rebuild user stats
require_once(DIR . '/includes/functions_databuild.php');
build_user_statistics();

/*insert query*/
vB::getDbAssertor()->assertQuery('stats', array(
		vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERTIGNORE,
		'dateline' => $timestamp,
		'nuser' => $newusers['total'],
		'npost' => $newnodes['total'],
		'ausers' => $activeusers['total']
));

log_cron_action('', $nextitem, 1);

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 67238 $
|| ####################################################################
\*======================================================================*/
?>