<?php

class vB5_Frontend_Controller_Bbcode extends vB5_Frontend_Controller
{

	protected static $needDebug = false;

	function __construct()
	{
		parent::__construct();
	}

	public static function parse($text, $options = array(), $attachments = array(), $cacheInfo = array())
	{
		//if we have a nodeid, let's try to cache this.
		if (!empty($cacheInfo))
		{
			//TODO- Find a caching method that doesn't break collapsed mode.
			if (!empty($cacheInfo['nodeid']))
			{
				$cacheKey = 'vbNodeText' . $cacheInfo['nodeid'];
			}
			else if (!empty($cacheInfo['signatureid']))
			{
				$cacheKey = 'vbSig' . $cacheInfo['signatureid'];
			}
			if (!empty($cacheKey))
			{
				$cacheKey .= strval($options);
				$parsed = vB_Cache::instance()->read($cacheKey);

				if ($parsed)
				{
					return $parsed;
				}
			}
		}
		$result = self::parseInternal(new vB5_Template_BbCode(), $text, $options, $attachments);

		if (!empty($cacheKey))
		{
			if (!empty($cacheInfo['nodeid']))
			{
				$cacheEvent = 'nodeChg_' . $cacheInfo['nodeid'];
			}
			else if (!empty($cacheInfo['signatureid']))
			{
				$cacheEvent = 'userChg_' . $cacheInfo['signatureid'];
			}
			vB_Cache::instance()->write($cacheKey, $result, 86400, $cacheEvent);
		}
		return $result;
	}

	public static function parseWysiwyg($text, $options = array(), $attachments = array())
	{
		return self::parseInternal(new vB5_Template_BbCode_Wysiwyg(), $text, $options, $attachments);
	}

	public static function verifyImgCheck($text, $options = array())
	{
		$parsed = self::parseWysiwygForImages($text, $options);
		$vboptions = vB5_Template_Options::instance()->getOptions();
		if ($vboptions['options']['maximages'])
		{
			$imagecount = substr_count(strtolower($parsed), '<img');
			if ($imagecount > $vboptions['options']['maximages'])
			{
				return array('toomanyimages', $imagecount, $vboptions['options']['maximages']);
			}
		}
		return true;
	}

	public static function parseWysiwygForImages($text, $options = array())
	{
		$api = Api_InterfaceAbstract::instance();
		$text = $api->callApi('bbcode', 'convertWysiwygTextToBbcode', array($text, array('autoparselinks' => false)));
		$parser = new vB5_Template_BbCode_Imgcheck();

		if (!isset($options['allowhtml']))
		{
			$options['allowhtml'] = false;
		}
		if (!isset($options['allowsmilies']))
		{
			$options['allowsmilies'] = true;
		}
		if (!isset($options['allowbbcode']))
		{
			$options['allowbbcode'] = true;
		}
		if (!isset($options['allowimagebbcode']))
		{
			$options['allowimagebbcode'] = true;
		}

		return $parser->doParse($text, $options['allowhtml'], $options['allowsmilies'], $options['allowbbcode'], $options['allowimagebbcode']);
	}

	public static function parseWysiwygForPreview($text, $options = array(), $attachments = array())
	{
		$api = Api_InterfaceAbstract::instance();
		$text = $api->callApi('bbcode', 'parseWysiwygHtmlToBbcode', array($text));
		$parser = new vB5_Template_BbCode();

		if (!isset($options['allowhtml']))
		{
			$options['allowhtml'] = false;
		}
		if (!isset($options['allowsmilies']))
		{
			$options['allowsmilies'] = true;
		}
		if (!isset($options['allowbbcode']))
		{
			$options['allowbbcode'] = true;
		}
		if (!isset($options['allowimagebbcode']))
		{
			$options['allowimagebbcode'] = true;
		}
		if (isset($options['userid']))
		{
			$parser->setParseUserinfo($options['userid']);
		}

		$parser->setAttachments($attachments);

		$templateCache = vB5_Template_Cache::instance();
		$phraseCache = vB5_Template_Phrase::instance();

		// In BBCode parser, the templates of inner BBCode are registered first,
		// so they should be replaced after the outer BBCode templates. See VBV-4834.
		$templateCache->setRenderTemplatesInReverseOrder(true);

		// Parse the bbcode
		$result = $parser->doParse($text, $options['allowhtml'], $options['allowsmilies'], $options['allowbbcode'], $options['allowimagebbcode']);

		$templateCache->replacePlaceholders($result);
		$phraseCache->replacePlaceholders($result);
		$templateCache->setRenderTemplatesInReverseOrder(false);

		return $result;
	}

	private static function parseInternal(vB5_Template_BbCode $parser, $text, $options = array(), $attachments = array())
	{
		if (!isset($options['allowhtml']))
		{
			$options['allowhtml'] = false;
		}

		if (!isset($options['allowsmilies']))
		{
			$options['allowsmilies'] = true;
		}

		if (!isset($options['allowbbcode']))
		{
			$options['allowbbcode'] = true;
		}

		if (!isset($options['allowimagebbcode']))
		{
			$options['allowimagebbcode'] = true;
		}

		if (isset($options['userid']))
		{
			$parser->setParseUserinfo($options['userid']);
		}

		$parser->setAttachments($attachments);

		// Parse the bbcode
		$result = $parser->doParse($text, $options['allowhtml'], $options['allowsmilies'], $options['allowbbcode'], $options['allowimagebbcode']);

		return $result;
	}

	function evalCode($code)
	{
		ob_start();
		eval($code);
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}

	public function actionResolveIp($ip)
	{
		return @gethostbyaddr($ip);
	}

	/** parse the text table's rawtext field. At this point we just register. We do the parse and replace later in a block

	 	@param	int		the nodeid
	 * @param	mixed	array of bbcode options
	 *
	 * @return	string
	 */
	public function parseNodeText($nodeid, $bbCodeOptions  = array())
	{
		if (empty($nodeid))
		{
			return '';
		}

		return vB5_Template_NodeText::instance()->register($nodeid, $bbCodeOptions);
	}


	/** Compare two arrays, and merge any non-zero values of the second into the first. Intended for permissions merging. Must be string => integer members

		@param		mixed	array of $string => integer values
	*	@param		mixed	array of $string => integer values
	*
	*	@return		mixed	array of $string => integer values
	 */
	public function actionMergePerms($currPerms = array(), $addPerms = array())
	{
		if (is_array($currPerms) AND is_array($addPerms))
		{
			foreach($addPerms AS $permName => $permValue)
			{
				if (is_string($permName) AND (is_numeric($permValue) OR is_bool($permValue)) AND empty($currPerms[$permName]) AND ($permValue > 0))
				{
					$currPerms[$permName] = $permValue;
				}
			}
		}
		return $currPerms;
	}

	/** returns a placeholder for the debug information.
	 *
	 * @return	string
	 */
	public static function debugInfo()
	{
		self::$needDebug = true;
		return '<!-DebugInfo-->';
	}

	/** Returns the flag saying whether we should add debug information
	 *
	 *	@return		bool
	 **/
	public static function needDebug()
	{
		return self::$needDebug;
	}
}
