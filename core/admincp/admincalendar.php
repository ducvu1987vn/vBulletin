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
global $phrasegroups, $specialtemplates;
global 	$_CALENDAROPTIONS,
		$_CALENDARHOLIDAYS,
		$vbphrase,
		$months,
		$days,
		$cmodcache,
		$period;
$phrasegroups = array('calendar', 'cppermission', 'holiday');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/functions_calendar.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadmincalendars'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################

$vbulletin->input->clean_array_gpc('r', array(
	'moderatorid' 	=> vB_Cleaner::TYPE_INT,
	'calendarid'	=> vB_Cleaner::TYPE_INT
));


log_admin_action(iif($vbulletin->GPC['moderatorid'] != 0, " moderator id = ".$vbulletin->GPC['moderatorid'],
					iif($vbulletin->GPC['calendarid'] != 0, "calendar id = ".$vbulletin->GPC['calendarid'], '')));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$monthsarray = array();
foreach ($months AS $index => $month)
{
	$monthsarray["$index"] = $vbphrase["$month"];
}

$daysarray = array();
foreach ($days AS $index => $day)
{
	$daysarray["$index"] = $vbphrase["$day"];
}

$periodarray = array();
foreach ($period AS $index => $p)
{
	$periodarray["$index"] = $vbphrase["$p"];
}

print_cp_header($vbphrase['calendar_manager_gcalendar']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start Add Custom Calendar Field #######################

if ($_REQUEST['do'] == 'addcustom')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'calendarcustomfieldid' => vB_Cleaner::TYPE_INT,
		'calendarid'            => vB_Cleaner::TYPE_INT
	));

	if ($vbulletin->GPC['calendarcustomfieldid'])
	{ // edit
		$fieldinfo = $vbulletin->db->query_first("
			SELECT * FROM " . TABLE_PREFIX . "calendarcustomfield
			WHERE calendarcustomfieldid = " . $vbulletin->GPC['calendarcustomfieldid']
		);
		if (!empty($fieldinfo['options']))
		{
			$fieldinfo['options'] = implode("\n", unserialize($fieldinfo['options']));
		}
		$action = construct_phrase($vbphrase['x_y_id_z'], $vbphrase['custom_field'], $fieldinfo['title'], $vbulletin->GPC['calendarcustomfieldid']);
	}
	else if ($vbulletin->GPC['calendarid'])
	{ // Add new
		$fieldinfo = array('length' => 25);
		$action = $vbphrase['add_new_custom_field'];
	}
	else
	{
		print_stop_message2('must_save_calendar');
	}

	print_form_header('admincalendar', 'doaddcustom');
	construct_hidden_code('calendarid', $vbulletin->GPC['calendarid']);
	construct_hidden_code('calendarcustomfieldid', $vbulletin->GPC['calendarcustomfieldid']);
	print_table_header($action);
	print_input_row($vbphrase['title'], 'title', $fieldinfo['title']);
	print_textarea_row($vbphrase['description_gcpglobal'], 'description', $fieldinfo['description']);
	print_textarea_row(construct_phrase($vbphrase['x_enter_the_options_that_the_user_can_choose_from'], $vbphrase['options']), 'options', $fieldinfo['options']);
	print_yes_no_row($vbphrase['allow_user_to_input_their_own_value_for_this_custom_field'], 'allowentry', $fieldinfo['allowentry']);
	print_input_row($vbphrase['max_length_of_allowed_user_input'], 'length', $fieldinfo['length'], 1, 5);
	print_yes_no_row($vbphrase['field_required'], 'required', $fieldinfo['required']);
	print_submit_row($vbphrase['save']);
}


// ###################### Do Add Custom Calendar Field #######################
if ($_POST['do'] == 'doaddcustom')
{
	// NOTE: working
	$vbulletin->input->clean_array_gpc('p', array(
		'title'                 => vB_Cleaner::TYPE_NOHTML,
		'options'               => vB_Cleaner::TYPE_STR,
		'description'           => vB_Cleaner::TYPE_STR,
		'allowentry'            => vB_Cleaner::TYPE_INT,
		'required'              => vB_Cleaner::TYPE_INT,
		'calendarcustomfieldid' => vB_Cleaner::TYPE_INT,
		'calendarid'            => vB_Cleaner::TYPE_INT,
		'length'                => vB_Cleaner::TYPE_INT
	));

	if (empty($vbulletin->GPC['title']))
	{
		print_stop_message2('invalid_custom_field_specified');
	}
	else if (empty($vbulletin->GPC['options']) AND !$vbulletin->GPC['allowentry'])
	{
		print_stop_message2('must_specify_field_option');
	}
	if (!empty($vbulletin->GPC['options']))
	{
		$optionsarray = explode("\n", htmlspecialchars_uni($vbulletin->GPC['options']));
		$temp = array();
		array_unshift($optionsarray, 0);
		unset($optionsarray[0]);
		foreach ($optionsarray AS $index => $value)
		{
			$optionsarray["$index"] = trim($value);
		}
		$vbulletin->GPC['options'] = serialize($optionsarray);
	}

	if ($vbulletin->GPC['calendarcustomfieldid'])
	{
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "calendarcustomfield
			SET title = '" . $vbulletin->db->escape_string($vbulletin->GPC['title']) . "',
				options = '" . $vbulletin->db->escape_string($vbulletin->GPC['options']) . "',
				allowentry = " . $vbulletin->GPC['allowentry'] . ",
				required = " . $vbulletin->GPC['required'] . ",
				length = " . $vbulletin->GPC['length'] . ",
				description = '" . $vbulletin->db->escape_string($vbulletin->GPC['description']) . "'
			WHERE calendarcustomfieldid = " . $vbulletin->GPC['calendarcustomfieldid']
		);
	}
	else if ($vbulletin->GPC['calendarid'])
	{ // Add new Entry
		/*insert query*/
		$vbulletin->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "calendarcustomfield
			(
				calendarid,
				title,
				options,
				allowentry,
				required,
				length,
				description
			)
			VALUES
			(" .
				$vbulletin->GPC['calendarid'] .", '" .
				$vbulletin->db->escape_string($vbulletin->GPC['title']) . "', '" .
				$vbulletin->db->escape_string($vbulletin->GPC['options']) . "', " .
				$vbulletin->GPC['allowentry'] . ", " .
				$vbulletin->GPC['required'] . ", " .
				$vbulletin->GPC['length'] . ", '" .
				$vbulletin->db->escape_string($vbulletin->GPC['description']) . "'
			)
		");
	}

	print_stop_message2(array('saved_custom_field_x_successfully',  $vbulletin->GPC['title']),"admincalendar", array('do'=>'edit', 'c' => $vbulletin->GPC['calendarid']));
}


// ###################### Remove Custom Calendar Field #######################
if ($_POST['do'] == 'killcustom')
{
	$vbulletin->input->clean_array_gpc('p', array('calendarcustomfieldid' => vB_Cleaner::TYPE_UINT ));

	// $calendarid used to pass to CP_REDIRECT, not a GPC at this point though unset to be sure after pass
	$calendarid = $vbulletin->db->query_first("
		SELECT calendarid FROM " . TABLE_PREFIX . "calendarcustomfield
		WHERE calendarcustomfieldid = " . $vbulletin->GPC['calendarcustomfieldid']
	);
	$vbulletin->db->query_write("
		DELETE FROM " . TABLE_PREFIX . "calendarcustomfield
		WHERE calendarcustomfieldid = " . $vbulletin->GPC['calendarcustomfieldid']
	);

	print_stop_message2('deleted_custom_field_successfully', "admincalendar", array('do'=>'edit','c'=>$calendarid['calendarid']));
	unset($calendarid);
}

// ###################### Start add / edit Calendar #######################
if ($_REQUEST['do'] == 'add' or $_REQUEST['do'] == 'edit')
{
	print_form_header('admincalendar', 'update');

	$vbulletin->input->clean_array_gpc('r', array('calendarid' => vB_Cleaner::TYPE_INT));

	$exampledaterange = (date('Y') - 3) . '-' . (date('Y') + 3);

	if ($_REQUEST['do'] == 'add')
	{
		// need to set default yes permissions!
		$calendar = array(
			'active'        => 1,
			'allowbbcode'   => 1,
			'allowimgcode'  => 1,
			'allowsmilies'  => 1,
			'startofweek'   => 1,
			'showweekends'  => 1,
			'cutoff'        => 40,
			'eventcount'    => 4,
			'birthdaycount' => 4,
			'daterange'     => $exampledaterange,
			'usetimes'      => 1,
			'usetranstime'  => 1,
			'showupcoming'  => 1,
		);

		$maxdisplayorder = $vbulletin->db->query_first("
			SELECT MAX(displayorder) AS displayorder
			FROM " . TABLE_PREFIX . "calendar
		");
		$calendar['displayorder'] = $maxdisplayorder['displayorder'] + 1;

		print_table_header($vbphrase['add_new_calendar_gcalendar']);
	}
	else
	{
		$calendar = $vbulletin->db->query_first("SELECT * FROM " . TABLE_PREFIX . "calendar WHERE calendarid = " . $vbulletin->GPC['calendarid']);
		construct_hidden_code('calendarid', $vbulletin->GPC['calendarid']);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['calendar'], $calendar['title'], $calendar['calendarid']));

		$calendar['daterange'] = $calendar['startyear'] . '-' . $calendar['endyear'];

		$customfields = $vbulletin->db->query_read("
			SELECT title, calendarcustomfieldid
			FROM " . TABLE_PREFIX . "calendarcustomfield
			WHERE calendarid = " . $vbulletin->GPC['calendarid']
		);
		$fieldcount = $vbulletin->db->num_rows($customfields);

		$getoptions = convert_bits_to_array($calendar['options'], $_CALENDAROPTIONS);
		$calendar = array_merge($calendar, $getoptions);
		$geteaster = convert_bits_to_array($calendar['holidays'], $_CALENDARHOLIDAYS);
		$calendar = array_merge($calendar, $geteaster);

		if (!empty($calendar['neweventemail']))
		{
			$calendar['neweventemail'] = @unserialize($calendar['neweventemail']);
			$calendar['neweventemail'] =  (!$calendar['neweventemail'] ? '' : implode("\n", $calendar['neweventemail']));
		}
	}

	print_input_row($vbphrase['title'], 'calendar[title]', $calendar['title']);
	print_input_row("$vbphrase[display_order] <dfn>$vbphrase[zero_equals_no_display]</dfn>", 'calendar[displayorder]', $calendar['displayorder'], 1, 5);

	print_table_header($vbphrase['custom_fields'] . '&nbsp;&nbsp;&nbsp;' . construct_link_code($vbphrase['add_new_custom_field'], "admincalendar.php?" . vB::getCurrentSession()->get('sessionurl') . "c=" . $vbulletin->GPC['calendarid'] . "&do=addcustom"));
	if ($fieldcount > 0)
	{
		while ($field = $vbulletin->db->fetch_array($customfields))
		{
			print_label_row($field['title'], construct_link_code($vbphrase['modify'], "admincalendar.php?" . vB::getCurrentSession()->get('sessionurl') . "do=addcustom&c=" . $vbulletin->GPC['calendarid'] . "&calendarcustomfieldid=$field[calendarcustomfieldid]") . ' ' . construct_link_code($vbphrase['delete'], "admincalendar.php?" . vB::getCurrentSession()->get('sessionurl') . "do=deletecustom&c=" . $vbulletin->GPC['calendarid'] . "&calendarcustomfieldid=$field[calendarcustomfieldid]"), '', 'top', 'customfields');
		}
	}

	print_table_header($vbphrase['moderation_options_gcalendar']);
	print_textarea_row($vbphrase['emails_to_notify_when_event'], 'calendar[neweventemail]', $calendar['neweventemail']);
	print_yes_no_row($vbphrase['moderate_events_gcalendar'] . ' <dfn>(' . $vbphrase['require_moderator_validation_before_new_events_are_displayed'] . ')</dfn>', 'calendar[moderatenew]', $calendar['moderatenew']);
	print_table_header($vbphrase['options']);

	print_input_row(construct_phrase($vbphrase['date_range_dfn'], $exampledaterange), 'calendar[daterange]', $calendar['daterange']);
	print_select_row($vbphrase['default_view'], 'default', array(0 => $vbphrase['monthly'], $vbphrase['weekly_gcalendar'], $vbphrase['yearly']), (!empty($calendar['weekly'])) ? 1 : ((!empty($calendar['yearly'])) ? 2 : 0));
	print_select_row($vbphrase['start_of_week'], 'calendar[startofweek]', array(1 => $vbphrase['sunday'], $vbphrase['monday'], $vbphrase['tuesday'], $vbphrase['wednesday'], $vbphrase['thursday'], $vbphrase['friday'], $vbphrase['saturday']), $calendar['startofweek']);
	print_input_row($vbphrase['event_title_cutoff'], 'calendar[cutoff]', $calendar['cutoff'], 1, 5);
	print_input_row($vbphrase['event_count_max_events_per_day'], 'calendar[eventcount]', $calendar['eventcount'], 1, 5);
	print_input_row($vbphrase['birthday_count_max_birthdays_per_day'], 'calendar[birthdaycount]', $calendar['birthdaycount'], 1, 5);

	print_table_header($vbphrase['enable_disable_features']);
	$endtable = 0;
	foreach ($_CALENDARHOLIDAYS AS $holiday => $value)
	{
		$holidaytext .= iif(!$endtable, "<tr>\n");
		$checked = iif($calendar["$holiday"], 'checked="checked"');
		$holidaytext .= "<td><input type=\"checkbox\" name=\"holidays[$holiday]\" value=\"1\" $checked />$vbphrase[$holiday]</td>\n";
		$holidaytext .= iif($endtable, "</tr>\n");
		$endtable = iif($endtable, 0, 1);
	}
	print_label_row($vbphrase['show_easter_holidays_on_this_calendar'], '<table cellspacing="2" cellpadding="0" border="0">' . $holidaytext . '</tr></table>', '', 'top', 'holidays');

	print_yes_no_row($vbphrase['show_weekend'], 'options[showweekends]', $calendar['showweekends']);
	print_yes_no_row($vbphrase['show_upcoming_events_from_this_calendar'], 'options[showupcoming]', $calendar['showupcoming']);
	print_yes_no_row($vbphrase['allow_html'], 'options[allowhtml]', $calendar['allowhtml']);
	print_yes_no_row($vbphrase['allow_bbcode'], 'options[allowbbcode]', $calendar['allowbbcode']);
	print_yes_no_row($vbphrase['allow_img_code'], 'options[allowimgcode]', $calendar['allowimgcode']);
	print_yes_no_row($vbphrase['allow_smilies'], 'options[allowsmilies]', $calendar['allowsmilies']);

	print_submit_row($vbphrase['save']);
}

// ###################### Start Delete Custom Calendar Field #######################
if ($_REQUEST['do'] == 'deletecustom')
{
	$vbulletin->input->clean_array_gpc('r', array('calendarcustomfieldid' => vB_Cleaner::TYPE_INT));

	print_delete_confirmation('calendarcustomfield', $vbulletin->GPC['calendarcustomfieldid'], 'admincalendar', 'killcustom', 'custom_calendar_field');
}

// ###################### Start insert/update #######################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'calendarid'  => vB_Cleaner::TYPE_INT,
		'calendar'	  => vB_Cleaner::TYPE_ARRAY,
		'default'     => vB_Cleaner::TYPE_UINT,
		'options'	  => vB_Cleaner::TYPE_ARRAY_BOOL,
		'holidays'	  => vB_Cleaner::TYPE_ARRAY_BOOL,
	));

	require_once(DIR . '/includes/functions_misc.php');
	switch($vbulletin->GPC['default'])
	{
		case 1:
			$vbulletin->GPC['options']['weekly'] = 1;
			break;
		case 2:
			$vbulletin->GPC['options']['yearly'] = 1;
			break;
	}
	$vbulletin->GPC['calendar']['options'] = convert_array_to_bits($vbulletin->GPC['options'], $_CALENDAROPTIONS);
	$vbulletin->GPC['calendar']['holidays'] = convert_array_to_bits($vbulletin->GPC['holidays'], $_CALENDARHOLIDAYS);

	$email = array();
	$emails = preg_split('#\s+|,#s', $vbulletin->GPC['calendar']['neweventemail'], -1, PREG_SPLIT_NO_EMPTY);
	foreach ($emails AS $index => $value)
	{
		$value = trim($value);
		if (!empty($value))
		{
			$email[] = $value;
		}
	}
	$vbulletin->GPC['options'] = serialize($optionsarray);
	$vbulletin->GPC['calendar']['neweventemail'] = serialize($email);

	$daterange = explode('-', $vbulletin->GPC['calendar']['daterange']);
	$vbulletin->GPC['calendar']['startyear'] = intval($daterange[0]);
	$vbulletin->GPC['calendar']['endyear'] = intval($daterange[1]);
	unset($vbulletin->GPC['calendar']['daterange']);
	if (!$vbulletin->GPC['calendar']['startyear'] OR
		$vbulletin->GPC['calendar']['startyear'] < 1970 OR
		$vbulletin->GPC['calendar']['endyear'] > 2037 OR
		!$vbulletin->GPC['calendar']['endyear'] OR
		$vbulletin->GPC['calendar']['startyear'] > $vbulletin->GPC['calendar']['endyear'])
	{
		print_stop_message2('invalid_date_range_specified');
	}

	if ($vbulletin->GPC['calendarid'])
	{
		$vbulletin->db->query_write(fetch_query_sql($vbulletin->GPC['calendar'], 'calendar', "WHERE calendarid=" . $vbulletin->GPC['calendarid']));
		build_events();
		print_stop_message2(array('saved_calendar_x_successfully',  $vbulletin->GPC['calendar']['title']), 'admincalendar', array('do'=>'modify'));
	}
	else
	{
		$vbulletin->db->query_write(fetch_query_sql($vbulletin->GPC['calendar'], 'calendar'));
		build_events();
		print_stop_message2(array('saved_calendar_x_successfully',  $vbulletin->GPC['calendar']['title']), 'admincalendar', array('do'=>'modify'));
	}

}

// ###################### Start Modify Calendar #######################
if ($_REQUEST['do'] == 'modify')
{

	// a little javascript for the options menus
	?>
	<script type="text/javascript">
	function js_calendar_jump(calendarinfo)
	{
		action = eval("document.cpform.c" + calendarinfo + ".options[document.cpform.c" + calendarinfo + ".selectedIndex].value");
		if (action != '')
		{
			switch (action)
			{
				case 'edit':
					page = "admincalendar.php?do=edit&c=";
					break;
				case 'view':
					page = "../calendar.php?c=";
					break;
				case 'remove':
					page = "admincalendar.php?do=remove&c=";
					break;
				case 'perms':
					page = "calendarpermission.php?do=modify&devnull=";
					break;
			}
			document.cpform.reset();
			jumptopage = page + calendarinfo + "&s=<?php echo vB::getCurrentSession()->get('sessionhash'); ?>";
			if (action=='perms')
			{
				window.location = jumptopage + '#calendar' + calendarinfo;
			}
			else
			{
				window.location = jumptopage;
			}
		}
		else
		{
			alert('<?php echo addslashes_js($vbphrase['invalid_action_specified_gcpglobal']); ?>');
		}
	}
	function js_moderator_jump(calendarinfo)
	{
		modinfo = eval("document.cpform.m" + calendarinfo + ".options[document.cpform.m" + calendarinfo + ".selectedIndex].value");
		document.cpform.reset();
		switch (modinfo)
		{
			case 'Add':
				window.location = "admincalendar.php?s=<?php echo vB::getCurrentSession()->get('sessionhash'); ?>&do=addmod&c=" + calendarinfo;
				break;
			case '':
				return false;
				break;
			default:
				window.location = "admincalendar.php?s=<?php echo vB::getCurrentSession()->get('sessionhash'); ?>&do=editmod&moderatorid=" + modinfo;
		}
	}
	</script>
	<?php

	cache_calendar_moderators();

	$calendaroptions = array(
		'edit' => $vbphrase['edit'],
		'view' => $vbphrase['view'],
		'remove' => $vbphrase['delete'],
		'perms' => $vbphrase['permissions']
	);

	print_form_header('admincalendar', 'doorder');
	print_table_header($vbphrase['calendar_manager_gcalendar'], 4);
	print_description_row($vbphrase['if_you_change_display_order'],0,4);
	print_cells_row(array('&nbsp; ' . $vbphrase['title'], $vbphrase['controls'], $vbphrase['order_by_gcpglobal'], $vbphrase['moderators']), 1, 'tcat');

	$calendars = $vbulletin->db->query_read("SELECT * FROM " . TABLE_PREFIX . "calendar ORDER BY displayorder");
	while ($calendar = $vbulletin->db->fetch_array($calendars))
	{
		$cell = array();
		$cell[] = "&nbsp;<b><a href=\"admincalendar.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&c=$calendar[calendarid]\">$calendar[title]</a></b>";
		$cell[] = "\n\t<select name=\"c$calendar[calendarid]\" onchange=\"js_calendar_jump($calendar[calendarid]);\" class=\"bginput\">\n" . construct_select_options($calendaroptions) . "\t</select>\n\t<input type=\"button\" class=\"button\" value=\"".$vbphrase['go']."\" onclick=\"js_calendar_jump($calendar[calendarid]);\" />\n\t";
		$cell[] = "<input type=\"text\" class=\"bginput\" name=\"order[$calendar[calendarid]]\" value=\"$calendar[displayorder]\" tabindex=\"1\" size=\"3\" title=\"" . $vbphrase['order_by_gcpglobal']  . 'ssss' ."\" />";

		$mods = array('no_value' => $vbphrase['moderators']. ' (' . sizeof($cmodcache["$calendar[calendarid]"]) . ')');
		if (is_array($cmodcache["$calendar[calendarid]"]))
		{
			foreach ($cmodcache["$calendar[calendarid]"] AS $moderator)
			{
				$mods["$moderator[calendarmoderatorid]"] = "&nbsp; &nbsp; $moderator[username]";
			}
		}
		$mods['Add'] = $vbphrase['add'];

		$cell[] = "\n\t<select name=\"m$calendar[calendarid]\" onchange=\"js_moderator_jump($calendar[calendarid]);\" class=\"bginput\">\n" . construct_select_options($mods) . "\t</select>\n\t<input type=\"button\" class=\"button\" value=\"" . $vbphrase['go'] . "\" onclick=\"js_moderator_jump($calendar[calendarid]);\" />\n\t";

		print_cells_row($cell);
	}

	print_table_footer(4, '<input type="submit" class="button" value="' . $vbphrase['save_display_order'] . '" accesskey="s" tabindex="1" />' . construct_button_code($vbphrase['add_new_calendar_gcalendar'], "admincalendar.php?" . vB::getCurrentSession()->get('sessionurl') . "do=add"));

}

// ###################### Start do order #######################
if ($_POST['do'] == 'doorder')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'order' => vB_Cleaner::TYPE_ARRAY_UINT
	));

	if (!empty($vbulletin->GPC['order']))
	{
		$calendars = $vbulletin->db->query_read("SELECT calendarid,displayorder FROM " . TABLE_PREFIX . "calendar");
		while ($calendar = $vbulletin->db->fetch_array($calendars))
		{
			if ($calendar['displayorder'] != $vbulletin->GPC['order']["$calendar[calendarid]"])
			{
				$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "calendar SET displayorder=" . $vbulletin->GPC['order']["$calendar[calendarid]"] . " WHERE calendarid=$calendar[calendarid]");
			}
		}
	}

	print_stop_message2('saved_display_order_successfully', 'admincalendar', array('do'=>'modify'));
}

// ###################### Start Remove #######################
if ($_REQUEST['do'] == 'remove')
{
	$vbulletin->input->clean_array_gpc('r', array('calendarid' 	=> vB_Cleaner::TYPE_INT));

	print_delete_confirmation('calendar', $vbulletin->GPC['calendarid'], 'admincalendar', 'kill', 'calendar', 0);
}

// ###################### Start Kill #######################

if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_array_gpc('p', array('calendarid' 	=> vB_Cleaner::TYPE_INT));

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "event WHERE calendarid = " . $vbulletin->GPC['calendarid']);
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "calendarpermission WHERE calendarid = " . $vbulletin->GPC['calendarid']);
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "calendarcustomfield WHERE calendarid = " . $vbulletin->GPC['calendarid']);
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "calendar WHERE calendarid = " . $vbulletin->GPC['calendarid']);

	print_stop_message2('deleted_calendar_successfully', 'admincalendar');

}

// ##################### Start Add/Edit Moderator ##########

if ($_REQUEST['do'] == 'addmod' or $_REQUEST['do'] == 'editmod')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'moderatorid'	=> vB_Cleaner::TYPE_INT,
		'calendarid'	=> vB_Cleaner::TYPE_INT
	));

	if (empty($vbulletin->GPC['moderatorid']))
	{
		// add moderator - set default values
		$calendarinfo = $vbulletin->db->query_first("SELECT calendarid, title AS calendartitle FROM " . TABLE_PREFIX . "calendar WHERE calendarid = " . $vbulletin->GPC['calendarid']);
		$moderator = array(
			'caneditevents'     => 1,
			'candeleteevents'   => 1,
			'canmoderateevents' => 1,
			'canviewips'        => 1,
			'canmoveevents'     => 1,
			'calendarid'        => $calendarinfo['calendarid'],
			'calendartitle'     => $calendarinfo['calendartitle']
		);
		print_form_header('admincalendar', 'updatemod');
		print_table_header(construct_phrase($vbphrase['add_new_moderator_to_calendar_x'], $calendarinfo['calendartitle']));
	}
	else
	{
		// edit moderator - query moderator
		$moderator = $vbulletin->db->query_first("
			SELECT calendarmoderatorid, calendarmoderator.userid, calendarmoderator.calendarid, permissions, user.username, title AS calendartitle
			FROM " . TABLE_PREFIX . "calendarmoderator AS calendarmoderator
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = calendarmoderator.userid)
			LEFT JOIN " . TABLE_PREFIX . "calendar AS calendar ON (calendar.calendarid = calendarmoderator.calendarid)
			WHERE calendarmoderatorid = " . $vbulletin->GPC['moderatorid']
		);

		$perms = convert_bits_to_array($moderator['permissions'], $vbulletin->bf_misc_calmoderatorpermissions, 1);
		$moderator = array_merge($perms, $moderator);

		// delete link
		print_form_header('admincalendar', 'removemod');
		construct_hidden_code('moderatorid', $vbulletin->GPC['moderatorid']);
		print_table_header($vbphrase['if_you_would_like_to_remove_this_moderator_gcalendar'] . ' &nbsp; &nbsp; <input type="submit" class="button" value="' . $vbphrase['delete_moderator'] . '" style="font:bold 11px tahoma" />');
		print_table_footer();

		print_form_header('admincalendar', 'updatemod');
		construct_hidden_code('moderatorid', $vbulletin->GPC['moderatorid']);
		print_table_header(construct_phrase($vbphrase['edit_moderator_x_for_calendar_y'], $moderator['username'], $moderator['calendartitle']));
	}

	print_calendar_chooser($vbphrase['calendar'], 'moderator[calendarid]', $moderator['calendarid'], '');
	if (empty($vbulletin->GPC['moderatorid']))
	{
		print_input_row($vbphrase['moderator_username'], 'modusername', $moderator['username']);
	}
	else
	{
		print_label_row($vbphrase['moderator_username'], '<b>' . $moderator['username'] . '</b>');
	}

	print_table_header($vbphrase['calendar_permissions_gcalendar']);
	// post permissions
	print_yes_no_row($vbphrase['can_edit_events'], 'modperms[caneditevents]', $moderator['caneditevents']);
	print_yes_no_row($vbphrase['can_delete_events'], 'modperms[candeleteevents]', $moderator['candeleteevents']);
	print_yes_no_row($vbphrase['can_move_events'], 'modperms[canmoveevents]', $moderator['canmoveevents']);
	print_yes_no_row($vbphrase['can_moderate_events'], 'modperms[canmoderateevents]', $moderator['canmoderateevents']);
	print_yes_no_row($vbphrase['can_view_ip_addresses'], 'modperms[canviewips]', $moderator['canviewips']);

	print_submit_row(iif(!empty($vbulletin->GPC['moderatorid']), $vbphrase['update'], $vbphrase['save']));

}

// ###################### Start insert / update moderator #######################
if ($_POST['do'] == 'updatemod')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'modusername'		=> vB_Cleaner::TYPE_STR,
		'moderator'			=> vB_Cleaner::TYPE_ARRAY,
		'modperms'			=> vB_Cleaner::TYPE_ARRAY,
		'moderatorid'		=> vB_Cleaner::TYPE_UINT
	));

	if (!$vbulletin->GPC['moderatorid'])
	{
		$vbulletin->GPC['modusername'] = htmlspecialchars_uni($vbulletin->GPC['modusername']);

		$userinfo = $vbulletin->db->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "user
			WHERE username='" . $vbulletin->db->escape_string($vbulletin->GPC['modusername']) . "'
		");
	}
	else
	{
		$userinfo = $vbulletin->db->query_first("
			SELECT user.username, user.userid
			FROM " . TABLE_PREFIX . "calendarmoderator AS calendarmoderator
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (calendarmoderator.userid = user.userid)
			WHERE calendarmoderatorid = " . $vbulletin->GPC['moderatorid']
		);

		$vbulletin->GPC['modusername'] = $userinfo['username'];
	}

	$calendarinfo = $vbulletin->db->query_first("
		SELECT calendarid,title
		FROM " . TABLE_PREFIX . "calendar
		WHERE calendarid = " . intval($vbulletin->GPC['moderator']['calendarid'])
	);

	if ($calendarinfo['calendarid'] AND ($userinfo['userid'] OR $vbulletin->GPC['moderatorid']))
	{ // no errors

		require_once(DIR . '/includes/functions_misc.php');
		$vbulletin->GPC['moderator']['permissions'] = convert_array_to_bits($vbulletin->GPC['modperms'], $vbulletin->bf_misc_calmoderatorpermissions, 1);
		if ($vbulletin->GPC['moderatorid'])
		{ // update
			$vbulletin->db->query_write(fetch_query_sql($vbulletin->GPC['moderator'], 'calendarmoderator', "WHERE calendarmoderatorid=" . $vbulletin->GPC['moderatorid']));

			print_stop_message2(array('saved_moderator_x_successfully',  $vbulletin->GPC['modusername']), 'admincalendar');
		}
		else
		{ // insert
			$vbulletin->GPC['moderator']['userid'] = $userinfo['userid'];
			$vbulletin->db->query_write(fetch_query_sql($vbulletin->GPC['moderator'], 'calendarmoderator'));

			print_stop_message2(array('saved_moderator_x_successfully',  $vbulletin->GPC['modusername'], 'admincalendar'));
		}
	}
	else
	{ // error
		if (!$userinfo['userid'])
		{
			print_stop_message2('no_moderator_matched_your_query');
		}
		if (!$calendarinfo['calendarid'])
		{
			print_stop_message2('invalid_calendar_specified');
		}
	}
}

// ###################### Start Remove moderator #######################

if ($_REQUEST['do'] == 'removemod')
{
	$vbulletin->input->clean_array_gpc('r', array('moderatorid' => vB_Cleaner::TYPE_UINT));

	print_delete_confirmation('calendarmoderator', $vbulletin->GPC['moderatorid'], 'admincalendar', 'killmod', 'calendar_moderator');
}

// ###################### Start Kill moderator #######################

$vbulletin->input->clean_array_gpc('p', array('calendarmoderatorid' => vB_Cleaner::TYPE_UINT));

if ($_POST['do'] == 'killmod')
{
	$getuserid = $vbulletin->db->query_first("
		SELECT user.userid,usergroupid
		FROM " . TABLE_PREFIX . "calendarmoderator AS calendarmoderator
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid)
		WHERE calendarmoderatorid = " . $vbulletin->GPC['calendarmoderatorid']
	);
	if (!$getuserid)
	{
		print_stop_message2('user_no_longer_moderator');
	}
	else
	{
		$vbulletin->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "calendarmoderator
			WHERE calendarmoderatorid = " . $vbulletin->GPC['calendarmoderatorid']
		);

		print_stop_message2('deleted_moderator_successfully', 'admincalendar');
	}
}

// ##################### Holidays ###################################
if ($_REQUEST['do'] == 'modifyholiday')
{
	print_form_header('', '');
	print_table_header($vbphrase['holidays']);
	print_table_footer();

	$holidays = $vbulletin->db->query_write("SELECT * FROM " . TABLE_PREFIX . "holiday");

	?>
	<script type="text/javascript">
	function js_holiday_jump(holidayid, obj)
	{
		task = obj.options[obj.selectedIndex].value;
		switch (task)
		{
			case 'edit': window.location = "admincalendar.php?s=<?php echo vB::getCurrentSession()->get('sessionhash'); ?>&do=updateholiday&holidayid=" + holidayid; break;
			case 'kill': window.location = "admincalendar.php?s=<?php echo vB::getCurrentSession()->get('sessionhash'); ?>&do=removeholiday&holidayid=" + holidayid; break;
			default: return false; break;
		}
	}
	</script>
	<?php

	$options = array(
		'edit' => $vbphrase['edit'],
		'kill' => $vbphrase['delete'],
	);

	print_form_header('admincalendar', 'updateholiday');
	print_cells_row(array($vbphrase['title'], $vbphrase['recurring_option'], $vbphrase['controls']), 1);

	while ($holiday = $vbulletin->db->fetch_array($holidays))
	{
		$recuroptions = explode('|', $holiday['recuroption']);

		$cell = array();

		$cell[] = '<b>' . $vbphrase['holiday' . $holiday['holidayid'] . '_title'] . '</b>';
		if ($holiday['recurring'] == 6)
		{
			$cell[] = construct_phrase($vbphrase['every_x_y'], $monthsarray["$recuroptions[0]"], $recuroptions[1]);
		}
		else
		{
			$cell[] = construct_phrase($vbphrase['the_x_y_of_z'], $periodarray["$recuroptions[0]"], $daysarray["$recuroptions[1]"], $monthsarray["$recuroptions[2]"]);
		}

		$cell[] = "\n\t<select name=\"u$holiday[holidayid]\" onchange=\"js_holiday_jump($holiday[holidayid], this);\" class=\"bginput\">\n" . construct_select_options($options) . "\t</select>\n\t<input type=\"button\" class=\"button\" value=\"".$vbphrase['go']."\" onclick=\"js_holiday_jump($holiday[holidayid], this.form.u$holiday[holidayid]);\" />\n\t";
		print_cells_row($cell);
	}

	print_submit_row($vbphrase['add_new_holiday'], 0, 3);

}

// #####################Edit Hoiday###################################
if ($_REQUEST['do'] == 'updateholiday')
{
	$vbulletin->input->clean_array_gpc('r', array('holidayid' => vB_Cleaner::TYPE_UINT));

	print_form_header('admincalendar', 'saveholiday');
	if ($vbulletin->GPC['holidayid']) // Existing Holiday
	{
		$holidayinfo = $vbulletin->db->query_first("SELECT * FROM " . TABLE_PREFIX . "holiday WHERE holidayid = " . $vbulletin->GPC['holidayid']);
		construct_hidden_code('holidayid', $vbulletin->GPC['holidayid']);
		$options = explode('|', $holidayinfo['recuroption']);
		$checked = array($holidayinfo['recurring'] => 'checked="checked"');

		$title = 'holiday' . $holidayinfo['holidayid'] . '_title';
		$desc = 'holiday' . $holidayinfo['holidayid'] . '_desc';

		$phrases = $vbulletin->db->query_read("
			SELECT varname, text
			FROM " . TABLE_PREFIX . "phrase
			WHERE languageid = 0 AND
					fieldname = 'holiday' AND
					varname IN ('$title', '$desc')
		");
		while ($phrase = $vbulletin->db->fetch_array($phrases))
		{
			if ($phrase['varname'] == $title)
			{
				$holidayinfo['title'] = $phrase['text'];
			}
			else if ($phrase['varname'] == $desc)
			{
				$holidayinfo['description'] = $phrase['text'];
			}
		}
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['holiday'], $holidayinfo['title'], $holidayinfo['holidayid']));
	}
	else // New Holiday
	{
		$holidayinfo = array('allowsmilies' => 1);
		$checked = array(6 => 'checked="checked"');
		print_table_header($vbphrase['add_new_holiday']);
	}

	if ($holidayinfo['title'])
	{
		print_input_row($vbphrase['title'] . '<dfn>' . construct_link_code($vbphrase['translations'], "phrase.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&fieldname=holiday&varname=$title&t=1", 1)  . '</dfn>', 'title', $holidayinfo['title']);
	}
	else
	{
		print_input_row($vbphrase['title'], 'title');
	}
	if ($holidayinfo['description'])
	{
		print_textarea_row($vbphrase['description_gcpglobal'] . '<dfn>' . construct_link_code($vbphrase['translations'], "phrase.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&fieldname=holiday&varname=$desc&t=1", 1)  . '</dfn>', 'description', $holidayinfo['description']);
	}
	else
	{
		print_textarea_row($vbphrase['description_gcpglobal'], 'description');
	}

	print_label_row($vbphrase['recurring_option'],
		'<input type="radio" name="holidayinfo[recurring]" value="6" tabindex="1" ' . $checked[6] . '/>' .
		construct_phrase($vbphrase['every_x_y'], construct_month_select_html($options[0], 'month1'),  construct_day_select_html($options[1], 'day1')) . '
		<br /><input type="radio" name="holidayinfo[recurring]" value="7" tabindex="1" ' . $checked[7] . '/>' .
		construct_phrase($vbphrase['the_x_y_of_z'], '<select name="period" tabindex="1" class="bginput">' . construct_select_options($periodarray, $options[0]) . '</select>', '<select name="day2" tabindex="1" class="bginput">' . construct_select_options($daysarray, $options[1]) . '</select>', construct_month_select_html($options[2], 'month2')),
		'', 'top', 'recurring'
	);
	print_yes_no_row($vbphrase['allow_smilies'], 'holidayinfo[allowsmilies]', $holidayinfo['allowsmilies']);

	print_submit_row($vbphrase['save']);

}

// ################# Save or Create a Holiday ###################
if($_POST['do'] == 'saveholiday')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'holidayid'   => vB_Cleaner::TYPE_INT,
		'holidayinfo' => vB_Cleaner::TYPE_ARRAY,
		'month1'      => vB_Cleaner::TYPE_INT,
		'day1'        => vB_Cleaner::TYPE_INT,
		'month2'      => vB_Cleaner::TYPE_INT,
		'day2'        => vB_Cleaner::TYPE_INT,
		'period'      => vB_Cleaner::TYPE_INT,
		'title'       => vB_Cleaner::TYPE_STR,
		'description' => vB_Cleaner::TYPE_STR,
	));

	if (empty($vbulletin->GPC['title']))
	{
		print_stop_message2('please_complete_required_fields');
	}

	$vbulletin->GPC['holidayinfo']['recurring'] = intval($vbulletin->GPC['holidayinfo']['recurring']);
	$vbulletin->GPC['holidayinfo']['month1'] = intval($vbulletin->GPC['holidayinfo']['month1']);
	$vbulletin->GPC['holidayinfo']['month2'] = intval($vbulletin->GPC['holidayinfo']['month2']);
	$vbulletin->GPC['holidayinfo']['day1'] = intval($vbulletin->GPC['holidayinfo']['day1']);
	$vbulletin->GPC['holidayinfo']['day2'] = intval($vbulletin->GPC['holidayinfo']['day2']);
	$vbulletin->GPC['holidayinfo']['period'] = intval($vbulletin->GPC['holidayinfo']['period']);


	if ($vbulletin->GPC['holidayinfo']['recurring'] == 6)
	{
		$vbulletin->GPC['holidayinfo']['recuroption'] = $vbulletin->GPC['month1'] . '|' . $vbulletin->GPC['day1'];
	}
	else
	{
		$vbulletin->GPC['holidayinfo']['recuroption'] = $vbulletin->GPC['period'] . '|' . $vbulletin->GPC['day2'] . '|' . $vbulletin->GPC['month2'];
	}

	if (empty($vbulletin->GPC['holidayid']))
	{
		/*insert query*/
		$vbulletin->db->query_write("INSERT INTO " . TABLE_PREFIX . "holiday (allowsmilies) VALUES (1)");
		$vbulletin->GPC['holidayid'] = $vbulletin->db->insert_id();
	}

	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "holiday
		SET allowsmilies = " . $vbulletin->GPC['holidayinfo']['allowsmilies'] . ",
		recuroption = '" . $vbulletin->db->escape_string($vbulletin->GPC['holidayinfo']['recuroption']) . "',
		recurring = " . $vbulletin->GPC['holidayinfo']['recurring'] . "
		WHERE holidayid = " . $vbulletin->GPC['holidayid']
	);

	/*insert_query*/
	$vbulletin->db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "phrase
			(languageid, fieldname, varname, text, product, username, dateline, version)
		VALUES
			(
				0,
				'holiday',
				'holiday" . $vbulletin->GPC['holidayid'] . "_title',
				'" . $vbulletin->db->escape_string($vbulletin->GPC['title']) . "',
				'vbulletin',
				'" . $vbulletin->db->escape_string($vbulletin->userinfo['username']) . "',
				" . TIMENOW . ",
				'" . $vbulletin->db->escape_string($vbulletin->options['templateversion']) . "'
			),
			(
				0,
				'holiday',
				'holiday" . $vbulletin->GPC['holidayid'] . "_desc',
				'" . $vbulletin->db->escape_string($vbulletin->GPC['description']) . "',
				'vbulletin',
				'" . $vbulletin->db->escape_string($vbulletin->userinfo['username']) . "',
				" . TIMENOW . ",
				'" . $vbulletin->db->escape_string($vbulletin->options['templateversion']) . "'
			)
	");

	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language();

	require_once(DIR . '/includes/functions_databuild.php');
	build_events();

	print_stop_message2(array('saved_holiday_x_successfully',  $vbulletin->GPC['title']),'admincalendar', array('do'=>'modifyholiday'));
}

// ################# Delete a Holiday ###########################
if ($_REQUEST['do'] == 'removeholiday')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'holidayid' 	=> vB_Cleaner::TYPE_INT
	));

	print_form_header('admincalendar', 'doremoveholiday', 0, 1, '', '75%');
	construct_hidden_code('holidayid', $vbulletin->GPC['holidayid']);
	print_table_header(construct_phrase($vbphrase['confirm_deletion_of_holiday_x'], $vbphrase['holiday' . $vbulletin->GPC['holidayid'] . '_title']));
	print_description_row("
			<blockquote><br />
			".construct_phrase($vbphrase['are_you_sure_you_want_to_delete_the_holiday_called_x'], $vbphrase['holiday' . $vbulletin->GPC['holidayid'] . '_title'], $vbulletin->GPC['holidayid'])."
			<br /></blockquote>\n\t");
	print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);

}

// ################ Really Delete a Holiday ####################
if ($_POST['do'] == 'doremoveholiday')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'holidayid' 	=> vB_Cleaner::TYPE_INT
	));

	// delete phrases
	$vbulletin->db->query_write("
		DELETE FROM " . TABLE_PREFIX . "phrase
		WHERE fieldname = 'holiday' AND
				varname IN ('holiday" . $vbulletin->GPC['holidayid'] . "_title', 'holiday" . $vbulletin->GPC['holidayid'] . "_desc')
	");

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "holiday WHERE holidayid=" . $vbulletin->GPC['holidayid']);

	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language();

	require_once(DIR . '/includes/functions_databuild.php');
	build_events();

	print_stop_message2('deleted_holiday_successfully', 'admincalendar', array('do'=>'modifyholiday'));
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>
