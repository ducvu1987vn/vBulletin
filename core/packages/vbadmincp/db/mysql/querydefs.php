<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
*
* @package vBulletin
* @version $Revision: 28823 $
* @since $Date: 2008-12-16 17:43:04 +0000 (Tue, 16 Dec 2008) $
* @copyright vBulletin Solutions Inc.
*/
class vBAdmincp_dB_MYSQL_QueryDefs extends vB_dB_MYSQL_QueryDefs
{

	/**
	* This class is called by the new vB_dB_Assertor database class
	* It does the actual execution. See the vB_dB_Assertor class for more information
	*
	* Note that there is no install package. Therefore the ONLY thing that should be in this are queries unique to
	* the install/upgrade process. Especially there should be no table definitions unless they are vB3/4 tables not used
	* in vB5.
	*
	**/

	/*Properties====================================================================*/

	//type-specific

	protected $db_type = 'MYSQL';

	protected $table_data = array(
	);

	/** This is the definition for queries.
	 * **/
	protected $query_data = array(
		'updateThreadCounts' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node as node INNER JOIN
			(SELECT node.starter,
			SUM(CASE WHEN node.parentid = node.starter AND node.showpublished > 0 AND node.showapproved >0 THEN 0 ELSE 1 END) AS textcount,
			SUM(CASE WHEN node.parentid = node.starter AND (node.showpublished = 0 OR node.showapproved = 0) THEN 1 ELSE 0 END) AS textunpubcount,
			SUM(CASE WHEN node.showpublished > 0 AND node.showapproved > 0 THEN 1 ELSE 0 END) AS totalcount,
			SUM(CASE WHEN node.showpublished = 0 OR node.showapproved = 0 THEN 1 ELSE 0 END) AS totalunpubcount,
			MAX(node.publishdate) AS lastcontent
			FROM {TABLE_PREFIX}node AS node
			WHERE node.starter between {start} and {end} AND node.contenttypeid NOT IN ({nonTextTypes})
			GROUP BY node.starter) as counts
			ON node.nodeid = counts.starter
			SET node.textcount = counts.textcount - 1, node.textunpubcount = counts.textunpubcount - 1, node.totalcount = counts.totalcount - 1,
			node.totalunpubcount = counts.totalunpubcount - 1, node.lastcontent = counts.lastcontent'),
		'updateThreadLast' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS node INNER JOIN {TABLE_PREFIX}node AS lastcontent
			ON lastcontent.starter = node.nodeid AND lastcontent.publishdate = node.lastcontent
			SET node.lastcontentid = lastcontent.nodeid, node.lastcontentauthor = lastcontent.authorname, node.lastauthorid = lastcontent.userid
			WHERE node.starter between {start} and {end}  AND lastcontent.contenttypeid NOT IN ({nonTextTypes})'),
		'getMaxNodeid' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(nodeid) AS maxid FROM {TABLE_PREFIX}node'),
		'getMaxStarter' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(starter) AS maxstarter FROM {TABLE_PREFIX}node'),
		'getNextStarter' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT min(starter) AS next FROM {TABLE_PREFIX}node WHERE starter > {startat}'),
		'getNextChannels' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT nodeid FROM {TABLE_PREFIX}channel WHERE nodeid > {startat} LIMIT {blocksize}'),
		'getMaxChannel' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(nodeid) AS maxid FROM {TABLE_PREFIX}channel'),
		'updateChannelCounts' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS channel INNER JOIN
			( SELECT parentid,
			SUM(CASE WHEN showpublished AND showapproved THEN 1 else 0 END) AS textcount,
			SUM(CASE WHEN showpublished AND showapproved THEN 0 else 1 END) AS textunpubcount,
			SUM(totalcount) AS childcount,
			SUM(totalunpubcount) AS childunpub,
			MAX(COALESCE(lastcontent, publishdate, 0)) AS lastcontent
			FROM {TABLE_PREFIX}node
			WHERE parentid IN ({nodeids})
			GROUP BY parentid
			) AS starter ON starter.parentid = channel.nodeid
			set channel.textcount = starter.textcount, channel.textunpubcount = starter.textunpubcount,
			channel.totalcount = starter.childcount + starter.textcount,
			channel.totalunpubcount = starter.childunpub,
			channel.lastcontent = starter.lastcontent'),
		'updateChannelLast' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS channel INNER JOIN {TABLE_PREFIX}node AS starter
			ON starter.parentid = channel.nodeid AND starter.lastcontent = channel.lastcontent
			SET channel.lastcontentid = starter.lastcontentid, channel.lastcontentauthor = starter.lastcontentauthor,
			channel.lastauthorid = starter.lastauthorid
			WHERE channel.nodeid IN ({nodeids})'),
		'rows_affected' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' =>'SELECT ROW_COUNT() AS qty'),
	);


}
