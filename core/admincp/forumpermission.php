<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright  2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 69532 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbphrase, $npermscache;
$phrasegroups = array('cppermission', 'forum');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/adminfunctions_forums.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminpermissions'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################

$vbulletin->input->clean_array_gpc('r', array(
	'np'	=> vB_Cleaner::TYPE_INT,
	'n'		=> vB_Cleaner::TYPE_INT,
	'u'		=> vB_Cleaner::TYPE_INT
));

log_admin_action(
	iif($vbulletin->GPC['np'] != 0, "nodepermission id = " . $vbulletin->GPC['np'],
	iif($vbulletin->GPC['n'] != 0, "node id = " . $vbulletin->GPC['n'] .
	iif($vbulletin->GPC['u'] != 0, " / usergroup id = " . $vbulletin->GPC['u']
))));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

// Load defaultchannelpermissions datastore as its not loaded by default
vB::getDatastore()->fetch('defaultchannelpermissions');

print_cp_header($vbphrase['channel_permissions_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start edit #######################
if ($_REQUEST['do'] == 'edit')
{
	$nodeid =& $vbulletin->GPC['n'];
	$usergroupid =& $vbulletin->GPC['u'];
	$permissionid =& $vbulletin->GPC['np'];

	?>
	<script type="text/javascript">
	<!--
	function js_set_custom()
	{
		if (document.cpform.inherit[1].checked == false)
		{
			if (confirm("<?php echo $vbphrase['must_enable_custom_permissions']; ?>"))
			{
				document.cpform.inherit[1].checked = true;
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			return true;
		}
	}
	// -->
	</script>
	<?php

	print_form_header('forumpermission', 'doupdate');

	if (!empty($permissionid))
	{
		$nodepermission = vB_ChannelPermission::instance()->fetchPermById($permissionid);
		$nodepermission = current($nodepermission);
		$nodeid = $nodepermission['nodeid'];
		$usergroupid = $nodepermission['groupid'];
	}
	else if (!empty($nodeid) AND !empty($usergroupid))
	{
		$nodepermission = vB_ChannelPermission::instance()->fetchPermissions($nodeid, $usergroupid);
		$nodepermission = current($nodepermission);
	}
	else
	{
		print_table_footer();
		print_stop_message2('invalid_channel_permissions_specified');
	}

	if (empty($nodepermission) OR !empty($nodepermission['errors']))
	{
		print_table_footer();
		print_stop_message2('invalid_channel_permissions_specified');
	}

	construct_hidden_code('nodepermission[usergroupid]', $usergroupid);
	construct_hidden_code('nodeid', $nodeid);
	construct_hidden_code('permissionid', $nodepermission['permissionid']);
	$channel = vB_Api::instanceInternal('node')->getNode($nodeid);
	$usergroup = vB_Api::instanceInternal('usergroup')->fetchUsergroupByID($usergroupid);

	print_table_header(construct_phrase($vbphrase['edit_channel_permissions_for_usergroup_x_in_channel_y'], $usergroup['title'], $channel['title']));
	if ($nodeid > 1)
	{
		print_description_row('
			<label for="uug_1"><input type="radio" name="inherit" value="1" id="inherit_1" onclick="this.form.reset(); this.checked=true;"' . iif(empty($permissionid), ' checked="checked"') . ' />' . $vbphrase['inherit_channel_permission'] . '</label>
			<br />
			<label for="uug_0"><input type="radio" name="inherit" value="0" id="inherit_0"' . iif(!empty($permissionid), ' checked="checked"') . ' />' . $vbphrase['use_custom_permissions'] . '</label>
		', 0, 2, 'tfoot', '' , 'mode');
		print_table_break();
	}
	print_channel_permission_rows($vbphrase['edit_channel_permissions'], $nodepermission, 'js_set_custom();');

	print_submit_row($vbphrase['save']);

}

// ###################### Start do update #######################
if ($_POST['do'] == 'doupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'permissionid'	=> vB_Cleaner::TYPE_INT,
		'nodepermission'	=> vB_Cleaner::TYPE_ARRAY_INT,	// Its only ever refrenced as an array would be
		'useusergroup' 		=> vB_Cleaner::TYPE_INT,
		'nodeid' 			=> vB_Cleaner::TYPE_INT,
		'inherit'			=> vB_Cleaner::TYPE_INT
		));
	if ($vbulletin->GPC_exists['permissionid'] AND intval($vbulletin->GPC['permissionid']))
	{
		$groupid = 0;
		$nodeid = 0;
		$params = array('permissionid' => $vbulletin->GPC['permissionid']);
	}
	else if ($vbulletin->GPC_exists['permissionid'] AND $vbulletin->GPC_exists['nodepermission'])
	{
		$groupid =  $vbulletin->GPC['nodepermission']['usergroupid'];
		$nodeid = $vbulletin->GPC['nodeid'];
		$params = array('nodeid' => $nodeid, 'groupid' => $groupid);
		if (!intval($groupid) OR !intval($nodeid))
		{
			print_table_footer();
			print_stop_message2('invalid_usergroup_specified');
		}
	}

	if ($vbulletin->GPC_exists['inherit'] AND intval($vbulletin->GPC['inherit']))
	{
		vB_ChannelPermission::instance()->deletePerms($params);
	}
	else
	{
		$result = vB_ChannelPermission::instance()->setPermissions($nodeid, $groupid, $_POST);
	}
	print_stop_message2('saved_channel_permissions_successfully', 'forumpermission', array(
		'do' => 'modify',
		'n'  => $nodeid .'#node' . $nodeid
	));

}

// ###################### Start duplicator #######################
if ($_REQUEST['do'] == 'duplicate')
{
	$result = vB::getDbAssertor()->assertQuery('fetchpermgroups', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));

	$ugarr = array();

	while($result AND $result->valid())
	{
			$permgroup = $result->current();
			$ugarr["$permgroup[usergroupid]"] = $permgroup['title'];
			$result->next();
	}
	if (!empty($ugarr))
	{
		$usergrouplist = array();
		foreach($vbulletin->usergroupcache AS $usergroup)
		{
			$usergrouplist[] = "<input type=\"checkbox\" name=\"usergrouplist[$usergroup[usergroupid]]\" value=\"1\" /> $usergroup[title]";
		}
		$usergrouplist = implode("<br />\n", $usergrouplist);

		print_form_header('forumpermission', 'doduplicate_group');
		print_table_header($vbphrase['usergroup_based_permission_duplicator']);
		print_select_row($vbphrase['copy_permissions_from_group'], 'ugid_from', $ugarr);
		print_label_row($vbphrase['copy_permissions_to_groups'], "<span class=\"smallfont\">$usergrouplist</span>", '', 'top', 'usergrouplist');
		print_channel_chooser($vbphrase['only_copy_permissions_from_channel'], 'limitnodeid', 0);
		print_yes_no_row($vbphrase['overwrite_duplicate_entries'], 'overwritedupes_group', 0);
		print_yes_no_row($vbphrase['overwrite_inherited_entries'], 'overwriteinherited_group', 0);
		print_submit_row($vbphrase['go']);
	}

	// generate forum check boxes
	$channellist = array();
	$channels = vB_Api::instanceInternal('search')->getChannels(true);
	foreach($channels AS $nodeid => $channel)
	{
		$depth = str_repeat('--', $channel['depth']);
		$channellist[] = "<input type=\"checkbox\" name=\"channellist[$channel[nodeid]]\" value=\"1\" tabindex=\"1\" />$depth $channel[htmltitle] ";
	}
	$channellist = implode("<br />\n", $channellist);

	print_form_header('forumpermission', 'doduplicate_channel');
	print_table_header($vbphrase['channel_based_permission_duplicator']);
	print_channel_chooser($vbphrase['copy_permissions_from_channel'], 'nodeid_from', 0);
	print_label_row($vbphrase['copy_permissions_to_channels'], "<span class=\"smallfont\">$channellist</span>", '', 'top', 'channellist');
	//print_chooser_row($vbphrase['only_copy_permissions_from_group'], 'limitugid', 'usergroup', -1, $vbphrase['all_usergroups']);
	print_yes_no_row($vbphrase['overwrite_duplicate_entries'], 'overwritedupes_channel', 0);
	print_yes_no_row($vbphrase['overwrite_inherited_entries'], 'overwriteinherited_channel', 0);
	print_submit_row($vbphrase['go']);

}

// ###################### Start do duplicate (group-based) #######################
if ($_POST['do'] == 'doduplicate_group')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'ugid_from' 				=> vB_Cleaner::TYPE_INT,
		'limitnodeid' 				=> vB_Cleaner::TYPE_INT,
		'overwritedupes_group' 		=> vB_Cleaner::TYPE_INT,
		'overwriteinherited_group' 	=> vB_Cleaner::TYPE_INT,
		'usergrouplist' 			=> vB_Cleaner::TYPE_ARRAY
	));
	if (sizeof($vbulletin->GPC['usergrouplist']) == 0)
	{
		print_stop_message2('invalid_usergroup_specified');
	}

//	if ($vbulletin->GPC['limitnode'] > 0)
//	{
//		$foruminfo = fetch_foruminfo($vbulletin->GPC['limitnodeid']);
//		$forumsql = "AND forumpermission.forumid IN ($foruminfo[parentlist])";
//		$childforum = "AND forumpermission.forumid IN ($foruminfo[childlist])";
//	}
//	else
//	{
//		$childforum = '';
//		$forumsql = '';
//	}

	$assertor = vB::getDbAssertor();

	foreach ($vbulletin->GPC['usergrouplist'] AS $ugid_to => $confirm)
	{
		$ugid_to = intval($ugid_to);
		if ($vbulletin->GPC['ugid_from'] == $ugid_to OR $confirm != 1)
		{
			continue;
		}
		$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'groupid' => $ugid_to
		);
		$queryid = 'fetchExistingPermsForGroup';
		if ($vbulletin->GPC['limitnodeid'] > 1)
		{
			$params['parentid'] = $vbulletin->GPC['limitnodeid'];
			$queryid = 'fetchExistingPermsForGroupLimit';
		}
		$result = $assertor->assertQuery($queryid, $params);
		$perm_set = array();
		// get existing permissions
		while($result AND $result->valid())
		{
			$permission = $result->current();
			$perm_set[] = $permission['nodeid'];
			$result->next();
		}
//		$existing = $vbulletin->db->query_read("
//			SELECT forumpermission.forumid, forum.parentlist
//			FROM " . TABLE_PREFIX . "forumpermission AS forumpermission, " . TABLE_PREFIX . "forum AS forum
//			WHERE forumpermission.forumid = forum.forumid
//				AND groupid = $ugid_to
//				$forumsql
//		");
//		while ($thisperm = $vbulletin->db->fetch_array($existing))
//		{
//			$perm_set[] = $thisperm['forumid'];
//		}

		$perm_inherited = array();
		if (sizeof($perm_set) > 0)
		{
			$result =$assertor->assertQuery('vBForum:closure', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field'=>'parent',	'value'=>$perm_set, 'operator' => vB_dB_Query::OPERATOR_EQ),
						array('field'=>'depth',	'value'=>0, 'operator' => vB_dB_Query::OPERATOR_GT),
					)
			));
			while($result AND $result->valid())
			{
				$child = $result->current();
				$perm_inherited[] = $child['child'];
				$result->next();
			}
//			$inherits = $vbulletin->db->query_read("
//				SELECT forumid
//				FROM " . TABLE_PREFIX . "forum
//				WHERE CONCAT(',', parentlist, ',') LIKE '%," . implode(",%' OR CONCAT(',', parentlist, ',') LIKE '%,", $perm_set) . ",%'
//			");
//			while ($thisperm = $vbulletin->db->fetch_array($inherits))
//			{
//				$perm_inherited[] = $thisperm['forumid'];
//			}
		}

//		$forumsql_local = '';
//
//		if (!$vbulletin->GPC['overwritedupes_group'] OR !$vbulletin->GPC['overwriteinherited_group'])
//		{
//			$exclude = array('0');
//			if (!$vbulletin->GPC['overwritedupes_group'])
//			{
//				$exclude = array_merge($exclude, $perm_set);
//			}
//			if (!$vbulletin->GPC['overwriteinherited_group'])
//			{
//				$exclude = array_merge($exclude, $perm_inherited);
//			}
//			$exclude = array_unique($exclude);
//			$forumsql_local .= ' AND forumpermission.forumid NOT IN (' . implode(',', $exclude) . ')';
//		}

//		$perms = $vbulletin->db->query_read("
//			SELECT forumid, forumpermissions
//			FROM " . TABLE_PREFIX . "forumpermission AS forumpermission
//			WHERE usergroupid = " . $vbulletin->GPC['ugid_from'] . "
//				$childforum
//				$forumsql_local
//		");
		$condition = array(
						array('field'=>'groupid',	'value'=>$vbulletin->GPC['ugid_from'], 'operator' => vB_dB_Query::OPERATOR_EQ)
					);
		if (!$vbulletin->GPC['overwritedupes_group'] OR !$vbulletin->GPC['overwriteinherited_group'])
		{
			$exclude = array('1');
			if (!$vbulletin->GPC['overwritedupes_group'])
			{
				$exclude = array_merge($exclude, $perm_set);
			}
			if (!$vbulletin->GPC['overwriteinherited_group'])
			{
				$exclude = array_merge($exclude, $perm_inherited);
			}
			$exclude = array_unique($exclude);
			$condition[] = array('field'=>'nodeid',	'value'=>$exclude, 'operator' => vB_dB_Query::OPERATOR_NE);
		}


		if ($vbulletin->GPC['limitnodeid'] > 0)
		{
			$result = $assertor->assertQuery('vBForum:closure', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field'=>'parent',	'value'=>$vbulletin->GPC['limitnodeid'], 'operator' => vB_dB_Query::OPERATOR_EQ),
						array('field'=>'depth',	'value'=>0, 'operator' => vB_dB_Query::OPERATOR_GT),
					)
			));
			$children = array();
			while($result AND $result->valid())
			{
				$child = $result->current();
				$children[] = $child['child'];
				$result->next();
			}
			if (!empty($children))
			{
				$condition[] = array('field'=>'nodeid',	'value'=>$children, 'operator' => vB_dB_Query::OPERATOR_EQ);
			}
			else
			{
				$condition[] = array('field'=>'nodeid',	'operator' => vB_dB_Query::OPERATOR_ISNULL);
			}
		}
		$result = $assertor->assertQuery('permission', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => $condition
		));
		while($result AND $result->valid())
		{
			$permission = $result->current();
			$assertor->assertQuery('replacePermissions', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'nodeid' => $permission['nodeid'],
				'usergroupid' => $ugid_to,
				'permissions' => $permission['forumpermissions']
			));
			$result->next();
		}
	}
	build_channel_permissions();
	print_stop_message2('duplicated_permissions_successfully', 'forumpermission', array('do'=>'modify'));
}

// ###################### Start do duplicate (forum-based) #######################
if ($_POST['do'] == 'doduplicate_channel')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'nodeid_from'					=> vB_Cleaner::TYPE_INT,
		'overwritedupes_channel'		=> vB_Cleaner::TYPE_INT,
		'overwriteinherited_channel'	=> vB_Cleaner::TYPE_INT,
		'channellist' 					=> vB_Cleaner::TYPE_ARRAY
	));

	if (sizeof($vbulletin->GPC['channellist']) == 0)
	{
		print_stop_message2('invalid_channel_specified');
	}

	$result = vB::getDbAssertor()->assertQuery('permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
		'nodeid' => $vbulletin->GPC['nodeid_from']
	));
	$copyperms = array();
	if (!$result OR !$result->valid())
	{
		print_stop_message2('no_permissions_set');
	}
	else
	{
		while($result AND $result->valid())
		{
			$permission = $result->current();
			$copyperms["$permission[groupid]"] = $permission['forumpermissions'];
			$result->next();
		}
	}

	$permscache = array();
	if (!$vbulletin->GPC['overwritedupes_channel'] OR !$vbulletin->GPC['overwriteinherited_channel'])
	{
		// query channel permissions
		$result = vB::getDbAssertor()->assertQuery('fetchinherit', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));
		while($result AND $result->valid())
		{
			$permission = $result->current();
			$permscache["$permission[nodeid]"]["$permission[groupid]"] = $permission['inherited'];
			$result->next();
		}

	}
	$assertor = vB::getDbAssertor();
	foreach ($vbulletin->GPC['channellist'] AS $nodeid_to => $confirm)
	{
		$nodeid_to = intval($nodeid_to);
		if ($nodeid_to == $vbulletin->GPC['nodeid_from'] OR !$confirm)
		{
			continue;
		}
		foreach ($copyperms AS $usergroupid => $permissions)
		{
			if (!$vbulletin->GPC['overwritedupes_channel'] AND isset($permscache["$nodeid_to"]["$usergroupid"]) AND $permscache["$nodeid_to"]["$usergroupid"] == 0)
			{
				continue;
			}
			if (!$vbulletin->GPC['overwriteinherited_channel'] AND $permscache["$nodeid_to"]["$usergroupid"] == 1)
			{
				continue;
			}
			/*insert query*/
			$assertor->assertQuery('replacePermissions', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'nodeid' => $nodeid_to,
				'usergroupid' => $usergroupid,
				'permissions' => $permissions
			));
		}
	}

	build_channel_permissions();

	print_stop_message2('duplicated_permissions_successfully', 'forumpermission', array('do'=>'modify'));
}

// ###################### Start quick edit #######################
if ($_REQUEST['do'] == 'quickedit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'orderby' => vB_Cleaner::TYPE_STR
	));

	print_form_header('forumpermission', 'doquickedit');
	print_table_header($vbphrase['permissions_quick_editor'], 4);
	print_cells_row(array(
		'<input type="checkbox" name="allbox" title="' . $vbphrase['check_all'] . '" onclick="js_check_all(this.form);" />',
		"<a href=\"forumpermission.php?" . vB::getCurrentSession()->get('sessionurl') . "do=quickedit&amp;orderby=channel\" title=\"" . $vbphrase['order_by_channel'] . "\">" . $vbphrase['channel'] . "</a>",
		"<a href=\"forumpermission.php?" . vB::getCurrentSession()->get('sessionurl') . "do=quickedit&amp;orderby=usergroup\" title=\"" . $vbphrase['order_by_usergroup'] . "\">" . $vbphrase['usergroup'] . "</a>",
		$vbphrase['controls']
	), 1);
	$result = vB::getDbAssertor()->assertQuery('fetchperms', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'order_first' => $vbulletin->GPC['orderby'] == 'usergroup' ? 'usergroup' : 'node'));
	if($result AND $result->valid())
	{
		while($result->valid())
		{
			$perm = $result->current();
			print_cells_row(array("<input type=\"checkbox\" name=\"permission[$perm[permissionid]]\" value=\"1\" tabindex=\"1\" />", $perm['node_title'], $perm['ug_title'], construct_link_code($vbphrase['edit'], "forumpermission.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;np=$perm[permissionid]")));
			$result->next();
		}
		print_submit_row($vbphrase['delete_selected_permissions'], $vbphrase['reset'], 4);
	}
	else
	{
		print_description_row($vbphrase['nothing_to_do'], 0, 4, '', 'center');
	}

	print_table_footer();

}

// ###################### Start do quick edit #######################
if ($_POST['do'] == 'doquickedit')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'permission' => vB_Cleaner::TYPE_ARRAY
	));

	if (sizeof($vbulletin->GPC['permission'])  == 0)
	{
		print_stop_message2('nothing_to_do');
	}

	$removeids = array();
	foreach ($vbulletin->GPC['permission'] AS $permissionid => $confirm)
	{
		if ($confirm == 1)
		{
			$removeids[] = intval($permissionid);
		}
	}

	$result = vB::getDbAssertor()->assertQuery('permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, 'permissionid' => $removeids));

	build_channel_permissions();

	print_stop_message2('deleted_forum_permissions_successfully', 'forumpermission', array('do'=>'modify'));
}

// ###################### Start quick forum setup #######################
if ($_REQUEST['do'] == 'quickforum')
{
	$usergrouplist = array();
	foreach($vbulletin->usergroupcache AS $usergroupid => $usergroup)
	{
		$usergrouplist[] = "<input type=\"checkbox\" name=\"usergrouplist[$usergroup[usergroupid]]\" id=\"usergrouplist_$usergroup[usergroupid]\" value=\"1\" tabindex=\"1\" /><label for=\"usergrouplist_$usergroup[usergroupid]\">$usergroup[title]</label>";
	}
	$usergrouplist = implode('<br />', $usergrouplist);

	print_form_header('forumpermission', 'doquickforum');
	print_table_header($vbphrase['quick_channel_permission_setup']);
	print_channel_chooser($vbphrase['apply_permissions_to_channel'], 'nodeid', 0);
	print_label_row($vbphrase['apply_permissions_to_usergroup'], "<span class=\"smallfont\">$usergrouplist</span>", '', 'top', 'usergrouplist');
	print_description_row($vbphrase['permission_overwrite_notice']);

	print_table_break();
	print_channel_permission_rows($vbphrase['permissions']);
	print_submit_row();
}

// ###################### Start do quick forum #######################
if ($_POST['do'] == 'doquickforum')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'usergrouplist'		=> vB_Cleaner::TYPE_ARRAY,
		'nodeid'			=> vB_Cleaner::TYPE_INT,
		'forumpermissions'	=> vB_Cleaner::TYPE_ARRAY_INT,
		'moderatorpermissions'	=> vB_Cleaner::TYPE_ARRAY_INT,
		'createpermissions'	=> vB_Cleaner::TYPE_ARRAY_INT,
		'edit_time'			=> vB_Cleaner::TYPE_INT,
		'require_moderate'	=> vB_Cleaner::TYPE_INT,
		'maxtags'			=> vB_Cleaner::TYPE_INT,
		'maxstartertags'	=> vB_Cleaner::TYPE_INT,
		'maxothertags'		=> vB_Cleaner::TYPE_INT,
		'maxattachments'	=> vB_Cleaner::TYPE_INT,
	));

	if (sizeof($vbulletin->GPC['usergrouplist']) == 0)
	{
		print_stop_message2('invalid_usergroup_specified');
	}

	require_once(DIR . '/includes/functions_misc.php');
	$bf_ugp_forumpermissions = vB::getDatastore()->getValue('bf_ugp_forumpermissions');
	$bf_misc_moderatorpermissions = vB::getDatastore()->getValue('bf_misc_moderatorpermissions');
	$bf_ugp_createpermissions = vB::getDatastore()->getValue('bf_ugp_createpermissions');

	$forumpermbits = convert_array_to_bits($vbulletin->GPC['forumpermissions'], $bf_ugp_forumpermissions, 1);
	$moderatorpermbits = convert_array_to_bits($vbulletin->GPC['moderatorpermissions'], $bf_misc_moderatorpermissions, 1);
	$createpermbits = convert_array_to_bits($vbulletin->GPC['createpermissions'], $bf_ugp_createpermissions, 1);

	foreach ($vbulletin->GPC['usergrouplist'] AS $usergroupid => $confirm)
	{
		if ($confirm == 1)
		{
			$usergroupid = intval($usergroupid);
			/*insert query*/
			vB::getDbAssertor()->assertQuery('replacePermissions', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'nodeid' => $vbulletin->GPC['nodeid'],
				'usergroupid' => $usergroupid,
				'forumpermissions' => $forumpermbits,
				'moderatorpermissions' => $moderatorpermbits,
				'createpermissions' => $createpermbits,
				'edit_time' => $vbulletin->GPC['edit_time'],
				'require_moderate' => $vbulletin->GPC['require_moderate'],
				'maxtags' => $vbulletin->GPC['maxtags'],
				'maxstartertags' => $vbulletin->GPC['maxstartertags'],
				'maxothertags' => $vbulletin->GPC['maxothertags'],
				'maxattachments' => $vbulletin->GPC['maxattachments'],
			));

			// Legacy Hook 'admin_nperms_doquickforum' Removed //
		}
	}

	build_channel_permissions();

	print_stop_message2('saved_channel_permissions_successfully','forumpermission',array(
				'do' => 'modify',
				'n' => $vbulletin->GPC['nodeid']
			));
}

// ###################### Start quick set #######################
if ($_REQUEST['do'] == 'quickset')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'type'		=> vB_Cleaner::TYPE_STR,
		'nodeid'	=> vB_Cleaner::TYPE_INT
	));

	verify_cp_sessionhash();

	if (!$vbulletin->GPC['nodeid'])
	{
		print_stop_message2('invalid_channel_specified');
	}

	try
	{
		$channel = vB_Api::instanceInternal('node')->getNode($vbulletin->GPC['nodeid']);
		if ($channel['parentid'] == 0)
		{
			print_stop_message2('invalid_channel_specified');
		}
	}
	catch(exception $e)
	{
		print_stop_message2('invalid_channel_specified');
	}

	switch ($vbulletin->GPC['type'])
	{
		case 'reset':
			vB::getDbAssertor()->assertQuery('vBForum:permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'nodeid' => $vbulletin->GPC['nodeid']));

			// If the nodeid is in default permissions, we need to copy default permissions back to permission table
			$defaultpermissions = vB_ChannelPermission::loadDefaultChannelPermissions();
			if (!empty($defaultpermissions["node_" . $vbulletin->GPC['nodeid']]))
			{
				foreach ($defaultpermissions["node_" . $vbulletin->GPC['nodeid']] as $groupid => $perm)
				{
					$groupid = str_replace('group_', '', $groupid);

					$params = array();
					$params[vB_dB_Query::TYPE_KEY] = vB_dB_Query::QUERY_INSERT;
					$params['nodeid'] = $vbulletin->GPC['nodeid'];
					$params['groupid'] = intval($groupid);
					foreach ($perm as $k => $v)
					{
						$params[$k] = $v;
					}
					$id = vB::getDbAssertor()->assertQuery('vBForum:permission', $params);
				}
			}

			break;

		case 'deny':
			$usergroupcache = &vB::getDatastore()->getValue('usergroupcache');
			foreach ($usergroupcache as $group)
			{
				/*insert query*/
				vB::getDbAssertor()->assertQuery('replacePermissions', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'nodeid' => $vbulletin->GPC['nodeid'],
					'usergroupid' => $group['usergroupid'],
					'forumpermissions' => 0,
					'moderatorpermissions' => 0,
					'createpermissions' => 0,
					'edit_time' => 2,
					'require_moderate' => 1,
					'maxtags' => 0,
					'maxstartertags' => 0,
					'maxothertags' => 0,
					'maxattachments' => 0,
				));
			}
			break;

		default:
			print_stop_message2('invalid_quick_set_action');
	}

	build_channel_permissions();
	vB_Cache::instance()->event('perms_changed');
	vB::getUserContext()->rebuildGroupAccess();
	print_stop_message2('saved_channel_permissions_successfully', 'forumpermission', array(
		'do' => 'modify',
		'n'  => $vbulletin->GPC['nodeid']
	));
}

// ###################### Start fpgetstyle #######################
function fetch_forumpermission_style($permissions)
{
	global $vbulletin;

	if (!($permissions & $vbulletin->bf_ugp_forumpermissions['canview']))
	{
		return " style=\"list-style-type:circle;\"";
	}
	else
	{
		return '';
	}
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{
	print_form_header('', '');
	print_table_header($vbphrase['additional_functions_gcppermission']);
	print_description_row("<b><a href=\"forumpermission.php?" . vB::getCurrentSession()->get('sessionurl') . "do=duplicate\">" . $vbphrase['permission_duplication_tools'] . "</a> | <a href=\"forumpermission.php?" . vB::getCurrentSession()->get('sessionurl') . "do=quickedit\">" . $vbphrase['permissions_quick_editor'] . "</a> | <a href=\"forumpermission.php?" . vB::getCurrentSession()->get('sessionurl') . "do=quickforum\">" . $vbphrase['quick_channel_permission_setup'] . "</a></b>", 0, 2, '', 'center');
	print_table_footer();

	print_form_header('', '');
	print_table_header($vbphrase['view_channel_permissions_gcppermission']);
	print_description_row('
		<div class="darkbg" style="border: 2px inset"><ul class="darkbg">
		<li><b>' . $vbphrase['color_key'] . '</b></li>
		<li class="col-g">' . $vbphrase['standard_using_default_channel_permissions'] . '</li>
		<li class="col-c">' . $vbphrase['customized_using_custom_permissions_for_this_usergroup_gcppermission'] . '</li>
		<li class="col-i">' . $vbphrase['inherited_using_custom_permissions_inherited_channel_a_parent_channel'] . '</li>
		</ul></div>
	');
	print_table_footer();

	require_once(DIR . '/includes/functions_forumlist.php');

	// get forum orders
	//cache_ordered_forums(0, 1);

	// get moderators
	cache_moderators();

	//query channel permissions
	$result = vB::getDbAssertor()->assertQuery('vBForum:permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT));
	$npermscache = array();
	foreach ($result as $permission)
	{
		$npermscache[$permission['nodeid']][$permission['groupid']] = $permission;
	}

	// get usergroup default permissions
	$permissions = array();
	$usergroupcache = vB::getDatastore()->getValue('usergroupcache');
	foreach($usergroupcache AS $usergroupid => $usergroup)
	{
		$permissions["$usergroupid"] = $usergroup['forumpermissions'];
	}
	//build_channel_permissions();
?>
<center>
<div class="tborder" style="width: 100%">
<div class="alt1" style="padding: 8px">
<div class="darkbg" style="padding: 4px; border: 2px inset; text-align: <?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>">
<?php

	// run the display function
	if ($vbulletin->options['cp_collapse_forums'])
	{
?>
	<script type="text/javascript">
	<!--
	function js_forum_jump(nodeid)
	{
		if (forumid > 0)
		{
			window.location = 'forumpermission.php?do=modify&n=' + nodeid;
		}
	}
	-->
	</script>
		<?php
		$vbulletin->input->clean_array_gpc('g', array('nodeid' => vB_Cleaner::TYPE_INT));
		define('ONLYID', (!empty($vbulletin->GPC['nodeid']) ? $vbulletin->GPC['nodeid'] : $vbulletin->GPC['n']));

		$select = '<div align="center"><select name="nodeid" id="sel_foruid" tabindex="1" class="bginput" onchange="js_forum_jump(this.options[selectedIndex].value);">';
		$select .= construct_channel_chooser(ONLYID, true);
		$select .= "</select></div>\n";
		echo $select;

	}
	print_channels($permissions);

?>
</div>
</div>
</div>
</center>
<?php

}
function print_channels($permissions, $inheritance = array(), $channels = false, $indent = '	')
{
	global $vbulletin, $imodcache, $npermscache, $vbphrase;
	if ($channels === false)
	{
		$channels = vB_Api::instanceInternal('search')->getChannels();
	}
	foreach ($channels AS $nodeid => $node)
	{
		// make a copy of the current permissions set up
		$perms = $permissions;

		// make a copy of the inheritance set up
		$inherit = $inheritance;

		// echo channel title and links
		if (!defined('ONLYID') OR $nodeid == ONLYID)
		{
			echo "$indent<ul class=\"lsq\">\n";
			echo "$indent<li><b><a name=\"node$nodeid\" href=\"forum.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;n=$nodeid\">$node[htmltitle]</a></b>";
			if ($node['parentid'] != 0)
			{
				echo " <b><span class=\"smallfont\">(" . construct_link_code($vbphrase['reset'], "forumpermission.php?" . vB::getCurrentSession()->get('sessionurl') . "do=quickset&amp;type=reset&amp;n=$nodeid&amp;hash=" . CP_SESSIONHASH) . construct_link_code($vbphrase['deny_all'], "forumpermission.php?" . vB::getCurrentSession()->get('sessionurl') . "do=quickset&amp;type=deny&amp;n=$nodeid&amp;hash=" . CP_SESSIONHASH) . ")</span></b>";
			}

			// get moderators
			if (is_array($imodcache["$nodeid"]))
			{
				echo "<span class=\"smallfont\"><br /> - <i>" . $vbphrase['moderators'] . ":";
				foreach($imodcache["$nodeid"] AS $moderator)
				{
					// moderator username and links
					echo " <a href=\"moderator.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;moderatorid=$moderator[moderatorid]\">$moderator[username]</a>";
				}
				echo "</i></span>";
			}

			echo "$indent\t<ul class=\"usergroups\">\n";
		}
		$nplink = "";
		foreach($vbulletin->usergroupcache AS $usergroupid => $usergroup)
		{
			if ($inherit["$usergroupid"] == 'col-c')
			{
				$inherit["$usergroupid"] = 'col-i';
			}

			// if there is a custom permission for the current usergroup, use it
			if (isset($npermscache["$nodeid"]["$usergroupid"]) AND $node['parentid'] != 0 AND vB_ChannelPermission::compareDefaultChannelPermissions($nodeid, $usergroupid, $npermscache["$nodeid"]["$usergroupid"]))
			{
				$inherit["$usergroupid"] = 'col-c';
				$perms["$usergroupid"] = $npermscache[$nodeid][$usergroupid]['forumpermissions'];
				$nplink = 'np=' . $npermscache[$nodeid][$usergroupid]['permissionid'];
			}
			else
			{
				$nplink = "n=$nodeid&amp;u=$usergroupid";
			}

			// work out display style
			$liStyle = '';
			if (isset($inherit["$usergroupid"]))
			{
				$liStyle = " class=\"$inherit[$usergroupid]\"";
			}
			else
			{
				$liStyle = " class=\"col-g\"";
			}

			if (!($perms["$usergroupid"] & $vbulletin->bf_ugp_forumpermissions['canview']))
			{
				$liStyle .= " style=\"list-style:circle\"";
			}

			if (!defined('ONLYID') OR $nodeid == ONLYID)
			{
				echo "$indent\t<li$liStyle>" . construct_link_code($vbphrase['edit'], "forumpermission.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;$nplink") . $usergroup['title'] . "</li>\n";
			}
		}
		if (!defined('ONLYID') OR $nodeid == ONLYID)
		{
			echo "$indent\t</ul><br />\n";
		}

		if (defined('ONLYID') AND $nodeid == ONLYID)
		{
			echo "$indent</li>\n";
			echo "$indent</ul>\n";
			return;
		}
		if (!empty($node['channels']))
		{
			print_channels($perms, $inherit, $node['channels'], "$indent	");
		}
		if (!defined('ONLYID') OR $nodeid == ONLYID)
		{
			echo "$indent</li>\n";
		}
		unset($inherit);
		if (!defined('ONLYID') OR $nodeid == ONLYID)
		{
			echo "$indent</ul>\n";
		}

		if (!defined('ONLYID') AND $node['parentid'] == -1)
		{
			echo "<hr size=\"1\" />\n";
		}
	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 69532 $
|| ####################################################################
\*======================================================================*/
?>
