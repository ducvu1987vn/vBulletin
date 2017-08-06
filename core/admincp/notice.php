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
* To disable the Javascript-based disabling of criteria in the notice add/edit code,
* define NOTICE_CRITERIA_JS as 'false' in config.php
*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 68365 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbphrase, $vbulletin;
$phrasegroups = array('notice');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/adminfunctions_notice.php');
$assertor = vB::getDbAssertor();

// ############################# LOG ACTION ###############################
if (!can_administer('canadminnotices'))
{
	print_cp_no_permission();
}

$vbulletin->input->clean_array_gpc('r', array('noticeid' => vB_Cleaner::TYPE_INT));

log_admin_action($vbulletin->GPC['noticeid'] != 0 ? "notice id = " . $vbulletin->GPC['noticeid'] : '');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['notices_manager']);

if (empty($_REQUEST['do']))
{
	if (!empty($_REQUEST['noticeid']))
	{
		$_REQUEST['do'] = 'edit';
	}
	else
	{
		$_REQUEST['do'] = 'modify';
	}
}

// #############################################################################
// remove a notice
if ($_POST['do'] == 'remove')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'noticeid' => vB_Cleaner::TYPE_UINT
	));

	// delete criteria
	$assertor->delete('vBForum:noticecriteria', array('noticeid' => $vbulletin->GPC['noticeid']));

	// delete dismisses
	$assertor->delete('vBForum:noticedismissed', array('noticeid' => $vbulletin->GPC['noticeid']));

	// delete notice
	$assertor->delete('vBForum:notice', array('noticeid' => $vbulletin->GPC['noticeid']));

	// delete phrases
	$assertor->delete('vBForum:phrase', array('varname' => 'notice_' . $vbulletin->GPC['noticeid'] . '_html'));

	// update the datastore notice cache
	build_notice_datastore();

	// rebuild languages
	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language(-1);

	print_stop_message2('deleted_notice_successfully', 'notice', array('do'=>'modify'));
}

// #############################################################################
// confirm deletion of a notice
if ($_REQUEST['do'] == 'delete')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'noticeid' => vB_Cleaner::TYPE_UINT
	));

	print_delete_confirmation('notice', $vbulletin->GPC['noticeid'], 'notice', 'remove');
}

// #############################################################################
// update or insert a notice
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'noticeid'     => vB_Cleaner::TYPE_UINT,
		'title'        => vB_Cleaner::TYPE_NOHTML,
		'html'         => vB_Cleaner::TYPE_STR,
		'displayorder' => vB_Cleaner::TYPE_UINT,
		'active'       => vB_Cleaner::TYPE_BOOL,
		'persistent'   => vB_Cleaner::TYPE_BOOL,
		'dismissible'  => vB_Cleaner::TYPE_BOOL,
		'criteria'     => vB_Cleaner::TYPE_ARRAY
	));
	$noticeid =& $vbulletin->GPC['noticeid'];

	// Check to see if there is criteria
	$have_criteria = false;
	foreach ($vbulletin->GPC['criteria'] AS $criteria)
	{
		if ($criteria['active'])
		{
			$have_criteria = true;
			break;
		}
	}

	if ($vbulletin->GPC['title'] === '')
	{
		print_stop_message2('invalid_title_specified');
	}

	// we are editing
	if ($vbulletin->GPC['noticeid'])
	{
		// update notice record
		$assertor->update('vBForum:notice', array(
				'title'        => $vbulletin->GPC['title'],
				'displayorder' => $vbulletin->GPC['displayorder'],
				'active'       => $vbulletin->GPC['active'],
				'persistent'   => $vbulletin->GPC['persistent'],
				'dismissible'  => $vbulletin->GPC['dismissible'],
			),
			array(
				'noticeid' => $noticeid
			)
		);

		// delete criteria
		$assertor->delete('vBForum:noticecriteria', array('noticeid' => $noticeid));

		if (!$vbulletin->GPC['dismissible'])
		{
			// removing old dismissals
			$assertor->delete('vBForum:noticedismissed', array('noticeid' => $noticeid));
		}
	}
	// we are adding a new notice
	else
	{
		// insert notice record
		$noticeid = $assertor->insert('vBForum:notice', array(
			'title'        => $vbulletin->GPC['title'],
			'displayorder' => $vbulletin->GPC['displayorder'],
			'persistent'   => $vbulletin->GPC['persistent'],
			'active'       => $vbulletin->GPC['active'],
			'dismissible'  => $vbulletin->GPC['dismissible'],
		));
	}
	// Check to see if there is criteria to insert
	if ($have_criteria)
	{
		// assemble criteria insertion query
		$criteria_sql = array();
		foreach ($vbulletin->GPC['criteria'] AS $criteriaid => $criteria)
		{
			if ($criteria['active'])
			{
				$criteria_sql[] = array(
					'noticeid' => $noticeid,
					'criteriaid' => $criteriaid,
					'condition1' => trim($criteria['condition1']),
					'condition2' => trim($criteria['condition2']),
					'condition3' => trim($criteria['condition3'])
				);
			}
		}
		// insert criteria
		$assertor->insertMultiple('vBForum:noticecriteria',
			array('noticeid', 'criteriaid', 'condition1', 'condition2', 'condition3'),
			$criteria_sql
		);
	}
	// insert / update phrase
	$userInfo = vB_User::fetchUserInfo();
	$options = vB::getDatastore()->getValue('options');
	$assertor->assertQuery('replaceIntoPhrases', array(
		'languageid' => 0,
		'varname'    => 'notice_' . $noticeid . '_html',
		'text'       => $vbulletin->GPC['html'],
		'product'    => 'vbulletin',
		'fieldname'  => 'global',
		'enteredBy'   => $userInfo['username'],
		'dateline'   => vB::getRequest()->getTimeNow(),
		'version'    => $options['templateversion']
	));

	// update the datastore notice cache
	build_notice_datastore();

	// rebuild languages
	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language(-1);

	print_stop_message2(array('saved_notice_x_successfully',  $vbulletin->GPC['title']), 'notice', array('do' => 'modify'));
}

// #############################################################################
// edit a notice
if ($_REQUEST['do'] == 'edit' OR $_REQUEST['do'] == 'add')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'noticeid' => vB_Cleaner::TYPE_UINT
	));

	// initialize some data storage
	$notice_cache      = array();
	$notice_name_cache = array();
	$criteria_cache    = array();

	// cache all notices
	$notice_result = $assertor->getRows('vBForum:notice', array(), 'displayorder');
	$max_displayorder = 0;
	foreach ($notice_result AS $notice)
	{
		$notice_cache["$notice[noticeid]"] = $notice;
		if ($notice['noticeid'] != $vbulletin->GPC['noticeid'])
		{
			$notice_name_cache["$notice[noticeid]"] = $notice['title'];
		}
		if ($notice['displayorder'] > $max_displayorder)
		{
			$max_displayorder = $notice['displayorder'];
		}
	}

	// set some default values
	$notice = array(
		'displayorder' => $max_displayorder + 10,
		'active' => true,
		'persistent' => true,
		'dismissible' => true
	);

	$table_title = $vbphrase['add_new_notice'];

	// are we editing or adding?
	if ($vbulletin->GPC['noticeid'] AND !empty($notice_cache[$vbulletin->GPC['noticeid']]))
	{
		// edit existing notice
		$notice = $notice_cache[$vbulletin->GPC['noticeid']];

		// fetch title and notice phrases
		$phrases_result = $assertor->getRows('vBForum:phrase', array('varname' => 'notice_' . $notice['noticeid'] . '_html', 'languageid' => 0));
		foreach ($phrases_result AS $phrase)
		{
			$array_key = substr($phrase['varname'], strlen("notice_$notice[noticeid]_"));
			$notice["$array_key"] = $phrase['text'];
			$notice["{$array_key}_phraseid"] = $phrase['phraseid'];
		}

		$criteria_result = $assertor->getRows('vBForum:noticecriteria', array('noticeid' => $vbulletin->GPC['noticeid']));
		foreach ($criteria_result AS $criteria)
		{
			$criteria_cache["$criteria[criteriaid]"] = $criteria;
		}

		$table_title = $vbphrase['edit_notice'] . " <span class=\"normal\">$notice[title]</span>";
	}

	// build list of usergroup titles
	$usergroup_options = array();
	foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
	{
		$usergroup_options["$usergroupid"] = $usergroup['title'];
	}

	// build list of style names
	$stylecache = vB_Library::instance('Style')->fetchStyles(false, false);
	$style_options = array();
	foreach($stylecache AS $style)
	{
		$style_options["$style[styleid]"] = /*construct_depth_mark($style['depth'], '&nbsp; &nbsp; ') . ' ' .*/ $style['title'];
		$style_options["$style[styleid]"] = construct_depth_mark($style['depth'], '--') . ' ' . $style['title'];
	}

	// build the list of criteria options
	$criteria_options = array(
		'in_usergroup_x' => array(
			'<select name="criteria[in_usergroup_x][condition1]" tabindex="1">' .
				construct_select_options($usergroup_options, (empty($criteria_cache['in_usergroup_x']) ? 2 : $criteria_cache['in_usergroup_x']['condition1'])) .
			'</select>'
		),
		'not_in_usergroup_x' => array(
			'<select name="criteria[not_in_usergroup_x][condition1]" tabindex="1">' .
				construct_select_options($usergroup_options, (empty($criteria_cache['not_in_usergroup_x']) ? 6 : $criteria_cache['not_in_usergroup_x']['condition1'])) .
			'</select>'
		),
		'browsing_forum_x' => array(
			'<select name="criteria[browsing_forum_x][condition1]" tabindex="1">' .
				construct_select_options(construct_forum_chooser_options(), $criteria_cache['browsing_forum_x']['condition1']) .
			'</select>'
		),
		'browsing_forum_x_and_children' => array(
			'<select name="criteria[browsing_forum_x_and_children][condition1]" tabindex="1">' .
				construct_select_options(construct_forum_chooser_options(), $criteria_cache['browsing_forum_x_and_children']['condition1']) .
			'</select>'
		),
		'style_is_x' => array(
			'<select name="criteria[style_is_x][condition1]" tabindex="1">' .
				construct_select_options($style_options, $criteria_cache['style_is_x']['condition1']) .
			'</select>'
		),
		'no_visit_in_x_days' => array(
			'<input type="text" name="criteria[no_visit_in_x_days][condition1]" size="5" class="bginput" tabindex="1" value="' .
				(empty($criteria_cache['no_visit_in_x_days']) ? 30 : intval($criteria_cache['no_visit_in_x_days']['condition1'])) .
			'" />'
		),
		'no_posts_in_x_days' => array(
			'<input type="text" name="criteria[no_posts_in_x_days][condition1]" size="5" class="bginput" tabindex="1" value="' .
				(empty($criteria_cache['no_posts_in_x_days']) ? 30 : intval($criteria_cache['no_posts_in_x_days']['condition1'])) .
			'" />'
		),
		'has_x_postcount' => array(
			'<input type="text" name="criteria[has_x_postcount][condition1]" size="5" class="bginput" tabindex="1" value="' .
				$criteria_cache['has_x_postcount']['condition1'] .
			'" />',
			'<input type="text" name="criteria[has_x_postcount][condition2]" size="5" class="bginput" tabindex="1" value="' .
				$criteria_cache['has_x_postcount']['condition2'] .
			'" />'
		),
		'has_never_posted' => array(
		),
		'has_x_reputation' => array(
			'<input type="text" name="criteria[has_x_reputation][condition1]" size="5" class="bginput" tabindex="1" value="' .
				(empty($criteria_cache['has_x_reputation']) ? 100 : $criteria_cache['has_x_reputation']['condition1']) .
			'" />',
			'<input type="text" name="criteria[has_x_reputation][condition2]" size="5" class="bginput" tabindex="1" value="' .
				(empty($criteria_cache['has_x_reputation']) ? 200 : $criteria_cache['has_x_reputation']['condition2']) .
			'" />'
		),
		'has_x_infraction_points' => array(
			'<input type="text" name="criteria[has_x_infraction_points][condition1]" size="5" class="bginput" tabindex="1" value="' .
				(empty($criteria_cache['has_x_infraction_points']) ? 5 : $criteria_cache['has_x_infraction_points']['condition1']) .
			'" />',
			'<input type="text" name="criteria[has_x_infraction_points][condition2]" size="5" class="bginput" tabindex="1" value="' .
				(empty($criteria_cache['has_x_infraction_points']) ? 10 :$criteria_cache['has_x_infraction_points']['condition2']) .
			'" />'
		),
		'pm_storage_x_percent_full' => array(
			'<input type="text" name="criteria[pm_storage_x_percent_full][condition1]" size="5" class="bginput" tabindex="1" value="' .
				(empty($criteria_cache['pm_storage_x_percent_full']) ? 90 : $criteria_cache['pm_storage_x_percent_full']['condition1']) .
			'" />',
			'<input type="text" name="criteria[pm_storage_x_percent_full][condition2]" size="5" class="bginput" tabindex="1" value="' .
				(empty($criteria_cache['pm_storage_x_percent_full']) ? 100 : $criteria_cache['pm_storage_x_percent_full']['condition2']) .
			'" />'
		),
		'username_is' => array(
			'<input type="text" name="criteria[username_is][condition1]" size="20" class="bginput" tabindex="1" value="' .
				(empty($criteria_cache['username_is']) ? $vbulletin->userinfo['username'] : htmlspecialchars_uni($criteria_cache['username_is']['condition1'])) .
			'" />'
		),
		'is_birthday' => array(
		),
		'came_from_search_engine' => array(
		),
		'in_coventry' => array(
		),
		'is_date' => array(
			'<input type="text" name="criteria[is_date][condition1]" size="10" class="bginput" tabindex="1" value="' .
				(empty($criteria_cache['is_date']['condition1']) ? vbdate('d-m-Y', TIMENOW, false, false) : $criteria_cache['is_date']['condition1']) .
			'" />',
			'<select name="criteria[is_date][condition2]" tabindex="1">
				<option value="0"' . (empty($criteria_cache['is_date']['condition2']) ? ' selected="selected"' : '') . '>' . $vbphrase['user_timezone'] . '</option>
				<option value="1"' . ($criteria_cache['is_date']['condition2'] == 1 ? ' selected="selected"' : '') . '>' . $vbphrase['utc_universal_time'] . '</option>
			</select>'
		),
		'is_time' => array(
			'<input type="text" name="criteria[is_time][condition1]" size="5" class="bginput" tabindex="1" value="' .
				(empty($criteria_cache['is_time']['condition1']) ? vbdate('H:i', TIMENOW, false, false) : $criteria_cache['is_time']['condition1']) .
			'" />',
			'<input type="text" name="criteria[is_time][condition2]" size="5" class="bginput" tabindex="1" value="' .
				(empty($criteria_cache['is_time']['condition2']) ? (intval(vbdate('H', TIMENOW, false, false)) + 1) . vbdate(':i', TIMENOW, false, false) : $criteria_cache['is_time']['condition2']) .
			'" />',
			'<select name="criteria[is_time][condition3]" tabindex="1">
				<option value="0"' . (empty($criteria_cache['is_time']['condition3']) ? ' selected="selected"' : '') . '>' . $vbphrase['user_timezone'] . '</option>
				<option value="1"' . ($criteria_cache['is_time']['condition3'] == 1 ? ' selected="selected"' : '') . '>' . $vbphrase['utc_universal_time'] . '</option>
			</select>'
		),
		/*
		* These are flagged for a future version
		'userfield_x_equals_y' => array(
		),
		'userfield_x_contains_y' => array(
		),
		*/
	);

	if (!empty($notice_name_cache))
	{
		$criteria_options['notice_x_not_displayed'] = array(
			'<select name="criteria[notice_x_not_displayed][condition1]" tabindex="1">' .
				construct_select_options($notice_name_cache, $criteria_cache['notice_x_not_displayed']['condition1']) .
			'</select>'
		);
	}
	$display_active_criteria_first = false;
	// hook to allow third-party additions of criteria
	// Legacy Hook 'notices_list_criteria' Removed //

	// build the editor form
	print_form_header('notice', 'update');
	construct_hidden_code('noticeid', $vbulletin->GPC['noticeid']);
	print_table_header($table_title);

	print_input_row($vbphrase['title'] . '<dfn>' . $vbphrase['notice_title_description'] . '</dfn>', 'title', $notice['title'], 0, 60);
	print_textarea_row($vbphrase['notice_html'] . '<dfn>' . $vbphrase['notice_html_description'] . '</dfn>' . ($vbulletin->GPC['noticeid'] ? '<div class="smallfont" style="margin-top:6px"><a href="phrase.php?do=edit&amp;fieldname=global&amp;phraseid=' . $notice['html_phraseid'] . '" target="translate">' . $vbphrase['translations'] . '</a></div>' : ''), 'html', $notice['html'], 8, 60, true, false);

	print_input_row($vbphrase['display_order'], 'displayorder', $notice['displayorder'], 0, 10);
	print_yes_no_row($vbphrase['active_gcpglobal'] . '<dfn>' . $vbphrase['notice_active_description'] . '</dfn>', 'active', $notice['active']);
	print_yes_no_row($vbphrase['persistent'] . '<dfn>' . $vbphrase['persistent_description'] . '</dfn>', 'persistent', $notice['persistent']);
	print_yes_no_row($vbphrase['dismissible'], 'dismissible', $notice['dismissible']);
	print_description_row('<strong>' . $vbphrase['display_notice_if_elipsis'] . '</strong>', false, 2, 'tcat', '', 'criteria');

	if ($display_active_criteria_first)
	{
		function print_notice_criterion($criteria_option_id, &$criteria_options, $criteria_cache)
		{
			global $vbphrase;

			$criteria_option = $criteria_options["$criteria_option_id"];

			print_description_row(
				"<label><input type=\"checkbox\" id=\"cb_$criteria_option_id\" tabindex=\"1\" name=\"criteria[$criteria_option_id][active]\" title=\"$vbphrase[criterion_is_active]\" value=\"1\"" . (empty($criteria_cache["$criteria_option_id"]) ? '' : ' checked="checked"') . " />" .
				"<span id=\"span_$criteria_option_id\">" . construct_phrase($vbphrase[$criteria_option_id . '_criteria'], $criteria_option[0], $criteria_option[1], $criteria_option[2]) . '</span></label>'
			);

			unset($criteria_options["$criteria_option_id"]);
		}

		foreach (array_keys($criteria_cache) AS $id)
		{
			print_notice_criterion($id, $criteria_options, $criteria_cache);
		}
		foreach ($criteria_options AS $id => $criteria_option)
		{
			print_notice_criterion($id, $criteria_options, $criteria_cache);
		}
	}
	else
	{
		foreach ($criteria_options AS $criteria_option_id => $criteria_option)
		{
			// the criteria options can't trigger the checkbox to change, we need to break out of the label
			$criteria_text = '<label>' . construct_phrase($vbphrase[$criteria_option_id . '_criteria'],
				"</label>$criteria_option[0]<label>",
				"</label>$criteria_option[1]<label>",
				"</label>$criteria_option[2]<label>"
			) . '</label>';

			$criteria_text = str_replace('<label>', "<label for=\"cb_$criteria_option_id\">", $criteria_text);

			print_description_row(
				"<input type=\"checkbox\" id=\"cb_$criteria_option_id\" tabindex=\"1\" name=\"criteria[$criteria_option_id][active]\" title=\"$vbphrase[criterion_is_active]\" value=\"1\"" . (empty($criteria_cache["$criteria_option_id"]) ? '' : ' checked="checked"') . " />" .
				"<span id=\"span_$criteria_option_id\">$criteria_text</span>"
			);
		}
	}

	print_submit_row();


	// should we do the snazzy criteria disabling Javascript?
	if (!defined('NOTICE_CRITERIA_JS') OR NOTICE_CRITERIA_JS == true)
	{
	?>
	<!-- javascript to handle disabling elements for IE niceness -->
	<script type="text/javascript">
	<!--
	function init_checkboxes()
	{
		for (var i = 0; i < checkboxes.length; i++)
		{
			set_disabled(checkboxes[i]);
		}
	}

	function set_disabled_event(e)
	{
		set_disabled(this, true);
	}

	function set_disabled(element, focus_controls)
	{
		var span = YAHOO.util.Dom.get("span_" + element.id.substr(3));
		if (!span)
		{
			return;
		}
		if (element.checked)
		{
			YAHOO.util.Dom.removeClass(span, 'notices_disabled');
		}
		else
		{
			YAHOO.util.Dom.addClass(span, 'notices_disabled');
		}

		span.disabled = !element.checked;

		if (focus_controls && element.checked)
		{
			var inputs = span.getElementsByTagName("input");
			if (inputs.length > 0)
			{
				inputs[0].select();
				return;
			}

			var selects = span.getElementsByTagName("select");
			if (selects.length > 0)
			{
				selects[0].focus();
				return;
			}

			var textareas = span.getElementsByTagName("textarea");
			if (textareas.length > 0)
			{
				textareas[0].select();
				return;
			}
		}
	}

	function handle_reset()
	{
		setTimeout("init_checkboxes()", 100);
	}

	var checkboxes = new Array();
	var inputs = document.getElementsByTagName("input");
	for (var i = 0; i < inputs.length; i++)
	{
		if (inputs[i].type == "checkbox" && inputs[i].name.substr(0, String("criteria").length) == "criteria")
		{
			YAHOO.util.Event.on(inputs[i], "click", set_disabled_event);
			checkboxes.push(inputs[i]);
		}
	}

	YAHOO.util.Event.on("cpform", "reset", handle_reset);

	YAHOO.util.Event.addListener(window, 'load', init_checkboxes);
	init_checkboxes();
	//-->
	</script>
	<?php
	}
}

// #############################################################################
// quick update of active and display order fields
if ($_POST['do'] == 'quickupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'active'            => vB_Cleaner::TYPE_ARRAY_BOOL,
		'persistent'        => vB_Cleaner::TYPE_ARRAY_BOOL,
		'dismissible'		=> vB_Cleaner::TYPE_ARRAY_BOOL,
		'displayorder'      => vB_Cleaner::TYPE_ARRAY_UINT,
		'displayorderswap'  => vB_Cleaner::CONVERT_KEYS
	));

	//echo '<pre>'; print_r($vbulletin->GPC); echo '</pre>'; exit;

	$changes = false;
	$update_ids = '0';
	$update_active = '';
	$update_persistent = '';
	$update_dismissible = '';
	$update_displayorder = '';
	$notices_dispord = array();
	$notices_undismiss = '0';

	$notices_result = $assertor->getRows('vBForum:notice');
	$changed = $assertor->assertQuery('vBForum:noticeQuickUpdate', array(
		'notice' => $notices_result, 'active' => $vbulletin->GPC['active'], 'persistent' => $vbulletin->GPC['persistent'],
		'dismissible' => $vbulletin->GPC['dismissible'], 'displayorder' => $vbulletin->GPC['displayorder']
	));

	if (intval($changed))
	{
		$changes = true;
	}

	// handle swapping
	if (!empty($vbulletin->GPC['displayorderswap']))
	{
		list($orig_noticeid, $swap_direction) = explode(',', $vbulletin->GPC['displayorderswap'][0]);

		if (isset($vbulletin->GPC['displayorder']["$orig_noticeid"]))
		{
			$notice_orig = array(
				'noticeid'     => $orig_noticeid,
				'displayorder' => $vbulletin->GPC['displayorder']["$orig_noticeid"]
			);

			$sort = array('field' => array('displayorder', 'title'));
			$queryConditions = array();
			switch ($swap_direction)
			{
				case 'lower':
				{
					$comp = '<';
					$queryConditions[vB_dB_Query::CONDITIONS_KEY][] = array('field' => 'displayorder', 'value' => $notice_orig[displayorder], vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_LT);
					$sort['direction'] = array(vB_dB_Query::SORT_DESC, vB_dB_Query::SORT_ASC);
					break;
				}
				case 'higher':
				{
					$comp = '>';
					$queryConditions[vB_dB_Query::CONDITIONS_KEY][] = array('field' => 'displayorder', 'value' => $notice_orig[displayorder], vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_GT);
					$sort['direction'] = array(vB_dB_Query::SORT_ASC, vB_dB_Query::SORT_ASC);
					break;
				}
				default:
				{
					$comp = false;
					$sort = false;
				}
			}

			if ($comp AND $sort AND $notice_swap = $assertor->getRow('vBForum:notice', $queryConditions, $sort))
			{
				$assertor->assertQuery('vBForum:doNoticeSwap', array(
					'orig_noticeid' => $notice_orig['noticeid'],
					'swap_noticeid' => $notice_swap['noticeid'],
					'orig_displayorder' => $notice_orig['displayorder'],
					'swap_displayorder' => $notice_swap['displayorder']
				));

				// tell the datastore to update
				$changes = true;
			}
		}
	}

	//update the datastore notice cache
	if ($changes)
	{
		build_notice_datastore();
	}

	$_REQUEST['do'] = 'modify';
}

// #############################################################################
// list existing notices
if ($_REQUEST['do'] == 'modify')
{
	print_form_header('notice', 'quickupdate');
	print_column_style_code(array('width:100%', 'white-space:nowrap'));
	print_table_header($vbphrase['notices_manager']);

	$notice_result = $assertor->getRows('vBForum:notice', array(), array('displayorder', 'title'));
	$notice_count = count($notice_result);

	if ($notice_count)
	{
		print_description_row('<label><input type="checkbox" id="allbox" checked="checked" />' . $vbphrase['toggle_active_status_for_all'] . '</label><input type="image" value="" src="' . vB::getDatastore()->getOption('bburl') . '/' . $vbulletin->options['cleargifurl'] . '" name="normalsubmit" />', false, 2, 'thead checkbox-in-thead');
		foreach ($notice_result AS $notice)
		{
			print_label_row(
				'<a href="notice.php?' . vB::getCurrentSession()->get('sessionurl') . 'do=edit&amp;noticeid=' . $notice['noticeid'] . '" title="' . $vbphrase['edit_notice'] . '">' . $notice['title'] . '</a>',
				'<div style="white-space:nowrap">' .
				'<label class="smallfont"><input type="checkbox" name="active[' . $notice['noticeid'] . ']" value="1"' . ($notice['active'] ? ' checked="checked"' : '') . ' />' . $vbphrase['active_gcpglobal'] . '</label> ' .
				'<label class="smallfont"><input type="checkbox" name="persistent[' . $notice['noticeid'] . ']" value="1"' . ($notice['persistent'] ? ' checked="checked"' : '') . ' />' . $vbphrase['persistent'] . '</label> ' .
				'<label class="smallfont"><input type="checkbox" name="dismissible[' . $notice['noticeid'] . ']" value="1"' . ($notice['dismissible'] ? ' checked="checked"' : '') . ' />' . $vbphrase['dismissible'] . '</label> &nbsp; ' .
				'<input type="image" src="' . $vbulletin->options['bburl'] . '/cpstyles/' . $vbulletin->options['cpstylefolder'] . '/move_down.gif" name="displayorderswap[' . $notice['noticeid'] . ',higher]" />' .
				'<input type="text" name="displayorder[' . $notice['noticeid'] . ']" value="' . $notice['displayorder'] . '" class="bginput" size="4" title="' . $vbphrase['display_order'] . '" style="text-align:' . vB_Template_Runtime::fetchStyleVar('right') . '" />' .
				'<input type="image" src="' . $vbulletin->options['bburl'] . '/cpstyles/' . $vbulletin->options['cpstylefolder'] . '/move_up.gif" name="displayorderswap[' . $notice['noticeid'] . ',lower]" />' .
				construct_link_code($vbphrase['edit'], 'notice.php?' . vB::getCurrentSession()->get('sessionurl') . 'do=edit&amp;noticeid=' . $notice['noticeid']) .
				construct_link_code($vbphrase['delete'], 'notice.php?' . vB::getCurrentSession()->get('sessionurl') . 'do=delete&amp;noticeid=' . $notice['noticeid']) .
				'</div>'
			);
		}
	}

	print_label_row(
		'<input type="button" class="button" value="' . $vbphrase['add_new_notice'] . '" onclick="window.location=\'notice.php?' . vB::getCurrentSession()->get('sessionurl') . 'do=add\';" />',
		($notice_count ? '<div align="' . vB_Template_Runtime::fetchStyleVar('right') . '"><input type="submit" class="button" accesskey="s" value="' . $vbphrase['save'] . '" /> <input type="reset" class="button" accesskey="r" value="' . $vbphrase['reset'] . '" /></div>' : '&nbsp;'),
		'tfoot'
	);
	print_table_footer();

	?>
	<script type="text/javascript">
	<!--
	function toggle_all_active(e)
	{
		for (var i = 0; i < this.form.elements.length; i++)
		{
			if (this.form.elements[i].type == "checkbox" && this.form.elements[i].name.substr(0, 6) == "active")
			{
				this.form.elements[i].checked = this.checked;
			}
		}
	}

	YAHOO.util.Event.on("allbox", "click", toggle_all_active);
	//-->
	</script>
	<?php
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>
