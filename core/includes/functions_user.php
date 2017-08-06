<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright 2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * Fetches the Avatar Category Cache
 *
 * @param	array	User Information
 *
 * @return	array	Avatar Category Cache
 *
 */
function &fetch_avatar_categories(&$userinfo)
{
	global $vbulletin;
	static $categorycache = array();

	if (isset($categorycache["$userinfo[userid]"]))
	{
		return $categorycache["$userinfo[userid]"];
	}
	else
	{
		$categorycache["$userinfo[userid]"] = array();
	}

	$membergroups = fetch_membergroupids_array($userinfo);
	$infractiongroups = explode(',', str_replace(' ', '', $userinfo['infractiongroupids']));

	// ############### DISPLAY AVATAR CATEGORIES ###############
	// get all the available avatar categories
	$avperms = $vbulletin->db->query_read_slave("
		SELECT imagecategorypermission.imagecategoryid, usergroupid
		FROM " . TABLE_PREFIX . "imagecategorypermission AS imagecategorypermission, " . TABLE_PREFIX . "imagecategory AS imagecategory
		WHERE imagetype = 1
			AND imagecategorypermission.imagecategoryid = imagecategory.imagecategoryid
		ORDER BY imagecategory.displayorder
	");
	$noperms = array();
	while ($avperm = $vbulletin->db->fetch_array($avperms))
	{
		$noperms["{$avperm['imagecategoryid']}"][] = $avperm['usergroupid'];
	}
	foreach($noperms AS $imagecategoryid => $usergroups)
	{
		foreach($usergroups AS $usergroupid)
		{
			if (in_array($usergroupid, $infractiongroups))
			{
				$badcategories .= ",$imagecategoryid";
			}
		}
		if (!count(array_diff($membergroups, $usergroups)))
		{
			$badcategories .= ",$imagecategoryid";
		}
	}

	$categories = $vbulletin->db->query_read_slave("
		SELECT imagecategory.*, COUNT(avatarid) AS avatars
		FROM " . TABLE_PREFIX . "imagecategory AS imagecategory
		LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON
			(avatar.imagecategoryid=imagecategory.imagecategoryid)
		WHERE imagetype=1
		AND avatar.minimumposts <= " . intval($userinfo['posts']) . "
		AND avatar.avatarid <> " . intval($userinfo['avatarid']) . "
		AND imagecategory.imagecategoryid NOT IN (0$badcategories)
		GROUP BY imagecategory.imagecategoryid
		HAVING avatars > 0
		ORDER BY imagecategory.displayorder
	");

	while ($category = $vbulletin->db->fetch_array($categories))
	{
		$categorycache["$userinfo[userid]"]["{$category['imagecategoryid']}"] = $category;
	}

	return $categorycache["$userinfo[userid]"];
}

/**
 * Fetches the URL for a User's Avatar if we already have a database record.
 *
 * @param	array		The database record
 * @param	boolean	Whether to get the Thumbnailed avatar or not
 *
 * @return	array	Information regarding the avatar
 *
 */
function fetch_avatar_from_record($avatarinfo, $thumb = false)
{
	if (!$avatarinfo['userid'])
	{
		return false;
	}

 	$userid = $avatarinfo['userid'];

 	if (!empty($avatarinfo['avatarpath']))
 	{
 		return array($avatarinfo['avatarpath']);
 	}
 	else if ($avatarinfo['hascustomavatar'])
 	{
 		$avatarurl = array('hascustomavatar' => 1);

 		if (vB::$vbulletin->options['usefileavatar'])
 		{
 			$avatarurl[] = vB::$vbulletin->options['avatarurl'] . ($thumb ? '/thumbs' : '') . "/avatar{$userid}_{$avatarinfo['avatarrevision']}.gif";
 		}
 		else
 		{
 			$avatarurl[] = "image.php?u=$userid&amp;dateline=$avatarinfo[dateline]" . ($thumb ? '&amp;type=thumb' : '') ;
 		}

 		if ($thumb)
 		{
 			if ($avatarinfo['width_thumb'] AND $avatarinfo['height_thumb'])
 			{
 				$avatarurl[] = " width=\"$avatarinfo[width_thumb]\" height=\"$avatarinfo[height_thumb]\" ";
 			}
 		}
 		else
 		{
 			if ($avatarinfo['width'] AND $avatarinfo['height'])
 			{
 				$avatarurl[] = " width=\"$avatarinfo[width]\" height=\"$avatarinfo[height]\" ";
 			}
 		}
 		return $avatarurl;
 	}
 	else
 	{
 		return '';
 	}
}

/**
 * Fetches the URL for a User's Avatar
 *
 * @param	integer	The User ID
 * @param	boolean	Whether to get the Thumbnailed avatar or not
 *
 * @return	array	Information regarding the avatar
 *
 */
function fetch_avatar_url($userid, $thumb = false)
{
	global $vbulletin, $show;
	static $avatar_cache = array();

	if (isset($avatar_cache["$userid"]))
	{
		$avatarurl = $avatar_cache["$userid"]['avatarurl'];
		$avatarinfo = $avatar_cache["$userid"]['avatarinfo'];
	}
	else
	{
		if ($avatarinfo = fetch_userinfo($userid, 2, 0, 1))
		{
			$perms = cache_permissions($avatarinfo, false);
			$avatarurl = array();

			if ($avatarinfo['hascustomavatar'])
			{
				$avatarurl = array('hascustom' => 1);

				if ($vbulletin->options['usefileavatar'])
				{
					$avatarurl[] = $vbulletin->options['avatarurl'] . ($thumb ? '/thumbs' : '') . "/avatar{$userid}_{$avatarinfo['avatarrevision']}.gif";
				}
				else
				{
					$avatarurl[] = "image.php?" . vB::getCurrentSession()->get('sessionurl') . "u=$userid&amp;dateline=$avatarinfo[avatardateline]" . ($thumb ? '&amp;type=thumb' : '') ;
				}

				if ($thumb)
				{
					if ($avatarinfo['width_thumb'] AND $avatarinfo['height_thumb'])
					{
						$avatarurl[] = " width=\"$avatarinfo[width_thumb]\" height=\"$avatarinfo[height_thumb]\" ";
					}
				}
				else
				{
					if ($avatarinfo['avwidth'] AND $avatarinfo['avheight'])
					{
						$avatarurl[] = " width=\"$avatarinfo[avwidth]\" height=\"$avatarinfo[avheight]\" ";
					}
				}
			}
			elseif (!empty($avatarinfo['avatarpath']))
			{
				$avatarurl = array('hascustom' => 0, $avatarinfo['avatarpath']);
			}
			else
			{
				$avatarurl = '';
			}

		}
		else
		{
			$avatarurl = '';
		}

		$avatar_cache["$userid"]['avatarurl'] = $avatarurl;
		$avatar_cache["$userid"]['avatarinfo'] = $avatarinfo;
	}

	if ( // no avatar defined for this user
		empty($avatarurl)
		OR // visitor doesn't want to see avatars
		($vbulletin->userinfo['userid'] > 0 AND !$vbulletin->userinfo['showavatars'])
		OR // user has a custom avatar but no permission to display it
		(!$avatarinfo['avatarid'] AND !($perms['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar']) AND !$avatarinfo['adminavatar']) //
	)
	{
		$show['avatar'] = false;
	}
	else
	{
		$show['avatar'] = true;
	}

	return $avatarurl;
}

/**
 * Fetches the User's Avatar from Pre-processed information. Avatar data placed
 * in $userinfo (avatarurl, avatarwidth, avatarheight)
 *
 * @param	array	User Information
 * @param	boolean	Whether to return a Thumbnail
 * @param	boolean	Whether to return a placeholder Avatar if no avatar is found
*/
function fetch_avatar_from_userinfo(&$userinfo, $thumb = false, $returnfakeavatar = true)
{
	global $vbulletin;

	if (!empty($userinfo['avatarpath']))
	{
		// using a non custom avatar
		if ($thumb)
		{
			if (@file_exists(DIR . '/images/avatars/thumbs/' . $userinfo['avatarid'] . '.gif'))
			{
				$userinfo['avatarurl'] = 'images/avatars/thumbs/' . $userinfo['avatarid'] . '.gif';
			}
			else
			{
				// no width/height known, scale to the maximum allowed width
				$userinfo['avatarwidth'] = FIXED_SIZE_AVATAR_WIDTH;
				$userinfo['avatarurl'] = $userinfo['avatarpath'];
			}
		}
		else
		{
			$userinfo['avatarurl'] = $userinfo['avatarpath'];
		}
	}
	else if ($userinfo['hascustom'] OR $userinfo['hascustomavatar'])
	{
		if ($userinfo['adminavatar'])
		{
			$can_use_custom_avatar = true;
		}
		else
		{
			if (!isset($userinfo['permissions']))
			{
				cache_permissions($userinfo, false);
			}

			$can_use_custom_avatar = ($userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar']);
		}

		if ($can_use_custom_avatar)
		{
			// custom avatar
			if ($vbulletin->options['usefileavatar'])
			{
				if ($thumb AND @file_exists($vbulletin->options['avatarpath'] . "/thumbs/avatar$userinfo[userid]_$userinfo[avatarrevision].gif"))
				{
					$userinfo['avatarurl'] = $vbulletin->options['avatarurl'] . "/thumbs/avatar$userinfo[userid]_$userinfo[avatarrevision].gif";
				}
				else
				{
					$userinfo['avatarurl'] =  $vbulletin->options['avatarurl'] . "/avatar$userinfo[userid]_$userinfo[avatarrevision].gif";
				}
			}
			else
			{
				if ($thumb AND $userinfo['filedata_thumb'])
				{
					$userinfo['avatarurl'] = 'image.php?' . vB::getCurrentSession()->get('sessionurl') . 'u=' . $userinfo['userid'] . "&amp;dateline=$userinfo[avatardateline]&amp;type=thumb";
				}
				else
				{
					$userinfo['avatarurl'] = 'image.php?' . vB::getCurrentSession()->get('sessionurl') . 'u=' . $userinfo['userid'] . "&amp;dateline=$userinfo[avatardateline]";
				}
			}

			if ($thumb)
			{
				// use the known sizes if available, otherwise calculate as necessary
				if ($userinfo['width_thumb'])
				{
					$userinfo['avatarwidth'] = $userinfo['width_thumb'];
					$userinfo['avatarheight'] = $userinfo['height_thumb'];
				}
				else if ($userinfo['avwidth'] AND $userinfo['avheight'])
				{
					// resize to the most restrictive size; never increase size (ratios > 1)
					$resize_ratio = min(1, FIXED_SIZE_AVATAR_WIDTH / $userinfo['avwidth'], FIXED_SIZE_AVATAR_HEIGHT / $userinfo['avheight']);
					$userinfo['avatarwidth'] = floor($userinfo['avwidth'] * $resize_ratio);
					$userinfo['avatarheight'] = floor($userinfo['avheight'] * $resize_ratio);
				}
				else
				{
					// no width/height known, scale to the maximum allowed width
					$userinfo['avatarwidth'] = FIXED_SIZE_AVATAR_WIDTH;
				}
			}
			else
			{
				$userinfo['avatarwidth'] = $userinfo['avwidth'];
				$userinfo['avatarheight'] = $userinfo['avheight'];
			}
		}
	}

	// final case: didn't get an avatar, so use the fake one
	if (empty($userinfo['avatarurl']) AND $returnfakeavatar AND $vbulletin->options['avatarenabled'])
	{
		$userinfo['avatarurl'] = vB_Template_Runtime::fetchStyleVar('imgdir_misc') . '/unknown.gif';
	}
}

/**
 * Generates a totally random string
 *
 * @param	integer	Length of string to create
 *
 * @return	string	Generated String
 *
 */
function fetch_user_salt($length = 30)
{
	$salt = '';
	for ($i = 0; $i < $length; $i++)
	{
		$salt .= chr(vbrand(33, 126));
	}
	return $salt;
}

// nb: function verify_profilefields no longer exists, and is handled by vB_DataManager_User::set_userfields($values)

/**
 * Fetches the Profile Fields for a User input form
 *
 * @param	integer	Forum Type: 0 indicates a profile field, 1 indicates an option field
 *
 */
function fetch_profilefields($formtype = 0) // 0 indicates a profile field, 1 indicates an option field
{
	global $vbulletin, $customfields, $bgclass, $show;
	global $vbphrase, $altbgclass, $bgclass1, $tempclass;

	// get extra profile fields
	$profilefields = $vbulletin->db->query_read_slave("
		SELECT * FROM " . TABLE_PREFIX . "profilefield
		WHERE editable IN (1,2)
			AND form " . iif($formtype, '>= 1', '= 0'). "
		ORDER BY displayorder
	");
	while ($profilefield = $vbulletin->db->fetch_array($profilefields))
	{
		$profilefieldname = "field$profilefield[profilefieldid]";
		if ($profilefield['editable'] == 2 AND !empty($vbulletin->userinfo["$profilefieldname"]))
		{
			continue;
		}

		if ($formtype == 1 AND in_array($profilefield['type'], array('select', 'select_multiple')))
		{
			$show['optionspage'] = true;
		}
		else
		{
			$show['optionspage'] = false;
		}

		if (($profilefield['required'] == 1 OR $profilefield['required'] == 3) AND $profilefield['form'] == 0) // Ignore the required setting for fields on the options page
		{
			exec_switch_bg(1);
		}
		else
		{
			exec_switch_bg($profilefield['form']);
		}

		$tempcustom = fetch_profilefield($profilefield);

		// now add the HTML to the completed lists

		if (($profilefield['required'] == 1 OR $profilefield['required'] == 3) AND $profilefield['form'] == 0) // Ignore the required setting for fields on the options page
		{
			$customfields['required'] .= $tempcustom;
		}
		else
		{
			if ($profilefield['form'] == 0)
			{
				$customfields['regular'] .= $tempcustom;
			}
			else // not implemented
			{
				switch ($profilefield['form'])
				{
					case 1:
						$customfields['login'] .= $tempcustom;
						break;
					case 2:
						$customfields['messaging'] .= $tempcustom;
						break;
					case 3:
						$customfields['threadview'] .= $tempcustom;
						break;
					case 4:
						$customfields['datetime'] .= $tempcustom;
						break;
					case 5:
						$customfields['other'] .= $tempcustom;
						break;
					default:
						// Legacy Hook 'profile_fetch_profilefields_loc' Removed //
				}
			}
		}


	}
}

/**
 * Fetches a single profile Field
 *
 * @param	array	Profile Field Record from the database
 * @param	string	Template to wrap this profilefield in
 *
 * @return	string	HTML for this Profile Field
 *
 */
function fetch_profilefield($profilefield, $wrapper_template = 'userfield_wrapper')
{
	global $vbulletin, $customfields, $bgclass, $show;
	global $vbphrase, $altbgclass, $bgclass1, $tempclass;

	$profilefieldname = "field$profilefield[profilefieldid]";
	$optionalname = $profilefieldname . '_opt';
	$optional = '';
	$optionalfield = '';

	$profilefield['title'] = $vbphrase[$profilefieldname . '_title'];
	$profilefield['description'] = $vbphrase[$profilefieldname . '_desc'];
	$profilefield['currentvalue'] = $vbulletin->userinfo["$profilefieldname"];

	// Legacy Hook 'profile_fetch_profilefields' Removed //

	if ($profilefield['type'] == 'input')
	{
		$templater = vB_Template::create('userfield_textbox');
			$templater->register('profilefield', $profilefield);
			$templater->register('profilefieldname', $profilefieldname);
		$custom_field_holder = $templater->render();
	}
	else if ($profilefield['type'] == 'textarea')
	{
		$templater = vB_Template::create('userfield_textarea');
			$templater->register('profilefield', $profilefield);
			$templater->register('profilefieldname', $profilefieldname);
		$custom_field_holder = $templater->render();
	}
	else if ($profilefield['type'] == 'select')
	{
		$data = unserialize($profilefield['data']);
		$selectbits = '';
		$foundselect = 0;
		foreach ($data AS $key => $val)
		{
			$key++;
			$selected = '';
			if ($vbulletin->userinfo["$profilefieldname"])
			{
				if (trim($val) == $vbulletin->userinfo["$profilefieldname"])
				{
					$selected = 'selected="selected"';
					$foundselect = 1;
				}
			}
			else if ($profilefield['def'] AND $key == 1)
			{
				$selected = 'selected="selected"';
				$foundselect = 1;
			}
			$templater = vB_Template::create('userfield_select_option');
				$templater->register('key', $key);
				$templater->register('selected', $selected);
				$templater->register('val', $val);
			$selectbits .= $templater->render();
		}
		if ($profilefield['optional'])
		{
			if (!$foundselect AND (!empty($vbulletin->userinfo["$profilefieldname"]) OR $vbulletin->userinfo["$profilefieldname"] === '0'))
			{
				$optional = $vbulletin->userinfo["$profilefieldname"];
			}
			$templater = vB_Template::create('userfield_optional_input');
				$templater->register('optional', $optional);
				$templater->register('optionalname', $optionalname);
				$templater->register('profilefield', $profilefield);
				$templater->register('tabindex', $tabindex);
			$optionalfield = $templater->render();
		}
		if (!$foundselect)
		{
			$selected = 'selected="selected"';
		}
		else
		{
			$selected = '';
		}
		$show['noemptyoption'] = iif($profilefield['def'] != 2, true, false);
		$templater = vB_Template::create('userfield_select');
			$templater->register('optionalfield', $optionalfield);
			$templater->register('profilefield', $profilefield);
			$templater->register('profilefieldname', $profilefieldname);
			$templater->register('selectbits', $selectbits);
			$templater->register('selected', $selected);
		$custom_field_holder = $templater->render();
	}
	else if ($profilefield['type'] == 'radio')
	{
		$data = unserialize($profilefield['data']);
		$radiobits = '';
		$foundfield = 0;

		foreach ($data AS $key => $val)
		{
			$key++;
			$checked = '';
			if (!$vbulletin->userinfo["$profilefieldname"] AND $key == 1 AND $profilefield['def'] == 1)
			{
				$checked = 'checked="checked"';
			}
			else if (trim($val) == $vbulletin->userinfo["$profilefieldname"])
			{
				$checked = 'checked="checked"';
				$foundfield = 1;
			}
			$templater = vB_Template::create('userfield_radio_option');
				$templater->register('checked', $checked);
				$templater->register('key', $key);
				$templater->register('profilefieldname', $profilefieldname);
				$templater->register('val', $val);
			$radiobits .= $templater->render();
		}

		if ($profilefield['optional'])
		{
			if (!$foundfield AND $vbulletin->userinfo["$profilefieldname"])
			{
				$optional = $vbulletin->userinfo["$profilefieldname"];
			}
			$templater = vB_Template::create('userfield_optional_input');
				$templater->register('optional', $optional);
				$templater->register('optionalname', $optionalname);
				$templater->register('profilefield', $profilefield);
				$templater->register('tabindex', $tabindex);
			$optionalfield = $templater->render();
		}
		$templater = vB_Template::create('userfield_radio');
			$templater->register('optionalfield', $optionalfield);
			$templater->register('profilefield', $profilefield);
			$templater->register('profilefieldname', $profilefieldname);
			$templater->register('radiobits', $radiobits);
		$custom_field_holder = $templater->render();
	}
	else if ($profilefield['type'] == 'checkbox')
	{
		$data = unserialize($profilefield['data']);
		$radiobits = '';
		foreach ($data AS $key => $val)
		{
			if ($vbulletin->userinfo["$profilefieldname"] & pow(2,$key))
			{
				$checked = 'checked="checked"';
			}
			else
			{
				$checked = '';
			}
			$key++;
			$templater = vB_Template::create('userfield_checkbox_option');
				$templater->register('checked', $checked);
				$templater->register('key', $key);
				$templater->register('profilefieldname', $profilefieldname);
				$templater->register('val', $val);
			$radiobits .= $templater->render();
		}

		$templater = vB_Template::create('userfield_radio');
			$templater->register('optionalfield', $optionalfield);
			$templater->register('profilefield', $profilefield);
			$templater->register('profilefieldname', $profilefieldname);
			$templater->register('radiobits', $radiobits);
		$custom_field_holder = $templater->render();
	}
	else if ($profilefield['type'] == 'select_multiple')
	{
		$data = unserialize($profilefield['data']);
		$selectbits = '';

		if ($profilefield['height'] == 0)
		{
			$profilefield['height'] = count($data);
		}

		foreach ($data AS $key => $val)
		{
			if ($vbulletin->userinfo["$profilefieldname"] & pow(2, $key))
			{
				$selected = 'selected="selected"';
			}
			else
			{
				$selected = '';
			}
			$key++;
			$templater = vB_Template::create('userfield_select_option');
				$templater->register('key', $key);
				$templater->register('selected', $selected);
				$templater->register('val', $val);
			$selectbits .= $templater->render();
		}
		$templater = vB_Template::create('userfield_select_multiple');
			$templater->register('profilefield', $profilefield);
			$templater->register('profilefieldname', $profilefieldname);
			$templater->register('selectbits', $selectbits);
		$custom_field_holder = $templater->render();
	}

	$templater = vB_Template::create($wrapper_template);
		$templater->register('custom_field_holder', $custom_field_holder);
		$templater->register('profilefield', $profilefield);
	return $templater->render();
}

/**
 * Checks whether the email provided is banned from the forums
 *
 * @param	string	The email address to check
 *
 * @return	boolean	Whether the email address is banned or not
 *
 */
function is_banned_email($email)
{
	$options = vB::getDatastore()->get_value('options');

	if ($options['enablebanning'] AND !empty($options['banemail']))
	{
		$bannedemails = preg_split('/\s+/', $options['banemail'], -1, PREG_SPLIT_NO_EMPTY);

		foreach ($bannedemails AS $bannedemail)
		{
			if (is_valid_email($bannedemail))
			{
				$regex = '^' . preg_quote($bannedemail, '#') . '$';
			}
			else
			{
				$regex = preg_quote($bannedemail, '#') . ($options['aggressiveemailban'] ? '' : '$');
			}

			if (preg_match("#$regex#i", $email))
			{
				return 1;
			}
		}
	}

	return 0;
}

/**
 * (Re)Generates an Activation ID for a user
 *
 * @param	integer	User's ID
 * @param	integer	The group to move the user to when they are activated
 * @param	integer	0 for Normal Activation, 1 for Forgotten Password
 * @param	boolean	Whether this is an email change or not
 *
 * @return	string	The Activation ID
 *
 */
function build_user_activation_id($userid, $usergroupid, $type, $emailchange = 0)
{
	global $vbulletin;

	if ($usergroupid == 3 OR $usergroupid == 0)
	{ // stop them getting stuck in email confirmation group forever :)
		$usergroupid = 2;
	}

	vB::getDbAssertor()->assertQuery('useractivation', array(
		vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
		'userid' => $userid,
		'type' => $type,
	));

	$activateid = fetch_random_string(40);
	/*insert query*/
	vB::getDbAssertor()->assertQuery('user_replaceuseractivation', array(
		'userid' => $userid,
		'timenow' => vB::getRequest()->getTimeNow(),
		'activateid' => $activateid,
		'type' => $type,
		'usergroupid' => $usergroupid,
		'emailchange' => intval($emailchange),
	));

	if ($userinfo = vB_User::fetchUserinfo($userid))
	{
		$userdata = new vB_Datamanager_User($vbulletin, vB_DataManager_Constants::ERRTYPE_SILENT);
		$userdata->set_existing($userinfo);
		$userdata->set_bitfield('options', 'noactivationmails', 0);
		$userdata->save();
	}

	return $activateid;
}

/**
 * Constructs a Forum Jump Menu based on moderator permissions
 *
 * @param	integer	The "root" forum to work from
 * @param	integer	The ID of the forum that is currently selected
 * @param	integer	Characters to prepend to the item in the menu
 * @param	string	The moderator permission to check when building the Forum Jump Menu
 *
 * @return	string	The built forum Jump menu
 *
 */
function construct_mod_forum_jump($parentid = -1, $selectedid, $modpermission = '')
{
	global $vbulletin;

	if (empty($vbulletin->iforumcache))
	{
		cache_ordered_forums();
	}

	if (empty($vbulletin->iforumcache["$parentid"]) OR !is_array($vbulletin->iforumcache["$parentid"]))
	{
		return;
	}

	foreach($vbulletin->iforumcache["$parentid"] AS $forumid)
	{
		$forumperms = $vbulletin->userinfo['forumpermissions']["$forumid"];
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR $vbulletin->forumcache["$forumid"]['link'])
		{
			continue;
		}

		$children = construct_mod_forum_jump($forumid, $selectedid, $modpermission);

		if (!can_moderate($forumid, $modpermission) AND !$children)
		{
			continue;
		}

		// set $forum from the $vbulletin->forumcache
		$forum = $vbulletin->forumcache["$forumid"];

		$optionvalue = $forumid;
		$optiontitle = $forum['title_clean'];
		$optionclass = 'd' . iif($forum['depth'] > 4, 4, $forum['depth']);
		$optionselected = '';

		if ($selectedid == $optionvalue)
		{
			$optionselected = 'selected="selected"';
			$optionclass .= ' fjsel';
		}

		$forumjumpbits .= render_option_template($optiontitle, $optionvalue, $optionselected, $optionclass);

		$forumjumpbits .= $children;

	} // end foreach ($vbulletin->iforumcache[$parentid] AS $forumid)

	return $forumjumpbits;

}

/**
* Constructs the User's Custom CSS
*
* @param	array	An array of userinfo
* @param	bool	(Return) Whether to show the user css on/off switch to the user
*
* @return	string	HTML for the User's CSS
*/
function construct_usercss(&$userinfo, &$show_usercss_switch)
{
	global $vbulletin;

	// profile styling globally disabled
	if (!($vbulletin->options['enable_profile_styling']))
	{
		$show_usercss_switch = false;
		return '';
	}

	// check if permissions have changed and we need to rebuild this user's css
	if ($userinfo['hascachedcss'] AND $userinfo['cssbuildpermissions'] != $userinfo['permissions']['usercsspermissions'])
	{
		require_once(DIR . '/includes/class_usercss.php');
		$usercss = new vB_UserCSS($vbulletin, $userinfo['userid'], false);
		$userinfo['cachedcss'] = $usercss->update_css_cache();
	}

	if (!($vbulletin->userinfo['options'] & $vbulletin->bf_misc_useroptions['showusercss']) AND $vbulletin->userinfo['userid'] != $userinfo['userid'])
	{
		// user has disabled viewing css; they can reenable
		$show_usercss_switch = (trim($userinfo['cachedcss']) != '');
		$usercss = '';
	}
	else if (trim($userinfo['cachedcss']))
	{
		$show_usercss_switch = true;
		$userinfo['cachedcss'] = str_replace('/*sessionurl*/', vB::getCurrentSession()->get('sessionurl_js'), $userinfo['cachedcss']);
		$templater = vB_Template::create('memberinfo_usercss');
			$templater->register('userinfo', $userinfo);
		$usercss = $templater->render();
	}
	else
	{
		$show_usercss_switch = false;
		$usercss = '';
	}

	return $usercss;
}

/**
* Constructs the User's Custom CSS Switch Phrase
*
* @param	bool	If the switch is going to be shown or not
* @param	string	The phrase to use (Reference)
*
* @return	void
*/
function construct_usercss_switch($show_usercss_switch, &$usercss_switch_phrase)
{
	global $vbphrase, $vbulletin;

	if ($show_usercss_switch AND $vbulletin->userinfo['userid'])
	{
		if ($vbulletin->userinfo['options'] & $vbulletin->bf_misc_useroptions['showusercss'])
		{
			$usercss_switch_phrase = $vbphrase['hide_user_customizations'];
		}
		else
		{
			$usercss_switch_phrase = $vbphrase['show_user_customizations'];
		}
	}
}

/**
 * Gets the relationship of one user to another.
 *
 * The relationship level can be:
 *
 * 	3 - User 2 is a Friend of User 1 or is a Moderator
 *  2 - User 2 is on User 1's contact list
 *  1 - User 2 is a registered forum member
 *  0 - User 2 is a guest or ignored user
 *
 * @param int	$user1						- Id of user 1
 * @param int	$user2						- Id of user 2
 */
function fetch_user_relationship($user1, $user2)
{
	global $vbulletin;
	static $privacy_cache = array();

	$user1 = intval($user1);
	$user2 = intval($user2);

	if (!$user2)
	{
		return 0;
	}

	if (isset($privacy_cache["$user1-$user2"]))
	{
		return $privacy_cache["$user1-$user2"];
	}

	if ($user1 == $user2 OR can_moderate(0, '', $user2))
	{
		$privacy_cache["$user1-$user2"] = 3;
		return 3;
	}

	$contacts = vB::getDbAssertor()->assertQuery('userlist', array('userid' => $user1, 'relationid' => $user2));


	$return_value = 1;
	foreach ($contacts as $contact)
	{
		if ($contact['friend'] == 'yes')
		{
			$return_value = 3;
			break;
		}
		else if ($contact['type'] == 'ignore')
		{
			$return_value = 0;
			break;
		}
		else if ($contact['type'] == 'buddy')
		{
			// no break here, we neeed to make sure there is no other more definitive record
			$return_value = 2;
		}
	}


	$privacy_cache["$user1-$user2"] = $return_value;
	return $return_value;
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 70726 $
|| ####################################################################
\*======================================================================*/
?>
