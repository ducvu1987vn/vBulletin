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
$phrasegroups = array('cron');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (is_demo_mode() OR !can_administer('canadmincron'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'cronid' => vB_Cleaner::TYPE_INT
));
log_admin_action(iif($vbulletin->GPC['cronid'] != 0, 'cron id = ' . $vbulletin->GPC['cronid']));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vb5_config =& vB::getConfig();

print_cp_header($vbphrase['scheduled_task_manager_gcron']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ############## quick enabled/disabled status ################
if ($_POST['do'] == 'updateenabled')
{
	$vbulletin->input->clean_gpc('p', 'enabled', vB_Cleaner::TYPE_ARRAY_BOOL);
	$updates = array();

	//$crons_result = $vbulletin->db->query_read("SELECT varname, active FROM " . TABLE_PREFIX . "cron");
	$crons_result = vB::getDbAssertor()->assertQuery('cron');
	foreach ($crons_result as $cron)
	{
		$old = $cron['active'] ? 1 : 0;
		$new = $vbulletin->GPC['enabled']["$cron[varname]"] ? 1 : 0;

		if ($old != $new)
		{
			$updates["$cron[varname]"] = $new;
		}
	}

	if (!empty($updates))
	{
		vB::getDbAssertor()->assertQuery('updateCron', array('updates' => $updates));
	}
	$args = array();
	parse_str(vB::getCurrentSession()->get('sessionurl_js'),$args);
	$args['do'] = 'modify';
	print_cp_redirect2('cronadmin', $args);
}

// ###################### Start run cron #######################
if ($_REQUEST['do'] == 'runcron')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'cronid' => vB_Cleaner::TYPE_INT,
		'varname' => vB_Cleaner::TYPE_STR
	));

	$nextitem = null;

	if ($vbulletin->GPC['cronid'])
	{
		$nextitem = vB_Api::instanceInternal('cron')->fetchById($vbulletin->GPC['cronid']);
	}
	else if ($vbulletin->GPC['varname'])
	{
		$nextitem = vB_Api::instanceInternal('cron')->fetchByVarName($vbulletin->GPC['varname']);
	}

	if ($nextitem)
	{
		ignore_user_abort(1);
		@set_time_limit(0);

		echo "<p><b>" . $nextitem['varname'] . " </b></p>";
		require_once(DIR . '/includes/functions_cron.php');
		include_once(DIR . '/' . $nextitem['filename']);
		echo "<p>$vbphrase[done]</p>";

	}
	else
	{
		print_stop_message2('invalid_action_specified_gcpglobal');
	}
}

// ###################### Start edit #######################
if ($_REQUEST['do'] == 'edit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'cronid' => vB_Cleaner::TYPE_INT
	));

	print_form_header('cronadmin', 'update');
	if (!empty($vbulletin->GPC['cronid']))
	{
		$cron = vB_Api::instanceInternal('cron')->fetchById($vbulletin->GPC['cronid']);

		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['scheduled_task'], htmlspecialchars_uni($cron['title']), $cron['cronid']));
		construct_hidden_code('cronid' , $cron['cronid']);
		print_label_row($vbphrase['varname'], $cron['varname']);
	}
	else
	{
		$cron = array(
			'cronid'   => 0,
			'weekday'  => -1,
			'day'      => -1,
			'hour'     => -1,
			'minute'   => array (0 => -1),
			'filename' => './includes/cron/.php',
			'loglevel' => 0,
			'active'   => 1,
			'volatile' => ($vb5_config['Misc']['debug'] ? 1 : 0),
			'product'  => 'vbulletin'
		);
		print_table_header($vbphrase['add_new_scheduled_task_gcron']);
		print_input_row($vbphrase['varname'], 'varname');
	}

	$weekdays = array(-1 => '*', 0 => $vbphrase['sunday'], $vbphrase['monday'], $vbphrase['tuesday'], $vbphrase['wednesday'], $vbphrase['thursday'], $vbphrase['friday'], $vbphrase['saturday']);
	$hours = array(-1 => '*', 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23);
	$days = array(-1 => '*', 1 => 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31);
	$minutes = array(-1 => '*');
	for ($x = 0; $x < 60; $x++)
	{
		$minutes[] = $x;
	}

	if ($cron['cronid'])
	{
		$trans_link = "phrase.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&fieldname=cron&t=1&varname="; // has varname appended

		if (!$cron['volatile'] OR $vb5_config['Misc']['debug'])
		{
			// non volatile or in debug mode -- always editable (custom created)
			print_input_row($vbphrase['title'] . '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . $cron['titlevarname'], 1)  . '</dfn>', 'title', $cron['title']);
			print_textarea_row($vbphrase['description_gcpglobal'] . '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . $cron['descvarname'], 1)  . '</dfn>', 'description', $cron['description']);
			print_textarea_row($vbphrase['log_phrase'] . '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . $cron['logvarname'], 1)  . '</dfn>', 'logphrase', $cron['logphrase']);
		}
		else
		{
			print_label_row($vbphrase['title'] . '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . $cron['titlevarname'], 1)  . '</dfn>', htmlspecialchars_uni($cron['title']));
			print_label_row($vbphrase['description_gcpglobal'] . '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . $cron['descvarname'], 1)  . '</dfn>', htmlspecialchars_uni($cron['description']));
			print_label_row($vbphrase['log_phrase'] . '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . $cron['logvarname'], 1)  . '</dfn>', htmlspecialchars_uni($cron['logphrase']));
		}
	}
	else
	{
		print_input_row($vbphrase['title'], 'title');
		print_textarea_row($vbphrase['description_gcpglobal'], 'description');
		print_textarea_row($vbphrase['log_phrase'], 'logphrase');
	}

	print_select_row($vbphrase['day_of_week'], 'weekday', $weekdays, $cron['weekday']);
	print_select_row($vbphrase['day_of_month'], 'day', $days, $cron['day']);
	print_select_row($vbphrase['hour'], 'hour', $hours, $cron['hour']);

	$selects = '';
	for ($x = 0; $x < 6; $x++)
	{
		if ($x == 1)
		{
			$minutes = array(-2 => '-') + $minutes;
			unset($minutes[-1]);
		}
		if (!isset($cron['minute'][$x]))
		{
			$cron['minute'][$x] = -2;
		}
		$selects .= "<select name=\"minute[$x]\" tabindex=\"1\" class=\"bginput\">\n";
		$selects .= construct_select_options($minutes, $cron['minute'][$x]);
		$selects .= "</select>\n";
	}
	print_label_row($vbphrase['minute'], $selects, '', 'top', 'minute');
	print_yes_no_row($vbphrase['active_gcron'], 'active', $cron['active']);
	print_yes_no_row($vbphrase['log_entries'], 'loglevel', $cron['loglevel']);
	print_input_row($vbphrase['filename_gcpglobal'], 'filename', $cron['filename'], true, 35, 0, 'ltr');
	print_select_row($vbphrase['product'], 'product', fetch_product_list(), $cron['product']);
	if ($vb5_config['Misc']['debug'])
	{
		print_yes_no_row($vbphrase['vbulletin_default'], 'volatile', $cron['volatile']);
	}
	else
	{
		construct_hidden_code('volatile', $cron['volatile']);
	}
	print_submit_row($vbphrase['save']);
}

// ###################### Start do update #######################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'cronid'      => vB_Cleaner::TYPE_INT,
		'varname'     => vB_Cleaner::TYPE_STR,
		'filename'    => vB_Cleaner::TYPE_STR,
		'title'       => vB_Cleaner::TYPE_STR,
		'description' => vB_Cleaner::TYPE_STR,
		'logphrase'   => vB_Cleaner::TYPE_STR,
		'weekday'     => vB_Cleaner::TYPE_STR,
		'day'         => vB_Cleaner::TYPE_STR,
		'hour'        => vB_Cleaner::TYPE_STR,
		'minute'      => vB_Cleaner::TYPE_ARRAY,
		'active'      => vB_Cleaner::TYPE_INT,
		'loglevel'    => vB_Cleaner::TYPE_INT,
		'filename'    => vB_Cleaner::TYPE_STR,
		'product'     => vB_Cleaner::TYPE_STR,
		'volatile'    => vB_Cleaner::TYPE_INT
	));
	try
	{
		vB_Api::instanceInternal('cron')->save($vbulletin->GPC, $vbulletin->GPC['cronid']);
	}
	catch (vB_Exception_Api $e)
	{
		$errors = $e->get_errors();
		$errors = array_pop($errors);
		print_stop_message2($errors[0]);
	}
	print_stop_message2(array('saved_scheduled_task_x_successfully',  $vbulletin->GPC['title']), 'cronadmin', array('do'=>'modify'));
}

// ###################### Start Remove #######################
if ($_REQUEST['do'] == 'remove')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'cronid' 	=> vB_Cleaner::TYPE_INT
	));
	print_form_header('cronadmin', 'kill');
	construct_hidden_code('cronid', $vbulletin->GPC['cronid']);
	print_table_header($vbphrase['confirm_deletion_gcpglobal']);
	print_description_row($vbphrase['are_you_sure_you_want_to_delete_this_scheduled_task']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
}

// ###################### Start Kill #######################
if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'cronid' 	=> vB_Cleaner::TYPE_INT
	));

	vB_Api::instanceInternal('cron')->delete($vbulletin->GPC['cronid']);

	print_stop_message2('deleted_scheduled_task_successfully', 'cronadmin', array('do'=>'modify'));
}

// ###################### Start switchactive #######################
if ($_REQUEST['do'] == 'switchactive')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'cronid' 	=> vB_Cleaner::TYPE_INT
	));

	verify_cp_sessionhash();

	try
	{
		vB_Api::instanceInternal('cron')->switchActive($vbulletin->GPC['cronid']);
	}
	catch (vB_Exception_Api $e)
	{
		$errors = $e->get_errors();
		$errors = array_pop($errors);
		print_stop_message2($errors[0]);
	}

	print_stop_message2('enabled_disabled_scheduled_task_successfully', 'cronadmin', array('do'=>'modify'));
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{
	$phrase_names = array('min_abbr', 'hour_abbr', 'day_abbr', 'month_abbr', 'dow_acronym', 'title', 'next_time', 'controls', 'edit', 'disable', 'enable', 'delete', 'run_now', 'add_new_scheduled_task_gcron', 'go', 'n_a', 'add_new_scheduled_task_gcron', 'save_enabled_status', 'all_times_are_gmt_x_time_now_is_y');
	$crons = vB_Api::instanceInternal('cron')->fetchAll();
	foreach ($crons as $cron)
	{
		$phrase_names[] = 'task_' . $cron['varname'] . '_title';
		$phrase_names[] = 'task_' . $cron['varname'] . '_desc';
	}

	function fetch_cron_timerule($cron)
	{
		global $vbphrase;
		$t = array(
			'hour'		=> $cron['hour'],
			'day'		=> $cron['day'],
			'month'		=> -1,
			'weekday'	=> $cron['weekday']
		);

		// set '-1' fields as
		foreach ($t AS $field => $value)
		{
			$t["$field"] = iif($value == -1, '*', $value);
		}

		if (is_numeric($cron['minute']))
		{
			$cron['minute'] = array(0 => $cron['minute']);
		}
		else
		{
			$cron['minute'] = unserialize($cron['minute']);
			if (!is_array($cron['minute']))
			{
				$cron['minute'] = array(-1);
			}
		}

		if ($cron['minute'][0] == -1)
		{
			$t['minute'] = '*';
		}
		else
		{
			$minutes = array();
			foreach ($cron['minute'] AS $nextminute)
			{
				$minutes[] = str_pad(intval($nextminute), 2, 0, STR_PAD_LEFT);
			}
			$t['minute'] = implode(', ', $minutes);
		}

		// set weekday to override day of month if necessary
		$days = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
		if ($t['weekday'] != '*')
		{
			$day = $days[intval($t['weekday'])];
			$t['weekday'] = $vbphrase[$day . "_abbr"];
			$t['day'] = '*';
		}

		return $t;
	}

	?>
	<script type="text/javascript">
	<!--
	function js_cron_jump(cronid)
	{
		task = eval("document.cpform.c" + cronid + ".options[document.cpform.c" + cronid + ".selectedIndex].value");
		switch (task)
		{
			case 'edit': window.location = "cronadmin.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=edit&cronid=" + cronid; break;
			case 'kill': window.location = "cronadmin.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=remove&cronid=" + cronid; break;
			case 'switchactive': window.location = "cronadmin.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=switchactive&cronid=" + cronid + "&hash=<?php echo CP_SESSIONHASH; ?>"; break;
			default: return false; break;
		}
	}
	function js_run_cron(cronid)
	{
		window.location = "<?php echo "cronadmin.php?" . vB::getCurrentSession()->get('sessionurl_js') . "do=runcron&cronid="; ?>" + cronid;
	}
	//-->
	</script>
	<?php

	print_form_header('cronadmin', 'updateenabled');
	print_table_header($vbphrase['scheduled_task_manager_gcron'], 9);
	print_cells_row(array(
		'',
		$vbphrase['min_abbr'],
		$vbphrase['hour_abbr'],
		$vbphrase['day_abbr'],
		$vbphrase['month_abbr'],
		$vbphrase['dow_acronym'],
		$vbphrase['title'],
		$vbphrase['next_time'],
		$vbphrase['controls']
	), 1, '', 1);

	foreach ($crons as $cron)
	{
		$options = array(
			'edit' => $vbphrase['edit'],
			'switchactive' => ($cron['effective_active'] ? $vbphrase['disable'] : $vbphrase['enable'])
		);
		if (!$cron['volatile'] OR $vb5_config['Misc']['debug'])
		{
			$options['kill'] = $vbphrase['delete'];
		}

		$item_title = htmlspecialchars_uni($vbphrase['task_' . $cron['varname'] . '_title']);
		if (isset($vbphrase['task_' . $cron['varname'] . '_title']))
		{
			$item_title = htmlspecialchars_uni($vbphrase['task_' . $cron['varname'] . '_title']);
		}
		else
		{
			$item_title = $cron['varname'];
		}
		if (!$cron['effective_active'])
		{
			$item_title = "<strike>$item_title</strike>";
		}
		$item_desc = htmlspecialchars_uni($vbphrase['task_' . $cron['varname'] . '_desc']);

		$timerule = fetch_cron_timerule($cron);

		// this will happen in the future which the yestoday setting doesn't handle when its in the detailed mode
		$future = ($cron['nextrun'] > TIMENOW AND $vbulletin->options['yestoday'] == 2);

		$cell = array(
			"<input type=\"checkbox\" name=\"enabled[$cron[varname]]\" value=\"1\" title=\"$vbphrase[enabled]\" id=\"cb_enabled_$cron[varname]\" tabindex=\"1\"" . ($cron['active'] ? ' checked="checked"' : '') . " />",
			$timerule['minute'],
			$timerule['hour'],
			$timerule['day'],
			$timerule['month'],
			$timerule['weekday'],
			"<label for=\"cb_enabled_$cron[varname]\"><strong>$item_title</strong><br /><span class=\"smallfont\">$item_desc</span></label>",
			'<div style="white-space:nowrap">' . ($cron['effective_active'] ? vbdate($vbulletin->options['dateformat'], $cron['nextrun'], (true AND !$future)) . (($vbulletin->options['yestoday'] != 2 OR $future) ? '<br />' . vbdate($vbulletin->options['timeformat'], $cron['nextrun']) : '') : $vbphrase['n_a']) . '</div>',
			"\n\t<select name=\"c$cron[cronid]\" onchange=\"js_cron_jump($cron[cronid]);\" class=\"bginput\">\n" . construct_select_options($options) . "\t</select><input type=\"button\" class=\"button\" value=\"$vbphrase[go]\" onclick=\"js_cron_jump($cron[cronid]);\" />\n\t" .
			"\n\t<input type=\"button\" class=\"button\" value=\"$vbphrase[run_now]\" onclick=\"js_run_cron($cron[cronid]);\" />"
		);
		print_cells_row($cell, 0, '', -6);
	}

	print_description_row("<div class=\"smallfont\" align=\"center\">$vbphrase[all_times_are_gmt_x_time_now_is_y]</div>", 0, 9, 'thead');
	print_submit_row($vbphrase['save_enabled_status'], 0, 9, '', "<input type=\"button\" class=\"button\" value=\"$vbphrase[add_new_scheduled_task_gcron]\" tabindex=\"1\" onclick=\"window.location='cronadmin.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit'\" />");

}
print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>
