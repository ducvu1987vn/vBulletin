<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Class to do data save/delete operations for ADMINISTRATORS
*
* The major purpose of this class is for managing admin permissions.
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 16:38:49 -0200 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_Admin extends vB_DataManager
{
	/**
	* Array of recognised and required fields for moderators, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'userid'           => array(vB_Cleaner::TYPE_UINT,   vB_DataManager_Constants::REQ_YES, vB_DataManager_Constants::VF_METHOD),
		'adminpermissions' => array(vB_Cleaner::TYPE_UINT,   vB_DataManager_Constants::REQ_NO),
		'navprefs'         => array(vB_Cleaner::TYPE_STR,    vB_DataManager_Constants::REQ_NO,  vB_DataManager_Constants::VF_METHOD),
		'cssprefs'         => array(vB_Cleaner::TYPE_STR,    vB_DataManager_Constants::REQ_NO,  vB_DataManager_Constants::VF_METHOD),
		'notes'            => array(vB_Cleaner::TYPE_NOHTML, vB_DataManager_Constants::REQ_NO),
		'dismissednews'    => array(vB_Cleaner::TYPE_NOCLEAN,vB_DataManager_Constants::REQ_NO,  vB_DataManager_Constants::VF_METHOD, 'verify_commalist'),
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array('adminpermissions' => 'bf_ugp_adminpermissions');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'vBForum:administrator';
	public $keyField = 'userid';

	/**
	* Arrays to store stuff to save to admin-related tables
	*
	* @var	array
	*/
	var $administrator = array();

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Admin($registry, $errtype = vB_DataManager_Constants::ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		// Legacy Hook 'admindata_start' Removed //
	}

	/**
	* Sets a bit in a bitfield
	*
	* @param	string	Name of the database bitfield (options, permissions etc.)
	* @param	string	Name of the bit within the bitfield (canview, canpost etc.)
	* @param	boolean	Whether the bit should be set or not
	*
	* @return	boolean
	*/
	function set_bitfield($fieldname, $bitname, $onoff)
	{
		// there are 2 permissions in the the admin permission bitfield that don't actually
		// belong in this table...
		if ($fieldname == 'adminpermissions' AND
			($bitname == 'ismoderator' OR
			$bitname == 'cancontrolpanel'))
		{
			return false;
		}

		return parent::set_bitfield($fieldname, $bitname, $onoff);
	}

	/**
	* Verifies that the navigation preferences are valid
	*
	* @param	string	Navigation preferences string in (<product>_<id>(,?))* format
	*
	* @return	boolean
	*/
	function verify_navprefs(&$navprefs)
	{
		if (!preg_match('#^(\w+_[0-9]+,?)*$#', $navprefs))
		{
			$navprefs = '';
		}
		return true;
	}

	/**
	* Sanitizes and verifies that the CP style directory specified is valid
	*
	* @param	string	Name of the CP style directory; do not include any path info
	*
	* @return	boolean
	*/
	function verify_cssprefs(&$cssprefs)
	{
		$cssprefs = basename($cssprefs);
		if (!@file_exists(CWD . "/cpstyles/$cssprefs/controlpanel.css"))
		{
			$cssprefs = '';
		}

		return true;
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$return_value = true;
		// Legacy Hook 'admindata_presave' Removed //

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		// Legacy Hook 'admindata_postsave' Removed //

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		// Legacy Hook 'admindata_delete' Removed //
		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/

