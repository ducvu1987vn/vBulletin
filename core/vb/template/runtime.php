<?php

/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 5.0.0
  || # ---------------------------------------------------------------- # ||
  || # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

class vB_Template_Runtime
{
	public static $units = array('%', 'px', 'pt', 'em', 'ex', 'pc', 'in', 'cm', 'mm');

	public static function date($timestamp, $format = 'r')
	{
		if (empty($format))
		{
			$format = 'r';
		}
		return vbdate($format, intval($timestamp));
	}

	public static function time($timestamp)
	{
		if (empty($timestamp))
		{
			$timestamp = 0;
		}
		return vbdate(vB::getDatastore()->getOption('timeformat'), $timestamp);
	}

	/**
	 * This method is defined just to avoid errors while saving the template. The real implementation
	 * is in the presentation layer.
	 * @param <type> $var
	 * @return string
	 */
	public static function datetime($timestamp, $format = 'date, time', $formatdate = '', $formattime = '')
	{
		return '';
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
		global $vbphrase;
		$arg_list = func_get_args();
		$arg_list[0] = $vbphrase[$phraseName];
		return construct_phrase_from_array($arg_list);
	}

	public static function addStyleVar($name, $value, $datatype = 'string')
	{
		global $vbulletin;

		switch ($datatype)
		{
			case 'string':
				$vbulletin->stylevars["$name"] = array(
					'datatype' => $datatype,
					'string'   => $value,
				);
			break;
			case 'imgdir':
				$vbulletin->stylevars["$name"] = array(
					'datatype' => $datatype,
					'imagedir' => $value,
				);
			break;
		}
	}

	private static function outputStyleVar($base_stylevar, $parts = array())
	{
		global $vbulletin;

		if (isset($base_stylevar['value']) AND $base_stylevar['value'] == false)
		{
			// Invalid stylevar value
			return;
		}
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
			if (isset($base_stylevar['datatype']) AND in_array($base_stylevar['datatype'], array('padding', 'margin')) AND $parts[1] <> 'units')
			{
				if (isset($base_stylevar['same']) AND $base_stylevar['same'])
				{
					$parts[1] = $base_stylevar['datatype'] . 'Top';
				}
			}

			if (isset($base_stylevar['datatype']) AND isset($types[$base_stylevar['datatype']]))
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
					$base_stylevar['x'] = !empty($base_stylevar['x']) ? $base_stylevar['x'] : '0';
					$base_stylevar['y'] = !empty($base_stylevar['y']) ? $base_stylevar['y'] : '0';
					$base_stylevar['repeat'] = !empty($base_stylevar['repeat']) ? $base_stylevar['repeat'] : '';
					$base_stylevar['units'] = !empty($base_stylevar['units']) ? $base_stylevar['units'] : '';
					switch ($base_stylevar['x'])
					{
						case 'stylevar-left':
							$base_stylevar['x'] = $vbulletin->stylevars['left']['string']; break;
						case 'stylevar-right':
							$base_stylevar['x'] = $vbulletin->stylevars['right']['string']; break;
						default:
							$base_stylevar['x'] = $base_stylevar['x'] . $base_stylevar['units']; break;
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
					$output = $base_stylevar['url'];
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
						if (vB_Template_Runtime::fetchStyleVar('textdirection') == 'ltr')
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
		global $vbulletin;

		$parts = explode('.', $stylevar);
		if (empty($parts[0]) OR !isset($vbulletin->stylevars[$parts[0]]))
		{
			return;
		}
		return self::outputStyleVar($vbulletin->stylevars[$parts[0]], $parts);
	}

	public static function fetchCustomStylevar($stylevar, $user = false)
	{
		$parts = explode('.', $stylevar);

		$customstylevar = vB_Api::instanceInternal('stylevar')->get($parts[0], $user);

		// if there is no user passed and the customstylevar is empty (there is no session) fetch the sitedefault value
		// VBV-2213: Hiding customizations for users that have this setting enabled
		if (empty($customstylevar) AND $user === false)
		{
			return self::fetchStyleVar($stylevar);
		}
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
				$str = $str.$units_found[0];
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

	/**
	 * This method is defined just to avoid errors while saving the template. The real implementation
	 * is in the presentation layer.
	 * @param <type> $var
	 * @return string
	 */
	public static function parseData() {
		return '';
	}

	/**
	 * This method is defined just to avoid errors while saving the template. The real implementation
	 * is in the presentation layer.
	 * @param <type> $var
	 * @return string
	 */
	public static function parseAction() {
		return '';
	}

	/**
	 * This method is defined just to avoid errors while saving the template. The real implementation
	 * is in the presentation layer.
	 * @param <type> $var
	 * @return string
	 */
	public static function includeTemplate() {
		return '';
	}

	/**
	 * This method is defined just to avoid errors while saving the template. The real implementation
	 * is in the presentation layer.
	 * @param <type> $var
	 * @return string
	 */
	public static function parseJSON() {
		return '';
	}

	/**
	 * This method is defined just to avoid errors while saving the template. The real implementation
	 * is in the presentation layer.
	 * @param <type> $var
	 * @return string
	 */
	public static function includeCss() {
		return '';
	}

	/**
	 * This method is defined just to avoid errors while saving the template. The real implementation
	 * is in the presentation layer.
	 * @param <type> $var
	 * @return string
	 */
	public static function includeCssFile() {
		return '';
	}

	/**
	 * This method is defined just to avoid errors while saving the template. The real implementation
	 * is in the presentation layer.
	 * @param <type> $var
	 * @return string
	 */
	public static function includeJs() {
		return '';
	}

	/**
	 * This method is defined just to avoid errors while saving the template. The real implementation
	 * is in the presentation layer.
	 * @param <type> $var
	 * @return string
	 */
	public static function doRedirect() {
		return '';
	}

	/**
	 * This method is defined just to avoid errors while saving the template. The real implementation
	 * is in the presentation layer.
	 * @param <type> $var
	 * @return string
	 */
	public static function buildUrlAdmincpTemp() {
		return '';
	}

	/**
	 * This method is defined just to avoid errors while saving the template. The real implementation
	 * is in the presentation layer.
	 * @param <type> $var
	 * @return string
	 */
	public static function buildUrl() {
		return '';
	}

	/**
	 * This method is defined just to avoid errors while saving the template. The real implementation
	 * is in the presentation layer.
	 * @param <type> $var
	 * @return string
	 */
	public static function hook($hook) {
		return '';
	}

	public static function vBVar($value)
	{
		return vB_String::htmlSpecialCharsUni($value);
	}

	/**
	 * This method is defined just to avoid errors while saving the template. The real implementation
	 * is in the presentation layer.
	 * @return string
	 */
	public static function parseDataWithErrors() {
		return '';
	}

	/**
	 * This method is defined just to avoid errors while saving the template. The real implementation
	 * is in the presentation layer.
	 * @return string
	 */
	public static function parseSignature() {
		return '';
	}

}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
