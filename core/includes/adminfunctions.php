<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright 2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
/**
 * @package vBLegacy
 */

error_reporting(E_ALL & ~E_NOTICE);
define('ADMINHASH', md5(vB_Request_Web::COOKIE_SALT . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']));

// #############################################################################

/**
* Displays the login form for the various control panel areas
*
* The actual form displayed is dependent upon the VB_AREA constant
*/
function print_cp_login($mismatch = false)
{
	global $vbulletin, $vbphrase;

	if ($vbulletin->GPC['ajax'])
	{
		print_stop_message2('you_have_been_logged_out_of_the_cp');
	}

	$focusfield = iif($vbulletin->userinfo['userid'] == 0, 'username', 'password');

	$vbulletin->input->clean_array_gpc('r', array(
		'vb_login_username' => vB_Cleaner::TYPE_NOHTML,
		'loginerror'        => vB_Cleaner::TYPE_STR,
		'strikes'           => vB_Cleaner::TYPE_INT,
	));

	$printusername = iif(!empty($vbulletin->GPC['vb_login_username']), $vbulletin->GPC['vb_login_username'], ($vbulletin->userinfo['userid'] ? $vbulletin->userinfo['username'] : ''));
	$vbulletin->userinfo['badlocation'] = 1;

	$options = vB::getDatastore()->getValue('options');
	$filebase = $options['bburl'];

	switch(VB_AREA)
	{
		case 'AdminCP':
			$pagetitle = $vbphrase['admin_control_panel'];
			$getcssoptions = fetch_cpcss_options();
			$cssoptions = array();
			foreach ($getcssoptions AS $folder => $foldername)
			{
				$key = iif($folder == $options['cpstylefolder'], '', $folder);
				$cssoptions["$key"] = $foldername;
			}
			$showoptions = true;
			$logintype = 'cplogin';
		break;

		case 'ModCP':
			$pagetitle = $vbphrase['moderator_control_panel'];
			$showoptions = false;
			$logintype = 'modcplogin';
		break;

		default:
			// Legacy Hook 'admin_login_area_switch' Removed //
	}

	define('NO_PAGE_TITLE', true);
	print_cp_header($vbphrase['log_in'], "document.forms.loginform.vb_login_$focusfield.focus()");

	require_once(DIR . '/includes/functions_misc.php');
	$postvars = construct_post_vars_html();

	$forumHome = vB_Library::instance('content_channel')->getForumHomeChannel();
	$forumhome_url = vB5_Route::buildUrl($forumHome['routeid'] . '|nosession|fullurl');
	if (strpos('://', $forumhome_url) == 'false')
	{
			$forumhome_url = '../' . $forumhome_url;
	}
	?>
	<script type="text/javascript" src="<?php echo $filebase; ?>/clientscript/vbulletin_md5.js?v=<?php echo SIMPLE_VERSION; ?>"></script>
	<script type="text/javascript">
	<!--
	function js_show_options(objectid, clickedelm)
	{
		fetch_object(objectid).style.display = "";
		clickedelm.disabled = true;
	}
	function js_fetch_url_append(origbit,addbit)
	{
		if (origbit.search(/\?/) != -1)
		{
			return origbit + '&' + addbit;
		}
		else
		{
			return origbit + '?' + addbit;
		}
	}
	function js_do_options(formobj)
	{
		if (typeof(formobj.nojs) != "undefined" && formobj.nojs.checked == true)
		{
			formobj.url.value = js_fetch_url_append(formobj.url.value, 'nojs=1');
		}
		return true;
	}
	//-->
	</script>
	<form action="../login.php?do=login" method="post" name="loginform" onsubmit="md5hash(vb_login_password, vb_login_md5password, vb_login_md5password_utf); js_do_options(this)">
	<input type="hidden" name="url" value="<?php echo $vbulletin->scriptpath; ?>" />
	<input type="hidden" name="s" value="<?php echo vB::getCurrentSession()->get('dbsessionhash'); ?>" />
	<input type="hidden" name="securitytoken" value="<?php echo $vbulletin->userinfo['securitytoken']; ?>" />
	<input type="hidden" name="logintype" value="<?php echo $logintype; ?>" />
	<input type="hidden" name="do" value="login" />
	<input type="hidden" name="vb_login_md5password" value="" />
	<input type="hidden" name="vb_login_md5password_utf" value="" />
	<?php echo $postvars ?>
	<p>&nbsp;</p><p>&nbsp;</p>
	<table class="tborder" cellpadding="0" cellspacing="0" border="0" width="450" align="center"><tr><td>

		<!-- header -->
		<div class="tcat" style="text-align:center"><b><?php echo $vbphrase['log_in']; ?></b></div>
		<!-- /header -->

		<!-- logo and version -->
		<table cellpadding="4" cellspacing="0" border="0" width="100%" class="login-logo">
		<tr valign="bottom">
			<td><img src="<?php echo $filebase; ?>/cpstyles/<?php echo $options['cpstylefolder']; ?>/cp_logo.<?php echo $options['cpstyleimageext']; ?>" title="<?php echo $vbphrase['vbulletin_copyright']; ?>" border="0" /></td>
			<td>
				<b><a href="<?php echo $forumhome_url ?>"><?php echo $options['bbtitle']; ?></a></b><br />
				<?php echo "vBulletin " . $options['templateversion'] . " $pagetitle"; ?><br />
				&nbsp;
			</td>
		</tr>
		<?php

		if ($mismatch)
		{
			?>
			<tr>
				<td colspan="2" class="navbody"><b><?php echo $vbphrase['to_continue_this_action']; ?></b></td>
			</tr>
			<?php
		}

		if ($vbulletin->GPC['loginerror'])
		{
			$errorphrase = vB_Api::instanceInternal('phrase')->fetch($vbulletin->GPC['loginerror']);
			$errorphrase = $errorphrase[$vbulletin->GPC['loginerror']];
			?>
			<tr>
				<td colspan="2" class="navbody error"><b><?php echo construct_phrase($errorphrase, '../lostpw', $vbulletin->GPC['strikes']); ?></b></td>
			</tr>
			<?php
		}
		?>
		</table>
		<!-- /logo and version -->

		<table cellpadding="4" cellspacing="0" border="0" width="100%" class="alt1">
		<col width="50%" style="text-align:<?php echo vB_Template_Runtime::fetchStyleVar('right'); ?>; white-space:nowrap"></col>
		<col></col>
		<col width="50%"></col>

		<!-- login fields -->
		<tbody>
		<tr>
			<td><?php echo $vbphrase['username']; ?></td>
			<td><input type="text" style="padding-<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>:5px; font-weight:bold; width:250px" name="vb_login_username" value="<?php echo $printusername; ?>" accesskey="u" tabindex="1" id="vb_login_username" /></td>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td><?php echo $vbphrase['password']; ?></td>
			<td><input type="password" style="padding-<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>:5px; font-weight:bold; width:250px" name="vb_login_password" accesskey="p" tabindex="2" id="vb_login_password" /></td>
			<td>&nbsp;</td>
		</tr>
		<tr style="display: none" id="cap_lock_alert">
			<td>&nbsp;</td>
			<td class="tborder"><?php echo $vbphrase['caps_lock_is_on']; ?></td>
			<td>&nbsp;</td>
		</tr>
		</tbody>
		<!-- /login fields -->

		<?php if ($showoptions) { ?>
		<!-- admin options -->
		<tbody id="loginoptions" style="display:none">
		<tr>
			<td><?php echo $vbphrase['style']; ?></td>
			<td><select name="cssprefs" class="login" style="padding-<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>:5px; font-weight:normal; width:250px" tabindex="5"><?php echo construct_select_options($cssoptions, $csschoice); ?></select></td>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td><?php echo $vbphrase['options']; ?></td>
			<td>
				<label><input type="checkbox" name="nojs" value="1" tabindex="6" /> <?php echo $vbphrase['save_open_groups_automatically']; ?></label>
			</td>
			<td class="login">&nbsp;</td>
		</tr>
		</tbody>
		<!-- END admin options -->
		<?php } ?>

		<!-- submit row -->
		<tbody>
		<tr>
			<td colspan="3" align="center">
				<input type="submit" class="button" value="  <?php echo $vbphrase['log_in']; ?>  " accesskey="s" tabindex="3" />
				<?php if ($showoptions) { ?><input type="button" class="button" value=" <?php echo $vbphrase['options']; ?> " accesskey="o" onclick="js_show_options('loginoptions', this)" tabindex="4" /><?php } ?>
			</td>
		</tr>
		</tbody>
		<!-- /submit row -->
		</table>

	</td></tr></table>
	</form>
	<script type="text/javascript">
	<!--
	function caps_check(e)
	{
		var detected_on = detect_caps_lock(e);
		var alert_box = fetch_object('cap_lock_alert');

		if (alert_box.style.display == '')
		{
			// box showing already, hide if caps lock turns off
			if (!detected_on)
			{
				alert_box.style.display = 'none';
			}
		}
		else
		{
			if (detected_on)
			{
				alert_box.style.display = '';
			}
		}
	}
	fetch_object('vb_login_password').onkeypress = caps_check;
	//-->
	</script>
	<?php

	define('NO_CP_COPYRIGHT', true);
	unset($GLOBALS['DEVDEBUG']);
	print_cp_footer();
}

// #############################################################################
/**
* Starts Gzip encoding and prints out the main control panel page start / header
*
* @param	string	The page title
* @param	string	Javascript functions to be run on page start - for example "alert('moo'); alert('baa');"
* @param	string	Code to be inserted into the <head> of the page
* @param	integer	Width in pixels of page margins (default = 0)
* @param	string	HTML attributes for <body> tag - for example 'bgcolor="red" text="orange"'
*/
function print_cp_header($title = '', $onload = '', $headinsert = '', $marginwidth = 0, $bodyattributes = '')
{
	global $vbulletin, $helpcache, $vbphrase;

	$options = vB::getDatastore()->getValue('options');
	$filebase = $options['bburl'];

	// start GZ encoding output
	if ($vbulletin->options['gzipoutput'] AND !$vbulletin->nozip AND !headers_sent() AND function_exists('ob_start') AND function_exists('crc32') AND function_exists('gzcompress'))
	{
		// This will destroy all previous output buffers that could have been stacked up here.
		while (ob_get_level())
		{
			@ob_end_clean();
		}
		ob_start();
	}

	// get the appropriate <title> for the page
	switch(VB_AREA)
	{
		case 'AdminCP': $titlestring = iif($title, "$title - ") . $vbulletin->options['bbtitle'] . " - vBulletin $vbphrase[admin_control_panel]"; break;
		case 'ModCP': $titlestring = iif($title, "$title - ") . $vbulletin->options['bbtitle'] . " - vBulletin $vbphrase[moderator_control_panel]"; break;
		case 'Upgrade': $titlestring = iif($title, "vBulletin $title - ") . $vbulletin->options['bbtitle']; break;
		case 'Install': $titlestring = iif($title, "vBulletin $title - ") . $vbulletin->options['bbtitle']; break;
		default: $titlestring = iif($title, "$title - ") . $vbulletin->options['bbtitle'];
	}

	// if there is an onload action for <body>, set it up
	$onload = iif($onload != '', " $onload");

	// set up some options for nav-panel and head frames
	if (defined('IS_NAV_PANEL'))
	{
		$htmlattributes = ' class="navbody"';
		$bodyattributes .= ' class="navbody"';
		$headinsert .= '<base target="main" />';
	}
	else
	{
		$htmlattributes = '';
	}

	// print out the page header
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">' . "\r\n";
	echo "<html xmlns=\"http://www.w3.org/1999/xhtml\" dir=\"" . vB_Template_Runtime::fetchStyleVar('textdirection') . "\" lang=\"" . vB_Template_Runtime::fetchStyleVar('languagecode') . "\"$htmlattributes>\r\n";
	echo "<head>
	<title>$titlestring</title>
	<meta http-equiv=\"Content-Type\" content=\"text/html; charset=" . vB_Template_Runtime::fetchStyleVar('charset') . "\" />
	<link rel=\"stylesheet\" type=\"text/css\" href=\"$filebase/cpstyles/global.css?v={$options['simpleversion']}\" />
	<link rel=\"stylesheet\" type=\"text/css\" href=\"$filebase/cpstyles/" . $options['cpstylefolder'] . "/controlpanel.css?v={$options['simpleversion']}\" />" . iif($headinsert != '', "$headinsert") . "
	<style type=\"text/css\">
		.page { background-color:white; color:black; }
		.time { color:silver; }
		.error { color:red; }
		/* Start generic feature management styles */

		.feature_management_header {
			font-size:16px;
		}

		/* End generic feature management styles */


		/* Start Styles for Category Manager */

		#category_title_controls {
			padding-" . vB_Template_Runtime::fetchStyleVar('left') . ": 10px;
			font-weight:bold;
			font-size:14px;
		}

		.picker_overlay {
			/*
				background-color:black;
				color:white;
			*/
			background-color:white;
			color:black;
			font-size:14px;
			padding:3px;
			border:1px solid black;
		}

		.selected_marker {
			margin-" . vB_Template_Runtime::fetchStyleVar('right') . ":4px;
			margin-top:4px;
			float:" . vB_Template_Runtime::fetchStyleVar('left') . ";
		}

		.section_name {
			font-size:14px;
			font-weight:bold;
			padding:0.2em 1em;
			margin: 0.5em 0.2em;
			/*
			color:#a2de97;
			background-color:black;
			*/
			background-color:white;
		}

		.tcat .picker_overlay a, .picker_overlay a, a.section_switch_link {
			/*
			color:#a2de97;
			*/
			color:blue;
		}

		.tcat .picker_overlay a:hover, .picker_overlay a:hover, a.section_switch_link:hover {
			color:red;
		}
		/* End Styles for Category Manager */

		/* Styles that need Stylevars */
		#acp-top-links li#acp-top-link-acp > div .icon {
			margin-" . vB_Template_Runtime::fetchStyleVar('right') . ": 4px;
		}
		#acp-top-links .left {
			float: " . vB_Template_Runtime::fetchStyleVar('left') . ";
		}
		#acp-top-links .right {
			float: " . vB_Template_Runtime::fetchStyleVar('right') . ";
		}
		#acp-top-links li.rightmost {
			padding-" . vB_Template_Runtime::fetchStyleVar('right') . ": 0;
		}
		.acp-nav-controls a.nav-left {
			float: " . vB_Template_Runtime::fetchStyleVar('left') . ";
			margin-" . vB_Template_Runtime::fetchStyleVar('right') . ": 0px;
		}
		.acp-nav-controls a.nav-right {
			float: " . vB_Template_Runtime::fetchStyleVar('left') . ";
			margin-" . vB_Template_Runtime::fetchStyleVar('right') . ": 0px;
			margin-" . vB_Template_Runtime::fetchStyleVar('left') . ": 4px;
		}
		.navtitle {
			padding-" . vB_Template_Runtime::fetchStyleVar('left') . ": 20px;
		}
		.acp-nav-arrow {
			margin-" . vB_Template_Runtime::fetchStyleVar('right') . ": 20px;
		}
		.navgroup a {
			padding-" . vB_Template_Runtime::fetchStyleVar('left') . ": 20px;
		}
		.tcat {
			text-align: " . vB_Template_Runtime::fetchStyleVar('left') . ";
		}
		#acp-logo-bar .logo {
			float: " . vB_Template_Runtime::fetchStyleVar('left') . ";
		}
		#acp-logo-bar .links {
			float: " . vB_Template_Runtime::fetchStyleVar('left') . ";
		}
		#acp-logo-bar .search {
			float: " . vB_Template_Runtime::fetchStyleVar('right') . ";
			margin-" . vB_Template_Runtime::fetchStyleVar('right') . ": 35px;
		}
		#acp-logo-bar .search .button {
			margin-" . vB_Template_Runtime::fetchStyleVar('left') . ": 5px;
			margin-" . vB_Template_Runtime::fetchStyleVar('right') . ": 0;
		}
		.tfoot {
			text-align: " . vB_Template_Runtime::fetchStyleVar('right') . ";
		}
		.tfoot ul {
			text-align: " . vB_Template_Runtime::fetchStyleVar('left') . ";
		}
	" . (vB::getDbAssertor()->getDBConnection()->doExplain ? "
		.query { background: #FFF; border: 1px solid red; margin: 0 0 10px 0; padding: 10px; }
		.query h4 { margin: 0 0 10px 0; }
		.query pre {display:block;overflow:auto;border:1px solid black;margin:0 0 10px 0;padding:10px;background:#F6F6F6;}
		.query pre.trace {height: 30px; cursor: pointer; margin: 10px 0 0 0; background: #FCFCFC;}
		.query ul {padding:0;margin:0;list-style:none;}
		.query table {margin:0 0 10px 0;background:#000;}
		.query table th {background:#F6F6F6;text-align:left;}
		.query table td {background:#FFF;}
	" : "") . "
	</style>
	<script type=\"text/javascript\">
	<!--
	var SESSIONHASH = \"" . vB::getCurrentSession()->get('sessionhash') . "\";
	var ADMINHASH = \"" . ADMINHASH . "\";
	var SECURITYTOKEN = \"" . $vbulletin->userinfo['securitytoken'] . "\";
	var IMGDIR_MISC = \"$filebase/cpstyles/" . $vbulletin->options['cpstylefolder'] . "\";
	var CLEARGIFURL = \"$filebase/" . $vbulletin->options['cleargifurl'] . "\";
	function set_cp_title()
	{
		if (typeof(parent.document) != 'undefined' && typeof(parent.document) != 'unknown' && typeof(parent.document.title) == 'string')
		{
			parent.document.title = (document.title != '' ? document.title : 'vBulletin');
		}
	}
	//-->
	</script>
	<script type=\"text/javascript\" src=\"$filebase/clientscript/yui/yuiloader-dom-event/yuiloader-dom-event.js\"></script>
	<script type=\"text/javascript\" src=\"$filebase/clientscript/yui/connection/connection-min.js\"></script>
	<script type=\"text/javascript\" src=\"$filebase/clientscript/vbulletin_global.js\"></script>
	<script type=\"text/javascript\" src=\"$filebase/clientscript/vbulletin-core.js\"></script>
	<script type=\"text/javascript\" src=\"$filebase/clientscript/vbulletin_ajax_suggest.js\"></script>\n\r";
	echo "</head>\r\n";
	echo "<body style=\"margin:{$marginwidth}px\" onload=\"set_cp_title();$onload\"$bodyattributes>\r\n";
	echo iif($title != '' AND !defined('IS_NAV_PANEL') AND !defined('NO_PAGE_TITLE'), "<div class=\"pagetitle\">$title</div>\r\n<div class=\"acp-content-wrapper\">\r\n");
	echo "<!-- END CONTROL PANEL HEADER -->\r\n\r\n";

	// create the help cache
	if (VB_AREA == 'AdminCP' OR VB_AREA == 'ModCP')
	{
		$helpcache = array();
		$helptopics = $vbulletin->db->query_read("SELECT script, action, optionname FROM " . TABLE_PREFIX . "adminhelp");
		while ($helptopic = $vbulletin->db->fetch_array($helptopics))
		{
			$multactions = explode(',', $helptopic['action']);
			foreach ($multactions AS $act)
			{
				$act = trim($act);
				$helpcache["$helptopic[script]"]["$act"]["$helptopic[optionname]"] = 1;
			}
		}
	}
	else
	{
		$helpcache = array();
	}

	define('DONE_CPHEADER', true);
}

// #############################################################################
/**
* Prints the page footer, finishes Gzip encoding and terminates execution
*/
function print_cp_footer()
{
	global $vbulletin, $level, $vbphrase, $vb5_config;

	echo "\r\n\r\n<!-- START CONTROL PANEL FOOTER -->\r\n";

	if ($vb5_config['Misc']['debug'])
	{
		echo '<br /><br />';
		if (defined('CVS_REVISION'))
		{
			$re = '#^\$' . 'RCS' . 'file: (.*\.php),v ' . '\$ - \$' . 'Revision: ([0-9\.]+) \$$#siU';
			$cvsversion = preg_replace($re, '\1, CVS v\2', CVS_REVISION);
		}
		if ($size = sizeof($GLOBALS['DEVDEBUG']))
		{
			$displayarray = array();
			$displayarray[] = "<select id=\"moo\"><option selected=\"selected\">DEBUG MESSAGES ($size)</option>\n" . construct_select_options($GLOBALS['DEVDEBUG'],-1,1) . "\t</select>";
			if (defined('CVS_REVISION'))
			{
				$displayarray[] = "<p style=\"font: bold 11px tahoma;\">$cvsversion</p>";
			}
			$displayarray[] = "<p style=\"font: bold 11px tahoma;\">SQL Queries (" . $vbulletin->db->querycount . ")</p>";

			$buttons = "<input type=\"button\" class=\"button\" value=\"Explain\" onclick=\"window.location = '" . $vbulletin->scriptpath . iif(strpos($vbulletin->scriptpath, '?') > 0, '&amp;', '?') . 'explain=1' . "';\" />" . "\n" . "<input type=\"button\" class=\"button\" value=\"Reload\" onclick=\"window.location = window.location;\" />";

			print_form_header('../docs/phrasedev', 'dofindphrase', 0, 1, 'debug', '90%', '_phrasefind');

			$displayarray[] =& $buttons;

			print_cells_row($displayarray, 0, 'thead');
			print_table_footer();
			echo '<p align="center" class="smallfont">' . date('r T') . '</p>';
		}
		else
		{
			echo "<p align=\"center\" class=\"smallfont\">SQL Queries (" . $vbulletin->db->querycount . ") | " . (!empty($cvsversion) ? "$cvsversion | " : '') . "<a href=\"" . $vbulletin->scriptpath . iif(strpos($vbulletin->scriptpath, '?') > 0, '&amp;', '?') . "explain=1\">Explain</a></p>";
			if (function_exists('memory_get_usage'))
			{
				echo "<p align=\"center\" class=\"smallfont\">Memory Usage: " . vb_number_format(round(memory_get_usage() / 1024, 2)) . " KiB</p>";
			}
		}

		$_REQUEST['do'] = htmlspecialchars_uni($_REQUEST['do']);

		echo "<script type=\"text/javascript\">window.status = \"" . construct_phrase($vbphrase['logged_in_user_x_executed_y_queries'], $vbulletin->userinfo['username'], $vbulletin->db->querycount) . " \$_REQUEST[do] = '$_REQUEST[do]'\";</script>";
	}

	if (!defined('NO_CP_COPYRIGHT'))
	{
		$output_version = defined('ADMIN_VERSION_VBULLETIN') ? ADMIN_VERSION_VBULLETIN : $vbulletin->options['templateversion'];
		echo '<div class="acp-footer">' .
			construct_phrase($vbphrase['vbulletin_copyright_orig'], $output_version, date('Y')) .
			'</div>';
	}
	if (!defined('IS_NAV_PANEL') AND !defined('NO_PAGE_TITLE') AND VB_AREA != 'Upgrade' AND VB_AREA != 'Install')
	{
		echo "\n</div>";
	}

	if (vB::getDbAssertor()->getDBConnection()->doExplain)
	{
		$data = vB::getDbAssertor()->getDBConnection()->getExplain();
		if (!empty($data['describe']))
		{
			echo '<div class="query">';
			echo '<h4>Describe Queries: (Included in the full listing of queries below)</h4><ul>';
			foreach ($data['describe'] as $describe)
			{
				echo '<li>' . htmlspecialchars($describe) . '</li>';
			}
			echo '</ul>';
			echo '</div>';
		}

		if (!empty($data['duplicates']))
		{
			echo '<div class="query">';
			echo '<h4>Duplicate Queries: (Exact textual duplicates, also included in the full listing of queries below)</h4><ul>';
			foreach ($data['duplicates'] as $duplicate)
			{
				echo '<li>Times run: ' . $duplicate['count'] . '<pre>' . htmlspecialchars($duplicate['query']) . '</pre></li>';
			}
			echo '</ul>';
			echo '</div>';
		}

		foreach ($data['explain'] as $i => $query)
		{
			echo '
			<div class="query">
				<h4>SQL Query #' . ($i + 1) . '</h4>
				<pre>' . htmlspecialchars($query['query']) . '</pre>
				' . $query['explain'] . '
				<ul>
					<li>Time Before: ' . $query['timeStart'] . '</li>
					<li>Time After: ' . $query['timeStop'] . '</li>
					<li>Time Taken: ' . $query['timeTaken'] . '</li>
					<li>Memory Before: ' . $query['memoryStart'] . '</li>
					<li>Memory After: ' . $query['memoryStop'] . '</li>
					<li>Memory Used: ' . $query['memoryUsed'] . '</li>
				</ul>

			</div>
			';
		}

		$overall = $data['sqltime'] + $data['phptime'];
		echo '<h1>' . count($data['explain']) . ' Queries Run : Total SQL time was ' . number_format($data['sqltime'],6) .
		' seconds , Total PHP time was ' . number_format($data['phptime'],6) . ' seconds , Overall time was ' . number_format($overall,6) . ' seconds.</h1><br />';
	}

	echo "\n</body>\n</html>";

	// Legacy Hook 'admin_complete' Removed //
	if (vB::getDatastore()->getOption('gzipoutput') AND function_exists("ob_start") AND function_exists("crc32") AND function_exists("gzcompress") AND !$vbulletin->nozip)
	{
		$text = ob_get_contents();
		while (ob_get_level())
		{
			@ob_end_clean();
		}

		if (!headers_sent() AND SAPI_NAME != 'apache2filter')
		{
			$newtext = fetch_gzipped_text($text, vB::getDatastore()->getOption('gziplevel'));
		}
		else
		{
			$newtext = $text;
		}

		if (!headers_sent())
		{
			@header('Content-Length: ' . strlen($newtext));
		}
		echo $newtext;
	}
	flush();

	//make sure that shutdown functions get called on exit.
	$vbulletin->shutdown->shutdown();
	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}

	// terminate script execution now - DO NOT REMOVE THIS!
	exit;
}

// #############################################################################
/**
* Returns a number, unused in an ID thus far on the page.
* Functions that output elements with ID attributes use this internally.
*
* @param	boolean	Whether or not to increment the counter before returning
*
* @return	integer	Unused number
*/
function fetch_uniqueid_counter($increment = true)
{
	static $counter = 0;
	if ($increment)
	{
		return ++$counter;
	}
	else
	{
		return $counter;
	}
}

// #############################################################################
/**
* Prints the standard form header, setting target script and action to perform
*
* @param	string	PHP script to which the form will submit (ommit file suffix)
* @param	string	'do' action for target script
* @param	boolean	Whether or not to include an encoding type for the form (for file uploads)
* @param	boolean	Whether or not to add a <table> to give the form structure
* @param	string	Name for the form - <form name="$name" ... >
* @param	string	Width for the <table> - default = '90%'
* @param	string	Value for 'target' attribute of form
* @param	boolean	Whether or not to place a <br /> before the opening form tag
* @param	string	Form method (GET / POST)
* @param	integer	CellSpacing for Table
*/
function print_form_header($phpscript = '', $do = '', $uploadform = false, $addtable = true, $name = 'cpform', $width = '100%', $target = '', $echobr = true, $method = 'post', $cellspacing = 0, $border_collapse = false, $formid = '')
{
	global $tableadded;

	// override legacy flags
	$width = '100%';
	$echobr = false;

	if (($quote_pos = strpos($name, '"')) !== false)
	{
		$clean_name = substr($name, 0, $quote_pos);
	}
	else
	{
		$clean_name = $name;
	}
	/** @TODO change this when querycount is known */
	$querycount = 'unknown';//$vbulletin->db->querycount
	echo "\n<!-- form started:" . $querycount . " queries executed -->\n";
	echo "<form action=\"$phpscript.php?do=$do\"" . ($uploadform ? " enctype=\"multipart/form-data\"" : "") . " method=\"$method\"" . ($target ? " target=\"$target\"" : "") . " name=\"$clean_name\" id=\"" . ($formid ? $formid : $clean_name) . "\">\n";

	$sessionhash = vB::getCurrentSession()->get('sessionhash');
	$userInfo = vB::getCurrentSession()->fetch_userinfo();
	if (!empty($sessionhash))
	{
		//construct_hidden_code('s', vB::getCurrentSession()->get('sessionhash'));
		echo "<input type=\"hidden\" name=\"s\" value=\"" . htmlspecialchars_uni($sessionhash) . "\" />\n";
	}
	//construct_hidden_code('do', $do);
	echo "<input type=\"hidden\" name=\"do\" id=\"do\" value=\"" . htmlspecialchars_uni($do) . "\" />\n";
	if (strtolower(substr($method, 0, 4)) == 'post') // do this because we now do things like 'post" onsubmit="bla()' and we need to just know if the string BEGINS with POST
	{
		echo "<input type=\"hidden\" name=\"adminhash\" value=\"" . ADMINHASH . "\" />\n";
		echo "<input type=\"hidden\" name=\"securitytoken\" value=\"" . $userInfo['securitytoken'] . "\" />\n";
	}

	if ($addtable)
	{
		print_table_start($echobr, $width, $cellspacing, $clean_name . '_table', $border_collapse);
	}
	else
	{
		$tableadded = 0;
	}
}

// #############################################################################
/**
* Prints an opening <table> tag with standard attributes
*
* @param	boolean	Whether or not to place a <br /> before the opening table tag
* @param	string	Width for the <table> - default = '90%'
* @param	integer	Width in pixels for the table's 'cellspacing' attribute
* @param	boolean Whether to collapse borders in the table
*/
function print_table_start($echobr = true, $width = '100%', $cellspacing = 0, $id = '', $border_collapse = false)
{
	global $tableadded;

	$tableadded = 1;

	// override legacy flags
	$width = '100%';
	$echobr = false;

	if ($echobr)
	{
		echo '<br />';
	}

	$id_html = ($id == '' ? '' : " id=\"$id\"");

	echo "\n<table cellpadding=\"4\" cellspacing=\"$cellspacing\" border=\"0\" align=\"center\" width=\"$width\" style=\"border-collapse:" . ($border_collapse ? 'collapse' : 'separate') . "\" class=\"tborder\"$id_html>\n";
}

// #############################################################################
/**
* Prints submit and reset buttons for the current form, then closes the form and table tags
*
* @param	string	Value for submit button - if left blank, will use $vbphrase['save']
* @param	string	Value for reset button - if left blank, will use $vbphrase['reset']
* @param	integer	Number of table columns the cell containing the buttons should span
* @param	string	Optional value for 'Go Back' button
* @param	string	Optional arbitrary HTML code to add to the table cell
* @param	boolean	If true, reverses the order of the buttons in the cell
*/
function print_submit_row($submitname = '', $resetname = '_default_', $colspan = 2, $goback = '', $extra = '', $alt = false)
{
	$vb5_config =& vB::getConfig();
	$vbphrase = vB_Api::instanceInternal('phrase')->fetch(array('save', 'reset'));

	static $count = 0;
	// do submit button
	if ($submitname === '_default_' OR $submitname === '')
	{
		$submitname = $vbphrase['save'];
	}

	$button1 = "\t<input type=\"submit\" id=\"submit$count\" class=\"button\" tabindex=\"1\" value=\"" . str_pad($submitname, 8, ' ', STR_PAD_BOTH) . "\" accesskey=\"s\" />\n";

	// do extra stuff
	if ($extra)
	{
		$extrabutton = "\t$extra\n";
	}

	// do reset button
	if ($resetname)
	{
		if ($resetname === '_default_')
		{
			$resetname = $vbphrase['reset'];
		}

		$resetbutton .= "\t<input type=\"reset\" id=\"reset$count\" class=\"button\" tabindex=\"1\" value=\"" . str_pad($resetname, 8, ' ', STR_PAD_BOTH) . "\" accesskey=\"r\" />\n";
	}

	// do goback button
	if ($goback)
	{
		$button2 = "\t<input type=\"button\" id=\"goback$count\" class=\"button\" value=\"" . str_pad($goback, 8, ' ', STR_PAD_BOTH) . "\" tabindex=\"1\"
			onclick=\"if (history.length) { history.back(1); } else { self.close(); }\"
			/>
			<script type=\"text/javascript\">
			<!--
			if (history.length < 1 || ((is_saf || is_moz) && history.length <= 1)) // safari + gecko start at 1
			{
				document.getElementById('goback$count').parentNode.removeChild(document.getElementById('goback$count'));
			}
			//-->
			</script>\n";
	}

	if ($alt)
	{
		$tfoot = $button2 . $extrabutton . $resetbutton . $button1;
	}
	else
	{
		$tfoot = $button1 . $extrabutton . $resetbutton . $button2;
	}

	// do debug tooltip
	if ($vb5_config['Misc']['debug'] AND is_array($GLOBALS['_HIDDENFIELDS']))
	{
		$tooltip = "HIDDEN FIELDS:";
		foreach($GLOBALS['_HIDDENFIELDS'] AS $key => $val)
		{
			$tooltip .= "\n\$$key = &quot;$val&quot;";
		}
	}
	else
	{
		$tooltip = '';
	}

	$count++;

	print_table_footer($colspan, $tfoot, $tooltip);
}

// #############################################################################
/**
* Prints a closing table tag and closes the form tag if it is open
*
* @param	integer	Column span of the optional table row to be printed
* @param	string	If specified, creates an additional table row with this code as its contents
* @param	string	Tooltip for optional table row
* @param	boolean	Whether or not to close the <form> tag
*/
function print_table_footer($colspan = 2, $rowhtml = '', $tooltip = '', $echoform = true, $extra = '')
{
	global $tableadded, $vbulletin;

	if ($rowhtml)
	{
		$tooltip = iif($tooltip != '', " title=\"$tooltip\"", '');
		if ($tableadded)
		{
			echo "<tr>\n\t<td class=\"tfoot\"" . iif($colspan != 1 ," colspan=\"$colspan\"") . " align=\"center\"$tooltip>$rowhtml</td>\n</tr>\n";
		}
		else
		{
			if (empty($extra) && !empty($tooltip))
			{
				$extra = $tooltip;
			}
			echo "<p align=\"center\"$tooltip>$extra</p>\n";
		}
	}

	if ($tableadded)
	{
		echo "</table>\n";
	}

	if ($echoform)
	{
		print_hidden_fields();

		echo "</form>\n<!-- form ended: " . $vbulletin->db->querycount ." queries executed -->\n\n";
	}
}

// #############################################################################
/**
* Prints out a closing table tag and opens another for page layout purposes
*
* @param	string	Code to be inserted between the two tables
* @param	string	Width for the new table - default = '100%'
*/
function print_table_break($insert = '', $width = '100%')
{
// ends the current table, leaves a break and starts it again.
	echo "</table>\n<br />\n\n";
	if ($insert)
	{
		echo "<!-- start mid-table insert -->\n$insert\n<!-- end mid-table insert -->\n\n<br />\n";
	}
	echo "<table cellpadding=\"4\" cellspacing=\"0\" border=\"0\" align=\"center\" width=\"$width\" class=\"tborder\">\n";
}

// #############################################################################
/**
* Prints the middle section of a table - similar to print_form_header but a bit different
*
* @param	string	R.A.T. value to be used
* @param	boolean	Specifies cb parameter
*
* @return	mixed	R.A.T.
*/
function print_form_middle($ratval, $call = true)
{
	global $vbulletin;
	return $ratval;
}

// #############################################################################
/**
* Prints out all cached hidden field values, then empties the $_HIDDENFIELDS array and starts again
*/
function print_hidden_fields()
{
	global $_HIDDENFIELDS;
	if (is_array($_HIDDENFIELDS))
	{
		//DEVDEBUG("Do hidden fields...");
		foreach($_HIDDENFIELDS AS $name => $value)
		{
			echo "<input type=\"hidden\" name=\"$name\" value=\"$value\" />\n";
			//DEVDEBUG("> hidden field: $name='$value'");
		}
	}
	$_HIDDENFIELDS = array();
}

// #############################################################################
/**
* Ensures that the specified text direction is valid
*
* @param	string	Text direction choice (ltr / rtl)
*
* @return	string	Valid text direction attribute
*/
function verify_text_direction($choice)
{

	$choice = strtolower($choice);

	// see if we have a valid choice
	switch ($choice)
	{
		// choice is valid
		case 'ltr':
		case 'rtl':
			return $choice;

		// choice is not valid
		default:
			if ($textdirection = vB_Template_Runtime::fetchStyleVar('textdirection'))
			{
				// invalid choice - return vB_Template_Runtime::fetchStyleVar default
				return $textdirection;
			}
			else
			{
				// invalid choice and no default defined
				return 'ltr';
			}
	}
}

// #############################################################################
/**
* Returns the alternate background css class from its current state
*
* @return	string
*/
function fetch_row_bgclass()
{
// returns the current alternating class for <TR> rows in the CP.
	global $bgcounter;
	return ($bgcounter++ % 2) == 0 ? 'alt1' : 'alt2';
}

// #############################################################################
/**
* Makes a column-spanning bar with a named <A> and a title, then  reinitialises the background class counter.
*
* @param	string	Title for the row
* @param	integer	Number of columns to span
* @param	boolean	Whether or not to htmlspecialchars the title
* @param	string	Name for <a name=""> anchor tag
* @param	string	Alignment for the title (center / left / right)
* @param	boolean	Whether or not to show the help button in the row
*/
function print_table_header($title, $colspan = 2, $htmlise = false, $anchor = '', $align = 'center', $helplink = true)
{
	global $bgcounter;

	if ($htmlise)
	{
		$title = htmlspecialchars_uni($title);
	}
	$title = "<b>$title</b>";
	if ($anchor != '')
	{
		$title = "<a name=\"$anchor\">$title</a>";
	}
	if ($helplink AND $help = construct_help_button('', NULL, '', 1))
	{
		$title = "\n\t\t<div style=\"float:" . vB_Template_Runtime::fetchStyleVar('right') . "\">$help</div>\n\t\t$title\n\t";
	}

	echo "<tr>\n\t<td class=\"tcat\" align=\"$align\"" . ($colspan != 1 ? " colspan=\"$colspan\"" : "") . ">$title</td>\n</tr>\n";

	$bgcounter = 0;
}

// #############################################################################
/**
* Prints a two-cell row with arbitrary contents in each cell
*
* @param	string	HTML contents for first cell
* @param	string	HTML comments for second cell
* @param	string	CSS class for row - if not specified, uses alternating alt1/alt2 classes
* @param	string	Vertical alignment attribute for row (top / bottom etc.)
* @param	string	Name for help button
* @param	boolean	If true, set first cell to 30% width and second to 70%
* @param 	array 	Two element array of integers to set the colspans for first and second element (array[0] and array[1])
*/
function print_label_row($title, $value = '&nbsp;', $class = '', $valign = 'top', $helpname = NULL, $dowidth = false, $colspan = array(1,1))
{
	if (!$class)
	{
		$class = fetch_row_bgclass();
	}

	if ($helpname !== NULL AND $helpbutton = construct_table_help_button($helpname))
	{
		$value = '<table cellpadding="0" cellspacing="0" border="0" width="100%"><tr valign="top"><td>' . $value . "</td><td align=\"" . vB_Template_Runtime::fetchStyleVar('right') . "\" style=\"padding-" . vB_Template_Runtime::fetchStyleVar('left') . ":4px\">$helpbutton</td></tr></table>";
	}

	if ($dowidth)
	{
		if (is_numeric($dowidth))
		{
			$left_width = $dowidth;
			$right_width = 100 - $dowidth;
		}
		else
		{
			$left_width = 70;
			$right_width = 30;
		}
	}

	$colattr = array();
	foreach($colspan as $col)
	{
		if ($col < 1)
		{
			$colattr[] = '';
		}
		else
		{
			$colattr[] = ' colspan="' . $col . '" ';
		}
	}

	echo "<tr valign=\"$valign\">
	<td class=\"$class\"" . ($dowidth ? " width=\"$left_width%\"" : '') . $colattr[0] . ">$title</td>
	<td class=\"$class\"" . ($dowidth ? " width=\"$right_width%\"" : '') . $colattr[1] . ">$value</td>\n</tr>\n";
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
* @param 	array 	Two element array of integers to set the colspans for the label and input (array[0] and array[1])
*/
function print_input_row($title, $name, $value = '', $htmlise = true, $size = 35, $maxlength = 0, $direction = '', $inputclass = false, $inputid = false, $colspan = array(1,1))
{
	global $vbulletin, $vb5_config;

	$direction = verify_text_direction($direction);

	if($inputid===false)
	{
		$id = 'it_' . $name . '_' . fetch_uniqueid_counter();
	}
	else
	{
		$id = $inputid;
	}

	print_label_row(
		$title,
		"<div id=\"ctrl_$name\"><input type=\"text\" class=\"" . iif($inputclass, $inputclass, 'bginput') .
		"\" name=\"$name\" id=\"$id\" value=\"" . iif($htmlise, htmlspecialchars_uni($value), $value) . "\" size=\"$size\"" .
		iif($maxlength, " maxlength=\"$maxlength\"") . " dir=\"$direction\" tabindex=\"1\"" .
		iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot;\"") . " /></div>",
		'', 'top', $name, false, $colspan
	);
}

// #############################################################################
/**
* Prints a row containing an <input type="text" /> and a <select>
*
* @param	string	Title for row
* @param	string	Name for input field
* @param	string	Value for input field
* @param	string	Name for select field
* @param	array	Array of options for select field - array(0 => 'No', 1 => 'Yes') etc.
* @param	string	Value of selected option for select field
* @param	boolean	Whether or not to htmlspecialchars the input field value
* @param	integer	Size for input field
* @param	integer	Size for select field (if not 0, is multi-row)
* @param	integer	Max length for input field
* @param	string	Text direction for input field
* @param	mixed	If specified, overrides the default CSS class for the input field
* @param	boolean	Allow multiple selections from select field?
*/
function print_input_select_row($title, $inputname, $inputvalue = '', $selectname, $selectarray, $selected = '', $htmlise = true, $inputsize = 35, $selectsize = 0, $maxlength = 0, $direction = '', $inputclass = false, $multiple = false)
{
	global $vbulletin, $vb5_config;

	$direction = verify_text_direction($direction);

	print_label_row(
		$title,
		"<div id=\"ctrl_$inputname\">" .
		"<input type=\"text\" class=\"" . iif($inputclass, $inputclass, 'bginput') . "\" name=\"$inputname\" value=\"" . iif($htmlise, htmlspecialchars_uni($inputvalue), $inputvalue) . "\" size=\"$inputsize\"" . iif($maxlength, " maxlength=\"$maxlength\"") . " dir=\"$direction\" tabindex=\"1\"" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$inputname&quot;\"") . " />&nbsp;" .
		"<select name=\"$selectname\" tabindex=\"1\" class=\"" . iif($inputclass, $inputclass, 'bginput') . '"' . iif($selectsize, " size=\"$selectsize\"") . iif($multiple, ' multiple="multiple"') . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$selectname&quot;\"") . ">\n" .
		construct_select_options($selectarray, $selected, $htmlise) .
		"</select></div>\n",
		'', 'top', $inputname
	);
}

// #############################################################################
/**
* Prints a row containing a <textarea>
*
* @param	string	Title for row
* @param	string	Name for textarea field
* @param	string	Value for textarea field
* @param	integer	Number of rows for textarea field
* @param	integer	Number of columns for textarea field
* @param	boolean	Whether or not to htmlspecialchars the textarea field value
* @param	boolean	Whether or not to show the 'large edit box' button
* @param	string	Text direction for textarea field
* @param	mixed	If specified, overrides the default CSS class for the textare field
*/
function print_textarea_row($title, $name, $value = '', $rows = 4, $cols = 40, $htmlise = true, $doeditbutton = true, $direction = '', $textareaclass = false)
{
	global $vbulletin;
	static $vbphrase;
	$vb5_config =& vB::getConfig();

	if (empty($vbphrase))
	{
		$vbphrase = vB_Api::instanceInternal('phrase')->fetch(array('large_edit_box', 'increase_size'));
	}

	$direction = verify_text_direction($direction);

	if (!$doeditbutton OR strpos($name,'[') !== false)
	{
		$openwindowbutton = '';
	}
	else
	{
		$openwindowbutton = '<p><input type="button" unselectable="on" value="' . $vbphrase['large_edit_box'] . '" class="button" style="font-weight:normal" onclick="window.open(\'textarea.php?dir=' . $direction . '&name=' . $name. '\',\'textpopup\',\'resizable=yes,scrollbars=yes,width=\' + (screen.width - (screen.width/10)) + \',height=600\');" /></p>';
	}

	$vbulletin->textarea_id = 'ta_' . $name . '_' . fetch_uniqueid_counter();

	// trigger hasLayout for IE to prevent template box from jumping (#22761)
	$ie_reflow_css = (is_browser('ie') ? 'style="zoom:1"' : '');

	$resizer = "<div class=\"smallfont\"><a href=\"#\" $ie_reflow_css onclick=\"return resize_textarea(1, '{$vbulletin->textarea_id}')\">$vbphrase[increase_size]</a> <a href=\"#\" $ie_reflow_css onclick=\"return resize_textarea(-1, '{$vbulletin->textarea_id}')\">$vbphrase[decrease_size]</a></div>";

	print_label_row(
		$title . $openwindowbutton,
		"<div id=\"ctrl_$name\"><textarea name=\"$name\" id=\"{$vbulletin->textarea_id}\"" . iif($textareaclass, " class=\"$textareaclass\"") . " rows=\"$rows\" cols=\"$cols\" wrap=\"virtual\" dir=\"$direction\" tabindex=\"1\"" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot;\"") . ">" . iif($htmlise, htmlspecialchars_uni($value), $value) . "</textarea>$resizer</div>",
		'', 'top', $name
	);
}

// #############################################################################
/**
* Prints a row containing 'yes', 'no' <input type="radio" / > buttons
*
* @param	string	Title for row
* @param	string	Name for radio buttons
* @param	string	Selected button's value
* @param	string	Optional Javascript code to run when radio buttons are clicked - example: ' onclick="do_something()"'
*/
function print_yes_no_row($title, $name, $value = 1, $onclick = '')
{
	static $vbphrase;

	if (empty($vbphrase))
	{
		$vbphrase = vB_Api::instanceInternal('phrase')->fetch(array('yes', 'no', 'yes_but_not_parsing_html'));
	}

	$vb5_config =& vB::getConfig();

	if ($onclick)
	{
		$onclick = " onclick=\"$onclick\"";
	}

	$uniqueid = fetch_uniqueid_counter();

	print_label_row(
		$title,
		"<div id=\"ctrl_$name\" class=\"smallfont\" style=\"white-space:nowrap\">
		<label for=\"rb_1_{$name}_$uniqueid\"><input type=\"radio\" name=\"$name\" id=\"rb_1_{$name}_$uniqueid\" value=\"" . (($name == 'user[pmpopup]' AND $value == 2) ? 2 : 1) . "\" tabindex=\"1\"$onclick" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot; value=&quot;1&quot;\"") . iif($value == 1 OR ($name == 'user[pmpopup]' AND $value == 2), ' checked="checked"') . " />$vbphrase[yes]" . iif(vB_Template_Runtime::fetchStyleVar('textdirection') == 'rtl', "&rlm;") . "</label>
		<label for=\"rb_0_{$name}_$uniqueid\"><input type=\"radio\" name=\"$name\" id=\"rb_0_{$name}_$uniqueid\" value=\"0\" tabindex=\"1\"$onclick" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot; value=&quot;0&quot;\"") . iif($value == 0, ' checked="checked"') . " />$vbphrase[no]" . iif(vB_Template_Runtime::fetchStyleVar('textdirection') == 'rtl', "&rlm;") . "</label>" .
		iif($value == 2 AND $name == 'customtitle', "
			<label for=\"rb_2_{$name}_$uniqueid\"><input type=\"radio\" name=\"$name\" id=\"rb_2_{$name}_$uniqueid\" value=\"2\" tabindex=\"1\"$onclick" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot; value=&quot;2&quot;\"") . " checked=\"checked\" />$vbphrase[yes_but_not_parsing_html]</label>"
		) . "\n\t</div>",
		'', 'top', $name
	);
}

// #############################################################################
/**
* Prints a row containing 'yes', 'no' and 'other' <input type="radio" /> buttons
*
* @param	string	Title for row
* @param	string	Name for radio buttons
* @param	string	Text label for third button
* @param	string	Selected button's value
* @param	string	Optional Javascript code to run when radio buttons are clicked - example: ' onclick="do_something()"'
*/
function print_yes_no_other_row($title, $name, $thirdopt, $value = 1, $onclick = '')
{
	global $vbphrase, $vbulletin, $vb5_config;

	if ($onclick)
	{
		$onclick = " onclick=\"$onclick\"";
	}

	$uniqueid = fetch_uniqueid_counter();

	print_label_row(
		$title,
		"<div id=\"ctrl_$name\" class=\"smallfont\" style=\"white-space:nowrap\">
		<label for=\"rb_1_{$name}_$uniqueid\"><input type=\"radio\" name=\"$name\" id=\"rb_1_{$name}_$uniqueid\" value=\"1\" tabindex=\"1\"$onclick" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot; value=&quot;1&quot;\"") . iif($value == 1, ' checked="checked"') . " />$vbphrase[yes]" . iif(vB_Template_Runtime::fetchStyleVar('textdirection') == 'rtl', "&rlm;") . "</label>
		<label for=\"rb_0_{$name}_$uniqueid\"><input type=\"radio\" name=\"$name\" id=\"rb_0_{$name}_$uniqueid\" value=\"0\" tabindex=\"1\"$onclick" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot; value=&quot;0&quot;\"") . iif($value == 0, ' checked="checked"') . " />$vbphrase[no]" . iif(vB_Template_Runtime::fetchStyleVar('textdirection') == 'rtl', "&rlm;") . "</label>
		<label for=\"rb_x_{$name}_$uniqueid\"><input type=\"radio\" name=\"$name\" id=\"rb_x_{$name}_$uniqueid\" value=\"-1\" tabindex=\"1\"$onclick" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot; value=&quot;-1&quot;\"") . iif($value == -1, ' checked="checked"') . " />$thirdopt" . iif(vB_Template_Runtime::fetchStyleVar('textdirection') == 'rtl', "&rlm;") . "</label>
		\n\t</div>",
		'', 'top', $name
	);
}

// #############################################################################
/**
* Prints a row containing an <input type="checkbox" />
*
* @param	string	Title for row
* @param	string	Name for checkbox
* @param	boolean	Whether or not to check the box
* @param	string	Value for checkbox
* @param	string	Text label for checkbox
* @param	string	Optional Javascript code to run when checkbox is clicked - example: ' onclick="do_something()"'
*/
function print_checkbox_row($title, $name, $checked = true, $value = 1, $labeltext = '', $onclick = '')
{
	global $vbphrase, $vbulletin, $vb5_config;

	if ($labeltext == '')
	{
		$labeltext = $vbphrase['yes'];
	}

	$uniqueid = fetch_uniqueid_counter();

	print_label_row(
		"<label for=\"{$name}_$uniqueid\">$title</label>",
		"<div id=\"ctrl_$name\"><label for=\"{$name}_$uniqueid\" class=\"smallfont\"><input type=\"checkbox\" name=\"$name\" id=\"{$name}_$uniqueid\" value=\"$value\" tabindex=\"1\"" . iif($onclick, " onclick=\"$onclick\"") . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot;\"") . iif($checked, ' checked="checked"') . " /><strong>$labeltext</strong></label></div>",
		'', 'top', $name
	);
}

// #############################################################################
/**
* Prints a row containing a single 'yes' <input type="radio" /> button
*
* @param	string	Title for row
* @param	string	Name for radio button
* @param	string	Text label for radio button
* @param	boolean	Whether or not to check the radio button
* @param	string	Value for radio button
*/
function print_yes_row($title, $name, $yesno, $checked, $value = 1)
{
	global $vbulletin, $vb5_config;

	$uniqueid = fetch_uniqueid_counter();

	print_label_row(
		"<label for=\"{$name}_{$value}_$uniqueid\">$title</label>",
		"<div id=\"ctrl_$name\"><label for=\"{$name}_{$value}_$uniqueid\"><input type=\"radio\" name=\"$name\" id=\"{$name}_{$value}_$uniqueid\" value=\"$value\" tabindex=\"1\"" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot;\"") . iif($checked, ' checked="checked"') . " />$yesno</label></div>",
		'', 'top', $name
	);
}

// #############################################################################
/**
* Prints a row containing an <input type="password" />
*
* @param	string	Title for row
* @param	string	Name for password field
* @param	string	Value for password field
* @param	boolean	Whether or not to htmlspecialchars the value
* @param	integer	Size of the password field
*/
function print_password_row($title, $name, $value = '', $htmlise = 1, $size = 35)
{
	global $vbulletin, $vb5_config;

	print_label_row(
		$title,
		"<div id=\"ctrl_$name\"><input type=\"password\" class=\"bginput\" name=\"$name\" value=\"" . iif($htmlise, htmlspecialchars_uni($value), $value) . "\" size=\"$size\" tabindex=\"1\"" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot;\"") . " /></div>",
		'', 'top', $name
	);
}

// #############################################################################
/**
* Prints a row containing an <input type="file" />
*
* @param	string	Title for row
* @param	string	Name for file upload field
* @param	integer	Max uploaded file size in bytes
* @param	integer	Size of file upload field
*/
function print_upload_row($title, $name, $maxfilesize = 1000000, $size = 35)
{
	global $vbulletin, $vb5_config;

	construct_hidden_code('MAX_FILE_SIZE', $maxfilesize);

	// Don't style the file input for Opera or Firefox 3. #25838
	$use_bginput = (is_browser('opera') OR is_browser('firefox', 3) ? false : true);

	print_label_row(
		$title,
		"<div id=\"ctrl_$name\"><input type=\"file\"" . ($use_bginput ? ' class="bginput"' : '') . " name=\"$name\" size=\"$size\" tabindex=\"1\"" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot;\"") . " /></div>",
		'', 'top', $name
	);
}

// #############################################################################
/**
* Prints a column-spanning row containing arbitrary HTML
*
* @param	string	HTML contents for row
* @param	boolean	Whether or not to htmlspecialchars the row contents
* @param	integer	Number of columns to span
* @param	string	Optional CSS class to override the alternating classes
* @param	string	Alignment for row contents
* @param	string	Name for help button
*/
function print_description_row($text, $htmlise = false, $colspan = 2, $class = '', $align = '', $helpname = NULL)
{
	if (!$class)
	{
		$class = fetch_row_bgclass();
	}

	if ($helpname !== NULL AND $help = construct_help_button($helpname))
	{
		$text = "\n\t\t<div style=\"float:" . vB_Template_Runtime::fetchStyleVar('right') . "\">$help</div>\n\t\t$text\n\t";
	}

	echo "<tr valign=\"top\">
	<td class=\"$class\"" . iif($colspan != 1," colspan=\"$colspan\"") . iif($align, " align=\"$align\"") . ">" . iif($htmlise, htmlspecialchars_uni($text), $text) . "</td>\n</tr>\n";
}

// #############################################################################
/**
* Prints a <colgroup> section for styling table columns
*
* @param	array	Column styles - each array element represents HTML code for a column
*/
function print_column_style_code($columnstyles)
{
	if (is_array($columnstyles))
	{
		$span = sizeof($columnstyles);
		if ($span > 1)
		{
			echo "<colgroup span=\"$span\">\n";
		}
		foreach ($columnstyles AS $columnstyle)
		{
			if ($columnstyle != '')
			{
				$columnstyle = " style=\"$columnstyle\"";
			}
			echo "\t<col$columnstyle></col>\n";
		}
		if ($span > 1)
		{
			echo "</colgroup>\n";
		}
	}
}

// #############################################################################
/**
* Prints a row containing an <hr />
*
* @param	integer	Number of columns to span
* @param	string	Optional CSS class to override the alternating classes
* @param	string	Optional CSS attributes to apply to the <hr /> - example 'color:red; width:50%';
*/
function print_hr_row($colspan = 2, $class = '', $hrstyle = '')
{
	print_description_row('<hr' . iif($hrstyle, " style=\"$hrstyle\"") . ' />', 0, $colspan, $class, 'center');
}

// #############################################################################
/**
* Adds an entry to the $_HIDDENFIELDS array for later printing as an <input type="hidden" />
*
* @param	string	Name for hidden field
* @param	string	Value for hidden field
* @param	boolean	Whether or not to htmlspecialchars the hidden field value
*/
function construct_hidden_code($name, $value = '', $htmlise = true)
{
	global $_HIDDENFIELDS;

	$_HIDDENFIELDS["$name"] = iif($htmlise, htmlspecialchars_uni($value), $value);
}

// #############################################################################
/**
* Prints a row containing form elements to input a date & time
*
* Resulting form element names: $name[day], $name[month], $name[year], $name[hour], $name[minute]
*
* @param	string	Title for row
* @param	string	Base name for form elements - $name[day], $name[month], $name[year] etc.
* @param	mixed	Unix timestamp to be represented by the form fields OR SQL date field (yyyy-mm-dd)
* @param	boolean	Whether or not to show the time input components, or only the date
* @param	boolean	If true, expect an SQL date field from the unix timestamp parameter instead (for birthdays)
* @param	string	Vertical alignment for the row
*/
function print_time_row($title, $name = 'date', $unixtime = '', $showtime = true, $birthday = false, $valign = 'middle')
{
	global $vbphrase, $vbulletin, $vb5_config;
	static $datepicker_output = false;

	if (!$datepicker_output)
	{
		echo '
			<script type="text/javascript" src="../clientscript/vbulletin_date_picker.js?v=' . SIMPLE_VERSION . '"></script>
			<script type="text/javascript">
			<!--
				vbphrase["sunday"]    = "' . $vbphrase['sunday'] . '";
				vbphrase["monday"]    = "' . $vbphrase['monday'] . '";
				vbphrase["tuesday"]   = "' . $vbphrase['tuesday'] . '";
				vbphrase["wednesday"] = "' . $vbphrase['wednesday'] . '";
				vbphrase["thursday"]  = "' . $vbphrase['thursday'] . '";
				vbphrase["friday"]    = "' . $vbphrase['friday'] . '";
				vbphrase["saturday"]  = "' . $vbphrase['saturday'] . '";
			-->
			</script>
		';
		$datepicker_output = true;
	}

	$monthnames = array(
		0  => '- - - -',
		1  => $vbphrase['january'],
		2  => $vbphrase['february'],
		3  => $vbphrase['march'],
		4  => $vbphrase['april'],
		5  => $vbphrase['may'],
		6  => $vbphrase['june'],
		7  => $vbphrase['july'],
		8  => $vbphrase['august'],
		9  => $vbphrase['september'],
		10 => $vbphrase['october'],
		11 => $vbphrase['november'],
		12 => $vbphrase['december'],
	);

	if (is_array($unixtime))
	{
		require_once(DIR . '/includes/functions_misc.php');
		$unixtime = vbmktime(0, 0, 0, $unixtime['month'], $unixtime['day'], $unixtime['year']);
	}

	if ($birthday)
	{ // mktime() on win32 doesn't support dates before 1970 so we can't fool with a negative timestamp
		if ($unixtime == '')
		{
			$month = 0;
			$day = '';
			$year = '';
		}
		else
		{
			$temp = explode('-', $unixtime);
			$month = intval($temp[0]);
			$day = intval($temp[1]);
			if ($temp[2] == '0000')
			{
				$year = '';
			}
			else
			{
				$year = intval($temp[2]);
			}
		}
	}
	else
	{
		if ($unixtime)
		{
			$month = vbdate('n', $unixtime, false, false);
			$day = vbdate('j', $unixtime, false, false);
			$year = vbdate('Y', $unixtime, false, false);
			$hour = vbdate('G', $unixtime, false, false);
			$minute = vbdate('i', $unixtime, false, false);
		}
	}

	$cell = array();
	$cell[] = "<label for=\"{$name}_month\">$vbphrase[month]</label><br /><select name=\"{$name}[month]\" id=\"{$name}_month\" tabindex=\"1\" class=\"bginput\"" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name" . "[month]&quot;\"") . ">\n" . construct_select_options($monthnames, $month) . "\t\t</select>";
	$cell[] = "<label for=\"{$name}_date\">$vbphrase[day]</label><br /><input type=\"text\" class=\"bginput\" name=\"{$name}[day]\" id=\"{$name}_date\" value=\"$day\" size=\"4\" maxlength=\"2\" tabindex=\"1\"" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name" . "[day]&quot;\"") . ' />';
	$cell[] = "<label for=\"{$name}_year\">$vbphrase[year]</label><br /><input type=\"text\" class=\"bginput\" name=\"{$name}[year]\" id=\"{$name}_year\" value=\"$year\" size=\"4\" maxlength=\"4\" tabindex=\"1\"" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name" . "[year]&quot;\"") . ' />';
	if ($showtime)
	{
		$cell[] = $vbphrase['hour'] . '<br /><input type="text" tabindex="1" class="bginput" name="' . $name . '[hour]" value="' . $hour . '" size="4"' . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name" . "[hour]&quot;\"") . ' />';
		$cell[] = $vbphrase['minute'] . '<br /><input type="text" tabindex="1" class="bginput" name="' . $name . '[minute]" value="' . $minute . '" size="4"' . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name" . "[minute]&quot;\"") . ' />';
	}
	$inputs = '';
	foreach($cell AS $html)
	{
		$inputs .= "\t\t<td><span class=\"smallfont\">$html</span></td>\n";
	}

	print_label_row(
		$title,
		"<div id=\"ctrl_$name\"><table cellpadding=\"0\" cellspacing=\"2\" border=\"0\"><tr>\n$inputs\t\n</tr></table></div>",
		'', 'top', $name
	);

	echo "<script type=\"text/javascript\"> new vB_DatePicker(\"{$name}_year\", \"{$name}_\", \"" . $vbulletin->userinfo['startofweek']  . "\"); </script>\r\n";
}

// #############################################################################
/**
* Prints a row containing an arbitrary number of cells, each containing arbitrary HTML
*
* @param	array	Each array element contains the HTML code for one cell. If the array contains 4 elements, 4 cells will be printed
* @param	boolean	If true, make all cells' contents bold and use the 'thead' CSS class
* @param	mixed	If specified, override the alternating CSS classes with the specified class
* @param	integer	Cell offset - controls alignment of cells... best to experiment with small +ve and -ve numbers
* @param	string	Vertical alignment for the row
* @param	boolean	Whether or not to treat the cells as part of columns - will alternate classes horizontally instead of vertically
* @param	boolean	Whether or not to use 'smallfont' for cell contents
*/
function print_cells_row($array, $isheaderrow = false, $class = false, $i = 0, $valign = 'top', $column = false, $smallfont = false)
{
	global $colspan, $bgcounter;

	if (is_array($array))
	{
		$colspan = sizeof($array);
		if ($colspan)
		{
			$j = 0;
			$doecho = 0;

			if (!$class AND !$column AND !$isheaderrow)
			{
				$bgclass = fetch_row_bgclass();
			}
			elseif ($isheaderrow)
			{
				$bgclass = 'thead';
			}
			else
			{
				$bgclass = $class;
			}

			$bgcounter = iif($column, 0, $bgcounter);
			$out = "<tr valign=\"$valign\" align=\"center\">\n";

			foreach($array AS $key => $val)
			{
				$j++;
				if ($val == '' AND !is_int($val))
				{
					$val = '&nbsp;';
				}
				else
				{
					$doecho = 1;
				}

				if ($i++ < 1)
				{
					$align = ' align="' . vB_Template_Runtime::fetchStyleVar('left') . '"';
				}
				elseif ($j == $colspan AND $i == $colspan AND $j != 2)
				{
					$align = ' align="' . vB_Template_Runtime::fetchStyleVar('right') . '"';
				}
				else
				{
					$align = '';
				}

				if (!$class AND $column)
				{
					$bgclass = fetch_row_bgclass();
				}
				if ($smallfont)
				{
					$val = "<span class=\"smallfont\">$val</span>";
				}
				$out .= "\t<td" . iif($column, " class=\"$bgclass\"", " class=\"$bgclass\"") . "$align>$val</td>\n";
			}

			$out .= "</tr>\n";

			if ($doecho)
			{
				echo $out;
			}
		}
	}
}

// #############################################################################
/**
* Prints a row containing a number of <input type="checkbox" /> fields representing a user's membergroups
*
* @param	string	Title for row
* @param	string	Base name for checkboxes - $name[]
* @param	integer	Number of columns to split checkboxes into
* @param	mixed	Either NULL or a user info array
*/
function print_membergroup_row($title, $name = 'membergroup', $columns = 0, $userarray = NULL)
{
	global $vbulletin, $iusergroupcache, $vb5_config;

	$uniqueid = fetch_uniqueid_counter();

	if (!is_array($iusergroupcache))
	{
		$iusergroupcache = array();
		$usergroups = $vbulletin->db->query_read("SELECT usergroupid,title FROM " . TABLE_PREFIX . "usergroup ORDER BY title");
		while ($usergroup = $vbulletin->db->fetch_array($usergroups))
		{
			$iusergroupcache["$usergroup[usergroupid]"] = $usergroup['title'];
		}
		unset($usergroup);
		$vbulletin->db->free_result($usergroups);
	}
	// create a blank user array if one is not set
	if (!is_array($userarray))
	{
		$userarray = array('usergroupid' => 0, 'membergroupids' => '');
	}
	$options = array();
	foreach($iusergroupcache AS $usergroupid => $grouptitle)
	{
		// don't show the user's primary group (if set)
		if ($usergroupid != $userarray['usergroupid'])
		{
			$options[] = "\t\t<div><label for=\"$name{$usergroupid}_$uniqueid\" title=\"usergroupid: $usergroupid\"><input type=\"checkbox\" tabindex=\"1\" name=\"$name"."[]\" id=\"$name{$usergroupid}_$uniqueid\" value=\"$usergroupid\"" . iif(strpos(",$userarray[membergroupids],", ",$usergroupid,") !== false, ' checked="checked"') . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot;\"") . " />$grouptitle</label></div>\n";
		}
	}

	$class = fetch_row_bgclass();
	if ($columns > 1)
	{
		$html = "\n\t<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr valign=\"top\">\n";
		$counter = 0;
		$totaloptions = sizeof($options);
		$percolumn = ceil($totaloptions/$columns);
		for ($i = 0; $i < $columns; $i++)
		{
			$html .= "\t<td class=\"$class\"><span class=\"smallfont\">\n";
			for ($j = 0; $j < $percolumn; $j++)
			{
				$html .= $options[$counter++];
			}
			$html .= "\t</span></td>\n";
		}
		$html .= "</tr></table>\n\t";
	}
	else
	{
		$html = "<div id=\"ctrl_$name\" class=\"smallfont\">\n" . implode('', $options) . "\t</div>";
	}

	print_label_row($title, $html, $class, 'top', $name);
}

// #############################################################################
/**
* Prints a row containing a <select> field
*
* @param	string	Title for row
* @param	string	Name for select field
* @param	array	Array of value => text pairs representing '<option value="$key">$value</option>' fields
* @param	string	Selected option
* @param	boolean	Whether or not to htmlspecialchars the text for the options
* @param	integer	Size of select field (non-zero means multi-line)
* @param	boolean	Whether or not to allow multiple selections
*/
function print_select_row($title, $name, $array, $selected = '', $htmlise = false, $size = 0, $multiple = false)
{
	global $vbulletin, $vb5_config;

	$uniqueid = fetch_uniqueid_counter();

	$select = "<div id=\"ctrl_$name\"><select name=\"$name\" id=\"sel_{$name}_$uniqueid\" tabindex=\"1\" class=\"bginput\"" . iif($size, " size=\"$size\"") . iif($multiple, ' multiple="multiple"') . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot;\"") . ">\n";
	$select .= construct_select_options($array, $selected, $htmlise);
	$select .= "</select></div>\n";

	print_label_row($title, $select, '', 'top', $name);
}

// #############################################################################
/**
* Returns a list of <option> fields, optionally with one selected
*
* @param	array	Array of value => text pairs representing '<option value="$key">$value</option>' fields
* @param	string	Selected option
* @param	boolean	Whether or not to htmlspecialchars the text for the options
*
* @return	string	List of <option> tags
*/
function construct_select_options($array, $selectedid = '', $htmlise = false)
{
	if (is_array($array))
	{
		$options = '';
		foreach($array AS $key => $val)
		{
			if (is_array($val))
			{
				$options .= "\t\t<optgroup label=\"" . iif($htmlise, htmlspecialchars_uni($key), $key) . "\">\n";
				$options .= construct_select_options($val, $selectedid, $tabindex, $htmlise);
				$options .= "\t\t</optgroup>\n";
			}
			else
			{
				if (is_array($selectedid))
				{
					$selected = iif(in_array($key, $selectedid), ' selected="selected"', '');
				}
				else
				{
					$selected = iif($key == $selectedid, ' selected="selected"', '');
				}
				$options .= "\t\t<option value=\"" . iif($key !== 'no_value', $key) . "\"$selected>" . iif($htmlise, vB_String::htmlSpecialCharsUni($val), $val) . "</option>\n";
			}
		}
	}
	return $options;
}

// #############################################################################
/**
* Prints a row containing a number of <input type="radio" /> buttons
*
* @param	string	Title for row
* @param	string	Name for radio buttons
* @param	array	Array of value => text pairs representing '<input type="radio" value="$key" />$value' fields
* @param	string	Selected radio button value
* @param	string	CSS class for <span> surrounding radio buttons
* @param	boolean	Whether or not to htmlspecialchars the text for the buttons
*/
function print_radio_row($title, $name, $array, $checked = '', $class = 'normal', $htmlise = false)
{
	$radios = "<div class=\"$class\">\n";
	$radios .= construct_radio_options($name, $array, $checked, $htmlise);
	$radios .= "\t</div>";

	print_label_row($title, $radios, '', 'top', $name);
}

// #############################################################################
/**
* Returns a list of <input type="radio" /> buttons, optionally with one selected
*
* @param	string	Name for radio buttons
* @param	array	Array of value => text pairs representing '<input type="radio" value="$key" />$value' fields
* @param	string	Selected radio button value
* @param	boolean	Whether or not to htmlspecialchars the text for the buttons
* @param	string	Indent string to place before buttons
*
* @return	string	List of <input type="radio" /> buttons
*/
function construct_radio_options($name, $array, $checkedid = '', $htmlise = false, $indent = '')
{
	global $vbulletin, $vb5_config;

	$options = "<div class=\"ctrl_$ctrl\">";

	if (is_array($array))
	{
		$uniqueid = fetch_uniqueid_counter();

		foreach($array AS $key => $val)
		{
			if (is_array($val))
			{
				$options .= "\t\t<b>" . iif($htmlise, htmlspecialchars_uni($key), $key) . "</b><br />\n";
				$options .= construct_radio_options($name, $val, $checkedid, $htmlise, '&nbsp; &nbsp; ');
			}
			else
			{
				$options .= "\t\t<label for=\"rb_$name{$key}_$uniqueid\">$indent<input type=\"radio\" name=\"$name\" id=\"rb_$name{$key}_$uniqueid\" tabindex=\"1\" value=\"" . iif($key !== 'no_value', $key) . "\"" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot; value=&quot;$key&quot;\"") . iif($key == $checkedid, ' checked="checked"') . " />" . iif($htmlise, htmlspecialchars_uni($val), $val) . "</label><br />\n";
			}
		}
	}

	$options .= "</div>";

	return $options;
}

// #############################################################################
/**
* Returns a <select> menu populated with <option> fields representing calendar months
*
* @param	integer	Selected calendar month (1 = January ... 12 = December)
* @param	string	Name for select field
* @param	boolean	Whether or not to htmlspecialchars the option text
*
* @return	string	Select menu with month options
*/
function construct_month_select_html($selected = 1, $name = 'month', $htmlise = false)
{
	global $vbphrase, $vbulletin, $vb5_config;

	$select = "<select name=\"$name\" tabindex=\"1\" class=\"bginput\"" . iif($vb5_config['Misc']['debug'], " title=\"name=&title;$name&quot;\"") . ">\n";
	$array = array(
		1 => $vbphrase['january'],
			$vbphrase['february'],
			$vbphrase['march'],
			$vbphrase['april'],
			$vbphrase['may'],
			$vbphrase['june'],
			$vbphrase['july'],
			$vbphrase['august'],
			$vbphrase['september'],
			$vbphrase['october'],
			$vbphrase['november'],
			$vbphrase['december']
		);
	$select .= construct_select_options($array, $selected, $htmlise);
	$select .= "</select>\n";

	return $select;
}

// #############################################################################
/**
* Returns a <select> menu populated with <option> fields representing days in a month
*
* @param	integer	Selected day of the month (1 = 1st, 31 = 31st)
* @param	string	Name for select field
* @param	boolean	Whether or not to htmlspecialchars the option text
*
* @return	string	Select menu with day options
*/
function construct_day_select_html($selected = 1, $name = 'day', $htmlise = false)
{
	global $vbulletin, $vb5_config;

	$select = "<select name=\"$name\" tabindex=\"1\" class=\"bginput\"" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot;\"") . ">\n";
	$array = array(1 => 1,	2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15,
		16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31);
	$select .= construct_select_options($array, $selected, $htmlise);
	$select .= "</select>\n";

	return $select;
}

// #############################################################################
/**
* Prints a row containing a <select> menu containing the results of a simple select from a db table
*
* NB: This will only work if the db table contains '{tablename}id' and 'title' fields
*
* @param	string	Title for row
* @param	string	Name for select field
* @param	string	Name of db table to select from
* @param	string	Value of selected option
* @param	string	Optional extra <option> for the top of the list - value is -1, specify text here
* @param	integer	Size of select field. If non-zero, shows multi-line
* @param	string	Optional 'WHERE' clause for the SELECT query
* @param	boolean	Whether or not to allow multiple selections
*/
function print_chooser_row($title, $name, $tablename, $selvalue = -1, $extra = '', $size = 0, $wherecondition = '', $multiple = false)
{
	global $vbulletin;

	$tableid = $tablename . 'id';

	// check for existence of $iusergroupcache / $vbulletin->iforumcache etc first...
	$cachename = 'i' . $tablename . 'cache_' .  md5($wherecondition);

	if (!is_array($GLOBALS["$cachename"]))
	{
		$GLOBALS["$cachename"] = array();
		$result = $vbulletin->db->query_read("SELECT title, $tableid FROM " . TABLE_PREFIX . "$tablename $wherecondition ORDER BY title");
		while ($currow = $vbulletin->db->fetch_array($result))
		{
			$GLOBALS["$cachename"]["$currow[$tableid]"] = $currow['title'];
		}
		unset($currow);
		$vbulletin->db->free_result($result);
	}

	$selectoptions = array();
	if ($extra)
	{
		$selectoptions['-1'] = $extra;
	}

	foreach ($GLOBALS["$cachename"] AS $itemid => $itemtitle)
	{
		$selectoptions["$itemid"] = $itemtitle;
	}

	print_select_row($title, $name, $selectoptions, $selvalue, 0, $size, $multiple);
}

// #############################################################################
/**
* Prints a row containing a <select> menu of available calendars
*
* @param	string	Title for row
* @param	string	Name for select field
* @param	integer	Selected calendar id
* @param	string	Name for optional top option in menu (no name, no display)
*/
function print_calendar_chooser($title, $name, $selectedid, $topname = '')
{
	global $vbulletin, $vb5_config;

	$calendars = $vbulletin->db->query_read("SELECT title, calendarid FROM " . TABLE_PREFIX . "calendar ORDER BY displayorder");

	$htmlselect = "\n\t<select name=\"$name\" tabindex=\"1\" class=\"bginput\"" . iif($vb5_config['Misc']['debug'], " title=\"name=&quot;$name&quot;\"") . ">\n";

	$selectoptions = array();
	if ($topname != '')
	{
		$selectoptions['-1'] = $topname;
	}

	while ($calendar = $vbulletin->db->fetch_array($calendars))
	{
		$selectoptions["$calendar[calendarid]"] = $calendar['title'];
	}

	print_select_row($title, $name, $selectoptions, $selectedid);
}

// #############################################################################
/**
* Prints a row containing a <select> list of forums, complete with displayorder, parenting and depth information
*
* @param	string	text for the left cell of the table row
* @param	string	name of the <select>
* @param	mixed	selected <option>
* @param	string	name given to the -1 <option>
* @param	boolean	display the -1 <option> or not.
* @param	boolean	when true, allows multiple selections to be made. results will be stored in $name's array
* @param	string	Text to be used in sprintf() to indicate a 'category' forum, eg: '%s (Category)'. Leave blank for no category indicator
*/
function print_forum_chooser($title, $name, $selectedid = -1, $topname = null, $displayselectforum = false, $multiple = false, $category_phrase = null)
{
	if ($displayselectforum AND $selectedid <= 0)
	{
		$selectedid = 0;
	}

	print_select_row($title, $name, construct_forum_chooser_options($displayselectforum, $topname, $category_phrase), $selectedid, 0, $multiple ? 10 : 0, $multiple);
}
// #############################################################################
/**
* Prints a row containing a <select> list of channels, complete with displayorder, parenting and depth information
*
* @param	string	text for the left cell of the table row
* @param	string	name of the <select>
* @param	mixed	selected <option>
* @param	string	name given to the -1 <option>
* @param	boolean	display the -1 <option> or not.
* @param	boolean	when true, allows multiple selections to be made. results will be stored in $name's array
* @param	string	Text to be used in sprintf() to indicate a 'category' channel, eg: '%s (Category)'. Leave blank for no category indicator
*/
function print_channel_chooser($title, $name, $selectedid = -1, $topname = null, $displayselectchannel = false, $multiple = false, $category_phrase = null, $skip_root = false)
{
	if ($displayselectchannel AND $selectedid <= 0)
	{
		$selectedid = 0;
	}

	$channels = vB_Api::instanceInternal('search')->getChannels();

	if ($skip_root)
	{
		$channels = current($channels);
		$channels = $channels['channels'];
	}

	print_select_row($title, $name, construct_channel_chooser_options($channels, $displayselectchannel, $topname, $category_phrase), $selectedid, 0, $multiple ? 10 : 0, $multiple);
}

// #############################################################################
/**
* Returns a list of <option> tags representing the list of channels
*
* @param	integer	Selected channel ID
* @param	boolean	Whether or not to display the 'Select Channel' option
* @param	string	If specified, name for the optional top element - no name, no display
* @param	string	Text to be used in sprintf() to indicate a 'category' channel, eg: '%s (Category)'. Leave blank for no category indicator
*
* @return	string	List of <option> tags
*/
function construct_channel_chooser($selectedid = -1, $displayselectchannel = false, $topname = null, $category_phrase = null)
{
	$channels = vB_Api::instanceInternal('search')->getChannels();
	return construct_select_options(construct_channel_chooser_options($channels, $displayselectchannel, $topname, $category_phrase), $selectedid);
}


// #############################################################################
/**
* Returns a list of <option> tags representing the list of forums
*
* @param	integer	Selected forum ID
* @param	boolean	Whether or not to display the 'Select Forum' option
* @param	string	If specified, name for the optional top element - no name, no display
* @param	string	Text to be used in sprintf() to indicate a 'category' forum, eg: '%s (Category)'. Leave blank for no category indicator
*
* @return	string	List of <option> tags
*/
function construct_forum_chooser($selectedid = -1, $displayselectforum = false, $topname = null, $category_phrase = null)
{
	return construct_select_options(construct_forum_chooser_options($displayselectforum, $topname, $category_phrase), $selectedid);
}

// #############################################################################
/**
* Returns a list of <option> tags representing the list of forums
*
* @param	boolean	Whether or not to display the 'Select Forum' option
* @param	string	If specified, name for the optional top element - no name, no display
* @param	string	Text to be used in sprintf() to indicate a 'category' forum, eg: '%s (Category)'. Leave blank for no category indicator
*
* @return	string	List of <option> tags
*/
function construct_forum_chooser_options($displayselectforum = false, $topname = null, $category_phrase = null)
{
	static $vbphrase;

	if (empty($vbphrase))
	{
		$vbphrase = vB_Api::instanceInternal('phrase')->fetch(array('select_forum', 'forum_is_closed_for_posting'));
	}
	$channels = vB_Api::instanceInternal('search')->getChannels(true);
	unset($channels[1]); // Unset Home channel

	$selectoptions = array();

	if ($displayselectforum)
	{
		$selectoptions[0] = $vbphrase['select_forum'];
	}

	if ($topname)
	{
		$selectoptions['-1'] = $topname;
		$startdepth = '--';
	}
	else
	{
		$startdepth = '';
	}

	if (!$category_phrase)
	{
		$category_phrase = '%s';
	}

	foreach ($channels AS $nodeid => $channel)
	{
		$channel['title'] = vB_String::htmlSpecialCharsUni(sprintf($category_phrase, $channel['title']));

		$selectoptions["$nodeid"] = construct_depth_mark($channel['depth'] - 1, '--', $startdepth) . ' ' . $channel['title'];
	}

	return $selectoptions;
}
// #############################################################################
/**
* Returns a list of <option> tags representing the list of channels
*
* @param	array	List of Channels to display
* @param	boolean	Whether or not to display the 'Select Channel' option
* @param	string	If specified, name for the optional top element - no name, no display
* @param	string	Text to be used in sprintf() to indicate a 'category' forum, eg: '%s (Category)'. Leave blank for no category indicator
*
* @return	string	List of <option> tags
*/
function construct_channel_chooser_options($channels, $displayselectchannel = false, $topname = null, $category_phrase = null)
{
	global $vbulletin, $vbphrase;

	$selectoptions = array();

	if ($displayselectchannel)
	{
		$selectoptions[0] = $vbphrase['select_channel'];
	}

	if ($topname)
	{
		$selectoptions['-1'] = $topname;
		$startdepth = '--';
	}
	else
	{
		$startdepth = '';
	}

	if (!$category_phrase)
	{
		$category_phrase = '%s';
	}

	foreach ($channels AS $nodeid => $channel)
	{
		if (!($channel['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads']))
		{
			$channel['htmltitle'] = sprintf($category_phrase, $channel['htmltitle']);
		}

		$selectoptions["$nodeid"] = $startdepth . str_repeat('--', $channel['depth']) . ' ' . vB_String::htmlSpecialCharsUni($channel['htmltitle']);
		if (!empty($channel['channels']))
		{
			$selectoptions += construct_channel_chooser_options($channel['channels'], $displayselectchannel, $topname, $category_phrase);
		}
	}

	return $selectoptions;
}
// #############################################################################
/**
* Returns a 'depth mark' for use in prefixing items that need to show depth in a hierarchy
*
* @param	integer	Depth of item (0 = no depth, 3 = third level depth)
* @param	string	Character or string to repeat $depth times to build the depth mark
* @param	string	Existing depth mark to append to
*
* @return	string
*/
function construct_depth_mark($depth, $depthchar, $depthmark = '')
{
	return $depthmark . str_repeat($depthchar, $depth);
/*	for ($i = 0; $i < $depth; $i++)
	{
		$depthmark .= $depthchar;
	}
	return $depthmark;*/
}

// #############################################################################
/**
* Essentially just a wrapper for construct_help_button()
*
* @param	string	Option name
* @param	string	Action / Do name
* @param	string	Script name
* @param	integer	Help type
*
* @return	string
*/
function construct_table_help_button($option = '', $action = NULL, $script = '', $helptype = 0)
{
	if ($helplink = construct_help_button($option, $action, $script, $helptype))
	{
		return "$helplink ";
	}
	else
	{
		return '';
	}
}

// #############################################################################
/**
* Returns a help-link button for the specified script/action/option if available
*
* @param	string	Option name
* @param	string	Action / Do name (script.php?do=SOMETHING)
* @param	string	Script name (SCRIPT.php?do=something)
* @param	integer	Help type
*
* @return	string
*/
function construct_help_button($option = '', $action = NULL, $script = '', $helptype = 0)
{
	// used to make a link to the help section of the CP related to the current action
	global $helpcache, $vbphrase, $vbulletin, $vb5_config;

	if ($action === NULL)
	{
		// matches type as well (===)
		$action = $_REQUEST['do'];
	}

	if (empty($script))
	{
		$script = $vbulletin->scriptpath;
	}

	if ($strpos = strpos($script, '?'))
	{
		$script = basename(substr($script, 0, $strpos));
	}
	else
	{
		$script = basename($script);
	}

	if ($strpos = strpos($script, '.'))
	{
		$script = substr($script, 0, $strpos); // remove the .php part as people may have different extensions
	}

	if ($option AND !isset($helpcache["$script"]["$action"]["$option"]))
	{
		if (preg_match('#^[a-z0-9_]+(\[([a-z0-9_]+)\])+$#si', trim($option), $matches))
		{
			// parse out array notation, to just get index
			$option = $matches[2];
		}

		$option = str_replace('[]', '', $option);
	}

	if (!$option)
	{
		if (!isset($helpcache["$script"]["$action"]))
		{
			return '';
		}
	}
	else
	{
		if (!isset($helpcache["$script"]["$action"]["$option"]))
		{
			if ($vb5_config['Misc']['debug'] AND defined('DEV_EXTRA_CONTROLS') AND DEV_EXTRA_CONTROLS)
			{
				return construct_link_code('AddHelp', "help.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;option=" . urlencode($option) . '&amp;script=' . urlencode($script) . '&amp;scriptaction=' . urlencode($action));
			}
			else
			{
				return '';
			}
		}
	}

	$options = vB::getDatastore()->getValue('options');
	$filebase = $options['bburl'];

	$helplink = "js_open_help('" . urlencode($script) . "', '" . urlencode($action) . "', '" . urlencode($option) . "'); return false;";
	if ($option)
	{
		$id = $script . '_' . $action . '_' . $option;
	}

	switch ($helptype)
	{
		case 1:
		return "<a id=\"$id\" class=\"helplink\" href=\"#\" onclick=\"$helplink\">$vbphrase[help] <img src=\"$filebase/cpstyles/" . $vbulletin->options['cpstylefolder'] . "/cp_help." . $vbulletin->options['cpstyleimageext'] . "\" alt=\"\" border=\"0\" title=\"$vbphrase[click_for_help_on_these_options]\" style=\"vertical-align:middle\" /></a>";

		default:
		return "<a id=\"$id\" class=\"helplink\" href=\"#\" onclick=\"$helplink\"><img src=\"$filebase/cpstyles/" . $vbulletin->options['cpstylefolder'] . "/cp_help." . $vbulletin->options['cpstyleimageext'] . "\" alt=\"\" border=\"0\" title=\"$vbphrase[click_for_help_on_this_option]\" /></a>";
	}
}

// #############################################################################
/**
* Returns a hyperlink
*
* @param	string	Hyperlink text
* @param	string	Hyperlink URL
* @param	boolean	If true, hyperlink target="_blank"
* @param	string	If specified, parameter will be used as title="x" tooltip for link
*
* @param	string
*/
function construct_link_code($text, $url, $newwin = false, $tooltip = '', $smallfont = false)
{
	if ($newwin === true OR $newwin === 1)
	{
		$newwin = '_blank';
	}

	return ($smallfont ? '<span class="smallfont">' : '') . " <a href=\"$url\"" . ($newwin ? " target=\"$newwin\"" : '') . (!empty($tooltip) ? " title=\"$tooltip\"" : '') . '>' . (vB_Template_Runtime::fetchStyleVar('textdirection') == 'rtl' ? "[$text&lrm;]</a>&rlm; " : "[$text]</a> ") . ($smallfont ? '</span>' : '');
}

// #############################################################################
/**
* Returns an <input type="button" /> that acts like a hyperlink
*
* @param	string	Value for button
* @param	string	Hyperlink URL; special cases 'submit' and 'reset'
* @param	boolean	If true, hyperlink will open in a new window
* @param	string	If specified, parameter will be used as title="x" tooltip for button
* @param	boolean	If true, the hyperlink URL parameter will be treated as a javascript function call instead
*
* @return	string
*/
function construct_button_code($text = 'Click!', $link = '', $newwindow = false, $tooltip = '', $jsfunction = 0)
{
	if (preg_match('#^(submit|reset),?(\w+)?$#siU', $link, $matches))
	{
		$name_attribute = ($matches[2] ? " name=\"$matches[2]\"" : '');
		return " <input type=\"$matches[1]\"$name_attribute class=\"button\" value=\"$text\" title=\"$tooltip\" tabindex=\"1\" />";
	}
	else
	{
		return " <input type=\"button\" class=\"button\" value=\"$text\" title=\"$tooltip\" tabindex=\"1\" onclick=\"" . iif($jsfunction, $link, iif($newwindow, "window.open('$link')", "window.location='$link'")) . ";\"$tooltip/> ";
	}
}

/**
* Checks whether or not the visiting user has administrative permissions
*
* This function can optionally take any number of parameters, each of which
* should be a particular administrative permission you want to check. For example:
* can_administer('canadminsettings', 'canadminstyles', 'canadminlanguages')
* If any one of these permissions is met, the function will return true.
*
* If no parameters are specified, the function will simply check that the user is an administrator.
*
* @return	boolean
*/
function can_administer()
{
	global $vbulletin, $_NAVPREFS;

	static $admin, $superadmins;

	$vb5_config =& vB::getConfig();

	if (!isset($_NAVPREFS))
	{
		$_NAVPREFS = preg_split('#,#', $vbulletin->userinfo['navprefs'], -1, PREG_SPLIT_NO_EMPTY);
	}

	if (!is_array($superadmins))
	{
		$superadmins = preg_split('#\s*,\s*#s', $vb5_config['SpecialUsers']['superadmins'], -1, PREG_SPLIT_NO_EMPTY);
	}

	$do = func_get_args();
	$userContext = vB::getUserContext();

	if ($vbulletin->userinfo['userid'] < 1)
	{
		// user is a guest - definitely not an administrator
		return false;
	}
	else if (!$userContext->isAdministrator())
	{
		// user is not an administrator at all
		return false;
	}
	else if ($userContext->isSuperAdmin())
	{
		// user is a super administrator (defined in config.php) so can do anything
		return true;
	}
	else if (empty($do))
	{
		// user is an administrator and we are not checking a specific permission
		return true;
	}
	else if (!isset($admin))
	{
		// query specific admin permissions from the administrator table and assign them to $adminperms
		$getperms = $vbulletin->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "administrator
			WHERE userid = " . $vbulletin->userinfo['userid']
		);

		$admin = $getperms;

		// add normal adminpermissions and specific adminpermissions
		$adminperms = $getperms['adminpermissions'] + $vbulletin->userinfo['permissions']['adminpermissions'];

		// save nav prefs choices
		$_NAVPREFS = preg_split('#,#', $getperms['navprefs'], -1, PREG_SPLIT_NO_EMPTY);
	}

	// final bitfield check on each permission we are checking
	foreach($do AS $field)
	{
		if (!$userContext->hasAdminPermission($field))
		{
			return false;
		}
	}

	// Legacy Hook 'can_administer' Removed //

	// if we got this far then there is no permission, unless the hook says so
	return true;
}

// #############################################################################
/**
* Halts execution and prints an error message stating that the administrator does not have permission to perform this action
*
* @param	string	This parameter is no longer used
*/
function print_cp_no_permission($do = '')
{
	global $vbulletin, $vbphrase;

	if (!defined('DONE_CPHEADER'))
	{
		print_cp_header($vbphrase['vbulletin_message']);
	}

	print_stop_message('no_access_to_admin_control', vB::getCurrentSession()->get('sessionurl'), $vbulletin->userinfo['userid']);

}

// #############################################################################
/**
* Saves data into the adminutil table in the database
*
* @param	string	Name of adminutil record to be saved
* @param	string	Data to be saved into the adminutil table
*
* @return	boolean
*/
function build_adminutil_text($title, $text = '')
{
	global $vbulletin;

	if ($text == '')
	{
		$vbulletin->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "adminutil
			WHERE title = '" . $vbulletin->db->escape_string($title) . "'
		");
	}
	else
	{
		/*insert query*/
		$vbulletin->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "adminutil
			(title, text)
			VALUES
			('" . $vbulletin->db->escape_string($title) . "', '" . $vbulletin->db->escape_string($text) . "')
		");
	}

	return true;
}

// #############################################################################
/**
* Returns data from the adminutil table in the database
*
* @param	string	Name of the adminutil record to be fetched
*
* @return	string
*/
function fetch_adminutil_text($title)
{
	$text = vB::getDbAssertor()->getRow('adminutil', array('title' => $title));
	return $text['text'];
}

// #############################################################################
/**
* Halts execution and prints a Javascript redirect function to cause the browser to redirect to the specified page
*
* @param	string	Redirect target URL
* @param	float	Time delay (in seconds) before the redirect will occur
*/
function print_cp_redirect($gotopage, $timeout = 0)
{
	// performs a delayed javascript page redirection
	// get rid of &amp; if there are any...
	global $vbphrase;
	$gotopage = str_replace('&amp;', '&', $gotopage);
	if (!empty($gotopage) && ((($hashpos = strpos($gotopage, '#')) !== false) OR (($hashpos = strpos($gotopage, '%23')) !== false)))
	{
		$hashsize = (strpos($gotopage, '#') !== false) ? 1 : 3;
		$hash = substr($gotopage, $hashpos + $hashsize);
		$gotopage = substr($gotopage, 0, $hashpos);
	}

	$gotopage = create_full_url($gotopage);
	$gotopage = str_replace('"', '', $gotopage);
	if (!empty($hash))
	{
		$gotopage .= '#'.$hash;
	}

	if ($timeout == 0)
	{
		echo '<p align="center" class="smallfont"><a href="' . $gotopage . '">' . $vbphrase['processing_complete_proceed'] . '</a></p>';
		echo "\n<script type=\"text/javascript\">\n";
		echo "window.location=\"$gotopage\";";
		echo "\n</script>\n";
	}
	else
	{
		echo "\n<script type=\"text/javascript\">\n";
		echo "myvar = \"\"; timeout = " . ($timeout*10) . ";
		function exec_refresh()
		{
			window.status=\"" . $vbphrase['redirecting']."\"+myvar; myvar = myvar + \" .\";
			timerID = setTimeout(\"exec_refresh();\", 100);
			if (timeout > 0)
			{ timeout -= 1; }
			else { clearTimeout(timerID); window.status=\"\"; window.location=\"$gotopage\"; }
		}
		exec_refresh();";
		echo "\n</script>\n";
		echo '<p align="center" class="smallfont"><a href="' . $gotopage . '" onclick="javascript:clearTimeout(timerID);">' . $vbphrase['processing_complete_proceed'] . '</a></p>';
	}
	print_cp_footer();
	exit;
}

function print_cp_redirect2($file, $extra = array(), $timeout = 0)
{
	return print_cp_redirect(get_redirect_url($file, $extra, 'admincp'), $timeout);
}

function print_cp_redirect_with_session($file, $extra = array(), $timeout = 0)
{
	$args = array();
	parse_str(vB::getCurrentSession()->get('sessionurl'),$args);
	$extra = array_merge($args, $extra);
	return print_cp_redirect(get_redirect_url($file, $extra, 'admincp'), $timeout);
}

// #############################################################################
/**
* Prints a block of HTML containing a character that multiplies in width via javascript - a kind of progress meter
*
* @param	string	Text to be printed above the progress meter
* @param	string	Character to be used as the progress meter
* @param	string	Name to be given as the id for the HTML element containing the progress meter
*/
function print_dots_start($text, $dotschar = ':', $elementid = 'dotsarea')
{
	if (defined('NO_IMPORT_DOTS'))
	{
		return;
	}

	vbflush(); ?>
	<p align="center"><?php echo $text; ?><br /><br />[<span class="progress_dots" id="<?php echo $elementid; ?>"><?php echo $dotschar; ?></span>]</p>
	<script type="text/javascript"><!--
	function js_dots()
	{
		<?php echo $elementid; ?>.innerText = <?php echo $elementid; ?>.innerText + "<?php echo $dotschar; ?>";
		jstimer = setTimeout("js_dots();", 75);
	}
	if (document.all)
	{
		js_dots();
	}
	//--></script>
	<?php vbflush();
}

// #############################################################################
/**
* Prints a javascript code block that will halt the progress meter started with print_dots_start()
*/
function print_dots_stop()
{
	if (defined('NO_IMPORT_DOTS'))
	{
		return;
	}

	vbflush(); ?>
	<script type="text/javascript"><!--
	if (document.all)
	{
		clearTimeout(jstimer);
	}
	//--></script>
	<?php vbflush();
}

// #############################################################################
/**
* Deletes all private messages belonging to the specified user
*
* @param	integer	User ID
* @param	boolean	If true, update the user record in the database to reflect their new number of private messages
*
* @return	mixed	If messages are deleted, will return a string to be printed out detailing work done by this function
*/
	/*
function delete_user_pms($userid, $updateuser = true)
{
	global $vbulletin, $vbphrase;

	$userid = intval($userid);

	// array to store pm ids message ids
	$pms = array();
	// array to store the number of pmtext records used by this user
	$pmTextCount = array();
	// array to store the ids of any pmtext records that are used soley by this user
	$deleteTextIDs = array();
	// array to store results
	$out = array();

	// first zap all receipts belonging to this user
	$out['receipts'] = vB::getDbAssertor()->delete('pmreceipt', array('userid' => $userid));

	$messages = vB::getDbAssertor()->getRows('pm', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'userid' => $userid));
	foreach ($messages as $message)
	{
		// stick this record into our $pms array
		$pms["$message[pmid]"] = $message['pmtextid'];
		// increment the number of PMs that use the current PMtext record
		$pmTextCount["$message[pmtextid]"] ++;
	}

	if (!empty($pms))
	{
		// zap all pm records belonging to this user
		$out['pms'] = vB::getDbAssertor()->delete('pm', array('userid' => $userid));
		$out['pmtexts'] = 0;

		// update the user record if necessary
		if ($updateuser AND $user = fetch_userinfo($userid))
		{
			$updateduser = true;
			$userdm = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
			$userdm->set_existing($user);
			$userdm->set('pmtotal', 0);
			$userdm->set('pmunread', 0);
			$userdm->set('pmpopup', 'IF(pmpopup=2, 1, pmpopup)', false);
			$userdm->save();
			unset($userdm);
		}
	}
	else
	{
		$out['pms'] = 0;
		$out['pmtexts'] = 0;
	}

	// in case the totals have been corrupted somehow
	if (!isset($updateduser) AND $updateuser AND $user = fetch_userinfo($userid))
	{
		$userdm = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
		$userdm->set_existing($user);
		$userdm->set('pmtotal', 0);
		$userdm->set('pmunread', 0);
		$userdm->set('pmpopup', 'IF(pmpopup=2, 1, pmpopup)', false);
		$userdm->save();
		unset($userdm);
	}

	foreach ($out AS $k => $v)
	{
		$out["$k"] = vb_number_format($v);
	}

	return $out;
}
	 */

// #############################################################################
/**
* Writes data to a file
*
* @param	string	Path to file (including file name)
* @param	string	Data to be saved into the file
* @param	boolean	If true, will create a backup of the file called {filename}old
*/
function file_write($path, $data, $backup = false)
{
	if (file_exists($path) != false)
	{
		if ($backup)
		{
			$filenamenew = $path . 'old';
			rename($path, $filenamenew);
		}
		else
		{
			unlink($path);
		}
	}
	if ($data != '')
	{
		$filenum = fopen($path, 'w');
		fwrite($filenum, $data);
		fclose($filenum);
	}
}

// #############################################################################
/**
* Returns the contents of a file
*
* @param	string	Path to file (including file name)
*
* @return	string	If file does not exist, returns an empty string
*/
function file_read($path)
{
	// On some versions of PHP under IIS, file_exists returns false for uploaded files,
	// even though the file exists and is readable. http://bugs.php.net/bug.php?id=38308
	if(!file_exists($path) AND !is_uploaded_file($path))
	{
		return '';
	}
	else
	{
		$filestuff = @file_get_contents($path);
		return $filestuff;
	}
}

// #############################################################################
/**
 * @deprecated
* Reads settings from the settings then saves the values to the datastore
*
* After reading the contents of the setting table, the function will rebuild
* the $vbulletin->options array, then serialize the array and save that serialized
* array into the 'options' entry of the datastore in the database
*
* @return	array	The $vbulletin->options array
*/
function build_options()
{
	return vB::getDatastore()->build_options();
}

// #############################################################################
/**
* Saves a log into the adminlog table in the database
*
* @param	string	Extra info to be saved
* @param	integer	User ID of the visiting user
* @param	string	Name of the script this log applies to
* @param	string	Action / Do branch being viewed
*/
function log_admin_action($extrainfo = '', $userid = -1, $script = '', $scriptaction = '')
{
	// logs current activity to the adminlog db table

	if ($userid == -1)
	{
		$userInfo = vB::getCurrentSession()->fetch_userinfo();
		$userid = $userInfo['userid'];
	}
	if (empty($script))
	{
		$script = !empty($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : basename($_SERVER['PHP_SELF']);
	}
	if (empty($scriptaction))
	{
		$scriptaction = $_REQUEST['do'];
	}

	vB::getDbAssertor()->assertQuery('vBForum:adminlog',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
					'userid' => $userid,
					'dateline' => TIMENOW,
					'script' => $script,
					'action' => $scriptaction,
					'extrainfo' => $extrainfo,
					'ipaddress' => IPADDRESS,
			)
	);
}

// #############################################################################
/**
* Checks whether or not the visiting user can view logs
*
* @param	string	Comma-separated list of user IDs permitted to view logs
* @param	boolean	Variable to return if the previous parameter is found to be empty
* @param	string	Message to print if the user is NOT permitted to view
*
* @return	boolean
*/
function can_access_logs($idvar, $defaultreturnvar = false, $errmsg = '')
{
	if (empty($idvar))
	{
		return $defaultreturnvar;
	}
	else
	{
		$perm = trim($idvar);
		$logperms = explode(',', $perm);
		$userinfo = vB::getCurrentSession()->fetch_userinfo();
		if (in_array($userinfo['userid'], $logperms))
		{
			return true;
		}
		else
		{
			echo $errmsg;
			return false;
		}
	}
}

// #############################################################################
/**
* Prints a dialog box asking if the user is sure they want to delete the specified item from the database
*
* @param	string	Name of table from which item will be deleted
* @param	mixed		ID of item to be deleted
* @param	string	PHP script to which the form will submit
* @param	string	'do' action for target script
* @param	string	Word describing item to be deleted - eg: 'forum' or 'user' or 'post' etc.
* @param	mixed		If not empty, an array containing name=>value pairs to be used as hidden input fields
* @param	string	Extra text to be printed in the dialog box
* @param	string	Name of 'title' field in the table in the database
* @param	string	Name of 'idfield' field in the table in the database
*/
function print_delete_confirmation($table, $itemid, $phpscript, $do, $itemname = '', $hiddenfields = 0, $extra = '', $titlename = 'title', $idfield = '')
{
	global $vbphrase;

	$idfield = $idfield ? $idfield : $table . 'id';
	$itemname = $itemname ? $itemname : $table;
	$deleteword = 'delete';
	$encodehtml = true;
	$assertor = vB::getDbAssertor();

	switch($table)
	{
		case 'infraction':
			$item = $assertor->getRow('infraction', array('infractionid' => $itemid));
			$item['title'] = (!empty($item) AND isset($item['infractionid'])) ? $item['infractionid'] : '';
			break;
		case 'reputation':
			$item = $assertor->getRow('vBForum:reputation', array('reputationid' => $itemid));
			$item['title'] = (!empty($item) AND isset($item['reputationid'])) ? $item['reputationid'] : '';
			break;
		case 'user':
			$item = $assertor->getRow('user', array('userid' => $itemid));
			$item['title'] = (!empty($item) AND isset($item['username'])) ? $item['username'] : '';
			break;
		case 'moderator':
			$item = $assertor->getRow('vBForum:getModeratorBasicFields', array('moderatorid' => $itemid));
			$item['title'] = construct_phrase($vbphrase['x_from_the_forum_y'], $item['username'], $item['title']);
			$encodehtml = false;
			break;
		case 'calendarmoderator':
			$item = $assertor->getRow('vBForum:getCalendarModeratorBasicFields', array('calendarmoderatorid' => $itemid));
			$item['title'] = construct_phrase($vbphrase['x_from_the_calendar_y'], $item['username'], $item['title']);
			$encodehtml = false;
			break;
		case 'phrase':
			$item = $assertor->getRow('vBForum:phrase', array('phraseid' => $itemid));
			$item['title'] = (!empty($item) AND isset($item['varname'])) ? $item['varname'] : '';
			break;
		case 'userpromotion':
			$item = $assertor->getRow('vBForum:getUserPromotionBasicFields', array('userpromotionid' => $itemid));
			break;
		case 'usergroupleader':
			$item = $assertor->getRow('vBForum:getUserGroupLeaderBasicFields', array('usergroupleaderid' => $itemid));
			break;
		case 'setting':
			$item = $assertor->getRow('setting', array('varname' => $itemid));
			$item['title'] = (!empty($item) AND isset($item['varname'])) ? $item['varname'] : '';
			$idfield = 'title';
			break;
		case 'settinggroup':
			$item = $assertor->getRow('settinggroup', array('grouptitle' => $itemid));
			$item['title'] = (!empty($item) AND isset($item['grouptitle'])) ? $item['grouptitle'] : '';
			$idfield = 'title';
			break;
		case 'adminhelp':
			$item = $assertor->getRow('vBForum:getAdminHelpBasicFields', array('adminhelpid' => $itemid));
			break;
		case 'faq':
			$item = $assertor->getRow('vBForum:getFaqBasicFields', array('faqname' => $itemid));
			$idfield = 'faqname';
			break;
		case 'hook':
			$item = $assertor->getRow('hook', array('hookid' => $itemid));
			$item['title'] = (!empty($item) AND isset($item['title'])) ? $item['title'] : '';
			break;
		case 'product':
			$item = $assertor->getRow('product', array('productid' => $itemid));
			$item['title'] = (!empty($item) AND isset($item['title'])) ? $item['title'] : '';
			break;
		case 'prefix':
			$item = $assertor->getRow('vBForum:prefix', array('prefixid' => $itemid));
			$item['title'] = (!empty($item['prefixid'])) ? $vbphrase["prefix_$item[prefixid]_title_plain"] : '';
			break;
		case 'prefixset':
			$item = $assertor->getRow('vBForum:prefixset', array('prefixsetid' => $itemid));
			$item['title'] = (!empty($item['prefixsetid'])) ? $vbphrase["prefixset_$item[prefixsetid]_title"] : '';
			break;
		case 'stylevar':
			$item = $assertor->getRow('vBForum:stylevar', array('stylevarid' => $itemid));
			break;
		case 'announcement':
			$item = $assertor->getRow('vBForum:announcement', array('announcementid' => $itemid));
			$item['title'] = (!empty($item) AND isset($item['title'])) ? $item['title'] : '';
			break;
		default:
			$handled = false;
			// Legacy Hook 'admin_delete_confirmation' Removed //
			if (!$handled)
			{
				$item = $assertor->getRow($table, array($idfield => $itemid));
				$item['title'] = (!empty($item) AND isset($item[$titlename])) ? $item[$titlename] : '';
			}
			break;
	}

	switch($table)
	{
		case 'template':
			if ($itemname == 'replacement_variable')
			{
				$deleteword = 'delete';
			}
			else
			{
				$deleteword = 'revert';
			}
		break;

		case 'adminreminder':
			if (vbstrlen($item['title']) > 30)
			{
				$item['title'] = substr($item['title'], 0, 30) . '...';
			}
		break;

		case 'subscription':
			$item['title'] = (!empty($item['subscriptionid'])) ? $vbphrase['sub' . $item['subscriptionid'] . '_title'] : '';
		break;

		case 'stylevar':
			$item['title'] = (!empty($item['stylevarid'])) ? $vbphrase['stylevar' . $item['stylevarid'] . $titlename . '_name'] : '';

			//Friendly names not
			if (!$item['title'])
			{
				$item['title'] = $item["$idfield"];
			}

			$deleteword = 'revert';
		break;
	}

	if ($encodehtml
		AND (strcspn($item['title'], '<>"') < strlen($item['title'])
			OR (strpos($item['title'], '&') !== false AND !preg_match('/&(#[0-9]+|amp|lt|gt|quot);/si', $item['title']))
		)
	)
	{
		// title contains html entities that should be encoded
		$item['title'] = htmlspecialchars_uni($item['title']);
	}

	if ($item["$idfield"] == $itemid AND !empty($itemid))
	{
		echo "<p>&nbsp;</p><p>&nbsp;</p>";
		print_form_header($phpscript, $do, 0, 1, '', '75%');
		construct_hidden_code(($idfield == 'styleid' OR $idfield == 'languageid') ? 'do' . $idfield : $idfield, $itemid);
		if (is_array($hiddenfields))
		{
			foreach($hiddenfields AS $varname => $value)
			{
				construct_hidden_code($varname, $value);
			}
		}

		print_table_header(construct_phrase($vbphrase['confirm_deletion_x'], $item['title']));
		print_description_row("
			<blockquote><br />
			" . construct_phrase($vbphrase["are_you_sure_want_to_{$deleteword}_{$itemname}_x"], $item['title'],
				$idfield, $item["$idfield"], iif($extra, "$extra<br /><br />")) . "
			<br /></blockquote>\n\t");
		print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);
	}
	else
	{
		print_stop_message('could_not_find', '<b>' . $itemname . '</b>', $idfield, $itemid);
	}
}

// #############################################################################
/**
* Prints a dialog box asking if the user if they want to continue
*
* @param	string	Phrase that is presented to the user
* @param	string	PHP script to which the form will submit
* @param	string	'do' action for target script
* @param	mixed		If not empty, an array containing name=>value pairs to be used as hidden input fields
*/
function print_confirmation($phrase, $phpscript, $do, $hiddenfields = array())
{
	global $vbulletin, $vbphrase;

	echo "<p>&nbsp;</p><p>&nbsp;</p>";
	print_form_header($phpscript, $do, 0, 1, '', '75%');
	if (is_array($hiddenfields))
	{
		foreach($hiddenfields AS $varname => $value)
		{
			construct_hidden_code($varname, $value);
		}
	}
	print_table_header($vbphrase['confirm_action']);
	print_description_row("
		<blockquote><br />
		$phrase
		<br /></blockquote>\n\t");
	print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);

}

// #############################################################################
/**
* Halts execution and shows a message based upon a parsed phrase
*
* After the first parameter, this function can take any number of additional
* parameters, in order to replace {1}, {2}, {3},... {n} variable place holders
* within the given phrase text. The parsed phrase is then passed to print_cp_message()
*
* Note that a redirect can be performed if CP_REDIRECT is defined with a URL
*
* @deprecated
* @param	string	Name of phrase (from the Error phrase group)
* @param	string	1st variable replacement {1}
* @param	string	2nd variable replacement {2}
* @param	string	Nth variable replacement {n}
*/
function print_stop_message($phrasename)
{
	global $vbulletin, $vbphrase;

	$phraseAux = vB_Api::instanceInternal('phrase')->fetch(array($phrasename));
	$message = $phraseAux[$phrasename];

	$args = func_get_args();
	if (sizeof($args) > 1)
	{
		$args[0] = $message;
		$message = call_user_func_array('construct_phrase', $args);
	}

	if (defined('CP_CONTINUE'))
	{
		define('CP_REDIRECT', CP_CONTINUE);
	}

	if ($vbulletin->GPC['ajax'])
	{
		require_once(DIR . '/includes/class_xml.php');
		$xml = new vB_XML_Builder_Ajax('text/xml');
		$xml->add_tag('error', $message);
		$xml->print_xml();
	}

	if (VB_AREA == 'Upgrade')
	{
		echo $message;
		exit;
	}

	print_cp_message(
		$message,
		defined('CP_REDIRECT') ? CP_REDIRECT : NULL,
		1,
		defined('CP_BACKURL') ? CP_BACKURL : NULL,
		defined('CP_CONTINUE') ? true : false
	);
}

/**
 * Turn the filename, extra params into a url -- this should only be called by
 * functions in the adminfunction.php file.
 *
 * @private
 */
function get_redirect_url($file, $extra, $route)
{
	$file = preg_replace('#\.php$#si', '', $file);
	$vb5_options = vB::getDatastore()->getValue('options');
	if (strpos(VB_URL, $vb5_options['bburl']) !== false)
	{
		$redirect = $file . '.php?' . http_build_query($extra);
	}
	else
	{
		$redirect = vB5_Route::buildUrl($route . '|fullurl', array('file' => $file), $extra);
	}
	return $redirect;
}

function print_modcp_stop_message2($phrase, $file = NULL, $extra = array(), $backurl = NULL, $continue = false)
{
	return print_stop_message2($phrase, $file, $extra, $backurl, $continue, 'modcp');
}

function print_stop_message2($phrase, $file = NULL, $extra = array(), $backurl = NULL, $continue = false, $redirect_route = 'admincp')
{
	//handle phrase as a string
	if (!is_array($phrase))
	{
		$phrase = array($phrase);
	}

	$phraseAux = vB_Api::instanceInternal('phrase')->fetch(array($phrase[0]));
	if (isset($phraseAux[$phrase[0]]))
	{
		$message = $phraseAux[$phrase[0]];
	}
	else
	{
		$message = $phrase[0]; // phrase doesn't exist or wasn't found, display the varname
	}
	if (sizeof($phrase) > 1)
	{
		$phrase[0] = $message;
		$message = call_user_func_array('construct_phrase', $phrase);
	}

	//todo -- figure out where this is needed and remove.
	global $vbulletin;
	if ($vbulletin->GPC['ajax'])
	{
		require_once(DIR . '/includes/class_xml.php');
		$xml = new vB_XML_Builder_Ajax('text/xml');
		$xml->add_tag('error', $message);
		$xml->print_xml();
	}

	//todo -- figure out where this is needed and remove.
	if (VB_AREA == 'Upgrade')
	{
		echo $message;
		exit;
	}

	$hash = '';
	if ($file)
	{
		if (!empty($extra['#']))
		{
			$hash = '#' . $extra['#'];
			unset($extra['#']);
		}
		$redirect = get_redirect_url($file, $extra, $redirect_route);
	}

	print_cp_message(
		$message,
		$redirect . $hash,
		1,
		$backurl,
		$continue
	);
}

// #############################################################################
/**
* Halts execution and shows the specified message
*
* @param	string	Message to display
* @param	mixed	If specified, a redirect will be performed to the URL in this parameter
* @param	integer	If redirect is specified, this is the time in seconds to delay before redirect
* @param	string	If specified, will provide a specific URL for "Go Back". If empty, no button will be displayed!
* @param bool		If true along with redirect, 'CONTINUE' button will be used instead of automatic redirect
*/
function print_cp_message($text = '', $redirect = NULL, $delay = 1, $backurl = NULL, $continue = false)
{
	global $vbulletin, $vbphrase;

	if ($vbulletin->GPC['ajax'])
	{
		require_once(DIR . '/includes/class_xml.php');
		$xml = new vB_XML_Builder_Ajax('text/xml');
		$xml->add_tag('error', $text);
		$xml->print_xml();
		exit;
	}

	if ($redirect)
	{
		if ((($hashpos = strpos($redirect, '#')) !== false) OR (($hashpos = strpos($redirect, '%23')) !== false))
		{
			$hashsize = (strpos($redirect, '#') !== false) ? 1 : 3;
			$hash = substr($redirect, $hashpos + $hashsize);
			$redirect = substr($redirect, 0, $hashpos);
		}

		if ($session = vB::getCurrentSession()->get('sessionurl'))
		{
			if (strpos($redirect, $session) !== false)
			{
				if (strpos($redirect, '?') === false)
				{
					$redirect .= '?' . $session;
				}
				else
				{
					$redirect .= '&' . $session;
				}
			}
		}
	}

	if (!defined('DONE_CPHEADER'))
	{
		print_cp_header($vbphrase['vbulletin_message']);
	}

	print_form_header('', '', 0, 1, 'messageform', '65%');
	print_table_header(new vB_Phrase('global', 'vbulletin_message'));
	print_description_row("<blockquote><br />$text<br /><br /></blockquote>");

	if ($redirect)
	{
		// redirect to the new page
		if ($continue)
		{
			$continueurl = create_full_url(str_replace('&amp;', '&', $redirect));
			if (!empty($hash))
			{
				$continueurl .= '#'.$hash;
			}

			print_table_footer(2, construct_button_code(new vB_Phrase('global', 'continue'), $continueurl));
		}
		else
		{
			print_table_footer();

			$redirect_click = create_full_url($redirect);
			if (!empty($hash))
			{
				$redirect_click .= '#'.$hash;
				$redirect .= '#'.$hash;
			}
			$redirect_click = str_replace('"', '', $redirect_click);

			echo '<p align="center" class="smallfont">' . construct_phrase($vbphrase['if_you_are_not_automatically_redirected_click_here_x'], $redirect_click) . "</p>\n";
			print_cp_redirect($redirect, $delay);
		}
	}
	else
	{
		// end the table and halt
		if ($backurl === NULL)
		{
			$backurl = 'javascript:history.back(1)';
		}

		if (strpos($backurl, 'history.back(') !== false)
		{
			//if we are attempting to run a history.back(1), check we have a history to go back to, otherwise attempt to close the window.
			$back_button = '&nbsp;
				<input type="button" id="backbutton" class="button" value="' . $vbphrase['go_back'] . '" title="" tabindex="1" onclick="if (history.length) { history.back(1); } else { self.close(); }"/>
				&nbsp;
				<script type="text/javascript">
				<!--
				if (history.length < 1 || ((is_saf || is_moz) && history.length <= 1)) // safari + gecko start at 1
				{
					document.getElementById("backbutton").parentNode.removeChild(document.getElementById("backbutton"));
				}
				//-->
				</script>';

			// remove the back button if it leads back to the login redirect page
			if (strpos($vbulletin->url, 'login.php?do=login') !== false)
			{
				$back_button = '';
			}
		}
		else if ($backurl !== '')
		{
			// regular window.location=url call
			$backurl = create_full_url($backurl);
			$backurl = str_replace(array('"', "'"), '', $backurl);
			$back_button = '<input type="button" class="button" value="' . (new vB_Phrase('global', 'go_back')) . '" title="" tabindex="1" onclick="window.location=\'' . $backurl . '\';"/>';
		}
		else
		{
			$back_button = '';
		}

		print_table_footer(2, $back_button);
	}

	// and now terminate the script
	print_cp_footer();
}

/**
* Verifies the CP sessionhash is sent through with the request to prevent
* an XSS-style issue.
*
* @param	boolean	Whether to halt if an error occurs
* @param	string	Name of the input variable to look at
*
* @return	boolean	True on success, false on failure
*/
function verify_cp_sessionhash($halt = true, $input = 'hash')
{
	global $vbulletin;

	assert_cp_sessionhash();

	if (!isset($vbulletin->GPC["$input"]))
	{
		$vbulletin->input->clean_array_gpc('r', array(
			$input => vB_Cleaner::TYPE_STR
		));
	}

	if ($vbulletin->GPC["$input"] != CP_SESSIONHASH)
	{
		if ($halt)
		{
			print_stop_message2('security_alert_hash_mismatch');
		}
		else
		{
			return false;
		}
	}

	return true;
}

/**
 * Defines a valid CP_SESSIONHASH.
 */
function assert_cp_sessionhash()
{
	if (defined('CP_SESSIONHASH'))
	{
		return;
	}

	global $vbulletin;
	$options = vB::getDatastore()->getValue('options');
	$userId = vB::getCurrentSession()->get('userid');
	$timeNow = vB::getRequest()->getTimeNow();
	$assertor = vB::getDbAssertor();

	$cpsession = array();

	$vbulletin->input->clean_array_gpc('c', array(
		COOKIE_PREFIX . 'cpsession' => vB_Cleaner::TYPE_STR,
	));

	if (!empty($vbulletin->GPC[COOKIE_PREFIX . 'cpsession']))
	{
		$cpsession = $assertor->getRow('cpsession', array(
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'userid', 'operator' => vB_dB_Query::OPERATOR_EQ, 'value' => $userId),
				array('field' => 'hash', 'operator' => vB_dB_Query::OPERATOR_EQ, 'value' => $vbulletin->GPC[COOKIE_PREFIX . 'cpsession']),
				array('field' => 'dateline', 'operator' => vB_dB_Query::OPERATOR_GT, 'value' => ($options['timeoutcontrolpanel'] ? intval($timeNow - $options['cookietimeout']) : intval($timeNow - 3600)))
			)
		));

		if (!empty($cpsession))
		{
			$assertor->assertQuery('cpSessionUpdate', array(
				'timenow' => $timeNow,
				'userid' => $userId,
				'hash' => $vbulletin->GPC[COOKIE_PREFIX . 'cpsession']
			));
		}
	}

	vB::getCurrentSession()->setCpsessionHash($cpsession['hash']);
	define('CP_SESSIONHASH', $cpsession['hash']);
}

// #############################################################################
/**
* Returns an array of timezones, keyed with their offset from GMT
*
* @return	array	Timezones array
*/
function fetch_timezones_array()
{
	global $vbphrase;

	return array(
		'-12'  => $vbphrase['timezone_gmt_minus_1200'],
		'-11'  => $vbphrase['timezone_gmt_minus_1100'],
		'-10'  => $vbphrase['timezone_gmt_minus_1000'],
		'-9'   => $vbphrase['timezone_gmt_minus_0900'],
		'-8'   => $vbphrase['timezone_gmt_minus_0800'],
		'-7'   => $vbphrase['timezone_gmt_minus_0700'],
		'-6'   => $vbphrase['timezone_gmt_minus_0600'],
		'-5'   => $vbphrase['timezone_gmt_minus_0500'],
		'-4.5' => $vbphrase['timezone_gmt_minus_0430'],
		'-4'   => $vbphrase['timezone_gmt_minus_0400'],
		'-3.5' => $vbphrase['timezone_gmt_minus_0330'],
		'-3'   => $vbphrase['timezone_gmt_minus_0300'],
		'-2'   => $vbphrase['timezone_gmt_minus_0200'],
		'-1'   => $vbphrase['timezone_gmt_minus_0100'],
		'0'    => $vbphrase['timezone_gmt_plus_0000'],
		'1'    => $vbphrase['timezone_gmt_plus_0100'],
		'2'    => $vbphrase['timezone_gmt_plus_0200'],
		'3'    => $vbphrase['timezone_gmt_plus_0300'],
		'3.5'  => $vbphrase['timezone_gmt_plus_0330'],
		'4'    => $vbphrase['timezone_gmt_plus_0400'],
		'4.5'  => $vbphrase['timezone_gmt_plus_0430'],
		'5'    => $vbphrase['timezone_gmt_plus_0500'],
		'5.5'  => $vbphrase['timezone_gmt_plus_0530'],
		'5.75' => $vbphrase['timezone_gmt_plus_0545'],
		'6'    => $vbphrase['timezone_gmt_plus_0600'],
		'6.5'  => $vbphrase['timezone_gmt_plus_0630'],
		'7'    => $vbphrase['timezone_gmt_plus_0700'],
		'8'    => $vbphrase['timezone_gmt_plus_0800'],
		'9'    => $vbphrase['timezone_gmt_plus_0900'],
		'9.5'  => $vbphrase['timezone_gmt_plus_0930'],
		'10'   => $vbphrase['timezone_gmt_plus_1000'],
		'11'   => $vbphrase['timezone_gmt_plus_1100'],
		'12'   => $vbphrase['timezone_gmt_plus_1200']
	);
}

// #############################################################################
/**
* Reads all data from the specified image table and writes the serialized data to the datastore
*
* @param	string	Name of image table (avatar/icon/smilie)
*/
function build_image_cache($table)
{
	global $vbulletin;

	if ($table == 'avatar')
	{
		return;
	}

	DEVDEBUG("Updating $table cache template...");

	$itemid = $table.'id';
	if ($table == 'smilie')
	{
		// the smilie cache is basically only used for parsing; displaying smilies comes from a query
		$items = $vbulletin->db->query_read("
			SELECT *, LENGTH(smilietext) AS smilielen
			FROM " . TABLE_PREFIX . "$table
			WHERE LENGTH(TRIM(smilietext)) > 0
			ORDER BY smilielen DESC
		");
	}
	else
	{
		$items = $vbulletin->db->query_read("SELECT * FROM " . TABLE_PREFIX . "$table ORDER BY imagecategoryid, displayorder");
	}

	$itemarray = array();

	while ($item = $vbulletin->db->fetch_array($items))
	{
		$itemarray["$item[$itemid]"] = array();
		foreach ($item AS $field => $value)
		{
			if (!is_numeric($field))
			{
				$itemarray["$item[$itemid]"]["$field"] = $value;
			}
		}
	}

	build_datastore($table . 'cache', serialize($itemarray), 1);

	if ($table == 'smilie')
	{
//		$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "postparsed"); // smilies changed, so posts could parse differently
		if ($vbulletin->options['templateversion'] >= '3.6')
		{
//			$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "sigparsed");
		}
	}

	// Legacy Hook 'admin_cache_smilies' Removed //
}

// #############################################################################
/**
* Reads all data from the bbcode table and writes the serialized data to the datastore
*/
function build_bbcode_cache()
{
	global $vbulletin;
	DEVDEBUG("Updating bbcode cache template...");
	$bbcodes = $vbulletin->db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "bbcode
	");
	$bbcodearray = array();
	while ($bbcode = $vbulletin->db->fetch_array($bbcodes))
	{
		$bbcodearray["$bbcode[bbcodeid]"] = array();
		foreach ($bbcode AS $field => $value)
		{
			if (!is_numeric($field))
			{
				$bbcodearray["$bbcode[bbcodeid]"]["$field"] = $value;

			}
		}

		$bbcodearray["$bbcode[bbcodeid]"]['strip_empty'] = (intval($bbcode['options']) & $vbulletin->bf_misc['bbcodeoptions']['strip_empty']) ? 1 : 0 ;
		$bbcodearray["$bbcode[bbcodeid]"]['stop_parse'] = (intval($bbcode['options']) & $vbulletin->bf_misc['bbcodeoptions']['stop_parse']) ? 1 : 0 ;
		$bbcodearray["$bbcode[bbcodeid]"]['disable_smilies'] = (intval($bbcode['options']) & $vbulletin->bf_misc['bbcodeoptions']['disable_smilies']) ? 1 : 0 ;
		$bbcodearray["$bbcode[bbcodeid]"]['disable_wordwrap'] = (intval($bbcode['options']) & $vbulletin->bf_misc['bbcodeoptions']['disable_wordwrap']) ? 1 : 0 ;
	}

	build_datastore('bbcodecache', serialize($bbcodearray), 1);

//	$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "postparsed"); // bbcodes changed, so posts could parse differently
	if ($vbulletin->options['templateversion'] >= '3.6')
	{
//		$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "sigparsed");
	}

	// Legacy Hook 'admin_cache_bbcode' Removed //
}

// #############################################################################
/**
* Prints a <script> block that allows you to call js_open_phrase_ref() from Javascript
*
* @param	integer	ID of initial language to be displayed
* @param	integer	ID of initial phrasetype to be displayed
* @param	integer	Pixel width of popup window
* @param	integer	Pixel height of popup window
*/
function print_phrase_ref_popup_javascript($languageid = 0, $fieldname = '', $width = 700, $height = 202)
{
	global $vbulletin;

	$q =  iif($languageid, "&languageid=$languageid", '');
	$q .= iif($$fieldname, "&fieldname=$fieldname", '');

	echo "<script type=\"text/javascript\">\n<!--
	function js_open_phrase_ref(languageid,fieldname)
	{
		var qs = '';
		if (languageid != 0) qs += '&languageid=' + languageid;
		if (fieldname != '') qs += '&fieldname=' + fieldname;
		window.open('phrase.php?" . vB::getCurrentSession()->get('sessionurl') . "do=quickref' + qs, 'quickref', 'width=$width,height=$height,resizable=yes');
	}\n// -->\n</script>\n";
}
// #############################################################################

function get_disabled_perms($usergroupid)
{
	global $vbulletin;
	$disabled = array();
	// Profile pics disabled so don't inherit any of the profile pic settings
	if (!($vbulletin->usergroupcache[$usergroupid]['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canprofilepic']))
	{
		$disabled['profilepicmaxwidth'] = -1;
		$disabled['profilepicmaxheight'] = -1;
		$disabled['profilepicmaxsize'] = -1;
	}
	// Avatars disabled so don't inherit any of the avatar settings
	if (!($vbulletin->usergroupcache[$usergroupid]['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar']))
	{
		$disabled['avatarmaxwidth'] = -1;
		$disabled['avatarmaxheight'] = -1;
		$disabled['avatarmaxsize'] = -1;
	}

	// Signature pics or signatures are disabled so don't inherit any of the signature pic settings
	if (!($vbulletin->usergroupcache[$usergroupid]['signaturepermissions'] & $vbulletin->bf_ugp_signaturepermissions['cansigpic']) OR !($vbulletin->usergroupcache[$usergroupid]['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusesignature']))
	{
		$disabled['sigpicmaxwidth'] = -1;
		$disabled['sigpicmaxheight'] = -1;
		$disabled['sigpicmaxsize'] = -1;
	}

	// Signatures are disabled so don't inherit any of the signature settings
	if (!($vbulletin->usergroupcache[$usergroupid]['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusesignature']))
	{
		$disabled['sigmaxrawchars'] = -1;
		$disabled['sigmaxchars'] = -1;
		$disabled['sigmaxlines'] = -1;
		$disabled['sigmaxsizebbcode'] = -1;
		$disabled['sigmaximages'] = -1;
		$disabled['signaturepermissions'] = 0;
	}
	return $disabled;
}

/**
* Rebuilds the $vbulletin->usergroupcache
*/
function build_channel_permissions()
{
	global $vbulletin, $npermscache;

	$grouppermissions = array();
	$npermscache = array();
	$vbulletin->usergroupcache = array();

	// query usergroups
	$usergroups = vB::getDbAssertor()->assertQuery('usergroup', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
		), 'title');
	foreach ($usergroups as $usergroup)
	{
		foreach ($usergroup AS $key => $val)
		{
			if (is_numeric($val))
			{
				$usergroup["$key"] += 0;
			}
		}
		$vbulletin->usergroupcache["$usergroup[usergroupid]"] = $usergroup;
		$vbulletin->usergroupcache["$usergroup[usergroupid]"] = get_disabled_perms($usergroup['usergroupid']) + $vbulletin->usergroupcache["$usergroup[usergroupid]"];
		vB_Cache::instance()->write('channelperms_' . $usergroup[usergroupid], array(), 0, 'perms_changed');
		// Legacy Hook 'admin_build_forum_perms_group' Removed //

		$grouppermissions["$usergroup[usergroupid]"] = $usergroup['forumpermissions'];
	}
	unset($usergroup);
	DEVDEBUG('updateChannelCache( ) - Queried Usergroups');

	// query forum permissions
	$permQry = vB::getDbAssertor()->assertQuery('vBForum:permission', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT));
	$permissions = array();
	foreach ($permQry as $cperm) {
		$permissions["$cperm[groupid]"]["$cperm[nodeid]"] = $cperm;
		$npermscache["$cperm[nodeid]"]["$cperm[groupid]"] = $cperm;
		// Legacy Hook 'admin_build_channel_perms_forum' Removed //
	}
	foreach ($permissions as $groupid => $chanperms) {
		vB_Cache::instance()->write('channelperms_' . $groupid, $chanperms, 1440, 'perms_changed');
	}

	$userContext = vB::getUserContext();

	if ($userContext)
	{
		$userContext->rebuildGroupAccess();
	}
	DEVDEBUG('updateChannelCache( ) - Queried Channel Pemissions');

	// call the function that will work out the forum permissions
	cache_forum_permissions($grouppermissions);

	// finally replace the existing cache templates
	$vbulletin->usergroupcache = vB::getDatastore()->buildUserGroupCache($vbulletin->usergroupcache);
}
// #############################################################################
/**
* Rebuilds the $vbulletin->usergroupcache and $vbulletin->forumcache from the forum/usergroup tables
*
* @param	boolean	If true, force a recalculation of the forum parent and child lists
*/
function build_forum_permissions($rebuild_genealogy = true)
{
	global $vbulletin, $fpermcache;

	#echo "<h1>updateForumPermissions</h1>";

	$grouppermissions = array();
	$fpermcache = array();
	$vbulletin->forumcache = array();
	$vbulletin->usergroupcache = array();

	// query usergroups
	$usergroups = vB::getDbAssertor()->assertQuery('usergroup', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
		), 'title');
	foreach ($usergroups as $usergroup)
	{
		foreach ($usergroup AS $key => $val)
		{
			if (is_numeric($val))
			{
				$usergroup["$key"] += 0;
			}
		}
		$vbulletin->usergroupcache["$usergroup[usergroupid]"] = $usergroup;
		// Profile pics disabled so don't inherit any of the profile pic settings
		if (!($vbulletin->usergroupcache["$usergroup[usergroupid]"]['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canprofilepic']))
		{
			$vbulletin->usergroupcache["$usergroup[usergroupid]"]['profilepicmaxwidth'] = -1;
			$vbulletin->usergroupcache["$usergroup[usergroupid]"]['profilepicmaxheight'] = -1;
			$vbulletin->usergroupcache["$usergroup[usergroupid]"]['profilepicmaxsize'] = -1;
		}
		// Avatars disabled so don't inherit any of the avatar settings
		if (!($vbulletin->usergroupcache["$usergroup[usergroupid]"]['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar']))
		{
			$vbulletin->usergroupcache["$usergroup[usergroupid]"]['avatarmaxwidth'] = -1;
			$vbulletin->usergroupcache["$usergroup[usergroupid]"]['avatarmaxheight'] = -1;
			$vbulletin->usergroupcache["$usergroup[usergroupid]"]['avatarmaxsize'] = -1;
		}
		// Signature pics or signatures are disabled so don't inherit any of the signature pic settings
		if (!($vbulletin->usergroupcache["$usergroup[usergroupid]"]['signaturepermissions'] & $vbulletin->bf_ugp_signaturepermissions['cansigpic']) OR !($vbulletin->usergroupcache["$usergroup[usergroupid]"]['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusesignature']))
		{
			$vbulletin->usergroupcache["$usergroup[usergroupid]"]['sigpicmaxwidth'] = -1;
			$vbulletin->usergroupcache["$usergroup[usergroupid]"]['sigpicmaxheight'] = -1;
			$vbulletin->usergroupcache["$usergroup[usergroupid]"]['sigpicmaxsize'] = -1;
		}

		// Signatures are disabled so don't inherit any of the signature settings
		if (!($vbulletin->usergroupcache["$usergroup[usergroupid]"]['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusesignature']))
		{
			$vbulletin->usergroupcache["$usergroup[usergroupid]"]['sigmaxrawchars'] = -1;
			$vbulletin->usergroupcache["$usergroup[usergroupid]"]['sigmaxchars'] = -1;
			$vbulletin->usergroupcache["$usergroup[usergroupid]"]['sigmaxlines'] = -1;
			$vbulletin->usergroupcache["$usergroup[usergroupid]"]['sigmaxsizebbcode'] = -1;
			$vbulletin->usergroupcache["$usergroup[usergroupid]"]['sigmaximages'] = -1;
			$vbulletin->usergroupcache["$usergroup[usergroupid]"]['signaturepermissions'] = 0;
		}

		// Legacy Hook 'admin_build_forum_perms_group' Removed //

		$grouppermissions["$usergroup[usergroupid]"] = $usergroup['forumpermissions'];
	}
	unset($usergroup);
	$vbulletin->db->free_result($usergroups);
	DEVDEBUG('updateForumCache( ) - Queried Usergroups');

	$vbulletin->forumcache = array();
	$vbulletin->iforumcache = array();
	$forumdata = array();

	// get the vbulletin->iforumcache so we can traverse the forums in order within cache_forum_permissions
	$newforumcache = $vbulletin->db->query_read("
		SELECT forum.*" . ((VB_AREA != 'Upgrade' AND VB_AREA != 'Install') ? ", NOT ISNULL(podcast.forumid) AS podcast" : "") . "
		FROM " . TABLE_PREFIX . "forum AS forum
		" . ((VB_AREA != 'Upgrade' AND VB_AREA != 'Install') ? "LEFT JOIN " . TABLE_PREFIX . "podcast AS podcast ON (forum.forumid = podcast.forumid AND podcast.enabled = 1)" : "") . "
		ORDER BY displayorder
	");
	while ($newforum = $vbulletin->db->fetch_array($newforumcache))
	{
		foreach ($newforum AS $key => $val)
		{
			/* values which begin with 0 and are greater than 1 character are strings, since 01 would be an octal number in PHP */
			if (is_numeric($val) AND !(substr($val, 0, 1) == '0' AND strlen($val) > 1) AND !in_array($key, array('title', 'title_clean', 'description', 'description_clean')))
			{
				$newforum["$key"] += 0;
			}
		}
		$vbulletin->iforumcache["$newforum[parentid]"]["$newforum[forumid]"] = $newforum['forumid'];
		$forumdata["$newforum[forumid]"] = $newforum;
	}
	$vbulletin->db->free_result($newforumcache);

	// get the forumcache into the order specified in $vbulletin->iforumcache
	$vbulletin->forumorder = array();
	fetch_forum_order();
	foreach ($vbulletin->forumorder AS $forumid => $depth)
	{
		$vbulletin->forumcache["$forumid"] =& $forumdata["$forumid"];
		$vbulletin->forumcache["$forumid"]['depth'] = $depth;
	}
	unset($vbulletin->forumorder);

	// rebuild forum parent/child lists
	if ($rebuild_genealogy)
	{
		build_forum_genealogy();
	}

	// query forum permissions
	$fperms = $vbulletin->db->query_read("SELECT * FROM " . TABLE_PREFIX . "forumpermission");
	while ($fperm = $vbulletin->db->fetch_array($fperms))
	{
		$fpermcache["$fperm[forumid]"]["$fperm[usergroupid]"] = intval($fperm['forumpermissions']);

		// Legacy Hook 'admin_build_forum_perms_forum' Removed //
	}
	unset($fperm);
	$vbulletin->db->free_result($fperms);
	DEVDEBUG('updateForumCache( ) - Queried Forum Pemissions');

	// call the function that will work out the forum permissions
	cache_forum_permissions($grouppermissions);

	// finally replace the existing cache templates
	build_datastore('usergroupcache', serialize($vbulletin->usergroupcache), 1);
	foreach(array_keys($vbulletin->forumcache) AS $forumid)
	{
		unset(
			$vbulletin->forumcache["$forumid"]['replycount'],
			$vbulletin->forumcache["$forumid"]['lastpost'],
			$vbulletin->forumcache["$forumid"]['lastposter'],
			$vbulletin->forumcache["$forumid"]['lastposterid'],
			$vbulletin->forumcache["$forumid"]['lastthread'],
			$vbulletin->forumcache["$forumid"]['lastthreadid'],
			$vbulletin->forumcache["$forumid"]['lasticonid'],
			$vbulletin->forumcache["$forumid"]['lastprefixid'],
			$vbulletin->forumcache["$forumid"]['threadcount']
		);
	}
	build_datastore('forumcache', serialize($vbulletin->forumcache), 1);

	DEVDEBUG('updateForumCache( ) - Updated caches, ' . $vbulletin->db->affected_rows() . ' rows affected.');
}

// #############################################################################
/**
* Recursive function to build $vbulletin->forumorder - used to get the order of forums
*
* @param	integer	Initial parent forum ID to use
* @param	integer	Initial depth of forums
*/
function fetch_forum_order($parentid = -1, $depth = 0)
{
	global $vbulletin;

	if (is_array($vbulletin->iforumcache["$parentid"]))
	{
		foreach ($vbulletin->iforumcache["$parentid"] AS $forumid)
		{
			$vbulletin->forumorder["$forumid"] = $depth;
			fetch_forum_order($forumid, $depth + 1);
		}
	}
}

// #############################################################################
/**
* Recalculates forum parent and child lists, then saves them back to the forum table
*/
function build_forum_genealogy()
{
	global $vbulletin;

	if (empty($vbulletin->forumcache))
	{
		return;
	}

	// build parent/child lists
	foreach ($vbulletin->forumcache AS $forumid => $forum)
	{
		// parent list
		$i = 0;
		$curid = $forumid;

		$vbulletin->forumcache["$forumid"]['parentlist'] = '';

		while ($curid != -1 AND $i++ < 1000)
		{
			if ($curid)
			{
				$vbulletin->forumcache["$forumid"]['parentlist'] .= $curid . ',';
				$curid = $vbulletin->forumcache["$curid"]['parentid'];
			}
			else
			{
				global $vbphrase;
				if (!isset($vbphrase['invalid_forum_parenting']))
				{
					$vbphrase['invalid_forum_parenting'] = 'Invalid forum parenting setup. Contact vBulletin support.';
				}
				trigger_error($vbphrase['invalid_forum_parenting'], E_USER_ERROR);
			}
		}

		$vbulletin->forumcache["$forumid"]['parentlist'] .= '-1';

		// child list
		$vbulletin->forumcache["$forumid"]['childlist'] = $forumid;
		fetch_forum_child_list($forumid, $forumid);
		$vbulletin->forumcache["$forumid"]['childlist'] .= ',-1';
	}

	$parentsql = '';
	$childsql = '';
	foreach ($vbulletin->forumcache AS $forumid => $forum)
	{
		$parentsql .= "	WHEN $forumid THEN '$forum[parentlist]'
		";
		$childsql .= "	WHEN $forumid THEN '$forum[childlist]'
		";
	}

	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "forum SET
			parentlist = CASE forumid
				$parentsql
				ELSE parentlist
			END,
			childlist = CASE forumid
				$childsql
				ELSE childlist
			END
	");
}

// #############################################################################
/**
* Recursive function to populate $vbulletin->forumcache with correct child list fields
*
* @param	integer	Forum ID to be updated
* @param	integer	Parent forum ID
*/
function fetch_forum_child_list($mainforumid, $parentid)
{
	global $vbulletin;

	if (is_array($vbulletin->iforumcache["$parentid"]))
	{
		foreach ($vbulletin->iforumcache["$parentid"] AS $forumid => $forumparentid)
		{
			$vbulletin->forumcache["$mainforumid"]['childlist'] .= ',' . $forumid;
			fetch_forum_child_list($mainforumid, $forumid);
		}
	}
}

// #############################################################################
/**
* Populates the $vbulletin->forumcache with calculated forum permissions for each usergroup
*
* NB: this function should only be called from build_forum_permissions()
*
* @param	integer	Initial permissions value
* @param	integer	Parent forum id
*/
function cache_forum_permissions($permissions, $parentid = 0)
{
	global $vbulletin, $npermscache;
	// abort if no child forums found
	$channels = vB_Api::instanceInternal('search')->getChannels(true);
	if (empty($channels["$parentid"]) OR empty($channels["$parentid"]['channels']))
	{
		return;
	}
	$cacheperms = array();
	// run through each child forum
	foreach($channels["$parentid"]['channels'] AS $nodeid => $channel)
	{
		// make a copy of the current permissions set up
		$perms = $permissions;
		// run through each usergroup
		foreach(array_keys($vbulletin->usergroupcache) AS $usergroupid)
		{
			// if there is a custom permission for the current usergroup, use it
			if (isset($npermscache["$nodeid"]["$usergroupid"]))
			{
				$perms["$usergroupid"]["$nodeid"] = $npermscache["$nodeid"]["$usergroupid"];
			}
		}
		$cacheperms["$usergroupid"]["$nodeid"] = $perms["$usergroupid"]["$nodeid"];
		// recurse to child forums
		cache_forum_permissions($perms, $nodeid);
	}
	foreach ($cacheperms as $usergroupid => $perms) {
		$hashkey = 'channelperms_' . $usergroupid;
		// Legacy Hook 'admin_cache_channel_perms' Removed //
		vB_Cache::instance()->write($hashkey, $perms, 1440, 'perms_changed');
	}

}

// #############################################################################
/**
* Returns a string safe for use in Javascript code
*
* @param	string	Text to be made safe
* @param	string	Quote type to be used in Javascript (either ' or ")
*
* @return	string
*/
function fetch_js_safe_string($object, $quotechar = '"')
{
	$find = array(
		"\r\n",
		"\n",
		'"'
	);

	$replace = array(
		'\r\n',
		'\n',
		"\\$quotechar",
	);

	$object = str_replace($find, $replace, $object);

	return $object;
}

// #############################################################################
/**
* Returns a string safe for use in Javascript code
*
* @param	string	Text to be made safe
* @param	string	Quote type to be used in Javascript (either ' or ")
*
* @return	string
*/
function fetch_js_unsafe_string($object, $quotechar = '"')
{
	$find = array(
		'\r\n',
		'\n',
		"\\$quotechar",
	);

	$replace = array(
		"\r\n",
		"\n",
		"$quotechar",
	);

	$object = str_replace($find, $replace, $object);

	return $object;
}

// #############################################################################
/**
* Returns an array of folders containing control panel CSS styles
*
* Styles are read from /path/to/vbulletin/cpstyles/
*
* @return	array
*/
function fetch_cpcss_options()
{
	$folders = array();

	if ($handle = @opendir(DIR . '/cpstyles'))
	{
		while ($folder = readdir($handle))
		{
			if ($folder == '.' OR $folder == '..')
			{
				continue;
			}
			if (is_dir(DIR . "/cpstyles/$folder") AND @file_exists(DIR . "/cpstyles/$folder/controlpanel.css"))
			{
				$folders["$folder"] = $folder;
			}
		}
		closedir($handle);
		uksort($folders, 'strnatcasecmp');
		$folders = str_replace('_', ' ', $folders);
	}

	return $folders;
}

// #############################################################################
/**
* Returns a string with & converted to &amp; when not followed by an entity
*
* @param	string	Text to be converted
*
* @return	string
*/
function convert_to_valid_html($text)
{
	return preg_replace('/&(?![a-z0-9#]+;)/', '&amp;', $text);
}

// ############################## Start vbflush ####################################
/**
* Force the output buffers to the browser
*/
function vbflush()
{
	static $gzip_handler = null;
	if ($gzip_handler === null)
	{
		$gzip_handler = false;
		$output_handlers = ob_list_handlers();
		if (is_array($output_handlers))
		{
			foreach ($output_handlers AS $handler)
			{
				if ($handler == 'ob_gzhandler')
				{
					$gzip_handler = true;
					break;
				}
			}
		}
	}

	if ($gzip_handler)
	{
		// forcing a flush with this is very bad
		return;
	}

	if (ob_get_length() !== false)
	{
		@ob_flush();
	}
	flush();
}

// ############################## Start fetch_product_list ####################################
/**
* Returns an array of currently installed products. Always includes 'vBulletin'.
*
* @param	boolean	If true, SELECT *, otherwise SELECT productid, title
* @param	boolean	Allow a previously cached version to be used
*
* @return	array
*/
function fetch_product_list($alldata = false, $use_cached = true)
{
	if ($alldata)
	{
		static $all_data_cache = false;

		if ($all_data_cache === false)
		{
			$productlist = array(
				'vbulletin' => array(
					'productid' => 'vbulletin',
					'title' => 'vBulletin',
					'description' => '',
					'version' => vB::getDatastore()->getOption('templateversion'),
					'active' => 1
				)
			);

			$products = vB::getDbAssertor()->assertQuery('vBForum:fetchproduct');
			foreach ($products as $product)
			{
				$productlist["$product[productid]"] = $product;
			}

			$all_data_cache = $productlist;
		}
		else
		{
			$productlist = $all_data_cache;
		}
	}
	else
	{
		$productlist = array(
			'vbulletin' => 'vBulletin'
		);

		$products = vB::getDbAssertor()->assertQuery('vBForum:fetchproduct');
		foreach ($products as $product)
		{
			$productlist["$product[productid]"] = $product['title'];
		}
	}

	return $productlist;
}

// ############################## Start build_product_datastore ####################################
/**
* Stores the list of currently installed products into the datastore.
*/
function build_product_datastore()
{
	$products = array('vbulletin' => 1);

	$productList = vB::getDbAssertor()->getRows('product', array(vB_dB_Query::COLUMNS_KEY => array('productid', 'active')));

	foreach ($productList AS $product)
	{
		$products[$product['productid']] = $product['active'];
	}

	vB::getDatastore()->build('products', serialize($products), 1);
}

/**
* Verifies that the optimizer you are using with vB is compatible. Bugs in
* various versions of optimizers such as Turck MMCache and eAccelerator
* have rendered vB unusable.
*
* @return	string|bool	Returns true if no error, else returns a string that represents the error that occured
*/
function verify_optimizer_environment()
{
	// fail if eAccelerator is too old or Turck is loaded
	if (extension_loaded('Turck MMCache'))
	{
		return 'mmcache_not_supported';
	}
	else if (extension_loaded('eAccelerator'))
	{
		// first, attempt to use phpversion()...
		if ($eaccelerator_version = phpversion('eAccelerator'))
		{
			if (version_compare($eaccelerator_version, '0.9.3', '<') AND (@ini_get('eaccelerator.enable') OR @ini_get('eaccelerator.optimizer')))
			{
				return 'eaccelerator_too_old';
			}
		}
		// phpversion() failed, use phpinfo data
		else if (function_exists('phpinfo') AND function_exists('ob_start') AND @ob_start())
		{
			eval('phpinfo();');
			$info = @ob_get_contents();
			@ob_end_clean();
			preg_match('#<tr class="h"><th>eAccelerator support</th><th>enabled</th></tr>(?:\s+)<tr><td class="e">Version </td><td class="v">(.*?)</td></tr>(?:\s+)<tr><td class="e">Caching Enabled </td><td class="v">(.*?)</td></tr>(?:\s+)<tr><td class="e">Optimizer Enabled </td><td class="v">(.*?)</td></tr>#si', $info, $hits);
			if (!empty($hits[0]))
			{
				$version = trim($hits[1]);
				$caching = trim($hits[2]);
				$optimizer = trim($hits[3]);

				if (($caching === 'true' OR $optimizer === 'true') AND version_compare($version, '0.9.3', '<'))
				{
					return 'eaccelerator_too_old';
				}
			}
		}
	}
	else if (extension_loaded('apc'))
	{
		// first, attempt to use phpversion()...
		if ($apc_version = phpversion('apc'))
		{
			if (version_compare($apc_version, '2.0.4', '<'))
			{
				return 'apc_too_old';
			}
		}
		// phpversion() failed, use phpinfo data
		else if (function_exists('phpinfo') AND function_exists('ob_start') AND @ob_start())
		{
			eval('phpinfo();');
			$info = @ob_get_contents();
			@ob_end_clean();
			preg_match('#<tr class="h"><th>APC support</th><th>enabled</th></tr>(?:\s+)<tr><td class="e">Version </td><td class="v">(.*?)</td></tr>#si', $info, $hits);
			if (!empty($hits[0]))
			{
				$version = trim($hits[1]);

				if (version_compare($version, '2.0.4', '<'))
				{
					return 'apc_too_old';
				}
			}
		}
	}

	return true;
}

/**
* Checks userid is a user that shouldn't be editable
*
* @param	integer	userid to check
*
* @return	boolean
*/
function is_unalterable_user($userid)
{
	global $vbulletin;

	static $noalter = null;

	$vb5_config =& vB::getConfig();

	if (!$userid)
	{
		return false;
	}

	if ($noalter === null)
	{
		$noalter = explode(',', $vb5_config['SpecialUsers']['undeletableusers']);

		if (!is_array($noalter))
		{
			$noalter = array();
		}
	}

	return in_array($userid, $noalter);
}

/**
* Resolves an image URL used in the CP that should be relative to the root directory.
*
* @param	string	The path to resolve
*
* @return	string	Resolved path
*/
function resolve_cp_image_url($image_path)
{
	if ($image_path[0] == '/' OR preg_match('#^https?://#i', $image_path))
	{
		return $image_path;
	}
	else
	{
		return vB::getDatastore()->getOption('bburl') . "/$image_path";
	}
}

/**
* Prints JavaScript to automatically submit the named form. Primarily used
* for automatic redirects via POST.
*
* @param	string	Form name (in HTML)
*/
function print_form_auto_submit($form_name)
{
	$form_name = preg_replace('#[^a-z0-9_]#i', '', $form_name);

	?>
	<script type="text/javascript">
	<!--
	if (document.<?php echo $form_name; ?>)
	{
		function send_submit()
		{
			var submits = YAHOO.util.Dom.getElementsBy(
				function(element) { return (element.type == "submit") },
				"input", this
			);
			var submit_button;

			for (var i = 0; i < submits.length; i++)
			{
				submit_button = submits[i];
				submit_button.disabled = true;
				setTimeout(function() { submit_button.disabled = false; }, 10000);
			}

			return false;
		}

		YAHOO.util.Event.on(document.<?php echo $form_name; ?>, 'submit', send_submit);
		send_submit.call(document.<?php echo $form_name; ?>);
		document.<?php echo $form_name; ?>.submit();
	}
	// -->
	</script>
	<?php
}

// #############################################################################
/**
* Prints the help for the style generator
*
* @param	array 	contains all help info
*
* @return	string	Formatted help text
*/
function print_style_help($stylehelp)
{
	foreach ($stylehelp as $id => $info) {
		echo "<div id=\"$id\">";
		if($info[0]) echo "
		<strong>$info[0]</strong>";
		echo "
		$info[1]
		</div>
		";
	}
}

/**
 * Prints a standard table with a warning/notice
 *
 * @param	Message to print
 */
function print_warning_table($message)
{
		print_table_start();
		print_description_row($message, false, 2, 'warning');
		print_table_footer(2, '', '', false);
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 71254 $
|| ####################################################################
\*======================================================================*/
