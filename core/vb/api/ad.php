<?php

/**
 * vB_Api_Ad
 * Advertising API
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Ad extends vB_Api
{
	protected $ad_cache = array();
	protected $ad_name_cache = array();
	protected $max_displayorder = 0;

	protected function __construct()
	{
		parent::__construct();

		// cache all ads
		$this->updateAdCache();
	}

	protected function updateAdCache()
	{
		// cache all ads
		$ad_result = vB::getDbAssertor()->getRows('ad', array(), array(
			'field' => array('displayorder'),
			'direction' => array(vB_dB_Query::SORT_ASC)
		));

		foreach ($ad_result as $ad)
		{
			$this->ad_cache["$ad[adid]"] = $ad;
			$this->ad_name_cache["$ad[adid]"] = $ad['title'];
			if ($ad['displayorder'] > $this->max_displayorder)
			{
				$this->max_displayorder = $ad['displayorder'];
			}
		}
	}

	/**
	 * List Ads of a location
	 *
	 * @param $adlocation
	 *
	 * @return array Ads info
	 */
	public function listAdsByLocation($adlocation)
	{
		$this->checkHasAdminPermission('canadminads');

		$ads = vB::getDbAssertor()->getRows('ad', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'adlocation' => $adlocation
		), false, 'adid');

		if (!$ads)
		{
			return false;
		}

		foreach ($ads as $k => $ad)
		{
			$ads[$k]['criterias'] = vB::getDbAssertor()->getRows('adcriteria', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'adid' => $ad['adid']
			));
		}

		return $ads;
	}

	/**
	 * Fetch Ad by its ID
	 *
	 * @param $adid Ad ID
	 * @throws vB_Exception_Api
	 * @return array Ad data
	 */
	public function fetch($adid)
	{
		$this->checkHasAdminPermission('canadminads');

		$ad = vB::getDbAssertor()->getRow('ad', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'adid' => $adid
		));

		if (!$ad)
		{
			throw new vB_Exception_Api('invalidid');
		}

		$ad['criterias'] = vB::getDbAssertor()->getRows('adcriteria', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'adid' => $ad['adid']
		));

		return $ad;
	}

	/**
	 * Update existing Ad or Save a new Ad
	 *
	 * @param int $adid ID of Ad to be updated. Set to 0 to insert a new Ad
	 * @param array $data Ad data
	 * @return int|mixed Ad ID
	 * @throws vB_Exception_Api
	 */
	public function save($adid, $data)
	{
		$this->checkHasAdminPermission('canadminads');

		$criterion = $data['criteria'];
		if (isset($data['criteria_serialized']) AND $data['criteria_serialized'])
		{
			$criterion = unserialize($data['criteria_serialized']);
		}

		if (!$data['title'])
		{
			throw new vB_Exception_Api('invalid_title_specified');
		}

		$data['displayorder'] = (isset($data['displayorder']) AND $data['displayorder']) ? $data['displayorder'] : '';
		$data['active'] = (isset($data['active']) AND $data['active']) ? intval($data['active']) : 0;

		if ($adid)
		{
			// Update ad record
			vB::getDbAssertor()->update('ad', array(
				'title' => $data['title'],
				'adlocation' => $data['ad_location'],
				'displayorder' => $data['displayorder'],
				'active' => $data['active'],
				'snippet' => $data['ad_html'],
			), array(
				'adid' => $adid
			));

			// delete criteria
			vB::getDbAssertor()->delete('adcriteria', array('adid' => $adid));
		}
		// we are adding a new ad
		else
		{
			// insert ad record
			$adid = vB::getDbAssertor()->insert('ad', array(
				'title' => $data['title'],
				'adlocation' => $data['ad_location'],
				'displayorder' => $data['displayorder'],
				'active' => $data['active'],
				'snippet' => $data['ad_html'],
			));
			if (is_array($adid))
			{
				$adid = array_pop($adid);
			}
			$adid = (int) $adid;
		}

		// update the ad_cache
		$ad = array();
		$ad['adid'] = $adid;
		$ad['adlocation'] = $data['ad_location'];
		$ad['displayorder'] = $data['displayorder'];
		$ad['active'] = $data['active'];
		$ad['snippet'] = $data['ad_html'];
		$this->ad_cache[$adid] = $ad;

		$criteriadata = array();

		foreach ($criterion as $criteriaid => $criteria)
		{
			if (isset($criteria['active']) AND $criteria['active'])
			{
				$criteria += array('condition1' => '', 'condition2' => '', 'condition3' => ''); // Avoid "Undefined index" notice error
				$criteriadata[] = array(
					$adid, $criteriaid, trim($criteria['condition1']), trim($criteria['condition2']), trim($criteria['condition3']),
				);
			}
		}

		if ($criteriadata)
		{
			vB::getDbAssertor()->delete('adcriteria', array('adid' => $adid));
			vB::getDbAssertor()->insertMultiple('adcriteria', array('adid', 'criteriaid', 'condition1', 'condition2', 'condition3'), $criteriadata);
		}

		$template = $this->wrapAdTemplate($this->buildAdTemplate($data['ad_location']), $ad['adlocation']);

		// rebuild previous template if ad has moved locations
		$ad_location_orig = (isset($data['ad_location_orig']) AND $data['ad_location_orig']) ? $data['ad_location_orig'] : '';
		if (!empty($ad_location_orig) AND $ad['adlocation'] != $ad_location_orig)
		{
			$template_orig = $this->wrapAdTemplate($this->buildAdTemplate($ad_location_orig), $ad_location_orig);

			$this->replaceAdTemplate(-1, $ad_location_orig, $template_orig, 'vbulletin');
		}

		$ad_location = $data['ad_location'];

		// The insert of the template.
		$this->replaceAdTemplate(-1, $ad_location, $template, 'vbulletin');

		vB_Api::instanceInternal('style')->buildAllStyles();

		return $adid;
	}

	/**
	 * Save Active status and display orders of multiple Ads
	 *
	 * @param array $data Date to save. In format array(adid => array('active' => $active, 'displayorder' => $displayorder), ...)
	 *
	 * @return bool True on successful
	 */
	public function quickSave($data)
	{
		$this->checkHasAdminPermission('canadminads');

		$updatedadlocations = array();

		foreach ($data as $adid => $value)
		{
			vB::getDbAssertor()->update('ad',
				array(
					'active' => intval($value['active']),
					'displayorder' => intval($value['displayorder']),
				),
				array(
					'adid' => $adid,
				)
			);
		}

		$this->updateAdCache();

		foreach ($data as $adid => $value)
		{
			if ($this->ad_cache[$adid])
			{
				$updatedadlocations[$this->ad_cache[$adid]['adlocation']] = $this->ad_cache[$adid]['adlocation'];
			}
		}

		foreach ($updatedadlocations as $ad_location)
		{
			$template = $this->wrapAdTemplate($this->buildAdTemplate($ad_location), $ad_location);
			$this->replaceAdTemplate(-1, $ad_location, $template, 'vbulletin');
		}

		vB_Api::instanceInternal('style')->buildAllStyles();

		return true;
	}

	public function saveNumberOfHeaderAds($number)
	{
		$this->checkHasAdminPermission('canadminads');

		if ($number > 1)
		{
			$number = 2;
		}
		else
		{
			$number = 1;
		}

		vB_Api::instanceInternal('options')->updateValue('headeradnum', $number);

		return true;
	}

	/**
	 * Delete an AD
	 *
	 * @param int $adid AD ID
	 *
	 */
	public function delete($adid)
	{
		$this->checkHasAdminPermission('canadminads');

		// get ad location
		$adlocation = $this->ad_cache[$adid]['adlocation'];

		// delete criteria
		vB::getDbAssertor()->delete('adcriteria', array('adid' => $adid));

		// delete ad
		vB::getDbAssertor()->delete('ad', array('adid' => $adid));

		// remove record from ad_cache
		unset($this->ad_cache[$adid]);
		$this->ad_cache = array_values($this->ad_cache);

		// rebuild affected template
		$template = $this->buildAdTemplate($adlocation);

		// note: we are skipping the error check this time around because it would not make sense to ask user to check the
		// template if they've already confirmed at other locations that their if conditions are wrong or whatever, and they
		// cannot fix it here.
		$this->replaceAdTemplate(-1, $adlocation, $template, 'vbulletin');

		vB_Api::instanceInternal('style')->buildAllStyles();

		return true;
	}

	/**
	 * Buld Ad Template based on criteria
	 *
	 * @param string $location Template location
	 * @return string Template string
	 */
	protected function buildAdTemplate($location)
	{
		$this->checkHasAdminPermission('canadminads');

		$template = '';
		$vboptions = vB::getDatastore()->getValue('options');

		foreach ($this->ad_cache as $adid => $ad)
		{
			// active ads on the same location only
			if ($ad['active'] AND $ad['adlocation'] == $location)
			{
				$criterion = vB::getDbAssertor()->getRows('adcriteria', array('adid' => $adid));

				// create the template conditionals
				$conditional_prefix = "";
				$conditional_postfix = "";

				// The following code is to make browsing_forum_x and browsing_forum_x_and_children work concurrently. See VBV-4442
				$has_browsing_forum_x = false;
				$has_browsing_forum_x_and_children = false;
				foreach ($criterion as $criteria)
				{
					switch($criteria['criteriaid'])
					{
						case "browsing_forum_x":
							$has_browsing_forum_x = $criteria;
							break;
						case "browsing_forum_x_and_children":
							$has_browsing_forum_x_and_children = $criteria;
							break;
					}
				}

				if ($has_browsing_forum_x AND $has_browsing_forum_x_and_children)
				{
					foreach ($criterion as $k => $criteria)
					{
						if ($criteria['criteriaid'] == 'browsing_forum_x')
						{
							unset($criterion[$k]);
						}
						if ($criteria['criteriaid'] == 'browsing_forum_x_and_children')
						{
							$criterion[$k]['condition2'] = $has_browsing_forum_x['condition1'];
						}
					}
				}

				foreach ($criterion as $criteria)
				{
					switch($criteria['criteriaid'])
					{
						case "in_usergroup_x":
							$conditional_prefix .= '<vb:if condition="is_member_of($' . 'user, ' . $criteria['condition1'] . ')">';
							$conditional_postfix .= "</vb:if>";
							break;
						case "not_in_usergroup_x":
							$conditional_prefix .= '<vb:if condition="!is_member_of($' . 'user, ' . $criteria['condition1'] . ')">';
							$conditional_postfix .= "</vb:if>";
							break;
						case "browsing_content_page":
							if (!empty($criteria['condition1']))
							{
								$conditional_prefix .= '<vb:if condition="!empty($page[\'nodeid\'])">';
							}
							else
							{
								$conditional_prefix .= '<vb:if condition="empty($page[\'nodeid\'])">';
							}
							$conditional_postfix .= "</vb:if>";
							break;
						case "browsing_forum_x":
							$conditional_prefix .= '<vb:if condition="$page[\'channelid\'] == ' . $criteria['condition1'] . '">';
							$conditional_postfix .= "</vb:if>";
							break;
						case "browsing_forum_x_and_children":
							// find out who the children are:
							$channelcontenttypeid = vB_Api::instanceInternal('contenttype')->fetchContentTypeIdFromClass('Channel');
							$nodelib = vB_Library::instance('node');
							$children = $nodelib->listNodes(intval($criteria['condition1']), 1, 100, 0, $channelcontenttypeid, array());
							$childids = array(intval($criteria['condition1']));
							foreach ($children as $child)
							{
								$childids[] = intval($child['nodeid']);
							}
							$conditional_prefix .= '<vb:if condition="in_array($page[\'channelid\'], array(' . implode(',', $childids) . '))';
							if ($criteria['condition2'])
							{
								$conditional_prefix .= ' OR $page[\'channelid\'] == ' . $criteria['condition2'] . '';
							}
							$conditional_prefix .= '">';
							$conditional_postfix .= "</vb:if>";
							break;
						case "style_is_x":
							$conditional_prefix .= '<vb:if condition="STYLEID == ' . intval($criteria['condition1']) . '">';
							$conditional_postfix .= "</vb:if>";
							break;
						case "no_visit_in_x_days":
							$conditional_prefix .= '<vb:if condition="$' . 'user[\'lastactivity\'] < $timenow - (86400*' . intval($criteria['condition1']) . ')">';
							$conditional_postfix .= "</vb:if>";
							break;
						case "no_posts_in_x_days":
							$conditional_prefix .= '<vb:if condition="$' . 'user[\'lastpost\'] < $timenow - (86400*' . intval($criteria['condition1']) . ') AND $user[\'lastpost\'] > 0">';
							$conditional_postfix .= "</vb:if>";
							break;
						case "has_x_postcount":
							$conditional_prefix .= '<vb:if condition="$' . 'user[\'posts\'] > ' . intval($criteria['condition1']) . ' AND $' . 'user[\'posts\'] < ' . intval($criteria['condition2']) . '">';
							$conditional_postfix .= "</vb:if>";
							break;
						case "has_never_posted":
							$conditional_prefix .= '<vb:if condition="$' . 'user[\'posts\'] == 0">';
							$conditional_postfix .= "</vb:if>";
							break;
						case "has_x_reputation":
							$conditional_prefix .= '<vb:if condition="$' . 'user[\'reputation\'] > ' . intval($criteria['condition1']) . ' AND $' . 'user[\'reputation\'] < ' . intval($criteria['condition2']) . '">';
							$conditional_postfix .= "</vb:if>";
							break;
						case "pm_storage_x_percent_full":
							$conditional_prefix .= '<vb:if condition="$' . 'pmboxpercentage = $' . 'user[\'pmtotal\'] / $' . 'user[\'permissions\'][\'pmquota\'] * 100"></vb:if>';
							$conditional_prefix .= '<vb:if condition="$' . 'pmboxpercentage > ' . intval($criteria['condition1']) . ' AND $' . 'pmboxpercentage < ' . intval($criteria['condition2']) . '">';
							$conditional_postfix .= "</vb:if>";
							break;
						case "came_from_search_engine":
							$conditional_prefix .= '<vb:if condition="is_came_from_search_engine()">';
							$conditional_postfix .= "</vb:if>";
							break;
						case "is_date":
							if ($criteria['condition2'])
							{
								$conditional_prefix .= '<vb:if condition="gmdate(\'d-m-Y\', $timenow) == \'' . str_replace("'", "\'", $criteria['condition1']) .'\'">';
								$conditional_postfix .= "</vb:if>";
							}
							else
							{
								$conditional_prefix .= '<vb:if condition="vbdate(\'d-m-Y\', $timenow, false, false) == \'' . str_replace("'", "\'", $criteria['condition1']) .'\'">';
								$conditional_postfix .= "</vb:if>";
							}
							break;
						case "is_time":
							if (preg_match('#^(\d{1,2}):(\d{2})$#', $criteria['condition1'], $start_time) AND preg_match('#^(\d{1,2}):(\d{2})$#', $criteria['condition2'], $end_time))
							{
								if ($criteria['condition3'])
								{
									$conditional_prefix .= '<vb:if condition="$now = gmmktime()"></vb:if>';
									$conditional_prefix .= '<vb:if condition="$end = gmmktime(' . $end_time[1] . ',' . $end_time[2] . ')"></vb:if>';
									$conditional_prefix .= '<vb:if condition="$start = gmmktime(' . $start_time[1] . ',' . $start_time[2] . ')"></vb:if>';
								}
								else
								{
									$conditional_prefix .= '<vb:if condition="$now = mktime()"></vb:if>';
									$conditional_prefix .= '<vb:if condition="$end = mktime(' . $end_time[1] . ',' . $end_time[2] . ')"></vb:if>';
									$conditional_prefix .= '<vb:if condition="$start = mktime(' . $start_time[1] . ',' . $start_time[2] . ')"></vb:if>';
								}
								$conditional_prefix .= '<vb:if condition="$now >= $start AND $now <= $end">';
								$conditional_postfix .= '</vb:if>';
							}
							break;
						case "ad_x_not_displayed":
							// no ad shown? make note of it, and create the array for us
							$conditional_prefix .= '<vb:if condition="$noadshown = !isset($' . 'adsshown)"></vb:if>';
							$conditional_prefix .= '<vb:if condition="$noadshown"><vb:if condition="$' . 'adsshown = array()"></vb:if></vb:if>';
							// if no ads shown, OR ad x have not been shown, show the ad
							$conditional_prefix .= '<vb:if condition="$noadshown OR !in_array(' . intval($criteria['condition1']) . ', $' . 'adsshown)">';
							$conditional_postfix .= '</vb:if>';
							break;
						default:
							break;
					}
				}
				// add a faux conditional before all the closing conditions to mark that we've shown certain ad already
				$conditional_postfix = '<vb:if condition="$' . 'adsshown[] = ' . $adid . '"></vb:if>' . $conditional_postfix;

				// wrap the conditionals around their ad snippet / template
				$template .= $conditional_prefix . $ad['snippet'] . $conditional_postfix;
			}
		}

		return $template;
	}

	/**
	 * Fetch Display Options
	 *
	 * @param int $adid AD ID
	 *
	 * @return array Display Options
	 */
	public function fetchDisplayOptions($adid = 0)
	{
		try
		{
			$this->checkHasAdminPermission('canadminads');
		}
		catch (vB_Exception_Api $e)
		{
			// No permission, return empty array
			return array();
		}

		require_once(DIR . '/includes/adminfunctions.php');

		$criteria_cache = array();
		// TODO: Fetch criteria cache by adid

		$usergroups = vB_Api::instanceInternal('usergroup')->fetchUsergroupList();
		$usergroup_options = array();
		foreach ($usergroups as $usergroup)
		{
			$usergroup_options[$usergroup['usergroupid']] = $usergroup['title'];
		}

		$vbphrase = vB_Api::instanceInternal('phrase')->fetch(array(
			'content', 'non_content', 'user_timezone', 'utc_universal_time'
		));

		$timenow = vB::getRequest()->getTimeNow();

		$forum_chooser_options = construct_forum_chooser_options();

		$criteria_options = array(
			'in_usergroup_x' => array(
				array(
					'type' => 'select',
					'data' => $usergroup_options,
					'default_value' => 2
				)
			),
			'not_in_usergroup_x' => array(
				array(
					'type' => 'select',
					'data' => $usergroup_options,
					'default_value' => 6
				)
			),
			'browsing_content_page' => array(
				array(
					'type' => 'select',
					'data' => array(
				    	'1' => $vbphrase['content'],
				   		'0' => $vbphrase['non_content']
				   	),
					'default_value' => 1
				)
			),
			'browsing_forum_x' => array(
				array(
					'type' => 'select',
					'data' => $forum_chooser_options,
					'default_index' => 0
				)
			),
			'browsing_forum_x_and_children' => array(
				array(
					'type' => 'select',
					'data' => $forum_chooser_options,
					'default_index' => 0
				)
			),
			'no_visit_in_x_days' => array(
				array(
					'type' => 'input',
					'default_value' => 30
				)
			),
			'no_posts_in_x_days' => array(
				array(
					'type' => 'input',
					'default_value' => 30
				)
			),
			'has_x_postcount' => array(
				array(
					'type' => 'input',
					'default_value' => ''
				),
				array(
					'type' => 'input',
					'default_value' => ''
				)
			),
			'has_never_posted' => array(
			),
			'has_x_reputation' => array(
				array(
					'type' => 'input',
					'default_value' => 100
				),
				array(
					'type' => 'input',
					'default_value' => 200
				)
			),
			// Don't remove the following commented code as we may get PM quote feature back in future
//			'pm_storage_x_percent_full' => array(
//				array(
//					'type' => 'input',
//					'default_value' => 90
//				),
//				array(
//					'type' => 'input',
//					'default_value' => 100
//				)
//			),
			'came_from_search_engine' => array(
			),
			'is_date' => array(
				array(
					'type' => 'input',
					'default_value' => vbdate('d-m-Y', $timenow, false, false)
				),
				array(
					'type' => 'select',
					'data' => array(
				    	'0' => $vbphrase['user_timezone'],
				   		'1' => $vbphrase['utc_universal_time']
				   	),
					'default_value' => 0
				)
			),
			'is_time' => array(
				array(
					'type' => 'input',
					'default_value' => vbdate('H:i', $timenow, false, false)
				),
				array(
					'type' => 'input',
					'default_value' => (($h = (intval(vbdate('H', $timenow, false, false)) + 1)) < 10 ? '0' . $h : $h) . vbdate(':i', $timenow, false, false)
				),
				array(
					'type' => 'select',
					'data' => array(
				    	'0' => $vbphrase['user_timezone'],
				   		'1' => $vbphrase['utc_universal_time']
				   	),
					'default_value' => 0
				)
			),
			/*
			* These are flagged for a future version
			'userfield_x_equals_y' => array(
			),
			'userfield_x_contains_y' => array(
			),
			*/
		);

		return array(
			'options' => $criteria_options,
			'cache' => $criteria_cache
		);
	}

	/**
	 * Fetch Display Options HTML
	 *
	 * @param int $adid AD ID
	 *
	 * @return string Display Options HTML
	 */
	public function fetchDisplayoptionsHtml($adid = 0)
	{
		try
		{
			$this->checkHasAdminPermission('canadminads');
		}
		catch (vB_Exception_Api $e)
		{
			// No permission, return empty string
			return '';
		}

		require_once(DIR . '/includes/adminfunctions.php');

		$criteria_cache = array();
		// TODO: Fetch criteria cache by adid

		$usergroups = vB_Api::instanceInternal('usergroup')->fetchUsergroupList();
		$usergroup_options = array();
		foreach ($usergroups as $usergroup)
		{
			$usergroup_options[$usergroup['usergroupid']] = $usergroup['title'];
		}

		$vbphrase = vB_Api::instanceInternal('phrase')->fetch(array(
			'in_usergroup_x_criteria', 'not_in_usergroup_x_criteria', 'browsing_content_page_criteria',
			'content', 'non_content', 'browsing_forum_x_criteria', 'browsing_forum_x_and_children_criteria',
			'no_visit_in_x_days_criteria', 'no_posts_in_x_days_criteria', 'has_x_postcount_criteria',
			'has_never_posted_criteria', 'has_x_reputation_criteria', 'pm_storage_x_percent_full_criteria',
			'came_from_search_engine_criteria', 'is_date_criteria', 'user_timezone', 'utc_universal_time',
			'is_time_criteria', 'select_forum', 'forum_is_closed_for_posting'
		));

		$timenow = vB::getRequest()->getTimeNow();

		$criteria_options = array(
			'in_usergroup_x' => array(
				'<select name="data[criteria][in_usergroup_x][condition1]" tabindex="1">' .
					construct_select_options($usergroup_options, (empty($criteria_cache['in_usergroup_x']) ? 2 : $criteria_cache['in_usergroup_x']['condition1'])) .
				'</select>'
			),
			'not_in_usergroup_x' => array(
				'<select name="data[criteria][not_in_usergroup_x][condition1]" tabindex="1">' .
					construct_select_options($usergroup_options, (empty($criteria_cache['not_in_usergroup_x']) ? 6 : $criteria_cache['not_in_usergroup_x']['condition1'])) .
				'</select>'
			),
			'browsing_content_page' => array(
				'<select name="data[criteria][browsing_content_page][condition1]" tabindex="1">
					<option value="1"' . (empty($criteria_cache['browsing_content_page']['condition1']) ? ' selected="selected"' : '') . '>' . $vbphrase['content'] . '</option>
					<option value="0"' . ($criteria_cache['browsing_content_page']['condition1'] == 0 ? ' selected="selected"' : '') . '>' . $vbphrase['non_content'] . '</option>
				</select>'
			),
			'browsing_forum_x' => array(
				'<select name="data[criteria][browsing_forum_x][condition1]" tabindex="1">' .
					construct_select_options(construct_forum_chooser_options(), $criteria_cache['browsing_forum_x']['condition1']) .
				'</select>'
			),
			'browsing_forum_x_and_children' => array(
				'<select name="data[criteria][browsing_forum_x_and_children][condition1]" tabindex="1">' .
					construct_select_options(construct_forum_chooser_options(), $criteria_cache['browsing_forum_x_and_children']['condition1']) .
				'</select>'
			),
			'no_visit_in_x_days' => array(
				'<input type="text" name="data[criteria][no_visit_in_x_days][condition1]" size="5" class="bginput" tabindex="1" value="' .
					(empty($criteria_cache['no_visit_in_x_days']) ? 30 : intval($criteria_cache['no_visit_in_x_days']['condition1'])) .
				'" />'
			),
			'no_posts_in_x_days' => array(
				'<input type="text" name="data[criteria][no_posts_in_x_days][condition1]" size="5" class="bginput" tabindex="1" value="' .
					(empty($criteria_cache['no_posts_in_x_days']) ? 30 : intval($criteria_cache['no_posts_in_x_days']['condition1'])) .
				'" />'
			),
			'has_x_postcount' => array(
				'<input type="text" name="data[criteria][has_x_postcount][condition1]" size="5" class="bginput" tabindex="1" value="' .
					$criteria_cache['has_x_postcount']['condition1'] .
				'" />',
				'<input type="text" name="data[criteria][has_x_postcount][condition2]" size="5" class="bginput" tabindex="1" value="' .
					$criteria_cache['has_x_postcount']['condition2'] .
				'" />'
			),
			'has_never_posted' => array(
			),
			'has_x_reputation' => array(
				'<input type="text" name="data[criteria][has_x_reputation][condition1]" size="5" class="bginput" tabindex="1" value="' .
					(empty($criteria_cache['has_x_reputation']) ? 100 : $criteria_cache['has_x_reputation']['condition1']) .
				'" />',
				'<input type="text" name="data[criteria][has_x_reputation][condition2]" size="5" class="bginput" tabindex="1" value="' .
					(empty($criteria_cache['has_x_reputation']) ? 200 : $criteria_cache['has_x_reputation']['condition2']) .
				'" />'
			),
			// Don't remove the following commented code as we may get PM quote feature back in future
//			'pm_storage_x_percent_full' => array(
//				'<input type="text" name="data[criteria][pm_storage_x_percent_full][condition1]" size="5" class="bginput" tabindex="1" value="' .
//					(empty($criteria_cache['pm_storage_x_percent_full']) ? 90 : $criteria_cache['pm_storage_x_percent_full']['condition1']) .
//				'" />',
//				'<input type="text" name="data[criteria][pm_storage_x_percent_full][condition2]" size="5" class="bginput" tabindex="1" value="' .
//					(empty($criteria_cache['pm_storage_x_percent_full']) ? 100 : $criteria_cache['pm_storage_x_percent_full']['condition2']) .
//				'" />'
//			),
			'came_from_search_engine' => array(
			),
			'is_date' => array(
				'<input type="text" name="data[criteria][is_date][condition1]" size="10" class="bginput" tabindex="1" value="' .
					(empty($criteria_cache['is_date']['condition1']) ? vbdate('d-m-Y', $timenow, false, false) : $criteria_cache['is_date']['condition1']) .
				'" />',
				'<select name="data[criteria][is_date][condition2]" tabindex="1">
					<option value="0"' . (empty($criteria_cache['is_date']['condition2']) ? ' selected="selected"' : '') . '>' . $vbphrase['user_timezone'] . '</option>
					<option value="1"' . ($criteria_cache['is_date']['condition2'] == 1 ? ' selected="selected"' : '') . '>' . $vbphrase['utc_universal_time'] . '</option>
				</select>'
			),
			'is_time' => array(
				'<input type="text" name="data[criteria][is_time][condition1]" size="5" class="bginput" tabindex="1" value="' .
					(empty($criteria_cache['is_time']['condition1']) ? vbdate('H:i', $timenow, false, false) : $criteria_cache['is_time']['condition1']) .
				'" />',
				'<input type="text" name="data[criteria][is_time][condition2]" size="5" class="bginput" tabindex="1" value="' .
					(empty($criteria_cache['is_time']['condition2']) ? (intval(vbdate('H', $timenow, false, false)) + 1) . vbdate(':i', $timenow, false, false) : $criteria_cache['is_time']['condition2']) .
				'" />',
				'<select name="data[criteria][is_time][condition3]" tabindex="1">
					<option value="0"' . (empty($criteria_cache['is_time']['condition3']) ? ' selected="selected"' : '') . '>' . $vbphrase['user_timezone'] . '</option>
					<option value="1"' . ($criteria_cache['is_time']['condition3'] == 1 ? ' selected="selected"' : '') . '>' . $vbphrase['utc_universal_time'] . '</option>
				</select>'
			),
			/*
			* These are flagged for a future version
			'userfield_x_equals_y' => array(
			),
			'userfield_x_contains_y' => array(
			),
			*/
		);

		$output = '';
		foreach ($criteria_options AS $criteria_option_id => $criteria_option)
		{
			// the criteria options can't trigger the checkbox to change, we need to break out of the label
			$criteria_text = '<label>' . sprintf($vbphrase[$criteria_option_id . '_criteria'],
				"</label>$criteria_option[0]<label>",
				"</label>$criteria_option[1]<label>",
				"</label>$criteria_option[2]<label>"
			) . '</label>';

			$criteria_text = str_replace('<label>', "<label for=\"cb_$criteria_option_id\">", $criteria_text);

			$output .=	"<div class=\"optionrow\"><input type=\"checkbox\" id=\"cb_$criteria_option_id\" tabindex=\"1\" name=\"data[criteria][$criteria_option_id][active]\" title=\"$vbphrase[criterion_is_active]\" value=\"1\"" . (empty($criteria_cache["$criteria_option_id"]) ? '' : ' checked="checked"') . " />" .
				"<span id=\"span_$criteria_option_id\">$criteria_text</span></div>";

		}

		return $output;

	}

	/**
	 * Function to wrap ad template in a div with the correct id
	 *
	 * @param string $template Template String
	 * @param string $id_name Ad location (global_header1)
	 * @param string $id_prefix ID Prefix (Default: 'ad_')
	 *
	 * @return string Wrapped AD Template
	 */
	protected function wrapAdTemplate($template, $id_name, $id_prefix='ad_')
	{
		if (!$template)
		{
			return '';
		}

		// wrap the template in a div with the correct id
		$template_wrapped = '<div class="' . $id_prefix . $id_name . '_inner">' . $template . '</div>';

		return $template_wrapped;
	}

	/**
	* Function to replace ad code into correct template
	*
	* @param string $styleid Style for template
	* @param string $location Ad location
	* @param string $template Template compiled
	* @param string $template_un Template uncompiled
	* @param string $username Username for the edit
	* @param string $templateversion Version of the template
	* @param string $product Product that uses this template
	*/
	protected function replaceAdTemplate($styleid, $location, $template, $product='vbulletin')
	{
//		vB::getDbAssertor()->assertQuery('ad_replaceadtemplate', array(
//			'styleid' => $styleid,
//			'title' => 'ad_' . $location,
//			'template' => $template,
//			'template_un' => $template_un,
//			'timenow' => vB::getRequest()->getTimeNow(),
//			'username' => $username,
//			'templateversion' => $templateversion,
//			'product' => $product,
//		));
		$templateapi = vB_Api::instanceInternal('template');

		// Try to insert the template
		try
		{
			$templateapi->insert($styleid, 'ad_' . $location, $template, $product);
		}
		catch (vB_Exception_Api $e)
		{
			$templateid = $templateapi->getTemplateID('ad_' . $location, $styleid);
			$templateapi->update($templateid, 'ad_' . $location, $template, $product, false, false, '');
		}

	}
}
