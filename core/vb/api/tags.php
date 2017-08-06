<?php if (!defined('VB_ENTRY')) die('Access denied.');

/**
 * vB_Api_Tags
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Tags extends vB_Api
{
	protected $usercontext;
	protected $tagsObj = array();
	protected $errors = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		$this->usercontext = vB::getUserContext();
	}

	/**
	 * Adds new tags and/or deletes tags (added by the current user) if they have removed them from the list
	 *
	 * @param	int	Node ID
	 * @param	array	List of tags
	 *
	 * @return	array 	List of all tags on the node
	 */
	public function updateUserTags($nodeid, array $taglist = array())
	{
		$nodeid = (int) $nodeid;
		$userid = (int) $this->usercontext->fetchUserId();

		if (empty($this->tagsObj[$nodeid]))
		{
			$this->tagsObj[$nodeid] = new vB_Tags($nodeid);
		}

		$nodeTagInfo = $this->getNodeTags($nodeid);
		$nodeTags = $nodeTagInfo['tags'];

		$canManageTags = $this->tagsObj[$nodeid]->canManageTag();

		$remove = array();
		foreach ($nodeTags AS $tag)
		{
			if ($tag['userid'] == $userid OR $canManageTags)
			{
				if (!in_array($tag['tagtext'], $taglist))
				{
					$remove[] = $tag['tagtext'];
				}
			}
		}

		if (!empty($remove))
		{
			$removeResult = $this->removeTags($nodeid, $remove);
		}

		if (!empty($taglist))
		{
			return $this->addTags($nodeid, $taglist);
		}
		else if (isset($removeResult)) //this means user removes all the tags previously added
		{
			return $removeResult;
		}
		return true;
	}

	/*
	 * Add tags to the current item
	 * implements vB_Tag::addTagsToContent
	 *
	 * @param	NodeId
	 * @param	Taglist to add
	 *
	 * @return	Comma-separated list of all tags on the node
	 */
	public function addTags($nodeid, $taglist)
	{
		if (empty($this->tagsObj[$nodeid]))
		{
			$this->tagsObj[$nodeid] = new vB_Tags($nodeid);
		}

		$errors = $this->tagsObj[$nodeid]->addTagsToContent($taglist);
		if (sizeof($errors) > 0)
		{
			throw $this->getExceptionFromErrors($errors);
		}

		return $this->getNodeTags($nodeid);
	}

	/*
	 * @uses	Inserts new tags to database; node independent
	 * @param	String, TagText
	 * @return	Mixed, response
	 */
	public function insertTags($tagtext)
	{
		$response = array();
		$exception = new vB_Exception_Api();
		$tagdm = new vB_DataManager_Tag(vB_DataManager_Constants::ERRTYPE_ARRAY);
		if ($tagdm->fetch_by_tagtext($tagtext))
		{
			$exception->add_error("tag_exists", array());
		}

		if ($tagtext AND is_string($tagtext))
		{
			require_once(DIR . '/includes/class_taggablecontent.php');
			$valid = vB_Taggable_Content_Item::filter_tag_list(array($tagtext), $errors, false);
		}
		else
		{
			$exception->add_error("invalid_tag_value", array());
		}

		if (!empty($valid))
		{
			$tagdm->set('tagtext', $valid[0]);
			$tagdm->set('dateline', vB::getRequest()->getTimeNow());
			if ($tagdm->errors)
			{
				$exception->add_error($tagdm->errors, array());
			}
			$tagdm->save();
			$response['result'] = true;
		}
		else
		{
			if (!empty($errors))
			{
				foreach ($errors as $error)
				{
					$phraseid = $error[0];
					unset($error[0]);
					$exception->add_error($phraseid, $error);
				}
			}
			else
			{
				$exception->add_error("invalid_tag_value", array());
			}
		}

		//Exception Handling
		if ($exception->has_errors())
		{
			throw $exception;
		}

		return $response;
	}

	/*
	 * @uses		Updates existing tags from database; node independent
	 * @param	String, tag text
	 * @return	Mixed, response
	 */
	public function updateTags($tagtext)
	{
		$response = array();
		$exception = new vB_Exception_Api();
		$tagdm = new vB_DataManager_Tag(vB_DataManager_Constants::ERRTYPE_ARRAY);
		if (!$tagdm->fetch_by_tagtext($tagtext))
		{
			$exception->add_error("tag_not_exists", array());
		}
		else
		{
			$tagdm->set('tagtext', $tagtext);
			$tagdm->set('dateline', vB::getRequest()->getTimeNow());
			if ($tagdm->errors)
			{
				$exception->add_error($tagdm->errors, array());
			}
			$response['result'] = $tagdm->save();
		}

		//Exception Handling
		if ($exception->has_errors())
		{
			throw $exception;
		}

		return $response;
	}

	/*
	 * @uses	Create new tag for synonim tags
	 * @param	Array, Array of tags to merge
	 * @param	Integer, Id of canonical tag
	 * @return	Mixed response
	 */
	public function createSynonyms($tagList, $targetid)
	{
		$exception = new vB_Exception_Api();
		$mergetagdm = new vB_DataManager_Tag(vB_DataManager_Constants::ERRTYPE_ARRAY);

		//clear existing because they may be changed here.
		$this->tagsObj = array();

		$target = new vB_DataManager_Tag(vB_DataManager_Constants::ERRTYPE_ARRAY);
		if (!$x= $target->fetch_by_id($targetid))
		{
			throw new vB_Exception_Api('tag_not_exist');
		}

		//if our targe is a synonym, make sure that we merge to its canonical tag 
		if ($target->is_synonym())
		{
			$targetid = $target->fetch_field('canonicaltagid');
		}
		
		/*
		//Check that target tag exists
		$target = vB::getDbAssertor()->getRow('vBForum:tag',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'tagid' => $targetid)
		);

		if (!$target)
		{
			throw new vB_Exception_Api('tag_not_exist');
		}
		 */

		//Make synonym for every tag in the list
		foreach ($tagList AS $mergetagid)
		{
			if ($mergetagid == $targetid)
			{
				//making a tag a synonym of itself will cause bad things to happen.
				continue;
			}
			if ($mergetagdm->fetch_by_id($mergetagid))
			{
				$mergetagdm->make_synonym($targetid);
			}
			else
			{
				$exception->add_error("tag_not_exist", array());
			}
		}

		if ($mergetagdm->errors)
		{
			throw $mergetagdm->get_exception();
		}
		else
		{
			$response['result'] = true;
		}

		//Exception Handling
		if ($exception->has_errors())
		{
			throw $exception;
		}

		return $response;
	}

	/*
	 * @uses	Promote tags
	 * @param	Array, Tags
	 * @return	True if success
	 */
	public function promoteTags($taglist)
	{
		$exception = new vB_Exception_Api();

		//Check that tags exist and they are synonyms
		$target = vB::getDbAssertor()->assertQuery('vBForum:tag',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'tagid', 'value' => $taglist, 'operator' => vB_dB_Query::OPERATOR_EQ)
				)
			)
		);
		if ($target AND $target->valid())
		{
			foreach ($target as $tag) 
			{
				if ($tag['canonicaltagid'] == 0)
				{
					$existTag[] = $tag['tagid'];
				}
			}

			$taglist = array_diff($taglist, $existTag);

			if (!empty($taglist))
			{
				foreach ($taglist as $tagid)
				{
					$tagdm = new vB_DataManager_Tag(vB_DataManager_Constants::ERRTYPE_ARRAY);
					if ($tagdm->fetch_by_id($tagid))
					{
						$tagdm->make_independent();
					}
					if ($tagdm->errors)
					{
						throw $tagdm->get_exception();
					}
				}
				if (!$tagdm->errors)
				{
					$response['result'] = true;
				}
			}
			else
			{
				$exception->add_error("cant_promote_tag_to_tag", array());
			}
		}
		else
		{
			$exception->add_error("tag_not_exist", array());
		}

		//Exception Handling
		if ($exception->has_errors())
		{
			throw $exception;
		}

		return $response;
	}

	/*
	 * @uses	Get synonyms of given tag
	 * @param	array, Tag
	 * @return	array, synonyms
	 */
	public function getTagSynonyms($tag)
	{
		$exception = new vB_Exception_Api();
		$exists = vB::getDbAssertor()->getRow('vBForum:tag',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'tagid' => $tag['tagid']
			)
		);
		if ($exists)
		{
			$tagdm = new vB_DataManager_Tag(vB_DataManager_Constants::ERRTYPE_ARRAY);
			$tagdm->set_existing($tag);
			return $tagdm->fetch_synonyms();
		}
		else
		{
			$exception->add_error("tag_not_exist", array());
		}

		//Exception Handling
		if ($exception->has_errors())
		{
			throw $exception;
		}
	}

	/*
	 * @uses	Get canonical tag of given tag
	 * @param	array, Tag
	 * @return	object, canonical tag
	 */
	public function getCanonicalTag($tag)
	{
		$exists = vB::getDbAssertor()->getRow('vBForum:tag',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'tagid' => $tag
			)
		);
		if ($exists)
		{
			$tagdm = new vB_DataManager_Tag(vB_DataManager_Constants::ERRTYPE_ARRAY);
			$tagdm->fetch_by_id($tag);
			return $tagdm->fetch_canonical_tag();
		}
		else
		{
			throw new vB_Exception_Api("tag_not_exist");
		}
	}

	/*
	 * Remove tags from an item
	 * Implements vB_Tag::deleteTag
	 *
	 * @param	NodeId
	 * @param	Tags to delete (if not specified it will delete all tags from node).
	 */
	public function removeTags($nodeid, $tags = '')
	{
		if (empty($this->tagsObj[$nodeid]))
		{
			$this->tagsObj[$nodeid] = new vB_Tags($nodeid);
		}

		$errors = $this->tagsObj[$nodeid]->deleteTags($tags);
		if (sizeof($errors))
		{
			throw $this->getExceptionFromErrors($errors);
		}
	}

	/*
	 * @uses		Deletes tag from database, node independent function
	 * @param	Array Tags Id to delete
	 * @return	Mixed response
	 */
	public function killTags($killTagList)
	{
		if (!is_array($killTagList))
		{
			$killTagList = array($killTagList);
		}

		$exception = new vB_Exception_Api();
		$killtagdm = new vB_DataManager_Tag(vB_DataManager_Constants::ERRTYPE_ARRAY);

		//clear existing because they may be changed here.
		$this->tagsObj = array();	

		//Check that tags exist and they are synonyms
		$target = vB::getDbAssertor()->assertQuery('vBForum:tag',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'tagid' => $killTagList
			)
		);
		if ($target AND $target->valid())
		{
			foreach ($killTagList AS $killtagid)
			{
				if ($killtagdm->fetch_by_id($killtagid))
				{
					$killtagdm->delete();
				}
			}
		}
		else
		{
			$exception->add_error("tag_not_exists", array());
		}

		//Exception Handling
		if ($exception->has_errors())
		{
			throw $exception;
		}

		return $response;
	}

	/*
	 * Gets the tag list from an specific node.
	 * Implements vB_Tags::fetchExistingTagList
	 *
	 * @param	NodeId
	 *
	 * @return	The taglist from node
	 */
	public function getTagsList($nodeid)
	{
		if (empty($this->tagsObj[$nodeid]))
		{
			$this->tagsObj[$nodeid] = new vB_Tags($nodeid);
		}
		return $this->tagsObj[$nodeid]->fetchExistingTagList();
	}

	/*
	 * Merge tags from nodes.
	 * Implements vB_Tags::mergeTagAttachments
	 *
	 * @param	TargetNodeId (Node to merge tags in)
	 * @param	SourceNodeId (Node to merge tags from)
	 */
	public function mergeTags($nodeid, $sourceid)
	{
		if (empty($this->tagsObj[$nodeid]))
		{
			$this->tagsObj[$nodeid] = new vB_Tags($nodeid);
		}

		$this->tagsObj[$nodeid]->mergeTagAttachments($sourceid);
	}

	/*
	 * Move tags from one node to another one.
	 * Implements vB_Tags::moveTagAttachments
	 *
	 * @param	TargetNodeId (Node to move tags in)
	 * @param	SourceNodeId (Node to move tags from)
	 */
	public function moveTags($nodeid, $sourceid)
	{
		if (empty($this->tagsObj[$nodeid]))
		{
			$this->tagsObj[$nodeid] = new vB_Tags($nodeid);
		}

		//If we have the existing tags loaded in the source file we need to remove them.
		if (is_array($sourceid))
		{
			foreach ($sourceid as $removeid)
			{
				if (array_key_exists($removeid, $this->tagsObj))
				{
					//there is no public method to clear the
					unset($this->tagsObj[$sourceid]);
				}
			}

		}
		else
		{
			if (array_key_exists($sourceid, $this->tagsObj))
			{
				//there is no public method to clear the
				unset($this->tagsObj[$sourceid]);
			}
		}
		$this->tagsObj[$nodeid]->moveTagAttachments($sourceid);
	}

	/*
	 * Copy tags from a node to another one.
	 * Implements vB_Tags::copyTagAttachments.
	 *
	 * @param	TargetNodeId (Node to copy tags in)
	 * @param	SourceNodeId (Node to copy tags from)
	 */
	public function copyTags($nodeid, $sourceid)
	{
		if (empty($this->tagsObj[$nodeid]))
		{
			$this->tagsObj[$nodeid] = new vB_Tags($nodeid);
		}
		$this->tagsObj[$nodeid]->copyTagAttachments($sourceid);
	}

	/*
	 * Get tags with full info (userid, tagid, tagtext) from node.
	 * Implements vB_Tags::getNodeTags
	 *
	 * @param	NodeId
	 * @return	Tags from node
	 */
	public function getNodeTags($nodeid)
	{
		if (empty($this->tagsObj[$nodeid]))
		{
			$this->tagsObj[$nodeid] = new vB_Tags($nodeid);
		}

		$userid = $this->usercontext->fetchUserId();
		$limits = $this->tagsObj[$nodeid]->fetchTagLimits();

		return array(
			'tags' => $this->tagsObj[$nodeid]->getNodeTags(),
			'userid' => $userid,
			'nodeid' => $nodeid,
			'maxtags' => $limits['content_limit'],
			'maxusertags' => $limits['user_limit'],
			'canmanagetags' => $this->tagsObj[$nodeid]->canManageTag(),
		);
	}

	public function getAutocomplete($searchStr, $limitstart = 0, $limitnumber = 25)
	{
		$query = vB::getDbAssertor()->assertQuery(
			'vBForum:getPopularTags', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'searchStr' => $searchStr
			)
		);
		$popular_tags = array();

		while($query AND $query->valid() AND $tag = $query->current())
		{
			$popular_tags[] = array(
				'title' =>	$tag['tagtext'],
				'value' =>	$tag['userid'],
				'id' =>		$tag['tagid']
			);
			$query->next();
		}
		return array('suggestions' => $popular_tags);
	}

	/**
	 * Get an array of tags for building tag cloud
	 *
	 * @param int $levels Tag cloud levels
	 * @param int $limit How many tags to be fetched
	 * @param string $type The type of tag cloud
	 * @return array
	 */
	public function fetchTagsForCloud($taglevels = 5, $limit = 20, $type = 'nodes')
	{
		switch ($type) {
			case 'search':
				$function = 'fetchSearchTagsForCloud';
			break;
			case 'nodes':
			default:
				$function = 'fetchTagsForCloud';
			break;
		}
		$tags = vB::getDbAssertor()->getRows($function, array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			vB_dB_Query::PARAM_LIMIT => intval($limit)
		));
		$totals = array();
		foreach ($tags as $currenttag)
		{
			$totals[$currenttag['tagid']] = $currenttag['searchcount'];
		}

		// fetch the stddev levels
		$levels = fetch_standard_deviated_levels($totals, $taglevels);

		// assign the levels back to the tags
		foreach ($tags AS $tagtext => $tag)
		{
			$tags[$tagtext]['level'] = $levels[$tag['tagid']];
			$tags[$tagtext]['tagtext_url'] = urlencode(unhtmlspecialchars($tag['tagtext']));
		}

		// sort the categories by title
		uksort($tags, 'strnatcasecmp');

		return $tags;
	}

	/*
	 * @uses		Fetch tag by label
	 * @param	String, label
	 * @return	Mixed, response
	 */
	public function fetchTagByText($tagtext)
	{
		$response = array();
		$tag = vB::getDbAssertor()->getRow('vBForum:tag',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'tagtext' => vB_String::vBStrToLower($tagtext)
			)
		);
		if ($tag)
		{
			$response['tag'] = $tag;
		}
		else
		{
			//$response['errors'] = "tag_not_exists";
			throw new vB_Exception_Api("tag_not_exist");
		}

		return $response;
	}

	/**
	 * Saves the seached for tags so we can build a search tag cloud based on it
	 * @param array $tagIds the ids of the tags that it was searched for
	 */
	public function logSearchTags($tagIds)
	{
		if (!empty($tagIds))
		{
			// create new tags
			$tagIdsInsert = array();
			$timenow = vB::getRequest()->getTimeNow();
			foreach ($tagIds AS $tagId)
			{
				$tagIdsInsert[] = array( $tagId, $timenow);
			}
			vB::getDbAssertor()->assertQuery('vBDBSearch:tagsearch', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_MULTIPLEINSERT,
				vB_dB_Query::FIELDS_KEY => array('tagid', 'dateline'), vB_dB_Query::VALUES_KEY => $tagIdsInsert));
		}
	}


	protected function getExceptionFromErrors($errors)
	{
		$e = new vB_Exception_Api();
		foreach($errors AS $error)
		{
			if (is_array($error))
			{
				$phraseid = array_shift($error);
				$e->add_error($phraseid, $error);
			}
			else
			{
				$e->add_error($error, array());
			}
		}

		return $e;
	}
}
