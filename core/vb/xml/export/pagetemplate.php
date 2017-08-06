<?php

/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 5.0.0
  || # ---------------------------------------------------------------- # ||
  || # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

class vB_Xml_Export_PageTemplate extends vB_Xml_Export
{
	protected function getXml()
	{
		$xml = new vB_XML_Builder();
		$xml->add_group('pagetemplates');
		
		$pageTemplateTable = $this->db->fetchTableStructure('pagetemplate');
		$pageTemplateTableColumns = array_diff($pageTemplateTable['structure'], array('guid', $pageTemplateTable['key']));
		
		$widgetInstanceTable = $this->db->fetchTableStructure('widgetinstance');
		$widgetInstanceTableColumns = array_diff($widgetInstanceTable['structure'], array('guid', 'widgetid', 'pagetemplateid', $widgetInstanceTable['key']));
		
		$pageTemplates = $this->db->getRows('pagetemplate');
		foreach ($pageTemplates AS $pageTemplate)
		{
			$info[$pageTemplate[$pageTemplateTable['key']]] = $pageTemplate;
		}
		
		// fetch widget instances
		$widgetsInfo = array();
		$widgetInstances = $this->db->getRows('widgetinstance', array('pagetemplateid' => array_keys($info)));
		foreach($widgetInstances AS $widgetInstance)
		{
			$info[$widgetInstance['pagetemplateid']]['widgets'][$widgetInstance['widgetinstanceid']] = $widgetInstance;
			$widgetsInfo[$widgetInstance['widgetid']] = '';
		}
		
		// fetch widget titles
		if (!empty($widgetsInfo))
		{
			$widgets = $this->db->getRows('widget', array('widgetid' => array_keys($widgetsInfo)));
			foreach($widgets AS $widget)
			{
				$widgetsInfo[$widget['widgetid']] = $widget['guid'];
			}
		}
		
		// create XML
		foreach ($info AS $pageTemplate)
		{
			$xml->add_group('pagetemplate', array('guid' => $pageTemplate['guid']));
			
			// adding pagetemplate elements
			foreach ($pageTemplateTableColumns AS $column)
			{
				if ($pageTemplate[$column] != NULL)
				{
					$xml->add_tag($column, $pageTemplate[$column]);
				}
			}
			
			// adding widgetinstances
			if (isset($pageTemplate['widgets']) AND !empty($pageTemplate['widgets']))
			{
				$xml->add_group('widgets');
				
				foreach ($pageTemplate['widgets'] AS $widgetInstance)
				{
					$xml->add_group('widgetinstance');
					
					foreach($widgetInstanceTableColumns AS $column)
					{
						$xml->add_tag($column, $widgetInstance[$column]);
					}
					// add widget title
					$xml->add_tag('widgetguid', $widgetsInfo[$widgetInstance['widgetid']]);
					
					$xml->close_group();
				}
				
				$xml->close_group();
			}
			
			$xml->close_group();
		}
		
		$xml->close_group();
		
		return $xml->fetch_xml();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/