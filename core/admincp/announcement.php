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
global $vbulletin, $phrasegroups, $specialtemplates,$vbphrase, $permissions;
$phrasegroups = array('posting');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/adminfunctions_announcement.php');
// ############################# LOG ACTION ###############################
if (!vB::getUserContext()->hasAdminPermission('ismoderator'))
{
	print_cp_no_permission();
}

$vbulletin->input->clean_array_gpc('r', array(
	'announcementid' => vB_Cleaner::TYPE_INT
));
log_admin_action(iif($vbulletin->GPC['announcementid'] != 0, "announcement id = " . $vbulletin->GPC['announcementid']));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['announcement_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start add / edit #######################
if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'nodeid'        => vB_Cleaner::TYPE_INT,
		'newnodeid'     => vB_Cleaner::TYPE_ARRAY,
		'announcementid' => vB_Cleaner::TYPE_INT
	));

	print_form_header('announcement', 'update');

	$datastore = vB::getDatastore();
	if ($_REQUEST['do'] == 'add')
	{
		// set default values
		if (is_array($vbulletin->GPC['newnodeid']))
		{
			foreach($vbulletin->GPC['newnodeid'] AS $key => $val)
			{
				$vbulletin->GPC['nodeid'] = intval($key);
			}
		}

		$timeNow = vB::getRequest()->getTimeNow();
		$announcement = array(
			'startdate'           => $timeNow,
			'enddate'             => ($timeNow + 86400 * 31),
			'nodeid'             => $vbulletin->GPC['nodeid'],
			'announcementoptions' => 29
		);
		print_table_header($vbphrase['post_new_announcement_gposting']);
	}
	else
	{
		// query announcement
		$announcement = vB::getDbAssertor()->assertQuery("vBForum:announcement", array(
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'announcementid', 'operator' => vB_dB_Query::OPERATOR_EQ, 'value' => $vbulletin->GPC['announcementid'])
			)
		));


		$options = $datastore->getValue('options');
		if ($announcement AND !$announcement->valid())
		{
			print_stop_message2(array('invalidid', $vbphrase['announcement']));
		}

		$userContext = vB::getUserContext();
		$announcement = $announcement->current();
		if (!$userContext->hasAdminPermission('cancontrolpanel'))
		{
			if ($announcement['nodeid'] == -1 AND !$userContext->hasAdminPermission('ismoderator'))
			{
				print_table_header($vbphrase['no_permission_global_announcement']);
				print_table_break();
			}
			else if ($announcement['nodeid'] != -1 AND !can_moderate($announcement['nodeid'], 'canannounce'))
			{
				print_table_header($vbphrase['no_permission_announcement']);
				print_table_break();
			}
		}

		construct_hidden_code('announcementid', $vbulletin->GPC['announcementid']);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['announcement'], vB_String::htmlSpecialCharsUni($announcement['title']), $announcement['announcementid']));
	}

	print_channel_chooser($vbphrase['channel_and_subchannels'], 'nodeid', $announcement['nodeid'], $vbphrase['all_channels']);
	print_input_row($vbphrase['title'], 'title', $announcement['title']);

	print_time_row($vbphrase['start_date'], 'startdate', $announcement['startdate'], 0);
	print_time_row($vbphrase['end_date'], 'enddate', $announcement['enddate'], 0);

	print_textarea_row($vbphrase['text_gcpglobal'], 'pagetext', $announcement['pagetext'], 20, '75" style="width:100%');

	if ($vbulletin->GPC['announcementid'])
	{
		print_yes_no_row($vbphrase['reset_views_counter'], 'reset_views', 0);
	}

	$announcementOptions = $datastore->getValue('bf_misc_announcementoptions');
	print_yes_no_row($vbphrase['allow_bbcode'], 'announcementoptions[allowbbcode]', ($announcement['announcementoptions'] & $announcementOptions['allowbbcode'] ? 1 : 0));
	print_yes_no_row($vbphrase['allow_smilies'], 'announcementoptions[allowsmilies]', ($announcement['announcementoptions'] & $announcementOptions['allowsmilies'] ? 1 : 0));
	print_yes_no_row($vbphrase['allow_html'], 'announcementoptions[allowhtml]', ($announcement['announcementoptions'] & $announcementOptions['allowhtml'] ? 1 : 0));
	print_yes_no_row($vbphrase['automatically_parse_links_in_text'], 'announcementoptions[parseurl]', ($announcement['announcementoptions'] & $announcementOptions['parseurl'] ? 1 : 0));
	print_yes_no_row($vbphrase['show_your_signature_gposting'], 'announcementoptions[signature]', ($announcement['announcementoptions'] & $announcementOptions['signature'] ? 1 : 0));

	print_submit_row($vbphrase['save']);
}

// ###################### Start insert #######################
if ($_POST['do'] == 'update')
{
	$assertor = vB::getDbAssertor();
	$vbulletin->input->clean_array_gpc('p', array(
		'announcementid'      => vB_Cleaner::TYPE_UINT,
		'title'               => vB_Cleaner::TYPE_STR,
		'startdate'           => vB_Cleaner::TYPE_UNIXTIME,
		'enddate'             => vB_Cleaner::TYPE_UNIXTIME,
		'pagetext'            => vB_Cleaner::TYPE_STR,
		'nodeid'             => vB_Cleaner::TYPE_INT,
		'announcementoptions' => vB_Cleaner::TYPE_ARRAY_BOOL,
		'reset_views'         => vB_Cleaner::TYPE_BOOL
	));

	$userContext = vB::getUserContext();
	if (!$userContext->hasAdminPermission('cancontrolpanel'))
	{
		if ($vbulletin->GPC['nodeid'] == -1 AND !$userContext->hasAdminPermission('ismoderator'))
		{
			print_stop_message2('no_permission_global_announcement');
		}
		else if ($vbulletin->GPC['nodeid'] != -1 AND !can_moderate($vbulletin->GPC['nodeid'], 'canannounce'))
		{
			print_stop_message2('no_permission_announcement');
		}
	}

	// query original data
	$original_data = $assertor->assertQuery('vBForum:announcement', array(
		vB_dB_Query::CONDITIONS_KEY => array(
			array('field' => 'announcementid', 'operator' => vB_dB_Query::OPERATOR_EQ, 'value' => $vbulletin->GPC['announcementid'])
		)
	));

	$options = vB::getDatastore()->getValue('options');
	if ($vbulletin->GPC['announcementid'] AND !$original_data->valid())
	{
		print_stop_message2(array('invalidid', $vbphrase['announcement']));
	}

	if (!trim($vbulletin->GPC['title']))
	{
		$vbulletin->GPC['title'] = $vbphrase['announcement'];
	}

	$anncdata = new vB_DataManager_Announcement($vbulletin, vB_DataManager_Constants::ERRTYPE_CP);
	$original_data = $original_data->current();
	if ($vbulletin->GPC['announcementid'])
	{
		$anncdata->set_existing($original_data);

		if ($vbulletin->GPC['reset_views'])
		{
			define('RESET_VIEWS', true);
			$anncdata->set('views', 0);
		}
	}
	else
	{
		$anncdata->set('userid', $userContext->fetchUserId());
	}

	$anncdata->set('title', $vbulletin->GPC['title']);
	$anncdata->set('pagetext', $vbulletin->GPC['pagetext']);
	$anncdata->set('nodeid', $vbulletin->GPC['nodeid']);
	$anncdata->set('startdate', $vbulletin->GPC['startdate']);
	$anncdata->set('enddate', $vbulletin->GPC['enddate'] + 86399);

	foreach ($vbulletin->GPC['announcementoptions'] AS $key => $val)
	{
		$anncdata->set_bitfield('announcementoptions', $key, $val);
	}

	$announcementid = $anncdata->save();

	if ($original_data)
	{
		if ($vbulletin->GPC['reset_views'])
		{
			// @TODO define how we dealing with this...
			$assertor->delete("vBForum:announcementread", array(
				'announcementid' => $vbulletin->GPC['announcementid']
			));
		}
	}

	vB_Cache::instance(vB_Cache::CACHE_STD)->event('vB_AnnouncementChg');
	print_stop_message2(array('saved_announcement_x_successfully',  vB_String::htmlSpecialCharsUni($vbulletin->GPC['title'])), 'announcement');
}

// ###################### Start Remove #######################

if ($_REQUEST['do'] == 'remove')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'announcementid' 	=> vB_Cleaner::TYPE_UINT
	));

	print_delete_confirmation('announcement', $vbulletin->GPC['announcementid'], 'announcement', 'kill', 'announcement');
}

// ###################### Start Kill #######################

if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'announcementid' 	=> vB_Cleaner::TYPE_UINT
	));

	$announcement = vB::getDbAssertor()->assertQuery('vBForum:announcement', array(
		vB_dB_Query::CONDITIONS_KEY => array(
			array('field' => 'announcementid', 'operator' => vB_dB_Query::OPERATOR_EQ, 'value' => $vbulletin->GPC['announcementid'])
		)
	));

	if ($announcement AND $announcement->valid())
	{
		$announcement = $announcement->current();
		$anncdata =& datamanager_init('Announcement', $vbulletin, vB_DataManager_Constants::ERRTYPE_CP);
		$anncdata->set_existing($announcement);
		$anncdata->delete();

		vB_Cache::instance(vB_Cache::CACHE_STD)->event('vB_AnnouncementChg');
		print_stop_message2('deleted_announcement_successfully', 'announcement', array('do'=>'modify'));
	}
	else
	{
		$options = vB::getDatastore()->getValue('options');
		print_stop_message2(array('invalidid', $vbphrase['announcement']));
	}
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{
	$ans = vB::getDbAssertor()->assertQuery('vBForum:fetchModifyAnnouncements', array());
	$globalannounce = array();
	$ancache = array();
	if ($ans AND $ans->valid())
	{
		while ($ans->valid())
		{
			$an = $ans->current();
			if (!$an['username'])
			{
				$an['username'] = $vbphrase['guest'];
			}
			if ($an['nodeid'] == -1)
			{
				$globalannounce[] = $an;
			}
			else
			{
				$ancache[$an['nodeid']][$an['announcementid']] = $an;
			}
			$ans->next();
		}
	}

	//require_once(DIR . '/includes/functions_databuild.php');
	//cache_forums();
	print_form_header('announcement', 'add');
	print_table_header($vbphrase['announcement_manager'], 3);

	// display global announcments
	$options = vB::getDatastore()->getValue('options');
	if (is_array($globalannounce) AND !empty($globalannounce))
	{
		$cell = array();
		$cell[] = '<b>' . $vbphrase['global_announcements'] . '</b>';
		$announcements = '';
		foreach($globalannounce AS $announcementid => $announcement)
		{
			$announcements .=
			"\t\t<li><b>" . htmlspecialchars_uni($announcement['title']) . "</b> ($announcement[username]) ".
			construct_link_code($vbphrase['edit'], "announcement.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&a=$announcement[announcementid]").
			construct_link_code($vbphrase['delete'], "announcement.php?" . vB::getCurrentSession()->get('sessionurl') . "do=remove&a=$announcement[announcementid]").
			'<span class="smallfont">(' . ' ' .
				construct_phrase($vbphrase['x_to_y'], vbdate($options['dateformat'], $announcement['startdate']), vbdate($options['dateformat'], $announcement['enddate'])) .
			")</span></li>\n";
		}
		$cell[] = $announcements;
		$cell[] = '<input type="submit" class="button" value="' . $vbphrase['new'] . '" title="' . $vbphrase['post_new_announcement_gposting'] . '" />';
		print_cells_row($cell, 0, '', -1);
		print_table_break();
	}
	$channels = vB_Api::instanceInternal('search')->getChannels(true);

	// display forum-specific announcements
	foreach($channels AS $key => $channel)
	{
		if ($channel['parentid'] == 0)
		{
			print_cells_row(array($vbphrase['channel'], $vbphrase['announcements'], ''), 1, 'tcat', 1);
		}
		$cell = array();
		$cell[] = "<b>" . construct_depth_mark($channel['depth'], '- - ', '- - ') . "<a href=\"../announcement.php?" . vB::getCurrentSession()->get('sessionurl') . "n=$channel[nodeid]\" target=\"_blank\">$channel[htmltitle]</a></b>";
		$announcements = '';
		if (is_array($ancache[$channel['nodeid']]))
		{
			foreach($ancache[$channel['nodeid']] AS $announcementid => $announcement)
			{
				$announcements .=
				"\t\t<li><b>" . htmlspecialchars_uni($announcement['title']) . "</b> ($announcement[username]) ".
				construct_link_code($vbphrase['edit'], "announcement.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&a=$announcement[announcementid]").
				construct_link_code($vbphrase['delete'], "announcement.php?" . vB::getCurrentSession()->get('sessionurl') . "do=remove&a=$announcement[announcementid]").
				'<span class="smallfont">('.
					construct_phrase($vbphrase['x_to_y'], vbdate($options['dateformat'], $announcement['startdate']), vbdate($options['dateformat'], $announcement['enddate'])) .
				")</span></li>\n";
			}
		}
		$cell[] = $announcements;
		$cell[] = '<input type="submit" class="button" value="' . $vbphrase['new'] . '" name="newnodeid[' . $channel['nodeid'] . ']" title="' . $vbphrase['post_new_announcement_gposting'] . '" />';
		print_cells_row($cell, 0, '', -1);
	}

	print_table_footer();
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>
