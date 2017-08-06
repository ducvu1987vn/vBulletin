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
 * vB_Api_Content_Gallery
 *
 * @package vBApi
 * @author ebrown
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Library_Content_Gallery extends vB_Library_Content_Text
{
	//override in client- the text name
	protected $contenttype = 'vBForum_Gallery';

	//The table for the type-specific data.
	protected $tablename = array('gallery', 'text');


	//Whether we change the parent's text count- 1 or zero
	protected $textCountChange = 1;

	//Does this content show author signature?
	protected $showSignature = true;


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
		$nodeid = parent::add($data, $options, $convertWysiwygTextToBbcode);

		// @todo is this not already done in the vB_Library_Content_Text class?
		// Obtain and set generic conversation route
		$conversation = $this->getConversationParent($nodeid);
		$routeid = vB_Api::instanceInternal('route')->getChannelConversationRoute($conversation['parentid']);
		$this->assertor->update('vBForum:node', array('routeid' => $routeid), array('nodeid' => $nodeid));

		$this->nodeApi->clearCacheEvents(array($nodeid, $data['parentid']));

		return $nodeid;
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
		$contentInfo = parent::getFullContent($nodes, $permissions);
		return $this->addPhotoInfo($contentInfo, $nodes);
	}

	/** Get and cache node data
	*
	*	@param	mixed	array of nodeids
	*
	*	@return mixed	array of photo table records
	***/
	protected function getPhotos($nodeids)
	{
		if (!is_array($nodeids))
		{
			$nodeids = array($nodeids);
		}

		//First let's see what we have in cache.
		$found = array();
		$notfound = array();
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);

		foreach ($nodeids AS $nodeid)
		{
			$photos = $cache->read("vBPhoto_$nodeid");

			if (!empty($photos))
			{
				$found = array_merge($found, $photos);
			}
			else if ($photos !== NULL)
			{
				$notfound[$nodeid] = array() ;
			}
		}

		if (!empty($notfound))
		{
			$photos = vB::getDbAssertor()->assertQuery('vBForum:getPhotos', array('parentid' => array_keys($notfound)));

			foreach($photos AS $photo)
			{
				$found[] = $photo;
				$notfound[$photo['parentid']][$photo['nodeid']] = $photo;
			}

			//cache what we've found- but not false. Use empty array so we can distinguish
			// cached data from uncached.
			foreach ($notfound AS $parentId => $photos)
			{
				$hashKey = "vBPhoto_$parentId";

				if (empty($photo))
				{
					$photo = array();
				}
				$cache->write($hashKey, $photos, 1440, "nodeChg_$nodeid");
			}
		}

		return $found;


	}

	protected function addPhotoInfo($contentInfo, $nodes)
	{
		$photos = $this->getPhotos($nodes);
		//the key of for each node is the nodeid, fortunately
		foreach ($photos AS $photo)
		{
			//Need to add the photo to the right node.
			if (isset($photo['parentid']) && isset($contentInfo[$photo['parentid']]))
			{
				if (empty($contentInfo[$photo['parentid']]['firstphoto']))
				{
					$contentInfo[$photo['parentid']]['firstphoto'] = $photo;
				}
				//We have a match
				if (!isset($contentInfo[$photo['parentid']]['photo']))
				{
					$contentInfo[$photo['parentid']]['photo'] = array();
				}

				$photo['shortcaption'] = substr($photo['caption'],0,10);
				$contentInfo[$photo['parentid']]['photo'][$photo['nodeid']] = $photo;
			}
		}

		if (is_array($nodes))
		{
			foreach ($nodes as $node)
			{
				if (empty($contentInfo[$node]))
				{
					continue;
				}
				if (empty($contentInfo[$node]['photo']))
				{
					$contentInfo[$node]['photocount'] = 0;
				}
				else
				{
					$contentInfo[$node]['photocount'] = count($contentInfo[$node]['photo']);
				}
				//add 3 photo previews
				if (isset($contentInfo[$node]['photo']))
				{
					$contentInfo[$node]['photopreview'] = ($contentInfo[$node]['photocount'] > 3) ? array_slice($contentInfo[$node]['photo'], 0, 3) : $contentInfo[$node]['photo'];
				}
			}
		}
		elseif (!empty($contentInfo[$nodes]))
		{
			if (empty($contentInfo[$nodes]['photo']))
			{
				$contentInfo[$nodes]['photocount'] = 0;
				$contentInfo[$nodes]['photopreview'] = array();
			}
			else
			{
				$contentInfo[$nodes]['photocount'] = count($contentInfo[$nodes]['photo']);
				//add 3 photo previews
				$contentInfo[$nodes]['photopreview'] = ($contentInfo[$nodes]['photocount'] > 3) ? array_slice($contentInfo[$nodes]['photo'], 0, 3) : $contentInfo[$nodes]['photo'];
			}
		}

		return $contentInfo;
	}

	/**
	 * Adds content info to $result so that merged content can be edited.
	 * @param array $result
	 * @param array $content
	 */
	public function mergeContentInfo(&$result, $content)
	{
		parent::mergeContentInfo($result, $content);

		if (!isset($content['photo']))
		{
			throw new vB_Exception_Api('Invalid content info.');
		}

		foreach($content['photo'] as $photo)
		{
			$result['photo'][$photo['nodeid']] = $photo;
		}

		$result['photocount'] = count($result['photo']);
	}

	/**
	 * Performs the merge of content and updates the node.
	 * @param type $data
	 * @return type
	 */
	public function mergeContent($data)
	{
		// modify tables records (only one record will be modified due to constraints)
		$db = vB::getDbAssertor();

		$nodes = vB_Api::instanceInternal('node')->getContentForNodes(array($data['destnodeid']));
		$destNode = array_pop($nodes);
		if ($destNode['contenttypeclass'] != 'Gallery')
		{
			$db->insert('gallery', array('nodeid' => $data['destnodeid']));
		}

		$db->update('vBForum:node', array('contenttypeid' => $this->contenttypeid), array('nodeid' => $data['destnodeid']));

		// get photos
		$filedataids = array();
		if (!empty($data['filedataid[]']))
		{
			if (!is_array($data['filedataid[]']))
			{
				$data['filedataid[]'] = array($data['filedataid[]']);
			}

			foreach ($data['filedataid[]'] AS $filedataid)
			{
				$title_key = "title_$filedataid";
				$filedataids[$filedataid] = (isset($data[$title_key])) ? $data[$title_key] : '';
			}
		}

		$data['rawtext'] = $data['text'];

		return $this->updateFromWeb($data['destnodeid'], $data, $filedataids);
	}

	public function getQuotes($nodeids)
	{
		//Per Product, we just quote the text content (but this may change in the future)
		//If and when the requirement changes to include the non-text content, don't call the parent method and then implement it here
		return parent::getQuotes($nodeids);
	}
}
