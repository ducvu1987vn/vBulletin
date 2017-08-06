<?php
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
*  vB_DataManager_Avatar
*  vB_DataManager_ProfilePic
* Abstract class to do data save/delete operations for Userpics.
* You should call the fetch_library() function to instantiate the correct
* object based on how userpics are being stored.
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 16:38:49 -0200 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_Userpic extends vB_DataManager
{
	/**
	* Array of recognized and required fields for avatar inserts
	*
	* @var	array
	*/
	var $validfields = array(
		'userid'   => array(vB_Cleaner::TYPE_UINT,     vB_DataManager_Constants::REQ_YES),
		'filedata' => array(vB_Cleaner::TYPE_BINARY,   vB_DataManager_Constants::REQ_NO, vB_DataManager_Constants::VF_METHOD),
		'dateline' => array(vB_Cleaner::TYPE_UNIXTIME, vB_DataManager_Constants::REQ_AUTO),
		'filename' => array(vB_Cleaner::TYPE_STR,      vB_DataManager_Constants::REQ_YES),
		'visible'  => array(vB_Cleaner::TYPE_UINT,     vB_DataManager_Constants::REQ_NO),
		'filesize' => array(vB_Cleaner::TYPE_UINT,     vB_DataManager_Constants::REQ_YES),
		'width'    => array(vB_Cleaner::TYPE_UINT,     vB_DataManager_Constants::REQ_NO),
		'height'   => array(vB_Cleaner::TYPE_UINT,     vB_DataManager_Constants::REQ_NO),
		'filedata_thumb' => array(vB_Cleaner::TYPE_BINARY, vB_DataManager_Constants::REQ_NO,),
		'width_thumb'    => array(vB_Cleaner::TYPE_UINT, vB_DataManager_Constants::REQ_NO),
		'height_thumb'   => array(vB_Cleaner::TYPE_UINT, vB_DataManager_Constants::REQ_NO),
		'extension'      => array(vB_Cleaner::TYPE_STR, vB_DataManager_Constants::REQ_YES),
	);

	/**
	*
	* @var	string  The main table this class deals with
	*/
	var $table = 'vBForum:customavatar';

	/**
	* Revision field to update
	*
	* @var	string
	*/
	var $revision = 'avatarrevision';

	/**
	* Path to image directory
	*
	* @var	string
	*/
	var $filepath = 'customavatars';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('userid = %1$d', 'userid');

	/** instance of class vB_Image- does image functions**/
	protected $imageHandler;

	/**
	* Fetches the appropriate subclass based on how the userpics are being stored.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*
	* @return	vB_DataManager_Userpic	Subclass of vB_DataManager_Userpic
	*/
	public static function &fetch_library(&$registry, $errtype = vB_DataManager_Constants::ERRTYPE_STANDARD, $classtype = 'userpic_avatar', $usefilesystem = false)
	{
		$options =& vB::getDatastore()->get_value('options');

		if ($options['usefileavatar'] OR $usefilesystem)
		{
			$newclass = new vB_DataManager_Userpic_Filesystem($registry, $errtype);
			list($prefix, $type) = explode('_', $classtype);
			$newclass->setStorageOptions($type);
		}
		else
		{
			$class = 'vB_DataManager_' . $classtype;
			$newclass = new $class($registry, $errtype);
		}

		return $newclass;
	}

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Userpic(&$registry, $errtype = vB_DataManager_Constants::ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);
	}

	/**
	* Set the filehash/filesize of the file
	*
	* @param	integer	Maximum posts per page
	*
	* @return	boolean
	*/
	function verify_filedata(&$filedata)
	{
		if (strlen($filedata) > 0)
		{
			$this->set('filesize', strlen($filedata));
		}

		return true;
	}

	/**
	* Any code to run before deleting.
	*
	* @param	Boolean Do the query?
	*/
	function pre_delete($doquery = true)
	{
		@ignore_user_abort(true);

		return true;
	}

	/**
	*
	*
	*
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->condition)
		{
			// Check if we need to insert or overwrite this image.
			if ($this->fetch_field('userid') AND $this->assertor->getRow($this->table, array('userid' => $this->fetch_field('userid'))))
			{
				$this->condition['userid'] = $this->fetch_field('userid');
			}
		}

		// Store in database
		$table = $this->fetchTableBase($this->table);
		if ($table == 'customavatar' AND $this->fetch_field('filedata') AND !$this->fetch_field('filedata_thumb') AND !$this->options['usefileavatar'])
		{
			if (empty($this->imageHandler))
			{
				$this->imageHandler = vB_Image::instance();
			}

			if ($this->options['safeupload'])
			{
				$filename = $this->options['tmppath'] . '/' . md5(uniqid(microtime()) . $this->fetch_field('userid'));
			}
			else
			{
				$filename = tempnam(ini_get('upload_tmp_dir'), 'vbthumb');
			}

			$filenum = @fopen($filename, 'wb');
			@fwrite($filenum, $this->fetch_field('filedata'));
			@fclose($filenum);
			$imageinfo = $this->imageHandler->fetchImageInfo($filename);
			if (!$this->fetch_field('width') OR !$this->fetch_field('height'))
			{

				if ($imageinfo)
				{
					$this->set('width', $imageinfo[0]);
					$this->set('height', $imageinfo[1]);
				}
			}

			$thumbnail = $this->fetch_thumbnail($filename, false, $imageinfo);

			$this->deleteFile($filename);

			if ($thumbnail['filedata'])
			{
				$this->set('width_thumb', $thumbnail['width']);
				$this->set('height_thumb', $thumbnail['height']);
				$this->set('filedata_thumb', $thumbnail['filedata']);
				unset($thumbnail);
			}
			else
			{
				$this->set('width_thumb', 0);
				$this->set('height_thumb', 0);
				$this->set('filedata_thumb', '');
			}
		}

		$return_value = true;

		$this->presave_called = $return_value;
		return $return_value;
	}

	function post_save_each($doquery = true)
	{
		return parent::post_save_each($doquery);
	}

	function post_delete($doquery = true)
	{
		return parent::post_delete($doquery);
	}

	function fetch_thumbnail($file, $forceimage = false, $imageinfo = false)
	{
		if (empty($imageinfo))
		{

			if (empty($this->imageHandler))
			{
				$this->imageHandler = vB_Image::instance();
			}

			$imageinfo = $this->imageHandler->fetchImageInfo ($file);
		}

		if ($imageinfo[0] > FIXED_SIZE_AVATAR_WIDTH OR $imageinfo[1] > FIXED_SIZE_AVATAR_HEIGHT)
		{
			$filename = 'file.' . ($imageinfo[2] == 'JPEG' ? 'jpg' : strtolower($imageinfo[2]));
			$thumbnail = $this->imageHandler->fetchThumbnail($filename, $file, FIXED_SIZE_AVATAR_WIDTH, FIXED_SIZE_AVATAR_HEIGHT);
			if ($thumbnail['filedata'])
			{
				return $thumbnail;
			}
		}

		return array(
			'filedata' => @file_get_contents($file),
			'width'    => $imageinfo[0],
			'height'   => $imageinfo[1],
		);
	}

	public function setStorageOptions($type = 'avatar')
	{
		if ($type == 'avatar')
		{
			$this->table = 'vBForum:customavatar';
			$this->revision = 'avatarrevision';
			$this->filepath = realpath($this->options['avatarpath']);

			if (!$this->filepath)
			{
				$this->filepath = realpath(DIR . DIRECTORY_SEPARATOR . $this->options['avatarpath']);
			}
		}
		else if ($type == 'profilepic')
		{
			$this->table = 'vBForum:customprofilepic';
			$this->revision = 'profilepicrevision';
			$this->filepath = realpath($this->options['profilepicpath']);

			if (!$this->filepath)
			{
				$this->filepath = realpath(DIR . DIRECTORY_SEPARATOR . $this->options['profilepicpath']);
			}
		}
		else if ($type == 'sigpic')
		{
			$this->table = 'vBForum:sigpic';
			$this->revision = 'sigpicrevision';
			$this->filepath = realpath($this->options['sigpicpath']);

			if (!$this->filepath)
			{
				$this->filepath = realpath(DIR . DIRECTORY_SEPARATOR . $this->options['sigpicpath']);
			}
		}
		else
		{
			// Should never happen
			$this->errors[] = 'Storage type error';
			return false;
		}

		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
