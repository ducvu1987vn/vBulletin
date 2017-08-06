<?php

/**
 * Class for fetching and initializing the vBulletin datastore from files
 *
 * @package	vBulletin
 * @version	$Revision: 37901 $
 * @date		$Date: 2010-07-14 19:28:12 -0300 (Wed, 14 Jul 2010) $
 */
class vB_Datastore_Filecache extends vB_Datastore
{

	/**
	 * Default items that are always loaded by fetch() when using the file method;
	 *
	 * @var	array
	 */
	protected $cacheableitems = array(
		'options',
		'bitfields',
		'forumcache',
		'usergroupcache',
		'stylecache',
		'languagecache',
		'products',
		'hooks',
	);

	protected $datastoreLocation;

	public function __construct(&$config, &$db_assertor)
	{
		parent::__construct($config, $db_assertor);

		if (defined('SKIP_DEFAULTDATASTORE'))
		{
			$this->cacheableitems = array('options', 'bitfields');
		}

		//this define is only used in this file so move it here.
		$vb5_config =& vB::getConfig();
		if (!empty($vb5_config['Misc']['datastorepath']))
		{
			$this->datastoreLocation = $vb5_config['Misc']['datastorepath'];
			return;
		}

		//It's cool if the user can set this in fileSystem cache and let this pick it up.
		if (!empty($vb5_config['Cache']['fileCachePath']) AND file_exists($vb5_config['Cache']['fileCachePath'])
			AND is_dir($vb5_config['Cache']['fileCachePath']))
		{
			$path = $vb5_config['Cache']['fileCachePath'] . '/datastore';

			if (!file_exists($path))
			{
				mkdir($path);
				file_put_contents($path . '/index.html', '');
			}

			if(is_dir($path))
			{

				if (!file_exists($path . '/datastore_cache.php') AND
					file_exists(DIR . '/includes/datastore/datastore_cache.php'))
				{
					copy(DIR . '/includes/datastore/datastore_cache.php', $path . '/datastore_cache.php');
				}

				if (!file_exists($path . 'datastore_cache.php'))
				{
					$this->datastoreLocation = $path;
				}
				return;
			}
		}
		$this->datastoreLocation = DIR . '/includes/datastore';
	}
	
	public function resetCache()
	{
		if (file_exists(DIR . '/includes/datastore/datastore_cache.php'))
		{
			copy(DIR . '/includes/datastore/datastore_cache.php', $this->datastoreLocation . '/datastore_cache.php');
		}
		elseif (defined('VB_AREA') AND (VB_AREA == 'AdminCP'))
		{
			trigger_error('Datastore cache file does not exist. Please reupload includes/datastore/datastore_cache.php from the original download.', E_USER_ERROR);
		}
	}

	/**
	 * Fetches the contents of the datastore from cache files
	 *
	 * @param	array	Array of items to fetch from the datastore
	 *
	 * @return	void
	 */
	public function fetch($items)
	{
		$include_return = @include_once($this->datastoreLocation . '/datastore_cache.php');
		if ($include_return === false)
		{
			if (defined('VB_AREA') AND (VB_AREA == 'AdminCP'))
			{
				trigger_error('Datastore cache file does not exist. Please reupload includes/datastore/datastore_cache.php from the original download.', E_USER_ERROR);
			}
			else
			{
				parent::fetch($items);
				return;
			}
		}

		// Ensure $this->cacheableitems are always fetched
		$unfetched_items = array();
		foreach ($this->cacheableitems AS $item)
		{
			if (!array_key_exists($item, $this->registered))
			{
				if (empty($$item) OR !isset($$item))
				{
					if (defined('VB_AREA') AND (VB_AREA == 'AdminCP'))
					{
						$$item = $this->fetch_build($item);
					}
					else
					{
						$unfetched_items[] = $item;
						continue;
					}
				}

				if ($this->register($item, $$item) === false)
				{
					trigger_error('Unable to register some datastore items', E_USER_ERROR);
				}

			}
		}

		// fetch anything remaining
		$items = $items ? array_merge($items, $unfetched_items) : $unfetched_items;
		if ($items = $this->prepare_itemlist($items, true))
		{
			if (!($result = $this->do_db_fetch($items)))
			{
				return false;
			}
		}

		$this->store_result = false;

		$this->check_options();
		return true;
	}

	/**
	 * Updates the appropriate cache file
	 *
	 * @param	string	title of the datastore item
	 * @param	mixed	The data associated with the title
	 *
	 * @return	void
	 */
	public function build($title = '', $data = '', $unserialize = 0)
	{
		parent::build($title, $data, $unserialize);

		if (!in_array($title, $this->cacheableitems))
		{
			return;
		}

		if (!file_exists($this->datastoreLocation  . '/datastore_cache.php'))
		{
			// file doesn't exist so don't try to write to it
			return;
		}

		$data_code = var_export(unserialize(trim($data)), true);

		if ($this->lock())
		{
			$cache = file_get_contents($this->datastoreLocation  . '/datastore_cache.php');

			// this is equivalent to the old preg_match system, but doesn't have problems with big files (#23186)
			$open_match = strpos($cache, "### start $title ###");
			if ($open_match) // we don't want to match the first character either!
			{
				// matched and not at the beginning
				$preceding = $cache[$open_match - 1];
				if ($preceding != "\n" AND $preceding != "\r")
				{
					$open_match = false;
				}
			}

			if ($open_match)
			{
				$close_match = strpos($cache, "### end $title ###", $open_match);
				if ($close_match) // we don't want to match the first character either!
				{
					// matched and not at the beginning
					$preceding = $cache[$close_match - 1];
					if ($preceding != "\n" AND $preceding != "\r")
					{
						$close_match = false;
					}
				}
			}

			// if we matched the beginning and end, then update the cache
			if (!empty($open_match) AND !empty($close_match))
			{
				$replace_start = $open_match - 1; // include the \n
				$replace_end = $close_match + strlen("### end $title ###");
				$cache = substr_replace($cache, "\n### start $title ###\n$$title = $data_code;\n### end $title ###", $replace_start, $replace_end - $replace_start);
			}

			// try an atomic operation first, if that fails go for the old method
			$atomic = false;
			if (($fp = @fopen($this->datastoreLocation . '/datastore_cache_atomic.php', 'w')))
			{
				fwrite($fp, $cache);
				fclose($fp);
				$atomic = $this->atomic_move($this->datastoreLocation . '/datastore_cache_atomic.php', $this->datastoreLocation . '/datastore_cache.php');
			}

			if (!$atomic AND ($fp = @fopen($this->datastoreLocation . '/datastore_cache.php', 'w')))
			{
				fwrite($fp, $cache);
				fclose($fp);
			}

			$this->unlock();

//			/* insert query */

			$this->db_assertor->assertQuery('replace_adminutil', array(
				'text' => $cache
					)
			);
		}
		else
		{
			trigger_error('Could not obtain file lock', E_USER_ERROR);
		}
	}

	/**
	 * Obtains a lock for the datastore. Attempt to get the lock multiple times before failing.
	 *
	 * @param	string	title of the datastore item
	 *
	 * @return	boolean
	 */
	protected function lock($title = '')
	{
		$lock_attempts = 5;
		while ($lock_attempts >= 1)
		{
			/*
			$result = $this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "adminutil SET
					text = UNIX_TIMESTAMP()
				WHERE title = 'datastorelock' AND text < UNIX_TIMESTAMP() - 15
			");
			 */
			$this->db_assertor->assertQuery('datastore_lock', array('datastore' =>  vB_dB_Query::QUERY_STORED));
			if ($this->db_assertor->affected_rows() > 0)
			{
				return true;
			}
			else
			{
				$lock_attempts--;
				sleep(1);
			}
		}

		return false;
	}

	/**
	 * Releases the datastore lock
	 *
	 * @param	string	title of the datastore item
	 *
	 * @return	void
	 */
	protected function unlock($title = '')
	{
//		$this->dbobject->query_write("UPDATE " . TABLE_PREFIX . "adminutil SET text = 0 WHERE title = 'datastorelock'");
		$this->db_assertor->assertQuery('adminutil', array(
			vB_dB_Query::TYPE_KEY =>  vB_dB_Query::QUERY_UPDATE,
			'title' => 'datastorelock',
			'text' => 0
		));
	}

	/**
	 * Fetches the specified datastore item from the database and tries
	 * to update the file cache with it. Data is automatically unserialized.
	 *
	 * @param	string	Datastore item to fetch
	 *
	 * @return	mixed	Data from datastore (unserialized if fetched)
	 */
	protected function fetch_build($title)
	{
		$data = '';
		$this->db_assertor->hide_errors();

		/*
		$dataitem = $this->dbobject->query_first("
			SELECT title, data
			FROM " . TABLE_PREFIX . "datastore
			WHERE title = '" . $this->dbobject->escape_string($title) ."'
		");
		 */
		$result = $this->db_assertor->assertQuery('fetch_options',  array(vB_dB_Query::TYPE_KEY =>  vB_dB_Query::QUERY_STORED,
		'option_names' => $title));

		$this->db_assertor->show_errors();
		if ($result->valid() && $dataitem = $result->current() && !empty($dataitem['title']))
		{
			$this->build($dataitem['title'], $dataitem['data']);
			$data = unserialize($dataitem['data']);
		}

		return $data;
	}

	/**
	 * Perform an atomic move where a request may occur before a file is written
	 *
	 * @param	string	Source Filename
	 * @param	string	Destination Filename
	 *
	 * @return	boolean
	 */
	protected function atomic_move($sourcefile, $destfile)
	{
		if (!@rename($sourcefile, $destfile))
		{
			if (copy($sourcefile, $destfile))
			{
				unlink($sourcefile);
				return true;
			}
			return false;
		}
		return true;
	}

}
