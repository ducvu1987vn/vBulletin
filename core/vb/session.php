<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright  2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Class to handle sessions
*
* Creates, updates, and validates sessions; retrieves user info of browsing user
*
* @package	vBulletin
* @version	$Revision: 43053 $
* @date		$Date: 2011-04-25 17:02:53 -0300 (Mon, 25 Apr 2011) $
*/
abstract class vB_Session
{
	/**
	 *
	 * @var vB_dB_Assertor
	 */
	protected $dBAssertor = null;

	/**
	 *
	 * @var vB_Datastore
	 */
	protected $datastore = null;

	/**
	 * @var array
	 */
	protected $config;

	/**
	* The individual session variables. Equivalent to $session from the past.
	*
	* @var	array
	*/
	protected $vars = array();

	/**
	* A list of variables in the $vars member that are in the database. Includes their types.
	*
	* @var	array
	*/
	protected $db_fields = array(
		'sessionhash'   => vB_Cleaner::TYPE_STR,
		'userid'        => vB_Cleaner::TYPE_INT,
		'host'          => vB_Cleaner::TYPE_STR,
		'idhash'        => vB_Cleaner::TYPE_STR,
		'lastactivity'  => vB_Cleaner::TYPE_INT,
		'location'      => vB_Cleaner::TYPE_STR,
		'styleid'       => vB_Cleaner::TYPE_INT,
		'languageid'    => vB_Cleaner::TYPE_INT,
		'loggedin'      => vB_Cleaner::TYPE_INT,
		'inforum'       => vB_Cleaner::TYPE_INT,
		'inthread'      => vB_Cleaner::TYPE_INT,
		'incalendar'    => vB_Cleaner::TYPE_INT,
		'badlocation'   => vB_Cleaner::TYPE_INT,
		'useragent'     => vB_Cleaner::TYPE_STR,
		'bypass'        => vB_Cleaner::TYPE_INT,
		'profileupdate' => vB_Cleaner::TYPE_INT,
		'apiclientid'   => vB_Cleaner::TYPE_INT,
		'apiaccesstoken'=> vB_Cleaner::TYPE_STR,
	);

	/**
	* An array of changes. Used to prevent superfluous updates from being made.
	*
	* @var	array
	*/
	protected $changes = array();

	/**
	* Whether the session was created or existed previously
	*
	* @var	bool
	*/
	// todo: this is a public attribute to avoid breaking some references that even set this value.
	// Replace with getter and check if we can avoid setting this outside the class constructor.
	public $created = false;

	/**
	* Information about the user that this session belongs to.
	*
	* @var	array
	*/
	protected $userinfo = null;

	/**
	* Is the sessionhash to be passed through URLs?
	*
	* @var	boolean
	*/
	// todo: this is a public attribute to avoid breaking most references in code. Replace with a getter (task 6167)
	public $visible = true;

	/*
	 *This should *never* change during a session
	 *@var string
	 */
	protected $sessionIdHash = null;

	/**
	 * cpsessionhash is a special session hash for admins and moderators
	 *
	 * @var string
	 */
	protected $cpsessionHash = '';

	// This functions are used to fill in the skeleton of the constructor using template method pattern
	/**
	 * Sets the attribute sessionIdHash
	 */
	protected function createSessionIdHash()
	{
		// API session idhash won't have User Agent compiled.
		$this->sessionIdHash = md5($this->fetch_substr_ip(vB::getRequest()->getAltIp()));
	}

	protected function loadExistingSession($sessionhash, $userid, $password)
	{
		$gotsession = false;

		// try to fetch stored session first and save it in $this->vars
		if ($this->fetchStoredSession($sessionhash))
		{
			$gotsession = true;
			$this->created = false;

			// found a session - get the userinfo
			if ($this->vars['userid'] != 0)
			{
				$useroptions = array();
				if (defined('IN_CONTROL_PANEL'))
				{
					$useroptions[] = vB_Api_User::USERINFO_ADMIN;
					$userinfo = vB_Library::instance('user')->fetchUserinfo($this->vars['userid'], $useroptions, (!empty($languageid) ? $languageid : $this->vars['languageid']));
				}
				else
				{
					$userinfo = vB_Library::instance('user')->fetchUserWithPerms($this->vars['userid'], (!empty($languageid) ? $languageid : $this->vars['languageid']));
				}
				$this->userinfo =& $userinfo;
			}
		}

		if ($gotsession == false OR empty($this->vars['userid']))
		{
			// try to use remember me
			$useroptions = array();
			if (defined('IN_CONTROL_PANEL'))
			{
				$useroptions[] = 'admin';
			}
			$gotsession = $this->rememberSession($userid, $password, $useroptions);
		}

		// at this point, we're a guest, so lets try to *find* a session
		// you can prevent this check from being run by passing in a userid with no password
		if ($gotsession == false AND $userid == 0)
		{
			try
			{
				$session = $this->dBAssertor->getRow('session',
						array(
							'userid' => 0,
							'host' => vB::getRequest()->getSessionHost(),
							'idhash' => $this->getSessionIdHash(),
							)
						);
			}
			catch (Exception $e)
			{}
			if (!empty($session))
			{
				$gotsession = true;

				$this->vars =& $session;
				$this->created = false;
			}
		}

		return $gotsession;
	}

	// called from loadExistingSession
	// check vB_Session_Api for different behavior
	protected function fetchStoredSession($sessionhash)
	{
		if ($sessionhash)
		{
//			$test = ($this->vars = $db->query_first_slave("
//					SELECT *
//					FROM " . TABLE_PREFIX . "session
//					WHERE sessionhash = '" . $db->escape_string($sessionhash) . "'
//						AND lastactivity > " . (TIMENOW - $registry->options['cookietimeout']) . "
//						AND idhash = '" . $this->registry->db->escape_string(SESSION_IDHASH) . "'
//				") AND $this->fetch_substr_ip($this->vars['host']) == $this->fetch_substr_ip(SESSION_HOST));
			$options = $this->datastore->get_value('options');
			$request = vB::getRequest();
			$test = ($this->vars = $this->dBAssertor->getRow('session',
					array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						vB_dB_Query::CONDITIONS_KEY => array(
							array(
								'field' => 'sessionhash',
								'value'	=> $sessionhash,
								'operator' => vB_dB_Query::OPERATOR_EQ
							),
							array(
								'field' => 'lastactivity',
								'value' => ($request->getTimeNow() - $options['cookietimeout']),
								'operator' => vB_dB_Query::OPERATOR_GT
							),
							array(
								'field' => 'idhash',
								'value' => $this->getSessionIdHash(),
								'operator' => vB_dB_Query::OPERATOR_EQ
							)
						)
					))
				  AND $this->fetch_substr_ip($this->vars['host']) == $this->fetch_substr_ip($request->getSessionHost()));

			return $test;
		}
		else
		{
			return false;
		}
	}

	// called from loadExistingSession
	// check vB_Session_Api for different behavior
	protected function rememberSession($userid, $password, $useroptions)
	{
		// or maybe we can use a cookie..
		if ($userid AND $password)
		{
			if (empty($useroptions))
			{
				$userinfo = vB_Library::instance('user')->fetchUserWithPerms($userid, empty($this->vars['languageid']) ? 0 : $this->vars['languageid']);
			}
			else
			{
				$userinfo = vB_Library::instance('user')->fetchUserinfo($userid, $useroptions, empty($this->vars['languageid']) ? 0 : $this->vars['languageid']);
			}

			if (md5($userinfo['password'] . vB_Request_Web::COOKIE_SALT) == $password)
			{
				// combination is valid
				if (!empty($this->vars['sessionhash']))
				{
					$this->dBAssertor->delete('session', array('sessionhash'=>$this->vars['sessionhash']), TRUE);
				}

				$this->vars = $this->fetch_session($userinfo['userid']);
				$this->created = true;
				$this->userinfo =& $userinfo;


				return true;
			}
		}

		return false;
	}

	/**
	* Constructor. Attempts to grab a session that matches parameters, but will create one if it can't.
	*
	* @param	string		Previously specified sessionhash
	* @param	integer		User ID (passed in through a cookie)
	* @param	string		Password, must arrive in cookie format: md5(md5(md5(password) . salt) . 'abcd1234')
	* @param	integer		Style ID for this session
	* @param	integer		Language ID for this session
	*/
	protected function __construct(&$dBAssertor, &$datastore, &$config, $sessionhash = '', $userid = 0, $password = '', $styleid = 0, $languageid = 0)
	{
		$this->dBAssertor = & $dBAssertor;
		$this->datastore = & $datastore;
		$this->config = & $config;
		$request = vB::getRequest();

		$userid = intval($userid);
		$styleid = intval($styleid);
		$languageid = intval($languageid);

		$this->createSessionIdHash();

		if (!$this->loadExistingSession($sessionhash, $userid, $password)) {
			// well, nothing worked, time to create a new session
			$this->vars = $this->fetch_session(0);
			$this->created = true;
		}

		$this->vars['dbsessionhash'] = $this->vars['sessionhash'];

		$this->set('styleid', $styleid);
		$this->set('languageid', $languageid);
		if ($this->created == false)
		{
			$this->set('useragent', $request->getUserAgent());
			$this->set('lastactivity', $request->getTimeNow());
			if (!defined('LOCATION_BYPASS'))
			{
				$this->set('location', WOLPATH);
			}
			$this->set('bypass', SESSION_BYPASS);
		}
	}

	/**
	 * Returns a new session of the type specified by defined constants
	 *
	 * @global array $VB_API_PARAMS_TO_VERIFY - Defined in api.php
	 * @global array $VB_API_REQUESTS - Defined in api.php
	 * @param vB_dB_Assertor $dBAssertor
	 * @param vB_Datastore $datastore
	 * @param array $config
	 * @param string $sessionhash
	 * @param int $userid
	 * @param string $password
	 * @param int $styleid
	 * @param int $languageid
	 * @return vB_Session
	 */
	// this is used by legacy code, not vb5 API
	public static function getNewSession(&$dBAssertor, &$datastore, &$config, $sessionhash = '', $userid = 0, $password = '', $styleid = 0, $languageid = 0)
	{
		if (defined('SKIP_SESSIONCREATE') AND SKIP_SESSIONCREATE)
		{
			$session = new vB_Session_Skip($dBAssertor, $datastore, $config, $styleid, $languageid);
		}
		else if (defined('VB_API') AND VB_API)
		{
			global $VB_API_PARAMS_TO_VERIFY, $VB_API_REQUESTS;
			$session = new vB_Session_Api($dBAssertor, $datastore, $config, $VB_API_PARAMS_TO_VERIFY, $VB_API_REQUESTS);
		}
		else
		{
			$session = new vB_Session_Web($dBAssertor, $datastore, $config, $sessionhash, $userid, $password, $styleid, $languageid);
		}

		return $session;
	}

	/**
	 * Returns the sessionIdHash
	 * @return string
	 */
	public function getSessionIdHash()
	{
		return $this->sessionIdHash;
	}

	/**
	* Saves the session into the database by inserting it or updating an existing one.
	*/
	public function save()
	{
		$cleaned = array();
		foreach ($this->db_fields AS $fieldname => $cleantype)
		{
			switch ($cleantype)
			{
				case vB_Cleaner::TYPE_INT:
					$clean = isset($this->vars["$fieldname"]) ? intval($this->vars["$fieldname"]) : 0;
					break;
				case vB_Cleaner::TYPE_STR:
				default:
					// will be escaped by assertor
					$clean = isset($this->vars["$fieldname"]) ? $this->vars["$fieldname"] : '';
			}
			$cleaned["$fieldname"] = $clean;
		}

		// since the sessionhash can be blanked out, lets make sure we pull from "dbsessionhash"
		$cleaned['sessionhash'] = $this->vars['dbsessionhash'];

		if ($this->created == true)
		{

			$this->dBAssertor->insertIgnore('session', $cleaned);
		}
		else
		{
			// update query

			unset($this->changes['sessionhash']); // the sessionhash is not updateable
			$update = array();
			foreach ($cleaned AS $key => $value)
			{
				if (!empty($this->changes["$key"]))
				{
					$update[$key] = $value;
				}
			}

			if (sizeof($update) > 0)
			{
				// note that $cleaned['sessionhash'] has been escaped as necessary above!
				$this->dBAssertor->update('session', $update, array('sessionhash'=>$cleaned['sessionhash']));
			}
		}
		$this->changes = array();
	}

	/**
	* Sets a session variable and updates the change list.
	*
	* @param	string	Name of session variable to update
	* @param	string	Value to update it with
	*/
	public function set($key, $value)
	{
		if (!isset($this->vars["$key"]) OR $this->vars["$key"] != $value)
		{
			$this->vars["$key"] = $value;
			$this->changes["$key"] = true;
		}
	}

	/**
	 * Gets a session variable.
	 *
 	 * @param	string - Name of session variable
	 * @return	mixed - Value of the key, NULL if not found
	 */
	public function get($key)
	{
		return isset($this->vars["$key"]) ? $this->vars["$key"] : NULL;
	}

	/**
	 * Returns whether the session was created
	 * @return bool
	 */
	public function isCreated()
	{
		return $this->created;
	}

	// this is used by templates class
	/**
	 * Returns an array with all session vars
	 * @return array
	 */
	public function getAllVars()
	{
		return $this->vars;
	}

	public function setSessionVars($userId)
	{
		$this->vars = $this->fetch_session($userId);
	}

	/**
	* Sets the session visibility (whether session info shows up in a URL). Updates are put in the $vars member.
	*
	* @param	bool	Whether the session elements should be visible.
	*/
	public function set_session_visibility($invisible)
	{
		$this->visible = !$invisible;

		if ($invisible)
		{
			$this->vars['sessionhash'] = '';
			$this->vars['sessionurl'] = '';
			$this->vars['sessionurl_q'] = '';
			$this->vars['sessionurl_js'] = '';
		}
		else
		{
			$this->vars['sessionurl'] = 's=' . $this->vars['dbsessionhash'] . '&amp;';
			$this->vars['sessionurl_q'] = '?s=' . $this->vars['dbsessionhash'];
			$this->vars['sessionurl_js'] = 's=' . $this->vars['dbsessionhash'] . '&';
		}
	}


	/**
	 * Get the session visibility
	 *
	 */
	public function isVisible()
	{
		return $this->visible;
	}

	/**
	* Fetches a valid sessionhash value, not necessarily the one tied to this session.
	*
	* @return	string	32-character sessionhash
	*/
	public function fetch_sessionhash()
	{
		return md5(uniqid(microtime(), true));
	}

	/**
	* Returns the IP address with the specified number of octets removed
	*
	* @param	string	IP address
	*
	* @return	string	truncated IP address
	*/
	protected function fetch_substr_ip($ip, $length = null)
	{
		$options = $this->datastore->get_value('options');
		if ($length === null OR $length > 3)
		{
			$length = $options['ipcheck'];
		}
		return implode('.', array_slice(explode('.', $ip), 0, 4 - $length));
	}

	/**
	* Fetches a default session. Used when creating a new session.
	*
	* @param	integer	User ID the session should be for
	*
	* @return	array	Array of session variables
	*/
	protected function fetch_session($userid = 0)
	{
		$sessionhash = $this->fetch_sessionhash();

		$request = vB::getRequest();

		$session = array(
			'sessionhash'   => $sessionhash,
			'dbsessionhash' => $sessionhash,
			'userid'        => intval($userid),
			'host'          => (empty($request) ? '' : $request->getSessionHost()),
			'idhash'        => $this->getSessionIdHash(),
			'lastactivity'  => (empty($request) ? '' : $request->getTimeNow()),
			'location'      => (defined('LOCATION_BYPASS') OR !defined('WOLPATH')) ? '' : WOLPATH, //defined('LOCATION_BYPASS') ? '' : WOLPATH,
			'styleid'       => 0,
			'languageid'    => 0,
			'loggedin'      => intval($userid) ? 1 : 0,
			'inforum'       => 0,
			'inthread'      => 0,
			'incalendar'    => 0,
			'badlocation'   => 0,
			'profileupdate' => 0,
			'useragent'     => (empty($request) ? '' : $request->getUserAgent()),
			'bypass'        => (defined('SESSION_BYPASS')) ? SESSION_BYPASS : false //SESSION_BYPASS
		);
		return $session;
	}

	/** Called after setting phrasegroups, adds new phrases to userinfo
	 */
	public function loadPhraseGroups()
	{
		$options = $this->datastore->getValue('options');
		$phraseinfo = vB_Language::getPhraseInfo((!empty($this->vars['languageid']) ? $this->vars['languageid'] : intval($options['languageid'])));

		if (!empty($phraseinfo))
		{ // can't phrase this since we can't find the language
			foreach($phraseinfo AS $_arrykey => $_arryval)
			{
				$this->userinfo[$_arrykey] = $_arryval;
			}
			unset($phraseinfo);
		}
		else
		{
			trigger_error('The requested language does not exist, reset via tools.php.', E_USER_ERROR);
		}
	}

	/** Loads basic language information
	 */
	public function loadLanguage()
	{
		$allLanguages = vB::getDatastore()->getValue('languagecache');
		if(is_array($allLanguages) AND !array_key_exists($this->vars['languageid'], $allLanguages)){
			$this->vars['languageid'] = vB::getDatastore()->getOption('languageid');
		}

		$language = vB_Language::getPhraseInfo($this->vars['languageid']);
		if(!empty($language['lang_options']))
		{
			//convert bitfields to arrays for external clients.
			$bitfields = vB::getDatastore()->getValue('bf_misc_languageoptions');
			$lang_options = $language['lang_options'];
			$language['lang_options'] = array();
			foreach ($bitfields as $key => $value)
			{
				$language['lang_options'][$key] = (bool) ($lang_options & $value);
			}
		}

		if (!empty($language))
		{
			if (!empty($this->userinfo))
			{
				foreach($language AS $_arrykey => $_arryval)
				{
					$this->userinfo[$_arrykey] = $_arryval;
				}
			}
			else
			{
				foreach($language AS $_arrykey => $_arryval)
				{
					$this->vars[$_arrykey] = $_arryval;
				}
			}
		}
		else if (!defined('VB_UNITTEST'))
		{
			trigger_error('The requested language does not exist, reset via tools.php.', E_USER_ERROR);
		}
	}
	/**
	* Returns appropriate user info for the owner of this session.
	*
	* @return	array	Array of user information.
	*/
	public function &fetch_userinfo()
	{
		if ($this->userinfo)
		{
			// we already calculated this
			if (empty($this->userinfo['lang_options']))
			{
				$this->loadLanguage();
			}

			return $this->userinfo;
		}
		else if ($this->vars['userid'] AND !defined('SKIP_USERINFO'))
		{
			// user is logged in
			$useroptions = array();
			if (defined('IN_CONTROL_PANEL'))
			{
				$useroptions[] = vB_Api_User::USERINFO_ADMIN;
				$this->userinfo = vB_Library::instance('user')->fetchUserInfo($this->vars['userid'], $useroptions, $this->vars['languageid']);
			}
			else
			{
				$this->userinfo = vB_Library::instance('user')->fetchUserWithPerms($this->vars['userid'], $this->vars['languageid']);
			}
			$this->loadLanguage();
			return $this->userinfo;
		}
		else
		{
			$options = $this->datastore->getValue('options');
			$bf_misc_useroptions = $this->datastore->getValue('bf_misc_useroptions');

			// guest setup
			$this->userinfo = array(
				'userid'         => 0,
				'usergroupid'    => 1,
				'username'       => (!empty($_REQUEST['username']) ? vB_String::htmlSpecialCharsUni($_REQUEST['username']) : ''),
				'password'       => '',
				'email'          => '',
				'emailstamp'     => 0,
				'styleid'        => $this->vars['styleid'],
				'languageid'     => $this->vars['languageid'],
				'lastactivity'   => $this->vars['lastactivity'],
				'daysprune'      => 0,
				'timezoneoffset' => $options['timeoffset'],
				'dstonoff'       => $options['dstonoff'],
				'showsignatures' => 1,
				'showavatars'    => 1,
				'showimages'     => 1,
				'showusercss'    => 1,
				'dstauto'        => 0,
				'maxposts'       => -1,
				'startofweek'    => 1,
				'threadedmode'   => $options['threadedmode'],
				'securitytoken'  => 'guest',
				'securitytoken_raw'  => 'guest',
			);

			$this->userinfo['options'] =
										$bf_misc_useroptions['showsignatures'] | $bf_misc_useroptions['showavatars'] |
										$bf_misc_useroptions['showimages'] | $bf_misc_useroptions['dstauto'] |
										$bf_misc_useroptions['showusercss'];
			$this->loadLanguage();
			if (!$this->userinfo['username'])
			{
				$globalPhrases = unserialize($this->userinfo['phrasegroup_global']);
				$this->userinfo['username'] = $globalPhrases['guest'];
			}
			return $this->userinfo;
		}
	}

	/**
	 * Returns appropriate value from the user info array for the owner of this session.
	 *
	 * @return mix	value of user information.
	 */
	public function &fetch_userinfo_value($value)
	{
		$null = null;	// PHP will (can) complain if you return null by reference
		$userinfo = $this->fetch_userinfo();
		if (isset($userinfo[$value]))
		{
			return $userinfo[$value];
		}
		else
		{
			return $null;
		}
	}

	/**
	* Updates the last visit and last activity times for guests and registered users (differently).
	* Last visit is set to the last activity time (before it's updated) only when a certain
	* time has lapsed. Last activity is always set to the specified time.
	*
	* @param	integer	Time stamp for last visit time (guest only)
	* @param	integer	Time stamp for last activity time (guest only)
	* @return	array	Updated values for setting cookies (guest only)
	*/
	public function doLastVisitUpdate($lastvisit = 0, $lastactivity = 0)
	{
		$options = $this->datastore->getValue('options');
		$request = vB::getRequest();
		$timeNow = $request->getTimeNow();

		$cookies = array();

		// update last visit/activity stuff
		if ($this->vars['userid'] == 0)
		{
			// guest -- emulate last visit/activity for registered users by cookies
			if ($lastvisit)
			{
				// we've been here before
				$this->userinfo['lastactivity'] = ($lastactivity ? intval($lastactivity) : intval($lastvisit));

				// here's the emulation
				if ($timeNow - $this->userinfo['lastactivity'] > $options['cookietimeout'])
				{
					// update lastvisit
					$this->userinfo['lastvisit'] = $this->userinfo['lastactivity'];
					$cookies['lastvisit'] = $this->userinfo['lastactivity'];
				}
				else
				{
					// keep lastvisit value
					$this->userinfo['lastvisit'] = intval($lastvisit);
				}
			}
			else
			{
				// first visit!
				$this->userinfo['lastvisit'] = $timeNow;
				$cookies['lastvisit'] = $timeNow;
			}

			// lastactivity is always now
			$this->userinfo['lastactivity'] = $timeNow;
			$cookies['lastactivity'] = $timeNow;

			return $cookies;
		}
		else
		{
			// registered user
			if (!SESSION_BYPASS)
			{
				if ($timeNow - $this->userinfo['lastactivity'] > $options['cookietimeout'])
				{
					// see if session has 'expired' and if new post indicators need resetting
					$this->dBAssertor->shutdownQuery('updateLastVisit',
							array(
								'timenow' => $timeNow,
								'userid' => $this->userinfo['userid']
							),
							'lastvisit');

					$this->userinfo['lastvisit'] = $this->userinfo['lastactivity'];
				}
				else
				{
					// if this line is removed (say to be replaced by a cron job, you will need to change all of the 'online'
					// status indicators as they use $userinfo['lastactivity'] to determine if a user is online which relies
					// on this to be updated in real time.
					$this->dBAssertor->update('user', array('lastactivity'=>$timeNow), array('userid'=>$this->userinfo['userid']), 'lastvisit');
				}
			}

			// we don't need to set cookies for registered users
			return null;
		}
	}

	/**
	 * Create new cpsession for the user and insert it into database or fetch current existing one
	 *
	 * @param bool $renew Whether to renew cpsession hash (Create a new one and drop the old one)
	 *
	 * @throws vB_Exception
	 * @return string The new cpsession hash
	 *
	 */
	public function fetchCpsessionHash($renew = false)
	{
		if (!$this->created)
		{
			throw new vB_Exception_User('session_not_created');
		}

		if ($this->cpsessionHash)
		{
			if (!$renew)
			{
				return $this->cpsessionHash;
			}
			else
			{
				// Drop the old cp session record
				$this->dBAssertor->delete('cpsession', array('hash' => $this->cpsessionHash));
			}
		}

		$this->cpsessionHash = $this->fetch_sessionhash();
		$this->dBAssertor->insert('cpsession', array(
			'userid' => $this->vars['userid'],
			'hash' => $this->cpsessionHash,
			'dateline' => vB::getRequest()->getTimeNow()
		));

		return $this->cpsessionHash;
	}

	public function setCpsessionHash($cpsessionhash)
	{
		$this->cpsessionHash = $cpsessionhash;
	}

	/**
	 * Validate cpsession
	 *
	 * @param bool $updatetimeout Whether to update the table to reset the timeout
	 *
	 * @return bool
	 */
	public function validateCpsession($updatetimeout = true)
	{
		$vboptions = vB::getDatastore()->getValue('options');
		$timenow = vB::getRequest()->getTimeNow();
		$usercontext = vB::getUserContext();

		// Only moderators can use the mog login part of login.php, for cases that use inlinemod but don't have this permission return true
		if (!$usercontext->getCanModerate() OR !$vboptions['enable_inlinemod_auth'])
		{
			return true;
		}

		if (empty($this->cpsessionHash))
		{
			return false;
		}
		else
		{
			$cpsession = vB::getDbAssertor()->getRow('cpsession', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'userid', 'value' => $this->vars['userid'], 'operator' => vB_dB_Query::OPERATOR_EQ),
					array('field' => 'hash', 'value' => $this->cpsessionHash, 'operator' => vB_dB_Query::OPERATOR_EQ),
					array('field' => 'dateline', 'value' => ($vboptions['timeoutcontrolpanel'] ? intval($timenow - $vboptions['cookietimeout']) : intval($timenow - 3600)), 'operator' => vB_dB_Query::OPERATOR_GT),
				)
			));

			if (!empty($cpsession))
			{
				if($updatetimeout)
				{
					vB::getDbAssertor()->update("cpsession", array('dateline' => $timenow), array('userid' => $this->vars['userid'], 'hash' => $this->cpsessionHash));
				}
				return true;
			}
		}

		return false;
	}

}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
