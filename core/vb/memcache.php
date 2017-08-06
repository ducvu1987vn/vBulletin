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
 * This implements an object wrapper for Memcache
 */
class vB_Memcache
{
	/**
	 * A reference to the singleton instance
	 *
	 * @var vB_Memcached
	 */
	protected static $instance;

	/**
	 * Contains the config variables loaded from the config file
	 * @var array
	 */
	protected $config = null;

	/**
	* The Memcache object (can be either Memcache or Memcached)
	*/
	protected $memcached = null;

	protected $defaultExpiration;

	/**
	* To verify a connection is still active
	*
	* @var	boolean
	*/
	protected $memcached_connected = false;

	protected function __construct()
	{
		$this->memcached = new Memcache;
	}

	public static function instance()
	{
		if (!isset(self::$instance))
		{
			if (class_exists('Memcached', FALSE))
			{
				$class = 'vB_Memcached';
			}
			else if (class_exists('Memcache', FALSE))
			{
				$class = __CLASS__;
			}
			else
			{
				throw new Exception('Memcached is not installed');
			}
			self::$instance = new $class();
			self::$instance->config = vB::getConfig();
		}

		return self::$instance;
	}

	public function setConfig(&$config)
	{
		$this->config = & $config;
	}

	protected function addServers()
	{
		if (is_array($this->config['Misc']['memcacheserver']))
		{
			if (method_exists($this->memcached, 'addServer'))
			{
				foreach (array_keys($this->config['Misc']['memcacheserver']) AS $key)
				{
					$this->memcached->addServer(
							$this->config['Misc']['memcacheserver'][$key],
							$this->config['Misc']['memcacheport'][$key],
							$this->config['Misc']['memcachepersistent'][$key],
							$this->config['Misc']['memcacheweight'][$key],
							$this->config['Misc']['memcachetimeout'][$key],
							$this->config['Misc']['memcacheretry_interval'][$key]
					);
				}
			}
			else if (!$this->memcached->connect($this->config['Misc']['memcacheserver'][1], $this->config['Misc']['memcacheport'][1], $this->config['Misc']['memcachetimeout'][1]))
			{
				trigger_error('Unable to connect to memcache server', E_USER_ERROR);
			}
		}
		else if (!$this->memcached->connect($this->config['Misc']['memcacheserver'], $this->config['Misc']['memcacheport']))
		{
			trigger_error('Unable to connect to memcache server', E_USER_ERROR);
		}
	}

	/**
	* Connect Wrapper for Memcache
	*
	* @return	integer	When a new connection is made 1 is returned, 2 if a connection already existed
	*/
	public function connect()
	{
		if (!$this->memcached_connected)
		{
			$this->addServers();
			$this->memcached_connected = true;
			return 1;
		}
		return 2;
	}

	/**
	 * Add an item under a new key
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param $data = self::$memcached->get('key1');
	 * @return bool
	 */
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

		return $this->memcached->add($key, $value, MEMCACHE_COMPRESSED, $expiration);
	}

	/**
	 * Store an item
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param c
	 * @return bool
	 */
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

		return $this->memcached->set($key, $value, MEMCACHE_COMPRESSED, $expiration);
	}

	/**
	 * Retrieve an item
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function get($key)
	{
		if (!$this->memcached_connected)
		{
			return FALSE;
		}

		return $this->memcached->get($key);
	}

	/**
	 * Delete an item
	 *
	 * @param string $key
	 * @return bool
	 */
	public function delete($key)
	{
		if (!$this->memcached_connected)
		{
			return FALSE;
		}

		if (empty($key))
		{
			return true;
		}

		// Despite being deprecated, the second paramater is still required by some implementations of memcache
		return $this->memcached->delete($key,0);
	}

	/**
	 * Invalidate all items in the cache
	 *
	 * @return bool
	 */
	public function flush()
	{
		if (!$this->memcached_connected)
		{
			return FALSE;
		}

		return $this->memcached->flush();
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
