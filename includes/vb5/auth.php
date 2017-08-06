<?php

/**
 * Authentication/login related methods
 */
class vB5_Auth
{
	/**
	 * Sets cookies needed for authentication
	 *
	 * @param	array	$loginInfo - array of information returned from
	 *			the user::login api method
	 */
	public static function setLoginCookies(array $loginInfo, $loginType = '')
	{
		// remember me option keeps you logged in for 30 days
		$expire = (isset($_POST['rememberme']) AND $_POST['rememberme']) ? 30 : 0;

		vB5_Cookie::set('sessionhash', $loginInfo['sessionhash'], $expire, true);

		if ($loginType === 'cplogin')
		{
			vB5_Cookie::set('cpsession', $loginInfo['cpsession']);
		}

		if (isset($_POST['rememberme']) AND $_POST['rememberme'])
		{
			// in frontend we set these cookies only if rememberme is on
			vB5_Cookie::set('password', $loginInfo['password'], $expire);
			vB5_Cookie::set('userid', $loginInfo['userid'], $expire);
		}
	}

	/**
	 * Redirects the user back to where they were after logging in
	 */
	public static function doLoginRedirect()
	{
		$url = '';
		if (isset($_POST['url']) && $_POST['url'])
		{
			$url = base64_decode(trim($_POST['url']));
		}
		if (!$url OR strpos($url, '/auth/') !== false OR strpos($url, '/register') !== false)
		{
			$url = vB5_Config::instance()->baseurl;
		}

		$templater = new vB5_Template('login_redirect');
		$templater->register('url', filter_var($url, FILTER_SANITIZE_STRING));
		echo $templater->render();
	}
}
