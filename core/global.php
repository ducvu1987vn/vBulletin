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

error_reporting(E_ALL & ~E_NOTICE);

require_once(dirname(__FILE__) . '/includes/class_bootstrap.php');

define('VB_AREA', 'Forum');

global $bootstrap, $actiontemplates, $globaltemplates, $specialtemplates;
$bootstrap = new vB_Bootstrap_Forum();
$bootstrap->datastore_entries = $specialtemplates;
$bootstrap->cache_templates = vB_Bootstrap::fetch_required_template_list(
	empty($_REQUEST['do']) ? '' : $_REQUEST['do'],
	$actiontemplates, $globaltemplates
);

$bootstrap->bootstrap();

// Deprecated as of release 4.0.2, replaced by global_bootstrap_init_start
// Legacy Hook 'global_start' Removed //

$bootstrap->load_style();

// legacy code needs this
global $permissions;
$permissions = $vbulletin->userinfo['permissions'];

// Deprecated as of release 4.0.2, replaced by global_bootstrap_complete
// Legacy Hook 'global_setup_complete' Removed //

if (!empty($db->explain))
{
	$aftertime = microtime(true) - TIMESTART;
	echo "End call of global.php: $aftertime\n";
	echo "\n<hr />\n\n";
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 64161 $
|| ####################################################################
\*======================================================================*/
