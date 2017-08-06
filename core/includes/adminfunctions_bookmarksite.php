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


// #############################################################################
/**
* Caches social bookmark site data to the datastore
*/
function build_bookmarksite_datastore()
{
	global $vbulletin;
	$assertor = vB::getDbAssertor();

	$vbulletin->bookmarksitecache = array();
	$bookmarksitelist = $assertor->assertQuery('vBForum:bookmarksite',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'active' => 1),
			array('field' => array('displayorder', 'bookmarksiteid'), 'direction' => array(vB_dB_Query::SORT_ASC, vB_dB_Query::SORT_ASC))
	);

	//if ($bookmarksitelist)
	if ($bookmarksitelist AND $bookmarksitelist->valid())
	{
		//while ($bookmarksite = $vbulletin->db->fetch_array($bookmarksitelist))
		foreach ($bookmarksitelist AS $bookmarksite)
		{
			$vbulletin->bookmarksitecache["$bookmarksite[bookmarksiteid]"] = $bookmarksite;
		}
	}

	// store the cache array into the database
	build_datastore('bookmarksitecache', serialize($vbulletin->bookmarksitecache), 1);
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>