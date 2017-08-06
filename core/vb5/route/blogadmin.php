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

class vB5_Route_Blogadmin extends vB5_Route
{
	const CONTROLLER = 'page';
	const DEFAULT_PREFIX = 'blogadmin';
	const REGEXP = 'blogadmin/(?P<nodeid>([0-9]+)*)(?P<title>(-[^!@\\#\\$%\\^&\\*\\(\\)\\+\\?/:;"\'\\\\,\\.<>= _]*)*)(/?)(?P<action>([a-z^/]*)*)';
	protected static $createActions = array('settings', 'permissions', 'contributors', 'sidebar', 'invite');
	protected static $adminActions = array('settings', 'permissions', 'contributors', 'owner', 'sidebar', 'subscribers', 'invite', 'stats', 'delete');
	protected $title;
	protected static $actionKey = 'blogaction';


	/** constructor needs to check for valid data and set the arguments.
	*
	*	@param	mixed
	* 	@param	mixed
	* 	@param	string
	**/
	public function __construct($routeInfo, $matches, $queryString = '', $anchor = '')
	{

		parent::__construct($routeInfo, $matches, $queryString = '', $anchor = '');

		if (!empty($matches))
		{
			foreach ($matches as $key => $match)
			{
				//if we were passed routeInfo, skip it.
				if ($key == 'nodeid')
				{

					$this->arguments['nodeid'] = $routeInfo['arguments']['nodeid'] = $match;
				}
				else if ($key == self::$actionKey)
				{
					$action = explode('/', $match);
					$this->arguments[self::$actionKey] = $routeInfo['arguments'][self::$actionKey] = $action[0];
					if (count($action) > 1)
					{
						$this->arguments['action2'] = $routeInfo['arguments']['action2'] = $action[1];
					}
				}
				else if ($key == 'action2')
				{
					$this->arguments['action2'] = $routeInfo['arguments']['action2'] = $match;
				}
			}
		}

		//check for valid input.
		if (! self::validInput($routeInfo['arguments']))
		{
			throw new Exception('invalid_page_url');
		}

	}

	/**
	 * Checks if route info is valid and performs any required sanitation
	 *
	 * @param array $data
	 * @return bool Returns TRUE iff data is valid
	 */
	protected static function validInput(array &$data)
	{
		//if we have nothing we set actions to create, settings
		//if we have a channelid and no action1 or 2 we set actions to create, settings.
		//if we have no channelid and anything but create, settings then we throw an exception
		// if no action is defined, use index
		if (empty($data[self::$actionKey]))
		{
			$data[self::$actionKey] = 'create';
		}

		if (empty($data['action2']))
		{
			$data['action2'] = 'settings';
		}

		if (!isset($data['guid']) OR empty($data['guid']))
		{
			$data['guid'] = vB_Xml_Export_Route::createGUID($data);
		}

		if ($data[self::$actionKey] == 'admin')
		{
			return (isset($data['nodeid']) AND in_array($data['action2'], self::$adminActions));
		}

		if ($data[self::$actionKey] == 'create')
		{
			return in_array($data['action2'], self::$createActions);
		}

		return false;
	}

	public static function exportArguments($arguments)
	{
		$data = unserialize($arguments);

		$page = vB::getDbAssertor()->getRow('page', array('pageid' => $data['pageid']));
		if (empty($page))
		{
			throw new Exception('Couldn\'t find page');
		}
		$data['pageGuid'] = $page['guid'];
		unset($data['pageid']);
		$data['nodeid'] = 0;
		$data[self::$actionKey] = 'create';
		$data['action2'] = 'settings';

		return serialize($data);
	}

	public static function importArguments($arguments)
	{
		$data = unserialize($arguments);

		$page = vB::getDbAssertor()->getRow('page', array('guid' => $data['pageGuid']));
		if (empty($page))
		{
			throw new Exception('Couldn\'t find page');
		}
		$data['pageid'] = $page['pageid'];
		unset($data['pageGuid']);

		if (!isset($data['nodeid']))
		{
			$data['nodeid'] = 0;
		}

		if (!isset($data[self::$actionKey]))
		{
			$data[self::$actionKey] = 'create';
		}

		if (!isset($data['action2']))
		{
			$data['action2'] = 'settings';
		}
		return serialize($data);
	}

	public static function importContentId($arguments)
	{
		return $arguments['pageid'];
	}


	/** Returns the canonical url
	*
	*
	**/
	public function getCanonicalUrl()
	{
		$url = '/' . self::DEFAULT_PREFIX;

		if (!empty($this->arguments['nodeid']))
		{

			if (empty($this->title))
			{
				$node = vB_Library::instance('node')->getNodeBare($this->arguments['nodeid']);
				$this->title = self::prepareTitle($node['title']);
			}
			$url .= '/' . $this->arguments['nodeid'] . '-' . $this->title;
		}

		$url .= '/' . $this->arguments[self::$actionKey] . '/' . $this->arguments['action2'];
		return $url;
	}
	//Returns the Url
	public function getUrl()
	{
		$url = $this->getCanonicalUrl();
		
		if (strtolower(vB_String::getCharset()) != 'utf-8')
		{
			$url = vB_String::encodeUtf8Url($url);
		}
		
		return $url;
	}

	/**
	 * Build URLs using a single instance for the class. It does not check permissions
	 * @param string $className
	 * @param array $URLInfoList
	 *				- route
	 *				- data
	 *				- extra
	 *				- anchor
	 *				- options
	 * @return array
	 */
	protected static function bulkFetchUrls($className, $URLInfoList)
	{
		$results = array();
		
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		
		foreach($URLInfoList AS $hash => $info)
		{
			try
			{
				// we need different instances, since we need to instantiate different action classes
				$route = new $className($info['routeInfo'], $info['data'], http_build_query($info['extra']), $info['anchor']);

				$options = explode('|', $info['route']);
				$routeId = $options[0];

				$fullURL = $route->getFullUrl($options);
				$cache->write($info['innerHash'], $fullURL, 1440, array('routeChg_' . $routeId));
			}
			catch (Exception $e)
			{
				$fullURL = '';
			}
			
			$results[$hash] = $fullURL;
		}
		
		return $results;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
