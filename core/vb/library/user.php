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
 * vB_Library_User
 *
 * @package vBApi
 * @access public
 */
class vB_Library_User extends vB_Library
{
	protected function __construct()
	{
		parent::__construct();
	}

	/**
	 * Check whether a user is banned.
	 *
	 * @param integer $userid User ID.
	 * @return bool Whether the user is banned.
	 */
	public function isBanned($userid)
	{
		$usercontext = vB::getUserContext($userid);
		return !$usercontext->hasPermission('genericoptions', 'isnotbannedgroup');
	}

	/**
	 * Check whether a user is banned and returns info such as reason and liftdate if possible.
	 *
	 * @param	int		User id
	 *
	 * @retun	mixed	Array containing ban liftdate and reason or false is user is not banned.
	 */
	public function fetchBannedInfo($userid)
	{
		$userid = intval($userid);
		if (!$userid)
		{
			$userid = vB::getCurrentSession()->get('userid');
		}

		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		// looking up cache for the node
		$hashKey = 'vbUserBanned_'. $userid;
		$banned = $cache->read($hashKey);

		if (!empty($banned))
		{
			// a string false is received if the banning was already checked and the user is not banned
			if ($banned === 'false')
			{
				return false;
			}
			return $banned;
		}

		if ($this->isBanned($userid))
		{
			$info = array('isbanned' => 1);
			$banRecord = vB::getDbAssertor()->getRow('vBForum:userban', array('userid' => $userid));
			if ($banRecord AND empty($banRecord['errors']))
			{
				$info['liftdate'] = $banRecord['liftdate'];
				$info['reason'] = $banRecord['reason'];
				$info['admin'] = $this->fetchUserName($banRecord['adminid']);
			}
			$cache->write($hashKey, $info, 1440, 'userChg_' . $userid);
			return $info;
		}
		else
		{
			// false is intentionally passed as string so it can be identified as different from the boolean false returned by the cache if not cached
			$cache->write($hashKey, 'false', 1440, 'userChg_' . $userid);
			return false;
		}
	}

	/**
	 * Fetches the username for a userid
	 *
	 * @param integer $ User ID
	 * @return string
	 */
	public function fetchUserName($userid)
	{
		$userInfo = $this->fetchUserinfo($userid);

		if (empty($userInfo) OR empty($userInfo['userid']))
		{
			return false;
		}

		return $userInfo['username'];
	}
	/**
	 * Fetches the user names for the given user ids
	 * @param array $userIds
	 * @return array $usernames
	 */
	public function fetchUserNames($userIds)
	{
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$usernames = array();
		$remainingIds = array();
		foreach ($userIds as $userid)
		{
			$user = $cache->read('vbUserInfo_' . $userid);
			if (!empty($user))
			{
				$usernames[$userid] = $user['username'];
			}
			else
			{
				$remainingIds[] = $userid;
			}
		}
		if (!empty($remainingIds))
		{
			$usernames += vB::getDbAssertor()->getColumn('user', 'username', array('userid' => $remainingIds), false, 'userid');
		}
		return $usernames;
	}

	/**
	 * Fetches an array containing info for the specified user, or false if user is not found
	 * @param integer $ User ID
	 * @param integer $ Language ID. If set to 0, it will use user-set languageid (if exists) or default languageid.
	 * @param boolean $ If true, the method won't use user cache but fetch information from DB.
	 * @return array The information for the requested user
	 */
	public function fetchUserWithPerms($userid, $languageid = 0, $nocache = false)
	{
		//Try cached data.
		$fastCache = vB_Cache::instance(vB_Cache::CACHE_FAST);

		$userCacheKey = "vb_UserWPerms_$userid" . '_' . $languageid;
		$infoKey = "vb_UserInfo_$userid" . '_' . $languageid;
		$cached = vB_Cache::instance(vB_Cache::CACHE_LARGE)->read($userCacheKey);

		// This already uses FAST cache, do not encapsulate in LARGE
		$userInfo = $this->fetchUserinfo($userid, array(), $languageid);

		//This includes usergroups, groupintopic, moderator, and basic userinformation. Each should be cached in fastcache.
		if (($cached !== false) AND ($cached['groups'] !== false))
		{
			$usergroups = $cached['groups'];
			$groupintopic = $cached['git'];
			$moderators = $cached['moderators'];
		}
		else
		{
			//Let's see if we have the raw data.
			$groupintopic = $this->getGroupInTopic($userid);
			$primary_group_id = $userInfo['usergroupid'];
			$secondary_group_ids = (!empty($userInfo['membergroupids'])) ? explode(',', str_replace(' ', '', $userInfo['membergroupids'])) : array();
			$infraction_group_ids = (!empty($userInfo['infractiongroupids'])) ? explode(',', str_replace(' ', '', $userInfo['infractiongroupids'])) : array();
			$usergroups = array('groupid' => $primary_group_id, 'secondary' => $secondary_group_ids, 'infraction' => $infraction_group_ids);
			$moderators = $this->fetchModerator($userid);
			vB_Cache::instance(vB_Cache::CACHE_LARGE)->write($userCacheKey, array('groups' => $usergroups,
				'git' => $groupintopic, 'moderators' => $moderators), 1440,
				array("userPerms_$userid", "userChg_$userid"));
		}
		$fastCache->write("vB_UserUG_$userid", $usergroups, 5, "userChg_$userid");
		$fastCache->write("vB_UserGIT_$userid", $groupintopic, 5, array("userPerms_$userid", "userChg_$userid"));
		$fastCache->write("vB_UserMod_$userid", $moderators, 30, array("userPerms_$userid", "userChg_$userid"));
		$fastCache->write($infoKey, $userInfo, 5, "userChg_$userid");

		$this->groupInTopic[$userid] = $groupintopic;
		return $userInfo;


	}


	/** This returns a user's additional permissions from the groupintopic table
	 *
	 *	@param	int
	 *	@param	int	optional nodeid
	 *
	 *	@return	mixed	Associated array `of  array(nodeid, groupid);
	 ***/
	public function getGroupInTopic($userid , $nodeid = false, $forceReload = false)
	{
		if (!isset($this->groupInTopic[$userid]) OR $forceReload)
		{
			if (!$forceReload)
			{
				$cached = vB_Cache::instance(vB_Cache::CACHE_FAST)->read("vB_UserGIT_$userid");

				if ($cached !== false)
				{
					$this->groupInTopic[$userid] = $cached;
				}
			}
			$perms = array();
		}
		if (!isset($this->groupInTopic[$userid]) OR $forceReload)
		{
			// Only call getUserContext if we already have it, as we don't need all of the queries that it does
			if (vB::isUserContextSet($userid) AND !$forceReload)
			{
				$groupInTopic = vB::getUserContext($userid)->fetchGroupInTopic();
				foreach ($groupInTopic AS $_nodeid => $permissions)
				{
					foreach($permissions AS $permission)
					{
						$perms[] = array('nodeid' => $_nodeid, 'groupid' => $permission);
					}
				}
			}
			else
			{
				$params = array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'userid' => $userid
				);
				$permQry = vB::getDbAssertor()->assertQuery('vBForum:groupintopic', $params);
				$perms = array();
				foreach ($permQry AS $permission)
				{
					$perms[] = array('nodeid' => $permission['nodeid'], 'groupid' => $permission['groupid']);
				}
			}
			$this->groupInTopic[$userid] = $perms;
			vB_Cache::instance(vB_Cache::CACHE_FAST)->write("vB_UserGIT_$userid", $perms, 1440,
				array("userChg_$userid", "userPerms_$userid", 'perms_changed',  'vB_ChannelStructure_chg'));
		}

		if ($nodeid)
		{
			$results = array();
			foreach ($this->groupInTopic[$userid] AS $perm)
			{
				if ($perm['nodeid'] == $nodeid)
				{
					$results[] = $perm;
				}
			}
			return $results;
		}
		return $this->groupInTopic[$userid];
	}


	/**
	 * Fetches an array containing all the moderator permission informationd
	 * @param integer 	User ID
	 * @param mixed 	array of $nodeids where the user in a moveratoe
	 * @return mixed	the permission array
	 */

	public function fetchModerator($userid, $moderators = false)
	{
		$cached = vB_Cache::instance(vB_Cache::CACHE_FAST)->read("vB_UserMod_$userid");
		if ($cached !== false AND empty($cached))
		{
			return array();
		}

		$parentnodeids = array();
		$moderatorPerms = array();

		if ($moderators === false)
		{
			$moderators = vB::getDbAssertor()->assertQuery('vBForum:moderator', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'userid' => $userid));
			if (!$moderators->valid())
			{
				return array();
			}
		}

		if (empty($moderators))
		{
			return array();
		}
		foreach ($moderators AS $modPerm)
		{
			if (isset($modPerm['nodeid']))
			{
				if ($modPerm['nodeid'] >= 1)
				{
					$parentnodeids[] = $modPerm['nodeid'];
				}

				$moderatorPerms[$modPerm['nodeid']] = $modPerm;
			}
		}

		if (!empty($parentnodeids))
		{
			foreach ($parentnodeids as $parentnodeid)
			{
				if ($parentnodeid < 1)
				{
					continue;
				}

				$closurerecords = vB::getDbAssertor()->assertQuery('vBForum:getDescendantChannelNodeIds', array(
					'parentnodeid' => $parentnodeid, 'channelType' => vB_Types::instance()->getContentTypeID('vBForum_Channel')
				));
				foreach ($closurerecords as $closurerecord)
				{
					$childnodeid = $closurerecord['child'];
					if (!isset($moderatorPerms[$childnodeid]) AND isset($moderatorPerms[$parentnodeid]))
					{
						// descendant channels inherit moderator permissions from parent channels
						// so we copy the parent channel's permissions and change the nodeid in it
						$moderatorPerms[$childnodeid] = $moderatorPerms[$parentnodeid];
						$moderatorPerms[$childnodeid]['nodeid'] = $childnodeid;
					}
				}
			}
		}

		vB_Cache::instance(vB_Cache::CACHE_FAST)->write("vB_UserMod_$userid", $moderatorPerms, 30, array("userPerms_$userid", "userChg_$userid"));
		return $moderatorPerms;
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
		if ($languageid === false)
		{
			$session = vB::getCurrentSession();
			if ($session)
			{
				$languageid = vB::getCurrentSession()->get('languageid');
			}
		}

		$result = vB_User::fetchUserinfo($userid, $option, $languageid, $nocache);

		if (empty($result) OR !isset($result['userid']))
		{
			return false;
		}

		if(!empty($result['lang_options']))
		{
			//convert bitfields to arrays for external clients.
			$bitfields = vB::getDatastore()->getValue('bf_misc_languageoptions');
			$lang_options = $result['lang_options'];
			$result['lang_options'] = array();
			foreach ($bitfields as $key => $value)
			{
				$result['lang_options'][$key] = (bool) ($lang_options & $value);
			}
		}
		$userContext = vB::getUserContext($userid);

		//use the default style instead of the user style in some cases
		//1) The user style isn't set (value 0)
		//2) Style choosing isn't allowed and the user is not an admin
		if ($session = vB::getCurrentSession())
		{
			$sessionstyleid = $session->get('styleid');
			if ($sessionstyleid)
			{
				$result['styleid'] = $sessionstyleid;
			}
		}
		// adding some extra info
		if ($userid)
		{
			$result['is_admin'] = $userContext->isAdministrator();
			$result['can_use_sitebuilder'] = $userContext->hasAdminPermission('canusesitebuilder');
			$result['can_admin_ads'] = $userContext->hasAdminPermission('canadminads');
			$result['is_globally_ignored'] = $userContext->isGloballyIgnored();
		}

		$vboptions = vB::getDatastore()->getValue('options');
		$canChangeStyle =  ($vboptions['allowchangestyles'] == 1 OR $userContext->hasAdminPermission('cancontrolpanel'));
		if ( ($result['styleid'] == 0) OR !$canChangeStyle)
		{
			$result['styleid'] = $vboptions['styleid'];
		}


		return $result;
	}

	/** Gets the usergroup information
	 *
	 * @param	int		userid
	 *
	 * @return	mixed	array with groupid, secondary, infraction
	 */
	public function fetchUserGroups($userid)
	{
		$cached = vB_Cache::instance(vB_Cache::CACHE_FAST)->read("vB_UserUG_$userid");

		if ($cached !== false)
		{
			return $cached;
		}

		$session = vB::getCurrentSession();
		if ($session)
		{
			$languageid = $session->get('languageid');
			$cached = vB_Cache::instance(vB_Cache::CACHE_LARGE)->read("vb_UserWPerms_$userid" . '_' . $languageid);

			//This includes usergroups, groupintopic, moderator, and basic userinformation. Each should be cached in fastcache.
			if (($cached !== false) AND ($cached['groups'] !== false))
			{
				return $cached['groups'];
			}
		}

		//Now- we can't use fetchUserinfo here. It would put us in a loop.
		$userInfo = vB::getDbAssertor()->getRow('fetch_usergroups', array('userid' => $userid));
		$primary_group_id = $userInfo['usergroupid'];
		$secondary_group_ids = (!empty($userInfo['membergroupids'])) ? explode(',', str_replace(' ', '', $userInfo['membergroupids'])) : array();
		$infraction_group_ids = (!empty($userInfo['infractiongroupids'])) ? explode(',', str_replace(' ', '', $userInfo['infractiongroupids'])) : array();
		return array('groupid' => $primary_group_id, 'secondary' => $secondary_group_ids, 'infraction' => $infraction_group_ids);
	}

	public function sendActivateEmail($userid)
	{
		$userinfo = vB_User::fetchUserinfo($userid);

		if (empty($userinfo))
		{
			throw new vB_Exception_Api('invaliduserid');
		}

		if ($userinfo['usergroupid'] != 3)
		{
			// Already activated
			throw new vB_Exception_Api('activate_wrongusergroup');
		}

		$vboptions = vB::getDatastore()->getValue('options');
		$coppauser = false;

		if (!empty($userinfo['birthdaysearch']))
		{
			$birthday = $userinfo['birthdaysearch'];
		}
		else
		{
			//we want YYYY-MM-DD for the coppa check but normally we store MM-DD-YYYY
			$birthday = $userinfo['birthday'];
			if ($birthday[2] == '-' AND $birthday[5] == '-')
			{
				$birthday = substr($birthday, 6) . '-' . substr($birthday, 0, 2) . '-' . substr($birthday, 3, 2);
			}
		}

		if ($vboptions['usecoppa'] == 1 AND $this->needsCoppa($birthday))
		{
			$coppauser = true;
		}


		$username = trim(unhtmlspecialchars($userinfo['username']));
		require_once(DIR . '/includes/functions_user.php');

		// Try to get existing activateid from useractivation table
		$useractivation = vB::getDbAssertor()->getRow('useractivation', array(
			'userid' => $userinfo['userid'],
		));
		if ($useractivation)
		{
			$activateid = fetch_random_string(40);
			vB::getDbAssertor()->update('useractivation',
				array(
					'dateline' => vB::getRequest()->getTimeNow(),
					'activationid' => $activateid,
				),
				array(
					'userid' => $userinfo['userid'],
					'type' => 0,
				)
			);
		}
		else
		{
			$activateid = build_user_activation_id($userinfo['userid'], (($vboptions['moderatenewmembers'] OR $coppauser) ? 4 : 2), 0);
		}
		$maildata = vB_Api::instanceInternal('phrase')
			->fetchEmailPhrases('activateaccount', array($username, $vboptions['bbtitle'], class_exists('vB5_Config') ? vB5_Config::instance()->baseurl : vB::getDatastore()->getOption['bburl'], $userinfo['userid'], $activateid, $vboptions['webmasteremail']), array($username), $userinfo['languageid']);
		vB_Mail::vbmail($userinfo['email'], $maildata['subject'], $maildata['message'], true);

	}


	/**
	 * This checks whether a user needs COPPA approval based on birthdate. Responds to Ajax call
	 *
	 * @param mixed $dateInfo array of month/day/year.
	 * @return int 0 - no COPPA needed, 1- Approve but require adult validation, 2- Deny
	 */
	public function needsCoppa($dateInfo)
	{
		$options = vB::getDatastore()->get_value('options');
		$cleaner = vB::get_cleaner();

		if ((bool) $options['usecoppa']) {
			// date can come as a unix timestamp, or an array, or 'YYYY-MM-DD'
			if (is_array($dateInfo)) {
				$dateInfo = $cleaner->cleanArray($dateInfo, array('day' => vB_Cleaner::TYPE_UINT,
					'month' => vB_Cleaner::TYPE_UINT, 'year' => vB_Cleaner::TYPE_UINT));
				$birthdate = mktime(0, 0, 0, $dateInfo['month'], $dateInfo['day'], $dateInfo['year']);
			}else if (strlen($dateInfo) == 10) {
				$birthdate = strtotime($dateInfo);
			}else if (intval($dateInfo)) {
				$birthdate = intval($dateInfo);
			}else {
				return true;
			}

			if (empty($dateInfo)) {
				return $options['usecoppa'];
			}

			$request = vB::getRequest();

			if (empty($request)) {
				// mainly happens in test- should never happen in production.
				$cutoff = strtotime(date("Y-m-d", time()) . '- 13 years');
			}else {
				$cutoff = strtotime(date("Y-m-d", vB::getRequest()->getTimeNow()) . '- 13 years');
			}

			if ($birthdate > $cutoff) {
				return $options['usecoppa'];
			}
		}
		return 0;
	}

	/** This preloads information for a list of userids, so it will be available for userContext and other data loading

	@param 	mixed	array of integers

	 */
	public function preloadUserInfo($userids)
	{
		if (empty($userids) OR !is_array($userids))
		{
			//no harm here. Just nothing to do.
			return;
		}
		$userids = array_unique($userids);

		//first we can remove anything that already has been loaded.
		$fastCache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$languageid = vB::getCurrentSession()->get('languageid');
		$cacheKeys = array();
		foreach ($userids AS $key => $userid)
		{
			//If we already have userinfo in cache we'll have the others
			$infoKey = "vb_UserInfo_$userid" . '_' . $languageid;

			if ($fastCache->read($infoKey))
			{
				unset($userids[$key]);
				continue;
			}
			//See if we have a cached version we can use.
			$cacheKeys[$key] = "vb_UserWPerms_$userid" . '_' . $languageid;
		}

		if (!empty($cacheKeys))
		{
			$cached = vB_Cache::instance(vB_Cache::CACHE_LARGE)->read($cacheKeys);
			foreach ($cacheKeys AS $key => $cachekey)
			{
				if (!empty($cached[$cachekey]) AND !empty($cached[$cachekey]['userid']))
				{
					$this->fetchUserWithPerms($cached[$cachekey]['userid'], $languageid);
					unset($userids[$key]);
				}
			}
		}

		if (!empty($userids))
		{
			$assertor = vB::getDbAssertor();
			//First get userinfo.
			$userQry = $assertor->assertQuery('user', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'userid' => $userids));

			if (!$userQry->valid())
			{
				return;
			}
			foreach($userQry AS $userInfo)
			{
				$userid = $userInfo['userid'];
				$primary_group_id = $userInfo['usergroupid'];
				$secondary_group_ids = (!empty($userInfo['membergroupids'])) ? explode(',', str_replace(' ', '', $userInfo['membergroupids'])) : array();
				$infraction_group_ids = (!empty($userInfo['infractiongroupids'])) ? explode(',', str_replace(' ', '', $userInfo['infractiongroupids'])) : array();
				$usergroups = array('groupid' => $primary_group_id, 'secondary' => $secondary_group_ids, 'infraction' => $infraction_group_ids);
				$fastCache->write("vb_UserInfo_$userid" . '_' . $languageid, $userInfo, 5, "userChg_$userid");
				$fastCache->write("vB_UserUG_$userid", $usergroups, 5, "userChg_$userid");
			}

			$git = array();
			$moderators = array();
			foreach($userids AS $userid)
			{
				$git[$userid] = array();
				$moderators[$userid] = array();
			}

			$gitQry = $assertor->assertQuery('vBForum:groupintopic', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'userid' => $userids) );

			if ($gitQry->valid())
			{
				foreach ($gitQry AS $group)
				{
					$git[$group['userid']][] = $group;
				}
			}
			foreach($git AS $userid => $group)
			{
				$fastCache->write("vB_UserGIT_$userid", $group, 5, array("userPerms_$userid", "userChg_$userid"));
			}

			$modQry = $assertor->assertQuery('vBForum:moderator', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'userid' => $userids) );

			if ($modQry->valid())
			{
				foreach ($modQry AS $moderator)
				{
					$moderators[$moderator['userid']][] = $moderator;
				}
			}
			foreach($moderators AS $userid => $moderator)
			{
				if (!empty($moderator))
				{
					$moderators[$userid] = $this->fetchModerator($userid, $moderator);
				}
			}

			foreach($moderators AS $userid => $moderator)
			{
				$fastCache->write("vB_UserMod_$userid", $moderator, 30, array("userPerms_$userid", "userChg_$userid"));
			}
		}
	}

	/** This method clears remembered channel permission
	*
	*	@param	int		the userid to be cleared
	*
	***/
	public function clearChannelPerms($userid)
	{
		unset($this->groupInTopic[$userid]);
	}

	public function updateEmailFloodTime()
	{
		$usercontext = vB::getCurrentSession()->fetch_userinfo();
		vB::getDbAssertor()->update('user', array("emailstamp" => vB::getRequest()->getTimeNow()), array("userid" => $usercontext['userid']));
		vB_Cache::instance(vB_CACHE::CACHE_LARGE)->event(array('userChg_' . $usercontext['userid']));
	}

	public function uploadAvatar($filename, $crop = array(), $userid = false)
	{
		$imageHandler = vB_Image::instance();
		$fileInfo = $imageHandler->fetchImageInfo($filename);
		if (!$fileInfo)
		{
			throw new vB_Exception_Api('upload_invalid_image');
		}
		if ($userid === false)
		{
			$userid = vB::getCurrentSession()->get('userid');
		}
		$usercontext = vB::getUserContext($userid);
		$pathinfo = empty($crop['org_file_info']) ? pathinfo($filename) : $crop['org_file_info'];
		$dimensions['src_width'] = $fileInfo[0];
		$dimensions['src_height'] = $fileInfo[1];
		if (empty($crop['width']) AND empty($crop['height']))
		{
			$crop['width'] = $dimensions['src_width'];
			$crop['height'] = $dimensions['src_height'];
		}
		$crop['width'] = min($crop['width'], $dimensions['src_width']);
		$crop['height'] = min($crop['height'], $dimensions['src_height']);
		// the crop area should be square
		$crop['width'] = $crop['height'] = min($crop['width'], $crop['height']);

		$maxwidth = $usercontext->getLimit('avatarmaxwidth');
		$maxheight = $usercontext->getLimit('avatarmaxheight');
		//see if we need to resize the cropped image (if the crop happened on a resized image)
		$resize_ratio = 1;
		if (!empty($crop['resized_width']) AND $crop['resized_width'] < $dimensions['src_width'])
		{
			$resize_ratio = $dimensions['src_height'] / $crop['resized_height'];
		}
		$dimensions['x1'] = round(empty($crop['x1']) ? 0 : ($crop['x1'] * $resize_ratio));
		$dimensions['y1'] = round(empty($crop['y1']) ? 0 : ($crop['y1'] * $resize_ratio));
		$dimensions['width'] = round((empty($crop['width']) ? $maxwidth : $crop['width']) * $resize_ratio);
		$dimensions['height'] = round((empty($crop['height']) ? $maxheight : $crop['height']) * $resize_ratio);

		$isCropped = ($dimensions['src_width'] > $dimensions['width'] OR $dimensions['src_height'] > $dimensions['height']);

		$ext = strtolower($fileInfo[2]);

		$dimensions['extension'] = empty($ext) ? $pathinfo['extension'] : $ext;
		$dimensions['filename'] = $filename;
		$dimensions['filedata'] = file_get_contents($filename);
		// Check max height and max weight from the usergroup's permissions
		$forceResize = false;
		// force a resize if the uploaded file has the right dimensions but the file size exceeds the limits
		if ($resize_ratio == 1 AND !$isCropped AND strlen($dimensions['filedata']) > $usercontext->getLimit('avatarmaxsize'))
		{
			$new_dimensions = $imageHandler->bestResize($dimensions['src_width'], $dimensions['src_height']);
			$crop['width'] = $new_dimensions['width'];
			$crop['height'] = $new_dimensions['height'];
			$forceResize = true;
		}

		$fileArray_cropped = $imageHandler->cropImg($dimensions, min(empty($crop['width']) ? $maxwidth : $crop['width'], $maxwidth), min(empty($crop['height']) ? $maxheight : $crop['height'], $maxheight), $forceResize);

		if(strpos(get_class($imageHandler),"GD") !== false)
		{
			$fileArray_thumb = $imageHandler->cropImg($dimensions,100,100);
		}
		else
		{
			$fileArray_thumb = $fileArray_cropped['thumb_dimensions'];
		}

		$extension_map = $imageHandler->getExtensionMap();

		if ($forceResize OR $maxwidth < $fileInfo[0] OR $maxwidth < $fileInfo[1])
		{
			$filearray = array(
					'size' => $fileArray_cropped['filesize'],
					'filename' => $filename,
					'name' => $pathinfo['filename'],
					'location' => $pathinfo['dirname'],
					'type' => 'image/' . $extension_map[strtolower($dimensions['extension'])],
					'filesize' => $fileArray_cropped['filesize'],
					'height' => $fileArray_cropped['height'],
					'width' => $fileArray_cropped['width'],
					'filedata_thumb' => $fileArray_thumb['filedata'],
					'filesize_thumb' => $fileArray_thumb['filesize'],
					'height_thumb' => $fileArray_thumb['height'],
					'width_thumb' => $fileArray_thumb['width'],
					'extension' => $dimensions['extension'],
					'filedata' => $fileArray_cropped['filedata']
			);
		}
		else
		{
			$filearray = array(
					'size' => strlen($dimensions['filedata']),
					'filename' => $filename,
					'name' => $pathinfo['filename'],
					'location' => $pathinfo['dirname'],
					'type' => 'image/' . $extension_map[strtolower($dimensions['extension'])],
					'filesize' => strlen($dimensions['filedata']),
					'height' => $fileInfo[1],
					'width' => $fileInfo[0],
					'filedata_thumb' => $fileArray_thumb['filedata'],
					'filesize_thumb' => $fileArray_thumb['filesize'],
					'height_thumb' => $fileArray_thumb['height'],
					'width_thumb' => $fileArray_thumb['width'],
					'extension' => $dimensions['extension'],
					'filedata' => $dimensions['filedata']
			);
		}
		$api = vB_Api::instanceInternal('user');
		$result = $api->updateAvatar($userid, false, $filearray,true);
		if (empty($result['errors']))
		{
			return $api->fetchAvatar($userid);
		}
		else
		{
			return $result;
		}
	}
}
