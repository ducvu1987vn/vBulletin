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
 * vB_Api_Profile
 *
 * @package vBApi
 * @author ebrown
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Api_Profile extends vB_Api
{
	protected function __construct()
	{
		parent::__construct();
		$this->assertor = vB::getDbAssertor();
		$this->imageHandler = vB_Image::instance();
	}

	/**
	 *
	 *
	 ***/
	protected function saveUpload($filearray, $fileContents, $filesize, $extension)
	{
		$assertor = vB::getDbAssertor();
		$datastore = vB::getDatastore();
		$options = $datastore->get_value('options');
		$config = vB::getConfig();
		$usercontext = vB::getUserContext();
		$userid = vB::getCurrentSession()->get('userid');

		//make sure there's a place to put attachments.
		if ($options['attachfile'] AND
			(empty($options['attachpath']) OR !file_exists($options['attachpath']) OR !is_writable($options['attachpath']) OR !is_dir($options['attachpath'])))
		{
			throw new Exception('invalid_attachment_storage');
		}

		//make sure the file is good.
		if (!$this->imageHandler->verifyImageFile($fileContents))
		{
			unlink($filearray['tmp_name']);
			throw new Exception('dangerous_image_rejected');
		}

		//We check to see if this file already exists.
		$filehash = md5($fileContents);

		$fileCheck = $assertor->getRow('vBForum:getFiledataWithThumb', array(
			'filehash' => $filehash,
			'filesize' => $filesize
		));

		// Does filedata already exist?
		if (empty($fileCheck))
		{
			if (!$usercontext->canUpload($filesize, $extension))
			{
				unlink($filearray['tmp_name']);
				throw new Exception('cannot_create_file');
			}

			//Get the image size information.
			$timenow =  vB::getRequest()->getTimeNow();

			$filesize = strlen($fileContents);
			$data = array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'userid'    => $userid,
				'dateline'  => $timenow,
				'filesize'  => $filesize,
				'filehash'  => $filehash,
				'extension' => $extension,
				'refcount'  => 0,
				'width'     => $filearray['width'],
				'height'    => $filearray['height'],
			);
			$thumbnail_data = array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'resize_dateline' => $timenow,
				'resize_filesize' => $filearray['filesize_thumb'],
				'resize_width'    => $filearray['width_thumb'],
				'resize_height'   => $filearray['height_thumb'],
				'resize_type'     => 'thumb',
			);

			//Looks like we're ready to store. But do we put it in the database or the filesystem?
			if ($options['attachfile'])
			{
				//We name the files based on the filedata record, but we don't have that until we create the record. So we need
				// to do an insert, then create/move the files.
				$filedataid = $assertor->assertQuery('filedata', $data);

				if (is_array($filedataid))
				{
					$filedataid = $filedataid[0];
				}

				if (!intval($filedataid))
				{
					throw new Exception('unable_to_add_filedata');
				}

				if ($options['attachfile'] == ATTACH_AS_FILES_NEW) // expanded paths
				{
					$path = $options['attachpath'] . '/' . implode('/', preg_split('//', $userid,  -1, PREG_SPLIT_NO_EMPTY)) . '/';
				}
				else
				{
					$path = $options['attachpath'] . '/' . $userid . '/';
				}

				file_put_contents($path . $filedataid . '.thumb', $filearray['filedata_thumb']);
				move_uploaded_file ($filearray['tmp_name'] , $path . $filedataid . '.attach');
			}
			else
			{
				//We put the file contents into the data record.
				$data['filedata'] = file_get_contents($filearray['tmp_name']);
				$thumbnail_data['resize_filedata'] = $filearray['filedata_thumb'];
				$filedataid = $assertor->assertQuery('filedata', $data);

				if (is_array($filedataid))
				{
					$filedataid = $filedataid[0];
				}

			}

			$assertor->assertQuery('vBForum:filedataresize', $thumbnail_data);

			$result = array(
				'filedataid' => $filedataid,
				'filesize'   => $filesize,
				'thumbsize'  => $filearray['resize_filesize'],
				'extension'  => $extension,
				'headers'    => vB_Library::instance('content_attach')->getAttachmentHeaders($extension),
			);
		}
		else
		{
			// file already exists so we are not going to insert a new one
			$filedataid = $fileCheck['filedataid'];
			$result = array(
				'filedataid' => $filedataid,
				'filesize'   => $fileCheck['filesize'],
				'thumbsize'  => $fileCheck['resize_filesize'],
				'extension'  => $extension,
				'headers'    => vB_Library::instance('content_attach')->getAttachmentHeaders($extension),
			);
		}

		return $result;
	}

	/** return font information for profile customization
	*
	*	@return		array with two elements- fontsizes and fontnames.
	*
	**/
	public function getAllowedFonts()
	{
		$options = vB::getDatastore()->get_value('options');
		return array('fontsizes' => $this->buildSelectOptions($options['usercss_allowed_font_sizes']),
			'fontnames' => $this->buildSelectOptions($options['usercss_allowed_fonts']));
	}

	/**
	 * Builds the array for various admin-controlled select options (font sizes, etc).
	 * Determines the CSS value and internal phrase key if there is one.
	 *
	 * @param	string	Raw string. Line break and pipe delimited.
	 *
	 * @return	array	Array prepared for select building
	 */
	protected function buildSelectOptions($inputString)
	{
		$lines = preg_split("/(\n|\r\n|\r)/", $inputString, -1, PREG_SPLIT_NO_EMPTY);

		$output = array();
		foreach ($lines AS $line)
		{
			$parts = explode('|', $line);
			$key = trim($parts[0]);
			$value = isset($parts[1]) ? trim($parts[1]) : $key;
			$output["$key"] = $value;
		}

		return $output;
	}


	/** This function gets the avatar for a user.
	*
	*	@param	int	default- the userid. If not specified, get from request or use currentuser.
	*
	*	@return	mixed	array, includes avatarpath- the path from baseurl_core
	**/
	public function getAvatarUrl($userid = false)
	{
		if (!$userid)
		{
			if (empty($_REQUEST['userid']))
			{
				$userid = vB::getUserContext()->fetchUserId();
			}
			else
			{
				$userid = $_REQUEST['userid'];
			}

		}
		$result = vB_Api::instanceInternal('user')->fetchAvatar($userid);
		return $result;
	}

	/** Get the default avatars- creates the profile UI tab
	 *
	 **/
	public function getDefaultAvatars()
	{
		$avatars = $this->assertor->getRows('vBForum:avatar', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
		'imagecategoryid' => 3), 'displayorder');

		if (empty($avatars) OR !empty($avatars['errors']))
		{
			return array();
		}
		$userPosts = vB::getCurrentSession()->fetch_userinfo();
		$userPosts = $userPosts['posts'];

		foreach ($avatars as $key => $avatar)
		{
			if ($avatar['minimumposts'] > $userPosts)
			{
				unset ($avatars[$key]);
			}
		}
		return $avatars;
	}


	/** Import an avatar from an URL and set it to be this user's custom avatar
	 *
	 * 	 *	@param	mixed	either an object, or a $_FILE array
	 *
	 *	@return	mixed	an array- which can have $errors or avatarpath- the path from baseurl_core
	 **/
	public function uploadUrl($url, $data = array())
	{
		if (!defined('ATTACH_AS_FILES_NEW'))
		{
			//Leave for consistency with admincp
			define('ATTACH_AS_FILES_NEW', 2);
		}

		$imageHandler = vB_Image::instance();

		$usercontext = vB::getUserContext();

		//Only logged-in-users can upload files
		if (
			!$usercontext->fetchUserId()
				OR
			!$usercontext->hasPermission('genericpermissions', 'canuseavatar')
				OR
			!$usercontext->hasPermission('genericpermissions', 'canmodifyprofile')
		)
		{
			throw new vB_Exception_API('no_permission_use_avatar');
		}

		//Did we get a valid url?
		if (empty($url))
		{
			return array('error' => 'upload_invalid_url');
		}

		//add @ to suppress warnings caused by invalid url
		$fileContents = @file_get_contents($url);

		if ($fileContents == false)
		{
			throw new vB_Exception_API('upload_invalid_url');
		}

		$pathinfo = pathinfo($url);
		$data['crop']['org_file_info'] = $pathinfo;
		if ($fileContents !== FALSE AND !empty($pathinfo))
		{
			$extension_map = $imageHandler->getExtensionMap();
			if (empty($pathinfo['extension']) OR !array_key_exists(strtolower($pathinfo['extension']), $extension_map))
			{
				//try to get from the headers.
				try
				{
					$headers = get_headers($url);
				}
				catch(exception $e)
				{
					throw new vB_Exception_API('invalid_request');
				}
				//we should have a header content-type
				foreach ($headers as $key => $header)
				{
					$header = strtolower($header);
					if (strpos($header,'content-type') !== false)
					{
						//we should have something like 'Content-Type: image/jpeg".
						$typeData = explode(':', $header);
						$typeData = explode('/', $typeData[1]);
						if ((count($typeData) == 2) AND array_key_exists(trim($typeData[1]), $extension_map))
						{
							$extension = strtolower($extension_map[trim($typeData[1])]);
							break;
						}
					}
				}
			}
			else
			{
				$extension = $pathinfo['extension'];
			}

			//did we get an extension?
			if (empty($extension))
			{
				throw new vB_Exception_API('invalid_data');
			}
			$tempdir = sys_get_temp_dir();
			//Make a local copy
			$filename = $tempdir . '/' . time() . '.' . $extension;
			file_put_contents($filename, $fileContents);
			return vB_Library::instance('user')->uploadAvatar($filename, empty($data['crop']) ? array() : $data['crop']);
		}
	}


	/** Upload an avatar and set it as the user's profile image.
	 *
	 *	@param	mixed	either an object, or a $_FILE array
	 *
	 *	@return	mixed	an array- which can have $errors or avatarpath- the path from baseurl_core
	 **/
	public function upload($file, $data = array())
	{
		if (!defined('ATTACH_AS_FILES_NEW'))
		{
			//Leave for consistency with admincp
			define('ATTACH_AS_FILES_NEW', 2);
		}

		$usercontext = vB::getUserContext();
		if (
			!$usercontext->fetchUserId()
				OR
			!$usercontext->hasPermission('genericpermissions', 'canuseavatar')
				OR
			!$usercontext->hasPermission('genericpermissions', 'canmodifyprofile')
		)
		{
			throw new vB_Exception_API('no_permission_use_avatar');
		}

		//We can get either an uploaded file or an object. If we have an object let's make it into an array.

		$tempdir = sys_get_temp_dir();

		if (is_object($file) AND isset($file->name))
		{
			$filearray = array('name' => $file->name, 'size' => $file->size,'type' => $file->type);
			$pathinfo = pathinfo($file->name);
			$data['org_file_info'] = $pathinfo;
			$extension = $pathinfo['extension'];
			if (isset($file->contents) AND !empty($file->contents))
			{
				$filename = $tempdir . '/' . time() . $extension;
				file_put_contents($filename, $file->contents);
				$filearray['tmp_name'] = $filename;
				$fileContents = $file->contents;
				$filesize = strlen ($file->contents);
			}
		}
		else
		{
			if (!file_exists($file['tmp_name']))
			{
				// Encountered PHP upload error
				if (!($maxupload = @ini_get('upload_max_filesize')))
				{
					$maxupload = 10485760;
				}
				$maxattachsize = vb_number_format($maxupload, 1, true);

				switch($file['error'])
				{
					case '1': // UPLOAD_ERR_INI_SIZE
					case '2': // UPLOAD_ERR_FORM_SIZE
						throw new vB_Exception_Api('upload_file_exceeds_php_limit', $maxattachsize);
						break;
					case '3': // UPLOAD_ERR_PARTIAL
						throw new vB_Exception_Api('upload_file_partially_uploaded');
						break;
					case '4':
						throw new vB_Exception_Api('upload_file_failed');
						break;
					case '6':
						throw new vB_Exception_Api('missing_temporary_folder');
						break;
					case '7':
						throw new vB_Exception_Api('upload_writefile_failed');
						break;
					case '8':
						throw new vB_Exception_Api('upload_stopped_by_extension');
						break;
					default:
						throw new Exception('Upload failed. PHP upload error: ' . intval($file['error']));
				}
			}
			$filearray = $file;
			$data['org_file_info'] = pathinfo($file['name']);
			$filesize = filesize($file['tmp_name']);

			$fileContents = file_get_contents($file['tmp_name']);
			$filename = $file['tmp_name'];
		}

		return vB_Library::instance('user')->uploadAvatar($filename, empty($data) ? array() : $data);
	}

	public function resetAvatar($type = 'avatar')
	{
		$usercontext = vB::getUserContext();
		if (
			!($userid = $usercontext->fetchUserId())
				OR
			!$usercontext->hasPermission('genericpermissions', 'canuseavatar')
				OR
			!$usercontext->hasPermission('genericpermissions', 'canmodifyprofile')
		)
		{
			throw new vB_Exception_API('no_permission_use_avatar');
		}

		$userpic = new vB_DataManager_Userpic_Avatar(vB::get_registry(), vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED);
		$userpic->condition = array('userid'  => $userid);
		$userpic->delete();

		if ($userpic->has_errors(false))
		{
			throw $userpic->get_exception();
		}
		return vB_Api::instanceInternal('user')->fetchAvatar($userid, $type);
	}

	public function cropFileData($filedataid, $data = array())
	{
		$usercontext = vB::getUserContext();
		if (
			!$usercontext->fetchUserId()
				OR
			!$usercontext->hasPermission('genericpermissions', 'canuseavatar')
				OR
			!$usercontext->hasPermission('genericpermissions', 'canmodifyprofile')
		)
		{
			throw new vB_Exception_API('no_permission_use_avatar');
		}

		//Did we get a valid url?
		if (empty($filedataid))
		{
			throw new vB_Exception_API('upload_invalid_url');
		}

		//add @ to suppress warnings caused by invalid url
		$filedatas = vB_Api::instanceInternal('filedata')->fetchFiledataByid(array($filedataid));
		$filedata = empty($filedatas[$filedataid]) ? false : $filedatas[$filedataid];
		if (empty($filedata))
		{
			throw new vB_Exception_API('upload_invalid_url');
		}
		$imageHandler = vB_Image::instance();
		$extension_map = $imageHandler->getExtensionMap();
		if(!array_key_exists(strtolower($filedata['extension']), $extension_map))
		{
			throw new vB_Exception_API('error_thumbnail_notcorrectimage');
		}

		$tempdir = sys_get_temp_dir();
		//Make a local copy
		$filename = $tempdir . '/' . time() . '.' . $filedata['extension'];
		file_put_contents($filename, $filedata['filedata']);
		$crop = array();
		if (!empty($data) AND is_array($data) AND array_key_exists('crop', $data))
		{
			$crop = $data['crop'];
		}
		return vB_Library::instance('user')->uploadAvatar($filename, $crop);
	}

	/**return the placeable content types ***/
	public function fetchContentTypes()
	{
		return vB_Types::instance()->getContentTypes();

	}

	/** returns existing Albums- in the Albums Channel
	*
	*	@param	integer		optional userid- normally read from request.
	*
	*	@return	mixed		array including
	***/
	public function fetchAlbums($userid = false)
	{
		if (!$userid)
		{
			if (empty($_REQUEST['userid']))
			{
				$userid = vB::getUserContext()->fetchUserId();
			}
			else
			{
				$userid = $_REQUEST['userid'];
			}

		}

		$nodeApi = vB_Api::instanceInternal('node');
		//get the list of albums from the node api
		$galleries = $nodeApi->listAlbums($userid,  1, 500);
		return $galleries;

	}

	/** Lists the media for a user
	*
	*	@param	array
	* 	@param	int
	*	@param	int
	* 	@param	mixed, optional- sort (ASC/DESC), time limit, type (photo/video/all)
	*
	*	@return	mixed	array of media data- format is getContent
	*
	***/
	public function fetchMedia($mediaFilter, $page = 1, $perpage = 12, $params = array())
	{
		$currentUser = vB::getCurrentSession()->get('userid');

		$fetchContent = true;
		if (isset($mediaFilter['userId']) AND intval($mediaFilter['userId']))
		{
			// we are filtering per user
			$hashKey = "vB_ProfMedia_{$mediaFilter['userId']}" . '_' . $currentUser;
			$events = array('fUserContentChg_' . $mediaFilter['userId'], 'userPrivacyChg_' . $mediaFilter['userId'], 'followChg_' . $mediaFilter['userId']);

			// let's filter by privacy
			$userInfo = vB_Api::instanceInternal('user')->fetchProfileInfo($mediaFilter['userId']);
			if (!$userInfo['showPhotos'] AND !$userInfo['showVideos'])
			{
				$fetchContent = false;
			}
			else if (!$userInfo['showPhotos'])
			{
				$mediaFilter['type'] = 'video';
			}
			else if (!$userInfo['showVideos'])
			{
				$mediaFilter['type'] = 'gallery';
			}
		}
		else if(isset($mediaFilter['channelId']) AND intval($mediaFilter['channelId']))
		{
			// we are filtering per channel
			$hashKey = "vB_ChannelMedia_{$mediaFilter['channelId']}" . '_' . $currentUser;
			// TODO: check that this event is triggered when modifying the content of a subchannel
			$events = array('nodeChg_' . $mediaFilter['channelId']);
		}
		else
		{
			throw new vB_Exception_Api('invalid_data');
		}

		$cache = vB_Cache::instance();
		$page = max(1, intval($page));
		$perpage = max(1, intval($perpage));
		$data = $cache->read($hashKey);

		if ($data === false AND $fetchContent)
		{
			$nodeQry = $this->assertor->assertQuery('vBForum:fetchProfileMedia', $mediaFilter);
			$data = array();
			$childnodes = array();

			if ($nodeQry->valid())
			{
				foreach($nodeQry AS $node)
				{
					$data[$node['nodeid']] = $node;
					$childnodes[$node['childnode']] = $node['nodeid'];
				}
			}

			if (!empty($childnodes))
			{
				//The childnodes point to either attachments or photos.  Let's try photos first.
				$childQry = $this->assertor->assertQuery('vBForum:photo', array(vB_db_Query::TYPE_KEY => vB_db_Query::QUERY_SELECT,
					'nodeid' => array_keys($childnodes)));
				if ($childQry->valid())
				{
					foreach($childQry AS $child)
					{
						$parentNodeId = $childnodes[$child['nodeid']];
						$data[$parentNodeId]['filedataid'] = $child['filedataid'];
						$data[$parentNodeId]['photoid'] = $child['nodeid'];
						unset($childnodes[$child['nodeid']]);
					}
				}

				//And now attach records
				if (!empty($childnodes))
				{
					$childQry = $this->assertor->assertQuery('vBForum:attach', array(vB_db_Query::TYPE_KEY => vB_db_Query::QUERY_SELECT,
						'nodeid' => array_keys($childnodes)));

					if ($childQry->valid())
					{
						foreach($childQry AS $child)
						{
							$parentNodeId = $childnodes[$child['nodeid']];
							$data[$parentNodeId]['filedataid'] = $child['filedataid'];
							$data[$parentNodeId]['attachid'] = $child['nodeid'];
						}
					}
				}
			}

			$cache->write($hashKey, $data, 30, $events);
		}
		else if (!$fetchContent)
		{
			$data = array();
			$cache->write($hashKey, $data, 30, $events);
		}

		$nodes = array_slice($data,($page - 1) * $perpage, $perpage);
		$count = count($data);
		$cancreateVideo = 0;
		$cancreatePhoto = 0;
		if (vB::getUserContext()->isAdministrator() OR (!empty($mediaFilter['userId']) AND ($currentUser == $mediaFilter['userId'])))
		{
			$albumChannel = vB_Library::instance('node')->fetchAlbumChannel();

			if (vB::getUserContext()->hasPermission('forumpermissions', 'canview', $albumChannel))
			{
				$createPerms = vB::getUserContext()->getCanCreate($albumChannel);
				$cancreateVideo = $createPerms['vbforum_video'];
				$cancreatePhoto = $createPerms['vbforum_gallery'];
			}

		}
		else if (!empty($mediaFilter['channelId']))
		{
			$albumChannel = vB_Library::instance('node')->fetchAlbumChannel();

			if (vB::getUserContext()->hasPermission('forumpermissions', 'canview', $albumChannel))
			{
				$createPerms = vB::getUserContext()->getCanCreate($mediaFilter['channelId']);
				$cancreateVideo = $createPerms['vbforum_video'];
				$cancreatePhoto = $createPerms['vbforum_gallery'];
			}

		}

		return array('nodes' => $nodes, 'count' => $count, 'paging' => $this->getMediaNav($page, $perpage, $count),
			'cancreateVideo' => $cancreateVideo, 'cancreatePhoto' => $cancreatePhoto);
	}

	/**
	 * Get languages for current user settings
	 * @param	int		Id of the default user language
	 *
	 * @return	mixed	Array of languages.
	 */
	public function getLanguages($userLanguageId = 0)
	{
		$userContext = vB::getUserContext();
		$languages = vB::getDatastore()->getValue('languagecache');
		$userLanguages = array();
		foreach($languages as $language)
		{
			if ($language['userselect'] OR $userContext->hasPermission('adminpermissions', 'cancontrolpanel'))
			{
				$userLanguages[$language['languageid']] = array('title' => $language['title'], 'userselect' => $language['userselect']);
				$userLanguages[$language['languageid']]['selected'] = ($userLanguageId == $language['languageid'] ? true : false);
			}
		}

		return $userLanguages;
	}

	/**
	 * Get styles for current user settings
	 *
	 * @return mixed	Array of styles
	 */
	public function getStyles()
	{
		$userContext = vB::getUserContext();
		$stylelib = vB_Library::instance('Style');
		$styles = $stylelib->fetchStyles(false, false);
		$userStyles = array();
		foreach ($styles as $style)
		{
			if ($style['userselect'] OR $userContext->hasPermission('adminpermissions', 'cancontrolpanel'))
			{
				$userStyles[] = $style;
			}
		}

		return $userStyles;
	}


	/** Lists the media navigationfor a user
	 *
	 *	@param	int
	 * 	@param	int
	 *	@param	int
	 * 	@param	mixed, optional- sort (ASC/DESC), time limit, type (photo/video/all), and currentPage
	 *
	 *	@return	mixed	array include previous, next, currentPage, totalcount and totalpages
	 *
	 ***/
	protected function getMediaNav($page, $perpage, $qty)
	{
		$paging = array();
		$pageCount = ceil($qty / $perpage);
		$paging['previous'] = ($page > 1) ? 1 : 0;
		$paging['next'] = ($page < $pageCount) ? 1 : 0;
		$paging['totalpages'] = $pageCount;
		$paging['currentpage'] = $page;
		$paging['totalcount'] = $qty;
		return $paging;
	}

	/** Returns the album data for presentation on the media detail page for either a node, or one of the two pseudo-Albums
	 *
	 * @param int
	 * @param int $page
	 * @param int $perpage
	 * @param int $userid
	 * @param bool $dateFilter
	 *
	 * @return mixed array with key nodeid, node values and photo and/or attachment sub-arrays
	 */
	public function getAlbum($filters)
	{
		$nodeid = (isset($filters['nodeid']) AND !empty($filters['nodeid'])) ? $filters['nodeid'] : 0;
		$page = (isset($filters['page']) AND !empty($filters['page'])) ? intval($filters['page']) : 1;
		$perpage = (isset($filters['perpage']) AND !empty($filters['perpage'])) ? intval($filters['perpage']) : 60;
		$userid = (isset($filters['userid']) AND !empty($filters['userid'])) ? intval($filters['userid']) : 0;
		$channelid = (isset($filters['channelid']) AND !empty($filters['channelid'])) ? intval($filters['channelid']) : 0;
		$dateFilter = (isset($filters['dateFilter']) AND !empty($filters['dateFilter'])) ? $filters['dateFilter'] : false;

		switch (intval($nodeid))
		{
			case 0:
				throw new vB_Exception_Api('invalid_request');
				continue;
			case -1: //All Videos
				if (!intval($userid))
				{
					throw new vB_Exception_Api('invalid_request');
				}
					if (!intval($perpage) OR (intval($perpage) < 1))
				{
					$perpage = 60;
				}

				if (!intval($page) OR (intval($page) < 1))
				{
					$page = 1;
				}
				$params = array('userid' => $userid, 'dateFilter' => $dateFilter, vB_dB_Query::PARAM_LIMIT => $perpage, vB_dB_Query::PARAM_LIMITPAGE => $page);
				$videoQry = $this->assertor->assertQuery('vBForum:fetchVideoNodes', $params);

				if (!$videoQry->valid())
				{
					return array();
				}
				$videoNodes = array();
				foreach($videoQry AS $node)
				{
					$videoNodes[]= $node['nodeid'];
				}
				$videoCount = $this->assertor->getRow('vBForum:fetchVideoCount' , $params);
				$videoCount = $videoCount['count'];

				if ($videoCount)
				{
					$pagenav = $this->getMediaNav($page, $perpage, $videoCount);
				}
				else
				{
					$pagenav = $this->getMediaNav(1, $perpage, $videoCount);
				}

				$videoInfo[$nodeid] = array('nodeid' => $nodeid, 'title' => '', 'videos' => vB_Library::instance('node')->getFullContentforNodes($videoNodes),
					'pagenav' => $pagenav, 'videocount' => $videoCount);
				return $videoInfo;
				continue;
			case -2: //All non-Album photos and attachments
				//run the query.

				if (!intval($perpage) OR (intval($perpage) < 1))
				{
					$perpage = 60;
				}

				if (!intval($page) OR (intval($page) < 1))
				{
					$page = 1;
				}
				$params = array('userid' => $userid, 'channelid' => $channelid, 'dateFilter' => $dateFilter, vB_dB_Query::PARAM_LIMIT => $perpage, vB_dB_Query::PARAM_LIMITPAGE => $page);
				$photoCount = $this->assertor->getRow('vBForum:fetchPostedPhotoCount' , $params);

				$photoCount = $photoCount['count'];
				$photoQuery = $this->assertor->assertQuery('vBForum:fetchGalleryPhotos' , $params);
				if (!$photoQuery->valid())
				{
					return array();
				}

				$photoType = vB_Types::instance()->getContentTypeID('vBForum_Photo');
				$phrases = vB_Api::instanceInternal('phrase')->fetch(array('posted_photos'));

				$photo = current($photoQuery);
				$count = 0;

				if ($photoCount)
				{
					$pagenav = $this->getMediaNav($page, $perpage, $photoCount);
				}
				else
				{
					$pagenav = $this->getMediaNav(1, $perpage, $photoCount);
				}
				$photoInfo[$nodeid] = array('nodeid' => $nodeid, 'title' => $phrases['posted_photos'], 'photo' => array(), 'pagenav' => $pagenav);
				foreach ($photoQuery AS $photo)
				{
					// is it a photo or attachment?
					if ($photo['contenttypeid'] == $photoType)
					{
						$photo['isAttach'] = false;
						$count++;
					}
					else
					{	
						$photo['isAttach'] = true;
						$count++;
					}
					$photoInfo[$nodeid]['photo'][$photo['nodeid']] = $photo;
				}
				$photoInfo[$nodeid]['photocount'] = $count;
				return $photoInfo;

				continue;
			default:
				return vB_Api::instanceInternal('node')->getNodeFullContent($nodeid, false, array('attach_options' => array('perpage' => $perpage, 'page' => $page)));
		}
	}

	public function getSlideshow($filters)
	{
		$userid = (isset($filters['userid']) AND !empty($filters['userid'])) ? intval($filters['userid']) : 0;
		$channelid = (isset($filters['channelid']) AND !empty($filters['channelid'])) ? intval($filters['channelid']) : 0;
		$dateFilter = (isset($filters['dateFilter']) AND !empty($filters['dateFilter'])) ? $filters['dateFilter'] : false;
		$searchlimit = (isset($filters['searchlimit']) AND !empty($filters['searchlimit'])) ? $filters['searchlimit'] : 60;
		$startIndex = (isset($filters['startIndex']) AND !empty($filters['startIndex'])) ? $filters['startIndex'] : 0;
		$pagelimit =  floor($startIndex /$searchlimit) + 1;
		
		$currentUser = vB::getCurrentSession()->get('userid');
		if (isset($filters['userid']) AND intval($filters['userid']))
		{
			// we are filtering per user
			$hashKey = "vB_ProfDefaultAlbumSlideShow_{$filters['userid']}" . '_' . $currentUser . '_' . $pagelimit;
			$events = array('fUserContentChg_' . $filters['userid']);
		}
		else if(isset($filters['channelid']) AND intval($filters['channelid']))
		{
			// we are filtering per channel
			$hashKey = "vB_ChannelDefaultAlbumSlideShow_{$filters['channelid']}" . '_' . $currentUser . '_' . $pagelimit;
			// TODO: check that this event is triggered when modifying the content of a subchannel
			$events = array('nodeChg_' . $filters['channelid']);
		}
		else
		{
			throw new vB_Exception_Api('invalid_data');
		}

		$params = array(	'userid' => $userid, 
							'channelid' => $channelid, 
							'$dateFilter' => $dateFilter, 
							vB_dB_Query::PARAM_LIMIT => $searchlimit,
							vB_dB_Query::PARAM_LIMITPAGE => $pagelimit
		);
		$cache = vB_Cache::instance();
		$data = $cache->read($hashKey);

		if ($data === false)
		{
			$photoQuery = $this->assertor->assertQuery('vBForum:fetchGalleryPhotos' , $params);
			if (!$photoQuery->valid())
			{
				return array();
			}
			$data = array();
			foreach ($photoQuery AS $photo)
			{
				$data[$photo['nodeid']] = $photo;
			}
			$cache->write($hashKey, $data, 30, $events);
		}
		return $data;

	}
}
