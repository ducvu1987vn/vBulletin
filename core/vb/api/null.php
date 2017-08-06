<?php
if (!defined('VB_ENTRY')) die('Access denied.');
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
 * vB_Api_Null
 * Dummy API class, does nothing, its used as a dummy 
 * class when a call is made to a none existent core class, 
 * which may exist as a custom API extension only.
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Null extends vB_Api
{
	public function __construct()
	{
	}
}
