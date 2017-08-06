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
* Image class for ImageMagick
*
* @package 		vBulletin
* @version		$Revision: 58687 $
* @date 		$Date: 2012-02-06 23:57:08 -0700 (Mon, 06 Feb 2012) $
*
*/
class vB_Image_ImageMagick extends vB_Image
{

	/**
	* @var	string
	*/
	var $convertpath = '/usr/local/bin/convert';

	/**
	* @var	string
	*/
	var $identifypath = '/usr/local/bin/identify';

	/**
	* @var	integer
	*/
	var $returnvalue = 0;

	/**
	* @var  string
	*/
	var $identifyformat = '';

	/**
	* @var	string
	*/
	var $convertoptions = array(
		'width' => '100',
		'height' => '100',
		'quality' => '75',
	);

	/**
	* @var  string
	*
	*/
	var $error = '';

	/**
	* @var string
	*
	*/
	var $thumbcolor = 'black';

	/**
	* Constructor
	* Sets ImageMagick paths to convert and identify
	*
	* @return	void
	*/
	public function __construct($options)
	{
		parent::__construct($options);
		$path = preg_replace('#[/\\\]+$#', '', $this->options['magickpath']);

		if (preg_match('#^WIN#i', PHP_OS))
		{
			$this->identifypath = '"' . $path . '\identify.exe"';
			$this->convertpath = '"' . $path . '\convert.exe"';
		}
		else
		{
			$this->identifypath = "'" . $path .  "/identify'";
			$this->convertpath = "'" . $path . "/convert'";
		}

		$this->must_convert_types = array(
			'PSD'  => true,
			'BMP'  => true,
			'TIFF' => true,
			'PDF'  => true,
		);

		$this->resize_types = array(
			'GIF'   => true,
			'JPEG'  => true,
			'PNG'   => true,
			'BMP'   => true,
			'TIFF'  => true
		);

		$this->thumb_extensions = array(
			'gif'  => true,
			'jpg'  => true,
			'jpe'  => true,
			'jpeg' => true,
			'png'  => true,
			'psd'  => true,
			'pdf'  => true,
			'bmp'  => true,
			'tiff' => true,
			'tif'  => true,
		);
		$this->info_extensions =& $this->thumb_extensions;

		if (preg_match('~^#([0-9A-F]{6})$~i', $this->options['thumbcolor'], $match))
		{
			$this->thumbcolor = $match[0];
		}

		$this->version = $this->fetchVersion();
	}

	/**
	*
	* Generic call to imagemagick binaries
	*
	* @param	string	command	ImageMagick binary to execute
	* @param	string	args	Arguments to the ImageMagick binary
	*
	* @return	mixed
	*
	*/
	protected function fetchImExec($command, $args, $needoutput = false, $dieongs = true)
	{
		if (!function_exists('exec'))
		{
			throw new vB_Exception_Api('php_error_exec_disabled');
		}

		$imcommands = array(
			'identify' => $this->identifypath,
			'convert'  => $this->convertpath,
		);

		$input = $imcommands["$command"] . ' ' . $args . ' 2>&1';
		if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN' AND PHP_VERSION < '5.3.0')
		{
			$input = '"' . $input . '"';
		}
		$exec = @exec($input, $output, $this->returnvalue);

		if ($this->returnvalue OR $exec === null)
		{	// error was encountered
			if (!empty($output))
			{	// command issued by @exec failed
				$outputText = strtolower(implode(' ', $output));
				if (strpos($outputText, 'postscript delegate failed') !== false)
				{
					throw new vB_Exception_Api('install_ghostscript_to_resize_pdf');
				}
				else if (strpos($outputText, 'image smaller than radius') !== false)
				{
					throw new vB_Exception_Api('im_error_bad_sharpen_x', array($outputText));
				}

				throw new vB_Exception_Api('im_exec_error_x', array($outputText));
			}
			else if (!empty($php_errormsg))
			{	// @exec failed so display error and remove path reveal
				throw new vB_Exception_Api('php_error_x', array(str_replace($this->options['magickpath'] . '\\', '', $php_errormsg)));
			}
			else if ($this->returnvalue == -1)
			{	// @exec failed but we don't have $php_errormsg to tell us why
				throw new vB_Exception_Api('php_error_unspecified_exec');
			}
			return false;
		}
		else
		{
			if (!empty($output))
			{	// $output is an array of returned text
				// This is for IM which doesn't return false for failed font
				$outputText = strtolower(implode(' ', $output));
				if (strpos($outputText, 'unable to read font') !== false)
				{
					throw new vB_Exception_Api('im_error_cannot_read_font_x', array($outputText));
				}

				if (strpos($outputText, 'postscript delegate failed') !== false)
				{	// this is for IM 6.2.4+ which doesn't return false for exec(convert.exe) on .pdf when GS isn't installed
					throw new vB_Exception_Api('install_ghostscript_to_resize_pdf');
				}

				if (strpos($outputText, 'image smaller than radius') !== false)
				{
					throw new vB_Exception_Api('im_error_bad_sharpen_x', array($outputText));
				}

				return $output;
			}
			else if (empty($output) AND $needoutput)
			{	// $output is empty and we expected something back
				return false;
			}
			else
			{	// $output is empty and we didn't expect anything back
				return true;
			}
		}
	}

	/**
	*
	* Fetch Imagemagick Version
	*
	* @return	mixed
	*
	*/
	protected function fetchVersion()
	{
		if ($result = $this->fetchImExec('convert', '-version', true) AND preg_match('#ImageMagick (\d+\.\d+\.\d+)#', $result[0], $matches))
		{
			return $matches[1];
		}

		return false;
	}

	/**
	*
	* Identify an image
	*
	* @param	string	$filename File to obtain image information from
	*
	* @return	mixed
	*
	*/
	protected function fetchIdentifyInfo($filename)
	{
		$fp = @fopen($filename, 'rb');
		$frame0 = "";
		if (($header = @fread($fp, 4)) == '%PDF')
		{	// this is a PDF so only look at frame 0 to save mucho processing time
			$frame0 = '[0]';
		}
		@fclose($fp);

		$execute = (!empty($this->identifyformat) ? "-format {$this->identifyformat} \"$filename\"" : "\"$filename\"") . $frame0;

		if ($result = $this->fetchImExec('identify', $execute, true))
		{
			if (empty($result) OR !is_array($result))
			{
				return false;
			}

			do
			{
				$last = array_pop($result);
			}
			while (!empty($result) AND $last == '');

			$temp = explode('###', $last);

			if (count($temp) < 6)
			{
				return false;
			}

			preg_match('#^(\d+)x(\d+)#', $temp[0], $matches);

			$imageinfo = array(
				2         => $temp[3],
				'bits'    => $temp[6],
				'scenes'  => $temp[4],
				'animated' => ($temp[4] > 1),
				'library' => 'IM',
			);

			if (version_compare($this->version, '6.2.6', '>='))
			{
				$imageinfo[0] = $matches[1];
				$imageinfo[1] = $matches[2];
			}
			else	//IM v6.2.5 and lower don't support -laters optimize
			{
				$imageinfo[0] = $temp[1];
				$imageinfo[1] = $temp[2];
			}

			switch($temp[5])
			{
				case 'PseudoClassGray':
				case 'PseudoClassGrayMatte':
				case 'PseudoClassRGB':
				case 'PseudoClassRGBMatte':
					$imageinfo['channels'] = 1;
					break;
				case 'DirectClassRGB':
					$imageinfo['channels'] = 3;
					break;
				case 'DirectClassCMYK':
					$imageinfo['channels'] = 4;
					break;
				default:
					$imageinfo['channels'] = 1;
			}

			return $imageinfo;
		}
		else
		{
			return false;
		}
	}

	/**
	* Private
	* Set image size for convert
	*
	* @param	width	Width of new image
	* @param	height	Height of new image
	* @param	quality Quality of Jpeg images
	* @param bool		Include image dimensions and filesize on thumbnail
	* @param bool		Draw border around thumbnail
	*
	* @return	void
	*
	*/
	protected function setConvertOptions($width = 100, $height = 100, $quality = 75, $labelimage = false, $drawborder = false, $jpegconvert = false, $owidth = null, $oheight = null, $ofilesize = null)
	{
		$this->convertoptions['width'] = $width;
		$this->convertoptions['height'] = $height;
		$this->convertoptions['quality'] = $quality;
		$this->convertoptions['labelimage'] = $labelimage;
		$this->convertoptions['drawborder'] = $drawborder;
		$this->convertoptions['owidth'] = $owidth;
		$this->convertoptions['oheight'] = $oheight;
		$this->convertoptions['ofilesize'] = $ofilesize;
		$this->convertoptions['jpegconvert'] = $jpegconvert;
	}

	/**
	*
	* Convert an image
	*
	* @param	string	filename	Image file to convert
	* @param	string	output		Image file to write converted image to
	* @param	string	extension	Filetype
	* @param	boolean	thumbnail	Generate a thumbnail for display in a browser
	* @param	boolean	sharpen		Sharpen the output
	*
	* @return	mixed
	*
	*/
	protected function fetchConvertedImage($filename, $output, $imageinfo, $thumbnail = true, $sharpen = true)
	{
		$execute = '';

		if ($thumbnail)
		{
			// Only specify scene 1 if this is a PSD or a PDF -- allows animated gifs to be resized..
			$execute .= (in_array($imageinfo[2], array('PDF', 'PSD'))) ? " \"{$filename}\"[0] " : " \"$filename\"";
		}
		else
		{
			$execute .= " \"$filename\"";
		}

		if ($imageinfo['scenes'] > 1 AND version_compare($this->version, '6.2.6', '>='))
		{
			$execute .= ' -coalesce ';
		}

		if ($this->convertoptions['width'] > 0 OR $this->convertoptions['height'] > 0)
		{
			if ($this->convertoptions['width'])
			{
				$size = $this->convertoptions['width'];
				if ($this->convertoptions['height'])
				{
					$size .= 'x' . $this->convertoptions['height'];
				}
			}
			else if ($this->convertoptions['height'])
			{
				$size .= 'x' . $this->convertoptions['height'];
			}
			$execute .= " -size $size ";
		}

		if ($thumbnail)
		{
			if ($size)
			{	// have to use -thumbnail here .. -sample looks BAD for animated gifs
				$execute .= " -thumbnail \"$size>\" ";
			}
		}
		$execute .= ($sharpen AND $imageinfo[2] == 'JPEG') ? " -sharpen 0x1 " : '';

		if ($imageinfo['scenes'] > 1 AND version_compare($this->version, '6.2.6', '>='))
		{
			$execute .= ' -layers optimize ';
		}

		// ### Convert a CMYK jpg to RGB since IE/Firefox will not display CMYK inline .. conversion is ugly since we don't specify profiles
		if ($this->imageinfo['channels'] == 4 AND $thumbnail)
		{
			$execute .= ' -colorspace RGB ';
		}

		if ($thumbnail)
		{
			$xratio = ($this->convertoptions['width'] == 0 OR $imageinfo[0] <= $this->convertoptions['width']) ? 1 : $imageinfo[0] / $this->convertoptions['width'];
			$yratio = ($this->convertoptions['height'] == 0 OR $imageinfo[1] <= $this->convertoptions['height']) ? 1 : $imageinfo[1] / $this->convertoptions['height'];

			if ($xratio > $yratio)
			{
				$new_width = round($imageinfo[0] / $xratio) - 1;
				$new_height = round($imageinfo[1] / $xratio) - 1;
			}
			else
			{
				$new_width = round($imageinfo[0] / $yratio) - 1;
				$new_height = round($imageinfo[1] / $yratio) - 1;
			}

#			if ($imageinfo[0] <= $this->convertoptions['width'] AND $imageinfo[1] <= $this->convertoptions['height'])
#			{
#				$this->convertoptions['labelimage'] = false;
#				$this->convertoptions['drawborder'] = false;
#			}

			if ($this->convertoptions['labelimage'])
			{
				if ($this->convertoptions['owidth'])
				{
					$dimensions = "{$this->convertoptions['owidth']}x{$this->convertoptions['oheight']}";
				}
				else
				{
					$dimensions = "$imageinfo[0]x$imageinfo[1]";
				}
				if ($this->convertoptions['ofilesize'])
				{
					$filesize = $this->convertoptions['ofilesize'];
				}
				else
				{
					$filesize = @filesize($filename);
				}
				if ($filesize / 1024 < 1)
				{
					$filesize = 1024;
				}
				$sizestring = (!empty($filesize)) ? number_format($filesize / 1024, 0, '', '') . 'kb' : '';

				if (!$this->convertoptions['jpegconvert'] OR $imageinfo[2] == 'PSD' OR $imageinfo[2] == 'PDF')
				{
					$type = $imageinfo[2];
				}
				else
				{
					$type = 'JPEG';
				}

				if (($new_width / strlen("$dimensions $sizestring $type")) >= 6)
				{
					$finalstring = "$dimensions $sizestring $type";
				}
				else if  (($new_width / strlen("$dimensions $sizestring")) >= 6)
				{
					$finalstring = "$dimensions $sizestring";
				}
				else if (($new_width / strlen($dimensions)) >= 6)
				{
					$finalstring = $dimensions;
				}
				else if (($new_width / strlen($sizestring)) >= 6)
				{
					$finalstring = $sizestring;
				}

				if ($finalstring)
				{	// confusing -flip statements added to workaround an issue with very wide yet short images. See http://www.imagemagick.org/discourse-server/viewtopic.php?t=10367
					$execute .= " -flip -background \"{$this->thumbcolor}\" -splice 0x15 -flip -gravity South -fill white  -pointsize 11 -annotate 0 \"$finalstring\" ";
				}
			}

			if ($this->convertoptions['drawborder'])
			{
				$execute .= " -bordercolor \"{$this->thumbcolor}\" -compose Copy -border 1 ";
			}

			if (($imageinfo[2] == 'PNG' OR $imageinfo[2] == 'PSD') AND !$this->convertoptions['jpegconvert'])
			{
				$execute .= " -depth 8 -quality {$this->convertoptions['quality']} PNG:";
			}
			else if ($this->fetchMustConvert($imageinfo[2]) OR $imageinfo[2] == 'JPEG' OR $this->convertoptions['jpegconvert'])
			{
				$execute .= " -quality {$this->convertoptions['quality']} JPEG:";
			}
			else if ($imageinfo[2] == 'GIF')
			{
				$execute .= " -depth $imageinfo[bits] ";
			}
		}

		$execute .= "\"$output\"";

		try
		{
			$zak = $this->fetchImExec('convert', $execute);
		}
		catch (vB_Exception_Api $ex)
		{
			$errorMsg = strtolower($ex->getMessage());
			if ($sharpen AND (strpos($errorMsg, 'im_error_bad_sharpen_x') !== false))
			{
				// try to resize again, but without sharpen
				$zak = $this->fetchConvertedImage($filename, $output, $imageinfo, $thumbnail, false);
			}
			else
			{
				throw $ex;
			}
		}

		return $zak;
	}

	/**
	*
	* See function definition in vB_Image
	*
	*/
	public function fetchImageInfo($filename)
	{
		$fileContents = @file_get_contents($filename);
		if (!$this->verifyImageFile($fileContents))
		{
			throw new vB_Exception_Api('invalid_file_content');
		}

		$this->identifyformat = '%g###%w###%h###%m###%n###%r###%z###';
		$this->imageinfo = $this->fetchIdentifyInfo($filename);
		return $this->imageinfo;
	}

	/**
	*
	* See function definition in vB_Image
	*
	*/
	public function fetchThumbnail($filename, $location, $maxwidth = 100, $maxheight = 100, $quality = 75, $labelimage = false, $drawborder = false, $jpegconvert = false, $sharpen = true, $owidth = null, $oheight = null, $ofilesize = null)
	{
		$thumbnail = array(
			'filedata'   => '',
			'filesize'   => 0,
			'dateline'   => 0,
			'imageerror' => '',
		);

		if ($this->isValidThumbnailExtension(file_extension($filename)))
		{
			if ($imageinfo = $this->fetchImageInfo($location))
			{
				$thumbnail['source_width'] = $imageinfo[0];
				$thumbnail['source_height'] = $imageinfo[1];
				if ($this->fetchImagetypeFromExtension(file_extension($filename)) != $imageinfo[2])
				{
					throw new vB_Exception_Api('thumbnail_notcorrectimage');
				}
				else if ($imageinfo[0] > $maxwidth OR $imageinfo[1] > $maxheight OR $this->fetchMustConvert($imageinfo[2]))
				{
					if ($this->options['safeupload'])
					{
						$tmpname = $this->options['tmppath'] . '/' . md5(uniqid(microtime()) . vB::getCurrentSession()->get('userid'));
					}
					else
					{
						if (!($tmpname = @tempnam(ini_get('upload_tmp_dir'), 'vbthumb')))
						{
							throw new vB_Exception_Api('thumbnail_nogetimagesize');
						}
					}

					$this->setConvertOptions($maxwidth, $maxheight, $quality, $labelimage, $drawborder, $jpegconvert, $owidth, $oheight, $ofilesize);
					if ($result = $this->fetchConvertedImage($location, $tmpname, $imageinfo, true, $sharpen))
					{
						if ($imageinfo = $this->fetchImageInfo($tmpname))
						{
							$thumbnail['width'] = $imageinfo[0];
							$thumbnail['height'] = $imageinfo[1];
						}
						$extension = strtolower(file_extension($filename));
						if ($jpegconvert)
						{
							$thumbnail['filename'] = preg_replace('#' . preg_quote(file_extension($filename), '#') . '$#', 'jpg', $filename);
						}
						$thumbnail['filesize'] = filesize($tmpname);
						$thumbnail['dateline'] = vB::getRequest()->getTimeNow();
						$thumbnail['filedata'] = file_get_contents($tmpname);
					}
					else
					{
						throw new vB_Exception_Api('thumbnail_nogetimagesize');
					}
					@unlink($tmpname);
				}
				else
				{
					// image is a thumbnail size already
					if ($imageinfo[0] > 0 AND $imageinfo[1] > 0)
					{
						$thumbnail['filedata'] = @file_get_contents($location);
						$thumbnail['width'] = $imageinfo[0];
						$thumbnail['height'] = $imageinfo[1];
					}
					else
					{
						throw new vB_Exception_Api('thumbnail_nogetimagesize');
					}
				}
			}
			else
			{
				throw new vB_Exception_Api('thumbnail_nogetimagesize');
			}
		}
		else
		{
			throw new vB_Exception_Api('thumbnail_nosupport');
		}

		if (!empty($thumbnail['filedata']))
		{
			$thumbnail['filesize'] = strlen($thumbnail['filedata']);
			$thumbnail['dateline'] = vB::getRequest()->getTimeNow();
		}
		return $thumbnail;
	}

	public function cropImg($imgInfo, $maxwidth = 100, $maxheight = 100, $forceResize = false)
	{
		$thumbnail = array(
			'filedata'   => '',
			'filesize'   => 0,
			'dateline'   => 0,
			'imageerror' => '',
		);

		$execute = '';
		$filename = $imgInfo['filename'];
		$fh = fopen($filename, 'w');
		fwrite($fh, $imgInfo['filedata']);
		fclose($fh);

		if ($this->isValidThumbnailExtension($imgInfo['extension']))
		{
			$thumbnail['source_width'] = $width  = $imgInfo['width'];
			$thumbnail['source_height'] = $height = $imgInfo['height'];

			if ($forceResize OR $imgInfo['width'] >= $maxwidth OR $imgInfo['height'] >= $maxheight )
			{
				$xratio = ($maxwidth == 0) ? 1 : $width / $maxwidth;
				$yratio = ($maxheight == 0) ? 1 : $height / $maxheight;
				if ($xratio > $yratio)
				{
					$new_width = round($width / $xratio);
					$new_height = round($height / $xratio);
				}
				else
				{
					$new_width = round($width / $yratio);
					$new_height = round($height / $yratio);
				}
				$tempdir = sys_get_temp_dir();
				$time = time();
				$tmpFile = $tempdir . '/' . $time . '.' . $imgInfo['extension'];
				$tmpFileNewThumb = $tempdir . '/' . $time . '_thumb' . '.' . $imgInfo['extension'];
				$tmpFileNew = $tempdir . '/' . $time . '-0' . '.' . $imgInfo['extension'];

				$execute = "convert $filename -crop " . $new_width . "x" . $new_height . "+" . $imgInfo['x1'] . "+" . $imgInfo['y1'] . " " . $filename . " " . $tmpFile;
				exec($execute);

				$execute_thumb = "convert $tmpFileNew -resize 100x100 " . $tmpFileNewThumb;
				exec($execute_thumb);

				if (file_exists($tmpFileNew))
				{
					if ($imageinfo = $this->fetchImageInfo($tmpFileNew))
					{
						$thumbnail['width'] = $imageinfo[0];
						$thumbnail['height'] = $imageinfo[1];
					}
					$extension = strtolower(file_extension($filename));

					$thumbnail['filename'] = preg_replace('#' . preg_quote(file_extension($filename), '#') . '$#', 'jpg', $filename);

					$thumbnail['filesize'] = filesize($tmpFileNew);
					$thumbnail['dateline'] = vB::getRequest()->getTimeNow();
					$thumbnail['filedata'] = file_get_contents($tmpFileNew);
				}
				else
				{
					throw new vB_Exception_Api('thumbnail_nogetimagesize');
				}
				@unlink($tmpFileNew);
				//Now for the thumb
				if (file_exists($tmpFileNewThumb))
				{
					if ($imageinfo = $this->fetchImageInfo($tmpFileNewThumb))
					{
						$thumbnail['thumb_dimensions']['width'] = $imageinfo[0];
						$thumbnail['thumb_dimensions']['height'] = $imageinfo[1];
					}
					$extension = strtolower(file_extension($filename));

					$thumbnail['thumb_dimensions']['filesize'] = filesize($tmpFileNewThumb);
					$thumbnail['thumb_dimensions']['dateline'] = vB::getRequest()->getTimeNow();
					$thumbnail['thumb_dimensions']['filedata'] = file_get_contents($tmpFileNewThumb);
				}
				else
				{
					throw new vB_Exception_Api('thumbnail_nogetimagesize');
				}
				@unlink($tmpFileNewThumb);
			}
			else
			{
				// image is a thumbnail size already
				if ($imgInfo['width'] > 0 AND $imgInfo['height'] > 0)
				{
					$thumbnail['filedata'] = @file_get_contents($filename);
					$thumbnail['width'] = $imgInfo['width'];
					$thumbnail['height'] = $imgInfo['height'];
				}
				else
				{
					throw new vB_Exception_Api('thumbnail_nogetimagesize');
				}
			}
		}
		else
		{
			throw new vB_Exception_Api('thumbnail_nosupport');
		}

		if (!empty($thumbnail['filedata']))
		{
			$thumbnail['filesize'] = strlen($thumbnail['filedata']);
			$thumbnail['dateline'] = vB::getRequest()->getTimeNow();
		}
		return $thumbnail;
	}

	/**
	* See function definition in vB_Image
	*/
	public function getImageFromString($string, $moveabout = true)
	{
		if ($this->options['safeupload'])
		{
			$tmpname = $this->options['tmppath'] . '/' . md5(uniqid(microtime()) . vB::getCurrentSession()->get('userid'));
		}
		else
		{
			if (!($tmpname = @tempnam(ini_get('upload_tmp_dir'), 'vb')))
			{
				throw new vB_Exception_Api('temp_file_create_error');
			}
		}

		// Command start for no background image
		$execute = ' -size 201x61 xc:white ';

		$fonts = $this->fetchRegimageFonts();
		if ($moveabout)
		{
			$backgrounds = $this->fetchRegimageBackgrounds();

			if (!empty($backgrounds))
			{
				$index = mt_rand(0, count($backgrounds) - 1);
				$background = $backgrounds["$index"];

				// replace Command start with background image
				$execute = " \"$background\" -resize 201x61! -swirl " . mt_rand(10, 100);

				// randomly rotate the background image 180 degrees
				$execute .= (vB::getRequest()->getTimeNow() & 2) ? ' -rotate 180 ' : '';
			}

			// Randomly move the letters up and down
			for ($x = 0; $x < strlen($string); $x++)
			{
				if (!empty($fonts))
				{
					$index = mt_rand(0, count($fonts) - 1);
					if ($this->regimageoption['randomfont'])
					{
						$font = $fonts["$index"];
					}
					else
					{
						if (!$font)
						{
							$font = $fonts["$index"];
						}
					}
				}
				else
				{
					$font = 'Helvetica';
				}

				if ($this->regimageoption['randomshape'])
					{
					// Stroke Width, 1 or 2
					$strokewidth = mt_rand(1, 2);
					// Pick a random color
					$r = mt_rand(50, 200);
					$b = mt_rand(50, 200);
					$g = mt_rand(50, 200);
					// Pick a Shape

					$x1 = mt_rand(0, 200);
					$y1 = mt_rand(0, 60);
					$x2 = mt_rand(0, 200);
					$y2 = mt_rand(0, 60);
					$start = mt_rand(0, 360);
					$end = mt_rand(0, 360);
					switch(mt_rand(1, 5))
					{
						case 1:
							$shape = "\"roundrectangle $x1,$y1 $x2,$y2 $start,end\"";
							break;
						case 2:
							$shape = "\"arc $x1,$y1 $x2,$y2 20,15\"";
							break;
						case 3:
							$shape = "\"ellipse $x1,$y1 $x2,$y2 $start,$end\"";
							break;
						case 4:
							$shape = "\"line $x1,$y1 $x2,$y2\"";
							break;
						case 5:
							$x3 = mt_rand(0, 200);
							$y3 = mt_rand(0, 60);
							$x4 = mt_rand(0, 200);
							$y4 = mt_rand(0, 60);
							$shape = "\"polygon $x1,$y1 $x2,$y2 $x3,$y3 $x4,$y4\"";
							break;
					}
					// before or after
					$place = mt_rand(1, 2);

					$finalshape = " -flatten -stroke \"rgb($r,$b,$g)\" -strokewidth $strokewidth -fill none -draw $shape -stroke none ";

					if ($place == 1)
					{
						$execute .= $finalshape;
					}
				}

				$slant = (($x <= 1 OR $x == 5) AND $this->regimageoption['randomslant']) ? true : false;
				$execute .= $this->annotate($string["$x"], $font, $slant, true);

				if ($this->regimageoption['randomshape'] AND $place == 2)
				{
					$execute .= $finalshape;
				}
			}
		}
		else
		{
			if (!empty($fonts))
			{
				$font = $fonts[0];
			}
			else
			{
				$font = 'Helvetica';
			}
			$execute .= $this->annotate("\"$string\"", $font, false, false);
		}

		// Swirl text, stroke inner border of 1 pixel and output as GIF
		$execute .= ' -flatten ';

		$execute .= ($moveabout AND $this->regimageoption['randomslant']) ? ' -swirl 20 ' : '';
		$execute .= " -stroke black -strokewidth 1 -fill none -draw \"rectangle 0,60 200,0\" -depth 8 PNG:\"$tmpname\"";

		if ($result = $this->fetchImExec('convert', $execute))
		{
			$filedata = @file_get_contents($tmpname);
			$fileSize = 0;
			if ($tmpSize = @filesize($tmpname))
			{
				$fileSize = $tmpSize;
			}
			else
			{
				$fileSize = strlen($filedata);
			}

			@unlink($tmpname);

			// return imageinfo
			return array('filedata' => $filedata, 'filetype' => 'png', 'filesize' => $fileSize, 'contentType' => 'image/png');
		}
		else
		{
			@unlink($tmpname);
			return false;
		}
	}

	/**
	*
	* Return a letter position command
	*
	* @param	string	letter	Character to position
	*
	* @return	string
	*/
	protected function annotate($letter, $font, $slant = false, $random = true)
	{
		// Start position
		static $r, $g, $b, $position = 10;

		// Character Slant
		static $slants = array(
			'0x0',     # Normal
			'0x30',    # Slant Right
			'20x20',   # Slant Down
			'315x315', # Slant Up
			'45x45',
			'0x330',
		);

		// Can't use slants AND swirl at the same time, it just looks bad ;)
		if ($slant)
		{
			$coord = mt_rand(1, count($slants) - 1);
			$coord = $slants["$coord"];
		}
		else
		{
			$coord = $slants[0];
		}

		if ($random)
		{
			// Y Axis position, random from 32 to 48
			$y = mt_rand(32, 48);

			if ($this->regimageoption['randomcolor'] OR empty($r))
			{
				// Generate a random color..
				$r = mt_rand(50, 200);
				$b = mt_rand(50, 200);
				$g = mt_rand(50, 200);
			}

			$pointsize = $this->regimageoption['randomsize'] ? mt_rand(28, 36) : 32;
		}
		else
		{
			$y = 40;
			$pointsize = 32;
			$r = $b = $g = 0;
		}

		$output = " -font \"$font\" -pointsize $pointsize -fill \"rgb($r,$b,$g)\" -annotate $coord+$position+$y $letter ";
		$position += rand(25, 35);

		return $output;

	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 58687 $
|| ####################################################################
\*======================================================================*/
