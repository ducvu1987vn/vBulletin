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
 * vB_Api_Filedata
 *
 * @package vBApi
 * @author ebrown
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Api_Filedata extends vB_Api
{
	/**#@+
	* Allowed resize labels
	*/
	const SIZE_ICON	  = 'icon';
	const SIZE_THUMB  = 'thumb';
	const SIZE_SMALL  = 'small';
	const SIZE_MEDIUM = 'medium';
	const SIZE_LARGE  = 'large';
	const SIZE_FULL   = 'full';
	/**#@-*/

	/*
	 * Ensures that Sent in thumbnail type is valid
	 *
	 * @param	mixed	Image size to get
	 *
	 * @return	string	Valid image size to get
	 */
	public function sanitizeFiletype($type)
	{
		if ($type == 1 OR $type === true OR $type === 'thumbnail')
		{
			$type = vB_Api_Filedata::SIZE_THUMB;
		}

		$options = vB::getDatastore()->get_value('options');
		$sizes = @unserialize($options['attachresizes']);
		if (!isset($sizes[$type]) OR empty($sizes[$type]))
		{
			$type = vB_Api_Filedata::SIZE_FULL;
		}

		switch ($type)
		{
			case vB_Api_Filedata::SIZE_ICON:
			case vB_Api_Filedata::SIZE_THUMB:
			case vB_Api_Filedata::SIZE_SMALL:
			case vB_Api_Filedata::SIZE_MEDIUM:
			case vB_Api_Filedata::SIZE_LARGE:
				break;
			default:
				$type = vB_Api_Filedata::SIZE_FULL;
		}

		return $type;
	}

	/** fetch image information about an attachment based on file data id
	 *
	 * 	@param 	int		filedataid
	 * 	@param	mixed	size requested
	 * 	@param	bool	should we include the image content
	 *
	 *	@return	mixed	array of data, includes filesize, dateline, htmltype, filename, extension, and filedataid
	 **/
	public function fetchImageByFiledataid($id, $type = vB_Api_Filedata::SIZE_FULL, $includeData = true)
	{
		if (empty($id) OR !is_numeric($id) OR !intval($id))
		{
			throw new vB_Exception_Api('invalid_request');
		}

		$type = $this->sanitizeFiletype($type);

		//If the record belongs to this user, or if this user can view attachments
		//in this section, then this is O.K.
		$userinfo = vB::getCurrentSession()->fetch_userinfo();

		$params = array('filedataid' => $id, 'type' => $type);
		$record = vB::getDbAssertor()->getRow('vBForum:getFiledataContent', $params);

		if (empty($record))
		{
			return false;
		}

		if (($userinfo['userid'] == $record['userid']) OR ($record['publicview'] > 0))
		{
			$imageHandler = vB_Image::instance();
			return $imageHandler->loadFileData($record, $type, $includeData);
		}
		throw new vB_Exception_Api('no_view_permissions');
	}

	/** fetch filedata records based on filedata ids
	 *
	 * 	@param 	array/int		filedataids
	 *
	 *	@return	mixed	array of data, includes filesize, dateline, htmltype, filename, extension, and filedataid
	 **/
	public function fetchFiledataByid($ids)
	{
		if (empty($ids) OR !is_array($ids))
		{
			throw new vB_Exception_Api('invalid_request');
		}

		//If the record belongs to this user, or if this user can view attachments
		//in this section, then this is O.K.
		$userinfo = vB::getCurrentSession()->fetch_userinfo();

		$records = vB::getDbAssertor()->assertQuery('vBForum:getFiledataWithThumb', array('filedataid' => $ids));
		$filedatas = array();
		foreach ($records as $record)
		{
			if (($userinfo['userid'] == $record['userid']) OR ($record['publicview'] > 0))
			{
				$record['visible'] = $record['publicview'];
				$record['counter'] = $record['refcount'];
				$record['filename'] = $record['filehash'] . '.' . $record['extension'];
				$filedatas[$record['filedataid']] = $record;
			}
		}
		return $filedatas;
	}

	/**
	 * Returns filedata ids for legacy attachments
	 * @param array $ids
	 */
	public function fetchLegacyAttachments($ids)
	{
		if (empty($ids) OR !is_array($ids))
		{
			throw new vB_Exception_Api('invalid_request');
		}

		array_walk($ids, 'intval');

		$rows = vB::getDbAssertor()->assertQuery('vBForum:fetchLegacyAttachments',
				array(
					'oldids' => $ids,
					'oldcontenttypeid' => array(vB_Api_ContentType::OLDTYPE_THREADATTACHMENT, vB_Api_ContentType::OLDTYPE_POSTATTACHMENT)
				));
		$result = array();
		$userContext = vB::getUserContext();
		foreach($rows AS $row)
		{
			$row['visible'] = $row['publicview'];
			$row['counter'] = $row['refcount'];
			$row['filename'] = $row['filehash'] . '.' . $row['extension'];
			$row['cangetattachment'] = $userContext->getChannelPermission('forumpermissions', 'cangetattachment', $row['nodeid']);
			$result[$row['oldid']] = $row;
		}

		return $result;
	}

	/** fetch filedataid(s) for the passed photo nodeid(s)
	 *
	 * 	@param 	mixed(array|int)	photoid(s)
	 *
	 *	@return	array	filedataids for the requested photos
	 **/
	public function fetchPhotoFiledataid($nodeid)
	{
		if (!is_array($nodeid))
		{
			$nodeid = array($nodeid);
		}

		$filedataids = array();

		$resultSet = vB::getDbAssertor()->assertQuery('vBForum:photo', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'nodeid' => $nodeid
		));

		foreach ($resultSet as $filedata)
		{
			$filedataids[$filedata['nodeid']] = $filedata['filedataid'];
		}

		return $filedataids;
	}

	/** fetch filedataid(s) for the passed attachment nodeid(s)
	 *
	 * 	@param 	mixed(array|int)	attachmentid(s)
	 *
	 *	@return	array	filedataids for the requested attachments
	 **/
	public function fetchAttachFiledataid($nodeid)
	{
		if (!is_array($nodeid))
		{
			$nodeid = array($nodeid);
		}

		$filedataids = array();

		$resultSet = vB::getDbAssertor()->assertQuery('vBForum:attach', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'nodeid' => $nodeid
		));

		foreach ($resultSet as $filedata)
		{
			$filedataids[$filedata['nodeid']] = $filedata['filedataid'];
		}

		return $filedataids;
	}
}