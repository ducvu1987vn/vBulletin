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
error_reporting(E_ALL);

define('VB_AREA', 'Install');
define('VBINSTALL', true);
define('VB_ENTRY', 'install.php');

if (
		(!isset($_REQUEST['version']) OR in_array($_REQUEST['version'], array('', 'install'))) AND
		(!isset($_REQUEST['step']) OR $_REQUEST['step'] <= 2)
	)
{
	define('SKIPDB', true);
}

require_once('./upgrade.php');

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 39243 $
|| ####################################################################
\*======================================================================*/
