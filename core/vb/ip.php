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

class vB_Ip
{
	const IPV4_REGEX = '(\d{1,3})(?:\.(\d{1,3})){3}';

	/**
	 * Validates an IPv4 address
	 * @param string $ipAddress
	 * @return bool
	 */
	public static function isValidIPv4($ipAddress)
	{
		if (!preg_match('#^' . self::IPV4_REGEX . '$#', trim($ipAddress), $matches))
		{
			return FALSE;
		}

		for($i=1; $i<count($matches); $i++)
		{
			if ((!is_numeric($matches[$i])) OR $matches[$i] < 0 OR $matches[$i] > 255)
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	/**
	 * Validates an IPv6 string representation and returns the ip fields for storage. If representation is invalid it returns FALSE.
	 * @param string $ipAddress
	 * @return mixed
	 */
	public static function validateIPv6($ipAddress)
	{
		$ipAddress = strtolower(trim($ipAddress));

		if (!$ipAddress)
		{
			return FALSE;
		}

		if (strpos($ipAddress, '[') === 0)
		{
			if(substr($ipAddress, -1) == ']')
			{
				// remove square brackets
				$ipAddress = substr($ipAddress, 1, -1);
			}
			else
			{
				// unmatched square bracket
				return FALSE;
			}
		}

		if ( substr_count($ipAddress, '::') > 1)
		{
			// only one group of zeroes can be compressed
			return FALSE;
		}

		$fields = array(
			'ip_4' => '0',
			'ip_3' => '0',
			'ip_2' => '0',
			'ip_1' => '0'
		);

		// get part(s) of (compressed?) address
		$parts = explode('::', $ipAddress);

		$group_regex = '#^[a-f0-9]{1,4}$#';
		$canonical = array(0,0,0,0,0,0,0,0);

		// now validate each part, starting with lower order values
		if (isset($parts[1]) AND !empty($parts[1]))
		{
			$groups = explode(':', $parts[1]);
			$num_groups = count($groups);

			// we allow dotted-quad notation (::ffff:192.0.2.128)
			if (empty($parts[0]) AND $num_groups == 2 AND $groups[0] == 'ffff' AND self::isValidIPv4($groups[1]))
			{
				$fiels['ip_4'] = $fiels['ip_3'] = '0';
				$fields['ip_2'] = '0xffff';
				$fields['ip_1'] = sprintf('%u', ip2long($groups[1]));

				return $fields;
			}
			else
			{
				for($i=0; $i<$num_groups; $i++)
				{
					if (preg_match($group_regex, $groups[$i], $matches) AND ($hex = hexdec($groups[$i])) <= 0xffff)
					{
						// add it to the last part of canonical
						$canonical[8 - $num_groups + $i] = $hex;
					}
					else
					{
						return FALSE;
					}
				}
			}
		}

		// now high order values
		if ($parts[0])
		{
			$groups = explode(':', $parts[0]);
			$num_groups = count($groups);

			if (!isset($parts[1]) AND $num_groups < 8)
			{
				// some 2-byte groups are missing
				return FALSE;
			}

			for($i=0; $i<$num_groups; $i++)
			{
				if (preg_match($group_regex, $groups[$i], $matches) AND ($hex = hexdec($groups[$i])) <= 0xffff)
				{
					$canonical[$i] = $hex;
				}
				else
				{
					return FALSE;
				}
			}
		}

		// now use the canonical form to build the ip fields
		$fields['ip_4'] = sprintf('%u', ($canonical[0] << 16) + $canonical[1]);
		$fields['ip_3'] = sprintf('%u', ($canonical[2] << 16) + $canonical[3]);
		$fields['ip_2'] = sprintf('%u', ($canonical[4] << 16) + $canonical[5]);
		$fields['ip_1'] = sprintf('%u', ($canonical[6] << 16) + $canonical[7]);

		return $fields;
	}

	/**
	 * Gets ip fields for storage from a string representation of IP. If the IP string is invalid it returns FALSE.
	 * @param string $ipAddress
	 * @return mixed
	 */
	public static function getIpFields($ipAddress)
	{
		if (self::isValidIPv4($ipAddress))
		{
			return array(
				'ip_4' => '0',
				'ip_3' => '0',
				'ip_2' => '0xffff',
				'ip_1' => sprintf('%u', ip2long($ipAddress))
			);
		}
		else
		{
			return self::validateIPv6($ipAddress);
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/