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

class vB_Upgrade_500b18 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500b18';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Beta 18';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Beta 17';

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

	/** Set Imported Blog Post Url Identities **/
	public function step_1($data = NULL)
	{
		if (isset($this->registry->products['vbblog']) AND $this->registry->products['vbblog'])
		{
			$batchsize = 2000;
			$startat = intval($data['startat']);
			$assertor = vB::getDbAssertor();
			if(!isset($data))
			{
				$data = array();
			}

			if ($startat == 0)
			{
				$this->show_message($this->phrase['version']['500b18']['fixing_blog_post_url_identities']);
			}

			if (!isset($data['maxoldid']))
			{
				$maxOldIdQuery = $assertor->getRow('vBInstall:getMaxImportedBlogStarter', array());
				$data['maxoldid'] = intval($maxOldIdQuery['maxoldid']);
			}

			if ($startat > $data['maxoldid'])
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}

			$blogNodes = $assertor->assertQuery('vBForum:node', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::COLUMNS_KEY => array('nodeid', 'title'),
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'oldid', 'value' => $startat, 'operator' => vB_dB_Query::OPERATOR_GT),
					array('field' => 'oldid', 'value' => ($startat + $batchsize + 1), 'operator' => vB_dB_Query::OPERATOR_LT),
					array('field' => 'oldcontenttypeid', 'value' => 9985, 'operator' => vB_dB_Query::OPERATOR_EQ),
				),
			));

			$urlIdentNodes = array();
			foreach($blogNodes AS $key => $node)
			{
				$node['urlident'] = vB_String::getUrlIdent($node['title']);
				$urlIdentNodes[] = $node;
			}
			$assertor->assertQuery('vBInstall:updateUrlIdent', array('nodes' => $urlIdentNodes));
			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
			return array('startat' => ($startat + $batchsize), 'maxoldid' => $data['maxoldid']);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Set Imported Group Discussion Url Identities **/
	public function step_2($data = NULL)
	{
		if ($this->tableExists('socialgroup') AND $this->tableExists('discussion') AND $this->tableExists('groupmessage'))
		{
			$batchsize = 2000;
			$startat = intval($data['startat']);
			$assertor = vB::getDbAssertor();
			$discussionTypeid = vB_Types::instance()->getContentTypeID('vBForum_SocialGroupDiscussion');
			if(!isset($data))
			{
				$data = array();
			}

			if ($startat == 0)
			{
				$this->show_message($this->phrase['version']['500b18']['fixing_group_discussion_url_identities']);
			}

			if (!isset($data['maxoldid']))
			{
				$maxOldIdQuery = $assertor->getRow('vBInstall:getMaxSGDiscussion', array('discussionTypeid' => $discussionTypeid));
				$data['maxoldid'] = intval($maxOldIdQuery['maxoldid']);
			}

			if ($startat > $data['maxoldid'])
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}

			$discussionNodes = $assertor->assertQuery('vBForum:node', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::COLUMNS_KEY => array('nodeid', 'title'),
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'oldid', 'value' => $startat, 'operator' => vB_dB_Query::OPERATOR_GT),
					array('field' => 'oldid', 'value' => ($startat + $batchsize + 1), 'operator' => vB_dB_Query::OPERATOR_LT),
					array('field' => 'oldcontenttypeid', 'value' => $discussionTypeid, 'operator' => vB_dB_Query::OPERATOR_EQ),
				),
			));

			$urlIdentNodes = array();
			foreach($discussionNodes AS $key => $node)
			{
				$node['urlident'] = vB_String::getUrlIdent($node['title']);
				$urlIdentNodes[] = $node;
			}
			$assertor->assertQuery('vBInstall:updateUrlIdent', array('nodes' => $urlIdentNodes));
			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
			return array('startat' => ($startat + $batchsize), 'maxoldid' => $data['maxoldid']);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Drop csscolors column **/
	public function step_3()
	{
		if ($this->field_exists('style', 'csscolors'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'style', 1, 3),
				"ALTER TABLE " . TABLE_PREFIX . "style DROP COLUMN csscolors"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Drop css column **/
	public function step_4()
	{
		if ($this->field_exists('style', 'css'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'style', 2, 3),
				"ALTER TABLE " . TABLE_PREFIX . "style DROP COLUMN css"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Drop stylevars column **/
	public function step_5()
	{
		if ($this->field_exists('style', 'stylevars'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'style', 3, 3),
				"ALTER TABLE " . TABLE_PREFIX . "style DROP COLUMN stylevars"
			);
		}
		else
		{
			$this->skip_message();
		}

		$this->long_next_step();
	}

	/*Step #6
	 *
	 * Add index on node.lastauthorid
	 */
	public function step_6()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'node', 1, 1),
			'node',
			'node_lastauthorid',
			'lastauthorid'
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
