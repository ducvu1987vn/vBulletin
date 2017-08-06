<?php if (!defined('VB_ENTRY')) die('Access denied.');
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

/**
 * This is the MySQL-Specific version of the Create Table-type queries processor
 *
 * @package vBDatabase
 * @version $Revision: 28823 $
 */
class vB_dB_Query_Create_MYSQL extends vB_dB_Query_Create
{
	/*Properties====================================================================*/

	protected $db_type = 'MYSQL';
}

/*======================================================================*\
|| ####################################################################
|| # SVN=> $Revision=> 28823 $
|| ####################################################################
\*======================================================================*/