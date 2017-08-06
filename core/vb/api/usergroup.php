<?php if (!defined('VB_ENTRY')) die('Access denied.');
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
 * vB_Api_UserGroup
 *
 * @package vBApi
 * @access public
 */
class vB_Api_UserGroup extends vB_Api
{
	const UNREGISTERED_SYSGROUPID = 1;
	const REGISTERED_SYSGROUPID = 2;
	const ADMINISTRATOR = 6;
	const SUPER_MODERATOR = 5;

	// these are used for blogs
	const CHANNEL_OWNER_SYSGROUPID = 9;
	const CHANNEL_MODERATOR_SYSGROUPID = 10;
	const CHANNEL_MEMBER_SYSGROUPID = 11;

	// these are used for
	// @TODO we already removed usages of this in the system but still some references in upgrader. Need to figure it out what to do on those upgrade steps (a28 a29).
	const SG_OWNER_SYSGROUPID = 12;
	const SG_MODERATOR_SYSGROUPID = 13;
	const SG_MEMBER_SYSGROUPID = 14;

	protected $usergroupcache = array();
	protected $sortedList = array();
	protected $privateGroups= array();
	protected function __construct()
	{
		parent::__construct();
		$this->usergroupcache = vB::getDatastore()->get_value('usergroupcache');
		$this->sortUserGroupList();
		$this->privateGroups = array(self::CHANNEL_OWNER_SYSGROUPID, self::CHANNEL_MODERATOR_SYSGROUPID, self::CHANNEL_MEMBER_SYSGROUPID);
	}

	/**
	 * Returns a list of all user groups.
	 *
	 * @return	array
	 */
	public function fetchUsergroupList($flushcache = false)
	{
		if ($flushcache)
		{
			$this->usergroupcache = vB::getDatastore()->getValue('usergroupcache');
			$this->sortUserGroupList();
		}
		return $this->sortedList;
	}

	/** This sorts the usergroupcache
	*
	**/
	protected function sortUserGroupList()
	{
		$nameList = array();
		foreach ($this->usergroupcache AS $key => $group)
		{
			$nameList[$group['title']] = $key;
		}

		ksort($nameList);
		foreach($nameList AS $key => $userGroupKey)
		{
			$this->sortedList[] = $this->usergroupcache[$userGroupKey];
		}
	}

	/** Fetch the special groups. Used by permissions check. Each is a systemgroupid in the usergroups table
	 *
	 * 	@return		mixed	array of integer
	 *
	 */
	public function fetchPrivateGroups()
	{
		return $this->privateGroups;
	}

	/**
	 * Fetch usergroup information by its ID
	 *
	 * @param int $usergroupid Usergroup ID
	 * @return array Usergroup information
	 */
	public function fetchUsergroupByID($usergroupid)
	{
		if (isset($this->usergroupcache[$usergroupid]))
		{
			return $this->usergroupcache[$usergroupid];
		}
		else
		{
			throw new vb_Exception_Api('invalidid', array('usergroupid'));
		}
	}


	/**
	 * Fetch usergroup information by its SystemID
	 *
	 * @param int $usergroupid Usergroup ID
	 * @return array Usergroup information
	 */
	public function fetchUsergroupBySystemID($systemgroupid)
	{
		foreach ($this->usergroupcache AS $usergroup)
		{
			if ($usergroup['systemgroupid'] == $systemgroupid)
			{
				return $usergroup;
			}
		}

		//if we got here, the request is invalid
		throw new vb_Exception_Api('invalidid', array('$systemgroupid'));
	}

	/**
	 * Fetch default usergroup data for adding or editing new usergroup
	 *
	 * @param int $usergroupid If present, the data will be copied from this usergroup
	 * @return array Default usergroup data. It contains four sub-arrays:
	 *               'usergroup' - Basic usergroup information
	 *               'ugarr' - usergroups to be used for 'Create Forum Permissions Based off of Usergroup'
	 *               'ug_bitfield' - Usergroup bitfield
	 *               'groupinfo' - Usergroup permission information
	 */
	public function fetchDefaultData($usergroupid = 0)
	{
		$this->checkHasAdminPermission('canadminpermissions');

		$bf_ugp = vB::getDatastore()->get_value('bf_ugp');

		require_once(DIR . '/includes/class_bitfield_builder.php');
		$myobj =& vB_Bitfield_Builder::init();

		if ($usergroupid)
		{
			$usergroup = vB::getDbAssertor()->getRow('usergroup', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_TABLE,
				vB_dB_Query::CONDITIONS_KEY => array(
					'usergroupid' => $usergroupid
				)
			));

			$ug_bitfield = array();
			foreach($bf_ugp AS $permissiongroup => $fields)
			{
				$ug_bitfield["$permissiongroup"] = convert_bits_to_array($usergroup["$permissiongroup"], $fields);
			}
		}
		else
		{
			$ug_bitfield = array(
				'genericoptions' => array('showgroup' => 1, 'showeditedby' => 1, 'isnotbannedgroup' => 1),
				'forumpermissions' => array('canview' => 1, 'canviewothers' => 1, 'cangetattachment' => 1,
				'cansearch' => 1, 'canthreadrate' => 1, 'canpostattachment' => 1, 'canpostpoll' => 1, 'canvote' => 1, 'canviewthreads' => 1),
				'wolpermissions' => array('canwhosonline' => 1),
				'genericpermissions' => array('canviewmembers' => 1, 'canmodifyprofile' => 1, 'canseeprofilepic' => 1, 'canusesignature' => 1, 'cannegativerep' => 1, 'canuserep' => 1, 'cansearchft_nl' => 1)
			);
			// set default numeric permissions
			$usergroup = array(
				'pmquota' => 0, 'pmsendmax' => 5, 'attachlimit' => 1000000,
				'avatarmaxwidth' => 200, 'avatarmaxheight' => 200, 'avatarmaxsize' => 20000,
				'profilepicmaxwidth' => 100, 'profilepicmaxheight' => 100, 'profilepicmaxsize' => 25000, 'sigmaxsizebbcode' => 7
			);
		}

		$permgroups = vB::getDbAssertor()->assertQuery('usergroup_fetchperms', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));

		$ugarr = array();
		foreach ($permgroups as $group)
		{
			$ugarr["$group[usergroupid]"] = $group['title'];
		}

		foreach ((array)$myobj->data['ugp'] AS $grouptitle => $perms)
		{
			if ($grouptitle == 'createpermissions')
			{
				continue;
			}
			foreach ($perms AS $permtitle => $permvalue)
			{
				if (empty($permvalue['group']))
				{
					continue;
				}
				$groupinfo["$permvalue[group]"]["$permtitle"] = array('phrase' => $permvalue['phrase'], 'value' => $permvalue['value'], 'parentgroup' => $grouptitle);
				if ($permvalue['intperm'])
				{
					$groupinfo["$permvalue[group]"]["$permtitle"]['intperm'] = true;
				}
				if (!empty($myobj->data['layout']["$permvalue[group]"]['ignoregroups']))
				{
					$groupinfo["$permvalue[group]"]['ignoregroups'] = $myobj->data['layout']["$permvalue[group]"]['ignoregroups'];
				}
				if (!empty($permvalue['ignoregroups']))
				{
					$groupinfo["$permvalue[group]"]["$permtitle"]['ignoregroups'] = $permvalue['ignoregroups'];
				}
				if (!empty($permvalue['options']))
				{
					$groupinfo["$permvalue[group]"]["$permtitle"]['options'] = $permvalue['options'];
				}
			}
		}

		return array(
			'usergroup' => $usergroup,
			'ug_bitfield' => $ug_bitfield,
			'ugarr' => $ugarr,
			'groupinfo' => $groupinfo,
		);
	}

	/**
	 * Insert a new usergroup or update an existing usergroup
	 *
	 * @param array $usergroup Usergroup information to be inserted or updated
	 * @param int $ugid_base Usergroup ID. New inserted usergroup's forum permission will based on this usergroup.
	 * @param int $usergroupid when updating an existing usergroup, pass usergroup ID as this parameter
	 * @return int New or existing usergroup ID
	 */
	public function save($usergroup, $ugid_base = 0, $usergroupid = 0)
	{
		$this->checkHasAdminPermission('canadminpermissions');

		$bf_ugp = vB::getDatastore()->get_value('bf_ugp');
		$bf_ugp_adminpermissions = vB::getDatastore()->get_value('bf_ugp_adminpermissions');
		$bf_ugp_genericpermissions = vB::getDatastore()->get_value('bf_ugp_genericpermissions');
		$bf_ugp_genericoptions = vB::getDatastore()->get_value('bf_ugp_genericoptions');
		$bf_misc_useroptions = vB::getDatastore()->get_value('bf_misc_useroptions');
		$usergroupcache = vB::getDatastore()->get_value('usergroupcache');
		$bf_misc_prefixoptions = vB::getDatastore()->get_value('bf_misc_prefixoptions');

		// create bitfield values
		require_once(DIR . '/includes/functions_misc.php');
		foreach($bf_ugp AS $permissiongroup => $fields)
		{
			if ($permissiongroup == 'createpermissions' OR $permissiongroup == 'forumpermissions2')
			{
				continue;
			}
			$usergroup["$permissiongroup"] = convert_array_to_bits($usergroup["$permissiongroup"], $fields, 1);
		}

		if (!empty($usergroupid))
		{
			// update
			if (!($usergroup['adminpermissions'] & $bf_ugp_adminpermissions['cancontrolpanel']))
			{ // check that not removing last admin group
				$checkadmin = vB::getDbAssertor()->getField('usergroup_checkadmin', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'cancontrolpanel' => $bf_ugp_adminpermissions['cancontrolpanel'],
					'usergroupid' => $usergroupid,
				));
				if ($usergroupid == 6)
				{ // stop them turning no control panel for usergroup 6, seems the most sensible thing
					throw new vB_Exception_Api('invalid_usergroup_specified');
				}
				if (!$checkadmin)
				{
					throw new vB_Exception_Api('cant_delete_last_admin_group');
				}
			}

			$data = array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				vB_dB_Query::CONDITIONS_KEY => array('usergroupid' => $usergroupid)
			);
			$data = array_merge($data, $usergroup);
			vB::getDbAssertor()->assertQuery('usergroup', $data);

			if (!($usergroup['genericpermissions'] & $bf_ugp_genericpermissions['caninvisible']))
			{
				if (!($usergroup['genericoptions'] & $bf_ugp_genericoptions['allowmembergroups']))
				{
					// make the users in this group visible
					vB::getDbAssertor()->assertQuery('usergroup_makeuservisible', array(
						'invisible' => $bf_misc_useroptions['invisible'],
						'usergroupid' => $usergroupid,
					));
				}
				else
				{
					// find all groups allowed to be invisible - don't change people with those as secondary groups
					vB::getDbAssertor()->assertQuery('updateInvisible', array(
						'caninvisible' => $bf_ugp_genericpermissions['caninvisible'],
						'invisible' => $bf_misc_useroptions['invisible'],
						'usergroupid' => $usergroupid,
					));
				}
			}

			if ($usergroup['adminpermissions'] & $bf_ugp_adminpermissions['cancontrolpanel'])
			{
				$ausers = vB::getDbAssertor()->assertQuery('usergroup_fetchausers', array(
					'usergroupid' => $usergroupid,
				));
				foreach ($ausers as $auser)
				{
					$userids[] = $auser['userid'];
				}

				if (!empty($userids))
				{
					foreach ($userids AS $userid)
					{
						$admindm =& datamanager_init('Admin', $vbulletin, ERRTYPE_SILENT);
						$admindm->set('userid', $userid);
						$admindm->save();
						unset($admindm);
					}
				}
			}
			else if ($usergroupcache["{$usergroupid}"]['adminpermissions'] & $bf_ugp_adminpermissions['cancontrolpanel'])
			{
				// lets find admin usergroupids
				$ausergroupids = array();
				$usergroupcache["{$usergroupid}"]['adminpermissions'] = $usergroup['adminpermissions'];
				foreach ($usergroupcache AS $ausergroupid => $ausergroup)
				{
					if ($ausergroup['adminpermissions'] & $bf_ugp_adminpermissions['cancontrolpanel'])
					{
						$ausergroupids[] = $ausergroupid;
					}
				}
				$ausergroupids = implode(',', $ausergroupids);

				$ausers = vB::getDbAssertor()->assertQuery('usergroup_fetchausers2', array(
					'ausergroupids' => $ausergroupids,
					'usergroupid' => $usergroupid,
				));

				foreach ($ausers as $auser)
				{
					$userids[] = $auser['userid'];
				}

				if (!empty($userids))
				{
					foreach ($userids AS $userid)
					{
						$info = array('userid' => $userid);

						$admindm =& datamanager_init('Admin', $vbulletin, ERRTYPE_ARRAY);
						$admindm->set_existing($info);
						$admindm->delete();
						unset($admindm);
					}
				}
			}

			vB_Cache::instance()->event('perms_changed');
			vB::getUserContext()->clearChannelPermissions($usergroupid);
		}
		else
		{
		// insert
			/*insert query*/
			$newugid = vB::getDbAssertor()->insert('usergroup', $usergroup);

			if ($ugid_base <= 0)
			{
				// use usergroup registered as default
				foreach($usergroupcache AS $ausergroup)
				{
					if ($ausergroup['systemgroupid'] == self::REGISTERED_SYSGROUPID)
					{
						$ugid_base = $ausergroup['usergroupid'];
					}
				}
			}

			if ($ugid_base > 0)
			{
				$fperms = vB::getDbAssertor()->assertQuery('vBForum:forumpermission', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'usergroupid' => $ugid_base,
				));
				foreach ($fperms as $fperm)
				{
					unset($fperm['forumpermissionid']);
					$fperm['usergroupid'] = $newugid;
					/*insert query*/
					$data = array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
					);
					$data += $fperm;
					vB::getDbAssertor()->assertQuery('vBForum:forumpermission', $data);
				}

				$cperms = vB::getDbAssertor()->assertQuery('vBForum:calendarpermission', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'usergroupid' => $ugid_base,
				));
				foreach ($cperms as $cperm)
				{
					unset($cperm['calendarpermissionid']);
					$cperm['usergroupid'] = $newugid;
					/*insert query*/
					$data = array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
					);
					$data += $cperm;
					vB::getDbAssertor()->assertQuery('vBForum:calendarpermission', $data);
				}

				$perms = vB::getDbAssertor()->assertQuery('vBForum:permission', array('groupid' => $ugid_base));
				foreach ($perms as $perm)
				{
					unset($perm['permissionid']);
					$perm['groupid'] = $newugid;
					vB::getDbAssertor()->insert('vBForum:permission', $perm);
				}

				vB::getUserContext()->clearChannelPermissions();
			}

			vB::getDbAssertor()->assertQuery('usergroup_insertprefixpermission', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'newugid' => $newugid,
				'deny_by_default' => $bf_misc_prefixoptions['deny_by_default'],
			));
		}
		vB::getUserContext()->rebuildGroupAccess();

		$markups = vB::getDbAssertor()->getRows('usergroup_fetchmarkups', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
		));
		$usergroupmarkup = array();
		foreach ($markups as $markup)
		{
			$usergroupmarkup["{$markup['usergroupid']}"]['opentag'] = $markup['opentag'];
			$usergroupmarkup["{$markup['usergroupid']}"]['closetag'] = $markup['closetag'];
		}

		require_once(DIR . '/includes/adminfunctions.php');
		require_once(DIR . '/includes/functions_databuild.php');
		build_channel_permissions();
		build_birthdays();

		// could be changing sig perms -- this is unscientific, but empty the sig cache
		vB::getDbAssertor()->assertQuery('truncateTable', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD, 'table' => 'sigparsed'));

		if ($newugid)
		{
			return $newugid;
		}
		else
		{
			return $usergroupid;
		}

	}

	/**
	 * Delete an usergroup
	 *
	 * @param int $usergroupid Usergroup ID to be deleted
	 * @return void
	 */
	public function delete($usergroupid)
	{
		$this->checkHasAdminPermission('canadminpermissions');

		$db = vB::getDbAssertor();
		// update users who are in this usergroup to be in the registered usergroup
		$db->assertQuery('user', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'usergroupid' => 2,
			vB_dB_Query::CONDITIONS_KEY => array(
				'usergroupid' => $usergroupid
			),
		));
		$db->assertQuery('user', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'displaygroupid' => 0,
			vB_dB_Query::CONDITIONS_KEY => array(
				'displaygroupid' => $usergroupid
			),
		));
		$db->assertQuery('useractivation', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'usergroupid' => 2,
			vB_dB_Query::CONDITIONS_KEY => array(
				'usergroupid' => $usergroupid
			),
		));
		$db->assertQuery('vBForum:subscription', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'nusergroupid' => -1,
			vB_dB_Query::CONDITIONS_KEY => array(
				'nusergroupid' => $usergroupid
			),
		));
		$db->assertQuery('vBForum:subscriptionlog', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'pusergroupid' => -1,
			vB_dB_Query::CONDITIONS_KEY => array(
				'pusergroupid' => $usergroupid
			),
		));
		/** @todo rewise this query - it's currently invalid **/
//		$db->assertQuery('subscriptionlog', array(
//			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
//			'displaygroupid' => 0,
//			vB_dB_Query::CONDITIONS_KEY => array(
//				'displaygroupid' => $usergroupid
//			),
//		));

		// now get on with deleting stuff...
		$db->assertQuery('usergroup', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			vB_dB_Query::CONDITIONS_KEY => array(
				'usergroupid' => $usergroupid
			),
		));
		$db->assertQuery('vBForum:forumpermission', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			vB_dB_Query::CONDITIONS_KEY => array(
				'usergroupid' => $usergroupid
			),
		));
		$db->assertQuery('vBForum:permission', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				vB_dB_Query::CONDITIONS_KEY => array(
						'groupid' => $usergroupid
				),
		));

		$db->assertQuery('vBForum:ranks', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			vB_dB_Query::CONDITIONS_KEY => array(
				'usergroupid' => $usergroupid
			),
		));
		$db->assertQuery('vBForum:usergrouprequest', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			vB_dB_Query::CONDITIONS_KEY => array(
				'usergroupid' => $usergroupid
			),
		));
		$db->assertQuery('vBForum:userpromotion', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			vB_dB_Query::CONDITIONS_KEY => array(
				'usergroupid' => $usergroupid
			),
		));
		$db->assertQuery('vBForum:deleteUserPromotion', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'usergroupid' => $usergroupid
		));
		$db->assertQuery('vBForum:imagecategorypermission', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			vB_dB_Query::CONDITIONS_KEY => array(
				'usergroupid' => $usergroupid
			),
		));
		$db->assertQuery('vBForum:attachmentpermission', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			vB_dB_Query::CONDITIONS_KEY => array(
				'usergroupid' => $usergroupid
			),
		));
		$db->assertQuery('vBForum:prefixpermission', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			vB_dB_Query::CONDITIONS_KEY => array(
				'usergroupid' => $usergroupid
			),
		));

		require_once(DIR . '/includes/functions_ranks.php');
		build_ranks();
		require_once(DIR . '/includes/adminfunctions.php');
		build_channel_permissions();

		require_once(DIR . '/includes/adminfunctions_attachment.php');
		build_attachment_permissions();

		// remove this group from users who have this group as a membergroup
		$updateusers = array();
		$casesql = '';

		$users = $db->getRows('usergroup_fetchmemberstoremove', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'usergroupid' => $usergroupid,
		));
		if (count($users))
		{
			$db->assertQuery('updateMemberForDeletedUsergroup', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'users' => $users,
				'usergroupid' => $usergroupid,
			));
		}

		vB::getUserContext()->rebuildGroupAccess();
	}

	/**
	 * Remove usergroup leader from an usergroup
	 *
	 * @param  $usergroupleaderid Leader's user ID to be removed
	 * @return void
	 */
	public function removeLeader($usergroupleaderid)
	{
		$this->checkHasAdminPermission('canadminpermissions');

		vB::getDbAssertor()->assertQuery('usergroupleader', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			vB_dB_Query::CONDITIONS_KEY => array(
				'usergroupleaderid' => $usergroupleaderid
			),
		));
	}

	/**
	 * Add a leader for an usergroup
	 *
	 * @param int $usergroupid
	 * @param int $userid
	 * @return int New usergroupleader ID
	 */
	public function addLeader($usergroupid, $userid)
	{
		$this->checkHasAdminPermission('canadminpermissions');

		require_once(DIR . '/includes/adminfunctions.php');

		$usergroupid = intval($usergroupid);
		$userid = intval($userid);
		$vbulletin = vB::get_registry();
		if (
			$usergroup = vB::getDbAssertor()->getRow('usergroup', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'usergroupid', 'value' => $usergroupid, 'operator' => 'EQ'),
					array('field' => 'ispublicgroup', 'value' => 1, 'operator' => 'EQ'),
					array('field' => 'usergroupid', 'value' => 7, 'operator' => 'GT'),
				)
			))
		)
		{
			if (
				$user = vB::getDbAssertor()->getRow('user', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'userid' => $userid,
				))
			)
			{
				if (is_unalterable_user($user['userid']))
				{
					throw new vB_Exception_Api('user_is_protected_from_alteration_by_undeletableusers_var');
				}

				if (
					$preexists = vB::getDbAssertor()->getRow('user', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						vB_dB_Query::CONDITIONS_KEY => array(
							array('field' => 'usergroupid', 'value' => $usergroupid, 'operator' => vB_dB_Query::OPERATOR_EQ),
							array('field' => 'userid', 'value' => $user['userid'], 'operator' => vB_dB_Query::OPERATOR_EQ)
						)
					))
				)
				{
					throw new vB_Exception_Api('invalid_usergroup_leader_specified');
				}

				// update leader's member groups if necessary
				if (strpos(",$user[membergroupids],", "," . $usergroupid . ",") === false AND $user['usergroupid'] != $usergroupid)
				{
					if (empty($user['membergroupids']))
					{
						$membergroups = $usergroupid;
					}
					else
					{
						$membergroups = "$user[membergroupids]," . $usergroupid;
					}

					$userdm = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);
					$userdm->set_existing($user);
					$userdm->set('membergroupids', $membergroups);
					$userdm->save();
					unset($userdm);
				}

				// insert into usergroupleader table
				/*insert query*/
				return vB::getDbAssertor()->assertQuery('usergroupleader', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
					'userid' => $user['userid'],
					'usergroupid' => $usergroupid,
				));

			}
			else
			{
				throw new vB_Exception_Api('invalid_user_specified');
			}
		}
		else
		{
			throw new vB_Exception_Api('cant_add_usergroup_leader');
		}

	}

	/**
	 * Fetch a list of usergroup promotions
	 *
	 * @param int $usergroupid Fetch promotions for only this usergroup
	 * @return array Promotions information
	 */
	public function fetchPromotions($usergroupid = 0)
	{
		$this->checkHasAdminPermission('canadminpermissions');

		$promotions = array();
		$getpromos = vB::getDbAssertor()->assertQuery('fetchPromotions', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'usergroupid' => intval($usergroupid)
		));
		foreach ($getpromos as $promotion)
		{
			$promotions["$promotion[usergroupid]"][] = $promotion;
		}

		return $promotions;

	}

	/**
	 * Insert a new usergroup promotion or update an existing one
	 *
	 * @param array $promotion Promotion information
	 * @param int $usergroupid
	 * @param int $userpromotionid Existing Usergroup promotion ID to be updated
	 * @return int new or existing userpromotion ID
	 */
	public function savePromotion($promotion, $usergroupid, $userpromotionid = 0)
	{
		$this->checkHasAdminPermission('canadminpermissions');

		$usergroupid = intval($usergroupid);
		$userpromotionid = intval($userpromotionid);

		if ($promotion['joinusergroupid'] == -1)
		{
			throw new vB_Exception_Api('invalid_usergroup_specified');
		}

		if ($promotion['reputationtype'] AND $promotion['strategy'] <= 16)
		{
			$promotion['strategy'] += 8;
		}
		unset($promotion['reputationtype']);

		if (!empty($userpromotionid))
		{ // update
			if ($usergroupid == $promotion['joinusergroupid'])
			{
				throw new vB_Exception_Api('promotion_join_same_group');
			}
			$data = array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				vB_dB_Query::CONDITIONS_KEY => array(
					'userpromotionid' => $userpromotionid,
				)
			);
			$data += $promotion;
			vB::getDbAssertor()->assertQuery('userpromotion', $data);

			return $userpromotionid;
		}
		else
		{ // insert
			$usergroupid = $promotion['usergroupid'];
			if ($usergroupid == $promotion['joinusergroupid'])
			{
				throw new vB_Exception_Api('promotion_join_same_group');
			}
			/*insert query*/
			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT);
			$data += $promotion;
			$promotion_id = vB::getDbAssertor()->assertQuery('userpromotion', $data);
			return $promotion_id[0];
		}

	}

	/**
	 * Delete an usergroup promotion
	 *
	 * @param  $userpromotionid
	 * @return void
	 */
	public function deletePromotion($userpromotionid)
	{
		$this->checkHasAdminPermission('canadminpermissions');

		vB::getDbAssertor()->assertQuery('userpromotion', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			vB_dB_Query::CONDITIONS_KEY => array(
				'userpromotionid' => intval($userpromotionid)
			),
		));
	}

	/**
	 * Fetch a list of usergroup join requests
	 * @param int $usergroupid Usergroup ID. If 0, this method will return a list of usergroups
	 *                         which have join requests.
	 *
	 * @return array If $usergroupid is 0, it will return a list of usergroups which have join requests.
	 *               If $usergroupid is not 0, it will return an array of join requests.
	 *               If the return is an empty array, it means no join requests for all usergroups (usergroupid = 0)
	 *                  or for the specified usergroup ($usergroupid != 0)
	 */
	public function fetchJoinRequests($usergroupid = 0)
	{
		if (!$usergroupid)
		{
			$this->checkHasAdminPermission('canadminpermissions');
		}

		// first query groups that have join requests
		$getusergroups = vB::getDbAssertor()->getRows('usergroup_fetchwithjoinrequests', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));
		if (count($getusergroups) == 0)
		{
			// there are no join requests
			return array();
		}

		$usergroupcache = vB::getDatastore()->get_value('usergroupcache');

		// if we got this far we know that we have at least one group with some requests in it
		$usergroups = array();
		$badgroups = array();

		foreach ($getusergroups as $getusergroup)
		{
			$ugid =& $getusergroup['usergroupid'];

			if (isset($usergroupcache["$ugid"]))
			{
				$usergroupcache["$ugid"]['joinrequests'] = $getusergroup['requests'];
				if ($usergroupcache["$ugid"]['ispublicgroup'])
				{
					$goodgroups["$ugid"]['title'] = $usergroupcache["$ugid"]['title'];
					$goodgroups["$ugid"]['joinrequests'] = $usergroupcache["$ugid"]['joinrequests'];
				}
			}
			else
			{
				$badgroups[] = $getusergroup['usergroupid'];
			}
		}
		unset($getusergroup);

		// if there are any invalid requests, zap them now
		if (!empty($badgroups))
		{
			$badgroups = implode(', ', $badgroups);
			vB::getDbAssertor()->assertQuery('usergrouprequest', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				vB_dB_Query::CONDITIONS_KEY => array(
					'usergroupid' => $badgroups
				),
			));
		}

		// now if we are being asked to display a particular usergroup, do so.
		if ($usergroupid)
		{
			// check this is a valid usergroup
			if (!is_array($usergroupcache["{$usergroupid}"]))
			{
				throw new vB_Exception_Api('invalid_usergroup_specified');
			}

			// check that this usergroup has some join requests
			if ($usergroupcache["{$usergroupid}"]['joinrequests'])
			{

				// everything seems okay, so make a total record for this usergroup
				$usergroup =& $usergroupcache["{$usergroupid}"];

				// query the requests for this usergroup
				$requests = vB::getDbAssertor()->getRows('usergroup_fetchjoinrequests', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'usergroupid' => $usergroupid,
				));

				return (array)$requests;
			}
			else
			{
				return array();
			}

		}
		else
		{
			return $goodgroups;
		}
	}

	/**
	 * Process usergroup join requests
	 *
	 * @param $usergroupid Usergroup ID that the requests are in
	 * @param array $request Join requests to be processed
	 * @return void
	 */
	public function processJoinRequests($usergroupid, $request)
	{
		if (empty($request))
		{
			throw new vB_Exception_Api('no_matches_found_gerror');
		}
		$usergroupcache = vB::getDatastore()->get_value('usergroupcache');

		// check that we are working with a valid usergroup
		if (!is_array($usergroupcache["{$usergroupid}"]))
		{
			throw new vB_Exception_Api('invalid_usergroup_specified');
		}
		else
		{
			$usergroupname = htmlspecialchars_uni($usergroupcache["{$usergroupid}"]['title']);
		}

		$auth = array();

		// sort the requests according to the action specified
		foreach($request AS $requestid => $action)
		{
			switch($action)
			{
				case -1:	// this request will be ignored
					unset($request["$requestid"]);
					break;

				case  1:	// this request will be authorized
					$auth[] = intval($requestid);
					break;

				case  0:	// this request will be denied
					// do nothing - this request will be zapped at the end of this segment
					break;
			}
		}

		// if we have any accepted requests, make sure they are valid
		if (!empty($auth))
		{
			$users = vB::getDbAssertor()->assertQuery('usergroup_fetchjoinrequests2', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'auth' => $auth,
			));
			$auth = array();
			foreach ($users as $user)
			{
				$auth[] = $user['userid'];
			}

			// check that we STILL have some valid requests
			if (!empty($auth))
			{
				vB::getDbAssertor()->assertQuery('usergroup_updatemembergroup', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'usergroupid' => $usergroupid,
					'auth' => $auth,
				));
			}
		}

		// delete processed join requests
		if (!empty($request))
		{
			$request = array_map('intval', array_keys($request));
			vB::getDbAssertor()->assertQuery('usergrouprequest', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'usergrouprequestid' => $request
			));
		}
	}

	/**
	 * Fetch member groups that are joined by an User and able to be joined by the user
	 *
	 * @param  $userid User ID
	 * @return array Usergroups information
	 *               'checked' => user chosen usergroup to be identified as a member of,
	 *               'displaygroups' => Display groups list
	 *               'joinrequests' => Usergroup join requests,
	 *               'membergroups' => usergroups the user is a member of,
	 *               'nonmembergroups' => usergroups the user is not a member of but be able to join,
	 *               'primarygroup' => user's primary usergroup,
	 *               'primarygroupid' => the ID of user's primary usergroup,
	 */
	public function fetchMembergroups($userid)
	{
		$usergroupcache = vB::getDatastore()->get_value('usergroupcache');
		$userinfo = vB_Api::instanceInternal('user')->fetchUserinfo($userid);

		// check to see if there are usergroups available
		$haspublicgroups = false;
		foreach ($usergroupcache AS $usergroup)
		{
			if ($usergroup['ispublicgroup'] or $usergroup['canoverride'])
			{
				$haspublicgroups = true;
				break;
			}
		}

		if (!$haspublicgroups)
		{
			throw new vB_Exception_Api('no_public_usergroups');
		}
		else
		{
			$membergroups = fetch_membergroupids_array($userinfo);

			// query user's usertitle based on posts ladder
			$usertitle = vB::getDbAssertor()->getRow('usergroup_fetchusertitle', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'posts' => $userinfo['posts'],
			));

			// get array of all usergroup leaders
			$bbuserleader = array();
			$leaders = array();
			$groupleaders = vB::getDbAssertor()->getRows('usergroup_fetchallleaders', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));
			foreach ($groupleaders as $groupleader)
			{
				if ($groupleader['userid'] == $userinfo['userid'])
				{
					$bbuserleader[] = $groupleader['usergroupid'];
				}
				$leaders["$groupleader[usergroupid]"]["$groupleader[userid]"] = $groupleader;
			}
			unset($groupleader);

			// notify about new join requests if user is a group leader
			$joinrequests = array();
			if (!empty($bbuserleader))
			{
				$joinrequests = vB::getDbAssertor()->getRows('usergroup_fetchjoinrequests3', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'usergroupids' => $bbuserleader,
				));
			}

			// get usergroups
			$groups = array();
			$couldrequest = array();
			foreach ($usergroupcache AS $usergroupid => $usergroup)
			{
				if ($usergroup['usertitle'] == '')
				{
					$usergroup['usertitle'] = $usertitle['title'];
				}
				if (in_array($usergroupid, $membergroups))
				{
					$groups['member']["$usergroupid"] = $usergroup;
				}
				else if ($usergroup['ispublicgroup'])
				{
					$groups['notmember']["$usergroupid"] = $usergroup;
					$couldrequest[] = $usergroupid;
				}
			}

			// do groups user is NOT a member of
			$nonmembergroups = array();
			if (is_array($groups['notmember']))
			{
				// get array of join requests for this user
				$requests = array();
				$joinrequests = vB::getDbAssertor()->assertQuery('usergrouprequest', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'userid' => $userinfo['userid'],
					'usergroupid' => $couldrequest,
				));
				foreach ($joinrequests as $joinrequest)
				{
					$requests["$joinrequest[usergroupid]"] = $joinrequest;
				}
				unset($joinrequest);

				foreach ($groups['notmember'] AS $usergroupid => $usergroup)
				{
					$joinrequested = 0;
					$joinrequest = array();
					if (is_array($leaders["$usergroupid"]))
					{
						$_groupleaders = array();
						foreach ($leaders["$usergroupid"] AS $leader)
						{
							$groupleaders[] = $leader;
						}
						$ismoderated = 1;
						if (isset($requests["$usergroupid"]))
						{
							$joinrequest = $requests["$usergroupid"];
							$joinrequested = 1;
						}
					}
					else
					{
						$ismoderated = 0;
						$groupleaders = '';
					}

					$nonmembergroups[$usergroupid] = array(
						'groupleaders' => $groupleaders,
						'ismoderated' => $ismoderated,
						'joinrequest' => $joinrequest,
						'joinrequested' => $joinrequested,
						'usergroup' => $usergroup,
					);
				}
			}

			// set primary group info
			$primarygroupid = $userinfo['usergroupid'];
			$primarygroup = $groups['member']["{$userinfo['usergroupid']}"];

			// do groups user IS a member of
			$membergroups = array();
			foreach ($groups['member'] AS $usergroupid => $usergroup)
			{
				if ($usergroupid != $userinfo['usergroupid'] AND $usergroup['ispublicgroup'])
				{
					if ($usergroup['usertitle'] == '')
					{
						$usergroup['usertitle'] = $usertitle['title'];
					}

					$membergroups[$usergroupid] = $usergroup;
				}
			}

			// do groups user could use as display group
			$checked = array();
			if ($userinfo['displaygroupid'])
			{
				$checked["{$userinfo['displaygroupid']}"] = 'checked="checked"';
			}
			else
			{
				$checked["{$userinfo['usergroupid']}"] = 'checked="checked"';
			}
			$displaygroups = array();
			foreach ($groups['member'] AS $usergroupid => $usergroup)
			{
				if ($usergroupid != $userinfo['usergroupid'] AND $usergroup['canoverride'])
				{
					$displaygroups[$usergroupid] = array(
						'checked' => $checked,
						'usergroup' => $usergroup,
						'usergroupid' => $usergroupid,
					);
				}
			}

			if (!$joinrequests AND !$nonmembergroups AND !$membergroups AND !$displaygroups)
			{
				throw new vB_Exception_Api('no_public_usergroups');
			}


			return array(
				'checked' => $checked,
				'displaygroups' => $displaygroups,
				'joinrequests' => $joinrequests,
				'membergroups' => $membergroups,
				'nonmembergroups' => $nonmembergroups,
				'primarygroup' => $primarygroup,
				'primarygroupid' => $primarygroupid,
			);
		}

	}

	/**
	 * Insert usergroup join request for an user
	 *
	 * @param int $userid User ID
	 * @param int $usergroupid Usergroup ID to be joined
	 * @param string $reason Reason of join the group
	 * @return void
	 */
	public function insertJoinRequest($userid, $usergroupid, $reason)
	{
		$userid = intval($userid);
		$usergroupid = intval($usergroupid);
		$reason = trim($reason);

		$userinfo = vB_Api::instanceInternal('user')->fetchUserinfo($userid);

		$request = vB::getDbAssertor()->getRow('usergrouprequest', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'userid' => $userinfo['userid'],
			'usergroupid' => $usergroupid,
		));

		if ($request)
		{
			throw new vB_Exception_Api('usergroup_request_exists');
		}

		$request_id = vB::getDbAssertor()->assertQuery('usergrouprequest', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'userid' => $userinfo['userid'],
			'usergroupid' => $usergroupid,
			'reason' => $reason,
			'dateline' => vB::getRequest()->getTimeNow(),
		));

		return $request_id[0];
	}

	/**
	 * Leave a public usergroup
	 *
	 * @param int $userid User ID
	 * @param int $usergroupid Usergroup ID that the user will leave
	 * @return void
	 */
	public function leaveGroup($userid, $usergroupid)
	{
		$userinfo = vB_Api::instanceInternal('user')->fetchUserinfo($userid);

		$membergroups = fetch_membergroupids_array($userinfo);
		$permissions = $userinfo['permissions'];
		$vbulletin = vB::get_registry();
		$usergroupcache = vB::getDatastore()->get_value('usergroupcache');
		$bf_ugp_genericpermissions = vB::getDatastore()->get_value('bf_ugp_genericpermissions');

		if (empty($membergroups))
		{ // check they have membergroups
			throw new vB_Exception_Api('usergroup_cantleave_notmember');
		}
		else if (!in_array($usergroupid, $membergroups))
		{ // check they are a member before leaving
			throw new vB_Exception_Api('usergroup_cantleave_notmember');
		}
		else
		{
			if ($usergroupid == $userinfo['usergroupid'])
			{
				// trying to leave primary usergroup
				eval(standard_error(fetch_error('usergroup_cantleave_primary')));
			}
			else if (
				vB::getDbAssertor()->getRow('usergroupleader', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'usergroupid' => $usergroupid,
					'userid' => $userinfo['usrid']
				))
			)
			{
				// trying to leave a group of which user is a leader
				eval(standard_error(fetch_error('usergroup_cantleave_groupleader')));
			}
			else
			{
				$newmembergroups = array();
				foreach ($membergroups AS $groupid)
				{
					if ($groupid != $userinfo['usergroupid'] AND $groupid != $usergroupid)
					{
						$newmembergroups[] = $groupid;
					}
				}

				// init user data manager
				$userdata = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);
				$userdata->set_existing($userinfo);
				$userdata->set('membergroupids', $newmembergroups);
				if ($userinfo['displaygroupid'] == $usergroupid)
				{
					$userdata->set('displaygroupid', 0);
					$userdata->set_usertitle(
						$userinfo['customtitle'] ? $userinfo['usertitle'] : '',
						false,
						$usergroupcache["{$userinfo['usergroupid']}"],
						($permissions['genericpermissions'] & $bf_ugp_genericpermissions['canusecustomtitle']) ? true : false,
						($permissions['genericpermissions'] & $bf_ugp_genericpermissions['cancontrolpanel']) ? true : false
					);
				}

				$userdata->save();
			}
		}
	}

	/**
	 * Update user's display group
	 *
	 * @param  $userid User ID
	 * @param  $usergroupid Usergroup ID to be used as display group
	 * @return void
	 */
	public function updateDisplayGroup($userid, $usergroupid)
	{
		$userinfo = vB_Api::instanceInternal('user')->fetchUserinfo($userid);

		$membergroups = fetch_membergroupids_array($userinfo);
		$permissions = $userinfo['permissions'];
		$vbulletin = vB::get_registry();
		$bf_ugp_genericpermissions = vB::getDatastore()->get_value('bf_ugp_genericpermissions');

		if ($usergroupid == 0)
		{
			throw new vB_Exception_Api('invalidid', array('usergroupid'));
		}

		if (!in_array($usergroupid, $membergroups))
		{
			throw new vB_Exception_Api('notmemberofdisplaygroup');
		}
		else
		{
			$display_usergroup = $vbulletin->usergroupcache["{$usergroupid}"];

			if ($usergroupid == $userinfo['usergroupid'] OR $display_usergroup['canoverride'])
			{
				$userinfo['displaygroupid'] = $usergroupid;

				// init user data manager
				$userdata = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);
				$userdata->set_existing($userinfo);

				$userdata->set('displaygroupid', $usergroupid);

				if (!$userinfo['customtitle'])
				{
					$userdata->set_usertitle(
						$userinfo['customtitle'] ? $userinfo['usertitle'] : '',
						false,
						$display_usergroup,
						($permissions['genericpermissions'] & $bf_ugp_genericpermissions['canusecustomtitle']) ? true : false,
						($permissions['genericpermissions'] & $bf_ugp_genericpermissions['cancontrolpanel']) ? true : false
					);
				}

				$userdata->save();
			}
			else
			{
				throw new vB_Exception_Api('usergroup_invaliddisplaygroup');
			}
		}
	}

	/**
	 * Fetch a list of banned usergroups
	 */
	public function fetchBannedUsergroups()
	{
		$loginuser = &vB::getCurrentSession()->fetch_userinfo();
		$usercontext = &vB::getUserContext($loginuser['userid']);

		if (!$usercontext->hasAdminPermission('cancontrolpanel') AND !$usercontext->getChannelPermission('moderatorpermissions', 'canbanusers', 1))
		{
			$forumHome = vB_Library::instance('content_channel')->getForumHomeChannel();

			$args = array($loginuser['username']);
			$args[] = vB_Template_Runtime::fetchStyleVar('right');
			$args[] = vB::getCurrentSession()->get('sessionurl');
			$args[] = $loginuser['securitytoken'];
			$args[] = vB5_Route::buildUrl($forumHome['routeid'] . '|nosession|fullurl');

			throw new vB_Exception_Api('nopermission_loggedin', $args);
		}

		$bf_ugp_genericoptions = vB::getDatastore()->getValue('bf_ugp_genericoptions');

		$usergroups = $this->fetchUsergroupList();
		$bannedusergroups = array();
		foreach ($usergroups as $usergroup)
		{
			if (!($usergroup['genericoptions'] & $bf_ugp_genericoptions['isnotbannedgroup']))
			{
				$bannedusergroups[$usergroup['usergroupid']] = $usergroup;
			}
		}

		return $bannedusergroups;
	}

	/**
	 * Returns the usergroupid for moderator group
	 *
	 * @param int $moderatorGroup
	 * @return mixed
	 */
	public function getModeratorGroupId($moderatorGroup = 0)
	{
		if (empty($moderatorGroup))
		{
			$moderatorGroup = self::CHANNEL_MODERATOR_SYSGROUPID;
		}

		$group = vB::getDbAssertor()->getRow('vBForum:usergroup', array('systemgroupid' => $moderatorGroup));
		if (!empty($group))
		{
			return $group['usergroupid'];
		}
		return false;
	}

	/**
	 * Returns the usergroupid for owner group
	 *
	 * @param int $ownerGroup
	 * @return mixed
	 */
	public function getOwnerGroupId($ownerGroup = 0)
	{
		if (empty($ownerGroup))
		{
			$ownerGroup = self::CHANNEL_OWNER_SYSGROUPID;
		}

		$group = vB::getDbAssertor()->getRow('vBForum:usergroup', array('systemgroupid' => $ownerGroup));
		if (!empty($group))
		{
			return $group['usergroupid'];
		}
		return false;
	}

	/**
	 * Returns the usergroupid for member group
	 *
	 * @param type $memberGroup
	 * @return boolean
	 */
	public function getMemberGroupId($memberGroup = 0)
	{
		if (empty($memberGroup))
		{
			$memberGroup = self::CHANNEL_MEMBER_SYSGROUPID;
		}

		$group = vB::getDbAssertor()->getRow('vBForum:usergroup', array('systemgroupid' => $memberGroup));
		if (!empty($group))
		{
			return $group['usergroupid'];
		}
		return false;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
