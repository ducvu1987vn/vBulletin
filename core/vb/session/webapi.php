<?php
/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 5.0.0
  || # ---------------------------------------------------------------- # ||
  || # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

/**
 * This class is used by collapsed interface and behaves exactly as a web session without cookies
 */
class vB_Session_WebApi extends vB_Session_Web
{
    public static function getSession($userId, $sessionHash = '', &$dBAssertor = null, &$datastore = null, &$config = null)
	{
		$dBAssertor = ($dBAssertor) ? $dBAssertor : vB::getDbAssertor();
		$datastore = ($datastore) ? $datastore : vB::getDatastore();
		$config = ($config) ? $config : vB::getConfig();

		$session = new vB_Session_WebApi($dBAssertor, $datastore, $config, $sessionHash, $userId);
		$session->set('userid', $userId);
		$session->fetch_userinfo();

		return $session;
	}

	public static function createSession($sessionhash= '', $userid = 0, $password = '')
	{
		$assertor = vB::getDbAssertor();
		$datastore = vB::getDatastore();
		$config = vB::getConfig();

		$session = new vB_Session_WebApi($assertor, $datastore, $config, $sessionhash, $userid, $password);

		return $session;
	}

	public function __construct(&$dBAssertor, &$datastore, &$config, $sessionhash = '', $userid = 0, $password = '', $styleid = 0, $languageid = 0)
	{
		parent::__construct($dBAssertor, $datastore, $config, $sessionhash, $userid, $password, $styleid, $languageid);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
