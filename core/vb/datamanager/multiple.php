<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Abstract class to do data update operations for a particular data type (such as user, thread, post etc.).
* Works on multiple records simultaneously. Updates will occur on all records matching set_condition().
*
* @package	vBulletin
* @version	$Revision: 38992 $
* @date		$Date: 2010-09-15 16:29:46 -0300 (Wed, 15 Sep 2010) $
*/
class vB_DataManager_Multiple
{
	/**
	* The vBulletin registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* The vBulletin database object
	*
	* @var	vB_Database
	*/
	var $dbobject = null;

	/**
	* The error handler for the child objects. Should be one of the ERRTYPE_* constants.
	*
	* @var	integer
	*/
	var $error_handler = vB_DataManager_Constants::ERRTYPE_STANDARD;

	/**
	* The name of the class to instantiate for each matching. It is assumed to exist!
	* It should be a subclass of vB_DataManager.
	*
	* @var	string
	*/
	var $class_name = 'vB_DataManager';

	/**
	* The base object of type $class_name. This is created in the constructor
	* for optimization purposes. Do not change this object.
	*
	* @var	vB_DataManager
	*/
	var $base_object = null;

	/**
	* The name of the primary ID column that is used to uniquely identify records retrieved.
	* This will be used to build the condition in all update queries!
	*
	* @var string
	*/
	var $primary_id = 'dataid';

	/**
	* Holds an array of vB_DataManager objects that matched the condition specified
	* in a call to set_condition(). The first object in this array becomes the "master".
	* Changes are done to it first; changes are not pushed to the rest of the matches
	* until copy_changes() is called.
	*
	* @var	array	Key: Primary ID of record; value: vB_DataManager object
	*/
	var $children = array();

	/**
	* Array of the primary ID fields (as specified by $primary_id) of any records
	* that matched in a call to set_condition(). This is used to build the condition
	* when saving.
	*
	* @var	array
	*/
	var $primary_ids = array();

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Multiple(&$registry, $errtype = vB_DataManager_Constants::ERRTYPE_STANDARD)
	{
		if (!is_subclass_of($this, 'vB_DataManager_Multiple'))
		{
			trigger_error("Direct Instantiation of vB_DataManager_Multiple class prohibited.", E_USER_ERROR);
		}

		if (is_object($registry))
		{
			$this->registry =& $registry;

			if (is_object($registry->db))
			{
				$this->dbobject =& $registry->db;
			}
			else
			{
				trigger_error("Database object is not an object", E_USER_ERROR);
			}
		}
		else
		{
			trigger_error("Registry object is not an object", E_USER_ERROR);
		}

		$this->error_handler = $errtype;

		$class = $this->class_name;
		$this->base_object = new $class($this->registry, $this->error_handler);
	}

	/**
	* Queries for matching records based on the condition specified,
	* and sets up the manager to make modifications to those records.
	*
	* @param	string	Condition to use in the fetch query; the entire WHERE clause
	* @param	integer	The number of records to limit the results to; 0 is unlimited
	* @param	integer	The number of records to skip before retrieving matches.
	*
	* @return	integer	The number of matching records
	*/
	function set_condition($condition = '', $limit = 0, $offset = 0)
	{
		// Init arrays to ensure memory is reclaimed
		$this->reset();

		$results = $this->dbobject->query_read($this->fetch_query($condition, $limit, $offset));
		while ($result = $this->dbobject->fetch_array($results))
		{
			$new = clone($this->base_object);
			$new->set_existing($result);
			$this->children[$result[$this->primary_id]] =& $new;

			$this->primary_ids[] = $result[$this->primary_id];
		}
		$this->dbobject->free_result($results);

		return sizeof($this->primary_ids);
	}

	/**
	* This function adds an existing record to the data manager. This is helpful
	* if you have already executed the query to grab the data, for example.
	*
	* @param	array	Array of existing data. MUST HAVE THE PRIMARY ID!
	*/
	function add_existing(&$existing)
	{
		$primary_id = $existing[$this->primary_id];
		if (!$primary_id)
		{
			trigger_error('You must pass a primary ID value to vB_DataManager_Multiple::add_existing.', E_USER_ERROR);
		}

		$new = clone($this->base_object);
		$new->set_existing($existing);
		$this->children["$primary_id"] =& $new;
		$this->primary_ids[] = $primary_id;
	}

	/**
	* Builds the SQL to run to fetch records. This must be overridden by a child class!
	*
	* @param	string	Condition to use in the fetch query; the entire WHERE clause
	* @param	integer	The number of records to limit the results to; 0 is unlimited
	* @param	integer	The number of records to skip before retrieving matches.
	*
	* @return	string	The query to execute
	*/
	function fetch_query($condition = '', $limit = 0, $offset = 0)
	{
		trigger_error('vB_DataManager_Multiple::fetch_query must be overridden.', E_USER_ERROR);
	}

	/**
	* Allows you to fetch the value of a field whose value was changed.
	* This does not look at existing data as it may vary from record to record!
	*
	* @param	string	Field name
	* @param	string	Table name to force. Leave as null to use the default table
	*
	* @return	mixed	The requested data
	*/
	function &fetch_field($fieldname, $table = null)
	{
		if (!$this->children)
		{
			return null;
		}

		$firstid = reset($this->primary_ids);
		$master =& $this->children["$firstid"];

		if ($table === null)
		{
			$table =& $master->table;
		}

		if (isset($master->{$table}["$fieldname"]))
		{
			return $master->{$table}["$fieldname"];
		}
		else
		{
			return null;
		}
	}

	/**
	* Sets the supplied data to be part of the data to be saved
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	* @param	boolean	Clean data, or insert it RAW (used for non-arbitrary updates, like posts = posts + 1)
	* @param	boolean	Whether to verify the data with the appropriate function. Still cleans data if previous arg is true.
	* @param	string	Table name to force. Leave as null to use the default table
	*
	* @return	boolean	Returns false if the data is rejected for whatever reason
	*/
	function set($fieldname, $value, $clean = true, $doverify = true)
	{
		if ($this->children)
		{
			$firstid = reset($this->primary_ids);
			return $this->children["$firstid"]->setr($fieldname, $value, $clean, $doverify);
		}

		return false;
	}

	/**
	* Sets a bit in a bitfield
	*
	* @param	string	Name of the database bitfield (options, permissions etc.)
	* @param	string	Name of the bit within the bitfield (canview, canpost etc.)
	* @param	boolean	Whether the bit should be set or not
	*
	* @return	boolean
	*/
	function set_bitfield($fieldname, $bitname, $onoff)
	{
		if ($this->children)
		{
			$firstid = reset($this->primary_ids);
			return $this->children["$firstid"]->set_bitfield($fieldname, $bitname, $onoff);
		}

		return false;
	}

	/**
	* Rather like set(), but sets data into the $this->info array instead
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	*/
	function set_info($fieldname, $value)
	{
		if ($this->children)
		{
			$firstid = reset($this->primary_ids);
			$this->children["$firstid"]->setr_info($fieldname, $value);
		}
	}

	/**
	* Pushes the changes made to the "master" child to the rest.
	*/
	function copy_changes()
	{
		if (sizeof($this->children) > 1)
		{
			$firstid = reset($this->primary_ids);
			$master =& $this->children["$firstid"];
			$table = $master->table;

			while ($id = next($this->primary_ids))
			{
				$child =& $this->children["$id"];
				$child->{$table} = $master->{$table};
				$child->info = $master->info;
			}
		}
	}

	/**
	* Saves the data from the object into the specified database tables
	*
	* @param	boolean	Do the query?
	* @param	boolean	Whether to call post_save_once() at the end
	*
	* @return	boolean	True on success; false on failure
	*/
	function save($doquery = true, $call_save_once = true)
	{
		if (!$this->children)
		{
			return false;
		}

		$master =& $this->children[reset($this->primary_ids)];

		// push changes to all children
		$this->copy_changes();

		// pre-save validation
		foreach ($this->primary_ids AS $id)
		{
			if (!$this->children["$id"]->pre_save($doquery))
			{
				return false;
			}
		}

		// update all children
		$this->execute_query($doquery);

		// post-save updates
		foreach ($this->primary_ids AS $id)
		{
			$this->children["$id"]->post_save_each($doquery);
		}

		if ($call_save_once)
		{
			$master->post_save_once($doquery);
		}

		return true;
	}

	/**
	* Executes the necessary query/queries to update the records
	*
	* @param	boolean	Actually perform the query?
	*/
	function execute_query($doquery = true)
	{
		$condition = $this->primary_id . ' IN (' . implode(',', $this->primary_ids) . ')';
		$master =& $this->children[reset($this->primary_ids)];

		$sql = $master->fetch_update_sql(TABLE_PREFIX, $master->table, $condition);
		if ($doquery)
		{
			if ($sql)
			{
				$this->dbobject->query_write($sql);
			}
		}
		else
		{
			echo "<pre>$sql<hr /></pre>";
		}
	}

	/**
	* Removes the records stored in the manager, resetting it (essentially) to
	* its start state.
	*/
	function reset()
	{
		$this->primary_ids = array();
		$this->children = array();
	}

	/**
	* This function iterate over a large result set, only processing records in
	* batches to keep the memory usage reasonable. After a batch has been collected,
	* a reference to this object is passed to a callback function. That function
	* will update any columns necessary. This function will the save the changes
	* and start on a new batch.
	*
	* Callback function first argument must be a reference to a vB_DataManager_Multiple object.
	* Additional arguments should be passed to this function in an array.
	*
	* @param	string|resource	A query result resource or a string containing the query to execute
	* @param	callback		The function to call to make changes to the records
	* @param	integer			Number of records to process in a batch
	* @param	array			Any additional arguments to pass to the callback function
	*/
	function batch_iterate($records, $callback, $batch_size = 500, $args = array())
	{
		if (is_string($records))
		{
			$records = $this->dbobject->query_read($records);
		}

		$intargs = array(&$this);
		foreach (array_keys($args) AS $argkey)
		{
			$intargs[] =& $args["$argkey"];
		}

		$counter = 0;
		while ($record = $this->dbobject->fetch_array($records))
		{
			// this if is seperate because otherwise, if we had
			// count($records) % $batch_size == 0, $this->children would be empty
			// so we couldn't call post_save_once().
			if (($counter % $batch_size) == 0)
			{
				$this->reset();
			}

			$this->add_existing($record);
			$counter++;

			if (($counter % $batch_size) == 0)
			{
				call_user_func_array($callback, $intargs);
				$this->save(true, false);
			}
		}
		$this->dbobject->free_result($records);

		if ($this->children AND ($counter % $batch_size) != 0)
		{
			call_user_func_array($callback, $intargs);
			$this->save(true, false);
		}

		$master =& $this->children[reset($this->primary_ids)];
		if ($master)
		{
			$master->post_save_once();
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 38992 $
|| ####################################################################
\*======================================================================*/