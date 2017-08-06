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
 * vB_Api_Content_link
 *
 * @package vBApi
 * @author xiaoyu
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Library_Content_Link extends vB_Library_Content_Text
{
	//override in client- the text name
	protected $contenttype = 'vBForum_Link';

	//The table for the type-specific data.
	protected $tablename = array('link', 'text');

	protected $providers = array();

	/**
	 * Checks if user can delete a given link
	 *
	 * @param 	int		User Id
	 *
	 * @param	int		Link Id
	 *
	 * @return boolean value to indicate whether user can or not delete link
	 */
	protected function canDeleteLink($userId, $nodeid, $fileDataRecord)
	{
		/** moderators can delete links */
		if (vB::getUserContext()->getChannelPermission("moderatorpermissions", "canmoderateattachments", $nodeid))
		{
			return true;
		}

		return false;
	}

	protected function sendEmailNotification($data)
	{
		if ($data['about'] == vB_Library_Content_Privatemessage::NOTIFICATION_TYPE_VM)
		{
			$node = $this->getContent($data['contentnodeid']);
			$node = $node[$data['contentnodeid']];
			$routeInfo = array('nodeid' => $node['starter']);

			$maildata = vB_Api::instanceInternal('phrase')->
				fetchEmailPhrases('visitormessage', array(
					$data['username'],
					$node['userinfo']['username'],
					vB5_Route::buildUrl('visitormessage|nosession|fullurl', $routeInfo),
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

			$maildata = vB_Api::instanceInternal('phrase')->
				fetchEmailPhrases('reply', array(
					$data['username'],
					$currentNode['userinfo']['username'],
					$data['about'],
					//vB_Api::instanceInternal('phrase')->fetch(array('thread')) @TODO: Need to get those phrases
					($currentNode['starter'] == $currentNode['parentid'] ? 'thread' : 'post'),
					vB5_Route::buildUrl($node['routeid'] . '|nosession|fullurl', $node, array('goto' => 'newpost')),
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

	/**
	 * @param	int		Link ID
	 *
	 * @return	mixed	Filedata Record
	 */
	protected function fetchFileDataRecord($nodeid)
	{
		$link = $this->assertor->getRow("vBForum:link", array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						'nodeid' => $nodeid ));

		$fileDataRecord = null;
		if($link['filedataid'])
		{
			$fileDataRecord = $this->assertor->getRow("vBForum:filedata", array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						'filedataid' => $link['filedataid'] ));
		}

		return $fileDataRecord;
	}

	/**	Validate filedata record
	 * @param	int	fileDataId
	 *
	 * @param	int UserId
	 *
	 * @return	boolean	Indicate if fileData is valid for the user
	 */
	protected function validateFileData($fileDataId)
	{
		$fileData = $this->assertor->getRow("vBForum:filedata", array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						'filedataid' => $fileDataId ));

		if (empty($fileData))
		{
			throw new vB_Exception_Api('invalid_filedata');
		}

		if ($fileData["userid"] != vB::getCurrentSession()->get('userid'))
		{
			throw new vB_Exception_Api('invalid_user_filedata');
		}

		return true;
	}


	/*** Permanently deletes a node
	 *	@param	integer	The nodeid of the record to be deleted
	 *
	 *	@return	boolean
	 ***/
	public function delete($nodeid)
	{
		/** Get filedata refcount */
		$fileDataRecord = $this->fetchFileDataRecord($nodeid);
		if($fileDataRecord AND !$this->canDeleteLink(vB::getCurrentSession()->get('userid'), $nodeid, $fileDataRecord))
		{
			throw new vB_Exception_Api('no_delete_permissions');
		}

		if ($result = parent::delete($nodeid))
		{
			$refCount = $fileDataRecord["refcount"] - 1;
			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				vB_dB_Query::CONDITIONS_KEY =>  array('filedataid' => $fileDataRecord["filedataid"]), 'refcount' => $refCount);
			$this->assertor->assertQuery("vBForum:filedata", $data);

			return $result;
		}
		else
		{
			return false;
		}
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
		/** Validate Filedata */
		if (!empty($data["filedataid"]))
		{
			$this->validateFileData($data['filedataid']);
		}

		$nodeid = parent::add($data, $options, $convertWysiwygTextToBbcode);

		// do the indexing
		$this->nodeApi->clearCacheEvents(array($nodeid, $data['parentid']));
		vB_Api::instance('Search')->index($nodeid);

		return $nodeid;
	}

	public function update($nodeid, $data, $convertWysiwygTextToBbcode = true)
	{
		$currentNode = vB_Library::instance('Node')->getNodeBare($nodeid);

		if ($currentNode['contenttypeid'] != vB_Types::instance()->getContentTypeID($this->contenttype))
		{
			parent::changeContentType($nodeid, $currentNode['contenttypeid'], $this->contenttype);
			$data['contenttypeid'] = vB_Types::instance()->getContentTypeID($this->contenttype);
		}

		return parent::update($nodeid, $data, $convertWysiwygTextToBbcode);
	}

	/**
	 * Adds content info to $result so that merged content can be edited.
	 * @param array $result
	 * @param array $content
	 */
	public function mergeContentInfo(&$result, $content)
	{
		parent::mergeContentInfo($result, $content);

		$fields = array('filedataid', 'url', 'url_title', 'meta');

		$missing = array_diff($fields, array_keys($content));
		if (!empty($missing))
		{
			throw new vB_Exception('Invalid content info.');
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
		$db->update('link', array('nodeid' => $data['destnodeid']), array(array('field' => 'nodeid', 'value' => $sources)));
		$db->update('vBForum:node', array('contenttypeid' => $this->contenttypeid), array('nodeid' => $data['destnodeid']));

		//@TODO: There is no title for posting a reply or comment but api throws an error if blank. Fix this.
		if (empty($data['url_title']))
		{
			$data['url_title'] = '(Untitled)';
		}

		$filedataid = 0;
		if (!$data['url_nopreview'] AND $data['url_image'])
		{
			$ret = vB_Api::instance('content_attach')->uploadUrl($data['url_image']);

			if (empty($ret['error']))
			{
				$filedataid = $ret['filedataid'];
			}
		}

		if ($filedataid)
		{
//				vB_Api::instanceInternal('content_attach')->deleteAttachment($data['destnodeid']);
		}

		$linkData = array(
			'userid' => $data['destauthorid'],
			'url_title' => $data['url_title'],
			'rawtext' => $data['text'],
			'url' => $data['url'],
			'meta' => $data['url_meta'],
			// TODO: uncomment this when the editor is ready
//			'filedataid' => $filedataid
		);

		return vB_Api::instanceInternal('content_link')->update($data['destnodeid'], $linkData);
	}

	public function getQuotes($nodeids)
	{
		//Per Product, we just quote the text content (but this may change in the future)
		//If and when the requirement changes to include the non-text content, don't call the parent method and then implement it here
		return parent::getQuotes($nodeids);
	}
}
