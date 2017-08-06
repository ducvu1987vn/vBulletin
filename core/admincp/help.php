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
$phrasegroups = array('help_faq', 'fronthelp');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/adminfunctions_help.php');

// ############################# LOG ACTION ###############################

$vbulletin->input->clean_array_gpc('r', array('adminhelpid' => vB_Cleaner::TYPE_INT));

log_admin_action(iif($vbulletin->GPC['adminhelpid'] != 0, "help id = " . $vbulletin->GPC['adminhelpid']));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vb5_config =& vB::getConfig();
$assertor = vB::getDbAssertor();

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'answer';
}

// ############################### start download help XML ##############
if ($_REQUEST['do'] == 'download')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'product' => vB_Cleaner::TYPE_STR
	));

	$doc = get_help_export_xml($vbulletin->GPC['product']);
	require_once(DIR . '/includes/functions_file.php');
	file_download($doc, 'vbulletin-adminhelp.xml', 'text/xml');
}

// #########################################################################

print_cp_header($vbphrase['admin_help']);

if ($vb5_config['Misc']['debug'])
{
	print_form_header('', '', 0, 1, 'notaform');
	print_table_header($vbphrase['admin_help_manager_ghelp_faq']);
	print_description_row(
		construct_link_code($vbphrase['add_new_topic'], "help.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit") .
		construct_link_code($vbphrase['edit_topics'], "help.php?" . vB::getCurrentSession()->get('sessionurl') . "do=manage") .
		construct_link_code($vbphrase['download_upload_adminhelp'], "help.php?" . vB::getCurrentSession()->get('sessionurl') . "do=files"), 0, 2, '', 'center');
	print_table_footer();
}

// ############################### start do upload help XML ##############
if ($_REQUEST['do'] == 'doimport')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'serverfile'	=> vB_Cleaner::TYPE_STR,
	));

	$vbulletin->input->clean_array_gpc('f', array(
		'helpfile'		=> vB_Cleaner::TYPE_FILE,
	));

	// got an uploaded file?
	// do not use file_exists here, under IIS it will return false in some cases
	if (is_uploaded_file($vbulletin->GPC['helpfile']['tmp_name']))
	{
		$xml = file_read($vbulletin->GPC['helpfile']['tmp_name']);
	}
	// no uploaded file - got a local file?
	else if (file_exists($vbulletin->GPC['serverfile']))
	{
		$xml = file_read($vbulletin->GPC['serverfile']);
	}
	// no uploaded file and no local file - ERROR
	else
	{
		print_stop_message2('no_file_uploaded_and_no_local_file_found_gerror');
	}

	xml_import_help_topics($xml);

	echo '<p align="center">' . $vbphrase['imported_admin_help_successfully'] . '<br />' . construct_link_code($vbphrase['continue'], "help.php?" . vB::getCurrentSession()->get('sessionurl') . "do=manage") . '</p>';
}

// ############################### start upload help XML ##############
if ($_REQUEST['do'] == 'files')
{
	// download form
	print_form_header('help', 'download', 0, 1, 'downloadform" target="download');
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

	print_form_header('help', 'doimport', 1, 1, 'uploadform" onsubmit="return js_confirm_upload(this, this.helpfile);');
	print_table_header($vbphrase['import_admin_help_xml_file']);
	print_upload_row($vbphrase['upload_xml_file'], 'helpfile', 999999999);
	print_input_row($vbphrase['import_xml_file'], 'serverfile', './install/vbulletin-adminhelp.xml');
	print_submit_row($vbphrase['import'], 0);
}

// ############################### start listing answers ##############
if ($_REQUEST['do'] == 'answer')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'page'			=> vB_Cleaner::TYPE_STR,
		'pageaction'	=> vB_Cleaner::TYPE_STR,
		'option'		=> vB_Cleaner::TYPE_STR
	));

	if (empty($vbulletin->GPC['page']))
	{
		$fullpage = REFERRER;
	}
	else
	{
		$fullpage = $vbulletin->GPC['page'];
	}

	if (!$fullpage)
	{
		print_stop_message2('invalid_page_specified');
	}
	$strpos = strpos($fullpage, '?');
	if ($strpos)
	{
		$pagename = basename(substr($fullpage, 0, $strpos));
	}
	else
	{
		$pagename = basename($fullpage);
	}
	$strpos = strpos($pagename, '.');
	if ($strpos)
	{
		$pagename = substr($pagename, 0, $strpos); // remove the .php part as people may have different extensions
	}

	if (!empty($vbulletin->GPC['pageaction']))
	{
		$action = $vbulletin->GPC['pageaction'];
	}
	else if ($strpos AND preg_match('#do=([^&]+)(&|$)#sU', substr($fullpage, $strpos), $matches))
	{
		$action = $matches[1];
	}
	else
	{
		$action = '';
	}

	$option = empty($vbulletin->GPC['option']) ? false : $vbulletin->GPC['option'];
	$helptopics = $assertor->assertQuery('vBForum:getHelpLength',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'pagename' => $pagename,
				'action' => $action,
				'option' => $option
			)
		);
	$resultcount = 0;
	if (!$helptopics AND !empty($helptopics['errors']) AND !$helptopics->valid())
	{
		print_stop_message2('no_help_topics');
	}
	else
	{
		$general = array();
		$specific = array();
		$phraseSQL = array();
		foreach ($helptopics AS $topic)
		{
			$resultcount ++;
			$phrasename = fetch_help_phrase_short_name($topic);
			$phraseSQL[] = "$phrasename" . "_title";
			$phraseSQL[] = "$phrasename" . "_text";

			if (!$topic['action'])
			{
				$general[] = $topic;
			}
			else
			{
				$specific[] = $topic;
			}
		}

		// query phrases
		$helpphrase = array();
		$phrases = $assertor->assertQuery('vBForum:phrase',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'fieldname', 'value' => 'cphelptext', 'operator' => vB_dB_Query::OPERATOR_EQ),
						array('field' => 'languageid', 'value' => array(-1, 0, LANGUAGEID), 'operator' => vB_dB_Query::OPERATOR_EQ),
						array('field' => 'varname', 'value' => $phraseSQL, 'operator' => vB_dB_Query::OPERATOR_EQ)
					)
				),
				array('field' => 'languageid', 'direction' => vB_dB_Query::SORT_ASC)
			);

		if ($phrases AND (is_object($phrases) OR empty($phrases['errors'])) AND $phrases->valid())
		{
			foreach($phrases AS $phrase)
			{
				$helpphrase["$phrase[varname]"] = preg_replace('#\{\$([a-z0-9_>-]+([a-z0-9_]+(\[[a-z0-9_]+\])*))\}#ie',
					'(isset($\\1) AND !is_array($\\1)) ? $\\1 : \'$\\1\'', $phrase['text']);
			}
		}

		if ($resultcount != 1)
		{
			print_form_header('', '');
			print_table_header($vbphrase['quick_help_topic_links'], 1);
			if (sizeof($specific))
			{
				print_description_row($vbphrase['action_specific_topics'], 0, 1, 'thead');
				foreach ($specific AS $topic)
				{
					print_description_row('<a href="#help' . $topic['adminhelpid'] . '">' . $helpphrase[fetch_help_phrase_short_name($topic, '_title')] . '</a>', 0, 1);
				}
			}
			if (sizeof($general))
			{
				print_description_row($vbphrase['general_topics'], 0, 1, 'thead');
				foreach ($general AS $topic)
				{
					print_description_row('<a href="#help' . $topic['adminhelpid'] . '">' . $helpphrase[fetch_help_phrase_short_name($topic, '_title')] . '</a>', 0, 1);
				}
			}
			print_table_footer();
		}

		if (sizeof($specific))
		{
			reset($specific);
			print_form_header('', '');
			if ($resultcount != 1)
			{
				print_table_header($vbphrase['action_specific_topics'], 1);
			}
			foreach ($specific AS $topic)
			{
				print_description_row("<a name=\"help$topic[adminhelpid]\">" . $helpphrase[fetch_help_phrase_short_name($topic, '_title')] . "</a>", 0, 1, 'thead');
				print_description_row($helpphrase[fetch_help_phrase_short_name($topic, '_text')], 0, 1, 'alt1');
				if ($vb5_config['Misc']['debug'])
				{
					print_description_row("<div style=\"float:" . vB_Template_Runtime::fetchStyleVar('right') . "\">" . construct_button_code($vbphrase['edit'], "help.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;adminhelpid=$topic[adminhelpid]") . "</div><div>action = $topic[action] | optionname = $topic[optionname] | displayorder = $topic[displayorder]</div>", 0, 1, 'alt2 smallfont');
				}
			}
			print_table_footer();
		}

		if (sizeof($general))
		{
			reset($general);
			print_form_header('', '');
			if ($resultcount != 1)
			{
				print_table_header($vbphrase['general_topics'], 1);
			}
			foreach ($general AS $topic)
			{
				print_description_row("<a name=\"help$topic[adminhelpid]\">" . $helpphrase[fetch_help_phrase_short_name($topic, '_title')] . "</a>", 0, 1, 'thead');
				print_description_row($helpphrase[fetch_help_phrase_short_name($topic, '_text')]);
			}
			print_table_footer();
		}
	}
}

// ############################### start form for adding/editing help topics ##############
if ($_REQUEST['do'] == 'edit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'adminhelpid'	=> vB_Cleaner::TYPE_INT,
		'script'	=> vB_Cleaner::TYPE_NOHTML,
		'scriptaction'	=> vB_Cleaner::TYPE_NOHTML,
		'option'	=> vB_Cleaner::TYPE_NOHTML,
	));

	$helpphrase = array();

	print_form_header('help', 'doedit');
	if (empty($vbulletin->GPC['adminhelpid']))
	{
		$adminhelpid = 0;
		$helpdata = array(
			'adminhelpid'  => 0,
			'script'       => $vbulletin->GPC['script'],
			'action'       => $vbulletin->GPC['scriptaction'],
			'optionname'   => $vbulletin->GPC['option'],
			'displayorder' => 1,
			'volatile'     => iif($vb5_config['Misc']['debug'], 1, 0)
		);

		print_table_header($vbphrase['add_new_topic']);
	}
	else
	{
		$helpdatas = $assertor->assertQuery('vBForum:adminhelp',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'adminhelpid' => $vbulletin->GPC['adminhelpid'])
		);
		$helpdata = array();
		if ($helpdatas AND $helpdatas->valid())
		{
			$helpdata = $helpdatas->current();
		}
		$action = $helpdata['action'] ? "_" . $helpdata['action'] : "";
		$optionname = $helpdata['optionname'] ? "_" . $helpdata['optionname'] : "";
		$textphrase = $helpdata['script'] . $action . $optionname . "_text";
		$titlephrase = $helpdata['script'] . $action . $optionname . "_title";
		$titlephrase = fetch_help_phrase_short_name($helpdata, '_title');
		$textphrase = fetch_help_phrase_short_name($helpdata, '_text');

		// query phrases
		$conditions = array();
		$conditions[] = array(
			'field'    => 'fieldname',
			'value'    => 'cphelptext',
			'operator' => vB_dB_Query::OPERATOR_EQ
		);
		if ($helpdata['volatile'])
		{
			$conditions[] = array(
				'field'    => 'languageid',
				'value'    => -1,
				'operator' => vB_dB_Query::OPERATOR_EQ
			);
		}
		else
		{
			$conditions[] = array(
				'field'    => 'languageid',
				'value'    => 0,
				'operator' => vB_dB_Query::OPERATOR_EQ
			);
		}
		$conditions[] = array('field' => 'varname','value' => array($titlephrase, $textphrase), 'operator' => vB_dB_Query::OPERATOR_EQ);
		$phrases = $assertor->assertQuery('vBForum:phrase',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, vB_dB_Query::CONDITIONS_KEY => $conditions)
		);
		if ($phrases AND $phrases->valid())
		{
			foreach ($phrases as $phrase)
			{
				$helpphrase["$phrase[varname]"] = $phrase['text'];
			}

		}
		unset($phrase);
		$vbulletin->db->free_result($phrases);

		construct_hidden_code('orig[script]', $helpdata['script']);
		construct_hidden_code('orig[action]', $helpdata['action']);
		construct_hidden_code('orig[optionname]', $helpdata['optionname']);
		construct_hidden_code('orig[product]', $helpdata['product']);
		construct_hidden_code('orig[title]', $helpphrase["$titlephrase"]);
		construct_hidden_code('orig[text]', $helpphrase["$textphrase"]);

		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['topic'], $helpdata['title'], $helpdata['adminhelpid']));
	}

	print_input_row($vbphrase['script'], 'help[script]', $helpdata['script']);
	print_input_row($vbphrase['action_leave_blank'], 'help[action]', $helpdata['action']);

	print_select_row($vbphrase['product'], 'help[product]', fetch_product_list(), $helpdata['product']);

	print_input_row($vbphrase['option'], 'help[optionname]', $helpdata['optionname']);
	print_input_row($vbphrase['display_order'], 'help[displayorder]', $helpdata['displayorder']);

	print_input_row($vbphrase['title'], 'title', $helpphrase["$titlephrase"]);
	print_textarea_row($vbphrase['text_gcpglobal'], 'text', $helpphrase["$textphrase"], 10, '50" style="width:100%');

	if ($vb5_config['Misc']['debug'])
	{
		print_yes_no_row($vbphrase['vbulletin_default'], 'help[volatile]', $helpdata['volatile']);
	}
	else
	{
		construct_hidden_code('help[volatile]', $helpdata['volatile']);
	}

	construct_hidden_code('adminhelpid', $vbulletin->GPC['adminhelpid']);
	print_submit_row($vbphrase['save']);
}

// ############################### start actually adding/editing help topics ##############
if ($_POST['do'] == 'doedit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'adminhelpid'	=> vB_Cleaner::TYPE_INT,
		'help'			=> vB_Cleaner::TYPE_ARRAY_STR,
		'orig'			=> vB_Cleaner::TYPE_ARRAY_STR,
		'title' 		=> vB_Cleaner::TYPE_STR,
		'text' 			=> vB_Cleaner::TYPE_STR
	));

	if (!$vbulletin->GPC['help']['script'])
	{
		print_stop_message2('please_complete_required_fields');
	}

	//no longer need the escape here, handled by db assetor
	//$newphrasename = $vbulletin->db->escape_string(fetch_help_phrase_short_name($vbulletin->GPC['help']));
	$newphrasename = fetch_help_phrase_short_name($vbulletin->GPC['help']);

	$languageid = iif($vbulletin->GPC['help']['volatile'], -1, 0);

	$full_product_info = fetch_product_list(true);
	$product_version = $full_product_info[$vbulletin->GPC['help']['product']]['version'];

	if (!empty($vbulletin->GPC['orig'])) // update
	{
		$action = $vbulletin->GPC['orig']['action'] ? "_" . $vbulletin->GPC['orig']['action'] : "";
		$optionname = $vbulletin->GPC['orig']['optionname'] ? "_" . $vbulletin->GPC['orig']['optionname'] : "";
//		$oldphrasename = $vbulletin->GPC['orig']['script'].$action.$optionname;
		$oldphrasename = fetch_help_phrase_short_name($vbulletin->GPC['orig']);

		// update help item
		$assertor->assertQuery('vBForum:adminhelp',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					'script' => $vbulletin->GPC['help']['script'],
					'action' => $vbulletin->GPC['help']['action'],
					'product' => $vbulletin->GPC['help']['product'],
					'optionname' => $vbulletin->GPC['help']['optionname'],
					'displayorder' => $vbulletin->GPC['help']['displayorder'],
					'volatile' => $vbulletin->GPC['help']['volatile'],
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field'=>'adminhelpid','value'=>$vbulletin->GPC['adminhelpid'], 'operator'=> vB_dB_Query::OPERATOR_EQ)
					)
				)
		);

		// update phrase titles for all languages
		if ($newphrasename != $oldphrasename)
		{
			$assertor->assertQuery('vBForum:phrase',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					'varname' => $newphrasename . "_title",
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'fieldname','value' => 'cphelptext', 'operator' => vB_dB_Query::OPERATOR_EQ),
						array('field' => 'varname', 'value' => $oldphrasename . "_title", 'operator' => vB_dB_Query::OPERATOR_EQ)
					)
				)
			);
			$assertor->assertQuery('vBForum:phrase',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					'varname' => $newphrasename . "_text",
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'fieldname','value' => 'cphelptext', 'operator' => vB_dB_Query::OPERATOR_EQ),
						array('field' => 'varname', 'value' => $oldphrasename . "_text", 'operator' => vB_dB_Query::OPERATOR_EQ)
					)
				)
			);
		}

		// update phrase title contents for master language
		if ($vbulletin->GPC['orig']['title'] != $vbulletin->GPC['title'])
		{
			$assertor->assertQuery('replaceIntoPhrases',
					array(vB_dB_Query::QUERY_STORED,
						'text' => $vbulletin->GPC['title'],
						'languageid' => $languageid,
						'varname' => $newphrasename . '_title',
						'product' => $vbulletin->GPC['help']['product'],
						'enteredBy' => $vbulletin->userinfo['username'],
						'dateline' => vB::getRequest()->getTimeNow(),
						'version' => $product_version,
					)
			);
		}
		else if ($vbulletin->GPC['orig']['product'] != $vbulletin->GPC['help']['product'])
		{
			// haven't changed the title, but we changed the product,
			// so we need to reflect that
			$assertor->assertQuery('vBForum:phrase',
					array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
						'product' => $vbulletin->GPC['help']['product'],
						'username' => $vbulletin->userinfo['username'],
						'dateline' => vB::getRequest()->getTimeNow(),
						'version' => $product_version,
						vB_dB_Query::CONDITIONS_KEY => array(
							array('field' => 'fieldname','value'=>'cphelptext', 'operator'=> vB_dB_Query::OPERATOR_EQ),
							array('field' => 'varname', 'value'=>$newphrasename . "_title", 'operator'=> vB_dB_Query::OPERATOR_EQ)
						)
					)
			);
		}

		// update phrase text contents for master language
		if ($vbulletin->GPC['orig']['text'] != $vbulletin->GPC['text'])
		{
			$assertor->assertQuery('replaceIntoPhrases',
					array(vB_dB_Query::QUERY_STORED,
						'text' => $vbulletin->GPC['text'],
						'languageid' => $languageid,
						'varname' => $newphrasename . '_text',
						'product' => $vbulletin->GPC['help']['product'],
						'enteredBy' => $vbulletin->userinfo['username'],
						'dateline' => vB::getRequest()->getTimeNow(),
						'version' => $product_version,
					)
			);
		}
		else if ($vbulletin->GPC['orig']['product'] != $vbulletin->GPC['help']['product'])
		{
			// haven't changed the text, but we changed the product,
			// so we need to reflect that
			$assertor->assertQuery('vBForum:phrase',
					array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
						'product' => $vbulletin->GPC['help']['product'],
						'username' => $vbulletin->userinfo['username'],
						'dateline' => vB::getRequest()->getTimeNow(),
						'version' => $product_version,
						vB_dB_Query::CONDITIONS_KEY => array(
							array('field' => 'fieldname','value'=>'cphelptext', 'operator'=> vB_dB_Query::OPERATOR_EQ),
							array('field' => 'varname', 'value'=>$newphrasename . "_text", 'operator'=> vB_dB_Query::OPERATOR_EQ)
						)
					)
			);
		}
	}
	else // insert
	{
		$sql = $assertor->assertQuery('vBForum:adminhelp',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'script' => $vbulletin->GPC['help']['script'],
				'action' => $vbulletin->GPC['help']['action'],
				'optionname' => $vbulletin->GPC['help']['optionname']
			)
		);

		if ($sql AND $sql->valid())
		{ // error message, this already exists
			// why phrase when its only available in debug mode and its meant for us?
			print_cp_message('This help item already exists.');
		}

		// insert help item
		$res = $assertor->assertQuery('vBForum:adminhelp',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
						'script' => $vbulletin->GPC['help']['script'],
						'action' => $vbulletin->GPC['help']['action'],
						'optionname' => $vbulletin->GPC['help']['optionname'],
						'displayorder'=> $vbulletin->GPC['help']['displayorder'],
						'volatile' => $vbulletin->GPC['help']['volatile'],
						'product' => $vbulletin->GPC['help']['product']
				)
		);

		// insert new phrases
		$assertor->assertQuery('vBForum:phrase',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_MULTIPLEINSERT,
				vB_dB_Query::FIELDS_KEY => array('languageid', 'fieldname', 'varname', 'text', 'product', 'username', 'dateline', 'version'),
				vB_Db_Query::VALUES_KEY => array(
					array($languageid, 'cphelptext', $newphrasename . "_title", $vbulletin->GPC['title'], $vbulletin->GPC['help']['product'], $vbulletin->userinfo['username'], vB::getRequest()->getTimeNow(), $product_version),
					array($languageid, 'cphelptext', $newphrasename . "_text", $vbulletin->GPC['text'], $vbulletin->GPC['help']['product'], $vbulletin->userinfo['username'], vB::getRequest()->getTimeNow(), $product_version)
				)
			)
		);
	}

	if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT)
	{
		require_once(DIR . '/includes/functions_filesystemxml.php');
		autoexport_write_help(array($vbulletin->GPC['orig']['product'],
			$vbulletin->GPC['help']['product']));
	}

	print_stop_message2(array('saved_topic_x_successfully', $vbulletin->GPC['title']), 'help',
		array('do' => 'manage', 'script' => $vbulletin->GPC['help']['script']));
}

// ############################### start confirmation for deleting a help topic ##############
if ($_REQUEST['do'] == 'delete')
{
	$vbulletin->input->clean_array_gpc('r', array('adminhelpid'	=> vB_Cleaner::TYPE_INT));

	print_delete_confirmation('adminhelp', $vbulletin->GPC['adminhelpid'], 'help', 'dodelete', 'topic');
}

// ############################### start actually deleting the help topic ##############
if ($_POST['do'] == 'dodelete')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'adminhelpid' => vB_Cleaner::TYPE_INT
	));

	$help = $assertor->assertQuery('vBForum:adminhelp',
		array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'adminhelpid'         => $vbulletin->GPC['adminhelpid']
	));

	if ($help AND $help->valid())
	{
		$result = $help->current();
		$assertor->assertQuery('vBForum:adminhelp', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, 'adminhelpid' => $vbulletin->GPC['adminhelpid']));

		// delete associated phrases
		$phrasename = $vbulletin->db->escape_string(fetch_help_phrase_short_name($result));
		$assertor->assertQuery('vBForum:phrase',
				array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
					'fieldname'           => 'cphelptext',
					'varname'             => array(
						$phrasename . '_title',
						$phrasename . '_text'
					)
		));

		// update language records
		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language();

		if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT)
		{
			require_once(DIR . '/includes/functions_filesystemxml.php');
			autoexport_write_help($result['product']);
		}
	}

	print_stop_message2('deleted_topic_successfully', 'help', array('do' => 'manage'));
}

// ############################### start list of existing help topics ##############
if ($_REQUEST['do'] == 'manage')
{
	$vbulletin->input->clean_array_gpc('r', array('script'	=> vB_Cleaner::TYPE_STR));

	// query phrases
	$helpphrase = array();
	$phrases = $assertor->assertQuery('vBForum:phrase',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'fieldname' => 'cphelptext')
	);
	if ($phrases AND $phrases->valid())
	{
		foreach($phrases AS $phrase)
		{
			$helpphrase["$phrase[varname]"] = $phrase['text'];
		}
	}

	// query scripts
	$scripts = array();
	$getscripts = $assertor->assertQuery('vBForum:getDistinctScriptHelp',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED)
	);
	if ($getscripts AND $getscripts->valid())
	{
		foreach($getscripts AS $getscript)
		{
			$scripts["$getscript[script]"] = "$getscript[script].php";
		}
	}

	// query topics
	$topics = array();
	$conditions = array();
	if ($vbulletin->GPC['script'])
	{
		$conditions[] = array('field' => 'script', 'value' => $vbulletin->GPC['script'], 'operator' => vB_dB_Query::OPERATOR_EQ);
	}
	$gettopics = $assertor->assertQuery('vBForum:adminhelp',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY=> $conditions),
			array('field' => 'script', 'direction' => vB_dB_Query::SORT_ASC)
	);
	if ($gettopics AND $gettopics->valid())
	{
		foreach($gettopics AS $gettopic)
		{
			$topics["$gettopic[script]"][] = $gettopic;
		}
	}

	// build the form
	print_form_header('help', 'manage', false, true, 'helpform' ,'90%', '', true, 'get');
	print_table_header($vbphrase['topic_manager'], 5);
	print_description_row('<div align="center">' . $vbphrase['script'] . ': <select name="script" tabindex="1" onchange="this.form.submit()" class="bginput"><option value="">' . $vbphrase['all_scripts_ghelp_faq'] . '</option>' . construct_select_options($scripts, $vbulletin->GPC['script']) . '</select> <input type="submit" class="button" value="' . $vbphrase['go'] . '" tabindex="1" /></div>', 0, 5, 'thead');

	foreach($topics AS $script => $scripttopics)
	{
		print_table_header($script . '.php', 5);
		print_cells_row(
			array(
				$vbphrase['action'],
				$vbphrase['option'],
				$vbphrase['title'],
				$vbphrase['order_by_gcpglobal'],
				''
			), 1, 0, -5
		);
		foreach($scripttopics AS $topic)
		{
			print_cells_row(
				array(
					'<span class="smallfont">' . $topic['action'] . '</span>',
					'<span class="smallfont">' . $topic['optionname'] . '</span>',
					'<span class="smallfont"><b>' . $helpphrase[fetch_help_phrase_short_name($topic, '_title')] . '</b></span>',
					'<span class="smallfont">' . $topic['displayorder'] . '</span>',
					'<span class="smallfont">' . construct_link_code($vbphrase['edit'], "help.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;adminhelpid=$topic[adminhelpid]") . construct_link_code($vbphrase['delete'], "help.php?" . vB::getCurrentSession()->get('sessionurl') . "do=delete&amp;adminhelpid=$topic[adminhelpid]") . '</span>'
				), 0, 0, -5
			);
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
