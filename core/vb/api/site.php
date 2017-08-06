<?php
if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * vB_Api_Site
 *
 * @package vBApi
 * @access public
 */

class vB_Api_Site extends vB_Api
{
	protected $disableWhiteList = array('loadHeaderNavbar', 'loadFooterNavbar');

	// Assertor object
	protected $assertor;

	// required fields for site
	protected $fields = array(
		'title' => vB_Cleaner::TYPE_STR,
		'url' => vB_Cleaner::TYPE_STR,
		'usergroups' => vB_Cleaner::TYPE_ARRAY,
		'newWindow' => vB_Cleaner::TYPE_BOOL,
		'subnav' => vB_Cleaner::TYPE_ARRAY,
	);

	// cleaner instance
	protected $cleanerObj;

	protected $sitescache = array();

	/**
	 * Phrases that need to be cached for the navbar/footer items
	 *
	 * @var array
	 */
	protected $requiredPhrases = array();

	/**
	 * Cached phrases used for navbar/footer items
	 *
	 * @var array
	 */
	protected $phraseCache = array();

	/**
	 * Initializes an Api Site object
	 */
	public function __construct()
	{
		parent::__construct();

		$this->assertor = vB::getDbAssertor();
		$this->cleanerObj = new vB_Cleaner();
	}

	/**
	 * Stores the header navbar data.
	 *
	 * @param	int			The storing data siteid.
	 * @param	mixed		Array of elements containing data to be stored for header navbar. Elements might contain:
	 * 			title		--	string		Site title. *required
	 * 			url			--	string		Site url. *required
	 * 			usergroups	--	array		Array of ints.
	 * 			newWindow	--	boolean		Flag used to display site in new window. *required
	 * 			subnav		--	mixed		Array of subnav sites (containing same site data structure).
	 * 				id			--	int		Id of subnav site.
	 * 				title		--	string	Title of subnav site.
	 * 				url			--	string	Url of subnav site.
	 * 				usergroups	--	array	Array of ints.
	 * 				newWindow	--	boolean	Flag used to display subnav site in new window.
	 * 				subnav		--	mixed	Array of subnav sites (containing same site data structure).
	 * @return	boolean		To indicate if save was succesfully done.
	 */
	public function saveHeaderNavbar($siteId, $data)
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		/** Will throw exception if needed */
		$this->validate($siteId);

		/** We expect an array of elements for cleaning */
		$cleanedData = array();
		foreach ($data AS $key => $element)
		{
			$cleanedData[$key] = $this->cleanData($element);
		}

		/** Required fields check */
		$this->hasEmptyData($cleanedData);
		$phrases = array();
		foreach ($cleanedData AS &$element)
		{
			$this->hasRequiredData($element);
			$this->saveNavbarPhrase($element, $phrases);
		}

		/** At this point we can store the data */
		$cleanedData = serialize($cleanedData);

		$queryParams = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE, vB_dB_Query::CONDITIONS_KEY =>
				array(array('field' => 'siteid', 'value' => $siteId)), 'headernavbar' => $cleanedData);
		$response = $this->assertor->assertQuery('vBForum:site', $queryParams);
		
		// reset cache
		unset($this->sitescache);

		return true;

	}

	/**
	 * Stores the footer navbar data.
	 *
	 * @param	int			The storing data siteid.
	 * @param	mixed		Array of data to be stored for footer navbar.
	 * 			title		--	string		Site title.
	 * 			url			--	string		Site url.
	 * 			usergroups	--	array		Array of ints.
	 * 			newWindow	--	boolean		Flag used to display site in new window.
	 * 			subnav		--	mixed		Array of subnav sites (containing same site data structure).
	 * 				id			--	int		Id of subnav site.
	 * 				title		--	string	Title of subnav site.
	 * 				url			--	string	Url of subnav site.
	 * 				usergroups	--	array	Array of ints.
	 * 				newWindow	--	boolean	Flag used to display subnav site in new window.
	 * 				subnav		--	mixed	Array of subnav sites (containing same site data structure).
	 * @return	boolean		To indicate if save was succesfully done.
	 */
	public function saveFooterNavbar($siteId, $data)
	{
		$this->checkHasAdminPermission('canusesitebuilder');

		/** Will throw exception if needed */
		$this->validate($siteId);

		/** We expect an array of elements for cleaning */
		$cleanedData = array();
		foreach ($data AS $key => $element)
		{
			$cleanedData[$key] = $this->cleanData($element);
		}

		/** Required fields check */
		$this->hasEmptyData($cleanedData);
		$phrases = array();
		foreach ($cleanedData AS &$element)
		{
			$this->hasRequiredData($element);
			$this->saveNavbarPhrase($element, $phrases);
		}

		/** At this point we can store the data */
		$cleanedData = serialize($cleanedData);

		$queryParams = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE, vB_dB_Query::CONDITIONS_KEY =>
				array(array('field' => 'siteid', 'value' => $siteId)), 'footernavbar' => $cleanedData);
		$this->assertor->assertQuery('vBForum:site', $queryParams);
		
		// reset cache
		unset($this->sitescache);

		return true;
	}

	/**
	 * Gets the header navbar data
	 *
	 * @param	int		Site id requesting header data.
	 * @param	string		URL
	 * @param	int		Edit mode so allow all links if user can admin sitebuilder
	 *
	 * @return	mixed	Array of header navbar data (Described in save method).
	 */
	public function loadHeaderNavbar($siteId, $url = false, $edit = false)
	{
		return $this->getNavbar('header', $siteId, $url, $edit);
	}

	/**
	 * Gets the footer navbar data
	 *
	 * @param	int		Site id requesting footer data.
	 * @param	string		URL
	 * @param	int		Edit mode so allow all links if user can admin sitebuilder
	 *
	 * @return	mixed	Array of footer navbar data (Described in save method).
	 */
	public function loadFooterNavbar($siteId, $url = false, $edit = false)
	{
		return $this->getNavbar('footer', $siteId, $url, $edit);
	}

	/**
	 * Gets the navbar data for the header or the footer
	 *
	 * @param	int		Site id requesting header/footer data.
	 * @parma	string		URL
	 * @param	int		Edit mode so allow all links if user can admin sitebuilder
	 *
	 * @return	mixed	Array of header/footer navbar data (Described in save method).
	 */
	protected function getNavbar($type, $siteId, $url = false, $edit = false)
	{
		if ($this->disabled)
		{
			return array();
		}

		/** @TODO remove this block when multiple sites are supported */
		if ($siteId != 1)
		{
			throw new vB_Exception_Api("invalid_siteid", array($siteId));
		}

		if (!isset($this->sitescache[$siteId]))
		{
			$queryParams = array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array(array('field' => 'siteid', 'value' => $siteId))
			);
			$this->sitescache[$siteId] = $this->assertor->getRow('vBForum:site', $queryParams);

			if (!empty($url))
			{
				$url = array_shift(explode('?', $url, 2));
			}

			$header = unserialize($this->sitescache[$siteId]['headernavbar']);
			$footer = unserialize($this->sitescache[$siteId]['footernavbar']);

			$this->prepareNavbarData($header, $url, $edit);
			$this->prepareNavbarData($footer, $url, $edit);

			// when editing, phrases need to be loaded from language 0 specifically
			// other language translations can be edited in the Admin CP
			// when not editing, phrases are pulled via the template tag vb:phrase
			if ($edit)
			{
				$this->cachePhrases($edit);
				$this->addPhrasesToData($header);
				$this->addPhrasesToData($footer);
			}

			$this->sitescache[$siteId]['headernavbar_prepared'] = $header;
			$this->sitescache[$siteId]['footernavbar_prepared'] = $footer;
		}

		return $this->sitescache[$siteId][$type . 'navbar_prepared'];
	}

	protected function prepareNavbarData(&$data, $url = false, $edit = false)
	{
		/** @todo this is a hack, create a permanent solution to handle baseurl */
		$baseurl_short = vB_String::parseUrl($_SERVER['x-vb-presentation-base'], PHP_URL_PATH);
		$found_current = false;
		$found_sub_parent = false;
		$removed_element = false;
		$userinfo = vB_User::fetchUserinfo();
		$phraseApi = vB_Api::instance('phrase');
		foreach ($data as $k => &$item)
		{
			if (is_array($item) AND isset($item['url']))
			{
				$item['phrase'] = $item['title'];
				$this->requiredPhrases[] = $item['title'];
				$additionalGrp = false;

				if ($userinfo['membergroupids'] AND !empty($item['usergroups']))
				{
					$memberGroups = explode(',', $userinfo['membergroupids']);
					foreach ($memberGroups as $memberGroup)
					{
						if (in_array($memberGroup, $item['usergroups']))
						{
							$additionalGrp = true;
							break;
						}
					}
				}

				if (
					(!$edit OR !vB::getUserContext()->hasAdminPermission('canusesitebuilder'))
						AND
					(!empty($item['usergroups']) AND (!in_array($userinfo['usergroupid'], $item['usergroups']) AND !$additionalGrp))
				)
				{
					unset($data[$k]);
					$removed_element = true;
					continue;
				}
				$item['isAbsoluteUrl'] = (bool) preg_match('#^https?://#i', $item['url']);
				$item['normalizedUrl'] = ltrim($item['url'], '/');
				if (!empty($item['subnav']) AND is_array($item['subnav']))
				{
					$found_sub = $this->prepareNavbarData($item['subnav'], $url, $edit);
					if (!$found_current AND $found_sub)
					{
						$found_sub_parent = &$item;
						$item['current_sub'] = true;
					}
				}
				if (!$found_current AND !empty($url))
				{
					if ($item['isAbsoluteUrl'])
					{
						$itemUrl = vB_String::parseUrl($item['normalizedUrl'], PHP_URL_PATH);
					}
					else
					{
						$itemUrl = $baseurl_short . '/' . $item['normalizedUrl'];
					}

					if(strtolower($url) == strtolower($itemUrl) || (strlen($url) > strlen($itemUrl) && strtolower(substr($url, -strlen($itemUrl))) == strtolower($itemUrl)))
					{
						$found_current = $item['current'] = true;
					}
				}
			}
		}
		// Reset the keys of the array, because in js it will be considered as an object
		if ($removed_element)
		{
			$data = array_values($data);
		}
		if (!$found_current AND !empty($found_sub_parent))
		{
			$found_sub_parent['current'] = true;
		}
		return $found_current;
	}

	protected function cachePhrases($edit = false)
	{
		if (!empty($this->requiredPhrases))
		{
			// when editing, use the default language phrase
			// translations can be made in the Admin CP.
			// instanceinternal?
			$this->phraseCache = vB_Api::instance('phrase')->fetch($this->requiredPhrases, ($edit ? 0 : null));
			$this->requiredPhrases = array();
		}
	}

	protected function addPhrasesToData(&$data)
	{
		foreach ($data as $k => &$item)
		{
			$item['phrase'] = $item['title'];
			$item['title'] = (isset($this->phraseCache[$item['phrase']]) AND !empty($this->phraseCache[$item['phrase']])) 
				? $this->phraseCache[$item['phrase']] : $item['phrase'];

			if (!empty($item['subnav']) AND is_array($item['subnav']))
			{
				$this->addPhrasesToData($item['subnav']);
			}
		}
	}

	/**
	 * Check if data array is empty
	 *
	 * @param	mixed		Array of site data (described in save methods) to check.
	 *
	 * @throws 	Exception	missing_required_field if there's an empty field in site data.
	 */
	protected function hasEmptyData($data)
	{
		if (empty($data) OR !is_array($data))
		{
			throw new vB_Exception_Api('missing_required_field');
		}

		foreach ($data AS $field => $value)
		{
			//it's O.K. to have empty subnav
			if ((($field === 'subnav') OR ($field === 'usergroups') OR ($field === 'phrase') OR ($field == 'isAbsoluteUrl')) AND (empty($value)))
			{
				continue;
			}

			if (is_array($value))
			{
				$this->hasEmptyData($value);
			}
			else
			{
				//if it's a boolean then empty is O.K.
				if (array_key_exists($field, $this->fields) AND ($this->fields[$field] == vB_Cleaner::TYPE_BOOL))
				{
					continue;
				}

				if (empty($value))
				{
					throw new vB_Exception_Api('missing_required_field');
				}
			}
		}
	}

	/**
	 * Check if data array is empty
	 *
	 * @param	mixed		Array of site data (described in save methods) to check.
	 *
	 * @throws 	Exception	missing_required_field if there's an empty field in site data.
	 */
	protected function hasRequiredData($data)
	{
		foreach ($this->fields as $field => $cleaner)
		{
			//it's O.K. to have empty subnav, usergroups or newWindow
			if (($field != 'subnav') AND ($field != 'usergroups') AND ($field != 'newWindow') AND empty($data[$field]))
			{
				throw new vB_Exception_Api('missing_required_field' );
			}
		}
	}

	/**
	 * Validate site data and perms.
	 *
	 * @siteId	int
	 *
	 * @throws	Exception	Indicating either invalid_siteid or invalid_permissions
	 */
	protected function validate($siteId)
	{
		/** @TODO remove this block when multiple sites are supported */
		if ($siteId != 1)
		{
			throw new vB_Exception_Api("invalid_siteid", array($siteId));
		}

		/** Check user perm */
		if (!vB::getUserContext()->hasAdminPermission('canusesitebuilder'))
		{
			throw new vB_Exception_Api('invalid_permissions');
		}
	}

	protected function cleanData($data)
	{
		/** should be an array data */
		if (!is_array($data))
		{
			throw new vB_Exception_Api('invalid_data');
		}

		foreach ($this->fields as $fieldKey => $fieldVal)
		{
			if (isset($data[$fieldKey]))
			{
				$data[$fieldKey] = $this->cleanerObj->clean($data[$fieldKey], $fieldVal);
			}
		}

		return $data;
	}

	protected function saveNavbarPhrase(&$element, &$phrases)
	{
		if (!isset($element['phrase']) OR empty($element['phrase'])
					OR strpos($element['phrase'], 'navbar_') !==0
					/* we cannot have two different values for the same phrase */
					OR (isset($phrases[$element['phrase']]) AND $phrases[$element['phrase']] != $element['title']))
		{
			$words = explode(' ', $element['title']);
			array_walk($words, 'trim');
			$phrase = strtolower(implode('_', $words));

			//translating some special characters to their latin form
			$phrase = vB_String::latinise($phrase);

			// remove any invalid chars
			$phrase = preg_replace('#[^' . vB_Api_Phrase::VALID_CLASS . ']+#', '', $phrase);

			$phrase = 'navbar_' . $phrase;
			
			$suffix = 0;
			$tmpPhrase = $phrase;
			while (isset($phrases[$tmpPhrase]) AND $phrases[$tmpPhrase] != $element['title'])
			{
				$tmpPhrase = $phrase . (++$suffix); 
			}
			
			$element['phrase'] = $tmpPhrase;
		}
		
		// Store the phrase-value so that we can check
		$phrases[$element['phrase']] = $element['title'];

		$existingPhrases = vB::getDbAssertor()->getRows('phrase', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'varname' => $element['phrase'],
		));

		// don't destroy translations
		$text = array();
		foreach ($existingPhrases as $existingPhrase)
		{
			$text[$existingPhrase['languageid']] = $existingPhrase['text'];
		}
		// the edited phrase
		$text[0] = $element['title'];

		vB_Api::instance('phrase')->save('navbarlinks', $element['phrase'], array(
				'text' => $text,
				'oldvarname' => $element['phrase'],
				'oldfieldname' => 'navbarlinks',
				't' => 0,
				'ismaster' => 0,
				'product' => 'vbulletin'
		));

		// store phrase name instead of title
		$element['title'] = $element['phrase'];
		unset($element['phrase']);

		// do the same for subnavigation
		if (isset($element['subnav']) AND !empty($element['subnav']))
		{
			foreach($element['subnav'] AS &$subnav)
			{
				$this->saveNavbarPhrase($subnav, $phrases);
			}
		}
	}
}

