<?php

/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 5.0.0
  || # ---------------------------------------------------------------- # ||
  || # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

// TODO: move these functions as static methods of vB_Facebook
require_once(DIR . '/includes/functions_facebook.php');

/**
 * vBulletin wrapper for the facebook client api, singleton
 *
 * @package vBulletin
 * @author Michael Henretty, vBulletin Development Team
 * @version $Revision: 43642 $
 * @since $Date: 2011-05-19 12:14:21 -0300 (Thu, 19 May 2011) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Facebook
{

	/**
	 * A reference to the singleton instance
	 *
	 * @var vB_Facebook
	 */
	protected static $instance = null;
	/**
	 * The facebook client api object
	 *
	 * @var Facebook
	 */
	protected $facebook = null;
	/**
	 * The facebook session array
	 *
	 * @var array
	 */
	protected $fb_session = null;
	/**
	 * The facebook userid if logged in
	 *
	 * @var int
	 */
	protected $registry = null;
	/**
	 * The facebook userid if logged in
	 *
	 * @var int
	 */
	protected $fb_userid = null;
	/**
	 * The associated vBulletin userid if available
	 *
	 * @var int
	 */
	protected $vb_userid = null;
	/**
	 * The user infomation array we want to grab from fb api by default
	 *
	 * @var array
	 */
	protected $fb_userinfo = array();
	protected $fql_fields = array(
		'uid',
		'name',
		'first_name',
		'last_name',
		'about_me',
		'timezone',
		'email',
		'locale',
		'current_location',
		'affiliations',
		'profile_url',
		'sex',
		'pic_square',
		'pic',
		'pic_big',
		'birthday',
		'birthday_date',
		'profile_blurb',
		'website',
		'activities',
		'interests',
		'music',
		'movies',
		'books',
		'website',
		'quotes',
		'work_history'
	);
	/**
	 * The users connection info we want to grab
	 *
	 * @var array
	 */
	protected $fb_userconnectioninfo = array();
	protected $connection_fields = array(
		'activities',
		'interests',
		'music',
		'movies',
		'books',
		'notes',
		'website'
	);

	// Adapted from functions.php::is_facebookenabled
	public static function isFacebookEnabled()
	{
		$options = vB::getDatastore()->get_value('options');

		return ($options['facebookactive'] AND !defined('SKIP_SESSIONCREATE'));
	}

	/**
	 * Logs the user out of Facebook Connect, but not out of Facebook.com
	 */
	// Adapted from functions_facebook.php::do_facebooklogout
	public static function doFacebookLogout()
	{
		//global $show;
		//$show['facebookuser'] = false;
		vB_Facebook::instance()->doLogoutFbUser();
	}

	// Adapted from functions_facebook.php::get_fbprofileurl
	public static function getFbProfileUrl()
	{
		if ($fbuserid = vB_Facebook::instance()->getLoggedInFbUserId())
		{
			return "http://www.facebook.com/profile.php?id=$fbuserid";
		}
		else
		{
			return false;
		}
	}

	// Adapted from functions_facebook.php::get_fbprofilepicurl
	public static function getFbProfilePicUrl()
	{
		global $vbulletin;
		static $picurl = '';

		// attempt to pull profile pic from various sources
		if ($picurl == '')
		{
			$userinfo = vB::getCurrentSession()->fetch_userinfo();
			if (!empty($userinfo['fbuserid']))
			{
				$picurl = Facebook::$DOMAIN_MAP['graph'] . $userinfo['fbuserid'] . '/picture';
			}

			if ($picurl == '')
			{
				$cookieVal = vB5_Cookie::get('fbprofilepicurl', vB5_Cookie::TYPE_STRING);
				if (!empty($cookieVal))
				{
					$picurl = htmlspecialchars($cookieVal);
				}
			}

			if ($picurl == '')
			{
				$fbuserid = vB_Facebook::instance()->getLoggedInFbUserId();
				if (!empty($fbuserid))
				{
					$picurl = Facebook::$DOMAIN_MAP['graph'] . $fbuserid . '/picture';
				}
			}

			//if ($picurl == '')
			//{
			//	$picurl = vB_Template_Runtime::fetchStyleVar('imgdir_misc') . '/unknown.gif';
			//}
		}


		return $picurl;
	}

	/**
	* Saves fb data into a user data manager
	*
	* @param	vB_DataManager_User, the datamanager to save the fb form info into
	* @param	bool	True if the user is registering, false if the user is already registered (linking accounts)
	*/
	// Adapted from functions_facebook.php::save_fbdata
	public static function saveFacebookData($userdata, $is_registering = true)
	{
		// save the data from the import form
		vB_Facebook::saveFacebookImportFormIntoUserDm($userdata, $is_registering);

		// save the facebook usergroup
		vB_Facebook::saveFacebookUserGroup($userdata);
	}

	/**
	* Puts data from the facebook import form into the user datamanager
	*
	* @param	vB_DataManager_User, the datamanager to save the fb form info into
	* @param	bool	True if the user is registering, false if the user is already registered (linking accounts)
	*/
	// Adapted from functions_facebook.php::save_fbimportform_into_userdm
	protected static function saveFacebookImportFormIntoUserDm($userdata, $is_registering = true)
	{
		global $vbulletin;

		$vbulletin->input->clean_array_gpc('p', array(
			'fbuserid'    => vB_Cleaner::TYPE_STR,
			'fbname'      => vB_Cleaner::TYPE_STR,
			'fboptions'   => vB_Cleaner::TYPE_ARRAY,
			'avatarurl'   => vB_Cleaner::TYPE_STR,
			'userfield'   => vB_Cleaner::TYPE_ARRAY,
			'homepageurl' => vB_Cleaner::TYPE_STR,
			'fbday'       => vB_Cleaner::TYPE_INT,
			'fbmonth'     => vB_Cleaner::TYPE_INT,
			'fbyear'      => vB_Cleaner::TYPE_INT,
		));

		// make sure the current facebook userid matches the one when the form was generated
		if ($vbulletin->GPC['fbuserid'] != vB_Facebook::instance()->getLoggedInFbUserId())
		{
			$userdata->error('facebookuseridmismatch');
		}

		// make sure facebook account is not already associated with a vb account
		else if (vB_Facebook::instance()->getVbUseridFromFbUserid($vbulletin->GPC['fbuserid']))
		{
			$userdata->error('facebook_account_already_registered');
		}

		// passed validation, now we save the data
		else
		{
			$userdata->set('fbuserid', $vbulletin->GPC['fbuserid']);
			$userdata->set('fbname', $vbulletin->GPC['fbname']);
			$userdata->set('fbjoindate', time());

			$fboptions = $vbulletin->GPC['fboptions'];

			// unset any custom profile fields that were not checked
			$fields = array('biography', 'location', 'interests', 'occupation');
			foreach ($fields AS $field)
			{
				if (empty($fboptions["use$field"]) AND !$fboptions["skip$field"])
				{
					unset($vbulletin->GPC['userfield'][$vbulletin->options["fb_userfield_$field"]]);
				}
			}

			// set custom profile fields
			if ($is_registering)
			{
				$userdata->set_userfields($vbulletin->GPC['userfield'], true, 'register');
			}
			else
			{
				$userdata->set_userfields($vbulletin->GPC['userfield'], true, 'normal', true);
			}

			// now save any additional data to user profile from facebook, like avatar
			if (!empty($fboptions['useavatar']) AND !empty($vbulletin->GPC['avatarurl']))
			{
				save_fbavatar($userdata, $vbulletin->GPC['avatarurl']);
			}

			// homepage
			if (!empty($fboptions['usehomepageurl']) AND !empty($vbulletin->GPC['homepageurl']))
			{
				$userdata->set('homepage', $vbulletin->GPC['homepageurl']);
			}

			// birthday
			if (!empty($fboptions['usebirthday']))
			{
				$userdata->set('birthday', array(
					'day'   => $vbulletin->GPC['fbday'],
					'month' => $vbulletin->GPC['fbmonth'],
					'year'  => $vbulletin->GPC['fbyear']
				));
			}
		}
	}

	/**
	 * Saves fb usergroup into the datamanager
	 * Adapted from functions_facebook.php::save_fbusergroup
	 *
	 * @param	vB_DataManager_User, the datamanager to save the fb form info into
	 */
	protected static function saveFacebookUserGroup($userdata)
	{
		global $vbulletin;

		// save additional fb usergroup if specified, making sure it is not already the primary usergroup
		if ($vbulletin->options['facebookusergroupid'] > 0 AND $vbulletin->options['facebookusergroupid'] != $userdata->fetch_field('usergroupid'))
		{
			$membergroupids = fetch_membergroupids_array($vbulletin->userinfo, false);
			$membergroupids[] = $vbulletin->options['facebookusergroupid'];
			$userdata->set('membergroupids', array_unique($membergroupids));
		}
	}

	/**
	 * Returns an instance of the facebook client api object
	 *
	 * @return vB_Facebook
	 */
	public static function instance()
	{
		if (!isset(self::$instance))
		{
			// boot up the facebook api
			self::$instance = new vB_Facebook();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @param int $apikey	the api key for the facebook user
	 * @param int $secret	the facebook secret for the application
	 */
	protected function __construct()
	{
		// cache a reference to the registry object
		global $vbulletin;
		$this->registry = $vbulletin;

		$options = vB::getDatastore()->get_value('options');

		// initialize fb api and grab fb userid to cache locally
		try
		{
			// init the facebook graph api
			$this->facebook = new vB_Facebook_vUrl(array(
				'appId' => $options['facebookappid'],
				'secret' => $options['facebooksecret'],
				'cookie' => true,
			));

			$this->fb_session = array(
				'uid' => $this->facebook->getUser(),
				'access_token' => $this->facebook->getAccessToken(),
			);

			// check for valid session without pinging facebook
			if ($this->fb_session)
			{
				$this->fb_userid = $this->fb_session['uid'];

				// make sure local copy of fb session is up to date
				$this->validateFBSession();
			}
		}
		catch (Exception $e)
		{
			$this->handleFacebookException($e);
			$this->fb_userid = null;
		}
	}

	/**
	 * Checks the fb userid returned from api to make sure its valid
	 *
	 * @return bool, fb userid if logged in, false otherwise
	 */
	protected function isValidUser()
	{
		// check for null restuls, or error code (<1000)
		return (!empty($this->fb_userid) AND !$this->fb_userid < 1000);
	}

	/**
	 * Makes sure local copy of FB session is in synch with actual FB session
	 *
	 * @return bool, fb userid if logged in, false otherwise
	 */
	protected function validateFBSession()
	{
		$userinfo = vB::getCurrentSession()->fetch_userinfo();

		// grab the current access token stored locally (in cookie or db depending on login status)
		if ($userinfo['userid'] == 0)
		{
			if (isset($_COOKIE[COOKIE_PREFIX . 'fbaccesstoken']))
			{
				$curaccesstoken = strval($_COOKIE[COOKIE_PREFIX . 'fbaccesstoken']);
			}
			else
			{
				$curaccesstoken = '';
			}
		}
		else
		{
			$curaccesstoken = !empty($userinfo['fbaccesstoken']) ? $userinfo['fbaccesstoken'] : '';
		}

		// if we have a new access token that is valid, re-query FB for updated info, and cache it locally
		if ($curaccesstoken != $this->fb_session['access_token'] AND $this->isValidAuthToken())
		{
			// update the userinfo array with fresh facebook data
			$userinfo['fbaccesstoken'] = $this->fb_session['access_token'];

			//$this->registry->userinfo['fbprofilepicurl'] = $this->fb_userinfo['pic_square'];
			// if user is guest, store fb session info in cookie
			if ($userinfo['userid'] == 0)
			{
				vbsetcookie('fbaccesstoken', $this->fb_session['access_token']);
				vbsetcookie('fbprofilepicurl', $this->fb_userinfo['pic_square']);
			}

			// if authenticated user, store fb session in user table
			else
			{
				vB::getDbAssertor()->update('user', array('fbaccesstoken' => $this->fb_session['access_token']), array('userid' => $userinfo['userid']));

				//$this->registry->db->query_write("
				//	UPDATE " . TABLE_PREFIX . "user
				//	SET
				//		fbaccesstoken = '" . $this->fb_session['access_token'] . "'
				//	WHERE userid = " . $userinfo['userid'] . "
				//");
			}
		}
	}

	/**
	 * Checks if the current user is logged into facebook
	 *
	 * @return bool
	 */
	public function userIsLoggedIn()
	{
		// make sure facebook is connect also enabled
		return self::instance()->isValidUser();
	}

	/**
	 * Verifies that the current session auth token is still valid with facebook
	 * 	- performs a Facebook roundtrip
	 *
	 * @return bool, true if auth token is still valid
	 */
	public function isValidAuthToken()
	{
		if (!$this->getFbUserInfo())
		{
			//$this->facebook->setSession(null);
			$this->fb_session = array(
				'uid' => '',
				'access_token' => '',
			);
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	 * Checks for a currrently logged in user through facebook api
	 *
	 * @return mixed, fb userid if logged in, false otherwise
	 */
	public function getLoggedInFbUserId()
	{
		if (!$this->isValidUser())
		{
			return false;
		}

		return $this->fb_userid;
	}

	/**
	 * Grabs logged in user info from faceboook if user is logged in
	 *
	 * @param bool, forces a roundtrip to the facebook server, ie. dont use cached info
	 *
	 * @return array, fb userinfo array if logged in, false otherwise
	 */
	public function getFbUserInfo($force_reload = false)
	{
		// check for cached versions of this, and return it if so
		if (!empty($this->fb_userinfo) AND !$force_reload)
		{
			return $this->fb_userinfo;
		}

		// make sure we have a fb user and fb session, otherwise we cant return any data
		if (!$this->isValidUser() OR empty($this->fb_session['access_token']))
		{
			return false;
		}

		// attempt to grab userinfo from fb graph api, using FQL
		try
		{
			$response = $this->facebook->api(array(
				'access_token' => $this->fb_session['access_token'],
				'method' => 'fql.query',
				'query' => 'SELECT ' . implode(',', $this->fql_fields) . ' FROM user WHERE uid=' . $this->fb_userid,
			));

			if (is_array($response) AND !empty($response))
			{
				$this->fb_userinfo = $response[0];
			}
		}
		catch (Exception $e)
		{
			$this->handleFacebookException($e);
			return false;
		}

		// now return the user info if we got any
		return $this->fb_userinfo;
	}

	/**
	 * Grabs logged in user connections (ie likes, activities, interests, etc)
	 *
	 * @param bool, forces a roundtrip to the facebook server, ie. dont use cached info
	 *
	 * @return array, fb userconnectioninfo array if logged in, false otherwise
	 */
	public function getFbUserConnectionInfo($force_reload = false)
	{
		// check for cached versions of this, and return it if so
		if (!empty($this->fb_userconnectioninfo) AND !$force_reload)
		{
			return $this->fb_userconnectioninfo;
		}

		// make sure we have a fb user and fb session, otherwise we cant return any data
		if (!$this->isValidUser() OR empty($this->fb_session['access_token']))
		{
			return false;
		}

		// attempt to grab userinfo from fb graph api, using FQL
		try
		{
			$response = $this->facebook->api(
				'/me?fields=' . implode(',', $this->connection_fields)
			);

			if (is_array($response) AND !empty($response))
			{
				$this->fb_userconnectioninfo = $response[0];
			}
		}
		catch (Exception $e)
		{
			$this->handleFacebookException($e);
			return false;
		}

		// now return the user info if we got any
		return $this->fb_userconnectioninfo;
	}

	/**
	 * Checks if current facebook user is associated with a vb user, and returns vb userid if so
	 *
	 * @param int, facebook userid to check in vb database, if not there well user current
	 * 		logged in user
	 * @return mixed, vb userid if one is associated, false if not
	 */
	public function getVbUseridFromFbUserid($fb_userid = false)
	{
		// if no fb userid was passed in, attempt to use current logged in fb user
		// but if no current fb user, there cannot be an associated vb account, so return false
		if (empty($fb_userid) AND !($fb_userid = $this->getLoggedInFbUserId()))
		{
			return false;
		}

		// check if vB userid is already cached in this object
		if ($fb_userid == $this->getLoggedInFbUserId() AND !empty($this->vb_userid))
		{
			return $this->vb_userid;
		}

		// otherwise we have to grab the vb userid from the database
		$user = vB::getDbAssertor()->getRow('user', array('fbuserid' => $fb_userid));
		$this->vb_userid = (!empty($user['userid']) ? $user['userid'] : false);

		return $this->vb_userid;
	}

	/**
	 * Checks if current facebook user is associated with a vb user, and returns vb userid if so
	 *
	 * @param int, facebook userid to check in vb database, if not there well user current
	 * 		logged in user
	 * @return mixed, vb userid if one is associated, false if not
	 */
	public function publishFeed($message, $name, $link, $description, $picture = null)
	{
		$params = array(
			'message' => $message,
			'name' => $name,
			'link' => $link,
			'description' => $description,
		);

		// add picture link if applicable
		if (!empty($picture))
		{
			$params['picture'] = $picture;
		}
		else
		{
			$options = vB::getDatastore()->getValue('options');
			if (!empty($options['facebookfeedimageurl']))
			{
				$params['picture'] = $options['facebookfeedimageurl'];
			}
		}

		// attempt to publish to user's wall
		try
		{
			$response = $this->facebook->api(
				'/me/feed',
				'POST',
				$params
			);
			return !empty($response);
		}
		catch (Exception $e)
		{
			$this->handleFacebookException($e);
			return false;
		}
	}

	/**
	 * Kills the current Facebook session
	 */
	public function doLogoutFbUser()
	{
		// set the current session to null
		//$this->facebook->setSession(null);

		// get logout url?
	}

	/**
	 * Handles facebook exceptions (expose the exception if in debug mode)
	 *
	 * @param	object	The facebook exception
	 */
	protected function handleFacebookException(Exception $e)
	{
		$config = vB::getConfig();

		if (isset($vb5_config['Misc']['debug']) AND $vb5_config['Misc']['debug'])
		{
			throw $e;
		}
	}
}

/* ======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*====================================================================== */
