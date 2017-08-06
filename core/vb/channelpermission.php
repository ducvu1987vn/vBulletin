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
 * Channel Permissions interface
 * Provides methods used in admincp to read and set channel- specific permissions.
 *  *
 * @version $Revision: 29650 $
 * @since $Date: 2012-04-04 15:39:20 +0000 (Wed, 25 Feb 2009) $
 * @copyright vBulletin Solutions Inc.
 */

class vB_ChannelPermission
{

	const TYPE_BITMAP = 1;
	const TYPE_HOURS = 2;
	const TYPE_COUNT = 3;
	const TYPE_BOOL = 4;

	//If you change this, make sure you check the userContext $countFields list/
	protected static $permissionFields = array(
		'forumpermissions'     => self::TYPE_BITMAP,
		'forumpermissions2'     => self::TYPE_BITMAP,
		'moderatorpermissions' => self::TYPE_BITMAP,
		'createpermissions'    => self::TYPE_BITMAP,
		'edit_time'            => self::TYPE_HOURS,
		'require_moderate'     => self::TYPE_BOOL,
		'maxtags'              => self::TYPE_COUNT,
		'maxstartertags'       => self::TYPE_COUNT,
		'maxothertags'         => self::TYPE_COUNT,
		'maxattachments'       => self::TYPE_COUNT,
		'maxchannels'		   => self::TYPE_COUNT,
		'channeliconmaxsize'   => self::TYPE_COUNT
	);

	/**Not all the bitfields are currently used. These are the ones currently used **/
	protected static $bitfieldsUsed = array(
		'forumpermissions' => array(
			'candeletetagown',
			'cangetattachment',
			'canmove',
			'canpostnew',
			'canpostattachment',
			'cantagothers',
			'cantagown',
			'canopenclose',
			'canview',
			'maxattachments',
			'canvote',
			'canviewothers',
			'canviewthreads',
			'canreplyown',
			'canreplyothers',
			'caneditpost',
			'candeletepost',
			'candeletethread',
			'canseedelnotice',
			'canjoin',
			'followforummoderation',
			'canuploadchannelicon',
			'cananimatedchannelicon',
		),
		'forumpermissions2' => array(
			'canalwaysview',
			'canalwayspost',
			'canalwayspostnew',
			'canconfigchannel',
			'canusehtml',
			'canpublish',
		),
		'moderatorpermissions' => array(
			'canaddowners',
			'canbanusers',
			'caneditposts',
			'canopenclose',
			'canmanagethreads',
			'canmoderateattachments',
			'canmoderateposts',
			'canmoderatetags',
			'canremoveposts',
			'cansetfeatured',
			'canmassmove',
			'canviewips',
	));

	protected static $permissionPhrases = array(
		'forumpermissions'     => 'forum_permissions',
		'forumpermissions2'    => 'forum_permissions',
		'moderatorpermissions' => 'moderator_permissions',
		'createpermissions'    => 'create_permissions',
		'edit_time'            => 'edit_time_limit',
		'require_moderate'     => 'require_moderation',
		'maxtags'              => 'max_tags',
		'maxstartertags'       => 'max_starter_tags',
		'maxothertags'         => 'max_other_tags',
		'maxattachments'       => 'max_attachments',
		'maxchannels'		   => 'max_channels',
		'channeliconmaxsize'   => 'channel_icon_max_size'
	);


	/**This is the source of each bitmask array  **/
	protected static $bitfieldGroup = array(
		'forumpermissions'     => 'bf_ugp',
		'forumpermissions2'    => 'bf_ugp',
		'moderatorpermissions' => 'misc',
		'createpermissions'    => 'bf_ugp',
	);


	protected static $instance;

	/**list of the usergroups defined for this site **/
	protected static $usergroups;

	/**The bitmap values **/
	protected $permSettings;

	protected $defaultPermissions = array();

	protected function __construct()
	{
		//We need the usergroups and the permission bitmaps.
		$usergroups = vB_Api::instanceInternal('usergroup')->fetchUsergroupList($flushcache = false);

		foreach ($usergroups AS $usergroup)
		{
			self::$usergroups[$usergroup['usergroupid']] = $usergroup['title'];
		}
		$this->permSettings = array(
			'forumpermissions'     => array(),
			'forumpermissions2'     => array(),
			'moderatorpermissions' =>  array(),
			'createpermissions'    =>  array()
		);

		$parser = new vB_XML_Parser(false, DIR . '/includes/xml/bitfield_vbulletin.xml');
		$bitfields = $parser->parse();

		foreach ($bitfields['bitfielddefs']['group'] AS $topGroup)
		{
			if (($topGroup['name'] == 'ugp') OR ($topGroup['name'] == 'misc'))
			{
				foreach ($topGroup['group'] AS $group)
				{
					switch($group['name'])
					{
						case 'forumpermissions' :
						case 'forumpermissions2' :
						case 'moderatorpermissions' :
							foreach ($group['bitfield'] as $fielddef)
							{
								if (!empty($fielddef['intperm']))
								{
									continue;
								}

								if (in_array($fielddef['name'], self::$bitfieldsUsed[$group['name']]))
								{
									$fielddef['used'] = 1;
								}
								else
								{
									$fielddef['used'] = 0;
								}
								$this->permSettings[$group['name']][$fielddef['name']] = $fielddef;
							}

						break;

						case 'createpermissions' :
							foreach ($group['bitfield'] as $fielddef)
							{
								$fielddef['used'] = 1;
								$this->permSettings[$group['name']][$fielddef['name']] = $fielddef;
							}
						break;
					}
				}
			}
		}
		
	}

	/**
	 * Returns singleton instance of self.
	 *
	 * @return vB_ChannelPermission		- Reference to singleton instance of the type handler
	 */
	public static function instance()
	{
		if (!isset(self::$instance))
		{
			$class = __CLASS__;
			self::$instance = new $class();
		}

		return self::$instance;
	}


	/** this formats the result of a query
	 *
	 * 	@param	mixed		a query result object
	 *
	 * 	@return	mixed		array of $groupid => array(permission fields). Empty array if no permissions set for that node.
	 ***/
	protected function formatPermissions($permissions)
	{
		$results = array();

		foreach($permissions AS $permission)
		{
			if (isset($results[$permission['groupid']]))
			{
				// We already have set the permission for this group.
				// vBForum:getChannelPermissionsForAllGroups will return all permissions for
				// for each group and node's ancestors ordered by depth descending.
				continue;
			}
			$results[$permission['groupid']] = $permission;
			$results[$permission['groupid']]['title'] = self::$usergroups[$permission['groupid']];
			$results[$permission['groupid']]['bitfields'] = array();
			foreach ($this->permSettings AS $permgroup => $bitfields)
			{
				$results[$permission['groupid']]['bitfields'][$permgroup] = array();
				foreach ($bitfields AS $fieldDef)
				{
					$bitfield = array(
						'name' => $fieldDef['name'],
						'value' => $fieldDef['value'],
						'used' => $fieldDef['used']);
					if (!empty($fieldDef['phrase']))
					{
						$bitfield['phrase'] = $fieldDef['phrase'];
					}

					if (((int)$fieldDef['value'] & (int)$permission[$permgroup]) > 0)
					{
						$bitfield['set'] = 1;
					}
					else
					{
						$bitfield['set'] = 0;
					}
					$results[$permission['groupid']]['bitfields'][$permgroup][] = $bitfield;
				}
			}
		}

		return $results;

	}

	/** this returns a specific permission setting
	 *
	 * 	@param	integer		the node for which we are checking permissions
	 * 	@param	integer		optional- limit results to this usergroup
	 *
	 * 	@return	mixed		array of $groupid => array(permission fields). Its parent node's permissions if no permissions set for that node.
	 ***/
	public function fetchPermissions($nodeid, $groupid = false)
	{
		//validate the data.
		if (!intval($nodeid))
		{
			return false;
		}

		if ($groupid)
		{
			if (!intval($nodeid))
			{
				return false;
			}
			$qryResult = vB::getDbAssertor()->assertQuery('vBForum:getChannelPermissionsByGroup', array(
				'nodeid' => $nodeid,
				'groupid' => $groupid
			));
		}
		else
		{
			$qryResult = vB::getDbAssertor()->assertQuery('vBForum:getChannelPermissionsForAllGroups', array(
				'nodeid' => $nodeid
			));
		}

		$permissions = array();

		if ($groupid AND (!$qryResult OR !$qryResult->valid()))
		{
			// We have a new group which doesn't have permissions yet.
			// Default to the group permission for the root node on the registered group.
			$qryResult = vB::getDbAssertor()->assertQuery('vBForum:getChannelPermissionsByGroup', array(
				'nodeid' => vB_Api::instanceInternal('content_channel')->fetchChannelIdByGUID(vB_Channel::MAIN_CHANNEL),
				'groupid' => vB_Api_UserGroup::REGISTERED_SYSGROUPID
			));
		}
		if ($qryResult AND $qryResult->valid())
		{
			$permissions = self::formatPermissions($qryResult);
			foreach($permissions AS $permGroupid => $groupPermissions)
			{
				if ($nodeid != $groupPermissions['nodeid'])
				{
					unset($permissions[$permGroupid]['permissionid']);
					$permissions[$permGroupid]['nodeid'] = $nodeid;
				}
				if ($groupid != false AND $groupid != $groupPermissions['groupid'])
				{
					$permissions[$permGroupid]['groupid'] = $groupid;
					$permissions[$groupid] = $permissions[$permGroupid]; // be consistent with the groupid as the key
					unset($permissions[$permGroupid]);
				}
			}
			reset($permissions); // For those that use current() and the like
		}
		return $permissions;
	}


	/** this sets permissions for a node and user.
	 *
	 * 	@param	integer		the node for which we are setting permissions
	 * 	@param	integer		usergroup for which we are setting permissions
	 * 	@param	mixed		array of changed permissions. If the permission is not set and not in this array it will be set to zero.
	 *  @param  bool        Whether to update default permissions stored in datastore
	 *
	 * 	@return	mixed		true or an error array.
	 ***/
	public function setPermissions($nodeid, $groupid, $permissions, $updatedefault = false)
	{

		$params = array();
		foreach (self::$permissionFields AS $fieldname => $fieldtype)
		{

			switch ($fieldtype)
			{
				case self::TYPE_COUNT:
				case self::TYPE_HOURS:
					if (isset($permissions[$fieldname]))
					{
						$params[$fieldname] = intval($permissions[$fieldname]);
					}
				break;
				case self::TYPE_BITMAP :
					if (isset($permissions[$fieldname]) AND is_numeric($permissions[$fieldname]))
					{
						$params[$fieldname] = $permissions[$fieldname];
					}
					else if (isset($permissions[$fieldname]))
					{
						$bitValue = 0;
						foreach ($this->permSettings[$fieldname] AS $key => $bitfield)
						{
							if (!empty($permissions[$fieldname][$bitfield['name']]) AND intval($permissions[$fieldname][$bitfield['name']]))
							{
								$bitValue += $bitfield['value'];
							}
						}
						$params[$fieldname] = $bitValue;
					}
					break;
				case self::TYPE_BOOL:
					if (isset($permissions[$fieldname]))
					{
						$params[$fieldname] = (bool)$permissions[$fieldname];
					}
					break;

			}
		}

		if ($updatedefault)
		{
			// Make the array string keys so that we can use array_merge_recursive() easily
			$this->defaultPermissions["node_$nodeid"]["group_$groupid"] = $params;
		}

		if (!empty($params))
		{
			//First we need to know if we're saving or updating.
			if (!empty($permissions['permissionid']))
			{
				$params[vB_dB_Query::TYPE_KEY] = vB_dB_Query::QUERY_UPDATE;
				$params['permissionid'] = $permissions['permissionid'];
			}
			else
			{
				$existing = vB::getDbAssertor()->assertQuery('vBForum:permission',
				array('nodeid' => $nodeid, 'groupid' => $groupid,
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT));

				if ($existing AND $existing->valid())
				{
					$params[vB_dB_Query::TYPE_KEY] = vB_dB_Query::QUERY_UPDATE;
					$permRecord = $existing->current();
					$params['permissionid'] = $permRecord['permissionid'];
				}
				else
				{
					$params[vB_dB_Query::TYPE_KEY] = vB_dB_Query::QUERY_INSERT;
					$params['nodeid'] = $nodeid;
					$params['groupid'] = $groupid;
				}
			}
			vB_Cache::instance()->event('perms_changed');
			$id = vB::getDbAssertor()->assertQuery('vBForum:permission', $params);
			vB::getUserContext()->rebuildGroupAccess();
			return $id;
		}
		return false;
	}


	/** this returns the basic settings array.
	 *
	 *
	 * 	@return	mixed		the permSettings array
	 ***/
	public function fetchPermSettings()
	{
		return $this->permSettings;
	}


	/** this returns a permission setting by permissionid
	 *
	 * 	@param	integer		the permissionid
	 *
	 * 	@return	mixed		array of $groupid => array(permission fields). Empty array if no permissions set for that node.
	 ***/
	public function fetchPermById($permissionid)
	{
		//validate the data.
		if (!intval($permissionid))
		{
			return false;
		}
		$qryResult = vB::getDbAssertor()->assertQuery('vBForum:permission', array(vB_dB_Query::TYPE_KEY =>  vB_dB_Query::QUERY_SELECT, 'permissionid' => $permissionid));

		if ($qryResult AND $qryResult->valid())
		{

			return self::formatPermissions($qryResult);
		}

	}

	/** this returns the fields and types.
	 *
	 * 	@return	mixed		the $permissionFields array
	 ***/
	public static function fetchPermFields()
	{
		return self::$permissionFields;
	}

	/** this returns the fields and types.
	 *
	 * 	@return	mixed		the $permissionFields array
	 ***/
	public static function fetchPermPhrases()
	{
		return self::$permissionPhrases;
	}

	/** this deletes an existing permission
	 *
	 * 	@return	mixed		either permissionid(single or array), or nodeid and usergroupid. A single Nodeid is required and usergroup is optional and may be an array
	 ***/
	public function deletePerms($params)
	{
		if (!empty($params['permissionid']))
		{
			//We don't allow deleting permissions from page 1.
			$existing = vB::getDbAssertor()->getRow('vBForum:permission',
				array('permissionid' => $params['permissionid']));
			if (empty($existing) OR !empty($existing['errors']) OR ($existing['nodeid'] == 1))
			{
				return false;
			}
			$qryParams['permissionid'] = $params['permissionid'];
		}
		else if (!empty($params['nodeid']) AND intval($params['nodeid']))
		{
			$qryParams['nodeid'] = intval($params['nodeid']);
			if (!empty($params['groupid']))
			{
				$qryParams['groupid'] = $params['groupid'];
			}
		}
		else
		{
			return false;
		}
		$qryParams[vB_dB_Query::TYPE_KEY] = vB_dB_Query::QUERY_DELETE;
		$result = vB::getDbAssertor()->assertQuery('vBForum:permission', $qryParams);
		vB_Cache::instance()->event('perms_changed');
		//force reloading the group access cache
		vB::getUserContext()->rebuildGroupAccess();
		return $result;
	}

	public function buildDefaultChannelPermsDatastore()
	{
		if (empty($this->defaultPermissions))
		{
			return;
		}

		$datastore = vB::getDatastore();

		$datastore->fetch('defaultchannelpermissions');

		$currentpermissions = $datastore->getValue('defaultchannelpermissions');

		if (!$currentpermissions)
		{
			$currentpermissions = array();
		}

		$currentpermissions = array_merge_recursive($currentpermissions, $this->defaultPermissions);

		$datastore->build('defaultchannelpermissions', serialize($currentpermissions), 1);

	}

	/**
	 * Load default channel permissions
	 *
	 * @param $nodeid int Node ID
	 * @param $groupid int Group ID
	 * @return array Channel permissions
	 */
	public static function loadDefaultChannelPermissions($nodeid = 0, $groupid = 0)
	{
		vB::getDatastore()->fetch('defaultchannelpermissions');

		$permissions = vB::getDatastore()->getValue('defaultchannelpermissions');

		if (empty($nodeid) AND empty($groupid))
		{
			return $permissions;
		}

		return $permissions["node_" . intval($nodeid)]["group_" . intval($groupid)];
	}

	/**
	 * Compare current channel permissions with default ones.
	 *
	 * @param $nodeid int Node ID
	 * @param $groupid int Group ID
	 * @param array $currentpermissioncache If set the function won't try to load
	 *
	 * @return bool If current permissions are modified, return true. Otherwise false.
	 */
	public static function compareDefaultChannelPermissions($nodeid, $groupid, $currentpermissioncache = array())
	{
		$nodeid = intval($nodeid);
		$groupid = intval($groupid);

		if (empty($currentpermissioncache))
		{
			$currentpermissioncache = vB::getDbAssertor()->getRow('vBForum:permission', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'nodeid' => $nodeid,
				'groupid' => $groupid,
			));
		}

		if (empty($currentpermissioncache))
		{
			return true;
		}

		$defaultpermissions = self::loadDefaultChannelPermissions($nodeid, $groupid);

		if (empty($defaultpermissions))
		{
			return true;
		}

		foreach ($defaultpermissions as $k => $v)
		{
			if ($v != $currentpermissioncache[$k])
			{
				return true;
			}
		}

		return false;
	}
}
