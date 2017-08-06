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

class vB_Upgrade_500a4 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a4';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 4';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 3';

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

	/***	Updating initial widget definition records ***/
	function step_1()
	{
		$skip_message = false;
		$search_results_widget = $this->db->query_first("
			SELECT widgetid FROM " . TABLE_PREFIX . "widget
			WHERE template = 'widget_search_results'");

		if (!empty($search_results_widget['widgetid']))
		{
			$widgetDefRecords = $this->db->query_first("
				SELECT widgetid FROM " . TABLE_PREFIX . "widgetdefinition WHERE name = 'searchResultTitle' AND widgetid = '".$search_results_widget['widgetid']."'
			");

			if (empty($widgetDefRecords) OR empty($widgetDefRecords['widgetid']))
			{
				$this->run_query(
					sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetdefinition'),
					"
					INSERT INTO `" . TABLE_PREFIX . "widgetdefinition`
					(`widgetid`, `field`, `name`, `label`, `defaultvalue`, `isusereditable`, `isrequired`, `displayorder`, `validationtype`, `validationmethod`)
					VALUES
					('".$search_results_widget['widgetid']."', 'Text', 'searchResultTitle', 'WidgetTitle', '', 1, 0, 1, '', '')
					"
				);
			}
			else
			{
				$skip_message = true;
			}

		}
		else
		{
			$skip_message = true;
		}

		$search_criteria_widget = $this->db->query_first("
			SELECT widgetid FROM " . TABLE_PREFIX . "widget
			WHERE template = 'widget_search_criteria'");

		if (!empty($search_criteria_widget['widgetid']))
		{
			$widgetDefRecords = $this->db->query_first("
				SELECT widgetid FROM " . TABLE_PREFIX . "widgetdefinition WHERE name = 'searchCriteriaTitle' AND widgetid = '".$search_criteria_widget['widgetid']."'
			");

			if (empty($widgetDefRecords) OR empty($widgetDefRecords['widgetid']))
			{
				$this->run_query(
					sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetdefinition'),
					"
					INSERT INTO `" . TABLE_PREFIX . "widgetdefinition`
					(`widgetid`, `field`, `name`, `label`, `defaultvalue`, `isusereditable`, `isrequired`, `displayorder`, `validationtype`, `validationmethod`)
					VALUES
					('".$search_criteria_widget['widgetid']."', 'Text', 'searchCriteriaTitle', 'WidgetTitle', '', 1, 0, 1, '', '')
					"
				);
			}
			else
			{
				$skip_message = true;
			}

		}
		else
		{
			$skip_message = true;
		}
		if($skip_message)
		{
			$this->skip_message();
		}
	}
	/**
	 * adding search results template
	 */
	function step_2()
	{
		$this->db->query_write("
			UPDATE " . TABLE_PREFIX . "pagetemplate SET title = 'Advanced Search Template' WHERE title = 'Default Search Template'
		");
		$pageTemplateRecords = $this->db->query_first("
			SELECT pagetemplateid FROM " . TABLE_PREFIX . "pagetemplate WHERE title = 'Search Result Template'
		");

		if (empty($pageTemplateRecords) OR empty($pageTemplateRecords['pagetemplateid']))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'pagetemplate'),
				"
				INSERT INTO `" . TABLE_PREFIX . "pagetemplate`
				(`title`, `screenlayoutid`)
				VALUES
				('Search Result Template', '1')
				"
			);
		}
		else
		{
			$this->skip_message();
		}
	}
	/**
	 * moving the search criteria widget to a separate template
	 */
	function step_3()
	{
		$pageTemplateRecords = $this->db->query_first("
			SELECT pagetemplateid FROM " . TABLE_PREFIX . "pagetemplate WHERE title = 'Advanced Search Template'
		");
		$search_results_widget = $this->db->query_first("
			SELECT widgetid FROM " . TABLE_PREFIX . "widget
			WHERE template = 'widget_search_results'");

		$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'pagetemplate'),
				"
				UPDATE " . TABLE_PREFIX . "widgetinstance SET pagetemplateid = '".$pageTemplateRecords['pagetemplateid']."', widgetid = '".$search_results_widget['widgetid']."' WHERE pagetemplateid = '4' AND widgetid = '14'
				"
		);
	}
	/**
	 * creating route for search results
	 */
	function step_4()
	{
		$this->skip_message();
	}

	/**
	 * creating page for search results
	 */
	function step_5()
	{
		$this->skip_message();
	}
	/** Video */
	function step_6()
	{
		$contenttype = $this->db->query_first("
			SELECT contenttypeid FROM " . TABLE_PREFIX . "contenttype
			WHERE class = 'Video'");
		if (empty($contenttype) OR empty($contenttype['contenttypeid']))
		{
			$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'contenttype'),
			"INSERT INTO " . TABLE_PREFIX . "contenttype(class,
			packageid,	canplace,	cansearch,	cantag,	canattach,	isaggregator)
			SELECT 'Video', packageid, '1', '1', '1', '1', '1'  FROM " . TABLE_PREFIX . "package where class = 'vBForum';");
		}
		else
		{
			$this->skip_message();
		}

		$this->run_query(
		sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'video'),
		"
			CREATE TABLE " . TABLE_PREFIX . "video (
				nodeid INT UNSIGNED NOT NULL,
				PRIMARY KEY (nodeid)
			) ENGINE = " . $this->hightrafficengine . "
		",
		self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
		sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'videoitem'),
		"
			CREATE TABLE " . TABLE_PREFIX . "videoitem (
				videoitemid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				nodeid INT UNSIGNED NOT NULL,
				caption VARCHAR(255),
				provider VARCHAR(255),
				code VARCHAR(255),
				url VARCHAR(255),
				PRIMARY KEY (videoitemid),
				KEY nodeid (nodeid)
			) ENGINE = " . $this->hightrafficengine . "
		",
		self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	 * Video widget
	 */
	function step_7()
	{
		$videowidget = $this->db->query_first("
			SELECT widgetid FROM " . TABLE_PREFIX . "widget
			WHERE template = 'widget_2'");

		if ($videowidget['widgetid'])
		{
			// Rename video widget
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widget'),
				"
					UPDATE " . TABLE_PREFIX . "widget SET title = 'Video' WHERE widgetid = $videowidget[widgetid]
				"
			);

			// Modify video widget options
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetdefinition'),
				"
					DELETE FROM " . TABLE_PREFIX . "widgetdefinition WHERE widgetid = $videowidget[widgetid] AND name IN ('provider', 'videoid', 'url')
				"
			);
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetdefinition'),
				"
					INSERT INTO " . TABLE_PREFIX . "widgetdefinition
					(`widgetid`, `field`, `name`, `label`, `defaultvalue`, `isusereditable`, `isrequired`, `displayorder`, `validationtype`, `validationmethod`, `data`)
					VALUES
					($videowidget[widgetid], 'Text', 'url', 'Video Link', 'http://', 1, 1, 2, '', '', '')
				"
			);

		}
		else
		{
			$this->skip_message();
		}

	}

	/** Add users following moderate setting */
	function step_8()
	{
		$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'user'),
				"
				UPDATE " . TABLE_PREFIX . "user
				SET options = (options | 67108864)
				WHERE usergroupid IN (4, 8)
				"
		);
	}

	/*** Correct some routes*/
	function step_9()
	{
		$query = "update " . TABLE_PREFIX . "routenew set class = 'vB5_Route_Content' where name in ('profile',
		'following', 'followers', 'groups', 'settings');";
		$this->run_query(
		sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'routenew'),$query);

	}


	/**
	 * Change the URL for the "search" navbar tab from 'search' to 'advanced_search'
	 */
	function step_10()
	{
		/* This step was no longer needed 
		as we no longer add the Search Tab */
		$this->skip_message();
	}

	/**
	 * Add default "Home" item to navbar
	 */
	function step_11()
	{
		$site = $this->db->query_first("
			SELECT headernavbar
			FROM " . TABLE_PREFIX . "site
			WHERE siteid = 1
		");

		$update = true;

		if ($site AND $site['headernavbar'] AND ($navbar = @unserialize($site['headernavbar'])))
		{
			foreach ($navbar AS &$item)
			{
				if ($item['url'] == '/')
				{
					$update = false;
					break;
				}
			}
		}

		if (isset($navbar) AND $navbar AND $update)
		{
			$newItem = array(
				'url' => '/',
				'title' => 'Home',
			);

			array_unshift($navbar, $newItem);

			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'site'),
				"
					UPDATE " . TABLE_PREFIX . "site
					SET headernavbar = '" . serialize($navbar) . "'
					WHERE siteid = 1
				"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Add default footer navigation items
	 */
	function step_12()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'site'),
			"
				UPDATE " . TABLE_PREFIX . "site
				SET footernavbar = 'a:3:{i:0;a:3:{s:5:\"title\";s:10:\"Contact Us\";s:3:\"url\";s:10:\"contact-us\";s:9:\"newWindow\";s:1:\"0\";}i:1;a:4:{s:5:\"title\";s:5:\"Admin\";s:3:\"url\";s:7:\"admincp\";s:9:\"newWindow\";s:1:\"0\";s:10:\"usergroups\";a:1:{i:0;i:6;}}i:2;a:4:{s:5:\"title\";s:3:\"Mod\";s:3:\"url\";s:5:\"modcp\";s:9:\"newWindow\";s:1:\"0\";s:10:\"usergroups\";a:1:{i:0;i:6;}}}'
				WHERE
					siteid = 1
						AND
					footernavbar = ''
			"
		);

	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
