<?php

/**
 * vB_Api_Phrase
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Phrase extends vB_Api
{
	const VALID_CLASS = 'A-Za-z0-9_\[\]';

	protected $disableWhiteList = array('fetch');

	protected $styles = array();
	protected $phrasecache = array();

	protected function __construct()
	{
		parent::__construct();
	}

	/**
	 * Fetch phrases
	 *
	 * @param array $phrases An array of phrase ID to be fetched
	 * @param int $languageid Language ID. If not set, it will use current session's languageid

	 * @return array Phrase' texts
	 */
	public function fetch($phrases, $languageid = NULL)
	{
		if (empty($phrases))
		{
			return array();
		}

		if (!is_array($phrases))
		{
			$phrases = array($phrases);
		}

		if ($languageid === NULL)
		{
			$currentsession = vB::getCurrentSession();
			if ($currentsession)
			{
				$languageid = $currentsession->get('languageid');
				if (!$languageid)
				{
					$userinfo = vB::getCurrentSession()->fetch_userinfo();
					$languageid = $userinfo['languageid'];
				}
			}

			// Still no languageid, try to get current default languageid
			if (!$languageid)
			{
				$languageid = vB::getDatastore()->getOption('languageid');
			}

			// Still don't have a language, fall back to master language
			if (!$languageid)
			{
				$languageid = -1;
			}
		}

		// Unset phrases which have already been fetched
		$cachedphrasevars = array_keys($this->phrasecache);
		$phrasestofetch = array();
		foreach ($phrases as $phrasevar)
		{
			if (!in_array($phrasevar, $cachedphrasevars))
			{
				$phrasestofetch[] = $phrasevar;
			}
		}

		$phrasesdata = array();
		if (!empty($phrasestofetch))
		{
			$phrasesdata = vB::getDbAssertor()->getRows('phrase', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'varname' => $phrasestofetch,
				'languageid' => array($languageid, 0, -1),
			));
		}

		$realphrases = array();
		foreach ($phrasesdata as $phrase)
		{
			// User-selected language (>=1) overwrites custom phrase (0), which overwrites master language phrase (-1)
			if (empty($realphrases[$phrase['varname']]) OR $realphrases[$phrase['varname']]['languageid'] < $phrase['languageid'] )
			{
				$realphrases[$phrase['varname']] = $phrase;
			}
		}

		$this->phrasecache = array_merge($this->phrasecache, $realphrases);

		foreach ($phrases as $phrasevar)
		{
			if (empty($realphrases[$phrasevar]) AND !empty($this->phrasecache[$phrasevar]))
			{
				$realphrases[$phrasevar] = $this->phrasecache[$phrasevar];
			}
		}

		$return = array();
		foreach ($realphrases as $phrase)
		{
			// TODO: store this somewhere? -- might as well store phrases converted now to
			// stop all this real time conversion
			if (strpos($phrase['text'], '{1}') !== false)
			{

				$search = array(
					'/%/s',
					'/\{([0-9]+)\}/siU',
				);
				$replace = array(
					'%%',
					'%\\1$s',
				);
				$return[$phrase['varname']] = preg_replace($search, $replace, $phrase['text']);
			}
			else
			{
				$return[$phrase['varname']] = $phrase['text'];
			}
		}

		return $return;
	}

	/**
	 * Fetch orphan phrases
	 * @return array Orphan phrases
	 */
	public function fetchOrphans()
	{
		$this->checkHasAdminPermission('canadminlanguages');

		$phrases = vB::getDbAssertor()->getRows('phrase_fetchorphans', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
		));

		return $phrases;
	}

	/**
	 * Process orphan phrases
	 * @param array $del Orphan phrases to be deleted. In format array('varname@fieldname')
	 * @param array $keep Orphan phrases to be kept
	 * @return void
	 */
	public function processOrphans($del, $keep)
	{
		$this->checkHasAdminPermission('canadminlanguages');

		require_once(DIR . '/includes/adminfunctions_language.php');

		if ($del)
		{
			vB::getDbAssertor()->assertQuery('deleteOrphans', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'del' => $del,
			));
		}

		if ($keep)
		{
			vB::getDbAssertor()->assertQuery('keepOrphans', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'keep' => $keep,
			));
		}
	}

	/**
	 * Find custom phrases that need updating
	 * @return array Updated phrases
	 */
	public function findUpdates()
	{
		require_once(DIR . '/includes/adminfunctions_template.php');
		require_once(DIR . '/includes/adminfunctions.php');
		$full_product_info = fetch_product_list(true);
		// query custom phrases
		$customcache = array();
		$phrases = vB::getDbAssertor()->getRows('phrase_fetchupdates', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
		));

		foreach ($phrases as $phrase)
		{
			if ($phrase['globalversion'] == '')
			{
				// No version on the global phrase. Wasn't edited in 3.6,
				// can't tell when it was last edited. Skip it.
				continue;
			}

			if ($phrase['customversion'] == '' AND $phrase['globalversion'] < '3.6')
			{
				// don't know when the custom version was last edited,
				// and the global was edited before 3.6, so we don't know what's newer
				continue;
			}

			if (!$phrase['product'])
			{
				$phrase['product'] = 'vbulletin';
			}

			$product_version = $full_product_info["$phrase[product]"]['version'];

			if (is_newer_version($phrase['globalversion'], $phrase['customversion']))
			{
				$customcache["$phrase[languageid]"]["$phrase[phraseid]"] = $phrase;
			}
		}

		return $customcache;
	}

	/**
	 * Search phrases
	 * @param array $criteria Criteria to search phrases. It may have the following items:
	 *              'searchstring'	=> Search for Text
	 *              'searchwhere'	=> Search in: 0 - Phrase Text Only, 1 - Phrase Variable Name Only, 2 - Phrase Text and  Phrase Variable Name
	 *              'casesensitive' => Case-Sensitive 1 - Yes, 0 - No
	 *              'exactmatch'	=> Exact Match 1 - Yes, 0 - No
	 *              'languageid'	=> Search in Language. The ID of the language
	 *              'phrasetype'	=> Phrase Type. Phrase group IDs to search in.
	 *              'transonly'		=> Search Translated Phrases Only  1 - Yes, 0 - No
	 *              'product'		=> Product ID to search in.
	 *
	 * @return array Phrases
	 */
	public function search($criteria)
	{
		if ($criteria['searchstring'] == '')
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}

		$phrases = vB::getDbAssertor()->getRows('searchPhrases', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'criteria' => $criteria,
		));

		if (empty($phrases))
		{
			return array();
		}

		$phrasearray = array();
		foreach ($phrases as $phrase)
		{
			// check to see if the languageid is already set
			if ($criteria['languageid'] > 0 AND isset($phrasearray["$phrase[fieldname]"]["$phrase[varname]"]["{$criteria['languageid']}"]))
			{
				continue;
			}
			$phrasearray["{$phrase['fieldname']}"]["{$phrase['varname']}"]["{$phrase['languageid']}"] = $phrase;
		}

		return $phrasearray;
	}

	/**
	 * Find and replace phrases in languages
	 *
	 * @param array $replace A list of phrase ID to be replaced
	 * @param string $searchstring Search string
	 * @param string $replacestring Replace string
	 * @param int $languageid Language ID
	 * @return void
	 */
	public function replace($replace, $searchstring, $replacestring, $languageid)
	{
		if (empty($replace))
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}

		$userinfo = vB::getCurrentSession()->fetch_userinfo();

		require_once(DIR . '/includes/adminfunctions.php');
		$full_product_info = fetch_product_list(true);

		$phrases = vB::getDbAssertor()->assertQuery('phrase', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'phraseid' => $replace
		));

		$products =array();

		foreach ($phrases as $phrase)
		{
			$phrase['product'] = (empty($phrase['product']) ? 'vbulletin' : $phrase['product']);
			$phrase['text'] = str_replace($searchstring, $replacestring, $phrase['text']);

			if ($phrase['languageid'] == $vbulletin->GPC['languageid'])
			{ // update
				vB::getDbAssertor()->assertQuery('phrase', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
					'text' => $phrase['text'],
					'username' => $userinfo['username'],
					'datetime' => vB::getRequest()->getTimeNow(),
					'version' => $full_product_info["$phrase[product]"]['version'],
					vB_dB_Query::CONDITIONS_KEY => array(
						'phraseid' => $phrase['phraseid']
					)
				));
			}
			else
			{ // insert
				/*insert query*/
				vB::getDbAssertor()->assertQuery('phrase_replace', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
					'languageid' => $languageid,
					'varname' => $phrase['varname'],
					'text' => $phrase['text'],
					'fieldname' => $phrase['fieldname'],
					'product' => $phrase['product'],
					'username' => $userinfo['username'],
					'datetime' => vB::getRequest()->getTimeNow(),
					'version' => $full_product_info["$phrase[product]"]['version'],
				));
			}
			$products[$phrase['product']] = 1;
		}
		return array_keys($products);
	}

	/**
	 * Delete a phrase
	 * @param int $phraseid Pharse ID to be deleted
	 * @return void
	 */
	public function delete($phraseid)
	{
		$getvarname = vB::getDbAssertor()->getRow('phrase', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'phraseid' => $phraseid,
		));

		if ($getvarname)
		{
			vB::getDbAssertor()->assertQuery('phrase', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'varname' => $getvarname['varname'],
				'fieldname' => $getvarname['fieldname'],
			));

			require_once(DIR . '/includes/adminfunctions.php');
			require_once(DIR . '/includes/adminfunctions_language.php');
			build_language(-1);
		}
		else
		{
			throw new vB_Exception_Api('invalid_phrase_specified');
		}
		return $getvarname;
	}

	/**
	 * Add a new phrase or update an existing phrase
	 * @param string $fieldname New Phrase Type for adding, old Phrase Type for editing
	 * @param string $varname New Varname for adding, old Varname for editing
	 * @param array $data Phrase data to be added or updated
	 *              'text' => Phrase text array.
	 *              'oldvarname' => Old varname for editing only
	 *              'oldfieldname' => Old fieldname for editing only
	 *              't' =>
	 *              'ismaster' =>
	 *              'product' => Product ID of the phrase
	 * @return void
	 */
	public function save($fieldname, $varname, $data)
	{
		$fieldname = trim($fieldname);
		$varname = trim($varname);
		$vb5_config =& vB::getConfig();
		$userinfo = vB::getCurrentSession()->fetch_userinfo();
		require_once(DIR . '/includes/adminfunctions.php');
		$full_product_info = fetch_product_list(true);

		if (empty($varname))
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}

		if (!preg_match('#^[' . self::VALID_CLASS . ']+$#', $varname)) // match a-z, A-Z, 0-9, ',', _ only .. allow [] for help items
		{
			throw new vB_Exception_Api('invalid_phrase_varname');
		}

		require_once(DIR . '/includes/functions_misc.php');
		foreach ($data['text'] AS $text)
		{
			if (!validate_string_for_interpolation($text))
			{
				throw new vB_Exception_Api('phrase_text_not_safe', array($varname));
			}
		}

		// it's an update
		if (!empty($data['oldvarname']) AND !empty($data['oldfieldname']))
		{
			if (vB::getDbAssertor()->getField('phrase_fetchid', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'varname' => $varname,
				'fieldname' => $fieldname,
			)))
			{
				if ($varname != $data['oldvarname'])
				{
					throw new vB_Exception_Api('variable_name_exists', array($data['oldvarname'], $varname));
				}

				if ($fieldname != $data['oldfieldname'])
				{
					throw new vB_Exception_Api('there_is_already_phrase_named_x', array($varname));
				}
			}
			// delete old phrases
			vB::getDbAssertor()->assertQuery('deleteOldPhrases', array(
				'varname' => $data['oldvarname'],
				'fieldname' => $data['oldfieldname'],
				't' => $data['t'],
				'debug' => $vb5_config['Misc']['debug'],
			));

			$update = 1;
		}

		if (empty($update))
		{
			if ((empty($data['text'][0]) AND $data['text'][0] != '0' AND !$data['t']) OR empty($varname))
			{
				throw new vB_Exception_Api('please_complete_required_fields');
			}

			if (
				vB::getDbAssertor()->getField('phrase_fetchid', array(
					'varname' => $varname,
					'fieldname' => $fieldname,
				))
			)
			{
				throw new vB_Exception_Api('there_is_already_phrase_named_x', array($varname));
			}
		}

		if ($data['ismaster'])
		{
			if ($vb5_config['Misc']['debug'] AND !$data['t'])
			{
				/*insert query*/
				vB::getDbAssertor()->assertQuery('phrase', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
					'languageid' => -1,
					'varname' => $varname,
					'text' => $data['text'][0],
					'fieldname' => $fieldname,
					'product' => $data['product'],
					'username' => $userinfo['username'],
					'dateline' => vB::getRequest()->getTimeNow(),
					'version' =>$full_product_info[$data['product']]['version']
				));
			}

			unset($data['text'][0]);
		}

		foreach($data['text'] AS $_languageid => $txt)
		{
			$_languageid = intval($_languageid);
			if (!empty($txt) OR $txt == '0')
			{
				/*insert query*/
				vB::getDbAssertor()->assertQuery('phrase', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
					'languageid' => $_languageid,
					'varname' => $varname,
					'text' => $txt,
					'fieldname' => $fieldname,
					'product' => $data['product'],
					'username' => $userinfo['username'],
					'dateline' => vB::getRequest()->getTimeNow(),
					'version' =>$full_product_info[$data['product']]['version']
				));
			}
		}

		require_once(DIR . '/includes/adminfunctions.php');
		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language(-1);
	}

	/**
	 * Fetches an array of existing phrase types from the database
	 *
	 * @param	boolean	If true, will return names run through ucfirst()
	 *
	 * @return	array
	 */
	public function fetch_phrasetypes($doUcFirst = false)
	{
		$out = array();
		$phrasetypes = vB::getDbAssertor()->assertQuery('phrasetype', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'editrows', 'value' => '0', 'operator' => vB_dB_Query::OPERATOR_NE)
				)
		));
		foreach ($phrasetypes as $phrasetype)
		{
			$out["{$phrasetype['fieldname']}"] = $phrasetype;
			$out["{$phrasetype['fieldname']}"]['field'] = $phrasetype['title'];
			$out["{$phrasetype['fieldname']}"]['title'] = ($doUcFirst ? ucfirst($phrasetype['title']) : $phrasetype['title']);
		}
		ksort($out);

		return $out;
	}

	/**
	* Returns message and subject for an email.
	*
	* @param string $email_phrase Name of email phrase to fetch
	* @param array $email_vars Variables for the email message phrase
	* @param array $emailsub_vars Variables for the email subject phrase
	* @param int $languageid Language ID from which to pull the phrase (see fetch_phrase $languageid)
	* @param string	$emailsub_phrase If not empty, select the subject phrase with the given name
	*
	* @return array
	*/
	public function fetchEmailPhrases($email_phrase, $email_vars = array(), $emailsub_vars = array(), $languageid = 0, $emailsub_phrase = '')
	{
		if (empty($emailsub_phrase))
		{
			$emailsub_phrase = $email_phrase . '_gemailsubject';
		}

		$email_phrase .= '_gemailbody';

		$vbphrases = $this->fetch(array($email_phrase, $emailsub_phrase), $languageid);

		return array(
			'message' => vsprintf($vbphrases[$email_phrase], $email_vars),
			'subject' => vsprintf($vbphrases[$emailsub_phrase], $emailsub_vars),
		);
	}
}
