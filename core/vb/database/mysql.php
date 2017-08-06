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

// MySQL Database Class

/**
* Class to interface with a MySQL 4.1 database
*
* This class also handles data replication between a master and slave(s) servers
*
* @package	vBulletin
* @version	$Revision: 43748 $
* @date		$Date: 2011-05-23 16:49:33 -0300 (Mon, 23 May 2011) $
*/
class vB_Database_MySQL extends vB_Database
{
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
				OR strpos($this->sql, 'setting')
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
	* This should be set by the child class for each database type.
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
