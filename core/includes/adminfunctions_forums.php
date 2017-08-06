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

error_reporting(E_ALL & ~E_NOTICE);

// ###################### Start getforumpermissions #######################
// queries forumpermissions for a single forum and either returns the forumpermissions,
// or the usergroup default.
function fetch_forum_permissions($usergroupid, $forumid)
{
	global $vbulletin;

	// assign the permissions to the usergroup defaults
	$perms = $vbulletin->usergroupcache["$usergroupid"]['forumpermissions'];
	DEVDEBUG("FPerms: Usergroup Defaults: $perms");

	// get the parent list of the forum we are interested in, excluding -1
	$parentlist = substr($vbulletin->forumcache["$forumid"]['parentlist'], 0, -3);

	// query forum permissions for the forums in the parent list of the current one
	$fperms = $vbulletin->db->query_read("
		SELECT forumid, forumpermissions
		FROM " . TABLE_PREFIX . "forumpermission
		WHERE usergroupid = $usergroupid
		AND forumid IN($parentlist)
	");
	// no custom permissions found, return usergroup defaults
	if ($vbulletin->db->num_rows($fperms) == 0)
	{
		return array('forumpermissions' => $perms);
	}
	else
	{
		// assign custom permissions to forums
		$fp = array();
		while ($fperm = $vbulletin->db->fetch_array($fperms))
		{
			$fp["$fperm[forumid]"] = $fperm['forumpermissions'];
		}
		unset($fperm);
		$vbulletin->db->free_result($fperms);

		// run through each forum in the forum's parent list in order
		foreach(array_reverse(explode(',', $parentlist)) AS $parentid)
		{
			// if the current parent forum has a custom permission, use it
			if (isset($fp["$parentid"]))
			{
				$perms = $fp["$parentid"];
				DEVDEBUG("FPerms: Custom - forum '" . $vbulletin->forumcache["$parentid"]['title'] . "': $perms");
			}
		}

		// return the permissions, whatever they may be now.
		return array('forumpermissions' => $perms);
	}
}

// ###################### Start makechildlist ########################
function construct_child_list($forumid)
{
	global $vbulletin;

	if ($forumid == -1)
	{
		return '-1';
	}

	$childlist = $forumid;

	$children = $vbulletin->db->query_read("
		SELECT forumid
		FROM " . TABLE_PREFIX . "forum
		WHERE parentlist LIKE '%,$forumid,%'
	");
	while ($child = $vbulletin->db->fetch_array($children))
	{
		$childlist .= ',' . $child['forumid'];
	}

	$childlist .= ',-1';

	return $childlist;

}

// ###################### Start updatechildlists #######################
function build_forum_child_lists($forumid = -1)
{
	global $vbulletin;

	$forums = $vbulletin->db->query_read("SELECT * FROM " . TABLE_PREFIX . "forum WHERE FIND_IN_SET('$forumid', childlist)");
	while ($forum = $vbulletin->db->fetch_array($forums))
	{
		$childlist = construct_child_list($forum['forumid']);

		$forumdm =& datamanager_init('Forum', $vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
		$forumdm->set_existing($forum);
		$forumdm->setr('childlist', $childlist);
		$forumdm->save();
		unset($forumdm);
	}
}

// ###################### Start makeparentlist #######################
function fetch_forum_parentlist($forumid)
{
	global $vbulletin;

	if ($forumid == -1)
	{
		return '-1';
	}

	$foruminfo = $vbulletin->db->query_first("SELECT parentid FROM " . TABLE_PREFIX . "forum WHERE forumid = $forumid");

	$forumarray = $forumid;

	if ($foruminfo['parentid'] != 0)
	{
		$forumarray .= ',' . fetch_forum_parent_list($foruminfo['parentid']);
	}

	if (substr($forumarray, -2) != -1)
	{
		$forumarray .= '-1';
	}

	return $forumarray;
}

// ###################### Start updateparentlists #######################
function build_forum_parentlists($forumid = -1)
{
	global $vbulletin;

	$forums = $vbulletin->db->query_read("
		SELECT *, (CHAR_LENGTH(parentlist) - CHAR_LENGTH(REPLACE(parentlist, ',', ''))) AS parents
		FROM " . TABLE_PREFIX . "forum
		WHERE FIND_IN_SET('$forumid', parentlist)
		ORDER BY parents ASC
	");
	while($forum = $vbulletin->db->fetch_array($forums))
	{
		$parentlist = fetch_forum_parentlist($forum['forumid']);

		$forumdm =& datamanager_init('Forum', $vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
		$forumdm->set_existing($forum);
		$forumdm->setr('parentlist', $parentlist);
		$forumdm->save();
		unset($forumdm);
	}
}
// ###################### Start permboxes #######################
function print_channel_permission_rows($customword, $channelpermission = array(), $extra = '')
{
	global $vbphrase;

	print_label_row(
		"<b>$customword</b>",'
		<input type="button" class="button" value="' . $vbphrase['all_yes'] . '" onclick="' . iif($extra != '', 'if (js_set_custom()) { ') . ' js_check_all_option(this.form, 1);' . iif($extra != '', ' }') . '" class="button" />
		<input type="button" class="button" value=" ' . $vbphrase['all_no'] . ' " onclick="' . iif($extra != '', 'if (js_set_custom()) { ') . ' js_check_all_option(this.form, 0);' . iif($extra != '', ' }') . '" class="button" />
		<!--<input type="submit" class="button" value="Okay" class="button" />-->
	', 'tcat', 'middle');

	// Load permissions
	require_once(DIR . '/includes/class_bitfield_builder.php');

	$bitvalues = array('forumpermissions', 'forumpermissions2', 'moderatorpermissions', 'createpermissions');
	$permFields = vB_ChannelPermission::fetchPermFields();
	$permPhrases = vB_ChannelPermission::fetchPermPhrases();

	if (empty($channelpermission))
	{
		// we need the defaults to be displayed
		$channelpermission = vB_ChannelPermission::instance()->fetchPermissions(1);
		$channelpermission = current($channelpermission);
	}



	foreach($permFields AS $permField => $type)
	{

		//Do the non-bitmap fields first.
		switch ($type)
		{
			case vB_ChannelPermission::TYPE_HOURS :
			case vB_ChannelPermission::TYPE_COUNT :
				$permvalue = $channelpermission[$permField];
				print_input_row($vbphrase[$permPhrases[$permField]], $permField, $permvalue, true, 35, 0, '', false, 'channelPerm_' . $permField);
				break;

			case vB_ChannelPermission::TYPE_BOOL :
				$permvalue = &$channelpermission[$permField];
				print_yes_no_row($vbphrase[$permPhrases[$permField]], $permField, $permvalue, $extra);
				break;
		}

	}

	//now do the bitmaps
	foreach($permFields AS $permField => $type)
	{
		if ($type == vB_ChannelPermission::TYPE_BITMAP)
		{
			if ($permField !== 'forumpermissions2')
			{
				print_table_header($vbphrase[$permPhrases[$permField]]);
			}
			foreach ($channelpermission['bitfields'][$permField] AS $permBit )
			{
				if ($permBit['used'])
				{
					if (empty($permBit['phrase']) AND ($permField == 'moderatorpermissions'))
					{
						$permBit['phrase'] = "moderator_add_edit_" . $permBit['name'] . "_title";
					}
					print_yes_no_row((isset($vbphrase[$permBit['phrase']]) ? $vbphrase[$permBit['phrase']] : $permBit['phrase']), $permField . '[' . $permBit['name'] . ']', $permBit['set'], $extra);
				}
			}

		}

	}

	// Legacy Hook 'admin_nperms_form' Removed //
}
// ###################### Start permboxes #######################
function print_forum_permission_rows($customword, $forumpermission = array(), $extra = '')
{
	global $vbphrase;

	print_label_row(
		"<b>$customword</b>",'
		<input type="button" class="button" value="' . $vbphrase['all_yes'] . '" onclick="' . iif($extra != '', 'if (js_set_custom()) { ') . ' js_check_all_option(this.form, 1);' . iif($extra != '', ' }') . '" class="button" />
		<input type="button" class="button" value=" ' . $vbphrase['all_no'] . ' " onclick="' . iif($extra != '', 'if (js_set_custom()) { ') . ' js_check_all_option(this.form, 0);' . iif($extra != '', ' }') . '" class="button" />
		<!--<input type="submit" class="button" value="Okay" class="button" />-->
	', 'tcat', 'middle');

	// Load permissions
	require_once(DIR . '/includes/class_bitfield_builder.php');

	$groupinfo = vB_Bitfield_Builder::fetch_permission_group('forumpermissions');

	foreach($groupinfo AS $grouptitle => $group)
	{
		print_table_header($vbphrase["$grouptitle"]);

		foreach ($group AS $permtitle => $permvalue)
		{
			print_yes_no_row($vbphrase["{$permvalue['phrase']}"], "forumpermission[$permtitle]", $forumpermission["$permtitle"], $extra);
		}

		//print_table_break();
		//print_column_style_code(array('width: 70%', 'width: 30%'));
	}

	// Legacy Hook 'admin_fperms_form' Removed //
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 71168 $
|| ####################################################################
\*======================================================================*/
?>