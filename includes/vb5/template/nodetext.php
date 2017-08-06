<?php

class vB5_Template_NodeText
{
	const PLACEHOLDER_PREFIX = '<!-- ##nodetext_';
	const PLACEHOLDER_SUFIX = '## -->';

	protected static $instance;
	protected $cache = array();
	protected $pending = array();
	protected $bbCodeOptions = array();
	protected $placeHolders = array();

	public static function instance()
	{
		if (!isset(self::$instance))
		{
			$c = __CLASS__;
			self::$instance = new $c;
		}

		return self::$instance;
	}

	public function register($nodeId, $bbCodeOptions = array())
	{
		//  + VBV-3236 Add usergroup based permissions here for images 
		$cangetattachments =  Api_InterfaceAbstract::instance()->callApi(
			'user', 'hasPermissions', 
			array(
				'group' => 'forumpermissions',
				'permission' => 'cangetattachment', 
				'nodeid' => $nodeId,
			)
		);
		$bbCodeOptions += array('allowimages' => $cangetattachments > 0); // Use += array() to prevent from it overwrites "allowimages" which is set in $bbCodeOptions parameter
		// - VBV-3236

		$placeHolder = $this->getPlaceholder($nodeId, $bbCodeOptions);

		$this->pending[$placeHolder] = $nodeId;
		$cacheKey = $this->getCacheKey($nodeId, $bbCodeOptions);
		$this->placeHolders[$cacheKey] = $placeHolder;
		$this->bbCodeOptions[$placeHolder] = $bbCodeOptions;

		return $placeHolder;
	}

	public function resetPending()
	{
		$this->pending = array();
	}

	public function replacePlaceholders(&$content)
	{
		$this->fetchNodeText();

		foreach($this->cache AS $placeHolder => $replace)
		{

			$content = str_replace($placeHolder, $replace, $content);
		}

	}

	protected function getPlaceholder($nodeId, $bbCodeOptions)
	{
		if (empty($bbCodeOptions))
		{
			return self::PLACEHOLDER_PREFIX . $nodeId . self::PLACEHOLDER_SUFIX;
		}
		ksort($bbCodeOptions);
		return self::PLACEHOLDER_PREFIX . $nodeId. ':'  . serialize($bbCodeOptions) . self::PLACEHOLDER_SUFIX;
	}

	/**
	 * Returns the cache key to be used by vB_Cache
	 * @param type $nodeId
	 * @return string
	 */
	protected function getCacheKey($nodeId, $bbCodeOptions)
	{
		$styleId = vB5_Template_Stylevar::instance()->getPreferredStyleId();
		$languageId = vB5_User::getLanguageId();
		$cacheKey = "vbNodeText{$nodeId}_{$styleId}_{$languageId}";
		if (!empty($bbCodeOptions))
		{
			ksort($bbCodeOptions);
			$cacheKey .= ':' . md5(serialize($bbCodeOptions));
		}

		return strtolower($cacheKey);
	}

	protected function extractNodeIdFromKey($cacheKey)
	{
		//If we passed in bbcode options we need to trim the end.
		$end = strpos($cacheKey, ':');

		if ($end)
		{
			$cacheKey = substr($cacheKey, 0, $end);
		}

		return filter_var($cacheKey, FILTER_SANITIZE_NUMBER_INT);
	}

	protected function fetchNodeText()
	{
		if (!empty($this->placeHolders))
		{
			// first try with cache
			$api = Api_InterfaceAbstract::instance();
			$cache = $api->cacheInstance(0);
			$found = $cache->read(array_keys($this->placeHolders));

			if (!empty($found))
			{
				$foundValues = array();
				foreach($found AS $cacheKey => $parsedText)
				{

					if ($parsedText !== false)
					{
						$nodeId = $this->extractNodeIdFromKey($cacheKey);
						$placeHolder = $this->placeHolders[$cacheKey];
						$this->cache[$placeHolder] = $parsedText;
						unset($this->placeHolders[$cacheKey]);
					}
				}
			}

			if (!empty($this->placeHolders))
			{
				$missing = array();
				foreach ($this->placeHolders AS $placeHolder)
				{
					if (isset($this->pending[$placeHolder]))
					{
						$missing[] = $this->pending[$placeHolder];
					}
				}
				// we still have to parse some nodes, fetch data for them
				$textDataArray =  Api_InterfaceAbstract::instance()->callApi('content_text', 'getDataForParse', array($missing));
				$templateCache = vB5_Template_Cache::instance();
				$phraseCache = vB5_Template_Phrase::instance();
				$urlCache = vB5_Template_Url::instance();

				// In BBCode parser, the templates of inner BBCode are registered first,
				// so they should be replaced after the outer BBCode templates. See VBV-4834.
				$templateCache->setRenderTemplatesInReverseOrder(true);

				foreach($this->placeHolders AS $cacheKey => $placeHolder)
				{
					$nodeId = isset($this->pending[$placeHolder]) ? $this->pending[$placeHolder] : 0;

					if ($nodeId AND !empty($textDataArray[$nodeId]))
					{
						$textData = $textDataArray[$nodeId];
						$parser = new vB5_Template_BbCode();
						$parser->setAttachments($textData['attachments']);
						//make sure we have values for all the necessary options
						foreach(array('allowimages', 'allowimagebbcode', 'allowbbcode', 'allowhtml', 'allowsmilies') as $option)
						{
							if (!empty($this->bbCodeOptions[$placeHolder]) AND isset($this->bbCodeOptions[$placeHolder][$option]))
							{
								$textData['bbcodeoptions'][$option] = $this->bbCodeOptions[$placeHolder][$option];
							}
							else if (!isset($textData['bbcodeoptions'][$option]))
							{
								$textData['bbcodeoptions'][$option] = false;
							}
						}
						$allowimages = false;

						if (!empty($this->bbCodeOptions[$placeHolder]) AND !empty($this->bbCodeOptions[$placeHolder]['allowimages']))
						{
							$allowimages = $this->bbCodeOptions[$placeHolder]['allowimages'];
						}
						else if (!empty($textData['bbcodeoptions']['allowimages']))
						{
							$allowimages = $textData['bbcodeoptions']['allowimages'];
						}
						elseif (!empty($textData['bbcodeoptions']['allowimagecode']))
						{
							$allowimages = $textData['bbcodeoptions']['allowimagecode'];
						}

						$parsed = $parser->doParse(
							$textData['rawtext'],
							$textData['bbcodeoptions']['allowhtml'],
							$textData['bbcodeoptions']['allowsmilies'],
							$textData['bbcodeoptions']['allowbbcode'],
							$allowimages,
							true, // do_nl2br
							false, // cachable
							$textData['htmlstate']
						);

						// We need to call this here, so that we don't have placeholders in cache.
						// It's safe to do it here cause we already are in delayed rendering.
						$templateCache->replacePlaceholders($parsed);
						$phraseCache->replacePlaceholders($parsed);
						$urlCache->replacePlaceholders($parsed);

						//cache for a week
						$events = array('nodeChg_' . $nodeId);

						// need to update cache if channel change options

						$events[] = 'nodeChg_' . $textData['channelid'];

						$cache->write($cacheKey, $parsed, 10080, $events);

						if ($parsed !== false)
						{
							$this->cache[$placeHolder] = $parsed;
						}
					}
				}

				$templateCache->setRenderTemplatesInReverseOrder(false);
			}
		}
	}
}
