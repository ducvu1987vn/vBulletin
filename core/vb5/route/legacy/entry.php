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

class vB5_Route_Legacy_Entry extends vB5_Route_Legacy
{
	/**
	 * The request variable for the resource id.
	 *
	 * @var string
	 */
	protected $idvar = 'b';

	/**
	 * Link info index of the resource id.
	 *
	 * @var string
	 */
	protected $idkey = 'blogid';

	/**
	 * Array of pageinfo vars to ignore when building the uri.
	 *
	 * @var array string
	 */
	protected $ignorelist = array('b', 'blogid');

	/**
	 * The name of the script that the URL links to.
	 *
	 * @var string
	 */
	protected $script = 'entry.php';
	protected $script_base_option_name = 'vbblog_url';

	/**
	 * The segment of the uri that identifies this type.
	 *
	 * @var string
	 */
	protected $rewrite_segment = 'entries';

	public function __construct($routeInfo = array(), $matches = array(), $queryString = '')
	{
		$this->oldcontenttypeid = 9985;
		parent::__construct($routeInfo, $matches, $queryString);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/