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

class vB_Xml_Export_Route extends vB_Xml_Export
{
	protected function getXml()
	{
		$xml = new vB_XML_Builder();
		$xml->add_group('routes');
		
		$routeTable = $this->db->fetchTableStructure('routenew');
		$routeTableColumns = array_diff($routeTable['structure'], array('guid', 'contentid', $routeTable['key']));
		
		$routes = $this->db->getRows('routenew');
		foreach ($routes AS $route)
		{
			$routeClass = (isset($route['class']) AND !empty($route['class']) AND class_exists($route['class'])) ? $route['class'] : vB5_Route::DEFAULT_CLASS;
			$route['arguments'] = call_user_func(array($routeClass, 'exportArguments'), $route['arguments']);
			
			$xml->add_group('route', array('guid' => $route['guid']));
			foreach ($routeTableColumns AS $column)
			{
				if ($route[$column] != NULL)
				{
					$xml->add_tag($column, $route[$column]);
				}
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