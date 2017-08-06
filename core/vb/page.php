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

class vB_Page
{
	/**
	 * Used for specific pages
	 */
	const TYPE_CUSTOM = 'custom';
	/**
	 * Used for generic pages such as default conversation page
	 */
	const TYPE_DEFAULT = 'default';

	const PAGE_BLOG = 'vbulletin-4ecbdac82f2c27.60323366';
	const PAGE_SOCIALGROUP = 'vbulletin-4ecbdac93742a5.43676037';
	const PAGE_HOME = 'vbulletin-4ecbdac82ef5d4.12817784';
	const PAGE_ONLINE = 'vbulletin-4ecbdac82f07a5.18983925';
	const PAGE_SEARCH = 'vbulletin-4ecbdac82efb61.17736147';
	const PAGE_SEARCHRESULT = 'vbulletin-4ecbdac82f2815.04471586';

	const TEMPLATE_CHANNEL		= 'vbulletin-4ecbdac9371313.62302700';
	const TEMPLATE_CATEGORY		= 'vbulletin-4ecbdac9371313.62302701';
	const TEMPLATE_CONVERSATION = 'vbulletin-4ecbdac93716c4.69967191';
	const TEMPLATE_BLOG			= 'vbulletin-4ecbdac93742a5.43676030';
	const TEMPLATE_SOCIALGROUP	= 'vbulletin-sgroups93742a5.43676038';
	const TEMPLATE_SOCIALGROUP_CATEGORY = 'vbulletin-sgcatlist93742a5.43676040';
	const TEMPLATE_BLOGCONVERSATION			= 'vbulletin-4ecbdac93716c4.69967191';
	const TEMPLATE_SOCIALGROUPCONVERSATION	= 'vbulletin-sgtopic93742a5.43676039';
	/**
	 * Clones a page template with its widgets and returns the new page template id.
	 * @param int $pageTemplateId
	 * @return int
	 */
    public static function clonePageTemplate($pageTemplateId)
	{
		if (!$templatePage = vB_Api::instanceInternal('pagetemplate')->fetchPageTemplateById($pageTemplateId))
		{
			throw new Exception('Cannot find pagetemplate');
		}

		$db = vB::getDbAssertor();

		// clone page template
		$newTemplateId = $db->insert('pagetemplate', array(
			'title'	=> 'Clone of ' . $templatePage['title'],
			'screenlayoutid' => $templatePage['screenlayoutid'],
			'guid' => vB_Xml_Export_PageTemplate::createGUID($templatePage)
		));
		if (is_array($newTemplateId))
		{
			$newTemplateId = (int) array_pop($newTemplateId);
		}

		// clone widgets
		$widgets = $db->getRows('widgetinstance', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array('pagetemplateid'=>$pageTemplateId)
		));
		foreach ($widgets AS $widget)
		{
			unset($widget['widgetinstanceid']);
			$widget['pagetemplateid'] = $newTemplateId;
			$db->insert('widgetinstance', $widget);
		}

		return $newTemplateId;
	}

	/** Gets the page template for display of blog channels
    *
    *   @return		integer
    */
	public static function getChannelPageTemplate()
	{
		// use default pagetemplate for forum channels
		$pageTemplateId = vB::getDbAssertor()->getField('pagetemplate', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array('guid' => vB_Page::TEMPLATE_CHANNEL)
		));

		return $pageTemplateId;
	}

    /** Gets the page template for display of blog topics/conversations
    *
    *   @return		integer
    */
	public static function getConversPageTemplate()
	{
		// use default pagetemplate for forum conversations
		$pageTemplateId = vB::getDbAssertor()->getField('pagetemplate', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array('guid' => vB_Page::TEMPLATE_CONVERSATION)
		));

		return $pageTemplateId;
	}

	/** Gets the page template for display of blog channels
    *
    *   @return		integer
    */
	public static function getCategoryChannelPageTemplate()
	{
		// use default pagetemplate for forum categories
		$pageTemplateId = vB::getDbAssertor()->getField('pagetemplate', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array('guid' => vB_Page::TEMPLATE_CATEGORY)
		));

		return $pageTemplateId;
	}

	public static function getBlogPageTemplates()
	{
		$result = array();

		// TODO: is there any special condition to be a blog page template?
		$pagetemplates = vB::getDbAssertor()->assertQuery('pagetemplate', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT));

		foreach ($pagetemplates AS $pagetemplate)
		{
			if ($pagetemplate['guid'] == self::TEMPLATE_BLOG)
			{
				$result = array_merge(array($pagetemplate['pagetemplateid'] => $pagetemplate['title']), $result);
			}
			else
			{
				$result[$pagetemplate['pagetemplateid']] = $pagetemplate['title'];
			}
		}

		return $result;
	}

    /** Gets the page template for display of blog channels
    *
    *   @return		integer
    */
	public static function getBlogChannelPageTemplate()
	{
		$options = vB::getDatastore()->getValue('options');

		if (isset($options['blog_pagetemplate']) AND !empty($options['blog_pagetemplate']))
		{
			$pageTemplateId = $options['blog_pagetemplate'];
		}
		else
		{
			// use default pagetemplate for blogs
			$pageTemplateId = vB::getDbAssertor()->getField('pagetemplate', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array('guid' => vB_Page::TEMPLATE_BLOG)
			));
		}

		return $pageTemplateId;
	}

    /** Gets the page template for display of blog topics/conversations
    *
    *   @return		integer
    */
	public static function getBlogConversPageTemplate()
	{
		$options = vB::getDatastore()->getValue('options');

		if (isset($options['blog_pagetemplate']) AND !empty($options['blog_pagetemplate']))
		{
			$pageTemplateId = $options['blog_pagetemplate'];
		}
		else
		{
			// use default pagetemplate for blogs
			$pageTemplateId = vB::getDbAssertor()->getField('pagetemplate', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array('guid' => vB_Page::TEMPLATE_BLOGCONVERSATION)
			));
		}

		return $pageTemplateId;
	}

    /** Gets the page template for display of social group channels
    *
    *   @return		integer
    */
	public static function getSGChannelPageTemplate()
	{
		$options = vB::getDatastore()->getValue('options');

		if (isset($options['sg_pagetemplate']) AND !empty($options['sg_pagetemplate']))
		{
			$pageTemplateId = $options['sg_pagetemplate'];
		}
		else
		{
			// use default pagetemplate for blogs
			$pageTemplateId = vB::getDbAssertor()->getField('pagetemplate', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array('guid' => vB_Page::TEMPLATE_SOCIALGROUP)
			));
		}

		return $pageTemplateId;
	}

    /** Gets the page template for display of social group  topics/conversations
    *
    *   @return		integer
    */
	public static function getSGConversPageTemplate()
	{
		$options = vB::getDatastore()->getValue('options');

		if (isset($options['sg_pagetemplate']) AND !empty($options['sg_pagetemplate']))
		{
			$pageTemplateId = $options['sg_pagetemplate'];
		}
		else
		{
			// use default pagetemplate for blogs
			$pageTemplateId = vB::getDbAssertor()->getField('pagetemplate', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array('guid' => vB_Page::TEMPLATE_SOCIALGROUPCONVERSATION)
			));
		}

		return $pageTemplateId;
	}

	/** Gets the page template for display of social group categories
    *
    *   @return		integer
    */
	public static function getSGCategoryPageTemplate()
	{
		$options = vB::getDatastore()->getValue('options');

		if (isset($options['sg_category_pagetemplate']) AND !empty($options['sg_category_pagetemplate']))
		{
			$pageTemplateId = $options['sg_category_pagetemplate'];
		}
		else
		{
			// use default pagetemplate for blogs
			$pageTemplateId = vB::getDbAssertor()->getField('pagetemplate', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array('guid' => vB_Page::TEMPLATE_SOCIALGROUP_CATEGORY)
			));
		}

		return $pageTemplateId;
	}

    /** Gets the page template for display of social group  topics/conversations
    *
    *   @return		integer
    */
	public static function getSGCategoryConversPageTemplate()
	{
		$options = vB::getDatastore()->getValue('options');

		if (isset($options['sg_category_pagetemplate']) AND !empty($options['sg_category_pagetemplate']))
		{
			$pageTemplateId = $options['sg_category_pagetemplate'];
		}
		else
		{
			// use default pagetemplate for blogs
			$pageTemplateId = vB::getDbAssertor()->getField('pagetemplate', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array('guid' => vB_Page::TEMPLATE_CONVERSATION)
			));
		}

		return $pageTemplateId;
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/