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

class vB5_Route
{
	const DEFAULT_CLASS = 'vB5_Route';
	const PREFIX_MAXSIZE = 200;
	const REGEX_MAXSIZE = 400;

	protected $routeId;
	protected $routeGuid;

	/**
	 * Current route Id for current for request
	 */
	protected $redirect301;
	/**
	 * Prefix for the route. Always encoded in UTF-8.
	 * @var string
	 */
	protected $prefix;
	/**
	 * Regular expression to be matched by URL. Always encoded in UTF-8.
	 * @var string
	 */
	protected $regex;
	/**
	 * (Optional) Stores controller to be called
	 * @var string
	 */
	protected $controller;
	/**
	 * (Optional) Stores action to be invoked in controller
	 * @var string
	 */
	protected $action;
	/**
	 * (Optional) Stores template id to be loaded
	 *
	 * @var string
	 */
	protected $template;
	/**
	 * Contains parameters stored in db and extracted from URL
	 * @var array
	 */
	protected $arguments;
	/**
	 * Contains the matches passed to the class
	 * @var array
	 */
	protected $matches;
	/**
	 * Contains query string parameters
	 * @var array
	 */
	protected $queryParameters;
	/**
	 * Contains anchor id
	 * @var string
	 */
	protected $anchor;

	/**
	 * Contains the page key for preloading cache
	 * @var string
	 */
	protected $pageKey = FALSE;

	/**
	 * Stores user action associated to the route.
	 * The route class cannot register this action because
	 * we don't know whether we are parsing a URL or just displaying a link.
	 * @var mixed
	 */
	protected $userAction = FALSE;

	/**
	 * @var vB5_Route
	 */
	protected $canonicalRoute;

	/**
	 * Contains the breadcrumbs for header
	 * @var array
	 */
	protected $breadcrumbs;

	protected static $routeidentcache = array();

	protected function __construct($routeInfo, $matches, $queryString = '', $anchor = '')
	{
		$this->initRoute($routeInfo, $matches, $queryString, $anchor);
	}
	
	protected function initRoute($routeInfo, $matches, $queryString = '', $anchor = '')
	{
		$this->matches = $matches;
		$this->routeId = $routeInfo['routeid'];
		$this->routeGuid = isset($routeInfo['guid']) ? $routeInfo['guid'] : '';
		$this->arguments = $this->queryParameters = array();
		$this->redirect301 = isset($routeInfo['redirect301']) ? $routeInfo['redirect301'] : FALSE;
		$this->prefix = $routeInfo['prefix'];
		$this->regex = $routeInfo['regex'];

		// set field defaults
		if (!isset($routeInfo['controller']))
		{
			$routeInfo['controller'] = '';
		}

		if (!isset($routeInfo['action']))
		{
			$routeInfo['action'] = '';
		}

		if (!isset($routeInfo['template']))
		{
			$routeInfo['template'] = '';
		}

		if (empty($routeInfo['arguments']))
		{
			$routeInfo['arguments'] = array();
		}

		if (isset($routeInfo['contentid']))
		{
			$routeInfo['contentid'] = (int) $routeInfo['contentid'];
			if ($routeInfo['contentid'] > 0)
			{
				$routeInfo['arguments']['contentid'] = $routeInfo['contentid'];
			}
		}

		// replace with matches
		foreach ($matches as $name => $matched)
		{
			if (is_scalar($matched))
			{
				$replace = "$$name";
				$this->controller = $routeInfo['controller'] = str_replace($replace, $matched, $routeInfo['controller']);
				$this->action = $routeInfo['action'] = str_replace($replace, $matched, $routeInfo['action']);
				$this->template = $routeInfo['template'] = str_replace($replace, $matched, $routeInfo['template']);
				foreach ($routeInfo['arguments'] as $key => $value)
				{
					$this->arguments[$key] = $routeInfo['arguments'][$key] = str_replace($replace, $matched, $value);
				}
			}
		}

		if (!empty($queryString))
		{
			// add query string parameters
			parse_str($queryString, $queryStringParameters);
			foreach ($queryStringParameters AS $key => $value)
			{
				$this->queryParameters[$key] = $value;
			}
		}

		if (!empty($matches['innerPost']))
		{
			$this->anchor = 'post' . intval($matches['innerPost']);
		}
		elseif (!empty($anchor) AND is_string($anchor))
		{
			$this->anchor = $anchor;
		}
	}

	public function getRouteId()
	{
		return $this->routeId;
	}

	public function getRouteGuid()
	{
		return $this->routeGuid;
	}

	public function getRedirect301()
	{
		return $this->redirect301;
	}

	public function getPrefix()
	{
		return $this->prefix;
	}

	public function getController()
	{
		return $this->controller;
	}

	public function getAction()
	{
		return $this->action;
	}

	public function getTemplate()
	{
		return $this->template;
	}

	public function getArguments()
	{
		return $this->arguments;
	}

	public function getQueryParameters()
	{
		return $this->queryParameters;
	}
	
	public function getAnchor()
	{
		return $this->anchor;
	}

	protected function setPageKey()
	{
		$parameters = func_get_args();
		if (empty($parameters))
		{
			$this->pageKey = FALSE;
		}
		else
		{
			$baseClass = get_class() . '_';
			$this->pageKey = strtolower(str_replace($baseClass, '', get_class($this))) . $this->routeId;

			foreach($parameters as $param)
			{
				$this->pageKey .= isset($this->arguments[$param]) ? ('.' . $this->arguments[$param]) : '';
			}
		}
	}

	public function getPageKey()
	{
		return $this->pageKey;
	}

	protected function setUserAction()
	{
		$parameters = func_get_args();
		if (empty($parameters))
		{
			$this->userAction = false;
		}
		else
		{
			$this->userAction = array();
			foreach ($parameters AS $param)
			{
				$this->userAction[] = strval($param);
			}
		}
	}

	/**
	 * Returns the user action associated with the route
	 * @return mixed
	 */
	public function getUserAction()
	{
		return $this->userAction;
	}

	 /**
	 * Sets the breadcrumbs for the route
	 */
	protected function setBreadcrumbs()
	{
		$this->breadcrumbs = array();
		if (isset($this->arguments['channelid']) && $this->arguments['channelid'])
		{
			$this->addParentNodeBreadcrumbs($this->arguments['channelid']);
		}
	}

	/**
	 * Adds breadcrumb entries for all the parents of the passed node id.
	 * This is inclusive of the passed node id, but excludes "home".
	 * Modifies $this->breadcrumbs
	 *
	 * @param	int	Node ID
	 */
	protected function addParentNodeBreadcrumbs($nodeId)
	{
		try
		{
			// obtain crumbs
			$nodeLibrary = vB_Library::instance('node');
			$nodeParents = $nodeLibrary->getNodeParents($nodeId);
			$nodeParentsReversed = array_reverse($nodeParents);
			$parentsInfo = $nodeLibrary->getNodes($nodeParentsReversed);
			$routeIds = array();
			foreach ($nodeParentsReversed AS $parentId)
			{
				if ($parentId != 1)
				{
					$routeIds[] = $parentsInfo[$parentId]['routeid'];
				}
			}
			vB5_Route::preloadRoutes($routeIds);
			foreach ($nodeParentsReversed AS $parentId)
			{
				if ($parentId != 1)
				{
					$this->breadcrumbs[] = array(
						'title' => $parentsInfo[$parentId]['title'],
						'url' => vB5_Route::buildUrl("{$parentsInfo[$parentId]['routeid']}|nosession")
					);

				}
			}
		}
		catch (vB_Exception $e)
		{
			// if we don't have permissions to view the channel, then skip this
		}
	}

	/**
	 * Returns breadcrumbs to be displayed in page header
	 * @return array
	 */
	public function getBreadcrumbs()
	{
		$this->setBreadcrumbs();
		return $this->breadcrumbs;
	}

	/**
	 * Get the url of this route. To be overriden by child classes.
	 * This should always return the path encoded in UTF-8. If vB_String::getCharset() is not utf-8,
	 * the url should be percent encoded using vB_String::encodeUtf8Url().
	 * 
	 * @return	mixed	false|string
	 */
	public function getUrl()
	{
		return false;
	}

	public function getFullUrl($options = "")
	{
		if (!is_array($options))
		{
			$options = explode('|', $options);
		}

		$params = $this->queryParameters;
		if (!in_array('nosession', $options))
		{
			$session = vB::getCurrentSession();
			if ($session AND $session->isVisible())
			{
				$params['s'] = $session->get('dbsessionhash');
			}
		}

		$url = $this->getUrl();

		$base = '';
		if ((in_array('fullurl', $options) OR in_array('bburl', $options)) AND strpos($url, '://') === false)
		{
			//todo, this is a total and complete hack... we need to figure out a real way to tie
			//urls back to the presentation layer before we ship.
			$base = isset($_SERVER['x-vb-presentation-base']) ? strval($_SERVER['x-vb-presentation-base']) : '';
		}

		$response = $base . $url;

		if (!empty($params))
		{
			$response .= '?' . http_build_query($params, '', '&amp;');
		}

		if (!empty($this->anchor))
		{
			$response .= '#' . $this->anchor;
		}

		return $response;
	}

	/**
	 * Returns the route referenced by the associated item
	 * @return vB5_Route
	 */
	protected function getCanonicalRoute()
	{
		// only subclasses know how to obtain this
		return false;
	}

	public function getCanonicalPrefix()
	{
		if ($canonicalRoute = $this->getCanonicalRoute())
		{
			return $canonicalRoute->getPrefix();
		}
		else
		{
			return false;
		}
	}

	/**
	 * Returns the canonical url which may be based on a different route
	 */
	public function getCanonicalUrl()
	{
		if ($canonicalRoute = $this->getCanonicalRoute())
		{
			$url = $canonicalRoute->getUrl();
			
			$parameters = $canonicalRoute->getQueryParameters();
			if (!empty($parameters))
			{
				$url .= '?' . http_build_query($parameters, '', '&amp;');
			}

			$anchor = $canonicalRoute->getAnchor();
			if (!empty($anchor))
			{
				$url .= '#' . $anchor;
			}
			
			return $url;
		}
		else
		{
			return false;
		}
	}

	protected static function prepareTitle($title)
	{
		$title = vb_String::getUrlIdent($title);
		return self::prepareUrlIdent($title);
	}
	
	protected static function prepareUrlIdent($ident)
	{
		//ident can't start with a number
		if (preg_match('/^[0-9]+-/', $ident))
		{
			$ident = '-' . $ident;
		}
		return $ident;
	}

	/**
	 * Checks if route info is valid and performs any required sanitation
	 *
	 * @param array $data
	 * @return bool Returns TRUE iff data is valid
	 */
	protected static function validInput(array &$data)
	{
		if (!isset($data['guid']) OR empty($data['guid']))
		{
			$data['guid'] = vB_Xml_Export_Route::createGUID($data);
		}
		
		$prefixLength = strlen($data['prefix']);
		if (!isset($data['prefix']) OR  $prefixLength> self::PREFIX_MAXSIZE)
		{
			if (defined('VB_AREA') AND in_array(VB_AREA, array('Install', 'Upgrade')))
			{
				// We need to automatically shorten the URL
				$parts = array_reverse(explode('/', $data['prefix']));
				
				$newPath[] = $part = array_shift($parts);
				$length = strlen($part);
				
				if ($length > self::PREFIX_MAXSIZE)
				{
					// the last element is itself too long
					$newPrefix = substr($part, 0, self::PREFIX_MAXSIZE);
				}
				else
				{
					// prepend parts until we reach the limit
					while (($part = array_shift($parts)) AND ($length + 1 + strlen($part)) <= self::PREFIX_MAXSIZE)
					{
						array_unshift($newPath, $part);
						$length += 1 + strlen($part);
					}
					
					$newPrefix = implode('/', $newPath);
				}
				
				// replace in regex
				$data['regex'] = preg_replace("#^{$data['prefix']}#", $newPrefix, $data['regex']);
				
				$data['prefix'] = $newPrefix;
			}
			else
			{
				throw new vB_Exception_Api('url_too_long', array($prefixLength, self::PREFIX_MAXSIZE));
			}
		}
		
		if (!isset($data['regex']) OR strlen($data['regex']) > self::REGEX_MAXSIZE)
		{
			return false;
		}

		return true;
	}

	/**
	 * Returns TRUE iif the prefix cannot be used for page
	 * @param string $prefix - Prefix to be validated
	 * @param int $routeId - Route that is currently used
	 * @return mixed - The route that is using the prefix or FALSE if not used
	 */
	public static function isPrefixUsed($prefix, $routeId = 0)
	{
		$route = vB::getDbAssertor()->getRow('routenew', array('prefix' => $prefix));
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$cache->write('vbRoutenew_' . $route['routeid'], $route, 1440, 'routeChg_' . $route['routeid']);
		if (!empty($route['name']))
		{
			$cache->write('vbRoutenew_' . $route['name'], $route, 1440, 'routeChg_' . $route['routeid']);
		}

		if (empty($route))
		{
			return FALSE;
		}
		else
		{
			// if it redirects to the route we are looking at, we can reuseit
			$route = vB_Api::instanceInternal('route')->getRoute($prefix, '');

			$cache->write('vbRoutenew_' . $route['routeid'], $route, 1440, 'routeChg_' . $route['routeid']);

			if (!empty($route['name']))
			{
				$cache->write('vbRoutenew_' . $route['name'], $route, 1440, 'routeChg_' . $route['routeid']);
			}

			if (isset($route['redirectRouteId']) AND $routeId == $route['redirectRouteId'])
			{
				return FALSE;
			}
			// if the prefix redirects to a different place, we cannot use this prefix
			else
			{
				return $route;
			}
		}
	}

	/**
	 * Stores route in db and returns its id
	 * @param type $data
	 * @return int
	 */
	protected static function saveRoute($data, $condition = array())
	{
		$assertor = vB::getDbAssertor();

		$routeTable = $assertor->fetchTableStructure('routenew');
		$info = array();
		foreach ($routeTable['structure'] AS $field)
		{
			if (isset($data[$field]))
			{
				$info[$field] = $data[$field];
			}
		}

		if (empty($condition))
		{
			return $assertor->insert('routenew', $info);
		}
		else
		{
			return $assertor->update('routenew', $info, $condition);
		}
	}

	public static function createRoute($class, $data)
	{
		if (!class_exists($class))
		{
			throw new Exception('Invalid route class');
		}

		if (!call_user_func_array(array($class, 'validInput'), array(&$data)))
		{
			throw new vB_Exception_Api('Invalid route data');
		}

		//checking for existing/duplicate route info
		if (vB::getDbAssertor()->getRow('routenew', array('regex' => $data['regex'])))
		{
			throw new Exception('Duplicate route data: '. $data['regex']);
		}

		return self::saveRoute($data);
	}

	public static function updateRoute($routeId, $data)
	{
		$assertor = vB::getDbAssertor();

		$oldRouteInfo = $assertor->getRow('routenew', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'routeid' => $routeId
				));

		if (!$oldRouteInfo)
		{
			return FALSE;
		}

		if (isset($oldRouteInfo['arguments']) AND !empty($oldRouteInfo['arguments']))
		{
			$arguments = unserialize($oldRouteInfo['arguments']);
			foreach ($arguments AS $key=>$val)
			{
				$oldRouteInfo[$key] = $val;
			}
		}

		$class = $oldRouteInfo['class'];
		$new_data = array_merge($oldRouteInfo, $data);
		unset($new_data['routeid']);

		if (!call_user_func_array(array($class, 'validInput'), array(&$new_data)))
		{
			throw new Exception('Invalid route data');
		}

		if (
				(isset($new_data['prefix']) AND $new_data['prefix'] !== $oldRouteInfo['prefix'])
		)
		{
			// Overwrite any related record with the same regex.
			// If you need to validate the prefix before calling this method: use vB5_Route::isPrefixUsed (see vB_Api_Page::savePage).
			$assertor->delete('routenew', array('regex' => $new_data['regex']));

			// url has changed: create a new route and update old ones and page record
			$newrouteid = self::saveRoute($new_data);
			if (is_array($newrouteid))
			{
				$newrouteid = (int) array_pop($newrouteid);
			}
			$new_data['routeid'] = $newrouteid;

			call_user_func(array($class, 'updateContentRoute'), $oldRouteInfo, $new_data);

			return $newrouteid;
		}
		else
		{
			// url has not changed, so there is no need to create a new route
			unset($new_data['prefix']);
			unset($new_data['regex']);
			unset($new_data['arguments']);
			self::saveRoute($new_data, array('routeid'=>$oldRouteInfo['routeid']));
			return $oldRouteInfo['routeid'];
		}
	}

	/**
	 * Generates an array with all prefixes for $url
	 * @param string $url
	 * @return array
	 */
	public static function getPrefixSet($url)
	{
		// Generate all prefixes of the url where the prefix is
		// everything up to a slash and sort
		// e.g. for my/path/file.html:
		// 1 - my/path/file.html
		// 2 - my/path
		// 3 - my
		$prefixes[] = $temp = $url;
		while (($pos = strrpos($temp, '/')) !== FALSE)
		{
			$prefixes[] = $temp = substr($temp, 0, $pos);
		}
		return $prefixes;
	}

	/**
	 * Returns the route that best fits the pathInfo in $matchedRoutes
	 * @param string $pathInfo
	 * @param string $queryString
	 * @param array $matchedRoutes
	 * @return null|vB5_Route +
	 */
	public static function selectBestRoute($pathInfo, $queryString = '', $anchor = '', $matchedRoutes = array())
	{
		// loop through matched routes and select best match
		// if you find exact match, we are done
		// if not, find longest matching route
		// after finding best route, set urlData with subpatterns info, this will be use to complete parsing
		if (!is_array($matchedRoutes) OR empty($matchedRoutes))
		{
			return null;
		}

		if (isset($_SERVER['SERVER_PORT']))
		{
			$port = intval($_SERVER['SERVER_PORT']);
			$https = (($port == 443) OR (isset($_SERVER['HTTPS']) AND $_SERVER['HTTPS'] AND ($_SERVER['HTTPS'] != 'off'))) ? true : false;
			$fullPath = 'http' . ($https ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		}
		else
		{
			//we're in test mode.
			$fullPath = $pathInfo;
		}

		usort($matchedRoutes, array('vB5_Route', 'compareRoutes'));
		foreach ($matchedRoutes as $routeInfo)
		{
			// pattern matching is case-insensitive
			$pattern = '#^' . $routeInfo['regex'] . '(?:/)?$#i';

			if (preg_match('#^https?://#', $routeInfo['regex']))
			{
				$matchPath = $fullPath;
			}
			else
			{
				$matchPath = $pathInfo;
			}

			if (preg_match($pattern, $matchPath, $matches))
			{
				$className = (isset($routeInfo['class']) AND !empty($routeInfo['class']) AND class_exists($routeInfo['class'])) ? $routeInfo['class'] : self::DEFAULT_CLASS;
				$route = new $className($routeInfo, $matches, $queryString, $anchor);
				return $route;
			}
		}

		// if we got here, there were no matching routes
		return null;
	}

	protected static function compareRoutes($route1, $route2)
	{
		return (strlen($route2['prefix']) - strlen($route1['prefix']));
	}



	public static function getRouteByIdent($routeident)
	{
		if (empty($routeident))
		{
			return false;
		}
		if (empty(self::$routeidentcache))
		{
			// Loads all named routes together. The named routes will grow slowly so it's OK to load them all together
			self::loadNameRoutes();
		}

		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$hashKey = 'vbRoutenew_'. $routeident;
		$route = $cache->read($hashKey);
		if (!$route)
		{
			if (is_numeric($routeident))
			{
				if (!isset(self::$routeidentcache['routeid'][$routeident]))
				{
					$route = vB::getDbAssertor()->getRow(
					'routenew',	array('routeid' => $routeident	));
				}
				else
				{
					$route = self::$routeidentcache['routeid'][$routeident];
				}
			}
			else
			{
				$route = self::$routeidentcache['name'][$routeident];
			}

			if (empty($route) OR !empty($route['errors']))
			{
				$route = false;
			}

			$cache->write('vbRoutenew_' . $route['routeid'], $route, 1440, 'routeChg_' . $route['routeid']);

			if (!empty($route['name']))
			{
				$cache->write('vbRoutenew_' . $route['name'], $route, 1440, 'routeChg_' . $route['routeid']);
			}
		}
		return $route;
	}

	public static function preloadRoutes($routeIds)
	{

		if (empty(self::$routeidentcache))
		{
			// Loads all named routes together. The named routes will grow slowly so it's OK to load them all together
			self::loadNameRoutes();
		}
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		foreach ($routeIds AS $key => $routeId)
		{
			if (is_numeric($routeId))
			{
				if ($cache->isLoaded('vbRoutenew_' . $routeId))
				{
					unset($routeIds[$key]);
				}
			}
			else
			{
				unset($routeIds[$key]);
			}
		}
		if (!empty($routeIds))
		{
			//make sure we don't load these again
			//If it's a named route it's already loaded
			$routes = vB::getDbAssertor()->assertQuery(
				'routenew',	array('routeid' => $routeIds));
			$nodeids = array();
			//Now load from the database.
			foreach($routes AS $route)
			{
				$cache->write('vbRoutenew_' . $route['routeid'], $route, 1440, 'routeChg_' . $route['routeid']);
				if (!empty($route['name']))
				{
					$cache->write('vbRoutenew_' . $route['name'], $route, 1440, 'routeChg_' . $route['routeid']);
				}

				if ($route['class'] == 'vB5_Route_Channel'){
					$nodeids[] = $route['contentid'];
				}
			}
			if (count($nodeids) > 1)
			{
				// preload nodes
				vB_Library::instance('content_channel')->getFullContent($nodeids, false, false);
			}
		}
		return true;
	}

	public static function preloadConversationRoutes($channelIds)
	{
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		foreach ($channelIds AS $key => $channelId)
		{
			if (is_numeric($channelId))
			{
				if ($cache->isLoaded('vbRouteContentConversation_' . $channelId))
				{
					unset($channelIds[$key]);
				}
			}
			else
			{
				unset($channelIds[$key]);
			}
		}
		if (!empty($channelIds))
		{
			//make sure we don't load these again
			//If it's a named route it's already loaded
			$routes = vB::getDbAssertor()->assertQuery('routenew', array('class'=>'vB5_Route_Conversation', 'contentid' => $channelIds));

			//Now load from the database.
			foreach($routes AS $route)
			{
				$cache->write('vbRoutenew_' . $route['routeid'], $route, 1440, 'routeChg_' . $route['routeid']);
				if (!empty($route['name']))
				{
					$cache->write('vbRoutenew_' . $route['name'], $route, 1440, 'routeChg_' . $route['routeid']);
				}
				$cache->write('vbRouteContentConversation_' . $route['contentid'], $route, 1440, array('routeChg_' . $route['routeid'], 'nodeChg_' . $route['contentid']));
			}
		}
		return true;
	}

	/**Loads list of named routes, which changes rarely
	 *
	 **/
	protected static function loadNameRoutes()
	{
		$cache = vB_Cache::instance(vB_Cache::CACHE_STD);
		$cacheKey = 'vB_NamedRoutes';
		self::$routeidentcache = $cache->read($cacheKey);

		if (!empty(self::$routeidentcache))
		{
			return;
		}

		$routes = vB::getDbAssertor()->getRows('routenew', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'name', 'operator' => vB_dB_Query::OPERATOR_ISNOTNULL)
			)
		));
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		foreach ($routes as $route) {
			self::$routeidentcache['name'][$route['name']] = $route;
			self::$routeidentcache['routeid'][$route['routeid']] = $route;
			$cache->write('vbRoutenew_' . $route['routeid'], $route, 1440, 'routeChg_' . $route['routeid']);
			if (!empty($route['name']))
			{
				$cache->write('vbRoutenew_' . $route['name'], $route, 1440, 'routeChg_' . $route['routeid']);
			}
		}

		$cache->write($cacheKey, self::$routeidentcache, 1440, 'vB_Route_AddNamedUrl');
	}

	protected static function getClassName($routeId, &$routeInfo = array())
	{
		$routeInfo = self::getRouteByIdent($routeId);
		if (!$routeInfo)
		{
			return FALSE;
		}
		if (is_string($routeInfo['arguments']))
		{
			$routeInfo['arguments'] = @unserialize($routeInfo['arguments']);
		}
		return (isset($routeInfo['class']) AND !empty($routeInfo['class']) AND class_exists($routeInfo['class'])) ? $routeInfo['class'] : self::DEFAULT_CLASS;
	}

	public static function getRoute($routeId, $data = array(), $extra = array(), $anchor = '')
	{
		$routeInfo = array();
		$className = self::getClassName($routeId, $routeInfo);
		return new $className($routeInfo, $data, http_build_query($extra), $anchor);
	}

	/**
	 * Returns the URL associated to the route info. It does not use canonical route.
	 *
	 * @param <type> $routeId
	 * @param array $data
	 */
	public static function buildUrl($options, $data = array(), $extra = array(), $anchor = '')
	{
		$options = explode('|', $options);
		$routeId = $options[0];
		if(empty($routeId))
		{
			throw new Exception("error_no_routeid");
		}
		if (!$extra)
		{
			$extra = array();
		}
		$routeInfo = array();
		$className = self::getClassName($routeId, $routeInfo);
		if (!class_exists($className))
		{
			return '#';
		}
		$hashKey = $className::getHashKey($options, $data, $extra);
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$fullURL = $cache->read($hashKey);
		if (empty($fullURL))
		{
			$route = new $className($routeInfo, $data, http_build_query($extra), $anchor);

			if (empty($route))
			{
				throw new Exception('invalid_routeid');
			}
			$fullURL = $route->getFullUrl($options);
			$cache->write($hashKey, $fullURL, 1440, array('routeChg_' . $routeId));
		}
		return $fullURL;
	}
	
	/**
	 * get the urls in one batch
	 * @param array $URLInfoList has to contain the route, data and extra
	 * @return array URLs built based on the input
	 */
	public static function buildUrls($URLInfoList)
	{
		$URLs = array();
		
		// first we are going to collect inner hashes
		$innerHashes = array();
		$routeData = array();
		foreach ($URLInfoList as $hash => $info)
		{
			$options = explode('|', $info['route']);
			$routeId = $options[0];
			
			if(empty($routeId))
			{
				// we don't have a routeid, so we can skip this and return an empty URL
				$URLs[$hash] = '';
				unset($URLInfoList[$hash]);
				continue;
			}
			
			if (isset($routeData[$routeId]))
			{
				$URLInfoList[$hash]['routeInfo'] = $routeData[$routeId]['routeInfo'];
				$URLInfoList[$hash]['class'] = $routeData[$routeId]['className'];
				$className = $routeData[$routeId]['className'];
			}
			else
			{
				$routeInfo = array();
				$className = self::getClassName($routeId, $routeInfo);
				
				$routeData[$routeId] = array('className' => $className, 'routeInfo' => $routeInfo);
				
				$URLInfoList[$hash]['routeInfo'] = $routeInfo;
				$URLInfoList[$hash]['class'] = $className;
			}
				
			if (!class_exists($className))
			{
				// class doesn't exist (same as buildUrl)
				$URLs[$hash] = '#';
				unset($URLInfoList[$hash]);
				continue;
			}
			
			$URLInfoList[$hash]['anchor'] = (empty($info['options']['anchor'])  OR !is_string($info['options']['anchor'])) ? '' : $info['options']['anchor'];
			$URLInfoList[$hash]['innerHash'] = $innerHash = $className::getHashKey($options, $info['data'], $info['extra']);
			
			$innerHashes[$innerHash] = $hash;
		}
		
		if (!empty($innerHashes))
		{
			// now fetch as many URLs as possible from cache
			$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
			$hits = $cache->read(array_keys($innerHashes));

			foreach($hits AS $innerHash => $url)
			{
				if ($url !== false)
				{
					$hash = $innerHashes[$innerHash];
					$URLs[$hash] = $url;
					unset($URLInfoList[$hash]);
				}
			}
		}
		
		// do we still have URLs to build?
		if (!empty($URLInfoList))
		{
			// group by route class
			$classes = array();
			foreach ($URLInfoList AS $hash => $info)
			{
				$classes[$info['class']][$hash] = $info;
			}
			
			// now process URLs per class
			foreach($classes AS $className => $items)
			{
				$URLs += $className::bulkFetchUrls($className, $items);
			}
		}
			
		return $URLs;
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
				if (!isset($route))
				{
					$route = new $className($info['routeInfo'], $info['data'], http_build_query($info['extra']), $info['anchor']);
				}
				else
				{
					$route->initRoute($info['routeInfo'], $info['data'], http_build_query($info['extra']), $info['anchor']);
				}

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

	/**
	 * Returns arguments to be exported
	 * @param string $arguments
	 * @return array
	 */
	public static function exportArguments($arguments)
	{
		return $arguments;
	}

	/**
	 * Returns an array with imported values for the route
	 * @param string $arguments
	 * @return string
	 */
	public static function importArguments($arguments)
	{
		return $arguments;
	}

	/**
	 * Returns the content id from the imported values of the route after being parsed
	 * @param string $arguments
	 * @return int
	 */
	public static function importContentId($arguments)
	{
		return 0;
	}

	public function getHash($route, array $data, array $extra)
	{
		$options = explode('|', $route);
		if (empty($options[0]))
		{
			return '!!empty!!_' . md5(time());
			$className = self::DEFAULT_CLASS;
		}
		else
		{
			$className = self::getClassName($options[0]);
		}
		return $className::getHashKey($options, $data, $extra);
	}


	protected static function getHashKey($options = array(), $data = array(), $extra = array())
	{
		$routeId = array_shift($options);
		$option_keys = array_flip($options);
		if (array_key_exists('nosession', $option_keys))
		{
			unset($option_keys['nosession']);
			$options = array_keys($option_keys);
			$no_session = true;
		}

		$hashKey = 'vbRouteURL_'. $routeId;
		if (!empty($no_session))
		{
			$hashKey .= "_without_session";
		}
		$hash_add = (empty($options) ? '' : serialize($options)) . (empty($data) ? '' : serialize($data)) . (empty($extra) ? '' : serialize($extra));
		if (!empty($hash_add))
		{
			$hashKey .= '_' . md5($hash_add);
		}
		return $hashKey;
	}

	function getRouteSegments()
	{
		return explode('/', $this->prefix);
	}
}
