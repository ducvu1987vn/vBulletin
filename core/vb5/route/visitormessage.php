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

class vB5_Route_VisitorMessage extends vB5_Route
{
	const REGEXP = 'member/(?P<userid>[0-9]+)(?P<username>(-[^?/]*)*)/visitormessage/(?P<nodeid>[0-9]+)(?P<title>(-[^!@\\#\\$%\\^&\\*\\(\\)\\+\\?/:;"\'\\\\,\\.<>= _]*)*)';
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
			$this->arguments['nodeid'] = $matches['nodeid'];
			$this->arguments['contentid'] = $matches['nodeid'];
		}

		if (!empty($matches['title']))
		{
			$routeInfo['title'] = $matches['title'];
			$this->arguments['title'] = $matches['title'];
		}
		$node = vB_Library::instance('node')->getNodeBare($this->arguments['nodeid']);
		if (!empty($routeInfo['title']))
		{
			$this->arguments['title'] = vB_String::getUrlIdent($routeInfo['title']);
			// @TODO handle this in another way.
			$phrases = vB_Api::instanceInternal("phrase")->fetch(array('visitor_message_from_x'));
			$this->arguments['title'] = sprintf($phrases['visitor_message_from_x'], $node['authorname']);
		}

		// get userid and username
		if (empty($this->arguments['userid']))
		{
			$node = vB_Library::instance('node')->getNodeBare($this->arguments['nodeid']);

			//get userInfo
			if ($node['setfor'])
			{
				$user = vB_Api::instanceInternal('user')->fetchUsernames(array($node['setfor']));
				$user = $user[$node['setfor']];
				$this->arguments['userid'] = $node['setfor'];
				$this->arguments['username'] = $user['username'];
			}
		}

		$this->breadcrumbs = array(
			0 => array(
				'title' => $this->arguments['username'],
				'url' => vB5_Route::buildUrl('profile|nosession', array('userid' => $this->arguments['userid'], 'username' => vB_String::getUrlIdent($this->arguments['username'])))
			),
			1 => array(
				'phrase' => 'visitor_message',
				'url' => ''
			)
		);
	}

	protected static function validInput(array &$data)
	{
		if (!parent::validInput($data) OR !isset($data['nodeid']) OR !is_numeric($data['nodeid']))
		{
			return FALSE;
		}

		$data['pageid'] = intval($data['pageid']);
		$data['prefix'] = $data['prefix'];
		$data['regex'] = $data['prefix'] . '/' . self::REGEXP;
		$data['arguments'] = serialize(
			array(
				'nodeid'	=> '$nodeid',
				'pageid'	=> $data['pageid']
			)
		);

		$data['class'] = __CLASS__;
		$data['controller']	= 'page';
		$data['action']		= 'index';
		// this field will be used to delete the route when deleting the channel (contains channel id)

		unset($data['pageid']);

		return parent::validInput($data);
	}

	public function getUrl()
	{
		if (empty($this->arguments['title']))
		{
			$node = vB_Library::instance('node')->getNodeBare($this->arguments['nodeid']);
			if (empty($node) OR !empty($node['errors']))
			{
				return FALSE;
			}

			if ($node['urlident'])
			{
				$this->arguments['title'] = $node['urlident'];
			}
			else
			{
				$this->arguments['title'] = vB_String::getUrlIdent($node['title']);
			}

		}

		if (empty($this->arguments['userid']))
		{
			if (!isset($node['nodeid']))
			{
				$node = vB_Library::instance('node')->getNodeBare($this->arguments['nodeid']);
			}

			if ($node['setfor'])
			{
				$user = vB_User::fetchUserinfo($node['setfor']);
				$this->arguments['userid'] = $user['userid'];
				$this->arguments['username'] = $user['username'];
			}
		}

		$url = '/member/' . $this->arguments['userid'] . '-' . vB_String::getUrlIdent($this->arguments['username']) . '/visitormessage/' . $this->arguments['nodeid'] . '-' . vB_String::vBStrToLower(vB_String::htmlSpecialCharsUni(str_replace(' ', '-', $this->arguments['title'])));

		if (strtolower(vB_String::getCharset()) != 'utf-8')
		{
			$url = vB_String::encodeUtf8Url($url);
		}

		return $url;

	}

	public function  getCanonicalRoute()
	{
		if (!isset($this->canonicalRoute))
		{
			if (empty($this->arguments['title']))
			{
				$node = vB_Library::instance('node')->getNodeBare($this->arguments['nodeid']);

				if (empty($node) OR !empty($node['errors']))
				{
					return FALSE;
				}

				$this->arguments['title'] = $node['title'];
			}

			$routeInfo = array('routeid' => $this->routeId, 'prefix' => $this->prefix, 'regex' => $this->regex,
			 'nodeid' => $this->arguments['nodeid'], 'title' => $this->arguments['title'], 'controller' => $this->controller, 'pageid' => $this->arguments['contentid']);
			$this->canonicalRoute = new vB5_Route_VisitorMessage($routeInfo, array('nodeid' => $this->arguments['nodeid']));
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

