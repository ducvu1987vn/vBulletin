<?php

class vB5_Template_Options
{

	protected static $instance;
	protected $cache = array();

	public static function instance()
	{
		if (!isset(self::$instance))
		{
			$c = __CLASS__;
			self::$instance = new $c;
			self::$instance->getOptions();
		}

		return self::$instance;
	}

	public function get($name)
	{
		$path = explode('.', $name);

		$var = $this->cache;
		foreach ($path as $t)
		{
			if (isset($var[$t]))
			{
				$var = $var[$t];
			}
			else
			{
				return NULL;
			}
		}

		return $var;
	}

	public function getOptions()
	{
		if (!isset($this->cache['options']))
		{
			$this->fetchOptions();
		}

		return $this->cache;
	}

	private function fetchOptions()
	{
		$response = Api_InterfaceAbstract::instance()->callApi('options', 'fetch');

		foreach ($response as $key => $value)
		{
			$this->cache[$key] = $value;
		}
	}
}
