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

class vB_Upgrade_500a14 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a14';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 14';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 13';

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

	/** Adding UUID field to page table **/
	public function step_1()
	{
		$this->skip_message();
	}

	public function step_2()
	{
		$parsedXml = vB_Xml_Import::parseFile(dirname(__FILE__) . '/../vbulletin-pages.xml');

		$pages = array();
		foreach($parsedXml['page'] AS $t)
		{
			$title = ($t['title'] == 'Forums') ? $t['title'] . '-' . $t['pagetype'] : $t['title'];

			if (isset($pages[$title]))
			{
				throw new Exception("Duplicate id when updating page GUIDs! ($title)");
			}

			$pages[$title] = $t['guid'];
		}

		$missing = $this->db->query_read('SELECT pageid, title, pagetype FROM ' . TABLE_PREFIX . 'page WHERE guid IS NULL');

		if (!empty($missing) OR !empty($missing['pageid']))
		{
			while ($page = $this->db->fetch_array($missing))
			{
				$title = ($page['title'] == 'Forums') ? $page['title'] . '-' . $page['pagetype'] : $page['title'];
				$guid = (isset($pages[$title]) AND !empty($pages[$title])) ? $pages[$title] : vB_Xml_Export_Page::createGUID($page);
				$this->run_query(
						sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'page'),
						"UPDATE " . TABLE_PREFIX . "page
						SET guid = '{$guid}'
						WHERE pageid = {$page['pageid']}"
				);
			}
		}
		else
		{
			$this->skip_message();
		}
	}

	public function step_3()
	{
		$this->skip_message();
	}

	public function step_4()
	{
		$parsedXml = vB_Xml_Import::parseFile(dirname(__FILE__) . '/../vbulletin-pagetemplates.xml');

		$templates = array();
		foreach($parsedXml['pagetemplate'] AS $t)
		{
			if (isset($templates[$t['title']]))
			{
				throw new Exception("Duplicate id when updating page template GUIDs! ({$t['title']})");
			}

			$templates[$t['title']] = $t['guid'];
		}

		$missing = $this->db->query_read('SELECT pagetemplateid, title FROM ' . TABLE_PREFIX . 'pagetemplate WHERE guid IS NULL');

		if (!empty($missing) OR !empty($missing['pageid']))
		{
			while ($pagetemplate = $this->db->fetch_array($missing))
			{
				$guid = (isset($templates[$pagetemplate['title']]) AND !empty($templates[$pagetemplate['title']])) ? $templates[$pagetemplate['title']] : vB_Xml_Export::createGUID($pagetemplate);
				$this->run_query(
						sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'pagetemplate'),
						"UPDATE " . TABLE_PREFIX . "pagetemplate
						SET guid = '{$guid}'
						WHERE pagetemplateid = {$pagetemplate['pagetemplateid']}"
				);
			}
		}
		else
		{
			$this->skip_message();
		}
	}

	public function step_5()
	{
		$this->skip_message();
	}

	public function step_6()
	{
		$parsedXml = vB_Xml_Import::parseFile(dirname(__FILE__) . '/../vbulletin-widgets.xml');

		$widgets = array();
		foreach($parsedXml['widget'] AS $t)
		{
			if (isset($templates[$t['title']]))
			{
				throw new Exception("Duplicate id when updating widget GUIDs! ({$t['title']})");
			}

			$widgets[$t['title']] = $t['guid'];
		}

		$missing = $this->db->query_read('SELECT widgetid, title, template FROM ' . TABLE_PREFIX . 'widget WHERE guid IS NULL');

		if (!empty($missing) OR !empty($missing['widgetid']))
		{
			while ($widget = $this->db->fetch_array($missing))
			{
				$guid = (isset($widgets[$widget['title']]) AND !empty($widgets[$widget['title']])) ? $widgets[$widget['title']] : vB_Xml_Export_Widget::createGUID($widget);
				$this->run_query(
						sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widget'),
						"UPDATE " . TABLE_PREFIX . "widget
						SET guid = '{$guid}'
						WHERE widgetid = {$widget['widgetid']}"
				);
			}
		}
		else
		{
			$this->skip_message();
		}
	}

	public function step_7()
	{
		$this->skip_message();	}

	public function step_8()
	{
		$parsedXml = vB_Xml_Import::parseFile(dirname(__FILE__) . '/../vbulletin-channels.xml');

		$channels = array();
		foreach($parsedXml['channel'] AS $t)
		{
			if (isset($channels[$t['node']['title']]))
			{
				throw new Exception("Duplicate id when updating channel GUIDs! ({$t['node']['title']})");
			}

			$channels[$t['node']['title']] = $t['guid'];
		}

		$missing = $this->db->query_read(
			'SELECT c.nodeid, n.title
			FROM ' . TABLE_PREFIX . 'channel AS c
			INNER JOIN ' . TABLE_PREFIX . 'node AS n ON n.nodeid = c.nodeid
			WHERE guid IS NULL'
		);

		//if (!empty($missing) AND !empty($missing['nodeid']))
		if ($this->db->num_rows($missing) > 0)
		{
			while ($channel = $this->db->fetch_array($missing))
			{
				$guid = (isset($channels[$channel['title']]) AND !empty($channels[$channel['title']])) ? $channels[$channel['title']] : vB_Xml_Export::createGUID($channel);
				$this->run_query(
						sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'channel'),
						"UPDATE " . TABLE_PREFIX . "channel
						SET guid = '{$guid}'
						WHERE nodeid = {$channel['nodeid']}"
				);
			}
		}
		else
		{
			$this->skip_message();
		}
	}

	public function step_9()
	{
		$this->skip_message();
	}

	public function step_10()
	{
		$parsedXml = vB_Xml_Import::parseFile(dirname(__FILE__) . '/../vbulletin-routes.xml');

		$routes = array();
		foreach($parsedXml['route'] AS $t)
		{
			$title = "{$t['prefix']}-{$t['class']}";
			if (isset($routes[$title]))
			{
				throw new Exception("Duplicate id when updating route GUIDs! ({$title})");
			}

			$routes[$title] = $t['guid'];
		}

		$missing = $this->db->query_read('SELECT routeid, prefix, class FROM ' . TABLE_PREFIX . 'routenew WHERE guid IS NULL');

		$processed = false;
		if (!empty($missing) OR !empty($missing['routeid']))
		{
			while ($route = $this->db->fetch_array($missing))
			{
				$processed = true;
				$temp_id = "{$route['prefix']}-{$route['class']}";
				$guid = (isset($routes[$temp_id]) AND !empty($routes[$temp_id])) ? $routes[$temp_id] : vB_Xml_Export::createGUID($route);
				$this->run_query(
						sprintf($this->phrase['vbphrase']['update_table'], 'routenew'),
						"UPDATE " . TABLE_PREFIX . "routenew
						SET guid = '{$guid}'
						WHERE routeid = {$route['routeid']}"
				);
			}
		}

		if (!$processed)
		{
			$this->skip_message();
		}
	}

	// Update old reputation table
	function step_11()
	{
		$this->skip_message();
	}

	//Set nodeid in reputation table.
	function step_12($data = NULL)
	{
		//this is a pretty low-cost query, even on a big table.
		$process = 5000;
		$startat = intval($data['startat']);

		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], 'reputation'));
		$this->show_message(sprintf($this->phrase['core']['processing_records_x'], $process));
		$postTypeId = vB_Types::instance()->getContentTypeID('vBForum_Post');
		$query = "UPDATE " . TABLE_PREFIX . "reputation AS r
		INNER JOIN " . TABLE_PREFIX . "node AS n ON n.oldid = r.postid AND n.oldcontenttypeid = $postTypeId
		SET r.nodeid = n.nodeid
		WHERE r.nodeid = 0 AND r.reputationid < " . (($startat + 1) * $process);

		$this->db->query_write($query);
		$processed = $this->db->query_first("SELECT ROW_COUNT() AS recs");

		if ($processed['recs'] <= 0)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}
		$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $processed['recs']));
		return array('startat' => $startat + 1);
	}

	function step_13($data = NULL)
	{
		//this is not a terrible query even on a big table.
		$process = 2500;
		$startat = intval($data['startat']);
		$threadTypeId = vB_Types::instance()->getContentTypeID('vBForum_Post');

		$query = "
			UPDATE " . TABLE_PREFIX . "reputation as r
			INNER JOIN " . TABLE_PREFIX . "post AS p ON p.postid = r.postid
			INNER JOIN " . TABLE_PREFIX . "node AS n ON n.oldid = p.threadid AND n.oldcontenttypeid = 2
			SET r.nodeid = n.nodeid
			WHERE r.nodeid = 0 AND r.reputationid < " . (($startat + 1) * $process);

		$this->db->query($query);
		$processed = $this->db->query_first("SELECT ROW_COUNT() AS recs");

		if ($processed['recs'] <= 0)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}
		$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $processed['recs']));
		return array('startat' => $startat + 1);
	}

	// Update reputation table
	function step_14()
	{
		// Drop orphans
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 1, 3),
			"DELETE FROM " . TABLE_PREFIX . "reputation
			WHERE nodeid = 0"
		);

	}
	// Update reputation table
	function step_15()
	{
		// Add new index
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 2, 3),
			'reputation',
			'whoadded_nodeid',
			array('whoadded', 'nodeid'),
			'unique'
		);

	}
	// Update reputation table
	function step_16()
	{
		// Add new index
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 3, 3),
			'reputation',
			'multi',
			array('nodeid', 'userid')
		);

	}

	function step_17()
	{
		// Drop nodevote table
		$this->run_query(
			sprintf($this->phrase['core']['dropping_old_table_x'], "nodevote"),
			"DROP TABLE IF EXISTS " . TABLE_PREFIX . "nodevote"
		);
	}

	/*
	 * VBV-6546 : Set node hasphoto value
	 */
	function step_18($data = null) 
	{
		$process = 2500;
		$maxnode = intval($data['maxnode']);
		$startat = intval($data['startat']);

		if ($startat == 0)
		{	// Initial pass, get max nodeid
			$data = $this->db->query_first("
				SELECT MAX(nodeid) AS maxnode 
				FROM " . TABLE_PREFIX . "text
			");
			
			$maxnode = intval($data['maxnode']);

			if (!$maxnode)
			{ // Nothing to process (unlikely ....)
				$this->skip_message();
				return;
			}

			$this->show_message($this->phrase['version']['500a14']['processing_photos']);
			return array('startat' => 1, 'maxnode' => $maxnode);
		}
		else
		{	// Subsequent passes
			$first = $startat;
			$last = $first + $process - 1;
			
			if ($first > $maxnode)
			{
				$this->show_message($this->phrase['version']['500a14']['update_photos_complete']);
				return;
			}
		}

		$nodes = $this->db->query_read_slave("
			SELECT n.nodeid, t.rawtext, n.hasphoto
			FROM " . TABLE_PREFIX . "node AS n
			INNER JOIN " . TABLE_PREFIX . "text as t
			USING (nodeid)
			WHERE n.nodeid >= $first AND n.nodeid <= $last
		");

		$nodelist = array();
		$rows = $this->db->num_rows($nodes);

		if ($rows)
		{
			while ($node = $this->db->fetch_array($nodes))
			{
				if (!$node['hasphoto'] 
				// Make sure we have an opening and closing tag
				AND strripos($node['rawtext'], '[attach') !== false
				AND strripos($node['rawtext'], '[/attach') !== false)
				{
					$nodelist[] = $node['nodeid'];
				}
			}
		}

		if ($nodelist)
		{
			$nodes = implode(',', $nodelist);

			$this->db->query_write("
				UPDATE " . TABLE_PREFIX . "node
				SET hasphoto = 1
				WHERE nodeid IN ($nodes)
			");
		}

		$this->show_message(sprintf($this->phrase['version']['500a14']['processed_nodes'], $first, $last, $rows));

		return array('startat' => $last + 1, 'maxnode' => $maxnode);
	}

	/*
	 * VBV-6546 : Set node hasvideo value
	 */
	function step_19($data = null) 
	{
		$process = 2500;
		$maxnode = intval($data['maxnode']);
		$startat = intval($data['startat']);

		if ($startat == 0)
		{	// Initial pass, get max nodeid
			$data = $this->db->query_first("
				SELECT MAX(nodeid) AS maxnode 
				FROM " . TABLE_PREFIX . "text
			");
			
			$maxnode = intval($data['maxnode']);

			if (!$maxnode)
			{ // Nothing to process (unlikely ....)
				$this->skip_message();
				return;
			}

			$this->show_message($this->phrase['version']['500a14']['processing_videos']);
			return array('startat' => 1, 'maxnode' => $maxnode);
		}
		else
		{	// Subsequent passes
			$first = $startat;
			$last = $first + $process - 1;
			
			if ($first > $maxnode)
			{
				$this->show_message($this->phrase['version']['500a14']['update_videos_complete']);
				return;
			}
		}

		$nodes = $this->db->query_read_slave("
			SELECT n.nodeid, t.rawtext, n.hasvideo
			FROM " . TABLE_PREFIX . "node AS n
			INNER JOIN " . TABLE_PREFIX . "text as t
			USING (nodeid)
			WHERE n.nodeid >= $first AND n.nodeid <= $last
		");

		$nodelist = array();
		$rows = $this->db->num_rows($nodes);

		if ($rows)
		{
			while ($node = $this->db->fetch_array($nodes))
			{
				if (!$node['hasvideo'] 
				// Make sure we have an opening and closing tag
				AND strripos($node['rawtext'], '[video') !== false
				AND strripos($node['rawtext'], '[/video') !== false)
				{
					$nodelist[] = $node['nodeid'];
				}
			}
		}

		if ($nodelist)
		{
			$nodes = implode(',', $nodelist);

			$this->db->query_write("
				UPDATE " . TABLE_PREFIX . "node
				SET hasvideo = 1
				WHERE nodeid IN ($nodes)
			");
		}

		$this->show_message(sprintf($this->phrase['version']['500a14']['processed_nodes'], $first, $last, $rows));

		return array('startat' => $last + 1, 'maxnode' => $maxnode);
	}

	//For handling private message deletion
	public function step_20()
	{
		$this->skip_message();
	}

	//cron job for private message deletion.
	public function step_21()
	{
		$assertor = vB::getDbAssertor();
		$existing = $assertor->getRow('cron', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'varname' => 'privatemessages'));
		if ($existing AND empty($existing['errors']))
		{
			$this->skip_message();
		}
		else
		{
			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'nextrun' =>  vB::getRequest()->getTimeNow(), 'weekday' => -1, 'day' => -1,
			'hour' => -1, 'minute' => 'a:1:{i:0;i:40;}','filename' => './includes/cron/privatemessage_cleanup.php',
			'loglevel' => 1, 'varname' => 'privatemessages', 'volatile' => 1,'product' => 'vbulletin');

			$assertor->assertQuery('cron', $data);
			$this->show_message($this->phrase['version']['500a14']['adding_pm_scheduled_task']);
		}

		$this->long_next_step();
	}

	/**
	 * Remove following widget information
	 */
	function step_22()
	{
		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'routenew'),
			"DELETE FROM " . TABLE_PREFIX . "routenew WHERE guid = 'vbulletin-4ecbdacd6a7ef6.07321454'"
		);

		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'page'),
			"DELETE FROM " . TABLE_PREFIX . "page WHERE guid = 'vbulletin-4ecbdac82f17e1.17839721'"
		);

		// get the pagetemplateid to delete pages and routenew records
		$templateInfo = $this->db->query_first("
			SELECT pagetemplateid FROM " . TABLE_PREFIX . "pagetemplate
			WHERE guid = 'vbulletin-4ecbdac9373089.38426136'"
		);

		if ($templateInfo AND isset($templateInfo['pagetemplateid']) AND !empty($templateInfo['pagetemplateid']))
		{
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'pagetemplate'),
				"DELETE FROM " . TABLE_PREFIX . "pagetemplate WHERE pagetemplateid = " . $templateInfo['pagetemplateid']
			);

			// fetch pages using the template
			$pages = $this->db->query_read("
				SELECT pageid, routeid FROM " . TABLE_PREFIX . "page
				WHERE pagetemplateid = " . $templateInfo['pagetemplateid']
			);
			$pageIds = array();
			$routeIds = array();
			while ($page = $this->db->fetch_array($pages))
			{
				$pageIds[] = $page['pageid'];
				$routeIds[] = $page['routeid'];
			}

			// delete page...
			if (!empty($pageIds))
			{
				$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'page'),
					"DELETE FROM " . TABLE_PREFIX . "page WHERE pageid IN (" . implode(', ', $pageIds) . ")"
				);
			}
		}

		// ...and routenew records
		if (!empty($routeIds))
		{
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'routenew'),
				"DELETE FROM " . TABLE_PREFIX . "routenew WHERE routeid IN (" . implode(', ', $routeIds) . ")"
			);
		}

		// now from widget tables
		$widgetInfo = $this->db->query_first("
			SELECT widgetid FROM " . TABLE_PREFIX . "widget
			WHERE guid = 'vbulletin-widget_following-4eb423cfd6c778.30550576'"
		);

		if ($widgetInfo AND isset($widgetInfo['widgetid']) AND !empty($widgetInfo['widgetid']))
		{
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widget'),
				"DELETE FROM " . TABLE_PREFIX . "widget WHERE guid = 'vbulletin-widget_following-4eb423cfd6c778.30550576'"
			);

			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetdefinition'),
				"DELETE FROM " . TABLE_PREFIX . "widgetdefinition WHERE widgetid = " . $widgetInfo['widgetid']
			);

			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetinstance'),
				"DELETE FROM " . TABLE_PREFIX . "widgetinstance WHERE widgetid = " . $widgetInfo['widgetid']
			);
		}

		$this->long_next_step();
	}

	/**
	 * Remove followers widget information
	 */
	function step_23()
	{
		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'routenew'),
			"DELETE FROM " . TABLE_PREFIX . "routenew WHERE guid = 'vbulletin-4ecbdacd6a8b25.50710303'"
		);

		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'page'),
			"DELETE FROM " . TABLE_PREFIX . "page WHERE guid = 'vbulletin-4ecbdac82f1bf0.76172990'"
		);

		// get the pagetemplateid to delete pages and routenew records
		$templateInfo = $this->db->query_first("
			SELECT pagetemplateid FROM " . TABLE_PREFIX . "pagetemplate
			WHERE guid = 'vbulletin-4ecbdac9373422.51068894'"
		);

		if ($templateInfo AND isset($templateInfo['pagetemplateid']) AND !empty($templateInfo['pagetemplateid']))
		{
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'pagetemplate'),
				"DELETE FROM " . TABLE_PREFIX . "pagetemplate WHERE pagetemplateid = " . $templateInfo['pagetemplateid']
			);

			// fetch pages using the template
			$pages = $this->db->query_read("
				SELECT pageid, routeid FROM " . TABLE_PREFIX . "page
				WHERE pagetemplateid = " . $templateInfo['pagetemplateid']
			);
		}

		$pageIds = array();
		$routeIds = array();
		while ($page = $this->db->fetch_array($pages))
		{
			$pageIds[] = $page['pageid'];
			$routeIds[] = $page['routeid'];
		}

		// delete page...
		if (!empty($pageIds))
		{
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'page'),
				"DELETE FROM " . TABLE_PREFIX . "page WHERE pageid IN (" . implode(', ', $pageIds) . ")"
			);
		}

		// and routenew records
		if (!empty($routeIds))
		{
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'routenew'),
				"DELETE FROM " . TABLE_PREFIX . "routenew WHERE routeid IN (" . implode(', ', $routeIds) . ")"
			);
		}

		// now from widget tables
		$widgetInfo = $this->db->query_first("
			SELECT widgetid FROM " . TABLE_PREFIX . "widget
			WHERE guid = 'vbulletin-widget_followers-4eb423cfd6cac2.78540773'"
		);

		if ($widgetInfo AND isset($widgetInfo['widgetid']) AND !empty($widgetInfo['widgetid']))
		{
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widget'),
				"DELETE FROM " . TABLE_PREFIX . "widget WHERE guid = 'vbulletin-widget_followers-4eb423cfd6cac2.78540773'"
			);

			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetdefinition'),
				"DELETE FROM " . TABLE_PREFIX . "widgetdefinition WHERE widgetid = " . $widgetInfo['widgetid']
			);

			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetinstance'),
				"DELETE FROM " . TABLE_PREFIX . "widgetinstance WHERE widgetid = " . $widgetInfo['widgetid']
			);
		}

		$this->long_next_step();
	}

	/**
	 * Remove groups widget information
	 */
	function step_24()
	{
		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'routenew'),
			"DELETE FROM " . TABLE_PREFIX . "routenew WHERE guid = 'vbulletin-4ecbdacd6a8f29.89433296'"
		);

		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'page'),
			"DELETE FROM " . TABLE_PREFIX . "page WHERE guid = 'vbulletin-4ecbdac82f2008.58648267'"
		);

		// get the pagetemplateid to delete pages and routenew records
		$templateInfo = $this->db->query_first("
			SELECT pagetemplateid FROM " . TABLE_PREFIX . "pagetemplate
			WHERE guid = 'vbulletin-4ecbdac93737c2.35059434'"
		);

		if ($templateInfo AND isset($templateInfo['pagetemplateid']) AND !empty($templateInfo['pagetemplateid']))
		{
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'pagetemplate'),
				"DELETE FROM " . TABLE_PREFIX . "pagetemplate WHERE pagetemplateid = " . $templateInfo['pagetemplateid']
			);

			// fetch pages using the template
			$pages = $this->db->query_read("
				SELECT pageid, routeid FROM " . TABLE_PREFIX . "page
				WHERE pagetemplateid = " . $templateInfo['pagetemplateid']
			);
		}

		$pageIds = array();
		$routeIds = array();
		while ($page = $this->db->fetch_array($pages))
		{
			$pageIds[] = $page['pageid'];
			$routeIds[] = $page['routeid'];
		}

		// delete page...
		if (!empty($pageIds))
		{
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'page'),
				"DELETE FROM " . TABLE_PREFIX . "page WHERE pageid IN (" . implode(', ', $pageIds) . ")"
			);
		}

		// ...and routenew records
		if (!empty($routeIds))
		{
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'routenew'),
				"DELETE FROM " . TABLE_PREFIX . "routenew WHERE routeid IN (" . implode(', ', $routeIds) . ")"
			);
		}

		// now from widget tables
		$widgetInfo = $this->db->query_first("
			SELECT widgetid FROM " . TABLE_PREFIX . "widget
			WHERE guid = 'vbulletin-widget_groups-4eb423cfd6ce25.12220055'"
		);

		if ($widgetInfo AND isset($widgetInfo['widgetid']) AND !empty($widgetInfo['widgetid']))
		{
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widget'),
				"DELETE FROM " . TABLE_PREFIX . "widget WHERE guid = 'vbulletin-widget_groups-4eb423cfd6ce25.12220055'"
			);

			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetdefinition'),
				"DELETE FROM " . TABLE_PREFIX . "widgetdefinition WHERE widgetid = " . $widgetInfo['widgetid']
			);

			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetinstance'),
				"DELETE FROM " . TABLE_PREFIX . "widgetinstance WHERE widgetid = " . $widgetInfo['widgetid']
			);
		}
	}

	// Set all users to have collapsed signature by default
	public function step_25()
	{
		$bf_misc_useroptions = vB::getDatastore()->get_value('bf_misc_useroptions');

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'user'),
			"UPDATE " . TABLE_PREFIX . "user
			SET options = options - {$bf_misc_useroptions['showsignatures']}
			WHERE (options & {$bf_misc_useroptions['showsignatures']})"
		);
	}


	//Add setfor- needed for Visitor Message.
	public function step_26()
	{
		$assertor = vB::getDbAssertor();

		$current = $assertor->getRows('routenew', array('name' => 'album'));

		if (empty($current) OR !empty($current['routeid']))
		{
			$this->show_message($this->phrase['version']['500a13']['adding_album_widget']);

			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'title' => 'Album Template', 'screenlayoutid' => 1);
			$pagetemplateid = $assertor->assertQuery('pagetemplate', $data);
			$pagetemplateid = $pagetemplateid[0];

			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'parentid' => 0, 'pagetemplateid' => $pagetemplateid, 'title' => 'Album',
			'metakeywords' => 'album photos videos pictures images','metadescription' => 'vBulletin Photo Album',
			'routeid' => 10, 'displayorder' => 1, 'pagetype' => 'custom');
			$pageid = $assertor->assertQuery('page', $data);
			$pageid = $pageid[0];

			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'name' => 'album', 'prefix' => 'album', 'regex' => 'album/(?P<nodeid>[0-9]+)(?P<title>(-[^!@\\#\\$%\\^&\\*\\(\\)\\+\\?/:;"\'\\\\,\\.<>= _]*)*)',
			'class' => 'vB5_Route_album','controller' => 'page','action' => 'index',
			'template' => 'widget_album','arguments' => serialize(array('contentid' => $pageid)),'contentid' => $pageid);

			$routeid = $assertor->assertQuery('routenew', $data);
			$routeid = $routeid[0];

			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'pageid' => $pageid, 'routeid' => $routeid);

			$assertor->assertQuery('page', $data);

			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'pagetemplateid' => $pagetemplateid, 'widgetid' => 30, 'displaysection' => 0,
			'displayorder' => 0);

			$assertor->assertQuery('widgetinstance', $data);

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
