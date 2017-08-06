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
// if (!is_object($vbulletin->db))
// {
// 	exit;
// }

// ########################## REQUIRE BACK-END ############################
require_once(DIR . '/includes/functions_infractions.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$timenow = vB::getRequest()->getTimeNow();
$data = array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_STORED,
	'timenow' => $timenow);
$assertor = vB::getDbAssertor();
$infractions = $assertor->assertQuery('getUserExpiredInfractions', $data);

if ($infractions->valid())
{
	$infractionid = array();

	$warningarray = array();
	$infractionarray = array();
	$ipointsarray = array();

	$userids = array();
	$usernames = array();
	$infraction = $infractions->current();
	while ($infractions->valid())
	{
		$quantity = $assertor->assertquery('infraction', array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			vB_dB_Query::CONDITIONS_KEY => array('infractionid' => $infraction[infractionid],
				'action' => 0),
			'action' => 1, 'actiondateline' => $timenow));

	// enforce atomic update so that related records are only updated at most one time, in the event this task is executed more than one time
		if ($quantity)
	{
		$userids["$infraction[userid]"] = $infraction['username'];
		if ($infraction['points'])
		{
			$infractionarray["$infraction[userid]"]++;
			$ipointsarray["$infraction[userid]"] += $infraction['points'];
		}
		else
		{
			$warningarray["$infraction[userid]"]++;
		}
	}
		$infraction = $infractions->next();
	}

	// ############################ MAGIC ###################################
	if (!empty($userids))
	{
		$result = $assertor->assertquery('buildUserInfractions', array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'points' => $ipointsarray,
			'infractions' => $infractionarray,
			'warnings' => $warningarray
			)
		);
		if ($result)
		{
			vB_Api::instance('infraction')->build_infractiongroupids(array_keys($userids));
		}
	}

	if (!empty($userids))
	{
	log_cron_action(implode(', ', $userids), $nextitem, 1);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 62949 $
|| ####################################################################
\*======================================================================*/
?>