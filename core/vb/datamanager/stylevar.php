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
* Abstract class to do data save/delete operations for StyleVar.
*
* @package	vBulletin
* @version	$Revision: 34206 $
* @date		$Date: 2009-12-09 00:12:21 -0200 (Wed, 09 Dec 2009) $
*/
class vB_DataManager_StyleVar extends vB_DataManager
{
	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	* For example: var $bitfields = array('options' => 'bf_misc_useroptions', 'permissions' => 'bf_misc_moderatorpermissions')
	*
	* @var	array
	*/
	protected $bitfields = array();

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	public $table = 'stylevar';

	/**
	* The name of the primary ID column that is used to uniquely identify records retrieved.
	* This will be used to build the condition in all update queries!
	*
	* @var string
	*/
	protected $primary_id = 'stylevarid';

	/**
	* Array of recognised and required fields for stylevar, and their types
	*
	* @var	array
	*/
	public $validfields = array(
		'stylevarid'		=> array(vB_Cleaner::TYPE_STR,			vB_DataManager_Constants::REQ_YES,	vB_DataManager_Constants::VF_METHOD, 'verify_stylevar'),
		'styleid'			=> array(vB_Cleaner::TYPE_INT,			vB_DataManager_Constants::REQ_YES,	'if ($data < -1) { $data = 0; } return true;'),
		'dateline'			=> array(vB_Cleaner::TYPE_UNIXTIME,		vB_DataManager_Constants::REQ_AUTO),
		'username'			=> array(vB_Cleaner::TYPE_STR,			vB_DataManager_Constants::REQ_NO),
		'value'				=> array(vB_Cleaner::TYPE_ARRAY_STR,	vB_DataManager_Constants::REQ_NO,   	vB_DataManager_Constants::VF_METHOD, 'verify_serialized'),
	);

	/**
	* Local storage, used to house data that we will be serializing into value
	*
	* @var  array
	*/
	protected $local_storage = array();
	protected $childvals = array();

	protected $keyField = array('stylevarid', 'styleid');

	/**
	* Local value telling us what datatype this is; saves the resources of gettype()
	*
	* @var  string
	*/
	public $datatype = '';

	/**
	* Condition template for update query
	*
	* @var	array
	*/
	var $condition_construct = array('stylevarid = "%1$s" AND styleid = %2$d', 'stylevarid', 'styleid');

	/** flag for vb5 transition. A subclass can set this to false and we won't set up $vbulletin **/
	protected $needRegistry = false;

	//cleaner
	protected $cleaner;

	/**
	 * Constructor - Checks for necessity of registry object
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	public function __construct(&$registry = NULL, $errtype  = NULL)
	{
		parent::vB_DataManager($registry, $errtype);
		$this->cleaner = vB::getCleaner();

		// Legacy Hook 'stylevardata_start' Removed //
	}


	//We need to rebuild the
	public function post_save_once($doquery = true)
	{
		parent::post_save_once($doquery);

		require_once DIR . '/includes/adminfunctions_template.php';
		//print_rebuild_style(-1, '', 0, 1, 1, 0, false);
		build_style(-1, '', array(
		'docss' => 0,
		'dostylevars' => 1,
		'doreplacements' => 0,
		'doposteditor' => 0) , '-1,1', '', false, false);
	}


	/**
	* database build method that builds the data into our value field
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	public function build()
	{
		// similar to check required, this verifies actual data for stylevar instead of the datamanager fields
		if (is_array($this->childfields)) {
			foreach ($this->childfields AS $fieldname => $validfield)
			{
				if ($validfield[vB_DataManager_Constants::VF_REQ] == vB_DataManager_Constants::REQ_YES AND !$this->local_storage["$fieldname"])
				{
					$this->error('required_field_x_missing_or_invalid', $fieldname);
					return false;
				}
			}
			$this->set('value', $this->childvals);
		}
		else
		{
			$this->set('value', array());
		}
		return true;
	}

	/**
	* Sets the supplied data to be part of the data to be build into value.
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	* @param	boolean	Clean data, or insert it RAW (used for non-arbitrary updates, like posts = posts + 1)
	* @param	boolean	Whether to verify the data with the appropriate function. Still cleans data if previous arg is true.
	* @param	string	Table name to force. Leave as null to use the default table
	*
	* @return	boolean	Returns false if the data is rejected for whatever reason
	*/
	public function set_child($fieldname, $value, $clean = true, $doverify = true, $table = null)
	{
		if ($clean)
		{
			$verify = $this->verify_child($fieldname, $value, $doverify);
			if ($verify === true)
			{
				$errsize = sizeof($this->errors);
				$this->do_set_child($fieldname, $value, $table);
				return true;
			}
			else
			{
				if ($this->childfields["$fieldname"][vB_DataManager_Constants::VF_REQ] AND $errsize == sizeof($this->errors))
				{
					$this->error('required_field_x_missing_or_invalid', $fieldname);
				}
				return $verify;
			}
		}
		else if (isset($this->childfields["$fieldname"]))
		{
			$this->local_storage["$fieldname"] = true;
			$this->do_set_child($fieldname, $value, $table);
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Verifies that the supplied child data is one of the fields used by this object
	*
	* Also ensures that the data is of the correct type,
	* and attempts to correct errors in the supplied data.
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	* @param	boolean	Whether to verify the data with the appropriate function. Data is still cleaned though.
	*
	* @return	boolean	Returns true if the data is one of the fields used by this object, and is the correct type (or has been successfully corrected to be so)
	*/
	public function verify_child($fieldname, &$value, $doverify = true)
	{
		if (isset($this->childfields["$fieldname"]))
		{
			$field =& $this->childfields["$fieldname"];

			// clean the value according to its type
			$value = $this->cleaner->clean($value, $field[vB_DataManager_Constants::VF_TYPE]);

			if ($doverify AND isset($field[vB_DataManager_Constants::VF_CODE]))
			{
				if ($field[vB_DataManager_Constants::VF_CODE] === vB_DataManager_Constants::VF_METHOD)
				{
					if (isset($field[vB_DataManager_Constants::VF_METHODNAME]))
					{
						return $this->{$field[vB_DataManager_Constants::VF_METHODNAME]}($value);
					}
					else
					{
						return $this->{'verify_' . $fieldname}($value);
					}
				}
				else
				{
					$lamdafunction = create_function('&$data, &$dm', $field[vB_DataManager_Constants::VF_CODE]);
					return $lamdafunction($value, $this);
				}
			}
			else
			{
				return true;
			}
		}
		else
		{
			trigger_error("Field <em>$fieldname</em> is not defined in <em>\$validfields</em> in class <strong>" . get_class($this) . "</strong>", E_USER_ERROR);
			return false;
		}
	}

	/**
	* Takes valid data and sets it as part of the child data to be saved
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed		The data itself
	* @param	string	Table name to force. Leave as null to use the default table
	*/
	public function do_set_child($fieldname, &$value, $table = null)
	{
		$this->local_storage["$fieldname"] = true;
		$this->childvals["$fieldname"] =& $value;
	}

	/**
	* Validation functions
	*/
	public function verify_stylevar($stylevarid)
	{
		// check if longer than 25 chars, contains anything other than a-zA-Z1-0
		$return = preg_match('#^[_a-z][a-z0-9_]*$#i', $stylevarid) ? true : false;
		return ($return);
	}

	public function verify_url($url)
	{
		// TODO: validate the URL
		// return true if it is a valid URL
		return true;
	}

	public function verify_color($color)
	{
		// TODO: validate the color
		// return true if it is a valid color
		return true;
	}

	public function verify_image($image)
	{
		// TODO: validate the image is an image -- just a string though?
		// return true if it is an image
		return true;
	}

	public function verify_repeat($repeat)
	{
		// TODO: validate if the repeat is one of the valid repeats
		// return true if it is a valid repeat
		$valid_repeat = array(
			'',
			'repeat',
			'repeat-x',
			'repeat-y',
			'no-repeat'
		);
		return in_array($repeat, $valid_repeat);
	}

	public function verify_fontfamily($family)
	{
		return true;
	}

	public function verify_fontweight($weight)
	{
		return true;
	}

	public function verify_fontstyle($style)
	{
		return true;
	}

	public function verify_fontvariant($variant)
	{
		return true;
	}

	public function verify_size($variant)
	{
		return true;
	}

	public function verify_width($width)
	{
		return true;
	}

	public function verify_height($height)
	{
		return true;
	}

	public function verify_fontlist($fontlist)
	{
		// TODO: validate fontlist is a list of fonts, with "'" wrapped around font names with spaces, and each font separated with a ",".
		return true;
	}

	public function verify_units($unit)
	{
		$valid_units = array('', '%', 'px', 'pt', 'em', 'ex', 'pc', 'in', 'cm', 'mm');
		return in_array($unit, $valid_units);
	}

	public function verify_margin($margin)
	{
		return ($margin === 'auto' OR $margin === strval($margin + 0));
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 34206 $
|| ####################################################################
\*======================================================================*/
