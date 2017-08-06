<?php

class vB5_Cookie
{
	protected static $enabled = null;
	protected static $cookiePrefix = null;
	protected static $path = null;
	protected static $domain = null;
	protected static $secure = null;

	const TYPE_UINT = 1;
	const TYPE_STRING = 2;

	public static function set($name, $value, $expireDays = 0, $httpOnly = true)
	{
		self::loadConfig();

		if (!self::$enabled)
		{
			return;
		}

		if ($expireDays == 0)
		{
			$expire = 0;
		}
		else
		{
			$expire = time() + ($expireDays * 86400);
		}

		$name = self::$cookiePrefix . $name;

		if (!setcookie($name, $value, $expire, self::$path, self::$domain, self::$secure, $httpOnly))
		{
			throw new Exception('Unable to set cookies');
		}
	}

	public static function get($name, $type)
	{
		self::loadConfig();

		if (!self::$enabled)
		{
			return;
		}

		$name = self::$cookiePrefix . $name;

		$value = isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;

		switch($type)
		{
			case self::TYPE_UINT:
				$value = intval($value);
				$value = $value < 0 ? 0 : $value;
				break;

			case self::TYPE_STRING:
				$value = strval($value);
				break;

			default:
				throw new Exception('Invalid cookie clean type');
				break;
		}

		return $value;
	}

	public static function delete($name)
	{
		self::set($name, '', -1);
	}

	/**
	 * Deletes all cookies starting with cookiePrefix
	 */
	public static function deleteAll()
	{
		$prefix_length = strlen(self::$cookiePrefix);
		foreach ($_COOKIE AS $key => $val)
		{
			$index = strpos($key, self::$cookiePrefix);
			if ($index == 0 AND $index !== false)
			{
				$key = substr($key, $prefix_length);
				if (trim($key) == '')
				{
					continue;
				}
				// self::set will add the cookie prefix
				self::delete($key);
			}
		}
	}

	public static function isEnabled()
	{
		self::loadConfig();

		return self::$enabled;
	}

	protected static function loadConfig()
	{
		if (self::$cookiePrefix !== null)
		{
			return;
		}

		$config = vB5_Config::instance();

		// these could potentially all be config options
		self::$enabled = ($config->cookie_enabled !== false);
		self::$cookiePrefix = $config->cookie_prefix;

		$options = vB5_Template_Options::instance();
		self::$path = $options->get('options.cookiepath');
		self::$domain = $options->get('options.cookiedomain');

		self::$secure = (
			(
				(isset($_SERVER['SERVER_PORT']) AND (443 === intval($_SERVER['SERVER_PORT'])))
				OR
				(isset($_SERVER['HTTPS']) AND $_SERVER['HTTPS'] AND ($_SERVER['HTTPS'] != 'off'))
			)
			AND
			(isset($url['scheme']) AND $url['scheme'] == 'https')
		);
	}

	/**
	 * Returns the value for an array stored in a cookie
	 * Ported from functions.php fetch_bbarray_cookie
	 *
	 * @param	string	Name of the cookie
	 * @param	mixed	ID of the data within the cookie
	 *
	 * @return	mixed
	 */
	public static function fetchBbarrayCookie($cookiename, $id)
	{
		static $cache = null;
		if ($cache === null)
		{
			$cookie = self::get($cookiename, self::TYPE_STRING);
			if ($cookie != '')
			{
				$cache = @unserialize(self::convertBbarrayCookie($cookie));
			}
		}

		return empty($cache["$id"]) ? null : $cache["$id"];
	}

	/**
	 * Replaces all those none safe characters so we dont waste space in
	 * array cookie values with URL entities
	 * Ported from functions.php convert_bbarray_cookie
	 *
	 * @param	string	Cookie array
	 * @param	string	Direction ('get' or 'set')
	 *
	 * @return	array
	 */
	protected static function convertBbarrayCookie($cookie, $dir = 'get')
	{
		if ($dir == 'set')
		{
			$cookie = str_replace(array('"', ':', ';'), array('.', '-', '_'), $cookie);
		}
		else
		{
			$cookie = str_replace(array('.', '-', '_'), array('"', ':', ';'), $cookie);
		}
		return $cookie;
	}
}
