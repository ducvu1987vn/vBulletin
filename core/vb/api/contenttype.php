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
 * vB_Api_ContentType
 *
 * @package vBApi
 * @access public
 */
class vB_Api_ContentType extends vB_Api
{
	const OLDTYPE_THREADATTACHMENT = 9982;
	const OLDTYPE_POSTATTACHMENT = 9990;

	protected function __construct()
	{
		parent::__construct();
	}

	/**
	 * Returns the integer content type id for the given content type class name
	 *
	 * @param	string	Content Type Class Name
	 * @param	string	Package Name
	 *
	 * @return	int	Content Type ID
	 */
	public function fetchContentTypeIdFromClass($class, $package = 'vBForum')
	{
		$contenttypeid = vB_Types::instance()->getContentTypeId($package . '_' . $class);
		return $contenttypeid ? $contenttypeid : 0;
	}

	/**
	 * Returns the class name for for the given content type id
	 *
	 * @param	int	Content Type ID
	 *
	 * @return	string	Content Type Class Name
	 */
	public function fetchContentTypeClassFromId($contenttypeid)
	{
		return vB_Types::instance()->getContentTypeClass($contenttypeid);
	}

}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
