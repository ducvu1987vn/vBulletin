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

class vB_DataManager_User extends vB_DataManager
{
	/**
	* Array of recognised and required fields for users, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'userid'             => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_INCR, vB_DataManager_Constants::VF_METHOD, 'verify_nonzero'),
		'username'           => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_YES,  vB_DataManager_Constants::VF_METHOD),

		'email'              => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_YES,  vB_DataManager_Constants::VF_METHOD, 'verify_useremail'),
		'parentemail'        => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'emailstamp'         => array(vB_Cleaner::TYPE_UNIXTIME,   vB_DataManager_Constants::REQ_NO),

		'password'           => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_YES,  vB_DataManager_Constants::VF_METHOD),
		'passworddate'       => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_AUTO),
		'salt'               => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_AUTO, vB_DataManager_Constants::VF_METHOD),

		'usergroupid'        => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_YES,  vB_DataManager_Constants::VF_METHOD),
		'membergroupids'     => array(vB_Cleaner::TYPE_NOCLEAN,    vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD, 'verify_commalist'),
		'infractiongroupids' => array(vB_Cleaner::TYPE_NOCLEAN,    vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD, 'verify_commalist'),
		'infractiongroupid'  => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO,),
		'displaygroupid'     => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),

		'styleid'            => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'languageid'         => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),

		'options'            => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_YES),
		'privacy_options'	 => array(vB_Cleaner::TYPE_STR,		   vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'notification_options' => array(vB_Cleaner::TYPE_UINT,     vB_DataManager_Constants::REQ_NO),
		'adminoptions'       => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'showvbcode'         => array(vB_Cleaner::TYPE_INT,        vB_DataManager_Constants::REQ_NO, 'if (!in_array($data, array(0, 1, 2))) { $data = 1; } return true;'),
		'showbirthday'       => array(vB_Cleaner::TYPE_INT,        vB_DataManager_Constants::REQ_NO, 'if (!in_array($data, array(0, 1, 2, 3))) { $data = 2; } return true;'),
		'threadedmode'       => array(vB_Cleaner::TYPE_INT,        vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'maxposts'           => array(vB_Cleaner::TYPE_INT,        vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'ipaddress'          => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'referrerid'         => array(vB_Cleaner::TYPE_NOHTMLCOND, vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'posts'              => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'daysprune'          => array(vB_Cleaner::TYPE_INT,        vB_DataManager_Constants::REQ_NO),
		'startofweek'        => array(vB_Cleaner::TYPE_INT,        vB_DataManager_Constants::REQ_NO),
		'timezoneoffset'     => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_NO),
		'autosubscribe'      => array(vB_Cleaner::TYPE_INT,        vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),

		'homepage'           => array(vB_Cleaner::TYPE_NOHTML,     vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'icq'                => array(vB_Cleaner::TYPE_NOHTML,     vB_DataManager_Constants::REQ_NO),
		'aim'                => array(vB_Cleaner::TYPE_NOHTML,     vB_DataManager_Constants::REQ_NO),
		'yahoo'              => array(vB_Cleaner::TYPE_NOHTML,     vB_DataManager_Constants::REQ_NO),
		'msn'                => array(vB_Cleaner::TYPE_NOHTML,        vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'skype'              => array(vB_Cleaner::TYPE_NOHTML,     vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'google'             => array(vB_Cleaner::TYPE_NOHTML,	   vB_DataManager_Constants::REQ_NO),
		'status'             => array(vB_Cleaner::TYPE_STR,	   vB_DataManager_Constants::REQ_NO),

		'usertitle'          => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_NO),
		'customtitle'        => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO, 'if (!in_array($data, array(0, 1, 2))) { $data = 0; } return true;'),

		'ipoints'            => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'infractions'        => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'warnings'           => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),

		'joindate'           => array(vB_Cleaner::TYPE_UNIXTIME,   vB_DataManager_Constants::REQ_AUTO),
		'lastvisit'          => array(vB_Cleaner::TYPE_UNIXTIME,   vB_DataManager_Constants::REQ_NO),
		'lastactivity'       => array(vB_Cleaner::TYPE_UNIXTIME,   vB_DataManager_Constants::REQ_NO),
		'lastpost'           => array(vB_Cleaner::TYPE_UNIXTIME,   vB_DataManager_Constants::REQ_NO),
		'lastpostid'         => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),

		'birthday'           => array(vB_Cleaner::TYPE_NOCLEAN,    vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'birthday_search'    => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_AUTO),

		'reputation'         => array(vB_Cleaner::TYPE_NOHTML,     vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'reputationlevelid'  => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_AUTO),

		'avatarid'           => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'avatarrevision'     => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'profilepicrevision' => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'sigpicrevision'     => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),

		'pmpopup'            => array(vB_Cleaner::TYPE_INT,        vB_DataManager_Constants::REQ_NO),
		'pmtotal'            => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'pmunread'           => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),

		'assetposthash'      => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_NO),

		// socnet counter fields
		'profilevisits'      => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'friendcount'        => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'friendreqcount'     => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'vmunreadcount'      => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'vmmoderatedcount'   => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'pcunreadcount'      => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'pcmoderatedcount'   => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'gmmoderatedcount'   => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),

		// usertextfield fields
		'subfolders'         => array(vB_Cleaner::TYPE_NOCLEAN,    vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD, 'verify_serialized'),
		'pmfolders'          => array(vB_Cleaner::TYPE_NOCLEAN,    vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD, 'verify_serialized'),
		'searchprefs'        => array(vB_Cleaner::TYPE_NOCLEAN,    vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD, 'verify_serialized'),
		'buddylist'          => array(vB_Cleaner::TYPE_NOCLEAN,    vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD, 'verify_spacelist'),
		'ignorelist'         => array(vB_Cleaner::TYPE_NOCLEAN,    vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD, 'verify_spacelist'),
		'signature'          => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_NO),
		'rank'               => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_NO),

		// facebook fields
		'fbuserid'           => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_NO),
		'fbname'             => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_NO),
		'fbjoindate'         => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'logintype'          => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_NO, 'if (!in_array($data, array(\'vb\', \'fb\'))) { $data = \'vb\'; } return true; ')
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array(
		'options'      => 'bf_misc_useroptions',
		'adminoptions' => 'bf_misc_adminoptions',
		'notification_options' => 'bf_misc_usernotificationoptions'
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'user';

	/**#@+
	* Arrays to store stuff to save to user-related tables
	*
	* @var	array
	*/
	var $user = array();
	var $userfield = array();
	var $usertextfield = array();
	/**#@-*/


	//Primary Key
	protected $keyField = 'userid';

	/**
	* Whether or not we have inserted an administrator record
	*
	* @var	boolean
	*/
	var $insertedadmin = false;

	/**
	* Whether or not to skip some checks from the admin cp
	*
	* @var	boolean
	*/
	var $adminoverride = false;

	/**
	* Types of lists stored in usertextfield, named <X>list.
	*
	* @var	array
	*/
	var $list_types = array('buddy', 'ignore');

	/**
	* Arrays to store stuff to save to userchangelog table
	*
	* @var	array
	*/
	var $userchangelog = array();

	/**
	* We want to log or not the user changes
	*
	* @var	boolean
	*/
	var $user_changelog_state = true;

	/**
	* Which fieldchanges will be logged
	*
	* @var	array
	*/
	var $user_changelog_fields = array('username', 'usergroupid', 'membergroupids', 'email');

	protected $needRegistry = false;

	/**
	* The rawusername which is needed for verify_password
	*/
	private $rawusername = "";

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_User($registry = NULL, $errtype = vB_DataManager_Constants::ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);
		/*$this->userinfo = $this->session->fetch_userinfo();
		$this->options = vB::getDatastore()->get_value('options');*/
		// Legacy Hook 'userdata_start' Removed //
	}

	// #############################################################################
	// data verification functions

	/**
	* Verifies that the user's homepage is valid
	*
	* @param	string	URL
	*
	* @return	boolean
	*/
	function verify_homepage(&$homepage)
	{
		return (empty($homepage)) ? true : $this->verify_link($homepage, true);
	}

	/**
	* Verifies that $threadedmode is a valid value, and sets the appropriate options to support it.
	*
	* @param	integer	Threaded mode: 0 = linear, oldest first; 1 = threaded; 2 = hybrid; 3 = linear, newest first
	*
	* @return	boolean
	*/
	function verify_threadedmode(&$threadedmode)
	{
		// ensure that provided value is valid
		if (!in_array($threadedmode, array(0, 1, 2, 3)))
		{
			$threadedmode = 0;
		}

		// fix linear, newest first
		if ($threadedmode == 3)
		{
			$this->set_bitfield('options', 'postorder', 1);
			$threadedmode = 0;
		}
		// fix linear, oldest first
		else if ($threadedmode == 0)
		{
			$this->set_bitfield('options', 'postorder', 0);
		}

		// set threadedmode to linear / oldest first if threadedmode is disabled
		if ($threadedmode > 0 AND !$this->options['allowthreadedmode'])
		{
			$this->set_bitfield('options', 'postorder', 0);
			$threadedmode = 0;
		}

		return true;
	}

	/**
	* Verifies that an autosubscribe choice is valid and workable
	*
	* @param	integer	Autosubscribe choice: (-1: no subscribe; 0: subscribe, no email; 1: instant email; 2: daily email; 3: weekly email; 4: instant icq notification (dodgy))
	*
	* @return	boolean
	*/
	function verify_autosubscribe(&$autosubscribe)
	{
		// check that the subscription choice is valid
		switch ($autosubscribe)
		{
			// the choice is good
			case -1:
			case 0:
			case 1:
			case 2:
			case 3:
				break;

			// check that ICQ number is valid
			case 4:
				if (!preg_match('#^[0-9\-]+$', $this->fetch_field('icq')))
				{
					// icq number is bad
					$this->set('icq', '');
					$autosubscribe = 1;
				}
				break;

			// all other options
			default:
				$autosubscribe = -1;
				break;
		}

		return true;
	}

	/**
	* Verifies the value of user.maxposts, setting the forum default number if the value is invalid
	*
	* @param	integer	Maximum posts per page
	*
	* @return	boolean
	*/
	function verify_maxposts(&$maxposts)
	{
		if (!in_array($maxposts, explode(',', $this->options['usermaxposts'])))
		{
			$maxposts = -1;
		}

		return true;
	}

	/**
	* Verifies a valid reputation value, and sets the appropriate reputation level
	*
	* @param	integer	Reputation value
	*
	* @return	boolean
	*/
	function verify_reputation(&$reputation)
	{
		if ($reputation > 2147483647)
		{
			$reputation = 2147483647;
		}
		else if ($reputation < -2147483647)
		{
			$reputation = -2147483647;
		}
		else
		{
			$reputation = intval($reputation);
		}

		/*$reputationlevel = $this->dbobject->query_first("
			SELECT reputationlevelid
			FROM " . TABLE_PREFIX . "reputationlevel
			WHERE $reputation >= minimumreputation
			ORDER BY minimumreputation DESC
			LIMIT 1
		");*/
		$reputationlevel = $this->assertor->getRow('vBForum:reputationlevel', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'minimumreputation', 'value' => $reputation, 'operator' => 'LTE')
				)
		), array('field' => array('minimumreputation'), 'direction' => array(vB_dB_Query::SORT_DESC)));

		$this->set('reputationlevelid', intval($reputationlevel['reputationlevelid']));

		return true;
	}

	/**
	* Verifies that the provided username is valid, and attempts to correct it if it is not valid
	*
	* @param	string	Username
	*
	* @return	boolean	Returns true if the username is valid, or has been corrected to be valid
	*/
	function verify_username(&$username)
	{
		// fix extra whitespace and invisible ascii stuff
		$username = trim(preg_replace('#[ \r\n\t]+#si', ' ', strip_blank_ascii($username, ' ')));
		$username_raw = $username;

		$username = preg_replace(
			'/&#([0-9]+);/ie',
			"convert_unicode_char_to_charset('\\1', vB_String::getCharset())",
			$username
		);

		$username = preg_replace(
			'/&#0*([0-9]{1,2}|1[01][0-9]|12[0-7]);/ie',
			"convert_int_to_utf8('\\1')",
			$username
		);

		$username = str_replace(chr(0), '', $username);
		$username = trim($username);

		if (empty($this->existing['userid']))
		{
			$this->existing['userid'] = false;
		}

		if (empty($this->existing['username']))
		{
			if ($this->existing['userid'])
			{
				$userInfo = $this->assertor->getRow('user',
					array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'userid' => $this->existing['userid']));
				$this->existing['username'] = $userInfo['username'];
			}
			else
			{
				$this->existing['username'] = false;
			}
		}

		$length = vB_String::vbStrlen($username);
		if ($length == 0)
		{ // check for empty string
			$this->error('fieldmissing_username');
			return false;
		}
		else if ($length < $this->options['minuserlength'] AND !$this->adminoverride)
		{
			// name too short
			$this->error('usernametooshort', $this->options['minuserlength']);
			return false;
		}
		else if ($length > $this->options['maxuserlength'] AND !$this->adminoverride)
		{
			// name too long
			$this->error('usernametoolong', $this->options['maxuserlength']);
			return false;
		}
		else if (preg_match('/(?<!&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5});/', $username))
		{
			// name contains semicolons
			$this->error('username_contains_semi_colons');
			return false;
		}
		else if ($username != fetch_censored_text($username) AND !$this->adminoverride)
		{
			// name contains censored words
			$this->error('censorfield');
			return false;
		}
		/*else if (vB_String::htmlSpecialCharsUni($username_raw) != $this->existing['username'] AND $user = $this->dbobject->query_first("
			SELECT userid, username FROM " . TABLE_PREFIX . "user
			WHERE userid != " . intval($this->existing['userid']) . "
			AND
			(
				username = '" . $this->dbobject->escape_string(vB_String::htmlSpecialCharsUni($username)) . "'
				OR
				username = '" . $this->dbobject->escape_string(vB_String::htmlSpecialCharsUni($username_raw)) . "'
			)
		"))*/
		else if ((empty($this->existing['username']) OR (vB_String::htmlSpecialCharsUni($username_raw) != $this->existing['username'])) AND
						$user = $this->assertor->getRow('getUsernameAndId', array(
								vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
								'userid' => intval($this->existing['userid']),
								'username' => vB_String::htmlSpecialCharsUni($username),
								'username_raw' => vB_String::htmlSpecialCharsUni($username_raw)
			)))
		{
			// name is already in use
			if ($this->error_handler == vB_DataManager_Constants::ERRTYPE_CP)
			{
				$this->error('usernametaken_edit_here', vB_String::htmlSpecialCharsUni($username), $this->session->get('sessionurl'), $user['userid']);
			}
			else
			{
				$this->error('usernametaken', vB_String::htmlSpecialCharsUni($username), $this->session->get('sessionurl'));
			}
			return false;
		}

		if (!empty($this->options['usernameregex']) AND !$this->adminoverride)
		{
			// check for regex compliance
			if (!preg_match('#' . str_replace('#', '\#', $this->options['usernameregex']) . '#siU', $username))
			{
				$this->error('usernametaken', vB_String::htmlSpecialCharsUni($username), vB::getCurrentSession()->get('sessionurl'));
				return false;
			}
		}

		if (!empty($this->existing['username']) AND (vB_String::htmlSpecialCharsUni($username_raw) != $this->existing['username']
			AND !$this->adminoverride
			AND $this->options['usernamereusedelay'] > 0
		))
		{
			require_once(DIR . '/includes/class_userchangelog.php');
			$userchangelog = new vB_UserChangeLog($this->registry);
			$userchangelog->set_execute(true);
			$userchangelog->set_just_count(true);
			if ($userchangelog->sql_select_by_username(vB_String::htmlSpecialCharsUni($username), vB::getRequest()->getTimeNow() - ($this->options['usernamereusedelay'] * 86400)))
			{
				$this->error('usernametaken', vB_String::htmlSpecialCharsUni($username), vB::getCurrentSession()->get('sessionurl'));
				return false;
			}
		}

		if ( (empty($this->existing['username']) OR (vB_String::htmlSpecialCharsUni($username_raw) != $this->existing['username']))
			AND !empty($this->options['illegalusernames']) AND !$this->adminoverride)
		{
			// check for illegal username
			$usernames = preg_split('/[ \r\n\t]+/', $this->options['illegalusernames'], -1, PREG_SPLIT_NO_EMPTY);
			foreach ($usernames AS $val)
			{
				if (strpos(strtolower($username), strtolower($val)) !== false)
				{
					// wierd error to show, but hey...
					$this->error('usernametaken', vB_String::htmlSpecialCharsUni($username), vB::getCurrentSession()->get('sessionurl'));
					return false;
				}
			}
		}

		$unregisteredphrases = $this->assertor->getRows('phrase', array(
				'varname' => 'unregistered',
				'fieldname' => 'global'
		));

		//while ($unregisteredphrase = $this->registry->db->fetch_array($unregisteredphrases))
		foreach ($unregisteredphrases as $unregisteredphrase)
		{
			if (strtolower($unregisteredphrase['text']) == strtolower($username) OR strtolower($unregisteredphrase['text']) == strtolower($username_raw))
			{
				//$this->error('usernametaken', vB_String::htmlSpecialCharsUni($username), vB::getCurrentSession()->get('sessionurl'));
				$this->error('usernametaken', vB_String::htmlSpecialCharsUni($username), $this->session->get('sessionurl'));
				return false;
			}
		}

		// if we got here, everything is okay
		$username = vB_String::htmlSpecialCharsUni($username);

		// remove any trailing HTML entities that will be cut off when we stick them in the DB.
		// if we don't do this, the affected person won't be able to login, be banned, etc...
		$column_info = $this->assertor->getRow('getColumnUsername', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'field' => 'username'));
		if (preg_match('#char\((\d+)\)#i', $column_info['Type'], $match) AND $match[1] > 0)
		{
			$username = preg_replace('/&([a-z0-9#]*)$/i', '', substr($username, 0, $match[1]));
		}

		$this->rawusername = $username_raw;
		$username = trim($username);

		return true;
	}

	/**
	* Verifies that the provided birthday is valid
	*
	* @param	mixed	Birthday - can be yyyy-mm-dd, mm-dd-yyyy or an array containing day/month/year and converts it into a valid yyyy-mm-dd
	*
	* @return	boolean
	*/
	function verify_birthday(&$birthday)
	{
		if (!$this->adminoverride AND $this->options['reqbirthday'])
		{	// required birthday. If current birthday is acceptable, don't go any further (bypass form manipulation)
			$bday = explode('-', $this->existing['birthday']);
			if ($bday[2] > 1901 AND $bday[2] <= date('Y') AND @checkdate($bday[0], $bday[1], $bday[2]))
			{
				$this->set('birthday_search', $bday[2] . '-' . $bday[0] . '-' . $bday[1]);
				$birthday = "$bday[0]-$bday[1]-$bday[2]";
				return true;
			}
		}

		if (!is_array($birthday))
		{
			// check for yyyy-mm-dd string
			if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $birthday, $match))
			{
				$birthday = array('day' => $match[3], 'month' => $match[2], 'year' => $match[1]);
			}
			// check for mm-dd-yyyy string
			else if (preg_match('#^(\d{1,2})-(\d{1,2})-(\d{4})$#', $birthday, $match))
			{
				$birthday = array('day' => $match[2], 'month' => $match[1], 'year' => $match[3]);
			}
		}

		// check that all neccessary array keys are set
		if (!is_array($birthday) OR !isset($birthday['day']) OR !isset($birthday['month']) OR !isset($birthday['year']))
		{
			$this->error('birthdayfield');
			return false;
		}

		// force all array keys to integer
		$birthday = vB::get_cleaner()->cleanArray($birthday, array(
			'day'   => vB_Cleaner::TYPE_INT,
			'month' => vB_Cleaner::TYPE_INT,
			'year'	=> vB_Cleaner::TYPE_INT
		));

		if (
			($birthday['day'] <= 0 AND $birthday['month'] > 0) OR
			($birthday['day'] > 0 AND $birthday['month'] <= 0) OR
			(!$this->adminoverride AND $this->options['reqbirthday'] AND ($birthday['day'] <= 0 OR $birthday['month'] <= 0 OR $birthday['year'] <= 0))
		)
		{
			$this->error('birthdayfield');
			return false;
		}

		if ($birthday['day'] <= 0 AND $birthday['month'] <= 0)
		{
			$this->set('birthday_search', '');
			$birthday = '';

			return true;
		}
		else if (
			($birthday['year'] <= 0 OR (
				$birthday['year'] > 1901 AND $birthday['year'] <= date('Y')
			)) AND
			checkdate($birthday['month'], $birthday['day'], ($birthday['year'] == 0 ? 1996 : $birthday['year']))
		)
		{
			$birthday['day']   = str_pad($birthday['day'],   2, '0', STR_PAD_LEFT);
			$birthday['month'] = str_pad($birthday['month'], 2, '0', STR_PAD_LEFT);
			$birthday['year']  = str_pad($birthday['year'],  4, '0', STR_PAD_LEFT);

			$this->set('birthday_search', $birthday['year'] . '-' . $birthday['month'] . '-' . $birthday['day']);

			$birthday = "$birthday[month]-$birthday[day]-$birthday[year]";

			return true;
		}
		else
		{
			$this->error('birthdayfield');
			return false;
		}
	}

	/**
	* Verifies that everything is hunky dory with the user's email field
	*
	* @param	string	Email address
	*
	* @return	boolean
	*/
	function verify_useremail(&$email)
	{
		$email_changed = (!isset($this->existing['email']) OR $email != $this->existing['email']);

		// check for empty string
		if ($email == '')
		{
			if ($this->adminoverride OR !$email_changed)
			{
				return true;
			}

			$this->error('fieldmissing_email');
			return false;
		}

		// check valid email address
		if (!$this->verify_email($email))
		{
			$this->error('bademail');
			return false;
		}

		// check banned email addresses
		require_once(DIR . '/includes/functions_user.php');
		if (vB_Api::instanceInternal('user')->isBannedEmail($email) AND !$this->adminoverride)
		{
			if ($email_changed OR !$this->options['allowkeepbannedemail'])
			{
				// throw error if this is a new registration, or if updating users are not allowed to keep banned addresses
				$this->error('banemail');
				return false;
			}
		}

		// check unique address
		if ($this->options['requireuniqueemail'] AND $email_changed)
		{
			$params = array(array('field' => 'email', 'value' => $email, 'operator' => 'EQ'));

			if ($this->condition !== null)
			{
				$params[] = array('field' => 'userid', 'value' => intval($this->existing['userid']), 'operator' => 'NE');
			}
			$user = $this->assertor->getRow('user', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					vB_dB_Query::CONDITIONS_KEY =>$params
					));

			if ($user)
			{
				if ($this->error_handler == vB_DataManager_Constants::ERRTYPE_CP)
				{
					$this->error('emailtaken_search_here', $this->session->get('sessionurl'), $email);
				}
				else
				{
					$this->error('emailtaken', $this->session->get('sessionurl'));
				}
				return false;
			}
		}

		return true;
	}

	/**
	* Verifies that the provided parent email address is valid
	*
	* @param	string	Email address
	*
	* @return	boolean
	*/
	function verify_parentemail(&$parentemail)
	{
		if ($this->info['coppauser'] AND !$this->verify_email($parentemail))
		{
			$this->error('fieldmissing_parentemail');
			return false;
		}
		else
		{
			return true;
		}
}

	/**
	* Verifies that the usergroup provided is valid
	*
	* @param	integer	Usergroup ID
	*
	* @return	boolean
	*/
	function verify_usergroupid(&$usergroupid)
	{
		// if usergroupids is set because of email validation, don't allow it to be re-written
		if (isset($this->info['override_usergroupid']) AND $usergroupid != $this->user['usergroupid'])
		{
			$this->error("::Usergroup ID is already set to {$this->user[usergroupid]} and can not be changed due to email validation regulations::");
			return false;
		}

		if ($usergroupid < 1)
		{
			$usergroupid = 2;
		}

		return true;
	}

	/**
	* Verifies that the provided displaygroup ID is valid
	*
	* @param	integer	Display group ID
	*
	* @return	boolean
	*/
	function verify_displaygroupid(&$displaygroupid)
	{
		if ($displaygroupid == $this->fetch_field('usergroupid') OR in_array($displaygroupid, explode(',', $this->fetch_field('membergroupids'))))
		{
			return true;
		}
		else
		{
			$displaygroupid = 0;
			return true;
		}
	}

	/**
	* Verifies a specified referrer
	*
	* @param	mixed	Referrer - either a user ID or a user name
	*
	* @return	boolean
	*/
	function verify_referrerid(&$referrerid)
	{
		if ($referrerid == '')
		{
			$referrerid = 0;
			return true;
		}
		else if ($user = $this->assertor->getRow('user', array('username' => $referrerid)))
		{
			$referrerid = $user['userid'];
		}
		else if (is_numeric($referrerid) AND $user = $this->assertor->getRow('user', array('userid' => intval($referrerid))))
		{
			$referrerid = $user['userid'];
		}
		else
		{
			$this->error('invalid_referrer_specified');
			return false;
		}

		if ($referrerid > 0 AND $referrerid == $this->existing['userid'])
		{
			$this->error('invalid_referrer_specified');
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Verifies an MSN handle
	*
	* @param	string	MSN handle (email address)
	*
	* @return	boolean
	*/
	function verify_msn(&$msn)
	{
		if ($msn == '' OR $this->verify_email($msn))
		{
			$msn = vB_String::htmlSpecialCharsUni($msn);
			return true;
		}
		else
		{
			$this->error('badmsn');
			return false;
		}
	}

	/**
	* Verifies a Skype name
	*
	* @param	string	Skype name
	*
	* @return	boolean
	*/
	function verify_skype(&$skype)
	{
		if ($skype == '' OR preg_match('#^[a-z0-9_.,-]{6,32}$#si', $skype))
		{
			return true;
		}
		else
		{
			$this->error('badskype');
			return false;
		}
	}

	// #############################################################################
	// password related

	/**
	* Converts a PLAIN TEXT (or valid md5 hash) password into a hashed password
	*
	* @param	string	The plain text password to be converted
	*
	* @return	boolean
	*/
	function verify_password(&$password)
	{
		//regenerate the salt when the password is changed.  No reason not to and its
		//an easy way to increase the size when the user changes their password (doing
		//it this way avoids having to reset all of the passwords)
		$this->user['salt'] = $salt = $this->fetch_user_salt();

		// generate the password
		$password = $this->hash_password($password, $salt);

		if (!defined('ALLOW_SAME_USERNAME_PASSWORD'))
		{
			// check if password is same as username; if so, set an error and return false
			if ($password == md5(md5($this->rawusername) . $salt))
			{
				$this->error('sameusernamepass');
				return false;
			}
		}

		$this->set('passworddate', 'FROM_UNIXTIME(' . vB::getRequest()->getTimeNow() . ')', false);

		return true;
	}

	/**
	* Verifies that the user salt is valid
	*
	* @param	string	The salt string
	*
	* @return	boolean
	*/
	function verify_salt(&$salt)
	{
		$this->error('::You may not set salt manually.::');
		return false;
	}

	/**
	 * Verifies that privacy options are valid
	 */
	function verify_privacy_options($pOptions)
	{
		// not empty
		if (!empty($pOptions))
		{
			$pOptions = unserialize($pOptions);
			if (is_array($pOptions))
			{
				return true;
			}
			else
			{
				$this->error('badprivacyoptions');
				return false;
			}
		}

		return true;
	}

	/**
	* Takes a plain text or singly-md5'd password and returns the hashed version for storage in the database
	*
	* @param	string	Plain text or singly-md5'd password
	*
	* @return	string	Hashed password
	*/
	function hash_password($password, $salt)
	{
		// if the password is not already an md5, md5 it now
		if ($password == '')
		{
		}
		else if (!$this->verify_md5($password))
		{
			$password = md5($password);
		}

		// hash the md5'd password with the salt
		return md5($password . $salt);
	}

	/**
	* Generates a new user salt string
	*
	* @param	integer	(Optional) the length of the salt string to generate
	*
	* @return	string
	*/
	function fetch_user_salt($length = 30)
	{
		$salt = '';

		for ($i = 0; $i < $length; $i++)
		{
			$salt .= chr(rand(33, 126));
		}

		return $salt;
	}

	/**
	* Checks to see if a password is in the user's password history
	*
	* @param	integer	User ID
	* @param	integer	History time ($permissions['passwordhistory'])
	*
	* @return	boolean	Returns true if password is in the history
	*/
	function check_password_history($password, $historylength)
	{
		// delete old password history
		$this->assertor->delete('delPasswordHistory', array(
				'userid' => $this->existing['userid'],
				'passworddate' => (vB::getRequest()->getTimeNow() - $historylength * 86400)
		));

		// check to see if the password is invalid due to previous use
		if ($historylength AND $historycheck = $this->assertor->getRow('getHistoryCheck', array(
				'userid' => $this->existing['userid'],
				'password' => $password
		)))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	// #############################################################################
	// user title

	/**
	* Sets the values for user[usertitle] and user[customtitle]
	*
	* @param	string	Custom user title text
	* @param	boolean	Whether or not to reset a custom title to the default user title
	* @param	array	Array containing all information for the user's primary usergroup
	* @param	boolean	Whether or not a user can use custom user titles ($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusecustomtitle'])
	* @param	boolean	Whether or not the user is an administrator ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
	*/
	function set_usertitle($customtext, $reset, $usergroup, $canusecustomtitle, $isadmin)
	{
		$customtitle = $this->existing['customtitle'];
		$usertitle = $this->existing['usertitle'];

		if ($this->existing['customtitle'] == 2 AND isset($this->existing['musername']))
		{
			// fetch_musername has changed this value -- need to undo it
			$usertitle = unhtmlspecialchars($usertitle);
		}

		if ($canusecustomtitle)
		{
			// user is allowed to set a custom title
			if ($reset OR ($customtitle == 0 AND $customtext === ''))
			{
				// reset custom title or we don't have one but are allowed to
				if (empty($usergroup['usertitle']))
				{
					$gettitle = $this->assertor->getRow('usertitle', array(
							vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
							vB_dB_Query::CONDITIONS_KEY => array(
									array('field' => 'minposts', 'value' => intval($this->existing['posts']), 'operator' => 'LTE')
							)
					), array('field' => array('minposts'), 'direction' => array(vB_dB_Query::SORT_DESC)));
					$usertitle = $gettitle['title'];
				}
				else
				{
					$usertitle = $usergroup['usertitle'];
				}
				$customtitle = 0;
			}
			else if ($customtext)
			{
				// set custom text
				$usertitle = fetch_censored_text($customtext);
				$canModerate = false;
				if ($this->options['ctCensorMod'])
				{
					$canModerate = vB::getUserContext()->isForumModerator();
				}

				if (!$canModerate OR !$this->options['ctCensorMod'])
				{
					$usertitle = $this->censor_custom_title($usertitle);
				}

				$customtitle = $isadmin ?
					1: // administrator - don't run htmlspecialchars
					2; // regular user - run htmlspecialchars
				if ($customtitle == 2)
				{
					$usertitle = fetch_word_wrapped_string($usertitle, 25);
				}
			}
		}
		else if ($customtitle != 1)
		{
			if (empty($usergroup['usertitle']))
			{
				$gettitle = $this->assertor->getRow('usertitle', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						vB_dB_Query::CONDITIONS_KEY => array(
								array('field' => 'minposts', 'value' => intval($this->existing['posts']), 'operator' => 'LTE')
						)
				), array('field' => array('minposts'), 'direction' => array(vB_dB_Query::SORT_DESC)));
				$usertitle = $gettitle['title'];
			}
			else
			{
				$usertitle = $usergroup['usertitle'];
			}
			$customtitle = 0;
		}

		$this->set('usertitle', $usertitle);
		$this->set('customtitle', $customtitle);
	}

	/**
	* Sets the ladder-based or group based user title for a particular amount of posts.
	*
	* @param	integer			Number of posts to consider this user as having
	*
	* @return	false|string	False if they use a custom title or can't process, the new title otherwise
	*/
	function set_ladder_usertitle($posts)
	{
		if ($this->fetch_field('userid')
			AND (!isset($this->user['customtitle']) OR !isset($this->existing['customtitle']))
		)
		{
			// we don't have enough information, try to fetch it
			$user = vB_Api::instanceInternal('user')->fetchUserinfo($this->fetch_field('userid'));
			if ($user)
			{
				$this->set_existing($user);
			}
			else
			{
				return false;
			}
		}

		if ($this->fetch_field('customtitle'))
		{
			return false;
		}

		$getusergroupid = ($this->fetch_field('displaygroupid') ? $this->fetch_field('displaygroupid') : $this->fetch_field('usergroupid'));
		$usergroup = $this->registry->usergroupcache["$getusergroupid"];

		if (!$usergroup['usertitle'])
		{
			$gettitle = $this->assertor->getRow('usertitle', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					vB_dB_Query::CONDITIONS_KEY => array(
							array('field' => 'minposts', 'value' => intval($posts), 'operator' => 'LTE')
					)
			), array('field' => array('minposts'), 'direction' => array(vB_dB_Query::SORT_DESC)));

			$usertitle = $gettitle['title'];
		}
		else
		{
			$usertitle = $usergroup['usertitle'];
		}

		$this->set('usertitle', $usertitle);

		return $usertitle;
	}

	/**
	* Sets the ladder usertitle relative to the current number of posts.
	*
	* @param	integer			Offset to current number of posts
	*
	* @return	false|string	Same return values as set_ladder_usertitle
	*/
	function set_ladder_usertitle_relative($relative_post_offset)
	{
		if ($this->fetch_field('userid') AND !isset($this->existing['posts']))
		{
			// we don't have enough information, try to fetch it
			if (isset($GLOBALS['usercache'][$this->fetch_field('userid')]))
			{
				unset($GLOBALS['usercache'][$this->fetch_field('userid')]);
			}

			$user = $this->assertor->getRow('user', array('userid' => $this->fetch_field('userid')));
			if ($user)
			{
				$this->set_existing($user);
			}
			else
			{
				return false;
			}
		}

		return $this->set_ladder_usertitle($this->existing['posts'] + $relative_post_offset);
	}

	/**
	* Checks a string for words banned in custom user titles and replaces them with the censor character
	*
	* @param	string	Custom user title
	*
	* @return	string	The censored string
	*/
	function censor_custom_title($usertitle)
	{
		static $ctcensorwords;

		if (empty($ctcensorwords))
		{
			$ctcensorwords = preg_split('#[ \r\n\t]+#', preg_quote($this->options['ctCensorWords'], '#'), -1, PREG_SPLIT_NO_EMPTY);
		}

		foreach ($ctcensorwords AS $censorword)
		{
			if (substr($censorword, 0, 2) == '\\{')
			{
				$censorword = substr($censorword, 2, -2);
				$usertitle = preg_replace('#(?<=[^A-Za-z]|^)' . $censorword . '(?=[^A-Za-z]|$)#si', str_repeat($this->options['censorchar'], vB_String::vbStrlen($censorword)), $usertitle);
			}
			else
			{
				$usertitle = preg_replace("#$censorword#si", str_repeat($this->options['censorchar'], vB_String::vbStrlen($censorword)), $usertitle);
			}
		}

		return $usertitle;
	}

	// #############################################################################
	// user profile fields

	/**
	* Validates and sets custom user profile fields
	*
	* @param	array	Array of values for profile fields. Example: array('field1' => 'One', 'field2' => array(0 => 'a', 1 => 'b'), 'field2_opt' => 'c')
	* @param	bool	Whether or not to verify the data actually matches any specified regexes or required fields
	* @param	string	What type of editable value to apply (admin, register, normal)
	* @param	bool	Whether or not to skip verification of required fields that are not present, used for linking facebook accounts
	*
	* @return	string	Textual description of set profile fields (for email phrase)
	*/
	function set_userfields(&$values, $verify = true, $all_fields = 'normal', $skip_unset_required_fields = false)
	{
		global $vbphrase;

		if (!is_array($values))
		{
			$this->error('::$values for profile fields is not an array::');
			return false;
		}

		$customfields = '';

		$field_ids = array();
		foreach (array_keys($values) AS $key)
		{
			if (preg_match('#^field(\d+)\w*$#', $key, $match))
			{
				$field_ids["$match[1]"] = $match[1];
			}
		}
		if (empty($field_ids) AND $all_fields != 'register')
		{
			return false;
		}

		switch($all_fields)
		{
			case 'admin':
				$all_fields_sql = array('profilefieldid' => $field_ids);
				break;

			case 'register':
				// must read all fields in order to set defaults for fields that don't display
				//$all_fields_sql = "WHERE editable IN (1,2)";
				$all_fields_sql = array();

				// we need to ensure that each field the user could edit is sent through and processed,
				// so ensure that we process everyone one of these fields
				$profilefields = $this->assertor->getRows('vBForum:profilefield', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						vB_dB_Query::CONDITIONS_KEY => array(
								array('field' => 'editable', 'value' => 0, 'operator' => 'GT'),
								array('field' => 'required', 'value' => 0, 'operator' => 'NE')
						)
				));
				foreach ($profilefields as $profilefield)
				{
					$field_ids["$profilefield[profilefieldid]"] = $profilefield['profilefieldid'];
				}
				break;

			case 'normal':
			default:
				$all_fields_sql = array('profilefieldid' => $field_ids, 'editable' => array(1,2));
				break;
		}

		// check extra profile fields
		$profilefields = $this->assertor->getRows('vBForum:profilefield', $all_fields_sql, 'displayorder');
		foreach ($profilefields as $profilefield)
		{
			$varname = 'field' . $profilefield['profilefieldid'];
			$value =& $values["$varname"];
			$regex_check = false;

			if ($all_fields != 'admin' AND $profilefield['editable'] == 2 AND !empty($this->existing["$varname"]))
			{
				continue;
			}

			$title = vB_Api::instanceInternal('phrase')->fetch($varname . '_title');
			$profilefield['title'] = (!empty($title) ? $title[$varname . '_title'] : $varname);
			unset($title);

			$optionalvar = 'field' . $profilefield['profilefieldid'] . '_opt';
			$value_opt =& $values["$optionalvar"];

			// text box / text area
			if ($profilefield['type'] == 'input' OR $profilefield['type'] == 'textarea')
			{
				if (in_array($profilefield['profilefieldid'], $field_ids) AND ($all_fields != 'register' OR $profilefield['editable']))
				{
					$value = trim(substr(fetch_censored_text($value), 0, $profilefield['maxlength']));
					$value = (empty($value) AND $value != '0') ? false : $value;
				}
				else if ($all_fields == 'register' AND $profilefield['data'] !== '')
				{
					$value = unhtmlspecialchars($profilefield['data']);
				}
				else
				{
					continue;
				}
				$customfields .= "$profilefield[title] : $value\n";
				$regex_check = true;
			}
			// radio / select
			else if ($profilefield['type'] == 'radio' OR $profilefield['type'] == 'select')
			{
				if ($profilefield['optional'] AND $value_opt != '')
				{
					$value = trim(substr(fetch_censored_text($value_opt), 0, $profilefield['maxlength']));
					$value = (empty($value) AND $value != '0') ? false : $value;
					$regex_check = true;
				}
				else
				{
					$data = unserialize($profilefield['data']);
					$value -= 1;
					if (in_array($profilefield['profilefieldid'], $field_ids) AND ($all_fields != 'register' OR $profilefield['editable']))
					{
						if (isset($data["$value"]))
						{
							$value = unhtmlspecialchars(trim($data["$value"]));
						}
						else
						{
							$value = false;
						}
					}
					else if ($all_fields == 'register' AND $profilefield['def'])
					{
						$value = unhtmlspecialchars($data[0]);
					}
					else
					{
						continue;
					}
				}
				$customfields .= "$profilefield[title] : $value\n";
			}
			// checkboxes or select multiple
			else if (($profilefield['type'] == 'checkbox' OR $profilefield['type'] == 'select_multiple') AND in_array($profilefield['profilefieldid'], $field_ids))
			{
				if (is_array($value))
				{
					if (($profilefield['size'] == 0) OR (sizeof($value) <= $profilefield['size']))
					{
						$data = unserialize($profilefield['data']);

						$bitfield = 0;
						$cfield = '';
						foreach($value AS $key => $val)
						{
							$val--;
							$bitfield += pow(2, $val);
							$cfield .= (!empty($cfield) ? ', ' : '') . $data["$val"];
						}
						$value = $bitfield;
					}
					else
					{
						$this->error('checkboxsize', $profilefield['size'], $profilefield['title']);
						$value = false;
					}
					$customfields .= "$profilefield[title] : $cfield\n";
				}
				else
				{
					$value = false;
				}
			}
			else
			{
				continue;
			}

			// check for regex compliance
			if ($verify AND $profilefield['regex'] AND $regex_check)
			{
				if (!preg_match('#' . str_replace('#', '\#', $profilefield['regex']) . '#siU', $value))
				{
					$this->error('regexincorrect', $profilefield['title']);
					$value = false;
				}
			}

			// check for empty required fields
			if (($profilefield['required'] == 1 OR $profilefield['required'] == 3) AND $value === false AND $verify)
			{
				if ($skip_unset_required_fields AND !isset($values["$varname"]))
				{
					continue;
				}
				$this->error('required_field_x_missing_or_invalid', $profilefield['title']);
			}

			$this->setfields["$varname"] = true;
			$this->userfield["$varname"] = vB_String::htmlSpecialCharsUni($value);
		}

		//$this->dbobject->free_result($profilefields);
		return $customfields;
	}

	// #############################################################################
	// daylight savings

	/**
	* Sets DST options
	*
	* @param	integer	DST choice: (2: automatic; 1: auto-off, dst on; 0: auto-off, dst off)
	*/
	function set_dst(&$dst)
	{
		switch ($dst)
		{
			case 2:
				$dstauto = 1;
				$dstonoff = $this->existing['dstonoff'];
				break;
			case 1:
				$dstauto = 0;
				$dstonoff = 1;
				break;
			default:
				$dstauto = 0;
				$dstonoff = 0;
				break;
		}

		$this->set_bitfield('options', 'dstauto', $dstauto);
		$this->set_bitfield('options', 'dstonoff', $dstonoff);
	}

	// #############################################################################
	// fill in missing fields from registration default options

	/**
	* Sets registration defaults
	*/
	function set_registration_defaults()
	{
		$bf_misc_regoptions = $this->datastore->get_value('bf_misc_regoptions');

		// on/off fields
		foreach (array(
			'invisible'         => 'invisiblemode',
			'receivepm'         => 'enablepm',
			'emailonpm'         => 'emailonpm',
			'showreputation'    => 'showreputation',
			'showvcard'         => 'vcard',
			'showsignatures'    => 'signature',
			'showavatars'       => 'avatar',
			'showimages'        => 'image',
			'vm_enable'         => 'vm_enable',
			'vm_contactonly'    => 'vm_contactonly',
			'pmdefaultsavecopy' => 'pmdefaultsavecopy',
			'moderatefollowers'	=> 'moderatefollowers',
			'adminemail'		=> 'adminemail'
		) AS $optionname => $bitfield)
		{
			if (!isset($this->user['options']["$optionname"]))
			{
				$this->set_bitfield('options', $optionname,
					($bf_misc_regoptions["$bitfield"] &
						$this->options['defaultregoptions'] ? 1 : 0));
			}
		}

		//force the default to true (if it not set).  If we decide to make it an
		//option later, push it into the above loop above.
		if (!isset($this->user['options']['vbasset_enable']))
		{
			$this->set_bitfield('options', 'vbasset_enable', 1);
		}

		// time fields
		foreach (array('joindate', 'lastvisit', 'lastactivity') AS $datefield)
		{
			if (!isset($this->user["$datefield"]))
			{
				$this->set($datefield, vB::getRequest()->getTimeNow());
			}
		}

		// auto subscription
		if (!isset($this->user['autosubscribe']))
		{
			if ($bf_misc_regoptions['subscribe_none'] & $this->options['defaultregoptions'])
			{
				$autosubscribe = 0;
			}
			else if ($bf_misc_regoptions['subscribe_nonotify'] & $this->options['defaultregoptions'])
			{
				$autosubscribe = 0;
			}
			else if ($bf_misc_regoptions['subscribe_instant'] & $this->options['defaultregoptions'])
			{
				$autosubscribe = 1;
			}
			else if ($bf_misc_regoptions['subscribe_daily'] & $this->options['defaultregoptions'])
			{
				$autosubscribe = 2;
			}
			else
			{
				$autosubscribe = 3;
			}
			$this->set('autosubscribe', $autosubscribe);
		}

		// show vbcode
		if (!isset($this->user['showvbcode']))
		{
			if ($bf_misc_regoptions['vbcode_none'] & $this->options['defaultregoptions'])
			{
				$showvbcode = 0;
			}
			else if ($bf_misc_regoptions['vbcode_standard'] & $this->options['defaultregoptions'])
			{
				$showvbcode = 1;
			}
			else
			{
				$showvbcode = 2;
			}
			$this->set('showvbcode', $showvbcode);
		}

		// post order / thread display mode
		if (!isset($this->user['threadedmode']))
		{
			if ($bf_misc_regoptions['thread_linear_oldest'] & $this->options['defaultregoptions'])
			{
				$threadedmode = 0;
			}
			else if ($bf_misc_regoptions['thread_linear_newest'] & $this->options['defaultregoptions'])
			{
				$threadedmode = 3;
			}
			else if ($bf_misc_regoptions['thread_threaded'] & $this->options['defaultregoptions'])
			{
				$threadedmode = 1;
			}
			else if ($bf_misc_regoptions['thread_hybrid'] & $this->options['defaultregoptions'])
			{
				$threadedmode = 2;
			}
			else
			{
				$threadedmode = 0;
			}
			$this->set('threadedmode', $threadedmode);
		}

		// usergroupid
		if (!isset($this->user['usergroupid']))
		{
			if ($this->options['verifyemail'])
			{
				$usergroupid = 3;
			}
			else if ($this->options['moderatenewmembers'] OR !empty($this->info['coppauser']))
			{
				$usergroupid = 4;
			}
			else
			{
				$usergroupid = 2;
			}
			$this->set('usergroupid', $usergroupid);
		}

		// reputation
		if (!isset($this->user['reputation']))
		{
			$this->set('reputation', $this->options['reputationdefault']);
		}

		// pm popup
		if (!isset($this->user['pmpopup']))
		{
			$this->set('pmpopup', ($bf_misc_regoptions['pmpopup'] & $this->options['defaultregoptions'] ? 1 : 0));
		}

		// max posts per page
		if (!isset($this->user['maxposts']))
		{
			$this->set('maxposts', 1);
		}

		// days prune
		if (!isset($this->user['daysprune']))
		{
			$this->set('daysprune', 0);
		}

		// start of week
		if (!isset($this->user['startofweek']))
		{
			$this->set('startofweek', -1);
		}

		// show user css
		if (!isset($this->user['options']['showusercss']))
		{
			$this->set_bitfield('options', 'showusercss', 1);
		}

		// receive friend request pm
		if (!isset($this->user['options']['receivefriendemailrequest']))
		{
			$this->set_bitfield('options', 'receivefriendemailrequest', 1);
		}

		// set usertitle
		if (!isset($this->user['usertitle']))
		{
			$usertitle = vB_Api::instanceInternal('user')->getUsertitleFromPosts(0);
			$this->set('usertitle', $usertitle);
			$this->set('customtitle', 0);
		}
	}

	// #############################################################################
	// data saving

	/**
	* Takes valid data and sets it as part of the data to be saved
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	*/
	function do_set($fieldname, &$value, $table = null)
	{
		$this->setfields["$fieldname"] = true;

		$tables = array();

		switch ($fieldname)
		{
			case 'userid':
			{
				$tables = array('user', 'userfield', 'usertextfield');
			}
			break;

			case 'subfolders':
			case 'pmfolders':
			case 'searchprefs':
			case 'buddylist':
			case 'ignorelist':
			case 'signature':
			case 'rank':
			{
				$tables = array('usertextfield');
			}
			break;

			default:
			{
				$tables = array('user');
			}
		}

		// Legacy Hook 'userdata_doset' Removed //

		foreach ($tables AS $table)
		{
			$this->{$table}["$fieldname"] =& $value;
			$this->lasttable = $table;
		}
	}

	/**
	* Saves the data from the object into the specified database tables
	*
	* @param	boolean	Do the query?
	* @param	mixed	Whether to run the query now; see db_update() for more info
	*
	* @return	integer	Returns the user id of the affected data
	*/
	function save($doquery = true, $delayed = false, $affected_rows = false, $replace = false, $ignore = false)
	{
		if ($this->has_errors(false))
		{
			return false;
		}

		if (!$this->pre_save($doquery))
		{
			return 0;
		}
		// UPDATE EXISTING USER
		if ($this->condition)
		{
			// update query
			$return = $this->db_update(TABLE_PREFIX, 'user', $this->condition, $doquery, $delayed);
			if ($return)
			{
				$this->db_update(TABLE_PREFIX, 'vBForum:userfield',     $this->condition, $doquery, $delayed);
				$this->db_update(TABLE_PREFIX, 'vBForum:usertextfield', $this->condition, $doquery, $delayed);

				// check if we want userchange log and we have the all requirements
				if (
					$this->user_changelog_state AND is_array($this->user_changelog_fields) AND sizeof($this->user_changelog_fields) AND
					is_array($this->existing) AND sizeof($this->existing) AND is_array($this->user) AND sizeof($this->user)
				)
				{
					$uniqueid = md5(vB::getRequest()->getTimeNow() . $this->existing['userid'] . $this->userinfo['userid']. rand(1111,9999));

					// fill the storage array
					foreach($this->user_changelog_fields AS $fieldname)
					{
						// if no old and new value, or no change: we dont log this field
						if (
							!isset($this->user["$fieldname"])
							OR
							(!$this->existing["$fieldname"] AND !$this->user["$fieldname"])
							OR
							$this->existing["$fieldname"] == $this->user["$fieldname"]
						)
						{
							continue;
						}

						// init storage array
						$this->userchangelog = array(
							'userid'      => $this->existing['userid'],
							'adminid'     => $this->userinfo['userid'],
							'fieldname'   => $fieldname,
							'oldvalue'    => $this->existing["$fieldname"],
							'newvalue'    => $this->user["$fieldname"],
							'change_time' => vB::getRequest()->getTimeNow(),
							'change_uniq' => $uniqueid,
							'ipaddress'   => sprintf('%u', ip2long(IPADDRESS)),
						);

						// do the query ?
						if ($doquery)
						{
							$this->assertor->insert('userchangelog', $this->userchangelog);
						}
					}
				}
			}

			vB_Cache::instance(vB_Cache::CACHE_FAST)->event(array('userPerms_' . $this->existing['userid'], 'userChg_' . $this->existing['userid']));
			vB_Cache::instance(vB_Cache::CACHE_LARGE)->event(array('userPerms_' . $this->existing['userid'], 'userChg_' . $this->existing['userid']));

		}
		// INSERT NEW USER
		else
		{
			// fill in any registration defaults
			$this->set_registration_defaults();

			// insert query
			if ($return = $this->db_insert(TABLE_PREFIX, 'user', $doquery))
			{
				$this->set('userid', $return);
				// need to send tablename with package
				$this->db_insert(TABLE_PREFIX, 'vBForum:userfield',     $doquery, true);
				$this->db_insert(TABLE_PREFIX, 'vBForum:usertextfield', $doquery);

				// Send welcome PM
				if ($this->fetch_field('usergroupid') == 2)
				{

					$this->send_welcomepm(null, $return);
				}
			}

		}

		if ($return)
		{
			$this->post_save_each($doquery);
			$this->post_save_once($doquery);
		}

		return $return;
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		// USERGROUP CHECKS
		$usergroups_changed = $this->usergroups_changed();

		if ($usergroups_changed)
		{
			// VALIDATE USERGROUPID / MEMBERGROUPIDS
			$usergroupid = $this->fetch_field('usergroupid');
			$membergroupids = $this->fetch_field('membergroupids');

			//validation of the usergroupid can fix it if its wrong.  This is done during save
			//however that's too late to perform this particular check.  Therefore we validate
			//(and set the value to ensure that the possibly new value is saved) to so make sure
			//that the check loos for the right thing.  It means we do validation twice on the
			//usergroupid field, but that's not a serious problem.
			$this->verify_usergroupid($usergroupid);
			$this->do_set('usergroupid', $usergroupid);

			if (strpos(",$membergroupids,", ",$usergroupid,") !== false)
			{
				// usergroupid/membergroups conflict
				$this->error('usergroup_equals_secondary');
				return false;
			}

			// if changing usergroups, validate the displaygroup
			$displaygroupid = $this->fetch_field('displaygroupid');
			$this->verify_displaygroupid($displaygroupid); // this will edit the value if necessary
			$this->do_set('displaygroupid', $displaygroupid);
		}

		if ($this->condition)
		{
			$wasadmin = $this->is_admin($this->existing['usergroupid'], $this->existing['membergroupids']);
			$isadmin = $this->is_admin($this->fetch_field('usergroupid'), $this->fetch_field('membergroupids'));

			// if usergroups changed, check we are not de-admining the last admin
			if ($usergroups_changed AND $wasadmin AND !$isadmin AND $this->count_other_admins($this->existing['userid']) == 0)
			{
				$this->error('cant_de_admin_last_admin');
				return false;
			}

			$updateinfractions = false;
			// primary usergroup change, update infractions
			if (isset($this->user['usergroupid']) AND (($usergroupid = $this->user['usergroupid']) != $this->existing['usergroupid']) AND $this->existing['ipoints'] > 0)
			{
				$ipoints = $this->existing['ipoints'];
				$updateinfractions = true;
			}
			else if (isset($this->user['ipoints']) AND is_int($this->user['ipoints']) AND $this->user['ipoints'] != $this->existing['ipoints'])
			{
				$updateinfractions = true;
				$ipoints = $this->user['ipoints'];
			}

			if ($updateinfractions)
			{
 				// If user groups aren't changed, then $usergroupid is not set....
 				if (empty($usergroupid))
 				{
 					$usergroupid = $this->fetch_field('usergroupid');
 				}

				$infractiongroups = array();
				$infractiongroupid = 0;
				$groups = $this->assertor->getRows('getInfractiongroups', array(
						'usergroupid' => $usergroupid,
						'pointlevel' => $ipoints
				), 'pointlevel');
				foreach ($groups as $group)
				{
					if ($group['override'])
					{
						$infractiongroupid = $group['orusergroupid'];
					}
					$infractiongroups["$group[orusergroupid]"] = true;
				}

				$this->set('infractiongroupids', !empty($infractiongroups) ? implode(',', array_keys($infractiongroups)) : '');
				$this->set('infractiongroupid', $infractiongroupid);
			}
		}

		// Attempt to detect if we need a new rank or usertitle
		if (isset($this->rawfields['posts']) AND $this->rawfields['posts'])
		{	// posts = posts + 1 / posts - 1 was specified so we need existing posts to determine how many posts we will have
			if ($this->existing['posts'] != null)
			{
				$posts = $this->existing['posts'] + preg_replace('#^.*posts\s*([+-])\s*(\d+?).*$#sU', '\1\2', $this->fetch_field('posts'));
			}
		}
		else if ($this->fetch_field('posts') !== null)
		{
			$posts = $this->fetch_field('posts');
		}

		if (
				(
					(isset($this->setfields['membergroupids']) AND $this->setfields['membergroupids']) OR
					(isset($this->setfields['posts']) AND $this->setfields['posts'])  OR
					(isset($this->setfields['usergroupid']) AND $this->setfields['usergroupid']) OR
					(isset($this->setfields['displaygroupid']) AND $this->setfields['displaygroupid'])
				) AND empty($this->setfields['rank']) AND isset($posts) AND $userid = $this->fetch_field('userid'))
		{	// item affecting user's rank is changing and a new rank hasn't been given to us
			$userinfo = array(
				'userid' => $userid, // we need an userid for is_member_of's cache routine
				'posts' => $posts
			);
			if (($userinfo['usergroupid'] =& $this->fetch_field('usergroupid')) !== null AND
				($userinfo['displaygroupid'] =& $this->fetch_field('displaygroupid')) !== null AND
				($userinfo['membergroupids'] =& $this->fetch_field('membergroupids')) !== null
			)
			{
				require_once(DIR . '/includes/functions_ranks.php');
				$userrank =& fetch_rank($userinfo);
				if (isset($this->existing['rank']) OR ($userrank != $this->existing['rank']))
				{
					$this->setr('rank', $userrank);
				}
			}
		}

		$return_value = true;
		// Legacy Hook 'userdata_presave' Removed //

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		$userid = $this->fetch_field('userid');

		if (!$userid OR !$doquery)
		{
			return;
		}

		$usergroups_changed = $this->usergroups_changed();

		if (!empty($this->existing['usergroupid']))
		{
		$wasadmin = $this->is_admin($this->existing['usergroupid'], $this->existing['membergroupids']);
		}
		else
		{
			$wasadmin = false;
		}
		$isadmin = $this->is_admin($this->fetch_field('usergroupid'), $this->fetch_field('membergroupids'));


		if (!empty($this->existing['usergroupid']))
		{
		$wassupermod = $this->is_supermod($this->existing['usergroupid'], $this->existing['membergroupids']);
		}
		else
		{
			$wassupermod = false;
		}
		$issupermod = $this->is_supermod($this->fetch_field('usergroupid'), $this->fetch_field('membergroupids'));

		if (!$this->condition)
		{
			// save user count and new user id to template
			require_once(DIR . '/includes/functions_databuild.php');
			build_user_statistics();
		}
		else
		{
			// update denormalized username field in various tables
			$this->update_username($userid);

			// if usergroup membership has changed...
			if ($usergroups_changed)
			{
				// update subscriptions
				$this->update_subscriptions($userid, $doquery);

				// update ban status
				$this->update_ban_status($userid, $doquery);

				// recache permissions if the userid is the current browsing user
				if ($userid == $this->userinfo['userid'])
				{
					$this->userinfo['usergroupid'] = $this->fetch_field('usergroupid');
					$this->userinfo['membergroupids'] = $this->fetch_field('membergroupids');
					cache_permissions($this->userinfo);
				}
			}

			// if the primary user group has been changed, we need to update any user activation records
			if (!empty($this->user['usergroupid']) AND $this->user['usergroupid'] != $this->existing['usergroupid'])
			{
				$this->assertor->update('useractivation', array('usergroupid' => $this->user['usergroupid']), array('userid' => $userid, 'type' => 0));
			}

			if (isset($this->usertextfield['signature']))
			{
				// edited the signature, need to kill any parsed versions
				$this->assertor->delete('vBForum:sigparsed', array('userid' => $userid));
			}
		}

		// admin stuff
		$this->set_admin($userid, $usergroups_changed, $isadmin, $wasadmin);

		// super moderator stuff
		$this->set_supermod($userid, $usergroups_changed, $issupermod, $wassupermod);

		// update birthday datastore
		$this->update_birthday_datastore($userid);

		// update password history
		$this->update_password_history($userid);

		// reset style cookie
		$this->update_style_cookie($userid);

		// reset threadedmode cookie
		$this->update_threadedmode_cookie($userid);

		// reset languageid cookie
		$this->update_language_cookie($userid);

		// Send parent email
		if (isset($this->info['coppauser']) AND $this->info['coppauser'] AND $username = $this->fetch_field('username') AND $parentemail = $this->fetch_field('parentemail'))
		{
			//this uses in-scope variables in the phrases instead of composing the phrase normally.  It has to do with
			//the behavior of fetch_email_phrases which doesn't allow variables to be passed directly to the phrase.
			//$memberlink and $forumhomelink are referenced in the phrase and get interpolated when the phrase text is
			//eval'd.
			$vboptions = vB::getDatastore()->getValue('options');

			$memberlink = vB5_Route::buildUrl('profile|nosession|fullurl', array('userid' => $userid, 'username' => $username));
			$forumhomelink = vB5_Route::buildUrl('home|nosession|fullurl');

			if ($password = $this->info['coppapassword'])
			{
				$maildata = vB_Api::instanceInternal('phrase')
					->fetchEmailPhrases('parentcoppa_register', array($username, $vboptions['bbtitle'], $forumhomelink, $memberlink, $vboptions['privacyurl'], $password), array($username, $vboptions['bbtitle']));
			}
			else
			{
				$maildata = vB_Api::instanceInternal('phrase')
					->fetchEmailPhrases('parentcoppa_profile', array($username, $vboptions['bbtitle'], $forumhomelink, $vboptions['privacyurl'], $memberlink), array($username, $vboptions['bbtitle']));
			}

			//$subject and $message are magic variables created by the code returned from fetch_email_phrase
			//when it gets returned.
			vB_Mail::vbmail($parentemail, $maildata['subject'], $maildata['message'], true);
		}
		vB_Cache::instance(vB_Cache::CACHE_FAST)->event("userData_$userid");

		// Legacy Hook 'userdata_postsave' Removed //
	}

	/**
	* Deletes a user
	*
	* @return	mixed	The number of affected rows
	*/
	function delete($doquery = true)
	{

		if (empty($this->existing['usergroupid']) OR empty($this->existing['membergroupids']))
		{
			if (empty($this->existing['userid']))
			{
				throw new Exception('invalid_data');
		}
			$userInfo = $this->assertor->getRow('user', array('userid' => $this->existing['userid']));

			if (empty($userInfo) OR !empty($userInfo['error']))
			{
				throw new Exception('invalid_data');
			}

			$this->existing = $userInfo;
		}
		// make sure we are not going to delete the last admin o.O
		if ($this->is_admin($this->existing['usergroupid'], $this->existing['membergroupids']) AND $this->count_other_admins($this->existing['userid']) == 0)
		{
			$this->error('cant_delete_last_admin');
			return false;
		}

		if (!$this->pre_delete($doquery))
		{
			return false;
		}

		$this->condition = array(array('field' => 'userid', 'value' => $this->existing['userid'],
			'operator' => vB_dB_Query::OPERATOR_EQ));

		$return = $this->db_delete(TABLE_PREFIX,  'user');
		if ($return)
		{
			$this->db_delete(TABLE_PREFIX, 'vBForum:userfield');
			$this->db_delete(TABLE_PREFIX, 'vBForum:usertextfield');

			$this->post_delete($doquery);
		}


		return $return;
	}

	/**
	* Any code to run after deleting
	*
	* @param	Boolean Do the query?
	*/
	function post_delete($doquery = true)
	{
		/*
		$this->assertor->update('post', array(
				'username' => $this->existing['username'],
				'userid' => 0
		), array(
				'userid' => $this->existing['userid']
		));
		$this->assertor->update('groupmessage', array(
				'postusername' => $this->existing['username'],
				'postuserid' => 0
		), array(
				'postuserid' => $this->existing['userid']
		));
		$this->assertor->update('discussion', array(
				'lastposter' => $this->existing['username'],
				'lastposterid' => 0
		), array(
				'lastposterid' => $this->existing['userid']
		));
		$this->assertor->update('visitormessage', array(
				'postusername' => $this->existing['username'],
				'postuserid' => 0
		), array(
				'postuserid' => $this->existing['userid']
		));
		$this->assertor->delete('visitormessage', array('userid' => $this->existing['userid']));
		$this->assertor->update('usernote', array(
				'username' => $this->existing['username'],
				'posterid' => 0
		), array(
				'posterid' => $this->existing['userid']
			));
		 */
		$this->assertor->delete('usernote', array('userid' => $this->existing['userid']));
		$this->assertor->delete('access', array('userid' => $this->existing['userid']));
		$this->assertor->delete('event', array('userid' => $this->existing['userid']));
		$this->assertor->delete('customavatar', array('userid' => $this->existing['userid']));
		$this->deleteFile($this->options['avatarpath'] . '/avatar' . $this->existing['userid'] . '_' . $this->existing['avatarrevision'] . '.gif');

		$this->assertor->delete('vBForum:customprofilepic', array('userid' => $this->existing['userid']));
		$this->deleteFile($this->options['profilepicpath'] . '/profilepic' . $this->existing['userid'] . '_' . $this->existing['profilepicrevision'] . '.gif');

		$this->assertor->delete('vBForum:sigpic', array('userid' => $this->existing['userid']));
		$this->deleteFile($this->options['sigpicpath'] . '/sigpic' . $this->existing['userid'] . '_' . $this->existing['sigpicrevision'] . '.gif');

		$this->assertor->delete('vBForum:moderator', array('userid' => $this->existing['userid']));
		$this->assertor->delete('vBForum:reputation', array('userid' => $this->existing['userid']));
//		$this->assertor->delete('subscribeforum', array('userid' => $this->existing['userid']));
//		$this->assertor->delete('subscribethread', array('userid' => $this->existing['userid']));
		$this->assertor->delete('subscribeevent', array('userid' => $this->existing['userid']));
		$this->assertor->delete('vBForum:subscriptionlog', array('userid' => $this->existing['userid']));
		$this->assertor->delete('session', array('userid' => $this->existing['userid']));
		$this->assertor->delete('userban', array('userid' => $this->existing['userid']));
		$this->assertor->delete('vBForum:usergrouprequest', array('userid' => $this->existing['userid']));
		$this->assertor->delete('vBForum:announcementread', array('userid' => $this->existing['userid']));
		$this->assertor->delete('infraction', array('userid' => $this->existing['userid']));
		$this->assertor->delete('userstylevar', array('userid' => $this->existing['userid']));
		//$this->assertor->delete('groupread', array('userid' => $this->existing['userid']));
		//$this->assertor->delete('discussionread', array('userid' => $this->existing['userid']));
		//$this->assertor->delete('subscribediscussion', array('userid' => $this->existing['userid']));
		//$this->assertor->delete('subscribegroup', array('userid' => $this->existing['userid']));
		$this->assertor->delete('profileblockprivacy', array('userid' => $this->existing['userid']));
		$this->assertor->delete('vBForum:sentto', array('userid' => $this->existing['userid']));
		$this->assertor->delete('vBForum:messagefolder', array('userid' => $this->existing['userid']));

		$pendingfriends = array();
		$currentfriends = array();

		$friendlist = $this->assertor->getRows('userlist', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'userid', 'value' => $this->existing['userid'], 'operator' => vB_dB_Query::OPERATOR_EQ),
						array('field' => 'type', 'value' => 'buddy', 'operator' => vB_dB_Query::OPERATOR_EQ),
						array('field' => 'friend', 'value' => array('pending', 'yes'), 'operator' => vB_dB_Query::OPERATOR_EQ)
				)
		));

		foreach ($friendlist as $friend)
		{
			if ($friend['friend'] == 'yes')
			{
				$currentfriends[] = $friend['relationid'];
			}
			else
			{
				$pendingfriends[] = $friend['relationid'];
			}
		}

		if (!empty($pendingfriends))
		{
			$this->assertor->update('updFriendReqCount', array(), array('userid' => $pendingfriends));
		}

		if (!empty($currentfriends))
		{
			$this->assertor->update('updFriendCount', array(), array('userid' => $currentfriends));
		}

		$this->assertor->assertQuery('delUserList', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'userid' => $this->existing['userid'], 'relationid' => $this->existing['userid']));

		$admindm = new vB_Datamanager_Admin($this->registry, vB_DataManager_Constants::ERRTYPE_SILENT);
		$admindm->set_existing($this->existing);
		$admindm->delete();
		unset($admindm);

/*
		$groups = $this->assertor->getRows('socialgroup', array('creatoruserid' => $this->existing['userid']));

		$groupsowned = array();

		foreach ($groups as $group)
		{
			$groupsowned[] = $group['groupid'];
		}
		//$this->registry->db->free_result($groups);

		if (!empty($groupsowned))
		{
			require_once(DIR . '/includes/functions_socialgroup.php');
			foreach($groupsowned AS $groupowned)
			{
				$group = fetch_socialgroupinfo($groupowned);
				if (!empty($group))
				{
					// dm will have problem if the group is invalid, and in all honesty, at this situation,
					// if the group is no longer present, then we don't need to worry about it anymore.
					$socialgroupdm = new vB_Datamanager_SocialGroup($this->registry, vB_DataManager_Constants::ERRTYPE_SILENT);
					$socialgroupdm->set_existing($group);
					$socialgroupdm->delete();
				}
			}
		}

		$groupmemberships = $this->assertor->getRows('getGroupMemberships', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'userid' => $this->existing['userid']
		));

		$socialgroups = array();
		foreach ($groupmemberships as $groupmembership)
		{
			$socialgroups["$groupmembership[groupid]"] = $groupmembership;
		}
 */
		$types = vB_Types::instance();

		//todo -- fix this to work with the new attachment code...
		/*
		$picture_sql = $this->assertor->getRows('attachment', array(
				'userid' => $this->existing['userid'],
				'contenttypeid' => array(intval($types->getContentTypeID('vBForum_SocialGroup')), intval($types->getContentTypeID('vBForum_Album')))
		));
		$pictures = array();

		$attachdm = new vB_Datamanager_Attachment($this->registry, vB_DataManager_Constants::ERRTYPE_SILENT);
		foreach ($picture_sql as $picture)
		{
			$attachdm->set_existing($picture);
			$attachdm->delete();
		}
		*/
/*
		if (!empty($socialgroups))
		{
			$this->assertor->delete('socialgroupmember', array('userid' => $this->existing['userid']));

			foreach ($socialgroups AS $group)
			{
				$groupdm = new vB_Datamanager_SocialGroup($this->registry, vB_DataManager_Constants::ERRTYPE_STANDARD);
				$groupdm->set_existing($group);
				$groupdm->rebuild_membercounts();
				$groupdm->rebuild_picturecount();
				$groupdm->save();

				list($pendingcountforowner) = $this->assertor->getRow('getSumModeratedMembers', array('creatoruserid' => $group['creatoruserid']));

				$this->assertor->update('user', array(
						'socgroupreqcount' => intval($pendingcountforowner),
				), array(
						'userid' => $group['creatoruserid']
				));
			}

			unset($groupdm);
		}

		$this->assertor->update('socialgroup',
						array('transferowner' => 0),
						array('transferowner' => $this->existing['userid'])
		);
 */
//		$this->assertor->delete('album', array('userid' => $this->existing['userid']));

//		$this->assertor->update('picturecomment',
//						array('postusername' => $this->existing['username'], 'postuserid' => 0),
//						array('postuserid' => $this->existing['userid'])
//		);

		//For posts we set the owner of any attachments to 1. We delete any albums and anything in the protected channels.
		//This does the delete.
		$this->assertor->assertQuery('vBForum:deleteProtectedUserData',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD, 'userid' => $this->existing['userid']));

		//Now we need to update what remains.
		$this->assertor->assertQuery('vBForum:node',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			vB_dB_Query::CONDITIONS_KEY => array('userid' => $this->existing['userid']),
			'userid' => 1));

		$this->assertor->assertQuery('filedata',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			vB_dB_Query::CONDITIONS_KEY => array('userid' => $this->existing['userid']),
			'userid' => 1));

		//todo -- actually delete vistor messages and pms and other nodes that are indepentant of
		//a particular user.
		//require_once(DIR . '/includes/adminfunctions.php');
		//delete_user_pms($this->existing['userid'], false);

		require_once(DIR . '/includes/functions_databuild.php');

		// Legacy Hook 'userdata_delete' Removed //

		build_user_statistics();
		build_birthdays();
	}

	// #############################################################################
	// functions that are executed as part of the user save routine

	/**
	* Updates all denormalized tables that contain a 'username' field (or field that holds a username)
	*
	* @param	integer	User ID
	* @param	string	The user name. Helpful if you want to call this function from outside the DM.
	*/
	function update_username($userid, $username = null)
	{
		if ($username != null AND $username != '')
		{
			$doupdate = true;
		}
		else if (isset($this->user['username']) AND $this->user['username'] != $this->existing['username'])
		{
			$doupdate = true;
			$username = $this->user['username'];
		}
		else
		{
			$doupdate = false;
		}

		if ($doupdate)
		{
			// pm receipt 'tousername'
			//$this->assertor->update('pmreceipt',
			//				array('tousername' => $username),
			//				array('touserid' => $userid)
			//);

			// pm text 'fromusername'
			//$this->assertor->update('pmtext',
			//				array('fromusername' => $username),
			//				array('fromuserid' => $userid)
			//);

			// these updates work only when the old username is known,
			// so don't bother forcing them to update if the names aren't different
			if ($this->existing['username'] != $username)
			{
				// pm text 'touserarray'
//				$this->assertor->update('updPmText',
//								array(
//										'userid' => $userid,
//										'exusrstrlen' => strlen($this->existing['username']),
//										'exusername' => $this->existing['username'],
//										'usrstrlen' => strlen($username),
//										'username' => $username								)
//				);

				// forum 'lastposter'
//				$this->assertor->update('forum',
//								array('lastposter' => $username),
//								array('lastposter' => $this->existing['username'])
//				);

//				// thread 'lastposter'
//				$this->assertor->update('thread',
//								array('lastposter' => $username),
//								array('lastposter' => $this->existing['username'])
//				);
			}

			// thread 'postusername'
//			$this->assertor->update('thread',
//							array('postusername' => $username),
//							array('postuserid' => $userid)
//			);

			// post 'username'
//			$this->assertor->update('post',
//							array('username' => $username),
//							array('userid' => $userid)
//			);

			// usernote 'username'
			$this->assertor->update('usernote',
							array('username' => $username),
							array('posterid' => $userid)
			);

			// deletionlog 'username'
			$this->assertor->update('deletionlog',
							array('username' => $username),
							array('userid' => $userid)
			);

			// editlog 'username'
			$this->assertor->update('editlog',
							array('username' => $username),
							array('userid' => $userid)
			);

			// postedithistory 'username'
			$this->assertor->update('vbForum:postedithistory',
							array('username' => $username),
							array('userid' => $userid)
			);

			// socialgroup 'lastposter'
//			$this->assertor->update('socialgroup',
//							array('lastposter' => $username),
//							array('lastposterid' => $userid)
//			);

			// discussion 'lastposter'
//			$this->assertor->update('discussion',
//							array('lastposter' => $username),
//							array('lastposterid' => $userid)
//			);

			// groupmessage 'postusername'
//			$this->assertor->update('groupmessage',
//							array('postusername' => $username),
//							array('postuserid' => $userid)
//			);

			// visitormessage 'postusername'
//			$this->assertor->update('visitormessage',
//							array('postusername' => $username),
//							array('postuserid' => $userid)
//			);

			//Now we need to update what remains.
			$this->assertor->assertQuery('vBForum:node',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				vB_dB_Query::CONDITIONS_KEY => array('userid' => $userid),
				'authorname' => $username)
			);

			$this->assertor->assertQuery('vBForum:node',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				vB_dB_Query::CONDITIONS_KEY => array('lastauthorid' => $userid),
				'lastcontentauthor' => $username)
			);

			//  Rebuild newest user information
			require_once(DIR . '/includes/functions_databuild.php');

			// Legacy Hook 'userdata_update_username' Removed //

			build_user_statistics();
			build_birthdays();
		}
	}

	/**
	* Updates user subscribed threads/forums to reflect new permissions
	*
	* @param	integer	User ID
	*/
	function update_subscriptions($userid)
	{
		$bf_ugp_forumpermissions = $this->datastore->get_value('bf_ugp_forumpermissions');
		unset($this->existing['forumpermissions']);
		$this->existing['permissions'] = cache_permissions($this->existing);

		$old_canview = array();
		$old_canviewthreads = array();
		foreach ($this->existing['channelpermissions'] AS $nodeid => $perms)
		{
			if ($perms & $bf_ugp_forumpermissions['canview'])
			{
				$old_canview[] = $nodeid;
			}
			if ($perms & $bf_ugp_forumpermissions['canviewthreads'])
			{
				$old_canviewthreads[] = $nodeid;
			}
		}

		$user_perms = array(
			'userid'         => $this->fetch_field('userid'),
			'usergroupid'    => $this->fetch_field('usergroupid'),
			'membergroupids' => $this->fetch_field('membergroupids')
		);

		cache_permissions($user_perms);
		$remove_subs = array();
		$remove_forums = array();
		foreach ($old_canview AS $nodeid)
		{
			if (!($user_perms['forumpermissions']["$nodeid"] & $bf_ugp_forumpermissions['canview']))
			{
				$remove_forums[] = $nodeid;
			}
		}
		foreach($old_canviewthreads AS $nodeid)
		{
			if (!($user_perms['channelpermissions']["$nodeid"] & $bf_ugp_forumpermissions['canviewthreads']))
			{
				$remove_subs[] = $nodeid;
			}
		}

		$add_subs = array();
		foreach ($user_perms['channelpermissions'] AS $nodeid => $perms)
		{
			if (($perms & $bf_ugp_forumpermissions['canviewthreads'] AND $perms & $bf_ugp_forumpermissions['canview']) AND (!($this->existing['channelpermissions']["$nodeid"] & $bf_ugp_forumpermissions['canviewthreads']) OR !($this->existing['channelpermissions']["$nodeid"] & $bf_ugp_forumpermissions['canview'])))
			{
				$add_subs[] = $nodeid;
			}
		}

		if (!empty($remove_forums))
		{
			//$forum_list = implode(',', $remove_forums);
//			$this->assertor->delete(
//				'subscribeforum',
//				array(
//					array('field' => 'userid', 'value' => $userid, 'operator' => vB_dB_Query::OPERATOR_EQ),
//					array('field' => 'forumid', 'value' => $remove_forums, 'operator' => vB_dB_Query::OPERATOR_EQ)
//				)
//			);
		}

		$remove_subs = array_unique(array_merge($remove_subs, $remove_forums));

		if (!empty($remove_subs) OR !empty($add_subs))
		{
			$forum_list = array_unique(array_merge($remove_subs, $add_subs));
//			$threads = $this->assertor->getRows('getSubscribedThreads', array(
//					'userid' => $userid,
//					'forumid' => $forum_list
//			));
//			$remove_thread = array();
//			$add_thread = array();
//			foreach ($threads as $thread)
//			{
//				if ($thread['canview'] == 0 AND in_array($thread['forumid'], $add_subs))
//				{
//					$add_thread[] = $thread['subscribethreadid'];
//				}
//				else if ($thread['canview'] == 1 AND in_array($thread['forumid'], $remove_subs))
//				{
//					$remove_thread[] = $thread['subscribethreadid'];
//				}
//			}
//			unset($add_subs, $remove_subs);
			//$this->dbobject->free_result($threads);
//			if (!empty($remove_thread))
//			{
//				$this->assertor->update('updRemoveSubscribedThreads',
//								array('subscribethreadid' => $remove_thread),
//								array('userid' => $userid, 'subscribethreadid' => $remove_thread)
//				);
//			} elseif (!empty($add_thread)) {
//				$this->assertor->update('updAddSubscribedThreads',
//								array('subscribethreadid' => $add_thread),
//								array('userid' => $userid, 'subscribethreadid' => $add_thread)
//				);
//			}
		}
	}

	/**
	* Rebuilds the birthday datastore if the user's birthday has changed
	*
	* @param	integer	User ID
	*/
	function update_birthday_datastore($userid)
	{
		if ((isset($this->existing['birthday']) AND $this->fetch_field('birthday') != $this->existing['birthday'])
			OR (isset($this->existing['showbirthday']) AND $this->fetch_field('showbirthday') != $this->existing['showbirthday'])
			OR $this->usergroups_changed()
		)
		{
			require_once(DIR . '/includes/functions_databuild.php');
			build_birthdays();
		}
	}

	/**
	* Inserts a record into the password history table if the user's password has changed
	*
	* @param	integer	User ID
	*/
	function update_password_history($userid)
	{

		if (isset($this->user['password']) AND
			(empty($this->existing['password']) OR ($this->user['password'] != $this->existing['password'])))
		{
			/*insert query*/
			$this->assertor->assertQuery('insPasswordHistory', array(
					'userid' => $userid,
					'password' => $this->user['password'],
					'passworddate' => vB::getRequest()->getTimeNow()
			));
		}
	}

	/**
	* Resets the session styleid and styleid cookie to the user's profile choice
	*
	* @param	integer	User ID
	*/
	function update_style_cookie($userid)
	{
		if (isset($this->user['styleid']) AND $this->options['allowchangestyles'] AND $userid == $this->userinfo['userid'])
		{
			$this->assertor->update('session',
							array('styleid' => $this->user['styleid']),
							array('sessionhash' => $this->session->get('dbsessionhash'))
			);
			if (!@headers_sent())
			{
				vbsetcookie('userstyleid', '', 1);
			}
		}
	}

	/**
	* Resets the languageid cookie to the user's profile choice
	*
	* @param	integer	User ID
	*/
	function update_language_cookie($userid)
	{
		$languagecache = $this->datastore->get_value('languagecache');
		if (isset($this->user['languageid']) AND !empty($languagecache[$this->user['languageid']]['userselect']))
		{
			if (!@headers_sent())
			{
				vbsetcookie('languageid', '', 1);
			}
		}
	}

	/**
	* Resets the threadedmode cookie to the user's profile choice
	*
	* @param	integer	User ID
	*/
	function update_threadedmode_cookie($userid)
	{
		if (isset($this->user['threadedmode']))
		{
			if (!@headers_sent())
			{
				vbsetcookie('threadedmode', '', 1);
			}
		}
	}

	/**
	* Checks to see if a user's usergroup memberships have changed
	*
	* @return	boolean	Returns true if memberships have changed
	*/
	function usergroups_changed()
	{
//		if (!isset($this->existing['usergroupid']))
//		{
//			return true;
//		}

		if (isset($this->user['usergroupid']) AND
			(!isset($this->existing['usergroupid']) OR $this->user['usergroupid'] != $this->existing['usergroupid']))
		{
			return true;
		}
		else if (isset($this->user['membergroupids']) AND
			(!isset($this->existing['membergroupids']) OR $this->user['membergroupids'] != $this->existing['membergroupids']))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Checks usergroupid and membergroupids to see if the user has admin privileges
	*
	* @param	integer	Usergroupid
	* @param	string	Membergroupids (comma separated)
	*
	* @return	boolean	Returns true if user has admin privileges
	*/
	function is_admin($usergroupid, $membergroupids)
	{
		$datastore = vB::getDatastore();

		// check if user has access to controlpanel (extracted from userContext)
		$bf_ugp_adminpermissions = $datastore->get_value('bf_ugp_adminpermissions');
		$permissionContext = new vB_PermissionContext($datastore, $usergroupid, $membergroupids);
		$admin_permissions = $permissionContext->getPermission('adminpermissions');

		return ($admin_permissions & $bf_ugp_adminpermissions['cancontrolpanel']);
	}

	/**
	* Checks usergroupid and membergroupids to see if the user has super moderator privileges
	*
	* @param	integer	Usergroupid
	* @param	string	Membergroupids (comma separated)
	*
	* @return	boolean	Returns true if user has super moderator privileges
	*/
	function is_supermod($usergroupid, $membergroupids)
	{
		$datastore = vB::getDatastore();
		$bf_ugp_adminpermissions = $datastore->get_value('bf_ugp_adminpermissions');
		$permissionContext = new vB_PermissionContext($datastore, $usergroupid, $membergroupids);
		$admin_permissions = $permissionContext->getPermission('adminpermissions');

		return ($admin_permissions & $bf_ugp_adminpermissions['ismoderator']);
	}

	/**
	* Counts the number of administrators OTHER THAN the user specified
	*
	* @param	integer	User ID of user to be checked
	*
	* @return	integer	The number of administrators excluding the current user
	*/
	function count_other_admins($userid)
	{
		$usergroupcache = vB::getDatastore()->get_value('usergroupcache');
		$bf_ugp_adminpermissions = vB::getDatastore()->get_value('bf_ugp_adminpermissions');
		$bf_ugp_genericoptions = vB::getDatastore()->get_value('bf_ugp_genericoptions');
		$admingroups = array();
		$groupsql = '';
		foreach ($usergroupcache AS $usergroupid => $usergroup)
		{
			if ($usergroup['adminpermissions'] & $bf_ugp_adminpermissions['cancontrolpanel'])
			{
				$admingroups[] = $usergroupid;
				if ($usergroup['genericoptions'] & $bf_ugp_genericoptions['allowmembergroups'])
				{
					$groupsql .= "
					OR FIND_IN_SET('$usergroupid', membergroupids)";
				}
			}
		}

		if (empty($groupsql)) {
			$countadmin = $this->assertor->getRow('countOtherAdmins',
				array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'userid' => intval($userid),
					'usergroupid' => $admingroups
				));
		} else {
			$countadmin = $this->assertor->getRow('countOtherAdminsGroups',
				array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'userid' => intval($userid),
					'usergroupid' => $admingroups,
					'groupids' => $usergroupid
				));
		}

		return $countadmin['users'];
	}

	/**
	* Inserts or deletes a record from the administrator table if necessary
	*
	* @param	integer	User ID of this user
	* @param	boolean	Whether or not the usergroups of this user have changed
	* @param	boolean	Whether or not the user is now an admin
	* @param	boolean	Whether or not the user was an admin before this update
	*/
	function set_admin($userid, $usergroups_changed, $isadmin, $wasadmin = false)
	{
		if ($isadmin AND !$wasadmin)
		{
			// insert admin record
			$admindm = new vB_Datamanager_Admin($this->registry, vB_DataManager_Constants::ERRTYPE_SILENT);
			$admindm->set('userid', $userid);
			$admindm->save();
			unset($admindm);

			$this->insertedadmin = true;
		}
		else if ($usergroups_changed AND $wasadmin AND !$isadmin)
		{
			// delete admin record
			$info = array('userid' => $userid);

			$admindm = new vB_Datamanager_Admin($this->registry, vB_DataManager_Constants::ERRTYPE_SILENT);
			$admindm->set_existing($info);
			$admindm->delete();
			unset($admindm);
		}
	}

	/**
	* Inserts or deletes a record from the moderators table if necessary
	*
	* @param	integer	User ID of this user
	* @param	boolean	Whether or not the usergroups of this user have changed
	* @param	boolean	Whether or not the user is now a super moderator
	* @param	boolean	Whether or not the user was a super moderator before this update
	*/
	function set_supermod($userid, $usergroups_changed, $issupermod, $wassupermod = false)
	{
		if ($issupermod AND !$wassupermod)
		{
			// insert super moderator record
			$moddata = new vB_Datamanager_Moderator($this->registry, vB_DataManager_Constants::ERRTYPE_SILENT);
			$moddata->set('userid', $userid);
			$moddata->set('nodeid', 0);

			// need to insert permissions without looping everything
			// the following doesn't yet work.
			//Supermod has everything.
			$modPerms = vB::getDatastore()->get_value('bf_misc_moderatorpermissions');
			$permissions = 0;
			foreach ($modPerms as $perm)
			{
				$permissions += $perm;
			}
			$moddata->set('permissions', $permissions);
			$modPerms = vB::getDatastore()->get_value('bf_misc_moderatorpermissions2');
			$permissions = 0;
			foreach ($modPerms as $perm)
			{
				$permissions += $perm;
			}
			$moddata->set('permissions2', $permissions);
			$moddata->save();
			unset($moddata);

		}
		else if ($usergroups_changed AND $wassupermod AND !$issupermod)
		{
			// delete super moderator record
			$info = array('userid' => $userid, 'nodeid' => 0);

			$moddata = new vB_Datamanager_Moderator($this->registry, vB_DataManager_Constants::ERRTYPE_SILENT);
			$moddata->set_existing($info);
			$moddata->delete();
			unset($moddata);
		}
	}

	/**
	* Bla bla bla
	*
	* @param	integer	User ID
	*/
	function update_ban_status($userid)
	{
		$userid = intval($userid);
		$usergroupid = $this->fetch_field('usergroupid');
		$usergroupcache = vB::getDatastore()->get_value('usergroupcache');
		$bf_ugp_genericoptions = vB::getDatastore()->get_value('bf_ugp_genericoptions');

		if ($usergroupcache["$usergroupid"]['genericoptions'] & $bf_ugp_genericoptions['isnotbannedgroup'])
		{
			// user is going to a non-banned group, so there's no reason to keep this record (it won't be used)
			$this->assertor->delete('userban', array('userid' => $userid));
		}
		else
		{
			// check to see if there is already a ban record for this user...
			if (!($check = $this->assertor->getRow('userban', array('userid' => $userid))))
			{
				// ... there isn't, so create one
				$ousergroupid = $this->existing['usergroupid'];
				$odisplaygroupid = $this->existing['displaygroupid'];

				// make sure the ban lifting record doesn't loop back to a banned group
				if (!($usergroupcache["$ousergroupid"]['genericoptions'] & $bf_ugp_genericoptions['isnotbannedgroup']))
				{
					$ousergroupid = 2;
				}
				if (!($usergroupcache["$odisplaygroupid"]['genericoptions'] & $bf_ugp_genericoptions['isnotbannedgroup']))
				{
					$odisplaygroupid = 0;
				}

				// insert a ban record
				/*insert query*/
				$this->assertor->insert('userban', array(
						'userid' => $userid,
						'usergroupid' => $ousergroupid,
						'displaygroupid' => $odisplaygroupid,
						'customtitle' => intval($this->fetch_field('customtitle')),
						'usertitle' => $this->fetch_field('usertitle'),
						'adminid' => $this->userinfo['userid'],
						'bandate' => vB::getRequest()->getTimeNow(),
						'liftdate' => 0
				));
			}
		}
	}

	/**
	* Sends a welcome pm to the user
	*
	*/
	function send_welcomepm($fromuser = null, $recipient = false)
	{
		if ($this->options['welcomepm'] AND $username = unhtmlspecialchars($this->fetch_field('username')))
		{
			if (!$fromuser)
			{
				$fromuser = $this->assertor->getRow('user', array('userid' => $this->options['welcomepm']));
			}

			if ($fromuser)
			{
				cache_permissions($fromuser, false);
				$maildata = vB_Api::instanceInternal('phrase')
					->fetchEmailPhrases('welcomepm', array($this->options['bbtitle']), array($this->options['bbtitle']));

				$data = array('sentto' => $recipient, 'title' => $maildata['subject'], 'rawtext' => $maildata['message'], 'sender' => $fromuser['userid']);
				$pm_library = vB_Library::instance('Content_Privatemessage');
				$pm_library->add($data);

			}
		}
	}
}
