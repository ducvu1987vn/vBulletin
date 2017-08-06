<?php
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

require_once(DIR . '/includes/functions.php');
require_once(DIR . '/includes/class_taggablecontent.php');

/**
*/
class vB_DataManager_Tag extends vB_DataManager
{
	/**
	* Array of recognised and required fields for keywords, and their types
	*
	*	Should be protected, but base class is php4 and defines as public by
	* default.
	*
	* @var	array
	*/
	public $validfields = array (
		"tagid"						=> array(vB_Cleaner::TYPE_UINT, vB_DataManager_Constants::REQ_INCR, vB_DataManager_Constants::VF_METHOD, 'verify_nonzero'),
		"tagtext"					=> array(vB_Cleaner::TYPE_NOHTML, vB_DataManager_Constants::REQ_YES, vB_DataManager_Constants::VF_METHOD, 'verify_nonempty'),
		"canonicaltagid"	=> array(vB_Cleaner::TYPE_UINT, vB_DataManager_Constants::REQ_NO),
		"dateline"				=> array(vB_Cleaner::TYPE_UNIXTIME, vB_DataManager_Constants::REQ_YES, vB_DataManager_Constants::VF_METHOD, 'verify_nonzero')
	);

	public $table = 'vBForum:tag';
	public $keyField = 'tagid';

	public function __construct($errtype = vB_DataManager_Constants::ERRTYPE_STANDARD)
	{
		//we do need a registry object for now. But let's create it here.
		$this->registry = vB::get_registry();
		parent::__construct($errtype);
	}

	//*****************************************************************
	// Pseudo static methods
	// These aren't declared static because of the way dms work but
	// they don't require a record be set before calling.

	public function log_tag_search($tagid)
	{
		$set = vB::getDbAssertor()->assertQuery('vBForum:tagsearch',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'tagid' => $tagid,
				'dateline' => vB::getRequest()->getTimeNow()
			)
		);
	}


	//*****************************************************************
	// Regular methods

	public function fetch_by_id($id)
	{
		$this->set_condition(array("tagid" => intval($id)));
		return $this->load_existing();
	}

	public function fetch_by_tagtext($label)
	{
		$this->set_condition(array('tagtext' => $label));
		return $this->load_existing();
	}


	/**
	*	Return the array of fields that most the code expects to consume
	*/
	public function fetch_fields()
	{
		$fields = $this->existing;
		$table = $this->fetchTableBase($this->table);
		if (isset($this->{$table}))
		{
			foreach ($this->{$table} as $name => $value) {
				$field[$name] = $value;
			}
		}
		return $fields;
	}

	public function fetch_synonyms()
	{
		$set = vB::getDbAssertor()->assertQuery($this->table,
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'canonicaltagid' => $this->fetch_field("tagid")),
				array('field' => 'tagtext', 'direction' => vB_dB_Query::SORT_ASC)
		);
		$synonyms = array();
		if ($set AND $set->valid())
		{
			foreach ($set AS $row)
			{
				$synonym = new vB_DataManager_Tag(vB_DataManager_Constants::ERRTYPE_ARRAY);
				$result = $synonym->set_existing($row);
				$synonyms[] = $synonym;
			}
			//force the reference to change so that we don't end up with every
			//array linked (which makes them all change to be the same when one
			//changes).
		}
		return $synonyms;
	}

	public function is_synonym()
	{
		return $this->fetch_field("canonicaltagid") > 0;
	}

	public function fetch_canonical_tag()
	{
		if (!$this->is_synonym())
		{
			return false;
		}

		$tag = $synonym = new vB_DataManager_Tag(vB_DataManager_Constants::ERRTYPE_ARRAY);
		$tag->fetch_by_id($this->fetch_field("canonicaltagid"));
		return $tag;
	}

	public function attach_content($type, $id)
	{
		$tags_set = vB::getDbAssertor()->assertQuery('vBForum:InsertIgnoreTagContent2',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'tagid' => $this->fetch_field("tagid"),
					'id' => $id,
					'userid' => $this->registry->userinfo['userid'],
					'time' => vB::getRequest()->getTimeNow()
				)
		);
	}


	public function detach_content($type, $id)
	{
		vB::getDbAssertor()->assertQuery('vBForum:tagnode',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'entityid' => $id,
				'tagid' => $this->fetch_field("tagid"),
			)
		);
	}

	/**
	*	Make this tag a synonym for another tag
	*
	*	Any associations between this tag and content will be transfered to the parent.
	*
	* @param int $canonical_id id for the tag that this will become the synonym of
	*/
	public function make_synonym($canonical_id)
	{
		//if we already have synonyms attach them to the new canonical id as well
		//we only allow one level of synonyms
		foreach ($this->fetch_synonyms() as $synonym)
		{
			$synonym->make_synonym($canonical_id);
		}

		//actually make this a synonym
		$this->set("canonicaltagid", $canonical_id);
		$this->save();

		//fix any associated content items
		$associated_content = $this->fetch_associated_content();
		foreach ($associated_content as $nodeid)
		{
			$replace_threads[] = array(
				'tagid' => $canonical_id,
				'nodeid' => $nodeid,
				'userid' => $this->registry->userinfo['userid'],
				'dateline' =>  vB::getRequest()->getTimeNow()
			);
		}

		// add new tag to affected threads
		if (sizeof($replace_threads))
		{
			$tags_set = vB::getDbAssertor()->assertQuery('vBForum:replaceIntoTagContent',
					array('values' => $replace_threads)
			);
		}

		//clear old category associations.
		vB::getDbAssertor()->assertQuery('vBForum:tagnode',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, 'tagid' => $this->fetch_field("tagid"))
		);

		//update the tag search cloud datastore to reflect the change
		vB::getDbAssertor()->assertQuery('vBForum:tagsearch',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'tagid' => $canonical_id,
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'tagid', 'value' => $this->fetch_field("tagid"), 'operator'=> vB_dB_Query::OPERATOR_EQ)
				)
			)
		);

		return true;
	}

	/**
	* Unlink a synonym from its canonical parent.
	*
	* This will not reestablish any relationships between the tag and content that
	* may have been transfered to the parent when the tag was made a synonym.
	*/
	public function make_independent()
	{
		$this->set("canonicaltagid", 0);
		$this->save();
	}

	public function pre_save($doquery = true)
	{
		if (!$this->condition AND !$this->fetch_field('dateline'))
		{
			$this->set('dateline', vB::getRequest()->getTimeNow());
		}
		return true;
	}

	public function pre_delete($doquery = true)
	{
		//if we have synonyms for this tag, delete those as well.
		if ($doquery)
		{
			foreach ($this->fetch_synonyms() as $synonym)
			{
				$synonym->delete();
			}

			vB::getDbAssertor()->assertQuery('vBForum:tagnode',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, 'tagid' => $this->fetch_field("tagid"))
			);
			vB::getDbAssertor()->assertQuery('vBForum:tagsearch',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, 'tagid' => $this->fetch_field("tagid"))
			);
		}
		else
		{
			//probably should log the other non sql actions
			$this->log_query($contentsql);
			$this->log_query($searchtagssql);
		}
		return true;
	}

	private function fetch_associated_content()
	{
		$result = vB::getDbAssertor()->assertQuery('vBForum:tagnode',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'tagid' => $this->fetch_field("tagid"))
		);

		$associated_content = array();
		if($result AND $result->valid())
		{
			foreach ($result AS $row)
			{
				$associated_content[] = $row['nodeid'];
			}
		}
		return $associated_content;
	}

	/**
	*	Propogate any content specific effects for removing this tag from a content item
	*
	*	Some content tables may store the list of associated tags in as part of the record
	* in addition to the main association table.  While this is more efficient for lookups
	* we need to keep track of it when tags are removed.  This can happen because a tag
	* was deleted or because it was replaced with another tag.
	*
	* @param Array $associated_content An array of the form 'contenttypeid' => 'contentids'
	* 	containing all of the content ids associated with a particular tag
	 */
	/*
	private function handle_associated_content_removals($associated_content)
	{
		//As we add additional content types, we should look at how this logic can be
		//generalized

		// update thread taglists
		foreach ($associated_content as $contenttypeid => $contendids)
		{
			foreach ($contendids as $contendid)
			{
				$item = vB_Taggable_Content_Item::create($this->registry, $contenttypeid, $contendid);
				if ($item)
				{
					$item->rebuild_content_tags();
				}
			}
		}
	}
	*/

	/*
		Should probably get moved to the base class, but I'm not quite ready to
		do that.
	* Since this class builds conditions upon class member variables, it should be entirely replaced inside API class
	 * I think the API class has its own methods to retrive tags, probably this won't be needed anymore.
	*/

	protected function load_existing()
	{
		if ($this->condition)
		{
			$result = vB::getDbAssertor()->getRow('vBForum:tag', $this->condition);
			if ($result)
			{
				$this->set_existing($result);

				//reset to the default condition so that we use the primary key to
				//do the update.  This is especially important if somebody does something
				//stupid and calls this function on a condition that selects more than one
				//record -- we could end up updating multiple records if we don't do this.
				$this->set_condition(array());
			}
			else {
				//if we don't find a record, then reset the condition so that we will
				//do an insert rather than attempt to update an non existant record
				$this->condition = null;
			}
			return $result;
		}
		else
		{
			throw new Exception("Fetch existing requires a condition");
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 27979 $
|| ####################################################################
\*======================================================================*/
?>
