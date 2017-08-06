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
 * vB_Api_PageTemplate
 *
 * @package vBApi
 * @access public
 */
class vB_Api_PageTemplate extends vB_Api
{

	protected function __construct()
	{
		parent::__construct();
	}

	/**
	 * Returns a list of all page templates and widget instances associated with them.
	 *
	 * @return	array
	 */
	public function fetchPageTemplateList()
	{
		$db = vB::getDbAssertor();

		//$pageTemplates = $db->getRows('pagetemplate', array(), 'title', 'pagetemplateid');

		// get all page templates that are not in the process of being created
		$result = $db->assertQuery('fetch_page_template_list', array(), 'title');
		$pageTemplates = array();
		foreach ($result as $row)
		{
			$pageTemplates[$row['pagetemplateid']] = $row;
		}

		foreach ($pageTemplates AS $k => $v)
		{
			$pageTemplates[$k]['widgetinstances'] = array();
		}

		$widgets = $db->getRows('widget', array(), '', 'widgetid');

		$widgetInstances = $db->getRows('widgetinstance', array(), array('displaysection', 'displayorder'));
		foreach ($widgetInstances AS $widgetInstance)
		{
			$pageTemplateId = $widgetInstance['pagetemplateid'];
			if (isset($pageTemplates[$pageTemplateId]))
			{
				$widgetInstance['title'] = (empty($widgetInstance['widgetid']) OR empty($widgets[$widgetInstance['widgetid']])) ? '' :$widgets[$widgetInstance['widgetid']]['title'];
				$pageTemplates[$pageTemplateId]['widgetinstances'][] = $widgetInstance;
			}
		}

		// remove the page template ID as the array indices
		return array_values($pageTemplates);
	}

	/**
	 * Returns the Page Template record based on the passed id.
	 *
	 * @param	int	Page template id
	 *
	 * @return	array	Page template information
	 */
	public function fetchPageTemplateById($pagetemplateid)
	{
		$pagetemplateid = intval($pagetemplateid);

		$db = vB::getDbAssertor();

		$conditions = array(
			'pagetemplateid' => $pagetemplateid,
		);
		$result = $db->getRow('pagetemplate', $conditions);

		return $result;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
