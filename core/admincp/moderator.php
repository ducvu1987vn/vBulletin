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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 71514 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbphrase;
$phrasegroups = array('cppermission', 'forum', 'moderator');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
$assertor = vB::getDbAssertor();


// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminforums'))
{
	print_cp_no_permission();
}

$vbulletin->input->clean_array_gpc('r', array(
	'nodeid'     => vB_Cleaner::TYPE_INT,
	'moderatorid' => vB_Cleaner::TYPE_UINT,
	'userid'      => vB_Cleaner::TYPE_UINT,
	'modusername' => vB_Cleaner::TYPE_STR,
	'redir'       => vB_Cleaner::TYPE_NOHTML,
));

// ############################# LOG ACTION ###############################
log_admin_action(
	($vbulletin->GPC['moderatorid'] != 0 ? " moderator id = " . $vbulletin->GPC['moderatorid'] :
		($vbulletin->GPC['nodeid'] != 0 ? "node id = " . $vbulletin->GPC['nodeid'] :
			($vbulletin->GPC['userid'] != 0 ? "user id = " . $vbulletin->GPC['userid'] :
				(!empty($vbulletin->GPC['modusername']) ? "mod username = " . $vbulletin->GPC['modusername'] : '')
			)
		)
	)
);

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['moderator_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start add / edit moderator #######################
if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit' OR $_REQUEST['do'] == 'editglobal')
{
	require_once(DIR . '/includes/class_bitfield_builder.php');
	if (vB_Bitfield_Builder::build(false) !== false)
	{
		$myobj =& vB_Bitfield_Builder::init();
		if (sizeof($myobj->data['misc']['moderatorpermissions']) != sizeof($vbulletin->bf_misc_moderatorpermissions)
			OR
			sizeof($myobj->data['misc']['moderatorpermissions2']) != sizeof($vbulletin->bf_misc_moderatorpermissions2))
		{
			$myobj->save();
			$file = pathinfo($vbulletin->scriptpath,  PATHINFO_FILENAME );
			print_stop_message2('rebuilt_bitfields_successfully', $file['filename']);
		}
	}
	else
	{
		echo "<strong>error</strong>\n";
		print_r(vB_Bitfield_Builder::fetch_errors());
	}

	if ($_REQUEST['do'] == 'editglobal')
	{
		$moderator = $assertor->getRow('vBForum:getModGlobalEdit', array('userid' => $vbulletin->GPC['userid']));

		print_form_header('moderator', 'update');
		construct_hidden_code('nodeid', '0');
		construct_hidden_code('modusername', $moderator['username'], false);
		$username = $moderator['username'];
		log_admin_action('username = ' . $moderator['username']);

		if (empty($moderator['moderatorid']))
		{
			// this user doesn't have a record for super mod permissions, which is equivalent to having them all (except the email perms)
			$globalperms['permissions'] = array_sum($vbulletin->bf_misc_moderatorpermissions) - ($vbulletin->bf_misc_moderatorpermissions['newthreademail'] + $vbulletin->bf_misc_moderatorpermissions['newpostemail']);
			$globalperms['permissions2'] = array_sum($vbulletin->bf_misc_moderatorpermissions2);
			$moderator = convert_bits_to_array($globalperms['permissions'], $vbulletin->bf_misc_moderatorpermissions);
			$perms2 = convert_bits_to_array($globalperms['permissions2'], $vbulletin->bf_misc_moderatorpermissions2);
			$moderator['username'] = $username;
			$moderator = array_merge($perms2, $moderator);
		}
		else
		{
			construct_hidden_code('moderatorid', $moderator['moderatorid']);
			$perms = convert_bits_to_array($moderator['permissions'], $vbulletin->bf_misc_moderatorpermissions);
			$perms2 = convert_bits_to_array($moderator['permissions2'], $vbulletin->bf_misc_moderatorpermissions2);
			$moderator = array_merge($perms, $perms2, $moderator);
		}

		print_table_header($vbphrase['super_moderator_permissions'] . ' - <span class="normal">' . $moderator['username'] . '</span>');
	}
	else if (empty($vbulletin->GPC['moderatorid']))
	{
		// add moderator - set default values
		$channel = vB_Api::instanceInternal('node')->getNode($vbulletin->GPC['nodeid']);
		// add moderator - set default values
		$moderator = array();
		foreach ($myobj->data['misc']['moderatorpermissions'] AS $permission => $option)
		{
			$moderator["$permission"] = $option['default'] ? 1 : 0;
		}
		foreach ($myobj->data['misc']['moderatorpermissions2'] AS $permission => $option)
		{
			$moderator["$permission"] = $option['default'] ? 1 : 0;
		}

		$moderator['nodeid'] = $channel['nodeid'];
		$moderator['htmltitle'] = $channel['htmltitle'];

		print_form_header('moderator', 'update');
		print_table_header(construct_phrase($vbphrase['add_new_moderator_to_channel_x'], $channel['htmltitle']));
	}
	else
	{
		// edit moderator - query moderator
		$moderator = $assertor->getRow('vBForum:getModeratorChannelInfo', array('moderatorid' => $vbulletin->GPC['moderatorid']));

		$perms = convert_bits_to_array($moderator['permissions'], $vbulletin->bf_misc_moderatorpermissions);
		$perms2 = convert_bits_to_array($moderator['permissions2'], $vbulletin->bf_misc_moderatorpermissions2);
		$moderator = array_merge($perms, $perms2, $moderator);
		log_admin_action('username = ' . $moderator['username'] . ', userid = ' . $moderator['userid']);

		// delete link
		print_form_header('moderator', 'remove');
		construct_hidden_code('moderatorid', $vbulletin->GPC['moderatorid']);
		print_table_header($vbphrase['if_you_would_like_to_remove_this_moderator_gmoderator'] . ' &nbsp; &nbsp; <input type="submit" class="button" value="' . $vbphrase['remove'] . '" tabindex="1" />');
		print_table_footer();

		print_form_header('moderator', 'update');
		construct_hidden_code('moderatorid', $vbulletin->GPC['moderatorid']);
		print_table_header(construct_phrase($vbphrase['edit_moderator_x_for_channel_y'], $moderator['username'], $moderator['htmltitle']));
	}

	if ($_REQUEST['do'] != 'editglobal')
	{
		print_channel_chooser($vbphrase['channel_and_subchannels'], 'nodeid', $moderator['nodeid']);
		if ($_REQUEST['do'] == 'add')
		{
			print_input_row($vbphrase['moderator_usernames'] . "<dfn>$vbphrase[separate_usernames_semicolon]</dfn>", 'modusername', $moderator['username'], 0);
		}
		else if ($_REQUEST['do'] == 'edit')
		{
			print_input_row($vbphrase['moderator_username'], 'modusername', $moderator['username'], 0);
		}

		construct_hidden_code('redir', $vbulletin->GPC['redir']);
	}

	// usergroup membership options
	if ($_REQUEST['do'] == 'add' AND can_administer('canadminusers'))
	{
		$usergroups = array(0 => $vbphrase['do_not_change_usergroup']);
		foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
		{
			$usergroups["$usergroupid"] = $usergroup['title'];
		}
		print_table_header($vbphrase['usergroup_options_gcpglobal']);
		print_select_row($vbphrase['change_moderator_primary_usergroup_to'], 'usergroupid', $usergroups, 0);
		print_membergroup_row($vbphrase['make_moderator_a_member_of'], 'membergroupids', 2);
	}

	// post permissions
	print_description_row($vbphrase['post_thread_permissions'], false, 2, 'thead');
	print_yes_no_row($vbphrase['can_edit_posts'], 'modperms[caneditposts]', $moderator['caneditposts']);
	print_yes_no_row($vbphrase['can_delete_posts'], 'modperms[candeleteposts]', $moderator['candeleteposts']);
	print_yes_no_row($vbphrase['can_physically_delete_posts'], 'modperms[canremoveposts]', $moderator['canremoveposts']);
	// thread permissions
	print_yes_no_row($vbphrase['can_open_close_threads'], 'modperms[canopenclose]', $moderator['canopenclose']);
	print_yes_no_row($vbphrase['can_edit_threads'], 'modperms[caneditthreads]', $moderator['caneditthreads']);
	print_yes_no_row($vbphrase['can_manage_threads'], 'modperms[canmanagethreads]', $moderator['canmanagethreads']);
	print_yes_no_row($vbphrase['can_edit_polls'], 'modperms[caneditpoll]', $moderator['caneditpoll']);
	// moderation permissions
	print_description_row($vbphrase['channel_permissions_gcppermission'], false, 2, 'thead');
	print_yes_no_row($vbphrase['can_post_announcements'], 'modperms[canannounce]', $moderator['canannounce']);
	print_yes_no_row($vbphrase['can_moderate_posts'], 'modperms[canmoderateposts]', $moderator['canmoderateposts']);
	print_yes_no_row($vbphrase['can_moderate_attachments'], 'modperms[canmoderateattachments]', $moderator['canmoderateattachments']);
	print_yes_no_row($vbphrase['can_mass_move_threads'], 'modperms[canmassmove]', $moderator['canmassmove']);
	print_yes_no_row($vbphrase['can_mass_prune_threads'], 'modperms[canmassprune]', $moderator['canmassprune']);
	print_yes_no_row($vbphrase['can_set_forum_password'], 'modperms[cansetpassword]', $moderator['cansetpassword']);
	// visitor messaging permissions
	print_description_row($vbphrase['visitor_message_permissions'], false, 2, 'thead');
	print_yes_no_row($vbphrase['can_edit_posts'], 'modperms[caneditvisitormessages]', $moderator['caneditvisitormessages']);
	print_yes_no_row($vbphrase['can_delete_posts'], 'modperms[candeletevisitormessages]', $moderator['candeletevisitormessages']);
	print_yes_no_row($vbphrase['can_physically_delete_posts'], 'modperms[canremovevisitormessages]', $moderator['canremovevisitormessages']);
	print_yes_no_row($vbphrase['can_moderate_posts'], 'modperms[canmoderatevisitormessages]', $moderator['canmoderatevisitormessages']);

	// Social Groups
	print_description_row($vbphrase['social_group_permissions'], false, 2, 'thead');
	print_yes_no_row($vbphrase['can_edit_social_groups'], 'modperms[caneditsocialgroups]', $moderator['caneditsocialgroups']);
	print_yes_no_row($vbphrase['can_delete_social_groups'], 'modperms[candeletesocialgroups]', $moderator['candeletesocialgroups']);
	print_yes_no_row($vbphrase['can_transfer_social_groups'], 'modperms[cantransfersocialgroups]', $moderator['cantransfersocialgroups']);

	print_yes_no_row($vbphrase['can_edit_pictures'], 'modperms[caneditgrouppicture]', $moderator['caneditgrouppicture']);
	print_yes_no_row($vbphrase['can_delete_pictures'], 'modperms[candeletegrouppicture]', $moderator['candeletegrouppicture']);
	print_yes_no_row($vbphrase['can_moderate_pictures'], 'modperms[canmoderategrouppicture]', $moderator['canmoderategrouppicture']);

	print_yes_no_row($vbphrase['can_edit_posts'], 'modperms[caneditgroupmessages]', $moderator['caneditgroupmessages']);
	print_yes_no_row($vbphrase['can_moderate_posts'], 'modperms[canmoderategroupmessages]', $moderator['canmoderategroupmessages']);
	print_yes_no_row($vbphrase['can_delete_posts'], 'modperms[candeletegroupmessages]', $moderator['candeletegroupmessages']);
	print_yes_no_row($vbphrase['can_physically_delete_posts'], 'modperms[canremovegroupmessages]', $moderator['canremovegroupmessages']);
	print_yes_no_row($vbphrase['can_edit_discussions'], 'modperms[caneditdiscussions]', $moderator['caneditdiscussions']);
	print_yes_no_row($vbphrase['can_moderate_discussions'], 'modperms[canmoderatediscussions]', $moderator['canmoderatediscussions']);
	print_yes_no_row($vbphrase['can_delete_discussions'], 'modperms[candeletediscussions]', $moderator['candeletediscussions']);
	print_yes_no_row($vbphrase['can_physically_delete_discussions'], 'modperms[canremovediscussions]', $moderator['canremovediscussions']);

	// user permissions
	print_description_row($vbphrase['user_permissions'], false, 2, 'thead');
	print_yes_no_row($vbphrase['can_view_ip_addresses'], 'modperms[canviewips]', $moderator['canviewips']);
	print_yes_no_row($vbphrase['can_view_whole_profile'], 'modperms[canviewprofile]', $moderator['canviewprofile']);
	print_yes_no_row($vbphrase['can_ban_users'], 'modperms[canbanusers]', $moderator['canbanusers']);
	print_yes_no_row($vbphrase['can_restore_banned_users'], 'modperms[canunbanusers]', $moderator['canunbanusers']);
	print_yes_no_row($vbphrase['can_edit_user_signatures'], 'modperms[caneditsigs]', $moderator['caneditsigs']);
	print_yes_no_row($vbphrase['can_edit_user_avatars'], 'modperms[caneditavatar]', $moderator['caneditavatar']);
	print_yes_no_row($vbphrase['can_edit_user_profile_pictures'], 'modperms[caneditprofilepic]', $moderator['caneditprofilepic']);
	print_yes_no_row($vbphrase['can_edit_user_reputation_comments_gcppermission'], 'modperms[caneditreputation]', $moderator['caneditreputation']);

	// album permissions
	print_description_row($vbphrase['user_album_permissions_gmoderator'], false, 2, 'thead');
	print_yes_no_row($vbphrase['can_edit_albums_pictures'], 'modperms[caneditalbumpicture]', $moderator['caneditalbumpicture']);
	print_yes_no_row($vbphrase['can_delete_albums_pictures'], 'modperms[candeletealbumpicture]', $moderator['candeletealbumpicture']);
	print_yes_no_row($vbphrase['can_moderate_pictures'], 'modperms[canmoderatepictures]', $moderator['canmoderatepictures']);
 	print_yes_no_row($vbphrase['can_edit_picture_comments'], 'modperms[caneditpicturecomments]', $moderator['caneditpicturecomments']);
 	print_yes_no_row($vbphrase['can_delete_picture_comments'], 'modperms[candeletepicturecomments]', $moderator['candeletepicturecomments']);
 	print_yes_no_row($vbphrase['can_remove_picture_comments'], 'modperms[canremovepicturecomments]', $moderator['canremovepicturecomments']);
 	print_yes_no_row($vbphrase['can_moderate_picture_comments'], 'modperms[canmoderatepicturecomments]', $moderator['canmoderatepicturecomments']);

	// new thread/new post email preferences
	print_description_row($vbphrase['email_preferences'], false, 2, 'thead');
	print_yes_no_row($vbphrase['receive_email_on_new_thread'], 'modperms[newthreademail]', $moderator['newthreademail']);
	print_yes_no_row($vbphrase['receive_email_on_new_post'], 'modperms[newpostemail]', $moderator['newpostemail']);

	// Legacy Hook 'admin_moderator_form' Removed //

	print_submit_row($vbphrase['save']);

}

// ###################### Start insert / update moderator #######################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'modperms'       => vB_Cleaner::TYPE_ARRAY_BOOL,
		'usergroupid'    => vB_Cleaner::TYPE_UINT,
		'membergroupids' => vB_Cleaner::TYPE_ARRAY_UINT
	));

	$modnames = $successnames = $moddata_dms = $moddata_existing = array();

	if (!isset($vbulletin->GPC['nodeid']))
	{
		$vbulletin->GPC['nodeid'] = 0;
	}

	if ($vbulletin->GPC['moderatorid'])
	{
		$moddata_existing = $assertor->getRow('vBForum:getModeratorInfoToUpdate', array('moderatorid' => $vbulletin->GPC['moderatorid']));
		$modnames[] = trim(htmlspecialchars_uni($vbulletin->GPC['modusername']));
		log_admin_action('username = ' . $moddata_existing['username'] . ', userid = ' . $moddata_existing['userid']);
	}
	else
	{
		// split multiple recipients into an array
		if (preg_match('/(?<!&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5});/', $vbulletin->GPC['modusername'])) // multiple recipients attempted
		{
			$modnamelist = preg_split('/(?<!&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5});/', $vbulletin->GPC['modusername'], -1, PREG_SPLIT_NO_EMPTY);
			foreach ($modnamelist AS $name)
			{
				$name = trim($name);
				if ($name != '')
				{
					$modnames[] = htmlspecialchars_uni($name);
				}
			}
		}
		// just a single user
		else
		{
			$modnames[] = trim(htmlspecialchars_uni($vbulletin->GPC['modusername']));
		}
	}

	foreach ($modnames AS $name)
	{
		if (empty($name))
		{
			continue;
		}

		$moddata =& datamanager_init('Moderator', $vbulletin, vB_DataManager_Constants::ERRTYPE_CP);

		if ($moddata_existing AND $moddata_existing['nodeid'] == $vbulletin->GPC['nodeid'])
		{
			$moddata->set_existing($moddata_existing);
		}
		else
		{
			$moddata->set_info('usergroupid', $vbulletin->GPC['usergroupid']);
			$moddata->set_info('membergroupids', $vbulletin->GPC['membergroupids']);
		}

		$moddata->set('username', $name);
		$moddata->set('nodeid', $vbulletin->GPC['nodeid']);

		foreach ($vbulletin->GPC['modperms'] AS $key => $val)
		{
			if (isset($vbulletin->bf_misc_moderatorpermissions["$key"]))
			{
				$moddata->set_bitfield('permissions', $key, $val);
			}
			else if (isset($vbulletin->bf_misc_moderatorpermissions2["$key"]))
			{
				$moddata->set_bitfield('permissions2', $key, $val);
			}
		}

		$moddata->pre_save();
		$moddata_dms[] =& $moddata;
		$successnames[] = $name;
	}

	unset($moddata);

	$dm_errors = '';
	// we will only get here if every other DM succeeded
	foreach (array_keys($moddata_dms) AS $dmkey)
	{
		$moddata =& $moddata_dms["$dmkey"];

		// Legacy Hook 'admin_moderator_save' Removed //

		$moddata->save();
		if (!empty($moddata->errors))
		{
			$html = "<ul><li> " . $moddata->info['user']['username'] . "</li>";
			$html .= "<ul><li>" . implode($moddata->errors, "</li>\n<li>") . "</li></ul>";
			$html .="</ul>";
			$dm_errors .= $html;
		}
		else
		{
			log_admin_action('username = ' . $moddata->info['user']['username']);
		}
	}

	if (!empty($dm_errors))
	{
		$not_affected = array();
		// need to find the users not affected
		foreach ($moddata_dms AS $moddata)
		{
			if (empty($moddata->errors))
			{
				$not_affected[] = $moddata->info['user']['username'];
			}
		}

		print_form_header('', '', 0, 1, 'messageform', '65%');
		print_table_header($vbphrase['vbulletin_message']);
		print_description_row("$vbphrase[error_occurred_while_making_users_moderators]<blockquote>$dm_errors<br /></blockquote>");
		if (!empty($not_affected))
		{
			print_description_row("$vbphrase[the_following_users_were_made_moderators]<blockquote><ul><li>" . implode($not_affected, "</li>\n<li>") . "</li></ul></blockquote>");
		}
		print_table_footer();
		print_cp_footer();
		exit;
	}

	if ($vbulletin->GPC['nodeid'] == 0)
	{
		// Super Moderator
		$file = 'moderator';
		$args = array('do'=>'showlist');
	}
	else if (!empty($vbulletin->GPC['redir']))
	{
		$file = 'moderator';
		$args = array(
			'do' => $vbulletin->GPC['redir'] == 'showmods' ? 'showmods' : 'showlist',
			'n' => $vbulletin->GPC['nodeid']
		);
	}
	else
	{
		$file = 'forum';
		$args = array(
			'do' => 'modify',
			'n' => $vbulletin->GPC['nodeid'] . "#channel" . $vbulletin->GPC['nodeid']
		);

	}

	print_stop_message2(array('saved_moderator_x_successfully', implode('; ', $successnames)), $file, $args);

}

// ###################### Start Remove moderator #######################

if ($_REQUEST['do'] == 'remove')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'redir' => vB_Cleaner::TYPE_STR
	));

	$hidden = array('redir' => $vbulletin->GPC['redir']);

	print_delete_confirmation('moderator', $vbulletin->GPC['moderatorid'], 'moderator', 'kill', 'moderator', $hidden);
}

// ###################### Start Kill moderator #######################

if ($_POST['do'] == 'kill')
{
	$mod = $assertor->getRow('vBForum:getModeratorInfoToUpdate', array('moderatorid' => $vbulletin->GPC['moderatorid']));
	if (!$mod)
	{
		print_stop_message2('invalid_moderator_specified');
	}

	log_admin_action('username = ' . $mod['username'] . ', userid = ' . $mod['userid']);

	$moddata =& datamanager_init('Moderator', $vbulletin, vB_DataManager_Constants::ERRTYPE_CP);
	$moddata->set_existing($mod);
	$moddata->delete(true);

	$vbulletin->input->clean_array_gpc('p', array(
		'redir' => vB_Cleaner::TYPE_STR
	));

	if ($vbulletin->GPC['redir'] == 'modlist')
	{
		$file = 'moderator';
		$args = array('do'=>'showlist');
	}
	else if ($vbulletin->GPC['redir'] == 'showmods')
	{
		$file = 'moderator';
		$args = array(
			'do' => 'showmods',
			'n' => $mod['nodeid']
		);
	}
	else
	{
		$file = 'forum';
		$args = array();
	}
	print_stop_message2('deleted_moderator_successfully', $file, $args);
}

// ###################### Start Show moderator list per moderator #######################

if ($_REQUEST['do'] == 'showlist')
{
	print_form_header('', '');
	print_table_header($vbphrase['last_online'] . ' - ' . $vbphrase['color_key']);
	print_description_row('
		<div class="darkbg" style="border: 2px inset"><ul class="darkbg">
		<li class="modtoday">' . $vbphrase['today'] . '</li>
		<li class="modyesterday">' . $vbphrase['yesterday'] . '</li>
		<li class="modlasttendays">' . construct_phrase($vbphrase['within_the_last_x_days'], '10') . '</li>
		<li class="modsincetendays">' . construct_phrase($vbphrase['more_than_x_days_ago'], '10') . '</li>
		<li class="modsincethirtydays"> ' . construct_phrase($vbphrase['more_than_x_days_ago'], '30') . '</li>
		</ul></div>
	');
	print_table_footer();

	// get the timestamp for the beginning of today, according to bbuserinfo's timezone
	require_once(DIR . '/includes/functions_misc.php');
	$unixtoday = vbmktime(0, 0, 0, vbdate('m', TIMENOW, false, false), vbdate('d', TIMENOW, false, false), vbdate('Y', TIMENOW, false, false));

	print_form_header('', '');
	print_table_header($vbphrase['super_moderators']);
	echo "<tr valign=\"top\">\n\t<td class=\"" . fetch_row_bgclass() . "\" colspan=\"2\">";
	echo "<div class=\"darkbg\" style=\"padding: 4px; border: 2px inset; text-align: " . vB_Template_Runtime::fetchStyleVar('left') . "\"><ul>";

	$countmods = 0;
	$supergroups = $assertor->getRows('vBForum:getSuperGroups', array('ismodpermission' => $vbulletin->bf_ugp_adminpermissions['ismoderator']));
	if (count($supergroups))
	{
		foreach ($supergroups AS $supergroup)
		{
			$countmods++;
			if ($supergroup['lastactivity'] >= $unixtoday)
			{
				$onlinecolor = 'modtoday';
			}
			else if ($supergroup['lastactivity'] >= ($unixtoday - 86400))
			{
				$onlinecolor = 'modyesterday';
			}
			else if ($supergroup['lastactivity'] >= ($unixtoday - 864000))
			{
				$onlinecolor = 'modlasttendays';
			}
			else if ($supergroup['lastactivity'] >= ($unixtoday - 2592000))
			{
				$onlinecolor = 'modsincetendays';
			}
			else
			{
				$onlinecolor = 'modsincethirtydays';
			}

			$lastonline = vbdate($vbulletin->options['dateformat'] . ' ' .$vbulletin->options['timeformat'], $supergroup['lastactivity']);
			echo "\n\t<li><b><a href=\"user.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&u=$supergroup[userid]\">$supergroup[username]</a></b><span class=\"smallfont\"> (" . construct_link_code($vbphrase['edit_permissions'], "moderator.php?" . vB::getCurrentSession()->get('sessionurl') . "do=editglobal&amp;u=$supergroup[userid]") . ") - " . $vbphrase['last_online'] . " <span class=\"$onlinecolor\">" . $lastonline . "</span></span></li>\n";
		}
	}
	else
	{
		echo $vbphrase['there_are_no_moderators'];
	}
	echo "</ul></div>\n";
	echo "</td>\n</tr>\n";

	if ($countmods)
	{
		print_table_footer(1, $vbphrase['total'] . ": <b>$countmods</b>");
	}
	else
	{
		print_table_footer();
	}

	print_form_header('', '');
	print_table_header($vbphrase['moderators']);
	echo "<tr valign=\"top\">\n\t<td class=\"" . fetch_row_bgclass() . "\" colspan=\"2\">";
	echo "<div class=\"darkbg\" style=\"padding: 4px; border: 2px inset; text-align: " . vB_Template_Runtime::fetchStyleVar('left') . "\">";

	$countmods = 0;
	$moderators = $assertor->getRows('vBForum:getModeratorChannelInfo', array(
		'sortby' => array(
			'field' => array('user.username', 'node.htmltitle')
		)
	));
	if (count($moderators))
	{
		$curmod = -1;
		foreach ($moderators AS $moderator)
		{
			if ($curmod != $moderator['userid'])
			{
				$curmod = $moderator['userid'];
				if ($countmods++ != 0)
				{
					echo "\t\t</ul>\n\t\t</ul>\n\t</li>\n\t</ul>\n";
				}

				if ($moderator['lastactivity'] >= $unixtoday)
				{
					$onlinecolor = 'modtoday';
				}
				else if ($moderator['lastactivity'] >= ($unixtoday - 86400))
				{
					$onlinecolor = 'modyesterday';
				}
				else if ($moderator['lastactivity'] >= ($unixtoday - 864000))
				{
					$onlinecolor = 'modlasttendays';
				}
				else if ($moderator['lastactivity'] >= ($unixtoday - 2592000))
				{
					$onlinecolor = 'modsincetendays';
				}
				else
				{
					$onlinecolor = 'modsincethirtydays';
				}
				$lastonline = vbdate($vbulletin->options['dateformat'] . ' ' .$vbulletin->options['timeformat'], $moderator['lastactivity']);
				echo "\n\t<ul>\n\t<li><b><a href=\"user.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;u=$moderator[userid]&amp;redir=showlist\">$moderator[username]</a></b><span class=\"smallfont\"> - " . $vbphrase['last_online'] . " <span class=\"$onlinecolor\">" . $lastonline . "</span></span>\n";
				echo "\n\t\t<ul>$vbphrase[channels] <span class=\"smallfont\">(" . construct_link_code($vbphrase['remove_moderator_from_all_channels'], "moderator.php?" . vB::getCurrentSession()->get('sessionurl') . "do=removeall&amp;u=$moderator[userid]") . ")</span>\n\t<ul>\n";
			}
			echo "\t\t\t<li><a href=\"" . vB5_Route::buildUrl($moderator['routeid'].'|nosession|bburl') . "\" target=\"_blank\">$moderator[htmltitle]</a>\n".
				"\t\t\t\t<span class=\"smallfont\">(" . construct_link_code($vbphrase['edit'], "moderator.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&moderatorid=$moderator[moderatorid]&amp;redir=showlist").
				construct_link_code($vbphrase['remove'], "moderator.php?" . vB::getCurrentSession()->get('sessionurl') . "do=remove&moderatorid=$moderator[moderatorid]") . ")</span>\n".
				"\t\t\t</li><br />\n";
		}
		echo "\t\t</ul>\n\t\t</ul>\n\t</li>\n\t</ul>\n";
	}
	else
	{
		echo $vbphrase['there_are_no_moderators'];
	}
	echo "</div>\n";
	echo "</td>\n</tr>\n";

	if ($countmods)
	{
		print_table_footer(1, $vbphrase['total'] . ": <b>$countmods</b>");
	}
	else
	{
		print_table_footer();
	}
}

// ###################### Start Show moderator list per node #######################

if ($_REQUEST['do'] == 'showmods')
{
	$channels = $assertor->getRows('vBForum:getModeratorChannelInfo', array(
		'nodeid' => $vbulletin->GPC['nodeid'],
		'sortby' => array(
			array('field' => array('node.htmltitle', 'user.username'))
		)
	));

	if (!count($channels))
	{
		print_stop_message2('this_channel_does_not_have_any_moderators',NULL, array(),'');
	}

	print_form_header('', '');
	print_table_header($vbphrase['last_online'] . ' - ' . $vbphrase['color_key']);
	print_description_row('
		<div class="darkbg" style="border: 2px inset"><ul class="darkbg">
		<li class="modtoday">' . $vbphrase['today'] . '</li>
		<li class="modyesterday">' . $vbphrase['yesterday'] . '</li>
		<li class="modlasttendays">' . construct_phrase($vbphrase['within_the_last_x_days'], '10') . '</li>
		<li class="modsincetendays">' . construct_phrase($vbphrase['more_than_x_days_ago'], '10') . '</li>
		<li class="modsincethirtydays"> ' . construct_phrase($vbphrase['more_than_x_days_ago'], '30') . '</li>
		</ul></div>
	');
	print_table_footer();

	print_form_header('', '');
	print_table_header($vbphrase['moderators']);
	echo "<tr valign=\"top\">\n\t<td class=\"" . fetch_row_bgclass() . "\" colspan=\"2\">";
	echo "<div class=\"darkbg\" style=\"padding: 4px; border: 2px inset; text-align: " . vB_Template_Runtime::fetchStyleVar('left') . "\">";

	// get the timestamp for the beginning of today, according to bbuserinfo's timezone
	require_once(DIR . '/includes/functions_misc.php');
	$unixtoday = vbmktime(0, 0, 0, vbdate('m', TIMENOW, false, false), vbdate('d', TIMENOW, false, false), vbdate('Y', TIMENOW, false, false));

	$list = $modlist = array();
	$curnode = $countnodes = -1;
	if (count($channels))
	{
		foreach ($channels AS $channel)
		{
			$modlist["$channel[userid]"]++;

			if ($curnode != $channel['nodeid'])
			{
				$curnode = $channel['nodeid'];
				if ($countnodes++ != 0)
				{
					echo "\t\t</ul>\n\t\t</ul>\n\t</li>\n\t</ul>\n";
				}

				// @TODO handle channel url properly here... temp setting forum
				echo "\n\t<ul>\n\t<li><b><a href=\"" . fetch_seo_url('forum|nosession|bburl', $channel) . "\">$channel[htmltitle]</a></b>\n";
				echo "\n\t\t<ul>$vbphrase[moderators]\n\t<ul>\n";
			}

			if ($channel['lastactivity'] >= $unixtoday)
			{
				$onlinecolor = 'modtoday';
			}
			else if ($channel['lastactivity'] >= ($unixtoday - 86400))
			{
				$onlinecolor = 'modyesterday';
			}
			else if ($channel['lastactivity'] >= ($unixtoday - 864000))
			{
				$onlinecolor = 'modlasttendays';
			}
			else if ($channel['lastactivity'] >= ($unixtoday - 2592000))
			{
				$onlinecolor = 'modsincetendays';
			}
			else
			{
				$onlinecolor = 'modsincethirtydays';
			}

			$lastonline = vbdate($vbulletin->options['dateformat'] . ' ' . $vbulletin->options['timeformat'], $channel['lastactivity']);

			echo "\t\t\t<li><a href=\"user.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;u=$channel[userid]\" target=\"_blank\">$channel[username]</a>" .
				"\t\t\t\t<span class=\"smallfont\">(" . construct_link_code($vbphrase['edit'], "moderator.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&moderatorid=$channel[moderatorid]&amp;redir=showmods") .
				construct_link_code($vbphrase['remove'], "moderator.php?" . vB::getCurrentSession()->get('sessionurl') . "do=remove&moderatorid=$channel[moderatorid]&redir=showmods") . ")" .
				" - " . $vbphrase['last_online'] . " <span class=\"$onlinecolor\">" . $lastonline . "</span></span>\n" .
				"\t\t\t</li><br />\n";
		}
		echo "\t\t</ul>\n\t\t</ul>\n\t</li>\n\t</ul>\n";
	}
	else
	{
		echo $vbphrase['there_are_no_moderators'];
	}
	echo "</div>\n";
	echo "</td>\n</tr>\n";

	if (!empty($modlist))
	{
		print_table_footer(1, $vbphrase['total'] . ": <b>" . count($modlist) . "</b>");
	}
	else
	{
		print_table_footer();
	}
}

// ###################### Start Remove moderator from all channels #######################

if ($_REQUEST['do'] == 'removeall')
{
	$modinfo = $assertor->getRow('vBForum:checkUserMod', array('userid' => $vbulletin->GPC['userid']));
	if (!$modinfo)
	{
		print_stop_message2('user_no_longer_moderator');
	}

	print_form_header('moderator', 'killall', 0, 1, '', '75%');
	construct_hidden_code('userid', $vbulletin->GPC['userid']);
	print_table_header($vbphrase['confirm_deletion_gcpglobal']);
	print_description_row('<blockquote><br />' . $vbphrase['are_you_sure_you_want_to_delete_this_moderator'] . "<br /></blockquote>\n\t");
	print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);
}

// ###################### Start Kill moderator from all channels #######################

if ($_POST['do'] == 'killall')
{
	if (empty($vbulletin->GPC['userid']))
	{
		print_stop_message2('invalid_users_specified');
	}

	$getuserid = $assertor->getRow('vBForum:getModUserInfoKillAll', array('userid' => $vbulletin->GPC['userid']));
	if (!$getuserid)
	{
		print_stop_message2('user_no_longer_moderator');
	}
	else
	{
		// Legacy Hook 'admin_moderator_killall' Removed //

		$assertor->delete('vBForum:moderator', array('userid' => $vbulletin->GPC['userid']));
		// if the user is in the moderators usergroup, then move them to registered users usergroup
		if ($getuserid['usergroupid'] == 7)
		{
			if (!$getuserid['customtitle'])
			{
				if (!$vbulletin->usergroupcache["2"]['usertitle'])
				{
					$gettitle = $assertor->getRow('usertitle', array(
						vB_dB_Query::CONDITIONS_KEY => array(
							array('field' => 'minposts', 'value' => $getuserid[posts], vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_LTE),
						)),
						array('field' => array('minposts'), 'direction' => array(vB_dB_Query::SORT_DESC))
					);
					$usertitle = $gettitle['title'];
				}
				else
				{
					$usertitle = $vbulletin->usergroupcache["2"]['usertitle'];
				}
			}
			else
			{
				$usertitle = $getuserid['usertitle'];
			}

			$userdm = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
			$userdm->set_existing($getuserid);
			$userdm->set('usergroupid', 2);

			$getuserid['usergroupid'] = 2;
			if ($getuserid['displaygroupid'] == 7)
			{
				$userdm->set('displaygroupid', 2);
				$getuserid['displaygroupid'] = 2;
			}
			$userdm->set('usertitle', $usertitle);

			$userdm->save();
			unset($userdm);
		}

		print_stop_message2('deleted_moderators_successfully', 'moderator', array('do' => 'showlist'));
	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 71514 $
|| ####################################################################
\*======================================================================*/
?>
