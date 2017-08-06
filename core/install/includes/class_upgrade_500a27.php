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

class vB_Upgrade_500a27 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a27';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 27';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 26';

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

	//Add userstylevar table
	public function step_1()
	{
		if (!$this->tableExists('userstylevar'))
		{
			$this->run_query(
					sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'userstylevar'),
					"CREATE TABLE " . TABLE_PREFIX . "userstylevar (
						stylevarid varchar(250) NOT NULL,
						userid int(6) NOT NULL DEFAULT '-1',
						value mediumblob NOT NULL,
						dateline int(10) NOT NULL DEFAULT '0',
						PRIMARY KEY  (stylevarid, userid)
					) ENGINE = " . $this->hightrafficengine . "
					",
					self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}

	}

	/* Add hook table for template hooks */
	public function step_2()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'hook'),
			"
				CREATE TABLE " . TABLE_PREFIX . "hook (
				hookid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				product VARCHAR(25) NOT NULL DEFAULT 'vbulletin',
				hookname VARCHAR(30) NOT NULL DEFAULT '',
				title VARCHAR(50) NOT NULL DEFAULT '',
				active TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
				hookorder TINYINT(3) UNSIGNED NOT NULL DEFAULT 10,
				template VARCHAR(30) NOT NULL DEFAULT '',
				arguments TEXT NOT NULL,
				PRIMARY KEY (hookid),
				KEY product (product, active, hookorder),
				KEY hookorder (hookorder)
			) ENGINE = " . $this->hightrafficengine . "
			",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}


	/* Add product column */
	function step_3()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'widget', 1, 1),
			"ALTER  TABLE  " . TABLE_PREFIX . "widget ADD product VARCHAR(25) NOT NULL DEFAULT 'vbulletin'",
			self::MYSQL_ERROR_COLUMN_EXISTS
		);
	}


	/* Add product column */
	function step_4()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'widgetdefinition', 1, 1),
			"ALTER  TABLE  " . TABLE_PREFIX . "widgetdefinition ADD product VARCHAR(25) NOT NULL DEFAULT 'vbulletin'",
			self::MYSQL_ERROR_COLUMN_EXISTS
		);
	}


	/* Add product index */
	function step_5()
	{
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'product', TABLE_PREFIX . 'widget'),
			'widget',
			'product',
			array('product')
		);
	}


	/* Add product index */
	function step_6()
	{
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'product', TABLE_PREFIX . 'widgetdefinition'),
			'widgetdefinition',
			'product',
			array('product')
		);
	}

	/* Get paymenttransaction table */
	function step_7()
	{
		$this->skip_message();
	}

	/** Add nav bar Social Groups link **/
	function step_8()
	{
		$this->show_message($this->phrase['version']['500a27']['adding_socialgroup_navbar_link']);
		$assertor = vB::getDbAssertor();
		$sites = $assertor->getRows('vBForum:site', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT));
		foreach ($sites as $site)
		{
			$headerNav = unserialize($site['headernavbar'])	;
			$foundSG = false;
			foreach ($headerNav as $key => $nav)
			{
				if (($nav['url'] == 'sghome') OR (($nav['url'] == 'social-groups') AND $foundSG))
				{
					unset($headerNav[$key]);
				}

				if ($nav['url'] == 'social-groups')
				{
					$foundSG = true;
				}
			}

			if ((!$foundSG))
			{
				$phrase = vB_Api::instanceInternal('phrase')->fetch(array('groups'));
				$headerNav[] = array('title' => $phrase['groups'], 'url' => 'social-groups', 'newWindow' => 0);
			}
			$assertor->assertQuery('vBForum:site', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'siteid' => $site['siteid'], 'headernavbar' => serialize($headerNav)));
		}
	}

	/** modifying "default" field in widgetdefinition **/
	public function step_9()
	{
		if ($this->field_exists('widgetdefinition', 'default'))
		{
			$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'widgetdefinition', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "widgetdefinition CHANGE COLUMN `default` defaultvalue BLOB NOT NULL"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/* Remove old products */
	function step_10($data = null)
	{
		$startat = intval($data['startat']);

		$product = vB::getDbAssertor()->getRow('product');
		require_once(DIR . '/includes/adminfunctions_product.php');

		if ($product)
		{
			if (!$startat)
			{
				$this->show_message($this->phrase['version']['500a27']['products_removal']);
			}

			delete_product($product['productid']);
			$this->show_message(sprintf($this->phrase['version']['500a27']['removed_product'],$product['title']));
			return array('startat' => $startat+1);
		}
		else
		{
			if (!$startat)
			{
				$this->skip_message();
			}
			else
			{
				$this->show_message($this->phrase['version']['500a27']['products_removed']);
			}
		}
	}

	/** Importing vb4 profile stylevars to vb5 **/
	public function step_11()
	{
		if ($this->tableExists('customprofile'))
		{
			$this->show_message($this->phrase['version']['500a27']['mapping_vb4_vb5_profile']);

			$results = vB::getDbAssertor()->assertQuery('vBForum:customprofile', array(vB_db_Query::TYPE_KEY => vB_db_Query::QUERY_SELECT));

			$replaceValues = array();
			foreach($results as $profile_customization)
			{
				$stylevars = array();

				// Active Tabs
				if (!empty($profile_customization['module_background_color']))
				{
					$stylevars['module_tab_background_active']['color'] = $profile_customization['module_background_color'];
				}
				if (!empty($profile_customization['module_background_image']))
				{
					$stylevars['module_tab_background_active']['image'] = $profile_customization['module_background_image'];
				}
				if (!empty($profile_customization['module_background_repeat']))
				{
					$stylevars['module_tab_background_active']['repeat'] = $profile_customization['module_background_repeat'];
				}
				if (!empty($profile_customization['module_border']))
				{
					$stylevars['module_tab_border_active']['color'] = $profile_customization['module_border'];
				}
				if (!empty($profile_customization['module_text_color']))
				{
					$stylevars['module_tab_text_color_active']['color'] = $profile_customization['module_text_color'];
				}

				// Inactive Tabs
				if (!empty($profile_customization['moduleinactive_background_color']))
				{
					$stylevars['module_tab_background']['color'] = $profile_customization['moduleinactive_background_color'];
				}
				if (!empty($profile_customization['moduleinactive_background_image']))
				{
					$stylevars['module_tab_background']['image'] = $profile_customization['moduleinactive_background_image'];
				}
				if (!empty($profile_customization['moduleinactive_background_repeat']))
				{
					$stylevars['module_tab_background']['repeat'] = $profile_customization['moduleinactive_background_repeat'];
				}
				if (!empty($profile_customization['moduleinactive_border']))
				{
					$stylevars['module_tab_border']['color'] = $profile_customization['moduleinactive_border'];
				}
				if (!empty($profile_customization['moduleinactive_text_color']))
				{
					$stylevars['module_tab_text_color']['color'] = $profile_customization['moduleinactive_text_color'];
				}

				// Buttons
				if (!empty($profile_customization['button_background_color']))
				{
					$stylevars['profile_button_primary_background']['color'] = $profile_customization['button_background_color'];
				}
				if (!empty($profile_customization['button_background_image']))
				{
					$stylevars['profile_button_primary_background']['image'] = $profile_customization['button_background_image'];
				}
				if (!empty($profile_customization['button_background_repeat']))
				{
					$stylevars['profile_button_primary_background']['repeat'] = $profile_customization['button_background_repeat'];
				}
				if (!empty($profile_customization['button_border']))
				{
					$stylevars['button_primary_border']['color'] = $profile_customization['button_border'];
				}
				if (!empty($profile_customization['button_text_color']))
				{
					$stylevars['button_primary_text_color']['color'] = $profile_customization['button_text_color'];
				}

				// Content
				if (!empty($profile_customization['content_background_color']))
				{
					$stylevars['profile_content_background']['color'] = $profile_customization['content_background_color'];
				}
				if (!empty($profile_customization['content_background_image']))
				{
					$stylevars['profile_content_background']['image'] = $profile_customization['content_background_image'];
				}
				if (!empty($profile_customization['content_background_repeat']))
				{
					$stylevars['profile_content_background']['repeat'] = $profile_customization['content_background_repeat'];
				}
				if (!empty($profile_customization['content_border']))
				{
					$stylevars['profile_content_border']['color'] = $profile_customization['content_border'];
				}
				if (!empty($profile_customization['content_text_color']))
				{
					$stylevars['profile_content_primarytext']['color'] = $profile_customization['content_text_color'];
				}
				if (!empty($profile_customization['content_link_color']))
				{
					$stylevars['profile_content_linktext']['color'] = $profile_customization['content_link_color'];
				}

				// Content Headers
				if (!empty($profile_customization['headers_background_color']))
				{
					$stylevars['profile_section_background']['color'] = $profile_customization['headers_background_color'];
				}
				if (!empty($profile_customization['headers_background_image']))
				{
					$stylevars['profile_section_background']['image'] = $profile_customization['headers_background_image'];
				}
				if (!empty($profile_customization['headers_background_repeat']))
				{
					$stylevars['profile_section_background']['repeat'] = $profile_customization['headers_background_repeat'];
				}
				if (!empty($profile_customization['headers_border']))
				{
					$stylevars['profile_section_border']['color'] = $profile_customization['headers_border'];
				}
				if (!empty($profile_customization['headers_text_color']))
				{
					$stylevars['profile_section_text_color']['color'] = $profile_customization['headers_text_color'];
				}

				foreach ($stylevars as $stylevar => $value)
				{
					$replaceValues[] = array(
						'stylevarid' => $stylevar,
						'userid' => $profile_customization['userid'],
						'value' => serialize($value),
						'dateline' => vB::getRequest()->getTimeNow()
					);
				}
			}
			vB::getDbAssertor()->assertQuery('insertignoreValues', array('table' => 'userstylevar', 'values' => $replaceValues));
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Step 12 removed, it was a duplicate of stuff done in step 8 **/
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
