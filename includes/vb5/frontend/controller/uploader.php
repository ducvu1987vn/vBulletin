<?php

class vB5_Frontend_Controller_Uploader extends vB5_Frontend_Controller
{
	protected $api;

	protected $upload_handler;

	public function __construct()
	{
		parent::__construct();
		$this->api = Api_InterfaceAbstract::instance();
	}

	public function actionGetUploader()
	{
		$config = vB5_Config::instance();

		$templater = new vB5_Template('attach_uploader');
		$this->outputPage($templater->render());
	}

	public function actionUrl()
	{
		if (isset($_REQUEST['urlupload']))
		{
			$config = vB5_Config::instance();
			$api = Api_InterfaceAbstract::instance();

			$response = $api->callApi('content_attach', 'uploadUrl', array('url' => $_REQUEST['urlupload']));

			$response['imageUrl'] = $config->baseurl . '/filedata/fetch?filedataid=' . $response['filedataid'];
			$response['thumbUrl'] = $config->baseurl . '/filedata/fetch?filedataid=' . $response['filedataid'] . '&thumb=1';
			$response['deleteUrl'] = $config->baseurl . '/filedata/delete?filedataid=' . $response['filedataid'];

			$this->sendAsJson($response);
		}
	}

	/** This method uploads an image and sets it as the logo in one step **/
	public function actionUploadLogoUrl()
	{
		if (isset($_POST['urlupload']))
		{
			$api = Api_InterfaceAbstract::instance();
			$response = $api->callApi('content_attach', 'uploadUrl', array('url' => $_POST['urlupload']));

			if (!empty($response['errors']))
			{
				$this->sendAsJson($response);
			}

			$response2 = $api->callApi('content_attach', 'setLogo', array('filedataid' => $response['filedataid']));
			if (!empty($response2['errors']))
			{
				$this->sendAsJson($response2);
			}

			$config = vB5_Config::instance();
			$result['imageUrl'] = $config->baseurl . '/filedata/fetch?filedataid=' . $response['filedataid'];
			$result['thumbUrl'] = $config->baseurl . '/filedata/fetch?filedataid=' . $response['filedataid'] . '&thumb=1';
			$result['filedataid'] = $response['filedataid'];
			$this->sendAsJson($result);
		}
	}

	/**Upload a file. Just get the filedata back.
	*
	**/
	public function actionUploadFile()
	{
		if ($_FILES AND !empty($_FILES['file']))
		{
			$api = Api_InterfaceAbstract::instance();

			if (!empty($_REQUEST['uploadFrom']))
			{
				$_FILES['file']['uploadFrom'] = $_REQUEST['uploadFrom'];
			}

			if (!empty($_REQUEST['nodeid']))
			{
				$_FILES['file']['parentid'] = $_REQUEST['nodeid'];
			}

			$response = $api->callApi('content_attach', 'upload', array('file' => $_FILES['file']));

			if (!empty($response['errors']))
			{
				return $this->sendAsJson($response);
			}

			$config = vB5_Config::instance();
			$response['imageUrl'] = $config->baseurl . '/filedata/fetch?filedataid=' . $response['filedataid'];
			$response['thumbUrl'] = $config->baseurl . '/filedata/fetch?filedataid=' . $response['filedataid'] . '&thumb=1';
			$response['deleteUrl'] = $config->baseurl . '/filedata/delete?filedataid=' . $response['filedataid'];
			//$response['filedataid'] = $response['filedataid'];
			$this->sendAsJson($response);
		}
	}

	/**Upload a photo. Return an edit block and the photo URL.
	 *
	 **/
	public function actionUploadPhoto()
	{
		if ($_FILES AND !empty($_FILES) )
		{
			if (!empty($_FILES['file']))
			{
				$fileData = $_FILES['file'];
			}
			else if (!empty($_FILES['files']))
			{
				if (is_array($_FILES['files']['name']))
				{
					$fileData = array('name' => $_FILES['files']['name'][0],
					'type' => $_FILES['files']['type'][0], 'tmp_name' => $_FILES['files']['tmp_name'][0],
					'size' => $_FILES['files']['size'][0], 'error' => $_FILES['files']['error'][0]);
				}
				else
				{
					$fileData = $_FILES['files'];
				}
			}

			if (isset($_POST['galleryid']))
			{
				$galleryid = intval($_POST['galleryid']);
			}
			else
			{
				$galleryid = '';
			}

			if (isset($_POST['uploadFrom']))
			{
				$fileData['uploadFrom'] = $_POST['uploadFrom'];
			}
			else
			{
				$fileData['uploadFrom'] = '';
			}

			$config = vB5_Config::instance();
			$api = Api_InterfaceAbstract::instance();
			$response = $api->callApi('content_attach', 'upload', array('file' => $fileData));
			if (!empty($response['filedataid']))
			{
				$templater = new vB5_Template('photo_edit');
				$imgUrl = $config->baseurl . '/filedata/fetch?filedataid=' . $response['filedataid'] . "&thumb=1";
				$templater->register('imgUrl', $imgUrl);
				$templater->register('filedataid', $response['filedataid']);
				$response['edit'] = $templater->render();
				$response['imgUrl'] = $imgUrl;
				$response['galleryid'] = $galleryid;
			}
			//need this to avoid errors with iframe transport.
			header("Content-type: text/plain");
			$this->sendAsJson($response);
		}
	}

	/** This method uploads an image and sets it as the logo in one step **/
	public function actionUploadLogo()
	{
		if ($_FILES AND !empty($_FILES['file']))
		{
			$api = Api_InterfaceAbstract::instance();
			$response = $api->callApi('content_attach', 'upload', array('file' => $_FILES['file']));

			if (!empty($response['errors']))
			{
				$this->sendAsJson($response);
				return;
			}

			if (empty($response['filedataid']))
			{
				echo 'unknown error';
				return;
			}

			$response2 = $api->callApi('content_attach', 'setLogo', array('filedataid' => $response['filedataid']));
			if (!empty($response2['errors']))
			{
				$this->sendAsJson($response2);
				return;
			}

			$config = vB5_Config::instance();
			$response['imageUrl'] = $config->baseurl . '/filedata/fetch?filedataid=' . $response['filedataid'];
			$response['thumbUrl'] = $config->baseurl . '/filedata/fetch?filedataid=' . $response['filedataid'] . '&thumb=1';
			$response['filedataid'] = $response['filedataid'];
			$this->sendAsJson($response);

		}
		else
		{
			echo "No files to upload";
		}
	}

	/** This method sets an uploaded image as the logo**/
	public function actionSetlogo()
	{
		if (isset($_POST['filedataid']))
		{
			$styleselection = (isset($_POST['styleselection'])) ? trim($_POST['styleselection']) : 'current';
			$api = Api_InterfaceAbstract::instance();
			$response = $api->callApi('content_attach', 'setLogo', array('filedataid' => $_POST['filedataid'], 'styleselection' => $styleselection));
			$this->sendAsJson($response);
		}
	}
	
	public function actionCKEditorInsertImage()
	{
		$options = array(
			'param_name' => 'upload',
			'uploadFrom' => 'CKEditorInsertImage'
		);

		$this->upload_handler = new blueImpUploadHandler($options, $this->api);
		
		header('Pragma: no-cache');
		header('Cache-Control: private, no-cache');
		header('Content-Disposition: inline; filename="files.json"');

		$this->upload_handler->post();

	}

	/** This method uploads an image as filedata and returns an array of useful information including the filedataid and links to the image and the thumbnail **/
	public function actionUpload()
	{
		if (empty($this->upload_handler))
		{
			$this->upload_handler = new blueImpUploadHandler(null, $this->api);
		}

		header('Pragma: no-cache');
		header('Cache-Control: private, no-cache');
		header('Content-Disposition: inline; filename="files.json"');

		switch ($_SERVER['REQUEST_METHOD']) {
		case 'HEAD':
		case 'GET':
			$this->upload_handler->get();
			break;
		case 'POST':
			$this->upload_handler->post();
			break;
		case 'DELETE':
			$this->upload_handler->delete();
			break;
		default:
			header('HTTP/1.0 405 Method Not Allowed');
		}

	}

	/** This method gets a photo edit interface. **/
	public function actionGetPhotoedit()
	{
		//We need a nodeid
		if (empty($_REQUEST['nodeid']) OR ! intval($_REQUEST['nodeid']))
		{
			echo '';
			return;
		}

		$nodeid = intval($_REQUEST['nodeid']);
		$api = Api_InterfaceAbstract::instance();
		$gallery = $api->callApi('content_gallery', 'getContent', array('nodeid' => $nodeid));

		if (empty($gallery) OR !empty($gallery['errors']))
		{
			echo '';
			return;
		}

		$templater = new vB5_Template('gallery_edit');

		if (!empty($gallery[$nodeid]['photo']))
		{
			$templater->register('maxid', max(array_keys($gallery[$nodeid]['photo'])));
		}
		else
		{
			$templater->register('maxid', 0);
		}
		$templater->register('gallery', $gallery[$nodeid]);
		$this->outputPage($templater->render());
	}

	/** This method saves updates to the photo edit interface. **/
	public function actionSavegallery()
	{
		$response = array();
		$input = array(
			'title'		=> (isset($_POST['gallery_title']) ? trim(strval($_POST['gallery_title'])) : ''),
			'rawtext' 	=> (isset($_POST['text']) ? trim(strval($_POST['text'])) : ''),
			'nodeid' 	=> (isset($_POST['nodeid']) ? intval($_POST['nodeid']) : 0),
			'parentid'	=> (isset($_POST['parentid']) ? intval($_POST['parentid']) : 0),
			'reason' 	=> (isset($_POST['reason']) ? trim(strval($_POST['reason'])) : ''),
			'enable_comments' => (isset($_POST['enable_comments']) ? (bool)$_POST['enable_comments'] : false), // Used only when entering blog posts
		);

		if (empty($input['nodeid']) OR !intval($input['nodeid']))
		{
			$response['error'] = 'Invalid Post ID.';
			return $response['error'];
		}

		if (empty($_POST['filedataid'])) {
			$_POST['filedataid'] = array();
		}

		// prepare filedataids array for updateFromWeb
		$filedataids = array();
		foreach ($_POST['filedataid'] AS $filedataid)
		{
			$title_key = "title_$filedataid";
			$filedataids[$filedataid] = (isset($_POST[$title_key])) ? $_POST[$title_key] : '';
		}

		$api = Api_InterfaceAbstract::instance();
		$results = $api->callApi('content_gallery', 'updateFromWeb', array($input['nodeid'], $input, $filedataids));


		if ($results and empty($results['errors']))
		{
			//update tags
			$tags = !empty($_POST['tags']) ? explode(',', $_POST['tags']) : array();
			$tagRet = $api->callApi('tags', 'updateUserTags', array($input['nodeid'], $tags));
		}

		$this->sendAsJson($results);

	}

	/** This sets a profile picture **/
	public function actionUploadProfilepicture()
	{
		//Let's just let the API handle this.
		$api = Api_InterfaceAbstract::instance();
		if (!empty($_FILES) AND !empty($_FILES['profilePhotoFile']))
		{
			$response = $api->callApi('profile', 'upload', array('fileInfo' => $_FILES['profilePhotoFile']));
		}
		elseif (!empty($_POST) AND !empty($_POST['profilePhotoUrl']))
		{
			$response = $api->callApi('profile', 'uploadUrl', array('fileInfo' => false, 'url' => $_POST['profilePhotoUrl']));
		}
		else
		{
			$this->sendAsJson(array('errors' => 'invalid_data'));
		}

		if (!empty($response['errors']))
		{
			$this->sendAsJson($response);
			return;
		}

		if (empty($response['profilepicurl']))
		{
			$this->sendAsJson(array('errors' => 'unknown error'));
			return;
		}

		$this->sendAsJson($response);
	}

	/** This sets a sgocial group/blog picture **/
	public function actionUploadSGIcon()
	{
		//Let's just let the API handle this.
		$api = Api_InterfaceAbstract::instance();
		if (!empty($_FILES['file']))
		{

			if (!empty($_REQUEST['uploadFrom']))
			{
				$_FILES['file']['uploadFrom'] = $_REQUEST['uploadFrom'];
			}

			if (!empty($_REQUEST['nodeid']))
			{
				$_FILES['file']['parentid'] = $_REQUEST['nodeid'];
			}
			
			$response = $api->callApi('content_attach', 'upload', array('file' => $_FILES['file']));

		}
		elseif(!empty($_REQUEST['url']))
		{
			$response = $api->callApi('content_attach', 'uploadUrl', array('url' => $_REQUEST['url']));
		}
		else
		{
			throw new Exception('error_attachment_missing');
		}
		if (!empty($response['errors']))
		{
			return $this->sendAsJson($response);
		}

		$filedataid = $response['filedataid'];
		$response = $api->callApi('content_channel', 'update', array($_REQUEST['nodeid'], array('filedataid' => $response['filedataid'])));

		if (!empty($response['errors']))
		{
			return $this->sendAsJson($response);
		}

		$config = vB5_Config::instance();
		$response = array();
		$response['imageUrl'] = $config->baseurl . '/filedata/fetch?filedataid=' . $filedataid;
		$response['thumbUrl'] = $config->baseurl . '/filedata/fetch?filedataid=' . $filedataid . '&thumb=1';
		$response['deleteUrl'] = $config->baseurl . '/filedata/delete?filedataid=' . $filedataid;
		$this->sendAsJson($response);
	}

}

class blueImpUploadHandler
{
	protected $options;
	private $partials = array();
	private $fileData = array();
	protected $api;
	protected $baseurl;

	function __construct($options=null, $api)
	{
		$this->api = $api;
		$config = vB5_Config::instance();
		$this->baseurl = $config->baseurl;
		$this->options = array(
		    'script_url' => $_SERVER['PHP_SELF'],
		    'param_name' => 'files',
		    // The php.ini settings upload_max_filesize and post_max_size
		    // take precedence over the following max_file_size setting:
		    'max_file_size' => 500000,
		    'min_file_size' => 1,
		    'accept_file_types' => '/.+$/i',
		    'max_number_of_files' => 5,
		    'discard_aborted_uploads' => true,
		    'image_versions' => array(
		        // Uncomment the following version to restrict the size of
		        // uploaded images. You can also add additional versions with
		        // their own upload directories:
		        /*
		           'large' => array(
		           'upload_dir' => dirname(__FILE__).'/files/',
		           'upload_url' => dirname($_SERVER['PHP_SELF']).'/files/',
		           'max_width' => 1920,
		           'max_height' => 1200
		           ),
		        */
		       /* 'thumbnail' => array(
		            'upload_dir' => dirname(__FILE__).'/thumbnails/',
		            'upload_url' => dirname($_SERVER['PHP_SELF']).'/thumbnails/',
		            'max_width' => 80,
		            'max_height' => 80
		        ) */
		        )
		);

		if ($options) {
			$this->options = array_merge($this->options, $options);
		}
	}

	private function get_file_object($file_name) {
		if (array_key_exists($file_name, $this->fileData)) {
			$file = new stdClass();
			$file->name = $file_name;
			$file->size = $this->fileData[$file_name]['filesize'];
			$file->filedataid = $this->fileData[$file_name]['filedataid'];
			$file->url = $this->fileData[$file_name]['url'];
			$file->delete_url = $this->fileData[$file_name]['delete_url'];
			$file->thumb_url = $this->fileData[$file_name]['thumb_url'];
			$file->delete_type = 'DELETE';
			return $file;
		}
		return null;
	}

	private function get_file_objects() {
		$files = array();
		foreach ($this->fileData as $filename => $fileInfo)
		{
			$file = new stdClass();
			$file->name = $filename;
			$file->size = $fileInfo['filesize'];
			$file->filedataid = $fileInfo['filedataid'];
			$file->url =$fileInfo['url'];
			$file->delete_url = $fileInfo['delete_url'];
			$file->thumb_url = $fileInfo['thumb_url'];
			$file->delete_type = 'DELETE';
			return $file;
	}


	}

	private function has_error($uploaded_file, $file, $error) {
		if ($error) {
			return $error;
		}

		if ($uploaded_file && is_uploaded_file($uploaded_file)) {
			$file_size = filesize($uploaded_file);
		} else {
			$file_size = $_SERVER['CONTENT_LENGTH'];
		}
		if ($this->options['max_file_size'] && (
			$file_size > $this->options['max_file_size'] ||
			$file->size > $this->options['max_file_size'])
		) {
			return 'maxFileSize';
		}
		if ($this->options['min_file_size'] &&
		$file_size < $this->options['min_file_size']) {
			return 'minFileSize';
		}
		if (is_int($this->options['max_number_of_files']) && (
			count($this->fileData) >= $this->options['max_number_of_files'])
		)
		{
			return 'maxNumberOfFiles';
		}
		return $error;
	}

	private function handle_file_upload($uploaded_file, $name, $size, $type, $error)
	{

		$file = new stdClass();
		$file->name = basename(stripslashes($name));
		$file->size = intval($size);
		$file->type = $type;
		if (!empty($_POST['uploadFrom']))
		{
			$file->uploadfrom = $_POST['uploadFrom'];
		}
		if (!empty($_POST['parentid']))
		{
			$file->parentid = $_POST['parentid'];
		}

		// Validation is and should be done in the API
		//$error = $this->has_error($uploaded_file, $file, $error);

		if ($file->name)
		{
			if ($file->name[0] === '.')
			{
				$file->name = substr($file->name, 1);
			}

			$append_file = $file->size > filesize($uploaded_file);

			if ($uploaded_file && is_uploaded_file($uploaded_file))
			{
				// multipart/formdata uploads (POST method uploads)
				if ($append_file)
				{

					if (!array_key_exists($file->name, $this->partials))
					{
						$this->partials[$file->name] = '';
					}
					$this->partials[$file->name] .= file_get_contents($uploaded_file);
					$file_size = strlen($this->partials[$file->name] );

					if ($file_size >= $file->size)
					{
						$file->contents = $this->partials[$file->name] ;
						$file->size = $file_size;
						$fileInfo = $this->api->callApi('content_attach', 'upload', array($file));
					}

				}
				else

				{
					$file_size = filesize($uploaded_file);
					$file->contents = file_get_contents($uploaded_file);
					$fileInfo = $this->api->callApi('content_attach', 'upload', array($file));
				}
			}
			else
			{
				// Non-multipart uploads (PUT method support)
				$file->tmp_name = $uploaded_file;
				$file_size = filesize($uploaded_file);
				$fileInfo = $this->api->callApi('content_attach', 'upload', array($file));

			}
			if (!empty($fileInfo['errors']))
			{
				$file->error = $fileInfo['errors'][0];
			}
			else
			{
				if ($file_size === $file->size)
				{
					$file->url = $this->baseurl . '/filedata/fetch?filedataid=' . $fileInfo['filedataid'] ;
					$file->thumbnail_url = $this->baseurl . '/filedata/fetch?filedataid=' . $fileInfo['filedataid'] . '&thumb=1' ;
				}

				if (isset($fileInfo))
				{
					$this->fileData[$name] = $fileInfo;
					$this->fileData[$name]['url'] = $this->baseurl . '/filedata/fetch?filedataid=' . $fileInfo['filedataid'] ;
					$this->fileData[$name]['thumbnail_url'] = $this->baseurl . '/filedata/fetch?filedataid=' . $fileInfo['filedataid'] . '&thumb=1' ;
					$this->fileData[$name]['delete_url'] = $this->baseurl . '/filedata/delete?filedataid=' . $fileInfo['filedataid'] ;
					$file->filedataid = $fileInfo['filedataid'] ;
				}

				$file->size = $file_size;
				$file->delete_url =  $this->baseurl . '/filedata/delete?filedataid=' . $fileInfo['filedataid'] ;
				$file->delete_type = 'DELETE';
			}
		}
		else
		{
			$file->error = $error;
		}
		return $file;
	}

	public function get()
	{

		if (empty($_FILES) AND empty($_REQUEST['file']))
		{
			$controller = new vB5_Frontend_Controller();
			$controller->sendAsJson(array());
			return ;
		}

		$file_name = isset($_REQUEST['file']) ?
		    basename(stripslashes($_REQUEST['file'])) : null;
		if ($file_name) {
			$info = $this->get_file_object($file_name);
		} else {
			$info = $this->get_file_objects();
		}

		$controller = new vB5_Frontend_Controller();
		$controller->sendAsJson($info);
	}

	public function post()
	{
		$upload = isset($_FILES[$this->options['param_name']]) ?
		    $_FILES[$this->options['param_name']] : array(
		        'tmp_name' => null,
		        'name' => null,
		        'size' => null,
		        'type' => null,
		        'error' => 'no_file_to_upload'
		    );

		$info = array();
		if (is_array($upload['tmp_name'])) {

			foreach ($upload['tmp_name'] as $index => $value) {
				$info[] = $this->handle_file_upload(
				    $upload['tmp_name'][$index],
				    isset($_SERVER['HTTP_X_FILE_NAME']) ?
				        $_SERVER['HTTP_X_FILE_NAME'] : $upload['name'][$index],
				    isset($_SERVER['HTTP_X_FILE_SIZE']) ?
				        $_SERVER['HTTP_X_FILE_SIZE'] : $upload['size'][$index],
				    isset($_SERVER['HTTP_X_FILE_TYPE']) ?
				        $_SERVER['HTTP_X_FILE_TYPE'] : $upload['type'][$index],
				    $upload['error'][$index]
				);
			}
		} else {

			$info[] = $this->handle_file_upload(
			    $upload['tmp_name'],
			    isset($_SERVER['HTTP_X_FILE_NAME']) ?
			        $_SERVER['HTTP_X_FILE_NAME'] : $upload['name'],
			    isset($_SERVER['HTTP_X_FILE_SIZE']) ?
			        $_SERVER['HTTP_X_FILE_SIZE'] : $upload['size'],
			    isset($_SERVER['HTTP_X_FILE_TYPE']) ?
			        $_SERVER['HTTP_X_FILE_TYPE'] : $upload['type'],
			    $upload['error']
			);
		}

		foreach ($info as $file) {
			unset($file->contents);
		}			

		header('Vary: Accept');

		if (isset($this->options['uploadFrom']) AND $this->options['uploadFrom'] == 'CKEditorInsertImage')
		{
			header('Content-type: text/html');

			$funcNum = $_GET['CKEditorFuncNum'] ;
			$url='';			

			$api = Api_InterfaceAbstract::instance();

			if (!empty($info))
			{
				$url = isset($info[0]->url) ? $info[0]->url : '';
				$error = isset($info[0]->error) ? $info[0]->error : '';

				if (is_array($error))
				{
					$errorphrase = array_shift($error);
					$phrases = $api->callApi('phrase', 'fetch', array(array($errorphrase)));
					$error = vsprintf($phrases[$errorphrase], $error);
				}
				else if (!empty($error))  
				{
					$phrases = $api->callApi('phrase', 'fetch', array(array($error)));
					$error = $phrases[$error];
				}
			}
			else
			{
				$phrases = $api->callApi('phrase', 'fetch', array(array('error_uploading_image')));
				$error =  $phrases['error_uploading_image'];
			}

			//encode to ensure we don't encounter js syntax error
			$error = json_encode($error);

			echo "<script type=\"text/javascript\"> window.parent.CKEDITOR.tools.callFunction($funcNum, '$url', $error); </script>";
			exit;			
		}
		
		if (isset($_SERVER['HTTP_ACCEPT']) &&
		(strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
			header('Content-type: application/json');
		} else {
			header('Content-type: text/plain');
		}			

		$controller = new vB5_Frontend_Controller();
		$controller->sendAsJson($info);
	}

	public function delete()
	{
	}
}
