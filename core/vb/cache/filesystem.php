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
class vB_Cache_Filesystem extends vB_Cache
{
	/*Properties====================================================================*/

	/** There are three different kinds of files we could have;
	 *	XXXX.dat 	that's a data file. First we have a six-character space padded size. That's the space taken by the
	 * 				serialized array.
	 * 				next is a serialized array, with values 'expires', 'events', 'serialized', 'created', 'size'
 	 * 			the rest of the file is the data, which can be either text or a serialized array
	 *
	 *	XXXXX.del	That means that the file with the extension "dat" is invalid, but a delete of the file failed
	 *
	 * XXXXX.ev		That is an event file. Each line contains a cache id that should be cleared.
	 */

	/**
	 *
	 * @var requestStart
	 */
	protected $requestStart;

	protected $cacheLocation;

	protected $loadedData = array();

	/*Construction==================================================================*/

	/**
	 * Constructor public to allow for separate automated unit testing. Actual code should use
	 * vB_Cache::instance();
	 * @see vB_Cache::instance()
	 */
	public function __construct($cachetype)
	{
		parent::__construct($cachetype);
		$this->requestStart = vB::getRequest()->getTimeNow();
		$config = vB::getConfig();
		$this->cachetype = $cachetype;

		if (!isset($config['Cache']['fileCachePath']))
		{
			throw new vB_Exception_Cache('need_filecache_location');
		}
		$this->cacheLocation = $config['Cache']['fileCachePath'];

		if (!is_dir($this->cacheLocation) or !is_writable($this->cacheLocation))
		{
			throw new vB_Exception_Cache('invalid_filecache_location- ' . $this->cacheLocation);
		}
	}

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

		$cacheid = strtolower($cache['key']);
		$cachePath = $this->getPath($cacheid);

		if(!$cachePath)
		{
			return false;
		}

		$expires = $cache['expires'];
		$events = $cache['events'];
		$eventFiles = array();
		$summary = serialize(array('expires' => $expires, 'events' => $events, 'serialized' => $serialized,
			'created' => vB::getRequest()->getTimeNow(), 'size' => strlen($data)));

		if (!empty($events))
		{
			if (!is_array($events))
			{
				$events = array($events);
			}
			foreach ($events AS $event)
			{
				$eventFiles[] = $this->getPath($event) . ".ev";
			}
		}

		//File handling uses errors, but we're set up to use exceptions.
		set_error_handler(array($this, 'errorToException'), E_WARNING);
		try
		{
			//We need to block reading until we're done.
			if (!file_exists($cachePath . ".del"))
			{
				if (file_put_contents($cachePath . ".del", ' ') == false)
				{
					return false;
				}
			}

			//if there's an existing file, delete it.
			if (file_exists($cachePath . ".dat"))
			{
				unlink($cachePath . ".dat");
			}

			foreach ($eventFiles as $eventFile)
			{
				//if it doesn't exist we just create it.
				if (!file_exists($eventFile) )
				{
					file_put_contents($eventFile, "\r\n" .$cacheid );
				}
				else
				{
					$fhandle = fopen($eventFile, 'r');

					if (!$fhandle)
					{
						//if the file exists and we can't open it, anything we do is risky.
						return false;
					}
					$found = false;
					while ($line = fgets($fhandle))
					{
						if ($line == $cacheid)
						{
							$found = true;
							break;
						}
					}
					fclose($fhandle);

					if (!$found)
					{
						$fhandle = fopen($eventFile, 'a');

						if (!$fhandle)
						{
							//if the file exists and we can't open it, anything we do is risky.
							return false;
						}
						fputs($fhandle, "\r\n" . $cacheid );
						fclose($fhandle);
					}
				}
			}

			//now we can write the data file.
			$fhandle = fopen($cachePath . ".dat", 'a');

			if (!$fhandle)
			{
				//if the file exists and we can't open it, anything we do is risky.
				return false;
			}

			//First we have a six-character space padded size. That's the space taken by the serialized array.
			fputs($fhandle, str_pad(strlen($summary), 6, '_'));
			//next is a serialized array, with values 'expires', 'events', 'serialized', 'created', 'size'
			fputs($fhandle, $summary);
			//the rest of the file is the data
			fwrite($fhandle, $data, strlen($data));
			fclose($fhandle);

			//delete the del file
			if (file_exists($cachePath . ".del"))
			{
				unlink($cachePath . ".del");
				restore_error_handler();
				return false;
			}

			restore_error_handler();
			$this->loadedData[$cacheid] = $cache['data'];
			return true;
		}
		catch(Exception $e)
		{
			//It doesn't really matter why we got here. The sequence of operations ensures that no invalid data will be
			// stored
			restore_error_handler();

			if (!empty($fhandle))
			{
				fclose($fhandle);
			}
			return false;
		}

	}

	/**This catches filesystem errors and turns them to exceptions, which we are better at handling
	 * @param $err
	 *
	 */
	public function errorToException($errno , $errstr, $errfile, $errline)
	{
		throw new vB_Exception_Cache($errstr, $errno, $errfile, $errline);
		return false;
	}

	/**
	 * Reads the cache object from storage.
	 *
	 * @param string $key						- Id of the cache entry to read
	 * @return array	includes key, data, expires
	 */
	protected function readCache($key)
	{
		if (isset($this->values_read[$key]))
		{
			return $this->values_read[$key]['data'];
		}

		if (isset($this->no_values[$key]))
		{
			return false;
		}

		// The $cache is to prevent cache table from being queried twice
		// See VBV-4473
		// If vB_Cache::read()'s $write_lock parameter is set to true,
		// This function will be called twice in vB_Cache::read() itself and vB_Cache_Db::lock().
		$key = strtolower($key);
		//File handling uses errors, but we're set up to use exceptions.
		set_error_handler(array($this, 'errorToException'), E_WARNING);
		try
		{
			$cachePath = $this->getPath($key);

			if(!$cachePath)
			{
				return false;
			}

			//If there's a del file, this value is expired or otherwise invalid.
			if (file_exists($cachePath . '.del'))
			{
				if (file_exists($cachePath . '.dat'))
				{
					unlink ($cachePath . '.dat');
				}
				restore_error_handler();
				return false;
			}

			if (!file_exists($cachePath . '.dat'))
			{
				restore_error_handler();
				return false;
			}

			$timenow = vB::getRequest()->getTimeNow();
			//now we can open the file.
			$fhandle = fopen($cachePath . '.dat', 'r');

			if (!$fhandle)
			{
				restore_error_handler();
				return false;
			}
			//First we have a six-character space padded size. That's the space taken by the serialized array.
			$size = intval(fread($fhandle, 6));
			//next is a serialized array, with values 'expires', 'events', 'serialized', 'created', 'size'
			$summary = unserialize(fread($fhandle, $size));
			//the rest of the file is the data

			//if it's expired we're done.
			if (($summary['expires'] > 0) and ($summary['expires'] < $timenow))
			{
				fclose($fhandle);

				//try to delete the file
				if (!unlink ($cachePath . '.dat'))
				{
					file_put_contents($cachePath . 'del', ' ');
				}
				restore_error_handler();
				return false;
			}
			$data = '';
			$chunk = fread($fhandle, 4096);
			while(!empty($chunk) AND (strlen($data) < $summary['size']))
			{
				$data .= $chunk ;
				$chunk = fread($fhandle, 4096);
			}
			fclose($fhandle);

			if ($summary['serialized'])
			{
				$data = unserialize($data);
			}
			//the rest of the file is the data, which can be either text or a serialized array

			return array('key' => $key, 'data' => $data, 'expires' => $summary['expires']);
		}
		catch(Exception $e)
		{
			//It doesn't really matter why we got here. The sequence of operations ensures that no invalid data will be
			// stored
			restore_error_handler();

			if (!empty($fhandle))
			{
				fclose($fhandle);
			}
			return false;
		}
	}

	protected function getPath($filename)
	{

		if (empty($filename))
		{
			return false;
		}

		//generally we split on underscore and each numeric character.
		$filename = strtolower($filename);
		$path = preg_replace('^[0-9]^', './$0' , $filename, -1, $count) ;
		$path = str_replace('_', '/' , $path);
		$path = str_replace('.', '/' , $path);
		while (strpos($path, '//') !== false)
		{
			$path = str_replace('//', '/' , $path);
		}

		//In rare cases we could have a cache id with neither underscores or spaces.
		if (strpos($path, '/') === false)
		{
			$path = substr($path, 0, 2) . '/' . substr($path, 2);
		}
		//Now make sure the folder exists at each level.
		$partialPath = $this->cacheLocation ;
		set_error_handler(array($this, 'errorToException'), E_WARNING);

		if (!is_dir($partialPath . '/' . $path))
		{
			try
			{
				vB_Utilities::vbmkdir($partialPath . '/' . $path);
			}
			catch(exception $e)
			{
				restore_error_handler();
				return false;
			}
		}

		foreach(explode('/', $path) as $segment)
		{
			$partialPath .= '/' . $segment;

			if (!file_exists($partialPath .'/index.html'))
			{
				file_put_contents($partialPath .'/index.html' , '', 0);
			}
		}

		restore_error_handler();
		return $partialPath . "/" . $filename;
	}

	/**
	 * Removes a cache object from storage.
	 *
	 * @param int $key							- Key of the cache entry to purge
	 * @return bool								- Whether anything was purged
	 */
	protected function purgeCache($key)
	{
		return $this->clearOneCache(false, $key, true);
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
		if (!is_array($key))
		{
			$key = array($key);
		}

		$valid = array();
		foreach ($key AS $cacheid)
		{
			if ($this->expireCacheInternal(strtolower($cacheid)))
			{
				$valid[] = $cacheid;
			}
		}

		return $valid;
	}

	/**
	 * Sets a cache entry as expired in storage.
	 *
	 * @param string $key						- Key of the cache entry to expire
	 *
	 * @return	bool
	 */
	protected function expireCacheInternal($key)
	{
		$cachePath  = $this->getPath(strtolower($key));

		if(!$cachePath)
		{
			return false;
		}

		if (file_exists($cachePath . ".dat"))
		{
			if (unlink ($cachePath . ".dat"))
			{
				if (file_exists($cachePath . ".del"))
				{
					unlink($cachePath . ".del");
				}
			}
			else if (!file_exists($cachePath . ".del"))
			{
				if (file_put_contents($cachePath . ".del", ' ') == false)
				{
					return false;
				}
			}
		}

		return true;
	}


	/**
	 * Locks a cache entry.
	 *
	 * @param string $key						- Key of the cache entry to lock
	 * @return bool - TRUE iff the lock was obtained
	 */
	public function lock($key)
	{
		//the locking method is not well designed or particularly useful. So we ignore it.
		return true;
	}

	/*Clean=========================================================================*/

	/**
	 * Cleans cache.
	 *
	 * @param bool $only_expired				- Only clean expired entries
	 */
	public function clean($only_expired = true)
	{
		$this->cleanDir($this->cacheLocation . '/', $only_expired);
	}

	/**This is a recursive function which deletes all files in a folder.

	 * @param	string
	 */
	protected function cleanDir($path, $only_expired = true)
	{
		if (!is_dir($path))
		{
			//bad data.
			return false;
		}

		//We use a relative path, for safety. Don't want to do ramping around deleting stuff we shouldn't.
		if (strpos($path, '..') !== false OR (substr($path, 0, 4) != substr($this->cacheLocation, 0, 4)))
		{
			//too dangerous. Goodbye.
			return false;
		}

		$directory = opendir($path);
		rewinddir($directory);
		$dirs = array();
		while(($file = readdir($directory)) !== false)
		{
			if (!in_array($file, array('.', '..', 'index.html', 'datastore', 'datastore_cache.php')))
			{
				if (is_dir($path . $file))
				{
					$dirs[] = $path . $file . '/';
				}
				else if (substr($file, -4) == '.dat')
				{
					$this->clearOneCache($path . $file, false, $only_expired);
				}
			}
			//At this point you might notice we don't delete orphan .del files. That's because they are harmless
		}
		closedir($directory);

		foreach ($dirs AS $dir)
		{
			$this->cleanDir($dir, $only_expired	);
		}
		return true;
	}

	/**Clears a single cache value, ihcluding events and the del file if present.
	 *
	 *	@param	string	the file name
	 * 	@param	string	the cacheid
	 */
	protected function clearOneCache($fileName = false, $cacheid = false, $only_expired = true)
	{
		if (!empty($fileName))
		{
			if (!file_exists($fileName))
			{
					return false;
			}

			$fileInfo = pathinfo($fileName);
			//This only makes sense for .dat files

			if ($fileInfo['extension'] != 'dat')
			{
				return false;
			}
			$cacheid = $fileInfo['basename'];
			$baseName = $fileInfo['dirname'] . '/' . $cacheid;
		}
		else if (!empty($cacheid))
		{
			$baseName = $this->getPath($fileName);
			$fileName =  $baseName . '.dat';
		}
		else
		{
			return false;
		}

		$fhandle = fopen($fileName, 'r');

		if (!$fhandle)
		{
			return false;
		}
		//First we have a six-character space padded size. That's the space taken by the serialized array.
		$size = intval(fread($fhandle, 6));

		//next is a serialized array, with values 'expires', 'events', 'serialized', 'created', 'size'
		$summary = unserialize(fread($fhandle, $size));
		fclose($fhandle);

		if ($only_expired AND ($summary['expires'] >= vB::getRequest()->getTimeNow()))
		{
			return true;
		}
		//we delete the file first, then the del file, then the events.
		if (file_exists($fileName))
		{
			unlink($fileName);
		}

		if (file_exists($baseName . '.del'))
		{
			unlink($baseName . '.del');
		}

		if (!empty($summary['events']))
		{
			if (!is_array($summary['events']))
			{
				$summary['events'] = array($summary['events']);
			}
			foreach ($summary['events'] as $event)
			{
				$eventFile = $this->getPath($event) . '.ev';

				if(!$eventFile)
				{
					continue;
				}
				if (file_exists($eventFile))
				{
					unlink($eventFile);
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
		if (!is_array($events))
		{
			$events = (array)$events;
		}
		foreach ($events AS $event)
		{
			$event = strtolower($event);
			$eventLoc = $this->getPath($event);

			if(!$eventLoc)
			{
				continue;
			}

			if (file_exists($eventLoc . '.ev'))
			{
				try
				{
					$eventHandle = fopen($eventLoc . '.ev', 'r');
					while ($cacheid = fgets($eventHandle))
					{
						$cacheid = strtolower($cacheid);

						if (empty($cacheid))
						{
							continue;
						}
						$cacheLoc = $this->getPath($cacheid);

						if (array_key_exists($cacheid, $this->loadedData))
						{
							unset($this->loadedData[$cacheid]);
						}

						if(!$cacheLoc)
						{
							continue;
						}
						//standard practice- first we make the 'del' file
						if (!file_exists($cacheLoc . '.del'))
						{
							file_put_contents($cacheLoc . '.del', ' ');
						}

						if (file_exists($cacheLoc . '.dat'))
						{
							if (unlink($cacheLoc . '.dat') AND file_exists($cacheLoc . '.del'))
							{
								unlink($cacheLoc . '.del');
							}
						}
					}
					fclose($eventHandle);
				}
				catch(exception $e)
				{
					//Nothing we can do with this event- let's try any others.
					if ($eventHandle)
					{
						fclose($eventHandle);
					}
				}
			}
		}
		return $this;
	}

	protected function getLoadedValue($cacheid)
	{
		$cacheid = strtolower($cacheid);

		if (array_key_exists($cacheid, $this->loadedData))
		{
			return $this->readCache($cacheid);
		}

		return false;
	}

	protected function getData($cacheid)
	{
		return $this->loadedData[strtolower($cacheid)];
	}

}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision: 29401 $
|| ####################################################################
\*======================================================================*/
