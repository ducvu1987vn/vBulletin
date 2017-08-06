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


/**
 * Builds infraction groups for users
 *
 * @param	array	User IDs to build
 *
 */
function build_infractiongroupids($userids)
{
	global $vbulletin;
	static $infractiongroups = array(), $beenhere = false;
	if (!$beenhere)
	{
		$beenhere = true;
		$groups = vB::getDbAssertor()->assertQuery('infractiongroup' ,array
			(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			),
			array('field' => 'pointlevel', 'direction' => vB_dB_Query::SORT_ASC)
		);
		foreach ($groups as $group)
		{
			$infractiongroups["$group[usergroupid]"]["$group[pointlevel]"][] = array(
				'orusergroupid'	=> $group['orusergroupid'],
				'override'		=> $group['override'],
			);
		}
	}
	if (!empty($userids))
	{
		return;
	}
	$users = vB::getDbAssertor()->assertQuery('user' ,array
		(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'userid', 'value' => $userids, 'operator' => vB_dB_Query::OPERATOR_EQ)
			)
		));
	foreach ($users as $user)
	{
		$infractioninfo = fetch_infraction_groups($infractiongroups, $user['userid'], $user['ipoints'], $user['usergroupid']);

		if (($groupids = implode(',', $infractioninfo['infractiongroupids'])) != $user['infractiongroupids'] OR $infractioninfo['infractiongroupid'] != $user['infractiongroupid'])
		{
			$userdata = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_STANDARD);
			$userdata->set_existing($user);
			$userdata->set('infractiongroupids', $groupids);
			$userdata->set('infractiongroupid', $infractioninfo['infractiongroupid']);
			$userdata->save();
		}
	}
}

/**
* Takes valid data and sets it as part of the data to be saved
*
* @param	array		List of infraction groups
* @param integer  Userid of user
* @param	integer	Infraction Points
* @param interger Usergroupid
*
* @return array	User's final infraction groups
*/
function fetch_infraction_groups(&$infractiongroups, $userid, $ipoints, $usergroupid)
{
	static $cache = array();
	$data = array();

	$infractiongroupids = array();

	if (!empty($infractiongroups["$usergroupid"]))
	{
		foreach($infractiongroups["$usergroupid"] AS $pointlevel => $orusergroupids)
		{
			if ($pointlevel <= $ipoints)
			{
				foreach($orusergroupids AS $infinfo)
				{
					$data['infractiongroupids']["$infinfo[orusergroupid]"] = $infinfo['orusergroupid'];
					if ($infinfo['override'] AND $cache["$userid"]['pointlevel'] <= $pointlevel)
					{
						$cache["$userid"]['pointlevel'] = $pointlevel;
						$cache["$userid"]['infractiongroupid'] = $infinfo['orusergroupid'];
					}
				}
			}
			else
			{
				break;
			}
		}
	}

	if (!is_array($data['infractiongroupids']))
	{
		$data['infractiongroupids'] = array();
	}

	if ($usergroupid != -1)
	{
		$temp = fetch_infraction_groups($infractiongroups, $userid, $ipoints, -1);
		$data['infractiongroupids'] = array_merge($data['infractiongroupids'], $temp['infractiongroupids']);
	}

	if (!is_array($data['infractiongroupids']))
	{
		$data['infractiongroupids'] = array();
	}

	$data['infractiongroupid'] = intval($cache["$userid"]['infractiongroupid']);
	return $data;
}

/**
* Recalculates the members of an infraction group based on changes to it.
* Specifying the (required) override group ID allows removal of users from the group.
* Specifying the point level and applicable group allows addition of users to the group.
*
* @param	integer	Usergroup ID users are placed in
* @param	integer	Point level when this infraction group kicks in
* @param	integer	User group that this infraction group applies to
*/
function check_infraction_group_change($override_groupid, $point_level = null, $applies_groupid = -1)
{
	global $vbulletin;
	$params = array
			(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'override_groupid' => $override_groupid
			);

	if ($point_level !== null)
	{
		$params['point_level'] = $point_level;
		if ($applies_groupid != -1)
		{
			$params['applies_groupid'] = $applies_groupid;
		}
	}
	$users = vB::getDbAssertor()->assertQuery('fetchUsersInfractionGroups' ,$params);
	foreach ($users as $user)
	{
		$users[] = $user['userid'];
	}

	if ($users)
	{
		build_infractiongroupids($users);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>
