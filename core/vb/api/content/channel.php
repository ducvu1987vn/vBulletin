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
 * vB_Api_Content_Channel
 *
 * @package vBApi
 * @author ebrown
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Api_Content_Channel extends vB_Api_Content
{
	//override in client- the text name
	protected $contenttype = 'vBForum_Channel';

	//The table for the type-specific data.
	protected $tablename = 'channel';

	//We need the primary key field name.
	protected $primarykey = 'nodeid';

	/** normal protector- protected to prevent direct instantiation **/
	protected function __construct()
	{
		parent::__construct();
		$this->library = vB_Library::instance('Content_Channel');
	}

	/*** Adds a new channel.
	 *
	*	@param	mixed		Array of field => value pairs which define the record.
	*  @param	array		Array of options for the content being created. See subclasses for more info.
	*
	* 	@return	integer		the new nodeid
	***/
	public function add($data, $options = array())
	{
		// prevent adding top level channels
		if (!empty($data['parentid']))
		{
			$channel = $this->fetchChannelById($data['parentid']);
			if ($channel['guid'] == vB_Channel::MAIN_CHANNEL)
			{
				throw new vB_Exception_Api('cant_add_channel_to_root');
			}
		}
		return parent::add($data, $options);
	}


	/**
	 * Returns a channel record based on its node id
	 *
	 * @param	into	Node ID
	 *
	 * @return	array	Channel information
	 */
	public function fetchChannelById($nodeid, $options = array())
	{
		$nodeid = intval($nodeid);
		$nodes = $this->getContent($nodeid, $options);
		$data = array();

		if (!$this->validate($data, self::ACTION_VIEW, $nodeid, array($nodes)))
		{
			throw new vB_Exception_Api('no_permission');
		}

		if (!empty($options['moderatorperms']))
		{
			foreach ($nodes AS $nodeid => $node)
			{
				//Now the moderator-type permissions
				$nodes[$nodeid]['moderatorperms'] = vB::getUserContext()->getModeratorPerms($node);
				if (!empty($nodes[$nodeid]['moderatorperms']))
				{
					foreach ($nodes[$nodeid]['moderatorperms'] AS $perm)
					{
						if ($perm > 0)
						{
							$nodes[$nodeid]['canmoderate'] = true;
							break;
						}
					}
				}
			}
		}
		return $nodes[$nodeid];
	}

	/**
	 * Returns a channel record based on its node guid
	 *
	 * @param	string	GUID
	 *
	 * @return	array	Channel information
	 */
	public function fetchChannelByGUID($guid)
	{
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$channel = $cache->read('vbChannelGUID_' . $guid);
		if (!empty($channel))
		{
			return $channel;
		}

		$parentChannelGUIDs = vB_Channel::getDefaultGUIDs();
		$parentChannels = vB::getDbAssertor()->assertQuery('vBForum:channel', array('guid' => $parentChannelGUIDs));
		$channel = array();
		foreach ($parentChannels as $parentChannel)
		{
			$cache->write('vbChannelGUID_' . $parentChannel['guid'], $parentChannel, 1440, 'nodeChg_' . $parentChannel['nodeid']);
			if ($parentChannel['guid'] == $guid)
			{
				$channel = $parentChannel;
			}
		}

		return $channel;
	}

	/**
	 * Returns a channel id based on its node guid
	 *
	 * @param	string	GUID
	 *
	 * @return	int	Channel id
	 */
	public function fetchChannelIdByGUID($guid)
	{
		$channel = $this->fetchChannelByGUID($guid);
		return empty($channel) ? false : $channel['nodeid'];
	}
	/**
	 * Returns an array with bbcode options for the node.
	 * @param type $nodeId
	 */
	public function getBbcodeOptions($nodeId)
	{
		$record = $this->assertor->getRow('vBForum:channel', array(
			vB_dB_Query::TYPE_KEY	=> vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array('nodeid' => $nodeId)
		));

		$result = array();

		$options = vB::getDatastore()->getValue('bf_misc_forumoptions');
		foreach($options AS $optionName => $optionVal)
		{
			$result[$optionName] = (bool)($record['options'] & $optionVal);
		}

		return $result;
	}

	/** get a blog icon
	*
	*	@param	int		the channel or nodeid
	*
	*	@return	mixed	the raw content of the image.
	***/
	function fetchChannelIcon($nodeid, $type = vB_Api_Filedata::SIZE_FULL)
	{
		if (!vB::getUserContext()->getChannelPermission('forumpermissions', 'canview', $nodeid))
		{
			return $this->getDefaultChannelIcon($nodeid);
		}

		$channel = $this->assertor->getRow('vBForum:channel', array('nodeid' => $nodeid));
		if ($channel['filedataid'])
		{
			$params = array('filedataid' => $channel['filedataid'], 'type' => $type);
			$record = vB::getDbAssertor()->getRow('vBForum:getFiledataContent', $params);

			if (!empty($record))
			{
				return vB_Image::instance()->loadFileData($record, $type, true);
			}
		}
		//If we don't have a valid custom icon, return the default.
		return $this->getDefaultChannelIcon($nodeid);
	}

	private function getDefaultChannelIcon($nodeid)
	{
		$is_sg = vB_Api::instanceInternal('socialgroup')->isSGChannel($nodeid);
		$is_blog = $def_icon = false;
		if (!empty($is_sg))
		{
			$def_icon = "default_sg_large.png";
		}
		else
		{
			$is_blog = vB_Api::instanceInternal('blog')->isBlogNode($nodeid);
			if (!empty($is_blog))
			{
				$def_icon = "default_blog_large.png";
			}
		}
		if (!empty($def_icon))
		{
			return array(
					'filesize' => filesize(DIR . "/images/default/$def_icon"),
					'dateline' => vB::getRequest()->getTimeNow(),
					'headers' => vB_Library::instance('content_attach')->getAttachmentHeaders('png'),
					'filename' => $def_icon,
					'extension' => 'png',
					'filedataid' => 0,
					'is_default' => 1,
					'filedata' => file_get_contents(DIR . "/images/default/$def_icon")
				);
		}
		else
		{
			$cleargif = DIR . '/' . vB::getDatastore()->getOption('cleargifurl');
			$clearinfo = pathinfo($cleargif);
			return array(
					'filesize' => filesize($cleargif),
					'dateline' => vB::getRequest()->getTimeNow(),
					'headers' => vB_Library::instance('content_attach')->getAttachmentHeaders($clearinfo['extension']),
					'filename' => $clearinfo['basename'],
					'extension' => $clearinfo['extension'],
					'is_default' => 2,
					'filedataid' => 0,
					'filedata' => file_get_contents($cleargif)
			);

		}
	}

	public function getContributors($nodeId)
	{
		$db = vB::getDbAssertor();

		$users = array();

		// fetch relevant usergroups
		$systemgroups = array();
		$usergroups = $db->assertQuery('vBForum:usergroup', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'systemgroupid' => array(
				vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID,
				vB_Api_UserGroup::CHANNEL_MODERATOR_SYSGROUPID,
				vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID
			)
		));
		foreach ($usergroups as $usergroup)
		{
			$systemgroups[$usergroup['systemgroupid']] = $usergroup['usergroupid'];
		}
		$userids = array();
		// fetch active contributors
		$active = $db->assertQuery('vBForum:fetchActiveChannelContributors', array('nodeid' => $nodeId));
		if ($active AND $active->valid())
		{
			foreach($active AS $a)
			{
				switch ($a['systemgroupid'])
				{
					case vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID:
						$role = 'owner';
						break;
					case vB_Api_UserGroup::CHANNEL_MODERATOR_SYSGROUPID:
						$role = 'moderator';
						break;
					case vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID:
						$role = 'member';
						break;
					default:
						continue;
				}

				$userids[$a['userid']] = $a['userid'];

				$result['active'][$role][] = array(
					'usergroupid' => $a['usergroupid'],
					'userid' => $a['userid']
				);
			}
		}

		// fetch pending contributors
		$pending = $db->assertQuery('vBForum:fetchPendingChannelContributors', array('nodeid' => $nodeId));

		if ($pending AND $pending->valid())
		{
			foreach($pending AS $p)
			{
				switch ($p['about'])
				{
					case vB_Api_Node::REQUEST_TAKE_OWNER:
					case vB_Api_Node::REQUEST_SG_TAKE_OWNER:
						$role = 'owner';
						$usergroupid = $systemgroups[vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID];
						break;
					case vB_Api_Node::REQUEST_TAKE_MODERATOR:
					case vB_Api_Node::REQUEST_SG_TAKE_MODERATOR:
						$role = 'moderator';
						$usergroupid = $systemgroups[vB_Api_UserGroup::CHANNEL_MODERATOR_SYSGROUPID];
						break;
					default:
						continue;
				}

				$userids[$p['recipientid']] = $p['recipientid'];

				$result['pending'][$role][] = array(
					'usergroupid' => $usergroupid,
					'userid' => $p['recipientid'],
				);
			}
		}

		if (!empty($userids))
		{
			$usernames = vB_Library::instance('user')->fetchUserNames($userids);
			foreach ($result as $status => $roles)
			{
				foreach ($roles as $role => $users)
				{
					foreach ($users as $index => $user)
					{
						if (!empty($usernames[$user['userid']]))
						{
							$result[$status][$role][$index]['username'] = $usernames[$user['userid']];
						}
					}
				}
			}
		}

		return $result;
	}

	/**
	 *
	 * @param bool $makeCategory
	 * @param int $nodeId
	 * @return bool
	 */
	public function switchForumCategory($makeCategory, $nodeId)
	{
		// can't convert a top level channel
		if (in_array($nodeId, vB_Api::instanceInternal('content_channel')->fetchTopLevelChannelIds()))
		{
			throw new vB_Exception_Api('cannot_convert_channel');
		}

		return $this->library->switchForumCategory($makeCategory, $nodeId);
	}
	/**
	 * fetches the top level Channels/Categories
	 * @return array
	 */
	public function fetchTopLevelChannelIds()
	{
		$topLevelChannels = array(
				'forum' => vB_Channel::DEFAULT_FORUM_PARENT,
				'blog' => vB_Channel::DEFAULT_BLOG_PARENT,
				'groups' => vB_Channel::DEFAULT_SOCIALGROUP_PARENT,
				'special' => vB_Channel::DEFAULT_CHANNEL_PARENT,
		);
		$channels = array();
		$channels_res = vB::getDbAssertor()->assertQuery('vBForum:channel', array('guid' => $topLevelChannels));
		foreach ($channels_res as $channel)
		{
			$area = array_search($channel['guid'], $topLevelChannels);
			$channels[$area] = $channel['nodeid'];
		}
		return $channels;
	}

	/**
	 * fetches the top level Channel/Category for a node/nodes
	 * @param int $nodeid
	 */
	public function getTopLevelChannel($nodeids)
	{
		$parents = vB::getDbAssertor()->getColumn('vBForum:closure', 'parent', array(
				'child' => $nodeids, 'parent' => $this->fetchTopLevelChannelIds()), false, 'parent');
		// the nodes belog to different top level channels
		if (count($parents) > 1)
		{
			throw new vB_Exception_Api('invalid_data');
		}
		return array_pop($parents);
	}

	/** Tells whether or not the current user can add a new channel for the given node
	 *
	 *	@param	int		Nodeid to check
	 *
	 * 	@return mixed	Array containing checks information. It contains three keys:
	 * 					'can' 		-- to indicate if user can or can not add channel to the node.
	 * 					'error' 	-- phraseid of the error if user can't add channel to the node.
	 * 					'exceeded' 	-- value indicating if user already reached the max channels allowed at node level.
	 */
	public function canAddChannel($nodeid)
	{
		if (!is_numeric($nodeid) OR ($nodeid < 1))
		{
			return array('can' => false, 'error' => 'invalid_data', 'exceeded' => 0);
		}

		$usercontext = vB::getUserContext();
		if (!$usercontext->getChannelPermission('createpermissions', 'vbforum_channel', $nodeid)
			OR !$usercontext->getChannelPermission('forumpermissions', 'canjoin', $nodeid)
			OR !$usercontext->getChannelPermission('forumpermissions', 'canview', $nodeid)
			)
		{
			return array('can' => false, 'error' => 'no_permission', 'exceeded' => 0);
		}

		$queryParams = array('parent' => $nodeid, 'userid' => $usercontext->fetchUserId());
		$total = vB::getDbAssertor()->getRow('vBForum:getUserChannelsCount', $queryParams);
		$totalCount = $total['totalcount'];
		$maxchannels = $usercontext->getChannelLimits($nodeid, 'maxchannels');
		if(($maxchannels > 0) AND ($totalCount >= $maxchannels))
		{
			return array('can' => false, 'error' => '', 'exceeded' => $maxchannels);
		}

		return array('can' => true, 'error' => '', 'exceeded' => 0);
	}

	/**
	 * Checks the permissions to upload a channel icon
	 *
	 * @param int $nodeid
	 * @param array $data
	 */
	public function validateIcon($nodeid, $data)
	{
		if (empty($nodeid) OR !intval($nodeid))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		if (!vB::getUserContext()->getChannelPermission('forumpermissions', 'canuploadchannelicon', $nodeid))
		{
			throw new vB_Exception_Api('can_not_use_channel_icon');
		}

		if (!empty($data['filedata']) AND !empty($data['filesize']))
		{
			$filedata = $data;
		}
		else if (!empty($data['filedataid']))
		{
			$filedata = vB_Api::instanceInternal('filedata')->fetchImageByFiledataid($data['filedataid']);
		}

		// Is the image animated? This is how it is checked in vB_Image_GD::fetchImageInfo
		// For some reason some animated GIFs are uploaded unanimated, I couldn't find the cause
		if ((strpos($filedata['filedata'], 'NETSCAPE2.0') !== false) AND
			!vB::getUserContext()->getChannelPermission('forumpermissions', 'cananimatedchannelicon', $nodeid))
		{
			throw new vB_Exception_Api('can_not_use_animated_channel_icon');
		}

		$imageLimit = vB::getUserContext()->getChannelLimits($nodeid, 'channeliconmaxsize');

		if ($imageLimit > 0 AND $filedata['filesize'] > $imageLimit)
		{
			throw new vB_Exception_Api('upload_file_exceeds_limit', array(
				$filedata['filesize'], $imageLimit
			));
		}

		return true;
	}

	/**
	 * Updates the given channel
	 *
	 * @param int $nodeid
	 * @param int $data
	 * @return	boolean
	 */
	public function update($nodeid, $data)
	{
		if (isset($data['filedataid']))
		{
			$this->validateIcon($nodeid, array('filedataid' => $data['filedataid']));
		}

		return parent::update($nodeid, $data);
	}

	/** Does basic input cleaning for input data
	 	@param	mixed	array of fieldname => data pairs

	 	@return	mixed	the same data after cleaning.
	 */
	public function cleanInput(&$data, $nodeid = false)
	{
		$parentid = empty($data['parentid']) ? $nodeid : $data['parentid'];
		$userCanUseHtml = false;
		if (!empty($parentid))
		{
			$userCanUseHtml = vB::getUserContext()->getChannelPermission('forumpermissions2', 'canusehtml', $parentid);
		}
		// We're only allowing html in titles and descriptions for channels.
		// htmltitle not included because if it was provided, it should still not have html in it anyway.
		$htmlFields = array('title', 'description');
		$htmlData = array();
		$cleaner = vB::getCleaner();

		if ($userCanUseHtml)
		{
			foreach ($htmlFields as $fieldname)
			{
				if (isset($data[$fieldname]))
				{
					$htmlData[$fieldname] = $cleaner->clean($data[$fieldname], vB_Cleaner::TYPE_STR);
				}
			}
		}

		parent::cleanInput($data, $nodeid);

		// Let vB_Api_Content cleanInput do it's thing, then just replace the html fields if they were set.
		foreach ($htmlData AS $fieldname => $value)
		{
			$data[$fieldname] = $value;
		}
	}

}
