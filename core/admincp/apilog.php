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
define('CVS_REVISION', '$RCSfile$ - $Revision: 32878 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbphrase, $vbulletin;
$phrasegroups = array('logging');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vb5_config =& vB::getConfig();

// #############################################################################
print_cp_header($vbphrase['api_log']);
// #############################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'choose';
}

// ###################### Start view #######################
if ($_REQUEST['do'] == 'view' AND can_access_logs($vb5_config['SpecialUsers']['canviewadminlog'], 1, '<p>' . $vbphrase['control_panel_log_viewing_restricted'] . '</p>'))
{
	$vbulletin->input->clean_array_gpc('r', array(
		'apiclientname'	    => vB_Cleaner::TYPE_NOHTML,
		'userid'		    => vB_Cleaner::TYPE_INT,
		'apiclientid'       => vB_Cleaner::TYPE_INT,
		'apiclientuniqueid' => vB_Cleaner::TYPE_STR,
		'pagenumber'        => vB_Cleaner::TYPE_INT,
		'orderby'           => vB_Cleaner::TYPE_STR,
		'startdate'         => vB_Cleaner::TYPE_UNIXTIME,
		'enddate'           => vB_Cleaner::TYPE_UNIXTIME
	));

	if ($vbulletin->GPC['perpage'] < 1)
	{
		$vbulletin->GPC['perpage'] = 15;
	}
	if ($vbulletin->GPC['pagenumber'] < 1)
	{
		$vbulletin->GPC['pagenumber'] = 1;
	}
	$startat = ($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->GPC['perpage'];

	$counter = vB::getDbAssertor()->getRow('fetchApiLogsCount', array(
		'userid' => $vbulletin->GPC['userid'],
		'apiclientid' => $vbulletin->GPC['apiclientid'],
		'apiclientuniqueid' => $vbulletin->GPC['apiclientuniqueid'],
		'apiclientname' => $vbulletin->GPC['apiclientname'],
		'startdate' => $vbulletin->GPC['startdate'],
		'enddate' => $vbulletin->GPC['enddate'],
	));
	$totalpages = ceil($counter['total'] / $vbulletin->GPC['perpage']);

	if (!in_array($vbulletin->GPC['orderby'], array('user', 'clientname')))
	{
		$vbulletin->GPC['orderby'] = 'date';
	}

	$logs = vB::getDbAssertor()->getRows('fetchApiLogs', array(
		'userid' => $vbulletin->GPC['userid'],
		'apiclientid' => $vbulletin->GPC['apiclientid'],
		'apiclientuniqueid' => $vbulletin->GPC['apiclientuniqueid'],
		'apiclientname' => $vbulletin->GPC['apiclientname'],
		'startdate' => $vbulletin->GPC['startdate'],
		'enddate' => $vbulletin->GPC['enddate'],
		'orderby' => $vbulletin->GPC['orderby'],
		'startat' => $startat,
		'perpage' => $vbulletin->GPC['perpage'],
	));

	if (count($logs))
	{

		if ($vbulletin->GPC['pagenumber'] != 1)
		{
			$prv = $vbulletin->GPC['pagenumber'] - 1;
			$firstpage = "<input type=\"button\" class=\"button\" value=\"&laquo; " . $vbphrase['first_page'] .
							"\" tabindex=\"1\" onclick=\"window.location='apilog.php?" . vB::getCurrentSession()->get('sessionurl') .
							"do=view&amp;apiclientname=" . $vbulletin->GPC['apiclientname'] .
							"&amp;apiclientid=" . $vbulletin->GPC['apiclientid'] .
							"&amp;u=" . $vbulletin->GPC['userid'] .
							"&amp;pp=" . $vbulletin->GPC['perpage'] .
							"&amp;orderby=" . $vbulletin->GPC['orderby'] .
							"&amp;page=1" .
							"&amp;startdate=" . $vbulletin->GPC['startdate'] .
							"&amp;enddate=" . $vbulletin->GPC['enddate'] .
							"'\"/>";

			$prevpage = "<input type=\"button\" class=\"button\" value=\"&lt; " . $vbphrase['prev_page'] .
						"\" tabindex=\"1\" onclick=\"window.location='apilog.php?" . vB::getCurrentSession()->get('sessionurl') .
						"do=view&amp;apiclientname=" . $vbulletin->GPC['apiclientname'] .
						"&amp;apiclientid=" . $vbulletin->GPC['apiclientid'] .
						"&amp;u=" . $vbulletin->GPC['userid'] .
						"&amp;pp=" . $vbulletin->GPC['perpage'] .
						"&amp;orderby=" . $vbulletin->GPC['orderby'] .
						"&amp;page=$prv" .
						"&amp;startdate=" . $vbulletin->GPC['startdate'] .
						"&amp;enddate=" . $vbulletin->GPC['enddate'] .
						"'\"/>";
		}

		if ($vbulletin->GPC['pagenumber'] != $totalpages)
		{
			$nxt = $vbulletin->GPC['pagenumber'] + 1;
			$nextpage = "<input type=\"button\" class=\"button\" value=\"" . $vbphrase['next_page'] .
						" &gt;\" tabindex=\"1\" onclick=\"window.location='apilog.php?" .
						vB::getCurrentSession()->get('sessionurl') .
						"do=view&amp;apiclientname=" . $vbulletin->GPC['apiclientname'] .
						"&amp;apiclientid=" . $vbulletin->GPC['apiclientid'] .
						"&amp;u=" . $vbulletin->GPC['userid'] .
						"&amp;pp=" . $vbulletin->GPC['perpage'] .
						"&amp;orderby=" . $vbulletin->GPC['orderby'] .
						"&amp;page=$nxt" .
						"&amp;startdate=" . $vbulletin->GPC['startdate'] .
						"&amp;enddate=" . $vbulletin->GPC['enddate'] .
						"'\"/>";

			$lastpage = "<input type=\"button\" class=\"button\" value=\"" . $vbphrase['last_page'] .
						" &raquo;\" tabindex=\"1\" onclick=\"window.location='apilog.php?" . vB::getCurrentSession()->get('sessionurl') .
						"do=view&amp;apiclientname=" . $vbulletin->GPC['apiclientname'] .
						"&amp;apiclientid=" . $vbulletin->GPC['apiclientid'] .
						"&amp;u=" . $vbulletin->GPC['userid'] .
						"&amp;pp=" . $vbulletin->GPC['perpage'] .
						"&amp;orderby=" . $vbulletin->GPC['orderby'] .
						"&amp;page=$totalpages" .
						"&amp;startdate=" . $vbulletin->GPC['startdate'] .
						"&amp;enddate=" . $vbulletin->GPC['enddate'] .
						"'\"/>";
		}

		print_form_header('apilog', 'remove');
		print_description_row(construct_link_code($vbphrase['restart'], "apilog.php?" . vB::getCurrentSession()->get('sessionurl')), 0, 8, 'thead', vB_Template_Runtime::fetchStyleVar('right'));
		print_table_header(construct_phrase($vbphrase['api_log_viewer_page_x_y_there_are_z_total_log_entries'], vb_number_format($vbulletin->GPC['pagenumber']), vb_number_format($totalpages), vb_number_format($counter['total'])), 8);

		$headings = array();
		$headings[] = $vbphrase['id'];
		$headings[] = $vbphrase['apiclientid'];
		$headings[] = "<a href='apilog.php?" . vB::getCurrentSession()->get('sessionurl') . "do=view&amp;apiclientname=" . $vbulletin->GPC['apiclientname'] . "&amp;apiclientid=" . $vbulletin->GPC['apiclientid'] . "&amp;u=" . $vbulletin->GPC['userid'] . "&amp;pp=" . $vbulletin->GPC['perpage'] . "&amp;orderby=clientname&amp;page=" . $vbulletin->GPC['pagenumber'] . "&amp;startdate=" . $vbulletin->GPC['startdate'] . "&amp;enddate=" . $vbulletin->GPC['enddate'] . "' title='" . $vbphrase['order_by_clientname'] . "'>" . $vbphrase['apiclientname'] . "</a>";
		$headings[] = "<a href='apilog.php?" . vB::getCurrentSession()->get('sessionurl') . "do=view&amp;apiclientname=" . $vbulletin->GPC['apiclientname'] . "&amp;apiclientid=" . $vbulletin->GPC['apiclientid'] . "&amp;u=" . $vbulletin->GPC['userid'] . "&amp;pp=" . $vbulletin->GPC['perpage'] . "&amp;orderby=user&amp;page=" . $vbulletin->GPC['pagenumber'] . "&amp;startdate=" . $vbulletin->GPC['startdate'] . "&amp;enddate=" . $vbulletin->GPC['enddate'] . "' title='" . $vbphrase['order_by_username'] . "'>" . $vbphrase['username'] . "</a>";
		$headings[] = "<a href='apilog.php?" . vB::getCurrentSession()->get('sessionurl') . "do=view&amp;apiclientname=" . $vbulletin->GPC['apiclientname'] . "&amp;apiclientid=" . $vbulletin->GPC['apiclientid'] . "&amp;u=" . $vbulletin->GPC['userid'] . "&amp;pp=" . $vbulletin->GPC['perpage'] . "&amp;orderby=date&amp;page=" . $vbulletin->GPC['pagenumber'] . "&amp;startdate=" . $vbulletin->GPC['startdate'] . "&amp;enddate=" . $vbulletin->GPC['enddate'] . "' title='" . $vbphrase['order_by_date'] . "'>" . $vbphrase['date'] . "</a>";
		$headings[] = $vbphrase['apimethod'];
		$headings[] = $vbphrase['paramget'];
		$headings[] = $vbphrase['ip_address'];
		print_cells_row($headings, 1);

		foreach ($logs as $log)
		{
			$cell = array();
			$cell[] = $log['apilogid'];
			$cell[] = "<a href=\"apilog.php?" . vB::getCurrentSession()->get('sessionurl') . "do=viewclient&amp;apiclientid=$log[apiclientid]\"><b>$log[apiclientid]</b></a>";
			$cell[] = "<a href=\"apilog.php?" . vB::getCurrentSession()->get('sessionurl') . "do=viewclient&amp;apiclientid=$log[apiclientid]\"><b>" . htmlspecialchars_uni($log['clientname']) . "</b></a>";
			$cell[] = iif(!empty($log['username']), "<a href=\"user.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;u=$log[userid]\"><b>$log[username]</b></a>", $vbphrase['guest']);
			$cell[] = '<span class="smallfont">' . vbdate($vbulletin->options['logdateformat'], $log['dateline']) . '</span>';
			$cell[] = htmlspecialchars_uni($log['method']);
			$cell[] = htmlspecialchars_uni(print_r(@unserialize($log['paramget']), true));
			$cell[] = '<span class="smallfont">' . iif($log['ipaddress'], "<a href=\"usertools.php?" . vB::getCurrentSession()->get('sessionurl') . "do=doips&amp;depth=2&amp;ipaddress=$log[ipaddress]&amp;hash=" . CP_SESSIONHASH . "\">$log[ipaddress]</a>", '&nbsp;') . '</span>';
			print_cells_row($cell);
		}

		print_table_footer(8, "$firstpage $prevpage &nbsp; $nextpage $lastpage");

	}
	else
	{
		print_stop_message2('no_log_entries_matched_your_query');
	}
}

// ###################### Start prune log #######################
if ($_REQUEST['do'] == 'prunelog' AND can_access_logs($vb5_config['SpecialUsers']['canpruneadminlog'], 0, '<p>' . $vbphrase['control_panel_log_pruning_permission_restricted'] . '</p>'))
{
	$vbulletin->input->clean_array_gpc('r', array(
		'apiclientid'	=> vB_Cleaner::TYPE_INT,
		'daysprune'		=> vB_Cleaner::TYPE_INT
	));

	$datecut = TIMENOW - (86400 * $vbulletin->GPC['daysprune']);

	$logs = vB::getDbAssertor()->getRow('fetchApiLogsCountDatecut', array(
		'datecut' => $datecut,
		'apiclientid' => $vbulletin->GPC['apiclientid'],
	));

	if ($logs['total'])
	{
		print_form_header('apilog', 'doprunelog');
		construct_hidden_code('datecut', $datecut);
		construct_hidden_code('apiclientid', $vbulletin->GPC['apiclientid']);
		print_table_header($vbphrase['prune_control_panel_log']);
		print_description_row(construct_phrase($vbphrase['are_you_sure_you_want_to_prune_x_log_entries_from_api_log'], vb_number_format($logs['total'])));
		print_submit_row($vbphrase['yes'], 0, 0, $vbphrase['no']);
	}
	else
	{
		print_stop_message2('no_log_entries_matched_your_query');
	}
}

// ###################### Start do prune log #######################
if ($_POST['do'] == 'doprunelog' AND can_access_logs($vb5_config['SpecialUsers']['canpruneadminlog'], 0, '<p>' . $vbphrase['control_panel_log_pruning_permission_restricted'] . '</p>'))
{
	$vbulletin->input->clean_array_gpc('p', array(
		'apiclientid'	=> vB_Cleaner::TYPE_INT,
		'datecut'		=> vB_Cleaner::TYPE_INT
	));

	$conditions = array(
		array('field' => 'dateline', 'value' => $vbulletin->GPC['datecut'], 'operator' => 'LT'),
	);
	if ($vbulletin->GPC['apiclientid'])
	{
		$conditions[] = array('field' => 'apiclientid', 'value' => $vbulletin->GPC['apiclientid'], 'operator' => 'EQ');
	}

	vB::getDbAssertor()->delete('apilog', $conditions);

	print_stop_message2('pruned_control_panel_log_successfully', 'apilog', array('do'=>'choose'));
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'choose')
{

	if (can_access_logs($vb5_config['SpecialUsers']['canviewadminlog'], 1))
	{
		$show_admin_log = true;
	}
	else
	{
		echo '<p>' . $vbphrase['control_panel_log_viewing_restricted'] . '</p>';
	}

	if ($show_admin_log)
	{
		log_admin_action();

		$clientnames = vB::getDbAssertor()->getRows('api_fetchclientnames');
		$clientnamelist = array('no_value' => $vbphrase['all_api_clients']);
		foreach ($clientnames as $clientname)
		{
			$clientnamelist["$clientname[clientname]"] = $clientname['clientname'];
		}
		$users = vB::getDbAssertor()->getRows('api_fetchclientusers');
		$userlist = array('-1' => $vbphrase['all_users']);
		foreach ($users as $user)
		{
			if ($user['userid'] === '0')
			{
				$user['username'] = $vbphrase['guest'];
			}
			$userlist["$user[userid]"] = $user['username'];
		}

		$perpage_options = array(
			5 => 5,
			10 => 10,
			15 => 15,
			20 => 20,
			25 => 25,
			30 => 30,
			40 => 40,
			50 => 50,
			100 => 100,
		);

		if (!$vbulletin->options['enableapilog'])
		{
			print_warning_table($vbphrase['apilog_disabled_options']);
		}

		print_form_header('apilog', 'view');
		print_table_header($vbphrase['api_log_viewer']);
		print_select_row($vbphrase['log_entries_to_show_per_page'], 'perpage', $perpage_options, 15);
		print_select_row($vbphrase['show_only_entries_generated_by_apiclientname'], 'apiclientname', $clientnamelist, '-1');
		print_select_row($vbphrase['show_only_entries_related_to_remembered_user'], 'userid', $userlist, '-1');
		print_input_row($vbphrase['api_client_id'], 'apiclientid', '', true, 10);
		print_input_row($vbphrase['api_client_uniqueid'], 'apiclientuniqueid', '', true, 30);

		print_time_row($vbphrase['start_date'], 'startdate', 0, 0);
		print_time_row($vbphrase['end_date'], 'enddate', 0, 0);

		print_select_row($vbphrase['order_by_gcpglobal'], 'orderby', array('date' => $vbphrase['date'], 'user' => $vbphrase['user'], 'clientname' => $vbphrase['apiclientname']), 'date');
		print_submit_row($vbphrase['view'], 0);

		if (can_access_logs($vb5_config['SpecialUsers']['canpruneadminlog'], 1))
		{
			print_form_header('apilog', 'prunelog');
			print_table_header($vbphrase['prune_api_log']);
			print_input_row($vbphrase['remove_entries_logged_by_apiclientid'], 'apiclientid');
			print_input_row($vbphrase['remove_entries_older_than_days'], 'daysprune', 30);
			print_submit_row($vbphrase['prune_api_log'], 0);
		}
		else
		{
			echo '<p>' . $vbphrase['control_panel_log_pruning_permission_restricted'] . '</p>';
		}
	}
}

// ###################### Start view client #######################
if ($_REQUEST['do'] == 'viewclient')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'apiclientid'	=> vB_Cleaner::TYPE_UINT
	));

	if (!$vbulletin->GPC['apiclientid']
			OR
		!($client = vB::getDbAssertor()->getRow('api_fetchclientbyid', array('apiclientid' => $vbulletin->GPC['apiclientid']))))
	{
		print_stop_message2(array('invalidid',  'apiclientid'));
	}

	print_form_header('api', 'viewclient');
	print_table_header($vbphrase['apiclient']);
	print_label_row($vbphrase['apiclientid'], $client['apiclientid']);
	print_label_row($vbphrase['apiclientname'], $client['clientname']);
	print_label_row($vbphrase['apiclientversion'], $client['clientversion']);
	print_label_row($vbphrase['apiclient_platformname'], $client['platformname']);
	print_label_row($vbphrase['apiclient_platformversion'], $client['platformversion']);
	print_label_row($vbphrase['apiclient_uniqueid'], $client['uniqueid']);
	print_label_row($vbphrase['apiclient_initialipaddress'], iif(!empty($client['initialipaddress']), "<a href=\"usertools.php?" . vB::getCurrentSession()->get('sessionurl') . "do=doips&amp;depth=2&amp;ipaddress=$client[initialipaddress]&amp;hash=" . CP_SESSIONHASH . "\">$client[initialipaddress]</a>", "&nbsp;"));
	print_label_row($vbphrase['apiclient_initialtime'], vbdate($vbulletin->options['dateformat'] . ' ' .$vbulletin->options['timeformat'], $client['dateline']));
	print_label_row($vbphrase['apiclient_lastactivity'], vbdate($vbulletin->options['dateformat'] . ' ' .$vbulletin->options['timeformat'], $client['lastactivity']));
	print_label_row($vbphrase['apiclient_clienthash'], $client['clienthash']);
	print_label_row($vbphrase['apiclient_secret'], $client['secret']);
	print_label_row($vbphrase['apiclient_apiaccesstoken'], $client['apiaccesstoken']);
	print_label_row($vbphrase['apiclient_remembereduser'], iif(!empty($client['username']), "<a href=\"user.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;u=$client[userid]\"><b>$client[username]</b></a>", $vbphrase['guest']));
	print_table_footer();
}

echo '<p class="smallfont" align="center"><a href="#" onclick="js_open_help(\'adminlog\', \'restrict\', \'\');">' . $vbphrase['want_to_access_grant_access_to_this_script'] . '</a></p>';

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>
