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
 * Input
 * Utility class for handling user input, including cleaning.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: $
 * @since $Date: $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Input
{
	/*Constants=====================================================================*/

	/**
	 * Input types.
	 * These are currently derived from class_core.php
	 */
	const TYPE_NOCLEAN 			= vB_Cleaner::TYPE_NOCLEAN;				// no change

	const TYPE_BOOL 			= vB_Cleaner::TYPE_BOOL; 				// force boolean
	const TYPE_INT 				= vB_Cleaner::TYPE_INT; 				// force integer
	const TYPE_UINT 			= vB_Cleaner::TYPE_UINT; 				// force unsigned integer
	const TYPE_NUM 				= vB_Cleaner::TYPE_NUM; 				// force number
	const TYPE_UNUM 			= vB_Cleaner::TYPE_UNUM; 				// force unsigned number
	const TYPE_UNIXTIME 		= vB_Cleaner::TYPE_UNIXTIME; 			// force unix datestamp (unsigned integer)
	const TYPE_STR 				= vB_Cleaner::TYPE_STR; 				// force trimmed string
	const TYPE_NOTRIM 			= vB_Cleaner::TYPE_NOTRIM; 				// force string - no trim
	const TYPE_NOHTML 			= vB_Cleaner::TYPE_NOHTML; 				// force trimmed string with HTML made safe
	const TYPE_ARRAY 			= vB_Cleaner::TYPE_ARRAY; 				// force array
	const TYPE_FILE 			= vB_Cleaner::TYPE_FILE; 				// force file
	const TYPE_BINARY 			= vB_Cleaner::TYPE_BINARY; 				// force binary string
	const TYPE_NOHTMLCOND 		= vB_Cleaner::TYPE_NOHTMLCOND; 			// force trimmed string with HTML made safe if determined to be unsafe

	const TYPE_ARRAY_BOOL 		= vB_Cleaner::TYPE_ARRAY_BOOL;
	const TYPE_ARRAY_INT 		= vB_Cleaner::TYPE_ARRAY_INT;
	const TYPE_ARRAY_UINT 		= vB_Cleaner::TYPE_ARRAY_UINT;
	const TYPE_ARRAY_NUM 		= vB_Cleaner::TYPE_ARRAY_NUM;
	const TYPE_ARRAY_UNUM 		= vB_Cleaner::TYPE_ARRAY_UNUM;
	const TYPE_ARRAY_UNIXTIME 	= vB_Cleaner::TYPE_ARRAY_UNIXTIME;
	const TYPE_ARRAY_STR 		= vB_Cleaner::TYPE_ARRAY_STR;
	const TYPE_ARRAY_NOTRIM 	= vB_Cleaner::TYPE_ARRAY_NOTRIM;
	const TYPE_ARRAY_NOHTML 	= vB_Cleaner::TYPE_ARRAY_NOHTML;
	const TYPE_ARRAY_ARRAY 		= vB_Cleaner::TYPE_ARRAY_ARRAY;
	const TYPE_ARRAY_FILE 		= vB_Cleaner::TYPE_ARRAY_FILE;  		// An array of "Files" behaves differently than other <input> arrays. vB_Cleaner::TYPE_FILE handles both types.
	const TYPE_ARRAY_BINARY 	= vB_Cleaner::TYPE_ARRAY_BINARY;
	const TYPE_ARRAY_NOHTMLCOND = vB_Cleaner::TYPE_ARRAY_NOHTMLCOND;

	const TYPE_ARRAY_KEYS_INT 	= vB_Cleaner::TYPE_ARRAY_KEYS_INT;
	const TYPE_ARRAY_KEYS_STR 	= vB_Cleaner::TYPE_ARRAY_KEYS_STR;

	const TYPE_CONVERT_SINGLE 	= vB_Cleaner::TYPE_CONVERT_SINGLE; 		// value to subtract from array types to convert to single types
	const TYPE_CONVERT_KEYS 	= vB_Cleaner::TYPE_CONVERT_KEYS; 		// value to subtract from array => keys types to convert to single types



	/*Clean=========================================================================*/

	/**
	 * Cleans a value according to the specified type.
	 * The type should match one of the vB_Input::TYPE_ constants.
	 *
	 * Note: This is currently a wrapper for the vB_Input_Cleaner vB::$vbulletin->input.
	 *
	 * @param mixed $value						- The value to clean
	 * @param int $type							- The type to clean as
	 * @return mixed							- The cleaned value
	 */
	public static function clean($value, $type)
	{
		return vB::$vbulletin->cleaner->clean($value, $type);
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/