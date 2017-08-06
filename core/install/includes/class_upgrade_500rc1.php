<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright ?2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
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

class vB_Upgrade_500rc1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500rc1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Release Candidate 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Beta 28';

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
	 * Handle customized values for stylevars that have been renamed
	 */
	public function step_1()
	{
		$mapper = new vB_Stylevar_Mapper();

		// Add mappings
		$mapper->addMapping('thread_comment_background', 'comment_background');
		$mapper->addMapping('thread_comment_divider_color', 'comment_divider_color');

		// Do the processing
		if ($mapper->load() AND $mapper->process())
		{
			$this->show_message($this->phrase['version']['408']['mapping_customized_stylevars']);
			$mapper->processResults();
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Add forumpermissons2 field in permission table.
	**/
	function step_2()
	{
		if (!$this->field_exists('permission', 'forumpermissions2'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'permission', 1, 1),
				'permission',
				'forumpermissions2',
				'int',
				array('length' => 10, 'null' => false, 'default' => 0, 'attributes' => 'UNSIGNED')
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Update site navbars
	 */
	public function step_3()
	{
		$navbars = get_default_navbars();
		$headernavbar = $navbars['header'];

		// Get site's current navbar data
		$site = $this->db->query_first("
			SELECT * FROM " . TABLE_PREFIX . "site WHERE siteid = 1
		");

		$currentheadernavbar = @unserialize($site['headernavbar']);

		foreach ((array)$headernavbar as $item)
		{
			// Check Tab
			foreach ((array)$currentheadernavbar as $k => $currentitem)
			{
				if ($currentitem['title'] == $item['title'])
				{
					// We have the tab, check for subnavs of the tab
					foreach ((array)$item['subnav'] as $subitem)
					{
						foreach ((array)$currentitem['subnav'] as $currentsubitem)
						{
							if ($subitem['title'] == $currentsubitem['title'])
							{
								// The site already has the subitem, skip to next one
								continue 2;
							}
						}
						// The site doesn't have the subitem, we insert it
						$currentheadernavbar[$k]['subnav'][] = $subitem;
					}
				}
			}
		}


		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'site'),
			"
			UPDATE " . TABLE_PREFIX . "site
			SET headernavbar = '" . serialize($currentheadernavbar) . "'
			WHERE siteid = 1
			"
		);

	}

	public function step_4()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], 'routenew'));

		$queryClasses = $legacyUrls = array('vB5_Route_Legacy_Threadprint', 'vB5_Route_Legacy_Archive');

		$db = vB::getDbAssertor();
		$queryClasses[] = 'vB5_Route_Legacy_Thread';
		$routes = $db->assertQuery('routenew', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array('class' => $queryClasses)
		));

		// fetch data and check if routes are already inserted
		$insertRoutes = array();
		foreach($routes AS $route)
		{
			if (in_array($route['class'], $legacyUrls))
			{
				// we don't need to insert this route
				$legacyUrls = array_diff($legacyUrls, array($route['class']));
			}
			else
			{
				// fetch arguments from threads route, since settings are no longer available
				$arguments = unserialize($route['arguments']);
			}
		}

		if (empty($legacyUrls) OR empty($arguments))
		{
			$this->skip_message();
		}
		else
		{
			foreach ($legacyUrls as $className)
			{
				if (class_exists($className))
				{
					try
					{
						$route = new $className;

						$route->setArguments($arguments);

						$prefix = $route->getPrefix();
						$regex = $route->getRegex();
						$arguments = $route->getArguments();

						// prefix and regex will be empty if we are using rewrite setting
						if (!empty($prefix) AND !empty($regex))
						{
							$db->delete('routenew', array('class' => $className));

							$route = array(
								'prefix'	=> $prefix,
								'regex'		=> $regex,
								'class'		=> $className,
								'arguments'	=> serialize($arguments),
							);
							$route['guid'] = vB_Xml_Export_Route::createGUID($route);
							$db->insert('routenew', $route);
						}
					}
					catch (Exception $e) {

					}
				}
			}
		}
	}

	/** Drop hasowner column **/
	public function step_5()
	{
		if ($this->field_exists('node', 'hasowner'))
		{
			$this->drop_field(
				sprintf($this->phrase['core']['altering_x_table'], 'node', 1, 1),
				'node',
				'hasowner'
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/*
	 * The channel owner should have canconfigchannel in any channels where they are a moderator
	 */
	public function step_6()
	{
		vB_Upgrade::createAdminSession();
		$this->show_message($this->phrase['version']['500rc1']['correcting_channelowner_permission']);
		$forumPerms = vB::getDatastore()->getValue('bf_ugp_forumpermissions2');
		vB::getDbAssertor()->assertQuery('vBInstall:grantOwnerForumPerm:',
			array('permission' => $forumPerms['canconfigchannel'], 'systemgroupid' => 9));
		vB::getUserContext()->rebuildGroupAccess();

	}

	/**
	 * Add missing request and notification types
	 */
	public function step_7()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'privatemessage', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "privatemessage CHANGE about about ENUM(
				'vote',
				'vote_reply',
				'rate',
				'reply',
				'follow',
				'following',
				'vm',
				'comment',
				'threadcomment',
				'moderate',
				'" . vB_Api_Node::REQUEST_TAKE_OWNER . "',
				'" . vB_Api_Node::REQUEST_TAKE_MODERATOR . "',
				'" . vB_Api_Node::REQUEST_GRANT_OWNER . "',
				'" . vB_Api_Node::REQUEST_GRANT_MODERATOR . "',
				'" . vB_Api_Node::REQUEST_GRANT_MEMBER . "',
				'" . vB_Api_Node::REQUEST_TAKE_MEMBER . "',
				'" . vB_Api_Node::REQUEST_TAKE_SUBSCRIBER . "',
				'" . vB_Api_Node::REQUEST_GRANT_SUBSCRIBER . "',
				'" . vB_Api_Node::REQUEST_SG_TAKE_OWNER . "',
				'" . vB_Api_Node::REQUEST_SG_TAKE_MODERATOR . "',
				'" . vB_Api_Node::REQUEST_SG_GRANT_OWNER . "',
				'" . vB_Api_Node::REQUEST_SG_GRANT_MODERATOR . "',
				'" . vB_Api_Node::REQUEST_SG_GRANT_SUBSCRIBER . "',
				'" . vB_Api_Node::REQUEST_SG_TAKE_SUBSCRIBER . "',
				'" . vB_Api_Node::REQUEST_SG_GRANT_MEMBER . "',
				'" . vB_Api_Node::REQUEST_SG_TAKE_MEMBER . "');
			"
		);
	}

	/**
	 * Import notifications for new visitor messages
	 */
	function step_8($data = NULL)
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'));

		$process = 500;
		$startat = intval($data['startat']);

		//First see if we need to do something. Maybe we're O.K.
		if (!empty($data['maxvB5']))
		{
			$maxvB5 = $data['maxvB5'];
		}
		else
		{
			$maxvB5 = $this->db->query_first("SELECT MAX(userid) AS maxid FROM " . TABLE_PREFIX . "user");
			$maxvB5 = $maxvB5['maxid'];
		}

		if ($maxvB5 <= $startat)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}

		// Fetch user info
		$users = vB::getDbAssertor()->assertQuery('user', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::COLUMNS_KEY => array('userid', 'vmunreadcount'),
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'userid', 'value' => $startat, 'operator' => vB_dB_Query::OPERATOR_GT),
				array('field' => 'userid', 'value' => ($startat + $process), 'operator' => vB_dB_Query::OPERATOR_LTE),
			)
		));

		if ($users)
		{
			vB_Upgrade::createAdminSession();

			$messageLibrary = vB_Library::instance('Content_Privatemessage');
			$nodeLibrary = vB_Library::instance('node');
			$vmChannelId = $nodeLibrary->fetchVMChannel();

			$notifications = array();

			foreach($users AS $user)
			{

				if ($user['vmunreadcount'] > 0)
				{
					// fetch last N visitor messages
					$lastVM = vB::getDbAssertor()->assertQuery('vbForum:node', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						vB_dB_Query::COLUMNS_KEY => array('nodeid', 'userid', 'setfor'),
						vB_dB_Query::CONDITIONS_KEY => array(
							array('field' => 'setfor', 'value' => $user['userid']),
							array('field' => 'parentid', 'value' => $vmChannelId),
							array('field' => 'showpublished', 'value' => 0, 'operator' => vB_dB_Query::OPERATOR_GT)
						),
						vB_dB_Query::PARAM_LIMIT => $user['vmunreadcount'],
					), array('field' => 'publishdate', 'direction' => vB_dB_Query::SORT_DESC));

					if ($lastVM)
					{
						foreach ($lastVM AS $node)
						{
							$notifications[] = array(
								'about' => vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_VM,
								'aboutid' => $node['nodeid'],
								'sentto' => $node['setfor'],
								'sender' => $node['userid'],
								'contentnodeid' => $nodeid,
							);
						}
					}
				}
			}

			foreach ($notifications AS $notification)
			{
				$notification['msgtype'] = 'notification';
				$notification['rawtext'] = '';

				// send notification only if receiver is not the sender.
				// also check receiver's notification options with userReceivesNotification(userid, about)
				if (($notification['sentto'] != $notification['sender']) AND $messageLibrary->userReceivesNotification($notification['sentto'], $notification['about']))
				{
					try
					{	// This will throw an exception if the user doesn't exist
						$nodeid = $messageLibrary->addMessageNoFlood($notification);
					}
					catch (vB_Exception_Api $e)
					{}
				}
			}
		}

		$this->show_message(sprintf($this->phrase['core']['processed_records_x_y'], $startat + 1, $startat + $process - 1));

		return array('startat' => ($startat + $process - 1), 'maxvB5' => $maxvB5);
	}

	/**
	 * Import notifications for social group invites
	 */
	function step_9($data = NULL)
	{
		if (!$this->tableExists('socialgroupmember'))
		{
			$this->skip_message();
			return;
		}

		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'));

		$process = 500;
		$startat = intval($data['startat']);

		//First see if we need to do something. Maybe we're O.K.
		if (!empty($data['maxvB5']))
		{
			$maxvB5 = $data['maxvB5'];
		}
		else
		{
			$maxvB5 = $this->db->query_first("SELECT MAX(userid) AS maxid FROM " . TABLE_PREFIX . "user");
			$maxvB5 = $maxvB5['maxid'];
		}

		if ($maxvB5 <= $startat)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}

		// Fetch user info
		$users = vB::getDbAssertor()->assertQuery('user', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::COLUMNS_KEY => array('userid', 'socgroupinvitecount'),
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'userid', 'value' => $startat, 'operator' => vB_dB_Query::OPERATOR_GT),
				array('field' => 'userid', 'value' => ($startat + $process), 'operator' => vB_dB_Query::OPERATOR_LTE),
			)
		));

		if ($users)
		{
			vB_Upgrade::createAdminSession();

			//$nodeLibrary = vB_Library::instance('node');

			// build a map of group info, indexed by the old groupid
			$groupInfo = array();

			$oldContentTypeId = vB_Types::instance()->getContentTypeID('vBForum_SocialGroup');
			$oldSocialGroups = vB::getDbAssertor()->assertQuery('vbForum:node', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::COLUMNS_KEY => array('nodeid', 'oldid', 'userid'),
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'oldcontenttypeid', 'value' => $oldContentTypeId),
				),
			));
			if ($oldSocialGroups)
			{
				foreach ($oldSocialGroups AS $oldSocialGroup)
				{
					$groupInfo[$oldSocialGroup['oldid']] = $oldSocialGroup;
				}
			}

			$notifications = array();

			foreach($users AS $user)
			{

				if ($user['socgroupinvitecount'] > 0)
				{
					// get groups that this user has been invited to
					$groups = vB::getDbAssertor()->getRows('vBInstall:socialgroupmember', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						vB_dB_Query::COLUMNS_KEY => array('groupid'),
						vB_dB_Query::CONDITIONS_KEY => array(
							array('field' => 'userid', 'value' => $user['userid']),
							array('field' => 'type', 'value' => 'invited'),
						),
					));

					// get vB5 node information for the groups
					$nodes = array();
					foreach ($groups AS $group)
					{
						if ($group['groupid'] > 0)
						{
							$nodes[] = $groupInfo[$group['groupid']];
						}
					}

					// prepare notifications
					foreach ($nodes AS $node)
					{
						//$nodeLibrary->requestChannel($node['nodeid'], $user['userid'], vB_Api_Node::REQUEST_SG_TAKE_MEMBER, true, $node['userid']);
						$notifications[] = array(
							'about' => vB_Api_Node::REQUEST_SG_TAKE_MEMBER,
							'aboutid' => $node['nodeid'],
							'sentto' => $user['userid'],
							'sender' => $node['userid'],
						);

					}
				}
			}

			$messageLibrary = vB_Library::instance('Content_Privatemessage');

			foreach ($notifications AS $notification)
			{
				$notification['msgtype'] = 'request';
				$notification['rawtext'] = '';

				// send notification only if receiver is not the sender.
				// also check receiver's notification options with userReceivesNotification(userid, about)
				if (($notification['sentto'] != $notification['sender']) AND $messageLibrary->userReceivesNotification($notification['sentto'], $notification['about']))
				{
					// check for duplicate requests
					$messageLibrary->checkFolders($notification['sentto']);
					$folders = $messageLibrary->fetchFolders($notification['sentto']);
					$folderid = $folders['systemfolders'][vB_Library_Content_Privatemessage::REQUEST_FOLDER];

					$dupeCheck = vB::getDbAssertor()->getRows('vBInstall:500rc1_checkDuplicateRequests', array(
						'userid' => $notification['sentto'],
						'folderid' => $folderid,
						'aboutid' => $notification['aboutid'],
						'about' => vB_Api_Node::REQUEST_SG_TAKE_MEMBER,
					));

					// if not duplicate, insert the message
					if (count($dupeCheck) == 0)
					{
						$nodeid = $messageLibrary->addMessageNoFlood($notification);
					}
				}
			}
		}

		$this->show_message(sprintf($this->phrase['core']['processed_records_x_y'], $startat + 1, $startat + $process - 1));

		return array('startat' => ($startat + $process - 1), 'maxvB5' => $maxvB5);
	}

	/**
	 * Import notifications for social group join requests
	 */
	function step_10($data = NULL)
	{
		if (!$this->tableExists('socialgroupmember'))
		{
			$this->skip_message();
			return;
		}

		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'));

		$process = 500;
		$startat = intval($data['startat']);

		//First see if we need to do something. Maybe we're O.K.
		if (!empty($data['maxvB5']))
		{
			$maxvB5 = $data['maxvB5'];
		}
		else
		{
			$maxvB5 = $this->db->query_first("SELECT MAX(userid) AS maxid FROM " . TABLE_PREFIX . "user");
			$maxvB5 = $maxvB5['maxid'];
		}

		if ($maxvB5 <= $startat)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}

		// Fetch user info
		$users = vB::getDbAssertor()->assertQuery('user', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::COLUMNS_KEY => array('userid', 'socgroupreqcount'),
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'userid', 'value' => $startat, 'operator' => vB_dB_Query::OPERATOR_GT),
				array('field' => 'userid', 'value' => ($startat + $process), 'operator' => vB_dB_Query::OPERATOR_LTE),
			)
		));

		if ($users)
		{
			vB_Upgrade::createAdminSession();

			//$nodeLibrary = vB_Library::instance('node');

			// build a map of group info, indexed by the old groupid
			$groupInfo = array();

			$oldContentTypeId = vB_Types::instance()->getContentTypeID('vBForum_SocialGroup');
			$oldSocialGroups = vB::getDbAssertor()->assertQuery('vbForum:node', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::COLUMNS_KEY => array('nodeid', 'oldid', 'userid'),
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'oldcontenttypeid', 'value' => $oldContentTypeId),
				),
			));
			if ($oldSocialGroups)
			{
				foreach ($oldSocialGroups AS $oldSocialGroup)
				{
					$groupInfo[$oldSocialGroup['oldid']] = $oldSocialGroup;
				}
			}

			$notifications = array();

			foreach($users AS $user)
			{

				if ($user['socgroupreqcount'] > 0)
				{
					// get nodes that this user owns or moderates
					$modNodeResult = vB_Library::instance('user')->getGroupInTopic($user['userid']);
					$modNodes = array();
					if ($modNodeResult)
					{
						foreach ($modNodeResult AS $modNodeResultItem)
						{
							$modNodes[] = $modNodeResultItem['nodeid'];
						}
					}

					// based on nodes, get groups that this user owns or moderates
					$modGroupOldIds = array();
					$oldContentTypeId = vB_Types::instance()->getContentTypeID('vBForum_SocialGroup');
					$modGroupsResult = vB::getDbAssertor()->assertQuery('vbForum:node', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						vB_dB_Query::COLUMNS_KEY => array('nodeid', 'oldid'),
						vB_dB_Query::CONDITIONS_KEY => array(
							array('field' => 'oldcontenttypeid', 'value' => $oldContentTypeId),
							array('field' => 'nodeid', 'value' => $modNodes),
						),
					));
					if ($modGroupsResult)
					{
						foreach ($modGroupsResult AS $modGroupsResultItem)
						{
							$modGroupOldIds[] = $modGroupsResultItem['oldid'];
						}
					}

					// form this user's groups, get the ones that have pending (moderated) users waiting for approval
					$groups = vB::getDbAssertor()->getRows('vBInstall:socialgroupmember', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						vB_dB_Query::COLUMNS_KEY => array('groupid', 'userid'),
						vB_dB_Query::CONDITIONS_KEY => array(
							array('field' => 'groupid', 'value' => $modGroupOldIds),
							array('field' => 'type', 'value' => 'moderated'),
						),
					));

					// get vB5 node information for the groups and add the userid of the pending / moderated user
					$nodes = array();
					$i = 0;
					foreach ($groups AS $group)
					{
						if ($group['groupid'] > 0)
						{
							$nodes[$i] = $groupInfo[$group['groupid']];
							$nodes[$i]['moderateduserid'] = $group['userid'];
							++$i;
						}
					}

					// prepare notifications
					foreach ($nodes AS $node)
					{
						//$nodeLibrary->requestChannel($node['nodeid'], $user['userid'], vB_Api_Node::REQUEST_SG_TAKE_MEMBER, true, $node['userid']);
						$notifications[] = array(
							'about' => vB_Api_Node::REQUEST_SG_GRANT_MEMBER,
							'aboutid' => $node['nodeid'],
							'sentto' => $user['userid'],
							'sender' => $node['moderateduserid'],

						);

					}
				}
			}

			$messageLibrary = vB_Library::instance('Content_Privatemessage');

			foreach ($notifications AS $notification)
			{
				$notification['msgtype'] = 'request';
				$notification['rawtext'] = '';

				// send notification only if receiver is not the sender.
				// also check receiver's notification options with userReceivesNotification(userid, about)
				if (($notification['sentto'] != $notification['sender']) AND $messageLibrary->userReceivesNotification($notification['sentto'], $notification['about']))
				{
					// check for duplicate requests
					$messageLibrary->checkFolders($notification['sentto']);
					$folders = $messageLibrary->fetchFolders($notification['sentto']);
					$folderid = $folders['systemfolders'][vB_Library_Content_Privatemessage::REQUEST_FOLDER];

					$dupeCheck = vB::getDbAssertor()->getRows('vBInstall:500rc1_checkDuplicateRequests', array(
						'userid' => $notification['sentto'],
						'folderid' => $folderid,
						'aboutid' => $notification['aboutid'],
						'about' => vB_Api_Node::REQUEST_SG_GRANT_MEMBER,
					));

					// if not duplicate, insert the message
					if (count($dupeCheck) == 0)
					{
						$nodeid = $messageLibrary->addMessageNoFlood($notification);
					}
				}
			}
		}

		$this->show_message(sprintf($this->phrase['core']['processed_records_x_y'], $startat + 1, $startat + $process - 1));

		return array('startat' => ($startat + $process - 1), 'maxvB5' => $maxvB5);
	}

	/*
	 * Turn off html for imported posts that don't have allowhtml in the original forum.
	 * Relies on forum options being imported correctly before granting allow html option in a later upgrade step.
	 */
	public function step_11($data = NULL)
	{
		if ($this->tableExists('forum') AND $this->tableExists('post') AND $this->field_exists('post', 'htmlstate'))
		{
				//$options = vB::getDatastore()->getValue('bf_misc_forumoptions'); $options['allowhtml'];
			$this->show_message($this->phrase['version']['500rc1']['updating_text_nodes']);
			$batchsize = 500;
			$threadTypeId = vB_Types::instance()->getContentTypeID('vBForum_Thread');
			$postTypeId = vB_Types::instance()->getContentTypeID('vBForum_Post');
			$forumOptions = vB::getDatastore()->getValue('bf_misc_forumoptions');
			$startat = intval($data['startat']);

			if (!empty($data['max']))
			{
				$max = $data['max'];
			}
			else
			{
				$max = vB::getDbAssertor()->getRow('vBInstall:getMaxNodeidForOldContent', array('oldcontenttypeid' => array($threadTypeId, $postTypeId)));
				$max = $max['maxid'];

				//If we don't have any posts, we're done.
				if (intval($max) < 1)
				{
					$this->skip_message();
					return;
				}
			}

			if ($startat > $max)
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}

			vB::getDbAssertor()->assertQuery('vBInstall:updateImportedForumPostHtmlState', array(
				'startat' => $startat,
				'batchsize' => $batchsize,
				'allowhtmlpermission' => $forumOptions['allowhtml'],
				'oldcontenttypeids' => array($threadTypeId, $postTypeId),
			));
			return array('startat' => ($startat + $batchsize), 'max' => $max);
		}
		else
		{
			$this->skip_message();
		}
	}

	/*
	 * Set forum html state for imported starters (not set in 500a1 step_145)
	 */
	public function step_12($data = NULL)
	{
		if ($this->tableExists('forum') AND $this->tableExists('post') AND $this->field_exists('post', 'htmlstate'))
		{
			//$options = vB::getDatastore()->getValue('bf_misc_forumoptions'); $options['allowhtml'];
			$this->show_message($this->phrase['version']['500rc1']['updating_text_nodes']);
			$batchsize = 500;
			$startat = intval($data['startat']);

			if (!empty($data['max']))
			{
				$max = $data['max'];
			}
			else
			{
				$threadTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Thread');
				$max = vB::getDbAssertor()->getRow('vBInstall:getMaxNodeidForOldContent', array('oldcontenttypeid' => $threadTypeId));
				$max = $max['maxid'];

				//If we don't have any posts, we're done.
				if (intval($max) < 1)
				{
					$this->skip_message();
					return;
				}
			}

			if ($startat > $max)
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}

			vB::getDbAssertor()->assertQuery('vBInstall:updateStarterPostHtmlState', array(
				'startat' => $startat,
				'batchsize' => $batchsize,
			));
			return array('startat' => ($startat + $batchsize), 'max' => $max);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Turn off htmlstate for blog entries and comments
	 * 	from vb3/4.
	 * Turning off vB5 blog entries / comments should be handled by
	 *  updateAllTextHtmlStateDefault since they'll be null
	 * We'll tackle handling it correctly in the future, but right now we want to avoid potential XSS issues.
	**/
	public function step_13($data = NULL)
	{
		// check if blog product exists. Else, no action needed
		if (isset($this->registry->products['vbblog']) AND $this->registry->products['vbblog'])
		{
			$this->show_message($this->phrase['version']['500rc1']['updating_text_nodes_for_blogs']);
			$startat = intval($data['startat']);
			$batchsize = 500;
			// contenttypeid 9985 - from class_upgrade_500a22 step2
			// contenttypeid 9984 - from class_upgrade_500a22 step3
			$oldContetypeid_blogStarter = 9985;
			$oldContetypeid_blogReply = 9984;

			if (!empty($data['max']))
			{
					$max = $data['max'];
			}
			else
			{
				// grab the max id for imported vb3/4 blog entry/reply content types
				$max = vB::getDbAssertor()->getRow(	'vBInstall:getMaxNodeidForOldContent',
											array(	'oldcontenttypeid' => array($oldContetypeid_blogStarter, $oldContetypeid_blogReply))
										);
				$max = $max['maxid'];
			}

			if($startat > $max)
			{
				// we're done here
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}
			else
			{
				// let's just turn them all off for now.
				vB::getDbAssertor()->assertQuery('vBInstall:updateImportedBlogPostHtmlState', array(
					'startat' => $startat,
					'batchsize' => $batchsize,
					'oldcontenttypeids' => array($oldContetypeid_blogStarter, $oldContetypeid_blogReply)
				));

				// start next batch
				return array('startat' => ($startat + $batchsize), 'max' => $max);
			}
		}
		else
		{
			// no action needed for vb5 upgrades for now
			$this->skip_message();
		}
	}

	/*
	 * Update default channel options. Allow HTML by default. Leave it to channel permissions.
	 */
	public function step_14()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], 'channel'));
		vB::getDbAssertor()->assertQuery('vBInstall:alterChannelOptions');
	}

	/*
	 * Set allowhtml for channels. This should be handled by channel permissions and text.htmlstate.
	 */
	public function step_15()
	{
		$this->show_message($this->phrase['version']['500rc1']['updating_channel_options']);
		$forumOptions = vB::getDatastore()->getValue('bf_misc_forumoptions');
		vB::getDbAssertor()->assertQuery('vBInstall:updateAllowHtmlChannelOption',
			array('allowhtmlpermission' => $forumOptions['allowhtml']));
	}

	/*
	 * Set the html state to not be null and a sane default
	 */
	public function step_16()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], 'text'));
		vB::getDbAssertor()->assertQuery('vBInstall:alterTextHtmlstate');
	}

	/**
	 * Update the default text.allowhtml to be 'off' instead of NULL or ''
	**/
	public function step_17($data = NULL)
	{
		$this->show_message($this->phrase['version']['500rc1']['updating_text_nodes']);
		$startat = intval($data['startat']);
		$batchsize = 500;

		if (!empty($data['max']))
		{
				$max = $data['max'];
		}
		else
		{
			// grab the max nodeid
			$max = vB::getDbAssertor()->getRow('vBInstall:getMaxNodeid');
			$max = $max['maxid'];
		}

		if($startat > $max)
		{
			// we're done here
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}
		else
		{
			// let's set some text.allowhtml to Off
			vB::getDbAssertor()->assertQuery('vBInstall:updateAllTextHtmlStateDefault', array(
				'startat' => $startat,
				'batchsize' => $batchsize)
			);

			// start next batch
			return array('startat' => ($startat + $batchsize), 'max' => $max);
		}
	}

	/**
	 * Fix contenttypeid for redirect nodes
	 */
	public function step_18($data = null)
	{
		$this->show_message($this->phrase['version']['500b24']['thread_redirect_import']);
		$startat = intval($data['startat']);
		$batchsize = 500;

		if (!empty($data['max']))
		{
				$max = $data['max'];
		}
		else
		{
			// grab the max nodeid
			$max = vB::getDbAssertor()->getRow('vBInstall:getMaxNodeid');
			$max = $max['maxid'];
		}

		if($startat > $max)
		{
			// we're done here
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}
		else
		{
			vB_Types::instance()->reloadTypes();
			$redirectTypeId = vB_Types::instance()->getContentTypeId('vBForum_Redirect');

			// let's set some text.allowhtml to Off
			vB::getDbAssertor()->assertQuery('vBInstall:fixRedirectContentTypeId', array(
				'redirectContentTypeId' => $redirectTypeId,
				'redirectOldContentTypeId' => 9980,
				'startat' => $startat,
				'batchsize' => $batchsize)
			);

			// start next batch
			return array('startat' => ($startat + $batchsize), 'max' => $max);
		}
	}

	/**
	 * Fix some forumpermissons2 values as three bitfields moved.
	**/
	function step_19()
	{
		/* We only need to run this once.
		To do this we check the upgrader log to
		see if this step has been previously run. */
		$log = vB::getDbAssertor()->getRow('vBInstall:upgradelog', array('script' => '500rc1', 'step' => 19)); // Must match this step.

		if (empty($log))
		{
			vB::getDbAssertor()->assertQuery(
			'vBInstall:fixFperms2',
			array (
					'oldp1' => 16777216, // canattachmentcss in vB4.
					'oldp2' => 33554432, // bypassdoublepost in vB4.
					'oldp3' => 67108864, // canwrtmembers in vB4.
					'newp1' => 8,
					'newp2' => 16,
					'newp3' => 32,
				)
			);

			$this->show_message(sprintf($this->phrase['core']['process_done']));
		}
		else
		{
			$this->skip_message();
		}
	}

	/*
	 * Remove the vB4.2 cronemail task if it exists.
	 */
	public function step_20()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], 'cron'));
		vB::getDbAssertor()->delete('cron', array('varname' => array('cronmail', 'reminder', 'activitypopularity')));
	}

	/*
	 Step 21 - Add E-Mail Scheduled Task
	*/
	public function step_21()
	{
		$this->add_cronjob(
			array(
				'varname'  => 'nodestats',
				'nextrun'  => 1320000000,
				'weekday'  => -1,
				'day'      => -1,
				'hour'     => -1,
				'minute'   => 'a:1:{i:0;i:1;}',
				'filename' => './includes/cron/node_dailycleanup.php',
				'loglevel' => 1,
				'volatile' => 1,
				'product'  => 'vbulletin'
			)
		);
	}
	
	/**
	 * Step 22 - Fix regex for vB5_Route_Legacy_Archive
	 */
	public function step_22()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], 'routenew'));
		
		$archiveRoute = new vB5_Route_Legacy_Archive();
		$newRegex = $archiveRoute->getRegex();
		
		vB::getDbAssertor()->update('routenew', array('regex' => $newRegex), array('class' => 'vB5_Route_Legacy_Archive'));
	}

	/** Fix blog title, description **/
	public function step_23($data = NULL)
	{
		if ($this->tableExists('blog_user') AND $this->tableExists('node'))
		{
			$this->show_message($this->phrase['version']['500rc1']['fixing_blog_data']);
			$startat = intval($data['startat']);
			$batchsize = 2000;
			$assertor = vB::getDbAssertor();

			if (isset($data['startat']))
			{
				$startat = $data['startat'];
			}

			if (!empty($data['maxvB4']))
			{
				$maxToFix = $data['maxvB4'];
			}
			else
			{
				$maxToFix = $assertor->getRow('vBInstall:getMaxBlogUserIdToFix', array('contenttypeid' => 9999));
				$maxToFix = intval($maxToFix['maxid']);
				
				//If there are no blogs to fix...
				if (intval($maxToFix) < 1)
				{
					$this->skip_message();
					return;
				}
			}
			
			if (!isset($startat))
			{
				$maxFixed = $assertor->getRow('vBInstall:getMaxFixedBlogUserId', array('contenttypeid' => 9999));

				if (!empty($maxFixed) AND !empty($maxFixed['maxid']))
				{
					$startat = $maxFixed['maxid'];
				}
				else
				{
					$startat = 0;
				}
			}

			$blogs  = $assertor->assertQuery('vBInstall:getBlogsUserToFix', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9999));
			if (!$blogs->valid())
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}

			vB_Upgrade::createAdminSession();
			$channelLib = vB_Library::instance('content_channel');
			foreach ($blogs AS $blog)
			{
				$blog['urlident'] = $channelLib->getUniqueUrlIdent($blog['title']);
				$channelLib->update($blog['nodeid'], $blog);
			}

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
			return array('startat' => ($startat + $batchsize), 'maxvB4' => $maxToFix);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Fix nodeoptions **/
	public function step_24($data = NULL)
	{
		if ($this->tableExists('blog_user') AND $this->tableExists('node'))
		{
			$this->show_message($this->phrase['version']['500rc1']['fixing_blog_options']);
			$startat = intval($data['startat']);
			$batchsize = 2000;
			$assertor = vB::getDbAssertor();

			if (isset($data['startat']))
			{
				$startat = $data['startat'];
			}

			if (!empty($data['maxvB4']))
			{
				$maxToFix = $data['maxvB4'];
			}
			else
			{
				$maxToFix = $assertor->getRow('vBInstall:getMaxBlogUserIdToFixOptions', array('contenttypeid' => 9999));
				$maxToFix = intval($maxToFix['maxid']);
				
				//If there are no blogs to fix...
				if (intval($maxToFix) < 1)
				{
					$this->skip_message();
					return;
				}
			}
			
			if (!isset($startat))
			{
				$maxFixed = $assertor->getRow('vBInstall:getMaxOptionsFixedBlogUserId', array('contenttypeid' => 9999));

				if (!empty($maxFixed) AND !empty($maxFixed['maxid']))
				{
					$startat = $maxFixed['maxid'];
				}
				else
				{
					$startat = 0;
				}
			}

			$blogs  = $assertor->assertQuery('vBInstall:getBlogsUserToFixOptions', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9999));
			if (!$blogs->valid())
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}

			// let's map the vB4 options and port them to vB5
			// allow smilie 					=> 1/0
			// moderate comments 				=> 1
			// parse links						=> 2
			// allow comments 					=> 4
			// options_member/guest can view	=> 1
			// options_member/guest can post 	=> 2
			$options = array(1 => vB_Api_Node::OPTION_MODERATE_COMMENTS, 2 => vB_Api_Node::OPTION_NODE_PARSELINKS, 4 => vB_Api_Node::OPTION_ALLOW_POST);
			$perms = array('viewperms' => 1, 'commentperms' => 2);
			vB_Upgrade::createAdminSession();
			$nodeLib = vB_Library::instance('node');
			$nodeApi = vB_Api::instance('node');		
			foreach ($blogs AS $blog)
			{
				$nodeoption = 0;
				if (!$blog['allowsmilie'])
				{
					$nodeoption |= vB_Api_Node::OPTION_NODE_DISABLE_SMILIES;
				}

				foreach ($options AS $vb4 => $vb5)
				{
					if ($blog['options'] & $vb4)
					{
						$nodeoption |= $vb5;
					}
				}

				$nodeperms = array();
				foreach ($perms AS $name => $val)
				{
					// everyone
					if(($blog['options_member'] & $val) AND ($blog['options_guest'] & $val))
					{
						$nodeperms[$name] = 2;
					}
					// registered and members
					else if(($blog['options_member'] & $val) AND !($blog['options_guest'] & $val))
					{
						$nodeperms[$name] = 1;
					}
					// there's no currently guest only option in blogs...leave this alone
					else if(!($blog['options_member'] & $val) AND ($blog['options_guest'] & $val))
					{
						$nodeperms[$name] = 2;
					}
					// let's just leave any other case as blog members
					else
					{
						$nodeperms[$name] = 0;
					}
				}

				$nodeLib->setNodeOptions($blog['nodeid'], $nodeoption);
				$nodeApi->setNodePerms($blog['nodeid'], $nodeperms);
			}

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
			return array('startat' => ($startat + $batchsize), 'maxvB4' => $maxToFix);
		}
		else
		{
			$this->skip_message();
		}
	}
	
	/*
	 * Step 25 - Create needed PM starter sentto records for pm replies.
	 * Note that we user startat to send the pmtypeid so we don't request again.
	 */
	public function step_25($data = NULL)
	{
		if ($this->tableExists('node') AND $this->tableExists('sentto') AND $this->tableExists('messagefolder'))
		{
			$assertor = vB::getDbAssertor();
			if (!empty($data['startat']))
			{
				$pmType = $data['startat'];
			}
			else
			{
				$pmType = vB_Types::instance()->getContentTypeID('vBForum_PrivateMessage');
			}
			
			$maxToImport = $assertor->getRow('vBInstall:getMaxPmStarterToCreate', array('contenttypeid' => 9981, 'pmtypeid' => $pmType));
			$maxToImport = intval($maxToImport['maxid']);
				
			//If there are no records
			if (intval($maxToImport) < 1)
			{
				$this->skip_message();
				return;
			}
		
			$batchsize = 2000;
			$this->show_message($this->phrase['version']['500rc1']['fixing_pm_starters']);
			$assertor->assertQuery('vBInstall:createStarterPmRecords', array('batchsize' => $batchsize, 'contenttypeid' => 9981, 'pmtypeid' => $pmType));
			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
			return array('startat' => $pmType);
		}
		else
		{
			$this->skip_message();
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
