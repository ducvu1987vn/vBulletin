<?php

/* ======================================================================*\
   || #################################################################### ||
   || # vBulletin 5.0.0
   || # ---------------------------------------------------------------- # ||
   || # Copyright  2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
   || # This file may not be redistributed in whole or significant part. # ||
   || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
   || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
   || #################################################################### ||
   \*====================================================================== */

/**
* vB_UserContext
*
* @package vBulletin
*/

class vB_UserContext
{
	const USERGROUP_GUESTS = 1;

	// User hierarchy level
	const USERLEVEL_SUPERADMIN 	   = 5;
	const USERLEVEL_ADMIN 		   = 4;
	const USERLEVEL_SUPERMODERATOR = 3;
	const USERLEVEL_MODERATOR 	   = 2;
	const USERLEVEL_REGUSER 	   = 1;
	const USERLEVEL_GUEST 		   = 0;

	protected $userid;
	/**
	 *
	 * @var vB_dB_Assertor
	 */
	protected $assertor;
	/**
	 *
	 * @var vB_Datastore
	 */
	protected $datastore;
	/**
	 *
	 * @var array
	 */
	protected $config;
	/**
	 * @var vB_PermissionContext
	 */
	protected $permissionContext;
	protected $admin_info = null;
	protected $canRead = false;
	protected $cantRead = false;
	protected $selfOnly = false;
	protected $usergroups;
	protected $permissions = false;
	protected $channelPermissions = array();
	protected $superAdmins = array();
	protected $canModerate = false;
	protected $groupInTopic = array();
	protected $contentTypes = array();
	protected $canCreateTypes = array();
	protected $canPost = array();
	protected $noComments = array();
	protected $moderatorPerms = array();
	protected $moderatorBitMasks = false;
	protected $channelAccess = false;
	protected $channelPermsFrom = false;
	protected $userIsSuperAdmin = false;
	protected $superMod = array();
	//globalPerms is not all global perms, but the three that the node library needs to populate the node full content data
	protected $globalPerms = false;

	//If you change this, make sure you change the channelpermissions $permissionFields array/
	protected $countFields = array(
		'edit_time' => 'hours',
		'maxtags' => 'count',
		'maxstartertags' =>'count',
		'maxothertags' => 'count',
		'maxattachments' =>'count',
		'maxchannels' =>'count',
		'channeliconmaxsize' => 'count'
	);

	/**
	 * The following permissions should always be checked even if current user is a Superadmin
	 *
	 * @var array
	 */
	protected $superAdminsCheckPerms = array(
		'requirehvcheck' => 1,
		'showeditedby' => 1,
		'canbeusernoted' => 1,
		'allowhtml' => 1,
	);

	public function __construct($userid, $assertor, $datastore, $config)
	{
		$this->userid = $userid;
		$this->assertor = $assertor;
		$this->datastore = $datastore;
		$this->config = $config;
		$this->channelPermsFrom = vB::getDatastore()->getValue('vBUgChannelPermissionsFrom');
		$this->reloadUserPerms();

	}

	public function reloadUserPerms($forceReload = false)
	{
		if (!$forceReload)
		{
			$cached = vB_Cache::instance(vB_Cache::CACHE_LARGE)->read('vB_UserPerms' . $this->userid);
		}

		if (!empty($cached))
		{
			$this->groupInTopic = $cached['groupintopic'];
			$this->userIsSuperAdmin = $cached['is_SA'];
			$this->usergroups = $cached['usergroups'];
			$this->moderatorPerms = $cached['moderatorperms'];
			$this->channelAccess =  $cached['channelAccess'];
			$this->permissionContext = new vB_PermissionContext($this->datastore, $cached['primary'], $cached['secondary'], $cached['infraction']);
			$this->canModerate = $cached['canmoderate'];
			$this->superMod = $cached['superMod'];
		}
		else
		{
			//We can't call the user api function getGroupInTopic(), because that does a permission check. Which causes a recursion loop
			//We have to do a try-catch block here. During an upgrade we check permissions although the groupintopic table doesn't exist.
			try
			{
				if ($this->userid > 0)
				{
					$this->reloadGroupInTopic();
				}
			}
			catch (exception $e)
			{
				//Nothing to do here. Just continue
			}

			$this->superMod = array('moderatorpermissions' => 0 , 'moderatorpermissions2' => 0);
			// fetch superadmin list
			if (empty($this->superAdmins)) {
				$this->superAdmins = preg_split('#\s*,\s*#s', $this->config['SpecialUsers']['superadmins'], -1, PREG_SPLIT_NO_EMPTY);

				if (in_array($this->userid, $this->superAdmins)) {
					$this->userIsSuperAdmin = true;
				}
			}

			// fetch user groups
			//first see if we have a cached value
			$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
			$usergroupCached = $cache->read('userGroups.' . $this->userid);
			$userLib = vB_Library::instance('user');

			if ($usergroupCached)
			{
				$this->usergroups = $usergroupCached['usergroups'];
				$infraction_group_ids = $usergroupCached['infractiongroupids'];
				$primary_group_id = current($usergroupCached['usergroups']);
				$secondary_group_ids = array_slice($usergroupCached['usergroups'], 1);
			}
			else
			{
				//No, we need to query for it
				if ($this->userid > 0)
				{
					$result = $userLib->fetchUserGroups($this->userid);
				}
				else
				{
					$result = false;
				}

				if ($result)
				{
					$primary_group_id = $result['groupid'];
					$secondary_group_ids = $result['secondary'];
					$infraction_group_ids = $result['infraction'];
				}
				else
				{
					$primary_group_id = self::USERGROUP_GUESTS;
					$secondary_group_ids = array();
					$infraction_group_ids = array();
				}
				$this->usergroups = array_merge(array($primary_group_id), $secondary_group_ids);
				$cache->write('userGroups.' . $this->userid, array('usergroups' => $this->usergroups,
					'infractiongroupids' => $infraction_group_ids), 1440, array('perms_changed', 'userChg_' . $this->userid));
			}
			$this->permissionContext = new vB_PermissionContext($this->datastore, $primary_group_id, $secondary_group_ids, $infraction_group_ids);

			//Add any permissions from the moderator table.
			if ($this->userid)
			{
				$this->moderatorPerms = $userLib->fetchModerator($this->userid);

				if ($this->hasAdminPermission('ismoderator') AND !empty($this->moderatorPerms[0]))
				{
					$this->superMod = array('moderatorpermissions' => $this->moderatorPerms[0]['moderatorpermissions'],
						'moderatorpermissions2' =>  $this->moderatorPerms[0]['moderatorpermissions2']);
			}
			}
			else
			{
				$this->moderatorPerms = array();
			}

			vB_Cache::instance(vB_Cache::CACHE_LARGE)->write('vB_UserPerms' . $this->userid,
				array('groupintopic' => $this->groupInTopic, 'is_SA' => $this->userIsSuperAdmin, 'usergroups' => $this->usergroups,
					'moderatorperms' => $this->moderatorPerms, 'primary' => $primary_group_id, 'secondary' => $secondary_group_ids,
					'infraction' => $infraction_group_ids, 'channelAccess' => $this->getAllChannelAccess(),
					'canmoderate' => $this->getCanModerate(), 'superMod' => $this->superMod ),
				1440, array('userChg_' . $this->userid, 'perms_changed', 'vB_ChannelStructure_chg'));
		}
	}

	/**  This gives a list of all the groups that can create a starter somewhere.
	 *
	 * 	@param		int		the nodeid we're interested in. It's best if this is the channel id.
	 *	@return		mixed	array of integers- each is a usergroupid.
	 *
	 */
	public function getContributorGroups($nodeid)
	{
		$result = array();
		$usergroups = vB::getDatastore()->getValue('usergroupcache');
		foreach ($usergroups AS $groupid => $usergroup )
		{
			$channelid = $this->getPermissionsFrom($groupid, $nodeid );

			if ($this->permissionContext->getChannelPerm($groupid,'forumpermissions', 'canpostnew', $channelid))
			{
				$result[$groupid] = $groupid;
			}
		}
		return $result;
	}

	/** Clears all existing permissions. Needed primarily in test.
	 *
	 * 	@param	int		optional usergroup
	 *
	 **/
	public function clearChannelPermissions($usergroupid = false)
	{
		$this->permissionContext->clearChannelPermissions($usergroupid);
		$this->canModerate = false;
		$this->canRead = false;
		$this->cantRead = false;
		$this->noComments = array();
		$this->canCreateTypes = array();
		$this->canPost = array();
		$this->channelAccess = false;
		$this->globalPerms = false;
		$this->reloadUserPerms(true);
		$this->superMod = array('moderatorpermissions' => 0 , 'moderatorpermissions2' => 0);
		$this->channelPermsFrom = vB::getDatastore()->getValue('vBUgChannelPermissionsFrom');

		//Add any permissions from the moderator table.
		if ($this->userid)
		{
			$this->moderatorPerms = vB_Library::instance('user')->fetchModerator($this->userid);
		}
		else
		{
			$this->moderatorPerms = array();
		}
	}

	/** Reloads the groupInTopic data. Needed primarily in test.
	 *
	 *
	 **/
	public function reloadGroupInTopic()
	{
		$this->groupInTopic = array();

		//We need to query the database.
		$groupInTopic = vB::getDbAssertor()->assertQuery('vBForum:groupintopic', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'userid' => $this->userid));
		foreach ($groupInTopic as $topic)
		{
			if (!isset($this->groupInTopic[$topic['nodeid']]))
			{
				$this->groupInTopic[$topic['nodeid']] = array();
			}
			$this->groupInTopic[$topic['nodeid']][$topic['groupid']] = $topic['groupid'];
		}
	}

	/*
	 * Fetch group in topic
	 *
	 * @param	Mixed	Nodeid, to retrieve single item
	 *
	 * @return	Mixed
	 */
	public function fetchGroupInTopic($nodeid = null)
	{
		if ($nodeid)
		{
			if (isset($this->groupInTopic[$nodeid]))
			{
				return $this->groupInTopic[$nodeid];
			}
			return array();
		}
		return $this->groupInTopic;
	}


	/*** Checks for a specific usergroup permission
	 *
	 *  For admin permissions use the hasAdminPermission method
	 *	except for cancontrolpanel and ismoderator permission
	 *
	 * 	@param string	the name of the permission group
	 *	@param string	the name of the permission
	 *
	 *	@return	boolean
	 *
	 ***/
	public function hasPermission($group, $permission)
	{
		// if user is super admin no need to check
		if ($this->userIsSuperAdmin AND !isset($this->superAdminsCheckPerms[$permission]))
		{
			return true;
		}

		if ($this->permissionContext->hasPermission($group, $permission))
		{
			return true;
	}

		if (($group == 'moderatorpermissions') OR ($group == 'moderatorpermissions2'))
		{
			$perm_nodes = $this->checkModPerm($group, $permission);
			return !empty($perm_nodes);
		}

		return false;
	}

	/** Get the userid
	 *
	 *	@return		integer
	 ***/
	public function fetchUserId()
	{
		return $this->userid;
	}

	/** Return the usergroups for this user
	*
	*	@return 	mixed	array of usergroupid's
	***/
	public function fetchUserGroups()
	{
		return $this->usergroups;
	}

	/** This function finds where permissions are set for a given node.

	 	@param 	integer		the usergroupid being checked
	 	@param	integer		the nodeid being checked
	 	@param	integer		parentid- if we have this we can possibly skip a getNode call

		@return	integer		the nodeid were permissions are checked.
	 */
	public function getPermissionsFrom($groupid, $nodeid, $parentid = false)
	{
		static $channelType = false;

		if ($channelType == false)
		{
			$channelType = vB_Types::instance()->getContentTypeID('vBForum_Channel');
		}

		if (isset($this->channelPermsFrom[$groupid][$nodeid]))
		{
			return $this->channelPermsFrom[$groupid][$nodeid];
		}

		if ($parentid  AND isset($this->channelPermsFrom[$groupid][$parentid]))
		{
			return $this->channelPermsFrom[$groupid][$parentid];
		}

		if ($parentid)
		{
			$node = vB_Library::instance('node')->getNodeBare($parentid);
		}
		else
		{
			$node = vB_Library::instance('node')-> getNodeBare($nodeid);
		}

		//Perhaps the node is a starter and the parentid has a value;
		if (isset($this->channelPermsFrom[$groupid][$node['parentid']]))
		{
			return $this->channelPermsFrom[$groupid][$node['parentid']];
		}

		//If we got here and the node is a channel, this usergroup doesn't have a value for this node.
		//The same if this node is a starter.
		if (($node['contenttypeid'] == $channelType) OR ($node['starter'] == $node['nodeid']))
		{
			return false;
		}

		//let's get the starter. It has to have a starter, because it's not a channel.
		if (empty($node['starter']))
		{
			return false;
		}
		$starter = vB_Library::instance('node')-> getNodeBare($node['starter']);

		if (isset($this->channelPermsFrom[$groupid][$starter['parentid']]))
		{
			return $this->channelPermsFrom[$groupid][$starter['parentid']];
		}
		return false;
		//if we got here, we don't have any permissions for this node.
	}


	/*** Checks for a specific permission in a specific channel
	 *
	 *	@param	string	the name of the permission group
	 *	@param	string	the name of the permission
	 *	@param	int		the channel to check. Note that we may inherit from a parent.
	 * 	@param	mixed	optional array of either closure table records, node table records, or integer representing the ancestor nodeid's
	 *	@param	int		optional immediate parent nodeid
	 *
	 *	@return int		permission numeric value
	 *
	 ***/
	public function getChannelLimitPermission($permissiongroup, $permission, $nodeid, $parents = false, $parentid = false)
	{
		static $channelType = false;

		/* One of the rules is that permissions are additive. I.e. if someone belongs to multiple groups
		   and at least one of them has a permission, the user has that permission.
		   Also, permissions inherit. So:
		   So we start with all this user's usergroups. We look at the
		   current channel to see if that user has the requested permission. If not we look
		   up to tree to the top. At each level, if permissions are assigned for a usergroup
		   and the user DOESN'T have that permission then we remove that usergroup for further
		   checks.
		*/

		if ($channelType == false)
		{
			$channelType = vB_Types::instance()->getContentTypeID('vBForum_Channel');
		}

		$usergroups = $this->usergroups;
		//If we're lucky they'll be asking for a channel in the record. Let's try

		// comments disabled check
		if ($permissiongroup == 'createpermissions')
		{
			$createPerms = $this->getCanCreate($nodeid);
			$permission = vB_String::vBStrToLower($permission);
			if (!intval($createPerms[$permission]))
			{
				return false;
			}
		}
		else if($permissiongroup == 'forumpermissions')
		{
			// Check for VM Parent to map VM permissions
			$vmParentid = vB_Api::instance('node')->fetchVMChannel();
			$node = vB_Library::instance('node')->getNode($nodeid);
			if ($vmParentid == $node['parentid'])
			{
				$canEditVm = (($node['userid'] == $this->userid) AND $this->hasPermission('visitormessagepermissions', 'caneditownmessages'));
				$canDeleteVm = (($node['userid'] == $this->userid) AND $this->hasPermission('visitormessagepermissions', 'candeleteownmessages'));
				$canManageVm = (isset($node['setfor']) AND $node['setfor'] == $this->userid AND $this->hasPermission('visitormessagepermissions', 'canmanageownprofile'));
				switch($permission)
				{
					case 'caneditpost':
						return $canEditVm;
					case 'candeletethread':
						// User can delete from their own profile if they have canManageVm
						// User can delete from their own profile or their messages on other profiles if they have canDelete and canEdit
						return (($canDeleteVm AND $canEditVm) OR $canManageVm);
					default:
						// nothing, fall down
				}
			}
		}

		// check if we're accessing a boolean or integer permission
		$isIntPermission = isset($this->countFields[$permission]);

		$returnPermValue = false;

		// If we have channel-group permissions we need to check them first. However, only check it if
		// it's an int permission. Use getChannelPermission for bitfield/boolean values.
		if (!empty($this->groupInTopic) AND $isIntPermission)
		{
			//groupintopic is set at the channel level. So let's get this node's channel.
			$node = vB_Library::instance('node')->getNodeBare($nodeid);

			if ($node['contenttypeid'] == $channelType)
			{
				$gitNodeid = $nodeid;
			}
			else if ($node['starter'] == $nodeid)
			{
				$gitNodeid = $node['parentid'];
			}
			else
			{
				$starter = vB_Library::instance('node')->getNodeBare($node['starter']);
				$gitNodeid = $starter['parentid'];
			}

			//see if we have at the node level
			if (array_key_exists($gitNodeid, $this->groupInTopic))
			{
				foreach ($this->groupInTopic[$gitNodeid] as $usergroupid)
				{
					$channelid = $this->getPermissionsFrom($usergroupid, $gitNodeid);

					$thisPermission = $this->permissionContext->getChannelPerm($usergroupid, $permissiongroup, $permission, $channelid);

					// In the case of int permissions, 0 = no limit.
					// We're grabbing the max value among the user's usergroups.
					if ($thisPermission == 0)
					{
						return $thisPermission;
					}
					else
					{
						$returnPermValue = max($returnPermValue, $thisPermission);
					}
				}
			}
		}

		foreach ($usergroups AS $key => $usergroupid)
		{
			//We need to know where permissions are set. We may have permission at a higher level.
			$channelid = $this->getPermissionsFrom($usergroupid, $nodeid);

			$thisPermission = $this->permissionContext->getChannelPerm($usergroupid, $permissiongroup, $permission, $channelid);

			if ($isIntPermission)
			{
				// In the case of int permissions, 0 = no limit.
				// We're grabbing the max value among the user's usergroups.
				if ($thisPermission == 0)
				{
					return $thisPermission;
				}
				else
				{
					$returnPermValue = max($returnPermValue, $thisPermission);
				}
			}
			else
			{
				if ($thisPermission > 0)
				{
					return (bool) $thisPermission;
				}
			}
		}

		//If we got here, we don't have any permission.
		return $returnPermValue;
	}

	/**
	 * Checks to see if the user has the appropriate moderator permission from the moderators table
	 *
	 * @param 	string	$permissiongroup
	 * @param 	string	$permission
	 * @param 	int	$nodeid (optional, if not specified, will return a list of nodes on which the user has the permission)
	 *
	 * @return 	bool
	 */
	protected function checkModPerm($permissiongroup, $permission, $nodeid = null)
	{
		if (
			(!$this->isSuperMod())
			AND
			($nodeid !== null AND empty($this->moderatorPerms[$nodeid]))
		)
		{
			return false;
		}

		//We don't load the moderator bitmasks unless we need them.
		if (!$this->moderatorBitMasks)
		{
			$datastore = vB::getDatastore();
			$this->moderatorBitMasks['moderatorpermissions'] = $datastore->getValue('bf_misc_moderatorpermissions');
			$this->moderatorBitMasks['moderatorpermissions2'] = $datastore->getValue('bf_misc_moderatorpermissions2');
		}

		//if we have supermoderator, check that first.
		if ($this->isSuperMod() AND (($this->moderatorBitMasks[$permissiongroup][$permission] & $this->superMod[$permissiongroup]) > 0))
		{
			return true;
		}

		//the field name is just permissions or permissions2
		$field = substr($permissiongroup, 9);

		if (!empty($this->moderatorBitMasks[$permissiongroup]) AND !empty($this->moderatorBitMasks[$permissiongroup][$permission]))
		{
			if ($nodeid === null)
			{
				// If no nodeid is passed, we just want to check if the moderator
				// has this permission on *any* node. This applies to "global" mod
				// permissions such as 'canviewprofile'
				$nodes = array();
				foreach ($this->moderatorPerms AS $nodeid => $moderatorPerms)
				{
					if ($this->moderatorBitMasks[$permissiongroup][$permission] & $moderatorPerms[$field])
					{
						$nodes[] = $nodeid;
					}
				}

				return $nodes;
			}
			else
			{
				// We have a nodeid, so check for the permission on that node
				return (bool) $this->moderatorBitMasks[$permissiongroup][$permission] & $this->moderatorPerms[$nodeid][$field];
			}
		}

		return false;
	}

	/*** Checks for a limit-type permission in the usergroup table- e.g. maximum number of images.
	 *
	 * @param 	string	the name of the permission
	 *
	 *	@return		mixed	false if this is not a valid request, -1 if there is no setting for this user's group or groups,
	 *					or an integer
	 *
	 ***/
	public function getUsergroupLimit($permission)
	{
		//first let's make sure this is a field in the usergroup tables
		$ugFields = vB::getDbAssertor()->fetchTableStructure('usergroup');

		if (!in_array($permission, $ugFields['structure']))
		{
			return false;
		}

		$result = -1;
		$usergroupCache = vB::getDatastore()->getValue('usergroupcache');
		foreach ($this->usergroups AS $groupid)
		{

			if (array_key_exists($groupid, $usergroupCache) AND array_key_exists($permission, $usergroupCache[$groupid])
				and is_numeric($usergroupCache[$groupid][$permission]))
			{
				//0 means no limit;
				if (intval($usergroupCache[$groupid][$permission]) == 0)
				{
					return 0;
				}
				else if (intval($usergroupCache[$groupid][$permission]) > $result)
				{
					$result = intval($usergroupCache[$groupid][$permission]);
				}
			}
		}

		return $result;
	}


	/*** Checks for a specific permission in a specific channel
	 *
	 * 	@param	string	the name of the permission group
	 *	@param	string	the name of the permission
	 * 	@param	int		the channel to check. Note that we may inherit from a parent.
	 * 	@param	mixed	optional array of either closure table records, node table records, or integer representing the ancestor nodeid's
	 *	@param	int		optional immediate parent nodeid
	 *
	 *	@return	boolean|integer
	 *
	 ***/
	public function getChannelPermission($permissiongroup, $permission, $nodeid, $parents = false, $parentid = false)
	{
		static $channelType = false;

		if ($channelType == false)
		{
			$channelType = vB_Types::instance()->getContentTypeID('vBForum_Channel');
		}

		// comments disabled check
		if ($permissiongroup == 'createpermissions')
		{
			$createPerms = $this->getCanCreate($nodeid);
			$permission = vB_String::vBStrToLower($permission);

			if (isset($createPerms[$permission]))
			{
				return (bool)($createPerms[$permission]);
			}
			else
			{
				return false;
			}
		}
		// check if we're accessing a boolean or integer permission
		$isIntPermission = isset($this->countFields[$permission]);

		// if user is super admin and not requesting an integer permission, no need to check
		if (!$isIntPermission && $this->userIsSuperAdmin)
		{
			return true;
		}

		if (
			!$isIntPermission
			AND
			(
				($permissiongroup == 'moderatorpermissions')
				OR
				($permissiongroup == 'moderatorpermissions2')
			)
			AND
			$this->checkModPerm($permissiongroup, $permission, $nodeid)
		)
		{
			return true;
		}
		//If we have channel-group permissions we need to check them first.
		if (!empty($this->groupInTopic))
		{
			//groupintopic is set at the channel level. So let's get this node's channel.
			$node = vB_Library::instance('node')->getNodeBare($nodeid);

			if ($node['contenttypeid'] == $channelType)
			{
				$gitNodeid = $nodeid;
			}
			else if ($node['starter'] == $nodeid)
			{
				$gitNodeid = $node['parentid'];
			}
			else
			{
				$starter = vB_Library::instance('node')->getNodeBare($node['starter']);
				$gitNodeid = $starter['parentid'];
			}

			//see if the current user has groupintopic at this level
			if (!empty($this->groupInTopic[$gitNodeid]) AND is_array($this->groupInTopic[$gitNodeid]))
			{
				foreach ($this->groupInTopic[$gitNodeid] AS  $usergroupid)
				{
					$channelid = $this->channelPermsFrom[$usergroupid][$gitNodeid];

					if ((bool)$this->permissionContext->getChannelPerm($usergroupid, $permissiongroup, $permission, $channelid))
					{
						return true;
					}
				}
			}
		}

		// do not cast this to a boolean value, since we may be accessing an integer permission (max attachments, max tags, etc)
		return $this->getChannelLimitPermission($permissiongroup, $permission, $nodeid, $parents, $parentid);
	}

	/** This returns the 5 limit-type settings at the channel level- edit_time, require_moderate, maxstartertags, maxothertags and maxtags
	 *
	 *	@param	integer
	 *	@param	parents		array of ancestry
	 *
	 *	@return	mixed		2 element array
	 ***/
	public function getChannelLimits($nodeid, $permission = '', $parents = false)
	{
		// If there's a permission
		if ($permission)
		{
			return $this->getChannelLimitPermission('forumpermissions' , $permission, $nodeid);
		}

		// If perm wasn't specified
		$perms = array('edit_time', 'require_moderate', 'maxstartertags', 'maxothertags', 'maxtags', 'maxattachments');

		$permValues = array();
		foreach ($perms as $perm)
		{
			// We must ensure we get the int value
			$permValues["$perm"] = (int) $this->getChannelLimitPermission('forumpermissions', $perm, $nodeid);
		}

		return $permValues;
	}

	/*** Checks for a limit-type permission- e.g. maximum number of images.
	 *
	 * @param string	the name of the permission group
	 * @param string	the name of the permission
	 * @param int		the channel to check. Note that we may inherit from a parent.
	 *
	 *	@return	mixed
	 *
	 ***/
	public function getLimit($permission)
	{
		return $this->permissionContext->getLimit($permission);
	}

	/**
	 * Returns whether the user is administrator
	 * Adapted from adminfunctions.php::can_administer
	 * Returns 1 if it has full admin privileges, -1 if it doesn't, or 0 if further controls are required.
	 * @return int
	 */
	private function basicAdminControl()
	{
		// check if user is guest
		if ($this->userid < 1)
		{
			return -1;
		}

		// check if user has access to controlpanel
		$bf_ugp_adminpermissions = $this->datastore->get_value('bf_ugp_adminpermissions');
		$admin_permissions = $this->permissionContext->getPermission('adminpermissions');
		if (!($admin_permissions & $bf_ugp_adminpermissions['cancontrolpanel']))
		{
			return -1;
		}

		// check if user is superadmin (defined in config.php)
		if ($this->userIsSuperAdmin)
		{
			return 1;
		}

		// it is not superadmin but still can have admin privileges
		return 0;
	}

	/**
	 * Returns whether the user is globally ignored
	 * @return bool
	 */
	public function isGloballyIgnored()
	{
		$options = vB::getDatastore()->get_value('options');
		if (trim($options['globalignore']) != '')
		{
			$exclude = preg_split('#\s+#s', $options['globalignore'], -1, PREG_SPLIT_NO_EMPTY);
			if (in_array($this->userid, $exclude))
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns whether the user is administrator
	 * Adapted from adminfunctions.php::can_administer
	 * @return bool
	 */
	public function isAdministrator()
	{
		$full_admin = $this->basicAdminControl();

		return ($full_admin >= 0);
	}
	
	/**
	 * Returns whether the current user is a super moderator
	 * 
	 * @return bool
	 */
	public function isSuperMod()
	{
		return (!empty($this->superMod) AND (($this->superMod['moderatorpermissions'] > 0) OR ($this->superMod['moderatorpermissions2'] > 0)));
	}

	/*** Checks the administrator table for individual administrator permission
	 *  (all the different areas of the admin cp that they can access)
	 *
	 *	@param string	the name of the permission
	 *
	 *	@return	boolean
	 *
	 ***/
	public function hasAdminPermission($permission)
	{
		// if user is super admin
		if ($this->userIsSuperAdmin)
		{
			return true;
		}

		$full_admin = $this->basicAdminControl();
		if ($full_admin !== 0)
		{
			return $full_admin > 0;
		}

		if (!isset($this->admin_info))
		{
			$result = $this->assertor->assertQuery('vBForum:administrator', array(vB_dB_Query::TYPE_KEY =>  vB_dB_Query::QUERY_SELECT, 'userid' => $this->userid));

			if ($result->valid())
			{
				$this->admin_info = $result->current();
			}
			else
			{
				return false;
			}
		}

		$bf_ugp_adminpermissions = $this->datastore->get_value('bf_ugp_adminpermissions');
		return (bool)($this->admin_info['adminpermissions'] & $bf_ugp_adminpermissions[$permission]);
	}

	/*** This returns a list of channels where this user can read, and an array where they can't.
	 *
	 *	@param	integer	limit to this channel
	 *
	 *	@return	mixed		array of ('can'=> array of integers, 'cannot' => array of integers)
	 *
	 ***/
	public function getReadChannels($nodeid = false)
	{
		// If we don't have a channel id and we've already generated the list don't do it again.

		if (($nodeid == false) AND ($this->canRead !== false) )
		{
			return array('canRead' =>$this->canRead, 'cantRead' => $this->cantRead, 'selfOnly' => $this->selfOnly );
		}

		$forumPerms = array('canRead' => array(), 'cantRead' => array(), 'selfOnly' => array());
		//We need to generate. First add everything we can see.

		foreach ($this->usergroups as $usergroupid)
		{
			$perms = $this->permissionContext->getForumAccess($usergroupid);

			if (!empty($perms))
			{
				foreach ($perms['canRead'] as $values)
				{
					if (!in_array($values, $forumPerms['canRead']))
					{
						$forumPerms['canRead'][] = $values;
					}
				}
				foreach ($perms['selfOnly'] as $values)
				{
					if (!in_array($values, $forumPerms['selfOnly']))
					{
						$forumPerms['selfOnly'][] = $values;
					}
				}
				foreach ($perms['cantRead'] as $values)
				{
					// If one of the user's usergroup has permission to read a channel, then the user has the canRead permission
					if (!in_array($values, $forumPerms['cantRead']) AND !in_array($values, $forumPerms['canRead']))
					{
						$forumPerms['cantRead'][] = $values;
					}
				}
			}
		}

		if(!$this->hasPermission('socialgrouppermissions', 'canviewgroups') )
		{
			try
			{
				$forumPerms['cantRead'][] = vB_Api::instance('socialgroup')->getSGChannel();
			}
			catch (exception $e)
			{
				//this happens mainly during install/upgrade from vB4
			}
		}

		if (($this->userid == 0) AND(!empty($forumPerms['selfOnly'])))
		{
			$forumPerms['cantRead'] = array_merge($forumPerms['cantRead'], $forumPerms['selfOnly']);
			$forumPerms['selfOnly'] = array();
		}
		//Now we check. If any channel is in more than one category- canRead beats selfOnly which beats cantRead.
		foreach ($forumPerms['cantRead'] as $nodeid)
		{
			if (array_key_exists($nodeid, $forumPerms['canRead']) OR array_key_exists($nodeid, $forumPerms['selfOnly']))
			{
				unset($forumPerms['cantRead'][$nodeid]);
			}
		}
		foreach ($forumPerms['selfOnly'] as $nodeid)
		{
			if (array_key_exists($nodeid, $forumPerms['canRead'] ))
			{
				unset($forumPerms['selfOnly'][$nodeid]);
			}
		}

		//If this is the general case, store it.
		if (!$nodeid)
		{
			$this->canRead = $forumPerms['canRead'];
			$this->cantRead = $forumPerms['cantRead'];
			$this->selfOnly = $forumPerms['selfOnly'];
		}

		return $forumPerms;
	}

	/*** This returns a list of channels where this user can moderate, and an array where they can't.
	 *
	 *	@param	integer	limit to this channel
	 *
	 *	@return	mixed		array of ('can'=> array of integers, 'cannot' => array of integers)
	 *
	 ***/
	public function getCanModerate($nodeid = false)
	{
		//We could access this several times in a page load. No sense composing it more than once.
		if ($this->canModerate !== false)
		{
			return $this->canModerate;
		}
		$forumPerms = array('can' => array(), 'cannot' => array());
		
		// If we have supermoderator, we get these permissions in every channel
		if ($this->isSuperMod())
		{
			$channelQry = vB::getDbAssertor()->assertQuery('vBForum:node', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'contenttypeid' => vB_Types::instance()->getContentTypeID('vBForum_Channel')));
			foreach ($channelQry AS $channel)
			{
				$forumPerms['can'][] = $channel['nodeid'];
			}
			$this->canModerate = $forumPerms;
			return $forumPerms;
		}
		
		// If we have a channel moderator, we get these permissions for this channel and the 
		if ($nodeid !== false AND isset($this->moderatorPerms[$nodeid]))
		{
			$closurerecords = vB::getDbAssertor()->assertQuery('vBForum:getDescendantChannelNodeIds', array(
				'parentnodeid' => $nodeid, 'channelType' => vB_Types::instance()->getContentTypeID('vBForum_Channel')
			));
			foreach ($closurerecords as $closurerecord)
			{
				$forumPerms['can'][] = $closurerecord['child'];
			}
		}

		$allAccess = $this->permissionContext->getAllChannelAccess($this->usergroups);
		//the permission context has already done the heavy lifting. We just need to merge the appropriate arrays.
		foreach ($allAccess as $ugInfo)
		{
			if ($this->userIsSuperAdmin)
			{
				if (!empty($ugInfo['canmoderate']))
				{
					$forumPerms['can'] = array_merge($forumPerms['can'], $ugInfo['canmoderate']);
				}

				if (!empty($ugInfo['canview']))
				{
					$forumPerms['can'] = array_merge($forumPerms['can'], $ugInfo['canview']);
				}

				if (!empty($ugInfo['selfonly']))
				{
					$forumPerms['can'] = array_merge($forumPerms['can'], $ugInfo['selfonly']);
				}
			}
			else
			{
				if (!empty($ugInfo['canmoderate']))
				{
					$forumPerms['can'] = array_merge($forumPerms['can'], $ugInfo['canmoderate']);
				}

				if (!empty($ugInfo['canview']))
				{
					$forumPerms['cannot'] = array_merge($forumPerms['cannot'], $ugInfo['canview']);
				}

				if (!empty($ugInfo['selfonly']))
				{
					$forumPerms['cannot'] = array_merge($forumPerms['cannot'], $ugInfo['selfonly']);
				}
			}

		}

		//Now from groupintopic.
		if (!$this->userIsSuperAdmin AND !empty($this->groupInTopic))
		{
			foreach ($this->groupInTopic  AS $nodeid => $groups)
			{
				foreach ($groups AS $groupid)
				{

					if ($this->permissionContext->getChannelPerm($groupid, 'moderatorpermissions', 'canmoderateposts', $nodeid) AND
						!in_array($nodeid, $forumPerms ['can']))
					{
						$forumPerms['can'][] = $nodeid;
					}
				}
			}
		}

		//Now we check. If any channel is in both can and cannot, can wins.
		if (!empty($forumPerms['can']) and !empty($forumPerms['cannot']))
		{
			$forumPerms['cannot'] = array_diff($forumPerms['cannot'], $forumPerms['can']);
		}

		$this->canModerate = $forumPerms;
		return $forumPerms;
	}

	public function isForumModerator()
	{
		$moderator = $this->assertor->getField('vBForum:moderator', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_COUNT, 'userid' => $this->userid));
		if (!empty($moderator))
		{
			return true;
		}
		return false;
	}

	public function isModerator()
	{
		if ($this->isForumModerator())
		{
			return true;
		}

		$allAccess = $this->permissionContext->getAllChannelAccess($this->usergroups);
		//the permission context has already done the heavy lifting. We just need to merge the appropriate arrays.
		foreach ($allAccess as $ugInfo)
		{
			if (!empty($ugInfo['canmoderate']))
			{
				return true;
			}
		}

		return false;
	}

	/** Checks to see if a user can upload a file for attachment
	*
	* 	@param 	int		size of the file
	* 	@param	string	file extension
	* 	@param	int		channelid to which this will be attached. Optional
	***/
	public function canUpload($size, $extension, $nodeid = false)
	{
		//TODO	This needs to actually check. This is fairly complex, but it needs to check with
		//	permissionContext, which needs to
		//	- build the file type array similar to vB4 cache_permissions in functions.php
		//	- see if this type is allowed for this user
		//	- check the user's existing total space used against allowed space
		//	- if a nodeid was passed, which usually won't be- can this user attach files in this location?
		//  - given the above, getAttachmentPermissions (both here and in permission context) should only be treated as temporary work arounds
		return ($this->getAttachmentPermissions($extension, $nodeid) == false) ? false : true;
	}

	/** check whether current user is super admin. Don't allow request for a different user.
	 *
	 *	@return bool
	 **/
	public function isSuperAdmin()
	{
		return $this->userIsSuperAdmin;
	}

	/** This returns an array of moderator or moderator-like permissions
	 *
	 *	@param		mixed	a node record. has at least nodeid and contenttypeid. May include channelid or parentid.
	 *
	 * 	@return		mixed 	array of string -> integer values;
	 **/
	public function getModeratorPerms($node)
	{
		if (!is_array($node) OR empty($node['nodeid']) OR empty($node['contenttypeid'])) //not!empty, or you can't check node 1
		{
			return false;
		}

		$perms = array();
		$channelType = vB_Types::instance()->getContentTypeID('vBForum_Channel');
		foreach($this->usergroups AS $usergroup)
		{
			if ($node['contenttypeid'] == $channelType)
			{
				$channelid = $node['nodeid'];
			}
			else if (isset($node['channelid']))
			{
				$channelid = $node['channelid'];
			}
			else if (isset($this->channelPermsFrom[$usergroup][$node['parentid']]))
			{
				$channelid = $this->channelPermsFrom[$usergroup][$node['parentid']];
			}
			else
			{
				$starter = vB_Library::instance('node')->getNode($node['starter']);

				if (empty($starter) OR (!empty($starter['errors'])))
				{
					return false;
				}
				$channelid = $starter['parentid'];
			}

			$moderatorpermissions = $this->permissionContext->getModeratorPermissions($usergroup, $channelid);

			//If current user is superadmin, just return all ones.
			if ($this->userIsSuperAdmin)
			{
				foreach ($moderatorpermissions AS $key => $value)
				{
					$perms[$key] = max(1, $value);
				}
				foreach (array('canmove', 'canopenclose', 'candeletethread', 'candeletepost', ) AS $key)
				{
					$perms[$key] = 1;
				}
				return $perms;
			}

			foreach ($moderatorpermissions AS $key => $value)
			{
				if ((bool) $value)
				{
					$perms[$key] = $value;
				}
				else if (!isset($perms[$key]))
				{
					$perms[$key] = 0;
				}
			}
		}

		// And add the permissions from moderator table.
		if ($node['contenttypeid'] == $channelType)
		{
			$channelid = $node['nodeid'];
		}
		else if (isset($node['channelid']))
		{
			$channelid = $node['channelid'];
		}

		// If we have supermoderator, we get these permissions in every channel
		if ($this->isSuperMod() OR isset($this->moderatorPerms[$channelid]))
		{
			//We don't load the moderator bitmasks unless we need them.
			if (!$this->moderatorBitMasks)
			{
				$datastore = vB::getDatastore();
				$this->moderatorBitMasks['moderatorpermissions'] = $datastore->getValue('bf_misc_moderatorpermissions');
				$this->moderatorBitMasks['moderatorpermissions2'] = $datastore->getValue('bf_misc_moderatorpermissions2');
			}
			foreach (array('moderatorpermissions', 'moderatorpermissions2') AS $permgroup)
			{
				foreach ($this->moderatorBitMasks[$permgroup] AS $key => $mask)
				{
					if ($this->checkModPerm($permgroup, $key, $channelid))
					{
						$perms[$key] = 1;
					}
				}
			}
		}
		
		if (!empty($this->groupInTopic))
		{
			$gitMember = array();
			$gitModerate = array();

			foreach ($this->groupInTopic AS $gitChannel => $groups)
			{
				if ($gitChannel == $channelid)
				{
					foreach($groups AS $groupid)
					{

						$moderatorpermissions = $this->permissionContext->getModeratorPermissions($groupid, $gitChannel);

						if (!empty($moderatorpermissions))
						{
							foreach ($moderatorpermissions AS $key => $value)
							{
								if ((bool) $value)
								{
									$perms[$key] = $value;
								}
								else if (!isset($perms[$key]))
								{
									$perms[$key] = 0;
								}
							}
						}
					}
				}
			}
		}

		return $perms;
	}


	/** Lists the content types the current user can create at a node.
	 *
	 *	@param	int		the nodeid to be checked
	 *
	 *	@return	mixed	array of contenttypes
	 ***/
	public function getCanCreate($nodeid)
	{
		static $bf_ugp = array();
		//if we've already gotten for this node, we're done.
		if (isset($this->canCreateTypes[$nodeid]))
		{
			return $this->canCreateTypes[$nodeid];
		}

		if (empty($bf_ugp))
		{
			$bf_ugp = vB::getDatastore()->getValue('bf_ugp_createpermissions');

			if (empty($bf_ugp))
			{
				//This can happen during upgrade
				$xmlobj = new vB_XML_Parser(false, DIR . '/includes/xml/bitfield_vbulletin.xml');

				if ($xmlobj->error_no() == 1 OR $xmlobj->error_no() == 2)
				{
					throw new Exception("You are missing the " . DIR . "/includes/xml/bitfield_vbulletin.xml file. Please replace it");
				}
				$bitfields = $xmlobj->parse();
				foreach($bitfields['bitfielddefs']['group'] as $group)
				{
					if ($group['name'] == 'ugp')
					{
						foreach($group['group'] as $ugp)
						{
							if ($ugp['name'] == 'createpermissions')
							{
								foreach($ugp['bitfield'] AS $createPerm)
								{
									$bf_ugp[$createPerm['name']] = $createPerm['value'];
								}
								break;
							}
						}
					}
				}
			}
		}

		$options = vB::getDatastore()->getValue('options');

		$createPerms = array();
		$nodeLib = vB_Library::instance('node');
		// get the node record
		$node = $nodeLib->getNode($nodeid);
		$commentsEnabled = vB::getDatastore()->getOption('postcommentthreads');

		//if comments have been globally disabled and the node is not a starter or channel, we can't post
		if (!$commentsEnabled AND ($node['starter'] > 0)
			AND ($node['starter'] <> $node['nodeid']))
		{
			$attachOnly = true;
		}
		else
		{
			$attachOnly = false;
		}


		//If this user is superadmin, just grant them everything EXCEPT
		if ($this->userIsSuperAdmin)
		{
			foreach ($bf_ugp AS $type => $bitfield)
			{
				$createPerms[$type] = 1;
			}


			if ($attachOnly)
			{
				foreach ($createPerms AS $key => $value)
				{
					if (($key != 'vbforum_attach') AND ($key != 'vbforum_photo') )
					{
						$createPerms[$key] = 0;
					}
				}
			}
			return $createPerms;
		}
		$types = vB_Types::instance();
		$channelType = $types->getContentTypeId('vBForum_Channel');

		if ($node['contenttypeid'] == $channelType)
		{
			$isChannel = true;
		}
		else
		{
			$isChannel = false;
		}


		if (empty($commentsEnabled) AND !empty($node['starter']) AND ($node['starter'] != $node['nodeid'])
			AND ($node['contenttypeid'] <> vB_Types::instance()->getContentTypeId('vBForum_Attach'))
			AND ($node['contenttypeid'] <> vB_Types::instance()->getContentTypeId('vBForum_Photo'))
		)
		{
			$this->canCreateTypes[$nodeid] = array();
			return array();
		}

		if (is_array($bf_ugp))
		{
			//This user isn't superadmin and we don't already have this, so we need to calculate it
			foreach ($bf_ugp AS $type => $bitfield)
			{
				$createPerms[$type] = 0;
			}
		}

		//if this or a parent has nodeoptions[OPTION_ALLOW_POST] = 0, we are done.
		if (in_array( $nodeid, $this->noComments))
		{
			$this->canCreateTypes[$nodeid] = $createPerms;
			return $createPerms;
		}

		if (in_array($node['parentid'], $this->noComments) OR in_array($node['starter'], $this->noComments))
		{
			$this->canCreateTypes[$nodeid] = $createPerms;
			return $createPerms;
		}

		//if this is NOT a channel we need to test the channel.
		if (!$isChannel)
		{
			$isStarter = false;

			// try to get the channel node
			// the try/catch block is to keep the widget from crashing and burning
			// if it encounters orphaned nodes; see VBV-4198
			try
			{
				if ($node['nodeid'] == $node['starter'])
				{
					$channel = $nodeLib->getNode($node['parentid']);
				}
				else
				{
					$starter = $nodeLib->getNode($node['starter']);
					$channel = $nodeLib->getNode($starter['parentid']);
				}
				$channelid = $channel['nodeid'];
			}
			catch (vB_Exception_Api $e)
			{
				$errors = $e->get_errors();
				foreach ($errors as $error)
				{
					if (array_pop($error) == 'invalid_node_id')
					{
						// we are likely checking permissions for the parent of an
						// orphaned post (no parent exists) see VBV-4198
						$this->canCreateTypes[$nodeid] = $createPerms;
						return $createPerms;
					}
				}

				throw $e; // re-throw anything else that's not invalid_node_id
			}

			if((($channel['nodeoptions'] & vB_Api_Node::OPTION_ALLOW_POST) > 0) OR ($node['contenttypeid'] == vB_Types::instance()->getContentTypeID('vBForum_Gallery')))
			{
				$this->canPost[$channelid] = 1;
			}

			else
			{
				$this->canPost[$channelid] = 0;
			}

			if ($this->canPost[$channelid] == 0)
			{
				if (!$this->getChannelPermission('moderatorpermissions', 'canmoderateposts', $nodeid))
				{
					$this->canCreateTypes[$nodeid] = $createPerms;
					$this->noComments[] = $node['nodeid'];
					$this->noComments[] = $channel['nodeid'];

					if ($node['nodeid'] != $node['starter'])
					{
						$this->noComments[] = $node['starter'];
					}

					if (($node['parentid'] != $node['starter']) AND ($node['parentid'] != $channel['nodeid']))
					{
						$this->noComments[] = $node['parentid'];
					}

					if ($attachOnly)
					{
						foreach ($createPerms AS $key => $value)
						{
							if (($key != 'vbforum_attach') AND ($key != 'vbforum_photo') )
							{
								$createPerms[$key] = 0;
							}
						}
					}
					$this->canCreateTypes[$nodeid]  = $createPerms;
					return $createPerms;
				}
			}
		}
		else
		{
			$isStarter = true;
			$channelid = $nodeid;
			if(!isset($this->canPost[$channelid]))
			{
				$this->canPost[$channelid] = 1;
			}
		}

		if ($isStarter
			AND !$this->getChannelPermission('forumpermissions', 'canpostnew', $nodeid, false, $node['parentid']))
		{
			$this->canCreateTypes[$nodeid] = array();
			//the user may be able to create channels
			foreach ($this->usergroups as $key => $usergroupid)
			{
				$thisPermFrom = $this->channelPermsFrom[$usergroupid][$channelid];

				if ($this->permissionContext->getChannelPerm($usergroupid, 'createpermissions', 'vbforum_channel', $thisPermFrom))
				{
					$this->canCreateTypes[$nodeid]['vbforum_channel'] = 1;
				}

				// They may also be able to comment
				if ($this->permissionContext->getChannelPerm($usergroupid, 'forumpermissions', 'canreplyothers', $thisPermFrom)
					AND $this->permissionContext->getChannelPerm($usergroupid, 'createpermissions', 'vbforum_text', $thisPermFrom)
					)
				{
					$this->canCreateTypes[$nodeid]['vbforum_text'] = ($node['nodeoptions'] & vB_Api_Node::OPTION_ALLOW_POST) ? 1 : 0;
				}
			}
			return $this->canCreateTypes[$nodeid] ;
		}

		$attachTypes = array('vbforum_photo','vbforum_attach','vbforum_gallery');
		$canAttach = $this->hasPermission('forumpermissions', 'canpostattachment');

		// Adds the usergroups in the groupInTopic for the given channel, otherwise it only has the usergroup(s) set in the user table
		$secondary = (isset($this->groupInTopic[$channelid]) AND is_array($this->groupInTopic[$channelid])) ? $this->groupInTopic[$channelid] : array() ;
		$userGroups = array_merge($this->usergroups, $secondary);

		//Now we scan the  usergroups looking for permission
		foreach ($userGroups as $key => $usergroupid)
		{
			//we need a place to cache the ancestor where permissions inherit from.
			if (isset($this->channelPermsFrom[$usergroupid][$channelid]) AND is_array($bf_ugp))
			{
				$thisPermFrom = $this->channelPermsFrom[$usergroupid][$channelid];

				//If this is a starter we need canpostnew
				// We also need the usergroupid set in the permissions, this is
				// because it is (very) possible that there is no record for this usergroup/node in the groupInTopic
				if ($isStarter AND !$this->permissionContext->getChannelPerm($usergroupid, 'forumpermissions', 'canpostnew', $thisPermFrom))
				{
					if ($this->permissionContext->getChannelPerm($usergroupid, 'createpermissions', 'vbforum_channel', $thisPermFrom))
					{
						$createPerms['vbforum_channel'] = 1;
					}
					continue;
				}
				//if this is a reply to the current users's post we need canreplyown
				else if (!$isStarter AND ($this->userid == $node['userid']) AND !$this->permissionContext->getChannelPerm($usergroupid,'forumpermissions', 'canreplyown', $thisPermFrom))
				{
					continue;
				}
				//if this is a reply to the somebody else's post we need canreplyothers
				else if (!$isStarter AND ($this->userid != $node['userid']) AND !$this->permissionContext->getChannelPerm($usergroupid, 'forumpermissions', 'canreplyothers', $thisPermFrom))
				{
					continue;
				}

				foreach ($bf_ugp AS $type => $bitfield)
				{
					//Complex "IF" here. We need to have the standard permission, PLUS if the global "canpostattachment" is not set
					// then the user can't create galleries, attachmentts, or photos.

					if ($this->permissionContext->getChannelPerm($usergroupid, 'createpermissions', $type, $thisPermFrom) AND
						($canAttach OR !in_array($type, $attachTypes)))
					{
						$createPerms[$type] = 1;
					}
				}
			}
		}

		if ($attachOnly)
		{
			foreach ($createPerms AS $key => $value)
			{
				if (($key != 'vbforum_attach') AND ($key != 'vbforum_photo') )
				{
					$createPerms[$key] = 0;
				}
			}
		}
		//we now have all the create permissions info. Let's cache it for the next check.
		$this->canCreateTypes[$nodeid]  = $createPerms;
		return $createPerms;

	}

	/** This returns an array with the access for this user, reflecting usergroup and groupintopic permissions.
	 *
	 * $param	boolean		whether to force reload
	 *
	 * 	@return	mixed	Each element contains three arrays: canview, canaccess, and selfonly. Each is an array of nodeids where the user has that access
	 */
	public function getAllChannelAccess()
	{
		if ($this->channelAccess !== false)
		{
			return $this->channelAccess;
		}
		$result = array(
			'selfonly'        => array(),
			'canview'         => array(),
			'canalwaysview'	  => array(),
			'canmoderate'     => array(),
			'starteronly'     => array(),
			'canseedelnotice' => array(),
			'owndeleted'      => array(),
		);

		foreach ($this->permissionContext->getAllChannelAccess($this->usergroups) AS $groupid => $access)
		{
			//If by one group I have self only, and another I have moderator, moderate wins.
			//moderate beats can view which beats self only which beats not included (i.e. no access)
			if (!empty($access['canview']))
			{
				$result['canview'] = array_merge($result['canview'], $access['canview']);
				$result['selfonly'] = array_diff($result['selfonly'], $access['canview']);
				$result['starteronly'] = array_diff($result['starteronly'], $access['canview']);
			}

			if (!empty($access['canview']))
			{
				$result['canview'] = array_merge($result['canview'], $access['canview']);
				$result['selfonly'] = array_diff($result['selfonly'], $access['canview']);
			}

			if (!empty($access['canalwaysview']))
			{
				$result['canalwaysview'] = array_merge($result['canalwaysview'], $access['canalwaysview']);
			}

			if (!empty($access['canmoderate']))
			{
				$result['selfonly'] = array_diff($result['selfonly'], $access['canmoderate']);
				$result['canmoderate'] = array_merge($result['canmoderate'], $access['canmoderate']);
				$result['canview'] = array_diff($result['canview'], $access['canmoderate']);
			}

			if (!empty($access['selfonly']))
			{
				$result['selfonly'] = array_merge($result['selfonly'], $access['selfonly']);
				$result['starteronly'] = array_diff($result['starteronly'], $access['selfonly']);
			}

			if (!empty($access['starteronly']))
			{
				$result['starteronly'] = array_merge($result['starteronly'], $access['starteronly']);
			}

			if (!empty($access['canseedelnotice']))
			{
				$result['canseedelnotice'] = array_merge($result['canseedelnotice'], $access['canseedelnotice']);
			}

			if (!empty($access['owndeleted']))
			{
				$result['owndeleted'] = array_merge($result['owndeleted'], $access['owndeleted']);
			}
		}

		if (!empty($this->groupInTopic))
		{
			$gitMember = array();
			$gitModerate = array();

			foreach ($this->groupInTopic AS $nodeid => $groups)
			{
				foreach($groups AS $groupid)
				{
					if ($this->permissionContext->getChannelPerm($groupid, 'moderatorpermissions', 'canmoderateposts', $nodeid))
					{
						$gitModerate[] = $nodeid;
					}
					else if ($this->permissionContext->getChannelPerm($groupid, 'forumpermissions', 'canview', $nodeid))
					{
						$gitMember[] = $nodeid;
					}
				}
			}
			//first remove perms already superceded
			$gitMember = array_diff($gitMember, $gitModerate, $result['canmoderate']);
			//Now merge the new values.
			$result['canmoderate'] = array_merge($result['canmoderate'], $gitModerate);
			$result['member'] = $gitMember;
		}

		$pmquota = $this->getLimit('pmquota');
		$vboptions = vB::getDatastore($this->userid)->getValue('options');
		$canUsePmSystem = ($vboptions['enablepms'] AND $pmquota);
		if (!$canUsePmSystem)
		{
			// Private Messages are disabled
			$pmnodeid = vB_Api::instance('node')->fetchPMChannel();
			$keys = array_keys($result['canview'], $pmnodeid);
			foreach ($keys AS $key)
			{
				unset($result['canview'][$key]);
				unset($result['canalwaysview'][$key]);
			}
		}

		if (!$this->hasPermission('genericpermissions', 'canviewmembers'))
		{
			// No permission to view VMs if one can't view profiles
			$vmnodeid = vB_Api::instance('node')->fetchVMChannel();
			$keys = array_keys($result['canview'], $vmnodeid);
			foreach ($keys AS $key)
			{
				unset($result['canview'][$key]);
				unset($result['canalwaysview'][$key]);
			}
		}

		/**
		 * Add forums which this user has specific moderator permissions for.
		 */
		$canmoderateposts = $this->checkModPerm('moderatorpermissions', 'canmoderateposts');
		$canremoveposts = $this->checkModPerm('moderatorpermissions', 'canremoveposts');

		$result['canmoderate'] = array_merge($result['canmoderate'], $canmoderateposts);
		$result['canseedelnotice'] = array_merge($result['canseedelnotice'], $canremoveposts);

		$this->channelAccess = $result;
		return $result;
	}

	/** Convenience- since userContext is available everywhere, this calls permissionContext->public rebuildGroupAccess
	 *
	 *
	 */
	public function rebuildGroupAccess()
	{
		$this->permissionContext->rebuildGroupAccess();
		$this->clearChannelPermissions();
	}

	/**
	 * Gets user hierarchy level
	 * It is used when ignoring a user, the hierarchy is as follows (VBV-1503):
	 *
	 * SuperAdmin -> Admin -> Super moderator -> Moderator -> Regular user
	 */
	public function getUserLevel()
	{
		$level = 0;

		if ($this->userIsSuperAdmin)
		{
			return self::USERLEVEL_SUPERADMIN;
		}

		if ($this->isAdministrator())
		{
			return self::USERLEVEL_ADMIN;
		}

		if ($this->hasPermission('adminpermissions', 'ismoderator'))
		{
			return self::USERLEVEL_SUPERMODERATOR;
		}

		if ($this->isModerator())
		{
			return self::USERLEVEL_MODERATOR;
		}

		return ($this->userid == 0) ? self::USERLEVEL_GUEST : self::USERLEVEL_REGUSER;
	}

	/** Fetches an array of permissions for a list of channels, which will be passed in slightly adjusted form to the getFullContent methods
	 *
	 * 	@param	mixed	array of integers
	 *
	 * @return	mixed	array of permission. parentid => permissions. permissions are either 0/1 or array of string => 0/1
	 */
	public function fetchPermsForChannels($channels)
	{
		if (empty($this->globalPerms))
		{
			$this->globalPerms = array('caneditposts' => $this->hasPermission('moderatorpermissions', 'caneditposts'),
			'canmanageownprofile' => $this->hasPermission('visitormessagepermissions', 'canmanageownprofile'),
			'caneditownmessages' => $this->hasPermission('visitormessagepermissions', 'caneditownmessages'),
			'is_superadmin' =>$this->userIsSuperAdmin);
		}
		$result = array('global' => $this->globalPerms);
		$channelType = vB_Types::instance()->getContentTypeID('vBForum_Channel');
		foreach($channels AS $channel)
		{
			$canpost = (isset($this->canPost[$channel])) ? $this->canPost[$channel] : 1;
			//Now the templates don't handle true/false well, because they get converted to strings. So better to cast booleans as int's
			$result[$channel] = array(
				'canmove'				=> (int)$this->getChannelPermission('forumpermissions', 'canmove', $channel),
				'canopenclose'			=> (int)$this->getChannelPermission('forumpermissions', 'canopenclose', $channel),
				'caneditposts'			=> (int)$this->getChannelPermission('moderatorpermissions', 'caneditposts', $channel),
				'caneditown'			=> (int)$this->getChannelPermission('forumpermissions', 'caneditpost', $channel),
				'candeleteownpost'		=> (int)$this->getChannelPermission('forumpermissions', 'candeletepost', $channel),
				'candeleteownthread'	=> (int)$this->getChannelPermission('forumpermissions', 'candeletethread', $channel),
				'canmoderateposts'		=> (int)$this->getChannelPermission('moderatorpermissions', 'canmoderateposts', $channel),
				'canremoveposts'		=> (int)$this->getChannelPermission('moderatorpermissions', 'canremoveposts', $channel),
				'canvote'				=> (int)$this->getChannelPermission('forumpermissions', 'canvote', $channel),
				'canviewthreads' 		=> (int)$this->getChannelPermission('forumpermissions', 'canviewthreads', $channel),
				'cancreate'				=> $this->getCanCreate($channel),
				'canreplyown' 			=> (int)$this->getChannelPermission('forumpermissions', 'canreplyown', $channel),
				'canreplyothers' 		=> (int)$this->getChannelPermission('forumpermissions', 'canreplyothers', $channel),
				'canconfigchannel'		=> (int)$this->getChannelPermission('forumpermissions2', 'canconfigchannel', $channel),
				'moderate'				=> $this->getModeratorPerms(array('nodeid' =>$channel, 'contenttypeid' => $channelType)),
				'limits' 				=> $this->getChannelLimits($channel),
				'canpost'				=> (int)$canpost,
			);
			foreach ($result[$channel]['cancreate'] AS $key => $value)
			{
				$result[$channel]['cancreate'][$key] = (int)$value;
			}

			if (!empty($result[$channel]['moderate']))
			{
				foreach ($result[$channel]['moderate'] AS $key => $value)
				{
					$result[$channel]['moderate'][$key] = (int)$value;
				}
			}
		}
		return $result;
	}

	/** Check for attachment limits/permissions for a given extension.
	 *	Note: This is only constrained to the attachmenttype and attachmentpermission tables. The create permissions
	 *	for all attachments are handled in the channels.
	 *	TODO: Make this part of the channel permissions
	 *
	 *	@param	string	the extension to check for
	 *	@param	int		node id. in case of groupInTopic
	 *
	 *	@return	mixed	false if the extension is not allowed or the user doesn't have permission to use it. Otherwise an array of limits.
	 */
	public function getAttachmentPermissions($extension, $nodeid = false)
	{
		static $channelType = false;

		if ($channelType == false)
		{
			$channelType = vB_Types::instance()->getContentTypeID('vBForum_Channel');
		}


		$usergroups = $this->usergroups;

		$returnValue = false;

		if (($nodeid !== false) AND !empty($this->groupInTopic))
		{
			//groupintopic is set at the channel level. So let's get this node's channel.
			$node = vB_Library::instance('node')->getNodeBare($nodeid);

			if ($node['contenttypeid'] == $channelType)
			{
				$gitNodeid = $nodeid;
			}
			else if ($node['starter'] == $nodeid)
			{
				$gitNodeid = $node['parentid'];
			}
			else
			{
				$starter = vB_Library::instance('node')->getNodeBare($node['starter']);
				$gitNodeid = $starter['parentid'];
			}

			//see if we have at the node level
			if (array_key_exists($gitNodeid, $this->groupInTopic))
			{
				foreach ($this->groupInTopic[$gitNodeid] as $usergroupid)
				{
					if (!in_array($usergroupid, $usergroups))
					{
						$usergroups[] = $usergroupid;
					}
				}
			}
		}

		foreach ($usergroups AS $usergroupid)
		{
			$permission = $this->permissionContext->getAttachmentPermissions($usergroupid, $extension);

			if ($returnValue === false)
			{
				$returnValue = $permission;
			}
			else if ($permission === false)
			{
				// Don't need to do anything here...
			}
			else
			{
				// Get the max values for all the fields. 0 = no limit.
				foreach (array_keys($permission) AS $dimension)
				{
					if (($returnValue[$dimension] == 0) OR ($permission[$dimension] == 0))
					{
						$returnValue[$dimension] = 0;
					}
					else
					{
						$returnValue[$dimension] = max($returnValue[$dimension], $permission[$dimension]);
					}
				}
			}
		}

		return $returnValue;
	}
}
