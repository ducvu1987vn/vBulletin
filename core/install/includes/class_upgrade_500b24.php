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

class vB_Upgrade_500b24 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500b24';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Beta 24';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Beta 23';

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

	/*
	 * Step 2 - Drop primary key on editlog.postid
	 */
	function step_1()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'editlog', 1, 7),
			"ALTER TABLE " . TABLE_PREFIX . "editlog DROP PRIMARY KEY",
			self::MYSQL_ERROR_DROP_KEY_COLUMN_MISSING
		);
	}

	/*
	 * Step 2 - Add editlog.nodeid
	 */
	function step_2()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'editlog', 2, 7),
			'editlog',
			'nodeid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/*
	 * Step 3 - Add index on editlog.postid
	 */
	function step_3()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'editlog', 3, 7),
			'editlog',
			'postid',
			'postid'
		);
	}

	/*
	 * Step 4 - Update editlog.nodeid -- this will get non first posts.
	 */
	function step_4()
	{
		$postTypeId = vB_Types::instance()->getContentTypeID('vBForum_Post');
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'editlog', 4, 7),
				"UPDATE " . TABLE_PREFIX . "editlog AS e
				 INNER JOIN " . TABLE_PREFIX . "node AS n ON (e.postid = n.oldid AND n.oldcontenttypeid = {$postTypeId} AND e.postid <> 0)
				 SET e.nodeid = n.nodeid
		");
	}

	/*
	 * Step 5 - Update editlog.nodeid -- this will get first posts, which are now saved as thread type in vB5.
	 * We can't use oldcontenttypeid to tie these directly back to the editlog data so we use the thread_post table to get the threadid.
	 */
	function step_5()
	{
		$threadTypeId = vB_Types::instance()->getContentTypeID('vBForum_Thread');
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'editlog', 5, 7),
				"UPDATE " . TABLE_PREFIX . "editlog AS e
				 INNER JOIN " . TABLE_PREFIX . "thread_post AS tp ON (e.postid = tp.postid AND e.postid <> 0)
				 INNER JOIN " . TABLE_PREFIX . "node AS n ON (tp.threadid = n.oldid AND n.oldcontenttypeid = {$threadTypeId})
				 SET e.nodeid = n.nodeid
		");
	}

	/*
	 * Step 6 - We may have some orphan logs that reference a non-existant post/thread. These logs have nodeids of 0.
	 * Remove them.
	 */
	function step_6()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'editlog', 6, 7),
				"DELETE FROM " . TABLE_PREFIX . "editlog
				 WHERE nodeid = 0
		");
	}

	/*
	 * Step 7 - Add PRIMARY KEY on editlog.nodeid
	 */
	function step_7()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'editlog', 7, 7),
			"ALTER IGNORE TABLE " . TABLE_PREFIX . "editlog ADD PRIMARY KEY (nodeid)",
			self::MYSQL_ERROR_PRIMARY_KEY_EXISTS
		);
	}

	/* This index was missing from the schema file, so it
	wont exist in vB5 installations that were not upgrades.
	This will add it if its not there, otherwise it will do nothing. */
	public function step_8()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'node', 1, 1),
			'node',
			'node_unpubdate',
			array('unpublishdate')
		);
	}

	/*
	 * Step 9 - Update blog channel permissions,
	 * in order to comment to blog entries,  unregistered users should have
	 * forumpermission canreplyothers   and
	 * createpermission vbforum_text
	 */
	function step_9()
	{
		// create a user session..
		vB_Upgrade::createAdminSession();
		// need to grab the node id for blog channel and the usergroup id
		$blogNodeId = vB_Api::instance('Blog')->getBlogChannel();
		$unregisteredugid = vB_Api_UserGroup::UNREGISTERED_SYSGROUPID;
		// get the permissions..
		//$existingPermissions  = vB::getDbAssertor()->getRow('vBForum:permission', array('groupid' => $unregisteredugid, 'nodeid' => $blogNodeId));
		$existingPermissions = vB_ChannelPermission::instance()->fetchPermissions($blogNodeId, $unregisteredugid);
		$existingPermissions = $existingPermissions[$unregisteredugid];
		// get the bitfields..
		$forumpermissions = vB::getDatastore()->getValue('bf_ugp_forumpermissions');
		$createpermissions = vB::getDatastore()->getValue('bf_ugp_createpermissions');
		// set the permissions..
		$existingPermissions['forumpermissions'] |= $forumpermissions['canreplyothers'];
		$existingPermissions['createpermissions'] |= $createpermissions['vbforum_text'];
		// save the permissions..
		vB_ChannelPermission::instance()->setPermissions($blogNodeId, $unregisteredugid, $existingPermissions, true);
		$this->show_message(sprintf($this->phrase['version']['500b24']['blog_channel_permission_update']));
	}

	/**
	 * Update info for imported thread redirects
	 */
	public function step_10()
	{
		if ($this->tableExists('thread') AND $this->tableExists('threadredirect'))
		{
			$assertor = vB::getDbAssertor();
			
			vB_Types::instance()->reloadTypes();
			$forumTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Forum');
			$threadTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Thread');
			$redirectTypeId = vB_Types::instance()->getContentTypeId('vBForum_Redirect');

			$assertor->assertQuery('vBInstall:importRedirectThreads',
				array(
					'forumTypeId' => $forumTypeId,
					'redirectTypeId' => $redirectTypeId
				)
			);
			$imported = $assertor->affected_rows();

			if ($imported)
			{
				$this->show_message(sprintf($this->phrase['version']['500b24']['thread_redirect_import']));

				$nodes = $assertor->getRows('vBInstall:fetchRedirectThreads');

				$urlIdentNodes = array();
				$updateNodeids = array();
				foreach($nodes AS $node)
				{
					$node['urlident'] = vB_String::getUrlIdent($node['title']);
					$urlIdentNodes[] = $node;
					$updateNodeids[] = $node['nodeid'];
				}

				// Insert records into redirect table
				$assertor->assertQuery('vBInstall:insertRedirectRecords', array('nodes' => $updateNodeids, 'contenttypeid' => $threadTypeId));

				//Set the urlident values
				$assertor->assertQuery('vBInstall:updateUrlIdent', array('nodes' => $urlIdentNodes));

				//Now fix the starter
				$assertor->assertQuery('vBInstall:updateNodeStarter', array('contenttypeid' => 9980));

				//Now the closure record for depth=0
				$assertor->assertQuery('vBInstall:insertNodeClosure', array('contenttypeid' => 9980));

				//Add the closure records to root
				$assertor->assertQuery('vBInstall:insertNodeClosureRoot', array('contenttypeid' => 9980));

				// Update route
				vB::getDbAssertor()->assertQuery('vBInstall:updateRedirectRoutes', array('contenttypeid' => 9980));
			}
			else
			{
				$this->skip_message();
			}
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
