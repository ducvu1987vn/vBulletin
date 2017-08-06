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

class vB_Session_Web extends vB_Session
{
	public static function getSession($userId, $sessionHash = '', &$dBAssertor = null, &$datastore = null, &$config = null)
	{
		$dBAssertor = ($dBAssertor) ? $dBAssertor : vB::getDbAssertor();
		$datastore = ($datastore) ? $datastore : vB::getDatastore();
		$config = ($config) ? $config : vB::getConfig();

		$session = new vB_Session_Web($dBAssertor, $datastore, $config, $sessionHash, $userId);
		$session->set('userid', $userId);
		$session->fetch_userinfo();

		return $session;
	}

	public function __construct(&$dBAssertor, &$datastore, &$config, $sessionhash = '', $userid = 0, $password = '', $styleid = 0, $languageid = 0)
	{
		parent::__construct($dBAssertor, $datastore, $config, $sessionhash, $userid, $password, $styleid, $languageid);
	}

	protected function createSessionIdHash()
	{
		$request = vB::getRequest();
		$this->sessionIdHash = md5($request->getUserAgent() . $this->fetch_substr_ip($request->getAltIp()));
	}

	/** Get the current url scheme- http or https
	 *
	 * @return string
	 **/
	public function getVbUrlScheme()
	{
		return vB::getRequest()->getVbUrlScheme();
	}

}

/* ======================================================================*\
  || ####################################################################
  || # CVS: $RCSfile$ - $Revision: 40911 $
  || ####################################################################
  \*====================================================================== */