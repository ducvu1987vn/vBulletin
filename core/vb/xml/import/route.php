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

class vB_Xml_Import_Route extends vB_Xml_Import
{
	public function import($filepath)
	{
		$this->parsedXML = vB_Xml_Import::parseFile($filepath);

		// get all columns but the key
		$routeTable = $this->db->fetchTableStructure('routenew');
		$routeTableColumns = array_diff($routeTable['structure'], array('arguments', 'contentid', $routeTable['key']));

		$routes = $this->parsedXML['route'];

		foreach ($routes AS $route)
		{
			$values = array();
			foreach($routeTableColumns AS $col)
			{
				if (isset($route[$col]))
				{
					$values[$col] = $route[$col];
				}
				if (!isset($route['class']))
				{
					$values['class'] = '';
				}
			}
			$class = (isset($route['class']) AND !empty($route['class']) AND class_exists($route['class'])) ? $route['class'] : vB5_Route::DEFAULT_CLASS;
			$values['arguments'] = call_user_func_array(array($class, 'importArguments'), array($route['arguments']));
			$values['contentid'] = call_user_func_array(array($class, 'importContentId'), array(unserialize($values['arguments'])));
			$routeid = 0;
			$condition = array('guid' => $route['guid']);
			$existing = $this->db->getRow('routenew', $condition);

			if ($existing AND !empty($existing['routeid']))
			{
				$routeid = $existing['routeid'];
				if (($this->options & self::OPTION_OVERWRITE))
				{
					//update the existing record
					$values['routeid'] = $routeid;
					$values[vB_dB_Query::TYPE_KEY] = vB_dB_Query::QUERY_UPDATE;
					unset($values['guid']);
					$this->db->assertQuery('routenew', $values);
				}
			}
			else
			{
				$routeid = $this->db->insertIgnore('routenew', $values);

				if (is_array($routeid))
				{
					$routeid = array_pop($routeid);
				}
			}

			vB_Xml_Import::setImportedId(vB_Xml_Import::TYPE_ROUTE, $route['guid'], $routeid);
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
