<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 71335 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbphrase;
$phrasegroups = array('cppermission', 'cpuser', 'promotion', 'pm', 'cpusergroup');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/functions_ranks.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminpermissions'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'usergroupid' => vB_Cleaner::TYPE_INT,
	'usergroupleaderid' => vB_Cleaner::TYPE_INT,
));

log_admin_action(!empty($vbulletin->GPC['usergroupid']) ? "usergroup id = " . $vbulletin->GPC['usergroupid'] : (!empty($vbulletin->GPC['usergroupleaderid']) ? "leader id = " . $vbulletin->GPC['usergroupleaderid'] : ''));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################
$assertor = vB::getDbAssertor();
print_cp_header($vbphrase['usergroup_manager_gcpusergroup']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start add / update #######################
if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{

	$vbulletin->input->clean_array_gpc('r', array(
		'defaultgroupid' => vB_Cleaner::TYPE_INT
	));

	require_once(DIR . '/includes/class_bitfield_builder.php');
	if (vB_Bitfield_Builder::build(false) !== false)
	{
		$myobj =& vB_Bitfield_Builder::init();
		if (sizeof($myobj->datastore_total['ugp']) != sizeof($vbulletin->bf_ugp))
		{
			$myobj->save();
			build_channel_permissions();
			$file = pathinfo($vbulletin->scriptpath, PATHINFO_FILENAME );
			print_stop_message2('rebuilt_bitfields_successfully', $file['filename']);
		}
	}
	else
	{
		echo "<strong>error</strong>\n";
		print_r(vB_Bitfield_Builder::fetch_errors());
	}

	if ($_REQUEST['do'] == 'add')
	{
		// get a list of other usergroups to base this one off of
		print_form_header('usergroup', 'add');
		$groups = vB_Api::instanceInternal('usergroup')->fetchUsergroupList();
		$selectgroups = '';
		foreach ($groups AS $group)
		{
			$selectgroups .= "<option value=\"$group[usergroupid]\" " . iif($group['usergroupid'] == $vbulletin->GPC['defaultgroupid'], 'selected="selected"') . ">$group[title]</option>\n";
		}
		print_description_row(construct_table_help_button('defaultgroupid') . '<b>' . $vbphrase['create_usergroup_based_off_of_usergroup'] . '</b> <select name="defaultgroupid" tabindex="1" class="bginput">' . $selectgroups . '</select> <input type="submit" class="button" value="' . $vbphrase['go'] . '" tabindex="1" />', 0, 2, 'tfoot', 'center');
		print_table_footer();
	}

	print_form_header('usergroup', 'update');
	print_column_style_code(array('width: 70%', 'width: 30%'));
	$channePermHandler = vB_ChannelPermission::instance();
	$channelPerms = $channePermHandler->fetchPermSettings();
	$channelPermFields = $channePermHandler->fetchPermFields();
	//we don't need to bitmap fields- those we handle differently
	unset ($channelPermFields['moderatorpermissions']);
	unset ($channelPermFields['createpermissions']);
	unset ($channelPermFields['forumpermissions']);
	$channelPhrases = $channePermHandler->fetchPermPhrases();
	$groupinfo = array();
	//$usergroup contains disabled fields that are set in fetchUsergroupByID to -1 so we need the original values
	$disabled_perms = get_disabled_perms($vbulletin->GPC['usergroupid']);
	$usergroup_org = vB::getDbAssertor()->getRow('usergroup', array('usergroupid' => $vbulletin->GPC['usergroupid']));
	if (!$usergroup_org) $usergroup_org = array();

	if ($_REQUEST['do'] == 'add')
	{
		if (!empty($vbulletin->GPC['defaultgroupid']))
		{
			$usergroup = vB_Api::instanceInternal('usergroup')->fetchUsergroupByID($vbulletin->GPC['defaultgroupid']);

			$ug_bitfield = array();
			foreach($vbulletin->bf_ugp AS $permissiongroup => $fields)
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
				'createpermissions' => array(),
				'moderatorpermissions' => array(),
				'genericpermissions' => array('canviewmembers' => 1, 'canmodifyprofile' => 1, 'canseeprofilepic' => 1, 'canusesignature' => 1, 'cannegativerep' => 1, 'canuserep' => 1, 'cansearchft_nl' => 1)
			);
			// set default numeric permissions
			$usergroup = array(
				'pmquota' => 0, 'pmsendmax' => 5, 'attachlimit' => 1000000,
				'avatarmaxwidth' => 200, 'avatarmaxheight' => 200, 'avatarmaxsize' => 20000,
				'profilepicmaxwidth' => 100, 'profilepicmaxheight' => 100, 'profilepicmaxsize' => 25000, 'sigmaxsizebbcode' => 7
			);
		}
		foreach ($channelPerms['moderatorpermissions'] as $moderatorpermission)
		{
			$ug_bitfield['moderatorpermissions'][$moderatorpermission['name']] = 0;
		}
		foreach ($channelPerms['createpermissions'] as $createpermission)
		{
			$default = explode(',', $createpermission['install']);
			$ug_bitfield['createpermissions'][$createpermission['name']] = in_array(2, $default);
		}
		foreach ($channelPermFields AS $key => $permType)
		{
			if (!isset($usergroup[$key]))
			{
				$intperm = $permType != vB_ChannelPermission::TYPE_BOOL;
				$groupinfo['forum_permissions'][$key] = array('intperm' => $intperm, 'phrase' => $channelPhrases[$key],
					'value' => $channelPerms[$key], 'parentgroup' => 'forumpermissions');

				if (!$intperm)
				{
					$default = explode(',', $channelPerms[$key]['install']);
					$ug_bitfield['forum_permissions'][$key] = in_array(2, $default);;
				}
			}
		}

		$permgroups = $assertor->assertQuery('vBForum:getUserGroupPermissions', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));
		$ugarr = array('-1' => '--- ' . $vbphrase['none'] . ' ---');
		if ($permgroups AND $permgroups->valid())
		{
			foreach ($permgroups AS $group)
			{
				$ugarr["$group[usergroupid]"] = $group['title'];
			}
		}
		print_table_header($vbphrase['default_forum_permissions']);
		print_select_row($vbphrase['create_permissions_based_off_of_forum'], 'ugid_base', $ugarr, $vbulletin->GPC['defaultgroupid']);
		print_table_break();

		print_table_header($vbphrase['add_new_usergroup_gcpusergroup']);
	}
	else
	{

		$usergroup = vB_Api::instanceInternal('usergroup')->fetchUsergroupByID($vbulletin->GPC['usergroupid']);
		$ug_bitfield = array();
		foreach($vbulletin->bf_ugp AS $permissiongroup => $fields)
		{
			$ug_bitfield["$permissiongroup"] = convert_bits_to_array($usergroup["$permissiongroup"], $fields);
			if (array_key_exists($permissiongroup, $usergroup_org))
			{
				$usergroup_org[$permissiongroup] = convert_bits_to_array($usergroup_org["$permissiongroup"], $fields);
			}
		}
		try
		{
			$channelPerms = vB_ChannelPermission::instance()->fetchPermissions(1, $usergroup['usergroupid']);
			$groupinfo["moderator_permissions"] = array();
			$groupinfo["createpermissions"] = array();

			if (!empty($channelPerms) AND !empty($channelPerms[$usergroup['usergroupid']]))
			{
				$channelPerms = $channelPerms[$usergroup['usergroupid']];
				foreach (array( 'edit_time', 'require_moderate',
					'maxtags', 'maxstartertags', 'maxothertags', 'maxattachments') AS $field)
				{
					$usergroup[$field] = $channelPerms[$field];
				}
				$ug_bitfield['createpermissions'] = $usergroup['moderator_permissions'] = array();
				foreach ($channelPerms['bitfields']['createpermissions'] AS $createPerm)
				{
					if ($createPerm['used'])
					{
						$ug_bitfield['createpermissions'][$createPerm['name']] = $createPerm['set'];
						$groupinfo['createpermissions'][$createPerm['name']] = array('phrase' => $createPerm['phrase'],
							'value' => (bool)$createPerm['set'],
							'parentgroup' => 'createpermissions');
					}
				};
				foreach ($channelPerms['bitfields']['moderatorpermissions'] AS $modPerm)
				{
					if ($modPerm['used'])
					{
						$ug_bitfield['moderatorpermissions'][$modPerm[name]] = $modPerm['set'];
						$value = (bool)($modPerm['set']);
						$groupinfo['moderator_permissions'][$modPerm['name']] = array('phrase' => $modPerm['phrase'],
							'value' => (bool)$modPerm['set'],
							'parentgroup' => 'moderatorpermissions');
					}
				};
			}
			//and the added channel permissions
			foreach ($channelPermFields AS $key => $permType)
			{
				if (!isset($groupinfo[$key]))
				{
					$intperm = $permType != vB_ChannelPermission::TYPE_BOOL;
					$groupinfo['forum_permissions'][$key] = array('intperm' => $intperm, 'phrase' => $channelPhrases[$key],
						'value' => $channelPerms[$key], 'parentgroup' => 'forumpermissions');

					if (!$intperm)
					{
						$ug_bitfield['forum_permissions'][$key] = $channelPerms[$key];
					}
				}
			}
		}
		catch(Exception $e)
		{
			$channelPerms = false;
		}
		construct_hidden_code('usergroupid', $vbulletin->GPC['usergroupid']);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['usergroup'],$usergroup[title], $usergroup[usergroupid]), 2, 0);
	}

	print_input_row($vbphrase['title'], 'usergroup[title]', $usergroup['title']);
	print_input_row($vbphrase['description_gcpglobal'], 'usergroup[description]', $usergroup['description']);
	print_input_row($vbphrase['usergroup_user_title'], 'usergroup[usertitle]', $usergroup['usertitle'], true, 35, 100);
	print_label_row($vbphrase['username_markup'],
		'<span style="white-space:nowrap">
		<input size="15" type="text" class="bginput" name="usergroup[opentag]" value="' . htmlspecialchars_uni($usergroup['opentag']) . '" tabindex="1" />
		<input size="15" type="text" class="bginput" name="usergroup[closetag]" value="' . htmlspecialchars_uni($usergroup['closetag']) . '" tabindex="1" />
		</span>', '', 'top', 'htmltags');
	print_input_row($vbphrase['password_expiry'], 'usergroup[passwordexpires]', $usergroup['passwordexpires']);
	print_input_row($vbphrase['password_history'], 'usergroup[passwordhistory]', $usergroup['passwordhistory']);
	print_table_break();
	print_column_style_code(array('width: 70%', 'width: 30%'));

	// additional system usergroups with unpredicatable usergroupids
	if ((isset($usergroup['systemgroupid']) AND $usergroup['systemgroupid'] == 0) OR $_REQUEST['do'] == 'add')
	{
		print_table_header($vbphrase['public_group_settings']);
		print_yes_no_row($vbphrase['public_joinable_custom_usergroup'], 'usergroup[ispublicgroup]', $usergroup['ispublicgroup']);
		print_yes_no_row($vbphrase['can_override_primary_group_title'], 'usergroup[canoverride]', $usergroup['canoverride']);
		print_table_break();
		print_column_style_code(array('width: 70%', 'width: 30%'));
	}

	// Legacy Hook 'admin_usergroup_edit' Removed //

	// display only BF used in a nicer way. Removing unused BF for usergroup manager needs more planning.
	$excludedBF = array(
		'forumpermissions' => array('canemail', 'canpostpoll', 'canthreadrate'),
		'forumpermissions2' => array('canalwaysview', 'canalwayspostnew', 'canalwayspost'),
		'pmpermissions' => array('cantrackpm', 'candenypmreceipts', 'pmthrottlequantity'),
		'calendarpermissions' => array('canviewcalendar', 'canpostevent', 'caneditevent', 'candeleteevent', 'canviewothersevent', 'isnotmoderated'),
		'genericpermissions' => array(
			'canviewothersusernotes', 'canmanageownusernotes', 'canbeusernoted', 'canseeprofilepic', 'canviewownusernotes', 'canmanageothersusernotes',
			'canpostownusernotes', 'canpostothersusernotes', 'caneditownusernotes', 'cannegativerep', 'cansearchft_bool', 'canemailmember',
			'canprofilepic', 'cananimateprofilepic', 'profilepicmaxwidth', 'profilepicmaxheight', 'profilepicmaxsize',
			'cangiveinfraction', 'canseeinfraction', 'cangivearbinfraction', 'canreverseinfraction',
		),
		'genericoptions' => array('showgroup', 'showmemberlist'),
		'socialgrouppermissions' => array('groupfollowforummoderation', 'canupload'),
		'albumpermissions' => array('canalbum', 'canpiccomment', 'caneditownpiccomment', 'candeleteownpiccomment', 'canmanagepiccomment', 'commentfollowforummoderation'),
	);
	$bfGroups = array_keys($excludedBF);
	foreach ($myobj->data['ugp'] AS $grouptitle => $perms)
	{
		foreach ($perms AS $permtitle => $permvalue)
		{
			if (empty($permvalue['group']))
			{
				continue;
			}

			if (in_array($grouptitle, $bfGroups) AND in_array($permtitle, $excludedBF[$grouptitle]))
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

	foreach ($groupinfo AS $grouptitle => $group)
	{
		// This set of permissions is hidden from a specific group
		if (isset($group['ignoregroups']))
		{
			$ignoreids = explode(',', $group['ignoregroups']);
			if (in_array($vbulletin->GPC['usergroupid'], $ignoreids))
			{
				continue;
			}
			else
			{
				unset($group['ignoregroups']);
			}
		}
		print_table_header($vbphrase["$grouptitle"]);
		foreach ($group AS $permtitle => $permvalue)
		{
			// Permission is shown only if a particular option is enabled.
			if (isset($permvalue['options']) AND !$vbulletin->options["$permvalue[options]"])
			{
				continue;
			}

			// Permission is hidden from specific groups
			if (isset($permvalue['ignoregroups']))
			{
				$ignoreids = explode(',', $permvalue['ignoregroups']);
				if (in_array($vbulletin->GPC['usergroupid'], $ignoreids))
				{
					continue;
				}
			}

			if (!empty($permvalue['intperm']))
			{
				$getval = $usergroup["$permtitle"];
				if (isset($permvalue['readonly']))
				{
					// This permission is readonly for certain usergroups
					$readonlyids = explode(',', $permvalue['readonly']);
					if (in_array($vbulletin->GPC['usergroupid'], $readonlyids))
					{
						$getval = ($permvalue['readonlyvalue']) ? $permvalue['readonlyvalue'] : $getval;

						print_label_row($vbphrase["$permvalue[phrase]"], $getval);
						construct_hidden_code($vbphrase["$permvalue[phrase]"], $getval);
						continue;
					}
				}
				//this value has been disabled
				if (array_key_exists($permtitle, $disabled_perms) AND array_key_exists($permtitle, $usergroup_org))
				{
					print_input_row($vbphrase["$permvalue[phrase]"], "usergroup[$permtitle]", $usergroup_org["$permtitle"], 1, 20);
					continue;
				}

				print_input_row($vbphrase["$permvalue[phrase]"], "usergroup[$permtitle]", $getval, 1, 20);
			}
			else
			{
				$getval = $ug_bitfield[$permvalue['parentgroup']][$permtitle];
				//this value has been disabled
				if (array_key_exists($permvalue['parentgroup'], $disabled_perms))
				{
					$getval = empty($usergroup_org[$permvalue['parentgroup']][$permtitle]) ? false : true;
					print_yes_no_row($vbphrase["$permvalue[phrase]"], "usergroup[$permvalue[parentgroup]][$permtitle]", $getval);
					continue;
				}

				if (isset($permvalue['readonly']))
				{
					// This permission is readonly for certain usergroups
					$readonlyids = explode(',', $permvalue['readonly']);
					if (in_array($vbulletin->GPC['usergroupid'], $readonlyids))
					{
						if ($permvalue['readonlyvalue'] == 'true')
						{
							print_yes_row($vbphrase["$permvalue[phrase]"], "usergroup[$permvalue[parentgroup]][$permtitle]", $vbphrase['yes'], true);
						}
						else
						{
							print_yes_row($vbphrase["$permvalue[phrase]"], "usergroup[$permvalue[parentgroup]][$permtitle]", $vbphrase['no'], false);
						}
						continue;
					}
				}

				print_yes_no_row($vbphrase["$permvalue[phrase]"], "usergroup[$permvalue[parentgroup]][$permtitle]", $getval);
			}
		}
		print_table_break();
		print_column_style_code(array('width: 70%', 'width: 30%'));
	}

	print_submit_row(iif($_REQUEST['do'] == 'add', $vbphrase['save'], $vbphrase['update']));
}

// ###################### Start insert / update #######################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'usergroup' => vB_Cleaner::TYPE_ARRAY,
		'ugid_base' => vB_Cleaner::TYPE_INT,
		'usergroupid' => vB_Cleaner::TYPE_INT
	));
	$ugpermissions = $vbulletin->GPC['usergroup'];

	// These ones go in the permission table
	$fmcPermissions = array();
	$channelPerms = array('forumpermissions', 'forumpermissions2', 'moderatorpermissions', 'createpermissions', 'edit_time', 'require_moderate',
				 'maxtags', 'maxstartertags', 'maxothertags', 'maxattachments', 'maxchannels', 'channeliconmaxsize');
	foreach($ugpermissions AS $key => $value)
	{
		if (in_array($key, $channelPerms))
		{
			$fmcPermissions[$key] = $value;
			if ($key !== 'forumpermissions')
			{
				unset($ugpermissions[$key]);
			}
		}
	}

	// Special case, in the form is treated as
	// usergroup[forumpermissions][require_moderate] but in the permission table
	// it is a separete field, should it be like this?
	 $fmcPermissions['require_moderate'] = $ugpermissions['forumpermissions']['require_moderate'];

	/*
	 * This is the main save, usergroup permissions and for node 1
	 */
	$resultUg = vB_Api::instanceInternal('usergroup')->save($ugpermissions, $vbulletin->GPC['ugid_base'], empty($vbulletin->GPC['usergroupid']) ? 0 : $vbulletin->GPC['usergroupid']);
	$nodeid = vB_Api::instanceInternal('content_channel')->fetchChannelIdByGUID(vB_Channel::MAIN_CHANNEL);
	vB_ChannelPermission::instance()->setPermissions($nodeid, $resultUg, $fmcPermissions);
	/*
	 * This section is to save the create channel permission for Blog/Socialgroup
	 * in the permission table for the corresponding node, the request for the current
	 * permissions in each node is to don't overwrite them, this is because if
	 * the method vB_ChannelPermission::instance()->setPermissions() doesn't receive
	 * a set of permissions it sets them to No.
	 */

	$sgChannel = vB_Api::instanceInternal('socialgroup')->getSGChannel();
	$sgPerms = vB_ChannelPermission::instance()->fetchPermissions($sgChannel, $resultUg);
	$ug_bitfield = array();
	if (!empty($sgPerms) AND !empty($sgPerms[$resultUg]))
	{
		$channelPerms = $sgPerms[$resultUg];
		$ug_bitfield['createpermissions'] = array();
		$ug_bitfield['forumpermissions'] = array();
		$ug_bitfield['forumpermissions2'] = array();
		$ug_bitfield['moderatorpermissions'] = array();
		foreach ($channelPerms['bitfields']['createpermissions'] AS $createPerm)
		{
			if ($createPerm['used'])
			{
				$ug_bitfield['createpermissions'][$createPerm['name']] = $createPerm['set'];
			}
		}
		foreach ($channelPerms['bitfields']['forumpermissions'] AS $perm)
		{
			if ($createPerm['used'])
			{
				$ug_bitfield['forumpermissions'][$perm['name']] = $perm['set'];
			}
		}
		foreach ($channelPerms['bitfields']['forumpermissions2'] AS $perm)
		{
			if ($createPerm['used'])
			{
				$ug_bitfield['forumpermissions2'][$perm['name']] = $perm['set'];
			}
		}
		foreach ($channelPerms['bitfields']['moderatorpermissions'] AS $perm)
		{
			if ($createPerm['used'])
			{
				$ug_bitfield['moderatorpermissions'][$perm['name']] = $perm['set'];
			}
		}
	}
	// All these are the analogous permissions for socialgroups in the SG node
	/*
	*	All this code is to map the permissions to the corresponding channel,
	*	this is because we have the Groups permissions panel in the usergroup manager,
	*	if that section is removed, this code is likely to be removed as well
	*/
	$ug_bitfield['createpermissions']['vbforum_channel'] = $ugpermissions['socialgrouppermissions']['cancreategroups'];
	$ug_bitfield['forumpermissions']['canreplyown'] = $ug_bitfield['forumpermissions']['canreplyothers'] = $ugpermissions['socialgrouppermissions']['canpostmessage'];
	foreach (array('vbforum_text', 'vbforum_poll', 'vbforum_gallery', 'vbforum_video', 'vbforum_link') AS $createPerm)
	{
		$ug_bitfield['createpermissions'][$createPerm] = $ugpermissions['socialgrouppermissions']['canpostmessage'];
	}
	$ug_bitfield['forumpermissions']['canpostnew'] = $ugpermissions['socialgrouppermissions']['cancreatediscussion'];
	$ug_bitfield['forumpermissions']['canview'] = $ug_bitfield['forumpermissions']['canviewthreads'] = $ug_bitfield['forumpermissions']['canviewothers'] = $ugpermissions['socialgrouppermissions']['canviewgroups'];
	$ug_bitfield['forumpermissions']['candeletethread'] = $ugpermissions['socialgrouppermissions']['canmanagediscussions'];
	$ug_bitfield['forumpermissions']['caneditpost'] = $ug_bitfield['forumpermissions']['candeletepost'] = $ugpermissions['socialgrouppermissions']['canmanagemessages'];
	$ug_bitfield['forumpermissions2']['canconfigchannel'] = $ugpermissions['socialgrouppermissions']['caneditowngroups'];
	$ug_bitfield['forumpermissions']['followforummoderation'] = $ugpermissions['socialgrouppermissions']['followforummoderation'];
	$ug_bitfield['forumpermissions']['canuploadchannelicon'] = $ugpermissions['socialgrouppermissions']['canuploadgroupicon'];
	$ug_bitfield['forumpermissions']['cananimatedchannelicon'] = $ugpermissions['socialgrouppermissions']['cananimategroupicon'];
	$ug_bitfield['channeliconmaxsize'] = $ugpermissions['groupiconmaxsize'];

	$ug_bitfield['forumpermissions2']['canalwaysview'] = ($ugpermissions['socialgrouppermissions']['canalwayspostmessage'] OR $ugpermissions['socialgrouppermissions']['canalwayscreatediscussion']);
	$ug_bitfield['forumpermissions2']['canalwayspost'] = $ugpermissions['socialgrouppermissions']['canalwayspostmessage'];
	$ug_bitfield['forumpermissions2']['canalwayspostnew'] = $ugpermissions['socialgrouppermissions']['canalwayscreatediscussion'];

	vB_ChannelPermission::instance()->setPermissions($sgChannel, $resultUg, $ug_bitfield);


	$blogChannel = vB_Api::instanceInternal('blog')->getBlogChannel();
	$blogPerms = vB_ChannelPermission::instance()->fetchPermissions($blogChannel, $resultUg);
	unset($ug_bitfield);
	$ug_bitfield = array();
	if (!empty($blogPerms) AND !empty($blogPerms[$resultUg]))
	{
		$channelPerms = $blogPerms[$resultUg];
		$ug_bitfield['createpermissions'] = array();
		$ug_bitfield['forumpermissions'] = array();
		$ug_bitfield['moderatorpermissions'] = array();
		foreach ($channelPerms['bitfields']['createpermissions'] AS $createPerm)
		{
			if ($createPerm['used'])
			{
				$ug_bitfield['createpermissions'][$createPerm['name']] = $createPerm['set'];
			}
		}
		foreach ($channelPerms['bitfields']['forumpermissions'] AS $perm)
		{
			if ($createPerm['used'])
			{
				$ug_bitfield['forumpermissions'][$perm['name']] = $perm['set'];
			}
		}
		foreach ($channelPerms['bitfields']['moderatorpermissions'] AS $perm)
		{
			if ($createPerm['used'])
			{
				$ug_bitfield['moderatorpermissions'][$perm['name']] = $perm['set'];
			}
		}
	}

	// All this section is due to the subnav bar 'crate a new blog'
	$siteNavs = vB_Api::instanceInternal('site')->loadHeaderNavbar(1, false, 1);
	$break = false;
	foreach ($siteNavs AS $k => &$item)
	{
		foreach (array('isAbsoluteUrl', 'normalizedUrl') AS $urlvar)
		{
			if (array_key_exists($urlvar, $item) AND empty($item[$urlvar]))
			{
				unset($item[$urlvar]);
			}
		}

		if (!empty($item['phrase']) AND $item['phrase'] === 'navbar_blogs' AND !empty($item['subnav']))
		{
			foreach ($item['subnav'] AS &$subnav)
			{
				foreach (array('isAbsoluteUrl', 'normalizedUrl') AS $urlvar)
				{
					if (array_key_exists($urlvar, $subnav) AND empty($subnav[$urlvar]))
					{
						unset($subnav[$urlvar]);
					}
				}
				if (!empty($subnav['phrase']) AND $subnav['phrase'] === 'navbar_create_a_new_blog')
				{
					$foundKey = -1;
					foreach ($subnav['usergroups'] AS $key => $ug)
					{
						if ($ug == $resultUg)
						{
							$foundKey = $key;
						}
					}
					if ($ugpermissions['forumpermissions']['cancreateblog']) // permission
					{
						if ($foundKey == -1)
						{
							$subnav['usergroups'][] = $resultUg;
						}
					}
					else
					{
						if ($foundKey >= 0)
						{
							unset($subnav['usergroups'][$foundKey]);
							$subnav['usergroups'] = array_values($subnav['usergroups']);
						}
					}
					break;
				}
			}
		}
	}
	vB_Api::instanceInternal('site')->saveHeaderNavbar(1, $siteNavs);
	
	$ug_bitfield['createpermissions']['vbforum_channel'] = $ugpermissions['forumpermissions']['cancreateblog'];
	vB_ChannelPermission::instance()->setPermissions($blogChannel, $resultUg, $ug_bitfield);
	/*
	 * End of section 'create channel' for blog/socialgroup
	 */

	// Album channel
	$albumChannel = vB_Api::instanceInternal('node')->fetchAlbumChannel();
	$albumPerms = vB_ChannelPermission::instance()->fetchPermissions($albumChannel, $resultUg);
	$bitfields = vB_ChannelPermission::instance()->fetchPermSettings();

	if ($ugpermissions['albumpermissions']['canviewalbum'])
	{
		$albumPerms[$resultUg]['forumpermissions'] |= intval($bitfields['forumpermissions']['canview']['value']);
	}
	else
	{
		$albumPerms[$resultUg]['forumpermissions'] &= ~intval($bitfields['forumpermissions']['canview']['value']);
	}

	vB_ChannelPermission::instance()->setPermissions($albumChannel, $resultUg, $albumPerms[$resultUg]);

	print_stop_message2(array('saved_usergroup_x_successfully', htmlspecialchars_uni($vbulletin->GPC['usergroup']['title'])), 'usergroup', array('do'=>'modify'));
}

// ###################### Start Remove #######################

if ($_REQUEST['do'] == 'remove')
{

	if ($vbulletin->GPC['usergroupid'] < 8)
	{
		print_stop_message2('cant_delete_usergroup');
	}
	else
	{
		print_delete_confirmation('usergroup', $vbulletin->GPC['usergroupid'], 'usergroup', 'kill', 'usergroup', 0,
			construct_phrase($vbphrase['all_members_of_this_usergroup_will_revert'], $vbulletin->usergroupcache['2']['title'])
		);
	}

}

// ###################### Start Kill #######################
if ($_POST['do'] == 'kill')
{
	vB_Api::instanceInternal('usergroup')->delete($vbulletin->GPC['usergroupid']);

	print_stop_message2('deleted_usergroup_successfully', 'usergroup', array('do'=>'modify'));
}

// ###################### Start kill group leader #######################
if ($_POST['do'] == 'killleader')
{

	vB_Api::instanceInternal('usergroup')->removeLeader($vbulletin->GPC['usergroupleaderid']);

	print_stop_message2('deleted_usergroup_leader_successfully', 'usergroup', array('do'=>'modify'));
}

// ###################### Start delete group leader #######################
if ($_REQUEST['do'] == 'removeleader')
{

	print_delete_confirmation('usergroupleader', $vbulletin->GPC['usergroupleaderid'], 'usergroup', 'killleader', 'usergroup_leader');

}

// ###################### Start insert group leader #######################
if ($_POST['do'] == 'insertleader')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'username' => vB_Cleaner::TYPE_NOHTML
	));

	$userid = vB::getDbAssertor()->getField('user_fetchidbyusername', array(
		vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
		'username' => $vbulletin->GPC['username'],
	));

	try
	{
			vB_Api::instanceInternal('usergroup')->addLeader($vbulletin->GPC['usergroupid'], $userid);
	}
	catch (vB_Exception_Api $e)
	{
		$errors = $e->get_errors();
		print_stop_message2($errors[0]);
	}
	print_stop_message2(array('saved_usergroup_leader_x_successfully', $vbulletin->GPC['username']), 'usergroup', array('do'=>'modify'));
}

// ###################### Start add group leader #######################
if ($_REQUEST['do'] == 'addleader')
{

	$groups = array();
	$usergroups = $assertor->assertQuery('vBForum:usergroup',
		 array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'ispublicgroup','value' => 1, 'operator'=> vB_dB_Query::OPERATOR_EQ),
				array('field' => 'systemgroupid','value' => 0, 'operator'=> vB_dB_Query::OPERATOR_EQ)
			)
		), array('field' => 'title', 'direction' => vB_dB_Query::SORT_ASC)
	);
	if ($usergroups AND $usergroups->valid())
	{
		foreach ($usergroups AS $usergroup)
		{
			$groups["$usergroup[usergroupid]"] = $usergroup['title'];
		}
	}

	if (!isset($groups["{$vbulletin->GPC['usergroupid']}"]))
	{
		print_stop_message2('usergroup_not_public_or_invalid');
	}

	print_form_header('usergroup', 'insertleader');
	construct_hidden_code('usergroupid', $vbulletin->GPC['usergroupid']);
	print_table_header($vbphrase['add_new_usergroup_leader']);
	print_select_row($vbphrase['usergroup'], 'usergroupid', $groups, $vbulletin->GPC['usergroupid']);
	print_input_row($vbphrase['username'], 'username');
	print_submit_row($vbphrase['add'], 0);

}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{
	// get usergroups (don't use the cache at this point...
	// this is the only place where you could rebuild the forumcache and vbulletin->usergroupcache
	// without them being present already...

	unset($vbulletin->usergroupcache);

	$usergroups = $assertor->assertQuery('vBForum:usergroup',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT),
		array('field' => 'title', 'direction' => vB_dB_Query::SORT_ASC)
	);
	if ($usergroups AND $usergroups->valid())
	{
		foreach ($usergroups AS $usergroup)
		{
			$vbulletin->usergroupcache["{$usergroup['usergroupid']}"] = $usergroup;
		}
	}
	unset($usergroup);

	// count primary users
	$groupcounts = $assertor->assertQuery('vBForum:getPrimaryUsersCount', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));
	if ($groupcounts AND $groupcounts->valid())
	{
		foreach ($groupcounts AS $groupcount)
		{
			$vbulletin->usergroupcache["{$groupcount['usergroupid']}"]['count'] = $groupcount['total'];
		}
	}
	unset($groupcount);

	// count secondary users
	$groupcounts = $assertor->assertQuery('user',
		 array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'membergroupids','value' => '', 'operator'=> vB_dB_Query::OPERATOR_NE)
			)
		)
	);
	if ($groupcounts AND $groupcounts->valid())
	{
		foreach ($groupcounts AS $groupcount)
		{
			$ids = fetch_membergroupids_array($groupcount, false);
			foreach ($ids AS $index => $value)
			{
				if ($groupcount['usergroupid'] != $value AND !empty($vbulletin->usergroupcache["$value"]))
				{
					$vbulletin->usergroupcache["$value"]['secondarycount']++;
				}
			}
		}
	}
	unset($groupcount);

	// count requests
	$groupcounts = $assertor->assertQuery('vBForum:getUserGroupReqeustCount', array(vB_dB_Query::QUERY_STORED));
	if ($groupcounts AND $groupcounts->valid())
	{
		foreach ($groupcounts AS $groupcount)
		{
			$vbulletin->usergroupcache["{$groupcount['usergroupid']}"]['requests'] = $groupcount['total'];
		}
	}
	unset($groupcount);

	$usergroups = array();
	foreach($vbulletin->usergroupcache AS $group)
	{
		if ($group['systemgroupid'] == 0)
		{
			if ($group['ispublicgroup'])
			{
				$usergroups['public']["{$group['usergroupid']}"] = $group;
			}
			else
			{
				$usergroups['custom']["{$group['usergroupid']}"] = $group;
			}
		}
		else
		{
			$usergroups['default']["{$group['usergroupid']}"] = $group;
		}
	}

	$usergroupleaders = array();
	$leaders = $assertor->assertQuery('vBForum:getLeadersByUser', array(vB_dB_Query::QUERY_STORED));
	if ($leaders AND $leaders->valid())
	{
		foreach ($leaders AS $leader)
		{
			$usergroupleaders["{$leader['usergroupid']}"][] = $leader;
		}
	}
	unset($leader);

	$promotions = array();
	$proms = $assertor->assertQuery('vBForum:getUserGroupIdCountByPromotion', array(vB_dB_Query::QUERY_STORED));
	if ($proms AND $proms->valid())
	{
		foreach ($proms AS $prom)
		{
			$promotions["{$prom['usergroupid']}"] = $prom['count'];
		}
	}

	?>
	<script type="text/javascript">
	function js_usergroup_jump(usergroupid)
	{
		task = eval("document.cpform.u" + usergroupid + ".options[document.cpform.u" + usergroupid + ".selectedIndex].value");
		switch (task)
		{
			case 'edit': window.location = "usergroup.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=edit&usergroupid=" + usergroupid; break;
			case 'kill': window.location = "usergroup.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=remove&usergroupid=" + usergroupid; break;
			case 'list': window.location = "user.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=find&user[usergroupid]=" + usergroupid; break;
			case 'list2': window.location = "user.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=find&user[membergroup][]=" + usergroupid; break;
			case 'reputation': window.location = "user.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=find&display[username]=1&display[options]=1&display[posts]=1&display[usergroup]=1&display[lastvisit]=1&display[reputation]=1&orderby=reputation&direction=desc&limitnumber=25&user[usergroupid]=" + usergroupid; break;
			case 'promote': window.location = "usergroup.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=modifypromotion&returnug=1&usergroupid=" + usergroupid; break;
			case 'leader': window.location = "usergroup.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=addleader&usergroupid=" + usergroupid; break;
			case 'requests': window.location = "usergroup.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=viewjoinrequests&usergroupid=" + usergroupid; break;
			default: return false; break;
		}
	}
	</script>
	<?php

	// ###################### Start makeusergroupcode #######################
	function print_usergroup_row($usergroup, $options)
	{
		global $usergroupleaders, $vbphrase, $promotions, $vbulletin;

		if ($promotions["$usergroup[usergroupid]"])
		{
			$options['promote'] .= " (${promotions[$usergroup[usergroupid]]})";
		}

		$cell = array();
		$cell[] = "<b>$usergroup[title]" . iif($usergroup['canoverride'], '*') . "</b>" . iif($usergroup['ispublicgroup'], '<br /><span class="smallfont">' . $usergroup['description'] . '</span>');
		$cell[] = iif($usergroup['count'], vb_number_format($usergroup['count']), '-');
		$cell[] = iif($usergroup['secondarycount'], vb_number_format($usergroup['secondarycount']), '-');

		if ($usergroup['ispublicgroup'])
		{
			$cell[] = iif($usergroup['requests'], vb_number_format($usergroup['requests']), '0');
		}
		if ($usergroup['ispublicgroup'])
		{
			$cell_out = '<span class="smallfont">';
			if (is_array($usergroupleaders["$usergroup[usergroupid]"]))
			{
				foreach($usergroupleaders["$usergroup[usergroupid]"] AS $usergroupleader)
				{
					$cell_out .= "<a href=\"user.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;u=$usergroupleader[userid]\"><b>$usergroupleader[username]</b></a>" . construct_link_code($vbphrase['delete'], "usergroup.php?" . vB::getCurrentSession()->get('sessionurl') . "do=removeleader&amp;usergroupleaderid=$usergroupleader[usergroupleaderid]") . '<br />';
				}
			}
			$cell[] = $cell_out . '</span>';
		}
		$options['edit'] .= " (id: $usergroup[usergroupid])";
		$cell[] = "\n\t<select name=\"u$usergroup[usergroupid]\" onchange=\"js_usergroup_jump($usergroup[usergroupid]);\" class=\"bginput\">\n" . construct_select_options($options) . "\t</select><input type=\"button\" class=\"button\" value=\"" . $vbphrase['go'] . "\" onclick=\"js_usergroup_jump($usergroup[usergroupid]);\" />\n\t";
		print_cells_row($cell);
	}

	print_form_header('usergroup', 'add');

	$options_default = array(
		'edit'       => $vbphrase['edit_usergroup'],
		'promote'    => $vbphrase['edit_promotions'],
		'list'       => $vbphrase['show_all_primary_users'],
		'list2'      => $vbphrase['show_all_additional_users'],
		'reputation' => $vbphrase['view_reputation']
	);
	$options_custom = array(
		'edit'       => $vbphrase['edit_usergroup'],
		'promote'    => $vbphrase['edit_promotions'],
		'kill'       => $vbphrase['delete_usergroup'],
		'list'       => $vbphrase['show_all_primary_users'],
		'list2'      => $vbphrase['show_all_additional_users'],
		'reputation' => $vbphrase['view_reputation']
	);
	$options_public = array(
		'edit'       => $vbphrase['edit_usergroup'],
		'promote'    => $vbphrase['edit_promotions'],
		'kill'       => $vbphrase['delete_usergroup'],
		'list'       => $vbphrase['show_all_primary_users'],
		'list2'      => $vbphrase['show_all_additional_users'],
		'reputation' => $vbphrase['view_reputation'],
		'leader'     => $vbphrase['add_usergroup_leader'],
		'requests'   => $vbphrase['view_join_requests_gcpusergroup']
	);

	print_table_header($vbphrase['default_usergroups'], 5);
	print_cells_row(array($vbphrase['title'], $vbphrase['primary_users_gcpuser'], $vbphrase['additional_users_gcpuser'], $vbphrase['controls']), 1);
	foreach($usergroups['default'] AS $usergroup)
	{
		print_usergroup_row($usergroup, $options_default);
	}
	if (is_array($usergroups['custom']))
	{
		print_table_break();
		print_table_header($vbphrase['custom_usergroups'], 5);
		print_cells_row(array($vbphrase['title'], $vbphrase['primary_users_gcpuser'], $vbphrase['additional_users_gcpuser'], $vbphrase['controls']), 1);
		foreach($usergroups['custom'] AS $usergroup)
		{
			print_usergroup_row($usergroup, $options_custom);
		}
		print_description_row('<span class="smallfont">' . $vbphrase['note_groups_marked_with_a_asterisk'] . '</span>', 0, 6);
	}
	if (is_array($usergroups['public']))
	{
		print_table_break();
		print_table_header($vbphrase['public_joinable_custom_usergroup'], 9);
		print_cells_row(array($vbphrase['title'], $vbphrase['primary_users_gcpuser'], $vbphrase['additional_users_gcpuser'], $vbphrase['join_requests'], $vbphrase['usergroup_leader'], $vbphrase['controls']), 1);
		foreach($usergroups['public'] AS $usergroup)
		{
			print_usergroup_row($usergroup, $options_public);
		}
		print_description_row('<span class="smallfont">' . $vbphrase['note_groups_marked_with_a_asterisk'] . '</span>', 0, 6);
	}

	print_table_break();
	print_submit_row($vbphrase['add_new_usergroup_gcpusergroup'], 0);

}

// ###################### Start modify promotions #######################
if ($_REQUEST['do'] == 'modifypromotion')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'returnug' => vB_Cleaner::TYPE_BOOL
	));

	$title = $assertor->assertQuery('vBForum:usergroup', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'usergroupid' => $vbulletin->GPC['usergroupid']));
	if ($title AND $title->valid())
	{
		$title = $title->current();
	}

	$promotions = vB_Api::instanceInternal('usergroup')->fetchPromotions($vbulletin->GPC['usergroupid'] ? $vbulletin->GPC['usergroupid'] : 0);

	print_form_header('usergroup', 'updatepromotion');
	if (isset($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]))
	{
		construct_hidden_code('usergroupid', $vbulletin->GPC['usergroupid']);
	}
	if ($vbulletin->GPC['returnug'])
	{
		construct_hidden_code('returnug', 1);
	}

	foreach($promotions AS $groupid => $promos)
	{
		print_table_header("$vbphrase[promotions]: <span style=\"font-weight:normal\">" . $vbulletin->usergroupcache["$groupid"]['title'] . ' ' . construct_link_code($vbphrase['add_new_promotion'], "usergroup.php?" . vB::getCurrentSession()->get('sessionurl') . "do=updatepromotion&amp;usergroupid=$groupid" . ($vbulletin->GPC['returnug'] ? '&amp;returnug=1' : '')) . "</span>", 7);
		print_cells_row(array(
			$vbphrase['usergroup'],
			$vbphrase['promotion_type'],
			$vbphrase['promotion_strategy'],
			$vbphrase['reputation_level_gcpglobal'],
			$vbphrase['days_registered'],
			$vbphrase['posts'],
			$vbphrase['controls']
		), 1);

		foreach($promos AS $promotion)
		{
			$promotion['strategy'] = iif(($promotion['strategy'] > 7 AND $promotion['strategy'] < 16) OR $promotion['strategy'] == 24, $promotion['strategy'] - 8, $promotion['strategy']);
			if ($promotion['strategy'] == 16)
			{
				$type = $vbphrase['reputation'];
			}
			else if ($promotion['strategy'] == 17)
			{
				$type = $vbphrase['posts'];
			}
			else if ($promotion['strategy'] == 18)
			{
				$type = $vbphrase['join_date'];
			}
			else
			{
				$type = $vbphrase['promotion_strategy' . ($promotion['strategy'] + 1)];
			}
			print_cells_row(array(
				"<b>$promotion[title]</b>",
				iif($promotion['type']==1, $vbphrase['primary_usergroup'], $vbphrase['additional_usergroups']),
				$type,
				$promotion['reputation'],
				$promotion['date'],
				$promotion['posts'],
				construct_link_code($vbphrase['edit'], "usergroup.php?" . vB::getCurrentSession()->get('sessionurl') . "userpromotionid=$promotion[userpromotionid]&do=updatepromotion" . ($vbulletin->GPC['returnug'] ? '&returnug=1' : '')) . construct_link_code($vbphrase['delete'], "usergroup.php?" . vB::getCurrentSession()->get('sessionurl') . "userpromotionid=$promotion[userpromotionid]&do=removepromotion" . ($vbulletin->GPC['returnug'] ? '&returnug=1' : '')),
			));
		}
	}

	print_submit_row($vbphrase['add_new_promotion'], 0, 7);

}

// ###################### Start edit/insert promotions #######################
if ($_REQUEST['do'] == 'updatepromotion')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'userpromotionid' => vB_Cleaner::TYPE_INT,
		'returnug'        => vB_Cleaner::TYPE_BOOL,
	));

	$usergroups = array();
	foreach($vbulletin->usergroupcache AS $usergroup)
	{
		$usergroups["{$usergroup['usergroupid']}"] = $usergroup['title'];
	}

	print_form_header('usergroup', 'doupdatepromotion');

	if (!$vbulletin->GPC['userpromotionid'])
	{
		$promotion = array(
			'reputation' => 1000,
			'date' => 30,
			'posts' => 100,
			'type' => 1,
			'reputationtype' => 0,
			'strategy' => 16
		);

		if ($vbulletin->GPC['usergroupid'])
		{
			$promotion['usergroupid'] = $vbulletin->GPC['usergroupid'];
		}

		if ($vbulletin->GPC['returnug'])
		{
			construct_hidden_code('returnug', 1);
		}
		print_table_header($vbphrase['add_new_promotion']);
		print_select_row($vbphrase['usergroup'], 'promotion[usergroupid]', $usergroups, $promotion['usergroupid']);

	}
	else
	{
		$promotion = $assertor->assertQuery('vBForum:getUserPromotionsAndUserGroups', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'userpromotionid' => $vbulletin->GPC['userpromotionid']));
		if ($promotion AND $promotion->valid())
		{
			$promotion = $promotion->current();
		}

		if (($promotion['strategy'] > 7 AND $promotion['strategy'] < 16) OR $promotion['strategy'] == 24)
		{
			$promotion['reputationtype'] = 1;
			$promotion['strategy'] -= 8;
		}
		else
		{
			$promotion['reputationtype'] = 0;
		}
		if ($vbulletin->GPC['returnug'])
		{
			construct_hidden_code('returnug', 1);
		}
		construct_hidden_code('userpromotionid', $vbulletin->GPC['userpromotionid']);
		construct_hidden_code('usergroupid', $promotion['usergroupid']);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['promotion'], $promotion['title'], $promotion['userpromotionid']));
	}

	$promotionarray = array(
		17=> $vbphrase['posts'],
		18=> $vbphrase['join_date'],
		16=> $vbphrase['reputation'],
		0 => $vbphrase['promotion_strategy1'],
		1 => $vbphrase['promotion_strategy2'],
		2 => $vbphrase['promotion_strategy3'],
		3 => $vbphrase['promotion_strategy4'],
		4 => $vbphrase['promotion_strategy5'],
		5 => $vbphrase['promotion_strategy6'],
		6 => $vbphrase['promotion_strategy7'],
		7 => $vbphrase['promotion_strategy8'],
	);

	print_input_row($vbphrase['reputation_level_gcpglobal'], 'promotion[reputation]', $promotion['reputation']);
	print_input_row($vbphrase['days_registered'], 'promotion[date]', $promotion['date']);
	print_input_row($vbphrase['posts'], 'promotion[posts]', $promotion['posts']);
	print_select_row($vbphrase['promotion_strategy'] . " <dfn> $vbphrase[promotion_strategy_description]</dfn>", 'promotion[strategy]', $promotionarray, $promotion['strategy']);
	print_select_row($vbphrase['promotion_type'] . ' <dfn>' . $vbphrase['promotion_type_description_primary_additional'] . '</dfn>', 'promotion[type]', array(1 => $vbphrase['primary_usergroup'], 2 => $vbphrase['additional_usergroups']), $promotion['type']);
	print_select_row($vbphrase['reputation_comparison_type'] . '<dfn>' . $vbphrase['reputation_comparison_type_desc'] . '</dfn>', 'promotion[reputationtype]', array($vbphrase['greater_or_equal_to'], $vbphrase['less_than']), $promotion['reputationtype']);
	print_chooser_row($vbphrase['move_user_to_usergroup_gpromotion'] . " <dfn>$vbphrase[move_user_to_usergroup_description]</dfn>", 'promotion[joinusergroupid]', 'usergroup', $promotion['joinusergroupid'], '&nbsp;');

	print_submit_row(iif(empty($vbulletin->GPC['userpromotionid']), $vbphrase['save'], '_default_'));
}

// ###################### Start do edit/insert promotions #######################
if ($_POST['do'] == 'doupdatepromotion')
{

	$vbulletin->input->clean_array_gpc('p', array(
		'promotion'       => vB_Cleaner::TYPE_ARRAY,
		'userpromotionid' => vB_Cleaner::TYPE_INT,
		'returnug'        => vB_Cleaner::TYPE_BOOL,
	));

	try
	{
		vB_Api::instanceInternal('usergroup')->savePromotion($vbulletin->GPC['promotion'], $vbulletin->GPC['usergroupid'], $vbulletin->GPC['userpromotionid']);
	}
	catch (vB_Exception_Api $e)
	{
		$errors = $e->get_errors();
		print_stop_message2($errors[0]);
	}

//	/**TODO
//	 * This code was commented before conversion to Assertor DB API
//	 */
//	// $title = $vbulletin->db->query_first("SELECT title FROM " . TABLE_PREFIX . "usergroup WHERE usergroupid = " . $vbulletin->GPC['usergroupid']);
//	// $message = str_replace('{title}', $title['title'], $message);

	$args = array(
		'do' => 'modifypromotion'
	);
	if ($vbulletin->GPC['returnug'])
	{
		$args['returnug'] = 1;
		$args['usergroupid'] = $vbulletin->GPC['usergroupid'];
	}
	print_stop_message2('saved_promotion_successfully', 'usergroup', $args);
}

// ###################### Start Remove #######################

if ($_REQUEST['do'] == 'removepromotion')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'userpromotionid' => vB_Cleaner::TYPE_INT,
		'returnug'        => vB_Cleaner::TYPE_BOOL,
	));
	print_delete_confirmation('userpromotion', $vbulletin->GPC['userpromotionid'], 'usergroup', 'killpromotion', 'promotion_usergroup', array('returnug' => $vbulletin->GPC['returnug']));

}

// ###################### Start Kill #######################
if ($_POST['do'] == 'killpromotion')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'userpromotionid' => vB_Cleaner::TYPE_INT,
		'returnug'        => vB_Cleaner::TYPE_BOOL,
	));
	vB_Api::instanceInternal('usergroup')->deletePromotion($vbulletin->GPC['userpromotionid']);

	$args = array(
		'do' => 'modifypromotion'
	);
	if ($vbulletin->GPC['returnug'])
	{
		$args['returnug'] = 1;
		$args['usergroupid'] = $vbulletin->GPC['usergroupid'];
	}
	print_stop_message2('deleted_promotion_successfully', 'usergroup', $args);
}

// #############################################################################
// process usergroup join requests
if ($_POST['do'] == 'processjoinrequests')
{

	$vbulletin->input->clean_array_gpc('p', array(
		'request' => vB_Cleaner::TYPE_ARRAY_INT
	));

	try
	{
		vB_Api::instanceInternal('usergroup')->processJoinRequests($vbulletin->GPC['usergroupid'], $vbulletin->GPC['request']);
	}
	catch (vB_Exception_Api $e)
	{
		$errors = $e->get_errors();
		print_stop_message2($errors[0]);
	}

	// and finally jump back to the join requests screen
	$_REQUEST['do'] = 'viewjoinrequests';
}

// #############################################################################
// show usergroup join requests
if ($_REQUEST['do'] == 'viewjoinrequests')
{

	// first query groups that have join requests
	$usergroups = array();
	try
	{
		$usergroups = vB_Api::instanceInternal('usergroup')->fetchJoinRequests();
	}
	catch (vB_Exception_Api $e)
	{
		$errors = $e->get_errors();
		print_stop_message2($errors[0]);
	}

	if (empty($usergroups))
	{
		// there are no join requests
		print_stop_message2('nothing_to_do');
	}

	// if we got this far we know that we have at least one group with some requests in it
	// create array to hold options for the menu
	$groupsmenu = array();

	foreach ($usergroups AS $id => $usergroup)
	{
		$groupsmenu["$id"] = htmlspecialchars_uni($usergroup['title']) . " ($vbphrase[join_requests]: " . vb_number_format($usergroup['joinrequests']) . ")";
	}

	print_form_header('usergroup', 'viewjoinrequests', 0, 1, 'chooser');
	print_label_row(
		$vbphrase['usergroup'],
		'<select name="usergroupid" onchange="this.form.submit();" class="bginput">' . construct_select_options($groupsmenu, $vbulletin->GPC['usergroupid']) . '</select><input type="submit" class="button" value="' . $vbphrase['go'] . '" />',
		'thead'
	);
	print_table_footer();
	unset($groupsmenu);

	// now if we are being asked to display a particular usergroup, do so.
	if ($vbulletin->GPC['usergroupid'])
	{
		try
		{
			$requests = vB_Api::instanceInternal('usergroup')->fetchJoinRequests($vbulletin->GPC['usergroupid']);
		}
		catch (vB_Exception_Api $e)
		{
			$errors = $e->get_errors();
			print_stop_message2($errors[0]);
		}

		if (empty($requests))
		{
			print_stop_message2('no_join_requests_matched_your_query');
		}
		// everything seems okay, so make a total record for this usergroup
		$usergroup =& $usergroups["{$vbulletin->GPC['usergroupid']}"];

		// query the usergroup leaders of this usergroup
		$leaders = array();
		$getleaders = $assertor->assertQuery('vBForum:getUserGroupLeaders',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'usergroupid' => $vbulletin->GPC['usergroupid'])
		);
		if ($getleaders AND $getleaders->valid())
		{
			foreach($getleaders AS $getleader)
			{
				$leaders[] = "<a href=\"user.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;u=$getleader[userid]\">$getleader[username]</a>";
			}
		}
		unset($getleader);
		print_form_header('usergroup', 'processjoinrequests');
		construct_hidden_code('usergroupid', $vbulletin->GPC['usergroupid']);
		print_table_header("$usergroup[title] - ($vbphrase[join_requests]: $usergroup[joinrequests])", 6);
		if (!empty($leaders))
		{
			print_description_row("<span style=\"font-weight:normal\">(" . $vbphrase['usergroup_leader'] . ': ' . implode(', ', $leaders) . ')</span>', 0, 6, 'thead');
		}
		print_cells_row(array
		(
			$vbphrase['username'],
			$vbphrase['reason'],
			'<span style="white-space:nowrap">' . $vbphrase['date'] . '</span>',
			'<input type="button" value="' . $vbphrase['accept'] . '" onclick="js_check_all_option(this.form, 1);" class="button" title="' . $vbphrase['check_all'] . '" />',
			'<input type="button" value=" ' . $vbphrase['deny'] . ' " onclick="js_check_all_option(this.form, 0);" class="button" title="' . $vbphrase['check_all'] . '" />',
			'<input type="button" value="' . $vbphrase['ignore'] . '" onclick="js_check_all_option(this.form, -1);" class="button" title="' . $vbphrase['check_all'] . '" />'
		), 1);

		$i = 0;

		foreach ($requests AS $request)
		{
			if ($i > 0 AND $i % 10 == 0)
			{
				print_description_row('<div align="center"><input type="submit" class="button" value="' . $vbphrase['process'] . '" accesskey="s" tabindex="1" /></div>', 0, 6, 'thead');
			}
			$i++;
			$cell = array
			(
				"<a href=\"user.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;u=$request[userid]\"><b>$request[username]</b></a>",
				$request['reason'],
				'<span class="smallfont">' . vbdate($vbulletin->options['dateformat'], $request['dateline']) . '<br />' . vbdate($vbulletin->options['timeformat'], $request['dateline']) . '</span>',
				'<label for="a' . $request['usergrouprequestid'] . '" class="smallfont">' . $vbphrase['accept'] . '<input type="radio" name="request[' . $request['usergrouprequestid'] . ']" value="1" id="a' . $request['usergrouprequestid'] . '" tabindex="1" /></label>',
				'<label for="d' . $request['usergrouprequestid'] . '" class="smallfont">' . $vbphrase['deny'] . '<input type="radio" name="request[' . $request['usergrouprequestid'] . ']" value="0" id="d' . $request['usergrouprequestid'] . '" tabindex="1" /></label>',
				'<label for="i' . $request['usergrouprequestid'] . '" class="smallfont">' . $vbphrase['ignore'] . '<input type="radio" name="request[' . $request['usergrouprequestid'] . ']" value="-1" id="i' . $request['usergrouprequestid'] . '" tabindex="1" checked="checked" /></label>'
			);
			print_cells_row($cell, 0, '', -5);
		}
		unset($request);

		print_submit_row($vbphrase['process'], $vbphrase['reset'], 6);

	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 71335 $
|| ####################################################################
\*======================================================================*/
?>
