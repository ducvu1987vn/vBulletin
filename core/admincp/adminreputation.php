<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 70662 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbphrase, $reputation, $vbulletin;
$phrasegroups = array('reputation', 'user', 'reputationlevel');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/adminfunctions_reputation.php');
$assertor = vB::getDbAssertor();

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

$vbulletin->input->clean_array_gpc('r', array(
	'reputationlevelid' => vB_Cleaner::TYPE_INT,
	'minimumreputation' => vB_Cleaner::TYPE_INT,
));

// ############################# LOG ACTION ###############################
log_admin_action(iif($vbulletin->GPC['reputationlevelid'] != 0, " reputationlevel id = " . $vbulletin->GPC['reputationlevelid'], iif($vbulletin->GPC['minimumreputation'] != 0, "minimum reputation = " . $vbulletin->GPC['minimumreputation'], '')));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['user_reputation_manager_greputation']);

// *************************************************************************************************

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// *************************************************************************************************

if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'reputationlevelid' => vB_Cleaner::TYPE_INT
	));

	print_form_header('adminreputation', 'update');
	if ($vbulletin->GPC['reputationlevelid'])
	{
		$reputationlevel = $assertor->getRow('vBForum:reputationlevel', array('reputationlevelid' => $vbulletin->GPC['reputationlevelid']));

		$level = 'reputation' . $reputationlevel['reputationlevelid'];

		$phrase = $assertor->getRow('vBForum:phrase', array('languageid' => 0, 'fieldname' => 'reputationlevel', 'varname' => $level));
		if (is_array($phrase) AND !isset($phrase['errors']))
		{
			$reputationlevel['level'] = $phrase['text'];
			$reputationlevel['levelvarname'] = 'reputation' . $reputationlevel['reputationlevelid'];
		}

		construct_hidden_code('reputationlevelid', $vbulletin->GPC['reputationlevelid']);
		construct_hidden_code('oldminimum', $reputation['minimumreputation']);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['reputation_level_guser'], '<i>' . htmlspecialchars_uni($reputationlevel['level']) . '</i>', $reputationlevel['minimumreputation']));
	}
	else
	{
		print_table_header($vbphrase['add_new_reputation_level']);
	}

	if ($reputationlevel['level'])
	{
		print_input_row($vbphrase['description_gcpglobal'] . '<dfn>' . construct_link_code($vbphrase['translations'], "phrase.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&fieldname=reputationlevel&varname=$reputationlevel[levelvarname]&t=1", 1)  . '</dfn>', 'level', $reputationlevel['level']);
	}
	else
	{
		print_input_row($vbphrase['description_gcpglobal'], 'level');
	}
	print_input_row($vbphrase['minimum_reputation_level'], 'reputationlevel[minimumreputation]', $reputationlevel['minimumreputation']);
	print_submit_row(iif($vbulletin->GPC['reputationlevelid'], $vbphrase['update'], $vbphrase['save']));
}

// *************************************************************************************************

if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'reputationlevelid' => vB_Cleaner::TYPE_INT,
		'oldminimum'        => vB_Cleaner::TYPE_INT,
		'reputationlevel'   => vB_Cleaner::TYPE_ARRAY,
		'level'             => vB_Cleaner::TYPE_STR,
	));

	$vbulletin->GPC['reputationlevel']['minimumreputation'] = intval($vbulletin->GPC['reputationlevel']['minimumreputation']);

	$queryParams = array(vB_dB_Query::CONDITIONS_KEY => array(
		array('field' => 'minimumreputation', 'value' => $vbulletin->GPC['reputationlevel']['minimumreputation'], vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_EQ)
	));
	if ($vbulletin->GPC['reputationlevelid'])
	{
		// edit
		$queryParams[vB_dB_Query::CONDITIONS_KEY][] = array('field' => 'reputationlevelid', 'value' => $vbulletin->GPC['reputationlevelid'], vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_NE);
	}

	$existing = $assertor->getRow('vBForum:reputationlevel', $queryParams);
	if (!$existing)
	{
		if ($vbulletin->GPC['reputationlevelid'])
		{
			// edit
			$updated = $assertor->update('vBForum:reputationlevel', array('minimumreputation' => $vbulletin->GPC['reputationlevel']['minimumreputation']), array('reputationlevelid' => $vbulletin->GPC['reputationlevelid']));

			if ($vbulletin->GPC['oldminimum'] != $vbulletin->GPC['reputationlevel']['minimumreputation'])
			{
				// need to update user table
				build_reputationids();
			}
		}
		else
		{
			$rLevelId = $assertor->insert('vBForum:reputationlevel', array('minimumreputation' => $vbulletin->GPC['reputationlevel']['minimumreputation']));
			if ($rLevelId AND !isset($rLevelId['errors']))
			{
				$vbulletin->GPC['reputationlevelid'] = array_pop($rLevelId);
				build_reputationids();
			}
			else
			{
				print_stop_message2('could_not_create_reputationlevel', 'adminreputation', array('do'=>'modify'));
			}
		}

		$options = vB::getDatastore()->getValue('options');
		try
		{
			$userInfo = vB_Api::instanceInternal('user')->fetchUserInfo();
		}
		catch (vB_Exception_Api $ex)
		{
			print_stop_message2($ex->getMessage(), 'adminreputation', array('do'=>'modify'));
		}

		$assertor->assertQuery('vBForum:reputationLevelPhraseReplace', array(
			'languageid' => 0,
			'fieldname' => 'reputationlevel',
			'varname' => ('reputation' . $vbulletin->GPC['reputationlevelid']),
			'text' => $vbulletin->GPC['level'],
			'product' => 'vbulletin',
			'username' => $userInfo['username'],
			'dateline' => vB::getRequest()->getTimeNow(),
			'version' => $options['templateversion']
		));

		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language();

		print_stop_message2(array('saved_reputation_level_x_successfully',  htmlspecialchars_uni($vbulletin->GPC['level'])), 'adminreputation', array('do'=>'modify'));
	}
	else
	{
		print_stop_message2('no_permission_duplicate_reputation', 'adminreputation', array('do'=>'modify'));
	}
}

// *************************************************************************************************

if ($_REQUEST['do'] == 'remove')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'minimumreputation'	=> vB_Cleaner::TYPE_INT
	));

	print_form_header('adminreputation', 'kill');
	construct_hidden_code('minimumreputation', $vbulletin->GPC['minimumreputation']);
	print_table_header($vbphrase['confirm_deletion_gcpglobal']);
	print_description_row(construct_phrase($vbphrase['are_you_sure_you_want_to_delete_the_reputation_level_x'], '<i>' . $vbulletin->GPC['minimumreputation'] . '</i>'));
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
}

// *************************************************************************************************

if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'minimumreputation'	=> vB_Cleaner::TYPE_INT
	));

	$reputationlevel = $assertor->getRow('vBForum:reputationlevel', array('minimumreputation' => $vbulletin->GPC['minimumreputation']));
	$assertor->delete('vBForum:phrase', array(
		'fieldname' => 'reputationlevel',
		'varname' => ('reputation' . $reputationlevel['reputationlevelid'])
	));

	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language();

	$assertor->delete('vBForum:reputationlevel', array(
		'minimumreputation' => $vbulletin->GPC['minimumreputation']
	));

	build_reputationids();

	print_stop_message2('deleted_reputation_level_successfully', 'adminreputation', array('do'=>'modify'));
}

// *************************************************************************************************

if ($_POST['do'] == 'updateminimums')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'reputation' 	=> vB_Cleaner::TYPE_ARRAY
	));

	if (is_array($vbulletin->GPC['reputation']))
	{
		$found = array();
		foreach($vbulletin->GPC['reputation'] AS $index => $value)
		{
			if (isset($found["$value"]))
			{
				print_stop_message2('no_permission_duplicate_reputation');
			}
			else
			{
				$found["$value"] = 1;
			}
		}

		foreach ($vbulletin->GPC['reputation'] AS $index => $value)
		{
			$assertor->update('vBForum:reputationlevel',
				array('minimumreputation' => intval($value)),
				array('reputationlevelid' => intval($index))
			);
		}

		build_reputationids();
	}

	print_stop_message2(array('saved_reputation_level_x_successfully', ''), 'adminreputation', array('do'=>'modify'));
}

// *************************************************************************************************

if ($_REQUEST['do'] == 'editreputation')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'reputationid' => vB_Cleaner::TYPE_INT
	));

	if ($repinfo = $assertor->getRow('vBForum:editReputationInfo', array('reputationid' => $vbulletin->GPC['reputationid'])))
	{
		print_form_header('adminreputation', 'doeditreputation');
		print_table_header($vbphrase['edit_reputation_greputation']);
		print_label_row($vbphrase['thread'],
			$repinfo['title'] ? "<a href=\"" . fetch_seo_url('thread|bburl', $repinfo,
				array('p' => $repinfo['postid'])) . "#post$repinfo[postid]" .
				"\">$repinfo[title]</a>" : '');
		print_label_row($vbphrase['leftby'], $repinfo['whoadded_username']);
		print_label_row($vbphrase['leftfor'], $repinfo['username']);
		print_input_row($vbphrase['comment_guser'], 'reputation[reason]', $repinfo['reason']);
		print_input_row($vbphrase['reputation'], 'reputation[reputation]', $repinfo['reputation'], 0, 5);
		construct_hidden_code('reputationid', $vbulletin->GPC['reputationid']);
		construct_hidden_code('oldreputation', $repinfo[reputation]);
		construct_hidden_code('userid', $repinfo['userid']);
		print_submit_row();
	}
	else
	{
		print_stop_message2('no_matches_found_gerror');
	}
}

// *************************************************************************************************

if ($_POST['do'] == 'doeditreputation')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'reputation'	=> vB_Cleaner::TYPE_ARRAY,
		'reputationid'	=> vB_Cleaner::TYPE_INT,
		'oldreputation'	=> vB_Cleaner::TYPE_INT,
		'userid'		=> vB_Cleaner::TYPE_INT
	));

	$insertValues = array();
	$structure = $assertor->fetchTableStructure('vBForum:reputation');
	foreach ($vbulletin->GPC['reputation'] AS $field => $value)
	{
		if (in_array($field, $structure['structure']))
		{
			$insertValues[$field] = $value;
		}
	}

	if (!empty($insertValues))
	{
		$assertor->update('vBForum:reputation', $insertValues, array('reputationid' => $vbulletin->GPC['reputationid']));
		if ($vbulletin->GPC['oldreputation'] != $vbulletin->GPC['reputation']['reputation'])
		{
			$diff = $vbulletin->GPC['oldreputation'] - $vbulletin->GPC['reputation']['reputation'];

			$user = fetch_userinfo($vbulletin->GPC['userid']);
			if ($user)
			{
				$userdm = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
				$userdm->set_existing($user);
				$userdm->set('reputation', "reputation - $diff", false);
				$userdm->save();
				unset($userdm);
			}
		}
	}

	print_stop_message2('saved_reputation_successfully', 'adminreputation', array('do'=>'list', 'u'=> $vbulletin->GPC['userid']));
}

// *************************************************************************************************

if ($_POST['do'] == 'killreputation')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'reputationid'	=> vB_Cleaner::TYPE_INT
	));

	$repinfo = verify_id('reputation', $vbulletin->GPC['reputationid'], 0, 1);

	$user = fetch_userinfo($repinfo['userid']);
	if ($user)
	{
		$userdm = new vB_Datamanager_user($vbulletin, vB_DataManager_Constants::ERRTYPE_CP);
		$userdm->set_existing($user);
		$userdm->set('reputation', $user['reputation'] - $repinfo['reputation']);
		$userdm->save();
		unset($userdm);
	}

	$assertor->delete('vBForum:reputation', array('reputationid' => $vbulletin->GPC['reputationid']));

	print_stop_message2('deleted_reputation_successfully', 'adminreputation', array('do'=>'list','u'=>$repinfo['userid']));
}

// *************************************************************************************************

if ($_REQUEST['do'] == 'deletereputation')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'reputationid'	=> vB_Cleaner::TYPE_INT
	));

	print_delete_confirmation('reputation', $vbulletin->GPC['reputationid'], 'adminreputation', 'killreputation');
}

if ($_REQUEST['do'] == 'modify')
{
	$reputationlevels = $assertor->getRows('vBForum:reputationlevel', array(), array('minimumreputation'));

	print_form_header('adminreputation', 'updateminimums');
	print_table_header($vbphrase['user_reputation_manager_greputation'], 3);
	print_cells_row(array($vbphrase['reputation_level_guser'], $vbphrase['minimum_reputation_level'], $vbphrase['controls']), 1);

	foreach ($reputationlevels AS $reputationlevel)
	{
		$reputationlevel['level'] = htmlspecialchars_uni($vbphrase['reputation' . $reputationlevel['reputationlevelid']]);
		$cell = array();
		$cell[] = "$vbphrase[user] <b>$reputationlevel[level]</b>";
		$cell[] = "<input type=\"text\" class=\"bginput\" tabindex=\"1\" name=\"reputation[$reputationlevel[reputationlevelid]]\" value=\"$reputationlevel[minimumreputation]\" size=\"5\" />";
		$cell[] = construct_link_code($vbphrase['edit'], "adminreputation.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&reputationlevelid=$reputationlevel[reputationlevelid]") . construct_link_code($vbphrase['delete'], "adminreputation.php?" . vB::getCurrentSession()->get('sessionurl') . "do=remove&minimumreputation=$reputationlevel[minimumreputation]");
		print_cells_row($cell);
	}

	print_submit_row($vbphrase['update'], $vbphrase['reset'], 3);
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 70662 $
|| ####################################################################
\*======================================================================*/
?>
