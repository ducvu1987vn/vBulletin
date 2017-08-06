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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 70525 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbphrase, $vbulletin, $DEVDEBUG;
$phrasegroups = array('cphome');
$specialtemplates = array('maxloggedin', 'acpstats');

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');

// #############################################################################
// ########################### START MAIN SCRIPT ###############################
// #############################################################################

$vb5_config =& vB::getConfig();
$vb_options = vB::getDatastore()->getValue('options');
$assertor = vB::getDbAssertor();

// ############################## Start build_acpstats_datastore ####################################
/**
* Stores a cache of various data for ACP Home Quick Stats into the datastore.
*/
function build_acpstats_datastore()
{
	global $vbulletin;
	$assertor = vB::getDbAssertor();
	$starttime = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
	$mysqlversion = $assertor->getRow('mysqlVersion');

	$data = $assertor->getRow('vBForum:getFiledataFilesizeSum');
	$vbulletin->acpstats['attachsize'] = $data['size'];
	$data = $assertor->getRow('getCustomAvatarFilesizeSum');
	$vbulletin->acpstats['avatarsize'] = $data['size'];
	$data = $assertor->getRow('vBForum:getCustomProfilePicFilesizeSum');
	$vbulletin->acpstats['profilepicsize'] = $data['size'];

	$data = $assertor->getRow('user', array(
		vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_COUNT,
		vB_dB_Query::CONDITIONS_KEY => array(
			array('field' => 'joindate', 'value' => $starttime, vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_GTE)
		)
	));
	$vbulletin->acpstats['newusers'] = $data['count'];

	$data = $assertor->getRow('user', array(
		vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_COUNT,
		vB_dB_Query::CONDITIONS_KEY => array(
			array('field' => 'lastactivity', 'value' => $starttime, vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_GTE)
		)
	));
	$vbulletin->acpstats['userstoday'] = $data['count'];

	$data = $assertor->getRow('vBForum:node', array(
		vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_COUNT,
		vB_dB_Query::CONDITIONS_KEY => array(
			array('field' => 'created', 'value' => $starttime, vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_GTE)
		)
	));
	$vbulletin->acpstats['newposts'] = $data['count'];

	$vbulletin->acpstats['indexsize'] = 0;
	$vbulletin->acpstats['datasize'] = 0;
	if ($mysqlversion['version'] >= '3.23')
	{
		try
		{
			$tables = $assertor->getRows('getTableStatus', array());
		}
		catch (Exception $ex)
		{
			$tables = array();
		}

		if ($tables AND !isset($table['errors']))
		{
			foreach ($tables AS $table)
			{
				$vbulletin->acpstats['datasize'] += $table['Data_length'];
				$vbulletin->acpstats['indexsize'] += $table['Index_length'];
			}
		}
	}
	if (!$vbulletin->acpstats['indexsize'])
	{
		$vbulletin->acpstats['indexsize'] = -1;
	}
	if (!$vbulletin->acpstats['datasize'])
	{
		$vbulletin->acpstats['datasize'] = -1;
	}
	$vbulletin->acpstats['lastupdate'] = vB::getRequest()->getTimeNow();
	build_datastore('acpstats', serialize($vbulletin->acpstats), 1);
}

if (empty($_REQUEST['do']))
{
	log_admin_action();
}

// #############################################################################

$vbulletin->input->clean_array_gpc('r', array(
	'redirect' => vB_Cleaner::TYPE_STR,
	'nojs'     => vB_Cleaner::TYPE_BOOL,
));

// #############################################################################
// ################################## REDIRECTOR ###############################
// #############################################################################

if (!empty($vbulletin->GPC['redirect']))
{
	require_once(DIR . '/includes/functions_login.php');
	$redirect = htmlspecialchars_uni(fetch_replaced_session_url($vbulletin->GPC['redirect']));
	$redirect = create_full_url($redirect);
	$redirect = preg_replace(
		array('/&#0*59;?/', '/&#x0*3B;?/i', '#;#'),
		'%3B',
		$redirect
	);
	$redirect = preg_replace('#&amp%3B#i', '&amp;', $redirect);

	print_cp_header($vbphrase['redirecting_please_wait'], '', "<meta http-equiv=\"Refresh\" content=\"0; URL=$redirect\" />");
	echo "<p>&nbsp;</p><blockquote><p>$vbphrase[redirecting_please_wait]</p></blockquote>";
	print_cp_footer();
	exit;
}

// #############################################################################
// ############################### LOG OUT OF CP ###############################
// #############################################################################

if ($_REQUEST['do'] == 'cplogout')
{
	vbsetcookie('cpsession', '', false, true, true);
	$assertor->delete('cpsession', array(
		'userid' => vB::getCurrentSession()->get('userid'),
		'hash' => $vbulletin->GPC[COOKIE_PREFIX . 'cpsession']
	));
	$args = array();
	parse_str(vB::getCurrentSession()->get('sessionurl_js'),$args);

	exec_header_redirect2('index',$args);
}

// #############################################################################
// ################################# SAVE NOTES ################################
// #############################################################################

if ($_POST['do'] == 'notes')
{
	$vbulletin->input->clean_array_gpc('p', array('notes' => vB_Cleaner::TYPE_STR));

	$admindm =& datamanager_init('Admin', $vbulletin, vB_DataManager_Constants::ERRTYPE_CP);
	$admindm->set_existing($vbulletin->userinfo);
	$admindm->set('notes', $vbulletin->GPC['notes']);
	$admindm->save();
	unset($admindm);

	$vbulletin->userinfo['notes'] = htmlspecialchars_uni($vbulletin->GPC['notes']);
	$_REQUEST['do'] = 'home';
}

// #############################################################################
// ################################# HEADER FRAME ##############################
// #############################################################################


if ($_REQUEST['do'] == 'head')
{
	ignore_user_abort(true);

	define('IS_NAV_PANEL', true);
	print_cp_header('', '');

	$forumHome = vB_Library::instance('content_channel')->getForumHomeChannel();
	$forumhomelink = vB5_Route::buildUrl($forumHome['routeid'] . '|fullurl|nosession');

	?>
	<div id="acp-head-wrapper">
		<ul id="acp-top-links">
			<li id="acp-top-link-acp" class="left"><div><span class="icon"></span><?php echo $vbphrase['admin_cp']; ?></div></li>
			<li id="acp-top-link-site" class="left"><a href="../" target="_blank"><?php echo $vbphrase['site_home_page']; ?></a></li>
			<li class="left divider"></li>
			<li id="acp-top-link-logout" class="right rightmost"><a href="index.php?<?php echo vB::getCurrentSession()->get('sessionurl'); ?>do=cplogout" onclick="return confirm('<?php echo $vbphrase['sure_you_want_to_log_out_of_cp']; ?>');"  target="_top"><?php echo $vbphrase['log_out']; ?></a></li>
			<li class="right divider"></li>
			<li id="acp-top-link-msg" class="right"><a href="../privatemessage/index" target="_blank"><?php echo $vbphrase['messages_header']; ?></a></li>
			<li class="right divider"></li>			
		</ul>
		<div id="acp-logo-bar">
			<div class="logo">
				<img src="<?php echo $vb_options['bburl']; ?>/cpstyles/<?php echo $vbulletin->options['cpstylefolder']; ?>/cp_logo.<?php echo $vbulletin->options['cpstyleimageext']; ?>" title="<?php echo $vbphrase['admin_control_panel']; ?>" alt="" border="0" />
			</div>
			<div class="links">
				<?php echo $vbphrase['vbulletin'] . ' ' . ADMIN_VERSION_VBULLETIN; echo (is_demo_mode() ? ' <b>DEMO MODE</b>' : ''); ?>
			</div>
			<div class="search">
				<?php print_form_header('search', 'dosearch', 1, 1, ''); ?>
					<input type="text" name="terms" />
					<input type="submit" class="button" value="<?php echo $vbphrase['search']; ?>" />
				</form>
			</div>
		</div>
	</div>
	<?php

	define('NO_CP_COPYRIGHT', true);
	unset($DEVDEBUG);
	print_cp_footer();

}

$vbulletin->input->clean_array_gpc('r', array('navprefs' => vB_Cleaner::TYPE_STR));
$vbulletin->GPC['navprefs'] = preg_replace('#[^a-z0-9_,]#i', '', $vbulletin->GPC['navprefs']);

// #############################################################################
// ############################### SAVE NAV PREFS ##############################
// #############################################################################

if ($_REQUEST['do'] == 'navprefs')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'groups'	=> vB_Cleaner::TYPE_STR,
		'expand'	=> vB_Cleaner::TYPE_BOOL,
		'navprefs'	=> vB_Cleaner::TYPE_STR
	));

	$vbulletin->GPC['groups'] = preg_replace('#[^a-z0-9_,]#i', '', $vbulletin->GPC['groups']);

	if ($vbulletin->GPC['expand'])
	{
		$groups = explode(',', $vbulletin->GPC['groups']);

		foreach ($groups AS $group)
		{
			if (empty($group))
			{
				continue;
			}

			$vbulletin->input->clean_gpc('r', "num$group", vB_Cleaner::TYPE_UINT);

			for ($i = 0; $i < $vbulletin->GPC["num$group"]; $i++)
			{
				$vbulletin->GPC['navprefs'][] = $group . "_$i";
			}
		}

		$vbulletin->GPC['navprefs'] = implode(',', $vbulletin->GPC['navprefs']);
	}
	else
	{
		$vbulletin->GPC['navprefs'] = '';
	}

	$vbulletin->GPC['navprefs'] = preg_replace('#[^a-z0-9_,]#i', '', $vbulletin->GPC['navprefs']);

	$_REQUEST['do'] = 'savenavprefs';
}

if ($_REQUEST['do'] == 'buildbitfields')
{
	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save();
	build_channel_permissions();

	print_stop_message2('rebuilt_bitfields_successfully', 'index');
}

if ($_REQUEST['do'] == 'buildvideo')
{
	require_once(DIR . '/includes/functions_databuild.php');
	build_bbcode_video();

	print_cp_header();
	vB_Api::instanceInternal('style')->buildAllStyles();
	print_stop_message2('rebuilt_video_bbcodes_successfully', 'index');
}

if ($_REQUEST['do'] == 'buildnavprefs')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'prefs' 	=> vB_Cleaner::TYPE_STR,
		'dowhat'	=> vB_Cleaner::TYPE_STR,
		'id'		=> vB_Cleaner::TYPE_INT
	));

	$vbulletin->GPC['prefs'] = preg_replace('#[^a-z0-9_,]#i', '', $vbulletin->GPC['prefs']);
	$_tmp = preg_split('#,#', $vbulletin->GPC['prefs'], -1, PREG_SPLIT_NO_EMPTY);
	$_navprefs = array();

	foreach ($_tmp AS $_val)
	{
		$_navprefs["$_val"] = $_val;
	}
	unset($_tmp);

	if ($vbulletin->GPC['dowhat'] == 'collapse')
	{
		// remove an item from the list
		unset($_navprefs[$vbulletin->GPC['id']]);
	}
	else
	{
		// add an item to the list
		$_navprefs[$vbulletin->GPC['id']] = $vbulletin->GPC['id'];
		ksort($_navprefs);
	}

	$vbulletin->GPC['navprefs'] = implode(',', $_navprefs);
	$_REQUEST['do'] = 'savenavprefs';
}

if ($_REQUEST['do'] == 'savenavprefs')
{
	$admindm =& datamanager_init('Admin', $vbulletin, vB_DataManager_Constants::ERRTYPE_CP);
	$admindm->set_existing($vbulletin->userinfo);
	$admindm->set('navprefs', $vbulletin->GPC['navprefs']);
	$admindm->save();
	unset($admindm);

	$_NAVPREFS = preg_split('#,#', $vbulletin->GPC['navprefs'], -1, PREG_SPLIT_NO_EMPTY);
	$_REQUEST['do'] = 'nav';
}

// ################################ NAVIGATION FRAME #############################

if ($_REQUEST['do'] == 'nav')
{
	require_once(DIR . '/includes/adminfunctions_navpanel.php');
	print_cp_header();

	echo "\n\n" . iif(is_demo_mode(), "<div align=\"center\"><b>DEMO MODE</b></div>\n\n") . "<div id=\"acp-nav-wrapper\">\n";

	// cache nav prefs
	can_administer();
	$GLOBALS['_NAV'] .= '<div class="nav-spacer-wide"></div>';

	$navigation = array(); // [displayorder][phrase/text] = array([group], [options][disporder][])

	require_once(DIR . '/includes/class_xml.php');

	$navfiles = vB_Api_Product::loadProductXmlList('cpnav', true);

	if (empty($navfiles['vbulletin']))	// cpnav_vbulletin.xml is missing
	{
		echo construct_phrase($vbphrase['could_not_open_x'], DIR . '/includes/xml/cpnav_vbulletin.xml');
		exit;
	}

	foreach ($navfiles AS $nav_file => $file)
	{
		$xmlobj = new vB_XML_Parser(false, $file);
		$xml =& $xmlobj->parse();

		if ($xml['product'] AND empty($vbulletin->products["$xml[product]"]))
		{
			// attached to a specific product and that product isn't enabled
			continue;
		}

		if (!is_array($xml['navgroup'][0]))
		{
			$xml['navgroup'] = array($xml['navgroup']);
		}

		foreach ($xml['navgroup'] AS $navgroup)
		{
			if (!empty($navgroup['debug']) AND $vb5_config['Misc']['debug'] != 1)
			{
				continue;
			}

			// do we have access to this group
			if (empty($navgroup['permissions']) OR can_administer($navgroup['permissions']))
			{
				$group_displayorder = intval($navgroup['displayorder']);
				$group_key = fetch_nav_text($navgroup);

				if (!isset($navigation["$group_displayorder"]["$group_key"]))
				{
					$navigation["$group_displayorder"]["$group_key"] = array('options' => array());
				}
				$local_options =& $navigation["$group_displayorder"]["$group_key"]['options'];

				if (!is_array($navgroup['navoption'][0]))
				{
					$navgroup['navoption'] = array($navgroup['navoption']);
				}
				foreach ($navgroup['navoption'] AS $navoption)
				{
					if (
						(!empty($navoption['debug']) AND $vb5_config['Misc']['debug'] != 1)
							OR
						(!empty($navoption['permissions']) AND !can_administer($navoption['permissions']))
					)
					{
						continue;
					}

					$navoption['link'] = str_replace(
						array(
							'{$vbulletin->config[Misc][modcpdir]}',
							'{$vbulletin->config[Misc][admincpdir]}'
						),
						array($vb5_config['Misc']['modcpdir'], $vb5_config['Misc']['admincpdir']),
						$navoption['link']
					);

					$navoption['text'] = fetch_nav_text($navoption);

					$local_options[intval($navoption['displayorder'])]["$navoption[text]"] = $navoption;
				}

				if (!isset($navigation["$group_displayorder"]["$group_key"]['group']) OR $xml['master'])
				{
					unset($navgroup['navoption']);
					$navgroup['nav_file'] = $nav_file;
					$navgroup['text'] = $group_key;

					$navigation["$group_displayorder"]["$group_key"]['group'] = $navgroup;
				}
			}
		}

		$xmlobj = null;
		unset($xml);
	}

	// Legacy Hook 'admin_index_navigation' Removed //

	// sort groups by display order
	ksort($navigation);
	foreach ($navigation AS $group_keys)
	{
		foreach ($group_keys AS $group_key => $navgroup_holder)
		{
			// sort options by display order
			ksort($navgroup_holder['options']);

			foreach ($navgroup_holder['options'] AS $navoption_holder)
			{
				foreach ($navoption_holder AS $navoption)
				{
					construct_nav_option($navoption['text'], $navoption['link']);
				}
			}

			// have all the options, so do the group
			construct_nav_group($navgroup_holder['group']['text'], $navgroup_holder['group']['nav_file']);

			if ($navgroup_holder['group']['hr'] == 'true')
			{
				construct_nav_spacer();
			}
		}
	}

	print_nav_panel();

	unset($navigation);

	echo "</div>\n";
	// *************************************************

	define('NO_CP_COPYRIGHT', true);
	unset($DEVDEBUG);
	print_cp_footer();

}

// #############################################################################
// ################################ BUILD FRAMESET #############################
// #############################################################################

if ($_REQUEST['do'] == 'frames' OR empty($_REQUEST['do']))
{
	$vbulletin->input->clean_array_gpc('r', array(
		'loc' 		=> vB_Cleaner::TYPE_NOHTML
	));

	$navframe = "<frame src=\"index.php?" . vB::getCurrentSession()->get('sessionurl') . "do=nav" . iif($vbulletin->GPC['nojs'], '&amp;nojs=1') . "\" name=\"nav\" scrolling=\"yes\" frameborder=\"0\" marginwidth=\"0\" marginheight=\"0\" border=\"no\" id=\"vb-acp-navframe\" />\n";
	$headframe = "<frame src=\"index.php?" . vB::getCurrentSession()->get('sessionurl') . "do=head\" name=\"head\" scrolling=\"no\" noresize=\"noresize\" frameborder=\"0\" marginwidth=\"10\" marginheight=\"0\" border=\"no\" id=\"vb-acp-headframe\" />\n";
	$mainframe = "<frame src=\"" . iif(!empty($vbulletin->GPC['loc']) AND !preg_match('#^[a-z]+:#i', $vbulletin->GPC['loc']), create_full_url($vbulletin->GPC['loc']), "index.php?" . vB::getCurrentSession()->get('sessionurl') . "do=home") . "\" name=\"main\" scrolling=\"yes\" frameborder=\"0\" marginwidth=\"10\" marginheight=\"10\" border=\"no\" id=\"vb-acp-mainframe\" />\n";

	?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml" dir="<?php echo vB_Template_Runtime::fetchStyleVar('textdirection'); ?>" lang="<?php echo vB_Template_Runtime::fetchStyleVar('languagecode'); ?>">
	<head>
	<script type="text/javascript">
	<!--
	// get out of any containing frameset
	if (self.parent.frames.length != 0)
	{
		self.parent.location.replace(document.location.href);
	}
	// -->
	</script>
	<title><?php echo $vbulletin->options['bbtitle'] . ' ' . $vbphrase['admin_control_panel']; ?></title>
	</head>

	<?php

	if (vB_Template_Runtime::fetchStyleVar('textdirection') == 'ltr')
	{
	// left-to-right frameset
	?>
	<frameset rows="85,*"  framespacing="0" border="0" frameborder="0" frameborder="no" border="0">
		<?php echo $headframe; ?>
		<frameset cols="256,*"  framespacing="0" border="0" frameborder="0" frameborder="no" border="0">
			<?php echo $navframe; ?>
			<?php echo $mainframe; ?>
		</frameset>
	</frameset>
	<?php
	}
	else
	{
	// right-to-left frameset
	?>
	<frameset rows="85,*"  framespacing="0" border="0" frameborder="0" frameborder="no" border="0">
		<?php echo $headframe; ?>
		<frameset cols="*,256"  framespacing="0" border="0" frameborder="0" frameborder="no" border="0">
			<?php echo $mainframe; ?>
			<?php echo $navframe; ?>
		</frameset>
	</frameset>
	<?php
	}

	?>

	<noframes>
		<body>
			<p><?php echo $vbphrase['no_frames_support']; ?></p>
		</body>
	</noframes>
	</html>
	<?php
}

// ################################ MAIN FRAME #############################

if ($_REQUEST['do'] == 'home')
{

$vbulletin->input->clean_array_gpc('r', array('showallnews' => vB_Cleaner::TYPE_BOOL));

print_cp_header($vbphrase['welcome_to_the_vbulletin_admin_control_panel']);

// Warn admin if admincpdir setting doesn't match the admincp directory
if (!empty($_SERVER['PATH_INFO']))
{
	$admincppath = dirname($_SERVER['PATH_INFO']);
	if (strpos($admincppath, '/') !== false)
	{
		$admincppath = strrchr($admincppath, '/');
	}
	$admincppath = trim($admincppath, '/');
	if ($admincppath != $vb5_config['Misc']['admincpdir'])
	{
		print_warning_table(construct_phrase($vbphrase['admincpdir_mismatch'], htmlspecialchars_uni($admincppath), htmlspecialchars_uni($vb5_config['Misc']['admincpdir'])));
	}
	unset($admincppath);
}

$news_rows = array();

// look to see if MySQL is running in strict mode
if (empty($vb5_config['Database']['force_sql_mode']))
{
	// check to see if MySQL is running strict mode and recommend disabling it
	$strict_mode_check = $assertor->getRow('showVariablesLike', array('var' => 'sql_mode'));
	if (strpos(strtolower($strict_mode_check['Value']), 'strict_') !== false)
	{
		ob_start();
		print_table_header($vbphrase['mysql_strict_mode_warning']);
		print_description_row('<div class="smallfont">' . $vbphrase['mysql_running_strict_mode'] . '</div>');
		$news_rows['sql_strict'] = ob_get_clean();
	}
}

// check if a PHP optimizer with known issues is installed
if (($err = verify_optimizer_environment()) !== true)
{
	ob_start();
	print_description_row($vbphrase['problematic_php_optimizer_found'], false, 2, 'thead');
	print_description_row('<div class="smallfont">' . $vbphrase["$err"] . '</div>');
	$news_rows['php_optimizer'] = ob_get_clean();
}

// look for incomplete admin messages that may have actually been independently completed
// and say they're done
$donemessages_result = $assertor->getRows('getIncompleteAdminMessages');
foreach ($donemessages_result AS $donemessage)
{
	$assertor->update('adminmessage', array('status' => 'done'), array('adminmessageid' => intval($donemessage['adminmessageid'])));
}

// let's look for any messages that we need to display to the admin
$adminmessages_result = $assertor->getRows('adminmessage', array('status' => 'undone'), 'dateline');
ob_start();
foreach ($adminmessages_result AS $adminmessage)
{
	$buttons = '';
	if ($adminmessage['execurl'])
	{
		$buttons .= '<input type="submit" name="address[' . $adminmessage['adminmessageid'] .']" value="' . $vbphrase['address'] . '" class="button" />';
	}
	if ($adminmessage['dismissable'] OR !$adminmessage['execurl'])
	{
		$buttons .= ' <input type="submit" name="dismiss[' . $adminmessage['adminmessageid'] .']" value="' . $vbphrase['dismiss_gcphome'] . '" class="button" />';
	}

	$args = @unserialize($adminmessage['args']);
	print_description_row("<div style=\"float: right\">$buttons</div><div>" . $vbphrase['admin_attention_required'] . "</div>", false, 2, 'thead');
	print_description_row(
		'<div class="smallfont">' . fetch_error($adminmessage['varname'], $args) . "</div>"
	);
}

$news_rows['admin_messages'] = ob_get_clean();

if (can_administer('canadminstyles'))
{
	// before the quick stats, display the number of templates that need updating
	require_once(DIR . '/includes/adminfunctions_template.php');
	$need_updates = fetch_changed_templates_count();
	if ($need_updates)
	{
		ob_start();
		print_description_row($vbphrase['out_of_date_custom_templates_found'], false, 2, 'thead');
		print_description_row(construct_phrase(
			'<div class="smallfont">' .  $vbphrase['currently_x_customized_templates_updated'] . '</div>',
			$need_updates,
			vB::getCurrentSession()->get('sessionurl')
		));
		$news_rows['new_version'] = ob_get_clean();
	}
}

echo '<div id="admin_news"' . (empty($news_rows) ? ' style="display: none;"' : '') . '>';
if (!empty($news_rows))
{
	print_form_header('index', 'handlemessage', false, true, 'news');

	print_table_header($vbphrase['news_header_string']);
	echo $news_rows['new_version'];
	echo $news_rows['php_optimizer'];
	echo $news_rows['sql_strict'];
	echo $news_rows['admin_messages'];

	print_table_footer();
}
else
{
	print_form_header('index', 'handlemessage', false, true, 'news');

	print_table_footer();
}
echo '</div>'; // end of <div id="admin_news">

// *******************************
// Admin Quick Stats -- Toggable via the CP
$starttime = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
$mysqlversion = $assertor->getRow('mysqlVersion');

if ($vbulletin->options['adminquickstats'])
{
	if ($vbulletin->acpstats['lastupdate'] < (vB::getRequest()->getTimeNow() - 3600))
	{
		build_acpstats_datastore();
	}

	// An index exists on dateline for thread marking so we can run this on each page load.
	$newthreads = $assertor->getRow('vBForum:getIndexNewStartersCount', array('starttime' => $starttime));

	if ($vbulletin->acpstats['datasize'] == -1)
	{
		$vbulletin->acpstats['datasize'] = $vbphrase['n_a'];
	}
	if ($vbulletin->acpstats['indexsize'] == -1)
	{
		$vbulletin->acpstats['indexsize'] = $vbphrase['n_a'];
	}
}

try
{
	$variables = $assertor->getRow('showVariablesLike', array('var' => 'max_allowed_packet'));
}
catch (Exception $ex)
{
	$variables = false;
}

if ($variables)
{
	$maxpacket = $variables['Value'];
}
else
{
	$maxpacket = $vbphrase['n_a'];
}

if (preg_match('#(Apache)/([0-9\.]+)\s#siU', $_SERVER['SERVER_SOFTWARE'], $wsregs))
{
	$webserver = "$wsregs[1] v$wsregs[2]";
	if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
	{
		$addsapi = true;
	}
}
else if (preg_match('#Microsoft-IIS/([0-9\.]+)#siU', $_SERVER['SERVER_SOFTWARE'], $wsregs))
{
	$webserver = "IIS v$wsregs[1]";
	$addsapi = true;
}
else if (preg_match('#Zeus/([0-9\.]+)#siU', $_SERVER['SERVER_SOFTWARE'], $wsregs))
{
	$webserver = "Zeus v$wsregs[1]";
	$addsapi = true;
}
else if (strtoupper($_SERVER['SERVER_SOFTWARE']) == 'APACHE')
{
	$webserver = 'Apache';
	if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
	{
		$addsapi = true;
	}
}
else
{
	$webserver = SAPI_NAME;
}

if ($addsapi)
{
	$webserver .= ' (' . SAPI_NAME . ')';
}

$serverinfo = SAFEMODE ? "<br />$vbphrase[safe_mode]" : '';
$serverinfo .= (ini_get('file_uploads') == 0 OR strtolower(ini_get('file_uploads')) == 'off') ? "<br />$vbphrase[file_uploads_disabled]" : '';

$memorylimit = ini_get('memory_limit');

// Moderation Counts //
$eventcount['count'] = 0; // Dummy until events are added to vB5.

$guids = array 
(
	'vbulletin-4ecbdf567f2c35.70389590', // Forum
	'vbulletin-4ecbdf567f3a38.99555306', // Groups
	'vbulletin-4ecbdf567f36c3.90966558', // Visitor Messages
);

$roots = $assertor->getRows('getRootChannels', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'guids' => $guids), array(), 'title');
$vmrootid = $roots['Visitor Messages']['nodeid'];
$rootids = array($roots['Forum']['nodeid'], $roots['Groups']['nodeid']);

// Note the returned field here is automatically called 'count'.
$waiting = $assertor->getRow('user', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_COUNT, 'usergroupid' => 4));

$phototype = vB_Types::instance()->getContentTypeId('vBForum_Photo');
$attachtype = vB_Types::instance()->getContentTypeId('vBForum_Attach');
$typeids = array($phototype, $attachtype);

$postcount = $assertor->getRow('getModeratedReplies', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'typeids' => $typeids, 'rootids' => $rootids));
$threadcount = $assertor->getRow('getModeratedTopics', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'typeids' => $typeids, 'rootids' => $rootids));
$attachcount = $assertor->getRow('getModeratedAttachments', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'typeids' => $typeids));
$messagecount = $assertor->getRow('getModeratedVisitorMessages', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'typeid' => $vmrootid));

print_form_header('index', 'home');
if ($vbulletin->options['adminquickstats'])
{
	print_table_header($vbphrase['welcome_to_the_vbulletin_admin_control_panel'], 6);
	print_cells_row(array(
		$vbphrase['server_type'], PHP_OS . $serverinfo,
		$vbphrase['database_data_usage'], vb_number_format($vbulletin->acpstats['datasize'], 2, true),
		$vbphrase['users_awaiting_moderation_gcphome'], vb_number_format($waiting['users']) . '&nbsp;&nbsp;' . construct_link_code($vbphrase['view'], "user.php?" . vB::getCurrentSession()->get('sessionurl') . "do=moderate"),
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['web_server'], $webserver,

		$vbphrase['database_index_usage'], vb_number_format($vbulletin->acpstats['indexsize'], 2, true),
		$vbphrase['threads_awaiting_moderation_gcphome'], vb_number_format($threadcount['count']) /*. '&nbsp;&nbsp;' . construct_link_code($vbphrase['view'], '../' . $vb5_config['Misc']['modcpdir'] . '/moderate.php?' . vB::getCurrentSession()->get('sessionurl') . "do=posts")*/,
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		'PHP', PHP_VERSION,
		$vbphrase['attachment_usage'], vb_number_format($vbulletin->acpstats['attachsize'], 2, true),
		$vbphrase['posts_awaiting_moderation_gcphome'], vb_number_format($postcount['count']) /*. '&nbsp;&nbsp;' . construct_link_code($vbphrase['view'],'../' . $vb5_config['Misc']['modcpdir'] . '/moderate.php?' . vB::getCurrentSession()->get('sessionurl') . "do=posts#postlist")*/,
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['php_max_post_size'], ($postmaxsize = ini_get('post_max_size')) ? vb_number_format($postmaxsize, 2, true) : $vbphrase['n_a'],
		$vbphrase['custom_avatar_usage'], vb_number_format($vbulletin->acpstats['avatarsize'], 2, true),
		$vbphrase['attachments_awaiting_moderation_gcphome'], vb_number_format($attachcount['count']) /*. '&nbsp;&nbsp;' . construct_link_code($vbphrase['view'], '../' . $vb5_config['Misc']['modcpdir'] . '/moderate.php?' . vB::getCurrentSession()->get('sessionurl') . "do=attachments")*/,
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['php_max_upload_size'], ($postmaxuploadsize = ini_get('upload_max_filesize')) ? vb_number_format($postmaxuploadsize, 2, true) : $vbphrase['n_a'],
		$vbphrase['custom_profile_picture_usage'], vb_number_format($vbulletin->acpstats['profilepicsize'], 2, true),
		$vbphrase['events_awaiting_moderation_gcphome'], vb_number_format($eventcount['count']) /*. '&nbsp;&nbsp;' . construct_link_code($vbphrase['view'], '../' . $vb5_config['Misc']['modcpdir'] . '/moderate.php?' . vB::getCurrentSession()->get('sessionurl') . "do=events")*/,
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['php_memory_limit'], ($memorylimit AND $memorylimit != '-1') ? vb_number_format($memorylimit, 2, true) : $vbphrase['none'],
		$vbphrase['unique_registered_visitors_today'], vb_number_format($vbulletin->acpstats['userstoday']),
		$vbphrase['messages_awaiting_moderation'], vb_number_format($messagecount['count']) /*. '&nbsp;&nbsp;' . construct_link_code($vbphrase['view'], '../' . $vb5_config['Misc']['modcpdir'] . '/moderate.php?' . vB::getCurrentSession()->get('sessionurl') . "do=messages")*/,
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['mysql_version_gcphome'], $mysqlversion['version'],
		$vbphrase['new_users_today'], vb_number_format($vbulletin->acpstats['newusers']),
		$vbphrase['new_threads_today'], vb_number_format($newthreads['count']),
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['mysql_max_packet_size'], vb_number_format($maxpacket, 2, 1),
		$vbphrase['new_posts_today'], vb_number_format($vbulletin->acpstats['newposts']),
		'&nbsp;', '&nbsp;',
	), 0, 0, -5, 'top', 1, 1);
}
else
{
	print_table_header($vbphrase['welcome_to_the_vbulletin_admin_control_panel'], 4);
	print_cells_row(array(
		$vbphrase['server_type'], PHP_OS . $serverinfo,
		$vbphrase['users_awaiting_moderation_gcphome'], vb_number_format($waiting['users']) . ' ' . construct_link_code($vbphrase['view'], "user.php?" . vB::getCurrentSession()->get('sessionurl') . "do=moderate")
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['web_server'], $webserver,
		$vbphrase['threads_awaiting_moderation_gcphome'], vb_number_format($threadcount['count']) . ' ' . construct_link_code($vbphrase['view'], '../' . $vb5_config['Misc']['modcpdir'] . '/moderate.php?' . vB::getCurrentSession()->get('sessionurl') . "do=posts")
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		'PHP', PHP_VERSION,
		$vbphrase['posts_awaiting_moderation_gcphome'], vb_number_format($postcount['count']) . ' ' . construct_link_code($vbphrase['view'],'../' . $vb5_config['Misc']['modcpdir'] . '/moderate.php?' . vB::getCurrentSession()->get('sessionurl') . "do=posts#postlist")
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['php_max_post_size'], ($postmaxsize = ini_get('post_max_size')) ? vb_number_format($postmaxsize, 2, true) : $vbphrase['n_a'],
		$vbphrase['attachments_awaiting_moderation_gcphome'], vb_number_format($attachcount['count']) . ' ' . construct_link_code($vbphrase['view'], '../' . $vb5_config['Misc']['modcpdir'] . '/moderate.php?' . vB::getCurrentSession()->get('sessionurl') . "do=attachments")
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['php_max_upload_size'], ($postmaxuploadsize = ini_get('upload_max_filesize')) ? vb_number_format($postmaxuploadsize, 2, true) : $vbphrase['n_a'],
		$vbphrase['events_awaiting_moderation_gcphome'], vb_number_format($eventcount['count']) . ' ' . construct_link_code($vbphrase['view'], '../' . $vb5_config['Misc']['modcpdir'] . '/moderate.php?' . vB::getCurrentSession()->get('sessionurl') . "do=events")
	), 0, 0, -5, 'top', 1, 1);
	if ($memorylimit AND $memorylimit != '-1')
	{
		print_cells_row(array(
			$vbphrase['php_memory_limit'], vb_number_format($memorylimit, 2, true),
			'&nbsp;', '&nbsp;'
		), 0, 0, -5, 'top', 1, 1);
	}
	print_cells_row(array(
		$vbphrase['mysql_version_gcphome'], $mysqlversion['version'],
		'&nbsp;', '&nbsp;'
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array($vbphrase['mysql_max_packet_size'], vb_number_format($maxpacket, 2, 1), '&nbsp;', '&nbsp;'), 0, 0, -5, 'top', 1, 1);
}

print_table_footer();
// Legacy Hook 'admin_index_main1' Removed //

// *************************************
// Administrator Notes

print_form_header('index', 'notes');
print_table_header($vbphrase['administrator_notes'], 1);
print_description_row("<textarea name=\"notes\" style=\"width: 90%\" rows=\"9\" tabindex=\"1\">" . $vbulletin->userinfo['notes'] . "</textarea>", false, 1, '', 'center');
print_submit_row($vbphrase['save'], 0, 1);

// Legacy Hook 'admin_index_main2' Removed //

// *************************************
// QUICK ADMIN LINKS

print_table_start();
print_table_header($vbphrase['quick_administrator_links']);

$datecut = vB::getRequest()->getTimeNow() - $vbulletin->options['cookietimeout'];
$guestsarry = $assertor->getRow('session', array(
	vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_COUNT,
	vB_dB_Query::CONDITIONS_KEY => array(
		array('field' => 'userid', 'value' => 0, vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_EQ),
		array('field' => 'lastactivity', 'value' => $datecut, vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_GT)
	)
));

$membersarry = $assertor->getRows('getUserSessionsCount', array('datecut' => $datecut));
$guests = intval($guestsarry['count']);
$members = count($membersarry);

// ### MAX LOGGEDIN USERS ################################
if (intval($vbulletin->maxloggedin['maxonline']) <= ($guests + $members))
{
	$vbulletin->maxloggedin['maxonline'] = $guests + $members;
	$vbulletin->maxloggedin['maxonlinedate'] = TIMENOW;
	build_datastore('maxloggedin', serialize($vbulletin->maxloggedin), 1);
}

$is_windows = (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN');
$loadavg = array();

if (!$is_windows AND function_exists('exec') AND $stats = @exec('uptime 2>&1') AND trim($stats) != '' AND preg_match('#: ([\d.,]+),?\s+([\d.,]+),?\s+([\d.,]+)$#', $stats, $regs))
{
	$loadavg[0] = vb_number_format($regs[1], 2);
	$loadavg[1] = vb_number_format($regs[2], 2);
	$loadavg[2] = vb_number_format($regs[3], 2);
}
else if (!$is_windows AND @file_exists('/proc/loadavg') AND $stats = @file_get_contents('/proc/loadavg') AND trim($stats) != '')
{
	$loadavg = explode(' ', $stats);
	$loadavg[0] = vb_number_format($loadavg[0], 2);
	$loadavg[1] = vb_number_format($loadavg[1], 2);
	$loadavg[2] = vb_number_format($loadavg[2], 2);
}

if (!empty($loadavg))
{
	print_label_row($vbphrase['server_load_averages'], "$loadavg[0]&nbsp;&nbsp;$loadavg[1]&nbsp;&nbsp;$loadavg[2] | " . construct_phrase($vbphrase['users_online_x_members_y_guests'], vb_number_format($guests + $members), vb_number_format($members), vb_number_format($guests)), '', 'top', NULL, false);
}
else
{
	print_label_row($vbphrase['users_online'], construct_phrase($vbphrase['x_y_members_z_guests'], vb_number_format($guests + $members), vb_number_format($members), vb_number_format($guests)), '', 'top', NULL, false);
}

if (can_administer('canadminusers'))
{
	print_label_row($vbphrase['quick_user_finder'], '
		<form action="user.php?do=find" method="post" style="display:inline">
		<input type="hidden" name="s" value="' . vB::getCurrentSession()->get('sessionhash') . '" />
		<input type="hidden" name="adminhash" value="' . ADMINHASH . '" />
		<input type="hidden" name="do" value="find" />
		<input type="text" class="bginput" name="user[username]" size="30" tabindex="1" />
		<input type="submit" value=" ' . $vbphrase['find'] . ' " class="button" tabindex="1" />
		<input type="submit" class="button" value="' . $vbphrase['exact_match'] . '" tabindex="1" name="user[exact]" />
		</form>
		', '', 'top', NULL, false
	);
}

print_label_row($vbphrase['quick_phrase_finder'], '
	<form action="phrase.php?do=dosearch" method="post" style="display:inline">
	<input type="text" class="bginput" name="searchstring" size="30" tabindex="1" />
	<input type="submit" value=" ' . $vbphrase['find'] . ' " class="button" tabindex="1" />
	<input type="hidden" name="do" value="dosearch" />
	<input type="hidden" name="languageid" value="-10" />
	<input type="hidden" name="searchwhere" value="10" />
	<input type="hidden" name="adminhash" value="' . ADMINHASH . '" />
	</form>
	', '', 'top', NULL, false
);

print_label_row($vbphrase['php_function_lookup'], '
	<form action="http://www.ph' . 'p.net/manual-lookup.ph' . 'p" method="get" style="display:inline">
	<input type="text" class="bginput" name="function" size="30" tabindex="1" />
	<input type="submit" value=" ' . $vbphrase['find'] . ' " class="button" tabindex="1" />
	</form>
	', '', 'top', NULL, false
);
print_label_row($vbphrase['mysql_language_lookup'], '
	<form action="http://www.mysql.com/search/" method="get" style="display:inline">
	<input type="hidden" name="doc" value="1" />
	<input type="hidden" name="m" value="o" />
	<input type="text" class="bginput" name="q" size="30" tabindex="1" />
	<input type="submit" value=" ' . $vbphrase['find'] . ' " class="button" tabindex="1" />
	</form>
	', '', 'top', NULL, false
);
print_label_row($vbphrase['useful_links'], '
	<form style="display:inline">
	<select onchange="if (this.options[this.selectedIndex].value != \'\') { window.open(this.options[this.selectedIndex].value); } return false;" tabindex="1" class="bginput">
		<option value="">-- ' . $vbphrase['useful_links'] . ' --</option>' . construct_select_options(array(
			'PHP' => array(
				'http://www.ph' . 'p.net/' => $vbphrase['home_page_gcpglobal'] . ' (PHP.net)',
				'http://www.ph' . 'p.net/manual/' => $vbphrase['reference_manual'],
				'http://www.ph' . 'p.net/downloads.ph' . 'p' => $vbphrase['download_latest_version']
			),
			'MySQL' => array(
				'http://www.mysql.com/' => $vbphrase['home_page_gcpglobal'] . ' (MySQL.com)',
				'http://www.mysql.com/documentation/' => $vbphrase['reference_manual'],
				'http://www.mysql.com/downloads/' => $vbphrase['download_latest_version'],
			),
			'Apache' => array(
				'http://httpd.apache.org/' => $vbphrase['home_page_gcpglobal'] . ' (Apache.org)',
				'http://httpd.apache.org/docs/' => $vbphrase['reference_manual'],
				'http://httpd.apache.org/download.cgi' => $vbphrase['download_latest_version'],
			),
	)) . '</select>
	</form>
	', '', 'top', NULL, false
);
print_table_footer(2, '', '', false);

// Legacy Hook 'admin_index_main3' Removed //

// *************************************
// vBULLETIN CREDITS
require_once(DIR . '/includes/vbulletin_credits.php');

?>

<p class="smallfont" align="center">
<!--<?php echo construct_phrase($vbphrase['vbulletin_copyright'], $vbulletin->options['templateversion'], date('Y')); ?><br />-->
</p>

<?php

unset($DEVDEBUG);


print_cp_footer();

}

// ################################ SHOW PHP INFO #############################

if ($_REQUEST['do'] == 'phpinfo')
{
	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}
	else
	{
		phpinfo();
		exit;
	}
}

// ################################ HANDLE ADMIN MESSAGES #############################
if ($_POST['do'] == 'handlemessage')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'address' => vB_Cleaner::TYPE_ARRAY_KEYS_INT,
		'dismiss' => vB_Cleaner::TYPE_ARRAY_KEYS_INT,
		'acpnews' => vB_Cleaner::TYPE_ARRAY_KEYS_INT
	));

	print_cp_header($vbphrase['welcome_to_the_vbulletin_admin_control_panel']);

	if ($vbulletin->GPC['address'])
	{
		// chosen to address the issue -- redirect to the appropriate page
		$adminmessageid = intval($vbulletin->GPC['address'][0]);
		$adminmessage = vB::getDbAssertor()->getRow('adminmessage', array('adminmessageid' => $adminmessageid));

		if (!empty($adminmessage))
		{
			// set the issue as addressed
			vB::getDbAssertor()->update(
					'adminmessage',
					array('status' => 'done', 'statususerid' => $vbulletin->userinfo['userid']),
					array('adminmessageid' => $adminmessageid)
			);
		}

		if (!empty($adminmessage) AND !empty($adminmessage['execurl']))
		{
			if ($adminmessage['method'] == 'get')
			{
				// get redirect -- can use the url basically as is
				if (!strpos($adminmessage['execurl'], '?'))
				{
					$adminmessage['execurl'] .= '?';
				}
				$args = array();

				$execurl =  vB_String::parseUrl($adminmessage['execurl'] . vB::getCurrentSession()->get('sessionurl_js'));
				$pathinfo = pathinfo($execurl['path']);
				$file = $pathinfo['basename'];
				parse_str($execurl['query'], $args);
				print_cp_redirect2($file, $args);
			}
			else
			{
				// post redirect -- need to seperate into <file>?<querystring> first
				if (preg_match('#^(.+)\?(.*)$#siU', $adminmessage['execurl'], $match))
				{
					$script = $match[1];
					$arguments = explode('&', $match[2]);
				}
				else
				{
					$script = $adminmessage['execurl'];
					$arguments = array();
				}

				echo '
					<form action="' . htmlspecialchars($script) . '" method="post" id="postform">
				';

				foreach ($arguments AS $argument)
				{
					// now take each element in the query string into <name>=<value>
					// and stuff it into hidden form elements
					if (preg_match('#^(.*)=(.*)$#siU', $argument, $match))
					{
						$name = $match[1];
						$value = $match[2];
					}
					else
					{
						$name = $argument;
						$value = '';
					}
					echo '
						<input type="hidden" name="' . htmlspecialchars(urldecode($name)) . '" value="' . htmlspecialchars(urldecode($value)) . '" />
					';
				}

				// and submit the form automatically
				echo '
					</form>
					<script type="text/javascript">
					<!--
					fetch_object(\'postform\').submit();
					// -->
					</script>
				';
			}

			print_cp_footer();
		}
	}
	else if ($vbulletin->GPC['dismiss'])
	{
		$adminmessageid = intval($vbulletin->GPC['dismiss'][0]);

		vB::getDbAssertor()->update('adminmessage', array('status' => 'dismissed'), array('adminmessageid' => $adminmessageid));
	}
	$args = array();
	parse_str(vB::getCurrentSession()->get('sessionurl_js'),$args);
	$args['do'] = 'home';
	print_cp_redirect2('index', $args);
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 70525 $
|| ####################################################################
\*======================================================================*/
?>
