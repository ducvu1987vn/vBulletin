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
* Class to do data update operations for multiple USERS simultaneously
*
* @package	vBulletin
* @version	$Revision: 40911 $
* @date		$Date: 2010-12-02 20:38:25 -0200 (Thu, 02 Dec 2010) $
*/
class vB_DataManager_User_Multiple extends vB_DataManager_Multiple
{
	/**
	* The name of the class to instantiate for each matching. It is assumed to exist!
	* It should be a subclass of vB_DataManager.
	*
	* @var	string
	*/
	var $class_name = 'vB_DataManager_User';

	/**
	* The name of the primary ID column that is used to uniquely identify records retrieved.
	* This will be used to build the condition in all update queries!
	*
	* @var string
	*/
	var $primary_id = 'userid';

	/**
	* Builds the SQL to run to fetch records. This must be overridden by a child class!
	*
	* @param	string	Condition to use in the fetch query; the entire WHERE clause
	* @param	integer	The number of records to limit the results to; 0 is unlimited
	* @param	integer	The number of records to skip before retrieving matches.
	*
	* @return	string	The query to execute
	*/
	function fetch_query($condition, $limit = 0, $offset = 0)
	{
		$query = "SELECT * FROM " . TABLE_PREFIX . "user AS user";
		if ($condition)
		{
			$query .= " WHERE $condition";
		}

		$limit = intval($limit);
		$offset = intval($offset);
		if ($limit)
		{
			$query .= " LIMIT $offset, $limit";
		}

		return $query;
	}

	/**
	* Sets the values for user[usertitle] and user[customtitle]
	*
	* @param	string	Custom user title text
	* @param	boolean	Whether or not to reset a custom title to the default user title
	* @param	array	Array containing all information for the user's primary usergroup
	* @param	boolean	Whether or not a user can use custom user titles ($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusecustomtitle'])
	* @param	boolean	Whether or not the user is an administrator ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
	*/
	function set_usertitle($customtext, $reset, $usergroup, $canusecustomtitle, $isadmin)
	{
		if ($this->children)
		{
			$firstid = reset($this->primary_ids);
			$this->children["$firstid"]->set_usertitle($customtext, $reset, $usergroup, $canusecustomtitle, $isadmin);
		}
	}

	/**
	* Validates and sets custom user profile fields
	*
	* @param	array	Array of values for profile fields. Example: array('field1' => 'One', 'field2' => array(0 => 'a', 1 => 'b'), 'field2_opt' => 'c')
	*/
	function set_userfields(&$values)
	{
		if ($this->children)
		{
			$firstid = reset($this->primary_ids);
			$this->children["$firstid"]-> set_userfields($values);
		}
	}

	/**
	* Sets DST options
	*
	* @param	integer	DST choice: (2: automatic; 1: auto-off, dst on; 0: auto-off, dst off)
	*/
	function set_dst(&$dst)
	{
		if ($this->children)
		{
			$firstid = reset($this->primary_ids);
			$this->children["$firstid"]->set_dst($dst);
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

			while ($id = next($this->primary_ids))
			{
				$child =& $this->children["$id"];

				$child->user = $master->user;
				$child->userfield = $master->userfield;
				$child->usertextfield = $master->usertextfield;

				$child->info = $master->info;
			}
		}
	}

	/**
	* Executes the necessary query/queries to update the records
	*
	* @param	boolean	Actually perform the query?
	*/
	function execute_query($doquery = true)
	{
		$condition = 'userid IN (' . implode(',', $this->primary_ids) . ')';
		$master =& $this->children[reset($this->primary_ids)];

		foreach (array('user', 'userfield', 'usertextfield') AS $table)
		{
			if (is_array($master->$table) AND !empty($master->$table))
			{
				$sql = $master->fetch_update_sql(TABLE_PREFIX, $table, $condition);

				if ($doquery)
				{
					$this->dbobject->query_write($sql);
				}
				else
				{
					echo "<pre>$sql<hr /></pre>";
				}
			}
		}
	}

}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/