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
$phrasegroups = array('style');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/adminfunctions_template.php');
$assertor = vB::getDbAssertor();

$vbulletin->input->clean_array_gpc('r', array(
	'group'     => vB_Cleaner::TYPE_INT,
	'dostyleid' => vB_Cleaner::TYPE_INT,
	'dowhat'    => vB_Cleaner::TYPE_NOCLEAN // Sometimes this is an array and other times it is a string .. bad, bad.
));

// redirect back to template editor if required
if ($_REQUEST['do'] == 'edit' AND $vbulletin->GPC['dowhat'] == 'templateeditor')
{
	$args = array();
	parse_str(vB::getCurrentSession()->get('sessionurl_js'),$args);
	$args['do'] = 'modify';
	$args['group'] = $vbulletin->GPC['group'];
	$args['expandset'] = $vbulletin->GPC['dostyleid'];

	exec_header_redirect2('template', $args);
}

if ($_REQUEST['do'] == 'edit' AND $vbulletin->GPC['dowhat'] == 'stylevar')
{
	$args = array();
	parse_str(vB::getCurrentSession()->get('sessionurl_js'),$args);
	$args['dostyleid'] = $vbulletin->GPC['dostyleid'];

	exec_header_redirect2('stylevar', $args);
}

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminstyles'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'dostyleid'	=> vB_Cleaner::TYPE_INT
));
log_admin_action(iif($vbulletin->GPC['dostyleid'] != 0, "style id = " . $vbulletin->GPC['dostyleid']));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vb5_config =& vB::getConfig();

print_cp_header($vbphrase['style_manager_gstyle'], iif($_REQUEST['do'] == 'edit' OR $_REQUEST['do'] == 'doedit', 'init_color_preview()'));

?>
<script type="text/javascript" src="<?php echo $vbulletin->options['bburl']?>/clientscript/vbulletin_cpcolorpicker.js?v=<?php echo SIMPLE_VERSION; ?>"></script>
<?php

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}
else if ($_REQUEST['do'] == 'update')
{
	$vbulletin->nozip = true;
}


// ###################### Start Update Special Templates #######################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'dostyleid'       => vB_Cleaner::TYPE_INT,
		'group'           => vB_Cleaner::TYPE_STR,
		'css'             => vB_Cleaner::TYPE_ARRAY,
		'stylevar'        => vB_Cleaner::TYPE_ARRAY,
		'replacement'     => vB_Cleaner::TYPE_ARRAY,
		'commontemplate'  => vB_Cleaner::TYPE_ARRAY,
		'delete'          => vB_Cleaner::TYPE_ARRAY,
		'dowhat'          => vB_Cleaner::TYPE_ARRAY,
		'colorPickerType' => vB_Cleaner::TYPE_INT,
		'passthru_dowhat' => vB_Cleaner::TYPE_STR
	));

	if (empty($vbulletin->GPC['dostyleid']))
	{
		// probably lost due to Suhosin wiping out the variable
		print_stop_message2('variables_missing_suhosin');
	}
	else if ($vbulletin->GPC['dostyleid'] == -1)
	{
		$templates = $assertor->getRows('vBForum:getSpecialTemplates', array('styleid' => -1));
	}
	else
	{
		$style = $assertor->getRow('vBForum:style', array('styleid' => $vbulletin->GPC['dostyleid']));

		if (empty($style['templatelist']))
		{
			print_stop_message2('invalid_style_specified');
		}

		$queryParams = array();
		$templateids = array_values(unserialize($style['templatelist']));
		if (!$templateids)
		{
			// this used to cause an SQL error, this should work as an alternative
			$queryParams['styleid'] = $vbulletin->GPC['dostyleid'];
		}
		else
		{
			$queryParams['templateids'] = $templateids;
		}

		$templates = $assertor->getRows('vBForum:getSpecialTemplates', $queryParams);
	}

	$template_cache = array(
		'template'    => array(),
		'css'         => array(),
		'stylevar'    => array(),
		'replacement' => array()
	);

	foreach ($templates AS $template)
	{
		$template_cache["$template[templatetype]"]["$template[title]"] = $template;
	}

	// update templates
	if ($vbulletin->GPC['dowhat']['templates'] OR $vbulletin->GPC['dowhat']['posteditor'])
	{
		$templatequery = array();
		// Attempt to enable display_errors so that this eval actually returns something in the event of an error
		@ini_set('display_errors', true);

		foreach($vbulletin->GPC['commontemplate'] AS $templatetitle => $templatehtml)
		{
			if ($tquery = fetchTemplateUpdateSql($templatetitle, $templatehtml, $vbulletin->GPC['dostyleid'], $vbulletin->GPC['delete']))
			{
				$templatequery[] = $tquery;
			}
		}

		if (!empty($templatequery))
		{
			foreach($templatequery AS $query)
			{
				// is query method
				if (($query['params']) AND isset($query['params']['name']))
				{
					$assertor->assertQuery($query['queryname'], array($query['params']['name'] => $query['params']['value']));
				}
				else
				{
					$assertor->assertQuery($query['queryname'], $query['params']);
				}

			}
		}
	}

	// update stylevars
	if ($vbulletin->GPC['dowhat']['stylevars'])
	{
		build_special_templates($vbulletin->GPC['stylevar'], 'stylevar', 'stylevar');
	}

	// update css
	if ($vbulletin->GPC['dowhat']['css'])
	{
		build_special_templates($vbulletin->GPC['css'], 'css', 'css');
	}

	// update replacements
	if ($vbulletin->GPC['dowhat']['replacements'] AND is_array($vbulletin->GPC['replacement']) AND !empty($vbulletin->GPC['replacement']))
	{
		$temp = $vbulletin->GPC['replacement'];
		$vbulletin->GPC['replacement'] = array();
		foreach ($temp AS $key => $replacebits)
		{
			$vbulletin->GPC['replacement']["$replacebits[find]"] = $replacebits['replace'];
			$vbulletin->GPC['delete']['replacement']["$replacebits[find]"] = $vbulletin->GPC['delete']['replacement']["$key"];
		}
		build_special_templates($vbulletin->GPC['replacement'], 'replacement', 'replacement');
	}

	print_rebuild_style(
		$vbulletin->GPC['dostyleid'],
		iif($vbulletin->GPC['dostyleid'] == -1, $vbphrase['master_style'], $style['title']),
		$vbulletin->GPC['dowhat']['css'],
		$vbulletin->GPC['dowhat']['stylevars'],
		$vbulletin->GPC['dowhat']['replacements'],
		$vbulletin->GPC['dowhat']['posteditor']
	);

	$args = array();
	parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
	$args['do'] = 'edit';
	$args['dostyleid'] = $vbulletin->GPC['dostyleid'];
	$args['group'] = $vbulletin->GPC['group'];
	$args['dowhat'] = $vbulletin->GPC['passthru_dowhat'];
	$args['colorPickerType'] = $vbulletin->GPC['colorPickerType'];
	print_cp_redirect2('css', $args, 1);
}

// ###################### Start Choose What to Edit #######################
if ($_REQUEST['do'] == 'edit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'dostyleid' => vB_Cleaner::TYPE_INT,
		'group'     => vB_Cleaner::TYPE_STR,
		'dowhat'    => vB_Cleaner::TYPE_STR
	));

	if ($vbulletin->GPC['dostyleid'] == 0 OR $vbulletin->GPC['dostyleid'] < -1)
	{
		$vbulletin->GPC['dostyleid'] = 1;
	}

	if (!empty($vbulletin->GPC['dowhat']))
	{
		$_REQUEST['do'] = 'doedit';
	}
	else
	{
		if ($vbulletin->GPC['dostyleid'] == -1)
		{
			$style = array('styleid' => -1, 'title' => $vbphrase['master_style']);
		}
		else
		{
			$style = $assertor->getRow('vBForum:style', array('styleid' => $vbulletin->GPC['dostyleid']));
		}

		print_form_header('css', 'doedit', false, true, 'cpform', '90%', '', true, 'get');
		construct_hidden_code('dostyleid', $style['styleid']);
		construct_hidden_code('group', $vbulletin->GPC['group']);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['fonts_colors_etc'], $style['title'], $style['styleid']));
		print_yes_row($vbphrase['all_style_options'], 'dowhat', $vbphrase['yes'], true, 'all');
		print_yes_row($vbphrase['common_templates'], 'dowhat', $vbphrase['yes'], false, 'templates');
		print_yes_row($vbphrase['replacement_variables'], 'dowhat', $vbphrase['yes'], false, 'replacements');
		print_submit_row($vbphrase['go'], 0);
	}

}

// ###################### Start Edit CSS #######################
if ($_REQUEST['do'] == 'doedit')
{
    global $colorPickerType, $colorPickerWidth, $numcolors;
	$vbulletin->input->clean_array_gpc('r', array(
		'dostyleid'       => vB_Cleaner::TYPE_INT,
		'group'           => vB_Cleaner::TYPE_STR,
		'dowhat'          => vB_Cleaner::TYPE_STR,
		'colorPickerType' => vB_Cleaner::TYPE_INT
	));

	if ($vbulletin->GPC['dostyleid'] == 0 OR $vbulletin->GPC['dostyleid'] < -1)
	{
		print_stop_message2('invalid_style_specified');
	}

	$stylecache = vB_Library::instance('Style')->fetchStyles(false, false);

	if (!isset($stylecache[$vbulletin->GPC['dostyleid']]) AND !$vb5_config['Misc']['debug'])
	{
		print_stop_message2('invalid_style_specified');
	}

	?>
	<form action="css.php" method="get">
	<input type="hidden" name="s" value="<?php echo vB::getCurrentSession()->get('sessionhash'); ?>" />
	<input type="hidden" name="do" value="edit" />
	<input type="hidden" name="group" value="<?php echo htmlspecialchars_uni($vbulletin->GPC['group']); ?>" />
	<table cellpadding="0" cellspacing="0" border="0" width="90%" align="center">
	<tr valign="top">
		<td>

		<table cellpadding="4" cellspacing="1" border="0" class="tborder" width="300">
		<tr align="center">
			<td class="tcat"><b><?php echo construct_phrase($vbphrase['x_y_id_z'], $vbphrase['fonts_colors_etc'], iif($vbulletin->GPC['dostyleid'] == -1, $vbphrase['master_style'], $stylecache[$vbulletin->GPC['dostyleid']]['title']), $vbulletin->GPC['dostyleid']); ?></b></td>
		</tr>
		<tr>
			<td class="alt2" align="center">

			<select name="dostyleid" class="bginput" style="width:275px">
			<?php

			if ($vb5_config['Misc']['debug'])
			{
				echo "<option value=\"-1\"" . iif($vbulletin->GPC['dostyleid'] == -1, ' selected="selected"', '') . ">" . $vbphrase['master_style'] . "</option>\n";
			}
			foreach ($stylecache AS $style)
			{
				echo "<option value=\"$style[styleid]\"" . iif($style['styleid'] == $vbulletin->GPC['dostyleid'], ' selected="selected"', '') . ">" . construct_depth_mark($style['depth'], '--', '--') . " $style[title]</option>\n";
				$jsarray[] = "style[$style[styleid]] = \"" . addslashes_js($style['title'], '"') . "\";\n";
			}

			$optionselected[$vbulletin->GPC['dowhat']] = ' selected="selected"';

			?>
			</select>
			<br />
			<select name="dowhat" class="bginput" style="width:275px" onchange="this.form.submit()">
				<optgroup label="<?php echo $vbphrase['edit_fonts_colors_etc']; ?>">
					<option value="all"<?php echo $optionselected['all']; ?>><?php echo $vbphrase['all_style_options']; ?></option>
					<option value="templates"<?php echo $optionselected['templates']; ?>><?php echo $vbphrase['common_templates']; ?></option>
					<option value="stylevar"><?php echo $vbphrase['stylevars']; ?></option>
					<option value="replacements"<?php echo $optionselected['replacements']; ?>><?php echo $vbphrase['replacement_variables']; ?></option>
				</optgroup>
				<optgroup label="<?php echo $vbphrase['template_options']; ?>">
					<option value="templateeditor"><?php echo $vbphrase['edit_templates']; ?></option>
				</optgroup>
				<!-- <option value="<?php echo $vbulletin->GPC['dowhat']; ?>">&nbsp;</option> -->
			</select>

			</td>
		</tr>
		<tr>
			<td class="tfoot" align="center"><input type="submit" class="button" value="  <?php echo $vbphrase['go']; ?>  " /></td>
		</tr>
		</table>

		</td>
		<td align="<?php echo vB_Template_Runtime::fetchStyleVar('right'); ?>">

		<table cellpadding="4" cellspacing="1" border="0" class="tborder" width="300">
		<tr align="center">
			<td class="tcat"><b><?php echo $vbphrase['color_key']; ?></b></td>
		</tr>
		<tr>
			<td class="alt2">
			<div class="darkbg" style="margin: 4px; padding: 4px; border: 2px inset; text-align: ' . vB_Template_Runtime::fetchStyleVar('left') . '">
			<span class="col-g"><?php echo $vbphrase['template_is_unchanged_from_the_default_style']; ?></span><br />
			<span class="col-i"><?php echo $vbphrase['template_is_inherited_from_a_parent_style']; ?></span><br />
			<span class="col-c"><?php echo $vbphrase['template_is_customized_in_this_style']; ?></span>
			</div>
			</td>
		</tr>
		</table>

		</td>
	</tr>
	</table>
	</form>
	<script type="text/javascript">
	<!--
	function js_show_default_item(url, dolinks)
	{
		gotourl = "css.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=showdefault&dolinks=" + dolinks + "&" + url;
		if (dolinks==1)
		{
			wheight = 350;
		}
		else
		{
			wheight = 250;
		}
		window.open(gotourl, 'showdefault', 'resizable=yes,width=670,height=' + wheight);
	}
	var style = new Array();
	<?php echo implode('', $jsarray); ?>
	function js_show_style_info(styleid)
	{
		alert(construct_phrase("<?php echo $vbphrase['this_item_is_customized_in_the_parent_style_called_x']; ?>", style[styleid]));
	}

	<?php
	foreach (array(
		'css_value_invalid',
		'color_picker_not_ready',
	) AS $phrasename)
	{
			$JS_PHRASES[] = "\"$phrasename\" : \"" . fetch_js_safe_string($vbphrase["$phrasename"]) . "\"";
	}
	?>

	var vbphrase = {
		<?php echo implode(",\r\n\t", $JS_PHRASES) . "\r\n"; ?>
	};
	//-->
	</script>
	<?php

	if ($vbulletin->GPC['dostyleid'] == -1)
	{
		$templates = $assertor->getRows('vBForum:getSpecialTemplates', array('styleid' => -1));
	}
	else
	{
		$queryParams = array();
		$templateids = array_values(unserialize($stylecache[$vbulletin->GPC['dostyleid']]['templatelist']));
		if (!$templateids)
		{
			// this used to cause an SQL error, this should work as an alternative
			$queryParams['styleid'] = $vbulletin->GPC['dostyleid'];
		}
		else
		{
			$queryParams['templateids'] = $templateids;
		}

		$templates = $assertor->getRows('vBForum:getSpecialTemplates', $queryParams);
	}
	$template_cache = array();
	foreach ($templates AS $template)
	{
		$template_cache["$template[templatetype]"]["$template[title]"] = $template;
	}

	// get style options
	$stylevars = array();
	$stylevar_info = array();
	if (!empty($template_cache['stylevar']) AND is_array($template_cache['stylevar']))
	{
		foreach($template_cache['stylevar'] AS $title => $template)
		{
			$stylevars["$title"] = $template['template'];
			$stylevar_info["$title"] = $template['styleid'];
		}
	}

        // get css
	$css = array();
	foreach($template_cache['css'] AS $title => $template)
	{
		$css["$title"] = unserialize($template['template']);
		$css_info["$title"] = $template['styleid'];
	}

	// get replacements
	$replacement = array();
	if (is_array($template_cache['replacement']))
	{
		ksort($template_cache['replacement']);
		foreach($template_cache['replacement'] AS $title => $template)
		{
			$replacement["$title"] = $template['template'];
			$replacement_info["$title"] = $template['styleid'];
		}
	}

	$readonly = 0;

	// #############################################################################
	// start main form
	print_form_header('css', 'update', 0, 1, 'styleform');
	construct_hidden_code('dostyleid', $vbulletin->GPC['dostyleid']);
	construct_hidden_code('passthru_dowhat', $vbulletin->GPC['dowhat']);
	construct_hidden_code('group', $vbulletin->GPC['group']);

	// #############################################################################
	// build color picker if necessary
	if ($vbulletin->GPC['dowhat'] == 'all' OR $vbulletin->GPC['dowhat'] == 'css' OR $vbulletin->GPC['dowhat'] == 'maincss' OR $vbulletin->GPC['dowhat'] == 'posteditor')
	{
		$colorPicker = construct_color_picker(11);
	}
	else
	{
		$colorPicker = '';
	}

	// #############################################################################
	// COMMON TEMPLATES
	if ($vbulletin->GPC['dowhat'] == 'templates' OR $vbulletin->GPC['dowhat'] == 'all')
	{
		construct_hidden_code('dowhat[templates]', 1);
		print_table_header($vbphrase['common_templates']);
		print_common_template_row('header');
		print_common_template_row('footer');
		print_table_break(' ');
	}

	// #############################################################################
	// REPLACEMENT VARS
	if ($vbulletin->GPC['dowhat'] == 'replacements' OR $vbulletin->GPC['dowhat'] == 'all')
	{
		construct_hidden_code('dowhat[replacements]', 1);
		if (sizeof($replacement) > 0)
		{
			print_table_header($vbphrase['replacement_variables'], 3);
			print_cells_row(array($vbphrase['search_for_text'], $vbphrase['replace_with_text'], ''), 1);
			foreach($replacement AS $findword => $replaceword)
			{
				print_replacement_row($findword, $replaceword);
			}
		}
		else

		{
			print_description_row($vbphrase['no_replacements_defined']);
		}
		print_table_break("<center>".
		construct_link_code($vbphrase['add_new_replacement_variable'], "replacement.php?" . vB::getCurrentSession()->get('sessionurl') . "do=add&amp;dostyleid=" . $vbulletin->GPC['dostyleid']).
		"</center>");
	}

	if ($vbulletin->GPC['dowhat'] == 'maincss' OR $vbulletin->GPC['dowhat'] == 'css')
	{
		$footerhtml = '';
	}
	else
	{
		$footerhtml = '
		<input type="submit" class="button" value="' . $vbphrase['save'] . '" accesskey="s" tabindex="1" />
		<input type="reset" class="button" value="' . $vbphrase['reset'] . '" accesskey="r" tabindex="1" onclick="this.form.reset(); init_color_preview(); return false;" />
		';
	}

	print_table_footer(2, $footerhtml);
	echo $colorPicker;

	?>
	<script type="text/javascript">
	<!--

	var bburl = "<?php echo $vbulletin->options['bburl']; ?>/";
	var cpstylefolder = "<?php echo $vbulletin->options['cpstylefolder']; ?>";
	var numColors = <?php echo intval($numcolors); ?>;
	var colorPickerWidth = <?php echo intval($colorPickerWidth); ?>;
	var colorPickerType = <?php echo intval($colorPickerType); ?>;

	//-->
	</script>
	<?php
}

// ###################### Start List StyleVar Colors #######################
if ($_REQUEST['do'] == 'stylevar-colors')
{
	// use 'dostyleid'from GPC

	if ($vbulletin->GPC['dostyleid'] != 0
		AND
		$style = $assertor->getRow('vBForum:style', array('styleid' => $vbulletin->GPC['dostyleid']))
	)
	{
		print_form_header('', '');

		foreach (unserialize($style['csscolors']) AS $colorname => $colorvalue)
		{
			if (preg_match('#^[a-z0-9_]+_hex$#siU', $colorname))
			{
				echo "<tr><td class=\"" . fetch_row_bgclass() . "\">$colorname</td><td style=\"background-color:#$colorvalue\" title=\"#$colorvalue\">#$colorvalue</td></tr>";
			}
		}

		print_table_footer();
	}
	else
	{
		// fail.
		$_REQUEST['do'] = 'modify';
	}
}

// ###################### Start List styles #######################
if ($_REQUEST['do'] == 'modify')
{
	print_form_header('css', 'edit');
	print_table_header($vbphrase['edit_styles']);
	if ($vb5_config['Misc']['debug'])
	{
		print_label_row(
			'<b>' . $vbphrase['master_style'] . '</b>',
			construct_link_code($vbphrase['edit'], "css.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;dostyleid=-1") .
			construct_link_code($vbphrase['templates'], "template.php?" . vB::getCurrentSession()->get('sessionurl') . "expandset=$style[styleid]")
		);
		$depthmark = '--';
	}
	else
	{
		$dethmark = '';
	}
	$stylecache = vB_Library::instance('Style')->fetchStyles(false, false);
	foreach ($stylecache AS $style)
	{
		print_label_row(
			construct_depth_mark($style['depth'], '--', $depthmark) . " <b>$style[title]</b>",
			construct_link_code($vbphrase['edit'], "css.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;dostyleid=$style[styleid]") .
			construct_link_code($vbphrase['templates'], "template.php?" . vB::getCurrentSession()->get('sessionurl') . "expandset=$style[styleid]") .
			construct_link_code($vbphrase['settings_gstyle'], "template.php?" . vB::getCurrentSession()->get('sessionurl') . "do=editstyle&amp;dostyleid=$style[styleid]")
		);
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
