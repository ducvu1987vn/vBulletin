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
 * vB_Library_VB4_Functions
 *
 * @package vBApi
 * @access public
 */
class vB_Library_VB4_Functions extends vB_Library
{
	function pageNav($pagenumber, $perpage, $results)
	{
		$totalpages = ceil($results / $perpage);
		$pagenavarr = array();
		$pagenavarr['total'] = $results;
		$pagenavarr['pagenumber'] = $pagenumber;
		$pagenavarr['totalpages'] = $totalpages ? $totalpages : 1;

		if($pagenavarr['totalpages'] == 1)
		{
			$pagenavarr['pagenav'][] = array('curpage' => 1, 'total' => $results);
			return $pagenavarr;
		}

		$pages = array(1, $pagenumber, $totalpages);

		if ($totalpages < 5)
		{
			for ($i = 2; $i < $totalpages; $i++)
			{
				$pages[] = $i;
			}
		}
		if ($totalpages >= 5)
		{
			if ($pagenumber > 1)
			{
				$pages[] = $pagenumber - 1; 
			}
			if ($pagenumber < $totalpages)
			{
				$pages[] = $pagenumber + 1; 
			}
		}

		if ($totalpages >= 30)
		{
			if ($pagenumber > 5)
			{
				$pages[] = $pagenumber - 5; 
			}
			if ($pagenumber < $totalpages - 5)
			{
				$pages[] = $pagenumber + 5; 
			}
		}

		if ($totalpages >= 60)
		{
			if ($pagenumber > 10)
			{
				$pages[] = $pagenumber - 10; 
			}
			if ($pagenumber < $totalpages - 10)
			{
				$pages[] = $pagenumber + 10; 
			}
		}

		$pages = array_unique($pages);
		sort($pages);

		foreach ($pages AS $curpage)
		{
			if ($curpage < 1 OR $curpage > $totalpages)
			{
				continue;
			}

			$pagenavarr['pagenav'][] = array('curpage' => $curpage, 'total' => $results);
		}

		return $pagenavarr;
	}

	function avatarUrl($userid)
	{
		$options = vB::getDatastore()->getValue('options');
		$avatarurl = vB_Api::instance('user')->fetchAvatars(array($userid));
		$avatarurl = array_pop($avatarurl);
		$avatarurl = $options['bburl'] . '/' . $avatarurl['avatarpath'];
		return $avatarurl;
	}

	function parseAttachments($attaches)
	{
		$userinfo = vB_Api::instance('user')->fetchUserinfo();
		$attachments = array();
		$moderated_attachments = array();
		$options = vB::getDatastore()->getValue('options');
		foreach ($attaches as $attachment)
		{
			$pictureurl = '';
			$attachment_node = vB_Api::instance('node')->getNode($attachment['nodeid']);
			$pictureurl .= $options['bburl'] . '/attachment.php?';
			$pictureurl .= 'attachmentid=' . $attachment['nodeid'] . '&userid=' . $userinfo['userid'];
			$session = vB::getCurrentSession();
			$session->set_session_visibility(false);
			$pictureurl .= '&' . $session->get('sessionurl_js');

			$parsed = array(
				'attachment' => array(
					'attachmentextension' => 'jpg',
					'attachmentid' => $attachment['filedataid'],
					'dateline' => $attachment['dateline'],
					'contentid' => $attachment['parentid'],
					'filename' => $attachment['filename'],
					'filesize' => $attachment['filesize'],
				),
				'pictureurl' =>  $pictureurl,
				'url' =>  $pictureurl,
			);

			if ($attachment_node['approved'])
			{
				$attachments[] = $parsed;
			}
			else
			{
				$moderated_attachments[] = $parsed;
			}
		}

		return array($attachments, $moderated_attachments);
	}

	function parseBBCode($record)
	{
		$this->bbcode_parser = new vB_Library_BbCode();
		$this->bbcode_parser->setAttachments($record['content']['attach']);
		$this->bbcode_parser->setParseUserinfo($record['userid']);

		$authorContext = vB::getUserContext($record['userid']);

		require_once DIR . '/includes/functions.php';
		$record['pagetext'] = fetch_censored_text($this->bbcode_parser->doParse(
			$record['content']['rawtext'],
			$authorContext->getChannelPermission('forumpermissions2', 'canusehtml', $record['parentid']),
			true,
			true,
			$authorContext->getChannelPermission('forumpermissions', 'cangetattachment', $record['parentid']),
			true,
			true,
			$record->htmlstate
		));

		$record['previewtext'] = $this->bbcode_parser->get_preview($record['rawtext'], 200, false, true, $record->htmlstate );
		return array($record, $this->bbcode_parser->getAttachments());
	}

	function parsePost($node)
	{
		if (!isset($node['content']))
		{
			$node = vB_Api::instance('node')->getFullContentforNodes(array($node['nodeid']));
			$node = $node[0];
		}
		if (!isset($node['pagetext']))
		{
			list($node, $attachments) = $this->parseBBCode($node);
		}

		$message = $node['pagetext'];

		$channel_bbcode_permissions = vB_Api::instance('content_channel')->getBbcodeOptions($node['content']['channelid']);
		if ($channel_bbcode_permissions['allowbbcode'] === false)
		{
			$message = $node['content']['rawtext'];
		}

		$topic = array(
			'post' => array(
				'posttime' => $node['publishdate'],
				'postid' => $node['nodeid'],
				'threadid' => $node['starter'],
				'title' => $node['title'],
				'userid' => $node['userid'],
				'username' => $node['userid'] > 0 ? $node['authorname'] : ((string)new vB_Phrase('global', 'guest')),
				'message' => $message,
				'message_bbcode' => $node['content']['rawtext'],
				'avatarurl' => $this->avatarUrl($node['userid']),
			),
			'show' => array(
				'editlink' => $node['content']['canedit'] ? 1 : 0,
				'moderated' => $node['approved'] ? 0 : 1,
			)
		);

		if (!empty($attachments) AND !in_array($node['contenttypeclass'], array('Gallery')))
		{
			list($topic['post']['imageattachments'], $topic['post']['moderatedattachments']) = $this->parseAttachments($attachments);
			if (!empty($topic['post']['moderatedattachments']))
			{
				$topic['show']['moderatedattachment'] = 1;
			}
		}

		if(!empty($node['content']['deleteusername']))
		{
			$topic['post']['del_username'] = $node['content']['deleteusername'];
			$topic['show']['deletedpost'] = 1;
		}

		$user = vB_Api::instance('user')->fetchUserinfo();
		// We have this option in vB5
		// I don't think we should use it in this case though
		// $vboptions = vB::getDatastore()->getValue('options');
		// $showinline = $vboptions['showsignaturesinline'] 

		if ($user['showsignatures'])
		{
			$topic['post']['signature'] = vB_Api::instance('bbcode')->parseSignature($node['userid']);
		}
		else
		{
			$topic['post']['signature'] = '';
		}
		return $topic;
	}

	//
	//	This is a dirty hack to satisfy HV checks on the
	//	forum. MAPI clients currently require this
	//	circumvention to function.
	//
	function getHVToken()
	{
		require_once(DIR . '/includes/class_humanverify.php');
		$verify =& vB_HumanVerify::fetch_library(vB::get_registry());
		$token = $verify->generate_token();
		$ret = array('input' => $token['answer'], 'hash' => $token['hash']);
		return $ret;
	}

	function getErrorResponse($result)
	{
		if (!empty($result['errors']))
		{
			$error_code = $result['errors'][0][0];
			if ($error_code == 'no_create_permissions')
			{
				$error_code = 'nopermission_loggedin';
			}
			return array('response' => array('errormessage' => $error_code));
		}
		return array('response' => array('errormessage' => 'unknownerror'));
	}

	function filterUserInfo($userinfo)
	{
		return array(
			'username' => $userinfo['username'],
			'userid' => $userinfo['userid'],
		);
	}

	function parseThread($node)
	{
		$status = array();
		if (!$node['open'])
		{
			$status['lock'] = 1;
		}
		$topic = array(
			'thread' => array(
				'prefix_rich' => $this->getPrefixTitle($node['prefixid']),
				'forumid' => $node['content']['channelid'],
				'forumtitle' => $node['content']['channeltitle'],
				'threadid' => $node['nodeid'],
				'threadtitle' => $node['title'],
				'postusername' => $node['userid'] > 0 ? $node['authorname'] : ((string)new vB_Phrase('global', 'guest')),
				'postuserid' => $node['userid'],
				'starttime' => $node['content']['publishdate'],
				'replycount' => $node['textcount'],
				'status' => $status,
				'views' => $node['totalcount'] + $node['votes'], // Fake views because we don't have them in 5
				'sticky' => $node['sticky']
			),
			'userinfo' => $this->filterUserInfo($node['content']['userinfo']),
			'avatar' => array(
				'hascustom' => 1,
				'0' => $this->avatarUrl($node['userid']),
			),
			'show' => array(
				'moderated' => $node['approved'] ? 0 : 1
			),
		);
		if(!empty($node['deleteuserid']))
		{
			$topic['thread']['del_userid'] = $node['deleteuserid'];
		}
		if(!empty($node['lastcontentauthor']))
		{
			$topic['thread']['lastposter'] = $node['content']['lastcontentauthor'];
			$topic['thread']['lastposttime'] = $node['content']['lastcontent'];
			$topic['thread']['lastpostid'] = $node['content']['lastcontentid'];
		}
		else
		{
			$topic['thread']['lastposter'] = $node['authorname'];
			$topic['thread']['lastposttime'] = $node['created'];
			$topic['thread']['lastpostid'] = $topic['threadid'];
		}

		return $topic;
	}

	private function getPrefixTitle($prefixid)
	{
		$phrases = vB_Api::instance('phrase')->fetch(array('prefix_' . $prefixid . '_title_rich'));

		$ret = $phrases['prefix_' .  $prefixid . '_title_rich'];
		if ($ret === null)
		{
			$ret = "";
		}
		return $ret;
	}

	function getPrefixes($channel)
	{
		$prefixes = vB_Api::instance('prefix')->fetch($channel);
		if (empty($prefixes))
		{
			return '';
		}

		$out = array();
		foreach($prefixes as $prefix_group_label => $prefix_group)
		{
			$options = array();
			foreach($prefix_group as $prefix_option)
			{
				$options[] = array(
					'optiontitle' => $this->getPrefixTitle($prefix_option['prefixid']),
					'optionvalue' => $prefix_option['prefixid'],
				);
			}
			$out[] = array(
				'optgroup_label' => "$prefix_group_label",
				'optgroup_options' => $options,
			);
		}
		return $out;
	}

	function getUsersBlogChannel()
	{
		$userinfo = vB_Api::instance('user')->fetchUserinfo();
		$global_blog_channel = vB_Api::instance('blog')->getBlogChannel();
		$search = array(
			'type' => 'vBForum_Channel',
			'channel' => $global_blog_channel,
		);
		$result = vB_Api::instance('search')->getInitialNodes($search);

		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}
		foreach ($result['nodeIds'] as $node)
		{
			$node_owner = vB_Api::instance('blog')->fetchOwner($node);
			if ($node_owner === $userinfo['userid'])
			{
				return $node;
			}
		}
		return null;
	}

	function getGlobalBlogCategories()
	{
		// TODO: Implement when vB5 adds them
		return array();
	}

	function getLocalBlogCategories($userid = 0)
	{
		if (!$userid)
		{
			$userinfo = vB_Api::instance('user')->fetchUserinfo();
			$userid = $userinfo['userid'];
		}
		// TODO: Implement when vB5 adds them
		return array();
	}

	function parseBlogComment($node)
	{
		return array(
			'response' => array(
				'blogtextid' => $node['nodeid'],
				'userid' => $node['userid'],
				'username' => $node['authorname'],
				'time' => $node['publishdate'],
				'avatarurl' => $this->avatarUrl($node['userid']),
				'message_plain' => strip_bbcode($node['content']['rawtext']),
				'message' => $node['content']['rawtext'],
				'message_bbcode' => $node['content']['rawtext'],
			),
		);
	}

	function parseBlogHeader($node)
	{
		$result = vB_Api::instance('node')->getNode($node['content']['channelid']);
		return array(
			'blogheader' => array(
				'userid' => $node['content']['starteruserid'],
				'title' => $node['content']['channeltitle'],
				'description' => $result['description'],
			),
			'userinfo' => array(
				'username' => $node['content']['starterauthorname'],
				'avatarurl' => $this->avatarUrl($node['content']['starteruserid']),
			),
		);
	}

	function parseBlogEntrySearch($node)
	{
		return array(
			'blog' => array(
				'blogid' => $node['nodeid'],
				'blogposter'	=> $node['authorname'],
				'postedby_username'	=> $node['authorname'],
				'title'	=> $node['title'],
				'lastposttime' => $node['lastupdate'],
				'time' => $node['publishdate'],
				'blogtitle' => $node['title'],
				'message' => $node['content']['rawtext'],
				'message_bbcode' => $node['content']['rawtext'],
				'message_plain' => strip_bbcode($node['content']['rawtext']),
			),
			'userinfo' => $this->filterUserInfo($node['content']['userinfo']),
			'avatar' => array(
				'hascustom' => 1,
				'0' => $this->avatarUrl($node['userid']),
			),

		);
	}

	function parseBlogEntry($node)
	{
		return array(
			'blog' => array(
				'blogid' => $node['nodeid'],
				'postedby_username'	=> $node['authorname'],
				'title'	=> $node['title'],
				'time' => $node['publishdate'],
				'avatarurl'	=> $this->avatarUrl($node['userid']),
				'blogtitle' => $node['content']['channeltitle'],
				'message' => $node['content']['rawtext'],
				'message_bbcode' => $node['content']['rawtext'],
				'message_plain' => strip_bbcode($node['content']['rawtext']),
				'comments_total' => $node['content']['startertotalcount'] - 1,
			),
		);
	}

	function parseForumInfo($node)
	{
		return array(
			'forumid' 		=> $node['nodeid'],
			'title'			=> $node['title'],
			'description'	=> $node['description'],
			'title_clean'	=> $node['htmltitle'],
			'description_clean'	=> strip_tags($node['description']),
			'prefixrequired' => 0,
		);
	}

	function parseThreadInfo($node)
	{
		$info = array(
			'title' => $node['title'],
			'threadid' => $node['nodeid'],
		);
		return $info;
	}

	// 
	// Used solely with output from 
	// vB_Api_Node->fetchChannelNodeTree
	//
	function parseForum($node)
	{
		$subforums = array();
		foreach ($node['subchannels'] as $subforum)
		{
			$subforums[] = $this->parseForum($subforum);
		}
		$top = vB_Api::instance('content_channel')->fetchTopLevelChannelIds();
		$top = $top['forum'];
		return array(
			'parentid'      => $node['parentid'] == $top ? -1 : $node['parentid'],
			'forumid' 		=> $node['nodeid'],
			'title'			=> $node['title'],
			'description'	=> $node['description'] !== null ? $node['description'] : '',
			'title_clean'	=> strip_tags($node['title']),
			'description_clean'	=> strip_tags($node['description']),
			'threadcount'		=> $node['textcount'],
			'replycount'	=> $node['totalcount'],
			'lastpostinfo' 	=> array(
				'lastthreadid' => $node['lastcontent']['nodeid'],
				'lastthreadtitle' => $node['lastcontent']['title'],
				'lastposter' => $node['lastcontent']['authorname'],
				'lastposterid'	=> $node['lastcontent']['userid'],
				'lastposttime' => $node['lastcontent']['created'],
			),
			'is_category'   => $node['category'],
			'is_link'       => 0,
			'subforums' 	=> $subforums,
		);
	}

	//
	//	The attachment hack implemented by MAPI creates
	//	temporary nodes we should remove if any get
	//	left behind.
	//
	//	We could make this more inclusive, but currently
	//	it's tightly coupled to the post process, for
	//	efficiency. (It only has to clean the immediate
	//	children of one node.)
	//
	function deleteTemporaryNodes($parentid)
	{
		$userinfo = vB_Library::instance('user')->fetchUserinfo();
		$nodeList = vB_Library::instance('node')->listNodes($parentid, 1, 1000, 1, null, null, false);
		ksort($nodeList);
		foreach ($nodeList as $node)
		{
			if ($node['userid'] == $userinfo['userid'] AND $node['title'] == 'mapi_placeholder')
			{
				$result = vB_Library::instance('node')->deleteNode($node['nodeid']);
			}
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
