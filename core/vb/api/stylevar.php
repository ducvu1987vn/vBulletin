<?php

/**
 * vB_Api_Stylevar
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Stylevar extends vB_Api
{
	private static $cssMappings = array(
		// Background Stylevars
		"profcustom_navbar_background_active" => 'bgimage',
		"profcustom_navbar_background" => 'bgimage',
		"profcustom_navbarbutton_background" => 'bgimage',
		"profile_button_secondary_background" => 'bgimage',
		"toolbar_background" => 'bgimage',
		"profile_content_background" => 'bgimage',
		"profile_section_background" => 'bgimage',
		"profilesidebar_button_background" => 'bgimage',
		"side_nav_background" => 'bgimage',
		"profile_button_primary_background" => 'bgimage',

		// Border
		"profcustom_navbar_border_active" => 'borders',
		"profcustom_navbar_border" => 'borders',
		"profcustom_navbarbutton_border" => 'borders',
		"side_nav_divider_border" => 'borders',
		"profcustom_navbarbuttonsecondary_border" => 'borders',
		"profile_content_border" => 'borders',
		"profile_content_divider_border" => 'borders',
		"profile_section_border" => 'borders',
		"form_dropdown_border" => 'borders',
		"side_nav_avatar_border" => 'borders',
		"side_nav_divider_border" => 'borders',
		"profilesidebar_button_border" => 'borders',
		"button_primary_border" => 'borders',

		// Color
		"profcustom_navbar_text_color_active" => 'colors',
		"profcustom_navbar_text_color" => 'colors',
		"profcustom_navbar_toolbar_text_color" => 'colors',
		"profile_section_text_color" =>  'colors',
		"profcustom_navbarbutton_color" =>  'colors',
		"profcustom_navbarbuttonsecondary_color" => 'colors',
		"profile_content_primarytext" =>  'colors',
		"profile_content_secondarytext" =>  'colors',
		"profile_content_linktext" =>  'colors',
		"profile_userpanel_textcolor" =>  'colors',
		"profile_userpanel_linkcolor" =>  'colors',
		"profilesidebar_button_text_color" =>  'colors',
		"button_primary_text_color" =>  'colors',

		// Font family
		"profile_section_font" => 'fontfamily',
		"profile_content_font" => 'fontfamily',
		"profile_userpanel_font" => 'fontfamily',
	);

	/**
	 * Saves the stylevars specified in the array for the current user
	 *
	 * @param array $stylevars - associative array like array('activity_stream_avatar_border_color' => array('color' => '#123456'))
	 */
	function save($stylevars)
	{
		$result = array();

		$userid = vB::getCurrentSession()->get('userid');

		if (empty($userid))
		{
			$result['error'][] = 'logged_out_while_editing_post';
		}

		if (!$this->hasPermissions())
		{
			$result['error'][] = 'no_permission';
		}

		if (!isset($result['error']))
		{
			$values = array();
			$now = vB::getRequest()->getTimeNow();
			foreach ($stylevars as $stylevarname => $stylevarvalue)
			{
				if (isset(self::$cssMappings[$stylevarname]))
				{
					if (self::$cssMappings[$stylevarname] == 'fontfamily')
					{
						foreach ($stylevarvalue as $key => $val)
						{
							if (!vB::getUserContext()->hasPermission('usercsspermissions', 'caneditfont' . $key))
							{
								unset($stylevarvalue[$key]);
							}
						}

						$values[] = array(
							'stylevarid' => $stylevarname,
							'userid' => $userid,
							'value' => serialize($stylevarvalue),
							'dateline' => $now
						);
					}
					elseif (vB::getUserContext()->hasPermission('usercsspermissions', 'canedit' . self::$cssMappings[$stylevarname]))
					{
						$values[] = array(
							'stylevarid' => $stylevarname,
							'userid' => $userid,
							'value' => serialize($stylevarvalue),
							'dateline' => $now
						);
					}
				}
			}
			vB::getDbAssertor()->assertQuery('replaceValues', array('table' => 'userstylevar', 'values' => $values));
			//vB::getDbAssertor()->insertMultiple('userstylevar', array('stylevarid', 'userid','value','dateline'), $values);
		}

		vB_Library::instance('Style')->setCssDate();

		return $result;
	}

	/**
	 * Saves the stylevars specified in the array as default style for the whole site
	 *
	 * @param array $stylevars - associative array
	 */
	function save_default($stylevars)
	{
		$result = array();

		if (!$this->canSaveDefault())
		{
			$result['error'][] = 'no_permission_styles';
		}

		if (!$this->hasPermissions())
		{
			$result['error'][] = 'no_permission';
		}

		if (!isset($result['error']))
		{
			$values = array();
			$now = vB::getRequest()->getTimeNow();

			$styleid = vB::getDatastore()->getOption('styleid');

			foreach ($stylevars as $stylevarname => $stylevarvalue)
			{
				$values[] = array(
					'stylevarid' => $stylevarname,
					'styleid' => $styleid,
					'value' => serialize($stylevarvalue),
					'dateline' => $now
				);
			}
			vB::getDbAssertor()->assertQuery('replaceValues', array('table' => 'stylevar', 'values' => $values));
			vB_Library::instance('Style')->buildStyleDatastore();

			require_once(DIR . '/includes/adminfunctions_template.php');
			build_style($styleid, '', array(
					'docss' => 1,
					'dostylevars' => 1,
					/*'doreplacements' => 1,
					'doposteditor' => 1*/
			), '', '', true, false);
		}

		vB_Library::instance('Style')->setCssDate();

		return $result;
	}
	/**
	 * Deletes the listed stylevars for the current user
	 * Pass false to delete all the stylevars for the current user
	 * @param array|false $stylevars - list of stylevar names to delete
	 */
	function delete($stylevars = array())
	{
		$userid = vB::getCurrentSession()->get('userid');
		if (empty($userid))
		{
			return;
		}
		$options = array(
				array('field'=>'userid', 'value' => $userid, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ)
		);

		if (!empty($stylevars))
		{
			$stylevars = array_combine($stylevars, $stylevars);
			$options[] =  array('field'=>'stylevarid', 'value' => $stylevars, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ);
		}

		vB::getDbAssertor()->delete('userstylevar', $options);

		vB_Library::instance('Style')->setCssDate();
	}

	/**
	 * Fetches the value of the stylevar for the user $userid
	 * Pass 0 for userid to retrieve the stylevar for the current user
	 * If the stylevar is not customized for the specified user, the value from the default stylevar will be returned
	 * Pass false for $falback to limit the results to the custom stylevar only
	 * @param string $stylevar
	 * @param int $userid
	 * @param bool $fallback
	 * @return array valid key should be the value of the $stylevars
	 */
	function get($stylevarname, $userid = 0, $fallback = true)
	{
		if (empty($userid))
		{
			$userid = vB::getCurrentSession()->get('userid');
		}

		if (empty($userid))
		{
			return array();
		}

		$default_stylevars = array();
		if ($fallback)
		{
			$styleid = vB::getDatastore()->getOption('styleid');
			if ($styleid > 0)
			{
				$parentlist = vB_Library::instance('style')->fetchTemplateParentlist($styleid);
				$parentlist = explode(',',trim($parentlist));
			}
			else
			{
				$parentlist = array('-1');
			}

			$default_stylevars = vB::getDbAssertor()->getRow('fetchStylevarsArray', array('parentlist' => $parentlist, 'stylevars' => array($stylevarname), 'sortdir' => vB_dB_Query::SORT_DESC));
			$default_stylevars = (!empty($default_stylevars)) ? unserialize($default_stylevars['value']) : array();
		}

		$userstylevar = vB::getDbAssertor()->getRow('userstylevar', array('stylevarid' => $stylevarname, 'userid' =>$userid));

		if (!empty($userstylevar))
		{
			$userstylevar = unserialize($userstylevar['value']);
		}
		else
		{
			$userstylevar = array();
		}
		return array($stylevarname => array_merge($default_stylevars, $userstylevar));
	}

	/**
	 * Fetches the stylevar values for the user $userid
	 * Pass false for $stylevars to get all the stylevars
	 * Pass 0 for userid to retrieve the stylevar for the current user
	 * Returns an associative array with keys being the list specified in the $stylevar
	 * If any of the stylevars is not customized for the specified user, the value from the default stylevar will be returned instead
	 * Pass false for $falback to limit the results to the custom stylevars only
	 * @param array|false $stylevars
	 * @param int $userid
	 * @param bool $fallback
	 * @return array
	 */
	function fetch($stylevars = array(), $userid = 0, $fallback = true)
	{
		if (empty($userid))
		{
			$userid = vB::getCurrentSession()->get('userid');
		}

		if (empty($userid))
		{
			return;
		}
		$need_all = empty($stylevars);

		$stylevar_values = array();
		$conditions = array(
			array('field'=>'userid', 'value' => $userid, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ)
		);

		if (!$need_all)
		{
			$stylevars = array_combine($stylevars, $stylevars);
			$conditions[] =  array('field'=>'stylevarid', 'value' => $stylevars, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ);
		}

		if ($fallback)
		{
			$styleid = vB::getDatastore()->getOption('styleid');
			if ($styleid > 0)
			{
				$parentlist = vB_Library::instance('style')->fetchTemplateParentlist($styleid);
				$parentlist = explode(',',trim($parentlist));
			}
			else
			{
				$parentlist = array('-1');
			}

			$default_stylevar_res = vB::getDbAssertor()->assertQuery('fetchStylevarsArray', array('parentlist' => $parentlist, 'stylevars' => $need_all ? array() : $stylevars));
			foreach ($default_stylevar_res as $default_stylevar)
			{
				$stylevar_values[$default_stylevar['stylevarid']] = unserialize($default_stylevar['value']);
			}
		}
		$userstylevar_res = vB::getDbAssertor()->assertQuery('userstylevar', array(vB_dB_Query::CONDITIONS_KEY=> $conditions));

		foreach ($userstylevar_res as $stylevar)
		{
			$custom_stylevar = unserialize($stylevar['value']);
			if (!empty($stylevar_values[$stylevar['stylevarid']]))
			{
				$custom_stylevar = array_merge($stylevar_values[$stylevar['stylevarid']], $custom_stylevar);
			}

			$stylevar_values[$stylevar['stylevarid']] = $custom_stylevar;
		}

		return $stylevar_values;
	}

	function fetch_default_stylevar($stylevars = array(), $styleid = false)
	{
		$stylevar_values = array();
		if (empty($styleid))
		{
			$styleid = vB::getDatastore()->getOption('styleid');
		}
		if ($styleid > 0)
		{
			$parentlist = vB_Library::instance('style')->fetchTemplateParentlist($styleid);
			$parentlist = explode(',',trim($parentlist));
		}
		else
		{
			$parentlist = array('-1');
		}
		$need_all = empty($stylevars);
		$default_stylevar_res = vB::getDbAssertor()->assertQuery('fetchStylevarsArray', array('parentlist' => $parentlist, 'stylevars' => $need_all ? array() : $stylevars));
		foreach ($default_stylevar_res as $default_stylevar)
		{
			$stylevar_values[$default_stylevar['stylevarid']] = unserialize($default_stylevar['value']);
			$stylevar_values[$default_stylevar['stylevarid']]['datatype'] = $default_stylevar['datatype'];
		}
		return $stylevar_values;
	}
	/**
	 * Fetches the stylevar values for the user $userid
	 * @param int $userid
	 * @return array
	 */
	function fetch_user_stylevars($userid = 0)
	{
		if (empty($userid))
		{
			$userid = vB::getCurrentSession()->get('userid');
		}

		if (empty($userid))
		{
			return;
		}

		$conditions = array(
			array('field'=>'userid', 'value' => $userid, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_EQ)
		);

		$userstylevar_res = vB::getDbAssertor()->assertQuery('userstylevar', array(vB_dB_Query::CONDITIONS_KEY=> $conditions));
		$stylevar_values = array();

		foreach ($userstylevar_res as $stylevar)
		{
			$stylevar_values[$stylevar['stylevarid']] = unserialize($stylevar['value']);
		}
		return (!empty($stylevar_values) ? $stylevar_values : array());
	}

	/**
	 * Check whether the profile page of an user is customized
	 *
	 * @param $userid User ID
	 * @return bool
	 */
	public function isProfileCustomized($userid = 0)
	{
		if (empty($userid))
		{
			$userid = vB::getCurrentSession()->get('userid');
		}

		if (empty($userid))
		{
			return false;
		}

		$count = vB::getDbAssertor()->getField('userstylevarCount', array('userid' => $userid));

		if ($count > 0)
		{
			return true;
		}
		return false;
	}

	/**
	 * This is just a public method for calling the hasPermissions method
	 */
	public function canCustomizeProfile()
	{
		return $this->hasPermissions();
	}

	/**
	 * Checkes if the current loged user has admin permisions
	 * for administration of styles
	 *
	 * @return boolean
	 */
	public function canSaveDefault()
	{
		return vB::getUserContext()->hasAdminPermission('canadminstyles');
	}

	/**
	 * Returns all the permissions that the currently logged user
	 * has for customizing profile
	 *
	 * @return array
	 */
	public function fetchCustomizationPermissions()
	{
		$permissions = array(
			'fontFamily' => 0,
			'fontSize'	 => 0,
			'colors'	 => 0,
			'bgimage'	 => 0,
			'borders'	 => 0,
		);

		if (!vB::getCurrentSession()->get('userid'))
		{
			return $permissions;
		}

		$permissions['fontFamily'] = vB::getUserContext()->hasPermission('usercsspermissions', 'caneditfontfamily');
		$permissions['fontSize']   = vB::getUserContext()->hasPermission('usercsspermissions', 'caneditfontsize');
		$permissions['colors'] 	= vB::getUserContext()->hasPermission('usercsspermissions', 'caneditcolors');
		$permissions['bgimage'] = vB::getUserContext()->hasPermission('usercsspermissions', 'caneditbgimage');
		$permissions['borders'] = vB::getUserContext()->hasPermission('usercsspermissions', 'caneditborders');

		return $permissions;
	}

	/**
	 * Checks if the currently logged user has permissions
	 * for profile style customization
	 *
	 *	@return boolean
	 */
	private function hasPermissions()
	{
		// Guest ...
		if (!vB::getCurrentSession()->get('userid'))
		{
			return false;
		}

		$usercontext = vB::getUserContext();
		$options = vB::getDatastore()->getValue('options');

		$enabled = $options['enable_profile_styling'];
		$cancustomize = $usercontext->hasPermission('usercsspermissions', 'cancustomize');

		if ($enabled AND $cancustomize)
		{
			return true;
		}

		return false;
	}
}
