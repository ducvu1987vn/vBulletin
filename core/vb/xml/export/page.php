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

class vB_Xml_Export_Page extends vB_Xml_Export
{
	protected function getXml()
	{
		$xml = new vB_XML_Builder();
		$xml->add_group('pages');
		
		$pageTable = $this->db->fetchTableStructure('page');
		$pageTableColumns = array_diff($pageTable['structure'], array('guid', 'routeid', 'pagetemplateid', 'parentid', $pageTable['key']));
		
		$pages = $this->db->getRows('getPageInfoExport', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));
		foreach ($pages AS $page)
		{
			$xml->add_group('page', array('guid' => $page['guid']));
			foreach ($pageTableColumns AS $column)
			{
				if ($page[$column] != NULL)
				{
					$xml->add_tag($column, $page[$column]);
				}
			}
			$xml->add_tag('parentGuid', $page['parentGuid']);
			$xml->add_tag('pageTemplateGuid', $page['pageTemplateGuid']);
			$xml->add_tag('routeGuid', $page['routeGuid']);
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