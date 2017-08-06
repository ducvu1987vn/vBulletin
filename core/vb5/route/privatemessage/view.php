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

class vB5_Route_PrivateMessage_View
{
	protected $subtemplate = 'privatemessage_view';
	protected $messageid = 0;

	public function __construct(&$routeInfo, &$matches, &$queryString = '')
	{
		if (isset($matches['params']) AND !empty($matches['params']))
		{
			$paramString = (strpos($matches['params'], '/') === 0) ? substr($matches['params'], 1) : $matches['params'];
			list($this->messageid) = explode('/', $paramString);
		}
		else if (isset($matches['messageid']))
		{
			$this->messageid = $matches['messageid'];
		}
		$routeInfo['arguments']['subtemplate'] = $this->subtemplate;
	}

	public function validInput(&$data)
	{
		if ($this->messageid)
		{
			$data['arguments'] = serialize(array(
				'messageid' => $this->messageid
			));

			return true;
		}
		else
		{
			return false;
		}
	}

	public function getUrlParameters()
	{
		return "/{$this->messageid}";
	}

	public function getParameters()
	{
		return array('messageid' => $this->messageid);
	}

	public function getBreadcrumbs()
	{
		$breadcrumbs = array(
			array(
				'phrase' => 'inbox',
				'url'	=> ''
			),
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
