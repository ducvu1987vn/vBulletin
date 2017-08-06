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

class vB5_Route_Legacy_Subscription extends vB5_Route_Legacy_GenerationOnly
{
	protected $idkey = '';
	protected $titlekey = '';
	protected $script = 'subscription.php';
	protected $script_base_option_name = 'vbforum_url';

	protected $currentUser;

	public function __construct($routeInfo = array(), $matches = array(), $queryString = '')
	{
		if ($currentSession = vB::getCurrentSession())
		{
			$this->currentUser = vB::getCurrentSession()->get('userid');
		}
		else
		{
			$this->currentUser = 0;
		}
		parent::__construct($routeInfo, $matches, $queryString);
	}

	protected function getCacheKey($oldid)
	{
		return "_{$this->currentUser}";
	}

	protected function getNewRouteInfo($oldid)
	{
		return array('userid' => $this->currentUser);
	}

	protected function setNewRoute($routeInfo)
	{
		$this->arguments['userid'] = $routeInfo['userid'];
		$this->arguments['tab'] = 'subscriptions';

		return 'subscription';
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/