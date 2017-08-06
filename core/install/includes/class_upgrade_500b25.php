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
/*
if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}
*/

class vB_Upgrade_500b25 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500b25';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Beta 25';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Beta 24';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '';

	/**
	 * step 1 - Add maxchannels -- channel limit permission.
	 */
	function step_1()
	{
		if (!$this->field_exists('permission', 'maxchannels'))
		{
			$this->show_message($this->phrase['version']['500b24']['adding_maxchannel_field']);
			vB::getDbAssertor()->assertQuery('vBInstall:addMaxChannelsField');

			$usergroupinfo = vB::getDbAssertor()->getRows('vBForum:usergroup', array(
				vB_dB_Query::CONDITIONS_KEY => array(array('field' => 'maximumsocialgroups', 'value' => 0, vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_NE))
			));

			if (is_array($usergroupinfo)  AND !isset($usergroupinfo['errors']) AND !empty($usergroupinfo))
			{
				$updates = array();
				foreach ($usergroupinfo AS $ugp)
				{
					$updates[$ugp['usergroupid']] = $ugp['maximumsocialgroups'];
				}

				// do the actual update
				vB::getDbAssertor()->assertQuery('vBInstall:updateUGPMaxSGs', array('groups' => $updates));
			}
			else
			{
				$this->show_message($this->phrase['core']['process_done']);
				return;
			}
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * step 2 - Mapping social group member systemgroup permissions
	 */
	function step_2()
	{
		$assertor = vB::getDbAssertor();
		$sgmember = $assertor->assertQuery('vBForum:usergroup', array('systemgroupid' => 14));
		if ($sgmember AND $sgmember->valid())
		{
			$this->show_message($this->phrase['version']['500b24']['removing_sg_membergroup']);
			$moveperms = true;

			$sgmemberinfo = $sgmember->current();
			$sgparent = vB_Api::instance('content_channel')->fetchChannelIdByGUID(vB_Channel::DEFAULT_SOCIALGROUP_PARENT);
			$perm = $assertor->assertQuery('vBForum:permission',
				array('groupid' => $sgmemberinfo['usergroupid'], 'nodeid' => $sgparent)
			);
			$memberugp = $assertor->getRow('vBForum:usergroup', array('systemgroupid' => vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID));

			// make sure we don't have memberugp records on sg parent
			$assertor->assertQuery('vBForum:permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'nodeid', 'value' => $sgparent, 'operator' => vB_dB_Query::OPERATOR_EQ),
					array('field' => 'groupid', 'value' => $memberugp['usergroupid'], 'operator' => vB_dB_Query::OPERATOR_EQ)
				)
			));

			if ($perm AND $perm->valid())
			{
				// if we have permission sets at sg channel then just change the group id
				$perminfo = $perm->current();
				$assertor->assertQuery('vBForum:permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					vB_dB_Query::CONDITIONS_KEY => array(array('field' => 'permissionid', 'value' => $perminfo['permissionid'])),
					'groupid' => $memberugp['usergroupid']
				));
			}
			else
			{
				//  but if we don't we take'em from root node
				$perm = $assertor->assertQuery('vBForum:permission',
					array('groupid' => $sgmemberinfo['usergroupid'], 'nodeid' => vB_Api::instance('content_channel')->fetchChannelIdByGUID(vB_Channel::MAIN_CHANNEL))
				);

				if ($perm AND $perm->valid())
				{
					$permission = $perm->current();
					$assertor->assertQuery('vBForum:permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
						vB_dB_Query::CONDITIONS_KEY => array(array('field' => 'permissionid', 'value' => $permission['permissionid'])),
						'nodeid' => vB_Api::instance('content_channel')->fetchChannelIdByGUID(vB_Channel::DEFAULT_SOCIALGROUP_PARENT), 'groupid' => $memberugp['usergroupid']
					));
				}
			}

			// remove the rest of permissions, update channel group info, and change GIT records to make sense
			$assertor->assertQuery('vBForum:permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, 'groupid' => $sgmemberinfo['usergroupid']));
			$assertor->assertQuery('vBForum:usergroup', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				vB_dB_Query::CONDITIONS_KEY => array(array('field' => 'systemgroupid', 'value' => vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID)),
				'title' => $this->phrase['install']['channelmember_title']
			));
			$assertor->assertQuery('vBForum:groupintopic', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				vB_dB_Query::CONDITIONS_KEY => array(array('field' => 'groupid', 'value' => $sgmemberinfo['usergroupid'])),
				'groupid' => $memberugp['usergroupid']
			));
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * step 3 - Mapping social group moderator systemgroup permissions
	 */
	function step_3()
	{
		$assertor = vB::getDbAssertor();
		$sgmod = $assertor->assertQuery('vBForum:usergroup', array('systemgroupid' => 13));
		if ($sgmod AND $sgmod->valid())
		{
			$this->show_message($this->phrase['version']['500b24']['removing_sg_modgroup']);
			$moveperms = true;

			$sgmodinfo = $sgmod->current();
			$sgparent = vB_Api::instance('content_channel')->fetchChannelIdByGUID(vB_Channel::DEFAULT_SOCIALGROUP_PARENT);
			$perm = $assertor->assertQuery('vBForum:permission',
				array('groupid' => $sgmodinfo['usergroupid'], 'nodeid' => $sgparent)
			);
			$modupg = $assertor->getRow('vBForum:usergroup', array('systemgroupid' => vB_Api_UserGroup::CHANNEL_MODERATOR_SYSGROUPID));

			// make sure we don't have modugp records on sg parent
			$assertor->assertQuery('vBForum:permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'nodeid', 'value' => $sgparent, 'operator' => vB_dB_Query::OPERATOR_EQ),
					array('field' => 'groupid', 'value' => $modupg['usergroupid'], 'operator' => vB_dB_Query::OPERATOR_EQ)
				)
			));

			if ($perm AND $perm->valid())
			{
				// if we have permission sets at sg channel then just change the group id
				$perminfo = $perm->current();
				$assertor->assertQuery('vBForum:permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					vB_dB_Query::CONDITIONS_KEY => array(array('field' => 'permissionid', 'value' => $perminfo['permissionid'])),
					'groupid' => $modupg['usergroupid']
				));
			}
			else
			{
				//  but if we don't we take'em from root node
				$perm = $assertor->assertQuery('vBForum:permission',
					array('groupid' => $sgmodinfo['usergroupid'], 'nodeid' => vB_Api::instance('content_channel')->fetchChannelIdByGUID(vB_Channel::MAIN_CHANNEL))
				);

				if ($perm AND $perm->valid())
				{
					$permission = $perm->current();
					$assertor->assertQuery('vBForum:permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
						vB_dB_Query::CONDITIONS_KEY => array(array('field' => 'permissionid', 'value' => $permission['permissionid'])),
						'nodeid' => vB_Api::instance('content_channel')->fetchChannelIdByGUID(vB_Channel::DEFAULT_SOCIALGROUP_PARENT), 'groupid' => $modupg['usergroupid']
					));
				}
			}

			// remove the rest of permissions, update channel group info, and change GIT records to make sense
			$assertor->assertQuery('vBForum:permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, 'groupid' => $sgmodinfo['usergroupid']));
			$assertor->assertQuery('vBForum:usergroup', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				vB_dB_Query::CONDITIONS_KEY => array(array('field' => 'systemgroupid', 'value' => vB_Api_UserGroup::CHANNEL_MODERATOR_SYSGROUPID)),
				'title' => $this->phrase['install']['channelmod_title']
			));
			$assertor->assertQuery('vBForum:groupintopic', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				vB_dB_Query::CONDITIONS_KEY => array(array('field' => 'groupid', 'value' => $sgmodinfo['usergroupid'])),
				'groupid' => $modupg['usergroupid']
			));
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * step 4 - Mapping social group owner systemgroup permissions
	 */
	function step_4()
	{
		$assertor = vB::getDbAssertor();
		$sgowner = $assertor->assertQuery('vBForum:usergroup', array('systemgroupid' => 12));
		if ($sgowner AND $sgowner->valid())
		{
			$this->show_message($this->phrase['version']['500b24']['removing_sg_ownergroup']);
			$moveperms = true;

			$sgownerinfo = $sgowner->current();
			$sgparent = vB_Api::instance('content_channel')->fetchChannelIdByGUID(vB_Channel::DEFAULT_SOCIALGROUP_PARENT);
			$perm = $assertor->assertQuery('vBForum:permission',
				array('groupid' => $sgownerinfo['usergroupid'], 'nodeid' => $sgparent)
			);
			$ownerugp = $assertor->getRow('vBForum:usergroup', array('systemgroupid' => vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID));

			// make sure we don't have modugp records on sg parent
			$assertor->assertQuery('vBForum:permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'nodeid', 'value' => $sgparent, 'operator' => vB_dB_Query::OPERATOR_EQ),
					array('field' => 'groupid', 'value' => $ownerugp['usergroupid'], 'operator' => vB_dB_Query::OPERATOR_EQ)
				)
			));

			if ($perm AND $perm->valid())
			{
				// if we have permission sets at sg channel then just change the group id
				$perminfo = $perm->current();
				$assertor->assertQuery('vBForum:permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					vB_dB_Query::CONDITIONS_KEY => array(array('field' => 'permissionid', 'value' => $perminfo['permissionid'])),
					'groupid' => $ownerugp['usergroupid']
				));
			}
			else
			{
				//  but if we don't we take'em from root node
				$perm = $assertor->assertQuery('vBForum:permission',
					array('groupid' => $sgownerinfo['usergroupid'], 'nodeid' => vB_Api::instance('content_channel')->fetchChannelIdByGUID(vB_Channel::MAIN_CHANNEL))
				);

				if ($perm AND $perm->valid())
				{
					$permission = $perm->current();
					$assertor->assertQuery('vBForum:permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
						vB_dB_Query::CONDITIONS_KEY => array(array('field' => 'permissionid', 'value' => $permission['permissionid'])),
						'nodeid' => vB_Api::instance('content_channel')->fetchChannelIdByGUID(vB_Channel::DEFAULT_SOCIALGROUP_PARENT), 'groupid' => $ownerugp['usergroupid']
					));
				}
			}

			// remove the rest of permissions, update channel group info, and change GIT records to make sense
			$assertor->assertQuery('vBForum:permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, 'groupid' => $sgownerinfo['usergroupid']));
			$assertor->assertQuery('vBForum:usergroup', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				vB_dB_Query::CONDITIONS_KEY => array(array('field' => 'systemgroupid', 'value' => vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID)),
				'title' => $this->phrase['install']['channelowner_title']
			));
			$assertor->assertQuery('vBForum:groupintopic', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				vB_dB_Query::CONDITIONS_KEY => array(array('field' => 'groupid', 'value' => $sgownerinfo['usergroupid'])),
				'groupid' => $ownerugp['usergroupid']
			));
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * step 5 - Remove social group system usergroups
	 */
	function step_5()
	{
		$this->show_message($this->phrase['version']['500b24']['removing_sg_ugps']);
		vB::getDbAssertor()->assertQuery('vBInstall:removeSGSystemgroups');
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
