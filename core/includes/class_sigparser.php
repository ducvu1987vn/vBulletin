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

//if (!isset($GLOBALS['vbulletin']->db))
//{
//	exit;
//}

require_once(DIR . '/includes/class_bbcode.php');

/**
* Stack based BB code parser.
*
* @package 		vBulletin
* @version		$Revision: 70142 $
* @date 		$Date: 2013-01-07 13:01:30 -0800 (Mon, 07 Jan 2013) $
*
*/
class vB_SignatureParser extends vB_BbCodeParser
{
	/**
	* User this signature belongs to
	*
	* @var	integer
	*/
	var $userid = 0;

	/**
	* Groupings for tags
	*
	* @var	array
	*/
	var $tag_groups = array();

	/**
	* Errors found in the signature
	*
	* @var	array
	*/
	var $errors = array();

	protected $imgcount = 0;
	/**
	* Constructor. Sets up the tag permissions list.
	*
	* @param	vB_Registry	Reference to registry object
	* @param	array		The tag_list array for the parent class parser
	* @param	integer		The user this signature belongs to. Required
	* @param	boolean		Whether to append custom tags (they will not be parsed anyway)
	*/
	function vB_SignatureParser(&$registry, $tag_list, $userid, $append_custom_tags = true)
	{
		parent::vB_BbCodeParser($registry, $tag_list, false);

		$this->userid = intval($userid);
		if (!$this->userid)
		{
			trigger_error("User ID is 0. A signature cannot be parsed unless it belongs to a user.", E_USER_ERROR);
		}
		$usercontext = vB::getUserContext($this->userid);

		$this->tag_groups = array(
			'b'			=> 'basic',
			'i'			=> 'basic',
			'u'			=> 'basic',
			'sub'		=> 'basic',
			'sup'		=> 'basic',
			'hr'		=> 'basic',
			'table'		=> 'basic',

			'color'  => 'color',
			'size'   => 'size',
			'font'   => 'font',

			'left'   => 'align',
			'center' => 'align',
			'right'  => 'align',
			'indent' => 'align',

			'list'   => 'list',

			'url'    => 'link',
			'email'  => 'link',
			'thread' => 'link',
			'post'   => 'link',

			'code'   => 'code',
			'php'    => 'php',
			'html'   => 'html',
			'quote'  => 'quote',
		);

		foreach ($this->tag_groups AS $tag => $tag_group)
		{
			if ($usercontext->hasPermission('signaturepermissions', 'canbbcode' . $tag_group))
			{
				continue;
			}
			// General if not allowed
			if (isset($this->tag_list['no_option']["$tag"]))
			{
				$this->tag_list['no_option']["$tag"]['callback'] = 'check_bbcode_general';
				unset($this->tag_list['no_option']["$tag"]['html']);
			}

			if (isset($this->tag_list['option']["$tag"]))
			{
				$this->tag_list['option']["$tag"]['callback'] = 'check_bbcode_general';
				unset($this->tag_list['option']["$tag"]['html']);
			}
		}

 		// Specific functions
		$this->tag_list['option']['size']['callback'] = 'check_bbcode_size';
		$this->tag_list['no_option']['img']['callback'] = 'check_bbcode_img';

		// needs to parse sig pics like any other bb code
		$this->tag_list['no_option']['sigpic'] = array(
			'strip_empty' => false,
			'callback' => 'check_bbcode_sigpic'
		);

		if ($append_custom_tags)
		{
			$this->append_custom_tags();
		}
	}

	/**
	* Collect parser options and misc data to determine how to parse a signature
	* and determine if errors have occurred.
	*
	* @param	string	Unparsed text
	 * @param	int|str	ignored but necessary for consistency with class vB_BbCodeParser
	 * @param	bool	ignored but necessary for consistency with class vB_BbCodeParser
	 * @param	bool	ignored but necessary for consistency with class vB_BbCodeParser
	 * @param	string	ignored but necessary for consistency with class vB_BbCodeParser
	 * @param	int		ignored but necessary for consistency with class vB_BbCodeParser
	 * @param	bool	ignored but necessary for consistency with class vB_BbCodeParser
	 * @param	string	ignored but necessary for consistency with class vB_BbCodeParser
	 *
	* @return	string	Parsed text
	*/
	function parse($text, $forumid = 0, $allowsmilie = true, $isimgcheck = false, $parsedtext = '', $parsedhasimages = 3, $cachable = false, $htmlstate = null)
	{
		$usercontext = vB::getUserContext($this->userid);
		$dohtml = $usercontext->hasPermission('signaturepermissions', 'allowhtml');
		$dosmilies = $usercontext->hasPermission('signaturepermissions', 'allowsmilies');
		$dobbcode = $usercontext->hasPermission('signaturepermissions', 'canbbcode');
		$dobbimagecode = $usercontext->hasPermission('signaturepermissions', 'allowimg');
		return $this->do_parse($text, $dohtml, $dosmilies, $dobbcode, $dobbimagecode, false, false);
	}

	public function getPerms()
	{
		$can = $cant = array();
		$usercontext = vB::getUserContext($this->userid);
		$dobbcode = $usercontext->hasPermission('signaturepermissions', 'canbbcode');
		$taglist = $this->tag_groups;
		foreach ($taglist AS $tag => $tag_group)
		{
			if ($dobbcode AND $usercontext->hasPermission('signaturepermissions', 'canbbcode' . $tag_group))
			{
				$can[] = $tag;
			}
			else
			{
				$cant[] = $tag;
			}
		}
		return array('can' => $can, 'cant' => $cant);
	}


	/**
	* BB code callback allowed check
	*
	*/
	function check_bbcode_general($text)
	{
		$tag = $this->current_tag['name'];

		if ($this->tag_groups["$tag"] AND !vB::getUserContext($this->userid)->hasPermission('signaturepermissions', "canbbcode{$this->tag_groups[$tag]}"))
		{
			$this->errors["$tag"] = 'tag_not_allowed';
		}

		return '';
	}

	/**
	* BB code callback allowed check with size checking
	*
	*/
	function check_bbcode_size($text, $size)
	{
		$size_mod = array();
		foreach ($this->stack AS $stack)
		{
			if ($stack['type'] == 'tag' AND $stack['name'] == 'size')
			{
				$size_mod[] = trim($stack['option']);
			}
		}

		// need to process as a queue, not a stack of open tags
		$base_size = 3;
		foreach (array_reverse($size_mod) AS $tag_size)
		{
			if ($tag_size[0] == '-' OR $tag_size[0] == '+')
			{
				$base_size += $tag_size;
			}
			else
			{
				$base_size = $tag_size;
			}
		}
		//remove any chars from the end of the size, like px
		if (!is_numeric($base_size))
		{
			preg_match('#^([0-9]+)[^0-9]?#', $base_size, $matches);
			$base_size = $matches[1];
		}
		$usercontext = vB::getUserContext($this->userid);
		if ($usercontext->hasPermission('signaturepermissions', 'canbbcodesize'))
		{
			if (($sigmaxsizebbcode = $usercontext->getLimit('sigmaxsizebbcode')) > 0 AND $base_size > $sigmaxsizebbcode)
			{
				$this->errors['size'] = 'sig_bbcode_size_tag_too_big';
				$size = $sigmaxsizebbcode . 'px';
			}
		}
		else
		{
			$this->errors['size'] = 'tag_not_allowed';
			return '';
		}
		return $this->handle_bbcode_size($text, $size);
	}

	/**
	* BB code callback allowed check for images. Images fall back to links
	* if the image code is disabled, so allow if either is true.
	*
	*/
	function check_bbcode_img($image_path)
	{
		$userContext = vB::getUserContext($this->userid);
		if (
			!($userContext->hasPermission('signaturepermissions', 'allowimg'))
			AND
			!($userContext->hasPermission('signaturepermissions', 'canbbcodelink'))
		)
		{
			$this->errors['img'] = 'tag_not_allowed';
			return '';
		}
		elseif($userContext->hasPermission('signaturepermissions', 'allowimg'))
		{
			$this->imgcount ++;
			$allowedImgs = $userContext->getLimit('sigmaximages');
			if (($allowedImgs > 0) AND ($allowedImgs < $this->imgcount))
			{
				$this->errors['img'] = array('toomanyimages' => array($this->imgcount, $allowedImgs));
				return '';
			}
			return $this->handle_bbcode_img_match($image_path);
		}
		else
		{
			return $this->handle_bbcode_url($image_path, '');
		}
	}

	/**
	* BB code sigpic, returns the <img link.
	*
	*/
	function check_bbcode_sigpic($alt_text)
	{
		if (!vB::getDbAssertor()->getField('vBForum:sigpic', array('userid' => $this->userid)))
		{
			// guests can't have sigs (let alone sig pics) so why are we even here?
			if (!in_array('no_sig_pic_to_use', $this->errors))
			{
				$this->errors[] = 'no_sig_pic_to_use';
			}
			return 'sigpic';
		}

		static $sigpic_used = false;

		if ($sigpic_used == true)
		{
			// can only use the sigpic once in a signature
			if (!in_array('sig_pic_already_used', $this->errors))
			{
				$this->errors[] = 'sig_pic_already_used';
			}
			return 'sigpic';
		}

		$sigpic_used = true;
		return $this->handle_bbcode_sigpic($alt_text);
	}

} // End Class


/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 70142 $
|| ####################################################################
\*======================================================================*/

?>