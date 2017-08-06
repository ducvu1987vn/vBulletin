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
* Class to do data save/delete operations for just the Attachment table
*
* @package	vBulletin
* @version	$Revision: 56630 $
* @date		$Date: 2011-12-13 17:14:05 -0700 (Tue, 13 Dec 2011) $
*/

class vB_DataManager_Attachment extends vB_DataManager_AttachData
{
	/**
	* Array of recognized and required fields for attachment inserts
	*
	* @var	array
	*/
	var $validfields = array(
		'attachmentid'   => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_INCR),
		'filedataid'     => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_YES),
		'userid'         => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_YES),
		'filename'       => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_YES,  vB_DataManager_Constants::VF_METHOD, 'verify_filename'),
		'dateline'       => array(vB_Cleaner::TYPE_UNIXTIME,   vB_DataManager_Constants::REQ_AUTO),
		'state'          => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_NO),
		'counter'        => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'posthash'       => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD, 'verify_md5_alt'),
		'contenttypeid'  => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_YES),
		'contentid'      => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'caption'        => array(vB_Cleaner::TYPE_NOHTMLCOND, vB_DataManager_Constants::REQ_NO),
		'reportthreadid' => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'displayorder'   => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'attachment';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('attachmentid = %1$d', 'attachmentid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	public function __construct(&$registry, $errtype = vB_DataManager_Constants::ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);

		//($hook = vBulletinHook::fetch_hook('attachdata_start')) ? eval($hook) : false;
	}

	public function pre_delete($doquery = true, $checkperms = true)
	{
		return parent::pre_delete('attachment', $doquery, $checkperms);
	}

	/**
	* Delete from the attachment table
	*
	*/
	public function delete($doquery = true, $checkperms = true)
	{
		if (!$this->pre_delete($doquery, $checkperms) OR empty($this->lists['attachmentids']))
		{
			return false;
		}

		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "attachment
			WHERE attachmentid IN (" . implode(", ", $this->lists['attachmentids']) . ")
		");

		$this->post_delete($doquery);

		return true;
	}

	/**
	* Any code to run before approving
	*
	* @param	bool	Verify permissions
	*/
	function pre_moderate($checkperms = true, $type = 'approve')
	{
		$this->lists['content'] = array();
		$this->lists['attachmentids'] = array();
		$this->lists['userids'] = array();

		$ids = $this->registry->db->query_read("
			SELECT a.attachmentid, a.userid AS auserid, a.contenttypeid
			FROM " . TABLE_PREFIX . "attachment AS a
			WHERE " . $this->condition . "
		");
		while ($id = $this->registry->db->fetch_array($ids))
		{
			if ($id['attachmentid'])
			{
				$this->lists['content']["$id[contenttypeid]"][] = $id['attachmentid'];
				$this->lists['attachmentids'][] = $id['attachmentid'];
				$this->lists['userids']["$id[auserid]"] = 1;
			}
		}

		require_once(DIR . '/packages/vbattach/attach.php');
		if ($this->registry->db->num_rows($ids) == 0)
		{	// nothing to approve
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
				if ($type == 'approve')
				{
					if (!$attach->pre_approve($list, $checkperms, $this))
					{
						return false;
					}
				}
				else
				{
					if (!$attach->pre_unapprove($list, $checkperms, $this))
					{
						return false;
					}
				}
				unset($attach);
			}
		}

		return true;
	}

	/**
	* Approve in the attachment table
	*
	*/
	public function approve($checkperms = true)
	{
		if (!$this->pre_moderate($checkperms, 'approve') OR empty($this->lists['attachmentids']))
		{
			return false;
		}

		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "attachment
			SET state = 'visible'
			WHERE attachmentid IN (" . implode(", ", $this->lists['attachmentids']) . ")
		");

		$this->post_moderate('approve');

		return true;
	}

	/**
	* Unapprove in the attachment table
	*
	*/
	public function unapprove($checkperms = true)
	{
		if (!$this->pre_moderate($checkperms, 'unapprove') OR empty($this->lists['attachmentids']))
		{
			return false;
		}

		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "attachment
			SET state = 'moderation'
			WHERE attachmentid IN (" . implode(", ", $this->lists['attachmentids']) . ")
		");

		$this->post_moderate('unapprove');

		return true;
	}

	/**
	* Any code to run after approving
	*
	*/
	function post_moderate($type = 'approve')
	{
		foreach ($this->lists['content'] AS $contenttypeid => $list)
		{
			if (!($attach =& vB_Attachment_Dm_Library::fetch_library($this->registry, $contenttypeid)))
			{
				return false;
			}
			if ($type == 'approve')
			{
				$attach->post_approve($this);
			}
			else
			{
				$attach->post_unapprove($this);
			}
			unset($attach);
		}

		return true;
	}

	/**
	* Saves the data from the object into the specified database tables
	* Overwrites parent
	*
 	* @param	boolean	Do the query?
	* @param	mixed	Whether to run the query now; see db_update() for more info
	* @param bool 	Whether to return the number of affected rows.
	* @param bool		Perform REPLACE INTO instead of INSERT
	* @param bool		Perfrom INSERT IGNORE instead of INSERT
	*
	* @return	mixed	If this was an INSERT query, the INSERT ID is returned
	*/
	function save($doquery = true, $delayed = false, $affected_rows = false, $replace = false, $ignore = false)
	{
		if ($this->has_errors())
		{
			return false;
		}

		if (!$this->pre_save($doquery))
		{
			return false;
		}

		if ($this->condition === null)
		{
			$return = $this->db_insert(TABLE_PREFIX, $this->table, $doquery);
			// If no displayorder is set then default displayorder to be order of attachment insertion
			if (!$this->fetch_field('displayorder'))
			{
				$this->registry->db->query_write("
					UPDATE " . TABLE_PREFIX . "attachment SET displayorder = $return WHERE attachmentid = $return
				");
			}
			$this->set('attachmentid', $return);
		}
		else
		{
			$return = $this->db_update(TABLE_PREFIX, $this->table, $this->condition, $doquery, $delayed, $affected_rows);
		}

		if ($return AND $this->post_save_each($doquery) AND $this->post_save_once($doquery))
		{
			return $return;
		}
		else
		{
			return false;
		}
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		if ($filedataid = intval($this->attachment['filedataid']) AND $this->condition === null)
		{
			// Update the refcount in the filedata table
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "filedata AS fd
				SET fd.refcount = (
					SELECT COUNT(*)
					FROM " . TABLE_PREFIX . "attachment AS a
					WHERE fd.filedataid = a.filedataid
					GROUP BY a.filedataid
				)
				WHERE fd.filedataid = $filedataid
			");
		}

		return parent::post_save_each($doquery);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/