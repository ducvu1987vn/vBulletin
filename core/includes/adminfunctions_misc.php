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

error_reporting(E_ALL & ~E_NOTICE);

/* 

### This file only contains Podcast functions ###

Podcast is currently disabled and may or may not return at some 
future date at which point these should be moved to the ACP API.

Regardless of current use, they have been updated to 
read the podcast XML definition from custom packages folders.

*/

/**
* Fetch array of podcast categories
*
* @param	string	text for the left cell of the table row
* @param	string	name of the <select>
* @param	mixed	selected <option>
*
*/
function print_podcast_chooser($title, $name, $selectedid = -1)
{
	print_select_row($title, $name, fetch_podcast_categories(), $selectedid, true);
}

/**
* Fetch array of podcast categories
*
* @return	array		Array of categories
*/
function fetch_podcast_categories($bypass = false)
{
	$categories = array('');
	require_once(DIR . '/includes/class_xml.php');

	$files = vB_Api_Product::loadProductXmlList('podcast');

	foreach ($files AS $file)
	{
		$xmlobj = new vB_XML_Parser(false, $file);
		$podcastdata = $xmlobj->parse();

		$products = vB::getDatastore()->getValue('products');
		if (!$bypass AND $podcastdata['product'] AND empty($products["$data[product]"]))
		{
			// attached to a specific product and that product isn't enabled
			continue;
		}

		if (is_array($podcastdata['category']))
		{
			foreach ($podcastdata['category'] AS $cats)
			{
				$categories[] = '-- ' . $cats['name'];
				if (is_array($cats['sub']['name']))
				{
					foreach($cats['sub']['name'] AS $subcats)
					{
						$categories[] = '---- ' . $subcats;
					}
				}
			}
		}
	}
	
	return $categories;
}

/**
* Fetch array of podcast categories
*
* @return	array		Array of categories
*/
function fetch_podcast_categoryarray($categoryid)
{
	$key = 1;
	$output = array();
	require_once(DIR . '/includes/class_xml.php');

	$files = vB_Api_Product::loadProductXmlList('podcast');

	foreach ($files AS $file)
	{
		$xmlobj = new vB_XML_Parser(false, $file);
		$podcastdata = $xmlobj->parse();

		$products = vB::getDatastore()->getValue('products');
		if ($podcastdata['product'] AND empty($products["$data[product]"]))
		{
			// attached to a specific product and that product isn't enabled
			continue;
		}

		if (is_array($podcastdata['category']))
		{
			foreach ($podcastdata['category'] AS $cats)
			{
				if ($key == $categoryid)
				{
					$output[] = htmlspecialchars_uni($cats['name']);
					break;
				}
				$key++;
				if (is_array($cats['sub']['name']))
				{
					foreach($cats['sub']['name'] AS $subcats)
					{
						if ($key == $categoryid)
						{
							$output[] = htmlspecialchars_uni($cats['name']);
							$output[] = htmlspecialchars_uni($subcats);
							break(2);
						}
						$key++;
					}
				}
			}
		}
	}

	return $output;
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 64814 $
|| ####################################################################
\*======================================================================*/
?>
