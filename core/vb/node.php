<?php

/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 5.0.0
  || # ---------------------------------------------------------------- # ||
  || # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

/**
 * @package vBulletin
 */
class vB_Node
{

	/**
	 * Calculates textcount, textunpubcount, totalcount, totalunpubcount for a nodeid.
	 * Used for fixing node counts and for verifying integrity
	 * @param int $nodeId
	 * @return array
	 */
	public static function getCounts($nodeId)
	{
		static $exclude = array();

		if (empty($exclude))
		{
			$types = vB_Types::instance()->getContentTypes();
			foreach ($types as $className => $type)
			{
				try
				{
					if (class_exists('vB_Api_Content_' . $type['class'], true))
					{
						$contentAPI = vB_Api::instanceInternal('Content_' . $type['class']);

						if ($contentAPI->getTextCountChange() == 0)
						{
							$exclude[] = $type['id'];
						}
					}
				}
				catch(exception $e)
				{

				}
			}
		}
		$counts = vB::getDbAssertor()->getRow('vBForum:getContentCounts', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'excludeTypes' => $exclude, 'parentid' => $nodeId
			));

		return $counts;
	}

	/**
	 * Verifies integrity of a node in db. If a
	 * @param array $record
	 */
	public static function validateRecord($record)
	{
		$db = vB::getDbAssertor();

		$result['errors'] = array();

		// -- VERIFY ROUTE --
		// @TODO remove the check and check for all types when photo and attachs have routeid
		$photoType = vB_Types::instance()->getContentTypeID('vBForum_Photo');
		$attachType = vB_Types::instance()->getContentTypeID('vBForum_Attach');
		if (!in_array($record['contenttypeid'], array($photoType, $attachType)) AND !intval($record['routeid']))
		{
			$result['errors'][] = "Invalid routeid for node: " . $record['nodeid'] . " should be greater than 0";
		}

		// -- VERIFY COUNTS --
		$counts = self::getCounts($record['nodeid']);
		foreach($counts as $key=>$count)
		{
			if ($count == NULL)
			{
				$count = 0;
			}

			if (!isset($record[$key]))
			{
				$result['errors'][] = "Couldn't check $key";
			}
			else if ($record[$key] != $count)
			{
				$result['errors'][] = "Invalid count: $key values do not match (current:{$record[$key]} - expected:$count)";
			}
		}

		// -- VERIFY LAST DATA --
		if (($counts['textcount'] + $counts['totalcount']) > 0)
		{
			//verify lastcontentid value is valid
			if (intval($record['lastcontentid']) == 0)
			{
				$result['errors'][] = 'Invalid lastcontentid value: the node has children that are not reflected in last content value';
			}
			else
			{
				// verify last content exists
				$lastcontent = $db->getRow('vBForum:getLastData', array(
					'parentid' => $record['nodeid'],
					'timenow' => vB::getRequest()->getTimeNow()
				));

				if (!$lastcontent)
				{
					$result['errors'][] = 'Couldn\'t find last content';
				}
				else
				{
					if ($lastcontent['nodeid'] != $record['lastcontentid'])
					{
						$result['errors'][] = "Invalid lastcontentid: values do not match (expected:{$lastcontent['nodeid']} - current:{$record['lastcontentid']})";
					}
					if ($lastcontent['authorname'] != $record['lastcontentauthor'])
					{
						$result['errors'][] = "Invalid lastcontentauthor: values do not match (expected:{$lastcontent['authorname']} - current:{$record['lastcontentauthor']})";
					}
					if ($lastcontent['userid'] != $record['lastauthorid'])
					{
						$result['errors'][] = "Invalid lastauthorid: values do not match (expected:{$lastcontent['userid']} - current:{$record['lastauthorid']})";
					}
				}
			}
		}
		//It's O.K. for the lastcontentid to point to itself.
		else if (($record['lastcontentid'] != 0) AND ($record['lastcontentid'] != $record['nodeid']))
		{
			$result['errors'][] = "Invalid lastcontentid: value must be 0 instead of {$record['lastcontentid']}";
		}

		//check to see that getContentForNode returns valid information. This will delete some kinds of failure in search or node API's.
		$nodeid = $record['nodeid'];
		$node = vB_Library::instance('node')->getNodeContent($nodeid);

		if (empty($node) OR empty($node[$nodeid]) OR empty($node[$nodeid]['userid']))
		{
			$result['errors'][] = "getContentForNode for $nodeid fails- probably a permissions error;";
		}

		if (empty($result['errors']))
		{
			return true;
		}
		else
		{
			return $result;
		}
	}

	/**
	 * This method fixes the count values for a node
	 * @param int $nodeId - Node to fix
	 */
	public static function fixNodeCount($nodeId)
	{
		$counts = self::getCounts($nodeId);

		vB::getDbAssertor()->update('vBForum:node',
				array(
					'textcount'			=> $counts['textcount'],
					'textunpubcount'	=> $counts['textunpubcount'],
					'totalcount'		=> $counts['totalcount'],
					'totalunpubcount'	=>$counts['totalunpubcount']
				),
				array('nodeid' => $nodeId));

		vB_Cache::instance()->allCacheEvent("nodeChg_" .$nodeId);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
