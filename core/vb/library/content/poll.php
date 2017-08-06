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
 * vB_Api_Content_Poll
 *
 * @package vBApi
 * @author ebrown
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Library_Content_Poll extends vB_Library_Content_Text
{
	//override in client- the text name
	protected $contenttype = 'vBForum_Poll';

	//The table for the type-specific data.
	protected $tablename = array('poll', 'text');

	//When we parse the page.
	protected $bbcode_parser = false;

	//Whether we change the parent's text count- 1 or zero
	protected $textCountChange = 1;

	protected $tableFields = array();
	//for spam checking
	protected $spamType = false;
	protected $spamKey = false;
	protected $akismet;

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

	/*** Returns the node content as an associative array with fullcontent
	 *	@param	mixed	integer or array of integers=The id in the primary table
	 *	@param array permissions
	 *
	 * 	 *	@param bool	appends to the content the channel routeid and title, and starter route and title the as an associative array
	 ***/
	public function getFullContent($nodes, $permissions = false)
	{
		$results = parent::getFullContent($nodes, $permissions);
		return $this->addContentInfo($results);
	}

	protected function sendEmailNotification($data)
	{
		$node = $this->nodeApi->getNode($data['aboutid']);

		if ($data['about'] == vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_VOTE)
		{
			$maildata = vB_Api::instanceInternal('phrase')->
				fetchEmailPhrases('vote', array(
					$data['username'],
					vB_Api::instanceInternal('user')->fetchUserName(vB::getCurrentSession()->get('userid')),
					vB5_Route::buildUrl($node['routeid'] . '|nosession|fullurl', $node),
					vB::getDatastore()->getOption('bbtitle'),
					),
					array(vB::getDatastore()->getOption('bbtitle')
				)
			);
		}
		else
		{
			$currentNode = $this->getContent($data['contentnodeid']);
			$currentNode = $currentNode[$data['contentnodeid']];

			$routeInfo = array('nodeid' => $node['starter']);

			$maildata = vB_Api::instanceInternal('phrase')->
				fetchEmailPhrases('reply', array(
					$data['username'],
					$currentNode['userinfo']['username'],
					$data['about'],
					//vB_Api::instanceInternal('phrase')->fetch(array('thread')) @TODO: Need to get those phrases
					($currentNode['starter'] == $currentNode['parentid'] ? 'thread' : 'post'),
					vB5_Route::buildUrl($node['routeid'] . '|nosession|fullurl', $routeinfo, array('goto' => 'newpost')),
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

	protected function addContentInfo($results)
	{
		$results = parent::addContentInfo($results);

		$checkvoted = array();
		try
		{
			$checkvoted = $this->checkVotedMultiple(array_keys($results));
		}
		catch (vB_Exception_Api $e)
		{
			// Ignore for guest user
		}

		//the key of for each node is the nodeid, fortunately
		foreach ($results AS $key => &$record)
		{
			if (!empty($record['options']) AND !is_array($record['options']))
			{
				$record['options'] = @unserialize($record['options']);
			}

			// Check if the poll is timeout
			if (isset($record['timeout']))
			{
				$record['istimeout'] = ($record['timeout'] AND $record['timeout'] < vB::getRequest()->getTimeNow());

				// For timeout input
				if ($record['timeout'])
				{
					$record['timeoutstr'] = vbdate("m/d/Y H:i", $record['timeout']);
				}
				else
				{
					$record['timeoutstr'] = '';
				}
			}
			else
			{
				$record['timeoutstr'] = '';
				$record['istimeout'] = false;
			}

			// Check if it's voted already
			// TODO: we need to improve this to consider voting permissions for guests.
			$record['voted'] = !empty($checkvoted[$key]) ? $checkvoted[$key] : false;
		}

		return $results;
	}

	/*** updates a record
	 *
	 *	@param	mixed		array of nodeid's
	 *	@param	mixed		array of permissions that should be checked.
	 *
	 * 	@return	boolean
	 ***/
	public function update($nodeid, $data, $convertWysiwygTextToBbcode = true)
	{
		$loginuser = &vB::getCurrentSession()->fetch_userinfo();
		$usercontext = &vB::getUserContext($loginuser['userid']);
		if (!$usercontext->getCanModerate($nodeid))
		{
			throw new Exception('no_permission');
		}
		$existing = $this->nodeApi->getNode($nodeid);

		$this->checkPollOptions($data);

		$options = $data['options'];
		$oldnode = $this->getContent($nodeid);
		$oldnode = $oldnode[$nodeid];

		// skip the index in the parent and do it here so it can include the options
		$data['noIndex'] = true;

		if (isset($data['parseurl']))
		{
			$parseurl = $data['parseurl'];
			if ($parseurl)
			{
				require_once(DIR . '/includes/functions_newpost.php');
			}
		}

		unset($data['options'], $data['parseurl']);

		$result = parent::update($nodeid, $data, $convertWysiwygTextToBbcode);

		$oldoptionids = array();
		$optionids = array();
		foreach ($oldnode['options'] as $option)
		{
			$oldoptionids[$option['polloptionid']] = $option['polloptionid'];
		}

		// Save poll options
		foreach ($options as $option)
		{
			if (isset($parseurl) AND $parseurl)
			{
				$option['title'] = convert_url_to_bbcode($option['title']);
			}

			if ($option['polloptionid'])
			{
				// Check if the polloption belongs to the poll (node)
				$polloption = $this->assertor->getRow('vBForum:polloption', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'nodeid' => $nodeid,
				));

				if ($polloption['nodeid'] == $nodeid)
				{
					$votes = $this->assertor->getRows('vBForum:pollvote', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						'polloptionid' => $option['polloptionid']
					));

					$voters = array();
					foreach ($votes as $vote)
					{
						if (!in_array($vote['userid'], $voters))
						{
							$voters[] = $vote['userid'];
						}
					}

					$this->assertor->assertQuery('vBForum:polloption', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
						'title' => $option['title'],
						'votes' => count($votes),
						'voters' => serialize($voters),
						vB_dB_Query::CONDITIONS_KEY => array(
							'polloptionid' => $option['polloptionid']
						)
					));

					$optionids[$option['polloptionid']] = $option['polloptionid'];
				}
				else
				{
					throw new Exception('invalidid');
				}

			}
			else
			{
				// Insert new option
				$this->assertor->assertQuery('vBForum:polloption', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
					'nodeid' => $nodeid,
					'title' => $option['title'],
				));

			}
		}

		$optionstoremove = array_diff($oldoptionids, $optionids);
		if ($optionstoremove)
		{
			$this->assertor->assertQuery('vBForum:polloption', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'polloptionid' => $optionstoremove,
			));

			//delete pollvotes
			$this->assertor->assertQuery('vBForum:pollvote', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'polloptionid' => $optionstoremove,
			));
		}

		$this->updatePollCache($nodeid);
		$this->nodeApi->clearCacheEvents(array($nodeid, $existing['parentid']));
		// do the indexing after the options are added
		vB_Api::instance('Search')->index($nodeid);

		return $result;
	}

	/*** Permanently deletes a node
	 *	@param	integer	The nodeid of the record to be deleted
	 *
	 *	@return	boolean
	 ***/
	public function delete($nodeid)
	{
		$existing =	$this->nodeApi->getNode($nodeid);

		//do the delete
		parent::delete($nodeid);

		//delete polloptions
		$this->assertor->assertQuery('vBForum:polloption', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'nodeid' => $nodeid,
		));

		//delete pollvotes
		$this->assertor->assertQuery('vBForum:pollvote', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'nodeid' => $nodeid,
		));
		$this->nodeApi->clearCacheEvents(array($nodeid, $existing['parentid']));
	}

	/*** Adds a new node.
	 *
	 *	@param	mixed		Array of field => value pairs which define the record.
	 *  @param	array		Array of options for the content being created.
	 *						Available options include:
	 *
	 * 	@return	integer		the new nodeid
	 ***/
	public function add($data, array $options = array(), $convertWysiwygTextToBbcode = true)
	{
		$loginuser = &vB::getCurrentSession()->fetch_userinfo();

		$this->checkPollOptions($data);

		// Add the poll options (answers) to the standard content add method $options array
		$options = array_merge($data['options'], $options);

		// Keep an array of *only* the poll options, without the other options in the standard array
		$pollOptions = $data['options'];

		if (isset($data['parseurl']))
		{
			$parseurl = $data['parseurl'];
			if ($parseurl)
			{
				require_once(DIR . '/includes/functions_newpost.php');
			}
		}

		unset($data['options'], $data['parseurl']);

		// skip the index in the parent and do it here so it can include the options
		$data['noIndex'] = true;

		$nodeid = parent::add($data, $options, $convertWysiwygTextToBbcode);

		// Save poll options
		foreach ($pollOptions AS $option)
		{
			if (isset($parseurl) AND $parseurl)
			{
				$option['title'] = convert_url_to_bbcode($option['title']);
			}

			// Insert new option
			$this->assertor->assertQuery('vBForum:polloption', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'nodeid' => $nodeid,
				'title' => $option['title'],
			));
		}

		$this->updatePollCache($nodeid);
		// do the indexing after the options are added
		$this->nodeApi->clearCacheEvents(array($nodeid, $data['parentid']));
		vB_Api::instance('Search')->index($nodeid);

		return $nodeid;
	}

	protected function checkPollOptions($data)
	{
		$vboptions = vB::getDatastore()->get_value('options');

		if (empty($data['options']) OR !is_array($data['options']))
		{
			throw new Exception('no_options_specified');
		}
		if ($vboptions['maxpolloptions'] > 0 AND count($data['options']) > $vboptions['maxpolloptions'])
		{
			throw new Exception('too_many_options');
		}

		foreach ($data['options'] as &$option)
		{
			if (!$option['title'])
			{
				unset($option);
			}
			elseif ($vboptions['maxpolllength'] AND vB_String::vbStrlen($option['title']) > $vboptions['maxpolllength'])
			{
				throw new Exception('option_title_toolong');
			}
		}
	}

	public function vote($options, $userid = false)
	{
		if (!$userid)
		{
			$userid = vB::getCurrentSession()->get('userid');
		}

		foreach ($options as $option)
		{
			if (!$option)
			{
				throw new Exception('invalidid');
			}

			// Insert Vote
			$this->assertor->assertQuery('vBForum:pollvote', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'nodeid' => $option['nodeid'],
				'polloptionid' => $option['polloptionid'],
				'userid' => $userid,
				'votedate' => vB::getRequest()->getTimeNow(),
			));

			$voters = @unserialize($option['voters']);

			if (!$voters)
			{
				$voters = array();
			}
			$votes = $option['votes'];
			if (!in_array($userid, $voters))
			{
				$voters[] = $userid;
				$votes++;
			}

			// Update option
			$this->assertor->assertQuery('vBForum:polloption', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'voters' => serialize($voters),
				'votes' => $votes,
				vB_dB_Query::CONDITIONS_KEY => array(
					'polloptionid' => $option['polloptionid']
				)
			));
			$nodeid = $option['nodeid'];
		}

		// All options should be in a same poll
		$this->updatePollCache($nodeid, true);

		//Send a notification.
		$poll = $this->nodeApi->getNode($nodeid);
		$notifications[] = array('about' => vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_VOTE,
			'aboutid' => $nodeid, 'userid' => $poll['userid']);

		//If this node is not the starter, we need to send a notification to the starter also.
		// Note: this currently does not happen, but it's for when someone votes on a poll that was posted as a reply to another topic
		if ($poll['starter'] != $nodeid)
		{
			$starterid = $poll['starter'];
			$starter = $this->nodeApi->getNode($starterid);
			$notifications[] = array('about' => vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_VOTEREPLY,
				'aboutid' => $starterid, 'userid' => $starter['userid']);
		}

		if (!empty($notifications) AND empty($options['skipNotifications']))
		{
			$this->sendNotifications($notifications);
		}

		return $nodeid;
	}

	public function updatePollCache($nodeid, $updatelastvote = false)
	{
		// Update poll table's options
		// Get options
		$options = $this->assertor->getRows('vBForum:polloption', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'nodeid' => $nodeid
		));
		$optionstosave = array();
		$totalvotes = 0;
		foreach ($options as $option)
		{
			$option['voters'] = @unserialize($option['voters']);
			$totalvotes += $option['votes'];
			$optionstosave[$option['polloptionid']] = $option;
		}
		unset($options, $option);
		foreach ($optionstosave as &$option)
		{
			if ($totalvotes)
			{
				$option['percentage'] = number_format($option['votes'] / $totalvotes * 100, 2);
			}
			else
			{
				$option['percentage'] = 0;
			}
		}

		$data = array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'options' => serialize($optionstosave),
			'votes' => $this->assertor->getField('vBForum:poll_fetchvotes', array(
							vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
							'nodeid' => $nodeid,
						)),
			vB_dB_Query::CONDITIONS_KEY => array(
				'nodeid' => $nodeid
			)
		);

		if ($updatelastvote)
		{
			$data['lastvote'] = vB::getRequest()->getTimeNow();
		}

		$this->assertor->assertQuery('vBForum:poll', $data);
	}

	public function getIndexableFromNode($content, $recursive = true)
	{
		$indexableContent['title'] = $content['title'];
		$indexableContent['rawtext'] = $content['rawtext'];
		$option_titles = array();
		foreach ((array)$content['options'] as $option)
		{
			array_push($option_titles,$option['title']);
		}
		$indexableContent['options'] = implode(',',$option_titles);

		return $indexableContent;
	}

	public function getQuotes($nodeids)
	{
		//Per Product, we just quote the text content (but this may change in the future)
		//If and when the requirement changes to include the non-text content, don't call the parent method and then implement it here
		return parent::getQuotes($nodeids);
	}

	protected function checkVoted($nodeid)
	{
		$result = $this->checkVotedMultiple(array($nodeid));
		return $result[$nodeid];
	}

	protected function checkVotedMultiple($nodeids)
	{
		static $checked = array();
		$notfound = array();
		$return = array();

		foreach ($nodeids as $nodeid)
		{
			if (isset($checked[$nodeid]))
			{
				$return[$nodeid] = $checked[$nodeid];
			}
			else
			{
				$notfound[] = $nodeid;
			}
		}

		if (empty($notfound))
		{
			return $return;
		}

		$loginuser = &vB::getCurrentSession()->fetch_userinfo();
		if (!$loginuser['userid'])
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		$uservoteinfo = vB::getDbAssertor()->getRows('vBForum:pollvote', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'userid' => $loginuser['userid'],
			'nodeid' => $notfound,
		), false, 'nodeid');

		foreach ($nodeids as $nodeid)
		{
			if (!empty($uservoteinfo[$nodeid]))
			{
				$return[$nodeid] = true;
			}
			else
			{
				$return[$nodeid] = false;
			}
			$checked[$nodeid] = $return[$nodeid];
		}

		return $return;
	}

	/**
	 * Adds content info to $result so that merged content can be edited.
	 * @param array $result
	 * @param array $content
	 */
	public function mergeContentInfo(&$result, $content)
	{
		parent::mergeContentInfo($result, $content);

		$fields = array('title', 'options', 'timeout', 'timeoutstr', 'multiple', 'public');

		$missing = array_diff($fields, array_keys($content));
		if (!empty($missing))
		{
			throw new vB_Exception_Api('Invalid content info.');
		}

		foreach ($fields as $field)
		{
			$result[$field] = $content[$field];
		}
	}

	/**
	 * Performs the merge of content and updates the node.
	 * @param type $data
	 * @return type
	 */
	public function mergeContent($data)
	{
		// modify tables records (only one record will be modified due to constraints)
		$sources = array_diff($data['mergePosts'], array($data['destnodeid']));

		$db = vB::getDbAssertor();
		$db->update('poll', array('nodeid' => $data['destnodeid']), array(array('field' => 'nodeid', 'value' => $sources)));
		$db->update('vBForum:node', array('contenttypeid' => $this->contenttypeid), array('nodeid' => $data['destnodeid']));

		// get videoitems
		$polloptions = array();
		foreach($data AS $key => $value)
		{
			if (preg_match('#^polloptions\[([\d]+)#', $key, $matches))
			{
				$polloptions[] = array(
					'polloptionid' => intval($matches[1]),
					'title' => trim($value),
				);
			}
			else if (preg_match('^polloptions\[new', $key, $matches))
			{
				foreach ($value as $option)
				{
					$polloptions[]['title'] = trim($option);
				}
			}
		}

		$pollData = array(
			'title' => $data['title'],
			'rawtext' => $data['text'],
			'userid' => $data['destauthorid'],
			'urlident' => vB_String::getUrlIdent($data['title']),
			'options' => $polloptions,
			'multiple' => $data['multiple'],
			'public' => $data['public'],
			'parseurl' => $data['parseurl'],
			'timeout' => strtotime($data['timeout'])
		);

		return vB_Api::instanceInternal('content_poll')->update($data['destnodeid'], $pollData);
	}
}
