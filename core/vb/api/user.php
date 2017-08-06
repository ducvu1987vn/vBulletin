<?php

/**
 * vB_Api_User
 *
 * @package vBApi
 * @access public
 */
class vB_Api_User extends vB_Api
{
	const USERINFO_AVATAR = 'avatar'; // Get avatar
	const USERINFO_LOCATION = 'location'; // Process user's online location
	const USERINFO_PROFILEPIC = 'profilepic'; // Join the customprofilpic table to get the userid just to check if we have a picture
	const USERINFO_ADMIN = 'admin'; // Join the administrator table to get various admin options
	const USERINFO_SIGNPIC = 'signpic'; // Join the sigpic table to get the userid just to check if we have a picture
	const USERINFO_USERCSS = 'usercss'; // Get user's custom CSS
	const USERINFO_ISFRIEND = 'isfriend'; // Is the logged in User a friend of this person?

	protected $disableWhiteList = array('hasPermissions');

	protected $users = array();

	protected $userContext;

	protected $groupInTopic = array();
	protected $moderatorsOf = array();
	protected $membersOf = array();
	protected $permissionContext = array();
	protected $referrals = array();
	protected $avatarsCache = array();
	protected $avatarUserCache = array();
	// usertitle
	protected $usertitleCache = array();


	// user privacy options
	protected $privacyOptions = array(
		'showContactInfo' => 'contact_info', 'showAvatar' => 'profile_picture',
		'showActivities' => 'activities', 'showVM' => 'visitor_messages',
		'showSubscriptions' => 'following', 'showSubscribers' => 'followers',
		'showPhotos' => 'photos', 'showVideos' => 'videos', 'showGroups' => 'group_memberships'
	);

	protected $library;

	protected function __construct()
	{
		parent::__construct();
		$this->userContext = vB::getUserContext();
		$this->library = vB_Library::instance('user');
	}

	/**
	 * * This gets the information needed for a user's profile. Only public information unless this is an admin or the user.
	 */
	public function fetchProfileInfo($userid = false)
	{
		$options = vB::getDatastore()->getValue('options');
		$currentUserid = vB::getCurrentSession()->get('userid');

		if (empty($userid))
		{
			if (empty($_REQUEST['userid']))
			{
				$userid = $currentUserid;
			}
			else
			{
				$userid = intval($_REQUEST['userid']);
			}
		}
		else
		{
			$userid = intval($userid);
		}


		if (($userid < 1))
		{
			throw new vB_Exception_Api('incorrect_data');
		}

		$hashKey = 'vBProfileUser_' . $userid;

		$fastCache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$userInfo = $fastCache->read($hashKey);

		if (empty($userInfo))
		{
			$userInfo = vB_User::fetchUserinfo($userid, array(vB_Api_User::USERINFO_AVATAR, vB_Api_User::USERINFO_PROFILEPIC,
				vB_Api_User::USERINFO_ADMIN, vB_Api_User::USERINFO_SIGNPIC));

			// Some things even admins shouldn't see
			foreach (array('password', 'salt', 'coppauser', 'securitytoken_raw', 'securitytoken', 'logouthash', 'fbaccesstoken') as $field) {
				unset($userInfo[$field]);
			}
			try {
				$this->checkHasAdminPermission('canadminusers');
				// If this doesn't throw an exception, the other data is visible to an admin.
			}
			catch (Exception $e) {
				if (vB::getCurrentSession()->get('userid') != $userid) {
					foreach (array('passworddate', 'parentemail', 'logintype', 'ipaddress', 'passworddate',
							'referrerid', 'ipoints', 'infractions', 'warnings', 'infractiongroupids', 'infractiongroupid',
							) as $field) {
						unset($userInfo[$field]);
					}
				}
			}

			/**
			 * * Fields for the user's profile pages
			 */
			$assertor = vB::getDbAssertor();

			//see if we have a cached userfield
			$fields = vB_Cache::instance(vB_Cache::CACHE_FAST)->read("userFields_$userid");

			if ($fields == false)
			{
				$fields = $assertor->getRow('vBForum:userfield',  array('userid' => $userid));
				vB_Cache::instance(vB_Cache::CACHE_FAST)->write("userFields_$userid", $fields, 1440, "userData_$userid");
			}

			$customFields = array();

			if (!empty($fields))
			{
				// Show hidden fields only if the user views his own profile or if it has the permission to see them
				$hidden = array(0);
				if ($currentUserid == $userid OR vB_Api::instanceInternal('user')->hasPermissions('genericpermissions', 'canseehiddencustomfields'))
				{
					$hidden[] = 1;
				}

				$fieldsInfo = $assertor->getRows('vBForum:fetchCustomProfileFields', array('hidden' => $hidden));
				foreach ($fieldsInfo as $customField)
				{
					$catNameString = ($customField['profilefieldcategoryid']) ? 'category' . $customField['profilefieldcategoryid'] . '_title' : 'default';
					$fieldNameString = 'field' . $customField['profilefieldid'] . '_title';
					$customFields[$catNameString][$fieldNameString] = array(
						'val' => $this->getCustomFieldValue($customField, $fields),
						'hidden' => $customField['hidden'],
					);
				}
			}
			$userInfo['customFields'] = $customFields;

			/**
			 * Check whether user has permission to use friends list (follow users)
			 */
			$userInfo['canusefriends'] = vB::getUserContext($userid)->hasPermission('genericpermissions2', 'canusefriends');
			$userInfo['canviewmembers'] = vB::getUserContext($userid)->hasPermission('genericpermissions', 'canviewmembers');


			/**
			 * * User counts
			 */
			$followApi = vB_Api::instanceInternal('follow');
			if ($currentUserid == $userid OR $userInfo['canusefriends'])
			{
				$follows = $followApi->getFollowing($userid);
				$userInfo['followsCount'] = $follows['paginationInfo']['totalcount'];
			}
			$followers = $followApi->getFollowers($userid);
			$userInfo['followersCount'] = $followers['paginationInfo']['totalcount'];

			$userInfo['socialGroupsCount'] = 10;

			if (isset($this->referrals[$userid]))
			{
				$referrals = $this->referrals[$userid];
			}
			else
			{
				$referrals = $assertor->getRow('vBForum:getReferralsCount', array('userid' => $userid));
				$this->referrals[$userid] = $referrals;
			}

			$userInfo['birthdayTimestamp'] = 0;
			$userInfo['referralsCount'] = $referrals['referrals'];

			if ($userInfo['birthday_search'])
			{
				list($year, $month, $day) = explode("-", $userInfo['birthday_search']);
				$userInfo['birthdayTimestamp'] = mktime(0, 0, 0, $month, $day, $year);
				$userInfo['age'] = (date("md") < $month . $day ? date("Y") - $year - 1 : date("Y") - $year);
			}

			/**
			 * Get vms info
			 */
			$vms = $assertor->getRows('vBForum:node',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'setfor' => $userid),
				array('field' => 'publishdate', 'direction' => vB_dB_Query::SORT_DESC)
			);
			vB_Library_Content::writeToCache($vms, vB_Library_Content::CACHELEVEL_NODE);
			$userInfo['vmCount'] = count($vms);
			$userInfo['vmMostRecent'] = ($userInfo['vmCount']) ? $vms[0]['publishdate'] : 0;

			/**
			 * Let's get posts per day
			 */
			$timeIn = (vB::getRequest()->getTimeNow() - $userInfo['joindate']) / (24 * 60 * 60);
			if (($timeIn >= 1) AND ($userInfo['posts'] > 0))
			{
				$userInfo['postPerDay'] = number_format(($userInfo['posts'] / $timeIn), 2);
			}
			else
			{
				$userInfo['postPerDay'] = $userInfo['posts'];
			}

			$fastCache->write($hashKey, $userInfo, 1440, 'userChg_' . $userid);
		}

		// add current user flags
		// if user is the profile owner..
		$userInfo['showAvatar'] = 1;
		if ($currentUserid == $userid)
		{
			if (vB::getUserContext()->hasPermission('genericpermissions', 'canuseavatar'))
			{
				$userInfo['canuseavatar'] = 1;
				$userInfo['avatarmaxwidth'] = vB::getUserContext()->getLimit('avatarmaxwidth');
				$userInfo['avatarmaxheight'] = vB::getUserContext()->getLimit('avatarmaxheight');
				$userInfo['avatarmaxsize'] = (vB::getUserContext()->getLimit('avatarmaxsize')/1024);
			}
			else
			{
				$userInfo['canuseavatar'] = 0;
			}

			//Are there any default avatars this user could assign?
			$avatars = vB_Api::instanceInternal('profile')->getDefaultAvatars();
			$userInfo['defaultAvatarCount'] = count($avatars);
			if (($userInfo['defaultAvatarCount']) OR ($userInfo['canuseavatar'] > 0))
			{
				$userInfo['showAvatarOptions'] = 1;
			}
			else
			{
				$userInfo['showAvatarOptions'] = 0;
			}
			if ($userInfo['profilepic'])
			{
				if ($options['usefileavatar'])
				{
					$userInfo['profilepicturepath'] = $options['bburl'] . '/' . $options['profilepicurl'] . "/profilepic$currentUserid" . '_' . $userInfo['profilepicrevision'] . '.gif';
				}
				else
				{
					$userInfo['profilepicturepath'] = $options['bburl'] . "/image.php?u=$currentUserid&type=profile&dateline=".$userInfo['profilepicdateline'];
				}
			}
		}
		else
		{
			$userInfo['canuseavatar'] = $userInfo['showAvatarOptions'] = 0;

			//Check the privacy settings and see if this user has hidden his
			if ($userInfo['privacy_options'] AND vB::getUserContext($userid)->hasPermission('usercsspermissions', 'caneditprivacy'))
			{
				switch ($userInfo['privacy_options']['profile_picture'])
				{
					case 1:
						//visible only if the current user is a subscriber.
						if (($currentUserid == 0) OR (vB_Api::instanceInternal('follow')->isFollowingUser($userid) != vB_Api_Follow::FOLLOWING_YES))
						{
							$userInfo['showAvatar'] = 0;
						}
						break;
					case 2:
						//visible only if the current user is a registered user.
						if ($currentUserid == 0)
						{
							$userInfo['showAvatar'] = 0;
						}
						break;
				} // switch
			}
			$userInfo['profilepicturepath'] = '';
		}

		$this->setCurrentUserFlags($userInfo);
		return $userInfo;
	}

	protected function getCustomFieldValue($customField, $fieldValues)
	{
		switch ($customField['type'])
		{
			case 'select_multiple':
			case 'checkbox':
				$data = unserialize($customField['data']);
				$value = '';
				foreach ($data as $key => $val)
				{
					if ($fieldValues['field'.$customField['profilefieldid']] & pow(2,$key))
					{
						$value .= $val . ', ';
					}
				}
				$value = substr($value, 0, -2);
				break;
			default:
				$value = $fieldValues['field'.$customField['profilefieldid']];
				break;
		}
		return $value;
	}

	/**
	 * Set current user flags to display or not certain user items.
	 */
	protected function setCurrentUserFlags(&$userInfo)
	{
		$currentUserid = vB::getCurrentSession()->get('userid');
		foreach ($this->privacyOptions AS $key => $opt)
		{
			$userInfo[$key] = 1;
			if (
				$userInfo['userid'] != $currentUserid
					AND
				!vB::getUserContext()->isSuperAdmin()
					AND
				isset($userInfo['privacy_options'][$opt])
					AND
				vB::getUserContext($userInfo['userid'])->hasPermission('usercsspermissions', 'caneditprivacy')
			)
			{
				switch ($userInfo['privacy_options'][$opt])
				{
					case 1:
						if (($currentUserid == 0) OR (vB_Api::instanceInternal('follow')->isFollowingUser($userInfo['userid']) != vB_Api_Follow::FOLLOWING_YES))
						{
							$userInfo[$key] = 0;
						}
						break;
					case 2:
						if ($currentUserid == 0)
						{
							$userInfo[$key] = 0;
						}
						break;
				}
			}
		}
	}

	/**
	 * Fetches the needed info for user settings
	 */
	public function fetchUserSettings($userid = false)
	{
		if (empty($userid))
		{
			$userid = vB::getCurrentSession()->get('userid');
		}
		else
		{
			$userid = intval($userid);
		}

		if (($userid < 1))
		{
			throw new vB_Exception_Api('incorrect_data');
		}

		$userInfo = vB_User::fetchUserinfo($userid, array(vB_Api_User::USERINFO_AVATAR, vB_Api_User::USERINFO_PROFILEPIC,
			vB_Api_User::USERINFO_ADMIN, vB_Api_User::USERINFO_SIGNPIC));
		// Some things even admins shouldn't see
		foreach (array('password', 'salt', 'securitytoken_raw', 'securitytoken', 'logouthash', 'fbaccesstoken') as $field) {
			unset($userInfo[$field]);
		}

		try
		{
			$this->checkHasAdminPermission('canadminusers');
			// If this doesn't throw an exception, the other data is visible to an admin.
		}
		catch (Exception $e)
		{

			if (vB::getCurrentSession()->get('userid') != $userid)
			{
				$fields = array('passworddate', 'logintype', 'ipaddress', 'passworddate',
					'referrerid', 'ipoints', 'infractions', 'warnings', 'infractiongroupids', 'infractiongroupid',);
				foreach ($fields as $field)
				{
					unset($userInfo[$field]);
				}
			}
		}

		if (!$this->useCoppa() OR !$this->needsCoppa($userInfo['birthday']) OR
			($userid != vB::getCurrentSession()->get('userid')))
		{
			unset($userInfo['coppauser']);
			unset($userInfo['parentemail']);
		}

		/**
		 * * Fields for the user's profile pages
		 */
		$assertor = vB::getDbAssertor();

		//see if we have a cached userfield
		$fields = vB_Cache::instance(vB_Cache::CACHE_FAST)->read("userFields_$userid");

		if ($fields == false)
		{
			$fields = $assertor->getRow('vBForum:userfield',  array('userid' => $userid));
			vB_Cache::instance(vB_Cache::CACHE_FAST)->write("userFields_$userid", $fields, 1440, "userData_$userid");
		}

		// CustomFields for user settings
		$settingsCustomFields = array();
		// Types of userfields that have data array
		$fieldsWithData = array('radio', 'checkbox', 'select', 'select_multiple');

		// Web is not a customField we are grabbing this from user.homepage record
		$settingsCustomFields[0]['fields']['-1'] = array('text' => 'usersetting_web', 'field' => 'homepage', 'value' => $userInfo['homepage'], 'type' => 'text');

		$fieldsInfo = $assertor->getRows('vBForum:fetchCustomProfileFields', array('hidden' => array(0,1)));
		foreach ($fieldsInfo as $customField)
		{
			// Setting the category in which the profile field belongs to
			if (!isset($settingsCustomFields[$customField['profilefieldcategoryid']]))
			{
				$catNameString = ($customField['profilefieldcategoryid']) ? (string) new vB_Phrase('cprofilefield', 'category' . $customField['profilefieldcategoryid'] . '_title') : '';
				$catDescString = ($customField['profilefieldcategoryid']) ? (string) new vB_Phrase('cprofilefield', 'category' . $customField['profilefieldcategoryid'] . '_desc') : '';
				$settingsCustomFields[$customField['profilefieldcategoryid']] = array(
					'name' => $catNameString,
					'desc'  => $catDescString,
					'fields' => array(),
				);
			}

			// Adding general field information
			$settingsCustomFields[$customField['profilefieldcategoryid']]['fields'][$customField['profilefieldid']] = array(
				'text' => 'field' . $customField['profilefieldid'],
				'field' => 'field' . $customField['profilefieldid'],
				'value' => $fields['field'.$customField['profilefieldid']],
				'type' => $customField['type'],
				'hidden' => $customField['hidden'],
				'id' => $customField['profilefieldid'],
				'def' => $customField['def'],
			);

			// For specific field types (defined in $fieldsWithData) adding the data array and setting if that option is selected
			if (in_array($customField['type'], $fieldsWithData))
			{
				$tmpData = unserialize($customField['data']);
				foreach ($tmpData as $key => $value)
				{
					$selected = '';
					if ($customField['type'] == 'select_multiple')
					{
						$selected = ($fields['field'.$customField['profilefieldid']] & pow(2,$key)) ? 'selected="selected"' : '';
					}
					elseif ($customField['type'] == 'checkbox')
					{
						$selected = ($fields['field'.$customField['profilefieldid']] & pow(2,$key)) ? 'checked="checked"' : '';
					}
					$settingsCustomFields[$customField['profilefieldcategoryid']]['fields'][$customField['profilefieldid']]['data'][$key+1] = array(
						'value' => $value,
						'selected' => $selected,
					);
				}
			}
		}

		$userInfo['settings_customFields'] = $settingsCustomFields;

		$queryData = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'userid' => $userid);
		$tempData = array();

		/**
		 * Let's get day month and year from birthday
		 *
		 */
		if ($userInfo['birthday_search'] != '0000-00-00')
		{
			$elements = explode('-', $userInfo['birthday_search']);
		}
		else
		{
			$elements = array('', '', '');
		}

		list($userInfo['bd_year'], $userInfo['bd_month'], $userInfo['bd_day']) = $elements;

		/**
		 * we know available providers, so let's see if user has one
		 */
		$userInfo['im_providers'] = array('aim', 'google', 'msn', 'skype', 'yahoo', 'icq');
		$userInfo['has_im'] = false;
		foreach ($userInfo['im_providers'] as $provider)
		{
			if (!empty($userInfo[$provider]))
			{
				$userInfo['has_im'] = true;
			}
		}

		/**
		 * @TODO remove when templates support perm checks
		 */
		$userInfo['canusecustomtitle'] = vB::getUserContext()->hasPermission('genericpermissions', 'canusecustomtitle');

		/**
		 * @TODO timezone options
		 */
		$userInfo['timezones'] = array(
			'-12'  => 'timezone_gmt_minus_1200',
			'-11'  => 'timezone_gmt_minus_1100',
			'-10'  => 'timezone_gmt_minus_1000',
			'-9'   => 'timezone_gmt_minus_0900',
			'-8'   => 'timezone_gmt_minus_0800',
			'-7'   => 'timezone_gmt_minus_0700',
			'-6'   => 'timezone_gmt_minus_0600',
			'-5'   => 'timezone_gmt_minus_0500',
			'-4.5' => 'timezone_gmt_minus_0430',
			'-4'   => 'timezone_gmt_minus_0400',
			'-3.5' => 'timezone_gmt_minus_0330',
			'-3'   => 'timezone_gmt_minus_0300',
			'-2'   => 'timezone_gmt_minus_0200',
			'-1'   => 'timezone_gmt_minus_0100',
			'0'	=> 'timezone_gmt_plus_0000',
			'1'	=> 'timezone_gmt_plus_0100',
			'2'	=> 'timezone_gmt_plus_0200',
			'3'	=> 'timezone_gmt_plus_0300',
			'3.5'  => 'timezone_gmt_plus_0330',
			'4'	=> 'timezone_gmt_plus_0400',
			'4.5'  => 'timezone_gmt_plus_0430',
			'5'	=> 'timezone_gmt_plus_0500',
			'5.5'  => 'timezone_gmt_plus_0530',
			'5.75' => 'timezone_gmt_plus_0545',
			'6'	=> 'timezone_gmt_plus_0600',
			'6.5'  => 'timezone_gmt_plus_0630',
			'7'	=> 'timezone_gmt_plus_0700',
			'8'	=> 'timezone_gmt_plus_0800',
			'9'	=> 'timezone_gmt_plus_0900',
			'9.5'  => 'timezone_gmt_plus_0930',
			'10'   => 'timezone_gmt_plus_1000',
			'11'   => 'timezone_gmt_plus_1100',
			'12'   => 'timezone_gmt_plus_1200'
		);

		$profileApi = vB_Api::instanceInternal('profile');
		/**
		 * style options
		 */
		$styles = $profileApi->getStyles();
		if (count($styles) > 1)
		{
			$userInfo['styles']['count'] = count($styles);
			foreach ($styles as $style)
			{
				if ($style['depth'] > 1)
				{
					$identation = '';
					for($x = 1; $x < $style['depth']; $x++)
					{
						$identation .= '--';
					}
					$style['title'] = $identation . $style['title'];
				}

				$userInfo['styles']['options'][] = $style;
			}
		}

		/**
		 * fetch language options
		 */
		$languages = $profileApi->getLanguages($userInfo['languageid']);
		if (count($languages) > 1)
		{
			$userInfo['languages']['count'] = count($languages);
			$userInfo['languages']['options'] = $languages;
		}
		$userOptions = vB::getDatastore()->getValue('bf_misc_useroptions');
		foreach ($userOptions as $option => $value)
		{
			$userInfo["$option"] = ($userInfo['options'] & $value) ? true : false;
		}

		/**
		 * User max posts
		 */
		foreach (array(-1, 5, 10, 20, 30, 40) as $maxPostOption)
		{
			if ($maxPostOption == $userInfo['maxposts'])
			{
				$userInfo['maxposts_options'][$maxPostOption]['selected'] = true;
			}
			else
			{
				$userInfo['maxposts_options'][$maxPostOption]['selected'] = false;
			}
		}

		/**
		 * DST options
		 */
		$userInfo['dst_options'] = array(
			0 => array('phrase' => 'dst_corrections_always_off', 'selected' => ($userInfo['dstauto'] == 'false' && $userInfo['dstonoff'] == 'false' ? true : false)),
			1 => array('phrase' => 'dst_corrections_always_on', 'selected' => ($userInfo['dstonoff'] == 'true' ? true : false)),
			2 => array('phrase' => 'automatically_detect_dst_settings', 'selected' => ($userInfo['dstauto'] == 'true' ? true : false))
		);
		$userInfo['dst_options'] = array_reverse($userInfo['dst_options'], true);

		/**
		 * SoW options
		 */
		foreach (array(1 => 'sunday', 2 => 'monday', 3 => 'tuesday', 4 => 'wednesday', 5 => 'thursday', 6 => 'friday', 7 => 'saturday') as $idx => $day)
		{
			if ($userInfo['startofweek'] == $idx)
			{
				$userInfo['sow_options'][$idx] = array('day' => $day, 'selected' => true);
			}
			else
			{
				$userInfo['sow_options'][$idx] = array('day' => $day, 'selected' => false);
			}
		}

		$options = vB::getDatastore()->getValue('options');
		//user maxresults for the autocomplete display for the moment
		$userInfo['minuserlength'] = ($options['minuserlength']) ? $options['minuserlength'] : 1;
		$userInfo['maxresults'] = ($options['maxresults']) ? $options['maxresults'] : 20;

		//if facebook connect is enabled
		$userInfo['facebookactive'] = (bool)$options['facebookactive'];

		//user signature
		$userInfo['allow_signatures'] = ((bool)$options['allow_signatures']) & vB::getUserContext()->hasPermission('genericpermissions', 'canusesignature');
		//user ignorelist
		$userInfo['ignorelist'] = $this->fetchUserIgnorelist(explode(' ', $userInfo['ignorelist']));

		//profile options
		 $userInfo['profile_options']['options'] = array();
		foreach (array('everyone', 'followers', 'members') AS $phrase)
		{
			$userInfo['profile_options']['options'][] = $phrase;
		}

		//notifications settings
		$notificationOptions = vB::getDatastore()->getValue('bf_misc_usernotificationoptions');
		$optVals = array();
		foreach ($notificationOptions as $key => $option)
		{
			$optVals[$key] = ($option & $userInfo['notification_options']) ? 'true' : 'false';
		}
		$userInfo['notification_options'] = array('options' => $userInfo['notification_options'], 'values' => $optVals);

		//email notification setting
		$emailOptions = array(
			'0' => array('phrase' => 'usersetting_emailnotification_none'),
			'1' => array('phrase' => 'usersetting_emailnotification_on'),
			'2' => array('phrase' => 'usersetting_emailnotification_daily'),
			'3' => array('phrase' => 'usersetting_emailnotification_weekly')
		);
		foreach ($emailOptions as $key => $emailOption)
		{
			if ($userInfo['autosubscribe'] == $key)
			{
				$emailOptions[$key]['selected'] = 1;
			}
		}
		$userInfo['email_notifications'] = $emailOptions;

		//Permission for hiding reputation level
		$userInfo['canhiderep'] = vB::getUserContext()->hasPermission('genericpermissions', 'canhiderep');

		// Permission for invisible mode
		$userInfo['caninvisible'] = vB::getUserContext()->hasPermission('genericpermissions', 'caninvisible');

		/**
		 * advanced editor interface
		 */
		$editorOptions = array(
			'0' => array('phrase' => 'basic_editor_simple_text_box'),
			'1' => array('phrase' => 'standard_editor_extra_formatting'),
			'2' => array('phrase' => 'enhanced_interface_wysiwyg')
		);
		foreach ($editorOptions as $key => $val)
		{
			if ($key == $userInfo['showvbcode'])
			{
				$editorOptions[$key]['selected'] = true;
			}
		}
		$userInfo['editor_options'] = $editorOptions;
		return $userInfo;
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
	 * @param integer $ User ID
	 * @param array $ Fetch Option (see description)
	 * @param integer $ Language ID. If set to 0, it will use user-set languageid (if exists) or default languageid
	 * @param boolean $ If true, the method won't use user cache but fetch information from DB.
	* @return array The information for the requested user
	*/
	public function fetchUserinfo($userid = false, $option = array(), $languageid = false, $nocache = false)
	{
		$currentUserId = vB::getCurrentSession()->get('userid');
		$userid = intval($userid);

		if ($userid <= 0 AND $currentUserId)
		{
			$userid = $currentUserId;
		}

		if ($languageid === false)
		{
			$languageid = vB::getCurrentSession()->get('languageid');
		}

		//If we just want avatar info, we can proceed.
		if (($userid <> $currentUserId) AND ($option != array(vB_Api_User::USERINFO_AVATAR)))
		{
			try
			{
				$this->checkHasAdminPermission('canadminusers');
			}
			catch (Exception $e)
			{
				if ($currentUserId != $userid)
				{
					return $this->fetchProfileInfo($userid);
				}
			}
		}

		return $this->library->fetchUserinfo($userid, $option, $languageid, $nocache);
	}

	/**
	 * Fetches an array containing info for the current user

	 * @return array The information for the requested user. Userinfo record plus language information
	 */
	public function fetchCurrentUserinfo()
	{
		$session = vB::getCurrentSession();

		//if this is called if there's an error during initialization, and we have nothing yet
		if (empty($session))
		{
			return array();
		}
		$userInfo = $session->fetch_userinfo();
		$vboptions = vB::getDatastore()->getValue('options');

		$languageid = vB::getCurrentSession()->get('languageid');
		if (!$languageid)
		{
			$languageid = $vboptions['languageid'];
		}

		if ($userInfo['languageid'] <> $languageid)
		{
			vB::getCurrentSession()->loadLanguage();
			$userInfo = vB::getCurrentSession()->fetch_userinfo();
		}
		//convert bitfields to arrays for external clients.
		$bitfields = vB::getDatastore()->getValue('bf_misc_languageoptions');
		$lang_options = $userInfo['lang_options'];
		$userInfo['lang_options'] = array();
		foreach ($bitfields as $key => $value)
		{
			$userInfo['lang_options'][$key] = (bool) ($lang_options & $value);
		}
		return $userInfo;
	}

	/**
	 * Fetches the username for a userid, or false if user is not found
	 *
	 * @param integer $ User ID
	 * @return string
	 */
	public function fetchUserName($userid)
	{
		if (!intval($userid))
		{
			return false;
		}

		return $this->library->fetchUserName($userid);
	}

	/**
	 * fetches the proper username markup and title
	 *
	 * @param array $user User info array
	 * @param string $displaygroupfield Name of the field representing displaygroupid in the User info array
	 * @param string $usernamefield Name of the field representing username in the User info array
	 * @return string Username with markup and title
	 */
	public function fetchMusername($user, $displaygroupfield = 'displaygroupid', $usernamefield = 'username')
	{
		vB_User::fetchMusername($user, $displaygroupfield, $usernamefield);

		return $user['musername'];
	}

	/**
	 * Fetch user by its username
	 *
	 * @param string $username Username
	 * @param array $option Fetch Option (see description of fetchUserinfo())
	 * @return array The information for the requested user
	 */
	public function fetchByUsername($username, $option = array())
	{
		$userid = vB::getDbAssertor()->getField('user_fetchidbyusername', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'username' => $username,
		));

		if (!$userid) {
			return false;
		}else {
			//if this was added we might need a cache refresh.
			$result = $this->fetchUserInfo($userid, $option);

			if (empty($result))
			{
				//we know the information is there. We got a userid force so we need to force a refresh.
				$result = vB_User::fetchUserinfo($userid, $option, vB::getCurrentSession()->get('languageid'), true);
			}
			return $result;
		}
	}

	/**
	 * Fetch user by its email
	 *
	 * @param string $email Email
	 * @param array $option Fetch Option (see description of fetchUserinfo())
	 * @return array The information for the requested user
	 */
	public function fetchByEmail($email, $option = array())
	{
		$userid = vB::getDbAssertor()->getField('user', array(
			'email' => $email,
		));

		if (!$userid) {
			return false;
		}else {
			return $this->fetchUserInfo($userid, $option);
		}
	}

	/**
	 * Fetch a list of user
	 *
	 * @param integer $pagenumber
	 * @param integer $perpage
	 * @param string $sortfield
	 * @param string $sortorder
	 * @param integer $usergroupid
	 * @param array $searchdata
	 */
	// public function memberList($pagenumber, $perpage, $sortfield, $sortorder, $usergroupid, $searchdata)
	// {
	// ;
	// }
	/**
	 * Find user
	 *
	 * @param array $user An array of common conditions for user search
	 * @param array $profile An array of user profile field conditions for user search
	 * @param string $orderby Order by
	 * @param string $direction Order direction
	 * @param integer $limitstart Limit start
	 * @param integer $limitnumber Limit number
	 * @return bool |array False if no user found. Otherwise it returns users array as result.
	 *		 The array also contains a field that stores total found user count.
	 */
	public function find($user, $profile, $orderby, $direction, $limitstart = 0, $limitnumber = 25)
	{
		require_once(DIR . '/includes/class_core.php');
		require_once(DIR . '/includes/adminfunctions_user.php');
		require_once(DIR . '/includes/adminfunctions_profilefield.php');

		$conditions = $this->fetchUserSearchCondition($user, $profile);
		$countusers = vB::getDbAssertor()->getField('userFindCount', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'filters' => $conditions['filters'],
			'exceptions' => $conditions['exceptions'],
		));

		$users = vB::getDbAssertor()->getRows('userFind', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'filters' => $conditions['filters'],
			'exceptions' => $conditions['exceptions'],
			'orderby' => $orderby,
			'direction' => $direction,
			'limitstart' => $limitstart,
			vB_dB_Query::PARAM_LIMIT => $limitnumber,
		));

		if ($countusers == 0)
		{
			// no users found!
			return false;
		}
		else
		{
			return array(
				'users' => $users,
				'count' => $countusers,
			);
		}
	}

	/** This returns a user's additional permissions from the groupintopic table
	 *
	 *	@param	int
	 *	@param	int	optional nodeid
	 *
	 *	@return	mixed	Associated array of  array(nodeid, groupid);
	 ***/
	public function getGroupInTopic($userid = false, $nodeid = false, $forceReload = false)
	{
		$userInfo = vB::getCurrentSession()->fetch_userinfo();

		$currentUserid = $userInfo['userid'];
		//we need a single int for userid;
		if (!$userid)
		{
			$userid = $currentUserid;
		}
		else if (!is_numeric($userid) OR !intval($userid))
		{
			throw new vB_Exception_Api('invalid_request');
		}
		else
		{
			$userid = intval($userid);
		}

		//check permissions
		if (($userid != $currentUserid) AND
			!vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canaddowners', $nodeid ))
		{
			throw new vB_Exception_Api('no_permission');
		}

		return $this->library->getGroupInTopic($userid, $nodeid, $forceReload);
	}

	/** Returns a list of channels where this user is moderator
	*
	*
	* 	@param	int		the userid
	*
	*	@return	mixed	array of nodeids
	*/
	public function getModeratorsOf($userid = false)
	{
		$userInfo = vB::getCurrentSession()->fetch_userinfo();

		$currentUserid = $userInfo['userid'];
		//we need a single int for userid;
		if (!$userid)
		{
			$userid = $currentUserid;
		}
		else if (!is_numeric($userid) OR !intval($userid))
		{
			throw new vB_Exception_Api('invalid_request');
		}
		else
		{
			$userid = intval($userid);
		}

		//check permissions
		if (($userid != $currentUserid) AND
			!vB::getUserContext()->hasAdminPermission('canadminpermissions'))
		{
			//this requires admin canadminpermissions or that it be for the current user.
			throw new vB_Exception_Api('no_permission');
		}

		if (!isset($this->moderatorsOf[$userid]))
		{
			$this->computeMembersOf($userid);
		}
		return $this->moderatorsOf[$userid];

	}

	/** Returns a list of channels where this user is moderator
	 *
	 *
	 * 	@param	int		the userid
	 *
	 *	@return	mixed	array of nodeids
	 */

	public function getMembersOf($userid = false)
	{
		$userInfo = vB::getCurrentSession()->fetch_userinfo();

		$currentUserid = $userInfo['userid'];
		//we need a single int for userid;
		if (!$userid)
		{
			$userid = $currentUserid;
		}
		else if (!is_numeric($userid) OR !intval($userid))
		{
			throw new vB_Exception_Api('invalid_request');
		}
		else
		{
			$userid = intval($userid);
		}

		//check permissions
		if (($userid != $currentUserid) AND
			!vB::getUserContext()->hasAdminPermission('canadminpermissions'))
		{
			//this requires admin canadminpermissions or that it be for the current user.
			throw new vB_Exception_Api('no_permission');
		}

		if (!isset($this->membersOf[$userid]))
		{
			$this->computeMembersOf($userid);
		}
		return $this->membersOf[$userid];
	}

	/** This is a wrapper for userContext getCanCreate- it returns the content types a user can create
	*
	*	@param	int		the nodeid
	*
	*	@return mixed 	array of types the user can create in that node
	*
	***/
	public function getCanCreate($nodeid)
	{
		if (empty($nodeid))
		{
			return false;
		}
		return vB::getUserContext()->getCanCreate($nodeid);
	}

	/** Analyzes what groups this user belongs to in specific channels. Stores in member variables
	*
	*	@param	int		the userid
	*
	**/
	protected function computeMembersOf($userid)
	{
		$hashKey = "vB_MembersOf_$userid";
		$cached = vB_Cache::instance(vB_Cache::CACHE_STD)->read($hashKey);
		if ($cached !== false)
		{
			$this->membersOf[$userid] = $cached['membersOf'];
			$this->moderatorsOf[$userid] = $cached['moderatorsOf'];;
			return;
		}
		$this->membersOf[$userid] = $this->moderatorsOf[$userid] = array();

		$groupInTopic = $this->library->getGroupInTopic($userid);

		if (empty($this->permissionContext))
		{
			$this->permissionContext = new vB_PermissionContext(vB::getDatastore(), 2, null, null);
		}

		//scan the groups and set the array

		foreach ($groupInTopic as $permission)
		{
			$groupid = $permission['groupid'];
			$nodeid = false;

			if ($this->permissionContext->getChannelPermSet($groupid, $permission['nodeid']))
			{
				$nodeid = $permission['nodeid'];
			}
			else
			{
				$parentage = vB_Library::instance('node') -> fetchClosureParent($permission['nodeid']);
				foreach ($parentage as $parent)
				{
					if ($this->permissionContext->getChannelPermSet($groupid, $parent['parent']))
					{
						$nodeid = $parent['parent'];
						break;
					}
				}
			}

			//If we got a node with permissions we need to check what they are.
			if ($nodeid)
			{
				if (!in_array($permission['nodeid'], $this->membersOf[$userid]) AND
					$this->permissionContext->getChannelPerm($groupid, 'createpermissions', 'vbforum_privatemessage', $nodeid) > 0)
				{
					$this->membersOf[$userid][] =  $permission['nodeid'] ;
				}
				if (!in_array($permission['nodeid'], $this->moderatorsOf[$userid]) AND
					$this->permissionContext->getChannelPerm($groupid, 'moderatorpermissions', 'canmoderateposts', $nodeid) > 0)
				{
					$this->moderatorsOf[$userid][] =  $permission['nodeid'] ;
				}
			}
		}
		$cacheData = array('membersOf' => $this->membersOf[$userid], 'moderatorsOf' => $this->moderatorsOf[$userid]);
		vB_Cache::instance(vB_Cache::CACHE_STD)->write($hashKey, $cacheData, 1440, "userPerms_$userid");
	}

	/** This grants a user additional permissions in a specific channel, by adding to the groupintopic table
	*
	*	@param	int
	*	@param	mixed	integer or array of integers
	* 	@param	int
	*
	*	@return	bool
	***/
	public function setGroupInTopic($userid, $nodeids, $usergroupid)
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

		$usercontext = vB::getUserContext();
		//check permissions
		foreach ($nodeids as $nodeid)
		{
			if (!$usercontext->getChannelPermission('moderatorpermissions', 'canaddowners', $nodeid ))
			{
				throw new vB_Exception_Api('no_permission');
			}

		}

		$this->clearChannelPerms($userid);
		//class vB_User does the actual work. Here we just want to clean the data.
		return vB_User::setGroupInTopic($userid, $nodeids, $usergroupid);
	}


	/** This removes additional permissions a user was given in a specific channel, by removing from the groupintopic table
	 *
	 *	@param	int
	 *	@param	mixed	integer or array of integers
	 * 	@param	int
	 *
	 *	@return	bool
	 ***/
	public function unsetGroupInTopic($userid, $nodeids, $usergroupid)
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

		//check permissions
		$usercontext = vB::getUserContext();
		//this requires moderatorpermissions->canmoderatetags
		foreach ($nodeids as $nodeid)
		{
			if ((!$usercontext->getChannelPermission('moderatorpermissions', 'canaddowners', $nodeid )) AND !($userid == $usercontext->fetchUserId()))
			{
				throw new vB_Exception_Api('no_permission');
			}

		}

		//and do the deletes
		foreach ($nodeids as $nodeid)
		{
			vB::getDbAssertor()->assertQuery(
				'vBForum:groupintopic', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
					'userid'  => $userid,
					'nodeid'  => $nodeid,
					'groupid' => $usergroupid)
			);

			//deny any pending request
			$pending = vB::getDbAssertor()->assertQuery('vBForum:fetchPendingChannelRequestUser', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'msgtype' => 'request',
					'aboutid' => $nodeid,
					'about' => array(
											vB_Api_Node::REQUEST_TAKE_MODERATOR,
											vB_Api_Node::REQUEST_TAKE_OWNER,
											vB_Api_Node::REQUEST_SG_TAKE_MODERATOR,
											vB_Api_Node::REQUEST_SG_TAKE_OWNER
										),
					'userid' => $userid
				)
			);
			if ($pending)
			{
				$messageApi = vB_Api::instanceInternal('content_privatemessage');
				foreach($pending as $p)
				{
					$messageApi->denyRequest($p['nodeid'], $userid);
				}
			}
		}


		$this->clearChannelPerms($userid);
		$thisUserContext = vB::getUserContext($userid);
		$thisUserContext->clearChannelPermissions($usergroupid);
		vB_Cache::instance(vB_Cache::CACHE_FAST)->event('sgMemberChg_' . $userid);
		vB_Cache::instance()->event('sgMemberChg_' . $userid);
		vB_Cache::instance(vB_Cache::CACHE_FAST)->event('userChg_' . $userid);
		vB_Cache::instance(vB_Cache::CACHE_LARGE)->event('userChg_' . $userid);
		//if we got here all is well.
		return true;
	}


	/** This method clears remembered channel permission
	*
	*	@param	int		the userid to be cleared
	*
	***/
	public function clearChannelPerms($userid)
	{
		if (isset($this->membersOf[$userid]))
		{
			unset($this->membersOf[$userid]);
			unset($this->moderatorsOf[$userid]);
		}

		$this->library->clearChannelPerms($userid);
	}

	/**
	 * Fetches the URL for a User's Avatar
	 *
	 * @param integer $ The User ID
	 * @param boolean $ Whether to get the Thumbnailed avatar or not
	 * @return array Information regarding the avatar
	 */
	public function fetchAvatar($userid, $thumb = false, $userinfo = array())
	{
		// fetchAvatars wants an array of userinfos
		if (!empty($userinfo))
		{
			$userinfo = array($userinfo['userid'] => $userinfo);
		}

		$result = $this->fetchAvatars(array($userid), $thumb, $userinfo);
		if (!empty($result[$userid]))
		{
			$avatarurl = $result[$userid];
		}
		$userContext = vB::getUserContext($userid);
		if (// no avatar defined for this user
			empty($avatarurl)
			//TODO: Decide how we will handle the showavatars user option. We should add to the wireframe or remove the bitfield
			/*OR // user doesn't want to see avatars
			!$avatarinfo['showavatars']
			*/
			OR // user has a custom avatar but no permission to display it
			($avatarurl['hascustom'] AND !$userContext->hasPermission('genericpermissions', 'canuseavatar')
					AND !$userContext->isAdministrator())
		)
		{
			return false;
		}

		return $avatarurl;
	}

	/**
	* Fetch the Avatars for a userid array
	*
	* @param array The User IDs array
	* @param boolean $ Whether to get the Thumbnailed avatar or not
	* @param array	Array of userinfo, possibly already containing the avatar information
	* @return array Information regarding the avatar
	*/
	public function fetchAvatars($userids = array(), $thumb = false, $userinfo = array())
	{
		foreach ($userinfo AS $userid => $_userinfo)
		{
			if (!isset($this->avatarUserCache[$userid]) AND isset($_userinfo['hascustomavatar']))
			{
				$this->avatarUserCache[$userid] = array(
					'userid'         => $_userinfo['userid'],
					'avatarid'       => $_userinfo['avatarid'],
					'avatarpath'     => $_userinfo['avatarpath'],
					'avatarrevision' => $_userinfo['avatarrevision'],
					'dateline'       => $_userinfo['avatardateline'],
					'width'          => $_userinfo['avwidth'],
					'height'         => $_userinfo['avheight'],
					'height_thumb'   => $_userinfo['avheight_thumb'],
					'width_thumb'    => $_userinfo['avwidth_thumb'],
				);
			}
		}

		if (empty($thumb))
		{
			$typekey = 'avatar';
			$def_size = 'medium';
		}
		elseif ($thumb === 'profile')
		{
			$typekey = 'avatar';
			$def_size = 'large';
			$thumb = false;
		}
		else
		{
			$typekey = 'thumb';
			$def_size = 'thumb';
		}

		if(empty($userids))
		{
			return false;
		}

		$cachedKeys = array();
		if (isset($this->avatarsCache[$typekey]))
		{
			$cachedKeys = array_keys($this->avatarsCache[$typekey]);
		}
		$notCachedKeys = array_diff($userids, $cachedKeys);
		$cachedIdsKeys = array_intersect($cachedKeys, $userids);
		$arrayAux = array();

		foreach($notCachedKeys AS $notCachedKey)
		{
			$arrayAux[$notCachedKey] = $notCachedKey;
		}
		$notCached = $arrayAux;
		$arrayAux = array();
		foreach($cachedIdsKeys AS $cachedIdsKey)
		{
			$arrayAux[$cachedIdsKey] = $this->avatarsCache[$typekey][$cachedIdsKey];
		}
		$cachedIds = $arrayAux;
		unset($arrayAux);

		$avatarsurl = array();
		if(!empty($notCached))
		{
			$options = vB::getDatastore()->get_value('options');
			$avatars = array();
			foreach($notCached AS $userid)
			{
				if (isset($this->avatarUserCache[$userid]))
				{
					$avatars[$userid] = $this->avatarUserCache[$userid];
					unset($notCached[$userid]);
				}
			}
			if (!empty($notCached))
			{
				$avatarsinfo = vB::getDbAssertor()->assertQuery('vbForum:fetchAvatarsForUsers', array('userid' => $notCached));
				foreach ($avatarsinfo AS $user)
				{
					$this->avatarUserCache[$user['userid']] = $user;
					$avatars[$user['userid']] = $user;
				}
			}
			$avatarpaths = array();
			foreach ($avatars AS $user)
			{
				$userid = $user['userid'];
				$this->avatarsCache[$typekey][$userid]['avatarurl'] = array();
				if (!empty($user['avatarid']))
				{
						if(!isset($avatarpaths[$user['avatarid']]))
						{
							$avatarpath = $user['avatarpath'];
							//If this is an absolute path we must trim the DIR portion
							if (substr($avatarpath, 0, strlen(DIR)) == DIR)
							{
								$avatarpath = substr($avatarpath, strlen(DIR) + 1);
							}

							$avatarpaths[$user['avatarid']] = array('hascustom' => 0, 'avatarpath' => $avatarpath);
						}
						$this->avatarsCache[$typekey][$userid]['avatarurl'] = $avatarpaths[$user['avatarid']];
				}
				else
				{
					$this->avatarsCache['avatar'][$userid]['avatarurl'] = array('hascustom' => 1);
					$this->avatarsCache['thumb'][$userid]['avatarurl'] = array('hascustom' => 1);
					//the user did not select any avatars
					if (!$user['avatarrevision'] AND !$user['dateline'])
					{
						$this->avatarsCache['avatar'][$userid]['avatarurl']['avatarpath'] = 'images/default/default_avatar_' . $def_size . '.png';
						$this->avatarsCache['avatar'][$userid]['avatarurl']['hascustom'] = 0;
						$this->avatarsCache['thumb'][$userid]['avatarurl']['avatarpath'] = 'images/default/default_avatar_thumb.png';
						$this->avatarsCache['thumb'][$userid]['avatarurl']['hascustom'] = 0;
					}
					else
					{
						if ($options['usefileavatar'])
						{
							$this->avatarsCache['avatar'][$userid]['avatarurl']['avatarpath'] = $options['avatarurl'] . "/{$user['filename']}";
							$this->avatarsCache['thumb'][$userid]['avatarurl']['avatarpath'] = $options['avatarurl'] . "/thumbs/{$user['filename']}";
						}
						else
						{
							$add_session = (class_exists('vB5_Cookie') AND vB5_Cookie::isEnabled()) ? '' : vB::getCurrentSession()->get('sessionurl');
							$this->avatarsCache['avatar'][$userid]['avatarurl']['avatarpath'] = "image.php?" . $add_session . "userid=$userid";
							$this->avatarsCache['thumb'][$userid]['avatarurl']['avatarpath'] = "image.php?" . $add_session . "userid=$userid&thumb=1";

							if (!empty($user['dateline']))
							{
								$this->avatarsCache['thumb'][$userid]['avatarurl']['avatarpath'] .= '&dateline=' . $user['dateline'];
								$this->avatarsCache['avatar'][$userid]['avatarurl']['avatarpath'] .= '&dateline=' . $user['dateline'];
							}
						}
					}
					/* This code was used in vB3 & 4 to passback the avatar image sizes for use in the templates
					As far as I can tell, no vB5 templates make use of this, so I have commented it out for now */
//					if ($thumb)
//					{
//						if (isset($user['width_thumb']) AND isset($user['height_thumb']))
//						{
//							$avatarurl[] = " width=\"$user[width_thumb]\" height=\"$user[height_thumb]\" ";
//						}
//					}
//					else
//					{
//						if (isset($user['width']) AND isset($user['height']))
//						{
//							$avatarurl[] = " width=\"$user[width]\" height=\"$user[height]\" ";
//						}
//					}

						}
				//$this->avatarsCache[$typekey][$userid]['avatarurl'] = $avatarurl;
				$avatarsurl[$userid] = $this->avatarsCache[$typekey][$userid]['avatarurl'];
			}
		}

		if(!empty($cachedIds))
		{
			foreach($cachedIds as $uId => $avatarArray)
			{
				$avatarsurl[$uId] = $avatarArray['avatarurl'];
			}
		}
		return $avatarsurl;
	}

	/**
	 * Generates a totally random string
	 *
	 * @param integer $ Length of string to create
	 * @return	string	Generated String
	 */
	public function fetchUserSalt($length = 30)
	{
		$salt = '';
		for ($i = 0; $i < $length; $i++) {
			$salt .= chr(vbrand(33, 126));
		}
		return $salt;
	}

	/**
	 * Fetches the Profile Fields for a User input form
	 *
	 * @param integer $ Forum Type: 0 indicates a profile field, 1 indicates an option field
	 */
	public function fetchProfileFields($formtype = 0)
	{
		require_once(DIR . '/includes/functions_user.php');
		// get extra profile fields
		$profilefields = vB::getDbAssertor()->getRows('userProfileFields', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'formtype' => $formtype
		));
		return $profilefields;
	}

	/**
	 * Fetches the Profile Fields that needs to be displayed in Registration From
	 *
	 * @param array $userinfo User information as fields' current value
	 * @return array Profile fields
	 */
	public function fetchProfileFieldsForRegistration($userinfo = array())
	{
		$profilefields = vB::getDbAssertor()->getRows('user_fetchprofilefieldsforregistration', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
		));

		$this->_processProfileFields($profilefields, $userinfo);

		return $profilefields;
	}

	/**
	 * Process Profile Fields for templates
	 *
	 * @param array $profilefields (ref) Profile fields (database records array) to be processed.
	 * @param array $currentvalues Current values of the profile fields
	 * @return void
	 */
	protected function _processProfileFields(&$profilefields, $currentvalues)
	{

		$phraseapi = vB_Api::instanceInternal('phrase');

		$customfields_other = array();
		$customfields_profile = array();
		$customfields_option = array();

		foreach ($profilefields as $field)
		{
			$field['fieldname'] = "field$field[profilefieldid]";
			$field['optionalname'] = $field['fieldname'] . '_opt';

			$titleanddescription = $phraseapi->fetch(array($field['fieldname'] . '_title', $field['fieldname'] . '_desc'));

			$field['title'] = $titleanddescription[$field['fieldname'] . '_title'];
			$field['description'] = $titleanddescription[$field['fieldname'] . '_desc'];

			$field['foundfield'] = 0;

			$field['currentvalue'] = '';
			if ($currentvalues)
			{
				$field['currentvalue'] = $currentvalues['userfield'][$field['fieldname']];
			}

			if ($field['type'] == 'select')
			{
				$field['data'] = unserialize($field['data']);

				$field['bits'] = array();
				foreach ($field['data'] as $key => $val)
				{
					$key++;
					$field['bits'][$key]['val'] = $val;
					$field['bits'][$key]['selected'] = false;
					if (isset($field['currentvalue']))
					{
						if (trim($val) == $field['currentvalue'])
						{
							$field['bits'][$key]['selected'] = true;
							$field['foundfield'] = 1;
						}
					}
					else if ($field['def'] AND $key == 1)
					{
						$field['bits'][$key]['selected'] = true;
						$field['foundfield'] = 1;
					}
				}

				// No empty option
				if (!$field['foundfield'])
				{
					$field['selected'] = true;
				}
				else
				{
					$field['selected'] = false;
				}

			}
			elseif ($field['type'] == 'select_multiple')
			{
				$field['data'] = unserialize($field['data']);
				if ($field['height'] == 0)
				{
					$field['height'] = count($field['data']);
				}

				$field['bits'] = array();
				foreach ($field['data'] as $key => $val)
				{
					$key++;
					$field['bits'][$key]['val'] = $val;
					$field['bits'][$key]['selected'] = false;
					if ($field['currentvalue'] & pow(2, $key - 1))
					{
						$field['bits'][$key]['selected'] = true;
					}
					else
					{
						$field['bits'][$key]['selected'] = false;
					}
				}
			}
			elseif ($field['type'] == 'checkbox')
			{
				$field['data'] = unserialize($field['data']);

				$field['bits'] = array();
				foreach ($field['data'] as $key => $val)
				{
					$key++;
					$field['bits'][$key]['val'] = $val;
					$field['bits'][$key]['selected'] = false;
					if ($field['currentvalue'] & pow(2, $key - 1))
					{
						$field['bits'][$key]['selected'] = true;
					}
					else
					{
						$field['bits'][$key]['selected'] = false;
					}
				}
			}
			elseif ($field['type'] == 'radio')
			{
				$field['data'] = unserialize($field['data']);

				$field['bits'] = array();
				foreach ($field['data'] as $key => $val)
				{
					$key++;
					$field['bits'][$key]['val'] = $val;
					$field['bits'][$key]['checked'] = false;
					if (!$field['currentvalue'] AND $key == 1 AND $field['def'] == 1)
					{
						$field['bits'][$key]['checked'] = true;
					}
					else if (trim($val) == $field['currentvalue'])
					{
						$field['bits'][$key]['checked'] = 'checked="checked"';
						$field['foundfield'] = 1;
					}
				}
			}


			if ($field['required'] == 2)
			{
				// not required to be filled in but still show
				$customfields_other[] = $field;
			}
			else // required to be filled in
			{
				if ($field['form'])
				{
					$customfields_option[] = $field;
				}
				else
				{
					$customfields_profile[] = $field;
				}
			}

		}

		$profilefields = array(
			'other' => $customfields_other,
			'option' => $customfields_option,
			'profile' => $customfields_profile,
		);
	}

	/**
	 * Delete a user
	 *
	 * @param integer $userid The ID of user to be deleted
	 * @param bool $transfer_groups Whether to transfer the (social)Groups that owned by the user to current logged-in admininstrator
	 */
	public function delete($userid, $transfer_groups = true)
	{
		$vbulletin = vB::get_registry();

		$this->checkHasAdminPermission('canadminusers');
		require_once(DIR . '/includes/adminfunctions.php');

		// check user is not set in the $undeletable users string
		if (is_unalterable_user($userid))
		{
			throw new vB_Exception_Api('user_is_protected_from_alteration_by_undeletableusers_var');
		}
		else
		{
			$info = vB_User::fetchUserInfo($userid);
			if (!$info)
			{
				throw new vB_Exception_Api('invalid_user_specified');
			}

 			//todo handle blogs transfer here.
			if ($transfer_groups)
			{
				$groupidsforuser = array();
				$assertor = vB::getDbAssertor();
				$adminuserid = vB::getCurrentSession()->fetch_userinfo_value('userid'); // Admin userid

				$groups = vB_Api::instanceInternal('socialgroup')->getSGInfo(array('userid' => $userid));
				foreach ($groups['results'] as $groupnodeid => $group)
				{
					$groupidsforuser[] = $groupnodeid;
				}

				//let's get the current channels in which the user already is set for that group.
				//Then remove any for which they already are set.
				$usergroupInfo = vB_Api::instanceInternal('usergroup')->fetchUsergroupBySystemID(vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID);

				foreach ($groupidsforuser as $groupnodeid)
				{
					//There is only one owner at a time, so we delete the current user;
					$result = $assertor->assertQuery('vBForum:groupintopic', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
						'nodeid' => $groupnodeid, 'groupid' => $usergroupInfo['usergroupid']));

					//and do the inserts
					$result = $assertor->assertQuery('vBForum:groupintopic', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
						'userid' => $adminuserid, 'nodeid' => $groupnodeid, 'groupid' => $usergroupInfo['usergroupid']));

					//replace the old owner in the node table as well
					$assertor->update('vBForum:node', array('userid' => $adminuserid), array('nodeid' => $groupnodeid));
				}

				// Update admin user's cache
				$myUserContext = vB::getUserContext();
				vB_Cache::instance()->event("userPerms_$adminuserid" );
				$myUserContext->clearChannelPermissions();
			}

			$userdm = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);
			$userdm->set_existing($info);
			$userdm->delete();

			if ($userdm->has_errors(false)) {
				throw $userdm->get_exception();
			}

			vB_Cache::instance(vB_Cache::CACHE_FAST)->event('userChg_' . $userid);
			vB_Cache::instance(vB_Cache::CACHE_LARGE)->event('userChg_' . $userid);

			return true;
		}
	}

	/* Shortcut to saving only email and password if user only has permission to modify password and email
	 *
	 * @param array $extra Generic flags or data to affect processing.
	 *
	 * @return integer New or updated userid.
	 */
	protected function saveEmailPassword($extra)
	{
		if (!vB::getUserContext()->fetchUserId())
		{
			throw new vB_Exception_Api('no_permission');
		}

		$userdata = new vB_Datamanager_User(vB::get_registry(), vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);
		$userinfo = vB_User::fetchUserInfo(vB::getUserContext()->fetchUserId());
		$userdata->set_existing($userinfo);
		$user = array();

		if ($extra['email'] OR $extra['newpass'])
		{
			if (!$extra['password'])
			{
				throw new vB_Exception_Api('enter_current_password');
			}
			else
			{
				$password = $extra['newpass'];
				$user['email'] = $extra['email'];
				$user['password'] = $extra['password'];
			}
		}

		// Password & email
		if (!empty($password) OR !empty($user['email']))
		{
			if (!$user['password'])
			{
				throw new vB_Exception_Api('enter_current_password');
			}

			$auth = vB_User::verifyUserPass($userinfo, $user['password'], '', '');

			if (!$auth)
			{
				throw new vB_Exception_Api('badpassword', vB5_Route::buildUrl('lostpw|nosession|fullurl'));
			}

			$save = false;
			if (!empty($password))
			{
				$userdata->set('password', $password);
				$userdata->set('passworddate', date('Y-m-d', vB::getRequest()->getTimeNow()));
				$save = true;
			}

			if (!empty($user['email']))
			{
				$userdata->set('email', $user['email']);
				$save = true;
			}

			if ($save)
			{
				$userdata->save();

				if ($userdata->has_errors(false))
				{
					throw $userdata->get_exception();
				}
				vB_Cache::instance(vB_Cache::CACHE_FAST)->event('userChg_' . vB::getUserContext()->fetchUserId());
				vB_Cache::instance(vB_Cache::CACHE_LARGE)->event('userChg_' . vB::getUserContext()->fetchUserId());
			}
		}

		return vB::getUserContext()->fetchUserId();
	}

	/**
	 * Insert or Update an user
	 *
	 * @param integer $userid Userid to be updated. Set to 0 if you want to insert a new user.
	 * @param string $password Password for the user. Empty means no change.
	 * @param array $user Basic user information such as email or home page
	 * @param array $options vB options for the user
	 * @param array $adminoptions Admin Override Options for the user
	 * @param array $userfield User's User Profile Field data
	 * @param array $notificationOptions
	 * @param array $hvinput Human Verify input data. @see vB_Api_Hv::verifyToken()
	 * @param array $extra Generic flags or data to affect processing.
	 * @return integer New or updated userid.
	 */
	public function save($userid, $password, $user, $options, $adminoptions, $userfield, $notificationOptions = array(), $hvinput = array(), $extra = array())
	{
		$vboptions = vB::getDatastore()->getValue('options');
		$userContext = vB::getUserContext();
		$currentUserId = $userContext->fetchUserId();
		$userid = intval($userid);
		$coppauser = false;
		if (!$userid)
		{
			$newuser = true;
		}
		else
		{
			$newuser = false;
		}

		if (isset($user['usertitle']) AND (vB_String::vbStrlen($user['usertitle']) > $vboptions['ctMaxChars']) AND !$userContext->isAdministrator())
		{
			throw new vB_Exception_Api('please_enter_user_title_with_at_least_x_characters', $vboptions['ctMaxChars']);
		}

		// We shouldn't set $userid = $currentUserId if $userid == 0 here
		// Cause we may need to allow logged-in user to register again
		if ($userid < 0 AND $currentUserId)
		{
			$userid = $currentUserId;
		}

		// Check if current user canadminusers
		try
		{
			$this->checkHasAdminPermission('canadminusers');
		}
		catch (Exception $e)
		{
			// No. Then we need to do something here.
			if ($userid AND $currentUserId != $userid)
			{
				// If current user isn't the same as passed $userid
				// And $userid != 0 (attempt to update other's profile)
				throw new vB_Exception_Api('no_permission');
			}
			else if ($userid AND $userid == $currentUserId)
			{
				if (!vB::getUserContext()->hasPermission('genericpermissions', 'canmodifyprofile'))
				{
					// User can only update email and password
					return $this->saveEmailPassword($extra);
				}

				if (isset($user['privacy_options']) AND !vB::getUserContext()->hasPermission('usercsspermissions', 'caneditprivacy'))
				{
					// User doesn't have permission to update privacy
					throw new vB_Exception_Api('no_permission');
				}

				if (isset($options['invisible']) AND !empty($options['invisible']) AND !vB::getUserContext()->hasPermission('genericpermissions', 'caninvisible'))
				{
					// User doesn't have permission to go invisible
					throw new vB_Exception_Api('no_permission');
				}
			}
			else if (!$userid)
			{
				// Check if registration is allowed
				if (!$vboptions['allowregistration'])
				{
					throw new vB_Exception_Api('noregister');
				}

				// Check Multiple Registrations Per User
				if ($currentUserId AND !$vboptions['allowmultiregs'])
				{
					$currentUser = vB::getCurrentSession()->fetch_userinfo();
					throw new vB_Exception_Api('signing_up_but_currently_logged_in_msg', array($currentUser['username'], $_SERVER['x-vb-presentation-base'] . '/auth/logout?logouthash=' . $currentUser['logouthash']));
				}

				// If it's a new registration, we need to verify the HV
				vB_Api::instanceInternal('hv')->verifyToken($hvinput, 'register');
			}
		}

		require_once(DIR . '/includes/adminfunctions.php');
		$vbulletin = vB::get_registry();
		$bf_misc_useroptions = vB::getDatastore()->get_value('bf_misc_useroptions');
		$bf_misc_adminoptions = vB::getDatastore()->get_value('bf_misc_adminoptions');
		$bf_misc_notificationoptions = vB::getDatastore()->get_value('bf_misc_usernotificationoptions');
		$usergroupcache = vB::getDatastore()->get_value('usergroupcache');

		if (is_unalterable_user($userid) AND $userid != $currentUserId)
		{
			throw new vB_Exception_Api('user_is_protected_from_alteration_by_undeletableusers_var');
		}

		if ($userid != 0)
		{
			// Get old user information
			$olduser = vB::getDbAssertor()->getRow('user_fetchforupdating', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'userid'              => $userid,
			));

			if (!$olduser)
			{
				throw new vB_Exception_Api('invalid_user_specified');
			}
		}
		else
		{
			$olduser = array();
		}

		// if birthday is required
		if ($vboptions['reqbirthday']
			AND (!isset($olduser['birthday']) OR empty($olduser['birthday']))
			AND (!isset($user['birthday']) OR empty($user['birthday'])))
		{
			throw new vB_Exception_Api('birthdayfield');
		}

		$user['ipaddress'] = vB::getRequest()->getIpAddress();

		$olduser = array_merge($olduser, convert_bits_to_array($olduser['options'], $bf_misc_useroptions));
		$olduser = array_merge($olduser, convert_bits_to_array($olduser['adminoptions'], $bf_misc_adminoptions));
		$olduser = array_merge($olduser, convert_bits_to_array($olduser['notification_options'], $bf_misc_notificationoptions));

		// get threaded mode options
		if (isset($olduser['threadedmode']) AND ($olduser['threadedmode'] == 1 OR $olduser['threadedmode'] == 2))
		{
			$threaddisplaymode = $olduser['threadedmode'];
		}
		else
		{
			if (isset($olduser['postorder']) AND $olduser['postorder'] == 0)
			{
				$threaddisplaymode = 0;
			}
			else
			{
				$threaddisplaymode = 3;
			}
		}
		$olduser['threadedmode'] = $threaddisplaymode;
		// init data manager
		$userdata = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);
		//handle ignorelist
		if (isset($user['ignorelist']))
		{
			$user['ignorelist'] = $this->updateIgnorelist($userid, explode(',', $user['ignorelist']));
		}
		else
		{
			$user['ignorelist'] = array();
		}

		// Let's handle this at API level, ignore list is causing problems in the data manager

		/* If this was called from the account settings or registration pages
		(not the Admin Control Panel) then we shouldn't be setting admin override. */
		if (!(isset($extra['acnt_settings']) OR isset($extra['registration'])))
		{
			$userdata->adminoverride = true;
		}

		$updateUGPCache = false;
		// set existing info if this is an update
		if ($userid)
		{
			$userinfo = vB_User::fetchUserInfo($userid);

			// birthday
			if ($user['birthday'] AND $olduser['birthday'] AND ($user['birthday'] != $olduser['birthday']) AND $vboptions['reqbirthday'])
			{
				throw new vB_Exception_Api('has_no_permission_change_birthday');
			}

			// update buddy list
			$user['buddylist'] = array();
			foreach(explode(' ', $userinfo['buddylist']) as $buddy)
			{
				if (in_array($buddy, $user['ignorelist']) === false)
				{
					$user['buddylist'][] = $buddy;
				}
			}

			$userinfo['posts'] = intval($user['posts']);

			// update usergroups cache if needed...
			$uInfoMUgpIds = explode(',', trim($userinfo['membergroupids']));
			$uInfoUgpId = trim($userinfo['usergroupid']);
			$uIGpIds =  explode(',', trim($userinfo['infractiongroupids']));

			$mUgpIds = isset($user['membergroupids']) ? $user['membergroupids'] : false;
			$ugpId = isset($user['usergroupid']) ? trim($user['usergroupid']) : false;
			$iGpIds = isset($user['infractiongroupids']) ? explode(',', trim($user['infractiongroupids'])) : false;

			if (($ugpId AND ($uInfoUgpId != $ugpId)) OR ($mUgpIds AND array_diff($uInfoMUgpIds, $mUgpIds)) OR ($iGpIds AND array_diff($iGpIds, $uIGpIds)))
			{
				$updateUGPCache = true;
			}

			$userdata->set_existing($userinfo);
		}
		else if ($this->useCoppa())
		{
			if (empty($user['birthday'])) {
				throw new vB_Exception_Api('under_thirteen_registration_denied');
			}

			if ($this->needsCoppa($user['birthday']))
			{
				if ($vboptions['usecoppa'] == 2)
				{
					throw new vB_Exception_Api('under_thirteen_registration_denied');
				}
				else
				{
					if (empty($user['parentemail']))
					{
						throw new vB_Exception_Api('coppa_rules_description');
					}
					$userdata->set_info('coppauser', true);
					$userdata->set_info('coppapassword', $password);
					$options['coppauser'] = 1;
					$coppauser = true;
				}
			}
			//determine the usergroup.
			else if ($vboptions['moderatenewmembers'])
			{
				$userdata->set_info('usergroupid', 4);
			}
			else if ($vboptions['verifyemail'])
			{
				$userdata->set_info('usergroupid', 3);
			}
			else
			{
				$userdata->set_info('usergroupid', 2);
			}
		}

		// user options
		foreach ($bf_misc_useroptions as $key => $val)
		{
			if (isset($options["$key"]))
			{
				$userdata->set_bitfield('options', $key, $options["$key"]);
			}
			else if (isset($olduser["$key"]))
			{
				$userdata->set_bitfield('options', $key, $olduser["$key"]);
			}
		}

		foreach($adminoptions AS $key => $val)
		{
			$userdata->set_bitfield('adminoptions', $key, $val);
		}

		// notification options
		foreach ($notificationOptions as $key => $val)
		{
			// @TODO related to VBV-92
			if ($olduser["$key"] != $val)
			{
				$userdata->set_bitfield('notification_options', $key, $val);
			}
			else if($olduser["$key"] == $val)
			{
				$userdata->set_bitfield('notification_options', $key, $olduser["$key"]);
			}
		}

		$displaygroupid = (array_key_exists('displaygroupid', $user) AND intval($user['displaygroupid'])) ? $user['displaygroupid'] : '';
		if (isset($user['usergroupid']) AND $user['usergroupid'])
		{
			$displaygroupid = $user['usergroupid'];
		}
		elseif (isset($olduser['usergroupid']) AND $olduser['usergroupid'])
		{
			$displaygroupid = $olduser['usergroupid'];
		}

		// custom user title
		if (isset($user['usertitle']) AND $user['usertitle'])
		{
			$userdata->set_usertitle($user['usertitle'],
				$user['customtitle'] ? false : true,
				$usergroupcache["$displaygroupid"],
				$userContext->hasPermission('genericpermissions', 'canusecustomtitle'),
				$userContext->isAdministrator()
			);

			unset($user['usertitle'], $user['customtitle']);
		}
		else if (isset($user['usertitle']) AND empty($user['usertitle']) AND empty($user['customtitle']))
		{
			$userdata->set_usertitle('',
				true,
				$usergroupcache["$displaygroupid"],
				$userContext->hasPermission('genericpermissions', 'canusecustomtitle'),
				$userContext->isAdministrator()
			);

			unset($user['usertitle'], $user['customtitle']);
		}

		// privacy_options
		$privacyChanged = false;
		if (isset($user['privacy_options']) AND $user['privacy_options'])
		{
			foreach ($user['privacy_options'] AS $opt => $val)
			{
				if (!in_array($opt, $this->privacyOptions))
				{
					unset($user['privacy_options'][$opt]);
				}
			}

			// check if we need to update cached values...
			if ($olduser['privacy_options'])
			{
				$check = unserialize($olduser['privacy_options']);
				$diff = array_diff_assoc($user['privacy_options'], $check);
				if (!empty($diff))
				{
					$privacyChanged = true;
				}
			}

			$user['privacy_options'] = serialize($user['privacy_options']);
		}

		// Update from user fields
		foreach ($user AS $key => $val)
		{
			if (!$userid OR $olduser["$key"] != $val)
			{
				$userdata->set($key, $val);
			}
		}

		$user_settings = false;
		if (isset($extra['acnt_settings']))
		{
			if ($extra['email'] OR $extra['newpass'])
			{
				if (!$extra['password'])
				{
					throw new vB_Exception_Api('enter_current_password');
				}
				else
				{
					$user_settings = true;
					$password = $extra['newpass'];
					$user['email'] = $extra['email'];
					$user['password'] = $extra['password'];
				}
			}
		}

		// Password & email
		if (!empty($password) OR !empty($user['email']))
		{
			// If user is not admin, or change via user account settings
			if (!vB::getUserContext()->hasAdminPermission('canadminusers') OR $user_settings)
			{
				//If this is updating a user, we need to validate their old password
				if (!empty($userid))
				{
					if (!$user['password'])
					{
						throw new vB_Exception_Api('enter_current_password');
					}

					$auth = vB_User::verifyUserPass($userinfo, $user['password'], '', '');

					if (!$auth)
					{
						throw new vB_Exception_Api('badpassword', vB5_Route::buildUrl('lostpw|nosession|fullurl'));
					}
				}
			}

			if (!empty($password))
			{
				$userdata->set('password', $password);
				$userdata->set('passworddate', date('Y-m-d',vB::getRequest()->getTimeNow()));
			}

			if ($user_settings AND !empty($user['email']))
			{
				$userdata->set('email', $user['email']);
			}
		}
		else if (!$userid AND empty($password))
		{
			throw new vB_Exception_Api('invalid_password_specified');
		}

		if (isset($user['membergroupids']) AND is_array(($user['membergroupids'])))
		{
			$userdata->set('membergroupids', $user['membergroupids']);
		}

		// custom profile fields
		if (!empty($userfield) AND is_array($userfield))
		{
			$userdata->set_userfields($userfield, true, 'admin');
		}

		// handles ignorelist and buddylist correctly
		$userdata->set('ignorelist', $user['ignorelist']);
		$userdata->set('buddylist', isset($user['buddylist']) ? $user['buddylist'] : array());

		// timezone
		if($newuser)
		{
			$userdata->set('timezoneoffset', $vboptions['timeoffset']);
		}

		// save data
		$newuserid = $userdata->save();

		if ($userdata->has_errors(false))
		{
			throw $userdata->get_exception();
		}

		//a bit of a hack.  If the DM save function runs an update of an existing user then
		//it returns true rather than the userid (despite what the comments say). However its
		//not clear how to handle that in the DM (which looks like it could be use to alter
		//multiple users wholesale, in which case we really don't have an ID.  Better to catch it here.
		if ($newuserid === true)
		{
			$newuserid = $userid;
		}

		if ($updateUGPCache)
		{
			vB_Cache::instance(vB_Cache::CACHE_FAST)->event('perms_changed');
		}

		if ($privacyChanged)
		{
			vB_Cache::instance()->event('userPrivacyChg_' . $userid);
		}

		vB_Cache::instance(vB_Cache::CACHE_FAST)->event('userChg_' . $newuserid);
		vB_Cache::instance(vB_Cache::CACHE_LARGE)->event('userChg_' . $newuserid);

		// Check if we need to send out activate email
		$verifyEmail = (defined('VB_AREA') AND VB_AREA == 'AdminCP') ? false : true;
		if ($newuser AND $vboptions['verifyemail'] AND $verifyEmail)
		{
			$this->library->sendActivateEmail($newuserid);
		}

		// Check if we need to send out welcome email
		if ($newuser AND $userdata->fetch_field('usergroupid') == 2 AND $vboptions['welcomemail'])
		{
			// Send welcome mail
			$username = trim(unhtmlspecialchars($user['username']));
			$maildata = vB_Api::instanceInternal('phrase')->fetchEmailPhrases('welcomemail', array($username, $vboptions['bbtitle']), array($vboptions['bbtitle']), isset($user['languageid']) ? $user['languageid'] : 0);
			vB_Mail::vbmail($user['email'], $maildata['subject'], $maildata['message'], true);
		}

		return $newuserid;
	}

	public function sendActivateEmail($email)
	{
		$userinfo = $this->fetchByEmail($email);
		$vboptions = vB::getDatastore()->getValue('options');

		if (empty($userinfo))
		{
			throw new vB_Exception_Api('invalidemail', array('mailto:' . $vboptions['webmasteremail']));
		}

		$this->library->sendActivateEmail($userinfo['userid']);
	}

	/**
	 * Activate an user with an activate ID and Username
	 *
	 * @param string $username Username
	 * @param string $activateid Activate ID
	 *
	 * @throws vB_Exception_Api
	 * @return string User status after activation. Possible values:
	 *         1) moderateuser: user is put into moderate queue
	 *         2) emailchanged: user's email address has been updated successfully
	 *         3) registration_complete: user's registration is completed
	 */

	public function activateUserByUsername($username, $activateid)
	{
		$userinfo = $this->fetchByUsername($username);

		if (!$userinfo)
		{
			throw new vB_Exception_Api('invalid_username');
		}

		return $this->activateUser($userinfo['userid'], $activateid);
	}

	/**
	 * Activate an user with an activate ID
	 *
	 * @param int $userid User ID
	 * @param string $activateid Activate ID
	 *
	 * @throws vB_Exception_Api
	 * @return string User status after activation. Possible values:
	 *		 1) moderateuser: user is put into moderate queue
	 *		 2) emailchanged: user's email address has been updated successfully
	 *		 3) registration_complete: user's registration is completed
	 */
	public function activateUser($userid, $activateid)
	{
		$dbassertor = vB::getDbAssertor();
		$userinfo = vB_User::fetchUserinfo($userid);
		$usercontext = vB::getUserContext($userid);
		$userid = intval($userid);
		$usergroupcache = vB::getDatastore()->getValue('usergroupcache');
		$vboptions = vB::getDatastore()->getValue('options');

		if (!$userinfo)
		{
			throw new vB_Exception_Api('invalidid');
		}

		if ($userid == 0)
		{
			throw new vB_Exception_Api('invalidactivateid', array(
					vB5_Route::buildUrl('activateuser|nosession|fullurl'),
					vB5_Route::buildUrl('activateemail|nosession|fullurl'),
					vB5_Route::buildUrl('contact-us|nosession|fullurl')
			));
		}
		else if ($userinfo['usergroupid'] == 3)
		{
			// check valid activation id
			$user = $dbassertor->getRow('useractivation', array(
				'activationid' => $activateid,
				'userid' => $userid,
				'type' => 0
			));

			if (!$user OR $activateid != $user['activationid'])
			{
				// send email again
				throw new vB_Exception_Api('invalidactivateid', array(
						vB5_Route::buildUrl('activateuser|nosession|fullurl'),
						vB5_Route::buildUrl('activateemail|nosession|fullurl'),
						vB5_Route::buildUrl('contact-us|nosession|fullurl')
				));
			}

			// delete activationid
			$dbassertor->delete('useractivation', array('userid' => $userid, 'type' => 0));


			if (empty($user['usergroupid']))
			{
				$user['usergroupid'] = 2; // sanity check
			}

			// ### DO THE UG/TITLE UPDATE ###

			$getusergroupid = ($userinfo['displaygroupid'] != $userinfo['usergroupid']) ? $userinfo['displaygroupid'] : $user['usergroupid'];

			$user_usergroup =& $usergroupcache["$user[usergroupid]"];
			$display_usergroup =& $usergroupcache["$getusergroupid"];

			// init user data manager
			$userdata = new vB_Datamanager_User(null, vB_DataManager_Constants::ERRTYPE_STANDARD);
			$userdata->set_existing($userinfo);
			$userdata->set('usergroupid', $user['usergroupid']);
			$userdata->set_usertitle(
				!empty($user['customtitle']) ? $user['usertitle'] : '',
				false,
				$display_usergroup,
				($usercontext->hasPermission('genericpermissions', 'canusecustomtitle')) ? true : false,
				($usercontext->isAdministrator()) ? true : false
			);

			if ($userinfo['coppauser'] OR ($vboptions['moderatenewmembers'] AND !$userinfo['posts']))
			{
				// put user in moderated group
				$userdata->save();
				return array('moderateuser', $this->fetchUserName($userid), vB5_Route::buildUrl('home|nosession|fullurl'));;
			}
			else
			{
				// activate account
				$userdata->save();

				// rebuild stats so new user displays on forum home
				require_once(DIR . '/includes/functions_databuild.php');
				build_user_statistics();
				vB_Cache::instance(vB_Cache::CACHE_FAST)->event(array("userPerms_$userid", "userChg_$userid"));
				vB_Cache::instance(vB_Cache::CACHE_LARGE)->event(array("userPerms_$userid", "userChg_$userid"));

				$username = unhtmlspecialchars($userinfo['username']);
				if (!$user['emailchange'])
				{
					if ($vboptions['welcomemail'])
					{
						// Send welcome mail
						$maildata = vB_Api::instanceInternal('phrase')->fetchEmailPhrases('welcomemail', array($username, $vboptions['bbtitle']), array($vboptions['bbtitle']), $user['languageid']);
						vB_Mail::vbmail($userinfo['email'], $maildata['subject'], $maildata['message'], true);
					}

					$userdata->send_welcomepm(null, $userid);
				}

				if ($user['emailchange'])
				{
					return 'emailchanged';
				}
				else
				{
					return array('registration_complete',
						vB_String::htmlSpecialCharsUni($username),
						vB5_Route::buildUrl('profile|nosession|fullurl', $userinfo),
						vB5_Route::buildUrl('settings|nosession|fullurl', array('tab' => 'account')),
						vB5_Route::buildUrl('settings|nosession|fullurl', array('tab' => 'account')),
						vB5_Route::buildUrl('home|nosession|fullurl')
					);
				}
			}
		}
		else
		{
			if ($userinfo['usergroupid'] == 4)
			{
				// In Moderation Queue
				return 'activate_moderation';
				vB_Cache::instance(vB_Cache::CACHE_FAST)->event(array("userPerms_$userid", "userChg_$userid"));
				vB_Cache::instance(vB_Cache::CACHE_LARGE)->event(array("userPerms_$userid", "userChg_$userid"));
			}
			else
			{
				// Already activated
				throw new vB_Exception_Api('activate_wrongusergroup');
			}
		}
	}

	public function deleteActivation($userid, $activateid)
	{
		$userid = intval($userid);

		$dbassertor = vB::getDbAssertor();

		$userinfo = vB_User::fetchUserinfo($userid);
		if (!$userinfo)
		{
			throw new vB_Exception_Api('invalidid');
		}

		if ($userinfo['usergroupid'] == 3)
		{
			// check valid activation id
			$user = $dbassertor->getRow('useractivation', array(
				'activationid' => $activateid,
				'userid' => $userid,
				'type' => 0
			));

			if (!$user OR $activateid != $user['activationid'])
			{
				throw new vB_Exception_Api('invalidactivateid',
						vB5_Route::buildUrl('activateuser|nosession'),
						vB5_Route::buildUrl('activateeemail|nosession'),
						vB5_Route::buildUrl('contact-us|nosession')
				);
			}

			return array('activate_deleterequest', $user['activationid'], $user['userid']);
		}
		else
		{
			throw new vB_Exception_Api('activate_wrongusergroup');
		}
	}

	public function killActivation($userid, $activateid)
	{
		$userid = intval($userid);

		$dbassertor = vB::getDbAssertor();

		$userinfo = vB_User::fetchUserinfo($userid);
		if (!$userinfo)
		{
			throw new vB_Exception_Api('invalidid');
		}

		if ($userinfo['usergroupid'] == 3)
		{
			// check valid activation id
			$user = $dbassertor->getRow('useractivation', array(
				'activationid' => $activateid,
				'userid' => $userid,
				'type' => 0
			));

			if (!$user OR $activateid != $user['activationid'])
			{
				throw new vB_Exception_Api('invalidactivateid',
						vB5_Route::buildUrl('activateuser|nosession'),
						vB5_Route::buildUrl('activateeemail|nosession'),
						vB5_Route::buildUrl('contact-us|nosession')
				);
			}

			$userdata = new vB_Datamanager_User(null, vB_DataManager_Constants::ERRTYPE_SILENT);
			$userdata->set_existing($userinfo);
			$userdata->set_bitfield('options', 'receiveemail', 0);
			$userdata->set_bitfield('options', 'noactivationmails', 1);
			$userdata->save();

			$dbassertor->delete('useractivation', array('useractivationid' => intval($user['useractivationid'])));

			return array('activate_requestdeleted');
		}
		else
		{
			return array('activate_wrongusergroup');
		}
	}

	/**
	 * Verifies and saves a signature for current logged in user. Returns the signature.
	 * @param string $signature
	 * @param array $filedataids
	 * @return string
	 */
	public function saveSignature($signature, $filedataids = array())
	{
		// This code is based on profile.php

		$vbulletin = vB::get_registry();
		$options = vB::getDatastore()->getValue('options');

		// *********************** CHECKS **********************
		// *****************************************************

		$userid = vB::getCurrentSession()->get('userid');
		$userid = intval($userid);

		if ($userid <= 0)
		{
			throw new vB_Exception_Api('no_permission_logged_out');
		}

		$userContext = vB::getUserContext($userid);
		if (
			!$userContext->hasPermission('genericpermissions', 'canusesignature')
				OR
			!$userContext->hasPermission('genericpermissions', 'canmodifyprofile')
		)
		{
			throw new vB_Exception_Api('no_permission_signatures');
		}

		if (!empty($filedataids))
		{
			if (!$userContext->hasPermission('signaturepermissions', 'cansigpic'))
			{
				throw new vB_Exception_Api('no_permission_images');
			}

			// Max number of images in the sig if imgs are allowed.
			if ($maxImages = $userContext->getLimit('sigmaximages'))
			{
				if (count($filedataids) > $maxImages)
				{
					throw new vB_Exception_Api('max_attachments_reached');
				}
			}
		}

		// Count the raw characters in the signature
		if (($maxRawChars = $userContext->getLimit('sigmaxrawchars')) AND vB_String::vbStrlen($signature) > $maxRawChars)
		{
			throw new vB_Exception_Api('sigtoolong_includingbbcode', array($maxRawChars));
		}

		// *****************************************************
		//Convert signature to BBcode
		$bbcodeAPI = vB_Api::instanceInternal('bbcode');
		$signature = $bbcodeAPI->parseWysiwygHtmlToBbcode($signature);
		//removing consecutive spaces
		$signature = preg_replace('# +#', ' ', $signature);
		$hasBbcode = $bbcodeAPI->hasBbcode($signature);
		if ($hasBbcode AND !$userContext->hasPermission('signaturepermissions', 'canbbcode'))
		{
			throw new vB_Exception_Api('bbcode_not_allowed');
		}

		// add # to color tags using hex if it's not there
		$signature = preg_replace('#\[color=(&quot;|"|\'|)([a-f0-9]{6})\\1]#i', '[color=\1#\2\1]', $signature);

		// Turn the text into bb code.
		if ($userContext->hasPermission('signaturepermissions', 'canbbcodelink'))
		{
			// Get the files we need
			require_once(DIR . '/includes/functions_newpost.php');
			$signature = convert_url_to_bbcode($signature);
		}
		// Create the parser with the users sig permissions
		require_once(DIR . '/includes/class_sigparser.php');
		$sig_parser = new vB_SignatureParser($vbulletin, $bbcodeAPI->fetchTagList(), $userid);
		// Parse the signature
		$paresed = $sig_parser->parse($signature);
		if ($error_num = count($sig_parser->errors))
		{
			$e = new vB_Exception_Api();
			foreach ($sig_parser->errors AS $tag => $error_phrase)
			{
				if (is_array($error_phrase))
				{
					$phrase_name = key($error_phrase);
					$params = $error_phrase[$phrase_name];
					$e->add_error($phrase_name, $params);
				}
				else
				{
					$e->add_error($error_phrase, array($tag));
				}
			}

			throw $e;
		}

		unset($sig_parser);

		// Count the characters after stripping in the signature
		if (($maxChars = $userContext->getLimit('sigmaxchars')) AND (vB_String::vbStrlen(vB_String::stripBbcode($signature, false, false, false)) > $maxChars))
		{
			throw new vB_Exception_Api('sigtoolong_excludingbbcode', array($maxChars));
		}

		if (($maxLines = $userContext->getLimit('sigmaxlines')) > 0)
		{
			require_once(DIR . '/includes/class_sigparser_char.php');
			$char_counter = new vB_SignatureParser_CharCount($vbulletin, $bbcodeAPI->fetchTagList(), $userid);
			$line_count_text = $char_counter->parse(trim($signature));

			if ($options['softlinebreakchars'] > 0)
			{
				// implicitly wrap after X characters without a break
				//trim it to get rid of the trailing whitechars that are inserted by the replace
				$line_count_text = trim(preg_replace('#([^\r\n]{' . $options['softlinebreakchars'] . '})#', "\\1\n", $line_count_text));
			}

			// + 1, since 0 linebreaks still means 1 line
			$line_count = substr_count($line_count_text, "\n") + 1;

			if ($line_count > $maxLines)
			{
				throw new vB_Exception_Api('sigtoomanylines', array($maxLines));
			}
		}

		// *****************************************************

		// Censored Words
		$signature = vB_String::fetchCensoredText($signature);

		// init user data manager
		$userinfo = vB_User::fetchUserInfo($userid);
		$userdata = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_STANDARD);
		$userdata->set_existing($userinfo);
		$userdata->set('signature', $signature);

		// Legacy Hook 'profile_updatesignature_complete' Removed //

		// Decresing the refcount for the images that were previously used in the signature
		if (!empty($userinfo['signature']))
		{
			preg_match_all('#\[ATTACH\=CONFIG\]n(\d+)\[/ATTACH\]#si', $userinfo['signature'], $matches);
			if (!empty($matches[1]))
			{
				$attachmentids = implode(", ", $matches[1]);
				vB::getDbAssertor()->assertQuery('decrementFiledataRefcount', array('filedataid' => $attachmentids));
				vB::getDbAssertor()->assertQuery('filedata', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'filedataid', 'value' => $attachmentids, 'operator' =>  vB_dB_Query::OPERATOR_EQ),
						array('field' => 'refcount', 'value' => 0, 'operator' =>  vB_dB_Query::OPERATOR_EQ),
					),
					'publicview' => 0,
				));
			}
		}

		$userdata->save();

		// mark filedata as used
		foreach($filedataids as $filedataid)
		{
			vB::getDbAssertor()->assertQuery('incrementFiledataRefcount', array('filedataid' => $filedataid));
		}
		vB_Cache::instance(vB_Cache::CACHE_STD)->event("userChg_$userid");

		return $bbcodeAPI->parseSignature($userid, $signature);
	}

	/**
	 * DEPRECATED Fetch access masks information for the user
	 *
	 * @param integer $userid User ID
	 * @return array Access array. The key of its items is forum id.
	 */
	public function fetchAccess($userid)
	{
		/*This is now all done with userContext and permissionContext. We'll leave in place for now
		so nothing breaks. */
		return array();
	}

	/**
	 * DEPRECATED Update user's Access Masks (Forum Permissions)
	 *
	 * @param integer $userid User ID to be updated
	 * @param array $accessupdate New access mask information for the user.
	 * @return bool True if update successfully.
	 */
	public function updateAccess($userid, $accessupdate)
	{
		/*This is now all done with userContext and permissionContext */
		return true;
	}

	/**
	 * Fetch a list of users who are awaiting moderate or Coppa
	 *
	 * @return array A list of users that are awaiting moderation
	 */
	public function fetchAwaitingModerate()
	{
		$this->checkHasAdminPermission('canadminusers');
		return vB::getDbAssertor()->getRows('user_fetchmoderate', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));
	}

	/**
	 * Moderate users
	 *
	 * @param array $validate Validate information
	 * @param bool $send_validated Whether to send email to users who have been accepted
	 * @param bool $send_deleted Whether to send email to users who have been deleted
	 * @return bool True if user accounts validated successfully
	 */
	public function moderate($validate, $send_validated, $send_deleted)
	{
		$this->checkHasAdminPermission('canadminusers');

		if (empty($validate)) {
			throw new vB_Exception_Api('please_complete_required_fields');
		}

		$evalemail_validated = array();
		$evalemail_deleted = array();
		$vbulletin = vB::get_registry();
		$vboptions = vB::getDatastore()->get_value('options');
		$usergroupcache = vB::getDatastore()->get_value('usergroupcache');
		$bf_ugp_genericpermissions = vB::getDatastore()->get_value('bf_ugp_genericpermissions');

		require_once(DIR . '/includes/functions_misc.php');

		if ($vboptions['welcomepm']) {
			if ($fromuser = vB_User::fetchUserInfo($vboptions['welcomepm'])) {
				cache_permissions($fromuser, false);
			}
		}
		foreach($validate AS $userid => $status) {
			$userid = intval($userid);
			$user = vB::getDbAssertor()->getRow('user', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'#filters' => array('userid' => $userid)
			));
			if (!$user) {
				// use was likely deleted
				continue;
			}
			$username = unhtmlspecialchars($user['username']);

			$chosenlanguage = iif($user['languageid'] < 1, intval($vboptions['languageid']), intval($user['languageid']));

			if ($status == 1) { // validated
				// init user data manager
				$displaygroupid = ($user['displaygroupid'] > 0 AND $user['displaygroupid'] != $user['usergroupid']) ? $user['displaygroupid'] : 2;

				$userdata = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);
				$userdata->set_existing($user);
				$userdata->set('usergroupid', 2);
				$userdata->set_usertitle($user['customtitle'] ? $user['usertitle'] : '',
					false,
					$usergroupcache["$displaygroupid"],
					($usergroupcache['2']['genericpermissions'] &$bf_ugp_genericpermissions['canusecustomtitle']) ? true : false,
					false
				);
				$userdata->save();
				if ($userdata->has_errors(false)) {
					throw $userdata->get_exception();
				}

				if ($send_validated) {
					if (!isset($evalemail_validated["$user[languageid]"])) {
						// note that we pass the "all languages" flag as true all the time because if the function does
						// caching internally and is not smart enough to check if the language requested the second time
						// was cached on the first pass -- so we make sure that we load and cache all language version
						// in case the second user has a different language from the first
						$forumHome = vB_Library::instance('content_channel')->getForumHomeChannel();
						$evalemail_deleted["$user[languageid]"] = vB_Api::instanceInternal('phrase')
				  			->fetchEmailPhrases('moderation_validated', array(vB5_Route::buildUrl($forumHome['routeid']. '|fullurl|nosession'), $username, $vboptions['bbtitle'], $vboptions['bburl']), array($vboptions['bbtitle']), $chosenlanguage);
					}
					vB_Mail::vbmail($user['email'], $evalemail_deleted["$user[languageid]"]['subject'], $evalemail_deleted["$user[languageid]"]['message'], true);
				}

				if ($vboptions['welcomepm'] AND $fromuser AND !$user['posts'])
				{
					$userdata = new vB_Datamanager_User(null, vB_DataManager_Constants::ERRTYPE_STANDARD);
					$userdata->send_welcomepm(null, $user['userid']);
				}
			}else if ($status == - 1) { // deleted
				if ($send_deleted) {
					if (!isset($evalemail_deleted["$user[languageid]"])) {
						// note that we pass the "all languages" flag as true all the time because if the function does
						// caching internally and is not smart enough to check if the language requested the second time
						// was cached on the first pass -- so we make sure that we load and cache all language version
						// in case the second user has a different language from the first
						$evalemail_deleted["$user[languageid]"] = vB_Api::instanceInternal('phrase')
							->fetchEmailPhrases('moderation_deleted', array($username, $vboptions['bbtitle']), array($vboptions['bbtitle']), $chosenlanguage);
					}
					vB_Mail::vbmail($user['email'], $evalemail_deleted["$user[languageid]"]['subject'], $evalemail_deleted["$user[languageid]"]['message'], true);
				}

				$userdm = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
				$userdm->set_existing($user);
				$userdm->delete();
				unset($userdm);
			} // else, do nothing
		}
		// rebuild stats so new user displays on forum home
		require_once(DIR . '/includes/functions_databuild.php');
		build_user_statistics();

		return true;
	}

	/**
	 * Return a list of users for pruning or moving
	 *
	 * @param integer $usergroupid Usergroup where the users are in. -1 means all usergroups
	 * @param integer $daysprune Has not logged on for x days
	 * @param integer $minposts Posts is less than
	 * @param array $joindate Join Date is Before. It's an array of 'month', 'day' and 'year'.
	 * @param string $order
	 * @return array Users to be pruned or moved
	 */
	public function fetchPruneUsers($usergroupid, $daysprune, $minposts, $joindate, $order)
	{
		$this->checkHasAdminPermission('canadminusers');

		$users = vB::getDbAssertor()->getRows('userFetchPruneUsers', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'usergroupid' => $usergroupid,
			'daysprune' => $daysprune,
			'minposts' => $minposts,
			'joindate' => $joindate,
			'order' => $order,
		));

		return $users;
	}

	/**
	 * Do prune/move users (step 1)
	 *
	 * @param array $userids UserID to be pruned or moved
	 * @param string $dowhat 'delete' or 'move'
	 * @param integer $movegroup Usergroup ID that the users are going to be moved
	 */
	public function prune($userids, $dowhat, $movegroup = 0)
	{
		$this->checkHasAdminPermission('canadminusers');

		if (empty($userids))
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}

		$vboptions = vB::getDatastore()->get_value('options');
		if ($dowhat == 'delete')
		{
			foreach ($userids AS $userid)
			{
				$this->delete($userid);
			}
		}
		else if ($dowhat == 'move')
		{
			$group = vB::getDbAssertor()->getRow('user_fetchusergroup', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'usergroupid' => $movegroup
				)
			);

			if (!$group)
			{
				throw new vB_Exception_Api('invalidid', array('usergroupid'));
			}

			vB::getDbAssertor()->assertQuery('user_updateusergroup', array(
				'usergroupid' => $movegroup,
				'userids' => $userids,
			));
		}
		else
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}

		return true;
	}

	/**
	 * Do prune/move users (step 2). Userids to be updated are stored in adminutil table.
	 *
	 * @param integer $startat Start at index.
	 * @return integer |bool Next startat value. True means all users have been updated.
	 */
	public function pruneUpdateposts($startat)
	{
		$this->checkHasAdminPermission('canadminusers');

		require_once(DIR . '/includes/adminfunctions.php');
		$userids = fetch_adminutil_text('ids');
		if (!$userids) {
			$userids = '0';
		}
		$users = vB::getDbAssertor()->getRows('user_fetch', array(
			'userids' => $userids,
			vB_dB_Query::PARAM_LIMITSTART => intval($startat),
		));

		if ($users) {
			foreach ($users as $user) {
				vB::getDbAssertor()->assertQuery('user_updatethread', array(
					'username' => $user['username'],
					'userid' => $user['userid'],
				));
				vB::getDbAssertor()->assertQuery('user_updatepost', array(
					'username' => $user['username'],
					'userid' => $user['userid'],
				));
			}

			return ($startat + 50);
		}else {
			vB::getDbAssertor()->assertQuery('user_deleteusertextfield', array('userids' => $userids));
			vB::getDbAssertor()->assertQuery('user_deleteuserfield', array('userids' => $userids));
			vB::getDbAssertor()->assertQuery('user_deleteuser', array('userids' => $userids));

			require_once(DIR . '/includes/functions_databuild.php');
			build_user_statistics();

			return true;
		}
	}

	/**
	 * Return user change history
	 *
	 * @param integer $userid
	 * @return array |bool User change history array. False means no change history.
	 */
	public function changeHistory($userid)
	{
		$this->checkHasAdminPermission('canadminusers');

		$vbulletin = vB::get_registry();
		require_once(DIR . '/includes/class_userchangelog.php');
		require_once(DIR . '/includes/functions_misc.php');
		// initalize the $user storage
		$users = false;
		// create the vb_UserChangeLog instance and set the execute flag (we want to do the query, not just to build)
		$userchangelog = new vb_UserChangeLog($vbulletin);
		$userchangelog->set_execute(true);
		// get the user change list
		$userchange_list = $userchangelog->sql_select_by_userid($userid);

		if (!$userchange_list) {
			return false;
		}
		else
		{
			$usergroupcache = vB::getDatastore()->getValue('usergroupcache');
			// fetch the rows
			foreach ($userchange_list as $userchange) {
				// get/find some names, depend on the field and the content
				switch ($userchange['fieldname']) {
					// get usergroup names from the cache
					case 'usergroupid':
					case 'membergroupids': {
							foreach (array('oldvalue', 'newvalue') as $fname) {
							$str = '';
								if ($ids = explode(',', $userchange[$fname])) {
									foreach ($ids as $id) {
										if ($usergroupcache["$id"]['title']) {
											$str .= ($usergroupcache["$id"]['title']) . '<br/>';
									}
								}
							}
							$userchange["$fname"] = ($str ? $str : '-');
						}
						break;
					}
				}

				$userchanges[] = $userchange;
			}

			return $userchanges;
		}
	}

	/**
	 * Merge two users
	 *
	 * @param integer $sourceuserid
	 * @param integer $destuserid
	 */
	public function merge($sourceuserid, $destuserid)
	{
		$this->checkHasAdminPermission('canadminusers');

		$vbulletin = vB::get_registry();

		$assertor = vB::getDbAssertor();


		if (!$sourceinfo = $assertor->getRow('user_fetchwithtextfield', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'userid' => $sourceuserid
		)))
		{
			throw new vB_Exception_Api('invalid_source_username_specified');
		}

		if (!$destinfo = $assertor->getRow('user_fetchwithtextfield', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'userid' => $destuserid
		)))
		{
			throw new vB_Exception_Api('invalid_destination_username_specified');
		}




	/*
		// Update Subscribed Forums
		$assertor->assertQuery('userInsertSubscribeforum', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));

		$assertor->assertQuery('user_updatesubscribethread', array(
			'userid' => $destinfo['userid'],
		));

		$assertor->assertQuery('userInsertSubscribethread', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));

		require_once(DIR . '/includes/functions_databuild.php');
		update_subscriptions(array('userids' => array($destinfo['userid'])));
	 */
		// Update Subscribed Events
		$assertor->assertQuery('userInsertSubscribeevent', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));
//		// Update subscribed discussions
//		$assertor->assertQuery('user_insertsubscribediscussion', array(
//			'sourceuserid' => $sourceinfo['userid'],
//			'destuserid' => $destinfo['userid'],
//		));
		// Update subscribed groups
//		$assertor->assertQuery('user_insertsubscribegroup', array(
//			'sourceuserid' => $sourceinfo['userid'],
//			'destuserid' => $destinfo['userid'],
//		));

		/*
		REPLACE INTO
		*/
		// Merge relevant data in the user table
		// It is ok to have duplicate ids in the buddy/ignore lists
		$userdm = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
		$userdm->set_existing($destinfo);

		$userdm->set('posts', "posts + $sourceinfo[posts]", false);
		$userdm->set_ladder_usertitle_relative($sourceinfo['posts']);
		$userdm->set('reputation', "reputation + $sourceinfo[reputation] - " . $vbulletin->options['reputationdefault'], false);
		$userdm->set('lastvisit', "IF(lastvisit < $sourceinfo[lastvisit], $sourceinfo[lastvisit], lastvisit)", false);
		$userdm->set('lastactivity', "IF(lastactivity < $sourceinfo[lastactivity], $sourceinfo[lastactivity], lastactivity)", false);
		$userdm->set('lastpost', "IF(lastpost < $sourceinfo[lastpost], $sourceinfo[lastpost], lastpost)", false);
		$userdm->set('pmtotal', "pmtotal + $sourceinfo[pmtotal]", false);
		$userdm->set('pmunread', "pmunread + $sourceinfo[pmunread]", false);
		$userdm->set('gmmoderatedcount', "gmmoderatedcount + $sourceinfo[gmmoderatedcount]", false);

		if ($sourceinfo['joindate'] > 0) {
			// get the older join date, but only if we actually have a date
			$userdm->set('joindate', "IF(joindate > $sourceinfo[joindate], $sourceinfo[joindate], joindate)", false);
		}
		$userdm->set('ipoints', "ipoints + " . intval($sourceinfo['ipoints']), false);
		$userdm->set('warnings', "warnings + " . intval($sourceinfo['warnings']), false);
		$userdm->set('infractions', "infractions + " . intval($sourceinfo['infractions']), false);

		$assertor->assertQuery('user_insertuserlist', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));
		$assertor->assertQuery('user_updateuserlist', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));

		$myfriendcount = $assertor->getField('user_fetchuserlistcount', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'userid' => $destinfo['userid'],
		));

		$userdm->set('friendcount', $myfriendcount);

		$userdm->save();
		unset($userdm);

		require_once(DIR . '/includes/functions_databuild.php');
		build_userlist($destinfo['userid']);
		// if the source user has infractions, then we need to update the infraction groups on the dest
		// easier to do it this way to make sure we get fresh info about the destination user
		if ($sourceinfo['ipoints']) {
			unset($usercache["$destinfo[userid]"]);
			$new_user = vB_User::fetchUserInfo($destinfo['userid']);

			$infractiongroups = array();
			$groups = $assertor->assertQuery('user_fetchinfractiongroup', array());
			foreach ($groups as $group) {
				$infractiongroups["$group[usergroupid]"]["$group[pointlevel]"][] = array(
					'orusergroupid' => $group['orusergroupid'],
					'override'	  => $group['override'],
				);
			}

			$userdm = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
			$userdm->set_existing($new_user);

			require_once(DIR . '/includes/functions_infractions.php');
			$infractioninfo = fetch_infraction_groups($infractiongroups, $new_user['userid'], $new_user['ipoints'], $new_user['usergroupid']);
			$userdm->set('infractiongroupids', $infractioninfo['infractiongroupids']);
			$userdm->set('infractiongroupid', $infractioninfo['infractiongroupid']);
			$userdm->save();
			unset($userdm);
		}
		// Update announcements
		$assertor->assertQuery('user_updateannouncement', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));
		// Update Read Announcements
		$assertor->assertQuery('userInsertAnnouncementread', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));

//		$assertor->assertQuery('user_updateattachment', array(
//			'sourceuserid' => $sourceinfo['userid'],
//			'destuserid' => $destinfo['userid'],
//		));
		// Update Posts
//		$assertor->assertQuery('user_updatepost2', array(
//			'sourceuserid' => $sourceinfo['userid'],
//			'destuserid' => $destinfo['userid'],
//			'destusername' => $destinfo['username'],
//		));
		// Update Threads
//		$assertor->assertQuery('user_updatethread2', array(
//			'sourceuserid' => $sourceinfo['userid'],
//			'destuserid' => $destinfo['userid'],
//			'destusername' => $destinfo['username'],
//		));
		// Update Deletion Log
		$assertor->assertQuery('user_updatedeletionlog', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
			'destusername' => $destinfo['username'],
		));
		// Update Edit Log
		$assertor->assertQuery('user_updateeditlog', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
			'destusername' => $destinfo['username'],
		));
		// Update Edit Log
		$assertor->assertQuery('user_updatepostedithistory', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
			'destusername' => $destinfo['username'],
		));
		// Update Poll Votes - find any poll where we both voted
		// we need to remove the source user's vote
		$pollconflicts = array();
		$polls = $assertor->assertQuery('user_fetchpollvote', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));
		foreach ($polls as $poll) {
			$pollconflicts["$poll[nodeid]"] = $poll;
		}

		$assertor->assertQuery('user_updatepollvote', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));

		if (!empty($pollconflicts)) {
			$assertor->assertQuery('user_deletepollvote', array(
				'userid' => $sourceinfo['userid'],
			));
			// Polls that need to be rebuilt now
			foreach ($pollconflicts AS $pollconflict) {
				vB_Api::instanceInternal('poll')->updatePollCache($pollconflict['nodeid']);

				$pollvotes = $assertor->assertQuery('user_fetchpollvote2', array(
					'nodeid' => $pollconflict['nodeid'],
				));
				$lastvote = 0;
				foreach ($pollvotes as $pollvote) {
					if ($pollvote['votedate'] > $lastvote) {
						$lastvote = $pollvote['votedate'];
					}
				}
				// It appears that pollvote.votedate wasn't always set in the past so we could have votes with no datetime, hence the check on lastvote below
				$assertor->assertQuery('userUpdatePoll', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
					'nodeid' => $pollconflict['nodeid'],
					'lastvote' => $lastvote,
				));
			}
		}
		// Update Thread Ratings
//		$assertor->assertQuery('user_updatethreadrate', array(
//			'sourceuserid' => $sourceinfo['userid'],
//			'destuserid' => $destinfo['userid'],
//		));
		// Update User Notes
		$assertor->assertQuery('user_updateusernote', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));
		$assertor->assertQuery('user_updateusernote2', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));
		// Update Calendar Events
		$assertor->assertQuery('user_updateevent', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));
		// Update Reputation Details
		$assertor->assertQuery('user_updatereputation', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));

		$assertor->assertQuery('user_updatereputation2', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));
		// Update infractions
		$assertor->assertQuery('user_updateinfraction', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));

		$assertor->assertQuery('user_updateinfraction2', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));
		// Update Private Messages
//		$assertor->assertQuery('user_updatepm', array(
//			'sourceuserid' => $sourceinfo['userid'],
//			'destuserid' => $destinfo['userid'],
//		));

//		$assertor->assertQuery('user_updatepm2', array(
//			'sourceuserid' => $sourceinfo['userid'],
//			'destuserid' => $destinfo['userid'],
//		));

//		$assertor->assertQuery('user_updatepmreceipt', array(
//			'sourceuserid' => $sourceinfo['userid'],
//			'destuserid' => $destinfo['userid'],
//		));

//		$assertor->assertQuery('user_updatepmreceipt2', array(
//			'sourceuserid' => $sourceinfo['userid'],
//			'destuserid' => $destinfo['userid'],
//			'destusername' => $destinfo['username'],
//		));

//		$assertor->assertQuery('user_updatepmtext', array(
//			'sourceuserid' => $sourceinfo['userid'],
//			'destuserid' => $destinfo['userid'],
//			'destusername' => $destinfo['username'],
//		));
		// Update Visitor Messages
//		$assertor->assertQuery('user_updatevisitormessage', array(
//			'sourceuserid' => $sourceinfo['userid'],
//			'destuserid' => $destinfo['userid'],
//			'destusername' => $destinfo['username'],
//		));


//		$assertor->assertQuery('user_updatevisitormessage2', array(
//			'sourceuserid' => $sourceinfo['userid'],
//			'destuserid' => $destinfo['userid'],
//		));
		// Update tags
		require_once(DIR . '/includes/class_taggablecontent.php');
		vB_Taggable_Content_Item::merge_users($sourceinfo['userid'], $destinfo['userid']);

		// Clear Group Transfers
//		$assertor->assertQuery('user_updatesocialgroup', array(
//			'userid' => $sourceinfo['userid'],
//		));

		// Delete requests if the dest user already has them
		$assertor->assertQuery('userDeleteUsergrouprequest', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'sourceuserid' => $sourceinfo['userid'],
			'destusergroupid' => $destinfo['usergroupid'],
			'destmembergroupids' => $destinfo['membergroupids'],
		));
		// Convert remaining requests to dest user.
		$assertor->assertQuery('user_updateusergrouprequest', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));

//		$assertor->assertQuery('user_updatepmtext2', array(
//			'sourceuserid' => $sourceinfo['userid'],
//			'sourceusername' => $sourceinfo['username'],
//			'sourceusernamelength' => strlen($sourceinfo['username']),
//			'destuserid' => $destinfo['userid'],
//			'destusername' => $destinfo['username'],
//			'destusernamelength' => strlen($destinfo['username']),
//		));

		/*
		//obsolete social group update.
		$groups = array();
		$groupids = array();

		$memberships_dest = array();
		$memberships_source = array();

		$memberships_combined = array();
		$groups_recount = array();
		$groups_newowner = array();

		$groupmemberships = $assertor->assertQuery('user_fetchsocialgroupmember', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));

		foreach ($groupmemberships as $groupmembership)
		{
			if ($groupmembership['userid'] == $destinfo['userid'])
			{
				$memberships_dest["$groupmembership[groupid]"] = $groupmembership;

			}
			else if ($groupmembership['userid'] == $sourceinfo['userid'])
			{
				$memberships_source["$groupmembership[groupid]"] = $groupmembership;
			}

			$groupids["$groupmembership[groupid]"] = $groupmembership['groupid'];
		}


		if (!empty($groupids))
		{
			$groups_result = $assertor->assertQuery('user_fetchsocialgroup', array('groupids' => $groupids,));

			foreach ($groups_result as $group)
			{
				$groups["$group[groupid]"] = $group;
			}

			foreach ($groups AS $group)
			{
				if ($group['creatoruserid'] == $sourceinfo['userid'])
				{
					$groups_newowner[] = $group['groupid'];
				}

				if (!empty($memberships_source["$group[groupid]"]) AND !empty($memberships_dest["$group[groupid]"]))
				{
						//This may look weird, but it is correct. If both users are in the same group, and their status
						//is NOT the same, then they should be a member, as this means that either one or the other is
						//a member, or one is moderated, and one is invited, combining these makes them a member

					if ($memberships_source["$group[groupid]"]['type'] == $memberships_dest["$group[groupid]"]['type'])
					{
						$memberships_combined[] = $memberships_dest["$group[groupid]"];
						continue;
					}
					else
					{
						$memberships_dest["$group[groupid]"]['type'] = 'member';
						$memberships_combined[] = $memberships_dest["$group[groupid]"];
						continue;
					}
				}
				else if (empty($memberships_source["$group[groupid]"]))
				{
					// Only the destination user is a member of the group
					$memberships_combined[] = $memberships_dest["$group[groupid]"];
					continue;
				}
				else
				{
					// Only the source user is a member of the group
					$memberships_combined[] = $memberships_source["$group[groupid]"];
					continue;
				}
			}

			$memberships_sql = array();

			foreach ($memberships_combined AS $membership)
			{
				$memberships_sql[] = "(" . intval($membership['userid']) . ", " . intval($membership['groupid']) . ", " .
					intval($membership['dateline']) . ", '" . $vbulletin->db->escape_string($membership['type']) . "')";
			}

			$assertor->assertQuery('user_deletesocialgroupmember', array(
				'sourceuserid' => $sourceinfo['userid'],
				'destuserid' => $destinfo['userid'],
			));

			$assertor->assertQuery('user_insertsocialgroupmember', array(
				'memberships_sql' => $memberships_sql,
			));

			if (!empty($groups_newowner)) {
				$assertor->assertQuery('user_updatesocialgroup2', array(
					'userid' => $destinfo['userid'],
					'groups_newowner' => $groups_newowner,
				));
			}

			$invitedgroups = $assertor->getField('user_fetchsocialgroupmembercount', array(
				'userid' => $destinfo['userid'],
			));

			$assertor->assertQuery('user_updatesocgroupinvitecount', array(
				'groups' => $invitedgroups,
				'userid' => $destinfo['userid'],
			));

			$groupdm = datamanager_init("SocialGroup", $vbulletin, vB_DataManager_Constants::ERRTYPE_STANDARD);

			foreach ($groups AS $group) {
				$groupdm->set_existing($group);
				$groupdm->rebuild_membercounts();
				$groupdm->rebuild_picturecount();
				$groupdm->save();

				$pendingcountforowner = $assertor->getField('user_fetchmoderatedmemberssum', array(
					'userid' => $group['creatoruserid'],
				));

				$assertor->assertQuery('user_updatesocgroupinvitecount', array(
					'socgroupreqcount' => $pendingcountforowner,
					'userid' => $group['creatoruserid'],
				));
			}
		}
		*/
//
//		$assertor->assertQuery('user_updatepicturecomment', array(
//			'sourceuserid' => $sourceinfo['userid'],
//			'destuserid' => $destinfo['userid'],
//			'destusername' => $destinfo['username'],
//		));
		// Paid Subscriptions
		$assertor->assertQuery('user_updatepaymentinfo', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));
		// Move subscriptions over
		$assertor->assertQuery('user_updatesubscriptionlog', array(
			'sourceuserid' => $sourceinfo['userid'],
			'destuserid' => $destinfo['userid'],
		));

		$list = $remove = $update = array();
		// Combine active subscriptions
		$subs = $assertor->assertQuery('user_fetchsubscriptionlog', array(
			'userid' => $destinfo['userid'],
		));

		foreach ($subs as $sub)
		{
			$subscriptionid = $sub['subscriptionid'];
			$existing = $list[$subscriptionid];

			if ($existing)
			{
				if ($sub['expirydate'] > $existing['expirydate'])
				{
					$remove[] = $existing['subscriptionlogid'];
					unset($update[$existing['subscriptionlogid']]);
					$list[$subscriptionid] = $sub;
					$update[$sub['subscriptionlogid']] = $sub['expirydate'];
				}
				else
				{
					$remove[] = $sub['subscriptionlogid'];
				}
			}
			else
			{
				$list[$subscriptionid] = $sub;
			}
		}


		if (!empty($remove))
		{
			$assertor->assertQuery('user_deletesubscriptionlog', array(
				'ids' => $remove,
			));
		}


		foreach ($update AS $subscriptionlogid => $expirydate)
		{
			$assertor->assertQuery('user_updatesubscriptionlog2', array(
				'expirydate' => $expirydate,
				'subscriptionlogid' => $subscriptionlogid,
			));
		}
		// Now any CMS content they have written.
//		if ($vbulletin->products['vbcms']) {
//			$assertor->assertQuery('user_updatecmsarticle', array(
//				'sourceusername' => $sourceinfo['username'],
//				'destusername' => $destinfo['username'],
//			));
//			$assertor->assertQuery('user_updatecmsarticle2', array(
//				'sourceuserid' => $sourceinfo['userid'],
//				'destuserid' => $destinfo['userid'],
//			));
//			$assertor->assertQuery('user_updatecmsnode', array(
//				'sourceuserid' => $sourceinfo['userid'],
//				'destuserid' => $destinfo['userid'],
//			));
//			// We only want to move ratings if the target user hasn't voted.
//			$assertor->assertQuery('user_updatecmsrate', array(
//				'sourceuserid' => $sourceinfo['userid'],
//				'destuserid' => $destinfo['userid'],
//			));
//			// any remaining votes are orphans. Delete them
//			$assertor->assertQuery('user_deletecmsrate', array(
//				'userid' => $sourceinfo['userid'],
//			));
//		}
//			'sourceuserid' => $sourceinfo['userid'],
//			'destuserid' => $destinfo['userid'],
//			'destusername' => $destinfo['username'],

		//fix the names on any nodes that the user may be attached to.
		$assertor->assertQuery('vBForum:node',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			vB_dB_Query::CONDITIONS_KEY => array(
				'userid' => $sourceinfo['userid']
			),
				'authorname' => $destinfo['username'],
				'userid'     => $destinfo['userid']
		));

		$assertor->assertQuery('vBForum:node',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			vB_dB_Query::CONDITIONS_KEY => array(
				'lastauthorid' => $sourceinfo['userid']
			),
				'lastcontentauthor' => $destinfo['username'],
				'lastauthorid'     => $destinfo['userid']
		));

		//picture comments are obsolete
		//require_once(DIR . '/includes/functions_picturecomment.php');
		//build_picture_comment_counters($destinfo['userid']);

		// Remove remnants of source user
		$userdm = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
		$userdm->set_existing($sourceinfo);
		$userdm->delete();
		unset($userdm);

		return true;
	}

	/**
	 * Update profile picture
	 * @todo this class looks like it's not being used
	 * @param integer $userid User ID whose profile picture is going to be updated
	 * @param bool $delete Whether to delete profile picture of the user
	 * @param array $data Picture data. It should be an array contains
	 *			  the following items: 'filename', 'width', 'height', 'filedata' or 'tmp_name'
	 */
	public function updateProfilePic($userid, $delete, $data = array())
	{
		// We don't need everything but we need at least one of these
		if (!$delete AND empty($data['filedata']) AND empty($data['tmp_name']) AND empty($data[''])) {
			return false;
		}

		$userContext = vB::getUserContext();
		$currentUserId = $userContext->fetchUserId();
		$userid = intval($userid);

		if ($userid <= 0 AND $currentUserId)
		{
			$userid = $currentUserId;
		}

		// Check if current user canadminusers
		try
		{
			$this->checkHasAdminPermission('canadminusers');
		}
		catch (Exception $e)
		{
			// No. Then we need to do something here.
			if ($currentUserId != $userid)
			{
				// If current user isn't the same as passed $userid
				throw new vB_Exception_Api('no_permission');
			}
		}

		$userinfo = vB_User::fetchUserInfo(intval($userid));
		$vbulletin = vB::get_registry();
		$bf_ugp_genericpermissions = vB::getDatastore()->get_value('bf_ugp_genericpermissions');

		if (!$userinfo)
		{
			throw new vB_Exception_Api('invalid_user_specified');
		}

		if (!$delete)
		{
			$userpic = new vB_DataManager_Userpic($vbulletin, vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);

			cache_permissions($userinfo, false);
			// user's group doesn't have permission to use custom avatars so set override
			if (!($userinfo['permissions']['genericpermissions'] &$bf_ugp_genericpermissions['canprofilepic'])) {
				// init user datamanager
				$userdata = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);
				$userdata->set_existing($userinfo);
				$userdata->set_bitfield('adminoptions', 'adminprofilepic', 1);
				$userdata->save();
				if ($userdata->has_errors(false)) {
					throw $userdata->get_exception();
				}
				unset($userdata);
			}
			$userpic->set('userid', $userinfo['userid']);
			$userpic->set('dateline', vB::getRequest()->getTimeNow());
			$userpic->set('filename', $data['filename']);
			$userpic->set('width', $data['width']);
			$userpic->set('height', $data['height']);
			$userpic->setr('filedata', $data['filedata']);
			$userpic->set_info('avatarrevision', $userinfo['avatarrevision']);
			$userpic->set_info('profilepicrevision', $userinfo['profilepicrevision']);
			$userpic->set_info('sigpicrevision', $userinfo['sigpicrevision']);

			if (!($result = $userpic->save()))
			{
				throw $userpic->get_exception();
			}
		}
		else
		{
			// Delete userpic
			$userpic = new vB_DataManager_Userpic($vbulletin, vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);
			$userpic->condition = array('userid'  => $userinfo['userid']);
			$userpic->delete();

			if ($userpic->has_errors(false))
			{
				throw $userpic->get_exception();
			}
		}

		return true;
	}

	/**
	 * Update avatar
	 *
	 * @param integer $userid User ID whose avatar is going to be updated
	 * @param integer $avatarid Predefined avatar ID. -1 means to remove avatar
	 *				from the user. 0 means use custom avatar defined in $avatardata
	 * @param array $data Avatar data. It should be an array contains
	 *			  the following items: 'filename', 'width', 'height', 'filedata', 'location'
	 */
	public function updateAvatar($userid, $avatarid, $data = array(), $cropped = false)
	{
		$userContext = vB::getUserContext();
		$currentUserId = $userContext->fetchUserId();
		$userid = intval($userid);

		if ($userid <= 0 AND $currentUserId)
		{
			$userid = $currentUserId;
		}

		// Check if current user canadminusers
		try
		{
			$this->checkHasAdminPermission('canadminusers');
		}
		catch (Exception $e)
		{
			// No. Then we need to do something here.
			if ($currentUserId != $userid)
			{
				// If current user isn't the same as passed $userid
				throw new vB_Exception_Api('no_permission');
			}
		}

		$useavatar = iif($avatarid == - 1, 0, 1);
		$vbulletin = vB::get_registry();
		$bf_ugp_genericpermissions = vB::getDatastore()->get_value('bf_ugp_genericpermissions');

		$userinfo = vB_User::fetchUserInfo(intval($userid));
		if (!$userinfo)
		{
			throw new vB_Exception_Api('invalid_user_specified');
		}
		// init user datamanager
		$userdata = new vB_Datamanager_User(vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);
		$userdata->set_existing($userinfo);

		if ($useavatar)
		{
			if (!$avatarid)
			{
				$userpic = new vB_DataManager_Userpic($vbulletin, vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);
				// user's group doesn't have permission to use custom avatars so set override

				if (!$this->userContext->hasPermission('genericpermissions', 'canuseavatar'))
				{
					// init user datamanager
					$userdata->set_bitfield('adminoptions', 'adminavatar', 1);
				}
				$userpic->set('userid', $userinfo['userid']);
				$userpic->set('dateline', vB::getRequest()->getTimeNow());
				$userpic->set('width', $data['width']);
				$userpic->set('height', $data['height']);

				if (empty($data['extension']))
				{
					$filebits = explode('.', $data['filename']);
					$data['extension'] = end($filebits);
				}

				$userpic->set('extension', $data['extension']);

				if (vB::getDatastore()->getOption('usefileavatar'))
				{
					$avatarpath = vB::getDatastore()->getOption('avatarpath');
					$prev_dir = getcwd();
					chdir(DIR);

					$oldavatarfilename = "avatar{$userid}_{$userinfo['avatarrevision']}.{$data['extension']}";
					$avatarrevision = $userinfo['avatarrevision'] + 1;
					$avatarfilename = "avatar{$userid}_{$avatarrevision}.{$data['extension']}";
					@unlink($avatarpath . '/' . $oldavatarfilename);
					@unlink($avatarpath . '/thumbs/' . $oldavatarfilename);

					$avatarres = @fopen("$avatarpath/$avatarfilename", 'wb');
					$userpic->set('filename', $avatarfilename);
					fwrite($avatarres, $data['filedata']);
					@fclose($avatarres);
					if (!empty($data['filedata_thumb']))
					{
						$thumbres = @fopen("$avatarpath/thumbs/$avatarfilename", 'wb');
						fwrite($thumbres, $data['filedata_thumb']);
						@fclose($thumbres);
						$userpic->set('width_thumb', $data['width_thumb']);
						$userpic->set('height_thumb', $data['height_thumb']);
					}
					chdir($prev_dir);
					$userpic->set('filesize', $data['filesize']);
					$userdata->set('avatarrevision', $userinfo['avatarrevision'] + 1);
				}
				else
				{
					$avatarfilename = "avatar{$userid}_{$userinfo['avatarrevision']}.{$data['extension']}";
					$userpic->setr('filedata', $data['filedata']);
					$userpic->set('filename', $avatarfilename);

					$imageHandler = vB_Image::instance();
					if(!$cropped)
					{
						$thumb = $imageHandler->fetchThumbNail($data['name'], $data['location']);
					}
					if(!$cropped)
					{
						$userpic->set('filedata_thumb', $thumb['filedata']);
						$userpic->set('width_thumb', $thumb['width']);
						$userpic->set('height_thumb', $thumb['height']);
					}
					else {
						$userpic->set('filedata_thumb', $data['filedata_thumb']);
						$userpic->set('width_thumb', $data['width_thumb']);
						$userpic->set('height_thumb', $data['height_thumb']);
					}
				}

				$userpic->save();
			}
			else
			{
				// predefined avatar
				$userpic = new vB_DataManager_Userpic_Avatar($vbulletin, vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);
				$userpic->condition = array('userid'  => $userinfo['userid']);
				$userpic->delete();

				if ($userpic->has_errors(false))
				{
					throw $userpic->get_exception();
				}
			}
		}
		else
		{
			// not using an avatar
			$avatarid = 0;
			$userpic = new vB_DataManager_Userpic_Avatar($vbulletin, vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);
			$userpic->condition = array('userid'  => $userinfo['userid']);
			$userpic->delete();

			if ($userpic->has_errors(false))
			{
				throw $userpic->get_exception();
			}
		}

		$userdata->set('avatarid', $avatarid);
		if (!$userdata->save())
		{
			throw $userpic->get_exception();
		}

		unset($this->avatarsCache['avatar'][$userid]);
		unset($this->avatarsCache['thumb'][$userid]);

		return true;
	}

	/**
	 * Update profile picture
	 *
	 * @param integer $userid User ID whose profile picture is going to be updated
	 * @param bool $delete Whether to delete profile picture of the user
	 * @param array $data Picture data. It should be an array contains
	 *			  the following items: 'filename', 'width', 'height', 'filedata'
	 */

	public function updateSigPic($userid, $delete, $data = array())
	{
		$userContext = vB::getUserContext();
		$currentUserId = $userContext->fetchUserId();
		$userid = intval($userid);

		if ($userid <= 0 AND $currentUserId)
		{
			$userid = $currentUserId;
		}

		// Check if current user canadminusers
		try
		{
			$this->checkHasAdminPermission('canadminusers');
		}
		catch (Exception $e)
		{
			// No. Then we need to do something here.
			if ($currentUserId != $userid)
			{
				// If current user isn't the same as passed $userid
				throw new vB_Exception_Api('no_permission');
			}
		}

		$userinfo = vB_User::fetchUserInfo(intval($userid));
		$vbulletin = vB::get_registry();
		$bf_ugp_genericpermissions = vB::getDatastore()->get_value('bf_ugp_genericpermissions');

		if (!$userinfo) {
			throw new vB_Exception_Api('invalid_user_specified');
		}

		if (!$delete AND (empty($data['filedata']) OR empty($data['width']) OR empty($data['height']))) {
			throw new vB_Exception_Api('insufficent_data');
		}

		if (!$delete) {
			$userpic = new vB_DataManager_Userpic_SigPic($vbulletin, vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);

			$userpic->set('userid', $userinfo['userid']);
			$userpic->set('dateline', vB::getRequest()->getTimeNow());
			$userpic->set('filename', $data['filename']);
			$userpic->set('width', $data['width']);
			$userpic->set('height', $data['height']);
			$userpic->setr('filedata', $data['filedata']);
			$userpic->set_info('avatarrevision', $userinfo['avatarrevision']);
			$userpic->set_info('profilepicrevision', $userinfo['profilepicrevision']);
			$userpic->set_info('sigpicrevision', $userinfo['sigpicrevision']);

			$result = $userpic->save();

			if (!empty($result['errors'])) {
				throw new vB_Exception_Database($result['errors']);
			}
		}else {
			// Delete userpic
			$userpic = new vB_DataManager_Userpic_Sigpic($vbulletin, vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);
			$userpic->condition = array('userid'  => $userinfo['userid']);
			$userpic->delete();

			if ($userpic->has_errors(false)) {
				throw $userpic->get_exception();
			}
		}

		return true;
	}


	/**
	 * Search IP Addresses
	 *
	 * @param string $userid An userid. Find IP Addresses for user.
	 * @param integer $depth Depth to Search
	 * @return array 'regip' User's registration IP.
	 *			   'postips' IP addresses which the user has ever used to post
	 *			   'regipusers' Other users who used the same IP address to register
	 */
	public function searchIP($userid, $depth = 1)
	{
		$this->checkHasAdminPermission('canadminusers');

		if (!$depth) {
			$depth = 1;
		}
		$userinfo = vB_User::fetchUserInfo(intval($userid));

		if (!$userinfo) {
			throw new vB_Exception_Api('invalid_user_specified');
		}

		$retdata['regip'] = $userinfo['ipaddress'];
		$retdata['postips'] = $this->_searchUserIP($userid, 0, $depth);

		if ($userinfo['ipaddress']) {
			$retdata['regipusers'] = $this->_searchRegisterIP($userinfo['ipaddress'], $userid, $depth);
		}else {
			$retdata['regipusers'] = array();
		}

		return $retdata;
	}

	/**
	 * Search IP Addresses
	 *
	 * @param string $ipaddress An IP Address. Find Users by IP Address.
	 * @param integer $depth Search depth
	 * @return array 'postipusers' Users who used the IP address to post
	 *			   'regipusers' Users who used the IP address to register
	 */
	public function searchUsersByIP($ipaddress, $depth)
	{
		$this->checkHasAdminPermission('canadminusers');

		if (!$depth) {
			$depth = 1;
		}

		$retdata['postipusers'] = $this->_searchIPUsage($ipaddress, 0, $depth);
		$retdata['regipusers'] = $this->_searchRegisterIP($ipaddress, 0, $depth);

		return $retdata;
	}

	/**
	 * Rewrite function construct_ip_register_table()
	 */
	protected function _searchRegisterIP($ipaddress, $prevuserid, $depth = 1)
	{
		$depth--;

		$users = vB::getDbAssertor()->assertQuery('userSearchRegisterIP', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'ipaddress' => $ipaddress,
			'prevuserid' => $prevuserid,
		));

		$retdata = array();
		foreach ($users as $user) {
			$retdata[$depth][] = $user;

			if ($depth > 0) {
				$retdata += $this->_searchRegisterIP($user['userid'], $user['ipaddress'], $depth);
			}
		}

		return $retdata;
	}

	/**
	 * Rewrite function construct_user_ip_table()
	 */
	protected function _searchUserIP($userid, $previpaddress, $depth = 2)
	{
		$depth--;

		$ips = vB::getDbAssertor()->assertQuery('user_searchpostip', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'ipaddress' => $previpaddress,
			'userid' => $userid,
		));

		$retdata = array();
		foreach ($ips as $ip) {
			$retdata[$depth][] = $ip;

			if ($depth > 0) {
				$retdata += $this->_searchUserIP($ip['ipaddress'], $userid, $depth);
			}
		}

		return $retdata;
	}

	/**
	 * Rewrite function construct_ip_usage_table()
	 */
	protected function _searchIPUsage($ipaddress, $prevuserid, $depth = 1)
	{
		$depth--;

		$users = vB::getDbAssertor()->assertQuery('userSearchIPUsage', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'ipaddress' => $ipaddress,
			'prevuserid' => $prevuserid,
		));

		$retdata = array();
		foreach ($users as $user) {
			$retdata[$depth][] = $user;

			if ($depth > 0) {
				$retdata += $this->_searchIPUsage($user['userid'], $user['ipaddress'], $depth);
			}
		}

		return $retdata;
	}

	/**
	 * Return a report of referrers
	 *
	 * @param array $startdate Start Date of the report. an array of 'year', 'month', 'day', 'hour' and 'minute'
	 * @param array $enddate End Date of the report. an array of 'year', 'month', 'day', 'hour' and 'minute'
	 * @return array Referrers information
	 */
	public function fetchReferrers($startdate, $enddate)
	{
		$users = vB::getDbAssertor()->getRows('userReferrers', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'startdate' => $startdate,
			'enddate' => $enddate,
		));

		return $users;
	}


	/**
	 * Check whether a user is banned.
	 *
	 * @param integer $userid User ID.
	 * @return bool Whether the user is banned.
	 */
	public function isBanned($userid)
	{
		$userid = intval($userid);
		if (!$userid)
		{
			$userid = vB::getCurrentSession()->get('userid');
		}

		//todo -- do we need to restrict this function?
		return $this->library->isBanned($userid);
	}

	/**
	 * Check whether an email address is banned from the forums
	 *
	 * @param string $email The email address to check
	 * @return bool Whether the email is banned.
	 */
	public function isBannedEmail($email)
	{
		$vboptions = vB::getDatastore()->getValue('options');
 		$banemail = vB::getDatastore()->getValue('banemail');

		if ($vboptions['enablebanning'] AND $banemail !== null)
		{
			$bannedemails = preg_split('/\s+/', $banemail, - 1, PREG_SPLIT_NO_EMPTY);

			foreach ($bannedemails AS $bannedemail)
			{
				if (is_valid_email($bannedemail))
				{
					$regex = '^' . preg_quote($bannedemail, '#') . '$';
				}
				else
				{
					$regex = preg_quote($bannedemail, '#') . ($vboptions['aggressiveemailban'] ? '' : '$');
				}

				if (preg_match("#$regex#i", $email))
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Gets the relationship of one user to another.
	 *
	 * The relationship level can be:
	 *
	 * 	3 - User 2 is a Friend of User 1 or is a Moderator
	 *  2 - User 2 is on User 1's contact list
	 *  1 - User 2 is a registered forum member
	 *  0 - User 2 is a guest or ignored user
	 *
	 * @param integer $user1 Id of user 1
	 * @param integer $user2 Id of user 2
	 * @return integer Relationship level
	 */
	public function fetchUserRelationship($user1, $user2)
	{
		static $privacy_cache = array();

		$user1 = intval($user1);
		$user2 = intval($user2);

		if (!$user2)
		{
			return 0;
		}

		if (isset($privacy_cache["$user1-$user2"]))
		{
			return $privacy_cache["$user1-$user2"];
		}

		//todo move this to a user context call and base on channels instead of moderators.
		if ($user1 == $user2 OR can_moderate(0, '', $user2)) {
			$privacy_cache["$user1-$user2"] = 3;
			return 3;
		}

		$contacts = vB::getDbAssertor()->assertQuery('user_fetchcontacts', array(
			'user1' => $user1,
			'user2' => $user2,
		));

		$return_value = 1;
		foreach ($contacts as $contact)
		{
			if ($contact['friend'] == 'yes')
			{
				$return_value = 3;
				break;
			}
			else if ($contact['type'] == 'ignore')
			{
				$return_value = 0;
				break;
			}
			else if ($contact['type'] == 'buddy')
			{
				// no break here, we neeed to make sure there is no other more definitive record
				$return_value = 2;
			}
		}

		$privacy_cache["$user1-$user2"] = $return_value;
		return $return_value;
	}

	/**
	 * Login a user
	 *
	 * @param string $username
	 * @param string $password
	 * @param string $md5password
	 * @param string $md5passwordutf
	 * @param string $logintype
	 */
	public function login($username, $password = null, $md5password = null, $md5passwordutf = null, $logintype = null)
	{
		$username = vB_String::htmlSpecialCharsUni($username);

		// require_once(DIR . '/includes/functions_login.php');
		$vboptions = vB::getDatastore()->getValue('options');

		if (!$username)
		{
			throw new vB_Exception_Api('badlogin', vB5_Route::buildUrl('lostpw'));
		}

		$strikes = vB_User::verifyStrikeStatus($username);
		if ($strikes === false)
		{
			// todo: check for missing args
			throw new vB_Exception_Api('strikes', vB5_Route::buildUrl('lostpw'));
		}

		if ($logintype === 'fbauto')
		{
			$auth = vB_User::verifyFacebookAuthentication();
		}
		else
		{
			$auth = vB_User::verifyAuthentication($username, $password, $md5password, $md5passwordutf);
		}
		if (!$auth)
		{
			// check password
			vB_User::execStrikeUser($username);

			if ($logintype === 'cplogin')
			{
				// log this error if attempting to access the control panel
				require_once(DIR . '/includes/functions_log_error.php');
				log_vbulletin_error($username, 'security');
			}

			// TODO: we need to add missing parameters for 'forgot password'
			if ($vboptions['usestrikesystem'])
			{
				throw new vB_Exception_Api('badlogin_strikes_passthru', array(vB5_Route::buildUrl('lostpw'), $strikes+1));
			}
			else
			{
				throw new vB_Exception_Api('badlogin_passthru', array(vB5_Route::buildUrl('lostpw'), $strikes+1));
			}
		}

		vB_User::execUnstrikeUser($username);
		// create new session
		$res = vB_User::processNewLogin($auth, $logintype);

		return $res;
	}

	/**
	 * Logout user
	 *
	 * @param $logouthash Logout hash
	 * @return bool
	 */
	public function logout($logouthash = null)
	{
		// keeping this just because of datamanager constants
		require_once(DIR . '/includes/functions_login.php');
		// process facebook logout first if applicable

		if (vB_Facebook::isFacebookEnabled())
		{
			vB_Facebook::doFacebookLogout();
		}

		$userinfo = vB::getCurrentSession()->fetch_userinfo();

		if(!defined("VB_API") || VB_API_VERSION_CURRENT >= VB5_API_VERSION_START)
		{
			if ($userinfo['userid'] != 0 AND !vB_User::verifySecurityToken($logouthash, $userinfo['securitytoken_raw']))
			{
				throw new vB_Exception_Api('logout_error');
			}
		}

		return vB_User::processLogout();
	}

	/**
	 * Email user a password reset email
	 *
	 * @param integer $userid User ID
	 * @param string $email Email address
	 * @param array $hvinput Human Verify input data. @see vB_Api_Hv::verifyToken()
	 * @return bool
	 */
	public function emailPassword($userid, $email, $hvinput = array())
	{
		vB_Api::instanceInternal('hv')->verifyToken($hvinput, 'lostpw');

		if (!$email)
		{
			throw new vB_Exception_Api('invalidemail');
		}

		require_once(DIR . '/includes/functions_user.php');
		$users = vB::getDbAssertor()->getRows('user_fetchemailpassword', array('email' => $email));
		$vboptions = vB::getDatastore()->getValue('options');

		if ($users)
		{
			foreach ($users as $user)
			{
				if ($userid AND $userid != $user['userid'])
				{
					continue;
				}
				$user['username'] = unhtmlspecialchars($user['username']);

				$user['activationid'] = build_user_activation_id($user['userid'], 2, 1);

				$maildata = vB_Api::instanceInternal('phrase')
					->fetchEmailPhrases('lostpw', array($user['username'], $vboptions['bbtitle'], $vboptions['bburl'], $_SERVER['x-vb-presentation-base'] . "/auth/lostpw/?action=pwreset&userid={$user['userid']}&activationid={$user['activationid']}"), array($vboptions['bbtitle']), $user['languageid']);
		   		vB_Mail::vbmail($user['email'], $maildata['subject'], $maildata['message'], true);
			}

			return true;
		}
		else
		{
			throw new vB_Exception_Api('invalidemail', array(vB5_Route::buildUrl('contact-us|nosession|fullurl')));
		}
	}

	/**
	 * Reset user's password
	 *
	 * @param integer $userid User ID
	 * @param string $activationid Activation ID
	 */
	public function resetPassword($userid, $activationid)
	{
		$user = array();
		$vbulletin = vB::get_registry();
		$userinfo = vB_User::fetchUserinfo($userid);
		$vboptions = vB::getDatastore()->getValue('options');

		if (isset($userinfo['userid']))
		{
			$user = vB::getDbAssertor()->getRow('user_useractivation', array('userid' => $userinfo['userid']));
		}

		if (!$user)
		{
			throw new vB_Exception_Api('invaliduserid');
		}

 		// Is it older than 24 hours ?
 		if ($user['dateline'] < (vB::getRequest()->getTimeNow() - 86400))
		{
			throw new vB_Exception_Api('resetexpired');
		}

 		// Wrong act id ?
 		if ($user['activationid'] != $activationid)
		{
			throw new vB_Exception_Api('resetbadid');
		}

		// Delete old activation id
		vB::getDbAssertor()->assertQuery('user_deleteactivationid', array('userid' => $userinfo['userid']));

		$newpassword = fetch_random_password(8);

		// init user data manager
		$userdata = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_STANDARD);
		$userdata->set_existing($userinfo);
		$userdata->set('password', $newpassword);
		$userdata->save();

		if ($userdata->has_errors(false))
		{
			throw $userdata->get_exception();
		}

		$maildata = vB_Api::instanceInternal('phrase')
			->fetchEmailPhrases('resetpw', array($userinfo['username'], $newpassword, $vboptions['bburl'], $vboptions['bbtitle']), array($vboptions['bbtitle']), $userinfo['languageid']);
		vB_Mail::vbmail($userinfo['email'], $maildata['subject'], $maildata['message'], true);

		$response = vB_Api::instanceInternal('phrase')->fetch(array('password_reset', 'resetpw_message'));

		return $response;
	}

	/**
	 * * This checks whether a user needs COPPA approval based on birthdate. Responds to Ajax call
	 *
	 * @param mixed $ array of month/day/year.
	 *	@return	int		0 - no COPPA needed, 1- Approve but require adult validation, 2- Deny
	 */
	public function needsCoppa($dateInfo)
	{
		return $this->library->needsCoppa($dateInfo);
	}


	/**
	 * This checks whether the site uses COPPA review
	 *
	 *	@return	bool
	 */
	public function useCoppa()
	{
		$options = vB::getDatastore()->get_value('options');
		return (bool) $options['usecoppa'];
	}

	/**
	 * This checks whether the a username is available and valid
	 *
	 * @param username $
	 * @return	bool
	 */
	public function checkUsername($candidate)
	{
		$cleaner = vB::get_cleaner();
		$candidate = $cleaner->clean($candidate, vB_Cleaner::TYPE_STR);
		$options = vB::getDatastore()->getValue('options');

		if (empty($candidate))
		{
			throw new vB_Exception_Api('invalid_username_specified');
		}

		$usernameLen = vB_String::vbStrlen($candidate);
		if ($usernameLen < $options['minuserlength'])
		{
			throw new vB_Exception_Api('invalid_username_specified_minlength_x', array($options['minuserlength']));
		}

		if ($usernameLen > $options['maxuserlength'])
		{
			throw new vB_Exception_Api('invalid_username_specified_maxlength_x', array($options['maxuserlength']));
		}

		if (!empty($options['usernameregex']))
		{
			// check for regex compliance
			if (!preg_match('#' . str_replace('#', '\#', $options['usernameregex']) . '#siU', $candidate))
			{
				throw new vB_Exception_Api('usernametaken', array(vB_String::htmlSpecialCharsUni($candidate), vB::getCurrentSession()->get('sessionurl')));
			}
		}

		if (!empty($options['illegalusernames']))
		{
			// check for illegal username
			$usernames = preg_split('/[ \r\n\t]+/', $options['illegalusernames'], -1, PREG_SPLIT_NO_EMPTY);
			foreach ($usernames AS $val)
			{
				if (strpos(strtolower($candidate), strtolower($val)) !== false)
				{
					// wierd error to show, but hey...
					throw new vB_Exception_Api('usernametaken', array(vB_String::htmlSpecialCharsUni($candidate), vB::getCurrentSession()->get('sessionurl')));
				}
			}
		}

		$candidate = trim(preg_replace('#[ \r\n\t]+#si', ' ', strip_blank_ascii($candidate, ' ')));
		$check = vB::getDbAssertor()->getRow('user', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'username' => $candidate));

		if (isset($check['errors']))
		{
			throw new vB_Exception_Api($check['errors'][0][0]);
		}
		else if (!empty($check))
		{
			throw new vB_Exception_Api('user_name_x_already_in_use_choose_different_name', array($candidate));
		}

		return true;
	}

	public function currentUserHasAdminPermission($adminPermission)
	{
		$session = vB::getCurrentSession();
		if (!$session->validateCpsession())
		{
			throw new vB_Exception_Api('auth_required');
		}

		$userInfo = $session->fetch_userinfo();
		$currentUserid = (int) $userInfo['userid'];

		if ($currentUserid < 1)
		{
			return false;
		}

		return vB::getUserContext()->hasAdminPermission($adminPermission);
	}

	public function getAutocomplete($searchStr, $orderby = 'username', $direction = 'ASC', $limitstart = 0, $limitnumber = 25)
	{
		$query = vB::getDbAssertor()->assertQuery(
			'user', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'username', 'value' => "$searchStr", 'operator' => vB_dB_Query::OPERATOR_BEGINS)
				),
				array('field' => $orderby, 'direction' => $direction)
			)
		);
		$matching_users = array();

		while ($query AND $query->valid() AND $user = $query->current()) {
			$matching_users[] = array(
				'title' =>	vB_String::unHtmlSpecialChars($user['username']),
				'value' =>	$user['username'],
				'id' =>		$user['userid']
			);
			$query->next();
		}

		return array('suggestions' => $matching_users);
	}

	/**
	* This sets a user to use one of the default avatars.
	*
	* @param int $
	*	@result	mixed	the new avatar info array of custom => bool, 0=> avatarurl (string)
	*/
	public function setDefaultAvatar($avatarid)
	{
		// you can only do this for yourself.
		$userContext = vB::getUserContext();
		$userid = $userContext->fetchUserId();
		// just ignore for not logged in
		if ($userid < 1)
		{
			return;
		}
		// make sure this is a valid id.
		$assertor = vB::getDbAssertor();
		$avatarData = $assertor->getRows('avatar', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'avatarid' => $avatarid));

		if (!$avatarData OR !empty($avatarData['errors']) OR $avatarData[0]['imagecategoryid'] <> 3)
		{
			throw new vB_Exception_Api('invalid_data');
		}

		$result = $this->save($userid, null, array('avatarid' => $avatarid), array(), array(), array(), array());

		if ($result)
		{
			$assertor->assertQuery('customavatar', 	array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'userid' => $userid));
		}

		return $this->fetchAvatar($userid);
	}

	/**
	 *	Convert the search array to the assertor conditions.
	 *
	 *	Refactored from adminfunctions_user.php fetch_user_search_sql
	 */
	protected function fetchUserSearchCondition($user, $profile, $prefix = 'user')
	{
		if (!empty($prefix))
		{
			$prefix .= '.';
		}

		$conditions = array();

		$user['username'] = trim($user['username']);
		if ($user['username'])
		{
			$condition = array('field' => "{$prefix}username", 'value' => vB_String::htmlSpecialCharsUni($user['username']),
				'operator' => vB_dB_Query::OPERATOR_INCLUDES);

			if ($user['exact'])
			{
				$condition['operator'] =  vB_dB_Query::OPERATOR_EQ;
			}

			$conditions[] = $condition;
			unset($condition);
		}

		//handle the case where usergroup is an array or a singleton -- exclude the special value of -1
		$ids = false;
		if (is_array($user['usergroupid']))
		{
			$ids = array_map('intval', $user['usergroupid']);
		}
		else if ($user['usergroupid'] != -1 AND $user['usergroupid'])
		{
			$ids = intval($user['usergroupid']);
		}

		//if we have something, set the condition
		if ($ids)
		{
			$conditions[] = array('field' => "{$prefix}usergroupid", 'value' => $ids, 'operator' => vB_dB_Query::OPERATOR_EQ);
		}

		if (isset($user['coppauser']))
		{
			$user_option_fields = vB::getDatastore()->getValue('bf_misc_useroptions');
			if ($user['coppauser'] == 1)
			{
				$conditions[] = array('field' => "{$prefix}options", 'value' => $user_option_fields['coppauser'],
					'operator' => vB_dB_Query::OPERATOR_AND);
			}
			else if ($user['coppauser'] == 0)
			{
				$conditions[] = array('field' => "{$prefix}options", 'value' => $user_option_fields['coppauser'],
					'operator' => vB_dB_Query::OPERATOR_NAND);
			}
		}

		if (isset($user['facebook']))
		{
			if ($user['facebook'] == 1)
			{
				$conditions[] = array('field' => "{$prefix}fbuserid", 'value' => '',
					'operator' => vB_dB_Query::OPERATOR_NE);
			}
			else if ($user['facebook'] == 0)
			{
				$conditions[] = array('field' => "{$prefix}fbuserid", 'value' => '',
					'operator' => vB_dB_Query::OPERATOR_EQ);
			}
		}

		//different table.
		if ($user['signature'])
		{
			$conditions[] = array('field' => 'usertextfield.signature', 'value' => $user['signature'],
				'operator' => vB_dB_Query::OPERATOR_INCLUDES);
		}

		//this is special, I'm not sure why...
		//actual filter added below with the standard filters
		if ($user['lastactivityafter'])
		{
			if (strval($user['lastactivityafter']) == strval(intval($user['lastactivityafter'])))
			{
				$user['lastactivityafter'] = intval($user['lastactivityafter']);
			}
			else
			{
				$user['lastactivityafter'] = strtotime($user['lastactivityafter']);
			}
		}

		//note that previously the date => timestamp conversion was done on the mysql side
		//with UNIX_TIMESTAMP.  In order to avoid trying to encode as an operation into the
		//DB Assertor, we'll move this to the client side.
		$dateFields = array('joindateafter', 'joindatebefore', 'lastactivitybefore', 'lastpostafter', 'lastpostbefore');
		foreach ($dateFields as $field)
		{
			//strtotime is strange function, but anything valid for UNIX_TIMESTAMP should be okay here.
			if ($user[$field])
			{
				$user[$field] = strtotime($user[$field]);
			}
		}

		//standard fields
		// This is for fields that check if the given value is included in the text of a given field
		// (field like "%value%") in sql terms.  This replaces a bunch of nearly identical
		// if statements
		$fields = array (
			vB_dB_Query::OPERATOR_INCLUDES => array(
				'email', 'parentemail', 'homepage', 'icq', 'yahoo', 'msn', 'skype', 'usertitle', 'usertitle', 'ipaddress'
			),
			vB_dB_Query::OPERATOR_GT => array(
				'birthdayafter' => 'birthday_search', 'lastactivityafter' => 'lastactivity',
				'joindateafter' => 'joindate', 'lastpostafter' => 'lastpost'
			),
			vB_dB_Query::OPERATOR_GTE => array(
				'postslower' => 'posts', 'infractionslower' => 'infractions',
				'warningslower' => 'warnings', 'pointslower' => 'ipoints', 'reputationlower' => 'reputation'
			),
			vB_dB_Query::OPERATOR_LT => array(
				'birthdaybefore' => 'birthday_search', 'lastactivitybefore' => 'lastactivity',
				'joindatebefore' => 'joindate', 'lastpostbefore' => 'lastpost',
				'postslower' => 'posts', 'infractionsupper' => 'infractions',
				'warningsupper' => 'warnings', 'pointsupper' => 'ipoints', 'reputationupper' => 'reputation',
			),
		);

		foreach ($fields as $operator => $fieldList)
		{
			foreach ($fieldList as $key => $field)
			{
				if (is_numeric($key))
				{
					$key = $field;
				}

				if ($user[$key])
				{
					$conditions[] = array('field' => "{$prefix}$field", 'value' => $user[$key], 'operator' => $operator);
				}
			}
		}

		$profilefields = vB::getDbAssertor()->assertQuery('vBForum:fetchprofilefields');
		foreach ($profilefields as $profilefield)
		{
			$conditions = array_merge($conditions, $this->getProfileFieldConditions($profilefield, $profile));
		}

		return array('filters' => $conditions, 'exceptions' => array('aim' => $user['aim'], 'membergroup' => $user['membergroup']));
	}

	/** Get the profile information so the presentation can render it
	 *
	 *	@param 		int		userid
	 *
	 *	@return		mixed	array with signature, sigpic, the permissions, and revision info
	 */
	public function fetchSignature($userid)
	{
		if (empty($userid))
		{
			return false;
		}
		$sigUserContext = vB::getUserContext($userid);
		$options = vB::getDatastore()->getValue('options');

		if (!$sigUserContext->hasPermission('genericpermissions', 'canusesignature') OR
			!(bool)$options['allow_signatures'])
		{
			return false;
		}
		$userInfo = vB_User::fetchUserinfo($userid);

		if(empty($userInfo['signature']))
		{
			return '';
		}

		$signature = array();
		$signature['raw'] = trim($userInfo['signature']);
		$signature['permissions'] = array(
			'dohtml' => $sigUserContext->hasPermission('signaturepermissions', 'allowhtml'),
			'dobbcode' => $sigUserContext->hasPermission('signaturepermissions', 'canbbcode'),
			'dobbimagecode' => $sigUserContext->hasPermission('signaturepermissions', 'allowimg'),
			'dosmilies' => $sigUserContext->hasPermission('signaturepermissions', 'allowsmilies'),
			);
		if (isset($userInfo['sigpic']) AND !empty($userInfo['sigpic']) AND
			$sigUserContext->hasPermission('signaturepermissions', 'cansigpic'))
		{
			$signature['sigpic'] = $userInfo['sigpic'];
			$signature['sigpicrevision'] = $userInfo['sigpicrevision'];
			$signature['sigpicdateline'] = $userInfo['sigpicdateline'];;
		}
		return $signature;
	}


	// ###################### Start checkprofilefield #######################
	protected function getProfileFieldConditions($profilefield, $profile)
	{
		global $vbulletin;

		$varname = "field$profilefield[profilefieldid]";
		$optionalvar = $varname . '_opt';

		if (isset($profile["$varname"]))
		{
			$value = $profile["$varname"];
		}
		else
		{
			$value = '';
		}

		if (isset($profile["$optionalvar"]))
		{
			$optvalue = $profile["$optionalvar"];
		}
		else
		{
			$optvalue = '';
		}

		if (empty($value) AND $optvalue === '')
		{
			return array();
		}

		if (($profilefield['type'] == 'input' OR $profilefield['type'] == 'textarea') AND $value !== '')
		{
			$conditions[] = array('field' => $varname, 'value' => vB_String::htmlSpecialCharsUni(trim($value)),
					'operator' => vB_dB_Query::OPERATOR_INCLUDES);
		}

		if ($profilefield['type'] == 'radio' OR $profilefield['type'] == 'select')
		{
			if ($value == 0 AND $optvalue === '')
			{
				// The select field was left blank!
				// and the optional field is also empty
				return array();
			}

			if ($profilefield['optional'] AND !empty($optvalue))
			{
				$conditions[] = array('field' => $varname, 'value' => htmlspecialchars_uni(trim($optvalue)),
					'operator' => vB_dB_Query::OPERATOR_INCLUDES);
			}
			else
			{
				$data = unserialize($profilefield['data']);
				foreach($data AS $key => $val)
				{
					if (($key + 1) == $value)
					{
						$conditions[] = array('field' => $varname, 'value' => htmlspecialchars_uni(trim($val)),
							'operator' => vB_dB_Query::OPERATOR_INCLUDES);
						break;
					}
				}
			}
		}

		if (($profilefield['type'] == 'checkbox' OR $profilefield['type'] == 'select_multiple') AND is_array($value))
		{
			foreach ($value AS $key => $val)
			{
				$conditions[] = array('field' => $varname, 'value' => pow(2, $val - 1), 'operator' => vB_dB_Query::OPERATOR_AND);
			}
		}
		return $conditions;
	}

	/**
	 * Fetch today's birthdays
	 * @return array birthday information
	 */
	public function fetchBirthdays()
	{
		$today = vbdate('Y-m-d', vB::getRequest()->getTimeNow(), false, false);
		$birthdaycache = vB::getDatastore()->get_value('birthdaycache');

		if (!is_array($birthdaycache)
			OR ($today != $birthdaycache['day1'] AND $today != $birthdaycache['day2'])
			OR !is_array($birthdaycache['users1'])
		)
		{
			// Need to update!
			require_once(DIR . '/includes/functions_databuild.php');
			$birthdaystore = build_birthdays();
		}
		else
		{
			$birthdaystore = $birthdaycache;
		}

		switch ($today)
		{
			case $birthdaystore['day1']:
				$birthdaysarray = $birthdaystore['users1'];
				break;

			case $birthdaystore['day2']:
				$birthdaysarray = $birthdaystore['users2'];
				break;

			default:
				$birthdaysarray = array();
		}
		// memory saving
		unset($birthdaystore);

		return $birthdaysarray;

	}

	/**
	 * Returns an array with the usernames for the user ids
	 * @param array $userIds
	 */
	public function fetchUsernames($userIds)
	{
		$res = array();
		$usernames = vB_Library::instance('user')->fetchUsernames($userIds);
		foreach ($usernames as $userid => $username)
		{
			$res[$userid]['username'] = $username;
			$res[$userid]['profileUrl'] = vB5_Route::buildUrl('profile', array('userid' => $userid, 'username' => $username));
		}
		return $res;
	}

	/**
	 * Updates the user ignore list
	 *
	 * @param int	Userid
	 * @param mixed	Array of ids.
	 */
	protected function updateIgnorelist($userid, $userList)
	{
		$userContext = vB::getUserContext();
		$currentUserId = $userContext->fetchUserId();
		$userid = intval($userid);
		$vboptions = vB::getDatastore()->getValue('options');

		if ($userid <= 0 AND $currentUserId)
		{
			$userid = $currentUserId;
		}

		// Check if current user canadminusers
		try
		{
			$this->checkHasAdminPermission('canadminusers');
		}
		catch (Exception $e)
		{
			// No. Then we need to do something here.
			if ($currentUserId != $userid)
			{
				// If current user isn't the same as passed $userid
				throw new vB_Exception_Api('no_permission');
			}
		}

		$assertor = vB::getDbAssertor();

		//delete the existing ignored users
		$assertor->assertQuery('userlist', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'userid', 'value' => $userid, 'operator' => vB_dB_Query::OPERATOR_EQ),
				array('field' => 'type', 'value' => 'ignore', 'operator' => vB_dB_Query::OPERATOR_EQ),
				array('field' => 'friend', 'value' => 'denied', 'operator' => vB_dB_Query::OPERATOR_EQ)
			)
		));

		$ignored = array();
		if (!empty($userList))
		{
			$currentUserLvl = $userContext->getUserLevel();
			//get the ids from the userlist
			$users = $assertor->getRows('user', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'username' => $userList));

			// Update user list record
			foreach ($users as $user)
			{
				//ignore user itself
				if ($user['userid'] != $userid)
				{
					if (!$vboptions['ignoremods'] AND $currentUserLvl < vB::getUserContext($user['userid'])->getUserLevel())
					{
						throw new vB_Exception_Api('listignoreuser', array($user['username']));
					}

					$existing = $assertor->getRow('userlist', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						'userid' => $userid,
						'relationid' => $user['userid']
					));

					// update the record
					if ($existing AND !empty($existing) AND empty($existing['errors']))
					{
						$queryData = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
							vB_dB_Query::CONDITIONS_KEY => array('userid' => $userid, 'relationid' => $user['userid']),
							'type' => 'ignore',
							'friend' => 'denied'
						);
					}
					else
					{
						$queryData = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
							'userid' => $userid,
							'relationid' => $user['userid'],
							'type' => 'ignore',
							'friend' => 'denied'
						);
					}
					$response = $assertor->assertQuery('userlist', $queryData);
					$ignored[] = $user['userid'];
				}
			}
		}

		// return the ids ignored
		return $ignored;
	}

	/**
	 * Gets the user's ignore list
	 *
	 * @param	mixed	Array of ignored users ids.
	 *
	 * @return	String	User's ignore list.
	 */
	protected function fetchUserIgnorelist($ignorelist)
	{
		$userIgnorelist = '';
		if (!empty($ignorelist))
		{
			$users = vB::getDbAssertor()->getRows('user', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'userid' => $ignorelist)
			);

			$temp = array();
			foreach($users as $user)
			{
				$temp[] = $user['username'];
			}
			$userIgnorelist = implode(',', $temp);
		}

		return $userIgnorelist;
	}

	/**
	 * Updates the user status
	 *
	 * @param int		UserID
	 * @param String	Status to set
	 *
	 * @return	String	Updated status from user.
	 */
	public function updateStatus($userid = false, $status)
	{
		$userContext = vB::getUserContext();
		$currentUserId = $userContext->fetchUserId();
		$userid = intval($userid);
		$vboptions = vB::getDatastore()->getValue('options');

		if (vB_String::vbStrlen($status) > $vboptions['statusMaxChars'])
		{
			throw new vB_Exception_Api('please_enter_user_status_with_at_most_x_characters', array($vboptions['statusMaxChars']));
		}

		if ($userid <= 0 AND $currentUserId)
		{
			$userid = $currentUserId;
		}

		// check user is logged
		if (!$userid OR !$currentUserId)
		{
			throw new vB_Exception_Api('invalid_userid');
		}

		// Check if current user canadminusers
		try
		{
			$this->checkHasAdminPermission('canadminusers');
		}
		catch (Exception $e)
		{
			// No. Then we need to do something here.
			if ($currentUserId != $userid)
			{
				// If current user isn't the same as passed $userid
				throw new vB_Exception_Api('no_permission');
			}
		}

		$userInfo = vB_User::fetchUserInfo($userid);
		$userdata = new vB_Datamanager_User();
		$userdata->set_existing($userInfo);
		$userdata->set('status', $status);
		$result = $userdata->save();

		if (!is_array($result))
		{
			$userInfo = vB_User::fetchUserInfo(0, array(), 0, true);
			return $userInfo['status'];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Ban users
	 *
	 * @param array $userids Userids to ban
	 * @param int $banusergroupid Which banned usergroup to move the users to
	 * @param string $period Ban period
	 * @param string $reason Ban reason
	 */
	public function banUsers($userids, $banusergroupid, $period, $reason = '')
	{
		$loginuser = &vB::getCurrentSession()->fetch_userinfo();
		$usercontext = &vB::getUserContext($loginuser['userid']);
		if (!$usercontext->hasAdminPermission('cancontrolpanel') AND !$usercontext->hasPermission('moderatorpermissions', 'canbanusers'))
		{
			$forumHome = vB_Library::instance('content_channel')->getForumHomeChannel();
			throw new vB_Exception_Api('nopermission_loggedin',
				$loginuser['username'],
				vB_Template_Runtime::fetchStyleVar('right'),
				vB::getCurrentSession()->get('sessionurl'),
				$loginuser['securitytoken'],
				vB5_Route::buildUrl($forumHome['routeid'] . '|nosession|fullurl')
			);
		}

		foreach ($userids as &$userid)
		{
			$userid = intval($userid);
		}

		$bannedusergroups = vB_Api::instanceInternal('usergroup')->fetchBannedUsergroups();

		if (!in_array($banusergroupid, array_keys($bannedusergroups)))
		{
			throw new vB_Exception_Api('invalid_usergroup_specified');
		}

		// check that the number of days is valid
		if ($period != 'PERMANENT' AND !preg_match('#^(D|M|Y)_[1-9][0-9]?$#', $period))
		{
			throw new vB_Exception_Api('invalid_ban_period_specified');
		}

		if ($period == 'PERMANENT')
		{
			// make this ban permanent
			$liftdate = 0;
		}
		else
		{
			// get the unixtime for when this ban will be lifted
			require_once(DIR . '/includes/functions_banning.php');
			$liftdate = convert_date_to_timestamp($period);
		}

		$user_dms = array();

		$current_bans = vB::getDbAssertor()->getRows('user_fetchcurrentbans', array(
			'userids' => $userids
		));
		foreach ($current_bans as $current_ban)
		{
			$userinfo = vB_User::fetchUserinfo($current_ban['userid']);
			$userid = $userinfo['userid'];

			if ($current_ban['bandate'])
			{
				// they already have a ban, check if the current one is being made permanent, continue if its not
				if ($liftdate AND $liftdate < $current_ban['liftdate'])
				{
					continue;
				}

				// there is already a record - just update this record
				vB::getDbAssertor()->update('userban',
					array(
						'bandate' => vB::getRequest()->getTimeNow(),
						'liftdate' => $liftdate,
						'adminid' => $loginuser['userid'],
						'reason' => $reason,
					),
					array(
						'userid' => $userinfo['userid'],
					)
				);
			}
			else
			{
				// insert a record into the userban table
				/*insert query*/
				vB::getDbAssertor()->insert('userban', array(
					'userid' => $userinfo['userid'],
					'usergroupid' => $userinfo['usergroupid'],
					'displaygroupid' => $userinfo['displaygroupid'],
					'customtitle' => $userinfo['customtitle'],
					'usertitle' => $userinfo['usertitle'],
					'adminid' => $loginuser['userid'],
					'bandate' => vB::getRequest()->getTimeNow(),
					'liftdate' => $liftdate,
					'reason' => $reason,
				));
			}

			// update the user record
			$user_dms[$userid] = new vB_Datamanager_User(NULL, vB_DataManager_Constants::ERRTYPE_SILENT);
			$user_dms[$userid]->set_existing($userinfo);
			$user_dms[$userid]->set('usergroupid', $banusergroupid);
			$user_dms[$userid]->set('displaygroupid', 0);

			// update the user's title if they've specified a special user title for the banned group
			if ($bannedusergroups[$banusergroupid]['usertitle'] != '')
			{
				$user_dms[$userid]->set('usertitle', $bannedusergroups[$banusergroupid]['usertitle']);
				$user_dms[$userid]->set('customtitle', 0);
			}
			$user_dms[$userid]->pre_save();
		}

		foreach ($user_dms AS $userdm)
		{
			$userdm->save();
		}

		// and clear perms
		foreach ($userids AS $uid)
		{
			vB::getUserContext($uid)->clearChannelPermissions();
		}

		return true;
	}

	public function hasPermissions($group, $permission, $nodeid = false)
	{
		if ($this->disabled)
		{
			// if disabled we do not have permission
			return false;
		}

		if (empty($nodeid))
		{
			return vB::getUserContext()->hasPermission($group, $permission);
		}
		else
		{
			return vB::getUserContext()->getChannelLimitPermission($group, $permission, $nodeid);
		}
	}

	/**
	 *	Invites members to a given node channel passing either an array of userids or usernames.
	 *
	 *	@param	array		Array of userids to invite.
	 *	@param	array		Array of usernames to invite.
	 *	@param  int			Node id
	 *	@param  string		Either 'member_to' (blogs) or 'sg_member_to' (social groups)
	 *
	 *	@param	array		List of the sucessfully invited members.
	 */
	public function inviteMembers($userIds, $userNames, $nodeId, $requestType)
	{
		$inviteMembers = array();
		// fetch userids...
		if (!is_array($userNames))
		{
			$userNames = array($userNames);
		}

		$users = array();
		if (is_array($userNames) AND !empty($userNames))
		{
			$users = vB::getDbAssertor()->assertQuery('user', array('username' => $userNames));
			if ($users AND !$users->valid())
			{
				$users = array();
			}
		}

		foreach ($users AS $user)
		{
			$inviteMembers[] = $user['userid'];
		}

		// and check that userids are valid...
		if (!is_array($userIds))
		{
			$userIds = array($userIds);
		}

		foreach ($userIds AS $pos => $id)
		{
			if (!intval($id))
			{
				unset($userIds[$pos]);
			}
		}

		$inviteMembers = array_unique(array_merge($inviteMembers, $userIds));
		if (empty($inviteMembers))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		if (!intval($nodeId))
		{
			throw new vB_Exception_Api('invalid_node_id');
		}
		// let's check that node really exists
		$nodeApi = vB_Api::instanceInternal('node');
		$node = $nodeApi->getNode($nodeId);

		//and check that these invites don't already exist.

		$existingCheck =  vB::getDbAssertor()->assertQuery('vBForum:getExistingRequest', array('userid' => $inviteMembers,
			'nodeid' => $nodeId, 'request' => $requestType));

		if ($existingCheck->valid())
		{
			foreach ($existingCheck AS $existing)
			{
				unset($inviteMembers[$existing['userid']]);
			}
		}
		$invited = array();
		foreach ($inviteMembers AS $member)
		{
			$response = vB_Library::instance('node')->requestChannel($nodeId, $requestType, $member, null, true);
			if (!is_array($response))
			{
				$invited[] = $member;
			}
		}

		return $invited;
	}

	/**
	 *	Generates users mailing list for the given criteria.
	 *	Used for admincp - email sending and list generating.
	 *
	 * 	@param 	array 	$user 		An array of common conditions for user search
	 * 	@param 	array 	$profile 	An array of user profile field conditions for user search
	 * 	@param	array	$options 	Set of options such as activation info and pagination.
	 *
	 *	@return bool |array False if no user found. Otherwise it returns users array as result.
	 *		 The array also contains a field that stores total found user's e-mail count.
	 */
	public function generateMailingList($user, $profile, $options = array())
	{
		if (!vB::getUserContext()->hasAdminPermission('canadminusers'))
		{
			throw new vB_Exception_Api('invalid_permissions');
		}

		$conditions = $this->fetchUserSearchCondition($user, $profile);
		$conditions['options'] = array('adminemail' => $user['adminemail']);

		if (!empty($options['activation']))
		{
			$conditions['activation'] = 1;
			$conditions[vB_dB_Query::PARAM_LIMITPAGE] = (intval($options[vB_dB_Query::PARAM_LIMITPAGE])) ? intval($options[vB_dB_Query::PARAM_LIMITPAGE]) : 0;
			// default 500... taken from admincp
			$conditions[vB_dB_Query::PARAM_LIMIT] = (intval($options[vB_dB_Query::PARAM_LIMIT])) ? intval($options[vB_dB_Query::PARAM_LIMIT]) : 500;
		}

		$mailList = vB::getDbAssertor()->getRows('fetchMailingList', $conditions);
		return array('list' => $mailList, 'totalcount' => count($mailList));
	}

	/**
	 *	Fetch users and info from a given user criteria
	 *	Used for admincp - verticalresponse.
	 *
	 * 	@param 	array 	$user 		An array of common conditions for user search
	 * 	@param 	array 	$profile 	An array of user profile field conditions for user search
	 * 	@param	array	$options 	Set of options such as activation info and pagination.
	 *
	 *	@return array 	$result 	Result which includes the 'users' => userlist and the 'totalcount'.
	 */
	public function getUsersFromCriteria($user, $profile, $options = array())
	{
		if (!vB::getUserContext()->hasAdminPermission('canadminusers'))
		{
			throw new vB_Exception_Api('invalid_permissions');
		}

		$conditions = $this->fetchUserSearchCondition($user, $profile);
		if (!empty($options[vB_dB_Query::PARAM_LIMITPAGE]) OR !empty($options[vB_dB_Query::PARAM_LIMIT]))
		{
			$conditions[vB_dB_Query::PARAM_LIMITPAGE] = (intval($options[vB_dB_Query::PARAM_LIMITPAGE])) ? intval($options[vB_dB_Query::PARAM_LIMITPAGE]) : 1;
			// default 50...
			$conditions[vB_dB_Query::PARAM_LIMIT] = (intval($options[vB_dB_Query::PARAM_LIMIT])) ? intval($options[vB_dB_Query::PARAM_LIMIT]) : 50;
		}

		$userList = vB::getDbAssertor()->getRows('fetchUsersFromCriteria', $conditions);
		return array('users' => $userList, 'totalcount' => count($userList));
	}

	/**
	 *	Fetch private messages statistics from all the users.
	 *	Used for admincp - usertools private message statistics
	 *	@TODO implement in class cache to user in some others places...
	 *
	 * 	@param	array	$options 	Set of options such as pagination, total pms filter.
	 *
	 *	@return array 	$result 	Private messages grouped by userid (including some userinfo and pm total count).
	 */
	public function fetchUsersPms($options = array())
	{
		if (!vB::getUserContext()->hasAdminPermission('canadminusers'))
		{
			throw new vB_Exception_Api('invalid_permissions');
		}

		$params = array();
		if (!empty($options[vB_dB_Query::PARAM_LIMITPAGE]) OR !empty($options[vB_dB_Query::PARAM_LIMIT]))
		{
			$params[vB_dB_Query::PARAM_LIMITPAGE] = (intval($options[vB_dB_Query::PARAM_LIMITPAGE])) ? intval($options[vB_dB_Query::PARAM_LIMITPAGE]) : 1;
			// default 50...
			$params[vB_dB_Query::PARAM_LIMIT] = (intval($options[vB_dB_Query::PARAM_LIMIT])) ? intval($options[vB_dB_Query::PARAM_LIMIT]) : 50;
		}

		if (!empty($options['total']))
		{
			$params['total'] = intval($options['total']);
		}

		if (!empty($options['sortby']))
		{
			$params['sortby'] = array($options['sortby']);
		}

		if (!empty($options[vB_dB_Query::CONDITIONS_KEY]))
		{
			$params[vB_dB_Query::CONDITIONS_KEY] = $options[vB_dB_Query::CONDITIONS_KEY];
		}

		$pms = vB::getDbAssertor()->getRows('vBForum:getUsersPms', $params);
		return $pms;
	}

	/**
	 * This implements vB_PermissionContext::getAdminUser().
	 * return	int		User id from a user that can administer the admincp
	 */
	public function fetchAdminUser()
	{
		return vB_PermissionContext::getAdminUser();
	}

	/**
	 * This gets the current user profile fields from the database.
	 * @TODO improve this to be consistent with profilefield table. We should wrap that out when moving user profile fields add/updating to the API
	 *
	 * @return	array	The title of the existing user profile fields.
	 */
	public function fetchUserProfileFields()
	{
		$uFields = vB::getDbAssertor()->assertQuery('fetchUserFields');
		$fields = array();
		while($uFields AND $uFields->valid())
		{
			$field = $uFields->current();
			if ($field['Field'] != 'temp' AND $field['Field'] != 'userid')
			{
				$fields[] = $field['Field'];
			}

			$uFields->next();
		}

		return $fields;
	}

	/**
	 * Get the user title regarding the given posts.
	 *
	 * @param	int		Number of user posts.
	 *
	 * @return	string	User title.
	 */
	public function getUsertitleFromPosts($posts)
	{
		if (!is_numeric($posts))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		if (isset($this->usertitleCache[$posts]))
		{
			return $this->usertitleCache[$posts];
		}

		$title = vB::getDbAssertor()->getRow('usertitle', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'minposts', 'value' => $posts, 'operator' => 'LTE')
				)
			), array('field' => array('minposts'), 'direction' => array(vB_dB_Query::SORT_DESC))
		);

		$this->usertitleCache[$posts] = $title['title'];
		return $this->usertitleCache[$posts];
	}

	/**
	 * Mostly a getter for user privacy options.
	 *
	 * @return	array	Existing user privacy options.
	 */
	public function getPrivacyOptions()
	{
		return $this->privacyOptions;
	}

	/** This likes the channels below a parent node where a user can create starters based on groupintopic

	 * @param	int		the ancestor node id
	 *
	 * @return	mixed 	array of integer, title- the nodeids

	*/
	public function getGitCanStart($parentNodeId)
	{
		$groupsCanContribute = vB::getUserContext()->getContributorGroups($parentNodeId);

		return vB::getDbAssertor()->getRows('vBForum:getGitCanStartThreads', array('parentnodeId' => $parentNodeId,
		'contributors' => $groupsCanContribute, 'userid' => vB::getCurrentSession()->get('userid')));

	}

	/** Adjust GMT time back to user's time
	 *
	 * @param string $userinfo
	 * @param type $adjustForServer
	 *
	 * @return integer
	 */
	public function fetchTimeOffset($adjustForServer = false)
	{
		$userInfo = vB::getCurrentSession()->fetch_userinfo();

		if (is_array($userInfo) AND isset($userInfo['timezoneoffset']))
		{
			if (isset($userInfo['dstonoff']) AND $userInfo['dstonoff'])
			{
				// DST is on, add an hour
				$userInfo['timezoneoffset']++;
				if ((substr($userInfo['timezoneoffset'], 0, 1) != '-') AND (substr($userInfo['timezoneoffset'], 0, 1) != '+'))
				{
					// recorrect so that it has a + sign, if necessary
					$userInfo['timezoneoffset'] = '+' . $userInfo['timezoneoffset'];
				}
			}

			$options = vB::getDatastore()->getValue('options');
			if ($options['dstonoff'] AND $adjust_for_server)
			{
				$userInfo['timezoneoffset']--;
			}
			$hourdiff = ($userInfo['timezoneoffset'] - date('Z', vB::getRequest()->getTimeNow()) / 3600 ) * 3600;
		}
		else
		{
			$hourdiff = $options['hourdiff'];
		}
		return $hourdiff;
	}
}
