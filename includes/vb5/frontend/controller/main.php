<?php

class vB5_Frontend_Controller_Main extends vB5_Frontend_Controller
{

	protected $template;

	public function __construct($template)
	{
		parent::__construct();

		$this->template = $template;
	}

	public function index($controller, $method, array $arguments)
	{
		$serverData = array_merge($_GET, $_POST);
		if (!empty($controller))
		{
			$serverResponse = Api_InterfaceAbstract::instance()->callApi($controller, $method, $serverData);
			if (is_array($serverResponse))
			{
				$serverData = array_merge($serverData, $serverResponse);
			}
		}

		if (!empty($this->template))
		{
			$templater = new vB5_Template($this->template);
			$templater->register('server', $serverData);
			$templater->register('contentid', (isset($arguments['contentid']) ? $arguments['contentid'] : 0));
			$this->outputPage($templater->render());
		}
	}

}

