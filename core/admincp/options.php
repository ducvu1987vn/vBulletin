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
define('CVS_REVISION', '$RCSfile$ - $Revision: 69461 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbulletin, $vbphrase;
$phrasegroups = array(
	'timezone',
	'user',
	'cpuser',
	'holiday',
	'cppermission',
	'cpoption',
	'cprofilefield', // used for the profilefield option type
);

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');

require_once(DIR . '/includes/adminfunctions_misc.php');

$vbulletin->input->clean_array_gpc('r', array(
	'varname' => vB_Cleaner::TYPE_STR,
	'dogroup' => vB_Cleaner::TYPE_STR,
));

vB::getDatastore()->getValue('banemail'); 

// intercept direct call to do=options with $varname specified instead of $dogroup
if ($_REQUEST['do'] == 'options' AND !empty($vbulletin->GPC['varname']))
{
	if ($vbulletin->GPC['varname'] == '[all]')
	{
		// go ahead and show all settings
		$vbulletin->GPC['dogroup'] = '[all]';
	}
	//else if ($group = $vbulletin->db->query_first("SELECT varname, grouptitle FROM " . TABLE_PREFIX . "setting WHERE varname = '" . $vbulletin->db->escape_string($vbulletin->GPC['varname']) . "'"))
	else if ($group = vB::getDbAssertor()->getRow('setting', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'varname' => $vbulletin->GPC['varname'])))
	{
		$args = array();
		parse_str(vB::getCurrentSession()->get('sessionurl_js'),$args);
		$args['do'] = 'options';
		$args['dogroup'] = $group['grouptitle'];
		$args['#'] = $group['varname'];
		// redirect to show the correct group and use and anchor to jump to the correct variable
		exec_header_redirect2('options', $args);
	}
	else
	{
		// could not find a matching group - just carry on as if nothing happened
		$_REQUEST['do'] = 'options';
	}
}

require_once(DIR . '/includes/adminfunctions_options.php');
require_once(DIR . '/includes/functions_misc.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminsettings'))
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
$vb_options = vB::getDatastore()->getValue('options');

// query settings phrases
global $settingphrase;
$settingphrase = array();
$phrases = $assertor->assertQuery('vBForum:phrase',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'fieldname' => 'vbsettings',
			'languageid' => array(-1, 0, LANGUAGEID),
		),
		array('field' => 'languageid', 'direction' => vB_dB_Query::SORT_ASC)
);
if ($phrases AND $phrases->valid())
{
	foreach ($phrases AS $phrase)
	{
		$settingphrase["$phrase[varname]"] = $phrase['text'];
	}
}

// #############################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'options';
}

// ###################### Start download XML settings #######################

if ($_REQUEST['do'] == 'download')
{
	require_once(DIR . '/includes/functions_file.php');
	$vbulletin->input->clean_array_gpc('r', array('product' => vB_Cleaner::TYPE_STR));
	$get_settings = vB_Api::instance('Options')->getSettingsXML($vbulletin->GPC['product']);
	if (isset($get_settings['errors']))
	{
		print_stop_message2($get_settings['errors'][0]);
	}
	else
	{
		file_download($get_settings['settings'], 'vbulletin-settings.xml', 'text/xml');
	}
}


// ###################### Start product XML backup #######################

if ($_REQUEST['do'] == 'backup')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'product'   => vB_Cleaner::TYPE_STR,
		'blacklist' => vB_Cleaner::TYPE_BOOL,
	));

	require_once(DIR . '/includes/functions_file.php');
	$groupSettings = vB_Api::instance('Options')->getGroupSettingsXML($vbulletin->GPC['blacklist'], $vbulletin->GPC['product']);
	if (isset($groupSettings['errors']))
	{
		print_stop_message2($groupSettings['errors'][0]);
	}
	else
	{
		$doc = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n";
		$doc .= $groupSettings['settings'];
		file_download($doc, 'vbulletin-settings2.xml', 'text/xml');
	}
}

// #############################################################################
// ajax setting value validation
if ($_POST['do'] == 'validate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'varname' => vB_Cleaner::TYPE_STR,
		'setting' => vB_Cleaner::TYPE_ARRAY
	));

	$validate = vB_Api::instance('Options')->validateSettings($vbulletin->GPC['varname'], $vbulletin->GPC['setting']);
	if($validate['errors'])
	{
		print_stop_message2($validate['errors'][0]);
	}
	else
	{
		$validate['xml']->print_xml();
	}

}

// ***********************************************************************

print_cp_header($vbphrase['vbulletin_options']);

// ###################### Start do import settings XML #######################
if ($_POST['do'] == 'doimport')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'serverfile' => vB_Cleaner::TYPE_STR,
		'restore'    => vB_Cleaner::TYPE_BOOL,
		'blacklist'  => vB_Cleaner::TYPE_BOOL,
	));

	$vbulletin->input->clean_array_gpc('f', array(
		'settingsfile' => vB_Cleaner::TYPE_FILE
	));


	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	$doImport = vB_Api::instance('Options')->importSettingsXML($vbulletin->GPC['settingsfile'], $vbulletin->GPC['serverfile'], $vbulletin->GPC['restore'], $vbulletin->GPC['blacklist']);
	if(isset($doImport['errors']))
	{
		print_stop_message2($doImport['errors'][0]);
	}
	else
	{
		$args = array();
		parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
		print_cp_redirect2('options', $args, 0);
	}
}

// ###################### Start import settings XML #######################
if ($_REQUEST['do'] == 'files')
{
	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'type' => vB_Cleaner::TYPE_NOHTML
	));

	// download form
	print_form_header('options', 'download', 0, 1, 'downloadform', '90%', '', true, 'post" target="download');
	print_table_header($vbphrase['download']);
	print_select_row($vbphrase['product'], 'product', fetch_product_list());
	print_submit_row($vbphrase['download']);

	?>
	<script type="text/javascript">
	<!--
	function js_confirm_upload(tform, filefield)
	{
		if (filefield.value == "")
		{
			return confirm("<?php echo construct_phrase($vbphrase['you_did_not_specify_a_file_to_upload'], '" + tform.serverfile.value + "'); ?>");
		}
		return true;
	}
	//-->
	</script>
	<?php

	print_form_header('options', 'doimport', 1, 1, 'uploadform', '90%', '', true, 'post" onsubmit="return js_confirm_upload(this, this.settingsfile);');
	print_table_header($vbphrase['import_settings_xml_file']);
	print_upload_row($vbphrase['upload_xml_file'], 'settingsfile', 999999999);
	print_input_row($vbphrase['import_xml_file'], 'serverfile', './install/vbulletin-settings.xml');
	print_submit_row($vbphrase['import'], 0);
}

// ###################### Start kill setting group #######################
if ($_POST['do'] == 'killgroup')
{
	$vbulletin->input->clean_array_gpc('p', array('title' => vB_Cleaner::TYPE_STR));
	$doDelete = vB_Api::instance('Options')->deleteGroupSettings($vbulletin->GPC['title']);
	if(isset($doDelete['errors']))
	{
		print_stop_message2($doDelete['errors'][0]);
	}
	else
	{
		print_stop_message2('deleted_setting_group_successfully', 'options');
	}
}

// ###################### Start remove setting group #######################
if ($_REQUEST['do'] == 'removegroup')
{
	$vbulletin->input->clean_array_gpc('r', array('grouptitle' => vB_Cleaner::TYPE_STR));
	print_delete_confirmation('settinggroup', $vbulletin->GPC['grouptitle'], 'options', 'killgroup');
}

// ###################### Start insert setting group #######################
if ($_POST['do'] == 'insertgroup')
{
	$vbulletin->input->clean_array_gpc('p', array('group' => vB_Cleaner::TYPE_ARRAY));

	$insertGroup = vB_Api::instance('Options')->addGroupSettings($vbulletin->GPC['group']);
	if(isset($insertGroup['errors']))
	{
		print_stop_message2($insertGroup['errors'][0]);
	}

	// fall through to 'updategroup' for the real work...
	$_POST['do'] = 'updategroup';

}

// ###################### Start update setting group #######################
if ($_POST['do'] == 'updategroup')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'group' => vB_Cleaner::TYPE_ARRAY,
		'oldproduct' => vB_Cleaner::TYPE_STR
	));

	$updateGroup = vB_Api::instance('Options')->updateGroupSettings($vbulletin->GPC['group'], $vbulletin->userinfo['username'], $vbulletin->GPC['oldproduct']);
	if(isset($updateGroup['errors']))
	{
		print_stop_message2($updateGroup['errors'][0]);
	}
	else
	{
		print_stop_message2(array('saved_setting_group_x_successfully', $vbulletin->GPC['group']['title']), 'options',
			array('do' => 'options', 'dogroup' => $vbulletin->GPC['group']['grouptitle'])
		);
	}

}

// ###################### Start edit setting group #######################
if ($_REQUEST['do'] == 'editgroup' OR $_REQUEST['do'] == 'addgroup')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'grouptitle' => vB_Cleaner::TYPE_STR,
	));

	if ($_REQUEST['do'] == 'editgroup')
	{
		$group = vB::getDbAssertor()->getRow('settinggroup',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'grouptitle' => $vbulletin->GPC['grouptitle'])
		);

		$phrase = vB::getDbAssertor()->getRow('vBForum:phrase',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'languageid' => array(-1,0),
				'fieldname' => 'vbsettings',
				'varname' => "settinggroup_" . $group['grouptitle']
			)
		);

		$group['title'] = $phrase['text'];
		$pagetitle = construct_phrase($vbphrase['x_y_id_z'], $vbphrase['setting_group'], $group['title'], $group['grouptitle']);
		$formdo = 'updategroup';
	}
	else
	{
		$ordercheck = vB::getDbAssertor()->getRow('settinggroup',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT),
			array('field' => 'displayorder', 'direction' => vB_dB_Query::SORT_DESC)
		);

		$group = array(
			'displayorder' => $ordercheck['displayorder'] + 10,
			'volatile' => iif($vb5_config['Misc']['debug'], 1, 0)
		);

		$pagetitle = $vbphrase['add_new_setting_group'];
		$formdo = 'insertgroup';
	}

	print_form_header('options', $formdo);
	print_table_header($pagetitle);
	if ($_REQUEST['do'] == 'editgroup')
	{
		print_label_row($vbphrase['varname'], "<b>$group[grouptitle]</b>");
		construct_hidden_code('group[grouptitle]', $group['grouptitle']);
	}
	else
	{
		print_input_row($vbphrase['varname'], 'group[grouptitle]', $group['grouptitle']);
	}
	print_input_row($vbphrase['title'], 'group[title]', $group['title']);
	construct_hidden_code('oldproduct', $group['product']);
	print_select_row($vbphrase['product'], 'group[product]', fetch_product_list(), $group['product']);
	print_input_row($vbphrase['display_order'], 'group[displayorder]', $group['displayorder']);
	if ($vb5_config['Misc']['debug'])
	{
		print_yes_no_row($vbphrase['vbulletin_default'], 'group[volatile]', $group['volatile']);
	}
	else
	{
		construct_hidden_code('group[volatile]', $group['volatile']);
	}
	print_submit_row($vbphrase['save']);

}

// ###################### Start kill setting #######################
if ($_POST['do'] == 'killsetting')
{
	$vbulletin->input->clean_array_gpc('p', array( 'title' => vB_Cleaner::TYPE_STR ));

	$delete = vB_Api::instance('Options')->killSetting($vbulletin->GPC['title']);
	if ($delete['errors'])
	{
		print_stop_message2($delete['errors'][0]);
	}
	else
	{
		print_stop_message2('deleted_setting_successfully',	'options', array('do' => 'options', 'dogroup' => $delete['setting']['grouptitle']));
	}
}

// ###################### Start remove setting #######################
if ($_REQUEST['do'] == 'removesetting')
{
	print_delete_confirmation('setting', $vbulletin->GPC['varname'], 'options', 'killsetting');
}

// ###################### Start insert setting #######################
if ($_POST['do'] == 'insertsetting')
{
	$vbulletin->input->clean_array_gpc('p', array(
		// setting stuff
		'varname'        => vB_Cleaner::TYPE_STR,
		'grouptitle'     => vB_Cleaner::TYPE_STR,
		'optioncode'     => vB_Cleaner::TYPE_STR,
		'defaultvalue'   => vB_Cleaner::TYPE_STR,
		'displayorder'   => vB_Cleaner::TYPE_UINT,
		'volatile'       => vB_Cleaner::TYPE_INT,
		'datatype'       => vB_Cleaner::TYPE_STR,
		'validationcode' => vB_Cleaner::TYPE_STR,
		'product'        => vB_Cleaner::TYPE_STR,
		'blacklist'      => vB_Cleaner::TYPE_BOOL,
		'ispublic'          => vB_Cleaner::TYPE_BOOL,
		// phrase stuff
		'title'          => vB_Cleaner::TYPE_STR,
		'description'    => vB_Cleaner::TYPE_STR,
		'oldproduct'     => vB_Cleaner::TYPE_STR
	));

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	$setting = array('varname' => $vbulletin->GPC['varname'], 'grouptitle' => $vbulletin->GPC['grouptitle'], 'optioncode' => $vbulletin->GPC['optioncode'],
		'defaultvalue' => $vbulletin->GPC['defaultvalue'], 'displayorder' => $vbulletin->GPC['displayorder'], 'volatile' => $vbulletin->GPC['volatile'],
		'datatype' => $vbulletin->GPC['datatype'], 'validationcode' => $vbulletin->GPC['validationcode'], 'product' => $vbulletin->GPC['product'],
		'blacklist' => $vbulletin->GPC['blacklist'], 'title' => $vbulletin->GPC['title'], 'username' => $vbulletin->userinfo['username'],
		'description' => $vbulletin->GPC['description'], 'ispublic' => $vbulletin->GPC['ispublic']
	);
	$insert = vB_Api::instance('Options')->insertSetting($setting);
	if (isset($insert['errors']))
	{
		print_stop_message2($insert['errors'][0]);
	}
	else
	{
		print_stop_message2(array('saved_setting_x_successfully', $vbulletin->GPC['title']),
			'options', array('do' => 'options', 'dogroup' => $vbulletin->GPC['grouptitle'])
		);
	}
}

// ###################### Start update setting #######################
if ($_POST['do'] == 'updatesetting')
{
	$vbulletin->input->clean_array_gpc('p', array(
		// setting stuff
		'varname'        => vB_Cleaner::TYPE_STR,
		'grouptitle'     => vB_Cleaner::TYPE_STR,
		'optioncode'     => vB_Cleaner::TYPE_STR,
		'defaultvalue'   => vB_Cleaner::TYPE_STR,
		'displayorder'   => vB_Cleaner::TYPE_UINT,
		'volatile'       => vB_Cleaner::TYPE_INT,
		'datatype'       => vB_Cleaner::TYPE_STR,
		'validationcode' => vB_Cleaner::TYPE_STR,
		'product'        => vB_Cleaner::TYPE_STR,
		'blacklist'      => vB_Cleaner::TYPE_BOOL,
		'ispublic'          => vB_Cleaner::TYPE_BOOL,
		// phrase stuff
		'title'          => vB_Cleaner::TYPE_STR,
		'description'    => vB_Cleaner::TYPE_STR,
	));

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	$values = array('varname' => $vbulletin->GPC['varname'], 'grouptitle' => $vbulletin->GPC['grouptitle'], 'optioncode' => $vbulletin->GPC['optioncode'],
		'defaultvalue' => $vbulletin->GPC['defaultvalue'], 'displayorder' => $vbulletin->GPC['displayorder'], 'volatile' => $vbulletin->GPC['volatile'],
		'datatype' => $vbulletin->GPC['datatype'], 'validationcode' => $vbulletin->GPC['validationcode'], 'product' => $vbulletin->GPC['product'],
		'blacklist' => $vbulletin->GPC['blacklist'], 'title' => $vbulletin->GPC['title'], 'username' => $vbulletin->userinfo['username'],
		'description' => $vbulletin->GPC['description'], 'ispublic' => $vbulletin->GPC['ispublic']
	);

	$update = vB_Api::instance('Options')->updateSetting($values);

	if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT)
	{
		require_once(DIR . '/includes/functions_filesystemxml.php');
		autoexport_write_settings_and_language(($vbulletin->GPC['volatile'] ? -1 : 0),
			array($old_setting['product'], $vbulletin->GPC['product']));
	}

	if (isset($update['errors']))
	{
		print_stop_message2($update['errors'][0]);
	}
	else
	{
		print_stop_message2(array('saved_setting_x_successfully', $vbulletin->GPC['title']),
			'options', array('do' => 'options', 'dogroup' => $vbulletin->GPC['grouptitle'])
		);
	}
}

// ###################### Start edit / add setting #######################
if ($_REQUEST['do'] == 'editsetting' OR $_REQUEST['do'] == 'addsetting')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'grouptitle' => vB_Cleaner::TYPE_STR
	));

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	$product = '';
	$settinggroups = array();
	//$groups = $vbulletin->db->query_read("SELECT grouptitle, product FROM " . TABLE_PREFIX . "settinggroup ORDER BY displayorder");
	$groups = vB::getDbAssertor()->assertQuery('settinggroup',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT),
		array('field' => 'displayorder', 'direction' => vB_dB_Query::SORT_ASC)
	);
	//while ($group = $vbulletin->db->fetch_array($groups))
	if ($groups AND $groups->valid())
	{
		foreach ($groups AS $group)
		{
			$settinggroups["$group[grouptitle]"] = $settingphrase["settinggroup_$group[grouptitle]"];
			if ($group['grouptitle'] == $vbulletin->GPC['grouptitle'])
			{
				$product = $group['product'];
			}
		}
	}

	if ($_REQUEST['do'] == 'editsetting')
	{
		/*$setting = $vbulletin->db->query_first("
			SELECT * FROM " . TABLE_PREFIX . "setting
			WHERE varname = '" . $vbulletin->db->escape_string($vbulletin->GPC['varname']) . "'
		");*/
		$setting = vB::getDbAssertor()->getRow('setting',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'varname' => $vbulletin->GPC['varname'])
		);
		/*$phrases = $vbulletin->db->query_read("
			SELECT varname, text
			FROM " . TABLE_PREFIX . "phrase
			WHERE languageid = " . iif($setting['volatile'], -1, 0) . " AND
				fieldname = 'vbsettings' AND
			varname IN ('setting_" . $vbulletin->db->escape_string($setting['varname']) . "_title', 'setting_" . $vbulletin->db->escape_string($setting['varname']) . "_desc')
		");*/
		$langid = $setting['volatile'] ? -1 : 0;
		$phrases = vB::getDbAssertor()->assertQuery('vBForum:phrase',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			    'languageid' => $langid,
			    'fieldname' => 'vbsettings',
			    'varname' => array("setting_" . $setting['varname'] . "_title", "setting_" . $setting['varname'] . "_desc")
			)
		);
		//while ($phrase = $vbulletin->db->fetch_array($phrases))
		if ($phrases AND $phrases->valid())
		{
			foreach ($phrases AS $phrase)
			{
				if ($phrase['varname'] == "setting_$setting[varname]_title")
				{
					$setting['title'] = $phrase['text'];
				}
				else if ($phrase['varname'] == "setting_$setting[varname]_desc")
				{
					$setting['description'] = $phrase['text'];
				}
			}
		}
		$pagetitle = construct_phrase($vbphrase['x_y_id_z'], $vbphrase['setting'], $setting['title'], $setting['varname']);
		$formdo = 'updatesetting';
	}
	else
	{
		/*$ordercheck = $vbulletin->db->query_first("
			SELECT displayorder FROM " . TABLE_PREFIX . "setting
			WHERE grouptitle='" . $vbulletin->db->escape_string($vbulletin->GPC['grouptitle']) . "'
			ORDER BY displayorder DESC
		");*/
		$ordercheck = vB::getDbAssertor()->getRow('setting',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'grouptitle' => $vbulletin->GPC['grouptitle']),
			array('field' => 'displayorder', 'direction' => vB_dB_Query::SORT_DESC)
		);

		$setting = array(
			'grouptitle'   => $vbulletin->GPC['grouptitle'],
			'displayorder' => $ordercheck['displayorder'] + 10,
			'volatile'     => $vb5_config['Misc']['debug'] ? 1 : 0,
			'product'      => $product,
		);
		$pagetitle = $vbphrase['add_new_setting'];
		$formdo = 'insertsetting';
	}

	print_form_header('options', $formdo);
	print_table_header($pagetitle);
	if ($_REQUEST['do'] == 'editsetting')
	{
		construct_hidden_code('varname', $setting['varname']);
		print_label_row($vbphrase['varname'], "<b>$setting[varname]</b>");
	}
	else
	{
		print_input_row($vbphrase['varname'], 'varname', $setting['varname']);
	}
	print_select_row($vbphrase['setting_group'], 'grouptitle', $settinggroups, $setting['grouptitle']);
	print_select_row($vbphrase['product'], 'product', fetch_product_list(), $setting['product']);
	print_input_row($vbphrase['title'], 'title', $setting['title']);
	print_textarea_row($vbphrase['description_gcpglobal'], 'description', $setting['description'], 4, '50" style="width:100%');
	print_textarea_row($vbphrase['option_code'], 'optioncode', $setting['optioncode'], 4, '50" style="width:100%');
	print_textarea_row($vbphrase['default'], 'defaultvalue', $setting['defaultvalue'], 4, '50" style="width:100%');

	switch ($setting['datatype'])
	{
		case 'number':
			$checked = array('number' => ' checked="checked"');
			break;
		case 'integer':
			$checked = array('integer' => ' checked="checked"');
			break;
		case 'posint':
			$checked = array('posint' => ' checked="checked"');
			break;
		case 'boolean':
			$checked = array('boolean' => ' checked="checked"');
			break;
		case 'bitfield':
			$checked= array('bitfield' => ' checked="checked"');
			break;
		case 'username':
			$checked= array('username' => ' checked="checked"');
			break;
		default:
			$checked = array('free' => ' checked="checked"');
	}
	print_label_row($vbphrase['data_validation_type'], '
		<div class="smallfont">
		<label for="rb_dt_free"><input type="radio" name="datatype" id="rb_dt_free" tabindex="1" value="free"' . $checked['free'] . ' />' . $vbphrase['datatype_free'] . '</label>
		<label for="rb_dt_number"><input type="radio" name="datatype" id="rb_dt_number" tabindex="1" value="number"' . $checked['number'] . ' />' . $vbphrase['datatype_numeric'] . '</label>
		<label for="rb_dt_integer"><input type="radio" name="datatype" id="rb_dt_integer" tabindex="1" value="integer"' . $checked['integer'] . ' />' . $vbphrase['datatype_integer'] . '</label>
		<label for="rb_dt_posint"><input type="radio" name="datatype" id="rb_dt_posint" tabindex="1" value="posint"' . $checked['posint'] . ' />' . $vbphrase['datatype_posint'] . '</label>
		<label for="rb_dt_boolean"><input type="radio" name="datatype" id="rb_dt_boolean" tabindex="1" value="boolean"' . $checked['boolean'] . ' />' . $vbphrase['datatype_boolean'] . '</label>
		<label for="rb_dt_bitfield"><input type="radio" name="datatype" id="rb_dt_bitfield" tabindex="1" value="bitfield"' . $checked['bitfield'] . ' />' . $vbphrase['datatype_bitfield'] . '</label>
		<label for="rb_dt_username"><input type="radio" name="datatype" id="rb_dt_username" tabindex="1" value="username"' . $checked['username'] . ' />' . $vbphrase['datatype_username'] . '</label>
		</div>
	');
	print_textarea_row($vbphrase['validation_php_code'], 'validationcode', $setting['validationcode'], 4, '50" style="width:100%');

	print_input_row($vbphrase['display_order'], 'displayorder', $setting['displayorder']);
	print_yes_no_row($vbphrase['blacklist'], 'blacklist', $setting['blacklist']);
	if ($vb5_config['Misc']['debug'])
	{
		print_yes_no_row($vbphrase['vbulletin_default'], 'volatile', $setting['volatile']);
		print_yes_no_row($vbphrase['ispublic'], 'ispublic', $setting['ispublic']);
	}
	else
	{
		construct_hidden_code('volatile', $setting['volatile']);
	}
	print_submit_row($vbphrase['save']);
}

// ###################### Start do options #######################
if ($_POST['do'] == 'dooptions')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'setting'  => vB_Cleaner::TYPE_ARRAY,
		'advanced' => vB_Cleaner::TYPE_BOOL
	));

	if (!empty($vbulletin->GPC['setting']))
	{
		try 
		{
			$save = save_settings($vbulletin->GPC['setting']);
		}
		catch (vB_Exception_Api $e)
		{
			$errors = $e->get_errors();
			print_stop_message2($errors[0]);
		}

		if ($save)
		{
			print_stop_message2('saved_settings_successfully', 'options',
				array('do' => 'options', 'dogroup' => $vbulletin->GPC['dogroup'], 'advanced' => $vbulletin->GPC['advanced']));
		}
		else
		{
			print_stop_message2('nothing_to_do');
		}
	}
	else
	{
		print_stop_message2('nothing_to_do');
	}

}

// ###################### Start modify options #######################
if ($_REQUEST['do'] == 'options')
{
	global $settingscache, $grouptitlecache;

	require_once(DIR . '/includes/adminfunctions_language.php');

	$vbulletin->input->clean_array_gpc('r', array(
		'advanced' => vB_Cleaner::TYPE_BOOL,
		'expand'   => vB_Cleaner::TYPE_BOOL,
	));

	echo '<script type="text/javascript" src="' . $vb_options['bburl']. '/clientscript/vbulletin_cpoptions_scripts.js?v=' . SIMPLE_VERSION . '"></script>';

	// display links to settinggroups and create settingscache
	$settingscache = array();
	$options = array('[all]' => '-- ' . $vbphrase['show_all_settings'] . ' --');
	$lastgroup = '';

	$settings = vB::getDbAssertor()->assertQuery('vBForum:fetchSettingsByGroup',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD, 'debug' => $vb5_config['Misc']['debug'])
	);

	if (empty($vbulletin->GPC['dogroup']) AND $vbulletin->GPC['expand'])
	{
		foreach ($settings AS $setting)
		{
			// TODO: Issue #29084 - Reenable Profile Styling
			if ('profile_customization' == $setting['grouptitle'])
			{
				continue;
			}

			$settingscache["$setting[grouptitle]"]["$setting[varname]"] = $setting;
			if ($setting['grouptitle'] != $lastgroup)
			{
				$grouptitlecache["$setting[grouptitle]"] = $setting['grouptitle'];
				$grouptitle = $settingphrase["settinggroup_$setting[grouptitle]"];
			}
			$options["$grouptitle"]["$setting[varname]"] = $settingphrase["setting_$setting[varname]_title"];
			$lastgroup = $setting['grouptitle'];
		}

		$altmode = 0;
		$linktext =& $vbphrase['collapse_setting_groups'];
	}
	else
	{
		foreach ($settings AS $setting)
		{
			// TODO: Issue #29084 - Reenable Profile Styling
			if ('profile_customization' == $setting['grouptitle'])
			{
				continue;
			}

			$settingscache["$setting[grouptitle]"]["$setting[varname]"] = $setting;
			if ($setting['grouptitle'] != $lastgroup)
			{
				$grouptitlecache["$setting[grouptitle]"] = $setting['grouptitle'];
				$options["$setting[grouptitle]"] = $settingphrase["settinggroup_$setting[grouptitle]"];
			}
			$lastgroup = $setting['grouptitle'];
		}

		$altmode = 1;
		$linktext =& $vbphrase['expand_setting_groups'];
	}

	$optionsmenu = "\n\t<select name=\"" . iif($vbulletin->GPC['expand'], 'varname', 'dogroup') . "\" class=\"bginput\" tabindex=\"1\" " . iif(empty($vbulletin->GPC['dogroup']), 'ondblclick="this.form.submit();" size="20"', 'onchange="this.form.submit();"') . " style=\"width:350px\">\n" . construct_select_options($options, iif($vbulletin->GPC['dogroup'], $vbulletin->GPC['dogroup'], '[all]')) . "\t</select>\n\t";

	print_form_header('options', 'options', 0, 1, 'groupForm', '90%', '', 1, 'get');

	if (empty($vbulletin->GPC['dogroup'])) // show the big <select> with no options
	{
		print_table_header($vbphrase['vbulletin_options']);
		print_label_row($vbphrase['settings_to_edit'] .
			iif($vb5_config['Misc']['debug'],
				'<br /><table><tr><td><fieldset><legend>Developer Options</legend>
				<div style="padding: 2px"><a href="options.php?' . vB::getCurrentSession()->get('sessionurl') . 'do=addgroup">' . $vbphrase['add_new_setting_group'] . '</a></div>
				<div style="padding: 2px"><a href="options.php?' . vB::getCurrentSession()->get('sessionurl') . 'do=files">' . $vbphrase['download_upload_settings'] . '</a></div>' .
				'</fieldset></td></tr></table>'
			) .
			"<p><a href=\"options.php?" . vB::getCurrentSession()->get('sessionurl') . "expand=$altmode\">$linktext</a></p>
			<p><a href=\"options.php?" . vB::getCurrentSession()->get('sessionurl') . "do=backuprestore\">" . $vbphrase['backup_restore_settings'] . "</a>", $optionsmenu);
		print_submit_row($vbphrase['edit_settings'], 0);
	}
	else // show the small list with selected setting group(s) options
	{
		print_table_header("$vbphrase[setting_group] $optionsmenu <input type=\"submit\" value=\"$vbphrase[go]\" class=\"button\" tabindex=\"1\" />");
		print_table_footer();

		// show selected settings
		print_form_header('options', 'dooptions', false, true, 'optionsform', '90%', '', true, 'post" onsubmit="return count_errors()');
		construct_hidden_code('dogroup', $vbulletin->GPC['dogroup']);
		construct_hidden_code('advanced', $vbulletin->GPC['advanced']);

		if ($vbulletin->GPC['dogroup'] == '[all]') // show all settings groups
		{
			foreach ($grouptitlecache AS $curgroup => $group)
			{
				print_setting_group($curgroup, $vbulletin->GPC['advanced']);
				echo '<tbody>';
				print_description_row("<input type=\"submit\" class=\"button\" value=\" $vbphrase[save] \" tabindex=\"1\" title=\"" . $vbphrase['save_settings'] . "\" />", 0, 2, 'tfoot" style="padding:1px" align="right');
				echo '</tbody>';
				print_table_break(' ');
			}
		}
		else
		{
			print_setting_group($vbulletin->GPC['dogroup'], $vbulletin->GPC['advanced']);
		}

		print_submit_row($vbphrase['save']);

		?>
		<div id="error_output" style="font: 10pt courier new"></div>
		<script type="text/javascript">
		<!--
		var error_confirmation_phrase = "<?php echo $vbphrase['error_confirmation_phrase']; ?>";
		//-->
		</script>
		<script type="text/javascript" src="<?php echo$vb_options['bburl']; ?>/clientscript/vbulletin_settings_validate.js?v=<?php echo SIMPLE_VERSION; ?>"></script>
		<?php
	}
}

// ###################### Start modify options #######################
if ($_REQUEST['do'] == 'backuprestore')
{
	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	// download form
	print_form_header('options', 'backup', 0, 1, 'downloadform', '90%', 'backup');
	print_table_header($vbphrase['backup']);
	print_select_row($vbphrase['product'], 'product', fetch_product_list());
	print_yes_no_row($vbphrase['ignore_blacklisted_settings'], 'blacklist', 1);
	print_submit_row($vbphrase['backup']);

	?>
	<script type="text/javascript">
	<!--
	function js_confirm_upload(tform, filefield)
	{
		if (filefield.value == "")
		{
			return confirm("<?php echo construct_phrase($vbphrase['you_did_not_specify_a_file_to_upload'], '" + tform.serverfile.value + "'); ?>");
		}
		return true;
	}
	//-->
	</script>
	<?php

	print_form_header('options', 'doimport', 1, 1, 'uploadform', '90%', '', true, 'post" onsubmit="return js_confirm_upload(this, this.settingsfile);');
	construct_hidden_code('restore', 1);
	print_table_header($vbphrase['restore_settings_xml_file']);
	print_yes_no_row($vbphrase['ignore_blacklisted_settings'], 'blacklist', 1);
	print_upload_row($vbphrase['upload_xml_file'], 'settingsfile', 999999999);
	print_input_row($vbphrase['restore_xml_file'], 'serverfile', './install/vbulletin-settings.xml');
	print_submit_row($vbphrase['restore'], 0);
}

// #################### Start Change Search Type #####################
if ($_REQUEST['do'] == 'searchtype')
{
	print_form_header('options', 'dosearchtype');
	print_table_header("$vbphrase[search_type_gcpglobal]");

	print_select_row($vbphrase["select_search_implementation"], 'implementation',
		fetch_search_implementation_list(), $vbulletin->options['searchimplementation']);

	print_description_row($vbphrase['search_reindex_required']);
	print_submit_row($vbphrase['go'], 0);
}

// #################### Start Change Search Type #####################
if ($_POST['do'] == 'dosearchtype')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'implementation' => vB_Cleaner::TYPE_NOHTML
	));

	$options = fetch_search_implementation_list();
	$changeSearch = vB_Api::instance('Options')->changeSearchType($vbulletin->GPC['implementation'], $options);
	if (isset($changeSearch['errors']))
	{
		print_stop_message2($changeSearch['errors'][0]);
	}
	else
	{
		print_stop_message2('saved_settings_successfully', 'index');
	}


}

function fetch_search_implementation_list()
{
	global $vbphrase;
	$options['vBDBSearch_Core'] = $vbphrase['db_search_implementation'];
	//sets any additional options
	// Legacy Hook 'admin_search_options' Removed //
	return $options;
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 69461 $
|| ####################################################################
\*======================================================================*/
?>
