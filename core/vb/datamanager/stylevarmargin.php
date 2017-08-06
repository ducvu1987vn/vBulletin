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

class vB_DataManager_StyleVarMargin extends vB_DataManager_StyleVar
{
	var $childfields = array(
		'top'				=> array(vB_Cleaner::TYPE_STR,			vB_DataManager_Constants::REQ_NO,		vB_DataManager_Constants::VF_METHOD,	'verify_margin'),
		'right'				=> array(vB_Cleaner::TYPE_STR,			vB_DataManager_Constants::REQ_NO,		vB_DataManager_Constants::VF_METHOD,	'verify_margin'),
		'bottom'			=> array(vB_Cleaner::TYPE_STR,			vB_DataManager_Constants::REQ_NO,		vB_DataManager_Constants::VF_METHOD,	'verify_margin'),
		'left'				=> array(vB_Cleaner::TYPE_STR,			vB_DataManager_Constants::REQ_NO,		vB_DataManager_Constants::VF_METHOD,	'verify_margin'),
		'same'				=> array(vB_Cleaner::TYPE_BOOL,			vB_DataManager_Constants::REQ_NO),
		'units'				=> array(vB_Cleaner::TYPE_STR,			vB_DataManager_Constants::REQ_NO,		vB_DataManager_Constants::VF_METHOD,	'verify_units'),
	);

	public $datatype = 'Margin';
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/