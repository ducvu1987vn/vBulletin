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
 * vB_Library_Style
 *
 * @package vBApi
 * @access public
 */
class vB_Library_Style extends vB_Library
{
	private $stylecache = array();
	private $stylesById = array();

	private $stylevarcache = array();

	/**
	 * Contains styles that were forced in a channel
	 * @var array
	 */
	protected $forcedStyles = NULL;

	protected function __construct()
	{
	}

	/**
	 * This is intended to be used in tests, to be able to refresh the cache
	 */
	public function resetForcedStyles()
	{
		$this->forcedStyles = NULL;
	}

	/**
	 * Adds missing stylevars to a style array
	 * @param array $styles
	 */
	protected function addStylevars(&$styles)
	{
		$pending = array();
		foreach($styles AS $key => $style)
		{
			if (isset($style['styleid']) AND (!isset($style['newstylevars']) OR empty($style['newstylevars'])) AND !isset($this->stylevarcache[$style['styleid']]))
			{
				$pending[intval($style['styleid'])] =& $styles[$key];
			}
		}

		$result = null;
		if ($pending)
		{
			$result = vB::getDbAssertor()->assertQuery('style', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array('styleid' => array_keys($pending)),
				vB_Db_Query::COLUMNS_KEY => array('styleid', 'newstylevars')
			));
		}

		if ($result)
		{
			foreach($result AS $style)
			{
				$this->stylevarcache[$style['styleid']] = $pending[intval($style['styleid'])]['newstylevars'] = $style['newstylevars'];
			}
		}

		// Add cache back
		foreach ($styles as $k => $style)
		{
			if (isset($style['styleid']))
			{
				if (isset($this->stylevarcache[$style['styleid']]))
				{
					$styles[$k]['newstylevars'] = $this->stylevarcache[$style['styleid']];
				}
			}
		}
	}

	/**
	 * Returns a valid style to be used from the candidates
	 *
	 * @param array $stylePreference - Style candidates ordered by preference
	 * @return int
	 */
	public function getValidStyleFromPreference($stylePreference)
	{
		$styleId = FALSE;

		if (is_array($stylePreference) AND !empty($stylePreference))
		{
			// fetch info and verify styles exist
			$styles = vB::getDatastore()->getValue('stylecache');
			if (!$styles)
			{
				//if we don't have a datastore value, init from the database.
				$styles = array();
				$result = vB::getDbAssertor()->assertQuery('style', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'styleid', 'value' => array_map('intval', $stylePreference))
					),
					vB_Db_Query::COLUMNS_KEY => array('styleid', 'userselect')
				));
				foreach ($result as $style)
				{
					$styles[$style['styleid']] = $style;
				}
			}

			reset($stylePreference);
			$style = current($stylePreference);
			while ($style !== FALSE AND $styleId === FALSE)
			{
				if (isset($styles[$style]))
				{
					if ($styles[$style]['userselect'] OR vB::getUserContext()->isAdministrator())
					{
						$styleId = $styles[$style]['styleid'];
					}
					else
					{
						/* We cannot be certain that the user is actually looking at a specific channel,
						so if a user doesn't have permission for certain style and that style is among
						the forced ones, we still let the user get it. This will reduce the window for
						spoofing. */
						if ($this->forcedStyles === NULL)
						{
							$this->forcedStyles = array();

							$result = vB::getDbAssertor()->assertQuery('vBForum:fetchForcedStyles', array(
								'styles' => array_keys($styles)
							));
							foreach($result as $st)
							{
								$this->forcedStyles[] = $st['styleid'];
							}
						}

						if (in_array($styles[$style]['styleid'], $this->forcedStyles))
						{
							$styleId = $styles[$style]['styleid'];
						}
					}
				}

				// go to next style candidate
				$style = next($stylePreference);
			}
		}

		return ($styleId !== FALSE) ? $styleId : 0;
	}

	/**
	 * Loads style information (selected style and style vars)
	 *
	 * This is different from fetchStyleByID(). The style fetched
	 * by this method may not be the style specified in $styleid parameter.
	 *
	 * If the style with $styleid doesn't allow user to use (The user isn't admin either),
	 * default style specified in vBulletin Settings will be returned.
	 *
	 *
	 * @param int $styleid
	 *
	 * @return array Style information.
	 */
	public function fetchStyleRecord($styleid, $nopermissioncheck = false)
	{
		$userContext = vB::getUserContext();

		//This gets called if we have an error during initialization, and we want to display something useful
		if (!empty($userContext) AND is_object($userContext))
		{
			$isAdmin = $userContext->hasAdminPermission('cancontrolpanel');
		}
		else
		{
			$isAdmin = false;
		}
		$thisStyle = null;

		if (isset($this->stylesById[$styleid]))
		{
			$thisStyle = $this->stylesById[$styleid];
			if($isAdmin || $thisStyle['userselect'] || $nopermissioncheck)
			{
				return $thisStyle;
			}
		}

		$options = vB::getDatastore()->getValue('options');
		$defaultStyleId =  $options['styleid'];

		$defaultStyle = null;
		if (isset($this->stylesById[$defaultStyleId]))
		{
			$defaultStyle = $this->stylesById[$defaultStyleId];

			//intentionally checking $thisStyle here.  If we don't have thisStyle, then we need to
			//look it up (below) because we may have a valid style that we just haven't loaded yet.
			//if we have $thisStyle then we've already looked at it and rejected it.
			if ($thisStyle)
			{
				return $defaultStyle;
			}
		}

		$conditions = array();
		if (!$isAdmin AND !$nopermissioncheck)
		{
			$conditions['userselect'] = 1;
		}

		//reset and reload
		$thisStyle = null;
		$defaultStyle = null;
		$result = array();
		$stylecache = vB::getDatastore()->getValue('stylecache');
		if (empty($conditions['userselect']))
		{
			//determine which styles we need to look up.
			$styleids = array();
			if (!$thisStyle)
			{
				$styleids[] = $styleid;
			}

			//if we have the default style we don't need to query for it again.
			if (!$defaultStyle)
			{
				$styleids[] = $defaultStyleId;
			}
			$conditions['styleid'] = $styleids;

			foreach ($stylecache as $style)
			{
				if (in_array($style['styleid'], $styleids))
				{
					if ((!empty($conditions['userselect']) AND !empty($style['userselect'])) OR empty($conditions['userselect']))
					{
						$result[] = $style;
					}
				}
			}
		}
		else
		{
			foreach ($stylecache as $style)
			{
				if (($style['styleid'] == $styleid AND $style['userselect']) OR $style['styleid'] == $defaultStyleId)
				{
					$result[] = $style;
				}
			}
		}

		foreach($result as $style)
		{
			//I'm not sure why this serialized seperately from the rest of the style array, but it is.
			$style['templatelist'] = unserialize($style['templatelist']);
			$this->stylesById[$style['styleid']] = $style;
			if ($style['styleid'] == $styleid)
			{
				$thisStyle = $style;
			}
			else if($style['styleid'] == $defaultStyleId)
			{
				$defaultStyle = $style;
			}
		}

		if ($thisStyle)
		{
			if($isAdmin || $thisStyle['userselect'] || $nopermissioncheck)
			{
				$tmp = array(&$thisStyle);
				$this->addStylevars($tmp);
				return $thisStyle;
			}
		}

		if ($defaultStyle)
		{
			$tmp = array(&$defaultStyle);
			$this->addStylevars($tmp);
			return $defaultStyle;
		}

		//if we don't have anything.
		return false;
	}

	/**
	 * Import style from XML Data
	 *
	 * @param string $xmldata XML Data to be imported as style.
	 * @param string $title Style title.
	 * @param integer $parentid Parent style ID.
	 * @param integer $overwritestyleid Style ID to be overwritten.
	 * @param boolean $anyversion Whether to ignore style version.
	 * @param integer $displayorder Style display order.
	 * @param boolean $userselect Whether the style allows user selection.
	 */
	public function importStyleFromXML($xmldata, $title, $parentid, $overwritestyleid, $anyversion, $displayorder, $userselect, $scilent = false)
	{
		require_once(DIR . '/includes/adminfunctions_template.php');
		$imported = xml_import_style($xmldata,
			$overwritestyleid, $parentid, $title,
			$anyversion, $displayorder, $userselect,
			null, null, $scilent
		);

		return $imported;
	}

	/**
	 * Import style from Server File
	 *
	 * @param string $serverfile Server file name to be imported.
	 * @param string $title Style title.
	 * @param integer $parentid Parent style ID.
	 * @param integer $overwritestyleid Style ID to be overwritten.
	 * @param boolean $anyversion Whether to ignore style version.
	 * @param integer $displayorder Style display order.
	 * @param boolean $userselect Whether the style allows user selection.
	 */
	public function importStyleFromServer($serverfile, $title, $parentid, $overwritestyleid, $anyversion, $displayorder, $userselect)
	{
		require_once(DIR . '/includes/adminfunctions.php');

		if (file_exists($serverfile))
		{
			$xml = file_read($serverfile);
		}
		else
		{
			throw new vB_Exception_Api('no_file_uploaded_and_no_local_file_found_gerror');
		}

		return $this->importStyleFromXML($xml, $title, $parentid, $overwritestyleid, $anyversion, $displayorder, $userselect);
	}

	/**
	 * Returns an array of all styles that are parents to the style specified
	 *
	 * @param	integer	Style ID
	 *
	 * @return	array
	 */
	public function fetchTemplateParentlist($styleid)
	{
		static $ts_arraycache = array();
		if (empty($styleid))
		{
			return '';
		}

		if (isset($ts_arraycache["$styleid"]))
		{
			return $ts_arraycache["$styleid"];
		}

		$ts_info = vB::getDbAssertor()->getRow('style', array('styleid' => $styleid));
		$ts_array = $styleid;

		if ($ts_info['parentid'] >= 0)
		{
			$parentlist = $this->fetchTemplateParentlist($ts_info['parentid']);
			if (!empty($parentlist))
			{
				$ts_array .= ',' . $parentlist;
			}
		}

		if (substr($ts_array, -2) != '-1')
		{
			$ts_array .= ',-1';
		}
		return $ts_arraycache["$styleid"] = $ts_array;
	}

	/**
	 * Fetch style information by its ID.
	 *
	 * @param integer $styleid Style ID.
	 * @param bool abort or return empty array ?
	 *
	 * @return array style information
	 */
	public function fetchStyleByID($styleid, $abort = true)
	{
		$this->stylecache = vB::getDatastore()->getValue('stylecache');
		if(isset($this->stylecache[$styleid]))
		{
			$this->addStylevars($this->stylecache);
			return $this->stylecache[$styleid];
		}
		if (!isset($this->stylesById[$styleid]))
		{
			$style = vB::getDbAssertor()->getRow('style', array('styleid' => $styleid));
			if (!$style)
			{
				if (!$abort)
				{
					return array();
				}
				else
				{
					throw new vB_Exception_Api('invalidid', array('styleid'));
				}
			}
			$this->stylesById[$styleid] = $style;
		}

		return $this->stylesById[$styleid];
	}

	/**
	 * Builds the $stylecache array
	 *
	 * This is a recursive function - call it with no arguments
	 *
	 * @param boolean $styleid Style ID to start with
	 * @param integer $depth Current depth
	 * @return none
	 */
	private function cacheStyles($styleid = -1, $depth = 0)
	{
		static $cache = array();
		static $loaded = array();

		//the cache appears to be for the benefit of the recursive calls.  We'll reset if called
		//from the top to avoid problems if we need to regenerate the cache after a change
		//(mostly for the unit tests).
		if ($styleid == -1)
		{
			$cache = array();
			$loaded = array();
		}

		$vboptions = vB::getDatastore()->getValue('options');

		// check to see if we have already got the results from the database
		if (empty($cache))
		{
			$counter = 0;
			$styles = vB::getDbAssertor()->assertQuery('style', array(
				// VBV-4174: excluding csscolors, css and stylevars since they are deprecated
				vB_dB_Query::COLUMNS_KEY => array('styleid','parentid', 'title',
					'parentlist','templatelist','newstylevars', 'replacements',
					'editorstyles', 'userselect', 'displayorder', 'dateline'),
			), 'displayorder');

			foreach ($styles as $style)
			{
				if (!empty($loaded[$style['styleid']]))
				{
					continue;
				}

				if (trim($style['parentlist']) == '')
				{
					$parentlist = $this->fetchTemplateParentlist($style['styleid']);
					vB::getDbAssertor()->assertQuery('vBForum:updatestyleparent', array(
							'parentlist' => $parentlist,
							'styleid' => intval($style['styleid']),
					));
					$style['parentlist'] = $parentlist;
				}

				if (trim($style['templatelist']) == '')
				{
					$style['templatelist'] = $this->buildTemplateIdCache($style['styleid'], true, $style['parentlist']);
					vB::getDbAssertor()->assertQuery('vBForum:updatestyletemplatelist', array(
							'templatelist' => $style['templatelist'],
							'styleid' => intval($style['styleid']),
					));
				}

				// If a style is a default style, we need to make sure user can select it.
				if ($style['styleid'] == $vboptions['styleid'])
				{
					$style['userselect'] = 1;
				}

				$loaded[$style['styleid']] = true;
				$cache[$style['parentid']][$style['displayorder']][$style['styleid']] = $style;
				$counter ++;
			}

			foreach ($cache as $parentid => &$styles)
			{
				ksort($styles);
			}

			if (!defined('STYLECOUNT'))
			{
				define('STYLECOUNT', $counter);
			}
		}

		// database has already been queried
		if (!empty($cache["$styleid"]) AND is_array($cache["$styleid"]))
		{
			foreach ($cache["$styleid"] AS $holder)
			{
				foreach ($holder AS $style)
				{
					$this->stylecache["$style[styleid]"] = $style;
					$this->stylecache["$style[styleid]"]['depth'] = $depth;
					$this->cacheStyles($style['styleid'], $depth + 1, false);
				}
			}
		}
	}

	/**
	 * Fetch All styles
	 *
	 * @param bool $nocache Refresh Styles from database
	 * @param bool $fetchStylevars if true it will return stylevars for each style
	 *
	 * @return array All styles' information
	 */
	public function fetchStyles($nocache = false, $fetchStylevars = true)
	{
		if ($nocache)
		{
			$this->stylecache = array();

			// this will fetch the stylevars from db
			$this->cacheStyles();
		}
		elseif (empty($this->stylecache))
		{
			$this->stylecache = vB::getDatastore()->getValue('stylecache');

			if ($fetchStylevars)
			{
				$this->addStylevars($this->stylecache);
			}
		}
		return $this->stylecache;
	}

	/**
	 * Clear style in class cache.
	 * Needed for unit testing
	 *
	 */
	public function clearStyleCache()
	{
		$this->stylecache = array();
		$this->stylesById = array();
		$this->stylevarcache = array();
	}

	/**
	 * Checks if a styleid is valid
	 *
	 * @param int $styleid
	 * @return bool
	 */
	public function validStyle($styleid)
	{
		$this->fetchStyles(false, false);

		return isset($this->stylecache[$styleid]);
	}


	/**
	 *	Switch the style for rendering
	 *	This really should be part of the bootstrap code except:
	 *	1) We don't actually load the bootstrap in the admincp
	 * 2) There is a lot to the style load that isn't easy to redo (header/footer templates for example)
	 *
	 * This handles the stylevars and template lists -- including reloading the template cache.
	 * This is enough to handle the css template rendering, but probably won't work for anything
	 * more complicated.
	 */
	function switchCssStyle($styleid, $templates)
	{
		global $vbulletin;
		
		$style = $this->fetchStyleByID($styleid);

		if (empty($style))
		{
			return false;
		}

		$this->cacheStyles();

		$vbulletin->stylevars = unserialize($style['newstylevars']);
		fetch_stylevars($style, vB::getCurrentSession()->fetch_userinfo());

		//clear the template cache, otherwise we might get old templates
		vB_Api::instanceInternal('template')->cacheTemplates($templates, $style['templatelist'], false, true);
	}

	/**
	* Fetches a list of template IDs for the specified style
	* @deprecated
	* @param	integer	Style ID
	* @param	boolean	If true, returns a list of template ids; if false, goes ahead and runs the update query
	* @param	mixed	A comma-separated list of style parent ids (if false, will query to fetch the list)
	*
	* @return	mixed	Either the list of template ids, or nothing
	*/
	public function buildTemplateIdCache($styleid, $doreturn = false, $parentids = false)
	{
		if ($styleid == -1)
		{
			// doesn't have a cache
			return '';
		}

		//this is done as an array for historical reasons
		if ($parentids == 0)
		{
			$style['parentlist'] = $this->fetchTemplateParentlist($styleid);
		}
		else
		{
			$style['parentlist'] = $parentids;
		}

		$parents = explode(',', $style['parentlist']);
		$totalparents = sizeof($parents);

		$bbcodestyles = array();
		$templatelist = array();
		$assertor = vB::getDbAssertor();
		$templates = $assertor->assertQuery('vBForum:fetchTemplateIdsByParentlist', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'parentlist' => $style['parentlist'],
		));
		foreach ($templates as $template)
		{
			for ($tid = $totalparents; $tid > 0; $tid--)
			{
				if ($template["templateid_$tid"])
				{
					$templatelist["$template[title]"] = $template["templateid_$tid"];
					if (preg_match('#^bbcode_[code|html|php|quote]+$#si', trim($template['title'])))
					{
						$bbcodetemplate = $template['title'] . '_styleid';
						if ($template["styleid_$tid"])
						{
							$templatelist["$bbcodetemplate"] = $template["styleid_$tid"];
						}
						else
						{
							$templatelist["$bbcodetemplate"] = -1;
						}
					}
					break;
				}
			}
		}

		$customdone = array();
		$customtemps = $assertor->assertQuery('vBForum:fetchCustomtempsByParentlist', array(
				'parentlist' => $style['parentlist'],
		));

		foreach ($customtemps as $template)
		{
			if ($customdone["$template[title]"])
			{
				continue;
			}
			$customdone["$template[title]"] = 1;
			$templatelist["$template[title]"] = $template['templateid'];

			if (preg_match('#^bbcode_[code|html|php|quote]+$#si', trim($template['title'])))
			{
				$bbcodetemplate = $template['title'] . '_styleid';
				$templatelist["$bbcodetemplate"] = $template['styleid'];
			}
		}

		$templatelist = serialize($templatelist);

		if (!$doreturn)
		{
			$assertor->update(
					'template',
					array('templatelist' => $templatelist),
					array('styleid' => $styleid)
			);
		}
		else
		{
			return $templatelist;
		}
	}

	/**
	* Resets the css cachebuster date.
	*/
	public function setCssDate()
	{
		$options = vB::getDatastore()->getValue('miscoptions');
		$options['cssdate'] = vB::getRequest()->getTimeNow();
		vB::getDatastore()->build('miscoptions', serialize($options), 1);
	}

	/**
	* Rebuild the style datastore.
	*/
	public function buildStyleDatastore()
	{
		$this->setCssDate();
		$stylecache = $this->fetchStyles(true, false);

		foreach($stylecache AS $key => $style)
		{
			// VBV-4174: we don't want stylevars in the datastore
			if (isset($style['newstylevars']))
			{
				unset($stylecache[$key]['newstylevars']);
			}
		}

		$list = unserialize($stylecache[1]['templatelist']);
		
		vB::getDatastore()->build('stylecache', serialize($stylecache), 1);
		return $stylecache;
	}

	//@TODO port build_style to this class
	//just a cover for now so we can more easily port the internal function
	public function buildStyle($styleid, $title, $actions, $resetcache = false)
	{
		//clear the local cache if we're rebuilding the style.
		return build_style($styleid, $title, $actions, '', '', $resetcache, false);
	}

}
