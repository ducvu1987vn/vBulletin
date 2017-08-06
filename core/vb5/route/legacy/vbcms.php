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

class vB5_Route_Legacy_vBCms extends vB5_Route_Legacy
{
	/**
	 * Array of pageinfo vars to ignore when building the uri.
	 *
	 * @var array string
	 */
	protected $ignorelist = array();

	/**
	 * The name of the script that the URL links to.
	 *
	 * @var string
	 */
	protected $script;

	/**
	 * The segment of the uri that identifies this type.
	 *
	 * @var string
	 */
	protected $rewrite_segment;


	/**
	 * Whether to use the friendly uri in POST requests.
	 *
	 * @var bool
	 */
	protected $parse_post = true;

	/**
	 * Whether to always set the route, even if friendly urls are off.
	 *
	 * @var bool
	 */
	protected $always_route = true;

	public function __construct($routeInfo = array(), $matches = array(), $queryString = '')
	{
		$assertor = vB::getDbAssertor();

		$this->oldcontenttypeid = array(-1);

		$packageId = $assertor->getField('package', array('class' => 'vBCms'));
		if ($packageId)
		{
			$contentTypes = $assertor->assertQuery('contenttype', array('packageid' => $packageId));

			foreach($contentTypes AS $contentType)
			{
				$this->oldcontenttypeid[] = intval($contentType['contenttypeid']);
			}
		}

		$this->idvar = $this->ignorelist[] = vB::getDatastore()->getOption('route_requestvar');
//		$this->script = basename(SCRIPT);
		$this->script = 'content.php';

		parent::__construct($routeInfo, $matches, $queryString);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/