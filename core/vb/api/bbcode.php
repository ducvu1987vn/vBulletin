<?php

/**
 * vB_Api_Bbcode
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Bbcode extends vB_Api
{
	/**#@+
	* These make up the bit field to disable specific types of BB codes.
	*/
	const ALLOW_BBCODE_BASIC	= 1;
	const ALLOW_BBCODE_COLOR	= 2;
	const ALLOW_BBCODE_SIZE		= 4;
	const ALLOW_BBCODE_FONT		= 8;
	const ALLOW_BBCODE_ALIGN	= 16;
	const ALLOW_BBCODE_LIST		= 32;
	const ALLOW_BBCODE_URL		= 64;
	const ALLOW_BBCODE_CODE		= 128;
	const ALLOW_BBCODE_PHP		= 256;
	const ALLOW_BBCODE_HTML		= 512;
	const ALLOW_BBCODE_IMG		= 1024;
	const ALLOW_BBCODE_QUOTE	= 2048;
	const ALLOW_BBCODE_CUSTOM	= 4096;
	/**#@-*/

	const EDITOR_INDENT	= 40;

	protected function __construct()
	{
		parent::__construct();
	}

	public function initInfo()
	{
		$response['defaultTags'] = $this->fetchTagList();
		$response['customTags'] = $this->fetchCustomTags();
		$response['defaultOptions'] = $this->fetchBbcodeOptions();
		$response['censorship'] = $this->fetchCensorshipInfo();
		$response['smilies'] = $this->fetchSmilies();

		$response['sessionUrl'] = vB::getCurrentSession()->get('sessionurl');
		$response['vBHttpHost'] = vB::getRequest()->getVbHttpHost();

		$options = vB::getDatastore()->get_value('options');
		$response['blankAsciiStrip'] = $options['blankasciistrip'];
		$response['wordWrap'] = $options['wordwrap'];
		$response['codeMaxLines'] = $options['codemaxlines'];
		$response['bbUrl'] = $options['bburl'];
		$response['viewAttachedImages'] = $options['viewattachedimages'];
		$response['urlNoFollow'] = $options['url_nofollow'];
		$response['urlNoFollowWhiteList'] = $options['url_nofollow_whitelist'];
		$response['useFileAvatar'] = $options['usefileavatar'];
		$response['sigpicUrl'] = $options['sigpicurl'];

		return $response;
	}

	/**
	* Grabs the list of default BB code tags.
	*
	* @param	string	Allows an optional path/URL to prepend to thread/post tags
	* @param	boolean	Force all BB codes to be returned?
	*
	* @return	array	Array of BB code tags
	*/
	public function fetchTagList($prepend_path = '', $force_all = false)
	{
		// TODO: we need to refactor $vbphrase
		global $vbphrase;
		static $tag_list;

		$options = vB::getDatastore()->get_value('options');

		if ($force_all)
		{
			$tag_list_bak = $tag_list;
			$tag_list = array();
		}

		if (empty($tag_list))
		{
			$tag_list = array();

			// [QUOTE]
			$tag_list['no_option']['quote'] = array(
				'callback'          => 'handle_bbcode_quote',
				'strip_empty'       => true,
				'strip_space_after' => 2
			);

			// [QUOTE=XXX]
			$tag_list['option']['quote'] = array(
				'callback'          => 'handle_bbcode_quote',
				'strip_empty'       => true,
				'strip_space_after' => 2,
			);

			// [HIGHLIGHT]
			$tag_list['no_option']['highlight'] = array(
				'html'        => '<span class="highlight">%1$s</span>',
				'strip_empty' => true
			);

			// [NOPARSE]-- doesn't need a callback, just some flags
			$tag_list['no_option']['noparse'] = array(
				'html'            => '%1$s',
				'strip_empty'     => true,
				'stop_parse'      => true,
				'disable_smilies' => true
			);

			// [VIDEO]
			$tag_list['option']['video'] = array(
				'callback' => 'handle_bbcode_video',
				'strip_empty'     => true,
				'disable_smilies' => true,
			);

			$tag_list['no_option']['video'] = array(
				'callback'    => 'handle_bbcode_url',
				'strip_empty' => true
			);

			if (($options['allowedbbcodes'] & vB_Api_Bbcode::ALLOW_BBCODE_BASIC) OR $force_all)
			{
				// [B]
				$tag_list['no_option']['b'] = array(
					'html'        => '<b>%1$s</b>',
					'strip_empty' => true
				);

				// [I]
				$tag_list['no_option']['i'] = array(
					'html'        => '<i>%1$s</i>',
					'strip_empty' => true
				);

				// [U]
				$tag_list['no_option']['u'] = array(
					'html'        => '<u>%1$s</u>',
					'strip_empty' => true
				);

				// [H=1]
				$tag_list['option']['h'] = array(
					'callback' => 'handle_bbcode_h',
					'strip_space_after' => 2,
					'strip_empty' => true
				);

				// [TABLE]
				$tag_list['no_option']['table'] = array(
					'callback' => 'parseTableTag',
					'ignore_global_strip_space_after' => true,
					'strip_space_after' => 2,
					'strip_empty' => true
				);

				// [TABLE=]
				$tag_list['option']['table'] = array(
					'callback' => 'parseTableTag',
					'ignore_global_strip_space_after' => true,
					'strip_space_after' => 2,
					'strip_empty' => true
				);

				// [HR]
				$tag_list['no_option']['hr'] = array(
					'html' => '<hr />%1$s',
					'strip_empty' => false
				);

				// [SUB]
				$tag_list['no_option']['sub'] = array(
					'html' => '<sub>%1$s</sub>',
					'strip_empty' => true
				);

				// [SUP]
				$tag_list['no_option']['sup'] = array(
					'html' => '<sup>%1$s</sup>',
					'strip_empty' => true
				);
			}

			if (($options['allowedbbcodes'] & vB_Api_Bbcode::ALLOW_BBCODE_COLOR) OR $force_all)
			{
				// [COLOR=XXX]
				$tag_list['option']['color'] = array(
					'html'         => '<font color="%2$s">%1$s</font>',
					'option_regex' => '#^\#?\w+$#',
					'strip_empty'  => true
				);
			}

			if (($options['allowedbbcodes'] & vB_Api_Bbcode::ALLOW_BBCODE_SIZE) OR $force_all)
			{
				// [SIZE=XXX]
				$tag_list['option']['size'] = array(
					'callback'    => 'handle_bbcode_size',
					'strip_empty'  => true
				);
			}

			if (($options['allowedbbcodes'] & vB_Api_Bbcode::ALLOW_BBCODE_FONT) OR $force_all)
			{
				// [FONT=XXX]
				$tag_list['option']['font'] = array(
					'html'         => '<font face="%2$s">%1$s</font>',
					'option_regex' => '#^[^["`\':]+$#',
					'strip_empty'  => true
				);
			}

			if (($options['allowedbbcodes'] & vB_Api_Bbcode::ALLOW_BBCODE_ALIGN) OR $force_all)
			{
				// [LEFT]
				$tag_list['no_option']['left'] = array(
					'html'              => '<div align="left">%1$s</div>',
					'strip_empty'       => true,
					'strip_space_after' => 1
				);

				// [CENTER]
				$tag_list['no_option']['center'] = array(
					'html'              => '<div align="center">%1$s</div>',
					'strip_empty'       => true,
					'strip_space_after' => 1
				);

				// [RIGHT]
				$tag_list['no_option']['right'] = array(
					'html'              => '<div align="right">%1$s</div>',
					'strip_empty'       => true,
					'strip_space_after' => 1
				);

				// [INDENT]
				$tag_list['no_option']['indent'] = array(
					'html'              => '<blockquote>%1$s</blockquote>',
					'strip_empty'       => true,
					'strip_space_after' => 1
				);
			}

			if (($options['allowedbbcodes'] & vB_Api_Bbcode::ALLOW_BBCODE_LIST) OR $force_all)
			{
				// [LIST]
				$tag_list['no_option']['list'] = array(
					'callback'    => 'handle_bbcode_list',
					'strip_empty' => true
				);

				// [LIST=XXX]
				$tag_list['option']['list'] = array(
					'callback'    => 'handle_bbcode_list',
					'strip_empty' => true
				);

				// [INDENT]
				$tag_list['no_option']['indent'] = array(
					'html'              => '<blockquote>%1$s</blockquote>',
					'strip_empty'       => true,
					'strip_space_after' => 1
				);
			}

			if (($options['allowedbbcodes'] & vB_Api_Bbcode::ALLOW_BBCODE_URL) OR $force_all)
			{
				// [EMAIL]
				$tag_list['no_option']['email'] = array(
					'callback'    => 'handle_bbcode_email',
					'strip_empty' => true
				);

				// [EMAIL=XXX]
				$tag_list['option']['email'] = array(
					'callback'    => 'handle_bbcode_email',
					'strip_empty' => true
				);

				// [URL]
				$tag_list['no_option']['url'] = array(
					'callback'    => 'handle_bbcode_url',
					'strip_empty' => true
				);

				// [URL=XXX]
				$tag_list['option']['url'] = array(
					'callback'    => 'handle_bbcode_url',
					'strip_empty' => true
				);
				
				// [THREAD]
				$tag_list['no_option']['thread'] = array(
					'callback'    => 'handle_bbcode_thread',
					'strip_empty' => true
				);

				// [THREAD=XXX]
				$tag_list['option']['thread'] = array(
					'callback'    => 'handle_bbcode_thread',
					'strip_empty'  => true
				);

				// [POST]
				$tag_list['no_option']['post'] = array(
					'callback'    => 'handle_bbcode_post',
					'strip_empty' => true
				);

				// [POST=XXX]
				$tag_list['option']['post'] = array(
					'callback'    => 'handle_bbcode_post',
					'strip_empty'  => true
				);
				
				// [NODE]
				$tag_list['no_option']['node'] = array(
					'callback'    => 'handle_bbcode_node',
					'strip_empty' => true
				);

				// [NODE=XXX]
				$tag_list['option']['node'] = array(
					'callback'    => 'handle_bbcode_node',
					'strip_empty'  => true
				);

				if (defined('VB_API') AND VB_API === true)
				{
					$tag_list['no_option']['thread']['html'] = '<a href="vb:showthread/t=%1$s">' . $options['bburl'] . '/showthread.php?t=%1$s</a>';
					$tag_list['option']['thread']['html'] = '<a href="vb:showthread/t=%2$s">%1$s</a>';
					$tag_list['no_option']['post']['html'] = '<a href="vb:showthread/p=%1$s">' . $options['bburl'] . '/showthread.php?p=%1$s</a>';
					$tag_list['option']['post']['html'] = '<a href="vb:showthread/p=%2$s">%1$s</a>';
				}
			}

			if (($options['allowedbbcodes'] & vB_Api_Bbcode::ALLOW_BBCODE_PHP) OR $force_all)
			{
				// [PHP]
				$tag_list['no_option']['php'] = array(
					'callback'          => 'handle_bbcode_php',
					'strip_empty'       => true,
					'stop_parse'        => true,
					'disable_smilies'   => true,
					'disable_wordwrap'  => true,
					'strip_space_after' => 2
				);
			}

			if (($options['allowedbbcodes'] & vB_Api_Bbcode::ALLOW_BBCODE_CODE) OR $force_all)
			{
				//[CODE]
				$tag_list['no_option']['code'] = array(
					'callback'          => 'handle_bbcode_code',
					'strip_empty'       => true,
					'disable_smilies'   => true,
					'disable_wordwrap'  => true,
					'strip_space_after' => 2
				);
			}

			if (($options['allowedbbcodes'] & vB_Api_Bbcode::ALLOW_BBCODE_HTML) OR $force_all)
			{
				// [HTML]
				$tag_list['no_option']['html'] = array(
					'callback'          => 'handle_bbcode_html',
					'strip_empty'       => true,
					'stop_parse'        => true,
					'disable_smilies'   => true,
					'disable_wordwrap'  => true,
					'strip_space_after' => 2
				);
			}

			// Legacy Hook 'bbcode_fetch_tags' Removed //
		}
		if ($force_all)
		{
			$tag_list_return = $tag_list;
			$tag_list = $tag_list_bak;
			return $tag_list_return;
		}
		else
		{
			return $tag_list;
		}
	}

	/**
	* Loads any user specified custom BB code tags into the $tag_list
	*/
	protected function fetchCustomTags()
	{
		$customTags = array();

		$bbcodeoptions = vB::getDatastore()->get_value('bf_misc_bbcodeoptions');

		$bbcodes = vB::getDbAssertor()->getRows('bbcode');
		foreach($bbcodes as $customtag)
		{
			$has_option = $customtag['twoparams'] ? 'option' : 'no_option';
			$customtag['bbcodetag'] = strtolower($customtag['bbcodetag']);
			$customTags["$has_option"]["$customtag[bbcodetag]"] = array(
				'html'             => $customtag['bbcodereplacement'],
				'strip_empty'      => (intval($customtag['options']) & $bbcodeoptions['strip_empty']) ? 1 : 0 ,
				'stop_parse'       => (intval($customtag['options']) & $bbcodeoptions['stop_parse']) ? 1 : 0 ,
				'disable_smilies'  => (intval($customtag['options']) & $bbcodeoptions['disable_smilies']) ? 1 : 0 ,
				'disable_wordwrap' => (intval($customtag['options']) & $bbcodeoptions['disable_wordwrap']) ? 1 : 0
			);
		}

		return $customTags;
	}

	protected function fetchBbcodeOptions()
	{
		$options = vB::getDatastore()->get_value('options');

		// Parse Calendar
		$response['calendar'] = array(
			// TODO: we need to obtain this...
//			'dohtml'		=> $calendarinfo['allowhtml'],
//			'dobbcode'		=> $calendarinfo['allowbbcode'],
//			'dobbimagecode' => $calendarinfo['allowimgcode'],
//			'dosmilies'		=> $calendarinfo['allowsmilies']
		);

		// parse private message
		$response['privatemessage'] = array(
			'allowhtml'		 => $options['privallowhtml'],
			'allowbbcode'	 => $options['privallowbbcode'],
			'allowimagecode' => $options['privallowbbimagecode'],
			'allowsmilies'	 => $options['privallowsmilies'],
		);

		// parse non-forum item
		$response['nonforum'] = array(
			'dohtml'		=> $options['allowhtml'],
			'dobbcode'		=> $options['allowbbcode'],
			'dobbimagecode' => $options['allowbbimagecode'],
			'dosmilies'		=> $options['allowsmilies']
		);

		// parse visitor/group/picture message
		$response['visitormessage'] =
		$response['groupmessage']	=
		$response['picturecomment'] =
		$response['socialmessage']	= array(
			'dohtml'		=> $options['allowhtml'],
			'dobbcode'		=> $options['allowbbcode'],
			'dobbimagecode' => true, // this tag can be disabled manually; leaving as true means old usages remain (as documented)
			'dosmilies'		=> $options['allowsmilies']
		);

		return $response;
	}

	protected function fetchCensorshipInfo()
	{
		$options = vB::getDatastore()->get_value('options');

		$response = array('words'=>array(), 'char'=>$options['censorchar']);

		if ($options['enablecensor'])
		{
			$censorwords = preg_quote($options['censorwords'], '#');
			$response['words'] = preg_split('#[ \r\n\t]+#', $censorwords, -1, PREG_SPLIT_NO_EMPTY);
		}

		return $response;
	}

	public function fetchSmilies()
	{
		$smilieCache['html'] = $smilieCache['no_html'] = array();

		if ($smilies = vB::getDatastore()->get_value('smiliecache'))
		{
			// we can get the smilies from the smiliecache datastore
			DEVDEBUG('returning smilies from the datastore');

			return $smilies;
		}
		else
		{
			// we have to get the smilies from the database
			DEVDEBUG('querying for smilies');

			return vB::getDbAssertor()->getRows('fetchSmilies', array(vB_dB_Query::QUERYTYPE_KEY=>  vB_dB_Query::QUERY_STORED));
		}
	}

	/** Extracts the video and photo content from text.
	 *
	 *	@param	string
	 *
	 *	@return	mixed	array of 'url', 'provider', 'code'
	 **/
	public function extractVideo($rawtext)
	{
		$videos = array();
		$filter = '~\[video.*\[\/video~i';
		$matches = array();
		$count = preg_match_all($filter, $rawtext, $matches);

		if ($count > 0 )
		{
			foreach ($matches[0] as $match)
			{
				$pos = strpos($match,']');
				if ($pos)
				{
					$codes = substr($match,7, $pos -7);
					$codes = explode(';', $codes);
					if (count($codes) > 1)
					{
						$url = substr($match,$pos + 1, -7);

						if (!empty($url))
						{
							//we have all the necessary variables.
							$videos[] = array(
								'url' => $url,
								'provider' => $codes[0],
								'code' => $codes[1],
							);
						}
					}
				}
			}
			return $videos;
		}
		else
		{
			return 0;
		}

	}

	/**
	 * Parses HTML produced by a WYSIWYG editor and produces the corresponding BBCode formatted text
	 *
	 * @param	string	HTML text
	 *
	 * @return	string	BBCode text
	 */
	public function parseWysiwygHtmlToBbcode($text)
	{
		$wysiwyg = new vB_WysiwygHtmlParser();
		return $wysiwyg->parseWysiwygHtmlToBbcode($text);
	}

	/*
	 * Convert text from an editor into text ready to be saved with bbcode converted
	 *
	 * @param	string	Text to convert
	 * @param	array	Options
	 *					- autoparselinks
	 *
	 * @return	string	Converted Text
	 */
	public function convertWysiwygTextToBbcode($text, $options)
	{
		$text = $this->parseWysiwygHtmlToBbcode($text);

		require_once(DIR . '/includes/functions_video.php');
		$text = parse_video_bbcode($text);
		if (!empty($options['autoparselinks']))
		{
			$text = $this->convertUrlToBbcode($text);
		}

		return $text;
	}

	/*
	 * Converts urls into bbcode with [url]
	 *
	 * @param	string	Text containing url
	 *
	 * @return	string	Converted text
	 */
	public function convertUrlToBbcode($messagetext)
	{
		$datastore = vB::getDatastore();
		$bbcodecache = $datastore->getValue('bbcodecache');
		$bbcodeoptions = $datastore->getValue('bf_misc_bbcodeoptions');

		// areas we should attempt to skip auto-parse in
		$skiptaglist = 'url|email|code|php|html|noparse';

		if (!isset($bbcodecache))
		{
			$bbcodecache = array();

			$bbcodes = vB::getDbAssertor()->assertQuery('bbcode', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT
			));
			foreach ($bbcodes as $customtag)
			{
				$bbcodecache["$customtag[bbcodeid]"] = $customtag;
			}
		}

		foreach ($bbcodecache AS $customtag)
		{
			if (intval($customtag['options']) & $bbcodeoptions['stop_parse'] OR intval($customtag['options']) & $bbcodeoptions['disable_urlconversion'])
			{
				$skiptaglist .= '|' . preg_quote($customtag['bbcodetag'], '#');
			}
		}

		return preg_replace(
			'#(^|\[/(' . $skiptaglist . ')\])(.*(\[(' . $skiptaglist . ')\]|$))#siUe',
			"\$this->convertUrlToBbcodeCallback('\\3', '\\1')",
			$messagetext
		);
	}

	/**
	* Callback function for convert_url_to_bbcode
	*
	* @param	string	Message text
	* @param	string	Text to prepend
	*
	* @return	string
	*/
	private function convertUrlToBbcodeCallback($messagetext, $prepend)
	{
		$datastore = vB::getDatastore();
		$bbcodecache = $datastore->getValue('bbcodecache');
		$bbcodeoptions = $datastore->getValue('bf_misc_bbcodeoptions');

		// the auto parser - adds [url] tags around neccessary things
		$messagetext = str_replace('\"', '"', $messagetext);
		$prepend = str_replace('\"', '"', $prepend);

		static $urlSearchArray, $urlReplaceArray, $emailSearchArray, $emailReplaceArray;
		if (empty($urlSearchArray))
		{
			$taglist = '\[b|\[i|\[u|\[left|\[center|\[right|\[indent|\[quote|\[highlight|\[\*' .
				'|\[/b|\[/i|\[/u|\[/left|\[/center|\[/right|\[/indent|\[/quote|\[/highlight';

			foreach ($bbcodecache AS $customtag)
			{
				if (!(intval($customtag['options']) & $bbcodeoptions['disable_urlconversion']))
				{
					$customtag_quoted = preg_quote($customtag['bbcodetag'], '#');
					$taglist .= '|\[' . $customtag_quoted . '|\[/' . $customtag_quoted;
				}
			}

			$urlSearchArray = array(
				'#(^|(?<=[^_a-z0-9-=\]"\'/@]|(?<=' . $taglist . ')\]))((https?|ftp|gopher|news|telnet)://|www\.)((\[(?!/)|[^\s[^$`"{}<>])+)(?!\[/url|\[/img)(?=[,.!\')]*(\)\s|\)$|[\s[]|$))#siU'
			);

			$urlReplaceArray = array(
				"[url]\\2\\4[/url]"
			);

			$emailSearchArray = array(
				'/([ \n\r\t])([_a-z0-9-+]+(\.[_a-z0-9-+]+)*@[^\s]+(\.[a-z0-9-]+)*(\.[a-z]{2,6}))/si',
				'/^([_a-z0-9-+]+(\.[_a-z0-9-+]+)*@[^\s]+(\.[a-z0-9-]+)*(\.[a-z]{2,6}))/si'
			);

			$emailReplaceArray = array(
				"\\1[email]\\2[/email]",
				"[email]\\0[/email]"
			);
		}

		$text = preg_replace($urlSearchArray, $urlReplaceArray, $messagetext);
		if (strpos($text, "@"))
		{
			$text = preg_replace($emailSearchArray, $emailReplaceArray, $text);
		}

		return $prepend . $text;
	}

	public function hasBbcode($text)
	{
		$tags_list = $this->fetchTagList();
		$pattern = '#\[(' . implode('|', array_keys($tags_list['option'] + $tags_list['no_option'])) . ')[^\]]*\]#siU';
		if (preg_match($pattern, $text))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @deprecated
	 * Please use getSignatureInfo
	 * Fetches and parses to html a user's signature
	 * @param int $userid
	 * @param string $signature optionally pass the signature to avoid fetching it again
	 * @return string the parsed (html) signature
	 */
	public function parseSignature($userid, $signature = false)
	{
		if ($userid > 0)
		{
			$sigInfo = $this->getSignatureInfo($userid, $signature);
			return $sigInfo['signature'];
		}
		else
		{
			return '';
		}
	}
	/**
	 * Fetches and parses to html a user's signature
	 * @param int $userid
	 * @param string $signature optionally pass the signature to avoid fetching it again
	 * @return array
	 * 			signature => the parsed (html) signature
	 * 			allowed => list of tags the user has ('can') and doesn't (disabled) have permission to use
	 */
	public function getSignatureInfo($userid, $signature = false)
	{
		$userid = intval($userid);
		$cacheKey = "vbSig_$userid";
		$cachePermKey = "vbSigPerm_$userid";
		$cache = vB_Cache::instance(vB_Cache::CACHE_STD);
		$cached_signature = $cache->read($cacheKey);
		$cached_perms = $cache->read($cachePermKey);
		if ($cached_signature !== false AND $cached_perms !== false)
		{
			return array('signature' => $cached_signature, 'allowed' => $cached_perms['can'], 'disabled' => $cached_perms['cant']);
		}
		if (empty($signature))
		{
			$sigInfo =  vB_Api::instanceInternal('user')->fetchSignature($userid);
			if (empty($sigInfo) OR empty ($sigInfo['raw']))
			{
				$sigInfo['raw'] = '';
			}
			$signature = $sigInfo['raw'];
		}
		require_once(DIR . '/includes/class_sigparser.php');
		$sig_parser = new vB_SignatureParser(vB::get_registry(), $this->fetchTagList(), $userid);
		// Parse the signature
		$parsed = $sig_parser->parse($signature);
		$cache->write($cacheKey, $parsed, 1440, "userChg_$userid");
		$perms = $sig_parser->getPerms();
		$cache->write($cachePermKey, $perms, 1440, "userChg_$userid");
		return array('signature' => $parsed, 'allowed' => $perms['can'], 'disabled' => $perms['cant']);
	}
}
