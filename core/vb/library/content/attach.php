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
 * vB_Api_Content_Attach
 *
 * @package vBApi
 * @access public
 */
class vB_Library_Content_Attach extends vB_Library_Content
{
	protected $types;

	protected $extension_map;

	//override in client- the text name
	protected $contenttype = 'vBForum_Attach';

	//The table for the type-specific data.
	protected $tablename = 'attach';

	//Control whether this record will display on a channel page listing.
	protected $inlist = 0;

	//Image processing functions
	protected $imageHandler;

	protected function __construct()
	{
		parent::__construct();
		$this->imageHandler = vB_Image::instance();
	}

	/*** Adds a new node.
	 *
	 *	@param	mixed		Array of field => value pairs which define the record.
	 *  @param	array		Array of options for the content being created.
	 *						Available options include:
	 *
	 * 	@return	integer		the new nodeid
	 ***/
	public function add($data, array $options = array())
	{
		$result = parent::add($data, $options);

		//todo -- lock the caption to the description until we collapse the fields.  Remove when caption goes away
		if (isset($data['caption']))
		{
			$data['description'] = $data['caption'];
		}
		else if (isset($data['description']))
		{
			$data['caption'] = $data['description'];
		}

		if ($result)
		{
			$this->assertor->assertQuery('vBForum:node', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'nodeid' => $data['parentid'], 'hasphoto' => 1 ));
		}
		return $result;
	}


	/** Remove an attachment
	 * 	@param	INT	nodeid
	 *
	 **/
	public function delete($nodeid)
	{
		//We need the parent id. After deletion we may need to set hasphoto = 0;
		$existing =	$this->nodeApi->getNode($nodeid);
		$this->removeAttachment($nodeid);
		parent::delete($nodeid);
		$photo = $this->assertor->getRow('vBForum:node', array('contenttypeid' => $this->contenttypeid, 'parentid' => $existing['parentid']));

		//If we got empty or error, there are no longer any attachments.
		if (!empty($existing['parentid']) AND (empty($photo) OR !empty($photo['errors'])))
		{
			$this->assertor->assertQuery('vBForum:node', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'hasphoto' => 0, vB_dB_Query::CONDITIONS_KEY => array(
				array(
						'field' => 'nodeid',
						'value' => $existing['parentid'],
						'operator' => vB_dB_Query::OPERATOR_EQ
					))));
		}
		$this->nodeApi->clearCacheEvents(array($nodeid, $existing['parentid']));
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
		$existing = $this->assertor->getRow('vBForum:attach', array('nodeid' => $nodeid));

		//todo -- lock the caption to the description until we collapse the fields.  Remove when caption goes away
		if (isset($data['caption']))
		{
			$data['description'] = $data['caption'];
		}
		else if (isset($data['description']))
		{
			$data['caption'] = $data['description'];
		}

		if (parent::update($nodeid, $data))
		{
			//We need to update the filedata ref counts
			if (!empty($data['filedataid']) AND ($existing['filedataid'] != $data['filedataid']))
			{
				//Remove the existing
				$filedata = vB::getDbAssertor()->getRow('filedata', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						'filedataid' => $existing['filedataid']
				));

				if ($filedata['refcount'] > 1)
				{
					$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					'filedataid' => $existing['filedataid'],
					'refcount' => $filedata['refcount'] - 1);
				}
				else
				{
					$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
					'filedataid' => $existing['filedataid']);
					$this->assertor->assertQuery('vBForum:filedataresize', $params);
				}

				$this->assertor->assertQuery('filedata', $params);

				//add the new
				$filedata = vB::getDbAssertor()->getRow('filedata', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						'filedataid' => $data['filedataid']
				));

				if (!empty($filedata) AND empty($filedata['errors']))
				{
					$params = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					'filedataid' => $data['filedataid'],
					'refcount' => $filedata['refcount'] + 1);

					$this->assertor->assertQuery('filedata', $params);
				}
			}
		}
		$this->nodeApi->clearCacheEvents(array($nodeid, $existing['parentid']));
	}


	/**
	 *	See base class for information
	 */
	public function getIndexableFromNode($node, $include_attachments = true)
	{
		// Attachments are indexed via getIndexableContentForAttachments.
		// And we can't have attachments as children of attachments, so do nothing.
		$indexableContent = array();
		return $indexableContent;
	}


	/** Remove an attachment
	 * 	@param	INT	nodeid
	 *
	 **/
	public function removeAttachment($id)
	{
		if (empty($id) OR !intval($id))	{
			throw new Exception('invalid_request');
		}

		$attachdata = vB::getDbAssertor()->getRow('vBForum:attach', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'nodeid' => $id
			));

		if (!empty($attachdata) AND $attachdata['filedataid'])
		{
			$filedata = vB::getDbAssertor()->getRow('filedata', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'filedataid' => $attachdata['filedataid']
			));

			if ($filedata['refcount'] > 1)
			{
				$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'filedataid' => $attachdata['filedataid'],
				'refcount' => $filedata['refcount'] - 1);
			}
			else
			{
				$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'filedataid' => $attachdata['filedataid']);
				vB::getDbAssertor()->assertQuery('vBForum:filedataresize', $data);
			}

			vB::getDbAssertor()->assertQuery('vBForum:filedata', $data);
		}

		return true;
	}

	/** Get attachments for a content type
	 * 	@param	INT	nodeid
	 *
	 **/
	public function getAttachmentsFromType($typeid)
	{
		$attachdata = vB::getDbAssertor()->getRows('attachmentsByContentType', array('ctypeid' => $typeid));

		return $attachdata;
	}

	/** Remove all attachments for content type
	 * 	@param	INT	Content Type id
	 *
	 **/
	public function zapAttachmentType($typeid)
	{
		$list = $this->getAttachmentsFromType($typeid);

		foreach($list AS $attachment)
		{
			$this->removeAttachment($attachment['attachmentid']);
		}
	}

	/** Get array of http headers for this attachment file extension
	 * 	@param	STRING	file extension, e.g. 'pdf'
	 *
	 **/
	public function getAttachmentHeaders($extension)
	{
		$headers = array('Content-type: application/octet-stream');
		if (!empty($extension))
		{
			$attach_meta = vB::getDbAssertor()->getRows('vBForum:fetchAttachPermsByExtension', array('extension' => $extension));
			if (!empty($attach_meta) AND !empty($attach_meta[0]['mimetype']))
			{
				$headers = unserialize($attach_meta[0]['mimetype']);
			}
		}
		return $headers;
	}
}
