<?php

class vB5_Frontend_Controller_Profile extends vB5_Frontend_Controller
{
	/** Gets the default Avatars- echo's html
	 *
	 *
	 **/
	public function actionGetdefaultavatars()
	{
		$api = Api_InterfaceAbstract::instance();
		$avatars = $api->callApi('profile', 'getDefaultAvatars', array());
		$templater = new vB5_Template('defaultavatars');
		$templater->register('avatars', $avatars);
		$this->outputPage($templater->render());
	}

	/** gets the avatar url for a specific user.
	 *
	 **/
	public function actionGetAvatarUrl()
	{
		if (!empty($_REQUEST['userid']))
		{
			$api = Api_InterfaceAbstract::instance();
			$avatarUrl = $api->callApi('user', 'fetchAvatar', array('userid' => $_REQUEST['userid']));
			$this->outputPage($avatarUrl['avatarpath']);
		}
	}

	/** sets avatar to one of the defaults
	 *
	 **/
	public function actionSetDefaultAvatar()
	{
		if (!empty($_REQUEST['avatarid']))
		{
			$api = Api_InterfaceAbstract::instance();
			$avatarUrl = $api->callApi('user', 'setDefaultAvatar', array('avatarid' => $_REQUEST['avatarid']));
			$this->sendAsJson($avatarUrl);
		}
	}

	/** resets the avatar to the default/no avatar
	 *
	 **/
	public function actionResetAvatar()
	{
		$api = Api_InterfaceAbstract::instance();
		$avatarUrl = $api->callApi('profile', 'resetAvatar', array('profile'));
		$this->sendAsJson($avatarUrl);
	}


	/** uploads an image and sets it to be the avatar
	 *
	 **/
	public function actionUploadProfilepicture()
	{
		if ($_FILES AND !empty($_FILES['profilePhotoFile']))
		{
			$api = Api_InterfaceAbstract::instance();
			$response = $api->callApi('profile', 'upload', array('file' => $_FILES['profilePhotoFile'], 'data' => $_REQUEST));
		}
		elseif (!empty($_POST['filedataid']))
		{
			$api = Api_InterfaceAbstract::instance();
			$response = $api->callApi('profile', 'cropFileData', array('filedataid' => $_POST['filedataid'], 'data' => $_REQUEST));
		}
		elseif (!empty($_POST['profilePhotoUrl']))
		{
			$api = Api_InterfaceAbstract::instance();
			$response = $api->callApi('profile', 'uploadUrl', array('url' => $_POST['profilePhotoUrl'], 'data' => $_REQUEST));
		}
		elseif (!empty($_FILES['profilePhotoFull']))
		{
			$api = Api_InterfaceAbstract::instance();
			$response = $api->callApi('content_attach', 'uploadPhoto', array('file' => $_FILES['profilePhotoFull']));
			$response['imageUrl'] = vB5_Config::instance()->baseurl . '/filedata/fetch?filedataid=' . $response['filedataid'];
		}
		else
		{
			$response['errors'] = "No files to upload";
		}
		$this->sendAsJson($response);
	}

	/** Sets a filter and returns the filtered Activity list **/
	public function actionApplyfilter()
	{
		$filters = $_REQUEST['filters'];
		$result = array(
			'total' 	=> 0,
			'total_with_sticky' => 0,
			'template'	=> '',
			'resultId' => 0
		);

		$resultId = intval($filters['result-id']);
		$pagenumber = isset($filters['pagenum']) ? intval($filters['pagenum']) : false;
		$perpage = (isset($filters['per-page'])) ? intval($filters['per-page']) : false;
		$api = Api_InterfaceAbstract::instance();
		// if resultid
		if (!empty($resultId))
		{
			$nodes = $api->callApi('search', 'getMoreResults', array($resultId, 'perpage' => $perpage, 'pagenumber' => $pagenumber));
			$templater = new vB5_Template('profile_activity');
			$templater->register('nodes', $nodes['results']);
			$result['template'] = $templater->render();
			$result['total'] = $result['total_with_sticky'] = count($nodes['results']);
			$showSeeMore = ($nodes['totalpages'] > $pagenumber) ? true : false;
			$result['resultId'] = $nodes['resultId'];
			$result['pageinfo'] = array('pagenumber' => $pagenumber, 'totalpages' => $nodes['totalpages'], 'showseemore' => $showSeeMore);
			$this->sendAsJson($result);
		}
		else
		{
			//We need at least a userid
			if (empty($filters['userid']) OR !intval($filters['userid']))
			{
				$this->sendAsJson($result);
				return;
			}
			else
			{
				$searchJson = array('authorid' => $filters['userid'], 'view' => 'conversation_stream');
			}

			// source filter
			if (isset($filters['filter_source']))
			{
				switch ($filters['filter_source'])
				{
					case 'source_user':
						$searchJson['ignore_protected'] = 1;
						break;
					case 'source_vm':
						$searchJson['visitor_messages_only'] = 1;
						break;
					default:
						// source all
						$searchJson['include_visitor_messages'] = 1;
						break;
				}
			}

			if (!empty($filters['filter_show']) AND $filters['filter_show'] != 'show_all')
			{
				$searchJson['type'] = $filters['filter_show'];
			}

			if (!empty($filters['filter_time']))
			{
				switch ($filters['filter_time'])
				{
					case 'time_today':
						$searchJson['date']['from'] = 'lastDay';//vB_Api_Search::FILTER_LASTDAY
					break;
					case 'time_lastweek':
						$searchJson['date']['from'] = 'lastWeek';//vB_Api_Search::FILTER_LASTWEEK
					break;
					case 'time_lastmonth':
						$searchJson['date']['from'] = 'lastMonth';//vB_Api_Search::FILTER_LASTMONTH
					break;
					case 'time_lastyear':
						$searchJson['date']['from'] = 'lastYear';//vB_Api_Search::FILTER_LASTYEAR
					break;
					default:
					case 'time_all':
						$searchJson['date'] = 'all';
					break;
				}
			}
			else if (empty($filters['filter_time']) OR ($filters['filter_time'] == 'time_all'))
			{
				$searchJson['date'] = 'channelAge';
			}

			if (!empty($filters['exclude_visitor_messages']))
			{
				$searchJson['exclude_visitor_messages'] = 1;
				if (isset($searchJson['include_visitor_messages']))
				{
					unset($searchJson['include_visitor_messages']);
				}
			}

			$nodes = $api->callApi('search', 'getInitialResults', array('search_json' => $searchJson, 'perpage' => $perpage, 'pagenumber' => $pagenumber, 'getStarterInfo' => 1));
			$templater = new vB5_Template('profile_activity');
			$templater->register('nodes', $nodes['results']);
			$templater->register('userid', $filters['userid']);
			$userInfo = $api->callApi('user', 'fetchUserInfo', array());
			if (!empty($userInfo['userid']))
			{
				foreach ($nodes['results'] as $conversation)
				{
					if((!empty($conversation['setfor'])) AND ($userInfo['userid'] == $conversation['setfor']) AND (
							($conversation['content']['moderatorperms']['canmoderateposts'] > 0)
							OR ($conversation['content']['moderatorperms']['candeleteposts'] > 0)
							OR ($conversation['content']['moderatorperms']['caneditposts'] > 0)
							OR ($conversation['content']['moderatorperms']['canopenclose'] > 0)
							OR ($conversation['content']['moderatorperms']['canmassmove'] > 0)
							OR ($conversation['content']['moderatorperms']['canmassprune'] > 0)
							OR ($conversation['content']['moderatorperms']['canremoveposts'] > 0)
							OR ($conversation['content']['moderatorperms']['cansetfeatured'] > 0)
					))
					{
						$templater->register('showInlineMod', 1);
						break;
					}
				}
			}
			$result['template'] = $templater->render();
			$result['total'] = $result['total_with_sticky'] = count($nodes['results']);
			$result['resultId'] = $nodes['resultId'];
			$showSeeMore = ($nodes['totalpages'] > $pagenumber) ? true : false;
			$result['pageinfo'] = array('pagenumber' => $pagenumber, 'totalpages' => $nodes['totalpages'], 'showseemore' => $showSeeMore);
			$this->sendAsJson($result);
		}

	}

	public function actionGetMediaEditor()
	{
		$api = Api_InterfaceAbstract::instance();
		$parentid = $api->callApi('node', 'fetchAlbumChannel');
		$templater = new vB5_Template('editor_contenttype_Gallery');

		$templater->register('baseurl', vB5_Config::instance()->baseurl);
		$templater->register('ret', 'Not_needed');
		$templater->register('parentid', 4);
		$templater->register('ident', '_prof');
		$templater->register('routeid', 1);
		$this->outputPage($templater->render());
	}

	/** Get a list of the videos, galleries, and text with attachments **/
	public function actionGetmedia($userid)
	{
		$templater = new vB5_Template('profile_media');
		$api = Api_InterfaceAbstract::instance();
		$gallery = $api->callApi('profile', 'fetchAlbums', null);
		$templater->register('gallery', $gallery);
		$this->outputPage($templater->render());
	}

	/** create page content for file upload **/
	public function actionContentbox()
	{
		$templater = new vB5_Template('profile_mediaupload');
		$api = Api_InterfaceAbstract::instance();
		$this->outputPage($templater->render());
	}

	/** Add/delete following from user **/
	public function actionFollowButton()
	{
		if (!empty($_REQUEST['follower']) AND !empty($_REQUEST['type']) AND !empty($_REQUEST['do']))
		{
			$follower = $_REQUEST['follower'];
			$type = $_REQUEST['type'];
			$action = $_REQUEST['do'];

			$api = Api_InterfaceAbstract::instance();
			$response = $api->callApi('follow', $action, array('follower' => $follower, 'type' => $type));
			$this->sendAsJson($response);
		}
	}

	/** Fetches the info applying the filter criteria. **/
	public function actionFollowingFilter()
	{
		$result = array(
			'total' => 0,
			'total_with_sticky' => 0,
			'template' => '',
			'pagenavTemplate' => ''
		);

		$filters = $_REQUEST['filters'];
		$follower = $filters['userid'];
		if (empty($follower) OR !intval($follower))
		{
			$this->sendAsJson($result);
			return;
		}

		$type = (isset($filters['type']) AND !empty($filters['type'])) ? $filters['type'] : 'follow_all';
		$sortBy = ((isset($filters['filter_sort']) AND in_array($filters['filter_sort'], array('leastactive', 'mostactive', 'all')))) ? $filters['filter_sort'] : 'all';

		//pagination data
		$perPage = (isset($filters['per-page']) AND is_numeric($filters['per-page'])) ? $filters['per-page'] : 100;
		$page = (isset($filters['pagenum']) AND is_numeric($filters['pagenum'])) ? $filters['pagenum'] : 1;

		$api = Api_InterfaceAbstract::instance();
		$templater = new vB5_Template('subscriptions_one');

		try
		{
			$userInfo = $api->callApi('user', 'fetchUserInfo', array());
		}
		catch (Exception $e)
		{
			return $result;
		}

		if($follower == $userInfo['userid'])
		{
			$templater->register('showOwner', true);
			$response = $api->callApi('follow', 'getFollowingForCurrentUser', array('type' => $type, 'options' => array('page' => $page, 'perpage' => $perPage, 'filter_sort' => $sortBy)));
		}
		else
		{
			$params = array('userid' => $follower, 'type' => $type, 'filters' => array('filter_sort' => $sortBy), null, 'options' => array('page' => $page, 'perpage' => $perPage));
			$response = $api->callApi('follow', 'getFollowing', $params);
		}
		$templater->register('followings', $response['results']);
		$result['template'] = $templater->render();
		$result['total'] = $result['total_with_sticky'] = $response['paginationInfo']['totalcount'];
		$result['pageinfo'] = array('pagenumber' => $response['paginationInfo']['page'], 'totalpages' => $response['paginationInfo']['totalpages']);

		$this->sendAsJson($result);
	}

	/** Add/delete followers from user. **/
	public function actionFollowers()
	{
		if (!empty($_REQUEST['follower']) AND !empty($_REQUEST['do']))
		{
			$follower = $_REQUEST['follower'];
			$action = $_REQUEST['do'];
			$params = array('follower' => $follower);

			if (!empty($_REQUEST['type']) AND $_REQUEST['type'] == 'follower')
			{
				$action = $action . 'Follower';
			}
			$api = Api_InterfaceAbstract::instance();
			$response = $api->callApi('follow', $action, $params);

			$this->sendAsJson($response);
		}
	}

	/** Handles subscribers page pagination */
	public function actionFollowersPagination()
	{
		$result = array(
			'total' => 0,
			'total_with_sticky' => 0,
			'template' => '',
			'pagenavTemplate' => ''
		);

		if (empty($_REQUEST['follower']) OR !intval($_REQUEST['follower']))
		{
			$this->sendAsJson($result);
			return;
		}

		$follower = $_REQUEST['follower'];
		$sortBy = (isset($_REQUEST['filter_sort']) AND !empty($_REQUEST['filter_sort'])) ? $_REQUEST['filter_sort'] : 'all';
		$page = (isset($_REQUEST['page']) AND is_numeric($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
		$perPage = (isset($_REQUEST['perpage']) AND is_numeric($_REQUEST['perpage'])) ? $_REQUEST['perpage'] : 100;
		$api = Api_InterfaceAbstract::instance();
		$templater = new vB5_Template('subscriptions_two');

		try
		{
			$userInfo = $api->callApi('user', 'fetchUserInfo', array());
		}
		catch (Exception $e)
		{
			return $result;
		}

		if($follower == $userInfo['userid'])
		{
			$templater->register('showOwner', true);
			$response = $api->callApi('follow', 'getFollowersForCurrentUser', array('options' => array('page' => $page, 'perpage' => $perPage, 'filter_sort' => $sortBy)));
		}
		else
		{
			$response = $api->callApi('follow', 'getFollowers', array('userid' => $follower, 'options' => array('page' => $page, 'perpage' => $perPage, 'filter_sort' => $sortBy)));
		}

		// @TODO this isn't using conversation filter yet, but the code will be in place.
		$templater->register('followers', $response['results']);
		$result['template'] = $templater->render();
		$result['total'] = $result['total_with_sticky'] = $response['paginationInfo']['totalcount'];

		$templater = new vB5_Template('pagenavnew');
		$templater->register('pagenav', $response['paginationInfo']);
		$result['pagenavTemplate'] = $templater->render();

		$this->sendAsJson($result);
	}

	/** Fetches the nodes info applying the following filter criteria. **/
	public function actionApplyFollowingFilter()
	{
		$result = array(
			'lastDate'		  => 0,
			'total'			 => 0,
			'total_with_sticky' => 0,
			'template'		  => '',
		);

		$filters = $_REQUEST['filters'];
		$followerId = isset($filters['followerid']) ? intval($filters['followerid']) : intval(vB::getUserContext()->fetchUserId());

		if (!empty($followerId))
		{
			$followFilters = array();
			if (isset($filters['checkSince']) AND is_numeric($filters['checkSince']))
			{
				$followFilters['filter_time'] = $filters['checkSince'] + 1;
			}
			else
			{
				$followFilters['filter_time'] = isset($filters['filter_time']) ? $filters['filter_time'] : 'time_all';
			}
			$followFilters['filter_sort'] = isset($filters['filter_sort']) ? $filters['filter_sort'] : 'sort_recent';
			$typeFilter = isset($filters['filter_show']) ? $filters['filter_show'] : 'show_all';
			$followType = isset($filters['filter_follow']) ? $filters['filter_follow'] : 'follow_all';

			// Now we set the user options
			$options = array(
				'perpage' => isset($filters['per-page']) ? intval($filters['per-page']) : 20
			);

			if (isset($filters['pagenum']) AND !empty($filters['pagenum']))
			{
				$options['page'] = intval($filters['pagenum']);
			}
			if (isset($filters['nodeid']) AND !empty($filters['nodeid']))
			{
				$options['parentid'] = intval($filters['nodeid']);
			}

			$contentTypeClass = ($typeFilter AND strcasecmp($typeFilter, 'show_all') != 0) ? $typeFilter : '';

			$api = Api_InterfaceAbstract::instance();
			$resultNodes = $api->callApi(
				'follow',
				'getFollowingContentForTab',
				array(
					'userid'			=> $followerId,
					'type'				=> $followType,
					'filters'			=> $followFilters,
					'contenttypeclass'	=> $contentTypeClass,
					'options'			=> $options
			));

			$templater = new vB5_Template('profile_following');
			$templater->register('nodes', $resultNodes['nodes']);
			$templater->register('showChannelInfo', $filters['showChannelInfo']);
			$result['template'] = $templater->render();
			foreach($resultNodes['nodes'] AS $nodeid => $node)
			{
				$result['lastDate'] = max($result['lastDate'], $node['content']['publishdate']);
			}

			$result['total'] = $result['total_with_sticky'] = $resultNodes['totalcount'];
			$result['pageinfo'] = array('pagenumber' => $resultNodes['paginationInfo']['currentpage'], 'showseemore' => $resultNodes['paginationInfo']['showseemore']);
		}
		$this->sendAsJson($result);
	}

	/**
	 * Save profile settings from user
	 */
	public function actionSaveProfileSettings()
	{
		$userId = intval($_REQUEST['userid']);
		if ($userId > 0)
		{
			$api = Api_InterfaceAbstract::instance();

			// usertitle might not be in settings
			if (isset($_POST['usertitle']))
			{
				$userInfo['customtitle'] = (isset($_POST['resettitle'])) ? 0 : 1;
				$userInfo['usertitle'] = isset($_POST['usertitle']) ? $_POST['usertitle'] : '';
			}
			if(!empty($_POST['bd_year']) AND !empty($_POST['bd_month']) AND !empty($_POST['bd_day']))
			{
				$userInfo['birthday_search'] = implode('-', array($_POST['bd_year'], $_POST['bd_month'], $_POST['bd_day']));

				// default option would be 2
				$userInfo['showbirthday'] = isset($_POST['dob_display']) ? $_POST['dob_display'] : 2;

				/**
				* @TODO Birthday would be in english format for the moment.
				*/
				$userInfo['birthday'] = implode('-', array($_POST['bd_month'], $_POST['bd_day'], $_POST['bd_year']));
			}
			else{
				$userInfo['birthday'] = "";
			}
			$userInfo['homepage'] = isset($_POST['homepage']) ? $_POST['homepage'] : '';
			$_POST['user_im_providers'] = isset($_POST['user_im_providers']) ? $_POST['user_im_providers'] : array();
			foreach(array('icq', 'aim', 'yahoo', 'msn', 'skype', 'google') as $value)
			{
				$key = array_search($value, $_POST['user_im_providers']);
				$empty = true;
				// if valid provider is set then...
				if (($key !== false) AND ((isset($_POST['user_screennames'][$key])) AND (!empty($_POST['user_screennames'][$key]))))
				{
					$userInfo[strtolower($value)] = $_POST['user_screennames'][$key];
					$empty = false;
				}

				if ($empty)
				{
					$userInfo[strtolower($value)] = '';
				}
			}

			$userFields = array();
			$response = $api->callApi('user', 'fetchUserProfileFields', array());
			foreach ($response AS $uField)
			{
				$userFields[$uField] = isset($_POST[$uField]) ? $_POST[$uField] : '';
			}

			$response = $api->callApi('user', 'save', array(
					'userid' => $userId,
					'password' => '',
					'user' => $userInfo,
					'options' => array(),
					'adminoptions' => array(),
					'userfield' => $userFields
				)
			);

			$this->sendAsJson(array('response' => $response));
		}
	}

	/**
	 * Save account settings from user
	 */
	function actionSaveAccountSettings()
	{
		$userId = intval($_REQUEST['userid']);

		if ($userId > 0)
		{
			$extra = array(
				'email' => '',
				'newpass' => '',
				'password' => '',
				'acnt_settings' => 1
			);

			$api = Api_InterfaceAbstract::instance();

			// drag userinfo from post
			$userInfo = array();
			$userInfo['threadedmode'] = (isset($_POST['display_mode']) ? $_POST['display_mode'] : 0);
			$userInfo['maxposts'] = (isset($_POST['posts_per_page']) AND $_POST['posts_per_page'] != -1) ? $_POST['posts_per_page'] : 0;
			$userInfo['timezoneoffset'] = (isset($_POST['timezone'])) ? $_POST['timezone'] : '';
			$userInfo['startofweek'] = (isset($_POST['startofweek'])) ? $_POST['startofweek'] : -1;
			$userInfo['styleid'] = (isset($_POST['forum_skin'])) ? $_POST['forum_skin'] : 0;
			$userInfo['languageid'] = (isset($_POST['languageid'])) ? $_POST['languageid'] : 0;
			$userInfo['ignorelist'] = (isset($_POST['ignorelist'])) ? $_POST['ignorelist'] : '';
			$userInfo['showvbcode'] = (isset($_POST['showvbcode'])) ? $_POST['showvbcode'] : '';

			// Pass current password if set
			if (isset($_POST['current_pass'])
				AND !empty($_POST['current_pass'])
			)
			{
				$extra['password'] = $_POST['current_pass'];
			}

			// Check new e-mails match, and are not blank
			if (isset($_POST['new_email'])
				AND isset($_POST['new_email2'])
				AND !empty($_POST['new_email'])
				AND ($_POST['new_email'] == $_POST['new_email2'])
			)
			{
				$extra['email'] = $_POST['new_email'];
			}

			// Check new passwords match, and are not blank
			if (isset($_POST['new_pass'])
				AND isset($_POST['new_pass2'])
				AND !empty($_POST['new_pass'])
				AND $_POST['new_pass'] == $_POST['new_pass2']
			)
			{
				$extra['newpass'] = $_POST['new_pass'];
			}

			// and options
			$options = array();
			$options['invisible'] = (isset($_POST['invisible_mode'])) ? true : false;
			$options['showreputation'] = (isset($_POST['show_reputation'])) ? true : false;
			$options['showvcard'] = (isset($_POST['vcard_download'])) ? true : false;
			$options['receivepm'] = (isset($_POST['enable_pm'])) ? true : false;
			$options['receivepmbuddies'] = (isset($_POST['receive_pm']) AND $_POST['receive_pm'] == 'buddies') ? true : false;
			$options['vm_enable'] = (isset($_POST['enable_vm'])) ? true : false;
			$options['showusercss'] = (isset($_POST['other_customizations'])) ? true : false;
			$options['showavatars'] = (isset($_POST['showavatars'])) ? true : false;
			$options['showimages'] = (isset($_POST['showimages'])) ? true : false;
			$options['showsignatures'] = (isset($_POST['showsignatures'])) ? true : false;
			$options['adminemail'] = (isset($_POST['adminemail'])) ? true : false;

			if (isset($_POST['dst_correction']))
			{
				if ($_POST['dst_correction'] == 2)
				{
					$options['dstauto'] = true;
					$options['dstonoff'] = false;
				}
				else if($_POST['dst_correction'] == 1)
				{
					$options['dstauto'] = false;
					$options['dstonoff'] = true;
				}
				else
				{
					$options['dstauto'] = false;
					$options['dstonoff'] = false;
				}
			}

			$response = $api->callApi('user', 'save', array(
					'userid' => $userId,
					'password' => '', // Passed via $extra
					'user' => $userInfo,
					'options' => $options,
					'adminoptions' => array(),
					'userfield' => array(),
					'notificationOptions' => array(),
					'hvinput' => array(),
					'extra' => $extra,
				)
			);

			$this->sendAsJson(array('response' => $response));
		}
	}

	public function actionToggleProfileCustomizations()
	{
		$options['showusercss'] = !empty($_POST['showusercss']) ? true : false;
		$response = Api_InterfaceAbstract::instance()->callApi('user', 'save', array(
				'userid' => -1,
				'password' => '', // Passed via $extra
				'user' => array(),
				'options' => $options,
				'adminoptions' => array(),
				'userfield' => array(),
			)
		);

		$this->sendAsJson(array('response' => $response));

	}

	public function actionSaveNotificationSettings()
	{
		$userId = intval($_REQUEST['userid']);
		if ($userId > 0)
		{
			//notification settings
			$userInfo = array();
			$notificationOptions = array();

			$userInfo['autosubscribe'] = isset($_POST['email_notification']) ? $_POST['email_notification'] : 0;
			$settings = array(
				'general_followsyou', 'general_followrequest', 'general_vm', 'general_voteconvs', 'general_likespost',
				'discussions_on', 'discussion_comment'
			);
			foreach ($settings as $setting)
			{
				$notificationOptions[$setting] = isset($_POST['notificationSettings'][$setting]) ? true : false;
			}

			$api = Api_InterfaceAbstract::instance();
			$response = $api->callApi('user', 'save', array(
					'userid' => $userId,
					'password' => '',
					'user' => $userInfo,
					'options' => array(),
					'adminoptions' => array(),
					'userfield' => array(),
					'notificationOptions' => $notificationOptions
				)
			);

			$url = vB5_Config::instance()->baseurl . '/settings/notifications';
			if (is_array($response) AND array_key_exists('errors', $response))
			{
				$message = $api->callApi('phrase', 'fetch', array('phrases' => $response['errors'][0][0]));

				vB5_ApplicationAbstract::handleFormError(array_pop($message), $url);

			}
			else
			{
				// and get back to settings
				header('Location: ' . $url);
			}
		}
	}

	public function actionSavePrivacySettings()
	{
		$userId = intval($_REQUEST['userid']);
		if ($userId > 0)
		{
			// privacy settings
			$options = array();
			$userInfo = array('privacy_options' => $_POST['privacyOptions']);
			$tempOptions = array();
			$options['moderatefollowers'] = isset($_POST['follower_request']) ? false : true;

			$api = Api_InterfaceAbstract::instance();
			$response = $api->callApi('user', 'save', array(
					'userid' => $userId,
					'password' => '',
					'user' => $userInfo,
					'options' => $options,
					'adminoptions' => array(),
					'userfield' => array()
				)
			);

			$url = vB5_Config::instance()->baseurl . '/settings/privacy';
			if (is_array($response) AND array_key_exists('errors', $response))
			{
				$message = $api->callApi('phrase', 'fetch', array('phrases' => $response['errors'][0][0]));

				vB5_ApplicationAbstract::handleFormError(array_pop($message), $url);

			}
			else
			{
				// and get back to settings
				header('Location: ' . $url);
			}
		}
	}

	public function actionUpdateStatus()
	{
		$userId = intval($_REQUEST['userid']);
		if ($userId > 0)
		{
			$status = (isset($_REQUEST['status'])) ? $_REQUEST['status'] : '';
			$api = Api_InterfaceAbstract::instance();
			$response = $api->callApi('user', 'updateStatus', array(
					'userId' => $userId,
					'status' => $status
				)
			);

			$this->sendAsJson($response);
		}
	}

	/** Filter & sort media list
	*
	***/
	public function actionApplyMediaFilter()
	{
		if ( empty($_REQUEST['userid']))
		{
			return '';
		}
		$templater = new vB5_Template('profile_media_content');
		$userId = intval($_REQUEST['userid']);
		$api = Api_InterfaceAbstract::instance();

		if (isset($_REQUEST['perpage']) AND intval($_REQUEST['perpage']))
		{
			$perpage = intval($_REQUEST['perpage']);
		}
		else
		{
			$perpage = 10;
		}

		if (isset($_REQUEST['page']) AND intval($_REQUEST['page']))
		{
			$page = intval($_REQUEST['page']);
		}
		else
		{
			$page = 1;
		}

		$gallery = $api->callApi('profile', 'fetchMedia', array('userid' => $_REQUEST['userid'],
			'page' => $page, 'perpage' => $perpage, 'params' => $_REQUEST));
		$templater->register('gallery', $gallery);
		$userInfo = $api->callApi('user', 'fetchUserinfo', array('userid' => $_REQUEST['userid']));
		$templater->register('userInfo', $userInfo);
		$this->outputPage($templater->render());
	}

	public function actionGetUnsubscribeOverlay()
	{
		$userId = $_REQUEST['userId'];
		if (intval($userId))
		{
			$isFollowingContent = isset($_REQUEST['content']) ? intval($_REQUEST['content']) : 0;
			$isFollowingMember = isset($_REQUEST['member']) ? intval($_REQUEST['member']) : 0;
			$isFollowingChannel = isset($_REQUEST['channel']) ? intval($_REQUEST['channel']) : 0;
			$nodeId = isset($_REQUEST['nodeId']) ? intval($_REQUEST['nodeId']) : 0;

			$templater = new vB5_Template('profile_following_unsubscribe');
			$templater->register('isFollowingContent', $isFollowingContent);
			$templater->register('isFollowingMember', $isFollowingMember);
			$templater->register('isFollowingChannel', $isFollowingChannel);
			$templater->register('nodeId', $nodeId);
			$this->outputPage($templater->render());
		}
	}

	/** Show a single text detail page.
	 *
	 ***/
	public function actiontextDetail()
	{
		if ( empty($_REQUEST['nodeid']))
		{
			return '';
		}
		$templater = new vB5_Template('profile_textphotodetail');
		$userId = intval($_REQUEST['nodeid']);
		$api = Api_InterfaceAbstract::instance();

		$node = $api->callApi('content_text', 'getFullContent', array('nodeid' => $_REQUEST['nodeid']));
		$templater->register('node', $node);
		$this->outputPage($templater->render());
	}

	/** Saves profile customization
	 *
	 ***/
	public function actionsaveStylevar()
	{
		$userId = intval($_POST['userid']);
		$result = array();

		if ($userId < 1)
		{
			$result['error'][] = 'logged_out_while_editing_post';
		}

		if (!isset($_POST['stylevars']) OR (isset($_POST['stylevars']) AND empty($_POST['stylevars'])))
		{
			$result['error'][] = 'there_are_no_changes_to_save';
		}

		if (!isset($result['error']))
		{
			$api = Api_InterfaceAbstract::instance();

			$result = $api->callApi('stylevar', 'save', array('stylevars' => $_POST['stylevars']));
		}

		$this->sendAsJson($result);
	}

	/** Get default stylevar values
	 *
	 ***/
	public function actionrevertStylevars()
	{
		$userId = intval($_POST['userid']);
		$result = array();

		if ($userId < 1)
		{
			$result['error'][] = 'logged_out_while_editing_post';
		}

		if (!isset($_POST['stylevars']) OR (isset($_POST['stylevars']) AND empty($_POST['stylevars'])))
		{
			$result['error'][] = 'there_are_no_changes';
		}

		if (!isset($result['error']))
		{
			$api = Api_InterfaceAbstract::instance();

			if (count($_POST['stylevars']) == 1)
			{
				$result = $api->callApi('stylevar', 'get', array('stylevarname' => $_POST['stylevars'][0]));
			}
			else
			{
				$result = $api->callApi('stylevar', 'fetch', array('stylevars' => $_POST['stylevars']));
			}
		}

		$this->sendAsJson($result);
	}

	/** Save current style as default for the site
	 *
	 ***/
	public function actionsaveDefault()
	{
		$userId = intval($_POST['userid']);
		$result = array();

		if ($userId < 1)
		{
			$result['error'][] = 'logged_out_while_editing_post';
		}

		$api = Api_InterfaceAbstract::instance();

		if (!$api->callApi('stylevar', 'canSaveDefault'))
		{
			$result['error'][] = 'no_permission_styles';
		}

		if (!isset($result['error']))
		{
			$stylevars = $api->callApi('stylevar', 'fetch', array('stylevars' => false));

			if (isset($_POST['stylevars']) AND is_array($_POST['stylevars']))
			{
				foreach ($_POST['stylevars'] as $stylevarid => $value)
				{
					$styelvars[$stylevarid] = $value;
				}
			}

			$result = $api->callApi('stylevar', 'save_default', array('stylevars' => $stylevars));
		}

		$this->sendAsJson($result);
	}

	/** Resetting the user changed stylevars to default values
	 *
	 ***/
	public function actionresetDefault()
	{
		$result = array();
		$userId = intval($_POST['userid']);

		if ($userId < 1)
		{
			$result['error'][] = array('logged_out_while_editing_post');
		}

		if (!isset($result['error']))
		{
			$api = Api_InterfaceAbstract::instance();

			// Fetching all user changed stylevars
			$user_stylevars = $api->callApi('stylevar', 'fetch_user_stylevars');
			$changed_stylevars = array_keys($user_stylevars);

			// Deleteing userstylevars
			$api->callApi('stylevar', 'delete', array('stylevars' => $changed_stylevars));

			// To revert unsaved changes
			if (isset($_POST['stylevars']) AND is_array($_POST['stylevars']))
			{
				$changed_stylevars = array_merge($changed_stylevars, $_POST['stylevars']);
				$changed_stylevars = array_unique($changed_stylevars);
			}

			$result = $api->callApi('stylevar', 'fetch', array('stylevars' => $changed_stylevars));
		}

		$this->sendAsJson($result);
	}

	/** Fetch the tab info for the photo selector
	 *
	 ***/
	public function actiongetPhotoTabs()
	{
		$result = array();
		$userid = intval($_POST['userid']);

		$api = Api_InterfaceAbstract::instance();

		$tabsInfo = $api->callApi('profile', 'fetchMedia', array(array('userId' => $userid), 1, 12, array('type' => 'photo')));

		if (empty($tabsInfo['count']))
		{
			$tabsInfo['error'] = 'no_photos_or_albums';
		}

		$this->sendAsJson($tabsInfo);
	}

	/** Fetch the photo tab content for the photo selector
	 *
	 ***/
	public function actiongetPhotoTabContent()
	{
		$userid = isset($_GET['userid']) ? intval($_GET['userid']) : 0;
		$nodeid = isset($_GET['nodeid']) ? intval($_GET['nodeid']) : 0;
		$nodeid = ($nodeid ? $nodeid : -2);
		$photosPerRow = isset($_GET['ppr']) ? intval($_GET['ppr']) : 0;
		$tabContent = "";

		$api = Api_InterfaceAbstract::instance();
		$nodes = $api->callApi('profile', 'getAlbum', array(
			array(
				'nodeid' => $nodeid,
				'page' => 1,
				'perpage' => 60,
				'userid' => $userid
			)
		));

		foreach ($nodes as $nodeid => $node)
		{
			$items = array();
			$photoFiledataids = array();
			$attachFiledataids = array();
			$photoCount = 0;

			foreach ($node['photo'] as $photoid => $photo)
			{
				// if it's an attachment, we use the 'id=' param. If it's a photo, 'photoid='
				$paramname = ($photo['isAttach']) ? 'id' : 'photoid';
				$items[$photoid] = array(
					'title' => $photo['title'],
					'imgUrl' => vB5_Config::instance()->baseurl . '/filedata/fetch?' . $paramname . '=' . $photoid . '&type=thumb',
				);

				if (!isset($photo['filedataid']) OR !$photo['filedataid'])
				{
					if($photo['isAttach'])
					{
						$attachFiledataids[] = $photoid;
					}
					else
					{
						$photoFiledataids[] = $photoid;
					}
				}
				else
				{
					$items[$photoid]['filedataid'] = $photo['filedataid'];
				}
				
				if ($photosPerRow AND ++$photoCount % $photosPerRow == 0)
				{						
					$items[$photoid]['lastinrow'] = true;
				}
			}

			if (!empty($photoFiledataids))
			{
				$photoFileids = $api->callApi('filedata', 'fetchPhotoFiledataid', array($photoFiledataids));

				foreach ($photoFileids as $nodeid => $filedataid)
				{
					$items[$nodeid]['filedataid'] = $filedataid;
				}
			}

			if (!empty($attachFiledataids))
			{
				$attachFileids = $api->callApi('filedata', 'fetchAttachFiledataid', array($attachFiledataids));

				foreach ($attachFileids as $nodeid => $filedataid)
				{
					$items[$nodeid]['filedataid'] = $filedataid;
				}
			}

			$templater = new vB5_Template('photo_item');
			$templater->register('items', $items);
			$templater->register('photoSelector', 1);
			$tabContent = $templater->render();
		}

		$this->outputPage($tabContent);
	}

	public function actionPreviewSignature()
	{
		$parser = new vB5_Template_BbCode();
		$userInfo = Api_InterfaceAbstract::instance()->callApi('user', 'fetchUserInfo', array());
		$sigInfo =  Api_InterfaceAbstract::instance()->callApi('user', 'fetchSignature', array($userInfo['userid']));
		$signature = empty($_REQUEST['signature']) ? $sigInfo['raw'] : $_REQUEST['signature'];
		$signature = $parser->doParse($signature, $sigInfo['permissions']['dohtml'], $sigInfo['permissions']['dosmilies'],
				$sigInfo['permissions']['dobbcode'], $sigInfo['permissions']['dobbimagecode']);
		$this->sendAsJson($signature);
	}

}
