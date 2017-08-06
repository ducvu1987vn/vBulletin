<?php

/**
 * vB_Api_Request
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Request extends vB_Api
{

	protected function __construct()
	{
		parent::__construct();
	}

	/**
	 * Returns an array of request information
	 *
	 * @return 	array	The request info
	 */
	public function getRequestInfo()
	{
		$request = vB::getRequest();

		$items = array(
			'sessionClass'  => 'getSessionClass',
			'timeNow'       => 'getTimeNow',
			'ipAddress'     => 'getIpAddress',
			'altIp'         => 'getAltIp',
			'sessionHost'   => 'getSessionHost',
			'userAgent'     => 'getUserAgent',
			'useEarlyFlush' => 'getUseEarlyFlush',
			'cachePageForGuestTime' => 'getCachePageForGuestTime',
			'referrer'      => 'getReferrer',
			'vBHttpHost'    => 'getVbHttpHost',
			'vBUrlScheme'   => 'getVbUrlScheme',
			'vBUrlPath'     => 'getVbUrlPath',
			'vBUrlQuery'    => 'getVbUrlQuery',
			'vBUrlQueryRaw' => 'getVbUrlQueryRaw',
			'vBUrlClean'    => 'getVbUrlClean',
			'vBUrlWebroot'  => 'getVbUrlWebroot',
			'scriptPath'    => 'getScriptPath',
		);

		$values = array();

		foreach ($items AS $varName => $methodName)
		{
			$values[$varName] = $request->$methodName();
		}

		return $values;
	}
}
