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

if (VB_AREA != 'Install' AND !isset($GLOBALS['vbulletin']->db))
{
	exit;
}

class vB_Upgrade_final extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = 'final';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = 'final';

	/*Properties====================================================================*/

	/**
	* Step #1 - Import widgets XML
	*
	*/
	function step_1()
	{
		$this->show_message($this->phrase['final']['import_latest_widgets']);
		$widgetFile = DIR . '/install/vbulletin-widgets.xml';
		if (!($xml = file_read($widgetFile)))
		{
			$this->add_error(sprintf($this->phrase['vbphrase']['file_not_found'], 'vbulletin-widgets.xml'), self::PHP_TRIGGER_ERROR, true);
			return;
		}

		$this->show_message(sprintf($this->phrase['vbphrase']['importing_file'], 'vbulletin-widgets.xml'));

		$xml_importer = new vB_Xml_Import_Widget();
		$xml_importer->import($widgetFile);

		$this->show_message($this->phrase['core']['import_done']);
	}

	/**
	* Step #2 - Import pagetemplates XML
	*
	*/
	function step_2()
	{
		$pageTemplateFile = DIR . '/install/vbulletin-pagetemplates.xml';
		if (!($xml = file_read($pageTemplateFile)))
		{
			$this->add_error(sprintf($this->phrase['vbphrase']['file_not_found'], 'vbulletin-pagetemplates.xml'), self::PHP_TRIGGER_ERROR, true);
			return;
		}

		$this->show_message(sprintf($this->phrase['vbphrase']['importing_file'], 'vbulletin-pagetemplates.xml'));

		// TODO: there might be some upgrades in which we do want to add some widgetinstances
		$options = (VB_AREA == 'Upgrade') ? 0 : vB_Xml_Import::OPTION_ADDWIDGETS;
		$xml_importer = new vB_Xml_Import_PageTemplate($options);
		$xml_importer->import($pageTemplateFile);

		$this->show_message($this->phrase['core']['import_done']);
	}

	/**
	* Step #3 - Import pages XML
	*
	*/
	function step_3()
	{
		vB_Upgrade::createAdminSession();

		// Importing pages
		$pageFile = DIR . '/install/vbulletin-pages.xml';
		if (!($xml = file_read($pageFile)))
		{
			$this->add_error(sprintf($this->phrase['vbphrase']['file_not_found'], 'vbulletin-pages.xml'), self::PHP_TRIGGER_ERROR, true);
			return;
		}

		$this->show_message(sprintf($this->phrase['vbphrase']['importing_file'], 'vbulletin-pages.xml'));

		$page_importer = new vB_Xml_Import_Page(0);
		$page_importer->import($pageFile);

		$this->show_message($this->phrase['core']['import_done']);

		// Import channels
		$channelFile = DIR . '/install/vbulletin-channels.xml';
		if (!($xml = file_read($channelFile)))
		{
			$this->add_error(sprintf($this->phrase['vbphrase']['file_not_found'], 'vbulletin-channels.xml'), self::PHP_TRIGGER_ERROR, true);
			return;
		}

		$this->show_message(sprintf($this->phrase['vbphrase']['importing_file'], 'vbulletin-channels.xml'));

		$channel_importer = new vB_Xml_Import_Channel(0);
		$channel_importer->import($channelFile);

		$this->show_message($this->phrase['core']['import_done']);

		$routesFile = DIR . '/install/vbulletin-routes.xml';
		if (!($xml = file_read($pageFile)))
		{
			$this->add_error(sprintf($this->phrase['vbphrase']['file_not_found'], 'vbulletin-routes.xml'), self::PHP_TRIGGER_ERROR, true);
			return;
		}

		$this->show_message(sprintf($this->phrase['vbphrase']['importing_file'], 'vbulletin-routes.xml'));

		$route_importer = new vB_Xml_Import_Route();
		$route_importer->import($routesFile);

		// update pages with new route ids
		$page_importer->updatePageRoutes();

		// update channels with route ids
		$channel_importer->updateChannelRoutes();

		$this->show_message($this->phrase['core']['import_done']);
	}

		/*** after building out the node and channel tables with their data, we need to
	 create the Channel widget instance configuration and update ONLY IF IT HASN'T BEEN ALREADY SET **/
	function step_4()
	{
		$widgetid = $this->db->query_first("
			SELECT widgetinstanceid FROM `" . TABLE_PREFIX . "widgetinstance`
			WHERE widgetinstanceid = 1 AND adminconfig = ''
			");
		if (empty($widgetid))
		{
			$this->skip_message();
			return;
		}

		$contenttype = $this->db->query_first("
			SELECT contenttypeid
			FROM " . TABLE_PREFIX . "contenttype
			WHERE class = 'Channel'
		");
		$channelContentTypeId = $contenttype['contenttypeid'];

		$widgetConfig = array(
			'channel_node_ids' => array(),
		);


		$rootChannelResult = $this->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "node
			WHERE
				parentid = 1
				AND
				contenttypeid = $channelContentTypeId
		");
		while ($rootChannel = $this->db->fetch_array($rootChannelResult))
		{
			$widgetConfig['channel_node_ids'][] = $rootChannel['nodeid'];

			$subChannelResult = $this->db->query_read($q = "
				SELECT *
				FROM " . TABLE_PREFIX . "node
				WHERE
					parentid = $rootChannel[nodeid]
					AND
					contenttypeid = $channelContentTypeId
			");

			while ($subChannel = $this->db->fetch_array($subChannelResult))
			{
				$widgetConfig['channel_node_ids'][] = $subChannel['nodeid'];
			}
		}

		$this->run_query(
		sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetinstance'),
		"
		UPDATE `" . TABLE_PREFIX . "widgetinstance`
			SET adminconfig = '" . $this->db->escape_string(serialize($widgetConfig)) . "'
			WHERE widgetinstanceid = 1 AND adminconfig = ''
			"
		);

	}

	/*** create the channel routes
	 **/
	function step_5()
	{
		$this->show_message('Creating new routes');
		$contenttype = $this->db->query_first("
			SELECT contenttypeid
			FROM " . TABLE_PREFIX . "contenttype
			WHERE class = 'Channel'
		");
		$channelContentTypeId = $contenttype['contenttypeid'];

		$contenttype = $this->db->query_first("
			SELECT contenttypeid FROM " . TABLE_PREFIX . "contenttype
			WHERE class = 'Text'");
		$textTypeId = $contenttype['contenttypeid'];

		// fetch info for blog channels
		$blogParentId = vB_Api::instanceInternal('blog')->getBlogChannel();
		$blogRoute = vB::getDbAssertor()->getRow('routenew', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array('guid' => 'vbulletin-4ecbdacd6aac05.50909926')
		));
		$blogPageTemplate = vB_Page::getBlogChannelPageTemplate();
		$blogsPage = vB::getDbAssertor()->getField('page', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array('guid' => vB_Page::PAGE_BLOG)
		));

		$rootChannelResult = $this->db->query_read("
			SELECT n.nodeid, n.title, n.parentid, c.category
			FROM " . TABLE_PREFIX . "node n
			INNER JOIN " . TABLE_PREFIX . "channel c ON n.nodeid = c.nodeid
			WHERE
				parentid IN (1, $blogParentId)
				AND
				contenttypeid = $channelContentTypeId
				AND
				routeid = 0
		");
		$queue = array();
		$prefixes = array(1 => '', $blogParentId => $blogRoute['prefix']);
		while ($channel = $this->db->fetch_array($rootChannelResult))
		{
			$channel['page_parentid'] = ($channel['parentid'] == $blogParentId) ? $blogsPage : 1;
			$queue[] = $channel;
		}

		$defaultPageTemplates = vB::getDbAssertor()->assertQuery('pagetemplate', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'guid', 'value' => array(
					vB_Page::TEMPLATE_CHANNEL,
					vB_Page::TEMPLATE_CONVERSATION,
					vB_Page::TEMPLATE_CATEGORY
				))
			)
		));

		foreach ($defaultPageTemplates AS $pageTemplate)
		{
			$pageTemplates[$pageTemplate['guid']] = $pageTemplate['pagetemplateid'];
		}

		while ($channel = array_shift($queue))
		{
			$hyphenedTitle = strtolower(str_replace(' ', '-', $channel['title']));

			// create main page for channel
			if ($channel['category'] > 0)
			{
				$pagetemplateid = $pageTemplates[vB_Page::TEMPLATE_CATEGORY];
			}
			else if ($channel['parentid'] == $blogParentId)
			{
				$pagetemplateid = $blogPageTemplate;
			}
			else
			{
				$pagetemplateid = $pageTemplates[vB_Page::TEMPLATE_CHANNEL];
			}
			$parentid = (int) $channel['page_parentid'];
			$channel['title'] = $this->db->escape_string($channel['title']);
			$this->db->query_write(
			"
			INSERT INTO `" . TABLE_PREFIX . "page`
				(`parentid`, `pagetemplateid`, `title`, `pagetype`, `guid`)
				VALUES
				($parentid, $pagetemplateid, '{$channel['title']}', '" . vB_Page::TYPE_CUSTOM .  "', '" . vB_Xml_Export_Page::createGUID(array()) . "');
				"
			);
			$pageid = $this->db->insert_id();
			$mainpageid = $pageid;

			$newprefix = '';
			if (!empty($prefixes[$channel['parentid']]))
			{
				$newprefix .= $prefixes[$channel['parentid']] . '/';
			}
			$newprefix .= $hyphenedTitle;
			$newprefix =  $this->db->escape_string($newprefix);;
			$duplicateRegex = $this->db->query_first("
				SELECT COUNT(*) AS num
				FROM " . TABLE_PREFIX . "routenew
				WHERE regex = '$newprefix'
			");
			if ($duplicateRegex['num'] > 0)
			{
				// we cannot have duplicate regex
				$newprefix .= $channel['nodeid'];
			}

			$prefixes[$channel['nodeid']] = $newprefix;


			$this->db->query_write(
			"
			REPLACE INTO `" . TABLE_PREFIX . "routenew`
				(`prefix`, `regex`, `class`, `controller`, `action`, `template`, `arguments`, `contentid`, `guid`)
				VALUES
				('$newprefix', '$newprefix', 'vB5_Route_Channel', 'page', 'index', '', '" . serialize(array('channelid'=>$channel['nodeid'], 'pageid'=>$pageid)) . "', {$channel['nodeid']}, '" . vB_Xml_Export_Route::createGUID(array()) . "');
				"
			);
			$pageRouteId = $this->db->insert_id();

			$this->db->query_write(
			"
			UPDATE `" . TABLE_PREFIX . "page`
				SET `routeid` = $pageRouteId
				WHERE pageid = $pageid;
				"
			);
			$this->db->query_write(
			"
			UPDATE `" . TABLE_PREFIX . "node`
				SET `routeid` = $pageRouteId
				WHERE nodeid = {$channel['nodeid']} AND contenttypeid = $channelContentTypeId;
				"
			);

			// create conversation page for channels that are not a category
			if ($channel['category'] == 0)
			{
				$pagetemplateid = $pageTemplates[vB_Page::TEMPLATE_CONVERSATION];
				$parentid = $mainpageid;
				$this->db->query_write(
				"
				REPLACE INTO `" . TABLE_PREFIX . "page`
					(`parentid`, `pagetemplateid`, `title`, `pagetype`, `guid`)
					VALUES
					($parentid, $pagetemplateid, '{$channel['title']}', '" . vB_Page::TYPE_DEFAULT .  "', '" . vB_Xml_Export_Page::createGUID(array()) . "');
					"
				);
				$pageid = $this->db->insert_id();

				$this->db->query_write(
				"
				REPLACE INTO `" . TABLE_PREFIX . "routenew`
				(`prefix`, `regex`, `class`, `controller`, `action`, `template`, `arguments`, `contentid`, `guid`)
				VALUES
				('$newprefix', '$newprefix/(?P<nodeid>[0-9]+)(?P<title>(-[^!@\\\\#\\\\$%\\\\^&\\\\*\\\\(\\\\)\\\\+\\\\?/:;\"\\'\\\\\\\\,\\\\.<>= _]*)*)(?:/page(?P<pagenum>[0-9]+))?',
				'vB5_Route_Conversation', 'page', 'index', '', '" . serialize(
				array(
					'nodeid'	=> '$nodeid',
					'pagenum'	=> '$pagenum',
					'channelid'	=> $channel['nodeid'],
					'pageid'	=> $pageid
				)) . "', {$channel['nodeid']}, '" . vB_Xml_Export_Route::createGUID(array()) . "');
				"
				);
				$pageRouteId = $this->db->insert_id();
				$this->db->query_write(
				"
				UPDATE `" . TABLE_PREFIX . "page`
					SET `routeid` = $pageRouteId
					WHERE pageid = $pageid;
					"
				);
				$this->db->query_write(
				"
				UPDATE `" . TABLE_PREFIX . "node`
					SET `routeid` = $pageRouteId
					WHERE parentid = {$channel['nodeid']} AND contenttypeid = $textTypeId;
					"
				);
			}

			// add subchannels to queue
			$subChannelResult = $this->db->query_read($q = "
				SELECT nodeid, title, parentid
				FROM " . TABLE_PREFIX . "node
				WHERE
					parentid = {$channel['nodeid']}
					AND
					contenttypeid = $channelContentTypeId
					AND
					routeid = 0
			");

			$subChannels = array();
			while ($subChannel = $this->db->fetch_array($subChannelResult))
			{
				$subChannel['page_parentid'] = $mainpageid;
				$queue[] = $subChannel;
			}
		}
	}

	/** Add routes to channel table**/
	function step_6()
	{
		$contenttype = $this->db->query_first("
			SELECT contenttypeid
			FROM " . TABLE_PREFIX . "contenttype
			WHERE class = 'Channel'
		");
		$channelContentTypeId = $contenttype['contenttypeid'];

		// updating channel routes
		$this->run_query(
		sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'channel'),
		"
		UPDATE " . TABLE_PREFIX . "node c
			SET c.routeid = (SELECT MAX(r.routeid) FROM " . TABLE_PREFIX . "routenew r WHERE r.contentid = c.nodeid AND class='vB5_Route_Channel')
			WHERE c.routeid = 0 AND c.contenttypeid = $channelContentTypeId
			"
		);
	}

	/**
	* Step #7 - Import Settings XML
	*
	*/
	function step_7()
	{
		vB_Upgrade::createAdminSession();
		build_channel_permissions();
		vB::getUserContext()->rebuildGroupAccess();
		//build_product_datastore(); Why do this here ?

		if (VB_AREA == 'Upgrade')
		{
			$this->show_message($this->phrase['final']['import_latest_options']);
			require_once(DIR . '/includes/adminfunctions_options.php');

			if (!($xml = file_read(DIR . '/install/vbulletin-settings.xml')))
			{
				$this->add_error(sprintf($this->phrase['vbphrase']['file_not_found'], 'vbulletin-settings.xml'), self::PHP_TRIGGER_ERROR, true);
				return;
			}

			$this->show_message(sprintf($this->phrase['vbphrase']['importing_file'], 'vbulletin-settings.xml'));
			xml_import_settings($xml);
			$this->show_message($this->phrase['core']['import_done']);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #8 - Import Admin Help XML
	*
	*/
	function step_8()
	{
		$this->show_message($this->phrase['final']['import_latest_adminhelp']);
		require_once(DIR . '/includes/adminfunctions_help.php');

		if (!($xml = file_read(DIR . '/install/vbulletin-adminhelp.xml')))
		{
			$this->add_error(sprintf($this->phrase['vbphrase']['file_not_found'], 'vbulletin-adminhelp.xml'), self::PHP_TRIGGER_ERROR, true);
			return;
		}

		$this->show_message(sprintf($this->phrase['vbphrase']['importing_file'], 'vbulletin-adminhelp.xml'));

		xml_import_help_topics($xml);
		$this->show_message($this->phrase['core']['import_done']);
	}

	/**
	* Step #9 - Import Language XML
	*
	*/
	function step_9()
	{
		$this->show_message($this->phrase['final']['import_latest_language']);
		require_once(DIR . '/includes/adminfunctions_language.php');

		if (!($xml = file_read(DIR . '/install/vbulletin-language.xml')))
		{
			$this->add_error(sprintf($this->phrase['vbphrase']['file_not_found'], 'vbulletin-language.xml'), self::PHP_TRIGGER_ERROR, true);
			return;
		}

		$this->show_message(sprintf($this->phrase['vbphrase']['importing_file'], 'vbulletin-language.xml'));

		xml_import_language($xml, -1, '', false, true, !defined('SUPPRESS_KEEPALIVE_ECHO'));
		build_language();
		build_language_datastore();
		$this->show_message($this->phrase['core']['import_done']);
	}

	/**
	* Step #10 Check Product Dependencies
	*
	*/
	function step_10()
	{
		if (VB_AREA == 'Install')
		{
			$this->skip_message();
			return;
		}

		$this->show_message($this->phrase['final']['verifying_product_dependencies']);

		require_once(DIR . '/includes/class_upgrade_product.php');
		$this->product = new vB_Upgrade_Product($this->registry, $this->phrase['vbphrase'], true, $this->caller);

		$dependency_list = array();
		$product_dependencies = $this->db->query_read("
			SELECT pd.*
			FROM " . TABLE_PREFIX . "productdependency AS pd
			INNER JOIN " . TABLE_PREFIX . "product AS p ON (p.productid = pd.productid)
			WHERE
				pd.productid IN ('dummy') # // Any Integrated 3rd party products
					AND
				p.active = 1
			ORDER BY
				pd.dependencytype, pd.parentproductid, pd.minversion
		");
		while ($product_dependency = $this->db->fetch_array($product_dependencies))
		{
			$dependency_list["$product_dependency[productid]"][] = array(
				'dependencytype'  => $product_dependency['dependencytype'],
				'parentproductid' => $product_dependency['parentproductid'],
				'minversion'      => $product_dependency['minversion'],
				'maxversion'      => $product_dependency['maxversion'],
			);
		}

		$product_list = fetch_product_list(true);
		$disabled = array();

		foreach($dependency_list AS $productid => $dependencies)
		{
			$this->show_message(sprintf($this->phrase['final']['verifying_product_x'], $productid));
			$this->product->productinfo['productid'] = $productid;
			$disableproduct = false;
			try
			{
				$this->product->import_dependencies($dependencies);
			}
			catch(vB_Exception_AdminStopMessage $e)
			{
				$message = $this->stop_exception($e);
				$this->show_message($message);
				$disableproduct = true;
			}

			if ($disableproduct)
			{
				$disabled[] = $productid;
				$this->product->disable();
				$this->add_adminmessage(
					'disabled_product_x_y_z',
					array(
						'dismissable' => 1,
						'script'      => '',
						'action'      => '',
						'execurl'     => '',
						'method'      => '',
						'status'      => 'undone',
					),
					true,
					array($product_list["$productid"]['title'], $productid, $message)
				);
				$this->show_message(sprintf($this->phrase['final']['product_x_disabled'], $productid));
			}
		}

		if (!should_install_suite())
		{
			if (!$disabled['vbblog'] AND $product_list['vbblog']['active'])
			{
				$this->product = new vB_Upgrade_Product($this->registry, $this->phrase['vbphrase'], true, $this->caller);
				$this->product->productinfo['productid'] = 'vbblog';
				$this->product->disable();
				$this->show_message(sprintf($this->phrase['final']['product_x_disabled'], 'vbblog'));
			}
		}

	}

	/**
	 * Step #11 - Import Style XML
	 *
	 * @param	array	contains id to startat processing at
	 *
	 */
	function step_11($data = null)
	{
		$perpage = 1;
		$startat = intval($data['startat']);
		require_once(DIR . '/includes/functions_databuild.php');
		require_once(DIR . '/includes/adminfunctions_template.php');

		if (!($xml = file_read(DIR . '/install/vbulletin-style.xml')))
		{
			$this->add_error(sprintf($this->phrase['vbphrase']['file_not_found'], 'vbulletin-style.xml'), self::PHP_TRIGGER_ERROR, true);
			return;
		}

		if ($startat == 0)
		{
			$this->show_message(sprintf($this->phrase['vbphrase']['importing_file'], 'vbulletin-style.xml'));
		}

		$info = xml_import_style($xml, -1, -1, '', false, 1, false, $startat, $perpage);

		if (!$info['done'])
		{
			$this->show_message($info['output']);
			return array('startat' => $startat + $perpage);
		}
		else
		{
			vB_Upgrade::createAdminSession();
			build_bbcode_video(true);
			$this->show_message($this->phrase['core']['import_done']);
		}
	}
	
	/**
	 * Step #12 - Reset all caches
	 */
	function step_12()
	{
		/*
		 * There are two reasons for reset cache in this class:
		 *  1- we want to run this once, no matter what version we are upgrading
		 *  2- we need to make sure that db has been updated with cache table
		 */
		
		$this->show_message($this->phrase['final']['reseting_cache']);
		
		// we need to restore original cache values, reverting the change in upgrade.php
		$config =& vB::getConfig();
		if (!empty($config['Backup']['Cache']['class']))
		{
			foreach ($config['Backup']['Cache']['class'] AS $key => $class)
			{
				$config['Cache']['class'][$key] = $class;
			}
		}
		
		// now reset all cache types
		vB_Cache::resetAllCache();
	}
	
	/**
	* Step #13 Template Merge
	* THIS SHOULD ALWAYS BE THE LAST STEP
	* If this step changes vbulletin-upgrade.js must also be updated in the process_bad_response() function
	*
	* @param	array	contains start info
	*
	*/
	function step_13($data = null)
	{
		if ($data['response'] == 'timeout')
		{
			$this->show_message($this->phrase['final']['step_timed_out']);
			return;
		}

		$this->show_message($this->phrase['final']['merge_template_changes']);
		$startat = intval($data['startat']);
		require_once(DIR . '/includes/class_template_merge.php');

		$products = array("'vbulletin'");

		$merge_data = new vB_Template_Merge_Data($this->registry);
		$merge_data->start_offset = $startat;
		$merge_data->add_condition($c = "tnewmaster.product IN (" . implode(', ', $products) . ")");

		$merge = new vB_Template_Merge($this->registry);
		$merge->time_limit = 4;
		$output = array();
		$completed = $merge->merge_templates($merge_data, $output);

		if ($output)
		{
			foreach($output AS $message)
			{
				$this->show_message($message);
			}
		}

		if ($completed)
		{
			// Style rebuild sometimes needs a session.
			vB_Upgrade::createAdminSession();

			if ($error = build_all_styles(0, 0, '', true))
			{
				$this->add_error($error, self::PHP_TRIGGER_ERROR, true);
				return false;
			}
		}
		else
		{
			return array('startat' => $startat + $merge->fetch_processed_count() );
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/

