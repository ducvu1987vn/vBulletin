<?php

/**
 * Singleton object for accessing information about (the current user's) facebook session
 */
class vB5_Facebook
{
	/**
	 * @var	object	API object
	 */
	protected $api = null;

	/**
	 * @var bool	debug mode
	 */
	protected $debug = false;

	/**
	 * @var array	Debug log
	 */
	protected $debugLog = array();

	/**
	 * Singleton instance
	 * @var	vB5_Facebook
	 */
	protected static $instance = null;

	/**
	 * Singleton instance getter
	 *
	 * @return	vB5_User
	 */
	public static function instance()
	{
		if (self::$instance === null)
		{
			$class = __CLASS__;
			self::$instance = new $class;
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	protected function __construct()
	{
		$vb5_config = vB5_Config::instance();

		$this->api = Api_InterfaceAbstract::instance();
		$this->debug = (bool) $vb5_config->debug;
	}

	/**
	 * Checks to see if facebook is enabled in vB
	 *
	 * @return	bool	Enabled status of facebook
	 */
	public function isFacebookEnabled()
	{
		static $isEnabled = null;

		if ($isEnabled === null)
		{
			$isEnabled = $this->api->callApi('facebook', 'isFacebookEnabled');
		}

		return $isEnabled;
	}

	/**
	 * Checks to see if the current user is currently logged into facebook
	 *
	 * @return	bool	Current user's logged in status
	 */
	public function userIsLoggedIn()
	{
		static $userIsLoggedIn = null;

		if ($userIsLoggedIn === null)
		{
			$userIsLoggedIn = $this->api->callApi('facebook', 'userIsLoggedIn');
		}

		return $userIsLoggedIn;
	}

	/**
	 * Handles loading and initializing the facebook platform
	 *
	 * @param	bool	Whether or not to do the redirect
	 */
	public function loadFacebook($dofbredirect)
	{
		$api = Api_InterfaceAbstract::instance();
		$isEnabled = $this->isFacebookEnabled();

		// check if facebook and session is enabled
		if ($isEnabled)
		{
			$userIsLoggedIn = $this->userIsLoggedIn();

			// is user logged into facebook?
			if ($userIsLoggedIn)
			{
				$this->debugLog('User is logged into facebook');
				$user = vB5_User::instance();
				$inRegistrationRoute = (vB5_Frontend_Application::instance()->getRouter()->getRouteGuid() == 'vbulletin-4ecbdacd6a6f13.66635711');

				// is user logged into vB?
				if (!empty($user['userid']))
				{
					$this->debugLog('User is logged into vB');
					$loggedInFbUserId = $api->callApi('facebook', 'getLoggedInFbUserId');

					// if vb user is not associated with the current facebook account (or no facebook account at all),
					// redirect to the register association page, if doing facebook redirect
					if ($user['fbuserid'] != $loggedInFbUserId)
					{
						$this->debugLog('User that is logged into vB is not associated with the user logged into facebook');
						if ($dofbredirect) // do_facebook_redirect()
						{
							header('Location: ' . vB5_Config::instance()->baseurl . '/register');
							exit;
						}

						// if not doing facebook redirect and not on the reg page,
						// pretend the user is not logged into facebook at all so user can browse
						else if (!$inRegistrationRoute)
						{
							$show['facebookuser'] = false;
							if ($dofbredirect)
							{
								standard_error(fetch_error('facebook_connect_fail'));
							}
						}

						// connect this vB user to this FB user
						else if (!$dofbredirect AND $inRegistrationRoute)
						{
							$this->debugLog('Connect this vB user to this FB user');

							// save facebook data
							$fbUserInfo = $this->api->callApi('facebook', 'getFbUserInfo');
							$data = array(
								'userid' => $user['userid'],
								'password' => '',
								'user' => array(
									'fbuserid' => $fbUserInfo['uid'],
									'fbname' => $fbUserInfo['name'],
									'fbjoindate' => time(),
								),
								'options' => array(),
								'adminoptions' => array(),
								'userfield' => array(),
							);
							$api->callApi('user', 'save', $data);

							header('Location: ' . vB5_Config::instance()->baseurl . '/settings/account');
							exit;
						}
					}
				}

				// user is not logged into vb, but is logged into facebook
				else
				{
					$this->debugLog('User is NOT logged into vB');

					// check if there is an associated vb account, if so attempt to log that user in
					if ($vbUserId = $api->callApi('facebook', 'getVbUseridFromFbUserid'))
					{
						$this->debugLog('FB acct connected to a vB acct');

						// make sure user is trying to login
						if ($dofbredirect) // do_facebook_redirect()
						{
							$uname = $api->callApi('user', 'fetchUserName', array($vbUserId));
							$loginInfo = $api->callApi('user', 'login', array($uname, '', '', '', 'fbauto'));

							vB5_Auth::setLoginCookies($loginInfo);
							vB5_Auth::doLoginRedirect();
						}
						// if user is not trying to login with FB connect,
						// pretend like the user is not logged in to FB
						else if (!$inRegistrationRoute)
						{
							$show['facebookuser'] = false;
							if ($dofbredirect)
							{
								standard_error(fetch_error('facebook_connect_fail'));
							}
						}
					}

					// otherwise, fb account is not associated with any vb user
					else
					{
						$this->debugLog('FB acct not associated with any vB acct');

						// redirect to the registration page to create a vb account
						if ($dofbredirect && !$inRegistrationRoute) // do_facebook_redirect()
						{
							header('Location: ' . vB5_Config::instance()->baseurl . '/register');
							exit;
						}

						// if not doing redirect and not trying to register,
						// pretend user is not logged into facebook so they can still browse the site
						else if (!$inRegistrationRoute)
						{
							$show['facebookuser'] = false;
							if ($dofbredirect)
							{
								standard_error(fetch_error('facebook_connect_fail'));
							}
						}
					}
				}
			}
			else
			{
				$this->debugLog('User is NOT logged into facebook');

				$user = vB5_User::instance();

				// is user logged into vB?
				if (!empty($user['userid']))
				{
					$this->debugLog('User is logged into vB');


				}
				else
				{
					$this->debugLog('User is NOT logged into vB');

				}
			}
		}
	}

	/**
	 * Disconnects the vB user account from the facebook account
	 *
	 * @param	string	Facebook userid to disconnect, must be logged in and associated with
	 * 		current vB account
	 */
	public function fbdisconnect($fbuseridToDisconnect)
	{
		$api = Api_InterfaceAbstract::instance();
		$isEnabled = $this->isFacebookEnabled();

		// check if facebook and session is enabled
		if ($isEnabled)
		{
			$userIsLoggedIn = $this->userIsLoggedIn();

			// is user logged into facebook?
			if ($userIsLoggedIn)
			{
				$user = vB5_User::instance();
				$inRegistrationRoute = (vB5_Frontend_Application::instance()->getRouter()->getRouteGuid() == 'vbulletin-4ecbdacd6a6f13.66635711');

				// is user logged into vB?
				if (!empty($user['userid']))
				{
					$loggedInFbUserId = $api->callApi('facebook', 'getLoggedInFbUserId');

					// if vb user is associated with the current facebook account
					// and has a vB password
					if ($fbuseridToDisconnect == $loggedInFbUserId AND $user['fbuserid'] == $loggedInFbUserId AND $user['logintype'] == 'vb')
					{
						// remove facebook data
						//$fbUserInfo = $this->api->callApi('facebook', 'getFbUserInfo');
						$data = array(
							'userid' => $user['userid'],
							'password' => '',
							'user' => array(
								'fbuserid' => '',
								'fbname' => '',
								'fbjoindate' => '',
							),
							'options' => array(),
							'adminoptions' => array(),
							'userfield' => array(),
						);
						$api->callApi('user', 'save', $data);
					}
				}
			}
		}
	}

	protected function debugLog($text)
	{
		if ($this->debug)
		{
			$this->debugLog[] = $text;
		}
	}

	public static function getDebugLog()
	{
		$instance = self::instance();
		$log = array();

		if ($instance->debug)
		{
			$log = $instance->debugLog;
		}

		return $log;
	}

}
