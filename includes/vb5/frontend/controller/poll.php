<?php

class vB5_Frontend_Controller_Poll extends vB5_Frontend_Controller
{

	function __construct()
	{
		parent::__construct();
	}

	function actionVote()
	{
		if (!isset($_POST['polloptionid']) AND !isset($_POST['polloptionids']))
		{
			$this->sendAsJson(false);
			exit();
		}

		if (!isset($_POST['polloptionid']))
		{
			$_POST['polloptionid'] = 0;
		}
		if (!isset($_POST['polloptionids']))
		{
			$_POST['polloptionids'] = array();
		}

		$input = array(
			'polloptionid' => intval($_POST['polloptionid']),
			'polloptionids' => (array)$_POST['polloptionids'],
		);
		
		$options = array();
		if ($input['polloptionids'])
		{
			$options = $input['polloptionids'];
		}
		else
		{
			$options = array($input['polloptionid']);
		}

		$api = Api_InterfaceAbstract::instance();
		$nodeid = $api->callApi('content_poll', 'vote', array($options));

		if (!$nodeid OR !is_numeric($nodeid))
		{
			$this->sendAsJson(false);
			exit();
		}

		// Get new poll data
		$this->ajaxPollData($nodeid);
	}

	function actionGet()
	{
		$input = array(
			'nodeid' => intval($_GET['nodeid']),
		);

		$this->ajaxPollData($input['nodeid']);
	}
	
	function actionGetVoters()
	{
		$input = array(
			'nodeid' 		=> intval($_GET['nodeid']),
			'polloptionid'	=> intval($_GET['polloptionid']),
		);

		$api = Api_InterfaceAbstract::instance();
		$poll = $api->callApi('content_poll', 'getContent', array($input['nodeid']));
		if (!empty($poll) && empty($poll['errors'])){
			$poll = $poll[$input['nodeid']];
			$pollOption = $poll['options'][$input['polloptionid']];
			if (!empty($pollOption) && $pollOption['voters'])
			{
				$voters  = $api->callApi('user', 'fetchUsernames', array($pollOption['voters']));				
				$poll['options'][$input['polloptionid']]['votersinfo'] = $voters;
			}
			else 
			{
				$poll['options'][$input['polloptionid']]['votersinfo'] = array();	
			}
		}
		else 
		{
			$poll = array('error' => 'Error retrieving voters.');
		}

        $this->sendAsJson($poll);
	}

	protected function ajaxPollData($nodeid)
	{
		$poll = Api_InterfaceAbstract::instance()->callApi('content_poll', 'getContent', array($nodeid));
		foreach ($poll as $v)
		{
			$this->sendAsJson(array(
				'options' => $v['options'],
				'poll_votes' => $v['poll_votes']
			));
			return;
		}
	}
}