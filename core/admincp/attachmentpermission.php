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

// TODO: Move this to proper channel permissions

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 70325 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbphrase, $vbulletin;
$phrasegroups = array('cppermission', 'attachment_image');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/adminfunctions_attachment.php');
// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminpermissions'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'attachmentpermissionid' => vB_Cleaner::TYPE_UINT,
	'extension'              => vB_Cleaner::TYPE_NOHTML,
	'usergroupid'            => vB_Cleaner::TYPE_INT
));
log_admin_action(iif($vbulletin->GPC['attachmentpermissionid'] != 0, "attachmentpermission id = " . $vbulletin->GPC['attachmentpermissionid'],
					iif($vbulletin->GPC['extension'] != '', "extension = ". $vbulletin->GPC['extension'] .
						iif($vbulletin->GPC['usergroupid'] != 0, " / usergroup id = " . $vbulletin->GPC['usergroupid']))));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['attachment_permissions_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start edit #######################
if ($_REQUEST['do'] == 'edit')
{
	?>
	<script type="text/javascript">
	<!--
	function js_set_custom()
	{
		if (document.cpform.useusergroup[1].checked == false)
		{
			if (confirm("<?php echo $vbphrase['must_enable_custom_permissions']; ?>"))
			{
				document.cpform.useusergroup[1].checked = true;
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			return true;
		}
	}
	// -->
	</script>
	<?php

	print_form_header('attachmentpermission', 'doupdate');

	if ($vbulletin->GPC['attachmentpermissionid'])
	{
		$getperms = vB::getDbAssertor()->getRow('vBForum:fetchAttachPerms', array('attachmentpermissionid' => $vbulletin->GPC['attachmentpermissionid']));

		$usergroup = $vbulletin->usergroupcache["$getperms[usergroupid]"];
		$extension = $getperms['extension'];
		construct_hidden_code('extension', $extension);
		construct_hidden_code('attachmentpermissionid', $getperms['attachmentpermissionid']);
	}
	else
	{
		$getperms = vB::getDbAssertor()->getRow('vBForum:attachmenttype', array('extension' => $vbulletin->GPC['extension']));

		$getperms['attachmentpermissions'] = 1;
		$usergroup = $vbulletin->usergroupcache[$vbulletin->GPC['usergroupid']];
		$extension = $vbulletin->GPC['extension'];
		construct_hidden_code('extension', $vbulletin->GPC['extension']);
		construct_hidden_code('usergroupid', $vbulletin->GPC['usergroupid']);
	}

	print_table_header(construct_phrase($vbphrase['edit_attachment_permissions_for_usergroup_x_in_extension_y'], $usergroup['title'], $extension));
	print_description_row('
		<label for="uug_1"><input type="radio" name="useusergroup" value="1" id="uug_1" tabindex="1" onclick="this.form.reset(); this.checked=true;"' . iif(!$vbulletin->GPC['attachmentpermissionid'], ' checked="checked"', '') . ' />' . $vbphrase['use_default_permissions_gcppermission'] . '</label>
		<br />
		<label for="uug_0"><input type="radio" name="useusergroup" value="0" id="uug_0" tabindex="1"' . iif($vbulletin->GPC['attachmentpermissionid'], ' checked="checked"', '') . ' />' . $vbphrase['use_custom_permissions'] . '</label>
	', 0, 2, 'tfoot', '', 'mode');
	print_table_break();

	// the 35 . '" onchange="js_set_custom();' code below uses a
	// hack to get an onchange event while still using print_input_row

	print_table_header($vbphrase['custom_attachment_permissions']);
	print_yes_no_row($vbphrase['can_use_this_extension'], 'useextension', $getperms['attachmentpermissions'], 'js_set_custom();');
	print_input_row(construct_phrase($vbphrase['maximum_filesize_dfn']), 'size', $getperms['size'], true, 35 . '" onchange="js_set_custom();');
	if (in_array($extension, array('bmp', 'gif', 'jpe', 'jpg', 'jpeg', 'png', 'psd', 'tif', 'tiff')))
	{
		print_input_row($vbphrase['max_width_dfn'], 'width', $getperms['width'], true, 35 . '" onchange="js_set_custom();');
		print_input_row($vbphrase['max_height_dfn'], 'height', $getperms['height'], true, 35 . '" onchange="js_set_custom();');
	}

	print_submit_row($vbphrase['save']);
}

// ###################### Start do update #######################
if ($_POST['do'] == 'doupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'useextension'           => vB_Cleaner::TYPE_BOOL,
		'attachmentpermissionid' => vB_Cleaner::TYPE_INT,
		'extension'              => vB_Cleaner::TYPE_NOHTML,
		'usergroupid'            => vB_Cleaner::TYPE_INT,
		'useusergroup'           => vB_Cleaner::TYPE_BOOL,
		'size'                   => vB_Cleaner::TYPE_UINT,
		'width'                  => vB_Cleaner::TYPE_UINT,
		'height'                 => vB_Cleaner::TYPE_UINT,
	));


	if ($vbulletin->GPC['useusergroup'])
	{
		// use extension defaults. delete attachmentpermission if it exists
		if ($vbulletin->GPC['attachmentpermissionid'])
		{
			vB::getDbAssertor()->delete('attachmentpermission', array('attachmentpermissionid' => $vbulletin->GPC['attachmentpermissionid']));

			build_attachment_permissions();
			print_stop_message2('deleted_attachment_permissions_successfully','attachmentpermission', array('do'=>'modify#a_' . $vbulletin->GPC['extension']));
		}
		else
		{
			print_stop_message2('saved_attachment_permissions_successfully','attachmentpermission', array('do'=>'modify#a_' . $vbulletin->GPC['extension']));
		}
	}
	else
	{
		if ($vbulletin->GPC['attachmentpermissionid'])
		{
			$usergroup = vB::getDbAssertor()->getRow('vBForum:attachmentpermission', array('attachmentpermissionid' => $vbulletin->GPC['attachmentpermissionid']));
			$vbulletin->GPC['usergroupid'] = $usergroup['usergroupid'];
		}
		vB::getDbAssertor()->assertQuery('vBForum:replaceAttachPerms', array(
			'usergroupid' => $vbulletin->GPC['usergroupid'],
			'extension' =>$vbulletin->GPC['extension'],
			'attachmentpermissions' => $vbulletin->GPC['useextension'],
			'height' => $vbulletin->GPC['height'],
			'width' => $vbulletin->GPC['width'],
			'size' => $vbulletin->GPC['size']
		));

		build_attachment_permissions();
		print_stop_message2('saved_attachment_permissions_successfully','attachmentpermission', array('do'=>'modify','#' => 'a_' . $vbulletin->GPC['extension']));
	}
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{

	print_form_header('', '');
	print_table_header($vbphrase['attachment_permissions_gattachment_image']);
	print_description_row('
		<div class="darkbg" style="border: 2px inset">	<ul class="darkbg">
		<li><b>' . $vbphrase['color_key'] . '</b></li>
		<li class="col-g">' . $vbphrase['standard_using_default_attachment_permissions'] . '</li>
		<li class="col-c">' . $vbphrase['customized_using_custom_permissions_for_this_usergroup_gcppermission'] . '</li>
		</ul></div>
	');

	print_table_footer();

	// query subscription permissions
	$attachmentpermissions = vB::getDbAssertor()->assertQuery('vBForum:attachmentpermission');

	$permscache = array();
	foreach ($attachmentpermissions as $aperm)
	//while ($aperm = $vbulletin->db->fetch_array($attachmentpermissions))
	{
		$permscache["{$aperm['extension']}"]["{$aperm['usergroupid']}"] = $aperm;
	}

	echo '<center><div class="tborder" style="width: 100%">';
	echo '<div class="alt1" style="padding: 8px">';
	echo '<div class="darkbg" style="padding: 4px; border: 2px inset; text-align: ' . vB_Template_Runtime::fetchStyleVar('left') . '">';

	$indent = '   ';
	echo "$indent<ul class=\"lsq\">\n";

	$attachments = vB::getDbAssertor()->assertQuery('vBForum:attachmenttype');

	foreach ($attachments as $attach)
	{
		$extension = $attach['extension'];
		echo "$indent<li><b><a name=\"a_$extension\" href=\"attachment.php?" . vB::getCurrentSession()->get('sessionurl') . "do=updatetype&amp;extension=$extension\">$extension</a></b>";
		echo "<span class=\"smallfont\">(" . construct_link_code($vbphrase['reset'], "attachmentpermission.php?" . vB::getCurrentSession()->get('sessionurl') . "do=quickset&amp;type=reset&amp;extension=$extension&amp;hash=" . CP_SESSIONHASH) . construct_link_code($vbphrase['deny_all'], "attachmentpermission.php?" . vB::getCurrentSession()->get('sessionurl') . "do=quickset&amp;type=deny&amp;extension=$extension&amp;hash=" . CP_SESSIONHASH) . ")</span></b>";
		echo "$indent\t<ul class=\"usergroups\">\n";
		foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
		{
			if ($usergroupid == 1)
			{
				continue;
			}
			$ap = $permscache["$extension"]["$usergroupid"];
			if (!empty($ap))
			{
				$class = ' class="col-c"';
				$link = "attachmentpermissionid=$ap[attachmentpermissionid]";
			}
			else
			{
				$class = '';
				$link = "extension=$extension&amp;usergroupid=$usergroupid";
			}

			echo "$indent\t<li$class>" . construct_link_code($vbphrase['edit'], "attachmentpermission.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;$link") . $usergroup['title'] . "</li>\n";

			unset($permscache["$extension"]["$usergroupid"]);
		}
		echo "$indent\t</ul><br />\n";
		echo "$indent</li>\n";
	}
	echo "$indent</ul>\n";

	echo "</div></div></div></center>";
}

// ###################### Start quick set #######################
if ($_REQUEST['do'] == 'quickset')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'type' => vB_Cleaner::TYPE_STR
	));

	verify_cp_sessionhash();
	$attachment_type = vB::getDbAssertor()->getRow('attachmenttype', array('extension' => $vbulletin->GPC['extension']));

	if (!$attachment_type)
	{
		print_stop_message2('invalid_extension_specified');
	}

	switch ($vbulletin->GPC['type'])
	{
		case 'reset':
			vB::getDbAssertor()->delete('attachmentpermission', array('extension' => $vbulletin->GPC['extension']));
			break;

		case 'deny':
			$groups = vB::getDbAssertor()->assertQuery('usergroup');
			$insert = array();
			foreach ($groups as $group)
			{
				$insert[] = "
					('" . $vbulletin->db->escape_string($vbulletin->GPC['extension']) . "', $group[usergroupid],
					$attachment_type[size], $attachment_type[width], $attachment_type[height], 0)
				";
			}

			if (!empty($insert))
			{
				/*insert query*/
				vB::getDbAssertor()->assertQuery('vBForum:replacePerms', array('fields' => $insert));

			}
			break;

		default:
			print_stop_message2('invalid_quick_set_action');
	}

	build_attachment_permissions();

	print_stop_message2('saved_attachment_permissions_successfully','attachmentpermission', array('do'=>'modify#a_' . $vbulletin->GPC['extension']));
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 70325 $
|| ####################################################################
\*======================================================================*/
?>
