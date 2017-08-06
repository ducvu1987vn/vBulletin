<?php

class vB5_Template_Url
{
	const PLACEHOLDER_PREFIX = '!!VB:URL';
	const PLACEHOLDER_SUFIX = '!!';

	protected static $instance;	
	protected $delayedUrlInfo = array();
	protected $loadedUrlKeys = false;

	/**
	 * 
	 * @return vB5_Template_Url
	 */
	public static function instance()
	{
		if (!isset(self::$instance))
		{
			$c = __CLASS__;
			self::$instance = new $c;
		}

		return self::$instance;
	}
	
	protected function getPlaceholder($hash)
	{
		return self::PLACEHOLDER_PREFIX . $hash . self::PLACEHOLDER_SUFIX;
	}
	
	public function register($route, $data = array(), $extra = array(), $options = array())
	{
		if (empty($data))
		{
			$data = array();
		}
		else if (!is_array($data))
		{
			throw new vB5_Exception_Api('route', 'getUrl', $data, 'Invalid data for URL creation');
		}

		if (empty($extra))
		{
			$extra = array();
		}
		else if (!is_array($extra))
		{
			throw new vB5_Exception_Api('route', 'getUrl', $extra, 'Invalid extra data for URL creation');
		}
		//Most of the time we have a node record. Let's keep just what we need;
		if (!empty($data['nodeid']) AND isset($data['contenttypeid']))
		{
			//Let's unset the values we don't need
			foreach ($data AS $key => $field)
			{
				if (!in_array($key, array('title', 'startertitle', 'channeltitle', 'urlident','starterurlident', 'channelurlident',
					'page', 'channelroute','starterroute' , 'contenttypeid', 'starter', 'channelid', 'nodeid', 'routeid', 'userid', 'authorname')))
				{
					unset($data[$key]);
				}
			}
		}

		if (vB5_Cookie::isEnabled())
		{
			// session is stored in cookies, so do not append it to url
			$route .= '|nosession';
		}

		$hash = md5($route . serialize($data). serialize($extra).serialize($options));

		//We often call for the same url more than once on the page. Don't do this more than once.
		$replaceStr = $this->getPlaceholder($hash);
		if (empty($this->delayedUrlInfo[$replaceStr]))
		{
			$this->delayedUrlInfo[$replaceStr] = array('route' => $route, 'data' => $data, 'extra' =>$extra, 'options' =>$options);
		}
		elseif (!empty($data) AND empty($this->delayedUrlInfo[$replaceStr]['data']))
		{
			$this->delayedUrlInfo[$replaceStr]['data'] = $data;
		}
		return $replaceStr;
	}
	
	protected function fetchDelayedUrlKeys()
	{
		return array_keys($this->delayedUrlInfo);
	}

	/**
	 * Returns the URLs for the routes with the passed parameters
	 * @param $delayedUrls list of URL definitions:
	 * [route] - Route identifier (routeid or name)
	 * [$data] - Data for building route
	 * [extra] - Additional data to be added
	 * [options] - Options for building URL
	 *					- noBaseUrl: skips adding the baseurl
	 * @return type
	 * @throws vB5_Exception_Api
	 */
	public function finalBuildUrls($delayedUrls)
	{
		// the only reason for this method to be public is that it is required in vB5_Frontend_Controller_Page::index
		// todo: check if we can avoid this
		
		if (!$this->loadedUrlKeys)
		{
			$urlIds = array();
			foreach($this->delayedUrlInfo as $urlInfo)
			{
				$options = explode('|', $urlInfo['route']);
				$urlIds[] = $options[0];
			}
			$check = Api_InterfaceAbstract::instance()->callApi('route', 'preloadRoutes', array('routeid'=> array_unique($urlIds)));
			$this->loadedUrlKeys = true;
		}
		$addBaseURLs = array();
		foreach ($delayedUrls as $hashKey)
		{
			if (!isset($this->delayedUrlInfo[$hashKey]))
			{
				$missing_replacements[$hashKey] = '#';
			}

			$info[$hashKey]['route'] = $this->delayedUrlInfo[$hashKey]['route'];
			$info[$hashKey]['data'] = $this->delayedUrlInfo[$hashKey]['data'];
			$info[$hashKey]['extra'] = $this->delayedUrlInfo[$hashKey]['extra'];
			$info[$hashKey]['options'] = $this->delayedUrlInfo[$hashKey]['options'];
			if (empty($info[$hashKey]['options']['noBaseUrl']))
			{
				$addBaseURLs[] = $hashKey;
			}

		}

		$replacements = Api_InterfaceAbstract::instance()->callApi('route', 'getUrls', array('info' => $info));

		if (!empty($missing_replacements))
		{
			$replacements += $missing_replacements;
		}

		$config = vB5_Config::instance();

		foreach ($addBaseURLs as $hashKey)
		{
			$replacements[$hashKey] = $config->baseurl . $replacements[$hashKey];
		}

		return $replacements;
	}
	
	public function replacePlaceholders(&$content)
	{
		$delayedUrls = $this->fetchDelayedUrlKeys() ;

		if (!empty($delayedUrls))
		{
			$replacements = $this->finalBuildUrls($delayedUrls);
		}

		if (!empty($replacements))
		{
			$content = str_replace(array_keys($replacements),$replacements, $content);
		}
	}
}
