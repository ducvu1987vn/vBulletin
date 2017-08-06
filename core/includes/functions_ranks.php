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

error_reporting(E_ALL & ~E_NOTICE);

// #################### Fetch User's Rank ################
function &fetch_rank(&$userinfo)
{
	global $vbulletin;

	if (!is_array($vbulletin->ranks))
	{
		// grab ranks since we didn't include 'ranks' in $specialtemplates
		$vbulletin->ranks =& build_ranks();
	}

	$doneusergroup = array();
	$userrank = '';

	foreach ($vbulletin->ranks AS $rank)
	{
		$displaygroupid = empty($userinfo['displaygroupid']) ? $userinfo['usergroupid'] : $userinfo['displaygroupid'];
		if ($userinfo['posts'] >= $rank['m'] AND (!isset($doneusergroup["$rank[u]"]) OR $doneusergroup["$rank[u]"] === $rank['m'])
		AND
		(($rank['u'] > 0 AND is_member_of($userinfo, $rank['u'], false) AND (empty($rank['d']) OR $rank['u'] == $displaygroupid))
		OR
		($rank['u'] == 0 AND (empty($rank['d']) OR empty($userrank)))))
		{
			if (!empty($userrank) AND $rank['s'])
			{
				$userrank .= '<br />';
			}
			$doneusergroup["$rank[u]"] = $rank['m'];
			for ($x = $rank['l']; $x--; $x > 0)
			{
				if (empty($rank['t']))
				{
					$userrank .= "<img src=\"$rank[i]\" alt=\"\" border=\"\" />";
				}
				else
				{
					$userrank .= $rank['i'];
				}
			}
		}
	}

	return $userrank;
}

// #################### Begin Build Ranks PHP Code function ################
function &build_ranks()
{
	$ranks = vB::getDbAssertor()->assertQuery('vBForum:fetchranks', array(
		vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
	));

	$rankarray = array();
	foreach($ranks as $rank)
	{
		$rankarray[] = $rank;
	}

	build_datastore('ranks', serialize($rankarray), 1);

	return $rankarray;
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>