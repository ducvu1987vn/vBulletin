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
* Class to do data save/delete operations for MODERATORS
*
* Example usage (inserts a new moderator):
*
* $f = new vB_DataManager_Moderator();
* $f->set_info('moderatorid', 12);
* $f->save();
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 16:38:49 -0200 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_Moderator extends vB_DataManager
{
	/**
	* Array of recognised and required fields for moderators, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'moderatorid'  => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_INCR, vB_DataManager_Constants::VF_METHOD, 'verify_nonzero'),
		'userid'       => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_YES,  vB_DataManager_Constants::VF_METHOD),
		'nodeid'      => array(vB_Cleaner::TYPE_INT,        vB_DataManager_Constants::REQ_YES,  vB_DataManager_Constants::VF_METHOD),
		'permissions'  => array(vB_Cleaner::TYPE_UINT, vB_DataManager_Constants::REQ_YES,  vB_DataManager_Constants::VF_METHOD),
		'permissions2' => array(vB_Cleaner::TYPE_UINT, vB_DataManager_Constants::REQ_YES,  vB_DataManager_Constants::VF_METHOD),
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array(
		'permissions'  => 'bf_misc_moderatorpermissions',
		'permissions2' => 'bf_misc_moderatorpermissions2',
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'vBForum:moderator';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('moderatorid = %1$d', 'moderatorid');

	var $keyField = array('userid', 'nodeid');

	/**
	* Array to store stuff to save to moderator table
	*
	* @var	array
	*/
	var $moderator = array();

	var $config = array();

	protected $needRegistry = false;

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Moderator($registry = NULL, $errtype = vB_DataManager_Constants::ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($errtype);
		$this->config = vB::getConfig();

		// Legacy Hook 'moderatordata_start' Removed //
	}

	/**
	* Verifies that the specified user exists
	*
	* @param	integer	User ID
	*
	* @return 	boolean	Returns true if user exists
	*/
	function verify_userid(&$userid)
	{
		//if ($userinfo = $this->dbobject->query_first("SELECT * FROM " . TABLE_PREFIX . "user WHERE userid = $userid"))
		$userinfo = $this->assertor->getRow('user', array('userid' => $userid));
		if ($userinfo)
		{
			$this->info['user'] =& $userinfo;
			return true;
		}
		else
		{
			$this->error('no_users_matched_your_query');
			return false;
		}
	}

	/**
	* Verifies that the specified node exists
	*
	* @param	integer	ID of the node
	*
	* @return	boolean	Returns true if node exists
	*/
	function verify_nodeid(&$nodeid)
	{
		if (empty($nodeid))
		{
			return true;
		}

		$node = vB_Api::instanceInternal('node')->getNode($nodeid);
		if (!empty($node))
		{
			$this->info['node'] = $node;
			return true;
		}
		else
		{
			$this->error('invalid_channel_specified');
			return false;
		}
	}

	/**
	* Converts an array of 1/0 options into the permissions bitfield
	*
	* @param	mixed	Int OR Array of 1/0 values keyed with the bitfield names for the moderator permissions bitfield
	*
	* @return	boolean	Returns true on success
	*/
	function verify_permissions(&$permissions)
	{

		if (!is_array($permissions) AND intval($permissions))
		{
			return true;
		}
		require_once(DIR . '/includes/functions_misc.php');
		return $permissions = convert_array_to_bits($permissions, vB::getDatastore()->get_value('bf_misc_moderatorpermissions'));
	}

	/**
	* Converts an array of 1/0 options into the permissions bitfield
	*
	* @param	mixed	Int OR Array of 1/0 values keyed with the bitfield names for the moderator permissions bitfield
	*
	* @return	boolean	Returns true on success
	*/
	function verify_permissions2(&$permissions)
	{

		if (!is_array($permissions) AND intval($permissions))
		{
			return true;
		}
		require_once(DIR . '/includes/functions_misc.php');
		return $permissions = convert_array_to_bits($permissions, vB::getDatastore()->get_value('bf_misc_moderatorpermissions2'));
	}

	/**
	* Overriding version of the set() function to deal with the selecting userid from username
	*
	* @param	string	Name of the field
	* @param	mixed	Value of the field
	*
	* @return	boolean	Returns true on success
	*/
	function set($fieldname, $value, $clean = true, $doverify = true, $table = null)
	{
		switch ($fieldname)
		{
			case 'username':
			case 'modusername':
			{
				if ($value != '' AND $userinfo = $this->assertor->getRow('user', array('username' => $value)))
				{
					$this->do_set('userid', $userinfo['userid']);
					$this->info['user'] =& $userinfo;
					return true;
				}
				else
				{
					$this->error('no_users_matched_your_query_x', $value);
					return false;
				}
			}
			break;

			default:
			{
				return parent::set($fieldname, $value);
			}
		}
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (empty($this->condition))
		{
			// Check that we don't already have this user as a moderator in the node
			if ($this->assertor->getRow($this->table, array('nodeid' => $this->fetch_field('nodeid'), 'userid' => $this->fetch_field('userid'))))
			{
				$this->error('this_user_is_already_a_moderator');
				return false;
			}
		}

		$return_value = true;
		// Legacy Hook 'moderatordata_presave' Removed //

		$this->presave_called = $return_value;
		return $return_value;
	}


	/**
	 * Additional data to update after a save call (such as denormalized values in other tables).
	 * In batch updates, is executed once after all records are updated.
	 *
	 * @param	boolean	Do the query?
	 */
	function post_save_once($doquery = true)
	{
		parent::post_save_once($doquery);

		if (!empty($this->moderator['userid']))
		{
			vB_Cache::allCacheEvent('userChg_'. $this->moderator['userid']);
		}
		vB::getUserContext()->rebuildGroupAccess();
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		$moderatorid = $this->fetch_field('moderatorid');

		// update usergroupid / membergroupids
		if (!$this->condition AND !in_array($this->moderator['userid'], explode(',', $this->config['SpecialUsers']['undeletableusers'])) AND can_administer('canadminusers'))
		{
			$update_usergroupid = ($this->info['usergroupid'] > 0);
			$update_membergroup = (!empty($this->info['membergroupids']) AND is_array($this->info['membergroupids']));

			if ($update_usergroupid OR $update_membergroup)
			{
				$userdata = new vB_Datamanager_User($this->registry, vB_DataManager_Constants::ERRTYPE_SILENT);
				if (!$this->info['user'] AND $this->moderator['userid'])
				{
					$this->info['user'] = fetch_userinfo($this->moderator['userid']);
				}
				$userdata->set_existing($this->info['user']);
				cache_permissions($this->info['user'], false);

				$displaygroupid = $update_usergroupid ? $this->info['usergroupid'] : $this->info['user']['displaygroupid'];
				$this->usergroupcache = vB::getDatastore()->get_value('usergroupcache');
				$userdata->set_usertitle(
					($this->info['user']['customtitle'] ? $this->info['user']['usertitle'] : ''),
					false,
					$this->usergroupcache["$displaygroupid"],
					($this->info['user']['customtitle'] == 1 OR $this->info['user']['permissions']['genericpermissions'] & $this->bf_ugp_genericpermissions['canusecustomtitle']) ? true : false,
					($this->info['user']['customtitle'] == 1) ? true : false
				);

				$userdata->set_failure_callback(array(&$this, 'update_user_failed_insert'));
				if ($update_usergroupid)
				{
					$userdata->set('usergroupid', $this->info['usergroupid']);
					$userdata->set('displaygroupid', $this->info['usergroupid']);
				}
				if ($update_membergroup)
				{
					$membergroupids = preg_split('#,#', $this->info['user']['membergroupids'], -1, PREG_SPLIT_NO_EMPTY);
					$membergroupids = array_unique(array_merge($membergroupids, $this->info['membergroupids']));
					if ($key = array_search($this->info['user']['usergroupid'], $membergroupids))
					{
						unset($membergroupids["$key"]);
					}
					sort($membergroupids);
					$userdata->set('membergroupids', $membergroupids);
				}
				if ($userdata->errors)
				{
					$this->errors = array_merge($this->errors, $userdata->errors);
					return;
				}
				$userdata->save();
			}
		}

		if (!$this->condition AND !$this->options['ignoremods'])
		{
			$rebuild_ignore_list = array();

			$ignored_moderators = $this->assertor->getRows('userlist', array('relationid' => $this->fetch_field('userid'), 'type' => 'ignore'));
			foreach ($ignored_moderators as $ignored_moderator)
			{
				$rebuild_ignore_list[] = $ignored_moderator['userid'];
			}

			if (!empty($rebuild_ignore_list))
			{
				require_once(DIR . '/includes/functions_databuild.php');
				$this->assertor->delete('userlist', array('relationid' => $this->fetch_field('userid'), 'type' => 'ignore'));
				foreach ($rebuild_ignore_list AS $userid)
				{
					build_userlist($userid);
				}
			}
		}

		// Legacy Hook 'moderatordata_postsave' Removed //
		vB_Cache::instance(vB_Cache::CACHE_FAST)->event('userPerms_' . $this->moderator['userid']);
	}

	/**
	* Deletes a moderator
	*
	* @return	mixed	The number of affected rows
	*/
	function delete($doquery = true)
	{
		$params = array();
		$params[vB_dB_Query::TYPE_KEY] = vB_dB_Query::QUERY_METHOD;
		$params['condition'] = $this->condition;
		$moderator = $this->assertor->getRow('getModeratorInfo', $params);
		if ($moderator)
		{
			if ($moderator['usergroupid'] == 7 AND !($morenodes = $this->assertor->getRow($this->table, array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'#filters' => array(
							array('field' => 'userid', 'value' => $moderator[userid], 'operator' => 'EQ'),
							array('field' => 'moderatorid', 'value' => $moderator[moderatorid], 'operator' => 'NE'),
							array('field' => 'nodeid', 'value' => 0, 'operator' => 'NE')
					)
					))))
			{
				$userdata = new vB_Datamanager_User($this->registry, vB_DataManager_Constants::ERRTYPE_SILENT);
				if (!$this->info['user'])
				{
					$userinfo = fetch_userinfo($this->fetch_field('userid'));
					$userdata->set_existing($userinfo);
				}
				else
				{
					$userdata->set_existing($this->info['user']);
				}
				$userdata->set_failure_callback(array(&$this, 'update_user_failed_update'));
				$userdata->set('usergroupid', 2);
				$userdata->set('displaygroupid', ($moderator['displaygroupid'] == 7 ? 0 : $moderator['displaygroupid']));
				if ($userdata->errors)
				{
					$this->errors = array_merge($this->errors, $userdata->errors);
					return 0;
				}
				$userdata->save();
			}

			// Legacy Hook 'moderatordata_delete' Removed //

			return $this->db_delete(TABLE_PREFIX, $this->table, $this->condition, $doquery);
		}
		else
		{
			$this->error('user_no_longer_moderator');
		}
	}

	/**
	* Callback function that is hit when inserting a new moderator, and his/her
	* usergroup or member groups fail to be changed.
	*/
	function update_user_failed_insert(&$user)
	{
		$this->condition = 'moderatorid = ' . $this->fetch_field('moderatorid');
		$this->delete();
		$this->condition = '';

		$this->errors = array_merge($this->errors, $user->errors);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/

