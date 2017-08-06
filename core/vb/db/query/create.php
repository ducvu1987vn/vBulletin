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
 * This is the query processor for Create Table-type queries.
 *
 * @package vBDatabase
 * @version $Revision: 28823 $
 */
class vB_dB_Query_Create extends vB_dB_Query
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


	/** standard constructor.
	 *
	 *		@param 	string	id of the query
	 * 	@param 	mixed		the shared db object
	 * 	@param	array		the user information
	 *
	 ***/
	public function __construct($queryid, &$db, $userinfo)
	{
		parent:: __construct($queryid, $db, $userinfo);

		$this->query_type = self::QUERY_UPDATE;
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

		if (!$this->query_type OR (!$this->query_string AND !$this->structure))
		{
			return false;
		}
		reset($params);

		if (is_array(current($params)))
		{
			$checkvals = current($params);
		}
		else
		{
			$checkvals = $params;
		}

		//We're not going to do the detailed match. At this step we should
		//only do the obvious. So if we are a stored query and either params or the
		//replacements are empty but not the other, then we can't execute.
		switch($this->query_type)
		{
			case self::QUERY_INSERT: //We are a stored query insert.
			case self::QUERY_UPDATE: //We are a stored query update.
			case self::QUERY_SELECT:  //We are a stored query select.
			case self::QUERY_DELETE: //We are a stored query delete.
				$this->table_query = false;
				if (count($checkvals) AND ($this->query_string) AND !count($this->replacements))
				{
						return false;
				}

				if (count($this->replacements) AND ($this->query_string)  AND !count($checkvals))
				{
						return false;
				}
				//We at least are potentially good.


				break;
			case self::QUERY_TABLE: //We are a table. We don't know yet what we are executing

				if (!$checkvals[self::TYPE_KEY])
				{
					return false;
				}
				$this->table_query = true;
				switch($checkvals[self::TYPE_KEY])
				{
					case self::QUERY_INSERT: //We are a table insert
					case self::QUERY_INSERTIGNORE:
					case self::QUERY_UPDATE: //We are a table update.
					case self::QUERY_DELETE: //We are a table delete.
						if (count($checkvals) < 2)
						{
							return false;
						}
						$this->query_type = $checkvals[self::TYPE_KEY];
						break;
					case self::QUERY_MULTIPLEINSERT:
						if (!isset($checkvals[self::FIELDS_KEY]) OR empty($checkvals[self::FIELDS_KEY]) OR
							!isset($checkvals[self::VALUES_KEY]) OR empty($checkvals[self::VALUES_KEY]))
						{
							return false;
						}
						$this->query_type = self::QUERY_MULTIPLEINSERT;
						break;
					case self::QUERY_SELECT:  //We are a table select. We don't need anything
						$this->query_type = self::QUERY_SELECT;
						break;
					default:
						return false;
				} // switch
				break;
			case self::QUERY_METHOD: //we are a method call.
				$method = $this->method_name;
				$this->params = $params;
				$this->data_loaded = $this->querydef_object->$method($params, $this->db, true);
				return $this->data_loaded;
				break;

			default:
				return false;
			;
		} // switch

		if (is_array(current($params)) OR ($this->query_type == self::QUERY_SELECT))
		{
			$this->params = $params;
		}
		else
		{
			$this->params = array($params);
		}

		if ($sortorder)
		{
			$this->sortorder = $sortorder;
		}
		$this->data_loaded = true;
		return true;
	}

	/** This function is the public interface to actually execute the SQL.
	*
	*	@return 	mixed
	**/
	public function execSQL()
	{
		$result_class = 'vB_dB_' . $this->db_type . '_result';

		//If we don't have the data loaded, we can't execute.
		if (!$this->query_type OR !$this->data_loaded)
		{
			return false;
		}

		switch($this->query_type)
		{
			case self::QUERY_METHOD: //we are a method call.
				$method = $this->method_name;
				$result = $this->querydef_object->$method($this->params, $this->db, false);
				break;
			case self::QUERY_UPDATE: //We are a stored query update.
				$result = $this->doUpdates();
				break;
			case self::QUERY_INSERT: //We are a stored query insert.
			case self::QUERY_INSERTIGNORE:
				$result = $this->doInserts();
				break;
			case self::QUERY_MULTIPLEINSERT:
				$result = $this->doMultipleInserts();
				break;
			case self::QUERY_SELECT:  //We are a stored query select.
				return $this->doSelect();
				break;
			case self::QUERY_DELETE: //We are a stored query delete.
				$result = $this->doDeletes();
				;
				break;
			case self::QUERY_TABLE: //We are a table. We should never have gotten here.
			default:
				return false;
				;
		} // switch
		return $result;
	}


}

/*======================================================================*\
|| ####################################################################
|| # SVN=> $Revision=> 28823 $
|| ####################################################################
\*======================================================================*/
