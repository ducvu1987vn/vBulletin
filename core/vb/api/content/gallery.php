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
 * vB_Api_Content_Gallery
 *
 * @package vBApi
 * @author ebrown
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Api_Content_Gallery extends vB_Api_Content_Text
{
	//override in client- the text name
	protected $contenttype = 'vBForum_Gallery';

	//The table for the type-specific data.
	protected $tablename = array('gallery', 'text');

	//We need the primary key field name.
	protected $primarykey = 'nodeid';

	//Whether we change the parent's text count- 1 or zero
	protected $textCountChange = 1;

	//Does this content show author signature?
	protected $showSignature = true;

	//Whether we handle showapproved,approved fields internally or not
	protected $handleSpecialFields = 1;

	/** normal protector- protected to prevent direct instantiation **/
	protected function __construct()
	{
		parent::__construct();
		$this->library = vB_Library::instance('Content_Gallery');
	}

	/*** Adds a new node.
	 *
	 *	@param	mixed		Array of field => value pairs which define the record.
	 *  @param	array		Array of options for the content being created.
	 *						Available options include:
	 *
	 * 	@return	integer		the new nodeid
	 ***/
	public function add($data, $options = array())
	{
		vB_Api::instanceInternal('hv')->verifyToken($data['hvinput'], 'post');

		$result = $this->validateGalleryData($data, $options['filedataid']);

		if ((vB_Api::instanceInternal('node')->fetchAlbumChannel() == $data['parentid']) AND (!vB::getUserContext()->hasPermission('albumpermissions', 'picturefollowforummoderation')))
		{
			$data['approved'] = 0;
			$data['showapproved'] = 0;
		}

		if ($result === true)
		{
			return parent::add($data, $options);
		}
		else
		{
			return $result;
		}

	}

	/*** Returns the node indexable node text
	 *	@param	integer	The id in the primary table
	 *
	 *	@return	array- title and caption
	 ***/
	public function getIndexableFromNode($node, $include_attachments = true)
	{
		$indexableContent['title'] = $content[$nodeId]['title'];
		$indexableContent['caption'] = $content[$nodeId]['caption'];
		return $indexableContent;
	}

	/*** Updates from a web save
	 *	@param	integer	The id in the primary table
	 *
	 *	@return	int	Number of updates-standard save response.
	 ***/
	public function updateFromWeb($nodeid, $postdata, $filedataids = array())
	{
		//First do we have a nodeid?

		if (!$nodeid OR !intval($nodeid) OR !$this->validate($postdata, parent::ACTION_UPDATE, $nodeid))
		{
			throw new Exception('invalid_data');
		}
		$data = array();
		//And are we authorized to make changes?
		if (!$this->validate($data, parent::ACTION_UPDATE, $nodeid))
		{
			throw new Exception('no_permission');
		}

		if (isset($postdata['title']))
		{
			$postdata['urlident'] = vB_String::getUrlIdent($postdata['title']);
		}

		$existing = $this->getContent($nodeid);
		$existing = $existing[$nodeid];
		$cleaner = vB::getCleaner();
		//clean the gallery data.
		$fields = array('title' => vB_Cleaner::TYPE_STR,
			'caption' => vB_Cleaner::TYPE_STR,
			'htmltitle' => vB_Cleaner::TYPE_STR,
			'rawtext' => vB_Cleaner::TYPE_STR,
			'reason' => vB_Cleaner::TYPE_STR,
			'keyfields' => vB_Cleaner::TYPE_STR,
			'publishdate' => vB_Cleaner::TYPE_UINT,
			'unpublishdate' => vB_Cleaner::TYPE_UINT,
			'description' => vB_Cleaner::TYPE_STR,
			'displayorder' => vB_Cleaner::TYPE_UINT,
			'urlident' => vB_Cleaner::TYPE_STR,
			'tags' => vB_Cleaner::TYPE_STR,
			'enable_comments' => vB_Cleaner::TYPE_BOOL,
			'parentid' => vB_Cleaner::TYPE_UINT
		);

		$cleaned = $cleaner->cleanArray($postdata, $fields);
		$updates = array();

		//If nothing has changed we don't need to update the parent.
		foreach (array_keys($fields) as $fieldname)
		{
			if (isset($postdata[$fieldname]) AND isset($cleaned[$fieldname]))
			{
				$updates[$fieldname] = $cleaned[$fieldname];
			}
		}

		$results = true;
		if (!empty($updates))
		{
			$results = $this->update($nodeid, $updates);
		}

		if ($results AND (!is_array($results) OR empty($results['errors'])))
		{
			//let's get the current photo information;

			$existing = $this->getContent($nodeid);
			$existing = $existing[$nodeid];

			if (empty($existing['photo']))
			{
				$delete = array();
			}
			else
			{
				$delete = $existing['photo'];
			}

			//Now we match the submitted data against the photos
			//if they match, we remove from "delete" and do nothing else.
			//if the title is updated we do an immediate update.
			//Otherwise we add.
			if (!empty($filedataids) AND is_array($filedataids))
			{
				$photoApi = vB_Api::instanceInternal('content_photo');

				foreach ($filedataids AS $filedataid => $title)
				{
					//it has to be at least a integer.
					if (intval($filedataid))
					{
						//First see if we have a match.
						$foundMatch = false;
						foreach ($delete as $photoNodeid => $photo)
						{
							if ($filedataid == $photo['filedataid'])
							{
								$foundMatch = $photo;
								unset($delete[$photoNodeid]);
								break;
							}
						}

						if ($foundMatch)
						{
							if ($title != $foundMatch['title'])
							{
								$titles[$foundMatch['nodeid']] = $title;
							}
							//unset this record.

							//Skip to the next record
							continue;
						}

						//If we got here then this is new and must be added.
						//We do an add.
						$photoApi->add(array('parentid' => $nodeid,
							'caption' => $title, 'title' => $title, 'filedataid' => intval($filedataid)));
					}

				}
				if (!empty($delete))
				{
					foreach ($delete as $photo)
					{
						$photoApi->delete($photo['nodeid']);
					}
				}

				if (!empty($titles))
				{
					foreach ($titles as $nodeid => $title)
					{
						$photoApi->update($nodeid, array('caption' => $title, 'title' => $title));
					}
				}
			}
		}

		return $results;
	}

	/**
	 * Adds content info to $result so that merged content can be edited.
	 * @param array $result
	 * @param array $content
	 */
	public function mergeContentInfo(&$result, $content)
	{
		if (vb::getUserContext()->getChannelPermission('forumpermissions', 'canviewthreads', $result['nodeid']))
		{
			$this->library->mergeContentInfo($result, $content);
		}
	}

	/**
	 * Performs the merge of content and updates the node.
	 * @param type $data
	 * @return type
	 */
	public function mergeContent($data)
	{
		return $this->library->mergeContent($data);
	}

	/**
	 * Validates the gallery data
	 * @param	array		info about the photos
	 * @return	boolean
	 */
	protected function validateGalleryData($data, $filedataid)
	{
		$usercontext = vB::getUserContext();
		$albumChannel = vB_Api::instanceInternal('node')->fetchAlbumChannel();

		if (!empty($data['parentid']) AND $data['parentid'] == $albumChannel AND !$usercontext->hasPermission('albumpermissions', 'canviewalbum'))
		{
			throw new vB_Exception_Api('no_permission');
		}

		$albummaxpic = $usercontext->getLimit('albummaxpics');

		if (!empty($albummaxpic))
		{
			$overcount = count($filedataid) - $albummaxpic;
			if($overcount > 0)
			{
				throw new vB_Exception_Api('upload_album_pics_countfull_x', array($overcount));
			}
		}

		return true;
	}
}
