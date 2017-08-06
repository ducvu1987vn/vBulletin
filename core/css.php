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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'css');
define('CSRF_PROTECTION', true);
define('NOPMPOPUP', 1);
define('NOCOOKIES', 1);
define('NONOTICES', 1);
define('NOHEADER', 1);
define('NOSHUTDOWNFUNC', 1);
define('LOCATION_BYPASS', 1);

define('NOCHECKSTATE', 1);
define('SKIP_SESSIONCREATE', 1);

// Immediately send back the 304 Not Modified header if this css is cached, don't load global.php
if ((!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) OR !empty($_SERVER['HTTP_IF_NONE_MATCH'])))
{
	$sapi_name = php_sapi_name();
	if ($sapi_name == 'cgi' OR $sapi_name == 'cgi-fcgi')
	{
		header('Status: 304 Not Modified');
	}
	else
	{
		header('HTTP/1.1 304 Not Modified');
	}
	// remove the content-type and X-Powered headers to emulate a 304 Not Modified response as close as possible
	header('Content-Type:');
	header('X-Powered-By:');
	exit;
}

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
preg_match_all('#([a-z0-9_\-]+\.css)#i', $_REQUEST['sheet'], $matches);
if ($matches[1])
{
	foreach ($matches[1] AS $cssfile)
	{
		$globaltemplates[] = $cssfile;
	}
}
else
{
	$globaltemplates = array();
}

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (empty($matches[1]))
{
	header('Content-Type: text/css');
	echo "/* Unable to find css sheet */";
}
else
{
	$count = 0;
	$output = '';

	foreach ($matches[1] AS $template)
	{
		if ($count > 0)
		{
			$output .= "\r\n\r\n";
		}

		$templater = vB_Template::create($template);

		/* Note that the css publishing mechanism relies on the fact that
		there isn't any user specific data passed to the css templates.
		We violate this for a users profile css, because thats its reason for existing. */
		if ($template == 'css_profile.css' AND isset($_REQUEST['userid']) AND intval($_REQUEST['userid']))
		{
			$userId = (isset($_REQUEST['showusercss']) AND intval($_REQUEST['showusercss']) == 1) ? intval($_REQUEST['userid']) : false;
			$templater->register('userid', $userId);
		}

		$template = $templater->render(true, false, true);

		if ($count > 0)
		{
			$template = preg_replace("#@charset .*#i", "", $template);
		}

		$count++;
		$output .= $template;
	}

	$output = vB_String::getCssMinifiedText($output);
	if (!headers_sent() AND vB::getDatastore()->getOption('gzipoutput'))
	{
		$output = fetch_gzipped_text($output, vB::getDatastore()->getOption('gziplevel'));
	}

	header('Content-Type: text/css');
	header('Cache-control: max-age=31536000, private');
	header('Expires: ' . gmdate("D, d M Y H:i:s", TIMENOW + 31536000) . ' GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $style['dateline']) . ' GMT');
	header('Content-Length: ' . strlen($output));
	echo $output;
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 30573 $
|| ####################################################################
\*======================================================================*/
