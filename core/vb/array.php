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

class vB_Array
{

	protected static function arrayReplaceRecurse($array, $array1)
	{
		foreach ($array1 as $key => $value)
		{
			// create new key in $array, if it is empty or not an array
			if (!isset($array[$key]) || (isset($array[$key]) && !is_array($array[$key])))
			{
				$array[$key] = array();
			}

			// overwrite the value in the base array
			if (is_array($value))
			{
				$value = self::arrayReplaceRecurse($array[$key], $value);
			}
			$array[$key] = $value;
		}
		return $array;
	}

	public static function arrayReplaceRecursive(array &$array, array &$array1)
	{
		if (function_exists('array_replace_recursive'))
		{
			// For 5.3+
			return call_user_func_array('array_replace_recursive', func_get_args());
		}
		else
		{
			// Prior to 5.3
			// handle the arguments, merge one by one
			$args = func_get_args();
			$array = $args[0];
			if (!is_array($array))
			{
				return $array;
			}
			for ($i = 1; $i < count($args); $i++)
			{
				if (is_array($args[$i]))
				{
					$array = self::arrayReplaceRecurse($array, $args[$i]);
				}
			}
			return $array;
		}
	}

}

/* ======================================================================*\
  || ####################################################################
  || # CVS: $RCSfile$ - $Revision: 40911 $
  || ####################################################################
  \*====================================================================== */
