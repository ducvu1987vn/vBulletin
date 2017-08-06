<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Blog 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * @uses
 * This script keep up to date stats on visits and comments for nodes
 * as well as cleanup older stats
 */

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

$timestamp = vB::getRequest()->getTimeNow();
$assertor = vB::getDbAssertor();
//Perform statistical calculation operations and update Stats Table (nodestats)
$calcStats = $assertor->assertQuery('vBForum:calculateStats', array());
if ($calcStats === false)
{
	//We set the next run to timenow + 1 minute;
	$assertor->assertQuery('cron', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
	vB_dB_Query::CONDITIONS_KEY => array('varname' => 'nodestats'),
	'nextrun' => $timestamp + 60 ));
}
else
{
	log_cron_action('', $nextitem, 1);
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $Revision: 25612 $
|| ####################################################################
\*======================================================================*/
?>