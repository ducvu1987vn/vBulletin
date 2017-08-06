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
 * vB_Api_Vb4_blog
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Vb4_blog extends vB_Api
{
	public function post_postcomment($message, $blogid)
	{
		$cleaner = vB::getCleaner();
		$blogid = $cleaner->clean($blogid, vB_Cleaner::TYPE_UINT);
		$message = $cleaner->clean($message, vB_Cleaner::TYPE_STR);

		if (empty($blogid) || empty($message))
		{
			return array('response' => array('errormessage' => array('invalidid')));
		}

		$data = array(
			'parentid' => $blogid,
			'title' => '(Untitled)',
			'rawtext' => $message,
			'created' => vB::getRequest()->getTimeNow(),
		);

		$result = vB_Api::instance('content_text')->add($data);
		if (empty($result) || !empty($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}
		return array('response' => array('errormessage' => 'redirect_blog_commentthanks'));
	}

	public function post_comment()
	{
		$options = vB::getDatastore()->getValue('options');
		$out = array(
			'response' => array(
				'content' => array(
					'postminchars' => $options['postminchars'],
					'titlemaxchars' => $options['titlemaxchars'],
				),
			),
		);
		return $out;
	}

	public function post_updateblog(
		$message,
		$title,
		$blogid = null,
		$posthash = null,
		$allowcomments = null,
		$publish = null,
		$status = null)
	{
		$userinfo = vB_Api::instance('user')->fetchUserinfo();
		if ($userinfo['userid'] < 1)
		{
			return array('response' => array('errormessage' => array('nopermission_loggedout')));
		}

		$cleaner = vB::getCleaner();
		$blogid = $cleaner->clean($blogid, vB_Cleaner::TYPE_UINT);
		$posthash = $cleaner->clean($posthash, vB_Cleaner::TYPE_STR);
		$message = $cleaner->clean($message, vB_Cleaner::TYPE_STR);
		$title = $cleaner->clean($title, vB_Cleaner::TYPE_STR);
		$allowcomments = $cleaner->clean($allowcomments, vB_Cleaner::TYPE_UINT);
		$publish = $cleaner->clean($publish, vB_Cleaner::TYPE_ARRAY);
		$status = $cleaner->clean($status, vB_Cleaner::TYPE_STR);

		if (empty($title) || empty($message))
		{
			return array('response' => array('errormessage' => 'invalidid'));
		}

		$blogchannel = vB_Library::instance('vb4_functions')->getUsersBlogChannel();
		if (empty($blogchannel))
		{
			return array('response' => array('errormessage' => 'nopermission_loggedin'));
		}

		$data = array(
			'parentid' => $blogchannel,
			'title' => $title,
			'rawtext' => $message,
			'created' => vB::getRequest()->getTimeNow(),
		);

		if ($allowcomments)
		{
			$data['nodeoptions'] = 10;
		}

		if (!empty($status))
		{
			if($status == 'publish_on')
			{
				if (!empty($publish))
				{
					$now = vB::getRequest()->getTimeNow();
					if ($publish == 'minute')
					{
						$data['publishdate'] = $now + (60);
					}
					if ($publish == 'hour')
					{
						$data['publishdate'] = $now + (60*60);
					}
					if ($publish == 'day')
					{
						$data['publishdate'] = $now + (60*60*24);
					}
					if ($publish == 'month')
					{
						$data['publishdate'] = $now + (60*60*24*30);
					}
					if ($publish == 'year')
					{
						$data['publishdate'] = $now + (60*60*24*365);
					}
				}
			}
			else if ($status == 'draft')
			{
				$data['showpublished'] = 0;
			}
		}

		//
		// If we have a posthash, we should do an update of
		// a node already created by blog.post_newblog.
		//
		if (!empty($blogid))
		{
			unset($data['created']);
			unset($data['parentid']);
			$result = vB_Api::instance('content_text')->update($blogid, $data);
		}
		else
		{
			$result = vB_Api::instance('content_text')->add($data);
			$blogid = $result;
		}

		if (empty($result) || !empty($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		vB_Library::instance('vb4_posthash')->appendAttachments($blogid, $posthash);
		return array('response' => array('errormessage' => 'redirect_blog_entrythanks'));
	}

	public function post_newblog()
	{
		$userinfo = vB_Api::instance('user')->fetchUserinfo();
		if ($userinfo['userid'] < 1)
		{
			return array('response' => array('errormessage' => 'nopermission_loggedout'));
		}

		$blogchannel = vB_Library::instance('vb4_functions')->getUsersBlogChannel();
		if (empty($blogchannel))
		{
			return array('response' => array('errormessage' => 'nopermission_loggedin'));
		}

		$blogattach = vB::getUserContext()->getChannelPermission('forumpermissions', 'canpostattachment', $blogchannel) ? 1 : 0;

		$options = vB::getDatastore()->getValue('options');
		$out = array(
			'show' => array(
				'globalcategorybits' => vB_Library::instance('vb4_functions')->getGlobalBlogCategories(),
				'localcategorybits' => vB_Library::instance('vb4_functions')->getLocalBlogCategories(),
				'smiliebox' => 0,
				'blogattach' => $blogattach,
			),
			'response' => array(
				'content' => array(
					'poststarttime' => vB::getRequest()->getTimeNow(),
					'posthash' => vB_Library::instance('vb4_posthash')->getNewPosthash(),
					'postminchars' => $options['postminchars'],
					'titlemaxchars' => $options['titlemaxchars'],
				),
			),
		);
		return $out;
	}

	public function blog($blogid, $pagenumber = 1, $perpage = 10)
	{
		$cleaner = vB::getCleaner();
		$blogid = $cleaner->clean($blogid, vB_Cleaner::TYPE_UINT);
		$pagenumber = $cleaner->clean($pagenumber, vB_Cleaner::TYPE_UINT);
		$perpage = $cleaner->clean($perpage, vB_Cleaner::TYPE_UINT);

		$result = vB_Api::instance('node')->getFullContentforNodes(array($blogid));
		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}
		$result = $result[0];

		$postcomment = $result['content']['can_comment'];
		$blog = vB_Library::instance('vb4_functions')->parseBlogEntry($result);
		$blogheader = vB_Library::instance('vb4_functions')->parseBlogHeader($result);

		$result = vB_Api::instance('node')->listNodeFullContent($blogid, $pagenumber, $perpage);
		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		$blog_comments = array();
		foreach ($result as $commentid => $comment)
		{
			$blog_comments[] = vB_Library::instance('vb4_functions')->parseBlogComment($comment);
		}

		$pagenav = vB_Library::instance('vb4_functions')->pageNav($pagenumber, $perpage, $result['totalRecords']);

		$out = array(
			'response' => array(
				'content' => array(
					'blog' => $blog['blog'],
					'responsebits' => $blog_comments,
					'pagenav' => $pagenav,
				),
			),
			'show' => array(
				'edit' => 0,
				'postcomment' => $postcomment,
			),
		);

		$out['response']['content'] = array_merge($out['response']['content'], $blogheader);
		return $out;
	}

	public function bloglist($blogtype = null, $pagenumber = 1, $perpage = 10, $userid = 0)
	{
		$cleaner = vB::getCleaner();
		$blogtype = $cleaner->clean($blogtype, vB_Cleaner::TYPE_STR);
		$pagenumber = $cleaner->clean($pagenumber, vB_Cleaner::TYPE_UINT);
		$perpage = $cleaner->clean($perpage, vB_Cleaner::TYPE_UINT);
		$userid = $cleaner->clean($userid, vB_Cleaner::TYPE_UINT);

		$blog_channel = vB_Api::instanceInternal('blog')->getBlogChannel();
		$search = array('channel' => $blog_channel);
		$search['view'] = vB_Api_Search::FILTER_VIEW_TOPIC;

		$search['sort']['lastcontent'] = 'desc';
		$search['starter_only'] = 1;
		$search['nolimit'] = 1;
		if (!empty($blogtype))
		{
			if ($blogtype == 'recent')
			{
				// Default, above.
			}
			else if ($blogtype == 'best')
			{
				$search['sort'] = 'relevance';
			}
		}

		if (!empty($userid))
		{
			$search['authorid'] = $userid;
		}

		$result = vB_Api::instance('search')->getInitialResults($search, $perpage, $pagenumber);

		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		$blogs = array();
		$first = true;
		$blogheader = array();
		foreach ($result['results'] as $blogid => $blog)
		{
			if ($first && !empty($userid))
			{
				$first = false;
				$blogheader = vB_Library::instance('vb4_functions')->parseBlogHeader($blog);
			}
			$blogs[] = vB_Library::instance('vb4_functions')->parseBlogEntry($blog);
		}

		$pagenav = vB_Library::instance('vb4_functions')->pageNav($pagenumber, $perpage, $result['totalRecords']);

		$out = array(
			'response' => array(
				'content' => array(
					'blogbits' => $blogs,
					'pagenav' => $pagenav,
				),
			),
		);

		if (!empty($userid))
		{
			$out['response']['content'] = array_merge($out['response']['content'], $blogheader);
		}
		return $out;
	}

}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
