<?php

class Api_Interface_Test extends Api_Interface_Collapsed
{
	public function __construct()
	{
		// in collapsed form, we want to be able to load API classes
		$core_path = vB5_Config::instance()->core_path;
		vB5_Autoloader::register($core_path);

		vB::init();
		$request = new vB_Request_Test(
			array(
				'userid' => 1,
				'ipAddress' => '127.0.0.1',
				'altIp' => '127.0.0.1',
				'userAgent' => 'CLI'
			)
		);
		vB::setRequest($request);
		$request->createSession();
	}
}