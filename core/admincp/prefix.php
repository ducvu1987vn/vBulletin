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
define('CVS_REVISION', '$RCSfile$ - $Revision: 68365 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbphrase, $vbulletin;
$phrasegroups = array('prefix', 'prefixadmin');

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/adminfunctions_prefix.php');
require_once(DIR . '/includes/functions_prefix.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminforums'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
$log_vars = array();
if (!empty($_REQUEST['prefixsetid']))
{
	$log_vars[] = 'prefixsetid = ' . htmlspecialchars_uni($_REQUEST['prefixsetid']);
}
if (!empty($_REQUEST['prefixid']))
{
	$log_vars[] = 'prefixid = ' . htmlspecialchars_uni($_REQUEST['prefixid']);
}
log_admin_action(implode(', ', $log_vars));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['thread_prefix_manager_gprefixadmin']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'list';
}

// notes on phrases:
// prefixset_ID_title (prefixes), prefix_ID_title_plain (global), prefix_ID_title_rich (global)

// ########################################################################

if ($_REQUEST['do'] == 'duplicate')
{
	$prefixes = array();

	$prefixes_result = $vbulletin->db->query_read("
		SELECT prefix.prefixid, prefixset.prefixsetid
		FROM " . TABLE_PREFIX . "prefix AS prefix
		INNER JOIN " . TABLE_PREFIX . "prefixset AS prefixset ON (prefix.prefixsetid = prefixset.prefixsetid)
		ORDER BY prefixset.displayorder ASC, prefix.displayorder ASC
	");

	while ($prefix = $vbulletin->db->fetch_array($prefixes_result))
	{
		$prefixsetphrase = htmlspecialchars_uni($vbphrase["prefixset_{$prefix[prefixsetid]}_title"]);

		$prefixes["$prefixsetphrase"]["$prefix[prefixid]"] = htmlspecialchars_uni($vbphrase["prefix_{$prefix[prefixid]}_title_plain"]);
	}

	if (empty($prefixes))
	{
		print_cp_message($vbphrase['no_prefix_sets_defined_click_create']);
	}

	print_form_header('prefix', 'doduplicate');
	print_table_header($vbphrase['thread_prefixes_gprefixadmin'], 2);
	print_select_row($vbphrase['copy_permissions_from'], 'from', $prefixes);
	print_select_row($vbphrase['copy_permissions_to'], 'copyto[]', $prefixes, '', false, 10, true);
	print_yes_no_row($vbphrase['overwrite_customized_permissions_no_restrictions'], 'overwrite', 0);
	print_submit_row();
}

// ########################################################################

if ($_POST['do'] == 'doduplicate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'from'      => vB_Cleaner::TYPE_STR,
		'copyto'    => vB_Cleaner::TYPE_ARRAY_STR,
		'overwrite' => vB_Cleaner::TYPE_BOOL
	));

	$from_prefixid = $vbulletin->GPC['from'];

	if (empty($vbulletin->GPC['copyto'])
		OR (count($vbulletin->GPC['copyto']) == 1 AND reset($vbulletin->GPC['copyto']) == $from_prefixid)
	)
	{
		print_stop_message2('did_not_select_any_valid_prefixes_to_copy_to');
	}

	$prefix_restrictions = array();
	$prefix_options = array();

	$prefixes_result = $vbulletin->db->query_read("
		SELECT prefix.prefixid, prefix.options, prefixpermission.usergroupid AS restriction
		FROM " . TABLE_PREFIX . "prefix AS prefix
		LEFT JOIN " . TABLE_PREFIX . "prefixpermission AS prefixpermission ON (prefix.prefixid = prefixpermission.prefixid)
		WHERE prefix.prefixid IN ('" . $vbulletin->db->escape_string($from_prefixid) . "',
			'" . implode("', '", array_map(array($vbulletin->db, 'escape_string'), $vbulletin->GPC['copyto'])) . "')
	");
	while ($prefix = $vbulletin->db->fetch_array($prefixes_result))
	{
		if (empty($prefix_restrictions["$prefix[prefixid]"]))
		{
			$prefix_restrictions["$prefix[prefixid]"] = array();
			$prefix_options["$prefix[prefixid]"] = $prefix['options'];
		}

		if ($prefix['restriction'])
		{
			$prefix_restrictions["$prefix[prefixid]"][] = $prefix['restriction'];
		}
	}

	if (!isset($prefix_options["$from_prefixid"]))
	{
		print_stop_message2('you_did_not_select_any_prefixes');
	}

	$update_prefixids = array();

	foreach ($prefix_restrictions AS $prefixid => $restrictions)
	{
		if ($prefixid != $from_prefixid)
		{
			if (empty($restrictions) OR $vbulletin->GPC['overwrite'])
			{
				$update_prefixids[] = $prefixid;
			}
		}
	}

	if (!empty($update_prefixids))
	{
		$from_options = $prefix_options["$from_prefixid"];
		$from_restrictions = $prefix_restrictions["$from_prefixid"];
		$to_prefixes_in = "('" . implode("', '", array_map(array($vbulletin->db, 'escape_string'), $update_prefixids)) . "')";

		foreach ($update_prefixids AS $prefixid)
		{
			foreach ($from_restrictions AS $usergroupid)
			{
				$restriction_insert[] = "('" . $vbulletin->db->escape_string($prefixid) . "', $usergroupid)";
			}
		}

		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "prefix SET
				options = $from_options
			WHERE prefixid IN $to_prefixes_in
		");

		$vbulletin->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "prefixpermission
			WHERE prefixid IN $to_prefixes_in
		");

		$restriction_insert = array();

		foreach ($update_prefixids AS $prefixid)
		{
			foreach ($from_restrictions AS $usergroupid)
			{
				$restriction_insert[] = "('" . $vbulletin->db->escape_string($prefixid) . "', $usergroupid)";
			}
		}

		$vbulletin->db->query_replace(TABLE_PREFIX . "prefixpermission", '(prefixid, usergroupid)', $restriction_insert);

		build_prefix_datastore();
	}

	print_stop_message2('duplicated_prefix_permissions', 'prefix');
}

// ########################################################################

$vbulletin->input->clean_array_gpc('p', array(
	'dopermissionsmultiple' => vB_Cleaner::TYPE_STR
));

if ($vbulletin->GPC['dopermissionsmultiple'])
{
	$vbulletin->input->clean_array_gpc('p', array(
		'prefixids' => vB_Cleaner::TYPE_ARRAY_KEYS_STR
	));

	if (empty($vbulletin->GPC['prefixids']))
	{
		print_stop_message2('you_did_not_select_any_prefixes');
	}

	$_POST['do'] = $_REQUEST['do'] = 'permissions';
}

// ########################################################################

if ($_REQUEST['do'] == 'permissions')
{
	if (empty($vbulletin->GPC['prefixids']))
	{
		$vbulletin->input->clean_array_gpc('r', array(
			'prefixid' => vB_Cleaner::TYPE_STR
		));

		$prefixids = array($vbulletin->GPC['prefixid']);
	}
	else
	{
		$prefixids = $vbulletin->GPC['prefixids'];
	}

	$prefixes = $vbulletin->db->query_read("
		SELECT prefix.prefixid, prefix.options, prefixpermission.usergroupid AS restriction FROM " . TABLE_PREFIX . "prefix AS prefix
		LEFT JOIN " . TABLE_PREFIX . "prefixpermission AS prefixpermission ON (prefix.prefixid = prefixpermission.prefixid)
		WHERE prefix.prefixid IN ('" . implode("', '", array_map(array($vbulletin->db, 'escape_string'), $prefixids)) . "')
	");

	$prefixdefaults = $prefixpermissions = array();

	while ($prefix = $vbulletin->db->fetch_array($prefixes))
	{
		if (empty($prefixpermissions["$prefix[prefixid]"]))
		{
			$prefixpermissions["$prefix[prefixid]"] = array(intval($prefix['restriction']));
		}
		else
		{
			$prefixpermissions["$prefix[prefixid]"][] = intval($prefix['restriction']);
		}

		$prefixdefaults[] = !$prefix['options'] & $vbulletin->bf_misc_prefixoptions['deny_by_default'];
	}
	$vbulletin->db->free_result($prefixes);

	$usergroupperms = array();
	foreach ($prefixpermissions AS $prefixid => $restrictions)
	{
		foreach ($restrictions AS $restriction)
		{
			if ($restriction)
			{
				if (empty($usergroupperms["$restriction"]))
				{
					$usergroupperms["$restriction"] = array($prefixid);
				}
				else
				{
					$usergroupperms["$restriction"][] = $prefixid;
				}
			}
		}
	}

	$conflicts = array();
	foreach ($usergroupperms AS $usergroup => $prefixes)
	{
		if (array_diff($prefixids, $prefixes))
		{
				$conflicts[] = $usergroup;
		}
	}

	if (!empty($conflicts))
	{
		$conflict_options = array(
			$vbphrase['do_not_resolve_permission_conflict'],
			$vbphrase['grant_permission_for_selected_prefixes'],
			$vbphrase['deny_permission_for_selected_prefixes']
		);
	}

	$prefix_html = array();
	foreach ($prefixids AS $prefixid)
	{
		$prefix_html[] = '<a href="prefix.php?do=editprefix&amp;prefixid=' . htmlspecialchars_uni($prefixid) . '">' . htmlspecialchars_uni($vbphrase["prefix_{$prefixid}_title_plain"]) . '</a>';
	}

	?>
	<script type="text/javascript">
	<!--
	function check_all_checkable(toggle)
	{
		var els = YAHOO.util.Dom.getElementsByClassName('checkable');

		for (var i = 0; i < els.length; i++)
		{
			els[i].checked = toggle.checked;
		}
	}
	// -->
	</script>
	<?php

	print_form_header('prefix', 'savepermissions');
	print_table_header($vbphrase['edit_thread_prefix_permissions']);

	construct_hidden_code('prefixids', sign_client_string(serialize($prefixids)));
	construct_hidden_code('shownusergroups', sign_client_string(serialize(array_keys($vbulletin->usergroupcache))));

	print_description_row(construct_phrase($vbphrase['editing_permissions_for_x'], implode(', ', $prefix_html)));
	if (count(array_unique($prefixdefaults)) <= 1)
	{
		print_yes_no_row($vbphrase['allow_new_groups_to_use_selected_prefixes'], 'default', $prefixdefaults[0]);
	}
	else
	{
		$conflict_options_default = array(
			'-1' => $vbphrase['leave_default_permissions_unchanged'],
			'1'  => $vbphrase['new_groups_may_use_selected_prefixes'],
			'0'  => $vbphrase['new_groups_may_not_use_selected_prefixes']
		);

		print_label_row(
			$vbphrase['allow_new_groups_to_use_selected_prefixes'],
			"<label for=\"sel_default\" class=\"smallfont\">" . $vbphrase['set_default_permissions'] . ": <select name=\"default\" id=\"sel_default\">" . construct_select_options($conflict_options_default, '-1') . "</select>"
		);
	}

	print_description_row('<label for="cb_allbox"><input type="checkbox" name="allbox" id="cb_allbox" onclick="check_all_checkable(this)"' . (empty($usergroupperms) ? ' checked="checked"' : '') . " />$vbphrase[check_uncheck_all]</label>", false, 2, 'thead');

	foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
	{
		if (in_array($usergroupid, $conflicts))
		{
			print_label_row(
				"<label for=\"cb_ug$usergroupid\"><input type=\"checkbox\" disabled=\"disabled\" id=\"cb_ug$usergroupid\" />$usergroup[title]</label>",
				"<label for=\"sel_ug$usergroupid\" class=\"smallfont\">" . $vbphrase['resolve_permission_conflict'] . ": <select name=\"conflict[$usergroupid]\" id=\"sel_ug$usergroupid\">" . construct_select_options($conflict_options, 0) . "</select>"

			);
		}
		else
		{
			print_description_row("<label for=\"cb_ug$usergroupid\"><input type=\"checkbox\" name=\"usergroup[$usergroupid]\" id=\"cb_ug$usergroupid\" class=\"checkable\"" . (empty($usergroupperms["$usergroupid"]) ? ' checked="checked"' : '') . " />$usergroup[title]</label>");

		}
	}

	print_submit_row();
}

// ########################################################################

if ($_POST['do'] == 'savepermissions')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'prefixids' => vB_Cleaner::TYPE_NOCLEAN,
		'conflict' => vB_Cleaner::TYPE_ARRAY_INT
	));

	$prefixids_raw = unserialize(verify_client_string($vbulletin->GPC['prefixids']));

	$prefixids = array();

	foreach ($prefixids_raw AS $prefixid)
	{
		$prefixids[] = $vbulletin->cleaner->clean($prefixid, vB_Cleaner::TYPE_STR);
	}

	if (empty($prefixids))
	{
		print_stop_message2('you_did_not_select_any_prefixes');
	}

	$prefixes = $vbulletin->db->query_read("
		SELECT prefix.prefixid, prefix.options, prefixpermission.usergroupid AS restriction FROM " . TABLE_PREFIX . "prefix AS prefix
		LEFT JOIN " . TABLE_PREFIX . "prefixpermission AS prefixpermission ON (prefix.prefixid = prefixpermission.prefixid)
		WHERE prefix.prefixid IN ('" . implode("', '", array_map(array($vbulletin->db, 'escape_string'), $prefixids)) . "')
	");

	$prefixdefaults = array();

	while ($prefix = $vbulletin->db->fetch_array($prefixes))
	{
		if (empty($prefixpermissions["$prefix[prefixid]"]))
		{
			$prefixpermissions["$prefix[prefixid]"] = array(intval($prefix['restriction']));
		}
		else
		{
			$prefixpermissions["$prefix[prefixid]"][] = intval($prefix['restriction']);
		}

		$prefixdefaults[] = !$prefix['options'] & $vbulletin->bf_misc_prefixoptions['deny_by_default'];
	}
	$vbulletin->db->free_result($prefixes);

	$usergroupperms = array();
	foreach ($prefixpermissions AS $prefixid => $restrictions)
	{
		foreach ($restrictions AS $restriction)
		{
			if ($restriction)
			{
				if (empty($usergroupperms["$restriction"]))
				{
					$usergroupperms["$restriction"] = array($prefixid);
				}
				else
				{
					$usergroupperms["$restriction"][] = $prefixid;
				}
			}
		}
	}

	$conflicts = array();
	$override_no = array();
	foreach ($usergroupperms AS $usergroup => $prefixes)
	{
		if (in_array($usergroup, array_keys($vbulletin->GPC['conflict'])))
		{
			if ($vbulletin->GPC['conflict']["$usergroup"] === 0)
			{
				$conflicts[] = $usergroup;
			}
			else if ($vbulletin->GPC['conflict']["$usergroup"] === 2)
			{
				$override_no[] = $usergroup;
			}
		}
		else if (array_diff($prefixids, $prefixes)) // Marks as conflict when saving in race condition
		{
			if ($vbulletin->GPC['conflict']["$usergroup"] === 0)
			{
				$conflicts[] = $usergroup;
			}
		}
	}

	$vbulletin->input->clean_array_gpc('p', array(
		'shownusergroups' => vB_Cleaner::TYPE_NOCLEAN
	));

	$shownusergroups_raw = unserialize(verify_client_string($vbulletin->GPC['shownusergroups']));

	$shownusergroups = array();
	foreach ($shownusergroups_raw AS $shownusergroup)
	{
		$shownusergroups[] = $vbulletin->cleaner->clean($shownusergroup, vB_Cleaner::TYPE_UINT);
	}

	if (empty($shownusergroups))
	{ // This shouldn't trigger - probably a suhosin issue if it does
		print_stop_message2('variables_missing_suhosin');
	}

	$vbulletin->input->clean_array_gpc('p', array(
		'usergroup' => vB_Cleaner::TYPE_ARRAY_KEYS_INT
	));

	$vbulletin->db->query_write("
		DELETE FROM " . TABLE_PREFIX . "prefixpermission
		WHERE prefixid IN ('" . implode("', '", array_map(array($vbulletin->db, 'escape_string'), $prefixids)) . "')
		" . (!empty($conflicts) ? "AND usergroupid NOT IN (" . implode(', ', $conflicts) . ")" : '')
	);

	$todeny = array();

	foreach ($shownusergroups AS $shownusergroup)
	{
		if (array_key_exists($shownusergroup, $vbulletin->usergroupcache))
		{
			if (!in_array($shownusergroup, $conflicts))
			{
				if (!in_array($shownusergroup, $vbulletin->GPC['usergroup']) AND !in_array($shownusergroup, array_keys($vbulletin->GPC['conflict'])))
				{
					$todeny[] = $shownusergroup;
				}
			}
		}
	}

	$todeny = array_merge($todeny, $override_no);

	$sql_values = array();

	foreach ($prefixids AS $prefixid)
	{
		foreach ($todeny AS $deny)
		{
			$sql_values[] = "('" . $vbulletin->db->escape_string($prefixid) . "', " . $deny . ")";
		}
	}

	if (!empty($sql_values))
	{
		$vbulletin->db->query_replace(TABLE_PREFIX . "prefixpermission", '(prefixid, usergroupid)', $sql_values);
	}

	$vbulletin->input->clean_array_gpc('p', array(
		'default' => vB_Cleaner::TYPE_INT
	));

	if ($vbulletin->GPC['default'] != -1)
	{
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "prefix SET options = options " . (empty($vbulletin->GPC['default']) ? '| ' : '& ~') . $vbulletin->bf_misc_prefixoptions['deny_by_default'] . "
			WHERE prefixid IN ('" . implode("', '", array_map(array($vbulletin->db, 'escape_string'), $prefixids)) . "')
		");
	}
	build_prefix_datastore();

	print_stop_message2('saved_prefix_permissions', 'prefix', array('do'=>'list'));
}

// ########################################################################

if ($_POST['do'] == 'killprefix')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'prefixid' => vB_Cleaner::TYPE_NOHTML,
	));

	$prefixdm =& datamanager_init('Prefix', $vbulletin, vB_DataManager_Constants::ERRTYPE_CP);

	$prefix = $vbulletin->db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "prefix
		WHERE prefixid = '" . $vbulletin->db->escape_string($vbulletin->GPC['prefixid']) . "'
	");
	if (!$prefix)
	{
		print_stop_message2('invalid_action_specified_gcpglobal');
	}

	$prefixdm->set_existing($prefix);
	$prefixdm->delete();

	print_stop_message2('prefix_deleted', 'prefix', array('do'=>'list'));
}

// ########################################################################

if ($_REQUEST['do'] == 'deleteprefix')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'prefixid' => vB_Cleaner::TYPE_NOHTML,
	));

	print_delete_confirmation('prefix', $vbulletin->GPC['prefixid'], 'prefix', 'killprefix');
}

// ########################################################################

if ($_POST['do'] == 'insertprefix')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'prefixid' => vB_Cleaner::TYPE_NOHTML,
		'origprefixid' => vB_Cleaner::TYPE_NOHTML,
		'prefixsetid' => vB_Cleaner::TYPE_NOHTML,
		'title_plain' => vB_Cleaner::TYPE_STR,
		'title_rich' => vB_Cleaner::TYPE_STR,
		'displayorder' => vB_Cleaner::TYPE_UINT
	));

	$prefixdm =& datamanager_init('Prefix', $vbulletin, vB_DataManager_Constants::ERRTYPE_CP);

	if ($vbulletin->GPC['origprefixid'])
	{
		$prefix = $vbulletin->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "prefix
			WHERE prefixid = '" . $vbulletin->db->escape_string($vbulletin->GPC['origprefixid']) . "'
		");
		if (!$prefix)
		{
			print_stop_message2('invalid_action_specified');
		}

		$prefixdm->set_existing($prefix);
	}
	else
	{
		$prefixdm->set('prefixid', $vbulletin->GPC['prefixid']);
	}

	$prefixdm->set('prefixsetid', $vbulletin->GPC['prefixsetid']);
	$prefixdm->set('displayorder', $vbulletin->GPC['displayorder']);
	$prefixdm->set_info('title_plain', $vbulletin->GPC['title_plain']);
	$prefixdm->set_info('title_rich', $vbulletin->GPC['title_rich']);
	$prefixdm->set('options', $vbulletin->bf_misc_prefixoptions['deny_by_default']);

	$prefixdm->save();

	print_stop_message2('prefix_saved', 'prefix', array('do'=>'list'));
}

// ########################################################################

if ($_REQUEST['do'] == 'addprefix' OR $_REQUEST['do'] == 'editprefix')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'prefixid' => vB_Cleaner::TYPE_NOHTML,
		'prefixsetid' => vB_Cleaner::TYPE_NOHTML
	));

	// fetch existing prefix if we want to edit
	if ($vbulletin->GPC['prefixid'])
	{
		$prefix = $vbulletin->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "prefix
			WHERE prefixid = '" . $vbulletin->db->escape_string($vbulletin->GPC['prefixid']) . "'
		");
		if ($prefix)
		{
			$phrase_sql = $vbulletin->db->query_read("
				SELECT varname, text
				FROM " . TABLE_PREFIX . "phrase
				WHERE varname IN (
						'" . $vbulletin->db->escape_string("prefix_$prefix[prefixid]_title_plain") . "',
						'" . $vbulletin->db->escape_string("prefix_$prefix[prefixid]_title_rich") . "'
					)
					AND fieldname = 'global'
					AND languageid = 0
			");
			while ($phrase = $vbulletin->db->fetch_array($phrase_sql))
			{
				$title = str_replace("prefix_$prefix[prefixid]_", '', $phrase['varname']);
				$prefix["$title"] = $phrase['text'];
			}
		}
	}

	// if not editing a set, setup the default for a new set
	if (empty($prefix))
	{
		$prefix = array(
			'prefixid' => '',
			'prefixsetid' => $vbulletin->GPC['prefixsetid'],
			'title_plain' => '',
			'title_rich' => '',
			'displayorder' => 10
		);
	}

	$trans_link = "phrase.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&fieldname=global&t=1&varname="; // has varname appended

	print_form_header('prefix', 'insertprefix');

	if ($prefix['prefixid'])
	{
		print_table_header($vbphrase['editing_prefix']);
		print_label_row($vbphrase['prefix_id_alphanumeric_note'], $prefix['prefixid'], '', 'top', 'prefixid');
		construct_hidden_code('origprefixid', $prefix['prefixid']);
	}
	else
	{
		print_table_header($vbphrase['adding_prefix']);
		print_input_row($vbphrase['prefix_id_alphanumeric_note'], 'prefixid', '', true, 35, 25);
	}

	$prefixsets_sql = $vbulletin->db->query_read("
		SELECT prefixsetid
		FROM " . TABLE_PREFIX . "prefixset
		ORDER BY displayorder
	");

	$prefixsets = array();
	while ($prefixset = $vbulletin->db->fetch_array($prefixsets_sql))
	{
		$prefixsets["$prefixset[prefixsetid]"] = htmlspecialchars_uni($vbphrase["prefixset_$prefixset[prefixsetid]_title"]);
	}

	print_select_row($vbphrase['prefix_set'], 'prefixsetid', $prefixsets, $prefix['prefixsetid']);
	print_input_row(
		$vbphrase['title_plain_text'] . ($prefix['prefixid'] ?  '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "prefix_$prefix[prefixid]_title_plain", 1)  . '</dfn>' : ''),
		'title_plain', $prefix['title_plain']
	);
	print_input_row(
		$vbphrase['title_rich_text'] . ($prefix['prefixid'] ?  '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "prefix_$prefix[prefixid]_title_rich", 1)  . '</dfn>' : ''),
		'title_rich', $prefix['title_rich']
	);
	print_input_row($vbphrase['display_order'], 'displayorder', $prefix['displayorder']);
	print_submit_row();
}

// ########################################################################

if ($_POST['do'] == 'killset')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'prefixsetid' => vB_Cleaner::TYPE_NOHTML,
	));

	$prefixsetdm =& datamanager_init('PrefixSet', $vbulletin, vB_DataManager_Constants::ERRTYPE_CP);

	$prefixset = $vbulletin->db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "prefixset
		WHERE prefixsetid = '" . $vbulletin->db->escape_string($vbulletin->GPC['prefixsetid']) . "'
	");
	if (!$prefixset)
	{
		print_stop_message2('invalid_action_specified_gcpglobal');
	}

	$prefixsetdm->set_existing($prefixset);
	$prefixsetdm->delete();

	print_stop_message2('prefix_set_deleted', 'prefix', array('do'=>'list'));
}

// ########################################################################

if ($_REQUEST['do'] == 'deleteset')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'prefixsetid' => vB_Cleaner::TYPE_NOHTML,
	));

	print_delete_confirmation('prefixset', $vbulletin->GPC['prefixsetid'], 'prefix', 'killset');
}

// ########################################################################

if ($_POST['do'] == 'insertset')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'prefixsetid' => vB_Cleaner::TYPE_NOHTML,
		'origprefixsetid' => vB_Cleaner::TYPE_NOHTML,
		'title' => vB_Cleaner::TYPE_STR,
		'displayorder' => vB_Cleaner::TYPE_UINT,
		'nodeids' => vB_Cleaner::TYPE_ARRAY_INT
	));

	$prefixsetdm =& datamanager_init('PrefixSet', $vbulletin, vB_DataManager_Constants::ERRTYPE_CP);

	if ($vbulletin->GPC['origprefixsetid'])
	{
		$prefixset = $vbulletin->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "prefixset
			WHERE prefixsetid = '" . $vbulletin->db->escape_string($vbulletin->GPC['origprefixsetid']) . "'
		");
		if (!$prefixset)
		{
			print_stop_message2('invalid_action_specified_gcpglobal');
		}

		$prefixsetdm->set_existing($prefixset);
	}
	else
	{
		$prefixsetdm->set('prefixsetid', $vbulletin->GPC['prefixsetid']);
	}

	$prefixsetdm->set('displayorder', $vbulletin->GPC['displayorder']);
	$prefixsetdm->set_info('title', $vbulletin->GPC['title']);

	$prefixsetdm->save();
	$vbulletin->GPC['prefixsetid'] = $prefixsetdm->fetch_field('prefixsetid');

	// setup this prefix set for selected forums
	$old_channels = array();
	if ($vbulletin->GPC['origprefixsetid'])
	{
		// find where the prefix used to be used
		$channel_list_sql = $vbulletin->db->query_read("
			SELECT nodeid
			FROM " . TABLE_PREFIX . "channelprefixset
			WHERE prefixsetid = '" . $vbulletin->db->escape_string($vbulletin->GPC['prefixsetid']) . "'
		");
		while ($channel = $vbulletin->db->fetch_array($channel_list_sql))
		{
			$old_channels[] = $channel['nodeid'];
		}
	}

	$new_channels = array_diff($vbulletin->GPC['nodeids'], array(-1, 0));

	$vbulletin->db->query_write("
		DELETE FROM " . TABLE_PREFIX . "channelprefixset
		WHERE prefixsetid = '" . $vbulletin->db->escape_string($vbulletin->GPC['prefixsetid']) . "'
	");

	$add_channels_query = array();
	$escaped_id = $vbulletin->db->escape_string($vbulletin->GPC['prefixsetid']);

	foreach ($new_channels AS $channelid)
	{
		$add_channels_query[] = "($channelid, '$escaped_id')";
	}

	if ($add_channels_query)
	{
		$vbulletin->db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "channelprefixset
				(nodeid, prefixsetid)
			VALUES
				" . implode(',', $add_channels_query)
		);
	}

	// find the forums that were removed and remove these prefixes from threads
	$removed_channels = array_diff($old_channels, $new_channels);
	if ($removed_channels)
	{
		$prefixes = array();
		$prefix_sql = $vbulletin->db->query_read("
			SELECT prefixid
			FROM " . TABLE_PREFIX . "prefix
			WHERE prefixsetid = '" . $vbulletin->db->escape_string($vbulletin->GPC['prefixsetid']) . "'
		");
		while ($prefix = $vbulletin->db->fetch_array($prefix_sql))
		{
			$prefixes[] = $prefix['prefixid'];
		}

		remove_prefixes_forum($prefixes, $removed_channels);
	}

	build_prefix_datastore();

	print_stop_message2('prefix_set_saved', 'prefix', array('do'=>'list'));
}

// ########################################################################

if ($_REQUEST['do'] == 'addset' OR $_REQUEST['do'] == 'editset')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'prefixsetid' => vB_Cleaner::TYPE_NOHTML
	));

	// fetch existing prefix set if we want to edit
	if ($vbulletin->GPC['prefixsetid'])
	{
		$prefixset = $vbulletin->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "prefixset
			WHERE prefixsetid = '" . $vbulletin->db->escape_string($vbulletin->GPC['prefixsetid']) . "'
		");
		if ($prefixset)
		{
			$phrase = $vbulletin->db->query_first("
				SELECT text
				FROM " . TABLE_PREFIX . "phrase
				WHERE varname = '" . $vbulletin->db->escape_string("prefixset_$prefixset[prefixsetid]_title") . "'
					AND fieldname = 'prefix'
					AND languageid = 0
			");
			$prefixset['title'] = $phrase['text'];
		}
	}

	// if not editing a set, setup the default for a new set
	if (empty($prefixset))
	{
		$prefixset = array(
			'prefixsetid' => '',
			'title' => '',
			'displayorder' => 10
		);
	}

	print_form_header('prefix', 'insertset');

	if ($prefixset['prefixsetid'])
	{
		print_table_header($vbphrase['editing_prefix_set']);
		print_label_row($vbphrase['prefix_set_id_alphanumeric_note'], $prefixset['prefixsetid'], '', 'top', 'prefixsetid');
		construct_hidden_code('origprefixsetid', $prefixset['prefixsetid']);
	}
	else
	{
		print_table_header($vbphrase['adding_prefix_set']);
		print_input_row($vbphrase['prefix_set_id_alphanumeric_note'], 'prefixsetid', '', true, 35, 25);
	}

	$trans_link = "phrase.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&fieldname=prefix&t=1&varname="; // has varname appended

	print_input_row(
		$vbphrase['title']. ($prefixset['prefixsetid'] ?  '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "prefixset_$prefixset[prefixsetid]_title", 1)  . '</dfn>' : ''),
		'title', $prefixset['title']
	);
	print_input_row($vbphrase['display_order'], 'displayorder', $prefixset['displayorder']);

	$enabled_channels = array();
	if ($prefixset['prefixsetid'])
	{
		$channels_sql = $vbulletin->db->query_read("
			SELECT nodeid
			FROM " . TABLE_PREFIX . "channelprefixset
			WHERE prefixsetid = '" . $vbulletin->db->escape_string($prefixset['prefixsetid']) . "'
		");
		while ($channel = $vbulletin->db->fetch_array($channels_sql))
		{
			$enabled_channels[] = $channel['nodeid'];
		}
	}

	if (empty($enabled_channels))
	{
		// default to selecting "none"
		$enabled_channels = array(-1);
	}

	print_channel_chooser($vbphrase['use_prefix_set_in_these_channels'], 'nodeids[]', $enabled_channels, $vbphrase['none'], false, true);

	print_submit_row();
}

// ########################################################################

if ($_POST['do'] == 'displayorder')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'prefixset_order' => vB_Cleaner::TYPE_ARRAY_UINT,
		'prefix_order' => vB_Cleaner::TYPE_ARRAY_UINT
	));

	foreach ($vbulletin->GPC['prefixset_order'] AS $prefixsetid => $displayorder)
	{
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "prefixset SET
				displayorder =  " . intval($displayorder) . "
			WHERE prefixsetid = '" . $vbulletin->db->escape_string($prefixsetid) . "'
		");
	}

	foreach ($vbulletin->GPC['prefix_order'] AS $prefixid => $displayorder)
	{
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "prefix SET
				displayorder =  " . intval($displayorder) . "
			WHERE prefixid = '" . $vbulletin->db->escape_string($prefixid) . "'
		");
	}

	build_prefix_datastore();

	print_stop_message2('saved_display_order_successfully', 'prefix', array('do'=>'list'));
}

// ########################################################################

if ($_REQUEST['do'] == 'list')
{
	$prefixsets_sql = $vbulletin->db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "prefixset
		ORDER BY displayorder
	");

	$prefixsets = array();
	while ($prefixset = $vbulletin->db->fetch_array($prefixsets_sql))
	{
		$prefixsets["$prefixset[prefixsetid]"] = $prefixset;
		$prefixsets["$prefixset[prefixsetid]"]['prefixes'] = array();
	}

	$prefixes_sql = $vbulletin->db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "prefix
		ORDER BY displayorder
	");
	while ($prefix = $vbulletin->db->fetch_array($prefixes_sql))
	{
		if (isset($prefixsets["$prefix[prefixsetid]"]))
		{
			$prefixsets["$prefix[prefixsetid]"]['prefixes']["$prefix[prefixid]"] = $prefix;
		}
	}

	print_form_header('prefix', 'displayorder');
	print_table_header($vbphrase['thread_prefixes_gprefixadmin'], 3);

	?>
	<script type="text/javascript">
	<!--
	function selectprefixes(prefixset)
	{
		var els = YAHOO.util.Dom.getElementsByClassName(prefixset);

		var toggle = document.getElementById(prefixset);

		for (var i = 0; i < els.length; i++)
		{
			els[i].checked = toggle.checked;
		}

	}
	// -->
	</script>
	<?php

	if (!$prefixsets)
	{
		print_description_row($vbphrase['no_prefix_sets_defined_click_create'], false, 3, '', 'center');
	}
	else
	{
		// display existing sets
		foreach ($prefixsets AS $prefixset)
		{
			print_cells_row(array(
				'<input id="' . $prefixset['prefixsetid'] . '" type="checkbox" onclick="selectprefixes(\'' . $prefixset['prefixsetid'] . '\')" />' .
				'<label for="' . $prefixset['prefixsetid'] . '">' . htmlspecialchars_uni($vbphrase["prefixset_$prefixset[prefixsetid]_title"]) . '</label>',
				'<input type="text" size="3" class="bginput" name="prefixset_order[' . $prefixset['prefixsetid'] . ']" value="' . $prefixset['displayorder'] . '" />',
				'<div class="normal">'
					. construct_link_code($vbphrase['add_prefix'], "prefix.php?do=addprefix&amp;prefixsetid=$prefixset[prefixsetid]")
					. construct_link_code($vbphrase['edit'], "prefix.php?do=editset&amp;prefixsetid=$prefixset[prefixsetid]")
					. construct_link_code($vbphrase['delete'], "prefix.php?do=deleteset&amp;prefixsetid=$prefixset[prefixsetid]")
				. '</div>',
			), 1);

			if (!$prefixset['prefixes'])
			{
				print_description_row(construct_phrase($vbphrase['no_prefixes_defined_click_create'], $prefixset['prefixsetid']), false, 3, '', 'center');
			}
			else
			{
				foreach ($prefixset['prefixes'] AS $prefix)
				{
					print_cells_row(array(
						'<label for="' . $prefixset['prefixsetid'] . '_' . $prefix['prefixid'] . '">' .
							'<input type="checkbox" name="prefixids[' . $prefix["prefixid"] . ']" id="' . $prefixset['prefixsetid'] . '_' . $prefix['prefixid'] . '" class="' . $prefixset['prefixsetid'] . '" />' .
							htmlspecialchars_uni($vbphrase["prefix_$prefix[prefixid]_title_plain"]) .
						'</label>',
						'<input type="text" size="3" class="bginput" name="prefix_order[' . $prefix['prefixid'] . ']" value="' . $prefix['displayorder'] . '" />',
						'<div class="smallfont">'
							. construct_link_code($vbphrase['edit'], "prefix.php?do=editprefix&amp;prefixid=$prefix[prefixid]")
							. construct_link_code($vbphrase['delete'], "prefix.php?do=deleteprefix&amp;prefixid=$prefix[prefixid]")
							. construct_link_code($vbphrase['edit_permissions'], "prefix.php?do=permissions&amp;prefixid=$prefix[prefixid]")
						. '</div>'
					));
				}
			}
		}
	}

	print_cells_row(array(
		'<input type="image" style="width: 1px; height: 1px;" src="' . vB::getDatastore()->getOption('bburl') . '/' . $vbulletin->options['cleargifurl'] . '" />' .
		'<input class="button" type="submit" name="dopermissionsmultiple" value="' . $vbphrase['edit_selected_prefix_permissions'] . '" />',
		'<input class="button" type="submit" value="'. $vbphrase['save_display_order'] . '" />',
		'<input class="button" type="button" onclick="window.location = \'prefix.php?do=addset\';" value="'. $vbphrase['add_prefix_set'] . '" />'
	), false, 'tfoot');

	print_table_footer();
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>