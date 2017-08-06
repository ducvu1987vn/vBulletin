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
* Class to do data save/delete operations for StyleVarDefinitions.
*
* @package	vBulletin
* @version	$Revision: 34206 $
* @date		$Date: 2009-12-09 00:12:21 -0200 (Wed, 09 Dec 2009) $
*/

class vB_DataManager_StyleVarDefn extends vB_DataManager
{
	/**
	* Array of recognized and required fields for attachment inserts
	*
	* @var	array
	*/
	var $validfields = array(
		'stylevarid'    => array(vB_Cleaner::TYPE_STR,      vB_DataManager_Constants::REQ_YES),
		'styleid'       => array(vB_Cleaner::TYPE_INT,      vB_DataManager_Constants::REQ_NO,   'if ($data < -1) { $data = 0; } return true;'),
		'parentid'      => array(vB_Cleaner::TYPE_INT,      vB_DataManager_Constants::REQ_YES,  vB_DataManager_Constants::VF_METHOD),
		// 'parentlist'    => array(vB_Cleaner::TYPE_STR,      vB_DataManager_Constants::REQ_AUTO, 'return preg_match(\'#^(\d+,)*-1$#\', $data);'),
		'parentlist'    => array(vB_Cleaner::TYPE_STR,      vB_DataManager_Constants::REQ_NO),
		'stylevargroup' => array(vB_Cleaner::TYPE_STR,		vB_DataManager_Constants::REQ_YES),
		'product'       => array(vB_Cleaner::TYPE_STR,		vB_DataManager_Constants::REQ_YES,  vB_DataManager_Constants::VF_METHOD),
		'datatype'      => array(vB_Cleaner::TYPE_STR,		vB_DataManager_Constants::REQ_YES,  vB_DataManager_Constants::VF_METHOD),
		'validation'    => array(vB_Cleaner::TYPE_STR,		vB_DataManager_Constants::REQ_NO),
		'failsafe'      => array(vB_Cleaner::TYPE_STR,		vB_DataManager_Constants::REQ_NO),
		'units'         => array(vB_Cleaner::TYPE_STR,		vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'uneditable'    => array(vB_Cleaner::TYPE_BOOL,		vB_DataManager_Constants::REQ_YES),
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	* For example: var $bitfields = array('options' => 'bf_misc_useroptions', 'permissions' => 'bf_misc_moderatorpermissions')
	*
	* @var	array
	*/
	var $bitfields = array();

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'vBForum:stylevardfn';

	/**
	* Condition template for update query
	*
	* @var	array
	*/
	var $condition_construct = array('stylevarid = "%1$s"', 'stylevarid');

	protected $datatype = 'Custom';

	protected $keyField = 'stylevarid';

	/**
	 * Constructor - Checks for necessity of registry object
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	public function __construct(&$registry = NULL, $errtype  = NULL)
	{
		parent::vB_DataManager($registry, $errtype);

		// Legacy Hook 'stylevardfndata_start' Removed //
	}

	/**
	* Verifies that the parent style specified exists and is a valid parent for this style
	*
	* @param	integer	Parent style ID
	*
	* @return	boolean	Returns true if the parent id is valid, and the parent style specified exists
	*/
	public function verify_parentid(&$parentid)
	{
		if ($parentid == $this->fetch_field('styleid'))
		{
			$this->error('cant_parent_style_to_self');
			return false;
		}
		else if ($parentid <= 0)
		{
			$parentid = -1;
			return true;
		}
		$stylecache = vB_Library::instance('Style')->fetchStyles(false, false);
		if (!isset($stylecache["$parentid"]))
		{
			$this->error('invalid_style_specified');
			return false;
		}
		else if ($this->condition !== null)
		{
			return $this->is_substyle_of($this->fetch_field('styleid'), $parentid);
		}
		else
		{
			// no condition specified, so it's not an existing style...
			return true;
		}
	}

	/**
	* Verifies that a given style parent id is not one of its own children
	*
	* @param	integer	The ID of the current style
	* @param	integer	The ID of the style's proposed parentid
	*
	* @return	boolean	Returns true if the children of the given parent style does not include the specified style... or something
	*/
	public function is_substyle_of($styleid, $parentid)
	{
		$stylecache = vB_Library::instance('Style')->fetchStyles(false, false);
		// TODO: TEST THIS FUNCTION!!!  Coded w/o testing or reference
		while (is_array($stylecache["$styleid"]))
		{
			$curstyle = $stylecache["$styleid"];
			if ($curstyle['parentid'] == $parentid)
			{
				return true;
			}
			if ($curstyle['parentid'] == -1)
			{
				break;
			}
			$styleid = $curstyle['parentid'];
		}
		$this->error('cant_parent_style_to_child');
		return false;
	}


	public function verify_product($product)
	{
		// check if longer than 25 chars, contains anything other than a-zA-Z1-0
		return (preg_match('#^[a-z0-9_]+$#s', $product) AND strlen($product) < 25);
	}

	public function verify_datatype($datatype)
	{
		$valid_datatypes = array('numeric', 'string', 'color', 'url', 'path', 'background', 'imagedir', 'fontlist', 'textdecoration', 'dimension', 'border', 'padding', 'margin', 'font', 'size');
		return in_array($datatype, $valid_datatypes);
	}

	public function verify_units($unit)
	{
		$valid_units = array('', '%', 'px', 'pt', 'em', 'ex', 'pc', 'in', 'cm', 'mm');
		return in_array($unit, $valid_units);
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	public function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$return_value = true;
		// Legacy Hook 'stylevardfndata_presave' Removed //

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	public function post_save_each($doquery = true)
	{
		// Legacy Hook 'stylevardfndata_postsave' Removed //
	}

	/**
	* Deletes a stylevardfn and its associated data from the database
	*/
	public function delete()
	{
		// fetch list of stylevars to delete
		$stylevardfnlist = '';

		$stylevardfns = $this->dbobject->query_read_slave("SELECT stylevarid FROM " . TABLE_PREFIX . "stylevardfn WHERE " . $this->condition);
		while($thisdfn = $this->dbobject->fetch_array($stylevardfns))
		{
			$stylevardfnlist .= ',' . $thisdfn['stylevarid'];
		}
		$this->dbobject->free_result($stylevardfns);

		$stylevardfnlist = substr($stylevardfnlist, 1);

		if ($stylevardfnlist == '')
		{
			// nothing to do
			$this->error('invalid_stylevardfn_specified');
		}
		else
		{
			$condition = "stylevarid IN ($stylevardfnlist)";

			// delete from data tables
			$this->db_delete(TABLE_PREFIX, 'stylevar', $condition);
			$this->db_delete(TABLE_PREFIX, 'stylevardfn', $condition);

			// Legacy Hook 'stylevardfndata_delete' Removed //
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/