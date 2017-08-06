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

// @TODO VBV-3739 file only being used in upgrader earlier than vB4 due attachment filedata record imports
/**
* Class to do data save/delete operations for Attachment/Filedata table
*
* @package	vBulletin
* @version	$Revision: 56630 $
* @date		$Date: 2011-12-13 17:14:05 -0700 (Tue, 13 Dec 2011) $
*/
class vB_DataManager_AttachmentFiledata extends vB_DataManager_Attachment
{
	var $validfields = array(
		//attachment fields
		'attachmentid'       => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_INCR),
		'state'              => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_NO),
		'counter'            => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'posthash'           => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD, 'verify_md5_alt'),
		'contenttypeid'      => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_YES),
		'contentid'          => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'caption'            => array(vB_Cleaner::TYPE_NOHTMLCOND, vB_DataManager_Constants::REQ_NO),
		'reportthreadid'     => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'displayorder'       => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),

		// Shared fields
		'userid'             => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_YES),
		'dateline'           => array(vB_Cleaner::TYPE_UNIXTIME,   vB_DataManager_Constants::REQ_AUTO),
		'filename'           => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_YES,  vB_DataManager_Constants::VF_METHOD, 'verify_filename'),

		// filedata fields
		'filedata'           => array(vB_Cleaner::TYPE_BINARY,     vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'filesize'           => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_YES),
		'filehash'           => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_YES,  vB_DataManager_Constants::VF_METHOD, 'verify_md5'),
		'thumbnail'          => array(vB_Cleaner::TYPE_BINARY,     vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'thumbnail_dateline' => array(vB_Cleaner::TYPE_UNIXTIME,   vB_DataManager_Constants::REQ_AUTO),
		'thumbnail_filesize' => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'extension'          => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_YES),
		'refcount'           => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'width'              => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'height'             => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'thumbnail_width'    => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'thumbnail_height'   => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
	);

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	public function __construct(&$registry, $errtype = vB_DataManager_Constants::ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);

		// @TODO attachdata_start hook goes here
	}

	/**
	* Takes valid data and sets it as part of the data to be saved
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	*/
	function do_set($fieldname, &$value, $table = null)
	{
		$this->setfields["$fieldname"] = true;

		$tables = array();

		switch ($fieldname)
		{
			case 'userid':
			case 'dateline' :
			{
				$tables = array('attachment', 'filedata');
			}
			break;

			case 'filedata':
			case 'filesize':
			case 'filehash':
			case 'thumbnail':
			case 'thumbnail_dateline':
			case 'thumbnail_filesize':
			case 'extension':
			case 'refcount':
			case 'width':
			case 'height':
			case 'thumbnail_width':
			case 'thumbnail_height':
			{
				$tables = array('filedata');
			}
			break;

			default:
			{
				$tables = array('attachment');
			}
		}

		// @TODO attachdata_doset hook goes here

		foreach ($tables AS $table)
		{
			$this->{$table}["$fieldname"] =& $value;
		}
	}

	/**
	* Saves attachment to the database
	*
	* @return	mixed
	*/
	public function save($doquery = true)
	{
		if ($this->has_errors())
		{
			return false;
		}

		if (!$this->pre_save($$doquery))
		{
			return 0;
		}

		$insertfiledata = true;
		if ($filedataid = $this->pre_save_filedata($doquery))
		{
			if (!$filedataid)
			{
				return false;
			}

			if ($filedataid !== true)
			{
				// this is an insert and file already exists
				// Check if file is already attached to this content
				if ($info = $this->registry->db->query_first(
					($this->info['contentid'] ? "(" : "") .
						"SELECT filedataid, attachmentid
						FROM " . TABLE_PREFIX . "attachment
						WHERE
							filedataid = " . intval($filedataid) . "
								AND
							posthash = '" . $this->registry->db->escape_string($this->fetch_field('posthash')) . "'
								AND
							contentid = 0
					" . ($this->info['contentid'] ? ") UNION (" : "") . "
					" . ($this->info['contentid'] ? "
						SELECT filedataid, attachmentid
						FROM " . TABLE_PREFIX . "attachment
						WHERE
							filedataid = " . intval($filedataid) . "
								AND
							contentid = " . intval($this->info['contentid']) . "
								AND
							contenttypeid = " . intval($this->fetch_field('contenttypeid')) . "
					)
						" : "") . "
				"))
				{
					// really just do nothing since this file is already attached to this content
					return $info['attachmentid'];
				}

				$this->attachment['filedataid'] = $filedataid;
				$insertfiledata = false;
				unset($this->validfields['filedata'], $this->validfields['filesize'],
					$this->validfields['filehash'], $this->validfields['thumbnail'], $this->validfields['thumbnail_dateline'],
					$this->validfields['thumbnail_filesize'], $this->validfields['extension'], $this->validfields['refcount']
				);
			}
		}

		if ($this->condition)
		{
			$return = $this->db_update(TABLE_PREFIX, 'thread', $this->condition, $doquery);
			if ($return)
			{
				$this->db_update(TABLE_PREFIX, 'filedataid', 'filedataid = ' . $this->fetch_field('filedataid'), $doquery);
			}
		}
		else
		{
			// insert query
			$return = $this->attachment['attachmentid'] = $this->db_insert(TABLE_PREFIX, 'vBInstall:attachment', $doquery);

			if ($return)
			{
				$this->do_set('attachmentid', $return);

				if ($insertfiledata)
				{
					$filedataid = $this->filedata['filedataid'] = $this->attachment['filedataid'] = $this->db_insert(TABLE_PREFIX, 'filedata', $doquery);
				}
				if ($doquery)
				{
					$this->registry->db->query_write("UPDATE " . TABLE_PREFIX . "attachment SET filedataid = $filedataid WHERE attachmentid = $return");
					// Verify categoryid
					if (!intval($this->info['categoryid']) OR $this->registry->db->query_first("
						SELECT categoryid
						FROM " . TABLE_PREFIX . "attachmentcategory
						WHERE
							userid = " . $this->fetch_field('userid') . "
								AND
							categoryid = " . intval($this->info['categoryid']) . "
					"))
					{
						$this->registry->db->query_write("
							INSERT IGNORE INTO " . TABLE_PREFIX . "attachmentcategoryuser
								(userid, filedataid, categoryid, filename, dateline)
							VALUES
								(" . $this->fetch_field('userid') . ", $filedataid, " . intval($this->info['categoryid']) . ", '" . $this->registry->db->escape_string($this->fetch_field('filename')) . "', " . TIMENOW . ")
						");
					}
				}
			}
		}

		if ($return)
		{
			$this->post_save_each($doquery);
			$this->post_save_once($doquery);
			if ($insertfiledata)
			{
				$this->post_save_each_filedata($doquery);
			}
		}

		return $return;
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/