<?php

class vB5_Frontend_Controller_Registration extends vB5_Frontend_Controller
{
	/** Responds to a request to create a new user.
	*
	**/
	public function actionRegistration()
	{
		//We need at least a username, email, and password.

		if (empty($_REQUEST['username']) OR empty($_REQUEST['password']) OR empty($_REQUEST['email']))
		{
			$this->sendAsJson(array('error' => 'insufficient data'));
			return;
		}

		$username = trim($_REQUEST['username']);
		$password = trim($_REQUEST['password']);

		$postdata = array('username' => $username, 'email' => $_REQUEST['email']);

		if (isset($_REQUEST['month']) AND isset($_REQUEST['day']) AND !empty($_REQUEST['year']))
		{
			$postdata['birthday'] = $_REQUEST['year'] . '-' . str_pad($_REQUEST['month'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($_REQUEST['day'], 2, '0', STR_PAD_LEFT);
		}

		if (!empty($_REQUEST['guardian']))
		{
			$postdata['parentemail'] = $_REQUEST['guardian'];
		}

		$vboptions = vB5_Template_Options::instance()->getOptions();
		$vboptions = $vboptions['options'];

		// Coppa cookie check
		$coppaage = vB5_Cookie::get('coppaage', vB5_Cookie::TYPE_STRING);
		if ($vboptions['usecoppa'] AND $vboptions['checkcoppa'])
		{
			if ($coppaage)
			{
				$dob = explode('-', $coppaage);
				$month = $dob[0];
				$day = $dob[1];
				$year = $dob[2];
				$postdata['birthday'] = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
			}
			else
			{
				vB5_Cookie::set('coppaage', $_REQUEST['month'] . '-' . $_REQUEST['day'] . '-' . $_REQUEST['year'], 365, 0);
			}
		}
		
		// Fill in ReCaptcha data
		$recaptchaData = array();
		if (!empty($_REQUEST['recaptcha_challenge_field']))
		{
			$recaptchaData['recaptcha_challenge_field'] = $_REQUEST['recaptcha_challenge_field'];
		}
		if (!empty($_REQUEST['recaptcha_response_field']))
		{
			$recaptchaData['recaptcha_response_field'] = $_REQUEST['recaptcha_response_field'];
		}
		if (!empty($recaptchaData))
		{
			$_REQUEST['humanverify'] = $recaptchaData + (isset($_REQUEST['humanverify']) ? (array)$_REQUEST['humanverify'] : array());
		}

		$api = Api_InterfaceAbstract::instance();
		$data = array(
			'userid'   => 0,
			'password' => $password,
			'user'     => $postdata,
			array(),
			array(),
			'userfield' => (!empty($_REQUEST['userfield']) ? $_REQUEST['userfield'] : false),
			array(),
			isset($_REQUEST['humanverify']) ? $_REQUEST['humanverify'] : '',
			array('registration' => true),
		);

		// add facebook data
		if ($api->callApi('facebook', 'isFacebookEnabled') && $api->callApi('facebook', 'userIsLoggedIn'))
		{
			$fbUserInfo = $api->callApi('facebook', 'getFbUserInfo');
			$data['user']['fbuserid'] = $fbUserInfo['uid'];
			$data['user']['fbname'] = $fbUserInfo['name'];
			$data['user']['fbjoindate'] = time();
			$fb_profilefield_info = $this->getFacebookProfileinfo();
			if (empty($data['userfield']))
			{
				$data['userfield'] = array();
			}
			if ($vboptions['fb_userfield_biography'])
			{
				$data['userfield'] += array(
					$vboptions['fb_userfield_biography'] => $fb_profilefield_info['biography'],
				);
			}
			if ($vboptions['fb_userfield_location'])
			{
				$data['userfield'] += array(
					$vboptions['fb_userfield_location'] => $fb_profilefield_info['location'],
				);
			}
			if ($vboptions['fb_userfield_interests'])
			{
				$data['userfield'] += array(
					$vboptions['fb_userfield_interests'] => $fb_profilefield_info['interests'],
				);
			}
			if ($vboptions['fb_userfield_occupation'])
			{
				$data['userfield'] += array(
					$vboptions['fb_userfield_occupation'] => $fb_profilefield_info['occupation'],
				);
			}
		}

		// save data
		$response = $api->callApi('user', 'save', $data);

		// save facebook data
		//$api->callApi('facebook', 'saveConnectFacebook', $data);

		if (!empty($response) AND (!is_array($response) OR !isset($response['errors'])))
		{
			// try to login
			$loginInfo = $api->callApi('user', 'login', array($username, $password, '', '', ''));

			if (!isset($loginInfo['errors']) OR empty($loginInfo['errors']))
			{
				// browser session expiration
				vB5_Cookie::set('sessionhash', $loginInfo['sessionhash'], 0, true);
				vB5_Cookie::set('password', $loginInfo['password'], 0);
				vB5_Cookie::set('userid', $loginInfo['userid'], 0);

				$urlPath = '';
				if (!empty($_POST['urlpath']))
				{
					$urlPath = base64_decode(trim($_POST['urlpath']), true);
				}
				if (!$urlPath OR strpos($urlPath, '/auth/') !== false OR strpos($urlPath, '/register') !== false OR !vB5_Template_Runtime::allowRedirectToUrl($urlPath))
				{
					$urlPath = vB5_Config::instance()->baseurl;
				}
				$response = array('urlPath' => $urlPath);
			}
			else if (!empty($loginInfo['errors']))
			{
				$response = array(
					'errors' => $loginInfo['errors']
				);
			}

			if ($api->callApi('user', 'usecoppa'))
			{
				$response['usecoppa'] = true;
				$response['urlPath'] = vB5_Route::buildUrl('coppa-form|bburl');
			}
			else if ($vboptions['verifyemail'])
			{
				$response['msg'] = 'registeremail';
				$response['msg_params'] = array(vB5_String::htmlSpecialCharsUni($postdata['username']), $postdata['email'], vB5_Config::instance()->baseurl);
			}
			else if ($vboptions['moderatenewmembers'])
			{
				$response['msg'] = 'moderateuser';
				$response['msg_params'] = array(vB5_String::htmlSpecialCharsUni($postdata['username']), vB5_Config::instance()->baseurl);
			}
			else
			{
				$routeProfile = $api->callApi('route', 'getUrl', array('route' => 'profile', 'data' => array('userid' => $loginInfo['userid']), array()));
				$routeuserSettings = $api->callApi('route', 'getUrl', array('route' => 'settings', 'data' => array('tab' => 'profile'), array()));
				$routeAccount = $api->callApi('route', 'getUrl', array('route' => 'settings', 'data' => array('tab' => 'account'), array()));
				$response['msg'] = 'registration_complete';
				$response['msg_params'] = array(vB5_String::htmlSpecialCharsUni($postdata['username']),
					vB5_Config::instance()->baseurl . $routeProfile,
					vB5_Config::instance()->baseurl . $routeAccount,
					vB5_Config::instance()->baseurl . $routeuserSettings,
					vB5_Config::instance()->baseurl);
			}
		}

		$this->sendAsJson(array('response' => $response));
	}

	protected function getFacebookProfileinfo()
	{
		// the array we are going to return, populated with FB data
		$profilefields = array(
			'fbuserid'           => '',
			'fbname'             => '',
			'biography'          => '',
			'location'           => '',
			'interests'          => '',
			'occupation'         => '',
			'homepageurl'        => '',
			'birthday'           => '',
			'avatarurl'          => '',
			'fallback_avatarurl' => '',
			'timezone'           => '',
		);

		// grab fb account information
		$fb_info = Api_InterfaceAbstract::instance()->callApi('facebook', 'getFbUserInfo');

		// interests
		$profilefields['interests'] .= (!empty($fb_info['interests']) ? $fb_info['interests'] . ' ' : '');
		$profilefields['interests'] .= (!empty($fb_info['activities']) ? $fb_info['activities'] . ' ' : '');
		$profilefields['interests'] .= (!empty($fb_info['books']) ? $fb_info['books'] . ' ' : '');
		$profilefields['interests'] .= (!empty($fb_info['movies']) ? $fb_info['movies'] . ' ' : '');
		$profilefields['interests'] .= (!empty($fb_info['music']) ? $fb_info['music'] . ' ' : '');
		$profilefields['interests'] .= (!empty($fb_info['quotes']) ? $fb_info['quotes'] . ' ' : '');

		// occupation
		if (isset($fb_info['work_history']) AND isset($fb_info['work_history'][0]))
		{
			$occupation = array();
			if (isset($fb_info['work_history'][0]['position']) AND !empty($fb_info['work_history'][0]['position']))
			{
				$occupation[] = $fb_info['work_history'][0]['position'];
			}
			if (isset($fb_info['work_history'][0]['description']) AND !empty($fb_info['work_history'][0]['description']))
			{
				$occupation[] = $fb_info['work_history'][0]['description'];
			}
			if (!empty($occupation))
			{
				$profilefields['occupation'] = implode(', ', $occupation);
			}
		}

		// location
		if (isset($fb_info['current_location']))
		{
			$location = array();
			if (isset($fb_info['current_location']['name']) AND !empty($fb_info['current_location']['name']))
			{
				$location[] = $fb_info['current_location']['name'];
			}
			if (isset($fb_info['current_location']['country']) AND !empty($fb_info['current_location']['country']))
			{
				$location[] = $fb_info['current_location']['country'];
			}
			if (!empty($location))
			{
				$profilefields['location'] = implode(', ', $location);
			}
		}

		$profilefields['biography'] .= (!empty($fb_info['about_me']) ? $fb_info['about_me'] : '');
		$profilefields['homepageurl'] .= (!empty($fb_info['website']) ? $fb_info['website'] : '');
		$profilefields['birthday'] .= (!empty($fb_info['birthday_date']) ? $fb_info['birthday_date'] : '');
		$profilefields['timezone'] .= (!empty($fb_info['timezone']) ? $fb_info['timezone'] : '');

		$profilefields['fbuserid'] .= (!empty($fb_info['uid']) ? $fb_info['uid'] : '');
		$profilefields['fbname'] .= (!empty($fb_info['name']) ? $fb_info['name'] : '');
		$profilefields['avatarurl'] .= (!empty($fb_info['pic_big']) ? $fb_info['pic_big'] : '');
		$profilefields['fallback_avatarurl'] .= (!empty($fb_info['pic']) ? $fb_info['pic'] : '');

		return $profilefields;
	}

	/** Checks whether a user with a specific birthday is COPPA
	 *
	 **/
	public function actionIscoppa()
	{
		$vboptions = vB5_Template_Options::instance()->getOptions();
		$vboptions = $vboptions['options'];

		// Coppaage cookie
		if ($vboptions['usecoppa'] AND $vboptions['checkcoppa'])
		{
			vB5_Cookie::set('coppaage', $_REQUEST['month'] . '-' . $_REQUEST['day'] . '-' . $_REQUEST['year'], 365, 0);
		}

		//Note that 0 = wide open
		// 1 means COPPA users (under 13) can register but need approval before posting
		// 2 means COPPA users cannot register
		$api = Api_InterfaceAbstract::instance();
		$coppa = $api->callApi('user', 'needsCoppa', array('data' => $_REQUEST));

		$this->sendAsJson(array('needcoppa' => $coppa));
	}

	/** Checks whether a user with a specific birthday is COPPA
	 *
	 **/
	public function actionCheckUsername()
	{
		//Note that 0 = wide open
		// 1 means COPPA users (under 13) can register but need approval before posting
		// 2 means COPPA users cannot register
		if (empty($_REQUEST['username']))
		{
			return false;
		}
		$api = Api_InterfaceAbstract::instance();

		$result = $api->callApi('user', 'checkUsername', array('candidate' => $_REQUEST['username']));

		$this->sendAsJson($result);
	}

	/**
	 * Activate an user who is in "Users Awaiting Email Confirmation" usergroup
	 */
	public function actionActivateUser()
	{
		$get = array(
			'u' => !empty($_GET['u']) ? intval($_GET['u']) : 0, // Userid
			'i' => !empty($_GET['i']) ? trim($_GET['i']) : '', // Activate ID
		);

		$api = Api_InterfaceAbstract::instance();
		$result = $api->callApi('user', 'activateUser', array('userid' => $get['u'], 'activateid' => $get['i']));

		$phraseController = vB5_Template_Phrase::instance();
		$phraseController->register('registration');

		if (!empty($result['errors']) AND is_array($result['errors']))
		{
			$phraseArgs = is_array($result['errors'][0]) ? $result['errors'][0] : array($result['errors'][0]);
		}
		else
		{
			$phraseArgs = is_array($result) ? $result : array($result);
		}
		$messagevar = call_user_func_array(array($phraseController, 'getPhrase'), $phraseArgs);

		$this->showMsgPage($phraseController->getPhrase('registration'), $messagevar);

	}

	/**
	 * Activate an user who is in "Users Awaiting Email Confirmation" usergroup
	 * This action is for Activate form submission
	 */
	public function actionActivateForm()
	{
		$post = array(
			'username' => !empty($_POST['username']) ? trim($_POST['username']) : '', // username
			'activateid' => !empty($_POST['activateid']) ? trim($_POST['activateid']) : '', // Activate ID
		);

		$api = Api_InterfaceAbstract::instance();
		$result = $api->callApi('user', 'activateUserByUsername', array('username' => $post['username'], 'activateid' => $post['activateid']));

		if (empty($result['errors']))
		{
			$response['msg'] = $result;
			if ($response['msg'] == 'registration_complete')
			{
				$userinfo = $api->callApi('user', 'fetchByUsername', array('username' => $post['username']));
				$routeProfile = $api->callApi('route', 'getUrl', array('route' => 'profile', 'data' => array('userid' => $userinfo['userid']), array()));
				$routeuserSettings = $api->callApi('route', 'getUrl', array('route' => 'settings', 'data' => array('tab' => 'profile'), array()));
				$routeAccount = $api->callApi('route', 'getUrl', array('route' => 'settings', 'data' => array('tab' => 'account'), array()));
				$response['msg_params'] = array($post['username'],
					vB5_Config::instance()->baseurl . $routeProfile,
					vB5_Config::instance()->baseurl . $routeAccount,
					vB5_Config::instance()->baseurl . $routeuserSettings,
					vB5_Config::instance()->baseurl);
			}
			else
			{
				$response['msg_params'] = array();
			}
		}
		else
		{
			$response = $result;
		}

		$this->sendAsJson(array('response' => $response));
	}

	/**
	 * Send activate email
	 */
	public function actionActivateEmail()
	{
		$input = array(
			'email' => (isset($_POST['email']) ? trim(strval($_POST['email'])) : ''),
		);

		$api = Api_InterfaceAbstract::instance();
		$result = $api->callApi('user', 'sendActivateEmail', array('email' => $input['email']));


		if (empty($result['errors']))
		{
			$response['msg'] = 'lostactivatecode';
			$response['msg_params'] = array();
		}
		else
		{
			$response = $result;
		}

		$this->sendAsJson(array('response' => $response));
	}

	public function actionDeleteActivation()
	{
		$data = array(
			'u' => !empty($_GET['u']) ? intval($_GET['u']) : 0, // Userid
			'i' => !empty($_GET['i']) ? trim($_GET['i']) : '', // Activate ID
		);

		$api = Api_InterfaceAbstract::instance();
		$result = $api->callApi('user', 'deleteActivation', array('userid' => $data['u'], 'activateid' => $data['i']));

		$phraseController = vB5_Template_Phrase::instance();
		$phraseController->register('registration');

		if (!empty($result['errors']) AND is_array($result['errors']))
		{
			$phraseArgs = is_array($result['errors'][0]) ? $result['errors'][0] : array($result['errors'][0]);
		}
		else
		{
			$phraseArgs = is_array($result) ? $result : array($result);
		}
		$messagevar = call_user_func_array(array($phraseController, 'getPhrase'), $phraseArgs);

		$this->showMsgPage($phraseController->getPhrase('registration'), $messagevar);
	}

	public function actionKillActivation()
	{
		$data = array(
			'u' => !empty($_GET['u']) ? intval($_GET['u']) : 0, // Userid
			'i' => !empty($_GET['i']) ? trim($_GET['i']) : '', // Activate ID
		);

		$api = Api_InterfaceAbstract::instance();
		$result = $api->callApi('user', 'killActivation', array('userid' => $data['u'], 'activateid' => $data['i']));

		$phraseController = vB5_Template_Phrase::instance();
		$phraseController->register('registration');

		if (!empty($result['errors']) AND is_array($result['errors']))
		{
			$phraseArgs = is_array($result['errors'][0]) ? $result['errors'][0] : array($result['errors'][0]);
		}
		else
		{
			$phraseArgs = is_array($result) ? $result : array($result);
		}
		$messagevar = call_user_func_array(array($phraseController, 'getPhrase'), $phraseArgs);

		$this->showMsgPage($phraseController->getPhrase('registration'), $messagevar);
	}

	/**
	 * Disassociate a current vB account from its facebook account
	 */
	public function actionFbdisconnect()
	{
		$this->verifyPostRequest();

		if (!empty($_POST['disconnect']))
		{
			vB5_Facebook::instance()->fbdisconnect(trim(strval($_POST['disconnect'])));
		}

		// redirect
		// @todo - We need a standard way to do these redirects
		header('Location: ' . vB5_Config::instance()->baseurl . '/settings/account');
		exit;
	}
}
