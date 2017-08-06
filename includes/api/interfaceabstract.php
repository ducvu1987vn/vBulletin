<?php

abstract class Api_InterfaceAbstract
{
	const API_COLLAPSED = 'Collapsed';
	const API_NONCOLLAPSED = 'Noncollapsed';
	const API_TEST = 'Test';

	private static $instance;

	/*
	 * Defines whether we are using the API in test mode
	 * @var bool
	 */
	private static $test = false;

	/**
	 * Turns on/off the test mode in API
	 * @param bool $on
	 */
	public static function setTest($on)
	{
		self::$test = $on;
	}

	public static function instance($type = NULL)
	{
		if (self::$test)
		{
			$type = self::API_TEST;
		}
		else if ($type === NULL) {
			$type = (vB5_Config::instance()->collapsed) ? self::API_COLLAPSED : self::API_NONCOLLAPSED;
		}

		if (!isset(self::$instance[$type]))
		{
			$c = 'Api_Interface_' . ucfirst($type);
			if (class_exists($c))
			{
				self::$instance[$type] = new $c;
				self::$instance[$type]->init();
			}
			else
			{
				throw new Exception("Couldn't find $type interface");
			}
		}

		return self::$instance[$type];
	}

	// prevent users to clone the instance
	public function __clone()
	{
		trigger_error('Clone is not allowed.', E_USER_ERROR);
	}

	/**
	 * Initialized method. This method is to prevent nested construct calls. See VBV-1862
	 */
	public function init()
	{

	}

	/**
	 *
	 * @param string $controller
	 * @param string $method
	 * @param array $arguments
	 * @return array
	 */
	// This method is currently dealign with both indexed and associative arrays.
	// Indexed arrays are sent by template calls.
	// @todo: make sure all API methods exposed to the template can handle indexed arrays
	abstract public function callApi($controller, $method, array $arguments = array(), $useNamedParams = false);

	public function relay($file)
	{
		throw new Exception('relay only implemented in collapsed mode');
	}
}
