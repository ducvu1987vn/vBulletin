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

//This removes private messages with no activity for 30 days.

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

//First we get a list up to 500 records.

$assertor = vB::getDbAssertor();
$records = $assertor->assertQuery('vBForum:getDeletedMsgs', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
	'deleteLimit' => vB::getRequest()->getTimeNow() - (30 * 86400),
	vB_dB_Query::PARAM_LIMIT => 200));

if ($records AND $records->valid())
{
	//We know which records we are going to delete, but we need to also delete their children.
	//So we need to pull from the closure table.
	$nodeids = array();
	foreach ($records as $record)
	{
		$nodeids[] = $record['nodeid'];
	}
	$nodeQuery = $assertor->assertQuery('vBForum:closure', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
	'parent' => $nodeids));

	if ($nodeQuery AND $nodeQuery->valid())
	{
		$deleteList = array();
		foreach ($nodeQuery as $record)
		{
			$deleteList[] = $record['child'];
		}
	}
	//Now we can do the delete.
	vB_Api::instanceInternal('content_privatemessage')->delete($deleteList);
}

log_cron_action('', $nextitem, 1);

/*======================================================================*\
|| ####################################################################
|| # CVS: $Revision: 37230 $
|| ####################################################################
\*======================================================================*/
?>