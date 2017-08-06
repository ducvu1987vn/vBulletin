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

class vB_Xml_Import_Page extends vB_Xml_Import
{
	/**
	 * Widgets referenced by instances in the imported template
	 * @var array 
	 */
	protected $referencedTemplates;
	
	/**
	 * Checks if all referenced widgets are already defined
	 * Also sets referencedWidgets class attribute to be used while importing
	 */
	protected function checkTemplates()
	{
		$requiredTemplates = array();
		
		foreach ($this->parsedXML['page'] AS $page)
		{
			$requiredTemplates[] = $page['pageTemplateGuid'];
		}
		
		$existingPageTemplates = $this->db->getRows('pagetemplate', array('guid' => $requiredTemplates));
		foreach ($existingPageTemplates AS $pagetemplate)
		{
			$this->referencedTemplates[$pagetemplate['guid']] = $pagetemplate;
		}
		
		$missingTemplates = array_diff($requiredTemplates, array_keys($this->referencedTemplates));
		if (!empty($missingTemplates))
		{
			throw new Exception('Reference to undefined template(s): ' . implode(' ', $missingTemplates));
		}
	}
	
	public function import($filepath)
	{
		$this->parsedXML = vB_Xml_Import::parseFile($filepath);
		
		$this->checkTemplates();
		
		// get all columns but the key
		$pageTable = $this->db->fetchTableStructure('page');
		$pageTableColumns = array_diff($pageTable['structure'], array($pageTable['key']));
		
		$pages = is_array($this->parsedXML['page'][0]) ? $this->parsedXML['page'] : array($this->parsedXML['page']);
		
		foreach ($pages AS $page)
		{
			$values = array();
			foreach($pageTableColumns AS $col)
			{
				if (isset($page[$col]))
				{
					$values[$col] = $page[$col];
				}
			}
			$values['pagetemplateid'] = $this->referencedTemplates[$page['pageTemplateGuid']]['pagetemplateid'];
			
			if (isset($page['parentGuid']) AND !empty($page['parentGuid']))
			{
				$parent = $this->db->getRow('page', array('guid' => $page['parentGuid']));
				
				if ($parent)
				{
					$values['parentid'] = $parent['pageid'];
				}
				else if (!($this->options & vB_Xml_Import::OPTION_IGNOREMISSINGPARENTS))
				{
					throw new Exception('Couldn\'t find parent while attempting to import page ' . $page['guid']);
				}
			}
			
			$pageId = 0;
			$condition = array('guid' => $page['guid']);
			if ($this->options & self::OPTION_OVERWRITE)
			{
				// overwrite preexisting record
				$this->db->delete('page', $condition);
			}
			else
			{
				if ($existingPage = $this->db->getRow('page', $condition))
				{
					$pageId = $existingPage['pageid'];
				}
			}
			
			if (empty($pageId))
			{
				$pageId = $this->db->insertIgnore('page', $values);
			}
			
			if (is_array($pageId))
			{
				$pageId = array_pop($pageId);
			}
			
			vB_Xml_Import::setImportedId(vB_Xml_Import::TYPE_PAGE, $page['guid'], $pageId);
		}
	}

	public function updatePageRoutes()
	{
		$importedPages = vB_Xml_Import::getImportedId(vB_Xml_Import::TYPE_PAGE);
		$importedRoutes = vB_Xml_Import::getImportedId(vB_Xml_Import::TYPE_ROUTE);
		$pages = is_array($this->parsedXML['page'][0]) ? $this->parsedXML['page'] : array($this->parsedXML['page']);
		
		foreach ($pages AS $page)
		{
			if (isset($importedPages[$page['guid']]) AND isset($importedRoutes[$page['routeGuid']]))
			{
				$this->db->update(
					'page', 
					array('routeid' => $importedRoutes[$page['routeGuid']]),
					array('pageid'	=> $importedPages[$page['guid']])
				);
			}
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
