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

/**
* Log errors to a file
*
* @param	string	The error message to be placed within the log
* @param	string	The type of error that occured. php, database, security, etc.
*
* @return	boolean
*/
function log_vbulletin_error($errstring, $type = 'database')
{
	global $vbulletin;
	$options = vB::getDatastore()->getValue('options');

	// do different things depending on the error log type
	switch($type)
	{
		// log PHP E_USER_ERROR, E_USER_WARNING, E_WARNING to file
		case 'php':
			if (!empty($options['errorlogphp']))
			{
				$errfile = $options['errorlogphp'];
				$errstring .= "\r\nDate: " . date('l dS \o\f F Y h:i:s A') . "\r\n";
				$errstring .= "Username: {$vbulletin->userinfo['username']}\r\n";
				$errstring .= 'IP Address: ' . IPADDRESS . "\r\n";
			}
			break;

		// log database error to file
		case 'database':
			if (!empty($options['errorlogdatabase']))
			{
				$errstring = preg_replace("#(\r\n|\r|\n)#s", "\r\n", $errstring);
				$errfile = $vbulletin->options['errorlogdatabase'];
			}
			break;

		// log admin panel login failure to file
		case 'security':
			if (!empty($options['errorlogsecurity']))
			{
				$errfile = $options['errorlogsecurity'];
				$username = $errstring;
				$errstring  = 'Failed admin logon in ' . $vbulletin->db->appname . ' ' . $vbulletin->options['templateversion'] . "\r\n\r\n";
				$errstring .= 'Date: ' . date('l dS \o\f F Y h:i:s A') . "\r\n";
				$errstring .= "Script: http://$_SERVER[HTTP_HOST]" . unhtmlspecialchars($vbulletin->scriptpath) . "\r\n";
				$errstring .= 'Referer: ' . REFERRER . "\r\n";
				$errstring .= "Username: $username\r\n";
				$errstring .= 'IP Address: ' . IPADDRESS . "\r\n";
				$errstring .= "Strikes: $GLOBALS[strikes]/5\r\n";
			}
			break;
	}


	// if no filename is specified, exit this function
	if (!isset($errfile) OR (!file_exists($errfile)) OR !($errfile = trim($errfile)) OR (defined('DEMO_MODE') AND DEMO_MODE == true))
	{
		return false;
	}

	// rotate the log file if filesize is greater than $vbulletin->options[errorlogmaxsize]
	if ($vbulletin->options['errorlogmaxsize'] != 0 AND $filesize = @filesize("$errfile.log") AND $filesize >= $vbulletin->options['errorlogmaxsize'])
	{
		@copy("$errfile.log", $errfile . TIMENOW . '.log');
		@unlink("$errfile.log");
	}

	// write the log into the appropriate file
	if ($fp = @fopen("$errfile.log", 'a+'))
	{
		@fwrite($fp, "$errstring\r\n=====================================================\r\n\r\n");
		@fclose($fp);
		return true;
	}
	else
	{
		return false;
	}
}

/**
* Performs a check to see if an error email should be sent
*
* @param	mixed	Consistent identifier identifying the error that occured
* @param	string	The type of error that occured. php, database, security, etc.
*
* @return	boolean
*/
function verify_email_vbulletin_error($error = '', $type = 'database')
{
	return true;
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 69117 $
|| ####################################################################
\*======================================================================*/
?>
