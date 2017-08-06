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

/**
* Class to do data operations for Categories
*
* @package	vBulletin
* @author	Kevin Sours
* @version	$Revision:  $
* @date		$Date: $
*
*/
class vB_DataManager_category extends vB_DataManager
{
	/*
		Some high level fetch functions.  These don't really use the DM class
		at all, but I want to put the sql all in one place and this is as
		good as any.
	*/

	public function get_all_categories()
	{
		$set = $this->assertor->getRows($this->table, array(), 'displayorder');
		$rows = array();
		foreach ($set as $row)
		{
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	* Array of recognised and required fields for keywords, and their types
	*
	*	Should be protected, but base class is php4 and defines as public by
	* default.
	*
	* @var	array
	*/
	public $validfields = array (
		"categoryid"		=> array(vB_Cleaner::TYPE_UINT,		vB_DataManager_Constants::REQ_AUTO, vB_DataManager_Constants::VF_METHOD, 'verify_nonzero'),
		"parentid"			=> array(vB_Cleaner::TYPE_INT,		vB_DataManager_Constants::REQ_NO,		vB_DataManager_Constants::VF_METHOD, 'verify_nonzero_or_negone'),
		"styleid"				=> array(vB_Cleaner::TYPE_INT,		vB_DataManager_Constants::REQ_NO,		vB_DataManager_Constants::VF_METHOD, 'verify_nonzero_or_negone'),
		"labeltext"			=> array(vB_Cleaner::TYPE_NOHTML, vB_DataManager_Constants::REQ_YES,	vB_DataManager_Constants::VF_METHOD, 'verify_nonempty'),
		"labelhtml"			=> array(vB_Cleaner::TYPE_NOTRIM, vB_DataManager_Constants::REQ_NO),
		"displayorder"	=> array(vB_Cleaner::TYPE_INT,		vB_DataManager_Constants::REQ_NO)
	);

	public $table = "category";
	public $condition_construct = array('categoryid = %1$d', 'categoryid');

	public function fetch_by_id($id)
	{
		$this->set_condition("categoryid = " . intval($id));
		return $this->load_existing();
	}

	public function make_child($parentid)
	{
		if ($parentid == -1 or $parentid > 0)
		{
			$this->set('parentid', $parentid);
			return $this->save();
		}
		return false;
	}

	public function make_top()
	{
		return $this->make_child(-1);
	}

	public function get_root_nodes()
	{
		return $this->get_children_from_parentid(-1);
	}

	public function get_siblings()
	{
		return $this->get_children_from_parentid($this->fetch_field("parentid"));
	}

	public function get_children()
	{
		return $this->get_children_from_parentid($this->fetch_field("categoryid"));
	}

	public function reorder_siblings($childids)
	{
		$child_positions = array_flip($childids);
		$children = $this->get_siblings();

		$extra = count($child_positions);
		foreach ($children as $child)
		{
			$id = $child->fetch_field("categoryid");
			if (array_key_exists($id, $child_positions))
			{
				$order = $child_positions[$id];
			}
			else
			{
				$order = $extra;
				$extra++;
			}
			$child->set("displayorder", $order);
			$child->save();
		}
	}

	public function pre_delete()
	{
		//cascade deletes
		foreach ($this->get_children() as $child)
		{
			$child->delete();
		}


		return true;
	}

	private function promote_children()
	{
		$this->assertor->update($this->table,
				array(
						'parentid' => intval($this->fetch_field('parentid')),
						'displayorder' => intval($this->fetch_field('displayorder'))
				),
				array('parentid' => intval($this->fetch_field('categoryid')))
		);
	}

	private function get_children_from_parentid($parentid)
	{
		$set = $this->assertor->getRows($this->table, array('parentid' => intval($parentid)));
		$children = array();
		foreach ($set as $row)
		{
			$child = new vB_Datamanager_Category($this->registry, vB_DataManager_Constants::ERRTYPE_ARRAY);
			$result = $child->set_existing($row);
			$children[] = $child;

			//force the reference to change so that we don't end up with every
			//array linked (which makes them all change to be the same when one
			//changes).
			unset($row);
		}
		return $children;
	}


	/*
		Should probably get moved to the base class, but I'm not quite ready to
		do that.
	*/
	protected function load_existing()
	{
		if ($this->condition)
		{
			$fields = array_keys($this->validfields);
			$result = $this->assertor->getRow($this->table, $this->condition);

			if ($result)
			{
				$this->set_existing($result);

				//reset to the default condition so that we use the primary key to
				//do the update.  This is especially important if somebody does something
				//stupid and calls this function on a condition that selects more than one
				//record -- we could end up updating multiple records if we don't do this.
				$this->set_condition('');
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
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/