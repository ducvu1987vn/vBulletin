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

error_reporting(E_ALL & ~E_NOTICE);

// note #1: arrays used by functions in this code are declared at the bottom of the page
// note #2: REMEMBER to update the $template_table_query if the table changes!!!

/**
* Expand and collapse button labels
*/
define('EXPANDCODE', '&laquo; &raquo;');
define('COLLAPSECODE', '&raquo; &laquo;');

/**
* Size in rows of template editor <select>
*/
define('TEMPLATE_EDITOR_ROWS', 25);

/**
* List of special purpose templates used by css.php and build_style()
*/
global $vbphrase;

/**
* Initialize the IDs for colour preview boxes
*/
$numcolors = 0;


$template_table_fields = 'styleid, title, template, template_un, templatetype, dateline, username, version, product, mergestatus';

// #############################################################################
/**
* Trims the string passed to it
*
* @param	string	(ref) String to be trimmed
*/
function array_trim(&$val)
{
	$val = trim($val);
}

// #############################################################################
/**
* Returns an SQL query string to update a single template
*
* @param	string	Title of template
* @param	string	Un-parsed template HTML
* @param	integer	Style ID for template
* @param	array	(ref) array('template' => array($title => true))
* @param	string	The name of the product this template is associated with
*
* @return	string
*/
function fetch_template_update_sql($title, $template, $dostyleid, &$delete, $product = 'vbulletin')
{
	global $template_cache;

	$oldtemplate = $template_cache['template']["$title"];

	if (is_array($template))
	{
		array_walk($template, 'array_trim');
		$template = "background: $template[background]; color: $template[color]; padding: $template[padding]; border: $template[border];";
	}

	// check if template should be deleted
	if ($delete['template']["$title"])
	{
		return "### DELETE TEMPLATE $title ###
			DELETE FROM " . TABLE_PREFIX . "template
			WHERE templateid = $oldtemplate[templateid]
		";
	}

	if ($template == $oldtemplate['template_un'])
	{
		return false;
	}
	else
	{

		// parse template conditionals, bypass special templates
		if (!in_array($title, vB_Api::instanceInternal('template')->fetchSpecialTemplates()))
		{
			$parsedtemplate = compile_template($template);

			$errors = check_template_errors($parsedtemplate);

			// halt if errors in conditionals
			if (!empty($errors))
			{
				print_stop_message('error_in_template_x_y', $title, "<i>$errors</i>");
			}
		}
		else
		{
			$parsedtemplate =& $template;
		}

		$full_product_info = fetch_product_list(true);
		$userInfo = vB::getCurrentSession()->fetch_userinfo();
		return "
			### REPLACE TEMPLATE: $title ###
			REPLACE INTO " . TABLE_PREFIX . "template
				(styleid, title, template, template_un, templatetype, dateline, username, version, product)
			VALUES
				(" . intval($dostyleid) . ",
				'" . vB::getDbAssertor()->escape_string($title) . "',
				'" . vB::getDbAssertor()->escape_string($parsedtemplate) . "',
				'" . vB::getDbAssertor()->escape_string($template) . "',
				'template',
				" . intval(TIMENOW) . ",
				'" . vB::getDbAssertor()->escape_string($userInfo['username']) . "',
				'" . vB::getDbAssertor()->escape_string($full_product_info["$product"]['version']) . "',
				'" . vB::getDbAssertor()->escape_string($product) . "')
		";
	}

}

// #############################################################################
/**
* Refactor for fetch_template_update_sql() to fit the assertor syntax.
* Returns the sql query name to be executed with the params
*
* @param	string	Title of template
* @param	string	Un-parsed template HTML
* @param	integer	Style ID for template
* @param	array	(ref) array('template' => array($title => true))
* @param	string	The name of the product this template is associated with
*
* @return	array	Containing the queryname and the params needed for the query.
* 					It will return a 'name' key in the params array used if we are using a stored query or query method.
*/
function fetchTemplateUpdateSql($title, $template, $dostyleid, &$delete, $product = 'vbulletin')
{
	global $template_cache;

	$oldtemplate = $template_cache['template']["$title"];

	if (is_array($template))
	{
		array_walk($template, 'array_trim');
		$template = "background: $template[background]; color: $template[color]; padding: $template[padding]; border: $template[border];";
	}

	// check if template should be deleted
	if ($delete['template']["$title"])
	{
		return array('queryname' => 'vBForum:template', 'params' => array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'templateid' => $oldtemplate['templateid']
		));
	}

	if ($template == $oldtemplate['template_un'])
	{
		return false;
	}
	else
	{

		// parse template conditionals, bypass special templates
		if (!in_array($title, vB_Api::instanceInternal('template')->fetchSpecialTemplates()))
		{
			$parsedtemplate = compile_template($template);

			$errors = check_template_errors($parsedtemplate);

			// halt if errors in conditionals
			if (!empty($errors))
			{
				print_stop_message('error_in_template_x_y', $title, "<i>$errors</i>");
			}
		}
		else
		{
			$parsedtemplate =& $template;
		}

		$full_product_info = fetch_product_list(true);
		$userInfo = vB::getCurrentSession()->fetch_userinfo();
		$queryBits = array(
			'styleid' => intval($dostyleid),
			'title' => $title,
			'template' => $parsedtemplate,
			'template_un' => $template,
			'templatetype' => 'template',
			'dateline' => vB::getRequest()->getTimeNow(),
			'username' => $userInfo['username'],
			'version' => $full_product_info["$product"]['version'],
			'product' => $product
		);

		return array('queryname' => 'replaceTemplates', 'params' => array('name' => 'querybits', 'value' => array($queryBits)));
	}

}

// #############################################################################
/**
* Checks the style id of a template item and works out if it is inherited or not
*
* @param	integer	Style ID from template record
*
* @return	string	CSS class name to use to display item
*/
function fetch_inherited_color($itemstyleid, $styleid)
{
	switch ($itemstyleid)
	{
		case $styleid: // customized in current style, or is master set
			if ($styleid == -1)
			{
				return 'col-g';
			}
			else
			{
				return 'col-c';
			}
		case -1: // inherited from master set
		case 0:
			return 'col-g';
		default: // inhertited from parent set
			return 'col-i';
	}

}

// #############################################################################
/**
* Saves the correct style parentlist to each style in the database
*/
function build_template_parentlists()
{
	$styles = vB::getDbAssertor()->assertQuery('vBForum:fetchstyles2');
	foreach ($styles as $style)
	{
		$parentlist = vB_Library::instance('Style')->fetchTemplateParentlist($style['styleid']);
		if ($parentlist != $style['parentlist'])
		{
			vB::getDbAssertor()->assertQuery('vBForum:updatestyleparent', array(
				'parentlist' => $parentlist,
				'styleid' => $style['styleid']
			));
		}
	}

}

// #############################################################################
/**
* Builds all data from the template table into the fields in the style table
*
* @param	boolean	If true, will drop the template table and rebuild, so that template ids are renumbered from zero
* @param	boolean	If true, will fix styles with no parent style specified
* @param	string	If set, will redirect to specified URL on completion
* @param	boolean	If true, reset the master cache
* @param	boolean	Whether to print status/edit information
*/
function build_all_styles($renumber = 0, $install = 0, $goto = '', $resetcache = false, $printInfo = true)
{
	// -----------------------------------------------------------------------------
	// -----------------------------------------------------------------------------
	// this bit of text is used for upgrade scripts where the phrase system
	// is not available it should NOT be converted into phrases!!!
	$phrases = array(
		'master_style' => 'MASTER STYLE',
		'done' => 'Done',
		'style' => 'Style',
		'styles' => 'Styles',
		'templates' => 'Templates',
		'css' => 'CSS',
		'stylevars' => 'Stylevars',
		'replacement_variables' => 'Replacement Variables',
		'controls' => 'Controls',
		'rebuild_style_information' => 'Rebuild Style Information',
		'updating_style_information_for_each_style' => 'Updating style information for each style',
		'updating_styles_with_no_parents' => 'Updating style sets with no parent information',
		'updated_x_styles' => 'Updated %1$s Styles',
		'no_styles_needed_updating' => 'No Styles Needed Updating',
	);
	$vbphrase = vB_Api::instanceInternal('phrase')->fetch($phrases);
	foreach ($phrases AS $key => $val)
	{
		if (!isset($vbphrase["$key"]))
		{
			$vbphrase["$key"] = $val;
		}
	}
	// -----------------------------------------------------------------------------
	// -----------------------------------------------------------------------------

	if (!empty($goto))
	{
		$form_tags = true;
	}

	if ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
	{
		echo "<!--<p>&nbsp;</p>-->
		<blockquote>" . iif($form_tags, "<form>") . "<div class=\"tborder\">
		<div class=\"tcat\" style=\"padding:4px\" align=\"center\"><b>" . $vbphrase['rebuild_style_information'] . "</b></div>
		<div class=\"alt1\" style=\"padding:4px\">\n<blockquote>
		";
		vbflush();
	}

	// useful for restoring utterly broken (or pre vb3) styles
	if ($install)
	{
		if ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
		{
			echo "<p><b>" . $vbphrase['updating_styles_with_no_parents'] . "</b></p>\n<ul class=\"smallfont\">\n";
			vbflush();
		}

		vB::getDbAssertor()->assertQuery('updt_style_parentlist');
		// affected rows is not supported by the assertor
// 		if ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
// 		{
// 			//$affected = $vbulletin->db->affected_rows();
// 			if ($affected)
// 			{
// 				echo "<li>" . construct_phrase($vbphrase['updated_x_styles'], $affected) . "</li>\n";
// 				vbflush();
// 			}
// 			else
// 			{
// 				echo "<li>" . $vbphrase['no_styles_needed_updating'] . "</li>\n";
// 				vbflush();
// 			}
// 			echo "</ul>\n";
// 			vbflush();
// 		}
	}

	// creates a temporary table in order to renumber all templates from 1 to n sequentially
	if ($renumber)
	{
		if ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
		{
			echo "<p><b>" . $vbphrase['updating_template_ids'] . "</b></p>\n<ul class=\"smallfont\">\n";
			vbflush();
		}
		vB::getDbAssertor()->assertQuery('dropTableBlogTrackbackCount', array('tablename' => 'template_temp'));
		if ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
		{
			echo "<li>" . $vbphrase['temporary_template_table_created'] . "</li>\n";
			vbflush();
		}

		/*insert query*/
		vB::getDbAssertor()->assertQuery('template_table_query_insert');
		// affected rows is not supported by the assertor
		//$rows = $vbulletin->db->affected_rows();
// 		if ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
// 		{
// 			echo "<li>" . construct_phrase($vbphrase['temporary_template_table_populated_with_x_templates'], $rows) . "</li>\n";
// 			vbflush();
// 		}

		vB::getDbAssertor()->assertQuery('dropTableBlogTrackbackCount', array('tablename' => 'template'));
		if ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
		{
			echo "<li>" . $vbphrase['old_template_table_dropped'] . "</li>\n";
			vbflush();
		}

		vB::getDbAssertor()->assertQuery('template_table_query_alter');
		if ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
		{
			echo "<li>" . $vbphrase['temporary_template_table_renamed'] . "</li>\n";
			vbflush();
			echo "</ul>\n";
			vbflush();
		}
	}

	if ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
	{
		// the main bit.
		echo "<p><b>" . $vbphrase['updating_style_information_for_each_style'] . "</b></p>\n";
		vbflush();
	}

	build_template_parentlists();

	$styleactions = array('docss' => 1, 'dostylevars' => 1, 'doreplacements' => 1, 'doposteditor' => 1);
	if (defined('NO_POST_EDITOR_BUILD'))
	{
		$styleactions['doposteditor'] = 0;
	}

	if ($error = build_style(-1, $vbphrase['master_style'], $styleactions, '', '', $resetcache, $printInfo))
	{
		return $error;
	}

	if ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
	{
		echo "</blockquote></div>";
		if ($form_tags)
		{
			echo "
			<div class=\"tfoot\" style=\"padding:4px\" align=\"center\">
			<input type=\"button\" class=\"button\" value=\" " . $vbphrase['done'] . " \" onclick=\"window.location='$goto';\" />
			</div>";
		}
		echo "</div>" . iif($form_tags, "</form>") . "</blockquote>
		";
		vbflush();
	}

	vB_Library::instance('Style')->buildStyleDatastore();
}

// #############################################################################
/**
* Displays a style rebuild (build_style) in a nice user-friendly info page
*
* @param	integer	Style ID to rebuild
* @param	string	Title of style
* @param	boolean	Build CSS?
* @param	boolean	Build Stylevars?
* @param	boolean	Build Replacements?
* @param	boolean	Build Post Editor?
*/
function print_rebuild_style($styleid, $title = '', $docss = 1, $dostylevars = 1, $doreplacements = 1, $doposteditor = 1, $printInfo = true)
{
	$vbphrase = vB_Api::instanceInternal('phrase')->fetch(array('master_style', 'rebuild_style_information', 'updating_style_information_for_x', 'done'));
	$styleid = intval($styleid);

	if (empty($title))
	{
		if ($styleid == -1)
		{
			$title = $vbphrase['master_style'];
		}
		else
		{
			DEVDEBUG('Querying first style name');
			$getstyle = vB_Library::instance('Style')->fetchStyleByID($styleid);

			if (!$getstyle)
			{
				return;
			}

			$title = $getstyle['title'];
		}
	}

	if ($printInfo AND (VB_AREA != 'Upgrade') AND (VB_AREA != 'Install'))
	{
		echo "<p>&nbsp;</p>
		<blockquote><form><div class=\"tborder\">
		<div class=\"tcat\" style=\"padding:4px\" align=\"center\"><b>" . $vbphrase['rebuild_style_information'] . "</b></div>
		<div class=\"alt1\" style=\"padding:4px\">\n<blockquote>
		<p><b>" . construct_phrase($vbphrase['updating_style_information_for_x'], $title) . "</b></p>
		<ul class=\"lci\">\n";
		vbflush();
	}

	build_style($styleid, $title, array(
		'docss' => $docss,
		'dostylevars' => $dostylevars,
		'doreplacements' => $doreplacements,
		'doposteditor' => $doposteditor
	), false, '', 1, $printInfo);

	if ($printInfo AND (VB_AREA != 'Upgrade') AND (VB_AREA != 'Install'))
	{
		echo "</ul>\n<p><b>" . $vbphrase['done'] . "</b></p>\n</blockquote></div>
		</div></form></blockquote>
		";
		vbflush();
	}

	vB_Library::instance('Style')->buildStyleDatastore();

}

// #############################################################################
/**
* Attempts to delete the file specified in the <link rel /> for this style
*
* @param	integer	Style ID
* @param	string	CSS contents
*/
function delete_css_file($styleid, $csscontents)
{
	if (preg_match('#@import url\("(clientscript/vbulletin_css/style-\w{8}-0*' . $styleid . '\.css)"\);#siU', $csscontents, $match))
	{
		// attempt to delete old css file
		@unlink(DIR . "/$match[1]");
	}
}

function delete_style_css_directory($styleid, $dir = 'ltr')
{
	$styledir = DIR . '/clientscript/vbulletin_css/style' . str_pad($styleid, 5, '0', STR_PAD_LEFT) . ($dir == 'ltr' ? 'l' : 'r');
	if (is_dir($styledir))
	{
		if (!is_dir("$styledir/$file"))
		{
			if (!is_dir($file))
			{
				@unlink("$styledir/$file");
			}
		}
	}

	@rmdir($styledir);
}

// #############################################################################
/**
* Attempts to create a new css file for this style
*
* @param	string	CSS filename
* @param	string	CSS contents
*
* @return	boolean	Success
*/
function write_css_file($filename, $contents)
{
	// attempt to write new css file - store in database if unable to write file
	if ($fp = @fopen($filename, 'wb') AND !is_demo_mode())
	{
		fwrite($fp, vB_String::getCssMinifiedText($contents));
		@fclose($fp);
		return true;
	}
	else
	{
		@fclose($fp);
		return false;
	}
}

function write_style_css_directory($styleid, $parentlist, $dir = 'ltr')
{
	//verify that we have or can create a style directory
	$styledir = DIR . '/clientscript/vbulletin_css/style' . str_pad($styleid, 5, '0', STR_PAD_LEFT) . ($dir == 'ltr' ? 'l' : 'r');

	//if we have a file that's not a directory or not writable something is wrong.
	if (file_exists($styledir) AND (!is_dir($styledir) OR !is_writable($styledir)))
	{
		return false;
	}

	//clear any old files.
	if (file_exists($styledir))
	{
		delete_style_css_directory($styleid, $dir);
	}

	//create the directory -- if it still exists try to continue with the existing dir
	if (!file_exists($styledir))
	{
		if (!@mkdir($styledir))
		{
			return false;
		}
	}

	//check for success.
	if (!is_dir($styledir) OR !is_writable($styledir))
	{
		return false;
	}

	//write out the files for this style.
	$parentlistarr = explode(',', $parentlist);
	$set = vB::getDbAssertor()->assertQuery('vBForum:fetchParentTemplates', array('parentlist' => $parentlistarr));

	//collapse the list.
	$css_templates = array();
	foreach ($set as $row)
	{
		$css_templates[] = $row['title'];
	}

	vB_Library::instance('Style')->switchCssStyle($styleid, $css_templates);
	if ($dir == 'ltr')
	{
		vB_Template_Runtime::addStyleVar('left', 'left');
		vB_Template_Runtime::addStyleVar('right', 'right');
		vB_Template_Runtime::addStyleVar('textdirection', 'ltr');
	}
	else
	{
		vB_Template_Runtime::addStyleVar('left', 'right');
		vB_Template_Runtime::addStyleVar('right', 'left');
		vB_Template_Runtime::addStyleVar('textdirection', 'rtl');
	}

	$templates = array();
	foreach ($css_templates AS $title)
	{
		//I'd call this a hack but there probably isn't a cleaner way to do this.
		//The css is published to a different directory than the css.php file
		//which means that relative urls that works for css.php won't work for the
		//published directory.  Unfortunately urls from the webroot don't work
		//because the forum often isn't located at the webroot and we can only
		//specify urls from the forum root.  And css doens't provide any way
		//of setting a base url like html does.  So we are left to "fixing"
		//any relative urls in the published css.
		//
		//We leave alone any urls starting with '/', 'http', and 'https:'
		//there are other valid urls, but nothing that people should be
		//using in our css files.

		$text = vB_Template::create($title)->render(true);

	/*	We need the frontend base url, but this isnt always available.
		If it is available, we simply use it - otherwise we attempt to
		read the frontend config file. In 99.9% of sites this will work.
		If that fails, we attempt to get it from the backend config file.
		This requires that the backend config has this set (Misc, baseurl),
		By default this isnt set, but the site administrator can set it.
 		If all this fails, we give up and return false */

		$config = array();
		$cfile = realpath(DIR . './../config.php');

		if ($_SERVER['x-vb-presentation-base'])
		{
			$config['baseurl'] = $_SERVER['x-vb-presentation-base'];
		}
		else if (file_exists($cfile))
		{
			include($cfile);
		}
		else
		{
			$config =& vB::getConfig();
			$config['baseurl'] = $config['Misc']['baseurl'];
		}

		if (!isset($config['baseurl']))
		{
			return false;
		}
		else
		{
			$re = '#url\(\s*["\']?(?!/|http:|https:|"/|\'/)#';

			$base = vB::getDatastore()->getOption('cdnurl');
			if (!$base)
			{
				$base = $config['baseurl'];
			}

			if (substr($base, -1, 1) != '/')
			{
				$base .= '/';
			}
			$text = preg_replace ($re, "$0$base", $text);

			$templates[$title] = $text;
			if (!write_css_file("$styledir/$title", $text))
			{
				return false;
			}
		}
	}

	static $vbdefaultcss = array(), $cssfiles = array();

	if (empty($vbdefaultcss))
	{
		require_once(DIR . '/includes/class_xml.php');

		$cssfilelist = vB_Api_Product::loadProductXmlList('cssrollup', true);

		if (empty($cssfilelist['vbulletin']))
		{
			$vbphrase = vB_Api::instanceInternal('phrase')->fetch(array('could_not_open_x'));
			echo construct_phrase($vbphrase['could_not_open_x'], DIR . '/includes/xml/cssrollup_vbulletin.xml');
			exit;
		}
		else
		{
			$mainfile = array_shift($cssfilelist);
		}

		$xmlobj = new vB_XML_Parser(false, $mainfile);
		$data = $xmlobj->parse();

		if (!is_array($data['rollup'][0]))
		{
			$data['rollup'] = array($data['rollup']);
		}

		foreach ($data['rollup'] AS $file)
		{
			foreach ($file['template'] AS $name)
			{
				$vbdefaultcss["$file[name]"] = $file['template'];
			}
		}

		foreach ($cssfilelist AS $css_file => $file)
		{
			$xmlobj = new vB_XML_Parser(false, $file);
			$data = $xmlobj->parse();

			$products = vB::getDatastore()->getValue('products');
			if ($data['product'] AND empty($products["$data[product]"]))
			{
				// attached to a specific product and that product isn't enabled
				continue;
			}

			if (!is_array($data['rollup'][0]))
			{
				$data['rollup'] = array($data['rollup']);
			}

			$cssfiles[$css_file]['css'] = $data['rollup'];
		}
	}

	foreach ($cssfiles AS $css_file => $files)
	{
		if (is_array($files['css']))
		{
			foreach ($files['css'] AS $file)
			{
				if (process_css_rollup_file($file['name'], $file['template'], $templates, $styledir, $vbdefaultcss) === false)
				{
					return false;
				}
			}
		}
	}

	foreach ($vbdefaultcss AS $xmlfile => $files)
	{
		if (process_css_rollup_file($xmlfile, $files, $templates, $styledir) === false)
		{
			return false;
		}
	}

	return true;
}

function process_css_rollup_file($file, $templatelist, $templates, $styledir, &$vbdefaultcss = array())
{
	if (!is_array($templatelist))
	{
		$templatelist = array($templatelist);
	}

	if ($vbdefaultcss AND $vbdefaultcss["$file"])
	{
		// Add these templates to the main file rollup
		$vbdefaultcss["$file"] = array_unique(array_merge($vbdefaultcss["$file"], $templatelist));
		return true;
	}

	$count = 0;
	foreach ($templatelist AS $name)
	{
		$template = $templates[$name];
		if ($count > 0)
		{
			$text .= "\r\n\r\n";
			$template = preg_replace("#@charset .*#i", "", $template);
		}
		$text .= $template;
		$count++;
	}

	if (!write_css_file("$styledir/$file", $text))
	{
		return false;
	}

	return true;
}

// #############################################################################
/**
* Converts all data from the template table for a style into the style table
*
* @param	integer	Style ID
* @param	string	Title of style
* @param	array	Array of actions set to true/false: docss/dostylevars/doreplacements/doposteditor
* @param	string	List of parent styles
* @param	string	Indent for HTML printing
* @param	boolean	Reset the master cache
* @param	boolean	Whether to print status/edit information
*/
function build_style($styleid, $title = '', $actions = array(), $parentlist = '', $indent = '', $resetcache = false, $printInfo = true)
{
	require_once(DIR . '/includes/adminfunctions.php');
	static $csscache = array();
	$vbphrase = vB_Api::instanceInternal('phrase')->fetch(array('templates', 'stylevars','replacement_variables', 'css', 'controls', 'done'));

	if (($actions['doreplacements'] OR $actions['docss'] OR $actions['dostylevars']) AND vB::getDatastore()->getOption('storecssasfile'))
	{
		$actions['docss'] = true;
		$actions['doreplacements'] = true;
	}

	if ($styleid != -1)
	{
		if ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
		{
			// echo the title and start the listings
			echo "$indent<li><b>$title</b> ... <span class=\"smallfont\">";
			vbflush();
		}
		// build the templateid cache
		if (!$parentlist)
		{
			$parentlist = vB_Library::instance('Style')->fetchTemplateParentlist($styleid);
		}

		$templatelist = vB_Library::instance('Style')->buildTemplateIdCache($styleid, 1, $parentlist);
		$styleupdate = array();
		$styleupdate['templatelist'] = $templatelist;

		if ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
		{
			echo "($vbphrase[templates]) ";
			vbflush();
		}

		// cache special templates
		if ($actions['docss'] OR $actions['dostylevars'] OR $actions['doreplacements'] OR $actions['doposteditor'])
		{
			// get special templates for this style
			$template_cache = array();
			$templateids = unserialize($templatelist);
			$specials = vB_Api::instanceInternal('template')->fetchSpecialTemplates();

			if ($templateids)
			{
				$templates = vB::getDbAssertor()->assertQuery('vBForum:fetchtemplatewithspecial', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'templateids' => $templateids,
					'specialtemplates' => $specials
				));

				foreach ($templates as $template)
				{
					$template_cache["$template[templatetype]"]["$template[title]"] = $template;
				}
			}
		}

		// style vars
		if ($actions['dostylevars'])
		{
			if ($template_cache['stylevar'])
			{
				// rebuild the stylevars field for this style
				$stylevars = array();
				foreach($template_cache['stylevar'] AS $template)
				{
					// set absolute paths for image directories
					/*if (substr($template['title'], 0, 7) == 'imgdir_')
					{
						if (!preg_match('#^https?://#i', $template['template']))
						{
							$template['template'] = "$template[template]";
						}
					}*/
					$stylevars["$template[title]"] = $template['template'];
				}
			}

			// new stylevars
			static $master_stylevar_cache = null;
			static $resetcachedone = false;
			if ($resetcache AND !$resetcachedone)
			{
				$resetcachedone = true;
				$master_stylevar_cache = null;
			}
			if ($master_stylevar_cache === null)
			{
				$master_stylevar_cache = array();
				$master_stylevars = vB::getDbAssertor()->assertQuery('vBForum:getDefaultStyleVars',
					array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));

				foreach ($master_stylevars AS $master_stylevar)
				{
					$tmp = unserialize($master_stylevar['value']);
					if (!is_array($tmp))
					{
						$tmp = array('value' => $tmp);
					}
					$master_stylevar_cache[$master_stylevar['stylevarid']] = $tmp;
					$master_stylevar_cache[$master_stylevar['stylevarid']]['datatype'] = $master_stylevar['datatype'];
				}

			}

			$newstylevars = $master_stylevar_cache;

			if (substr(trim($parentlist), 0, -3) != '')
			{
				$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'stylelist' => explode(',', substr(trim($parentlist), 0, -3)),
				'parentlist' => $parentlist);
				$new_stylevars = vB::getDbAssertor()->getRows('vBForum:getStylesFromList', $data);

				foreach ($new_stylevars as $new_stylevar)
				{
					ob_start();
					$newstylevars[$new_stylevar['stylevarid']] = unserialize($new_stylevar['value']);
					if (ob_get_clean() OR !is_array($newstylevars[$new_stylevar['stylevarid']]))
					{
						continue;
					}
					$newstylevars[$new_stylevar['stylevarid']]['datatype'] = $master_stylevar_cache[$new_stylevar['stylevarid']]['datatype'];
				}
			}

			$styleupdate['newstylevars'] = serialize($newstylevars);

			if ($printInfo AND VB_AREA != 'Upgrade' AND VB_AREA != 'Install')
			{
				echo "($vbphrase[stylevars]) ";
				vbflush();
			}
		}

		// replacements
		if ($actions['doreplacements'])
		{
			// rebuild the replacements field for this style
			$replacements = array();
			if (is_array($template_cache['replacement']))
			{
				foreach($template_cache['replacement'] AS $template)
				{
					// set the key to be a case-insentitive preg find string
					$replacementkey = '#' . preg_quote($template['title'], '#') . '#si';

					$replacements["$replacementkey"] = $template['template'];
				}
				$styleupdate['replacements'] = serialize($replacements) ;
			}
			else
			{
				$styleupdate['replacements'] = "''";
			}
			if ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
			{
				echo "($vbphrase[replacement_variables]) ";
				vbflush();
			}
		}

		// css -- old style css
		if ($actions['docss'] AND $template_cache['css'])
		{
			// build a quick cache with the ~old~ contents of the css fields from the style table
			if (empty($csscache))
			{
				$fetchstyles = vB::getDbAssertor()->assertQuery('vBForum:style', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				));
				foreach ($fetchstyles as $fetchstyle)
				{
					$fetchstyle['css'] .= "\n";
					$csscache["$fetchstyle[styleid]"] = $fetchstyle['css'];
				}
			}

			// rebuild the css field for this style
			$css = array();
			foreach($template_cache['css'] AS $template)
			{
				$css["$template[title]"] = unserialize($template['template']);
			}

			// build the CSS contents
			$csscolors = array();
			$css = construct_css($css, $styleid, $title, $csscolors);

			// attempt to delete the old css file if it exists
			delete_css_file($styleid, $csscache["$styleid"]);

			$adblock_is_evil = str_replace('ad', 'be', substr(md5(microtime()), 8, 8));
			$cssfilename = DIR . '/clientscript/vbulletin_css/style-' . $adblock_is_evil . '-' . str_pad($styleid, 5, '0', STR_PAD_LEFT) . '.css';

			// if we are going to store CSS as files, run replacement variable substitution on the file to be saved
			if (vB::getDatastore()->getOption('storecssasfile'))
			{
				$css = process_replacement_vars($css, array('styleid' => $styleid, 'replacements' => serialize($replacements)));
				$css = preg_replace('#(?<=[^a-z0-9-]|^)url\((\'|"|)(.*)\\1\)#iUe', "rewrite_css_file_url('\\2', '\\1')", $css);
				if (write_css_file($cssfilename, $css))
				{
					$css = "@import url(\"$cssfilename\");";
				}
			}

			$fullcsstext = "<style type=\"text/css\" id=\"vbulletin_css\">\r\n" .
				"/**\r\n* vBulletin " . vB::getDatastore()->getOption('templateversion') . " CSS\r\n* Style: '$title'; Style ID: $styleid\r\n*/\r\n" .
				"$css\r\n</style>\r\n" .
				"<link rel=\"stylesheet\" type=\"text/css\" href=\"clientscript/vbulletin_important.css?v=" . vB::getDatastore()->getOption('simpleversion') . "\" />"
			;

			if  ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
			{
				echo "($vbphrase[css]) ";
				vbflush();
			}
		}

		// post editor styles
		if ($actions['doposteditor'] AND $template_cache['template'])
		{
			$editorstyles = array();
			if (!empty($template_cache['template']))
			{
				foreach ($template_cache['template'] AS $template)
				{
					if (substr($template['title'], 0, 13) == 'editor_styles')
					{
						$title = 'pi' . substr($template['title'], 13);
						$item = fetch_posteditor_styles($template['template']);
						$editorstyles["$title"] = array($item['background'], $item['color'], $item['padding'], $item['border']);
					}
				}
			}
			if  ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
			{
				echo "($vbphrase[controls]) ";
				vbflush();
			}
		}

		// do the style update query
		if (!empty($styleupdate))
		{
			$styleupdate['styleid'] = $styleid;
			$styleupdate[vB_dB_Query::TYPE_KEY] = vB_dB_Query::QUERY_UPDATE;
			vB::getDbAssertor()->assertQuery('vBForum:style', $styleupdate);
		}

		//write out the new css -- do this *after* we update the style record
		if (vB::getDatastore()->getOption('storecssasfile'))
		{
			if (!write_style_css_directory($styleid, $parentlist, 'ltr'))
			{
				if  ($printInfo AND VB_AREA != 'Upgrade' AND VB_AREA != 'Install')
				{
					echo fetch_error("rebuild_failed_to_write_css");
				}
				else
				{
					return fetch_error("rebuild_failed_to_write_css");
				}
			}
			else if (!write_style_css_directory($styleid, $parentlist, 'rtl'))
			{
				if  ($printInfo AND VB_AREA != 'Upgrade' AND VB_AREA != 'Install')
				{
					echo fetch_error("rebuild_failed_to_write_css");
				}
				else
				{
					return fetch_error("rebuild_failed_to_write_css");
				}
			}
		}

		// finish off the listings
		if ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
		{
			echo "</span><b>" . $vbphrase['done'] . "</b>.<br />&nbsp;</li>\n"; vbflush();
		}
	}

	$childsets = vB::getDbAssertor()->getRows('style', array(
		vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
		vB_dB_Query::CONDITIONS_KEY => array(
			'parentid' => $styleid
		)
	));

	if (count($childsets))
	{
		if ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
		{
			echo "$indent<ul class=\"ldi\">\n";
		}
		foreach ($childsets as $childset)
		{
			if ($error = build_style($childset['styleid'], $childset['title'], $actions, $childset['parentlist'], $indent . "\t", $resetcache, $printInfo))
			{
				return $error;
			}
		}
		if ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
		{
			echo "$indent</ul>\n";
		}
	}
}

// #############################################################################
/**
* Extracts a color value from a css string
*
* @param	string	CSS color value
*
* @return	string
*/
function fetch_color_value($csscolor)
{
	if (preg_match('/^(rgb\s*\([0-9,\s]+\)|(#?\w+))(\s|$)/siU', $csscolor, $match))
	{
		return $match[1];
	}
	else
	{
		return $csscolor;
	}
}

/**
 * Attempts to return a six-character hex value for a given color value (hex, rgb or named)
 *
 * @param	string	CSS color value
 * @return	string
 */
function fetch_color_hex_value($csscolor)
{
	static $html_color_names = null,
	       $html_color_names_regex = null,
	       $system_color_names = null,
	       $system_color_names_regex = null;

	if (!is_array($html_color_names))
	{
		require_once(DIR . '/includes/html_color_names.php');

		$html_color_names_regex = implode('|', array_keys($html_color_names));

		$system_color_names = (
			strpos(strtolower(USER_AGENT), 'macintosh') !== false
			? $system_color_names_mac
			: $system_color_names_win
		);

		$system_color_names_regex = implode('|', array_keys($system_color_names));
	}

	$hexcolor = '';

	// match a hex color
	if (preg_match('/\#([0-9a-f]{6}|#[0-9a-f]{3})($|[^0-9a-f])/siU', $csscolor, $match))
	{
		if (strlen($match[1]) == 3)
		{
			$hexcolor .= $match[1]{0} . $match[1]{0} . $match[1]{1} . $match[1]{1} . $match[1]{2} . $match[1]{2};
		}
		else
		{
			$hexcolor .= $match[1];
		}
	}
	// match an RGB color
	else if (preg_match('/rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/siU', $csscolor, $match))
	{
		for ($i = 1; $i <= 3; $i++)
		{
			$hexcolor .= str_pad(dechex($match["$i"]), 2, 0, STR_PAD_LEFT);
		}
	}
	// match a named color
	else if (preg_match("/(^|[^\w])($html_color_names_regex)($|[^\w])/siU", $csscolor, $match))
	{
		$hexcolor = $html_color_names[strtolower($match[2])];
	}
	// match a named system color (CSS2, deprecated)
	else if (preg_match("/(^|[^\w])($system_color_names_regex)($|[^\w])/siU", $csscolor, $match))
	{
		$hexcolor = $system_color_names[strtolower($match[2])];
	}
	else
	{
		// failed to match a color
		return false;
	}

	return strtoupper($hexcolor);
}

// #############################################################################
/**
* Reads the input from the CSS editor and builds it into CSS code
*
* @param	array	Submitted data from css.php?do=edit
* @param	integer	Style ID
* @param	string	Title of style
* @param	array	(ref) Array of extracted CSS colour values
*
* @return	string
*/
function construct_css($css, $styleid, $styletitle, &$csscolors)
{
	// remove the 'EXTRA' definition and stuff it in at the end :)
	$extra = trim($css['EXTRA']['all']);
	$extra2 = trim($css['EXTRA2']['all']);
	unset($css['EXTRA'], $css['EXTRA2']);

	// initialise the stylearray
	$cssarray = array();

	// order for writing out CSS variables
	$css_write_order = array(
		'body',
		'.page',
		'td, th, p, li',
		'.tborder',
		'.tcat',
		'.thead',
		'.tfoot',
		'.alt1, .alt1Active',
		'.alt2, .alt2Active',
		'.inlinemod',
		'.wysiwyg',
		'textarea, .bginput',
		'.button',
		'select',
		'.smallfont',
		'.time',
		'.navbar',
		'.highlight',
		'.fjsel',
		'.fjdpth0',
		'.fjdpth1',
		'.fjdpth2',
		'.fjdpth3',
		'.fjdpth4',

		'.panel',
		'.panelsurround',
		'legend',

		'.vbmenu_control',
		'.vbmenu_popup',
		'.vbmenu_option',
		'.vbmenu_hilite',
	);

	// Legacy Hook 'css_output_build' Removed //

	// loop through the $css_write_order array to make sure we
	// write the css into the template in the correct order

	foreach($css_write_order AS $itemname)
	{
		unset($links, $thisitem);
		if (is_array($css["$itemname"]))
		{
			foreach($css["$itemname"] AS $cssidentifier => $value)
			{
				if (preg_match('#^\.(\w+)#si', $itemname, $match))
				{
					$itemshortname = $match[1];
				}
				else
				{
					$itemshortname = $itemname;
				}

				switch ($cssidentifier)
				{
					// do normal links
					case 'LINK_N':
					{
						if ($getlinks = construct_link_css($itemname, $cssidentifier, $value))
						{
							$links['normal'] = $getlinks;
						}
					}
					break;

					// do visited links
					case 'LINK_V':
					{
						if ($getlinks = construct_link_css($itemname, $cssidentifier, $value))
						{
							$links['visited'] = $getlinks;
						}
					}
					break;

					// do hover links
					case 'LINK_M':
					{
						if ($getlinks = construct_link_css($itemname, $cssidentifier, $value))
						{
							$links['hover'] = $getlinks;
						}
					}
					break;

					// do extra attributes
					case 'EXTRA':
					case 'EXTRA2':
					{
						if (!empty($value))
						{
							$value = "\t" . str_replace("\r\n", "\r\n\t", $value);
							$thisitem[] = "$value\r\n";
						}
					}
					break;

					// do font bits
					case 'font':
					{
						if ($getfont = construct_font_css($value))
						{
							$thisitem[] = $getfont;
						}
					}
					break;

					// normal attributes
					default:
					{
						$value = trim($value);
						if ($value != '')
						{
							switch ($cssidentifier)
							{
								case 'background':
								{
									$csscolors["{$itemshortname}_bgcolor"] = fetch_color_value($value);
								}
								break;

								case 'color':
								{
									$csscolors["{$itemshortname}_fgcolor"] = fetch_color_value($value);
								}
								break;
							}
							$thisitem[] = "\t$cssidentifier: $value;\r\n";
						}
					}

				}
			}
		}
		// add the item to the css if it's not blank
		if (sizeof($thisitem) > 0)
		{
			$cssarray[] = "$itemname\r\n{\r\n" . implode('', $thisitem) . "}\r\n" . $links['normal'] . $links['visited'] . $links['hover'];

			if ($itemname == 'select')
			{
				$optioncss = array();
				if ($optionsize = trim($css["$itemname"]['font']['size']))
				{
					$optioncss[] = "\tfont-size: $optionsize;\r\n";
				}
				if ($optionfamily = trim($css["$itemname"]['font']['family']))
				{
					$optioncss[] = "\tfont-family: $optionfamily;\r\n";
				}
				$cssarray[] = "option, optgroup\r\n{\r\n" . implode('', $optioncss) . "}\r\n";
			}
			else if ($itemname == 'textarea, .bginput')
			{
				$optioncss = array();
				if ($optionsize = trim($css["$itemname"]['font']['size']))
				{
					$optioncss[] = "\tfont-size: $optionsize;\r\n";
				}
				if ($optionfamily = trim($css["$itemname"]['font']['family']))
				{
					$optioncss[] = "\tfont-family: $optionfamily;\r\n";
				}
				$cssarray[] = ".bginput option, .bginput optgroup\r\n{\r\n" . implode('', $optioncss) . "}\r\n";
			}
		}
	}

	// generate hex colors
	foreach ($css_write_order AS $itemname)
	{
		if (is_array($css["$itemname"]))
		{
			$itemshortname = (strpos($itemname, '.') === 0 ? substr($itemname, 1) : $itemname);

			foreach($css["$itemname"] AS $cssidentifier => $value)
			{
				switch ($cssidentifier)
				{
					case 'LINK_N':
					case 'LINK_V':
					case 'LINK_M':
					{
						if ($value['color'] != '')
						{
							$csscolors[$itemshortname . '_' . strtolower($cssidentifier) . '_fgcolor'] = fetch_color_value($value['color']);
						}

						if ($value['background'] != '')
						{
							$csscolors[$itemshortname . '_' . strtolower($cssidentifier) . '_bgcolor'] = fetch_color_value($value['background']);
						}
					}
					break;

					// do extra attributes
					case 'EXTRA':
					case 'EXTRA2':
					{
						if (preg_match('#border(-color)?\s*\:\s*([^;]+);#siU', $value, $match))
						{
							$csscolors[$itemshortname . '_border_color'] = fetch_color_value($match[2]);
						}
					}
					break;
				}
			}
		}
	}

	$csscolors_hex = array();

	foreach ($csscolors AS $colorname => $colorvalue)
	{
		$hexcolor = fetch_color_hex_value($colorvalue);

		if ($hexcolor !== false)
		{
			$csscolors_hex[$colorname . '_hex'] = $hexcolor;
		}
	}

	$csscolors = array_merge($csscolors, $csscolors_hex);

	// Legacy Hook 'css_output_build_end' Removed //

	return trim(implode('', $cssarray) . "$extra\r\n$extra2");
}

// #############################################################################
/**
* Returns a URL for use in CSS, dealing with directory nesting etc.
*
* @param	string	URL
* @param	string	Quote type (single quote, double quote)
*
* @return	string	example: url('/path/to/file.ext')
*/
function rewrite_css_file_url($url, $delimiter = '')
{
	static $iswritable = null;
	if ($iswritable === null)
	{
		$iswritable = is_writable(DIR . '/clientscript/vbulletin_css/');
	}

	$url = str_replace('\\"', '"', $url);
	$delimiter = str_replace('\\"', '"', $delimiter);

	if (!$iswritable OR preg_match('#^(https?://|/)#i', $url))
	{
		return "url($delimiter$url$delimiter)";
	}
	else
	{
		return "url($delimiter../../$url$delimiter)";
	}
}

// #############################################################################
/**
* Takes the font style input from css.php?do=edit and returns valid CSS
*
* @param	array	Array of values from form
*
* @return	string
*/
function construct_font_css($font)
{
	// possible values for CSS 'font-weight' attribute
	$css_font_weight = array('normal', 'bold', 'bolder', 'lighter');

	// possible values for CSS 'font-style' attribute
	$css_font_style = array('normal', 'italic', 'oblique');

	// possible values for CSS 'font-variant' attribute
	$css_font_variant = array('normal', 'small-caps');

	foreach($font AS $key => $value)
	{
		$font["$key"] = trim($value);
	}

	$out = '';

	if (!empty($font['size']) AND !empty($font['family']))
	{

		foreach ($font AS $value)
		{
			$out .= "$value ";
		}
		$out = trim($out);
		if (!empty($out))
		{
			$out = "\tfont: $out;\r\n";
		}

	}
	else
	{

		if (!empty($font['size']))
		{
			$out .= "\tfont-size: $font[size];\r\n";
		}
		if (!empty($font['family']))
		{
			$out .= "\tfont-family: $font[family];\r\n";
		}
		if (!empty($font['style']))
		{
			$stylebits = explode(' ', $font['style']);
			foreach($stylebits AS $bit)
			{
				$bit = strtolower($bit);
				if (in_array($bit, $css_font_weight) OR preg_match('/[1-9]{1}00/', $bit))
				{
					$out .= "\tfont-weight: $bit;\r\n";
				}
				if (in_array($bit, $css_font_style))
				{
					$out .= "\tfont-style: $bit;\r\n";
				}
				if (in_array($bit, $css_font_variant))
				{
					$out .= "\tfont-variant: $bit;\r\n";
				}
				if (preg_match('/(pt|\.|%)/siU', $bit))
				{
					$out .= "\tline-height: $bit;\r\n";
				}
			}
		}

	}

	if (trim($out) == '')
	{
		return false;
	}
	else
	{
		return $out;
	}

}

// #############################################################################
/**
* Takes the link style input from css.php?do=edit and returns valid CSS
*
* @param	array	Items from form
* @param	string	Link type (LINK_N, LINK_V etc.)
* @param	array	Attributes array
*
* @return	string
*/
function construct_link_css($item, $what, $array)
{
	$out = '';
	foreach($array AS $attribute => $value)
	{
		$value = trim($value);
		if (!empty($value))
		{
			$out .= "\t$attribute: $value;\r\n";
		}
	}

	if (!empty($out))
	{
		$item_bits = '';
		$items = explode(',', $item);
		foreach ($items AS $one_item)
		{
			$one_item = trim($one_item);
			if (!empty($one_item))
			{
				if ($what == 'LINK_N')
				{
					$item_bits .= ", $one_item a:link, {$one_item}_alink";
				}
				else if ($what == 'LINK_V')
				{
					$item_bits .= ", $one_item a:visited, {$one_item}_avisited";
				}
				else
				{
					$item_bits .= ", $one_item a:hover, $one_item a:active, {$one_item}_ahover";
				}
			}
		}
		$item_bits = str_replace('body a:', 'a:', substr($item_bits, 2));
		switch ($what)
		{
			case 'LINK_N':
				return "$item_bits\r\n{\r\n$out}\r\n";
			case 'LINK_V':
				return "$item_bits\r\n{\r\n$out}\r\n";
			default:
				return "$item_bits\r\n{\r\n$out}\r\n";
		}
	}
	else
	{
		return false;
	}
}

// #############################################################################
/**
* Prints out a style editor block, as seen in template.php?do=modify
*
* @param	integer	Style ID
* @param	array	Style info array
*/
function print_style($styleid, $style = '')
{
	global $vbulletin, $masterset;
	global $only;
	global $SHOWTEMPLATE;

	$vbphrase = vB_Api::instanceInternal('phrase')->fetch(array(
			'add_child_style', 'add_new_template','all_style_options', 'all_template_groups', 'allow_user_selection', 'collapse_all_template_groups', 'collapse_template_group', 'collapse_templates',
			'collapse_x', 'common_templates', 'controls', 'custom_templates', 'customize_gstyle', 'delete_style', 'display_order', 'download', 'edit', 'edit_fonts_colors_etc', 'edit_settings_gstyle',
			'edit_style_options', 'edit_templates', 'expand_all_template_groups', 'expand_template_group', 'expand_templates', 'expand_x', 'go', 'master_style', 'replacement_variables',
			'revert_all_stylevars', 'revert_all_templates', 'revert_gcpglobal', 'stylevareditor', 'template_is_customized_in_this_style', 'template_is_inherited_from_a_parent_style', 'template_is_unchanged_from_the_default_style',
			'template_options', 'view_original', 'view_your_forum_using_this_style', 'x_templates'
			));
	$titlesonly =& $vbulletin->GPC['titlesonly'];
	$expandset =& $vbulletin->GPC['expandset'];
	$group =& $vbulletin->GPC['group'];
	$searchstring =& $vbulletin->GPC['searchstring'];
	$vb5_config =& vB::getConfig();
	if ($styleid == -1)
	{
		$THISstyleid = 0;
		$style['title'] = $vbphrase['master_style'];
		$style['templatelist'] = serialize($masterset);
	}
	else
	{
		$THISstyleid = $styleid;
	}

	if ($expandset == 'all' OR $expandset == $styleid)
	{
		$showstyle = 1;
	}
	else
	{
		$showstyle = 0;
	}

	$forumhome_url = vB::getDatastore()->get_value('baseurl');
	//vB5_Route::buildUrl('page|fullurl', array(), array('styleid' => $styleid));

	// show the header row
	$printstyleid = iif($styleid == -1, 'm', $styleid);
	$onclickoptions = array('do'=>'modify', 'group'=>$group);
	if (empty($showstyle))
	{
		$onclickoptions['expandset'] = $styleid;
	}
	$onclicklink = vB5_Route::buildUrl('admincp|fullurl', array('file' => 'template'), $onclickoptions);
	echo "
	<!-- start header row for style '$style[styleid]' -->
	<table cellpadding=\"2\" cellspacing=\"0\" border=\"0\" width=\"100%\" class=\"stylerow\">
	<tr>
		<td><label for=\"userselect_$styleid\" title=\"$vbphrase[allow_user_selection]\">&nbsp; " .
			construct_depth_mark($style['depth'], '- - ', iif($vb5_config['Misc']['debug'] AND $styleid != -1, '- - ')) .
			iif($styleid != -1, "<input type=\"checkbox\" name=\"userselect[$styleid]\" value=\"1\" tabindex=\"1\"" .
			iif($style['userselect'], ' checked="checked"') .
			" id=\"userselect_$styleid\" onclick=\"check_children($styleid, this.checked)\" />") .
		"</label><a href=\"$forumhome_url\" target=\"_blank\" title=\"$vbphrase[view_your_forum_using_this_style]\">$style[title]</a></td>
		<td align=\"" . vB_Template_Runtime::fetchStyleVar('right') . "\" nowrap=\"nowrap\">
			" . iif($styleid != -1, "<input type=\"text\" class=\"bginput\" name=\"displayorder[$styleid]\" value=\"$style[displayorder]\" tabindex=\"1\" size=\"2\" title=\"$vbphrase[display_order]\" />") . "
			&nbsp;
			<select name=\"styleEdit_$printstyleid\" id=\"menu_$styleid\" onchange=\"Sdo(this.options[this.selectedIndex].value, $styleid);\" class=\"bginput\">
				<optgroup label=\"" . $vbphrase['template_options'] . "\">
					<option value=\"template_templates\">" . $vbphrase['edit_templates'] . "</option>
					<option value=\"template_addtemplate\">" . $vbphrase['add_new_template'] . "</option>
					" . iif($styleid != -1, "<option value=\"template_revertall\">" . $vbphrase['revert_all_templates'] . "</option>") . "
				</optgroup>
				<optgroup label=\"" . $vbphrase['edit_fonts_colors_etc'] . "\">
					<option value=\"css_all\">$vbphrase[all_style_options]</option>
					<option value=\"css_templates\">$vbphrase[common_templates]</option>
					<option value=\"stylevar\" selected=\"selected\">$vbphrase[stylevareditor]</option>
					" . iif($styleid != -1, "<option value=\"stylevar_revertall\">" . $vbphrase['revert_all_stylevars'] . "</option>") . "
					<option value=\"css_replacements\">$vbphrase[replacement_variables]</option>
				</optgroup>
				<optgroup label=\"" . $vbphrase['edit_style_options'] . "\">
					" . iif($styleid != -1, '<option value="template_editstyle">' . $vbphrase['edit_settings_gstyle'] . '</option>') . "
					<option value=\"template_addstyle\">" . $vbphrase['add_child_style'] . "</option>
					<option value=\"template_download\">" . $vbphrase['download'] . "</option>
					" . iif($styleid != -1, '<option value="template_delete" class="col-c">' . $vbphrase['delete_style'] . '</option>') . "
				</optgroup>
			</select><input type=\"button\" class=\"button\" value=\"$vbphrase[go]\" onclick=\"Sdo(this.form.styleEdit_$printstyleid.options[this.form.styleEdit_$printstyleid.selectedIndex].value, $styleid);\" />
			&nbsp;
			<input type=\"button\" class=\"button\" tabindex=\"1\"
			value=\"" . iif($showstyle, COLLAPSECODE, EXPANDCODE) . "\" title=\"" . iif($showstyle, $vbphrase['collapse_templates'], $vbphrase['expand_templates']) . "\"
			onclick=\"window.location='" . $onclicklink . "';\" />
			&nbsp;
		</td>
	</tr>
	</table>
	<!-- end header row for style '.$style[styleid]' -->
	";

	if ($showstyle)
	{

		if (empty($searchstring))
		{
			$searchconds = '';
		}
		elseif ($titlesonly)
		{
			$searchconds = "AND t1.title LIKE('%" . $vbulletin->db->escape_string_like($searchstring) . "%')";
		}
		else
		{
			$searchconds = "AND ( t1.title LIKE('%" . $vbulletin->db->escape_string_like($searchstring) . "%') OR template_un LIKE('%" . $vbulletin->db->escape_string_like($searchstring) . "%') ) ";
		}

		// query templates
		if (!empty($style['templatelist']))
		{
			$templatelist = unserialize($style['templatelist']);
			if (is_array($templatelist))
			{
				$templateids = implode(',' , $templatelist);
				if (!empty($templateids))
				{
					$searchconds .= "
							AND
						templateid IN($templateids)
					";
				}
			}
		}

		$specials = vB_Api::instanceInternal('template')->fetchSpecialTemplates();

		$templates = $vbulletin->db->query_read("
			SELECT templateid, IF(((t1.title LIKE '%.css') AND (t1.title NOT like 'css_%')),
				CONCAT('csslegacy_', t1.title), title) AS title, styleid, templatetype, dateline, username
			FROM " . TABLE_PREFIX . "template AS t1
			WHERE
				templatetype IN('template', 'replacement') $searchconds
			AND title NOT IN('" . implode("', '", $specials) . "')
			ORDER BY title
		");
		// just exit if no templates found
		$numtemplates = $vbulletin->db->num_rows($templates);
		if ($numtemplates == 0)
		{
			return;
		}

		echo "\n<!-- start template list for style '$style[styleid]' -->\n";

		if (FORMTYPE)
		{
			echo "<table cellpadding=\"0\" cellspacing=\"10\" border=\"0\" align=\"center\"><tr valign=\"top\">\n";
			echo "<td>\n<select name=\"tl$THISstyleid\" id=\"templatelist$THISstyleid\" class=\"darkbg\" size=\"" . TEMPLATE_EDITOR_ROWS . "\" style=\"width:450px\"\n\t";
			echo "onchange=\"Tprep(this.options[this.selectedIndex], $THISstyleid, 1);";
			echo "\"\n\t";
			echo "ondblclick=\"Tdo(Tprep(this.options[this.selectedIndex], $THISstyleid, 0), '');\">\n";
			echo "\t<option class=\"templategroup\" value=\"\">- - " . construct_phrase($vbphrase['x_templates'], $style['title']) . " - -</option>\n";
		}
		else
		{
			$expandall = vB5_Route::buildUrl('admincp|fullurl', array('file' => 'template'), array('do'=>'modify', 'group'=>'all', 'expandset'=>$expandset));
			$collapseall = vB5_Route::buildUrl('admincp|fullurl', array('file' => 'template'), array('do'=>'modify', 'expandset'=>$expandset));

			echo "<center><div class=\"darkbg\" style=\"padding: 4px; border: 2px inset; margin: 8px; text-align: " . vB_Template_Runtime::fetchStyleVar('left') . ";" . (is_browser('opera') ? " padding-" . vB_Template_Runtime::fetchStyleVar('left') . ":20px;" : '') . "\">\n<ul>\n";
			echo '<li class="templategroup"><b>' . $vbphrase['all_template_groups'] . '</b>' .
				construct_link_code("<b>" . EXPANDCODE . "</b>", "template.php?" . $expandall, 0, $vbphrase['expand_all_template_groups']).
				construct_link_code("<b>" . COLLAPSECODE . "</b>", "template.php?" . $collapseall, 0, $vbphrase['collapse_all_template_groups']).
				"<br />&nbsp;</li>\n";
		}

		while ($template = $vbulletin->db->fetch_array($templates))
		{
			if ($template['templatetype'] == 'replacement')
			{
				$replacements["$template[templateid]"] = $template;
			}
			else
			{
				// don't show special templates
				if (in_array($template['title'], vB_Api::instanceInternal('template')->fetchSpecialTemplates()))
				{
					continue;
				}
				else
				{
					$m = substr(strtolower($template['title']), 0, iif($n = strpos($template['title'], '_'), $n, strlen($template['title'])));
					if ($template['styleid'] != -1 AND !isset($masterset["$template[title]"]) AND !isset($only["$m"]))
					{
						$customtemplates["$template[templateid]"] = $template;
					}
					else
					{
						$maintemplates["$template[templateid]"] = $template;
					}
				}
			}
		}

		// custom templates
		if (!empty($customtemplates))
		{

			if (FORMTYPE)
			{
				echo "<optgroup label=\"\">\n";
				echo "\t<option class=\"templategroup\" value=\"\">" . $vbphrase['custom_templates'] . "</option>\n";
			}
			else
			{
				echo "<li class=\"templategroup\"><b>" . $vbphrase['custom_templates'] . "</b>\n<ul class=\"ldi\">\n";
			}

			foreach($customtemplates AS $template)
			{
				echo $SHOWTEMPLATE($template, $styleid, 1); vbflush();
			}

			if (FORMTYPE)
			{
				echo "</optgroup><!--<optgroup label=\"\"></optgroup>-->";
			}
			else

			{
				echo "</li>\n</ul>\n";
			}
		}

		// main templates
		if (!empty($maintemplates))
		{

			$lastgroup = '';
			$echo_ul = 0;

			foreach($maintemplates AS $template)
			{
				$showtemplate = 1;
				if (!empty($lastgroup) AND isTemplateInGroup($template['title'], $lastgroup))
				{
					if ($group == 'all' OR $group == $lastgroup)
					{
						echo $SHOWTEMPLATE($template, $styleid, $echo_ul);
						vbflush();
					}
				}
				else
				{
					foreach($only AS $thisgroup => $display)
					{
						if ($lastgroup != $thisgroup AND $echo_ul == 1)
						{
							if (FORMTYPE)
							{
								// do nothing
								echo "</optgroup><!--<optgroup label=\"\"></optgroup>-->\n";
							}
							else

							{
								echo "\t</ul>\n</li>\n";
							}
							$echo_ul = 0;
						}
						if (isTemplateInGroup($template['title'], $thisgroup))
						{
							$lastgroup = $thisgroup;
							if ($group == 'all' OR $group == $lastgroup)
							{
								if (FORMTYPE)
								{
									echo "<optgroup label=\"\">\n";
									echo "\t<option class=\"templategroup\" value=\"[]\"" . iif($group == $thisgroup AND empty($vbulletin->GPC['templateid']), ' selected="selected"', '') . ">" . construct_phrase($vbphrase['x_templates'], $display) . " &laquo;</option>\n";
								}
								else
								{
									echo "<li class=\"templategroup\"><b>" . construct_phrase($vbphrase['x_templates'], $display) . "</b>" . construct_link_code("<b>" . COLLAPSECODE . "</b>", "template.php?" . vB::getCurrentSession()->get('sessionurl') . "expandset=$expandset\" name=\"$thisgroup", 0, $vbphrase['collapse_template_group']) . "\n";
									echo "\t<ul class=\"ldi\">\n";
								}
								$echo_ul = 1;
							}
							else
							{
								if (FORMTYPE)
								{
									echo "\t<option class=\"templategroup\" value=\"[$thisgroup]\">" . construct_phrase($vbphrase['x_templates'], $display) . " &raquo;</option>\n";
								}
								else
								{
									echo "<li class=\"templategroup\"><b>" . construct_phrase($vbphrase['x_templates'], $display) . "</b>" . construct_link_code('<b>' . EXPANDCODE . '</b>', "template.php?" . vB::getCurrentSession()->get('sessionurl') . "group=$thisgroup&amp;expandset=$expandset#$thisgroup", 0, $vbphrase['expand_template_group']) . "</li>\n";
								}
								$showtemplate = 0;
							}
							break;
						}
					} // end foreach($only)

					if ($showtemplate)
					{
						echo $SHOWTEMPLATE($template, $styleid, $echo_ul);
						vbflush();
					}
				} // end if template string same AS last
			} // end foreach ($maintemplates)
		}

		if (FORMTYPE)
		{

			echo "</select>\n";
			echo "</td>\n<td width=\"100%\" align=\"center\" valign=\"top\">";
			echo "
			<table cellpadding=\"4\" cellspacing=\"1\" border=\"0\" class=\"tborder\" width=\"300\">
			<tr align=\"center\">
				<td class=\"tcat\"><b>$vbphrase[controls]</b></td>
			</tr>
			<tr>
				<td class=\"alt2\" align=\"center\" style=\"font: 11px tahoma, verdana, arial, helvetica, sans-serif\">
					<input type=\"button\" class=\"button\" style=\"font-weight: normal\" value=\"$vbphrase[customize_gstyle]\" id=\"cust$THISstyleid\" onclick=\"buttonclick(this, {$THISstyleid},'');\" />
					<input type=\"button\" class=\"button\" style=\"font-weight: normal\" value=\"" . trim(construct_phrase($vbphrase['expand_x'], '')) . '/' . trim(construct_phrase($vbphrase['collapse_x'], '')) . "\" id=\"expa$THISstyleid\" onclick=\"buttonclick(this, {$THISstyleid}, '');\" /><br />
					<input type=\"button\" class=\"button\" style=\"font-weight: normal\" value=\" $vbphrase[edit] \" id=\"edit$THISstyleid\" onclick=\"buttonclick(this,{$THISstyleid}, '');\" />
					<input type=\"button\" class=\"button\" style=\"font-weight: normal\" value=\"$vbphrase[view_original]\" id=\"orig$THISstyleid\" onclick=\"buttonclick(this, {$THISstyleid}, 'vieworiginal');\" />
					<input type=\"button\" class=\"button\" style=\"font-weight: normal\" value=\"$vbphrase[revert_gcpglobal]\" id=\"kill$THISstyleid\" onclick=\"buttonclick(this, {$THISstyleid}, 'killtemplate');\" />
					<div class=\"darkbg\" style=\"margin: 4px; padding: 4px; border: 2px inset; text-align: " . vB_Template_Runtime::fetchStyleVar('left') . "\" id=\"helparea$THISstyleid\">
						" . construct_phrase($vbphrase['x_templates'], '<b>' . $style['title'] . '</b>') . "
					</div>
					<input type=\"button\" class=\"button\" value=\"" . EXPANDCODE . "\" title=\"" . $vbphrase['expand_all_template_groups'] . "\" onclick=\"Texpand('all', '$expandset');\" />
					<b>" . $vbphrase['all_template_groups'] . "</b>
					<input type=\"button\" class=\"button\" value=\"" . COLLAPSECODE . "\" title=\"" . $vbphrase['collapse_all_template_groups'] . "\" onclick=\"Texpand('', '$expandset');\" />
				</td>
			</tr>
			</table>
			<br />
			<table cellpadding=\"4\" cellspacing=\"1\" border=\"0\" class=\"tborder\" width=\"300\">
			<tr align=\"center\">
				<td class=\"tcat\"><b>$vbphrase[color_key]</b></td>
			</tr>
			<tr>
				<td class=\"alt2\">
				<div class=\"darkbg\" style=\"margin: 4px; padding: 4px; border: 2px inset; text-align: " . vB_Template_Runtime::fetchStyleVar('left') . "\">
				<span class=\"col-g\">" . $vbphrase['template_is_unchanged_from_the_default_style'] . "</span><br />
				<span class=\"col-i\">" . $vbphrase['template_is_inherited_from_a_parent_style'] . "</span><br />
				<span class=\"col-c\">" . $vbphrase['template_is_customized_in_this_style'] . "</span>
				</div>
				</td>
			</tr>
			</table>
			";

			/*
			// might come back to this at some point...
			if (!empty($replacements))
			{
				$numreplacements = sizeof($replacements);
				echo "<br />\n<b>Replacement Variables:</b><br />\n<select name=\"rep$THISstyleid\" size=\"" . iif($numreplacements > ADMIN_MAXREPLACEMENTS, ADMIN_MAXREPLACEMENTS, $numreplacements) . "\" class=\"bginput\" style=\"width:350px\">\n";
				foreach($replacements AS $replacement)
				{
					echo $SHOWTEMPLATE($replacement, $styleid, 0, 1);
					vbflush();
				}
				echo "</select>\n";
			}
			*/

			echo "\n</td>\n</tr>\n</table>\n
			<script type=\"text/javascript\">
			<!--
			if (document.forms.tform.tl$THISstyleid.selectedIndex > 0)
			{
				Tprep(document.forms.tform.tl$THISstyleid.options[document.forms.tform.tl$THISstyleid.selectedIndex], $THISstyleid, 1);
			}
			//-->
			</script>";

		}
		else
		{
			echo "</ul>\n</div></center>\n";
		}

		echo "<!-- end template list for style '$style[styleid]' -->\n\n";

	} // end if($showstyle)

} // end function


function isTemplateInGroup($templatename, $groupname)
{
	return (strpos(strtolower(" $templatename"), $groupname) == 1);
}

// #############################################################################
/**
* Constructs a single template item for the style editor form
*
* @param	array	Template info array
* @param	integer	Style ID of style being shown
* @param	boolean	No longer used
* @param	boolean	HTMLise template titles?
*
* @return	string	Template <option>
*/
function construct_template_option($template, $styleid, $doindent = false, $htmlise = true)
{
	global $vbulletin;

	$template['title'] = preg_replace('#^csslegacy_(.*)#i', '\\1', $template['title']);

	if ($vbulletin->GPC['templateid'] == $template['templateid'])
	{
		$selected = ' selected="selected"';
	}
	else
	{
		$selected = '';
	}

	if ($htmlise)
	{
		$template['title'] = htmlspecialchars_uni($template['title']);
	}

	if ($doindent)
	{
		$indent = "\t";
	}
	else
	{
		$indent = '';
	}

	if ($styleid == -1)
	{
		return "\t<option value=\"$template[templateid]\" i=\"$template[username];$template[dateline]\"$selected>$indent$template[title]</option>\n";
	}
	else
	{
		switch ($template['styleid'])
		{
			// template is inherited from the master set
			case 0:
			case -1:
			{
				return "\t<option class=\"col-g\" value=\"~\" i=\"$template[username];$template[dateline]\"$selected>$indent$template[title]</option>\n";
			}

			// template is customized for this specific style
			case $styleid:
			{
				return "\t<option class=\"col-c\" value=\"$template[templateid]\" i=\"$template[username];$template[dateline]\"$selected>$indent$template[title]</option>\n";
			}

			// template is customized in a parent style - (inherited)
			default:
			{
				return "\t<option class=\"col-i\" value=\"[$template[templateid]]\" tsid=\"$template[styleid]\" i=\"$template[username];$template[dateline]\" tsid=\"$template[styleid]\"$selected>$indent$template[title]</option>\n";
			}
		}
	}
}

// #############################################################################
/**
* Equivalent to construct_template_option(), but creates an <a> instead of an <option>
*
* @param	array	Template info array
* @param	integer	Style ID of style being shown
* @param	boolean	Indent HTML code?
* @param	boolean	Not used any more
*
* @return	string	Template <a> link
*/
function construct_template_link($template, $styleid, $doindent = false, $htmlise = false)
{
	global $LINKEXTRA, $info, $templateid, $vbulletin;

	$template['title'] = preg_replace('#^css_(.*)#i', '\\1', $template['title']);

	if ($doindent)
	{
		$indent = "\t";
	}
	else
	{
		$indent = '';
	}

	if ($styleid == -1)
	{ // (debug option)
		return "$indent<li class=\"col-g\">$template[title]" .
			construct_link_code($vbphrase['edit'], "template.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;templateid=$template[templateid]&amp;dostyleid=$template[styleid]$LINKEXTRA").
			construct_link_code($vbphrase['delete'], "template.php?" . vB::getCurrentSession()->get('sessionurl') . "do=delete&amp;templateid=$template[templateid]&amp;dostyleid=$template[styleid]$LINKEXTRA").
		"</li>\n";
	}
	else
	{
		switch ($template['styleid'])
		{
			case -1: // template is inherited from the master set
				return "$indent<li class=\"col-g\">$template[title]" .
					construct_link_code($vbphrase['customize_gstyle'], "template.php?" . vB::getCurrentSession()->get('sessionurl') . "do=add&amp;dostyleid=$styleid&amp;title=" . urlencode($template['title']) . "$LINKEXTRA") . "</li>\n";
			case $styleid: // template is customized for this specific style
				return "$indent<li class=\"col-c\">$template[title]" .
					construct_link_code($vbphrase['edit'], "template.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;templateid=$template[templateid]&amp;dostyleid=$template[styleid]$LINKEXTRA").
					construct_link_code($vbphrase['revert_gcpglobal'], "template.php?" . vB::getCurrentSession()->get('sessionurl') . "do=delete&amp;templateid=$template[templateid]&amp;dostyleid=$template[styleid]$LINKEXTRA").
					construct_link_code($vbphrase['view_original'], "template.php?" . vB::getCurrentSession()->get('sessionurl') . "do=view&amp;title=" . urlencode($template['title']), 1).
				"</li>\n";
			default: // template is customized in a parent style - (inherited)
				return "$indent<li class=\"col-i\">$template[title]" .
					construct_link_code($vbphrase['customize_further'], "template.php?" . vB::getCurrentSession()->get('sessionurl') . "do=add&amp;dostyleid=$styleid&amp;templateid=$template[templateid]$LINKEXTRA").
					construct_link_code($vbphrase['view_original'], "template.php?" . vB::getCurrentSession()->get('sessionurl') . "do=view&amp;title=" . urlencode($template['title']), 1).
				"</li>\n";
		}
	}

}

// #############################################################################
/**
* Processes a template into PHP code for eval()
*
* @param	string	Unprocessed template
* @param	boolean	Halt on error?
*
* @return	string
*/
function process_template_conditionals($template, $haltonerror = true)
{
//	throw new Exception('old template syntax -- DO NOT WANT');
	$vbphrase = vB_Api::instanceInternal('phrase')->fetch(array(
		'template_condition_contains_functions', 'function_name', 'usage_in_expression', 'with_a_few_exceptions_function_calls_are_not_permitted', 'vbulletin_message', 'go_back'
	));

	$if_lookfor = '<if condition=';
	$if_location = -1;
	$if_end_lookfor = '</if>';
	$if_end_location = -1;

	$else_lookfor = '<else />';
	$else_location = -1;

	$condition_value = '';
	$true_value = '';
	$false_value = '';

	$template_cond = $template;

	static $safe_functions = array();
	if (empty($safe_functions))
	{
		$safe_functions = array(
			// logical stuff
			0 => 'and',                   // logical and
			1 => 'or',                    // logical or
			2 => 'xor',                   // logical xor

			// built-in variable checking functions
			'in_array',                   // used for checking
			'is_array',                   // used for checking
			'is_numeric',                 // used for checking
			'isset',                      // used for checking
			'empty',                      // used for checking
			'defined',                    // used for checking
			'array',                      // used for checking
			'gmdate',                     // used by ad manager
			'mktime',                     // used by ad manager
			'gmmktime',                   // used by ad manager

			// type casting functions, harmless
			'intval',											//used by some vb5 templates.

			// vBulletin-defined functions
			'can_moderate',               // obvious one
			'can_moderate_calendar',      // another obvious one
			'exec_switch_bg',             // harmless function that we use sometimes
			'is_browser',                 // function to detect browser and versions
			'is_member_of',               // function to check if $user is member of $usergroupid
			'is_came_from_search_engine', // function to check whether or not user came from search engine for ad manager
			'vbdate',                     // function to check date range for ad manager
		);

		// Legacy Hook 'template_safe_functions' Removed //
	}

	// #############################################################################

	while (1)
	{

		$condition_end = 0;
		$strlen = strlen($template_cond);

		$if_location = strpos($template_cond, $if_lookfor, $if_end_location + 1); // look for opening <if>
		if ($if_location === false)
		{ // conditional started not found
			break;
		}

		$condition_start = $if_location + strlen($if_lookfor) + 2; // the beginning of the conditional

		$delimiter = $template_cond[$condition_start - 1];
		if ($delimiter != '"' AND $delimiter != '\'')
		{ // ensure the conditional is surrounded by a valid character
			$if_end_location = $if_location + 1;
			continue;
		}

		$if_end_location = strpos($template_cond, $if_end_lookfor, $condition_start + 3); // location of conditional terminator
		if ($if_end_location === false)
		{ // move this code above the rest, if no end condition is found then the code below would get stuck
			return str_replace("\\'", '\'', $template_cond); // no </if> found -- return the original template
		}

		for ($i = $condition_start; $i < $strlen; $i++)
		{ // find the end of the conditional
			if ($template_cond["$i"] == $delimiter AND $template_cond[$i - 2] != '\\' AND $template_cond[$i + 1] == '>')
			{ // this char is delimiter and not preceded by backslash
				$condition_end = $i - 1;
				break;
			}
		}
		if (!$condition_end)
		{ // couldn't find an end to the condition, so don't even parse the template anymore
			return str_replace("\\'", '\'', $template_cond);
		}

		$condition_value = substr($template_cond, $condition_start, $condition_end-$condition_start);
		if (empty($condition_value))
		{
			// something went wrong
			$if_end_location = $if_location + 1;
			continue;
		}
		else if (strpos($condition_value, '`') !== false)
		{
			print_stop_message2(array('expression_contains_backticks_x_please_rewrite_without',  htmlspecialchars('<if condition="' . stripslashes($condition_value) . '">')));
		}
		else
		{
			if (preg_match_all('#([a-z0-9_\x7f-\xff\\\\{}$>-\\]]+)(\s|/\*.*\*/|(\#|//)[^\r\n]*(\r|\n))*\(#si', $condition_value, $matches))
			{
				$functions = array();
				foreach($matches[1] AS $key => $match)
				{
					if (!in_array(strtolower($match), $safe_functions))
					{
						$funcpos = strpos($condition_value, $matches[0]["$key"]);
						$functions[] = array(
							'func' => stripslashes($match),
							'usage' => stripslashes(substr($condition_value, $funcpos, (strpos($condition_value, ')', $funcpos) - $funcpos + 1))),
						);
					}
				}
				if (!empty($functions))
				{
					unset($safe_functions[0], $safe_functions[1], $safe_functions[2]);

					$errormsg = "
					$vbphrase[template_condition_contains_functions]:<br /><br />
					<code>" . htmlspecialchars('<if condition="' . stripslashes($condition_value) . '">') . '</code><br /><br />
					<table cellpadding="4" cellspacing="1" width="100%">
					<tr>
						<td class="thead">' . $vbphrase['function_name'] . '</td>
						<td class="thead">' . $vbphrase['usage_in_expression'] . '</td>
					</tr>';

					foreach($functions AS $error)
					{
						$errormsg .= "<tr><td class=\"alt2\"><code>" . htmlspecialchars($error['func']) . "</code></td><td class=\"alt2\"><code>" . htmlspecialchars($error['usage']) . "</code></td></tr>\n";
					}

					$errormsg .= "
					</table>
					<br />$vbphrase[with_a_few_exceptions_function_calls_are_not_permitted]<br />
					<code>". implode('() ', $safe_functions) . '()</code>';

					echo "<p>&nbsp;</p><p>&nbsp;</p>";
					print_form_header('', '', 0, 1, '', '65%');
					print_table_header($vbphrase['vbulletin_message']);
					print_description_row("<blockquote><br />$errormsg<br /><br /></blockquote>");
					print_table_footer(2, construct_button_code($vbphrase['go_back'], 'javascript:history.back(1)'));
					print_cp_footer();
				}
			}
		}

		if ($template_cond[$condition_end + 2] != '>')
		{ // the > doesn't come right after the condition must be malformed
			$if_end_location = $if_location + 1;
			continue;
		}

		// look for recursive case in the if block -- need to do this so the correct </if> is looked at
		$recursive_if_loc = $if_location;
		while (1)
		{
			$recursive_if_loc = strpos($template_cond, $if_lookfor, $recursive_if_loc + 1); // find an if case
			if ($recursive_if_loc === false OR $recursive_if_loc >= $if_end_location)
			{ //not found or out of bounds
				break;
			}

			// the bump first level's recursion back one </if> at a time
			$recursive_if_end_loc = $if_end_location;
			$if_end_location = strpos($template_cond, $if_end_lookfor, $recursive_if_end_loc + 1);
			if ($if_end_location === false)
			{
				return str_replace("\\'", "'", $template_cond); // no </if> found -- return the original template
			}
		}

		$else_location = strpos($template_cond, $else_lookfor, $condition_end + 3); // location of false portion

		// this is needed to correctly identify the <else /> tag associated with the outermost level
		while (1)
		{
			if ($else_location === false OR $else_location >= $if_end_location)
			{ // else isn't found/in a valid area
				$else_location = -1;
				break;
			}

			$temp = substr($template_cond, $condition_end + 3, $else_location - $condition_end + 3);
			$opened_if = substr_count($temp, $if_lookfor); // <if> tags opened between the outermost <if> and the <else />
			$closed_if = substr_count($temp, $if_end_lookfor); // <if> tags closed under same conditions
			if ($opened_if == $closed_if)
			{ // if this is true, we're back to the outermost level
				// and this is the correct else
				break;
			}
			else
			{
				// keep looking for correct else case
				$else_location = strpos($template_cond, $else_lookfor, $else_location + 1);
			}
		}

		if ($else_location == -1)
		{ // no else clause
			$read_length = $if_end_location - strlen($if_end_lookfor) + 1 - $condition_end + 1; // number of chars to read
			$true_value = substr($template_cond, $condition_end + 3, $read_length); // the true portion
			$false_value = '';
		}
		else
		{
			$read_length = $else_location - $condition_end - 3; // number of chars to read
			$true_value = substr($template_cond, $condition_end + 3, $read_length); // the true portion

			$read_length = $if_end_location - strlen($if_end_lookfor) - $else_location - 3; // number of chars to read
			$false_value = substr($template_cond, $else_location + strlen($else_lookfor), $read_length); // the false portion
		}

		if (strpos($true_value, $if_lookfor) !== false)
		{
			$true_value = process_template_conditionals($true_value);
		}
		if (strpos($false_value, $if_lookfor) !== false)
		{
			$false_value = process_template_conditionals($false_value);
		}

		// clean up the extra slashes
		$str_find = array('\\"', '\\\\');
		$str_replace = array('"', '\\');
		if ($delimiter == "'")
		{
			$str_find[] = "\\'";
			$str_replace[] = "'";
		}

		$str_find[] = '\\$delimiter';
		$str_replace[] =  $delimiter;

		$condition_value = str_replace($str_find, $str_replace, $condition_value);

		if (!function_exists('replace_template_variables'))
		{
			require_once(DIR . '/includes/functions_misc.php');
		}

		$condition_value = replace_template_variables($condition_value, true);

		$conditional = "\".(($condition_value) ? (\"$true_value\") : (\"$false_value\")).\"";
		$template_cond = substr_replace($template_cond, $conditional, $if_location, $if_end_location + strlen($if_end_lookfor) - $if_location);


/*echo "
<pre>-----
if_location:      ".htmlspecialchars_uni($if_location)."
delimiter:        ".htmlspecialchars_uni($delimiter)."
condition_start:  ".htmlspecialchars_uni($condition_start)."
condition_end:    ".htmlspecialchars_uni($condition_end)."
condition_value:  ".htmlspecialchars_uni($condition_value)."
else_location:    ".htmlspecialchars_uni($else_location)."
if_end_location:  ".htmlspecialchars_uni($if_end_location)."
true_value:       ".htmlspecialchars_uni($true_value)."
false_value:      ".htmlspecialchars_uni($false_value)."
conditional:      ".htmlspecialchars_uni($conditional)."
--------------
" . htmlspecialchars_uni($template_cond) . "
-----</pre>
";*/

		$if_end_location = $if_location + strlen($conditional) - 1; // adjust searching position for the replacement above
	}

	return str_replace("\\'", "'", $template_cond);
}

// #############################################################################
/*
* Processes {link thread[,] $threadinfo[[,] $pageinfo]} into fetch_seo_url('thread', $threadinfo[, $pageinfo]);
* @param	string	Text to be processed
*
* @return	string
*/
function process_seo_urls($template)
{
	$search = array(
		'#{link \s*([a-z_\|]+)(?:,|\s)\s*(\$[a-z_\[\]]+)\s*(?:(?:,|\s)\s*(?:(\$[a-z_\[\]]+|null)(?:\s*(?:,|\s)\s*\'([a-z_]+)\'\s*(?:,|\s)\s*\'([a-z_]+)\')?))?\s*}#si',
	);

	$text = preg_replace_callback($search, 'process_seo_urls_callback', $template);

	return $text;
}

// #############################################################################
/*
* Callback for process_seo_urls() to handle variable variables into fetch_seo_url
* @param	array	Matches from preg_replace
*
* @return	string
*/
function process_seo_urls_callback($matches)
{
	$search = array(
		'#\[#',
		'#\]#',
		'#\$bbuserinfo#',
	);
	$replace = array(
		'[\'',
		'\']',
		'$GLOBALS[\'vbulletin\']->userinfo',
	);
	$matches[2] = preg_replace($search, $replace, $matches[2]);

	switch (count($matches))
	{
		case 3:
			return '" . fetch_seo_url(\'' . $matches[1] . '\', ' . $matches[2] . ') . "';
		case 4:
			$matches[3] = preg_replace($search, $replace, $matches[3]);
			return '" . fetch_seo_url(\'' . $matches[1] . '\', ' . $matches[2] . ', ' . $matches[3] . ') . "';
		case 6:
			$matches[3] = preg_replace($search, $replace, $matches[3]);
			return '" . fetch_seo_url(\'' . $matches[1] . '\', ' . $matches[2] . ', ' . $matches[3] . ', \'' . $matches[4] . '\', \'' . $matches[5] . '\') . "';
		default:
			return $matches[0];
	}
}

// #############################################################################
/**
* Processes <phrase> tags into construct_phrase() PHP code for eval
*
* @param	string	Name of tag
* @param	string	Text to be processed
* @param	string	Name of processor function
* @param	string	Extra arguments for processor function
*
* @return	string
*/
function process_template_phrases($tagname, $text, $functionhandle, $extraargs = '')
{
	$tagname = strtolower($tagname);
	$open_tag = "<$tagname";
	$open_tag_len = strlen($open_tag);
	$close_tag = "</$tagname>";
	$close_tag_len = strlen($close_tag);

	$beginsearchpos = 0;
	do {
		$textlower = strtolower($text);
		$tagbegin = @strpos($textlower, $open_tag, $beginsearchpos);
		if ($tagbegin === false)
		{
			break;
		}

		$strlen = strlen($text);

		// we've found the beginning of the tag, now extract the options
		$inquote = '';
		$found = false;
		$tagnameend = false;
		for ($optionend = $tagbegin; $optionend <= $strlen; $optionend++)
		{
			$char = $text{$optionend};
			if (($char == '"' OR $char == "'") AND $inquote == '')
			{
				$inquote = $char; // wasn't in a quote, but now we are
			}
			else if (($char == '"' OR $char == "'") AND $inquote == $char)
			{
				$inquote = ''; // left the type of quote we were in
			}
			else if ($char == '>' AND !$inquote)
			{
				$found = true;
				break; // this is what we want
			}
			else if (($char == '=' OR $char == ' ') AND !$tagnameend)
			{
				$tagnameend = $optionend;
			}
		}
		if (!$found)
		{
			break;
		}
		if (!$tagnameend)
		{
			$tagnameend = $optionend;
		}
		$offset = $optionend - ($tagbegin + $open_tag_len);
		$tagoptions = substr($text, $tagbegin + $open_tag_len, $offset);
		$acttagname = substr($textlower, $tagbegin + 1, $tagnameend - $tagbegin - 1);
		if ($acttagname != $tagname)
		{
			$beginsearchpos = $optionend;
			continue;
		}

		// now find the "end"
		$tagend = strpos($textlower, $close_tag, $optionend);
		if ($tagend === false)
		{
			break;
		}

		// if there are nested tags, this </$tagname> won't match our open tag, so we need to bump it back
		$nestedopenpos = strpos($textlower, $open_tag, $optionend);
		while ($nestedopenpos !== false AND $tagend !== false)
		{
			if ($nestedopenpos > $tagend)
			{ // the tag it found isn't actually nested -- it's past the </$tagname>
				break;
			}
			$tagend = strpos($textlower, $close_tag, $tagend + $close_tag_len);
			$nestedopenpos = strpos($textlower, $open_tag, $nestedopenpos + $open_tag_len);
		}
		if ($tagend === false)
		{
			$beginsearchpos = $optionend;
			continue;
		}

		$localbegin = $optionend + 1;
		$localtext = $functionhandle($tagoptions, substr($text, $localbegin, $tagend - $localbegin), $tagname, $extraargs);

		$text = substr_replace($text, $localtext, $tagbegin, $tagend + $close_tag_len - $tagbegin);

		// this adjusts for $localtext having more/less characters than the amount of text it's replacing
		$beginsearchpos = $tagbegin + strlen($localtext);
	} while ($tagbegin !== false);

	return $text;
}

// #############################################################################
/**
* Processes a <phrase> tag
*
* @param	string	Options
* @param	string	Text of phrase
*
* @return	string
*/
function parse_phrase_tag($options, $phrasetext)
{
	$options = stripslashes($options);

	$i = 1;
	$param = array();
	do
	{
		$attribute = parse_tag_attribute("$i=", $options);
		if ($attribute !== false)
		{
			$param[] = $attribute;
		}
		$i++;
	} while ($attribute !== false);

	if (sizeof($param) > 0)
	{
		$return = '" . construct_phrase("' . $phrasetext . '"';
		foreach ($param AS $argument)
		{
			$argument = str_replace(array('\\', '"'), array('\\\\', '\"'), $argument);
			$return .= ', "' . $argument . '"';
		}
		$return .= ') . "';
	}
	else
	{
		$return = $phrasetext;
	}

	return $return;
}

// #############################################################################
/**
* Parses an attribute within a <phrase>
*
* @param	string	Option
* @param	string	Text
*
* @return	string
*/
function parse_tag_attribute($option, $text)
{
	if (($position = strpos($text, $option)) !== false)
	{
		$delimiter = $position + strlen($option);
		if ($text{$delimiter} == '"')
		{ // read to another "
			$delimchar = '"';
		}
		else if ($text{$delimiter} == '\'')
		{
			$delimchar = '\'';
		}
		else
		{ // read to a space
			$delimchar = ' ';
		}
		$delimloc = strpos($text, $delimchar, $delimiter + 1);
		if ($delimloc === false)
		{
			$delimloc = strlen($text);
		}
		else if ($delimchar == '"' OR $delimchar == '\'')
		{
			// don't include the delimiters
			$delimiter++;
		}
		return trim(substr($text, $delimiter, $delimloc - $delimiter));
	}
	else
	{
		return false;
	}
}

// #############################################################################
/**
* Processes a raw template for conditionals, phrases etc into PHP code for eval()
*
* @param	string	Template
*	@deprecated -- this functionality has been moved to the template API.
* @return	string
*/
function compile_template($template, &$errors = array())
{
	$orig_template = $template;


	$template = preg_replace('#[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]#', '', $template);
	$new_syntax = (strpos($template, '<vb:') !== false OR strpos($template, '{vb:') !== false);
	$old_syntax = (strpos($template, '<if') !== false OR strpos($template, '<phrase') !== false);
	$maybe_old_syntax = preg_match('/(^|[^{])\$[a-z0-9_]+\[?/si', $template);

	if (!$new_syntax AND ($old_syntax OR $maybe_old_syntax))
	{
		$template = addslashes($template);
		$template = process_template_conditionals($template);
		$template = process_template_phrases('phrase', $template, 'parse_phrase_tag');
		$template = process_seo_urls($template);

		if (!function_exists('replace_template_variables') OR !function_exists('validate_string_for_interpolation'))
		{
			require_once(DIR . '/includes/functions_misc.php');
		}

		//only check the old style syntax, the new style doesn't use string interpolation and isn't affected
		//by this exploit.  The new syntax doesn't 100% pass this check.
		if(!validate_string_for_interpolation($template))
		{
			global $vbphrase;
			echo "<p>&nbsp;</p><p>&nbsp;</p>";
			print_form_header('', '', 0, 1, '', '65%');
			print_table_header($vbphrase['vbulletin_message']);
			print_description_row($vbphrase['template_text_not_safe']);
			print_table_footer(2, construct_button_code($vbphrase['go_back'], 'javascript:history.back(1)'));
			print_cp_footer();
			exit;
		}


		$template = replace_template_variables($template, false);

		$template = str_replace('\\\\$', '\\$', $template);

		if (function_exists('token_get_all'))
		{
			$tokens = @token_get_all('<?php $var = "' . $template . '"; ?' . '>');

			foreach ($tokens AS $token)
			{
				if (is_array($token))
				{
					switch ($token[0])
					{
						case T_INCLUDE:
						case T_INCLUDE_ONCE:
						case T_REQUIRE:
						case T_REQUIRE_ONCE:
						{
							global $vbphrase;
							echo "<p>&nbsp;</p><p>&nbsp;</p>";
							print_form_header('', '', 0, 1, '', '65%');
							print_table_header($vbphrase['vbulletin_message']);
							print_description_row($vbphrase['file_inclusion_not_permitted']);
							print_table_footer(2, construct_button_code($vbphrase['go_back'], 'javascript:history.back(1)'));
							print_cp_footer();
							exit;
						}
					}
				}
			}
		}
	}
	else
	{
		require_once(DIR . '/includes/class_template_parser.php');

		$parser = new vB_TemplateParser($orig_template);

		try
		{
			$parser->validate($errors);
		}
		catch (vB_Exception_TemplateFatalError $e)
		{
			global $vbphrase;
			echo "<p>&nbsp;</p><p>&nbsp;</p>";
			print_form_header('', '', 0, 1, '', '65%');
			print_table_header($vbphrase['vbulletin_message']);
			print_description_row($vbphrase[$e->getMessage()]);
			print_table_footer(2, construct_button_code($vbphrase['go_back'], 'javascript:history.back(1)'));
			print_cp_footer();
			exit;
		}

		$template = $parser->compile();

		// TODO: Reimplement these - if done, $session[], $bbuserinfo[], $vboptions will parse in the template without using {vb:raw, which isn't what we
		// necessarily want to happen
		/*
		if (!function_exists('replace_template_variables'))
		{
			require_once(DIR . '/includes/functions_misc.php');
		}
		$template = replace_template_variables($template, false);
		*/
	}

	if (function_exists('verify_demo_template'))
	{
		verify_demo_template($template);
	}

	// Legacy Hook 'template_compile' Removed //

	return $template;
}

// #############################################################################
/**
* Prints a row containing a <select> showing the available styles
*
* @param	string	Name for <select>
* @param	integer	Selected style ID
* @param	string	Name of top item in <select>
* @param	string	Title of row
* @param	boolean	Display top item?
*/
function print_style_chooser_row($name = 'parentid', $selectedid = -1, $topname = NULL, $title = NULL, $displaytop = true)
{
	global $vbphrase;

	if ($topname === NULL)
	{
		$topname = $vbphrase['no_parent_style'];
	}
	if ($title === NULL)
	{
		$title = $vbphrase['parent_style'];
	}

	$stylecache = vB_Library::instance('Style')->fetchStyles(false, false);

	$styles = array();

	if ($displaytop)
	{
		$styles['-1'] = $topname;
	}

	foreach($stylecache AS $style)
	{
		$styles["$style[styleid]"] = construct_depth_mark($style['depth'], '--', iif($displaytop, '--')) . " $style[title]";
	}

	print_select_row($title, $name, $styles, $selectedid);
}

// #############################################################################
/**
* If a template item is customized, returns HTML to allow revertion
*
* @param	integer	Style ID of template item
* @param	string	Template type (replacement / stylevar etc.)
* @param	string	Name of template record
*
* @return	array	array('info' => x, 'revertcode' => 'y')
*/
function construct_revert_code($itemstyleid, $templatetype, $varname)
{
	global $vbphrase, $vbulletin;

	if ($templatetype == 'replacement')
	{
		$revertword = 'delete';
	}
	else
	{
		$revertword = 'revert';
	}

	switch ($itemstyleid)
	{
		case -1:
			return array('info' => '', 'revertcode' => '&nbsp;');
		case $vbulletin->GPC['dostyleid']:
			return array('info' => "($vbphrase[customized_in_this_style])", 'revertcode' => "<label for=\"del_{$templatetype}_{$varname}\">" . $vbphrase["$revertword"] . "<input type=\"checkbox\" name=\"delete[$templatetype][$varname]\" id=\"del_{$templatetype}_{$varname}\" value=\"1\" tabindex=\"1\" title=\"" . $vbphrase["$revertword"] . "\" /></label>");
		default:
			return array('info' => '(' . construct_phrase($vbphrase['customized_in_a_parent_style_x'], $itemstyleid) . ')', 'revertcode' => '&nbsp;');
	}
}

// #############################################################################
/**
* Prints a row containing a textarea for editing one of the 'common templates'
*
* @param	string	Template variable name
*/
function print_common_template_row($varname)
{
	global $template_cache, $vbphrase, $vbulletin;

	$template = $template_cache['template']["$varname"];
	$description = $vbphrase["{$varname}_desc"];

	$color = fetch_inherited_color($template['styleid'], $vbulletin->GPC['dostyleid']);
	$revertcode = construct_revert_code($template['styleid'], 'template', $varname);

	print_textarea_row(
		"<b>$varname</b> <dfn>$description</dfn><span class=\"smallfont\"><br /><br />$revertcode[info]<br /><br />$revertcode[revertcode]</span>",
		"commontemplate[$varname]",
		$template['template_un'],
		8, 70, 1, 0, 'ltr',
		"$color\" style=\"font: 9pt courier new"
	);
}

// #############################################################################
/**
* Prints a row containing a textarea for editing a replacement variable
*
* @param	string	Find text
* @param	string	Replace text
* @param	integer	Number of rows for textarea
* @param	integer	Number of columns for textarea
*/
function print_replacement_row($find, $replace, $rows = 2, $cols = 50)
{
	global $replacement_info, $vbulletin;
	static $rcount = 0;

	$rcount++;

	$color = fetch_inherited_color($replacement_info["$find"], $vbulletin->GPC['dostyleid']);
	$revertcode = construct_revert_code($replacement_info["$find"], 'replacement', $rcount);

	construct_hidden_code("replacement[$rcount][find]", $find);
	print_cells_row(array(
		'<pre>' . htmlspecialchars_uni($find) . '</pre>',
		"\n\t<span class=\"smallfont\"><textarea name=\"replacement[$rcount][replace]\" class=\"$color\" rows=\"$rows\" cols=\"$cols\" tabindex=\"1\">" . htmlspecialchars_uni($replace) . "</textarea><br />$revertcode[info]</span>\n\t",
		"<span class=\"smallfont\">$revertcode[revertcode]</span>"
	));

}

// #############################################################################
/**
* Prints a row containing an input for editing a stylevar
*
* @param	string	Stylevar title
* @param	string	Stylevar varname
* @param	integer	Size of text box
*/
function print_stylevar_row($title, $varname, $size = 30, $validation_regex = '', $failsafe_value = '')
{
	global $stylevars, $stylevar_info, $vbulletin;

	$color = fetch_inherited_color($stylevar_info["$varname"], $vbulletin->GPC['dostyleid']);
	$revertcode = construct_revert_code($stylevar_info["$varname"], 'stylevar', $varname);

	if ($help = construct_table_help_button("stylevar[$varname]"))
	{
		$helplink = "&nbsp;$help";
	}

	if ($validation_regex != '')
	{
		construct_hidden_code("stylevar[_validation][$varname]", htmlspecialchars_uni($validation_regex));
		construct_hidden_code("stylevar[_failsafe][$varname]", htmlspecialchars_uni($failsafe_value));
	}

	print_cells_row(array(
		"<span title=\"\$stylevar[$varname]\">$title</span>",
		"<span class=\"smallfont\"><input type=\"text\" class=\"$color\" title=\"\$stylevar[$varname]\" name=\"stylevar[$varname]\" tabindex=\"1\" value=\"" . htmlspecialchars_uni($stylevars["$varname"]) . "\" size=\"$size\" dir=\"ltr\" /><br />$revertcode[info]</span>",
		"<span class=\"smallfont\">$revertcode[revertcode]</span>$helplink"
	));
}

// #############################################################################
/**
* Returns a row with an input box for use in the CSS editor
*
* @param	string	Title of item
* @param	array	Item info array
* @param	string	CSS class to display with
* @param	boolean	True if the value is a colour (will show colour picker widget)
* @param	integer	Size of input box
*
* @return	string
*/
function construct_css_input_row($title, $item, $class = 'bginput', $iscolor = false, $size = 30)
{
	global $css, $readonly, $color, $numcolors;

	eval('$value = $css' . $item . ';');
	$name = "css" . str_replace("['", "[", str_replace("']", "]", $item));

	if ($iscolor)
	{
		return construct_color_row($title, $name, $value, $class, $size - 8);
	}

	$value = htmlspecialchars_uni($value);
	$readonly = iif($readonly, ' readonly="readonly"', '');

	return "
		<tr>
			<td>$title</td>
			<td><input type=\"text\" class=\"$class\" name=\"$name\" value=\"$value\" title=\"\$$name\" tabindex=\"1\" size=\"$size\" dir=\"ltr\" /></td>
		</tr>
	";
}

// #############################################################################
/**
* Returns styles for post editor interface from template
*
* @param	string	Template contents
*
* @return	array
*/
function fetch_posteditor_styles($template)
{
	$item = array();

	preg_match_all('#([a-z0-9-]+):\s*([^\s].*);#siU', $template, $regs);

	foreach ($regs[1] AS $key => $cssname)
	{
		$item[strtolower($cssname)] = trim($regs[2]["$key"]);
	}

	return $item;
}

// #############################################################################
/**
* Returns a row containing a <select> for use in selecting text alignment
*
* @param	string	Item title
* @param	array	Item info array
*
* @return	string
*/
function construct_text_align_code($title, $item)
{
	// this is currently disabled
	return false;

	$alignoptions = array(
		'' => '(' . $vbphrase['inherit'] . ')',
		'left' => $vbphrase['align_left_gstyle'],
		'center' => $vbphrase['align_center_gstyle'],
		'right' => $vbphrase['align_right_gstyle'],
		'justify' => $vbphrase['justified']
	);

	eval("\$value = \$css" . $item . ";");
	return "\t\t<tr><td>$title</td><td>\n\t<select class=\"$color\" name=\"css" . str_replace("['", "[", str_replace("']", "]", $item)) . "\" tabindex=\"1\">\n" . construct_select_options($alignoptions, $value) . "\t</select>\n\t</td></tr>\n";
}

// #############################################################################
/**
* Returns a row containing an input and a color picker widget
*
* @param	string	Item title
* @param	string	Item varname
* @param	string	Item value
* @param	string	CSS class to display with
* @param	integer	Size of input box
* @param	boolean	Surround code with <tr> ... </tr> ?
*
* @return	string
*/
function construct_color_row($title, $name, $value, $class = 'bginput', $size = 22, $printtr = true)
{
	global $numcolors;

	$value = htmlspecialchars_uni($value);

	$html = '';
	if ($printtr)
	{
		$html .= "
		<tr>\n";
	}
	$html .= "
			<td>$title</td>
			<td>
				<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
				<tr>
					<td><input type=\"text\" class=\"$class\" name=\"$name\" id=\"color_$numcolors\" value=\"$value\" title=\"\$$name\" tabindex=\"1\" size=\"$size\" onchange=\"preview_color($numcolors)\" dir=\"ltr\" />&nbsp;</td>
					<td><div id=\"preview_$numcolors\" class=\"colorpreview\" onclick=\"open_color_picker($numcolors, event)\"></div></td>
				</tr>
				</table>
			</td>
	";
	if ($printtr)
	{
		$html .= "	</tr>\n";
	}

	$numcolors ++;

	return $html;
}

// #############################################################################
/**
* Prints a row containing an <input type="text" />
*
* @param	string	Title for row
* @param	string	Name for input field
* @param	string	Value for input field
* @param	boolean	Whether or not to htmlspecialchars the input field value
* @param	integer	Size for input field
* @param	integer	Max length for input field
* @param	string	Text direction for input field
* @param	mixed	If specified, overrides the default CSS class for the input field
*/
function print_color_input_row($title, $name, $value = '', $htmlise = true, $size = 35, $maxlength = 0, $direction = '', $inputclass = false)
{
	global $vbulletin, $numcolors, $vb5_config;

	$direction = verify_text_direction($direction);

	print_label_row(
		$title,
		"<div id=\"ctrl_$name\">
			<input style=\"float:" . vB_Template_Runtime::fetchStyleVar('left') . "; margin-" . vB_Template_Runtime::fetchStyleVar('right') . ": 4px\" type=\"text\" class=\"" . iif($inputclass, $inputclass, 'bginput') . "\" name=\"$name\" id=\"color_$numcolors\" value=\"" . iif($htmlise, htmlspecialchars_uni($value), $value) . "\" size=\"$size\"" . iif($maxlength, " maxlength=\"$maxlength\"") . " dir=\"$direction\" tabindex=\"1\"" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot;\"") . " onchange=\"preview_color($numcolors)\" />
			<div style=\"float:" . vB_Template_Runtime::fetchStyleVar('left') . "\" id=\"preview_$numcolors\" class=\"colorpreview\" onclick=\"open_color_picker($numcolors, event)\"></div>
		</div>",
		'', 'top', $name
	);

	$numcolors++;
}

// #############################################################################
/**
* Builds the color picker popup item for the style editor
*
* @param	integer	Width of each color swatch (pixels)
* @param	string	CSS 'display' parameter (default: 'none')
*
* @return	string
*/
function construct_color_picker($size = 12, $display = 'none')
{
	global $vbulletin, $colorPickerWidth, $colorPickerType;

	$previewsize = 3 * $size;
	$surroundsize = $previewsize * 2;
	$colorPickerWidth = 21 * $size + 22;

	$html = "
	<style type=\"text/css\">
	#colorPicker
	{
		background: black;
		position: absolute;
		left: 0px;
		top: 0px;
		width: {$colorPickerWidth}px;
	}
	#colorFeedback
	{
		border: solid 1px black;
		border-bottom: none;
		width: {$colorPickerWidth}px;
	}
	#colorFeedback input
	{
		font: 11px verdana, arial, helvetica, sans-serif;
	}
	#colorFeedback button
	{
		width: 19px;
		height: 19px;
	}
	#txtColor
	{
		border: inset 1px;
		width: 70px;
	}
	#colorSurround
	{
		border: inset 1px;
		white-space: nowrap;
		width: {$surroundsize}px;
		height: 15px;
	}
	#colorSurround td
	{
		background-color: none;
		border: none;
		width: {$previewsize}px;
		height: 15px;
	}
	#swatches
	{
		background-color: black;
		width: {$colorPickerWidth}px;
	}
	#swatches td
	{
		background: black;
		border: none;
		width: {$size}px;
		height: {$size}px;
	}
	</style>
	<div id=\"colorPicker\" style=\"display:$display\" oncontextmenu=\"switch_color_picker(1); return false\" onmousewheel=\"switch_color_picker(event.wheelDelta * -1); return false;\">
	<table id=\"colorFeedback\" class=\"tcat\" cellpadding=\"0\" cellspacing=\"4\" border=\"0\" width=\"100%\">
	<tr>
		<td><button onclick=\"col_click('transparent'); return false\"><img src=\"../cpstyles/" . $vbulletin->options['cpstylefolder'] . "/colorpicker_transparent.gif\" title=\"'transparent'\" alt=\"\" /></button></td>
		<td>
			<table id=\"colorSurround\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
			<tr>
				<td id=\"oldColor\" onclick=\"close_color_picker()\"></td>
				<td id=\"newColor\"></td>
			</tr>
			</table>
		</td>
		<td width=\"100%\"><input id=\"txtColor\" type=\"text\" value=\"\" size=\"8\" /></td>
		<td style=\"white-space:nowrap\">
			<input type=\"hidden\" name=\"colorPickerType\" id=\"colorPickerType\" value=\"$colorPickerType\" />
			<button onclick=\"switch_color_picker(1); return false\"><img src=\"../cpstyles/" . $vbulletin->options['cpstylefolder'] . "/colorpicker_toggle.gif\" alt=\"\" /></button>
			<button onclick=\"close_color_picker(); return false\"><img src=\"../cpstyles/" . $vbulletin->options['cpstylefolder'] . "/colorpicker_close.gif\" alt=\"\" /></button>
		</td>
	</tr>
	</table>
	<table id=\"swatches\" cellpadding=\"0\" cellspacing=\"1\" border=\"0\">\n";

	$colors = array(
		'00', '33', '66',
		'99', 'CC', 'FF'
	);

	$specials = array(
		'#000000', '#333333', '#666666',
		'#999999', '#CCCCCC', '#FFFFFF',
		'#FF0000', '#00FF00', '#0000FF',
		'#FFFF00', '#00FFFF', '#FF00FF'
	);

	$green = array(5, 4, 3, 2, 1, 0, 0, 1, 2, 3, 4, 5);
	$blue = array(0, 0, 0, 5, 4, 3, 2, 1, 0, 0, 1, 2, 3, 4, 5, 5, 4, 3, 2, 1, 0);

	for ($y = 0; $y < 12; $y++)
	{
		$html .= "\t<tr>\n";

		$html .= construct_color_picker_element(0, $y, '#000000');
		$html .= construct_color_picker_element(1, $y, $specials["$y"]);
		$html .= construct_color_picker_element(2, $y, '#000000');

		for ($x = 3; $x < 21; $x++)
		{
			$r = floor((20 - $x) / 6) * 2 + floor($y / 6);
			$g = $green["$y"];
			$b = $blue["$x"];

			$html .= construct_color_picker_element($x, $y, '#' . $colors["$r"] . $colors["$g"] . $colors["$b"]);
		}

		$html .= "\t</tr>\n";
	}

	$html .= "\t</table>
	</div>
	<script type=\"text/javascript\">
	<!--
	var tds = fetch_tags(fetch_object(\"swatches\"), \"td\");
	for (var i = 0; i < tds.length; i++)
	{
		tds[i].onclick = swatch_click;
		tds[i].onmouseover = swatch_over;
	}
	//-->
	</script>\n";

	return $html;
}

// #############################################################################
/**
* Builds a single color swatch for the color picker gadget
*
* @param	integer	Current X coordinate
* @param	integer	Current Y coordinate
* @param	string	Color
*
* @return	string
*/
function construct_color_picker_element($x, $y, $color)
{
	global $vbulletin;
	return "\t\t<td style=\"background:$color\" id=\"sw$x-$y\"><img src=\"" . vB::getDatastore()->getOption('bburl') . '/' . $vbulletin->options['cleargifurl'] . "\" alt=\"\" style=\"width:11px; height:11px\" /></td>\r\n";
}

// #############################################################################
/**
* Reads results of form submission and updates special templates accordingly
*
* @param	array	Array of data from form
* @param	string	Variable type
* @param	string	Variable type name
*/
function build_special_templates($newtemplates, $templatetype, $vartype)
{
	global $vbulletin, $template_cache;

	DEVDEBUG('------------------------');

	foreach ($template_cache["$templatetype"] AS $title => $oldtemplate)
	{
		// ignore the '_validation' and '_failsafe' keys
		if ($title == '_validation' OR $title == '_failsafe')
		{
			continue;
		}

		// just carry on if there is no data for the current $newtemplate
		if (!isset($newtemplates["$title"]))
		{
			DEVDEBUG("\$$vartype" . "['$title'] is not set");
			continue;
		}

		// if delete the customized template, delete and continue
		if ($vbulletin->GPC['delete']["$vartype"]["$title"])
		{
			if ($vbulletin->GPC['dostyleid'] != -1)
			{
				vB::getDbAssertor()->delete(
						'template',
						array(
							'title' => $title,
							'templatetype' => $templatetype,
							'styleid' => $vbulletin->GPC['dostyleid']
						)
				);
				DEVDEBUG("$vartype $title (reverted)");

				if ($templatetype == 'stylevar' AND $title == 'codeblockwidth')
				{
					$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "postparsed");
				}
			}
			continue;
		}

		// check for what to do with the template
		switch($templatetype)
		{
			case 'stylevar':
			{
				$newtemplate = $newtemplates["$title"];

				if (isset($newtemplates['_validation']["$title"]))
				{
					if (!preg_match($newtemplates['_validation']["$title"], $newtemplate))
					{
						$newtemplate = $newtemplates['_failsafe']["$title"];
					}
				}
				break;
			}
			case 'css':
				$newtemplate = serialize($newtemplates["$title"]);
				break;
			case 'replacement':
				$newtemplate = $newtemplates["$title"];
				break;
		}

		if ($newtemplate != $oldtemplate['template'])
		{
			// update existing $vartype template
			if ($oldtemplate['styleid'] == $vbulletin->GPC['dostyleid'])
			{
				vB::getDbAssertor()->update(
						'template',
						array(
							'template' => $newtemplate,
							'dateline' => vB::getRequest()->getTimeNow(),
							'username' => $vbulletin->userinfo['username']
						),
						array(
							'title' => $title,
							'templatetype' => $templatetype,
							'styleid' => $vbulletin->GPC['dostyleid']
						)
				);
				DEVDEBUG("$vartype $title (updated)");
			// insert new $vartype template
			}
			else
			{
				/*insert query*/
				vB::getDbAssertor()->update(
						'template',
						array(
								'title' => $title,
								'templatetype' => $templatetype,
								'styleid' => $vbulletin->GPC['dostyleid'],
								'template' => $newtemplate,
								'dateline' => vB::getRequest()->getTimeNow(),
								'username' => $vbulletin->userinfo['username']
						)
				);
				DEVDEBUG("$vartype $title (inserted)");
			}

			if ($templatetype == 'stylevar' AND $title == 'codeblockwidth')
			{
				$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "postparsed");
			}
		}
		else
		{
			DEVDEBUG("$vartype $title (not changed)");
		}

	} // end foreach($template_cache)

}

// #############################################################################
/**
* Prints a row containing template search javascript controls
*/
function print_template_javascript()
{
	global $vbphrase, $vbulletin;

	$vb5_options = vB::getDatastore()->getValue('options');
	print_phrase_ref_popup_javascript();

	echo '<script type="text/javascript" src="' . $vb5_options['bburl'] . '/clientscript/vbulletin_templatemgr.js?v=' . SIMPLE_VERSION . '"></script>';
	echo '<script type="text/javascript">
<!--
	var textarea_id = "' . $vbulletin->textarea_id . '";
	var vbphrase = { \'not_found\' : "' . fetch_js_safe_string($vbphrase['not_found']) . '" };
// -->
</script>
';

	print_label_row(iif(is_browser('ie') OR is_browser('mozilla', '20040707'), $vbphrase['search_in_template'], $vbphrase['additional_functions']), iif(is_browser('ie') OR is_browser('mozilla', '1.7'), '
	<input type="text" class="bginput" name="string" accesskey="t" value="' . htmlspecialchars_uni($vbulletin->GPC['searchstring']) . '" size="20" onChange="n=0;" tabindex="1" />
	<input type="button" class="button" style="font-weight:normal" value=" ' . $vbphrase['find'] . ' " accesskey="f" onClick="findInPage(document.cpform.string.value);" tabindex="1" />
	&nbsp;') .
	'<input type="button" class="button" style="font-weight:normal" value=" ' . $vbphrase['copy_gstyle'] . ' " accesskey="c" onclick="HighlightAll();" tabindex="1" />
	&nbsp;
	<input type="button" class="button" style="font-weight:normal" value="' . $vbphrase['view_quickref_gstyle'] . '" accesskey="v" onclick="js_open_phrase_ref(0, 0);" tabindex="1" />
	<script type="text/javascript">document.cpform.string.onkeypress = findInPageKeyPress;</script>
	');
}

// ###########################################################################################
// START XML STYLE FILE FUNCTIONS


function get_style_export_xml
(
	$styleid,
	$product,
	$product_version,
	$title,
	$mode
)
{
	//only is the (badly named) list of template groups
	global $vbulletin, $vbphrase, $only, $vb5_config;

	if (!$vb5_config)
	{
		$vb5_config =& vB::getConfig();
	}

	if ($styleid == -1)
	{
		// set the style title as 'master style'
		$style = array('title' => $vbphrase['master_style']);
		$sqlcondition = "styleid = -1";
		$parentlist = "-1";
		$is_master = true;
	}
	else
	{
		// query everything from the specified style
		$style = $vbulletin->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "style
			WHERE styleid = " . $styleid
		);

		//export as master -- export a style with all changes as a new master style.
		if ($mode == 2)
		{
			//only allowed in debug mode.
			if (!$vb5_config['Misc']['debug'])
			{
				print_cp_no_permission();
			}

			// get all items from this style and all parent styles
			$sqlcondition = "templateid IN(" . implode(',', unserialize($style['templatelist'])) . ")";
			$sqlcondition .= " AND title NOT LIKE 'vbcms_grid_%'";
			$parentlist = $style['parentlist'];
			$is_master = true;
			$title = $vbphrase['master_style'];
		}

		//export with parent styles
		else if ($mode == 1)
		{
			// get all items from this style and all parent styles (except master)
			$sqlcondition = "styleid <> -1 AND templateid IN(" . implode(',', unserialize($style['templatelist'])) . ")";
			//remove the master style id off the end of the list
			$parentlist = substr(trim($style['parentlist']), 0, -3);
			$is_master = false;
		}

		//this style only
		else
		{
			// get only items customized in THIS style
			$sqlcondition = "styleid = " . $styleid;
			$parentlist = $styleid;
			$is_master = false;
		}
	}

	if ($product == 'vbulletin')
	{
		$sqlcondition .= " AND (product = '" . vB::getDbAssertor()->escape_string($product) . "' OR product = '')";
	}
	else
	{
		$sqlcondition .= " AND product = '" . vB::getDbAssertor()->escape_string($product) . "'";
	}

	// set a default title
	if ($title == '' OR $styleid == -1)
	{
		$title = $style['title'];
	}

	// --------------------------------------------
	// query the templates and put them in an array

	$templates = array();

	$gettemplates = $vbulletin->db->query_read("
		SELECT title, templatetype, username, dateline, version,
		IF(templatetype = 'template', template_un, template) AS template
		FROM " . TABLE_PREFIX . "template
		WHERE $sqlcondition
		ORDER BY title
	");

	$ugcount = $ugtemplates = 0;
	while ($gettemplate = $vbulletin->db->fetch_array($gettemplates))
	{
		switch($gettemplate['templatetype'])
		{
			case 'template': // regular template

				// if we have ad template, and we are exporting as master, make sure we do not export the add data
				if (substr($gettemplate['title'], 0, 3) == 'ad_' AND $mode == 2)
				{
					$gettemplate['template'] = '';
				}

				$isgrouped = false;
				foreach(array_keys($only) AS $group)
				{
					if (strpos(strtolower(" $gettemplate[title]"), $group) == 1)
					{
						$templates["$group"][] = $gettemplate;
						$isgrouped = true;
					}
				}
				if (!$isgrouped)
				{
					if ($ugtemplates % 10 == 0)
					{
						$ugcount++;
					}
					$ugtemplates++;
					//sort ungrouped templates last.
					$ugcount_key = 'zzz' . str_pad($ugcount, 5, '0', STR_PAD_LEFT);
					$templates[$ugcount_key][] = $gettemplate;
					$only[$ugcount_key] = construct_phrase($vbphrase['ungrouped_templates_x'], $ugcount);
				}
			break;

			case 'stylevar': // stylevar
				$templates[$vbphrase['stylevar_special_templates']][] = $gettemplate;
			break;

			case 'css': // css
				$templates[$vbphrase['css_special_templates']][] = $gettemplate;
			break;

			case 'replacement': // replacement
				$templates[$vbphrase['replacement_var_special_templates']][] = $gettemplate;
			break;
		}
	}
	unset($gettemplate);
	$vbulletin->db->free_result($gettemplates);
	if (!empty($templates))
	{
		ksort($templates);
	}

	// --------------------------------------------
	// fetch stylevar-dfns

	$stylevarinfo = get_stylevars_for_export($product, $parentlist);
	$stylevar_cache = $stylevarinfo['stylevars'];
	$stylevar_dfn_cache = $stylevarinfo['stylevardfns'];

	if (empty($templates) AND empty($stylevar_cache) AND empty($stylevar_dfn_cache))
	{
		print_stop_message2('download_contains_no_customizations');
	}

	// --------------------------------------------
	// now output the XML

	require_once(DIR . '/includes/class_xml.php');
	$xml = new vB_XML_Builder();
	$xml->add_group('style',
		array(
			'name' => $title,
			'vbversion' => $product_version,
			'product' => $product,
			'type' => $is_master ? 'master' : 'custom'
		)
	);

	foreach($templates AS $group => $grouptemplates)
	{
		$xml->add_group('templategroup', array('name' => iif(isset($only["$group"]), $only["$group"], $group)));
		foreach($grouptemplates AS $template)
		{
			$xml->add_tag('template', $template['template'],
				array(
					'name' => htmlspecialchars($template['title']),
					'templatetype' => $template['templatetype'],
					'date' => $template['dateline'],
					'username' => $template['username'],
					'version' => htmlspecialchars_uni($template['version'])),
				true
			);
		}
		$xml->close_group();
	}

	$xml->add_group('stylevardfns');
	foreach ($stylevar_dfn_cache AS $stylevargroupname => $stylevargroup)
	{
		$xml->add_group('stylevargroup', array('name' => $stylevargroupname));
		foreach($stylevargroup AS $stylevar)
		{
			$xml->add_tag('stylevar', '',
				array(
					'name' => htmlspecialchars($stylevar['stylevarid']),
					'datatype' => $stylevar['datatype'],
					'validation' => base64_encode($stylevar['validation']),
					'failsafe' => base64_encode($stylevar['failsafe'])
				)
			);
		}
		$xml->close_group();
	}
	$xml->close_group();

	$xml->add_group('stylevars');
	foreach ($stylevar_cache AS $stylevarid => $stylevar)
	{
		$xml->add_tag('stylevar', '',
			array(
				'name' => htmlspecialchars($stylevar['stylevarid']),
				'value' => base64_encode($stylevar['value'])
			)
		);
	}
	$xml->close_group();

	$xml->close_group();

	$doc = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n";
	$doc .= $xml->output();
	$xml = null;
	return $doc;
}

/// #############################################################################
/**
* Reads XML style file and imports data from it into the database
*
* @param	string	XML data
* @param	integer	Style ID
* @param	integer	Parent style ID
* @param	string	New style title
* @param	boolean	Allow vBulletin version mismatch
* @param	integer	Display order for new style
* @param	boolean	Allow user selection of new style
* @param  int|null Starting template group index for this run of importing templates (0 based). Null means all templates (single run)
* @param  int|null
*
* @return	array	Array of information about the imported style
*/
function xml_import_style(
	$xml = false,
	$styleid = -1,
	$parentid = -1,
	$title = '',
	$anyversion = false,
	$displayorder = 1,
	$userselect = true,
	$startat = null,
	$perpage = null,
	$scilent = false
)
{
	// $GLOBALS['path'] needs to be passed into this function or reference $vbulletin->GPC['path']

	global $vbulletin;
	if (!$scilent)
	{
		$vbphrase = vB_Api::instanceInternal('phrase')->fetch(array('importing_style', 'please_wait', 'creating_a_new_style_called_x'));
		print_dots_start('<b>' . $vbphrase['importing_style'] . "</b>, $vbphrase[please_wait]", ':', 'dspan');
	}
	require_once(DIR . '/includes/class_xml.php');

	//where is this used?  I hate having this random global value in the middle of this function
	$xmlobj = new vB_XML_Parser($xml, $vbulletin->GPC['path']);
	if ($xmlobj->error_no() == 1)
	{
		if ($scilent)
		{
			throw new vB_Exception_AdminStopMessage('no_xml_and_no_path');
		}
		print_dots_stop();
		print_stop_message2('no_xml_and_no_path');
	}
	else if ($xmlobj->error_no() == 2)
	{
		if ($scilent)
		{
			throw new vB_Exception_AdminStopMessage(array('please_ensure_x_file_is_located_at_y', 'vbulletin-style.xml', $vbulletin->GPC['path']));
		}
		print_dots_stop();
		print_stop_message2(array('please_ensure_x_file_is_located_at_y', 'vbulletin-style.xml', $vbulletin->GPC['path']));
	}

	if(!$parsed_xml = $xmlobj->parse())
	{
		if ($scilent)
		{
			throw new vB_Exception_AdminStopMessage(array('xml_error_x_at_line_y', $xmlobj->error_string(), $xmlobj->error_line()));
		}
		print_dots_stop();
		print_stop_message2(array('xml_error_x_at_line_y', $xmlobj->error_string(), $xmlobj->error_line()));
	}

	$version = $parsed_xml['vbversion'];
	$master = ($parsed_xml['type'] == 'master' ? 1 : 0);
	$title = (empty($title) ? $parsed_xml['name'] : $title);
	$product = (empty($parsed_xml['product']) ? 'vbulletin' : $parsed_xml['product']);


	$one_pass = (is_null($startat) AND is_null($perpage));
	if (!$one_pass AND (!is_numeric($startat) OR !is_numeric($perpage) OR $perpage <= 0 OR $startat < 0))
	{
			if ($scilent)
			{
				throw new vB_Exception_AdminStopMessage('');
			}
			print_dots_stop();
			print_stop_message2('');
	}

	if ($one_pass OR ($startat == 0))
	{
		require_once(DIR . '/includes/adminfunctions.php');
		// version check
		$full_product_info = fetch_product_list(true);
		$product_info = $full_product_info["$product"];

		if ($version != $product_info['version'] AND !$anyversion AND !$master)
		{
			if ($scilent)
			{
				throw new vB_Exception_AdminStopMessage(array('upload_file_created_with_different_version', $product_info['version'], $version));
			}
			print_dots_stop();
			print_stop_message2(array('upload_file_created_with_different_version', $product_info['version'], $version));
		}

		//Initialize the style -- either init the master, create a new style, or verify the style to overwrite.
		if ($master)
		{
			$import_data = @unserialize(fetch_adminutil_text('master_style_import'));
			if (!empty($import_data) AND (TIMENOW - $import_data['last_import']) <= 30)
			{
				if ($scilent)
				{
					throw new vB_Exception_AdminStopMessage(array('must_wait_x_seconds_master_style_import', vb_number_format($import_data['last_import'] + 30 - TIMENOW)));
				}
				print_dots_stop();
				print_stop_message2(array('must_wait_x_seconds_master_style_import',  vb_number_format($import_data['last_import'] + 30 - TIMENOW)));
			}

			// overwrite master style
//			if  ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
//			{
//				echo "<h3>$vbphrase[master_style]</h3>\n<p>$vbphrase[please_wait]</p>";
//				vbflush();
//			}
			$products = array($product);
			if ($product == 'vbulletin')
			{
				$products[] = '';
			}
			vB::getDbAssertor()->assertQuery('vBForum:deleteProductTemplates', array('products' =>$products));
			vB::getDbAssertor()->assertQuery('vBForum:updateProductTemplates', array('products' =>$products));
			$styleid = -1;
		}
		else
		{
			if ($styleid == -1)
			{
				// creating a new style
				if (vB::getDbAssertor()->getRow('style', array('title' => $title)))
				{
					if ($scilent)
					{
						throw new vB_Exception_AdminStopMessage(array('style_already_exists', $title));
					}
					print_dots_stop();
					print_stop_message2(array('style_already_exists',  $title));
				}
				else
				{
					if (!$scilent)
					{
						echo "<h3><b>" . construct_phrase($vbphrase['creating_a_new_style_called_x'], $title) . "</b></h3>\n<p>$vbphrase[please_wait]</p>";
						vbflush();
					}
					/*insert query*/
					$styleid = vB::getDbAssertor()->insert('style', array(
							'title' => $title,
							'parentid' => $parentid,
							'displayorder' => $displayorder,
							'userselect' => $userselect ? 1 : 0
						));
					if (is_array($styleid))
					{
						$styleid = array_pop($styleid);
					}
				}
			}
			else
			{
				// overwriting an existing style
				if (vB::getDbAssertor()->getRow('style', array('styleid' => $styleid)))
				{
//					if  ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
//					{
//						echo "<h3><b>" . construct_phrase($vbphrase['overwriting_style_x'], $getstyle['title']) . "</b></h3>\n<p>$vbphrase[please_wait]</p>";
//						vbflush();
//					}
				}
				else
				{
					if ($scilent)
					{
						throw new vB_Exception_AdminStopMessage('cant_overwrite_non_existent_style');
					}
					print_dots_stop();
					print_stop_message2('cant_overwrite_non_existent_style');
				}
			}
		}
	}
	else
	{
		//We should never get styleid = -1 unless $master is true;
		if (($styleid == -1) AND !$master)
		{
			$stylerec = vB::getDbAssertor()->getRow('style', array('title' => $title));

			if ($stylerec AND intval($stylerec['styleid']))
			{
				$styleid = $stylerec['styleid'];
			}
			else
			{
				if ($scilent)
				{
					throw new vB_Exception_AdminStopMessage(array('incorrect_style_setting', $title));
				}
				print_dots_stop();
				print_stop_message2(array('incorrect_style_setting',  $title));
			}
		}
	}

	$outputtext = '';
	//load the templates
	if ($arr = $parsed_xml['templategroup'])
	{
		if (empty($arr[0]))
		{
			$arr = array($arr);
		}

		$templates_done = (is_numeric($startat) AND (count($arr) <= $startat));
		if ($one_pass OR !$templates_done)
		{
			if (!$one_pass)
			{
				$arr = array_slice($arr, $startat, $perpage);
			}
			$outputtext = xml_import_template_groups($styleid, $product, $arr, !$one_pass);
		}
	}
	else
	{
		$templates_done = true;
	}

	//note that templates may actually be done at this point, but templates_done is
	//only true if templates were completed in a prior step. If we are doing a multi-pass
	//process, we don't want to install stylevars in the same pass.  We aren't really done
	//until we hit a pass where the templates are done before processing.
	$done = ($one_pass OR $templates_done);
	if ($done)
	{
		//load stylevars and definitions
		// re-import any stylevar definitions
		if ($master AND !empty($parsed_xml['stylevardfns']['stylevargroup']))
		{
			xml_import_stylevar_definitions($parsed_xml['stylevardfns'], 'vbulletin');
		}

		//if the tag is present but empty we'll end up with a string with whitespace which
		//is a non "empty" value.
		if (!empty($parsed_xml['stylevars']) AND is_array($parsed_xml['stylevars']))
		{
			xml_import_stylevars($parsed_xml['stylevars'], $styleid);
		}

		if ($master)
		{
			xml_import_restore_ad_templates();
			build_adminutil_text('master_style_import', serialize(array('last_import' => TIMENOW)));
		}
		if (!$scilent)
		{
			print_dots_stop();
		}
	}

	return array(
		'version' => $version,
		'master'  => $master,
		'title'   => $title,
		'product' => $product,
		'done'    => $done,
		'overwritestyleid' => $styleid,
		'output'  => $outputtext,
	);
}

function xml_import_template_groups($styleid, $product, $templategroup_array, $output_group_name, $printInfo = true)
{
	global $vbulletin, $vbphrase;

	$safe_product =  vB::getDbAssertor()->escape_string($product);

	$querytemplates = 0;
	$outputtext = '';
	if  ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
	{
		echo defined('NO_IMPORT_DOTS') ? "\n" : '<br />';
		vbflush();
	}
	foreach ($templategroup_array AS $templategroup)
	{
		if (empty($templategroup['template'][0]))
		{
			$tg = array($templategroup['template']);
		}
		else
		{
			$tg = &$templategroup['template'];
		}

		if ($output_group_name)
		{
			$text = construct_phrase($vbphrase['template_group_x'], $templategroup['name']);
			$outputtext .= $text;
			if  ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
			{
				echo $text;
				vbflush();
			}
		}

		foreach($tg AS $template)
		{

			$querybit = array(
				'styleid'     => $styleid,
				'title'       => $template['name'],
				'template'    => $template['templatetype'] == 'template' ? compile_template($template['value']) : $template['value'],
				'template_un' => $template['templatetype'] == 'template' ? $template['value'] : '',
				'dateline'    => $template['date'],
				'username'    => $template['username'],
				'version'     => $template['version'],
				'product'     => $product,
			);
			$querybit['templatetype'] = $template['templatetype'];

			$querybits[] = $querybit;

			if (++$querytemplates % 10 == 0 OR $templategroup['name'] == 'Css')
			{
				/*insert query*/
				vB::getDbAssertor()->assertQuery('replaceTemplates', array('querybits' => $querybits));
				$querybits = array();
			}

			// Send some output to the browser inside this loop so certain hosts
			// don't artificially kill the script. See bug #34585
			if (!defined('SUPPRESS_KEEPALIVE_ECHO'))
			{
				if (VB_AREA == 'Upgrade' OR VB_AREA == 'Install')
				{
					echo ' ';
				}
				else
				{
					echo '-';
				}
				vbflush();
			}
		}

		if  ($printInfo AND (VB_AREA != 'Upgrade' AND VB_AREA != 'Install'))
		{
			echo defined('NO_IMPORT_DOTS') ? "\n" : '<br />';
			vbflush();
		}
	}

	// insert any remaining templates
	if (!empty($querybits))
	{
		vB::getDbAssertor()->assertQuery('replaceTemplates', array('querybits' => $querybits));
		$querybits = array();
	}

	return $outputtext;
}

function xml_import_restore_ad_templates()
{
	global $vbulletin;

	// Get the template titles
	$save = array();
	$save_tables = vB::getDbAssertor()->assertQuery('template', array(vB_dB_Query::CONDITIONS_KEY=> array(
			array('field'=>'templatetype', 'value' => 'template', vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ),
			array('field'=>'styleid', 'value' => -10, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ),
			array('field'=>'product', 'value' => array('vbulletin', ''), vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ),
			array('field'=>'title', 'value' => 'ad_', vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_BEGINS),
	)));


	foreach ($save_tables as $table)
	{
		$save[] = $table['title'];
	}

	// Are there any
	if (count($save))
	{
		// Delete any style id -1 ad templates that may of just been imported.
		vB::getDbAssertor()->delete('template', array(
			array('field'=>'templatetype', 'value' => 'template', vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ),
			array('field'=>'styleid', 'value' => -1, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ),
			array('field'=>'product', 'value' => array('vbulletin', ''), vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ),
			array('field'=>'title', 'value' => $save, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ),
		));


		// Replace the -1 templates with the -10 before they are deleted
		vB::getDbAssertor()->delete('template', array(
			array('field'=>'templatetype', 'value' => 'template', vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ),
			array('field'=>'styleid', 'value' => -10, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ),
			array('field'=>'product', 'value' => array('vbulletin', ''), vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ),
			array('field'=>'title', 'value' => $save, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ),
		));

	}
}

function xml_import_stylevar_definitions($stylevardfns, $product)
{
	global $vbulletin;

	$querybits = array();
	$stylevardfns = get_xml_list($stylevardfns['stylevargroup']);
	foreach ($stylevardfns AS $stylevardfn_group)
	{
		$sg = get_xml_list($stylevardfn_group['stylevar']);
		foreach ($sg AS $stylevardfn)
		{
			$querybits[] = "('" . $vbulletin->db->escape_string($stylevardfn['name']) . "', -1, '" .
				$vbulletin->db->escape_string($stylevardfn_group['name']) . "', '" .
				$vbulletin->db->escape_string($product) . "', '" .
				$vbulletin->db->escape_string($stylevardfn['datatype']) . "', '" .
				$vbulletin->db->escape_string(base64_decode($stylevardfn['validation'])) . "', '" .
				$vbulletin->db->escape_string(base64_decode($stylevardfn['failsafe'])) .
			"')";
		}

		if (!empty($querybits))
		{
			$vbulletin->db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "stylevardfn
				(stylevarid, styleid, stylevargroup, product, datatype, validation, failsafe)
				VALUES
				" . implode(',', $querybits) . "
			");
		}
		$querybits = array();
	}
}

function xml_import_stylevars($stylevars, $styleid)
{
	$values = array();
	$sv = get_xml_list($stylevars['stylevar']);

	foreach ($sv AS $stylevar)
	{
		//the parser merges attributes and child nodes into a single array.  The unnamed text
		//children get placed into a key called "value" automagically.  Since we don't have any
		//text children we just take the first one.
		$values[] = array(
			'stylevarid' => $stylevar['name'],
			'styleid' => $styleid,
			'value' => base64_decode($stylevar['value'][0])
		);
	}

	if (!empty($values))
	{
		vB::getDbAssertor()->assertQuery('replaceValues', array('table' => 'stylevar', 'values' => $values));
	}
}




/**
*	Get a list from the parsed xml array
*
* A common way to format lists in xml is
* <tag>
* 	<subtag />
* 	<subtag />
*   ...
* </tag>
*
* The problem is a single item is ambiguous
* <tag>
* 	<subtag />
* </tag>
*
* It could be a one element list or it could be a scalar child -- we only
* know from the context of the data, which the parser doesn't know.  Our parser
* assumes that it is a scalar value unless there are multiple tags with the same
* name.  Therefor so the first is rendered as:
*
* tag['subtag'] = array (0 => $element, 1 => $element)
*
* While the second is:
*
* tag['subtag'] = $element.
*
* Rather than handle each list element as a special case if there is only one item in the
* xml, this function will examine the element passed and if it isn't a 0 indexed array
* as expect will wrap the single element in an array() call.  The first case is not
* affected and the second is converted to tag['subtag'] = array(0 => $element), which
* is what we'd actually expect.
*
*	@param array The array entry for the list value.
* @return The list properly regularized to a numerically indexed array.
*/
function get_xml_list($xmlarray)
{
	if (is_array($xmlarray) AND array_key_exists(0, $xmlarray))
	{
		return $xmlarray;
	}
	else
	{
		return array($xmlarray);
	}
}

/**
*	Get the stylevar list processed to export
*
*	Seperated into its own function for reuse by products
*
*	@param string product -- The name of the product to
*	@param string stylelist -- The styles to export as a comma seperated string
*		(in descending order of precedence).  THE CALLER IS RESPONSIBLE FOR SANITIZING THE
*		INPUT.
*/
function get_stylevars_for_export($product, $stylelist)
{
	$assertor = vB::getDbAssertor();
	$queryParams = array(
		'product'   => ($product == 'vbulletin') ? array('vbulletin', '') : array((string)$product),
		'stylelist' => explode(',', $stylelist)
	);

	$stylevar_cache = array();
	$stylevars = $assertor->getRows('vBForum:getStylevarsForExport', $queryParams);
	foreach ($stylevars AS $stylevar)
	{
		$stylevar_cache[$stylevar['stylevarid']] = $stylevar;
		ksort($stylevar_cache);
	}

	$stylevar_dfn_cache = array();
	$stylevar_dfns = $assertor->getRows('vBForum:getStylevarsDfnForExport', $queryParams);
	foreach ($stylevar_dfns AS $stylevar_dfn)
	{
		$stylevar_dfn_cache[$stylevar_dfn['stylevargroup']][] = $stylevar_dfn;
	}

	return array("stylevars" => $stylevar_cache, "stylevardfns" => $stylevar_dfn_cache);
}


// #############################################################################
/**
* Converts a version number string into an array that can be parsed
* to determine if which of several version strings is the newest.
*
* @param	string	Version string to parse
*
* @return	array	Array of 6 bits, in decreasing order of influence; a higher bit value is newer
*/
function fetch_version_array($version)
{
	// parse for a main and subversion
	if (preg_match('#^([a-z]+ )?([0-9\.]+)[\s-]*([a-z].*)$#i', trim($version), $match))
	{
		$main_version = $match[2];
		$sub_version = $match[3];
	}
	else
	{
		$main_version = $version;
		$sub_version = '';
	}

	$version_bits = explode('.', $main_version);

	// pad the main version to 4 parts (1.1.1.1)
	if (sizeof($version_bits) < 4)
	{
		for ($i = sizeof($version_bits); $i < 4; $i++)
		{
			$version_bits["$i"] = 0;
		}
	}

	// default sub-versions
	$version_bits[4] = 0; // for alpha, beta, rc, pl, etc
	$version_bits[5] = 0; // alpha, beta, etc number

	if (!empty($sub_version))
	{
		// match the sub-version
		if (preg_match('#^(A|ALPHA|B|BETA|G|GAMMA|RC|RELEASE CANDIDATE|GOLD|STABLE|FINAL|PL|PATCH LEVEL)\s*(\d*)\D*$#i', $sub_version, $match))
		{
			switch (strtoupper($match[1]))
			{
				case 'A':
				case 'ALPHA';
					$version_bits[4] = -4;
					break;

				case 'B':
				case 'BETA':
					$version_bits[4] = -3;
					break;

				case 'G':
				case 'GAMMA':
					$version_bits[4] = -2;
					break;

				case 'RC':
				case 'RELEASE CANDIDATE':
					$version_bits[4] = -1;
					break;

				case 'PL':
				case 'PATCH LEVEL';
					$version_bits[4] = 1;
					break;

				case 'GOLD':
				case 'STABLE':
				case 'FINAL':
				default:
					$version_bits[4] = 0;
					break;
			}

			$version_bits[5] = $match[2];
		}
	}

	// sanity check -- make sure each bit is an int
	for ($i = 0; $i <= 5; $i++)
	{
		$version_bits["$i"] = intval($version_bits["$i"]);
	}

	return $version_bits;
}

/**
* Compares two version strings. Returns true if the first parameter is
* newer than the second.
*
* @param	string	Version string; usually the latest version
* @param	string	Version string; usually the current version
* @param	bool	Flag to allow check if the versions are the same
*
* @return	bool	True if the first argument is newer than the second, or if 'check_same' is true and the versions are the equal
*/
function is_newer_version($new_version_str, $cur_version_str, $check_same = false)
{
	// if they're the same, don't even bother
	if ($cur_version_str != $new_version_str)
	{
		$cur_version = fetch_version_array($cur_version_str);
		$new_version = fetch_version_array($new_version_str);

		// iterate parts
		for ($i = 0; $i <= 5; $i++)
		{
			if ($new_version["$i"] != $cur_version["$i"])
			{
				// true if newer is greater
				return ($new_version["$i"] > $cur_version["$i"]);
			}
		}
	}
	else if ($check_same)
	{
		return true;
	}

	return false;
}

// #############################################################################
/**
* Converts a version number such as 3.0.0 Beta 6 into an integer for comparison purposes
*
* Example:
* 3.0.0 beta 6
* (main version: 3.0.0, sub version: beta, sub release: 6)
* returns an integer like this:
* (main version){5,}(sub version){1}(sub release){2}
* Main version is sub divided to \d+?\d{2}\d{2} .
*
* Supports versions such as 3.99.99 beta 99 too.
* Direct comparison between versions integers is possible.
*
* @param	string	Version number string
*
* @return	integer
*/
function convert_version_to_int($version)
{
	$split = explode(' ', strtolower($version));
	$size = sizeof($split);

	$outputversion = 0;
	$type = 'none';

	for ($i = 0; $i < $size; $i++)
	{
		$token = trim($split[$i]);
		if (!$token)
		{
			continue;
		}

		if (preg_match('#^(\d+)\.(\d+)\.(\d+)$#', $token, $matches))
		{
			// matches X.Y.Z style
			$beginning = intval(sprintf('%02d%02d%02d', $matches[1], $matches[2], $matches[3]));
			$outputversion += $beginning * 1000;
		}
		else if ($token == 'rc')
		{
			$type = 'rc';
			$outputversion += 600;
		}
		else if ($token == 'release' AND trim($split[$i + 1]) == 'candidate')
		{
			$i++; // skip 'candidate';
			$type = 'rc';
			$outputversion  += 600;
		}
		else if ($token == 'gamma' OR $token == 'g')
		{
			$type = 'gamma';
			$outputversion += 500;
		}
		else if ($token == 'beta' OR $token == 'b')
		{
			$type = 'beta';
			$outputversion += 400;
		}
		else if ($token == 'alpha' OR $token == 'a')
		{
			$type = 'alpha';
			$outputversion += 200;
		}
		else if (preg_match('#^\d+$#', $token))
		{
			// is just a number, so it's probably the number in "beta X"
			$outputversion += sprintf('%02d', $token);
		}
	}
	if ($type == 'none')
	{
		// no type found, so assume that this is a final version
		$outputversion += 900;
	}

	return $outputversion;
}

/**
* Function used for usort'ing a collection of templates.
* This function will return newer versions first.
*
* @param	array	First version
* @param	array	Second version
*
* @return	integer	-1, 0, 1
*/
function history_compare($a, $b)
{
	// if either of them does not have a version, make it look really old to the
	// comparison tool so it doesn't get bumped all the way up when its not supposed to
	if (!$a['version'])
	{
		$a['version'] = "0.0.0";
	}

	if (!$b['version'])
	{
		$b['version'] = "0.0.0";
	}

	// these return values are backwards to sort in descending order
	if (is_newer_version($a['version'], $b['version']))
	{
		return -1;
	}
	else if (is_newer_version($b['version'], $a['version']))
	{
		return 1;
	}
	else
	{
		if($a['type'] == $b['type'])
		{
			return ($a['dateline'] > $b['dateline']) ? -1 : 1;
		}
		else if($a['type'] == "historical")
		{
			return 1;
		}
		else
		{
			return -1;
		}
	}
}

// #############################################################################
/**
*	Checks for problems with conflict resolution
*
*	This was not put into check_template_errors because the reported for that
* assumes a certain kind of error and is confusing with the conflict error
* message.
*
* @param	string Template PHP code
* @return string Error message detected or empty string if no error
*/
function check_template_conflict_error($template)
{
	if (preg_match(get_conflict_text_re(), $template))
	{
		$error = fetch_error('template_conflict_exists');
		if (!$error)
		{
			//if the error lookup fails return *something* so the calling code doesn't think
			//we succeeded.
			return "Conflict Error";
		}
		else
		{
			return $error;
		}
	}

	return '';
}

/**
* Collects errors encountered while parsing a template and returns them
*
* @param	string	Template PHP code
*
* @return	string
*/
function check_template_errors($template)
{
	// Attempt to enable display_errors so that this eval actually returns something in the event of an error
	@ini_set('display_errors', true);

	require_once(DIR . '/includes/functions_calendar.php'); // to make sure can_moderate_calendar exists

	if (preg_match('#^(.*)<if condition=(\\\\"|\')(.*)\\2>#siU', $template, $match))
	{
		// remnants of a conditional -- that means something is malformed, probably missing a </if>
		return fetch_error('template_conditional_end_missing_x', (substr_count($match[1], "\n") + 1));
	}

	if (preg_match('#^(.*)</if>#siU', $template, $match))
	{
		// remnants of a conditional -- missing beginning
		return fetch_error('template_conditional_beginning_missing_x', (substr_count($match[1], "\n") + 1));
	}

	if (strpos(@ini_get('disable_functions'), 'ob_start') !== false)
	{
		// alternate method in case OB is disabled; probably not as fool proof
		@ini_set('track_errors', true);
		$oldlevel = error_reporting(0);
		eval('$devnull = "' . $template . '";');
		error_reporting($oldlevel);

		if (strpos(strtolower($php_errormsg), 'parse') !== false)
		{
			// only return error if we think there's a parse error
			// best workaround to ignore "undefined variable" type errors
			return $php_errormsg;
		}
		else
		{
			return '';
		}
	}
	else
	{
		$olderrors = @ini_set('display_errors', true);
		$oldlevel = error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR);

		ob_start();
		if (strpos($template, '$final_rendered') !== false)
		{
			eval($template);
		}
		else
		{
			eval('$devnull = "' . $template . '";');
		}

		$errors = ob_get_contents();
		ob_end_clean();

		error_reporting($oldlevel);
		if ($olderrors !== false)
		{
			@ini_set('display_errors', $olderrors);
		}

		return $errors;
	}
}

/**
* Fetches a current or historical template.
*
* @param	integer	The ID (in the appropriate table) of the record you want to fetch
* @param	string	Type of template you want to fetch; should be "current" or "historical"
*
* @return	array	The data for the matching record
*/
function fetch_template_current_historical(&$id, $type)
{
	global $vbulletin;

	$id = intval($id);

	if ($type == 'current')
	{
		return $vbulletin->db->query_first("
			SELECT *, template_un AS templatetext
			FROM " . TABLE_PREFIX . "template
			WHERE templateid = $id
		");
	}
	else
	{
		return $vbulletin->db->query_first("
			SELECT *, template AS templatetext
			FROM " . TABLE_PREFIX . "templatehistory
			WHERE templatehistoryid = $id
		");
	}
}


/**
* Fetches the list of templates that have a changed status in the database
*
* List is hierarchical by style.
*
* @return array Associative array of styleid => template list with each template
* list being an array of templateid => template record.
*/
function fetch_changed_templates()
{
	$set = vB::getDbAssertor()->getRows('vBForum:fetchchangedtemplates', array(
		vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED
	));
	foreach ($set as $template)
	{
		$templates["$template[styleid]"]["$template[templateid]"] = $template;
	}
	return $templates;
}

/**
* Fetches the count templates that have a changed status in the database
*
* @return int Number of changed templates
*/
function fetch_changed_templates_count()
{
	$result = vB::getDbAssertor()->getRow('vBForum:getChangedTemplatesCount');
	return $result["count"];
}

/**
* Internal function to generate query for changed templates
*
*	@private
* @param string $select fields to be selected from the result set
* @return query to fetch changed templates
*/
//should only be called by the above cover functions
function fetch_changed_templates_query_internal($select)
{
	$query = "
		SELECT $select
		FROM " . TABLE_PREFIX . "template AS tCustom
		INNER JOIN " . TABLE_PREFIX . "template AS tGlobal ON
			(tGlobal.styleid = -1 AND tGlobal.title = tCustom.title)
		LEFT JOIN " . TABLE_PREFIX . "templatemerge AS templatemerge ON
			(templatemerge.templateid = tCustom.templateid)
		WHERE tCustom.styleid <> -1
			AND tCustom.templatetype = 'template' AND tCustom.mergestatus IN ('merged', 'conflicted')
		ORDER BY tCustom.title
	";

	return $query;
}

/**
*	Get the template from the template id
*
*	@param id template id
* @return array template table record
*/
function fetch_template_by_id($id)
{
	$filter = array('templateid' => intval($id));
	return fetch_template_internal($filter);
}

/**
*	Get the template from the template using the style and title
*
*	@param 	int 	styleid
* 	@param  string	title
* 	@return array 	template table record
*/
function fetch_template_by_title($styleid, $title)
{
	$filter = array('styleid' => intval($styleid), 'title' => (string) $title, 'templatetype' => 'template');
	return fetch_template_internal($filter);
}


/**
*	Get the template from the templatemerge (saved origin templates in the merge process)
* using the id
*
* The record is returned with the addition of an extra template_un field.
* This is set to the same value as the template field and is intended to match up the
* fields in the merge table with the fields in the main template table.
*
*	@param 	int 	id - Note that this is the same value as the main template table id
* 	@return array 	template record with extra template_un field
*/
function fetch_origin_template_by_id($id)
{
	$result = vB::getDbAssertor()->getRow('templatemerge', array('templateid' => intval($id)));

	if ($result)
	{
		$result['template_un'] = $result['template'];
	}
	return $result;
}

/**
*	Get the template from the template using the id
*
* The record is returned with the addition of an extra template_un field.
* This is set to the same value as the template field and is intended to match up the
* fields in the merge table with the fields in the main template table.
*
*	@param int id - Note that this is the not same value as the main template table id,
*		there can be multiple saved history versions for a given template
* @return array template record with extra template_un field
*/
function fetch_historical_template_by_id($id)
{
	$result = vB::getDbAssertor()->getRow('templatehistory', array('templatehistoryid' => intval($id)));

	//adjust to look like the main template result
	if ($result)
	{
		$result['template_un'] = $result['template'];
	}
	return $result;
}

/**
*	Get the template record
*
* This should only be called by cover functions in the file
* caller is responsible for sql security on $filter;
*
*	@filter Array	Filters to be used in the where clause. Field should be the key:
*					e.g: array('templateid' => $someValue)
* @private
*/
function fetch_template_internal($filter)
{
	$assertor = vB::getDbAssertor();
	$structure = $assertor->fetchTableStructure('vBForum:template');
	$structure = $structure['structure'];

	$queryParams = array();
	foreach ($filter AS $field => $val)
	{
		if (in_array($field, $structure))
		{
			$queryParams[$field] = $val;
		}
	}

	return $assertor->getRow('vBForum:template', $queryParams);
}


/**
* Get the requested templates for a merge operation
*
*	This gets the templates needed to show the merge display for a given custom
* template.  These are the custom template, the current default template, and the
* origin template saved when the template was initially merged.
*
* We can only display merges for templates that were actually merged during upgrade
*	as we only save the necesary information at that point.  If we don't have the
* available inforamtion to support the merge display, then an exception will be thrown
* with an explanatory message. Updating a template after upgrade
*
*	If the custom template was successfully merged we return the historical template
* save at upgrade time instead of the current (automatically updated at merge time)
* template.  Otherwise the differences merged into the current template will not be
* correctly displayed.
*
*	@param int templateid - The id of the custom user template to start this off
*	@throws Exception thrown if state does not support a merge display for
* 	the requested template
*	@return array array('custom' => $custom, 'new' => $new, 'origin' => $origin)
*/
function fetch_templates_for_merge($templateid)
{
	global $vbphrase;
	if (!$templateid)
	{
		throw new Exception($vbphrase['merge_error_invalid_template']);
	}

	$custom = fetch_template_by_id($templateid);
	if (!$custom)
	{
		throw new Exception(construct_phrase($vbphrase['merge_error_notemplate'], $templateid));
	}

	if ($custom['mergestatus'] == 'none')
	{
		throw new Exception($vbphrase['merge_error_nomerge']);
	}

	$new = fetch_template_by_title(-1, $custom['title']);
	if (!$new)
	{
		throw new Exception(construct_phrase($vbphrase['merge_error_nodefault'],  $custom['title']));
	}

	$origin = fetch_origin_template_by_id($custom['templateid']);
	if (!$origin)
	{
		throw new Exception(construct_phrase($vbphrase['merge_error_noorigin'],  $custom['title']));
	}

	if ($custom['mergestatus'] == 'merged')
	{
		$custom = fetch_historical_template_by_id($origin['savedtemplateid']);
		if (!$custom)
		{
			throw new Exception(construct_phrase($vbphrase['merge_error_nohistory'],  $custom['title']));
		}
	}

	return array('custom' => $custom, 'new' => $new, 'origin' => $origin);
}


/**
* Format the text for a merge conflict
*
* Take the three conflict text strings and format them into a human readable
* text block for display.
*
* @param string	Text from custom template
* @param string	Text from origin template
* @param string	Text from current VBulletin template
* @param string	Version string for origin template
* @param string	Version string for currnet VBulletin template
* @param bool	Whether to output the wrapping text with html markup for richer display
*
* @return string -- combined text
*/
function format_conflict_text($custom, $origin, $new, $origin_version, $new_version, $html_markup = false, $wrap = true)
{
	global $vbphrase;

	$new_title = $vbphrase['new_default_value'];
	$origin_title = $vbphrase['old_default_value'];
	$custom_title = $vbphrase['your_customized_value'];

	if ($html_markup)
	{
		$text =
			"<div class=\"merge-conflict-row\"><b>$custom_title</b><div>" . format_diff_text($custom, $wrap) . "</div></div>"
			. "<div class=\"merge-conflict-row\"><b>$origin_title</b><div>" . format_diff_text($origin, $wrap) . "</div></div>"
			. "<div class=\"merge-conflict-final-row\"><b>$new_title</b><div>" . format_diff_text($new, $wrap) . "</div></div>";
	}
	else
	{
		$origin_bar = "======== $origin_title ========";

		$text  = "<<<<<<<< $custom_title <<<<<<<<\n";
		$text .= $custom;
		$text .= $origin_bar . "\n";
		$text .= $origin;
		$text .= str_repeat("=", strlen($origin_bar)) . "\n";
		$text .= $new;
		$text .= ">>>>>>>> $new_title >>>>>>>>\n";
	}

	return $text;
}

function format_diff_text($string, $wrap = true)
{
	if (trim($string) === '')
	{
		return '&nbsp;';
	}
	else
	{
		if ($wrap)
		{
			$string = nl2br(htmlspecialchars_uni($string));
			$string = preg_replace('#( ){2}#', '&nbsp; ', $string);
			$string = str_replace("\t", '&nbsp; &nbsp; ', $string);
			return "<code>$string</code>";
		}
		else
		{
			return '<pre style="display:inline">' . "\n" . htmlspecialchars_uni($string) . '</pre>';
		}
	}
}

/**
* Return regular expression to detect the blocks returned by format_conflict_text
*
* @return string -- value suitable for passing to preg_match as an re
*/
function get_conflict_text_re()
{
	//we'll start by grabbing the formatting from format_conflict_text directly
	//this should reduce cases were we change the formatting and forget to change the re
	$re = format_conflict_text(".*\n", ".*\n", ".*\n", ".*", '.*');

	//we don't have a set number of delimeter characters since we try to even up the lines
	//in some cases (which can vary based on the version strings).  Since we don't have the
	//exact version available, we don't know how many got inserted.  We'll match any number
	//(we use two because we should always have at least that many and it dramatically improves
	//performance -- probably because we get an early failure on all of the html tags)
	$re = preg_replace('#<+#', '<<+', $re);
	$re = preg_replace('#=+#', '==+', $re);
	$re = preg_replace('#>+#', '>>+', $re);

	//handle variations on newlines.
	$re = str_replace("\n", "(?:\r|\n|\r\n)", $re);

	//convert the preg format
	$re = "#$re#isU";
	return $re;
}

// ******************************** DECLARE ARRAYS AND GLOBAL VARS ******************************

/**
* Template group names => phrases
*
* @var	array
*/
global $only;
$only = array
(
	// Template Groups
	'admin'				 => $vbphrase['group_admin'],
	'bbcode'			 => $vbphrase['group_bbcode'],
	'blog'				 => $vbphrase['group_blog'],
	'blogadmin'			 => $vbphrase['group_blogadmin'],
	'color'				 => $vbphrase['group_color'],
	'content'			 => $vbphrase['group_content_templates'],
	'conversation'		 => $vbphrase['group_conversation'],
	'css'				 => $vbphrase['group_css'],
	'display'			 => $vbphrase['group_display'],
	'editor'			 => $vbphrase['group_editor'],
	'error'				 => $vbphrase['group_error'],
	'group'				 => $vbphrase['group_sgroup'],
	'humanverify'		 => $vbphrase['group_human_verification'],
	'inlinemod'			 => $vbphrase['group_inlinemod'],
	'link'				 => $vbphrase['group_link'],
	'login'				 => $vbphrase['group_login'],
	'media'				 => $vbphrase['group_media'],
	'modify'			 => $vbphrase['group_modify'],
	'pagenav'			 => $vbphrase['group_pagenav'],
	'photo'				 => $vbphrase['group_photo'],
	'picture'			 => $vbphrase['group_picture_templates'],
	'privatemessage'	 => $vbphrase['group_private_message'],
	'profile'			 => $vbphrase['group_profile'],
	'screenlayout'		 => $vbphrase['group_screen'],
	'search'			 => $vbphrase['group_search'],
	'sgadmin'			 => $vbphrase['group_sgadmin'],
	'site'				 => $vbphrase['group_site'],
	'subscriptions'		 => $vbphrase['group_subscription'],
	'tag'				 => $vbphrase['group_tag'],
	'userfield'			 => $vbphrase['group_user_profile_field'],
	'usersettings'		 => $vbphrase['group_usersetting'],
	'widget'			 => $vbphrase['group_widget'],
);

// #############################################################################
/**
* Prints the palette for the style generator
*
* @param	array 	contains all help info
*
* @return	string	Formatted help text
*/
function print_style_palette($palette)
{
	foreach ($palette as $id => $info) {
		echo "<div id=\"$id\" class=\"colorpalette\">
			<div id=\"colordisplay-$id\" class=\"colordisplay $info[0]\">&nbsp;
			</div>
			<div id=\"colorinfo-$id\" class=\"colorinfo\">
				$info[1]
			</div>
		</div>
		";
	}
}

// #############################################################################
/**
* Generates the style for the style generator
*
* @param	array 	contains all color data
* @param	int 	Number for the parent id
* @param	string	Title for the genrated style
* @param	boolean	Override version check
* @param	int		Display order for the style
* @param	boolean	True / False whether it will be user selectable
* @param	int		Version
*
*/

function generate_style($data, $parentid, $title, $anyversion=false, $displayorder, $userselect, $version)
{
	global $vbulletin;
	require_once(DIR . '/includes/class_xml.php');
	// Need to check variable for values - Check to make sure we have a name etc

	$arr = explode('{', stripslashes($data)); // checked below
	$hex = array(0 => ''); // start at one
	$match = $match2 = array(); // initialize
	$type = 'lps'; // checked below

	foreach ($arr AS $key => $value)
	{
		if (preg_match("/\"hex\":\"([0-9A-F]{6})\"/", $value, $match) == 1)
		{
			$hex[] = '#' . $match[1];
		}
		if (preg_match("/\"type\":\"([a-z0-9]{3})\"/", $value, $match2) == 1)
		{
			$type = $match2[1];
		}
	}

	switch (count($hex))
	{
		case '11':
			break;

		default:
			print_stop_message2('incorrect_color_mapping');
	}

	if ($type == 'lps') // Color : Primary and Secondary
	{
		$sample_file = "style_generator_sample_light.xml";
		$from = array('#FF0000', '#BF3030', '#A60000', '#FF4040', '#FF7373', '#009999', '#1D7373', '#5CCCCC');
		$to = array($hex[1], $hex[2], $hex[3], $hex[4], $hex[5], $hex[6], $hex[7], $hex[10]);
	}
	else if ($type == 'lpt') // White : Similar to the current style
	{
		$sample_file = "style_generator_sample_white.xml";
		$from = array('#A60000', '#BF3030', '#FF4040', '#FF7373');
		$to = array($hex[3], $hex[2], $hex[1], $hex[1]);
	}
	else if ($type == 'gry') // Grey :: Primary 3 and Primary 4 only
	{
		$sample_file = "style_generator_sample_gray.xml";
		$from = array('#A60000', '#FF4040');
		$to = array($hex[1], $hex[4]);
	}
	else if ($type == 'drk') // Dark : Primary 3 and Primary 4 only
	{
		$sample_file = "style_generator_sample_dark.xml";
		$from = array('#A60000', '#FF4040');
		$to = array($hex[1], $hex[4]);
	}
	else // Dark : Default to Dark
	{
		$sample_file = "style_generator_sample_dark.xml";
		$from = array('#A60000', '#FF4040');
		$to = array($hex[1], $hex[4]);
	}

	$decode = $match = array();

	$xmlobj = new vB_XML_Parser(false, DIR . '/includes/xml/' . $sample_file);
	$styledata = $xmlobj->parse();
	foreach($styledata['stylevars']['stylevar'] AS $stylevars)
	{
		// The XML Parser outputs 2 values for the value field when one is set as an attribute.
		// The work around for now is to specify the first value (the attribute). In reality
		// the parser shouldn't add a blank 'value' if it exists as an attribute.
		$decode[$stylevars['name']] = base64_decode($stylevars['value'][0]);
	}

	// Preg match and then replace. Shutter, a better method is on the way.
	$match = array();
	foreach ($decode AS $name => $value) // replaces the RRGGBB in the sample_*.xml file with chosen colors and re-encode
	{
		if (preg_match("/\"(#[a-zA-Z0-9]{6})\"/", $value, $match) == 1)
		{
			$upper = '"' . strtoupper($match[1]) . '"';
			$stylevarparts[$name] = str_replace($from, $to, preg_replace("/\"(#[a-zA-Z0-9]{6})\"/", $upper, $value));
		}
	}

	if($title===''){$title = 'Style ' . time();}
	$xml = new vB_XML_Builder();
	$xml->add_group('style',
		array(
			'name' => $title,
			'vbversion' => $version,
			'product' => 'vbulletin',
			'type' => 'custom'
		)
	);

	$xml->add_group('stylevars');
	foreach ($stylevarparts AS $stylevarid => $stylevar)
	{
		$xml->add_tag('stylevar', '',
			array(
				'name' => htmlspecialchars($stylevarid),
				'value' => base64_encode(serialize(json_decode($stylevar)))
			)
		);
	}
	// Close stylevar group
	$xml->close_group();
	// Close style group
	$xml->close_group();

	$doc = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n";
	$doc .= $xml->output();
	$xml = null;

	xml_import_style($doc, -1, $parentid, $title, $anyversion, $displayorder, $userselect);

	$args['do'] = 'rebuild';
	$args['goto'] = "template?" . vB::getCurrentSession()->get('sessionurl');
	print_cp_redirect_with_session('template', $args);
}

// #############################################################################
/**
* Prints out the save options for the style generator
*/

function import_generated_style() {
	global $vbphrase, $vb5_config;

	$stylecache = vB_Library::instance('Style')->fetchStyles(false, false);
	echo "
	<script type=\"text/javascript\">
	<!--
	function js_confirm_upload(tform, filefield)
	{
		if (filefield.value == \"\")
		{
			return confirm(\"".construct_phrase($vbphrase['you_did_not_specify_a_file_to_upload'], " + tform.serverfile.value + ")."\");
		}
		return true;
	}
	function js_fetch_style_title()
	{
		styleid = document.forms.downloadform.dostyleid.options[document.forms.downloadform.dostyleid.selectedIndex].value;
		document.forms.downloadform.title.value = style[styleid];
	}
	var style = new Array();
	style['-1'] = \"" . $vbphrase['master_style'] . "\"";
	foreach($stylecache AS $styleid => $style)
	{
		echo "\n\tstyle['$styleid'] = \"" . addslashes_js($style['title'], '"') . "\";";
		$styleoptions["$styleid"] = construct_depth_mark($style['depth'], '--', iif($vb5_config['Misc']['debug'], '--', '')) . ' ' . $style['title'];
	}
	echo "
	// -->
	</script>";

	echo '<div id="styleform">';
	echo '<form id="form">';
	construct_hidden_code('adid', $vbulletin->GPC['adid']);
	echo '<input id="form-data" type="hidden" name="data">';
	echo '<div class="styledetails"><div id="title-generated-style" class="help title-generated-style">';
	print_input_row($vbphrase['title_generated_style'], 'name', null, null, null, null, null, null, 'form-name');
	echo '</div><div id="parent-id" class="help parent-id">';
	print_style_chooser_row('parentid', -1, $vbphrase['no_parent_style'], $vbphrase['parent_style'], 1);
	echo '</div></div><div class="styleoptions"><div id="display-order" class="help display-order">';
	print_input_row($vbphrase['display_order'], 'displayorder', 1, null, null, null, null, null, 'form-displayorder');
	echo '</div><div id="allow-user-selection" class="help allow-user-selection">';
	print_yes_no_row($vbphrase['allow_user_selection'], 'userselect', 1, null, null, null, null, null, 'form-userselect');
	echo '</div></div></form></div>';
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 71510 $
|| ####################################################################
\*======================================================================*/
?>
