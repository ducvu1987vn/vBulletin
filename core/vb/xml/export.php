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

abstract class vB_Xml_Export
{
	/**
	 *
	 * @var vB_dB_Assertor 
	 */
	protected $db;
	
	public function __construct()
	{
		$this->db = vB::getDbAssertor();
	}
	
	public static function createGUID($record, $source = 'vbulletin')
	{
		return vB_GUID::get("$source-");
	}
	
	/**
	 * Export objects to the specified filepath
	 */
	public function export($filepath, $overwrite = TRUE)
	{
		if (!$overwrite AND file_exists($filepath))
		{
			throw new Exception('Target file already exists');
		}
		
		file_put_contents($filepath, $this->getXml());
	}
	
	protected abstract function getXml();
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
