<?php
if (!defined('VB_ENTRY')) die('Access denied.');
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
 * vB_Library_Template
 *
 * @package vBApi
 * @access public
 */
class vB_Library_Template extends vB_Library
{
	private static $templatecache = array();
	private static $bbcode_style = array('code' => -1, 'html' => -1, 'php' => -1, 'quote' => -1);

	/**
	 * Fetch one template based on its name and style ID.
	 *
	 * @param string $template_name Template name.
	 * @param integer $styleid Style ID. If empty, this method will fetch template from default style.
	 * @return mixed
	 */
	public function fetch($template_name, $styleid = -1, $nopermissioncheck = false)
	{
		if (!empty(self::$templatecache[$template_name]))
		{
			return self::$templatecache[$template_name];
		}
		$templates = $this->fetchBulk(array($template_name), $styleid, 'compiled', $nopermissioncheck);

		if ($templates[$template_name])
		{
			return $templates[$template_name];
		}

		return false;
	}

	/**
	 * Fetches a bulk of templates from the database
	 *
	 * @param array $template_names List of template names to be fetched.
	 * @param integer $styleid Style ID. If empty, this method will fetch template from default style.
	 *
	 * @return array Array of information about the imported style
	 */
	public function fetchBulk($template_names, $styleid = -1, $type = 'compiled', $nopermissioncheck = false)
	{
		if ($styleid == -1)
		{
			$vboptions = vB::getDatastore()->get_value('options');
			$styleid = $vboptions['styleid'];
		}
		$style = false;

		$response = array();
		foreach ($template_names AS $template)
		{
			// see if we have it in cache already
			if ($type == 'compiled' AND !empty(self::$templatecache[$template]))
			{
				$response[$template] = self::$templatecache[$template];
				continue;
			}
			// load the cache only(once) when we need it
			if (empty($style))
			{
				$style = vB_Library::instance('Style')->fetchStyleRecord($styleid, $nopermissioncheck);
				$templateassoc = $style['templatelist'];
			}
			//handle bad template names -- they should be blank by default.
			if (isset($templateassoc["$template"]))
			{
				$templateids[] = intval($templateassoc["$template"]);
			}
			else
			{
				// @todo: throw an exception if the template doesn't exist and we are in debug mode?
				$response[$template] = '';
			}
		}

		if (!empty($templateids))
		{
			$result = vB::getDbAssertor()->assertQuery('template', array('templateid' => $templateids));

			//vB::getDbAssertor()->assertQuery('template_fetchbyids', array('templateids' => $templateids));

			if ($result->valid())
			{
				$template = $result->current();

				while ($result->valid())
				{
					if ($type == 'compiled')
					{
						$response[$template['title']] = $template['template'];
					}
					else
					{
						$response[$template['title']] = $template['template_un'];
					}
					self::$templatecache[$template['title']] = $template['template'];
					$template = $result->next();
				}
			}
		}

		return $response;
	}

	/**
	 * Fetches a number of templates from the database and puts them into the templatecache
	 *
	 * @param	array	List of template names to be fetched
	 * @param	string	Serialized array of template name => template id pairs
	 * @param	bool	Whether to skip adding the bbcode style refs
	 * @param	bool	Whether to force setting the template
	 */
	public function cacheTemplates($templates, $templateidlist, $skip_bbcode_style = false, $force_set = false)
	{
		$vboptions = vB::getDatastore()->get_value('options');
		$templateassoc = unserialize($templateidlist);

		if ($vboptions['legacypostbit'] AND in_array('postbit', $templates))
		{
			$templateassoc['postbit'] = $templateassoc['postbit_legacy'];
		}

		foreach ($templates AS $template)
		{
			$templateids[] = intval($templateassoc["$template"]);
		}

		if (!empty($templateids))
		{
			// run query
			$temps = vB::getDbAssertor()->assertQuery("vBForum:fetchtemplates", array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
					'templateids' => $templateids,
			));

			// cache templates
			foreach ($temps as $temp)
			{
				if (empty(self::$templatecache["$temp[title]"]) OR $force_set)
				{
					self::$templatecache["$temp[title]"] = $temp['template'];
				}
			}
		}

		if (!$skip_bbcode_style)
		{
			self::$bbcode_style = array(
					'code'  => &$templateassoc['bbcode_code_styleid'],
					'html'  => &$templateassoc['bbcode_html_styleid'],
					'php'   => &$templateassoc['bbcode_php_styleid'],
					'quote' => &$templateassoc['bbcode_quote_styleid']
			);
		}
	}

	/**
	 *	Rewrites the file cache for the templates for all styles.
	 */
	public function saveAllTemplatesToFile()
	{
		$template_path = vB::getDatastore()->getOption('template_cache_path');
		
		$db = vB::getDBAssertor();
		$result = $db->select('template', array(), false, array('templateid', 'template'));

		foreach ($result AS $template)
		{	
			$this->saveTemplateToFileSystem($template['templateid'], $template['template'], $template_path);
		}
	}

	public function deleteAllTemplateFiles()
	{
		$template_path = vB::getDatastore()->getOption('template_cache_path');
		
		$db = vB::getDBAssertor();
		$result = $db->select('template', array(), false, array('templateid', 'template'));
		
		foreach ($result AS $template)
		{
			$this->deleteTemplateFromFileSystem($template['templateid'], $template_path);
		}
	}	

	public function saveTemplateToFileSystem($templateid, $compiled_template, $template_path)
	{
		$template_name = "template$templateid.php";

		$real_path = realpath($template_path);
		if ($real_path === false)
		{
			$real_path = realpath(DIR . '/' . $template_path);
			if ($real_path === false)
			{
				throw new vB_Exception_Api('could_not_cache_template', array($templateid, $template_path, $template_name));
			}
		}

		$template_file = $real_path . "/$template_name";

		//determine if we can write to the provided location
		$can_write_template = false;
		
		//is writeable does not work properly on windows, see https://bugs.php.net/bug.php?id=54709
		//this is mostly used to avoid warnings when dealing with file_put_contents below so we'll skip the 
		//checks for windows.
		if (!(strtoupper(substr(PHP_OS, 0, 3)) == 'WIN'))
		{
			//file is writable
			if (is_writable($template_file))
			{
				$can_write_template = true;
			}
			else
			{
				//file doesn't exist and directory is writeable
				if(!file_exists($template_file) AND is_writeable($real_path))
				{
					$can_write_template = true;
				}
			}
		}
		else
		{
			$can_write_template = true;
		}
		
		//if we can write, try to write
		$file = false;
		if ($can_write_template)
		{
			$file = fopen($template_file, 'w+');
			if ($file)
			{
				//hack to deal with the fact that the presentation layer has a separate runtime class.
				$compiled_template = str_replace('vB_Template_Runtime', 'vB5_Template_Runtime', $compiled_template);
				
				fwrite($file, "<?php \nif (!class_exists('vB5_Template', false)) throw new Exception('direct access error');\n");
				fwrite($file, $compiled_template);
				fclose($file);
			}
		}
		
		if (!$can_write_template OR !$file)
		{
			throw new vB_Exception_Api('could_not_cache_template', array($templateid, $template_path, $template_name));
		}
	}

	public function deleteTemplateFromFileSystem($templateid, $template_path)
	{
		$template_name = "template$templateid.php";

		$real_path = realpath($template_path);
		if ($real_path === false)
		{
			$real_path = realpath(DIR . '/' . $template_path);
			if ($real_path === false)
			{
				//fail quietly on delete, not much we can do about it.
				return;
			}
		}

		$template_file = $real_path . "/$template_name";

		//is_writable not reliable on windows.
		if (!(strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') AND !is_writable($template_file))
		{
			return; 
		}

		if (file_exists($template_file))
		{
			unlink($template_file);
		}
	}

	/**
	 * Process the replacement variables.
	 *
	 * @param string The html to be processed
	 * @param integer The styleid to use.
	 *
	 * @return string The processed output
	 */
	public function processReplacementVars($html, $styleid = -1)
	{
		$style = vB_Library::instance('Style')->fetchStyleByID($styleid, false);

		if (!empty($style['replacements']))
		{
			if (!isset($replacementvars["$style[styleid]"]))
			{
				$replacementvars[$style['styleid']] = @unserialize($style['replacements']);
			}

			if (is_array($replacementvars[$style['styleid']]) AND !empty($replacementvars[$style['styleid']]))
			{
				$html = preg_replace(array_keys($replacementvars[$style['styleid']]), $replacementvars[$style['styleid']], $html);
			}
		}

		return $html;
	}
}
		
