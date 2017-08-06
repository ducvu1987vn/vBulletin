<?php

class vB5_Frontend_Controller_Report extends vB5_Frontend_Controller
{

	function __construct()
	{
		parent::__construct();
	}

	function actionReport()
	{
		$input = array(
			'reason' => (isset($_POST['reason']) ? trim(strval($_POST['reason'])) : ''),
			'reportnodeid' => (isset($_POST['reportnodeid']) ? trim(intval($_POST['reportnodeid'])) : 0),
		);

		if (!$input['reportnodeid'])
		{
			$results['error'] = 'invalid_nodeid';
            $this->sendAsJson($results);
			return;
		}

		$api = Api_InterfaceAbstract::instance();
		
		// get user info for the currently logged in user
		$user  = $api->callApi('user', 'fetchCurrentUserinfo', array());

		$reportData = array(
			'rawtext' => $input['reason'],
			'reportnodeid' => $input['reportnodeid'],
			'parentid' => $input['reportnodeid'],
			'userid' => $user['userid'],
			'authorname' => $user['username'],
			'created' => time(),
		);

		$nodeId = $api->callApi('content_report', 'add', array($reportData));

		if (!empty($nodeId['errors']))
		{
			$results['error'] = $nodeId['errors'][0];
		}
		else
		{
			$results = $nodeId;
		}

		$this->sendAsJson($results);
		return;
	}
}
