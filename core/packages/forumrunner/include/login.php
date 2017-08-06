<?php
/*
 * Forum Runner
 *
 * Copyright (c) 2010-2011 to End of Time Studios, LLC
 *
 * This file may not be redistributed in whole or significant part.
 *
 * http://www.forumrunner.com
 */

function do_login()
{
	global $fr_version, $fr_platform;

	$options = vB::get_datastore()->get_value('options');

	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'username' => vB_Cleaner::TYPE_STR,
		'password' => vB_Cleaner::TYPE_STR,
		'md5_password' => vB_Cleaner::TYPE_STR,
		'fr_username' => vB_Cleaner::TYPE_STR,
		'fr_b' => vB_Cleaner::TYPE_BOOL,
		'token' => vB_Cleaner::TYPE_STR,
	));

	$navbg = null;
	if (strlen($options['forumrunner_branding_navbar_bg'])) {
		$navbg = $options['forumrunner_branding_navbar_bg'];
		if (is_iphone() && strlen($navbg) == 7) {
			$r = hexdec(substr($navbg, 1, 2));
			$g = hexdec(substr($navbg, 3, 2));
			$b = hexdec(substr($navbg, 5, 2));
			$navbg = "$r,$g,$b";
		}
	}

	$out = array(
		'v' => $fr_version,
		'p' => $fr_platform,
	);

	if ($navbg) {
		$out['navbg'] = $navbg;
	}

	if (is_iphone() && $options['forumrunner_admob_publisherid_iphone']) {
		$out['admob'] = $options['forumrunner_admob_publisherid_iphone'];
	} else if (is_android() && $options['forumrunner_admob_publisherid_android']) {
		$out['admob'] = $options['forumrunner_admob_publisherid_android'];
	}

	if ($options['forumrunner_google_analytics_id']) {
		$out['gan'] = $options['forumrunner_google_analytics_id'];
	}

	if ($options['forumrunner_enable_registration']) {
		$out['reg'] = true;
	}

	if (!$cleaned['username'] || (!$cleaned['password'] && !$cleaned['md5_password'])) {
		// This could be an attempt to see if forums require login.  Check.
		$out += array(
			'authenticated' => false,
			'requires_authentication' => requires_authentication(),
		);
	} else {
		$login = vB_Api::instance('user')->login($cleaned['username'], $cleaned['password'], '', '', 'cplogin');

		if(isset($login['errors']) and !empty($login['errors']))
		{
			$login = vB_Api::instance('user')->login($cleaned['username'], $cleaned['password'], '', '', '');
			if(isset($login['errors']) and !empty($login['errors']))
			{
				return json_error('Incorrect login.', RV_BAD_PASSWORD);
			}
		}

		if (!$options['bbactive'] && !vB::getUserContext()->hasAdminPermission('cancontrolpanel'))
		{
			vB_Api::instance('user')->processLogout();
			return json_error(strip_tags($options['bbclosedreason']), RV_BAD_PASSWORD);
		}

		if (isset($login['cpsession'])) {
			vB5_Cookie::set('cpsession', $login['cpsession'], 30);
		}

		vB5_Cookie::set('sessionhash', $login['sessionhash'], 30);
		vB5_Cookie::set('password', $login['password'], 30);
		vB5_Cookie::set('userid', $login['userid'], 30);

		if(isset($cleaned['fr_username'])) {
			fr_update_push_user($cleaned['fr_username'], $cleaned['fr_b']);
		}

		$userinfo = vB_Api::instance('user')->fetchUserInfo();

		$out += array(
			'authenticated' => true,
			'username' => $userinfo['username'],
			'cookiepath' => $options['cookiepath'],
		);
	}

	return $out;
}

function do_logout()
{
	$vbulletin = vB::get_registry();
	$userinfo = vB_Api::instance('user')->fetchUserInfo();
	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'fr_username' => vB_Cleaner::TYPE_STR,
	));

	if ($userinfo['userid'] < 1) {
		return json_error(ERR_NO_PERMISSION);
	}

	$tableinfo = $vbulletin->db->query_first("
		SHOW TABLES LIKE '" . TABLE_PREFIX . "forumrunner_push_users'
		");
	if ($tableinfo) {
		$vbulletin->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "forumrunner_push_users
			WHERE fr_username = '" . $vbulletin->db->escape_string($cleaned['fr_username']) . "' AND vb_userid = {$userinfo['userid']}
			");
	}

	vB_User::processLogout();

	//
	// Properly set cookies on logout
	// 

	$login = array();
	$session = vB::getCurrentSession();

	$login['sessionhash'] = $session->get('sessionhash');
	$login['password'] = $session->get('password');
	$login['cpsession'] = $session->get('cpsession');
	$login['userid'] = $session->get('userid');

	vB5_Cookie::set('cpsession', $login['cpsession'], 30);
	vB5_Cookie::set('sessionhash', $login['sessionhash'], 30);
	vB5_Cookie::set('password', $login['password'], 30);
	vB5_Cookie::set('userid', $login['userid'], 30);

	return array(
		'success' => true,
		'requires_authentication' => requires_authentication(),
	);
}

function requires_authentication()
{
	$options = vB::get_datastore()->get_value('options');
	$requires_authentication = false;
	$channel_permissions = vB::getUserContext()->getReadChannels();

	if (empty($channel_permissions['canRead'])) {
		$requires_authentication = true;
	}

	// If the forum is closed, require login!
	if (!$options['bbactive']) {
		$requires_authentication = true;
	}
	return $requires_authentication;
}


function do_register()
{
	$cleaned = vB::getCleaner()->cleanArray($_REQUEST, array(
		'username' => vB_Cleaner::TYPE_STR,
		'email' => vB_Cleaner::TYPE_STR,
		'password' => vB_Cleaner::TYPE_STR,
		'birthday' => vB_Cleaner::TYPE_STR,
		'timezone_name' => vB_Cleaner::TYPE_STR,
	));

	if (empty($cleaned['username'])) {
		return fr_register_info();
	}

	if (empty($cleaned['email']) || empty($cleaned['password'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	$data = array(
		'username' => $cleaned['username'],
		'email' => $cleaned['email'],
	);

	if (!empty($cleaned['birthday'])) {
		$data['birthday'] = $cleaned['birthday'];
	}

	$result = vB_Api::instance('user')->save(0, $cleaned['password'], $data, array(), array(), array());

	if (empty($result) || !empty($result['errors'])) {
		return json_error(ERR_NO_PERMISSION);
	}

	return true;
}

function fr_register_info()
{
	$options = vB::get_datastore()->get_value('options');
	$rules = ((string)new vB_Phrase('global', 'site_terms_and_rules'));
	$birthday = $options['usecoppa'];

	return array(
		'rules' => $rules,
		'birthday' => $birthday,
	);
}
