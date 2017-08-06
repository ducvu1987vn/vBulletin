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
global $phrasegroups, $specialtemplates, $vbphrase;
$phrasegroups = array('user', 'cpuser');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
$assertor = vB::getDbAssertor();

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'usertitleid' => vB_Cleaner::TYPE_INT
));
log_admin_action(!empty($vbulletin->GPC['usertitleid']) ? 'usertitle id = ' . $vbulletin->GPC['usertitleid'] : '');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['user_title_manager_gcpuser']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start add #######################
if ($_REQUEST['do'] == 'add')
{

	print_form_header('usertitle', 'insert');

	print_table_header($vbphrase['add_new_user_title_gcpuser']);
	print_input_row($vbphrase['title'], 'title');
	print_input_row($vbphrase['minimum_posts'], 'minposts');

	print_submit_row($vbphrase['save']);
}

// ###################### Start insert #######################
if ($_POST['do'] == 'insert')
{

	$vbulletin->input->clean_array_gpc('p', array(
		'title'    => vB_Cleaner::TYPE_STR,
		'minposts' => vB_Cleaner::TYPE_UINT
	));

	if (empty($vbulletin->GPC['title']))
	{
		print_stop_message2('invalid_user_title_specified');
	}

	/*insert query*/
	$assertor->insert('usertitle', array(
		'title' => $vbulletin->GPC['title'],
		'minposts' => $vbulletin->GPC['minposts']
	));

	print_stop_message2(array('saved_user_title_x_successfully',  $vbulletin->GPC['title']), 'usertitle', array('do' => 'modify'));
}

// ###################### Start edit #######################
if ($_REQUEST['do'] == 'edit')
{
	$usertitle = $assertor->getRow('usertitle', array('usertitleid' => $vbulletin->GPC['usertitleid']));

	print_form_header('usertitle', 'doupdate');
	construct_hidden_code('usertitleid', $vbulletin->GPC['usertitleid']);

	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['user_title_guser'], $usertitle['title'], $vbulletin->GPC['usertitleid']), 2, 0);
	print_input_row($vbphrase['title'], 'title', $usertitle['title']);
	print_input_row($vbphrase['minimum_posts'], 'minposts', $usertitle['minposts']);

	print_submit_row($vbphrase['save']);

}

// ###################### Start do update #######################
if ($_POST['do'] == 'doupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'title'    => vB_Cleaner::TYPE_STR,
		'minposts' => vB_Cleaner::TYPE_UINT
	));

	if (empty($vbulletin->GPC['title']))
	{
		print_stop_message2('invalid_user_title_specified');
	}

	$assertor->update('usertitle', array('title' => $vbulletin->GPC['title'], 'minposts' => $vbulletin->GPC['minposts']), array('usertitleid' => $vbulletin->GPC['usertitleid']));

	print_stop_message2(array('saved_user_title_x_successfully',  $vbulletin->GPC['title']), 'usertitle', array('do' => 'modify'));

}
// ###################### Start Remove #######################

if ($_REQUEST['do'] == 'remove')
{
	print_form_header('usertitle', 'kill');
	construct_hidden_code('usertitleid', $vbulletin->GPC['usertitleid']);
	print_table_header($vbphrase['confirm_deletion_gcpglobal']);
	print_description_row($vbphrase['are_you_sure_you_want_to_delete_this_user_title']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);

}

// ###################### Start Kill #######################

if ($_POST['do'] == 'kill')
{
	$assertor->delete('usertitle', array('usertitleid' => $vbulletin->GPC['usertitleid']));

	print_stop_message2('deleted_user_title_successfully', 'usertitle', array('do'=>'modify'));
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{
	$usertitles = $assertor->getRows('usertitle', array(), 'minposts');

	?>
	<script type="text/javascript">
	function js_usergroup_jump(usertitleid, obj)
	{
		task = obj.options[obj.selectedIndex].value;
		switch (task)
		{
			case 'edit': window.location = "usertitle.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=edit&usertitleid=" + usertitleid; break;
			case 'kill': window.location = "usertitle.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=remove&usertitleid=" + usertitleid; break;
			default: return false; break;
		}
	}
	</script>
	<?php

	$options = array(
		'edit' => $vbphrase['edit'],
		'kill' => $vbphrase['delete'],
	);

	print_form_header('usertitle', 'add');
	print_table_header($vbphrase['user_title_manager_gcpuser'], 3);

	print_description_row('<p>' . construct_phrase($vbphrase['it_is_recommended_that_you_update_user_titles'], vB::getCurrentSession()->get('sessionurl')) . '</p>', 0, 3);
	print_cells_row(array($vbphrase['user_title_guser'], $vbphrase['minimum_posts'], $vbphrase['controls']), 1);

	foreach ($usertitles AS $usertitle)
	{
		print_cells_row(array(
			'<b>' . $usertitle['title'] . '</b>',
			$usertitle['minposts'],
			"\n\t<select name=\"u$usertitle[usertitleid]\" onchange=\"js_usergroup_jump($usertitle[usertitleid], this);\" class=\"bginput\">\n" . construct_select_options($options) . "\t</select>\n\t<input type=\"button\" value=\"" . $vbphrase['go'] . "\" onclick=\"js_usergroup_jump($usertitle[usertitleid], this.form.u$usertitle[usertitleid]);\" />\n\t"
		));
	}

	print_submit_row($vbphrase['add_new_user_title_gcpuser'], 0, 3);

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>