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
* Class to interface with a database
*
* This class also handles data replication between a master and slave(s) servers
*
* @package	vBulletin
* @version	$Revision: 43748 $
* @date		$Date: 2011-05-23 16:49:33 -0300 (Mon, 23 May 2011) $
*/
class vB_Database
{
	/**
	 * The type of result set to return from the database for a specific row.
	 */
	const DBARRAY_BOTH	= 0;
	const DBARRAY_ASSOC = 1;
	const DBARRAY_NUM	= 2;

	/**
	* Array of function names, mapping a simple name to the RDBMS specific function name
	*
	* @var	array
	*/
	var $functions = array(
		'connect'            => 'mysql_connect',
		'pconnect'           => 'mysql_pconnect',
		'select_db'          => 'mysql_select_db',
		'query'              => 'mysql_query',
		'query_unbuffered'   => 'mysql_unbuffered_query',
		'fetch_row'          => 'mysql_fetch_row',
		'fetch_array'        => 'mysql_fetch_array',
		'fetch_field'        => 'mysql_fetch_field',
		'free_result'        => 'mysql_free_result',
		'data_seek'          => 'mysql_data_seek',
		'error'              => 'mysql_error',
		'errno'              => 'mysql_errno',
		'affected_rows'      => 'mysql_affected_rows',
		'num_rows'           => 'mysql_num_rows',
		'num_fields'         => 'mysql_num_fields',
		'field_name'         => 'mysql_field_name',
		'insert_id'          => 'mysql_insert_id',
		'escape_string'      => 'mysql_escape_string',
		'real_escape_string' => 'mysql_real_escape_string',
		'close'              => 'mysql_close',
		'client_encoding'    => 'mysql_client_encoding',
		'ping'               => 'mysql_ping',
	);

	/**
	* Array of constants for use in fetch_array
	*
	* @var	array
	*/
	var $fetchtypes = array(
		self::DBARRAY_NUM   => MYSQL_NUM,
		self::DBARRAY_ASSOC => MYSQL_ASSOC,
		self::DBARRAY_BOTH  => MYSQL_BOTH
	);

	/**
	* Full name of the system
	*
	* @var	string
	*/
	var $appname = 'vBulletin';

	/**
	* Short name of the system
	*
	* @var	string
	*/
	var $appshortname = 'vBulletin';

	/**
	* Database name
	*
	* @var	string
	*/
	var $database = null;

	/**
	* Link variable. The connection to the master/write server.
	*
	* @var	string
	*/
	var $connection_master = null;

	/**
	* Link variable. The connection to the slave/read server(s).
	*
	* @var	string
	*/
	var $connection_slave = null;

	/**
	* Link variable. The connection last used.
	*
	* @var	string
	*/
	var $connection_recent = null;

	var $multiserver = false;

	/**
	* Array of queries to be executed when the script shuts down
	*
	* @var	array
	*/
	var $shutdownqueries = array();

	/**
	* The contents of the most recent SQL query string.
	*
	* @var	string
	*/
	var $sql = '';

	/**
	* Whether or not to show and halt on database errors
	*
	* @var	boolean
	*/
	var $reporterror = true;

	/**
	* The text of the most recent database error message
	*
	* @var	string
	*/
	var $error = '';

	/**
	* The error number of the most recent database error message
	*
	* @var	integer
	*/
	var $errno = '';

	/**
	* SQL Query String
	*
	* @var	integer	The maximum size of query string permitted by the master server
	*/
	var $maxpacket = 0;

	/**
	* Track lock status of tables. True if a table lock has been issued
	*
	* @var	bool
	*/
	var $locked = false;

	/**
	* Number of queries executed
	*
	* @var	integer	The number of SQL queries run by the system
	*/
	var $querycount = 0;

	/**
	* Whether or not to log the queries to generate query "explain" output
	*
	* @var	bool
	*/
	public $doExplain = false;

	/**
	* Array of information on each query, used for the "explain" output
	*
	* @var	array
	*/
	protected $explain = array();

	/**
	* Time information for queries, used by the "explain" output
	*
	* @var	array
	*/
	protected $phpTime = 0;
	protected $sqlTime = 0;
	protected $startTime = 0;

	/**
	* Constructor.
	*
	*/
	function vB_Database()
	{
		$config = vB::getConfig();
		$this->doExplain = (!empty($config['Misc']['debug']) && $config['Misc']['debug'] && isset($_GET['explain']) && $_GET['explain'] == 1);
	}

	/**
	* Connects to the specified database server(s)
	*
	* @param	string	Name of the database that we will be using for select_db()
	* @param	string	Name of the master (write) server - should be either 'localhost' or an IP address
	* @param	integer	Port for the master server
	* @param	string	Username to connect to the master server
	* @param	string	Password associated with the username for the master server
	* @param	boolean	Whether or not to use persistent connections to the master server
	* @param	string	(Optional) Name of the slave (read) server - should be either left blank or set to 'localhost' or an IP address, but NOT the same as the servername for the master server
	* @param	integer	(Optional) Port of the slave server
	* @param	string	(Optional) Username to connect to the slave server
	* @param	string	(Optional) Password associated with the username for the slave server
	* @param	boolean	(Optional) Whether or not to use persistent connections to the slave server
	* @param	string	(Optional) Parse given MySQL config file to set options
	* @param	string	(Optional) Connection Charset MySQLi / PHP 5.1.0+ or 5.0.5+ / MySQL 4.1.13+ or MySQL 5.1.10+ Only
	*
	* @return	none
	*/
	function connect($database, $w_servername, $w_port, $w_username, $w_password, $w_usepconnect = false, $r_servername = '', $r_port = 3306, $r_username = '', $r_password = '', $r_usepconnect = false, $configfile = '', $charset = '')
	{
		$this->database = $database;

		$w_port = $w_port ? $w_port : 3306;
		$r_port = $r_port ? $r_port : 3306;

		$this->connection_master = $this->db_connect($w_servername, $w_port, $w_username, $w_password, $w_usepconnect, $configfile, $charset);
		$this->multiserver = false;

		if (!$this->connection_master)
		{
			throw new vB_Exception_Database('vbulletin_database_errors');
		}

		if (!empty($r_servername))
		{
			$this->connection_slave = $this->db_connect($r_servername, $r_port, $r_username, $r_password, $r_usepconnect, $configfile, $charset);
		}

		$this->select_db($this->database);

		if (empty($this->connection_slave))
		{
			$this->connection_slave =& $this->connection_master;
		}
		else
		{
			if (!$this->select_db_wrapper($this->database, $this->connection_slave))
			{
				throw new vB_Exception_Database('vbulletin_database_errors');
			}

		}
	}

	/**
	* Initialize database connection(s)
	*
	* Connects to the specified master database server, and also to the slave server if it is specified
	*
	* @param	string	Name of the database server - should be either 'localhost' or an IP address
	* @param	integer	Port of the database server (usually 3306)
	* @param	string	Username to connect to the database server
	* @param	string	Password associated with the username for the database server
	* @param	boolean	Whether or not to use persistent connections to the database server
	* @param	string  Not applicable; config file for MySQLi only
	* @param	string  Force connection character set (to prevent collation errors)
	*
	* @return	boolean
	*/
	function db_connect($servername, $port, $username, $password, $usepconnect, $configfile = '', $charset = '')
	{
		set_error_handler(array($this, 'catch_db_error'));

		// catch_db_error will handle exiting, no infinite loop here
		do
		{
			$link = $this->functions[$usepconnect ? 'pconnect' : 'connect']("$servername:$port", $username, $password);
		}
		while ($link == false AND $this->reporterror);

		restore_error_handler();

		if (!empty($charset))
		{
			if (function_exists('mysql_set_charset'))
			{
				mysql_set_charset($charset);
			}
			else
			{
				$this->sql = "SET NAMES $charset";
				$this->execute_query(true, $link);
			}
		}

		return $link;
	}

	/**
	* Selects a database to use
	*
	* @param	string	The name of the database located on the database server(s)
	*
	* @return	boolean
	*/
	function select_db($database = '')
	{
		if ($database != '')
		{
			$this->database = $database;
		}

		if ($check_write = @$this->select_db_wrapper($this->database, $this->connection_master))
		{
			$this->connection_recent =& $this->connection_master;
			return true;
		}
		else
		{
			$this->connection_recent =& $this->connection_master;
			if (!file_exists(DIR . '/install/install.php'))
			{
				$this->halt('Cannot use database ' . $this->database);
			}
			return false;
		}
	}

	/**
	* Simple wrapper for select_db(), to allow argument order changes
	*
	* @param	string	Database name
	* @param	integer	Link identifier
	*
	* @return	boolean
	*/
	function select_db_wrapper($database = '', $link = null)
	{
		return $this->functions['select_db']($database, $link);
	}

	/**
	* Forces the sql_mode varaible to a specific mode. Certain modes may be
	* incompatible with vBulletin. Applies to MySQL 4.1+.
	*
	* @param	string	The mode to set the sql_mode variable to
	*/
	function force_sql_mode($mode)
	{
		$reset_errors = $this->reporterror;
		if ($reset_errors)
		{
			$this->hide_errors();
		}

		$this->query_write("SET @@sql_mode = '" . $this->escape_string($mode) . "'");

		if ($reset_errors)
		{
			$this->show_errors();
		}
	}

	/**
	* Executes an SQL query through the specified connection
	*
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	* @param	string	The connection ID to the database server
	*
	* @return	string
	*/
	function &execute_query($buffered = true, &$link)
	{
		$this->connection_recent =& $link;
		$this->querycount++;

		if ($this->doExplain)
		{
			$index = $this->preLogQueryToExplain();
		}

		if ($queryresult = @$this->functions[$buffered ? 'query' : 'query_unbuffered']($this->sql, $link))
		{

			if ($this->doExplain)
			{
				$this->postLogQueryToExplain($index);
			}

			// unset $sql to lower memory .. this isn't an error, so it's not needed
			$this->sql = '';

			return $queryresult;
		}
		else
		{
			//halt... doesn't always halt.  Its controlled by the variable $this->reporterror
			if ($this->reporterror)
			{
				$error = 'query_failed';
				$config = vB::getConfig();

				if (!empty($config['Misc']['debug']) AND $config['Misc']['debug'])
				{
					$error .= "\n" . $this->sql;
				}
				$this->halt();
			}
			//need to do it this way because we have a reference return.  Otherwise we get notices in php
			$queryresult = null;
			return $queryresult;
		}
	}

	/**
	* Executes a data-writing SQL query through the 'master' database connection
	*
	* @param	string	The text of the SQL query to be executed
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is buffered.
	*
	* @return	string
	*/
	function query_write($sql, $buffered = true)
	{
		$this->sql =& $sql;
		return $this->execute_query($buffered, $this->connection_master);
	}

	/**
	* Executes a data-reading SQL query through the 'master' database connection
	* we don't know if the 'read' database is up to date so be on the safe side
	*
	* @param	string	The text of the SQL query to be executed
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is buffered.
	*
	* @return	string
	*/
	function query_read($sql, $buffered = true)
	{
		$this->sql =& $sql;
		return $this->execute_query($buffered, $this->connection_master);
	}

	/**
	* Executes a data-reading SQL query through the 'slave' database connection
	*
	* @param	string	The text of the SQL query to be executed
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is buffered.
	*
	* @return	string
	*/
	function query_read_slave($sql, $buffered = true)
	{
		$this->sql =& $sql;
		return $this->execute_query($buffered, $this->connection_slave);
	}

	/**
	* Executes an SQL query, using either the write connection
	*
	* @deprecated	Deprecated as of 3.6. Use query_(read/write)
	*
	* @param	string	The text of the SQL query to be executed
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	*
	* @return	string
	*/
	function query($sql, $buffered = true)
	{
		$this->sql =& $sql;
		return $this->execute_query($buffered, $this->connection_master);
	}

	/**
	* Executes a data-reading SQL query, then returns an array of the data from the first row from the result set
	*
	* @param	string	The text of the SQL query to be executed
	* @param	string	One of (NUM, ASSOC, BOTH)
	*
	* @return	array
	*/
	function &query_first($sql, $type = self::DBARRAY_ASSOC)
	{
		$this->sql =& $sql;
		$queryresult = $this->execute_query(true, $this->connection_master);
		$returnarray = $this->fetch_array($queryresult, $type);
		$this->free_result($queryresult);
		return $returnarray;
	}

	/**
	* Executes a FOUND_ROWS query to get the results of SQL_CALC_FOUND_ROWS
	*
	* @return	integer
	*/
	function found_rows()
	{
		$this->sql = "SELECT FOUND_ROWS()";
		$queryresult = $this->execute_query(true, $this->connection_recent);
		$returnarray = $this->fetch_array($queryresult, self::DBARRAY_NUM);
		$this->free_result($queryresult);

		return intval($returnarray[0]);
	}

	/**
	* Executes a data-reading SQL query against the slave server, then returns an array of the data from the first row from the result set
	*
	* @param	string	The text of the SQL query to be executed
	* @param	string	One of (NUM, ASSOC, BOTH)
	*
	* @return	array
	*/
	function &query_first_slave($sql, $type = self::DBARRAY_ASSOC)
	{
		$this->sql =& $sql;
		$queryresult = $this->query_read_slave($sql);
		$returnarray = $this->fetch_array($queryresult, $type);
		$this->free_result($queryresult);
		return $returnarray;
	}

	/**
	* Executes an INSERT INTO query, using extended inserts if possible
	*
	* @param	string	Name of the table into which data should be inserted
	* @param	string	Comma-separated list of the fields to affect
	* @param	array	Array of SQL values
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	*
	* @return	mixed
	*/
	function &query_insert($table, $fields, &$values, $buffered = true)
	{
		return $this->insert_multiple("INSERT INTO $table $fields VALUES", $values, $buffered);
	}

	/**
	* Executes a REPLACE INTO query, using extended inserts if possible
	*
	* @param	string	Name of the table into which data should be inserted
	* @param	string	Comma-separated list of the fields to affect
	* @param	array	Array of SQL values
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	*
	* @return	mixed
	*/
	function &query_replace($table, $fields, &$values, $buffered = true)
	{
		return $this->insert_multiple("REPLACE INTO $table $fields VALUES", $values, $buffered);
	}

	/**
	* Executes an INSERT or REPLACE query with multiple values, splitting large queries into manageable chunks based on $this->maxpacket
	*
	* @param	string	The text of the first part of the SQL query to be executed - example "INSERT INTO table (field1, field2) VALUES"
	* @param	mixed	The values to be inserted. Example: (0 => "('value1', 'value2')", 1 => "('value3', 'value4')")
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	*
	* @return	mixed
	*/
	function insert_multiple($sql, &$values, $buffered)
	{
		if ($this->maxpacket == 0)
		{
			// must do a READ query on the WRITE link here!
			$vars = $this->query_write("SHOW VARIABLES LIKE 'max_allowed_packet'");
			$var = $this->fetch_row($vars);
			$this->maxpacket = $var[1];
			$this->free_result($vars);
		}

		$i = 0;
		$num_values = sizeof($values);
		$this->sql = $sql;

		while ($i < $num_values)
		{
			$sql_length = strlen($this->sql);
			$value_length = strlen("\r\n" . $values["$i"] . ",");

			if (($sql_length + $value_length) < $this->maxpacket)
			{
				$this->sql .= "\r\n" . $values["$i"] . ",";
				unset($values["$i"]);
				$i++;
			}
			else
			{
				$this->sql = (substr($this->sql, -1) == ',') ? substr($this->sql, 0, -1) : $this->sql;
				$this->execute_query($buffered, $this->connection_master);
				$this->sql = $sql;
			}
		}
		if ($this->sql != $sql)
		{
			$this->sql = (substr($this->sql, -1) == ',') ? substr($this->sql, 0, -1) : $this->sql;
			$this->execute_query($buffered, $this->connection_master);
		}

		if (sizeof($values) == 1)
		{
			return $this->insert_id();
		}
		else
		{
			return true;
		}
	}

	/**
	* Registers an SQL query to be executed at shutdown time. If shutdown functions are disabled, the query is run immediately.
	*
	* @param	string	The text of the SQL query to be executed
	* @param	mixed	(Optional) Allows particular shutdown queries to be labelled
	*
	* @return	boolean
	*/
	function shutdown_query($sql, $arraykey = -1)
	{
		if ($arraykey === -1)
		{
			$this->shutdownqueries[] = $sql;
			return true;
		}
		else
		{
			$this->shutdownqueries["$arraykey"] = $sql;
			return true;
		}
	}

	/**
	* Returns the number of rows contained within a query result set
	*
	* @param	string	The query result ID we are dealing with
	*
	* @return	integer
	*/
	function num_rows($queryresult)
	{
		return @$this->functions['num_rows']($queryresult);
	}

	/**
	* Returns the number of fields contained within a query result set
	*
	* @param	string	The query result ID we are dealing with
	*
	* @return	integer
	*/
	function num_fields($queryresult)
	{
		return @$this->functions['num_fields']($queryresult);
	}

	/**
	* Returns the name of a field from within a query result set
	*
	* @param	string	The query result ID we are dealing with
	* @param	integer	The index position of the field
	*
	* @return	string
	*/
	function field_name($queryresult, $index)
	{
		return @$this->functions['field_name']($queryresult, $index);
	}

	/**
	* Returns the ID of the item just inserted into an auto-increment field
	*
	* @return	integer
	*/
	function insert_id()
	{
		return @$this->functions['insert_id']($this->connection_master);
	}

	/**
	* Returns the name of the character set
	*
	* @return	string
	*/
	function client_encoding()
	{
		return @$this->functions['client_encoding']($this->connection_master);
	}

	/**
	* Closes the connection to the database server
	*
	* @return	integer
	*/
	function close()
	{
		return @$this->functions['close']($this->connection_master);
	}

	/**
	* Escapes a string to make it safe to be inserted into an SQL query
	*
	* @param	string	The string to be escaped
	*
	* @return	string
	*/
	function escape_string($string)
	{
		if ($this->connection_recent === null OR !is_resource($this->connection_recent))
		{
			throw new vB_Exception_Database('vbulletin_database_errors');
		}
		if (!empty($string) AND !is_scalar($string))
		{
			throw new vB_Exception_Api('database_cant_escape_param', array(gettype($string)));
		}
		return $this->functions['real_escape_string']($string, $this->connection_master);
	}

	/**
	* Escapes a string using the appropriate escape character for the RDBMS for use in LIKE conditions
	*
	* @param	string	The string to be escaped
	*
	* @return	string
	*/
	function escape_string_like($string)
	{
		return str_replace(array('%', '_') , array('\%' , '\_') , $this->escape_string($string));
	}

	/**
	* Takes a piece of data and prepares it to be put into an SQL query by adding quotes etc.
	*
	* @param	mixed	The data to be used
	*
	* @return	mixed	The prepared data
	*/
	function sql_prepare($value)
	{
		if (is_string($value))
		{
			return "'" . $this->escape_string($value) . "'";
		}
		else if (is_numeric($value) AND $value + 0 == $value)
		{
			return $value;
		}
		else if (is_bool($value))
		{
			return $value ? 1 : 0;
		}
		else
		{
			return "'" . $this->escape_string($value) . "'";
		}
	}

	/**
	* Fetches a row from a query result and returns the values from that row as an array
	*
	* The value of $type defines whether the array will have numeric or associative keys, or both
	*
	* @param	string	The query result ID we are dealing with
	* @param	integer	One of self::DBARRAY_ASSOC / self::DBARRAY_NUM / self::DBARRAY_BOTH
	*
	* @return	array
	*/
	function fetch_array($queryresult, $type = self::DBARRAY_ASSOC)
	{
		$result = @$this->functions['fetch_array']($queryresult, $this->fetchtypes["$type"]);
		return $result;
	}

	/**
	* Fetches a row from a query result and returns the values from that row as an array with numeric keys
	*
	* @param	string	The query result ID we are dealing with
	*
	* @return	array
	*/
	function fetch_row($queryresult)
	{
		$result = @$this->functions['fetch_row']($queryresult);
		return $result;
	}

	/**
	* Fetches a row information from a query result and returns the values from that row as an array
	*
	* @param	string	The query result ID we are dealing with
	*
	* @return	array
	*/
	function fetch_field($queryresult)
	{
		$result = @$this->functions['fetch_field']($queryresult);
		return $result;
	}

	/**
	* Moves the internal result pointer within a query result set
	*
	* @param	string	The query result ID we are dealing with
	* @param	integer	The position to which to move the pointer (first position is 0)
	*
	* @return	boolean
	*/
	function data_seek($queryresult, $index)
	{
		return @$this->functions['data_seek']($queryresult, $index);
	}

	/**
	* Frees all memory associated with the specified query result
	*
	* @param	string	The query result ID we are dealing with
	*
	* @return	boolean
	*/
	function free_result($queryresult)
	{
		$this->sql = '';
		return @$this->functions['free_result']($queryresult);
	}

	/**
	* Retuns the number of rows affected by the most recent insert/replace/update query
	*
	* @return	integer
	*/
	function affected_rows()
	{
		$this->rows = $this->functions['affected_rows']($this->connection_recent);
		return $this->rows;
	}

	/**
	* Ping connection and reconnect
	* Don't use this in a manner that could cause a loop condition
	*
	*/
	function ping()
	{
		if (!@$this->functions['ping']($this->connection_master))
		{
			$this->close();

			$vb5_config =& vB::getConfig();
			// make database connection
			$this->connect(
				$vb5_config['Database']['dbname'],
				$vb5_config['MasterServer']['servername'],
				$vb5_config['MasterServer']['port'],
				$vb5_config['MasterServer']['username'],
				$vb5_config['MasterServer']['password'],
				$vb5_config['MasterServer']['usepconnect'],
				$vb5_config['SlaveServer']['servername'],
				$vb5_config['SlaveServer']['port'],
				$vb5_config['SlaveServer']['username'],
				$vb5_config['SlaveServer']['password'],
				$vb5_config['SlaveServer']['usepconnect']
			);
		}
	}

	/**
	* Lock tables
	*
	* @param	mixed	List of tables to lock
	* @param	string	Type of lock to perform
	*
	*/
	function lock_tables($tablelist)
	{
		if (!empty($tablelist) AND is_array($tablelist))
		{
			$vb5_config =& vB::getConfig();

			// Don't lock tables if we know we might get stuck with them locked (pconnect = true)
			if ($vb5_config['MasterServer']['usepconnect'])
			{
				return;
			}

			$sql = '';
			foreach($tablelist AS $name => $type)
			{
				$sql .= (!empty($sql) ? ', ' : '') . TABLE_PREFIX . $name . " " . $type;
			}

			$this->query_write("LOCK TABLES $sql");
			$this->locked = true;
		}
	}

	/**
	* Unlock tables
	*
	*/
	function unlock_tables()
	{
		if ($this->locked)
		{
			$this->query_write("UNLOCK TABLES");
		}
	}

	/**
	* Returns the text of the error message from previous database operation
	*
	* @return	string
	*/
	function error()
	{
		if ($this->connection_recent === null)
		{
			$this->error = '';
		}
		else
		{
			$this->error = $this->functions['error']($this->connection_recent);
		}
		return $this->error;
	}

	/**
	* Returns the numerical value of the error message from previous database operation
	*
	* @return	integer
	*/
	function errno()
	{
		if ($this->connection_recent === null)
		{
			$this->errno = 0;
		}
		else
		{
			$this->errno = $this->functions['errno']($this->connection_recent);
		}
	}

	/**
	* Switches database error display ON
	*/
	function show_errors()
	{
		$this->reporterror = true;
	}

	/**
	* Switches database error display OFF
	*/
	function hide_errors()
	{
		$this->reporterror = false;
	}


	// #############################################################################
	/**
	* Feeds database connection errors into the halt() method of the vB_Database class.
	*
	* @param	integer	Error number
	* @param	string	PHP error text string
	* @param	strig	File that contained the error
	* @param	integer	Line in the file that contained the error
	*/
	//this needs to be public to work but *should not be called* except as an error call back.
	public function catch_db_error($errno, $errstr, $errfile, $errline)
	{
		global $db;
		static $failures;

		if (strstr($errstr, 'Lost connection') AND $failures < 5)
		{
			$failures++;
			return;
		}

		throw new Exception("$errno: $errstr");
	}

	/**
	* Halts execution of the entire system and displays an error message
	*
	* @param	string	Text of the error message. Leave blank to use $this->sql as error text.
	*
	* @return	integer
	*/
	function halt($errortext = '')
	{
		static $called = false;

		if ($called)
		{
			if (!empty($errortext))
			{
				$this->error = $errortext;
			}
			return $this->error;
		}
		else
		{
			$called = true;
		}

		if ($this->connection_recent)
		{
			$this->error = $this->error($this->connection_recent);
			$this->errno = $this->errno($this->connection_recent);
		}

		if ($this->errno == -1)
		{
			throw new exception('no_vb5_database');
		}

		if ($this->reporterror)
		{
			if ($errortext == '')
			{
				$this->sql = "Invalid SQL:\r\n" . chop($this->sql) . ';';
				$errortext =& $this->sql;

				if (strlen($errortext) > 512)
				{
					$truncated_errortext = "\r\n[Showing truncated query, original length: " . strlen($this->sql) . "]\r\n[First 100 chars]\r\n" . substr($errortext, 0, 100) . "\r\n[Last 100 chars]\r\n" . substr($errortext, -100);
					$errortext = $truncated_errortext;
					unset($truncated_errortext);
				}
			}

			$session = vB::getCurrentSession();

			if ($session)
			{
				$userinfo = $session->fetch_userinfo();
			}

			//TODO -- need to clean up VB_AREA stuff
			if (defined('VB_AREA') AND (VB_AREA == 'Upgrade' OR VB_AREA == 'Install'))
			{
				$display_db_error = true;
			}
			else
			{
				$userContext = vB::getUserContext();
				$display_db_error = $userContext ? $userContext->isAdministrator() : false;
			}

			// Hide the MySQL Version if its going in the source
			if (!$display_db_error)
			{
				$mysqlversion = '';
			}
			else if ($this->connection_recent)
			{
				$this->hide_errors();
				list($mysqlversion) = $this->query_first("SELECT VERSION() AS version", self::DBARRAY_NUM);
				$this->show_errors();
			}

			$vb5_config = vB::getConfig();
			$request = vB::getRequest();

			if ($request)
			{
				$timeNow = $request->getTimeNow();
				$scriptpath = 'unknown';
				$ipAddress = 'unknown';
				$scriptpath = $request->getScriptPath();
				$ipAddress = $request->getIpAddress();
				$referer = $request->getReferrer();
			}
			else
			{
				$timeNow = time();
				$scriptpath = '';
				$ipAddress = '';
				$referer = '';
			}
			$vboptions = vB::getDatastore()->getValue('options');
			$technicalemail =& $vb5_config['Database']['technicalemail'];

			$data = array();
			$data['error'] = $this->error;
			$data['errno'] = $this->errno;
			$data['requestdate'] = date('l, F jS Y @ h:i:s A', $timeNow);
			$data['date'] =  date('l, F jS Y @ h:i:s A');
			$data['host'] = ""; //todo figure this out for non http requests
			$data['scriptpath'] = str_replace('&amp;', '&', $scriptpath);
			$data['referer'] = $referer;
			$data['ipaddress'] = $ipAddress;
			$data['username'] = isset($userinfo['username']) ? $userinfo['username'] : "";
			$data['classname']  = get_class($this);
			$data['mysqlversion'] = $mysqlversion;
			$data['technicalemail'] = $technicalemail;
			$data['appname'] = $this->appname;
			$data['templateversion'] = $vboptions['templateversion'];

			if ($vb5_config['Misc']['debug'])
			{
				$data['trace'] = debug_backtrace();
			}

			$dbexception = new vB_Exception_Database($errortext, $data);

			//log message
			require_once(DIR . '/includes/functions_log_error.php');
			if (function_exists('log_vbulletin_error'))
			{
				log_vbulletin_error($dbexception->getMessage(), 'database');
			}

			if ($this->reporterror)
			{
				throw $dbexception;
			}

		}
		else if (!empty($errortext))
		{
			$this->error = $errortext;
		}
	}

	/**
	* Initializes logging a query for "explain" output
	*
	* @return	int	Index of the current query in the explain output array
	*/
	protected function preLogQueryToExplain()
	{
		if (!$this->doExplain)
		{
			return;
		}

		static $index = -1;
		++$index;

		$this->explain[$index] = array(
			'timeStart' => microtime(true),
			'timeStop' => null,
			'timeTaken' => null,
			'memoryStart' => memory_get_usage(true),
			'memoryStop' => null,
			'memoryUsed' => null,
			'query' => $this->sql,
			'trace' => debug_backtrace(),
		);

		if (!$this->startTime)
		{
			$this->startTime = $this->explain[$index]['timeStart'];
		}

		return $index;
	}

	/**
	* Finishes logging a query for "explain" output, called after running the query
	*
	* @param	int	Index of the current query in the explain output array
	*/
	protected function postLogQueryToExplain($index)
	{
		if (!$this->doExplain)
		{
			return;
		}

		$this->explain[$index]['timeStop'] = microtime(true);
		$this->explain[$index]['timeTaken'] = $this->explain[$index]['timeStop'] - $this->explain[$index]['timeStart'];

		$this->explain[$index]['memoryStop'] = memory_get_usage(true);
		$this->explain[$index]['memoryUsed'] = $this->explain[$index]['memoryStop'] - $this->explain[$index]['memoryStart'];

		$this->sqlTime += $this->explain[$index]['timeTaken'];
		$this->phpTime = $this->explain[$index]['timeStop'] - $this->startTime - $this->sqlTime;
	}

	/**
	* At the end of the request, this returns the generated "explain" output for display
	*/
	public function getExplain()
	{
		if (!$this->doExplain)
		{
			return;
		}

		$explain = $this->explain;
		$this->explain = null;
		$describe = array();
		$duplicates = array();

		foreach ($explain as $i => &$query)
		{
			if (preg_match('/^\s*SELECT/i', $query['query']))
			{
				$query['explain'] = $this->runExplainQuery($query['query']);
			}
			else
			{
				$query['explain'] = '';
			}

			if (preg_match('/^\s*DESCRIBE/i', $query['query']))
			{
				$describe[] = $query['query'];
			}

			$key = md5(strtolower(trim($query['query'])));
			if (!isset($duplicates[$key]))
			{
				$duplicates[$key] = array(
					'count' => 1,
					'queryIndex' => $i,
				);
			}
			else
			{
				++$duplicates[$key]['count'];
			}
		}

		$realDuplicates = array();
		foreach ($duplicates as $duplicate)
		{
			if ($duplicate['count'] > 1)
			{
				$realDuplicates[] = array(
					'count' => $duplicate['count'],
					'query' => $explain[$duplicate['queryIndex']]['query'],
				);
			}
		}

		return array(
			'explain' => $explain,
			'describe' => $describe,
			'duplicates' => $realDuplicates,
			'sqltime' => $this->sqlTime,
			'phptime' => $this->phpTime,
		);
	}

	/**
	* Helper function used by getExplain to run the EXPLAIN query for the current query
	*
	* @param	string	The current SQL query
	*
	* @return	string	The formatted output for the EXPLAIN information for the query
	*/
	protected function runExplainQuery($sql)
	{
		if (!$this->doExplain)
		{
			return;
		}

		$results = $this->functions['query']('EXPLAIN ' . $sql);
		$output = '<table width="100%" cellpadding="2" cellspacing="1"><tr>';
		while ($field = $this->functions['fetch_field']($results))
		{
			$output .= '<th>' . $field->name . '</th>';
		}
		$output .= '</tr>';
		$numfields = $this->functions['num_fields']($results);
		while ($result = $this->fetch_row($results))
		{
			$output .= '<tr>';
			for ($i = 0; $i < $numfields; $i++)
			{
				$output .= "<td>" . ($result["$i"] == '' ? '&nbsp;' : $result["$i"]) . "</td>";
			}
			$output .= '</tr>';
		}
		$output .= '</table>';

		return $output;
	}

	/**
	* Function to return the codes of critical errors when testing if a database 
	* is a valid vB5 database - normally database not found and table not found errors.
	*
	* This should be set by the child class for each database type.
	*
	* @return	array	An array of error codes.
	*/
	protected function getCriticalErrors()
	{
		return array(-1);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
