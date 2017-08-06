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

/**
 * This class is used for creating redirects of legacy URLs
 * Based on vB_Friendly_Url
 */
abstract class vB5_Route_Legacy extends vB5_Route
{
	/**
	 * Friendly URL types
	 */
	const FRIENDLY_URL_OFF		= 0;
	const FRIENDLY_URL_BASIC	= 1;
	const FRIENDLY_URL_ADVANCED	= 2;
	const FRIENDLY_URL_REWRITE	= 3;

	/**
	 * Regex to clean fragments.
	 *
	 * @var string
	 */
	const CLEAN_URL_REGEX = '*([\s$+,/:=\?@"\'<>%{}|\\^~[\]`\r\n\t\x00-\x1f\x7f]|(?(?<!&)#|#(?![0-9]+;))|&(?!#[0-9]+;)|(?<!&#\d|&#\d{2}|&#\d{3}|&#\d{4}|&#\d{5});)*s';

	/**
	 * Unicode URL options
	 *
	 * @var int
	 */
	const UNI_IGNORE = 0;
	const UNI_CONVERT = 1;
	const UNI_STRIP = 2;

	/**
	 * The rewrite segment to identify this friendly url type.
	 *
	 * @var string
	 */
	protected $rewrite_segment;

	/**
	 * The name of the script that the URL links to.
	 *
	 * @var string
	 */
	protected $script;

	/**
	 * olcontenttypeid used in node table while importing content
	 * @var mixed - int or array
	 */
	protected $oldcontenttypeid;

	/**
	 * The request variable for the resource id.
	 *
	 * @var string
	 */
	protected $idvar;

	// This is for legacy routes, so we don't need to pass an anchor id
	public function __construct($routeInfo = array(), $matches = array(), $queryString = '')
	{
		if (!empty($routeInfo))
		{
			parent::__construct($routeInfo, $matches, $queryString);
		}
		else
		{
			// We are not parsing the route
			$this->arguments = array(
				'friendlyurl' => $this->getFriendlyUrlMethod(),
				'script_base_option_name' => $this->getScriptBaseOptionName()
			);
		}
	}
	
	/**
	 * Allows the upgrader to set arguments
	 * @param array $arguments
	 */
	public function setArguments(array $arguments)
	{
		if (!empty($arguments))
		{
			foreach ($arguments as $name => $value)
			{
				$this->arguments[$name] = $value;
			}
		}
	}

	protected function getFriendlyUrlMethod()
	{
		if (isset($this->arguments['friendlyurl']))
		{
			return $this->arguments['friendlyurl'];
		}
		else
		{
			// try fetching from datastore while upgrading (this is before the setting is removed in class_upgrade_500a1)
			return vB::getDatastore()->getOption('friendlyurl');
		}
	}

	protected function getScriptBaseOptionName()
	{
		if (isset($this->arguments['script_base_option_name']))
		{
			return $this->arguments['script_base_option_name'];
		}
		else
		{
			// try fetching from datastore while upgrading (this is before the setting is removed in class_upgrade_500a1)
			return (empty($this->script_base_option_name) ? '' : vB::getDatastore()->getOption($this->script_base_option_name));
		}
	}

	public function getRedirect301()
	{
		if (!isset($this->arguments['friendlyurl']))
		{
			return false;
		}
		// Get the appropriate url
		$oldid = 0;
		switch ($this->arguments['friendlyurl'])
		{
			case self::FRIENDLY_URL_BASIC:
				$keys = array_keys($this->queryParameters);
				if (!empty($keys) AND preg_match('#^(?P<oldid>[0-9]+)(?P<title>(-[^!@\\#\\$%\\^&\\*\\(\\)\\+\\?/:;"\'\\\\,\\.<>= _]*)*)#', $keys[0], $matches))
				{
					$oldid = $matches['oldid'];
					unset($this->queryParameters[$keys[0]]);
				}
				break;

			case self::FRIENDLY_URL_ADVANCED:
				if (isset($this->arguments['oldid']) AND intval($this->arguments['oldid']))
				{
					$oldid = $this->arguments['oldid'];
				}
				break;
		}

		// if the above methods failed, try FRIENDLY_URL_OFF
		if ($oldid == 0)
		{
			if (isset($this->queryParameters[$this->idvar]))
			{
				$oldid = $this->queryParameters[$this->idvar];
				unset($this->queryParameters[$this->idvar]);
			}
			else if (isset($this->queryParameters[$this->idkey]))
			{
				$oldid = $this->queryParameters[$this->idkey];
				unset($this->queryParameters[$this->idkey]);
			}
			else
			{
				// there's nothing else we can do
				return false;
			}
			if (isset($this->pagevar) AND isset($this->queryParameters[$this->pagevar]))
			{
				$this->arguments['pagevar'] = $this->queryParameters[$this->pagevar];
				unset($this->queryParameters[$this->pagevar]);
			}
		}

		//See if we have a cached version
		$cache = vB_Cache::instance(vB_Cache::CACHE_STD);
		$cacheKey = get_class($this) . $this->getCacheKey($oldid);
		$newRouteInfo = $cache->read($cacheKey);

		if ($newRouteInfo === false)
		{
			$newRouteInfo = $this->getNewRouteInfo($oldid);
			$cache->write($cacheKey, $newRouteInfo, 86400);
		}

		return $this->setNewRoute($newRouteInfo);
	}

	protected function getCacheKey($oldid)
	{
		return '_' . $this->idvar . '_' . $oldid;
	}

	protected function getNewRouteInfo($oldid)
	{
		// this method has different implementation in some subclasses
		$node = vB::getDbAssertor()->getRow('vBForum:node', array(
			'oldid' => $oldid,
			'oldcontenttypeid' => $this->oldcontenttypeid
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

	/**
	 * Sets arguments for the new route
	 */
	protected function setNewRoute($routeInfo)
	{
		// this method has different implementation in some subclasses
		if (!empty($routeInfo))
		{
			$this->arguments['nodeid'] = $routeInfo['nodeid'];

			if (isset($this->arguments['pagevar']))
			{
				$this->arguments['pagenum'] = intval($this->arguments['pagevar']);
			}

			return $routeInfo['routeid'];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Returns only the uri.
	 * Setting $canonical gets the uri without encoding it for output.
	 * @see vB_Friendly_Url::redirect_canonical_url()
	 *
	 * @param bool $canonical							- If true, don't encode for output
	 * @return string
	 */
	protected function getUriRegex()
	{
		if (FRIENDLY_URL_OFF == vB::getDatastore()->getOption('friendlyurl'))
		{
			return false;
		}

//		return self::cleanFragment($this->id . '-' . $this->title, $canonical);
		$this->arguments['oldid'] = '$oldid';
		return '(?P<oldid>[0-9]+)(?P<title>(-[^?&]*)*)';
	}

	public function getPrefix()
	{
		$method = $this->getFriendlyUrlMethod();

		if ($method == self::FRIENDLY_URL_REWRITE)
		{
			// redirect must be handled in rewrite rule
			return false;
		}
		else
		{
			$prefix = $this->script;
		}

		//this is a nasty workaround, but we have to do it.  Instead of dealing with the base option
		//and forcing the full url after we construct the url, do it before.  The reason is that
		//create_full_url can deal poorly with UTF characters encoded as &#xxxx; in the url (because it contains
		//'#'.  This may happen in friendly urls when the title contains UTF characters in this format.
		if (!empty($this->arguments['script_base_option_name']))
		{
			$prefix = $this->arguments['script_base_option_name'] . '/' . $prefix;
		}

		return $prefix;
	}

	public function getRegex()
	{
		$regex = $this->getPrefix();

		$method = $this->getFriendlyUrlMethod();
		switch($method)
		{
			case self::FRIENDLY_URL_REWRITE:
				return false;

			case self::FRIENDLY_URL_ADVANCED:
				if ($uri = $this->getUriRegex())
				{
					// the regex still needs to match standard URLs
					$regex .= "(?:/$uri?)?";
				}
				break;
		}

		return $regex;
	}

	protected static function validInput(array &$data)
	{
		// we cannot create nor update these routes
		throw new Exception('Invalid route data');
	}

	/**
	 *
	 * @throws Exception
	 */
	protected static function updateContentRoute($oldRouteInfo, $newRouteInfo)
	{
		// we cannot update content for these routes
		throw new Exception('Invalid route data');
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
