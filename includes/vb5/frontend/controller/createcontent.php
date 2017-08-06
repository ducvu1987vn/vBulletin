<?php

class vB5_Frontend_Controller_CreateContent extends vB5_Frontend_Controller
{
	function __construct()
	{
		parent::__construct();
	}

	function index()
	{
		$input = array(
			'title' => (isset($_POST['title']) ? trim(strval($_POST['title'])) : ''),
			'text' => (isset($_POST['text']) ? trim(strval($_POST['text'])) : ''),
			'nodeid' => (isset($_POST['nodeid']) ? trim(intval($_POST['nodeid'])) : 0),
			'parentid' => (isset($_POST['parentid']) ? trim(intval($_POST['parentid'])) : 0),
			'channelid' => (isset($_POST['channelid']) ? trim(intval($_POST['channelid'])) : 0),
			'ret' => (isset($_POST['ret']) ? trim(strval($_POST['ret'])) : ''),
			'tags' => (isset($_POST['tags']) ? $_POST['tags'] : ''),
			'reason' => (isset($_POST['reason']) ? trim(strval($_POST['reason'])) : ''), //used in editing a post
			'iconid' => (isset($_POST['iconid']) ? intval($_POST['iconid']) : 0),
			'prefixid' => (isset($_POST['prefixid']) ? trim(strval($_POST['prefixid'])) : ''),
			'hvinput' => (isset($_POST['humanverify']) ? $_POST['humanverify'] : ''),
			'enable_comments' => (isset($_POST['enable_comments']) ? (bool)$_POST['enable_comments'] : false), // Used only when entering blog posts
		);
		if (!empty($_POST['setfor']))
		{
			$input['setfor'] = $_POST['setfor'];
		}
		if (!empty($_POST['recaptcha_challenge_field']))
		{
			// reCaptcha fields
			$input['hvinput']['recaptcha_challenge_field'] = $_POST['recaptcha_challenge_field'];
			$input['hvinput']['recaptcha_response_field'] = $_POST['recaptcha_response_field'];
		}

		//@TODO: There is no title for posting a reply or comment but api throws an error if blank. Fix this.

		$api = Api_InterfaceAbstract::instance();

		// get user info for the currently logged in user
		$user  = $api->callApi('user', 'fetchUserinfo', array());

		$time = vB5_Request::get('timeNow');
		$tagRet = false;

		if ($input['nodeid'])
		{
			$result = array();
			if ($user['userid'] < 1)
			{
				$result['error'] = 'logged_out_while_editing_post';
				$this->sendAsJson($result);
				exit;
			}

			$textData = array(
				'title'           => $input['title'],
				'parentid'        => $input['parentid'],
				'rawtext'         => $input['text'],
				'iconid'          => $input['iconid'],
				'prefixid'        => $input['prefixid'],
				'reason'          => $input['reason'], //@TODO
				'enable_comments' => $input['enable_comments'],
			);

			$options = array();

			// We need to convert WYSIWYG html here and run the img check
			if (isset($textData['rawtext']))
			{
				$tmpText = $api->callApi('bbcode', 'convertWysiwygTextToBbcode', array($textData['rawtext'], $options));
				// Check Images
				if (($phrase = vB5_Frontend_Controller_Bbcode::verifyImgCheck($tmpText)) !== true)
				{
					$results['error'] = $phrase;
					$this->sendAsJson($results);
					return;
				}
			}
			$updateRet = $api->callApi('content_text', 'update', array($input['nodeid'], $textData, $options));
			$this->handleErrorsForAjax($result, $updateRet);

			//update tags
			$tags = !empty($input['tags']) ? explode(',', $input['tags']) : array();
			$tagRet = $api->callApi('tags', 'updateUserTags', array($input['nodeid'], $tags));
			$this->handleErrorsForAjax($result, $tagRet);

			$this->sendAsJson($result);
		}
		else
		{
			$result = array();
			$textData = array(
				'title' => $input['title'],
				'parentid' => $input['parentid'],
				'rawtext' => $input['text'],
				'userid' => $user['userid'],
				'authorname' => $user['username'],
				'created' => $time,
				'iconid' => $input['iconid'],
				'prefixid' => $input['prefixid'],
				'publishdate' => $api->callApi('content_text', 'getTimeNow', array()),
				'hvinput' => $input['hvinput'],
				'enable_comments' => $input['enable_comments'],
			);

			if (!empty($_POST['setfor']))
			{
				$textData['setfor'] = intval($_POST['setfor']);
			}

			$options = array(
				'facebook' => $this->getFacebookOptionsForAddNode(),
			);

			// We need to convert WYSIWYG html here and run the img check
			if (isset($textData['rawtext']))
			{
				$tmpText = $api->callApi('bbcode', 'convertWysiwygTextToBbcode', array($textData['rawtext'], $options));
				if (($phrase = vB5_Frontend_Controller_Bbcode::verifyImgCheck($tmpText)) !== true)
				{
					$results['error'] = $phrase;
					$this->sendAsJson($results);
					return;
				}
			}

			$nodeId = $api->callApi('content_text', 'add', array($textData, $options));
			$this->handleErrorsForAjax($result, $nodeId);

			if (!is_int($nodeId) OR $nodeId < 1)
			{
				$this->handleErrorsForAjax($result, $nodeId);
				$this->sendAsJson($result);
				exit();
				/*
				if (!empty($nodeId['errors']) AND in_array('postfloodcheck', $nodeId['errors'][0]))
				{
					$message = vB5_Template_Phrase::instance()->getPhrase('searchfloodcheck', $nodeId['errors'][0][1], $nodeId['errors'][0][2]);

				}
				else
				{
					// @todo: catch this problem more gracefully.
					// DO NOT remove this exception unless you are adding code to
					// actually handle the problem and display a user-friendly error
					// We do not want to "hide" problems when content cannot be created
					$message ="Node cannot be created";
					$config = vB5_Config::instance();

					if ($config->debug)
					{
						$message .= "<br />\n" . var_export($nodeId, true);
					}
				}

				throw new Exception($message);
				*/
			}

			if (!empty($input['tags']))
			{
				$tagRet = $api->callApi('tags', 'addTags', array($nodeId, $input['tags']));
				$this->handleErrorsForAjax($result, $tagRet);
			}

			if (isset($_POST['filedataids']))
			{
				foreach ($_POST['filedataids'] as $filedataid)
				{
					if (intval($filedataid))
					{
						$data = array('filedataid' => $filedataid);
						$fieldname = "filename_$filedataid";
						if (!empty($_POST[$fieldname]))
						{
							$data['filename'] = $_POST[$fieldname];
						}

						$attRes = $api->callApi('node', 'addAttachment', array($nodeId, $data));
						$this->handleErrorsForAjax($result, $attRes);
					}
				}
			}

			$this->getReturnUrl($result, $input['channelid'], $input['parentid'], $nodeId);
			$result['nodeId'] = $nodeId;

			$this->sendAsJson($result);
		}
		exit;
	}

	function actionPoll()
	{
		$api = Api_InterfaceAbstract::instance();
		$offset = $api->callApi('user', 'fetchTimeOffset', array());

		$input = array(
			'title'           => (isset($_POST['title']) ? trim(strval($_POST['title'])) : ''),
			'text'            => (isset($_POST['text']) ? trim(strval($_POST['text'])) : ''),
			'polloptions'     => (array)$_POST['polloptions'],
			'parentid'        => (isset($_POST['parentid']) ? trim(intval($_POST['parentid'])) : 0),
			'nodeid'          => (isset($_POST['nodeid']) ? trim(intval($_POST['nodeid'])) : 0),
			'ret'             => (isset($_POST['ret']) ? trim(strval($_POST['ret'])) : ''),
			'timeout'         => (isset($_POST['timeout']) ? intval(strtotime(trim(strval($_POST['timeout'])))) - $offset : 0),
			'multiple'        => (isset($_POST['multiple'])? (boolean)$_POST['multiple'] : false),
			'public'          => (isset($_POST['public'])? (boolean)$_POST['public'] : false),
			'parseurl'        => (isset($_POST['parseurl']) ? (boolean)$_POST['parseurl'] : false),
			'tags'            => (isset($_POST['tags']) ? $_POST['tags'] : ''),
			'iconid'          => (isset($_POST['iconid']) ? intval($_POST['iconid']) : 0),
			'prefixid'        => (isset($_POST['prefixid']) ? trim(strval($_POST['prefixid'])) : ''),
			'hvinput'         => (isset($_POST['humanverify']) ? $_POST['humanverify'] : ''),
			'enable_comments' => (isset($_POST['enable_comments']) ? (bool)$_POST['enable_comments'] : false), // Used only when entering blog posts
			'reason'          => (isset($_POST['reason']) ? trim(strval($_POST['reason'])) : ''), //used in editing a post
		);
		if (!empty($_POST['recaptcha_challenge_field']))
		{
			// reCaptcha fields
			$input['hvinput']['recaptcha_challenge_field'] = $_POST['recaptcha_challenge_field'];
			$input['hvinput']['recaptcha_response_field'] = $_POST['recaptcha_response_field'];
		}

		// Poll Options
		$polloptions = array();
		foreach ($input['polloptions'] as $k => $v)
		{
			if ($v)
			{
				if ($k == 'new')
				{
					foreach ($v as $v2)
					{
						$v2 = trim(strval($v2));
						if ($v2 !== '')
						{
							$polloptions[]['title'] = $v2;
						}
					}
				}
				else
				{
					$polloptions[] = array(
						'polloptionid' => intval($k),
						'title' => trim($v),
					);
				}
			}
		}

		// get user info for the currently logged in user
		$user  = $api->callApi('user', 'fetchUserinfo', array());

		if ($input['nodeid'])
		{
			$pollData = array(
				'title'           => $input['title'],
				'rawtext'         => $input['text'],
				'parentid'        => $input['parentid'],
//				'userid'          => $user['userid'],
				'options'         => $polloptions,
				'multiple'        => $input['multiple'],
				'public'          => $input['public'],
				'parseurl'        => $input['parseurl'],
				'timeout'         => $input['timeout'],
				'iconid'          => $input['iconid'],
				'prefixid'        => $input['prefixid'],
				'enable_comments' => $input['enable_comments'],
				'reason'          => $input['reason'],
			);

			$nodeId = $api->callApi('content_poll', 'update', array($input['nodeid'], $pollData));

			//update tags
			$tags = !empty($input['tags']) ? explode(',', $input['tags']) : array();
			$tagRet = $api->callApi('tags', 'updateUserTags', array($input['nodeid'], $tags));
		}
		else
		{
			$result = array();
			$time = vB5_Request::get('timeNow');
			$pollData = array(
				'title'           => $input['title'],
				'rawtext'         => $input['text'],
				'parentid'        => $input['parentid'],
				'userid'          => $user['userid'],
				'authorname'      => $user['username'],
				'created'         => $time,
				'publishdate'     => $time,
				'options'         => $polloptions,
				'multiple'        => $input['multiple'],
				'public'          => $input['public'],
				'parseurl'        => $input['parseurl'],
				'timeout'         => $input['timeout'],
				'prefixid'        => $input['prefixid'],
				'hvinput'         => $input['hvinput'],
				'enable_comments' => $input['enable_comments'],
			);

			$options = array(
				'facebook' => $this->getFacebookOptionsForAddNode(),
			);

			$nodeId = $api->callApi('content_poll', 'add', array($pollData, $options));

			if (!is_int($nodeId))
			{
				$this->handleErrorsForAjax($result, $nodeId);
				$this->sendAsJson($result);
				exit;
			}

			if (!empty($input['tags']))
			{
				$tagRet = $api->callApi('tags', 'addTags', array($nodeId, $input['tags']));
			}

			if (isset($_POST['filedataids']))
			{
				foreach ($_POST['filedataids'] as $filedataid)
				{
					if (intval($filedataid))
					{
						$data = array('filedataid' => $filedataid);
						$fieldname = "filename_$filedataid";
						if (!empty($_POST[$fieldname]))
						{
							$data['filename'] = $_POST[$fieldname];
						}

						$attRes = $api->callApi('node', 'addAttachment', array($nodeId, $data));
						$this->handleErrorsForAjax($result, $attRes);
					}
				}
			}

			//redirect to the conversation detail page of the newly created Poll starter
			$node = $api->callApi('node', 'getNode', array($nodeId));
			$this->handleErrorsForAjax($result, $nodeId);
			if ($node AND empty($node['errors']))
			{
				$returnUrl = vB5_Config::instance()->baseurl . $api->callApi('route', 'getUrl', array('route' => $node['routeid'], 'data' => $node, 'extra' => array()));
			}

			if (!empty($returnUrl))
			{
				$result['retUrl'] = $returnUrl;
			}

			$result['nodeId'] = $nodeId;

			$this->sendAsJson($result);
			exit;
		}
		exit;
	}

	/**
	 * Creates a gallery, used by actionAlbum and actionGallery
	 */
	private function createGallery()
	{
		if (!isset($_POST['parentid']) OR !intval($_POST['parentid']))
		{
			return '';
		}

		$time = vB5_Request::get('timeNow');
		$input = array(
			'parentid'        => intval($_POST['parentid']),
			'publishdate'     => $time,
			'created'         => $time,
			'rawtext'         => (isset($_POST['text'])) ? trim(strval($_POST['text'])) : '',
			'title'           => (isset($_POST['gallery_title'])) ? trim(strval($_POST['gallery_title'])) : 'No Title',
			'tags'            => (isset($_POST['tags'])) ? trim(strval($_POST['tags'])) : '',
			'iconid'          => (isset($_POST['iconid']) ? intval($_POST['iconid']) : 0),
			'prefixid'        => (isset($_POST['prefixid']) ? trim(strval($_POST['prefixid'])) : ''),
			'hvinput'         => (isset($_POST['humanverify']) ? $_POST['humanverify'] : ''),
			'enable_comments' => (isset($_POST['enable_comments']) ? (bool)$_POST['enable_comments'] : false), // Used only when entering blog posts
		);

		if (!empty($_POST['setfor']))
		{
			$input['setfor'] = $_POST['setfor'];
		}
		if (!empty($_POST['recaptcha_challenge_field']))
		{
			// reCaptcha fields
			$input['hvinput']['recaptcha_challenge_field'] = $_POST['recaptcha_challenge_field'];
			$input['hvinput']['recaptcha_response_field'] = $_POST['recaptcha_response_field'];
		}

		$api = Api_InterfaceAbstract::instance();

		if (!empty($_POST['filedataid']))
		{
			// We need to convert WYSIWYG html here and run the img check
			if (isset($input['rawtext']))
			{
				$tmpText = $api->callApi('bbcode', 'convertWysiwygTextToBbcode', array($input['rawtext'], array()));
				// Check Images
				if (($phrase = vB5_Frontend_Controller_Bbcode::verifyImgCheck($tmpText)) !== true)
				{
					$results['error'] = $phrase;
					$this->sendAsJson($results);
					return;
				}
			}

			$options = array(
				'facebook' => $this->getFacebookOptionsForAddNode(),
				'filedataid' => $_POST['filedataid'],
			);

			$nodeId = $api->callApi('content_gallery', 'add', array($input, $options));

			if (!empty($nodeId['errors']))
			{
				return $nodeId;
			}

			foreach($_POST['filedataid'] AS $filedataid)
			{

				$titleKey = "title_$filedataid";
				if (isset($_POST[$titleKey]))
				{
					$caption = $_POST[$titleKey];
				}
				else
				{
					$caption = '';
				}

				$result = $api->callApi('content_photo', 'add', array(
					array(
						'publishdate' => $time,
						'parentid' => $nodeId,
						'caption' => $caption,
						'title' => $caption,
						'filedataid' => $filedataid,
						'hvinput' => $input['hvinput'],
					),
					array(
						'isnewgallery' => true
					)
				));
				if (!empty($result['errors']))
				{
					return $result;
				}
			}

			if (!empty($input['tags']))
			{
				$tagRet = $api->callApi('tags', 'addTags', array($nodeId, $input['tags']));
				if (!empty($tagRet['errors']))
				{
					return $tagRet;
				}
			}

			if (isset($_POST['filedataids']))
			{
				foreach ($_POST['filedataids'] as $filedataid)
				{
					if (intval($filedataid))
					{
						$data = array('filedataid' => $filedataid);
						$fieldname = "filename_$filedataid";
						if (!empty($_POST[$fieldname]))
						{
							$data['filename'] = $_POST[$fieldname];
						}

						$attRes = $api->callApi('node', 'addAttachment', array($nodeId, $data));
						$this->handleErrorsForAjax($result, $attRes);
					}
				}
			}
		}

		return $nodeId;
	}

	/**
	 * Creates a user album, which is really just a gallery in the "Albums" channel
	 */
	function actionAlbum()
	{
		$api = Api_InterfaceAbstract::instance();
		$_POST['parentid'] = $api->callApi('node', 'fetchAlbumChannel', array());
		$galleryid = $this->createGallery();
		$html = '';

		$galleries = $api->callApi('profile', 'fetchAlbums', array());
		$templater = new vB5_Template('album_photo');
		foreach ($galleries as $gallery)
		{
			$templater->register('node', $gallery);
			$html .=  $templater->render();
		}

		$this->outputPage($html);
	}

	/**
	 * Creates a gallery
	 * This is called when creating a thread or reply using the "Photos" tab
	 * And when uploading photos at Profile => Media => Share Photos
	 */
	function actionGallery()
	{
		$galleryid = $this->createGallery();

		$input = array(
			'parentid' => (isset($_POST['parentid']) ? trim(intval($_POST['parentid'])) : 0),
			'channelid' => (isset($_POST['channelid']) ? trim(intval($_POST['channelid'])) : 0),
			'ret' => (isset($_POST['ret']) ? trim(strval($_POST['ret'])) : ''),
		);

		$result = array();

		if (!is_int($galleryid))
		{
			$this->handleErrorsForAjax($result, $galleryid);
			$this->sendAsJson($result);
			exit;
		}

		// Sets redirect url when creating new conversation
		$this->getReturnUrl($result, $input['channelid'], $input['parentid'], $galleryid);
		$result['nodeId'] = $galleryid;
		$this->sendAsJson($result);
		exit;
	}

	function actionVideo()
	{
		$input = array(
			'title'           => (isset($_POST['title']) ? trim(strval($_POST['title'])) : ''),
			'text'            => (isset($_POST['text']) ? trim(strval($_POST['text'])) : ''),
			'parentid'        => (isset($_POST['parentid']) ? trim(intval($_POST['parentid'])) : 0),
			'channelid'       => (isset($_POST['channelid']) ? trim(intval($_POST['channelid'])) : 0),
			'nodeid'          => (isset($_POST['nodeid']) ? trim(intval($_POST['nodeid'])) : 0),
			'ret'             => (isset($_POST['ret']) ? trim(strval($_POST['ret'])) : ''),
			'tags'            => (isset($_POST['tags']) ? $_POST['tags'] : ''),
			'url_title'       => (isset($_POST['url_title']) ? trim(strval($_POST['url_title'])) : ''),
			'url'             => (isset($_POST['url']) ? trim(strval($_POST['url'])) : ''),
			'url_meta'        => (isset($_POST['url_meta']) ? trim(strval($_POST['url_meta'])) : ''),
			'videoitems'      => (isset($_POST['videoitems']) ? $_POST['videoitems'] : array()),
			'iconid'          => (isset($_POST['iconid']) ? intval($_POST['iconid']) : 0),
			'prefixid'        => (isset($_POST['prefixid']) ? trim(strval($_POST['prefixid'])) : ''),
			'hvinput'         => (isset($_POST['humanverify']) ? $_POST['humanverify'] : ''),
			'enable_comments' => (isset($_POST['enable_comments']) ? (bool)$_POST['enable_comments'] : false), // Used only when entering blog posts
			'reason'          => (isset($_POST['reason']) ? trim(strval($_POST['reason'])) : ''), //used in editing a post
		);

		//@TODO: There is no title for posting a reply or comment but api throws an error if blank. Fix this.

		if (!empty($_POST['setfor']))
		{
			$input['setfor'] = $_POST['setfor'];
		}
		if (!empty($_POST['recaptcha_challenge_field']))
		{
			// reCaptcha fields
			$input['hvinput']['recaptcha_challenge_field'] = $_POST['recaptcha_challenge_field'];
			$input['hvinput']['recaptcha_response_field'] = $_POST['recaptcha_response_field'];
		}

		$videoitems = array();
		foreach ($input['videoitems'] as $k => $v)
		{
			if ($k == 'new')
			{
				foreach ($v as $v2)
				{
					if ($v2)
					{
						$videoitems[]['url'] = $v2['url'];
					}
				}
			}
			else
			{
				$videoitems[] = array(
					'videoitemid' => intval($k),
					'url' => $v['url'],
				);
			}
		}

		$api = Api_InterfaceAbstract::instance();

		// get user info for the currently logged in user
		$user  = $api->callApi('user', 'fetchUserinfo', array());

		if ($input['nodeid'])
		{
			$videoData = array(
				'title'           => $input['title'],
				'rawtext'         => $input['text'],
				'url_title'       => $input['url_title'],
				'url'             => $input['url'],
				'meta'            => $input['url_meta'],
				'videoitems'      => $videoitems,
				'iconid'          => $input['iconid'],
				'prefixid'        => $input['prefixid'],
				'enable_comments' => $input['enable_comments'],
				'reason'          => $input['reason'],
			);

			// We need to convert WYSIWYG html here and run the img check
			if (isset($videoData['rawtext']))
			{
				$tmpText = $api->callApi('bbcode', 'convertWysiwygTextToBbcode', array($videoData['rawtext'], array()));
				// Check Images
				if (($phrase = vB5_Frontend_Controller_Bbcode::verifyImgCheck($tmpText)) !== true)
				{
					$results['error'] = $phrase;
					$this->sendAsJson($results);
					return;
				}
			}

			$ret = $api->callApi('content_video', 'update', array($input['nodeid'], $videoData));

			//update tags
			$tags = !empty($input['tags']) ? explode(',', $input['tags']) : array();
			$tagRet = $api->callApi('tags', 'updateUserTags', array($input['nodeid'], $tags));
		}
		else
		{
			$result = array();
			$videoData = array(
				'title'           => $input['title'],
				'parentid'        => $input['parentid'],
				'rawtext'         => $input['text'],
				'userid'          => $user['userid'],
				'authorname'      => $user['username'],
				'created'         => vB5_Request::get('timeNow'),
				'publishdate'     => $api->callApi('content_text', 'getTimeNow', array()),
				'url_title'       => $input['url_title'],
				'url'             => $input['url'],
				'meta'            => $input['url_meta'],
				'videoitems'      => $videoitems,
				'iconid'          => $input['iconid'],
				'prefixid'        => $input['prefixid'],
				'hvinput'         => $input['hvinput'],
				'enable_comments' => $input['enable_comments'],
			);

			if (!empty($_POST['setfor']))
			{
				$videoData['setfor'] = $_POST['setfor'];
			}

			$options = array(
				'facebook' => $this->getFacebookOptionsForAddNode(),
			);

			// We need to convert WYSIWYG html here and run the img check
			if (isset($videoData['rawtext']))
			{
				$tmpText = $api->callApi('bbcode', 'convertWysiwygTextToBbcode', array($videoData['rawtext'], array()));
				// Check Images
				if (($phrase = vB5_Frontend_Controller_Bbcode::verifyImgCheck($tmpText)) !== true)
				{
					$results['error'] = $phrase;
					$this->sendAsJson($results);
					return;
				}
			}

			$nodeId = $api->callApi('content_video', 'add', array($videoData, $options));

			if (!is_int($nodeId))
			{
				$this->handleErrorsForAjax($result, $nodeId);
				$this->sendAsJson($result);
				exit();
			}

			if (!empty($input['tags']))
			{
				$tagRet = $api->callApi('tags', 'addTags', array($nodeId, $input['tags']));
			}

			if (isset($_POST['filedataids']))
			{
				foreach ($_POST['filedataids'] as $filedataid)
				{
					if (intval($filedataid))
					{
						$data = array('filedataid' => $filedataid);
						$fieldname = "filename_$filedataid";
						if (!empty($_POST[$fieldname]))
						{
							$data['filename'] = $_POST[$fieldname];
						}

						$attRes = $api->callApi('node', 'addAttachment', array($nodeId, $data));
						$this->handleErrorsForAjax($result, $attRes);
					}
				}
			}

			// Sets redirect url when creating new conversation
			$this->getReturnUrl($result, $input['channelid'], $input['parentid'], $nodeId);
			$result['nodeId'] = $nodeId;

			// publish to facebook
			//$this->publishToFacebook($result);

			$this->sendAsJson($result);
		}
		exit;
	}

	function actionLink()
	{

		if (isset($_POST['videoitems']))
		{
			return $this->actionVideo();
		}

		$input = array(
			'title'           => (isset($_POST['title']) ? trim(strval($_POST['title'])) : ''),
			'text'            => (isset($_POST['text']) ? trim(strval($_POST['text'])) : ''),
			'parentid'        => (isset($_POST['parentid']) ? trim(intval($_POST['parentid'])) : 0),
			'channelid'       => (isset($_POST['channelid']) ? trim(intval($_POST['channelid'])) : 0),
			'nodeid'          => (isset($_POST['nodeid']) ? trim(intval($_POST['nodeid'])) : 0),
			'ret'             => (isset($_POST['ret']) ? trim(strval($_POST['ret'])) : ''),
			'tags'            => (isset($_POST['tags']) ? $_POST['tags'] : ''),
			'url_image'       => (isset($_POST['url_image']) ? trim(strval($_POST['url_image'])) : ''),
			'url_title'       => (isset($_POST['url_title']) ? trim(strval($_POST['url_title'])) : ''),
			'url'             => (isset($_POST['url']) ? trim(strval($_POST['url'])) : ''),
			'url_meta'        => (isset($_POST['url_meta']) ? trim(strval($_POST['url_meta'])) : ''),
			'url_nopreview'   => (isset($_POST['url_nopreview']) ? intval($_POST['url_nopreview']) : 0),
			'iconid'          => (isset($_POST['iconid']) ? intval($_POST['iconid']) : 0),
			'prefixid'        => (isset($_POST['prefixid']) ? trim(strval($_POST['prefixid'])) : ''),
			'hvinput'         => (isset($_POST['humanverify']) ? $_POST['humanverify'] : ''),
			'enable_comments' => (isset($_POST['enable_comments']) ? (bool)$_POST['enable_comments'] : false), // Used only when entering blog posts
			'reason'          => (isset($_POST['reason']) ? trim(strval($_POST['reason'])) : ''), //used in editing a post
		);

		//@TODO: There is no title for posting a reply or comment but api throws an error if blank. Fix this.

		if (!empty($_POST['setfor']))
		{
			$input['setfor'] = $_POST['setfor'];
		}
		if (!empty($_POST['recaptcha_challenge_field']))
		{
			// reCaptcha fields
			$input['hvinput']['recaptcha_challenge_field'] = $_POST['recaptcha_challenge_field'];
			$input['hvinput']['recaptcha_response_field'] = $_POST['recaptcha_response_field'];
		}

		$api = Api_InterfaceAbstract::instance();

		// get user info for the currently logged in user
		$user  = $api->callApi('user', 'fetchUserinfo', array());

		// Upload images
		$filedataid = 0;
		if (!$input['url_nopreview'] AND $input['url_image'])
		{
			$ret = $api->callApi('content_attach', 'uploadUrl', array($input['url_image']));

			if (empty($ret['error']))
			{
				$filedataid = $ret['filedataid'];
			}
		}

		if ($input['nodeid'])
		{
			if ($filedataid)
			{
//				$api->callApi('content_attach', 'deleteAttachment', array($input['nodeid']));
			}

			$linkData = array(
				'title'           => $input['title'],
				'url_title'       => $input['url_title'],
				'rawtext'         => $input['text'],
				'url'             => $input['url'],
				'meta'            => $input['url_meta'],
				'filedataid'      => $filedataid,
				'iconid'          => $input['iconid'],
				'prefixid'        => $input['prefixid'],
				'enable_comments' => $input['enable_comments'],
				'reason'          => $input['reason'],
			);

			// We need to convert WYSIWYG html here and run the img check
			if (isset($linkData['rawtext']))
			{
				$tmpText = $api->callApi('bbcode', 'convertWysiwygTextToBbcode', array($linkData['rawtext'], array()));
				// Check Images
				if (($phrase = vB5_Frontend_Controller_Bbcode::verifyImgCheck($tmpText)) !== true)
				{
					$results['error'] = $phrase;
					$this->sendAsJson($results);
					return;
				}
			}

			$ret = $api->callApi('content_link', 'update', array($input['nodeid'], $linkData));

			//update tags
			$tags = !empty($input['tags']) ? explode(',', $input['tags']) : array();
			$tagRet = $api->callApi('tags', 'updateUserTags', array($input['nodeid'], $tags));
		}
		else
		{
			$result = array();
			$linkData = array(
				'title'           => $input['title'],
				'url_title'       => $input['url_title'],
				'parentid'        => $input['parentid'],
				'rawtext'         => $input['text'],
				'userid'          => $user['userid'],
				'authorname'      => $user['username'],
				'created'         => vB5_Request::get('timeNow'),
				'publishdate'     => $api->callApi('content_link', 'getTimeNow', array()),
				'url'             => $input['url'],
				'meta'            => $input['url_meta'],
				'filedataid'      => $filedataid,
				'iconid'          => $input['iconid'],
				'prefixid'        => $input['prefixid'],
				'hvinput'         => $input['hvinput'],
				'enable_comments' => $input['enable_comments'],
			);

			if (!empty($_POST['setfor']))
			{
				$linkData['setfor'] = $_POST['setfor'];
			}

			$options = array(
				'facebook' => $this->getFacebookOptionsForAddNode(),
			);

			// We need to convert WYSIWYG html here and run the img check
			if (isset($linkData['rawtext']))
			{
				$tmpText = $api->callApi('bbcode', 'convertWysiwygTextToBbcode', array($linkData['rawtext'], array()));
				// Check Images
				if (($phrase = vB5_Frontend_Controller_Bbcode::verifyImgCheck($tmpText)) !== true)
				{
					$results['error'] = $phrase;
					$this->sendAsJson($results);
					return;
				}
			}

			$nodeId = $api->callApi('content_link', 'add', array($linkData, $options));

			if (!is_int($nodeId))
			{
				$this->handleErrorsForAjax($result, $nodeId);
				$this->sendAsJson($result);
				exit();
			}

			if (!empty($input['tags']))
			{
				$tagRet = $api->callApi('tags', 'addTags', array($nodeId, $input['tags']));
				$this->handleErrorsForAjax($result, $tagRet);
			}

			if (isset($_POST['filedataids']))
			{
				foreach ($_POST['filedataids'] as $filedataid)
				{
					if (intval($filedataid))
					{
						$data = array('filedataid' => $filedataid);
						$fieldname = "filename_$filedataid";
						if (!empty($_POST[$fieldname]))
						{
							$data['filename'] = $_POST[$fieldname];
						}

						$attRes = $api->callApi('node', 'addAttachment', array($nodeId, $data));
						$this->handleErrorsForAjax($result, $attRes);
					}
				}
			}

			// Sets redirect url when creating new conversation
			$this->getReturnUrl($result, $input['channelid'], $input['parentid'], $nodeId);
			$result['nodeId'] = $nodeId;

			// publish to facebook
			//$this->publishToFacebook($result);

			$this->sendAsJson($result);
		}
		exit;
	}

	/**
	 * Creates a private message.
	 */
	public function actionPrivatemessage()
	{
		$api = Api_InterfaceAbstract::instance();

		if (!empty($_POST['autocompleteHelper']) AND empty($_POST['msgRecipients']))
		{
			$msgRecipients = $_POST['autocompleteHelper'];


			if (substr($msgRecipients, -1) == ';')
			{
				$msgRecipients = substr($msgRecipients, 0, -1);
			}
			$_POST['msgRecipients'] = $msgRecipients;
		}

		if (!empty($_POST['msgRecipients']) AND (substr($_POST['msgRecipients'], -1) == ';'))
		{
			$_POST['msgRecipients'] = substr($_POST['msgRecipients'], 0, -1);
		}

		$hvInput = isset($_POST['humanverify']) ? $_POST['humanverify'] : '';
		if (!empty($_POST['recaptcha_challenge_field']))
		{
			// reCaptcha fields
			$hvInput['recaptcha_challenge_field'] = $_POST['recaptcha_challenge_field'];
			$hvInput['recaptcha_response_field'] = $_POST['recaptcha_response_field'];
		}
		$_POST['hvinput'] =& $hvInput;

		$result = $api->callApi('content_privatemessage', 'add', array($_POST));
		$results = array();

		if (!empty($result['errors']))
		{
			if (is_array($result['errors'][0]))
			{
				$errorphrase = array_shift($result['errors'][0]);
				$phrases = $api->callApi('phrase', 'fetch', array(array($errorphrase)));
				$results['errormessage'] = vsprintf($phrases[$errorphrase], $result['errors'][0]);
			}
			else
			{
				$phrases = $api->callApi('phrase', 'fetch', array(array($result['errors'][0])));
				$results['errormessage'] =  $phrases[$result['errors'][0]];
			}

		}
		else
		{
			$phrases = $api->callApi('phrase', 'fetch', array(array('pm_sent')));
			$results['message'] = $phrases['pm_sent'];
		}

		return $this->sendAsJson($results);
	}

	public function actionLoadeditor()
	{
		$input = array(
			'nodeid' => (isset($_POST['nodeid']) ? intval($_POST['nodeid']) : 0),
			'type' => (isset($_POST['type']) ? trim(strval($_POST['type'])) : ''),
			'view' => (isset($_POST['view']) ? trim($_POST['view']) : 'stream'),
		);

		if (!$input['nodeid'])
		{
			$results['error'] = 'error_loading_editor';
			$this->sendAsJson($results);
			return;
		}

		//if (!empty($_POST['setfor']))
		//{
		//	$videoData['setfor'] = intval($_POST['setfor']);
		//}

		$api = Api_InterfaceAbstract::instance();

		$node = $api->callApi('node', 'getNodeContent', array($input['nodeid'], false, array(
			'permissions' => array(
				'moderatorpermissions2' => array(
					'candeletevisitormessages', 'canremovevisitormessages'
				)
			)
		)));

		$node = $node[$input['nodeid']];

		if (!$node)
		{
			$results['error'] = 'error_loading_editor';
			$this->sendAsJson($results);
			return;
		}

		if ($api->callApi('user', 'hasPermissions', array('moderatorpermissions2', 'candeletevisitormessages'))
			OR
			$api->callApi('user', 'hasPermissions', array('moderatorpermissions2', 'canremovevisitormessages'))
		)
		{
			$node['canremove'] = 1; // Make the editor show Delete button
		}

		if (in_array($node['contenttypeclass'], array('Text', 'Gallery', 'Poll', 'Video', 'Link')))
		{
			if ($input['type'] == 'comment' AND $node['contenttypeclass'] == 'Text')
			{
				$templater = new vB5_Template('editor_contenttype_Text_comment');
				$templater->register('conversation', $node);
				$templater->register('showDelete', true);
			}
			else
			{
				$templater = new vB5_Template('editor_contenttype_' . $node['contenttypeclass']);
				$templater->register('nodeid', $node['nodeid']);
				$templater->register('conversation', $node);
				$templater->register('parentid', $node['parentid']);
				$templater->register('submitButtonLabelDiscussion', 'Save');
				$templater->register('showCancel', true);
				$templater->register('showDelete', true);
				$templater->register('showPreview', true);
				$templater->register('showGoAdvanced', $input['type'] != 'comment');
				$templater->register('editPost', true);
				$templater->register('conversationType', $input['type']);

				if ($node['contenttypeclass'] == 'Gallery')
				{
					if (!empty($node['photo']))
					{
						$templater->register('maxid', max(array_keys($node['photo'])));
					}
					else
					{
						$templater->register('maxid', 0);
					}
				}

				//content types that has no Tags. Types used should be the same used in $input['type']
				$noTagsContentTypes = array('media', 'visitorMessage'); //add more types as needed
				if ($node['nodeid'] == $node['starter'])
				{
					if (!in_array($input['type'], $noTagsContentTypes)) //get tags of the starter (exclude types that don't use tags)
					{
						$tagList = $api->callApi('tags', 'getNodeTags', array($input['nodeid']));
						if (!empty($tagList) AND !empty($tagList['tags']))
						{
							$tags = array();
							foreach ($tagList['tags'] as $tag)
							{
								$tags[] = $tag['tagtext'];
							}

							$tagList['displaytags']	= implode(', ', $tags);
							$templater->register('tagList', $tagList);
						}
					}
					$channelInfo = $api->callApi('content_channel', 'fetchChannelById', array($node['parentid']));

					$is_Blog = $api->callApi('blog', 'isBlogNode', array($node['parentid'], $channelInfo));
					if ($channelInfo['can_comment'] AND $is_Blog)
					{
						$templater->register('can_comment_option', true);
					}
				}
				if (in_array($input['type'], $noTagsContentTypes) OR $node['nodeid'] != $node['starter'])
				{
					$templater->register('showTags', false);
				}
			}

			$results['template'] = $templater->render();
		}
		else
		{
			$results['error'] = 'error_loading_editor';
		}
		$this->sendAsJson($results);
		return;
	}

	public function actionLoadPreview()
	{
		$input = array(
			'parentid' => (isset($_POST['parentid']) ? intval($_POST['parentid']) : 0),
			'channelid' => (isset($_POST['channelid']) ? intval($_POST['channelid']) : 0),
			'pagedata' => (isset($_POST['pagedata']) ? ((array)$_POST['pagedata']) : array()),
			'conversationtype' => (isset($_POST['conversationtype']) ? trim(strval($_POST['conversationtype'])) : ''),
			'posttags' => (isset($_POST['posttags']) ? trim(strval($_POST['posttags'])) : ''),
			'rawtext' => (isset($_POST['rawtext']) ? trim(strval($_POST['rawtext'])) : ''),
			'filedataid' => (isset($_POST['filedataid']) ? ((array)$_POST['filedataid']) : array()),
		);

		$results = array();

		if ($input['parentid'] < 1)
		{
			$results['error'] = 'invalid_parentid';
			$this->sendAsJson($results);
			return;
		}

		// when creating a new content item, channelid == parentid
		$input['channelid'] = $input['channelid'] == 0 ? $input['parentid'] : $input['channelid'];

		$templateName = 'display_contenttype_conversation';
		//$templateName .= ($input['channelid'] == $input['parentid']) ? 'starter_threadview_' : 'reply_';
		$templateName .= 'starter_threadview_';
		$templateName .= ucfirst($input['conversationtype']);

		$api = Api_InterfaceAbstract::instance();
		$channelBbcodes = $api->callApi('content_channel', 'getBbcodeOptions', array($input['channelid']));

		$node = array(
			'rawtext' => '',
			'userid' => vB5_User::get('userid'),
			'authorname' => vB5_User::get('username'),
			'tags' => $input['posttags'],
			'taglist' => $input['posttags'],
		);

		if ($input['conversationtype'] == 'gallery')
		{
			$node['photopreview'] = array();
			foreach ($input['filedataid'] AS $filedataid)
			{
				$node['photopreview'][] = array(
					'nodeid' => $filedataid,
					'htmltitle' => isset($_POST['title_' . $filedataid]) ? vB_String::htmlSpecialCharsUni($_POST['title_' . $filedataid]) : '',
				);

				//photo preview is up to 3 photos only
				if (count($node['photopreview']) == 3)
				{
					break;
				}
			}
			$node['photocount'] = count($input['filedataid']);
		}

		$templater = new vB5_Template($templateName);
		$templater->register('nodeid', 0);
		$templater->register('conversation', $node);
		$templater->register('currentConversation', $node);
		$templater->register('bbcodeOptions', $channelBbcodes);
		$templater->register('pagingInfo', array());
		$templater->register('postIndex', 0);
		$templater->register('reportActivity', false);
		$templater->register('showChannelInfo', false);
		$templater->register('showInlineMod', false);
		$templater->register('commentsPerPage', 1);
		$templater->register('view', 'conversation_detail');
		$templater->register('previewMode', true);

		try
		{
			$results['template'] = $templater->render();
		}
		catch (Exception $e)
		{
			if (vB5_Config::instance()->debug)
			{
				$results['error'] = 'error_rendering_preview_template ' . (string) $e;
			}
			else
			{
				$results['error'] = 'error_rendering_preview_template';
			}
			$this->sendAsJson($results);
			return;
		}

		// parse bbcode in text
		try
		{
			$results['parsedText'] = vB5_Frontend_Controller_Bbcode::parseWysiwygForPreview($input['rawtext']);
		}
		catch (Exception $e)
		{
			if (vB5_Config::instance()->debug)
			{
				$results['error'] = 'error_parsing_bbcode_for_preview ' . (string) $e;
			}
			else
			{
				$results['error'] = 'error_parsing_bbcode_for_preview';
			}
			$this->sendAsJson($results);
			return;
		}

		$this->sendAsJson($results);
	}

	public function actionLoadnode()
	{
		$input = array(
			'nodeid' => (isset($_POST['nodeid']) ? intval($_POST['nodeid']) : 0),
			'view' => (isset($_POST['view']) ? trim($_POST['view']) : 'stream'),
			'page' => (isset($_POST['page']) ? $_POST['page'] : array()),
			'index' => (isset($_POST['index']) ? floatval($_POST['index']) : 0),
			'type' => (isset($_POST['type']) ? trim(strval($_POST['type'])) : ''),
		);

		$results = array();

		if (!$input['nodeid'])
		{
			$results['error'] = 'error_loading_post';
			$this->sendAsJson($results);
			return;
		}

		$api = Api_InterfaceAbstract::instance();

		$node = $api->callApi('node', 'getNodeFullContent', array('nodeid' => $input['nodeid'], 'contenttypeid' => false, 'options' => array('showVM' => 1, 'withParent' => 1)));
		$node = isset($node[$input['nodeid']]) ? $node[$input['nodeid']] : null;

		if (!$node)
		{
			$results['error'] = 'error_loading_post';
			$this->sendAsJson($results);
			return;
		}

		if (!in_array($input['view'], array('stream', 'thread', 'activity-stream', 'full-activity-stream')))
		{
			$input['view'] = 'stream';
		}

		//comment in Thread view
		if ($input['view'] == 'thread' AND $input['type'] == 'comment' AND $node['contenttypeclass'] == 'Text')
		{
			$templater = new vB5_Template('conversation_comment_item');
			$templater->register('conversation', $node);
			$templater->register('conversationIndex', floor($input['index']));
			if ($input['index'] - floor($input['index']) > 0)
			{
				$commentIndex = explode('.', strval($input['index']));
				$templater->register('commentIndex', $commentIndex[1]);
			}
			else
			{
				$templater->register('commentIndex', 1);
			}
		}
		else //reply or starter node or comment in Stream view
		{
			$template = 'display_contenttype_';
			if ($node['nodeid'] == $node['starter'])
			{
				$template .= ($input['view'] == 'thread') ? 'conversationstarter_threadview_' : 'conversationreply_';
				$parentConversation = $node;
			}
			else
			{
				$template .= ($input['view'] == 'thread') ? 'conversationreply_threadview_' : 'conversationreply_';
			}

			$conversationRoute = $api->callApi('route', 'getChannelConversationRoute', array($input['page']['channelid']));
			$channelBbcodes = $api->callApi('content_channel', 'getBbcodeOptions', array($input['page']['channelid']));

			if (strpos($input['view'], 'stream') !== false)
			{
				$totalCount = $node['totalcount'];
			}
			else
			{
				$totalCount = $node['textcount'];
			}

			$arguments = array(
				'nodeid'	=>	$node['nodeid'],
				'pagenum'	=>	$input['page']['pagenum'],
				'channelid'	=>	$input['page']['channelid'],
				'pageid'	=>	$input['page']['pageid']
			);

			$routeInfo = array(
				'routeId' => $conversationRoute,
				'arguments'	=> $arguments,
			);

			$pagingInfo = $api->callApi('page', 'getPagingInfo', array($input['page']['pagenum'], $totalCount, (isset($input['page']['posts-perpage']) ? $input['page']['posts-perpage'] : null), $routeInfo, vB5_Config::instance()->baseurl));
			$currentNodeIsBlog = $api->callApi('blog', 'isBlogNode', array($node['nodeid']));

			$template .= $node['contenttypeclass'];

			$templater = new vB5_Template($template);
			$templater->register('nodeid', $node['nodeid']);
			$templater->register('currentNodeIsBlog', $currentNodeIsBlog);
			$templater->register('conversation', $node);
			$templater->register('currentConversation', $node);
			$templater->register('bbcodeOptions', $channelBbcodes);
			$templater->register('pagingInfo', $pagingInfo);
			$templater->register('postIndex', $input['index']);
			$templater->register('reportActivity', strpos($input['view'], 'activity-stream') !== false);
			$templater->register('showChannelInfo', $input['view'] == 'full-activity-stream');
			if ($input['view'] == 'thread')
			{
				$templater->register('showInlineMod', true);
				$templater->register('commentsPerPage', $input['page']['comments-perpage']);
			}
			else if ($input['view'] == 'stream' AND !$node['isVisitorMessage']) // Visitor Message doesn't allow to be quoted. See VBV-5583.
			{
				$templater->register('view', 'conversation_detail');
			}
		}

		$results['template'] = $templater->render();

		$this->sendAsJson($results);
		return;
	}

	/**
	 * This handles all saves of blog data.
	 */
	public function actionBlog()
	{
		$fields = array('title', 'description', 'nodeid', 'filedataid', 'invite_usernames', 'invite_userids', 'viewperms', 'commentperms',
			'moderate_comments', 'approve_membership', 'allow_post', 'autoparselinks', 'disablesmilies', 'sidebarInfo');

		// forum options map
		$channelOpts = array('allowsmilies' => 'disablesmilies', 'allowposting' => 'allow_post');

		$input = array();
		foreach ($fields as $field)
		{
			if (isset($_POST[$field]))
			{
				$input[$field] = $_POST[$field];
			}
		}

		// allowsmilies is general
		if (isset($_POST['next']) AND ($_POST['next'] == 'permissions'))
		{
			foreach (array('autoparselinks', 'disablesmilies') AS $field)
			{
				// channeloptions
				if ($idx = array_search($field, $channelOpts))
				{
					// some options means totally the oppositve than the bf when enable, tweak then
					if (isset($_POST[$field]))
					{
						$input['options'][$idx] = (in_array($field, array('disablesmilies')) ? 0 : 1);
					}
					else
					{
						$input['options'][$idx] = (in_array($field, array('disablesmilies')) ? 1 : 0);
					}
				}

				if (!isset($_POST[$field]))
				{
					$input[$field] = 0;
				}
			}
		}


		//If this is the "permission" step, we must pass the three checkboxes
		if (isset($_POST['next']) AND ($_POST['next'] == 'contributors'))
		{
			foreach (array( 'moderate_comments', 'approve_membership', 'allow_post') AS $field )
			{
				if ($idx = array_search($field, $channelOpts))
				{
					// some options means totally the oppositve than the bf when enable, tweak then
					if (isset($_POST[$field]))
					{
						$input['options'][$idx] = 1;
					}
					else
					{
						$input['options'][$idx] = 0;
					}
				}

				if (!isset($_POST[$field]))
				{
					$input[$field] = 0;
				}
			}
		}
		if (empty($input['options']))
		{
			$input['options'] = array();
		}
		// Other default options
		$input['options'] += array(
			'allowbbcode' => 1,
			'allowimages' => 1,
		);

		$api = Api_InterfaceAbstract::instance();

		$quickCreateBlog = (isset($_POST['wizard']) AND $_POST['wizard'] == '0') ? true : false; //check if in quick create blog mode (in overlay and non-wizard type)

		if (count($input) > 1)
		{
			$input['parentid'] = $api->callApi('blog', 'getBlogChannel');
			if (empty($input['nodeid']))
			{
				$nodeid = $api->callApi('blog', 'createBlog', array($input));
				$url = vB5_Config::instance()->baseurl . '/blogadmin/create/settings';
				if (is_array($nodeid) AND array_key_exists('errors', $nodeid))
				{
					if ($quickCreateBlog)
					{
						$this->sendAsJson($nodeid);
						return;
					}
					else
					{
						$message = $api->callApi('phrase', 'fetch', array('phrases' => $nodeid['errors'][0][0]));
						if (empty($message))
						{
							$message = $api->callApi('phrase', 'fetch', array('phrases' => 'pm_ajax_error_desc'));
						}
						vB5_ApplicationAbstract::handleFormError(array_pop($message), $url);
					}

				}
				if (!is_numeric($nodeid) AND !empty($nodeid['errors']))
				{
					if ($quickCreateBlog)
					{
						$this->sendAsJson($nodeid);
						return;
					}
					else
					{
						$urlparams = array('blogaction' => 'create', 'action2' => 'settings');
						$url = $api->callApi('route', 'getUrl', array('blogadmin', $urlparams, array()));
						header('Location: ' . vB5_Config::instance()->baseurl . $url);
						vB5_Cookie::set('blogadmin_error', $nodeid['errors'][0][0]);
						if (isset($input['title']))
						{
							vB5_Cookie::set('blog_title', $input['title']);
						}
						if (isset($input['description']))
						{
							vB5_Cookie::set('blog_description', $input['description']);
						}
						die();
					}
				}
			}
			else if(isset($input['invite_usernames']) AND $input['nodeid'])
			{
				$inviteUnames = explode(',', $input['invite_usernames']);
				$inviteIds = (isset($input['invite_userids'])) ? $input['invite_userids'] : array();
				$nodeid = $input['nodeid'];
				$api->callApi('user', 'inviteMembers', array($inviteIds, $inviteUnames, $nodeid, 'member_to'));
			}
			else if (isset($input['sidebarInfo']) AND $input['nodeid'])
			{
				$modules = explode(',', $input['sidebarInfo']);
				$nodeid = $input['nodeid'];
				foreach ($modules AS $key => $val)
				{
					$info = explode(':', $val);
					$modules[$key] = array('widgetinstanceid' => $info[0], 'hide' => ($info[1] == 'hide'));
				}
				$api->callApi('blog', 'saveBlogSidebarModules', array($input['nodeid'], $modules));
			}
			else
			{
				$nodeid = $input['nodeid'];
				unset($input['nodeid']);
				$api->callApi('content_channel', 'update', array($nodeid, $input));

				//if this is for the permission page we handle differently

			}
//			set_exception_handler(array('vB5_ApplicationAbstract','handleException'));
//
//			if (!is_numeric($nodeid) AND !empty($nodeid['errors']))
//			{
//				throw new exception($nodeid['errors'][0][0]);
//			}
		}
		else if (isset($_POST['nodeid']))
		{
			$nodeid = $_POST['nodeid'];
			if (isset($_POST['next']) AND ($_POST['next'] == 'contributors'))
			{
				$updates = array();
				foreach (array('allow_post', 'moderate_comments', 'approve_membership') as $bitfield)
				{

					if (empty($_POST[$bitfield]))
					{
						$updates[$bitfield] = 0;
					}
					else
					{
						$updates[$bitfield] = 1;
					}
				}
				$api->callApi('node', 'setNodeOptions', array($nodeid, $updates));
				$updates = array();

				if (isset($_POST['viewperms']))
				{
					$updates['viewperms'] = $_POST['viewperms'];
				}

				if (isset($_POST['commentperms']))
				{
					$updates['commentperms'] = $_POST['commentperms'];
				}

				if (!empty($updates))
				{
					$results = $api->callApi('node', 'setNodePerms', array($nodeid, $updates));
				}

			}
		}
		else
		{
			$nodeid = 0;
		}

		//If the user clicked Next we go to the permissions page. Otherwise we go to the node.
		if (isset($_POST['btnSubmit']))
		{
			if (isset($_POST['next']))
			{
				$action2 = $_POST['next'];
			}
			else
			{
				$action2 = 'permissions';
			}

			if (isset($_POST['blogaction']))
			{
				$blogaction = $_POST['blogaction'];
			}
			else
			{
				$blogaction = 'admin';
			}

			$urlparams = array('nodeid' => $nodeid, 'blogaction' => $blogaction, 'action2' => $action2);
			$url = $api->callApi('route', 'getUrl', array('blogadmin', $urlparams, array()));
		}
		else if ($quickCreateBlog)
		{
			$this->sendAsJson(array('nodeid' => $nodeid));
			return;
		}
		else
		{
			$node = $api->callApi('node', 'getNode', array('nodeid' => $nodeid));
			$url = $api->callApi('route', 'getUrl', array($node['routeid'], array('nodeid' => $nodeid, 'title' => $node['title'], 'urlident' => $node['urlident']), array()));
		}

		header('Location: ' . vB5_Config::instance()->baseurl . $url);
	}


	/**
	 * This handles all saves of social group data.
	 */
	public function actionSocialgroup()
	{
		$fields = array('title', 'description', 'nodeid', 'filedataid', 'invite_usernames', 'parentid', 'invite_userids',
			'group_type', 'viewperms', 'commentperms', 'moderate_topics', 'autoparselinks',
			'disablesmilies', 'allow_post', 'approve_subscription', 'group_type');

		// forum options map
		$channelOpts = array('allowsmilies' => 'disablesmilies', 'allowposting' => 'allow_post');

		$input = array();
		foreach ($fields as $field)
		{
			if (isset($_POST[$field]))
			{
				$input[$field] = $_POST[$field];
			}
		}

		//If this is the "permission" step, we must pass the four checkboxes
		if (isset($_POST['next']) AND ($_POST['next'] == 'contributors'))
		{
			foreach (array( 'moderate_comments', 'autoparselinks', 'disablesmilies', 'allow_post', 'approve_subscription', 'moderate_topics') AS $field)
			{
				// channeloptions
				if ($idx = array_search($field, $channelOpts))
				{
					// some options means totally the oppositve than the bf when enable, tweak then
					if (isset($_POST[$field]))
					{
						$input['options'][$idx] = (in_array($field, array('disablesmilies')) ? 0 : 1);
					}
					else
					{
						$input['options'][$idx] = (in_array($field, array('disablesmilies')) ? 1 : 0);
					}
				}

				if (!isset($_POST[$field]))
				{
					$input[$field] = 0;
				}
			}
		}

		$api = Api_InterfaceAbstract::instance();
		if (count($input) > 1)
		{
			if (!isset($input['nodeid']) OR (intval($input['nodeid']) == 0))
			{
				$nodeid = $api->callApi('socialgroup', 'createSocialGroup', array($input));
				$url = vB5_Config::instance()->baseurl . '/sgadmin/create/settings';
				if (is_array($nodeid) AND array_key_exists('errors', $nodeid))
				{
					$message = $api->callApi('phrase', 'fetch', array('phrases' => $nodeid['errors'][0][0]));
					if (empty($message))
					{
						$message = $api->callApi('phrase', 'fetch', array('phrases' => 'pm_ajax_error_desc'));
					}

					vB5_ApplicationAbstract::handleFormError(array_pop($message), $url);
				}
				if (!is_numeric($nodeid) AND !empty($nodeid['errors']))
				{
					$urlparams = array('sgaction' => 'create', 'action2' => 'settings');
					$url = $api->callApi('route', 'getUrl', array('sgadmin', $urlparams, array()));
					header('Location: ' . vB5_Config::instance()->baseurl . $url);
					vB5_Cookie::set('sgadmin_error', $nodeid['errors'][0][0]);
					if (isset($input['title']))
					{
						vB5_Cookie::set('sg_title', $input['title']);
					}
					if (isset($input['description']))
					{
						vB5_Cookie::set('sg_description', $input['description']);
					}
					die();
				}

				if ($nodeid AND !empty($nodeid['errors']))
				{
					$urlparams = array('sgaction' => 'create', 'action2' => 'settings');
					$url = $api->callApi('route', 'getUrl', array('sgadmin', $urlparams, array()));
					header('Location: ' . vB5_Config::instance()->baseurl . $url);
					vB5_Cookie::set('sgadmin_error', $nodeid['errors'][0][0]);
					if (isset($input['title']))
					{
						vB5_Cookie::set('sg_title', $input['title']);
					}
					if (isset($input['description']))
					{
						vB5_Cookie::set('sg_description', $input['description']);
					}
					die();
				}

			}
			else if(isset($input['invite_usernames']) AND $input['nodeid'])
			{
				$inviteUnames = explode(',', $input['invite_usernames']);
				$inviteIds = (isset($input['invite_userids'])) ? $input['invite_userids'] : array();
				$nodeid = $input['nodeid'];
				$api->callApi('user', 'inviteMembers', array($inviteIds, $inviteUnames, $nodeid, 'sg_member_to'));
			}
			else
			{
				$nodeid = $input['nodeid'];
				unset($input['nodeid']);

				$update = $api->callApi('content_channel', 'update', array($nodeid, $input));

				// set group type nodeoptions
				if (empty($update['errors']) AND isset($input['group_type']))
				{
					$bitfields = array();
					switch ($input['group_type'])
					{
						case 2:
							$bitfields['invite_only'] = 1;
							$bitfields['approve_membership'] = 0;
							break;
						case 1:
							$bitfields['invite_only'] = 0;
							$bitfields['approve_membership'] = 0;
							break;
						default:
							$bitfields['invite_only'] = 0;
							$bitfields['approve_membership'] = 1;
							break;
					}

					$api->callApi('node', 'setNodeOptions', array($nodeid, $bitfields));
				}

				//if this is for the permission page we handle differently

			}
			//			set_exception_handler(array('vB5_ApplicationAbstract','handleException'));
			//
			//			if (!is_numeric($nodeid) AND !empty($nodeid['errors']))
			//			{
			//				throw new exception($nodeid['errors'][0][0]);
			//			}
		}
		else if (isset($_POST['nodeid']))
		{
			$nodeid = $_POST['nodeid'];
			if (isset($_POST['next']) AND ($_POST['next'] == 'contributors'))
			{
				$updates = array();
				foreach (array('allow_post', 'moderate_comments', 'autoparselinks', 'disablesmilies', 'approve_subscription') as $bitfield)
				{
					if (empty($_POST[$bitfield]))
					{
						$updates[$bitfield] = 0;
					}
					else
					{
						$updates[$bitfield] = 1;
					}
				}
				$api->callApi('node', 'setNodeOptions', array($nodeid, $updates));
				$updates = array();

				if (isset($_POST['viewperms']))
				{
					$updates['viewperms'] = $_POST['viewperms'];
				}

				if (isset($_POST['commentperms']))
				{
					$updates['commentperms'] = $_POST['commentperms'];
				}

				if (!empty($updates))
				{
					$results = $api->callApi('node', 'setNodePerms', array($nodeid, $updates));
				}

			}
		}
		else
		{
			$nodeid = 0;
		}

		//If the user clicked Next we go to the permissions page. Otherwise we go to the node.
		if (isset($_POST['btnSubmit']))
		{
			if (isset($_POST['next']))
			{
				$action2 = $_POST['next'];
			}
			else
			{
				$action2 = 'permissions';
			}

			if (isset($_POST['sgaction']))
			{
				$sgaction = $_POST['sgaction'];
			}
			else
			{
				$sgaction = 'admin';
			}

			$urlparams = array('nodeid' => $nodeid, 'sgaction' => $sgaction, 'action2' => $action2);
			$url = $api->callApi('route', 'getUrl', array('sgadmin', $urlparams, array()));
		}
		else
		{
			$node = $api->callApi('node', 'getNode', array('nodeid' => $nodeid));
			$url = $api->callApi('route', 'getUrl', array($node['routeid'], array('nodeid' => $nodeid, 'title' => $node['title'], 'urlident' => $node['urlident']), array()));
		}

		header('Location: ' . vB5_Config::instance()->baseurl . $url);
	}

	/**
	 * This sets a return url when creating new content and sets if the created content
	 * is a visitor message
	 *
	 */
	protected function getReturnUrl(&$result, $channelid, $parentid, $nodeid)
	{
		$api = Api_InterfaceAbstract::instance();
		$returnUrl = '';

		// ensure we have a channelid for the redirect
		if (!$channelid && $parentid)
		{
			try
			{
				$channel = $api->callApi('content_channel', 'fetchChannelById', array($parentid));
				if ($channel && isset($channel['nodeid']) && $channel['nodeid'])
				{
					$channelid = $channel['nodeid'];
				}
			}
			catch (Exception $e){}
		}

		//Get the conversation detail page of the newly created post if we are creating a starter
		if ($channelid == $parentid)
		{
			$node = $api->callApi('node', 'getNode', array($nodeid));
			if ($node AND empty($node['errors']))
			{
				$returnUrl = vB5_Config::instance()->baseurl . $api->callApi('route', 'getUrl', array('route' => $node['routeid'], 'data' => $node, 'extra' => array()));
			}
		}

		if (!empty($returnUrl))
		{
			$result['retUrl'] = $returnUrl;
		}
	}

	/**
	 * Get facebook related options to pass to the add node apis
	 *
	 * @return	array
	 *
	 */
	protected function getFacebookOptionsForAddNode()
	{
		return array(
			'fbpublish' => (isset($_POST['fbpublish']) && intval($_POST['fbpublish']) === 1),
			'baseurl' => vB5_Config::instance()->baseurl,
		);
	}

}