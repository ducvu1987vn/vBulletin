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
 * DB Cache.
 * Handler that caches and retrieves data from the database.
 * @see vB_Cache
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 29424 $
 * @since $Date: 2009-02-02 14:07:13 +0000 (Mon, 02 Feb 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Cache_Db extends vB_Cache
{
	/*Properties====================================================================*/

	/**
	 *
	 * @var vB_dB_Assertor
	 */
	protected $assertor;

	/**
	 *
	 * @var requestStart
	 */
	protected $requestStart;

	/*
	 * Cache
	 */
	protected $recordsToSave = array();
	protected $deleteCache = array();
	protected $newEvents = array();
	protected $pageEvents = array();
	protected $locked = array();
	protected $lockDuration = 5; //If a lock hasn't been released in four seconds, there's a mistake and we should ignore it.

	/*Construction==================================================================*/

	/**
	 * Constructor public to allow for separate automated unit testing. Actual code should use
	 * vB_Cache::instance();
	 * @see vB_Cache::instance()
	 */
	public function __construct($cachetype)
	{
		parent::__construct($cachetype);
		$this->assertor = vB::getDbAssertor();
		$this->requestStart = $this->timeNow;
	}



	/*Initialisation================================================================*/

	/**
	 * Writes the cache data to storage.
	 *
	 * @param array	includes key, data, expires
	 */
	protected function writeCache($cache)
	{
		$data = $cache['data'];

		if (is_array($data) OR is_object($data))
		{
			$serialized = '1';
			$data = serialize($data);
		}
		else
		{
			$serialized = '0';
		}

		$this->recordsToSave[$cache['key']] = array('cacheid' => $cache['key'],
			'expires' => $cache['expires'],
			'created' => $this->requestStart,
			'locktime' => 0,
			'data' => $data,
			'serialized' => $serialized,
			'events' => $cache['events']);

		if (!empty($cache['events']))
		{
			foreach($cache['events'] AS $event)
			{
				if (isset($this->pageEvents[$event]))
				{
					$this->pageEvents[$event][$cache['key']] = $cache['key'];
				}
				else
				{
					$this->pageEvents[$event] = array($cache['key'] => $cache['key']);
				}
			}
		}
		return true;
	}

	/**
	 * Reads the cache object from storage.
	 *
	 * @param string $key						- Id of the cache entry to read
	 * @return array	includes key, data, expires
	 */
	protected function readCache($key)
	{
		$entry = $this->assertor->getRow('cache', array('cacheid' => $key));

		if (!$entry)
		{
			return false;
		}
		else if (($entry['expires'] > 0 ) AND ($entry['expires'] < $this->timeNow ))
		{
			return false;
		}
		else if (!empty($entry['data']) AND !empty($entry['serialized']) AND is_string($entry['data']))
		{
			$entry['data'] = @unserialize($entry['data']);
		}

		return array('key' => $key, 'data' => $entry['data'], 'created' => $entry['created'], 'expires' => $entry['expires']);
	}


	/**
	 * Reads an array of cache objects from storage.
	 *
	 * @param string $keys						- Ids of the cache entry to read
	 * @return array of array	includes key, data, expires
	 */
	protected function readCacheArray($keys, $writeLock = false)
	{
		$found = array();
		$toLock = array();

		if ($keys)
		{
			$rst = $this->assertor->assertQuery('cache',array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'cacheid'	=> $keys));
		}

		foreach($rst AS $record)
		{
			try
			{
				if ($record['data'])
				{
					if (intval($record['serialized']))
					{
						$record['data'] = @unserialize($record['data']);
					}

					//only return good values
					if (($record['expires'] == 0) OR ($this->timeNow < $record['expires']) )
					{
						$found[$record['cacheid']] = array('key' => $record['cacheid'], 'data' => $record['data'],
							'expires' => intval($record['expires']), 'locktime' => intval($record['locktime']));
						if ($writeLock AND !empty($toLock))
						{
							$this->lock($record['cacheid']);
						}
					}
				}
			}
			catch (exception $e)
			{
				//If we got here, something was improperly serialized
				//There's not much we can do, but we don't want to return bad data.
			}
		}

		return $found;
	}

	/**
	 * Removes a cache object from storage and the current page.
	 *
	 * @param int $key							- Key of the cache entry to purge
	 * @return bool								- Whether anything was purged
	 */
	protected function purgeCache($key)
	{
		unset($this->values_read[$key]);
		$this->no_values[$key] = $key;
		$this->expireCache($key);
	}

	/**
	 * Sets a cache entry as expired in storage.
	 *
	 * @param string/array $key						- Key of the cache entry to expire
	 *
	 * @return	array of killed items
	 */
	protected function expireCache($key)
	{
		if (empty($key))
		{
			return;
		}
		else
		{
			$this->assertor->delete('cache', array('cacheid' => $key));
		}
	}


	/**
	 * Locks a cache entry.
	 *
	 * @param string $keys	 array of string keys
	 */
	public function lock($key)
	{
		$currentTime = time();

		$cacheRecord = $this->readCache($key);

		if (!empty($cacheRecord) AND (($this->timeNow - $cacheRecord['locktime']) > $this->lockDuration))
		{
		// there's an entry, check if it is locked. if so we do nothing.
			if ($this->assertor->assertQuery('cache', array(vB_dB_Query::TYPE_KEY =>vB_dB_Query::QUERY_UPDATE,
				'locktime' => $this->timeNow - 1,  'cacheid' => $key))  > 0)
			{
				return true;
			}
		}
		return false;
	}


	/*Clean=========================================================================*/

	/**
	 * Cleans cache.
	 *
	 * @param bool $only_expired				- Only clean expired entries
	 */
	public function clean($only_expired = true)
	{
		if ($only_expired)
		{
			$queryData = array(
				'timefrom' => 1,
				'timeto' => $this->requestStart,
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			);

			$toBePurged = $this->assertor->getRows('cacheExpireSelect', $queryData);

			foreach ($toBePurged AS $entry)
			{
				unset( $this->values_read[$entry['cacheid']]);
			}

			$this->assertor->assertQuery('cacheExpireDelete', $queryData);
		}
		else
		{
			$this->values_read = array();
			$this->assertor->assertQuery('cacheDeleteAll');
		}
	}

	/** Adds an event record
	 	@param	string	cacheid
	 *	@param	mixed	array of strings	the events
	 *
	 */
	public function addEvents($cacheid, $events)
	{
		if (!isset($this->newEvents[$cacheid]))
		{
			$this->newEvents[$cacheid] = array();
		}

		if (!is_array($events))
		{
			$events = array($events);
		}
		foreach($events AS $event)
		{
			if (!in_array($event, $this->newEvents[$cacheid]))
			{
				$this->newEvents[$cacheid][] = $event;
			}
		}
	}


	/**
	 * Expires cache entries associated with triggered events.
	 *
	 * @return bool								- Whether any events were triggered
	 */
	public function event($events)
	{
		if (empty($events))
		{
			return;
		}
		if (!is_array($events))
		{
			$events = array($events);
		}
		// Get affected cache entries
		$result = $this->assertor->getRows('cacheevent', array( vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'event', 'value' => $events)
			)));

		$results = array();
		foreach ($result AS $entry)
		{
			$results[] = $entry['cacheid'];
		}


		foreach($events AS $event)
		{
			if (isset($this->pageEvents[$event]))
			{
				foreach ($this->pageEvents[$event] AS $cacheid)
				{
					$results[] = $cacheid;
					unset($this->values_read[$cacheid]);
					$this->no_values[$cacheid] = $cacheid;
					unset($this->recordsToSave[$cacheid]);
				}
			}
		}

		$this->expire($results);

		return true;
	}

	/**
	 * Tells the cache to trigger all events.
	 */
	public function cleanNow()
	{

	}
	//This is a delayed function, and does all the delayed writes

	public function shutdown()
	{
		//If we got an expire and a save for the same record, expire wins.
		if (!empty($this->deleteCache) and is_array($this->deleteCache))
		{
			$this->deleteCache = array_unique($this->deleteCache);
			foreach ($this->deleteCache AS $key =>$cacheid)
			{
				if (empty($cacheid) OR (is_string($cacheid) AND array_key_exists($cacheid, $this->recordsToSave)))
				{
					unset($this->recordsToSave[$cacheid]);
				}
			}
		}

		if (!empty($this->deleteCache))
		{
			$this->assertor->assertQuery('cacheAndEventDelete', array('cacheid' =>$this->deleteCache));
		}

		if (!empty($this->locked))
		{
			foreach($this->locked AS $cacheid)
			{
				unset($this->recordsToSave[$cacheid]);
			}
		}

		if (!empty($this->recordsToSave))
		{
			$this->assertor->assertQuery('saveDbCache', array('cache' => $this->recordsToSave));
		}

		if (!empty($this->newEvents))
		{
			foreach ($this->newEvents AS $cacheid => $events)
			{
				if (!empty($this->recordsToSave[$cacheid]['events']) AND is_array($this->recordsToSave[$cacheid]['events'])
				AND is_array($events))
				{
					$this->newEvents[$cacheid] = array_diff($this->newEvents[$cacheid], $this->recordsToSave[$cacheid]['events']);

					//now it's possible they are all overlap
					if (empty($this->newEvents[$cacheid]))
					{
						unset($this->newEvents[$cacheid]);
					}
				}
			}

			if (!empty($this->newEvents))
			{
				$this->assertor->assertQuery('saveDbCacheEvents', array('events' => $this->newEvents));
			}
		}

		$this->deleteCache = array();
		$this->recordsToSave = array();
		$this->newEvents = array();
		$this->no_values = array();
		$this->values_read = array();
	}


	/** Don't writing this cache record to disk
	 *
	 * @param	integer		the key for the cache record
	 *
	 *
	 *
	 */
	protected function cancelWrite($key)
	{
		//If we don't have a write for this then we can ignore.
		unset($this->recordsToSave[$key]);
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision: 29401 $
|| ####################################################################
\*======================================================================*/
