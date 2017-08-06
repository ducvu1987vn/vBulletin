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
 * vB_Library_Functions
 *
 * @package vBApi
 * @access public
 */
class vB_Library_Admin extends vB_Library
{
	public static $modlogtypes = array(
		// Legacy Actions //
		'closed_thread'								=> 1,
		'opened_thread'								=> 2,
		'thread_moved_to_x'							=> 3,
		'thread_moved_with_redirect_to_a'			=> 4,
		'thread_copied_to_x'						=> 5,
		'thread_edited_visible_x_open_y_sticky_z'	=> 6,
		'thread_merged_with_x'						=> 7,
		'thread_split_to_x'							=> 8,
		'unstuck_thread'							=> 9,
		'stuck_thread'								=> 10,
		'attachment_removed'						=> 11,
		'attachment_uploaded'						=> 12,
		'poll_edited'								=> 13,
		'thread_softdeleted'						=> 14,
		'thread_removed'							=> 15,
		'thread_undeleted'							=> 16,
		'post_x_by_y_softdeleted'					=> 17,
		'post_x_by_y_removed'						=> 18,
		'post_y_by_x_undeleted'						=> 19,
		'post_x_edited'								=> 20,
		'approved_thread'							=> 21,
		'unapproved_thread'							=> 22,
		'thread_merged_from_multiple_threads'		=> 23,
		'unapproved_post'							=> 24,
		'approved_post'								=> 25,
		'post_merged_from_multiple_posts'			=> 26,
		'approved_attachment'						=> 27,
		'unapproved_attachment'						=> 28,
		'thread_title_x_changed'					=> 29,
		'thread_redirect_removed'					=> 30,
		'posts_copied_to_x'							=> 31,

		'album_x_by_y_edited'						=> 32,
		'album_x_by_y_deleted'						=> 33,
		'picture_x_in_y_by_z_edited'				=> 34,
		'picture_x_in_y_by_z_deleted'				=> 35,
		'picture_x_in_y_by_z_approved'				=> 46,

		'social_group_x_edited'						=> 36,
		'social_group_x_deleted'					=> 37,
		'social_group_x_members_managed'			=> 38,
		'social_group_picture_x_in_y_removed'		=> 39,

		'pc_by_x_on_y_edited'						=> 40,
		'pc_by_x_on_y_soft_deleted'					=> 41,
		'pc_by_x_on_y_removed'						=> 42,
		'pc_by_x_on_y_undeleted'					=> 43,
		'pc_by_x_on_y_unapproved'					=> 44,
		'pc_by_x_on_y_approved'						=> 45,

		'gm_by_x_in_y_for_z_edited'					=> 47,
		'gm_by_x_in_y_for_z_soft_deleted'			=> 48,
		'gm_by_x_in_y_for_z_removed'				=> 49,
		'gm_by_x_in_y_for_z_undeleted'				=> 50,
		'gm_by_x_in_y_for_z_unapproved'				=> 51,
		'gm_by_x_in_y_for_z_approved'				=> 52,

		'vm_by_x_for_y_edited'						=> 53,
		'vm_by_x_for_y_soft_deleted'				=> 54,
		'vm_by_x_for_y_removed'						=> 55,
		'vm_by_x_for_y_undeleted'					=> 56,
		'vm_by_x_for_y_unapproved'					=> 57,
		'vm_by_x_for_y_approved'					=> 58,

		'discussion_by_x_for_y_edited'				=> 59,
		'discussion_by_x_for_y_soft_deleted'		=> 60,
		'discussion_by_x_for_y_removed'				=> 61,
		'discussion_by_x_for_y_undeleted'			=> 62,
		'discussion_by_x_for_y_unapproved'			=> 63,
		'discussion_by_x_for_y_approved'			=> 64,

		// vB5 Actions //
		'node_opened_by_x'							=> 65,
		'node_closed_by_x'							=> 66,
		'node_copied_by_x'							=> 67,
		'node_moved_by_x'							=> 68,
		'node_featured_by_x'						=> 69,
		'node_unfeatured_by_x'						=> 70,
		'node_approved_by_x'						=> 71,
		'node_unapproved_by_x'						=> 72,
		'node_soft_deleted_by_x'					=> 73,
		'node_hard_deleted_by_x'					=> 74,
		'node_restored_by_x'						=> 75,
		'node_edited_by_x'							=> 76,
		'node_stuck_by_x'							=> 77,
		'node_unstuck_by_x'							=> 78,
		'node_merged_by_x'							=> 79,
	);

	public static $modlogactions = array();

	public static function buildElementCell($name, $text, $depth, $bold = false, $link = '', $do = '', $session = '', $subtext = '', $subtitle = '')
	{
		$cell = $name ? '<a name="'.$name.'">&nbsp;</a>' : '';
		$cell .= $bold ? '<b>' : '';
		$cell .= self::constructDepthMark($depth, '- - ');
		$cell .= $link ? '<a href="'.$link : '';
		$cell .= ($link AND $session) ? '?'.$session : '';
		$cell .= ($link AND $do AND $session) ? '&do=' . $do : '';
		$cell .= ($link AND $do AND !$session) ? '?do=' . $do : '';
		$cell .= $link ? '">' : '';
		$cell .= $text;
		$cell .= $link ? '</a>' : '';
		$cell .= $bold ? '</b>' : '';
		$cell .= $subtext ? '&nbsp;&nbsp;<span class="acpsmallfont" title = "'.$subtitle.'">('.$subtext.')</span>' : '';
		return $cell;
	}

	public static function buildCheckboxCell($name, $value = 1, $id = 'id', $checked = false, $disabled = false, $onclick = false, $vinput = true)
	{
		$current = $disabled ? 3 : 0;
		$cell = '<input type="checkbox" name="'.$name.'" id="'.$id.'"';
		$cell .= ($checked ? ' checked="checked" ' : '');
		$cell .= ($disabled ? ' disabled="disabled" ' : '');
		$cell .= ($onclick ? ' onclick="'.$onclick.';"' : '');
		$cell .= ' value="'.$value.'" />';
		$cell .= ($vinput ? ' <input id="v'.$id.'" type="hidden" name="v'.$name.'" value="'.$current.'" />' : '');
		return $cell;
	}

	public static function buildTextInputCell($name, $value = '', $size = 5, $title = '', $taborder = 1)
	{
		$cell = '<input type="text" class="bginput" name="'.$name.'" value="'.$value;
		$cell .= '" tabindex="'.$taborder.'" size="'.$size.'" title="'.$title.'" />';
		return $cell;
	}

	public static function buildDisplayCell($text, $bold = false, $smallfont = false, $istrike = false)
	{
		$cell = $smallfont ? '<span class="smallfont">' : '<span>';
		$cell .= $bold ? '<b>' : '';
		$cell .= $istrike ? '<i><s>' : '';
		$cell .= $text;
		$cell .= $istrike ? '</s></i>' : '';
		$cell .= $bold ? '</b>' : '';
		$cell .= '</span>';
		return $cell;
	}

	public static function buildActionCell($name, $options, $jsfunction = '', $button = 'Go', $onclick = false, $onchange = false)
	{
		$cell = '<select name="'.$name.'"';
		$cell .= ($onchange ? ' onchange="'.$jsfunction.';"' : '') . ' >';
		$cell .= construct_select_options($options) . '</select>';
		$cell .= "\t".'<input type="button" class="button" value="'.$button.'"';
		$cell .= ($onclick ? ' onclick="'.$jsfunction.';"' : '') . ' />';
		return $cell;
	}

	public static function constructDepthMark($name, $options, $jsfunction = '', $button = 'Go', $onclick = false, $onchange = false)
	{
		return $depthmark . str_repeat($depthchar, $depth);
	}

	/**
	* Save moderator actions
	*
	* @param	array	Array of log action data
	* @param	mixed	The log action type
	* @param	string	The log action text
	*
	*/
	public static function logModeratorAction($nodeinfo, $logtype, $action = array())
	{
		if (!$nodeinfo)
		{
			return;
		}

		if (isset($nodeinfo[0]))
		{
			$logs = $nodeinfo;
		}
		else
		{
			$logs[0] = $nodeinfo;
		}
		
		if (intval($logtype) == 0 
		AND $result = self::GetModlogType($logtype))
		{
			$logtype = $result;
		}

		$modlogsql = array();
		$request = vB::getRequest();
		$userinfo = vB::getCurrentSession()->fetch_userinfo();

		foreach ($logs AS $index => $log)
		{
			if (isset($log['action']))
			{
				$action = $log['action'];
				unset($log['action']);
			}

			if (!is_array($action))
			{
				$action = array('action' => $action);
			}

			$logtype = intval($logtype);
			$moderator = $userinfo['userid'];
			$nodeid = $log['nodeid'] ? intval($log['nodeid']) : 0;
			$nodetitle = $log['nodetitle'] ? $log['nodetitle'] : '';
			$nodeuserid = $log['nodeuserid'] ? intval($log['nodeuserid']) : 0;
			$nodeusername = $log['nodeusername'] ? $log['nodeusername'] : '';

			
			if (!isset($action['userid']))
			{
				$action['userid'] = $nodeuserid;
				$action['username'] = $nodeusername;
			}

			$modlogsql[] = array(
				$logtype,
				$moderator,
				time(),
				$nodeid,
				$nodetitle,
				serialize($action),
				$request->getIpAddress(),
			);
		}

		vB::getDbAssertor()->assertQuery('moderatorlog',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_MULTIPLEINSERT,
				vB_dB_Query::FIELDS_KEY => array('type', 'userid', 'dateline', 'nodeid', 'nodetitle', 'action', 'ipaddress'),
				vB_Db_Query::VALUES_KEY => $modlogsql
			)
		);
	}

	/**
	* Fetches the integer value associated with a moderator log action string
	*
	* @param	string	The moderator log action
	*
	* @return	integer
	*/
	public static function GetModlogType($logtext)
	{
		return !empty(self::$modlogtypes[$logtext]) ? self::$modlogtypes[$logtext] : 0;
	}

	/**
	* Fetches the string associated with a moderator log action integer value
	*
	* @param	integer	The moderator log action
	*
	* @return	string
	*/
	function GetModlogAction($logtype)
	{
		if (empty(self::$modlogactions))
		{
			self::$modlogactions = array_flip(self::$modlogtypes);
		}

		return !empty(self::$modlogactions[$logtype]) ? self::$modlogactions[$logtype] : '';
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
