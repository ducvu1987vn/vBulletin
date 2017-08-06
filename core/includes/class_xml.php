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

// Attempt to load XML extension if we don't have the XML functions
// already loaded.
if (!function_exists('xml_set_element_handler'))
{
	$extension_dir = ini_get('extension_dir');
	if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
	{
		$extension_file = 'php_xml.dll';
	}
	else
	{
		$extension_file = 'xml.so';
	}
	if ($extension_dir AND file_exists($extension_dir . '/' . $extension_file))
	{
		dl($extension_file);
	}
}

if (!function_exists('ini_size_to_bytes') OR (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < 256 * 1024 * 1024 AND $current_memory_limit > 0))
{
	@ini_set('memory_limit', 256 * 1024 * 1024);
}

// #############################################################################
// legacy stuff

class XMLparser extends vB_XML_Parser
{
}
class XMLexporter extends vB_XML_Builder
{
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 71084 $
|| ####################################################################
\*======================================================================*/
