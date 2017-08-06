<?php
/*
 * Forum Runner
 *
 * Copyright (c) 2010-2011 to End of Time Studios, LLC
 *
 * This file may not be redistributed in whole or significant part.
 *
 * http://www.forumrunner.com
 */

chdir('../');

require_once('includes/vb5/autoloader.php');
vB5_Autoloader::register(getcwd());
vB5_Frontend_Application::init('config.php');

$return = Api_InterfaceAbstract::instance()->callApi('site', 'forumrunner_request');
if (!is_string($return)) {
	$config = vB::getConfig();
	if (!empty($config['Misc']['debug'])) {
		error_log(var_export($return, true));
	}
	$return = json_encode(array('success' => false, 'message' => 'Unknown error.'));
}
echo $return;
