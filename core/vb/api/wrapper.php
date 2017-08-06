<?php
if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
   || #################################################################### ||
   || # vBulletin 5.0.0
   || # ---------------------------------------------------------------- # ||
   || # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
   || # This file may not be redistributed in whole or significant part. # ||
   || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
   || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
   || #################################################################### ||
   \*======================================================================*/

/**
 * vB_Api_Wrapper
 * This class is just a wrapper for API classes so that exceptions can be handled
 * and translated for the client.
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Wrapper
{
	protected $controller;
	protected $api;

	public function __construct($controller, $api)
	{
		$this->controller = $controller;
		$this->api = $api;
	}

	/**
	 * This method prevents catchable fatal errors when calling the API with missing arguments
	 * @param string $method
	 * @param array $arguments
	 */
	protected function validateCall($controller, $method, $arguments)
	{
		if(get_class($controller) == 'vB_Api_Null')
		{
			/* No such Class in the core controllers
			   but it may be defined in an extension */
			return 0;
		}

		if (method_exists($controller, $method))
		{
			$reflection = new ReflectionMethod($controller, $method);
		}
		else
		{
			/* No such Method in the core controller
			   but it may be defined in an extension */
			return 0;
		}

		if ($reflection->isStatic())
		{
			return 2;
		}

		if ($reflection->isConstructor())
		{
			return 3;
		}

		if ($reflection->isDestructor())
		{
			return 4;
		}

		$index = 0;
		foreach($reflection->getParameters() as $param)
		{
			if (!isset($arguments[$index]))
			{
				if (!$param->allowsNull() AND !$param->isDefaultValueAvailable())
				{
					// cannot omit parameter
					throw new vB_Exception_Api('invalid_data');
				}
			}
			else if ($param->isArray() AND !is_array($arguments[$index]))
			{
				// array type was expected
				throw new vB_Exception_Api('invalid_data');
			}

			$index++;
		}

		return 1;
	}

	public function __call($method, $arguments)
	{
		try
		{
			// check if API method is enabled
			$this->api->checkApiState($method);

			$result = null;
			$type = $this->validateCall($this->api, $method, $arguments);

			if($type)
			{
				if (is_callable(array($this->api, $method)))
				{
					$call = call_user_func_array(array(&$this->api, $method), $arguments);

					if ($call !== null)
					{
						$result = $call;
					}
				}
			}

			if($elist = vB_Api_Extensions::getExtensions($this->controller))
			{
				foreach($elist AS $class)
				{
					if (is_callable(array($class, $method)))
					{
						$args = $arguments;
						array_unshift($args, $result);
						$call = call_user_func_array(array($class, $method), $args);

						if ($call !== null)
						{
							$result = $call;
						}
					}
				}
			}

			return $result;
		}
		catch (vB_Exception_Api $e)
		{
			$errors = $e->get_errors();
			$config = vB::getConfig();
			if (!empty($config['Misc']['debug']))
			{
				$errors[] = array("exception_trace", $e->getTraceAsString());
				$errors[] = array("errormsg", 'Error ' . $e->getMessage() . ' occurred in file ' . $e->getFile() . ' on line ' . $e->getLine());
			}
			return array('errors' => $errors);
		}
		catch (Exception $e)
		{
			$errors = array(array('unexpected_error', $e->getMessage()));
			$config = vB::getConfig();
			if (!empty($config['Misc']['debug']))
			{
				$errors[] = array("exception_trace", $e->getTraceAsString());
				$errors[] = array("errormsg", 'Error ' . $e->getMessage() . ' occurred in file ' . $e->getFile() . ' on line ' . $e->getLine());
			}
			return array('errors' => $errors);
		}
	}
}
