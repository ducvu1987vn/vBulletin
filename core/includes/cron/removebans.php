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

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

// select all banned users who are due to have their ban lifted
$bannedusers = vB::getDbAssertor()->assertQuery('getBannedUsers', array('liftdate' => vB::getRequest()->getTimeNow()));

// some users need to have their bans lifted
$vbulletin = &vB::get_registry();
foreach ($bannedusers as $banneduser)
{
	// get usergroup info
	$getusergroupid = iif($banneduser['bandisplaygroupid'], $banneduser['bandisplaygroupid'], $banneduser['banusergroupid']);
	$usergroup = $vbulletin->usergroupcache["$getusergroupid"];
	if ($banneduser['bancustomtitle'])
	{
		$usertitle = $banneduser['banusertitle'];
	}
	else if (!$usergroup['usertitle'])
	{
		$gettitle = vB::getDbAssertor()->getRow('usertitle', array(vB_dB_Query::CONDITIONS_KEY=> array(
			array('field'=>'minposts', 'value' => $banneduser['posts'], vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_LTE)
		), array(array('field' => 'minposts', 'direction' => vB_dB_Query::SORT_DESC))));
		$usertitle = $gettitle['title'];
	}
	else
	{
		$usertitle = $usergroup['usertitle'];
	}

	// update users to get their old usergroupid/displaygroupid/usertitle back
	$userdm = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
	$userdm->set_existing($banneduser);
	$userdm->set('usertitle', $usertitle);
	$userdm->set('usergroupid', $banneduser['banusergroupid']);
	$userdm->set('displaygroupid', $banneduser['bandisplaygroupid']);
	$userdm->set('customtitle', $banneduser['bancustomtitle']);

	$userdm->save();
	unset($userdm);

	$users["$banneduser[userid]"] = $banneduser['username'];
}
if (!empty($users))
{
	// delete ban records
	vB::getDbAssertor()->delete('userban', array('userid' => array_keys($users)));

	// log the cron action
	log_cron_action(implode(', ', $users), $nextitem, 1);
}
/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>