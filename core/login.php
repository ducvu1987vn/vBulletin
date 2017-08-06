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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'login');
//define('CSRF_PROTECTION', true);
define('CSRF_SKIP_LIST', 'login');
define('CONTENT_PAGE', false);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
global $phrasegroups, $specialtemplates, $globaltemplates, $actiontemplates, $show;
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array(
	'lostpw' => array(
		'lostpw',
		'humanverify'
	)
);

// ######################### REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/functions_login.php');

global $vbulletin, $vbphrase;
// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$vbulletin->input->clean_gpc('r', 'a', vB_Cleaner::TYPE_STR);

if (empty($_REQUEST['do']) AND empty($vbulletin->GPC['a']))
{
	exec_header_redirect(vB5_Route::buildUrl('home|nosession|fullurl'));
}

// ############################### start logout ###############################
if ($_REQUEST['do'] == 'logout')
{
	// process facebook logout first if applicable
	if (is_facebookenabled())
	{
		do_facebooklogout();
	}

	define('NOPMPOPUP', true);

	if (!VB_API)
	{
		$vbulletin->input->clean_gpc('r', 'logouthash', vB_Cleaner::TYPE_STR);

		if ($vbulletin->userinfo['userid'] != 0 AND !verify_security_token($vbulletin->GPC['logouthash'], $vbulletin->userinfo['securitytoken_raw']))
		{
			eval(standard_error(fetch_error('logout_error', vB::getCurrentSession()->get('sessionurl'), $vbulletin->userinfo['securitytoken'])));
		}
	}

	process_logout();

	$vbulletin->url = fetch_replaced_session_url($vbulletin->url);
	$forumHome = vB_Library::instance('content_channel')->getForumHomeChannel();
	if (strpos($vbulletin->url, 'do=logout') !== false)
	{
		$vbulletin->url = vB5_Route::buildUrl($forumHome['routeid'] . '|nosession|fullurl');
	}
	$show['member'] = false;

	eval(standard_error(fetch_error('cookieclear', create_full_url($vbulletin->url),  vB5_Route::buildUrl($forumHome['routeid'] . '|nosession|fullurl')), '', false));
}

// ############################### start do login ###############################
// this was a _REQUEST action but where do we all login via request?
if ($_POST['do'] == 'login')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'vb_login_username'        => vB_Cleaner::TYPE_STR,
		'vb_login_password'        => vB_Cleaner::TYPE_STR,
		'vb_login_md5password'     => vB_Cleaner::TYPE_STR,
		'vb_login_md5password_utf' => vB_Cleaner::TYPE_STR,
		'postvars'                 => vB_Cleaner::TYPE_BINARY,
		'cookieuser'               => vB_Cleaner::TYPE_BOOL,
		'logintype'                => vB_Cleaner::TYPE_STR,
		'cssprefs'                 => vB_Cleaner::TYPE_STR,
		'inlineverify'             => vB_Cleaner::TYPE_BOOL,
	));

	// TODO: This is a temp fix for VBV-3475
	function admin_login_error($error, array $args = array())
	{
		global $vbulletin;
		if ($vbulletin->GPC['logintype'] === 'cplogin' OR $vbulletin->GPC['logintype'] === 'modcplogin')
		{
			require_once(DIR . '/includes/adminfunctions.php');

			$url = unhtmlspecialchars($vbulletin->url);

			$urlarr = vB_String::parseUrl($url);

			$urlquery = $urlarr['query'];

			$oldargs = array();
			if ($urlquery)
			{
				parse_str($urlquery, $oldargs);
			}

			$args = array_merge($oldargs, $args);

			unset($args['loginerror']);

			$argstr = http_build_query($args);

			$url = "/$urlarr[path]?loginerror=" . $error;

			if ($argstr) {
				$url .= '&' . $argstr;
			}

			print_cp_redirect($url);
		}
	}

	// can the user login?
	$strikes = vB_User::verifyStrikeStatus($vbulletin->GPC['vb_login_username']);
	if ($strikes === false)
	{
		admin_login_error('strikes');
		eval(standard_error(fetch_error('strikes', $vbulletin->options['bburl'], vB::getCurrentSession()->get('sessionurl'))));
	}

	if ($vbulletin->GPC['vb_login_username'] == '')
	{
		admin_login_error('badlogin', array('strikes' => $strikes));
		eval(standard_error(fetch_error('badlogin', $vbulletin->options['bburl'], vB::getCurrentSession()->get('sessionurl'), $strikes)));
	}

	// WE DON'T NEED THIS ANYMORE, AS verify_authentication WILL MODIFY vbulletin->userinfo ONLY IF IT PASSES THE CHECK
//	// make sure our user info stays as whoever we were (for example, we might be logged in via cookies already)
//	$original_userinfo = $vbulletin->userinfo;

	$auth = vB_User::verifyAuthentication($vbulletin->GPC['vb_login_username'], $vbulletin->GPC['vb_login_password'], $vbulletin->GPC['vb_login_md5password'], $vbulletin->GPC['vb_login_md5password_utf']);
	if (!$auth)
	{
		// Legacy Hook 'login_failure' Removed //

		// check password
		vB_User::execStrikeUser($vbulletin->userinfo['username']);

		if ($vbulletin->GPC['logintype'] === 'cplogin' OR $vbulletin->GPC['logintype'] === 'modcplogin')
		{
			// log this error if attempting to access the control panel
			require_once(DIR . '/includes/functions_log_error.php');
			log_vbulletin_error($vbulletin->GPC['vb_login_username'], 'security');
		}
//		$vbulletin->userinfo = $original_userinfo;

		// For vB_API we need to unlogin the users we logged in before
		if (defined('VB_API') AND VB_API === true)
		{
			$vbulletin->session->set('userid', 0);
			$vbulletin->session->set('loggedin', 0);
		}

		if ($vbulletin->GPC['inlineverify'] AND $vbulletin->userinfo)
		{
			require_once(DIR . '/includes/modfunctions.php');
			show_inline_mod_login(true);
		}
		else
		{
			define('VB_ERROR_PERMISSION', true);
			$show['useurl'] = true;
			$show['specificerror'] = true;
			$url = $vbulletin->url;
			if ($vbulletin->options['usestrikesystem'])
			{
				admin_login_error('badlogin_strikes_passthru', array('strikes' => $strikes + 1));
				eval(standard_error(fetch_error('badlogin_strikes_passthru', vB5_Route::buildUrl('lostpw|nosession|fullurl'), $strikes + 1)));
			}
			else
			{
				admin_login_error('badlogin_passthru', array('strikes' => $strikes + 1));
				eval(standard_error(fetch_error('badlogin_passthru', vB5_Route::buildUrl('lostpw|nosession|fullurl'), $strikes + 1)));
			}
		}
	}

	vB_User::execUnstrikeUser($vbulletin->GPC['vb_login_username']);

	// create new session
	$res = vB_User::processNewLogin($auth, $vbulletin->GPC['logintype'], $vbulletin->GPC['cssprefs']);

	// set cookies (temp hack for admincp)
	if (isset($res['cpsession']))
	{
		vbsetcookie('cpsession', $res['cpsession'], false, true, true);
	}
	vbsetcookie('userid', $res['userid'], false, true, true);
	vbsetcookie('password', $res['password'], false, true, true);
	vbsetcookie('sessionhash', $res['sessionhash'], false, false, true);

	// do redirect
	do_login_redirect();

}
else if ($_GET['do'] == 'login')
{
	// add consistency with previous behavior
	exec_header_redirect(vB5_Route::buildUrl('home|nosession|fullurl'));
}

// ############################### start lost password ###############################
if ($_REQUEST['do'] == 'lostpw')
{
	$vbulletin->input->clean_gpc('r', 'email', vB_Cleaner::TYPE_NOHTML);
	$email = $vbulletin->GPC['email'];

	if ($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview'])
	{
		$navbits = construct_navbits(array('' => $vbphrase['lost_password_recovery_form']));
		$navbar = render_navbar_template($navbits);
	}
	else
	{
		$navbar = '';
	}

	// human verification
	if (fetch_require_hvcheck('lostpw'))
	{
		require_once(DIR . '/includes/class_humanverify.php');
		$verification =& vB_HumanVerify::fetch_library($vbulletin);
		$human_verify = $verification->output_token();
	}
	else
	{
		$human_verify = '';
	}

	$url =& $vbulletin->url;
	$templater = vB_Template::create('lostpw');
		$templater->register_page_templates();
		$templater->register('email', $email);
		$templater->register('human_verify', $human_verify);
		$templater->register('navbar', $navbar);
		$templater->register('url', $url);
	print_output($templater->render());
}

// ############################### start email password ###############################
if ($_POST['do'] == 'emailpassword')
{

	$vbulletin->input->clean_array_gpc('p', array(
		'email' => vB_Cleaner::TYPE_STR,
		'userid' => vB_Cleaner::TYPE_UINT,
		'humanverify'  => vB_Cleaner::TYPE_ARRAY,
	));

	if ($vbulletin->GPC['email'] == '')
	{
		eval(standard_error(fetch_error('invalidemail')));
	}

	if (fetch_require_hvcheck('lostpw'))
	{
		require_once(DIR . '/includes/class_humanverify.php');
		$verify =& vB_HumanVerify::fetch_library($vbulletin);
		if (!$verify->verify_token($vbulletin->GPC['humanverify']))
		{
	  		standard_error(fetch_error($verify->fetch_error()));
	  	}
	}

	require_once(DIR . '/includes/functions_user.php');

	$users = $db->query_read_slave("
		SELECT userid, username, email, languageid
		FROM " . TABLE_PREFIX . "user
		WHERE email = '" . $db->escape_string($vbulletin->GPC['email']) . "'
	");
	if ($db->num_rows($users))
	{
		while ($user = $db->fetch_array($users))
		{
			if ($vbulletin->GPC['userid'] AND $vbulletin->GPC['userid'] != $user['userid'])
			{
				continue;
			}
			$user['username'] = unhtmlspecialchars($user['username']);

			$user['activationid'] = build_user_activation_id($user['userid'], 2, 1);

			eval(fetch_email_phrases('lostpw', $user['languageid']));
			vbmail($user['email'], $subject, $message, true);
		}

		$vbulletin->url = str_replace('"', '', $vbulletin->url);
		eval(print_standard_redirect('redirect_lostpw', true, true));
	}
	else
	{
		eval(standard_error(fetch_error('invalidemail')));
	}
}

// ############################### start reset password ###############################
if ($vbulletin->GPC['a'] == 'pwd' OR $_REQUEST['do'] == 'resetpassword')
{

	$vbulletin->input->clean_array_gpc('r', array(
		'userid'       => vB_Cleaner::TYPE_UINT,
		'u'            => vB_Cleaner::TYPE_UINT,
		'activationid' => vB_Cleaner::TYPE_STR,
		'i'            => vB_Cleaner::TYPE_STR
	));

	if (!$vbulletin->GPC['userid'])
	{
		$vbulletin->GPC['userid'] = $vbulletin->GPC['u'];
	}

	if (!$vbulletin->GPC['activationid'])
	{
		$vbulletin->GPC['activationid'] = $vbulletin->GPC['i'];
	}

	$userinfo = verify_id('user', $vbulletin->GPC['userid'], 1, 1);

//	$user = $db->query_first("
//		SELECT activationid, dateline
//		FROM " . TABLE_PREFIX . "useractivation
//		WHERE type = 1
//			AND userid = $userinfo[userid]
//	");
	$user = vB::getDbAssertor()->getRow('useractivation', array(
		vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
		vB_dB_Query::COLUMNS_KEY => array('activationid', 'dateline'),
		vB_dB_Query::CONDITIONS_KEY => array('type' => 1, 'userid' => $userinfo['userid'])
	));

	if (!$user)
	{
		// no activation record, probably got back here after a successful request, back to home
		exec_header_redirect(vB5_Route::buildUrl('home|nosession|fullurl'));
	}

	if ($user['dateline'] < (TIMENOW - 24 * 60 * 60))
	{  // is it older than 24 hours?
		eval(standard_error(fetch_error('resetexpired', vB5_Route::buildUrl('lostpw|nosession|fullurl'))));
	}

	if ($user['activationid'] != $vbulletin->GPC['activationid'])
	{ //wrong act id
		eval(standard_error(fetch_error('resetbadid', vB5_Route::buildUrl('lostpw|nosession|fullurl'))));
	}

	// delete old activation id
//	$db->query_write("DELETE FROM " . TABLE_PREFIX . "useractivation WHERE userid = $userinfo[userid] AND type = 1");
	vB::getDbAssertor()->delete('useractivation', array('userid' => $userinfo['userid'], 'type' => 1));

	$newpassword = fetch_random_password(8);

	// init user data manager
	$userdata = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_STANDARD);
	$userdata->set_existing($userinfo);
	$userdata->set('password', $newpassword);
	$userdata->save();

	// Legacy Hook 'reset_password' Removed //

	$accountPage = vB5_Route::buildUrl('settings|nosession|fullurl', array('tab' => 'account'));
	$maildata = vB_Api::instanceInternal('phrase')
		->fetchEmailPhrases('resetpw', array($userinfo['username'], $newpassword, $accountPage , $vbulletin->options['bbtitle']), array($vbulletin->options['bbtitle']), $userinfo['languageid']);
	vB_Mail::vbmail($userinfo['email'], $maildata['subject'], $maildata['message'], true);

	eval(standard_error(fetch_error('resetpw_gerror', $accountPage)));

}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 70525 $
|| ####################################################################
\*======================================================================*/
?>
