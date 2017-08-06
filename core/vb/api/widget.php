<?php if (!defined('VB_ENTRY')) die('Access denied.');
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

/**
 * vB_Api_Widget
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Widget extends vB_Api
{
	const WIDGETCATEGORY_SYSTEM = 'System';

	// Following members are cached data from fetchWidgetInstancesByPageTemplateId()
	protected $preloadWidgetIds = array();
	protected $pagetemplateid = 0;
	protected $sectionnumber = -1; // We use 0 to request all sections

	protected function __construct()
	{
		parent::__construct();
	}

	public function isSystemWidget($widgetId)
	{
		static $systemWidgetIds;

		if (!isset($systemWidgetIds) OR empty($systemWidgetIds))
		{
			$widgets = vB::getDbAssertor()->assertQuery('widget', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::COLUMNS_KEY => array('widgetid'),
				vB_dB_Query::CONDITIONS_KEY => array('category' => self::WIDGETCATEGORY_SYSTEM)
			));

			foreach ($widgets as $widget)
			{
				$systemWidgetIds[] = $widget['widgetid'];
			}
		}

		return in_array($widgetId, $systemWidgetIds);
	}

	/**
	 * Returns the widget configuration schema for the given widget instance.
	 * If no widget instance ID is given, one is created. If no page template ID
	 * is given, one is created (to be able to create the widget instance). If the
	 * widget instance ID is given, the returned config fields will contain the
	 * current values of the configured widget instance for the config type
	 * specified.
	 *
	 * @param	int	The widget ID for this widget instance
	 * @param	int	The widget instance ID that is to be configured (can be zero)
	 * @param	int	The page template ID that this widget instance belongs to (can be zero)
	 * @param	string	Specifies a config type of either "user" or "admin"
	 * @param	int	The user ID to fetch the user config from, if config type is "user" (optional)
	 *
	 * @return 	array	An array containing widgetid, widgetinstanceid, pagetemplateid, and an
	 *			array of config fields to generate the edit configuration form
	 */
	public function fetchConfigSchema($widgetid, $widgetinstanceid = 0, $pagetemplateid = 0, $configtype = 'admin', $userid = 0)
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		$widgetid = intval($widgetid);
		$widgetinstanceid = intval($widgetinstanceid);
		$pagetemplateid = intval($pagetemplateid);
		$configtype = strtolower($configtype);
		$userid = intval($userid);

		if ($widgetid < 1)
		{
			throw new Exception('Invalid widget ID specified: ' . htmlspecialchars($widgetid));
		}

		if (!in_array($configtype, array('user', 'admin'), true))
		{
			throw new Exception('Invalid config type specified: ' . htmlspecialchars($widgetid));
		}

		if ($pagetemplateid < 1)
		{
			$pagetemplateid = $this->_getNewPageTemplateId();
		}

		if ($widgetinstanceid < 1)
		{
			$widgetinstanceid = $this->_getNewWidgetInstanceId($widgetid, $pagetemplateid);
		}


		$configFields = $this->_getWidgetConfigFields($widgetid, $widgetinstanceid, $configtype, $userid);

		return array(
			'widgetid' => $widgetid,
			'widgetinstanceid' => $widgetinstanceid,
			'pagetemplateid' => $pagetemplateid,
			'configs' => $configFields,
		);
	}

	/**
	 * Returns the final configuration for a specific widget instance.
	 *
	 * @param	int	The widget instance ID
	 * @param	int	The user ID (optional)
	 *
	 * @return	array	An associative array of the widget config items and their values
	 */
	public function fetchConfig($widgetinstanceid, $userid = 0, $channelId = 0)
	{
		$widgetinstanceid = intval($widgetinstanceid);
		$widgetInstance = $this->_getWidgetInstance($widgetinstanceid); /** the response must include widgetid (VBV-199) **/
		$userid = intval($userid);

		if ($userid > 0)
		{
			$userConfig = $this->fetchUserConfig($widgetinstanceid, $userid);
			if ($userConfig !== false)
			{
				$userConfig['widgetid'] = $widgetInstance['widgetid'];
				$userConfig['widgetinstanceid'] = $widgetinstanceid;
				return $userConfig;
			}
		}

		if ($channelId > 0)
		{
			$channelConfig = $this->fetchChannelConfig($widgetinstanceid, $channelId);
			if ($channelConfig !== false)
			{
				$channelConfig['widgetid'] = $widgetInstance['widgetid'];
				$channelConfig['widgetinstanceid'] = $widgetinstanceid;
				return $channelConfig;
			}
		}

		$adminConfig = $this->fetchAdminConfig($widgetinstanceid);

		if ($adminConfig !== false)
		{
			$adminConfig['widgetid'] = $widgetInstance['widgetid'];
			$adminConfig['widgetinstanceid'] = $widgetinstanceid;
			return $adminConfig;
		}

		return $this->fetchDefaultConfig($widgetinstanceid);
	}

	/**
	 * Returns the final configuration for the search widget instance.
	 *
	 * @param	int	The widget instance ID
	 * @param	int	The user ID (optional)
	 *
	 * @return	array	An associative array of the widget config items and their values
	 */
	public function fetchSearchConfig($widgetinstanceid, $userid = 0)
	{

		$widgetinstanceid = intval($widgetinstanceid);
		$userid = intval($userid);
		$contentTypes = vB_Types::instance()->getSearchableContentTypes();
		$channels = vB_Api::instanceInternal("Search")->getChannels();
		if ($userid > 0)
		{
			$userConfig = $this->fetchUserConfig($widgetinstanceid, $userid);
			if ($userConfig !== false)
			{
				return array_merge($userConfig,array('contentTypes' => $contentTypes, 'channels' => $channels));
			}
		}

		$adminConfig = $this->fetchAdminConfig($widgetinstanceid);
		if ($adminConfig !== false)
		{
			return array_merge($adminConfig, array('contentTypes' => $contentTypes, 'channels' => $channels));
		}

		return array_merge($this->fetchDefaultConfig($widgetinstanceid), array('contentTypes' => $contentTypes, 'channels' => $channels));
	}

	/**
	 * Returns the admin configuration for a specific widget instance.
	 *
	 * @param	int	The widget instance ID
	 *
	 * @return	array|false	An associative array of the widget config items and their values
	 * 				False if there is no admin config for this widget
	 */
	public function fetchAdminConfig($widgetinstanceid)
	{
		$widgetinstanceid = intval($widgetinstanceid);

		$widgetInstance = $this->_getWidgetInstance($widgetinstanceid);
		$adminConfig = unserialize($widgetInstance['adminconfig']);

		if (!empty($adminConfig))
		{
			/* Set a default value to stop php
			notices in widget template rendering */
			if (!isset($adminConfig['icon']))
			{
				$adminConfig['icon'] = null;
			}

			return $adminConfig;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Returns the channel configuration for a specific widget instance.
	 *
	 * @param	int	The widget instance ID
	 * @param	int	The channel ID
	 *
	 * @return	array|false	An associative array of the widget config items and
	 *				their values, or false if there is no channel config
	 *				for this widget and channel.
	 */
	public function fetchChannelConfig($widgetinstanceid, $nodeId)
	{
		$widgetinstanceid = intval($widgetinstanceid);
		$nodeId = intval($nodeId);

		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$cachekey = 'widgetChannelConfig_' . $this->pagetemplateid . '_' . $this->sectionnumber . '_' . $nodeId;
		$cacheevent = 'widgetChannelConfigChg_' . $this->pagetemplateid . '_' . $this->sectionnumber . '_' . $nodeId;
		$cachedchannelconfig = $cache->read($cachekey);

		// If we have the cache, return it
		if (isset($cachedchannelconfig[$widgetinstanceid][$nodeId]))
		{
			return !empty($cachedchannelconfig[$widgetinstanceid][$nodeId])?$cachedchannelconfig[$widgetinstanceid][$nodeId]:false;
		}

		// If we reach here, we don't have cache.

		// Check if $widgetinstanceid is in $this->preloadWidgetIds
		// If so, we write the cache for all preloadWidgetIds
		if ($this->preloadWidgetIds AND in_array($widgetinstanceid, $this->preloadWidgetIds))
		{
			$result = vB::getDbAssertor()->getRows(
				'widgetchannelconfig',
				array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'widgetinstanceid' => $this->preloadWidgetIds,
					'nodeid' => $nodeId,
				)
			);

			$cachedchannelconfig = array();
			foreach ($result as $row)
			{
				$cachedchannelconfig[$row['widgetinstanceid']][$row['nodeid']] = unserialize($row['channelconfig']);
			}
			$cache->write($cachekey, $cachedchannelconfig, false, array($cacheevent));

			if (isset($cachedchannelconfig[$widgetinstanceid][$nodeId]))
			{
				return !empty($cachedchannelconfig[$widgetinstanceid][$nodeId])?$cachedchannelconfig[$widgetinstanceid][$nodeId]:false;
			}
		}

		// If we reach here, it means that $widgetinstaceid isn't included in our cache
		// We do separated query
		$result = vB::getDbAssertor()->assertQuery(
			'widgetchannelconfig',
			array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'widgetinstanceid' => $widgetinstanceid,
				'nodeid' => $nodeId,
			)
		);

		if ($result->valid())
		{
			$channelConfig = $result->current();
			return unserialize($channelConfig['channelconfig']);
		}

		return false;
	}

	/**
	 * Returns the user configuration for a specific widget instance.
	 *
	 * @param	int	The widget instance ID
	 * @param	int	The user ID
	 *
	 * @return	array|false	An associative array of the widget config items and
	 *				their values, or false if there is no user config
	 *				for this widget and user.
	 */
	public function fetchUserConfig($widgetinstanceid, $userid)
	{
		$widgetinstanceid = intval($widgetinstanceid);
		$userid = intval($userid);

		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$cachekey = 'widgetUserConfig_' . $this->pagetemplateid . '_' . $this->sectionnumber . '_' . $userid;
		$cacheevent = 'widgetUserConfigChg_' . $this->pagetemplateid . '_' . $this->sectionnumber . '_' . $userid;
		$cacheduserconfig = $cache->read($cachekey);

		// If we have the cache, return it
		if (isset($cacheduserconfig[$widgetinstanceid][$userid]))
		{
			return !empty($cacheduserconfig[$widgetinstanceid][$userid])?$cacheduserconfig[$widgetinstanceid][$userid]:false;
		}

		// If we reach here, we don't have cache.

		// Check if $widgetinstanceid is in $this->preloadWidgetIds
		// If so, we write the cache for all preloadWidgetIds
		if ($this->preloadWidgetIds AND in_array($widgetinstanceid, $this->preloadWidgetIds))
		{
			$result = vB::getDbAssertor()->getRows(
				'widgetuserconfig',
				array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'widgetinstanceid' => $this->preloadWidgetIds,
					'userid' => $userid,
				)
			);

			$cacheduserconfig = array();
			foreach ($result as $row)
			{
				$cacheduserconfig[$row['widgetinstanceid']][$row['userid']] = unserialize($row['userconfig']);
			}
			$cache->write($cachekey, $cacheduserconfig, false, array($cacheevent));

			if (isset($cacheduserconfig[$widgetinstanceid][$userid]))
			{
				return !empty($cacheduserconfig[$widgetinstanceid][$userid])?$cacheduserconfig[$widgetinstanceid][$userid]:false;
			}
		}

		// If we reach here, it means that $widgetinstaceid isn't included in our cache
		// We do separated query
		$result = vB::getDbAssertor()->assertQuery(
			'widgetuserconfig',
			array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'widgetinstanceid' => $widgetinstanceid,
				'userid' => $userid,
			)
		);

		if ($result->valid())
		{
			$userConfig = $result->current();
			return unserialize($userConfig['userconfig']);
		}

		return false;
	}

	/**
	 * Returns the default configuration for a specific widget instance.
	 *
	 * @param	int	The widget instance ID
	 *
	 * @return	array	An associative array of the widget config items and their values
	 */
	public function fetchDefaultConfig($widgetinstanceid)
	{
		$widgetinstanceid = intval($widgetinstanceid);

		$widgetInstance = $this->_getWidgetInstance($widgetinstanceid);
		$fields = $this->_getWidgetDefinition($widgetInstance['widgetid']);

		$defaultConfig = array(
			'widgetid' => $widgetInstance['widgetid'],
			'widgetinstanceid' => $widgetInstance['widgetinstanceid'],
			'icon' => null
		);

		foreach ($fields as $field)
		{
			$data = @unserialize($field['defaultvalue']);
			if ($data === false && $data !== 'b:0;')
			{
				$data = $field['defaultvalue'];
			}
			$defaultConfig[$field['name']] = $data;
		}

		return $defaultConfig;
	}

	/**
	 * Saves an admin widget configuration for the given widget instance
	 *
	 * @param	int	The widget ID for this widget instance
	 * @param	int	The page template ID that this widget instance belongs to
	 * @param	int	The widget instance ID that is being configured
	 * @param	array	An associative array of widget configuration data
	 *
	 * @return 	bool	Whether or not the widget configuration was saved.
	 */
	public function saveAdminConfig($widgetid, $pagetemplateid, $widgetinstanceid, $data)
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		$widgetid = intval($widgetid);
		$widgetinstanceid = intval($widgetinstanceid);
		$pagetemplateid = intval($pagetemplateid);

		if ($widgetid < 1 OR $widgetinstanceid < 1)
		{
			return false;
		}

		$configFields = $this->_getWidgetConfigFields($widgetid);
		$configData = array();
		if ($configFields)
		{
			foreach ($configFields AS $configField)
			{
				if (!isset($data[$configField['name']]) AND empty($configField['isrequired']))
				{
					continue;
				}
				// @todo - THIS DATA NEEDS TO BE CLEANED AND VALIDATED!!
				$configData[$configField['name']] = $data[$configField['name']];
			}

			$configData['widget_type'] = empty($data['widget_type']) ? '' : $data['widget_type'];
		}
		else
		{
			// arbitrary data for this widget
			$configData = $data;
		}

		if (!empty($data['widget_type']) AND $data['widget_type'] == 'video-widget' AND isset($data['url']))
		{
			$videoData = vB_Api::instanceInternal('Content_Video')->getVideoFromUrl($data['url']);
			if (!empty($videoData))
			{
				$configData['embed_data'] = array(
					'provider'	=> $videoData['provider'],
					'code'		=> $videoData['code'],
				);
			}
		}

		// @todo --- clean, validate, and sanitize $configData




		$options = array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'widgetinstanceid' => $widgetinstanceid,
			'adminconfig' => serialize($configData),
		);

		$result = vB::getDbAssertor()->assertQuery(
			'widgetinstance',
			$options
		);
		// there is no way to tell from a failed query and
		// a query that didn't change rows (the data was the same

		return array(
			'widgetid' => $widgetid,
			'widgetinstanceid' => $widgetinstanceid,
			'pagetemplateid' => $pagetemplateid,
			'data' => $configData,
		);
	}

	/**
	 * Saves a channel widget configuration for the given widget instance
	 *
	 * @param	int	The widget instance ID that is being configured
	 * @param	int The channel ID that is being configured
	 * @param	array	An associative array of widget configuration data
	 *
	 * @return 	bool	Whether or not the widget configuration was saved.
	 */
	public function saveChannelConfig($widgetinstanceid, $nodeid, $data)
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		$widgetinstanceid = intval($widgetinstanceid);

		$widgetInstance = vB::getDbAssertor()->getRow('widgetinstance', array('widgetinstanceid' => $widgetinstanceid));

		$widgetid = intval($widgetInstance['widgetid']);

		if ($widgetid < 1 OR $widgetinstanceid < 1)
		{
			return false;
		}

		$configFields = $this->_getWidgetConfigFields($widgetid);
		$configData = array();
		if ($configFields)
		{
			foreach ($configFields AS $configField)
			{
				if (!isset($data[$configField['name']]) AND empty($configField['isrequired']))
				{
					continue;
				}
				// @todo - THIS DATA NEEDS TO BE CLEANED AND VALIDATED!!
				$configData[$configField['name']] = $data[$configField['name']];
			}
			$configData['widget_type'] = $data['widget_type'];
		}
		else
		{
			// arbitrary data for this widget
			$configData = $data;
		}

		// @todo --- clean, validate, and sanitize $configData
		$current = vB::getDbAssertor()->getRow('widgetchannelconfig', array('widgetinstanceid' => $widgetinstanceid, 'nodeid' => $nodeid));
		if ($current)
		{
			$config = unserialize($current['channelconfig']);
			foreach($configData AS $key => $value)
			{
				$config[$key] = $value;
			}

			vB::getDbAssertor()->update('widgetchannelconfig',
					array('channelconfig' => serialize($config)),
					array('widgetinstanceid' => $widgetinstanceid, 'nodeid' => $nodeid)
			);
		}
		else
		{
			vB::getDbAssertor()->insert('widgetchannelconfig', array(
				'widgetinstanceid' => $widgetinstanceid,
				'nodeid'	=> $nodeid,
				'channelconfig' => serialize($configData)
			));
		}

		if ($this->preloadWidgetIds)
		{
			// Expires cache if we have preloaded widgets so that fetchChannelConfig will return updated data
			vB_Cache::instance(vB_Cache::CACHE_FAST)->event('widgetChannelConfigChg_' . $this->pagetemplateid . '_' . $this->sectionnumber . '_' . $nodeid);
		}

		return true;
	}

	// @todo
	// TODO: Remember to expires userconfig cache
	//public function saveUserConfig()
	//{}

	/**
	 * Saves the 'default' config for a widget; updates the widgetdefinitions default field
	 * currently only used for customized_copy widgets
	 *
	 * @param	int	widget id
	 * @param	array	config data for the widget
	 *
	 * @return	array
	 */
	public function saveDefaultConfig($widgetid, array $data)
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		$widgetid = intval($widgetid);

		if ($widgetid < 1)
		{
			throw vB_Exception_Api('Invalid widget ID');
		}


		// @TODO check admin perms


		$widget = $this->fetchWidget($widgetid);

		if ($widget['cloneable'] != '1')
		{
			// this may need to change if we want to use the method for purposes other
			// than manipulating cloned widgets
			throw vB_Exception_Api('Cannot modify the default configuration for non-cloneable widgets');
		}

		$configFields = $this->_getWidgetConfigFields($widgetid);
		$configData = array();
		if ($configFields)
		{
			foreach ($configFields AS $configField)
			{
				// @todo - THIS DATA NEEDS TO BE CLEANED AND VALIDATED!!
				$configData[$configField['name']] = $data[$configField['name']];
			}
		}
		else
		{
			throw vB_Exception_Api('Global (customized) widgets do not support arbitrary configuration data');
		}


		// @todo --- clean, validate, and sanitize $configData

		foreach ($configData AS $field => $value)
		{
			$options = array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				vB_dB_Query::CONDITIONS_KEY => array(
					'widgetid' => $widgetid,
					'name' => $field,
				),
				'defaultvalue' => $value,
			);
			$result = vB::getDbAssertor()->assertQuery('widgetdefinition', $options);
		}
		vB_Cache::instance()->event('widgetDefChg_' . $widgetid);
		return array(
			'widgetid' => $widgetid,
			'data' => $configData,
		);
	}

	/**
	 * Returns the basic widget data for a widget
	 *
	 * @param	int	Widget ID
	 *
	 * @return	array|false	The array of widget data, or false on failure
	 */
	public function fetchWidget($widgetid)
	{
		$widgets = $this->fetchWidgets(array($widgetid));
		if (is_array($widgets))
		{
			$widgets = array_pop($widgets);
		}
		return $widgets;
	}

	/**
	 * Returns the basic widget data for multiple widgets
	 *
	 * @param	array		(optional) Array of integer widget IDs, if you don't specify
	 * 				any widget ids, they will all be returned
	 *
	 * @return	array		The array of widget data, empty on failure
	 */
	public function fetchWidgets(array $widgetids = array())
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		$widgetids = array_map('intval', $widgetids);
		$widgetids = array_unique($widgetids);

		if (!empty($widgetids))
		{
			$conditions = array('widgetid' => $widgetids);
			//$conditions = array(
			//	array(
			//		'field' => 'widgetid',
			//		'value' => $widgetids,
			//	),
			//);
		}
		else
		{
			$conditions = array();
		}

		$widgets = vB::getDbAssertor()->getRows('widget', $conditions, 'title', 'widgetid');

		// @todo put this in the phrasing system
		$phrases = array(
			'uncategorized' => 'Uncategorized',
			'primary_content' => 'Primary Content',
		);

		foreach ($widgets AS &$widget)
		{
			//provide a default in case the phrase isn't correctly defined.
			if (isset($phrases[$widget['category']]))
			{
				$widget['category_title'] = $phrases[$widget['category']];
			}
			else
			{
				$widget['category_title'] = $widget['category'];
			}
		}

		return $widgets;
	}

	/**
	 * Returns  multiple widget instances
	 *
	 * @param	array		Array of integer widget instance IDs
	 *
	 * @return	array		The array of widget instance data, empty on failure
	 */
	public function fetchWidgetInstances(array $widgetInstanceIds)
	{
		$widgetInstanceIds = array_map('intval', $widgetInstanceIds);
		$widgetInstanceIds = array_unique($widgetInstanceIds);

		if (!empty($widgetInstanceIds))
		{
			$conditions = array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array(array(
					'field' => 'widgetinstanceid',
					'value' => $widgetInstanceIds,
				)),
			);
			$sortOrder = false;
			$widgetInstances_res = vB::getDbAssertor()->assertQuery('widgetinstance', $conditions, $sortOrder, 'widgetinstanceid');
			foreach ($widgetInstances_res as $widgetInstance)
			{
				$widgetInstances[] = $widgetInstance;
				vB_Cache::instance(vB_Cache::CACHE_FAST)->write('widgetInstance_' . $widgetInstance['widgetinstanceid'], $widgetInstance, false, array('widgetInstanceChg_' . $widgetInstance['widgetinstanceid']));
			}
		}
		else
		{
			$widgetInstances = array();
		}
		return $widgetInstances;
	}

	/**
	 * Returns  all widget instances that are associated with the
	 * given page template id.  These are the widget instances that should
	 * shown on that page template.
	 *
	 * @param	int		Page template id.
	 * @param	int		Section number. Sections start at 0. Use -1 to
	 * 				return all widget instances, specify section number
	 *				to only return the widget instances in that section.
	 * @param	int		Channel id. May have specific configuration for display and order of widgets
	 *
	 * @return	array		The array of widget instance data, empty on failure
	 */
	public function fetchWidgetInstancesByPageTemplateId($pagetemplateid, $sectionnumber = -1, $channelId = 0)
	{
		//@todo -- copied directly from scaffold-- this should be done with a JOIN and fewer queries.

		$this->pagetemplateid = intval($pagetemplateid);
		$this->sectionnumber = intval($sectionnumber);
		$userid = intval(vB::getCurrentSession()->get('userid'));

		$db = vB::getDbAssertor();

		$conditions = array(
			'pagetemplateid' => $this->pagetemplateid,
		);

		if ($this->sectionnumber >= 0)
		{
			// get widget instances from a specific section only
			$conditions['displaysection'] = $this->sectionnumber;
		}
		else
		{
			// get all widgets ($sectionnumber == -1)
		}

		$result = $db->assertQuery('widgetinstance', $conditions, array('parent', 'displaysection', 'displayorder'));

		$this->preloadWidgetIds = $widgetinstanceids = $widgetinstances = $widgetids = array();
		$widgetids[] = 0;
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		foreach ($result AS $widget)
		{
			$widgetids[] = $widget['widgetid'];
			$widgetinstanceids[] = $widget['widgetinstanceid'];
			$widgetinstances[] = $widget;
			if ($cache->read('widgetDefinition_' . $widget['widgetid']) === false)
			{
				$this->preloadWidgetIds[] = $widget['widgetid'];
			}
			vB_Cache::instance(vB_Cache::CACHE_FAST)->write('widgetInstance_' . $widget['widgetinstanceid'], $widget, false, array('widgetInstanceChg_' . $widget['widgetinstanceid']));
		}
		if (!empty($this->preloadWidgetIds))
		{
			$widgetdefinitions_res = $db->assertQuery('widgetdefinition', array('widgetid' => $this->preloadWidgetIds));
			foreach ($widgetdefinitions_res as $widgetdefinition)
			{
				$widgetdefinitions[$widgetdefinition['widgetid']][] = $widgetdefinition;
			}
			// there might be some widget that don't have configuration
			if (count($this->preloadWidgetIds) != count($widgetdefinitions))
			{
				foreach ($this->preloadWidgetIds as $preloadWidgetId)
				{
					// add those widgets as well so we don't query them again
					if (empty($widgetdefinitions[$preloadWidgetId]))
					{
						$widgetdefinitions[$preloadWidgetId] = array();
					}
				}
			}
			if (!empty($widgetdefinitions))
			{
				foreach ($widgetdefinitions as $widgetid => $definitions)
				{
					$cache->write('widgetDefinition_' . $widgetid, $definitions, false, array('widgetDefChg_' . $widgetid));
				}
			}
		}
		//let's pre-fetch the widget instances
		if (!empty($widgetinstanceids))
		{
			$this->fetchWidgetInstances($widgetinstanceids);
		}

		$widgetdata = $db->getRows('widget', array('widgetid' => $widgetids), false, 'widgetid');
		// preload and cache widget definitions
		// order by display order
		$widgets = $allWidgets = $configInfo = $sortAgain = array();

		foreach ($widgetinstances AS $widgetinstance)
		{
			$data = $widgetdata[$widgetinstance['widgetid']];
			$data['widgetinstanceid'] = $widgetinstance['widgetinstanceid'];
			$data['displaysection'] = $widgetinstance['displaysection'];

			$allWidgets[$data['widgetinstanceid']] = $data;

			if ($widgetinstance['parent'] > 0)
			{
				if (!isset($configInfo[$widgetinstance['parent']]))
				{
					$configInfo[$widgetinstance['parent']] = $this->fetchConfig($widgetinstance['parent'], $userid, $channelId);
					if (isset($configInfo[$widgetinstance['parent']]['display_order']) AND
						!empty($configInfo[$widgetinstance['parent']]['display_order']))
					{
						$sortAgain[] = $widgetinstance['parent'];
					}
				}

				if (isset($configInfo[$widgetinstance['parent']]['display_modules']) AND
					!empty($configInfo[$widgetinstance['parent']]['display_modules']))
				{
					$allWidgets[$data['widgetinstanceid']]['hidden'] = in_array($data['widgetinstanceid'], $configInfo[$widgetinstance['parent']]['display_modules']) ? 0 : 1;
				}
				else
				{
					$allWidgets[$data['widgetinstanceid']]['hidden'] = 0;
				}

				$allWidgets[$widgetinstance['parent']]['subModules'][$data['widgetinstanceid']] =& $allWidgets[$data['widgetinstanceid']];
			}
			else
			{
				$allWidgets[$data['widgetinstanceid']]['hidden'] = 0;
				$widgets[] =& $allWidgets[$data['widgetinstanceid']];
			}
		}

		// if there's an order in config, we need to resort submodules
		if (!empty($sortAgain))
		{
			foreach($sortAgain AS $parent)
			{
				$newOrder = array();
				if (!empty($configInfo[$parent]['display_order']))
				{
					foreach($configInfo[$parent]['display_order'] AS $widgetInstanceId)
					{
						$newOrder[$widgetInstanceId] = $allWidgets[$parent]['subModules'][$widgetInstanceId];
						unset($allWidgets[$parent]['subModules'][$widgetInstanceId]);
					}
				}

				// append any remaining item
				$newOrder += $allWidgets[$parent]['subModules'];
				$allWidgets[$parent]['subModules'] = $newOrder;
			}
		}


		return $widgets;
	}

	/**
	 * Returns  all widget instances that are associated with the
	 * given page template id in a hierarchical array indexed by section number.
	 * These are the widget instances that should shown on that page template.
	 *
	 * @param	int		Page template id.
	 * @param	int		Channel id (optional)
	 *
	 * @return	array		The array of sections with widget instance data, empty on failure
	 */
	public function fetchHierarchicalWidgetInstancesByPageTemplateId($pagetemplateid, $channelId = 0)
	{
		$widgetInstances = $this->fetchWidgetInstancesByPageTemplateId($pagetemplateid, -1, $channelId);
		$maxDisplaySection = 0;
		foreach ($widgetInstances as $widgetInstance)
		{
			$maxDisplaySection = (int) max($maxDisplaySection, $widgetInstance['displaysection']);
		}

		$widgets = array();
		for ($i = 0; $i <= $maxDisplaySection; ++$i)
		{
			$widgets[$i] = array();
		}

		foreach ($widgetInstances as $widgetInstance)
		{
			$displaySection = $widgetInstance['displaysection'];
			$widgets[$displaySection][] = $widgetInstance;
		}

		return $widgets;
	}

	/**
	 * Deletes a widget instance
	 *
	 * @param	int	Widget instance ID to delete
	 *
	 * @return	false|int	False or 0 on failure, 1 on success
	 */
	public function deleteWidgetInstance($widgetInstanceId)
	{
		return $this->deleteWidgetInstances(array(intval($widgetInstanceId)));
	}

	/**
	 * Deletes multiple widget instances
	 *
	 * @param	array	Widget instance IDs to delete
	 *
	 * @return	false|int	False or 0 on failure, number of rows deleted on success
	 */
	public function deleteWidgetInstances(array $widgetInstanceIds)
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		if (empty($widgetInstanceIds))
		{
			return false;
		}

		$widgetInstanceIds = array_map('intval', $widgetInstanceIds);

		$db = vB::getDbAssertor();

		// we may need to delete submodules as well
		$subModules = $db->getRows('widgetinstance', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::COLUMNS_KEY => array('widgetinstanceid'),
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'parent', 'value' => $widgetInstanceIds)
			)
		));
		if ($subModules)
		{
			$subModuleIds = array();
			foreach($subModules AS $module)
			{
				$subModuleIds[] = intval($module['widgetinstanceid']);
			}
			$this->deleteWidgetInstances($subModuleIds);
		}

		$db->delete('widgetinstance', array(array(
			'field' => 'widgetinstanceid',
			'value' => $widgetInstanceIds,
		)));

		return $db->affected_rows();
	}

	/**
	 * Saves (inserts/updates) one channel record. Used by
	 * {@see saveChannelWidgetConfig, saveChannels}
	 *
	 * @param	int	Channel Node ID, if available
	 * @param	array	Channel data (title, etc)
	 * @param	int	Page parent ID
	 *
	 * @return	int	Channel Node ID
	 */
	protected function saveChannel($nodeid, array $data)//, $page_parentid
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		$db = vB::getDbAssertor();
		// TODO: this interface is not available on core
		$channelApi = vB_Api::instanceInternal('content_channel');
		$nodeid = (int) $nodeid;
		$return_page_parentid = null;

		if ($nodeid > 0)
		{
			if (isset($data['switchCategory']))
			{
				$channelApi->switchForumCategory($data['switchCategory'], $nodeid);
			}

			// this call won't update parentid
			$channelApi->update($nodeid, $data);

			// check if we need to move the channel
			if ($data['parentid'] != $data['previousParentId'])
			{
				vB_Api::instanceInternal('node')->moveNodes($nodeid, $data['parentid']);
			}
		}
		else
		{
			if (isset($data['switchCategory']) AND $data['switchCategory'] > 0)
			{
				$data['category'] = $data['switchCategory'] ? 1 : 0;
				$data['options']['cancontainthreads'] = $data['switchCategory'] ? 0 : 1;
				unset($data['switchCategory']);
			}

			//Normally we want it to be published.
			if (!isset($data['publishdate']))
			{
				$data['publishdate'] = vB::getRequest()->getTimeNow();
			}

			$nodeid = $channelApi->add($data);
		}

		$channel = $db->getRow('vBForum:node', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array('nodeid'=>$nodeid)
		));

		return array(
				'nodeid' => $nodeid,
				'routeid' => $channel['routeid']
			);
	}

	/**
	 * Creates a page template record for a channel. Used by
	 * {@see saveChannelWidgetConfig, saveChannels}
	 *
	 * @param	int	Page template ID
	 * @param	int	Channel Node ID
	 *
	 * @return	int	New Page template ID
	 */
	protected function saveChannelPageTemplate($pagetemplateid, $nodeid)
	{
		/*
		// create page template for this channel
		mysql_query("
			INSERT INTO " . $config->db_prefix . "pagetemplate
			(screenlayoutid, title)
			SELECT p.screenlayoutid, 'Channel #$nodeid Page Template'
			FROM " . $config->db_prefix . "pagetemplate AS p
			WHERE p.pagetemplateid = $pagetemplateid
		");
		$newpagetemplateid = (int) mysql_insert_id($dblink);
		// copy widgets to new page template (except for widget in position 0, 0)
		mysql_query("
			INSERT INTO " . $config->db_prefix . "widgetinstance
			(pagetemplateid, widgetid, displaysection, displayorder, adminconfig)
			SELECT $newpagetemplateid, w.widgetid, w.displaysection, w.displayorder, w.adminconfig
			FROM " . $config->db_prefix . "widgetinstance AS w
			WHERE w.pagetemplateid = $pagetemplateid AND w.displaysection <> 0 AND w.displayorder <> 0
		");
		// copy widget from 0, 0 position in page template id #2 (the default channel page template)
		mysql_query("
			INSERT INTO " . $config->db_prefix . "widgetinstance
			(pagetemplateid, widgetid, displaysection, displayorder, adminconfig)
			SELECT $newpagetemplateid, w.widgetid, w.displaysection, w.displayorder, w.adminconfig
			FROM " . $config->db_prefix . "widgetinstance AS w
			WHERE w.pagetemplateid = 2 AND w.displaysection = 0 AND w.displayorder = 0
		");
		// @todo save the pagetemplateid in the channel table? so
		// it's accessible when viewing a channel in the channel controller
		//
		*/

	}

	/**
	 * Recursively saves all channels in the Channel Widget Used by
	 * {@see saveChannelWidgetConfig}
	 *
	 * @param	array	Channel data
	 * @param	int	Parent node ID (used by the recursive call only)
	 * @param
	 * @param	int	Page ID where the channels are being created
	 *
	 * @return	array	Channel Information
	 */
	protected function saveChannels($channels, $parentid = 1, &$channelIds)//, $pageid
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		if (empty($channels))
		{
			return array();
		}
		$existing_nodeids = array();
		foreach ($channels as $channel)
		{
			if (!empty($channel->nodeid))
			{
				$existing_nodeids[] = $channel->nodeid;
			}
		}
		$existingChannels = array();
		if (!empty($existing_nodeids))
		{
			$existingChannels = vB_Library::instance('content_channel')->getContent($existing_nodeids);
		}

		$channelsOut = array();

		foreach ($channels as $channel)
		{
			$channelData = array(
				'title' => $channel->title,
				'parentid' => $parentid,
				'previousParentId' => $channel->previousParentId,
				'displayorder' => $channel->displayorder
			);
			if (isset($channel->switchCategory))
			{
				$channelData['switchCategory'] = (bool)$channel->switchCategory;
			}

			if (
				!empty($channel->nodeid)
				AND !empty($existingChannels[$channel->nodeid])
				AND $channel->title == $existingChannels[$channel->nodeid]['title']
				AND $parentid == $existingChannels[$channel->nodeid]['parentid']
				AND $parentid == $channel->previousParentId
				AND $channel->displayorder == $existingChannels[$channel->nodeid]['displayorder']
				AND (!isset($channel->switchCategory) OR $channel->switchCategory == $existingChannels[$channel->nodeid]['category'])
			)
			{
				// no need to update this channel, nothing changed
				$nodeid = $channel->nodeid;
				$channelInfo = $existingChannels[$channel->nodeid];
			}
			else
			{
				$channelInfo = $this->saveChannel($channel->nodeid, $channelData);//, $pageid
				$nodeid = $channelInfo['nodeid'];
			}
			if (empty($channelInfo['routeid']))
			{
				//this can only happen if there is an invalid node record, which shouldn't happen but is bad.
				throw new vB_Exception_Content('invalid_route_contact_vbulletin_support');
			}

			$channelsOut[] = array(
				//'channelid' => $nodeid, // @todo - remove
				'nodeid' => $nodeid,
				//'parentchannelid' => $parentid, // @todo - remove
				'parentid' => $parentid,
				'title' => $channel->title,
				'subchannels' => $this->saveChannels($channel->subchannels, $nodeid, $channelIds),//, $channelInfo['page_parentid']
				'url' => vB5_Route::buildUrl($channelInfo['routeid'])
			);
			$channelIds[] = $nodeid;
		}

		return $channelsOut;
	}

	/**
	 * Returns the structure which was previously stored in the adminconfig field of widgetinstancetable
	 * @param int $rootChannelId
	 */
	public function fetchChannelWidgetAdminConfig($channelIds)
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		// get channels for which current user has access
		$nodes = vB_Api::instanceInternal('node')->getNodes($channelIds);

		return $this->assembleChannelConfig($nodes);
	}

	public function fetchPageManagerForums()
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		// TODO: this doesn't use pagination. If/When UI changes, use vBForum:getChannel instead
		$nodes = vB::getDbAssertor()->getRows('vBForum:getChannelWidgetInfo');

		$response = $this->assemblePageManagerChannelsConfig($nodes);

		$forums = array_shift($response['channel_hierarchy']['forum']);
		if (empty($forums))
		{
			return array();
		}

		return $forums['subchannels'];
	}

	public function fetchPageManagerGroups($channel = 'groups', $page = 1)
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		$page = max($page, 1);

		$perpage = vB::getDatastore()->getOption('maxposts');
		if (empty($perpage))
		{
			$perpage = 20;
		}
		$from = (($page - 1) * $perpage);
		$topChannelIds = vB_Api::instanceInternal('Content_Channel')->fetchTopLevelChannelIds();

		$result['nodes'] = vB::getDbAssertor()->getRows('vBForum:getTLChannelInfo', array('channelid' => $topChannelIds[$channel], 'from' => $from, 'perpage' => $perpage), false, 'nodeid');

		$total = count($result['nodes']);
		if ($page > 1 OR $total == $perpage)
		{
			$total = vB::getDbAssertor()->getField('vBForum:getTLChannelCount', array('channelid' => $topChannelIds[$channel]));
		}

		$result['paginationInfo'] = array(
				'startcount' => $from + 1,
				'endcount' => $from + count($result['nodes']),
				'totalcount' => $total,
				'currentpage' => $page,
				'page' => $page,
				'totalpages' => ceil($total / $perpage),
//				'name' => $name,
//				'tab' => $params['tab']
				//'queryParams' => $params['queryParams']
		);
		return $result;
	}

	protected function channelDisplaySort($ch1, $ch2)
	{
		if ($ch1['displayOrder'] == $ch2['displayOrder'])
		{
			if ($ch1['nodeid'] == $ch2['nodeid'])
			{
				return 0;
			}
			else if ($ch1['nodeid'] > $ch2['nodeid'])
			{
				return 1;
			}
			else
			{
				return -1;
			}
		}
		else if ($ch1['displayOrder'] > $ch2['displayOrder'])
		{
			return 1;
		}
		else
		{
			return -1;
		}
	}

	protected function assembleChannelConfig($nodes)
	{
		// build required variables
		$channels = $channelHierarchy = $channelNodeIds = $lastContentIds =  array();

		foreach ($nodes AS $node)
		{
			if (intval($node['lastcontentid']))
			{
				$lastContentIds[] = $node['lastcontentid'];
			}

			$channels[$node['nodeid']] = array(
				'nodeid' => intval($node['nodeid']),
				'routeid' => intval($node['routeid']),
				'title'	=> $node['title'],
				'parentid' => intval($node['parentid']),
				'isSubChannel' => false,
				'lastPostTitle' => '',
				'subchannels' => array(),
				'displayOrder' => intval($node['displayorder']),
				'category' => intval($node['category']),
			);
		}

		// preorder channels to follow display order
		uasort($channels, array($this, 'channelDisplaySort'));

		$lastContents = vB_Api::instanceInternal('node')->getNodes($lastContentIds);

		foreach ($channels as $channel)
		{
			$nodeId = $channel['nodeid'];
			$parentId = $channel['parentid'];
			$displayOrder = intval($channel['displayOrder']);

			if (!empty($nodes[$nodeId]['lastcontentid']) AND !empty($lastContents[$nodes[$nodeId]['lastcontentid']]))
			{
				$channels[$nodeId]['lastPostTitle'] = $lastContents[$nodes[$nodeId]['lastcontentid']]['htmltitle'];
				$channels[$nodeId]['lastPost'] = $lastContents[$nodes[$nodeId]['lastcontentid']];
			}

			if (isset($channels[$parentId]))
			{
				// assign by reference, so subchannels can be filled in later
				$channels[$nodeId]['isSubChannel'] = true;
				if ($displayOrder > 0)
				{
					$channels[$parentId]['subchannels']["$displayOrder.$nodeId"] =& $channels[$nodeId];
				}
				else
				{
					$channels[$parentId]['subchannels'][] =& $channels[$nodeId];
				}
			}
			else
			{
				// assign by reference, so subchannels can be filled in later
				if ($displayOrder > 0)
				{
					$channelHierarchy["$displayOrder.$nodeId"] =& $channels[$nodeId];
				}
				else
				{
					$channelHierarchy[] =& $channels[$nodeId];
				}
			}
		}

		$channelWidgetConfig = array(
			'channels'          => $channels,
			'channel_hierarchy' => $channelHierarchy,
			// this is used to update the channel url based on the page url
			'channel_node_ids'  => array_keys($channels),
		);

		return $channelWidgetConfig;
	}

	protected function assemblePageManagerChannelsConfig($nodes)
	{
		// build required variables
		$channels = $channelHierarchy = $channelNodeIds = $lastContentIds = array();
		$topChannelIds = vB_Api::instanceInternal('Content_Channel')->fetchTopLevelChannelIds();

		foreach ($nodes AS $node)
		{
			if (intval($node['lastcontentid']))
			{
				$lastContentIds[] = $node['lastcontentid'];
			}

			$channels[$node['nodeid']] = array(
					'nodeid' => intval($node['nodeid']),
					'routeid' => intval($node['routeid']),
					'title'	=> $node['title'],
					'parentid' => intval($node['parentid']),
					'isSubChannel' => false,
					'lastPostTitle' => '',
					'subchannels' => array(),
					'displayOrder' => intval($node['displayorder']),
					'category' => intval($node['category']),
			);
			if ($tl = array_search($node['nodeid'], $topChannelIds))
			{
				$channels[$node['nodeid']]['top_level'] = $tl;
			}
		}

		// preorder channels to follow display order
		uasort($channels, array($this, 'channelDisplaySort'));

		$lastContents = vB_Api::instanceInternal('node')->getNodes($lastContentIds);

		foreach ($channels AS $channel)
		{
			$nodeId = $channel['nodeid'];
			$parentId = $channel['parentid'];
			$displayOrder = intval($channel['displayOrder']);

			if (!empty($nodes[$nodeId]['lastcontentid']) AND !empty($lastContents[$nodes[$nodeId]['lastcontentid']]))
			{
				$channels[$nodeId]['lastPostTitle'] = $lastContents[$nodes[$nodeId]['lastcontentid']]['htmltitle'];
				$channels[$nodeId]['lastPost'] = $lastContents[$nodes[$nodeId]['lastcontentid']];
			}

			if (isset($channels[$parentId]))
			{
				// assign by reference, so subchannels can be filled in later
				$channels[$nodeId]['isSubChannel'] = true;
				if ($displayOrder > 0)
				{
					$channels[$parentId]['subchannels']["$displayOrder.$nodeId"] =& $channels[$nodeId];
				}
				else
				{
					$channels[$parentId]['subchannels'][] =& $channels[$nodeId];
				}

			}
			else
			{
				// assign by reference, so subchannels can be filled in later
				$index = $displayOrder > 0 ? $displayOrder : count($channelHierarchy);
				if (!empty($channel['top_level']))
				{
					$channelHierarchy[$channel['top_level']]["$index.$nodeId"] =& $channels[$nodeId];
				}
				else
				{
					$channelHierarchy["$index.$nodeId"] =& $channels[$nodeId];
				}
			}
		}

		$channelWidgetConfig = array(
				'channels'          => $channels,
				'channel_hierarchy' => $channelHierarchy,
				// this is used to update the channel url based on the page url
				'channel_node_ids'  => array_keys($channels),
		);

		return $channelWidgetConfig;
	}


	/**
	 * Saves the configuration for the Channel Widget, including creating/saving channels
	 * as necessary.
	 *
	 * @param	array	An array of channel hierarchy information
	 *
	 * @return	array	Array of information to display the channel widget config interface
	 */
	public function saveForums($data)
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		$forums = json_decode($data);//json_decode(array_shift($arguments));

		$channelNodeIds = array();
		$topChannelIds = vB_Api::instanceInternal('Content_Channel')->fetchTopLevelChannelIds();
		$channelsOut = $this->saveChannels($forums, $topChannelIds['forum'], $channelNodeIds);//, $pageid
		$output = array(
			'forums' => $channelsOut,
		);
		return $output;
	}

	/**
	 * Saves the configuration for the Channel Widget, including creating/saving channels
	 * as necessary.
	 *
	 * @param	array	An array of channel hierarchy information
	 *
	 * @return	array	Array of information to display the channel widget config interface
	 */
	public function saveChannelWidgetConfig($data)
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		// @todo: use the cleaner class to auto convert from json to array
		$input = json_decode($data);//json_decode(array_shift($arguments));
		$channels = $input->channels;
		$pageid = (int) $input->pageid;
//		$pagetemplateid = (int) $input->pagetemplateid;
//		$widgetid = (int) $input->widgetid;
//		$widgetinstanceid = (int) $input->widgetinstanceid;

		$db = vB::getDbAssertor();

		// return value and widget config values
		$channelNodeIds = array();
		$parentId = 1;
		$channelsOut = $this->saveChannels($channels, $parentId, $channelNodeIds, $pageid);

//		$channelWidgetConfig = array(
//			'channel_node_ids'	=> $channelNodeIds // this is used to update the channel url based on the page url
//		);
//
//		// save widget config
//		$widgetConfigSchema = $this->fetchConfigSchema($widgetid, $widgetinstanceid, $pagetemplateid, 'admin');
//
//		$widgetid = $widgetConfigSchema['widgetid'];
//		$widgetinstanceid = $widgetConfigSchema['widgetinstanceid'];
//		$pagetemplateid = $widgetConfigSchema['pagetemplateid'];
//
//		// if the pagetemplate was not created, this call will do it in order to save the widgetinstance
//		$res = $this->saveAdminConfig($widgetid, $pagetemplateid, $widgetinstanceid, $channelWidgetConfig);

		// send output
		$output = array(
//			'pagetemplateid' => $res['pagetemplateid'],
//			'widgetinstanceid' => $widgetinstanceid,
			'channels' => $channelsOut,
		);
		return $output;

	}

	/**
	 * Saves the configuration for the Search Widget,
	 *
	 * @param	array	An array of search information
	 *
	 * @return	string	search JSON string
	 */

	public function saveSearchWidgetConfig($data)
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		// @todo: use the cleaner class to auto convert from json to array
		$input = json_decode($data, true);//json_decode(array_shift($arguments));
		$pageid = (int) $input['pageid'];
		$pagetemplateid = empty($input['pagetemplateid']) ? 0 : (int) $input['pagetemplateid'];
		$widgetid = (int) $input['widgetid'];
		$widgetinstanceid = (int) $input['widgetinstanceid'];

		// save widget config
		$widgetConfigSchema = $this->fetchConfigSchema($widgetid, $widgetinstanceid, $pagetemplateid, 'admin');
		$widgetid = $widgetConfigSchema['widgetid'];
		$widgetinstanceid = $widgetConfigSchema['widgetinstanceid'];
		$pagetemplateid = $widgetConfigSchema['pagetemplateid'];

		if (empty($input['searchJSON']))
		{
			return array('error' => "empty_JSON");
		}
		$data = array(
			'searchJSON' => $input['searchJSON']
		);
		if (is_array($data['searchJSON']))
		{
			// If private messages are not explicitely requested, exclude them.
			// If an unauthorized user attempts to fetch pms, the results will not be displayed.
			$pmContenTypeId = vB_Types::instance()->getContentTypeId('vBForum_PrivateMessage');
			if (!(
					(isset($data['searchJSON']['contenttypeid']) AND (
						(is_array($data['searchJSON']['contenttypeid']) AND in_array($pmContenTypeId, $data['searchJSON']['contenttypeid'])) OR
						$data['searchJSON']['contenttypeid'] == $pmContenTypeId
					)) OR
					(isset($data['searchJSON']['type']) AND (
						(is_array($data['searchJSON']['type']) AND in_array('vBForum_PrivateMessage', $data['searchJSON']['type'])) OR
						$data['searchJSON']['type'] == 'vBForum_PrivateMessage'
					))
				))
			{
				if (!isset($data['searchJSON']['exclude_type']))
				{
					$data['searchJSON']['exclude_type'] = 'vBForum_PrivateMessage';
				}
				else if (is_string($data['searchJSON']['exclude_type']) AND $data['searchJSON']['exclude_type'] != 'vBForum_PrivateMessage')
				{
					$data['searchJSON']['exclude_type'] = array($data['searchJSON']['exclude_type'], 'vBForum_PrivateMessage');
				}
				elseif(is_array($data['searchJSON']['exclude_type']) AND !in_array('vBForum_PrivateMessage', $data['searchJSON']['exclude_type']))
				{
					$data['searchJSON']['exclude_type'][] = 'vBForum_PrivateMessage';
				}
			}

			$data['searchJSON'] = json_encode($data['searchJSON']);
		}
		if (!empty($input['resultsPerPage']))
		{
			$data['resultsPerPage'] = $input['resultsPerPage'];
		}

		if (!empty($input['searchTitle']))
		{
			$data['searchTitle'] = $input['searchTitle'];
		}

		$this->saveAdminConfig($widgetid, $pagetemplateid, $widgetinstanceid, $data);
		// send output
		$output = array(
			'widgetinstanceid' => $widgetinstanceid,
			'pagetemplateid' => $pagetemplateid,
			'searchJSON' => $input['searchJSON'],
		);
		return $output;
	}


	/**
	 * Clones a widget and returns the new widget info
	 *
	 * @param	int	Widget ID
	 * @param	array	Default data for the new widget
	 *
	 * @return	array
	 */
	public function cloneWidget($widgetId, $data = array())
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		$widgetId = (int) $widgetId;

		if ($widgetId < 1)
		{
			throw new vB_Exception_Api('Invalid widget ID');
		}


		// @TODO: Check admin permissions here



		$db = vB::getDbAssertor();

		// copy widget record
		$widget = $db->getRow('widget', array('widgetid' => $widgetId));
		if (!$widget['cloneable'])
		{
			throw new vB_Exception_Api('Widget type not cloneable');
		}
		unset($widget['widgetid']);
		$widget['title'] = empty($data['title']) ? ('Copy of ' . $widget['title']) : $data['title'];
		$widget['category'] = empty($data['category']) ? 'customized_copy' : $data['category'];
		$newWidgetId = $db->insert('widget', $widget);
		if (is_array($newWidgetId))
		{
			$newWidgetId = array_pop($newWidgetId);
		}
		$newWidgetId = (int) $newWidgetId;
		$widget['widgetid'] = $newWidgetId;

		// copy the widget configuration definitions if there are any
		$widgetDefinitions = $this->getWidgetDefinition($widgetId);
		if (!empty($widgetDefinitions))
		{
			foreach ($widgetDefinitions AS &$widgetDefinition)
			{
				$widgetDefinition['widgetid'] = $newWidgetId;
			}
			$db->insertMultiple('widgetdefinition', array_keys($widgetDefinitions[0]), $widgetDefinitions);
		}

		return array('widget' => $widget);
	}

	/**
	 * Rename custom widget
	 *
	 * @param $widgetId
	 * @param $newname
	 */
	public function renameWidget($widgetId, $newtitle)
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		$widgetId = (int) $widgetId;

		if ($widgetId < 1)
		{
			throw new vB_Exception_Api('invalid_widget_id');
		}

		if (empty($newtitle))
		{
			throw new vB_Exception_Api('invalid_new_widget_title');
		}

		// @TODO: Check admin permissions here

		$db = vB::getDbAssertor();

		$widget = $db->getRow('widget', array('widgetid' => $widgetId));
		if ($widget['category'] != 'customized_copy')
		{
			throw new vB_Exception_Api('widget_cannot_rename');
		}

		$db->assertQuery('widget', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'title' => $newtitle,
			vB_dB_Query::CONDITIONS_KEY => array(
				'widgetid' => $widgetId,
			)
		));

		return true;
	}

	/**
	 * Generates a new page template ID for the new page template that
	 * that widgets are being configured for. Needed to be able to
	 * generate a widget instance ID for the new widget instance.
	 *
	 * @return	int	New page template ID
	 */
	protected function _getNewPageTemplateId()
	{
		$result = vB::getDbAssertor()->assertQuery(
			'pagetemplate',
			array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'title' => '',
			)
		);

		if (is_array($result))
		{
			$result = array_pop($result);
		}

		return $result;
	}

	/**
	 * Generates a new widget instance ID for the widget instance
	 * being configured.
	 *
	 * @param	int	Widget ID - The new widget instance is an instance of this widget
	 * @param	int	Page template ID - The new widget instance will be on this page template
	 *
	 * @return	int	New widget instance ID
	 */
	protected function _getNewWidgetInstanceId($widgetid, $pagetemplateid)
	{
		$result = vB::getDbAssertor()->assertQuery(
			'widgetinstance',
			array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'pagetemplateid' => $pagetemplateid,
				'widgetid' => $widgetid,
			)
		);

		if (is_array($result))
		{
			$result = array_pop($result);
		}

		return $result;
	}

	/**
	 * Returns stored widget instance data for the given widget instance ID
	 *
	 * @param	int	Widget instance ID
	 *
	 * @return	array	Array of widget instance data
	 */
	protected function _getWidgetInstance($widgetinstanceid)
	{
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$cachedInstance = $cache->read('widgetInstance_' . $widgetinstanceid);
		if ($cachedInstance !== false)
		{
			return $cachedInstance;
		}

		$widgetinstance = vB::getDbAssertor()->getRow('widgetinstance',array('widgetinstanceid' => $widgetinstanceid));
		$cache->write('widgetInstance_' . $widgetinstanceid, $widgetinstance, false, array('widgetInstanceChg_' . $widgetinstanceid));
		return $widgetinstance;
	}

	/**
	 * Returns the configuration fields needed to configure a widget of this type.
	 * If the widget instance ID is given, it will also set the current values for
	 * the config fields to the current configured values for the widget instance.
	 *
	 * @param	int	The widget ID
	 * @param	int	The widget instance ID that is to be configured (optional)
	 * @param	string	The config type ("user" or "admin"), used if widget instance ID is given (optional)
	 * @param	int	The user ID, used if the config type is "user" (optional)
	 *
	 * @return 	array	An associative array, keyed by the config field name and containing
	 *			name, label, type, default value, is editable, and is required
	 * 			with which the config fields can be displayed.
	 */
	protected function _getWidgetConfigFields($widgetid, $widgetinstanceid = 0, $configtype = '', $userid = 0)
	{
		$configFields = $this->_getWidgetDefinition($widgetid);

		// get current widget config
		$userid = intval($userid);
		if ($widgetinstanceid > 0)
		{
			if ($configtype == 'user' AND $userid > 0)
			{
				$widgetConfig = $this->fetchUserConfig($widgetinstanceid, $userid);
			}
			else if ($configtype == 'admin')
			{
				$widgetConfig = $this->fetchAdminConfig($widgetinstanceid);
			}
			else
			{
				// @todo Throw an API widget exception here
				throw new Exception('Must specify valid config type. If config type is "user", a valid userid must be given.');
			}

			// if there is no user/admin config for this widget instance,
			// $widgetConfig will be false
			if (is_array($widgetConfig))
			{
				foreach ($widgetConfig AS $k => $v)
				{
					$configFields[$k]['defaultvalue'] = $v;
				}
			}
		}

		return $configFields;
	}

	/**
	 * fetches the rows from the widgetdefinition table for a widgetid
	 * @param int $widgetid
	 * @return array
	 */
	public function getWidgetDefinition($widgetid)
	{
		$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
		$cachedDefinitions = $cache->read('widgetDefinition_' . $widgetid);
		if ($cachedDefinitions !== false)
		{
			return $cachedDefinitions;
		}
		$definitions = vB::getDbAssertor()->getRows('widgetdefinition',array('widgetid' => $widgetid));
		$cache->write('widgetDefinition_' . $widgetid, $definitions, false, array('widgetDefChg_' . $widgetid));
		return $definitions;
	}


	/**
	 * Returns the config fields that define a widget
	 *
	 * @param	int	The widget ID
	 *
	 * @return 	array	The config fields
	 */
	protected function _getWidgetDefinition($widgetid)
	{
		$configFields = array();
		$fields = $this->getWidgetDefinition($widgetid);
		usort($fields, array($this,'_cmpWigetDefFields'));

		foreach ($fields as $field)
		{
			if (is_array($field) AND !empty($field))
			{
				if (($data = @unserialize($field['data'])) === false)
				{
					$data = $field['data'];
				}
				$configFields[$field['name']] = array(
					'name' => $field['name'],
					'label' => $field['label'],
					'type' => $field['field'],
					'defaultvalue' => $field['defaultvalue'],
					'isEditable' => $field['isusereditable'],
					'isRequired' => $field['isrequired'],
					'data' => $data,
				);
			}
		}

		return $configFields;
	}

	/**
	 * compare function for widget definition sorting
	 */
	protected function _cmpWigetDefFields($f1, $f2)
	{
		if ($f1['displayorder'] == $f2['displayorder']) {
			return 0;
		}
		return ($f1['displayorder'] < $f2['displayorder']) ? -1 : 1;
	}
	/**
	 * Writes debugging output to the filesystem for AJAX calls
	 *
	 * @param	mixed	Output to write
	 */
	protected function _writeDebugOutput($output)
	{
		$fname = dirname(__FILE__) . '/_debug_output.txt';
		file_put_contents($fname, $output);
	}

	/**
	 * Returns widget & widget definition data (e.g. for xml export)
	 *
	 * @return	array	WHERE array
	 *
	 * @return	array	array of widgets
	 */
	public function getWidgetList($where = array())
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		$widgets = vB::getDbAssertor()->getRows('widget', $where, array('widgetid'), 'widgetid');
		$widgetdefs = vB::getDbAssertor()->getRows('widgetdefinition', $where, array('widgetid'));

		if ($widgetdefs)
		{
			foreach ($widgetdefs AS $widgetdef)
			{
				$widgets[$widgetdef['widgetid']]['definitions'][] = $widgetdef;
			}
		}

		unset($widgetdefs);

		return $widgets;
	}

	public function fetchWidgetInstanceTemplates($modules)
	{
		$result = array();

		if (is_array($modules) AND !empty($modules))
		{
			array_walk($modules, 'intval');

			$result = vB::getDbAssertor()->getRows('getWidgetTemplates', array('modules' => $modules));
		}

		return $result;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
