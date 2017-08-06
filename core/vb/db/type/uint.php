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
abstract class vB_dB_Type_UInt extends vB_dB_Type
{
	/**
	 * String representation of unsigned integer
	 * @var string
	 */
	protected $value;

	public static function instance($value)
	{
		return parent::getInstance('UInt', $value);
	}

	public function __construct($value)
	{
		if (!is_numeric($value))
		{
			throw new vB_Exception_Assertor("Invalid value for $c constructor.");
		}

		$this->value = (string)$value;
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/