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

class vB_Upgrade_500a44 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a44';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 44';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 43';

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

	/** turn off all access for password-protected forums.  */
	public function step_1()
	{
		if ($this->tableExists('forum'))
		{
			$this->show_message(sprintf($this->phrase['version']['500a44']['importing_forum_perms_1']));
			vB::getDbAssertor()->assertQuery('vBInstall:hidePasswordForums', array('forumTypeid' =>vB_Types::instance()->getContentTypeID('vBForum_Forum')));
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Importing forum permissions.  */
	public function step_2()
	{
		if ($this->tableExists('forum'))
		{
			$this->show_message(sprintf($this->phrase['version']['500a44']['importing_forum_perms_2']));
			$options = vB::getDatastore()->getValue('options');
			$params = array('forumTypeid' =>vB_Types::instance()->getContentTypeID('vBForum_Forum'));
			$params['editTime'] = $options['noeditedbytime'];
			$params['maxtags'] = $options['maxtags'];
			$params['maxstartertags'] = $options['tagmaxstarter'];
			$params['maxothertags'] = $options['tagmaxuser'];
			$params['maxattachments'] = $options['attachlimit'];

			vB::getDbAssertor()->assertQuery('vBInstall:setForumPermissions', $params);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Clear any style settings in user table. Those will only break the display in vB5 */
	public function step_3()
	{
		//We only need to do this if we are upgraded  a vB 3/4 install
		if ($this->tableExists('forum'))
		{
			$this->show_message(sprintf($this->phrase['version']['500a44']['clearing_user_styles']));
			vB::getDbAssertor()->assertQuery('vBInstall:clearUserStyle', array());
		}
	else
		{
			$this->skip_message();
		}
	}

}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/