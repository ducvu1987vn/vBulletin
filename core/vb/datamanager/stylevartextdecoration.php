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

class vB_DataManager_StyleVarTextDecoration extends vB_DataManager_StyleVar
{
	var $childfields = array(
		'none'				=> array(vB_Cleaner::TYPE_BOOL,			vB_DataManager_Constants::REQ_NO),
		'underline'			=> array(vB_Cleaner::TYPE_BOOL,			vB_DataManager_Constants::REQ_NO),
		'overline'			=> array(vB_Cleaner::TYPE_BOOL,			vB_DataManager_Constants::REQ_NO),
		'line-through'		=> array(vB_Cleaner::TYPE_BOOL,			vB_DataManager_Constants::REQ_NO),
		'blink'				=> array(vB_Cleaner::TYPE_BOOL,			vB_DataManager_Constants::REQ_NO),
	);

	public $datatype = 'TextDecoration';
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/