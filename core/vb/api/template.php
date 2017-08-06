<?php

/**
 * vB_Api_Template
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Template extends vB_Api
{
	protected $disableWhiteList = array('fetch', 'fetchBulk', 'fetchTemplateHooks');

	protected $library;

	protected static $special_templates = array(
	/* None currently exist.
	The dummy stops some crashes */
		'dummy entry',
	);

	protected static $common_templates = array(
		'header',
		'footer',
	);

	private static $templatecache = array();
	private static $bbcode_style = array('code' => -1, 'html' => -1, 'php' => -1, 'quote' => -1);

	protected function __construct()
	{
		parent::__construct();
		$this->library = vB_Library::instance('Template');
	}

	/**
	 * Fetch one template based on its name and style ID.
	 *
	 * @param string $template_name Template name.
	 * @param integer $styleid Style ID. If empty, this method will fetch template from default style.
	 * @return mixed
	 */
	public function fetch($template_name, $styleid = -1)
	{
		return $this->library->fetch($template_name, $styleid);
	}

	/**
	 * Fetches a bulk of templates from the database
	 *
	 * @param array $template_names List of template names to be fetched.
	 * @param integer $styleid Style ID. If empty, this method will fetch template from default style.
	 *
	 * @return array Array of information about the imported style
	 */
	public function fetchBulk($template_names, $styleid = -1, $type = 'compiled')
	{
		return $this->library->fetchBulk($template_names, $styleid, $type);
	}

	/**
	 * Get template ID by its template name and style id
	 *
	 * @param $template_name the name of the template
	 * @param $styleid
	 */
	public function getTemplateID($template_name, $styleid = -1)
	{
		$result = $this->getTemplateIds(array($template_name), $styleid);
		return $result['ids'][$template_name];
	}

	/**
	 * Get a list of template IDs by thier template names and style id
	 *
	 * @param array $template_names -- a list of template names
	 * @param array $styleid -- must be a style the user has access to.  If not specified, the default style is used.
	 * @return array array('ids' => $ids) where $ids is a map of names to the template id for that name.  If the name is not
	 * 	found, the entry for that name in the map will be false.
	 */
	public function getTemplateIds($template_names, $styleid = -1)
	{
		$cleaner = vB::getCleaner();
		$template_names = $cleaner->clean($template_names, vB_Cleaner::TYPE_ARRAY);
		$styleid = $cleaner->clean($styleid, vB_Cleaner::TYPE_INT);
		$stylelib = vB_Library::instance('style');

		$style = $stylelib->fetchStyleRecord($styleid, true);
		$ids = array();
		foreach($template_names AS $name)
		{
			if (isset($style['templatelist'][$name]))
			{
				$ids[$name] =  $style['templatelist'][$name];
			}
			else
			{
				$ids[$name] = false;
			}
		}

		return array('ids' => $ids);
	}


	/**
	 * Fetch template by its ID
	 *
	 * @param integer $templateid Template ID.
	 *
	 * @return array Return template array if $templateid is valid.
	 */
	public function fetchByID($templateid)
	{
		$templateid = intval($templateid);
		$result = vB::getDbAssertor()->assertQuery('template_fetchbyid', array('templateid' => $templateid));

		if ($result->valid())
		{
			return $result->current();
		}
		else
		{
			throw new vB_Exception_Api('invalidid', array('templateid'));
		}
	}

	/**
	 * Fetch one uncompiled template based on its name and style ID.
	 *
	 * @param string $template_name Template name.
	 * @param integer $styleid Style ID.
	 */
	public function fetchUncompiled($template_name, $styleid = -1)
	{
		$this->checkHasAdminPermission('canadminstyles');

		$templates = $this->fetchBulk(array($template_name), $styleid, 'uncompiled');

		if ($templates[$template_name])
		{
			return $templates[$template_name];
		}

		return false;
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
	 * Insert a new template
	 *
	 * @param integer $dostyleid Style ID which the new template belongs to.
	 * @param string $title Template name.
	 * @param string $content Template content.
	 * @param string $product The product ID which the template belongs to.
	 * @param boolean $savehistory Whether to save the change in template history.
	 * @param string $histcomment Comment of the change to be saved to template history.
	 *
	 * @return integer New inserted template ID.
	 */
	public function insert
	(
		$dostyleid,
		$title,
		$content,
		$product = 'vbulletin',
		$savehistory = false,
		$histcomment = '',
		$forcesaveonerror = false
	)
	{
		$this->checkHasAdminPermission('canadminstyles');

		$dostyleid = intval($dostyleid);
		$title = trim($title);
		$content = trim($content);
		$product = trim($product);
		$histcomment = trim($histcomment);
		$userinfo = vB::getCurrentSession()->fetch_userinfo();
		$timenow = vB::getRequest()->getTimeNow();

		//bad things happen if we don't have a valid product at this point.
		//If its blank (which is itself an error) recover with a default that keeps thee
		//system functional
		if (!$product)
		{
			$product = 'vbulletin';
		}

		if (!$title)
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}

		require_once(DIR . '/includes/adminfunctions.php');
		require_once(DIR . '/includes/adminfunctions_template.php');

		// Compile template
		$template = $this->compile($content, $forcesaveonerror);
		// TODO: Product API
		$full_product_info = fetch_product_list(true);

		$result = vB::getDbAssertor()->assertQuery('template_get_existing', array('title' => $title));

		foreach ($result as $curtemplate)
		{
			$exists["$curtemplate[styleid]"] = $curtemplate;
		}

		// work out what we should be doing with the product field
		if ($exists['-1'] AND $dostyleid != -1)
		{
			// there is already a template with this name in the master set - don't allow a different product id
			$product = $exists['-1']['product'];
		}
		else if ($dostyleid != -1)
		{
			// we are not adding a new template to the master set - only allow the default product id
			$product = 'vbulletin';
		}
		else
		{
			// allow this - we are adding a totally new template to the master set
		}

		$stylelib = vB_Library::instance('Style');

		// check if template already exists
		if (!$exists[$dostyleid])
		{
			$templateid = $this->saveTemplate(
				$title,
				$template,
				$content,
				$timenow,
				$userinfo['username'],
				$full_product_info[$product]['version'],
				$product,
				null,
				null,
				$dostyleid,
				$savehistory,
				$histcomment
			);

			// now to update the template id list for this style and all its dependents...
			$stylelib->buildStyle($dostyleid, $title, array(
				'docss' => 0,
				'dostylevars' => 0,
				'doreplacements' => 0,
				'doposteditor' => 0
			), false);

			vB_Library::instance('Style')->buildStyleDatastore();
		}
		else
		{
			throw new vB_Exception_Api('template_x_exists_error', array($title));
		}

		if ($savehistory)
		{
			$result = vB::getDbAssertor()->assertQuery('template_savehistory', array(
				'dostyleid' 	=> $dostyleid,
				'title' 		=> $title,
				'template_un' 	=> $content,
				'dateline'		=> $timenow,
				'username'		=> $userinfo['username'],
				'version'		=> $full_product_info[$product]['version'],
				'comment'		=> $histcomment,
			));
		}

		$stylelib->buildStyleDatastore();
		return $templateid;
	}

	/**
	 * Update a template
	 *
	 * @param integer $templateid Template ID to be updated
	 * @param string $title Template name.
	 * @param string $content Template content.
	 * @param string $product The product ID which the template belongs to.
	 * @param string $oldcontent The content of the template at the time it was loaded.  This is used to prevent
	 *	cases where the template was changed while editing. Pass false to force an update.
	 * @param boolean $savehistory Whether to save the change in template history.
	 * @param string $histcomment Comment of the change to be saved to template history.
	 * @param boolean $forcesaveonerror save the template even though there are errors.
	 */
	public function update
	(
		$templateid,
		$title,
		$content,
		$product,
		$oldcontent,
		$savehistory,
		$histcomment,
		$forcesaveonerror = false
	)
	{
		$this->checkHasAdminPermission('canadminstyles');

		$templateid = intval($templateid);
		$title = trim($title);
		$content = trim($content);
		$product = trim($product);
		$histcomment = trim($histcomment);
		$userinfo = vB::getCurrentSession()->fetch_userinfo();
		$timenow = vB::getRequest()->getTimeNow();

		require_once(DIR . '/includes/adminfunctions.php');
		require_once(DIR . '/includes/adminfunctions_template.php');

		// Compile template
		$template = $this->compile($content, $forcesaveonerror);

		// TODO: Product API
		$full_product_info = fetch_product_list(true);

		if (!$forcesaveonerror)
		{
			$errors = check_template_conflict_error($template);
			if (!empty($errors))
			{
				throw new vB_Exception_Api('template_conflict_errors', array($errors));
			}
		}

		$old_template = $this->fetchByID($templateid);

		// Test whether the template exists if new template title is not the same as old one's
		if (strtolower($title) != strtolower($old_template['title']))
		{
			$result = vB::getDbAssertor()->assertQuery('template_fetchbystyleandtitle', array(
				'styleid' => $old_template['styleid'],
				'title' => $title,
			));

			if ($result->valid())
			{
				throw new vB_Exception_Api('invalidid', array('templateid'));
			}
		}

		if ($oldcontent === false)
		{
			$hash = md5($old_template['template_un']);
		}
		else
		{
			$hash = md5($oldcontent);
		}

		$result = $this->saveTemplate(
			$title,
			$template,
			$content,
			$timenow,
			$userinfo['username'],
			$full_product_info[$product]['version'],
			$product,
			$templateid,
			$hash,
			$old_template['styleid'],
			$savehistory,
			$histcomment
		);

		if ($result == 0)
		{
			// we have an edit conflict
			throw new vB_Exception_Api('edit_conflict');
		}
		else
		{
			// Remove templatemerge record
			vB::getDbAssertor()->assertQuery('templatemerge',
				array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,	'templateid' => $templateid));

			// update any customized templates to reflect a change of product id
			if ($old_template['styleid'] == -1 AND $product != $old_template['product'])
			{
				$result = vB::getDbAssertor()->assertQuery('template_updatecustom_product', array(
					'product'	=> $product,
					'title' 	=> $title,
				));
			}

			//we need to rebuild the style if a css template is changed, we may need to republish.
			if (preg_match('#\.css$#i', $title))
			{
				build_style($old_template['styleid'], $title, array(
					'docss' => 0,
					'dostylevars' => 0,
					'doreplacements' => 0,
					'doposteditor' => 0
				), '', '', false, false);
			}

			return true;
		}
	}


	/**
	 *	Save a template and handle all common operations between an insert and an update
	 *	caller is responsible for determining if a update or an insert is needed (via
	 *	providing the existing templateid for the record to be updated)
	 *
	 *	@param $title string.  The title of the template
	 *	@param $template string.  Compiled template text
	 *	@param $content string. Uncompiled template text
	 *	@param $timenow int.  Current time as a datestamp
	 *	@param $username string. Username of the user saving the template
	 *	@param $version string. The version of the product the template belongs to.
	 *	@param $product string. The product that the template belongs to.
	 *	@param $templateid int. The id of the template being saved, null if this is a new template
	 *	@param $hash string.  The md5 hash of the original text of the template being updated. This is used to
	 *		avoid conflicting edits.  Null if this is a new template.
	 *	@param $styleid int.  The ID of the style the template is being saved to.
	 *	@param $savehistory bool. Whether to save this edit to the template history -- valid for new templates
	 *	@param $hiscomment string.  A comment on the edit to save with the history
	 *
	 */
	protected function saveTemplate
	(
		$title,
		$template,
		$content,
		$timenow,
		$username,
		$version,
		$product,
		$templateid,
		$hash,
		$styleid,
		$savehistory,
		$histcomment
	)
	{
		$fields = array(
			'title' => $title,
			'template' => $template,
			'template_un' => $content,
			'dateline' => $timenow,
			'username' => $username,
			'version' => $version,
			'product' => $product,
		);

		//update
		if($templateid)
		{
			$fields['templateid'] = $templateid;
			$fields['hash'] = $hash;
			$queryid = 'template_update';
		}
		//insert
		else
		{
			$fields['dostyleid'] = $styleid;
			$queryid = 'template_insert';
		}

		// Do update
		$result = vB::getDbAssertor()->assertQuery($queryid, $fields);

		//a non positive result indicates failure
		if ($result)
		{
			// now update the file system if we setup to do so and we are in the master style
			if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT AND $styleid == -1)
			{
				require_once(DIR . '/includes/functions_filesystemxml.php');
				autoexport_write_template($title, $content, $product, $version, $username, $timenow);
			}

			if ($savehistory)
			{
				vB::getDbAssertor()->assertQuery('template_savehistory', array(
					'dostyleid' => $styleid,
					'title' => $title,
					'template_un' => $content,
					'dateline' => $timenow,
					'username' => $username,
					'version' => $version,
					'comment' => $histcomment,
				));
			}

			//if this is a new template the return from the insert query is the templateid
			if (!$templateid)
			{
				$templateid = $result;
			}

			//if we are storing the templates on the file systems
			$options = vB::getDatastore()->getValue('options');
			if ($options['cache_templates_as_files'] AND $options['template_cache_path'])
			{
				$this->library->saveTemplateToFileSystem($templateid, $template, $options['template_cache_path']);
			}

			vB_Library::instance('Style')->setCssDate();
		}

		return $result;
	}


	/**
	 * Delete a template
	 *
	 * @param integer $templateid Template ID to be deleted.
	 */
	public function delete($templateid)
	{
		$this->checkHasAdminPermission('canadminstyles');

		$templateid = intval($templateid);

		$template = $this->fetchByID($templateid);

		if ($template)
		{
			$this->deleteTemplateInternal($templateid, $template['styleid']);
		}

		if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT AND $template['styleid'] == -1)
		{
			require_once(DIR . '/includes/functions_filesystemxml.php');
			autoexport_delete_template($template['title']);
		}

		return true;
	}

	/**
	 * Fetch original (not customized) template content
	 *
	 * @param string $title Template name.
	 *
	 * @return array Original template information
	 */
	public function fetchOriginal($title)
	{
		$this->checkHasAdminPermission('canadminstyles');

		$title = trim($title);

		$result = vB::getDbAssertor()->assertQuery('template_fetchoriginal', array('title' => $title));

		if ($result->valid())
		{
			return $result->current();
		}
		else
		{
			throw new vB_Exception_Api('invalidid', array('templateid'));
		}
	}

	/**
	 * Find custom templates that need updating
	 *
	 * @return array Templates that need updating.
	 */
	public function findUpdates()
	{
		$this->checkHasAdminPermission('canadminstyles');

		require_once(DIR . '/includes/adminfunctions.php');
		require_once(DIR . '/includes/adminfunctions_template.php');

		$customcache = fetch_changed_templates();
		// TODO: Product API
		$full_product_info = fetch_product_list(true);

		$stylecache = vB_Library::instance('Style')->fetchStyles(false, false);

		$return = array();
		foreach ($stylecache AS $styleid => $style)
		{
			if (is_array($customcache["$styleid"]))
			{
				$return[] = $customcache["$styleid"];
			}
		}

		return $return;
	}

	/**
	 * Dismiss automatical merge
	 *
	 * @param array Template IDs which merge needs to be dismissed.
	 * @return array Number of affected templates.
	 */
	public function dismissMerge($templateids)
	{
		$this->checkHasAdminPermission('canadminstyles');

		if (empty($templateids))
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}

		foreach ($templateids as &$templateid)
		{
			$templateid = intval($templateid);
		}

		$result = vB::getDbAssertor()->assertQuery('template_update_mergestatus', array('templateids' => $templateids));

		vB::getDbAssertor()->assertQuery('templatemerge',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,	'templateid' => $templateids));

		return $result;
	}

	/**
	 * Search and fetch a list of templates
	 *
	 * @param integer $dostyleid Style ID to be searched in. -1 means search in all styles.
	 * @param mixed $expandset
	 * @param string $searchstring Search for text.
	 * @param boolean $titlesonly Wether to search template titles (names) only.
	 *
	 * @return mixed false if no templates are found. Otherwise an array will be returned with styleids as its keys.
	 */
	public function search($dostyleid, $expandset = null, $searchstring = '', $titlesonly = true)
	{
		$this->checkHasAdminPermission('canadminstyles');

		if ($searchstring)
		{
			$group = 'all';
		}

		if (!empty($expandset))
		{
			$result = vB::getDbAssertor()->assertQuery('template_getmasters');
			foreach ($result as $master)
			{
				$masterset["$master[title]"] = $master['templateid'];
			}
		}
		else
		{
			$masterset = array();
		}

		$stylecache = vB_Library::instance('Style')->fetchStyles(false, false);

		$return = array();
		foreach($stylecache AS $styleid => $style)
		{
			if ($styleid == -1)
			{
				$THISstyleid = 0;
				$vbphrase = vB_Api::instanceInternal('phrase')->fetch(array('master_style'));
				$style['title'] = $vbphrase['master_style'];
				$style['templatelist'] = serialize($masterset);
			}
			else
			{
				$THISstyleid = $styleid;
			}

			if ($expandset == 'all' OR $expandset == $styleid)
			{
				$showstyle = 1;
			}
			else
			{
				$showstyle = 0;
			}

			$result = vB::getDbAssertor()->assertQuery('searchTemplates', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'searchstring' => $searchstring,
				'titlesonly' => $titlesonly,
				'templateids' => implode(',' , unserialize($style['templatelist'])),
			));

			if (!$result->valid())
			{
				return false;
			}

			foreach ($result as $template)
			{
				if ($template['templatetype'] == 'replacement')
				{
					$replacements["$template[templateid]"] = $template;
				}
				else
				{
					// don't show any special templates
					if (in_array($template['title'], vB_Api::instanceInternal('template')->fetchSpecialTemplates()))
					{
						continue;
					}
					else
					{
						$m = substr(strtolower($template['title']), 0, iif($n = strpos($template['title'], '_'), $n, strlen($template['title'])));
						if ($template['styleid'] != -1 AND !isset($masterset["$template[title]"]) AND !isset($only["$m"]))
						{
							$customtemplates["$template[templateid]"] = $template;
						}
						else
						{
							$maintemplates["$template[templateid]"] = $template;
						}
					}
				}

				$return[$styleid]['customtemplates'] = $customtemplates;
				$return[$styleid]['maintemplates'] = $maintemplates;
			}
		}

		return $return;
	}

	/**
	 * Search and Replace templates.
	 *
	 * @param integer $dostyleid Style ID to be searched in. -1 means search in all styles.
	 * @param string $searchstring Search for text.
	 * @param string $replacestring Replace with text.
	 * @param boolean $case_insensitive Case-Insensitive or not.
	 * @param boolean $regex Whether to use regular expressions.
	 * @param boolean $test Test only.
	 * @param integer $startat_style Replacement startat style ID.
	 * @param integer $startat_template Replacement startat template ID.
	 *
	 * @return mixed False if no templates found. Otherwise an array will be returned.
	 */
	public function searchAndReplace(
		$dostyleid,
		$searchstring,
		$replacestring,
		$case_insensitive,
		$regex,
		$test,
		$startat_style,
		$startat_template
	)
	{
		$this->checkHasAdminPermission('canadminstyles');

		require_once(DIR . '/includes/adminfunctions.php');
		require_once(DIR . '/includes/adminfunctions_template.php');

		// TODO: Product API
		$full_product_info = fetch_product_list(true);

		$vb5_config = &vB::getConfig();
		$userinfo = vB::getCurrentSession()->fetch_userinfo();


		$perpage = 50;
		$searchstring = str_replace(chr(0), '', $searchstring);

		if (empty($searchstring))
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}

		$limit_style = $startat_style;
		$conditions = array();
		if ($dostyleid == -1)
		{
			if ($vb5_config['Misc']['debug'])
			{
				$conditions[] = array('field' => 'styleid', 'value' => -2, 'operator' => vB_dB_Query::OPERATOR_NE);
				if ($startat_style == 0)
				{
					$editmaster = true;
				}
				else
				{
					$limit_style--; // since 0 means the master style, we have to renormalize
				}
			}
			else
			{
				$conditions[] = array('field' => 'styleid', 'value' => 0, 'operator' => vB_dB_Query::OPERATOR_GT);
			}
		}
		else
		{
			$conditions['styleid'] = $dostyleid;
		}

		if ($editmaster != true)
		{
			$result = vB::getDbAssertor()->assertQuery('getStyleByConds', array(
				vB_Db_Query::TYPE_KEY => vB_Db_Query::QUERY_METHOD,
				'conds' => $conditions,
				'limit_style' => $limit_style
			));

			if (!$result->valid())
			{
				// couldn't grab a style, so we're done
				return false;
			}
			$styleinfo = $result->current();
			$templatelist = unserialize($styleinfo['templatelist']);
		}
		else
		{
			$styleinfo = array(
				'styleid' => -1,
				'title' => 'MASTER STYLE'
			);
			$templatelist = array();

			$result = vB::getDbAssertor()->assertQuery('template_getmasters2');
			foreach ($result as $tid)
			{
				$templatelist["$tid[title]"] = $tid['templateid'];
			}
			$styleinfo['templatelist'] = serialize($templatelist); // for sanity
		}

		$loopend = $startat_template + $perpage;
		$process_templates = array(0);
		$i = 0;

		foreach ($templatelist AS $title => $tid)
		{
			if ($i >= $startat_template AND $i < $loopend)
			{
				$process_templates[] = $tid;
			}
			if ($i >= $loopend)
			{
				break;
			}
			$i++;
		}
		if ($i != $loopend)
		{
			// didn't get the $perpage templates, so we're done with this style
			$styledone = true;
		}
		else
		{
			$styledone = false;
		}

		$templates = vB::getDbAssertor()->assertQuery('template', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'templateid' => $process_templates
			)
		);
		$stats['page'] = $startat_template / $perpage + 1;
		$stats['first'] = $startat_template + 1;
		$count = 0;
		$processed_templates = array();
		foreach ($templates as $temp)
		{
			$count ++;
			$insensitive_mod = ($case_insensitive ? 'i' : '');

			if ($test)
			{
				if ($regex)
				{
					$encodedsearchstr = str_replace('(?&lt;', '(?<', htmlspecialchars_uni($searchstring));
				}
				else
				{
					$encodedsearchstr = preg_quote(htmlspecialchars_uni($searchstring), '#');
				}

				$newtemplate = preg_replace("#$encodedsearchstr#sU$insensitive_mod",
					'<span class="col-i" style="text-decoration:underline;">' .
					htmlspecialchars_uni($replacestring) . '</span>', htmlspecialchars_uni($temp['template_un']));

				if ($newtemplate != htmlspecialchars_uni($temp['template_un']))
				{
					$temp['newtemplate'] = $newtemplate;
					$processed_templates[] = $temp;
				}
				else
				{
					continue;
				}
			}
			else
			{
				if ($regex)
				{
					$newtemplate = preg_replace("#" . $searchstring . "#sU$insensitive_mod", $replacestring, $temp['template_un']);
				}
				else
				{
					$usedstr = preg_quote($searchstring, '#');
					$newtemplate = preg_replace("#$usedstr#sU$insensitive_mod", $replacestring, $temp['template_un']);
				}

				if ($newtemplate != $temp['template_un'])
				{
					if ($temp['styleid'] == $styleinfo['styleid'])
					{
						vB::getDbAssertor()->assertQuery('template_update2', array(
							'template' 		=> $this->compile($newtemplate, true),
							'template_un' 	=> $newtemplate,
							'dateline'		=> TIMENOW,
							'username'		=> $userinfo['username'],
							'version'		=> $full_product_info["$temp[product]"]['version'],
							'templateid'	=> $temp['templateid']
						));

						// now update the file system if we setup to do so and we are in the master style
						if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT AND $temp['styleid'] == -1)
						{
							require_once(DIR . '/includes/functions_filesystemxml.php');
							autoexport_write_template(
								$temp['title'],
								$newtemplate,
								$temp['product'],
								$full_product_info["$temp[product]"]['version'],
								$userinfo['username'],
								TIMENOW
							);
						}

						vB::getDbAssertor()->assertQuery('templatemerge',
							array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,	'templateid' => $temp['templateid']));
					}
					else
					{
						/*insert query*/
						$result = vB::getDbAssertor()->assertQuery('template_insert', array(
							'dostyleid' 	=> $styleinfo['styleid'],
							'title' 		=> $temp['title'],
							'template' 		=> $this->compile($newtemplate, true),
							'template_un' 	=> $newtemplate,
							'dateline'		=> TIMENOW,
							'username'		=> $userinfo['username'],
							'version'		=> $full_product_info["$temp[product]"]['version'],
							'product'		=> $temp['product'],
						));

						$requirerebuild = true;
					}
					$temp['newtemplate'] = $newtemplate;
					$processed_templates[] = $temp;
					vB_Library::instance('Style')->setCssDate();
				}
				else
				{
					continue;
				}
			}
		} // End foreach
		$stats['last'] = $startat_template + $count;

		if ($styledone == true)
		{
			// Go to the next style. If we're only doing replacements in one style,
			// this will trigger the finished message.
			$startat_style++;
			$loopend = 0;
		}

		return array(
			'processed_templates'	=> $processed_templates,
			'startat_style' 		=> $startat_style,
			'startat_template'		=> $loopend,
			'requirerebuild'		=> $requirerebuild,
			'styleinfo'				=> $rtyleinfo,
			'stats'					=> $stats,
		);
	}

	/**
	 * Revert all templates in a style
	 *
	 * @param integer $dostyleid Style ID where the custom templates in it will be reverted
	 *
	 * @return boolean False if nothing to do.
	 */
	public function revertAllInStyle($dostyleid)
	{
		$this->checkHasAdminPermission('canadminstyles');

		if ($dostyleid == -1)
		{
			throw new vB_Exception_Api('invalid_style_specified');
		}

		$style = vB_Library::instance('Style')->fetchStyleByID($dostyleid);

		if (!$style)
		{
			throw new vB_Exception_Api('invalid_style_specified');
		}

		if (!$style['parentlist'])
		{
			$style['parentlist'] = '-1';
		}
		else if (is_string($style['parentlist']))
		{
			$style['parentlist'] = explode(',', $style['parentlist']);
		}

		$result = vB::getDbAssertor()->assertQuery('template_getrevertingtemplates', array(
			'styleparentlist' => $style['parentlist'],
			'styleid'	=> $style['styleid'],
		));

		if ($result->valid())
		{
			$deletetemplates = array();
			foreach ($result as $template)
			{
				$deletetemplates["$template[title]"] = $template['templateid'];
			}

			if (!empty($deletetemplates))
			{
				$this->deleteTemplateInternal($deletetemplates, $style['styleid']);
				return true;
			}
		}

		return false;
	}

	/**
	 * Massive merge templates
	 *
	 * @param string $product Product string ID.
	 * @param integer $startat Start offset of the merge.
	 *
	 * @return integer New startat value. -1 if no more to do.
	 */
	public function massMerge($product = 'vbulletin', $startat = 0)
	{
		$this->checkHasAdminPermission('canadminstyles');

		require_once(DIR . '/includes/adminfunctions.php');

		// TODO: Product API
		$full_product_info = fetch_product_list(true);
		$vbulletin = &vB::get_registry();
		require_once(DIR . '/includes/class_template_merge.php');
		require_once(DIR . '/includes/adminfunctions_template.php');

		$merge = new vB_Template_Merge($vbulletin);
		$merge->time_limit = 5;

		$merge_data = new vB_Template_Merge_Data($vbulletin);
		$merge_data->start_offset = $startat;

		if ($product == 'vbulletin' OR !$product)
		{
			$merge_data->add_condition("tnewmaster.product IN ('', 'vbulletin')");
		}
		else
		{
			$merge_data->add_condition("tnewmaster.product = '" . mysql_escape_string($product) . "'");

			$merge->merge_version = $full_product_info[$product]['version'];
		}

		$completed = $merge->merge_templates($merge_data, $output);

		if ($completed)
		{
			// completed
			build_all_styles();

			vB_Library::instance('Style')->setCssDate();

			return -1;
		}
		else
		{
			return $merge_data->start_offset + $merge->fetch_processed_count();
		}
	}

	/**
	 * Return editing history of a template, including old versions and diffs between versions
	 *
	 * @param string $title Template name.
	 * @param integer $dostyleid Style ID of the template.
	 *
	 * @return array Array of template history revisions.
	 */
	public function history($title, $dostyleid)
	{
		$this->checkHasAdminPermission('canadminstyles');

		$revisions = array();
		$have_cur_def = false;
		$cur_temp_time = 0;

		$current_temps = vB::getDbAssertor()->assertQuery('template_fetchbystyleandtitle2', array(
			'title' => $title,
			'styleid' => $dostyleid
		));
		foreach ($current_temps as $template)
		{
			$template['type'] = 'current';

			// the point of the second part of this key is to prevent dateline
			// collisions, as rare as that may be
			$revisions["$template[dateline]|b$template[templateid]"] = $template;

			if ($template['styleid'] == -1)
			{
				$have_cur_def = true;
			}
			else
			{
				$cur_temp_time = $template['dateline'];
			}
		}

		$historical_temps = vB::getDbAssertor()->getRows('templatehistory',
			array('title' => $title, 'styleid' => array(-1, $dostyleid)));
		$history_count = count($historical_temps);
		foreach ($historical_temps as $template)
		{
			$template['type'] = 'historical';

			// the point of the second part of this key is to prevent dateline
			// collisions, as rare as that may be
			$revisions["$template[dateline]|a$template[templatehistoryid]"] = $template;
		}

		// I used a/b above, so current versions sort above historical versions
		usort($revisions, "history_compare");

		return $revisions;
	}

	/**
	 * Return editing history of a template by its ID, including old versions and diffs between versions
	 *
	 * @param integer $templateid Template ID.
	 *
	 * @return array Array of template history revisions.
	 */
	public function historyByTemplateID($templateid)
	{
		$this->checkHasAdminPermission('canadminstyles');

		$template = $this->fetchByID($templateid);
		return $this->history($template['title'], $template['styleid']);
	}

	/**
	 * Fetch current or historical uncompiled version of a template
	 *
	 * @param integer The ID (in the appropriate table) of the record you want to fetch.
	 * @param string Type of template you want to fetch; should be "current" or "historical"
	 */
	public function fetchVersion($historyid, $type)
	{
		$this->checkHasAdminPermission('canadminstyles');

		require_once(DIR . '/includes/adminfunctions_template.php');

		$template = fetch_template_current_historical($historyid, $type);

		return $template;
	}

	/**
	 * Delete template history versions
	 *
	 * @param array $historyids History IDs to be deleted
	 */
	public function deleteHistoryVersion($historyids)
	{
		$this->checkHasAdminPermission('canadminstyles');

		if (!is_array($historyids))
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}

		vB::getDbAssertor()->assertQuery('templatehistory', array(
			vB_Db_Query::TYPE_KEY => vB_Db_Query::QUERY_DELETE,
			'templatehistoryid' => $historyids
		));
	}

	/**
	 * Compile a template.
	 *
	 * @param string $template_un The uncompiled content of a template.
	 */
	protected function compile($template, $forcesaveonerror)
	{
		// @todo
		// Incorrect hack warning!!!
		// The legacy code in class_template_parser.php needs this to be set
		// but it apparrently does not actually need to be an instance of the
		// legacy db class for purposes of compiling a template.
		if (empty($GLOBALS['vbulletin']->db))
		{
			$GLOBALS['vbulletin']->db = false;
		}

		require_once(DIR . '/includes/class_template_parser.php');
		$parser = new vB_TemplateParser($template);

		try
		{
			$parser->validate($errors);
		}
		catch (vB_Exception_TemplateFatalError $e)
		{
			throw new vB_Exception_Api($e->getMessage());
		}

		$template = $parser->compile();

		// This is a comment from vB4 moved here.  Need to figure out what replace_template_variables
		// is supposed to do.
		// TODO: Reimplement these - if done, $session[], $bbuserinfo[], $vboptions
		// will parse in the template without using {vb:raw, which isn't what we
		// necessarily want to happen
		/*
		if (!function_exists('replace_template_variables'))
		{
			require_once(DIR . '/includes/functions_misc.php');
		}
		$template = replace_template_variables($template, false);
		*/

		if (function_exists('verify_demo_template'))
		{
			verify_demo_template($template);
		}

		// Legacy Hook 'template_compile' Removed //

		if (!$forcesaveonerror AND !empty($errors))
		{
			throw new vB_Exception_Api('template_compile_error', array($errors));
		}

		//extra set of error checking.  This can be skipped in many situations.
		if (!$forcesaveonerror)
		{
			$errors = check_template_errors($template);
			if (!empty($errors))
			{
				throw new vB_Exception_Api('template_eval_error', array($errors));
			}
		}

		return $template;
	}

	public function fetchSpecialTemplates()
	{
		return self::$special_templates;
	}

	public function fetchCommonTemplates()
	{
		return self::$common_templates;
	}

	public function fetchTemplateHooks($hookName)
	{
		static $hooklist, $hooks_set;

		$vboptions = vB::getDatastore()->getValue('options');

		if (!$vboptions['enablehooks'] OR defined('DISABLE_HOOKS'))
		{
			return false;
		}

		if (!$hooks_set)
		{
			$hooks_set = true;
			$hooklist = $this->buildHooklist();
		}

		if (isset($hooklist[$hookName]))
		{
			return $hooklist[$hookName];
		}
		else
		{
			return false;
		}
	}

	public function saveAllTemplatesToFile()
	{
		//these are primarily for the options.  We should
		$this->checkHasAdminPermission('canadminsettings');
		vB_Library::instance('template')->saveAllTemplatesToFile();
	}

	public function deleteAllTemplateFiles()
	{
		$this->checkHasAdminPermission('canadminsettings');
		vB_Library::instance('template')->deleteAllTemplateFiles();
	}


	/** Preload basic language information we're going to need.
	 *
	 */
	public function loadLanguage()
	{
		vB::getCurrentSession()->loadLanguage();
	}


	//make sure that we handle all of the associated operations when we delete a template
	protected function deleteTemplateInternal($templateids, $styleid)
	{
		//delete some stuff.
		vB::getDbAssertor()->assertQuery('template',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,	'templateid' => $templateids));

		vB::getDbAssertor()->assertQuery('templatemerge',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,	'templateid' => $templateids));

		//if we are storing the templates on the file systems
		//handle array or single item
		if (!is_array($templateids))
		{
			$templateids = array($templateids);
		}

		$options = vB::getDatastore()->getValue('options');
		//delete cached template files.
		foreach($templateids as $templateid)
		{
			if ($options['cache_templates_as_files'] AND $options['template_cache_path'])
			{
				$this->library->deleteTemplateFromFileSystem($templateid, $options['template_cache_path']);
			}
		}

		//rebuild cached information
		$stylelib = vB_Library::instance('Style');
		$stylelib->buildStyle($styleid, '', array('docss' => 0, 'dostylevars' => 0, 'doreplacements' => 0, 'doposteditor' => 0), false);
		$stylelib->buildStyleDatastore();
	}

	private function buildHooklist()
	{
		$hooks = array();
		$templateHooks = vB::getDatastore()->get_value('hooks');

		if ($templateHooks)
		{
			foreach ($templateHooks as $hook)
			{
				$hooks[$hook['hookname']][][$hook['template']] = $hook['arguments'] ? unserialize($hook['arguments']) : array();
			}
		}

		return $hooks;
	}

	/**
	 * Process the replacement variables.
	 *
	 * @param string The html to be processed
	 * @param integer The styleid to use.
	 *
	 * @return string The processed output
	 */
	public function processReplacementVars($html, $syleid = -1)
	{
		return $this->library->processReplacementVars($html, $syleid);
	}
}

