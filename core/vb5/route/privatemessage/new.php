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

class vB5_Route_PrivateMessage_New
{
	protected $subtemplate = 'privatemessage_newpm';

	protected $userid = 0;

	public function __construct(&$routeInfo, &$matches, &$queryString = '')
	{
		if (isset($matches['params']) AND !empty($matches['params']))
		{
			$paramString = (strpos($matches['params'], '/') === 0) ? substr($matches['params'], 1) : $matches['params'];
			list($this->userid) = explode('/', $paramString);
		}
		else if (isset($matches['userid']))
		{
			$this->userid = $matches['userid'];
		}
		$routeInfo['arguments']['subtemplate'] = $this->subtemplate;

		$userid = vB::getCurrentSession()->get('userid');
		$pmquota = vB::getUserContext($userid)->getLimit('pmquota');
		$vboptions = vB::getDatastore($userid)->getValue('options');
		$canUsePmSystem = ($vboptions['enablepms'] AND $pmquota);
		if (!$canUsePmSystem)
		{
			throw new vB_Exception_NodePermission('privatemessage');
		}
	}

	public function validInput(&$data)
	{
		//We don't actually need anything. userid is optional
		if ($this->userid)
		{
			$data['arguments'] = serialize(array(
				'userid' => $this->userid
			));

			return true;
		}
		else
		{
			return true;
		}
	}

	public function getUrlParameters()
	{
		return "/{$this->userid}";
	}

	public function getParameters()
	{
		return array('userid' => $this->userid);
	}

	public function getBreadcrumbs()
	{
		$breadcrumbs = array(
			array(
				'phrase' => 'inbox',
				'url'	=> vB5_Route::buildUrl('privatemessage|nosession')
			),
			array(
				'phrase' => 'messages',
				'url' => ''
			),
			array(
				'phrase' => 'new_message',
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
