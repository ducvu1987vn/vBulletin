<?php
if (!defined('VB_ENTRY')) die('Access denied.');
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
 * vB_Api_Content_Privatemessage
 *
 * @package vBApi
 * @author ebrown
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Library_Content_Privatemessage extends vB_Library_Content_Text
{
	//override in client- the text name
	protected $contenttype = 'vBForum_PrivateMessage';

	//The table for the type-specific data.
	protected $tablename = array('text', 'privatemessage');

	protected $folders = array();

	CONST TRASH_FOLDER = 'trash';
	CONST REQUEST_FOLDER = 'requests';
	CONST MESSAGE_FOLDER = 'messages';
	CONST NOTIFICATION_FOLDER = 'your_notifications';
	CONST PENDING_FOLDER = 'pending_posts';
	CONST SENT_FOLDER = 'sent_items';
	CONST NOTIFICATION_TYPE_VOTE = 'vote';
	CONST NOTIFICATION_TYPE_VOTEREPLY = 'vote_reply';
	CONST NOTIFICATION_TYPE_RATE = 'rate';
	CONST NOTIFICATION_TYPE_REPLY = 'reply';
	CONST NOTIFICATION_TYPE_FOLLOW = 'follow';
	CONST NOTIFICATION_TYPE_FOLLOWING = 'following';
	CONST NOTIFICATION_TYPE_VM = 'vm';
	CONST NOTIFICATION_TYPE_COMMENT = 'comment';
	CONST NOTIFICATION_TYPE_THREADCOMMENT = 'threadcomment';
	CONST NOTIFICATION_TYPE_MODERATE = 'moderate';
	protected $nodeApi;
	protected $pmChannel;

	//Cache our knowledge of records the current user can see, to streamline permission checking.
	protected $canSee = array();
	protected $foldersInfo = array();

	//these are the notification message types. Message and request are handled differently.
	//the parameter is whether they need an aboutid.
	protected $notificationTypes = array(vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_VOTE, vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_VOTEREPLY,
		vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_RATE, vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_REPLY,
		vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_FOLLOW, vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_FOLLOWING,
		vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_VM, vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_COMMENT,
		vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_THREADCOMMENT,
		vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_MODERATE);


	// these are channel requests for ownership/moderation/membership
	protected $channelRequests = array(
            vB_Api_Node::REQUEST_TAKE_OWNER,
            vB_Api_Node::REQUEST_TAKE_MODERATOR,
            vB_Api_Node::REQUEST_TAKE_MEMBER,
            vB_Api_Node::REQUEST_GRANT_OWNER,
            vB_Api_Node::REQUEST_GRANT_MODERATOR,
            vB_Api_Node::REQUEST_GRANT_MEMBER,
            vB_Api_Node::REQUEST_SG_TAKE_OWNER,
            vB_Api_Node::REQUEST_SG_TAKE_MODERATOR,
            vB_Api_Node::REQUEST_SG_TAKE_MEMBER,
            vB_Api_Node::REQUEST_SG_GRANT_OWNER,
            vB_Api_Node::REQUEST_SG_GRANT_MODERATOR,
            vB_Api_Node::REQUEST_SG_GRANT_MEMBER
        );

	/**
	 * If true, then creating a node of this content type will increment
	 * the user's post count. If false, it will not. Generally, this should be
	 * true for topic starters and replies, and false for everything else.
	 *
	 * @var	bool
	 */
	protected $includeInUserPostCount = false;

	protected function __construct()
	{
		parent::__construct();
		$this->pmChannel = $this->nodeApi->fetchPMChannel();
	}

	public function addMessageNoFlood($data, $options = array())
	{
		$floodcheck = $this->doFloodCheck;
		$this->doFloodCheck = false;
		
		if (empty($data['sender']))
		{
			$userInfo = vB::getCurrentSession()->fetch_userinfo();
			$data['sender'] = $userInfo['userid'];
		}
		
		$nodeid = $this->add($data, $options);
		$this->doFloodCheck = $floodcheck;
		return $nodeid;
	}

	protected function sendEmailNotification($data)
	{
		if (isset($data['msgtype']) AND $data['msgtype'] == 'request')
		{
			if (isset($data['about']) AND $data['about'] == 'follow')
			{
				$maildata = vB_Api::instanceInternal('phrase')->
					fetchEmailPhrases('follow_request', array(
						$data['username'],
						vB_Api::instanceInternal('user')->fetchUserName($data['aboutid']),
						vB5_Route::buildUrl('privatemessage|nosession|fullurl', array(
							'folderid' => $data['folderid'], 'pagenum' => 1, 'action' => $data['msgtype'])),
						vB::getDatastore()->getOption('bbtitle'),
					),
					array(vB::getDatastore()->getOption('bbtitle'))
				);
			}
			else if (isset($data['about']) AND $data['about'] == 'moderator_to')
			{
				$maildata = vB_Api::instanceInternal('phrase')->
					fetchEmailPhrases('moderation_request', array(
						$data['username'],
						vB_Api::instanceInternal('user')->fetchUserName($data['userid']),
						vB5_Route::buildUrl('privatemessage|nosession|fullurl', array(
							'folderid' => $data['folderid'], 'pagenum' => 1, 'action' => $data['msgtype'])),
						vB::getDatastore()->getOption('bbtitle'),
					),
					array(vB::getDatastore()->getOption('bbtitle'))
				);
			}
			else if (isset($data['about']) AND $data['about'] == 'owner_to')
			{
				$maildata = vB_Api::instanceInternal('phrase')->
					fetchEmailPhrases('ownership_request', array(
						$data['username'],
						vB_Api::instanceInternal('user')->fetchUserName($data['userid']),
						vB5_Route::buildUrl('privatemessage|nosession|fullurl', array(
							'folderid' => $data['folderid'], 'pagenum' => 1, 'action' => $data['msgtype'])),
						vB::getDatastore()->getOption('bbtitle'),
					),
					array(vB::getDatastore()->getOption('bbtitle'))
				);
			}
		}
		else if (isset($data['about']) AND $data['about'] == self::NOTIFICATION_TYPE_FOLLOW)
		{
			$maildata = vB_Api::instanceInternal('phrase')->
				fetchEmailPhrases('follow_approve', array(
					$data['username'],
					vB_Api::instanceInternal('user')->fetchUserName($data['aboutid']),
					vB5_Route::buildUrl('profile|nosession|fullurl', array(
						'userid' => $data['userid'])),
					vB::getDatastore()->getOption('bbtitle'),
					),
					array(vB::getDatastore()->getOption('bbtitle')
				)
			);
		}
		else if (isset($data['msgtype']) AND $data['msgtype'] == 'message')
		{
			$maildata = vB_Api::instanceInternal('phrase')->
				fetchEmailPhrases('privatemessage', array(
					$data['username'],
					vB_Api::instanceInternal('user')->fetchUserName($data['userid']),
					vB5_Route::buildUrl('privatemessage|nosession|fullurl', array(
						'action' => 'list', 'pagenum' => 1, 'folderid' => $data['folderid'])),
					$data['rawtext'],
					vB::getDatastore()->getOption('bbtitle'),
					),
					array(vB::getDatastore()->getOption('bbtitle')
				)
			);
		}
		else if ((isset($data['msgtype']) AND $data['msgtype'] == 'notification') AND
				 (isset($data['about']) AND $data['about'] == self::NOTIFICATION_TYPE_RATE))
		{
			$node = $this->nodeApi->getNode($data['aboutid']);
			$maildata = vB_Api::instanceInternal('phrase')->
				fetchEmailPhrases('like', array(
					$data['username'],
					vB_Api::instanceInternal('user')->fetchUserName($data['userid']),
					($node['starter'] == $node['parentid'] ? 'post' : 'thread'),
					vB5_Route::buildUrl($node['routeid'] . '|nosession|fullurl', $node),
					vB::getDatastore()->getOption('bbtitle'),
					),
					array(vB::getDatastore()->getOption('bbtitle')
				)
			);
		}

		if (!empty($maildata))
		{
			// Sending the email
			vB_Mail::vbmail($data['email'], $maildata['subject'], $maildata['message'], false);
		}
	}

	/** This adds a new message
	 *
	 *	@param	mixed	must include 'sentto', 'contenttypeid', and the necessary data for that contenttype.
	 *  @param	array	Array of options for the content being created.
	 *					Available options include:
	 *
	 *	@return	int		the new nodeid.
	 *	Notes: 		For Notifications, be sure to go through sendNotifications() and not call this function directly.
	 *  			This is because the notification options check is called in library\content->sendNotifications(),
	 * 				which library\privatemesage inherits.
	 **/
	public function add($data, array $options = array(), $convertWysiwygTextToBbcode = true)
	{
		$usercontext = vB::getUserContext();
		//If we're installing, just abort.
		if (defined('VBINSTALL'))
		{
			return true;
		}

		$sender = $data['sender'];
		if (isset($data['msgRecipients']) AND empty($data['sentto']))
		{
			$recipientNames = explode(',', $data['msgRecipients']);
			foreach ($recipientNames as $k => $name)
			{
				$recipientNames[$k] = vB_String::htmlSpecialCharsUni($name);
			}

			$recipQry = $this->assertor->getRows('fetchPmRecipients', array('usernames' => $recipientNames, 'userid' => $sender));
			$bf_misc_useroptions = vB::getDatastore()->getOption('bf_misc_useroptions');

			if (!$recipQry OR !empty($recipQry['errors']))
			{
				throw new vB_Exception_Api('invalid_pm_recipients');
			}
			foreach ($recipQry as $recipient)
			{
				$this->checkCanReceivePM($recipient);
				$data['sentto'][] = $recipient['userid'];
			}
		}

		if (!isset($data['msgtype']) OR ($data['msgtype'] <> 'request' AND $data['msgtype'] <> 'notification'))
		{
			$data['msgtype'] = 'message';
		}
		else if (($data['msgtype'] == 'notification'))
		//If we have a notification we need an about and an aboutid
		{
			if (empty($data['about']) OR empty($data['aboutid']) OR empty($data['sentto']))
			{
				throw new vB_Exception_Api('invalid_data');
			}

		}

		//If we have a message we need text content
		if (($data['msgtype'] == 'message') AND (empty($data['rawtext']) AND empty($data['pagetext'])))
		{
			throw new vB_Exception_Api('need_privatemessage_text');
		}

		if (($data['msgtype'] == 'request') AND (empty($data['rawtext']) AND empty($data['pagetext'])) AND !empty($data['sentto']))
		{
			$recipient = vB_User::fetchUserinfo($data['sentto']);
			switch ($data['about'])
			{
				case vB_Api_Node::REQUEST_TAKE_OWNER:
					$channel = vB_Library::instance('node')->getNodeBare($data['aboutid']);
					$phrase = vB_Api::instanceInternal('phrase')->fetch(array('sent_ownership_transfer_request_for_x_to_y'));
					$data['rawtext'] = $data['pagetext'] = vsprintf($phrase['sent_ownership_transfer_request_for_x_to_y'], array($channel['title'], $recipient['username']));
				break;
				case vB_Api_Node::REQUEST_TAKE_MODERATOR:
					$channel = vB_Library::instance('node')->getNodeBare($data['aboutid']);
					$phrase = vB_Api::instanceInternal('phrase')->fetch(array('sent_moderation_request_for_x_to_y'));
					$data['rawtext'] = $data['pagetext'] = vsprintf($phrase['sent_moderation_request_for_x_to_y'], array($channel['title'], $recipient['username']));
				break;
				case vB_Api_Node::REQUEST_GRANT_MEMBER:
					$channel = vB_Library::instance('node')->getNodeBare($data['aboutid']);
					$phrase = vB_Api::instanceInternal('phrase')->fetch(array('sent_subscription_request_to_x'));
					$data['rawtext'] = $data['pagetext'] = vsprintf($phrase['sent_subscription_request_to_x'], $channel['title']);
				break;
				case vB_Api_Node::REQUEST_SG_GRANT_SUBSCRIBER:
					$channel = vB_Library::instance('node')->getNodeBare($data['aboutid']);
					$phrase = vB_Api::instanceInternal('phrase')->fetch(array('sent_subscription_request_to_x'));
					$data['rawtext'] = $data['pagetext'] = vsprintf($phrase['sent_subscription_request_to_x'], $channel['title']);
				break;
				default:
					$phrase = vB_Api::instanceInternal('phrase')->fetch(array('sent_follow_request_to_x'));
					$data['rawtext'] = $data['pagetext'] = vsprintf($phrase['sent_follow_request_to_x'], $recipient['username']);
				break;
			}
		}
		$this->checkFolders($sender);
		$sendto = array();
		if (isset($data['respondto']))
		{
			//We have a forward/reply. We maintain the node hierarchy. If it's a reply
			// we also need to keep the list of recipients.
			$data['parentid'] = $data['respondto'];
			//Obviously we've read this, if we're forwarding it.
			$this->setRead($data['respondto'], 1, $sender);
			$recipients = $this->assertor->getRows('vBForum:getRecipientsForNode', array('nodeid' => $data['respondto']));
			$msgSender = 0;
			$senderIncluded = false;
			foreach ($recipients AS $recipient)
			{
				if ($msgSender)
				{
					continue;
				}

				if ($recipient['folder'] == self::SENT_FOLDER)
				{
					$msgSender = $recipient['userid'];
				}
			}

			foreach ($recipients as $recipient)
			{
				$this->checkCanReceivePM($recipient);
				if ($recipient['userid'] == $msgSender AND $recipient['folder'] != self::SENT_FOLDER)
				{
					$senderIncluded = true;
				}

				$data['sentto'][] = intval($recipient['userid']);
			}
		}
		else if (isset($data['forward']))
		{
			if (empty($data['sentto']))
			{
				throw new vB_Exception_Api('invalid_request');
			}

			$data['parentid'] = $this->pmChannel;
			//Obviously we've read this, if we're forwarding it.
			$this->setRead($data['forward'], 1, $sender);
		}
		else
		{
			//We'll get the folders into which we need to insert this record. In the process we'll
			// validate that all the sentto id's are valid.
			$data['parentid'] = $this->pmChannel;
		}

		if (empty($data['sentto']))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		if (!is_array($data['sentto']))
		{
			$sendto = array($data['sentto']);
		}
		else
		{
			$sendto = array_unique($data['sentto']);
		}

		//We can't pass recipients to the parent add method.
		$data['userid']	= $sender;
		$fields = array('parentid', 'rawtext', 'pagetext', 'msgtype', 'title', 'userid', 'about',
			'aboutid', 'folderid', 'deleted', 'msgread', 'publishdate', 'url', 'filedataid',
			'url_title', 'url_meta', 'url_image');
		$contentData = array();
		foreach ($fields as $field)
		{
			if (isset($data[$field]))
			{
				$contentData[$field] = $data[$field];
			}
		}

		// create the node, unless it's a notification
		if (!($data['msgtype'] == 'notification'))
		{
			$nodeid = parent::add($contentData, $options, $convertWysiwygTextToBbcode);
		}
		else
		{
			if (in_array($data['about'], array(
				vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_REPLY,
				vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_THREADCOMMENT
			)))
			{
				// for threadcomments we have to check parentage so that we get 1 notification per thread pointing to the reply that has the newest comment
				// for replies we have to check parentage so that we get 1 notification per thread pointing to the latest reply
				$existingNotification = $this->assertor->getRow('vBForum:fetchThreadNotification',
					array(vB_dB_QUERY::TYPE_KEY => vB_dB_QUERY::QUERY_STORED, 'userid' => $data['sentto'],
						'about' => $data['about'], 'aboutid' => $data['aboutid']));
			}
			else
			{
				// for comments, we just check for aboutid & about
				$existingNotification = $this->assertor->getRow('vBForum:fetchNotification',
					array(vB_dB_QUERY::TYPE_KEY => vB_dB_QUERY::QUERY_STORED, 'userid' => $data['sentto'],
						'about' => $data['about'], 'aboutid' => $data['aboutid']));
			}

			// does an existing notification exist?
			if ($existingNotification AND !empty($existingNotification) AND empty($existingNotification['errors']))
			{
				// grab the nodeid for the existing notification
				$nodeid = (int) $existingNotification['nodeid'];
				// if the old notification was deleted or read, we have to update it
				if($existingNotification['deleted'] != 0 OR $existingNotification['msgread'] != 0)
				{
					// undelete the notification
					$this->assertor->assertQuery('vBForum:sentto', array(vB_dB_QUERY::TYPE_KEY => vB_dB_QUERY::QUERY_UPDATE,
						vB_dB_QUERY::CONDITIONS_KEY =>	array('nodeid' => $nodeid),
						'deleted' => 0, 'msgread' => 0));
					$this->assertor->assertQuery('vBForum:privatemessage', array(vB_dB_QUERY::TYPE_KEY => vB_dB_QUERY::QUERY_UPDATE,
						vB_dB_QUERY::CONDITIONS_KEY =>	array('nodeid' => $nodeid),
						'deleted' => 0));
					// update the publishdate of the node
					$this->assertor->assertQuery('vBForum:node', array(vB_dB_QUERY::TYPE_KEY => vB_dB_QUERY::QUERY_UPDATE,
						vB_dB_QUERY::CONDITIONS_KEY =>	array('nodeid' => $nodeid),
						'publishdate' => vB::getRequest()->getTimeNow()));
				}

				// for threadcomments, we have to update the pm.aboutid to point to the reply with the newest comment
				// for replies, we have to update the pm.aboutid to point to the latest reply
				if (in_array($data['about'], array(
					vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_REPLY,
					vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_THREADCOMMENT
					)))
				{
					$this->assertor->assertQuery('vBForum:privatemessage', array(vB_dB_QUERY::TYPE_KEY => vB_dB_QUERY::QUERY_UPDATE,
						vB_dB_QUERY::CONDITIONS_KEY =>	array('nodeid' => $nodeid),
						'aboutid' => $data['aboutid']));
				}
			}
			else
			{
				// create a new notification since there isn't one
				$nodeid = parent::add($contentData, $options, $convertWysiwygTextToBbcode);
			}
		}


		if (!$nodeid OR !empty($nodeid['errors']) OR !intval($nodeid))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		//If we are passed "notification' or 'request', then this is just inserted for the recipient. But the user must be an admin.
		$insertSent = false;
		if ($data['msgtype']== 'notification')
		{
			$folderKey = self::NOTIFICATION_FOLDER;
		}
		else if ($data['msgtype'] == 'request')
		{
			$folderKey = self::REQUEST_FOLDER;
		}
		else
		{
			$folderKey = self::MESSAGE_FOLDER;
			$insertSent = true;
		}

		//Note that if this is a response, sendto is empty.
		foreach ($sendto AS $recipient)
		{
			$this->checkFolders($recipient);
			$sendData = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT, 'userid' => $recipient,
				'nodeid' => $nodeid, 'folderid' => $this->folders[$recipient]['systemfolders'][$folderKey]);

			// create a sentto record only if it's not an existing notification
			if( !($data['msgtype'] == 'notification' AND ($existingNotification AND !empty($existingNotification) AND empty($existingNotification['errors']))) )
			{
				$this->assertor->assertQuery('vBForum:sentto', $sendData);
			}

			$recipientInfo = vB_Api::instanceInternal('User')->fetchUserinfo($recipient);

			if ($recipientInfo['autosubscribe'] == 1)
			{
				if ($contentData['msgtype'] == 'request')
				{
					$contentData['sentto'] = $data['sentto'];
					$contentData['folderid'] = $this->folders[$recipient]['systemfolders'][$folderKey];
					$contentData['email'] = $recipientInfo['email'];
					$contentData['username'] = $recipientInfo['username'];
					$this->sendEmailNotification($contentData);
				}
				elseif ($data['msgtype'] == 'message' AND $recipient != vB::getCurrentSession()->get('userid'))
				{
					$data['folderid'] = $this->folders[$recipient]['systemfolders'][$folderKey];
					$data['recipient'] = $recipient;
					$data['contentid'] = $nodeid;
					$data['email'] = $recipientInfo['email'];
					$data['username'] = $recipientInfo['username'];
					$this->sendEmailNotification($data);
				}
				elseif ($contentData['msgtype'] == 'notification' AND $contentData['about'] == self::NOTIFICATION_TYPE_RATE)
				{
					$contentData['email'] = $recipientInfo['email'];
					$contentData['username'] = $recipientInfo['username'];
					$this->sendEmailNotification($contentData);
				}
			}
		}

		// insert message starter for sender if needed
		if (isset($data['respondto']) AND $data['respondto'] AND !$senderIncluded)
		{
			$this->checkFolders($msgSender);
			$sendData = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT, 'userid' => $msgSender,
				'nodeid' => $data['respondto'], 'folderid' => $this->folders[$msgSender]['systemfolders'][$folderKey], 'msgread' => 1);
			$this->assertor->assertQuery('vBForum:sentto', $sendData);
		}
		//If this is a new message, we also insert a "sentto" record for the sender, but we mark that "read".
		//That ensures we properly handle replies.
		if (!in_array($data['msgtype'], array('notification', 'request')))
		{
			//If someone deleted their message we need to restore it. For that we need the starter.
			//This can only occur, of course, if this is a response.
			$existing = $this->nodeApi->getNode($data['parentid']);
			// only check the "trash" and "messages" folders for everyone. We don't want to accidentally change
			// something in the sent_items folder, for instance
			$includeFoldersQry = $this->assertor->getRows('vBForum:messagefolder',
				array(	vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'titlephrase'=>array(self::TRASH_FOLDER, self::MESSAGE_FOLDER), vB_Db_Query::COLUMNS_KEY => array('folderid')));
			$includeFolders = array(-1);	// -1 so that array is not empty. Other wise IN clause breaks
			foreach($includeFoldersQry AS $includeFolder)
			{
				$includeFolders[] = $includeFolder['folderid'];
			}
			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			vB_dB_Query::CONDITIONS_KEY =>array(array('field' => 'nodeid', 'value' => $existing['starter']),
					array('field' => 'userid', 'value' => $sender, 'operator' => vB_dB_Query::OPERATOR_NE),
					'folderid' => $includeFolders
					),
				'msgread' => 0, 'deleted' => 0);
			$this->assertor->assertQuery('vBForum:sentto', $data);

			// @TODO : Shouldn't we move the "restored" messages back to the inbox...?
			$this->setRead($nodeid, 1, $sender);
		}

		if ($insertSent)
		{
			$this->checkFolders($sender);
			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT, 'userid' => $sender,
				'nodeid' => $nodeid, 'folderid' => $this->folders[$sender]['systemfolders'][self::SENT_FOLDER],
				'msgread' => 1);
			$this->assertor->assertQuery('vBForum:sentto', $data);
		}

		$sendto[] = $sender; // duplicate ids is fine in this array
		$this->buildPmTotals($sendto);

		vB_Api::instance('Search')->index($nodeid);
		return $nodeid;
	}

	// $recipient is either a user info array, or an array with a userid
	protected function checkCanReceivePM($recipient)
	{
		// Make sure we have all user data.
		if (empty($recipient['username']))
		{
			$recipient = vB_Library::instance('user')->fetchUserinfo($recipient['userid']);
		}
		$usercontext = vB::getUserContext();
		$recipientcontext = vB::getUserContext($recipient['userid']);
		$recipientsettings = vB_Api::instanceInternal('user')->fetchUserSettings($recipient['userid']);
		$current_user_following = vB_Api::instanceInternal('follow')->isFollowingUser($recipient['userid']);
		$pmquota = $recipientcontext->getLimit('pmquota');

		if (!$usercontext->isAdministrator()
			AND (
				!$recipientsettings['receivepm']
				OR !$pmquota
				OR (
					$recipientsettings['receivepmbuddies']
					AND ($current_user_following != 1)
				)
			)
		)
		{
			throw new vB_Exception_Api('pmrecipturnedoff', $recipient['username']);
		}

		if ($recipient['pmtotal'] >= $pmquota AND !$usercontext->hasPermission('pmpermissions', 'canignorequota'))
		{
			throw new vB_Exception_Api('pmquotaexceeded', array($recipient['username']));
		}

	}

	protected function isFlood($data)
	{
		if (isset($data['msgtype']) AND $data['msgtype'] == 'notification')
		{
			return false;
		}
		return parent::isFlood($data);
	}

	protected function duplicateCheck($data, $options = array())
	{
		if (isset($data['msgtype']) AND (($data['msgtype'] == 'notification') OR ($data['msgtype'] == 'request')))
		{
			return false;
		}
		return parent::duplicateCheck($data, $options);
	}


	/*** Permanently deletes a node
	 *	@param	integer	The nodeid of the record to be deleted
	 *
	 *	@return	boolean
	 ***/
	public function delete($nodeid)
	{
		//Call the parent first. It will do the permission checks, among other things.
		$result = parent::delete($nodeid);

		if ($result)
		{
			//We need to delete from the sentto table.
			$users = array();
			$recipients = $this->assertor->getRows('vBForum:sentto', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'nodeid' => $nodeid
			));

			foreach ($recipients AS $recipient)
			{
				$users[] = $recipient['userid'];
			}

			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'nodeid' => $nodeid);
				$this->assertor->assertQuery('vBForum:sentto', $data);

			if (!empty($users))
			{
				$this->buildPmTotals($users);
			}
		}
		return $result;
	}

	/** Moves a message to a different folder
	 *
	 *	@param	int		the node to be moved
	 *	@param	int		the new parent node.
	 *
	 *	@return	bool	did it succeed?
	 *
	 **/
	public function moveMessage($nodeid, $newFolderid, $existing)
	{
		//A user can only move, obviously, to one of their own folders
		$currentUser = vB::getCurrentSession()->get('userid');
		$this->checkFolders($currentUser);

		if (array_key_exists($newFolderid , $this->folders[$currentUser]['folders']))
		{
			$movedMsgs = array();
			foreach ($existing as $nodeRecord)
			{
				$key = $nodeRecord['nodeid'] . '-' . $nodeRecord['userid'];
				if (!in_array($key, $movedMsgs))
				{
					$data = array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
						'folderid' => $newFolderid,
						'deleted'  => 0,
						vB_dB_Query::CONDITIONS_KEY => array(
							array('field' => 'userid',   'value' => $currentUser),
							array('field' => 'nodeid',   'value' => $nodeRecord['nodeid']),
							array('field' => 'folderid', 'value' => $nodeRecord['folderid'])
					));
					$result = $this->assertor->assertQuery('vBForum:sentto', $data);
					$movedMsgs[] = $key;
				}
				else
				{
					$this->assertor->assertQuery('vBForum:sentto', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
						'userid' => $nodeRecord['userid'],
						'nodeid' => $nodeRecord['nodeid'],
						'folderid' => $nodeRecord['folderid']
					));
				}
			}
			return true;
		}
		else
		{
			throw new vB_Exception_Api('invalid_data');
		}
	}

	/** Get a message
	 *
	 *	@param	int		the nodeid
	 *
	 *	@return	mixed	array of data
	 *
	 **/
	public function getMessage($nodeid)
	{
		$content = $this->nodeApi->getNode($nodeid);
		$userid =  vB::getCurrentSession()->get('userid');
		return $this->getMessageTree($nodeid, array($userid, $content['userid']), $userid);
	}

	/**
	 * Get a single request
	 *
	 * @param	int		the nodeid
	 *
	 * @return	array The node data array for the request
	 *
	 **/
	public function getRequest($nodeid)
	{
		$userInfo =  vB::getCurrentSession()->fetch_userinfo();
		$userid = $userInfo['userid'];

		if (!intval($userid))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		$content = $this->nodeApi->getNodeContent($nodeid);

		//getNodeContent returns a list.
		$content = $content[$nodeid];

		//if this is the author we can return the value
		if ($content['userid'] == $userid)
		{
			return $content;
		}
		else
		{
			//Maybe this is a recipient.
			$recipients = $this->assertor->getRows('vBForum:sentto', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'nodeid' => $nodeid));
			$canshow = false;
			foreach ($recipients as $recipient)
			{
				if ($recipient['userid'] == $userid)
				{
					return $content;
				}
			}
		}

		//If we got here, this user isn't authorized to see this record. Well, it's also possible this may not exist.
		throw new vB_Exception_Api('no_permission');
	}
	/** Get a message and all replies
	 *
	 *	@param	int		the nodeid
	 *
	 *	@return	mixed	array of data
	 *
	 **/
	public function getMessageTree($nodeid, $exclude, $userid)
	{
		//The permissions are checked before we get here, so we don't need to be concerned
		$messagesQry = $this->assertor->getRows('vBForum:getPrivateMessageTree', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'nodeid' => $nodeid,
			'userid' => $userid
		));

		$messages = array();
		foreach ($messagesQry AS $message)
		{
			if (isset($messages[$message['nodeid']]))
			{
				continue;
			}
			$content = vB_Library::instance('node')->getNodeContent($message['nodeid']);
			$messages[$message['nodeid']] = $message + $content[$message['nodeid']];
		}

		$userApi = vB_Api::instanceInternal('user');
		$initial = key($messages);
		$messageIds = array();
		foreach ($messages as $key => $message)
		{
			// @TODO implement fetchAvatars to get all avatars together instead of one by one
			$messages[$key]['senderAvatar'] = $messages[$key]['avatar'];//$userApi->fetchAvatar($message['userid']);
			$messages[$key]['starter'] = false;

			if (empty($message['pagetext']))
			{
				$messages[$key]['pagetext'] = $message['rawtext'];
			}
			$messageIds[] = $message['nodeid'];
		}
		$messages[$initial]['starter'] = true;

		// try to set the first recipient
		$needLast = array();
		if (empty($messages[$initial]['lastauthorid']) OR $messages[$initial]['lastauthorid'] == $userid)
		{
			$needLast[] = $messages[$initial]['nodeid'];
		}

		// @TODO check for a way to implement a generic protected library method to fetch recipients instead of cloning code through methods.
		// fetch the right lastauthor if needed
		if (!empty($needLast))
		{
			$neededUsernames = $this->assertor->assertQuery('vBForum:getPMLastAuthor', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'nodeid' => $needLast, 'userid' => $userid));
			foreach ($neededUsernames AS $user)
			{
				if ($user['nodeid'] == $messages[$initial]['nodeid'])
				{
					$messages[$initial]['lastcontentauthor'] = $user['username'];
					$messages[$initial]['lastauthorid'] = $user['userid'];
				}
			}
		}

		$included = false;
		$recipients = array();
		$recipientsInfo = $this->assertor->assertQuery('vBForum:getPMRecipientsForMessage', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'nodeid' => $messages[$initial]['nodeid']
		));

		foreach ($recipientsInfo as $recipient)
		{
			if (($recipient['userid'] == $userid))
			{
				if (!$included)
				{
					$included = true;
				}

				continue;
			}
			else if ($messages[$initial]['lastcontentauthor'] == $recipient['username'])
			{
				continue;
			}

			if (!isset($recipients[$recipient['userid']]))
			{
				$recipients[$recipient['userid']] = $recipient;
			}
		}

		// and set the first recipient properly if needed
		$firstRecipient = array();
		if (!empty($messages[$initial]['lastcontentauthor']) AND !empty($messages[$initial]['lastauthorid']) AND ($messages[$initial]['lastauthorid'] != $userid))
		{
			$firstRecipient = array(
				'userid' => $messages[$initial]['lastauthorid'],
				'username' => $messages[$initial]['lastcontentauthor']
			);
		}
		else if (!empty($recipients))
		{
			$firstRecipient = reset($recipients);
			unset($recipients[$firstRecipient['userid']]);
		}

		//set these messages read.
		$this->setRead($messageIds, 1, $userid);
		return array('message' => $messages[$initial], 'messages' => $messages, 'otherRecipients' => count($recipients), 'firstRecipient' => $firstRecipient, 'included' => $included);
	}


	/** This lists the folders.
	 *
	 *	@param	mixed	array of system folders to be hidden. like self::MESSAGE_FOLDER
	 *
	 *	@return	mixed	array of folderid => title
	 **/
	public function listFolders($suppress = array())
	{
		$userInfo =  vB::getCurrentSession()->fetch_userinfo();
		$userid = $userInfo['userid'];

		if (!intval($userid))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		$this->checkFolders($userid);
		//You can only suppress system folders
		if (!empty($suppress))
		{
			$folders = $this->folders[$userid]['folders'];
			foreach ($suppress as $titlephrase)
			{
				if (array_key_exists($titlephrase, $this->folders[$userid]['systemfolders']))
				{
					$folderid = $this->folders[$userid]['systemfolders'][$titlephrase];

					if (array_key_exists($folderid, $folders))
					{
						unset($folders[$folderid]);
					}
				}
			}

			return $folders;
		}

		return $this->folders[$userid]['folders'];
	}

	/** This creates a new message folder. It returns false if the record already exists and the id if it is able to create the folder
	 *
	 *	@return	int
	 **/
	public function createMessageFolder($folderName, $userid)
	{
		$cleaner = vB::get_cleaner();
		$foldername = $cleaner->clean($folderName, $vartype = vB_Cleaner::TYPE_NOHTML);


		$this->checkFolders($userid);
		if (!in_array($foldername, $this->folders[$userid]['folders']))
		{
			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT, 'userid' => $userid,	'title' => $foldername);
			//We need for the new folder to be sorted correctly. Easiest to unset, and if needed the folders will be rebuilt.
			unset($this->folders[$userid]);
			return $this->assertor->assertQuery('vBForum:messagefolder', $data);
		}
	}

	/** Moves a node to the trashcan. Wrapper for deleteMessage()
	*
	*
	*	@param	int
	*
	**/
	public function toTrashcan($nodeid)
	{
		$userInfo =  vB::getCurrentSession()->fetch_userinfo();
		$userid = $userInfo['userid'];

		if (!intval($userid))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		$this->checkFolders($userid);
		//If we're already in the trashcan we delete it.
		$existing = $this->assertor->assertQuery('vBForum:sentto', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'nodeid' => $nodeid, 'folderid' => $this->folders[$userid]['systemfolders'][self::TRASH_FOLDER]));

		if ($existing->valid())
		{
			$this->delete($nodeid);
			return;
		}

		$this->moveMessage($nodeid, $this->folders[$userid]['systemfolders'][self::TRASH_FOLDER], $existing);
	}


	/** This summarizes messages for current user
	 *
	 *
	 *	@return	mixed - array-includes folderId, title, quantity not read.
	 **/
	public function fetchSummary($userid)
	{
		$this->checkFolders($userid);
		$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'userid' => $userid);
		$folders = $this->assertor->getRows('vBForum:messageSummary', $params);
		/* Here we need to do some rearranging.  We get the user-created folders first, and the standards ones at the end.
		   But we want:
		   Message
		   <custom>
		   Trash
		   Requests
		   Notification
		*/

		$results = array('customFolders' => ARRAY());
		$totalUnread = 0;

		//messages first
		foreach ($folders as $key => $folder)
		{

			if ($folder['titlephrase'] == self::MESSAGE_FOLDER)
			{
				$phrase = new vB_Phrase('global', $folder['titlephrase']);
				$folder['title'] = (string) $phrase;
				$totalUnread += $folder['qty'];
				$results['messages'] = $folder;
				unset($folders[$key]);
					break;
			}
		}

		// sent items
		foreach ($folders as $key => $folder)
		{
			if ($folder['titlephrase'] == self::SENT_FOLDER)
			{
				$phrase = new vB_Phrase('global', $folder['titlephrase']);
				$folder['title'] = (string) $phrase;
				// never show the count
				$folder['qty'] = 0;
				$results['sent_items'] = $folder;
				unset($folders[$key]);
					break;
			}
		}

		//now custom
		foreach ($folders as $key => $folder)
		{
			//if it's a custom folder it won't have a title phrase.
			if (empty($folder['titlephrase']) )
			{
				$totalUnread += $folder['qty'];
				$results['customFolders'][] = $folder;
				unset($folders[$key]);
			}
		}

		//now trash
		foreach ($folders as $key => $folder)
		{

			if ($folder['titlephrase'] == self::TRASH_FOLDER)
			{
				$phrase = new vB_Phrase('global', $folder['titlephrase']);
				$folder['title'] = (string) $phrase;
				$totalUnread += $folder['qty'];
				$results['trash'] = $folder;
				unset($folders[$key]);
					break;
			}
		}

		//Now requests
		foreach ($folders as $key => $folder)
		{

			if ($folder['titlephrase'] == self::REQUEST_FOLDER)
			{
				$phrase = new vB_Phrase('global', $folder['titlephrase']);
				$folder['title'] = (string) $phrase;
					$totalUnread += $folder['qty'];
				$results['requests'] = $folder;
				unset($folders[$key]);
					break;
			}
		}

		//Notifications
		foreach ($folders as $key => $folder)
			{

			if ($folder['titlephrase'] == self::NOTIFICATION_FOLDER)
			{
				$phrase = new vB_Phrase('global', $folder['titlephrase']);
				$folder['title'] = (string) $phrase;
				$totalUnread += $folder['qty'];
				$results['notifications'] = $folder;
				unset($folders[$key]);
				break;
			}
		}

		// @TODO implement this nicer...
		$phrase = new vB_Phrase('global', 'pending_posts');
		$folder['folderid'] = $this->getPendingPostFolderId();
		$folder['titlephrase'] = 'pending_posts';
		$folder['title'] = (string) $phrase;
		$pendingPosts = vB_Api::instanceInternal('node')->listPendingPostsForCurrentUser(array('totalcount' => true));
		$folder['qty'] = !empty($pendingPosts['pageInfo']['totalcount']) ? $pendingPosts['pageInfo']['totalcount'] : 0;
		$results['pending'] = $folder;
		return array('unread' => $totalUnread, 'folders' => $results);
	}

	/** This lists messages for current user
	 *
	 *	@param	mixed- can pass sort direction, type, page, perpage, or folderid. If sent_items flag is passed includes the firstrecipient info in result array
	 *
	 *	@return	mixed - array-includes folderId, title, quantity not read. Also 'page' is array of node records for page 1.
	 **/
	public function listMessages($data, $userid)
	{
		$this->checkFolders($userid);
		if ( isset($data['folderid']) AND !array_key_exists($data['folderid'], $this->folders[$userid]['folders']))
		{
			throw new vB_Exception_Api('no_permission');

		}
		else if (empty($data['folderid']))
		{
			$folderid = $this->folders[$userid]['systemfolders'][self::MESSAGE_FOLDER];
			$data['folderid'] = $folderid;
		}
		$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD, 'userid' => $userid);
		foreach (array('sortDir', 'page', 'contenttypeid', 'folderid') as $param)
		{
			if (isset($data[$param]))
			{
				$params[$param] = $data[$param];
			}
		}

		if (!empty($data['showdeleted']))
		{
			$params['showdeleted'] = 1;
		}
		else
		{
			$params['showdeleted'] = 0;
		}

		if (isset($data['perpage']) AND intval($data['perpage']))
		{
			$params[vB_dB_Query::PARAM_LIMIT] = intval($data['perpage']);
		}
		else
		{
			$params[vB_dB_Query::PARAM_LIMIT] = 50;
		}

		if (empty($data['pageNum'] ))
		{
			$params[vB_dB_Query::PARAM_LIMITPAGE] = 1;
		}
		else
		{
			$params[vB_dB_Query::PARAM_LIMITPAGE] = intval($data['pageNum']);
		}

		$methodName = 'listPrivateMessages';
		if ($data['folderid'] == $this->folders[$userid]['systemfolders'][self::SENT_FOLDER])
		{
			$methodName = 'listSentMessages';
		}
		$messageQry = $this->assertor->assertQuery('vBForum:' . $methodName, $params);
		//Let's get all the recipients;
		$messages = array();
		$recipientsNeeded = array();
		if ($messageQry AND is_object($messageQry))
		{
			foreach ($messageQry AS $message)
			{
				$message['recipients'] = array();
				$message['included'] = false;
				if (empty($message['lastauthorid']) OR ($message['lastauthorid'] == $userid))
				{
					$recipientsNeeded[$message['nodeid']] = $message['nodeid'];
					$message['lastauthor'] = '';
					$message['lastauthorid'] = 0;
				}

				$messages[$message['nodeid']] = $message;
			}

			if (!empty($messages))
			{
				$userApi = vB_Api::instanceInternal('user');
				if (empty($data['ignoreRecipients']))
				{
					$messageIds = array_keys($messages);
					$recipientsInfo = $this->assertor->assertQuery('vBForum:getPMRecipientsForMessage', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
						'nodeid' => $messageIds
					));

					// @TODO check for a way to implement a generic protected library method to fetch recipients instead of cloning code through methods.
					if (!empty($recipientsNeeded))
					{
						$neededUsernames = $this->assertor->assertQuery('vBForum:getPMLastAuthor', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'nodeid' => $recipientsNeeded, 'userid' => $userid));
						foreach ($neededUsernames AS $username)
						{
							if (isset($messages[$username['nodeid']]))
							{
								$messages[$username['nodeid']]['lastauthor'] = $username['username'];
								$messages[$username['nodeid']]['lastauthorid'] = $username['userid'];
							}
						}
					}
					foreach ($recipientsInfo as $recipient)
					{
						if (isset($messages[$recipient['starter']]))
						{
							if (($recipient['userid'] == $userid))
							{
								if (empty($messages[$recipient['starter']]['included']))
								{
									$messages[$recipient['starter']]['included'] = true;
								}

								continue;
							}
							else if ($messages[$recipient['starter']]['lastauthor'] == $recipient['username'])
							{
								continue;
							}

							if (!isset($messages[$recipient['starter']]['recipients'][$recipient['userid']]))
							{
								$messages[$recipient['starter']]['recipients'][$recipient['userid']] = $recipient;
							}
						}
					}
				}

				foreach ($messages as $key => $message)
				{
					if (empty($data['ignoreRecipients']))
					{
						// set the first recipient
						if (!empty($message['lastauthor']) AND !empty($message['lastauthorid']) AND ($message['lastauthorid'] != $userid))
						{
							$messages[$key]['firstrecipient'] = array(
								'userid' => $message['lastauthorid'],
								'username' => $message['lastauthor']
							);
						}
						else if (!empty($message['recipients']))
						{
							$firstrecip = reset($message['recipients']);
							$messages[$key]['firstrecipient'] = $firstrecip;
							unset($messages[$key]['recipients'][$firstrecip['userid']]);
						}

						$messages[$key]['otherRecipients'] = count($messages[$key]['recipients']);
					}

					$messages[$key]['senderAvatar'] = $userApi->fetchAvatar($message['userid']);
					if (empty($message['previewtext']))
					{
						$messages[$key]['previewtext'] = vB_String::getPreviewText($message['rawtext']);
					}
				}
			}
		}

		return $messages;
	}

	/** This lists notifications for current user
	 *
	 *	@param	mixed- can pass sort direction, type, page, perpage. Showdetail flag is passed as param to display detailed notification [rate] participants infromation or not.
	 *
	 *	@return	mixed - array-includes folderId, title, quantity not read. Also 'page' is array of node records for page 1.
	 **/
	public function listNotifications($data, $userid)
	{
		$this->checkFolders($userid);
		
		$data['folderid'] = $this->folders[$userid]['systemfolders'][self::NOTIFICATION_FOLDER];

		if (!empty($data['about']) AND !in_array($data['about'], $this->notificationTypes))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		$params = array('userid' => $userid);
		foreach (array('sortDir', 'page', 'about', 'folderid') as $param)
		{
			if (!empty($data[$param]))
			{
				$params[$param] = $data[$param];
			}
		}

		if (isset($data['perpage']) AND intval($data['perpage']))
		{
			$params[vB_dB_Query::PARAM_LIMIT]= intval($data['perpage']);
		}
		else
		{
			$params[vB_dB_Query::PARAM_LIMIT]= 50;
		}


		if (empty($data['page']))
		{
			$params[vB_dB_Query::PARAM_LIMITPAGE] = 1;
		}
		else
		{
			$params[vB_dB_Query::PARAM_LIMITPAGE] = $data['page'];
		}

		$notificationQry = $this->assertor->assertQuery('vBForum:listNotifications', $params);
		$notifications = array();
		$discussions = array();
		$rated = array();
		$userApi = vB_Api::instanceInternal('user');
		foreach ($notificationQry AS $notifyRec)
		{
			// The rate notification info is stored in the reputation table
			if ($notifyRec['about'] == 'rate')
			{
				$rated[] = $notifyRec['aboutid'];
				$notifyRec['showdetail'] = (isset($data['showdetail'])) ? intval($data['showdetail']) : 0;
			}

			$notifyRec['otherRecipients'] = 0;
			
			if (!empty($notifyRec['showdetail']))
			{
				$notifyRec['lastcontentavatar'] = $notifyRec['lastauthorid'] ? $userApi->fetchAvatar($notifyRec['lastauthorid']) : $userApi->fetchAvatar($notifyRec['userid']);
			}

			$notifications[] = $notifyRec;
			if (($notifyRec['about'] == 'reply') OR ($notifyRec['about'] == 'comment') OR ($notifyRec['about'] == 'threadcomment'))
			{
				$discussions[] = $notifyRec['aboutid'];
			}
		}
		if (!empty($rated))
		{
			// get list of voters
			$votersQry = $this->assertor->assertQuery('vBForum:reputation',
				array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'nodeid' => $rated,
				),
				array(
					'field' => 'dateline',
					'direction' => vB_dB_Query::SORT_DESC
				)
			);
			$votersList = array();

			foreach($votersQry AS $voter)
			{
				$votersList[$voter['nodeid']][] = $voter['whoadded'];
			}

			foreach ($notifications AS $key => &$notification)
			{
				if (isset($votersList[$notification['aboutid']]))
				{
					if (!empty($notification['showdetail']))
					{
						$notification['otherRecipients'] = count($votersList[$notification['aboutid']]) - 1;
						$notification['lastcontentavatar'] = $userApi->fetchAvatar($votersList[$notification['aboutid']][0]);
						$notification['lastauthor'] = $userApi->fetchUserName($votersList[$notification['aboutid']][0]);
						$notification['lastauthorid'] = $votersList[$notification['aboutid']][0];
					}
					else
					{
						$notification['otherRecipients'] = count($votersList[$notification['aboutid']]);
					}
				}
			}

		}
		if (!empty($discussions))
		{
			// get a list of participants
			$participantQry = $this->assertor->assertQuery('vBForum:getParticipantsList', array('nodeids' => $discussions));
			$participantList = array();
			foreach($participantQry AS $participant)
			{
				$participantList[$participant['parent']][$participant['about']][$participant['userid']] = $participant['username'];
			}

			// get a list of authors for the aboutuser field
			$authorQry = $this->assertor->assertQuery('vBForum:node',
					array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						'nodeid' => $discussions,
						vB_dB_Query::COLUMNS_KEY => array('authorname', 'nodeid'),
					));
			$authorList = array();
			foreach($authorQry AS $author)
			{
				$authorList[$author['nodeid']] = $author['authorname'];
			}

			// set the # of other recipients and the 'aboutuser' field
			foreach ($notifications AS $key => &$notification)
			{
				// only reply, comment and thread_comment are considered for participantList
				if (isset($participantList[$notification['aboutstarterid']][$notification['about']]))
				{
					// need to subtract 1 since first particpant is displayed by name
					$notification['otherRecipients'] = count($participantList[$notification['aboutstarterid']][$notification['about']]) - 1;

					// comments or replies can set the node's lastauthor. We don't want the reply notification to have the commenter's name or vice versa.
					// Since they are distinct, use the username returned from the query
					$participantIDs = array_keys($participantList[$notification['aboutstarterid']][$notification['about']]);
					$participantNames = $participantList[$notification['aboutstarterid']][$notification['about']];
					$notification['lastauthorid'] = $participantIDs[0];
					$notification['lastauthor'] =  $participantNames[$participantIDs[0]];
					$notification['recipients'] = $participantNames;
				}
				$notification['lastcontentavatar'] = $notification['lastauthorid'] ? $userApi->fetchAvatar($notification['lastauthorid']) : $userApi->fetchAvatar($notification['userid']);
				$notification['aboutuser'] = $authorList[$notification['aboutid']];
			}
		}
		
		return $notifications;
	}

	/** This lists messages for current user
	 *
	 *	@param	mixed- can pass sort direction, type, page, perpage, or folderid.
	 *
	 *	@return	mixed - array-includes folderId, title, quantity not read. Also 'page' is array of node records for page 1.
	 **/
	public function listSpecialPrivateMessages($data = array())
	{
		$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD, 'userid' => vB::getCurrentSession()->get('userid'));
		foreach (array('sortDir', vB_dB_Query::PARAM_LIMITSTART, 'contenttypeid','folderid', 'userid') as $param)
		{
			if (isset($data[$param]))
			{
				$params[$param] = $data[$param];
			}
		}


		if (isset($params['perpage']) AND intval($params['perpage']))
		{
			$params[vB_dB_Query::PARAM_LIMIT]= 50;
		}
		else
		{
			$params[vB_dB_Query::PARAM_LIMIT]= 50;
		}

		if (empty($params[vB_dB_Query::PARAM_LIMITSTART] ))
		{
			$params[vB_dB_Query::PARAM_LIMITSTART]= 1;
		}

		$messageQry = $this->assertor->assertQuery('vBForum:listSpecialMessages', $params);

		$messages = array();

		if ($messageQry AND is_object($messageQry))
		{
			foreach ($messageQry AS $message)
			{
				$messages[$message['nodeid']] = $message;
			}

			if (!empty($messages))
			{
				$userApi = vB_Api::instanceInternal('user');

				foreach ($messages as  $key => $message)
				{
					$messages[$key]['senderAvatar'] = $userApi->fetchAvatar($message['userid']);
					if (empty($message['previewtext']))
					{
						$messages[$key]['previewtext'] = vB_String::getPreviewText($message['rawtext']);
					}
				}
			}

		}
		return $messages;

	}

	/** This sends a notification. It's essentially an alias for addMessage.
	 *
	 *	@param	mixed	must include 'sentto', 'contenttypeid', and the necessary data for that contenttype.
	 *
	 *	@return	int		the new nodeid.
	 *
	 **/
	public function addNotification($data)
	{
		$this->addMessage($data, true);
	}

	/** This sends a notification. It's an alias for deleteMessage.
	 *
	 *	@param	int	nodeid of the entry to be deleted.
	 *
	 *	@return	bool	did the deletion succeed?
	 **/
	public function deleteNotification($nodeid)
	{
		return $this->deleteMessage($nodeid);
	}

	/**	Permanently deletes a message
	 *
	 *	@param	int	nodeid of the entry to be deleted.
	 *
	 *
	 *	@return	bool	did the deletion succeed?
	 **/
	public function deleteMessage($nodeid, $userid = false)
	{
		if (!$userid)
		{
			$userid = vB::getCurrentSession()->get('userid');
		}
		$this->checkFolders($userid);

		// if !$canUsePmSystem then only usable folders are notifications, requests, and pending
		$pmquota = vB::getUserContext($userid)->getLimit('pmquota');
		$vboptions = vB::getDatastore($userid)->getValue('options');
		$canUsePmSystem = ($vboptions['enablepms'] AND $pmquota);

		//We set "deleted" for this user's record. If there is nobody still connected we do the actual delete.
		$data = array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'deleted' => 1,
			vB_dB_Query::CONDITIONS_KEY => array(
				array(
					'field' => 'userid',
					'value' => $userid
				),
				array(
					'field' =>'nodeid',
					'value' => $nodeid
				)
		));
		$this->assertor->assertQuery('vBForum:sentto', $data);

		//Is there anyone currently still accessing this message?
		$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'deleted' => 0, 'nodeid' => $nodeid);
		$undeleted = $this->assertor->getRows('vBForum:sentto', $data);
		//If there's no matching records, we get an error message.
		if (empty($undeleted) OR !empty($undeleted['errors']))
		{
			//We set the record to "deleted"
			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				vB_dB_Query::CONDITIONS_KEY => array('nodeid' => $nodeid),
				'deleted' => vB::getRequest()->getTimeNow());
			$this->assertor->assertQuery('vBForum:privatemessage', $data);
		}
	}



	/** This function checks that we have all the folders for the current user, and the set folders are there.
	 *
	 ***/
	public function checkFolders($userid)
	{
		if (!intval($userid))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		if (!empty($this->folders[$userid]))
		{
			//We've already loaded them
			return;
		}

		//make sure this is a valid userid
		$userCheck = $this->assertor->getRows('user', array('userid' => $userid));

		if (empty($userCheck) OR !empty($userCheck['errors']))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		//We need to load the folders.
		$folderResult = $this->assertor->getRows('vBForum:messagefolder', array('userid' => $userid),
			'title');

		//The folders WE put in will have titlephrase, but no title. The ones users put in will have title but
		//no titlephrase.
		$systemFolders = array(self::TRASH_FOLDER, self::MESSAGE_FOLDER, self::SENT_FOLDER, self::NOTIFICATION_FOLDER, self::REQUEST_FOLDER, self::PENDING_FOLDER);
		$systemFolderIds = array();
		$folders = array();
		foreach ($folderResult as $folder)
		{
			if (!empty($folder['titlephrase']))
			{
				$folders[$folder['folderid']] = (string) new vB_Phrase('global', $folder['titlephrase']);
				$systemFolderIds[$folder['titlephrase']] = $folder['folderid'];
				$systemKey = array_search($folder['titlephrase'], $systemFolders);

				if ($systemKey !== false)
				{
					unset($systemFolders[$systemKey]);
				}
			}
			else
			{
				$folders[$folder['folderid']] = $folder['title'];
			}
		}

		//If we don't have the five system folders we need to create them.
		foreach ($systemFolders as $folderNeeded)
		{
			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT, 'userid' => $userid, 'title' => '',
			'titlephrase' => $folderNeeded);
			$folderid = $this->assertor->assertQuery('vBForum:messagefolder', $data);
			$systemFolderIds[$folderNeeded] = $folderid;
			$folders[$folderid] = (string) new vB_Phrase('global', $folderNeeded);
		}

		//We have it all now.
		$this->folders[$userid] = array('folders' => $folders, 'systemfolders' => $systemFolderIds);
	}

	/**
	 * Verifies that the request exists and its valid.
	 * Returns the message if no error is found.
	 * Throws vB_Exception_Api if an error is found.
	 * @param int $userid
	 * @param int $nodeid
	 * @return array - message info
	 */
	public function validateRequest($userid, $nodeid)
	{
		$sentto = $this->assertor->getRow('vBForum:sentto', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'nodeid' => $nodeid, 'userid' => $userid));

		if (!$sentto OR !empty($sentto['errors']))
		{
			throw new vB_Exception_Api('invalid_data');

		}

		//So the node is valid and the message was sent to this user. Let's confirm it was a request.
		$message = $this->assertor->getRow('vBForum:privatemessage', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'nodeid' => $nodeid));

		if (!$sentto OR !empty($sentto['errors']) OR ($message['msgtype'] != 'request'))
		{
			throw new vB_Exception_Api('invalid_data');

		}

		return $message;
	}

	/** This function denies a user follow request
	*
	*	@param	int		the nodeid of the request
	*	@param	int		(optional) the userid to whom the request was sent
	*	@return	mixed - boolean true OR string phrasename if popup dialogue is required
	*
	**/
	public function denyRequest($nodeid, $cancelRequestFor)
	{
		/* We do the following
		   - validate the record
		   - call the following api to change the data.
		   - delete the message records
		*/

		$userInfo =  vB::getCurrentSession()->fetch_userinfo();

		if (intval($cancelRequestFor) AND ($cancelRequestFor > 0))
		{
			$userid = $cancelRequestFor;
		}
		else
		{
			$userid = $userInfo['userid'];
		}
		
		if (!intval($userInfo['userid']))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		$message = $this->validateRequest($userid, $nodeid);

		// if it's a channel request, result will stay true and no pop-up alert will be displayed
		$result = true;
		if (!in_array($message['about'], $this->channelRequests))
		{
			// it's a following request
			$about = $message['about'];
			$message = vB_Library::instance('node')->getNodeBare($nodeid);

			// @TODO we might want to distinguish between follow members and content here...
			// which implicates adding new approve/deny methods and might need notification message as well.
			$vboptions = vB::getDatastore()->getValue('options');
			if (!$vboptions['ignoremods'])
			{
				$channels = vB::getUserContext($message['userid'])->getCanModerate();
				if (!empty($channels['can']))
				{
					$result = 'follow_request_denied';
				}
			}

			if ($result === true)
			{
				$followApi = vB_Api::instanceInternal('follow');
				$followApi->denyFollowing($message['userid']);
				$result = 'follow_request_denied_ignored';
			}
		}

		//Now we should delete the records from the node, privatemessage, text, and sentto tables.
		$this->assertor->assertQuery('vBForum:node', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'nodeid' => $nodeid));
		$this->assertor->assertQuery('vBForum:privatemessage', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'nodeid' => $nodeid));
		$this->assertor->assertQuery('vBForum:sentto', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'nodeid' => $nodeid));
		$this->assertor->assertQuery('vBForum:text', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'nodeid' => $nodeid));

		return $result;
	}


	/** This function accepts a user follow request or a channel ownership/moderation/membership request
	 *
	 *	@param	int		the nodeid of the request
	 *
	 *	@return	bool
	 *
	 **/
	public function acceptRequest($nodeid)
	{
		/* We do the following
		   - validate the record
		   - call the following api to change the data.
		   - delete the message records
		*/
		$userInfo =  vB::getCurrentSession()->fetch_userinfo();
		$userid = $userInfo['userid'];

		if (!intval($userid))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		$message = $this->validateRequest($userid, $nodeid);

		$resultphrase = '';
		//if this is one of the ownership/membership requests, send it to the node api.
		if (in_array($message['about'], $this->channelRequests))
		{
			$result = $this->nodeApi->approveChannelRequest($nodeid);

			if ($result === true)
			{
				switch ($message['about'])
				{
					case vB_Api_Node::REQUEST_TAKE_OWNER:
					case vB_Api_Node::REQUEST_SG_TAKE_OWNER:
						$resultphrase = 'take_owner_request_accepted';
						break;
					case vB_Api_Node::REQUEST_TAKE_MODERATOR:
					case vB_Api_Node::REQUEST_SG_TAKE_MODERATOR:
						$resultphrase = 'take_moderator_request_accepted';
						break;
					case vB_Api_Node::REQUEST_TAKE_MEMBER:
					case vB_Api_Node::REQUEST_SG_TAKE_MEMBER:
						$resultphrase = 'take_member_request_accepted';
						break;
					case vB_Api_Node::REQUEST_GRANT_OWNER:
					case vB_Api_Node::REQUEST_SG_GRANT_OWNER:
						$resultphrase = 'grant_owner_request_accepted';
						break;
					case vB_Api_Node::REQUEST_GRANT_MODERATOR:
					case vB_Api_Node::REQUEST_SG_GRANT_MODERATOR:
						$resultphrase = 'grant_moderator_request_accepted';
						break;
					case vB_Api_Node::REQUEST_GRANT_MEMBER:
					case vB_Api_Node::REQUEST_SG_GRANT_MEMBER:
						$resultphrase = 'grant_member_request_accepted';
						break;
				}
			}
		}
		else
		{
			// it's a following request
			$aboutid = $message['aboutid'];
			$about = $message['about'];
			$message = vB_Library::instance('node')->getNodeBare($nodeid);

			$followApi = vB_Api::instanceInternal('follow');
			// @TODO we might want to distinguish between follow members and content here...
			// which implicates adding new approve/deny methods and might need notification message as well.
			if (in_array($about, array(vB_Api_Node::REQUEST_GRANT_SUBSCRIBER, vB_Api_Node::REQUEST_SG_GRANT_SUBSCRIBER)))
			{
				$followApi->add($aboutid, vB_Api_Follow::FOLLOWTYPE_CONTENT, $message['userid']);
			}
			else
			{
				$followApi->approveFollowing($message['userid']);
			}

			$notifications = array(array('about' => self::NOTIFICATION_TYPE_FOLLOW,
			'aboutid' => $userid, 'userid' => $message['userid']));
			$this->sendNotifications($notifications);

			$resultphrase = 'follow_request_accepted';
			$result = true;
		}

		if ($result)
		{
			//Now we should delete the records from the node, privatemessage, text, and sentto tables.
			$this->assertor->assertQuery('vBForum:node', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'nodeid' => $nodeid));
			$this->assertor->assertQuery('vBForum:privatemessage', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'nodeid' => $nodeid));
			$this->assertor->assertQuery('vBForum:sentto', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'nodeid' => $nodeid));
			$this->assertor->assertQuery('vBForum:text', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'nodeid' => $nodeid));
			$this->nodeApi->clearCacheEvents($nodeid);
			return vB_Phrase::fetchPhrase('global', $resultphrase);
		}
		else
		{
			return false;
		}
	}

	/** Clears the cached folder information
	*
	**/
	public function resetFolders()
	{
		$this->folders = array();
	}

	/** returns the cached folder information
	 *
	 **/
	public function fetchFolders($userid)
	{
		return $this->folders[$userid];
	}

	/** This sets a message to read
	 *
	 *	@param $nodeid
	 *
	 **/
	public function setRead($nodeid, $read, $userid)
	{
		if (is_string($nodeid) AND strpos($nodeid, ','))
		{
			$nodeid = explode(',', $nodeid);
		}

		$node = $this->assertor->getRow('vBForum:sentto', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'nodeid' => $nodeid, 'userid' => $userid));

		//We can only change if we have a valid node.
		if (empty($node) OR !empty($node['errors']))
		{
			return;
		}

		$this->assertor->assertQuery('vBForum:sentto', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			vB_dB_Query::CONDITIONS_KEY => array(array('field' => 'nodeid', 'value' => $nodeid), array('field' => 'userid', 'value' => $userid)),
			'msgread' => $read));
	}


	/** returns the cached folder information
	 *
	 **/
	public function unsetFolders($userid)
	{
		unset($this->folders[$userid]);
	}

	public function validate($data, $action = vB_Api_Content::ACTION_ADD, $nodeid = false, $nodes = false)
	{
		if (vB::getUserContext()->isSuperAdmin())
		{
			return true;
		}
		$userInfo = vB::getCurrentSession()->fetch_userinfo();
		$currentUser = $userInfo['userid'];

		if (!intval($currentUser))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		//we need a nodeid (or parentid if we are adding) or we cannot answer the question.
		if ($action == vB_Api_Content::ACTION_ADD)
		{
			if (empty($data['parentid']) OR !intval($data['parentid']))
			{
				$data['parentid'] = $this->pmChannel;
			}
			$parentid = $data['parentid'];
		}
		else
		{
			if (!$nodeid)
			{
				if (empty($data['nodeid']) OR !intval($data['nodeid']))
				{
					throw new vB_Exception_Api('invalid_data');
				}
				else
				{
					$nodeid = $data['nodeid'];
			}
			}

			if (!is_array($nodeid))
			{
				$nodeid = array($nodeid);
			}

			$nodes = vB_Api::instanceInternal('node')->getNodes($nodeid);

			}

		switch ($action) {
			case  vB_Api_Content::ACTION_ADD:
				//parentid must be pmChannel or a descendant.
				if ($parentid != $this->pmChannel)
				{
					$closure = vB_Library::instance('node') -> fetchClosureParent($parentid, $this->$this->pmChannel);

					if (!$closure OR !is_array($closure) OR empty($closure) OR empty($closure[$parentid]))
					{
						throw new vB_Exception_Api('invalid_data');
					}
				}

				return vB::getUserContext()->getChannelPermission('createpermissions', $this->contenttype, $parentid);
				break;

			case  vB_Api_Content::ACTION_UPDATE:
				//They can only update if they are a moderator with permission to moderate messages.
				// As a moderator
				foreach ($nodes  as $node)
				{
					if (!vB::getUserContext()->getChannelPermission('moderatorpermissions', 'caneditposts', $node['nodeid'], false, $node['parentid']))
					{
						return false;
					}

				}
					return true;
				break;

			case  vB_Api_Content::ACTION_VIEW:
				//Maybe we already have a record.
				if (!isset($this->canSee[$currentUser]))
				{
					$this->canSee[$currentUser] = array();
				}

				$canSeeQry = $this->assertor->assertQuery('vBForum:sentto', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'nodeid' => $nodeid, 'userid' => $currentUser));

				//We scan the $canSeeQuery list. If there's a match then they can view this node.
				foreach ($canSeeQry  as $sentto)
				{
					foreach ($nodes as $key => $node)
				{
						if ($node['nodeid'] == $sentto['nodeid'])
						{
							unset($nodes[$key]);
							if (count($nodes) == 0)
							{
					return true;
				}
							break;
				}
					}
				}

				//if we got here we have some unmatched nodes. That means no view permission
				return false;
				break;

			case  vB_Api_Content::ACTION_DELETE:
				foreach ($nodes  as $node)
				{
					if (!vB::getUserContext()->getChannelPermission('moderatorpermissions', 'canremoveposts', $node['nodeid'], false, $node['parentid']))
					{
					return false;
				}
				}
				return true;

				break;

			case vB_Api_Content::ACTION_APPROVE:

				return true;
				break;

			case vB_Api_Content::ACTION_PUBLISH:

				return true;
				break;
			default:
			;
		} // switch

		return false;
	}

	/*** Checks nodes to ensure that the current user can see them.
	 *	@param	mixed	integer	or array of integers for id in the primary table
	 *	@return	int
	 ***/
	public function checkCanSee($nodeids)
	{
		//We need to limit to only the records the current user can see.
		$userInfo = vB::getCurrentSession()->fetch_userinfo();
		$currentUser = $userInfo['userid'];

		if (!intval($currentUser))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		if (!isset($this->canSee[$currentUser]))
		{
			$this->canSee[$currentUser] = array();
		}

		if (!is_array($nodeids))
		{
			$nodeids = array($nodeids);
		}

		$canSeeQry = $this->assertor->assertQuery('vBForum:sentto', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'nodeid' => $nodeids, 'userid' => $currentUser));
		foreach ($canSeeQry as $canSee)
		{
			$this->canSee[$currentUser][$canSee['nodeid']] = 1;
		}

		foreach ($nodeids as $key => $id)
		{
			if (!array_key_exists($id, $this->canSee[$currentUser]))
			{
				unset($nodeids[$key]);
			}
		}

		if (empty($nodeids))
		{
			return array();
		}
		return $nodeids;
	}


	/*** Returns the node content as an associative array
	 *	@param	mixed	array of content records

	 *	@return	mixed	array of content records with extra "about" information for notifications.
	 ***/
	public function addMessageInfo($nodes)
	{
		if (empty($nodes))
		{
			return $nodes;
		}
		foreach ($nodes as $nodeid => $node)
		{
			$nodes[$nodeid]['recipients'] = array();
		}

		$userInfo = vB::getCurrentSession()->fetch_userinfo();
		$currentUser = $userInfo['userid'];
		$recipients = $this->assertor->assertQuery('vBForum:sentto', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'nodeid' => array_keys($nodes)));
		//lets only call fetchAvatar once per user. We'll probably have overlaps
		$avatars = array();
		$userApi = vB_Api::instanceInternal('user');
		foreach ($recipients as $key => $recipient)
		{
			if ($recipient['userid'] != $currentUser)
			{
				if (!isset($avatars[$recipient['userid']]))
				{
					$avatars[$recipient['userid']] = $userApi->fetchAvatar($recipient['userid']);
				}
				$nodes[$recipient['nodeid']]['recipients'][$recipient['userid']] = $recipient;
				$nodes[$recipient['nodeid']]['recipients'][$recipient['userid']]['avatarUrl'] = $avatars[$recipient['userid']];
			}
		}

		$notifications = array();

		foreach ($nodes as $key => $node)
		{
			if (($node['msgtype'] == 'notification') AND ($node['about'] != 'vm'))
			{
				$notifications[] = $node['aboutid'];
			}
		}
		if (empty($notifications))
		{
			return $nodes;
		}

		$notifQry = vB::getDbAssertor()->assertQuery('vBForum:privatemessage',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'nodeid' => $notifications));
		foreach ($notifQry as $notification)
		{
			$nodeid = $notification['nodeid'];
			$nodes[$nodeid]['aboutcount'] = max(0, $notification['textcount'] - 1);
			$nodes[$nodeid]['lastauthor'] = $notification['lastcontentauthor'];
			$nodes[$nodeid]['aboutuser'] = $notification['username'];
			$nodes[$nodeid]['abouttitle'] = $notification['title'];
			$nodes[$nodeid]['aboutrouteid'] = $notification['routeid'];
		}
		return $nodes;
	}

	/*** Returns the node content as an associative array
	 *	@param	integer	The id in the primary table
	 *	@param array permissions
	 *	@param bool	appends to the content the channel routeid and title, and starter route and title the as an associative array

	 *	@return	int
	 ***/
	public function getFullContent($nodeid, $permissions = false)
	{
		$results = parent::getFullContent($nodeid, $permissions);
		return $this->addMessageInfo($results);
	}

	/**
	 * Get the pending posts folder id
	 *
	 * @return	int		The pending posts folder id from messagefolder.
	 */
	public function getPendingPostFolderId()
	{
		$folderInfo = $this->assertor->assertQuery('vBForum:messagefolder', array('titlephrase' => self::PENDING_FOLDER, 'userid' => vB::getUserContext()->fetchUserId()));
		if ($folderInfo AND !$folderInfo->valid())
		{
			return false;
		}

		$folder = $folderInfo->current();
		return intval($folder['folderid']);
	}

	/** Returns array of Request message types
	 *
	 *	@return		mixed		array of strings
	 ***/
	public function getChannelRequestTypes()
	{
		return $this->channelRequests;
	}

	/** Return all the valid notification types
	 *
	 *	@return		mixed		array of strings
	 * */
	public function fetchNotificationTypes()
	{return $this->notificationTypes;
	}

	/**
	 * Move a message back to user inbox folder
	 *
	 * @params		int		The nodeid we are undeleting.
	 * @params		mixed	array of existing sent to records.
	 *
	 * @return		bool	True if succesfully done.
	 */
	public function undeleteMessage($nodeid, $existing)
	{
		$userid = vB::getCurrentSession()->get('userid');
		$this->checkFolders($userid);
		return $this->moveUndeleted($nodeid, $this->folders[$userid]['systemfolders'][self::MESSAGE_FOLDER], $existing);
	}

	/**
	 * Delete messages
	 *
	 * @params		array	Array of the nodeids from messages to delete.
	 *
	 * @return		bool	Indicating if deletion were succesfully done or will throw exception.
	 */
	public function deleteMessages($nodeids)
	{
		$nodes = vB_Library::instance('node')->getNodes($nodeids);
		$toDelete = array();
		foreach ($nodes AS $node)
		{
			$toDelete[] = $node['nodeid'];
		}

		$userid = vB::getCurrentSession()->get('userid');
		$this->assertor->assertQuery('vBForum:sentto', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'userid' => $userid,
			'nodeid' => $toDelete
		));

		$this->buildPmTotals(array($userid));

		return true;
	}

	/** Move messages to inbox marking them as undeleted.
	 *
	 *	@param	int		the node to be moved
	 *	@param	int		the new parent node.
	 *	@param	int		existing sent to records.
	 *
	 *	@return	bool	did it succeed?
	 *
	 **/
	protected function moveUndeleted($nodeid, $newFolderid, $existing)
	{
		//A user can only move, obviously, to one of their own folders
		$currentUser = vB::getCurrentSession()->get('userid');
		$this->checkFolders($currentUser);

		if (array_key_exists($newFolderid , $this->folders[$currentUser]['folders']))
		{
			foreach ($existing as $nodeRecord)
			{
				$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE, 'folderid' => $newFolderid, 'deleted' => 0,
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'userid', 'value' => $currentUser),
					array('field' => 'nodeid', 'value' => $nodeRecord['nodeid']),
					array('field' => 'folderid', 'value' => $this->folders[$currentUser]['systemfolders'][self::SENT_FOLDER], vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_NE)
				));
				$result = $this->assertor->assertQuery('vBForum:sentto', $data);

				// and restore sent item record
				$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE, 'deleted' => 0,
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'userid', 'value' => $currentUser),
					array('field' => 'nodeid', 'value' => $nodeRecord['nodeid']),
					array('field' => 'folderid', 'value' => $this->folders[$currentUser]['systemfolders'][self::SENT_FOLDER])
				));
				$result = $this->assertor->assertQuery('vBForum:sentto', $data);
			}

			return true;
		}
		else
		{
			throw new vB_Exception_Api('invalid_data');
		}
	}

	/**
	 * Gets the folder information from a given folderid. The folderid requested should belong to the user who is requesting.
	 *
	 * @param	int		The folderid to fetch information for.
	 *
	 * @return	array	The folder information such as folder title, titlephrase and if is custom folder.
	 *
	 */
	public function getFolderFromId($folderid, $userid)
	{
		if (!empty($this->foldersInfo[$userid][$folderid]))
		{
			return $this->foldersInfo[$userid][$folderid];
		}

		$folderInfo = $this->assertor->assertQuery('vBForum:getFolderInfoFromId', array('folderid' => $folderid));

		$folder = array();
		while ($folderInfo AND $folderInfo->valid())
		{
			$info = $folderInfo->current();
			$folder[$info['folderid']] = $info;
			$folderInfo->next();
		}

		$this->foldersInfo[$userid][$folderid] = $folder;
		return $this->foldersInfo[$userid][$folderid];
	}

	/* Calculates the number of private messages that a user has in the system
	 * Used to limit pm abilities based on overage of this count
	 *
	 * @param array/int	List of users to rebuild user.pmtotal for
	 *
	 */
	public function buildPmTotals($userids)
	{
		$this->assertor->assertQuery('vBForum:buildPmTotals', array(vB_dB_Query::TYPE_KEY=> vB_dB_Query::QUERY_METHOD, 'userid' => $userids));
	}

	/*	Checks if userid's notification options for notification type
	 *
	 *	@return boolean		true if user should receive the notificationType
	 */
	public function userReceivesNotification($userid, $notificationType)
	{
		// grab bitfield masks for notification options
		$bf_masks = vB::getDatastore()->getValue('bf_misc_usernotificationoptions');

		// each notification type should have an option linked to it.
		// note that NOTIFICATION_TYPE_MODERATE should not exist anymore. Mod messages go to
		// the pending posts folder
		$notificationOptionBitfields = array(
				// votes in your poll
			self::NOTIFICATION_TYPE_VOTE => $bf_masks['general_voteconvs'],

				// votes in a poll that was a reply to your topic.
				// currently there is no way to reply with a poll, however
			self::NOTIFICATION_TYPE_VOTEREPLY => $bf_masks['general_votereplies'],

				// 'Likes' your post
				// NEEDS A BITFIELD/OPTION
			self::NOTIFICATION_TYPE_RATE => $bf_masks['general_likespost'],

				// replies to your topic
			self::NOTIFICATION_TYPE_REPLY => $bf_masks['discussions_on'],

				// comments on your post
			self::NOTIFICATION_TYPE_COMMENT => $bf_masks['discussion_comment'],

				// comments on a reply to your topic.
				// could possibly use the same one as reply "discussions_on"
				// but would be good to have its own option
			self::NOTIFICATION_TYPE_THREADCOMMENT => $bf_masks['discussions_on'],

				// someone accepts your follow request
			self::NOTIFICATION_TYPE_FOLLOW => $bf_masks['general_followrequest'],

				// when someone follows you & you allowed auto-accept subscribers
			self::NOTIFICATION_TYPE_FOLLOWING => $bf_masks['general_followsyou'],

				// when someone leaves a visitor message on your profile/wall
			self::NOTIFICATION_TYPE_VM => $bf_masks['general_vm']
		);

		// grab user info
		$userInfo = vB_User::fetchUserinfo($userid);

		// if it has an option, check it. Notifications without an option are sent by default
		if (array_key_exists($notificationType, $notificationOptionBitfields))
		{
			// check options
			return (($userInfo['notification_options'] & $notificationOptionBitfields[$notificationType]) == true);
		}
		else
		{
			// no option for this notification type, send notification
			return true;
		}
	}
}

