<?php if (!defined('VB_ENTRY')) die('Access denied.');
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
* BB code parser for img check
*
* @package	vBulletin
*/
class vB5_Template_BbCode_Imgcheck extends vB5_Template_BbCode
{
	public function __construct($appendCustomTags = true)
	{
		parent::__construct($appendCustomTags);

		// change all unparsable tags to use the unparsable callback
		// [img] and [attach] tags are not parsed via the normal parser
		foreach ($this->tag_list['option'] AS $tagname => $info)
		{
			if (isset($this->tag_list['option']["$tagname"]))
			{
				$this->tag_list['option']["$tagname"]['callback'] = 'handle_unparsable';
				unset($this->tag_list['option']["$tagname"]['html']);
			}
		}

		foreach ($this->tag_list['no_option'] AS $tagname => $info)
		{
			if (isset($this->tag_list['no_option']["$tagname"]))
			{
				$this->tag_list['no_option']["$tagname"]['callback'] = 'handle_unparsable';
				unset($this->tag_list['no_option']["$tagname"]['html']);
			}
		}
	}

	/**
	* Call back to replace any tag with itself. In the context of this class,
	* very few tags are actually parsed.
	*
	* @param	string	Text inside the tag
	*
	* @return	string	The unparsed tag and the text within it
	*/
	function handle_unparsable($text)
	{
		$current_tag =& $this->current_tag;

		return "[$current_tag[name]" .
			($current_tag['option'] !== false ?
				"=$current_tag[delimiter]$current_tag[option]$current_tag[delimiter]" :
				''
			) . "]$text [/$current_tag[name]]";
	}

	/**
	* Handles an [img] tag.
	*
	* @param	string	The text to search for an image in.
	* @param	string	Whether to parse matching images into pictures or just links.
	*
	* @return	string	HTML representation of the tag.
	*/
	function handle_bbcode_img($bbcode, $do_imgcode, $has_img_code = false, $fulltext = '', $forceShowImages = false)
	{
		$bbcode = parent::handle_bbcode_img($bbcode, $do_imgcode, $has_img_code, $fulltext, true);
		return $bbcode;
	}
}
