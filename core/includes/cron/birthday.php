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


$ids = array(0);
$bf_misc_useroptions = vB::getDatastore()->get_value('bf_misc_useroptions');
$bf_ugp_genericoptions = vB::getDatastore()->get_value('bf_ugp_genericoptions');
$usergroupcache = vB::getDatastore()->get_value('usergroupcache');
foreach($usergroupcache AS $usergroupid => $usergroup)
{
	if ($usergroup['genericoptions'] & $bf_ugp_genericoptions['showbirthday'] AND $usergroup['genericoptions'] & $bf_ugp_genericoptions['isnotbannedgroup'] AND !in_array($usergroup['usergroupid'], array(1, 3, 4)))
	{
		$ids[] = $usergroupid;
	}
}


$birthdays = vB::getDbAssertor()->assertQuery('fetchUsersWithBirthday', array(
		'today' => date('m-d', vB::getRequest()->getTimeNow()),
		'adminemail' => $bf_misc_useroptions['adminemail'],
		'usergroupids' => $ids
	));

vB_Mail::vbmailStart();
foreach ($birthdays as $userinfo)
{
	$username = unhtmlspecialchars($userinfo['username']);
	$vboptions = vB::getDatastore()->getValue('options');
	$maildata = vB_Api::instanceInternal('phrase')->fetchEmailPhrases('birthday', array($username, $vboptions['bbtitle']), array($vboptions['bbtitle']), $userinfo['languageid']);
	vB_Mail::vbmail($userinfo['email'], $maildata['subject'], $maildata['message']);
	$emails .= iif($emails, ', ');
	$emails .= $userinfo['username'];
}

vB_Mail::vbmailEnd();

if ($emails)
{
	log_cron_action($emails, $nextitem, 1);
}


/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>
