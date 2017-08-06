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
 * This is the query processor for select queries. This includes index handling.
 *
 * @package vBDatabase
 * @version $Revision: 28823 $
 */
class vB_dB_Query_Select extends vB_dB_Query
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

		$this->query_type = self::QUERY_SELECT;
		$this->table_query = true;
	}

	/** This loads and validates the data- ensures we have all we need
	*
	*	@param	array		the data for the query
	 *	@param	mixed		sort information
	 ***/
	public function setQuery($params, $sortorder)
	{
		return parent::setTableQuery($params, $sortorder);
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
			throw new Exception('invalid_query_definition');
		}

		return $this->doSelect();
	}


	/** This function does the selects and returns a result object
	 *
	 *	@param	char
	 *
	 *	@return 	object
	 **/
	protected function doSelect()
	{
		$querystring = self::buildQuery($this->params);

		if (!$querystring)
		{
			return false;
		}

		if ($this->dbSlave)
		{
			$useSlave = false;
		}
		else
		{
			$useSlave = !empty($this->params[vB_dB_Query::PRIORITY_QUERY]);
		}
		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($this->db, $querystring, $useSlave);
		return $result;
	}

}

/*======================================================================*\
|| ####################################################################
|| # SVN=> $Revision=> 28823 $
|| ####################################################################
\*======================================================================*/
