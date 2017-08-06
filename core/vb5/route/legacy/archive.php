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

class vB5_Route_Legacy_Archive extends vB5_Route_Legacy
{
//	protected $idvar = 't';
//	protected $idkey = 'threadid';
//	protected $titlekey = '';
//	protected $script = 'printthread.php';
//	protected $script_base_option_name = 'vbforum_url';
	
	public function __construct($routeInfo = array(), $matches = array(), $queryString = '')
	{
		parent::__construct($routeInfo, $matches, $queryString);
		
		if (!empty($matches['threadid']))
		{
			$this->idvar = 't';
			$this->queryParameters[$this->idvar] = $matches['threadid'];
			$this->oldcontenttypeid = vB_Types::instance()->getContentTypeID(array('package' => 'vBForum', 'class' =>'Thread'));
		}
		else if (!empty($matches['forumid']))
		{
			$this->idvar = 'f';
			$this->queryParameters[$this->idvar] = $matches['forumid'];
			$this->oldcontenttypeid = vB_Types::instance()->getContentTypeID(array('package' => 'vBForum', 'class' =>'Forum'));
		}
		else
		{
			// it's the home page
			$this->idvar = 'forumhome';
		}
	}
	
	public function getPrefix()
	{
		return 'archive/index.php';
	}
	
	public function getRegex()
	{
		return 'archive/index.php' .
			'(/' . 
				'(' .
					'(t-(?P<threadid>[0-9]+))|(f-(?P<forumid>[0-9]+))' .
				')' .
				'(-p-(?P<pageid>[0-9]+))?' .
				'\.html' .
			')?';
	}
	
	public function getRedirect301()
	{
		if ($this->idvar == 'forumhome')
		{
			// just go to forum home page
			$forumHomeChannel = vB_Library::instance('content_channel')->getForumHomeChannel();
			
			return $forumHomeChannel['routeid'];
		}
		else
		{
			return parent::getRedirect301();
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/