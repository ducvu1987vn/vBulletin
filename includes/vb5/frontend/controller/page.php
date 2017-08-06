<?php

class vB5_Frontend_Controller_Page extends vB5_Frontend_Controller
{

	function __construct()
	{
		parent::__construct();
	}

	function index($pageid)
	{
		$top = '';
		if (vB5_Request::get('cachePageForGuestTime') > 0 AND !vB5_User::get('userid'))
		{
			$fullPageKey = 'vBPage_' . md5(serialize($_REQUEST));
			$fullPage = vB_Cache::instance(vB_Cache::CACHE_LARGE)->read($fullPageKey);

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
		$pageKey = $router->getPageKey();

		$api->callApi('page', 'preload', array($pageKey));

		if (!empty($userAction))
		{
			$api->callApi('wol', 'register', array($userAction['action'], $userAction['params'], $pageKey, vB::getRequest()->getScriptPath()));
		}

		if (isset($arguments['pagenum']))
		{
			$arguments['pagenum'] = intval($arguments['pagenum']) > 0 ? intval($arguments['pagenum']) : 1;
		}
		$pageid = (int) (isset($arguments['pageid']) ? $arguments['pageid'] : (isset($arguments['contentid']) ? $arguments['contentid'] : 0));

		if ($pageid < 1)
		{
			// @todo This needs to output a user-friendly "page not found" page
			throw new Exception('Could not find page.');
		}

		$page = $api->callApi('page', 'fetchPageById', array($pageid, $arguments));
		if (!$page)
		{
			// @todo This needs to output a user-friendly "page not found" page
			throw new Exception('Could not find page.');
		}

		// Go to the first new / unread post for this user in this topic
		if (!empty($_REQUEST['goto']) AND $_REQUEST['goto'] == 'newpost' AND !empty($arguments['nodeid']) AND !empty($arguments['channelid']))
		{
			if ($this->vboptions['threadmarking'] AND vB5_User::get('userid'))
			{
				// Database read marking
				$channelRead = $api->callApi('node', 'getNodeReadTime', array($arguments['channelid']));
				$topicRead = $api->callApi('node', 'getNodeReadTime', array($arguments['nodeid']));
				$topicView = max($topicRead, $channelRead, time() - ($this->vboptions['markinglimit'] * 86400));
			}
			else
			{
				// Cookie read marking
				$topicView = intval(vB5_Cookie::fetchBbarrayCookie('discussion_view', $arguments['nodeid']));
				if (!$topicView)
				{
					$topicView = vB5_User::get('lastvisit');
				}
			}
			$topicView = intval($topicView);

			// Get the first unread reply
			$goToNodeId = $api->callApi('node', 'getFirstChildAfterTime', array($arguments['nodeid'], $topicView));

			if ($goToNodeId)
			{
				// Redirect to the new post
				$urlCache = vB5_Template_Url::instance();
				$urlKey = $urlCache->register($router->getRouteId(), array('nodeid' => $arguments['nodeid']), array('p' => $goToNodeId));
				$replacements = $urlCache->finalBuildUrls(array($urlKey));
				$url = $replacements[$urlKey];
				if ($url)
				{
					$url .= '#post' . $goToNodeId;
					if (headers_sent())
					{
						echo '<script type="text/javascript">window.location = "' . $url . '";</script>';
					}
					else
					{
						header('Location: ' . $url);
					}
					exit;
				}
			}
		}

		$page['routeInfo'] = array(
			'routeId' => $router->getRouteId(),
			'arguments'	=> $arguments,
			'queryParameters' => $router->getQueryParameters()
		);
		$page['crumbs'] = $router->getBreadcrumbs();
		$page['pageKey'] = $pageKey;

		$queryParameters = $router->getQueryParameters();
		$arguments = array_merge($queryParameters, $arguments);
		foreach ($arguments AS $key => $value)
		{
			$page[$key] = $value;
		}

		// Charset
		$page['charset'] = vB5_String::getTempCharset();

		// Meta Keywords and Description
		// If a page doesn't have keywords or description, then we use global set ones
		if (empty($page['metakeywords']) OR empty($page['metadescription']))
		{
			$options = vB5_Template_Options::instance();
			if (empty($page['metakeywords']))
			{
				$page['metakeywords'] = $options->get('options.keywords');
			}
			if (empty($page['metadescription']))
			{
				$page['metadescription'] = $options->get('options.description');
			}
		}

		$config = vB5_Config::instance();
		// Non-persistent notices @todo - change this to use vB_Cookie
		$page['ignore_np_notices'] = isset($_COOKIE[$config->cookie_prefix . 'np_notices_displayed']) ? explode(',', $_COOKIE[vB5_Config::instance()->cookie_prefix . 'np_notices_displayed']) : array();


		$templateCache = vB5_Template_Cache::instance();
		$templateCache->preload($page['pageid']);

		$templater = new vB5_Template($page['screenlayouttemplate']);

		//IMPORTANT: If you add any variable to the page object here,
		// please make sure you add them to other controllers which create page objects.
		// That includes at a minimum the search controller (in two places currently)
		// and vB5_ApplicationAbstract::showErrorPage

		$templater->registerGlobal('page', $page);
		$page = $this->outputPage($templater->render(), false);
		$fullPage = $top . $page;

		if (!empty($fullPageKey) AND is_string($fullPageKey))
		{
			vB_Cache::instance(vB_Cache::CACHE_LARGE)->write($fullPageKey, $fullPage, vB5_Request::get('cachePageForGuestTime'));
		}

		// these are the templates rendered for this page
		$loadedTemplates = vB5_Template::getRenderedTemplates();

		$templateCache->storePreload($page, $loadedTemplates);

		$api->callApi('page', 'savePreCacheInfo', array($pageKey));

		if (!vB5_Request::get('useEarlyFlush'))
		{
			echo $fullPage;
		}
		else
		{
			echo $page;
		}
	}

	/**
	 * This method is used from template code to render a template and store it in a variable
	 * @param string $templateName
	 * @param array $data
	 * @param bool $isParentTemplate
	 */
	public static function renderTemplate($templateName, $data = array(), $isParentTemplate=true)
	{
		if (empty($templateName))
		{
			return null;
		}

		return vB5_Template::staticRender($templateName, $data, $isParentTemplate);
	}
}
