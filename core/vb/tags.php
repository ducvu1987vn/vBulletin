<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright  2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
*/
class vB_Tags
{
	/********************************************************
	 *	Private Members
	 ********************************************************/

	protected $nodeid;
	protected $contentinfo = null;
	protected $tags = null;
	protected $currentUserId = false;
	protected $owner = false;
	protected $usercontext;
	protected $assertor;

	/********************************************************
	*	Constructors / Factory Methods
	********************************************************/

	/**
	*	Create a taggable content item.
	*
	* @param int id for the content item to be tagged.
	* @param array content info -- database record for item to be tagged, values vary by
	*	specific content item.  For performance reasons this can be included, otherwise the
	* 	data will be fetched if needed from the provided id.
	*/

	public function __construct($nodeid, $contentinfo = false)
	{
		$this->nodeid = $nodeid;
		$this->assertor = vB::getDbAssertor();
		$this->currentUserId = vB::getCurrentSession()->get('userid');
		$this->owner = $this->getNodeOwner($this->nodeid);
		if ($contentinfo)
		{
			$this->contentinfo = $contentinfo;
		}
		else
		{
			$this->loadContentInfo();
		}
	}

	/**
	*	Takes a list of tags and returns a list of valid tags
	*
	* Tags are transformed to removed tabs and newlines
	* Tags may be lowercased based on options
	* Tags matching synomyns will
	* Duplicate will be eliminated (case insensitive)
	* Invalid tags will be removed.
	*
	* Fetch the valid tags from a list. Filters are length, censorship, perms (if desired).
	*
	* @param	string|array	List of tags to add (comma delimited, or an array as is). If array, ensure there are no commas.
	* @param	array			(output) List of errors that happens
	* @param	boolean		Whether to expand the error phrase
	*
	* @return	array			List of valid tags
	*/
	protected function filterTagList($taglist, &$errors, $evalerrors = true)
	{
		$options = vB::getDatastore()->get_value('options');
		$errors = array();
		if (!is_array($taglist))
		{
			$taglist = $this->splitTagList($taglist);
		}
		//This seems like a terrible place to put this, but I don't know where else it should go.
		if ($options['tagmaxlen'] <= 0 OR $options['tagmaxlen'] >= 100)
		{
			$options['tagmaxlen'] = 100;
		}

		$validRaw = array();

		foreach ($taglist AS $tagtext)
		{
			$tagtext = trim(preg_replace('#[ \r\n\t]+#', ' ', $tagtext));
			if ($this->isTagValid($tagtext, $errors))
			{
				$validRaw[] = ($options['tagforcelower'] ? vB_String::vBStrToLower($tagtext) : $tagtext);
			}
		}

		if (empty($validRaw))
		{
			$errors['no_valid_tags'] = 'no_valid_tags_found';
			return array();
		}
		$validRaw = $this->convertSynonyms($validRaw, $errors);
		// we need to essentially do a case-insensitive array_unique here
		$validUnique = array_unique(array_map('vB_String::vBStrToLower', $validRaw));
		$valid = array();
		foreach (array_keys($validUnique) AS $key)
		{
			$valid[] = $validRaw["$key"];
		}
		$validUnique = array_values($validUnique); // make the keys jive with $valid
		//if requested compose the error messages to strings
		if ($evalerrors)
		{
			$errors = fetch_error_array($errors);
		}
		return $valid;
	}

	/**
	*	Delete tag attachments for a list of content items
	*
	* @param array $nodeidids
	*/
	protected function deleteTagAttachmentsList($nodeids)
	{
		if ($this->contentinfo === null)
		{
			$this->loadContentInfo();
		}

		$canDelete = true;
		$unDeletableTags = "";

		foreach ($this->contentinfo as $tag)
		{
			if (!($this->canDeleteTag($tag)))
			{
				$canDelete = false;
				$unDeletableTags .= $tag["tagtext"] . ",";
			}
		}

		if (!$canDelete)
		{
			throw new vB_Exception_Api('no_delete_permissions_for_tag');
		}

		foreach($nodeids as $nodeid)
		{
			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, vB_dB_Query::CONDITIONS_KEY =>
				array('nodeid' => $nodeid));
			$this->assertor->assertQuery('vBForum:tagnode', $data);

			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE, vB_dB_Query::CONDITIONS_KEY =>
				array('nodeid' => $nodeid), 'taglist' => '');
			$this->assertor->assertQuery('vBForum:node', $data);
		}
		$this->invalidateTagList();
	}

	public function mergeUsers($olduserid, $newuserid)
	{
		$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE, vB_dB_Query::CONDITIONS_KEY =>
			array('userid' => $olduserid), 'userid' => $newuserid) ;
		$this->assertor->assertQuery('tagcontent', $data);
	}

	/********* provides a list of content types

	/**
	*	Checks to see if the tag is valid.
	*
	* Does not check the validity of any tag associations.
	* @param 	string $tagtext tag text to validate
	* @param	array	$errors (output) List of errors that happens
	*/
	protected function isTagValid($tagtext, &$errors)
	{
		static $taggoodwords = null;
		static $tagbadwords = null;
		$options = vB::getDatastore()->get_value('options');

		// construct stop words and exception lists (if not previously constructed)
		if (is_null($taggoodwords) or is_null($tagbadwords))
		{
			// filter the stop words by adding custom stop words (tagbadwords) and allowing through exceptions (taggoodwords)
			if (!is_array($tagbadwords))
			{
				$tagbadwords = preg_split('/\s+/s', vB_String::vBStrToLower($options['tagbadwords']), -1, PREG_SPLIT_NO_EMPTY);
			}

			if (!is_array($taggoodwords))
			{
				$taggoodwords = preg_split('/\s+/s', vB_String::vBStrToLower($options['taggoodwords']), -1, PREG_SPLIT_NO_EMPTY);
			}

			// get the stop word list; allow multiple requires
			// merge hard-coded badwords and tag-specific badwords
			$tagbadwords = array_merge(vB_Badwords::getBadWords(), $tagbadwords);
		}

		if ($tagtext === '')
		{
			return false;
		}

		if (in_array(vB_String::vBStrToLower($tagtext), $taggoodwords))
		{
			return true;
		}

		$charStrlen = vB_String::vbStrlen($tagtext, true);
		if ($options['tagminlen'] AND $charStrlen < $options['tagminlen'])
		{
			$errors['min_length'] = array('tag_too_short_min_x', $options['tagminlen']);
			return false;
		}

		if ($charStrlen > $options['tagmaxlen'])
		{
			$errors['max_length'] = array('tag_too_long_max_x', $options['tagmaxlen']);
			return false;
		}

		if (strlen($tagtext) > 100)
		{
			// only have 100 bytes to store a tag
			$errors['max_length'] = array('tag_too_long_max_x', $options['tagmaxlen']);
			return false;
		}

		$censored = fetch_censored_text($tagtext);
		if ($censored != $tagtext)
		{
			// can't have tags with censored text
			$errors['censor'] = 'tag_no_censored';
			return false;
		}

		if (count($this->splitTagList($tagtext)) > 1)
		{
			// contains a delimiter character
		//	$errors['comma'] = $evalerrors ? fetch_error('tag_no_comma') : 'tag_no_comma';
			$errors['comma'] = 'tag_no_comma';
			return false;
		}

		if (in_array(strtolower($tagtext), $tagbadwords))
		{
			if(isset($errors['common']))
			{
				$tagtext = trim($errors['common'][1]).', '.$tagtext;
			}
			$errors['common'] = array('tag_x_not_be_common_words', ' '.$tagtext.' ');
			return false;
		}
		return true;
	}

	/**
	* Splits the tag list based on an admin-specified set of delimiters (and comma).
	*
	* @param	string	List of tags
	*
	* @return	array	Tags in seperate array entries
	* temporarily make public
	*/
	protected function splitTagList($taglist)
	{
		static $delimiters = array();
		$taglist = unhtmlspecialchars($taglist);
		$options = vB::getDatastore()->get_value('options');

		if (empty($delimiters))
		{
			$delimiter_list = $options['tagdelimiter'];
			$delimiters = array(',');

			// match {...} segments as is, then remove them from the string
			if (preg_match_all('#\{([^}]*)\}#s', $delimiter_list, $matches, PREG_SET_ORDER))
			{
				foreach ($matches AS $match)
				{
					if ($match[1] !== '')
					{
						$delimiters[] = preg_quote($match[1], '#');
					}
					$delimiter_list = str_replace($match[0], '', $delimiter_list);
				}
			}

			// remaining is simple, space-delimited text
			foreach (preg_split('#\s+#', $delimiter_list, -1, PREG_SPLIT_NO_EMPTY) AS $delimiter)
			{
				$delimiters[] = preg_quote($delimiter, '#');
			}
		}

		$taglist = preg_split('#(' . implode('|', $delimiters) . ')#', $taglist, -1, PREG_SPLIT_NO_EMPTY);

		return array_map('vB_String::htmlSpecialCharsUni', $taglist);
	}

	/**
	*	Converts synomyns to canonical tags
	*
	* If a tag is converted a message will be added to the error array to alert the user
	* Does not handle removing duplicates created by the coversion process
	*
	* @param array array of tags to convert
	* @param array array of errors (in/out param)
	*
	*	@return array the new list of tags
	*/
	protected function convertSynonyms($tags, &$errors)
	{
		$set = $this->assertor->assertQuery(
			'vBForum:getCanonicalTags', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'tags' => $tags));

		$map = array();

		if ($set->valid())
		{
			$row = $set->current();
			while ($set->valid())
			{
				$map[vB_String::vBStrToLower($row['tagtext'])] = $row['canonicaltagtext'];
				$row = $set->next();
			}

		}
		$newTags = array();
		foreach ($tags as $key => $tag)
		{
			$tag_lower = vB_String::vBStrToLower($tag);
			if (array_key_exists($tag_lower, $map))
			{
				$errors["$tag_lower-convert"] = array('tag_x_converted_to_y', $tag, $map[$tag_lower]);
				$newTags[] = $map[$tag_lower];
			}
			else
			{
				$newTags[] = $tag;
			}
		}
		return $newTags;
	}


	/********************************************************
	*	Public Methods
	********************************************************/

	/**
	*	Determines if a user can delete a tag associated with this content item
	*
	* A user can delete his or her own tags.
	* A user with moderator rights can delete a tag.
	* If not otherwise specified a user can delete a tag if they own the content item
	*
	*	This function requires that content info is set.
	*
	*	@param int The user id for the tag/content association
	* @return bool
	*/
	protected function canDeleteTag($tag, &$errors = array())
	{
		$tagInfo = $this->fetchTagInfo($tag);
		//Check if tag exists
		if(!is_array($tagInfo))
		{
			$errors['invalid_tags'] = array('x_is_invalid_tag', $tag);
			return false;
		}
		// Attempt some decent content agnostic defaults
		// Content types that care should override this function

		//the user can delete his own tag associations
		if ($this->currentUserId == $tagInfo['userid'])
		{
			return true;
		}

		//moderators can delete tags
		if ($this->canModerateTag())
		{
			return true;
		}

		//the object's owner can delete tags
		if ($this->isOwnedByCurrentUser())
		{
			return true;
		}

		$errors['no_delete_permissions'] = array('no_delete_tag_permission');
		return false;
	}

	/**
	*	Checks to see if the user has permission to "moderate" tags for this content items.
	*
	* This is specific to the content type and defaults to false.
	*	This function requires that content info be set.
	*
	* @return bool
	*/
	protected function canModerateTag()
	{
		// Basic logic is that only super admin can moderate tags.
		// Content types with more granular permissions should override this function

		if (empty($this->usercontext))
		{
			$this->usercontext = vB::getUserContext($this->currentUserId);
		}
		//admin and mods can Moderate tag
		if ($this->usercontext->getChannelPermission('moderatorpermissions', 'canmoderatetags', $this->nodeid))
		{
			return true;
		}
		return false;
	}

	/**
	*	Checks to see if the user can add tags to this content item
	*
	*	This function requires that content info be set.
	* @return bool
	*/
	protected function canAddTag($targetid = false)
	{
		// By default, logged in users can add tags
		// Content types that care should override this function

		if (empty($this->usercontext))
		{
			$this->usercontext = vB::getUserContext($this->currentUserId);
		}

		if (!$targetid)
		{
			$targetid = $this->nodeid;
		}

		if ($this->isOwnedByCurrentUser())
		{
			return ($this->usercontext->getChannelPermission('forumpermissions', 'cantagown', $targetid));
		}
		else
		{
			return ($this->usercontext->getChannelPermission('forumpermissions', 'cantagothers', $targetid));
		}
	}

	/**
	*	Can the current user manage existing tags?
	*
	*	The only current operation on existing tags is to remove them from the content
	* item.
	*
	*	This is odd.  It controls whether or not we show the checkboxes beside the
	* tags in tagUI (and if we check at all for deletes).  It exists primarily to
	* capture some logic in the thread to handle the situation where a user can
	* delete tags but not add them (if a user can add tags we'll always display the
	* delete UI in the legacy logic).  Note that there is a seperate check for each
	* tag to determine if the user can actually delete that particular tag.  Most
	* new types aren't likely to require that kind of granularity and probably
	* won't need to to extend this function.
	*
	*	This function requires that content info be set.
	*
	* @return bool
	*/
	public function canManageTag($nodeid = 0)
	{
		if (!$nodeid)
		{
			$nodeid = $this->nodeid;
		}
		// By default, logged in users can add tags
		// Content types that care should override this function
		if (empty($this->usercontext))
		{
			$this->usercontext = vB::getUserContext($this->currentUserId);
		}

		if ($this->usercontext->getChannelPermission('moderatorpermissions', 'canmoderatetags', $nodeid))
		{
			return true;
		}

		if ($this->isOwnedByCurrentUser())
		{
			return ($this->usercontext->getChannelPermission('forumpermissions', 'candeletetagown', $nodeid));
		}
		else
		{
			return false;
		}
	}

	/**
	*	Determines if the current user owns this content item
	*
	*	Ownership is a content specific concept.  For example the "owner" of a thread
	* is the thread starter.
	*	This function requires that content info be set.
	*
	* @return bool
	*/
	protected function isOwnedByCurrentUser()
	{
		// Attempt some decent content agnostic defaults
		// Content types that care should override this function

		if (empty($this->owner))
		{
			$this->owner = $this->getNodeOwner($this->nodeid);
		}

		return ($this->owner == $this->currentUserId);
	}

	/**
	 * Get the id of the node owner
	 * @return <int> the id of the owner
	 */
	protected function getNodeOwner($node)
	{
		$node = vB_Library::instance('node')->getNodeBare($node);
		return $node['userid'];
	}

	/**
	*	Get the user permission to create tags
	*
	* @return bool
	*/
	function checkUserPermission()
	{
		if (empty($this->usercontext))
		{
			$this->usercontext = vB::getUserContext($this->currentUserId);
		}
		return $this->usercontext->getChannelPermission('forumpermissions', 'cantagown', $this->nodeid);
	}

	/**
	*	Get the tag limits for the content type
	*
	*	This function requires that content info be set.
	*
	* @return array ('content_limit' => total tags for content type, 'user_limit' => total tags the
	*		current user can have on this item)
	*/
	public function fetchTagLimits()
	{
		if (empty($this->usercontext))
		{
			$this->usercontext = vB::getUserContext($this->currentUserId);
		}

		if ($this->isOwnedByCurrentUser())
		{
			$user_limit = $this->usercontext->getChannelLimits($this->nodeid, 'maxstartertags');
		}
		else
		{
			$user_limit = $this->usercontext->getChannelLimits($this->nodeid, 'maxothertags');
		}

		$tagmaxthread = $this->usercontext->getChannelLimits($this->nodeid, 'maxtags');
		return array('content_limit' => $tagmaxthread, 'user_limit' => $user_limit);
	}

	/**
	*	Get the display label for the current content type
	*
	*	@return string
	*/
	public function fetchContentTypeDisplay()
	{
		return "";
		{
			return $vbphrase['picture'];
		}
	}

 	/**
	* Adds tags to the content item. Tags are created if they don't already exist
	* (assuming the user has permissions)
	*
	*	If a tag cannot be processed it is dropped from the list and an error is returned, however
	* this does not prevent valid tags from being processed.
	*
	* @param	string|array	List of tags to add (comma delimited, or an array as is).
	*												If array, ensure there are no commas.
	*
	* @return	array			Array of errors, if any
	*/
	public function addTagsToContent($taglist)
	{
		$this->invalidateTagList();

		if (!$this->nodeid OR !$this->canAddTag())
		{
			return array();
		}

		$errors = array();
		$limits = $this->fetchTagLimits();

		if (!$taglist)
		{
			return $errors;
		}

		//Let's first remove existing and verify we're not exceeding the limits.
		$taglist = $this->filterTagListContentLimits($taglist, $limits, $errors);
		if (empty($taglist))
		{
			return $errors;
		}

		$inserts = $taglist;
		$existing = $this->assertor->assertQuery('vBForum:tag', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'tagtext' => $taglist));

		if ($existing AND $existing->valid())
		{
			$tag = $existing->current();
			while($existing->valid())
			{
				$key = array_search($tag['tagtext'], $inserts);

				if ($key !== false)
				{
					unset($inserts[$key]);
				}
				$tag = $existing->next();
			}
		}

		if (!empty($inserts))
		{
			// Can user create new tags?
			if (empty($this->usercontext))
			{
				$this->usercontext = vB::getUserContext($this->currentUserId);
			}
			if (!$this->usercontext->hasPermission('genericpermissions', 'cancreatetag'))
			{
				$errors['nopermission'] = array('tag_no_create');
				return $errors;
			}

			// create new tags
			$taglistInsert = array();
			foreach ($inserts AS $tag)
			{
				$taglistInsert[] = array( $tag, vB::getRequest()->getTimeNow());
			}
			$this->assertor->assertQuery('vBForum:tag', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_MULTIPLEINSERT,
				vB_dB_Query::FIELDS_KEY => array('tagtext', 'dateline'), vB_dB_Query::VALUES_KEY => $taglistInsert));
		}

		// now associate with content item
		$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'tags' => $taglist,
		'nodeid' => intval($this->nodeid),
		'userid' => $this->currentUserId,
		'dateline' => vB::getRequest()->getTimeNow());
 		$this->assertor->assertQuery('vBForum:addTagContent', $data);

		//existing content info is invalid now.
		$this->invalidateTagList();
		// do any content type specific updates for new tags
		$this->rebuildContentTags();
		$this->updateNode();

		return $errors;
	}


	/**
	*	Copy the tag attachments from one item to another
	*
	*	Copying of tag attachements from an item of a different type is supported.
	*
	*	@param $sourceid The id of the item whose tags should be copied
	*/
	public function copyTagAttachments($sourceid)
	{
		if (!$this->nodeid OR !$this->canAddTag($sourceid))
		{
			return array();
		}
		//user can move source node
		if (!$this->canManageTag($sourceid))
		{
			return array();
		}

		//checking available space for tags
		$remaining = $this->getSpace($sourceid);
		if ($remaining <= 0)
		{
			throw new vB_Exception_Api('tag_limits_error');
		}

		$this->invalidateTagList();
		$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
						'nodeid' => intval($this->nodeid),
						'sourceid' => intval($sourceid),
						vB_dB_Query::PARAM_LIMIT => $remaining);

		$this->assertor->assertQuery('vBForum:copyTagContent', $data);
		$this->rebuildContentTags();
		$this->updateNode();
	}

	public function moveTagAttachments($sourceid)
	{
		if (!$sourceid)
		{
			return false;
		}

		if (!$this->nodeid OR !$this->canAddTag($sourceid))
		{
			return array();
		}

		//user can move source node
		if (!$this->canManageTag($sourceid))
		{
			return array();
		}

		//checking available space for tags
		$remaining = $this->getSpace($sourceid);
		if ($remaining <= 0)
		{
			throw new vB_Exception_Api('tag_limit_error');
		}


		$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'nodeid' => intval($this->nodeid),
					'sourceid' => intval($sourceid),
					vB_dB_Query::PARAM_LIMIT => $remaining);
		$this->assertor->assertQuery('vBForum:copyTagContent', $data);

		$this->removeTagInfo($sourceid, true, $remaining);
		$this->invalidateTagList();
		$this->rebuildContentTags();
		$this->updateNode();
	}

	protected function removeTagInfo($nodeid, $isMoving = false, $remaining = 0)
	{
		$taglist = '';
		if ($isMoving)
		{
			$sourceTagList = $this->assertor->getRow('vBForum:getNodeTagList', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
						'nodeid' => $nodeid));
			$targetTagList = $this->assertor->getRow('vBForum:getNodeTagList', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
						'nodeid' => $this->nodeid));
			$sourceTagList = explode(",", $sourceTagList['taglist']);
			$targetTagList = explode(",", $targetTagList['taglist']);
			$ignored = array_intersect($sourceTagList, $targetTagList);
			//If there are no similar tags between both nodes
			if (!$ignored)
			{
				$tmp = array_diff($sourceTagList, $targetTagList);
				foreach ($tmp as $key => $tag)
				{
					if ($key >= $remaining)
					{
						$ignored[] = $tag;
					}
				}
			}

			$ignoredTags = array();
			foreach ($ignored as $tag)
			{
				$taglist .= $tag . ",";
				$ignoredTags[] = $tag;
			}
			$taglist = substr($taglist, 0, -1);
			$ignoredTags = sizeof($ignoredTags) > 0 ? $ignoredTags : '';

			//Delete Records
			$this->assertor->assertQuery('vBForum:deleteTags', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
						'nodeid' => $nodeid,
				'ignoredTags' => $ignoredTags));
		}
		else
		{
			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				vB_dB_Query::CONDITIONS_KEY => array('nodeid' => $nodeid));
			$this->assertor->assertQuery('vBForum:tagnode', $data);
		}
		$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			vB_dB_Query::CONDITIONS_KEY =>  array('nodeid' => $nodeid), 'taglist' => $taglist);
		$this->assertor->assertQuery('vBForum:node', $data);
	}

	/**
	*	Merge the tag attachments for one or more tagged items to this item
	*
	*	Designed to handle the results of merging items (the tags also need to be
	* merged).  Items merged are assumed to be the same type as this item. Merged
	* tags are detached from the items they are merged from.
	*
	*	@param $sourceids The id of the item whose tags should be merged
	*	@param $destid The id of the item to merge tags to
	*
	*/
	public function mergeTagAttachments($sourceids)
	{
		//check if user can move nodes
		$canMerge = true;
		foreach ($sourceids as $source)
		{
			if (!$this->canManageTag($source))
			{
				$canMerge = false;
			}
		}

		if (!$canMerge AND !$this->canManageTag())
		{
			throw new vB_Exception_Api('no_merge_permissions');
		}

		foreach ($sourceids as $key => $sourceid)
		{
			if (!$this->canAddTag($sourceid))
			{
				unset($sourceids[$key]);
			}
		}

		if (empty($sourceids))
		{
			return false;
		}

		$remaining = array();
		foreach ($sourceids as $sourceid)
		{
			//checking available space for tags
			$remaining[$sourceid] = $this->getSpace($sourceid);
			if ($remaining[$sourceid] <= 0)
			{
				$canMerge = false;
			}
		}

		if ($canMerge)
		{
			$remaining = min($remaining);
			$this->invalidateTagList();
			$safeids = array_map('intval', $sourceids);

			//some places like to include the target id in the array of
			//merged items.  This fixes that.
			$safeids = array_diff($safeids, array($this->nodeid));

			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'sourceid' => $safeids,
				'nodeid' => $this->nodeid,
				vB_dB_Query::PARAM_LIMIT => $remaining);
			$this->assertor->assertQuery('vBForum:mergeTagContent', $data);

			//Anything that didn't get deleted is a duplicate, to be removed
			foreach($safeids as $safeid)
			{
				$this->removeTagInfo($safeid);
			}

			$this->rebuildContentTags();
			$this->updateNode();
		}
		else
		{
			throw new vB_Exception_Api('tag_limit_error');
		}
	}

	/**
	*	Delete tag attachments for this item
	*/
	protected function deleteTagAttachments($nodeid, $taglist, &$errors)
	{
		if (!is_array($taglist))
		{
			$taglist = $this->splitTagList($taglist);
		}

		$notValidTags = "";
		foreach ($taglist as $key => $tag)
		{
			if (!($this->canDeleteTag($tag, $errors)))
			{
				$notValidTags .= $tag . ",";
				unset($taglist[$key]);
			}
		}
		if (!$this->contentinfo)
		{
			$this->contentinfo = $this->fetchContentInfo();
		}
		// Ensure node has tags
		if (sizeof($this->contentinfo) > 0)
		{
			$tags = array();
			foreach($this->contentinfo as $existingTag)
			{
				if (in_array($existingTag['tagtext'], $taglist))
				{
					$tags[] = $existingTag;
				}
			}
			//If we have tags to remove
			if (sizeof($tags) > 0)
			{
				foreach($tags as $tag)
				{
					$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, vB_dB_Query::CONDITIONS_KEY =>
						array('nodeid' => $nodeid, 'tagid' => $tag['tagid']));
					$this->assertor->assertQuery('vBForum:tagnode', $data);
				}
				$this->invalidateTagList();
				$this->updateNode();
			}
		}
	}
	protected function getSpace($sourceNode)
	{
		$limits = $this->fetchTagLimits();
		$contentTagLimit = isset($limits['content_limit']) ? intval($limits['content_limit']) : 0;
		$userTagLimit = isset($limits['user_limit']) ? intval($limits['user_limit']) : 0;
		$existingTagCount = $this->fetchExistingTagCount();

		//Get the tags count from an specific node (source) excluding tags already contained in $this->nodeid
			$sourceNodeTags = $this->assertor->getRow('vBForum:filteredTagsCount',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'targetid' => $this->nodeid,
					'sourceid' => $sourceNode
				)
			);
		$sourceNodeTagsCount = $sourceNodeTags["filteredTags"];
		if (!$sourceNodeTagsCount)
		{
				throw new vB_Exception_Api('no_tags_to_move_merge_copy');
		}

		$contentTagsRemaining = PHP_INT_MAX;
		if ($contentTagLimit)
		{
				$contentTagsRemaining = $contentTagLimit - $existingTagCount - $sourceNodeTagsCount;
		}

		$userTagsRemaining = PHP_INT_MAX;
		if ($userTagLimit)
		{
				$userTagCount = 0;
				if ($this->contentinfo === null)
				{
					$this->contentinfo = $this->loadContentInfo();
				}
				foreach($this->contentinfo as $tag)
				{
					if ($tag["userid"] == $this->currentUserId)
					{
						$userTagCount++;
					}
				}
				$userTagsRemaining = $userTagLimit - $userTagCount - $sourceNodeTagsCount;
		}
		$userTagsRemaining = ($userTagsRemaining < 0 ? ($sourceNodeTagsCount + $userTagsRemaining) : $sourceNodeTagsCount);
		$contentTagsRemaining = ($contentTagsRemaining < 0 ? ($sourceNodeTagsCount + $contentTagsRemaining) : $sourceNodeTagsCount);
		$remainingTags = min($userTagsRemaining, $contentTagsRemaining);

		return $remainingTags;
	}

	/**
	 * Update the node value
	 */
	protected function updateNode()
	{
		$taglist = $this->fetchExistingTagList();
		if (empty($taglist))
		{
			$taglist = '';
		}
		else
		{
			$taglist = implode(',' ,$taglist);
		}

		$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE, vB_dB_Query::CONDITIONS_KEY =>
			array('nodeid' => $this->nodeid), 'taglist' => $taglist);
		$this->assertor->assertQuery('vBForum:node', $data);
	}

	/**
	 *	Delete all tag attachments for this item
	 */
	public function deleteTags($tags = '')
	{
		$errors = array();
		if (empty($tags))
		{
			$this->invalidateTagList();
			$this->deleteTagAttachmentsList( array($this->nodeid));
		}
		else
		{
			$this->deleteTagAttachments($this->nodeid, $tags, $errors);
		}

		return $errors;
	}

	/**
	* Filters the tag list to exclude invalid tags based on the content item the tags
	* are assigned to.
	*
	*	Calls filterTagList internally to handle invalid tags.
	*
	* @param	string|array	List of tags to add (comma delimited, or an array as is).
	*  											If array, ensure there are no commas.
	* @param	array			array of tag limit constraints.  If a limit is not specified a suitable
	*										default will be used (currently unlimited, but a specific default should
	*										not be relied on). Current limits recognized are 'content_limit' which
	*										is the maximum number of tags for a content item and 'user_limit' which
	*										is the maximum number of tags the current user can add to the content item.
	* @param	int				The maximum number of tags the current user can assign to this item (0 is unlimited)
	* @param	boolean		Whether to check the browsing user's create tag perms
	* @param	boolean		Whether to expand the error phrase
	*
	* @return	array			List of valid tags.  If there are too many tags to add, the list will
	*		be truncated first.  An error will be set in this case.
	*/
	protected function filterTagListContentLimits (
		$taglist,
		$limits,
		&$errors,
		$checkBrowserPerms = true,
		$evalerrors = true
	)
	{
		$contentTagLimit = isset($limits['content_limit']) ? intval($limits['content_limit']) : 0;
		$userTagLimit = isset($limits['user_limit']) ? intval($limits['user_limit']) : 0;
		//Note that this call ensures we have loaded content, so no need to check that later
		$existingTagCount = $this->fetchExistingTagCount();

		if ($contentTagLimit AND $existingTagCount >= $contentTagLimit)
		{
		//	$errors['threadmax'] = $evalerrors ? fetch_error('item_has_max_allowed_tags') : 'item_has_max_allowed_tags';
			$errors['threadmax'] = 'item_has_allowed_tags';
			return array();
		}
		$validTags = $this->filterTagList($taglist, $errors, $evalerrors);
		$validTagsLower = array_map('vB_String::vBStrToLower', $validTags);

		if ($validTags)
		{

			if ($checkBrowserPerms AND !$this->canAddTag())
			{
					// can't create tags, need to throw errors about bad ones
				$newTags = array_flip($validTagsLower);
				foreach ($this->contentinfo AS $tag)
				{
					unset($newTags[vB_String::vBStrToLower($tag['tagtext'])]);
					$tag = $existing->next();
				}

				if ($newTags)
				{
					// trying to create tags without permissions. Remove and throw an error
				//	$errors['no_create'] = $evalerrors ? fetch_error('tag_no_create') : 'tag_no_create';
					$errors['no_create'] = 'tag_no_create';

					foreach ($newTags AS $newTag => $key)
					{
						// remove those that we can't add from the list
						unset($validTags["$key"], $validTagsLower["$key"]);
					}
				}
			}

			// determine which tags are already in the thread and just ignore them
			$userTagCount = 0;

			if ($this->contentinfo === null)
			{
				$this->loadContentInfo();
			}
			foreach ($this->contentinfo as $tag)
			{
				if ($tag['userid'] == $this->currentUserId)
				{
					$userTagCount++;
				}

				// tag is in thread, find it and remove
				if (($key = array_search(vB_String::vBStrToLower($tag['tagtext']), $validTagsLower)) !== false)
				{
					unset($validTags["$key"], $validTagsLower["$key"]);
				}
			}

 			//approximate "unlimited" as PHP_INT_MAX -- makes the min logic cleaner
			$contentTagsRemaining = PHP_INT_MAX;
			if ($contentTagLimit)
			{
				$contentTagsRemaining = $contentTagLimit - $existingTagCount - count($validTags);

			}

			$userTagsRemaining = PHP_INT_MAX;
			if ($userTagLimit)
			{
				$userTagsRemaining = $userTagLimit - $userTagCount - count($validTags);
			}

			$remainingTags = min($contentTagsRemaining, $userTagsRemaining);
			if ($remainingTags < 0)
			{
			//	$errors['threadmax'] = $evalerrors ?
			//		fetch_error('number_tags_add_exceeded_x', vb_number_format($remainingTags * -1)) :
			//		array('number_tags_add_exceeded_x', vb_number_format($remainingTags * -1));
				$errors['threadmax'] = array('number_tags_add_exceeded_x', vb_number_format($remainingTags * -1));

				$allowedTagCount = count($validTags) + $remainingTags;
				if ($allowedTagCount > 0)
				{
					$validTags = array_slice($validTags, 0, count($validTags) + $remainingTags);
				}
				else
				{
					$validTags = array();
				}
			}
		}
		return $validTags;
	}

	/**
	*	Handle any content specific changes that are required when the main tag data
	*	changes.
	*/
	public function rebuildContentTags() {
		//intentionally does nothing by default.  A hook for subclasses to handle
	}


	/**
	*	Get the number of existing tags for this item
	*
	*	@return int the tag count
	*/
	public function fetchExistingTagCount()
	{
		if (!is_null($this->tags))
		{
			$this->fetchExistingTagList();
		}
		return count($this->contentinfo);
	}


	/**
	*	Get the list of tags associated with this item
	*
	* @return array Array of tag text for the associated tags
	*/
	public function fetchExistingTagList()
	{
		if (!is_null($this->tags))
		{
			return $this->tags;
		}

		if ($this->contentinfo === null)
		{
			$this->loadContentInfo();
		}

		$this->tags = array();
		foreach ($this->contentinfo AS $tag)
		{
			$this->tags[] = $tag['tagtext'];
		}

		return $this->tags;
	}


	/**
	*	Get the html rendered tag list for this item.
	*
	*	Allows types to override the display of tags based on their own formatting
	*/
	public function fetchRenderedTagList()
	{
		$taglist = $this->fetchExistingTagList();
		return fetch_tagbits(implode(", ", $taglist));
	}

	/**
	*	Allow access to the content array
	*
	* Lazy loads content info array.  Used internally so that we only load this if
	* we actually need it (and don't load it multiple times).
	*
	*	This function is exposed publicly for the benefit of code that needs the
	* content array but may not know precisely how to load it (because it isn't
	* aware of the type of content being tagged).
	*
	* Actually, this is a bad idea precisely because the code doesn't know what
	* type its dealing with.  Its a paint to have to create a bunch of getters
	* for the details, but we need to do just that to ensure a consistant
	* interface.
	*
	*	@return array Content info array.
	*/
	protected function fetchContentInfo()
	{
		if ($this->contentinfo === null)
		{
			$this->contentinfo = $this->loadContentInfo();
		}
		return $this->contentinfo;
	}

	public function getTitle()
	{
		//probably shouldn't leave this as the default, but provides
		//shim code for existing implementations
		$contentinfo = $this->fetchContentInfo();
		return $contentinfo['title'];
	}

	/**
	*	Is the tag cloud cachable
	*
	*	This function does not rely on the content information and can be
	* called from an object initialized with a null nodeid
	*/
	public function isCloudCachable()
	{
		return false;
	}

	/********************************************************
	*	Management Page Methods
	********************************************************/
	/*
	 This part of the interface should not be considered somwhere volatile

	 These don't really belong here in their current form
	 They probably don't belong anywhere in their current form
	 but until we figure out a better way to deal with it
	 we're kind of stuck with them.
	*/

	/**
	*	Get the return url for the tag UI
	*
	* This is where we go when we finish saving tag changes.
	*
	*/
	public function fetchReturnUrl()
	{
		$cleaner = vB::get_cleaner();
		$cleaned = $cleaner->clean('returnurl', vB_Cleaner::TYPE_STR);

		if ($cleaned['returnurl'])
		{
			return $cleaned['returnurl'];
		}
		else
		{
			return "";
		}
	}

	/**
	* Get the page navigation elements for the tag UI
	*/
	public function fetchPageNav()
	{
		global $vbphrase;

		// navbar and output
		$navbits = array();
		$navbits[''] = $vbphrase['tag_management'];
		$navbits = construct_navbits($navbits);
		return $navbits;
	}

	/**
	*	Verify that the current user has basic rights to manipulate tags for this item
	*
	*	Redirects with appropriate error message if the user can't access the UI.
	*	Its ugly to put it here but the rules very by content type and we want to
	*	hide that from the tag UI.
	*
	*	@return should not return if the user does not have permissions.
	*/
	public function verifyUiPermissions()
	{
		$options = vB::getDatastore()->get_value('options');

		if (!$options['threadtagging'])
		{
			throw new vB_Exception_Api('no_permission');
		}

		if ( !($this->canAddTag() OR $this->canManageTag()) )
		{
			throw new vB_Exception_Api('no_permission');
		}
	}

	/*
	 * Return the taglist of the current node.
	 * The taglist info for each tag is userid, tagtext, tagid
	 */
	public function getNodeTags()
	{
		if ($this->contentinfo === null)
		{
			$this->loadContentInfo();
		}

		return array_values($this->contentinfo);
	}

	/********************************************************
	*	Private Methods
	********************************************************/

	/**
	*	Load the Content Info
	*
	* Actually loads the content info for this type
	*
	*	@return array The content info
	*/
	protected function loadContentInfo()
	{
		$this->contentinfo = array();
		$query = $this->assertor->assertQuery('vBForum:getTags',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'nodeid' => $this->nodeid));
		if ($query AND $query->valid())
		{
			$tag = $query->current();
			while ($query->valid())
			{
				$this->contentinfo[$tag['tagid']] = $tag;
				$tag = $query->next();
			}
		}
	}

	/**
	*	Invalidates the cached list of tags for this item.
	*
	*	Should be called by any method that alters the tag
	* types.
	*/
	protected function invalidateTagList()
	{
		$this->contentinfo = $this->tags = null;
	}

	protected function fetchTagInfo($tag)
	{
		if (sizeof($this->contentinfo) == 0)
		{
			$this->contentinfo = $this->loadContentInfo();
		}

		if (sizeof($this->contentinfo) > 0)
		{
			foreach($this->contentinfo as $nodeTag)
			{
				if (in_array($tag, $nodeTag))
				{
					$tag = $nodeTag;
				}
			}
			return $tag;
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 27657 $
|| ####################################################################
\*======================================================================*/
