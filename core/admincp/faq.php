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
global $phrasegroups, $specialtemplates, $ifaqcache, $faqcache, $faqjumpbits, $faqparent, $vbphrase, $vbulletin, $bgcounter;

$phrasegroups = array('cphome', 'help_faq', 'fronthelp');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/functions_faq.php');
require_once(DIR . '/includes/functions_misc.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminfaq'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vb5_config =& vB::getConfig();
$assertor = vB::getDbAssertor();

print_cp_header($vbphrase['faq_manager_ghelp_faq']);

// #############################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// #############################################################################

if ($_POST['do'] == 'doupdatefaq')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'faq' => vB_Cleaner::TYPE_ARRAY_STR,
		'faqexists' => vB_Cleaner::TYPE_ARRAY_STR
	));

	// create an array of entries that are NOT to be deleted
	$retain_faq_items = array_diff($vbulletin->GPC['faqexists'], $vbulletin->GPC['faq']);

	// if there are items to delete...
	if (!empty($vbulletin->GPC['faq']))
	{
		// delete all items selected on previous form
		$assertor->delete('vBForum:faq', array('faqname' => $vbulletin->GPC['faq']));
		// search for any remaining items with faqparent = one of the deleted items
		$orphans_result = $assertor->assertQuery('vBForum:faq',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'faqparent', 'value' => $vbulletin->GPC['faq'], 'operator' => vB_dB_Query::OPERATOR_EQ),
						array('field' => 'faqname', 'value' => $vbulletin->GPC['faq'], 'operator' => vB_dB_Query::OPERATOR_NE)
					)
				)
		);
		if ( $orphans_result AND $orphans_result->valid() )
		{
			$orphans = array();

			foreach ($orphans_result AS $current)
			{
				$orphans[] = $current['faqname'];
			}

			// update orphans to have vb_faq as their parent
			$assertor->assertQuery('vBForum:faq',
					array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
						'faqparent' => 'vb_faq',
						vB_dB_Query::CONDITIONS_KEY => array(
							array('field' => 'faqname', 'value' => $orphans, 'operator' => vB_dB_Query::OPERATOR_EQ)
						)
					)
			);
			$retain_faq_items[] = 'vb_faq';
		}
		else
		{
			// check to see if there are any remaining children of vb_faq
			$children = $assertor->assertQuery('vBForum:faq',
					array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						vB_dB_Query::CONDITIONS_KEY => array(
							array('field' => 'faqparent', 'value' => 'vb_faq', 'operator' => vB_dB_Query::OPERATOR_EQ),
							array('field' => 'faqname', 'value' => $vbulletin->GPC['faq'], 'operator' => vB_dB_Query::OPERATOR_NE)
						)
					)
			);
			if ( $children AND $children->valid() )
			{
				$retain_faq_items[] = 'vb_faq';
			}
			else
			{
				// no remaining children, delete vb_faq
				$assertor->assertQuery('vBForum:faq', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, 'faqname' => 'vb_faq'));
			}
		}
	}

	// set remaining old default FAQ items to volatile=0 - decouple from vBulletin default
	$response = $assertor->assertQuery('vBForum:faq',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					 'volatile' => 0,
					 vB_dB_Query::CONDITIONS_KEY => array(
						 array('field' => 'volatile', 'value' => 1, 'operator' => vB_dB_Query::OPERATOR_EQ),
						 array('field' => 'faqname', 'value' => 'vb_', 'operator' => vB_dB_Query::OPERATOR_BEGINS)
					 )
				)
	);

	// set remaining old default FAQ phrases to languageid=0 - decouple from vBulletin master language
	$response = $assertor->assertQuery('vBForum:phrase',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					'languageid' => 0,
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'languageid', 'value' => -1, 'operator' => vB_dB_Query::OPERATOR_EQ),
						array('field' => 'fieldname', 'value' => array('faqtitle', 'faqtext'), 'operator' => vB_dB_Query::OPERATOR_EQ),
						array('field' => 'varname', 'value' => 'vb_', 'operator' => vB_dB_Query::OPERATOR_BEGINS)
					)
				)
	);


	print_stop_message2('deleted_faq_item_successfully','index');
}

if ($_REQUEST['do'] == 'updatefaq')
{
	function fetch_faq_checkbox_tree($parent = 0)
	{
		global $ifaqcache, $faqcache, $faqjumpbits, $faqparent, $vbphrase, $vbulletin, $parentlist;
		static $output = '';

		if ($parentlist === null)
		{
			$parentlist = $parent;
		}

		if (!is_array($ifaqcache))
		{
			cache_ordered_faq(true, false, -1);
		}

		if (!is_array($ifaqcache["$parent"]))
		{
			return;
		}

		$output .= "<ul id=\"li_$parent\">";

		foreach($ifaqcache["$parent"] AS $key1 => $faq)
		{
			if ($faq['volatile'])
			{
				$checked = ' checked="checked"';
				$class = '';
			}
			else
			{
				$checked = '';
				$class = ' class="customfaq"';
			}

			$output .= "<li>
				<label for=\"$faq[faqname]\"$class>" .
				"<input type=\"checkbox\" name=\"faq[$faq[faqname]]\" value=\"$faq[faqname]\"$checked id=\"$faq[faqname]\" title=\"$parentlist\" />"
				. ($faq['title'] ? $faq['title'] : $faq['faqname']) . "</label>";

			construct_hidden_code("faqexists[$faq[faqname]]", $faq['faqname']);

			if (is_array($ifaqcache["$faq[faqname]"]))
			{
				fetch_faq_checkbox_tree($faq['faqname']);
			}
			$output .= "</li>";
		}

		$output .= '</ul>';

		return $output;
	}

	?>
	<style type="text/css">
	#faqlist_checkboxes ul { list-style:none; }
	#faqlist_checkboxes li { margin-top:3px; }
	#faqlist_checkboxes label.customfaq { font-style:italic; }
	</style>
	<script type="text/javascript" src="<?php echo $vbulletin->options['bburl']?>/clientscript/yui/yahoo-dom-event/yahoo-dom-event.js?v=<?php echo SIMPLE_VERSION; ?>"></script>
	<script type="text/javascript">
	<!--

	function is_checkbox(element)
	{
		return (element.type == "checkbox");
	}

	function toggle_children()
	{
		var checkboxes, i;

		checkboxes = YAHOO.util.Dom.getElementsBy(is_checkbox, "input", "li_" + this.id);
		for (i = 0; i < checkboxes.length; i++)
		{
			checkboxes[i].checked = this.checked;
		}
	}

	var checkboxes = YAHOO.util.Dom.getElementsBy(is_checkbox, "input", "faqlist_checkboxes");
	for (var i = 0; i < checkboxes.length; i++)
	{
		YAHOO.util.Event.on(checkboxes[i], "click", toggle_children);
	}

	//-->
	</script>
	<?php

	$data = '<div id="faqlist_checkboxes">';
	$data .= fetch_faq_checkbox_tree('vb_faq');
	$data .= '</div>';

	print_form_header('faq', 'doupdatefaq');
	print_table_header($vbphrase['delete_old_faq']);
	print_description_row($vbphrase['delete_old_faq_desc']);
	print_description_row($data);
	print_submit_row($vbphrase['delete'], $vbphrase['reset']);

}

if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'faqname' => vB_Cleaner::TYPE_STR
	));

	// get list of items to delete
	$faqDeleteNames = fetch_faq_delete_list($vbulletin->GPC['faqname']);
	$phraseDeleteNamesSql = array();
	foreach ($faqDeleteNames as $name)
	{
		$phraseDeleteNamesSql[] = $name . '_gfaqtitle';
		$phraseDeleteNamesSql[] = $name . '_gfaqtext';
	}

	// delete faq
	$res = $assertor->assertQuery('vBForum:faq', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, 'faqname' => $faqDeleteNames));

	if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT)
	{
		// get phrases to delete
		if (!empty($phraseDeleteNamesSql))
		{
			$set = $assertor->assertQuery('vBForum:getDistinctProduct', array(vB_dB_Query::QUERY_STORED, 'phraseDeleteNamesSql' => $phraseDeleteNamesSql));
			$products_to_export = array();
			foreach ($set AS $row)
			{
				$products_to_export[$row['product']] = 1;
			}
		}
	}

	// delete phrases
	$res = $assertor->assertQuery('vBForum:phrase',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				vB_dB_Query::CONDITIONS_KEY=> array(
					array('field' => 'varname', 'value' => $phraseDeleteNamesSql, 'operator' => vB_dB_Query::OPERATOR_EQ),
					array('field' => 'fieldname', 'value' => array('faqtitle', 'faqtext'), 'operator' => vB_dB_Query::OPERATOR_EQ)
				)
			)
	);
	if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT)
	{
		require_once(DIR . '/includes/functions_filesystemxml.php');
		foreach(array_keys($products_to_export) as $product)
		{
			autoexport_write_faq_and_language(-1, $product);
		}
	}

	print_stop_message2('deleted_faq_item_successfully','faq', array('faq' => $faqcache[$vbulletin->GPC['faqname']]['faqparent']));
}

// #############################################################################

if ($_REQUEST['do'] == 'delete')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'faq' => vB_Cleaner::TYPE_STR
	));

	print_delete_confirmation('faq', $vbulletin->db->escape_string($vbulletin->GPC['faq']), 'faq', 'kill', 'faq_item', '', $vbphrase['please_note_deleting_this_item_will_remove_children']);
}

// #############################################################################

if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'faq' 			=> vB_Cleaner::TYPE_STR,
		'faqparent' 	=> vB_Cleaner::TYPE_STR,
		'deftitle'		=> vB_Cleaner::TYPE_STR,
		'deftext'		=> vB_Cleaner::TYPE_STR,
		'text'			=> vB_Cleaner::TYPE_ARRAY_STR,	// Originally NULL though not type checking incode, used as an array
	));

	if ($vbulletin->GPC['deftitle'] == '')
	{
		print_stop_message2('invalid_title_specified');
	}

	if (!preg_match('#^[a-z0-9_]+$#i', $vbulletin->GPC['faq']))
	{
		print_stop_message2('invalid_faq_varname');
	}

	if (!validate_string_for_interpolation($vbulletin->GPC['deftext']))
	{
		print_stop_message2('faq_text_not_safe');
	}

	foreach ($vbulletin->GPC['text'] AS $text)
	{
		if (!validate_string_for_interpolation($text))
		{
			print_stop_message2('faq_text_not_safe');
		}
	}

	if ($vbulletin->GPC['faqparent'] == $vbulletin->GPC['faq'])
	{
		print_stop_message2('cant_parent_faq_item_to_self');
	}
	else
	{
		$faqarray = array();
		$getfaqs = $assertor->assertQuery('vBForum:faq', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT));
		if ($getfaqs AND $getfaqs->valid())
		{
			foreach($getfaqs AS $current)
			{
				$faqarray["$current[faqname]"] = $current['faqparent'];
			}
		}

		$parent_item = $vbulletin->GPC['faqparent'];
		$i = 0;
		// Traverses up the parent list to check we're not moving an faq item to something already below it
		while ($parent_item != 'faqroot' AND $parent_item != '' AND $i++ < 100)
		{
			$parent_item = $faqarray["$parent_item"];
			if ($parent_item == $vbulletin->GPC['faq'])
			{
				print_stop_message2('cant_parent_faq_item_to_child');
			}
		}
	}

	$conditions = array();
	$conditions[] = array('field' => 'varname', 'value' => array($vbulletin->GPC['faq'] . '_gfaqtitle', $vbulletin->GPC['faq'] . '_gfaqtext'), 'operator' => vB_dB_Query::OPERATOR_EQ);
	if(!$vb5_config['Misc']['debug']){
		$conditions[] = array('field' => 'languageid','value' => -1, 'operator' => vB_dB_Query::OPERATOR_NE);
	}
	$res = $assertor->assertQuery('vBForum:phrase', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, vB_dB_Query::CONDITIONS_KEY => $conditions));

	if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT)
	{
		$old_products = $assertor->assertQuery('vBForum:faq',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'faqname' => $vbulletin->GPC['faq'])
		);
		if ($old_products AND $old_products->valid())
		{
			foreach ($old_products AS $current)
			{
				$old_product[] = $current['product'];
			}
		}
	}

	$res = $assertor->assertQuery('vBForum:faq', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, 'faqname' => $vbulletin->GPC['faq']));

	$_POST['do'] = 'insert';
}

// #############################################################################

if ($_POST['do'] == 'insert')
{
	$vars = array(
		'faq'			=> vB_Cleaner::TYPE_STR,
		'faqparent'		=> vB_Cleaner::TYPE_STR,
		'volatile'		=> vB_Cleaner::TYPE_INT,
		'product'		=> vB_Cleaner::TYPE_STR,
		'displayorder'		=> vB_Cleaner::TYPE_INT,
		'title'			=> vB_Cleaner::TYPE_ARRAY_STR,	// Originally NULL though not type checking incode, used as an array
		'text'			=> vB_Cleaner::TYPE_ARRAY_STR,	// Originally NULL though not type checking incode, used as an array
		'deftitle'		=> vB_Cleaner::TYPE_STR,
		'deftext'		=> vB_Cleaner::TYPE_STR
	);

	$vbulletin->input->clean_array_gpc('r', $vars);

	if ($vbulletin->GPC['deftitle'] == '')
	{
		print_stop_message2('invalid_title_specified');
	}

	if (!preg_match('#^[a-z0-9_]+$#i', $vbulletin->GPC['faq']))
	{
		print_stop_message2('invalid_faq_varname');
	}


	if (!validate_string_for_interpolation($vbulletin->GPC['deftext']))
	{
		print_stop_message2('faq_text_not_safe');
	}

	foreach ($vbulletin->GPC['text'] AS $text)
	{
		if (!validate_string_for_interpolation($text))
		{
			print_stop_message2('faq_text_not_safe');
		}
	}

	// ensure that the faq name is in 'word_word_word' format
	$fixedfaq = strtolower(preg_replace('#\s+#s', '_', $vbulletin->GPC['faq']));
	if ($fixedfaq !== $vbulletin->GPC['faq'])
	{
		print_form_header('faq', 'insert');
		print_table_header($vbphrase['faq_link_name_changed']);
		print_description_row(construct_phrase($vbphrase['to_maintain_compatibility_with_the_system_name_changed'], $vbulletin->GPC['faq'], $fixedfaq));
		print_input_row($vbphrase['varname'], 'faq', $fixedfaq);

		$vbulletin->GPC['faq'] = $fixedfaq;

		foreach(array_keys($vars) AS $varname_outer)
		{
			$var &= $vbulletin->GPC[$varname_outer];
			if (is_array($var))
			{
				foreach($var AS $varname_inner => $value)
				{
					construct_hidden_code($varname_outer . "[$varname_inner]", $value);
				}
			}
			else if ($vbulletin->GPC['varname'] != 'faq')
			{
				construct_hidden_code($varname_outer, $var);
			}
		}

		print_submit_row($vbphrase['continue'], 0, 2, $vbphrase['go_back']);

		print_cp_footer();
		exit;
	}

	$check = $assertor->assertQuery('vBForum:faq', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'faqname' => $vbulletin->GPC['faq']));
	if ($check AND $check->valid())
	{
		$current = $check->current();
		print_stop_message2(array('there_is_already_faq_item_named_x', $current['faqname']));
	}

	$conditions = array();
	$conditions[] = array('field' => 'varname','value' => $vbulletin->GPC['faq'], 'operator' => vB_dB_Query::OPERATOR_BEGINS);
	if(!$vb5_config['Misc']['debug']){
		$conditions[] = array('field' => 'languageid','value' => -1, 'operator'=> vB_dB_Query::OPERATOR_NE);
	}
	$check = $assertor->assertQuery('vBForum:phrase',
			 array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => $conditions
			 )
	);
	if ($check AND $check->valid())
	{
		$current = $check->current();
		$varname = $current['varname'];
		print_stop_message2(array('there_is_already_faq_item_named_x', $varname));
	}

	$faqname = $vbulletin->db->escape_string($vbulletin->GPC['faq']);

	// set base language versions
	$baselang = iif($vbulletin->GPC['volatile'], -1, 0);

	if ($baselang != -1 OR $vb5_config['Misc']['debug'])
	{
		// can't edit a master version if not in debug mode
		$vbulletin->GPC['title']["$baselang"] =& $vbulletin->GPC['deftitle'];
		$vbulletin->GPC['text']["$baselang"] =& $vbulletin->GPC['deftext'];
	}

	$full_product_info = fetch_product_list(true);
	$product_version = $full_product_info[$vbulletin->GPC['product']]['version'];

	$insertSql = array();

	foreach (array_keys($vbulletin->GPC['title']) AS $languageid)
	{
		$newtitle = trim($vbulletin->GPC['title']["$languageid"]);
		$newtext = trim($vbulletin->GPC['text']["$languageid"]);

		if ($newtitle OR $newtext)
		{
			$assertor->assertQuery('vBForum:phrase',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_MULTIPLEINSERT,
					vB_dB_Query::FIELDS_KEY => array('languageid', 'varname', 'text', 'fieldname', 'product', 'username', 'dateline', 'version'),
					vB_Db_Query::VALUES_KEY => array(
						array($languageid, $faqname . "_gfaqtitle", $newtitle, 'faqtitle', $vbulletin->GPC['product'], $vbulletin->userinfo['username'], vB::getRequest()->getTimeNow(), $product_version),
						array($languageid, $faqname . "_gfaqtext", $newtext, 'faqtext', $vbulletin->GPC['product'], $vbulletin->userinfo['username'], vB::getRequest()->getTimeNow(), $product_version)
					)
				)
			);
		}
	}

	/*insert query*/
	$set = $assertor->assertQuery('vBForum:replaceIntoFaq',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'faqname' => $faqname,
				'faqparent' => $vbulletin->GPC['faqparent'],
				'displayorder' => $vbulletin->GPC['displayorder'],
				'volatile' => $vbulletin->GPC['volatile'],
				'product' => $vbulletin->GPC['product']
			)
	);

	if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT)
	{
		require_once(DIR . '/includes/functions_filesystemxml.php');
		$products_to_export = array($vbulletin->GPC['product']);
		if (isset($old_product['product']))
		{
			$products_to_export[] = $old_product['product'];
		}
		autoexport_write_faq_and_language($baselang, $products_to_export);
	}

	print_stop_message2(array('saved_faq_x_successfully', $vbulletin->GPC['deftitle']),'faq', array('faq' => $vbulletin->GPC['faqparent']));
}

// #############################################################################

if ($_REQUEST['do'] == 'edit' OR $_REQUEST['do'] == 'add')
{
	require_once(DIR . '/includes/adminfunctions_language.php');

	$faqphrase = array();

	if ($_REQUEST['do'] == 'edit')
	{
		$vbulletin->input->clean_array_gpc('r', array(
			'faq' => vB_Cleaner::TYPE_STR
		));

		$faqs = $assertor->assertQuery('vBForum:faq', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'faqname' => $vbulletin->GPC['faq']));
		if ( (!$faqs) OR (!$faqs->valid()) )
		{
			print_stop_message2('no_matches_found_gerror');
		}
		else
		{
			$faq = $faqs->current();
		}

		$phrases = $assertor->assertQuery('vBForum:phrase',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY=> array(
					array('field'=>'varname','value'=>$vbulletin->GPC['faq'], 'operator'=> vB_dB_Query::OPERATOR_BEGINS)
				)
			)
		);
		if ( $phrases AND $phrases->valid() )
		{
			foreach ($phrases AS $phrase)
			{
				if ($phrase['fieldname'] == 'faqtitle')
				{
					$faqphrase["$phrase[languageid]"]['title'] = $phrase['text'];
				}
				else
				{
					$faqphrase["$phrase[languageid]"]['text'] = $phrase['text'];
				}
			}
		}

		print_form_header('faq', 'update');
		construct_hidden_code('faq', $faq['faqname']);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['faq_item'], $faqphrase['-1']['title'], $faq['faqname']));
	}
	else
	{
		$vbulletin->input->clean_array_gpc('r', array(
			'faq' => vB_Cleaner::TYPE_STR
		));

		$faq = array(
			'faqparent' => iif($vbulletin->GPC['faq'], $vbulletin->GPC['faq'], 'faqroot'),
			'displayorder' => 1,
			'volatile' => iif($vb5_config['Misc']['debug'], 1, 0)
		);

		?>
		<script type="text/javascript">
		<!--
		function js_check_shortname(theform, checkvb)
		{
			theform.faq.value = theform.faq.value.toLowerCase();

			for (i = 0; i < theform.faqparent.options.length; i++)
			{
				if (theform.faq.value == theform.faqparent.options[i].value)
				{
					alert(" <?php echo $vbphrase['sorry_there_is_already_an_item_called']; ?> '" + theform.faq.value + "'");
					return false;
				}
			}
			return true;
		}
		//-->
		</script>
		<?php

		print_form_header('faq', 'insert', 0, 1, 'cpform" onsubmit="return js_check_shortname(this, ' . iif($vb5_config['Misc']['debug'], 'false', 'true') . ');');
		print_table_header($vbphrase['add_new_faq_item_ghelp_faq']);
		print_input_row($vbphrase['varname'], 'faq', '', 0, '35" onblur="js_check_shortname(this.form, ' . iif($vb5_config['Misc']['debug'], 'false', 'true') . ');');
	}

	cache_ordered_faq();
	global $parentoptions;
	$parentoptions = array('faqroot' => $vbphrase['no_parent_faq_item']);
	fetch_faq_parent_options($faq['faqname']);
	print_select_row($vbphrase['parent_faq_item'], 'faqparent', $parentoptions, $faq['faqparent']);

	if (is_array($faqphrase['-1']))
	{
		$defaultlang = -1;
	}
	else
	{
		$defaultlang = 0;
	}

	if ($vb5_config['Misc']['debug'] OR $defaultlang == 0)
	{
		print_input_row($vbphrase['title'], 'deftitle', $faqphrase["$defaultlang"]['title'], 1, '70" style="width:100%');
		print_textarea_row($vbphrase['text_gcpglobal'], 'deftext', $faqphrase["$defaultlang"]['text'], 10, '70" style="width:100%');
	}
	else
	{
		construct_hidden_code('deftitle', $faqphrase["$defaultlang"]['title'], 1, 69);
		construct_hidden_code('deftext', $faqphrase["$defaultlang"]['text'], 10, 70);
		print_label_row($vbphrase['title'], htmlspecialchars($faqphrase["$defaultlang"]['title']));
		print_label_row($vbphrase['text_gcpglobal'], nl2br(htmlspecialchars($faqphrase["$defaultlang"]['text'])));
	}

	print_input_row($vbphrase['display_order'], 'displayorder', $faq['displayorder']);

	if ($vb5_config['Misc']['debug'])
	{
		print_yes_no_row($vbphrase['vbulletin_default'], 'volatile', $faq['volatile']);
	}
	else
	{
		construct_hidden_code('volatile', $faq['volatile']);
	}

	print_select_row($vbphrase['product'], 'product', fetch_product_list(), $faq['product']);

	print_table_header($vbphrase['translations']);
	$languages = fetch_languages_array();
	foreach($languages AS $languageid => $lang)
	{

		print_input_row("$vbphrase[title] <dfn>(" . construct_phrase($vbphrase['x_translation'], "<b>$lang[title]</b>") . ")</dfn>", "title[$languageid]", $faqphrase["$languageid"]['title'], 1, 69, 0, $lang['direction']);
		// reset bgcounter so that both entries are the same colour
		$bgcounter --;
		print_textarea_row("$vbphrase[text_gcpglobal] <dfn>(" . construct_phrase($vbphrase['x_translation'], "<b>$lang[title]</b>") . ")</dfn>", "text[$languageid]", $faqphrase["$languageid"]['text'], 4, 70, 1, 1, $lang['direction']);
		print_description_row('<img src="' . vB::getDatastore()->getOption('bburl') . '/' . $vbulletin->options['cleargifurl'] . '" width="1" height="1" alt="" />', 0, 2, 'thead');
	}

	print_submit_row($vbphrase['save']);
}

// #############################################################################

if ($_POST['do'] == 'updateorder')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'order' 	=> vB_Cleaner::TYPE_NOCLEAN,
		'faqparent'	=> vB_Cleaner::TYPE_STR
	));

	if (empty($vbulletin->GPC['order']) OR !is_array($vbulletin->GPC['order']))
	{
		print_stop_message2('invalid_array_specified');
	}

	$faqnames = array();
	$faqnamesNONEscaped = array();

	foreach($vbulletin->GPC['order'] AS $faqname => $displayorder)
	{
		$vbulletin->GPC['order']["$faqname"] = intval($displayorder);
		$faqnames[] = "'" . $vbulletin->db->escape_string($faqname) . "'";
		$faqnamesNONEscaped[] = $faqname;
	}

	$faqs = $assertor->assertQuery('vBForum:faq', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'faqname' => $faqnamesNONEscaped));
	if ( $faqs AND $faqs->valid() )
	{
		foreach($faqs AS $faq)
		{
			if ($faq['displayorder'] != $vbulletin->GPC['order']["$faq[faqname]"])
			{
				$response = $assertor->assertQuery('vBForum:faq',
					array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
						'displayorder' => $vbulletin->GPC['order']["$faq[faqname]"],
						vB_dB_Query::CONDITIONS_KEY => array(
							array('field'=>'faqname','value'=>$faq['faqname'], 'operator'=> vB_dB_Query::OPERATOR_EQ)
						)
					)
				);
			}
		}
	}

	if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT)
	{
		require_once(DIR . '/includes/functions_filesystemxml.php');
		$products = $assertor->assertQuery('vBForum:getDistinctProductFAQ', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'faqnames'=>implode(', ', $faqnames)));

		if ( $products AND $products->valid() )
		{
			foreach($products AS $product)
			{
				autoexport_write_faq($product['product']);
			}
		}
	}

	print_stop_message2('saved_display_order_successfully','faq', array('faq' => $vbulletin->GPC['faqparent']));
}

// #############################################################################

if ($_REQUEST['do'] == 'modify')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'faq' 	=> vB_Cleaner::TYPE_STR
	));

	$faqparent = iif(empty($vbulletin->GPC['faq']), 'faqroot', $vbulletin->GPC['faq']);
	cache_ordered_faq();
	if (!is_array($ifaqcache["$faqparent"]))
	{
		$faqparent = $faqcache["$faqparent"]['faqparent'];

		if (!is_array($ifaqcache["$faqparent"]))
		{
			print_stop_message2('invalid_faq_item_specified');
		}
	}
	global $parents;
	$parents = array();
	fetch_faq_parents($faqcache["$faqparent"]['faqname']);
	$parents = array_reverse($parents);
	$nav = "<a href=\"faq.php?" . vB::getCurrentSession()->get('sessionurl') . "\">$vbphrase[faq]</a>";
	if (!empty($parents))
	{
		$i = 1;
		foreach($parents AS $link => $name)
		{
			$nav .= '<br />' . str_repeat('&nbsp; &nbsp; ', $i) . iif(empty($link), $name, "<a href=\"$link\">$name</a>");
			$i ++;
		}
		$nav .= '
			<span class="smallfont">' .
			construct_link_code($vbphrase['edit'], "faq.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;faq=" . urlencode($faqparent)) .
			construct_link_code($vbphrase['add_child_faq_item'], "faq.php?" . vB::getCurrentSession()->get('sessionurl') . "do=add&amp;faq=" . urlencode($faqparent)) .
			construct_link_code($vbphrase['delete'], "faq.php?" . vB::getCurrentSession()->get('sessionurl') . "do=delete&amp;faq=" . urlencode($faqparent)) .
			'</span>';
	}

	print_form_header('faq', 'updateorder');
	construct_hidden_code('faqparent', $faqparent);
	print_table_header($vbphrase['faq_manager_ghelp_faq'], 3);
	print_description_row("<b>$nav</b>", 0, 3);
	print_cells_row(array($vbphrase['title'], $vbphrase['display_order'], $vbphrase['controls']), 1);

	foreach($ifaqcache["$faqparent"] AS $faq)
	{
		print_faq_admin_row($faq);
		if (is_array($ifaqcache["$faq[faqname]"]))
		{
			foreach($ifaqcache["$faq[faqname]"] AS $subfaq)
			{
				print_faq_admin_row($subfaq, '&nbsp; &nbsp; &nbsp;');
			}
		}
	}

	print_submit_row($vbphrase['save_display_order'], false, 3);
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>
