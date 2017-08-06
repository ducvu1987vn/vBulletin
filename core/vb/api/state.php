<?php
if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/


/**
 * vB_Api_State
 *
 * @package vBApi
 * @access public
 */
class vB_Api_State extends vB_Api
{
	protected $disableWhiteList = array('checkBeforeView');
	
	/*
	 * Route
	 *
	 * @var	string
	 */
	protected $route = array();

	/*
	 * Route Segments
	 *
	 * @var	string
	 */
	protected $segments = array();

	/*
	 * Valid Locations
	 *
	 * @var	array
	 */
	protected $location = array(
		'login'   => false,
		'ajax' => false,
		'settings' => false,
		'lostpw' => false,
		'admincp' => false,
	);

	/*
	 *
	 */
	protected function __construct()
	{
		parent::__construct();
		$this->assertor = vB::getDbAssertor();
	}
	
	public function checkBeforeView($route = null)
	{
		if (!empty($route))
		{
			$this->setRoute($route);
		}
		
		if (
			(
				($result = $this->checkForumClosed()) !== false 
					AND 
				(empty($route) OR !$this->location['admincp'])
			)
				OR
			($route AND !$this->location['admincp'] AND
				(
					($result = $this->checkForumBusy()) !== false
						OR
					($result = $this->checkPasswordExpiry()) !== false
						OR
					($result = $this->checkPasswordEqualsUsername()) !== false
						OR
					($result = $this->checkProfileUpdate()) !== false
						OR
					($result = $this->checkIpBan()) !== false
				)
			)
		)
		{
			$phrasevars = array('message');
			if (isset($result['error']))
			{
				$phrasevars[] = $result['error'];
			}
			$phrases =  vB_Api::instanceInternal('phrase')->fetch($phrasevars);
			$returnvalue = array('title' => $phrases['message']);
			if (isset($result['option']))
			{
				$returnvalue['msg'] = vB::getDatastore()->getOption($result['option']);
				$returnvalue['state'] = $result['option'];
				return $returnvalue;
			}
			else if (isset($result['error']))
			{
				if (isset($result['args']))
				{
					$returnvalue['msg'] = vsprintf($phrases[$result['error']], $result['args']);
				}
				else
				{
					$returnvalue['msg'] = $phrases[$result['error']];
				}
				$returnvalue['state'] = $result['error'];
				return $returnvalue;
			}
		}

		return false;
	}

	/*
	 * Set route info since these functions are called during the route verification process
	 *
	 * @param	string	Route Controller
	 * @param	string	Route Action
	 *
	 */
	protected function setRoute($route)
	{
		$this->route = $route;
		$this->route['routeguid'] = isset($this->route['routeguid']) ? strtolower($this->route['routeguid']) : '';
		$this->route['controller'] = isset($this->route['controller']) ? strtolower($this->route['controller']) : '';
		$this->route['action'] = isset($this->route['action']) ? strtolower($this->route['action']) : '';

		$this->setLocation();
	}

	/*
	 * Set location infomation 
	 *
	 */
	protected function setLocation()
	{
		$this->location['login'] = ($this->route['controller'] == 'auth');

		$this->location['ajax'] = ($this->route['routeguid'] == 'vbulletin-4ecbdacd6a3d43.49233131');
		$this->location['lostpw'] = ($this->route['routeguid'] == 'vbulletin-4ecbdacd6a6f13.66635712');
		$this->location['admincp'] = ($this->route['routeguid'] == 'vbulletin-4ecbdacd6aa7c8.79724467');
		$this->location['contactus'] = ($this->route['routeguid'] == 'vbulletin-4ecbdacd6a6f13.66635713');

		$this->location['settings'] = (
			($this->route['routeguid'] == 'vbulletin-4ecbdacd6a9307.24480802')
				OR
			($this->route['controller'] == 'profile' AND $this->route['action'] == 'actionsaveaccountsettings')
		);

		if ($this->location['ajax'] AND isset($this->route['arguments']['route']))
		{
			// Split the route and also provide full lowercase version (ajax calls).
			$this->segments = explode('/', strtolower($this->route['arguments']['route']));
			$this->segments['route'] = implode('/', $this->segments);
		}
	}

	/**
	 * Check if Forum is closed. Allows administrators and login actions to bypass.
	 *
	 * @return	mixed	error phrase on success, false on failure
	 */
	protected function checkForumClosed()
	{
		if (!defined('BYPASS_FORUM_DISABLED')
				AND
			!vB::getDatastore()->getOption('bbactive')
				AND
			!vB::getUserContext()->isAdministrator()
				AND
			!$this->location['login'] // Login
		)
		{
			return array('option' => 'bbclosedreason');
		}
		return false;
	}

	/*
	 * Check if forum is overloaded. Allow administrators and login actions to bypass.
	 *
	 * @return	mixed	error phrase on success, false on failure
	 */
	protected function checkForumBusy()
	{
		if ($this->serverOverloaded() AND !vB::getUserContext()->isAdministrator() AND !$this->location['login'])
		{
			return array('error' => 'toobusy');
		}
		return false;
	}

	/*
	 * Check password is the same as username
	 */
	protected function checkPasswordEqualsUsername()
	{
		if (!defined('ALLOW_SAME_USERNAME_PASSWORD') AND vB::getCurrentSession()->fetch_userinfo_value('userid'))
		{
			// save the resource on md5'ing if the option is not enabled or guest
			if (vB::getCurrentSession()->fetch_userinfo_value('password') == md5(md5(vB::getCurrentSession()->fetch_userinfo_value('username')) . vB::getCurrentSession()->fetch_userinfo_value('salt')))
			{
				if (!$this->location['settings'] AND !$this->location['login'])
				{
					return array(
						'error' => 'username_same_as_password',
						'args' => array(
							vB5_Config::instance()->baseurl . '/settings/account'
					));
				}
			}
		}
		return false;
	}

	/*
	 * Check profile fields
	 */
	protected function checkProfileUpdate()
	{
		if (vB::getCurrentSession()->get('profileupdate') AND !$this->location['settings'] AND !$this->location['login'])
		{
			return array(
				'error' => 'updateprofilefields',
				'args' => array(
					vB5_Config::instance()->baseurl . '/settings/account'
			));
		}
		return false;
	}

	/*
	 * Check IP Ban
	 */
	protected function checkIpBan()
	{
		$user_ipaddress = IPADDRESS . '.';
		$options = vB::getDatastore()->get_value('options');
		$ajaxroute = isset($this->segments['route']) ? $this->segments['route'] : '';

		if ($options['enablebanning'] == 1 AND $options['banip'] = trim($options['banip']))
		{
			$addresses = preg_split('#\s+#', $options['banip'], -1, PREG_SPLIT_NO_EMPTY);
			foreach ($addresses AS $banned_ip)
			{
				if (strpos($banned_ip, '*') === false AND $banned_ip{strlen($banned_ip) - 1} != '.')
				{
					$banned_ip .= '.';
				}

				$banned_ip_regex = str_replace('\*', '(.*)', preg_quote($banned_ip, '#'));
				if (preg_match('#^' . $banned_ip_regex . '#U', $user_ipaddress))
				{
					$excluded = 	
					(
						$this->location['contactus']
						OR $ajaxroute == '/api/phrase/fetch'
						OR $ajaxroute == '/api/contactus/sendmail'
					);

					if (!$excluded)
					{
						return array('error' => 'banip');
					}
				}
			}
		}
		return false;
	}

	/*
	 * Check if user's password is expired
	 *
	 * @return	mixed	error phrase name on success, false on failure
	 */
	protected function checkPasswordExpiry()
	{
		$usergroupid = vB::GetCurrentSession()->fetch_userinfo_value('usergroupid');
		$usergroup = vB_Api::instanceInternal('usergroup')->fetchUsergroupByID($usergroupid);
		$passwordexpires = $usergroup['passwordexpires'];

		if (vB::getCurrentSession()->fetch_userinfo_value('userid') AND $passwordexpires)
		{
			$passworddaysold = floor((vB::getRequest()->getTimeNow() - vB::GetCurrentSession()->fetch_userinfo_value('passworddate')) / 86400);

			if ($passworddaysold >= $passwordexpires)
			{
				if (!($this->location['settings']
					OR $this->location['login']
					OR $this->location['lostpw']
//					OR $this->location['admincp'] // Now checked in checkBeforeView()
				))
				{
					return array(
						'error' => 'passwordexpired',
						'args' => array(
							$passworddaysold,
							vB5_Config::instance()->baseurl . '/settings/account'
					));
				}
			}
		}
		return false;
	}

	/**
	 * Determines if the server is over the defined load limits
	 *
	 * @return	bool
	*/
	protected function serverOverloaded()
	{
		$loadcache = vB::getDatastore()->getValue('loadcache');

		if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN' AND vB::getDatastore()->getOption('loadlimit') > 0)
		{
			if (!is_array($loadcache) OR $loadcache['lastcheck'] < (vB::getRequest()->getTimeNow() - vB::getDatastore()->getOption('recheckfrequency')))
			{
				$this->updateLoadavg();
			}

			if ($loadcache['loadavg'] > vB::getDatastore()->getOption('loadlimit'))
			{
				return true;
			}
		}

		return false;
	}

	/*
	 * Update the loadavg cache
	 *
	 */
	protected function updateLoadavg()
	{
		$loadcache = array();

		if (function_exists('exec') AND $stats = @exec('uptime 2>&1') AND trim($stats) != '' AND preg_match('#: ([\d.,]+),?\s+([\d.,]+),?\s+([\d.,]+)$#', $stats, $regs))
		{
			$loadcache['loadavg'] = $regs[2];
		}
		else if (@file_exists('/proc/loadavg') AND $filestuff = @file_get_contents('/proc/loadavg'))
		{
			$loadavg = explode(' ', $filestuff);
			$loadcache['loadavg'] = $loadavg[1];
		}
		else
		{
			$loadcache['loadavg'] = 0;
		}

		$loadcache['lastcheck'] = vB::getRequest()->getTimeNow();
		vB::getDatastore()->build('loadcache', serialize($loadcache), 1);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 65107 $
|| ####################################################################
\*======================================================================*/
