<?php

/**
 * vB_Api_Facebook
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Facebook extends vB_Api
{

	protected function __construct()
	{
		parent::__construct();
	}

	public function isFacebookEnabled()
	{
		return vB_Facebook::isFacebookEnabled();
	}

	public function userIsLoggedIn()
	{
		return vB_Facebook::instance()->userIsLoggedIn();
	}

	public function getLoggedInFbUserId()
	{
		return vB_Facebook::instance()->getLoggedInFbUserId();
	}

	public function getVbUseridFromFbUserid()
	{
		return vB_Facebook::instance()->getVbUseridFromFbUserid();
	}

	public function getFbProfileUrl()
	{
		return vB_Facebook::getFbProfileUrl();
	}

	public function getFbProfilePicUrl()
	{
		return vB_Facebook::getFbProfilePicUrl();
	}

	public function getFbUserInfo()
	{
		return vB_Facebook::instance()->getFbUserInfo();
	}

}
