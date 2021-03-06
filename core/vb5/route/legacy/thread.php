<?php

/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 5.0.0
  || # ---------------------------------------------------------------- # ||
  || # Copyright �2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

class vB5_Route_Legacy_Thread extends vB5_Route_Legacy
{
	/**
	 * The request variable for the resource id.
	 *
	 * @var string
	 */
	protected $idvar = 't';

	/**
	 * Link info index of the resource id.
	 *
	 * @var string
	 */
	protected $idkey = 'threadid';

	/**
	 * Array of pageinfo vars to ignore when building the uri.
	 *
	 * @var array string
	 */
	protected $ignorelist = array('t', 'threadid');

	/**
	 * The name of the script that the URL links to.
	 *
	 * @var string
	 */
	protected $script = 'showthread.php';
	protected $script_base_option_name = 'vbforum_url';

	/**
	 * The segment of the uri that identifies this type.
	 *
	 * @var string
	 */
	protected $rewrite_segment = 'threads';

	public function __construct($routeInfo = array(), $matches = array(), $queryString = '')
	{
		$this->oldcontenttypeid = vB_Types::instance()->getContentTypeID(array('package' => 'vBForum', 'class' =>'Thread'));
		parent::__construct($routeInfo, $matches, $queryString);
		
		if (!isset($this->queryParameters[$this->idvar]) AND !isset($this->queryParameters[$this->idkey]))
		{
			// try to fetch it from postid
			if(($postId = intval($this->queryParameters['p'])) OR $postId = intval($this->queryParameters['postid']))
			{
				$this->queryParameters[$this->idvar] = "p$postId";
			}
		}
	}
	
	protected function getNewRouteInfo($oldid)
	{
		if (preg_match('#^p([0-9]*)#', $oldid, $matches))
		{
			// it's a postid
			$node = vB::getDbAssertor()->getRow('vBForum:fetchLegacyPostIds', array(
				'oldids' => intval($matches[1]),
				'postContentTypeId' => vB_Types::instance()->getContentTypeID('vBForum_Post'),
			));
		}
		else
		{
			// it's a thread id
			$node = parent::getNewRouteInfo($oldid);
		}

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