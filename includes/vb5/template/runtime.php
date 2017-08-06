<?php

class vB5_Template_Runtime
{

	public static $units = array('%', 'px', 'pt', 'em', 'ex', 'pc', 'in', 'cm', 'mm');

	public static function date($timestamp, $format = '')
	{
		/* It appears that in vB5 its not customary to pass
		the dateformat from the template so we load it here.

		Dates formatted in templates need to be told what format to
		use and if today/yesterday/hours ago is to be used (if enabled)

		This function needs to accept most of vbdate's options if
		we still allow the admin to dictate formats and we still
		use today/yesterday/hours ago in some places and not in others.
		*/
		if (!$format)
		{
			$format = vB5_Template_Options::instance()->get('options.dateformat');

			if (vB5_User::get('lang_locale'))
			{
				$format = vB5_User::get('lang_dateoverride');
			}
		}

		// Timenow.
		if (strtolower($timestamp) == 'timenow')
		{
			$timestamp = time();
		}
		else
		{
			/* Note that negative
			timestamps are allowed in vB5 */
			$timestamp = intval($timestamp);
		}

		return self::vbdate($format, $timestamp, true);
	}

	public static function time($timestamp, $timeformat = '')
	{
		if (!$timeformat)
		{
			$timeformat = vB5_Template_Options::instance()->get('options.timeformat');

			if (vB5_User::get('lang_locale'))
			{
				$timeformat = vB5_User::get('lang_timeoverride');
			}
		}

		if (empty($timestamp))
		{
			$timestamp = 0;
		}
		return self::vbdate($timeformat, $timestamp, true);
	}

	public static function datetime($timestamp, $format = 'date, time', $formatdate = '', $formattime = '')
	{
		$options = vB5_Template_Options::instance();

		if (!$formatdate)
		{
			$formatdate = $options->get('options.dateformat');

			if (vB5_User::get('lang_locale'))
			{
				$formatdate = vB5_User::get('lang_dateoverride');
			}
		}

		if (!$formattime)
		{
			$formattime = $options->get('options.timeformat');

			if (vB5_User::get('lang_locale'))
			{
				$formattime = vB5_User::get('lang_timeoverride');
			}
		}

		// Timenow.
		$timenow = time();
		if (strtolower($timestamp) == 'timenow')
		{
			$timestamp = $timenow;
		}
		else
		{
			/* Note that negative
			timestamps are allowed in vB5 */
			$timestamp = intval($timestamp);
		}

		$date = self::vbdate($formatdate, $timestamp, true);
		if ($options->get('options.yestoday') == 2)
		{
			// Process detailed "Datestamp Display Option"
			// 'Detailed' will show times such as '1 Minute Ago', '1 Hour Ago', '1 Day Ago', and '1 Week Ago'.
			$timediff = $timenow - $timestamp;

			if ($timediff >= 0 AND $timediff < 3024000)
			{
				return $date;
			}
		}

		$time = self::vbdate($formattime, $timestamp, true);

		return str_replace(array('date', 'time'), array($date, $time), $format);
	}

	public static function escapeJS($javascript)
	{
		return str_replace("'", "\'", $javascript);
	}

	public static function numberFormat($number, $decimals = 0)
	{
		return vb_number_format($number, $decimals);
	}

	public static function urlEncode($text)
	{
		return urlencode($text);
	}

	public static function parsePhrase($phraseName)
	{
		$phrase = vB5_Template_Phrase::instance();

		//allow the first paramter to be a phrase array	( array($phraseName, $arg1, $arg2, ...)
		//otherwise the parameter is the phraseName and the args list is the phrase array
		//this allows us to pass phrase arrays around and use them directly without unpacking them
		//in the templates (which is both difficult and inefficient in the template code)
		if (is_array($phraseName))
		{
			return $phrase->register($phraseName);
		}
		else
		{
			return $phrase->register(func_get_args());
		}
	}

	private static function outputStyleVar($base_stylevar, $parts = array())
	{
		$stylevars = vB5_Template_Stylevar::instance();

		if (isset($parts[1]))
		{
			$types = array(
				'background' => array(
					'backgroundColor' => 'color',
					'backgroundImage' => 'image',
					'backgroundRepeat' => 'repeat',
					'backgroundPositionX' => 'x',
					'backgroundPositionY' => 'y',
					'backgroundPositionUnits' => 'units',
					// make short names valid too
					'color' => 'color',
					'image' => 'image',
					'repeat' => 'repeat',
					'x' => 'x',
					'y' => 'y',
					'units' => 'units',
				),

				'font' => array(
					'fontWeight' => 'weight',
					'units' => 'units',
					'fontSize' => 'size',
					'fontFamily' => 'family',
					'fontStyle' => 'style',
					'fontVariant' => 'variant',
					// make short names valid too
					'weight' => 'weight',
					'size' => 'size',
					'family' => 'family',
					'style' => 'style',
					'variant' => 'variant',
				),

				'padding' => array(
					'units' => 'units',
					'paddingTop' => 'top',
					'paddingRight' => 'right',
					'paddingBottom' => 'bottom',
					'paddingLeft' => 'left',
					// make short names valid too
					'top' => 'top',
					'right' => 'right',
					'bottom' => 'bottom',
					'left' => 'left',
				),

				'margin' => array(
					'units' => 'units',
					'marginTop' => 'top',
					'marginRight' => 'right',
					'marginBottom' => 'bottom',
					'marginLeft' => 'left',
					// make short names valid too
					'top' => 'top',
					'right' => 'right',
					'bottom' => 'bottom',
					'left' => 'left',
				),

				'border' => array(
					'borderStyle' => 'style',
					'units' => 'units',
					'borderWidth' => 'width',
					'borderColor' => 'color',
					// make short names valid too
					'style' => 'style',
					'width' => 'width',
					'color' => 'color',
				),
			);

			//handle is same for margin and padding -- allows the top value to be
			//used for all padding values
			if (in_array($base_stylevar['datatype'], array('padding', 'margin')) AND $parts[1] <> 'units')
			{
				if (isset($base_stylevar['same']) AND $base_stylevar['same'])
				{
					$parts[1] = $base_stylevar['datatype'] . 'Top';
				}
			}

			if (isset($types[$base_stylevar['datatype']]))
			{
				$mapping = $types[$base_stylevar['datatype']][$parts[1]];
				$output = $base_stylevar[$mapping];
			}
			else
			{
				$output = $base_stylevar;
				for ($i = 1; $i < sizeof($parts); $i++)
				{
					$output = $output[$parts[$i]];
				}
			}
		}
		else
		{
			$output = '';

			switch($base_stylevar['datatype'])
			{
				case 'color':
					$output = $base_stylevar['color'];
				break;

				case 'background':
					switch ($base_stylevar['x'])
					{
						case 'stylevar-left':
							$base_stylevar['x'] = $stylevars->get('left.string');
							break;
						case 'stylevar-right':
							$base_stylevar['x'] = $stylevars->get('right.string');
							break;
						default:
							$base_stylevar['x'] = $base_stylevar['x'] . $base_stylevar['units'];
							break;
					}
					$output = $base_stylevar['color'] . ' ' . (!empty($base_stylevar['image']) ? "$base_stylevar[image]" : 'none') . ' ' .
						$base_stylevar['repeat'] . ' ' .$base_stylevar['x'] . ' ' .
						$base_stylevar['y'] .
						$base_stylevar['units'];
				break;

				case 'textdecoration':
					if ($base_stylevar['none'])
					{
						$output = 'none';
					}
					else
					{
						unset($base_stylevar['datatype'], $base_stylevar['none']);
						$output = implode(' ', array_keys(array_filter($base_stylevar)));
					}
				break;

				case 'font':
					$output = $base_stylevar['style'] . ' ' . $base_stylevar['variant'] . ' ' .
					$base_stylevar['weight'] . ' ' . $base_stylevar['size'] . $base_stylevar['units'] . ' ' .
					$base_stylevar['family'];
				break;

				case 'imagedir':
					$output = $base_stylevar['imagedir'];
				break;

				case 'string':
					$output = $base_stylevar['string'];
				break;

				case 'numeric':
					$output = $base_stylevar['numeric'];
				break;

				case 'size':
					$output =  $base_stylevar['size'] . $base_stylevar['units'];
				break;

				case 'url':
					if (filter_var($base_stylevar['url'], FILTER_VALIDATE_URL))
					{
						$output = $base_stylevar['url'];
					}
					else
					{
						// Assume that the url is relative url
						$output = vB5_Config::instance()->baseurl . '/' . $base_stylevar['url'];
					}
				break;

				case 'path':
					$output = $base_stylevar['path'];
				break;

				case 'fontlist':
					$output = implode(',', preg_split('/[\r\n]+/', trim($base_stylevar['fontlist']), -1, PREG_SPLIT_NO_EMPTY));
				break;

				case 'border':
					$output = $base_stylevar['width'] . $base_stylevar['units'] . ' ' .
						$base_stylevar['style'] . ' ' . $base_stylevar['color'];
				break;

				case 'dimension':
					$output = 'width: ' . intval($base_stylevar['width'])  . $base_stylevar['units'] .
						'; height: ' . intval($base_stylevar['height']) . $base_stylevar['units'] . ';';
				break;

				case 'padding':
				case 'margin':
					foreach (array('top', 'right', 'bottom', 'left') AS $side)
					{
						if ($base_stylevar[$side] != 'auto')
						{
							$base_stylevar[$side] = $base_stylevar[$side] . $base_stylevar['units'];
						}
					}
					if (isset($base_stylevar['same']) AND $base_stylevar['same'])
					{
						$output = $base_stylevar['top'];
					}
					else
					{
						if (self::fetchStyleVar('textdirection') == 'ltr')
						{
							$output = $base_stylevar['top'] . ' ' . $base_stylevar['right'] . ' ' . $base_stylevar['bottom'] . ' ' . $base_stylevar['left'];
						}
						else
						{
							$output = $base_stylevar['top'] . ' ' . $base_stylevar['left'] . ' ' . $base_stylevar['bottom'] . ' ' . $base_stylevar['right'];
						}
					}
				break;
			}
		}
		return $output;
	}

	public static function fetchStyleVar($stylevar)
	{
		$parts = explode('.', $stylevar);
		return self::outputStyleVar(vB5_Template_Stylevar::instance()->get($parts[0]), $parts);
	}

	public static function fetchCustomStylevar($stylevar, $user = false)
	{
		$parts = explode('.', $stylevar);
		$api = Api_InterfaceAbstract::instance();

		// get user info for the currently logged in user
		$customstylevar  = $api->callApi('stylevar', 'get', array($parts[0], $user));
		//$customstylevar = vB_Api::instanceInternal('stylevar')->get($parts[0], $user);
		return self::outputStyleVar($customstylevar[$parts[0]], $parts);
	}

	public static function runMaths($str)
	{
		//this would usually be dangerous, but none of the units make sense
		//in a math string anyway.  Note that there is ambiguty between the '%'
		//unit and the modulo operator.  We don't allow the latter anyway
		//(though we do allow bitwise operations !?)
		$units_found = null;
		foreach (self::$units AS $unit)
		{
			if (strpos($str, $unit))
			{
				$units_found[] = $unit;
			}
		}

		//mixed units.
		if (count($units_found) > 1)
		{
			return "/* ~~cannot perform math on mixed units ~~ found (" .
			implode(",", $units_found) . ") in $str */";
		}

		$str = preg_replace('#([^+\-*=/\(\)\d\^<>&|\.]*)#', '', $str);

		if (empty($str))
		{
			$str = '0';
		}
		else
		{
			//hack: if the math string is invalid we can get a php parse error here.
			//a bad expression or even a bad variable value (blank instead of a number) can
			//cause this to occur.  This fails quietly, but also sets the status code to 500
			//(but, due to a bug in php only if display_errors is *off* -- if display errors
			//is on, then it will work just fine only $str below will not be set.
			//
			//This can result is say an almost correct css file being ignored by the browser
			//for reasons that aren't clear (and goes away if you turn error reporting on).
			//We can check to see if eval hit a parse error and, if so, we'll attempt to
			//clear the 500 status (this does more harm then good) and send an error
			//to the file.  Since math is mostly used in css, we'll provide error text
			//that works best with that.
			$status = @eval("\$str = $str;");
			if ($status === false)
			{
				if (!headers_sent())
				{
					header("HTTP/1.1 200 OK");
				}
				return "/* Invalid math expression */";
			}

			if (count($units_found) == 1)
			{
				$str = $str . $units_found[0];
			}
		}
		return $str;
	}

	public static function linkBuild($type, $info = array(), $extra = array(), $primaryid = null, $primarytitle = null)
	{
		//allow strings of form of query strings for info or extra.  This allows us to hard code some values
		//in the templates instead of having to pass everything in from the php code.  Limitations
		//in the markup do not allow us to build arrays in the template so we need to use strings.
		//We still can't build strings from variables to pass here so we can't mix hardcoded and
		//passed values, but we do what we can.

		if (is_string($info))
		{
			parse_str($info, $new_vals);
			$info = $new_vals;
		}

		if (is_string($extra))
		{
			parse_str($extra, $new_vals);
			$extra = $new_vals;
		}

		return fetch_seo_url($type, $info, $extra, $primaryid, $primarytitle);
	}

	public static function parseData()
	{
		$arguments = func_get_args();
		$controller = array_shift($arguments);
		$method = array_shift($arguments);

		$api = Api_InterfaceAbstract::instance();
		$result = $api->callApi($controller, $method, $arguments, false, true);

		if (is_array($result) AND count($result) == 1 AND isset($result['errors']))
		{
			throw new vB5_Exception_Api($controller, $method, $arguments, $result['errors']);
		}

		return $result;
	}

	public static function parseDataWithErrors()
	{
		$arguments = func_get_args();
		$controller = array_shift($arguments);
		$method = array_shift($arguments);

		$api = Api_InterfaceAbstract::instance();
		$result = $api->callApi($controller, $method, $arguments);

		return $result;
	}

	public static function parseAction()
	{
		$arguments = func_get_args();
		$controller = array_shift($arguments);
		$method = array_shift($arguments);

		$class = 'vB5_Frontend_Controller_' . ucfirst($controller);

		if (!class_exists($class) || !method_exists($class, $method))
		{
			return null;
		}

		$result =  call_user_func_array(array($class, $method), $arguments);

		return $result;
	}

	public static function parseJSON()
	{
		$arguments = func_get_args();
		$searchJSON = array_shift($arguments);
		$arguments = array_pop($arguments);
			$search_structure = json_decode($searchJSON, true);
		if(empty($search_structure))
		{
			return "{}";
		}
		$all_arguments = array();

		foreach ($arguments as $argument)
		{
			if (!is_array($argument))
			{
				continue;
			}
			$all_arguments = array_merge($argument, $all_arguments);
		}
		$search_structure = self::replaceJSON($search_structure, $all_arguments);

		return json_encode($search_structure);
	}

	protected static function replaceJSON($search_structure, $all_arguments)
	{
		foreach ($search_structure as $filter => $value) {
			if(is_array($value))
			{
				if(array_key_exists("param", $value))
				{
					$param_name = $value['param'];
					$param_value = null;
					if(array_key_exists($param_name, $all_arguments))
					{
						$search_structure[$filter] = (string) $all_arguments[$param_name];
					}
					else
					{
						unset($search_structure[$filter]);
						// re-indexing an indexed array so it won't be considered associative
						if(is_numeric($filter))
						{
							$search_structure = array_values($search_structure);
						}
					}
				}
				else
				{
					$val = self::replaceJSON($value, $all_arguments);
					if($val === null)
					{
						unset($search_structure[$filter]);
					}
					else
					{
						$search_structure[$filter] = $val;
					}
				}
			}
		}
		if(empty($search_structure))
		{
			$search_structure = null;
		}
		return $search_structure;
	}

	public static function includeTemplate()
	{
		$arguments = func_get_args();

		$template_id = array_shift($arguments);
		$args = array_shift($arguments);

		$cache = vB5_Template_Cache::instance();

		return $cache->register($template_id, $args);
	}

	public static function includeJs()
	{
		$scripts = func_get_args();

		if (!empty($scripts) AND $scripts[0] == '1')
		{
			$scripts = array_slice($scripts, 1);
			if (!empty($scripts))
			{
				$javascript = vB5_Template_Javascript::instance();
				$rendered =  $javascript->insertJsInclude($scripts);
				return $rendered;
			}
			return '';
		}

		$javascript = vB5_Template_Javascript::instance();
		return $javascript->register($scripts);
	}

	public static function includeCss()
	{
		$stylesheets = func_get_args();
		foreach ($stylesheets AS $key => $stylesheet)
		{
			//For when we remove a record per below
			if (empty($stylesheet))
			{
				unset($stylesheets[$key]);
				continue;
			}

			if ((substr($stylesheet, -7, 7) == 'userid=' ))
			{
				if (($key < count($stylesheets) - 1) AND (is_numeric($stylesheets[$key + 1])))
				{
					$stylesheets[$key] .= $stylesheets[$key + 1];
					unset($stylesheets[$key + 1]);
				}
				if (isset($stylesheets[$key + 2]) AND isset($stylesheets[$key + 3]) AND
					($stylesheets[$key + 2] == '&showusercss=') OR ($stylesheets[$key + 2] == '&amp;showusercss='))
				{
					$stylesheets[$key] .= $stylesheets[$key + 2] . $stylesheets[$key + 3];
					unset($stylesheets[$key + 2]);
					unset($stylesheets[$key + 3]);
				}
			}
		}
		$stylesheet = vB5_Template_Stylesheet::instance();
		return $stylesheet->register($stylesheets);
	}

	public static function includeCssFile()
	{
		$stylesheets = func_get_args();
		$stylesheet = vB5_Template_Stylesheet::instance();
		return $stylesheet->getCssFile($stylesheets[0]);
	}

	/**
	 * Determines if we are allowed to redirect to this URL, based
	 * on Admin CP whitelist settings
	 *
	 * @param	string	The URL
	 *
	 * @return	bool	True if redirecting to this URL is allowed, false otherwise
	 */
	public static function allowRedirectToUrl($url)
	{
		if (empty($url))
		{
			return false;
		}

		$options = vB5_Template_Options::instance();

		if ($options->get('options.redirect_whitelist_disable'))
		{
			return true;
		}

		$foundurl = false;

		if ($urlinfo = @vB5_String::parseUrl($url))
		{
			if (!$urlinfo['scheme'])
			{
				$foundurl = true; // Relative redirect.
			}
			else
			{
				$whitelist = array();
				if ($options->get('options.redirect_whitelist'))
				{
					$whitelist = explode("\n", trim($options->get('options.redirect_whitelist')));
				}

				// Add the base and core urls to the whitelist
				$baseinfo = @vB5_String::parseUrl(vB5_Config::instance()->baseurl);
				$coreinfo = @vB5_String::parseUrl(vB5_Config::instance()->baseurl_core);

				$baseurl = "{$baseinfo['scheme']}://{$baseinfo['host']}";
				$coreurl = "{$coreinfo['scheme']}://{$coreinfo['host']}";

				array_unshift($whitelist, strtolower($baseurl));
				array_unshift($whitelist, strtolower($coreurl));

				$vburl = strtolower($url);
				foreach ($whitelist AS $urlx)
				{
					$urlx = trim($urlx);
					if ($vburl == strtolower($urlx) OR strpos($vburl, strtolower($urlx) . '/', 0) === 0)
					{
						$foundurl = true;
						break;
					}
				}
			}
		}

		return $foundurl;
	}

	public static function doRedirect($url, $bypasswhitelist = false)
	{
		if (!$bypasswhitelist AND !self::allowRedirectToUrl($url))
		{
			throw new vB5_Exception('invalid_redirect_url');
		}

		if (vB5_Request::get('useEarlyFlush'))
		{
			echo '<script type="text/javascript">window.location = "' . $url . '";</script>';
		}
		else
		{
			header('Location: ' . $url);
		}

		die();
	}

	/**
	 * Formats a UNIX timestamp into a human-readable string according to vBulletin prefs
	 *
	 * Note: Ifvbdate() is called with a date format other than than one in $vbulletin->options[],
	 * set $locale to false unless you dynamically set the date() and strftime() formats in the vbdate() call.
	 *
	 * @param	string	Date format string (same syntax as PHP's date() function)
	 * @param	integer	Unix time stamp
	 * @param	boolean	If true, attempt to show strings like "Yesterday, 12pm" instead of full date string
	 * @param	boolean	If true, and user has a language locale, use strftime() to generate language specific dates
	 * @param	boolean	If true, don't adjust time to user's adjusted time .. (think gmdate instead of date!)
	 * @param	boolean	If true, uses gmstrftime() and gmdate() instead of strftime() and date()
	 *
	 * @return	string	Formatted date string
	 */

	protected static function vbdate($format, $timestamp = 0, $doyestoday = false, $locale = true, $adjust = true, $gmdate = false)
	{
		$timenow = time();
		if (!$timestamp)
		{
			$timestamp = $timenow;
		}

		$options = vB5_Template_Options::instance();
		$vboptions = vB::getDatastore()->getValue('options');

		$uselocale = false;

		$timezone = vB5_User::get('timezoneoffset');
		if (vB5_User::get('dstonoff') || (vB5_User::get('dstauto') AND $vboptions['dstonoff']))
		{
			// DST is on, add an hour
			$timezone++;
		}
		$hourdiff = (date('Z', time()) / 3600 - $timezone) * 3600;

		if (vB5_User::get('lang_locale'))
		{
			$uselocale = true;
		}

		if ($uselocale AND $locale)
		{
			if ($gmdate)
			{
				$datefunc = 'gmstrftime';
			}
			else
			{
				$datefunc = 'strftime';
			}
		}
		else
		{
			if ($gmdate)
			{
				$datefunc = 'gmdate';
			}
			else
			{
				$datefunc = 'date';
			}
		}
		if (!$adjust)
		{
			$hourdiff = 0;
		}

		if ($timestamp < 0)
		{
			$timestamp_adjusted = $timestamp;
		}
		else
		{
			$timestamp_adjusted = max(0, $timestamp - $hourdiff);
		}

		if ($format == $options->get('options.dateformat') AND $doyestoday AND $options->get('options.yestoday'))
		{
			if (vB5_User::get('lang_locale'))
			{
				$format = vB5_User::get('lang_dateoverride');
			}

			if ($options->get('options.yestoday') == 1)
			{
				if (!defined('TODAYDATE'))
				{
					define('TODAYDATE', self::vbdate('n-j-Y', $timenow, false, false));
					define('YESTDATE', self::vbdate('n-j-Y', $timenow - 86400, false, false));
					define('TOMDATE', self::vbdate('n-j-Y', $timenow + 86400, false, false));
				}

				$datetest = @date('n-j-Y', $timestamp - $hourdiff);

				if ($datetest == TODAYDATE)
				{
					$returndate = self::parsePhrase('today');
				}
				else if ($datetest == YESTDATE)
				{
					$returndate = self::parsePhrase('yesterday');
				}
				else
				{
					$returndate = $datefunc($format, $timestamp_adjusted);
				}
			}
			else
			{
				$timediff = $timenow - $timestamp;

				if ($timediff >= 0)
				{
					if ($timediff < 120)
					{
						$returndate = self::parsePhrase('1_minute_ago');
					}
					else if ($timediff < 3600)
					{
						$returndate = self::parsePhrase('x_minutes_ago', intval($timediff / 60));
					}
					else if ($timediff < 7200)
					{
						$returndate = self::parsePhrase('1_hour_ago');
					}
					else if ($timediff < 86400)
					{
						$returndate = self::parsePhrase('x_hours_ago', intval($timediff / 3600));
					}
					else if ($timediff < 172800)
					{
						$returndate = self::parsePhrase('1_day_ago');
					}
					else if ($timediff < 604800)
					{
						$returndate = self::parsePhrase('x_days_ago', intval($timediff / 86400));
					}
					else if ($timediff < 1209600)
					{
						$returndate = self::parsePhrase('1_week_ago');
					}
					else if ($timediff < 3024000)
					{
						$returndate = self::parsePhrase('x_weeks_ago', intval($timediff / 604900));
					}
					else
					{
						$returndate = $datefunc($format, $timestamp_adjusted);
					}
				}
				else
				{
					$returndate = $datefunc($format, $timestamp_adjusted);
				}
			}
		}
		else
		{
			if ($format == 'Y' AND $uselocale AND $locale)
			{
				$format = '%Y'; // For copyright year
			}

			$returndate = $datefunc($format, $timestamp_adjusted);
		}

		return $returndate;
	}

	public static function buildUrlAdmincpTemp($route, array $parameters = array())
	{
		$config = vB5_Config::instance();

		static $baseurl = null;
		if ($baseurl === null)
		{
			$baseurl = $config->baseurl;
		}

		// @todo: this might need to be a setting
		$admincp_directory = $config->admincpdir;

		// @todo: This would be either index.php or empty, depending on use of mod_rewrite
		$index_file = 'index.php';

		$url = "$baseurl/$admincp_directory/$index_file";

		if (!empty($route))
		{
			$url .= '/' . htmlspecialchars($route);
		}
		if (!empty($parameters))
		{
			$url .= '?' . http_build_query($parameters, '', '&amp;');
		}
		return $url;
	}

	/**
	 * Returns the URL for a route with the passed parameters
	 * @param mixed $route - Route identifier (routeid or name)
	 * @param array $data - Data for building route
	 * @param array $extra - Additional data to be added
	 * @param array $options - Options for building URL
	 *					- noBaseUrl: skips adding the baseurl
	 *					- anchor: anchor id to be added
	 * @return type
	 * @throws vB5_Exception_Api
	 */
	public static function buildUrl($route, $data = array(), $extra = array(), $options = array())
	{
		return vB5_Template_Url::instance()->register($route, $data, $extra, $options);
	}

	public static function hook($hookName, $vars = array())
	{
		$hooks = Api_InterfaceAbstract::instance()->callApi('template','fetchTemplateHooks', array('hookName'=>$hookName));

		if ($hooks)
		{
			$placeHolders = '';
			foreach ($hooks as $templates)
			{
				foreach($templates as $template => $arguments)
				{
					$passed = self::buildVars($arguments, $vars);
					$placeHolders .= self::includeTemplate($template, $passed) . "\r\n";
				}
			}

			unset($vars);
			return $placeHolders;
		}
	}

	public static function buildVars($select, &$master)
	{
		$args = array();

		foreach ($select AS $argname => $argval)
		{
			$result = array();

			foreach ($argval AS $varname => $value)
			{
				if(is_array($value))
				{
					self::nextLevel($result, $value, $master[$varname]);
				}
				else
				{
					$result = $master[$varname];
				}
			}

			$args[$argname] = $result;
		}

		return $args;
	}

	public static function nextLevel(&$res, $array, &$master)
	{
		foreach ($array AS $varname => $value)
		{
			if(is_array($value))
			{
				self::nextLevel($res, $value, $master[$varname]);
			}
			else
			{
				$res = $master[$varname];
			}
		}
	}

	/**
	* Browser detection system - returns whether or not the visiting browser is the one specified
	*
	* @param	string	Browser name (opera, ie, mozilla, firebord, firefox... etc. - see $is array)
	* @param	float	Minimum acceptable version for true result (optional)
	*
	* @return	boolean
	*/
	public static function isBrowser($browser, $version = 0)
	{
		static $is;
		if (!is_array($is))
		{
			$useragent = strtolower($_SERVER['HTTP_USER_AGENT']); //strtolower($_SERVER['HTTP_USER_AGENT']);
			$is = array(
				'opera'     => 0,
				'ie'        => 0,
				'mozilla'   => 0,
				'firebird'  => 0,
				'firefox'   => 0,
				'camino'    => 0,
				'konqueror' => 0,
				'safari'    => 0,
				'webkit'    => 0,
				'webtv'     => 0,
				'netscape'  => 0,
				'mac'       => 0
			);

			// detect opera
				# Opera/7.11 (Windows NT 5.1; U) [en]
				# Mozilla/4.0 (compatible; MSIE 6.0; MSIE 5.5; Windows NT 5.0) Opera 7.02 Bork-edition [en]
				# Mozilla/4.0 (compatible; MSIE 6.0; MSIE 5.5; Windows NT 4.0) Opera 7.0 [en]
				# Mozilla/4.0 (compatible; MSIE 5.0; Windows 2000) Opera 6.0 [en]
				# Mozilla/4.0 (compatible; MSIE 5.0; Mac_PowerPC) Opera 5.0 [en]
			if (strpos($useragent, 'opera') !== false)
			{
				preg_match('#opera(/| )([0-9\.]+)#', $useragent, $regs);
				$is['opera'] = $regs[2];
			}

			// detect internet explorer
				# Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; Q312461)
				# Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.0.3705)
				# Mozilla/4.0 (compatible; MSIE 5.22; Mac_PowerPC)
				# Mozilla/4.0 (compatible; MSIE 5.0; Mac_PowerPC; e504460WanadooNL)
			if (strpos($useragent, 'msie ') !== false AND !$is['opera'])
			{
				preg_match('#msie ([0-9\.]+)#', $useragent, $regs);
				$is['ie'] = $regs[1];
			}

			// detect macintosh
			if (strpos($useragent, 'mac') !== false)
			{
				$is['mac'] = 1;
			}

			// detect safari
				# Mozilla/5.0 (Macintosh; U; PPC Mac OS X; en-us) AppleWebKit/74 (KHTML, like Gecko) Safari/74
				# Mozilla/5.0 (Macintosh; U; PPC Mac OS X; en) AppleWebKit/51 (like Gecko) Safari/51
				# Mozilla/5.0 (Windows; U; Windows NT 6.0; en) AppleWebKit/522.11.3 (KHTML, like Gecko) Version/3.0 Safari/522.11.3
				# Mozilla/5.0 (iPhone; U; CPU like Mac OS X; en) AppleWebKit/420+ (KHTML, like Gecko) Version/3.0 Mobile/1C28 Safari/419.3
				# Mozilla/5.0 (iPod; U; CPU like Mac OS X; en) AppleWebKit/420.1 (KHTML, like Gecko) Version/3.0 Mobile/3A100a Safari/419.3
			if (strpos($useragent, 'applewebkit') !== false)
			{
				preg_match('#applewebkit/([0-9\.]+)#', $useragent, $regs);
				$is['webkit'] = $regs[1];

				if (strpos($useragent, 'safari') !== false)
				{
					preg_match('#safari/([0-9\.]+)#', $useragent, $regs);
					$is['safari'] = $regs[1];
				}
			}

			// detect konqueror
				# Mozilla/5.0 (compatible; Konqueror/3.1; Linux; X11; i686)
				# Mozilla/5.0 (compatible; Konqueror/3.1; Linux 2.4.19-32mdkenterprise; X11; i686; ar, en_US)
				# Mozilla/5.0 (compatible; Konqueror/2.1.1; X11)
			if (strpos($useragent, 'konqueror') !== false)
			{
				preg_match('#konqueror/([0-9\.-]+)#', $useragent, $regs);
				$is['konqueror'] = $regs[1];
			}

			// detect mozilla
				# Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.4b) Gecko/20030504 Mozilla
				# Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.2a) Gecko/20020910
				# Mozilla/5.0 (X11; U; Linux 2.4.3-20mdk i586; en-US; rv:0.9.1) Gecko/20010611
			if (strpos($useragent, 'gecko') !== false AND !$is['safari'] AND !$is['konqueror'])
			{
				// See bug #26926, this is for Gecko based products without a build
				$is['mozilla'] = 20090105;
				if (preg_match('#gecko/(\d+)#', $useragent, $regs))
				{
					$is['mozilla'] = $regs[1];
				}

				// detect firebird / firefox
					# Mozilla/5.0 (Windows; U; WinNT4.0; en-US; rv:1.3a) Gecko/20021207 Phoenix/0.5
					# Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.4b) Gecko/20030516 Mozilla Firebird/0.6
					# Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.4a) Gecko/20030423 Firebird Browser/0.6
					# Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.6) Gecko/20040206 Firefox/0.8
				if (strpos($useragent, 'firefox') !== false OR strpos($useragent, 'firebird') !== false OR strpos($useragent, 'phoenix') !== false)
				{
					preg_match('#(phoenix|firebird|firefox)( browser)?/([0-9\.]+)#', $useragent, $regs);
					$is['firebird'] = $regs[3];

					if ($regs[1] == 'firefox')
					{
						$is['firefox'] = $regs[3];
					}
				}

				// detect camino
					# Mozilla/5.0 (Macintosh; U; PPC Mac OS X; en-US; rv:1.0.1) Gecko/20021104 Chimera/0.6
				if (strpos($useragent, 'chimera') !== false OR strpos($useragent, 'camino') !== false)
				{
					preg_match('#(chimera|camino)/([0-9\.]+)#', $useragent, $regs);
					$is['camino'] = $regs[2];
				}
			}

			// detect web tv
			if (strpos($useragent, 'webtv') !== false)
			{
				preg_match('#webtv/([0-9\.]+)#', $useragent, $regs);
				$is['webtv'] = $regs[1];
			}

			// detect pre-gecko netscape
			if (preg_match('#mozilla/([1-4]{1})\.([0-9]{2}|[1-8]{1})#', $useragent, $regs))
			{
				$is['netscape'] = "$regs[1].$regs[2]";
			}
		}

		// sanitize the incoming browser name
		$browser = strtolower($browser);
		if (substr($browser, 0, 3) == 'is_')
		{
			$browser = substr($browser, 3);
		}

		// return the version number of the detected browser if it is the same as $browser
		if ($is["$browser"])
		{
			// $version was specified - only return version number if detected version is >= to specified $version
			if ($version)
			{
				if ($is["$browser"] >= $version)
				{
					return $is["$browser"];
				}
			}
			else
			{
				return $is["$browser"];
			}
		}

		// if we got this far, we are not the specified browser, or the version number is too low
		return 0;
	}

	public static function vBVar($value)
	{
		return vB5_String::htmlSpecialCharsUni($value);
	}

	/** Gets rendered signature for a user
	 *
	 *	@param	int
	 *
	 * @return	string
	 *
	 */
	public static function parseSignature()
	{
		$args = func_get_args();
		if (count($args) < 2)
		{
			return '';
		}
		$userid = $args[1];
		$cacheKey = "vbSig_$userid";
		$cache = vB_Cache::instance(vB_Cache::CACHE_STD);
		$signature = $cache->read($cacheKey);
		if ($signature !== false)
		{
			return $signature;
		}
		$sigInfo =  Api_InterfaceAbstract::instance()->callApi('user', 'fetchSignature', array($userid));
		if (empty($sigInfo) OR empty ($sigInfo['raw']))
		{
			return '';
		}
		$parser = new vB5_Template_BbCode();
		$parsed = $parser->doParse($sigInfo['raw'], $sigInfo['permissions']['dohtml'], $sigInfo['permissions']['dosmilies'],
			$sigInfo['permissions']['dobbcode'], $sigInfo['permissions']['dobbimagecode']);
		$cache->write($cacheKey, $parsed, 1440, "userChg_$userid");
		return $parsed;
	}
}
