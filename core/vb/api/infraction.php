<?php
if (!defined('VB_ENTRY')) die('Access denied.');
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
 * vB_Api_Infraction
 * Handles user infractions
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Infraction extends vB_Api
{
 	protected $assertor;
 	protected $usercontext;

 	/** Standard constructor
 	*
 	*
 	***/
 	function __construct()
 	{
 		parent::__construct();
		$this->assertor = vB::getDbAssertor();
 		$this->usercontext = vB::getUserContext();
 	}

 	function add($data)
 	{
 		//confirm permissions
 		if (!$this->usercontext->hasPermission('genericpermissions','cangiveinfraction' ))
 		{
 			throw new Exception('no_permission');
 		}

 		//We need either an infractionlevelid or points, expires, and customreason

 		if (empty($data['infractionlevelid']) AND (empty($data['points']) OR empty($data['expires']) OR empty($data['customreason'])))
 		{
 			throw new Exception('incomplete_data');
 		}

 		if (empty($data['userid']))
 		{
 			throw new Exception('no_user_specified');
 		}

 		if (!empty($data['nodeid']))
 		{
 			$node = $this->assertor->getRow('vBForum:node', array('nodeid' => $data['nodeid']));

 			if (empty($node))
 			{
 				throw new Exception('invalid_node');
 			}
 			$dm = new vB_DataManager_Infraction(vB_DataManager_Constants::ERRTYPE_STANDARD);
 			$dm->set('nodeid', $data['nodeid']);
 		}
 		else
 		{
 			$dm = new vB_DataManager_Infraction(vB_DataManager_Constants::ERRTYPE_STANDARD);
 		}
		//$infraction
 		foreach ($data as $field => $value)
 		{
 			$dm->set($field, $value);
 		}

 		return($dm->save());
 	}

 	/**
 	 * updates an infraction
 	 * @param int $pointlevel
 	 * @param int $usergroupid
 	 * @param int $orusergroupid
 	 * @param bool $override
 	 * @param int $infractiongroupid - optional
 	 */

	function save_group($pointlevel, $usergroupid, $orusergroupid, $override, $infractiongroupid = false)
	{
		if (empty($pointlevel))
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}
		$conditions = array(
			array('field' => 'usergroupid', 'value' => $pointlevel, 'operator' => vB_dB_Query::OPERATOR_EQ)
		);
		if ($usergroupid != -1)
		{
			$conditions[] = array('field' => 'pointlevel', 'value' => array(-1, $usergroupid), 'operator' => vB_dB_Query::OPERATOR_EQ);
		}
		if ($infractiongroupid)
		{
			$conditions[] = array('field' => 'infractiongroupid', 'value' => $infractiongroupid, 'operator' => vB_dB_Query::OPERATOR_NE);
		}
		$assertor = vB::getDbAssertor();
		if ($assertor->getRow('infractiongroup', array(vB_dB_Query::CONDITIONS_KEY  => $conditions)))
		{
			throw new vB_Exception_Api('invalid_infraction_usergroup');
		}

		if (empty($vbulletin->GPC['infractiongroupid']))
		{
			$infractiongroupid = $assertor->insert('infractiongroup', array('pointlevel' => '0'));
		}

		$assertor->update('infractiongroup',
			array(
				'pointlevel' => $pointlevel,
				'usergroupid' => $usergroupid,
				'orusergroupid' => $orusergroupid,
				'override' => intval($override)
			),
			array(
				array('field' => 'infractiongroupid', 'value' => $infractiongroupid, 'operator' => vB_dB_Query::OPERATOR_EQ)
			)
		);

		require_once(DIR . '/includes/functions_infractions.php');
		check_infraction_group_change(
			$orusergroupid,
			$pointlevel,
			$usergroupid
		);
		return $infractiongroupid;
 	}

 	function delete($infractionid)
 	{
 		if (!$this->usercontext->hasPermission('genericpermissions','canreverseinfraction' ))
 		{
 			throw new Exception('no_permission');
 		}

 		if (empty($infractionid))
 		{
 			throw new Exception('incomplete_data');
 		}
 		$infraction = $this->assertor->getRow('infraction', array('infractionid' => $infractionid));

		if (empty($infraction) OR !empty($infraction['errors']))
 		{
			throw new Exception('incorrect_data');
		}

 		$dm = new vB_DataManager_Infraction(vB_DataManager_Constants::ERRTYPE_STANDARD);
 		$dm->set_existing($infraction);
 		$dm->set('infractionid', $infractionid);
 		$dm->set_condition(array(array('field' => 'infractionid', 'value' => $infractionid, 'operator' => vB_dB_Query::OPERATOR_EQ)));
 		return $dm->delete();
 	}

	function buildUserInfractions($points, $infractions, $warnings)
	{
		$warningsql = array();
		$infractionsql = array();
		$ipointssql = array();
		$querysql = array();
		$userids = array();

		// ############################ WARNINGS #################################
		$updates = array();

		foreach($warnings AS $userid => $warning)
		{
			if (!isset($updates[$userid]))
			{
				$updates[$userid] = array('warnings' => 0,
				'infractions' => 0,
				'points' => 0);
			}
			$updates[$userid]['warnings'] += $warning;
		}

		// ############################ INFRACTIONS ##############################
		foreach($infractions AS $userid => $infraction)
		{
			if (!isset($updates[$userid]))
			{
				$updates[$userid] = array('warnings' =>array(),
				'infractions' => array(),
				'points' => array());
			}

			$updates[$userid]['infractions'] += $infraction;
		}

		// ############################ POINTS ###################################
		foreach($points AS $userid => $point)
		{
			if (!isset($updates[$userid]))
			{
				$updates[$userid] = array('warnings' =>array(),
				'infractions' => array(),
				'points' => array());
			}

			$updates[$userid]['points'][] = $point;
		}

		foreach ($updates as $userid => $update)
		{
			if (!empty($update['warnings']) OR !empty($update['infractions'])
				OR !empty($update['points']))
			{
				$data = array_merge(array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'userid' => $userid), $update);
				$this->assertor->assertQuery('userInfractions', $data);
			}
		}
	}


	/**
	 * Builds infraction groups for users
	 *
	 * @param	array	User IDs to build
	 *
	 */
	function build_infractiongroupids($userids)
	{
		static $infractiongroups = array(), $beenhere = false;

		if (!$beenhere)
		{
			$beenhere = true;

			$groups = $this->assertor->assertQuery('infractiongroup', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT),
				'pointlevel');

			if ($groups->valid())
			{
				$group = $groups->current();
				while ($groups->valid())
				{
					$infractiongroups["$group[usergroupid]"]["$group[pointlevel]"][] = array(
						'orusergroupid' => $group['orusergroupid'],
						'override'      => $group['override'],
					);
					$group =  $groups->next();
				}
			}
		}
		$users = $this->assertor->assertQuery('user', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'userid' => $userids));


		if ($users->valid())
		{
			$user = $users->current();
			while ($users->valid())
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
				$user = $users->next();
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
		static $cache;

		if (!is_array($data))
		{
			$data = array();
		}

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
		$users = array();
		if ($point_level === null)
		{
			$users = $this->assertor->getRows(array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'userid' => $override_groupid));
		}
		else if ($applies_groupid > 0 )
		{
			$users = $this->assertor->getRows(array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'userid' => $override_groupid,
			'point_level' => $point_level, 'applies_groupid' => $applies_groupid));
		}
		else
		{
			$users = $this->assertor->getRows(array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'userid' => $override_groupid,
			'point_level' => $point_level));
		}

		if ($users)
		{
			build_infractiongroupids($users);
		}
	}


}
