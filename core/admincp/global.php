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

global $vbphrase, $phrasegroups, $vbulletin, $specialtemplates;

// identify where we are
define('VB_AREA', 'AdminCP');
define('VB_ENTRY', 1);
define('IN_CONTROL_PANEL', true);

if (!isset($phrasegroups) OR !is_array($phrasegroups))
{
	$phrasegroups = array('global');
}
if (!in_array('global', $phrasegroups))
{
	$phrasegroups[] = 'global';
}
$phrasegroups[] = 'cpglobal';

if (!isset($specialtemplates) OR !is_array($specialtemplates))
{
	$specialtemplates = array('mailqueue');
}

// ###################### Start functions #######################
chdir('./../');
define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));
if (!defined('VB_API'))
{
	define('VB_API', false);
}
require_once(CWD . '/includes/init.php');
require_once(DIR . '/includes/adminfunctions.php');
vB_Language::preloadPhraseGroups($phrasegroups);
//Force load of the user information
vB::getCurrentSession()->loadPhraseGroups();

$vb5_config =& vB::getConfig();
$assertor = vB::getDbAssertor();

// ###################### Start headers (send no-cache) #######################
exec_nocache_headers();

if ($vbulletin->userinfo['cssprefs'] != '')
{
	$vbulletin->options['cpstylefolder'] = $vbulletin->userinfo['cssprefs'];
}

# cache full permissions so scheduled tasks will have access to them
$permissions = cache_permissions($vbulletin->userinfo);
$vbulletin->userinfo['permissions'] =& $permissions;

if (
		// this checks for superadmins, basic admin control and administrator table
		// administrator table has adminpermissions = 0 ?!?
		!vB::getUserContext()->hasAdminPermission('cancontrolpanel') AND
		// this checks for datastore
		!vB::getUserContext()->hasPermission('adminpermissions', 'cancontrolpanel')
	)
{
	$checkpwd = 1;
}

// ###################### Get date / time info #######################
// override date/time settings if specified
fetch_options_overrides($vbulletin->userinfo);
fetch_time_data();

// ############################################ LANGUAGE STUFF ####################################
// initialize $vbphrase and set language constants
$vbphrase = init_language();
if ($stylestuff = $assertor->getRow('vBForum:style', array('styleid' => $vbulletin->options['styleid']), array()))
{
	fetch_stylevars($stylestuff, $vbulletin->userinfo);
}
else
{
	$_tmp = NULL;
	fetch_stylevars($_tmp, $vbulletin->userinfo);
}

// ############################################ Check for files existance ####################################
if (empty($vb5_config['Misc']['debug']) and !defined('BYPASS_FILE_CHECK'))
{
	// check for files existance. Potential security risks!
	$continue = false;
	if (file_exists(DIR . '/install/install.php') == true)
	{
		if ($_SERVER['REQUEST_METHOD'] == 'GET')
		{
			$continue = $vbulletin->scriptpath;
		}
		print_stop_message2(array('security_alert_x_still_exists',  'install.php'));
	}
	else if (file_exists(DIR . '/install/makeconfig.php') == true)
	{
		if ($_SERVER['REQUEST_METHOD'] == 'GET')
		{
			$continue = $vbulletin->scriptpath;
		}
		print_stop_message2(array('security_alert_x_still_exists',  'makeconfig.php'));
	}
	else if (file_exists(DIR . '/install/tools.php'))
	{
		if ($_SERVER['REQUEST_METHOD'] == 'GET')
		{
			$continue = $vbulletin->scriptpath;
		}
		print_stop_message2(array('security_alert_tools_still_exists_in_x',  'install'));
	}
	else if (file_exists(DIR . '/' . $vb5_config['Misc']['admincpdir'] . '/tools.php'))
	{
		if ($_SERVER['REQUEST_METHOD'] == 'GET')
		{
			$continue = $vbulletin->scriptpath;
		}
		print_stop_message2(array('security_alert_tools_still_exists_in_x',  $vb5_config['Misc']['admincpdir']));
	}
	else if (file_exists(DIR . '/' . $vb5_config['Misc']['modcpdir'] . '/tools.php'))
	{
		if ($_SERVER['REQUEST_METHOD'] == 'GET')
		{
			$continue = $vbulletin->scriptpath;
		}
		print_stop_message2(array('security_alert_tools_still_exists_in_x',  $vb5_config['Misc']['modcpdir']),NULL,array(),NULL, $continue);
	}
}

// ############################################ Start Login Check ####################################
$vbulletin->input->clean_array_gpc('p', array(
	'adminhash' => vB_Cleaner::TYPE_STR,
	'ajax'      => vB_Cleaner::TYPE_BOOL,
));

assert_cp_sessionhash();

if (!CP_SESSIONHASH OR $checkpwd OR ($vbulletin->options['timeoutcontrolpanel'] AND !vB::getCurrentSession()->get('loggedin')))
{
	// #############################################################################
	// Put in some auto-repair ;)
	$check = array();

	$spectemps = $assertor->getRows('datastore', array());
	foreach ($spectemps AS $spectemp)
	{
		$check["$spectemp[title]"] = true;
	}

	if (!$check['maxloggedin'])
	{
		build_datastore('maxloggedin', '', 1);
	}
	if (!$check['smiliecache'])
	{
		build_datastore('smiliecache', '', 1);
		build_image_cache('smilie');
	}
	if (!$check['iconcache'])
	{
		build_datastore('iconcache', '', 1);
		build_image_cache('icon');
	}
	if (!$check['bbcodecache'])
	{
		build_datastore('bbcodecache', '', 1);
		build_bbcode_cache();
	}
	if (!$check['ranks'])
	{
		require_once(DIR . '/includes/functions_ranks.php');
		build_ranks();
	}
	if (!$check['userstats'])
	{
		build_datastore('userstats', '', 1);
		require_once(DIR . '/includes/functions_databuild.php');
		build_user_statistics();
	}
	if (!$check['mailqueue'])
	{
		build_datastore('mailqueue');
	}
	if (!$check['cron'])
	{
		build_datastore('cron');
	}
	if (!$check['attachmentcache'])
	{
		build_datastore('attachmentcache', '', 1);
	}
	if (!$check['wol_spiders'])
	{
		build_datastore('wol_spiders', '', 1);
	}
	if (!$check['banemail'])
	{
		vB::getDatastore()->build('banemail', $settings['banemail']);
	}
	if (!$check['stylecache'])
	{
		vB_Library::instance('Style')->buildStyleDatastore();
	}
	if (!$check['usergroupcache'] OR !$check['forumcache'])
	{
		build_channel_permissions();
	}
	if (!$check['noticecache'])
	{
		build_datastore('noticecache', '', 1);
	}
	if (!$check['loadcache'])
	{
		update_loadavg();
	}
	if (!$check['prefixcache'])
	{
		require_once(DIR . '/includes/adminfunctions_prefix.php');
		build_prefix_datastore();
	}

	// Legacy Hook 'admin_global_datastore_check' Removed //

	// end auto-repair
	// #############################################################################

	print_cp_login();
}
else if ($_POST['do'] AND ADMINHASH != $vbulletin->GPC['adminhash'])
{
	print_cp_login(true);
}

if (file_exists(DIR . '/includes/version_vbulletin.php'))
{
	include_once(DIR . '/includes/version_vbulletin.php');
}

if (defined('FILE_VERSION_VBULLETIN') AND FILE_VERSION_VBULLETIN !== '')
{
	define('ADMIN_VERSION_VBULLETIN', FILE_VERSION_VBULLETIN);
}
else
{
	define('ADMIN_VERSION_VBULLETIN', $vbulletin->options['templateversion']);
}

// Legacy Hook 'admin_global' Removed //

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 70422 $
|| ####################################################################
\*======================================================================*/
