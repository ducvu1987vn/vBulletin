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
$phrasegroups = array('logging', 'cron');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
$assertor = vB::getDbAssertor();

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadmincron'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['scheduled_task_log']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'choose';
}

// ###################### Start view #######################
if ($_REQUEST['do'] == 'view')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'perpage' => vB_Cleaner::TYPE_INT,
		'varname' => vB_Cleaner::TYPE_STR,
		'orderby' => vB_Cleaner::TYPE_STR,
		'page'    => vB_Cleaner::TYPE_INT
	));

	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 15;
	}

	$queryConds = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_COUNT);
	if (!empty($vbulletin->GPC['varname']))
	{
		$queryConds['varname'] = $vbulletin->GPC['varname'];
	}

	$counter = $assertor->getRow('vBForum:cronlog', $queryConds);
	$totalpages = ceil($counter['count'] / $vbulletin->GPC['perpage']);

	if (empty($vbulletin->GPC['page']))
	{
		$vbulletin->GPC['page'] = 1;
	}

	$startat = ($vbulletin->GPC['page'] - 1) * $vbulletin->GPC['perpage'];

	switch ($vbulletin->GPC['orderby'])
	{
		case 'action':
			$order = array(
				'field' => array('cronlog.varname', 'cronlog.dateline'),
				'direction' => array(vB_dB_Query::SORT_ASC, vB_dB_Query::SORT_DESC)
			);
			break;

		case 'date':
		default:
			$order = array(
				'field' => array('cronlog.dateline'),
				'direction' => array(vB_dB_Query::SORT_DESC)
			);
	}

	unset($queryConds[vB_dB_Query::TYPE_KEY]);
	$queryConds['sortby'] = $order;
	$queryConds['checkCron'] = 1;
	$queryConds[vB_dB_Query::PARAM_LIMITSTART] = $startat;
	$queryConds[vB_dB_Query::PARAM_LIMIT] = $vbulletin->GPC['perpage'];
	$logs = $assertor->getRows('vBForum:getCronLog', $queryConds);

	if (count($logs))
	{
		if ($vbulletin->GPC['page'] != 1)
		{
			$prv = $vbulletin->GPC['page'] - 1;
			$firstpage = "<input type=\"button\" class=\"button\" tabindex=\"1\" value=\"&laquo; " . $vbphrase['first_page'] . "\" onclick=\"window.location='cronlog.php?" . vB::getCurrentSession()->get('sessionurl') . "do=view" .
				"&varname=" . urlencode($vbulletin->GPC['varname']) .
				"&pp=" . $vbulletin->GPC['perpage'] .
				"&orderby=" . urlencode($vbulletin->GPC['orderby']) . "&page=1'\">";
			$prevpage = "<input type=\"button\" class=\"button\" tabindex=\"1\" value=\"&lt; " . $vbphrase['prev_page'] . "\" onclick=\"window.location='cronlog.php?" . vB::getCurrentSession()->get('sessionurl') . "do=view" .
				"&varname=" . urlencode($vbulletin->GPC['varname']) .
				"&pp=" . $vbulletin->GPC['perpage'] .
				"&orderby=" . urlencode($vbulletin->GPC['orderby']) . "&page=$prv'\">";
		}

		if ($vbulletin->GPC['page'] != $totalpages)
		{
			$nxt = $vbulletin->GPC['page'] + 1;
			$page_button = "cronlog.php?" . vB::getCurrentSession()->get('sessionurl') . "do=view&varname=" . urlencode($vbulletin->GPC['varname']) . "&pp=" . $vbulletin->GPC['perpage'] . "&orderby=" . urlencode($vbulletin->GPC['orderby']);
			$nextpage = "<input type=\"button\" class=\"button\" tabindex=\"1\" value=\"" . $vbphrase['next_page'] . " &gt;\" onclick=\"window.location='$page_button&page=$nxt'\">";
			$lastpage = "<input type=\"button\" class=\"button\" tabindex=\"1\" value=\"" . $vbphrase['last_page'] . " &raquo;\" onclick=\"window.location='$page_button&page=$totalpages'\">";
		}

		print_form_header('cronlog', 'remove');
		print_description_row(construct_link_code($vbphrase['restart'], "cronlog.php?" . vB::getCurrentSession()->get('sessionurl') . ""), 0, 4, 'thead', vB_Template_Runtime::fetchStyleVar('right'));
		print_table_header(construct_phrase($vbphrase['scheduled_task_log_viewer_page_x_y_there_are_z_total_log_entries'], vb_number_format($vbulletin->GPC['page']), vb_number_format($totalpages), vb_number_format($counter['count'])), 4);

		$headings = array();
		$headings[] = $vbphrase['id'];
		$headings[] = "<a href=\"cronlog.php?" . vB::getCurrentSession()->get('sessionurl') . "do=view" .
			"&varname=" . urlencode($vbulletin->GPC['varname']) .
			"&pp=" . $vbulletin->GPC['perpage'] .
			"&orderby=action" .
			"&page=" . $vbulletin->GPC['page'] . "\" title=\"" . $vbphrase['order_by_action'] . "\">" . $vbphrase['action'] . "</a>";
		$headings[] = "<a href=\"cronlog.php?" . vB::getCurrentSession()->get('sessionurl') . "do=view" .
			"&varname=" . urlencode($vbulletin->GPC['varname']) .
			"&pp=" . $vbulletin->GPC['perpage'] .
			"&orderby=date" .
			"&page=" . $vbulletin->GPC['page'] . "\" title=\"" . $vbphrase['order_by_date'] . "\">" . $vbphrase['date'] . "</a>";
		$headings[] = $vbphrase['info'];
		print_cells_row($headings, 1);

		foreach ($logs AS $log)
		{
			$cell = array();
			$cell[] = $log['cronlogid'];
			$cell[] = (isset($vbphrase['task_' . $log['varname'] . '_title']) ? $vbphrase['task_' . $log['varname'] . '_title'] : $log['varname']);
			$cell[] = '<span class="smallfont">' . vbdate($vbulletin->options['logdateformat'], $log['dateline']) . '</span>';
			if ($log['type'])
			{
				if (isset($vbphrase['task_' . $log['varname'] . '_log']))
				{
					$phrase = $vbphrase['task_' . $log['varname'] . '_log'];
					if ($unserialized = unserialize($log['description']))
					{
						array_unshift($unserialized, $phrase);
						$cell[] = call_user_func_array('construct_phrase', $unserialized);
					}
					else
					{
						$cell[] = construct_phrase($phrase, $log['description']);
					}
				}
				else if ($log['description'])
				{
					// display this, in case the phrase has been deleted
					$cell[] = "$log[varname] - $log[description]";
				}
				else
				{
					// no phrase, no description, show nothing (varname shown earlier)
					$cell[] = '&nbsp;';
				}
			}
			else
			{
				$cell[] = $log['description'];
			}

			print_cells_row($cell, 0, 0, -4);
		}

		print_table_footer(4, "$firstpage $prevpage &nbsp; $nextpage $lastpage");
	}
	else
	{
		print_stop_message2('no_matches_found_gerror');
	}
}

// ###################### Start prune log #######################
if ($_POST['do'] == 'prunelog')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'varname' 	=> vB_Cleaner::TYPE_STR,
		'daysprune' => vB_Cleaner::TYPE_INT
	));

	$conditions = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_COUNT);
	if ($vbulletin->GPC['varname'])
	{
		$conditions[vB_dB_Query::CONDITIONS_KEY][] = array('field' => 'varname', 'value' => $vbulletin->GPC['varname'], vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_EQ);
	}

	$datecut = vB::getRequest()->getTimeNow() - (86400 * $vbulletin->GPC['daysprune']);

	$conditions[vB_dB_Query::CONDITIONS_KEY][] = array('field' => 'dateline', 'value' => $datecut, vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_LT);
	$logs = $assertor->getRow('vBForum:cronlog', $conditions);


	if ($logs['count'])
	{
		print_form_header('cronlog', 'doprunelog');
		construct_hidden_code('datecut', $datecut);
		construct_hidden_code('varname', $vbulletin->GPC['varname']);
		print_table_header($vbphrase['prune_scheduled_task_log']);
		print_description_row(construct_phrase($vbphrase['are_you_sure_you_want_to_prune_x_log_entries_from_scheduled_task_log'], vb_number_format($logs['count'])));
		print_submit_row($vbphrase['yes'], 0, 0, $vbphrase['no']);
	}
	else
	{
		print_stop_message2('no_matches_found_gerror');
	}
}

// ###################### Start do prune log #######################
if ($_POST['do'] == 'doprunelog')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'varname' => vB_Cleaner::TYPE_STR,
		'datecut' => vB_Cleaner::TYPE_INT
	));

	$conditions = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE);
	if (!empty($vbulletin->GPC['varname']))
	{
		$conditions[vB_dB_Query::CONDITIONS_KEY][] = array('field' => 'varname', 'value' => $vbulletin->GPC['varname'], vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_EQ);
	}

	$conditions[vB_dB_Query::CONDITIONS_KEY][] = array('field' => 'dateline', 'value' => $vbulletin->GPC['datecut'], vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_LT);

	$logs = $assertor->assertQuery('vBForum:cronlog', $conditions);

	print_stop_message2('pruned_scheduled_task_log_successfully', 'cronlog', array('do'=>'choose'));
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'choose')
{
	$cronjobs = $assertor->getRows('cron', array(), 'varname');

	$filelist = array();
	$filelist[0] = $vbphrase['all_scheduled_tasks'];

	foreach ($cronjobs AS $file)
	{
		$filelist["$file[varname]"] = (isset($vbphrase['task_' . $file['varname'] . '_title']) ?
			htmlspecialchars_uni($vbphrase['task_' . $file['varname'] . '_title']) :
			$file['varname']
		);
	}

	$perpage = array(5 => 5, 10 => 10, 15 => 15, 20 => 20, 25 => 25, 30 => 30, 40 => 40, 50 => 50, 100 => 100);
	$orderby = array('date' => $vbphrase['date'], 'action' => $vbphrase['action']);

	print_form_header('cronlog', 'view');
	print_table_header($vbphrase['scheduled_task_log_viewer']);

	print_select_row($vbphrase['log_entries_to_show_per_page'], 'perpage', $perpage, 15);
	print_select_row($vbphrase['show_only_entries_generated_by'], 'varname', $filelist);
	print_select_row($vbphrase['order_by_gcpglobal'], 'orderby', $orderby);

	print_submit_row($vbphrase['view'], 0);

	print_form_header('cronlog', 'prunelog');
	print_table_header($vbphrase['prune_scheduled_task_log']);
	print_select_row($vbphrase['remove_entries_related_to_action'], 'varname', $filelist);
	print_input_row($vbphrase['remove_entries_older_than_days'], 'daysprune', 30);
	print_submit_row($vbphrase['prune'], 0);
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>
