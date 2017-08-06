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

Api_InterfaceAbstract::instance()->callApi('site', 'forumrunner_image');
