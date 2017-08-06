<?php

/**
 * Class to handle and sanitize variables from GET, POST and COOKIE etc
 *
 * @package	vBulletin
 * @version	$Revision: 43053 $
 * @date		$Date: 2011-04-25 13:02:53 -0700 (Mon, 25 Apr 2011) $
 */
class vB_Cleaner
{
	const TYPE_NOCLEAN			= 0;
	const TYPE_BOOL					= 1;
	const TYPE_INT					= 2;
	const TYPE_UINT					= 3;
	const TYPE_NUM					= 4;
	const TYPE_UNUM					= 5;
	const TYPE_UNIXTIME			= 6;
	const TYPE_STR					= 7;
	const TYPE_NOTRIM				= 8;
	const TYPE_NOHTML				= 9;
	const TYPE_ARRAY				= 10;
	const TYPE_FILE					= 11;
	const TYPE_BINARY				= 12;
	const TYPE_NOHTMLCOND		= 13;
	const TYPE_ARRAY_BOOL				= 101;
	const TYPE_ARRAY_INT					= 102;
	const TYPE_ARRAY_UINT				= 103;
	const TYPE_ARRAY_NUM					= 104;
	const TYPE_ARRAY_UNUM				= 105;
	const TYPE_ARRAY_UNIXTIME		= 106;
	const TYPE_ARRAY_STR					= 107;
	const TYPE_ARRAY_NOTRIM			= 108;
	const TYPE_ARRAY_NOHTML			= 109;
	const TYPE_ARRAY_ARRAY				= 110;
	const TYPE_ARRAY_FILE				= self::TYPE_FILE; // An array of "Files" behaves differently than other <input> arrays. TYPE_FILE handles both types.
	const TYPE_ARRAY_BINARY			= 112;
	const TYPE_ARRAY_NOHTMLCOND	= 113;
	const TYPE_ARRAY_KEYS_INT		= 202;
	const TYPE_ARRAY_KEYS_STR		= 207;
	const CONVERT_SINGLE		= 100; // Value to subtract from array types to convert to single types
	const CONVERT_KEYS			= 200; // Value to subtract from array => keys types to convert to single types
	const STR_NOHTML				= self::TYPE_NOHTML;

	/**
	 * Translation table for short superglobal name to long superglobal name
	 *
	 * @var array
	 */
	protected $superglobalLookup = array(
			'g' => '_GET',
			'p' => '_POST',
			'r' => '_REQUEST',
			'c' => '_COOKIE',
			's' => '_SERVER',
			'e' => '_ENV',
			'f' => '_FILES'
	);

	/**
	 * Constructor
	 *
	 * First, verifies that $GLOBALS has not been modified from the outside.
	 * Second, ensures that if REQUEST_METHOD is POST all super globals have
	 * the same keys to avoid variable injection.
	 * Third, Ensures that register_globals is disabled and unsets all GPC
	 * variables from the $GLOBALS array if register_globals is not disabled.
	 * Fourth, moves $_COOKIE vars into the REQUEST_METHOD vars and deletes them
	 * from the $_REQUEST array.
	 */
	public function __construct()
	{
		if (!is_array($GLOBALS))
		{
			die('<strong>Fatal Error:</strong> Invalid URL.');
		}

		if (isset($_SERVER['REQUEST_METHOD']) AND $_SERVER['REQUEST_METHOD'] == 'POST')
		{
			foreach (array_keys($_POST) as $key)
			{
				if (isset($_GET["$key"]))
				{
					$_GET["$key"] = $_REQUEST["$key"] = $_POST["$key"];
				}
			}
		}

		if (!defined('SESSION_BYPASS'))
		{
			define('SESSION_BYPASS', !empty($_REQUEST['bypass']));
		}

		if (isset($_SERVER['REQUEST_METHOD']) AND $_SERVER['REQUEST_METHOD'] == 'POST' AND isset($_SERVER['HTTP_X_REQUESTED_WITH']) AND $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' AND
						!(isset($_REQUEST['forcenoajax']) AND $_REQUEST['forcenoajax']))
		{
			$_POST['ajax'] = $_REQUEST['ajax'] = 1;
		}

		if (@ini_get('register_globals') OR !@ini_get('gpc_order'))
		{
			foreach ($this->superglobalLookup AS $arrayname)
			{
				if (!empty($GLOBALS["$arrayname"]))
				{
					foreach (array_keys($GLOBALS["$arrayname"]) AS $varname)
					{
						if (!in_array($varname, $this->superglobalLookup))
						{
							unset($GLOBALS["$varname"]);
						}
					}
				}
			}
		}

		foreach (array_keys($_COOKIE) AS $varname)
		{
			unset($_REQUEST["$varname"]);
			if (isset($_POST["$varname"]))
			{
				$_REQUEST["$varname"] =& $_POST["$varname"];
			}
			else if (isset($_GET["$varname"]))
			{
				$_REQUEST["$varname"] =& $_GET["$varname"];
			}
		}
	}

	/**
	 * Makes data in an array safe to use
	 *
	 * @param	array	The source array containing the data to be cleaned
	 * @param	array	Array of variable names and types we want to extract from the source array
	 *
	 * @return	array
	 */
	public function &cleanArray(&$source, $variables)
	{
		$return = array();

		foreach ($variables AS $varname => $vartype)
		{
			$return["$varname"] = & $this->clean($source["$varname"], $vartype, isset($source["$varname"]));
		}

		return $return;
	}

	/**
	 * Makes a single variable safe to use and returns it
	 *
	 * @param	mixed	The variable to be cleaned
	 * @param	integer	The type of the variable in which we are interested
	 * @param	boolean	Whether or not the variable to be cleaned actually is set
	 *
	 * @return	mixed	The cleaned value
	 */
	public function &clean(&$var, $vartype = self::TYPE_NOCLEAN, $exists = true)
	{
		if ($exists)
		{
			if (($vartype == self::TYPE_ARRAY OR ($vartype > self::CONVERT_SINGLE AND $vartype < self::CONVERT_KEYS)) AND is_string($var))
			{
				$tempvar = array();
				$tempvar = json_decode($var, true);
				$var = $tempvar;
			}

			if ($vartype < self::CONVERT_SINGLE)
			{
				$this->doClean($var, $vartype);
			}
			else if (is_array($var))
			{
				if ($vartype >= self::CONVERT_KEYS)
				{
					$var = array_keys($var);
					$vartype -= self::CONVERT_KEYS;
				}
				else
				{
					$vartype -= self::CONVERT_SINGLE;
				}

				foreach (array_keys($var) AS $key)
				{
					$this->doClean($var["$key"], $vartype);
				}
			}
			else
			{
				$var = array();
			}
			return $var;
		}
		else
		{
			// We use $newvar here to prevent overwrite superglobals. See bug #28898.
			if ($vartype < self::CONVERT_SINGLE)
			{
				switch ($vartype)
				{
					case self::TYPE_INT:
					case self::TYPE_UINT:
					case self::TYPE_NUM:
					case self::TYPE_UNUM:
					case self::TYPE_UNIXTIME:
						{
							$newvar = 0;
							break;
						}
					case self::TYPE_STR:
					case self::TYPE_NOHTML:
					case self::TYPE_NOTRIM:
					case self::TYPE_NOHTMLCOND:
						{
							$newvar = '';
							break;
						}
					case self::TYPE_BOOL:
						{
							$newvar = 0;
							break;
						}
					case self::TYPE_ARRAY:
					case self::TYPE_FILE:
						{
							$newvar = array();
							break;
						}
					case self::TYPE_NOCLEAN:
						{
							$newvar = null;
							break;
						}
					default:
						{
							$newvar = null;
						}
				}
			}
			else
			{
				$newvar = array();
			}

			return $newvar;
		}
	}

	/**
	 * Does the actual work to make a variable safe
	 *
	 * @param	mixed	The data we want to make safe
	 * @param	integer	The type of the data
	 *
	 * @return	mixed
	 */
	protected function &doClean(&$data, $type)
	{
		static $booltypes = array('1', 'yes', 'y', 'true', 'on');

		switch ($type)
		{
			case self::TYPE_INT:		$data = intval($data);
				break;
			case self::TYPE_UINT:		$data = ($data = intval($data)) < 0 ? 0 : $data;
				break;
			case self::TYPE_NUM:		$data = strval($data) + 0;
				break;
			case self::TYPE_UNUM:		$data = strval($data) + 0;
															$data = ($data < 0) ? 0 : $data;
				break;
			case self::TYPE_BINARY:	$data = strval($data);
				break;
			case self::TYPE_STR:		$data = trim(strval($data));
				break;
			case self::TYPE_NOTRIM: $data = strval($data);
				break;
			case self::TYPE_NOHTML: $data = vB_String::htmlSpecialCharsUni(trim(strval($data)));
				break;
			case self::TYPE_BOOL:		$data = in_array(strtolower($data), $booltypes) ? 1 : 0;
				break;
			case self::TYPE_ARRAY:	$data = (is_array($data)) ? $data : array();
				break;
			case self::TYPE_NOHTMLCOND:
				{
					$data = trim(strval($data));
					if (strcspn($data, '<>"') < strlen($data) OR
									(strpos($data, '&') !== false AND !preg_match('/&(#[0-9]+|amp|lt|gt|quot);/si', $data)))
					{
						// data is not htmlspecialchars because it still has characters or entities it shouldn't
						$data = vB_String::htmlSpecialCharsUni($data);
					}
					break;
				}
			case self::TYPE_FILE:
				{
					// perhaps redundant :p
					if (is_array($data))
					{
						if (is_array($data['name']))
						{
							$files = count($data['name']);
							for ($index = 0; $index < $files; $index++)
							{
								$data['name']["$index"] = trim(strval($data['name']["$index"]));
								$data['type']["$index"] = trim(strval($data['type']["$index"]));
								$data['tmp_name']["$index"] = trim(strval($data['tmp_name']["$index"]));
								$data['error']["$index"] = intval($data['error']["$index"]);
								$data['size']["$index"] = intval($data['size']["$index"]);
							}
						}
						else
						{
							$data['name'] = trim(strval($data['name']));
							$data['type'] = trim(strval($data['type']));
							$data['tmp_name'] = trim(strval($data['tmp_name']));
							$data['error'] = intval($data['error']);
							$data['size'] = intval($data['size']);
						}
					}
					else
					{
						$data = array(
								'name' => '',
								'type' => '',
								'tmp_name' => '',
								'error' => 0,
								'size' => 4, // UPLOAD_ERR_NO_FILE
						);
					}
					break;
				}
			case self::TYPE_UNIXTIME:
				{
					if (is_array($data))
					{
						$data = $this->clean($data,vB_Cleaner::TYPE_ARRAY_UINT);
						if ($data['month'] AND $data['day'] AND $data['year'])
						{
							require_once(DIR . '/includes/functions_misc.php');
							$data = vbmktime($data['hour'], $data['minute'], $data['second'], $data['month'], $data['day'], $data['year']);
						}
						else
						{
							$data = 0;
						}
					}
					else
					{
						$data = ($data = intval($data)) < 0 ? 0 : $data;
					}
					break;
				}
			// null actions should be deifned here so we can still catch typos below
			case self::TYPE_NOCLEAN:
				{
					break;
				}

			default:
				{
					if (($config = vB::getConfig()) AND $config['Misc']['debug'])
					{
						trigger_error('vB_Cleaner::doClean() Invalid data type specified', E_USER_WARNING);
					}
				}
		}

		// strip out characters that really have no business being in non-binary data
		switch ($type)
		{
			case self::TYPE_STR:
			case self::TYPE_NOTRIM:
			case self::TYPE_NOHTML:
			case self::TYPE_NOHTMLCOND:
				$data = str_replace(chr(0), '', $data);
		}

		return $data;
	}

	/**
	 * Removes HTML characters and potentially unsafe scripting words from a string
	 *
	 * @param	string	The variable we want to make safe
	 *
	 * @return	string
	 */
	public function xssClean($var)
	{
		static $preg_find = array('#^javascript#i', '#^vbscript#i');
		static $preg_replace = array('java script', 'vb script');

		return preg_replace($preg_find, $preg_replace, htmlspecialchars(trim($var)));
	}

	/**
	 * Removes HTML characters and potentially unsafe scripting words from a URL
	 * Note: The query string is preserved.
	 *
	 * @param	string	The url to clean
	 * @return	string
	 */
	public function xssCleanUrl($url)
	{
		if ($query = vB_String::parseUrl($url, PHP_URL_QUERY))
		{
			$url = substr($url, 0, strpos($url, '?'));
			$url = $this->xssClean($url);
			return $url . '?' . $query;
		}

		return $this->xssClean($url);
	}
}
