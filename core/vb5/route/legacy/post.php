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

class vB5_Route_Legacy_Post extends vB5_Route_Legacy
{
	/**
	 * The request variable for the resource id.
	 *
	 * @var string
	 */
	protected $idvar = 'p';

	/**
	 * Link info index of the resource id.
	 *
	 * @var string
	 */
	protected $idkey = 'postid';

	/**
	 * Link info index of the title.
	 *
	 * @var string
	 */
	protected $titlekey = 'title';

	/**
	 * Array of pageinfo vars to ignore when building the uri.
	 *
	 * @var array string
	 */
	protected $ignorelist = array('p', 'postid', 'title');

	/**
	 * The name of the script that the URL links to.
	 *
	 * @var string
	 */
	protected $script = 'showpost.php';
	protected $script_base_option_name = 'vbforum_url';

	/**
	 * The segment of the uri that identifies this type.
	 *
	 * @var string
	 */
	protected $rewrite_segment = 'post';

	public function __construct($routeInfo = array(), $matches = array(), $queryString = '')
	{
		$this->oldcontenttypeid = vB_Types::instance()->getContentTypeID(array('package' => 'vBForum', 'class' =>'Post'));
		parent::__construct($routeInfo, $matches, $queryString);
	}
	
	protected function getNewRouteInfo($oldid)
	{
		$node = vB::getDbAssertor()->getRow('vBForum:fetchLegacyPostIds', array(
			'oldids' => $oldid,
			'postContentTypeId' => vB_Types::instance()->getContentTypeID('vBForum_Post'),
		));

		if (empty($node))
		{
			return false;
		}
		else
		{
			return $node;
		}
	}
	
	protected function setNewRoute($routeInfo)
	{
		if ($routeid = parent::setNewRoute($routeInfo))
		{
			if ($routeInfo['nodeid'] != $routeInfo['starter'])
			{
				$this->arguments['innerPost'] = $routeInfo['nodeid'];
			}

			$this->arguments['nodeid'] = $routeInfo['starter'];
		}
		
		return $routeid;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/