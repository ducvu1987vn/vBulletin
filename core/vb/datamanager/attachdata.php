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

// Temporary
require_once(DIR . '/includes/functions_file.php');

/**
* Abstract class to do data save/delete operations for ATTACHMENTS.
*
* @package	vBulletin
* @version	$Revision: 35688 $
* @date		$Date: 2010-03-04 21:40:16 -0200 (Thu, 04 Mar 2010) $
*/
abstract class vB_DataManager_AttachData extends vB_DataManager
{
	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array();

	/**
	* Storage holder
	*
	* @var  array   Storage Holder
	*/
	var $lists = array();

	/**
	* Storage Type
	*
	* @var  string
	*/
	var $storage = 'db';

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	public function __construct(&$registry, $errtype = vB_DataManager_Constants::ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		$this->storage = vB::getDatastore()->getOption('attachfile') ? 'fs' : 'db';

		//attachdata_start hook goes here
	}

	// Allows to override attachfile option (used in admincp/attachment.php)
	public function setStorage($attachfile)
	{
		$this->storage = (intval($attachfile)) ? 'fs' : 'db';
	}

	/**
	* Set the extension of the filename
	*
	* @param	filename
	*
	* @return	boolean
	*/
	function verify_filename(&$filename)
	{
		$ext_pos = strrpos($filename, '.');
		if ($ext_pos !== false)
		{
			$extension = substr($filename, $ext_pos + 1);
			// 100 (filename length in DB) - 1 (.) - length of extension
			$filename = substr($filename, 0, min(100 - 1 - strlen($extension), $ext_pos)) . ".$extension";
		}
		else
		{
			$extension = '';
		}

		if ($this->validfields['extension'])
		{
			$this->set('extension', strtolower($extension));
		}
		return true;
	}

	/**
	* Set the filesize of the thumbnail
	*
	* @param	integer	Maximum posts per page
	*
	* @return	boolean
	*/
	function verify_thumbnail(&$thumbnail)
	{
		if (strlen($thumbnail) > 0)
		{
			$this->set('thumbnail_filesize', strlen($thumbnail));
		}
		return true;
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
			$this->set('filehash', md5($filedata));
			$this->set('filesize', strlen($filedata));
		}

		return true;
	}

	/**
	* Verify that posthash is either md5 or empty
	* @param	string the md5
	*
	* @return	boolean
	*/
	function verify_md5_alt(&$md5)
	{
		return (empty($md5) OR (strlen($md5) == 32 AND preg_match('#^[a-f0-9]{32}$#', $md5)));
	}

	/**
	* database pre_save method that only applies to subclasses that have filedata fields
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save_filedata($doquery = true)
	{
		if ($this->condition === null)
		{
			if ($this->fetch_field('filehash', 'filedata'))
			{
				$filehash = $this->fetch_field('filehash', 'filedata');
			}
			else if (!empty($this->info['filedata_location']) AND file_exists($this->info['filedata_location']))
			{
				$filehash = md5(file_get_contents($this->info['filedata_location']));
			}
			else if (!empty($this->info['filedata']))
			{
				$filehash = md5($this->info['filedata']);
			}
			else if ($this->fetch_field('filedata', 'filedata'))
			{
				$filehash = md5($this->fetch_field('filedata', 'filedata'));
			}

			// Does filedata already exist?
			if ($filehash AND $fd = $this->registry->db->query_first("
				SELECT filedataid
				FROM " . TABLE_PREFIX . "filedata
				WHERE filehash = '" . $this->registry->db->escape_string($filehash) . "'
			"))
			{
				// file already exists so we are not going to insert a new one
				return $fd['filedataid'];
			}
		}

		if ($this->storage == 'db')
		{
			if (!empty($this->info['filedata_location']) AND file_exists($this->info['filedata_location']))
			{
				$this->set_info('filedata', file_get_contents($this->info['filedata_location']));
			}

			if (!empty($this->info['filedata']))
			{
				$this->setr('filedata', $this->info['filedata']);
			}

			if (!empty($this->info['thumbnail']))
			{
				$this->setr('thumbnail', $this->info['thumbnail']);
			}
		}
		else	// Saving in the filesystem
		{
			// make sure we don't have the binary data set
			// if so move it to an information field
			// benefit of this is that when we "move" files from DB to FS,
			// the filedata fields are not blanked in the database
			// during the update.
			if ($file =& $this->fetch_field('filedata', 'filedata'))
			{
				$this->setr_info('filedata', $file);
				$this->do_unset('filedata', 'filedata');
			}

			if ($thumb =& $this->fetch_field('thumbnail', 'filedata'))
			{
				$this->setr_info('thumbnail', $thumb);
				$this->do_unset('thumbnail', 'filedata');
			}

			if (!empty($this->info['filedata']))
			{
				$this->set('filehash', md5($this->info['filedata']), true, true, 'filedata');
				$this->set('filesize', strlen($this->info['filedata']), true, true, 'filedata');
			}
			else if (!empty($this->info['filedata_location']) AND file_exists($this->info['filedata_location']))
			{
				$this->set('filehash', md5_file($this->info['filedata_location']), true, true, 'filedata');
				$this->set('filesize', filesize($this->info['filedata_location']), true, true, 'filedata');
			}

			if (!empty($this->info['thumbnail']))
			{
				$this->set('thumbnail_filesize', strlen($this->info['thumbnail']), true, true, 'filedata');
			}

			if (!empty($this->info['filedata']) OR !empty($this->info['filedata_location']))
			{
				$path = $this->verify_attachment_path($this->fetch_field('userid', 'filedata'));
				if (!$path)
				{
					$this->error('attachpathfailed');
					return false;
				}

				if (!is_writable($path))
				{
					$this->error('upload_file_system_is_not_writable_path', htmlspecialchars($path));
					return false;
				}
			}
		}

		return true;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each_filedata($doquery = true)
	{
		if ($this->storage == 'fs')
		{
			$filedataid =& $this->fetch_field('filedataid', 'filedata');
			$userid =& $this->fetch_field('userid', 'filedata');
			$failed = false;

			// Check for filedata in an information field
			if (!empty($this->info['filedata']))
			{
				$filename = fetch_attachment_path($userid, $filedataid);
				if ($fp = fopen($filename, 'wb'))
				{
					if (!fwrite($fp, $this->info['filedata']))
					{
						$failed = true;
					}
					fclose($fp);

					#remove possible existing thumbnail in case no thumbnail is written in the next step.
					if (
						vB_Api::instanceInternal('filedata')->sanitizeFiletype(vB_Api_Filedata::SIZE_THUMB) == vB_Api_Filedata::SIZE_THUMB
							AND
						file_exists(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_THUMB)))
					{
						$this->deleteFile(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_THUMB));
					}
 					if (
						vB_Api::instanceInternal('filedata')->sanitizeFiletype(vB_Api_Filedata::SIZE_ICON) == vB_Api_Filedata::SIZE_ICON
							AND
						file_exists(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_ICON)))
					{
						$this->deleteFile(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_ICON));
					}
 					if (
						vB_Api::instanceInternal('filedata')->sanitizeFiletype(vB_Api_Filedata::SIZE_SMALL) == vB_Api_Filedata::SIZE_SMALL
							AND
						file_exists(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_SMALL)))
					{
						$this->deleteFile(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_SMALL));
					}
 					if (
						vB_Api::instanceInternal('filedata')->sanitizeFiletype(vB_Api_Filedata::SIZE_MEDIUM) == vB_Api_Filedata::SIZE_MEDIUM
							AND
						file_exists(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_MEDIUM)))
					{
						$this->deleteFile(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_MEDIUM));
					}
 					if (
						vB_Api::instanceInternal('filedata')->sanitizeFiletype(vB_Api_Filedata::SIZE_LARGE) == vB_Api_Filedata::SIZE_LARGE
							AND
						file_exists(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_LARGE)))
					{
						$this->deleteFile(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_LARGE));
					}
				}
				else
				{
					$failed = true;
				}
			}
			else if (!empty($this->info['filedata_location']))
			{
				$filename = fetch_attachment_path($userid, $filedataid);
				if (@rename($this->info['filedata_location'], $filename))
				{
					$mask = 0777 & ~umask();
					@chmod($filename, $mask);

					if (
						vB_Api::instanceInternal('filedata')->sanitizeFiletype(vB_Api_Filedata::SIZE_THUMB) == vB_Api_Filedata::SIZE_THUMB
							AND
						file_exists(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_THUMB)))
					{
						$this->deleteFile(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_THUMB));
					}
 					if (
						vB_Api::instanceInternal('filedata')->sanitizeFiletype(vB_Api_Filedata::SIZE_ICON) == vB_Api_Filedata::SIZE_ICON
							AND
						file_exists(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_ICON)))
					{
						$this->deleteFile(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_ICON));
					}
 					if (
						vB_Api::instanceInternal('filedata')->sanitizeFiletype(vB_Api_Filedata::SIZE_SMALL) == vB_Api_Filedata::SIZE_SMALL
							AND
						file_exists(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_SMALL)))
					{
						$this->deleteFile(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_SMALL));
					}
 					if (
						vB_Api::instanceInternal('filedata')->sanitizeFiletype(vB_Api_Filedata::SIZE_MEDIUM) == vB_Api_Filedata::SIZE_MEDIUM
							AND
						file_exists(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_MEDIUM)))
					{
						$this->deleteFile(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_MEDIUM));
					}
 					if (
						vB_Api::instanceInternal('filedata')->sanitizeFiletype(vB_Api_Filedata::SIZE_LARGE) == vB_Api_Filedata::SIZE_LARGE
							AND
						file_exists(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_LARGE)))
					{
						$this->deleteFile(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_LARGE));
					}
				}
				else
				{

					$failed = true;
				}
			}

			//attachdata_postsave hook goes here
			if ($failed)
			{
				if ($this->condition === null) // Insert, delete filedata
				{
					$this->registry->db->query_write("
						DELETE FROM " . TABLE_PREFIX . "filedata
						WHERE filedataid = $filedataid
					");
					$this->registry->db->query_write("
						DELETE FROM " . TABLE_PREFIX . "filedataresize
						WHERE filedataid = $filedataid
					");
					$this->registry->db->query_write("
						DELETE FROM " . TABLE_PREFIX . "attachmentcategoryuser
						WHERE filedataid = $filedataid
					");
				}

				// $php_errormsg is automatically set if track_vars is enabled
				$this->error('upload_copyfailed', htmlspecialchars_uni($php_errormsg), fetch_attachment_path($userid));
				return false;
			}
			else
			{
				return true;
			}
		}
	}

	/**
	* Any code to run before deleting.
	*
	* @param	string	What are we deleteing?
	*/
	function pre_delete($type = 'attachment', $doquery = true, $checkperms = true)
	{
		$this->lists['content'] = array();
		$this->lists['filedataids'] = array();
		$this->lists['attachmentids'] = array();
		$this->lists['picturecomments'] = array();
		$this->lists['userids'] = array();
		$this->set_info('type', $type);

		if ($type == 'filedata')
		{
			$ids = $this->registry->db->query_read("
				SELECT a.attachmentid, fd.userid, fd.filedataid, a.userid AS auserid, a.contenttypeid
				FROM " . TABLE_PREFIX . "filedata AS fd
				LEFT JOIN " . TABLE_PREFIX . "attachment AS a ON (a.filedataid = fd.filedataid)
				WHERE " . $this->condition
			);
		}
		else
		{
			$ids = $this->registry->db->query_read("
				SELECT a.attachmentid, fd.userid, fd.filedataid, a.userid AS auserid, a.contenttypeid
				FROM " . TABLE_PREFIX . "attachment AS a
				LEFT JOIN " . TABLE_PREFIX . "filedata AS fd ON (a.filedataid = fd.filedataid)
				WHERE " . $this->condition
			);
		}
		while ($id = $this->registry->db->fetch_array($ids))
		{
			if ($id['attachmentid'])
			{
				$this->lists['content']["$id[contenttypeid]"][] = $id['attachmentid'];
				$this->lists['attachmentids'][] = $id['attachmentid'];
				$this->lists['picturecomments'][] = "(filedataid = $id[filedataid] AND userid = $id[auserid])";
				$this->lists['userids']["$id[auserid]"] = 1;
			}
			if ($id['filedataid'])
			{
				$this->lists['filedataids']["$id[filedataid]"] = $id['userid'];
			}
		}

		require_once(DIR . '/packages/vbattach/attach.php');
		if ($this->registry->db->num_rows($ids) == 0)
		{	// nothing to delete
			return false;
		}
		else
		{
			foreach ($this->lists['content'] AS $contenttypeid => $list)
			{
				if (!($attach =& vB_Attachment_Dm_Library::fetch_library($this->registry, $contenttypeid)))
				{
					return false;
				}
				if (!$attach->pre_delete($list, $checkperms, $this))
				{
					return false;
				}
				unset($attach);
			}
		}

		return parent::pre_delete($doquery);
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		if ($contenttypeid = intval($this->fetch_field('contenttypeid')))
		{
			require_once(DIR . '/packages/vbattach/attach.php');
			if (!($attach =& vB_Attachment_Dm_Library::fetch_library($this->registry, $contenttypeid)))
			{
				return false;
			}
			$attach->post_save_each($this);
		}
		return parent::post_save_each($doquery);
	}

	/**
	* Any code to run after deleting
	*
	* @param	Boolean Do the query?
	*/
	function post_delete($doquery = true)
	{
		foreach ($this->lists['content'] AS $contenttypeid => $list)
		{
			if (!($attach =& vB_Attachment_Dm_Library::fetch_library($this->registry, $contenttypeid)))
			{
				return false;
			}
			$attach->post_delete($this);
			unset($attach);
		}
		// Update the refcount in the filedata table
		if (!empty($this->lists['filedataids']))
		{
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "filedata AS fd
				SET fd.refcount = (
					SELECT COUNT(*)
					FROM " . TABLE_PREFIX . "attachment AS a
					WHERE fd.filedataid = a.filedataid
				)
				WHERE fd.filedataid IN (" . implode(", ", array_keys($this->lists['filedataids'])) . ")
			");
		}
		// Hourly cron job will clean out the FS where refcount = 0 and dateline > 1 hour

		// Below here only applies to attachments in pictures/groups but I forsee all attachments gaining the ability to have comments
		if ($this->info['type'] == 'filedata')
		{
			if (!empty($this->lists['filedataids']))
			{
				$this->registry->db->query_write("
					DELETE FROM " . TABLE_PREFIX . "picturecomment
					WHERE filedataid IN (" . implode(", ", array_keys($this->lists['filedataids'])) . ")
				");
			}
		}
		else if (!empty($this->lists['picturecomments']))	// deletion type is by attachment
		{
			foreach ($this->lists['picturecomments'] AS $sql)
			{
				if (!($results = $this->registry->db->query_first("
					SELECT a.attachmentid
					FROM " . TABLE_PREFIX . "attachment AS a
					WHERE
						$sql
				")))
				{
					$this->registry->db->query_write("
						DELETE FROM " . TABLE_PREFIX . "picturecomment
						WHERE
							$sql
					");
				}
			}
		}

		require_once(DIR . '/includes/functions_picturecomment.php');
		foreach (array_keys($this->lists['userids']) AS $userid)
		{
			build_picture_comment_counters($userid);
		}

		return parent::post_delete($doquery);
	}

	/**
	* Verify that user's attach path exists, create if it doesn't
	*
	* @param	int		userid
	*/
	function verify_attachment_path($userid)
	{
		// Allow userid to be 0 since vB2 allowed guests to post attachments
		$userid = intval($userid);

		$path = fetch_attachment_path($userid);
		if (vB_Library_Functions::vbMkdir($path))
		{
			return $path;
		}
		else
		{
			return false;
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
