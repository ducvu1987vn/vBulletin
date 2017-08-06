<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

class vB5_Route_Subscription extends vB5_Route
{
	const REGEXP = '(?P<userid>[0-9]+)(?P<username>(-[^\?]*)*)/(?P<tab>subscriptions|subscribers|groups)';

	public function __construct($routeInfo, $matches, $queryString = '', $anchor = '')
	{
		parent::__construct($routeInfo, $matches, $queryString, $anchor);
		$this->arguments['userid'] = intval($this->arguments['userid']);
		$userInfo = vB_Api::instanceInternal('user')->fetchProfileInfo($this->arguments['userid']);
		if (empty($this->arguments['userid']))
		{
			$this->arguments['userid'] = $userInfo['userid'];
			$this->arguments['username'] = $userInfo['username'];
		}
		else if (empty($this->arguments['username']))
		{
			$this->arguments['username'] = $userInfo['username'];
		}

		if ($this->arguments['tab'] == 'subscriptions' AND !$userInfo['showSubscriptions'])
		{
			throw new vB_Exception_NodePermission('subscriptions');
		}
		else if($this->arguments['tab'] == 'subscribers' AND !$userInfo['showSubscribers'])
		{
			throw new vB_Exception_NodePermission('subscribers');
		}

		$this->breadcrumbs = array(
			0 => array(
				'title' => $this->arguments['username'],
				'url' => vB5_Route::buildUrl('profile|nosession', array('userid' => $this->arguments['userid'], 'username' => vB_String::getUrlIdent($this->arguments['username'])))
			),
			1 => array(
				'phrase' => $this->arguments['tab'],
				'url' => ''
			)
		);

	}

	public function getUrl()
	{
		if (empty($this->arguments['username']))
		{
			$userInfo = vB_Api::instanceInternal('user')->fetchProfileInfo($this->arguments['userid']);
			$this->arguments['username'] = $userInfo['username'];
		}

		// the regex contains the url
		$url = '/' . $this->prefix . '/' . $this->arguments['userid'] . '-' . vB_String::getUrlIdent($this->arguments['username']) . '/' . $this->arguments['tab'];

		if (strtolower(vB_String::getCharset()) != 'utf-8')
		{
			$url = vB_String::encodeUtf8Url($url);
		}

		return $url;
	}

	public function getCanonicalRoute()
	{
		if (!isset($this->canonicalRoute))
		{
			$page = vB::getDbAssertor()->getRow('page', array('pageid'=>$this->arguments['pageid']));
			$this->canonicalRoute = self::getRoute($page['routeid'], $this->arguments, $this->queryParameters);
		}

		return $this->canonicalRoute;
	}

	protected static function validInput(array &$data)
	{
		if (
				!isset($data['pageid']) OR !is_numeric($data['pageid']) OR
				!isset($data['prefix'])
			)
		{
			return FALSE;
		}

		$data['regex'] = $data['prefix'] . '/' . self::REGEXP;
		$data['class'] = __CLASS__;
		$data['controller']	= 'page';
		$data['action']		= 'index';
		$data['arguments']	= serialize(array('pageid' => $data['contentid'], 'tab' =>'$tab'));

		return parent::validInput($data);
	}

	protected static function updateContentRoute($oldRouteInfo, $newRouteInfo)
	{
		$db = vB::getDbAssertor();

		$db->assertQuery('update_route_301', array('newrouteid' => $newRouteInfo['routeid'], 'oldrouteid' => $oldRouteInfo['routeid']));

		// don't modify the routeid for default pages, as it will still be used
		$db->update('page', array('routeid' => $newRouteInfo['routeid']), array('routeid' => $oldRouteInfo['routeid'], 'pagetype' => vB_Page::TYPE_CUSTOM));
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

		return serialize($data);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/