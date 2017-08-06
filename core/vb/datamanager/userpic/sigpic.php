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

class vB_DataManager_Userpic_Sigpic extends vB_DataManager_Userpic
{
	function vB_DataManager_Userpic_Sigpic(&$registry, $errtype = vB_DataManager_Constants::ERRTYPE_STANDARD)
	{
		$this->setStorageOptions('sigpic');
		parent::vB_DataManager_Userpic($registry, $errtype);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
