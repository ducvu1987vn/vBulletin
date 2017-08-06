<?php

class vB5_Frontend_Controller_Video extends vB5_Frontend_Controller
{

	function __construct()
	{
		parent::__construct();
	}

	function actionGetvideodata()
	{
		$input = array(
			'url' => trim($_POST['url']),
		);

		$api = Api_InterfaceAbstract::instance();
		$video = $api->callApi('content_video', 'getVideoFromUrl', array($input['url']));

		if ($video)
		{
			$templater = new vB5_Template('video_edit');
			$templater->register('video', $video);
			$templater->register('existing', 0);
			$templater->register('editMode', 1);
			$results['template'] = $templater->render();
		}
		else
		{
			$results['error'] = 'Invalid URL.';
		}

        $this->sendAsJson($results);
		return;
	}
}