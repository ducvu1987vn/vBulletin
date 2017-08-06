<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * vB_Api_Page
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Page extends vB_Api
{
	protected $disableWhiteList = array('getQryCount');

	/** array of info used for precaching
	***/
	protected $preCacheInfo = array();
	protected $lastCacheData = array();
	/**Last time we saved cache- useful to prevent thrashing **/
	protected $lastpreCache = false;
	/**Minimum time between precache list updates, in seconds **/
	const MIN_PRECACHELIFE = 300;

	protected function __construct()
	{
		parent::__construct();
	}

	/**
	 * Returns a list of all pages.
	 * It only returns pages that doesn't accept any parameters. And it's used for Page Map {@see fetchPageMapHierarchy}.
	 * See VBV-2527
	 *
	 * @return	array
	 */
	protected function fetchPageList()
	{
		$db = vB::getDbAssertor();
		//$api = Api_InterfaceAbstract::instance();

		$pages = $db->getRows('fetchPageList', array(), 'title');

		if ($pages)
		{
			//foreach ($pages AS $k => $page)
			//{
			//	$pages[$k]['url'] = $api->callApi('route', 'getUrl', array(
			//		'routeid' => $page['routeid'],
			//		'data' => array(),
			//		'extra' => array(),
			//	));
			//}
			return $pages;
		}
		else
		{
			return array();
		}
	}

	public function fetchPageById($pageid, $routeData = array())
	{
		$pageid = intval($pageid);

		$db = vB::getDbAssertor();

		$conditions = array(
			'pageid' => $pageid,
		);
		//$page = $db->getRow('fetch_page_pagetemplate_screenlayout', $conditions);
		$page = $db->assertQuery('fetch_page_pagetemplate_screenlayout', $conditions);
		$page = $page->current();

		if ($page)
		{
			// check if this is currently the homepage
			//$route = $db->getRow('fetch_homepage_route', array());
			$route = $db->assertQuery('fetch_homepage_route');
			if ($route)
			{
				$route = $route->current();
			}

			if ($route AND $route['contentid'] == $page['pageid'])
			{
				$page['ishomepage'] = true;
				//todo shouldn't use html in the API.
				$page['makehomepagecheckattr'] = ' checked="checked"';
			}
			else
			{
				$page['ishomepage'] = false;
				$page['makehomepagecheckattr'] = '';
			}

			$page['isgeneric'] = ($page['pagetype'] == vB_Page::TYPE_DEFAULT);

			// get url scheme, hostname and path
			$route = vB5_Route::getRoute(intval($page['routeid']), $routeData);
			if ($route)
			{
				$page['urlprefix'] = $route->getCanonicalPrefix();
				$page['url'] = $route->getCanonicalUrl();

				$parsed = vB_String::parseUrl($page['url']);
				$page['urlscheme'] = isset($parsed['scheme']) ? $parsed['scheme'] : '';
				$page['urlhostname'] = isset($parsed['host']) ? $parsed['host'] : '';
				$page['urlpath'] = base64_encode($parsed['path']);
			}
		}

		return $page;
	}

	/**
	 * Saves a (new or existing) page
	 *
	 * @param	array	Page data
	 * @param	array	Conditions - Must be specified if updating an existing record.
	 *
	 * @return	int|mixed	If it is a new page, the pageid will be returned
	 */
	public function save(array $data, array $conditions = array())
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		$db = vB::getDbAssertor();

		if (!empty($conditions))
		{
			return $db->update('page', $data, $conditions);
		}
		else
		{
			return $db->insert('page', $data);
		}
	}

	public function getPageNav($currentpage = 1, $totalpages = 1)
	{
		$cacheKey = 'pageNav_' . $currentpage . '_' . $totalpages;

		if ($pageNav = vB_Cache::instance()->read($cacheKey))
		{
			return $pageNav;
		}

		$options = vB::getDatastore()->getValue('options');
		// create array of possible relative links that we might have (eg. +10, +20, +50, etc.)
		if (!isset($options['pagenavsarr']))
		{
			$options['pagenavsarr'] = preg_split('#\s+#s', $options['pagenavs'], -1, PREG_SPLIT_NO_EMPTY);
		}

		$pages = array(1, $currentpage, $totalpages);

		for ($i = 1; $i <= $options['pagenavpages']; $i++)
		{
			$pages[] = $currentpage + $i;
			$pages[] = $currentpage - $i;
		}

		foreach ($options['pagenavsarr'] AS $relpage)
		{
			$pages[] = $currentpage + $relpage;
			$pages[] = $currentpage - $relpage;
		}

		$show_prior_elipsis = $show_after_elipsis = ($totalpages > $options['pagenavpages']) ? 1 : 0;

		$pages = array_unique($pages);
		sort($pages);

		$final_pages = array();
		foreach ($pages AS $foo => $curpage)
		{
			if ($curpage < 1 OR $curpage > $totalpages)
			{
				continue;
			}
			$final_pages[] = $curpage;
		}
		vB_Cache::instance()->write("pageNav_$currentpage_$totalpages", $final_pages, 0, "pageNavChg");
		return $final_pages;
	}

	public function getPagingInfo($pageNum = 1, $totalCount = 0, $perPage = 0, array $routeInfo, $baseUrl, $maxpage = 0)
	{
		$perPage = (int) $perPage;
		$perPage = $perPage < 1 ? vB::getDatastore()->getOption('searchperpage') : $perPage;
		$totalPages = ceil($totalCount / $perPage);
		if ($totalPages == 0)
		{
			$totalPages = 1;
		}

		if ($maxpage AND $totalPages > $maxpage)
		{
			$totalPages = $maxpage;
		}

		$pageNum = (int) $pageNum;
		if ($pageNum < 1)
		{
			$pageNum = 1;
		}
		else if ($pageNum > $totalPages)
		{
			$pageNum = ($totalPages > 0) ? $totalPages : 1;
		}

		$prevUrl = $nextUrl = '';

		if ($pageNum > 1)
		{
			$routeInfo['arguments']['pagenum'] = $pageNum - 1;
			$prevUrl = $baseUrl . vB5_Route::buildUrl($routeInfo['routeId'] . '|nosession', $routeInfo['arguments'], $routeInfo['queryParameters']);
		}

		if ($pageNum < $totalPages)
		{
			$routeInfo['arguments']['pagenum'] = $pageNum + 1;
			$nextUrl = $baseUrl . vB5_Route::buildUrl($routeInfo['routeId'] . '|nosession', $routeInfo['arguments'], $routeInfo['queryParameters']);
		}

		if ($totalCount > 0)
		{
			$startCount = ($pageNum * $perPage) - $perPage + 1;
			$endCount = $pageNum * $perPage;
			if ($endCount > $totalCount)
			{
				$endCount = $totalCount;
			}
		}
		else
		{
			$startCount = $endCount = 0;
		}

		unset($routeInfo['arguments']['pagenum']);
		$pageBaseUrl = $baseUrl . vB5_Route::buildUrl($routeInfo['routeId'] . '|nosession', $routeInfo['arguments']);

		//get pagenav data
		$pageNavData = array(
			'startcount' => $startCount,
			'endcount' => $endCount,
			'totalcount' => $totalCount,
			'currentpage' => $pageNum,
			'prevurl' => $prevUrl,
			'nexturl' => $nextUrl,
			'totalpages' => $totalPages,
			'perpage' => $perPage,
			'baseurl' => $pageBaseUrl
		);

		return $pageNavData;
	}

	/**
	 * Returns an array of page hierarchy information to display the page map
	 *
	 * @param	int	Page ID
	 * @return	array	Page map hierarchy information
	 */
	public function fetchPageMapHierarchy($pageId)
	{
		$pagesTemp = $this->fetchPageList();
		$pages = array();
		foreach ($pagesTemp AS $page)
		{
			$pages[$page['pageid']] = $page;
		}
		unset($pagesTemp, $page);

		$parentIds = $this->getPageParentIds($pageId, $pages);

		$pageMap = array();

		$routeapi = vB_Api::instanceInternal('route');

		foreach ($parentIds as $parentId)
		{
			$siblingIds = $this->getPageSiblingIds($parentId, $pages);
			$siblings = array();
			foreach ($siblingIds AS $siblingId)
			{
				$sibling = $pages[$siblingId];
				try {
					$sibling['url'] = $routeapi->getUrl($sibling['routeid'], array(), array());
				}
				catch(Exception $e)
				{
				}

				$siblings[] = $sibling;
			}

			$pageMap[] = array(
				'siblings' => $siblings,
				'page' => $pages[$parentId],
			);
		}

		$pageMap = array_reverse($pageMap);

		return array('pageMap' => $pageMap);
	}

	/**
	 * Used by {@see fetchPageMapHierarchy}. Returns an array of
	 * page ids for the pages that are siblings to the passed page id.
	 *
	 * @param	int	Page ID
	 * @param	array	Array of pages, as returned from {@see fetchPageList}, but indexed by page ID.
	 *
	 * @return	array	Array of pageIDs corresponding to the siblings.
	 */
	protected function getPageSiblingIds($pageId, &$pages)
	{
		$parentId = $pages[$pageId]['parentid'];
		$siblingPageIds = array();

		foreach ($pages AS $page)
		{
			if ($page['parentid'] == $parentId)
			{
				$siblingPageIds[] = $page['pageid'];
			}
		}

		return $siblingPageIds;

	}

	/**
	 * Used by {@see fetchPageMapHierarchy}. Returns an array of
	 * page ids for the pages that are parents/ancestors of the passed page id.
	 *
	 * Returns a list of page IDs, from the passed page ID, up until the root page ID.
	 *
	 * @param	int	Page ID
	 * @param	array	Array of pages, as returned from {@see fetchPageList}, but indexed by page ID.
	 *
	 * @return	array	Array of pageIDs corresponding to the parents/ancestors.
	 */
	protected function getPageParentIds($pageId, &$pages)
	{
		$parentId = $pages[$pageId]['parentid'];
		$parentIds = array($pageId);

		while ($parentId > 0)
		{
			$parentIds[] = $parentId;
			$parentId = $pages[$parentId]['parentid'];
		}

		return $parentIds;
	}

	/**
	 * Saves a page based on page editor info
	 * @param array $input
	 * @return array
	 */
	public function pageSave($input)
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		/* Sample input
		Array
		(
			[pageid] => 1
			[screenlayoutid] => 2
			[displaysections] => Array
			(
				[0] => 3=1,4=2
				[1] => 1=3,2=4
			)

			[pagetitle] => Forums
			[resturl] => forums
			[pagetemplateid] => 0	// 0 if we are saving the page template as a new page template
			[templatetitle] => Name
			[btnSaveEditPage] =>
		)
		*/
		$done = false;
		$i = 0;
		$displaysections = array();
		foreach ($input as $key => $value)
		{
			if (!empty($value) AND preg_match('/^displaysections\[([0-9]+)$/i', $key, $matches))
			{
				$displaysection_value = json_decode($value, true);
				if (!empty($displaysection_value))
				{
					$displaysections[$matches[1]] = $displaysection_value;
				}
			}
		}

		// cleaning input
		$input = array(
			'pagetitle' => trim(strval($input['pagetitle'])),
			'resturl' => trim(strval($input['resturl'])), // subdirectory
			'pageid' => intval($input['pageid']),
			'nodeid' => intval($input['nodeid']),
			'pagetemplateid' => intval($input['pagetemplateid']),
			'templatetitle' => trim(strval($input['templatetitle'])),
			'screenlayoutid' => intval($input['screenlayoutid']),
			'displaysections' => $displaysections,
			'metakeywords' => trim(strval($input['metakeywords'])),
			'metadescription' => trim(strval($input['metadescription'])),
		);

		if (empty($input['pagetitle']))
		{
			throw new vB_Exception_Api('page_title_cannot_be_empty');
		}
		if (empty($input['templatetitle']) AND $input['pagetemplateid'] < 1)
		{
			throw new vB_Exception_Api('page_template_title_cannot_be_empty');
		}
		if ($input['screenlayoutid'] < 1)
		{
			throw new vB_Exception_Api('you_must_specify_a_screen_layout');
		}

		$this->db = vB::getDbAssertor();

		// --- save the page template ----------------------------

		// get page info
		$forceNewPage = false; /* if prefix is modified, we need to create a new page, pagetemplate and widgets */
		$isPrefixUsed = false;
		if ($input['pageid'] > 0)
		{
			$page = $this->fetchPageById($input['pageid'], array('nodeid'=>$input['nodeid']));
			if (!is_array($page))
			{
				$page = array();
			}
			else
			{
				$forceNewPage = ($page['isgeneric'] AND ($input['resturl'] != $page['urlprefix']));

				// if we are modifying a page url, we need to check the new url...
				if ($input['resturl'] != $page['urlprefix'])
				{
					$isPrefixUsed = vB5_Route::isPrefixUsed($input['resturl'], $page['routeid']);
				}
			}
		}
		else
		{
			// if it is a new page, we need to check the url
			$isPrefixUsed = vB5_Route::isPrefixUsed($input['resturl']);

			$page = array();
		}

		$routeApi = vB_Api::instanceInternal('route');
		if ($isPrefixUsed !== FALSE AND empty($isPrefixUsed['redirectRouteId']))
		{
			throw new vB_Exception_Api('this_url_is_already_used');
		}

		// page template
		$valuePairs = array(
			'title' => $input['templatetitle'],
			'screenlayoutid' => $input['screenlayoutid'],
		);

		$pagetemplateid = $input['pagetemplateid'];

		if ($pagetemplateid < 1 OR $forceNewPage)
		{
			$valuePairs['guid'] = vB_Xml_Export_PageTemplate::createGUID($valuePairs);
			// If no widgets were configured on the page template, we won't have a page template ID.
			$pagetemplateid = $this->db->insert('pagetemplate', $valuePairs);
			if (is_array($pagetemplateid))
			{
				$pagetemplateid = (int) array_pop($pagetemplateid);
			}
			$newTemplate = true;
		}
		else
		{
			$this->db->update('pagetemplate', $valuePairs, array('pagetemplateid' => $pagetemplateid));
			$newTemplate = false;
		}

		// widgets on page template

		$widgetApi = vB_Api::instanceInternal('widget');
		$currentWidgetInstances = $widgetApi->fetchWidgetInstancesByPageTemplateId($pagetemplateid);
		$currentWidgetInstanceIds = $this->getAllCurrentModuleInstances($currentWidgetInstances);

		$savedWidgetInstanceIds = array();

		$widgets = array();

		foreach ($input['displaysections'] as $displaycolumn => $columnwidgets)
		{
			$displayorder = 0;
			foreach ($columnwidgets as $columnwidget)
			{
				$columnwidgetid = intval($columnwidget['widgetId']);
				$columnwidgetinstanceid = intval($columnwidget['widgetInstanceId']);

				if (!$columnwidgetid)
				{
					continue;
				}

				if ($newTemplate)
				{
					$widgetInstanceId = 0;
				}
				else
				{
					$widgetInstanceId = $columnwidgetinstanceid;
					$savedWidgetInstanceIds[$widgetInstanceId] = $columnwidgetid;
				}

				$widget = array(
					'widgetinstanceid'	=> $widgetInstanceId,
					'pagetemplateid'	=> $pagetemplateid,
					'widgetid'			=> $columnwidgetid,
					'displaysection'	=> $displaycolumn,
					'displayorder'		=> $displayorder,
				);

				if (isset($columnwidget['subModules']))
				{
					$widget['subModules'] = $columnwidget['subModules'];
					$widget['displaySubModules'] = $columnwidget['displaySubModules'];

					if (!$newTemplate)
					{
						$savedWidgetInstanceIds += $this->getAllSubModulesInstances($columnwidget['subModules']);
					}
				}

				$widgets[] = $widget;

				++$displayorder;
			}
		}

		// check we are not adding a system widget
		$newWidgets = array_diff_key($savedWidgetInstanceIds, $currentWidgetInstanceIds);
		if ($newWidgets)
		{
			foreach($newWidgets AS $widgetId)
			{
				if ($widgetApi->isSystemWidget($widgetId))
				{
					throw new vB_Exception_Api('cannot_add_system_module');
				}
			}
		}

		// check we are not removing a system widget
		$deleteWidgets = array_diff_key($currentWidgetInstanceIds, $savedWidgetInstanceIds);
		if ($deleteWidgets)
		{
			foreach($deleteWidgets AS $widgetId)
			{
				if ($widgetApi->isSystemWidget($widgetId))
				{
					throw new vB_Exception_Api('cannot_remove_system_module');
				}
			}
		}
		// save widget placements on the page template
		foreach ($widgets as $widget)
		{
			$widgetinstanceid = $widget['widgetinstanceid'];
			unset($widget['widgetinstanceid']);

			$subModules = isset($widget['subModules']) ? $widget['subModules'] : array();
			unset($widget['subModules']);

			$displaySubModules = isset($widget['displaySubModules']) ? $widget['displaySubModules'] : array();
			unset($widget['displaySubModules']);

			if ($widgetinstanceid > 0 AND !$forceNewPage)
			{
				$this->db->update('widgetinstance', $widget, array('widgetinstanceid' => $widgetinstanceid));
			}
			else
			{
				$widgetinstanceid = $this->db->insert('widgetinstance', $widget);
				if (is_array($widgetinstanceid))
				{
					$widgetinstanceid = (int) array_pop($widgetinstanceid);
				}
			}

			// save submodules if available
			if (!empty($subModules))
			{
				$this->saveSubModules($pagetemplateid, $widgetinstanceid, $subModules, $displaySubModules, $forceNewPage);
			}
		}

		// remove any widgets that have been removed from the page template
		if (!empty($deleteWidgets))
		{
			$deleted = $widgetApi->deleteWidgetInstances(array_keys($deleteWidgets));
			if ($deleted != count($deleteWidgets))
			{
				throw new vB_Exception_Api('unable_to_delete_widget_instances');
			}
		}

		// --- save the page  ---------------------------------

		// permalink
		$urlprefix = $input['resturl'];

		$valuePairs = array(
			'title' => $input['pagetitle'],
			'pagetemplateid' => $pagetemplateid,
			'metakeywords' => $input['metakeywords'],
			'metadescription' => $input['metadescription'],
		);

		// save page
		if (!empty($page) AND !$forceNewPage)
		{
			// update page record
			$conditions = array(
				'pageid' => $page['pageid'],
			);
			$this->save($valuePairs, $conditions);
			$pageid = $page['pageid'];

			// update this page's current route if needed
			if ($input['resturl'] != $page['urlprefix'])
			{
				$data = array('prefix' => $urlprefix);
				if (isset($input['nodeid']) AND !empty($input['nodeid']))
				{
					$data['nodeid'] = $input['nodeid'];
				}
				$routeApi->updateRoute($page['routeid'],$data);
			}
		}
		else
		{
			$valuePairs['guid'] = vB_Xml_Export_Page::createGUID($valuePairs);

			// insert a new page
			$pageid = $this->save($valuePairs);
			if (is_array($pageid))
			{
				$pageid = (int) array_pop($pageid);
			}

			// route
			if (isset($page['routeid']))
			{
				// update this page's current route
				$data = array(
					'pageid' => $pageid,
					'prefix' => $urlprefix,
					'nodeid' => $input['nodeid']
				);
				$routeid = $routeApi->updateRoute($page['routeid'], $data);
			}
			else
			{
				$valuePairs = array(
					'prefix' => $urlprefix,
					'contentid' => $pageid,
				);
				$routeid = $routeApi->createRoute('vB5_Route_Page', $valuePairs);
			}
			if (is_array($routeid))
			{
				$routeid = (int) array_pop($routeid);
			}

			// update page with routeid (for deleting it when deleting a page)
			$routeApi->updateNewPageRoute($pageid, $routeid);
		}

		$page = $this->fetchPageById($pageid, array('nodeid'=>$input['nodeid']));
		vB_Cache::instance()->event('pageChg_' . $pageid);
		return array('url' => $page['url']);
	}

	protected function getAllCurrentModuleInstances($modules)
	{
		if (empty($modules))
		{
			return array();
		}
		else
		{
			$result = array();
			foreach($modules AS $module)
			{
				$result[$module['widgetinstanceid']] = $module['widgetid'];

				if (isset($module['subModules']))
				{
					$result += $this->getAllCurrentModuleInstances($module['subModules']);
				}
			}

			return $result;
		}
	}

	protected function getAllSubModulesInstances($subModules)
	{
		if (empty($subModules))
		{
			return array();
		}
		else
		{
			$result = array();
			foreach($subModules AS $module)
			{
				$widgetInstanceId = intval($module->widgetInstanceId);
				$widgetId = intval($module->widgetId);

				$result[$widgetInstanceId] = $widgetId;

				if (isset($module->subModules))
				{
					$result += $this->getAllSubModulesInstances($subModules);
				}
			}

			return $result;
		}
	}

	protected function saveSubModules($pageTemplateId, $widgetInstanceId, $subModules, $displaySubModules, $forceNewPage)
	{
		$subWidgetInstances = array();

		// save subwidget instances
		foreach ($subModules as $module)
		{
			$widgetinstanceid = intval($module->widgetInstanceId);
			$widget['widgetid'] = intval($module->widgetId);
			$widget['parent'] = intval($widgetInstanceId);
			$widget['pagetemplateid'] = intval($pageTemplateId);

			if (empty($widget['widgetid']))
			{
				continue;
			}

			if ($widgetinstanceid > 0 AND !$forceNewPage)
			{
				$this->db->update('widgetinstance', $widget, array('widgetinstanceid' => $widgetinstanceid));
			}
			else
			{
				$widgetinstanceid = $this->db->insert('widgetinstance', $widget);
				if (is_array($widgetinstanceid))
				{
					$widgetinstanceid = (int) array_pop($widgetinstanceid);
				}
			}
			$subWidgetInstances[] = $widgetinstanceid;

			// update visible modules
			$widgetApi = vB_Api::instance('widget');
			if (!($adminConfig = $widgetApi->fetchAdminConfig($widget['parent'])))
			{
				$adminConfig = array();
			}
			array_walk($displaySubModules, 'intval');
			$adminConfig['display_modules'] = $displaySubModules;
			$this->db->update('widgetinstance', array('adminconfig' => serialize($adminConfig)), array('widgetinstanceid' => $widget['parent']));

			// save submodules if available
			if (isset($module->subModules))
			{
				$this->saveSubModules($pageTemplateId, $widgetinstanceid, $module->subModules, $module->displaySubModules, $forceNewPage);
			}
		}
	}

	/** This returns the number and type of database asserts. This is similar to but a bit smaller than the number of queries executed.
	 *
	 *	@return 	mixed	array of 'queryCount', 'queries'. Integer and array of strings
	 * **/
	public function getQryCount()
	{
		$qryCount = vB::getDbAssertor()->getQryCount();

		if (!empty($_REQUEST) AND !empty($_REQUEST['querylist']))
		{
			$qryCount['showQueries'] = 1;
		}
		else
		{
			$qryCount['showQueries'] = 0;
			unset($qryCount['queries']);
		}
		return $qryCount;
	}

	/** This preloads information for the current page.
	*
	* 	@param	string	the identifier for this page, which comes from the route class.
	*
	***/
	public function preload($pageKey)
	{
		$this->lastCacheData = vB_Cache::instance(vB_Cache::CACHE_LARGE)->read("vbPre_$pageKey");

		//If we don't have anything, just return;
		if (!$this->lastCacheData)
		{
			return;
		}

		$this->lastpreCache = $this->lastCacheData['cachetime'];

		if (!empty($this->lastCacheData['data']))
		{
			foreach ($this->lastCacheData['data'] AS $class => $tasks)
			{
				try
				{
					$library = vB_Library::instance($class);
					foreach ($tasks AS $method => $params)
					{
						if (method_exists($library, $method))
						{
							$reflection = new ReflectionMethod($library, $method);
							$reflection->invokeArgs($library, $params);
						}
					}

				}
				catch(exception $e)
				{
					//nothing to do. Just try the other methods.
				}
			}
		}



	}

	/** This saves preload information for the current page.
	 *
	 * 	@param	string	the identifier for this page, which comes from the route class.
	 *
	 ***/
	public function savePreCacheInfo($pageKey)
	{
		$timenow = vB::getRequest()->getTimeNow();

		if (empty($this->preCacheInfo) OR
			(($timenow - intval($this->lastpreCache)) < self::MIN_PRECACHELIFE)
		)
		{
			return;
		}
		$data = array('cachetime' => $timenow, 'data' => $this->preCacheInfo);

		vB_Cache::instance(vB_Cache::CACHE_LARGE)->write("vbPre_$pageKey", $data, 300);
	}

	/** This saves preload information for the current page.
	 *
	 *	@param	string	name of the api class
	 * 	@param	string	name of the api method that should be called
	 *	@param	mixed	array of method parameters that should be passed
	 *
	 ***/
	public function registerPrecacheInfo($apiClass, $method, $params)
	{
		//if we have cached within the last five minutes do nothing.
		if ((vB::getRequest()->getTimeNow() - intval($this->lastpreCache)) < self::MIN_PRECACHELIFE)
		{
			return;
		}

		if (!isset($this->preCacheInfo[$apiClass]))
		{
			$this->preCacheInfo[$apiClass] = array();
		}

		$this->preCacheInfo[$apiClass][$method] = $params;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
