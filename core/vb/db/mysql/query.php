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
 * @package vBDatabase
 */

/**
 * Mysql specific query interface -- see base class
 * @package vBDatabase
 */

class vB_dB_MYSQL_Query extends vB_dB_Query
{
	/*Properties====================================================================*/

	protected $db_type = 'MYSQL';



	/** This is the definition for queries we will process through.  We could also
	 * put them in the database, but this eliminates a query.
	 * **/
	protected $query_data = array(
		'select_section' => array(self::QUERYTYPE_KEY => self::QUERY_SELECT,
			'query_string' =>  'SELECT {sql_calc} node.nodeid AS itemid,
					(node.nodeleft = 1) AS isroot, node.nodeid, node.contenttypeid, node.contentid, node.url, node.parentnode, node.styleid, node.userid,
					node.layoutid, node.publishdate, node.setpublish, node.issection, parent.permissionsfrom as parentpermissions,
					node.permissionsfrom, node.publicpreview, node.showtitle, node.showuser, node.showpreviewonly, node.showall,
					node.showupdated, node.showviewcount, node.showpublishdate, node.settingsforboth, node.includechildren, node.editshowchildren,
					node.shownav, node.hidden, node.nosearch, node.nodeleft,
					info.description, info.title, info.html_title, info.viewcount, info.creationdate, info.workflowdate,
					info.workflowstatus, info.workflowcheckedout, info.workflowlevelid, info.associatedthreadid,
					user.username, sectionorder.displayorder, thread.replycount, parentinfo.title AS parenttitle
				FROM {TABLE_PREFIX}cms_node AS node
				INNER JOIN {TABLE_PREFIX}cms_nodeinfo AS info ON info.nodeid = node.nodeid
				LEFT JOIN {TABLE_PREFIX}user AS user ON user.userid = node.userid
				LEFT JOIN {TABLE_PREFIX}thread AS thread ON thread.threadid = info.associatedthreadid
				LEFT JOIN {TABLE_PREFIX}cms_sectionorder AS sectionorder ON sectionorder.sectionid = {filter_node}
					AND sectionorder.nodeid = node.nodeid
				LEFT JOIN {TABLE_PREFIX}cms_node AS parent ON parent.nodeid = node.parentnode
				LEFT JOIN {TABLE_PREFIX}cms_nodeinfo AS parentinfo ON parentinfo.nodeid = parent.nodeid
				INNER JOIN {TABLE_PREFIX}cms_node AS rootnode
					ON rootnode.nodeid = {filter_node} AND (node.nodeleft >= rootnode.nodeleft AND node.nodeleft <= rootnode.noderight) AND node.nodeleft != rootnode.nodeleft
				  {$extrasql} AND node.contenttypeid <> {sectiontype} AND node.new != 1'),
		'updt_nodeconfig' => array(self::QUERYTYPE_KEY => self::QUERY_UPDATE,
			'query_string' =>  "UPDATE {TABLE_PREFIX}cms_nodeconfig SET value='{value}' WHERE nodeid={nodeid} AND name='{name}';"),
		'del_nodeconfig' => array(self::QUERYTYPE_KEY => self::QUERY_DELETE,
			'query_string' =>  'DELETE FROM {TABLE_PREFIX}cms_nodeconfig WHERE nodeid={nodeid} AND name=\'{name}\''),
		'ins_nodeconfig' => array(self::QUERYTYPE_KEY => self::QUERY_INSERT,
			'query_string' =>  "INSERT INTO {TABLE_PREFIX}cms_nodeconfig (nodeid, name, value, serialized)
			VALUES({nodeid}, '{name}','{value}', {serialized});"),
		'sel_nodeconfig' => array(self::QUERYTYPE_KEY => self::QUERY_SELECT,
			'query_string' =>  'SELECT * FROM {TABLE_PREFIX}cms_nodeconfig WHERE nodeid={nodeid} ORDER BY name;'),
	);
}

/*======================================================================*\
|| ####################################################################
|| # SVN=> $Revision=> 28823 $
|| ####################################################################
\*======================================================================*/
