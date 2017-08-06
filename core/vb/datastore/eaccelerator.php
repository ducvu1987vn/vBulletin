<?php
/**
* Class for fetching and initializing the vBulletin datastore from eAccelerator
*
* @package	vBulletin
* @version	$Revision: 37901 $
* @date		$Date: 2010-07-14 19:28:12 -0300 (Wed, 14 Jul 2010) $
*/
class vB_Datastore_eAccelerator extends vB_Datastore
{
	/**
	* Indicates if the result of a call to the register function should store the value in memory
	*
	* @var	boolean
	*/
	var $store_result = false;
	
	public function resetCache()
	{
		eaccelerator_clear();
	}

	/**
	* Fetches the contents of the datastore from eAccelerator
	*
	* @param	array	Array of items to fetch from the datastore
	*
	* @return	void
	*/
	public function fetch($items)
	{
		if (!function_exists('eaccelerator_get'))
		{
			trigger_error('eAccelerator not installed', E_USER_ERROR);
		}

		if (!sizeof($items = $this->prepare_itemarray($items)))
		{
			return;
		}

		$unfetched_items = array();
		foreach ($items AS $item)
		{
			$this->do_fetch($item, $unfetched_items);
		}

		$this->store_result = true;

		// some of the items we are looking for were not found, lets get them in one go
		if (sizeof($unfetched_items))
		{
			if (!($result = $this->do_db_fetch($this->prepare_itemlist($unfetched_items))))
			{
				return false;
			}
		}

		$this->store_result = false;

		$this->check_options();
		return true;
	}

	/**
	* Fetches the data from shared memory and detects errors
	*
	* @param	string	title of the datastore item
	* @param	array	A reference to an array of items that failed and need to fetched from the database
	*
	* @return	boolean
	*/
	protected function do_fetch($title, &$unfetched_items)
	{
		$ptitle = $this->prefix . $title;

		if (($data = eaccelerator_get($ptitle)) === null)
		{ // appears its not there, lets grab the data
			$unfetched_items[] = $title;
			return false;
		}

		$this->register($title, $data);
		return true;
	}

	/**
	* Sorts the data returned from the cache and places it into appropriate places
	*
	* @param	string	The name of the data item to be processed
	* @param	mixed	The data associated with the title
	*
	* @return	void
	*/
	protected function register($title, $data, $unserialize_detect = 2)
	{
		if ($this->store_result === true)
		{
			$this->storeEAccelerator($title, $data);
		}

		parent::register($title, $data, $unserialize_detect);
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
		$this->storeEAccelerator($title, $data);
	}
	
	protected function storeEAccelerator($title, $data)
	{
		$ptitle = $this->prefix . $title;

		eaccelerator_rm($ptitle);
		eaccelerator_put($ptitle, $data);
	}
}
