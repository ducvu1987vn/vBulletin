<?php

class vB5_Config
{

	private static $instance;
	private static $defaults = array(
		'no_template_notices' => false,
		'debug' => false,
		'report_all_php_errors' => true,
		'collapsed' => true,
		'no_js_bundles' => false,
		'render_debug' => false,
	);
	private $config = array();


	public static function instance()
	{
		if (!isset(self::$instance))
		{
			$c = __CLASS__;
			self::$instance = new $c;
		}

		return self::$instance;
	}

	/**
	 *
	 * @param string $file
	 */
	public function loadConfigFile($file)
	{
		require_once($file);
		if (!isset($config))
		{
			die("Couldn't read config file $file");
		}

		$this->config = array_merge(self::$defaults, $config);
	}

	public function __get($name)
	{
		if (isset($this->config[$name]))
		{
			return $this->config[$name];
		}
		else
		{
			$trace = debug_backtrace();
			trigger_error("Undefined config property '$name' in " .
					$trace[0]['file'] . ' on line ' .
					$trace[0]['line'], E_USER_NOTICE);
			return null;
		}
	}

}
