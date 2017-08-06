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
class vB_Api_Logwrapper
{
	protected $controller;
	protected $api;

	public function __construct($controller, $api)
	{
		$this->controller = $controller;
		$this->api = $api;
	}

	public function __call($method, $arguments)
	{
		$logger = vB::getLogger('api.' . $this->controller . '.' . $method);

		//check so that we don't var_export large variables when we don't have to
		if($logger->isInfoEnabled())
		{
			$message = str_repeat('=', 80) . "\ncalled $method on {$this->controller}\n\$arguments = " .
				var_export($arguments, true) . "\n" . str_repeat('=', 80) . "\n";
			$logger->info($message);
			$logger->info("time: " . microtime(true));
		}

		if ($logger->isTraceEnabled())
		{
			$message = str_repeat('=', 80) . "\n " . $this->getTrace() . str_repeat('=', 80) . "\n";
			$logger->trace($message);
		}

		$c = $this->api;

		// This is a hack to prevent method parameter reference error. See VBV-5546
		$hackedarguments = array();
		foreach($arguments as $k => &$arg)
		{
			$hackedarguments[$k] = &$arg;
		}

		$return = call_user_func_array(array(&$c, $method), $hackedarguments);

		//check so that we don't var_export large variables when we don't have to
		if($logger->isDebugEnabled())
		{
			$message = str_repeat('=', 80) . "\ncalled $method on {$this->controller}\n\$return = " .
				var_export($return, true) . "\n" . str_repeat('=', 80) . "\n";
			$logger->debug($message);
		}



		return $return;
	}

	private function getTrace()
	{
		$trace = debug_backtrace();

		$outString = '';
		foreach ($trace as $line)
		{
			$outString .= 'In file ';
			if (!isset($line['file']))
			{
				$line['file'] = 'unknown';
			}

			if (!isset($line['line']))
			{
				$line['line'] = 'unknown';
			}

			$outString .= $line['file'] . ":" . @$line['line'];

			if (isset($line['function']))
			{
				$outString .= ' called ';

				if (isset($line['class']))
				{
					$outString .= $line['class'] . ':';
				}

				$outString .= $line['function'];
			}

			$outString .= "\n";
		}

		return $outString;
	}
}
