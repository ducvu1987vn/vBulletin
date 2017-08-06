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
define('CVS_REVISION', '$RCSfile$ - $Revision: 69791 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates,$vbphrase, $vbulletin;
$phrasegroups = array('cppermission', 'accessmask');
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
	'nodeid' 		=> vB_Cleaner::TYPE_INT,
	'accessmask' 	=> vB_Cleaner::TYPE_INT
));

log_admin_action(iif($vbulletin->GPC['nodeid'] != 0, "node id = ".$vbulletin->GPC['nodeid'] . iif($vbulletin->GPC['accessmask'] != 0, " / accessmask = ".$vbulletin->GPC['accessmask'])));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['access_mask_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start Edit Access #######################

if ($_REQUEST['do'] == 'edit')
{
	$channel = vB_Api::instanceInternal('node')->getNode($vbulletin->GPC['nodeid']);
	if (empty($channel))
	{
		print_stop_message2('invalid_channel_specified');
	}

	print_form_header('accessmask', 'update');
	construct_hidden_code('nodeid', $vbulletin->GPC['nodeid']);

	print_table_header(construct_phrase($vbphrase['user_channel_access_for_x'], '<span class="normal">' . $channel['htmltitle'] . '</span>'), 2, 0);
	print_description_row($vbphrase['here_you_may_edit_channel_access_on_a_user_by_user_basis']);

	print_table_header($vbphrase['users']);

	$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD, 'nodeid' => $vbulletin->GPC['nodeid']);
	if ($vbulletin->GPC['accessmask'] != 2)
	{
		$params['accessmask'] = $vbulletin->GPC['accessmask'];
	}

	$query = vB::getDbAssertor()->assertQuery('fetchAccessMasksForChannel', $params);
	while ($query AND $query->valid())
	{
		$access = $query->current();
		if ($access['accessmask'] == 0)
		{
			$sel = 0;
		}
		else if ($access['accessmask'] == 1)
		{
			$sel = 1;
		}
		else
		{
			$sel = -1;
		}
		construct_hidden_code('oldcache[' . $access['userid'] . ']', $sel);
		$radioname = 'accessupdate[' . $access['userid'] . ']';
		print_label_row($access['username'], "
			<label for=\"rb_1_$radioname\"><input type=\"radio\" name=\"$radioname\" value=\"1\" id=\"rb_1_$radioname\" tabindex=\"1\"" . iif($sel==1," checked=\"checked\"","")." />" . $vbphrase['yes'] . "</label>
			<label for=\"rb_0_$radioname\"><input type=\"radio\" name=\"$radioname\" value=\"0\" id=\"rb_0_$radioname\" tabindex=\"1\"" . iif($sel==0," checked=\"checked\"","")." />" . $vbphrase['no'] . "</label>
			<label for=\"rb_x_$radioname\"><input type=\"radio\" name=\"$radioname\" value=\"-1\" id=\"rb_x_$radioname\" tabindex=\"1\"" . iif($sel==-1," checked=\"checked\"","")." />" . $vbphrase['default'] . "</label>
		");
		$query->next();
	}

	print_submit_row();
}

// ###################### Start Update Access #######################

if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'oldcache'		=> vB_Cleaner::TYPE_ARRAY_INT,
		'accessupdate'	=> vB_Cleaner::TYPE_ARRAY_INT,
		'nodeid' 		=> vB_Cleaner::TYPE_INT
	));

	if (!is_array($vbulletin->GPC['oldcache']) OR !is_array($vbulletin->GPC['accessupdate']))
	{
		print_stop_message2('nothing_to_do');
	}
	$userlist = array();
	foreach ($vbulletin->GPC['accessupdate'] AS $userid => $val)
	{
		// build 3 arrays, one of users to have access masks added
		// one to have theres deleted
		// those to have it changed

		$userid = intval($userid);
		$userlist['userids']["$userid"] = $userid;
		// $val already intval'd above

		if ($vbulletin->GPC['oldcache']["$userid"] == $val)
		{
			continue;
		}

		$noperms = array();
		if ($vbulletin->GPC['oldcache']["$userid"] != '-1' AND $val == '-1')
		{ // remove access mask
			$query = vB::getDbAssertor()->assertQuery('accessUserCount', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'userid' => $userid));
			if ($query AND $query->valid())
			{
				$countaccess = $query->current();
			}
			// we're removing a channel so remove it from the total
			$countaccess['masks']--;

			if ($countaccess['masks'] == 0)
			{
				$maskdelete[] = $userid;
			}

			$removemask[] = $userid;
		}
		else
		{ // add access mask or updating it
			$updateuserids[] = $userid;
			$newmask[] = array('userid' => $userid, 'nodeid' => $vbulletin->GPC['nodeid'] , 'accessmask' => $val);
			if ($val == 0)
			{
				$noperms[] = $userid;
			}
		}
	}

	if (!empty($removemask))
	{
		vB::getDbAssertor()->delete('access', array('userid' => $removemask, 'nodeid' => $vbulletin->GPC['nodeid']));
	}

	if (!empty($maskdelete))
	{
		vB::getDbAssertor()->assertQuery('maskDelete', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'maskdelete' => implode(',', $maskdelete), 'hasaccessmask' => $vbulletin->bf_misc_useroptions['hasaccessmask']));
	}

	if (!empty($newmask))
	{
		/*insert query*/
		vB::getDbAssertor()->assertQuery('newAccessMask', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD, 'newmask' => $newmask));

		vB::getDbAssertor()->assertQuery('maskAdd', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'updateuserids' => implode(',', $updateuserids), 'hasaccessmask' => $vbulletin->bf_misc_useroptions['hasaccessmask']));

		foreach($noperms AS $userid)
		{
			$user = vB_Api::instanceInternal('user')->fetchUserinfo($userid);
			if (!$user)
			{
				print_stop_message2('invalid_user_specified');
			}
			cache_permissions($user);
			$noforums = array();
			foreach ($user['forumpermissions'] AS $nodeid => $perm)
			{
				if ($perm == 0)
				{
					$noforums[] = $nodeid;
				}
			}
			if (!empty($noforums))
			{
				/** @todo need to rewrite this query when subscriptions are implemented */
//				$vbulletin->db->query_write("
//					DELETE FROM " . TABLE_PREFIX . "subscribeforum
//					WHERE userid = $userid AND
//						nodeid IN(" . implode(',', $noforums) . ")
//				");
			}
		}
	}

	if (!empty($userlist))
	{
		require_once(DIR . '/includes/functions_databuild.php');
		update_subscriptions($userlist);
	}

	print_stop_message2('saved_user_forum_access_successfully', 'accessmask', array('do'=>'modify'));
}

// ###################### Start quick edit #######################
if ($_REQUEST['do'] == 'quickedit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'orderby' 	=> vB_Cleaner::TYPE_STR
	));

	print_form_header('accessmask', 'doquickedit');
	print_table_header($vbphrase['access_masks_quick_editor'], 4);
	print_cells_row(array(
		"<a href=\"accessmask.php?" . vB::getCurrentSession()->get('sessionurl') . "do=quickedit&amp;orderby=user\" title=\"" . $vbphrase['sort'] . "\">" . $vbphrase['username'] . "</a>",
		"<a href=\"accessmask.php?" . vB::getCurrentSession()->get('sessionurl') . "do=quickedit&amp;orderby=channel\" title=\"" . $vbphrase['sort'] . "\">" . $vbphrase['channel'] . "</a>",
		'<input type="button" value="' . $vbphrase['all_yes'] . '" onclick="js_check_all_option(this.form, 1);" class="button" />
		<input type="button" value=" ' . $vbphrase['all_no'] . ' " onclick="js_check_all_option(this.form, 0);" class="button" />
		<input type="button" value="' . $vbphrase['all_default'] .'" onclick="js_check_all_option(this.form, -1);" class="button" />'), 0, 'thead', 0, 'middle');

	$result = vB::getDbAssertor()->assertQuery('fetchUserAccessMask', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'order_first' => $vbulletin->GPC['orderby'] == 'channel' ? 'channel' : 'node'));
	if($result AND $result->valid())
	{
		while($result->valid())
		{
			$access = $result->current();
			if ($access['accessmask'] == 0)
			{
				$sel = 0;
			}
			else if ($access['accessmask'] == 1)
			{
				$sel = 1;
			}
			else
			{
				$sel = -1;
			}
			construct_hidden_code('oldcache[' . $access['userid'] . '][' . $access['nodeid'] . ']', $sel);
			$radioname = 'accessupdate[' . $access['userid'] . '][' . $access['nodeid'] . ']';

			print_cells_row(array(
				"<a href=\"user.php?" . vB::getCurrentSession()->get('sessionurl') . "do=editaccess&u=$access[userid]\">$access[username]</a>",
				"<a href=\"accessmask.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;n=$access[nodeid]&amp;accessmask=2\">$access[node_title]</a>",
				"
					<label for=\"rb_1_$radioname\"><input type=\"radio\" name=\"$radioname\" value=\"1\" id=\"rb_1_$radioname\" tabindex=\"1\"" . iif($sel==1," checked=\"checked\"","")." />" . $vbphrase['yes'] . "</label>
					<label for=\"rb_0_$radioname\"><input type=\"radio\" name=\"$radioname\" value=\"0\" id=\"rb_0_$radioname\" tabindex=\"1\"" . iif($sel==0," checked=\"checked\"","")." />" . $vbphrase['no'] . "</label>
					<label for=\"rb_x_$radioname\"><input type=\"radio\" name=\"$radioname\" value=\"-1\"  id=\"rb_x_$radioname\"tabindex=\"1\"" . iif($sel==-1," checked=\"checked\"","")." />" . $vbphrase['default'] . "</label>
				"));
			$result->next();
		}
		print_submit_row($vbphrase['update_selected_permissions'], $vbphrase['reset'], 4);
	}

	else
	{
		print_description_row($vbphrase['nothing_to_do'], 0, 4, '', 'center');
		print_table_footer();
	}
}

// ###################### Start do quick edit #######################
if ($_POST['do'] == 'doquickedit')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'oldcache'		=> vB_Cleaner::TYPE_ARRAY,
		'accessupdate'	=> vB_Cleaner::TYPE_ARRAY
	));

	if (!is_array($vbulletin->GPC['oldcache']) OR !is_array($vbulletin->GPC['accessupdate']))
	{
		print_stop_message2('nothing_to_do');
	}

	$oldcache =& $vbulletin->GPC['oldcache'];
	$userlist = array();

	foreach($vbulletin->GPC['accessupdate'] AS $userid => $accessforums)
	{
		$userid = intval($userid);
		$userlist['userids'][] = $userid;

		foreach($accessforums AS $nodeid => $val)
		{
			$nodeid = intval($nodeid);
			$val = intval($val);

			if ($oldcache["$userid"]["$nodeid"] == $val)
			{
				continue;
			}

			if ($oldcache["$userid"]["$nodeid"] == '-1' OR $oldcache["$userid"]["$nodeid"] === null)
			{
				/*insert query*/
				vB::getDbAssertor()->assertQuery('insertAccess', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'userid' => $userid, 'nodeid' => $nodeid, 'accessmask' => $val));
			}
			else if ($oldcache["$userid"]["$nodeid"] != '-1' AND $val == '-1')
			{
				vB::getDbAssertor()->delete('access', array('nodeid' => $nodeid, 'userid' => $userid));
			}
			else
			{
				vB::getDbAssertor()->update('access', array(accessmask => $val), array('nodeid' => $nodeid, 'userid' => $userid));
			}
		}

		$userinfo = array('userid' => $userid, 'masks' => 0);
		$query = vB::getDbAssertor()->assertQuery('fetchAccessMaskForUser', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'userid' => $userid));
		if ($query AND $query->valid())
		{
			$userinfo = $query->current();
		}

		$userdm = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_CP);
		$userdm->set_existing($userinfo);
		$userdm->set_bitfield('options', 'hasaccessmask', ($userinfo['masks'] ? true : false));
		$userdm->save();
		unset($userdm);
	}
/** @todo update this when subscriptions are implemented */
//	if (!empty($userlist))
//	{
//		require_once(DIR . '/includes/functions_databuild.php');
//		update_subscriptions($userlist);
//	}

	print_stop_message2('saved_user_channel_access_successfully', 'accessmask', array('do'=>'modify'));

}

// ###################### Start reset all access masks for forum #######################
if ($_REQUEST['do'] == 'resetchannel')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'nodeid' 	=> vB_Cleaner::TYPE_INT
	));

	verify_cp_sessionhash();

	if (!$vbulletin->GPC['nodeid'])
	{
		print_stop_message2('invalid_channel_specified');
	}

	vB::getDbAssertor()->assertQuery('access', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'nodeid' => $vbulletin->GPC['nodeid']));

	print_stop_message2('deleted_access_masks_successfully', 'accessmask', array('do'=>'modify'));
}

// ###################### Start Delete All Access Masks #######################
if ($_REQUEST['do'] == 'resetall')
{
	print_form_header('accessmask', 'doresetall');
	print_table_header($vbphrase['confirm_deletion_gcpglobal']);
	print_description_row($vbphrase['delete_all_access_masks']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
}

// ###################### Process Delete All Access Masks #######################

if ($_POST['do'] == 'doresetall')
{
	vB::getDbAssertor()->assertQuery('truncateTable', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'table' => 'access'));

	print_stop_message2('saved_user_channel_access_successfully','accessmask',array('do'=>'modify'));
}

// ###################### Start displaychannels #######################
function print_channels($parentid = 0, $indent = "	")
{
	global $vbulletin, $imodcache, $accesscache, $vbphrase;
	// check to see if we need to do the queries
	if (!is_array($imodcache))
	{
		require_once(DIR . '/includes/functions_forumlist.php');
		cache_moderators();
	}
	if ($parentid === 0)
	{
		$channels = vB_Api::instanceInternal('search')->getChannels(false);
	}
	else
	{
		$channels = vB_Api::instanceInternal('search')->getChannels(true);
		if (empty($channels["$parentid"]) OR empty($channels["$parentid"]['channels']))
		{
			return;
		}
		$channels = $channels["$parentid"]['channels'];
	}
	// check to see if this channel actually exists / has children
	foreach ($channels AS $nodeid => $node)
	{
		echo "$indent<ul class=\"lsq\">\n";
		// node title and links
		echo "$indent<li><b><a name=\"node$nodeid\" href=\"forum.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;n=$nodeid\">$node[htmltitle]</a> <span class=\"smallfont\">(" . construct_link_code($vbphrase['reset'], "accessmask.php?" . vB::getCurrentSession()->get('sessionurl') . "do=resetchannel&amp;n=$nodeid&amp;hash=" . CP_SESSIONHASH) . ")</span></b>";

		// get moderators
		if (is_array($imodcache["$nodeid"]))
		{
			echo "<span class=\"smallfont\"><br /> - <i>".$vbphrase['moderators'].":";
			foreach($imodcache["$nodeid"] AS $moderator)
			{
				// moderator username and links
				echo " <a href=\"moderator.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;moderatorid=$moderator[moderatorid]\">$moderator[username]</a>";
			}
			echo "</i></span>";
		}

		$allaccessmasks = 0;
		$forbidden = '';
		$permitted = '';
		$deny = $accesscache["$nodeid"]['0'];
		$permit = $accesscache["$nodeid"]['1'];

		/*echo "<pre>--$nodeid--\n";
		print_r($accesscache["$nodeid"]);
		echo '</pre>';*/

		if (is_array($deny))
		{
			$forbidden = "$indent\t<li class=\"am-deny\"><b>" . construct_phrase($vbphrase['access_denied_x_users'], $deny['count']) . '</b>' . construct_link_code($vbphrase['display_users'], "accessmask.php?" . vB::getCurrentSession()->get('sessionurl') . "&do=edit&n=$nodeid&accessmask=$deny[accessmask]") . "</li>\n";
			$allaccessmasks = $deny['count'];
		}

		if (is_array($permit))
		{
			$permitted = "$indent\t<li class=\"am-grant\"><b>" . construct_phrase($vbphrase['access_granted_x_users'], $permit['count']) . '</b>' . construct_link_code($vbphrase['display_users'], "accessmask.php?" . vB::getCurrentSession()->get('sessionurl') . "&do=edit&n=$nodeid&accessmask=$permit[accessmask]") . "</li>\n";
			$allaccessmasks = $allaccessmasks + $permit['count'];
		}

		if ($allaccessmasks > 0)
		{
			echo "$indent\t<ul class=\"usergroups\">\n";
			echo "$indent\t<li>" . construct_phrase($vbphrase['x_access_masks_set'], $allaccessmasks) . ' ' . construct_link_code('<b>' . $vbphrase['display_all_users'] . '</b>', "accessmask.php?" . vB::getCurrentSession()->get('sessionurl') . "&do=edit&n=$nodeid&accessmask=2")."</li>";
			echo $permitted;
			echo $forbidden;
			echo "$indent\t</ul><br />\n";
		}
		else
		{
			echo "$indent\t\n";
			echo "$indent\t<br />\n";
		}

		print_channels($nodeid, "$indent	");
		echo "$indent</li>\n";
		echo "$indent</ul>\n";

		if ($node['parentid'] == 0)
		{
			echo "<hr size=\"1\" />\n";
		}
	}
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{
	print_form_header('', '');
	print_table_header($vbphrase['additional_functions_gcppermission']);
	print_description_row("<b><a href=\"accessmask.php?" . vB::getCurrentSession()->get('sessionurl') . "do=resetall\">" . $vbphrase['delete_all_access_masks'] . "</a> | <a href=\"accessmask.php?" . vB::getCurrentSession()->get('sessionurl') . "do=quickedit\">" . $vbphrase['access_masks_quick_editor'] . "</a></b>", 0, 2, '', 'center');
	print_table_footer();

	print_form_header('', '');
	print_table_header($vbphrase['access_masks']);
	print_description_row('
		<div class="darkbg" style="border: 2px inset"><ul class="darkbg">
		<li><b>' . $vbphrase['color_key'] . '</b></li>
		<li class="am-grant">' . $vbphrase['access_granted'] . '</li>
		<li class="am-deny">' . $vbphrase['access_denied'] . '</li>
		</ul></div>
	');
	print_table_footer();

	// query access masks
	$query = vB::getDbAssertor()->assertQuery('accesscount', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));
	global $accesscache;
	$accesscache = array();
	while ($query AND $query->valid())
	{
		$amask = $query->current();
		$accesscache["$amask[nodeid]"]["$amask[accessmask]"] = $amask;
		$query->next();
	}
	echo "<center>\n";
	echo "<div class=\"tborder\" style=\"width: 100%\">\n";
	echo "<div class=\"alt1\" style=\"padding: 8px\">\n";
	echo "<div class=\"darkbg\" style=\"padding: 4px; border: 2px inset; text-align: " . vB_Template_Runtime::fetchStyleVar('left') . "\">\n";

	// run the display function
	print_channels();

	echo "</div></div></div>\n</center>\n";

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 69791 $
|| ####################################################################
\*======================================================================*/
?>
