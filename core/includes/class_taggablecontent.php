<?php
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

require_once(DIR . "/includes/functions_bigthree.php");


/**
 *	Not quite dead yet.  The static functions should be moved to a more appropriate location
 *	and then we'll kill it.
 */
abstract class vB_Taggable_Content_Item
{
	/**
	*	Private constructor, use 'create' method to instantiate objects.
	*
	*	@private
	*/
	private function __construct()
	{
	}

	/********************************************************
	*	Static Methods
	********************************************************/

	/**
	*	Takes a list of tags and returns a list of valid tags
	*
	* Tags are transformed to removed tabs and newlines
	* Tags may be lowercased based on options
	* Tags matching synomyns will
	* Duplicate will be eliminated (case insensitive)
	* Invalid tags will be removed.
	*
	* Fetch the valid tags from a list. Filters are length, censorship, perms (if desired).
	*
	* @param	string|array	List of tags to add (comma delimited, or an array as is). If array, ensure there are no commas.
	* @param	array			(output) List of errors that happens
	* @param	boolean		Whether to expand the error phrase
	*
	* @return	array			List of valid tags
	*/
	public static function filter_tag_list($taglist, &$errors, $evalerrors = true)
	{
		$options = vB::getDatastore()->getValue('options');
		$errors = array();

		if (!is_array($taglist))
		{
			$taglist = self::split_tag_list($taglist);
		}

		//This seems like a terrible place to put this, but I don't know where else it should go.
		if ($options['tagmaxlen'] <= 0 OR $options['tagmaxlen'] >= 100)
		{
			$options['tagmaxlen'] = 100;
		}

		$valid_raw = array();

		foreach ($taglist AS $tagtext)
		{
			$tagtext = trim(preg_replace('#[ \r\n\t]+#', ' ', $tagtext));
			if (self::is_tag_valid($tagtext, $errors))
			{
				$valid_raw[] = ($options['tagforcelower'] ? vbstrtolower($tagtext) : $tagtext);
			}
		}

		$valid_raw = self::convert_synonyms($valid_raw, $errors);

		// we need to essentially do a case-insensitive array_unique here
		$valid_unique = array_unique(array_map('vbstrtolower', $valid_raw));
		$valid = array();
		foreach (array_keys($valid_unique) AS $key)
		{
			$valid[] = $valid_raw["$key"];
		}
		$valid_unique = array_values($valid_unique); // make the keys jive with $valid

		//if requested compose the error messages to strings
		if ($evalerrors)
		{
			$errors = fetch_error_array($errors);
		}

		return $valid;
	}

	/**
	*	Delete tag attachments for a list of content items
	*
	* @param mixed contenttypeid in one of the forms accepted by vB_Types
	* @param array $contentids
	 */
/*
	public static function delete_tag_attachments_list($contenttypeid, $contentids)
	{
		//throw new Exception('Function needs to be converted to use assertor or new API');
	 	$contenttypeid = vB_Types::instance()->getContentTypeID($contenttypeid);

		vB::getDbAssertor()->assertQuery('tagcontent',
			array(vB_dB_Query::TYPE_KEY =>vB_dB_Query::QUERY_DELETE,
				'contentid' => array_map('intval', $contentids),
				'contenttypeid' => $contenttypeid,
			)
			);
	}
 */

	public static function merge_users($olduserid, $newuserid)
	{
		vB::getDbAssertor()->assertQuery('vBForum:tagnode', array(
			vB_dB_Query::TYPE_KEY =>vB_dB_Query::QUERY_UPDATE,
			'userid' => intval($newuserid),
			vB_dB_Query::CONDITIONS_KEY => array(
				'userid' => intval($olduserid),
			)
		));
	}

   /********* provides a list of content types

	/**
	*	Checks to see if the tag is valid.
	*
	* Does not check the validity of any tag associations.
	* @param 	string $tagtext tag text to validate
	* @param	array	$errors (output) List of errors that happens
	*/
	protected static function is_tag_valid($tagtext, &$errors)
	{
		$options = vB::getDatastore()->getValue('options');
		static $taggoodwords = null;
		static $tagbadwords = null;

		// construct stop words and exception lists (if not previously constructed)
		if (is_null($taggoodwords) or is_null($tagbadwords))
		{

			// filter the stop words by adding custom stop words (tagbadwords) and allowing through exceptions (taggoodwords)
			if (!is_array($tagbadwords))
			{
				$tagbadwords = preg_split('/\s+/s', vbstrtolower($options['tagbadwords']), -1, PREG_SPLIT_NO_EMPTY);
			}

			if (!is_array($taggoodwords))
			{
				$taggoodwords = preg_split('/\s+/s', vbstrtolower($options['taggoodwords']), -1, PREG_SPLIT_NO_EMPTY);
			}
			// get the stop word list
			$badwords = vB_Api::instanceInternal("Search")->get_bad_words();
			// merge hard-coded badwords and tag-specific badwords
			$tagbadwords = array_merge($badwords, $tagbadwords);
		}

		if ($tagtext === '')
		{
			return false;
		}

		if (in_array(vbstrtolower($tagtext), $taggoodwords))
		{
			return true;
		}

		$char_strlen = vbstrlen($tagtext, true);
		if ($options['tagminlen'] AND $char_strlen < $options['tagminlen'])
		{
			$errors['min_length'] = array('tag_too_short_min_x', $options['tagminlen']);
			return false;
		}

		if ($char_strlen > $options['tagmaxlen'])
		{
			$errors['max_length'] = array('tag_too_long_max_x', $options['tagmaxlen']);
			return false;
		}

		if (strlen($tagtext) > 100)
		{
			// only have 100 bytes to store a tag
			$errors['max_length'] = array('tag_too_long_max_x', $options['tagmaxlen']);
			return false;
		}

		$censored = fetch_censored_text($tagtext);
		if ($censored != $tagtext)
		{
			// can't have tags with censored text
			$errors['censor'] = 'tag_no_censored';
			return false;
		}

		if (count(self::split_tag_list($tagtext)) > 1)
		{
			// contains a delimiter character
			$errors['comma'] = $evalerrors ? fetch_error('tag_no_comma') : 'tag_no_comma';
			return false;
		}

		if (in_array(strtolower($tagtext), $tagbadwords))
		{
			$errors['common'] = array('tag_x_not_be_common_words', $tagtext);
			return false;
		}

		return true;
	}

	/**
	* Splits the tag list based on an admin-specified set of delimiters (and comma).
	*
	* @param	string	List of tags
	*
	* @return	array	Tags in seperate array entries
	* temporarily make public
	*/
	public static function split_tag_list($taglist)
	{
		static $delimiters = array();
		$taglist = unhtmlspecialchars($taglist);

		$options = vB::getDatastore()->getValue('options');
		if (empty($delimiters))
		{
			$delimiter_list = $options['tagdelimiter'];
			$delimiters = array(',');

			// match {...} segments as is, then remove them from the string
			if (preg_match_all('#\{([^}]*)\}#s', $delimiter_list, $matches, PREG_SET_ORDER))
			{
				foreach ($matches AS $match)
				{
					if ($match[1] !== '')
					{
						$delimiters[] = preg_quote($match[1], '#');
					}
					$delimiter_list = str_replace($match[0], '', $delimiter_list);
				}
			}

			// remaining is simple, space-delimited text
			foreach (preg_split('#\s+#', $delimiter_list, -1, PREG_SPLIT_NO_EMPTY) AS $delimiter)
			{
				$delimiters[] = preg_quote($delimiter, '#');
			}
		}

		$taglist = preg_split('#(' . implode('|', $delimiters) . ')#', $taglist, -1, PREG_SPLIT_NO_EMPTY);

		return array_map(array('vB_String', 'htmlSpecialCharsUni'), $taglist);
	}

	/**
	*	Converts synomyns to canonical tags
	*
	* If a tag is converted a message will be added to the error array to alert the user
	* Does not handle removing duplicates created by the coversion process
	*
	* @param array array of tags to convert
	* @param array array of errors (in/out param)
	*
	*	@return array the new list of tags
	*/
	protected static function convert_synonyms($tags, &$errors)
	{
		//throw new Exception('Function needs to be converted to use assertor or new API');
		if (empty($tags))
		{
			return array();
		}
		//global $vbulletin;
		//$escaped_tags = array_map(array(&$vbulletin->db, 'escape_string'), $tags);
		/*$set = $vbulletin->db->query_read("
		  SELECT t.tagtext, p.tagtext as canonicaltagtext
			FROM " . TABLE_PREFIX . "tag t JOIN
				" . TABLE_PREFIX . "tag p ON t.canonicaltagid = p.tagid
			WHERE t.tagtext IN ('" . implode ("', '", $escaped_tags) . "')
		");*/
		$set = vB::getDbAssertor()->assertQuery('vBForum:getTagsBySynonym',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED, 'tags' => $tags)
		);

		$map = array();
		//while ($row = $vbulletin->db->fetch_array($set))
		if ($set AND $set->valid())
		{
			foreach ($set AS $row)
			{
			$map[vbstrtolower($row['tagtext'])] = $row['canonicaltagtext'];
		}
		}
		//$vbulletin->db->free_result($set);

		$new_tags = array();
		foreach ($tags as $key => $tag)
		{
			$tag_lower = vbstrtolower($tag);
			if (array_key_exists($tag_lower, $map))
			{
				$errors["$tag_lower-convert"] = array('tag_x_converted_to_y', $tag, $map[$tag_lower]);
				$new_tags[] = $map[$tag_lower];
			}
			else
			{
				$new_tags[] = $tag;
			}
		}
		return $new_tags;

	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 27657 $
|| ####################################################################
\*======================================================================*/
