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
* Class to do data save/delete operations for just the Filedata table
*
* @package	vBulletin
* @version	$Revision: 35688 $
* @date		$Date: 2010-03-04 21:40:16 -0200 (Thu, 04 Mar 2010) $
*/
class vB_DataManager_Filedata extends vB_DataManager_AttachData
{
	/**
	* Array of recognized and required fields for attachment inserts
	*
	* @var	array
	*/
	var $validfields = array(
		'filedataid'         => array(vB_Cleaner::TYPE_UINT,     vB_DataManager_Constants::REQ_INCR),
		'userid'             => array(vB_Cleaner::TYPE_UINT,     vB_DataManager_Constants::REQ_YES),
		'dateline'           => array(vB_Cleaner::TYPE_UNIXTIME, vB_DataManager_Constants::REQ_AUTO),
		'filedata'           => array(vB_Cleaner::TYPE_BINARY,   vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'filesize'           => array(vB_Cleaner::TYPE_UINT,     vB_DataManager_Constants::REQ_YES),
		'filehash'           => array(vB_Cleaner::TYPE_STR,      vB_DataManager_Constants::REQ_YES,  vB_DataManager_Constants::VF_METHOD, 'verify_md5'),
		'extension'          => array(vB_Cleaner::TYPE_STR,      vB_DataManager_Constants::REQ_YES),
		'refcount'           => array(vB_Cleaner::TYPE_UINT,     vB_DataManager_Constants::REQ_NO),
		'width'              => array(vB_Cleaner::TYPE_UINT,     vB_DataManager_Constants::REQ_NO),
		'height'             => array(vB_Cleaner::TYPE_UINT,     vB_DataManager_Constants::REQ_NO),
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'vBForum:filedata';

	protected $keyField = 'filedataid';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('filedataid = %1$d', 'filedataid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	public function __construct(&$registry, $errtype = vB_DataManager_Constants::ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);

//		($hook = vBulletinHook::fetch_hook('attachdata_start')) ? eval($hook) : false;
	}

	public function pre_delete($doquery = true)
	{
		return parent::pre_delete('filedata', $doquery);
	}

	public function delete($doquery = true)
	{
		if (!$this->pre_delete($doquery) OR empty($this->lists['filedataids']))
		{
			return false;
		}

		if (!empty($this->lists['filedataids']))
		{
			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "filedata
				WHERE filedataid IN (" . implode(", ", array_keys($this->lists['filedataids'])) . ")
			");

			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "attachmentcategoryuser
				WHERE filedataid IN (" . implode(", ", array_keys($this->lists['filedataids'])) . ")
			");

			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "filedataresize
				WHERE filedataid IN (" . implode(", ", array_keys($this->lists['filedataids'])) . ")
			");

			if ($this->storage == 'fs')
			{
				require_once(DIR . '/includes/functions_file.php');
				foreach ($this->lists['filedataids'] AS $filedataid => $userid)
				{
					$this->deleteFile(fetch_attachment_path($userid, $filedataid));
					$this->deleteFile(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_THUMB));
					$this->deleteFile(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_ICON));
					$this->deleteFile(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_SMALL));
					$this->deleteFile(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_MEDIUM));
					$this->deleteFile(fetch_attachment_path($userid, $filedataid, vB_Api_Filedata::SIZE_LARGE));
				}
			}

			// unset filedataids so that the post_delete function doesn't bother calculating refcount for the records that we just removed
			unset($this->lists['filedataids']);
		}

		if (!empty($this->lists['attachmentids']))
		{
			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "attachment
				WHERE attachmentid IN (" . implode(", ", $this->lists['attachmentids']) . ")
			");
		}

		$this->post_delete();

		return true;
	}

	/**
	* Saves the data from the object into the specified database tables
	* Overwrites parent
	*
	* @return	mixed	If this was an INSERT query, the INSERT ID is returned
	*/
	function save($doquery = true, $delayed = false)
	{
		if ($this->has_errors())
		{
			return false;
		}

		if (!$this->pre_save($doquery))
		{
			return false;
		}

		if ($filedataid = $this->pre_save_filedata($doquery))
		{
			if (!$filedataid)
			{
				return false;
			}

			if ($filedataid !== true)
			{
				// this is an insert and file already exists
				$this->attachment['filedataid'] = $this->filedata['filedataid'] = $filedataid;

				$this->post_save_each($doquery);
				$this->post_save_once($doquery);

				// this is an insert and file already exists
				return $filedataid;
			}
		}

		if ($this->condition === null)
		{
			$return = $this->db_insert(TABLE_PREFIX, $this->table, $doquery);
			$this->set('filedataid', $return);
		}
		else
		{
			$return = $this->db_update(TABLE_PREFIX, $this->table, $this->condition, $doquery, $delayed);
		}

		if ($return AND $this->post_save_each($doquery) AND $this->post_save_once($doquery) AND $this->post_save_each_filedata($doquery))
		{
			return $return;
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
