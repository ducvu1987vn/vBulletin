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

class vB_DataManager_StyleVarNumeric extends vB_DataManager_StyleVar
{
	/**
	* Array of recognised and required child fields for stylevar, and their types
	*
	* @var	array
	*/
	var $childfields = array(
		'numeric'			=> array(vB_Cleaner::TYPE_NUM,			vB_DataManager_Constants::REQ_NO),
	);

	public $datatype = 'Numeric';
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/