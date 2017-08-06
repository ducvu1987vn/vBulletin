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

// ###################### Start makeFaqJump #######################
// get complete faq listings
function construct_faq_jump($parent = 0, $depth = 0)
{
	global $ifaqcache, $faqcache, $faqjumpbits, $faqparent, $vbphrase, $vbulletin;

	if (!is_array($ifaqcache["$parent"]))
	{
		return;
	}

	foreach($ifaqcache["$parent"] AS $key1 => $faq)
	{
		$optiontitle = str_repeat('--', $depth) . ' ' . $faq['title'];
		$optionvalue = 'faq.php?' . vB::getCurrentSession()->get('sessionurl') . "faq=$parent#faq_$faq[faqname]";
		$optionselected = iif($faq['faqname'] == $faqparent, ' ' . 'selected="selected"');

		$faqjumpbits .= render_option_template($optiontitle, $optionvalue, $optionselected, $optionclass);

		if (is_array($ifaqcache["$faq[faqname]"]))
		{
			construct_faq_jump($faq['faqname'], $depth + 1);
		}
	}
}

// ###################### Start getFaqParents #######################
// get parent titles function for navbar
function fetch_faq_parents($faqname)
{
	global $ifaqcache, $faqcache, $parents, $vbulletin;
	static $i = 0;

	$faq = $faqcache["$faqname"];
	if (is_array($ifaqcache["$faq[faqparent]"]))
	{
		$key = iif($i++, 'faq.php?' . vB::getCurrentSession()->get('sessionurl') . "faq=$faq[faqname]");
		$parents["$key"] = $faq['title'];
		fetch_faq_parents($faq['faqparent']);
	}
}

// ###################### Start showFaqItem #######################
// show an faq entry
function construct_faq_item($faq, $find = '')
{
	global $vbulletin, $ifaqcache, $faqbits, $faqlinks, $show, $vbphrase;

	$faq['text'] = trim($faq['text']);
	if (!empty($find) AND is_array($find))
	{
		$faq['title'] = preg_replace('#(^|>)([^<]+)(?=<|$)#sUe', "process_highlight_faq('\\2', \$find, '\\1', '<u>\\\\1</u>')", $faq['title']);
		$faq['text'] = preg_replace('#(^|>)([^<]+)(?=<|$)#sUe', "process_highlight_faq('\\2', \$find, '\\1', '<span class=\"highlight\">\\\\1</span>')", $faq['text']);
	}

	$faqsublinks = '';
	if (is_array($ifaqcache["$faq[faqname]"]))
	{
		foreach($ifaqcache["$faq[faqname]"] AS $subfaq)
		{
			if ($subfaq['displayorder'] > 0)
			{
				$templater = vB_Template::create('faqbit_link');
					$templater->register('faq', $faq);
					$templater->register('subfaq', $subfaq);
				$faqsublinks .= $templater->render();
			}
		}
	}

	$show['faqsublinks'] = iif ($faqsublinks, true, false);
	$show['faqtext'] = iif ($faq['text'], true, false);

	// Legacy Hook 'faq_item_display' Removed //

	$templater = vB_Template::create('faqbit');
		$templater->register('faq', $faq);
		$templater->register('faqsublinks', $faqsublinks);
	$faqbits .= $templater->render();
}

// ###################### Start getFaqText #######################
// get text for FAQ entries
function fetch_faq_text_array($faqnames)
{
	global $vbulletin, $faqcache, $header;
	$assertor = vB::getDbAssertor();

	$faqtext = array();
	$textcache = array();
	foreach ($faqnames AS $faq)
	{
		$faqtext[] = $faq['faqname'] . '_gfaqtext';
	}

	$faqtexts = $assertor->assertQuery('vBForum:phrase',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'fieldname' => 'faqtext',
			'languageid' => array(-1, 0, LANGUAGEID),
			'varname' => $faqtext
		)
	);
	if ( $faqtexts AND $faqtexts->valid() )
	{
		foreach($faqtexts AS $faqtext)
		{
			$textcache["$faqtext[languageid]"]["$faqtext[varname]"] = $faqtext['text'];
		}
	}
	unset ($faqtext);

	// sort with languageid
	ksort($textcache);

	foreach($textcache AS $faqtexts)
	{
		foreach($faqtexts AS $faqname => $faqtext)
		{
			$faqname = str_replace('_gfaqtext', '', $faqname);
			$faqcache["$faqname"]['text'] = $faqtext;
		}
	}
}

// ###################### Start makeAdminFaqRow #######################
function print_faq_admin_row($faq, $prefix = '')
{
	global $ifaqcache, $vbphrase, $vbulletin;

	$cell = array(
		// first column
		$prefix . '<b></b>' . iif(is_array($ifaqcache["$faq[faqname]"]), '<a href="faq.php?' . vB::getCurrentSession()->get('sessionurl') . 'faq=' . urlencode($faq['faqname']) . "\" title=\"$vbphrase[show_child_faq_entries]\">$faq[title]</a>", $faq['title']) . '<b></b>',
		// second column
		"<input type=\"text\" class=\"bginput\" size=\"4\" name=\"order[$faq[faqname]]\" title=\"$vbphrase[display_order]\" tabindex=\"1\" value=\"$faq[displayorder]\" />",
		// third column
		construct_link_code($vbphrase['edit'], 'faq.php?' . vB::getCurrentSession()->get('sessionurl') . 'do=edit&amp;faq=' . urlencode($faq['faqname'])) .
		construct_link_code($vbphrase['add_child_faq_item'], "faq.php?" . vB::getCurrentSession()->get('sessionurl') . 'do=add&amp;faq=' . urlencode($faq['faqname'])) .
		construct_link_code($vbphrase['delete'], 'faq.php?' . vB::getCurrentSession()->get('sessionurl') . 'do=delete&amp;faq=' . urlencode($faq['faqname'])),
	);
	print_cells_row($cell);
}

// ###################### Start getifaqcache #######################
function cache_ordered_faq($gettext = false, $disableproducts = false, $languageid = null)
{
	global $vbulletin, $db, $faqcache, $ifaqcache;
	$assertor = vB::getDbAssertor();

	if ($languageid === null)
	{
		$languageid = LANGUAGEID;
	}

	// ordering arrays
	$displayorder = array();
	$languageorder = array();

	// data cache arrays
	$faqcache = array();
	$ifaqcache = array();
	$phrasecache = array();

	$fieldname = ($gettext) ? array('faqtitle', 'faqtext') : 'faqtitle';
	$phrases = $assertor->assertQuery('vBForum:phrase',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'fieldname' => $fieldname,
			'languageid' => array(-1, 0, $languageid)
		)
	);
	if ( $phrases AND $phrases->valid() )
	{
		foreach($phrases AS $phrase)
		{
			$languageorder["$phrase[languageid]"][] = $phrase;
		}
	}

	ksort($languageorder);

	foreach($languageorder AS $phrases)
	{
		foreach($phrases AS $phrase)
		{
			$phrasecache["$phrase[varname]"] = $phrase['text'];
		}
	}
	unset($languageorder);

	$activeproducts = array(
		'', 'vbulletin'
	);
	if ($disableproducts)
	{
		foreach ($vbulletin->products AS $product => $active)
		{
			if ($active)
			{
				$activeproducts[] = $product;
			}
		}
	}

	// Legacy Hook 'faq_cache_query' Removed //

	/** TODO
	 * Handle hooks inside this query !
	 */

	$conditions = array();
	if ($disableproducts)
	{
		$conditions[] = array('field' => 'product','value' => $activeproducts, 'operator' => vB_dB_Query::OPERATOR_EQ);
	}
	$faqs = $assertor->assertQuery('vBForum:faq',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, vB_dB_Query::CONDITIONS_KEY => $conditions)
	);
	if ($faqs AND $faqs->valid())
	{
		foreach($faqs AS $faq)
		{
			$faq['title'] = $phrasecache["$faq[faqname]_gfaqtitle"];
			if ($gettext)
			{
				$faq['text'] = $phrasecache["$faq[faqname]_gfaqtext"];
			}
			$faqcache["$faq[faqname]"] = $faq;
			$displayorder["$faq[displayorder]"][] =& $faqcache["$faq[faqname]"];
		}
	}
	unset($faq);
	$vbulletin->db->free_result($faqs);

	unset($phrasecache);
	ksort($displayorder);

	$ifaqcache = array('faqroot' => array());

	foreach($displayorder AS $faqs)
	{
		foreach($faqs AS $faq)
		{
			$ifaqcache["$faq[faqparent]"]["$faq[faqname]"] =& $faqcache["$faq[faqname]"];
		}
	}
}

// ###################### Start getFaqParentOptions #######################
function fetch_faq_parent_options($thisitem = '', $parentname = 'faqroot', $depth = 1)
{
	global $ifaqcache, $parentoptions;
	if (!is_array($parentoptions))
	{
		$parentoptions = array();
	}

	foreach($ifaqcache["$parentname"] AS $faq)
	{
		if ($faq['faqname'] != $thisitem)
		{
			$parentoptions["$faq[faqname]"] = str_repeat('--', $depth) . ' ' . $faq['title'];
			if (is_array($ifaqcache["$faq[faqname]"]))
			{
				fetch_faq_parent_options($thisitem, $faq['faqname'], $depth + 1);
			}
		}
	}
}

// ###################### Start getFaqDeleteList #######################
function fetch_faq_delete_list($parentname)
{
	global $ifaqcache;

	if (!is_array($ifaqcache))
	{
		cache_ordered_faq();
	}

	static $deletelist;
	if (!is_array($deletelist))
	{
		$deletelist = array($parentname);
	}

	if (is_array($ifaqcache["$parentname"]))
	{
		foreach($ifaqcache["$parentname"] AS $faq)
		{
			$deletelist[] = $faq['faqname'];
			fetch_faq_delete_list($faq['faqname']);
		}
	}

	return $deletelist;
}

// ###################### Start process_highlight_faq #######################
function process_highlight_faq($text, $words, $prepend, $replace)
{
	$text = str_replace('\"', '"', $text);

	if ($words)
	{
		$text = preg_replace('#(?<=[^\w=]|^)(\w*(' . implode('|', $words) . ')\w*)(?=[^\w=]|$)#siU', $replace, $text);
	}

	return "$prepend$text";
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>
