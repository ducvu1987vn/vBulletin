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

class vB_Upgrade_500a3 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a3';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 3';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 2';

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



	/***	Adding initial widgets*/
	function step_1()
	{
		$widgetRecords = $this->db->query_first("
			SELECT widgetid FROM " . TABLE_PREFIX . "widget WHERE `title` = 'PHP'
		");

		if (empty($widgetRecords) OR empty($widgetRecords['widgetid']))
		{
			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widget'));
			$this->db->query_write("
				INSERT INTO `" . TABLE_PREFIX . "widget`
				(`title`, `template`, `admintemplate`, `icon`, `isthirdparty`, `category`, `cloneable`)
				VALUES
				('PHP', 'widget_15', '', 'module-icon-default.png', 0, 'uncategorized', 0);
				"
			);
			$widgetid = $this->db->insert_id();

			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetdefinition'),
				"
				INSERT INTO `" . TABLE_PREFIX . "widgetdefinition`
				(`widgetid`, `field`, `name`, `label`, `defaultvalue`, `isusereditable`, `isrequired`, `displayorder`, `validationtype`, `validationmethod`, `data`)
				VALUES
				($widgetid, 'Text', 'title', 'Title', 'Unconfigured PHP Widget', 1, 1, 1, '', '', ''),
				($widgetid, 'LongText', 'code', 'PHP Code', 'PHP Widget Content', 1, 0, 2, '', '', '')
				"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_2()
	{
		$widgetRecords = $this->db->query_first("
			SELECT widgetid FROM " . TABLE_PREFIX . "widget WHERE `template` = 'widget_top_active_users'
		");

		if (empty($widgetRecords) OR empty($widgetRecords['widgetid']))
		{
			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widget'));
			$this->db->query_write("
				INSERT INTO `" . TABLE_PREFIX . "widget`
				(`title`, `template`, `admintemplate`, `icon`, `isthirdparty`, `category`, `cloneable`)
				VALUES
				('Top Active Users', 'widget_top_active_users', '', 'module-icon-default.png', 0, 'uncategorized', 0);
				"
			);
			$widgetid = $this->db->insert_id();

			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetdefinition'),
				"
				INSERT INTO `" . TABLE_PREFIX . "widgetdefinition`
				(`widgetid`, `field`, `name`, `label`, `defaultvalue`, `isusereditable`, `isrequired`, `displayorder`, `validationtype`, `validationmethod`, `data`)
				VALUES
				($widgetid, 'Text', 'maxUsers', 'Max top online users to show:', '20', 1, 0, 1, '', '', '')
				"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_3()
	{
		$activityWidget = $this->db->query_first("SELECT widgetid FROM " . TABLE_PREFIX . "widget WHERE title='Activity Stream';");

		if (empty($activityWidget) OR empty($activityWidget['widgetid']))
		{
			$this->skip_message();
			return;
		}

		$activityWidgetId = $activityWidget['widgetid'];

		$widgetDefRecords = $this->db->query_first("SELECT widgetid FROM " . TABLE_PREFIX . "widgetdefinition WHERE `widgetid` = " . $activityWidgetId);

		if (empty($widgetDefRecords) OR empty($widgetDefRecords['widgetid']))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetdefinition'),
				"
				INSERT INTO `" . TABLE_PREFIX . "widgetdefinition`
				(`widgetid`, `field`, `name`, `label`, `defaultvalue`, `isusereditable`, `isrequired`, `displayorder`, `validationtype`, `validationmethod`, `data`)
				VALUES
				($activityWidgetId, 'Select', 'filter_sort', 'Sort By', 'sort_recent', 1, 1, 1, '', '', 'a:2:{s:11:\"sort_recent\";s:11:\"Most Recent\";s:13:\"sort_featured\";s:13:\"Sort Featured\";}'),
				($activityWidgetId, 'Select', 'filter_time', 'Time', 'time_all', 1, 1, 2, '', '', 'a:4:{s:10:\"time_today\";s:5:\"Today\";s:13:\"time_lastweek\";s:9:\"Last Week\";s:14:\"time_lastmonth\";s:10:\"Last Month\";s:8:\"time_all\";s:8:\"All time\";}'),
				($activityWidgetId, 'Select', 'filter_show', 'Show', 'show_all', 1, 1, 3, '', '', 'a:3:{s:8:\"show_all\";s:3:\"All\";s:11:\"show_photos\";s:11:\"Photos only\";s:10:\"show_polls\";s:10:\"Polls only\";}'),
				($activityWidgetId, 'YesNo', 'filter_conversations', 'Show new conversations?', 1, 1, 0, 4, '', '', '')
				"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/***	adding relationship 'follow' to userlist table*/
	function step_4()
	{

		$this->run_query(sprintf($this->phrase['core']['altering_x_table'], 'userlist', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "userlist CHANGE type type ENUM('buddy', 'ignore', 'follow') NOT NULL DEFAULT 'buddy';");
	}

	/** Add the route for the profile pages**/
	function step_5()
	{
		$existing = $this->db->query_first("SELECT routeid FROM " . TABLE_PREFIX . "routenew WHERE prefix='profile';");

		if (!$existing OR empty($existing))
		{
			$newRoutes = array(array('name' => 'profile', 'title' =>  'User Profile', 'keywords' =>  'user profile', 'description' =>  'User Profile', 'template' => 'widget_profile'),
			array('name' => 'media', 'title' =>  'Media', 'keywords' =>  'upload media images video', 'description' =>  'Upload Media', 'template' => 'widget_media'),
			array('name' => 'editphoto', 'title' =>  'Edit Photos', 'keywords' =>  'edit photos gallery album', 'description' =>  'Edit Photos', 'template' => 'widget_photos'),
			array('name' => 'following', 'title' =>  'Following', 'keywords' =>  'following friends interest connected', 'description' =>  'Following', 'template' => 'widget_following'),
			array('name' => 'followers', 'title' =>  'Followers', 'keywords' =>  'followers friends interest connected', 'description' =>  'Followers', 'template' => 'widget_followers'),
			array('name' => 'groups', 'title' =>  'Groups', 'keywords' =>  'groups friends', 'description' =>  'Groups', 'template' => 'widget_groups'),
			array('name' => 'settings', 'title' =>  'User Settings', 'keywords' =>  'edit profile settings', 'description' =>  'User Profile Settings', 'template' => 'widget_settings'),
			 );

			foreach ($newRoutes as $newRoute)
			{
				//See if this widget exists
				$existing = $this->db->query_first("SELECT widgetid FROM " . TABLE_PREFIX . "widget WHERE title='" . $newRoute['name'] . "';");

				if (!$existing OR empty($existing))
				{
					$this->show_message('Create route for ' . $newRoute['title']);
					//Create a pagetemplate.
					$this->db->query_write("REPLACE INTO " . TABLE_PREFIX . "pagetemplate (title, screenlayoutid, content)
						values('" . $newRoute['title'] . " Template', 2, '');");
					$pagetemplateid = $this->db->insert_id();

					//We need to create a page. We have to put in a dummy routeid. We'll fix later
					$this->db->query_write("REPLACE INTO " . TABLE_PREFIX . "page
					(parentid, pagetemplateid, title, metakeywords, metadescription, routeid, moderatorid,
					displayorder, pagetype)
					VALUES (0, $pagetemplateid, '" . $newRoute['title'] . "',
					'" . $newRoute['keywords'] . "', '" . $newRoute['description'] . "',
					 19,  0, 2, '". vB_Page::TYPE_CUSTOM . "');");
					$pageid = $this->db->insert_id();

					//And a route
					$arguments = serialize(array('contentid' => $pageid));
					$this->db->query_write("REPLACE INTO " . TABLE_PREFIX . "routenew
					(name, prefix, regex,  class, controller, action, template,
	        		arguments, contentid)
					VALUES ('" . $newRoute['name'] . "', '" . $newRoute['name'] . "', '" . $newRoute['name'] . "',
					 'vB5_Route_Page', 'page', 'index', '',
	       			'$arguments', $pageid);");
					$routeid = $this->db->insert_id();

					//Correct the routeid for the page entry.
					$this->db->query_write("UPDATE " . TABLE_PREFIX . "page SET routeid= $routeid WHERE
					pageid = $pageid" );

					//We need to create a widget.

					$this->db->query_write("REPLACE INTO " . TABLE_PREFIX . "widget
					(title, template, admintemplate, icon, isthirdparty, category)
					VALUES('" . $newRoute['title'] . "', '" . $newRoute['template'] . "', '', 'module-icon-default.png', 0, 'uncategorized');");
					$widgetid = $this->db->insert_id();

					//And a couple of widget instances.
					$this->db->query_write("REPLACE INTO " . TABLE_PREFIX . "widgetinstance(pagetemplateid, widgetid, displaysection, displayorder, adminconfig)
						values($pagetemplateid, $widgetid, 0, 1, ''),
						($pagetemplateid, 2, 1,1, '');");

				}

			}

		}
		else
		{
			$this->skip_message();
		}


	}

	/***	Setting default adminConfig for activity stream widget */
	function step_6()
	{

		$this->run_query(
		sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetinstance'),
		"
		UPDATE " . TABLE_PREFIX . "widgetinstance
			SET adminconfig = 'a:4:{s:11:\"filter_sort\";s:11:\"sort_recent\";s:11:\"filter_time\";s:8:\"time_all\";s:11:\"filter_show\";s:8:\"show_all\";s:20:\"filter_conversations\";s:1:\"1\";}'
			WHERE widgetid = (SELECT widgetid FROM " . TABLE_PREFIX . "widget WHERE title = 'Activity Stream') AND adminconfig = ''
			"
		);
	}

	/**
	 * Add default header navbar items for Blogs
	 */
	function step_7()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'site'),
			"
				UPDATE " . TABLE_PREFIX . "site
				SET headernavbar = 'a:1:{i:0;a:2:{s:5:\"title\";s:5:\"Blogs\";s:3:\"url\";s:1:\"#\";}}'
				WHERE
					siteid = 1
						AND
					headernavbar = ''
			"
		);
	}

	function step_8()
	{
		$widgetRecords = $this->db->query_first("
			SELECT widgetid FROM " . TABLE_PREFIX . "widget WHERE `title` = 'Today\\'s Birthday'
		");

		if (empty($widgetRecords) OR empty($widgetRecords['widgetid']))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widget'),
				"
				INSERT INTO `" . TABLE_PREFIX . "widget`
				(`title`, `template`, `admintemplate`, `icon`, `isthirdparty`, `category`, `cloneable`)
				VALUES
				('Today\\'s Birthday', 'widget_birthday', '', 'module-icon-default.png', 0, 'uncategorized', 0);
				"
			);
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
