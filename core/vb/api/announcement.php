<?php

/**
 * vB_Api_Announcement
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Announcement extends vB_Api
{
	/**
	 * @var vB_dB_Assertor
	 */
	protected $assertor;


	public function __construct()
	{
		parent::__construct();

		$this->assertor = vB::getDbAssertor();
	}

	/**
	 * Fetch announcements by channel ID
	 * @param int $channelid Channel ID
	 * @return array Announcements
	 */
	public function fetch($channelid = 0, $announcementid = 0)
	{
		$usercontext = vB::getUserContext();
		$userapi = vB_Api::instanceInternal('user');
		$channelapi = vB_Api::instanceInternal('content_channel');
		$parentids = array();

		// Check channel permission
		if ($channelid)
		{
			// This is to verify $channelid
			$channelapi->fetchChannelById($channelid);

			if (!$usercontext->getChannelPermission('forumpermissions', 'canview', $channelid))
			{
				throw new vB_Exception_Api('no_permission');
			}

			$parents = vB_Library::instance('node')->getParents($channelid);
			foreach ($parents as $parent)
			{
				if ($parent['nodeid'] != 1)
				{
					$parentids[] = $parent['nodeid'];
				}
			}
		}

		$data = array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'startdate', 'value' => vB::getRequest()->getTimeNow(), 'operator' => vB_dB_Query::OPERATOR_LTE),
				array('field' => 'enddate', 'value' => vB::getRequest()->getTimeNow(), 'operator' => vB_dB_Query::OPERATOR_GTE),
			),
		);
		if ($parentids)
		{
			$parentids[] = -1; // We should always include -1 for global announcements
			$data[vB_dB_Query::CONDITIONS_KEY][] = array('field' => 'nodeid', 'value' => $parentids);
		}
		elseif ($channelid)
		{
			$channelid = array($channelid, -1); // We should always include -1 for global announcements
			$data[vB_dB_Query::CONDITIONS_KEY][] = array('field' => 'nodeid', 'value' => $channelid);
		}
		else
		{
			$data[vB_dB_Query::CONDITIONS_KEY][] = array('field' => 'nodeid', 'value' => '-1');
		}

		$announcements = $this->assertor->getRows('vBForum:announcement', $data, array(
			'field' => array('startdate', 'announcementid'),
			'direction' => array(vB_dB_Query::SORT_DESC, vB_dB_Query::SORT_DESC)
		));

		if (!$announcements)
		{
			return array();
		}
		else
		{
			$results = array();
			$bf_misc_announcementoptions = vB::getDatastore()->getValue('bf_misc_announcementoptions');
			foreach ($announcements as $k => $post)
			{
				$userinfo = $userapi->fetchUserinfo($post['userid'], array(vB_Api_User::USERINFO_AVATAR, vB_Api_User::USERINFO_SIGNPIC));
				$announcements[$k]['username'] = $userinfo['username'];
				$announcements[$k]['avatarurl'] = $userapi->fetchAvatar($post['userid']);

				$announcements[$k]['dohtml'] = ($post['announcementoptions'] & $bf_misc_announcementoptions['allowhtml']);

				if ($announcements[$k]['dohtml'])
				{
					$announcements[$k]['donl2br'] = false;
				}
				else
				{
					$announcements[$k]['donl2br'] = true;
				}

				$announcements[$k]['dobbcode'] = ($post['announcementoptions'] & $bf_misc_announcementoptions['allowbbcode']);
				$announcements[$k]['dobbimagecode'] = ($post['announcementoptions'] & $bf_misc_announcementoptions['allowbbcode']);
				$announcements[$k]['dosmilies'] = ($post['announcementoptions'] & $bf_misc_announcementoptions['allowsmilies']);

				if ($announcements[$k]['dobbcode'] AND
					$post['announcementoptions'] & $bf_misc_announcementoptions['parseurl'])
				{
					require_once(DIR . '/includes/functions_newpost.php');
					$announcements[$k]['pagetext'] = convert_url_to_bbcode($post['pagetext']);
				}
			}
			return $announcements;
		}
	}
}
