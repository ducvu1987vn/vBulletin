<?php
if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
   || #################################################################### ||G
   || # vBulletin 5.0.0
   || # ---------------------------------------------------------------- # ||
   || # Copyright  2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
   || # This file may not be redistributed in whole or significant part. # ||
   || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
   || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
   || #################################################################### ||
   \*======================================================================*/


/**
 * vB_Api_Content
 *
 * @package vBApi
 * @author ebrown
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
abstract class vB_Api_Content extends vB_Api
{
	const ACTION_ADD = 1;
	const ACTION_UPDATE = 2;
	const ACTION_VIEW = 3;
	const ACTION_DELETE = 4;
	const ACTION_APPROVE = 5;
	const ACTION_PUBLISH = 6;

	protected $disableWhiteList = array('getTimeNow');

	//needed primarily for permissions
	protected $usercontext;

	//Let's keep a pointer to the assertor
	/**
	 * @var vB_dB_Assertor
	 */
	protected $assertor;


	//the node API, which we will use a lot.
	protected $nodeApi;

	//the fields in the node table
	protected $nodeFields ;

	//we'll need some checks based on the options setting.
	protected $options;

	//We need a way to skip flood check for types like Photos, where we'll upload several together.
	protected $doFloodCheck = true;
	
	//Whether we handle showapproved,approved fields internally or not
	protected $handleSpecialFields = 0;

	protected $notifications = array();

	protected $library;

	//Standard constructor
	protected function __construct()
	{
		parent::__construct();
		//The table for the type-specific data.
		$this->assertor = vB::getDbAssertor();
		$this->nodeApi = vB_Api::instanceInternal('node');
		$this->nodeFields = $this->nodeApi->getNodeFields();
		$this->options = vB::get_datastore()->get_value('options');
	}

	/**
	 * Returns textCountChange property
	 * @return int
	 */
	public function getTextCountChange()
	{
		return $this->library->getTextCountChange();
	}

	/*** Adds a new node.
	 *
	 *	@param	mixed		Array of field => value pairs which define the record.
	 *  @param	array		Array of options for the content being created. See subclasses for more info.
	 *
	 * 	@return	integer		the new nodeid
	 ***/
	public function add($data, $options = array())
	{
		if (!$this->validate($data, vB_Api_Content::ACTION_ADD))
		{
			throw new vB_Exception_Api('no_create_permissions');
		}
		
		//We shouldn't pass the open or show open fields
		unset($data['open']);
		unset($data['showopen']);

		//We shouldn't pass the approved or showapproved open fields
		if (!$this->handleSpecialFields)
		{
			unset($data['approved']);
			unset($data['showapproved']);
		}
		
		$this->cleanInput($data);
		$this->cleanOptions($options);
		return $this->library->add($data, $options);
	}
	
	
	/**
	 * Clean unallowed options from user request, only cleans 'skipFloodCheck' for now
	 * @param array $options Array of options, may be passed in from client 
	 */
	public function cleanOptions(&$options)
	{
		if (isset($options['skipFloodCheck']))
		{
			unset($options['skipFloodCheck']);
		}
	}


	/** Does basic input cleaning for input data
	 	@param	mixed	array of fieldname => data pairs

	 	@return	mixed	the same data after cleaning.
	 */
	public function cleanInput(&$data, $nodeid = false)
	{
		if (isset($data['userid']))
		{
			unset($data['userid']);
		}

		if (isset($data['authorname']))
		{
			unset($data['authorname']);
		}
		
		// These fields should be cleaned regardless of the user's canusehtml permission.
		$cleaner = vB::getCleaner();
		foreach(array('title', 'htmltitle', 'keywords', 'description', 'prefixid', 'caption') as $fieldname)
		{
			if (isset($data[$fieldname]))
			{
				$data[$fieldname] = $cleaner->clean($data[$fieldname], vB_Cleaner::TYPE_NOHTML);
			}
		}
		
		foreach(array('open', 'showopen', 'approved', 'showapproved') as $fieldname)
		{
			if (isset($data[$fieldname]))
			{
				$data[$fieldname] = $cleaner->clean($data[$fieldname], vB_Cleaner::TYPE_INT);
			}
		}
		
		if (isset($data['urlident']))
		{
			// Let's make sure it's a valid identifier. No spaces, UTF-8 encoded, etc.
			$data['urlident'] = vB_String::getUrlIdent($data['urlident']);
		}

		if (isset($data['parentid']))
		{
			if (vB::getUserContext()->getChannelPermission('forumpermissions2', 'canusehtml', $data['parentid']))
			{
				return;
			}
		}
		else if ($nodeid)
		{
			if (vB::getUserContext()->getChannelPermission('forumpermissions2', 'canusehtml', $nodeid))
			{
				return;
			}
		}
		
		// These fields are cleaned for people who cannot use html
		foreach(array('pagetext') as $fieldname)
		{
			if (isset($data[$fieldname]))
			{
				$data[$fieldname] = $cleaner->clean($data[$fieldname], vB_Cleaner::TYPE_NOHTML);
			}
		}
	}



	/*** validates that the current can create a node with these values
	 *
	 *	@param	mixed		Array of field => value pairs which define the record.
	 *	@param	action		Parameters to be checked for permission
	 *
	 * 	@return	bool
	 ***/
	public function validate(&$data, $action = self::ACTION_ADD, $nodeid = false, $nodes = false)
	{
		// Each descendant should override this function and add their own
		// check of individual required fields.
		if ((defined('VB_AREA') AND VB_AREA == 'Upgrade'))
		{
			return true;
		}

		if (vB::getUserContext()->isSuperAdmin())
		{
			//The only reason we would return false is if comments are globally disabled  AND the content type is neither attachment nor photo, this
			// would be a comment, and the reply would be a comment.
			if (($action != self::ACTION_ADD) OR vB::getDatastore()->getOption('postcommentthreads')
			OR ($data['contenttypeid'] == vB_Types::instance()->getContentTypeID('vBForum_Photo'))
			OR ($data['contenttypeid'] == vB_Types::instance()->getContentTypeID('vBForum_Attach')) )
			{
				return true;
			}

			if (empty($data['parentid']) OR !intval($data['parentid']))
			{
				throw new vB_Exception_Api('invalid_data');
			}
			$parent = vB_Library::instance('node')->getNodeBare($data['parentid']);

			//If the parent is not a starter. this would be a comment.
			if (($parent['starter'] > 0) AND ($parent['nodeid'] != $parent['starter']))
			{
				return false;
			}
			return true;
		}

		if ($nodeid == false AND !empty($data['nodeid']))
		{
			$nodeid = $data['nodeid'];
		}

		//we need a nodeid (or parentid if we are adding) or we cannot answer the question.
		if ($action == vB_Api_Content::ACTION_ADD)
		{
			if (empty($data['parentid']) OR !intval($data['parentid']))
			{
				throw new vB_Exception_Api('invalid_data');
			}
			$parentid = $data['parentid'];
		}
		else
		{
			if (!$nodeid)
			{
				if (empty($data['nodeid']))
				{
					throw new Exception('invalid_data');
				}
			}

			if (!is_array($nodeid))
			{
				$nodeid = array($nodeid);
			}

			if (!$nodes)
			{
				$nodes = vB_Api::instanceInternal('node')->getNodes($nodeid);
			}
		}

		$userContext = vB::getUserContext();
		$userid = vB::getCurrentSession()->get('userid');
		switch ($action)
		{
			case vB_Api_Content::ACTION_ADD:				
				//Check the node-specific permissions first.
				$parent = vB_Library::instance('node')->getNode($parentid);
				if (in_array($this->contenttype, array('vBForum_Text', 'vBForum_Poll', 'vBForum_PrivateMessage'))
					AND ($parent['parentid'] != $parent['starter'])
				)
				{
					// Only validate HV for specific content types.
					// To skip HV, please call library methods instead.
					vB_Api::instanceInternal('hv')->verifyToken($data['hvinput'], 'post');
				}

				//We need to know what the channel is, and see if it's a blog channel.
				if ($parent['contenttypeid'] == vB_Types::instance()->getContentTypeId('vBForum_Channel'))
				{
					$channelid = $parentid;
				}
				else if ($parent['starter'] == $parentid)
				{
					//Check for the the parent's parent
					$channelid = $parent['parentid'];
				}
				else
				{
					// The channel is of course the starter's parent.
					$starter = vB_Api::instanceInternal('node')->getNode($parent['starter']);
					$channelid = $starter['parentid'];
				}

				//we need the channel information.
				if ($channelid == $parentid)
				{
					$channel = &$parent;
				}
				else
				{
					$channel = vB_Api::instanceInternal('node')->getNode($channelid);
				}
				
				if(!empty($data['filedataid']) AND $data['filedataid'] > 0){
					$channel['isPhotoOrVideo'] = true; 
				}
				
				if ($this->contenttype != 'vBForum_Channel')
				{
					//If this is a reply we need to check two global permissions
					if ($parent['contenttypeid'] != vB_Types::instance()->getContentTypeId('vBForum_Channel'))
					{
						$canalways = $userContext->getChannelPermission('forumpermissions2', 'canalwayspost', $parent['nodeid']);
						
						//if comments are globally disabled AND the parent isn't a starter we can stop now.
						if ($parent['nodeid'] != $parent['starter'])
						{
							$commentsEnabled = vB::getDatastore()->getOption('postcommentthreads');

							if (empty($commentsEnabled) AND ($data['contenttypeid'] <> vB_Types::instance()->getContentTypeID('vBForum_Attach'))
								AND ($data['contenttypeid'] <> vB_Types::instance()->getContentTypeID('vBForum_Photo')))
							{
								return false;
							}
						}

						if ($parent['starter'] == $parent['nodeid'])
						{
							$authorid = $parent['userid'];
						}
						else if ($parent['starter'] > 0)
						{
							$starter = vB_Library::instance('node')->getNodeBare($parent['starter']);
							$authorid = $starter['userid'];
						}
						
						if (($userid != $authorid) AND !$userContext->getChannelPermission('forumpermissions', 'canreplyothers', $parent['nodeid'])	)
						{
							return false;
						}
						else if ((($userid == $authorid) AND !$userContext->getChannelPermission('forumpermissions', 'canreplyown', $parent['nodeid']))
						)
						{
							return false;
						}
					}
					else
					{
						//The user needs canpostnew
						$canalways = $userContext->getChannelPermission('forumpermissions2', 'canalwayspostnew', $parent['nodeid']);
						if (!$userContext->getChannelPermission('forumpermissions', 'canpostnew', $parentid))
						{
							return false;
						}
					}

					$sgapi = vB_Api::instanceInternal('socialgroup');
					$blogapi = vB_Api::instanceInternal('blog');

					$isSGChannel = $sgapi->isSGNode($channel['nodeid']);
					$isBlogChannel = $blogapi->isBlogNode($channel['nodeid']);

					if ($isSGChannel OR $isBlogChannel)
					{
						if ($isSGChannel)
						{
							$channel['type'] = 'sg';
						}
						else
						{
							$channel['type'] = 'blog';
						}
						if ($channel['commentperms'] == 0)
						{
							//Only blog members can contribute. We check for the channel
							if ((!$userid OR (($blogapi->isChannelMember($channelid) != 1) AND !$canalways)))
							{
								//Not O.K.
								return false;
							}
							//If this is the starter
							if ($isBlogChannel AND $parentid == $channelid)
							{
								//This is a blog post. Only owners and moderators can start them.
								$adminPerms = $channelInfo = $blogapi->getChannelAdminPerms($channelid);
								if (!empty($adminPerms['canmoderate']))
								{
									$channel['allow_post'] = 1;
								}
								if (empty($adminPerms) OR (!$adminPerms['canmoderate']))
								{
									return false;
								}
							}

							if ($isSGChannel)
							{
								// SG only Group Members can post
								if (($sgapi->isChannelMember($channelid) != 1) AND !$canalways)
								{
									return false;
								}

								// If this is a new topic/discussion check for the permission
								if ($parent['contenttypeid'] == vB_Types::instance()->getContentTypeId('vBForum_Channel')
									AND !vB::getUserContext()->hasPermission('socialgrouppermissions', 'cancreatediscussion')
									AND vB::getUserContext()->fetchUserId() != $parent['userid'])
								{
									return false;
								}

							}

							//handle channel-specific settings from user.
							$this->checkChannelSettings($channel, $parent, $data, $userid);
						}
						else
						{
							//We need to check to see if it's a blog channel
							if ($isBlogChannel)
							{
								//If $channel['commentperms'] wasn't 0, then it can be 1 or 2
								//1: Only registered users. 2: everyone
								//If the current user is not registered they can't post
								if (($channel['commentperms'] == 1) AND !$userid)
								{
									return false;
								}
								if ($parentid == $channelid)
								{
									//This is a blog post. Only owners and moderators can start them.
									$adminPerms = $channelInfo = $blogapi->getChannelAdminPerms($channelid);
									if (!empty($adminPerms['canmoderate']))
									{
										$channel['allow_post'] = 1;
									}
									if (empty($adminPerms) OR (!$adminPerms['canmoderate']))
									{
										return false;
									}
								}
							}
							elseif ($isSGChannel)
							{
								//If the current user is not registered they can't post
								if (!$userid)
								{
									return false;
								}

								// If this is a new topic/discussion check for the permission
								if ($parent['contenttypeid'] == vB_Types::instance()->getContentTypeId('vBForum_Channel')
									AND !vB::getUserContext()->hasPermission('socialgrouppermissions', 'cancreatediscussion')
									AND vB::getUserContext()->fetchUserId() != $parent['userid'])
								{
									return false;
								}
							}
							$this->checkChannelSettings($channel, $parent, $data, $userid);
						}
					}

					$vmChannel = vB_Api::instanceInternal('node')->fetchVMChannel();
					if ($parentid == $vmChannel)
					{
						if (!isset($data['setfor']) OR
							(isset($data['setfor']) AND (!is_numeric($data['setfor']) OR $data['setfor'] <= 0)))
						{
							throw new vB_Exception_Api('invalid_data');
						}

						$vm_user = vB_User::fetchUserinfo($data['setfor']);
						if ($vm_user == false)
						{
							throw new vB_Exception_Api('invalid_data');
						}

						if (
							!$vm_user['vm_enable']
								OR
							!vB::getUserContext($vm_user['userid'])->hasPermission('genericpermissions', 'canviewmembers')
						)
						{
							return false;
						}

						if ($data['setfor'] == $userid)
						{
							// Do we have add permission to write on our own wall?
							if (!vB::getUserContext()->hasPermission('visitormessagepermissions', 'canmessageownprofile'))
							{
								return false;
							}
						}
						else
						{
							// Do we have permission to write on others' walls?
							if (!vB::getUserContext()->hasPermission('visitormessagepermissions', 'canmessageothersprofile'))
							{
								return false;
							}
						}
					}
				}

				//check the showPublished.
				if (($parent['showpublished'] == 0) )
				{
					if (!$userContext->getChannelPermission('moderatorpermissions', 'canmoderateposts', $parentid))
					{
						return false;
					}
				}

				if ($parent['open'] == 0 AND !$userContext->getChannelPermission('moderatorpermissions', 'canmoderateposts', $parentid))
				{
					return false;
				}
				
				//if the parent has canPost
				if ($userContext->getChannelPermission('createpermissions', $this->contenttype, $parentid))
				{
					return true;
				}
				
				return false;
				break;

			case vB_Api_Content::ACTION_UPDATE:
				//There are a couple of ways this user could be allowed to edit this record.
				// As a moderator
				$channelType = vB_Types::instance()->getContentTypeID('vBForum_Channel');

				foreach ($nodes AS $node)
				{
					// Can configure channel goes first, otherwise it is ignored due to the moderator perms
					if ($node['contenttypeid'] == $channelType)
					{
						if (!$userContext->getChannelPermission('forumpermissions2', 'canconfigchannel', $node['nodeid'], false, $node['parentid']))
						{
							return false;
						}


					}

					if ($userContext->getChannelPermission('moderatorpermissions', 'caneditposts', $node['nodeid'], false, $node['parentid']))
					{
						continue;
					}



					$vmChannel = vB_Api::instanceInternal('node')->fetchVMChannel();
					if ($node['parentid'] == $vmChannel)
					{
						$vm_user = vB_User::fetchUserinfo($node['setfor']);
						if (
							!$vm_user['vm_enable']
								OR
							$node['userid'] != $userid
								OR
							!vB::getUserContext()->hasPermission('visitormessagepermissions', 'caneditownmessages')
								OR
							(!vB::getUserContext($vm_user['userid'])->hasPermission('genericpermissions', 'canviewmembers') AND $userid == $vm_user['userid'])
						)
						{
							return false;
						}
					}

					// It's a VM for the user from himself
					if (!empty($node['setfor']) AND $node['setfor'] == $userid AND $node['setfor'] == $node['userid'] AND $userContext->hasPermission('visitormessagepermissions', 'caneditownmessages'))
					{
						continue;
					}

					//Or because this is their content and it's within the date range. We need to pull the record.
					if ($node['userid'] == vB::getCurrentSession()->get('userid') AND ($userContext->getChannelPermission('forumpermissions', 'caneditpost', $node['nodeid'], false, $node['parentid'])))
					{
						$limits = $userContext->getChannelLimits($node['nodeid']);

						if (!$limits OR empty($limits['edit_time']))
						{
							//There is no edit timeout set;
							continue;
						}

						if ($node['publishdate'] + ($limits['edit_time'] * 3600) >= vB::getRequest()->getTimeNow())
						{
							continue;
						};
					}

					//if we got here the user isn't authorized to update this record.
					return false;
				}
				return true;
				break;


			case vB_Api_Content::ACTION_VIEW:
				$channelType = vB_Types::instance()->getContentTypeID('vBForum_Channel');
				foreach ($nodes as $key => $node)
				{
					if (empty($node['nodeid']) OR !is_numeric($node['nodeid']))
					{
						$check = current($node);

						if (!empty($check['nodeid']) AND is_numeric($check['nodeid']) )
						{
							$node = $check;
						}
					}

					if (!$userContext->getChannelPermission('forumpermissions', 'canview', $node['nodeid'], false, $node['parentid']))
					{
						return false;
					}

					if ($node['contenttypeid'] != $channelType)
					{
						if (!$userContext->getChannelPermission('forumpermissions', 'canviewthreads', $node['nodeid'], false, $node['parentid']))
							{
							//All the juicy stuff is in the content record. Unset it now.
							if (isset($node['content']))
							{
								$content = array();

								foreach(array('channelroute', 'channeltitle', 'channelid', 'starterroute', 'startertitle', 'starterauthorname',
											'starterprefixid', 'starteruserid', 'starterurlident') AS $field)
								{
									if (!empty($nodes[$key]['content'][$field]))
									{
										$content[$field] = $nodes[$key]['content'][$field];
									}
								}
								$nodes[$key]['content'] = $content;
							}
						}

						if (($node['userid'] <> $userid) AND !$userContext->getChannelPermission('forumpermissions', 'canviewothers', $node['nodeid'], false, $node['parentid']))
						{
							//can only see if they  started it.
							$starter = vB_Library::instance('node')->getNodeBare($node['starter']);

							if ($starter['userid'] != $userid)
							{
								return false;
							}
						}
					}

					//If the node is published, we just need to check viewperms.
					if (($userid > 0) AND ($node['viewperms'] > 0) AND ($node['showpublished'] > 0) AND ($node['showapproved'] > 0))
					{
						continue;
					}

					if (($node['viewperms'] > 1) AND ($node['showpublished'] > 0) AND ($node['showapproved'] > 0))
					{
						continue;
					}

					if (!$node['showapproved'] AND !$userContext->getChannelPermission('moderatorpermissions', 'canmoderateposts', $node['nodeid'], false, $node['parentid']))
					{
						throw new vB_Exception_NodePermission($node['nodeid']);
					}

					if (!$node['showpublished'] AND !$userContext->getChannelPermission('moderatorpermissions', 'candeleteposts', $node['nodeid'], false, $node['parentid']))
					{
						throw new vB_Exception_NodePermission($node['nodeid']);
					}

					if ($userContext->getChannelPermission('moderatorpermissions', 'caneditposts', $node['nodeid'], false, $node['parentid']))
					{
						continue;
					}

					if ($node['viewperms'] == 0)
					{
						//Only blog members can view.  We need to find the channel
						if ($node['contenttypeid'] == vB_Types::instance()->getContentTypeId('vBForum_Channel'))
						{
							$checkNodeId = $node['nodeid'];
						}
						else if ($node['starter'] == $node['nodeid'])
						{
							//Check for the the parent's parent
							$checkNodeId = $node['parentid'];
						}
						else
						{
							// The channel is of course the starter's parent.
							$starter = vB_Api::instanceInternal('node')->getNode($node['starter']);
							$checkNodeId = $starter['parentid'];
						}

						$groupInTopic = vB_Api::instanceInternal('user')->getGroupInTopic($userid, $checkNodeId);

						if (!$groupInTopic OR empty($groupInTopic) OR !empty($groupInTopic['errors']))
						{
							//someone with moderator permission can view
							if ($userContext->getChannelPermission('moderatorpermissions', 'caneditposts', $node['nodeid'], false, $node['parentid']))
							{
								continue;
							}
							if ($userContext->getChannelPermission('forumpermissions2', 'canalwaysview', $node['nodeid'], false, $node['parentid']))
							{
								continue;
							}
							//Not O.K.
							throw new vB_Exception_NodePermission($node['nodeid']);
						}

						$validGroups = vB_Api::instanceInternal('usergroup')->fetchPrivateGroups();
						$found = false;
						foreach($groupInTopic as $pair)
						{
							if (in_array($pair['groupid'], $validGroups))
							{
								$found = true;
								break;
							}
						}
						if (!$found)
						{
							throw new vB_Exception_NodePermission($node['nodeid']);
						}
					}
					else if (($node['viewperms'] == 1) AND ($userid < 1))
					{
						throw new vB_Exception_NodePermission($node['nodeid']);
					}


					if (!$userContext->getChannelPermission('forumpermissions', 'canview', $node['nodeid'], false, $node['parentid']))
					{
						throw new vB_Exception_NodePermission($node['nodeid']);
					}
				}

				return true;

				break;

			case vB_Api_Content::ACTION_DELETE:
				foreach ($nodes as $node)
				{
					if (!$userContext->getChannelPermission('moderatorpermissions', 'canremoveposts', $node['nodeid']))
					{
						// Check if it's a SG
						if (vB_Api::instanceInternal('socialgroup')->isSGNode($node['nodeid'])
							AND ($node['contenttypeid'] === vB_Types::instance()->getContentTypeId('vBForum_Channel'))
							AND ($node['userid'] === vB::getCurrentSession()->get('userid'))
						)
						{
							if (!$userContext->hasPermission('socialgrouppermissions', 'candeleteowngroups'))
							{
								return false;
							}
						}
						else
						{
							//If the current user created this and has the correct post permission they can delete.
							if ($node['userid'] != $userid)
							{
								return false;
							}

							if (($node['starter'] == $node['nodeid']) AND $userContext->getChannelPermission('forumpermissions', 'candeletethread', $node['nodeid'], false, $node['parentid']))
							{
								continue;
							}
							else if ($node['starter'] > 0 AND $userContext->getChannelPermission('forumpermissions', 'candeletepost', $node['nodeid'], false, $node['parentid']))
							{
								continue;
							}
							return false;
						}

					}
				}
				return true;
				break;
			case vB_Api_Content::ACTION_APPROVE:
				foreach ($nodes AS $node)
				{
					return $userContext->getChannelPermission('moderatorpermissions', 'canmoderateposts', $node['nodeid']);
				}
				break;
			case vB_Api_Content::ACTION_PUBLISH:
				foreach ($nodes AS $node)
				{
					return $userContext->getChannelPermission('forumpermissions2', 'canpublish', $node['nodeid']);
				}
				break;
			default:
			;
		} // switch
	}

	/*** Permanently deletes a node
	 *	@param	integer	The nodeid of the record to be deleted
	 *
	 *	@return	boolean
	 ***/
	public function delete($nodeid)
	{
		$data = false;

		if (!$this->validate($data, self::ACTION_DELETE, $nodeid))
		{
			throw new vB_Exception_Api('no_delete_permissions');
		}

		return $this->library->delete($nodeid);
	}

	/*** Returns a content api of the appropriate type
	 *
	 *	@param	int		the content type id
	 *
	 *	@return	mixed	content api object
	 ****/
	public static function getContentApi($contenttypeid)
	{
		return vB_Api::instanceInternal('Content_' . vB_Types::instance()->getContentTypeClass($contenttypeid));
	}

	/*** Is this record in a published state based on the times?
	*
	*	@param	mixed
	*
	*	@return	bool
	****/
	public function isPublished($data)
	{
		return $this->library->isPublished($data);
	}


	/*** updates a record
	 *
	 *	@param	mixed		array of nodeid's
	 *	@param	mixed		array of permissions that should be checked.
	 *
	 * 	@return	boolean
	 ***/
	public function update($nodeid, $data)
	{
		if (!$this->validate($data, self::ACTION_UPDATE, $nodeid))
		{
			throw new vB_Exception_Api('no_update_permissions');
		}
		$this->cleanInput($data, $nodeid);

		if (empty($data['title']))
		{
			unset ($data['title']);
		}

		$nodeInfo = vB_Api::instanceInternal('node')->getNode($nodeid);

		//check time limit on editing of thread title
		if(isset($data['title']) AND ($data['title'] != $nodeInfo['title']) AND !vB_Library::instance('node')->canEditThreadTitle($nodeid))
		{
			throw new vB_Exception_Api('exceeded_timelimit_editing_thread_title');
		}

		return $this->library->update($nodeid, $data);
	}

	/*** Returns the node content as an associative array
	 *	@param	integer	The id in the primary table
	 *	@param array permissions
	 *	@param bool	appends to the content the channel routeid and title, and starter route and title the as an associative array

	 *	@return	int
	 ***/
	public function getContent($nodeid, $permissions = false)
	{
		return $this->getFullContent($nodeid, $permissions);
	}

	/*** Returns the node content plus the channel routeid and title, and starter route and title the as an associative array
	 *	@param	integer	The id in the primary table
	 *
	 *	@return	mixed
	 ***/
	public function getFullContent($nodeid, $permissions = false)
	{
		$temporary = $this->library->getFullContent($nodeid, $permissions);
		$data = array();

		if (!$this->validate($data, self::ACTION_VIEW, $nodeid, $temporary))
		{
			throw new vB_Exception_Api('no_permission');
		}
		return $temporary;
	}


	/*** Gets the main conversation node.
	*
	* 	@param	int		the nodeid
	* 	@return	mixed	the main conversation node
	*/
	public function getConversationParent($nodeid)
	{
		return $this->library->getConversationParent($nodeid);
	}

	/*** Finds the correct conversation starter for a node
	 *
	 *	@param	int		nodeid of the item being checked
	 *
	 *	@return	int		the conversation starter's nodeid
	 ***/
	protected function getStarter($nodeid)
	{
		return $this->library->getStarter($nodeid);
	}


	/*** Processing after a move. In this case, set the text counts and "last" data.
	 *
	 * 	@param	int		the nodeid
	 * 	@param	int		the old parentid
	 * 	@param	int		the new parentid
	 */

	public function afterMove($nodeid, $oldparent, $newparent)
	{
		//We add the various counts to the new parent, and subtract from the old.
		$node = $this->nodeApi->getNode($nodeid);

		$published = $this->isPublished($node);
		$this->assertor->assertQuery('vBForum:UpdateParentCount',
		array(vB_dB_Query::TYPE_KEY =>vB_dB_Query::QUERY_STORED,
		'nodeid' => $oldparent,
		'textChange' => (-1 * $published), 'textUnpubChange' => (-1 * !$published)));

		$this->assertor->assertQuery('vBForum:UpdateParentCount',
		array(vB_dB_Query::TYPE_KEY =>vB_dB_Query::QUERY_STORED,
		'nodeid' => $newparent,
		'textChange' => (1 * $published), 'textUnpubChange' => (1 * !$published)));

		$oldParentAncestors = $newParentAncestors = array();

		// Fetch ancestors
		// fetchClosureParent() cannot be used here as it does not return the contenttypeid.
		$parentsInfo = vB_Library::instance('node')->getNodes(array($oldparent, $newparent));
		$oldAncestors = array(
			array('child' => $oldparent, 'parent' => $oldparent, 'depth' => 0, 'contenttypeid' => $parentsInfo[$oldparent]['contenttypeid'])
		);

		$newAncestors = array(
			array('child' => $newparent, 'parent' => $newparent, 'depth' => 0, 'contenttypeid' => $parentsInfo[$newparent]['contenttypeid'])
		);

		$oldAncestors += vB_Library::instance('node')->getParents($oldparent);
		$newAncestors += vB_Library::instance('node')->getParents($newparent);
		$ancestors = array_merge($oldAncestors, $newAncestors);

		$toUpdate = array();
		if ($ancestors)
		{
			foreach($ancestors AS $ancestor)
			{
				if ($ancestor['child'] == $oldparent)
				{
					if (array_key_exists($ancestor['parent'], $newParentAncestors))
					{
						// common ancestor, remove it
						unset($newParentAncestors[$ancestor['parent']]);
					}
					else
					{
						$oldParentAncestors[$ancestor['parent']] = $ancestor['parent'];
					}
				}
				else if ($ancestor['child'] == $newparent)
				{
					if (array_key_exists($ancestor['parent'], $oldParentAncestors))
					{
						// common ancestor, remove it
						unset($oldParentAncestors[$ancestor['parent']]);
					}
					else
					{
						$newParentAncestors[$ancestor['parent']] = $ancestor['parent'];
					}
				}

				$toUpdate[$ancestor['parent']] = array('nodeid' => $ancestor['parent'], 'contenttypeid' => $ancestor['contenttypeid']);
			}
		}
		krsort($toUpdate);

		if (!empty($oldParentAncestors))
		{
			$this->assertor->assertQuery('vBForum:UpdateAncestorCount',
				array(vB_dB_Query::TYPE_KEY =>vB_dB_Query::QUERY_STORED,
				'nodeid' => $oldParentAncestors,
				'totalChange' => -1 * ($node['totalcount'] + (1 * $published)), 'totalUnpubChange' => -1 * ($node['totalunpubcount'] + (1 * !$published)))
			);
		}

		if (!empty($newParentAncestors))
		{
			$this->assertor->assertQuery('vBForum:UpdateAncestorCount',
				array(vB_dB_Query::TYPE_KEY =>vB_dB_Query::QUERY_STORED,
				'nodeid' => $newParentAncestors,
				'totalChange' => ($node['totalcount'] + (1 * $published)), 'totalUnpubChange' => ($node['totalunpubcount'] + (1 * !$published))));
		}

		// reset last content for all parents that have the deleted node
		$this->assertor->update('vBForum:node',
				array(
					'lastcontent' => 0,
					'lastcontentid' => 0,
					'lastcontentauthor' => '',
					'lastauthorid' => 0),
				array(
					'nodeid' => $oldparent
				)
		);

		// and update each ancestor last data
		$channelTypeId = vB_Types::instance()->getContentTypeID('vBForum_Channel');
		foreach ($toUpdate AS $ancestor)
		{
			if ($ancestor['contenttypeid'] == $channelTypeId)
			{
				$this->assertor->assertQuery('vBForum:fixNodeLast', array('nodeid' => $ancestor['nodeid']));
			}
			else
			{
				$this->assertor->assertQuery('vBForum:updateLastData', array('parentid' => $ancestor['nodeid'], 'timenow' => vB::getRequest()->getTimeNow()));
			}
		}
	}

	/**
	 * The classes  that inherit this should implement this function
	 * It should return the content that should be indexed
	 * If there is a title field, the array key for that field should be 'title',
	 * the rest of the text can have any key
	 * @param int $nodeId - it might be the node (assiciative array)
	 * @return array $indexableContent
	 */
	public function getIndexableContent($nodeId, $include_attachments = true)
	{
		return $this->library->getIndexableContent($nodeId, $include_attachments);
	}


	/**
	 * Returns an array with bbcode options for the node.
	 * @param type $nodeId
	 */
	public function getBbcodeOptions($nodeId)
	{
		// This method needs to be overwritten for each relevant contenttype
		return array();
	}

	/** Gives the current board time- needed to set publishdate.
	*
	*	@return INT
	*
	**/
	public function getTimeNow()
	{
		return vB::getRequest()->getTimeNow();
	}

	/** This returns the text to quote a node. Used initially for private messaging.
	*
	* 	@param	integer		the nodeid of the quoted item
	*
	*	@return	string		quote text.
	**/
	public function getQuoteText($nodeid)
	{
		//This must be implemented in the child class
		{
			throw new vB_Exception_Api('feature_not_implemented');
		}
	}


	/** This returns the text to quote a node. Used initially for private messaging.
	 *
	 * 	@param	integer		the nodeid of the quoted item
	 *
	 *	@return	string		quote text.
	 **/
	public function createQuoteText($nodeid, $pageText)
	{
		//This must be implemented in the child class
		{
			throw new vB_Exception_Api('feature_not_implemented');
		}
	}


	/** returns the tables used by this content type.
	 *
	*	@return	Array
	 *
	 **/
	public function fetchTableName()
	{
		return $this->library->fetchTableName();
	}


	/** determines whether a specific node is a visitor message
	 *
	 *	@param	int
	 *
	 *	@return bool
	 **/
	public function isVisitorMessage($nodeid)
	{
		return $this->library->isVisitorMessage($nodeid);
	}

	/** Extracts the video and photo content from text.
	 *
	 *	@param	string
	 *
	 *	@return	mixed	array of "photo", "video". Each is an array of images.
	 **/
	public function extractMedia($rawtext)
	{
		$photos = array();
		$videos = array();
		$filter = '~\[video.*\[\/video~i';
		$matches = array();
		$count = preg_match_all($filter, $rawtext, $matches);
		return $matches;
	}

	/**
	 * Adds content info to $result so that merged content can be edited.
	 * This method needs to be implemented in the content subclasses that support merging.
	 * @param array $result
	 * @param array $content
	 */
	public function mergeContentInfo(&$result, $content)
	{
		// content cannot be merged unless this method is implemented
		throw new vB_Exception_Api('merge_invalid_contenttypes');
	}

	/*** For posts to blog-type channel, checks allow_post if applicable
	 *
	 *	@param	mixed	the channel to which we are posting
	 *	@param	mixed	the parent of this potential post
	 *	@param	mixed	reference to the data we are going to add
	 ***/
	protected function checkChannelSettings($channel, $parent, &$data, $userid)
	{
		// Only apply allow_post to replies and comments
		if ($parent['nodeid'] !== $channel['nodeid'])
		{
			if (vB::getUserContext()->isSuperAdmin())
			{
				return true;
			}

			if (($channel['type'] == 'blog' OR $channel['type'] == 'sg') AND vB_Api::instanceInternal('blog')->fetchOwner($channel['nodeid']) == $userid)
			{
				return true;
			}

			//If we set the OPTION_ALLOW_POST off then unless we're owner or superadmin we can't add
			if ((empty($channel['allow_post']) OR $channel['allow_post'] <= 0) AND (!empty($channel['isPhotoOrVideo']) AND !$channel['isPhotoOrVideo']))
			{
				throw new vB_Exception_Api('no_permission');
			}
		}
	}

	/*** For checking the limits about content
	 *
	 *	@param	array			info about the content that needs to be added
	 *
	 *  @return boolean/text	either true if all the tests passed or thrown exception
	 ***/
	protected function verify_limits($data)
	{
		// This is where conent general checks should go
		return true;
	}
}


