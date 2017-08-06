<?php
if (!defined('VB_ENTRY')) die('Access denied.');
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
 * vB_Library_Functions
 *
 * @package vBApi
 * @access public
 */
class vB_Library_Functions extends vB_Library
{
	/**
	* Converts a version number string into an array that can be parsed
	* to determine if which of several version strings is the newest.
	*
	* @param	string	Version string to parse
	*
	* @return	array	Array of 6 bits, in decreasing order of influence; a higher bit value is newer
	*/
	public static function fetchVersionArray($version)
	{
		// parse for a main and subversion
		if (preg_match('#^([a-z]+ )?([0-9\.]+)[\s-]*([a-z].*)$#i', trim($version), $match))
		{
			$main_version = $match[2];
			$sub_version = $match[3];
		}
		else
		{
			$main_version = $version;
			$sub_version = '';
		}

		$version_bits = explode('.', $main_version);

		// Pad the main version to 4 parts ( 1.1.1 XXX )
		if (sizeof($version_bits) < 4)
		{
			for ($i = sizeof($version_bits); $i < 4; $i++)
			{
				$version_bits[$i] = 0;
			}
		}

		// default sub-versions
		$version_bits[4] = 0; // for alpha, beta, rc, pl, etc
		$version_bits[5] = 0; // alpha, beta, etc number

		if (!empty($sub_version))
		{
			// match the sub-version
			if (preg_match('#^(A|ALPHA|B|BETA|G|GAMMA|RC|RELEASE CANDIDATE|GOLD|STABLE|FINAL|PL|PATCH LEVEL)\s*(\d*)\D*$#i', $sub_version, $match))
			{
				switch (strtoupper($match[1]))
				{
					case 'A':
					case 'ALPHA';
						$version_bits[4] = -4;
						break;

					case 'B':
					case 'BETA':
						$version_bits[4] = -3;
						break;

					case 'G':
					case 'GAMMA':
						$version_bits[4] = -2;
						break;

					case 'RC':
					case 'RELEASE CANDIDATE':
						$version_bits[4] = -1;
						break;

					case 'PL':
					case 'PATCH LEVEL';
						$version_bits[4] = 1;
						break;

					case 'GOLD':
					case 'STABLE':
					case 'FINAL':
					default:
						$version_bits[4] = 0;
						break;
				}

				$version_bits[5] = $match[2];
			}
		}

		// sanity check -- make sure each bit is an int
		for ($i = 0; $i <= 5; $i++)
		{
			$version_bits[$i] = intval($version_bits[$i]);
		}

		return $version_bits;
	}

	/**
	* Compares two version strings.
	* Returns true if the first is newer than the second.
	* Returns true if 'check_same' is set and the versions are equal.
	*
	* @param	string	Version string; usually the latest version
	* @param	string	Version string; usually the current version
	* @param	bool	Flag to allow check if the versions are the same
	*
	* @return	bool	True or False
	*/
	public static function isNewerVersion($new_version_str, $cur_version_str, $check_same = false)
	{
		// if they're the same, don't even bother
		if ($cur_version_str != $new_version_str)
		{
			$cur_version = self::fetchVersionArray($cur_version_str);
			$new_version = self::fetchVersionArray($new_version_str);

			// iterate parts
			for ($i = 0; $i <= 5; $i++)
			{
				if ($new_version[$i] != $cur_version[$i])
				{
					// true if newer is greater
					return ($new_version[$i] > $cur_version[$i]);
				}
			}
		}
		else if ($check_same)
		{
			return true;
		}

		return false;
	}

	/**
	* Writes data to a file
	*
	* @param	string	Path to file (including file name)
	* @param	string	Data to be saved into the file
	* @param	boolean	If true, will create a backup of the file called {filename}.old
	*/
	public static function fileWrite($path, $data, $backup = false)
	{
		if (file_exists($path) != false)
		{
			if ($backup)
			{
				$filenamenew = $path . '.old';
				rename($path, $filenamenew);
			}
			else
			{
				unlink($path);
			}
		}

		if ($data != '')
		{
			$filenum = fopen($path, 'w');
			fwrite($filenum, $data);
			fclose($filenum);
		}
	}

	/**
	* Returns the contents of a file
	*
	* @param	string	Path to file (including file name)
	*
	* @return	string	If file does not exist, returns an empty string
	*/
	public static function fileRead($path)
	{
		if(!file_exists($path) AND !is_uploaded_file($path))
		{
			return '';
		}
		else
		{
			$filestuff = @file_get_contents($path);
			return $filestuff;
		}
	}

	/**
	* Installs a product from the xml text, currently calls the legacy function
	*
	* @return bool True if the product requires a template merge, false otherwise
	*/
	public static function installProduct($product, $path = '', $filename = '', $overwrite = false, $printinfo = false)
	{
		if(!$path)
		{
			$path = DIR . DIRECTORY_SEPARATOR . 'includes'. DIRECTORY_SEPARATOR . 'xml';
		}

		if(!$filename)
		{
			// Default filenames
			$filename1 = 'product_' . $product . '.xml'; // vB5 format
			$filename2 = 'product-' . $product . '.xml'; // vB3/4 format
		}
		else
		{
			$filename1 = $filename;
			$filename2 = '';
		}

		$file1 = $path . DIRECTORY_SEPARATOR . $filename1;
		$file2 = $path . DIRECTORY_SEPARATOR . $filename2;

		if ($xml = self::fileRead($file1))
		{
			return self::installProductXML($xml, $overwrite, $printinfo);
		}
		else if ($xml = self::fileRead($file2))
		{
			return self::installProductXML($xml, $overwrite, $printinfo);
		}
		else
		{
			return false;
		}
	}

	/**
	* Installs a product from the xml text, currently calls the legacy function
	*
	* @return bool True if the product requires a template merge, false otherwise
	*/
	public static function installProductXML($xml, $overwrite = false, $printinfo = false)
	{
		require_once(DIR . '/includes/adminfunctions.php');
		require_once(DIR . '/includes/adminfunctions_product.php');

		try
		{
			$install = install_product($xml, $overwrite, $printinfo);
		}
		catch (exception $e)
		{
			return false;
		}

		return $install;
	}

	/*
	 * Recursion creation of directory
	 *
	 * @param	string	Directory to create
	 * @param	octal	Mode
	 *
	 * @return bool success
	 */
	public static function vbMkdir($path, $mode = 0777)
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
			if (!self::vbMkdir($partialpath, $mode))
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
