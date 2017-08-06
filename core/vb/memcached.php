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
 * This implements an object wrapper for Memcached
 */
class vB_Memcached extends vB_Memcache
{
	protected function __construct()
	{
		$this->memcached = new Memcached;
		$this->memcached->setOption(Memcached::OPT_COMPRESSION, TRUE);
	}

	protected function addServers()
	{
		if (is_array($this->config['Misc']['memcacheserver']))
		{
			foreach (array_keys($this->config['Misc']['memcacheserver']) AS $key)
			{
				$this->memcached->addServer(
						$this->config['Misc']['memcacheserver'][$key],
						$this->config['Misc']['memcacheport'][$key],
						$this->config['Misc']['memcacheweight'][$key]
				);
			}
		}
		else if (!$this->memcached->addServer($this->config['Misc']['memcacheserver'], $this->config['Misc']['memcacheport']))
		{
			trigger_error('Unable to connect to memcache server', E_USER_ERROR);
		}
	}

	public function add($key, $value, $expiration = NULL)
	{
		if (!$this->memcached_connected)
		{
			return FALSE;
		}

		if ($expiration === NULL)
		{
			$expiration = $this->defaultExpiration;
		}

		return $this->memcached->add($key, $value, $expiration);
	}

	public function set($key, $value, $expiration = NULL)
	{
		if (!$this->memcached_connected)
		{
			return FALSE;
		}

		if ($expiration === NULL)
		{
			$expiration = $this->defaultExpiration;
		}

		return $this->memcached->set($key, $value, $expiration);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
