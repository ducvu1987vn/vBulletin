<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5 Presentation Configuration                           # ||
|| # ---------------------------------------------------------------- # ||
|| # All PHP code in this file is ©2000-2012 vBulletin Solutions Inc. # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/*-------------------------------------------------------*\
| ****** NOTE REGARDING THE VARIABLES IN THIS FILE ****** |
+---------------------------------------------------------+
| When making changes to the file, the edit should always |
| be to the right of the = sign between the single quotes |
| Default: $config['admincpdir'] = 'admincp';             |
| Example: $config['admincpdir'] = 'myadmin';  GOOD!      |
| Example: $config['myadmin'] = 'admincp'; BAD!           |
\*-------------------------------------------------------*/


    //    ****** Base URLs ******
    // The following settings all deal with the url of your forum.
    // If set incorrectly your site/software will not function correctly.
    // These urls should NOT include a trailing slash
    // This is the url and web path of your root vBulletin directory
$config['baseurl'] = 'http://www.yourdomain.com/folder';


// This will only be used if you wish to require https logins
// You will not need to change this setting most of the time.
$config['baseurl_login'] = $config['baseurl'];

    // If you do wish to use https for login, uncomment this line
    // Then fill in your https url.
//$config['baseurl_login'] = 'https://www.yourdomain.com/folder';


    //    ****** System Paths ******

    // This setting allows you to change the name of the admin folder
$config['admincpdir'] = 'admincp';

    //    ****** Cookie Settings ******
    // These are cookie related settings.
    // This Setting allows you to change the cookie prefix
$config['cookie_prefix'] = 'bb';


//    ****** Special Settings ******
// These settings are only used in some circumstances
// Please do not edit if you are not sure what they do.

// You can ignore this setting for right now.
$config['cookie_enabled'] = true;

$config['report_all_php_errors'] = false;
$config['no_template_notices'] = true;

// This setting should never be used on a live site
$config['no_js_bundles'] = false;

// This setting enables debug mode, it should NEVER be used on a live site
$config['debug'] = false;

// Assumes default location of core. 
// These are the system paths and folders for your vBulletin files
// This setting is for where your vbulletin core folder is
$config['core_path'] = realpath(dirname(__FILE__)) . '/core';

    // This is the url and web based path to your core directory
$config['baseurl_core'] = $config['baseurl']  .  '/core';


/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - 
|| ####################################################################
\*======================================================================*/

