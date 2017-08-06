<?php if (!defined('VB_ENTRY')) die('Access denied.');
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
 * String
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision$
 * @since $Date$
 * @copyright vBulletin Solutions Inc.
 */
class vB_String
{
	// TODO: this array is bigger in global.js
	public static $convertionMap = array(
		'ß'=>'Ss','à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a',
		'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i',
		'ï'=>'i', 'ð'=>'d', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u',
		'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'ƒ'=>'f'
	);

	/**
	 * Unicode-safe version of htmlspecialchars()
	 *
	 * @param	string	Text to be made html-safe
	 *
	 * @return	string
	 */
	public static function htmlSpecialCharsUni($text, $entities = true)
	{
		if ($entities)
		{
			$text = preg_replace_callback(
				'/&((#([0-9]+)|[a-z]+);)?/si',
				array(__CLASS__, 'htmlSpecialCharsUniCallback'),
				$text
			);
		}
		else
		{
			$text = preg_replace(
				// translates all non-unicode entities
				'/&(?!(#[0-9]+|[a-z]+);)/si',
				'&amp;',
				$text
			);
		}

		return str_replace(
			// replace special html characters
			array('<', '>', '"'),
			array('&lt;', '&gt;', '&quot;'),
				$text
		);
	}

	protected static function htmlSpecialCharsUniCallback($matches)
	{
		if (count($matches) == 1)
		{
			return '&amp;';
		}

		if (strpos($matches[2], '#') === false)
		{
			// &gt; like
			if ($matches[2] == 'shy')
			{
				return '&shy;';
			}
			else
			{
				return "&amp;$matches[2];";
			}
		}
		else
		{
			// Only convert chars that are in ISO-8859-1
			if (($matches[3] >= 32 AND $matches[3] <= 126)
				OR
				($matches[3] >= 160 AND $matches[3] <= 255))
			{
				return "&amp;#$matches[3];";
			}
			else
			{
				return "&#$matches[3];";
			}
		}
	}

	// To be used as callback function
	/**
	 *
	 * @param string $val
	 * @return bool
	 */
	public static function isEmpty($val)
	{
		return !empty($val);
	}

	/** Takes a string of arbitrary length and returns a preview string of maximum arbitrary length
	*
	*	@param	string
	*
	* 	@return	string
	*
	***/
	public static function getPreviewText($text)
	{
		static $textLength = false;
		//Save time if we're passed something like empty string or zero.
		if (!$text)
		{
			return $text;
		}
		//We don't want any table content to display when we generate the preview- unless there
		// is nothing else

		if (empty($textLength))
		{
			$options = vB::getDatastore()->get_value('options');

			if (!empty($options['threadlength']))
			{
				$textLength = $options['threadlength'];
			}

			if (empty($textLength))
			{
				$textLength = 150;
			}

		}
		$tableless_text = trim(preg_replace('/\[TABLE(.+)\[\/TABLE\]/is', ' ', $text));

		if ($tableless_text =='')
		{
			$tableless_text = trim(preg_replace('/\<(\s*)TABLE(.+)\<\/TABLE\>/is', ' ', $text));
		}

		$previewtext = self::fetchCensoredText(self::htmlSpecialCharsUni($tableless_text));

		if (strlen($previewtext) > ($textLength + 10))
		{
			//try to split on a word break.
			$previewText = substr($tableless_text, 0, $textLength + 10 );
			for ($i = 0; $i < 20; $i++)
			{
				$checkChar = $previewtext[$textLength + 10 - $i];
				if ($checkChar == ' ' OR $checkChar == "\n" OR $checkChar == "\." OR $checkChar == "-")
				{
					$previewtext = substr($previewtext, 0, $textLength + 10 - $i);
					break;
				}
			}
		}
		//We tend to get some blank lines that we don't need.
		$previewtext = preg_replace('/^\<br\>$/i', '', $previewtext);
		$previewtext = preg_replace('/^\<br\/\>$/i', '', $previewtext);
		$previewtext = preg_replace('/^\<br \/\>$/i', '', $previewtext);
		return $previewtext;
	}

	/**
	 * Replaces any instances of words censored in $options['censorwords'] with $options['censorchar']
	 *
	 * @param	string	Text to be censored
	 *
	 * @return	string
	 */
	public static function fetchCensoredText($text)
	{
		static $censorwords;
		$options = vB::getDatastore()->get_value('options');

		if (!$text)
		{
			// return $text rather than nothing, since this could be '' or 0
			return $text;
		}
		$options = vB::getDatastore()->get_value('options');

		if ($options['enablecensor'] AND !empty($options['censorwords']))
		{
			if (empty($censorwords))
			{
				$options['censorwords'] = preg_quote($options['censorwords'], '#');
				$censorwords = preg_split('#[ \r\n\t]+#', $options['censorwords'], -1, PREG_SPLIT_NO_EMPTY);
			}

			foreach ($censorwords AS $censorword)
			{
				if (substr($censorword, 0, 2) == '\\{')
				{
					if (substr($censorword, -2, 2) == '\\}')
					{
						// prevents errors from the replace if the { and } are mismatched
						$censorword = substr($censorword, 2, -2);
					}

					// ASCII character search 0-47, 58-64, 91-96, 123-127
					$nonword_chars = '\x00-\x2f\x3a-\x40\x5b-\x60\x7b-\x7f';

					// words are delimited by ASCII characters outside of A-Z, a-z and 0-9
					$text = preg_replace(
						'#(?<=[' . $nonword_chars . ']|^)' . $censorword . '(?=[' . $nonword_chars . ']|$)#si',
						str_repeat($options['censorchar'], self::vbStrlen($censorword)),
						$text
					);
				}
				else
				{
					$text = preg_replace("#$censorword#si", str_repeat($options['censorchar'], self::vbStrlen($censorword)), $text);
				}
			}
		}

		// strip any admin-specified blank ascii chars
		$text = self::stripBlankAscii($text, $options['censorchar']);

		return $text;
	}

	/**
	* Strips away bbcode from a given string, leaving plain text
	*
	* @param	string	Text to be stripped of bbcode tags
	* @param	boolean	If true, strip away quote tags AND their contents
	* @param	boolean	If true, use the fast-and-dirty method rather than the shiny and nice method
	* @param	boolean	If true, display the url of the link in parenthesis after the link text
	* @param	boolean	If true, strip away img/video tags and their contents
	* @param	boolean	If true, keep [quote] tags. Useful for API.
	*
	* @return	string
	*/
	public static function stripBbcode($message, $stripquotes = false, $fast_and_dirty = false, $showlinks = true, $stripimg = false, $keepquotetags = false)
	{
		$find = array();
		$replace = array();
		$block_elements = array(
			'code',
			'php',
			'html',
			'quote',
			'indent',
			'center',
			'left',
			'right',
			'video',
		);

		if ($stripquotes)
		{
			// [quote=username] and [quote]
			$message = strip_quotes($message);
		}

		if ($stripimg)
		{
			$find[] = '#\[(attach|img|video).*\].+\[\/\\1\]#siU';
			$replace[] = '';
		}

		// a really quick and rather nasty way of removing vbcode
		if ($fast_and_dirty)
		{

			// any old thing in square brackets
			$find[] = '#\[.*/?\]#siU';
			$replace[] = '';

			$message = preg_replace($find, $replace, $message);
		}
		// the preferable way to remove vbcode
		else
		{

			// simple links
			$find[] = '#\[(email|url)=("??)(.+)\\2\]\\3\[/\\1\]#siU';
			$replace[] = '\3';

			// named links
			$find[] = '#\[(email|url)=("??)(.+)\\2\](.+)\[/\\1\]#siU';
			$replace[] = ($showlinks ? '\4 (\3)' : '\4');

			// replace links (and quotes if specified) from message
			$message = preg_replace($find, $replace, $message);

			if ($keepquotetags)
			{
				$regex = '#\[(?!quote)(\w+?)(?>[^\]]*?)\](.*)(\[/\1\])#siU';
			}
			else
			{
				$regex = '#\[(\w+?)(?>[^\]]*?)\](.*)(\[/\1\])#siU';
			}

			// strip out all other instances of [x]...[/x]
			while(preg_match_all($regex, $message, $regs))
			{
				foreach($regs[0] AS $key => $val)
				{
					$message = str_replace($val, (in_array(strtolower($regs[1]["$key"]), $block_elements) ? "\n" : '') . $regs[2]["$key"], $message);
				}
			}
			$message = str_replace('[*]', ' ', $message);
		}

		return trim($message);
	}

	/**
	 * Replaces any non-printing ASCII characters with the specified string.
	 * This also supports removing Unicode characters automatically when
	 * the entered value is >255 or starts with a 'u'.
	 *
	 * @param	string	Text to be processed
	 * @param	string	String with which to replace non-printing characters
	 *
	 * @return	string
	 */
	public static function stripBlankAscii($text, $replace)
	{
		static $blanks = null;
		$options = vB::getDatastore()->get_value('options');
		$userinfo = $userinfo = vB::getCurrentSession()->fetch_userinfo();

		if ($blanks === null AND trim($options['blankasciistrip']) != '')
		{
			$blanks = array();

			if (!($charset = self::getCharset()))
			{
				$charset = $userinfo['lang_charset'];
			}

			$charset_unicode = (strtolower($charset) == 'utf-8');

			$raw_blanks = preg_split('#\s+#', $options['blankasciistrip'], -1, PREG_SPLIT_NO_EMPTY);
			foreach ($raw_blanks AS $code_point)
			{
				if ($code_point[0] == 'u')
				{
					// this is a unicode character to remove
					$code_point = intval(substr($code_point, 1));
					$force_unicode = true;
				}
				else
				{
					$code_point = intval($code_point);
					$force_unicode = false;
				}

				if ($code_point > 255 OR $force_unicode OR $charset_unicode)
				{
					// outside ASCII range or forced Unicode, so the chr function wouldn't work anyway
					$blanks[] = '&#' . $code_point . ';';
					$blanks[] = convert_int_to_utf8($code_point);
				}
				else
				{
					$blanks[] = chr($code_point);
				}
			}
		}

		if ($blanks)
		{
			$text = str_replace($blanks, $replace, $text);
		}

		return $text;
	}

	/**
	 * This is a temporary function used to get the stylevar 'charset' (added for presentation).
	 *
	 * @return string, stylevar charset value
	 */
	public static function getTempCharset()
	{
		// first check for user info
		$userinfo = vB_Api::instanceInternal('user')->fetchCurrentUserinfo();
		if ($userinfo === null OR empty($userinfo['lang_charset']))
		{
			$encoding = vB_Template_Runtime::fetchStyleVar('charset');
		}

		return strtoupper($encoding);
	}

	/**
	 * Attempts to intelligently wrap excessively long strings onto multiple lines
	 *
	 * @param	integer max word wrap length
	 * @param	string	Text to be wrapped
	 * @param	string	Text to insert at the wrap point
	 *
	 * @return	string
	 */
	public static function fetchWordWrappedString($text, $limit, $wraptext = ' ')
	{
		$limit = intval($limit);

		$utf8Modifier = (strtolower(self::getTempCharset()) == 'utf-8') ? 'u' : '';

		if ($limit > 0 AND !empty($text)) {
			return preg_replace('
				#((?>[^\s&/<>"\\-\[\]]|&[\#a-z0-9]{1,7};){' . $limit . '})(?=[^\s&/<>"\\-\[\]]|&[\#a-z0-9]{1,7};)#i' . $utf8Modifier,
				'$0' . $wraptext,
				$text
			);
		}
		else
		{
			return $text;
		}
	}

	/**
	 * Case-insensitive version of strpos(). Defined if it does not exist.
	 *
	 * @param	string		Text to search for
	 * @param	string		Text to search in
	 * @param	int			Position to start search at
	 *
	 * @param	int|false	Position of text if found, false otherwise
	 */
	public static function stripos($haystack, $needle, $offset = 0)
	{
		if (!function_exists('stripos')) {
			$foundstring = stristr(substr($haystack, $offset), $needle);
			return $foundstring === false ? false : strlen($haystack) - strlen($foundstring);
		}
		else
		{
			return stripos($haystack, $needle, $offset);
		}
	}

	/**
 * Strips NCRs from a string.
 *
 * @param	string	The string to strip from
 * @return	string	The result
 */
	public static function stripNcrs($str)
	{
		return preg_replace('/(&#[0-9]+;)/', '', $str);
	}

	/** Gets the current charset
	*
	**/
	public static function getCharset()
	{
		static $lang_charset = '';
		if (!empty($lang_charset))
		{
			return $lang_charset;
		}
		$lang_charset = vB_Template_Runtime::fetchStyleVar('charset');

		if (!empty($lang_charset))
		{
			return $lang_charset;
		}

		$currentSession = vB::getCurrentSession();
		$userinfo = is_object($currentSession) ? $currentSession->fetch_userinfo() : array();
		$lang_charset = (!empty($userinfo['lang_charset'])) ? $userinfo['lang_charset'] : 'utf-8';

		return $lang_charset;
	}

	/**
	 * Converts a string from one character encoding to another.
	 * If the target encoding is not specified then it will be resolved from the current
	 * language settings.
	 *
	 * @param	string|array	The string/array to convert
	 * @param	string	The source encoding
	 * @return	string	The target encoding
	 */
	public static function toCharset($in, $in_encoding, $target_encoding = false)
	{
		if (!$target_encoding) {
			if (!($target_encoding = self::getCharset())) {
				return $in;
			}
		}

		if (is_object($in))
		{
			foreach ($in as $key => $val)
			{
				$in->$key = self::toCharset($val, $in_encoding, $target_encoding);
			}

			return $in;
		}
		else if (is_array($in)) {
			foreach ($in as $key => $val)
			{
				$in["$key"] = self::toCharset($val, $in_encoding, $target_encoding);
			}

			return $in;
		}
		else if (is_string($in))
		{
			// ISO-8859-1 or other Western charset doesn't support Asian ones so that we need to NCR them
			// Iconv will ignore them
			if (preg_match("/^[ISO|Windows|IBM|MAC|CP]/i", $target_encoding)) {
				$in = self::ncrencode($in, true, true);
			}

			// Try iconv
			if (function_exists('iconv')) {
				// Try iconv
				$out = @iconv($in_encoding, $target_encoding . '//IGNORE', $in);
				return $out;
			}

			// Try mbstring
			if (function_exists('mb_convert_encoding')) {
				return @mb_convert_encoding($in, $target_encoding, $in_encoding);
			}
		}
		else
		{
			// if it's not a string, array or object, don't modify it
			return $in;
		}

	}

	/**
	 * Converts a string to utf8
	 *
	 * @param	string	The variable to clean
	 * @param	string	The source charset
	 * @param	bool	Whether to strip invalid utf8 if we couldn't convert
	 * @return	string	The reencoded string
	 */
	public static function toUtf8($in, $charset = false, $strip = true)
	{
		if ('' === $in OR false === $in OR is_null($in))
		{
			return $in;
		}

		// Fallback to UTF-8
		if (!$charset)
		{
			$charset = 'UTF-8';
		}

		// Try iconv
		if (function_exists('iconv'))
		{
			$out = @iconv($charset, 'UTF-8//IGNORE', $in);
			return $out;
		}

		// Try mbstring
		if (function_exists('mb_convert_encoding'))
		{
			return @mb_convert_encoding($in, 'UTF-8', $charset);
		}

		if (!$strip)
		{
			return $in;
		}

		// Strip non valid UTF-8
		// TODO: Do we really want to do this?
		return self::stripInvalidUtf8($in);
	}

	public static function stripInvalidUtf8($in)
	{
		$utf8 = '#([\x09\x0A\x0D\x20-\x7E]' . # ASCII
				'|[\xC2-\xDF][\x80-\xBF]' . # non-overlong 2-byte
				'|\xE0[\xA0-\xBF][\x80-\xBF]' . # excluding overlongs
				'|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}' . # straight 3-byte
				'|\xED[\x80-\x9F][\x80-\xBF]' . # excluding surrogates
				'|\xF0[\x90-\xBF][\x80-\xBF]{2}' . # planes 1-3
				'|[\xF1-\xF3][\x80-\xBF]{3}' . # planes 4-15
				'|\xF4[\x80-\x8F][\x80-\xBF]{2})#S'; # plane 16

		$out = '';
		$matches = array();
		while (preg_match($utf8, $in, $matches))
		{
			$out .= $matches[0];
			$in = substr($in, strlen($matches[0]));
		}

		return $out;
	}

	/**
	* Attempts to do a character-based strlen on data that might contain HTML entities.
	* By default, it only converts numeric entities but can optional convert &quot;,
	* &lt;, etc. Uses a multi-byte aware function to do the counting if available.
	*
	* @param	string	String to be measured
	* @param	boolean	If true, run unhtmlspecialchars on string to count &quot; as one, etc.
	*
	* @return	integer	Length of string
	*/
	public static function vbStrlen($string, $unHtmlSpecialChars = false)
	{
		$string = preg_replace('#&\#([0-9]+);#', '_', $string);
		if ($unHtmlSpecialChars)
		{
			// don't try to translate unicode entities ever, as we want them to count as 1 (above)
			$string = vB_String::unHtmlSpecialChars($string, false);
		}

		//for some reason the original version of this -- without the extra parans around the
		//second half of the and caused an ajax failure in the installer. I have no explanation
		//and it only appears to happen in really specific environments, but since adding the
		//extra parens seems to work and is harmless I'm going with it.
		if (function_exists('mb_strlen') AND ($length = @mb_strlen($string, self::getCharSet())))
		{
			return $length;
		}
		else
		{
			return strlen($string);
		}
	}

	/**
	 *	Get a valid UrlIdent value from a title removing special chars.
	 *
	 * @param	String	The title text to be converted.
	 * @param	String	Encoding of the string. (Optional)
	 *
	 * @return	String	A valid urlident encoded in UTF-8
	 */
	public static function getUrlIdent($title, $encoding = false)
	{
		if (empty($encoding))
		{
			$encoding = self::getCharset();
		}
		if (!empty($title))
		{
			if (strtolower($encoding) != 'utf-8')
			{
				// Convert to utf-8 after making it lower case because the lower case conversion depends on the current charset.
				$title = self::toUtf8($title, $encoding);
			}
			// titles are stored as html. remove html tags, then convert entities to their actual characters
			$title = self::unHtmlSpecialChars(self::stripTags(self::vBStrToLower($title, 'utf-8')), true);
			//these characters can cause problems in a URL (browsers, email clients, instant messengers, etc.)
			$invalidchars = '!@#$%^&*()+?/:;"\'\\,.<>= _';
			$title = strtr($title, $invalidchars, str_repeat('-', strlen($invalidchars)));
			//collapse multiple consecutive dashes
			$title = preg_replace('/-{2,}/', '-', trim($title, '-'));
			return $title;
		}

		return $title;
	}

	/**
	 * Converts A-Z to a-z, doesn't change any other characters
	 *
	 * @param	string	String to convert to lowercase
	 * @param	string	Encoding of the string (Optional)
	 *
	 * @return	string	Lowercase string
	 */
	public static function vBStrToLower($string, $encoding = false)
	{
		if (empty($encoding))
		{
			$encoding = self::getCharSet();
		}

		if (function_exists('mb_strtolower') AND $newstring = mb_strtolower($string, $encoding))
		{
			return $newstring;
		}
		else
		{
			return strtr($string,
				'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
				'abcdefghijklmnopqrstuvwxyz'
			);
		}
	}

	/**
	 * Returns a string where HTML entities have been converted back to their original characters
	 *
	 * @param	string	String to be parsed
	 * @param	boolean	Convert unicode characters back from HTML entities?
	 *
	 * @return	string
	 */
	public static function unHtmlSpecialChars($text, $doUniCode = false)
	{
		if ($doUniCode)
		{
			$text = preg_replace('/&#([0-9]+);/esiU', "vB_String::convertIntToUtf8('\\1')", $text);
		}

		return str_replace(array('&lt;', '&gt;', '&quot;', '&amp;'), array('<', '>', '"', '&'), $text);
	}

	/**
	 * Converts an integer into a UTF-8 character string
	 *
	 * @param	integer	Integer to be converted
	 *
	 * @return	string
	 */
	public static function convertIntToUtf8($intval)
	{
		$intval = intval($intval);
		switch ($intval)
		{
			// 1 byte, 7 bits
			case 0:
				return chr(0);
			case ($intval & 0x7F):
				return chr($intval);
			// 2 bytes, 11 bits
			case ($intval & 0x7FF):
				return chr(0xC0 | (($intval >> 6) & 0x1F)) .
					chr(0x80 | ($intval & 0x3F));
			// 3 bytes, 16 bits
			case ($intval & 0xFFFF):
				return chr(0xE0 | (($intval >> 12) & 0x0F)) .
					chr(0x80 | (($intval >> 6) & 0x3F)) .
					chr(0x80 | ($intval & 0x3F));
			// 4 bytes, 21 bits
			case ($intval & 0x1FFFFF):
				return chr(0xF0 | ($intval >> 18)) .
					chr(0x80 | (($intval >> 12) & 0x3F)) .
					chr(0x80 | (($intval >> 6) & 0x3F)) .
					chr(0x80 | ($intval & 0x3F));
		}

		return '';
	}


	/**
	 * Chops off a string at a specific length, counting entities as once character
	 * and using multibyte-safe functions if available. Copied from presentation method of the same name.
	 *
	 * @param	string	String to chop
	 * @param	integer	Number of characters to chop at
	 *
	 * @return	string	Chopped string
	 */
	public static function vbChop($string, $length)
	{
		$length = intval($length);
		if ($length <= 0) {
			return $string;
		}

		// Pretruncate the string to something shorter, so we don't run into memory problems with
		// very very very long strings at the regular expression down below.
		//
		// UTF-32 allows 0x7FFFFFFF code space, meaning possibility of code point: &#2147483647;
		// If we assume entire string we want to keep is in this butchered form, we need to keep
		// 13 bytes per character we want to output. Strings actually encoded in UTF-32 takes 4
		// bytes per character, so 13 is large enough to cover that without problem, too.
		//
		// ((Unlike the regex below, no memory problems here with very very very long comments.))
		$pretruncate = 13 * $length;
		$string = substr($string, 0, $pretruncate);

		if (preg_match_all('/&(#[0-9]+|lt|gt|quot|amp);/', $string, $matches, PREG_OFFSET_CAPTURE)) {
			// find all entities because we need to count them as 1 character
			foreach ($matches[0] AS $match)
			{
				$entity_length = strlen($match[0]);
				$offset = $match[1];

				// < since length starts at 1 but offset starts at 0
				if ($offset < $length) {
					// this entity happens in the chop area, so extend the length to include this
					// -1 since the entity should still count as 1 character
					$length += strlen($match[0]) - 1;
				}
				else
				{
					break;
				}
			}
		}

		$substr = '';
		if (function_exists('mb_substr'))
		{
			return @mb_substr($string, 0, $length);
		}

		return substr($string, 0, $length);
	}

	/**
	* Converts a UTF-8 string into unicode NCR equivelants.
	*
	* @param	string	String to encode
	* @param	bool	Only ncrencode unicode bytes
	* @param	bool	If true and $skip_ascii is true, it will skip windows-1252 extended chars
	* @return	string	Encoded string
	*/
	public static function ncrEncode($str, $skip_ascii = false, $skip_win = false)
	{
		if (!$str)
		{
			return $str;
		}

		if (function_exists('mb_encode_numericentity'))
		{
			if ($skip_ascii)
			{
				if ($skip_win)
				{
					$start = 0xFE;
				}
				else
				{
					$start = 0x80;
				}
			}
			else
			{
				$start = 0x0;
			}
			return mb_encode_numericentity($str, array($start, 0xffff, 0, 0xffff), 'UTF-8');
		}

		if (is_pcre_unicode())
		{
			return preg_replace_callback(
				'#\X#u',
				create_function('$matches', 'return ncrencode_matches($matches, ' . (int)$skip_ascii . ', ' . (int)$skip_win . ');'),
				$str
			);
		}

		return $str;
	}

	/**
	 * Translates some special characters to their latin form
	 * @param string $str
	 * @return string
	 */
	public static function latinise($str)
	{
		return strtr($str, self::$convertionMap);
	}

	/**
	 * Strip HTML Tags, HTML comments, and PHP Tags from a string
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	public static function stripTags($str)
	{
		return strip_tags($str);
	}

	/**
	 * UTF-8 Safe Parse_url
	 * http://us3.php.net/manual/en/function.parse-url.php
	 *
	 * @param	string	$url
	 * @param	int		$component
	 *
	 * @return	mixed
	 */
	public static function parseUrl($url, $component = -1)
	{
		$removeScheme = false;

		if (strpos($url, '//') === 0)
		{
			// Schemeless URLS like '//www.vbulletin.com/actualpath' are treated as being a huge path
			// rather than having a domain. This is fixed in PHP 5.4.7+, but let's make it consistent
			// since we're supporting PHP 5.3+.
			$removeScheme = true;
			$url = 'http:' . $url;
		}

		$return = parse_url(
			self::encodeUtf8Url($url),
			$component
		);

		if ($removeScheme)
		{
			if (is_array($return))
			{
				unset($return['scheme']);
			}
			else if ($component == PHP_URL_SCHEME AND $return !== false)
			{
				$return = null;
			}
		}

		if (is_array($return))
		{
			foreach ($return as $key => $value)
			{
				$return[$key] = urldecode($value);
			}

			if (isset($return['port']))
			{
				// Port is supposed to return an integer. The rest are strings.
				$return['port'] = intval($return['port']);
			}
		}
		else if ($component != PHP_URL_PORT AND !empty($return))
		{
			// We're checking if $return is empty because it could be
			// NULL (the component specified wasn't there)
			// or false (parse_url failed)
			$return = urldecode($return);
		}

		return $return;
	}

	/**
	 * Encode a UTF-8 Encoded URL and urlencode it while leaving control characters in tact.
	 * (It can also work with single byte encodings, but its purpose is to supply UTF-8 urls on non UTF-8 forums.)
	 *
	 * @param	string	url
	 *
	 * @return	string
	 */
	public static function encodeUtf8Url($url)
	{

		static $controlCharsArr = array();

		if (empty($controlCharsArr))
		{
			// special url control characters needed for parsing urls
			$controlChars = '!@#$%^&*()+?/:;"\'\\,.<>=';
			$controlCharsCount = strlen($controlChars);

			for ($char = 0; $char < $controlCharsCount; $char++)
			{
				$controlCharsArr[urlencode($controlChars[$char])] = $controlChars[$char];
			}
		}

		return str_replace(array_keys($controlCharsArr), array_values($controlCharsArr), urlencode($url));
	}

	/*
	 * Minify CSS text
	 *
	 * @param	string
	 *
	 * @return	string
	 */
	public static function getCssMinifiedText($text)
	{
		$search1 = array(
			'#/\*.*?\*/#s',
			'#(\t|\r|\n)#',
			'#/[^}{]+{\s?}#',
			'#\s+#',
			'#\s*{\s*#',
			'#\s*}\s*#',
		);
		$replace1 = array('', '', '', ' ', '{', '}');
		$text = preg_replace($search1, $replace1, $text);

		$search2 = array(';}', ', ', '; ', ': ');
		$replace2 = array('}', ',', ';', ':');
		$text = str_replace($search2, $replace2, $text);

		$text = preg_replace('#\s+#', ' ', $text);

		return $text;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision: 28674 $
|| ####################################################################
\*======================================================================*/