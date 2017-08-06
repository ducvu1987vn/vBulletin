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
/*
if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}
*/

class vB_Upgrade_500b9 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500b9';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Beta 9';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Beta 8';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '';

	/** We have some data elements with missing closure records. Let's repair them.
	 *
	 */
	public function step_1($data = array())
	{
		if ($this->tableExists('blog'))
		{
			$repairTypes = array(1 => vB_Types::instance()->getContentTypeID('vBForum_Album'), 2 => 9984, 3 => 9011,
				4 => 9986, 5=> 9990);

			if (isset($data['startat']))
			{
				$startat = $data['startat'];
			}
			else
			{
				$startat = 1;
			}
			$this->show_message(sprintf($this->phrase['version']['500b9']['fixing_closure_records_step_x'], $startat));
			$assertor = vB::getDbAssertor();
			$nodeids = array();
			$nodeQry = $assertor->assertQuery('vBInstall:missingClosureByType', array('oldcontenttypeid' =>$repairTypes[$startat],
				'batchsize' => 250));

			if(!$nodeQry->valid())
			{
				//If we have already scanned all the types, we are done.
				if ($startat >= 5)
				{
					$this->show_message(sprintf($this->phrase['core']['process_done']));
					return;
				}
				return(array('startat' => $startat + 1));
			}

			foreach($nodeQry AS $node)
			{
				$nodeids[] = $node['nodeid'];
			}

			//make sure we have no detritus for these nodes.
			//
			$assertor->assertQuery('vBForum:closure', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'child' => $nodeids));
			//First the record with depth = 0
			$assertor->assertQuery('vBInstall:addClosureSelfForNodes', array('nodeid' => $nodeids));
			//Then the parent records.
			$assertor->assertQuery('vBInstall:addClosureParentsForNodes', array('nodeid' => $nodeids));
			return(array('startat' => $startat));

		}
		else
		{
			$this->skip_message();
		}
	}

	/** Some blog posts are marked approved when they shouldn't be.
	 *
	 */
	public function step_2($data = array())
	{
		if ($this->tableExists('blog'))
		{
			$this->show_message(sprintf($this->phrase['version']['500b9']['fixing_blog_counts_step_x'], 1));
			vB::getDbAssertor()->assertQuery('vBInstall:updateBlogModerated', array());
		}
		else
		{
			$this->skip_message();
		}
	}

	/** The count was incorrect in the vb4 blog table, so let's correct
	 *
	 */
	public function step_3($data = array())
	{
		if ($this->tableExists('blog'))
		{
			$this->show_message(sprintf($this->phrase['version']['500b9']['fixing_blog_counts_step_x'], 2));
			vB::getDbAssertor()->assertQuery('vBInstall:updateBlogCounts', array());
		}
		else
		{
			$this->skip_message();
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/