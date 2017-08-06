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
* Class for image processing
*
* @package 		vBulletin
* @version		$Revision: 58687 $
* @date 		$Date: 2012-02-06 23:57:08 -0700 (Mon, 06 Feb 2012) $
*
*/
abstract class vB_Image
{
	/**
	 * Class constants
	 */

	/**
	 * Global image type defines used by serveral functions
	 */
	const GIF = 1;
	const JPG = 2;
	const PNG = 3;

	/**
	* These make up the bit field to enable specific parts of image verification
	*/
	const ALLOW_RANDOM_FONT = 1;
	const ALLOW_RANDOM_SIZE = 2;
	const ALLOW_RANDOM_SLANT = 4;
	const ALLOW_RANDOM_COLOR = 8;
	const ALLOW_RANDOM_SHAPE = 16;

	/**
	* Options from datastore
	*
	* @var	array
	*/
	var $options = null;

	/**
	* @var	array
	*/
	var $thumb_extensions = array();

	/**
	* @var	array
	*/
	var $info_extensions = array();

	/**
	* @var	array
	*/
	var $must_convert_types = array();

	/**
	* @var	array
	*/
	var $resize_types = array();

	/**
	* @var	mixed
	*/
	var $imageinfo = null;

	/**
	* @var	array $extension_map
	*/
	var $extension_map = array(
		'gif'  => 'GIF',
		'jpg'  => 'JPEG',
		'jpeg' => 'JPEG',
		'jpe'  => 'JPEG',
		'png'  => 'PNG',
		'bmp'  => 'BMP',
		'tif'  => 'TIFF',
		'tiff' => 'TIFF',
		'psd'  => 'PSD',
		'pdf'  => 'PDF',
	);

	/**
	* @var	array	$regimageoption
	*/
	var $regimageoption = array(
		'randomfont'  => false,
		'randomsize'  => false,
		'randomslant' => false,
		'randomcolor' => false,
		'randomshape'  => false,
	);

	/**
	 * Used to translate from imagetype constants to extension name.
	 * @var	array	$imagetype_constants
	 */
	var $imagetype_constants = array(
		1 => 'GIF',
		2 => 'JPEG',
		3 => 'PNG',
		5 => 'PSD',
		6 => 'BMP',
		7 => 'TIFF',
		8 => 'TIFF'
	);

	/**
	* Constructor
	* Don't allow direct construction of this abstract class
	* Sets registry
	*
	* @return	void
	*/
	public function __construct($options)
	{
		if (!defined('ATTACH_AS_DB'))
		{
			define('ATTACH_AS_DB', 0);
		}

		if (!defined('ATTACH_AS_FILES_OLD'))
		{
			define('ATTACH_AS_FILES_OLD', 1);
		}

		if (!defined('ATTACH_AS_FILES_NEW'))
		{
			define('ATTACH_AS_FILES_NEW', 2);
		}

		if (!defined('IMAGEGIF'))
		{
			if (function_exists('imagegif'))
			{
				define('IMAGEGIF', true);
			}
			else
			{
				define('IMAGEGIF', false);
			}
		}

		if (!defined('IMAGEJPEG'))
		{
			if (function_exists('imagejpeg'))
			{
				define('IMAGEJPEG', true);
			}
			else
			{
				define('IMAGEJPEG', false);
			}
		}


		if (!defined('IMAGEPNG'))
		{
			if (function_exists('imagepng'))
			{
				define('IMAGEPNG', true);
			}
			else
			{
				define('IMAGEPNG', false);
			}
		}

		if (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < 256 * 1024 * 1024 AND $current_memory_limit > 0)
		{
			try
			{
				@ini_set('memory_limit', 256 * 1024 * 1024);
			}
			catch (Exception $e)
			{
				// just ignore
			}
		}

		$this->options = $options;
		$this->regimageoption['randomfont'] = $this->options['regimageoption'] & self::ALLOW_RANDOM_FONT;
		$this->regimageoption['randomsize'] = $this->options['regimageoption'] & self::ALLOW_RANDOM_SIZE;
		$this->regimageoption['randomslant'] = $this->options['regimageoption'] & self::ALLOW_RANDOM_SLANT;
		$this->regimageoption['randomcolor'] = $this->options['regimageoption'] & self::ALLOW_RANDOM_COLOR;
		$this->regimageoption['randomshape'] = $this->options['regimageoption'] & self::ALLOW_RANDOM_SHAPE;
	}

	/**
	* Select image library
	*
	* @return	object
	*/
	public static function instance($type = 'image')
	{
		$vboptions = vB::getDatastore()->getValue('options');

		// Library used for thumbnails, image functions
		if ($type == 'image')
		{
			$selectclass = 'vB_Image_' . (($vboptions['imagetype'] == 'Magick') ? 'ImageMagick' : 'GD');
		}
		// Library used for Verification Image
		else
		{
			switch($vboptions['regimagetype'])
			{
				case 'Magick':
					$selectclass = 'vB_Image_ImageMagick';
					break;
				default:
					$selectclass = 'vB_Image_GD';
			}
		}
		$object = new $selectclass($vboptions);
		return $object; // function defined as returning & must return a defined variable
	}

	/**
	*
	* Fetches image files from the backgrounds directory
	*
	* @return array
	*
	*/
	protected function &fetchRegimageBackgrounds()
	{
		// Get backgrounds
		$backgrounds = array();
		if ($handle = @opendir(DIR . '/images/regimage/backgrounds/'))
		{
			while ($filename = @readdir($handle))
			{
				if (preg_match('#\.(gif|jpg|jpeg|jpe|png)$#i', $filename))
				{
					$backgrounds[] = DIR . "/images/regimage/backgrounds/$filename";
				}
			}
			@closedir($handle);
		}
		return $backgrounds;
	}

	/**
	*
	* Fetches True Type fonts from the fonts directory
	*
	* @return array
	*
	*/
	protected function &fetchRegimageFonts()
	{
		// Get fonts
		$fonts = array();
		if ($handle = @opendir(DIR . '/images/regimage/fonts/'))
		{
			while ($filename =@ readdir($handle))
			{
				if (preg_match('#\.ttf$#i', $filename))
				{
					$fonts[] = DIR . "/images/regimage/fonts/$filename";
				}
			}
			@closedir($handle);
		}
		return $fonts;
	}

	/**
	*
	*
	*
	* @param	string	$type		Type of image from $info_extensions
	*
	* @return	bool
	*/
	public function fetchMustConvert($type)
	{
		return !empty($this->must_convert_types["$type"]);
	}

	/**
	*
	* Checks if supplied extension can be used by fetchImageInfo
	*
	* @param	string	$extension 	Extension of file
	*
	* @return	bool
	*/
	public function isValidInfoExtension($extension)
	{
		return !empty($this->info_extensions[strtolower($extension)]);
	}

	/**
	*
	* Checks if supplied extension can be resized into a smaller permanent image, not to be used for PSD, PDF, etc as it will lose the original format
	*
	* @param	string	$type 	Type of image from $info_extensions
	*
	* @return	bool
	*
	*/
	public function isValidResizeType($type)
	{
		return !empty($this->resize_types["$type"]);
	}

	/**
	*
	* Checks if supplied extension can be used by fetchThumbnail
	*
	* @param	string	$extension 	Extension of file
	*
	* @return	bool
	*
	*/
	public function isValidThumbnailExtension($extension)
	{
		return !empty($this->thumb_extensions[strtolower($extension)]);
	}

	/**
	*
	* Checks if supplied extension can be used by fetchThumbnail
	*
	* @param	string	$extension 	Extension of file
	*
	* @return	bool
	*
	*/
	public function fetchImagetypeFromExtension($extension)
	{
		$extension = strtolower($extension);

		if (isset($this->extension_map[$extension]))
		{
			return $this->extension_map[$extension];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Gets the extension from a given image file or URL.
	 *
	 * @param	string	Could be a URL string or full path filename
	 *
	 * @return mixed	array of thumbnail ext
	 */
	public function fetchImageExtension($file)
	{
		// let's try to get it from curl
		if (function_exists('curl_getinfo'))
		{
			$connection = curl_init();
			curl_setopt($connection, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($connection , CURLOPT_URL, $file);
			curl_exec($connection);
			$type = @curl_getinfo($connection, CURLINFO_CONTENT_TYPE);
			if (!empty($type))
			{
				$type = explode('/', $type);
				if (!empty($type[1]) AND isset($this->extension_map[$type[1]]))
				{
					return $this->extension_map[$type[1]];
				}
			}
		}

		// now from full path name
		$info = @exif_imagetype($file);
		if (is_numeric($info) AND isset($this->imagetype_constants[$info]))
		{
			return $this->imagetype_constants[$info];
		}

		return false;
	}

	/**
	*
	* Checks for HTML tags that can be exploited via IE
	*
	* @param string	filecontents
	*
	* @return bool
	*
	*/
	public function verifyImageFile($fileContents)
	{
		// Verify that file is playing nice
		$header = substr($fileContents, 0, 256);
		if ($header)
		{
			if (preg_match('#<html|<head|<body|<script|<pre|<plaintext|<table|<a href|<img|<title#si', $header))
			{
				throw new vB_Exception_Api('upload_invalid_image');
			}
		}
		else
		{
			return false;
		}

		return true;
	}

	/**
	*
	* Retrieve info about image
	*
	* @param	string	filename	Location of file
	* @param	string	extension	Extension of file name
	*
	* @return	array	[0]			int		width
	*					[1]			int		height
	*					[2]			string	type ('GIF', 'JPEG', 'PNG', 'PSD', 'BMP', 'TIFF',) (and so on)
	*					[scenes]	int		scenes
	*					[channels]	int		Number of channels (GREYSCALE = 1, RGB = 3, CMYK = 4)
	*					[bits]		int		Number of bits per pixel
	*					[library]	string	Library Identifier
	*
	*/
	public function fetchImageInfo($filename) {}

	/**
	*
	* Output an image based on a string
	*
	* @param	string		string	String to output
	* @param 	bool		moveabout	move text about
	*
	* @return	array		Array containing imageInfo: filedata, filesize, filetype and htmltype
	*
	*/
	public function getImageFromString($string, $moveabout = true) {}

	/**
	*
	* Returns an array containing a thumbnail, creation time, thumbnail size and any errors
	*
	* @param	string	filename	filename of the source file
	* @param	string	location	location of the source file
	* @param	int		maxwidth
	* @param	int		maxheight
	* @param	int		quality		Jpeg Quality
	* @param bool		labelimage	Include image dimensions and filesize on thumbnail
	* @param bool		drawborder	Draw border around thumbnail
	* @param	bool	jpegconvert
	* @param	bool	sharpen
	* @param			owidth
	* @param			oheight
	* @param			ofilesize
	*
	* @return	array
	*
	*/
	public function fetchThumbnail($filename, $location, $maxwidth = 100, $maxheight = 100, $quality = 75, $labelimage = false, $drawborder = false, $jpegconvert = false, $sharpen = true, $owidth = null, $oheight = null, $ofilesize = null) {}

	/** Crop the profile image
	*
	* 	@param 	mixed	array contains all the required information
	* 	@param	int		max width
	* 	@param	int		max height
	* 	@param	bool	force generation of a new file
	*
	*	@return	mixed	array of data with the cropped image info
	 **/
	public function cropImg($imgInfo, $maxwidth = 100, $maxheight = 100, $forceResize = false){}

	/**
	 * Fetch a resize image from an existing filedata
	 *
	 * @param	array	File information
	 *
	 *
	 */
	public function fetchResizedImageFromFiledata(&$record, $type)
	{
		$options = vB::getDatastore()->get_value('options');
		$sizes = @unserialize($options['attachresizes']);
		$filename = 'temp.' . $record['extension'];
		if (!isset($sizes[$type]) OR empty($sizes[$type]))
		{
			throw new vB_Exception_Api('thumbnail_nosupport');
		}

		if ($options['attachfile'])
		{
			if ($options['attachfile'] == ATTACH_AS_FILES_NEW) // expanded paths
			{
				$path = $options['attachpath'] . '/' . implode('/', preg_split('//', $record['userid'],  -1, PREG_SPLIT_NO_EMPTY)) . '/';
			}
			else
			{
				$path = $options['attachpath'] . '/' . $record['userid'] . '/';
			}
			$location = $path .  $record['filedataid'] . '.attach';
		}
		else
		{
			// Must save filedata to a temp file as the img operations require a file read
			if ($options['safeupload'])
			{
				$location = $options['tmppath'] . '/vbupload' . $record['userid'] . substr(time(), -4);
			}
			else
			{
				$location = @tempnam(sys_get_temp_dir(), 'vbupload');
			}
			@file_put_contents($location, $record['filedata']);
		}

		$resized = $this->fetchThumbnail($filename, $location, $sizes[$type], $sizes[$type], $options['thumbquality']);
		$record['resize_dateline'] = $resized['filesize'];
		$record['resize_filesize'] = strlen($resized['filedata']);

		if ($options['attachfile'])
		{
			if ($options['attachfile'] == ATTACH_AS_FILES_NEW) // expanded paths
			{
				$path = $options['attachpath'] . '/' . implode('/', preg_split('//', $record['userid'],  -1, PREG_SPLIT_NO_EMPTY)) . '/';
			}
			else
			{
				$path = $options['attachpath'] . '/' . $record['userid'] . '/';
			}
			@file_put_contents($path .  $record['filedataid'] . '.' . $type, $resized['filedata']);
		}
		else
		{
			$record['resize_filedata'] = $resized['filedata'];
		}

 		vB::getDbAssertor()->assertQuery('vBForum:replaceIntoFiledataResize', array(
 			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
 			'filedataid'      => $record['filedataid'],
 			'resize_type'     => $type,
			'resize_filedata' => $options['attachfile'] ? '' : $record['resize_filedata'],
			'resize_filesize' => $record['resize_filesize'],
			'resize_dateline' => vB::getRequest()->getTimeNow(),
			'resize_width'    => $resized['width'],
			'resize_height'   => $resized['height'],
			'reload'          => 0,
 		));

		if (!$options['attachfile'])
		{
			@unlink($location);
		}
	}

	/* Load information about a file base on the data
	 *
	 * @param 	mixed	database record
	 * @param	mixed	size of image requested [ICON/THUMB/SMALL/MEDIUM/LARGE/FULL]
	 * @param	bool	should we include the image content
	 *
	 * @return	mixed	array of data, includes filesize, dateline, htmltype, filename, extension, and filedataid
	 */
	public function loadFileData($record, $type = vB_Api_Filedata::SIZE_FULL, $includeData = true)
	{
		$options = vB::getDatastore()->get_value('options');
		$type = vB_Api::instanceInternal('filedata')->sanitizeFiletype($type);

		if ($type != vB_Api_Filedata::SIZE_FULL)
		{
			if ($options['attachfile'])
			{
				if ($options['attachfile'] == ATTACH_AS_FILES_NEW) // expanded paths
				{
					$path = $options['attachpath'] . '/' . implode('/', preg_split('//', $record['userid'],  -1, PREG_SPLIT_NO_EMPTY)) . '/';
				}
				else
				{
					$path = $options['attachpath'] . '/' . $record['userid'] . '/';
				}
				$path .= $record['filedataid'] . '.' . $type;
			}

			// Resized image wasn't found
			if (
				empty($record['resize_type'])
					OR
				empty($record['resize_filesize'])
					OR
				(empty($record['resize_filedata']) AND !$options['attachfile'])
					OR
				(
					$options['attachfile']
						AND
					!file_exists($path)
				)
				OR
				$record['reload']
			)
			{
				$this->fetchResizedImageFromFiledata($record, $type);
			}

			$results = array(
				'filesize'   => $record['resize_filesize'],
				'dateline'   => $record['resize_dateline'],
				'headers'    => vB_Library::instance('content_attach')->getAttachmentHeaders(strtolower($record['extension'])),
				'filename'   => $type . '_' . $record['filedataid'] . "." . strtolower($record['extension']),
				'extension'  => $record['extension'],
			   	'filedataid' => $record['filedataid'],
			);

			if ($options['attachfile'] AND $includeData)
			{
				$results['filedata'] = @file_get_contents($path);
			}
			else if ($includeData)
			{
				$results['filedata'] = $record['resize_filedata'];
			}
		}
		else
		{
			$results = array(
				'filesize'   => $record['filesize'],
				'dateline'   => $record['dateline'],
				'headers'    => vB_Library::instance('content_attach')->getAttachmentHeaders(strtolower($record['extension'])),
				'filename'   => 'image_' . $record['filedataid'] . "." . strtolower($record['extension']),
				'extension'  => $record['extension'],
				'filedataid' => $record['filedataid'],
			);

			if ($options['attachfile'] AND $includeData)
			{

				if ($options['attachfile'] == ATTACH_AS_FILES_NEW) // expanded paths
				{
					$path = $options['attachpath'] . '/' . implode('/', preg_split('//', $record['userid'],  -1, PREG_SPLIT_NO_EMPTY)) . '/';
				}
				else
				{
					$path = $options['attachpath'] . '/' . $record['userid'] . '/';
				}
				$results['filedata'] = file_get_contents($path .  $record['filedataid'] . '.attach');

			}
			else if ($includeData)
			{
				$results['filedata'] = $record['filedata'];

			}
		}

		return $results;
	}

	/** standard getter
	 *
	 *	@return	mixed	array of file extension-to-type maps , like 'gif' => "GIF'
	 *  **/
	public function getExtensionMap()
	{
		return $this->extension_map;
	}

	/**
	 * standard getter
	 *
	 * @return mixed	array of must conver types
	 */
	public function getConvertTypes()
	{
		return $this->must_convert_types;
	}

	/**
	 * standard getter
	 *
	 * @return mixed	array of valid extensions
	 */
	public function getInfoExtensions()
	{
		return $this->info_extensions;
	}

	/**
	 * standard getter
	 *
	 * @return mixed	array of resize types
	 */
	public function getResizeTypes()
	{
		return $this->resize_types;
	}

	/**
	 * standard getter
	 *
	 * @return mixed	array of thumbnail ext
	 */
	public function getThumbExtensions()
	{
		return $this->thumb_extensions;
	}

	/**
	 * Attempt to resize file if the filesize is too large after an initial resize to max dimensions or the file is already within max dimensions but the filesize is too large
	 *
	 * @param	bool	Has the image already been resized once?
	 * @param	bool	Attempt a resize
	 */
	function bestResize($width, $height)
	{
		// Linear Regression
		$maxuploadsize = vB::getUserContext()->getLimit('avatarmaxsize');
		switch(vB::getDatastore()->getOption('thumbquality'))
		{
			case 65:
				// No Sharpen
				// $magicnumber = round(379.421 + .00348171 * $this->maxuploadsize);
				// Sharpen
				$magicnumber = round(277.652 + .00428902 * $maxuploadsize);
				break;
			case 85:
				// No Sharpen
				// $magicnumber = round(292.53 + .0027378 * $maxuploadsize);
				// Sharpen
				$magicnumber = round(189.939 + .00352439 * $maxuploadsize);
				break;
			case 95:
				// No Sharpen
				// $magicnumber = round(188.11 + .0022561 * $maxuploadsize);
				// Sharpen
				$magicnumber = round(159.146 + .00234146 * $maxuploadsize);
				break;
			default:	//75
				// No Sharpen
				// $magicnumber = round(328.415 + .00323415 * $maxuploadsize);
				// Sharpen
				$magicnumber = round(228.201 + .00396951 * $maxuploadsize);
		}

		$xratio = ($width > $magicnumber) ? $magicnumber / $width : 1;
		$yratio = ($height > $magicnumber) ? $magicnumber / $height : 1;

		if ($xratio > $yratio AND $xratio != 1)
		{
			$new_width = round($width * $xratio);
			$new_height = round($height * $xratio);
		}
		else
		{
			$new_width = round($width * $yratio);
			$new_height = round($height * $yratio);
		}
		if ($new_width == $width AND $new_height == $height)
		{	// subtract one pixel so that requested size isn't the same as the image size
			$new_width--;
		}
		return array('width' => $new_width, 'height' => $new_height);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 58687 $
|| ####################################################################
\*======================================================================*/
