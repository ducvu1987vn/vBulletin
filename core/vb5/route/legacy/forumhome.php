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

class vB5_Route_Legacy_Forumhome extends vB5_Route_Legacy_GenerationOnly
{
	protected $idkey = '';
	protected $titlekey = '';
	protected $ignorelist = array();
	protected $script = 'forum.php';
	protected $script_base_option_name = 'vbforum_url';
	protected $rewrite_segment = '';

	public function __construct($routeInfo = array(), $matches = array(), $queryString = '')
	{
		$this->script = vB::getDatastore()->getOption('forumhome') . ".php";
		parent::__construct($routeInfo, $matches, $queryString);
	}

	public function getRedirect301()
	{
		$forumHomeChannel = vB_Library::instance('content_channel')->getForumHomeChannel();

		return $forumHomeChannel['routeid'];
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/