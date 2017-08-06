<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright  2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
@set_time_limit(0);
ignore_user_abort(true);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 71443 $');
if ($_POST['do'] == 'updatetemplate' OR $_POST['do'] == 'inserttemplate' OR $_REQUEST['do'] == 'createfiles')
{
	// double output buffering does some weird things, so turn it off in these three cases
	DEFINE('NOZIP', 1);
}

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbulletin, $session, $stylestuff;
	global $masterset,$only;
	global $SHOWTEMPLATE, $vbphrase;

$phrasegroups = array('style');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/adminfunctions_template.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminstyles'))
{
	print_cp_no_permission();
}

$vbulletin->input->clean_array_gpc('r', array(
	'templateid'   => vB_Cleaner::TYPE_INT,
	'dostyleid'    => vB_Cleaner::TYPE_INT,
));

// ############################# LOG ACTION ###############################
log_admin_action(!empty($vbulletin->GPC['templateid']) ? 'template id = ' . $vbulletin->GPC['templateid'] : !empty($vbulletin->GPC['dostyleid']) ? 'style id = ' . $vbulletin->GPC['dostyleid'] : '');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vb5_config =& vB::getConfig();
$vb5_options = vB::getDatastore()->getValue('options');

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}
else
{
	$nozipDos = array('inserttemplate', 'rebuild', 'kill', 'insertstyle', 'killstyle', 'updatestyle');
	if (in_array($_REQUEST['do'], $nozipDos))
	{
		$vbulletin->nozip = true;
	}
}

$full_product_info = fetch_product_list(true);

if ($vb5_options['storecssasfile'])
{
	$cssfile = $vb5_options['bburl'] . '/clientscript/vbulletin_css/style' . str_pad($vbulletin->options['styleid'], 5, '0', STR_PAD_LEFT) . $vbulletin->stylevars['textdirection']['string'][0] . '/' . 'stylegenerator.css';
}
else
{
	$cssfile = $vb5_options['bburl'] . '/css.php?styleid=' . $vb5_options['styleid'] . '&amp;langid=' . LANGUAGEID . '&amp;d=' . $stylestuff['dateline'] . '&amp;td=' . $vbulletin->stylevars['textdirection']['string'] . '&amp;sheet=stylegenerator.css';
}

// Javascript that is required only for the style generator - Ignored on the rest
$stylegeneratorjs = null;
if ($_REQUEST['do'] == 'stylegenerator' AND !(is_browser('ie') AND !is_browser('ie', 7)))
{
	$stylegeneratorjs_phrases = array(
		'style_generator_browser_not_supported', 'primary_color', 'secondary_color_a',
		'complementary_color', 'hide_tooltips', 'show_tooltips', 'err_order_too_large',
		'err_order_negative', 'err_order_need_name', 'err_order_need_order', 'err_invalid_name',
		'cancel_js', 'ok_js', 'enter_hue', 'enter_complement_hue', 'enter_distance_angle',
		'enter_hex_value', 'enter_hex'
	);

	$stylegeneratorjs_phrasetext = "\nvar vbphrase = (typeof(vbphrase) == \"undefined\" ? new Array() : vbphrase);\n";
	foreach($stylegeneratorjs_phrases as $phrase)
	{
		$stylegeneratorjs_phrasetext .= "\t" . 'vbphrase["' . $phrase . '"] = "' . $vbphrase[$phrase] . '";' . "\n";
	}

	$stylegeneratorjs =
		'<link rel="stylesheet" href="' . $cssfile . '" type="text/css">
		<script type="text/javascript">' . $stylegeneratorjs_phrasetext . '</script>
		<script type="text/javascript" src="' . $vb5_options['bburl'] . '/clientscript/jquery/jquery-1.3.min.js?v=' . SIMPLE_VERSION . '"></script>
		<script type="text/javascript" src="' . $vb5_options['bburl'] . '/clientscript/jquery/jquery.styledselect.js?v=' . SIMPLE_VERSION . '"></script>
		<script type="text/javascript" src="' . $vb5_options['bburl'] . '/clientscript/jquery/jquery.floatbox.js?v=' . SIMPLE_VERSION . '"></script>
		<script type="text/javascript" src="' . $vb5_options['bburl'] . '/clientscript/jquery/jquery.tooltip.js?v=' . SIMPLE_VERSION . '"></script>
		<script type="text/javascript" src="' . $vb5_options['bburl'] . '/clientscript/jquery/jquery.droppy.js?v=' . SIMPLE_VERSION . '"></script>
		<script type="text/javascript" src="' . $vb5_options['bburl'] . '/clientscript/vbulletin_stylegeneratorcolor.js?v=' . SIMPLE_VERSION . '"></script>
		<script type="text/javascript" src="' . $vb5_options['bburl'] . '/clientscript/vbulletin_stylegenerator.js?v=' . SIMPLE_VERSION . '"></script>';
}

if ($_REQUEST['do'] != 'download')
{
	print_cp_header($vbphrase['style_manager_gstyle'], iif($_REQUEST['do'] == 'files', 'js_fetch_style_title()'), $stylegeneratorjs);
	?><script type="text/javascript" src="<?php echo $vb5_options['bburl']; ?>/clientscript/vbulletin_templatemgr.js?v=<?php echo SIMPLE_VERSION; ?>"></script><?php
}

// #############################################################################
// find custom templates that need updating

if ($_REQUEST['do'] == 'findupdates')
{
	$customcache = fetch_changed_templates();
	if (empty($customcache))
	{
		print_stop_message2('all_templates_are_up_to_date');
	}

	$stylecache = vB_Library::instance('Style')->fetchStyles(false, false);

	print_form_header('template', 'dismissmerge');
	print_table_header($vbphrase['updated_default_templates']);
	print_description_row('<span class="smallfont">' .
	construct_phrase($vbphrase['updated_default_templates_desc'],
	$vbulletin->options['templateversion']) . '</span>');
	print_table_break(' ');

	$have_dismissible = false;

	foreach ($stylecache AS $styleid => $style)
	{
		if (is_array($customcache["$styleid"]))
		{
			print_description_row($style['title'], 0, 3, 'thead');
			foreach ($customcache["$styleid"] AS $templateid => $template)
			{
				if (!$template['customuser'])
				{
					$template['customuser'] = $vbphrase['n_a'];
				}
				if (!$template['customversion'])
				{
					$template['customversion'] = $vbphrase['n_a'];
				}

				$product_name = $full_product_info["$template[product]"]['title'];

				if ($template['custommergestatus'] == 'merged')
				{
					$merge_text = '<span class="smallfont template-text-merged">'
						. $vbphrase['changes_automatically_merged_into_template']
						. '<span>';
				}
				else if ($template['custommergestatus'] == 'conflicted')
				{
					$merge_text = '<span class="smallfont template-text-conflicted">'
						. $vbphrase['attempted_merge_failed_conflicts']
						. '<span>';
				}
				else
				{
					$merge_text = '';
				}

				$title =
					"<b>$template[title]</b><br />"
					. "<span class=\"smallfont\">"
					. construct_phrase($vbphrase['default_template_updated_desc'],
						"$product_name $template[globalversion]",
						$template['globaluser'],
						"$product_name $template[customversion]",
						$template['customuser']
					)
					. '</span><br/>' . $merge_text
				;

				$links = array();

				$links[] = construct_link_code($vbphrase['edit_template'],
					"template.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;templateid=$templateid",
					'templatewin');

				$links[] = construct_link_code($vbphrase['view_highlighted_changes'],
					"template.php?" . vB::getCurrentSession()->get('sessionurl') .
						"do=docompare3&amp;templateid=$templateid",
					'templatewin');

				if ($template['custommergestatus'] == 'merged' AND $template['savedtemplateid'])
				{
					$links[] = construct_link_code($vbphrase['view_pre_merge_version'],
						"template.php?" . vB::getCurrentSession()->get('sessionurl') .
							"do=viewversion&amp;id=$template[savedtemplateid]&amp;type=historical",
						'templatewin');
				}

				$links[] = construct_link_code($vbphrase['revert_gcpglobal'],
					"template.php?" . vB::getCurrentSession()->get('sessionurl') .
						"do=delete&amp;templateid=$templateid&amp;dostyleid=$styleid",
					'templatewin');

				$value = '<span class="smallfont">' . implode('<br />', $links) . '</span>';

				if ($template['custommergestatus'] == 'merged')
				{
					$dismiss_checkbox = '<input type="checkbox" name="dismiss_merge[]" value="' . $templateid . '" />';
					$have_dismissible = true;
				}
				else
				{
					$dismiss_checkbox = '&nbsp;';
				}

				$cells = array(
					$title,
					$value,
					$dismiss_checkbox
				);

				print_cells_row($cells, false, false, -1);
			}
		}
	}

	if ($have_dismissible)
	{
		print_submit_row($vbphrase['dismiss_selected_notifications'], false, 3);
		echo '<p class="smallfont" align="center">' . $vbphrase['dismissing_merge_notifications_cause_not_appear'] . '</p>';
	}
	else
	{
		print_table_footer();
	}
}

// #############################################################################
if ($_REQUEST['do'] == 'dismissmerge')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'dismiss_merge' => vB_Cleaner::TYPE_ARRAY_UINT,
	));

	if (!$vbulletin->GPC['dismiss_merge'])
	{
		print_stop_message2('did_not_select_merge_notifications_dismiss');
	}
	else
	{
		print_form_header('template', 'dodismissmerge');
		print_table_header($vbphrase['dismiss_template_merge_notifications']);
		print_description_row(construct_phrase($vbphrase['sure_dismiss_x_merge_notifications'], sizeof($vbulletin->GPC['dismiss_merge'])));
		foreach ($vbulletin->GPC['dismiss_merge'] AS $templateid)
		{
			construct_hidden_code("dismiss_merge[$templateid]", $templateid);
		}
		print_submit_row($vbphrase['dismiss_template_merge_notifications'], false);
	}
}

// #############################################################################
if ($_POST['do'] == 'dodismissmerge')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'dismiss_merge' => vB_Cleaner::TYPE_ARRAY_UINT,
	));

	if ($vbulletin->GPC['dismiss_merge'])
	{
		vB_Api::instanceInternal('template')->dismissMerge($vbulletin->GPC['dismiss_merge']);
	}

	print_stop_message2('template_merge_notifications_dismissed', 'template', array('do' => 'findupdates'));
}

// #############################################################################
// download style

if ($_REQUEST['do'] == 'download')
{

	if (function_exists('set_time_limit') AND !SAFEMODE)
	{
		@set_time_limit(1200);
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'filename' => vB_Cleaner::TYPE_STR,
		'title'    => vB_Cleaner::TYPE_NOHTML,
		'mode'     => vB_Cleaner::TYPE_UINT,
		'product'  => vB_Cleaner::TYPE_STR,
	));

	// --------------------------------------------
	// work out what we are supposed to do

	// set a default filename
	if (empty($vbulletin->GPC['filename']))
	{
		$vbulletin->GPC['filename'] = 'vbulletin-style.xml';
	}

	$doc = get_style_export_xml(
		$vbulletin->GPC['dostyleid'],
		$vbulletin->GPC['product'],
		$full_product_info[$vbulletin->GPC['product']]['version'],
		$vbulletin->GPC['title'],
		$vbulletin->GPC['mode']
	);

	require_once(DIR . '/includes/functions_file.php');
	file_download($doc, $vbulletin->GPC['filename'], 'text/xml');
}

// #############################################################################
// upload style
if ($_REQUEST['do'] == 'upload')
{
	$fields = array(
		'overwritestyleid' => vB_Cleaner::TYPE_INT,
		'serverfile'       => vB_Cleaner::TYPE_STR,
		'parentid'         => vB_Cleaner::TYPE_INT,
		'title'            => vB_Cleaner::TYPE_STR,
		'anyversion'       => vB_Cleaner::TYPE_BOOL,
		'displayorder'     => vB_Cleaner::TYPE_INT,
		'userselect'       => vB_Cleaner::TYPE_BOOL,
		'startat'          => vB_Cleaner::TYPE_INT,
	);

	$vbulletin->input->clean_array_gpc('r', $fields);
	$vbulletin->input->clean_array_gpc('f', array(
		'stylefile'        => vB_Cleaner::TYPE_FILE,
	));

	// Legacy Hook 'admin_style_import' Removed //

	//only do multipage processing for a local file.  If we do it for an uploaded file we need
	//to figure out how to
	//a) store the file locally so it will be available on subsequent page loads.
	//b) make sure that that location is shared across an load balanced servers (which
	//	eliminates any php tempfile functions)

	// got an uploaded file?
	// do not use file_exists here, under IIS it will return false in some cases
	if (is_uploaded_file($vbulletin->GPC['stylefile']['tmp_name']))
	{
		$xml = file_read($vbulletin->GPC['stylefile']['tmp_name']);
		$startat = null;
		$perpage = null;
	}
	// no uploaded file - got a local file?
	else
	{
		$serverfile = vB5_Route_Admincp::resolvePath(urldecode($vbulletin->GPC['serverfile']));
		if (file_exists($serverfile))
		{
				$xml = file_read($serverfile);
			$startat = $vbulletin->GPC['startat'];
			$perpage = 10;
		}
		// no uploaded file and no local file - ERROR
		else
		{
			print_stop_message2('no_file_uploaded_and_no_local_file_found_gerror');
		}
	}

	$imported = xml_import_style($xml,
		$vbulletin->GPC['overwritestyleid'], $vbulletin->GPC['parentid'], $vbulletin->GPC['title'],
		$vbulletin->GPC['anyversion'], $vbulletin->GPC['displayorder'], $vbulletin->GPC['userselect'],
		$startat, $perpage
	);

	if (!$imported['done'])
	{
		//build the next page url;
		$startat = $startat + $perpage;
		$args = array();
		$args['do'] = 'upload';
		$args['startat'] = $startat;

		unset($fields['startat']);
		foreach($fields AS $name => $type)
		{
			//if its some other type this trick probably won't work and will need to be
			//handled seperately.
			if ($type == vB_Cleaner::TYPE_STR)
			{
				$args[$name] = $vbulletin->GPC[$name];
			}
			else if ($type == vB_Cleaner::TYPE_INT OR $type = vB_Cleaner::TYPE_BOOL)
			{
				$args[$name] = intval($vbulletin->GPC[$name]);
			}
		}
		print_cp_redirect2('template', $args);
	}

	if ($imported['master'])
	{
		$args = array();
		$args['do'] = 'massmerge';
		$args['product'] = urlencode($imported['product']);
		$args['hash'] = CP_SESSIONHASH;
		print_cp_redirect2('template', $args);
	}
	else
	{
		$args = array();
		parse_str(vB::getCurrentSession()->get('sessionurl'), $args);
		$args['do'] = 'rebuild';
		$args['goto'] = 'template.php?' . vB::getCurrentSession()->get('sessionurl');
		print_cp_redirect2('template', $args, 1);
	}
}

// #############################################################################
// file manager
if ($_REQUEST['do'] == 'files')
{

	$stylecache = vB_Library::instance('Style')->fetchStyles(false, false);
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
	function js_fetch_style_title()
	{
		styleid = document.forms.downloadform.dostyleid.options[document.forms.downloadform.dostyleid.selectedIndex].value;
		document.forms.downloadform.title.value = style[styleid];
	}
	var style = new Array();
	style['-1'] = "<?php echo $vbphrase['master_style'] . '";';
	foreach($stylecache AS $styleid => $style)
	{
		echo "\n\tstyle['$styleid'] = \"" . addslashes_js($style['title'], '"') . "\";";
		$styleoptions["$styleid"] = construct_depth_mark($style['depth'], '--', iif($vb5_config['Misc']['debug'], '--', '')) . ' ' . $style['title'];
	}
	echo "\n";
	?>
	// -->
	</script>
	<?php

	print_form_header('template', 'download', 0, 1, 'downloadform" target="download');
	print_table_header($vbphrase['download']);
	print_label_row($vbphrase['style'], '
		<select name="dostyleid" onchange="js_fetch_style_title();" tabindex="1" class="bginput">
		' . iif($vb5_config['Misc']['debug'], '<option value="-1">' . $vbphrase['master_style'] . '</option>') . '
		' . construct_select_options($styleoptions, $vbulletin->GPC['dostyleid']) . '
		</select>
	', '', 'top', 'dostyleid');
	print_select_row($vbphrase['product'], 'product', fetch_product_list());
	print_input_row($vbphrase['title'], 'title');
	print_input_row($vbphrase['filename_gcpglobal'], 'filename', 'vbulletin-style.xml');
	print_label_row($vbphrase['options'], '
		<span class="smallfont">
			<label for="rb_mode_0"><input type="radio" name="mode" value="0" id="rb_mode_0" tabindex="1" checked="checked" />' . $vbphrase['get_customizations_from_this_style_only'] . '</label><br />
			<label for="rb_mode_1"><input type="radio" name="mode" value="1" id="rb_mode_1" tabindex="1" />' . $vbphrase['get_customizations_from_parent_styles'] . '</label><br />' .
			($vb5_config['Misc']['debug'] ? '<label for="rb_mode_2"><input type="radio" name="mode" value="2" id="rb_mode_2" tabindex="1" checked="checked" />' . $vbphrase['download_as_master'] . '</label>' : '') . '
		</span>
	', '', 'top', 'mode');
	print_submit_row($vbphrase['download']);

	print_form_header('template', 'upload', 1, 1, 'uploadform" onsubmit="return js_confirm_upload(this, this.stylefile);');
	print_table_header($vbphrase['import_style_xml_file']);
	print_upload_row($vbphrase['upload_xml_file'], 'stylefile', 999999999);
	print_input_row($vbphrase['import_xml_file'], 'serverfile', './install/vbulletin-style.xml');
	print_style_chooser_row('overwritestyleid', -1, '(' . $vbphrase['create_new_style'] . ')', $vbphrase['overwrite_style'], 1);
	print_yes_no_row($vbphrase['ignore_style_version'], 'anyversion', 0);
	print_description_row($vbphrase['following_options_apply_only_if_new_style'], 0, 2, 'thead" style="font-weight:normal; text-align:center');
	print_input_row($vbphrase['title_for_uploaded_style'], 'title');
	print_style_chooser_row('parentid', -1, $vbphrase['no_parent_style'], $vbphrase['parent_style'], 1);
	print_input_row($vbphrase['display_order'], 'displayorder', 1);
	print_yes_no_row($vbphrase['allow_user_selection'], 'userselect', 1);

	print_submit_row($vbphrase['import']);

}

// #############################################################################
// find & replace
if ($_POST['do'] == 'replace')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'startat_template' => vB_Cleaner::TYPE_INT,
		'startat_style'    => vB_Cleaner::TYPE_INT,
		'requirerebuild'   => vB_Cleaner::TYPE_BOOL,
		'test'             => vB_Cleaner::TYPE_BOOL,
		'regex'            => vB_Cleaner::TYPE_BOOL,
		'case_insensitive' => vB_Cleaner::TYPE_BOOL,
		'searchstring'     => vB_Cleaner::TYPE_NOTRIM,
		'replacestring'    => vB_Cleaner::TYPE_NOTRIM,
	));
	$result = vB_Api::instanceInternal('template')->searchAndReplace(
		$vbulletin->GPC['dostyleid'],
		$vbulletin->GPC['searchstring'],
		$vbulletin->GPC['replacestring'],
		$vbulletin->GPC['case_insensitive'],
		$vbulletin->GPC['regex'],
		$vbulletin->GPC['test'],
		$vbulletin->GPC['startat_style'],
		$vbulletin->GPC['startat_template']
	);
	if (empty($result))
	{
		print_stop_message2('completed_search_successfully', 'template', array('do' => 'search'));
	}

	echo "<p><b>" . construct_phrase($vbphrase['search_in_x'], "<i>{$result[styleinfo][title]}</i>") . "</b></p>\n";

	echo "<p><b>$vbphrase[search_results]</b><br />$vbphrase[page_gcpglobal] {$result[stats][page]}, $vbphrase[templates] {$result[stats][first]} - {$result[stats][last]}</p>" . iif($vbulletin->GPC['test'], "<p><i>$vbphrase[test_replace_only]</i></p>") . "\n";
	if ($vbulletin->GPC['regex'])
	{
		echo "<p span=\"smallfont\"><b>" . $vbphrase['regular_expression_used'] . ":</b> " . htmlspecialchars_uni("#" . $vbulletin->GPC['searchstring'] . "#siU") . "</p>\n";
	}
	echo "<ol class=\"smallfont\" start=\"{$result[stats][first]}\">\n";
	foreach ($result['processed_templates'] as $temp) {
		echo "<li><a href=\"template.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;templateid=$temp[templateid]&amp;dostyleid=$temp[styleid]\">$temp[title]</a>\n";
		vbflush();
		if ($vbulletin->GPC['test'])
		{
			if ($temp['newtemplate'] != htmlspecialchars_uni($temp['template_un']))
			{
				echo "<hr />\n<font size=\"+1\"><b>$temp[title]</b></font> (templateid: $temp[templateid], styleid: $temp[styleid])\n<pre class=\"smallfont\">" . str_replace("\t", " &nbsp; &nbsp; ", $temp['newtemplate']) . "</pre><hr />\n</li>\n";
			}
			else
			{
				echo ' (' . $vbphrase['0_matches_found'] . ")</li>\n";
			}
		}
		else
		{
			if ($temp['newtemplate'] != htmlspecialchars_uni($temp['template_un']))
			{
				echo "<span class=\"col-i\"><b>" . $vbphrase['done'] . "</b></span></li>\n";
			}
			else
			{
				echo ' (' . $vbphrase['0_matches_found'] . ")</li>\n";
			}
		}
	}
	echo "</ol>\n";

	print_form_header('template', 'replace', false, false);
		construct_hidden_code('regex', $vbulletin->GPC['regex']);
		construct_hidden_code('case_insensitive', $vbulletin->GPC['case_insensitive']);
		construct_hidden_code('requirerebuild', $result['requirerebuild']);
		construct_hidden_code('test', $vbulletin->GPC['test']);
		construct_hidden_code('dostyleid', $vbulletin->GPC['dostyleid']);
		construct_hidden_code('startat_template', $result['startat_template']);
		construct_hidden_code('startat_style', $result['startat_style']);
		construct_hidden_code('searchstring', $vbulletin->GPC['searchstring']);
		construct_hidden_code('replacestring', $vbulletin->GPC['replacestring']);
		echo "<input type=\"submit\" class=\"button\" tabindex=\"1\" value=\"$vbphrase[next_page]\" accesskey=\"s\" />";
	print_table_footer();

	print_cp_footer();
}

// #############################################################################
// form for search / find & replace
if ($_REQUEST['do'] == 'search')
{

	// search only
	print_form_header('template', 'modify', false, true, 'sform', '90%', '', true, 'get');
	print_table_header($vbphrase['search_templates']);
	print_style_chooser_row("searchset", $vbulletin->GPC['dostyleid'], $vbphrase['search_in_all_styles'] . iif($vb5_config['Misc']['debug'], ' (' . $vbphrase['including_master_style'] . ')'), $vbphrase['search_in_style'], 1);
	print_textarea_row($vbphrase['search_for_text'], "searchstring");
	print_yes_no_row($vbphrase['search_titles_only'], "titlesonly", 0);
	print_submit_row($vbphrase['find']);

	// search & replace
	print_form_header('template', 'replace', 0, 1, 'srform');
	print_table_header($vbphrase['find_and_replace_in_templates']);
	print_style_chooser_row("dostyleid", $vbulletin->GPC['dostyleid'], $vbphrase['search_in_all_styles'] .  iif($vb5_config['Misc']['debug'], ' (' . $vbphrase['including_master_style'] . ')'), $vbphrase['search_in_style'], 1);
	print_textarea_row($vbphrase['search_for_text'], 'searchstring', '', 5, 60, 1, 0);
	print_textarea_row($vbphrase['replace_with_text'], 'replacestring', '', 5, 60, 1, 0);
	print_yes_no_row($vbphrase['test_replace_only'], 'test', 1);
	print_yes_no_row($vbphrase['use_regular_expressions'], 'regex', 0);
	print_yes_no_row($vbphrase['case_insensitive'], 'case_insensitive', 0);
	print_submit_row($vbphrase['find']);

	print_form_header('', '', 0, 1, 'regexform');
	print_table_header($vbphrase['notes_for_using_regex_in_find_replace']);
	print_description_row($vbphrase['regex_help']);
	print_table_footer(2, $vbphrase['strongly_recommend_testing_regex_replace']);

}

// #############################################################################
// query to insert a new style
// $dostyleid then gets passed to 'updatestyle' for cache and template list rebuild
if ($_POST['do'] == 'insertstyle')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'title'        => vB_Cleaner::TYPE_STR,
		'displayorder' => vB_Cleaner::TYPE_INT,
		'parentid'     => vB_Cleaner::TYPE_INT,
		'userselect'   => vB_Cleaner::TYPE_INT,
		'displayorder' => vB_Cleaner::TYPE_UINT,
		'group'        => vB_Cleaner::TYPE_STR,
	));

	$result = vB_Api::instance('style')->insertStyle($vbulletin->GPC['title'], $vbulletin->GPC['parentid'],
		$vbulletin->GPC['userselect'], $vbulletin->GPC['displayorder']);

	//we can't easily display multiple errors in the admincp code, so display the first one.
	if(isset($result['errors'][0]))
	{
		print_stop_message2($result['errors'][0]);
	}

	print_stop_message2(array('saved_style_x_successfully', $vbulletin->GPC['title']), 'template',
		array('do' => 'modify', 'expandset' => $result, 'group' => $vbulletin->GPC['group']));
}

// #############################################################################
// form to create a new style
if ($_REQUEST['do'] == 'addstyle')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'parentid' => vB_Cleaner::TYPE_INT,
	));

	$stylecache = vB_Library::instance('Style')->fetchStyles(false, false);

	if ($vbulletin->GPC['parentid'] > 0 AND is_array($stylecache["{$vbulletin->GPC['parentid']}"]))
	{
		$title = construct_phrase($vbphrase['child_of_x'], $stylecache["{$vbulletin->GPC['parentid']}"]['title']);
	}

	print_form_header('template', 'insertstyle');
	print_table_header($vbphrase['add_new_style']);
	print_style_chooser_row('parentid', $vbulletin->GPC['parentid'], $vbphrase['no_parent_style'], $vbphrase['parent_style'], 1);
	print_input_row($vbphrase['title'], 'title', $title);
	print_yes_no_row($vbphrase['allow_user_selection'], 'userselect', 1);
	print_input_row($vbphrase['display_order'], 'displayorder');

	// Legacy Hook 'admin_style_form' Removed //

	print_submit_row($vbphrase['save']);

}

// #############################################################################
// query to update a style
// also rebuilds parent lists and template id cache if parentid is altered
if ($_POST['do'] == 'updatestyle')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'parentid'     => vB_Cleaner::TYPE_INT,
		'oldparentid'  => vB_Cleaner::TYPE_INT,
		'userselect'   => vB_Cleaner::TYPE_INT,
		'displayorder' => vB_Cleaner::TYPE_UINT,
		'title'        => vB_Cleaner::TYPE_STR,
		'group'        => vB_Cleaner::TYPE_STR,
	));

	$result = vB_Api::instance('style')->updateStyle($vbulletin->GPC['dostyleid'], $vbulletin->GPC['title'], $vbulletin->GPC['parentid'],
		$vbulletin->GPC['userselect'], $vbulletin->GPC['displayorder'],
		($vbulletin->GPC['parentid'] != $vbulletin->GPC['oldparentid']));

	//we can't easily display multiple errors in the admincp code, so display the first one.
	if(isset($result['errors'][0]))
	{
		print_stop_message2($result['errors'][0]);
	}

	print_stop_message2(array('saved_style_x_successfully', $vbulletin->GPC['title']), 'template',
		array('do' => 'modify', 'expandset' => $vbulletin->GPC['dostyleid'], 'group' => $vbulletin->GPC['group']));
}

// #############################################################################
// form to edit a style
if ($_REQUEST['do'] == 'editstyle')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'dostyleid' => vB_Cleaner::TYPE_INT,
	));

	$style = vB_Library::instance('Style')->fetchStyleByID($vbulletin->GPC['dostyleid']);

	print_form_header('template', 'updatestyle');
	construct_hidden_code('dostyleid', $vbulletin->GPC['dostyleid']);
	construct_hidden_code('oldparentid', $style['parentid']);
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['style'], $style['title'], $style['styleid']), 2, 0);
	print_style_chooser_row('parentid', $style['parentid'], $vbphrase['no_parent_style'], $vbphrase['parent_style'], 1);
	print_input_row($vbphrase['title'], 'title', $style['title']);
	print_yes_no_row($vbphrase['allow_user_selection'], 'userselect', $style['userselect']);
	print_input_row($vbphrase['display_order'], 'displayorder', $style['displayorder']);

	// Legacy Hook 'admin_style_form' Removed //

	print_submit_row($vbphrase['save']);

}

// #############################################################################
// kill a style, set parents for child forums and update template id caches for dependent styles
if ($_POST['do'] == 'killstyle')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'parentid'   => vB_Cleaner::TYPE_INT,
		'parentlist' => vB_Cleaner::TYPE_STR,
		'group'      => vB_Cleaner::TYPE_STR,
	));

	$result = vB_Api::instance('style')->deleteStyle($vbulletin->GPC['dostyleid']);
	//we can't easily display multiple errors in the admincp code, so display the first one.
	if(isset($result['errors'][0]))
	{
		print_stop_message2($result['errors'][0]);
	}

	print_cp_redirect2('template', array('do' => 'modify', 'group' =>  $vbulletin->GPC['group']), 1);
}

// #############################################################################
// delete style - confirmation for style deletion
if ($_REQUEST['do'] == 'deletestyle')
{

	if ($vbulletin->GPC['dostyleid'] == $vbulletin->options['styleid'])
	{
		print_stop_message2('cant_delete_default_style');
	}

	// look at how many styles are being deleted
	$count = vB::getDbAssertor()->getRow('style_count', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));

	// check that this isn't the last one that we're about to delete
	$style = vB_Library::instance('Style')->fetchStyleByID($vbulletin->GPC['dostyleid']);
	if ($count['styles'] == 1 AND $style['userselect'] == 1)
	{
		print_stop_message2('cant_delete_last_style');
	}

	$hidden = array();
	$hidden['parentid'] = $style['parentid'];
	$hidden['parentlist'] = $style['parentlist'];
	print_delete_confirmation('style', $vbulletin->GPC['dostyleid'], 'template', 'killstyle', 'style', $hidden, $vbphrase['please_be_aware_this_will_delete_custom_templates']);

}

// #############################################################################
// do revert all templates in a style
if ($_POST['do'] == 'dorevertall')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'group' => vB_Cleaner::TYPE_STR,
	));

	try
	{
		if (!vB_Api::instance('template')->revertAllInStyle($vbulletin->GPC['dostyleid']))
		{
			print_stop_message2('nothing_to_do');
		}
		$args = array();
		parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
		$args['do'] = 'modify';
		$args['expandset'] = $style['styleid'];
		$args['group'] = $vbulletin->GPC['group'];
		print_cp_redirect2('template', $args, 1);
	}
	catch (vB_Exception_Api $e)
	{
		print_stop_message2('invalid_style_specified');
	}

}

// #############################################################################
// revert all templates in a style
if ($_REQUEST['do'] == 'revertall')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'group' => vB_Cleaner::TYPE_STR,
	));

	if ($vbulletin->GPC['dostyleid'] != -1 AND $style = vB_Library::instance('Style')->fetchStyleByID($vbulletin->GPC['dostyleid']))
	{
		if (!$style['parentlist'])
		{
			$style['parentlist'] = '-1';
		}
		$templates = vB::getDbAssertor()->assertQuery('template_getrevertingtemplates', array(
			'styleparentlist' => $style['parentlist'],
			'styleid'	=> $style['styleid'],
		));

		if ($templates->valid())
		{
			$templatelist = '';
			foreach ($templates as $template)
			{
				$templatelist .= "<li>$template[title]</li>\n";
			}
			echo "<br /><br />";

			print_form_header('template', 'dorevertall');
			print_table_header($vbphrase['revert_all_templates']);
			print_description_row("
				<blockquote><br />
				" . construct_phrase($vbphrase["revert_all_templates_from_style_x"], $style['title'], $templatelist) . "
				<br /></blockquote>
			");
			construct_hidden_code('dostyleid', $style['styleid']);
			construct_hidden_code('group', $vbulletin->GPC['group']);
			print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);

		}
		else
		{
			print_stop_message2('nothing_to_do');
		}
	}
	else
	{
		print_stop_message2('invalid_style_specified');
	}
}

// #############################################################################
if ($_REQUEST['do'] == 'massmerge')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'startat'  => vB_Cleaner::TYPE_UINT,
		'product'  => vB_Cleaner::TYPE_STR,
		'redirect' => vB_Cleaner::TYPE_STR,
	));

	verify_cp_sessionhash();

	$result = vB_Api::instance('template')->massMerge($vbulletin->GPC['product'], $vbulletin->GPC['startat']);
	if ($result == -1)
	{
		$file = '';
		$args = array();
		if ($vbulletin->GPC['redirect'])
		{
			$redirect = vB_String::parseUrl($vbulletin->GPC['redirect']);
			$pathinfo = pathinfo($redirect['path']);
			list($file) = explode('.',$pathinfo['basename']);
			parse_str($redirect['query'], $args);
		}
		print_stop_message2('templates_merged', $file, $args);
	}
	else
	{
		// more templates to merge
		$args = array(
			'do' => 'massmerge',
			'product' => urlencode($vbulletin->GPC['product']),
			'hash' => CP_SESSIONHASH,
			'redirect' => urlencode($vbulletin->GPC['redirect']),
			'startat' => $result
		);
		print_cp_redirect2('template', $args);
	}
}

// #############################################################################
// view the history of a template, including old versions and diffs between versions
if ($_REQUEST['do'] == 'history')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'title' => vB_Cleaner::TYPE_STR,
	));

	$revisions = vB_Api::instanceInternal('template')->history($vbulletin->GPC['title'], $vbulletin->GPC['dostyleid']);

	$history_count = 0;
	print_form_header('template', 'historysubmit');
	print_table_header(construct_phrase($vbphrase['history_of_template_x'], htmlspecialchars_uni($vbulletin->GPC['title'])), 7);
	print_cells_row(array(
		$vbphrase['delete'],
		$vbphrase['type_gstyle'],
		$vbphrase['version_gstyle'],
		$vbphrase['last_modified'],
		$vbphrase['view'],
		$vbphrase['old'],
		$vbphrase['new']
	), true, false, 1);

	$have_left_sel = false;
	$have_right_sel = false;

	foreach ($revisions AS $revision)
	{
		$left_sel = false;
		$right_sel = false;

		if ($revision['type'] == 'current')
		{
			// we are marking this entry (ignore all other entries)
			if ($revision['styleid'] == -1)
			{
				$type = $vbphrase['current_default'];
			}
			else
			{
				$type = $vbphrase['current_version'];
			}

			if ($have_right_sel)
			{
				$left_sel = ' checked="checked"';
				$have_left_sel = true;
			}
			else
			{
				$right_sel = ' checked="checked"';
				$have_right_sel = true;
				if (sizeof($revisions) == 1)
				{
					$left_sel = ' checked="checked"';
					$left_sel_sel = true;
				}
			}

			$id = $revision['templateid'];
			$deletebox = '&nbsp;';
		}
		else
		{
			if ($revision['styleid'] == '-1')
			{
				$type = $vbphrase['old_default'];
			}
			else
			{
				$type = $vbphrase['historical'];
			}

			$id = $revision['templatehistoryid'];
			$deletebox = '<input type="checkbox" name="delete[]" value="' . $id . '" />';
			$history_count ++;
		}

		if (!$revision['version'])
		{
			$revision['version'] = '<i>' . $vbphrase['unknown'] . '</i>';
		}

		$date = vbdate($vbulletin->options['dateformat'], $revision['dateline']);
		$time = vbdate($vbulletin->options['timeformat'], $revision['dateline']);
		$last_modified = "<i>$date $time</i> / <b>$revision[username]</b>";

		$view_link = construct_link_code($vbphrase['view'], "template.php?" . vB::getCurrentSession()->get('sessionurl') . "do=viewversion&amp;id=$id&amp;type=$revision[type]");

		$left = '<input type="radio" name="left_template" tabindex="1" value="' . "$id|$revision[type]" . "\"$left_sel />";
		$right = '<input type="radio" name="right_template" tabindex="1" value="' . "$id|$revision[type]" . "\"$right_sel />";

		if ($revision['comment'])
		{
			$comment = htmlspecialchars_uni($revision['comment']);

			$type = "<div title=\"$comment\">$type*</div>";
			$last_modified = "<div title=\"$comment\">$last_modified</div>";
			$revision['version'] = "<div title=\"$comment\">$revision[version]</div>";
			$view_link = "<div title=\"$comment\">$view_link</div>";
		}

		print_cells_row(array(
			$deletebox,
			$type,
			$revision['version'],
			$last_modified,
			$view_link,
			$left,
			$right
		), false, false, 1);
	}

	construct_hidden_code('wrap', 1);
	construct_hidden_code('inline', 1);
	construct_hidden_code('dostyleid', $vbulletin->GPC['dostyleid']);
	construct_hidden_code('title', $vbulletin->GPC['title']);

	print_description_row(
		'<span style="float:' . vB_Template_Runtime::fetchStyleVar('right') . '"><input type="submit" class="button" tabindex="1" name="docompare" value="' . $vbphrase['compare_versions_gstyle'] . '" /></span>' .
		($history_count ? '<input type="submit" class="button" tabindex="1" name="dodelete" value="' . $vbphrase['delete'] . '" />' : '&nbsp;'), false, 7, 'tfoot');
	print_table_footer();

	echo '<div align="center" class="smallfont" style="margin-top:4px;">' . $vbphrase['entry_has_a_comment'] . '</div>';
}

// #############################################################################
// generate a diff between two templates (current or historical versions)
if ($_REQUEST['do'] == 'viewversion')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'id'   => vB_Cleaner::TYPE_UINT,
		'type' => vB_Cleaner::TYPE_STR,
	));

	$template = vB_Api::instanceInternal('template')->fetchVersion($vbulletin->GPC['id'], $vbulletin->GPC['type']);

	if ($template['templateid'])
	{
		$type = ($template['styleid'] == -1 ? $vbphrase['current_default'] : $vbphrase['current_version']);
	}
	else
	{
		$type = ($template['styleid'] == -1 ? $vbphrase['old_default'] : $vbphrase['historical']);
	}
	$date = vbdate($vbulletin->options['dateformat'], $template['dateline']);
	$time = vbdate($vbulletin->options['timeformat'], $template['dateline']);
	$last_modified = "<i>$date $time</i> / <b>$template[username]</b>";

	print_form_header('', '');
	print_table_header(construct_phrase($vbphrase['viewing_version_of_x'], htmlspecialchars_uni($template['title'])));
	print_label_row($vbphrase['type_gstyle'], $type);
	print_label_row($vbphrase['last_modified'], $last_modified);
	if ($template['version'])
	{
		print_label_row($vbphrase['version_gstyle'], $template['version']);
	}
	if ($template['comment'])
	{
		print_label_row($vbphrase['comment_gstyle'], $template['comment']);
	}
	print_description_row('<textarea class="code" style="width:95%; height:500px">' . htmlspecialchars_uni($template['templatetext']) . '</textarea>', false, 2, '', 'center');
	print_table_footer();

}

// #############################################################################
// just a small action to figure out which submit button was pressed
if ($_POST['do'] == 'historysubmit')
{
	$vbulletin->input->clean_array_gpc('p', array('dodelete' => vB_Cleaner::TYPE_STR));

	if ($vbulletin->GPC['dodelete'])
	{
		$_POST['do'] = 'dodelete';
	}
	else
	{
		$_POST['do'] = 'docompare';
	}
}

// #############################################################################
// delete history points
if ($_POST['do'] == 'dodelete')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'delete'    => vB_Cleaner::TYPE_ARRAY_INT,
		'dostyleid' => vB_Cleaner::TYPE_INT,
		'title'     => vB_Cleaner::TYPE_STR,
	));

	if ($vbulletin->GPC['delete'])
	{
		vB_Api::instanceInternal('template')->deleteHistoryVersion($vbulletin->GPC['delete']);
	}

	print_stop_message2('template_history_entries_deleted', 'template',
		array('do' => 'history', 'dostyleid' => $vbulletin->GPC['dostyleid'], 'title' => urlencode($vbulletin->GPC['title']) ));
}



// #############################################################################
// generate a diff between two templates (current or historical versions)
if ($_POST['do'] == 'docompare')
{

	// Consolidating duplicate code used in this do branch into a function
	// not sure this is the right place for this.
	function docompare_print_control_form($inline, $wrap, $context_lines)
	{
		global $vbulletin, $vbphrase;

		static $form_count = 0;
		++$form_count;

		print_form_header('template', 'docompare', false, true, 'cpform' . $form_count, '90%', '', false, 'post', 0, true);
		print_table_header($vbphrase['display_options'], 1);
		?>
		<tr>
			<td colspan="4" class="tfoot" align="center">
				<input type="submit" name="switch_inline" class="submit" value="<?php echo ($inline ? $vbphrase['view_side_by_side'] : $vbphrase['view_inline']); ?>" accesskey="r" />
				<input type="submit" name="switch_wrapping" class="submit" value="<?php echo ($wrap ? $vbphrase['disable_wrapping'] : $vbphrase['enable_wrapping']); ?>" accesskey="s" />
		<?php
		if ($inline)
		{
		?>
				&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="text" name="context_lines" value="<?php echo $context_lines; ?>" size="2" class="ctrl_context_lines" dir="<?php echo vB_Template_Runtime::fetchStyleVar('textdirection'); ?>" accesskey="t" />
				<strong><?php echo $vbphrase['lines_around_each_diff']; ?></strong>
				&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="submit" name="submit_diff" class="submit" value="<?php echo $vbphrase['update'] ?>" accesskey="u" />
		<?php
		}
		?>
			</td>
		</tr>
		<?php

		construct_hidden_code('left_template', $vbulletin->GPC['left_template']);
		construct_hidden_code('right_template', $vbulletin->GPC['right_template']);
		construct_hidden_code('do_compare_text', $vbulletin->GPC['do_compare_text']);
		construct_hidden_code('left_template_text', $vbulletin->GPC['left_template_text']);
		construct_hidden_code('right_template_text', $vbulletin->GPC['right_template_text']);
		construct_hidden_code('wrap', $wrap);
		construct_hidden_code('inline', $inline);

		print_table_footer(1);
	}


	$vbulletin->input->clean_array_gpc('p', array(
		'left_template'       => vB_Cleaner::TYPE_STR,
		'right_template'      => vB_Cleaner::TYPE_STR,
		'switch_wrapping'     => vB_Cleaner::TYPE_NOHTML,
		'switch_inline'       => vB_Cleaner::TYPE_NOHTML,
		'wrap'                => vB_Cleaner::TYPE_BOOL,
		'inline'              => vB_Cleaner::TYPE_BOOL,
		'context_lines'       => vB_Cleaner::TYPE_UINT,
		'do_compare_text'     => vB_Cleaner::TYPE_BOOL,
		'left_template_text'  => vB_Cleaner::TYPE_STR,
		'right_template_text' => vB_Cleaner::TYPE_STR,
		'template_name'       => vB_Cleaner::TYPE_NOHTML,
	));

	$wrap = ($vbulletin->GPC_exists['switch_wrapping'] ? !$vbulletin->GPC['wrap'] : $vbulletin->GPC['wrap']);
	$inline = ($vbulletin->GPC_exists['switch_inline'] ? !$vbulletin->GPC['inline'] : $vbulletin->GPC['inline']);
	$context_lines = ($vbulletin->GPC_exists['context_lines'] ? $vbulletin->GPC['context_lines'] : 3);

	if ($vbulletin->GPC['do_compare_text'])
	{
		// Compare posted text instead of comparing templates saved in the database
		$left_template = array(
			'templatetext' => $vbulletin->GPC['left_template_text'],
			'title'        => $vbulletin->GPC['template_name'],
		);
		$right_template = array(
			'templatetext' => $vbulletin->GPC['right_template_text'],
		);
	}
	else
	{
		list($left_id, $left_type) = explode('|', $vbulletin->GPC['left_template']);
		list($right_id, $right_type) = explode('|', $vbulletin->GPC['right_template']);

		$left_template = fetch_template_current_historical($left_id, $left_type);
		$right_template = fetch_template_current_historical($right_id, $right_type);
	}

	if (!$left_template OR !$right_template)
	{
		exit;
	}

	require_once(DIR . '/includes/class_diff.php');

	$diff = new vB_Text_Diff($left_template['templatetext'], $right_template['templatetext']);
	$entries =& $diff->fetch_diff();


	docompare_print_control_form($inline, $wrap, $context_lines);


	print_table_start(true, '90%', '', '', true);
	print_table_header(construct_phrase($vbphrase['comparing_versions_of_x'], htmlspecialchars_uni($left_template['title'])), 4);

	if (!$inline)
	{
		// side by side
		print_cells_row(array(
			$vbphrase['old_version'],
			$vbphrase['new_version']
		), true, false, 1);

		foreach ($entries AS $diff_entry)
		{
			// possible classes: unchanged, notext, deleted, added, changed
			echo "<tr>\n\t";
			echo '<td width="50%" valign="top" class="diff-' . $diff_entry->fetch_data_old_class() . '" dir="ltr">';

			foreach ($diff_entry->fetch_data_old() AS $content)
			{
				echo $diff_entry->prep_diff_text($content, $wrap) . "<br />\n";
			}

			echo '</td><td width="50%" valign="top" class="diff-' . $diff_entry->fetch_data_new_class() . '" dir="ltr">';

			foreach ($diff_entry->fetch_data_new() AS $content)
			{
				echo $diff_entry->prep_diff_text($content, $wrap) . "\n";
			}

			echo "</td></tr>\n\n";
		}
	}
	else
	{
		// inline
		echo "	<tr valign=\"top\" align=\"center\">
					<td class=\"thead\">$vbphrase[old]</td>
					<td class=\"thead\">$vbphrase[new]</td>
					<td class=\"thead\" width=\"100%\">$vbphrase[content]</td>
				</tr>";

		$wrap_buffer = array();
		$first_diff = true;

		foreach ($entries AS $diff_entry)
		{
			if ('unchanged' == $diff_entry->old_class)
			{
				$old_data = $diff_entry->fetch_data_old();
				$new_data_keys = array_keys($diff_entry->fetch_data_new());

				if (sizeof($entries) <= 1)
				{
					$context_lines = sizeof($old_data);
				}

				if (!$context_lines)
				{
					continue;
				}

				// add unchanged lines to wrap buffer
				foreach ($diff_entry->fetch_data_old() AS $lineno => $content)
				{
					$wrap_buffer[] = array('oldline' => $lineno, 'newline' => array_shift($new_data_keys), 'content' => $content);
				}

				continue;
			}
			else if(sizeof($wrap_buffer))
			{
				if (sizeof($wrap_buffer) > $context_lines)
				{
					if (!$first_diff)
					{
						$buffer = array_slice($wrap_buffer, 0, $context_lines);
						$buffer[] = array('oldline' => '', 'newline' => '', 'content' => '<hr />');
						$wrap_buffer = array_merge($buffer, array_slice($wrap_buffer, -$context_lines));
					}
					else
					{
						$wrap_buffer = array_slice($wrap_buffer, -$context_lines);
						$first_diff = false;
					}
				}

				foreach ($wrap_buffer AS $wrap_line)
				{
					if (!$wrap_line['oldline'] AND !$wrap_line['newline'])
					{
						echo '<tr><td class="diff-linenumber">...</td><td class="diff-linenumber">...</td>';
						echo '<td colspan="2" class="diff-unchanged diff-inline-break"></td></tr>';
					}
					else
					{
						echo "<tr>\n\t<td class=\"diff-linenumber\">$wrap_line[oldline]</td><td class=\"diff-linenumber\">$wrap_line[newline]</td>";
						echo '<td colspan="2" valign="top" class="diff-unchanged" dir="ltr">';
						echo $diff_entry->prep_diff_text($wrap_line['content'], $wrap);
						echo "</td></tr>\n\n";
					}
				}

				$wrap_buffer = array();
			}

			$data_old = $diff_entry->fetch_data_old();
			$data_new = $diff_entry->fetch_data_new();
			$data_old_len = sizeof($data_old);
			$data_new_len = sizeof($data_new);

			$first = true;
			$current = 1;

			foreach ($data_old AS $lineno => $content)
			{
				$class = 'diff-deleted';

				// only top border the first line
				$class .= ($first ? ' diff-inline-deleted-start' : '');

				// only bottom border the last line if it is not followed by a new diff
				$class .= ($current >= $data_old_len ? ($data_new_len ? '' : ' diff-inline-deleted-end') : '');

				echo "<tr>\n\t<td class=\"diff-linenumber\">$lineno</td><td class=\"diff-linenumber\">&nbsp;</td>";
				echo '<td colspan="" valign="top" class="' . $class . '" dir="ltr">';
				echo $diff_entry->prep_diff_text($content, $wrap);
				echo "</td></tr>\n\n";

				$first = false;
				$current++;
			}

			$first = true;
			$current = 1;

			foreach ($data_new AS $lineno => $content)
			{
				$class = 'diff-inline-added';

				// only top border the first line if it doesn't consecutively follow an old diff comparison
				$class .= ($first ? ($data_old_len ? '' : ' diff-inline-added-start') : '');

				// only bottom border the last line
				$class .= ($current >= $data_new_len ? ' diff-inline-added-end' : '');

				echo "<tr>\n\t<td class=\"diff-linenumber\">&nbsp;</td><td class=\"diff-linenumber\">$lineno</td>";
				echo '<td colspan="" valign="top" class="' . $class . '" dir="ltr">';
				echo $diff_entry->prep_diff_text($content, $wrap);
				echo "</td></tr>\n\n";

				$first = false;
				$current++;
			}
		}

		// If any buffer remains display the first two lines
		if (sizeof($wrap_buffer))
		{
			$i = 0;
			while ($i < $context_lines AND ($wrap_line = array_shift($wrap_buffer)))
			{
				echo "<tr>\n\t<td class=\"diff-linenumber\">$wrap_line[oldline]</td><td class=\"diff-linenumber\">$wrap_line[newline]</td>";
				echo '<td colspan="2" valign="top" class="diff-unchanged" dir="ltr">';
				echo $diff_entry->prep_diff_text($wrap_line['content'], $wrap);
				echo "</td></tr>\n\n";

				$i++;
			}
		}
		unset($wrap_buffer);
	}

	print_table_footer();

	echo '<br />';
	docompare_print_control_form($inline, $wrap, $context_lines);


	print_form_header('', '');
	print_table_header($vbphrase['comparison_key']);

	if ($inline)
	{
		echo "<tr><td class=\"diff-deleted diff-inline-deleted-end\" align=\"center\">$vbphrase[text_in_old_version]</td></tr>\n";
		echo "<tr><td class=\"diff-added diff-inline-added-end\" align=\"center\">$vbphrase[text_in_new_version]</td></tr>\n";
		echo "<tr><td class=\"diff-unchanged\" align=\"center\">$vbphrase[text_surrounding_changes]</td></tr>\n";
	}
	else
	{
		echo "<tr><td class=\"diff-deleted\" align=\"center\" width=\"50%\">$vbphrase[text_removed_from_old_version]</td><td class=\"diff-notext\">&nbsp;</td></tr>\n";
		echo "<tr><td class=\"diff-changed\" colspan=\"2\" align=\"center\">$vbphrase[text_changed_between_versions]</td></tr>\n";
		echo "<tr><td class=\"diff-notext\" width=\"50%\">&nbsp;</td><td class=\"diff-added\" align=\"center\">$vbphrase[text_added_in_new_version]</td></tr>\n";
	}

	print_table_footer();
}

// #############################################################################
// generate a diff between two templates (current or historical versions)
if ($_REQUEST['do'] == 'docompare3')
{

	/*
		Copied from vB_Text_Diff_Entry::prep_diff_text
		I don't want to put html formatting code in the merge class, but I'm not
		sure this really belongs here either.
	*/

	function docompare3_print_control_form($inline, $wrap)
	{
		global $vbphrase, $vbulletin;

		$editlink = '?do=edit&amp;templateid=' . $vbulletin->GPC['templateid'] .
			'&amp;group=&amp;searchstring=&amp;expandset=5&amp;showmerge=1';

		print_form_header('template', 'docompare3', false, true, 'cpform', '90%', '', false);
		construct_hidden_code('templateid', $vbulletin->GPC['templateid']);
		construct_hidden_code('wrap', $wrap);
		construct_hidden_code('inline', $inline);

		print_table_header($vbphrase['display_options']);
		print_table_footer(2,
			'<div style="float:' . vB_Template_Runtime::fetchStyleVar('right') . '"><a href="' . $editlink . '" style="font-weight: bold">' . $vbphrase['merge_edit_link'] . '</a></div>
			<div align="' . vB_Template_Runtime::fetchStyleVar('left') . '"><input type="submit" name="switch_inline" class="submit" value="' . ($inline ? $vbphrase['view_side_by_side'] : $vbphrase['view_inline']) . '" accesskey="r" />
			<input type="submit" name="switch_wrapping" class="submit" value="' . ($wrap ? $vbphrase['disable_wrapping'] : $vbphrase['enable_wrapping']) . '" accesskey="s" /></div>'
		);
	}

	//get values
	$vbulletin->input->clean_array_gpc('r', array(
		'templateid'      => vB_Cleaner::TYPE_STR,
		'switch_wrapping' => vB_Cleaner::TYPE_NOHTML,
		'switch_inline'   => vB_Cleaner::TYPE_NOHTML,
		'inline'          => vB_Cleaner::TYPE_BOOL,
		'wrap'            => vB_Cleaner::TYPE_BOOL,
	));

	if ($vbulletin->GPC_exists['wrap'])
	{
		$wrap = ($vbulletin->GPC_exists['switch_wrapping'] ? !$vbulletin->GPC['wrap'] : $vbulletin->GPC['wrap']);
	}
	else
	{
		$wrap = true;
	}

	if ($vbulletin->GPC_exists['inline'])
	{
		$inline = ($vbulletin->GPC_exists['switch_inline'] ? !$vbulletin->GPC['inline'] : $vbulletin->GPC['inline']);
	}
	else
	{
		$inline = true;
	}

	$templateid = $vbulletin->GPC['templateid'];

	//find templates
	try
	{
		$templates = fetch_templates_for_merge($templateid);
		$new = $templates["new"];
		$custom = $templates["custom"];
		$origin = $templates["origin"];
	}
	catch (Exception $e)
	{
		print_cp_message($e->getMessage());
	}

	require_once (DIR . '/includes/class_merge.php');
	// Output progress to browser #34585
	$merge = new vB_Text_Merge_Threeway($origin['template_un'], $new['template_un'], $custom['template_un'], true);
	$chunks = $merge->get_chunks();

	docompare3_print_control_form($inline, $wrap);

	print_table_start(true, '90%', 0, ($inline ? 'compare_inline' : 'compare_side'));
	print_table_header(
		construct_phrase($vbphrase['comparing_versions_of_x'], htmlspecialchars_uni($custom['title'])),
		$inline ? 1 : 3
	);

	if ($inline)
	{
		foreach ($chunks as $chunk)
		{
			if ($chunk->is_stable())
			{
				$formatted_text = format_diff_text($chunk->get_text_original(), $wrap);
				$class = "merge-nochange";
			}
			else
			{
				$text = $chunk->get_merged_text();
				if ($text === false)
				{
					$formatted_text = format_conflict_text(
						$chunk->get_text_right(), $chunk->get_text_original(), $chunk->get_text_left(),
						$origin['version'], $new['version'], true, $wrap
					);
					$class = "merge-conflict";
				}
				else
				{
					$formatted_text = format_diff_text($text, $wrap);
					$class = "merge-successful";
				}
			}
			echo "<tr>\n\t";
			echo "<td width='100%' valign='top' class='$class' dir='ltr'>\n";
			echo $formatted_text;
			echo "\n</td>\n</tr>\n\n";
		}
	}
	else
	{
		$cells = array(
			$vbphrase['your_customized_template'],
			$vbphrase['merged_template_conflicts_show_original'],
			$vbphrase['new_default_template']
		);
		print_cells_row($cells, true, false, 1);

		foreach ($chunks as $chunk)
		{
			if ($chunk->is_stable())
			{
				$col1 = $chunk->get_text_original();
				$col2 = $col1;
				$col3 = $col1;
				$class = "merge-nochange";
			}
			else
			{
				$col1 = $chunk->get_text_right();
				$col2 = $chunk->get_merged_text();
				if ($col2 === false) {
					$class = "merge-conflict";
					$col2 = $chunk->get_text_original();
				}
				else
				{
					$class = "merge-successful";
				}

				$col3 = $chunk->get_text_left();
			}

			// possible classes: unchanged, notext, deleted, added, changed
			echo "<tr>\n\t";
			echo '<td width="33%" valign="top" class="' . $class . '" dir="ltr">';
			echo	format_diff_text($col1, $wrap);
			echo '</td><td width="34%" valign="top" class="' . $class . '" dir="ltr">';
			echo	format_diff_text($col2, $wrap);
			echo '</td><td width="33%" valign="top" class="' . $class . '" dir="ltr">';
			echo	format_diff_text($col3, $wrap);
			echo "</td></tr>\n\n";
		}
	}
	print_table_footer();

	echo '<br />';
	docompare3_print_control_form($inline, $wrap);

	print_form_header('', '');
	print_table_header($vbphrase['comparison_key']);

	$conflictkey = "";
	echo '<tr><td class="merge-conflict" align="center">' . $vbphrase['merge_key_conflict'] .
		$conflictkey . "</td></tr>\n";
	echo '<tr><td class="merge-successful" align="center">' . $vbphrase['merge_key_merged'] . "</td></tr>\n";
	echo '<tr><td class="merge-nochange" align="center">' . $vbphrase['merge_key_none'] . "</td></tr>\n";

	print_table_footer();
}



function updatetemplate_print_error_page($template_un, $error)
{
	global $vbulletin, $vbphrase;
	print_form_header('template', 'updatetemplate', 0, 1, '', '75%');
	construct_hidden_code('confirmerrors', 1);
	construct_hidden_code('title', $vbulletin->GPC['title']);
	construct_hidden_code('template', $template_un);
	construct_hidden_code('templateid', $vbulletin->GPC['templateid']);
	construct_hidden_code('group', $vbulletin->GPC['group']);
	construct_hidden_code('searchstring', $vbulletin->GPC['searchstring']);
	construct_hidden_code('dostyleid', $vbulletin->GPC['dostyleid']);
	construct_hidden_code('product', $vbulletin->GPC['product']);
	construct_hidden_code('savehistory', intval($vbulletin->GPC['savehistory']));
	construct_hidden_code('histcomment', $vbulletin->GPC['histcomment']);
	print_table_header($vbphrase['vbulletin_message']);
	print_description_row($error);
	print_submit_row($vbphrase['continue'], 0, 2, $vbphrase['go_back']);
	print_cp_footer();
}


function print_template_confirm_error_page($params, $error)
{
	global $vbphrase;
	print_form_header('template', 'updatetemplate', 0, 1, '', '75%');
	construct_hidden_code('confirmerrors', 1);

	$persist = array('title', 'template', 'templateid', 'group', 'searchstring', 'dostyleid',
		'product', 'savehistory', 'histcomment');
	foreach ($persist AS $varname)
	{
		construct_hidden_code($varname, $params[$varname]);
	}

	print_table_header($vbphrase['vbulletin_message']);
	print_description_row($error);
	print_submit_row($vbphrase['continue'], 0, 2, $vbphrase['go_back']);
	print_cp_footer();
}


// #############################################################################
// insert queries and cache rebuilt for template insertion
if ($_POST['do'] == 'inserttemplate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'title'          => vB_Cleaner::TYPE_STR,
		'product'        => vB_Cleaner::TYPE_STR,
		'template'       => vB_Cleaner::TYPE_NOTRIM,
		'searchstring'   => vB_Cleaner::TYPE_STR,
		'expandset'      => vB_Cleaner::TYPE_NOHTML,
		'searchset'      => vB_Cleaner::TYPE_NOHTML,
		'savehistory'    => vB_Cleaner::TYPE_BOOL,
		'histcomment'    => vB_Cleaner::TYPE_STR,
		'return'         => vB_Cleaner::TYPE_STR,
		'group'          => vB_Cleaner::TYPE_STR,
		'confirmremoval' => vB_Cleaner::TYPE_BOOL,
		'confirmerrors'  => vB_Cleaner::TYPE_BOOL,
	));


	// remove escaped CDATA (just in case user is pasting template direct from an XML editor
	// where the CDATA tags will have been escaped by our escaper...
	//$template = xml_unescape_cdata($template);

	if (!$vbulletin->GPC['title'])
	{
		print_stop_message2('please_complete_required_fields');
	}

	//todo move these checks into the the api call.
	if ($vbulletin->GPC['title'] == 'footer' AND !$vbulletin->GPC['confirmremoval'])
	{
		if (strpos($vbulletin->GPC['template'], '{vb:rawphrase powered_by_vbulletin}') === false)
		{
			print_form_header('template', 'inserttemplate', 0, 1, '', '75%');
			construct_hidden_code('confirmremoval', 1);
			construct_hidden_code('title', $vbulletin->GPC['title']);
			construct_hidden_code('template', $vbulletin->GPC['template']);
			construct_hidden_code('group', $vbulletin->GPC['group']);
			construct_hidden_code('searchstring', $vbulletin->GPC['searchstring']);
			construct_hidden_code('dostyleid', $vbulletin->GPC['dostyleid']);
			construct_hidden_code('savehistory', intval($vbulletin->GPC['savehistory']));
			construct_hidden_code('histcomment', $vbulletin->GPC['histcomment']);
			construct_hidden_code('product', $vbulletin->GPC['product']);
			print_table_header($vbphrase['confirm_removal_of_copyright_notice']);
			print_description_row($vbphrase['it_appears_you_are_removing_vbulletin_copyright']);
			print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);
			print_cp_footer();
			exit;
		}
	}

	$templateid = vB_Api::instance('template')->insert($vbulletin->GPC['dostyleid'], $vbulletin->GPC['title'], $vbulletin->GPC['template'],
		$vbulletin->GPC['product'], $vbulletin->GPC['savehistory'], $vbulletin->GPC['histcomment'],  $vbulletin->GPC['confirmerrors']);

	//we can't easily display multiple errors in the admincp code, so display the first one.
	if (is_array($templateid) AND isset($templateid['errors'][0]))
	{
		$error = $templateid['errors'][0];

		if ($error[0] == 'template_eval_error' OR $error[0] == 'template_compile_error')
		{
			print_template_confirm_error_page($vbulletin->GPC, construct_phrase($vbphrase['template_eval_error'], fetch_error_array($error[1])));
		}
		else
		{
			print_stop_message2($templateid['errors'][0]);
		}
	}

	//if no error, then redirect the page
	$args = array();
	parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
	$args['searchset'] = $vbulletin->GPC['searchset'];
	$args['group'] = $vbulletin->GPC['group'];
	$args['templateid'] = $templateid;
	$args['searchstring'] = urlencode($vbulletin->GPC['searchstring']);

	if ($vbulletin->GPC['return'])
	{
		$args['do'] = 'edit';
		$args['expandset'] = $vbulletin->GPC['expandset'];
	}
	else
	{
		$args['do'] = 'modify';
		$args['expandset'] = $vbulletin->GPC['dostyleid'];
	}

	print_cp_redirect2('template', $args, 1);
}

// #############################################################################
// add a new template form
if ($_REQUEST['do'] == 'add')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'title'        => vB_Cleaner::TYPE_STR,
		'group'        => vB_Cleaner::TYPE_STR,
		'searchstring' => vB_Cleaner::TYPE_STR,
		'expandset'    => vB_Cleaner::TYPE_STR,
	));

	if ($vbulletin->GPC['dostyleid'] == -1)
	{
		$style['title'] = $vbphrase['global_templates'];
	}
	else
	{
		$style = vB_Library::instance('Style')->fetchStyleByID($vbulletin->GPC['dostyleid']);
	}

	if ($vbulletin->GPC['title'])
	{
		$templateinfo = vB::getDbAssertor()->getRow(
					'template',
					array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						vB_dB_Query::CONDITIONS_KEY => array(
							array('field' => 'title', 'value' => $vbulletin->GPC['title'], 'operator' => vB_dB_Query::OPERATOR_EQ),
							array('field' => 'styleid', 'value' => array(-1,0), 'operator' => vB_dB_Query::OPERATOR_EQ)
						)
					)
		);
	}
	else if ($vbulletin->GPC['templateid'])
	{
		$templateinfo = vB_Api::instanceInternal('template')->fetchByID($vbulletin->GPC['templateid']);
		$vbulletin->GPC['title'] = $templateinfo['title'];
	}

	print_form_header('template', 'inserttemplate');
	print_table_header(iif($vbulletin->GPC['title'],
		construct_phrase($vbphrase['customize_template_x'], $vbulletin->GPC['title']),
		$vbphrase['add_new_template']
	));

	construct_hidden_code('group', $vbulletin->GPC['group']);

	$products = fetch_product_list();

	if ($vbulletin->GPC['title'])
	{
		construct_hidden_code('product', $templateinfo['product']);
		print_label_row($vbphrase['product'], $products["$templateinfo[product]"]);
	}
	else if ($vb5_config['Misc']['debug'])
	{
		print_select_row($vbphrase['product'], 'product', $products, $templateinfo['product']);
	}
	else
	{ // use the default as we dictate in inserttemplate, if they dont have debug mode on they can't add templates to -1 anyway
		construct_hidden_code('product', 'vbulletin');
	}

	//if ($vbulletin->GPC['dostyleid'] > 0)
	//{
	$history = vB_Api::instanceInternal('template')->history($vbulletin->GPC['title'], $vbulletin->GPC['dostyleid']);
	//}
	//else
	//{
	//	$history = null;
	//}

	construct_hidden_code('expandset', $vbulletin->GPC['expandset']);
	construct_hidden_code('searchset', $vbulletin->GPC['expandset']);
	construct_hidden_code('searchstring', $vbulletin->GPC['searchstring']);
	print_style_chooser_row('dostyleid', $vbulletin->GPC['dostyleid'], $vbphrase['master_style'], $vbphrase['style'], iif($vb5_config['Misc']['debug'] == 1, 1, 0));
	print_input_row(
		$vbphrase['title'] .
			($history ?
				'<dfn>' .
				construct_link_code($vbphrase['view_history_gstyle'], 'template.php?do=history&amp;dostyleid=' . $vbulletin->GPC['dostyleid'] . '&amp;title=' . urlencode($vbulletin->GPC['title']), 1) .
				'</dfn>'
			: ''),
		'title',
		$vbulletin->GPC['title']);
	print_textarea_row($vbphrase['template'] . '
			<br /><br />
			<span class="smallfont">' .
			iif($vbulletin->GPC['title'], construct_link_code($vbphrase['show_default'], "template.php?" . vB::getCurrentSession()->get('sessionurl') . "do=view&amp;title=" . $vbulletin->GPC['title'], 1) . '<br /><br />', '') .
			'<!--' . $vbphrase['wrap_text'] . '<input type="checkbox" unselectable="on" onclick="set_wordwrap(\'ta_template\', this.checked);" accesskey="w" checked="checked" />-->
			</span>',
		'template', $templateinfo['template_un'], 22, '5000" style="width:99%', true, true, 'ltr', 'code');
	print_template_javascript();
	print_label_row($vbphrase['save_in_template_history'], '<label for="savehistory"><input type="checkbox" name="savehistory" id="savehistory" value="1" tabindex="1" />' . $vbphrase['yes'] . '</label><br /><span class="smallfont">' . $vbphrase['comment_gstyle'] . '</span> <input type="text" name="histcomment" value="" tabindex="1" class="bginput" size="50" />');
	print_submit_row($vbphrase['save'], '_default_', 2, '', "<input type=\"submit\" class=\"button\" tabindex=\"1\" name=\"return\" value=\"$vbphrase[save_and_reload]\" accesskey=\"e\" />");
	?>
	<script type="text/javascript">
	<!--
	var initial_crc32 = crc32(YAHOO.util.Dom.get(textarea_id).value);
	var confirmUnload = true;
	YAHOO.util.Event.addListener('cpform', 'submit', function(e) { confirmUnload = false; });
	YAHOO.util.Event.addListener(window, 'beforeunload', function(e) {
		if (initial_crc32 != crc32(YAHOO.util.Dom.get(textarea_id).value) && confirmUnload) {
			e.returnValue = '<?php echo addslashes_js($vbphrase[unsaved_data_may_be_lost]); ?>';
		}
	});
	//-->
	</script>
	<?php
}

// #############################################################################
// simple update query for an existing template
$updatetemplate_edit_conflict = false;
if ($_POST['do'] == 'updatetemplate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'title'            => vB_Cleaner::TYPE_STR,
		'oldtitle'         => vB_Cleaner::TYPE_STR,
		'template'         => vB_Cleaner::TYPE_NOTRIM,
		'group'            => vB_Cleaner::TYPE_STR,
		'product'          => vB_Cleaner::TYPE_STR,
		'savehistory'      => vB_Cleaner::TYPE_BOOL,
		'histcomment'      => vB_Cleaner::TYPE_STR,
		'string'           => vB_Cleaner::TYPE_STR,
		'searchstring'     => vB_Cleaner::TYPE_STR,
		'expandset'        => vB_Cleaner::TYPE_NOHTML,
		'searchset'        => vB_Cleaner::TYPE_NOHTML,
		'return'           => vB_Cleaner::TYPE_STR,
		'confirmerrors'    => vB_Cleaner::TYPE_BOOL,
		'lastedit'         => vB_Cleaner::TYPE_UINT,
		'hash'             => vB_Cleaner::TYPE_STR,
		'fromeditconflict' => vB_Cleaner::TYPE_BOOL,
	));

	try
	{
		vB_Api::instanceInternal('template')->update(
			$vbulletin->GPC['templateid'],
			$vbulletin->GPC['title'],
			$vbulletin->GPC['template'],
			$vbulletin->GPC['product'],
			false,
			$vbulletin->GPC['savehistory'],
			$vbulletin->GPC['histcomment'],
			!empty($vbulletin->GPC['confirmerrors'])
		);

	}
	catch (vB_Exception_Api $e)
	{
		$errors = $e->get_errors();
		$error = $errors[0];

		if ($error == 'edit_conflict')
		{
			$updatetemplate_edit_conflict = true;
		}
		else if ($error[0] == 'template_eval_error' OR $error[0] == 'template_compile_error')
		{
			updatetemplate_print_error_page($vbulletin->GPC['template'], construct_phrase($vbphrase['template_eval_error'], fetch_error_array($error[1])));
			exit;
		}
		else
		{
			print_stop_message2($error[0]);
		}
	}

	$args = array(
		'templateid'   => $vbulletin->GPC['templateid'],
		'group'        => $vbulletin->GPC['group'],
		'expandset'    => $vbulletin->GPC['expandset'],
		'searchset'    => $vbulletin->GPC['searchset'],
		'searchstring' => urlencode($vbulletin->GPC['searchstring'])
	);

	if ($vbulletin->GPC['return'])
	{
		$args['do'] = 'edit';
		$args['template'] = $vbulletin->GPC['template'];

	}
	else
	{
		$args['do'] = 'modify';
	}

	if ($vbulletin->GPC['title'] == $vbulletin->GPC['oldtitle'])
	{
		if ($vbulletin->GPC['return'])
		{
			print_cp_redirect2('template', $args);
		}
		else
		{
			$_REQUEST['do'] = 'modify';
			$vbulletin->GPC['expandset'] = $vbulletin->GPC['dostyleid'];
		}

		//$vbulletin->GPC['searchstring'] = $string ? $string : $vbulletin->GPC['searchstring'];
		$vbulletin->GPC['searchset'] = $vbulletin->GPC['dostyleid'];
	}
	else
	{
		print_rebuild_style($vbulletin->GPC['dostyleid'], '', 0, 0, 0, 0);
		print_cp_redirect2('template', $args, 1);
	}
}

// #############################################################################
// edit form for an existing template
if ($_REQUEST['do'] == 'edit')
{

	function edit_get_merged_text($templateid)
	{
		global $vbphrase;

		$templates = fetch_templates_for_merge($templateid);
		$new = $templates["new"];
		$custom = $templates["custom"];
		$origin = $templates["origin"];

		require_once (DIR . '/includes/class_merge.php');
		$merge = new vB_Text_Merge_Threeway($origin['template_un'], $new['template_un'], $custom['template_un']);
		$chunks = $merge->get_chunks();

		$text = "";
		foreach ($chunks as $chunk)
		{
			if ($chunk->is_stable())
			{
				$text .= $chunk->get_text_original();
			}
			else
			{
				$chunk_text = $chunk->get_merged_text();
				if ($chunk_text === false)
				{
						$new_title = construct_phrase($vbphrase['merge_title_new'], $new['version']);
						$chunk_text = format_conflict_text($chunk->get_text_right(), $chunk->get_text_original(),
							 $chunk->get_text_left(), $origin['version'], $new['version']);
				}
				$text .= $chunk_text;
			}
		}
		return $text;
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'group'            => vB_Cleaner::TYPE_STR,
		'searchstring'     => vB_Cleaner::TYPE_STR,
		'expandset'        => vB_Cleaner::TYPE_STR,
		'showmerge'        => vB_Cleaner::TYPE_BOOL,
	));

	$template = vB::getDbAssertor()->getRow(
					'fetchTemplateWithStyle',
					array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
						'templateid' => $vbulletin->GPC['templateid']
					)
		);

	if ($template['styleid'] == -1)
	{
		$template['style'] = $vbphrase['master_style'];
	}

	if ($vbulletin->GPC['showmerge'])
	{
		try
		{
			$text = edit_get_merged_text($vbulletin->GPC['templateid']);
		}
		catch (Exception $e)
		{
			print_cp_message($e->getMessage());
		}

		print_table_start();
		print_description_row(
			construct_phrase($vbphrase['edting_merged_version_view_highlighted'], "template.php?do=docompare3&amp;templateid=$template[templateid]")
		);
		print_table_footer();
	}
	else
	{
		if ($template['mergestatus'] == 'conflicted')
		{
			print_table_start();
			print_description_row(
				construct_phrase($vbphrase['default_version_newer_merging_failed'],
					"template.php?do=docompare3&amp;templateid=$template[templateid]",
					$vbulletin->scriptpath . '&amp;showmerge=1'
				)
			);
			print_table_footer();
		}
		else if ($template['mergestatus'] == 'merged')
		{
			$merge_info = vB::getDbAssertor()->getRow(
					'templatemerge',
					array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						'templateid' => $template[templateid]
					)
			);

			print_table_start();
			print_description_row(
				construct_phrase($vbphrase['changes_made_default_merged_customized'],
					"template.php?do=docompare3&amp;templateid=$template[templateid]",
					"template.php?do=viewversion&amp;id=$merge_info[savedtemplateid]&amp;type=historical"
				)
			);
			print_table_footer();
		}

		$text = $template['template_un'];
	}

	if ($updatetemplate_edit_conflict)
	{
		if ($vbulletin->GPC['fromeditconflict'])
		{
			print_warning_table($vbphrase['template_was_changed_again']);
		}

		// An edit conflict was detected in do=updatetemplate
		print_form_header('template', 'docompare', false, true, 'editconfcompform', '90%', '_new');
		print_table_header($vbphrase['edit_conflict']);
		print_description_row($vbphrase['template_was_changed']);
		construct_hidden_code('left_template_text', $vbulletin->GPC['template']);
		construct_hidden_code('right_template_text', $text);
		construct_hidden_code('do_compare_text', 1);
		construct_hidden_code('template_name', urlencode($template['title']));
		construct_hidden_code('inline', 1);
		construct_hidden_code('wrap', 0);
		print_submit_row($vbphrase['view_comparison_your_version_current_version'], false);
	}

	print_form_header('template', 'updatetemplate');
	print_column_style_code(array('width:20%', 'width:80%'));
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['template'], $template['title'], $template['templateid']));
	construct_hidden_code('templateid', $template['templateid']);
	construct_hidden_code('group', $vbulletin->GPC['group']);
	construct_hidden_code('searchstring', $vbulletin->GPC['searchstring']);
	construct_hidden_code('dostyleid', $template['styleid']);
	construct_hidden_code('expandset', $vbulletin->GPC['expandset']);
	construct_hidden_code('oldtitle', $template['title']);
	construct_hidden_code('lastedit', $template['dateline']);
	construct_hidden_code('hash', htmlspecialchars($template['hash']));

	if ($updatetemplate_edit_conflict)
	{
		construct_hidden_code('fromeditconflict', 1);
	}

	$products = fetch_product_list();

	if ($template['styleid'] == -1)
	{
		print_select_row($vbphrase['product'], 'product', $products, $template['product']);
	}
	else
	{
		print_label_row($vbphrase['product'], $products[($template['product'] ? $template['product'] : 'vbulletin')]);
		construct_hidden_code('product', ($template['product'] ? $template['product'] : 'vbulletin'));
	}

	$backlink = "template.php?" . vB::getCurrentSession()->get('sessionurl') .
		"do=modify&amp;expandset=$template[styleid]&amp;group=" . $vbulletin->GPC['group'] .
		"&amp;templateid=" . $vbulletin->GPC['templateid'] . "&amp;searchstring=" .
		urlencode($vbulletin->GPC['searchstring']);
	print_label_row($vbphrase['style'], "<a href=\"$backlink\" title=\"" . $vbphrase['edit_templates'] . "\"><b>$template[style]</b></a>");
	print_input_row(
		$vbphrase['title'] . /*($template['styleid'] != -1 ?*/ '<dfn>' .
			construct_link_code($vbphrase['view_history_gstyle'], 'template.php?do=history&amp;dostyleid=' .
				$template['styleid'] . '&amp;title=' . urlencode($template['title']), 1) .
			'</dfn>' /*: '')*/,
		'title',
		$template['title']
	);

	if ($updatetemplate_edit_conflict)
	{
		print_description_row($vbphrase['template_current_version_merge_here'], false, 2, 'tfoot', 'center');
	}

	print_textarea_row($vbphrase['template'] . '
			<br /><br />
			<span class="smallfont">' .
			iif($template['styleid'] != -1, construct_link_code($vbphrase['show_default'], "template.php?" . vB::getCurrentSession()->get('sessionurl') . "do=view&amp;title=$template[title]", 1) . '<br /><br />', '') .
			'<!--' . $vbphrase['wrap_text'] . '<input type="checkbox" unselectable="on" onclick="set_wordwrap(\'ta_template\', this.checked);" accesskey="w" checked="checked" />-->
			</span>',
		'template', $text, 22, '5000" style="width:99%', true, true, 'ltr', 'code');

	print_template_javascript();

	print_label_row($vbphrase['save_in_template_history'],
		'<label for="savehistory"><input type="checkbox" name="savehistory" id="savehistory" value="1" tabindex="1" ' . (($updatetemplate_edit_conflict AND $vbulletin->GPC['savehistory']) ? 'checked="checked" ' : '') . '/>' .
			$vbphrase['yes'] . '</label><br /><span class="smallfont">' . $vbphrase['comment_gstyle'] .
			'</span> <input type="text" name="histcomment" value="' . ($updatetemplate_edit_conflict ? $vbulletin->GPC['histcomment'] : '') . '" tabindex="1" class="bginput" size="50" />');

	print_submit_row($vbphrase['save'], '_default_', 2, '',
		"<input type=\"submit\" class=\"button\" tabindex=\"1\" name=\"return\" value=\"$vbphrase[save_and_reload]\" accesskey=\"e\" />");

	if ($updatetemplate_edit_conflict)
	{
		print_form_header('', '', false, true, 'cpform_oldtemplate');
		print_column_style_code(array('width:20%', 'width:80%'));
		print_table_header($vbphrase['your_version_of_template']);
		print_description_row($vbphrase['template_your_version_merge_from_here']);
		print_textarea_row($vbphrase['template'], 'oldtemplate_editconflict', $vbulletin->GPC['template'], 22, '5000" style="width:99%" readonly="readonly', true, false, 'ltr', 'code');
		//print_template_javascript();
		print_table_footer();
	}

	?>
	<script type="text/javascript">
	<!--
	var initial_crc32 = crc32(YAHOO.util.Dom.get(textarea_id).value);
	var confirmUnload = true;
	YAHOO.util.Event.addListener('cpform', 'submit', function(e) { confirmUnload = false; });
	YAHOO.util.Event.addListener(window, 'beforeunload', function(e) {
		if (initial_crc32 != crc32(YAHOO.util.Dom.get(textarea_id).value) && confirmUnload) {
			e.returnValue = '<?php echo addslashes_js($vbphrase[unsaved_data_may_be_lost]); ?>';
		}
	});
	//-->
	</script>
	<?php
}

// #############################################################################
// kill a template and update template id caches for dependent styles
if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'group' => vB_Cleaner::TYPE_STR,
	));

	$template = vB_Api::instanceInternal('template')->fetchByID($vbulletin->GPC['templateid']);
	if ($template)
	{
		vB_Api::instanceInternal('template')->delete($vbulletin->GPC['templateid']);
		//print_rebuild_style($template['styleid'], '', 0, 0, 0, 0);
	}

	?>
	<script type="text/javascript">
	<!--

	// refresh the opening window (used for the revert updated default templates action)
	if (window.opener && String(window.opener.location).indexOf("template.php?do=findupdates") != -1)
	{
		window.opener.window.location = window.opener.window.location;
	}

	//-->
	</script>
	<?php


	if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT AND $template['styleid'] == -1)
	{
		require_once(DIR . '/includes/functions_filesystemxml.php');
		autoexport_delete_template($template['title']);
	}
	$args = array();
	parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
	$args['do'] = 'modify';
	$args['expandset'] = $template['styleid'];
	$args['group'] = $vbulletin->GPC['group'];
	print_cp_redirect2('template', $args, 1);
}

// #############################################################################
// confirmation for template deletion
if ($_REQUEST['do'] == 'delete')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'group' => vB_Cleaner::TYPE_STR,
	));

	$hidden = array();
	$hidden['group'] = $vbulletin->GPC['group'];
	print_delete_confirmation('template', $vbulletin->GPC['templateid'], 'template', 'kill', 'template', $hidden, $vbphrase['please_be_aware_template_is_inherited']);

}

// #############################################################################
// lets the user see the original template
if ($_REQUEST['do'] == 'view')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'title' => vB_Cleaner::TYPE_STR,
	));

	$template = vB::getDbAssertor()->getRow(
				'template',
				array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'title', 'value' => $vbulletin->GPC['title'], 'operator' => vB_dB_Query::OPERATOR_EQ),
						array('field' => 'styleid', 'value' => array(-1,0), 'operator' => vB_dB_Query::OPERATOR_EQ)
					)
				)
	);

	print_form_header('', '');
	print_table_header($vbphrase['show_default']);
	print_textarea_row($template['title'], '--[-ORIGINAL-TEMPLATE-]--', $template['template_un'], 20, 80, true, true, 'ltr', 'code');
	print_table_footer();
}


// #############################################################################
// update display order values
if ($_POST['do'] == 'dodisplayorder')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'displayorder' => vB_Cleaner::TYPE_ARRAY_INT,
		'userselect'   => vB_Cleaner::TYPE_ARRAY_INT,
	));
	$styleAPI = vB_Api::instanceInternal('style');
	$styles = $styleAPI->fetchStyles(false, false);
	foreach ($styles as $style)
	{
		$order = $vbulletin->GPC['displayorder']["{$style['styleid']}"];
		$uperm = intval($vbulletin->GPC['userselect']["{$style['styleid']}"]);
		if ($style['displayorder'] != $order OR $style['userselect'] != $uperm)
		{
			$styleAPI->updateStyle($style[styleid], $style['title'], $style['parentid'], $uperm, $order);
		}
	}
	$args = array();
	parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
	$args['do'] = 'modify';
	print_cp_redirect2('template', $args);

}

// #############################################################################
// Main style generator display
if ($_REQUEST['do'] == 'stylegenerator')
{
	global $vbphrase, $vbulletin;

	$vbulletin->input->clean_array_gpc('p', array(
		'data'         => vB_Cleaner::TYPE_STR,
		'parentid'     => vB_Cleaner::TYPE_INT,
		'name'         => vB_Cleaner::TYPE_STR,
		'displayorder' => vB_Cleaner::TYPE_INT,
		'userselect'   => vB_Cleaner::TYPE_STR
	));
	$vbulletin->input->clean_array_gpc('r', array(
		'save' => vB_Cleaner::TYPE_STR
	));

	if (is_browser('ie') AND !is_browser('ie', 7))
	{
		print_stop_message2('style_generator_browser_not_supported');
	}

	// Variables that decides who, what, when, where and how of saving the style.
	$styledata = $vbulletin->GPC['data'];
	$styleparentid = $vbulletin->GPC['parentid'];
	$styletitle = $vbulletin->GPC['name'];
	$styleanyversion = true;
	$styledisplayorder = $vbulletin->GPC['displayorder'];
	$styleuserselectable = $vbulletin->GPC['userselect'];

	// url response tell us where to save the xml
	$stylesave = $vbulletin->GPC['save'];

	if ($stylesave)
	{
		$version = ADMIN_VERSION_VBULLETIN;
		$stylexml = generate_style($styledata, $styleparentid, $styletitle, $styleanyversion,
			$styledisplayorder, $styleuserselectable, $version);
	}

		//  Modified version of the "Color Scheme Designer 3"
		//  Copyright (c) 2002-2009, Petr Stanicek, pixy@pixy.cz ("the author")
		//  See your do_not_upload folder for the full license

		echo "<div id=\"jscheck\">
		<h1>" . $vbphrase['style_generator_gstyle'] . "</h1>
		<div id=\"load\">
		<h4>" . $vbphrase['style_generator_error'] . "</h4>
			<hr>
			</div>
			</div>

			<script type=\"text/javascript\">
			<!--
			var elm = document.getElementById('load')
			if (elm) {elm.innerHTML = '<p>" . addslashes_js($vbphrase['style_generator_loading']) . "<'+'/p>';}
			//-->
			</script>
	<div id=\"canvas\">
		<div class=\"styleaction\">
			<div class=\"tabs\" id=\"tabs-color\">
				<ul>
					<li><a id=\"tab-wheel\" class=\"sel help\" href=\"#\">" . $vbphrase['choose_primary_color'] . "</a></li>
					<li><a id=\"tab-vars\" class=\"help\" href=\"#\">" . $vbphrase['fine_tune'] . "</a></li>
				</ul>
			</div>
			<div id=\"pane-wheel\" class=\"pane\"><div id=\"wheel\">
				<div id=\"sample\" class=\"bg-pri-0\"></div>
				<div id=\"wh1\"></div>
				<div id=\"wh2\"></div>
				<div id=\"wh3\"></div>
				<div id=\"wh4\"></div>
				<img class=\"dot help\" id=\"dot1\" src=\"../images/style_generator/e.png\">
				<img class=\"dot help\" id=\"dot2\" src=\"../images/style_generator/e.png\">
				<img class=\"dot help\" id=\"dot3\" src=\"../images/style_generator/e.png\">
				<div class=\"val help\" id=\"hue-val\">" . $vbphrase['hue_gstyle'] . " <span>0</span></div>
				<div class=\"val help\" id=\"dist-val\">" . $vbphrase['angle'] . " <span>0</span></div>
				<table id=\"rgb-parts\" class=\"help\">
					<tr><th>R:</th><td id=\"rgb-r\">100 %</td></tr>
					<tr><th>G:</th><td id=\"rgb-g\">0 %</td></tr>
					<tr><th>B:</th><td id=\"rgb-b\">0 %</td></tr>
				</table>
				<div class=\"val help\" id=\"rgb-val\">" . $vbphrase['hex'] . " <span>0F4FA8</span></div>
			</div></div>
			<div id=\"pane-vars\" class=\"pane\">
				<div class=\"tabs\" id=\"tabs-preview\">
					<ul>
						<li><a id=\"tab-light-ps\" class=\"help sel previewtab\" href=\"#\">" . $vbphrase['color_gstyle'] . "</a></li>
						<li><a id=\"tab-light-pt\" class=\"help previewtab\" href=\"#\">" . $vbphrase['white'] . "</a></li>
						<li><a id=\"tab-grey\" class=\"help previewtab\" href=\"#\">" . $vbphrase['grey'] . "</a></li>
						<li><a id=\"tab-dark\" class=\"help previewtab\" href=\"#\">" . $vbphrase['dark'] . "</a></li>
					</ul>
				</div>
				<div id=\"saturation-cover\">
					<div id=\"saturation\"><div class=\"slider\">
						<img class=\"dot help\" id=\"dots\" src=\"../images/style_generator/e.png\">
						<img class=\"dotv\" id=\"dotv0\" src=\"../images/style_generator/e.png\">
						<img class=\"dotv\" id=\"dotv1\" src=\"../images/style_generator/e.png\">
						<img class=\"dotv\" id=\"dotv2\" src=\"../images/style_generator/e.png\">
						<img class=\"dotv\" id=\"dotv3\" src=\"../images/style_generator/e.png\">
						<img class=\"dotv\" id=\"dotv4\" src=\"../images/style_generator/e.png\">
					</div></div>
					<div id=\"colorcomparison\">
						<span>" . $vbphrase['new'] . "</span>
						<div id=\"currentcolor\" class=\"help currentcolor\">&nbsp;</div>
						<div id=\"originalcolor\" class=\"help originalcolor\">&nbsp;</div>
						<span>" . $vbphrase['current'] . "</span>
					</div>
				</div>
			</div>
			<div id=\"savestyle\">
				<h3><span id=\"save-style\" class=\"save-style help\">" . $vbphrase['save_style'] . "</span></h3>
				<div class=\"floatcontainer\">";
					import_generated_style();
					echo "
				</div>
				<div class=\"save\" id=\"save-type\">
					<ul>
					<li id=\"menu-light-ps\" class=\"savemenu selected\"><a id=\"menu-export-light-ps\" class=\"help previewing selected\" href=\"#\">" . $vbphrase['save'] . "</a></li>
					<li id=\"menu-light-pt\" class=\"savemenu\"><a id=\"menu-export-light-pt\" class=\"help previewing\" href=\"#\">" . $vbphrase['save'] . "</a></li>
					<li id=\"menu-grey\" class=\"savemenu\"><a id=\"menu-export-grey\" class=\"help previewing\" href=\"#\">" . $vbphrase['save'] . "</a></li>
					<li id=\"menu-dark\" class=\"savemenu\"><a id=\"menu-export-dark\" class=\"help previewing\" href=\"#\">" . $vbphrase['save'] . "</a></li>
					</ul>
				</div>
			</div>
		</div>
		<div class=\"stylereaction\">
			<div id=\"palette\">
				<h3>" . $vbphrase['my_color_palette'] . "</h3>
				<div id=\"manualvars\">
				</div>
				<div style=\"clear:both;\"></div>
			</div>
			<div id=\"preview-palette-canvas\" class=\"help\">
				<h3>" . $vbphrase['preview_window'] . "</h3>
				";

				//preview text output.
				echo "
				<div id=\"previewoverride\" class=\"stylepreviewbackground bg-pri-0\">
				<div class=\"stylegenpreview\" id=\"stylegenpreview\">
					<div id=\"header\" class=\"header bg-pri-2\">
						<img src=\"../images/misc/vbulletin4_logo.png\"/>
						<div id=\"navbar\" class=\"navbar bg-pri-2\">
							<ul id=\"navtabs\" class=\"navtabs floatcontainer bg-sec1-0\">
								<li><span class=\"navtab bg-sec1-0\" href=\"#\">" . $vbphrase['home'] . "</span></li>
								<li class=\"selected\"><span class=\"navtab bg-sec1-0\" href=\"#\">" . $vbphrase['forum'] . "</span>
									<ul id=\"popupbody\" class=\"popupbody popuphover\">
										<li><span href=\"#\">" . $vbphrase['sub_1'] . "</span></li>
										<li><span href=\"#\">" . $vbphrase['sub_2'] . "</span></li>
										<li><span href=\"#\">" . $vbphrase['sub_3'] . "</span></li>
									</ul>
								</li>
								<li><span class=\"navtab bg-sec1-0\" href=\"#\">" . $vbphrase['blogs'] . "</span></li>
							</ul>
						</div>
					</div>
					<div id=\"body_wrapper\" class=\"body_wrapper bg-pri-3\">
						<div class=\"breadcrumb\">
							" . $vbphrase['forum'] . "
						</div>
						<div class=\"pagetitle\" style='border 5px solid blue'>
							<h1>" . $vbphrase['forum_title_gstyle'] . "</h1>
						</div>
						<ol id=\"forums\">
							<li class=\"forumbit_nopost L1\" id=\"cat1\">
							<div id=\"forumhead\" class=\"forumhead foruminfo L1 collapse bg-pri-1\">
								<h2>
									<span class=\"forumtitle\">" . $vbphrase['main_category'] . "</span>
									<span class=\"forumlastpost\">" . $vbphrase['last_post'] . "</span>
								</h2>
								<div class=\"forumrowdata\">
									<div id=\"subforumdescription\" class=\"subforumdescription bg-pri-3\">" . $vbphrase['main_category_description'] . "</div>
								</div>
							</div>
							<ol id=\"c_cat1\" class=\"childforum\">
								<li id=\"forum2\" class=\"forumbit_post L2\">
							<div class=\"forumrow table\">
								<div id=\"foruminfowrap\">
									<div class=\"foruminfo td\">
										<img src=\"../images/statusicon/forum_old-48.png\" class=\"forumicon\" id=\"forum_statusicon_2\" alt=\"\" />
										<div class=\"forumdata\">
											<div class=\"datacontainer\">
												<div class=\"titleline\">
													<h2 class=\"forumtitle mainforum col-pri-2\">" . $vbphrase['main_forum'] . "</h2>
												</div>
												<p class=\"forumdescription\">" . $vbphrase['main_forum_description'] . "</p>
											</div>
										</div>
									</div>
									<ul class=\"forumstats td\">
										<li>" . $vbphrase['threads'] . ": 0</li>
										<li>" . $vbphrase['posts'] . ": 0</li>
									</ul>
									<div class=\"forumlastpost td\">
										" . $vbphrase['last_post'] . ":
										<div>
											" . $vbphrase['never'] . "
										</div>
									</div>
								</div>
							</div>
						</li>
							</ol>
						</li>
							</ol>
					<div class=\"navlinks\">
					" . $vbphrase['mark_forums_read'] . "|" . $vbphrase['view_site_leaders'] . "
					</div>
					</div>
					<div id=\"footer\" class=\"footer bg-pri-2\">
						<ul id=\"footer_links\" class=\"footer_links\">
							<li><span href=\"#\">" . $vbphrase['contact_us'] . "</span></li>
							<li><span href=\"#\">" . $vbphrase['archive'] . "</span></li>
							<li><span href=\"#\">" . $vbphrase['top_gstyle'] . "</span></li>
						</ul>
					</div>
				</div>
				</div>";
			//end of preview text
			echo "
				</div>
				<div id=\"fps\" class=\"help\"></div>
				<ul id=\"menu\" class=\"\">
					<li><a href=\"#\" id=\"menu-undo\" class=\"\">" . $vbphrase['undo_gstyle'] . "</a></li>
					<li><a href=\"#\" id=\"menu-redo\" class=\"disabled\">" . $vbphrase['redo_gstyle'] . "</a></li>
					<li><a href=\"#\" id=\"menu-tooltips\">" . $vbphrase['show_tooltips'] . "</a></li>
				</ul>
			</div>
			<div id=\"help\" style=\"display:none\">
				";
			$styleinfohelp = array(
				'help-tab-wheel'    => array($vbphrase['choose_primary_color'], $vbphrase['choose_primary_color_desc']),
				'help-tab-vars'     => array($vbphrase['fine_tune'], $vbphrase['fine_tune_desc']),
				'help-menu-export-light-ps'     => array($vbphrase[''], $vbphrase['export_light_ps_desc']),
				'help-menu-export-light-pt'     => array($vbphrase[''], $vbphrase['export_light_pt_desc']),
				'help-menu-export-grey'     => array($vbphrase[''], $vbphrase['export_grey_desc']),
				'help-menu-export-dark'     => array($vbphrase[''], $vbphrase['export_dark_desc']),
				'help-dot1'    		=> array($vbphrase['primary_color_hue'], $vbphrase['primary_color_hue_desc']),
				'help-dot3'   		=> array($vbphrase['secondary_color_hue'], $vbphrase['secondary_color_hue_desc']),
				'help-hue-val'    	=> array($vbphrase['primary_color_hue_value'], $vbphrase['primary_color_hue_value_desc']),
				'help-dist-val'    	=> array($vbphrase['secondary_color_hues'], $vbphrase['secondary_color_hues_desc']),
				'help-rgb-val'    	=> array($vbphrase['primary_color_hex_value'], $vbphrase['primary_color_hex_desc']),
				'help-rgb-parts'   	=> array($vbphrase['primary_color_rgb_values'], ''),
				'help-dots'    		=> array($vbphrase['brightness_and_saturation'], $vbphrase['brightness_and_saturation_desc']),
				'help-dotc'    		=> array($vbphrase['scheme_contrast_help'], $vbphrase['scheme_contrast_desc']),
				'help-palette'   	=> array($vbphrase['pre'], $vbphrase['des']),
				'help-preview-palette-canvas'   => array($vbphrase['palette_preview'], $vbphrase['palette_preview_desc']),
				'help-tab-preview'  => array($vbphrase['palette_preview'], $vbphrase['palette_preview_desc']),
				'help-showtext'   	=> array($vbphrase['show_text_sample'], $vbphrase['show_text_sample']),
				'help-showtooltips' => array($vbphrase['show_tooltips'], $vbphrase['show_tooltips_desc']),
				'help-tab-light-ps' => array($vbphrase['color_style'], $vbphrase['color_style_desc_gstyle']),
				'help-tab-light-pt' => array($vbphrase['white_style'], $vbphrase['white_style_desc']),
				'help-tab-grey' => array($vbphrase['grey_style'], $vbphrase['grey_style_desc']),
				'help-tab-dark' => array($vbphrase['dark_style'], $vbphrase['dark_style_desc']),
				'help-currentcolor' => array($vbphrase['currentcolor'], $vbphrase['currentcolor_desc']),
				'help-originalcolor' => array($vbphrase['originalcolor'], $vbphrase['originalcolor_desc']),
				'help-title-generated-style' => array($vbphrase['title_generated_style'], $vbphrase['title_generated_style_desc']),
				'help-parent-id' => array($vbphrase['parent_id'], $vbphrase['parent_id_desc']),
				'help-display-order' => array($vbphrase['display_order'], $vbphrase['display_order_desc']),
				'help-allow-user-selection' => array($vbphrase['user_selection'], $vbphrase['user_selection_desc']),
				'help-save-style' => array($vbphrase['save_style'], $vbphrase['save_style_desc'])
			);
			print_style_help($styleinfohelp);
			echo "
			</div>
		</div>
	<script type=\"text/javascript\">
	<!--
		var hash = document.location.hash.substring(1);
		if (hash) {}
		else { loadScheme('3E11TvVw5w0w0'); }
		var palInfoColorPri = ['" . addslashes_js($vbphrase['primary0_info']) . "','" . addslashes_js($vbphrase['primary1_info']) . "','" . addslashes_js($vbphrase['primary2_info']) . "','" . addslashes_js($vbphrase['primary3_info']) . "','" . addslashes_js($vbphrase['primary4_info']) . "'];
		var palInfoColorSec = ['" . addslashes_js($vbphrase['secondary0_info']) . "','" . addslashes_js($vbphrase['secondary1_info']) . "','" . addslashes_js($vbphrase['secondary2_info']) . "','','" . addslashes_js($vbphrase['secondary4_info']) . "'];
		var palInfoWhitePri = ['" . addslashes_js($vbphrase['primary0_info']) . "','" . addslashes_js($vbphrase['primary1_info']) . "','" . addslashes_js($vbphrase['primary2_info']) . "','',''];
		var palInfoWhiteSec = ['','','','',''];
		var palInfoDarkPri = ['" . addslashes_js($vbphrase['primary0_info']) . "','','','" . addslashes_js($vbphrase['primary3_info']) . "',''];
		var palInfoDarkSec = ['','','','',''];
		var notSupportedBrowser = '" . addslashes_js($vbphrase['not_supported_browser']) . "';
			//-->
	</script>
	</body>
	</html>";
}

// #############################################################################
// main template list display
if ($_REQUEST['do'] == 'modify')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'searchset'    => vB_Cleaner::TYPE_INT,
		'expandset'    => vB_Cleaner::TYPE_NOHTML,
		'searchstring' => vB_Cleaner::TYPE_STR,
		'titlesonly'   => vB_Cleaner::TYPE_BOOL,
		'group'        => vB_Cleaner::TYPE_NOHTML,
	));
	// populate the stylecache
	$stylecache = vB_Library::instance('Style')->fetchStyles(false, false);
	// sort out parameters for searching
	if ($vbulletin->GPC['searchstring'])
	{
		$vbulletin->GPC['group'] = 'all';
		if ($vbulletin->GPC['searchset'] > 0)
		{
			$vbulletin->GPC['expandset'] =& $vbulletin->GPC['searchset'];
		}
		else
		{
			$vbulletin->GPC['expandset'] = 'all';
		}
	}
	else
	{
		$vbulletin->GPC['searchstring'] = '';
	}
	// all browsers now support the enhanced template editor
	if (true)
	{
		define('FORMTYPE', 1);
		$SHOWTEMPLATE = 'construct_template_option';
	}
	else
	{
		define('FORMTYPE', 0);
		$SHOWTEMPLATE = 'construct_template_link';
	}

	if ($vb5_config['Misc']['debug'])
	{
		$JS_STYLETITLES[] = "\"0\" : \"" . $vbphrase['master_style'] . "\"";
		$prepend = '--';
	}

	foreach($stylecache AS $style)
	{
		$JS_STYLETITLES[] = "\"$style[styleid]\" : \"" . addslashes_js($style['title'], '"') . "\"";
		$JS_STYLEPARENTS[] = "\"$style[styleid]\" : \"$style[parentid]\"";
	}

	$JS_MONTHS = array();
	$i = 0;
	$months = array('january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december');
	foreach($months AS $month)
	{
		$JS_MONTHS[] = "\"$i\" : \"" . $vbphrase["$month"] . "\"";
		$i++;
	}

	foreach (array(
		'click_the_expand_collapse_button',
		'this_template_has_been_customized_in_a_parent_style',
		'this_template_has_not_been_customized',
		'this_template_has_been_customized_in_this_style',
		'template_last_edited_js',
		'x_templates'
		) AS $phrasename)
	{
		$JS_PHRASES[] = "\"$phrasename\" : \"" . fetch_js_safe_string($vbphrase["$phrasename"]) . "\"";
	}

?>

<script type="text/javascript">
<!--
var SESSIONHASH = "<?php echo vB::getCurrentSession()->get('sessionhash'); ?>";
var EXPANDSET = "<?php echo $vbulletin->GPC['expandset']; ?>";
var GROUP = "<?php echo $vbulletin->GPC['group']; ?>";
var SEARCHSTRING = "<?php echo urlencode($vbulletin->GPC['searchstring']); ?>";
var STYLETITLE = { <?php echo implode(', ', $JS_STYLETITLES); ?> };
var STYLEPARENTS = { <?php echo implode(', ', $JS_STYLEPARENTS); ?> };
var MONTH = { <?php echo implode(', ', $JS_MONTHS); ?> };
var vbphrase = {
	<?php echo implode(",\r\n\t", $JS_PHRASES) . "\r\n"; ?>
};

// -->
</script>

<?php
if (!FORMTYPE)
{
	print_form_header('', '');
	print_table_header("$vbphrase[styles_gcpglobal] &amp; $vbphrase[templates]");
	print_description_row('
		<div class="darkbg" style="border: 2px inset"><ul class="darkbg">
		<li><b>' . $vbphrase['color_key'] . '</b></li>
		<li class="col-g">' . $vbphrase['template_is_unchanged_from_the_default_style'] . '</li>
		<li class="col-i">' . $vbphrase['template_is_inherited_from_a_parent_style'] . '</li>
		<li class="col-c">' . $vbphrase['template_is_customized_in_this_style'] . '</li>
		</ul></div>
	');
	print_table_footer();
}
else
{
	//echo "<br />\n";
}

if ($help = construct_help_button('', NULL, '', 1))
{
	$pagehelplink = "<div style=\"float:" . vB_Template_Runtime::fetchStyleVar('right') . "\">$help</div>";
}
else
{
	$pagehelplink = '';
}

?>

<form action="template.php?do=displayorder" method="post" tabindex="1" name="tform">
<input type="hidden" name="do" value="dodisplayorder" />
<input type="hidden" name="s" value="<?php echo vB::getCurrentSession()->get('sessionhash'); ?>" />
<input type="hidden" name="adminhash" value="<?php echo ADMINHASH; ?>" />
<input type="hidden" name="expandset" value="<?php echo $vbulletin->GPC['expandset']; ?>" />
<input type="hidden" name="group" value="<?php echo $vbulletin->GPC['group']; ?>" />
<div align="center">
<div class="tborder" style="width:100%; text-align:<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>">
<div class="tcat"><?php echo $pagehelplink; ?><b><?php echo $vbphrase['style_manager']; ?></b></div>
<div class="stylebg">

<?php

	$masterset = array();
	if (!empty($vbulletin->GPC['expandset']))
	{
		DEVDEBUG("Querying master template ids");
		$masters = vB::getDbAssertor()->assertQuery(
					'template',
					array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						vB_dB_Query::CONDITIONS_KEY => array(
							array('field' => 'templatetype', 'value' => 'template', 'operator' => vB_dB_Query::OPERATOR_EQ),
							array('field' => 'styleid', 'value' => array(-1,0), 'operator' => vB_dB_Query::OPERATOR_EQ)
						)
					),
					'title'
		);
		foreach ($masters as $master)
		{
			$masterset["$master[title]"] = $master['templateid'];
		}
	}

	$LINKEXTRA = '';
	if (!empty($vbulletin->GPC['group']))
	{
		$LINKEXTRA .= "&amp;group=" . $vbulletin->GPC['group'];
	}
	if (!empty($vbulletin->GPC['searchstring']))
	{
		$LINKEXTRA .= "&amp;searchstring=" . urlencode($vbulletin->GPC['searchstring']) . "&amp;searchset=" . $vbulletin->GPC['searchset'];
	}

	if ($vb5_config['Misc']['debug'])
	{
		print_style(-1);
	}
	foreach($stylecache AS $styleid => $style)
	{
		print_style($styleid, $style);
	}

?>
</div>
<table cellpadding="2" cellspacing="0" border="0" width="100%" class="tborder" style="border: 0px">
<tr>
	<td class="tfoot" align="center">
		<input type="submit" class="button" tabindex="1" value="<?php echo $vbphrase['save_display_order']; ?>" />
		<input type="button" class="button" tabindex="1" value="<?php echo $vbphrase['search_in_templates_gstyle']; ?>" onclick="window.location='template.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=search';" />
	</td>
</tr>
</table>
</div>
</div>
</form>
<?php

	echo '<p align="center" class="smallfont">' .
		construct_link_code($vbphrase['add_new_style'], "template.php?" . vB::getCurrentSession()->get('sessionurl') . "do=addstyle");
	if ($vb5_config['Misc']['debug'])
	{
		echo construct_link_code($vbphrase['rebuild_all_styles'], "template.php?" . vB::getCurrentSession()->get('sessionurl') . "do=rebuild&amp;goto=template.php?" . vB::getCurrentSession()->get('sessionurl'));
	}
	echo "</p>\n";


	// search only
	/*
	print_form_header('template', 'modify');
	print_table_header($vbphrase['search_templates']);
	construct_hidden_code('searchset', -1);
	construct_hidden_code('titlesonly', 0);
	print_input_row($vbphrase['search_for_text'], 'searchstring', $vbulletin->GPC['searchstring']);
	print_description_row('<input type="button" value="Submit with GET" onclick="window.location = (\'template.php?do=modify&amp;searchset=-1&amp;searchstring=\' + this.form.searchstring.value)" />');
	print_submit_row($vbphrase['find']);
	*/

}

// #############################################################################
// rebuilds all parent lists and id cache lists
if ($_REQUEST['do'] == 'rebuild')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'renumber'	=> vB_Cleaner::TYPE_INT,
		'install'	=> vB_Cleaner::TYPE_INT,
		'goto'		=> vB_Cleaner::TYPE_STR,
	));

	echo "<p>&nbsp;</p>";
	vB_Api::instanceInternal('style')->buildAllStyles($vbulletin->GPC['renumber'], $vbulletin->GPC['install']);
	$execurl =  vB_String::parseUrl($vbulletin->GPC['goto']);
	$pathinfo = pathinfo($execurl['path']);
	$file = $pathinfo['basename'];
	parse_str($execurl['query'], $args);
	print_cp_redirect2($file, $args);
}

// #############################################################################
// create template files

if ($_REQUEST['do'] == 'createfiles' AND $vb5_config['Misc']['debug'])
{
	// this action requires that a web-server writable folder called
	// 'template_dump' exists in the root of the vbulletin directory

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}
	$templateids = empty($templateids)? array() : $templateids;
	vB_Api::instanceInternal('style')->createTemplateFiles($vbulletin->GPC['dostyleid'], $templateids);
}

// #############################################################################
// hex convertor
if ($_REQUEST['do'] == 'colorconverter')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'hex'    => vB_Cleaner::TYPE_NOHTML,
		'rgb'    => vB_Cleaner::TYPE_NOHTML,
		'hexdec' => vB_Cleaner::TYPE_STR,
		'dechex' => vB_Cleaner::TYPE_STR,
	));

	if ($vbulletin->GPC['dechex'])
	{
		$vbulletin->GPC['rgb'] = preg_split('#\s*,\s*#si', $vbulletin->GPC['rgb'], -1, PREG_SPLIT_NO_EMPTY);
		$vbulletin->GPC['hex'] = '#';
		foreach ($vbulletin->GPC['rgb'] AS $i => $value)
		{
			$vbulletin->GPC['hex'] .= strtoupper(str_pad(dechex($value), 2, '0', STR_PAD_LEFT));
		}
		$vbulletin->GPC['rgb'] = implode(',', $vbulletin->GPC['rgb']);
	}
	else if ($vbulletin->GPC['hexdec'])
	{
		if (preg_match('/#?([a-f0-9]{2})([a-f0-9]{2})([a-f0-9]{2})/siU', $vbulletin->GPC['hex'], $matches))
		{
			$vbulletin->GPC['rgb'] = array();
			for ($i = 1; $i <= 3; $i++)
			{
				$vbulletin->GPC['rgb'][] = hexdec($matches["$i"]);
			}
			$vbulletin->GPC['rgb'] = implode(',', $vbulletin->GPC['rgb']);
			$vbulletin->GPC['hex'] = strtoupper("#$matches[1]$matches[2]$matches[3]");
		}
	}

	print_form_header('template', 'colorconverter');
	print_table_header('Color Converter');
	print_label_row('Hexadecimal Color (#xxyyzz)', "<span style=\"padding:4px; background-color:" . $vbulletin->GPC['hex'] . "\"><input type=\"text\" class=\"bginput\" name=\"hex\" value=\"" . $vbulletin->GPC['hex'] . "\" size=\"20\" maxlength=\"7\" /> <input type=\"submit\" class=\"button\" name=\"hexdec\" value=\"Hex &raquo; RGB\" /></span>");
	print_label_row('RGB Color (r,g,b)', "<span style=\"padding:4px; background-color:rgb(" . $vbulletin->GPC['rgb'] . ")\"><input type=\"text\" class=\"bginput\" name=\"rgb\" value=\"" . $vbulletin->GPC['rgb'] . "\" size=\"20\" maxlength=\"11\" /> <input type=\"submit\" class=\"button\" name=\"dechex\" value=\"RGB &raquo; Hex\" /></span>");
	print_table_footer();
}

print_cp_footer();
/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 71443 $
|| ####################################################################
\*======================================================================*/
