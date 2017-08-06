<?php

class vB5_Template_Phrase
{
	const PLACEHOLDER_PREFIX = '<!-- ##phrase_';
	const PLACEHOLDER_SUFIX = '## -->';

	protected static $instance;
	protected $cache = array();
	protected $pending = array();
	protected $stack = array();

	public static function instance()
	{
		if (!isset(self::$instance))
		{
			$c = __CLASS__;
			self::$instance = new $c;
		}

		return self::$instance;
	}

	public function register($args)
	{
		$phraseName = $args[0];
		$pos = isset($this->pending[$phraseName]) ? count($this->pending[$phraseName]) : 0;

		$placeHolder = $this->getPlaceholder($phraseName, $pos);

		$this->pending[$phraseName][$placeHolder] = $args;
		$this->stack[$placeHolder] = $phraseName;

		return $placeHolder;
	}

	/**
	 * The use of this function should be avoided when possible because it forces the controller to fetch all missing phrases immediately.
	 *
	 * @var string phraseName
	 * @var mixed parameter1
	 * @var mixed parameter2
	 * @return type
	 */
	public function getPhrase()
	{
		$args = func_get_args();
		$phraseName = $args[0];

		// first check if we already have the phrase, if not force fetching
		if (!isset($this->cache[$phraseName]))
		{
			// note: the placeholder won't be used in this case
			$this->pending[$phraseName][] = $args;
			$this->fetchPhrases();
		}

		$args[0] = isset($this->cache[$phraseName]) ? $this->cache[$phraseName] : $args[0];
		return $this->constructPhraseFromArray($args);
	}

	public function resetPending() {
		$this->pending = array();
		$this->stack = array();
	}

	public function replacePlaceholders(&$content)
	{
		$this->fetchPhrases();
		$placeholders = array();
		end($this->stack);
		while (!is_null($placeholder_id = key($this->stack)))
		{
			$phraseName = current($this->stack);
			$phraseInfo = $this->pending[$phraseName][$placeholder_id];
			$phraseInfo[0] = isset($this->cache[$phraseName]) ? $this->cache[$phraseName] : $phraseInfo[0];

			// do parameter replacements in phrases for notices, since we don't want
			// the extra overhead of pulling these phrases in the api method
			if (strpos($phraseName, 'notice_') === 0 AND preg_match('/^notice_[0-9]+_html$/', $phraseName))
			{
				$phraseInfo[0] = str_replace(
					array('{musername}', '{username}', '{userid}', '{sessionurl}', '{sessionurl_q}', '{register_page}'),
					array(vB5_User::get('musername'), vB5_User::get('username'), vB5_User::get('userid'), vB::getCurrentSession()->get('sessionurl'), vB::getCurrentSession()->get('sessionurl_q'),  vB5_Template_Runtime::buildUrl('register|nosession')),
					$phraseInfo[0]
				);
			}

			$replace = $this->constructPhraseFromArray($phraseInfo);
			$placeholders[$placeholder_id] = $replace;
			//$content = str_replace($placeholder_id, $replace, $content);

			prev($this->stack);
		}

		if (!empty($placeholders))
		{
			$content = str_replace(array_keys($placeholders), $placeholders, $content);
		}
	}

	protected function getPlaceholder($phraseName, $pos)
	{
		return self::PLACEHOLDER_PREFIX . $phraseName . '_' . $pos . self::PLACEHOLDER_SUFIX;
	}

	protected function fetchPhrases()
	{
		// add phrases from phrasegroups already fetched with the user
		$phrasegroup_global = vB5_User::get('phrasegroup_global');
		if (!empty($phrasegroup_global))
		{
			$phrasegroup_global = unserialize($phrasegroup_global);
			if (is_array($phrasegroup_global))
			{
				foreach ($phrasegroup_global as $key => $value)
				{
					$this->cache[$key] = $value;
				}

				// @todo - here we should unset phrasegroup_global
				// in user information, since it's no longer needed,
				// but there's no good way to do that through the api
			}
		}

		$missing = array_diff(array_keys($this->pending), array_keys($this->cache));

		if (!empty($missing))
		{
			$response = Api_InterfaceAbstract::instance()->callApi('phrase', 'fetch', array('phrases' => $missing));
			foreach ($response as $key => $value)
			{
				$this->cache[$key] = $value;
			}
		}
	}

	/**
	 * Construct Phrase from Array
	 *
	 * this function is actually just a wrapper for sprintf but makes identification of phrase code easier
	 * and will not error if there are no additional arguments. The first element of the array is the phrase text, and
	 * the (unlimited number of) following elements are the variables to be parsed into that phrase.
	 *
	 * @param	array	array containing phrase and arguments
	 *
	 * @return	string	The parsed phrase
	 */
	protected function constructPhraseFromArray($phrase_array)
	{
		$numargs = sizeof($phrase_array);

		// if we have only one argument then its a phrase
		// with no variables, so just return it
		if ($numargs < 2)
		{
			return $phrase_array[0];
		}

		// if the second argument is an array, use their values as variables
		if (is_array($phrase_array[1]))
		{
			array_unshift($phrase_array[1], $phrase_array[0]);
			$phrase_array = $phrase_array[1];
		}

		// call sprintf() on the first argument of this function
		$phrase = @call_user_func_array('sprintf', $phrase_array);
		if ($phrase !== false)
		{
			return $phrase;
		}
		else
		{
			// if that failed, add some extra arguments for debugging
			for ($i = $numargs; $i < 10; $i++)
			{
				$phrase_array["$i"] = "[ARG:$i UNDEFINED]";
			}
			if ($phrase = @call_user_func_array('sprintf', $phrase_array))
			{
				return $phrase;
			}
			// if it still doesn't work, just return the un-parsed text
			else
			{
				return $phrase_array[0];
			}
		}
	}

}
