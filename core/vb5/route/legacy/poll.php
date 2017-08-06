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

class vB5_Route_Legacy_Poll extends vB5_Route_Legacy_GenerationOnly
{
	protected $idvar = 'pollid';
	protected $idkey = 'pollid';
	protected $titlekey = '';
	protected $script = 'poll.php';
	protected $script_base_option_name = 'vbforum_url';

	public function __construct($routeInfo = array(), $matches = array(), $queryString = '')
	{
		$this->oldcontenttypeid = vB_Types::instance()->getContentTypeID(array('package' => 'vBForum', 'class' =>'Poll'));
		parent::__construct($routeInfo, $matches, $queryString);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/