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
define('CVS_REVISION', '$RCSfile$ - $Revision: 70677 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $DEVDEBUG;
global $phrasegroups, $specialtemplates, $vbphrase, $vbulletin;
$phrasegroups = array('language');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/adminfunctions_language.php');
require_once(DIR . '/includes/functions_misc.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminlanguages'))
{
	print_cp_no_permission();
}

$vbulletin->input->clean_array_gpc('r', array(
	'phraseid' => vB_Cleaner::TYPE_INT,
));

// ############################# LOG ACTION ###############################
log_admin_action(iif($vbulletin->GPC['phraseid'], "phrase id = " . $vbulletin->GPC['phraseid']));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vb5_config =& vB::getConfig();

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

$full_product_info = fetch_product_list(true);

// #############################################################################

if ($_REQUEST['do'] == 'quickref')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'languageid' => vB_Cleaner::TYPE_INT,
		'fieldname'  => vB_Cleaner::TYPE_NOHTML,
	));

	if ($vbulletin->GPC['languageid'] == 0)
	{
		$vbulletin->GPC['languageid'] = vB::getDatastore()->getOption('languageid');
	}
	if ($vbulletin->GPC['fieldname'] == '')
	{
		$vbulletin->GPC['fieldname'] = 'global';
	}
	$languages = vB_Api::instanceInternal('language')->fetchAll();
	if ($vb5_config['Misc']['debug'])
	{
		$langoptions['-1'] = $vbphrase['master_language'];
	}
	foreach($languages AS $id => $lang)
	{
		$langoptions["$id"] = $lang['title'];
	}
	$phrasetypes = vB_Api::instanceInternal('phrase')->fetch_phrasetypes();
	foreach($phrasetypes AS $fieldname => $type)
	{
		$typeoptions["$fieldname"] = $type['title'] . ' ' . $vbphrase['phrases'];
	}

	define('NO_PAGE_TITLE', true);
	print_cp_header("$vbphrase[quickref] {$langoptions["{$vbulletin->GPC['languageid']}"]} {$typeoptions["{$vbulletin->GPC['fieldname']}"]}", '', '', 0);

	$phrasearray = array();

	if ($vbulletin->GPC['languageid'] != -1)
	{
		$custom = fetch_custom_phrases($vbulletin->GPC['languageid'], $vbulletin->GPC['fieldname']);
		if (!empty($custom))
		{
			foreach($custom AS $phrase)
			{
				$phrasearray[htmlspecialchars_uni($phrase['text'])] = $phrase['varname'];
			}
		}
	}

	$standard = fetch_standard_phrases($vbulletin->GPC['languageid'], $vbulletin->GPC['fieldname']);

	if (is_array($standard))
	{
		foreach($standard AS $phrase)
		{
			$phrasearray[htmlspecialchars_uni($phrase['text'])] = $phrase['varname'];
		}
		$tval = $langoptions["{$vbulletin->GPC['languageid']}"] . ' ' . $typeoptions["{$vbulletin->GPC['fieldname']}"];
	}
	else
	{
		$tval = construct_phrase($vbphrase['no_x_phrases_defined'], '<i>' . $typeoptions["{$vbulletin->GPC['fieldname']}"] . '</i>');
	}

	$directionHtml = 'dir="' . $languages["{$vbulletin->GPC['languageid']}"]['direction'] . '"';

	print_form_header('phrase', 'quickref', 0, 1, 'cpform', '100%', '', 0);
	print_table_header($vbphrase['quickref'] . ' </b>' . $langoptions["{$vbulletin->GPC['languageid']}"] . ' ' . $typeoptions["{$vbulletin->GPC['fieldname']}"] . '<b>');
	print_label_row("<select size=\"10\" class=\"bginput\" onchange=\"
		if (this.options[this.selectedIndex].value != '')
		{
			this.form.tvar.value = '\$" . "vbphrase[' + this.options[this.selectedIndex].text + ']';
			this.form.tbox.value = this.options[this.selectedIndex].value;
		}
		\">" . construct_select_options($phrasearray) . '</select>','
		<input type="text" class="bginput" name="tvar" size="35" class="button" /><br />
		<textarea name="tbox" class="darkbg" style="font: 11px verdana" rows="8" cols="35" ' . $directionHtml . '>' . $tval . '</textarea>
		');
	print_description_row('
		<center>
		<select name="languageid" accesskey="l" class="bginput">' . construct_select_options($langoptions, $vbulletin->GPC['languageid']) . '</select>
		<select name="fieldname" accesskey="t" class="bginput">' . construct_select_options($typeoptions, $vbulletin->GPC['fieldname']) . '</select>
		<input type="submit" class="button" value="' . $vbphrase['view'] . '" accesskey="s" />
		<input type="button" class="button" value="' . $vbphrase['close_gcpglobal'] . '" accesskey="c" onclick="self.close()" />
		</center>
	', 0, 2, 'thead');
	print_table_footer();

	unset($DEVDEBUG);
	print_cp_footer();

}

// #############################################################################

if ($_POST['do'] == 'completeorphans')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'del'  => vB_Cleaner::TYPE_ARRAY_STR,  // phrases to delete
		'keep' => vB_Cleaner::TYPE_ARRAY_UINT, // phrases to keep
	));

	vB_Api::instanceInternal('phrase')->processOrphans($vbulletin->GPC['del'], $vbulletin->GPC['keep']);

	$args = array();
	parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
	$args['do'] = 'rebuild';
	$args['goto'] = urlencode("phrase.php?" . vB::getCurrentSession()->get('sessionurl'));
	exec_header_redirect2('language', $args);
}

// #############################################################################

if ($_POST['do'] != 'doreplace')
{
	print_cp_header($vbphrase['phrase_manager_glanguage']);
}

// #############################################################################

if ($_POST['do'] == 'manageorphans')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'phr' => vB_Cleaner::TYPE_ARRAY_BOOL,
	));

	print_form_header('phrase', 'completeorphans');

	$hidden_code_num = 0;
	$keepnames = array();

	foreach ($vbulletin->GPC['phr'] AS $key => $keep)
	{
		if ($keep)
		{
			fetch_varname_fieldname($key, $varname, $fieldname);
			//$keepnames[] = "(varname = '" . $vbulletin->db->escape_string($varname) . "' AND fieldname = '" . $vbulletin->db->escape_string($fieldname) . "')";
			$keepnames [] = array('varname' => $varname, 'fieldname' => $fieldname);
		}
		else
		{
			construct_hidden_code("del[$hidden_code_num]", $key);
			$hidden_code_num ++;
		}
	}
	print_table_header($vbphrase['find_orphan_phrases']);

	if (empty($keepnames))
	{
		// there are no phrases to keep, just show a message telling admin to click to proceed
		print_description_row('<blockquote><p><br />' . $vbphrase['delete_all_orphans_notes'] . '</p></blockquote>');
	}
	else
	{
		// there are some phrases to keep, show a message explaining the page
		print_description_row($vbphrase['keep_orphans_notes']);

		$orphans = array();
		$phrases = vB::getDbAssertor()->assertQuery('fetchKeepNames', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'keepnames' => $keepnames,
		));

		foreach ($phrases as $phrase)
		{
			$orphans["{$phrase['varname']}@{$phrase['fieldname']}"]["{$phrase['languageid']}"] = array('phraseid' => $phrase['phraseid'], 'text' => $phrase['text']);
		}
		$vbulletin->db->free_result($phrases);

		$languages = vB_Api::instanceInternal('language')->fetchAll();
		$phrasetypes = vB_Api::instanceInternal('phrase')->fetch_phrasetypes();

		$bgcounter = 0;
		foreach ($orphans AS $key => $languageids)
		{
			fetch_varname_fieldname($key, $varname, $fieldname);

			if (isset($languageids[vB::getDatastore()->getOption('languageid')]))
			{
				$checked = vB::getDatastore()->getOption('languageid');
			}
			else
			{
				$checked = 0;
			}

			$bgclass = fetch_row_bgclass();

			echo "<tr valign=\"top\">\n";
			echo "\t<td class=\"$bgclass\">" . construct_wrappable_varname($varname, 'font-weight:bold;') . " <dfn>" . construct_phrase($vbphrase['x_phrases'], $phrasetypes["$fieldname"]['title']) . "</dfn></td>\n";
			echo "\t<td style=\"padding:0px\">\n\t\t<table cellpadding=\"2\" cellspacing=\"1\" border=\"0\" width=\"100%\">\n\t\t<col width=\"65%\"><col width=\"35%\" align=\"" . vB_Template_Runtime::fetchStyleVar('right') . "\">\n";

			$i = 0;
			$tr_bgclass = iif((++$bgcounter % 2) == 0, 'alt2', 'alt1');

			foreach ($languages AS $_languageid => $language)
			{
				if (isset($languageids["$_languageid"]))
				{
					if ($checked)
					{
						if ($_languageid == $checked)
						{
							$checkedhtml = ' checked="checked"';
						}
						else
						{
							$checkedhtml = '';
						}
					}
					else if ($i == 0)
					{
						$checkedhtml = ' checked="checked"';
					}
					else
					{
						$checkedhtml = '';
					}
					$i++;
					$phrase =& $orphans["$key"]["$_languageid"];

					echo "\t\t<tr class=\"$tr_bgclass\">\n";
					echo "\t\t\t<td class=\"smallfont\"><label for=\"p$phrase[phraseid]\"><i>$phrase[text]</i></label></td>\n";
					echo "\t\t\t<td class=\"smallfont\"><label for=\"p$phrase[phraseid]\"><b>$language[title]</b><input type=\"radio\" name=\"keep[" . urlencode($key) . "]\" value=\"$phrase[phraseid]\" id=\"p$phrase[phraseid]\" tabindex=\"1\"$checkedhtml /></label></td>\n";
					echo "\t\t</tr>\n";
				}
			}

			echo "\n\t\t</table>\n";
			echo "\t\t<div class=\"$bgclass\">&nbsp;</div>\n";
			echo "\t</td>\n</tr>\n";
		}
	}

	print_submit_row($vbphrase['continue'], iif(empty($keepnames), false, " $vbphrase[reset] "));
}

// #############################################################################

if ($_REQUEST['do'] == 'findorphans')
{
	// get info for the languages and phrase types
	$phraseAPI = vB_Api::instanceInternal('phrase');
	$languages = vB_Api::instanceInternal('language')->fetchAll();
	$phrasetypes = $phraseAPI->fetch_phrasetypes();
	$phrases = $phraseAPI->fetchOrphans();

	if (empty($phrases))
	{
		print_stop_message2('no_phrases_matched_your_query');
	}

	$orphans = array();

	foreach ($phrases as $phrase)
	{
		$phrase['varname'] = urlencode($phrase['varname']);
		$orphans["{$phrase['varname']}@{$phrase['fieldname']}"]["{$phrase['languageid']}"] = true;
	}


	// get the number of columns for the table
	$colspan = sizeof($languages) + 2;

	print_form_header('phrase', 'manageorphans');
	print_table_header($vbphrase['find_orphan_phrases'], $colspan);

	// make the column headings
	$headings = array($vbphrase['varname']);
	foreach ($languages AS $language)
	{
		$headings[] = $language['title'];
	}
	$headings[] = '<input type="button" class="button" value="' . $vbphrase['keep_all'] . '" onclick="js_check_all_option(this.form, 1)" /> <input type="button" class="button" value="' . $vbphrase['delete_all_gcpglobal'] . '" onclick="js_check_all_option(this.form, 0)" />';
	print_cells_row($headings, 1);

	// init the counter for our id attributes in label tags
	$i = 0;

	foreach ($orphans AS $key => $languageids)
	{
		// split the array key
		fetch_varname_fieldname($key, $varname, $fieldname);

		// make the first cell
		$cell = array(construct_wrappable_varname($varname, 'font-weight:bold;') . " <dfn>" . construct_phrase($vbphrase['x_phrases'], $phrasetypes["$fieldname"]['title']) . "</dfn>");

		// either display a tick or not depending on whether a translation exists
		foreach ($languages AS $_languageid => $language)
		{
			if (isset($languageids["$_languageid"]))
			{
				$yesno = 'yes';
			}
			else
			{
				$yesno = 'no';
			}

			$cell[] = "<img src=\"" . vB::getDatastore()->getOption('bburl') . "/cpstyles/" . vB::getDatastore()->getOption('cpstylefolder') . "/cp_tick_$yesno.gif\" alt=\"\" />";
		}

		$i++;
		$varname = urlencode($varname);
		$cell[] = "
		<label for=\"k_$i\"><input type=\"radio\" id=\"k_$i\" name=\"phr[{$varname}@$fieldname]\" value=\"1\" tabindex=\"1\" />$vbphrase[keep]</label>
		<label for=\"d_$i\"><input type=\"radio\" id=\"d_$i\" name=\"phr[{$varname}@$fieldname]\" value=\"0\" tabindex=\"1\" checked=\"checked\" />$vbphrase[delete]</label>
		";

		print_cells_row($cell);
	}

	print_submit_row($vbphrase['continue'], " $vbphrase[reset] ", $colspan);
}

// #############################################################################
// find custom phrases that need updating
if ($_REQUEST['do'] == 'findupdates')
{
	// for is_newer_version...
	require_once(DIR . '/includes/adminfunctions_template.php');

	// query custom phrases
	$customcache = vB_Api::instanceInternal('phrase')->findUpdates();

	if (empty($customcache))
	{
		print_stop_message2('all_phrases_are_up_to_date');
	}
	$languages = vB_Api::instanceInternal('language')->fetchAll();

	print_form_header('', '');
	print_table_header($vbphrase['find_updated_phrases_glanguage']);
	print_description_row('<span class="smallfont">' . construct_phrase($vbphrase['updated_default_phrases_desc'], vB::getDatastore()->getOption('templateversion')) . '</span>');
	print_table_break(' ');

	foreach($languages AS $languageid => $language)
	{
		if (is_array($customcache["$languageid"]))
		{
			print_description_row($language['title'], 0, 2, 'thead');
			foreach($customcache["$languageid"] AS $phraseid => $phrase)
			{
				if (!$phrase['customuser'])
				{
					$phrase['customuser'] = $vbphrase['n_a'];
				}
				if (!$phrase['customversion'])
				{
					$phrase['customversion'] = $vbphrase['n_a'];
				}

				$product_name = $full_product_info["$phrase[product]"]['title'];

				print_label_row("
					<b>$phrase[varname]</b> ($phrase[phrasetype_title])<br />
					<span class=\"smallfont\">" .
						construct_phrase($vbphrase['default_phrase_updated_desc'],
							"$product_name $phrase[globalversion]",
							$phrase['globaluser'],
							"$product_name $phrase[customversion]",
							$phrase['customuser'])
					. '</span>',
				'<span class="smallfont">' .
					construct_link_code($vbphrase['edit'], "phrase.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;phraseid=$phraseid", 1) . '<br />' .
				'</span>'
				);
			}
		}
	}

	print_table_footer();
}

// #############################################################################

if ($_POST['do'] == 'dosearch')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'searchstring'  => vB_Cleaner::TYPE_STR,
		'searchwhere'   => vB_Cleaner::TYPE_UINT,
		'casesensitive' => vB_Cleaner::TYPE_BOOL,
		'exactmatch'    => vB_Cleaner::TYPE_BOOL,
		'languageid'    => vB_Cleaner::TYPE_INT,
		'phrasetype'    => vB_Cleaner::TYPE_ARRAY_NOHTML,
		'transonly'     => vB_Cleaner::TYPE_BOOL,
		'product'       => vB_Cleaner::TYPE_STR,
	));

	if ($vbulletin->GPC['searchstring'] == '')
	{
		print_stop_message2('please_complete_required_fields');
	}

	$criteria['exactmatch'] = $vbulletin->GPC['exactmatch'] ? 1 : 0;
	$criteria['casesensitive'] = $vbulletin->GPC['casesensitive'] ? 1 : 0;
	$criteria['searchwhere'] = $vbulletin->GPC['searchwhere'];
	$criteria['phrasetype'] = $vbulletin->GPC['phrasetype'];
	$criteria['searchstring'] = $vbulletin->GPC['searchstring'];
	$criteria['languageid'] = $vbulletin->GPC['languageid'];
	$criteria['transonly'] = $vbulletin->GPC['transonly'];
	$criteria['product'] = $vbulletin->GPC['product'];

	$phrasearray = vB_Api::instanceInternal('phrase')->search($criteria);

	if (empty($phrasearray))
	{
		print_stop_message2('no_phrases_matched_your_query');
	}

	$phrasetypes = vB_Api::instanceInternal('phrase')->fetch_phrasetypes();

	print_form_header('phrase', 'edit');
	print_table_header($vbphrase['search_results'], 5);

	$ignorecase = ($vbulletin->GPC['casesensitive'] ? false : true);

	foreach($phrasearray AS $fieldname => $x)
	{
		// display the header for the phrasetype
		print_description_row(construct_phrase($vbphrase['x_phrases_containing_y'], $phrasetypes["$fieldname"]['title'], htmlspecialchars_uni($vbulletin->GPC['searchstring'])), 0, 5, 'thead" align="center');

		// sort the phrases alphabetically by $varname
		ksort($x);
		foreach($x AS $varname => $y)
		{
			foreach($y AS $phrase)
			{
				$cell = array();
				$cell[] = '<b>' . ($vbulletin->GPC['searchwhere'] > 0 ? fetch_highlighted_search_results($vbulletin->GPC['searchstring'], $varname, $ignorecase) : $varname) . '</b>';
				$cell[] = '<span class="smallfont">' . fetch_language_type_string($phrase['languageid'], $phrase['title']) . '</span>';
				$cell[] = '<span class="smallfont">' . nl2br(($vbulletin->GPC['searchwhere'] % 10 == 0) ? fetch_highlighted_search_results($vbulletin->GPC['searchstring'], $phrase['text'], $ignorecase) : htmlspecialchars_uni($phrase['text'])) . '</span>';
				$cell[] = "<input type=\"submit\" class=\"button\" value=\" $vbphrase[edit] \" name=\"e[$fieldname][" . urlencode($varname) . "]\" />";
				if (($vb5_config['Misc']['debug'] AND $phrase['languageid'] == -1) OR $phrase['languageid'] == 0)
				{
					$cell[] = "<input type=\"submit\" class=\"button\" value=\" $vbphrase[delete] \" name=\"delete[$fieldname][" . urlencode($varname) . "]\" />";
				}
				else
				{
					$cell[] = '';
				}
				print_cells_row($cell, 0, 0, -2);
			} // end foreach($y)
		} // end foreach($x)
	} // end foreach($phrasearray)

	print_table_footer();

	$_REQUEST['do'] = 'search';

}

// #############################################################################

if ($_REQUEST['do'] == 'search')
{
	if (!isset($_REQUEST['languageid']))
	{
		$_REQUEST['languageid'] = -10;
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'searchstring'  => vB_Cleaner::TYPE_STR,
		'searchwhere'   => vB_Cleaner::TYPE_UINT,
		'casesensitive' => vB_Cleaner::TYPE_BOOL,
		'exactmatch'    => vB_Cleaner::TYPE_BOOL,
		'languageid'    => vB_Cleaner::TYPE_INT,
		'phrasetype'    => vB_Cleaner::TYPE_ARRAY_NOHTML,
		'transonly'     => vB_Cleaner::TYPE_BOOL,
		'product'       => vB_Cleaner::TYPE_STR,
	));

	// get all languages
	$languageselect = array(-10 => $vbphrase['all_languages']);

	if ($vb5_config['Misc']['debug'])
	{
		$languageselect["$vbphrase[developer_options]"] = array(
			-1 => $vbphrase['master_language'] . ' (-1)',
			0  => $vbphrase['custom_language'] . ' (0)'
		);
	}

	$languageselectall = vB_Api::instance('language')->fetchAll();
	
	if(isset($languageselectall['errors']))
	{
		print_stop_message2($languageselectall['errors'][0]);
	}
	
	foreach ($languageselectall AS $id => $infos)
	{
		$languageselect["$vbphrase[translations]"]["$id"] = $infos['title'];
	}

	// get all phrase types
	$phrasetypes = array('' => '');

	//$phrasetypes_result = $vbulletin->db->query_read("SELECT fieldname, title FROM " . TABLE_PREFIX . "phrasetype ORDER BY title");
	$phrasetypes_result = vB::getDbAssertor()->assertQuery('phrasetype',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT),
		array('field' => 'title', 'direction' => vB_dB_Query::SORT_ASC)
	);
	//while ($phrasetype = $vbulletin->db->fetch_array($phrasetypes_result))
	foreach ($phrasetypes_result AS $phrasetype)
	{
		$phrasetypes["$phrasetype[fieldname]"] = $phrasetype['title'];
	}

	print_form_header('phrase', 'dosearch');
	print_table_header($vbphrase['search_in_phrases_gcphome']);
	print_input_row($vbphrase['search_for_text'], 'searchstring', $vbulletin->GPC['searchstring'], 1, 50);
	print_select_row($vbphrase['search_in_language'], 'languageid', $languageselect, $vbulletin->GPC['languageid']);
	print_select_row($vbphrase['product'], 'product', array('' => $vbphrase['all_products']) + fetch_product_list(), $vbulletin->GPC['product']);
	print_yes_no_row($vbphrase['search_translated_phrases_only'], 'transonly', $vbulletin->GPC['transonly']);
	print_select_row($vbphrase['phrase_type'], 'phrasetype[]', $phrasetypes, $vbulletin->GPC['phrasetype'], false, 10, true);

	$where = array("{$vbulletin->GPC['searchwhere']}" => ' checked="checked"');
	print_label_row(construct_phrase($vbphrase['search_in_x'], '...'),'
		<label for="rb_sw_0"><input type="radio" name="searchwhere" id="rb_sw_0" value="0" tabindex="1"' . $where[0] . ' />' . $vbphrase['phrase_text_only'] . '</label><br />
		<label for="rb_sw_1"><input type="radio" name="searchwhere" id="rb_sw_1" value="1" tabindex="1"' . $where[1] . ' />' . $vbphrase['phrase_name_only'] . '</label><br />
		<label for="rb_sw_10"><input type="radio" name="searchwhere" id="rb_sw_10" value="10" tabindex="1"' . $where[10] . ' />' . $vbphrase['phrase_text_and_phrase_name'] . '</label>', '', 'top', 'searchwhere');
	print_yes_no_row($vbphrase['case_sensitive'], 'casesensitive', $vbulletin->GPC['casesensitive']);
	print_yes_no_row($vbphrase['exact_match'], 'exactmatch', $vbulletin->GPC['exactmatch']);
	print_submit_row($vbphrase['find']);

	unset($languageselect[-10], $languageselect[-1], $languageselect[0]);
	// search & replace
	print_form_header('phrase', 'replace', 0, 1, 'srform');
	print_table_header($vbphrase['find_and_replace_in_languages']);
	print_select_row($vbphrase['search_in_language'], 'languageid', $languageselect);
	print_textarea_row($vbphrase['search_for_text'], 'searchstring', '', 5, 60, 1, 0);
	print_textarea_row($vbphrase['replace_with_text'], 'replacestring', '', 5, 60, 1, 0);
	print_submit_row($vbphrase['replace']);

}

// #############################################################################

if ($_POST['do'] == 'doreplace')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'replace'       => vB_Cleaner::TYPE_ARRAY_UINT,
		'searchstring'  => vB_Cleaner::TYPE_STR,
		'replacestring' => vB_Cleaner::TYPE_STR,
		'languageid'    => vB_Cleaner::TYPE_INT
	));

	if (empty($vbulletin->GPC['replace']))
	{
		print_stop_message2('please_complete_required_fields');
	}

	$products_to_export = vB_Api::instanceInternal('phrase')->replace(
		array_keys($vbulletin->GPC['replace']),
		$vbulletin->GPC['searchstring'],
		$vbulletin->GPC['replacestring'],
		$vbulletin->GPC['languageid']
	);

	if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT AND !empty($products_to_export))
	{
		require_once(DIR . '/includes/functions_filesystemxml.php');
		foreach($products_to_export as $product)
		{
			autoexport_write_language($vbulletin->GPC['languageid'], $product);
		}
	}
	$args = array();
	parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
	$args['do'] = 'rebuild';
	$args['goto'] = urlencode("phrase.php?" . vB::getCurrentSession()->get('sessionurl') . "do=search");
	exec_header_redirect2('language', $args);
}

// #############################################################################

if ($_POST['do'] == 'replace')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'searchstring'  => vB_Cleaner::TYPE_STR,
		'replacestring' => vB_Cleaner::TYPE_STR,
		'languageid'    => vB_Cleaner::TYPE_INT
	));

	if (empty($vbulletin->GPC['searchstring']) OR empty($vbulletin->GPC['replacestring']))
	{
		print_stop_message2('please_complete_required_fields');
	}

	// do a rather clever query to find what phrases to display
	$phrases = vB::getDbAssertor()->assertQuery('fetchPhrasesForDisplay',
			array('searchstring' => $vbulletin->GPC['searchstring'], 'languageid' => $vbulletin->GPC['languageid'])
	);

	$phrasearray = array();

	foreach ($phrases as $phrase)
	{
		$phrasearray["$phrase[fieldname]"]["$phrase[varname]"]["$phrase[languageid]"] = $phrase;
	}
	unset($phrase);

	if (empty($phrasearray))
	{
		print_stop_message2('no_phrases_matched_your_query');
	}

	$phrasetypes = vB_Api::instanceInternal('phrase')->fetch_phrasetypes();

	print_form_header('phrase', 'doreplace');
	print_table_header($vbphrase['search_results'], 4);

	construct_hidden_code('searchstring', $vbulletin->GPC['searchstring']);
	construct_hidden_code('replacestring', $vbulletin->GPC['replacestring']);
	construct_hidden_code('languageid', $vbulletin->GPC['languageid']);

	foreach($phrasearray AS $fieldname => $x)
	{
		// display the header for the phrasetype
		print_description_row(construct_phrase($vbphrase['x_phrases_containing_y'], $phrasetypes["$fieldname"]['title'], htmlspecialchars_uni($vbulletin->GPC['searchstring'])), 0, 4, 'thead" align="center');

		// sort the phrases alphabetically by $varname
		ksort($x);
		foreach($x AS $varname => $y)
		{
			foreach($y AS $phrase)
			{
				$cell = array();
				$cell[] = '<b>' . $varname . '</b>';
				$cell[] = '<span class="smallfont">' . fetch_language_type_string($phrase['languageid'], $phrase['title']) . '</span>';
				$cell[] = '<span class="smallfont">' . fetch_highlighted_search_results($vbulletin->GPC['searchstring'], $phrase['text'], false) . '</span>';
				$cell[] = "<input type=\"checkbox\" value=\"1\" name=\"replace[{$phrase['phraseid']}]\" />";
				print_cells_row($cell, 0, 0, -2);
			} // end foreach($y)
		} // end foreach($x)
	} // end foreach($phrasearray)
	//print_submit_row($vbphrase['replace'], '', 4);
	print_submit_row($vbphrase['replace'], '', 4, '', '<label for="cb_checkall"><input type="checkbox" name="allbox" id="cb_checkall" onclick="js_check_all(this.form)" />' . $vbphrase['check_uncheck_all'] . '</label>');
	//print_table_footer();
}

// #############################################################################

if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'fieldname'       => vB_Cleaner::TYPE_NOHTML,
		'pagenumber'      => vB_Cleaner::TYPE_UINT,
		'perpage'         => vB_Cleaner::TYPE_UINT,
		'sourcefieldname' => vB_Cleaner::TYPE_NOHTML,
	));
	try
	{
		$getvarname = vB_Api::instanceInternal('phrase')->delete($vbulletin->GPC['phraseid']);
		if (!empty($getvarname) AND defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT)
		{
			require_once(DIR . '/includes/functions_filesystemxml.php');
			autoexport_write_language($getvarname['languageid'], $getvarname['product']);
		}
		else
		{
			if (empty($getvarname))
			{
				print_stop_message2('invalid_phrase_specified');
			}
		}

		print_stop_message2('deleted_phrase_successfully', 'phrase', array(
			'fieldname' => $vbulletin->GPC['sourcefieldname'],
			"page" => $vbulletin->GPC['pagenumber'],
			"pp" => $vbulletin->GPC['perpage']
		));
	}
	catch (vB_Exception_Api $e)
	{
		$errors = $e->get_errors();
		$errors = array_pop($errors);
		print_stop_message2($errors[0]);
	}
}

// #############################################################################

if ($_POST['do'] == 'insert' OR $_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'fieldname'       => vB_Cleaner::TYPE_NOHTML,
		'oldfieldname'    => vB_Cleaner::TYPE_NOHTML,
		'languageid'      => vB_Cleaner::TYPE_INT,
		'oldvarname'      => vB_Cleaner::TYPE_STR,
		'varname'         => vB_Cleaner::TYPE_STR,
		'text'            => vB_Cleaner::TYPE_ARRAY_NOTRIM,
		'ismaster'        => vB_Cleaner::TYPE_INT,
		'sourcefieldname' => vB_Cleaner::TYPE_NOHTML,
		't'               => vB_Cleaner::TYPE_BOOL,
		'product'         => vB_Cleaner::TYPE_STR,
		'pagenumber'      => vB_Cleaner::TYPE_UINT,
		'perpage'         => vB_Cleaner::TYPE_UINT,
	));

	try {
		if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT AND $vbulletin->GPC['ismaster'])
		{
			$old_product = vB::getDbAssertor()->getRow('phrase',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'languageid' => -1,
					'varname' => $vbulletin->GPC['oldvarname'],
					'fieldname' => $vbulletin->GPC['oldfieldname']
				)
			);
		}
		if ($_POST['do'] == 'update')
		{
			$vbulletin->GPC['ismaster'] = ($vbulletin->GPC['languageid'] == -1) ? true : false;
		}
		vB_Api::instanceInternal('phrase')->save(
			$vbulletin->GPC['fieldname'],
			$vbulletin->GPC['varname'],
			array(
				'text' => $vbulletin->GPC['text'],
				'oldvarname' => $vbulletin->GPC['oldvarname'],
				'oldfieldname' => $vbulletin->GPC['oldfieldname'],
				't' => $vbulletin->GPC['t'],
				'ismaster' => $vbulletin->GPC['ismaster'],
				'product' => $vbulletin->GPC['product'],
			)
		);
		if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT AND $vbulletin->GPC['ismaster'])
		{
			require_once(DIR . '/includes/functions_filesystemxml.php');

			$products_to_export = array( $vbulletin->GPC['product']);
			if (isset($old_product['product']))
			{
				$products_to_export[] = $old_product['product'];
			}
			autoexport_write_language(-1, $products_to_export);
		}

	}
	catch (vB_Exception_Api $e)
	{
		$errors = $e->get_errors();
		if (!empty($errors))
		{
			$error = array_pop($errors);
			print_stop_message2($error);
		}
		print_stop_message2('error');
	}

	print_stop_message2(array('saved_phrase_x_successfully', $vbulletin->GPC['varname']),'phrase', array(
		'fieldname' => $vbulletin->GPC['sourcefieldname'],
		'page' => $vbulletin->GPC['pagenumber'],
		"pp" => $vbulletin->GPC['perpage']
	));

}

// #############################################################################

if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{
?>
<script type="text/javascript">
function copy_default_text(targetlanguage)
{
	var deftext = fetch_object("default_phrase").value
	if (deftext == "")
	{
		<?php echo "alert(\"$vbphrase[default_text_is_empty]\");"; ?>
	}
	else
	{
		fetch_object("text_" + targetlanguage).value = deftext;
	}
}
</script>
<?php
}

// #############################################################################

if ($_REQUEST['do'] == 'add')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'fieldname'       => vB_Cleaner::TYPE_NOHTML,
		'pagenumber'      => vB_Cleaner::TYPE_UINT,
		'perpage'         => vB_Cleaner::TYPE_UINT
	));

	// make phrasetype options
	$phrasetypes = vB_Api::instanceInternal('phrase')->fetch_phrasetypes();
	$typeoptions = array();
	$type_product_options = array();
	foreach($phrasetypes AS $fieldname => $phrasetype)
	{
		$typeoptions["$fieldname"] = $phrasetype['title'];
		$type_product_options["$fieldname"] = $phrasetype['product'];
	}

	print_form_header('phrase', 'insert');
	print_table_header($vbphrase['add_new_phrase']);

	if ($vb5_config['Misc']['debug'])
	{
		print_yes_no_row(construct_phrase($vbphrase['insert_into_master_language_developer_option'], "<b></b>"), 'ismaster', iif($vb5_config['Misc']['debug'], 1, 0));
	}

	print_select_row($vbphrase['phrase_type'], 'fieldname', $typeoptions, $vbulletin->GPC['fieldname']);

	print_select_row($vbphrase['product'], 'product', fetch_product_list(), $type_product_options[$vbulletin->GPC['fieldname']]);

	// main input fields
	$resizer = "<div class=\"smallfont\"><a href=\"#\" onclick=\"return resize_textarea(1, 'default_phrase')\">$vbphrase[increase_size]</a> <a href=\"#\" onclick=\"return resize_textarea(-1, 'default_phrase')\">$vbphrase[decrease_size]</a></div>";

	print_input_row($vbphrase['varname'], 'varname', '', 1, 60);
	print_label_row(
		$vbphrase['text_gcpglobal'] . $resizer,
		"<textarea name=\"text[0]\" id=\"default_phrase\" rows=\"5\" cols=\"60\" wrap=\"virtual\" tabindex=\"1\" dir=\"ltr\"" . iif($vb5_config['Misc']['debug'], ' title="name=&quot;text[0]&quot;"') . "></textarea>",
		'', 'top', 'text[0]'
	);

	// do translation boxes
	print_table_header($vbphrase['translations']);
	print_description_row("
			<ul><li>$vbphrase[phrase_translation_desc_1]</li>
			<li>$vbphrase[phrase_translation_desc_2]</li>
			<li>$vbphrase[phrase_translation_desc_3]</li></ul>
		",
		0, 2, 'tfoot'
	);
	$languages = vB_Api::instanceInternal('language')->fetchAll();
	foreach($languages AS $_languageid => $lang)
	{
		$resizer = "<div class=\"smallfont\"><a href=\"#\" onclick=\"return resize_textarea(1, 'text_$_languageid')\">$vbphrase[increase_size]</a> <a href=\"#\" onclick=\"return resize_textarea(-1, 'text_$_languageid')\">$vbphrase[decrease_size]</a></div>";

		print_label_row(
			construct_phrase($vbphrase['x_translation'], "<b>$lang[title]</b>") . " <dfn>($vbphrase[optional])</dfn><br /><input type=\"button\" class=\"button\" value=\"$vbphrase[copy_default_text]\" tabindex=\"1\" onclick=\"copy_default_text($_languageid);\" />" . $resizer,
			"<textarea name=\"text[$_languageid]\" id=\"text_$_languageid\" rows=\"5\" cols=\"60\" tabindex=\"1\" wrap=\"virtual\" dir=\"$lang[direction]\"></textarea>"
		);
		print_description_row('<img src="' . vB::getDatastore()->getOption('bburl') . '/' . vB::getDatastore()->getOption('cleargifurl') . '" width="1" height="1" alt="" />', 0, 2, 'thead');
	}

	construct_hidden_code('page', $vbulletin->GPC['pagenumber']);
	construct_hidden_code('perpage', $vbulletin->GPC['perpage']);
	construct_hidden_code('sourcefieldname', $vbulletin->GPC['fieldname']);
	print_submit_row($vbphrase['save']);

}

// #############################################################################

if ($_REQUEST['do'] == 'edit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'e'          => vB_Cleaner::TYPE_ARRAY_ARRAY,
		'delete'     => vB_Cleaner::TYPE_ARRAY_ARRAY,
		'pagenumber' => vB_Cleaner::TYPE_UINT,
		'perpage'    => vB_Cleaner::TYPE_UINT,
		'fieldname'  => vB_Cleaner::TYPE_NOHTML,
		'varname'    => vB_Cleaner::TYPE_STR,
		't'          => vB_Cleaner::TYPE_BOOL,		// Display only the translations and no delete button
	));
	if (!empty($vbulletin->GPC['delete']))
	{
		$editvarname =& $vbulletin->GPC['delete'];
		$_REQUEST['do'] = 'delete';
	}
	else
	{
		$editvarname =& $vbulletin->GPC['e'];
	}

	// make phrasetype options
	$phrasetypes = vB_Api::instanceInternal('phrase')->fetch_phrasetypes();
	$typeoptions = array();
	foreach($phrasetypes AS $fieldname => $phrasetype)
	{
		$typeoptions["$fieldname"] = $phrasetype['title'];
	}

	if (!empty($editvarname))
	{
		foreach($editvarname AS $fieldname => $varnames)
		{
			foreach($varnames AS $varname => $type)
			{
				$varname = urldecode($varname);
				$phrase = vB::getDbAssertor()->getRow('fetchPhrassesByLanguage',
					array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
						'varname' => $varname,
						'fieldname' => $fieldname
					)
				);
				break;
			}
		}
	}
	else if ($vbulletin->GPC['phraseid'])
	{
		$phrase = vB::getDbAssertor()->getRow('phrase', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'phraseid' => $vbulletin->GPC['phraseid'],
		));
	}
	else if ($vbulletin->GPC['fieldname'] AND $vbulletin->GPC['varname'])
	{
		$varname = urldecode($vbulletin->GPC['varname']);
		$phrase = vB::getDbAssertor()->getRow('fetchPhrassesByLanguage',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'varname' => $varname,
				'fieldname' => $vbulletin->GPC['fieldname']
			)
		);
	}

	if (!$phrase['phraseid'] OR !$phrase['varname'])
	{
		print_stop_message2('no_phrases_matched_your_query');
	}

	if ($_REQUEST['do'] == 'delete')
	{
		$vbulletin->GPC['phraseid'] = $phrase['phraseid'];
	}
	else
	{
		// delete link
		if (($vb5_config['Misc']['debug'] OR $phrase['languageid'] != '-1') AND !$vbulletin->GPC['t'])
		{
			print_form_header('phrase', 'delete');
			construct_hidden_code('phraseid', $phrase['phraseid']);
			print_table_header($vbphrase['if_you_would_like_to_remove_this_phrase'] . ' &nbsp; &nbsp; <input type="submit" class="button" tabindex="1" value="' . $vbphrase['delete'] . '" />');
			print_table_footer();
		}

		//. '<input type="hidden" id="default_phrase" value="' . htmlspecialchars_uni($phrase['text']) . '" />'

		print_form_header('phrase', 'update', false, true, 'phraseform');

		print_table_header(construct_phrase($vbphrase['x_y_id_z'], iif(
			$phrase['languageid'] == 0,
			$vbphrase['custom_phrase'],
			$vbphrase['standard_phrase']
		), $phrase['varname'], $phrase['phraseid']));
		construct_hidden_code('oldvarname', $phrase['varname']);
		construct_hidden_code('t', $vbulletin->GPC['t']);

		if ($vb5_config['Misc']['debug'])
		{
			print_select_row($vbphrase['language'], 'languageid', array('-1' => $vbphrase['master_language'], '0' => $vbphrase['custom_language']), $phrase['languageid']);
			construct_hidden_code('oldfieldname', $phrase['fieldname']);
			print_select_row($vbphrase['phrase_type'], 'fieldname', $typeoptions, $phrase['fieldname']);
		}
		else
		{
			construct_hidden_code('languageid', $phrase['languageid']);
			construct_hidden_code('oldfieldname', $phrase['fieldname']);
			construct_hidden_code('fieldname', $phrase['fieldname']);
		}

		print_select_row($vbphrase['product'], 'product', fetch_product_list(), $phrase['product']);

		if (($phrase['languageid'] == 0 OR $vb5_config['Misc']['debug']) AND !$vbulletin->GPC['t'])
		{
			$resizer = "<div class=\"smallfont\"><a href=\"#\" onclick=\"return resize_textarea(1, 'default_phrase')\">$vbphrase[increase_size]</a> <a href=\"#\" onclick=\"return resize_textarea(-1, 'default_phrase')\">$vbphrase[decrease_size]</a></div>";

			print_input_row($vbphrase['varname'], 'varname', $phrase['varname'], 1, 50);
			print_label_row(
				$vbphrase['text_gcpglobal'] . $resizer,
				"<textarea name=\"text[0]\" id=\"default_phrase\" rows=\"4\" cols=\"50\" wrap=\"virtual\" tabindex=\"1\" dir=\"ltr\"" . iif($vb5_config['Misc']['debug'], ' title="name=&quot;text[0]&quot;"') . ">" . htmlspecialchars_uni($phrase['text']) . "</textarea>",
				'', 'top', 'text[0]'
			);
		}
		else
		{
			print_label_row($vbphrase['varname'], '$vbphrase[<b>' . $phrase['varname'] . '</b>]');
			construct_hidden_code('varname', $phrase['varname']);

			print_label_row($vbphrase['text_gcpglobal'], nl2br(htmlspecialchars_uni($phrase['text'])) . '<input type="hidden" id="default_phrase" value="' . htmlspecialchars_uni($phrase['text']) . '" />');
			if (!$vbulletin->GPC['t'])
			{
				construct_hidden_code('text[0]', $phrase['text']);
			}
		}

		// do translation boxes
		print_table_header($vbphrase['translations']);
		print_description_row("
				<ul><li>$vbphrase[phrase_translation_desc_1]</li>
				<li>$vbphrase[phrase_translation_desc_2]</li>
				<li>$vbphrase[phrase_translation_desc_3]</li></ul>
			",
			0, 2, 'tfoot'
		);

			$translations = vB::getDbAssertor()->assertQuery('phrase',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'varname', 'value' => $phrase['varname'], 'operator' => vB_dB_Query::OPERATOR_EQ),
					array('field' => 'languageid', 'value' => $phrase['languageid'], 'operator' => vB_dB_Query::OPERATOR_NE),
					array('field' => 'fieldname', 'value' => $phrase['fieldname'], 'operator' => vB_dB_Query::OPERATOR_EQ),
				)
			)
		);
		//while ($translation = $vbulletin->db->fetch_array($translations))
		if ($translations AND $translations->valid())
		{
			foreach ($translations AS $translation)
			{
				$text["{$translation['languageid']}"] = $translation['text'];
			}
		}
		// remove escape junk from javascript phrases for nice editable look
		fetch_js_unsafe_string($text);

		$languages = vB_Api::instanceInternal('language')->fetchAll();
		foreach($languages AS $_languageid => $lang)
		{
			$resizer = "<div class=\"smallfont\"><a href=\"#\" onclick=\"return resize_textarea(1, 'text_$_languageid')\">$vbphrase[increase_size]</a> <a href=\"#\" onclick=\"return resize_textarea(-1, 'text_$_languageid')\">$vbphrase[decrease_size]</a></div>";

			print_label_row(
				construct_phrase($vbphrase['x_translation'], "<b>$lang[title]</b>") . " <dfn>($vbphrase[optional])</dfn><br /><input type=\"button\" class=\"button\" value=\"$vbphrase[copy_default_text]\" tabindex=\"1\" onclick=\"copy_default_text($_languageid);\" />" . $resizer,
				"<textarea name=\"text[$_languageid]\" id=\"text_$_languageid\" rows=\"5\" cols=\"60\" tabindex=\"1\" wrap=\"virtual\" dir=\"$lang[direction]\">" . htmlspecialchars_uni($text["$_languageid"]) . "</textarea>"
			);
			print_description_row('<img src="../' . vB::getDatastore()->getOption('cleargifurl') . '" width="1" height="1" alt="" />', 0, 2, 'thead');
		}

		construct_hidden_code('page', $vbulletin->GPC['pagenumber']);
		construct_hidden_code('perpage', $vbulletin->GPC['perpage']);
		construct_hidden_code('sourcefieldname', $vbulletin->GPC['fieldname']);
		print_submit_row($vbphrase['save']);
	}
}

// #############################################################################

if ($_REQUEST['do'] == 'delete')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pagenumber' => vB_Cleaner::TYPE_UINT,
		'perpage'    => vB_Cleaner::TYPE_UINT,
		'fieldname'  => vB_Cleaner::TYPE_NOHTML,
	));

	//Check if Phrase belongs to Master Language -> only able to delete if $vbulletin->debug=1
	$getvarname = vB::getDbAssertor()->getRow('phrase', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'phraseid' => $vbulletin->GPC['phraseid'],
	));

	/**TODO
	 * This query should be checked; languageid = '-1' seems weird; string or integer ?
	 */
	$conditions = array();
	$conditions[] = array('field' => 'varname', 'value' => $getvarname['varname'], 'operator' => vB_dB_Query::OPERATOR_EQ);
	$conditions[] = array('field' => 'languageid', 'value' => '-1', 'operator' => vB_dB_Query::OPERATOR_EQ);
	if($getvarname['fieldname'])
	{
		$conditions[] = array('field' => 'fieldname', 'value' => $getvarname['fieldname'], 'operator' => vB_dB_Query::OPERATOR_EQ);
	}
	$ismasterphrase = vB::getDbAssertor()->getRow('phrase',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, vB_dB_Query::CONDITIONS_KEY => $conditions)
	);
	if (!$vb5_config['Misc']['debug'] AND $ismasterphrase)
	{
		print_stop_message2('cant_delete_master_phrase');
	}

	print_delete_confirmation('phrase', $vbulletin->GPC['phraseid'], 'phrase', 'kill', 'phrase', array(
		'sourcefieldname' => $vbulletin->GPC['fieldname'],
		'fieldname'       => $getvarname['fieldname'],
		'pagenumber'      => $vbulletin->GPC['pagenumber'],
		'perpage'         => $vbulletin->GPC['perpage']
	), $vbphrase['if_you_delete_this_phrase_translations_will_be_deleted']);

}

// #############################################################################

if ($_REQUEST['do'] == 'modify')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'fieldname'  => vB_Cleaner::TYPE_NOHTML,
		'perpage'    => vB_Cleaner::TYPE_INT,
		'pagenumber' => vB_Cleaner::TYPE_INT,
		'showpt'     => vB_Cleaner::TYPE_ARRAY_UINT,
	));

/*if (empty($vbulletin->GPC['showpt']))
	{
		$vbulletin->GPC['showpt'] = array('master' => 1, 'custom' => 1);
	}
	$checked = array();
	foreach ($vbulletin->GPC['showpt'] AS $type => $yesno)
	{
		$checked["$type$yesno"] = ' checked="checked"';
	}*/

	$phrasetypes = vB_Api::instanceInternal('phrase')->fetch_phrasetypes();

	// make sure $fieldname is valid
	if ($vbulletin->GPC['fieldname'] != '' AND !isset($phrasetypes["{$vbulletin->GPC['fieldname']}"]))
	{
		$vbulletin->GPC['fieldname'] = 'global';
	}

	// check display values are valid
	if ($vbulletin->GPC['perpage'] < 1)
	{
		$vbulletin->GPC['perpage'] = 15;
	}
	if ($vbulletin->GPC['pagenumber'] < 1)
	{
		$vbulletin->GPC['pagenumber'] = 1;
	}

	// count phrases
	$countphrases = vB::getDbAssertor()->getRow('fetchCountPhrasesByLang',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD, 'fieldname' => $vbulletin->GPC['fieldname'])
	);

	$numphrases =& $countphrases['total'];
	$numpages = ceil($numphrases / $vbulletin->GPC['perpage']);

	if ($numpages < 1)
	{
		$numpages = 1;
	}
	if ($vbulletin->GPC['pagenumber'] > $numpages)
	{
		$vbulletin->GPC['pagenumber'] = $numpages;
	}

	$showprev = false;
	$shownext = false;

	if ($vbulletin->GPC['pagenumber'] > 1)
	{
		$showprev = true;
	}
	if ($vbulletin->GPC['pagenumber'] < $numpages)
	{
		$shownext = true;
	}

	$pageoptions = array();
	for ($i = 1; $i <= $numpages; $i++)
	{
		$pageoptions["$i"] = "$vbphrase[page_gcpglobal] $i / $numpages";
	}

	$phraseoptions = array('' => $vbphrase['all_phrase_groups']);
	foreach($phrasetypes AS $fieldname => $type)
	{
		$phraseoptions["$fieldname"] = $type['title'];
	}

	print_form_header('phrase', 'modify', false, true, 'navform', '90%', '', true, 'get');
	echo '
	<colgroup span="5">
		<col style="white-space:nowrap"></col>
		<col></col>
		<col width="100%" align="center"></col>
		<col style="white-space:nowrap"></col>
		<col></col>
	</colgroup>
	<tr>
		<td class="thead">' . $vbphrase['phrase_type'] . ':</td>
		<td class="thead"><select name="fieldname" class="bginput" tabindex="1" onchange="this.form.page.selectedIndex = 0; this.form.submit()">' . construct_select_options($phraseoptions, $vbulletin->GPC['fieldname']) . '</select></td>
		<td class="thead">' .
			'<input type="button"' . iif(!$showprev, ' disabled="disabled"') . ' class="button" value="&laquo; ' . $vbphrase['prev'] . '" tabindex="1" onclick="this.form.page.selectedIndex -= 1; this.form.submit()" />' .
			'<select name="page" tabindex="1" onchange="this.form.submit()" class="bginput">' . construct_select_options($pageoptions, $vbulletin->GPC['pagenumber']) . '</select>' .
			'<input type="button"' . iif(!$shownext, ' disabled="disabled"') . ' class="button" value="' . $vbphrase['next'] . ' &raquo;" tabindex="1" onclick="this.form.page.selectedIndex += 1; this.form.submit()" />
		</td>
		<td class="thead">' . $vbphrase['phrases_to_show_per_page'] . ':</td>
		<td class="thead"><input type="text" class="bginput" name="perpage" value="' . $vbulletin->GPC['perpage'] . '" tabindex="1" size="5" /></td>
		<td class="thead"><input type="submit" class="button" value=" ' . $vbphrase['go'] . ' " tabindex="1" accesskey="s" /></td>
	</tr>';
	print_table_footer();

	/*print_form_header('phrase', 'modify');
	print_table_header($vbphrase['controls'], 3);
	echo '
	<tr>
		<td class="tfoot">
			<select name="fieldname" class="bginput" tabindex="1" onchange="this.form.page.selectedIndex = 0; this.form.submit()">' . construct_select_options($phraseoptions, $vbulletin->GPC['fieldname']) . '</select><br />
			<table cellpadding="0" cellspacing="0" border="0">
			<tr>
				<td><b>Show Master Phrases?</b> &nbsp; &nbsp; &nbsp;</td>
				<td><label for="rb_smy"><input type="radio" name="showpt[master]" id="rb_smy" value="1"' . $checked['master1'] . ' />' . $vbphrase['yes'] . '</label></td>
				<td><label for="rb_smn"><input type="radio" name="showpt[master]" id="rb_smn" value="0"' . $checked['master0'] . ' />' . $vbphrase['no'] . '</label></td>
			</tr>
			<tr>
				<td><b>Show Custom Phrases?</b> &nbsp; &nbsp; &nbsp;</td>
				<td><label for="rb_scy"><input type="radio" name="showpt[custom]" id="rb_scy" value="1"' . $checked['custom1'] . ' />' . $vbphrase['yes'] . '</label></td>
				<td><label for="rb_scn"><input type="radio" name="showpt[custom]" id="rb_scn" value="0"' . $checked['custom0'] . ' />' . $vbphrase['no'] . '</label></td>
			</tr>
			</table>
		</td>
		<td class="tfoot" align="center">
			<div style="margin-bottom:4px"><b>' . $vbphrase['phrases_to_show_per_page'] . ':</b> <input type="text" class="bginput" name="perpage" value="' . $vbulletin->GPC['perpage'] . '" tabindex="1" size="5" /></div>
			<input type="button"' . iif(!$showprev, ' disabled="disabled"') . ' class="button" value="&laquo; ' . $vbphrase['prev'] . '" tabindex="1" onclick="this.form.page.selectedIndex -= 1; this.form.submit()" />' .
			'<select name="page" tabindex="1" onchange="this.form.submit()" class="bginput">' . construct_select_options($pageoptions, $vbulletin->GPC['pagenumber']) . '</select>' .
			'<input type="button"' . iif(!$shownext, ' disabled="disabled"') . ' class="button" value="' . $vbphrase['next'] . ' &raquo;" tabindex="1" onclick="this.form.page.selectedIndex += 1; this.form.submit()" />
		</td>
		<td class="tfoot" align="center"><input type="submit" class="button" value=" ' . $vbphrase['go'] . ' " tabindex="1" accesskey="s" /></td>
	</tr>
	';
	print_table_footer();*/

	print_phrase_ref_popup_javascript();

	?>
	<script type="text/javascript">
	<!--
	function js_edit_phrase(id)
	{
		window.location = "phrase.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=edit&phraseid=" + id;
	}
	// -->
	</script>
	<?php

		$masterphrases = vB::getDbAssertor()->assertQuery('fetchPhrasesOrderedPaged',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'fieldname' => $vbulletin->GPC['fieldname'],
			vB_dB_Query::PARAM_LIMITPAGE => ($vbulletin->GPC['pagenumber'] - 1),
			vB_dB_Query::PARAM_LIMIT => $vbulletin->GPC['perpage']
		)
	);
	$phrasenames = array();
	//while ($masterphrase = $vbulletin->db->fetch_array($masterphrases))
	if ($masterphrases AND $masterphrases->valid())
	{
		foreach ($masterphrases AS $masterphrase)
		{
			$phrasenames [] = array('varname' => $masterphrase['varname'], 'fieldname' => $masterphrase['fieldname']);
		}

	}
	unset($masterphrase);
	//$vbulletin->db->free_result($masterphrases);

	$cphrases = array();
	if (!empty($phrasenames))
	{
		$phrases = vB::getDbAssertor()->assertQuery('fetchKeepNames', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'keepnames' => $phrasenames,
		));

		unset($phrasenames);
		foreach ($phrases as $phrase)
		{
			$cphrases["{$phrase['fieldname']}"]["{$phrase['varname']}"]["{$phrase['languageid']}"] = $phrase['phraseid'];
		}
		unset($phrase);
	}

	$languages = vB_Api::instanceInternal('language')->fetchAll();
	$numlangs = sizeof($languages);
	$colspan = $numlangs + 2;

	print_form_header('phrase', 'add', false, true, 'phraseform', '90%', '', true, 'post', 1);
	construct_hidden_code('fieldname', $vbulletin->GPC['fieldname']);

	echo "\t<colgroup span=\"" . (sizeof($languages) + 1) . "\"></colgroup>\n";
	echo "\t<col style=\"white-space:nowrap\"></col>\n";

	// show phrases
	foreach($cphrases AS $_fieldname => $varnames)
	{
		print_table_header(construct_phrase($vbphrase['x_phrases'], $phrasetypes["$_fieldname"]['title']) . " <span class=\"normal\">(fieldname = $_fieldname)</span>", $colspan);

		$headings = array($vbphrase['varname']);
		foreach($languages AS $_languageid => $language)
		{
			$headings[] = "<a href=\"javascript:js_open_phrase_ref($language[languageid],'$_fieldname');\" title=\"" . $vbphrase['view_quickref_glanguage'] . ": $language[title]\">$language[title]</a>";
		}
		$headings[] = '';
		print_cells_row($headings, 0, 'thead');

		ksort($varnames);
		foreach($varnames AS $varname => $phrase)
		{
			$cell = array(construct_wrappable_varname($varname, 'font-weight:bold;', 'smallfont', 'span'));
			if (isset($phrase['-1']))
			{
				$phraseid = $phrase['-1'];
				$custom = 0;
			}
			else

			{
				$phraseid = $phrase['0'];
				$custom = 1;
			}
			foreach(array_keys($languages) AS $_languageid)
			{
				$cell[] = "<img src=\"" . vB::getDatastore()->getOption('bburl') . "/cpstyles/" . vB::getDatastore()->getOption('cpstylefolder') . "/cp_tick_" . iif(isset($phrase["$_languageid"]), 'yes', 'no') . ".gif\" alt=\"\" />";
			}
			$cell[] = '<span class="smallfont">' . construct_link_code(fetch_tag_wrap($vbphrase['edit'], 'span class="col-i"', $custom==1), "phrase.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;phraseid=$phraseid&amp;page=" . $vbulletin->GPC['pagenumber'] . "&amp;pp=" . $vbulletin->GPC['perpage'] . "&amp;fieldname=" . $vbulletin->GPC['fieldname']) . iif($custom OR $vb5_config['Misc']['debug'], construct_link_code(fetch_tag_wrap($vbphrase['delete'], 'span class="col-i"', $custom==1), "phrase.php?" . vB::getCurrentSession()->get('sessionurl') . "do=delete&amp;phraseid=$phraseid&amp;page=" . $vbulletin->GPC['pagenumber'] . "&amp;pp=" . $vbulletin->GPC['perpage'] . "&amp;fieldname=" . $vbulletin->GPC['fieldname']), '') . '</span>';
			print_cells_row($cell, 0, 0, 0, 'top', 0);
		}
	}

	print_table_footer($colspan, "
		<input type=\"button\" class=\"button\" value=\"" . $vbphrase['search_in_phrases_glanguage'] . "\" tabindex=\"1\" onclick=\"window.location='phrase.php?" . vB::getCurrentSession()->get('sessionurl') . "&amp;do=search';\" />
		&nbsp; &nbsp;
		<input type=\"button\" class=\"button\" value=\"" . $vbphrase['add_new_phrase'] . "\" tabindex=\"1\" onclick=\"window.location='phrase.php?" . vB::getCurrentSession()->get('sessionurl') . "do=add&amp;fieldname=" . $vbulletin->GPC['fieldname'] . "&amp;page=" . $vbulletin->GPC['pagenumber'] . "&amp;pp=" . $vbulletin->GPC['perpage'] . "';\" />
		&nbsp; &nbsp;
		<input type=\"button\" class=\"button\" value=\"" . $vbphrase['find_orphan_phrases'] . "\" tabindex=\"1\" onclick=\"window.location='phrase.php?" . vB::getCurrentSession()->get('sessionurl') . "do=findorphans';\" />
	");


}

// #############################################################################

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 70677 $
|| ####################################################################
\*======================================================================*/
?>
