<?php if (!defined('VB_ENTRY')) die('Access denied.');
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
 * @package vBDatabase
 */

/**
 * The base query class for the assertor query object.
 *
 * For the moment most of the query logic is here rather than the type specific child classes.
 * The main reason for this is that this is generally applicable to SQL backends.  We'll
 * need to refactor this eventually, but that's probably better done when we have a better
 * sense of the requirements of the additional backends.
 *
 * This class is internal to the assertor interface and should not be called directly from
 * application code.
 *
 * @package vBDatabase
 */

Abstract class vB_dB_Query
{
	/** This class is called by the new vB_dB_Assertor database class
	 * It does the actual execution. See the vB_dB_Assertor class for more information

	 * $queryid can be either the id of a query from the dbqueries table, or the
	 * name of a table.
	 *
	 * if it is the name of a table , $params MUST include self::TYPE_KEY of either update, insert, select, or delete.
	 *
	 * $params includes a list of parameters. Here's how it gets interpreted.
	 *
	 * If the queryid was the name of a table and type was "update", one of the params
	 * must be the primary key of the table. All the other parameters will be matched against
	 * the table field names, and appropriate fields will be updated. The return value will
	 * be false if an error is generated and true otherwise
	 *
	 * If the queryid was the name of a table and type was "delete", one of the params
	 * must be the primary key of the table. All the other parameters will be ignored
	 * The return value will be false if an error is generated and true otherwise
	 *
	 * If the queryid was the name of a table and type was "insert", all the parameters will be
	 * matched against the table field names, and appropriate fields will be set in the insert.
	 * The return value is the primary key of the inserted record.
	 *
	 * If the queryid was the name of a table and type was "select", all the parameters will be
	 * matched against the table field names, and appropriate fields will be part of the
	 * "where" clause of the select. The return value will be a vB_dB_Result object
	 * The return value is the primary key of the inserted record.
	 *
	 * If the queryid is the key of a record in the dbqueries table then each params
	 * value will be matched to the query. If there are missing parameters we will return false.
	 * If the query generates an error we return false, and otherwise we return either true,
	 * or an inserted id, or a recordset.
	 *
	 **/

	const CONDITIONS_KEY = '#filters';
	const FIELDS_KEY = '#fields';
	const VALUES_KEY = '#values';
	const TYPE_KEY =  '#type';
	const COLUMNS_KEY = '#columns';
	const QUERYTYPE_KEY =  '#querytype';
	const OPERATOR_KEY = 'operator';
	const PRIORITY_QUERY = '#priority';
	const DEBUG_QUERY = '#debug';
	const CONDITION_ALL = '#all';

	const OPERATOR_LT = 'LT';
	const OPERATOR_LTE = 'LTE';
	const OPERATOR_GT = 'GT';
	const OPERATOR_GTE = 'GTE';
	const OPERATOR_EQ = 'EQ';
	const OPERATOR_NE = 'NE';
	const OPERATOR_BEGINS = 'BEGINS';
	const OPERATOR_ENDS = 'ENDS';
	const OPERATOR_INCLUDES = 'INCLUDES';
	const OPERATOR_ISNULL = 'IS NULL';
	const OPERATOR_ISNOTNULL = 'IS NOT NULL';
	const OPERATOR_AND = '#AND';
	const OPERATOR_NAND = '#NAND';
	const OPERATOR_OR = '#OR';
	const OPERATOR_FALSE = 'FALSE';
	const VALUE_ISNULL = '#NULL';

	const QUERY_SELECT = 's';
	const QUERY_COUNT = 'co';
	const QUERY_STORED = 'st';
	const QUERY_UPDATE ='u';
	const QUERY_INSERT = 'i';
	const QUERY_INSERTIGNORE = 'ig';
	const QUERY_MULTIPLEINSERT = 'mi';
	const QUERY_DELETE = 'd';
	const QUERY_METHOD = 'm';
	const QUERY_DROP = 'dr';
	const QUERY_CREATE = 'cr';
	const QUERY_ALTER = 'a';

	const PARAM_LIMIT = '#limit';
	const PARAM_LIMITSTART = '#limit_start';
	const PARAM_LIMITPAGE = '#limit_page';

	const BITFIELDS_KEY = '#bitfields';
	const SORT_ASC	= 'ASC';
	const SORT_DESC	= 'DESC';
	// these are for internal use
	const QUERY_TABLE = 't';

	/*Properties====================================================================*/

	/** The database connection **/
	protected $db = false;
	/** And the slave connection **/
	protected $dbSlave = false;

	/** The user info ***/
	protected $userinfo = false;

	/** The character used for quoting in an sql string- usually '.
	 ***/
	protected $quotechar = "'";

	/** We need to know whether we are using a table query when we compose the sql
	* replacements
	***/
	protected $table_query = false;

	protected $query_type = false;

	/** are we ready to execute? **/
	protected $data_loaded = false;

	/** are we ready to execute? **/
	protected $datafields = false;

	/** What is the primary key of the table, if applicable? */
	protected $primarykey = false;

	/** What is the text of the stored query from the dictionary, if applicable? */
	protected $query_string = false;

	/** The parameters are are going to use to populate the query data */
	protected $params = false;

	/** The array from a describe statement for database structure, if applicable? */
	protected $structure = false;

	/** The replacement variables from a stored query**/
	protected $replacements = false;

	/** The original query id **/
	protected $queryid = false;

	/** The most recent error **/
	protected $error = false;

	/** All errors for this query **/
	protected $errors = array();

	/** sortorder, for select queries only (obviously) **/
	protected $sortorder = false;


	/** This is the definition for tables we will process through.  It saves a
	* database query to put them here.
	* **/
	protected $table_data = array();

	protected $query_data = array();

	/**if applicable, the name of querydef method we are going to call and the object it's in **/
	protected $method_name = false;
	protected $querydef_object = false;

	/**the name of the class in the packages that contains the DB code **/
	protected $class_name = false;

	//fields which must be loaded as text even if the value looks like a number. This is for MySQL enum fields with numeric values.
	// and possibly other.

	protected $forcetext = array();

	//The querydefs class
	protected $querydefs = false;

	/**
	 * Causes the SQL for the next query that is exectuted to be displayed
	 * for debugging purposes. This only works if debug mode is turned on
	 *
	 * @var	bool
	 */
	protected $debugDisplayNextQuerySql = false;

	protected static $configDebug = false;

	/**
	 *	Holds the query builder object.  We don't need a copy for every query so we
	 *	store them here.
	 */
	protected static $queryBuilders = array();

	/*Initialisation================================================================*/

	public static function getQuery($queryid, $params, &$db, $userinfo, $dbtype, $dbSlave)
	{
		//init querybuilder if needed
		if (!isset(self::$queryBuilders[$dbtype]))
		{
			$config = vB::getConfig();
			$queryClass = 'vB_Db_' . $dbtype . '_QueryBuilder';
			self::$queryBuilders[$dbtype] = new $queryClass($db, !empty($config['Misc']['debug_sql']) OR !empty($params[self::DEBUG_QUERY]));
			self::$configDebug = !empty($config['Misc']['debug_sql']);
		}
		else
		{
			self::$queryBuilders[$dbtype]->setDebugSQL(self::$configDebug OR !empty($params[self::DEBUG_QUERY]));
		}

		//We need the query type for what happens next. For that we need the querydefs.
		if (strpos($queryid, ':'))
		{
			$values = explode(':', $queryid);
			if (count($values) > 1)
			{
				$class_prefix = $values[0];
				$queryid = $values[1];
			}
		}

		if (isset($class_prefix))
		{
			$classname = $class_prefix . '_dB_' . $dbtype . "_QueryDefs";
			$filename = DIR . '/packages/' . strtolower(str_replace('_', '/', $classname)) . '.php';

			if (file_exists($filename))
			{
				include_once($filename);
			}
			//make sure this is valid
			if (class_exists($classname, false))
			{
				$class = $classname;
			}
		}
		else
		{
			$class = 'vB_Db_' . $dbtype . "_QueryDefs";
		}
		$querydefs = new $class();
		$tableData = $querydefs->getTableData();
		$queryData = $querydefs->getQueryData();
		//First we need to find out what kind of query we have. If it's a table-based query
		// then we have a "type" in the params array.
		if (! isset($params[self::TYPE_KEY]))
		{
			//We can still recover is this is a method or stored query.
			if (!empty($queryData[$queryid]))
			{
				$params[self::TYPE_KEY] = self::QUERY_STORED;
			}
			else if (method_exists($querydefs, $queryid))
			{
				$params[self::TYPE_KEY] = self::QUERY_METHOD;
			}
			else //Last try. If this is wrong we'll know in a moment.
			{
				$params[self::TYPE_KEY] = self::QUERY_SELECT;
			}
		}

		if (empty($tableData[$queryid]) AND in_array($params[self::TYPE_KEY],
			array(self::QUERY_SELECT, self::QUERY_COUNT, self::QUERY_UPDATE, self::QUERY_INSERT,
			self::QUERY_INSERTIGNORE, self::QUERY_MULTIPLEINSERT, self::QUERY_DELETE))
			)
		{
			throw new vB_Exception_Api('invalid_query_definition_x', $queryid);
		}

		switch($params[self::TYPE_KEY])
		{
			case self::QUERY_STORED:
				$queryClass = 'vB_Db_Query_Stored_' . $dbtype ;
				break;
			case self::QUERY_SELECT:
				$queryClass = 'vB_Db_Query_Select_' . $dbtype ;
				break;
			case self::QUERY_COUNT:
				$queryClass = 'vB_Db_Query_Count_' . $dbtype ;
				break;
			case self::QUERY_UPDATE:
				$queryClass = 'vB_Db_Query_Update_' . $dbtype ;
				break;
			case self::QUERY_INSERT:
				$queryClass = 'vB_Db_Query_Insert_' . $dbtype ;
				break;
			case self::QUERY_INSERTIGNORE:
				$queryClass = 'vB_Db_Query_InsertIgnore_' . $dbtype ;
				break;
			case self::QUERY_DELETE:
				$queryClass = 'vB_Db_Query_Delete_' . $dbtype ;
				break;
			case self::QUERY_MULTIPLEINSERT:
				$queryClass = 'vB_Db_Query_MultipleInsert_' . $dbtype ;
				break;
			case self::QUERY_METHOD:
				$queryClass = 'vB_Db_Query_Method_' . $dbtype ;
				break;
			case self::QUERY_CREATE:
				$queryClass = 'vB_Db_Query_Create_' . $dbtype ;
				break;
			case self::QUERY_ALTER:
				$queryClass = 'vB_Db_Query_Alter_' . $dbtype ;
				break;
			case self::QUERY_DROP:
				$queryClass = 'vB_Db_Query_Drop_' . $dbtype ;
				break;
			default:
				throw new Exception('invalid_query_definition');
		} // switch


		$query = new $queryClass($queryid, $db, $userinfo, $dbSlave);
		$query->setQueryDefs($querydefs, $queryid);
		$query->setTableData($tableData, $queryid);
		$query->setQueryData($queryData);

		//If we had to build the structure, let's store it and not make another query later.
		if (!empty($structure))
		{
			$query->setStructure($structure);
		}
		return $query;
	}

	/** validates that we know what to do with this queryid
	*
	*	@param 	string	id of the query
	*  @param 	mixed		the shared db object
	* 	@param	array		the user information
	*
	***/
	 protected function __construct($queryid, &$db, $userinfo, $dbSlave)
	{
		$this->db = $db;
	 	$this->dbSlave = $dbSlave;
	 	$this->userinfo = $userinfo;
		$this->queryid = $queryid;
	 }

	/** This loads and validates the data- ensures we have all we need
	*
	*	@param	array		the data for the query
	***/
	abstract public function setQuery($params, $sortorder);

	/** This loads and validates the data for a table. There's some extra work required
	 *
	 *	@param	array		the data for the query
	 ***/
	public function setTableQuery($params, $sortorder)
	{
		//Let's first check that we have a valid type, and if necessary we
		// have a valid key.
		if (isset($this->table_data[$this->queryid]))
		{
			$this->primarykey = isset($this->table_data[$this->queryid]['key']) ? $this->table_data[$this->queryid]['key'] : array();

			if (empty($this->structure['forcetext']))
			{
				$this->forcetext = array();
			}
			else
			{
				$this->forcetext = $this->structure['forcetext'];
			}
		}
		else if (empty($this->structure))
		{

			//try to pull from the database
			$reporterror = $this->db->reporterror;
			$this->db->hide_errors();
			$structure = $this->db->query_read("describe " . TABLE_PREFIX . $this->queryid);
			$this->db->show_errors();

			if (!$structure)
			{
				throw new Exception('invalid_query_definition');
			}

			$this->structure = array();
			while($record = $this->db->fetch_array($structure))
			{
				$this->structure[] = $record['Field'];

				if (isset($record['key']) AND $record['key'] == 'PRI')
				{
					$this->primarykey[] = $record['field'];
				}
			}

			if (count($this->primarykey) > 1)
			{
				$this->primarykey =  array();
			}
			$this->table_data[$this->queryid] = $this->structure;
		}

		if (empty($this->structure))
		{
			throw new Exception('invalid_data');
		}

		$this->sortorder = $sortorder;

		$this->data_loaded = true;
		$this->params = $params;
		return true;
	}

	/** This function is the public interface to actually execute the SQL.
	*
	*	@return 	mixed
	**/
	abstract public function execSQL();

	/** This function generates the query text against a table.
	 *
	 *	@param	char
	 *	@param	array
	 *
	 *	@return 	mixed
	 **/
	protected function buildQuery($values)
	{
		$queryBuilder = self::$queryBuilders[$this->db_type];
		//Let's first find primary key values if there are any.
		//The "WHERE" clause logic is similar for update, delete, and select
		//insert of course doesn't have a WHERE clause
		if (
				($this->query_type == self::QUERY_UPDATE)
			OR	($this->query_type == self::QUERY_DELETE)
			OR	($this->query_type == self::QUERY_SELECT)
			OR	($this->query_type == self::QUERY_COUNT)
		)
		{
			if (isset($values[self::CONDITIONS_KEY]))
			{
				if (is_string($values[self::CONDITIONS_KEY]) AND $values[self::CONDITIONS_KEY] == self::CONDITION_ALL)
				{
					$where = false;
				}
				else
				{
					$where = $queryBuilder->conditionsToFilter($values[self::CONDITIONS_KEY], $this->forcetext);
				}
			}
			else if (!empty($this->primarykey)
				AND (
					(!is_array($this->primarykey) AND isset($values[$this->primarykey]))
					OR
					// Make sure that all primary keys has $values set
					(is_array($this->primarykey) AND array_intersect($this->primarykey, array_keys($values)) == $this->primarykey)
				)
			)
			{
				$where = $queryBuilder->primaryKeyToFilter($this->primarykey, $values, $this->forcetext);
			}
			else if (($this->query_type == self::QUERY_SELECT) OR ($this->query_type == self::QUERY_DELETE) OR ($this->query_type == self::QUERY_COUNT))
			{
				$where = $queryBuilder->valueArrayToFilter($values, $this->forcetext);
				if (empty($where) AND ($this->query_type == self::QUERY_DELETE))
				{
					throw new Exception('invalid_query_limit_parameters');
				}
			}
		}
		$cond = array();
		if (!empty($values[self::CONDITIONS_KEY]) AND is_string($values[self::CONDITIONS_KEY]) AND $values[self::CONDITIONS_KEY] == self::CONDITION_ALL)
		{
			$cond = self::CONDITION_ALL;
		}
		switch($this->query_type)
		{
			case self::QUERY_UPDATE:
				$setline = $queryBuilder->valueArrayToSetLine($values, $this->forcetext);
				return $queryBuilder->makeUpdateQuery($this->queryid, $where, $setline, $cond);
				break;

			case self::QUERY_INSERT:
				return $queryBuilder->makeInsertQuery($this->queryid, false, $values, $this->forcetext);
				break;

			case self::QUERY_INSERTIGNORE:
				return $queryBuilder->makeInsertQuery($this->queryid, true, $values, $this->forcetext);
				break;

			case self::QUERY_MULTIPLEINSERT:
				return $queryBuilder->makeInsertMultipleQuery($this->queryid, $values, $this->forcetext);
				break;

			case self::QUERY_DELETE:
				return $queryBuilder->makeDeleteQuery($this->queryid, $where, $cond);
				break;

			case self::QUERY_SELECT:
				if (empty($this->structure['structure']))
				{
					$structure = $this->structure;
				}
				else
				{
					$structure = $this->structure['structure'];
				}
				$params = array();
				foreach(array(self::COLUMNS_KEY, self::PARAM_LIMIT, self::PARAM_LIMITSTART) AS $key)
				{
					if (!empty($values[$key]))
					{
						$params[$key] = $values[$key];
					}
				}
				return $queryBuilder->makeSelectQuery($this->queryid, $where, $this->sortorder, $structure, $params);
				break;

			case self::QUERY_COUNT:
			if (empty($this->structure['structure']))
			{
				$structure = $this->structure;
			}
			else
			{
				$structure = $this->structure['structure'];
			}
			return $queryBuilder->makeCountQuery($this->queryid, $where, $structure);
			break;

			default:
				return false;
				break;
		} // switch
	}

	/**
	 * Causes the SQL for the next query that is exectuted to be displayed
	 * for debugging purposes. This only works if debug mode is turned on
	 * @deprecated never worked quite right and now does nothing.
	 */
	public function debugDisplayNextQuerySql()
	{
		$this->debugDisplayNextQuerySql = true;
	}

	/** Sets the query Query definitions
	*
	*	@param	mixed	The query data from the querydefs
	***/
	protected function setQueryData(&$queryData)
	{
		$this->query_data = $queryData;
}

	/** Sets the query Table definitions
	 *
	 *	@param	mixed	The table data from the querydefs
	 * 	@param	string
	 ***/
	protected function setTableData(&$tableData, $queryid)
	{
		$this->table_data = $tableData;
		if (isset($this->table_data[$queryid]))
		{
			$this->structure = $this->table_data[$this->queryid];
		}

	}

	/** Sets the querydef object
	 *
	 *	@param	object	The querydef object
	 ***/
	protected function setQueryDefs(&$querydefs)
	{
		$this->querydefs = $querydefs;
	}


	/** Sets the table structure.
	 *
	 *	@param	object	The querydef object
	 ***/
	protected function setStructure($structure)
	{
		$this->structure = $structure;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN=> $Revision=> 28823 $
|| ####################################################################
\*======================================================================*/
