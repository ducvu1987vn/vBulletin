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

class vB5_Route_PrivateMessage_Notification extends vB5_Route_PrivateMessage_List
{
	protected $subtemplate = 'privatemessage_listnotifications';

	public function __construct(&$routeInfo, &$matches, &$queryString = '')
	{
		$this->overrideDisable = true;
		parent::__construct($routeInfo, $matches, $queryString);
	}

	public function getBreadcrumbs()
	{
		$breadcrumbs = array(
			array(
				'phrase' => 'inbox',
				'url'	=> vB5_Route::buildUrl('privatemessage|nosession')
			),
			array(
				'phrase' => 'notifications',
				'url' => ''
			)
		);

		return $breadcrumbs;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
