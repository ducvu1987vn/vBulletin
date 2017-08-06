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
 * vB_Utilities
 *
 * @package vBApi
 * @access public
 */
class vB_Utilities
{
	public static function vbmkdir($path, $mode = 0777)
	{
		if (is_dir($path))
		{
			if (!(is_writable($path)))
			{
				@chmod($path, $mode);
			}
			return true;
		}
		else
		{
			$oldmask = @umask(0);
			$partialpath = dirname($path);

			if (!self::vbmkdir($partialpath, $mode))
			{
				return false;
			}
			else
			{
				return @mkdir($path, $mode);
			}
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 27657 $
|| ####################################################################
\*======================================================================*/
