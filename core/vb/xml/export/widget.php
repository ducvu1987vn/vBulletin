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

class vB_Xml_Export_Widget extends vB_Xml_Export
{
	public static function createGUID($record, $source = 'vbulletin')
	{
		return vB_GUID::get("$source-{$record['template']}-");
	}
	
	protected function getXml()
	{
		$xml = new vB_XML_Builder();
		$xml->add_group('widgets', array('product' => 'vbulletin'));
		
		$widgetTable = $this->db->fetchTableStructure('widget');
		$widgetTableColumns = array_diff($widgetTable['structure'], array('guid', $widgetTable['key']));
		
		$widgets = $this->db->getRows('widget');
		foreach ($widgets AS $widget)
		{
			$widgetInfo[$widget[$widgetTable['key']]] = $widget;
		}
		
		$widgetDefinitions = $this->db->getRows('widgetdefinition', array('widgetid' => array_keys($widgetInfo)));
		if (!empty($widgetDefinitions))
		{
			$definitionTable = $this->db->fetchTableStructure('widgetdefinition');
			$definitionTableColumns = array_diff($definitionTable['structure'], array('guid', $widgetTable['key'], $definitionTable['key']));

			foreach($widgetDefinitions AS $widgetDefinition)
			{
				$widgetInfo[$widgetDefinition[$widgetTable['key']]]['definitions'][] = $widgetDefinition;
			}
		}
		
		foreach ($widgetInfo AS $widget)
		{
			$xml->add_group('widget', array('guid' => $widget['guid']));
			foreach ($widgetTableColumns AS $column)
			{
				if ($widget[$column] != NULL)
				{
					$xml->add_tag($column, $widget[$column]);
				}
			}
			
			if (isset($widget['definitions']) AND !empty($widget['definitions']))
			{
				$xml->add_group('definitions');

				foreach ($widget['definitions'] AS $definition)
				{
					$xml->add_group('definition');
					foreach($definitionTableColumns AS $column)
					{
						if ($definition[$column] != NULL)
						{
							$xml->add_tag($column, $definition[$column]);
						}
					}
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