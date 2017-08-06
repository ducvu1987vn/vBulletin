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

require_once(DIR . '/includes/facebook/facebook.php');

/**
 * Extension of the Facebook API class, so we can use vUrl instead of cUrl
 *
 * @package vBulletin
 * @author Michael Henretty, vBulletin Development Team
 * @version $Revision: 43642 $
 * @since $Date: 2011-05-19 12:14:21 -0300 (Thu, 19 May 2011) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Facebook_vUrl extends Facebook
{
	/**
	 * Overrides the Facebook API request methods, so we can use vUrl
	 *
	 * @param String $url the URL to make the request to
	 * @param Array $params the parameters to use for the POST body
	 * @param CurlHandler $ch optional initialized curl handle
	 * @return String the response text
	 */
	/*
	protected function makeRequest($url, $params, $ch = null)
	{
		// try Facebook's cURL implementation (including the new bundled certificates)
		if (function_exists('curl_init'))
		{
			try
			{
				$result = parent::makeRequest($url, $params, $ch);
			}
			catch (Exception $e)
			{
				$result = false;
			}

			if ($result)
			{
				return $result;
			}
		}

		// use vB_vURL implmentation
		global $vbulletin;
		$opts = self::$CURL_OPTS;

		require_once(DIR . '/includes/class_vurl.php');
		$vurl = new vB_vURL($vbulletin);
		$vurl->set_option(VURL_URL, $url);
		$vurl->set_option(VURL_CONNECTTIMEOUT, $opts[CURLOPT_CONNECTTIMEOUT]);
		$vurl->set_option(VURL_TIMEOUT, $opts[CURLOPT_TIMEOUT]);
		$vurl->set_option(VURL_POST, 1);
		// If we want to use more advanced features such as uploading pictures
		// to facebook, we may need to remove http_build_query and refactor
		// vB_vURL to accept an array of POST data and send the multipart/form-data
		// Content-Type header.
		$vurl->set_option(VURL_POSTFIELDS, http_build_query($params, '', '&'));
		$vurl->set_option(VURL_RETURNTRANSFER, $opts[CURLOPT_RETURNTRANSFER]);
		$vurl->set_option(VURL_CLOSECONNECTION, $opts[CURLOPT_RETURNTRANSFER]);
		$vurl->set_option(VURL_USERAGENT, $opts[CURLOPT_USERAGENT]);

		$result = $vurl->exec();

		// TODO: add some error checking here
		// particularly check if $vurl->fetch_error() returns VURL_ERROR_SSL, meaning the server
		// does not have access to TLS/SSL with which to communicate with facebook

		return $result;
	}
	*/
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
