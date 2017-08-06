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

define('VB_API', true);
define('VB5_API_VERSION_START', 500);
define('VB_API_VERSION', 500);
define('VB_API_VERSION_MIN', 1);
define('CWD_API', (($getcwd = getcwd()) ? $getcwd : '.') . '/includes/api');
define('NOCOOKIES', true);
require_once('vb/vb.php');
vB::init();

$api_m = trim($_REQUEST['api_m']);

// Client ID
$api_c = intval($_REQUEST['api_c']);

// Access token
$api_s = trim($_REQUEST['api_s']);

// Request Signature Verification Prepare (Verified in vB_Session_Api)
$api_sig = trim($_REQUEST['api_sig']);
$api_version = intval($_REQUEST['api_v']);


if (empty($api_m) || ($api_version >= VB5_API_VERSION_START && !strpos($api_m, '.') && !strstr($api_m, 'api_init')))
{
	header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
	header("Connection: Close");
	die();
}
unset($_GET['']); // See VBM-835
$VB_API_PARAMS_TO_VERIFY = $_GET;
unset($VB_API_PARAMS_TO_VERIFY['api_c'], $VB_API_PARAMS_TO_VERIFY['api_v'], $VB_API_PARAMS_TO_VERIFY['api_s'], $VB_API_PARAMS_TO_VERIFY['api_sig'], $VB_API_PARAMS_TO_VERIFY['debug'], $VB_API_PARAMS_TO_VERIFY['showall'], $VB_API_PARAMS_TO_VERIFY['do'], $VB_API_PARAMS_TO_VERIFY['r']);
ksort($VB_API_PARAMS_TO_VERIFY);
$VB_API_REQUESTS = array(
	'api_m' => $api_m,
	'api_version' => $api_version,
	'api_c' => $api_c,
	'api_s' => $api_s,
	'api_sig' => $api_sig
);

$request = new vB_Request_Api();
vB::setRequest($request);
$request->createSession($VB_API_PARAMS_TO_VERIFY, $VB_API_REQUESTS);

$api_m = trim($_REQUEST['api_m']);

// API Version
if (!$api_version)
{
	$api_version = VB_API_VERSION;
}
if ($api_version < VB_API_VERSION_MIN)
{
	print_apierror('api_version_too_low', 'This server accepts API version ' . VB_API_VERSION_MIN . ' at least. The requested API version is too low.');
}
elseif ($api_version > VB_API_VERSION)
{
	print_apierror('api_version_too_high', 'This server accepts API version ' . VB_API_VERSION . ' at most. The requested API version is too high.');
}

define('VB_API_VERSION_CURRENT', $api_version);

if($api_version < VB5_API_VERSION_START || strstr("api_init", $api_m))
{
	$old_api_m = $api_m;
	define("VB4_MAPI_METHOD", $old_api_m);
	$api_m = vB_Api::map_vb4_input_to_vb5($api_m, $_REQUEST);
}

// $methodsegments[0] is the API class name
// $methodsegments[1] is the API function name
// $_REQUEST data as function named params

$methodsegments = explode(".", $api_m);

try
{
	$apiobj = vB_Api::instanceInternal(strtolower($methodsegments[0]));
	$data = $apiobj->callNamed($methodsegments[1], array_merge($_REQUEST, $_FILES));

	if (!empty($data))
	{
		if($api_version < VB5_API_VERSION_START)
		{
			vB_Api::map_vb5_output_to_vb4($old_api_m, $data);
		}
		print_apioutput($data);
	}
}
catch (Exception $e)
{
	if ($e instanceof vB_Exception_Api)
	{
		print_apierror($e->get_errors(), $e->getMessage());
	}
	else
	{
		print_apierror($e->getMessage());
	}
}

function print_apierror($errors, $debugstr = '')
{
	if (!is_array($errors))
	{
		$errors = array($errors);
	}

	$data = array();
	if($api_version < VB5_API_VERSION_START)
	{
		vB_Api::map_vb5_errors_to_vb4(VB4_MAPI_METHOD, $errors);
		$data = $errors;
		print_apioutput($data);
		return;
	}
	else
	{
		$data = array('errors' => $errors);
	}

	$vb5_config =& vB::getConfig();

	if ($debugstr AND $vb5_config['Misc']['debug'])
	{
		$data['debug'] = $debugstr;
	}

	print_apioutput($data);
}

function print_apioutput($data)
{
	global $VB_API_REQUESTS;

	// We need to convert $data charset if we're not using UTF-8
	if (vB_String::getCharset() != 'UTF-8')
	{
		$data = vB_String::toCharset($data, vB_String::getCharset(), 'UTF-8');
	}

	//If this is IE9 we need to send type "text/html".
	//Yes, we know that's not the standard.
	if (!headers_sent() AND isset($_SERVER['HTTP_USER_AGENT']) AND
		(strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false))
	{
		header('Content-type: text/plain; charset=UTF-8');
	}
	else
	{
		header('Content-type: application/json; charset=UTF-8');
	}

	// IE will cache ajax requests, and we need to prevent this - VBV-148
	header('Cache-Control: max-age=0,no-cache,no-store,post-check=0,pre-check=0');
	header('Expires: Sat, 1 Jan 2000 01:00:00 GMT');
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Pragma: no-cache");
	header("ContentEncoding: UTF8");
	$output = json_encode($data);

	$apiclient = vB::getCurrentSession()->getApiClient();
	$vboptions = vB::getDatastore()->getValue('options');

	if (!in_array($VB_API_REQUESTS['api_m'], array('user.login', 'user.logout')))
	{
		$sign = md5($output . $apiclient['apiaccesstoken'] . $apiclient['apiclientid'] . $apiclient['secret'] . $vboptions['apikey']);
		@header('Authorization: ' . $sign);
	}

	echo $output;

	exit;
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/
