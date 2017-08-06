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
define('CVS_REVISION', '$RCSfile$ - $Revision: 70576 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbphrase, $vbulletin;
$phrasegroups = array('attachment_image');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminimages'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################
$assertor = vB::getDbAssertor();
$vb5_config =& vB::getConfig();

print_cp_header($vbphrase['userpic_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'storage';
}

// ###################### Start checkpath #######################
function verify_upload_folder($imagepath)
{
	if ($imagepath == '')
	{
		print_stop_message2('please_complete_required_fields');
	}

	// Get realpath.
	$test = realpath($imagepath);

	if (!$test)
	{
		// If above fails, try relative path instead.
		$test = realpath(DIR . DIRECTORY_SEPARATOR . $imagepath);
	}

	if ($fp = @fopen($test . '/test.image', 'wb'))
	{
		fclose($fp);
		if (!@unlink($test . '/test.image'))
		{
			print_stop_message2(array('test_file_write_failed',  $imagepath));
		}
		return true;
	}
	else
	{
		print_stop_message2(array('test_file_write_failed',  $imagepath));
	}
}

$vbulletin->input->clean_array_gpc('r', array(
	'avatarpath'     => vB_Cleaner::TYPE_STR,
	'avatarurl'      => vB_Cleaner::TYPE_STR,
	'profilepicpath' => vB_Cleaner::TYPE_STR,
	'profilepicurl'  => vB_Cleaner::TYPE_STR,
	'sigpicpath'     => vB_Cleaner::TYPE_STR,
	'sigpicurl'      => vB_Cleaner::TYPE_STR,
	'dowhat'         => vB_Cleaner::TYPE_STR
));

// ###################### Swap from database to file system and vice versa ##########
if ($_REQUEST['do'] == 'storage')
{
	if ($vbulletin->options['usefileavatar'])
	{
		print_form_header('avatar', 'switchtype');
		print_table_header("$vbphrase[storage_type]: <span class=\"normal\">$vbphrase[user_pictures]</span>");
		print_description_row(construct_phrase($vbphrase['avatars_are_currently_being_served_from_the_filesystem_at_x'], '<b>' . $vbulletin->options['avatarpath'] . '</b>'));
		print_description_row(construct_phrase($vbphrase['profilepics_are_currently_being_served_from_the_filesystem_at_x'], '<b>' . $vbulletin->options['profilepicpath'] . '</b>'));
		print_description_row(construct_phrase($vbphrase['sigpics_are_currently_being_served_from_the_filesystem_at_x'], '<b>' . $vbulletin->options['sigpicpath'] . '</b>'));
		print_table_break();
		print_table_header('&nbsp;');
		print_radio_row($vbphrase['move_items_from_filesystem_into_database'], 'dowhat', array('FS_to_DB' => ''), 'FS_to_DB');

		print_table_break();
		print_table_header('&nbsp;');
		print_radio_row($vbphrase['move_avatars_to_a_different_directory'], 'dowhat', array('FS_to_FS1' => ''));
		print_radio_row($vbphrase['move_profilepics_to_a_different_directory'], 'dowhat', array('FS_to_FS2' => ''));
		print_radio_row($vbphrase['move_sigpics_to_a_different_directory'], 'dowhat', array('FS_to_FS3' => ''));

		print_submit_row($vbphrase['go'], 0);
	}
	else
	{
		$vbulletin->GPC['dowhat'] = 'DB_to_FS';
		$_REQUEST['do'] = 'switchtype';
	}


}

// ###################### Swap from database to file system and vice versa ##########
if ($_REQUEST['do'] == 'switchtype')
{
	if ($vbulletin->GPC['dowhat'] == 'FS_to_DB')
	{
		// redirect straight through to image mover
		$vbulletin->GPC['avatarpath'] = $vbulletin->options['avatarpath'];
		$vbulletin->GPC['avatarurl'] = $vbulletin->options['avatarurl'];
		$vbulletin->GPC['profilepicpath'] = $vbulletin->options['profilepicpath'];
		$vbulletin->GPC['profilepicurl'] = $vbulletin->options['profilepicurl'];
		$vbulletin->GPC['sigpicpath'] = $vbulletin->options['sigpicpath'];
		$vbulletin->GPC['sigpicurl'] = $vbulletin->options['sigpicurl'];
		$_POST['do'] = 'doswitchtype';
	}
	else
	{
		// show a form to allow user to specify file path
		print_form_header('avatar', 'doswitchtype');
		construct_hidden_code('dowhat', $vbulletin->GPC['dowhat']);

		switch($vbulletin->GPC['dowhat'])
		{
			case 'FS_to_FS1':
				print_table_header($vbphrase['move_avatars_to_a_different_directory']);
				print_description_row(construct_phrase($vbphrase['avatars_are_currently_being_served_from_the_filesystem_at_x'], '<b>' . $vbulletin->options['avatarpath'] . '</b>'));
				print_input_row($vbphrase['avatar_file_path_dfn'], 'avatarpath', $vbulletin->options['avatarpath']);
				print_input_row($vbphrase['url_to_avatars_relative_to_your_forums_home_page'], 'avatarurl', $vbulletin->options['avatarurl']);
				break;

			case 'FS_to_FS2':
				print_table_header($vbphrase['move_profilepics_to_a_different_directory']);
				print_description_row(construct_phrase($vbphrase['profilepics_are_currently_being_served_from_the_filesystem_at_x'], '<b>' . $vbulletin->options['profilepicpath'] . '</b>'));
				print_input_row($vbphrase['profilepic_file_path_dfn'], 'profilepicpath', $vbulletin->options['profilepicpath']);
				print_input_row($vbphrase['url_to_profilepics_relative_to_your_forums_home_page'], 'profilepicurl', $vbulletin->options['profilepicurl']);
				break;

			case 'FS_to_FS3':
				print_table_header($vbphrase['move_sigpics_to_a_different_directory']);
				print_description_row(construct_phrase($vbphrase['sigpics_are_currently_being_served_from_the_filesystem_at_x'], '<b>' . $vbulletin->options['sigpicpath'] . '</b>'));
				print_input_row($vbphrase['sigpic_file_path_dfn'], 'sigpicpath', $vbulletin->options['sigpicpath']);
				print_input_row($vbphrase['url_to_sigpics_relative_to_your_forums_home_page'], 'sigpicurl', $vbulletin->options['sigpicurl']);
				break;

			default:
				print_table_header($vbphrase['move_items_from_database_into_filesystem']);
				print_description_row($vbphrase['images_are_currently_being_served_from_the_database'], false, 2, '', 'center');
				print_input_row($vbphrase['avatar_file_path_dfn'], 'avatarpath', $vbulletin->options['avatarpath']);
				print_input_row($vbphrase['url_to_avatars_relative_to_your_forums_home_page'], 'avatarurl', $vbulletin->options['avatarurl']);
				print_input_row($vbphrase['profilepic_file_path_dfn'], 'profilepicpath', $vbulletin->options['profilepicpath']);
				print_input_row($vbphrase['url_to_profilepics_relative_to_your_forums_home_page'], 'profilepicurl', $vbulletin->options['profilepicurl']);
				print_input_row($vbphrase['sigpic_file_path_dfn'], 'sigpicpath', $vbulletin->options['sigpicpath']);
				print_input_row($vbphrase['url_to_sigpics_relative_to_your_forums_home_page'], 'sigpicurl', $vbulletin->options['sigpicurl']);
		}

		print_submit_row($vbphrase['go']);
	}
}

// ############### Move files from database to file system and vice versa ###########
if ($_POST['do'] == 'doswitchtype')
{
	$vbulletin->GPC['avatarpath'] = preg_replace('/(\/|\\\)$/s', '', $vbulletin->GPC['avatarpath']);
	$vbulletin->GPC['avatarurl'] = preg_replace('/(\/|\\\)$/s', '', $vbulletin->GPC['avatarurl']);
	$vbulletin->GPC['profilepicpath'] = preg_replace('/(\/|\\\)$/s', '', $vbulletin->GPC['profilepicpath']);
	$vbulletin->GPC['profilepicurl'] = preg_replace('/(\/|\\\)$/s', '', $vbulletin->GPC['profilepicurl']);
	$vbulletin->GPC['sigpicpath'] = preg_replace('/(\/|\\\)$/s', '', $vbulletin->GPC['sigpicpath']);
	$vbulletin->GPC['sigpicurl'] = preg_replace('/(\/|\\\)$/s', '', $vbulletin->GPC['sigpicurl']);

	if ($vbulletin->GPC['dowhat'] == 'FS_to_FS1')
	{
		$imagepath =& $vbulletin->GPC['avatarpath'];
		$imageurl =& $vbulletin->GPC['avatarurl'];
		$path = 'avatarpath';
		$url = 'avatarurl';
	}
	else if ($vbulletin->GPC['dowhat'] == 'FS_to_FS2')
	{
		$imagepath =& $vbulletin->GPC['profilepicpath'];
		$imageurl =& $vbulletin->GPC['profilepicurl'];
		$path = 'profilepicpath';
		$url = 'profilepicurl';
	}
	else
	{
		$imagepath =& $vbulletin->GPC['sigpicpath'];
		$imageurl =& $vbulletin->GPC['sigpicurl'];
		$path = 'sigpicpath';
		$url = 'sigpicurl';
	}

	switch($vbulletin->GPC['dowhat'])
	{
		// #############################################################################
		// update image file path
		case 'FS_to_FS1':
		case 'FS_to_FS2':
		case 'FS_to_FS3':

			if ($imagepath === $vbulletin->options["$path"] AND $imageurl === $vbulletin->options["$url"])
			{
				// new and old path are the same - show error
				print_stop_message2('invalid_file_path_specified');
			}
			else
			{
				// new and old paths are different - check the directory is valid
				verify_upload_folder($imagepath);
				$oldpath = $vbulletin->options["$path"];

				$assertor->assertQuery('vBForum:updateSettingValues',
					array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
						'path' => $path,
						'url' => $url,
						'imagepath' => $imagepath,
						'imageurl' => $imageurl
					)
				);
				vB::getDatastore()->build_options();

				// show message
				print_stop_message2(array('your_vb_settings_have_been_updated_to_store_images_in_x', $imagepath, $oldpath));
			}

			break;

		// #############################################################################
		// move userpics from database to filesystem
		case 'DB_to_FS':

			// check path is valid
			verify_upload_folder($vbulletin->GPC['avatarpath']);
			verify_upload_folder($vbulletin->GPC['profilepicpath']);
			verify_upload_folder($vbulletin->GPC['sigpicpath']);

			$assertor->assertQuery('vBForum:updateSettingValuesByVarname',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'avatarpath' => $vbulletin->GPC['avatarpath'],
					'avatarurl' => $vbulletin->GPC['avatarurl'],
					'profilepicpath' => $vbulletin->GPC['profilepicpath'],
					'profilepicurl' => $vbulletin->GPC['profilepicurl'],
					'sigpicpath' => $vbulletin->GPC['sigpicpath'],
					'sigpicurl' => $vbulletin->GPC['sigpicurl']
				)
			);
			vB::getDatastore()->build_options();

			break;
	}

	// #############################################################################

	print_form_header('avatar', 'domoveavatar');
	print_table_header(construct_phrase($vbphrase['edit_storage_type'], "<span class=\"normal\">" . $vbphrase['user_pictures'] . "</span>"));
	construct_hidden_code('dowhat', $vbulletin->GPC['dowhat']);

	if ($vbulletin->GPC['dowhat'] == 'DB_to_FS')
	{
		print_description_row($vbphrase['we_are_ready_to_attempt_to_move_your_images_from_database_to_filesystem']);
	}
	else
	{
		print_description_row($vbphrase['we_are_ready_to_attempt_to_move_your_images_from_filesystem_to_database']);
	}

	print_input_row($vbphrase['number_of_users_to_process_per_cycle_gattachment_image'], 'perpage', 300, 1, 5);
	print_submit_row($vbphrase['go']);

}

// ################### Move avatars ######################################
if ($_REQUEST['do'] == 'domoveavatar')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'perpage' => vB_Cleaner::TYPE_INT,
		'startat' => vB_Cleaner::TYPE_INT,
	));

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	if ($vbulletin->GPC['perpage'] < 1)
	{
		$vbulletin->GPC['perpage'] = 10;
	}

	if ($vbulletin->GPC['startat'] < 0)
	{
		$vbulletin->GPC['startat'] = 0;
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	$images = $assertor->assertQuery('vBForum:fetchAvatarInfo',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			vB_dB_Query::PARAM_LIMITSTART => $vbulletin->GPC['startat'],
			vB_dB_Query::PARAM_LIMIT => $vbulletin->GPC['perpage']
		)
	);

	//while ($image = $vbulletin->db->fetch_array($images))
	if ($images AND $images->valid())
	{
		foreach ($images AS $image)
		{
			if ($vb5_config['Misc']['debug'])
			{
				echo "<strong>$vbphrase[user] : $image[userid]</strong><br />";
				if ($image['afilename'])
				{
					echo "&nbsp;&nbsp;$vbphrase[avatar] : $image[afilename]<br />";
				}
				if ($image['pfilename'])
				{
					echo "&nbsp;&nbsp;$vbphrase[profile_picture] : $image[pfilename]<br />";
				}
				if ($image['sfilename'])
				{
					echo "&nbsp;&nbsp;$vbphrase[signature_picture] : $image[pfilename]<br />";
				}
			}

			if (!$vbulletin->options['usefileavatar'])
			{
				$vbulletin->options['usefileavatar'] = true;

				// Converting FROM mysql TO fs
				if (!empty($image['afiledata']))
				{
					$userpic = vB_DataManager_Userpic::fetch_library($vbulletin, vB_DataManager_Constants::ERRTYPE_CP, 'userpic_avatar', true);
					$userpic->set_existing($image);
					$userpic->setr('filedata', $image['afiledata']);
					if (!$userpic->save())
					{
						print_stop_message2(array('error_writing_x',  $image['afilename']));
					}
				}

				if (!empty($image['pfiledata']))
				{
					$userpic = vB_DataManager_Userpic::fetch_library($vbulletin, vB_DataManager_Constants::ERRTYPE_CP, 'userpic_profilepic', true);
					$userpic->set_existing($image);
					$userpic->setr('filedata', $image['pfiledata']);
					if (!$userpic->save())
					{
						print_stop_message2(array('error_writing_x',  $image['pfilename']));
					}
				}

				if (!empty($image['sfiledata']))
				{
					$userpic = vB_DataManager_Userpic::fetch_library($vbulletin, vB_DataManager_Constants::ERRTYPE_CP, 'userpic_sigpic', true);
					$userpic->set_existing($image);
					$userpic->setr('filedata', $image['sfiledata']);
					if (!$userpic->save())
					{
						print_stop_message2(array('error_writing_x',  $image['sfilename']));
					}
				}
				unset($userpic);

				$vbulletin->options['usefileavatar'] = false;
			}
			else
			{
				$vbulletin->options['usefileavatar'] = false;

				// Converting FROM fs TO mysql
				if (!empty($image['afilename']))
				{
					$path = $vbulletin->options['avatarpath'] . "/$image[afilename]";
					$thumbpath = $vbulletin->options['avatarpath'] . "/thumbs/$image[afilename]";
					chdir(DIR);
					$filedata = @file_get_contents($path);
					if ($filedata)
					{
						$userpic = new vB_Datamanager_Userpic_Avatar($vbulletin, vB_DataManager_Constants::ERRTYPE_CP, 'userpic');
						$userpic->set_existing($image);
						$userpic->setr('filedata', $filedata);
						$userpic->save();
						unset($userpic);
					}
				}
				if (!empty($image['pfilename']))
				{
					$path = $vbulletin->options['profilepicpath'] . "/profilepic$image[userid]_$image[profilepicrevision].gif";
					$filedata = @file_get_contents($path);
					if ($filedata)
					{
						$userpic = new vB_Datamanager_Userpic_Profilepic($vbulletin, vB_DataManager_Constants::ERRTYPE_CP, 'userpic');
						$userpic->set_existing($image);
						$userpic->setr('filedata', $filedata);
						$userpic->save();
						unset($userpic);
					}
				}

				if (!empty($image['sfilename']))
				{
					$path = $vbulletin->options['sigpicpath'] . "/sigpic$image[userid]_$image[sigpicrevision].gif";
					$filedata = @file_get_contents($path);
					if ($filedata)
					{
						$userpic = new vB_Datamanager_Userpic_Sigpic($vbulletin, vB_DataManager_Constants::ERRTYPE_CP, 'userpic');
						$userpic->set_existing($image);
						$userpic->setr('filedata', $filedata);
						$userpic->save();
						unset($userpic);
					}
				}

				$vbulletin->options['usefileavatar'] = true;
			}
			$lastuser = $image['userid'];
		}
	}

	$userid = $assertor->getRow('vBForum:fetchUserIdByAvatar', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'lastuser' => $lastuser));

	if ($lastuser AND $userid)
	{
		$args = array();
		parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
		$args['do'] = 'domoveavatar';
		$args['startat'] = $finishat;
		$args['pp'] = $vbulletin->GPC['perpage'];
		print_cp_redirect2('avatar', $args);

		echo "<p><a href=\"avatar.php?" . vB::getCurrentSession()->get('sessionurl') . "do=domoveavatar&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{

		if (!$vbulletin->options['usefileavatar'])
		{
			$assertor->assertQuery('setting',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE, 'value' => 1,
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'varname', 'value' => 'usefileavatar', 'operator' => vB_dB_Query::OPERATOR_EQ)
					)
				)
			);
			vB::getDatastore()->build_options();

			$assertor->assertQuery('vBForum:clearPictureData', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD));
			$assertor->assertQuery('vBForum:optimizePictureTables', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD));

			print_stop_message2('images_moved_to_the_filesystem','avatar', array('do' => 'storage'));
		}
		else
		{
			$assertor->assertQuery('setting',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE, 'value' => 0,
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'varname', 'value' => 'usefileavatar', 'operator' => vB_dB_Query::OPERATOR_EQ)
					)
				)
			);
			vB::getDatastore()->build_options();
			print_stop_message2('images_moved_to_the_database','avatar', array('do' => 'storage'));
		}
	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 70576 $
|| ####################################################################
\*======================================================================*/
?>
