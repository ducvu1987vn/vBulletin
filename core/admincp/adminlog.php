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
$phrasegroups = array('logging');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

// ###################### Start listLogFiles #######################
// function to return an array of log files in the filesystem
function fetch_log_file_array($type = 'database')
{
	global $vbulletin, $vbphrase;

	$filelist = array();

	switch($type)
	{
		case 'database':
			$option = 'errorlogdatabase';
			$title = $vbphrase['vbulletin_database_errors'];
			break;

		case 'security':
			$option = 'errorlogsecurity';
			$title = $vbphrase['admin_control_panel_failed_logins'];
			break;

		default:
			return $filelist;
	}

	if ($filebase = trim($vbulletin->options["$option"]))
	{
		$slashpos = strrpos($filebase, '/');
		if ($slashpos === false)
		{
			$basedir = '.';
		}
		else
		{
			$basedir = substr($filebase, 0, $slashpos);
		}
		if ($handle = @opendir($basedir))
		{
			$filebase = substr($filebase, $slashpos + 1);
			$namelength = strlen($filebase);
			while ($file = readdir($handle))
			{
				if (strpos($file, $filebase) === 0)
				{
					if ($unixdate = intval(substr($file, $namelength, -4)))
					{
						$date = vbdate($vbulletin->options['dateformat'] . ' ' . $vbulletin->options['timeformat'], $unixdate);
					}
					else
					{
						$date = '(Current Version)';
					}
					$key = $type . '_' . $unixdate;
					$filelist["$key"] = "$title $date";
				}
			}
			@closedir($handle);
			return $filelist;
		}
		else
		{
			echo '<p>' . $vbphrase['invalid_directory_specified'] . '</p>';
		}
	}
	else
	{
		return false;
	}
}

// #############################################################################

$vb5_config =& vB::getConfig();
$assertor = vB::getDbAssertor();
if ($_POST['do'] == 'viewlogfile' AND can_access_logs($vb5_config['SpecialUsers']['canviewadminlog'], 1, '<p>' . $vbphrase['control_panel_log_viewing_restricted'] .'</p>'))
{
	$vbulletin->input->clean_array_gpc('p', array(
		'filename'	=> vB_Cleaner::TYPE_STR,
		'delete'	=> vB_Cleaner::TYPE_STR
	));

	$filebits = explode('_', $vbulletin->GPC['filename']);
	$type = trim($filebits[0]);
	$date = intval($filebits[1]);

	switch($type)
	{
		case 'database':
		case 'security':
		{
			if ($vbulletin->GPC['filename'] = trim($vbulletin->options["errorlog$type"]))
			{
				$vbulletin->GPC['filename'] = $vbulletin->GPC['filename'] . iif($date, $date) . '.log';
				if (file_exists($vbulletin->GPC['filename']))
				{
					if ($vbulletin->GPC['delete'])
					{
						if (can_access_logs($vb5_config['SpecialUsers']['canpruneadminlog'], 0, '<p>' . $vbphrase['log_file_deletion_restricted'] . '</p>'))
						{
							if (@unlink($vbulletin->GPC['filename']))
							{
								print_stop_message2('deleted_file_successfully');
							}
							else
							{
								print_stop_message2('unable_to_delete_file');
							}
						}
					}
					else
					{
						require_once(DIR . '/includes/functions_file.php');
						file_download(implode('', file($vbulletin->GPC['filename'])), substr($vbulletin->GPC['filename'], strrpos($vbulletin->GPC['filename'], '/') + 1), 'baa');
					}
				}
				else
				{
					print_stop_message2('invalid_file_specified');
				}
			}
		}
	}

	$_REQUEST['do'] = 'logfiles';

}

// #############################################################################
print_cp_header($vbphrase['control_panel_log']);
// #############################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'choose';
}

// ###################### Start view db error log #######################
if ($_REQUEST['do'] == 'logfiles' AND can_access_logs($vb5_config['SpecialUsers']['canviewadminlog'], 1, '<p>' . $vbphrase['control_panel_log_viewing_restricted'] . '</p>'))
{
	// get database and security log files list
	$dblogs = fetch_log_file_array('database');
	$cplogs = fetch_log_file_array('security');

	if ($dblogs === false AND $cplogs === false)
	{
		print_stop_message2('no_log_file_defined_in_vbulletin_options');
	}

	if ($dblogs)
	{
		$dblogs = '<optgroup label="vBulletin Database Errors">' . construct_select_options($dblogs) . '</optgroup>';
	}
	if ($cplogs)
	{
		$cplogs = '<optgroup label="Admin Control Panel Failed Logins">' . construct_select_options($cplogs) . '</optgroup>';
	}

	print_form_header('adminlog', 'viewlogfile');
	print_table_header($vbphrase['view_logs']);
	print_label_row($vbphrase['logs'], '<select name="filename" size="15" tabindex="1" class="bginput">' . $dblogs . $cplogs  . '<option value="">' . str_repeat('&nbsp; ', 40) . '</option></select>');
	print_table_footer(2, '<input type="submit" class="button" value=" ' . $vbphrase['view'] . ' " accesskey="s" tabindex="1" /> <input type="submit" class="button" value=" ' . $vbphrase['delete'] . ' " name="delete" tabindex="1" />');
}

// ###################### Start view #######################
if ($_REQUEST['do'] == 'view' AND can_access_logs($vb5_config['SpecialUsers']['canviewadminlog'], 1, '<p>' . $vbphrase['control_panel_log_viewing_restricted'] . '</p>'))
{
	$vbulletin->input->clean_array_gpc('r', array(
		'userid'	=> vB_Cleaner::TYPE_UINT,
		'script'	=> vB_Cleaner::TYPE_NOHTML,
		'perpage'   => vB_Cleaner::TYPE_INT,
		'pagenumber'=> vB_Cleaner::TYPE_INT,
		'orderby'   => vB_Cleaner::TYPE_STR,
		'startdate' => vB_Cleaner::TYPE_UNIXTIME,
		'enddate'   => vB_Cleaner::TYPE_UNIXTIME
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

	$counter = $assertor->getRow('vBForum:fetchAdminLogCount',
		array('userid' => $vbulletin->GPC['userid'],
			'script' => $vbulletin->GPC['script'],
			'startdate' => $vbulletin->GPC['startdate'],
			'enddate' => $vbulletin->GPC['enddate']
		)
	);
	$totalpages = ceil($counter['total'] / $vbulletin->GPC['perpage']);

	$logs = $assertor->assertQuery('vBForum:fetchAdminLog',
		array('userid' => $vbulletin->GPC['userid'],
			'script' => $vbulletin->GPC['script'],
			'startdate' => $vbulletin->GPC['startdate'],
			'enddate' => $vbulletin->GPC['enddate'],
			'orderby' => $vbulletin->GPC['orderby'],
			vB_Db_Query::PARAM_LIMITSTART => $startat,
			vB_Db_Query::PARAM_LIMIT => $vbulletin->GPC['perpage'],
		)
	);

	if ($logs AND $logs->valid())
	{
		if ($vbulletin->GPC['pagenumber'] != 1)
		{
			$prv = $vbulletin->GPC['pagenumber'] - 1;
			$firstpage = "<input type=\"button\" class=\"button\" value=\"&laquo; " . $vbphrase['first_page'] .
							"\" tabindex=\"1\" onclick=\"window.location='adminlog.php?" . vB::getCurrentSession()->get('sessionurl') .
							"do=view&script=" . $vbulletin->GPC['script'] .
							"&u=" . $vbulletin->GPC['userid'] .
							"&pp=" . $vbulletin->GPC['perpage'] .
							"&orderby=" . $vbulletin->GPC['orderby'] .
							"&page=1" .
							"&startdate=" . $vbulletin->GPC['startdate'] .
							"&enddate=" . $vbulletin->GPC['enddate'] .
							"'\">";

			$prevpage = "<input type=\"button\" class=\"button\" value=\"&lt; " . $vbphrase['prev_page'] .
						"\" tabindex=\"1\" onclick=\"window.location='adminlog.php?" . vB::getCurrentSession()->get('sessionurl') .
						"do=view&script=" . $vbulletin->GPC['script'] .
						"&u=" . $vbulletin->GPC['userid'] .
						"&pp=" . $vbulletin->GPC['perpage'] .
						"&orderby=" . $vbulletin->GPC['orderby'] .
						"&page=$prv" .
						"&startdate=" . $vbulletin->GPC['startdate'] .
						"&enddate=" . $vbulletin->GPC['enddate'] .
						"'\">";
		}

		if ($vbulletin->GPC['pagenumber'] != $totalpages)
		{
			$nxt = $vbulletin->GPC['pagenumber'] + 1;
			$nextpage = "<input type=\"button\" class=\"button\" value=\"" . $vbphrase['next_page'] .
						" &gt;\" tabindex=\"1\" onclick=\"window.location='adminlog.php?" .
						vB::getCurrentSession()->get('sessionurl') .
						"do=view&script=" . $vbulletin->GPC['script'] .
						"&u=" . $vbulletin->GPC['userid'] .
						"&pp=" . $vbulletin->GPC['perpage'] .
						"&orderby=" . $vbulletin->GPC['orderby'] .
						"&page=$nxt" .
						"&startdate=" . $vbulletin->GPC['startdate'] .
						"&enddate=" . $vbulletin->GPC['enddate'] .
						"'\">";

			$lastpage = "<input type=\"button\" class=\"button\" value=\"" . $vbphrase['last_page'] .
						" &raquo;\" tabindex=\"1\" onclick=\"window.location='adminlog.php?" . vB::getCurrentSession()->get('sessionurl') .
						"do=view&script=" . $vbulletin->GPC['script'] .
						"&u=" . $vbulletin->GPC['userid'] .
						"&pp=" . $vbulletin->GPC['perpage'] .
						"&orderby=" . $vbulletin->GPC['orderby'] .
						"&page=$totalpages" .
						"&startdate=" . $vbulletin->GPC['startdate'] .
						"&enddate=" . $vbulletin->GPC['enddate'] .
						"'\">";
		}

		print_form_header('adminlog', 'remove');
		print_description_row(construct_link_code($vbphrase['restart'], "adminlog.php?" . vB::getCurrentSession()->get('sessionurl')), 0, 7, 'thead', vB_Template_Runtime::fetchStyleVar('right'));
		print_table_header(construct_phrase($vbphrase['control_panel_log_viewer_page_x_y_there_are_z_total_log_entries'], vb_number_format($vbulletin->GPC['pagenumber']), vb_number_format($totalpages), vb_number_format($counter['total'])), 7);

		$headings = array();
		$headings[] = $vbphrase['id'];
		$headings[] = "<a href='adminlog.php?" . vB::getCurrentSession()->get('sessionurl') . "do=view&script=" . $vbulletin->GPC['script'] . "&u=" . $vbulletin->GPC['userid'] . "&pp=" . $vbulletin->GPC['perpage'] . "&orderby=user&page=" . $vbulletin->GPC['pagenumber'] . "&startdate=" . $vbulletin->GPC['startdate'] . "&enddate=" . $vbulletin->GPC['enddate'] . "' title='" . $vbphrase['order_by_username'] . "'>" . $vbphrase['username'] . "</a>";
		$headings[] = "<a href='adminlog.php?" . vB::getCurrentSession()->get('sessionurl') . "do=view&script=" . $vbulletin->GPC['script'] . "&u=" . $vbulletin->GPC['userid'] . "&pp=" . $vbulletin->GPC['perpage'] . "&orderby=date&page=" . $vbulletin->GPC['pagenumber'] . "&startdate=" . $vbulletin->GPC['startdate'] . "&enddate=" . $vbulletin->GPC['enddate'] . "' title='" . $vbphrase['order_by_date'] . "'>" . $vbphrase['date'] . "</a>";
		$headings[] = "<a href='adminlog.php?" . vB::getCurrentSession()->get('sessionurl') . "do=view&script=" . $vbulletin->GPC['script'] . "&u=" . $vbulletin->GPC['userid'] . "&pp=" . $vbulletin->GPC['perpage'] . "&orderby=script&page=" . $vbulletin->GPC['pagenumber'] . "&startdate=" . $vbulletin->GPC['startdate'] . "&enddate=" . $vbulletin->GPC['enddate'] . "' title='" . $vbphrase['order_by_script'] . "'>" . $vbphrase['script'] . "</a>";
		$headings[] = $vbphrase['action'];
		$headings[] = $vbphrase['info'];
		$headings[] = $vbphrase['ip_address'];
		print_cells_row($headings, 1);

		foreach ($logs AS $log)
		{
			$cell = array();
			$cell[] = $log['adminlogid'];
			$cell[] = iif(!empty($log['username']), "<a href=\"user.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&u=$log[userid]\"><b>$log[username]</b></a>", $vbphrase['n_a']);
			$cell[] = '<span class="smallfont">' . vbdate($vbulletin->options['logdateformat'], $log['dateline']) . '</span>';
			$cell[] = htmlspecialchars_uni($log['script']);
			$cell[] = htmlspecialchars_uni($log['action']);
			$cell[] = htmlspecialchars_uni($log['extrainfo']);
			$cell[] = '<span class="smallfont">' . iif($log['ipaddress'], "<a href=\"usertools.php?" . vB::getCurrentSession()->get('sessionurl') . "do=gethost&ip=$log[ipaddress]\">$log[ipaddress]</a>", '&nbsp;') . '</span>';
			print_cells_row($cell);
		}

		print_table_footer(7, "$firstpage $prevpage &nbsp; $nextpage $lastpage");

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
		'userid'	=> vB_Cleaner::TYPE_INT,
		'script'	=> vB_Cleaner::TYPE_STR,
		'daysprune'	=> vB_Cleaner::TYPE_INT
	));

	$datecut = TIMENOW - (86400 * $vbulletin->GPC['daysprune']);

	$logs = $assertor->getRow('vBForum:countAdminLogByDateCut',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'datecut' => $datecut,
			'userid' => $vbulletin->GPC['userid'],
			'script' => $vbulletin->GPC['script']
		)
	);

	if ($logs['total'])
	{
		print_form_header('adminlog', 'doprunelog');
		construct_hidden_code('datecut', $datecut);
		construct_hidden_code('script', $vbulletin->GPC['script']);
		construct_hidden_code('userid', $vbulletin->GPC['userid']);
		print_table_header($vbphrase['prune_control_panel_log']);
		print_description_row(construct_phrase($vbphrase['are_you_sure_you_want_to_prune_x_log_entries_from_control_panel_log'], vb_number_format($logs['total'])));
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
		'userid'	=> vB_Cleaner::TYPE_INT,
		'script'	=> vB_Cleaner::TYPE_STR,
		'datecut'	=> vB_Cleaner::TYPE_INT
	));

	$assertor->assertQuery('vBForum:deleteAdminLogByDateCut',
		array('datecut' =>  $vbulletin->GPC['datecut'],
			'userid' => $vbulletin->GPC['userid'],
			'script' => $vbulletin->GPC['script']
		)
	);

	print_stop_message2('pruned_control_panel_log_successfully', 'adminlog', array('do'=>'choose'));
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

		$files = $assertor->assertQuery('vBForum:fetchDistinctScript', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));
		$filelist = array('no_value' => $vbphrase['all_scripts_glogging']);
		if ($files AND $files->valid())
		{
			foreach($files AS $file)
			{
				$file['script'] = htmlspecialchars_uni($file['script']);
				$filelist["$file[script]"] = $file['script'];
			}
		}

		$users = $assertor->assertQuery('vBForum:fetchDistinctUsers', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));
		$userlist = array('no_value' => $vbphrase['all_users']);
		if ($users AND $users->valid())
		{
			foreach($users AS $user)
			{
				$userlist["$user[userid]"] = $user['username'];
			}
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

		print_form_header('adminlog', 'view');
		print_table_header($vbphrase['control_panel_log_viewer']);
		print_select_row($vbphrase['log_entries_to_show_per_page'], 'perpage', $perpage_options, 15);
		print_select_row($vbphrase['show_only_entries_relating_to_script'], 'script', $filelist);

		print_select_row($vbphrase['show_only_entries_generated_by'], 'userid', $userlist);

		print_time_row($vbphrase['start_date'], 'startdate', 0, 0);
		print_time_row($vbphrase['end_date'], 'enddate', 0, 0);

		print_select_row($vbphrase['order_by_gcpglobal'], 'orderby', array('date' => $vbphrase['date'], 'user' => $vbphrase['user'], 'script' => $vbphrase['script']), 'date');
		print_submit_row($vbphrase['view'], 0);

		if (can_access_logs($vb5_config['SpecialUsers']['canpruneadminlog'], 1))
		{
			print_form_header('adminlog', 'prunelog');
			print_table_header($vbphrase['prune_control_panel_log']);
			print_label_row($vbphrase['remove_entries_relating_to_script'], '<select name="script" tabindex="1" class="bginput">' . construct_select_options($filelist) . '</select>', '', 'top', 'pscript');
			print_label_row($vbphrase['remove_entries_logged_by_user'], '<select name="userid" tabindex="1" class="bginput">' . construct_select_options($userlist) . '</select>', '', 'top', 'puserid');
			print_input_row($vbphrase['remove_entries_older_than_days'], 'daysprune', 30);
			print_submit_row($vbphrase['prune_control_panel_log'], 0);
		}
		else
		{
			echo '<p>' . $vbphrase['control_panel_log_pruning_permission_restricted'] . '</p>';
		}
	}
}

echo '<p class="smallfont" align="center"><a href="#" onclick="js_open_help(\'adminlog\', \'restrict\', \'\');">' . $vbphrase['want_to_access_grant_access_to_this_script'] . '</a></p>';

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>
