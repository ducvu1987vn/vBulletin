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
 * This class replaces the use of SKIP_SESSIONCREATE.
 * All it does is overriding the methods that are not supposed to run when the flag is on
 */
// TODO: remove _Tmp when we are ready to replace the legacy object
class vB_Session_Skip extends vB_Session
{
	public function __construct(&$dBAssertor, &$datastore, &$config, $styleid = 0, $languageid = 0)
	{
		parent::__construct($dBAssertor, $datastore, $config, '', 0, '', $styleid, $languageid);
	}

	protected function loadExistingSession($sessionhash, $userid, $password)
	{
		return false;
	}

	public function save()
	{
		return;
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/