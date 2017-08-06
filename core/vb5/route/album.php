<?php if (!defined('VB_ENTRY')) die('Access denied.');

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

class vB5_Route_Album extends vB5_Route
{
	protected $nodeid;

	protected $title;

	protected $controller = 'page';

	public function __construct($routeInfo, $matches, $queryString = '', $anchor = '')
	{
		parent::__construct($routeInfo, $matches, $queryString, $anchor);

		if (empty($matches['nodeid']))
		{
			throw new vB_Exception_Router('invalid_request');
		}
		else
		{
			$routeInfo['nodeid'] =  $matches['nodeid'];
			$this->nodeid = $matches['nodeid'];
			$this->arguments['nodeid'] = $matches['nodeid'];
			$this->arguments['contentid'] = $matches['nodeid'];
		}

		if (!empty($matches['title']))
		{
			//It should start with a dash, which we can ignore
			$routeInfo['title'] = substr($matches['title'],1);
			$this->arguments['title'] = substr($matches['title'],1);
		}

		if (!empty($routeInfo['title']))
		{
			$this->title = $routeInfo['title'];
		}

		$this->setPageKey('nodeid');
		$this->setUserAction('viewing_album');
	}

	protected static function validInput(array &$data)
	{
		if (!parent::validInput($data) OR !isset($data['nodeid']) OR !is_numeric($data['nodeid']))
		{
			return FALSE;
		}

		$node = vB_Library::instance('node')->getNodeBare($this->arguments['nodeid']);
		if (empty($node) OR !empty($node['errors']))
		{
			return FALSE;
		}

		$this->title = $node['title'];

	}

	public function getUrl()
	{
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$hashKey = 'vbRouteURLIndent_'. $this->arguments['nodeid'];
		$urlident = $cache->read($hashKey);
		if (empty($urlident))
		{
			$node = vB_Library::instance('node')->getNodeBare($this->arguments['nodeid']);
			$urlident = $node['urlident'];
			$cache->write($hashKey, $urlident);
		}
		
		$url = '/album/' . $this->arguments['nodeid'] . '-' . $urlident;
		
		if (strtolower(vB_String::getCharset()) != 'utf-8')
		{
			$url = vB_String::encodeUtf8Url($url);
		}
		
		return $url;
	}

	public function getCanonicalRoute($node = false)
	{
		if (!isset($this->canonicalRoute))
		{
			if (empty($this->title))
			{
				if (empty($node))
				{
					$node = vB_Library::instance('node')->getNodeBare($this->arguments['nodeid']);
				}

				if (empty($node) OR !empty($node['errors']))
				{
					return FALSE;
				}

				$this->title = self::prepareTitle($node['title']);
			}

			$routeInfo = array('routeid' => $this->routeId, 'prefix' => $this->prefix, 'regex' => $this->regex,
			 'nodeid' => $this->nodeid, 'title' => $this->title, 'controller' => $this->controller, 'pageid' => $this->arguments['contentid']);
			$this->canonicalRoute = new vB5_Route_Album($routeInfo, array('nodeid' => $this->nodeid));
		}

		return $this->canonicalRoute;
	}

	/**
	 * Returns arguments to be exported
	 * @param string $arguments
	 * @return array
	 */
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

		return serialize($data);	}

	/**
	 * Returns an array with imported values for the route
	 * @param string $arguments
	 * @return string
	 */
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

		return serialize($data);
	}
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
