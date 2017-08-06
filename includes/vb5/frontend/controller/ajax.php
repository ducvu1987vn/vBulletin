<?php

class vB5_Frontend_Controller_Ajax extends vB5_Frontend_Controller
{

	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Handles all calls to /ajax/* and routes them to the correct method in
	 * this controller, then sends the result as JSON.
	 *
	 * @todo: This should probably be in the router
	 *
	 * @param	string	Route
	 */
	public function index($route)
	{
		ob_start();
		$route = trim(strval($route), '/');
		$segments = explode('/', $route);

		// change method-name to actionMethodName
		$method = array_shift($segments);
		$method = preg_replace('#-(.)#e', 'strtoupper("$1")', strtolower($method));
		$method = 'action' . ucfirst($method);

		if (method_exists($this, $method))
		{
			$returnValue = call_user_func_array(array($this, $method), $segments);
		}
		else
		{
			exit('Invalid AJAX method called');
			//$returnValue = array('error' => 'Invalid AJAX method called');
		}
		$errors = trim(ob_get_clean());
		if (!empty($errors))
		{
			if (!is_array($returnValue))
			{
				$returnValue = array($returnValue);
				$returnValue['wasNotArray'] = 1;
			}

			if (empty($returnValue['errors']))
			{
				$returnValue['errors'] = array();
			}
			array_push($returnValue['errors'], $errors);
		}
		$this->sendAsJson($returnValue);
	}

	/**
	 * Ajax calls to /ajax/api/[controller]/[method] allow calling the API directly
	 * via this function without cross-domain requests
	 *
	 * @param	string	API controller
	 * @param	string	API method
	 *
	 * @param	mixed	The return value of the API call
	 */
	public function actionApi($controller, $method)
	{
		if (!empty($controller))
		{
			$serverData = array_merge($_GET, $_POST);
			return Api_InterfaceAbstract::instance(Api_InterfaceAbstract::API_COLLAPSED)->callApi($controller, $method, $serverData, true);
		}
		return null;
	}

	/**
	 * Ajax calls to /ajax/call/[controller]/[method] allow calling a
	 * presentation controller
	 *
	 * @param	string	API controller
	 * @param	string	API method
	 *
	 * @param	mixed	The return value of the API call
	 */
	public function actionCall($controller, $method)
	{
		if (!empty($controller))
		{
			$args = array_merge($_GET, $_POST);
			$class = 'vB5_Frontend_Controller_' . ucfirst($controller);

			// TODO: This is a temporary fix for VBV-4731. Only 'action' methods can be called from ajax/call
			if (strpos($method, 'action') !== 0)
			{
				$method = 'action' . $method;
			}

			if (!class_exists($class) || !method_exists($class, $method))
			{
				return null;
			}
			else
			{
				$object = new $class;
			}

			$reflection = new ReflectionMethod($object, $method);

			if($reflection->isConstructor() || $reflection->isDestructor() || $reflection->isStatic() )
			{
				return null;
			}

			$php_args = array();
			foreach($reflection->getParameters() as $param)
			{
				if(isset($args[$param->getName()]))
				{
					$php_args[] = &$args[$param->getName()];
				}
				else
				{
					if ($param->isDefaultValueAvailable())
					{
						$php_args[] = $param->getDefaultValue();
					}
					else
					{
						throw new Exception('Required argument missing: ' . htmlspecialchars($param->getName()));
						return null;
					}
				}
			}

			return $reflection->invokeArgs($object, $php_args);
		}
		return null;
	}


	/**
	 * Ajax calls to /ajax/render/[template] renders a template with the
	 * via this function without cross-domain requests
	 *
	 * @param	string	API controller
	 * @param	string	API method
	 *
	 * @param	mixed	The return value of the API call
	 */
	public function actionRender($template, $data = array(), $isParentTemplate=true)
	{
		if (empty($template))
		{
			return null;
		}
		$serverData = array_merge($_GET, $_POST, $data);

		return vB5_Template::staticRender($template, $serverData, $isParentTemplate);
	}


	/**
	 * Renders a widget or screen layout admin template in the presentation layer and
	 * returns it as JSON
	 * Ajax calls should go to /ajax/admin-template/widget or /ajax/admin-template/screen-layout
	 *
	 * @param	string	The type of template requested (widget or screen-layout)
	 */
	public function actionAdminTemplate($type)
	{
		if ($type == 'widget')
		{
			$pagetemplateid = isset($_REQUEST['pagetemplateid']) ? intval($_REQUEST['pagetemplateid']) : 0;

			if (isset($_REQUEST['widgets']) AND is_array($_REQUEST['widgets']))
			{
				// requesting multiple widget admin templates
				$requestedWidgets = array();
				$requestedWidgetIds = array();
				$requestedWidgetInstanceIds = array();
				foreach ($_REQUEST['widgets'] AS $widget)
				{
					$widgetId = isset($widget['widgetid']) ? intval($widget['widgetid']) : 0;
					$widgetInstanceId = isset($widget['widgetinstanceid']) ? intval($widget['widgetinstanceid']) : 0;

					if ($widgetId < 1)
					{
						continue;
					}

					$requestedWidgets[] = array(
						'widgetid' => $widgetId,
						'widgetinstanceid' => $widgetInstanceId,
					);
					$requestedWidgetIds[] = $widgetId;
					$requestedWidgetInstanceIds[] = $widgetInstanceId;
				}

				$requestedWidgetIds = array_unique($requestedWidgetIds);
				$requestedWidgetInstanceIds = array_unique($requestedWidgetInstanceIds);

				if (!empty($requestedWidgetIds))
				{
					$widgets = Api_InterfaceAbstract::instance()->callApi('widget', 'fetchWidgets', array('widgetids' => $requestedWidgetIds));
				}
				else
				{
					$widgets = array();
				}

				if (!empty($requestedWidgetInstanceIds))
				{
					$widgetInstances = Api_InterfaceAbstract::instance()->callApi('widget', 'fetchWidgetInstances', array('widgetinstanceids' => $requestedWidgetInstanceIds));
				}
				else
				{
					$widgetInstances = array();
				}

				$widgetsOut = array();
				foreach ($requestedWidgets AS $requestedWidget)
				{
					if (!isset($widgets[$requestedWidget['widgetid']]))
					{
						continue;
					}

					$widget = $widgets[$requestedWidget['widgetid']];

					// we may want to pull the whole widget instance and send it to the template if needed
					$widget['widgetinstanceid'] = $requestedWidget['widgetinstanceid'];

					$templateName = empty($widget['admintemplate']) ? 'admin_widget_default' : $widget['admintemplate'];
					$templater = new vB5_Template($templateName);
					$templater->register('widget', $widget);

					if (isset($widgetInstances[$widget['widgetinstanceid']]) AND is_array($widgetInstances[$widget['widgetinstanceid']]))
					{
						$widgetInstance = $widgetInstances[$widget['widgetinstanceid']];
						$displaySection = $widgetInstance['displaysection'] >= 0 ? $widgetInstance['displaysection'] : 0;
						$displayOrder = $widgetInstance['displayorder'] >= 0 ? $widgetInstance['displayorder'] : 0;
					}
					else
					{
						$displaySection = $displayOrder = 0;
					}

					$widgetsOut[] = array(
						'widgetid'         => $widget['widgetid'],
						'widgetinstanceid' => $widget['widgetinstanceid'],
						'displaysection'   => $displaySection,
						'displayorder'     => $displayOrder,
						'pagetemplateid'   => $pagetemplateid,
						'template'         => $templater->render(),
					);
				}

				$output = array(
					'widgets'        => $widgetsOut,
					'pagetemplateid' => $pagetemplateid,
				);
			}
			else
			{
				// requesting one widget admin template
				$widgetid = isset($_REQUEST['widgetid']) ? intval($_REQUEST['widgetid']) : 0;
				$widgetinstanceid = isset($_REQUEST['widgetinstanceid']) ? intval($_REQUEST['widgetinstanceid']) : 0;

				$widget = Api_InterfaceAbstract::instance()->callApi('widget', 'fetchWidget', array('widgetid' => $widgetid));

				// we may want to pull the whole widget instance and send it to the template if needed
				$widget['widgetinstanceid'] = $widgetinstanceid;

				$templateName = empty($widget['admintemplate']) ? 'admin_widget_default' : $widget['admintemplate'];
				$templater = new vB5_Template($templateName);
				$templater->register('widget', $widget);

				$output = array(
					'widgetid'         => $widgetid,
					'widgetinstanceid' => $widgetinstanceid,
					'pagetemplateid'   => $pagetemplateid,
					'template'         => $templater->render(),
				);
			}

			return $output;
		}
		else if ($type == 'screen-layout')
		{
			// @todo implement this
		}
	}

	/**
	 * Returns the widget template
	 *
	 * Ajax calls should go to /ajax/fetch-widget-template
	 *
	 * @param	string	The type of template requested (widget or screen-layout)
	 */
	public function actionFetchWidgetTemplate()
	{
		$api = Api_InterfaceAbstract::instance();

		$widgetId = intval($_POST['widgetid']);

		$widget = $api->callApi('widget', 'fetchWidget', array($widgetId));

		$templateName = empty($widget['admintemplate']) ? 'admin_widget_default' : $widget['admintemplate'];
		$templater = new vB5_Template($templateName);
		$templater->register('widget', $widget);

		try
		{
			$template = $templater->render();
		}
		catch (Exception $e)
		{
			$template = FALSE;
		}

		return $template;
	}

	/**
	 * Returns an array of widget objects which include some of the widget information available
	 * via the widget-fetchWidgets API call *and* the rendered admin template to display the
	 * widget on the page canvas when editing a page template. The widget admin template
	 * is rendered here (client side)
	 *
	 * Ajax calls should go to /ajax/fetch-widget-admin-template-list
	 *
	 * @param	string	The type of template requested (widget or screen-layout)
	 */
	public function actionFetchWidgetAdminTemplateList()
	{
		$api = Api_InterfaceAbstract::instance();

		if (isset($_POST['widgetids']) AND is_array($_POST['widgetids']))
		{
			$widgetids = array_map('intval', $_POST['widgetids']);
			$widgetids = array_unique($widgetids);
		}
		else
		{
			$widgetids = array(); // retrieve all widgets
		}

		$widgets = $api->callApi('widget', 'fetchWidgets', array($widgetids));

		$widgetsOut = array();

		foreach ($widgets AS $widget)
		{
			// note-- we never have a widgetinstanceid here, since this is only for
			// new widget instances.

			//do not include the 'dependent' widgets (e.g. Conversation Display)
			if ($widget['category'] == vB_Api_Widget::WIDGETCATEGORY_SYSTEM)
			{
				continue;
			}

			$widgetsOut[] = $widget;
		}

		return $widgetsOut;
	}

	/**
	 * Returns an array of quotes
	 *
	 */
	public function actionFetchQuotes()
	{
		$quotes = array();
		$nodeids = isset($_REQUEST['nodeid']) ? $_REQUEST['nodeid'] : array();

		if (!empty($nodeids))
		{
			$contenttypes = vB_Types::instance()->getContentTypes();
			$typelist = array();
			foreach ($contenttypes as $key => $type)
			{
				$typelist[$type['id']] = $key;
			}

			$api = Api_InterfaceAbstract::instance();
			$contentTypes = array('vBForum_Text', 'vBForum_Gallery', 'vBForum_Poll', 'vBForum_Video', 'vBForum_Link');

			foreach ($nodeids as $nodeid)
			{
				$node = $api->callApi('node', 'getNode', array($nodeid));
				$contentType = $typelist[$node['contenttypeid']];
				if (in_array($contentType, $contentTypes))
				{
					$quotes[$nodeid] = $api->callApi('content_' . strtolower(substr($contentType, 8)), 'getQuotes', array($nodeid));
				}
			}
		}

		return $quotes;
	}

	/**
	 * Returns the sitebuilder template markup required for using sitebuilder
	 *
	 * @param	int	The page id
	 */
	public function actionActivateSitebuilder()
	{
		$sb = array();
		$pageId = isset($_REQUEST['pageid']) ? intval($_REQUEST['pageid']) : 0;
		if ($pageId > 0)
		{
			$api = Api_InterfaceAbstract::instance();

			$arguments = array(
				'pageid'	=>	$pageId,
				'nodeid' 	=>	isset($_REQUEST['nodeid']) ? intval($_REQUEST['nodeid']) : 0,
			);

			$page = $api->callApi('page', 'fetchPageById', array($pageId, $arguments));

			if ($page)
			{
				$router = vB5_ApplicationAbstract::instance()->getRouter();
				$page['routeInfo'] = array(
					'routeId' => $router->getRouteId(),
					'arguments'	=> $arguments
				);

				$queryParameters = $router->getQueryParameters();
				$arguments = array_merge($queryParameters, $arguments);
				foreach ($arguments AS $key => $value)
				{
					$page[$key] = $value;
				}

				$templater = new vB5_Template('admin_sitebuilder_menu');
				$sb['menu'] = $templater->render();

				$templater = new vB5_Template('admin_sitebuilder');
				$templater->registerGlobal('page', $page);
				$sb['template'] = $templater->render();
			}
		}
		return $sb;
	}

	/**
	 * Posts a comment to a conversation reply.
	 *
	 */
	public function actionPostComment()
	{
		$results = array();
		$input = array(
			'text' => (isset($_POST['text']) ? trim(strval($_POST['text'])) : ''),
			'parentid' => (isset($_POST['parentid']) ? intval($_POST['parentid']) : 0),
			'postindex' => (isset($_POST['postindex']) ? intval($_POST['postindex']) : 1),
			'view'	=> (isset($_POST['view']) ? trim(strval($_POST['view'])) : 'thread'),
			'redirecturl' => (isset($_POST['redirecturl']) ? intval($_POST['redirecturl']) : 0),
			'isblogcomment' => (isset($_POST['isblogcomment']) ? intval($_POST['isblogcomment']) : 0),
			'hvinput' => (isset($_POST['humanverify']) ? $_POST['humanverify'] : ''),
		);
		if (!empty($_POST['recaptcha_challenge_field']))
		{
			// reCaptcha fields
			$input['hvinput']['recaptcha_challenge_field'] = $_POST['recaptcha_challenge_field'];
			$input['hvinput']['recaptcha_response_field'] = $_POST['recaptcha_response_field'];
		}

		if ($input['parentid'] > 0 AND !empty($input['text']))
		{
			$api = Api_InterfaceAbstract::instance();
			$user  = $api->callApi('user', 'fetchUserinfo', array());
			$textData = array(
				'parentid' => $input['parentid'],
				'rawtext' => nl2br($input['text']),
				'userid' => $user['userid'],
				'authorname' => $user['username'],
				'created' => time(),
				'hvinput' => $input['hvinput'],
				'publishdate' => $api->callApi('content_text', 'getTimeNow', array())
			);

			$nodeId = $api->callApi('content_text', 'add', array($textData));

			if (is_int($nodeId) AND $nodeId > 0)
			{
				$node = $api->callApi('content_text', 'getContent', array($nodeId));
				if ($node)
				{
					$node = $node[$nodeId];

					if ($input['redirecturl'])
					{
						//send redirecturl to the client to indicate that it must redirect to the starter detail page after posting a comment to a reply
						$starterNode = $api->callApi('node', 'getNode', array($node['starter']));
						$results['redirecturl'] = vB5_Config::instance()->baseurl . vB5_Route::buildUrl($starterNode['routeid'], $starterNode, array('view' => 'stream', 'p' => $nodeId)) . '#post' . $nodeId;
					}
					else
					{
						//get parent node
						$parentNode = $api->callApi('node', 'getNodeContent', array($input['parentid']));
						if (!empty($parentNode))
						{
							$parentNode = $parentNode[$input['parentid']];
							$totalComments = $parentNode['textcount'];
						}
						else
						{
							$totalComments = 1;
						}

						$templater = new vB5_Template('conversation_comment_item');
						$templater->register('conversation', $node);
						$templater->register('commentIndex', $totalComments);
						$templater->register('conversationIndex', $input['postindex']);
						$templater->register('parentNodeIsBlog', $input['isblogcomment']);

						$enableInlineMod = (
							!empty($parentNode['moderatorperms']['canmoderateposts']) OR
							!empty($parentNode['moderatorperms']['candeleteposts']) OR
							!empty($parentNode['moderatorperms']['caneditposts']) OR
							!empty($parentNode['moderatorperms']['canremoveposts'])
						);
						$templater->register('enableInlineMod', $enableInlineMod);

						$results['template'] = $templater->render();
						$results['totalcomments'] = $totalComments;
						$results['nodeId'] = $nodeId;
					}
				}
			}
			else
			{
				$errorphrase = array_shift($nodeId['errors'][0]);
				$errorargs = $nodeId['errors'][0];
				$phrases = $api->callApi('phrase', 'fetch', array($errorphrase));
				$results['error'] = vsprintf($phrases[$errorphrase], $errorargs);
			}
		}
		else if (empty($input['text']))
		{
			$results['error'] = 'Blank comment is not allowed.';
		}
		else
		{
			$results['error'] = 'Cannot post comment.';
		}
		return $results;
	}

	/**
	 * Fetches comments of a conversation reply.
	 *
	 */
	public function actionFetchComments()
	{
		$results = array();
		$input = array(
			'parentid'			=> (isset($_POST['parentid']) ? intval($_POST['parentid']) : 0),
			'page'				=> (isset($_POST['page']) ? intval($_POST['page']) : 0),
			'postindex'			=> (isset($_POST['postindex']) ? intval($_POST['postindex']) : 1),
			'isblogcomment' 	=> (isset($_POST['isblogcomment']) ? intval($_POST['isblogcomment']) : 0),
			'widgetInstanceId'	=> (isset($_POST['widgetInstanceId']) ? intval($_POST['widgetInstanceId']) : 0),
		);
		if ($input['page'] == 0)
		{
			$is_default = true;
			$input['page'] = 1;
		}
		if ($input['parentid'] > 0)
		{
			$params = array(
				'parentid'			=> $input['parentid'],
				'page'				=> $input['page'],
				'perpage'			=> 25, // default to 25
				'depth'				=> 1,
				'contenttypeid'		=> null,
				'options'			=> array('sort' => array('created' => 'ASC'))
			);

			$api = Api_InterfaceAbstract::instance();

			// get comment perpage setting from widget config
			$widgetConfig = $api->callApi('widget', 'fetchConfig', array($input['widgetInstanceId']));
			$params['perpage'] = $commentsPerPage = !empty($widgetConfig['commentsPerPage']) ? $widgetConfig['commentsPerPage'] : 25;
			$initialCommentsPerPage = isset($widgetConfig['initialCommentsPerPage']) ? $widgetConfig['initialCommentsPerPage'] : 3;
			//get parent node's total comment count
			$parentNode = $api->callApi('node', 'getNodeContent', array($input['parentid']));
			$totalComments = 1;
			if ($parentNode)
			{
				$parentNode = $parentNode[$input['parentid']];
				$totalComments = $parentNode['textcount'];
			}
			$totalPages = ceil($parentNode['textcount'] / $commentsPerPage);
			// flip the pages, first page will have the oldest comments
			$params['page'] = $totalPages - $input['page'] + 1;
			if (!empty($is_default) AND $params['page'] == $totalPages AND ($rem =  $parentNode['textcount'] % $commentsPerPage) > 0 AND $rem <= $initialCommentsPerPage)
			{
				$params['page'] --;
			}
			$nodes = $api->callApi('node', 'listNodeContent', $params);
			if ($nodes)
			{

				$results['totalcomments'] = $totalComments;
				$results['page'] = $totalPages - $params['page'] + 1;
				$commentIndex = (($params['page'] - 1) * $params['perpage']) + 1;
				if ($commentIndex < 1)
				{
					$commentIndex = 1;
				}

				$enableInlineMod = (
					!empty($parentNode['moderatorperms']['canmoderateposts']) OR
					!empty($parentNode['moderatorperms']['candeleteposts']) OR
					!empty($parentNode['moderatorperms']['caneditposts']) OR
					!empty($parentNode['moderatorperms']['canremoveposts'])
				);

				$results['templates'] = array();
				$templater = new vB5_Template('conversation_comment_item');
//				$nodes = array_reverse($nodes, true);
				//loop backwards because we need to display the comments in ascending order
// 				for ($i = count($nodes) - 1; $i >= 0; $i--)
// 				{
// 					$node = $nodes[$i];
// 					$templater->register('conversation', $node['content']);
// 					$templater->register('commentIndex', $commentIndex);
// 					$templater->register('conversationIndex', $input['postindex']);
// 					$results['templates'][$node['nodeid']] = $templater->render();
// 					++$commentIndex;
// 				}

				foreach ($nodes as $node)
				{
					$templater->register('conversation', $node['content']);
					$templater->register('commentIndex', $commentIndex);
					$templater->register('conversationIndex', $input['postindex']);
					$templater->register('parentNodeIsBlog', $input['isblogcomment']);
					$templater->register('enableInlineMod', $enableInlineMod);
					$results['templates'][$node['nodeid']] = $templater->render();
					++$commentIndex;
				}
				//$results['templates'] = array_reverse($results['templates'], true);
			}
			else
			{
				$results['error'] = 'Error fetching comments.';
			}
		}
		else
		{
			$results['error'] = 'Cannot fetch comments.';
		}

		return $results;
	}

	public function actionFetchHiddenModules()
	{
		$api = Api_InterfaceAbstract::instance();

		$result = array();

		if (isset($_POST['modules']) AND !empty($_POST['modules']))
		{
			$widgets = $api->callApi('widget', 'fetchWidgetInstanceTemplates', array($_POST['modules']));

			if ($widgets)
			{
				// register the templates, so we use bulk fetch
				$templateCache = vB5_Template_Cache::instance();
				foreach($widgets AS $widget)
				{
					$templateCache->register($widget['template'], array());
				}

				// now render them
				foreach($widgets AS $widget)
				{
					$result[] = array(
						'widgetinstanceid' => $widget['widgetinstanceid'],
						'template' => $this->actionRender($widget['template'], array(
							'widgetid' => $widget['widgetid'],
							'widgetinstanceid' => $widget['widgetinstanceid'],
							'isWidget' => 1,
							'title' => $widget['title'],
						))
					);
				}
			}
		}

		return $result;
	}
}
