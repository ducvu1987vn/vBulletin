<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * vB_Api_Vb4_member
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Vb4_member extends vB_Api
{
	public function call($username = null, $userid = 0, $tab = 'friends', $perpage = 10, $pagenumber = 1)
	{
		$cleaner = vB::getCleaner();

		if ($username)
		{
			$username = $cleaner->clean($username, vB_Cleaner::TYPE_STR);
			$userinfo = vB_Api::instance('user')->fetchByUsername($username);
			$userid = $userinfo['userid'];
		}
		else if($userid)
		{
			$userid = $cleaner->clean($userid, vB_Cleaner::TYPE_UINT);
			$userinfo = vB_Api::instance('user')->fetchUserinfo($userid);
		}
		else
		{
			$userinfo = vB_Api::instance('user')->fetchUserinfo();
			$userid = $userinfo['userid'];
		}

		$tab = $cleaner->clean($tab, vB_Cleaner::TYPE_STR);
		$perpage = $cleaner->clean($perpage, vB_Cleaner::TYPE_UINT);
		$pagenumber = $cleaner->clean($pagenumber, vB_Cleaner::TYPE_UINT);

		$profile = vB_Api::instance('user')->fetchProfileInfo($userid);

		if (empty($profile)) {
			return array('response' => array('errormessage' => 'invalidid'));
		}

		$current_userinfo = vB_Api::instanceInternal('user')->fetchUserinfo();
		$following = vB_Api::instance('follow')->isFollowingUser($userid);
		$values = array();

		if (!empty($profile['birthday']))
		{
			if ($userinfo['showbirthday'] == 1) {
				$values[] = array(
					'profilefield' => array(
						'title' => (string)(new vB_Phrase('global', 'age')),
						'value' => "$profile[age]",
					),
				);
			}

			if ($userinfo['showbirthday'] == 2)
			{
				if (!empty($profile['age']))
				{
					$values[] = array(
						'profilefield' => array(
							'title' => (string)(new vB_Phrase('user', 'birthday_guser')),
							'value' => $profile['birthday'] . ' (' . $profile['age'] .')',
						),
					);
				}
			}

			if ($userinfo['showbirthday'] == 3) {
				$birthday = explode('-', $profile['birthday']);
				unset($birthday[2]);
				$birthday = implode('-', $birthday);
				$birthday = 
					$values[] = array(
						'profilefield' => array(
							'title' => (string)(new vB_Phrase('user', 'birthday_guser')),
							'value' => $birthday,
						),
					);
			}
		}

		foreach($profile['customFields']['default'] as $name => $value) {
			$value = $value['val'];
			if ($value === null) {
				$value = '';
			}
			$values[] = array(
				'profilefield' => array(
					'title' => (string) new vB_Phrase('cprofilefield', $name),
					'value' => $value,
				),
			);
		}

		$groups = array();
		$groups[] = array(
			'category' => array(
				'title' => (string)(new vB_Phrase('global', 'basicinfo')),
				'fields' => $values,
			),
		);

		$values = array();
		if ($userinfo['homepage'] OR $userinfo['icq']  OR $userinfo['aim'] OR $userinfo['yahoo'] OR $userinfo['msn'] OR $userinfo['skype'])
		{
			if ($userinfo['homepage'])
			{
				$values[] = array(
					'profilefield' => array(
						'title' => (string)(new vB_Phrase('global', 'web')),
						'value' => $userinfo['homepage'],
					),
				);
			}

			if ($userinfo['aim'])
			{
				$values[] = array(
					'profilefield' => array(
						'title' => (string)(new vB_Phrase('global', 'aim')),
						'value' => $userinfo['aim'],
					),
				);
			}

			if ($userinfo['icq'])
			{
				$values[] = array(
					'profilefield' => array(
						'title' => (string)(new vB_Phrase('global', 'icq')),
						'value' => $userinfo['icq'],
					),
				);
			}
			if ($userinfo['yahoo'])
			{
				$values[] = array(
					'profilefield' => array(
						'title' => (string)(new vB_Phrase('global', 'yahoo')),
						'value' => $userinfo['yahoo'],
					),
				);
			}

			if ($userinfo['msn'])
			{
				$values[] = array(
					'profilefield' => array(
						'title' => (string)(new vB_Phrase('global', 'msn')),
						'value' => $userinfo['msn'],
					),
				);
			}

			if ($userinfo['skype'])
			{
				$values[] = array(
					'profilefield' => array(
						'title' => (string)(new vB_Phrase('global', 'skype')),
						'value' => $userinfo['skype'],
					),
				);
			}


			$groups[] = array(
				'category' => array(
					'title' => (string)(new vB_Phrase('global', 'contact')),
					'fields' => $values,
				),
			);
		}

		$values = array();

		$values[] = array(
			'profilefield' => array(
				'title' => (string)(new vB_Phrase('global', 'total_posts')),
				'value' => $userinfo['posts'],
			),
		);

		$values[] = array(
			'profilefield' => array(
				'title' => (string)(new vB_Phrase('global', 'posts_per_day')),
				'value' => $userinfo['postPerDay'],
			),
		);

		$values[] = array(
			'profilefield' => array(
				'title' => (string)(new vB_Phrase('global', 'visitor_messages')),
				'value' => $userinfo['vmCount'],
			),
		);

		$values[] = array(
			'profilefield' => array(
				'title' => (string)(new vB_Phrase('global', 'referrals')),
				'value' => $userinfo['referralsCount'],
			),
		);

		$groups[] = array(
			'category' => array(
				'title' => (string)(new vB_Phrase('global', 'statistics')),
				'fields' => $values,
			),
		);

		foreach ($groups as &$group)
		{
			foreach ($group['category']['fields'] as &$field)
			{
				if ($field['profilefield']['value'] === null)
				{
					$field['profilefield']['value'] = "";
				}
				$field['profilefield']['value'] = (string)$field['profilefield']['value'];
			}
		}

		$canbefriend = (($following == 0) && ($current_userinfo['userid'] != $userid)) ? 1 : 0;

		$avatarurl = vB_Library::instance('vb4_functions')->avatarUrl($userid);
		$out = array(
			'response' => array(
				'prepared' => array(
					'userid' => $userid,
					'username' => $userinfo['username'],
					'usertitle' => $userinfo['usertitle'],
					'profilepicurl' => $avatarurl,
					'avatarurl' => $avatarurl,
					'canbefriend' => $canbefriend,
				),
				'blocks' => array(
					'aboutme' => array(
						'block_data' => array(
							'fields' => $groups,
						),
					),
				),
			),
			'show' => array(
				'vm_block' => $userinfo['vm_enable'],
				'post_visitor_message' => $userinfo['vm_enable'],
				'addbuddylist' => $canbefriend,
			),
		);

		if ($tab == 'friends')
		{
			$followers = vB_Api::instance('follow')->getFollowers($userid, array('page' => $pagenumber, 'perpage' => $perpage));
			$friends = array();

			foreach($followers['results'] as $friend) {
				$avatarurl = vB_Library::instance('vb4_functions')->avatarUrl($friend['userid']);
				$friendinfo = vB_Api::instance('user')->fetchUserinfo($friend['userid']);
				$friends[] = array(
					'user' => array(
						'userid' => $friend['userid'],
						'username' => $friend['username'],
						'usertitle' => $friendinfo['usertitle'],
						'avatarurl' => $avatarurl,
					),
				);
			}

			$pagenav = vB_Library::instance('vb4_functions')->pageNav($pagenumber, $perpage, $followers['paginationInfo']['totalcount']);
			$out['response']['blocks']['friends']['block_data']['friendbits'] = $friends;
			$out['response']['blocks']['friends']['block_data']['pagenav'] = $pagenav;
		}
		else if ($tab == 'visitor_messaging')
		{
			$search = array('authorid' => $userid);
			$search['view'] = vB_Api_Search::FILTER_VIEW_ACTIVITY;
			$search['visitor_messages_only'] = 1;
			$search['date'] = vB_Api_Search::FILTER_CHANNELAGE;
			$vm_search = vB_Api::instance('search')->getInitialResults($search, $perpage, $pagenumber, true);

			$vms = array();
			$page_nav = vB_Library::instance('vb4_functions')->pageNav(1, 1, 0);

			;
			if (!empty($vm_search) || !isset($vm_search['errors']))
			{
				foreach ($vm_search['results'] AS $key => $node)
				{
					if ($node['content']['vm_userInfo']['userid'] != $userid)
					{
						continue;
					}
					$avatarurl = vB_Library::instance('vb4_functions')->avatarUrl($node['userid']);
					$vms[] = array(
						'message' => array(
							'vmid' => $node['nodeid'],
							'userid' => $node['userid'],
							'username' => $node['authorname'],
							'message' => $node['content']['rawtext'],
							'time' => $node['publishdate'],
							'avatarurl' => $avatarurl,
						),
					);
				}

				$page_nav = vB_Library::instance('vb4_functions')->pageNav($pagenumber, $perpage, $vm_search['totalRecords']);

			}
			$out['response']['blocks']['visitor_messaging']['block_data']['messagebits'] = $vms;
			$out['response']['blocks']['visitor_messaging']['block_data']['pagenav'] = $page_nav;
		}

		return $out;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
