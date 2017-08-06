<?php if (!defined('VB_ENTRY')) die('Access denied.');
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
 * CMS Navbar View
 * View for rendering the legacy navbar.
 * Wraps up some global assignments and uses the legacy template.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: $
 * @since $Date: $
 * @copyright vBulletin Solutions Inc.
 */
class vB_View_NavBar extends vB_View
{
	/*Render========================================================================*/

	/**
	 * Prepare the widget block locations and other info.
	 */
	protected function prepareProperties()
	{
		// Legacy globals needed by navbar template
		$this->prepareLegacyGlobals();
	}


	/**
	 * Prepares the legacy output.
	 * Registers the globals required for the legacy output such as the header,
	 * footer and navbar.
	 */
	protected function prepareLegacyGlobals()
	{
		global $stylevar, $vbphrase, $vboptions, $session, $navbar_reloadurl, $show,
		$bbuserinfo, $pmbox, $notifications_total, $return_link, $notices,
		$foruminfo, $notifications_menubits, $ad_location;

		$globals = array(
			'stylevar' => $stylevar,
			'vbphrase' => $vbphrase,
			'vboptions' => $vboptions,
			'session' => $session,
			'navbar_reloadurl' => $navbar_reloadurl,
			'show' => $show,
			'bbuserinfo' => $bbuserinfo,
			'pmbox' => $pmbox,
			'notifications_total' => $notifications_total,
			'return_link' => $return_link,
			'notices' => $notices,
			'foruminfo' => $foruminfo,
			'notifications_menubits' => $notifications_menubits,
//			'ad_location' => $ad_location
		);

		$this->_properties = array_merge($this->_properties, $globals);
		
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision: 28709 $
|| ####################################################################
\*======================================================================*/
