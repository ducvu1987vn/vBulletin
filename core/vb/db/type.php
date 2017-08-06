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
 * @package vBDatabase
 */

/**
 * @package vBDatabase
 */
abstract class vB_dB_Type
{
	protected static function getInstance($type, $value)
	{
		$dbType = vB::getDbAssertor()->getDbType();
		$className = "vB_dB_{$dbType}_Type_" . $type;
		if (class_exists($className))
		{
			return new $className($value);
		}
	}

	public abstract function escapeFieldValue();
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/