<?php

class vB5_Frontend_Controller_Filedata extends vB5_Frontend_Controller
{

	/**This methods returns the contents of a specific image
	***/
	public function actionFetch()
	{
		$request = array(
			'id'          => 0,
			'type'        => '',
			'includeData' => true,
		);

		if (isset($_REQUEST['type']) AND !empty($_REQUEST['type']))
		{
			$request['type'] = $_REQUEST['type'];
		}
		else if (!empty($_REQUEST['thumb']) AND intval($_REQUEST['thumb']))
		{
			$request['type'] = 'thumb';
		}

		if (!empty($_REQUEST['id']) AND intval($_REQUEST['id']))
		{
			$request['id'] = $_REQUEST['id'];
			$api = Api_InterfaceAbstract::instance();
			try
			{
				set_error_handler(array($this, 'handleImageError'), E_ALL | E_STRICT ) ;
				$fileInfo = $api->callApi('content_attach', 'fetchImage', $request);
			}
			catch(Exception $e)
			{
				//just end quietly
				return '';
			}
		}
		else if (!empty($_REQUEST['filedataid']) AND intval($_REQUEST['filedataid']))
		{
			$request['id'] = $_REQUEST['filedataid'];
			$api = Api_InterfaceAbstract::instance();
			try
			{
				set_error_handler(array($this, 'handleImageError'), E_ALL | E_STRICT ) ;
				$fileInfo = $api->callApi('filedata', 'fetchImageByFiledataid', $request);
			}
			catch(Exception $e)
			{
				//just end quietly
				return '';
			}
		}
		else if (!empty($_REQUEST['photoid']) AND intval($_REQUEST['photoid']))
		{
			$request['id'] = $_REQUEST['photoid'];
			$api = Api_InterfaceAbstract::instance();
			try
			{
				set_error_handler(array($this, 'handleImageError'), E_ALL | E_STRICT ) ;
				$fileInfo = $api->callApi('content_photo', 'fetchImageByPhotoid', $request);
			}
			catch(Exception $e)
			{
				//just end quietly
				return '';
			}
		}
		else if (!empty($_REQUEST['linkid']) AND intval($_REQUEST['linkid']))
		{
			$request['id'] = $_REQUEST['linkid'];
			$request['includeData'] = false;
			$api = Api_InterfaceAbstract::instance();
			try
			{
				set_error_handler(array($this, 'handleImageError'), E_ALL | E_STRICT ) ;
				$fileInfo = $api->callApi('content_link', 'fetchImageByLinkId', $request);
			}
			catch(Exception $e)
			{
				//just end quietly
				return '';
			}
		}
		else if (!empty($_REQUEST['attachid']) AND intval($_REQUEST['attachid']))
		{
			$request['id'] = $_REQUEST['attachid'];
			$api = Api_InterfaceAbstract::instance();
			try
			{
				set_error_handler(array($this, 'handleImageError'), E_ALL | E_STRICT ) ;
				$fileInfo = $api->callApi('content_attach', 'fetchImage', $request);
			}
			catch(Exception $e)
			{
				//just end quietly
				return '';
			}
		}
		else if (!empty($_REQUEST['channelid']) AND intval($_REQUEST['channelid']))
		{
			$request['id'] = $_REQUEST['channelid'];
			$api = Api_InterfaceAbstract::instance();
			try
			{
				set_error_handler(array($this, 'handleImageError'), E_ALL | E_STRICT ) ;
				$fileInfo = $api->callApi('content_channel', 'fetchChannelIcon', $request);
			}
			catch(Exception $e)
			{
				//just end quietly
				return '';
			}
		}
		else
		{
			return '';
		}

		if (!empty($fileInfo['filedata']))
		{
			header('ETag: "' . $fileInfo['filedataid'] . '"');
			header('Accept-Ranges: bytes');
			header('Content-transfer-encoding: binary');
			header("Content-Length: " . $fileInfo['filesize'] );
			header("Content-Disposition: inline; filename=\"image_" . $fileInfo['filedataid'] .  "." . $fileInfo['extension'] . "\"");
			header('Cache-control: max-age=31536000, private');
			header('Expires: ' . gmdate("D, d M Y H:i:s", time() + 31536000) . ' GMT');
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $fileInfo['dateline']) . ' GMT');
			foreach ($fileInfo['headers'] as $header)
			{
				header($header);
			}
			echo $fileInfo['filedata'];
		}
	}

	/**If there is an error, there's little we can do. We have a 1px file. Let's return that with a header so the
	 * client won't request it again soon;
	 **/
	public function handleImageError($error)
	{

		$location = pathinfo(__FILE__, PATHINFO_DIRNAME);

		if (file_exists($location . '/../../../../images/1px.png'))
		{
			$contents = file_get_contents($location . '/../../../../images/1px.png');
		}
		else
		{
			die('');
		}
		header('Content-Type: image/png');
		header('Accept-Ranges: bytes');
		header('Content-transfer-encoding: binary');
		header("Content-Length: " . strlen($contents) );
		header("Content-Disposition: inline; filename=\"1px.png\"");
		header('Cache-control: max-age=31536000, private');
		header('Expires: ' . gmdate("D, d M Y H:i:s", time() + 31536000) . ' GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
		die($contents);
	}


	/**This is called on a delete- only used by the blueimp slider and doesn't do anything
	 ***/
	public function actionDelete()
	{
		//Note that we shouldn't actually do anything here. If the filedata record isn't
		//used it will soon be deleted.
		$contents = '';
		header('Content-Type: image/png');
		header('Accept-Ranges: bytes');
		header('Content-transfer-encoding: binary');
		header("Content-Length: " . strlen($contents) );
		header("Content-Disposition: inline; filename=\"1px.png\"");
		header('Cache-control: max-age=31536000, private');
		header('Expires: ' . gmdate("D, d M Y H:i:s", time() + 31536000) . ' GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
		die($contents);
	}

	/** gets a gallery and returns in json format for slideshow presentation.
	*
	***/
	public function actionGallery()
	{
		//We need a nodeid
		if (!empty($_REQUEST['nodeid']))
		{
			$nodeid = $_REQUEST['nodeid'];
		}
		else if (!empty($_REQUEST['id']))
		{
			$nodeid = $_REQUEST['id'];
		}
		else
		{
			return '';
		}

		//get the raw data.
		$api = Api_InterfaceAbstract::instance();

		$config = vB5_Config::instance();
		$gallery = array('photos' => array());
		switch (intval($nodeid))
		{
			case 0:
			case -1: //All Videos
				throw new vB_Exception_Api('invalid_request');
			case -2: //All non-Album photos and attachments
				if ((empty($_REQUEST['userid']) OR !intval($_REQUEST['userid'])) AND
					(empty($_REQUEST['channelid']) OR !intval($_REQUEST['channelid'])))
				{
					throw new vB_Exception_Api('invalid_request');
				}
				$galleryData = $api->callApi('profile', 'getSlideshow', array(
					array(
						'userid' => isset($_REQUEST['userid']) ? intval($_REQUEST['userid']) : 0,
						'channelid' => isset($_REQUEST['channelid']) ? intval($_REQUEST['channelid']) : 0,
						'dateFilter' => isset($_REQUEST['dateFilter']) ? $_REQUEST['dateFilter'] : '',
						'searchlimit' => isset($_REQUEST['perpage']) ? $_REQUEST['perpage'] : '',
						'startIndex' => isset($_REQUEST['startIndex']) ? $_REQUEST['startIndex'] : ''
					)
				));

				if (empty($galleryData))
				{
					return array();
				}

				$phraseApi = vB5_Template_Phrase::instance();

				foreach($galleryData AS $photo)
				{
					$titleVm = $photo['parenttitle'];
					$route = $photo['routeid'];
					if($photo['parenttitle'] == 'No Title' AND $photo['parentsetfor'] > 0)
					{
						$titleVm = $phraseApi->getPhrase('visitor_message_from_x', array($photo['authorname']));
						$route = 'visitormessage';
					}
					$userLink =  $config->baseurl . '/' . $api->callApi('route', 'getUrl', array('route' => 'profile',
						'data' => array('userid' => $photo['userid'], 'username' => $photo['authorname']), 'extra' => array()));
					$topicLink = $config->baseurl . '/' . $api->callApi('route', 'getUrl', array('route' => $route,
						'data' => array('title' => $titleVm, 'nodeid' => $photo['parentnode']), 'extra' => array()));
					$title = $photo['title'] ;
					$photoTypeid = vB_Types::instance()->getContentTypeID('vBForum_Photo');
					$attachTypeid = vB_Types::instance()->getContentTypeID('vBForum_Attach');
					if ($photo['contenttypeid'] === $photoTypeid) {
						$queryVar = 'photoid';
					} else if ($photo['contenttypeid'] === $attachTypeid) {
						$queryVar = 'id';
					}
					$gallery['photos'][] = array('title' => $title,
						'url' => $config->baseurl . '/filedata/fetch?' . $queryVar . '=' . intval($photo['nodeid']),
						'thumb' => $config->baseurl . '/filedata/fetch?' . $queryVar . '=' . intval($photo['nodeid']) . "&thumb=1",
						'links' => $phraseApi->getPhrase('photos_by_x_in_y_linked', array($userLink, $photo['authorname'],
							$topicLink, htmlspecialchars($titleVm) )) . "<br />\n");
				}
				$this->sendAsJson($gallery);
				return;

			default:
				$galleryData = $api->callApi('content_gallery', 'getContent', array('nodeid' => $nodeid));
				if (!empty($galleryData) AND !empty($galleryData[$nodeid]['photo']))
				{
					foreach($galleryData[$nodeid]['photo'] AS $photo)
					{
						$gallery['photos'][] = array('title' => $photo['title'],
							'url' => $config->baseurl . '/filedata/fetch?photoid=' . intval($photo['nodeid']),
							'thumb' => $config->baseurl . '/filedata/fetch?photoid=' . intval($photo['nodeid']) . "&thumb=1",
						);
					}
					$this->sendAsJson($gallery);
				}
				return;
		}
	}
}
