<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright  2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Abstract class to do data save/delete operations for a particular data type (such as user, thread, post etc.)
*
* @package	vBulletin
* @version	$Revision: 38992 $
* @date		$Date: 2010-09-15 16:29:46 -0300 (Wed, 15 Sep 2010) $
*/
abstract class vB_DataManager
{
	/*Constants=====================================================================*/

	/*
	* Error types, not used anymore - See vB_DataManager_Constants
	*/
//	const ERRTYPE_ARRAY = 0;
//	const ERRTYPE_STANDARD = 1;
//	const ERRTYPE_CP = 2;
//	const ERRTYPE_SILENT = 3;
	/**
	* Array of field names that are valid for this data object
	*
	* Each array element has the field name as its key, and then a three element array as the value.
	* These three elements are used as follows:
	* FIELD 0 (VF_TYPE) - This specifies the expected data type of the field, and draws on the
	* 	data types defined for the vB_Input_Cleaner class
	* FIELD 1 (VF_REQ) - This specified whether or not the field is REQUIRED for a valid INSERT query.
	* 	Options include REQ_NO, REQ_YES and REQ_AUTO, which is a special option, indicating that the value of the field is automatically created
	* FIELD 2 (VF_CODE) - This contains code to be executed as a lamda function called as 'function($data, $dm)'.
	* 	Alternatively, the value can be VF_METHOD, in which case, $this->verify_{$fieldname} will be called.
	*
	* @var	array
	*/
	var $validfields = array();

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	* For example: var $bitfields = array('options' => 'bf_misc_useroptions', 'permissions' => 'bf_misc_moderatorpermissions')
	*
	* @var	array
	*/
	protected $bitfields = array();

	/**
	* Array to store the names of fields that have been sucessfully set
	*
	* @var	array
	*/
	protected $setfields = array();

	/**
	* Array to store the names for fields that will be taking raw SQL
	*
	* @var	array
	*/
	var $rawfields = array();

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
	* The vBulletin dB_Assertor object
	*
	* @var	vB_dB_Assertor
	*/
	protected $assertor = null;

	/**
	 * The vBulletin vB_Session object
	 *
	 * @var vB_Session $session
	 */
	protected $session = null;

	/**
	 * The userinfo array from vB_Session object
	 *
	 * @var array $userinfo
	 */
	protected $userinfo = array();

	/**
	 * The options array from vB_Datastore object
	 *
	 * @var array $options
	 */
	protected $options = array();

	/**
	 * The vBulletin vB_Datastore object
	 *
	 * @var vB_Datastore $datastore
	 */
	protected $datastore = array();

	/**
	* Will contain the temporary verification function for each field
	*
	* @var	function
	*/
	var $lamda = null;

	/**
	* Array to store any errors encountered while building data
	*
	* @var	array
	*/
	var $errors = array();

	/**
	* The error handler for this object
	*
	* @var	string
	*/
	var $error_handler = vB_DataManager_Constants::ERRTYPE_STANDARD;

	/**
	* Array to store existing data
	*
	* @var	array
	*/
	var $existing = array();

	/**
	* Array to store information
	*
	* @var	array
	*/
	var $info = array();

	/**
	* Condition to be used. Can be either array('keyfield' => 'value') or a valid assertor vB_dB_Query::CONDITIONS_KEY setting.
	*
	* @var	string
	*/
	var $condition = null;

	/**
	* Default table to be used in queries
	*
	* @var	string
	*/
	var $table = 'default_table';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/

	/**
	* Callback to execute just before an error is logged.
	*
	* @var	callback
	*/
	var $failure_callback = null;

	/**
	* This variable prevents the pre_save() method from being called more than once.
	* In some classes, it is helpful to explicitly call pre_save() before
	* calling save as additional checks are done. This variable is used to prevent
	* pre_save() from being executed when save() is called. If null, pre_save()
	* has yet to be called; else, it is the return value of pre_save().
	*
	* @var	null|bool
	*/
	var $presave_called = null;

	/** flag for vb5 transition. A subclass can set this to false and we won't set up $vbulletin **/
	protected $needRegistry = true;

	protected $keyField = false;

	/**
	* Constructor - Checks for necessity of registry object
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	public function vB_DataManager($registry = NULL, $errtype = NULL)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else if ($this->needRegistry)
		{
			$this->registry = vB::get_registry();
		}

		if (is_int($registry) and $errtype === NULL)
		{
			//This allows us to function as either vb3/4 style with $vbulletin,
			// or vb5-style with no global variables
			$errtype = $registry;
		}

		if ($errtype === NULL)
		{
			$errtype = vB_DataManager_Constants::ERRTYPE_STANDARD;
		}

		$this->assertor = vB::getDbAssertor();
		$this->session = vB::getCurrentSession();
		$this->userinfo = $this->session->fetch_userinfo();
		$this->datastore = vB::getDatastore();
		$this->options = $this->datastore->get_value('options');

		$this->setErrorHandler($errtype);

		if (is_array($this->bitfields))
		{
			foreach ($this->bitfields AS $key => $val)
			{
				//set this to bitfields array directly and unset if bad. if we try to set this to a interim
				//variable we end up getting the references crossed so that every element of the array is
				//the same as the last value loaded (this is a bit of a problem).  We could not use references
				//but I'd like to avoid copying static arrays more than I need to.
				$this->bitfields["$key"] = $this->datastore->get_value($val);
				if (!$this->bitfields["$key"])
				{
					unset($this->bitfields["$key"]);
					trigger_error("Please check the <em>\$bitfields</em> array in the <strong>" . get_class($this) .
						"</strong> class definition - <em>\$vbulletin->$val</em> is not a valid bitfield.<br />", E_USER_ERROR);
				}
			}
		}

		/* Legacy Hook $this->hook_start Removed */
	}

	/**
	* Sets the existing data
	*
	* @param	array	Optional array of data describing the existing data we will be updating
	*
	* @return	boolean	Returns true if successful
	*/
	function set_existing($existing)
	{
		if (is_array($existing))
		{
			if (sizeof($this->existing) == 0)
			{
				$this->existing =& $existing;
			}
			else
			{
				foreach (array_keys($existing) AS $fieldname)
				{
					$this->existing["$fieldname"] =& $existing["$fieldname"];
				}
			}

			$this->set_condition();
		}
		else
		{
			throw new Exception('Existing data passed is not an array');
		}
	}

	/**
	 * Sets the condition to be used in WHERE clauses, based upon the $this->existing data and
	 * the $this->condition_constuct condition template.
	 */
	function set_condition($params = null)
	{
		if (!empty($params))
		{
			$this->condition = $params;
			return true;
		}
		else
		{
			if (empty($this->keyField))
			{
				return false;
			}

			if (is_array($this->keyField))
			{
				$keyFields = $this->keyField;
			}
			else
			{
				$keyFields = array($this->keyField);
			}

			$condition = array();
			foreach ($keyFields AS $key)
			{
				if (isset($this->existing[$key]))
				{
					$condition[] = array(
						'field' => $key,
						'value' => $this->existing[$key],
						'operator' => vB_dB_Query::OPERATOR_EQ
					);
				}
				else
				{
						return false;
				}
			}
			//if we got here we should have valid conditions.

			if (!empty($condition))
			{
				$this->condition = $condition;
			}
		}
	}

	/**
	 * Determines if a fields is set
	 */

	public function is_field_set($field)
	{
		return isset($this->setfields[$field]);
	}

	/**
	* Fetches info about the current data object - if a new value is set, it returns this, otherwise it will return the existing data
	*
	* @param	string	Fieldname
	*
	* @return	mixed	The requested data
	*/
	function &fetch_field($fieldname, $table = null)
	{
		if ($table === null)
		{
			$table = $this->table;
		}

		$table = $this->fetchTableBase($table);

		if (isset($this->{$table}["$fieldname"]))
		{
			return $this->{$table}["$fieldname"];
		}
		else if (isset($this->existing["$fieldname"]))
		{
			return $this->existing["$fieldname"];
		}
		else
		{
			$return = null;
			return $return;
		}
	}

	protected function loadExisting($keyValue)
	{
		if (empty($this->keyField))
		{
			throw new Exception('invalid_data');
		}

		$params = array(vB_dB_Query::QUERY_SELECT);

		if (is_array($this->keyField))
		{
			if (!is_array($keyValue))
			{
				throw new Exception('invalid_data');
			}

			foreach ($this->keyField AS $field)
			{
				if (!isset($keyValue[$field]))
				{
					throw new Exception('invalid_data');
				}
				$params[$field] = $keyValue[$field];
			}
		}
		else
		{
			if (is_array($keyValue))
			{
				throw new Exception('invalid_data');
			}
			$params[$this->keyField] = $keyValue;
		}
		$this->existing = $this->assertor->getRow($this->table, $params);
	}

	/**
	* Sets the supplied data to be part of the data to be saved. Use setr() if a reference to $value is to be passed
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	* @param	boolean	Clean data, or insert it RAW (used for non-arbitrary updates, like posts = posts + 1)
	* @param	boolean	Whether to verify the data with the appropriate function. Still cleans data if previous arg is true.
	* @param	string	Table name to force. Leave as null to use the default table
	*
	* @return	boolean	Returns false if the data is rejected for whatever reason
	*/
	function set($fieldname, $value, $clean = true, $doverify = true, $table = null)
	{
		if ($clean)
		{
			$verify = $this->verify($fieldname, $value, $doverify);
			if ($verify === true)
			{
				$this->do_set($fieldname, $value, $table);
				return true;
			}
			else
			{
				$errsize = sizeof($this->errors);
				if ($this->validfields["$fieldname"][vB_DataManager_Constants::VF_REQ] AND $errsize == sizeof($this->errors))
				{
					$this->error('required_field_x_missing_or_invalid', $fieldname);
				}
				return $verify;
			}
		}
		else if (isset($this->validfields["$fieldname"]))
		{
			$this->rawfields["$fieldname"] = true;
			$this->do_set($fieldname, $value, $table);
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Sets the supplied data to be part of the data to be saved
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data (reference) itself
	* @param	boolean	Clean data, or insert it RAW (used for non-arbitrary updates, like posts = posts + 1)
	* @param	boolean	Whether to verify the data with the appropriate function. Still cleans data if previous arg is true.
	*
	* @return	boolean	Returns false if the data is rejected for whatever reason
	*/
	function setr($fieldname, &$value, $clean = true, $doverify = true)
	{
		if ($clean)
		{
			$verify = $this->verify($fieldname, $value, $doverify);

			if ($verify === true)
			{
				$this->do_set($fieldname, $value);
				return true;
			}
			else
			{
				$errsize = sizeof($this->errors);
				if ($this->validfields["$fieldname"][vB_DataManager_Constants::VF_REQ] AND $errsize == sizeof($this->errors))
				{
					$this->error('required_field_x_missing_or_invalid', $fieldname);
				}
				return $verify;
			}
		}
		else if (isset($this->validfields["$fieldname"]))
		{
			$this->rawfields["$fieldname"] = true;
			$this->do_set($fieldname, $value);
			return true;
		}
		else
		{
			return false;
		}
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
		$table = $this->fetchTableBase($this->table);

		if ($bitvalue = $this->bitfields["$fieldname"]["$bitname"])
		{
			$this->{$table}["$fieldname"]["$bitvalue"] = ($onoff ? 1 : 0);
			$this->setfields["$fieldname"] = true;
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Rather like set(), but sets data into the $this->info array instead. Use setr_info if $value if a reference to value is to be passed
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	*/
	function set_info($fieldname, $value)
	{
		if (isset($this->validfields["$fieldname"]))
		{
			$this->verify($fieldname, $value);
		}
		$this->info["$fieldname"] = $value;
	}

	/**
	* Rather like set(), but sets reference to data into the $this->info array instead
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data (reference) itself
	*/
	function setr_info($fieldname, &$value)
	{
		if (isset($this->validfields["$fieldname"]))
		{
			$this->verify($fieldname, $value);
		}
		$this->info["$fieldname"] =& $value;
	}

	/**
	* Verifies that the supplied data is one of the fields used by this object
	*
	* Also ensures that the data is of the correct type,
	* and attempts to correct errors in the supplied data.
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	* @param	boolean	Whether to verify the data with the appropriate function. Data is still cleaned though.
	*
	* @return	boolean	Returns true if the data is one of the fields used by this object, and is the correct type (or has been successfully corrected to be so)
	*/
	function verify($fieldname, &$value, $doverify = true)
	{
		if (isset($this->validfields["$fieldname"]))
		{
			$field =& $this->validfields["$fieldname"];

			// clean the value according to its type
			$value = vB::get_cleaner()->clean($value, $field[vB_DataManager_Constants::VF_TYPE]);

			if ($doverify AND isset($field[vB_DataManager_Constants::VF_CODE]))
			{
				if ($field[vB_DataManager_Constants::VF_CODE] === vB_DataManager_Constants::VF_METHOD)
				{
					if (isset($field[vB_DataManager_Constants::VF_METHODNAME]))
					{
						return $this->{$field[vB_DataManager_Constants::VF_METHODNAME]}($value);
					}
					else
					{
						return $this->{'verify_' . $fieldname}($value);
					}
				}
				else
				{
					$lamdafunction = create_function('&$data, &$dm', $field[vB_DataManager_Constants::VF_CODE]);
					return $lamdafunction($value, $this);
				}
			}
			else
			{
				return true;
			}
		}
		else
		{
			trigger_error("Field <em>$fieldname</em> is not defined in <em>\$validfields</em> in class <strong>" . get_class($this) . "</strong>", E_USER_ERROR);
			return false;
		}
	}

	/**
	* Unsets a values that has already been set
	*
	* @param	string	The name of the field that is to be unset
	* @param	string	Table name to force. Leave as null to use the default table
	*/
	function do_unset($fieldname, $table = null)
	{
		if ($table === null)
		{
			$table = $this->table;
		}

		$table = $this->fetchTableBase($table);

		if (isset($this->{$table}["$fieldname"]))
		{
			unset($this->{$table}["$fieldname"], $this->setfields["$fieldname"]);
		}
	}

	/**
	* Takes valid data and sets it as part of the data to be saved
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed		The data itself
	* @param	string	Table name to force. Leave as null to use the default table
	*/
	function do_set($fieldname, &$value, $table = null)
	{
		if ($table === null)
		{
			$table = $this->table;
		}

		$table = $this->fetchTableBase($table);

		$this->setfields["$fieldname"] = true;
		$this->{$table}["$fieldname"] =& $value;

	}

	/**
	* Checks through the required fields for this object and ensures that all required fields have a value
	*
	* @return	boolean	Returns true if all required fields have a valid value set
	*/
	function check_required()
	{
		foreach ($this->validfields AS $fieldname => $validfield)
		{
			if ($validfield[vB_DataManager_Constants::VF_REQ] == vB_DataManager_Constants::REQ_YES AND !$this->setfields["$fieldname"])
			{
				$this->error('required_field_x_missing_or_invalid', $fieldname);
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns an array with object info required for query methods as first argument
	 * @param string $tableName
	 * @return array
	 */
	protected function &fetchQueryInfo($tableName)
	{
		$info = array();
		$info['table'] = $tableName;
		$info['tableData'] =& $this->$tableName;
		$info['bitfields'] =& $this->bitfields;
		$info['rawfields'] =& $this->rawfields;

		return $info;
	}


	/**
	* Creates and runs an UPDATE query to save the data from the object into the database
	*
	* @param	string	The system's table prefix
	* @param	string	The name of the database table to be affected (do not include TABLE_PREFIX in your argument)
	* @param	string	Specify the WHERE condition here. For example, 'userid > 10 AND posts < 50'
	* @param	boolean	Whether or not to actually run the query
	* @param	mixed	If this evaluates to true, the query will be delayed. If it is a string, that will be the name of the shutdown query.
	* @param boolean	Whether to return the number of affected rows
	*
	* @return	boolean	Returns true on success
	*/
	function db_update($tableprefix, $table, $condition = null, $doquery = true, $delayed = false, $affected_rows = false)
	{
		if ($this->has_errors())
		{
			return false;
		}

		else if (!empty($table))
		{
			$varname = $this->fetchTableBase($table);
			//nothing to update.
			if(!$this->{$varname})
			{
				return true;
			}

			if (empty($condition) AND !empty($this->condition))
			{
				$condition = $this->condition;
			}

			$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE);

			if (!empty($condition) AND is_array($condition))
			{
				$params[vB_dB_Query::CONDITIONS_KEY] = $condition;
			}
			else
			{
				if (!empty($this->existing))
				{
					$params[vB_dB_Query::CONDITIONS_KEY] = array();

					foreach ($this->existing as $field => $value)
					{
						$params[vB_dB_Query::CONDITIONS_KEY][] = array('field' => $field,
						'value' => $value, 'operator' => vB_dB_Query::OPERATOR_EQ);
					}
				}
			}

			foreach ($this->{$varname} as $field => $value)
			{

				if (array_key_exists($field, $this->bitfields) AND is_array($value))
				{
					$bitValue = $this->existing[$field];
					foreach ($value as $bitField => $on)
					{
						if ($on)
						{
							$bitValue |= $bitField;
						}
						else
						{
							$bitValue = $bitValue & (~$bitField);
						}
					}

					$value = $bitValue;
				}
				$params[$field] = $value;
			}

			if ($delayed)
			{
				if (is_string($delayed))
				{
					$this->assertor->shutdownQuery($table, $params, $delayed);
				}
				else
				{
					$this->assertor->shutdownQuery('datamanagerUpdate', $params);
				}
			}
			else
			{
				$affected = $this->assertor->assertQuery($table, $params);

				if ($affected_rows)
				{
					return $affected;
				}
			}

			return true;
		}
		else
		{
			return true;
		}
	}

	/**
	* Creates and runs an INSERT query to save the data from the object into the database
	*
	* @param	string	The system's table prefix
	* @param	string	The name of the database table to be affected (do not include TABLE_PREFIX in your argument)
	* @param	boolean	Whether or not to actually run the query
	* @param bool		Perform REPLACE INTO instead of INSERT
	*
	* @return	integer	Returns the ID of the inserted record
	*/
	function db_insert($tableprefix, $table, $doquery = true, $replace = false)
	{
		static $requiredfields = null;

		if ($requiredfields === null)
		{
			$requiredfields = $this->check_required();
		}

		$varname = $this->fetchTableBase($table);
		if ($this->has_errors())
		{
			return false;
		}
		else if (is_array($this->$varname) AND !empty($this->$varname) AND $requiredfields)
		{
			$params[vB_dB_Query::TYPE_KEY] = vB_dB_Query::QUERY_INSERT;
			foreach ($this->{$varname} as $field => $value)
			{
				if (array_key_exists($field, $this->bitfields) AND is_array($value))
				{
					$bitValue = 0;
					foreach ($value as $bitField => $on)
					{
						if ($on)
						{
							$bitValue |= $bitField;
						}
					}

					$value = $bitValue;
				}
				$params[$field] = $value;
			}

			$result = $this->assertor->assertQuery($table, $params);
			if (is_array($result))
			{
				if (empty($result['errors']))
				{
					return $result[0];
				}

				throw new Exception($result['errors']);
			}

			// Not all of our tables have AUTO_INCREMENT fields, i.e. customavatar
			return $result;
		}
		else
		{
			return 0;
		}
	}

	/**
	* Creates and runs an INSERT query to save the data from the object into the database
	*
	* @param	string	The system's table prefix
	* @param	string	The name of the database table to be affected (do not include TABLE_PREFIX in your argument)
	* @param	boolean	Whether or not to actually run the query
	*
	* @return	integer	Returns the affected rows
	*/
	function db_insert_ignore($tableprefix, $table, $doquery = true)
	{
		static $requiredfields = null;

		if ($requiredfields === null)
		{
			$requiredfields = $this->check_required();
		}

		$varname = $this->fetchTableBase($table);
		if ($this->has_errors())
		{
			return false;
		}
		else if (!empty($this->$varname) AND is_array($this->$varname) AND $requiredfields)
		{
			$params = $this->fetchQueryInfo($varname);
			$params[vB_dB_Query::TYPE_KEY] = vB_dB_Query::QUERY_METHOD;
			$params['replace'] = false;
			$params['ignore'] = true;

			return $this->assertor->assertQuery('datamanagerInsert', $params);
		}
		else
		{
			return 0;
		}
	}

	/**
	* Generates the SQL to delete a record from a database table, then executes it
	*
	* @param	string	The system's table prefix
	* @param	string	The name of the database table to be affected (do not include TABLE_PREFIX in your argument)
	* @param	array	  Specify the dbasserter condition for the DELETE here.
	* @param	boolean	Whether or not to actually run the query
	*
	* @return	integer	The number of records deleted
	*/
	function db_delete($tableprefix, $table, $condition = '', $doquery = true)
	{
		if (empty($condition))
		{
			if (empty($this->condition))
			{
				throw new vB_Exception_Api('invalid_data');
			}

			$condition = $this->condition;
		}

		if (!is_array($condition))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		$params = array(
			vB_dB_Query::CONDITIONS_KEY => $condition,
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
		);

		return $this->assertor->assertQuery($table, $params);
	}

	/**
	* Check if the DM currently has errors. Will kill execution if it does and $die is true.
	*
	* @param	bool	Whether or not to end execution if errors are found; ignored if the error type is ERRTYPE_SILENT
	*
	* @return	bool	True if there *are* errors, false otherwise
	*/
	public function has_errors($die = true)
	{
		if (!empty($this->errors))
		{
			if ($this->error_handler == vB_DataManager_Constants::ERRTYPE_SILENT OR $die == false)
			{
				return true;
			}
			else if ($this->error_handler == vB_DataManager_Constants::ERRTYPE_UPGRADE)
			{
				return true;
			}
			else
			{
				$error = '';

				$config = vB::getConfig();
				if (!empty($config['Misc']['debug']))
				{
					$trace = debug_backtrace();
					foreach ($trace as $level =>$record)
					{
						if (!empty($level))
						{
							echo "Level $level<br/>\n		Function " . $record['function'] . '..Line '.
							(empty($record['line']) ? ' ' : $record['line']) . "..<br/>\n" .
							(empty($record['file']) ? '' : "in		 ". $record['file'] . "<br/>\n")	  ;
						}
					}

					$error .= var_export($this->errors, true);
				}

				$error .= '</ul>Unable to proceed with save while $errors array is not empty in class <strong>' . get_class($this) . '</strong>';

				trigger_error($error, E_USER_ERROR);
				return true;
			}
		}
		else
		{
			return false;
		}
	}

	/**
	 *	Returns the array of errors
	 *
	 *	@return array --
	 */
	public function get_errors()
	{
		return $this->errors;
	}

	public function get_exception()
	{
		$e = new vB_Exception_Api();
		foreach($this->errors as $error)
		{
			//this is intended to be used with the "array unprocessed" error mode.
			if (is_array($error))
			{
				$phraseid = array_shift($error);
				$e->add_error($phraseid, $error);
			}
			//but if not, do what we can
			else
			{
				$e->add_error('unexpected_error', $error);
			}
		}
		return $e;
	}


	/**
	* Saves the data from the object into the specified database tables
	*
	* @param	boolean	Do the query?
	* @param	mixed	Whether to run the query now; see db_update() for more info
	* @param bool 	Whether to return the number of affected rows.
	* @param bool		Perform REPLACE INTO instead of INSERT
	* @param bool		Perfrom INSERT IGNORE instead of INSERT
	*
	* @return	mixed	If this was an INSERT query, the INSERT ID is returned
	*/
	function save($doquery = true, $delayed = false, $affected_rows = false, $replace = false, $ignore = false)
	{
		if ($this->has_errors())
		{
			return false;
		}

		if (!$this->pre_save($doquery))
		{
			return false;
		}

		$table = $this->fetchTableBase($this->table);

		//Originally we used mysql's "replace into" query. But that's not portable. Instead, if $replace is set
		// we'll look for a primary key. If it has one we'll check for a record with that value. If it has one
		// then we should set that to be the condition, which means we'll do an update.
		if ($replace AND !empty($this->keyField))
		{
			//single field key first.
			if (is_array($this->keyField))
			{
				$keyField = $this->keyField;
			}
			else
			{
				$keyField = array($this->keyField);
			}

			$gotKey = true;

			$conditions = array();
			foreach ($keyField AS $field)
			{
				if (!isset($this->{$table}[$field]))
				{
					$gotKey = false;
					break;
				}
				$conditions[] = array(
					'field' => $field,
					'value' => $this->{$table}[$field],
					'operator' => vB_dB_Query::OPERATOR_EQ,
				);
			}

			if ($gotKey)
			{
				$testVal = $this->assertor->getRow($this->table, array(vB_dB_Query::CONDITIONS_KEY => $conditions));

				//If we got a valid entry, we have an existing record
				if (!empty($testVal) AND empty($testVal['errors']))
				{
					$this->condition = $conditions;
				}
			}
		}

		if ($this->condition === null)
		{
			if ($ignore)
			{
				$return = $this->db_insert_ignore(TABLE_PREFIX, $this->table, $doquery);
			}
			else
			{
				$return = $this->db_insert(TABLE_PREFIX, $this->table, $doquery, $replace);
			}
			if ($return)
			{
				$autoid = '';
				foreach ($this->validfields AS $fieldid => $fieldinfo)
				{
					if ($fieldinfo[vB_DataManager_Constants::VF_REQ] == vB_DataManager_Constants::REQ_INCR)
					{
						$autoid = $fieldid;
						break;
					}
				}

				if ($autoid)
				{
					$this->{$table}["$autoid"] = $return;
				}
			}
		}
		else
		{
			$return = $this->db_update(TABLE_PREFIX, $this->table, $this->condition, $doquery, $delayed, $affected_rows);
		}

		// Node that $return may be integer 0 if the primary key of a table is not INT
		if ($return !== false)
		{
			$this->post_save_each($doquery, $return);
			$this->post_save_once($doquery, $return);
		}

		return $return;
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$return_value = true;
		/* Legacy Hook $this->hook_presave Removed */

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		$return_value = true;
		/* Legacy Hook $this->hook_postsave Removed */

		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed once after all records are updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_once($doquery = true)
	{
		$return_value = true;
		/* Legacy Hook $this->hook_postsave Removed */

		return $return_value;
	}

	/**
	* Deletes the specified data item from the database
	*
	* @return	integer	The number of rows deleted
	*/
	function delete($doquery = true)
	{
		if (empty($this->condition))
		{
			if ($this->error_handler == vB_DataManager_Constants::ERRTYPE_SILENT)
			{
				return false;
			}
			else if ($this->error_handler == vB_DataManager_Constants::ERRTYPE_UPGRADE)
			{
				return false;
			}
			else
			{
				trigger_error('Delete condition not specified!', E_USER_ERROR);
			}
		}
		else
		{
			if (!$this->pre_delete($doquery))
			{
				return false;
			}

			$return = $this->db_delete(TABLE_PREFIX, $this->table, $this->condition, $doquery);
			$this->post_delete($doquery);
			return $return;
		}
	}

	/**
	* Additional data to update before a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function pre_delete($doquery = true)
	{
		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$return_value = true;
		/* Legacy Hook $this->hook_delete Removed */

		return $return_value;
	}

	/**
	* Sets the error handler for the object
	*
	* @param	string	Error type
	*
	* @return	boolean
	*/
	function setErrorHandler($errtype = vB_DataManager_Constants::ERRTYPE_STANDARD)
	{
		switch ($errtype)
		{
			case vB_DataManager_Constants::ERRTYPE_ARRAY:
			case vB_DataManager_Constants::ERRTYPE_STANDARD:
			case vB_DataManager_Constants::ERRTYPE_CP:
			case vB_DataManager_Constants::ERRTYPE_SILENT:
			case vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED:
			case vB_DataManager_Constants::ERRTYPE_UPGRADE:
				$this->error_handler = $errtype;
				break;
			default:
				$this->error_handler = vB_DataManager_Constants::ERRTYPE_STANDARD;
				break;
		}
	}

	/**
	* Shows an error message and halts execution - use this in the same way as print_stop_message();
	*
	* @param	string	Phrase name for error message
	*/
	function error($errorphrase)
	{
		//if we are passed a array then assume that it is the phrase plus arguments
		if (is_array($errorphrase))
		{
			$args = $errorphrase;
		}
		//otherwise we should assume that the args are a "varargs" format.
		else
		{
			$args = func_get_args();
		}

		//if we aren't processing the error, just save the phrase id and args raw
		if ($this->error_handler == vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED)
		{
			$error = $args;
		}
		else if ($this->error_handler == vB_DataManager_Constants::ERRTYPE_UPGRADE)
		{
			$error = $args;
		}
		else
		{	//otherwise fetch the error message
			$error = fetch_error($args);
		}

		$this->errors[] = $error;

		if ($this->failure_callback AND is_callable($this->failure_callback))
		{
			call_user_func_array($this->failure_callback, array(&$this, $errorphrase));
		}

		switch ($this->error_handler)
		{
			case vB_DataManager_Constants::ERRTYPE_ARRAY:
			case vB_DataManager_Constants::ERRTYPE_SILENT:
			case vB_DataManager_Constants::ERRTYPE_ARRAY_UNPROCESSED:
			case vB_DataManager_Constants::ERRTYPE_UPGRADE:
			{
				// do nothing -- either we are ignoring errors or manually checking the error array at intervals.
			}
			break;

			case vB_DataManager_Constants::ERRTYPE_STANDARD:
			{
				throw new Exception($error);
			}
			break;

			case vB_DataManager_Constants::ERRTYPE_CP:
			{
				print_cp_message($error);
			}
			break;
		}
	}

	/**
	* Sets the function to call on an error.
	*
	* @param	callback	A valid callback (either a function name, or specially formed array)
	*/
	function set_failure_callback($callback)
	{
		$this->failure_callback = $callback;
	}

	// #############################################################################
	// additional functions for use in data verification

	/**
	* Verifies that the specified user exists
	*
	* @param	integer	User ID
	*
	* @return 	boolean	Returns true if user exists
	*/
	function verify_userid(&$userid)
	{
		if ($userid == $this->registry->userinfo['userid'])
		{
			$this->info['verifyuser'] =& $this->registry->userinfo;
		}
		else if ($userinfo = $this->assertor->getRow('user', array('userid' => $userid)))
		{
			$this->info['verifyuser'] =& $userinfo;
		}
		else
		{
			$this->error('no_users_matched_your_query');
			return false;
		}

		return true;
	}

	/**
	* Verifies that the provided username is valid, and attempts to correct it if it is not valid
	*
	* @param	string	Username
	*
	* @return	boolean	Returns true if the username is valid, or has been corrected to be valid
	*/
	function verify_username(&$username)
	{
		// this is duplicated from the user manager

		// fix extra whitespace and invisible ascii stuff
		$username = trim(preg_replace('#[ \r\n\t]+#si', ' ', strip_blank_ascii($username, ' ')));
		$username_raw = $username;

		$username = preg_replace(
			'/&#([0-9]+);/ie',
			"convert_unicode_char_to_charset('\\1', vB_String::getCharset())",
			$username
		);

		$username = preg_replace(
			'/&#0*([0-9]{1,2}|1[01][0-9]|12[0-7]);/ie',
			"convert_int_to_utf8('\\1')",
			$username
		);

		$username = str_replace(chr(0), '', $username);
		$username = trim($username);

		$length = vB_String::vbStrlen($username);
		if ($length < $this->registry->options['minuserlength'])
		{
			// name too short
			$this->error('usernametooshort', $this->registry->options['minuserlength']);
			return false;
		}
		else if ($length > $this->registry->options['maxuserlength'])
		{
			// name too long
			$this->error('usernametoolong', $this->registry->options['maxuserlength']);
			return false;
		}
		else if (preg_match('/(?<!&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5});/', $username))
		{
			// name contains semicolons
			$this->error('username_contains_semi_colons');
			return false;
		}
		else if ($username != fetch_censored_text($username))
		{
			// name contains censored words
			$this->error('censorfield');
			return false;
		}
		else
		{
			$result = $this->assertor->assertQuery('verifyUsername',
					array(
						'userid'		=> intval($this->existing['userid']),
						'username'		=> vB_String::htmlSpecialCharsUni($username),
						'username_raw'	=> vB_String::htmlSpecialCharsUni($username_raw)
					));

			if ($result->valid() AND $result->current())
			{
				// name is already in use
				$this->error('usernametaken', vB_String::htmlSpecialCharsUni($username), vB::getCurrentSession()->get('sessionurl'));
				return false;
			}
			else if (!empty($this->registry->options['illegalusernames']))
			{
				// check for illegal username
				$usernames = preg_split('/[ \r\n\t]+/', $this->registry->options['illegalusernames'], -1, PREG_SPLIT_NO_EMPTY);
				foreach ($usernames AS $val)
				{
					if (strpos(strtolower($username), strtolower($val)) !== false)
					{
						// wierd error to show, but hey...
						$this->error('usernametaken', vB_String::htmlSpecialCharsUni($username), vB::getCurrentSession()->get('sessionurl'));
						return false;
					}
				}
			}
		}

		// if we got here, everything is okay
		$username = vB_String::htmlSpecialCharsUni($username);

		return true;
	}

	/**
	* Verifies that an integer is greater than zero
	*
	* @param	integer	Value to check
	*
	* @return	boolean
	*/
	function verify_nonzero(&$int)
	{
		return ($int > 0 ? true : false);
	}

	/**
	* Verifies that an integer is greater than zero or the special value -1
	* this rule matches a fair number of id columns
	*
	* @param	integer	Value to check
	*
	* @return	boolean
	*/
	function verify_nonzero_or_negone(&$int)
	{
		return ( ($int > 0) or $int == -1);
	}

	/**
	* Verifies that a string is not empty
	*
	* @param	string	Text to check
	*
	* @return	boolean
	*/
	function verify_nonempty(&$string)
	{
		$string = strval($string);

		return ($string !== '');
	}

	/**
	* Verifies that a variable is a comma-separated list of integers
	*
	* @param	mixed	List (can be string or array)
	*
	* @return	boolean
	*/
	function verify_commalist(&$list)
	{
		return $this->verify_list($list, ',', true);
	}

	/**
	* Verifies that a variable is a space-separated list of integers
	*
	* @param	mixed	List (can be string or array)
	*
	* @return	boolean
	*/
	function verify_spacelist(&$list)
	{
		return $this->verify_list($list, ' ', true);
	}

	/**
	* Creates a valid string of comma-separated integers
	*
	* @param	mixed	Either specify a string of integers separated by parameter 2, or an array of integers
	* @param	string	The 'glue' for the string. Usually a comma or a space.
	* @param	boolean	Whether or not to exclude zero from the list
	*
	* @return	boolean
	*/
	function verify_list(&$list, $glue = ',', $dropzero = false)
	{
		if ($list !== '')
		{
			// turn strings into arrays
			if (!is_array($list))
			{
				if (preg_match_all('#(-?\d+)#s', $list, $matches))
				{
					$list = $matches[1];
				}
				else
				{
					$list = '';
					return true;
				}
			}


			// clean array values and remove duplicates, then sort into order
			$cleaner = vB::getCleaner();
			$list = array_unique($cleaner->clean($list, vB_Cleaner::TYPE_ARRAY_INT));
			sort($list);

			// remove zero values
			if ($dropzero)
			{
				$key = array_search(0, $list);
				if ($key !== false)
				{
					unset($list["$key"]);
				}
			}

			// implode back into a string
			$list = implode($glue, $list);
		}

		return true;
	}

	/**
	* Verifies that input is a serialized array (or force an array to serialize)
	*
	* @param	mixed	Either specify a serialized array, or an array to serialize, or an empty string
	*
	* @return	boolean
	*/
	function verify_serialized(&$data)
	{
		if ($data === '')
		{
			$data = serialize(array());
			return true;
		}
		else
		{
			if (!is_array($data))
			{
				$data = unserialize($data);
				if ($data === false)
				{
					return false;
				}
			}

			$data = serialize($data);
		}

		return true;
	}

	/**
	* Verifies an IP address - currently only works with IPv4
	*
	* @param	string	IP address
	*
	* @return 	boolean
	*/
	function verify_ipaddress(&$ipaddress)
	{
		if ($ipaddress == '')
		{
			return true;
		}
		else if (preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$#', $ipaddress, $octets))
		{
			for ($i = 1; $i <= 4; $i++)
			{
				if ($octets["$i"] > 255)
				{
					return false;
				}
			}
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Verifies that a string is an MD5 string
	*
	* @param	string	The MD5 string
	*
	* @return	boolean
	*/
	function verify_md5(&$md5)
	{
		return (preg_match('#^[a-f0-9]{32}$#', $md5) ? true : false);
	}

	/**
	* Verifies that an email address is valid
	*
	* @param	string	Email address
	*
	* @return	boolean
	*/
	function verify_email(&$email)
	{
		return is_valid_email($email);
	}

	/**
	* Verifies that a hyperlink is valid
	*
	* @param	string	Hyperlink URL
	* @param	boolean	Strict link (only HTTP/HTTPS); default false
	*
	* @return	boolean
	*/
	function verify_link(&$link, $strict = false)
	{
		if (preg_match('#^www\.#si', $link))
		{
			$link = 'http://' . $link;
			return true;
		}
		else if (!preg_match('#^[a-z0-9]+://#si', $link))
		{
			// link doesn't match the http://-style format in the beginning -- possible attempted exploit
			return false;
		}
		else if ($strict && !preg_match('#^(http|https)://#si', $link))
		{
			// link that doesn't start with http:// or https:// should not be allowed in certain places (IE: profile homepage)
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Verifies a date array as a valid unix timestamp
	*
	* @param	array	Date array containing day/month/year and optionally: hour/minute/second
	*
	* @return	boolean
	*/
	function verify_date_array(&$date)
	{
		$date['year']	= intval($date['year']);
		$date['month']  = intval($date['month']);
		$date['day']	 = intval($date['day']);
		$date['hour']	= intval($date['hour']);
		$date['minute'] = intval($date['minute']);
		$date['second'] = intval($date['second']);

		if ($date['year'] < 1970)
		{
			return false;
		}
		else if (checkdate($date['month'], $date['day'], $date['year']))
		{
			$date = vbmktime($date['hour'],  $date['minute'], $date['second'], $date['month'], $date['day'], $date['year']);

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Basic options to perform on all pagetext type fields
	*
	* @param	string	Page text
	*
	* @param	bool	Whether the text is valid
	* @param	bool	Whether to run the case stripper
	*/
	function verify_pagetext(&$pagetext, $noshouting = true)
	{
		require_once(DIR . '/includes/functions_newpost.php');

		$pagetext = preg_replace('/&#(0*32|x0*20);/', ' ', $pagetext);
		$pagetext = trim($pagetext);

		// remove empty bbcodes
		//$pagetext = $this->strip_empty_bbcode($pagetext);

		// add # to color tags using hex if it's not there
		$pagetext = preg_replace('#\[color=(&quot;|"|\'|)([a-f0-9]{6})\\1]#i', '[color=\1#\2\1]', $pagetext);

		// strip alignment codes that are closed and then immediately reopened
		$pagetext = preg_replace('#\[/(left|center|right)\]([\r\n]*)\[\\1\]#i', '\\2', $pagetext);
		// remove [/list=x remnants
		if (stristr($pagetext, '[/list=') != false)
		{
			$pagetext = preg_replace('#\[/list=[a-z0-9]+\]#siU', '[/list]', $pagetext);
		}

		// remove extra whitespace between [list] and first element
		// -- unnecessary now, bbcode parser handles leading spaces after a list tag
		//$pagetext = preg_replace('#(\[list(=(&quot;|"|\'|)([^\]]*)\\3)?\])\s+#i', "\\1\n", $pagetext);

		// censor main message text
		$pagetext = fetch_censored_text($pagetext);

		// parse URLs in message text
		if ($this->info['parseurl'])
		{
			$pagetext = convert_url_to_bbcode($pagetext);
		}

		// remove sessionhash from urls:
		require_once(DIR . '/includes/functions_login.php');
		$pagetext = fetch_removed_sessionhash($pagetext);

		if ($noshouting)
		{
			$pagetext = fetch_no_shouting_text($pagetext);
		}

		require_once(DIR . '/includes/functions_video.php');
		$pagetext = parse_video_bbcode($pagetext);

		return true;
	}

	/**
	* Strips empty BB code from the entire message except inside PHP/HTML/Noparse tags.
	*
	* @param	string	Text to strip tags from
	*
	* @return	string	Text with tags stripped
	*/
	function strip_empty_bbcode($text)
	{
		return preg_replace_callback(
			'#(^|\[/(php|html|noparse)\])(.+)(?=\[(php|html|noparse)\]|$)#sU',
			array(&$this, 'strip_empty_bbcode_callback'),
			$text
		);
	}

	/**
	* Callback function for strip_empty_bbcode.
	*
	* @param	array	Array of matches. 1 is the close of the previous tag (if there is one). 3 is the text to strip from.
	*
	* @return	string	Compiled text with empty tags stripped where appropriate
	*/
	function strip_empty_bbcode_callback($matches)
	{
		$stripped = preg_replace('#(\[([^=\]]+)(=[^\]]+)?]\s*\[/\\2])#siU', '', $matches[3]);
		return $matches[1] . $stripped;
	}

	/**
	* Verifies the number of images in the post text. Call it from pre_save() after pagetext/allowsmilie has been set
	*
	* @return	bool	Whether the post passes the image count check
	*/
	function verify_image_count($pagetext = 'pagetext', $allowsmilie = 'allowsmilie', $parsetype = 'nonforum', $table = null)
	{
		global $vbulletin;

		$_allowsmilie =& $this->fetch_field($allowsmilie, $table);
		$_pagetext =& $this->fetch_field($pagetext, $table);

		if ($_allowsmilie !== null AND $_pagetext !== null)
		{
			// check max images
			require_once(DIR . '/includes/functions_misc.php');
			require_once(DIR . '/includes/class_bbcode_alt.php');
			$bbcode_parser = new vB_BbCodeParser_ImgCheck($this->registry, fetch_tag_list());
			$bbcode_parser->set_parse_userinfo($vbulletin->userinfo);

			if ($this->registry->options['maximages'] AND !$this->info['is_automated'])
			{
				$imagecount = fetch_character_count($bbcode_parser->parse($_pagetext, $parsetype, $_allowsmilie, true), '<img');
				if ($imagecount > $this->registry->options['maximages'])
				{
					$this->error('toomanyimages', $imagecount, $this->registry->options['maximages']);
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Fetches the base table name even if the parameter contains the package prefix.
	 *
	 * @param	string		Name of the table we are checking existing prefix.
	 *
	 * @return	string		The proper table name.
	 */
	protected function fetchTableBase($tablename)
	{
		// split the table name if it contains the package prefix
		if (strpos($tablename, ':'))
		{
			list($prefix, $tablename) = explode(':', $tablename);
		}

		return $tablename;
	}

	/**
	* Check if a file exists, and then delete it if it does.
	*
	* @param	string filename
	*/
	function deleteFile($file)
	{
		if ($file AND file_exists($file))
		{
			@unlink($file);
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 38992 $
|| ####################################################################
\*======================================================================*/
