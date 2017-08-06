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
 * This is the query processor for stored queries.
 *
 * @package vBDatabase
 * @version $Revision: 28823 $
 */
class vB_dB_Query_Stored extends vB_dB_Query
{
	/**
	 *  Handles a query in the "stored queries" array
	 **/


	/*Initialisation================================================================*/
	protected function __construct($queryid, &$db, $userinfo, $dbSlave)
	{
		parent::__construct($queryid, $db, $userinfo, $dbSlave);
		//We must first find out whether we need to use the slave database.
		$this->table_query = false;

	}

	/** This loads and validates the data- ensures we have all we need
	*
	*	@param	array		the data for the query
	***/
	public function setQuery($params, $sortorder)
	{
		if (!isset($this->query_data[$this->queryid]))
		{
			throw new Exception('invalid_query_definition');
		}

		$this->query_type = $this->query_data[$this->queryid][self::QUERYTYPE_KEY];

		$this->query_string = $this->query_data[$this->queryid]['query_string'];
		//Let's first check that we have a valid type, and if necessary we
		// have a valid key.

		if (isset($this->query_data[$this->queryid]['forcetext']))
		{
			$this->forcetext = $this->query_data[$this->queryid]['forcetext'];

			if (!is_array($this->forcetext))
			{
				$this->forcetext = array($this->forcetext);
			}
		}

		if (isset($params[self::TYPE_KEY]))
		{
			unset($params[self::TYPE_KEY]);
		}
		$this->params = $params;

		$queryBuilder = self::$queryBuilders[$this->db_type];
		$this->query_string = $queryBuilder->matchValues($this->queryid, $this->query_string, $this->params, $this->forcetext);

		if (!$this->query_string)
		{
			return false;
		}

		//We at least are potentially good.
		$this->data_loaded = true;
		return true;
	}

	/** This function is the public interface to actually execute the SQL.
	*
	*	@return 	mixed
	**/
	public function execSQL()
	{
		//If we don't have the data loaded, we can't execute.
		if (!$this->query_type OR !$this->data_loaded)
		{
			return false;
		}

		//At this point we need to replace parameters and execute. But the
		//question is whether should return a single value or an iterator.

		if ($this->query_string)
		{
			switch($this->query_type)
			{
				case self::QUERY_SELECT :
					$useSlave = ($this->dbSlave AND empty($this->params[self::PRIORITY_QUERY]));
					$resultclass = 'vB_dB_' . $this->db_type . '_result';
					$result = new $resultclass($this->db, $this->query_string, $useSlave);
					return $result;
					break;

				case self::QUERY_DELETE :
				case self::QUERY_UPDATE :
					$this->db->query_write($this->query_string);
					$error = $this->db->error();
					if (!empty($error))
					{
						throw new Exception($error);
					}
					return $this->db->affected_rows();
					break;

				case self::QUERY_INSERT :
					$this->db->query_write($this->query_string);
					$error = $this->db->error();
					if (!empty($error))
					{
						throw new Exception($error);
					}
					return $this->db->insert_id();
					break;
				case self::QUERY_CREATE :
					$this->db->query_write($this->query_string);
					$error = $this->db->error();
					if (!empty($error))
					{
						throw new Exception($error);
					}
					return;
					break;

			} // switch
		}
		//We should never get here.
		throw new Exception('invalid_query_definition');
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN=> $Revision=> 28823 $
|| ####################################################################
\*======================================================================*/
