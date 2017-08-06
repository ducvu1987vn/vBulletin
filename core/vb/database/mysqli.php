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

// MySQLi Database Class

/**
* Class to interface with a MySQL 4.1 database
*
* This class also handles data replication between a master and slave(s) servers
*
* @package	vBulletin
* @version	$Revision: 43748 $
* @date		$Date: 2011-05-23 16:49:33 -0300 (Mon, 23 May 2011) $
*/
class vB_Database_MySQLi extends vB_Database
{
	/**
	* Array of function names, mapping a simple name to the RDBMS specific function name
	*
	* @var	array
	*/
	var $functions = array(
		'connect'            => 'mysqli_real_connect',
		'pconnect'           => 'mysqli_real_connect', // mysqli doesn't support persistent connections THANK YOU!
		'select_db'          => 'mysqli_select_db',
		'query'              => 'mysqli_query',
		'query_unbuffered'   => 'mysqli_unbuffered_query',
		'fetch_row'          => 'mysqli_fetch_row',
		'fetch_array'        => 'mysqli_fetch_array',
		'fetch_field'        => 'mysqli_fetch_field',
		'free_result'        => 'mysqli_free_result',
		'data_seek'          => 'mysqli_data_seek',
		'error'              => 'mysqli_error',
		'errno'              => 'mysqli_errno',
		'affected_rows'      => 'mysqli_affected_rows',
		'num_rows'           => 'mysqli_num_rows',
		'num_fields'         => 'mysqli_num_fields',
		'field_name'         => 'mysqli_field_tell',
		'insert_id'          => 'mysqli_insert_id',
		'escape_string'      => 'mysqli_real_escape_string',
		'real_escape_string' => 'mysqli_real_escape_string',
		'close'              => 'mysqli_close',
		'client_encoding'    => 'mysqli_client_encoding',
		'ping'               => 'mysqli_ping',
	);

	/**
	* Array of constants for use in fetch_array
	*
	* @var	array
	*/
	var $fetchtypes = array(
		self::DBARRAY_NUM   => MYSQLI_NUM,
		self::DBARRAY_ASSOC => MYSQLI_ASSOC,
		self::DBARRAY_BOTH  => MYSQLI_BOTH
	);

	/**
	* Initialize database connection(s)
	*
	* Connects to the specified master database server, and also to the slave server if it is specified
	*
	* @param	string  Name of the database server - should be either 'localhost' or an IP address
	* @param	integer	Port of the database server - usually 3306
	* @param	string  Username to connect to the database server
	* @param	string  Password associated with the username for the database server
	* @param	string  Persistent Connections - Not supported with MySQLi
	* @param	string  Configuration file from config.php.ini (my.ini / my.cnf)
	* @param	string  Mysqli Connection Charset PHP 5.1.0+ or 5.0.5+ / MySQL 4.1.13+ or MySQL 5.1.10+ Only
	*
	* @return	object  Mysqli Resource
	*/
	function db_connect($servername, $port, $username, $password, $usepconnect, $configfile = '', $charset = '')
	{
		set_error_handler(array($this, 'catch_db_error'));

		$link = mysqli_init();
		# Set Options Connection Options
		if (!empty($configfile))
		{
			mysqli_options($link, MYSQLI_READ_DEFAULT_FILE, $configfile);
		}

		// this will execute at most 5 times, see catch_db_error()
		do
		{
			$connect = $this->functions['connect']($link, $servername, $username, $password, '', $port);
		}
		while ($connect == false AND $this->reporterror);

		restore_error_handler();

		if (!empty($charset))
		{
			if (function_exists('mysqli_set_charset'))
			{
				mysqli_set_charset($link, $charset);
			}
			else
			{
				$this->sql = "SET NAMES $charset";
				$this->execute_query(true, $link);
			}
		}

		return (!$connect) ? false : $link;
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

		if ($queryresult = @mysqli_query($link, $this->sql, ($buffered ? MYSQLI_STORE_RESULT : MYSQLI_USE_RESULT)))
		{
			// unset $sql to lower memory .. this isn't an error, so it's not needed
			$this->sql = '';

			return $queryresult;
		}
		else
		{
			$this->halt();
			// unset $sql to lower memory .. error will have already been thrown
			$this->sql = '';
		}
		// Because this function returns a reference, PHP expects something to be returned regardless of $this->halt() above.
		return $queryresult;
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
		return $this->functions['select_db']($link, $database);
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
		return $this->functions['real_escape_string']($this->connection_master, $string);
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
		$field = @$this->functions['fetch_field']($queryresult);
		return $field->name;
	}

	/**
	* Switches database error display ON
	*/
	function show_errors()
	{
		$this->reporterror = true;
		mysqli_report(MYSQLI_REPORT_ERROR);
	}

	/**
	* Switches database error display OFF
	*/
	function hide_errors()
	{
		$this->reporterror = false;
		mysqli_report(MYSQLI_REPORT_OFF);
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
				false, // mysqli doesn't support persistent connections
				$vb5_config['SlaveServer']['servername'],
				$vb5_config['SlaveServer']['port'],
				$vb5_config['SlaveServer']['username'],
				$vb5_config['SlaveServer']['password'],
				false, // mysqli doesn't support persistent connections
				$vb5_config['Mysqli']['ini_file'],
				(isset($vb5_config['Mysqli']['charset']) ? $vb5_config['Mysqli']['charset'] : '')
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

			$sql = '';
			foreach($tablelist AS $name => $type)
			{
				$sql .= (!empty($sql) ? ', ' : '') . TABLE_PREFIX . $name . " " . $type;
			}

			$this->query_write("LOCK TABLES $sql");
			$this->locked = true;
		}
	}

	function errno()
	{
		if ($this->connection_recent === null)
		{
			$this->errno = 0;
		}
		else
		{
			if (!($this->errno = @$this->functions['errno']($this->connection_recent)))
			{
				$this->errno = 0;
			};
		}

		/*	1046 = No database, 
			1146 = Table Missing.
			This is quite likely not a valid vB5 database */
		if ((!defined('VB_AREA') OR VB_AREA != 'Install')
			AND (
				strpos($this->sql, 'routenew') 
				OR strpos($this->sql, 'cache')
			)
			AND (in_array($this->errno, $this->getCriticalErrors()))
		)
		{
			$this->errno = -1;
		}

		return $this->errno;
	}

	/**
	* Function to return the codes of critical errors when testing if a database 
	* is a valid vB5 database - normally database not found and table not found errors.
	*
	* @return	array	An array of error codes.
	*/
	function getCriticalErrors()
	{
	/*	1046 = No database, 
		1146 = Table Missing */
		return array(1046, 1146);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
