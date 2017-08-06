<?php
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
/*
if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}
*/

class vB_Upgrade_500b19 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500b19';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Beta 19';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Beta 18';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '';

	/** Make sure the root canviewforums permission is set */
	public function step_1()
	{
		$this->show_message($this->phrase['version']['500b19']['confirming_root_canviewforum']);
		$parsedRaw = vB_Xml_Import::parseFile(DIR . '/includes/xml/bitfield_vbulletin.xml');
		$bitmask = $parsedRaw['bitfielddefs']['group']['ugp']['forumpermissions']['canviewthreads']['value'];
		vB::getDbAssertor()->assertQuery('vBInstall:updateRootChannelperm', array('bitmask' => $bitmask));
		vB::getUserContext()->rebuildGroupAccess();
	}

	/** Update profile picture size to 200x200 */
	public function step_2()
	{
		$this->show_message($this->phrase['version']['500b19']['update_profile_picture_size']);

		vB::getDbAssertor()->update('usergroup', array(
				'avatarmaxwidth' => 200,
				'avatarmaxheight' => 200
			),
			array(
					'avatarmaxwidth' => 165,
					'avatarmaxheight' => 165
			)
		);

	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
