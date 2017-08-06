<?php

if (!defined('VB_ENTRY'))
	die('Access denied.');
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

/**
 * vB_Api_Content_Privatemessage
 *
 * @package vBApi
 * @author ebrown
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Api_Content_Privatemessage extends vB_Api_Content_Text
{

	//override in client- the text name
	protected $contenttype = 'vBForum_PrivateMessage';
	//The table for the type-specific data.
	protected $tablename = array('text', 'privatemessage');
	protected $folders = array();
	protected $nodeApi;
	protected $assertor;
	protected $pmChannel;
	//Cache our knowledge of records the current user can see, to streamline permission checking.
	protected $canSee = array();
	//these are the notification message types. Message and request are handled differently.
	//the parameter is whether they need an aboutid.
	protected $notificationTypes = array();
	protected $bbcodeOptions = array();

	const PARTICIPANTS_PM = 'PrivateMessage';
	const PARTICIPANTS_POLL = 'Poll';

	protected function __construct()
	{
		parent::__construct();
		$this->library = vB_Library::instance('Content_Privatemessage');
		$userInfo = vB::getCurrentSession()->fetch_userinfo();
		if ($userInfo['userid'] > 0)
		{
			$this->library->checkFolders($userInfo['userid']);
			$this->pmChannel = $this->nodeApi->fetchPMChannel();
			$this->notificationTypes = $this->library->fetchNotificationTypes();
		}
	}

	/* Private messaging can be disabled either by pmquota or enablepms
	 *
	 * @return bool
	 */

	public function canUsePmSystem()
	{
		$pmquota = vB::getUserContext()->getLimit('pmquota');
		$vboptions = vB::getDatastore()->getValue('options');
		$userid = intval(vB::getCurrentSession()->get('userid'));

		if (!$userid OR !$pmquota OR !$vboptions['enablepms'])
		{
			return false;
		}
		return true;
	}

	/** This adds a new message
	 *
	 * 	@param	mixed	must include 'sentto', 'contenttypeid', and the necessary data for that contenttype.
	 *  @param	array	Array of options for the content being created.
	 * 					Available options include:
	 *
	 * 	@return	int		the new nodeid.
	 *
	 * */
	public function add($data, $options = array())
	{
		$vboptions = vB::getDatastore()->getValue('options');
		if (!empty($data['title']))
		{
			$strlen = vB_String::vbStrlen(trim($data['title']), true);
			if ($strlen > $vboptions['titlemaxchars'])
			{
				throw new vB_Exception_Api('maxchars_exceeded_x_title_y', array($vboptions['titlemaxchars'], $strlen));
			}
		}

		//If this is a response, we have a "respondto" = nodeid
		//If it's a forward, we set "forward" = nodeid
		$userInfo = vB::getCurrentSession()->fetch_userinfo();
		$sender = intval($userInfo['userid']);

		if (!intval($sender) OR !$this->canUsePmSystem())
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		if (!$userInfo['receivepm'])
		{
			throw new vB_Exception_Api('pm_turnedoff');
		}

		$pmquota = vB::getUserContext()->getLimit('pmquota');
		if ($userInfo['pmtotal'] >= $pmquota)
		{
			throw new vB_Exception_Api('yourpmquotaexceeded', array($pmquota, $userInfo['pmtotal']));
		}

		$data['sender'] = $sender;
		$recipientNames = 0;
		//check if the user from the usergroup can send the pm to the number of recipients
		$pmsendmax = vB::getUserContext()->getLimit('pmsendmax');
		if (!empty($data['msgRecipients']))
		{
			$recipientNames = count(explode(',', $data['msgRecipients']));
		}
		else if (!empty($data['sentto']))
		{
			$recipientNames = count($data['sentto']);
		}
		if ($pmsendmax > 0 AND $recipientNames > $pmsendmax)
		{
			throw new vB_Exception_Api('pmtoomanyrecipients', array($recipientNames, $pmsendmax));
		}

		if (!empty($data['pagetext']))
		{
			$strlen = vB_String::vbStrlen($this->library->parseAndStrip($data['pagetext']), true);
			if ($strlen < $vboptions['postminchars'])
			{
				throw new vB_Exception_Api('please_enter_message_x_chars', $vboptions['postminchars']);
			}
			if ($vboptions['postmaxchars'] != 0 AND $strlen > $vboptions['postmaxchars'])
			{
				throw new vB_Exception_Api('maxchars_exceeded_x_y', array($vboptions['postmaxchars'], $strlen));
			}
		}
		else if (!empty($data['rawtext']))
		{
			$strlen = vB_String::vbStrlen($this->library->parseAndStrip($data['rawtext']), true);
			if ($strlen < $vboptions['postminchars'])
			{
				throw new vB_Exception_Api('please_enter_message_x_chars', $vboptions['postminchars']);
			}
			if ($vboptions['postmaxchars'] != 0 AND $strlen > $vboptions['postmaxchars'])
			{
				throw new vB_Exception_Api('maxchars_exceeded_x_y', array($vboptions['postmaxchars'], $strlen));
			}
		}
		else
		{
			throw new vB_Exception_Api('invalid_data');
		}

		if (empty($data['parentid']))
		{
			$data['parentid'] = $this->pmChannel;
		}

		if (!$this->validate($data, vB_Api_Content::ACTION_ADD))
		{
			throw new vB_Exception_Api('no_create_permissions');
		}
		$this->cleanInput($data);
		$this->cleanOptions($options);
		//If this is a response, we have a "respondto" = nodeid
		return $this->library->add($data, $options);
	}

	/** 	Permanently deletes a message
	 *
	 * 	@param	int	nodeid of the entry to be deleted.
	 *
	 * 	@return	bool	did the deletion succeed?
	 * */
	public function deleteMessage($nodeid)
	{
		//We need a copy of the existing node.
		$content = $this->nodeApi->getNode($nodeid);

		if (empty($content) OR !empty($content['error']))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		$userInfo = vB::getCurrentSession()->fetch_userinfo();
		$currentUser = $userInfo['userid'];

		if (!intval($currentUser))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		return $this->library->deleteMessage($nodeid, $currentUser);
	}

	/*	 * * Permanently deletes a node
	 * 	@param	integer	The nodeid of the record to be deleted
	 *
	 * 	@return	boolean
	 * * */

	public function delete($nodeid)
	{
		return $this->library->delete($nodeid);
	}

	/** Moves a message to a different folder
	 *
	 * 	@param	int		the node to be moved
	 * 	@param	int		the new parent node.
	 *
	 * 	@return	bool	did it succeed?
	 *
	 * */
	public function moveMessage($nodeid, $newFolderid = false)
	{
		if (!$this->canUsePmSystem())
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		$userInfo = vB::getCurrentSession()->fetch_userinfo();
		$currentUser = $userInfo['userid'];

		if (!intval($currentUser))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		$nodeids = explode(',', $nodeid);

		if (!is_array($nodeids))
		{
			$nodeids = array($nodeids);
		}

		// if it's not message we can't move
		$pmRec = $this->assertor->getRows('vBForum:privatemessage', array(
			'nodeid' => $nodeids
			));

		foreach ($pmRec AS $node)
		{
			if ($node['msgtype'] != 'message')
			{
				throw new vB_Exception_Api('no_move_permission_x', array($node['nodeid']));
			}
		}

		//we can only move a record to which the user has access.
		$this->library->checkFolders($currentUser);
		$folders = $this->library->fetchFolders($currentUser);
		$sentFolder = $folders['systemfolders'][vB_Library_Content_Privatemessage::SENT_FOLDER];

		if (
			in_array($newFolderid, $folders['systemfolders'])
			AND !in_array($newFolderid, array(
				$folders['systemfolders'][vB_Library_Content_Privatemessage::MESSAGE_FOLDER],
				$folders['systemfolders'][vB_Library_Content_Privatemessage::TRASH_FOLDER]
			))
		)
		{
			throw new vB_Exception_Api('invalid_move_folder');
		}
		$conditions = array(
			array('field' => 'userid', 'value' => $currentUser),
			array('field' => 'nodeid', 'value' => $nodeids)
		);
		// allow deleting sent items
		if ($newFolderid != $folders['systemfolders'][vB_Library_Content_Privatemessage::TRASH_FOLDER])
		{
			$conditions[] = array('field' => 'folderid', 'value' => $sentFolder, vB_dB_Query::OPERATOR_KEY => vB_dB_Query::OPERATOR_NE);
		}

		$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, vB_dB_Query::CONDITIONS_KEY => $conditions);
		$existing = $this->assertor->getRows('vBForum:sentto', $data);

		if (empty($existing) OR !empty($existing['errors']))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		return $this->library->moveMessage($nodeid, $newFolderid, $existing);
	}

	/** Get a message
	 *
	 * 	@param	int		the nodeid
	 *
	 * 	@return	mixed	array of data
	 *
	 * */
	public function getMessage($nodeid)
	{
		$content = $this->nodeApi->getNode($nodeid);

		if (!$this->canUsePmSystem())
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		$userid = vB::getCurrentSession()->get('userid');

		//if this is the author we can return the value
		if ($content['userid'] == $userid)
		{
			return $this->library->getMessage($nodeid);
		}

		//Maybe this is a recipient.
		$recipients = $this->assertor->getRows('vBForum:sentto', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'nodeid' => $nodeid));
		foreach ($recipients as $recipient)
		{
			if ($recipient['userid'] == $userid)
			{
				return $this->library->getMessage($nodeid);
			}
		}

		//If we got here, this user isn't authorized to see this record. Well, it's also possible this may not exist.
		throw new vB_Exception_Api('no_permission');
	}

	/**
	 * Get a single request
	 *
	 * @param	int		the nodeid
	 *
	 * @return	array The node data array for the request
	 *
	 * */
	public function getRequest($nodeid)
	{
		if (!$this->canUsePmSystem())
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		$userid = vB::getCurrentSession()->get('userid');

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

	/** This lists the folders.
	 *
	 * 	@param	mixed	array of system folders to be hidden. like vB_Library_Content_Privatemessage::MESSAGE_FOLDER
	 *
	 * 	@return	mixed	array of folderid => title
	 * */
	public function listFolders($suppress = array())
	{
		return $this->library->listFolders($suppress);
	}

	/** This creates a new message folder. It returns false if the record already exists and the id if it is able to create the folder
	 *
	 * 	@return	int
	 * */
	public function createMessageFolder($folderName)
	{
		if (!$this->canUsePmSystem())
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		$userid = vB::getCurrentSession()->get('userid');

		return $folders = $this->library->createMessageFolder($folderName, $userid);
	}

	/** Moves a node to the trashcan. Wrapper for deleteMessage()
	 *
	 *
	 * 	@param	int
	 *
	 * */
	public function toTrashcan($nodeid)
	{
		if (!$this->canUsePmSystem())
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		$userid = vB::getCurrentSession()->get('userid');

		$this->library->checkFolders($userid);
		$folders = $this->library->fetchFolders($userid);
		return $this->moveMessage($nodeid, $folders['systemfolders'][vB_Library_Content_Privatemessage::TRASH_FOLDER]);
	}

	/** This summarizes messages for current user
	 *
	 *
	 * 	@return	mixed - array-includes folderId, title, quantity not read.
	 * */
	public function fetchSummary()
	{
		$userid = vB::getCurrentSession()->get('userid');

		if (!intval($userid))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		return $this->library->fetchSummary($userid);
	}

	/** This lists messages for current user
	 *
	 * 	@param	mixed- can pass sort direction, type, page, perpage, or folderid.
	 *
	 * 	@return	mixed - array-includes folderId, title, quantity not read. Also 'page' is array of node records for page 1.
	 * */
	public function listMessages($data = array())
	{
		if (!$this->canUsePmSystem())
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		$userid = vB::getCurrentSession()->get('userid');

		return $this->library->listMessages($data, $userid);
	}

	/** This lists notifications for current user
	 *
	 * 	@param	mixed- can pass sort direction, type, page, perpage
	 *
	 * 	@return	mixed - array-includes folderId, title, quantity not read. Also 'page' is array of node records for page 1.
	 * */
	public function listNotifications($data = array())
	{
		$userid = vB::getCurrentSession()->get('userid');

		if (!intval($userid))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		
		$data['showdetail'] = vB::getUserContext()->hasPermission('genericpermissions', 'canseewholiked');
		
		return $this->library->listNotifications($data, $userid);

	}

	/** This lists messages for current user
	 *
	 * 	@param	mixed- can pass sort direction, type, page, perpage, or folderid.
	 *
	 * 	@return	mixed - array-includes folderId, title, quantity not read. Also 'page' is array of node records for page 1.
	 * */
	protected function listSpecialPrivateMessages($data = array())
	{
		$userid = vB::getCurrentSession()->get('userid');
		if (!intval($userid))
		{
			return false;
		}

		return $this->library->listSpecialPrivateMessages($data);
	}

	/** This lists messages for current user
	 *
	 * 	@param	mixed- can pass sort direction, type, page, perpage, or folderid.
	 *
	 * 	@return	mixed - array-includes folderId, title, quantity not read. Also 'page' is array of node records for page 1.
	 * */
	public function listRequests($data = array())
	{
		$userid = vB::getCurrentSession()->get('userid');

		if (!intval($userid))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		$this->library->checkFolders($userid);

		$folders = $this->library->fetchFolders($userid);
		$data['folderid'] = $folders['systemfolders'][vB_Library_Content_Privatemessage::REQUEST_FOLDER];
		$data['userid'] = $userid;

		$requests = $this->listSpecialPrivateMessages($data);

		//We need blog info

		$channelRequests = $this->library->getChannelRequestTypes();

		$channels = array();

		if (!empty($requests))
		{
			foreach ($requests as $key => &$request)
			{
				//if it's a channel request we need the channel title
				if (in_array($request['about'], $channelRequests))
				{
					$channels[] = $request['aboutid'];
				}
				
				/* construct phrase name.  Be sure to create the new 
				 * phrases when new requests are added! Also add any new channel requests to 
				 * library\content\privatemessage's $channelRequests array.
				 * Channel requests: received_<about string>_request_from_x_link_y_<to/for>_channel_z
				 * Other requests: received_<about string>_request_from_x_link_y
				 * If the about string is equal to another request's about string after stripping sg_ and _to, the same phrase will be used.
				 * */
				$cleanAboutStr = preg_replace('/(^sg_)?|(_to$)?/', '', $request['about']);
				$request['phrasename'] = 'received_' . $cleanAboutStr . '_request_from_x_link_y';
			}
		}

		//If we have some channel info to get let's do it now.
		if (!empty($channels))
		{
			$channelInfo = vB_Api::instanceInternal('node')->getNodes($channels);

			foreach ($channelInfo AS $channel)
			{
				foreach ($requests as $key => &$request)
				{
					if ($request['aboutid'] == $channel['nodeid'])
					{
						$request['abouttitle'] = $channel['title'];
						$request['aboutrouteid'] = $channel['routeid'];
						
						// if it's a channel request, and has a title & url, the phrase name 
						// should have a "_to_channel_z" (take request) or "_for_channel_z" (grant request) appended
						if(strpos($request['about'], '_to') !== false)
						{
							$request['phrasename'] .= '_to_channel_z';
						}
						else
						{
							$request['phrasename'] .= '_for_channel_z';
						}
					}
				}
			}
		}

		return $requests;
	}

	/**
	 * Returns an array with bbcode options for PMs
	 * @return array Options
	 */
	public function getBbcodeOptions($nodeid = 0)
	{
		if (!$this->bbcodeOptions)
		{
			// all pm nodes have the same options
			$response = Api_InterfaceAbstract::instance()->callApi('bbcode', 'initInfo');
			$this->bbcodeOptions = $response['defaultOptions']['privatemessage'];
		}
		return $this->bbcodeOptions;
	}

	/** This method gets the count of undeleted messages in a folder
	 *
	 * 	@param	int		the folderid to search
	 *
	 * 	@return	int		the count
	 *
	 * */
	public function getFolderMsgCount($folderid, $pageNum = 1, $perpage = 50, $about = false)
	{
		$userid = vB::getCurrentSession()->get('userid');

		if (!intval($userid))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		$this->library->checkFolders($userid);
		$folders = $this->library->fetchFolders($userid);

		if (!array_key_exists($folderid, $folders['folders']))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		// @TODO improve the queries to return the count already to avoid using count() from rows
		if (empty($about))
		{
			$result = $this->assertor->getRows('vBForum:getMsgCountInFolder', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'folderid' => $folderid));
		}
		else
		{
			$result = $this->assertor->getRows('vBForum:getMsgCountInFolderAbout', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'userid' => $userid, 'folderid' => $folderid, 'about' => $about));
		}


		if (empty($result) OR !empty($result['errors']))
		{
			$qty = 0;
		}
		else
		{
			$qty = count($result);
		}

		if (empty($perpage))
		{
			$pagecount = ceil($qty / 50);
		}
		else
		{
			$pagecount = ceil($qty / $perpage);
		}

		if ($pageNum > 1)
		{
			$prevpage = $pageNum - 1;
		}
		else
		{
			$prevpage = false;
		}

		if ($pageNum < $pagecount)
		{
			$nextpage = $pageNum + 1;
		}
		else
		{
			$nextpage = false;
		}

		return array('count' => $qty, 'pages' => $pagecount, 'nextpage' => $nextpage, 'prevpage' => $prevpage);
	}

	/** This method gets the count of undeleted messages in a folder
	 *
	 * 	@param	int		the folderid to search
	 *
	 * 	@return	int		the count
	 *
	 * */
	public function getUnreadInboxCount()
	{
		$userid = vB::getCurrentSession()->get('userid');

		if (!intval($userid))
		{
			return 0;
		}

		$this->library->checkFolders($userid);

		if ($this->canUsePmSystem())
		{
			$result = $this->assertor->getRow('vBForum:getUnreadMsgCount', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'userid' => $userid));
		}
		else
		{
			$result = $this->assertor->getRow('vBForum:getUnreadSystemMsgCount', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'userid' => $userid));
		}

		if (empty($result) OR !empty($result['errors']))
		{
			return 0;
		}

		return $result['qty'] + $this->getOpenReportsCount();
	}

	/** This method gets the count of open reports
	 *
	 * 	@return	int		the count of open reports
	 *
	 * */
	public function getOpenReportsCount()
	{
		if (vB::getUserContext()->getChannelPermission('forumpermissions', 'canview', vB_Api::instanceInternal('node')->fetchReportChannel()))
		{
			$result = $this->assertor->getRow('vBForum:getOpenReportsCount');
			return $result['qty'];
		}
		else
		{
			// they cannot view reports. return 0
			return 0;
		}
	}

	/** This method gets the preview for the messages
	 *
	 * 	@return	mixed	array of record-up to five each messages, then requests, then
	 *
	 * */
	public function previewMessages()
	{
		if (!$this->canUsePmSystem())
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		$userid = vB::getCurrentSession()->get('userid');

		$this->library->checkFolders($userid);
		$folders = $this->library->fetchFolders($userid);
		$exclude = array($folders['systemfolders'][vB_Library_Content_Privatemessage::TRASH_FOLDER],
			$folders['systemfolders'][vB_Library_Content_Privatemessage::NOTIFICATION_FOLDER]);
		$lastnodeidsQry = $this->assertor->getRows('vBForum:lastNodeids', array('userid' => $userid, 'excludeFolders' => $exclude));
		// since the above query might not return anything, if there are no privatemessages for the user, add a -1 to prevent
		// the qryResults query from breaking
		$lastnodeids = array(-1);
		foreach ($lastnodeidsQry AS $lastnode)
		{
			$lastnodeids[] = $lastnode['nodeid'];
		}
		$ignoreUsersQry = $this->assertor->getRows('vBForum:getIgnoredUserids', array('userid' => $userid));
		$ignoreUsers = array(-1);
		foreach ($ignoreUsersQry as $ignoreUser)
		{
			$ignoreUsers[] = $ignoreUser['userid'];
		}
		$qryResults = $this->assertor->assertQuery('vBForum:pmPreview', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'userid' => $userid,
			'ignoreUsers' => $ignoreUsers,
			'excludeFolders' => $exclude,
			'nodeids' => $lastnodeids,
		));
		$results = array(
			'message' => array(
				'count' => 0,
				'title'=> (string) new vB_Phrase('global', 'messages'),
				'folderid' => 0,
				'messages' => array(),
			),
			'request' => array(
				'count' => 0,
				'title'=>(string) new vB_Phrase('global', 'requests'),
				'folderid' => 0,
				'messages' => array(),
			),
			'notification' => array(
				'count' => 0,
				'title'=> (string) new vB_Phrase('global', 'notifications'),
				'folderid' => 0,
				'messages' => array(),
			),
		);
		$messageIds = array();
		$nodeIds = array();
		$userIds = array();
		$userApi = vB_Api::instanceInternal('user');
		$starters = array();
		$receiptDetail = vB::getUserContext()->hasPermission('genericpermissions', 'canseewholiked');

		$needLast = array();
		if ($qryResults->valid())
		{
			foreach ($qryResults AS $result)
			{
				if (empty($result['previewtext']))
				{
					$result['previewtext'] = vB_String::getPreviewText($result['rawtext']);
				}

				if ($result['titlephrase'] == 'notifications')
				{
					// We will get the rate notification info seperate from this
					if ($result['about'] != 'rate')
					{
						$starters[] = $result['aboutid'];
					}
				}
				else if ($result['titlephrase'] == 'messages')
				{
					$messageIds[] = $result['nodeid'];
				}
				else
				{
					$nodeIds[] = $result['nodeid'];
				}
				
				// privatemessage_requestdetail template requires you to pass back the phrase name for requests.
				// See listRequests() for more details
				if($result['msgtype'] == 'request')
				{
					// remove starting sg_ and ending _to from the about string
					$cleanAboutStr = preg_replace('/(^sg_)?|(_to$)?/', '', $result['about']);
					$result['phrasename'] = 'received_' . $cleanAboutStr . '_request_from_x_link_y';
					
					// grab channel request types
					$channelRequests = $this->library->getChannelRequestTypes();
					
					// append correct suffix for channel requests
					if(in_array($result['about'], $channelRequests))
					{
						// should have a "_to_channel_z" (take request) or "_for_channel_z" (grant request) appended
						if(strpos($result['about'], '_to') !== false)
						{
							$result['phrasename'] .= '_to_channel_z';
						}
						else
						{
							$result['phrasename'] .= '_for_channel_z';
						}
					}
				}

				// The rate notification details are stored in reputation table. we are fetching all the data from there
				$result['showdetail'] = $receiptDetail;
				if ($result['about'] == 'rate')
				{
					$rateInfo = $this->assertor->getRow('vBForum:reputation', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						'nodeid' => $result['aboutid'],
						), array(
						'field' => 'dateline',
						'direction' => vB_dB_Query::SORT_DESC
						)
					);
					
					if ($receiptDetail)
					{
						$result['otherRecipients'] = $this->assertor->affected_rows() - 1;
						$result['lastcontentavatar'] = $userApi->fetchAvatar($rateInfo['whoadded']);
						$result['lastauthor'] = $userApi->fetchUserName($rateInfo['whoadded']);
						$result['lastauthorid'] = $rateInfo['whoadded'];
					}
					else
					{
						$result['otherRecipients'] = $this->assertor->affected_rows();
						unset($result['lastcontentavatar']);
						unset($result['lastauthor']);
						unset($result['lastauthorid']);
					}
					
					$result['is_conversation'] = ($result['starter_parent'] == $result['starter'] ? 1 : 0);
					$result['responded'] = 0;
					$results[$result['msgtype']]['messages'][$result['nodeid']] = $result;
					$results[$result['msgtype']]['count']++;
					$userIds[] = $rateInfo['whoadded'];
				}
				else
				{
					$result['senderAvatar'] = $userApi->fetchAvatar($result['userid']);
					$result['recipients'] = array();
					$result['otherRecipients'] = 0;
					$result['responded'] = 0;
					$results[$result['msgtype']]['messages'][$result['nodeid']] = $result;
					$results[$result['msgtype']]['count']++;
					$userIds[] = $result['userid'];
				}


				if (intval($result['lastauthorid']))
				{
					$userIds[] = $result['lastauthorid'];
				}
				if (!$results[$result['msgtype']]['folderid'])
				{
					$results[$result['msgtype']]['folderid'] = $result['folderid'];
				}

				// set recipients needed
				if ($result['msgtype'] == 'message')
				{
					if (empty($result['lastauthorid']) OR $result['lastauthorid'] == $userid)
					{
						$needLast[] = $result['nodeid'];
					}
				}
			}

			// @TODO check for a way to implement a generic protected library method to fetch recipients instead of cloning code through methods.
			// drag the needed info
			if (!empty($needLast))
			{
				$needLast = array_unique($needLast);
				$neededUsernames = $this->assertor->assertQuery('vBForum:getPMLastAuthor', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'nodeid' => $needLast, 'userid' => $userid));
				foreach ($neededUsernames AS $username)
				{
					if (isset($results['message']['messages'][$username['nodeid']]))
					{
						$results['message']['messages'][$username['nodeid']]['lastcontentauthor'] = $username['username'];
						$results['message']['messages'][$username['nodeid']]['lastauthorid'] = $username['userid'];
					}
				}
			}

			//Now we need to sort out the other recipients for this message.
			$recipients = array();
			if (!empty($nodeIds))
			{
				$recipientQry = $this->assertor->assertQuery('vBForum:getPMRecipients', array(
					'nodeid' => array_unique($nodeIds), 'userid' => $userid));
				foreach ($recipientQry as $recipient)
				{
					$recipients[$recipient['nodeid']][$recipient['userid']] = $recipient;
				}
			}

			$messageRecipients = array();
			if (!empty($messageIds))
			{
				$recipientsInfo = $this->assertor->assertQuery('vBForum:getPMRecipientsForMessage', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'nodeid' => $messageIds
				));

				$recipients = array();
				if (!empty($recipientsInfo))
				{
					foreach ($recipientsInfo AS $recipient)
					{
						if (isset($results['message']['messages'][$recipient['starter']]))
						{
							if (($recipient['userid'] == $userid))
							{
								if (empty($results['message']['messages'][$recipient['starter']]['included']))
								{
									$results['message']['messages'][$recipient['starter']]['included'] = true;
								}

								continue;
							}
							else if ($results['message']['messages'][$recipient['starter']]['lastcontentauthor'] == $recipient['username'])
							{
								continue;
							}

							if (!isset($results['message']['messages'][$recipient['starter']]['recipients'][$recipient['userid']]))
							{
								$results['message']['messages'][$recipient['starter']]['recipients'][$recipient['userid']] = $recipient;
							}
						}
					}
				}
			}

			$participants = array();
			if (!empty($starters))
			{
				// get a list of participants
				$participantQry = $this->assertor->assertQuery('vBForum:getParticipantsList', array('nodeids' => $starters));
				$participantList = array();
				foreach ($participantQry AS $participant)
				{
					$participantList[$participant['parent']][$participant['about']][$participant['userid']] = $participant['username'];
				}

				// also get a list of authors for the aboutuser field
				$authorQry = $this->assertor->assertQuery('vBForum:node', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'nodeid' => $starters,
					vB_dB_Query::COLUMNS_KEY => array('authorname', 'nodeid'),
					));
				$authorList = array();
				foreach ($authorQry AS $author)
				{
					$authorList[$author['nodeid']] = $author['authorname'];
				}
			}

			//Collect the user info. Doing it this way we get a lot of info in one query.
			$userQuery = $this->assertor->assertQuery('user', array('userid' => array_unique($userIds)));
			$userInfo = array();
			$userApi = vB_Api::instanceInternal('user');
			foreach ($userQuery AS $userRecord)
			{
				//some information we shouldn't pass along.
				foreach (array('password', 'salt', 'coppauser', 'securitytoken_raw', 'securitytoken', 'logouthash', 'fbaccesstoken',
					'passworddate', 'parentemail', 'logintype', 'ipaddress', 'passworddate',
					'referrerid', 'ipoints', 'infractions', 'warnings', 'infractiongroupids', 'infractiongroupid',
				) AS $field)
				{
					unset($userRecord[$field]);
				}

				$userRecord['avatar'] = $userApi->fetchAvatar($userRecord['userid'], true, $userRecord);
				$userInfo[$userRecord['userid']] = $userRecord;
			}

			//Now we need to scan the results list and assign the other recipients.
			foreach ($results AS $key => $folder)
			{
				foreach ($folder['messages'] AS $msgkey => $message)
				{
					if ($message['titlephrase'] == 'notifications')
					{
						if (!empty($participantList[$message['aboutstarterid']][$message['about']]))
						{
							$results[$key]['messages'][$msgkey]['recipients'] = $participantList[$message['aboutstarterid']][$message['about']];
							$results[$key]['messages'][$msgkey]['otherRecipients'] = count($participantList[$message['aboutstarterid']][$message['about']]) - 1;
							// comments or replies can set the node's lastauthor. We don't want the reply notification to have the commenter's name or vice versa.
							// Since they are distinct, use the username returned from the query
							$participantIDs = array_keys($participantList[$message['aboutstarterid']][$message['about']]);
							$results[$key]['messages'][$msgkey]['lastauthorid'] = $participantIDs[0];
							$message['lastauthorid'] = $participantIDs[0];
						}
						$results[$key]['messages'][$msgkey]['aboutuser'] = $authorList[$message['aboutid']];
					}
					else if ($message['titlephrase'] == 'messages')
					{
						// set the first recipient
						if (!empty($message['lastcontentauthor']) AND !empty($message['lastauthorid']) AND ($message['lastauthorid'] != $userid))
						{
							$results[$key]['messages'][$msgkey]['firstrecipient'] = array(
								'userid' => $message['lastauthorid'],
								'username' => $message['lastcontentauthor']
							);
						}
						else if (!empty($message['recipients']))
						{
							$firstrecip = reset($message['recipients']);
							$results[$key]['messages'][$msgkey]['firstrecipient'] = $firstrecip;
							unset($results[$key]['messages'][$msgkey]['recipients'][$firstrecip['userid']]);
						}

						$results[$key]['messages'][$msgkey]['otherRecipients'] = count($results[$key]['messages'][$msgkey]['recipients']);
					}
					else
					{
						if (!empty($recipients[$message['nodeid']]))
						{
							$results[$key]['messages'][$msgkey]['recipients'] = $recipients[$message['nodeid']];
							$results[$key]['messages'][$msgkey]['otherRecipients'] = count($recipients[$message['nodeid']]);
							$results[$key]['messages'][$msgkey]['userinfo'] = $userInfo[$message['userid']];
						}
					}

					if ($message['lastauthorid'])
					{
						$results[$key]['messages'][$msgkey]['lastauthor'] = $userInfo[$message['lastauthorid']]['username'];
						$results[$key]['messages'][$msgkey]['lastcontentauthorid'] = $message['lastauthorid'];
						$results[$key]['messages'][$msgkey]['lastcontentavatar'] = $userInfo[$message['lastauthorid']]['avatar'];
					}
				}

				if (empty($message['previewtext']))
				{
					$results[$key]['previewtext'] = vB_String::getPreviewText($message['rawtext']);
				}
			}
		}

		$channelRequests = $this->library->getChannelRequestTypes();

		$nodeIds = array();
		foreach ($results['request']['messages'] AS $message)
		{
			if (in_array($message['about'], $channelRequests))
			{
				$nodeIds[] = $message['aboutid'];
			}
		}

		if (!empty($nodeIds))
		{
			$nodesInfo = vB_Library::instance('node')->getNodes($nodeIds);

			$arrayNodeInfo = array();
			foreach ($nodesInfo as $node)
			{
				$arrayNodeInfo[$node['nodeid']] = array('title' => $node['title'], 'routeid' => $node['routeid']);
			}

			foreach ($results['request']['messages'] AS $key => &$val)
			{
				if (isset($arrayNodeInfo[$val['aboutid']]))
				{
					$val['abouttitle'] = $arrayNodeInfo[$val['aboutid']]['title'];
					$val['aboutrouteid'] = $arrayNodeInfo[$val['aboutid']]['routeid'];
				}
			}
		}
		
		return $results;
	}

	/** This returns the text for a "reply" or "forward" message. Not implemented yet
	 *
	 *
	 * */
	public function getReplyText($nodeid)
	{
		if (!$this->canUsePmSystem())
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		throw new vB_Exception_Api('not_implemented');
	}

	/** This sets a message to read
	 *
	 * 	@param $nodeid
	 *
	 * */
	public function setRead($nodeid, $read = 1)
	{
		if (!$this->canUsePmSystem())
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		$this->library->setRead($nodeid, $read, vB::getCurrentSession()->get('userid'));
	}

	/** This sends a notification. It's essentially an alias for addMessage.
	 *
	 * 	@param	mixed	must include 'sentto', 'contenttypeid', and the necessary data for that contenttype.
	 *
	 * 	@return	int		the new nodeid.
	 *
	 * */
	public function addNotification($data)
	{
		$this->addMessage($data, true);
	}

	/** This sends a notification. It's an alias for deleteMessage.
	 *
	 * 	@param	int	nodeid of the entry to be deleted.
	 *
	 * 	@return	bool	did the deletion succeed?
	 * */
	public function deleteNotification($nodeid)
	{
		return $this->library->deleteMessage($nodeid);
	}

	/** This function checks that we have all the folders for the current user, and the set folders are there.
	 *
	 * * */
	public function checkFolders($userid = false)
	{
		if (empty($userid))
		{
			if (!$this->canUsePmSystem())
			{
				throw new vB_Exception_Api('not_logged_no_permission');
			}
		}
		return $this->library->checkFolders(vB::getCurrentSession()->get('userid'));
	}

	/** This updates the title
	 *
	 * 	@param	mixed	array, must include folderName and folderid
	 *
	 * */
	public function updateFolderTitle($folderName, $folderid)
	{
		if (!$this->canUsePmSystem())
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		$userid = vB::getCurrentSession()->get('userid');

		$this->library->checkFolders($userid);

		if (empty($folderid) OR empty($folderName))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		$cleaner = vB::get_cleaner();
		$foldername = $cleaner->clean($folderName, $vartype = vB_Cleaner::TYPE_NOHTML);
		$folderid = intval($folderid);
		$folders = $this->library->fetchFolders($userid);
		if (!array_key_exists($folderid, $folders['folders']) OR
			in_array($folderid, $folders['systemfolders']))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		if (empty($foldername) OR (strlen($foldername) > 512))
		{
			throw new vB_Exception_Api('invalid_msgfolder_name');
		}

		//If we got here we have valid data.
		return $this->assertor->assertQuery('vBForum:messagefolder', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				vB_dB_Query::CONDITIONS_KEY => array('folderid' => $folderid),
				'title' => $foldername));
	}

	/** deletes a folder and moves its contents to trash
	 *
	 * 	@param	string	The new folder title.
	 *
	 * */
	public function deleteFolder($folderid)
	{
		if (!$this->canUsePmSystem())
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		$userid = vB::getCurrentSession()->get('userid');

		$this->library->checkFolders($userid);
		$folders = $this->library->fetchFolders($userid);

		if (!array_key_exists($folderid, $folders['folders']) OR
			in_array($folderid, $folders['systemfolders']))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		//If we got here we have valid data. First move the existing messages to trash
		$this->assertor->assertQuery('vBForum:sentto', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'folderid' => $folders['systemfolders'][vB_Library_Content_Privatemessage::TRASH_FOLDER],
			vB_dB_Query::CONDITIONS_KEY => array('folderid' => $folderid)));
		//Then delete the folder
		$this->assertor->assertQuery('vBForum:messagefolder', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'folderid' => $folderid));

		return true;
	}

	/*	 * * Returns the node content as an associative array
	 * 	@param	integer	The id in the primary table
	 * 	@param array permissions
	 * 	@param bool	appends to the content the channel routeid and title, and starter route and title the as an associative array

	 * 	@return	int
	 * * */

	public function getFullContent($nodeid, $permissions = false)
	{
		$results = $this->library->getFullContent($this->library->checkCanSee($nodeid), $permissions);

		if (empty($results))
		{
			throw new vB_Exception_Api('no_permission');
		}
		return $results;
	}

	/** gets the title and forward
	 *
	 * 	@param	mixed	will accept an array, but normall a comma-delimited string
	 *
	 * 	@return	mixed	array of first (single db record), messages- nodeid=> array(title, recipents(string), to (array of names), pagetext, date)
	 * */
	public function getForward($nodeids)
	{
		if (!is_array($nodeids))
		{
			$nodeids = explode(',', $nodeids);
		}

		if (!$this->canUsePmSystem())
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		$userid = vB::getCurrentSession()->get('userid');

		$valid = array();

		foreach ($nodeids as $nodeid)
		{
			$content = $this->nodeApi->getNode($nodeid);
			//if this is the author we can return the value
			if ($content['userid'] == $userid)
			{
				$valid[] = $nodeid;
			}
			else
			{
				//Maybe this is a recipient.
				$recipients = $this->assertor->getRows('vBForum:getPMRecipients', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'nodeid' => $nodeid, 'userid' => -1));
				foreach ($recipients as $recipient)
				{
					if ($recipient['userid'] == $userid)
					{
						$valid[] = $nodeid;
						break;
					}
				}
			}
		}

		if (empty($valid))
		{
			throw new vB_Exception_Api('invalid_data');
		}
		//Now build the response.
		$messageInfo = $this->assertor->assertQuery('vBForum:getPrivateMessageForward', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'nodeid' => $valid));

		if (!$messageInfo OR !$messageInfo->valid())
		{
			throw new vB_Exception_Api('invalid_data');
		}
		$results = array();
		$currentNode = false;
		$currentQuote = false;
		$currentAuthors = array();
		//We may have several messages, but normally all will be from one person to the same list.
		foreach ($messageInfo as $message)
		{
			if ($message['messageid'] != $currentNode['messageid'])
			{
				if ($currentNode)
				{
					$results[$currentNode['messageid']] = array('from' => $currentNode['authorname'], 'to' => $currentAuthors,
						'recipients' => implode(', ', $currentAuthors),
						'title' => $currentNode['title'],
						'date' => $currentNode['publishdate']);

					if (empty($currentNode['pagetext']))
					{
						$results[$currentNode['messageid']]['pagetext'] = $currentNode['rawtext'];
					}
					else
					{
						$results[$currentNode['messageid']]['pagetext'] = $currentNode['pagetext'];
					}
				}

				$currentNode = $message;
				$currentAuthors = array($message['username']);
			}
			else
			{
				$currentAuthors[] = $message['username'];
			}
		}

		//we'll have a last node that didn't get loaded.
		if ($currentNode)
		{
			$results[$currentNode['messageid']] = array('from' => $currentNode['authorname'], 'to' => $currentAuthors,
				'recipients' => implode(', ', $currentAuthors),
				'title' => $currentNode['title'],
				'date' => $currentNode['publishdate']);

			if (empty($currentNode['pagetext']))
			{
				$results[$currentNode['messageid']]['pagetext'] = $currentNode['rawtext'];
			}
			else
			{
				$results[$currentNode['messageid']]['pagetext'] = $currentNode['pagetext'];
			}
		}

		$firstMessage = reset($results);
		return array('first' => $firstMessage, 'messages' => $results);
	}

	/**
	 * Verifies that the request exists and its valid.
	 * Returns the message if no error is found.
	 * Throws vB_Exception_Api if an error is found.
	 * @param int $userid
	 * @param int $nodeid
	 * @return array - message info
	 */
	protected function validateRequest($userid, $nodeid)
	{
		return $this->library->validateRequest($userid, $nodeid);
	}

	/** This function denies a user follow request
	 *
	 * 	@param	int		the nodeid of the request
	 *	@param	int		(optional) the userid to whom the request was sent
	 * 	@return	bool
	 *
	 * */
	public function denyRequest($nodeid, $cancelRequestFor = 0)
	{
		return $this->library->denyRequest($nodeid, $cancelRequestFor);
	}

	/** This function accepts a user follow request or a channel ownership/moderation/membership request
	 *
	 * 	@param	int		the nodeid of the request
	 *
	 * 	@return	bool
	 *
	 * */
	public function acceptRequest($nodeid)
	{
		return $this->library->acceptRequest($nodeid);
	}

	/** Clears the cached folder information
	 *
	 * */
	public function resetFolders()
	{
		$this->library->resetFolders();
	}

	public function validate(&$data, $action = vB_Api_Content::ACTION_ADD, $nodeid = false, $nodes = false)
	{
		if (vB::getUserContext()->isSuperAdmin())
		{
			return true;
		}

		if (!$this->canUsePmSystem())
		{
			throw new vB_Exception_Api('not_logged_no_permission');
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

		switch ($action)
		{
			case vB_Api_Content::ACTION_ADD:
				// VBV-3512
				if (vB::getUserContext()->isGloballyIgnored())
				{
					throw new vB_Exception_Api('not_logged_no_permission');
				}

				// HV is not needed while sending requests or notifications.
				if (!isset($data['msgtype']) OR !in_array($data['msgtype'], array('request', 'notification')))
				{
					vB_Api::instanceInternal('hv')->verifyToken($data['hvinput'], 'post');
				}

				//parentid must be pmChannel or a descendant.
				if ($parentid != $this->pmChannel)
				{
					$closure = vB_Library::instance('node')->fetchClosureParent($parentid, $this->pmChannel);

					if (!$closure OR !is_array($closure) OR empty($closure) OR empty($closure[$parentid]))
					{
						throw new vB_Exception_Api('invalid_data');
					}
				}

				return vB::getUserContext()->getChannelPermission('createpermissions', $this->contenttype, $parentid);
				break;

			case vB_Api_Content::ACTION_UPDATE:
				//They can only update if they are a moderator with permission to moderate messages.
				// As a moderator
				foreach ($nodes as $node)
				{
					if (!vB::getUserContext()->getChannelPermission('moderatorpermissions', 'caneditposts', $node['nodeid'], false, $node['parentid']))
					{
						return false;
					}
				}
				return true;
				break;

			case vB_Api_Content::ACTION_VIEW:
				//Maybe we already have a record.
				if (!isset($this->canSee[$currentUser]))
				{
					$this->canSee[$currentUser] = array();
				}

				$canSeeQry = $this->assertor->assertQuery('vBForum:sentto', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'nodeid' => $nodeid, 'userid' => $currentUser));

				//We scan the $canSeeQuery list. If there's a match then they can view this node.
				foreach ($canSeeQry as $sentto)
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
				throw new vB_Exception_NodePermission($node['nodeid']);
				break;

			case vB_Api_Content::ACTION_DELETE:
				foreach ($nodes as $node)
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

	/** returns a formatted json string appropriate for the search api interface
	 *
	 * 	@param	string	the search query
	 *
	 * 	@return	string	the json string
	 * */
	public function getSearchJSON($queryText)
	{
		return json_encode(array('keywords' => $queryText,
				/* 'contenttypeid' => vB_Types::instance()->getContentTypeId('vBForum_PrivateMessage' ) */
				'type' => 'vBForum_PrivateMessage'));
	}

	/**
	 * Get the pending posts folder id
	 *
	 * @return	int		The pending posts folder id from messagefolder.
	 */
	public function getPendingPostFolderId()
	{
		return $this->library->getPendingPostFolderId;
	}

	/**
	 * Move a message back to user inbox folder
	 *
	 * @params		int		The nodeid we are undeleting.
	 *
	 * @return		bool	True if succesfully done.
	 */
	public function undeleteMessage($nodeid)
	{
		if (!$this->canUsePmSystem())
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		$userInfo = vB::getCurrentSession()->fetch_userinfo();
		$currentUser = $userInfo['userid'];

		if (!intval($currentUser))
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}

		$nodeids = explode(',', $nodeid);

		if (!is_array($nodeids))
		{
			$nodeids = array($nodeids);
		}

		//we can only move a record to which the user has access.
		$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'userid', 'value' => $currentUser),
				array('field' => 'nodeid', 'value' => $nodeids)
			));
		$existing = $this->assertor->getRows('vBForum:sentto', $data);

		if (empty($existing) OR !empty($existing['errors']))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		return $this->library->undeleteMessage($nodeid, $existing);
	}

	/**
	 * Delete private messages messages. Once deleted user won't be able to retrieve them again.
	 *
	 * @params		array	Array of the nodeids from messages to delete.
	 *
	 * @return		bool	Indicating if deletion were succesfully done or will throw exception.
	 */
	public function deleteMessages($nodeid)
	{
		$nodeids = array();
		if (strpos($nodeid, ',') !== false)
		{
			$nodeids = explode(',', $nodeid);
		}
		else if (!is_array($nodeid))
		{
			$nodeids = array($nodeid);
		}

		foreach ($nodeids as $nodeid)
		{
			$this->deleteMessage($nodeid);
		}
	}

	/**
	 * Gets the folder information from a given folderid. The folderid requested should belong to the user who is requesting.
	 * @TODO implement cache if needed and extend to support an array of folderids if needed.
	 *
	 * @param	int		The folderid to fetch information for.
	 *
	 * @return	array	The folder information such as folder title, titlephrase and if is custom folder.
	 *
	 */
	public function getFolderInfoFromId($folderid)
	{
		if (!$this->canUsePmSystem())
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		$userid = vB::getCurrentSession()->get('userid');

		$folderid = intval($folderid);
		if (!$folderid)
		{
			throw new vB_Exception_Api('invalid_data');
		}

		// check that the folderid belongs to the user request.
		// @TODO we might want to let admin to fetch any requested folder
		$folders = $this->library->listFolders();
		if (!in_array($folderid, array_keys($folders)))
		{
			throw new vB_Exception_Api('no_permission');
		}

		return $this->library->getFolderFromId($folderid, $userid);
	}

	/** returns the cached folder information
	 *
	 * 	@param		int		Userid we are fetching folders for.
	 *
	 * 	@return		mixed	Array containing user folders info.
	 *
	 * */
	public function fetchFolders($userid)
	{
		$userid = intval($userid);
		if (!$userid)
		{
			throw new vB_Exception_Api('invalid_data');
		}

		return $this->library->fetchFolders($userid);
	}

	/** Returns an array of all users participating in a discussion, but omitting the current user
	 *
	 * 	@param	int		the nodeid of the discussion
	 *
	 * 	@return	mixed	array of user information including avatar.
	 *
	 * */
	public function fetchParticipants($nodeid)
	{
		if (!intval($nodeid))
		{
			throw new vB_Exception_Api('invalid_data');
		}
		$userInfo = vB::getCurrentSession()->fetch_userinfo();
		$currentUser = $userInfo['userid'];

		//We always should have something in $exclude.
		$exclude = array('-1');

		if (intval($currentUser))
		{
			$options = vB::getDatastore()->get_value('options');
			if (trim($options['globalignore']) != '')
			{
				$exclude = preg_split('#\s+#s', $options['globalignore'], -1, PREG_SPLIT_NO_EMPTY);
			}
		}

		$node = vB_Api::instanceInternal('node')->getNode($nodeid);
		$nodeCTClass = vB_Types::instance()->getContentTypeClass($node['contenttypeid']);

		switch ($nodeCTClass)
		{
			case self::PARTICIPANTS_PM :
				$queryPart = 'vBForum:getPMRecipientsForMessageOverlay';
				$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'nodeid' => $nodeid);
				break;
			case self::PARTICIPANTS_POLL :
				$queryPart = 'vBForum:getNotificationPollVoters';
				$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'nodeid' => $nodeid);
				break;
			default :
				$queryPart = 'vBForum:fetchParticipants';
				$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'nodeid' => $nodeid, 'currentuser' => $currentUser, 'exclude' => $exclude);
				break;
		}

		$members = vB::getDbAssertor()->getRows($queryPart, $params);

		$partcipitans = array();
		foreach ($members AS $member)
		{
			if (isset($partcipitans[$member['userid']]))
			{
				continue;
			}

			$partcipitans[$member['userid']] = $member;
		}

		$userApi = vB_Api::instanceInternal('user');
		foreach ($partcipitans as $uid => $participant)
		{
			$partcipitans[$uid]['avatarurl'] = $userApi->fetchAvatar($uid, true, $participant);
		}

		return $partcipitans;
	}

}

