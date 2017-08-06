<?php

// TODO: replace this controller with page one
class vB5_Frontend_Controller_Search extends vB5_Frontend_Controller
{
	function __construct()
	{
		parent::__construct();
	}

	function actionIndex()
	{
		$top = '';
		if (vB5_Request::get('cachePageForGuestTime') > 0 AND !vB5_User::get('userid'))
		{
			$fullPageKey = md5(serialize($_REQUEST));
			$fullPage = vB_Cache::instance()->read($fullPageKey);
			if (!empty($fullPage))
			{
				echo $fullPage;
				exit;
			}
		}

		$templater = new vB5_Template('preheader');
		$preheader = $templater->render();
		$top .= $preheader;

		if (vB5_Request::get('useEarlyFlush'))
		{
			echo $preheader;
			flush();
		}

		$api = Api_InterfaceAbstract::instance();
		$router = vB5_ApplicationAbstract::instance()->getRouter();
		$arguments = $router->getArguments();
		$userAction = $router->getUserAction();
		if (!empty($userAction))
		{
			$api->callApi('wol', 'register', array($userAction['action'], $userAction['params']));
		}

		$pageid = (int) (isset($arguments['pageid']) ? $arguments['pageid'] : $arguments['contentid']);

		$page = $api->callApi('page', 'fetchPageById', array($pageid, $arguments));
		if (!$page)
		{
			// @todo This needs to output a user-friendly "page not found" page
			throw new Exception('Could not find page.');
		}
		$serverData = array_merge($_GET, $_POST);
		$page['title'] = 'Advanced Search';
		$page['url'] = vB5_Route::buildUrl('advanced_search');
		$page['crumbs'] = $router->getBreadcrumbs();

		if(!empty($serverData['cookie']))
		{
			$page['searchJSON'] = '{"specific":['.$_COOKIE[$serverData['cookie']].']}';
		}

		if(!empty($serverData['searchJSON']))
		{
			$decoded = json_decode($serverData['searchJSON'],true);
			if (!empty($decoded))
			{
				$page['searchJSON'] = json_encode($decoded);
			}
		}
		elseif (!empty($serverData['r']))
		{
			$page['resultId'] = $serverData['r'];
			if(!empty($serverData['p']) && is_numeric($serverData['p'])){
				$page['currentPage'] = intval($serverData['p']);
			}
		}
		elseif (!empty($serverData['e']))
		{
			$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
			if (strlen($path) AND $path{0} == '/')
			{
				$path = substr($path, 1);
			}
			$route = $api->callApi('route', 'getRoute', array('pathInfo' => 'advanced_search', 'queryString' => $_SERVER['QUERY_STRING']));
			$page = $api->callApi('page', 'fetchPageById', array($route['arguments']['pageid']));
			$page['resultId'] = $serverData['e'];
		}
		$page['ignore_np_notices'] = isset($_COOKIE[vB5_Config::instance()->cookie_prefix . 'np_notices_displayed']) ? explode(',', $_COOKIE[vB5_Config::instance()->cookie_prefix . 'np_notices_displayed']) : array();
		$page['charset'] = vB5_String::getTempCharset();

		$templater = new vB5_Template($page['screenlayouttemplate']);
		$templater->registerGlobal('page', $page);
		$page = $this->outputPage($templater->render(), false);
		$fullPage = $top . $page;

		if (vB5_Request::get('cachePageForGuestTime') > 0 AND !vB5_User::get('userid'))
		{
			vB_Cache::instance()->write($fullPageKey, $fullPage, vB5_Request::get('cachePageForGuestTime')); 
		}

		if (!vB5_Request::get('useEarlyFlush'))
		{
			echo $fullPage;
		}	
		else
		{
			echo $page;
		}
	}

	function index()
	{
		return $this->actionIndex();
	}

	function actionResult()
	{
		$top = '';
		if (vB5_Request::get('cachePageForGuestTime') > 0 AND !vB5_User::get('userid'))
		{
			$fullPageKey = md5(serialize($_REQUEST));
			$fullPage = vB_Cache::instance()->read($fullPageKey);
			if (!empty($fullPage))
			{
				echo $fullPage;
				exit;
			}
		}

		$templater = new vB5_Template('preheader');
		$preheader = $templater->render();
		$top .= $preheader;

		if (vB5_Request::get('useEarlyFlush'))
		{
			echo $preheader;
			flush();
		}

		$serverData = array_merge($_GET, $_POST);
		$api = Api_InterfaceAbstract::instance();
		$router = vB5_ApplicationAbstract::instance()->getRouter();
		$arguments = $router->getArguments();
		$userAction = $router->getUserAction();

		if (!empty($userAction))
		{
			$api->callApi('wol', 'register', array($userAction['action'], $userAction['params']));
		}

		// if Human verification is required, and we don't have 'q' set in serverData (means the user is using
		//   the quick search box), we redirect user to advanced search page with HV
		$requirehv = $api->callApi('hv', 'fetchRequireHvcheck', array('search'));
		if (!empty($serverData['AdvSearch']) OR ($requirehv AND isset($serverData['q'])))
		{
			$adv_search = $api->callApi('route', 'getRoute', array('pathInfo' => 'advanced_search', 'queryString' =>''), true);
			$arguments = $adv_search['arguments'];
		}
		elseif ($requirehv)
		{
			// Advanced search form submitted
			if (empty($serverData['humanverify']))
			{
				$serverData['humanverify'] = array();
			}
			$return = $api->callApi('hv', 'verifyToken', array($serverData['humanverify'], 'search'));

			if ($return !== true)
			{
				$adv_search = $api->callApi('route', 'getRoute', array('pathInfo' => 'advanced_search', 'queryString' =>''), true);
				$arguments = $adv_search['arguments'];
				$error = $return['errors'][0][0];
			}
		}

		$pageid = (int) (isset($arguments['pageid']) ? $arguments['pageid'] : $arguments['contentid']);

		$page = $api->callApi('page', 'fetchPageById', array($pageid, $arguments));
		if (!$page)
		{
			echo 'Could not find page.';
			exit;
		}
		$page['crumbs'] = array(
			0 => array(
				'title' => 'Advanced Search',
				'url' => vB5_Template_Runtime::buildUrl('advanced_search', array(), array(), array('noBaseUrl' => true))
			),
			1 => array(
				'title' => 'Search Results',
				'url' => ''
			)
		);
		if(!empty($serverData['searchJSON']))
		{
			if (is_string($serverData['searchJSON']))
			{
				if(preg_match('/[^\x00-\x7F]/', $serverData['searchJSON']))
				{
					$serverData['searchJSON'] = vB5_String::toUtf8($serverData['searchJSON'], vB5_String::getTempCharset());
				}
				$serverData['searchJSON'] = json_decode($serverData['searchJSON'], true);
			}
			if (!empty($serverData['searchJSON']))
			{
				if (!empty($serverData['searchJSON']['keywords']))
				{
					$serverData['searchJSON']['keywords'] = str_replace(array('"', '\\'), '', $serverData['searchJSON']['keywords']);
					$serverData['searchJSON']['keywords'] = filter_var($serverData['searchJSON']['keywords'], FILTER_SANITIZE_STRING);
				}
				$serverData['searchJSON'] = json_encode($serverData['searchJSON']);
			}
			else
			{
				$serverData['searchJSON'] = '';
			}
			$page['searchJSON'] = $serverData['searchJSON'];
			$extra = array('searchJSON' => !empty($serverData['searchJSON'])?$serverData['searchJSON']:'{}');
			if (!empty($serverData['AdvSearch']))
			{
				$extra['AdvSearch'] = 1;
			}
			$page['url'] = str_replace('&amp;', '&', vB5_Route::buildUrl('search', array(),$extra));
			//$page['searchJSONStructure'] = json_decode($page['searchJSON'],true);
			$page['crumbs'][0]['url'] = vB5_Template_Runtime::buildUrl('advanced_search', array(),array('searchJSON' => $page['searchJSON']), array('noBaseUrl' => true));
		}
		elseif (!empty($serverData['q']))
		{
			$serverData['q'] = str_replace(array('"', '\\'), '', $serverData['q']);
			$serverData['q'] = filter_var($serverData['q'], FILTER_SANITIZE_STRING);
			$searchType = '';

			if (!empty($serverData['type']))
			{
				$serverData['type'] = str_replace(array('"', '\\'), '', $serverData['type']);
				$serverData['type'] = filter_var($serverData['type'], FILTER_SANITIZE_STRING);
				$searchType = ',"type":"' . $serverData['type'] . '"';
			}

			$page['searchJSON'] = '{"keywords":"' . $serverData['q'] . '","sort":"title"' . $searchType . '}';
			$extra = array('q' => $serverData['q']);
			if (!empty($serverData['AdvSearch']))
			{
				$extra['AdvSearch'] = 1;
			}
			$page['url'] = str_replace('&amp;', '&', vB5_Route::buildUrl('search', array(),$extra));
			$page['searchStr'] = $serverData['q'];
			$page['crumbs'][0]['url'] = vB5_Template_Runtime::buildUrl('advanced_search', array(''),array('searchJSON' => $page['searchJSON']), array('noBaseUrl' => true));
		}
		elseif (!empty($serverData['r']))
		{
			unset($page['crumbs'][0]);
			$page['url'] = str_replace('&amp;', '&', vB5_Route::buildUrl('search', array(),array('r' => $serverData['r'])));
			$page['resultId'] = $serverData['r'];
			if(!empty($serverData['p']) && is_numeric($serverData['p'])){
				$page['currentPage'] = intval($serverData['p']);
			}
			$page['crumbs'][0]['url'] = vB5_Template_Runtime::buildUrl('advanced_search', array(),array('r' => $serverData['r']), array('noBaseUrl' => true));
		}
		else
		{
			return $this->index();
		}
		$page['ignore_np_notices'] = isset($_COOKIE[vB5_Config::instance()->cookie_prefix . 'np_notices_displayed']) ? explode(',', $_COOKIE[vB5_Config::instance()->cookie_prefix . 'np_notices_displayed']) : array();
		$page['charset'] = vB5_String::getTempCharset();
		if (!empty($error))
		{
			$page['error'] = $error;
		}

		$templater = new vB5_Template($page['screenlayouttemplate']);
		$templater->registerGlobal('page', $page);
		$page = $this->outputPage($templater->render(), false);
		$fullPage = $top . $page;

		if (vB5_Request::get('cachePageForGuestTime') > 0 AND !vB5_User::get('userid'))
		{
			vB_Cache::instance()->write($fullPageKey, $fullPage, vB5_Request::get('cachePageForGuestTime')); 
		}

		if (!vB5_Request::get('useEarlyFlush'))
		{
			echo $fullPage;
		}	
		else
		{
			echo $page;
		}
	}

	function results()
	{
		return $this->actionResult();
	}

	public function actionFetchTagCloud()
	{
		$taglevels = 5;
		$limit = 20;
		$type = 'search';
		$serverData = array_merge($_GET, $_POST);
		$type = empty($serverData['type']) ? 'search' : $serverData['type'];
		$taglevels = empty($serverData['taglevels']) ? 5 : $serverData['taglevels'];
		$limit = empty($serverData['limit']) ? 20 : $serverData['limit'];

		$tags = vB_Api::instanceInternal('Tags')->fetchTagsForCloud($taglevels, $limit, $type);
		$templater = new vB5_Template('tag_cloud');
		$templater->register('tags', $tags);
		$templater->register('noformat', $serverData['noformat']);
		$this->sendAsJson($templater->render());
	}
}
