<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright 2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * APC.
 * Handler that caches and retrieves data from APC.
 * @see vB_Cache
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 29424 $
 * @since $Date: 2009-02-02 14:07:13 +0000 (Mon, 02 Feb 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Cache_APC extends vB_Cache
{
	const LOCK_PREFIX = 'lock_';

	/** There is a strangeness in the event handling of this class. We cannot guarantee an APC record will be available
	 * when desired. If we have a cache record but not an event, that's not a problem.  But if we have a cache record but
	 * not an event, we have potentially bad data. So:
	 *
	 * We store both cache records and events in APC.
	 *
	 * an event record is just an integer. A unix time value represents the last time the event was called. 0 means it has never been called.
	 *
	 * On write, add the current time and the events to the cache record. We also make sure there is an event record for all associated events.
	 *
	 * On read, if we get a value we check all the events. If any have been called more recently than the cache record, we remove the
	 *	record and return false. If we don't have an event record, we can't know if the cached value is valid, so we remove it and
	 *  return false
	 *
	 * For the in-memory copy of the data we treat as normal. We retain the values_read, no_values, and add an events array. So when
	 * an event is called we clear the values_read array and add to no_values;
	 */

	/*Properties====================================================================*/
	/**
	 * A reference to the singleton instance
	 *
	 * @var vB_Cache_Memcached
	 */
	protected static $instance;
	protected $events = array();

	/*Construction==================================================================*/

	/**
	 * Constructor protected to enforce singleton use.
	 * @see instance()
	 */
	protected function __construct($cachetype)
	{
		parent::__construct($cachetype);

		//get the APC prefix.
		$config = vB::getConfig();

		if (empty($config['Cache']['apcprefix']))
		{
			$this->prefix = $config['Database']['tableprefix'];
		}
		else
		{
			$this->prefix = $config['Cache']['apcprefix'];
		}
	}

	/**
	 * Returns singleton instance of self.
	 * @todo This can be inherited once late static binding is available.  For now
	 * it has to be redefined in the child classes
	 *
	 * @return vB_Cache_Memcached						- Reference to singleton instance of cache handler
	 */
	public static function instance($type = NULL)
	{
		if (!isset(self::$instance))
		{
			$class = __CLASS__;
			self::$instance = new $class($type);
		}

		return self::$instance;
	}

	/**
	 * Returns the UNIX timestamp of the last ocurrence of $event, and FALSE if there isn't one
	 *
	 * @param string $event
	 * @return mixed
	 */
	protected function getEventTime($event)
	{
		return apc_fetch($this->prefix . $event);
	}

	/**
	 * Store event in memcache
	 *
	 * @param string $event - Event identifier
	 * @param int $time - If 0, the event won't overwrite any memcache entry
	 */
	protected function setEventTime($event, $time)
	{
		// if $time = 0, this is a dummy event which should not overwrite real events
        if ($time == 0)
        {
            apc_add($this->prefix . $event, $time);
        }
        else
        {
            apc_store($this->prefix . $event, $time);
        }
	}

	/*Initialisation================================================================*/

	/**
	 * Writes the cache data to storage.
	 *
	 * @param array	includes key, data, expires
	 */
	protected function writeCache($cache)
	{
		$ptitle = $this->prefix . $cache['key'];

		try
		{
			$this->lock($cache['key']);
            if (!apc_store($ptitle, $cache, $cache['expires']))
            {
                $this->unlock($cache['key']);
                return;
            }

			if (!empty($cache['events']))
			{
				foreach ($cache['events'] AS $event)
				{
					// store the cache information in memory so we can clear them.
					if (empty ($this->events[$event]))
					{
						$this->events[$event] = array();
					}
					$this->events[$event][$cache['key']] = $cache['key'];

					if (!$this->getEventTime($event))
					{
						// no events in APC, set event time to 0
						apc_store($this->prefix . $event, 0);
					}
				}
			}
		}
		catch(Exception $e)
		{
			$this->unlock($cache['key']);
		}
	}

	/**
	 * Reads the cache object from storage.
	 *
	 * @param string $key						- Id of the cache entry to read
	 * @return array	includes key, data, expires
	 */
	protected function readCache($key)
	{
		$ptitle = $this->prefix . $key;
		$entry = apc_fetch($ptitle);

		if ($entry === false)
		{
			return false;
		}

		//see if it's locked
		$lock = apc_fetch($this->prefix . self::LOCK_PREFIX . $key);

		// check if it is still valid
		if (!empty($entry['events']))
		{
			foreach ($entry['events'] AS $event)
			{

				if (($time = $this->getEventTime($event)) !== false)
				{
					if ($time >= $entry['created'])
					{
						// we need to expire this object and stop checking
						$this->expireCache($key);
						return false;
					}

					return $entry;
				}
				else
				{
					// event not logged in APC
					$this->expireCache($key);
					return false;
				}
			}
		}

        error_log(json_encode($entry));
		return $entry;
	}

	/**
	 * Removes a cache object from storage.
	 *
	 * @param int $key							- Key of the cache entry to purge
	 * @return bool								- Whether anything was purged
	 */
	protected function purgeCache($key)
	{
		$ptitle = $this->prefix . $key;
		apc_delete($ptitle);

		return true;
	}

	/**
	 * Sets a cache entry as expired in storage.
	 *
	 * @param string/array $key						- Key of the cache entry to expire
	 *
	 * @return	array of killed items
	 */
	protected function expireCache($keys)
	{
		if (!is_array($keys))
		{
			$keys = array($keys);
		}

		foreach ($keys AS $key)
		{
			apc_delete($key);

			if (!empty($this->events[$key]))
			{
				foreach ($this->events[$key] AS $cacheKey)
				{
					unset($this->values_read[$cacheKey]);
				}
			}
		}
	}

	/**
	 * Expires cache objects based on a triggered event.
	 *
	 * An event handling vB_CacheObserver must be attached to handle cache events.
	 * Generally the CacheObservers would respond by calling vB_Cache::expire() with
	 * the cache_id's of the objects to expire.
	 *
	 * @param string | array $event				- The name of the event
	 */
	public function event($events)
	{
		// set to an array of strings
		$events = (array)$events;
		foreach ($events AS $key => $event)
		{
			$strEvent = strval($event);
			$events[$key] = $strEvent;
			$this->setEventTime($strEvent, $this->timeNow);

			if (!empty($this->events[$strEvent]))
			{
				foreach ($this->events[$strEvent] AS $cacheKey)
				{
					unset($this->values_read[$cacheKey]);
				}
			}
		}
		return $this;
	}

	/**
	 * Locks a cache entry.
	 *
	 * @param string $key						- Key of the cache entry to lock
	 */
	public function lock($key)
	{
		// For some weird reason, storing a simple timestamp does not work, so use prefix.
		$lock_expiration = max(array(ini_get('max_execution_time'),30));
		return (apc_add($this->prefix . self::LOCK_PREFIX . $key, self::LOCK_PREFIX . $this->timeNow, $lock_expiration));
	}

	public function unlock($key)
	{
		return (apc_delete($this->prefix . self::LOCK_PREFIX . $key));
	}

	/*Clean=========================================================================*/

	/**
	 * Cleans cache.
	 *
	 * @param bool $only_expired				- Only clean expired entries
	 */
	public function clean($only_expired = true)
	{
		if (!$only_expired)
		{
            $regex = '#^' . $this->prefix . '#';
            apc_delete(new APCIterator('user', $regex, APC_ITER_VALUE));
			$this->cleanNow();
			$this->events = array();
		}
	}



	/*Shutdown======================================================================*\

	/**
	 * Perform any finalisation on shutdown.
	 */
	public function shutdown()
	{
		parent::shutdown();
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision: 29401 $
|| ####################################################################
\*======================================================================*/
