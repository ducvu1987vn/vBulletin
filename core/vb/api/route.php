<?php

/**
 * vB_Api_Route
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Route extends vB_Api
{
	protected $disableWhiteList = array('getRoute');

	protected function __construct()
	{
		parent::__construct();
	}

	public function GetSpecialRoutes()
	{
		/* Routes that should always give
		a no permission error if directly viewed
		They are mostly Top Level special channels */
		return array(
			'special',
			'special/reports'
		);
	}

	/**
	 * Returns the array of routes for the application
	 *
	 * @return 	array	The routes
	 */
	public function fetchAll()
	{
		$result = vB::getDbAssertor()->assertQuery(
			'routenew',
			array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			)
		);

		$routes = array();
		if ($result->valid())
		{
			foreach ($result AS $route)
			{
				if (($unserialized = @unserialize($route['arguments'])) !== false)
				{
					$route['arguments'] = $unserialized;
				}
				else
				{
					$route['arguments'] = array();
				}
				$routes[$route['routeid']] = $route;
			}
		}

		//uasort($routes, array($this, '_sortRoutes'));

		return $routes;
	}

	/**
	 * Returns a matching route if available for $pathInfo
	 *
	 * @param string $pathInfo
	 * @param string $queryString
	 * @return vB_Frontend_Route
	 */
	public function getRoute($pathInfo, $queryString, $anchor = '')
	{
		// clean the path if necessary
		$parsed = vB_String::parseUrl($pathInfo);
		$pathInfo = $parsed['path'];

		// check for any querystring to append
		if (!empty($parsed['query']))
		{
			if (!empty($queryString))
			{
				$queryString = $parsed['query'] . '&' . $queryString;
			}
			else
			{
				$queryString = $parsed['query'];
			}
		}

		if (empty($anchor) AND (!empty($parsed['anchor'])))
		{
			$anchor = $parsed['anchor'];
		}

		// calculate prefixes set
		$prefixes = vB5_Route::getPrefixSet($pathInfo);

		// get matching routes
		$result = vB::getDbAssertor()->assertQuery('routenew', array('prefix' => $prefixes));

		if (in_array($result->db()->errno, $result->db()->getCriticalErrors()))
		{
			throw new Exception ('no_vb5_database');
		}

		$prefixMatches = array();
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		foreach ($result AS $route)
		{
			if (($unserialized = @unserialize($route['arguments'])) !== false)
			{
				$route['arguments'] = $unserialized;
			}
			else
			{
				$route['arguments'] = array();
			}
			$prefixMatches[$route['routeid']] = $route;
 			$cache->write('vbRoutenew_' . $route['routeid'], $route, 1440, 'routeChg_' . $route['routeid']);

			if (!empty($route['name']))
			{
				$cache->write('vbRoutenew_' . $route['name'], $route, 1440, 'routeChg_' . $route['routeid']);
			}
		}

		// check for banned
		$bannedInfo = vB_Library::instance('user')->fetchBannedInfo(false);

		// get best route
		try
		{
			$route = vB5_Route::selectBestRoute($pathInfo, $queryString, $anchor, $prefixMatches);

			if ($route)
			{
				// Check if forum is closed
				$routeInfo = array(
					'routeguid' => $route->getRouteGuid(),
					'controller' => $route->getController(),
					'action' => $route->getAction(),
					'arguments' => $route->getArguments(),
				);

				$segments = $route->getRouteSegments();
				$cleanedRoute = implode('/', $segments);

				if(in_array($cleanedRoute, $this->GetSpecialRoutes()))
				{
					return array('no_permission' => 1);
				}

				$result = vB_Api::instanceInternal('state')->checkBeforeView($routeInfo);

				if ($result !== false)
				{
					return array('forum_closed' => $result['msg']);
				}

				if ($bannedInfo['isbanned'])
				{
					return array('banned_info' => $bannedInfo);
				}

				if (! vB::getUserContext()->getChannelPermission('forumpermissions', 'canview', 1) )
				{
					$prefix = $route->getCanonicalPrefix();
					if (!in_array($prefix, array('contact-us', 'lostpw', 'register', 'activateuser', 'activateemail')))
					{
						if ($route->getPrefix() == 'admincp' OR $route->getPrefix() == 'modcp')
						{
							// do nothing really, just allow passage
						}
						else if ($route->getPrefix() == 'ajax')
						{
							$arguments = $route->getArguments();
							$allowedOptions = array(
								'/api/contactus/sendMail',
								'/api/hv/generateToken',
							);
							if (!isset($arguments['route']) OR !in_array($arguments['route'], $allowedOptions))
							{
								return array('no_permission' => 1);
							}
						}
						else
						{
							return array('no_permission' => 1);
						}
					}
				}

				if (is_array($route) AND (isset($route['no_permission']) OR isset($route['internal_error'])))
				{
					return $route;
				}
				$canonicalUrl = $route->getCanonicalUrl();
				$canonicalPathInfo = ($canonicalUrl) ? vB_String::parseUrl($canonicalUrl, PHP_URL_PATH) : $pathInfo;
				if ($canonicalPathInfo AND $canonicalPathInfo{0} == '/')
				{
					$canonicalPathInfo = substr($canonicalPathInfo, 1);
				}

				if ($redirectId = $route->getRedirect301())
				{
					return array(
						'redirect'	=> vB5_Route::buildUrl($redirectId, $route->getArguments(), $route->getQueryParameters()),
						'redirectRouteId' => $redirectId
					);
				}
				else if ($pathInfo != $canonicalPathInfo)
				{
					return array(
						'redirect' => $canonicalUrl . (empty($queryString) ? '' : "?$queryString"),
						'redirectRouteId' => $route->getRouteId()
					);
				}
				else
				{
					return array(
						'routeid'         => $route->getRouteId(),
						'routeguid'       => $route->getRouteGuid(),
						'controller'      => $route->getController(),
						'action'          => $route->getAction(),
						'template'        => $route->getTemplate(),
						'arguments'       => $route->getArguments(),
						'queryParameters' => $route->getQueryParameters(),
						'pageKey'         => $route->getPageKey(),
						'userAction'      => $route->getUserAction(),
						'breadcrumbs'     => $route->getBreadcrumbs(),
					);
				}
			}
			else
			{
				return false;
			}
		}
		catch (vB_Exception_Api $ex)
		{
			return array(
				'internal_error' => $ex
			);
		}
		catch (vB_Exception_NodePermission $ex)
		{
			if (!$bannedInfo['isbanned'])
			{
				return array('no_permission' => 1);
			}
			else
			{
				return array('banned_info' => $bannedInfo);
			}
		}
		catch (vB_Exception_404 $ex)
		{
			// we want to return a 404
			return false;
		}
	}

	/**
	 * Returns the route id for the generic conversation route
	 * @param int $channelId
	 * @return int
	 */
	public function getChannelConversationRoute($channelId)
	{
		if(empty($channelId))
		{
			return false;
		}
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$hashKey = 'vbRouteContentConversation_'. $channelId;
		$route = $cache->read($hashKey);
		if ($route !== false)
		{
			if (!empty($route['routeid']))
			{
				return $route['routeid'];
			}
			else
			{
				return false;
			}
		}
		$route = vB::getDbAssertor()->getRow('routenew', array('class'=>'vB5_Route_Conversation', 'contentid'=>intval($channelId)));
		$cache->write($hashKey, $route, 1440, array('routeChg_' . $route['routeid'], 'nodeChg_' . $channelId));

		if(empty($route))
		{
			return false;
		}
		return $route['routeid'];
	}

	/**
	 * Get URL of a node
	 *
	 * @param int $nodeid Node ID
	 * @param array $data Additional route data for the node
	 * @param array $extra Extra data for the route
	 *
	 * @return string Node's URL
	 */
	public function getNodeUrl($nodeid, $data = array(), $extra = array())
	{
		$node = vB_Api::instanceInternal('node')->getNode($nodeid);

		$data = array_merge($data, array('nodeid' => $node['nodeid']));

		return $this->getUrl($node['routeid'], $data, $extra);
	}

	/**
	 *
	 * @param mixed $route
	 * @param array $data
	 * @param array $extra
	 * @return string	Always in UTF-8. If vB_String::getCharset() is not utf-8, it's percent encoded.
	 */
	public function getUrl($route, array $data, array $extra)
	{
		return vB5_Route::buildUrl($route, $data, $extra);
	}

	/**
	 * get the urls in one batch
	 * @param array $URLInfoList has to contain the route, data and extra
	 * @return array URLs built based on the input
	 */
	public function getUrls($URLInfoList)
	{
		return vB5_Route::buildUrls($URLInfoList);
	}
	/**
	 *	get a unique hash
	 * @param mixed $route
	 * @param array $data
	 * @param array $extra
	 * @return string
	 */
	public function getHash($route, array $data, array $extra)
	{
		return vB5_Route::getHash($route, $data, $extra);
	}

	/**
	 * Saves a new route
	 *
	 * @param	string	Route class name
	 * @param	array	Route data
	 *(
	 * @return	mixed	The routeid will be returned
	 */
	public function createRoute($class, array $data)
	{
		return call_user_func(array($class, 'createRoute'), $class, $data);
	}

	/**
	 * Updates an existing route
	 *
	 * @param	mixed	Route id
	 * @param	array	Route data
	 *
	 * @return	mixed	The routeid will be returned
	 */
	public function updateRoute($routeId, array $data)
	{
		return vB5_Route::updateRoute($routeId, $data);
	}

	/** Preloads a list of routes to reduce database traffic
	 *
	 * @param	mixed	array of route ids- can be integers or strings.
	 **/
	public function preloadRoutes($routeIds)
	{
		return vB5_Route::preloadRoutes($routeIds);
	}

	/** Preloads a list of conversation routes to reduce database traffic
	 *
	 * @param	mixed	array of channel ids- can be integers or strings.
	 **/
	public function preloadConversationRoutes($channelIds)
	{
		return vB5_Route::preloadConversationRoutes($channelIds);
	}


	public function updateNewPageRoute($pageId, $routeId)
	{
		$db = vB::getDbAssertor();

		$db->update('page', array('routeid'=>$routeId), array('pageid'=>$pageId));

		$pageRoute = $db->getRow('routenew', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array(
				'routeid' => $routeId
			)
		));

		// update routes for included channels
		$pageWidgets = $db->getRows('getPageWidgetsByType', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'pageid'	=> $pageId,
			'widgetids'	=> 3 // TODO: replace with a class constant
		));

		if ($pageWidgets)
		{
			$channelIds = array();
			foreach ($pageWidgets AS $widget)
			{
				$adminConfig = unserialize($widget['adminconfig']);
				if (isset($adminConfig['channel_node_ids']))
				{
					$channelIds = array_merge($channelIds, $adminConfig['channel_node_ids']);
				}
			}

			if ($channelIds)
			{
				$routes = $db->getRows('getChannelRoutes', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'channelids'	=> $channelIds
				));

				$defaultPrefix = 'home';
				foreach ($routes AS $route)
				{
					if (strpos($route['prefix'], $defaultPrefix) === 0)
					{
						$newprefix = preg_replace("#^$defaultPrefix#", $pageRoute['prefix'], $route['prefix']);
						$newregex = preg_replace("#^$defaultPrefix#", $pageRoute['prefix'], $route['regex']);

						$db->update('routenew', array('prefix'=>$newprefix, 'regex'=>$newregex), array('routeid'=>$route['routeid']));
					}
				}
			}
		}
	}

	/**
	 * Writes debugging output to the filesystem for AJAX calls
	 *
	 * @param	mixed	Output to write
	 */
	protected function _writeDebugOutput($output)
	{
		$fname = dirname(__FILE__) . '/_debug_output.txt';
		file_put_contents($fname, $output);
	}
	
	/**
	 * Returns the URL for the legacy postid
	 * @param int $postId
	 * @return mixed
	 */
	public function fetchLegacyPostUrl($postId)
	{
		$nodeInfo = vB::getDbAssertor()->getRow('vBForum:fetchLegacyPostIds', array(
			'oldids' => $postId,
			'postContentTypeId' => vB_Types::instance()->getContentTypeID('vBForum_Post'),
		));
		
		if ($nodeInfo)
		{
			return vB5_Route::buildUrl('node|fullurl|nosession', $nodeInfo);
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * Returns the URL for the legacy threadid
	 * @param int $threadId
	 * @return mixed
	 */
	public function fetchLegacyThreadUrl($threadId)
	{
		$nodeInfo = vB::getDbAssertor()->getRow('vBForum:node', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::COLUMNS_KEY => array('nodeid', 'starter', 'routeid'),
			vB_dB_Query::CONDITIONS_KEY => array(
				'oldid' => $threadId,
				'oldcontenttypeid' => vB_Types::instance()->getContentTypeID('vBForum_Thread')
			)
		));
		
		if ($nodeInfo)
		{
			return vB5_Route::buildUrl('node|fullurl|nosession', $nodeInfo);
		}
		else
		{
			return false;
		}
	}
}
