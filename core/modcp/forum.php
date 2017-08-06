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
$phrasegroups = array('forum');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'moderatorid' => vB_Cleaner::TYPE_INT,
	'forumid'     => vB_Cleaner::TYPE_INT
));
log_admin_action(iif($vbulletin->GPC['moderatorid'] != 0, " moderator id = " . $vbulletin->GPC['moderatorid'], iif($vbulletin->GPC['forumid'] != 0, "forum id = " . $vbulletin->GPC['forumid'])));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['forum_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ################# Start edit password ###################
if ($_REQUEST['do'] == 'editpassword')
{
	if (empty($vbulletin->GPC['forumid']))
	{
		print_stop_message2('invalid_forum_specified');
	}

	if (!can_moderate($vbulletin->GPC['forumid'], 'cansetpassword'))
	{
		print_stop_message2('no_permission_forum_password');
	}
	$foruminfo = fetch_foruminfo($vbulletin->GPC['forumid']);
	if (!$foruminfo['canhavepassword'])
	{
		print_stop_message2('forum_cant_have_password');
	}

	print_form_header('forum', 'doeditpassword');
	print_table_header(construct_phrase($vbphrase['edit_password_gforum'], $foruminfo['title']), 2);
	print_input_row($vbphrase['forum_password'], 'forumpwd', $foruminfo['password']);
	print_yes_no_row($vbphrase['apply_password_to_children'], 'applypwdtochild', iif($foruminfo['password'], 0, 1));
	construct_hidden_code('forumid', $vbulletin->GPC['forumid']);
	print_submit_row($vbphrase['save']);
}

// ################# Start do edit password ###################
if ($_POST['do'] == 'doeditpassword')
{

	$vbulletin->input->clean_array_gpc('p', array(
		'forumpwd'        => vB_Cleaner::TYPE_STR,
		'applypwdtochild' => vB_Cleaner::TYPE_INT,
	));

	if (!can_moderate($vbulletin->GPC['forumid'], 'cansetpassword'))
	{
		print_stop_message2('no_permission_forum_password');
	}
	$foruminfo = fetch_foruminfo($vbulletin->GPC['forumid']);

	if (!$foruminfo['canhavepassword'])
	{
		print_stop_message2('forum_cant_have_password');
	}

	$forumdm =& datamanager_init('Forum', $vbulletin, vB_DataManager_Constants::ERRTYPE_CP);
	$forumdm->set_existing($foruminfo);
	$forumdm->setr('password', $vbulletin->GPC['forumpwd']);
	$forumdm->save();
	unset($forumdm);

	if ($vbulletin->GPC['applypwdtochild'])
	{
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "forum SET
				password = '" . $vbulletin->db->escape_string($vbulletin->GPC['forumpwd']) . "'
			WHERE FIND_IN_SET('" . $vbulletin->GPC['forumid'] . "', parentlist) AND (options & " . $vbulletin->bf_misc_forumoptions['canhavepassword'] . ")
		");
	}

	build_forum_permissions();

	print_stop_message2(array('saved_x_y_successfully', $foruminfo['forum'], $foruminfo['title']),'forum');
}

// ################# Start modify ###################
if ($_REQUEST['do'] == 'modify')
{
	/******** Global Announcements ****/
	if ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator'])
	{
		$forumannouncements = $vbulletin->db->query_read("
			SELECT title, FROM_UNIXTIME(startdate) AS startdate, FROM_UNIXTIME(enddate) AS enddate, announcementid
			FROM " . TABLE_PREFIX . "announcement AS announcement
			WHERE announcement.forumid = -1
		");

		print_form_header('', '');
		print_table_header($vbphrase['global_announcements'], 4);
		print_cells_row(array($vbphrase['title'], $vbphrase['start_date'], $vbphrase['end_date'], $vbphrase['modify']), 1);

		if ($vbulletin->db->num_rows($forumannouncements))
		{
			while ($announcement = $vbulletin->db->fetch_array($forumannouncements))
			{
				$cell = array(htmlspecialchars_uni($announcement['title']), $announcement['startdate'], $announcement['enddate']);
				$cell[] = construct_link_code($vbphrase['edit'], 'announcement.php?' . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;a=$announcement[announcementid]") .
					construct_link_code($vbphrase['delete'],'announcement.php?' . vB::getCurrentSession()->get('sessionurl') . "do=remove&amp;a=$announcement[announcementid]");
				print_cells_row($cell);
			}
		}
		else
		{
			print_description_row($vbphrase['no_global_announcements_defined'], '', 4, '', 'center');
		}
		print_description_row(construct_link_code($vbphrase['add_announcement'], 'announcement.php?' . vB::getCurrentSession()->get('sessionurl') . "do=add"), '', 4, 'thead', vB_Template_Runtime::fetchStyleVar('right'));
		print_table_footer();
	}

	/******** Forums List ****/
	//require_once(DIR . '/includes/functions_databuild.php');
	//cache_forums();

	$forums = array();
	foreach ($vbulletin->forumcache AS $forumid => $forum)
	{
		$forums["$forum[forumid]"] = construct_depth_mark($forum['depth'], '--') . ' ' . $forum['title'];
	}

	print_form_header('', '');
	print_table_header($vbphrase['forums'], 2);


	foreach ($vbulletin->forumcache AS $key => $forum)
	{
		$perms = fetch_permissions($forum['forumid']);
		if (!($perms & $vbulletin->bf_ugp_forumpermissions['canview']))
		{
			continue;
		}

		if ($forum['parentid'] == -1)
		{
			print_cells_row(array('&nbsp; ' . $vbphrase['title'], $vbphrase['modify']), 1, 'tcat');
		}

		$cell = array();
		$cell[] = '&nbsp; <b>' . construct_depth_mark($forum['depth'], '- - ') . '<a href="../' . fetch_seo_url('forum', $forum) . "\">$forum[title]</a></b>";
		$cell[] =
			'&nbsp;' .
			iif(can_moderate($forum['forumid'], 'canannounce'), construct_link_code($vbphrase['add_announcement'], 'announcement.php?' . vB::getCurrentSession()->get('sessionurl') . "do=add&amp;f=$forum[forumid]"), '') .
			' ' .
			iif(can_moderate($forum['forumid'], 'cansetpassword') AND ($forum['options'] & $vbulletin->bf_misc_forumoptions['canhavepassword']), construct_link_code($vbphrase['edit_password_gforum'], 'forum.php?' . vB::getCurrentSession()->get('sessionurl') . "do=editpassword&amp;f=$forum[forumid]"), '');

		print_cells_row($cell);

		if (can_moderate($forum['forumid'], 'canannounce'))
		{
			$forumannouncements = $vbulletin->db->query_read("SELECT * FROM " . TABLE_PREFIX . "announcement WHERE forumid = $forum[forumid]");
			if ($vbulletin->db->num_rows($forumannouncements))
			{
				$annc = "<ul><b>" . $vbphrase['announcements'] . ":</b><ul>\n";
				while ($announcement=$vbulletin->db->fetch_array($forumannouncements))
				{
					$annc .=
						"<li>$announcement[title] ".
						construct_link_code($vbphrase['edit'], 'announcement.php?' . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;a=$announcement[announcementid]") .
						' '.
						construct_link_code($vbphrase['delete'], 'announcement.php?' . vB::getCurrentSession()->get('sessionurl') . "do=remove&amp;a=$announcement[announcementid]") .
						'</li>';
				}
				$annc .= "</ul></ul>\n";
				print_description_row($annc);
			}
		}
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