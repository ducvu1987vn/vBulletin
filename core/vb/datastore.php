<?php

/**
 * Class for fetching and initializing the vBulletin datastore from the database
 *
 * @package	vBulletin
 * @version	$Revision: 42826 $
 * @date		$Date: 2011-04-13 15:28:31 -0300 (Wed, 13 Apr 2011) $
 */
class vB_Datastore
{
	const BITFIELDS_TITLE = 'bitfields';
	const BITFIELDS_PREFIX = 'bf_';

	/**
	 * Default items that are always loaded by fetch();
	 *
	 * @var	array
	 */
	protected  $defaultitems = array(

		'bitfields',
		'attachmentcache',
		'forumcache',
		'usergroupcache',
		'stylecache',
		'languagecache',
		'products',
		'cron',
		'profilefield',
		'loadcache',
		'miscoptions',
		'noticecache',
		'hooks',
		'publicoptions',
		'vBUgChannelAccess',
	);
	/**
	 * Unique prefix for item's title, required for multiple forums on the same server using the same classes that read/write to memory
	 *
	 * @var	string
	 */
	protected $prefix = '';
	/**
	 * Whether we have verified that options were loaded correctly.
	 *
	 * @var bool
	 */
	protected $checked_options;
	/**
	 * Contains the config variables loaded from the config file
	 * @var array
	 */
	protected $config;
	/**
	 * Contains the assertor object
	 *
	 * @var vB_dB_Assertor
	 */
	protected $db_assertor;

	/*
	 * This variable contains the titles that need to be fetched
	 *
	 * @var array
	 */
	protected $pending = array();
	/**
	 * All of the entries that have already been fetched
	 *
	 * @var array string
	 */
	protected $registered = array();

	protected $registeredBitfields = false;

	protected $noValues = array();

	public function __construct(&$config, &$db_assertor)
	{
		$this->config = & $config;

		if (empty($db_assertor))
		{
			$this->db_assertor = vB::getDbAssertor();
		}
		else
		{
			$this->db_assertor = & $db_assertor;
		}

		$this->prefix = & $this->config['Datastore']['prefix'];

		if (defined('SKIP_DEFAULTDATASTORE'))
		{
			$this->defaultitems = array('options', 'bitfields');
		}

		if (!is_object($db_assertor))
		{
			trigger_error('<strong>vB_Datastore</strong>: $this->db_assertor is not an object!', E_USER_ERROR);
		}
	}

	/**
	 * Resets datastore cache
	 */
	public function resetCache()
	{
		// nothing to do here
	}

	/**
	 * Set an array of items that should be preloaded. These will not be loaded immediately
	 * but will be fetched on the first call to getValue.
	 *
	 * @param array $titles
	 */
	public function pre_load($titles)
	{
		if (!empty($titles))
		{
			foreach ($titles as $title)
			{
				if (strpos($title, self::BITFIELDS_PREFIX) !== false)
				{
					$title = self::BITFIELDS_TITLE;
				}

				if (!in_array($title, $this->pending))
				{
					$this->pending[] = $title;
				}
			}
		}
	}


	/**
	 * @deprecated
	 */
	public function get_value($title)
	{
		return $this->getValue($title);
	}

	public function registerCount()
	{
		return count($this->registered);
	}

	public function getValue($title)
	{
		if (isset($this->registered[$title]))
		{
			return $this->registered[$title];
		}
		else if (isset($this->noValues[$title]))
		{
			return NULL;
		}
		else
		{
			$this->pre_load(array($title));
			if ($this->fetch($this->pending) AND isset($this->registered[$title]))
			{
				return $this->registered[$title];
			}
			else
			{
				return null;
			}
		}
	}

	public function getOption($name)
	{
		$options = $this->getValue('options');
		if (!isset($options[$name]))
		{
			return null;
		}
		return $options[$name];
	}

	public function setOption($name, $value, $save = true)
	{
		$setting = $this->db_assertor->getRow('setting', array('varname' => $name));
		$new_value = $value;
		$valid_value = $this->validate_setting_value($value, $setting['datatype'], true, false);
		$old_value = $this->validate_setting_value($setting['value'], $setting['datatype'], true, false);

		$options = $this->getValue('options');
		$options[$name] = $valid_value;
		$this->registered['options'] = $options;

		if ($valid_value != $setting['value'])
		{
			if ($save)
			{
				$this->db_assertor->update('setting', array('value' => $valid_value), array('varname' => $name));
				$this->build('options', serialize($options), 1);
			}
		}
	}

	/**
	 * This method is intended only for unit testing. Do NOT use it in other context.
	 * @param string $title
	 * @param mixed value
	 */
	public function setValue($title, $value)
	{
		if (!defined('VB_UNITTEST'))
		{
			throw new Exception('This method should be called only from unit tests');
		}
		else
		{
			$this->registered[$title] = $value;
		}
	}

	/**
	 * This method replaces the legacy function build_datastore
	 *
	 * @param string $title
	 * @param string $data
	 * @param int $unserialize
	 */
	public function build($title = '', $data = '', $unserialize = 0)
	{
		if (empty($title))
		{
			return;
		}
		//See if we already have a record.
		$assertor = vB::getDbAssertor();
		$existing = $assertor->assertQuery('datastore', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'title' => $title));

		if ($existing->valid())
		{
			$assertor->assertQuery('datastore', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'title' => $title, 'data' => $data, 'unserialize' => $unserialize));
		}
		else
		{
			$assertor->assertQuery('datastore', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'title' => $title, 'data' => $data, 'unserialize' => $unserialize));
		}
		$this->register($title, $data, $unserialize);
	}

	/**
	 * Temporary function to manage initializing the legacy registry from the datastore
	 *
	 * @deprecated
	 */
	public function init_registry()
	{
		$registry = & vB::get_registry();
		foreach($this->registered as $name => &$data)
		{
			$registry->{$name} = &$data;
		}
	}

	protected function update_registry($title, $data)
	{
		// todo: remove this when all references to vbulletin settings are replaced
		$registry = & vB::get_registry();
		if (!$registry)
		{
			return;
		}

		if ($title == self::BITFIELDS_TITLE)
		{
			foreach (array_keys($data) AS $group)
			{
				// todo: remove this when all references to vbulletin settings are replaced
				$registry->{self::BITFIELDS_PREFIX . $group} = & $data["$group"];
				$group_prefix = self::BITFIELDS_PREFIX . $group . '_';
				$group_info = & $data["$group"];
				foreach (array_keys($group_info) AS $subgroup)
				{
					// todo: remove this when all references to vbulletin settings are replaced
					$registry->{$group_prefix . $subgroup} = & $group_info["$subgroup"];
				}
			}
		}
		else if (!empty($title))
		{
			// todo: remove this when all references to vbulletin settings are replaced
			$registry->$title = $data;
		}
	}

	/**
	 * Sorts the data returned from the cache and places it into appropriate places
	 *
	 * @param	string	The name of the data item to be processed
	 * @param	mixed	The data associated with the title
	 * @param	integer	If the data needs to be unserialized, 0 = no, 1 = yes, 2 = auto detect
	 *
	 * @return	boolean
	 */
	protected function register($title, $data, $unserialize_detect = 2)
	{
		if ($this->registeredBitfields AND ($title == self::BITFIELDS_TITLE))
		{
			return true;
		}

		// specifies whether or not $data should be an array
		$try_unserialize = (($unserialize_detect == 2) AND !is_array($data) AND ($data[0] == 'a' AND $data[1] == ':'));

		if ($try_unserialize OR $unserialize_detect == 1)
		{
			// unserialize returned an error so return false
			if (($data = @unserialize($data)) === false)
			{
				return false;
			}
		}

		if ($title == self::BITFIELDS_TITLE)
		{
			foreach (array_keys($data) AS $group)
			{
				$this->registered[self::BITFIELDS_PREFIX . $group] = & $data["$group"];

				$group_prefix = self::BITFIELDS_PREFIX . $group . '_';
				$group_info = & $data["$group"];

				foreach (array_keys($group_info) AS $subgroup)
				{
					$this->registered[$group_prefix . $subgroup] = & $group_info["$subgroup"];
				}
			}
			$this->registeredBitfields = true;
		}
		else if (!empty($title))
		{
			$this->registered[$title] = $data;
		}

		//remove when the registry object is removed from the code.
		$this->update_registry($title, $data);
		return true;
	}

	/**
	 * Prepares a list of items for fetching.
	 * Items that are already fetched are skipped.
	 *
	 * @param array string $items				- Array of item titles that are required
	 * @return array string						- An array of items that need to be fetched
	 */
	protected function prepare_itemarray($items)
	{
		if ($items)
		{
			if (is_array($items))
			{
				$itemarray = $items;
			}
			else
			{
				$itemarray = explode(',', $items);

				foreach ($itemarray AS &$title)
				{
					$title = trim($title);
				}
			}
			// Include default items
			$itemarray = array_unique(array_merge($itemarray, $this->defaultitems));
		}
		else
		{
			$itemarray = $this->defaultitems;
		}

		// Remove anything that is already loaded
		//if we've already loaded the bitfields, don't do it again.
		if ($this->registeredBitfields)
		{
			$itemarray = array_diff($itemarray, array_keys($this->registered), array_keys($this->noValues), array(self::BITFIELDS_TITLE));
		}
		else
		{
			$itemarray = array_diff($itemarray, array_keys($this->registered), array_keys($this->noValues));
		}
		return $itemarray;
	}

	/**
	 * Prepares an array of items into a list.
	 * The result is a comma delimited, db escaped, quoted list for use in SQL.
	 *
	 * @param array string $items				- An array of item titles
	 * @param bool $prepare_items				- Wether to check the items first
	 *
	 * @return string							- A sql safe comma delimited list
	 */
	protected function prepare_itemlist($items, $prepare_items = false)
	{
		if (is_string($items) OR $prepare_items)
		{
			$items = $this->prepare_itemarray($items);
		}

		if (!sizeof($items))
		{
			return false;
		}

		return $items;
	}

	/**
	 * Fetches the contents of the datastore from the database
	 *
	 * @param	array	Array of items to fetch from the datastore
	 *
	 * @return	boolean
	 */
	public function fetch($items)
	{
		if ($items = $this->prepare_itemlist($items, true))
		{
			$result = $this->do_db_fetch($items);
			if (!$result)
			{
				return false;
			}
		}

		$this->check_options();

		return true;
	}

	/**
	 * Performs the actual fetching of the datastore items for the database, child classes may use this
	 *
	 * @param	string	title of the datastore item
	 *
	 * @return	bool	Valid Query?
	 */
	protected function do_db_fetch($itemlist)
	{
		$this->db_assertor->hide_errors();
		$result = $this->db_assertor->assertQuery('datastore', array('title' => $itemlist));
		$this->db_assertor->show_errors();

		while($result->valid())
		{
			$dataitem = $result->current();
			$this->register($dataitem['title'], $dataitem['data'], (isset($dataitem['unserialize']) ? $dataitem['unserialize'] : 2));
			//remove this value.
			$key = array_search($dataitem['title'], $itemlist);

			if ($key !== false)
			{
				unset($itemlist[$key]);
			}
			$result->next();
		}

		//Whatever is left we don't have in the database. No reason to query in the future;
		if (!empty($itemlist))
		{
			foreach($itemlist AS $item)
			{
				$this->noValues[$item] = $item;
			}
		}
		return true;
	}

	/**
	 * Checks that the options item has come out of the datastore correctly
	 * and sets the 'versionnumber' variable
	 */
	protected function check_options()
	{
		if ($this->checked_options)
		{
			return;
		}

		if (!isset($this->registered['options']['templateversion']))
		{
			// fatal error - options not loaded correctly
			$this->register('options', $this->build_options(), 0);
		}
		
		$this->check_pseudo_options();

		$this->checked_options = true;
	}

	/**
	 * Checks that certain pseudo-options (versionnumber and facebookactive) are set correctly
	 */
	protected function check_pseudo_options()
	{
		// set the short version number
		if (isset($this->registered['options']) && is_array($this->registered['options']) && !isset($this->registered['options']['simpleversion']))
		{
			$this->registered['options']['simpleversion'] = SIMPLE_VERSION . (isset($this->config['Misc']['jsver']) ? $this->config['Misc']['jsver'] : '');
		}
		if (isset($this->registered['publicoptions']) && is_array($this->registered['publicoptions']) && !isset($this->registered['publicoptions']['simpleversion']))
		{
			$this->registered['publicoptions']['simpleversion'] = SIMPLE_VERSION . (isset($this->config['Misc']['jsver']) ? $this->config['Misc']['jsver'] : '');
		}

		// set facebook active / inactive
		foreach (array('options', 'publicoptions') AS $key)
		{
			if (isset($this->registered[$key]) AND is_array($this->registered[$key]) AND !isset($this->registered[$key]['facebookactive']))
			{
				// if facebook is enabled and the appid & secret are set, then facebook is activated
				// always pull the facebook secret from the "options" array, since it is not public
				$this->registered[$key]['facebookactive'] = (bool) ($this->registered[$key]['enablefacebookconnect'] AND $this->registered[$key]['facebookappid'] AND !empty($this->registered['options']['facebooksecret']));
			}
		}
	}

	/**
	 * Reads settings from the settings then saves the values to the datastore
	 *
	 * After reading the contents of the setting table, the function will rebuild
	 * the $vbulletin->options array, then serialize the array and save that serialized
	 * array into the 'options' entry of the datastore in the database
	 *
	 * Extracted from adminfunctions.php
	 *
	 * @return	array	The $vbulletin->options array
	 */
	public function build_options()
	{
		$options = array();

		$result = $this->db_assertor->assertQuery('setting',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT));

		foreach ($result as $setting)
		{
			$options["$setting[varname]"] = $this->validate_setting_value($setting['value'], $setting['datatype'], true, false);
		}

		if (isset($options['cookiepath']) AND substr($options['cookiepath'], -1, 1) != '/')
		{
			$options['cookiepath'] .= '/';
			$this->db_assertor->assertQuery('setting', array(
				vB_dB_Query::TYPE_KEY =>  vB_dB_Query::QUERY_UPDATE,
				'varname' => 'cookiepath',
				'value' => $options['cookiepath']
					)
			);
		}

		$this->build('options', serialize($options), 1);
		$this->registered['options'] = $options;

		// Build public options
		$publicoptions = array();
		foreach ($result as $setting)
		{
			if ($setting['ispublic'])
			{
				$publicoptions["$setting[varname]"] = $options["$setting[varname]"];
			}
		}
		$this->build('publicoptions', serialize($publicoptions), 1);
		$this->registered['publicoptions'] = $publicoptions;

		return $options;
	}

	/**
	 * Validates the provided value of a setting against its datatype.
	 * Extracted from adminfunctions_options
	 *
	 * @param	mixed	(ref) Setting value
	 * @param	string	Setting datatype ('number', 'boolean' or other)
	 * @param	boolean	Represent boolean with 1/0 instead of true/false
	 * @param boolean  Query database for username type
	 *
	 * @return	mixed	Setting value
	 */
	protected function validate_setting_value(&$value, $datatype, $bool_as_int = true, $username_query = true)
	{
		switch ($datatype)
		{
			case 'number':
				$value += 0;
				break;

			case 'integer':
				$value = intval($value);
				break;

			case 'arrayinteger':
				$key = array_keys($value);
				$size = sizeOf($key);
				for ($i = 0; $i < $size; $i++)
				{
					$value[$key[$i]] = intval($value[$key[$i]]);
				}
				break;

			case 'arrayfree':
				$key = array_keys($value);
				$size = sizeOf($key);
				for ($i = 0; $i < $size; $i++)
				{
					$value[$key[$i]] = trim($value[$key[$i]]);
				}
				break;

			case 'posint':
				$value = max(1, intval($value));
				break;

			case 'boolean':
				$value = ($bool_as_int ? ($value ? 1 : 0) : ($value ? true : false));
				break;

			case 'bitfield':
				if (is_array($value))
				{
					$bitfield = 0;
					foreach ($value AS $bitval)
					{
						$bitfield += $bitval;
					}
					$value = $bitfield;
				}
				else
				{
					$value += 0;
				}
				break;

			case 'username':
				$value = trim($value);
				if ($username_query)
				{
					if (empty($value))
					{
						$value = 0;
					}
					else
					{
						$result = $this->db_assertor->assertQuery('user', array(
									vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
									'username' => htmlspecialchars_uni($value)
										)
						);

						if ($result->valid())
						{
							$userinfo = $result->current();
							$value = $userinfo['userid'];
						}
						else
						{
							$value = false;
						}
					}
				}
				break;

			default:
				$value = trim($value);
		}

		return $value;
	}

	/**
	 *
	 * Gets usergroup data to save it in the datastore and update the related values.
	 * This includes some validations to keep consistency in the datastore and db.
	 *
	 *	@param	array	An array containing the usergroups information.
	 *
	 * 	@param	array	The saved usergroup info
	 */
	public function buildUserGroupCache($usergroupinfo)
	{
		// Removed the call to validateUserGroup(), see VBV-6051.
		// We do not need to validate / clean the usergroup info.
		// The only extra items will be user group permissions
		// added by addons / products.

		// set info needed
		$this->build('usergroupcache', serialize($usergroupinfo), 1);
		$this->registered['usergroupcache'] = $usergroupinfo;
		return $usergroupinfo;
	}
}
