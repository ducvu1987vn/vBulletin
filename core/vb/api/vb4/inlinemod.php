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
 * vB_Api_Vb4_inlinemod
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Vb4_inlinemod extends vB_Api
{
	public function unapproveattachments($plist)
	{
		$cleaner = vB::getCleaner();
		$plist = $cleaner->clean($plist, vB_Cleaner::TYPE_ARRAY);

		if (empty($plist))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}

		$postids = array();
		foreach ($plist as $postid => $nothing)
		{
			$result = vB_Api::instance('node')->getNodeAttachments($postid);
			if ($result === null || isset($result['errors']))
			{
				return vB_Library::instance('vb4_functions')->getErrorResponse($result);
			}

			$attachmentids = array();
			foreach ($result as $attachmentid => $nothing)
			{
				$attachmentids[] = $attachmentid;
			}

			$result = vB_Api::instance('node')->setApproved($attachmentids, false);

			if ($result === null || isset($result['errors']))
			{
				return vB_Library::instance('vb4_functions')->getErrorResponse($result);
			}
		}

		return array('response' => array('errormessage' => 'redirect_inline_unapprovedattachments'));
	}

	public function approveattachments($plist)
	{
		$cleaner = vB::getCleaner();
		$plist = $cleaner->clean($plist, vB_Cleaner::TYPE_ARRAY);

		if (empty($plist))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}

		$postids = array();
		foreach ($plist as $postid => $nothing)
		{
			$result = vB_Api::instance('node')->getNodeAttachments($postid);
			if ($result === null || isset($result['errors']))
			{
				return vB_Library::instance('vb4_functions')->getErrorResponse($result);
			}
			$attachmentids = array();
			foreach ($result as $attachmentid => $nothing)
			{
				$attachmentids[] = $attachmentid;
			}

			$result = vB_Api::instance('node')->setApproved($attachmentids, true);

			if ($result === null || isset($result['errors']))
			{
				return vB_Library::instance('vb4_functions')->getErrorResponse($result);
			}
		}

		return array('response' => array('errormessage' => 'redirect_inline_approvedattachments'));
	}

	public function unapproveposts($plist)
	{
		$cleaner = vB::getCleaner();
		$plist = $cleaner->clean($plist, vB_Cleaner::TYPE_ARRAY);

		if (empty($plist))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}

		$postids = array();
		foreach ($plist as $postid => $nothing)
		{
			$postids[] = $postid;
		}

		$result = vB_Api::instance('node')->setApproved($postids, false);

		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		return array('response' => array('errormessage' => 'redirect_inline_unapprovedposts'));
	}

	public function approveposts($plist)
	{
		$cleaner = vB::getCleaner();
		$plist = $cleaner->clean($plist, vB_Cleaner::TYPE_ARRAY);

		if (empty($plist))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}

		$postids = array();
		foreach ($plist as $postid => $nothing)
		{
			$postids[] = $postid;
		}

		$result = vB_Api::instance('node')->setApproved($postids, true);

		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		return array('response' => array('errormessage' => 'redirect_inline_approvedposts'));
	}

	public function unapprovethread($tlist)
	{
		$cleaner = vB::getCleaner();
		$tlist = $cleaner->clean($tlist, vB_Cleaner::TYPE_ARRAY);

		if (empty($tlist))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}

		$threadids = array();
		foreach ($tlist as $threadid => $nothing)
		{
			$threadids[] = $threadid;
		}

		$result = vB_Api::instance('node')->setApproved($threadids, false);

		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		return array('response' => array('errormessage' => 'redirect_inline_unapprovedthreads'));
	}

	public function approvethread($tlist)
	{
		$cleaner = vB::getCleaner();
		$tlist = $cleaner->clean($tlist, vB_Cleaner::TYPE_ARRAY);

		if (empty($tlist))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}

		$threadids = array();
		foreach ($tlist as $threadid => $nothing)
		{
			$threadids[] = $threadid;
		}

		$result = vB_Api::instance('node')->setApproved($threadids, true);

		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		return array('response' => array('errormessage' => 'redirect_inline_approvedthreads'));
	}

	public function docopyposts($postids, $destforumid, $title)
	{
		$cleaner = vB::getCleaner();
		$postids = $cleaner->clean($postids, vB_Cleaner::TYPE_STR);
		$title = $cleaner->clean($title, vB_Cleaner::TYPE_STR);
		$destforumid = $cleaner->clean($destforumid, vB_Cleaner::TYPE_UINT);

		$postids = explode(',', $postids);
		if (empty($postids))
		{
			return array('response' => array('errormessage' => 'no_applicable_posts_selected'));
		}
		if (empty($title))
		{
			return array('response' => array('errormessage' => 'notitle'));
		}
		if (empty($destforumid))
		{
			return array('response' => array('errormessage' => 'moveillegalforum'));
		}

		$result = vB_Api::instance('node')->cloneNodes($postids, $destforumid, $title);

		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		return array('response' => array('errormessage' => 'redirect_inline_copiedposts'));
	}

	public function domoveposts($postids, $destforumid, $title = "")
	{
		$cleaner = vB::getCleaner();
		$postids = $cleaner->clean($postids, vB_Cleaner::TYPE_STR);
		$title = $cleaner->clean($title, vB_Cleaner::TYPE_STR);
		$destforumid = $cleaner->clean($destforumid, vB_Cleaner::TYPE_UINT);

		$postids = explode(',', $postids);
		if (empty($postids))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}
		if (empty($destforumid))
		{
			return array('response' => array('errormessage' => 'moveillegalforum'));
		}

		$result = vB_Api::instance('node')->moveNodes($postids, $destforumid, true, $title);

		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		return array('response' => array('errormessage' => 'redirect_inline_movedposts'));
	}

	public function domovethreads($threadids, $destforumid)
	{
		$cleaner = vB::getCleaner();
		$threadids = $cleaner->clean($threadids, vB_Cleaner::TYPE_STR);
		$destforumid = $cleaner->clean($destforumid, vB_Cleaner::TYPE_UINT);

		$threadids = explode(',', $threadids);
		if (empty($threadids))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}
		if (empty($destforumid))
		{
			return array('response' => array('errormessage' => 'moveillegalforum'));
		}

		foreach ($threadids as $threadid)
		{
			$result = vB_Api::instance('node')->moveNodes(array($threadid), $destforumid, true);

			if ($result === null || isset($result['errors']))
			{
				return vB_Library::instance('vb4_functions')->getErrorResponse($result);
			} 
		}

		return array('response' => array('errormessage' => 'redirect_inline_moved'));
	}

	public function undeleteposts($plist)
	{
		$cleaner = vB::getCleaner();
		$plist = $cleaner->clean($plist, vB_Cleaner::TYPE_ARRAY);

		if (empty($plist))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}

		$postids = array();
		foreach ($plist as $postid => $nothing)
		{
			$postids[] = $postid;
		}

		$result = vB_Api::instance('node')->undeleteNodes($postids);

		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		return array('response' => array('errormessage' => 'redirect_inline_undeleteposts'));
	}

	public function undeletethread($tlist)
	{
		$cleaner = vB::getCleaner();
		$tlist = $cleaner->clean($tlist, vB_Cleaner::TYPE_ARRAY);

		if (empty($tlist))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}

		$threadids = array();
		foreach ($tlist as $threadid => $nothing)
		{
			$threadids[] = $threadid;
		}

		$result = vB_Api::instance('node')->undeleteNodes($threadids);

		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		return array('response' => array('errormessage' => 'redirect_inline_undeleted'));
	}

	public function dodeleteposts($postids, $deletetype, $deletereason = null)
	{
		$cleaner = vB::getCleaner();
		$postids = $cleaner->clean($postids, vB_Cleaner::TYPE_STR);
		$deletetype = $cleaner->clean($deletetype, vB_Cleaner::TYPE_UINT);
		$deletereason = $cleaner->clean($deletereason, vB_Cleaner::TYPE_STR);

		$postids = explode(',', $postids);
		if (empty($postids))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}
		if (empty($deletereason))
		{
			$deletereason = null;
		}
		$hard = false;
		if ($deletetype == 2)
		{
			$hard = true;
		}

		$result = vB_Api::instance('node')->deleteNodes($postids, $hard, $deletereason);

		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		return array('response' => array('errormessage' => 'redirect_inline_deletedposts'));
	}

	public function dodeletethreads($threadids, $deletetype, $deletereason = null)
	{
		$cleaner = vB::getCleaner();
		$threadids = $cleaner->clean($threadids, vB_Cleaner::TYPE_STR);
		$deletetype = $cleaner->clean($deletetype, vB_Cleaner::TYPE_UINT);
		$deletereason = $cleaner->clean($deletereason, vB_Cleaner::TYPE_STR);

		$threadids = explode(',', $threadids);
		if (empty($threadids))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}
		if (empty($deletereason))
		{
			$deletereason = null;
		}
		$hard = false;
		if ($deletetype == 2)
		{
			$hard = true;
		}

		$result = vB_Api::instance('node')->deleteNodes($threadids, $hard, $deletereason);

		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		return array('response' => array('errormessage' => 'redirect_inline_deleted'));
	}

	public function unstick($tlist)
	{
		$cleaner = vB::getCleaner();
		$tlist = $cleaner->clean($tlist, vB_Cleaner::TYPE_ARRAY);

		if (empty($tlist))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}

		$threadids = array();
		foreach ($tlist as $threadid => $nothing)
		{
			$threadids[] = $threadid;
		}
		$result = vB_Api::instance('node')->unsetSticky($threadids);

		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		return array('response' => array('errormessage' => 'redirect_inline_unstuck'));
	}

	public function stick($tlist)
	{
		$cleaner = vB::getCleaner();
		$tlist = $cleaner->clean($tlist, vB_Cleaner::TYPE_ARRAY);

		if (empty($tlist))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}

		$threadids = array();
		foreach ($tlist as $threadid => $nothing)
		{
			$threadids[] = $threadid;
		}
		$result = vB_Api::instance('node')->setSticky($threadids);

		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		return array('response' => array('errormessage' => 'redirect_inline_stuck'));
	}

	public function close($tlist)
	{
		$cleaner = vB::getCleaner();
		$tlist = $cleaner->clean($tlist, vB_Cleaner::TYPE_ARRAY);

		if (empty($tlist))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}

		foreach ($tlist as $threadid => $nothing)
		{
			$result = vB_Api::instance('node')->closeNode($threadid);

			if ($result === null || isset($result['errors']))
			{
				return vB_Library::instance('vb4_functions')->getErrorResponse($result);
			}
		}

		return array('response' => array('errormessage' => 'redirect_inline_closed'));
	}

	public function open($tlist)
	{
		$cleaner = vB::getCleaner();
		$tlist = $cleaner->clean($tlist, vB_Cleaner::TYPE_ARRAY);

		if (empty($tlist))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}

		foreach ($tlist as $threadid => $nothing)
		{
			$result = vB_Api::instance('node')->openNode($threadid);

			if ($result === null || isset($result['errors']))
			{
				return vB_Library::instance('vb4_functions')->getErrorResponse($result);
			}
		}

		return array('response' => array('errormessage' => 'redirect_inline_opened'));
	}

	public function spamthread($tlist)
	{
		$cleaner = vB::getCleaner();
		$tlist = $cleaner->clean($tlist, vB_Cleaner::TYPE_ARRAY);

		if (empty($tlist))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}
		return array('response' => array());
	}

	// Support only threads for now. This can support posts in the future.
	public function dodeletespam($threadids, $deletetype)
	{
		$cleaner = vB::getCleaner();
		$threadids = $cleaner->clean($threadids, vB_Cleaner::TYPE_STR);
		$deletetype = $cleaner->clean($deletetype, vB_Cleaner::TYPE_UINT);

		$threadids = explode(',', $threadids);
		if (empty($threadids))
		{
			return array('response' => array('errormessage' => 'you_did_not_select_any_valid_threads'));
		}

		$deletereason = null;
		$hard = false;
		if ($deletetype == 2)
		{
			$hard = true;
		}

		$result = vB_Api::instance('node')->deleteNodes($threadids, $hard, $deletereason);

		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		return array('response' => array('errormessage' => 'redirect_inline_deleted'));
	}

}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
