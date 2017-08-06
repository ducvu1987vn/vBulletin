<?php

/**
 * vB_Api_Language
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Language extends vB_Api
{
	protected $disableWhiteList = array('fetchAll', 'fetchLanguageSelector');

	protected $styles = array();

	protected function __construct()
	{
		parent::__construct();
	}

	/**
	 * Fetch all languages
	 *
	 * @return array Languages
	 */
	public function fetchAll($languageid = 0, $baseonly = false)
	{
		require_once(DIR . '/includes/adminfunctions_language.php');
		return fetch_languages_array($languageid, $baseonly);
	}

	/**
	 * Simplified version of the fetchAll function, 
	 * required for the language selector in the footer
	 * See VBV-4360
	 *
	 * @return array Languages
	 */
	public function fetchLanguageSelector()
	{
		$languages = array();
		$result = vB::getDbAssertor()->assertQuery('language',
			array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_Db_Query::COLUMNS_KEY => array('languageid', 'title')
			),
			'title'
		);
		foreach ($result as $language)
		{
			$languages[] = $language;
		}
		return $languages;
	}

	/**
	 * Export language as xml
	 *
	 * @param int $languageid Language ID to be exported
	 * @param string $product Product ID. Language of which product to be exported.
	 * @param bool $just_phrases Whether to Just fetch phrases
	 * @param bool $custom Whether to Include Custom Phrases
	 * @param string $charset Export charset
	 * @return string XML data
	 */
	public function export($languageid, $product = 'vbulletin', $just_phrases = false, $custom = false, $charset = 'ISO-8859-1')
	{
		$this->checkHasAdminPermission('canadminlanguages');

		$languageid = intval($languageid);

		require_once(DIR . '/includes/adminfunctions_language.php');
		return get_language_export_xml (
			$languageid,
			$product,
			$custom,
			$just_phrases,
			$charset
		);
	}

	/**
	 * Import a language
	 *
	 * @param string $xml Language xml data
	 * @param int $languageid Language ID to be overwrite. 0 means creating new language
	 * @param string $title Title for Imported Language. Empty means to use the language title specified in the language xml
	 * @param bool $anyversion Whether to Ignore Language Version
	 * @param bool	Allow user-select of imported language
	 * @param bool	Echo output..
	 * @param bool	Read charset from XML header
	 * @return void
	 */
	public function import($xml, $languageid = 0, $title = '', $anyversion = false, $userselect = true, $output = true, $readcharset = false)
	{
		$this->checkHasAdminPermission('canadminlanguages');

		require_once(DIR . '/includes/adminfunctions_language.php');

		xml_import_language($xml, $languageid, $title, $anyversion, $userselect, $output, $readcharset);

		build_language_datastore();
	}

	/**
	 * Insert or update language
	 *
	 * @param array $data Language options to be inserted or updated
	 * @param int $languageid If not 0, the language with the ID will be updated
	 * @return int New language ID or the updated language ID
	 */
	public function save($data, $languageid = 0)
	{
		$this->checkHasAdminPermission('canadminlanguages');

		require_once(DIR . '/includes/adminfunctions.php');
		require_once(DIR . '/includes/adminfunctions_language.php');

		$langglobals = array(
			'title',
			'userselect',
			'options',
			'languagecode',
			'charset',
			'locale',
			'imagesoverride',
			'dateoverride',
			'timeoverride',
			'registereddateoverride',
			'calformat1override',
			'calformat2override',
			'logdateoverride',
			'decimalsep',
			'thousandsep'
		);

		$bf_misc_languageoptions = vB::getDatastore()->get_value('bf_misc_languageoptions');

		require_once(DIR . '/includes/functions_misc.php');
		$data['options'] = convert_array_to_bits($data['options'], $bf_misc_languageoptions);

		$newlang = array();
		foreach($langglobals AS $val)
		{
			$newlang["$val"] =& $data["$val"];
		}

		if (empty($newlang['title']) OR empty($newlang['charset']))
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}

		// User has defined a locale.
		if ($newlang['locale'] != '')
		{
			if (!setlocale(LC_TIME, $newlang['locale']) OR !setlocale(LC_CTYPE, $newlang['locale']))
			{
				throw new vB_Exception_Api('invalid_locale', array($newlang['locale']));
			}

			if ($newlang['dateoverride'] == '' OR $newlang['timeoverride'] == '' OR $newlang['registereddateoverride'] == '' OR $newlang['calformat1override'] == '' OR $newlang['calformat2override'] == '' OR $newlang['logdateoverride'] == '')
			{
				throw new vB_Exception_Api('locale_define_fill_in_all_overrides');
			}
		}

		if (!$languageid)
		{
			/*insert query*/
			$insertdata = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT);
			$insertdata += $newlang;

			$_languageid = vB::getDbAssertor()->assertQuery('language', $insertdata);
			$languageid = $_languageid;

			build_language($languageid);
			build_language_datastore();
		}
		else
		{
			if (empty($data['product']))
			{
				$data['product'] = 'vbulletin';
			}

			$updatelanguage = false;

			if (!empty($data['rvt']))
			{
				$updatelanguage = true;

				vB::getDbAssertor()->assertQuery('phrase', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
					'phraseid' => $data['rvt'],

				));

				// unset reverted phrases
				foreach (array_keys($data['rvt']) AS $varname)
				{
					unset($data['def']["$varname"]);
				}
			}

			if (!empty($data['def']))
			{
				$updaterows = vB::getDbAssertor()->assertQuery('updateLanguagePhrases', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
					'def' => $data['def'],
					'languageid' => $languageid,
					'fieldname' => $data['fieldname'],
				));
			}

			if ($updaterows)
			{
				$updatelanguage = true;
			}

			/* update query */
			$updatedata = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE);
			$updatewhere = array(
				vB_dB_Query::CONDITIONS_KEY => array(
					array(
						'field' => 'languageid',
						'value' => $languageid,
						'operator' => vB_dB_Query::OPERATOR_EQ
					)
				)
			);

			$updatedata += $newlang;
			$updatedata += $updatewhere;
			$updateprincipal = vB::getDbAssertor()->assertQuery('language',$updatedata);

			if ($updatelanguage)
			{
				build_language($languageid);
			}
		}
		return $languageid;
	}

	/**
	 * Delete a language
	 * @param int $languageid Language ID to be deleted
	 * @return void
	 */
	public function delete($languageid)
	{
		$this->checkHasAdminPermission('canadminlanguages');

		require_once(DIR . '/includes/adminfunctions.php');
		require_once(DIR . '/includes/adminfunctions_language.php');

		$options = vB::getDatastore()->get_value('options');
		if ($languageid == $options['languageid'])
		{
			throw new vB_Exception_Api('cant_delete_default_language');
		}
		else
		{
			$languages = vB::getDbAssertor()->getField('language_count', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED));
			if ($languages['total'] == 1)
			{
				throw new vB_Exception_Api('cant_delete_last_language');
			}
			else
			{
				vB::getDbAssertor()->assertQuery('user', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					'languageid' => 0,
					vB_dB_Query::CONDITIONS_KEY => array(
						'languageid' => $languageid,
					)
				));
				vB::getDbAssertor()->assertQuery('session', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					'languageid' => 0,
					vB_dB_Query::CONDITIONS_KEY => array(
						'languageid' => $languageid,
					)
				));
				vB::getDbAssertor()->assertQuery('phrase', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
					'languageid' => $languageid,
				));
				vB::getDbAssertor()->assertQuery('language', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
					'languageid' => $languageid,
				));
				build_language_datastore();
			}
		}

	}

	/**
	 * Rebuld languages
	 * @return void
	 */
	public function rebuild()
	{
		$this->checkHasAdminPermission('canadminlanguages');

		require_once(DIR . '/includes/adminfunctions.php');
		require_once(DIR . '/includes/adminfunctions_language.php');

		$languages = fetch_languages_array();
		foreach($languages AS $_languageid => $language)
		{
			build_language($_languageid);
		}

		build_language_datastore();

	}

	/**
	 * Set language as default language
	 * @param int $languageid Language ID to be set as default
	 * @return void
	 */
	public function setDefault($languageid)
	{
		$this->checkHasAdminPermission('canadminlanguages');

		require_once(DIR . '/includes/adminfunctions.php');
		require_once(DIR . '/includes/adminfunctions_language.php');

		$languageid = intval($languageid);

		vB::getDbAssertor()->assertQuery('setting', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'value' => $languageid,
			vB_dB_Query::CONDITIONS_KEY => array(
				'varname' => 'languageid',
			)
		));

		vB::getDatastore()->build_options();
		$vbulletin->options['languageid'] = $languageid;

		build_language($languageid);
	}
}
