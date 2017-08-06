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

require_once(DIR . '/vb/search/indexcontroller/queue.php');

/**
* Class to do data save/delete operations for profile messages
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 16:38:49 -0200 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_GroupMessage extends vB_DataManager
{
	/**
	* Array of recognised and required fields for groupmessage, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'gmid'           => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_INCR, vB_DataManager_Constants::VF_METHOD, 'verify_nonzero'),
		'discussionid'   => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_YES),
		'postuserid'     => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD, 'verify_userid'),
		'postusername'   => array(vB_Cleaner::TYPE_NOHTMLCOND, vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD, 'verify_username'),
		'dateline'       => array(vB_Cleaner::TYPE_UNIXTIME,   vB_DataManager_Constants::REQ_AUTO),
		'state'          => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_NO),
		'title'          => array(vB_Cleaner::TYPE_NOHTMLCOND, vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'pagetext'       => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_YES,  vB_DataManager_Constants::VF_METHOD),
		'ipaddress'      => array(vB_Cleaner::TYPE_STR,        vB_DataManager_Constants::REQ_AUTO, vB_DataManager_Constants::VF_METHOD),
		'allowsmilie'    => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
		'reportthreadid' => array(vB_Cleaner::TYPE_UINT,       vB_DataManager_Constants::REQ_NO),
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('gmid = %1$s', 'gmid');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'groupmessage';

	/**
	* Verifies the title is valid and sets up the title for saving (wordwrap, censor, etc).
	*
	* @param	string	Title text
	*
	* @param	bool	Whether the title is valid
	*/
	function verify_title(&$title)
	{
		// replace html-encoded spaces with actual spaces
		$title = preg_replace('/&#(0*32|x0*20);/', ' ', $title);

		$title = trim($title);

		if ($title == '')
		{
			$this->error('nosubject');
			return false;
		}

		if ($this->options['titlemaxchars'] AND $title != $this->existing['title'])
		{
			if (!empty($this->info['show_title_error']))
			{
				if (($titlelen = vB_String::vbStrlen($title)) > $this->options['titlemaxchars'])
				{
					// title too long
					$this->error('title_toolong', $titlelen, $this->options['titlemaxchars']);
					return false;
				}
			}
			else if (empty($this->info['is_automated']))
			{
				// not showing the title length error, just chop it
				$title = vbchop($title, $this->options['titlemaxchars']);
			}
		}

		require_once(DIR . '/includes/functions_newpost.php');
		// censor, remove all caps subjects, and htmlspecialchars title
		$title = fetch_no_shouting_text(fetch_censored_text($title));

		// do word wrapping
		$title = fetch_word_wrapped_string($title);

		return true;
	}

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_GroupMessage(&$registry, $errtype = vB_DataManager_Constants::ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		// Legacy Hook 'groupmessagedata_start' Removed //
	}

	/**
	 * Pre-Save code for a SG Message
	 *
	 * @param	boolean	Do we actually run the query?
	 *
	 * @return	boolean	Did this function run successfully?
	 */
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->condition)
		{
			if ($this->fetch_field('state') === null)
			{
				$this->set('state', 'visible');
			}

			if ($this->fetch_field('dateline') === null)
			{
				$this->set('dateline', TIMENOW);
			}

			if ($this->fetch_field('ipaddress') === null)
			{
				$this->set('ipaddress', ($this->options['logip'] ? IPADDRESS : ''));
			}

			if (!$this->info['preview'])
			{
				if (($this->options['floodchecktime'] > 0 AND empty($this->info['is_automated']) AND $this->fetch_field('postuserid') AND $this->is_flooding()) OR $this->is_duplicate())
				{
					return false;
				}
			}
		}

		if (!$this->verify_image_count('pagetext', 'allowsmilie', 'socialmessage'))
		{
			return false;
		}

		// New posts that aren't automated and are visible should be scanned
		if (!$this->condition AND $this->options['vb_antispam_type'] > 0 AND !empty($this->options['vb_antispam_key']) AND empty($this->info['is_automated']) AND $this->fetch_field('state') == 'visible' AND (!$this->options['vb_antispam_posts'] OR $this->info['user']['posts'] < $this->options['vb_antispam_posts']) AND !can_moderate())
		{
			require_once(DIR . '/includes/class_akismet.php');
			$akismet = new vB_Akismet($this->registry);
			$akismet->akismet_board = $this->options['bburl'];
			$akismet->akismet_key = $this->options['vb_antispam_key'];
			if ($akismet->verify_text(array('user_ip' => IPADDRESS, 'user_agent' => USER_AGENT, 'comment_type' => 'post', 'comment_author' => ($this->info['user']['userid'] ? $this->info['user']['username'] : $this->fetch_field('postusername')), 'comment_author_email' => $this->info['user']['email'], 'comment_author_url' => $this->info['user']['homepage'], 'comment_content' => $this->fetch_field('pagetext'))) === 'spam')
			{
				$this->set('state', 'moderation');
				$this->spamlog_insert = true;
			}
		}

		$return_value = true;
		// Legacy Hook 'groupmessagedata_presave' Removed //

		$this->presave_called = $return_value;
		return $return_value;
	}


	/**
	 * Overridding parent function to add search index updates
	 *
	* @param	boolean	Do the query?
	* @param	mixed	Whether to run the query now; see db_update() for more info
	* @param 	bool 	Whether to return the number of affected rows.
	* @param 	bool	Perform REPLACE INTO instead of INSERT
	8 @param 	bool	Perfrom INSERT IGNORE instead of INSERT
	*
	* @return	mixed	If this was an INSERT query, the INSERT ID is returned
	*/
	function save($doquery = true, $delayed = false, $affected_rows = false, $replace = false, $ignore = false)
	{
		// Call and get the new id
		$result = parent::save($doquery, $delayed, $affected_rows, $replace, $ignore);

		// Search index maintenance
		if ($result AND ($this->groupmessage['discussionid'] OR $this->existing['discussionid']))
		{
			// If result is the number (opposed to just TRUE) then use that, or which ever of the others is a number
			$do = (is_bool($result) == true ? (is_numeric($this->existing['gmid']) == true ? $this->existing['gmid'] : $this->existing['discussionid']) : $result);

			vb_Search_Indexcontroller_Queue::indexQueue('vBForum', 'SocialGroupMessage', 'index', $do);
		}

		return $result;
	}


	/**
	 * Deleted a SG Message
	 *
	 * @return	boolean	Was this message deleted successfully?
	*/
	function delete()
	{
		if ($gmid = $this->existing['gmid'])
		{
			// Search index maintenance - Remove for a hard delete.
			require_once(DIR . '/vb/search/core.php');

			if ($this->info['hard_delete'])
			{
				$this->assertor->delete('deletionlog', array('primaryid' => $gmid, 'type' => 'groupmessage'));

				$this->assertor->delete('groupmessage', array('gmid' => $gmid));

				$this->assertor->delete('moderation', array('primaryid' => $gmid, 'type' => 'groupmessage'));

				vb_Search_Indexcontroller_Queue::indexQueue('vBForum', 'SocialGroupMessage', 'delete', $gmid);

				// Logging?
			}
			else
			{
				$this->set('state', 'deleted');
				$this->save();
				vb_Search_Indexcontroller_Queue::indexQueue('vBForum', 'SocialGroupMessage', 'index', $gmid);

				$deletionman = new vB_DataManager_Deletionlog_GroupMessage($this->registry, vB_DataManager_Constants::ERRTYPE_SILENT);
				$deletionman->set('primaryid', $gmid);
				$deletionman->set('type', 'groupmessage');
				$deletionman->set('userid', $this->userinfo['userid']);
				$deletionman->set('username', $this->userinfo['username']);
				$deletionman->set('reason', $this->info['reason']);
				$deletionman->save();
				unset($deletionman);
			}

			/*$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "moderation WHERE primaryid = $gmid AND type = 'groupmessage'
			");*/
			$this->assertor->delete('moderation', array('primaryid' => $gmid, 'type' => 'groupmessage'));

			if (!$this->info['skip_build_counters'])
			{
				require_once(DIR . '/includes/functions_socialgroup.php');
				build_discussion_counters($this->existing['discussionid']);
				build_group_counters($this->info['group']['groupid']);
			}

			$this->post_delete();

			return true;
		}

		return false;
	}

	/**
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		// Legacy Hook 'groupmessagedata_delete' Removed //

		if ($this->info['group'])
		{
			update_owner_pending_gm_count($this->info['group']['creatoruserid']);
		}
	}


	/**
	*
	* @param	boolean	Do the query?
	*/
	function post_save_once($doquery = true)
	{
		$gmid = intval($this->fetch_field('gmid'));
		$discussionid = intval($this->fetch_field('discussionid'));

		if (!$this->condition)
		{
			if ($this->fetch_field('discussionid'))
			{
				$this->insert_dupehash($this->fetch_field('discussionid'));
			}

			// Update last post info on parent discussion
			if ($this->info['discussion'])
			{
				$dataman = new vB_DataManager_Discussion($this->registry, vB_DataManager_Constants::ERRTYPE_SILENT);
				$dataman->set_existing($this->info['discussion']);

				// Give group info to discussion
				if ($this->info['group'])
				{
					$dataman->setr_info('group', $this->info['group']);
				}

				if ($this->fetch_field('state') == 'visible' AND $this->fetch_field('dateline') == TIMENOW)
				{
					$dataman->set('lastpost', TIMENOW);
					$dataman->set('lastposter', $this->fetch_field('postusername'));
					$dataman->set('lastposterid', $this->fetch_field('postuserid'));
					$dataman->set('lastpostid', $gmid);
					$dataman->set_info('lastposttitle', $this->info['discussion']['title']);
				}

				if ($this->fetch_field('state') == 'visible')
				{
					$dataman->set('visible', 'visible + 1', false);
				}
				else if ($this->fetch_field('state') == 'moderation')
				{
					$dataman->set('moderation', 'moderation + 1', false);
				}
				$dataman->save();
				unset($dataman);
			}
		}

		if ($this->fetch_field('state') == 'moderation')
		{
			/*insert query*/
			$this->assertor->insertIgnore('moderation', array('primaryid' => $gmid, 'type' => 'groupmessage', 'dateline' => TIMENOW));
		}
		else if ($this->fetch_field('state') == 'visible' AND $this->existing['state'] == 'moderation')
		{
			// message was made visible, remove the moderation record
			$this->assertor->delete('moderation', array('primaryid' => $gmid, 'type' => 'groupmessage'));
		}

		if ($this->info['group'])
		{
			update_owner_pending_gm_count($this->info['group']['creatoruserid']);
		}

		// Legacy Hook 'groupmessagedata_postsave' Removed //
	}

	/**
	* Verifies that the specified user exists
	*
	* @param	integer	User ID
	*
	* @return 	boolean	Returns true if user exists
	*/
	function verify_userid(&$userid)
	{
		if ($userid == $this->userinfo['userid'])
		{
			$this->info['user'] = $this->userinfo;
			$return = true;
		}
		else if ($userinfo = fetch_userinfo($userid))
		{	// This case should hit the cache most of the time
			$this->info['user'] = $userinfo;
			$return = true;
		}
		else
		{
			$this->error('no_users_matched_your_query');
			$return = false;
		}

		if ($return)
		{
				$this->do_set('postusername', $this->info['user']['username']);
		}

		return $return;
	}

	/**
	* Converts ip address into an integer
	*
	* @param	string	IP Address
	*
	* @param	bool	Whether the ip is valid
	*/
	function verify_ipaddress(&$ipaddress)
	{
		// need to run it through sprintf to get the integer representation
		$ipaddress = sprintf('%u', ip2long($ipaddress));
		return true;
	}

	/**
	* Verifies the page text is valid and sets it up for saving.
	*
	* @param	string	Page text
	*
	* @param	bool	Whether the text is valid
	*/
	function verify_pagetext(&$pagetext)
	{
		if (empty($this->info['is_automated']))
		{
			if ($this->options['postmaxchars'] != 0 AND ($postlength = vB_String::vbStrlen($pagetext)) > $this->options['postmaxchars'])
			{
				$this->error('toolong', $postlength, $this->options['postmaxchars']);
				return false;
			}

			$this->options['postminchars'] = intval($this->options['postminchars']);
			if ($this->options['postminchars'] <= 0)
			{
				$this->options['postminchars'] = 1;
			}
			if (vB_String::vbStrlen(strip_bbcode($pagetext, $this->options['ignorequotechars'])) < $this->options['postminchars'])
			{
				$this->error('tooshort', $this->options['postminchars']);
				return false;
			}
		}

		return parent::verify_pagetext($pagetext);

	}


	/**
	 * Determines whether the message being posted would constitute flooding
	 *
	 * @return	boolean	Is this classed as flooding?
	 *
	 */
	function is_flooding()
	{
		$floodmintime = TIMENOW - $this->options['floodchecktime'];
		if (!can_moderate() AND $this->fetch_field('dateline') > $floodmintime)
		{
			$flood = $this->assertor->getRow('getDatelineGMHash', array('postuserid' => $this->fetch_field('postuserid'), 'dateline' => $floodmintime));
			if ($flood)
			{
				$this->error(
					'postfloodcheck',
					$this->options['floodchecktime'],
					($flood['dateline'] - $floodmintime)
				);
				return true;
			}
		}

		return false;
	}

	/**
	 * Is this a duplicate post of a message posted with the last 5 minutes?
	 *
	 * @return	boolean	whether this is a duplicate post or not
	 *
	 */
	function is_duplicate()
	{
		$dupemintime = TIMENOW - 300;
		if ($this->fetch_field('dateline') > $dupemintime)
		{
			// ### DUPE CHECK ###
			$dupehash = md5($this->fetch_field('groupid') . $this->fetch_field('pagetext') . $this->fetch_field('postuserid'));
			$dupe = $this->assertor->getRow('getDupleHashGM', array('postuserid' => $this->fetch_field('postuserid'), 'dupehash' => $dupehash, 'dateline' => $dupemintime));
			if ($dupe)
			{
				// Do we want to only check for the post for this same user, or for all users???
				if ($dupe['groupid'] == $this->fetch_field('groupid'))
				{
					$this->error('duplicate_post');
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Inserts a hash into the database for use with duplicate checking
	 *
	 * @param	integer	Group ID (-1 for current group from DM)
	 *
	 */
	function insert_dupehash($groupid = -1)
	{
		if ($groupid == -1)
		{
			$groupid = $this->fetch_field('groupid');
		}

		$postuserid = $this->fetch_field('postuserid');

		$dupehash = md5($groupid . $this->fetch_field('pagetext') . $postuserid);
		/*insert query*/
		$this->assertor->insert('groupmessage_hash', array('postuserid' => intval($postuserid), 'groupid' => intval($groupid), 'dupehash' => $dupehash, 'dateline' => TIMENOW));
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/

