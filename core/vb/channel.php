<?php

/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 5.0.0
  || # ---------------------------------------------------------------- # ||
  || # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

class vB_Channel
{
	// Less obvious channel names.
	const MAIN_CHANNEL = 'vbulletin-4ecbdf567f2773.55528984'; // Overall master root node
	const DEFAULT_CHANNEL_PARENT = 'vbulletin-4ecbdf567f3341.44451100'; // 'Special' root channel
	const DEFAULT_FORUM_PARENT = 'vbulletin-4ecbdf567f2c35.70389590'; // Forums root channel
	const MAIN_FORUM = 'vbulletin-4ecbdf567f3341.44450667'; // Default Main Forum on new installations
	const MAIN_FORUM_CATEGORY = 'vbulletin-4ecbdf567f3341.44450666'; // Default Main Category on new installations
	const DEFAULT_BLOG_PARENT = 'vbulletin-4ecbdf567f3a38.99555305'; // Blogs root channel

	// It should be obvious from the names of these what they are.
	const DEFAULT_SOCIALGROUP_PARENT = 'vbulletin-4ecbdf567f3a38.99555306';
	const DEFAULT_UNCATEGORIZEDGROUPS_PARENT = 'vbulletin-4ecbdf567f3a38.99555307';
	const PRIVATEMESSAGE_CHANNEL = 'vbulletin-4ecbdf567f3da8.31769341';
	const VISITORMESSAGE_CHANNEL = 'vbulletin-4ecbdf567f36c3.90966558';
	const ALBUM_CHANNEL = 'vbulletin-4ecbdf567f3a38.99555303';
	const REPORT_CHANNEL = 'vbulletin-4ecbdf567f3a38.99555304';

	/**
	 * Moves all blog channels from the old blog channel parent to the new one.
	 * @param int $oldChannelId
	 * @param int $newChannelId
	 */
	public static function moveBlogChannels($oldChannelId, $newChannelId)
	{
		if (empty($oldChannelId))
		{
			$oldChannelId = vB::getDbAssertor()->getField('vBForum:channel', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'guid' => self::DEFAULT_BLOG_PARENT));
		}

		$children = vB::getDbAssertor()->assertQuery('vBForum:closure', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'parent' => $oldChannelId,
			'depth' => 1
		));

		$childrenIds = array();
		foreach ($children AS $child)
		{
			$childrenIds[] = $child['child'];
		}
		if (!empty($childrenIds))
		{
			vB_Api::instanceInternal('node')->moveNodes($childrenIds, $newChannelId);
		}
	}

	public static function getDefaultGUIDs()
	{
		return array(
			'MAIN_CHANNEL' => self::MAIN_CHANNEL,
			'MAIN_FORUM' => self::MAIN_FORUM,
			'DEFAULT_CHANNEL_PARENT' => self::DEFAULT_CHANNEL_PARENT,
			'DEFAULT_FORUM_PARENT' => self::DEFAULT_FORUM_PARENT,
			'DEFAULT_BLOG_PARENT' => self::DEFAULT_BLOG_PARENT,
			'DEFAULT_SOCIALGROUP_PARENT' => self::DEFAULT_SOCIALGROUP_PARENT,
			'DEFAULT_UNCATEGORIZEDGROUPS_PARENT' => self::DEFAULT_UNCATEGORIZEDGROUPS_PARENT,
			'PRIVATEMESSAGE_CHANNEL' => self::PRIVATEMESSAGE_CHANNEL,
			'VISITORMESSAGE_CHANNEL' => self::VISITORMESSAGE_CHANNEL,
			'ALBUM_CHANNEL' => self::ALBUM_CHANNEL,
			'REPORT_CHANNEL' => self::REPORT_CHANNEL
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
