<?php

/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 5.0.0
  || # ---------------------------------------------------------------- # ||
  || # Copyright  2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

class vB_User
{
	protected static $users = array();
	/**
	 * Processes logins into CP
	 * Adapted from functions_login.php::process_new_login
	 * THIS METHOD DOES NOT SET ANY COOKIES, SO IT CANNOT REPLACE DIRECTLY THE LEGACY FUNCTION
	 *
	 * @static
	 * @param array $auth The userinfo returned by vB_User::verifyAuthentication()
	 * @param string $logintype Currently 'cplogin' only or empty
	 * @param string $cssprefs AdminCP css preferences array
	 * @return array The userinfo returned by vB_User::verifyAuthentication() together with sessionhash and cpsessionhash
	 */
	public static function processNewLogin($auth, $logintype = '', $cssprefs = '')
	{
		$assertor = vB::getDbAssertor();

		$result = array();

		if (
			($session = vB::getCurrentSession()) AND
			$session->isCreated() AND
			($session->get('userid') == 0)
		)
		{
			// if we just created a session on this page, there's no reason not to use it
			$newsession = $session;
			$newsession->set('userid', $auth['userid']);
		}
		else
		{
			$sessionClass = vB::getRequest()->getSessionClass();
			$newsession = call_user_func(array($sessionClass, 'getSession'), $auth['userid']);
		}
		$newsession->set('loggedin', 1);

		if ($logintype == 'cplogin')
		{
			$newsession->set('bypass', 1);
		}
		else
		{
			$newsession->set('bypass', 0);
		}

		$newsession->set_session_visibility(false);
		$newsession->fetch_userinfo();
		vB::setCurrentSession($newsession);
		$result['sessionhash'] = $newsession->get('dbsessionhash');

		$usercontext = vB::getUserContext();

		if ($usercontext->isAdministrator() OR $usercontext->getCanModerate())
		{
			// If the user is admin or moderator, we create the cpsession
			$cpsession = $newsession->fetchCpsessionHash();
			$result['cpsession'] = $cpsession;
		}

		// admin control panel or upgrade script login
		if ($logintype === 'cplogin')
		{
			if ($usercontext->hasAdminPermission('cancontrolpanel'))
			{
				if ($cssprefs != '')
				{
					$admininfo = $assertor->getRow('vBForum:administrator', array('userid' => $auth['userid']));
					if ($admininfo)
					{
						$admindm = new vB_DataManager_Admin(null, vB_DataManager_Constants::ERRTYPE_SILENT);
						$admindm->set_existing($admininfo);
						$admindm->set('cssprefs', $cssprefs);
						$admindm->save();
					}
				}

			}
		}

		if (defined('VB_API') AND VB_API === true)
		{
			$apiclient = $newsession->getApiClient();
			if ($apiclient['apiclientid'] AND $auth['userid'])
			{
				$assertor->update('apiclient',
					array(
						'userid' => intval($auth['userid']),
					),
					array(
						'apiclientid' => intval($apiclient['apiclientid'])
					)
				);
			}
		}

		$result = array_merge($result, $auth);

		return $result;
	}

	/**
	 * Verifies a security token is valid
	 *
	 * @param	string	Security token from the REQUEST data
	 * @param	string	Security token used in the hash
	 *
	 * @return	boolean	True if the hash matches and is within the correct TTL
	 */
	public static function verifySecurityToken($request_token, $user_token)
	{
		global $vbulletin;

		// This is for backwards compatability before tokens had TIMENOW prefixed
		if (strpos($request_token, '-') === false)
		{
			return ($request_token === $user_token);
		}

		list($time, $token) = explode('-', $request_token);

		if ($token !== sha1($time . $user_token))
		{
			return false;
		}

		// A token is only valid for 3 hours
		if ($time <= vB::getRequest()->getTimeNow() - 10800)
		{
			$vbulletin->GPC['securitytoken'] = 'timeout';
			return false;
		}

		return true;
	}

	// Adapted from functions_login::process_logout
	// IT DOES NOT REMOVE COOKIES
	public static function processLogout()
	{
		global $vbulletin;

		$assertor = vB::getDbAssertor();
		$userinfo = vB::getCurrentSession()->fetch_userinfo();
		$timeNow = vB::getRequest()->getTimeNow();
		$options = vB::getDatastore()->get_value('options');
		$session = vB::getCurrentSession();

		if ($userinfo['userid'] AND $userinfo['userid'] != -1)
		{
			// init user data manager
//			$userdata = & datamanager_init('User', $vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
			$userdata = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
			$userdata->set_existing($userinfo);
			$userdata->set('lastactivity', $timeNow - $options['cookietimeout']);
			$userdata->set('lastvisit', $timeNow);
			$userdata->save();

            if (!defined('VB_API'))
            {
				// log out of any admin cp sessions as well.
                $assertor->delete('session', array('userid' => $userinfo['userid'], 'apiaccesstoken' => null));
				$assertor->delete('cpsession', array('userid' => $userinfo['userid']));
			}
		}

		$assertor->delete('session', array('sessionhash'=>$session->get('dbsessionhash')));

		// Remove accesstoken from apiclient table so that a new one will be generated
		if (defined('VB_API') AND VB_API === true AND $vbulletin->apiclient['apiclientid'])
		{
			$assertor->update('apiclient', array('apiaccesstoken'=>'', 'userid' => 0), array('apiclientid'=>intval($vbulletin->apiclient['apiclientid'])));
			$vbulletin->apiclient['apiaccesstoken'] = '';
		}

		if ($vbulletin->session->created == true AND (!defined('VB_API') OR !VB_API))
		{
			// if we just created a session on this page, there's no reason not to use it
			$newsession = $vbulletin->session;
		}
		else
		{
			// API should always create a new session here to generate a new accesstoken
			$newsession = vB_Session::getNewSession(vB::getDbAssertor(), vB::getDatastore(), vB::getConfig(), '', 0, '', vB::getCurrentSession()->get('styleid'));
		}

		$newsession->set('userid', 0);
		$newsession->set('loggedin', 0);
		$newsession->set_session_visibility(false);
		$vbulletin->session = & $newsession;

        $result = array();
        $result['sessionhash'] = $newsession->get('dbsessionhash');
        $result['apiaccesstoken'] = $newsession->get('apiaccesstoken');

		if (defined('VB_API') AND VB_API === true)
		{
			if ($_REQUEST['api_c'])
			{
				$assertor->update('apiclient',
					array(
						'apiaccesstoken' => $result['apiaccesstoken'],
						'userid' => 0,
					),
					array(
						'apiclientid' => intval($_REQUEST['api_c'])
					)
				);
			}
		}
        return $result;
	}

	/**
	 *
	 * @param string $username
	 */
	public static function verifyStrikeStatus($username = '')
	{
		$assertor = vB::getDbAssertor();
		$request = vB::getRequest();
		$options = vB::getDatastore()->get_value('options');

		$assertor->delete('vBForum:strikes', array(
			array(
				'field' => 'striketime',
				'value' => ($request->getTimeNow() - 3600),
				'operator' => vB_dB_Query::OPERATOR_LT
			)
		));

		if (!$options['usestrikesystem'])
		{
			return 0;
		}

		$ipFields = vB_Ip::getIpFields($request->getIpAddress());
		$strikes = $assertor->getRow('user_fetchstrikes', array(
					'ip_4' => vB_dB_Type_UInt::instance($ipFields['ip_4']),
					'ip_3' => vB_dB_Type_UInt::instance($ipFields['ip_3']),
					'ip_2' => vB_dB_Type_UInt::instance($ipFields['ip_2']),
					'ip_1' => vB_dB_Type_UInt::instance($ipFields['ip_1']),
				));

		if ($strikes['strikes'] >= 5 AND $strikes['lasttime'] > ($request->getTimeNow() - 900))
		{ //they've got it wrong 5 times or greater for any username at the moment
			// the user is still not giving up so lets keep increasing this marker
			self::execStrikeUser($username);

			return false;
		}
//		else if ($strikes['strikes'] > 5)
//		{ // a bit sneaky but at least it makes the error message look right
//			$strikes['strikes'] = 5;
//		}

		return $strikes['strikes'];
	}

	/**
	 * Port of function verify_authentication()
	 *
	 * @param  $username
	 * @param  $password
	 * @param  $md5password
	 * @param  $md5password_utf
	 * @return array|bool false if auth failed. User info array if auth successfully.
	 */
	public static function verifyAuthentication($username, $password, $md5password, $md5password_utf)
	{
		// todo: we need to restore this method
		// $username = vB_String::stripBlankAscii($username, ' ');
		// See VBM-635: &#xxx; should be converted to windows-1252 extended char. This may
		// not happen if a browser submits the form. But from API or user manually input, it does.
		// See also vB_DataManager_User::verify_username()
		$username = preg_replace(
			'/&#([0-9]+);/ie',
			"convert_unicode_char_to_charset('\\1', vB_String::getCharset())",
			$username
		);

		$userinfo = vB::getDbAssertor()->getRow('user_fetchlogin',
				array(
					'username' => $username
				));

		if ($userinfo)
		{
			$isValid = vB_User::verifyUserPass($userinfo, $password, $md5password, $md5password_utf);
			if (!$isValid)
			{
				return false;
			}
			// authentication is valid, so delete current session from db...
			vB::getDbAssertor()->delete('session', array('sessionhash' => vB::getCurrentSession()->get('dbsessionhash')));
            if(vB::getCurrentSession()->get('apiaccesstoken'))
            {
                vB::getDbAssertor()->delete('session', array('apiaccesstoken' => vB::getCurrentSession()->get('apiaccesstoken')));
            }

			// ... and create new session (this modifies vbulletin->userinfo)
            $sessionClass = vB::getRequest()->getSessionClass();
			$session = call_user_func(array($sessionClass, 'getSession'), $userinfo['userid']);

			$sessionUserInfo = $session->fetch_userinfo();
			vB::setCurrentSession($session);
			// API can not set cookies
            // if ($send_cookies)
            // {
            // set_authentication_cookies($cookieuser);
            // }
			$return_value = array(
				'userid'		=> $userinfo['userid'],
				'password'		=> md5($userinfo['password'] . vB_Request_Web::COOKIE_SALT),
				'lastvisit'		=> $sessionUserInfo['lastvisit'],
				'lastactivity'	=> $sessionUserInfo['lastactivity']
			);

			// Legacy Hook 'login_verify_success' Removed //
			return $return_value;
		}

		$return_value = false;
		// Legacy Hook 'login_verify_failure_username' Removed //
		return $return_value;
	}

	/**
	 * Port of verify_facebook_authentication()
	 *
	 * similar to verify_authentication(), but instead of checking user/pass match, we use asociated fb userid
	 */
	public static function verifyFacebookAuthentication()
	{
		// get the userinfo associated with current logged in facebook user
		// return false if not logged in to fb, or there is no associated user record
		$fb_userid = vB_Facebook::instance()->getLoggedInFbUserId();
		if (!$fb_userid)
		{
			return false;
		}

		$userinfo = vB::getDbAssertor()->getRow('user', array('fbuserid' => $fb_userid));
		if (!$userinfo)
		{
			return false;
		}

		// facebook login is valid, so delete current session from db...
		vB::getDbAssertor()->delete('session', array('sessionhash' => vB::getCurrentSession()->get('dbsessionhash')));

		// ... and create new session (this modifies vbulletin->userinfo)
		$session = vB_Session_WebApi::getSession($userinfo['userid']);
		$sessionUserInfo = $session->fetch_userinfo();
		vB::setCurrentSession($session);

		$return_value = array(
			'userid'       => $userinfo['userid'],
			'password'     => md5($userinfo['password'] . vB_Request_Web::COOKIE_SALT),
			'lastvisit'    => $sessionUserInfo['lastvisit'],
			'lastactivity' => $sessionUserInfo['lastactivity']
		);

		// Legacy Hook 'login_verify_success' Removed //

		return $return_value;
	}

	/**
	 * Verifies user password
	 *
	 * @param $userinfo	Must contain password and salt
	 * @param $password
	 * @param $md5password
	 * @param $md5password_utf
	 *
	 * @return	boolean	Flag to indicate if pass is valid
	 */
	public static function verifyUserPass($userinfo, $password, $md5password, $md5password_utf)
	{
		if (!empty($password))
		{
			$candidate_password = md5(md5($password) . $userinfo['salt']);
			if ($userinfo['password'] == $candidate_password)
			{
				return true;
			}
		}
		if (!empty($md5password))
		{
			$candidate_password = md5($md5password . $userinfo['salt']);
			if ($userinfo['password'] == $candidate_password)
			{
				return true;
			}
		}
		if (!empty($md5password_utf))
		{
			$candidate_password = md5($md5password_utf . $userinfo['salt']);
			if ($userinfo['password'] == $candidate_password)
			{
				return true;
			}
		}
		return false;
	}

	public static function execStrikeUser($username = '')
	{
		// todo: remove this global variable
		global $strikes;

		$assertor = vB::getDbAssertor();
		$request = vB::getRequest();
		$options = vB::getDatastore()->getValue('options');

		if (!$options['usestrikesystem'])
		{
			return 0;
		}

		$strikeip = $request->getIpAddress();
		$ipFields = vB_Ip::getIpFields($strikeip);

		if (!empty($username))
		{
			$strikes_user = $assertor->getRow('vBForum:strikes', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_COUNT,
							'ip_4' => vB_dB_Type_UInt::instance($ipFields['ip_4']),
							'ip_3' => vB_dB_Type_UInt::instance($ipFields['ip_3']),
							'ip_2' => vB_dB_Type_UInt::instance($ipFields['ip_2']),
							'ip_1' => vB_dB_Type_UInt::instance($ipFields['ip_1']),
							'username' => vB_String::htmlSpecialCharsUni($username)
					));

			if ($strikes_user['count'] == 4)  // We're about to add the 5th Strike for a user
			{
//				if ($user = $vbulletin->db->query_first("SELECT userid, username, email, languageid FROM " . TABLE_PREFIX . "user WHERE username = '" . $vbulletin->db->escape_string($username) . "' AND usergroupid <> 3"))
				if ($user = $assertor->getRow('user', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						vB_dB_Query::CONDITIONS_KEY => array(
							array('field' => 'username', 'value' => $username, 'operator' => vB_dB_Query::OPERATOR_EQ),
							array('field' => 'usergroupid', 'value' => 3, 'operator' => vB_dB_Query::OPERATOR_NE),
						)
				)))
				{
					$ip = $request->getIpAddress();

					$maildata = vB_Api::instanceInternal('phrase')->fetchEmailPhrases('accountlocked', array($user['username'], $options['bbtitle'], $ip), array($options['bbtitle']), $user['languageid']);
					vB_Mail::vbmail($user['email'], $maildata['subject'], $maildata['message'], true);
				}
			}
		}

		/* insert query */
		$assertor->insert('vBForum:strikes', array(
			'striketime' => $request->getTimeNow(),
			'strikeip' => $strikeip,
			'ip_4' => vB_dB_Type_UInt::instance($ipFields['ip_4']),
			'ip_3' => vB_dB_Type_UInt::instance($ipFields['ip_3']),
			'ip_2' => vB_dB_Type_UInt::instance($ipFields['ip_2']),
			'ip_1' => vB_dB_Type_UInt::instance($ipFields['ip_1']),
			'username' => vB_String::htmlSpecialCharsUni($username)
		));
		$strikes++;

		// Legacy Hook 'login_strikes' Removed //
	}

	public static function execUnstrikeUser($username)
	{
		$ipFields = vB_Ip::getIpFields(vB::getRequest()->getIpAddress());
		vB::getDbAssertor()->delete('vBForum:strikes', array(
			'ip_4' => vB_dB_Type_UInt::instance($ipFields['ip_4']),
			'ip_3' => vB_dB_Type_UInt::instance($ipFields['ip_3']),
			'ip_2' => vB_dB_Type_UInt::instance($ipFields['ip_2']),
			'ip_1' => vB_dB_Type_UInt::instance($ipFields['ip_1']),
			'username' => vB_String::htmlSpecialCharsUni($username)
		));
	}

	/**
	* Fetches an array containing info for the specified user, or false if user is not found
	*
	* Values for Option parameter:
	* avatar - Get avatar
	* location - Process user's online location
	* profilepic - Join the customprofilpic table to get the userid just to check if we have a picture
	* admin - Join the administrator table to get various admin options
	* signpic - Join the sigpic table to get the userid just to check if we have a picture
	* usercss - Get user's custom CSS
	* isfriend - Is the logged in User a friend of this person?
	* Therefore: array('avatar', 'location') means 'Get avatar' and 'Process online location'
	*
	* @param integer User ID
	* @param array Fetch Option (see description)
	* @param integer Language ID. If set to 0, it will use user-set languageid (if exists) or default languageid
	* @param boolean If true, the method won't use user cache but fetch information from DB.
	*
	* @return array The information for the requested user
	*/
	public static function fetchUserinfo($userid = 0, $option = array(), $languageid = false, $nocache = false)
	{
		sort($option);

		if (!empty($option))
		{
			$optionKey = implode('-', $option);
		}
		else
		{
			$optionKey = '#';
		}

		if (($session = vB::getCurrentSession()) AND ($currentUserId = $session->get('userid')))
		{
			if (!$userid)
			{
				$userid = $currentUserId;
			}
		}

		$userid = intval($userid);

		if (!$userid AND $session)
		{
			// return guest user info
			return $session->fetch_userinfo();
		}

		if ($languageid === false)
		{
			$languageid = vB::getCurrentSession()->get('languageid');
		}

		if ($nocache AND isset(self::$users["$userid"][$optionKey]))
		{
			// clear the cache if we are looking at ourself and need to add one of the JOINS to our information.
			unset(self::$users["$userid"][$optionKey]);
		}

		// return the cached result if it exists
		if (isset(self::$users[$userid][$optionKey]))
		{
			return self::$users[$userid][$optionKey];
		}

		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$hashKey = 'vb_UserInfo_' . $userid;
		if (!empty($languageid))
		{
			$hashKey .= '_' . $languageid;
		}
		if (!empty($option))
		{
			$hashKey .= '_' . md5(serialize($option));
		}

		if (!$nocache)
		{
			$user = $cache->read($hashKey);
		}

		if (empty($user))
		{
			$user = vB::getDbAssertor()->getRow('fetchUserinfo', array(
				'userid' 		=> $userid,
				'option' 		=> $option,
				'languageid' 	=> $languageid,
			));
			if (empty($user))
			{
				return false;
			}
		}
		$cache->write($hashKey, $user, 1440, 'userChg_' . $userid);

		$user['languageid'] = (!empty($languageid) ? $languageid : $user['languageid']);

		// decipher 'options' bitfield
		$user['options'] = intval($user['options']);

		$bf_misc_useroptions = vB::getDatastore()->get_value('bf_misc_useroptions');
		$bf_misc_adminoptions = vB::getDatastore()->get_value('bf_misc_adminoptions');

		if (!empty($bf_misc_useroptions))
		{
			foreach ($bf_misc_useroptions AS $optionname => $optionval)
			{
				$user["$optionname"] = ($user['options'] & $optionval ? 1 : 0);
			}
		}

		if (!empty($bf_misc_adminoptions))
		{
			foreach($bf_misc_adminoptions AS $optionname => $optionval)
			{
				$user["$optionname"] = ($user['adminoptions'] & $optionval ? 1 : 0);
			}
		}
		// make a username variable that is safe to pass through URL links
		$user['urlusername'] = urlencode(unhtmlspecialchars($user['username']));

		self::fetchMusername($user);

		// get the user's real styleid (not the cookie value)
		$user['realstyleid'] = $user['styleid'];

		$request = vB::getRequest();

		if ($request)
		{
			$timenow = vB::getRequest()->getTimeNow();
		}
		else
		{
			$timenow = time();
		}
		$user['securitytoken_raw'] = sha1($user['userid'] . sha1($user['salt']) . sha1(vB_Request_Web::COOKIE_SALT));
		$user['securitytoken'] = $timenow . '-' . sha1($timenow . $user['securitytoken_raw']);

		$user['logouthash'] =& $user['securitytoken'];

		if (in_array('location', $option))
		{ // Process Location info for this user
			require_once(DIR . '/includes/functions_online.php');
			$user = fetch_user_location_array($user);
		}

		// privacy_options
		if (isset($user['privacy_options']) AND $user['privacy_options'])
		{
			$user['privacy_options'] = unserialize($user['privacy_options']);
		}

		if (!isset(self::$users[$userid]))
		{
			self::$users[$userid] = array();
		}

		self::$users[$userid][$optionKey] = $user;
		return $user;
	}

	/**
	 * fetches the proper username markup and title
	 *
	 * @param array $user (ref) User info array
	 * @param string $displaygroupfield Name of the field representing displaygroupid in the User info array
	 * @param string $usernamefield Name of the field representing username in the User info array
	 *
	 * @return string Username with markup and title
	 */
	public static function fetchMusername(&$user, $displaygroupfield = 'displaygroupid', $usernamefield = 'username')
	{
		if (!empty($user['musername']))
		{
			// function already been called
			return $user['musername'];
		}

		$username = $user["$usernamefield"];

		$usergroupcache = vB::getDatastore()->get_value('usergroupcache');
		$bf_ugp_genericoptions = vB::getDatastore()->get_value('bf_ugp_genericoptions');

		if (!empty($user['infractiongroupid']) AND $usergroupcache["$user[usergroupid]"]['genericoptions'] & $bf_ugp_genericoptions['isnotbannedgroup'])
		{
			$displaygroupfield = 'infractiongroupid';
		}

		if (isset($user["$displaygroupfield"], $usergroupcache["$user[$displaygroupfield]"]) AND $user["$displaygroupfield"] > 0)
		{
			// use $displaygroupid
			$displaygroupid = $user["$displaygroupfield"];
		}
		else if (isset($usergroupcache["$user[usergroupid]"]) AND $user['usergroupid'] > 0)
		{
			// use primary usergroupid
			$displaygroupid = $user['usergroupid'];
		}
		else
		{
			// use guest usergroup
			$displaygroupid = 1;
		}

		$user['musername'] = $usergroupcache["$displaygroupid"]['opentag'] . $username . $usergroupcache["$displaygroupid"]['closetag'];
		$user['displaygrouptitle'] = $usergroupcache["$displaygroupid"]['title'];
		$user['displayusertitle'] = $usergroupcache["$displaygroupid"]['usertitle'];

		if ($displaygroupfield == 'infractiongroupid' AND $usertitle = $usergroupcache["$user[$displaygroupfield]"]['usertitle'])
		{
			$user['usertitle'] = $usertitle;
		}
		else if (isset($user['customtitle']) AND $user['customtitle'] == 2)
		{
			$user['usertitle'] = function_exists('htmlspecialchars_uni')?htmlspecialchars_uni($user['usertitle']):htmlspecialchars($user['usertitle']);
		}

		return $user['musername'];
	}

	/** This grants a user additional permissions in a specific channel, by adding to the groupintopic table
	 *
	 *	@param	int
	 *	@param	mixed	integer or array of integers
	 * 	@param	int
	 *
	 *	@return	bool
	 ***/
	public static function setGroupInTopic($userid, $nodeids, $usergroupid)
	{
		//check the data.
		if (!is_numeric($userid) OR !is_numeric($usergroupid))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		if (!is_array($nodeids))
		{
			$nodeids = array($nodeids);
		}
		else
		{
			$nodeids = array_unique($nodeids);
		}

		//We don't do a permission check. It's essential that the api's do that before calling here.

		//let's get the current channels in which the user already is set for that group.
		//Then remove any for which they already are set.
		$assertor = vB::getDbAssertor();
		$existing = $assertor->assertQuery('vBForum:groupintopic', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'userid' => $userid, 'groupid' => $usergroupid));
		foreach ($existing as $permission)
		{
			$index = array_search($permission['nodeid'] , $nodeids);

			if ($index !== false)
			{
				unset($nodeids[$index]);
			}
		}

		//and do the inserts
		foreach ($nodeids as $nodeid)
		{
			$assertor->assertQuery('vBForum:groupintopic', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'userid' => $userid, 'nodeid' => $nodeid, 'groupid' => $usergroupid));
		}

		vB_Cache::allCacheEvent(array("userPerms_$userid", "userChg_$userid", "followChg_$userid", "sgMemberChg_$userid"));
		vB_Api::instanceInternal('user')->clearChannelPerms($userid);
		vB::getUserContext($userid)->clearChannelPermissions($usergroupid);

		//if we got here all is well.
		return true;
	}

	/**
	 * Mainly needed for unit test.
	 */
	public static function clearUsersCache($userid = false)
	{
		$userid = intval($userid);
		if ($userid)
		{
			unset(self::$users[$userid]);
		}
		else
		{
			self::$users = array();
		}
	}
}

/* ======================================================================*\
  || ####################################################################
  || # CVS: $RCSfile$ - $Revision: 40911 $
  || ####################################################################
  \*====================================================================== */
