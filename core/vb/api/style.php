<?php

/**
 * vB_Api_Style
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Style extends vB_Api
{
	// TODO: some of these methods shouldn't be public. We should move them to vB_Library_Style instead to avoid exposing them in the API.

	protected $disableWhiteList = array('fetchStyles');

	protected $library;

	protected function __construct()
	{
		parent::__construct();
		$this->library = vB_Library::instance('Style');
	}

	public function fetchStyleVars($stylePreference)
	{
		$styleId = $this->library->getValidStyleFromPreference($stylePreference);
		if ($styleId <= 0)
		{
			// use default style
			$styleId = vB::getDatastore()->getOption('styleid');
		}

		// fetch style from datastore
		$style = $this->library->fetchStyleByID($styleId);

		if (is_array($style) AND isset($style['newstylevars']))
		{
			return unserialize($style['newstylevars']);
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * Fetch All styles
	 *
	 * @param bool $withdepthmark If true, style title will be prepended with depth mark
	 * @param bool $userselectonly If true, this method returns only styles that allows user to select
	 *
	 * @return array All styles' information
	 */
	public function fetchStyles($withdepthmark = false, $userselectonly = false, $nocache = false)
	{
		// todo: if we don't need stylevars, set the second flag to false
		$stylecache = $this->library->fetchStyles($nocache, true);

		require_once(DIR . '/includes/adminfunctions.php');
		foreach ($stylecache as $k => $v)
		{
			if ($userselectonly AND !$v['userselect'])
			{
				unset($stylecache[$k]);
			}

			if (isset($stylecache[$k]) && $withdepthmark)
			{
				$stylecache[$k]['title'] = construct_depth_mark($v['depth'], '-') . ' ' . $v['title'];
			}
		}

		return $stylecache;
	}

	/**
	 * Insert style
	 *
	 * @param string $title Style title
	 * @param integer $parentid New parent style ID for the style.
	 * @param boolean $userselect Whether user is able to choose the style.
	 * @param integer $displayorder Display order.
	 * @return array array('styleid' => newstyleid)
	 */
	public function insertStyle($title, $parentid, $userselect, $displayorder)
	{
		$this->checkHasAdminPermission('canadminstyles');

		if (!$title)
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}

		$result = vB::getDbAssertor()->insert('style', array(
			'title' => $title,
			'parentid' => $parentid,
			'userselect' => intval($userselect),
			'displayorder' => $displayorder
		));
		if(is_array($result))
		{
			$result = array_pop($result);
		}

		require_once(DIR . '/includes/adminfunctions_template.php');
		build_template_parentlists();
		build_style($result, $title, array(
				'docss' => 1,
				'dostylevars' => 1,
				'doreplacements' => 1,
				'doposteditor' => 1
		), '', '', false, false);

		$this->library->buildStyleDatastore();

		return array('styleid' => $result);
	}

	/**
	 * Update style
	 *
	 * @param integer $dostyleid Style ID to be updated.
	 * @param string $title Style title.
	 * @param integer $parentid New parent style ID for the style.
	 * @param boolean $userselect Whether user is able to choose the style.
	 * @param integer $displayorder Display order of the style.
	 * @param boolean $rebuild Whether to rebuild style
	 */
	public function updateStyle($dostyleid, $title, $parentid, $userselect, $displayorder, $rebuild = false)
	{
		$this->checkHasAdminPermission('canadminstyles');

		$vboptions = vB::getDatastore()->getValue('options');
		if ($vboptions['styleid'] == $dostyleid)
		{
			// If a style is default style, we should always allow user to select it.
			$userselect = 1;
		}

		if (!$title)
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}

		// SANITY CHECK (prevent invalid nesting)
		if ($parentid == $dostyleid)
		{
			throw new vB_Exception_Api('cant_parent_style_to_self');
		}
		$parents = array();
		if ($parentid != -1)
		{
			$ts_info = $this->library->fetchStyleByID($parentid);
			$parents = explode(',', $ts_info['parentlist']);
		}

		foreach($parents AS $childid)
		{
			if ($childid == $dostyleid)
			{
				throw new vB_Exception_Api('cant_parent_x_to_child', array('style'));
			}
		}

		// end Sanity check

		vB::getDbAssertor()->update('style', array(
			'title' => $title,
			'parentid' => $parentid,
			'userselect' => intval($userselect),
			'displayorder' => $displayorder
		), array('styleid' => $dostyleid));

		if ($rebuild)
		{
			require_once(DIR . '/includes/adminfunctions_template.php');
			build_template_parentlists();
			build_style($dostyleid, $title, array(
				'docss' => 1,
				'dostylevars' => 1,
				'doreplacements' => 1,
				'doposteditor' => 1
			), '', '', false, false);
		}

		$this->library->buildStyleDatastore();

		return true;
	}

	/**
	 * Delete style
	 *
	 * @param integer $dostyleid Style ID to be deleted.
	 */
	public function deleteStyle($dostyleid)
	{
		$this->checkHasAdminPermission('canadminstyles');

		$vboptions = vB::getDatastore()->get_value('options');
		if ($dostyleid == $vboptions['styleid'])
		{
			throw new vB_Exception_Api('cant_delete_default_style');
		}

		// look at how many styles are being deleted
		$count = vB::getDbAssertor()->getField('style_count',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));

		// check that this isn't the last one that we're about to delete
		$last = vB::getDbAssertor()->getField('style_checklast',
			array(
				'styleid' => $dostyleid,
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED
			));
		if ($count == 1 AND $last == 1)
		{
			throw new vB_Exception_Api('cant_delete_last_style');
		}

		$style = $this->library->fetchStyleByID($dostyleid);

		// Delete css file
		if ($vboptions['storecssasfile'] AND $style)
		{
			$style['css'] .= "\n";
			$css = substr($style['css'], 0, strpos($style['css'], "\n"));

			// attempt to delete the old css file if it exists
			delete_css_file($dostyleid, $css);
			delete_style_css_directory($dostyleid, 'ltr');
			delete_style_css_directory($dostyleid, 'rtl');
		}

		vB::getDbAssertor()->assertQuery('template_deletefrom_templatemerge2', array('styleid' => $dostyleid));
		vB::getDbAssertor()->assertQuery('style_delete', array('styleid' => $dostyleid));
		vB::getDbAssertor()->assertQuery('template_deletehistory2', array('styleid' => $dostyleid));
		vB::getDbAssertor()->assertQuery('template_delete2', array('styleid' => $dostyleid));
		vB::getDbAssertor()->assertQuery('style_deletestylevar', array('styleid' => $dostyleid));

		// update parent info for child styles
		vB::getDbAssertor()->assertQuery('style_updateparent', array(
			'styleid' =>	$dostyleid,
			'parentid' =>	$style['parentid'],
			'parentlist' =>	$style['parentlist'],
		));

		$this->buildAllStyles(0, 0);

		return true;
	}

	/**
	* Builds all data from the template table into the fields in the style table
	*
	* @param	boolean	If true, will drop the template table and rebuild, so that template ids are renumbered from zero
	* @param	boolean	If true, will fix styles with no parent style specified
	* @param	boolean	If true, reset the master cache
	*/
	public function buildAllStyles($renumber = 0, $install = 0, $resetcache = false)
	{
		$this->checkHasAdminPermission('canadminstyles');

		$vbphrase = vB_Api::instanceInternal('phrase')->fetch(array('master_style'));

		// creates a temporary table in order to renumber all templates from 1 to n sequentially
		if ($renumber)
		{
			vB::getDbAssertor()->assertQuery('template_table_query_drop');
			vB::getDbAssertor()->assertQuery('template_table_query');

			/*insert query*/
			vB::getDbAssertor()->assertQuery('template_table_query_insert');

			vB::getDbAssertor()->assertQuery('template_drop');
			vB::getDbAssertor()->assertQuery('template_table_query_alter');
		}

		require_once(DIR . '/includes/adminfunctions_template.php');
		build_template_parentlists();

		$styleactions = array('docss' => 1, 'dostylevars' => 1, 'doreplacements' => 1, 'doposteditor' => 1);
		build_style(-1, $vbphrase['master_style'], $styleactions, '', '', $resetcache, false);

		$this->library->buildStyleDatastore();
		return true;
	}

	/**
	 * Create template files. It requires that a web-server writable folder called
	 * 'template_dump' exists in the root of the vbulletin directory
	 *
	 * @param integer $dostyleid Style ID where the templates are in.
	 * @param array $templateids Specify template IDs to be exported as files
	 */
	public function createTemplateFiles($dostyleid, $templateids = array())
	{
		$this->checkHasAdminPermission('canadminstyles');

		if (function_exists('set_time_limit') AND !SAFEMODE)
		{
			@set_time_limit(1200);
		}

		chdir(DIR . '/template_dump');

		$result = vB::getDbAssertor()->assertQuery('getTemplatesForDump', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'styleid' => $dostyleid,
			'templateids' => $templateids,
		));

		foreach ($result as $template)
		{
			$text = str_replace("\r\n", "\n", $template['template']);
			$text = str_replace("\n", "\r\n", $text);

			$fp = fopen("./$template[title].htm", 'w+');
			fwrite($fp, $text);
			fclose($fp);
		}

		return true;
	}

	public function generateStyle($scheme, $type, $parentid, $title, $displayorder = 1, $userselect = false)
	{
		if (!vB::getUserContext()->hasAdminPermission('canadminstyles'))
		{
			throw new vB_Exception_Api('no_permission_styles');
		}

		define('NO_IMPORT_DOTS', true);

		$merge = $scheme['primary'];

		if (!empty($scheme['secondary']))
		{
			$merge = array_merge($merge, $scheme['secondary']);
		}

		if (!empty($scheme['complement']))
		{
			$merge = array_merge($merge, $scheme['complement']);
		}

		foreach ($merge as $val)
		{
			$hex[] = $val['hex'];
		}

		switch ($type)
		{
			case 'lps': // Color : Primary and Secondary
				$sample_file = "style_generator_sample_light.xml";
				break;
			case 'lpt': // White : Similar to the current style
				$sample_file = "style_generator_sample_white.xml";
				break;
			case 'gry': // Grey :: Primary 3 and Primary 4 only
				$sample_file = "style_generator_sample_gray.xml";
				break;
			case 'drk': // Dark : Primary 3 and Primary 4 only
			default:// Dark : Default to Dark
				$sample_file = "style_generator_sample_dark.xml";
				break;
		}

		$xmlobj = new vB_XML_Parser(false, DIR . '/includes/xml/' . $sample_file);
		$styledata = $xmlobj->parse();

		if($title === '')
		{
			$title = 'Style ' . time();
		}

		$xml = new vB_XML_Builder();
		$xml->add_group('style', array(
			'name' => $title,
			'vbversion' => vB::getDatastore()->getOption('templateversion'),
			'product' => 'vbulletin',
			'type' => 'custom',
		));
		$xml->add_group('stylevars');

		foreach($styledata['stylevars']['stylevar'] AS $stylevars)
		{
			// The XML Parser outputs 2 values for the value field when one is set as an attribute.
			// The work around for now is to specify the first value (the attribute). In reality
			// the parser shouldn't add a blank 'value' if it exists as an attribute.
			if (!empty($stylevars['colCat']))
			{
				list($group, $nr) = explode('-', $stylevars['colCat']);
				$group = ($group == 'sec' ? 'secondary' : 'primary');
				$stylevars['value'] = '{"color":"#' . $scheme[$group][$nr]['hex'] . '"}';
			}

			$thisValue = json_decode($stylevars['value'], true);

			if (strpos($stylevars['name'], '_border') !== false)
			{
				// @todo, make this inherit the border style & width from the default style?
				$thisValue['width'] = 1;
				$thisValue['units'] = 'px';
				$thisValue['style'] = 'solid';
			}

			$xml->add_tag('stylevar', '', array(
				'name' => htmlspecialchars($stylevars['name']),
				'value' =>  base64_encode(serialize($thisValue)),
			));
		}

		// Close stylevar group
		$xml->close_group();
		// Close style group
		$xml->close_group();

		$doc = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n";
		$doc .= $xml->output();
		$xml = null;
		$imported = $this->library->importStyleFromXML($doc, $title, $parentid, -1, true, $displayorder, $userselect, true);
		$this->buildAllStyles();
		//xml_import_style($doc, -1, $parentid, $title, $anyversion, $displayorder, $userselect, null, null, true);
		return $imported;
	}
}
