<?php
if (!defined('VB_ENTRY')) die('Access denied.');
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
 * vB_Api_Content_Text
 *
 * @package vBApi
 * @author ebrown
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Library_Content_Text extends vB_Library_Content
{
	//override in client- the text name
	protected $contenttype = 'vBForum_Text';

	//The table for the type-specific data.
	protected $tablename = 'text';

	//When we parse the page.
	protected $bbcode_parser = false;

	//Whether we change the parent's text count- 1 or zero
	protected $textCountChange = 1;

	//Whether we inherit viewperms from parents
	protected $inheritViewPerms = 1;

	//for spam checking
	protected $spamType = false;
	protected $spamKey = false;
	protected $akismet;

	//Does this content show author signature?
	protected $showSignature = true;

	/**
	 * If true, then creating a node of this content type will increment
	 * the user's post count. If false, it will not. Generally, this should be
	 * true for topic starters and replies, and false for everything else.
	 *
	 * @var	bool
	 */
	protected $includeInUserPostCount = true;

	protected function __construct()
	{
		parent::__construct();
		//see if we have spam checking set.
		if (isset($this->options['vb_antispam_type']) AND $this->options['vb_antispam_type'] > 0 AND !empty($this->options['vb_antispam_key']))
		{
			$this->spamType = $this->options['vb_antispam_type'];
			$this->spamKey = $this->options['vb_antispam_key'];
		}
	}

	/** This function parses the raw text and stores the result in pagetext and previewtext
	*
	*	@param	mixed
	***/
	protected function parseContent(&$record)
	{
		require_once(DIR . '/includes/class_bbcode.php');
	//	require_once(DIR . '/packages/vbforum/bbcode/wysiwyg.php');
		if ($this->bbcode_parser == false)
		{
			//TODO: execute the line below to get all text tags.
			//$tags = vBForum_BBCode_Wysiwyg::fetchTextTags();
			$tags = fetch_tag_list();
			$registry = vB::get_registry();

			if (empty($registry->db) AND !empty(vB::$db))
			{
				$registry->db = vB::$db;
			}

			$this->bbcode_parser = new vB_BbCodeParser($registry, $tags);
		}

		// Articles will generally have an attachment but they should still keep a counter so that this query isn't always running
		require_once(DIR . '/packages/vbattach/attach.php');
		$attach = new vB_Attach_Display_Content(vB::$vbulletin, 'vBForum_Text');
		$attachments = $attach->fetch_postattach(0, $record['nodeid']);
		$this->bbcode_parser->attachments = $attachments;
		$this->bbcode_parser->unsetattach = true;

		require_once DIR . '/includes/functions.php';
		$this->bbcode_parser->attachments = $attachments;

		$authorContext = vB::getUserContext($record['userid']);

		$record['pagetext'] = fetch_censored_text($this->bbcode_parser->do_parse(
			$record['rawtext'],
			$authorContext->getChannelPermission('forumpermissions2', 'canusehtml', $record['parentid']),
			true, true, $authorContext->getChannelPermission('forumpermissions', 'cangetattachment', $record['parentid']),
			true,true,$record->htmlstate
			));

		$attach->process_attachments($record, $this->bbcode_parser->attachments, false, false, true, false, true);

		$record['previewtext'] = $this->bbcode_parser->get_preview($record['rawtext'], 200,
			false, true, $record->htmlstate );
		$this->assertor->assertQuery('vBForum:text', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE, 'nodeid' => $record['nodeid'],
			'pagetext' => $record['pagetext'], 'previewtext' => $record['previewtext']) );
	}

	protected function sendEmailNotification($data)
	{
		if ($data['about'] == vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_VM)
		{
			$node = $this->getContent($data['contentnodeid']);
			$node = $node[$data['contentnodeid']];

			$maildata = vB_Api::instanceInternal('phrase')->
				fetchEmailPhrases('visitormessage', array(
					$data['username'],
					$node['userinfo']['username'],
					vB5_Route::buildUrl('visitormessage|nosession|fullurl', array('nodeid' => $node['nodeid'], 'title' => $node['title'])),
					vB_String::getPreviewText($node['rawtext']),
					vB::getDatastore()->getOption('bbtitle'),
					),
					array()
			);
		}
		else
		{
			$node = $this->nodeApi->getNode($data['aboutid']);
			$currentNode = $this->getContent($data['contentnodeid']);
			$currentNode = $currentNode[$data['contentnodeid']];

			switch ($data['about'])
			{
				case 'reply':
					$phrase = vB_Api::instanceInternal('phrase')->fetch(array('replied', 'thread', 'post'));
					$about = $phrase['replied'];
					$aboutNode = ($currentNode['starter'] == $currentNode['parentid']) ? $phrase['thread'] : $phrase['post'];
					break;
				case 'comment':
					$phrase = vB_Api::instanceInternal('phrase')->fetch(array('commented', 'thread', 'post'));
					$about = $phrase['commented'];
					$aboutNode = ($currentNode['starter'] == $currentNode['parentid']) ? $phrase['thread'] : $phrase['post'];
					break;
				case 'threadcomment':
					$phrase = vB_Api::instanceInternal('phrase')->fetch(array('commented', 'thread'));
					$about = $phrase['commented'];
					$aboutNode = $phrase['thread'];
					break;
				default:
					$phrase = vB_Api::instanceInternal('phrase')->fetch(array('thread', 'post'));
					$about = $data['about'];
					$aboutNode = ($currentNode['starter'] == $currentNode['parentid']) ? $phrase['thread'] : $phrase['post'];
					break;
			}

			$routeInfo = array(
				'nodeid' => $node['starter'],
			);

			$maildata = vB_Api::instanceInternal('phrase')->
				fetchEmailPhrases('reply', array(
					$data['username'],
					$currentNode['userinfo']['username'],
					$about,
					$aboutNode,
					vB5_Route::buildUrl($node['routeid'] . '|nosession|fullurl', $routeInfo, array('goto' => 'newpost')),
					vB_String::getPreviewText($currentNode['rawtext']),
					vB::getDatastore()->getOption('bbtitle'),
					vB5_Route::buildUrl('subscription|nosession|fullurl', array('tab' => 'subscriptions', 'userid' => $data['userid'])),
					),
					array(vB::getDatastore()->getOption('bbtitle')
				)
			);
		}
		// Sending the email
		vB_Mail::vbmail($data['email'], $maildata['subject'], $maildata['message'], false);
	}

	/*** Returns the node content as an associative array with fullcontent
	 *	@param	mixed	integer or array of integers=The id in the primary table
	 *	@param array permissions
	 *
	 * 	 *	@param bool	appends to the content the channel routeid and title, and starter route and title the as an associative array
	 ***/
	public function getFullContent($nodes, $permissions = false)
	{
		if (empty($nodes))
		{
			return array();
		}

		$results = parent::getFullContent($nodes, $permissions);
		return $this->addContentInfo($results);
	}

	protected function addContentInfo($results)
	{
		//the key of for each node is the nodeid, fortunately
		$userids = array();
		$userContext = vB::getUserContext();
		//If pagetext and previewtext aren't populated let's do that now.
		foreach ($results as $key => $record)
		{
			if (isset($record['pagetextimages']))
			{
				unset($results[$key]['pagetextimages']);
			}

			//make sure the current user can see the content
			if (!$userContext->getChannelPermission('forumpermissions', 'canviewthreads', $record['nodeid'], false, $record['parentid']))
			{
				continue;
			}

			if (!empty($record['userid']) AND !in_array($record['userid'], $userids))
			{
				$userids[] = $record['userid'];
			}

			if (empty($record['starter']))
			{
				//The starter should never be empty or zero.  Let's fix this.
				$starter = $this->getStarter($record['nodeid']);
				$data = array(vB_dB_Query::TYPE_KEY =>vB_dB_Query::QUERY_UPDATE, 'nodeid' => $record['nodeid'],
					'starter' => $starter);
				$this->assertor->assertQuery('vBForum:node', $data);
				$results[$key]['starter'] = $starter;
			}
			$results[$key]['attach'] = array();
		}

		if (!empty($userids))
		{
			//First, see what we can get from the page cache.
			$users = array();
			$pageCache = vB_Cache::instance(vB_Cache::CACHE_FAST);
			foreach ($userids AS $key => $userid)
			{
				$userInfo = $pageCache->read('userDetails.' . $userid);

				if (!empty($userInfo))
				{
					$users[$userid] = $userInfo;
					//unset so we won't query.
					unset($userids[$key]);
				}
			}

			//Now query for whatever is left
			if (!empty($userids))
			{
				$userQry = $this->assertor->assertQuery('vBForum:fetchUserinfo', array(vB_dB_Query::TYPE_KEY =>vB_dB_Query::QUERY_METHOD,
					'userid' => $userids));

				if ($userQry AND $userQry->valid())
				{
					$user = $userQry->current();
					while($userQry->valid())
					{

						//If this user has a signature we'll probably want it.
						/** TODO:
						 * This is currently broken because the cache_permissions function is broken.
						 * It's not worth fixing- it's broken because it relies on the $vbulletin object.
						 if (!empty($user['signature']))
						 {
						 require_once(DIR . '/includes/class_sigparser.php');
						 $userperms = cache_permissions($user['userid'], false);
						 $bbcode_parser = new vB_SignatureParser(vB::$vbulletin, fetch_tag_list(), $userperms, $user['userid'] );

						 $authorContext = vB::getUserContext($user['userid']);
						 $user['sigparsed'] = fetch_censored_text($bbcode_parser->do_parse(
						 $user['signature'],
						 $authorContext->getChannelPermission('forumpermissions2', 'canusehtml', $record['parentid']),
						 true, true, $authorContext->getChannelPermission('forumpermissions', 'cangetattachment', $record['parentid']),
						 true,true,$record->htmlstate
						 ));
						 }
						 ****/
						$pageCache->write('userDetails.' . $user['userid'], $user, 1440, 'userChanged.' . $userid);
						$users[$user['userid']] = $user;
						$user = $userQry->next();
					}
				}
			}

			$canseehiddencustomfields = vB::getUserContext()->hasPermission('genericpermissions', 'canseehiddencustomfields');

			$fields = array();
			if (!$canseehiddencustomfields)
			{
				// Get profile fields information
				$fieldsInfo = $this->assertor->getRows('vBForum:profilefield');

				foreach ($fieldsInfo as $field)
				{
					$fields['field' . $field['profilefieldid']] = $field['hidden'];
				}
			}

			foreach ($results AS $key => $record)
			{
				if (!empty($record['userid']) AND !empty($users[$record['userid']]))
				{
					$results[$key]['userinfo'] = array(
						'userid' => $users[$record['userid']]['userid'],
						'username' => $users[$record['userid']]['username'],
						'rank' => $users[$record['userid']]['rank'],
						'usertitle' => $users[$record['userid']]['usertitle'],
						'joindate' => $users[$record['userid']]['joindate'],
						'posts' => $users[$record['userid']]['posts'],
						'customtitle' => $users[$record['userid']]['customtitle'],
						'userfield' => array(),
					);

					// Add userfields data
					foreach (array_keys($users[$record['userid']]) as $item)
					{
						if (preg_match('/^field[0-9]+/', $item))
						{
							if (!$canseehiddencustomfields AND ($fields[$item] == 1))
							{
								// Hidden field
								continue;
							}

							$results[$key]['userinfo']['userfield'][$item] = $users[$record['userid']][$item];
						}
					}

					if (!empty($results[$key]['showsignature']) AND !empty($users[$record['userid']]['sigparsed']) )
					{
						$results[$key]['signature'] = $users[$record['userid']]['sigparsed'];
					}
				}
			}
		}

		//let's get the attachment info.
		$attachments = vB_Api::instanceInternal('node')->getNodeAttachments(array_keys($results));
		foreach ($attachments as $attachment)
		{
			if (array_key_exists($attachment['parentid'], $results))
			{
				$results[$attachment['parentid']]['attach'][] = $attachment;
			}
		}

		foreach ($results as $key => $result)
		{
			if (empty($result))
			{
				continue;
			}
			if (!empty($result['attach']) AND is_array($result['attach']))
			{
				$results[$key]['photocount'] = count($result['attach']);

			}
			else
			{
				$results[$key]['photocount'] = 0;
			}
		}

		return $results;
	}


	/*** updates a record
	 *
	 *	@param	mixed		array of nodeid's
	 *	@param	mixed		array of permissions that should be checked.
	 *  @param	bool		Convert text to bbcode
	 *
	 * 	@return	boolean
	 ***/
	public function update($nodeid, $data, $convertWysiwygTextToBbcode = true)
	{
		$node = $this->assertor->getRow('vBForum:node', array('nodeid' => $nodeid));

		//We may need to update the "last" counts.
		if (isset($data['publishdate']) OR isset($data['unpublishdate']) OR isset($data['showpublished']))
		{
			$updates = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'lastauthorid' => $node['userid'], 'nodeid' => $nodeid);

			if (!isset($data['publishdate']))
			{
				$updates['lastcontent'] = $node['publishdate'];
			}
			else
			{
				$updates['lastcontent'] = $data['publishdate'];
			}

			if (empty($data['lastcontentauthor']))
			{
				$updates['lastcontentauthor'] = $node['authorname'];
			}
			else
			{
				$updates['lastcontentauthor'] = $data['authorname'];
			}

			if (empty($data['lastauthorid']))
			{
				$updates['lastauthorid'] = $node['userid'];
			}
		}

		if (isset($data['rawtext']))
		{
			if ($convertWysiwygTextToBbcode)
			{
				$parents = vB_Library::instance('node')->getParents($node['parentid']);
				$parents = array_reverse($parents);
				$channelType = vB_Types::instance()->getContentTypeId('vBForum_Channel');

				// check if we can autoparselinks
				$options['autoparselinks'] = true;
				foreach($parents AS $parent)
				{
					// currently only groups and blogs seem to disallow this
					if (
							($parent['contenttypeid'] == $channelType
							AND vB_Api::instanceInternal('socialgroup')->isSGNode($parent['nodeid']) OR vB_Api::instanceInternal('blog')->isBlogNode($parent['nodeid']))
							AND ($channelOptions = vB_Library::instance('node')->getNodeOptions($parent['nodeid']))
					)
					{
						$options['autoparselinks'] = $channelOptions['autoparselinks'];
					}
				}

				$data['rawtext'] = vB_Api::instanceInternal('bbcode')->convertWysiwygTextToBbcode($data['rawtext'], $options);
			}

			if (!isset($data['pagetext']))
			{
				$data['pagetext'] = '';
			}

			if (!isset($data['previewtext']))
			{
				$data['previewtext'] = '';
			}
			//Set the "hasvideo" value;
			$filter = '~\[video.*\[\/video~i';
			$matches = array();
			$count = preg_match_all($filter, $data['rawtext'], $matches);

			if ($count > 0 )
			{
				$data['hasvideo'] = 1;
			}
			else
			{
				$data['hasvideo'] = 0;
			}
		}

		$published = $this->isPublished($data);
		$result = parent::update($nodeid, $data, $published);

		if (isset($node) AND ($published <> $node['showpublished']))
		{
			//We don't need to update the counts- that gets done in the parent class.
			if ($published)
			{
				$updates['lastcontentid'] = $nodeid;
				$this->assertor->assertQuery('vBForum:setLastData', $updates);
			}
		}
		$this->nodeApi->clearCacheEvents(array($nodeid, $node['parentid']));
		return $result;
	}

	/**
	 * Permanently deletes a node
	 *
	 * @param	integer	The nodeid of the record to be deleted
	 *
	 * @return	boolean
	 */
	public function delete($nodeid)
	{
		//We need to update the parent counts, but first we need to get the status
		try
		{
			$node = vB_Library::instance('node')->getNodeBare($nodeid);
		}
		catch (Exception $e)
		{
			// make sure the parent counts are updated
			$node['showpublished'] = true;
		}

		//We have to get this before we delete
		$parents = vB_Library::instance('node')->getParents($nodeid);

		//do the delete
		$result = parent::delete($nodeid);
		$cachedNodes = array($nodeid);

		// reset last content for all parents that have the deleted node
		$this->assertor->update('vBForum:node',
				array(
					'lastcontent' => 0,
					'lastcontentid' => 0,
					'lastcontentauthor' => '',
					'lastauthorid' => 0),
				array(
					'lastcontentid' => $nodeid
				)
		);

		foreach ($parents AS $parent)
		{
			$cachedNodes[] = $parent['parent'];
		}

		vB_Library::instance('node')->resetAncestorCounts($node, $parents, true);

		$this->nodeApi->clearCacheEvents($cachedNodes);
		return $result;
	}

	public function parseAndStrip($text)
	{
		if (!empty($text))
		{
			// We can ignore autoparselinks setting here since the tags will be stripped anyway
			$bbOptions = array('autoparselinks' => false);
			$text = vB_Api::instanceInternal('bbcode')->convertWysiwygTextToBbcode($text, $bbOptions);
			$options = vB::getDatastore()->get_value('options');
			return trim(vB_String::stripBbcode($text, $options['ignorequotechars']));
		}

		return '';
	}

	/**
	 * Adds a new node.
	 *
	 * @param	mixed		Array of field => value pairs which define the record.
	 * @param	array		Array of options for the content being created.
	 * @param	bool		Convert text to bbcode
	 *
	 * @return	integer		the new nodeid
	 */
	public function add($data, array $options = array(), $convertWysiwygTextToBbcode = true)
	{
		if (empty($data['parentid']))
		{
			throw new Exception('need_parent_node');
		}

		// Get parents for cleaning cache and checking permissions
		$parents = vB_Library::instance('node')->getParents($data['parentid']);
		$parents = array_reverse($parents);

		// convert to bbcode for saving
		if (isset($data['rawtext']) AND !empty($data['rawtext']))
		{
			if (isset($options['nl2br']) AND $options['nl2br'])
			{
				$data['rawtext'] = nl2br($data['rawtext']);
			}

			if ($convertWysiwygTextToBbcode)
			{
				$channelType = vB_Types::instance()->getContentTypeId('vBForum_Channel');

				// check if we can autoparselinks
				$options['autoparselinks'] = true;
				foreach($parents AS $parent)
				{
					// currently only groups and blogs seem to disallow this
					if (
							($parent['contenttypeid'] == $channelType
							AND vB_Api::instanceInternal('socialgroup')->isSGNode($parent['nodeid']) OR vB_Api::instanceInternal('blog')->isBlogNode($parent['nodeid']))
							AND ($channelOptions = vB_Library::instance('node')->getNodeOptions($parent['nodeid']))
					)
					{
						$options['autoparselinks'] = $channelOptions['autoparselinks'];
					}
				}

				$data['rawtext'] = vB_Api::instanceInternal('bbcode')->convertWysiwygTextToBbcode($data['rawtext'], $options);
			}
		}

		if (empty($data['userid']))
		{
			$user = vB::getCurrentSession()->fetch_userinfo();
			$data['authorname'] = $user['username'];
			$userid = $data['userid'] = $user['userid'];
		}
		else
		{
			$userid = $data['userid'];
			if (empty($data['authorname']))
			{
				$data['authorname'] = vB_Api::instanceInternal('user')->fetchUserName($userid);
			}
			$user = vB_Api::instance('user')->fetchUserinfo($userid);
		}

		$isSpam = false;

		//run the spam check.
		if ($this->spamType !== false)
		{
			if (empty($this->akismet))
			{
				$this->akismet = vB_Akismet::instance();
			}

			$params = array('comment_type' => 'user_post', 'comment_content' => $data['rawtext']);

			$params['comment_author'] = $data['authorname'];
			$params['comment_author_email'] = $user['email'];
			$result = $this->akismet->verifyText($params);

			if ($result == 'spam')
			{
				$data['publishdate'] = 0;
				$data['showpublished'] = 0;
				$isSpam = true;
			}
		}

		//We need a copy of the data, maybe
		$updates = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED);

		//Set the "hasvideo" value;
		if (!empty($data['rawtext']))
		{
			$filter = '~\[video.*\[\/video~i';
			$matches = array();
			$count = preg_match_all($filter, $data['rawtext'], $matches);

			if ($count > 0 )
			{
				$data['hasvideo'] = 1;
			}
			else
			{
				$data['hasvideo'] = 0;
			}
		}

		if (!isset($data['publishdate']))
		{
			$data['publishdate'] = vB::getRequest()->getTimeNow();
		}
		$updates['lastcontent'] = $data['publishdate'];

		if (isset($data['userid']))
		{
			$updates['lastauthorid'] = $data['userid'];
		}
		else
		{
			$updates['lastauthorid'] = $data['userid'] = vB::getCurrentSession()->get('userid');
		}

		if (isset($data['authorname']))
		{
			$updates['lastcontentauthor'] = $data['authorname'];
		}
		else
		{
			$author = $this->assertor->getRow('user', array(
				vB_Db_Query::TYPE_KEY =>vB_dB_Query::QUERY_SELECT,
				'userid' => $data['userid'],
			));
			$data['authorname'] = $author['username'];
			$updates['lastcontentauthor'] = $author['username'];
		}

		$published = $this->isPublished($data);
		$nodeid = parent::add($data, $options);

		// Obtain and set generic conversation route
		$conversation = $this->getConversationParent($nodeid);
		$routeid = vB_Api::instanceInternal('route')->getChannelConversationRoute($conversation['parentid']);
		$this->assertor->update('vBForum:node', array('routeid' => $routeid), array('nodeid' => $nodeid));

		//set the last post and count data.

		if ($published AND (!isset($options['skipUpdateLastContent']) OR !$options['skipUpdateLastContent']))
		{
			$updates['nodeid'] = $nodeid;
			$updates['lastcontentid'] = $nodeid;
			$this->assertor->assertQuery('vBForum:setLastData', $updates);
		}

		if ($isSpam)
		{
			$this->assertor->assertQuery('spamlog', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT, 'postid' => $nodeid));
		}

		$cachedNodes = array($nodeid);
		foreach ($parents as $node)
		{
			$cachedNodes[] = $node['nodeid'];
		}

		$this->nodeApi->clearCacheEvents($cachedNodes);
		$this->nodeApi->clearCacheEvents(array($nodeid, $data['parentid']));

		// publish to facebook
		$this->publishToFacebook($nodeid, $options);

		return $nodeid;
	}

	/**
	 * Processing after a move. In this case, update the starter field and set the text counts and "last" data.
	 *
	 * @param	int	the nodeid
	 * @param	int	the old parentid
	 * @param	int	the new parentid
	 */
	public function afterMove($nodeid, $oldparent, $newparent)
	{
		parent::afterMove($nodeid, $oldparent, $newparent);
		$newParentNode = $this->nodeApi->getNode($newparent);

		//if this IS the starter, then no change is necessary.
		if ($newParentNode['starter'] != $nodeid)
		{
			$starter = $this->getStarter($nodeid);
			$this->assertor->assertQuery('vBForum:setStarter', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'nodeid' => $nodeid, 'starter' => $starter));
		}

	}

	/** THis returns a string with quoted strings in bbcode format.
	*
	*	@param	mixed	array of integers
	*
	* 	@return	string
	***/

	public function getQuotes($nodeids)
	{
		if (is_array($nodeids)){
			$quotes = array();
			foreach ($nodeids as $nodeid)
			{
				$node = $this->getContent($nodeid);
				$node = $node[$nodeid];
				$quotes[$nodeid] = "[QUOTE=$node[authorname];n$nodeid]$node[rawtext][/QUOTE]";
			}
			return $quotes;
		}
		else
		{
			$node = $this->getContent($nodeids);
			$node = $node[$nodeids];
			$quote = "[QUOTE=$node[authorname];n$nodeids]$node[rawtext][/QUOTE]";
			return $quote;
		}
	}

	public function getIndexableFromNode($content, $recursive = true)
	{
		$indexableContent['title'] = '';
		if (!empty($content['title']))
		{
			$indexableContent['title'] = $content['title'];
		}
		if (!empty($content['rawtext']))
		{
			$indexableContent['rawtext'] = $content['rawtext'];
		}
		return $indexableContent;
	}

	public function getIndexableContent($nodeId, $include_attachments = true)
	{
		// we might already have the content
		if (is_array($nodeId) AND array_key_exists('nodeid', $nodeId))
		{
			$content = $nodeId;
			$nodeId = $content['nodeid'];
			if (!array_key_exists('rawtext', $content))
			{
				$content = $this->getContent($nodeId);
				$content = $content[$nodeId];
			}
		}
		else
		{
			$content = $this->getContent($nodeId);
			$content = $content[$nodeId];
		}
		$all_content = parent::getIndexableContent($content, $include_attachments = true);
		if (!empty($content) AND !empty($content['rawtext']) AND empty($all_content['rawtext']))
		{
			array_push($all_content, $content['rawtext']);
		}
		return $all_content;
	}

	/**
	 * Adds content info to $result so that merged content can be edited.
	 * @param array $result
	 * @param array $content
	 */
	public function mergeContentInfo(&$result, $content)
	{
		if (!isset($content['rawtext']))
		{
			throw new vB_Exception_Api('Invalid content info.');
		}

		if (!isset($result['rawtext']) || empty($result['rawtext']))
		{
			$result['rawtext'] = $content['rawtext'];
		}
		else
		{
			$result['rawtext'] .= "\n{$content['rawtext']}";
		}
	}

	/**
	 * Performs the merge of content and updates the node.
	 * @param type $data
	 * @return type
	 */
	public function mergeContent($data)
	{
		// if we are merging text, the contenttype must already be text, so just update rawtext, author
		$update = array(
			'userid' => $data['destauthorid'],
			'rawtext' => $data['text']
		);

		return $this->update($data['destnodeid'], $update);
	}

	/*** Assembles the response for detailed content
	 *
	 *	@param	mixed	assertor response object
	 *	@param	mixed	optional array of permissions
	 *
	 *	@return	mixed	formatted data
	 ***/
	public function assembleContent(&$content, $permissions = false)
	{
		$nodesContent = parent::assembleContent($content, $permissions);
		$results = array();
		//If we don't already have ancestry, we need to add it for the canEditThreadTitle check.
		$needParents = array();

		foreach($nodesContent AS $record)
		{
			if (empty($record['parents']) AND !empty($record['nodeid']))
			{
				$needParents[$record['nodeid']] = $record['nodeid'];
			}
		}

		if (!empty($needParents))
		{
			$parents = vB_Library::instance('node')->preLoadParents($needParents);
			foreach ($nodesContent AS $key => $content)
			{
				if (!empty($content['nodeid']) AND !empty($parents[$content['nodeid']]))
				{
					$nodesContent[$key]['parents'] = $parents[$content['nodeid']];
				}
			}
		}

		$userid = vB::getCurrentSession()->get('userid');
		foreach($nodesContent AS $record)
		{
			if (isset($record['nodeid']))
			{
				if (($record['starter'] == $record['nodeid']) AND ($userid > 0) AND vB_Library::instance('node')->canEditThreadTitle($record['nodeid'], $record))
				{
					$record['canedittitle'] = 1;
				}
				else
				{
					$record['canedittitle'] = 0;
				}
				$results[$record['nodeid']] = $record;
			}

		}
		return $results;
	}
}
