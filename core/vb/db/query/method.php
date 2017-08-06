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
 * This is the query processor for method queries.
 *
 * @package vBDatabase
 * @version $Revision: 28823 $
 */
class vB_dB_Query_Method extends vB_dB_Query
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


	// these are for internal use
	const QUERY_TABLE = 't';

	/*Properties====================================================================*/

	/** The database connection **/
	protected $db = false;

	/** The user info ***/
	protected $userinfo = false;

	/** The character used for quoting in an sql string- usually '.
	 ***/
	protected $quotechar = "'";

	/** We need to know whether we are using a table query when we compose the sql
	* replacements
	***/
	protected $table_query = false;

	/** query_type - s (select), u (update), i (insert), d (delete), and t (table... we don't know yet
	**/
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

	/**the name of the class in the packages that contains the DB code **/
	protected $class_name = false;

	/*Initialisation================================================================*/


	/** standard constructor.
	 *
	 *		@param 	string	id of the query
	 * 	@param 	mixed		the shared db object
	 * 	@param	array		the user information
	 *
	 ***/
	public function __construct($queryid, &$db, $userinfo, $dbSlave)
	{
		parent:: __construct($queryid, $db, $userinfo, $dbSlave);

		$this->query_type = self::QUERY_METHOD;
		$this->table_query = true;
	}

	/** This loads and validates the data- ensures we have all we need
	*
	*	@param	array		the data for the query
	***/
	public function setQuery($params, $sortorder)
	{
		//Let's first check that we have a valid type, and if necessary we
		// have a valid key.

		$method = $this->queryid;
		$this->params = $params;
		$this->data_loaded = $this->querydefs->$method($params, $this->db, true, $this->dbSlave);
		return $this->data_loaded;
	}

	/** This function is the public interface to actually execute the SQL.
	*
	*	@return 	mixed
	**/
	public function execSQL()
	{
		//If we don't have the data loaded, we can't execute.
		if (!$this->data_loaded)
		{
			throw new Exception('invalid_query_parameters for ' . $this->queryid);
		}

		$method = $this->queryid;

		$result = $this->querydefs->$method($this->params, $this->db, false, $this->dbSlave);
		return $result;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN=> $Revision=> 28823 $
|| ####################################################################
\*======================================================================*/
