<?php

class vB5_Admin_Routing
{

	protected $controller;
	protected $action;

	function setRoutes()
	{
		//TODO: this is a very basic and straight forward way of parsing the URI, we need to improve it
		$path = $_SERVER['PATH_INFO'];
		if ($path{0} == '/')
		{
			$path = substr($path, 1);
		}

		@list($controller, $method) = explode('/', $path);

		$this->controller = isset($controller) && !empty($controller) ? $controller : 'main';
		$this->action = isset($method) && !empty($method) ? $method : 'index';
	}

	function getController()
	{
		return $this->controller;
	}

	function getAction()
	{
		return $this->action;
	}

}
