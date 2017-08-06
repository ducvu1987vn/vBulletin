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

class vB_Xml_Export_Channel extends vB_Xml_Export
{
	protected $nonExportableFields = array('guid', 'routeid', 'contenttypeid', 'userid', 'parentid', 'lastcontent', 'lastcontentid', 'lastauthorid');
	
	protected function getXml()
	{
		$xml = new vB_XML_Builder();
		$xml->add_group('channels');
		
		$channelTable = $this->db->fetchTableStructure('vbforum:channel');
		$channelTableColumns = array_diff($channelTable['structure'], array('guid', $channelTable['key']));
		
		$nodeTable = $this->db->fetchTableStructure('vbforum:node');
		$nodeTableColumns = array_diff($nodeTable['structure'], array($nodeTable['key']), $this->nonExportableFields);
		
		$channels = $this->db->getRows('vbforum:getChannelInfoExport', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));
		foreach ($channels AS $channel)
		{
			$xml->add_group('channel', array('guid' => $channel['guid']));
			foreach ($channelTableColumns AS $column)
			{
				if ($channel[$column] != NULL)
				{
					$xml->add_tag($column, $channel[$column]);
				}
			}
			$xml->add_group('node');
			foreach ($nodeTableColumns as $column)
			{
				if ($channel[$column] != NULL)
				{
					$xml->add_tag($column, $channel[$column]);
				}
			}
			$xml->add_tag('routeguid', $channel['routeguid']);
			$xml->add_tag('parentguid', $channel['parentguid']);
			$xml->close_group();
			
			$xml->close_group();
		}
		
		$xml->close_group();
		
		return $xml->fetch_xml();
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/