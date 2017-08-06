<?php

class vB5_Frontend_Controller_Link extends vB5_Frontend_Controller
{

	function __construct()
	{
		parent::__construct();
	}

	function actionGetlinkdata()
	{
		$input = array(
			'url' => trim($_REQUEST['url']),
		);

		$api = Api_InterfaceAbstract::instance();

		$video = $api->callApi('content_video', 'getVideoFromUrl', array($input['url']));
		$data = $api->callApi('content_link', 'parsePage', array($input['url']));

		if ($video AND empty($video['errors']))
		{
			$templater = new vB5_Template('video_edit');
			$templater->register('video', $video);
			$templater->register('existing', 0);
			$templater->register('editMode', 1);
			$templater->register('title', $data['title']);
			$templater->register('url', $input['url']);
			$templater->register('meta', $data['meta']);
			$results['template'] = $templater->render();
		}
		else
		{

			if ($data AND empty($data['errors']))
			{
				$templater = new vB5_Template('link_edit');
				$templater->register('images', $data['images']);
				$templater->register('title', $data['title']);
				$templater->register('url', $input['url']);
				$templater->register('meta', $data['meta']);
				$results['template'] = $templater->render();
			}
			else
			{
				$results['error'] = 'Invalid URL.';
			}
		}
		
		$this->sendAsJson($results);
		return;
	}
}