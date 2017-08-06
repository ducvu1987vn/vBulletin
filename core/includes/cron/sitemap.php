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
// if (!is_object($vbulletin->db))
// {
// 	exit;
// }

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

require_once (DIR . '/includes/class_sitemap.php');
$runner = new vB_SiteMapRunner_Cron(vB::get_registry());
$runner->set_cron_item($nextitem);

$status = $runner->check_environment();
if ($status['error'])
{
	// if an error has happened, display/log it if necessary and die

	if (VB_AREA == 'AdminCP')
	{
		print_stop_message($status['error']);
	}
	else if ($status['loggable'])
	{
		$rows = vB::getDbAssertor()->getRow('adminmessage', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_COUNT,
					'varname' => $status['error'],
					'status' => 'undone'
				));
		if ($rows['count'] == 0)
		{
			vB::getDbAssertor()->insert('adminmessage', array(
				'varname' => $status['error'],
				'dismissable' => 1,
				'script' => 'sitemap.php',
				'action' => 'buildsitemap',
				'execurl' => 'sitemap.php?do=buildsitemap',
				'method' => 'get',
				'dateline' => vB::getRequest()->getTimeNow(),
				'status' => 'undone'
			));
		}
	}

	exit;
}

$runner->generate();

if ($runner->is_finished)
{
	$log_text = $runner->written_filename . ', vbulletin_sitemap_index.xml';
}
else
{
	$log_text = $runner->written_filename;
}

log_cron_action($log_text, $nextitem, 1);

if (defined('IN_CONTROL_PANEL'))
{
	echo "<p>$log_text</p>";
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 24070 $
|| ####################################################################
\*======================================================================*/