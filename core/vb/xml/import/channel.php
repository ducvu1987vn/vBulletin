<?php

/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 5.0.0
  || # ---------------------------------------------------------------- # ||
  || # Copyright  2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

class vB_Xml_Import_Channel extends vB_Xml_Import
{
	protected $referencedRoutes;
	protected $referencedParents;
	protected $contenttypes;
	protected $bf_moderatorpermissions;
	protected $bf_administratorpermissions;
	protected $bf_albumpermissions;
	protected $bf_forumpermissions;
	protected $bf_forumpermissions2;
	protected $bf_socialgrouppermissions;

	/**
	 * Checks if all referenced routes are already defined
	 * Also sets a class attribute to be used while importing
	 */
	protected function checkRoutes()
	{
		$requiredRoutes = array();

		$channels = is_array($this->parsedXML['channel'][0]) ? $this->parsedXML['channel'] : array($this->parsedXML['channel']);
		foreach ($channels AS $channel)
		{
			if (isset($channel['node']['routeguid']))
			{
				$requiredRoutes[] = $channel['node']['routeguid'];
			}
		}

		$existingRoutes = $this->db->getRows('routenew', array('guid' => $requiredRoutes));
		foreach ($existingRoutes AS $route)
		{
			$this->referencedRoutes[$route['guid']] = $route['routeid'];
		}

		if (!$this->options & vB_Xml_Import::OPTION_IGNOREMISSINGROUTES)
		{
			$missingRoutes = array_diff($requiredRoutes, array_keys($this->referencedRoutes));
			if (!empty($missingRoutes))
			{
				throw new Exception('Reference to undefined routes(s): ' . implode(' ', $missingRoutes));
			}
		}
	}

	/**
	 * Checks if all referenced parents are already defined or imported
	 * Also sets a class attribute to be used while importing
	 */
	protected function checkParents()
	{
		$requiredChannels = array();

		$channels = is_array($this->parsedXML['channel'][0]) ? $this->parsedXML['channel'] : array($this->parsedXML['channel']);
		foreach ($channels AS $channel)
		{
			if (isset($channel['node']['parentguid']))
			{
				$requiredChannels[] = $channel['node']['parentguid'];
			}
		}

		$existingChannels = $this->db->getRows('vBForum:channel', array('guid' => $requiredChannels));
		foreach ($existingChannels AS $channel)
		{
			$this->referencedParents[$channel['guid']] = $channel['nodeid'];
		}

		if (!$this->options & vB_Xml_Import::OPTION_IGNOREMISSINGPARENTS)
		{
			$missingChannels = array_diff($requiredChannels, array_keys($this->referencedParents));
			if (!empty($missingChannels))
			{
				throw new Exception('Reference to undefined parent channel(s): ' . implode(' ', $missingChannels));
			}
		}
	}

	public function import($filepath)
	{
		$this->parsedXML = vB_Xml_Import::parseFile($filepath);
		$this->checkRoutes();
		$this->checkParents();
		$channels = is_array($this->parsedXML['channel'][0]) ? $this->parsedXML['channel'] : array($this->parsedXML['channel']);

		//On an upgrade from Pre-vB5 the datastore won't exist yet. So we can't get the bitfields from there.
		$parsedRaw = vB_Xml_Import::parseFile(DIR . '/includes/xml/bitfield_vbulletin.xml');
		$forumpermissions = $forumpermissions2 = $moderatorpermissions = $createpermissions = $adminpermissions = $albumpermissions = $socialgrouppermissions = array();

		foreach ($parsedRaw['bitfielddefs']['group'] AS $group)
		{
			if ($group['name'] == 'ugp')
			{
				foreach($group['group'] AS $bfgroup)
				{
					switch ($bfgroup['name'])
					{
						case 'forumpermissions':
							foreach ($bfgroup['bitfield'] AS $bitfield)
							{
								$forumpermissions[$bitfield['name']] = intval($bitfield['value']);
							}
							break;
						case 'forumpermissions2':
							foreach ($bfgroup['bitfield'] AS $bitfield)
							{
								$forumpermissions2[$bitfield['name']] = intval($bitfield['value']);
							}
							break;
						case 'adminpermissions':
							foreach ($bfgroup['bitfield'] AS $bitfield)
							{
								$adminpermissions[$bitfield['name']] = intval($bitfield['value']);
							}
							break;
						case 'albumpermissions':
							foreach ($bfgroup['bitfield'] AS $bitfield)
							{
								$albumpermissions[$bitfield['name']] = intval($bitfield['value']);
							}
							break;
						case 'createpermissions':
							foreach ($bfgroup['bitfield'] AS $bitfield)
							{
								$createpermissions[$bitfield['name']] = intval($bitfield['value']);
							}
							break;
						case 'socialgrouppermissions':
							foreach ($bfgroup['bitfield'] AS $bitfield)
							{
								$socialgrouppermissions[$bitfield['name']] = intval($bitfield['value']);
							}
					}
				}
			}
			else if ($group['name'] == 'misc')
			{
				foreach($group['group'] AS $bfgroup)
				{
					if ($bfgroup['name'] == 'moderatorpermissions')
					{
						foreach ($bfgroup['bitfield'] AS $bitfield)
						{
							$moderatorpermissions[$bitfield['name']] = intval($bitfield['value']);
						}
					}
				}
			}
		}

		$this->bf_administratorpermissions = $adminpermissions;
		$this->bf_moderatorpermissions = $moderatorpermissions;
		$this->bf_forumpermissions = $forumpermissions;
		$this->bf_forumpermissions2 = $forumpermissions2;
		$this->bf_albumpermissions = $albumpermissions;
		$this->bf_socialgrouppermissions = $socialgrouppermissions;
		$this->contenttypes = $createpermissions;
		$usergroups = vB::getDbAssertor()->assertQuery('usergroup', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT));

		foreach ($usergroups AS $usergroup)
		{
			if ($usergroup['systemgroupid'] == vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID)
			{
				$blogMemberGroupId = $usergroup['usergroupid'];
			}
			else if ($usergroup['systemgroupid'] == vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID)
			{
				$sgMemberGroupId = $usergroup['usergroupid'];
			}
			else if ($usergroup['systemgroupid'] == vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID)
			{
				$channelOwnerGroupId = $usergroup['usergroupid'];
			}
		}
		$registeredGroupId = 2;
		$unRegisteredGroupId = 1;

		$rootPerms = $this->buildRootPerms();
		$sgperms = array();
		foreach ($rootPerms AS $ug => $value)
		{
			foreach ($value AS $perms =>$bitvalue)
			{
				if ($perms === 'socialgrouppermissions')
				{
					$sgperms[$ug]['socialgrouppermissions'] = $bitvalue;
					unset($rootPerms[$ug]['socialgrouppermissions']);
					break;
				}
			}
		}

		$channelLib = vB_Library::instance('content_channel');
		$channelPerm = vB_ChannelPermission::instance();
		foreach ($channels AS $channel)
		{
			if (isset($channel['installonly']))
			{
				if (defined('VB_AREA') AND (VB_AREA != 'Install') AND $channel['installonly'])
				{
					// this channel is only added during install, so skip it
					continue;
				}
				unset($channel['installonly']);
			}
			
			$data = array_merge($channel, $channel['node']);
			unset($data['node']);
			if (isset($channel['node']['routeguid']) AND isset($this->referencedRoutes[$channel['node']['routeguid']]))
			{
				$data['routeid'] = $this->referencedRoutes[$channel['node']['routeguid']];
			}

			if
			(
				isset($channel['node']['parentguid']) AND !empty($channel['node']['parentguid']) AND
					($parentId = $this->referencedParents[$channel['node']['parentguid']] OR
					 $parentId = vB_Xml_Import::getImportedId(vB_Xml_Import::TYPE_CHANNEL, $channel['node']['parentguid']))
			)
			{
				$data['parentid'] = $parentId;
			}

			$channelId = 0;
			$condition = array('guid' => $channel['guid']);

			// TODO: after talking with Ed, we decided it might be best not to allow node removal at all. Need to confirm with Kevin
			if ($oldChannel = $this->db->getRow('vBForum:channel', $condition))
			{
				$channelId = $oldChannel['nodeid'];
			}

			if (empty($channelId))
			{
				// We cannot use the API method for adding the root channel, since it requires a parentid.
				if (!isset($data['parentid']))
				{
					$channelContentTypeId = vB_Api::instanceInternal('contenttype')->fetchContentTypeIdFromClass('Channel');
					$userId = vB::getCurrentSession()->get('userid');

					// get all columns but the key
					$channelTable = $this->db->fetchTableStructure('vbforum:channel');
					$channelTableColumns = array_diff($channelTable['structure'], array($channelTable['key']));

					$nodeTable = $this->db->fetchTableStructure('vbforum:node');
					$nodeTableColumns = array_diff($nodeTable['structure'], array($nodeTable['key'], $channelTable['key']));

					// insert root node
					$nodeValues = array(
						'userid' => $userId,
						'contenttypeid' => $channelContentTypeId
					);
					foreach($nodeTableColumns AS $col)
					{
						if (isset($channel['node'][$col]))
						{
							$nodeValues[$col] = $channel['node'][$col];
						}
					}
					if (isset($data['routeid']))
					{
						$nodeValues['routeid'] = $data['routeid'];
					}

					$channelId = $this->db->insertIgnore('vBForum:node', $nodeValues);

					if (is_array($channelId))
					{
						$channelId = array_pop($channelId);
					}

					// continue only if the node could be inserted
					if ($channelId)
					{
						$values = array();
						foreach($channelTableColumns AS $col)
						{
							if (isset($channel[$col]))
							{
								$values[$col] = $channel[$col];
							}
						}

						$values['nodeid'] = $channelId;
						$this->db->insert('vBForum:channel', $values);
					}

					// insert in closure
					$this->db->insert('vBForum:closure', array('parent' => $channelId, 'child' => $channelId, 'depth' => 0));

					foreach ($rootPerms AS $groupPerm)
					{
						$channelPerm->setPermissions($channelId, $groupPerm['groupid'], $groupPerm, true);
					}
				}
				else
				{
					$channelId = $channelLib->add($data, array('nodeonly' => true, 'skipFloodCheck' => true, 'skipNotification' => true, 'skipDupCheck' => true));
				}
			}
			else
			{
				/* Apparently routeid gets changed every update .... */
				if (isset($channel['node']['routeguid']) AND isset($this->referencedRoutes[$channel['node']['routeguid']]))
				{
					$data['routeid'] = $this->referencedRoutes[$channel['node']['routeguid']];
				}
			}

			if (is_array($channelId))
			{
				$channelId = array_pop($channelId);
			}

			/* VBV-2536, $oldChannel check. This is to stop the 
			import blindly resetting permissions on every update */
			if (!$oldChannel AND !empty($channelId))
			{
				/* We don't know what usergroups exist currently. There may be the groups 1-8 we create on a new install,
				 or there may not. If a group exists it may have a different meaning from what we create on a new install.
				So here's what we have decided:
				1) At root if there is at least one forum permission record:
					a) you get any permissions you have in any existing root forum.
					b) If you have "albumpermissions -> canalbum" you can create albums and photos.
					c) If you have adminpermissions->canadminforums you can create channels.
					d) If you can forumpermissions->canpostnew you can create all the other types
				2) If the forumpermissions doesn't exist or is empty, treat this as a new install. Take the permissions
					from the xml file, taking the id as systemgroupid.
				3) At root everybody can create private messages.
				4) 	For the blog and social groups channels, we have newly created usergroups with systemgroupid. Those two channels
				are specified in the xml file.
				5) For all other usergroups in those two channels-
					a) if you have moderator permission canmoderateposts, no record. Just inherit.
					b) otherwise, get your root permissions plus channel
				6) For the report channel- if you have moderator permission canmoderateposts anywhere on the site
					you inherit permissions. Otherwise you have none.
				*/

				vB_Xml_Import::setImportedId(vB_Xml_Import::TYPE_CHANNEL, $channel['guid'], $channelId);

				switch ($data['guid'])
				{
					case vB_Channel::DEFAULT_BLOG_PARENT :
						foreach ($rootPerms AS $groupPerm)
						{
							$thisPerm = $groupPerm;
							if ($groupPerm['createpermissions'] > 0)
							{
								$thisPerm['createpermissions'] = $groupPerm['createpermissions'] | $this->contenttypes['vbforum_channel'];
							}

							//These four groups should not be able to start threads in the blog and social group channels.
							if (($groupPerm['groupid'] == $blogMemberGroupId) OR ($groupPerm['groupid'] == $sgMemberGroupId)
								OR ($groupPerm['groupid'] == $registeredGroupId) OR ($groupPerm['groupid'] == $unRegisteredGroupId))
							{
								$thisPerm['forumpermissions'] = $thisPerm['forumpermissions'] & ~ $forumpermissions['canpostnew'];
								// but they should be able to reply to others' threads
								$thisPerm['forumpermissions'] |= $forumpermissions['canreplyothers'];
								$thisPerm['createpermissions'] |= $this->contenttypes['vbforum_text'];
							}

							//Let the channel owner configure his/her channel
							if ($groupPerm['groupid'] == $channelOwnerGroupId)
							{
								$thisPerm['forumpermissions2'] |= $forumpermissions2['canconfigchannel'];
							}

							$channelPerm->setPermissions($channelId, $groupPerm['groupid'], $thisPerm, true);
						}
					break;
					case vB_Channel::DEFAULT_SOCIALGROUP_PARENT :
						foreach ($rootPerms AS $groupPerm)
						{
							$thisPerm = $groupPerm;
							if ($groupPerm['createpermissions'] > 0)
							{
								$thisPerm['createpermissions'] = $groupPerm['createpermissions'] | $this->contenttypes['vbforum_channel'];
								if (($groupPerm['systemgroupid'] == vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID)
									OR ($groupPerm['systemgroupid'] == vB_Api_UserGroup::CHANNEL_MODERATOR_SYSGROUPID)
									OR ($groupPerm['systemgroupid'] == vB_Api_UserGroup::CHANNEL_MEMBER_SYSGROUPID)
									OR ($groupPerm['systemgroupid'] == vB_Api_UserGroup::ADMINISTRATOR)
									OR ($groupPerm['systemgroupid'] == vB_Api_UserGroup::SUPER_MODERATOR)
								)
								{
									$thisPerm['createpermissions'] |= $this->contenttypes['vbforum_text'];
									$thisPerm['createpermissions'] |= $this->contenttypes['vbforum_report'];
									$thisPerm['createpermissions'] |= $this->contenttypes['vbforum_calendar'];
									$thisPerm['createpermissions'] |= $this->contenttypes['vbforum_gallery'];
									$thisPerm['createpermissions'] |= $this->contenttypes['vbforum_poll'];
									$thisPerm['createpermissions'] |= $this->contenttypes['vbforum_attach'];
									$thisPerm['createpermissions'] |= $this->contenttypes['vbforum_photo'];
									$thisPerm['createpermissions'] |= $this->contenttypes['vbforum_privatemessage'];
									$thisPerm['createpermissions'] |= $this->contenttypes['vbforum_video'];
									$thisPerm['createpermissions'] |= $this->contenttypes['vbforum_link'];
								}
								else
								{
									$thisPerm['createpermissions'] &= ~$this->contenttypes['vbforum_text'];
									$thisPerm['createpermissions'] &= ~$this->contenttypes['vbforum_report'];
									$thisPerm['createpermissions'] &= ~$this->contenttypes['vbforum_calendar'];
									$thisPerm['createpermissions'] &= ~$this->contenttypes['vbforum_gallery'];
									$thisPerm['createpermissions'] &= ~$this->contenttypes['vbforum_poll'];
									$thisPerm['createpermissions'] &= ~$this->contenttypes['vbforum_attach'];
									$thisPerm['createpermissions'] &= ~$this->contenttypes['vbforum_photo'];
									$thisPerm['createpermissions'] &= ~$this->contenttypes['vbforum_privatemessage'];
									$thisPerm['createpermissions'] &= ~$this->contenttypes['vbforum_video'];
									$thisPerm['createpermissions'] &= ~$this->contenttypes['vbforum_link'];
								}

								$rootPerms[$groupPerm['groupid']] = $thisPerm;
							}

							//These four groups should not be able to start threads in the blog and social group channels.
							if (($groupPerm['groupid'] == $blogMemberGroupId) OR ($groupPerm['groupid'] == $sgMemberGroupId)
								OR ($groupPerm['groupid'] == $registeredGroupId) OR ($groupPerm['groupid'] == $unRegisteredGroupId))
							{
								$thisPerm['forumpermissions'] = $thisPerm['forumpermissions'] & ~ $forumpermissions['canpostnew'];
							}

							//Let the channel owner configure his/her channel
							if ($groupPerm['groupid'] == $channelOwnerGroupId)
							{
								$thisPerm['forumpermissions2'] |= $forumpermissions['canconfigchannel'];
							}

							$channelPerm->setPermissions($channelId, $groupPerm['groupid'], $thisPerm, true);
						}

						foreach ($sgperms AS $index => $sgperm)
						{
								$thisPerm = $rootPerms[$index];

								if ($sgperm['socialgrouppermissions'] & $this->bf_socialgrouppermissions['cancreategroups'])
								{
									$thisPerm['createpermissions'] |= $this->contenttypes['vbforum_channel'];
								}
								else
								{
									$thisPerm['createpermissions'] &= ~$this->contenttypes['vbforum_channel'];
								}
								foreach ($this->bf_forumpermissions AS $name => $bitfield)
								{
									switch($name)
									{
										case 'canreplyown':
										case 'canreplyothers':
											if ($sgperm['socialgrouppermissions'] & $this->bf_socialgrouppermissions['canpostmessage'])
											{
												$thisPerm['forumpermissions'] |= $bitfield;
												$thisPerm['createpermissions'] |= $this->contenttypes['vbforum_text'];
											}
											else
											{
												$rootPerms[$index]['forumpermissions'] &= ~$bitfield;
												$thisPerm['forumpermissions'] &= ~$bitfield;
											}
											break;
										case 'canview':
										case 'canviewthreads':
										case 'canviewothers':
											if ($sgperm['socialgrouppermissions'] & $this->bf_socialgrouppermissions['canviewgroups'])
											{
												$thisPerm['forumpermissions'] |= $bitfield;
											}
											else
											{
												$rootPerms[$index]['forumpermissions'] &= ~$bitfield;
												$thisPerm['forumpermissions'] &= ~$bitfield;
											}
											break;
										case 'caneditpost':
										case 'candeletepost':
											if ($sgperm['socialgrouppermissions'] & $this->bf_socialgrouppermissions['canmanagemessages'])
											{
												$thisPerm['forumpermissions'] |= $bitfield;
											}
											else
											{
												$rootPerms[$index]['forumpermissions'] &= ~$bitfield;
												$thisPerm['forumpermissions'] &= ~$bitfield;
											}
											break;
										case 'canpostnew':
											if ($sgperm['socialgrouppermissions'] & $this->bf_socialgrouppermissions['cancreatediscussion'])
											{
												$thisPerm['forumpermissions'] |= $bitfield;
												$thisPerm['createpermissions'] |= $this->contenttypes['vbforum_text'];
											}
											else
											{
												$rootPerms[$index]['forumpermissions'] &= ~$bitfield;
												$thisPerm['forumpermissions'] &= ~$bitfield;
											}
											break;
										case 'canpostattachment':
											if ($sgperm['socialgrouppermissions'] & $this->bf_socialgrouppermissions['canupload'])
											{
												$thisPerm['forumpermissions'] |= $bitfield;
												$thisPerm['createpermissions'] |= $this->contenttypes['vbforum_photo'];
											}
											else
											{
												$rootPerms[$index]['forumpermissions'] &= ~$bitfield;
												$thisPerm['forumpermissions'] &= ~$bitfield;
											}
											break;
										case 'canjoin':
											if ($sgperm['socialgrouppermissions'] & $this->bf_socialgrouppermissions['canjoingroups'])
											{
												$thisPerm['forumpermissions'] |= $bitfield;
											}
											else
											{
												$rootPerms[$index]['forumpermissions'] &= ~$bitfield;
												$thisPerm['forumpermissions'] &= ~$bitfield;
											}
											break;
										case 'candeletethread':
											if ($sgperm['socialgrouppermissions'] & $this->bf_socialgrouppermissions['canmanagediscussions'])
											{
												$thisPerm['forumpermissions'] |= $bitfield;
											}
											else
											{
												$rootPerms[$index]['forumpermissions'] &= ~$bitfield;
												$thisPerm['forumpermissions'] &= ~$bitfield;
											}
											break;
										case 'canuploadchannelicon':
											if ($sgperm['socialgrouppermissions'] & $this->bf_socialgrouppermissions['canuploadgroupicon'])
											{
												$thisPerm['forumpermissions'] |= $bitfield;
											}
											else
											{
												$rootPerms[$index]['forumpermissions'] &= ~$bitfield;
												$thisPerm['forumpermissions'] &= ~$bitfield;
											}
											break;
										case 'cananimatedchannelicon':
											if ($sgperm['socialgrouppermissions'] & $this->bf_socialgrouppermissions['cananimategroupicon'])
											{
												$thisPerm['forumpermissions'] |= $bitfield;
											}
											else
											{
												$rootPerms[$index]['forumpermissions'] &= ~$bitfield;
												$thisPerm['forumpermissions'] &= ~$bitfield;
											}
											break;
										default :
									}
								}
								foreach ($this->bf_forumpermissions2 AS $name => $bitfield)
								{
									switch($name)
									{
										case 'canconfigchannel':
											if (($index == vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID)
												OR ($index == vB_Api_UserGroup::ADMINISTRATOR)
												OR ($index == vB_Api_UserGroup::SUPER_MODERATOR)
											)
											{
												if ($sgperm['socialgrouppermissions'] & $this->bf_socialgrouppermissions['caneditowngroups'])
												{
													$thisPerm['forumpermissions2'] |= $bitfield;
												}
												else
												{
													$rootPerms[$index]['forumpermissions2'] &= ~$bitfield;
													$thisPerm['forumpermissions2'] &= ~$bitfield;
												}
											}
											break;
										default :
									}
								}

								$channelPerm->setPermissions($channelId, $index, $thisPerm, true);
						}
					break;
					case vB_Channel::REPORT_CHANNEL :
						foreach ($rootPerms AS $groupPerm)
						{
							if ($groupPerm['adminpermissions'] > 0 )
							{
								$channelPerm->setPermissions($channelId, $groupPerm['groupid'], $groupPerm, true);
							}
							else
							{
								$channelPerm->setPermissions($channelId, $groupPerm['groupid'], $this->fetchEmptyPermissions(), true);
							}
						}
					break;
					//If it's anything else we don't set permissions.
				}
			}
		}

		$channelPerm->buildDefaultChannelPermsDatastore();
	}

	/**
	 * Returns a permission record with nothing in it
	 *
	 * @return	array	empty permission array
	 */
	protected function fetchEmptyPermissions()
	{
		return array(
			'maxothertags'         => 0,
			'maxstartertags'       => 0,
			'maxtags'              => 0,
			'maxattachments'       => 0,
			'edit_time'            => 1,
			'adminpermissions'     => 0,
			'createpermissions'    => 0,
			'forumpermissions'     => 0,
			'forumpermissions2'    => 0,
			'moderatorpermissions' => 0,
		);
	}

	public function updateChannelRoutes()
	{
		$importedChannels = vB_Xml_Import::getImportedId(vB_Xml_Import::TYPE_CHANNEL);
		$importedRoutes = vB_Xml_Import::getImportedId(vB_Xml_Import::TYPE_ROUTE);
		$channels = is_array($this->parsedXML['channel'][0]) ? $this->parsedXML['channel'] : array($this->parsedXML['channel']);

		foreach ($channels AS $channel)
		{
			$routeGUID = $channel['node']['routeguid'];
			if (isset($importedChannels[$channel['guid']]) AND isset($importedRoutes[$routeGUID]))
			{
				$this->db->update(
					'vBForum:node',
					array('routeid' => $importedRoutes[$routeGUID]),
					array('nodeid'	=> $importedChannels[$channel['guid']])
				);
			}
		}
	}

	protected function buildRootPerms()
	{
		$perms = array();
		$options = vB::getDatastore()->getValue('options');
		$groupList = vB_Api::instanceInternal('usergroup')->fetchUsergroupList(true);

		foreach($groupList as $group)
		{
			$groupid = $group['usergroupid'];
			$perms[$groupid] = array('groupid' => $groupid);
			$perms[$groupid]['systemgroupid'] = $group['systemgroupid'];
			$plist = array(
				'moderatorpermissions',
				'createpermissions',
				'require_moderate',
				'edit_time',
				'maxtags',
				'maxstartertags',
				'maxothertags',
				'maxattachments',
			);
			
			foreach ($plist AS $fieldName)
			{
				$perms[$groupid][$fieldName] = 0;
			}

			$perms[$groupid]['forumpermissions'] = $group['forumpermissions'];
			$perms[$groupid]['adminpermissions'] = $group['adminpermissions'];
			$perms[$groupid]['albumpermissions'] = $group['albumpermissions'];
			$perms[$groupid]['socialgrouppermissions'] = $group['socialgrouppermissions'];
			$perms[$groupid]['forumpermissions2'] = isset($group['forumpermissions2']) ? $group['forumpermissions2'] : 0;

			if (($group['adminpermissions'] & $this->bf_administratorpermissions['ismoderator'])
			OR ($group['systemgroupid'] == vB_Api_UserGroup::CHANNEL_MODERATOR_SYSGROUPID))
			{
				$perms[$groupid]['moderatorpermissions'] =  $this->bf_moderatorpermissions['caneditposts'] |
				$this->bf_moderatorpermissions['candeleteposts'] | $this->bf_moderatorpermissions['canopenclose'] |
				$this->bf_moderatorpermissions['canmanagethreads'] | $this->bf_moderatorpermissions['caneditthreads'] |
				$this->bf_moderatorpermissions['canmoderateposts'] | $this->bf_moderatorpermissions['canmoderateattachments'] |
				$this->bf_moderatorpermissions['canviewprofile'] | $this->bf_moderatorpermissions['canremoveposts'] |
				$this->bf_moderatorpermissions['caneditpoll'] | $this->bf_moderatorpermissions['cansetfeatured'] |
				$this->bf_moderatorpermissions['canmoderatetags'];
			}
			elseif ($group['systemgroupid'] == vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID)
			{
				$perms[$groupid]['moderatorpermissions'] =  $this->bf_moderatorpermissions['caneditposts'] |
					$this->bf_moderatorpermissions['candeleteposts'] | $this->bf_moderatorpermissions['canopenclose'] |
					$this->bf_moderatorpermissions['canmanagethreads'] | $this->bf_moderatorpermissions['caneditthreads'] |
					$this->bf_moderatorpermissions['canmoderateposts'] | $this->bf_moderatorpermissions['canmoderateattachments'] |
					$this->bf_moderatorpermissions['canviewprofile'] | $this->bf_moderatorpermissions['caneditpoll'] |
					$this->bf_moderatorpermissions['cansetfeatured'] | $this->bf_moderatorpermissions['canmoderatetags'] |
					$this->bf_moderatorpermissions['canaddowners'];
			}
			
			if ($group['adminpermissions'] & $this->bf_administratorpermissions['cancontrolpanel'])
			{
				$perms[$groupid]['moderatorpermissions'] |= $this->bf_moderatorpermissions['canbanusers'];
				$perms[$groupid]['moderatorpermissions'] |= $this->bf_moderatorpermissions['canaddowners'];
				$perms[$groupid]['moderatorpermissions'] |= $this->bf_moderatorpermissions['canmassmove'];
			}
		}

		if (VB_AREA == 'Upgrade')
		{
			try
			{
				$rootPermQry = vB::getDbAssertor()->assertQuery('vBInstall:getRootForumPerms', array());

				if ($rootPermQry->valid())
				{
					foreach($rootPermQry AS $rootPerm)
					{
						/* Need to convert these to numeric values
						otherwise the logical OR does very bad things */
						$groupid = intval($rootPerm['usergroupid']);
						$fperms = intval($rootPerm['forumpermissions']);

						/* Note: vB4 doesnt have a forumpermissions2 */
						$perms[$groupid]['forumpermissions'] |= $fperms;
					}
				}
			}
			catch (Exception $e)
			{
				// Not a vB4 upgrade, carry on.
			}
		}

		foreach ($perms AS $groupid => $permissions)
		{
			$perms[$groupid]['maxtags'] = 10;
			$perms[$groupid]['edit_time'] = 0;
			$perms[$groupid]['maxchannels'] = 0;
			$perms[$groupid]['maxothertags'] = 5;
			$perms[$groupid]['maxstartertags'] = 5;
			$perms[$groupid]['channeliconmaxsize'] = 65535;

			$perms[$groupid]['maxattachments'] = isset($options['attachlimit']) ? $options['attachlimit'] : 5;

			foreach ($this->contenttypes AS $name => $bitfield)
			{
				switch($name)
				{
					case 'vbforum_channel':
						if (($perms[$groupid]['adminpermissions'] & $this->bf_administratorpermissions['canadminforums']) OR ($perms[$groupid]['adminpermissions'] & $this->bf_administratorpermissions['cancontrolpanel']))
						{
							$perms[$groupid]['createpermissions'] |= $bitfield;
						}
					break;

					case 'vbforum_gallery':
					case 'vbforum_photo':
						if ($perms[$groupid]['albumpermissions'] & $this->bf_albumpermissions['canalbum'])
						{
							$perms[$groupid]['createpermissions'] |= $bitfield;
						}
					break;

					case 'vbforum_privatemessage':
						if ($groupid != 1)
						{
							$perms[$groupid]['createpermissions'] |= $bitfield;
						}
					break;

					default :
						if ($perms[$groupid]['forumpermissions'] & $this->bf_forumpermissions['canpostnew'])
						{
							$perms[$groupid]['createpermissions'] |= $bitfield;
						}
					break;
				}
			}
		}

		return $perms;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
