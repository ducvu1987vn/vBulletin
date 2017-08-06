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

class vB5_Route_PrivateMessage_Index
{
	protected $subtemplate = 'privatemessage_foldersummary';

	public function __construct(&$routeInfo, &$matches, &$queryString = '')
	{
		// just modify routeInfo, no internal settings
		$routeInfo['arguments']['subtemplate'] = $this->subtemplate;
		}

	public function validInput(&$data)
	{
		$data['arguments'] = '';

		return true;
	}

	public function getUrlParameters()
	{
		return '';
	}

	public function getParameters()
	{
		// TODO: remove the dummy variable, this was just a demo
		return array('dummyIndex' => "I'm a dummy value!");
	}

	public function getBreadcrumbs()
	{
		return array(
			array(
				'phrase' => 'inbox',
				'url'	=> ''
			)
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
