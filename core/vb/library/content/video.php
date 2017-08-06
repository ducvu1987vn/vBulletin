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
 * vB_Api_Content_Video
 *
 * @package vBApi
 * @author ebrown
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Library_Content_Video extends vB_Library_Content_Text
{
	//override in client- the text name
	protected $contenttype = 'vBForum_Video';

	//The table for the type-specific data.
	protected $tablename = array('video', 'text');

	protected $providers = array();


	protected function __construct()
	{
		$this->library = vB_Library::instance('Content_Attach');
		parent::__construct();
	}

	/*** Returns the node content as an associative array with fullcontent
	 *	@param	integer	The id in the primary table
	 *	@param array permissions
	 *
	 * 	 *	@param bool	appends to the content the channel routeid and title, and starter route and title the as an associative array
	 ***/
	public function getFullContent($nodeid, $permissions = false)
	{
		if (empty($nodeid))
		{
			return array();
		}

		$results = parent::getFullContent($nodeid, $permissions);
		$videoitems = $this->fetchVideoItems($nodeid);

		foreach ($results as $key => $result)
		{
			$results[$key]['items'] = array();

		}

		foreach ($videoitems as $videoitem)
		{
			$results[$videoitem['nodeid']]['items'][] = $videoitem;
		}

		foreach ($results as $key => $result)
		{
			if (empty($results['items']))
			{
				$results[$key]['videocount'] = 0;
			}
			else
			{
				$results[$key]['videocount'] = count($results['items']);
			}
		}
		return $results;
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

			$routeInfo = array('nodeid' => $node['starter']);

			$maildata = vB_Api::instanceInternal('phrase')->
				fetchEmailPhrases('reply', array(
					$data['username'],
					$currentNode['userinfo']['username'],
					$data['about'],
					//vB_Api::instanceInternal('phrase')->fetch(array('thread')) @TODO: Need to get those phrases
					($currentNode['starter'] == $currentNode['parentid'] ? 'thread' : 'post'),
					vB5_Route::buildUrl($node['routeid'] . '|nosession|fullurl', $routeInfo),
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


	/*** updates a record
	 *
	 *	@param	mixed		array of nodeid's
	 *	@param	mixed		array of permissions that should be checked.
	 *
	 * 	@return	array
	 ***/
	public function update($nodeid, $data, $convertWysiwygTextToBbcode = true)
	{
		// TODO: Permission check
//		$loginuser = &vB::getCurrentSession()->fetch_userinfo();
//		$usercontext = &vB::getUserContext($loginuser['userid']);
//		if (!$usercontext->hasPermission('forumpermissions', 'canpostvideo'))
//		{
//			throw new Exception('no_permission');
//		}
		$currentNode = vB_Library::instance('Node')->getNodeBare($nodeid);

		if ($currentNode['contenttypeid'] != vB_Types::instance()->getContentTypeID($this->contenttype))
		{
			parent::changeContentType($nodeid, $currentNode['contenttypeid'], $this->contenttype);
			$data['contenttypeid'] = vB_Types::instance()->getContentTypeID($this->contenttype);
		}

		$newvideoitems = $this->checkVideoData($data);

		unset($data['videoitems']);

		$result = parent::update($nodeid, $data, $convertWysiwygTextToBbcode);

		// Get a list of current video items
		$videoitems = $this->fetchVideoItems($nodeid);

		$oldvideoitemids = array();
		$newvideoitemids = array();
		foreach ($videoitems as $item)
		{
			$oldvideoitemids[$item['videoitemid']] = $item['videoitemid'];
		}
		foreach ($newvideoitems as $item)
		{
			$newvideoitemids[$item['videoitemid']] = $item['videoitemid'];
			$newvideoitemdata[$item['videoitemid']] = $item;
		}

		$itemstoremove = array_diff($oldvideoitemids, $newvideoitemids);
		$itemstoupdate = array_intersect($oldvideoitemids, $newvideoitemids);
		$itemstoinsert = array_diff($newvideoitemids, $oldvideoitemids);

		// Save video items
		foreach ($itemstoinsert as $itemid)
		{
			$this->assertor->assertQuery("videoitem", array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'nodeid' => $nodeid,
				'provider' => $newvideoitemdata[$itemid]['provider'],
				'code' => $newvideoitemdata[$itemid]['code'],
				'url' => $newvideoitemdata[$itemid]['url'],
			));
		}
		foreach ($itemstoupdate as $itemid)
		{
			$this->assertor->assertQuery("videoitem", array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'nodeid' => $nodeid,
				'provider' => $newvideoitemdata[$itemid]['provider'],
				'code' => $newvideoitemdata[$itemid]['code'],
				'url' => $newvideoitemdata[$itemid]['url'],
				vB_dB_Query::CONDITIONS_KEY => array(
					'videoitemid' => $itemid,
				)
			));
		}
		foreach ($itemstoremove as $itemid)
		{
			$this->assertor->assertQuery("videoitem", array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'videoitemid' => $itemid,
			));
		}

		// do the indexing after the options are added
		vB_Api::instance('Search')->index($nodeid);
		vB_Cache::instance(vB_Cache::CACHE_FAST)->event("nodeChg_$nodeid");
		vB_Cache::instance()->event("nodeChg_$nodeid");

		return $result;
	}

	/*** Permanently deletes a node
	 *	@param	integer	The nodeid of the record to be deleted
	 *
	 *	@return	boolean
	 ***/
	public function delete($nodeid)
	{
		//We need to update the parent counts, but first we need to get the status
		$node = $this->assertor->getRow('vBForum:node', array('nodeid' => $nodeid));
		//We have to get this before we delete
		if ($node['showpublished'])
		{
			$parents = vB_Library::instance('Node')->getParents($nodeid);
		}

		//do the delete
		parent::delete($nodeid);

		//delete videoitems
		$this->assertor->assertQuery('videoitem', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'nodeid' => $nodeid,
		));
		vB_Cache::instance(vB_Cache::CACHE_FAST)->event("nodeChg_$nodeid");
		vB_Cache::instance()->event("nodeChg_$nodeid");

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
		// TODO: Permission check
//		$loginuser = &vB::getCurrentSession()->fetch_userinfo();
//		$usercontext = &vB::getUserContext($loginuser['userid']);
//		if (!$usercontext->hasPermission('forumpermissions', 'canpostvideo'))
//		{
//			throw new Exception('no_permission');
//		}

		$videoitems = $this->checkVideoData($data);

		unset($data['videoitems']);

		if (!$videoitems)
		{
			throw new vB_Exception_Api("invalid_videoitems");
		}

		$nodeid = parent::add($data, $options, $convertWysiwygTextToBbcode);

		// Save video items
		foreach ($videoitems as $item)
		{
			$this->assertor->assertQuery("videoitem", array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'nodeid' => $nodeid,
				'provider' => $item['provider'],
				'code' => $item['code'],
				'url' => $item['url'],
			));
		}

		// do the indexing after the options are added
		$this->nodeApi->clearCacheEvents(array($nodeid, $data['parentid']));
		vB_Api::instance('Search')->index($nodeid);
		return $nodeid;
	}

	/**
	 * Get information from video's URL.
	 * This method makes use of bbcode_video table to get provider information
	 * @param $url
	 * @return array|bool Video data. False if the url is not supported or invalid
	 */
	public function getVideoFromUrl($url)
	{
		static $scraped = 0;

		$vboptions = vB::getDatastore()->get_value('options');

		if (!$this->providers)
		{
			$bbcodes = $this->assertor->assertQuery("video_fetchproviders", array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED
			));
			foreach ($bbcodes as $bbcode)
			{
				$this->providers["$bbcode[tagoption]"] = $bbcode;
			}
		}

		if (!empty($this->providers))
		{
			$match = false;
			foreach ($this->providers AS $provider)
			{
				$addcaret = ($provider['regex_url'][0] != '^') ? '^' : '';
				if (preg_match('#' . $addcaret . $provider['regex_url'] . '#si', $url, $match))
				{
					break;
				}
			}
			if ($match)
			{
				if (!$provider['regex_scrape'] AND $match[1])
				{
					$data = array(
						'provider' => $provider['tagoption'],
						'code' => $match[1],
						'url' => $url,
					);
				}
				else if ($provider['regex_scrape'] AND $vboptions['bbcode_video_scrape'] > 0 AND $scraped < $vboptions['bbcode_video_scrape'])
				{
					require_once(DIR . '/includes/functions_file.php');
					$result = fetch_body_request($url);
					if (preg_match('#' . $provider['regex_scrape'] . '#si', $result, $scrapematch))
					{
						$data = array(
							'provider' => $provider['tagoption'],
							'code' => $scrapematch[1],
							'url' => $url,
						);
					}
					$scraped++;
				}
			}

			if (!empty($data))
			{
				return $data;
			}
			else
			{
				return false;
			}
		}

		return false;
	}

	public function getQuotes($nodeids)
	{
		//Per Product, we just quote the text content (but this may change in the future)
		//If and when the requirement changes to include the non-text content, don't call the parent method and then implement it here
		return parent::getQuotes($nodeids);
	}

	/**
	 * Check video node input data and return an array of valid video items
	 *
	 * @throws vB_Exception_Api
	 * @param $data
	 * @return void
	 */
	protected function checkVideoData($data)
	{
		if (!$data['videoitems'] OR !is_array($data['videoitems']))
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}

		$return = array();
		foreach ($data['videoitems'] as $video)
		{
			if (!$video['url']) continue;

			if (!isset($video['videoitemid']))
			{
				$video['videoitemid'] = 0;
			}

			if ($info = $this->getVideoFromUrl($video['url']))
			{

				$return[] = array(
					'videoitemid' => $video['videoitemid'],
					'url' => $video['url'],
					'provider' => $info['provider'],
					'code' => $info['code'],
				);
			}
		}

		if ($return)
		{
			return $return;
		}
		else
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}
	}

	/**
	 * Fetch video items by nodeid
	 *
	 * @param $nodeid Node ID
	 * @return array Video items
	 */
	protected function fetchVideoItems($nodeid)
	{
		return $this->assertor->getRows("vBForum:videoitem", array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'nodeid' => $nodeid
		));
	}

	/**
	 * Adds content info to $result so that merged content can be edited.
	 * @param array $result
	 * @param array $content
	 */
	public function mergeContentInfo(&$result, $content)
	{
		parent::mergeContentInfo($result, $content);

		if (!isset($content['items']))
		{
			throw new vB_Exception_Api('Invalid content info.');
		}

		foreach($content['items'] as $video)
		{
			$result['items'][] = $video;
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
		$db->update('video', array('nodeid' => $data['destnodeid']), array(array('field' => 'nodeid', 'value' => $sources)));
		$db->update('vBForum:node', array('contenttypeid' => $this->contenttypeid), array('nodeid' => $data['destnodeid']));

		// get videoitems
		$videoitems = array();
		foreach($data AS $key => $value)
		{
			if (preg_match('#^videoitems\[([\d]+)#', $key, $matches))
			{
				$videoitems[] = array(
					'videoitemid' => intval($matches[1]),
					'url' => $value['url'],
				);
			}
			else if (preg_match('^videoitems\[new', $key, $matches))
			{
				foreach ($value as $video)
				{
					$videoitems[]['url'] = $video['url'];
				}
			}
		}

		$videoData = array(
			'userid' => $data['destauthorid'],
			'rawtext' => $data['text'],
			'videoitems' => $videoitems
		);

		return vB_Api::instanceInternal('content_video')->update($data['destnodeid'], $videoData);
	}
}
