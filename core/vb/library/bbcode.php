<?php
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
 * Stack based BB code parser.
 *
 * DO NOT USE THIS CLASS UNLESS YOU GET PRIOR APPROVAL
 *
 * @package 		vBulletin
 * @version		$Revision: 70155 $
 * @date 		$Date: 2013-01-07 16:44:48 -0800 (Mon, 07 Jan 2013) $
 *
 */
class vB_Library_BbCode
{
	/**#@+
	 * These make up the bit field to control what "special" BB codes are found in the text.
	 */
	const BBCODE_HAS_IMG		= 1;
	const BBCODE_HAS_ATTACH		= 2;
	const BBCODE_HAS_SIGPIC		= 4;
	const BBCODE_HAS_RELPATH	= 8;
	/**#@-*/

	/**
	 * BB code parser's start state. Looking for the next tag to start.
	 */
	const PARSER_START		= 1;

	/**
	 * BB code parser's "this range is just text" state.
	 * Requires $internal_data to be set appropriately.
	 */
	const PARSER_TEXT		= 2;

	/**
	 * Tag has been opened. Now parsing for option and closing ].
	 */
	const PARSER_TAG_OPENED	= 3;

	/**
	 * Forum config baseurl, passed in from presentation
	 *
	 * @var string
	 */
	protected static $baseurl = '';

	protected static $baseurl_core = '';

	/**
	 *
	 * @var vB_Library_BbCode
	 */
	protected static $initialized = false;

	/**
	 * A list of default tags to be parsed.
	 * Takes a specific format. See function that defines the array passed into the c'tor.
	 *
	 * @var	array
	 */
	protected static $defaultTags = array();

	/**
	 * A list of default options for most types.
	 * Use <function> to retrieve options based on content id
	 *
	 * @var	array
	 */
	protected static $defaultOptions = array();

	/**
	 * A list of custom tags to be parsed.
	 *
	 * @var	array
	 */
	protected static $customTags = array();

	/**
	 * List of smilies
	 * @var array
	 */
	protected static $smilies = array();

	/**
	 * Censorship info
	 * @var array
	 */
	protected static $censorship = array();

	protected static $sessionUrl;
	protected static $blankAsciiStrip = '';
	protected static $wordWrap;
	protected static $bbUrl;
	protected static $viewAttachedImages;
	protected static $urlNoFollow;
	protected static $urlNoFollowWhiteList;
	protected static $vBHttpHost;
	protected static $useFileAvatar;
	protected static $sigpicUrl;

	/**
	 * A list of tags to be parsed.
	 * Takes a specific format. See function that defines the array passed into the c'tor.
	 *
	 * @var	array
	 */
	protected $tag_list = array();

	/**
	 * Used alongside the stack. Holds a reference to the node on the stack that is
	 * currently being processed. Only applicable in callback functions.
	 */
	protected $currentTag = null;

	/**
	 * Whether this parser is parsing for printable output
	 *
	 * @var	bool
	 */
	protected $printable = false;

	/**
	 * Holds various options such what type of things to parse and cachability.
	 *
	 * @var	array
	 */
	protected $options = array();

	/**
	 * Holds the cached post if caching was enabled
	 *
	 * @var	array	keys: text (string), has_images (int)
	 */
	protected $cached = array();

	// TODO: refactor this property
	/**
	 * Reference to attachment information pertaining to this post
	 * Uses filedataid as key
	 *
	 * @var	array
	 */
	protected $attachments = null;

	// This is used for translating old attachment ids
	protected $oldAttachments = array();

	/**
	 * Whether this parser unsets attachment info in $this->attachments when an inline attachment is found
	 *
	 * @var	bool
	 */
	protected $unsetattach = true;

	// TODO: remove $this->forumid
	/**
	 * Id of the forum the source string is in for permissions
	 *
	 * @var integer
	 */
	protected $forumid = 0;

	/**
	 * Id of the outer container, if applicable
	 *
	 * @var mixed
	 */
	protected $containerid = 0;

	/**
	 * Local cache of smilies for this parser. This is per object to allow WYSIWYG and
	 * non-WYSIWYG versions on the same page.
	 *
	 * @var array
	 */
	protected $smilieCache = array();

	/**
	 * If we need to parse using specific user information (such as in a sig),
	 * set that info in this member. This should include userid, custom image revision info,
	 * and the user's permissions, at the least.
	 *
	 * @var	array
	 */
	protected $parseUserinfo = array();

	/**
	 * Global override for space stripping
	 *
	 * @var	bool
	 */
	var $stripSpaceAfter = true;

	/** Template for generating quote links. We need to override for cms comments" **/
	protected $quotePrintableTemplate = 'bbcode_quote_printable';

	/** Template for generating quote links. We need to override for cms comments" **/
	protected $quoteTemplate =  'bbcode_quote';

	/**Additional parameter(s) for the quote template. We need for cms comments **/
	protected $quoteVars = false;

	/**
	 * Object to provide the implementation of the table helper to use.
	 * See setTableHelper and getTableHelper.
	 *
	 * @var	vB_Library_BbCode_Table
	 */
	protected $tableHelper = null;

	/**
	 *	Display full size image attachment if an image is [attach] using without =config, otherwise display a thumbnail
	 *
	 */
	protected $displayimage = false;

	/**
	 * Constructor. Sets up the tag list.
	 *
	 * @param	bool		Whether to append customer user tags to the tag list
	 */
	public function __construct($appendCustomTags = true)
	{
		if (!self::$initialized)
		{
			$response = vB_Api::instanceInternal('bbcode')->initInfo();
			self::$defaultTags = $response['defaultTags'];
			self::$customTags = $response['customTags'];
			self::$defaultOptions = $response['defaultOptions'];
			self::$smilies = $response['smilies'];
			self::$censorship = $response['censorship'];
			self::$sessionUrl = $response['sessionUrl'];
			self::$blankAsciiStrip = $response['blankAsciiStrip'];
			self::$wordWrap = $response['wordWrap'];
			self::$bbUrl = $response['bbUrl'];
			self::$viewAttachedImages = $response['viewAttachedImages'];
			self::$urlNoFollow = $response['urlNoFollow'];
			self::$urlNoFollowWhiteList = $response['urlNoFollowWhiteList'];
			self::$vBHttpHost = $response['vBHttpHost'];
			self::$useFileAvatar = $response['useFileAvatar'];
			self::$sigpicUrl = $response['sigpicUrl'];

			$options = vB::getDatastore()->getValue('options');
			self::$baseurl = preg_replace("#/?core/?$#", '', $options['bburl']);
			self::$baseurl_core = $options['bburl'];

			self::$initialized = true;
		}

		$this->tag_list = self::$defaultTags;
		if ($appendCustomTags)
		{
			$this->tag_list = vB_Array::arrayReplaceRecursive($this->tag_list, self::$customTags);
		}

		// Legacy Hook 'bbcode_create' Removed //
	}

	/**
	 * Adds attachments to the class property using filedataid as key.
	 * If the key is already set, it overwrites the value
	 * @param type $attachments
	 */
	public function setAttachments($attachments)
	{
		if (is_array($attachments) AND !empty($attachments))
		{
			foreach($attachments AS $attachment)
			{
				$this->attachments[$attachment['filedataid']] = $attachment;
			}
		}
	}

	public function getAttachments()
	{
		return $this->attachments;
	}

	/**
	 * Sets the user the BB code as parsed as. As of 3.7, this function should
	 * only be called for parsing signatures (for sigpics and permissions).
	 *
	 * @param	array	Array of user info to parse as
	 * @param	array	Array of user's permissions (may come through $userinfo already)
	 */
	public function setParseUserinfo($userId)
	{
		$this->parseUserinfo = vB_Api::instanceInternal('user')->fetchUserInfo($userId, array('signpic'));
	}

	/**
	 * Sets whether this parser is parsing for printable output
	 *
	 * @var	bool
	 */
	public function setPrintable($bool)
	{
		$this->printable = $bool;
	}

	/**
	 * Collect parser options and misc data and fully parse the string into an HTML version
	 *
	 * @param	string	Unparsed text
	 * @param	int|str	ID number of the forum whose parsing options should be used or a "special" string
	 * @param	bool	Whether to allow smilies in this post (if the option is allowed)
	 * @param	bool	Whether to parse the text as an image count check
	 * @param	string	Preparsed text ([img] tags should not be parsed)
	 * @param	int		Whether the preparsed text has images
	 * @param	bool	Whether the parsed post is cachable
	 * @param	string	Switch for dealing with nl2br
	 *
	 * @return	string	Parsed text
	 */
	public function parse($text, $forumid = 0, $allowsmilie = true, $isimgcheck = false, $parsedtext = '', $parsedhasimages = 3, $cachable = false, $htmlstate = null)
	{
		$this->forumid = $forumid;

		$donl2br = true;

		if (empty($forumid))
		{
			$forumid = 'nonforum';
		}

		switch($forumid)
		{
		case 'calendar':
			case 'privatemessage':
				case 'usernote':
					case 'visitormessage':
						case 'groupmessage':
							case 'picturecomment':
								case 'socialmessage':
									$dohtml = $this->defaultOptions[$forumid]['dohtml'];
									$dobbcode = $this->defaultOptions[$forumid]['dobbcode'];
									$dobbimagecode = $this->defaultOptions[$forumid]['dobbimagecode'];
									$dosmilies = $this->defaultOptions[$forumid]['dosmilies'];
									break;

									// parse signature
								case 'signature':
									if (!empty($this->parseUserinfo['permissions']))
									{
										$dohtml = ($this->parseUserinfo['permissions']['signaturepermissions'] & $this->registry->bf_ugp_signaturepermissions['allowhtml']);
										$dobbcode = ($this->parseUserinfo['permissions']['signaturepermissions'] & $this->registry->bf_ugp_signaturepermissions['canbbcode']);
										$dobbimagecode = ($this->parseUserinfo['permissions']['signaturepermissions'] & $this->registry->bf_ugp_signaturepermissions['allowimg']);
										$dosmilies = ($this->parseUserinfo['permissions']['signaturepermissions'] & $this->registry->bf_ugp_signaturepermissions['allowsmilies']);
										break;
									}
									// else fall through to nonforum

									// parse non-forum item
								case 'nonforum':
									$dohtml = $this->defaultOptions['nonforum']['dohtml'];
									$dobbcode = $this->defaultOptions['nonforum']['dobbcode'];
									$dobbimagecode = $this->defaultOptions['nonforum']['dobbimagecode'];
									$dosmilies = $this->defaultOptions['nonforum']['dosmilies'];
									break;

									// parse announcement
								case 'announcement':
									global $post;
									$dohtml = ($post['announcementoptions'] & $this->registry->bf_misc_announcementoptions['allowhtml']);
									if ($dohtml)
									{
										$donl2br = false;
									}
									$dobbcode = ($post['announcementoptions'] & $this->registry->bf_misc_announcementoptions['allowbbcode']);
									$dobbimagecode = ($post['announcementoptions'] & $this->registry->bf_misc_announcementoptions['allowbbcode']);
									$dosmilies = $allowsmilie;
									break;

									// parse forum item
								default:
									if (intval($forumid))
									{
										$forum = fetch_foruminfo($forumid);
										$dohtml = $forum['allowhtml'];
										$dobbimagecode = $forum['allowimages'];
										$dosmilies = $forum['allowsmilies'];
										$dobbcode = $forum['allowbbcode'];
									}
									// else they'll basically just default to false -- saves a query in certain circumstances
									break;
		}

		if (!$allowsmilie)
		{
			$dosmilies = false;
		}

		// Legacy Hook 'bbcode_parse_start' Removed //

		if (!empty($parsedtext))
		{
			if ($parsedhasimages)
			{
				return $this->handle_bbcode_img($parsedtext, $dobbimagecode, $parsedhasimages);
			}
			else
			{
				return $parsedtext;
			}
		}
		else
		{
			return $this->doParse($text, $dohtml, $dosmilies, $dobbcode, $dobbimagecode, $donl2br, $cachable, $htmlstate);
		}
	}

	/**
	 * Parse the string with the selected options
	 *
	 * @param	string	Unparsed text
	 * @param	bool	Whether to allow HTML (true) or not (false)
	 * @param	bool	Whether to parse smilies or not
	 * @param	bool	Whether to parse BB code
	 * @param	bool	Whether to parse the [img] BB code (independent of $do_bbcode)
	 * @param	bool	Whether to automatically replace new lines with HTML line breaks
	 * @param	bool	Whether the post text is cachable
	 * @param	string	Switch for dealing with nl2br
	 *	@param	boolean	do minimal required actions to parse bbcode
	 *
	 * @return	string	Parsed text
	 */
	public function doParse($text, $do_html = false, $do_smilies = true, $do_bbcode = true , $do_imgcode = true, $do_nl2br = true, $cachable = false, $htmlstate = null, $minimal = false)
	{
		if ($htmlstate)
		{
			switch ($htmlstate)
			{
			case 'on':
				$do_nl2br = false;
				break;
			case 'off':
				$do_html = false;
				break;
			case 'on_nl2br':
				$do_nl2br = true;
				break;
			}
		}

		$this->options = array(
			'do_html'    => $do_html,
			'do_smilies' => $do_smilies,
			'do_bbcode'  => $do_bbcode,
			'do_imgcode' => $do_imgcode,
			'do_nl2br'   => $do_nl2br,
			'cachable'   => $cachable
		);
		$this->cached = array('text' => '', 'has_images' => 0);

		$fulltext = $text;

		// ********************* REMOVE HTML CODES ***************************
		if (!$do_html)
		{
			$text = vB_String::htmlSpecialCharsUni($text);
		}

		if (!$minimal)
		{
			$text = $this->parseWhitespaceNewlines($text, $do_nl2br);
		}

		// ********************* PARSE BBCODE TAGS ***************************
		if ($do_bbcode)
		{
			$text = $this->parseBbcode($text, $do_smilies, $do_imgcode, $do_html);
		}
		else if ($do_smilies)
		{
			$text = $this->parseSmilies($text, $do_html);
		}

		if (!$minimal)
		{
			// parse out nasty active scripting codes
			static $global_find = array('/(javascript):/si', '/(about):/si', '/(vbscript):/si', '/&(?![a-z0-9#]+;)/si');
			static $global_replace = array('\\1<b></b>:', '\\1<b></b>:', '\\1<b></b>:', '&amp;');
			$text = preg_replace($global_find, $global_replace, $text);

			// run the censor
			$text = $this->fetchCensoredText($text);
			$has_img_tag = ($do_bbcode ? max(array($this->containsBbcodeImgTags($fulltext), $this->containsBbcodeImgTags($text))) : 0);
		}

		// Legacy Hook 'bbcode_parse_complete_precache' Removed //

		// save the cached post
		if ($this->options['cachable'])
		{
			$this->cached['text'] = $text;
			$this->cached['has_images'] = $has_img_tag;
		}

		// do [img] tags if the item contains images
		if(($do_bbcode OR $do_imgcode) AND $has_img_tag)
		{
			$text = $this->handle_bbcode_img($text, $do_imgcode, $has_img_tag, $fulltext);
		}

		if (!defined('VB_API') OR VB_API === false)
		{
			$text = $this->append_noninline_attachments($text, $this->attachments, $do_imgcode);
		}

		// Legacy Hook 'bbcode_parse_complete' Removed //
		return $text;
	}

	/**
	 * Replaces any instances of words censored in self::$censorship['words'] with self::$censorship['char']
	 *
	 * @param	string	Text to be censored
	 *
	 * @return	string
	 */
	public function fetchCensoredText($text)
	{
		if (!$text)
		{
			// return $text rather than nothing, since this could be '' or 0
			return $text;
		}

		if (!empty(self::$censorship['words']))
		{
			foreach (self::$censorship['words'] AS $censorword)
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
						'#(?<=[' . $nonword_chars . ']|^)' . $censorword . '(?=[' . $nonword_chars . ']|$)#si', str_repeat(self::$censorship['char'], vB_String::vbStrlen($censorword)), $text
					);
				}
				else
				{
					$text = preg_replace("#$censorword#si", str_repeat(self::$censorship['char'], vB_String::vbStrlen($censorword)), $text);
				}
			}
		}

		// strip any admin-specified blank ascii chars
		$text = vB_String::stripBlankAscii($text, self::$censorship['char'], self::$blankAsciiStrip);

		return $text;
	}

	/**
	 * This is copied from the blog bbcode parser. We either have a specific
	 * amount of text, or [PRBREAK][/PRBREAK].
	 *
	 * @param	array	Fixed tokens
	 * @param	integer	Length of the text before parsing (optional)
	 *
	 * @return	array	Tokens, chopped to the right length.
	 */
	public function get_preview($pagetext, $initial_length = 0, $do_html = false, $do_nl2br = true, $htmlstate = null)
	{
		if ($htmlstate)
		{
			switch ($htmlstate)
			{
			case 'on':
				$do_nl2br = false;
				break;
			case 'off':
				$do_html = false;
				break;
			case 'on_nl2br':
				$do_nl2br = true;
				break;
			}
		}

		$this->options = array(
			'do_html'    => $do_html,
			'do_smilies' => false,
			'do_bbcode'  => true,
			'do_imgcode' => false,
			'do_nl2br'   => $do_nl2br,
			'cachable'   => true
		);

		if (!$do_html)
		{
			$pagetext = vB_String::htmlSpecialCharsUni($pagetext);
		}
		$pagetext = $this->parseWhitespaceNewlines(trim(strip_quotes($pagetext)), $do_nl2br);
		$tokens = $this->fixTags($this->buildParseArray($pagetext));

		$counter = 0;
		$stack = array();
		$new = array();
		$over_threshold = false;

		if (strpos($pagetext, '[PRBREAK][/PRBREAK]'))
		{
			$this->snippet_length = strlen($pagetext);
		}
		else if (intval($initial_length))
		{
			$this->snippet_length = $initial_length;

		}
		else
		{
			$this->snippet_length = $this->default_previewlen;
		}

		$noparse = false;
		$video = false;
		$in_page = false;

		foreach ($tokens AS $tokenid => $token)
		{
			if (($token['name'] == 'noparse') AND $do_html)
			{
				//can't parse this. We don't know what's inside.
				$new[] = $token;
				$noparse = ! $noparse;

			}
			else if ($token['name'] == 'video')
			{
				$video = !$token['closing'];
				continue;

			}
			else if ($token['name'] == 'page')
			{
				$in_page = !$token['closing'];
				continue;

			}
			else if ($video OR $in_page)
			{
				continue;
			}
			// only count the length of text entries
			else if ($token['type'] == 'text')
			{

				if (!$noparse)
				{
					//If this has [ATTACH] or [IMG] or VIDEO then we nuke it.
					$pagetext =preg_replace('#\[ATTACH.*?\[/ATTACH\]#si', '', $token['data']);
					$pagetext = preg_replace('#\[IMG.*?\[/IMG\]#si', '', $pagetext);
					$pagetext = preg_replace('#\[video.*?\[/video\]#si', '', $pagetext);
					if ($pagetext == '')
					{
						continue;
					}
					$token['data'] = $pagetext;
				}
				$length = vB_String::vbStrlen($token['data']);

				// uninterruptable means that we will always show until this tag is closed
				$uninterruptable = (isset($stack[0]) AND isset($this->uninterruptable["$stack[0]"]));

				if ((($counter + $length) < $this->snippet_length )OR $uninterruptable OR $noparse)
				{
					// this entry doesn't push us over the threshold
					$new[] = $token;
					$counter += $length;
				}
				else
				{
					// a text entry that pushes us over the threshold
					$over_threshold = true;
					$last_char_pos = $this->snippet_length - $counter - 1; // this is the threshold char; -1 means look for a space at it
					if ($last_char_pos < 0)
					{
						$last_char_pos = 0;
					}

					if (preg_match('#\s#s', $token['data'], $match, PREG_OFFSET_CAPTURE, $last_char_pos))
					{
						$token['data'] = substr($token['data'], 0, $match[0][1]); // chop to offset of whitespace
						if (substr($token['data'], -3) == '<br')
						{
							// we cut off a <br /> code, so just take this out
							$token['data'] = substr($token['data'], 0, -3);
						}

						$new[] = $token;
					}
					else
					{
						$new[] = $token;
					}

					break;
				}
			}
			else
			{
				// not a text entry
				if ($token['type'] == 'tag')
				{
					//If we have a prbreak we are done.
					if (($token['name'] == 'prbreak') AND isset($tokens[intval($tokenid) + 1])
						AND ($tokens[intval($tokenid) + 1]['name'] == 'prbreak')
						AND ($tokens[intval($tokenid) + 1]['closing']))
					{
						$over_threshold == true;
						break;
					}
					// build a stack of open tags
					if ($token['closing'] == true)
					{
						// by now, we know the stack is sane, so just remove the first entry
						array_shift($stack);
					}
					else
					{
						array_unshift($stack, $token['name']);
					}
				}

				$new[] = $token;
			}
		}
		// since we may have cut the text, close any tags that we left open
		foreach ($stack AS $tag_name)
		{
			$new[] = array('type' => 'tag', 'name' => $tag_name, 'closing' => true);
		}

		$this->createdsnippet = (sizeof($new) != sizeof($tokens) OR $over_threshold); // we did something, so we made a snippet

		$result = $this->parseArray($new, true, true, $do_html);
		return $result;
	}

	/**
	 * Word wraps the text if enabled.
	 *
	 * @param	string	Text to wrap
	 *
	 * @return	string	Wrapped text
	 */
	protected function doWordWrap($text)
	{
		if (self::$wordWrap != 0)
		{
			$text = vB_String::fetchWordWrappedString($text, self::$wordWrap, '  ');
		}
		return $text;
	}

	/**
	 * Parses smilie codes into their appropriate HTML image versions
	 *
	 * @param	string	Text with smilie codes
	 * @param	bool	Whether HTML is allowed
	 *
	 * @return	string	Text with HTML images in place of smilies
	 */
	protected function parseSmilies($text, $do_html = false)
	{
		static $regex_cache;

		// this class property is used just for the callback function below
		$this->local_smilies =& $this->cacheSmilies($do_html);

		$cache_key = ($do_html ? 'html' : 'nohtml');

		if (!isset($regex_cache["$cache_key"]))
		{
			$regex_cache["$cache_key"] = array();
			$quoted = array();

			foreach ($this->local_smilies AS $find => $replace)
			{
				$quoted[] = preg_quote($find, '/');
				if (sizeof($quoted) > 500)
				{
					$regex_cache["$cache_key"][] = '/(?<!&amp|&quot|&lt|&gt|&copy|&#[0-9]{1}|&#[0-9]{2}|&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5})(' . implode('|', $quoted) . ')/s';
					$quoted = array();
				}
			}

			if (sizeof($quoted) > 0)
			{
				$regex_cache["$cache_key"][] = '/(?<!&amp|&quot|&lt|&gt|&copy|&#[0-9]{1}|&#[0-9]{2}|&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5})(' . implode('|', $quoted) . ')/s';
			}
		}

		foreach ($regex_cache["$cache_key"] AS $regex)
		{
			$text = preg_replace_callback($regex, array(&$this, 'replaceSmilies'), $text);
		}

		return $text;
	}

	/**
	 * Callback function for replacing smilies.
	 *
	 * @ignore
	 */
	protected function replaceSmilies($matches)
	{
		return $this->local_smilies["$matches[0]"];
	}

	/**
	 * Caches the smilies in a form ready to be executed.
	 *
	 * @param	bool	Whether HTML parsing is enabled
	 *
	 * @return	array	Reference to smilie cache (key: find text; value: replace text)
	 */
	protected function &cacheSmilies($do_html)
	{
		$key = $do_html ? 'html' : 'no_html';
		if (isset($this->smilieCache["$key"]))
		{
			return $this->smilieCache["$key"];
		}

		$sc =& $this->smilieCache["$key"];
		$sc = array();

		foreach (self::$smilies AS $smilie)
		{
			if (!$do_html)
			{
				$find = vB_String::htmlSpecialCharsUni(trim($smilie['smilietext']));
			}
			else
			{
				$find = trim($smilie['smilietext']);
			}

			// if you change this HTML tag, make sure you change the smilie remover in code/php/html tag handlers!
			if ($this->isWysiwyg())
			{
				$replace = "<img src=\"{$smilie['smiliepath']}\" border=\"0\" alt=\"\" title=\"" . vB_String::htmlSpecialCharsUni($smilie['title']) . "\" smilieid=\"{$smilie['smilieid']}\" class=\"inlineimg\" />";
			}
			else
			{
				$smiliepath = preg_match('#^https?://#si', $smilie['smiliepath']) ? $smilie['smiliepath'] : $this->baseurl_core . '/' . $smilie['smiliepath'];
				$replace = "<img src=\"{$smiliepath}\" border=\"0\" alt=\"\" title=\"" . vB_String::htmlSpecialCharsUni($smilie['title']) . "\" smilieid=\"{$smilie['smilieid']}\" class=\"inlineimg\" />";
			}

			$sc["$find"] = $replace;
		}

		return $sc;
	}

	/**
	 * Parses out specific white space before or after cetain tags and does nl2br
	 *
	 * @param	string	Text to process
	 * @param	bool	Whether to translate newlines to <br /> tags
	 *
	 * @return	string	Processed text
	 */
	protected function parseWhitespaceNewlines($text, $do_nl2br = true)
	{
		// this replacement is equivalent to removing leading whitespace via this regex:
		// '#(? >(\r\n|\n|\r)?( )+)(\[(\*\]|/?list|indent))#si'
		// however, it's performance is much better! (because the tags occur less than the whitespace)
		foreach (array('[*]', '[list', '[/list', '[indent') AS $search_string)
		{
			$start_pos = 0;
			while (($tag_pos = vB_String::stripos($text, $search_string, $start_pos)) !== false)
			{
				$whitespace_pos = $tag_pos - 1;
				while ($whitespace_pos >= 0 AND $text{$whitespace_pos} == ' ')
				{
					--$whitespace_pos;
				}
				if ($whitespace_pos >= 1 AND substr($text, $whitespace_pos - 1, 2) == "\r\n")
				{
					$whitespace_pos -= 2;
				}
				else if ($whitespace_pos >= 0 AND ($text{$whitespace_pos} == "\r" OR $text{$whitespace_pos} == "\n"))
				{
					--$whitespace_pos;
				}

				$length = $tag_pos - $whitespace_pos - 1;
				if ($length > 0)
				{
					$text = substr_replace($text, '', $whitespace_pos + 1, $length);
				}

				$start_pos = $tag_pos + 1 - $length;
			}
		}
		$text = preg_replace('#(/list\]|/indent\])(?> *)(\r\n|\n|\r)?#si', '$1', $text);

		if ($do_nl2br)
		{
			$text = nl2br($text);
	}

	return $text;
	}

	/**
	 * Parse an input string with BB code to a final output string of HTML
	 *
	 * @param	string	Input Text (BB code)
	 * @param	bool	Whether to parse smilies
	 * @param	bool	Whether to parse img (for the video bbcodes)
	 * @param	bool	Whether to allow HTML (for smilies)
	 *
	 * @return	string	Ouput Text (HTML)
	 */
	protected function parseBbcode($input_text, $do_smilies, $do_imgcode, $do_html = false)
	{
		return $this->parseArray($this->fixTags($this->buildParseArray($input_text)), $do_smilies, $do_imgcode, $do_html);
	}


	/**
	 * Takes a raw string and builds an array of tokens for parsing.
	 *
	 * @param	string	Raw text input
	 *
	 * @return	array	List of tokens
	 */
	protected function buildParseArray($text)
	{
		$start_pos = 0;
		$strlen = strlen($text);
		$output = array();
		$state = self::PARSER_START;

		while ($start_pos < $strlen)
		{
			switch ($state)
			{
			case self::PARSER_START:
				$tag_open_pos = strpos($text, '[', $start_pos);
				if ($tag_open_pos === false)
				{
					$internal_data = array('start' => $start_pos, 'end' => $strlen);
					$state = self::PARSER_TEXT;
				}
				else if ($tag_open_pos != $start_pos)
				{
					$internal_data = array('start' => $start_pos, 'end' => $tag_open_pos);
					$state = self::PARSER_TEXT;
				}
				else
				{
					$start_pos = $tag_open_pos + 1;
					if ($start_pos >= $strlen)
					{
						$internal_data = array('start' => $tag_open_pos, 'end' => $strlen);
						$start_pos = $tag_open_pos;
						$state = self::PARSER_TEXT;
					}
					else
					{
						$state = self::PARSER_TAG_OPENED;
					}
				}
				break;

			case self::PARSER_TEXT:
				$end = end($output);
				if ($end['type'] == 'text')
				{
					// our last element was text too, so let's join them
					$key = key($output);
					$output["$key"]['data'] .= substr($text, $internal_data['start'], $internal_data['end'] - $internal_data['start']);
				}
				else
				{
					$output[] = array('type' => 'text', 'data' => substr($text, $internal_data['start'], $internal_data['end'] - $internal_data['start']));
				}

				$start_pos = $internal_data['end'];
				$state = self::PARSER_START;
				break;

			case self::PARSER_TAG_OPENED:
				$tag_close_pos = strpos($text, ']', $start_pos);
				if ($tag_close_pos === false)
				{
					$internal_data = array('start' => $start_pos - 1, 'end' => $start_pos);
					$state = self::PARSER_TEXT;
					break;
				}

				// check to see if this is a closing tag, since behavior changes
				$closing_tag = ($text{$start_pos} == '/');
				if ($closing_tag)
				{
					// we don't want the / to be saved
					++$start_pos;
				}

				// ok, we have a ], check for an option
				$tag_opt_start_pos = strpos($text, '=', $start_pos);
				if ($closing_tag OR $tag_opt_start_pos === false OR $tag_opt_start_pos > $tag_close_pos)
				{
					// no option, so the ] is the end of the tag
					// check to see if this tag name is valid
					$tag_name_orig = substr($text, $start_pos, $tag_close_pos - $start_pos);
					$tag_name = strtolower($tag_name_orig);

					// if this is a closing tag, we don't know whether we had an option
					$has_option = $closing_tag ? null : false;

					if ($this->isValidTag($tag_name, $has_option))
					{
						$output[] = array(
							'type' => 'tag',
							'name' => $tag_name,
							'name_orig' => $tag_name_orig,
							'option' => false,
							'closing' => $closing_tag
						);

						$start_pos = $tag_close_pos + 1;
						$state = self::PARSER_START;
					}
					else
					{
						// this is an invalid tag, so it's just text
						$internal_data = array('start' => $start_pos - 1 - ($closing_tag ? 1 : 0), 'end' => $start_pos);
						$state = self::PARSER_TEXT;
					}
				}
				else
				{
					// check to see if this tag name is valid
					$tag_name_orig = substr($text, $start_pos, $tag_opt_start_pos - $start_pos);
					$tag_name = strtolower($tag_name_orig);

					if (!$this->isValidTag($tag_name, true))
					{
						// this isn't a valid tag name, so just consider it text
						$internal_data = array('start' => $start_pos - 1, 'end' => $start_pos);
						$state = self::PARSER_TEXT;
						break;
					}

					// we have a = before a ], so we have an option
					$delimiter = $text{$tag_opt_start_pos + 1};
					if ($delimiter == '&' AND substr($text, $tag_opt_start_pos + 2, 5) == 'quot;')
					{
						$delimiter = '&quot;';
						$delim_len = 7;
					}
					else if ($delimiter != '"' AND $delimiter != "'")
					{
						$delimiter = '';
						$delim_len = 1;
					}
					else
					{
						$delim_len = 2;
					}

					if ($delimiter != '')
					{
						$close_delim = strpos($text, "$delimiter]", $tag_opt_start_pos + $delim_len);
						if ($close_delim === false)
						{
							// assume no delimiter, and the delimiter was actually a character
							$delimiter = '';
							$delim_len = 1;
						}
						else
						{
							$tag_close_pos = $close_delim;
						}
					}

					$tag_option = substr($text, $tag_opt_start_pos + $delim_len, $tag_close_pos - ($tag_opt_start_pos + $delim_len));
					if ($this->isValidOption($tag_name, $tag_option))
					{
						$output[] = array(
							'type' => 'tag',
							'name' => $tag_name,
							'name_orig' => $tag_name_orig,
							'option' => $tag_option,
							'delimiter' => $delimiter,
							'closing' => false
						);

						$start_pos = $tag_close_pos + $delim_len;
						$state = self::PARSER_START;
					}
					else
					{
						// this is an invalid option, so consider it just text
						$internal_data = array('start' => $start_pos - 1, 'end' => $start_pos);
						$state = self::PARSER_TEXT;
					}
				}
				break;
			}
		}
		return $output;
	}

	/**
	 * Traverses parse array and fixes nesting and mismatched tags.
	 *
	 * @param	array	Parsed data array, such as one from buildParseArray
	 *
	 * @return	array	Parse array with specific data fixed
	 */
	protected function fixTags($preparsed)
	{
		$output = array();
		$stack = array();
		$noparse = null;

		foreach ($preparsed AS $node_key => $node)
		{
			if ($node['type'] == 'text')
			{
				$output[] = $node;
			}
			else if ($node['closing'] == false)
			{
				// opening a tag
				if ($noparse !== null)
				{
					$output[] = array('type' => 'text', 'data' => '[' . $node['name_orig'] . ($node['option'] !== false ? "=$node[delimiter]$node[option]$node[delimiter]" : '') . ']');
					continue;
				}

				$output[] = $node;
				end($output);

				$node['added_list'] = array();
				$node['my_key'] = key($output);
				array_unshift($stack, $node);

				if ($node['name'] == 'noparse')
				{
					$noparse = $node_key;
				}
			}
			else
			{
				// closing tag
				if ($noparse !== null AND $node['name'] != 'noparse')
				{
					// closing a tag but we're in a noparse - treat as text
					$output[] = array('type' => 'text', 'data' => '[/' . $node['name_orig'] . ']');
				}
				else if (($key = $this->findFirstTag($node['name'], $stack)) !== false)
				{
					if ($node['name'] == 'noparse')
					{
						// we're closing a noparse tag that we opened
						if ($key != 0)
						{
							for ($i = 0; $i < $key; $i++)
							{
								$output[] = $stack["$i"];
								unset($stack["$i"]);
							}
						}

						$output[] = $node;

						unset($stack["$key"]);
						$stack = array_values($stack); // this is a tricky way to renumber the stack's keys

						$noparse = null;

						continue;
					}

					if ($key != 0)
					{
						end($output);
						$max_key = key($output);

						// we're trying to close a tag which wasn't the last one to be opened
						// this is bad nesting, so fix it by closing tags early
						for ($i = 0; $i < $key; $i++)
						{
							$output[] = array('type' => 'tag', 'name' => $stack["$i"]['name'], 'name_orig' => $stack["$i"]['name_orig'], 'closing' => true);
							$max_key++;
							$stack["$i"]['added_list'][] = $max_key;
						}
					}

					$output[] = $node;

					if ($key != 0)
					{
						$max_key++; // for the node we just added

						// ...and now reopen those tags in the same order
						for ($i = $key - 1; $i >= 0; $i--)
						{
							$output[] = $stack["$i"];
							$max_key++;
							$stack["$i"]['added_list'][] = $max_key;
						}
					}

					unset($stack["$key"]);
					$stack = array_values($stack); // this is a tricky way to renumber the stack's keys
				}
				else
				{
					// we tried to close a tag which wasn't open, to just make this text
					$output[] = array('type' => 'text', 'data' => '[/' . $node['name_orig'] . ']');
				}
			}
		}

		// These tags were never closed, so we want to display the literal BB code.
		// Rremove any nodes we might've added before, thinking this was valid,
		// and make this node become text.
		foreach ($stack AS $open)
		{
			foreach ($open['added_list'] AS $node_key)
			{
				unset($output["$node_key"]);
			}
			$output["$open[my_key]"] = array(
				'type' => 'text',
				'data' => '[' . $open['name_orig'] . (!empty($open['option']) ? '=' . $open['delimiter'] . $open['option'] . $open['delimiter'] : '') . ']'
			);
		}

		return $output;
	}

	/**
	 * Override each tag's default strip_space_after setting ..
	 * We don't want to strip spaces when parsing bbcode for the editor
	 *
	 * @param	bool
	 */
	function setStripSpace($value)
	{
		$this->stripSpaceAfter = $value;
	}

	/**
	 * Takes a parse array and parses it into the final HTML.
	 * Tags are assumed to be matched.
	 *
	 * @param	array	Parse array
	 * @param	bool	Whether to parse smilies
	 * @param	bool	Whether to parse img (for the video tags)
	 * @param	bool	Whether to allow HTML (for smilies)
	 *
	 * @return	string	Final HTML
	 */
	protected function parseArray($preparsed, $do_smilies, $do_imgcode, $do_html = false)
	{
		$output = '';

		$stack = array();
		$stack_size = 0;

		// holds options to disable certain aspects of parsing
		$parse_options = array(
			'no_parse'          => 0,
			'no_wordwrap'       => 0,
			'no_smilies'        => 0,
			'strip_space_after' => 0
		);

		$node_max = count($preparsed);
		$node_num = 0;

		foreach ($preparsed AS $node)
		{
			$node_num++;

			$pending_text = '';
			if ($node['type'] == 'text')
			{
				$pending_text =& $node['data'];

				// remove leading space after a tag
				if ($parse_options['strip_space_after'])
				{
					$pending_text = $this->stripFrontBackWhitespace($pending_text, $parse_options['strip_space_after'], true, false);
					$parse_options['strip_space_after'] = 0;
				}

				// parse smilies
				if ($do_smilies AND !$parse_options['no_smilies'])
				{
					$pending_text = $this->parseSmilies($pending_text, $do_html);
				}

				// do word wrap
				if (!$parse_options['no_wordwrap'])
				{
					$pending_text = $this->doWordWrap($pending_text);
				}

				if ($parse_options['no_parse'])
				{
					$pending_text = str_replace(array('[', ']'), array('&#91;', '&#93;'), $pending_text);
				}
			}
			else if ($node['closing'] == false)
			{
				$parse_options['strip_space_after'] = 0;

				if ($parse_options['no_parse'] == 0)
				{
					// opening a tag
					// initialize data holder and push it onto the stack
					$node['data'] = '';
					array_unshift($stack, $node);
					++$stack_size;

					$has_option = $node['option'] !== false ? 'option' : 'no_option';
					$tag_info =& $this->tag_list["$has_option"]["$node[name]"];

					// setup tag options
					if (!empty($tag_info['stop_parse']))
					{
						$parse_options['no_parse'] = 1;
					}
					if (!empty($tag_info['disable_smilies']))
					{
						$parse_options['no_smilies']++;
					}
					if (!empty($tag_info['disable_wordwrap']))
					{
						$parse_options['no_wordwrap']++;
					}
				}
				else
				{
					$pending_text = '&#91;' . $node['name_orig'] . ($node['option'] !== false ? "=$node[delimiter]$node[option]$node[delimiter]" : '') . '&#93;';
				}
			}
			else
			{
				$parse_options['strip_space_after'] = 0;

				// closing a tag
				// look for this tag on the stack
				if (($key = $this->findFirstTag($node['name'], $stack)) !== false)
				{
					// found it
					$open =& $stack["$key"];
					$this->currentTag =& $open;

					$has_option = $open['option'] !== false ? 'option' : 'no_option';

					// check to see if this version of the tag is valid
					if (isset($this->tag_list["$has_option"]["$open[name]"]))
					{
						$tag_info =& $this->tag_list["$has_option"]["$open[name]"];

						// make sure we have data between the tags
						if ((isset($tag_info['strip_empty']) AND $tag_info['strip_empty'] == false) OR trim($open['data']) != '')
						{
							// make sure our data matches our pattern if there is one
							if (empty($tag_info['data_regex']) OR preg_match($tag_info['data_regex'], $open['data']))
							{
								// see if the option might have a tag, and if it might, run a parser on it
								if (!empty($tag_info['parse_option']) AND strpos($open['option'], '[') !== false)
								{
									$old_stack = $stack;
									$open['option'] = $this->parseBbcode($open['option'], $do_smilies, $do_imgcode);
									$stack = $old_stack;
									$this->currentTag =& $open;
									unset($old_stack);
								}

								// now do the actual replacement
								if (isset($tag_info['html']))
								{
									// this is a simple HTML replacement
									// removing bad fix per Freddie.
									//$search = array("'", '=');
									//$replace = array('&#039;', '&#0061;');
									//$open['data'] = str_replace($search, $replace, $open['data']);
									//$open['option'] = str_replace($search, $replace, $open['option']);
									$pending_text = sprintf($tag_info['html'], $open['data'], $open['option'], $this->baseurl);
								}
								else if (isset($tag_info['callback']))
								{
									// call a callback function
									if ($tag_info['callback'] == 'handle_bbcode_video' AND !$do_imgcode)
									{
										$tag_info['callback'] = 'handle_bbcode_url';
										$open['option'] = '';
									}

									$pending_text = $this->$tag_info['callback']($open['data'], $open['option']);
								}
							}
							else
							{
								// oh, we didn't match our regex, just print the tag out raw
								$pending_text =
									'&#91;' . $open['name_orig'] .
									($open['option'] !== false ? "=$open[delimiter]$open[option]$open[delimiter]" : '') .
									'&#93;' . $open['data'] . '&#91;/' . $node['name_orig'] . '&#93;'
									;
							}
						}

						if (!isset($tag_info['ignore_global_strip_space_after']))
						{
							$tag_info['ignore_global_strip_space_after'] = false;
						}

						// undo effects of various tag options
						if (!empty($tag_info['strip_space_after']) AND ($this->stripSpaceAfter OR $tag_info['ignore_global_strip_space_after']))
						{
							$parse_options['strip_space_after'] = $tag_info['strip_space_after'];
						}
						if (!empty($tag_info['stop_parse']))
						{
							$parse_options['no_parse'] = 0;
						}
						if (!empty($tag_info['disable_smilies']))
						{
							$parse_options['no_smilies']--;
						}
						if (!empty($tag_info['disable_wordwrap']))
						{
							$parse_options['no_wordwrap']--;
						}
					}
					else
					{
						// this tag appears to be invalid, so just print it out as text
						$pending_text = '&#91;' . $open['name_orig'] . ($open['option'] !== false ? "=$open[delimiter]$open[option]$open[delimiter]" : '') . '&#93;';
					}

					// pop the tag off the stack

					unset($stack["$key"]);
					--$stack_size;
					$stack = array_values($stack); // this is a tricky way to renumber the stack's keys
				}
				else
				{
					// wasn't there - we tried to close a tag which wasn't open, so just output the text
					$pending_text = '&#91;/' . $node['name_orig'] . '&#93;';
				}
			}


			if ($stack_size == 0)
			{
				$output .= $pending_text;
			}
			else
			{
				$stack[0]['data'] .= $pending_text;
			}
		}

		return $output;
	}

	/**
	 * Checks if the specified tag exists in the list of parsable tags
	 *
	 * @param	string		Name of the tag
	 * @param	bool/null	true = tag with option, false = tag without option, null = either
	 *
	 * @return	bool		Whether the tag is valid
	 */
	protected function isValidTag($tagName, $hasOption = null)
	{
		if ($tagName === '')
		{
			// no tag name, so this definitely isn't a valid tag
			return false;
		}

		if ($tagName[0] == '/')
		{
			$tagName = substr($tagName, 1);
		}

		if ($hasOption === null)
		{
			return (isset($this->tag_list['no_option']["$tagName"]) OR isset($this->tag_list['option']["$tagName"]));
		}
		else
		{
			$option = $hasOption ? 'option' : 'no_option';
			return isset($this->tag_list["$option"]["$tagName"]);
		}
	}

	/**
	 * Checks if the specified tag option is valid (matches the regex if there is one)
	 *
	 * @param	string		Name of the tag
	 * @param	string		Value of the option
	 *
	 * @return	bool		Whether the option is valid
	 */
	protected function isValidOption($tagName, $tagOption)
	{
		if (empty($this->tag_list['option']["$tagName"]['option_regex']))
		{
			return true;
		}
		return preg_match($this->tag_list['option']["$tagName"]['option_regex'], $tagOption);
	}

	/**
	 * Find the first instance of a tag in an array
	 *
	 * @param	string		Name of tag
	 * @param	array		Array to search
	 *
	 * @return	int/false	Array key of first instance; false if it does not exist
	 */
	protected function findFirstTag($tagName, &$stack)
	{
		foreach ($stack AS $key => $node)
		{
			if ($node['name'] == $tagName)
			{
				return $key;
			}
		}
		return false;
	}

	/**
	 * Find the last instance of a tag in an array.
	 *
	 * @param	string		Name of tag
	 * @param	array		Array to search
	 *
	 * @return	int/false	Array key of first instance; false if it does not exist
	 */
	protected function findLastTag($tag_name, &$stack)
	{
		foreach (array_reverse($stack, true) AS $key => $node)
		{
			if ($node['name'] == $tag_name)
			{
				return $key;
			}
		}
		return false;
	}

	// The handle functions haven't been renamed since they must have the same name as in core (see vB_Api_Bbcode::fetchTagList).

	/**
	 * Allows extension of the class functionality at run time by calling an
	 * external function. To use this, your tag must have a callback of
	 * 'handle_external' and define an additional 'external_callback' entry.
	 * Your function will receive 3 parameters:
	 *	A reference to this BB code parser
	 *	The value for the tag
	 *	The option for the tag
	 * Ensure that you accept at least the first parameter by reference!
	 *
	 * @param	string	Value for the tag
	 * @param	string	Option for the tag (if it has one)
	 *
	 * @return	string	HTML representation of the tag
	 */
	function handle_external($value, $option = null)
	{
		$open = $this->currentTag;

		$has_option = $open['option'] !== false ? 'option' : 'no_option';
		$tag_info =& $this->tag_list["$has_option"]["$open[name]"];

		return $tag_info['external_callback']($this, $value, $option);
	}

	/**
	 * Handles an [email] tag. Creates a link to email an address.
	 *
	 * @param	string	If tag has option, the displayable email name. Else, the email address.
	 * @param	string	If tag has option, the email address.
	 *
	 * @return	string	HTML representation of the tag.
	 */
	protected function handle_bbcode_email($text, $link = '')
	{
		$rightlink = trim($link);
		if (empty($rightlink))
		{
			// no option -- use param
			$rightlink = trim($text);
		}
		$rightlink = str_replace(array('`', '"', "'", '['), array('&#96;', '&quot;', '&#39;', '&#91;'), $this->stripSmilies($rightlink));

		if (!trim($link) OR $text == $rightlink)
		{
			$tmp = vB_String::unHtmlSpecialChars($text);
			if (vB_String::vbStrlen($tmp) > 55 AND $this->isWysiwyg() == false)
			{
				$text = vB_String::htmlSpecialCharsUni(vbchop($tmp, 36) . '...' . substr($tmp, -14));
			}
		}

		// remove double spaces -- fixes issues with wordwrap
		$rightlink = str_replace('  ', '', $rightlink);

		// email hyperlink (mailto:)
		if (vB_String::isValidEmail($rightlink))
		{
			return "<a href=\"mailto:$rightlink\">$text</a>";
		}
		else
		{
			return $text;
		}
	}

	/**
	 * Handles a [quote] tag. Displays a string in an area indicating it was quoted from someone/somewhere else.
	 *
	 * @param	string	The body of the quote.
	 * @param	string	If tag has option, the original user to post.
	 *
	 * @return	string	HTML representation of the tag.
	 */
	function handle_bbcode_quote($message, $username = '')
	{
		//		global $vbphrase, $show;

		// remove smilies from username
		$username = $this->stripSmilies($username);
		if (preg_match('/^(.+)(?<!&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5});\s*(n?\d+)\s*$/U', $username, $match))
		{
			$username = $match[1];
			$postid = $match[2];
		}
		else
		{
			$postid = 0;
		}

		$username = $this->doWordWrap($username);

		$show['username'] = iif($username != '', true, false);
		$message = $this->stripFrontBackWhitespace($message, 1);

		$templater = vB_Template::create($this->printable ? $this->quotePrintableTemplate : $this->quoteTemplate, true);
		$templater->register('message', $message);
		$templater->register('postid', $postid);
		$templater->register('username', $username);
		$templater->register('quote_vars', $this->quote_vars);
		return $templater->render();
	}

	/**
	 * Handles a [php] tag. Syntax highlights a string of PHP.
	 *
	 * @param	string	The code to highlight.
	 *
	 * @return	string	HTML representation of the tag.
	 */
	function handle_bbcode_php($code)
	{
		//		global $vbphrase, $show;
		static $codefind1, $codereplace1, $codefind2, $codereplace2;

		$code = $this->stripFrontBackWhitespace($code, 1);

		if (!is_array($codefind1))
		{
			$codefind1 = array(
				'<br>',		// <br> to nothing
				'<br />'	// <br /> to nothing
			);
			$codereplace1 = array(
				'',
				''
			);

			$codefind2 = array(
				'&gt;',		// &gt; to >
				'&lt;',		// &lt; to <
				'&quot;',	// &quot; to ",
				'&amp;',	// &amp; to &
				'&#91;',    // &#91; to [
				'&#93;',    // &#93; to ]
			);
			$codereplace2 = array(
				'>',
				'<',
				'"',
				'&',
				'[',
				']',
			);
		}

		// remove htmlspecialchars'd bits and excess spacing
		$code = rtrim(str_replace($codefind1, $codereplace1, $code));
		$blockheight = $this->fetchBlockHeight($code); // fetch height of block element
		$code = str_replace($codefind2, $codereplace2, $code); // finish replacements

		// do we have an opening <? tag?
		if (!preg_match('#<\?#si', $code))
		{
			// if not, replace leading newlines and stuff in a <?php tag and a closing tag at the end
			$code = "<?php BEGIN__VBULLETIN__CODE__SNIPPET $code \r\nEND__VBULLETIN__CODE__SNIPPET ?>";
			$addedtags = true;
		}
		else
		{
			$addedtags = false;
		}

		// highlight the string
		$oldlevel = error_reporting(0);
		$code = highlight_string($code, true);
		error_reporting($oldlevel);

		// if we added tags above, now get rid of them from the resulting string
		if ($addedtags)
		{
			$search = array(
				'#&lt;\?php( |&nbsp;)BEGIN__VBULLETIN__CODE__SNIPPET( |&nbsp;)#siU',
				'#(<(span|font)[^>]*>)&lt;\?(</\\2>(<\\2[^>]*>))php( |&nbsp;)BEGIN__VBULLETIN__CODE__SNIPPET( |&nbsp;)#siU',
				'#END__VBULLETIN__CODE__SNIPPET( |&nbsp;)\?(>|&gt;)#siU'
			);
			$replace = array(
				'',
				'\\4',
				''
			);

			$code = preg_replace($search, $replace, $code);
		}

		$code = preg_replace('/&amp;#([0-9]+);/', '&#$1;', $code); // allow unicode entities back through
		$code = str_replace(array('[', ']'), array('&#91;', '&#93;'), $code);

		$templater = vB_Template::create($this->printable ? 'bbcode_php_printable' : 'bbcode_php', true);
		$templater->register('blockheight', $blockheight);
		$templater->register('code', $code);
		return $templater->render();
		}

		/**
		 * Emulates the behavior of a pre tag in HTML. Tabs and multiple spaces
		 * are replaced with spaces mixed with non-breaking spaces. Usually combined
		 * with code tags. Note: this still allows the browser to wrap lines.
		 *
		 * @param	string	Text to convert. Should not have <br> tags!
		 *
		 * @param	string	Converted text
		 */
		protected function emulatePreTag($text)
		{
			$text = str_replace(
				array("\t",       '  '),
				array('        ', '&nbsp; '),
				nl2br($text)
			);

			return preg_replace('#([\r\n]) (\S)#', '$1&nbsp;$2', $text);
		}

		/**
		 * Handles a [video] tag. Displays a movie.
		 *
		 * @param	string	The code to display
		 *
		 * @return	string	HTML representation of the tag.
		 */
		protected function handle_bbcode_video($url, $option)
		{
			$params = array();
			$options = explode(';', $option);
			$provider = strtolower($options[0]);
			$code = $options[1];

			if (!$code OR !$provider)
			{
				return '[video=' . $option . ']' . $url . '[/video]';
			}

			$templater = vB_Template::create('bbcode_video', true);
			$templater->register('url', $url);
			$templater->register('provider', $provider);
			$templater->register('code', $code);

			return $templater->render();
		}

		/**
		 * Handles a [code] tag. Displays a preformatted string.
		 *
		 * @param	string	The code to display
		 *
		 * @return	string	HTML representation of the tag.
		 */
		function handle_bbcode_code($code)
		{
			//		global $vbphrase, $show;

			// remove unnecessary line breaks and escaped quotes
			$code = str_replace(array('<br>', '<br />'), array('', ''), $code);

			$code = $this->stripFrontBackWhitespace($code, 1);

			if ($this->printable)
			{
				$code = $this->emulatePreTag($code);
				$template = 'bbcode_code_printable';
			}
			else
			{
				$blockheight = $this->fetchBlockHeight($code);
				$template = 'bbcode_code';
			}

			$templater = vB_Template::create($template, true);
			$templater->register('blockheight', $blockheight);
			$templater->register('code', $code);
			return $templater->render();
		}

		/**
		 * Handles an [html] tag. Syntax highlights a string of HTML.
		 *
		 * @param	string	The HTML to highlight.
		 *
		 * @return	string	HTML representation of the tag.
		 */
		function handle_bbcode_html($code)
		{
			//		global $vbphrase, $show;
			static $regexfind, $regexreplace;

			$code = $this->stripFrontBackWhitespace($code, 1);


			if (!is_array($regexfind))
			{
				$regexfind = array(
					'#<br( /)?>#siU',				// strip <br /> codes
					'#(&amp;\w+;)#siU',				// do html entities
					'#&lt;!--(.*)--&gt;#siU',		// italicise comments
				'#&lt;((?>[^&"\']+?|&quot;.*&quot;|&(?!gt;)|"[^"]*"|\'[^\']*\')+)&gt;#esiU'			// push code through the tag handler
			);
			$regexreplace = array(
				'',								// strip <br /> codes
				'<b><i>\1</i></b>',				// do html entities
				'<i>&lt;!--\1--&gt;</i>',		// italicise comments
				"\$this->handle_bbcode_html_tag('\\1')"	// push code through the tag handler
			);
			}

			if ($this->options['do_html'])
			{
				$regexfind[] = '#<((?>[^>"\']+?|"[^"]*"|\'[^\']*\')+)>#e';
				$regexreplace[] = "\$this->handle_bbcode_html_tag(vB_String::htmlSpecialCharsUni(str_replace('\\\"', '\"', '\\1')))";
			}
			// parse the code
			$code = preg_replace($regexfind, $regexreplace, $code);

			// how lame but HTML might not be on in signatures
			if ($this->options['do_html'])
			{
				$regexfind = array_pop($regexfind);
				$regexreplace = array_pop($regexreplace);
			}

			if ($this->printable)
			{
				$code = $this->emulatePreTag($code);
				$template = 'bbcode_html_printable';
			}
			else
			{
				$blockheight = $this->fetchBlockHeight($code);
				$template = 'bbcode_html';
			}

			$templater = vB_Template::create($template, true);
			$templater->register('blockheight', $blockheight);
			$templater->register('code', $code);
			return $templater->render();
			}

			/**
			 * Handles an individual HTML tag in a [html] tag.
			 *
			 * @param	string	The body of the tag.
			 *
			 * @return	string	Syntax highlighted, displayable HTML tag.
			 */
			function handle_bbcode_html_tag($tag)
			{
				static $bbcode_html_colors;

				if (empty($bbcode_html_colors))
				{
					$bbcode_html_colors = $this->fetchBbcodeHtmlColors();
				}

				// change any embedded URLs so they don't cause any problems
				$tag = preg_replace('#\[(email|url)=&quot;(.*)&quot;\]#siU', '[$1="$2"]', $tag);

				// find if the tag has attributes
				$spacepos = strpos($tag, ' ');
				if ($spacepos != false)
				{
					// tag has attributes - get the tag name and parse the attributes
					$tagname = substr($tag, 0, $spacepos);
					$tag = preg_replace('# (\w+)=&quot;(.*)&quot;#siU', ' \1=<span style="color:' . $bbcode_html_colors['attribs'] . '">&quot;\2&quot;</span>', $tag);
				}
				else
				{
					// no attributes found
					$tagname = $tag;
				}
				// remove leading slash if there is one
				if ($tag{0} == '/')
				{
					$tagname = substr($tagname, 1);
				}
				// convert tag name to lower case
				$tagname = strtolower($tagname);

				// get highlight colour based on tag type
				switch($tagname)
				{
					// table tags
				case 'table':
					case 'tr':
						case 'td':
							case 'th':
								case 'tbody':
									case 'thead':
										$tagcolor = $bbcode_html_colors['table'];
										break;
										// form tags
										//NOTE: Supposed to be a semi colon here ?
										case 'form';
									case 'input':
										case 'select':
											case 'option':
												case 'textarea':
													case 'label':
														case 'fieldset':
															case 'legend':
																$tagcolor = $bbcode_html_colors['form'];
																break;
																// script tags
															case 'script':
																$tagcolor = $bbcode_html_colors['script'];
																break;
																// style tags
															case 'style':
																$tagcolor = $bbcode_html_colors['style'];
																break;
																// anchor tags
															case 'a':
																$tagcolor = $bbcode_html_colors['a'];
																break;
																// img tags
															case 'img':
																$tagcolor = $bbcode_html_colors['img'];
																break;
																// if (vB Conditional) tags
															case 'if':
																case 'else':
																	case 'elseif':
																		$tagcolor = $bbcode_html_colors['if'];
																		break;
																		// all other tags
																	default:
																		$tagcolor = $bbcode_html_colors['default'];
																		break;
				}

				$tag = '<span style="color:' . $tagcolor . '">&lt;' . str_replace('\\"', '"', $tag) . '&gt;</span>';
				return $tag;
			}

			/*
			 * Handled [h] tags - converts to <b>
			 *
			 * @param	string	Body of the [H]
			 * @param	string	H Size (1 - 6)
			 *
			 * @return	string	Parsed text
			 */
			function handle_bbcode_h($text, $option)
			{
				if (preg_match('#^[1-6]$#', $option))
				{
					return "<b>{$text}</b><br /><br />";
				}
				else
				{
					return $text;
				}

				return $text;
			}

			/**
			 * Handles a [size] tag
			 *
			 * @param	string	The text to size.
			 * @param	string	The size to size to
			 *
			 * @return	string	HTML representation of the tag.
			 */
			function handle_bbcode_size($text, $size)
			{
				$newsize = 0;
				if (preg_match('#^[1-7]$#si', $size, $matches))
				{
					switch ($size)
					{
					case 1:
						$newsize = '8px';
						break;
					case 2:
						$newsize = '10px';
						break;
					case 3:
						$newsize = '12px';
						break;
					case 4:
						$newsize = '20px';
						break;
					case 5:
						$newsize = '28px';
						break;
					case 6:
						$newsize = '48px';
						break;
					case 7:
						$newsize = '72px';
					}

					return "<span style=\"font-size:$newsize\">$text</span>";
				}
				else if (preg_match('#^([8-9]|([1-6][0-9])|(7[0-2]))px$#si', $size, $matches))
				{
					$newsize = $size;
				}

				if ($newsize)
				{
					return "<span style=\"font-size:$newsize\">$text</span>";
				}
				else
				{
					return $text;
				}
			}

			/**
			 * Handles a [list] tag. Makes a bulleted or ordered list.
			 *
			 * @param	string	The body of the list.
			 * @param	string	If tag has option, the type of list (ordered, etc).
			 *
			 * @return	string	HTML representation of the tag.
			 */
			function handle_bbcode_list($text, $type = '')
			{
				if ($type)
				{
					switch ($type)
					{
					case 'A':
						$listtype = 'upper-alpha';
						break;
					case 'a':
						$listtype = 'lower-alpha';
						break;
					case 'I':
						$listtype = 'upper-roman';
						break;
					case 'i':
						$listtype = 'lower-roman';
						break;
					case '1': //break missing intentionally
						default:
							$listtype = 'decimal';
							break;
					}
				}
				else
				{
					$listtype = '';
				}

				// emulates ltrim after nl2br
				$text = preg_replace('#^(\s|<br>|<br />)+#si', '', $text);

				$bullets = preg_split('#\s*\[\*\]#s', $text, -1, PREG_SPLIT_NO_EMPTY);
				if (empty($bullets))
				{
					return "\n\n";
				}

				$output = '';
				foreach ($bullets AS $bullet)
				{
					$output .= $this->handle_bbcode_list_element($bullet);
				}

				if ($listtype)
				{
					return '<ol class="' . $listtype . '">' . $output . '</ol>';
				}
				else
				{
					return "<ul>$output</ul>";
				}
			}

			/**
			 * Handles a single bullet of a list
			 *
			 * @param	string	Text of bullet
			 *
			 * @return	string	HTML for bullet
			 */
			function handle_bbcode_list_element($text)
			{
				return "<li>$text</li>\n";
			}

			/**
			 * Handles a [url] tag. Creates a link to another web page.
			 *
			 * @param	string	If tag has option, the displayable name. Else, the URL.
			 * @param	string	If tag has option, the URL.
			 *
			 * @return	string	HTML representation of the tag.
			 */
			function handle_bbcode_url($text, $link)
			{
				$rightlink = trim($link);

				if (empty($rightlink))
				{
					// no option -- use param
					$rightlink = trim($text);
				}
				$rightlink = str_replace(array('`', '"', "'", '['), array('&#96;', '&quot;', '&#39;', '&#91;'), $this->stripSmilies($rightlink));

				// remove double spaces -- fixes issues with wordwrap
				$rightlink = str_replace('  ', '', $rightlink);

				if (!preg_match('#^[a-z0-9]+(?<!about|javascript|vbscript|data):#si', $rightlink))
				{
					$rightlink = "http://$rightlink";
				}

				if (!trim($link) OR str_replace('  ', '', $text) == $rightlink)
				{
					$tmp = vB_String::unHtmlSpecialChars($rightlink);
					if (vB_String::vbStrlen($tmp) > 55 AND $this->isWysiwyg() == false)
					{
						$text = vB_String::htmlSpecialCharsUni(vB_String::vbChop($tmp, 36) . '...' . substr($tmp, -14));
					}
					else
					{
						// under the 55 chars length, don't wordwrap this
						$text = str_replace('  ', '', $text);
					}
				}

				static $current_url, $current_host, $allowed, $friendlyurls = array();
				if (!isset($current_url))
				{
					$current_url = @vB_String::parseUrl(self::$bbUrl);
				}
				$is_external = self::$urlNoFollow;

				if (self::$urlNoFollow)
				{
					if (!isset($current_host))
					{
						$current_host = preg_replace('#:(\d)+$#', '', self::$vBHttpHost);

						$allowed = preg_split('#\s+#', self::$urlNoFollowWhiteList, -1, PREG_SPLIT_NO_EMPTY);
						$allowed[] = preg_replace('#^www\.#i', '', $current_host);
						$allowed[] = preg_replace('#^www\.#i', '', $current_url['host']);
					}

					$target_url = preg_replace('#^([a-z0-9]+:(//)?)#', '', $rightlink);

					foreach ($allowed AS $host)
					{
						if (vB_String::stripos($target_url, $host) !== false)
						{
							$is_external = false;
						}
					}
				}

				// standard URL hyperlink
				return "<a href=\"$rightlink\" target=\"_blank\"" . ($is_external ? ' rel="nofollow"' : '') . ">$text</a>";
			}

			/**
			 * Handles an [img] tag.
			 *
			 * @param	string	The text to search for an image in.
			 * @param	string	Whether to parse matching images into pictures or just links.
			 *
			 * @return	string	HTML representation of the tag.
			 */
			function handle_bbcode_img($bbcode, $do_imgcode, $has_img_code = false, $fulltext = '', $forceShowImages = false)
			{
				$sessionurl = self::$sessionUrl;
				$showImages = (self::getUserValue('userid') == 0 OR self::getUserValue('showimages') OR $forceShowImages);

				/* Do search on $fulltext, which would be the entire article, not just a page of the article which would be in $page */
				if (!$fulltext)
				{
					$fulltext = $bbcode;
				}

				if (($has_img_code & self::BBCODE_HAS_ATTACH) AND preg_match_all('#\[attach(?:=(right|left|config))?\]([[:alnum:]]+)\[/attach\]#i', $fulltext, $matches))
				{

					$legacyIds = $attachmentIds = array();
					foreach($matches[2] AS $key => $attachmentid)
					{
						$align = $matches[1]["$key"];
						$search[] = '#\[attach(' . (!empty($align) ? '=' . $align : '') . ')\](' . $attachmentid . ')\[/attach\]#i';

						// We need to decide whether it's a legacy attachment or a vB5 one
						if (preg_match('#^n(\d+)$#', $attachmentid, $matches2))
						{
							// if the id has 'n' as prefix, it's a nodeid
							$attachmentIds[] = intval($matches2[1]);
						}
						else
						{
							// it's a legacy attachmentid
							$legacyIds[] = intval($attachmentid);
						}
					}

					if (!empty($legacyIds))
					{
						$this->oldAttachments = vB_Api::instanceInternal('filedata')->fetchLegacyAttachmentsIds($legacyIds);
						$attachmentIds += array_values($this->oldAttachments);
					}

					// we may already have the attachments (see vB5_Template_NodeText)
					if (!empty($this->attachments))
					{
						$attachmentIds = array_diff(array_unique($attachmentIds), array_keys($this->attachments));
					}

					if (!empty($attachmentIds))
					{
						$attachments = vB_Api::instanceInternal('filedata')->fetchFiledataByid($attachmentIds);
						$this->setAttachments($attachments);
					}

					$bbcode = preg_replace_callback($search, array($this, 'attachReplaceCallback'), $bbcode);
				}

				if ($has_img_code & self::BBCODE_HAS_IMG)
				{
					if ($do_imgcode AND $showImages)
					{
						// do [img]xxx[/img]
						$bbcode = preg_replace('#\[img\]\s*(https?://([^*\r\n]+|[a-z0-9/\\._\- !]+))\[/img\]#iUe', "\$this->handleBbcodeImgMatch('\\1')", $bbcode);
					}
					else
					{
						$bbcode = preg_replace('#\[img\]\s*(https?://([^*\r\n]+|[a-z0-9/\\._\- !]+))\[/img\]#iUe', "\$this->handle_bbcode_url(str_replace('\\\"', '\"', '\\1'), '')", $bbcode);
					}
				}

				if ($has_img_code & self::BBCODE_HAS_SIGPIC)
				{
					$bbcode = preg_replace('#\[sigpic\](.*)\[/sigpic\]#siUe', "\$this->handle_bbcode_sigpic('\\1')", $bbcode);
				}

				if ($has_img_code & self::BBCODE_HAS_RELPATH)
				{
					$bbcode = str_replace('[relpath][/relpath]', vB_String::htmlSpecialCharsUni(vB5_Request::get('vBUrlClean')), $bbcode);
				}

				return $bbcode;
			}

			protected function attachReplaceCallback($matches)
			{
				$align = $matches[1];

				// Same as before: are we looking at a legacy attachment?
				if (preg_match('#^n(\d+)$#', $matches[2], $matches2))
				{
					// if the id has 'n' as prefix, it's a nodeid
					$attachmentid = intval($matches2[1]);
				}
				else
				{
					// it's a legacy attachmentid, get the new id
					$attachmentid = isset($this->oldAttachments[intval($matches[2])]) ? $this->oldAttachments[intval($matches[2])] : false;
				}

				$cangetattachment = $this->options['do_imgcode'];
				$canseethumbnails = true;

				if ($attachmentid === false)
				{	// No data match was found for the attachment, so just return nothing
					return '';
				}
				else if (!empty($this->attachments["$attachmentid"]))
				{	// attachment specified by [attach] tag belongs to this post
					$attachment =& $this->attachments["$attachmentid"];
					// remove attachment from array
					if ($this->unsetattach)
					{
						unset($this->attachments["$attachmentid"]);
					}

					if (!empty($attachment['settings']) AND strtolower($align) == 'config')
					{
						$settings = unserialize($attachment['settings']);
					}
					else
					{
						$settings = '';
					}

					if (!$attachment['visible'] AND $attachment['userid'] != self::getUserValue('userid'))
					{	// Don't show inline unless the poster is viewing the post (post preview)
						return '';
					}

					$forceimage = false;
					if ($cangetattachment AND $canseethumbnails AND $attachment['resize_filesize'] == $attachment['filesize'])
					{
						$attachment['hasthumbnail'] = false;
						$forceimage = self::$viewAttachedImages;
					}
					else if (!$canseethumbnails)
					{
						$attachment['hasthumbnail'] = false;
					}

					if (empty($attachment['extension']))
					{
						$attachment['filename'] = $this->fetchCensoredText(vB_String::htmlSpecialCharsUni($attachment['filename']));
						$attachment['extension'] = strtolower(file_extension($attachment['filename']));
					}
					$attachment['filesize'] = vb_number_format($attachment['filesize'], 1, true);

					$lightbox_extensions = array('gif', 'jpg', 'jpeg', 'jpe', 'png', 'bmp');

					$fullsize = false;

					switch($attachment['extension'])
					{
					case 'gif':
						case 'jpg':
							case 'jpeg':
								case 'jpe':
									case 'png':
										case 'bmp':
											case 'tiff':
												case 'tif':
													case 'psd':
														case 'pdf':
															$imgclass = array();
															$alt_text = $title_text = $caption_tag = $styles = '';
															if ($settings)
															{
																if ($settings['alignment'])
																{
																	switch ($settings['alignment'])
																	{
																	case 'left':
																		$imgclass[] = 'align_left';
																		break;
																	case 'center':
																		$imgclass[] = 'align_center';
																		break;
																	case 'right':
																		$imgclass[] = 'align_right';
																		break;
																	}
																}
																if ($settings['size'])
																{
																	if (isset($settings['size']))
																	{
																		switch ($settings['size'])
																		{
																		case 'thumbnail':
																			$imgclass[] = 'size_thumbnail';
																			break;
																		case 'medium':
																			$imgclass[] = 'size_medium';
																			break;
																		case 'large':
																			$imgclass[] = 'size_large';
																			break;
																		case 'fullsize':
																			$fullsize = true;
																			break;
																		}
																	}
																}
																//						if ($settings['caption'])
																//						{
																//							$caption_tag = "<p class=\"caption $size_class\">$settings[caption]</p>";
																//						}
																$alt_text = $settings['title'];
																$description_text = $settings['description'];
																$title_text = $settings['title'];
																$styles = $settings['styles'];
															}

															if (($settings OR (self::$viewAttachedImages == 1 AND $attachment['hasthumbnail'])) AND self::getUserValue('showimages') AND $cangetattachment)
															{
																if (empty($link))
																{
																	$link = $this->getAttachmentLink($attachment);
																}
																if (!empty($attachment['nodeid']))
																{
																	$id = 'attachment' . $attachment['nodeid'];
																}
																else
																{
																	$id = 'filedata' . $attachment['filedataid'];
																}

																$lightbox = (!$fullsize AND $cangetattachment AND in_array($attachment['extension'], $lightbox_extensions));
																$hrefbits = array(
																	'href'   => "$link&amp;d=$attachment[dateline]",
																	'id'     => $id,
																	'class'     => 'bbcode-attachment',
																);
																if ($lightbox)
																{
																	$hrefbits["rel"] = 'Lightbox_' . $this->containerid;
																}
																else
																{
																	$hrefbits["rel"] = "nofollow";
																}
																//						if ($addnewwindow)
																//						{
																//							$hrefbits['target'] = '_blank';
																//						}
																$atag = '';
																foreach ($hrefbits AS $tag => $value)
																{
																	$atag .= "$tag=\"$value\" ";
																}

																$imgbits = array(
																	'src'    => "$link&amp;d=$attachment[resize_dateline]",
																	'border' => '0',
																	'alt'    => $alt_text ? $alt_text : $this->getPhrase('image_larger_version_x_y_z', $attachment['filename'], $attachment['counter'], $attachment['filesize'], $attachment['filedataid'])
																);

																if (!$settings AND !$this->displayimage)
																{
																	$imgbits['src'] .= '&amp;thumb=1';
																}

																if (!empty($imgclass))
																{
																	$imgbits['class'] = implode(' ', $imgclass);
																}
																else
																{
																	$imgbits['class'] = 'thumbnail';
																}
																if ($title_text)
																{
																	$imgbits['title'] = $title_text;
																}
																else if (isset($description_text) AND !empty($description_text))
																{
																	$imgbits['title'] = $description_text;
																}

																if (isset($description_text) AND !empty($description_text))
																{
																	$imgbits['description'] = $description_text;
																}

																if ($styles)
																{
																	$imgbits['style'] = $styles;
																}
																else if (!$settings AND $align AND $align != '=CONFIG')
																{
																	$imgbits['style'] = "float:$align";
																}
																$imgtag = '';
																foreach ($imgbits AS $tag => $value)
																{
																	$imgtag .= "$tag=\"$value\" ";
																}

																if ($fullsize)
																{
																	return ($fullsize ? '<div class="size_fullsize">' : '') .
																		"<img $imgtag/>" . ($fullsize ? '</div>' : '');
																}
																else
																{
																	if (isset($settings['alignment']) && $settings['alignment'] == 'center')
																	{
																		return "<div class=\"img_align_center " .
																			($fullsize ? 'size_fullsize' : '') .
																			"\"><a $atag><img $imgtag/></a></div>";
																	}
																	else
																	{
																		return ($fullsize ? '<div class="size_fullsize">' : '') .
																			"<a $atag><img $imgtag/></a>" . ($fullsize ? '</div>' : '');
																	}
																}

															}
															else if (self::getUserValue('showimages') AND ($forceimage OR self::$viewAttachedImages == 3) AND !in_array($attachment['extension'], array('tiff', 'tif', 'psd', 'pdf')) AND $cangetattachment)
															{
																$link = $this->getAttachmentLink($attachment);
																return ($fullsize ? '<div class="size_fullsize">' : '') .
																	"<img src=\"" . $link . "&amp;d=$attachment[dateline]\" border=\"0\" alt=\""
																	. $this->getPhrase('image_x_y_z', $attachment['filename'], $attachment['counter'], $attachment['filesize'])
																	. "\" " . (!empty($align) ? " style=\"float: $align\"" : '') . " />";
																($fullsize ? '</div>' : '');
															}
															else
															{
																$link = $this->getAttachmentLink($attachment);
																return ($fullsize ? '<div class="size_fullsize">' : '') .
																	"<a href=\"" . $link . "&amp;d=$attachment[dateline]\" title=\""
																	. $this->getPhrase('image_x_y_z', $attachment['filename'], $attachment['counter'], $attachment['filesize'])
																	. "\">$attachment[filename]</a>" .
																	($fullsize ? '</div>' : '') ;
															}
															break;
														default:
															$link = $this->getAttachmentLink($attachment);
															return ($fullsize  ? '<div class="size_fullsize">' : '') .
																"<a href=\"" . $link . "&amp;d=$attachment[dateline]\" title=\""
																. $this->getPhrase('image_x_y_z', $attachment['filename'], $attachment['counter'], $attachment['filesize'])
																. "\">$attachment[filename]</a>" .
																($fullsize? '</div>' : '') ;
					}
				}
				else
				{	// Belongs to another post so we know nothing about it ... or we are not displying images so always show a link
					return "<a href=\"" . self::$baseurl . "/filedata/fetch?filedataid=$attachmentid\">" . $this->getPhrase('attachment') . " </a>";
				}
			}

			/**
			 * Handles a match of the [img] tag that will be displayed as an actual image.
			 *
			 * @param	string	The URL to the image.
			 *
			 * @return	string	HTML representation of the tag.
			 */
			function handleBbcodeImgMatch($link, $fullsize = false)
			{
				$link = $this->stripSmilies(str_replace('\\"', '"', $link));

				// remove double spaces -- fixes issues with wordwrap
				$link = str_replace(array('  ', '"'), '', $link);

				return  ($fullsize ? '<div class="size_fullsize">' : '')  . '<img class="bbcode-attachment" src="' .  $link . '" border="0" alt="" />'
					. ($fullsize ? '</div>' : '');
			}

			/**
			 * Handles the parsing of a signature picture. Most of this is handled
			 * based on the $parseUserinfo member.
			 *
			 * @param	string	Description for the sig pic
			 *
			 * @return	string	HTML representation of the sig pic
			 */
			function handle_bbcode_sigpic($description)
			{
				// remove unnecessary line breaks and escaped quotes
				$description = str_replace(array('<br>', '<br />', '\\"'), array('', '', '"'), $description);

				// permissions are checked on API method
				if (empty($this->parseUserinfo['userid']) OR empty($this->parseUserinfo['sigpic']))
				{
					// unknown user or no sigpic
					return '';
				}

				if (self::$useFileAvatar)
				{
					$sigpic_url = $this->registry->options['sigpicurl'] . '/sigpic' . $this->parseUserinfo['userid'] . '_' . $this->parseUserinfo['sigpicrevision'] . '.gif';
				}
				else
				{
					$sigpic_url = 'image.php?' . vB::getCurrentSession()->get('sessionurl') . 'u=' . $this->parseUserinfo['userid'] . "&amp;type=sigpic&amp;dateline=" . $this->parseUserinfo['sigpicdateline'];
				}

		/*
		if (defined('VB_AREA') AND VB_AREA != 'Forum')
		{
			// in a sub directory, may need to move up a level
			if ($sigpic_url[0] != '/' AND !preg_match('#^[a-z0-9]+:#i', $sigpic_url))
			{
				$sigpic_url = '../' . $sigpic_url;
			}
		}
		 */

				$description = str_replace(array('\\"', '"'), '', trim($description));

				if (self::getUserValue('userid') == 0 OR self::getUserValue('showimages'))
				{
					return "<img src=\"$sigpic_url\" alt=\"$description\" border=\"0\" />";
				}
				else
				{
					if (!$description)
					{
						$description = $sigpic_url;
						if (vB_String::vB_Strlen($description) > 55 AND $this->isWysiwyg() == false)
						{
							$description = substr($description, 0, 36) . '...' . substr($description, -14);
						}
					}
					return "<a href=\"$sigpic_url\">$description</a>";
				}
			}

			/**
			 * Appends the non-inline attachment UI to the passed $text
			 *
			 * @param	string	Text to append attachments
			 * @param	array	Attachment data
			 * @param	bool	Whether to show images
			 */
			public function append_noninline_attachments($text, $attachments, $do_imgcode = false)
			{
				if (!empty($attachments))
				{
					foreach ($attachments as &$attachment)
					{
						$attachment['filesize'] = (!empty($attachment['filesize'])) ?
							number_format($attachment['filesize'] / 1024, 1, '', '') . ' KB' : 0;
					}
					$attach_url = self::$baseurl . "/filedata/fetch?id=";
					$templater = vB_Template::create('bbcode_attachment_list', true);
					$templater->register('attachments', $attachments);
					$templater->register('attachurl', $attach_url);
					$text .= $templater->render();
				}
				return $text;
			}

			/**
			 * Removes the specified amount of line breaks from the front and/or back
			 * of the input string. Includes HTML line braeks.
			 *
			 * @param	string	Text to remove white space from
			 * @param	int		Amount of breaks to remove
			 * @param	bool	Whether to strip from the front of the string
			 * @param	bool	Whether to strip from the back of the string
			 */
			public function stripFrontBackWhitespace($text, $max_amount = 1, $strip_front = true, $strip_back = true)
			{
				$max_amount = intval($max_amount);

				if ($strip_front)
				{
					$text = preg_replace('#^(( |\t)*((<br>|<br />)[\r\n]*)|\r\n|\n|\r){0,' . $max_amount . '}#si', '', $text);
				}

				if ($strip_back)
				{
					// The original regex to do this: #(<br>|<br />|\r\n|\n|\r){0,' . $max_amount . '}$#si
					// is slow because the regex engine searches for all breaks and fails except when it's at the end.
					// This uses ^ as an optimization by reversing the string. Note that the strings in the regex
					// have been reversed too! strrev(<br />) == >/ rb<
					$text = strrev(preg_replace('#^(((>rb<|>/ rb<)[\n\r]*)|\n\r|\n|\r){0,' . $max_amount . '}#si', '', strrev(rtrim($text))));
				}

				return $text;
			}

			/**
			 * Removes translated smilies from a string.
			 *
			 * @param	string	Text to search
			 *
			 * @return	string	Text with smilie HTML returned to smilie codes
			 */
			protected function stripSmilies($text)
			{
				$cache =& $this->cacheSmilies(false);

				// 'replace' refers to the <img> tag, so we want to remove that
				return str_replace($cache, array_keys($cache), $text);
			}

			/**
			 * Determines whether a string contains an [img] tag.
			 *
			 * @param	string	Text to search
			 *
			 * @return	bool	Whether the text contains an [img] tag
			 */
			protected function containsBbcodeImgTags($text)
			{
				// use a bitfield system to look for img, attach, and sigpic tags

				$hasimage = 0;
				if (vB_String::stripos($text, '[/img]') !== false)
				{
					$hasimage += self::BBCODE_HAS_IMG;
				}

				if (vB_String::stripos($text, '[/attach]') !== false)
				{
					$hasimage += self::BBCODE_HAS_ATTACH;
				}

				if (vB_String::stripos($text, '[/sigpic]') !== false)
				{
					// permissions are checked on API method
					if (!empty($this->parseUserinfo['userid'])
						AND !empty($this->parseUserinfo['sigpic'])
					)
					{
						$hasimage += self::BBCODE_HAS_SIGPIC;
					}
				}

				if (vB_String::stripos($text, '[/relpath]') !== false)
				{
					$hasimage += self::BBCODE_HAS_RELPATH;
				}

				return $hasimage;
			}

			/**
			 * Returns the height of a block of text in pixels (assuming 16px per line).
			 * Limited by your "codemaxlines" setting (if > 0).
			 *
			 * @param	string	Block of text to find the height of
			 *
			 * @return	int		Number of lines
			 */
			protected function fetchBlockHeight($code)
			{

				$options = vB_Template_Options::instance();
				$codeMaxLines = $options->get('options.codemaxlines');
				// establish a reasonable number for the line count in the code block
				$numlines = max(substr_count($code, "\n"), substr_count($code, "<br />")) + 1;

				// set a maximum number of lines...
				if ($numlines > $codeMaxLines AND $codeMaxLines > 0)
				{
					$numlines = $codeMaxLines;
				}
				else if ($numlines < 1)
				{
					$numlines = 1;
				}

				// return height in pixels
				return ($numlines); // removed multiplier
			}

			/**
			 * Fetches the colors used to highlight HTML in an [html] tag.
			 *
			 * @return	array	array of type (key) to color (value)
			 */
			protected function fetchBbcodeHtmlColors()
			{
				return array(
					'attribs'	=> '#0000FF',
					'table'		=> '#008080',
					'form'		=> '#FF8000',
					'script'	=> '#800000',
					'style'		=> '#800080',
					'a'			=> '#008000',
					'img'		=> '#800080',
					'if'		=> '#FF0000',
					'default'	=> '#000080'
				);
			}

			/**
			 * Returns whether this parser is a WYSIWYG parser. Useful to change
			 * behavior slightly for a WYSIWYG parser without rewriting code.
			 *
			 * @return	bool	True if it is; false otherwise
			 */
			protected function isWysiwyg()
			{
				return false;
			}

			/**
			 * Chops a set of (fixed) BB code tokens to a specified length or slightly over.
			 * It will search for the first whitespace after the snippet length.
			 *
			 * @param	array	Fixed tokens
			 * @param	integer	Length of the text before parsing (optional)
			 *
			 * @return	array	Tokens, chopped to the right length.
			 */
	/*
	function make_snippet($tokens, $initial_length = 0)
	{
		// no snippet to make, or our original text was short enough
		if ($this->snippet_length == 0 OR ($initial_length AND $initial_length < $this->snippet_length))
		{
			$this->createdsnippet = false;
			return $tokens;
		}

		$counter = 0;
		$stack = array();
		$new = array();
		$over_threshold = false;

		foreach ($tokens AS $tokenid => $token)
		{
			// only count the length of text entries
			if ($token['type'] == 'text')
			{
				$length = vB_String::vbStrlen($token['data']);

				// uninterruptable means that we will always show until this tag is closed
				$uninterruptable = (isset($stack[0]) AND isset($this->uninterruptable["$stack[0]"]));

				if ($counter + $length < $this->snippet_length OR $uninterruptable)
				{
					// this entry doesn't push us over the threshold
					$new["$tokenid"] = $token;
					$counter += $length;
				}
				else
				{
					// a text entry that pushes us over the threshold
					$over_threshold = true;
					$last_char_pos = $this->snippet_length - $counter - 1; // this is the threshold char; -1 means look for a space at it
					if ($last_char_pos < 0)
					{
						$last_char_pos = 0;
					}

					if (preg_match('#\s#s', $token['data'], $match, PREG_OFFSET_CAPTURE, $last_char_pos))
					{
						$token['data'] = substr($token['data'], 0, $match[0][1]); // chop to offset of whitespace
						if (substr($token['data'], -3) == '<br')
						{
							// we cut off a <br /> code, so just take this out
							$token['data'] = substr($token['data'], 0, -3);
						}

						$new["$tokenid"] = $token;
					}
					else
					{
						$new["$tokenid"] = $token;
					}

					break;
				}
			}
			else
			{
				// not a text entry
				if ($token['type'] == 'tag')
				{
					// build a stack of open tags
					if ($token['closing'] == true)
					{
						// by now, we know the stack is sane, so just remove the first entry
						array_shift($stack);
					}
					else
					{
						array_unshift($stack, $token['name']);
					}
				}

				$new["$tokenid"] = $token;
			}
		}

		// since we may have cut the text, close any tags that we left open
		foreach ($stack AS $tag_name)
		{
			$new[] = array('type' => 'tag', 'name' => $tag_name, 'closing' => true);
		}

		$this->createdsnippet = (sizeof($new) != sizeof($tokens) OR $over_threshold); // we did something, so we made a snippet

		return $new;
	}
	 */

			/** Sets the template to be used for generating quotes
			 *
			 * @param	string	the template name
			 ***/
			public function setQuoteTemplate($templateName)
			{
				$this->quoteTemplate = $templateName;
			}

			/** Sets the template to be used for generating quotes
			 *
			 * @param	string	the template name
			 ***/
			public function setQuotePrintableTemplate($template_name)
			{
				$this->quotePrintableTemplate = $template_name;
			}

			/** Sets variables to be passed to the quote template
			 *
			 * @param	string	the template name
			 ***/
			public function setQuoteVars($var_array)
			{
				$this->quoteVars = $var_array;
			}

			/**
			 * Fetches the table helper in use. It also acts as a lazy initializer.
			 * If no table helper has been explicitly set, it will instantiate
			 * the class's default.
			 *
			 * @return	vBForum_BBCodeHelper_Table	Table helper object
			 */
			public function getTableHelper()
			{
				if (!isset($this->tableHelper))
				{
					$this->tableHelper = new vB_Library_BbCode_Table($this);
				}

				return $this->tableHelper;
			}

			/**
			 * Parses the [table] tag and returns the necessary HTML representation.
			 * TRs and TDs are parsed by this function (they are not real BB codes).
			 * Classes are pushed down to inner tags (TRs and TDs) and TRs are automatically
			 * valigned top.
			 *
			 * @param	string	Content within the table tag
			 * @param	string	Optional set of parameters in an unparsed format. Parses "param: value, param: value" form.
			 *
			 * @return	string	HTML representation of the table and its contents.
			 */
			protected function parseTableTag($content, $params = '')
			{
				$helper = $this->getTableHelper();
				return $helper->parseTableTag($content, $params);
			}

			protected function getUserValue($value)
			{
				$userinfo = vB_Api::instanceInternal('user')->fetchCurrentUserinfo();
				if ($userinfo === null OR empty($userinfo[$value]))
				{
					return null;
				}
				else
				{
					return $userinfo[$value];
				}
			}

			protected function getPhrase()
			{
				$phrase_array = func_get_args();
				$phrase_array[0] = vB_Api::instanceInternal('phrase')->fetch($phrase_array[0]);
				if ($phrase_array[0] === null OR empty($phrase_array[0]))
				{
					return '';
				}
				return @call_user_func_array('sprintf', $phrase_array);
			}

			protected function getAttachmentLink($attachment)
			{
				$userinfo = vB_Api::instance('user')->fetchUserinfo();
				$pictureurl .= self::$baseurl_core . '/attachment.php?';
				$pictureurl .= 'attachmentid=' . $attachment['nodeid'];
				$session = vB::getCurrentSession();
				$session->set_session_visibility(false);
				$pictureurl .= '&' . $session->get('sessionurl_js') . 'userid=' . $userinfo['userid'];
				return $pictureurl;
			}
			}

			// ####################################################################


/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 70155 $
|| ####################################################################
\*======================================================================*/
