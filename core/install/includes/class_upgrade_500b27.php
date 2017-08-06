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

class vB_Upgrade_500b27 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500b27';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Beta 27';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Beta 26';

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


	/**
	 * Handle customized values for stylevars that have been renamed
	 */
	public function step_1()
	{
		// Renamed stylevars (no datatype change)
		$mapRenamed = array(
			'body_bg_color' => array('body_background', 'header_background'),
			'display_tab_background' => 'module_tab_background',
			'display_tab_background_active' => 'module_tab_background_active',
			'display_tab_border' => 'module_tab_border',
			'display_tab_border_active' => 'module_tab_border_active',
			'display_tab_text_color' => 'module_tab_text_color',
			'display_tab_text_color_active' => 'module_tab_text_color_active',
			'footer_bar_bg' => 'footer_background',
			'inline_edit_search_bar_background_color_active' => 'inline_edit_search_bar_background_active',
			'inline_edit_search_bar_background_color_hover' => 'inline_edit_search_bar_background_hover',
			'left_nav_background' => 'side_nav_background',
			'left_nav_button_background_active_color' => 'side_nav_button_background_active',
			'left_nav_number_messages_color' => 'side_nav_number_messages_color',
			'list_item_bg' => 'list_item_background',
			'module_content_bg' => 'module_content_background',
			'tabbar_bg' => array('header_tabbar_background', 'header_tab_background'),
			'tabbar_list_item_color' => 'header_tab_text_color',
			'wrapper_bg_color' => 'wrapper_background',
		);

		// Renamed and datatype change, color to border
		$mapColorToBorder = array(
			'activity_stream_avatar_border_color' => 'activity_stream_avatar_border',
			'announcement_border_color' => 'announcement_border',
			'button_primary_border_color' => 'button_primary_border',
			'button_primary_border_color_hover' => 'button_primary_border_hover',
			'button_secondary_border_color' => 'button_secondary_border',
			'button_secondary_border_color_hover' => 'button_secondary_border_hover',
			'button_special_border_color' => 'button_special_border',
			'button_special_border_color_hover' => 'button_special_border_hover',
			'display_tab_border_color' => 'module_tab_border',
			'display_tab_border_color_active' => 'module_tab_border_active',
			'filter_bar_border_color' => 'toolbar_border',
			'filter_bar_button_border_color' => 'filter_bar_button_border',
			'filter_bar_form_field_border_color' => 'filter_bar_form_field_border',
			'filter_dropdown_border_color' => 'filter_dropdown_border',
			'form_dropdown_border_color' => 'form_dropdown_border',
			'form_field_border_color' => 'form_field_border',
			'inline_edit_button_border_color' => 'inline_edit_button_border',
			'inline_edit_field_border_color' => 'inline_edit_field_border',
			'left_nav_avatar_border_color' => 'side_nav_avatar_border',
			'left_nav_divider_border' => 'side_nav_item_border_top',
			'left_nav_divider_border_bottom' => 'side_nav_item_border_bottom',
			'main_nav_button_border_color' => 'main_nav_button_border',
			'module_content_border_color' => 'module_content_border',
			'module_header_border_color' => 'module_header_border',
			'notice_border_color' => 'notice_border',
			'photo_border_color' => 'photo_border',
			'photo_border_hover_color' => 'photo_border_hover',
			'poll_result_border_color' => 'poll_result_border',
			'popup_border_color' => 'popup_border',
			'post_border_color' => 'post_border',
			'post_deleted_border_color' => 'post_deleted_border',
			'profile_section_border_color' => 'profile_section_border',
			'profilesidebar_button_border_color' => 'profilesidebar_button_border',
			'secondary_content_border_color' => 'secondary_content_border',
			'thread_view_avatar_border_color' => 'thread_view_avatar_border',
		);

		$mapper = new vB_Stylevar_Mapper();

		// Add mappings
		foreach ($mapRenamed AS $old => $newArr)
		{
			$newArr = (array) $newArr;
			foreach ($newArr AS $new)
			{
				$mapper->addMapping($old, $new);
			}
		}
		foreach ($mapColorToBorder AS $old => $newArr)
		{
			$newArr = (array) $newArr;
			foreach ($newArr AS $new)
			{
				$mapper->addMapping($old . '.color', $new . '.color');
				$mapper->addPreset($new . '.units', 'px');
				$mapper->addPreset($new . '.style', 'solid');
				$mapper->addPreset($new . '.width', '1');
			}
		}

		// Do the processing
		if ($mapper->load() AND $mapper->process())
		{
			$this->show_message($this->phrase['version']['408']['mapping_customized_stylevars']);
			//$mapper->displayResults(); // Debug only
			$mapper->processResults();
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Add url, url_title, meta fields to video table
	 */
	public function step_2()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'video', 1, 3),
			'video',
			'url',
			'VARCHAR',
			array('length' => 255)
		);
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'video', 2, 3),
			'video',
			'url_title',
			'VARCHAR',
			array('length' => 255)
		);
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'video', 3, 3),
			'video',
			'meta',
			'MEDIUMTEXT',
			self::FIELD_DEFAULTS
		);
	}

	public function step_3()
	{
		if (!$this->field_exists('permission', 'channeliconmaxsize'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'permission', 1, 1),
				'permission',
				'channeliconmaxsize',
				'INT',
				array('attributes' => 'UNSIGNED', 'null' => false, 'default' => 65535)
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Add oldfolderid field to messagefolder table
	*/
	public function step_4()
	{
		if (!$this->field_exists('messagefolder', 'oldfolderid'))
		{

			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'messagefolder ', 1, 2),
				'messagefolder',
				'oldfolderid',
				'tinyint',
				array('null' => true, 'default' => NULL)
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Add UNIQUE index to the userid, oldfolderid pair on the messagefolder table 
	* For ensuring no duplicate imports from vb4 custom folders
	*/ 
	public function step_5()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'messagefolder', 2, 2),
			'messagefolder',
			'userid_oldfolderid',
			array('userid', 'oldfolderid'),
			'unique'
		);
	}

	/**
	 * Importing custom folders
	 */
	public function step_6($data = array())
	{
		$assertor = vB::getDbAssertor();
		$batchsize = 1000;
		$startat = intval($data['startat']);

		// Check if any users have custom folders
		if (!empty($data['totalUsers']))
		{
			$totalUsers = $data['totalUsers'];
		}
		else
		{
			// Get the number of users that has custom pm folders
			$totalUsers = $assertor->getRow('vBInstall:getTotalUsersWithFolders');
			$totalUsers = intval($totalUsers['totalusers']);

			if (intval($totalUsers) < 1)
			{
				$this->skip_message();
				return;
			}
			else
			{
				$this->show_message($this->phrase['version']['500b27']['importing_custom_folders']);
			}
		}

		if ($startat >= $totalUsers)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}
		// Get the users for import
		$this->show_message(sprintf($this->phrase['core']['processing_records_x'], $batchsize));
		$users = $assertor->getRows('vBInstall:getUsersWithFolders', array('startat' => $startat, 'batchsize' => $batchsize));
		$inserValues = array();
		foreach ($users as $user)
		{
			$pmFolders = unserialize($user['pmfolders']);

			foreach ($pmFolders as $folderid => $title)
			{
				$inserValues[] = array(
					'userid' => $user['userid'],
					'title' => $title,
					'oldfolderid' => $folderid,
				);
			}
		}
		$assertor->assertQuery('insertignoreValues', array('table' => 'messagefolder', 'values' => $inserValues));

		return array('startat' => ($startat + $batchsize), 'totalUsers' => $totalUsers);
	}

	/**
	 * Dropping unique key on regex (recreating in step 14)
	 */
	public function step_7()
	{
		$this->drop_index(sprintf($this->phrase['core']['altering_x_table'], 'routenew', 1, 1), 'routenew', 'regex');
	}
	
	public function step_8()
	{
		$this->show_message($this->phrase['version']['500b27']['updating_conversation_routes']);
		
		// Update regular expressions to be less restrictive. However don't update custom conversation routes (when prefix = regex).
		// For those upgrading from 5.0.0 Alpha 1 - 5.0.0 Beta 26 to 5.0.0 Beta 27
		vB::getDbAssertor()->assertQuery('vBInstall:updateNonCustomConversationRoutes', array('regex' => vB5_Route_Conversation::REGEXP));
	}
	
	public function step_9()
	{
		$this->show_message($this->phrase['version']['500b27']['updating_routes']);
		
		// We're now using less restrictive url indentifiers. The regex needs to be updated to match.
		$guidsToUpdate = array(
			'vbulletin-4ecbdacd6aac05.50909923', // albums
			'vbulletin-4ecbdacd6aac05.50909924', // vistior message
			'vbulletin-4ecbdacd6aac05.50909925', // blog admin
			'vbulletin-4ecbdacd6aac05.50909980', // (social) group admin
		);
		$parsedXml = vB_Xml_Import::parseFile(dirname(__FILE__) . '/../vbulletin-routes.xml');

		$routes = array();
		foreach($parsedXml['route'] AS $t)
		{
			if (!in_array($t['guid'], $guidsToUpdate))
			{
				continue;
			}
			$routes[] = array('guid' => $t['guid'], 'regex' => $t['regex']);
		}
		
		vB::getDbAssertor()->assertQuery('vBInstall:updateRouteRegex', array('routes' => $routes));
	}
	
	/**
	 * Fix mimetype defaults
	 */
	public function step_10()
	{
		$assertor = vB::getDbAssertor();
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'attachmenttype'));
		$assertor->update('vBInstall:attachmenttype',
			array('mimetype' => serialize(array('Content-type: text/plain'))),
			array('extension' => 'txt')
		);
		$assertor->update('vBInstall:attachmenttype',
			array('mimetype' => serialize(array('Content-type: image/bmp'))),
			array('extension' => 'bmp')
		);
		$assertor->update('vBInstall:attachmenttype',
			array('mimetype' => serialize(array('Content-type: image/vnd.adobe.photoshop'))),
			array('extension' => 'psd')
		);
	}
	
	/**
	 * Fix pm responses starter
	 */
	public function step_11($data = array())
	{
		if($this->tableExists('pm') AND $this->tableExists('pmtext'))
		{
			$assertor = vB::getDbAssertor();
			$batchsize = 2000;
			$this->show_message($this->phrase['version']['500b27']['fixing_pm_records']);
			
			if (isset($data['startat']))
			{
				$startat = $data['startat'];
			}

			if (!empty($data['maxvB4']))
			{
				$maxPMid = $data['maxvB4'];
			}
			else
			{
				$maxPMid = $assertor->getRow('vBInstall:getMaxPMResponseToFix', array('contenttypeid' => 9981));
				$maxPMid = intval($maxPMid['maxid']);
				
				//If there are no responses to fix...
				if (intval($maxPMid) < 1)
				{
					$this->skip_message();
					return;
				}
			}
			
			if (!isset($startat))
			{
				$maxvB5 = $assertor->getRow('vBInstall:getMaxFixedPMResponse', array('contenttypeid' => 9981));

				if (!empty($maxvB5) AND !empty($maxvB5['maxid']))
				{
					$startat = $maxvB5['maxid'];
				}
				else
				{
					$startat = 1;
				}
			}

			if ($startat >= $maxPMid)
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}
			
			// fix starter from pm replies
			$assertor->assertQuery('vBInstall:setResponseStarter', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9981));
			$assertor->assertQuery('vBInstall:setShowValues', array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => 9981, 'value' => 1));
			return array('startat' => ($startat + $batchsize), 'maxvB4' => $maxPMid);
		}
		else
		{
			$this->skip_message();
		}
	}
	
	/** fixing ipv6 fields in strike table **/
	public function step_12()
	{
		$this->show_message(sprintf($this->phrase['core']['altering_x_table'], 'strikes', 1, 1));

		vB::getDbAssertor()->assertQuery('vBInstall:fixStrikeIPFields');
	}
	
	/**
	 * Modifying regex size in routenew
	 */
	public function step_13()
	{
		$this->show_message(sprintf($this->phrase['core']['altering_x_table'], 'routenew', 1, 1));

		vB::getDbAssertor()->assertQuery('vBInstall:alterRouteRegexSize', array('regexSize' => vB5_Route::REGEX_MAXSIZE));
	}
	
	/**
	 * Recreating regex index
	 */
	public function step_14()
	{
		$this->add_index(sprintf($this->phrase['core']['altering_x_table'], 'routenew', 1, 1), 'routenew', 'regex', 'regex');
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
