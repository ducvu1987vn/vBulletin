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

if (!defined('VB_AREA') AND !defined('THIS_SCRIPT'))
{
	echo 'VB_AREA and THIS_SCRIPT must be defined to continue';
	exit;
}


// start the page generation timer
define('TIMESTART', microtime(true));

// set the current unix timestamp
define('TIMENOW', time());

// Define safe_mode
define('SAFEMODE', (@ini_get('safe_mode') == 1 OR strtolower(@ini_get('safe_mode')) == 'on') ? true : false);

// #############################################################################
// fetch the core includes


if (!class_exists('vB')) {
	require_once(dirname(__FILE__) . '/../vb/vb.php');
}

vB::init();
vB::setRequest(new vB_Request_Web());

require_once(CWD . '/includes/class_core.php');
set_error_handler('vb_error_handler');

// initialize the data registry
global $vbulletin;
$vbulletin = vB::get_registry();

$vb5_config =& vB::getConfig();
if ($vb5_config['Misc']['debug'])
{
	restore_error_handler();
}

$db = &$vbulletin->db;
require_once(DIR . '/includes/functions.php');

if (defined('DEMO_MODE') AND DEMO_MODE AND function_exists('vbulletin_demo_init_db'))
{
	vbulletin_demo_init_db();
}


// #############################################################################
// fetch options and other data from the datastore

//if ((!empty($specialtemplates)) AND !$vbulletin->datastore->fetch($specialtemplates))
//{
//	switch(VB_AREA)
//	{
//		case 'AdminCP':
//		case 'Archive':
//			exec_header_redirect('../install/install.php');
//			break;
//		case 'Forum':
//		default:
//			exec_header_redirect('install/install.php');
//	}
//}

if ($vbulletin->bf_ugp === null)
{
	echo '<div>vBulletin datastore error caused by one or more of the following:
		<ol>
			' . (function_exists('mmcache_get') ? '<li>Turck MMCache has been detected on your server, first try disabling Turck MMCache or replacing it with eAccelerator</li>' : '') . '
			<li>You may have uploaded vBulletin files without also running the vBulletin upgrade script. If you have not run the upgrade script, do so now.</li>
			<li>The datastore cache may have been corrupted. Run <em>Rebuild Bitfields</em> from <em>tools.php</em>, which you can upload from the <em>do_not_upload</em> folder of the vBulletin package.</li>
		</ol>
	</div>';

	trigger_error('vBulletin datastore cache incomplete or corrupt', E_USER_ERROR);
}

if (defined('VB_PRODUCT') AND (!isset($vbulletin->products[VB_PRODUCT]) OR !($vbulletin->products[VB_PRODUCT])))
{
	exec_header_redirect(vB5_Route::buildUrl('home|nosession|fullurl'), 302);
}

if ($vbulletin->options['cookietimeout'] < 60)
{
	// values less than 60 will probably break things, so prevent that
	$vbulletin->options['cookietimeout'] = 60;
}

// #############################################################################
/**
* If shutdown functions are allowed, register exec_shut_down to be run on exit.
* Disable shutdown function for IIS CGI with Gzip enabled since it just doesn't work, sometimes, unless we kill the content-length header
* Also disable for PHP4 due to the echo() timeout issue
*/
define('SAPI_NAME', php_sapi_name());
if (!defined('NOSHUTDOWNFUNC'))
{
	define('NOSHUTDOWNFUNC', true);
}

// fetch url of referring page after we have access to vboptions['forumhome']
$vbulletin->url = $vbulletin->input->fetch_url();
define('REFERRER_PASSTHRU', $vbulletin->url);

// #############################################################################
// demo mode stuff
if (defined('DEMO_MODE') AND DEMO_MODE AND function_exists('vbulletin_demo_init_page'))
{
	vbulletin_demo_init_page();
}

// $new_datastore_fetch does not require single quotes
$new_datastore_fetch = $datastore_fetch = array();

// Legacy Hook 'init_startup' Removed //

if (!empty($datastore_fetch))
{
	// Remove the single quotes that $datastore_fetch required
	foreach ($datastore_fetch AS $value)
	{
		$new_datastore_fetch[] = substr($value, 1, -1);
	}
}

$vbulletin->datastore->fetch($new_datastore_fetch);
unset($datastore_fetch, $new_datastore_fetch);

// #############################################################################
// do a callback to modify any variables that might need modifying based on HTTP input
// eg: doing a conditional redirect based on a $goto value or $vbulletin->noheader must be set
if (function_exists('exec_postvar_call_back'))
{
	exec_postvar_call_back();
}

// #############################################################################
// initialize $show variable - used for template conditionals
$show = array();

// #############################################################################
// Clean Cookie Vars
$vbulletin->input->clean_array_gpc('c', array(
	'vbulletin_collapse'              => vB_Cleaner::TYPE_STR,
	COOKIE_PREFIX . 'referrerid'      => vB_Cleaner::TYPE_UINT,
	COOKIE_PREFIX . 'userid'          => vB_Cleaner::TYPE_UINT,
	COOKIE_PREFIX . 'password'        => vB_Cleaner::TYPE_STR,
	COOKIE_PREFIX . 'lastvisit'       => vB_Cleaner::TYPE_UINT,
	COOKIE_PREFIX . 'lastactivity'    => vB_Cleaner::TYPE_UINT,
	COOKIE_PREFIX . 'threadedmode'    => vB_Cleaner::TYPE_NOHTML,
	COOKIE_PREFIX . 'sessionhash'     => vB_Cleaner::TYPE_NOHTML,
	COOKIE_PREFIX . 'userstyleid'     => vB_Cleaner::TYPE_UINT,
	COOKIE_PREFIX . 'languageid'      => vB_Cleaner::TYPE_UINT,
	COOKIE_PREFIX . 'skipmobilestyle' => vB_Cleaner::TYPE_BOOL,
));

// Setup session
if (!empty($db->explain))
{
	$db->timer_start('Session Handling');
}

$vbulletin->input->clean_array_gpc('r', array(
	's'       => vB_Cleaner::TYPE_NOHTML,
	'styleid' => vB_Cleaner::TYPE_INT,
	'langid'  => vB_Cleaner::TYPE_INT,
));

// handle session input
if (!defined('VB_API') || !VB_API)
{
	$sessionhash = (!empty($vbulletin->GPC['s']) ? $vbulletin->GPC['s'] : $vbulletin->GPC[COOKIE_PREFIX . 'sessionhash']); // override cookie
}
else
{
	$sessionhash = '';
}

// Set up user's chosen language
if ($vbulletin->GPC['langid'] AND !empty($vbulletin->languagecache["{$vbulletin->GPC['langid']}"]['userselect']))
{
	$languageid =& $vbulletin->GPC['langid'];
	vbsetcookie('languageid', $languageid);
}
else if ($vbulletin->GPC[COOKIE_PREFIX . 'languageid'] AND !empty($vbulletin->languagecache[$vbulletin->GPC[COOKIE_PREFIX . 'languageid']]['userselect']))
{
	$languageid = $vbulletin->GPC[COOKIE_PREFIX . 'languageid'];
}
else
{
	$languageid = 0;
}

// Test mobile browser
$mobile_browser = false;
$mobile_browser_advanced = false;
if (isset($vbulletin->options['mobilestyleid_advanced']) OR isset($vbulletin->options['mobilestyleid_basic']))
{
	if (stripos($_SERVER['HTTP_USER_AGENT'], 'windows') === false)
	{
		if (
			preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android)/i', strtolower($_SERVER['HTTP_USER_AGENT']))
			OR
			stripos($_SERVER['HTTP_ACCEPT'],'application/vnd.wap.xhtml+xml') !== false
			OR
			((isset($_SERVER['HTTP_X_WAP_PROFILE']) OR isset($_SERVER['HTTP_PROFILE'])))
			OR
			stripos($_SERVER['ALL_HTTP'],'OperaMini') !== false
		)
		{
			$mobile_browser = true;
		}
		// This array is big and may be bigger later on. So we move it to a second if.
		elseif (in_array(
					strtolower(substr($_SERVER['HTTP_USER_AGENT'], 0, 4)),
					array(
					'w3c ','acs-','alav','alca','amoi','audi','avan','benq','bird','blac',
					'blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno',
					'ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-',
					'maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-',
					'newt','noki','oper','palm','pana','pant','phil','play','port','prox',
					'qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar',
					'sie-','siem','smal','smar','sony','sph-','symb','t-mo','teli','tim-',
					'tosh','tsm-','upg1','upsi','vk-v','voda','wap-','wapa','wapi','wapp',
					'wapr','webc','winw','winw','xda ','xda-')
				)
			)
		{
			$mobile_browser = true;
		}
	}

	if (
		$mobile_browser
		AND
		preg_match('/(ipad|ipod|iphone|blackberry|android|pre\/|palm os|palm|hiptop|avantgo|plucker|xiino|blazer|elaine)/i', strtolower($_SERVER['HTTP_USER_AGENT']))
	)
	{
		$mobile_browser_advanced = true;
	}
}

// Set up user's chosen style
if ($vbulletin->GPC['styleid'])
{
	$styleid =& $vbulletin->GPC['styleid'];
	vbsetcookie('userstyleid', $styleid);
	if ($styleid == -1)
	{
		vbsetcookie('skipmobilestyle', 1);
		$vbulletin->GPC[COOKIE_PREFIX . 'skipmobilestyle'] = 1;
	}
	elseif ((isset($vbulletin->options['mobilestyleid_advanced']) AND $styleid == $vbulletin->options['mobilestyleid_advanced']) OR
			(isset($vbulletin->options['mobilestyleid_basic']) AND $styleid == $vbulletin->options['mobilestyleid_basic']))
	{
		vbsetcookie('skipmobilestyle', 0);
		$vbulletin->GPC[COOKIE_PREFIX . 'skipmobilestyle'] = 0;
	}
}
elseif ($mobile_browser_advanced && $vbulletin->options['mobilestyleid_advanced'] && !$vbulletin->GPC[COOKIE_PREFIX . 'skipmobilestyle'])
{
	$styleid = $vbulletin->options['mobilestyleid_advanced'];
}
elseif ($mobile_browser && $vbulletin->options['mobilestyleid_basic'] && !$vbulletin->GPC[COOKIE_PREFIX . 'skipmobilestyle'])
{
	$styleid = $vbulletin->options['mobilestyleid_basic'];
}
elseif ($vbulletin->GPC[COOKIE_PREFIX . 'userstyleid'])
{
	$styleid = $vbulletin->GPC[COOKIE_PREFIX . 'userstyleid'];
}
else
{
	$styleid = 0;
}

$session = vB_Session::getNewSession(vB::getDbAssertor(), vB::getDatastore(), vB::getConfig(), $sessionhash, $vbulletin->GPC[COOKIE_PREFIX . 'userid'], $vbulletin->GPC[COOKIE_PREFIX . 'password'], $styleid, $languageid);
vB::setCurrentSession($session);

// conditional used in templates to hide things from search engines.
$show['search_engine'] = (preg_match("#(google|msnbot|yahoo! slurp)#si", $_SERVER['HTTP_USER_AGENT']));

// Hide sessionid in url if we are a search engine or if we have a cookie
$vbulletin->session->set_session_visibility($show['search_engine'] AND !VB_API);
$vbulletin->session->doLastVisitUpdate($vbulletin->GPC[COOKIE_PREFIX . 'lastvisit'], $vbulletin->GPC[COOKIE_PREFIX . 'lastactivity']);

// Because of Signature Verification, VB API won't need to verify securitytoken
// CSRF Protection for POST requests
if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST' AND !VB_API)
{

	if (empty($_POST) AND isset($_SERVER['CONTENT_LENGTH']) AND $_SERVER['CONTENT_LENGTH'] > 0)
	{
		die('The file(s) uploaded were too large to process.');
	}

	if ($vbulletin->userinfo['userid'] > 0 AND defined('CSRF_PROTECTION') AND CSRF_PROTECTION === true)
	{
		$vbulletin->input->clean_array_gpc('p', array(
			'securitytoken' => vB_Cleaner::TYPE_STR,
		));

		if (!in_array($_POST['do'], $vbulletin->csrf_skip_list))
		{
			if (!verify_security_token($vbulletin->GPC['securitytoken'], $vbulletin->userinfo['securitytoken_raw']))
			{
				switch ($vbulletin->GPC['securitytoken'])
				{
					case '':
						define('CSRF_ERROR', 'missing');
						break;
					case 'guest':
						define('CSRF_ERROR', 'guest');
						break;
					case 'timeout':
						define('CSRF_ERROR', 'timeout');
						break;
					default:
						define('CSRF_ERROR', 'invalid');
				}
			}
		}
	}
	else if (!defined('CSRF_PROTECTION') AND !defined('SKIP_REFERRER_CHECK'))
	{
		if (VB_HTTP_HOST AND $_SERVER['HTTP_REFERER'])
		{
			$host_parts = @vB_String::parseUrl($_SERVER['HTTP_HOST']);
			$http_host_port = isset($host_parts['port']) ? intval($host_parts['port']) : 0;
			$http_host = strtolower(VB_HTTP_HOST . ((!empty($http_host_port) AND $http_host_port != '80') ? ":$http_host_port" : ''));

			$referrer_parts = @vB_String::parseUrl($_SERVER['HTTP_REFERER']);
			$ref_port = isset($referrer_parts['port']) ? intval($referrer_parts['port']) : 0;
			$ref_host = strtolower($referrer_parts['host'] . ((!empty($ref_port) AND $ref_port != '80') ? ":$ref_port" : ''));

			if ($http_host == $ref_host)
			{	/* Instant match is good enough
				no need to check anything further. */
				$pass_ref_check = true;
			}
			else
			{
				$pass_ref_check = false;
				$allowed = array('.paypal.com');
				$allowed[] = '.'.preg_replace('#^www\.#i', '', $http_host);
				$whitelist = preg_split('#\s+#', $vbulletin->options['allowedreferrers'], -1, PREG_SPLIT_NO_EMPTY); // Get whitelist
				$allowed = array_unique(is_array($whitelist) ? array_merge($allowed,$whitelist) : $allowed); // Merge and de-duplicate.

				foreach ($allowed AS $host)
				{
					$host = strtolower($host);
					if (substr($host,0,1) == '.' AND
					(preg_match('#' . preg_quote($host, '#') . '$#siU', $ref_host) OR substr($host,1) == $ref_host))
					{
						$pass_ref_check = true;
						break;
					}
				}

				unset($allowed, $whitelist);
			}

			if ($pass_ref_check == false)
			{
				die('In order to accept POST requests originating from this domain, the admin must add the domain to the whitelist.');
			}
		}
	}
}


// Google Web Accelerator can display sensitive data ignoring any headers regarding caching
// it's a good thing for guests but not for anyone else
if ($vbulletin->userinfo['userid'] > 0 AND isset($_SERVER['HTTP_X_MOZ']) AND strpos($_SERVER['HTTP_X_MOZ'], 'prefetch') !== false)
{
	if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
	{
		header('Status: 403 Forbidden');
	}
	else
	{
		header('HTTP/1.1 403 Forbidden');
	}
	die('Prefetching is not allowed due to the various privacy issues that arise.');
}

// use the session-specified style if there is one
if (vB::getCurrentSession()->get('styleid') != 0)
{
	$vbulletin->userinfo['styleid'] = vB::getCurrentSession()->get('styleid');
}

if (isset($languageid))
{
	$vbulletin->userinfo['languageid'] = $languageid;
}

if (!empty($db->explain))
{
	$db->timer_stop(false);
}

/*======================================================================*\
   || ####################################################################
   || # CVS: $RCSfile$ - $Revision: 70525 $
   || ####################################################################
   \*======================================================================*/
