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
*
* @package vBulletin
* @version $Revision: 28823 $
* @since $Date: 2008-12-16 17:43:04 +0000 (Tue, 16 Dec 2008) $
* @copyright vBulletin Solutions Inc.
*/
class vBInstall_dB_MYSQL_QueryDefs extends vB_dB_MYSQL_QueryDefs
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
		'attachmenttype' => array('key' => 'extension', 'structure' => array('extension', 'mimetype','size','width','height','display','contenttypes')),
		'attachment' => array('key' => 'attachmentid', 'structure' => array('attachmentid', 'contenttypeid',
			'contentid', 'userid', 'dateline', 'filedataid', 'state', 'counter', 'posthash', 'filename',
			'caption', 'reportthreadid', 'settings', 'displayorder')
		),
		'socialgroup' => array('key'=> 'groupid','structure' => array( 'groupid', 'socialgroupcategoryid', 'name', 'description',
			'creatoruserid', 'dateline', 'members', 'picturecount', 'lastpost', 'lastposter', 'lastposterid', 'lastgmid',
			'visible', 'deleted', 'moderation', 'type', 'moderatedmembers', 'options', 'lastdiscussionid', 'discussions', 'lastdiscussion',
			'lastupdate', 'transferowner')),
		'socialgroupcategory' => array('key'=> 'socialgroupcategoryid','structure' => array('socialgroupcategoryid',
			'creatoruserid', 'title', 'description', 'displayorder', 'lastupdate', 'groups')),
		'socialgroupmember' => array('key'=> false,'structure' => array('userid', 'groupid', 'dateline', 'type')),
		'groupmessage' => array('key' => 'gmid','structure' => array('gmid', 'discussionid', 'postuserid',
			'postusername', 'dateline', 'state', 'title', 'pagetext', 'ipaddress', 'allowsmilie', 'reportthreadid')),
		'discussion' => array('key'=> 'discussionid','structure' => array('discussionid', 'groupid',
			'firstpostid', 'lastpostid', 'lastpost', 'lastposter', 'lastposterid', 'visible', 'deleted', 'moderation', 'subscribers')),
		'socialgroupicon' => array('key'=> false,'structure' => array('groupid', 'userid', 'filedata', 'extension',
			'dateline', 'width', 'height', 'thumbnail_filedata mediumblob', 'thumbnail_width', 'thumbnail_height')),
		'upgradelog' => array('key'=> 'upgradelogid','structure' => array('script', 'steptitle', 'step', 'startat','perpage', 'dateline', 'only')),
	);

	/** This is the definition for queries.
	 * **/
	protected $query_data = array(
		'getMaxPMSenderid' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT MAX(fromuserid) AS maxid FROM {TABLE_PREFIX}pmtext'),
		'getMaxPMRecipient' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT MAX(userid) AS maxid FROM {TABLE_PREFIX}pm'),
		'getMaxPMFolderUser' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT MAX(userid) AS maxid FROM {TABLE_PREFIX}messagefolder WHERE titlephrase = {titlephrase} '),
		'createPMFoldersSent' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}messagefolder(userid, titlephrase)
			SELECT distinct pmtext.fromuserid , \'sent_items\'
			FROM {TABLE_PREFIX}pmtext AS pmtext
			WHERE pmtext.fromuserid > {startat} AND pmtext.fromuserid < ({startat} + {batchsize} -1) ORDER BY pmtext.fromuserid'),
		'createPMFoldersMsg' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}messagefolder(userid, titlephrase)
			SELECT DISTINCT pm.userid , \'messages\'
			FROM {TABLE_PREFIX}pm AS pm
			WHERE pm.folderid = 0 AND pm.userid > {startat} AND pm.userid < ({startat} + {batchsize} -1) 
			ORDER BY pm.userid'),
		'importPMStarter' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}node(userid, authorname, parentid, contenttypeid, title, description, deleteuserid, deletereason, sticky,
			publishdate, created, oldid, oldcontenttypeid, routeid, inlist, protected, showpublished, showapproved, showopen, lastcontent)
			SELECT pmt.fromuserid, pmt.fromusername, {privateMessageChannel}, {privatemessageType}, pmt.title, pmt.title, 0, 0, 0,
			pmt.dateline, pmt.dateline, pmt.pmtextid, 9989, {pmRouteid}, 0, 1,1,1,1, pmt.dateline
			FROM {TABLE_PREFIX}pmtext AS pmt
			INNER JOIN (SELECT DISTINCT pmtextid FROM {TABLE_PREFIX}pm
				WHERE pmtextid > {startat} AND pmtextid < ({startat} + {batchsize} -1) AND parentpmid = 0
			)
			AS pm ON pm.pmtextid = pmt.pmtextid'),
		'getMaxPMStarter' =>array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT MAX(pmtextid) AS maxid
			FROM {TABLE_PREFIX}pm WHERE parentpmid = 0; '),
		'setPMStarter' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node SET starter = nodeid, lastcontentid = nodeid WHERE
 			oldcontenttypeid = {contenttypeid} AND oldid > {startat} AND oldid < ({startat} + {batchsize} -1)'),
		'setResponseStarter' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node SET starter = parentid, lastcontentid = nodeid WHERE
 			oldcontenttypeid = {contenttypeid} AND oldid > {startat} AND oldid < ({startat} + {batchsize} -1)'),
		'setShowValues' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node SET showapproved = {value}, showopen = {value}, showpublished = {value} WHERE
 			oldcontenttypeid = {contenttypeid} AND oldid > {startat} AND oldid < ({startat} + {batchsize} -1)'),
		'importPMText' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}text(nodeid, rawtext)
			SELECT node.nodeid, pmtext.message
			FROM {TABLE_PREFIX}pmtext AS pmtext
			INNER JOIN {TABLE_PREFIX}node AS node ON node.oldid = pmtext.pmtextid AND oldcontenttypeid = {contenttypeid}
			WHERE node.oldid > {startat} AND node.oldid < ({startat} + {batchsize} -1)'),
		'importPMMessage' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}privatemessage (nodeid, msgtype)
			SELECT nodeid, \'message\'
			FROM {TABLE_PREFIX}node AS node
			WHERE node.oldid > {startat} AND node.oldid < ({startat} + {batchsize} -1) AND node.oldcontenttypeid = {contenttypeid}'),
		'importPMSent' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}sentto (nodeid, userid, folderid, msgread)
			SELECT DISTINCT node.nodeid, node.userid, f.folderid, 1
			FROM {TABLE_PREFIX}node AS node
			INNER JOIN {TABLE_PREFIX}pmtext AS pmt ON pmt.pmtextid = node.oldid AND node.oldcontenttypeid = {contenttypeid}
			INNER JOIN {TABLE_PREFIX}messagefolder AS f ON f.userid = node.userid AND f.titlephrase = \'sent_items\'
			WHERE node.oldid > {startat} AND node.oldid < ({startat} + {batchsize} -1)'),
		'importPMInbox' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}sentto (nodeid, userid, folderid, msgread)
			SELECT DISTINCT node.nodeid, pm.userid, f.folderid,
			MAX(CASE WHEN pm.messageread > 0 THEN 1 ELSE 0 END) AS msgread
			FROM {TABLE_PREFIX}node AS node
			INNER JOIN {TABLE_PREFIX}pm AS pm ON pm.pmtextid = node.oldid AND node.oldcontenttypeid = {contenttypeid}
			INNER JOIN {TABLE_PREFIX}pmtext AS pmt ON pmt.pmtextid = pm.pmtextid AND pm.userid <> pmt.fromuserid
			INNER JOIN {TABLE_PREFIX}messagefolder AS f ON f.userid = pm.userid AND (CASE WHEN pm.folderid > 0 THEN f.oldfolderid = pm.folderid ELSE f.oldfolderid IS NULL END)
			AND (CASE WHEN pm.folderid > 0 THEN f.titlephrase IS NULL ELSE f.titlephrase = \'messages\' END)
			WHERE node.oldid > {startat} AND node.oldid < ({startat} + {batchsize} -1)
			GROUP BY node.nodeid, pm.userid, f.folderid'),
		'getMaxPMResponse' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT MAX(pmtextid) AS maxid FROM {TABLE_PREFIX}pm WHERE parentpmid > 0'),
		'getMaxPMResponseToFix' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT MAX(pmtextid) AS maxid
			FROM {TABLE_PREFIX}node n
			INNER JOIN {TABLE_PREFIX}pm pm ON n.oldid = pm.pmtextid AND n.oldcontenttypeid = {contenttypeid}
			WHERE n.starter <> n.parentid
		'),
		'getMaxNodeRecordToFix' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT MAX(nodeid) AS maxid
			FROM {TABLE_PREFIX}node n
			WHERE n.starter <> n.parentid AND oldcontenttypeid = {contenttypeid}
		'),
		'importPMResponse' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}node(userid, authorname, parentid, contenttypeid, title, description, deleteuserid, deletereason, sticky,
			publishdate, created, oldid, oldcontenttypeid, routeid, inlist, protected, starter, showpublished, showapproved, showopen)
			SELECT pmt.fromuserid, pmt.fromusername, node.nodeid, {privatemessageType}, pmt.title, pmt.title, 0, 0, 0,
			pmt.dateline, pmt.dateline, pmt.pmtextid, 9981, node.routeid, 0, 1, node.starter, 1, 1, 1
			FROM {TABLE_PREFIX}pmtext AS pmt
			INNER JOIN
			(SELECT pmtextid, min(parentpmid) AS parentpmid FROM {TABLE_PREFIX}pm
				WHERE pmtextid > {startat} AND pmtextid < ({startat} + {batchsize} -1) GROUP BY pmtextid HAVING min(parentpmid) > 0
			)
			AS response ON response.pmtextid = pmt.pmtextid
			INNER JOIN {TABLE_PREFIX}pm AS pm ON pm.pmid = response.parentpmid
			INNER JOIN {TABLE_PREFIX}node AS node ON node.oldid = pm.pmtextid  AND node.oldcontenttypeid = 9989
			WHERE node.nodeid > {maxNodeid}'),
		'getMaxPMNodeid' =>array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(nodeid) AS maxid FROM {TABLE_PREFIX}node WHERE oldcontenttypeid in (9981, 9989)'),
		'createClosureSelf' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}closure(parent, child, depth, publishdate)
			SELECT node.nodeid, node.nodeid, 0, node.publishdate FROM {TABLE_PREFIX}node AS node
			LEFT JOIN {TABLE_PREFIX}closure AS existing on node.nodeid = existing.child AND existing.depth = 0
			WHERE node.oldcontenttypeid = {oldcontenttype} AND existing.child IS NULL'),
		'createClosurefromParent' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}closure(parent, child, depth, publishdate)
			SELECT parent.parent, node.nodeid, parent.depth + 1, node.publishdate FROM {TABLE_PREFIX}node AS node
 			 INNER JOIN {TABLE_PREFIX}closure AS parent ON parent.child = node.parentid
			LEFT JOIN {TABLE_PREFIX}closure AS existing on existing.child = node.nodeid AND existing.parent = parent.parent
			WHERE node.oldcontenttypeid = {oldcontenttype} AND existing.child IS NULL' ),
		'runClosureAgain' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT parent.parent FROM {TABLE_PREFIX}node AS node
 			 INNER JOIN {TABLE_PREFIX}closure AS parent ON parent.child = node.parentid
			LEFT JOIN {TABLE_PREFIX}closure AS existing on existing.child = node.nodeid AND existing.parent = parent.parent
			WHERE node.oldcontenttypeid = {oldcontenttype} AND existing.child IS NULL
			LIMIT 1'),
		'getMissingGroupCategories' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT cat.* FROM {TABLE_PREFIX}socialgroupcategory AS cat
			LEFT JOIN {TABLE_PREFIX}node AS node ON node.oldcontenttypeid = 9988
			AND node.oldid = cat.socialgroupcategoryid WHERE node.nodeid IS NULL'),
		'getMissingSocialGroups' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT sgroup.*, category.nodeid AS categoryid, user.userid, user.username, transfer.userid AS transferuserid,
			transfer.username AS transferusername, route.routeid
			FROM {TABLE_PREFIX}socialgroup AS sgroup
			INNER JOIN {TABLE_PREFIX}node AS category ON category.oldcontenttypeid = 9988 AND category.oldid = sgroup.socialgroupcategoryid
			INNER JOIN {TABLE_PREFIX}user AS user ON user.userid = sgroup.creatoruserid
			LEFT JOIN {TABLE_PREFIX}user AS transfer ON user.userid = sgroup.transferowner
			LEFT JOIN {TABLE_PREFIX}node AS node ON node.oldcontenttypeid = {socialgroupType} AND node.oldid = sgroup.groupid
			LEFT JOIN {TABLE_PREFIX}routenew AS route ON route.routeid = category.routeid
			WHERE node.nodeid IS NULL
			LIMIT {batch_size}'),
		'getSocialGroupsCount' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT COUNT(*) AS total
			FROM {TABLE_PREFIX}socialgroup AS sgroup
			INNER JOIN {TABLE_PREFIX}node AS category ON category.oldcontenttypeid = 9988 AND category.oldid = sgroup.socialgroupcategoryid
			LEFT JOIN {TABLE_PREFIX}node AS node ON node.oldcontenttypeid = {socialgroupType} AND node.oldid = sgroup.groupid
			WHERE node.nodeid IS NULL'),
		'getImportedGroupsCount' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT COUNT(*) AS total
			FROM {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}closure AS cl ON (n.nodeid = cl.child)
			WHERE cl.parent = {parentid} AND n.contenttypeid = {channeltype} AND cl.depth > 1'),
		'getMaxSGDiscussionID' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT MAX(discussionid) AS maxid FROM {TABLE_PREFIX}discussion WHERE deleted = 0'),
		'getMaxSGPhotoID' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT MAX(attachmentid) AS maxid FROM {TABLE_PREFIX}attachment WHERE contenttypeid = {grouptypeid}'),
		'getMaxSGGallery' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT MAX(galleryid) AS maxid
			FROM {TABLE_PREFIX}socialgroup AS sg
			INNER JOIN (
				SELECT contentid AS galleryid
				FROM {TABLE_PREFIX}attachment
				WHERE contenttypeid = {grouptypeid}
			)
			AS gallerycheck ON gallerycheck.galleryid = sg.groupid'
		),
		'importSGDiscussions' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}node(userid, authorname, parentid, contenttypeid, title,
			description, deleteuserid, deletereason, sticky, publishdate,
			oldid, oldcontenttypeid, routeid, inlist, protected,
			showpublished, showapproved, approved, showopen,textcount, totalcount,
			textunpubcount, totalunpubcount, lastcontent, lastcontentauthor, lastauthorid,
			ipaddress, created)
			SELECT gm.postuserid, gm.postusername, n.nodeid AS parentid, {textTypeid}, gm.title,
			'', 0, '', 0 AS sticky, CASE WHEN (d.deleted = 1) THEN 0 ELSE gm.dateline END AS publishdate,
			d.discussionid, {discussionTypeid}, n.routeid, 1, 0,
			CASE WHEN (d.deleted = 0) THEN 1 ELSE 0 END AS showpublished,
			CASE WHEN (d.moderation = 0) THEN 1 ELSE 0 END AS showapproved,
			CASE WHEN (d.moderation = 0) THEN 1 ELSE 0 END AS approved,
			1, d.visible, d.visible,
			d.moderation, d.moderation, d.lastpost, d.lastposter, d.lastposterid,
			gm.ipaddress, gm.dateline
			FROM {TABLE_PREFIX}discussion AS d
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = d.groupid AND n.oldcontenttypeid = {grouptypeid}
			INNER JOIN {TABLE_PREFIX}groupmessage AS gm ON gm.gmid = d.firstpostid
			WHERE d.deleted = 0 AND d.discussionid > {startat} AND d.discussionid < ({startat} + {batchsize} -1)" ),
		'importSGGalleryNode' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}node(userid, authorname, parentid, contenttypeid, title,
			description, deleteuserid, deletereason, sticky, publishdate,
			oldid, oldcontenttypeid, routeid, inlist, protected,
			showpublished, showapproved, showopen,textcount, totalcount,
			textunpubcount, totalunpubcount, lastcontent, lastcontentauthor, lastauthorid,
			ipaddress, created)
			SELECT sg.creatoruserid, user.username, n.nodeid AS parentid, {gallerytypeid}, n.title,
			n.description, 0, '', 0 AS sticky, sg.dateline,
			gallerycheck.galleryid, 9983, n.routeid, 1, 0,
			1, 1, 1, gallerycheck.pubcount, gallerycheck.pubcount,
			gallerycheck.unpubcount, gallerycheck.unpubcount, 0, '', 0,
			n.ipaddress, sg.dateline
			FROM {TABLE_PREFIX}socialgroup AS sg
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = sg.groupid AND n.oldcontenttypeid = {grouptypeid}
			INNER JOIN (
				SELECT contentid AS galleryid, SUM(CASE WHEN state = 'visible' THEN 1 ELSE 0 END) AS pubcount, SUM(CASE WHEN state = 'moderation' THEN 1 ELSE 0 END) AS unpubcount
				FROM {TABLE_PREFIX}attachment
				WHERE contenttypeid = {grouptypeid} AND contentid > {startat} AND contentid < ({startat} + {batchsize} - 1)
				GROUP BY galleryid
			)
			AS gallerycheck ON gallerycheck.galleryid = sg.groupid
			INNER JOIN {TABLE_PREFIX}user AS user ON sg.creatoruserid = user.userid"),
		'fixLastGalleryData' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
						'query_string' => "UPDATE {TABLE_PREFIX}node AS n
				INNER JOIN (SELECT nodeid, parentid, publishdate, oldid, oldcontenttypeid, authorname, userid FROM {TABLE_PREFIX}node
				WHERE oldid > {startat} AND oldid < ({startat} + {batchsize} - 1) AND oldcontenttypeid = 9987 ORDER BY publishdate DESC, nodeid DESC)
				AS photo ON photo.parentid = n.nodeid
				SET n.lastcontentid = (CASE WHEN photo.publishdate >= n.lastcontent THEN photo.nodeid ELSE n.lastcontentid END),
				n.lastcontent = (CASE WHEN photo.publishdate >= n.lastcontent THEN photo.publishdate ELSE n.lastcontent END),
				n.lastcontentauthor = (CASE WHEN photo.publishdate >= n.lastcontent THEN photo.authorname ELSE n.lastcontentauthor END),
				n.lastauthorid = (CASE WHEN photo.publishdate >= n.lastcontent THEN photo.userid ELSE n.lastauthorid END)"),
		'importSGGallery' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}gallery(nodeid, caption)
			SELECT n.nodeid, CONCAT({caption}, ' - ', n.title)
			FROM {TABLE_PREFIX}attachment AS a
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = a.contentid AND n.oldcontenttypeid = 9983
			WHERE a.contenttypeid = {grouptypeid} AND a.contentid > {startat} AND a.contentid < ({startat} + {batchsize} - 1)
			GROUP BY a.contentid"),
		'importSGText' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}text(nodeid, rawtext)
			SELECT n.nodeid, CONCAT({caption}, ' - ', n.title)
			FROM {TABLE_PREFIX}attachment AS a
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = a.contentid AND n.oldcontenttypeid = 9983
			WHERE a.contenttypeid = {grouptypeid} AND a.contentid > {startat} AND a.contentid < ({startat} + {batchsize} - 1)
			GROUP BY a.contentid"),
		'importSGPhotoNodes' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}node(userid, authorname, parentid, starter, contenttypeid, title,
			description, deleteuserid, deletereason, sticky, publishdate,
			oldid, oldcontenttypeid, routeid, inlist, protected,
			showpublished, showapproved, showopen,textcount, totalcount,
			textunpubcount, totalunpubcount, lastcontent, lastcontentauthor, lastauthorid,
			ipaddress, created)
			SELECT a.userid, u.username, n.nodeid AS parentid, n.nodeid AS starter, {phototypeid}, a.caption,
			'', 0, '', 0 AS sticky, CASE WHEN (a.state = 'visible') THEN a.dateline ELSE 0 END AS publishdate,
			a.attachmentid, 9987, 0, 0, 0,
			CASE WHEN (a.state = 'visible') THEN 1 ELSE 0 END AS showpublished,
			CASE WHEN (a.state = 'visible') THEN 1 ELSE 0 END AS showapproved,
			1, 0, 0, 0, 0, a.dateline, '', 0,
			n.ipaddress, a.dateline
			FROM {TABLE_PREFIX}attachment AS a
			INNER JOIN {TABLE_PREFIX}user AS u ON u.userid = a.userid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = a.contentid AND n.oldcontenttypeid = 9983 AND a.contenttypeid = {grouptypeid}
			WHERE a.attachmentid > {startat} AND a.attachmentid < ({startat} + {batchsize} - 1)"),
		'importSGPhotos' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}photo(nodeid, filedataid, caption, height, width)
			SELECT n.nodeid, a.filedataid, a.caption, fd.height, fd.width
			FROM {TABLE_PREFIX}attachment AS a
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = a.attachmentid AND n.oldcontenttypeid = 9987
			INNER JOIN {TABLE_PREFIX}filedata AS fd ON a.filedataid = fd.filedataid
			WHERE a.contenttypeid = {grouptypeid} AND a.attachmentid > {startat} AND a.attachmentid < ({startat} + {batchsize} - 1)"),
		'updateDiscussionLastContentId' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}node AS node
			INNER JOIN {TABLE_PREFIX}discussion AS d ON d.discussionid = node.oldid
			INNER JOIN {TABLE_PREFIX}node AS lm ON lm.oldid = d.lastpostid AND lm.oldcontenttypeid = {messageTypeid}
			SET node.lastcontentid = lm.nodeid,
				node.lastcontentauthor = lm.authorname, node.lastcontent = lm.publishdate, node.lastauthorid = lm.userid
			WHERE node.oldcontenttypeid = {discussionTypeid} AND node.nodeid > {startat}
			AND node.nodeid < ({startat} + {batchsize} -1)" ),
		'importSGDiscussionText' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}text(nodeid, rawtext)
			SELECT n.nodeid, gm.pagetext
			FROM {TABLE_PREFIX}discussion AS d
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = d.discussionid AND n.oldcontenttypeid = {discussionTypeid}
			INNER JOIN {TABLE_PREFIX}groupmessage AS gm ON gm.gmid = d.firstpostid
			WHERE d.deleted = 0 AND d.discussionid > {startat} AND d.discussionid < ({startat} + {batchsize} -1)" ),
		'importSGPosts' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}node(userid, authorname, parentid, contenttypeid, title,
			description, deleteuserid, deletereason, sticky, publishdate,
			oldid, oldcontenttypeid, routeid, inlist, protected,
			showpublished, showapproved, showopen, ipaddress, starter, created)
			SELECT gm.postuserid, gm.postusername, n.nodeid AS parentid, {textTypeid}, gm.title,
			'', 0, '', 0 AS sticky, CASE WHEN (gm.state = 'visible') THEN gm.dateline ELSE 0 END AS publishdate,
			gm.gmid, {messageTypeid}, n.routeid, 1, 0,
			CASE WHEN (gm.state = 'visible') THEN 1 ELSE 0 END AS showpublished,
			CASE WHEN (gm.state = 'visible') THEN 1 ELSE 0 END AS showapproved,
			1, gm.ipaddress, n.starter, gm.dateline
			FROM {TABLE_PREFIX}groupmessage AS gm
			INNER JOIN {TABLE_PREFIX}discussion AS d ON gm.discussionid = d.discussionid AND gm.gmid <> d.firstpostid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = gm.discussionid AND n.oldcontenttypeid = {discussionTypeid}
			WHERE gm.gmid > {startat} AND gm.gmid < ({startat} + {batchsize} -1)"),
		'importSGPostText' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}text(nodeid, rawtext)
			SELECT n.nodeid, gm.pagetext
			FROM {TABLE_PREFIX}groupmessage AS gm
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = gm.gmid AND n.oldcontenttypeid = {messageTypeid}
			WHERE gm.gmid > {startat} AND gm.gmid < ({startat} + {batchsize} -1)" ),
		'getMaxSGPost' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT MAX(gmid) AS maxid
			FROM {TABLE_PREFIX}discussion AS d
			INNER JOIN {TABLE_PREFIX}groupmessage AS gm ON gm.gmid = d.firstpostid"),
		'addGroupOwners' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}groupintopic(userid, groupid, nodeid)
			SELECT n.userid, {groupid}, n.nodeid
			FROM {TABLE_PREFIX}node AS n
			LEFT JOIN {TABLE_PREFIX}groupintopic AS existing ON existing.userid = n.userid AND existing.groupid = {groupid} AND existing.nodeid = n.nodeid
			WHERE n.oldcontenttypeid = {socialgroupType} AND existing.groupid IS NULL' ),
		'addGroupMembers' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}groupintopic(userid, groupid, nodeid)
			SELECT member.userid, {groupid}, n.nodeid
			FROM {TABLE_PREFIX}socialgroupmember AS member INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = member.groupid AND n.oldcontenttypeid = {socialgroupType}
			LEFT JOIN {TABLE_PREFIX}groupintopic AS existing ON existing.userid = member.userid AND existing.nodeid = n.nodeid
			WHERE existing.groupid IS NULL' ),
		'getMissingSocialGroupPhotos' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT a.filedataid, a.contentid, a.caption, n.nodeid AS parentid, 9987 AS oldcontenttypeid, a.attachmentid AS oldid,
			n.userid, n.authorname, f.height, f.width
			FROM {TABLE_PREFIX}attachment AS a
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = a.contentid AND a.contenttypeid = n.oldcontenttypeid AND a.contenttypeid = {groupcontenttype}
			INNER JOIN {TABLE_PREFIX}filedata AS f ON f.filedataid = a.filedataid
			LEFT JOIN {TABLE_PREFIX}node AS existing ON existing.oldid = a.attachmentid AND n.oldcontenttypeid = 9987
			WHERE existing.nodeid IS NULL ORDER BY a.contentid' ),
		'getMax4VM' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT MAX(vmid) AS maxid FROM {TABLE_PREFIX}visitormessage'),
		'ImportVisitorMessages' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}node(userid, authorname, parentid, contenttypeid, title,
			description, deleteuserid, deletereason, sticky, publishdate,
			oldid, oldcontenttypeid, routeid, inlist, protected,
			showpublished, showapproved, approved, showopen, ipaddress, setfor)
			SELECT vm.postuserid, vm.postusername, {vmChannel}, {texttypeid}, vm.title,
			\'\', 0, \'\', 0, CASE WHEN vm.state <> \'deleted\' THEN vm.dateline ELSE 0 END AS publishdate,
			vm.vmid AS oldid, {visitorMessageType} AS oldcontenttypeid, {vmRouteid}, 1, 0,
			CASE WHEN vm.state=\'deleted\' THEN 0 ELSE 1 END AS showpublished, CASE WHEN vm.state=\'moderation\' THEN 0 ELSE 1 END AS showapproved, CASE WHEN vm.state=\'moderation\' THEN 0 ELSE 1 END AS approved, 1, vm.ipaddress, vm.userid AS setfor
			FROM {TABLE_PREFIX}visitormessage AS vm
			WHERE vm.vmid > {startat} AND vm.vmid < ({startat} + {batchsize} -1) ' ),
		'importVMText' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}text(nodeid, rawtext)
			SELECT node.nodeid, vm.pagetext AS rawtext
			FROM {TABLE_PREFIX}visitormessage AS vm
			INNER JOIN {TABLE_PREFIX}node AS node ON node.oldid = vm.vmid AND node.oldcontenttypeid = {visitorMessageType}
			WHERE vm.vmid > {startat} AND vm.vmid < ({startat} + {batchsize} -1)' ),
		'getMaxvB4Album' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(albumid) AS maxid
			FROM {TABLE_PREFIX}album' ),
		'getMaxvB5Album' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(oldid) AS maxid	FROM {TABLE_PREFIX}node WHERE oldcontenttypeid = {albumtypeid}' ),
		'importAlbumNodes' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}node (publishdate, title, userid, authorname,htmltitle,
			parentid, created, oldid, oldcontenttypeid,`open`,
			showopen, approved, showapproved, showpublished, protected,
			routeid, contenttypeid, deleteuserid, deletereason, sticky)
			SELECT al.createdate, al.title, al.userid, u.username, al.title,
			{albumChannel}, al.createdate, al.albumid, {albumtype},1,
			1, 1, 1, 1, 0,
			{routeid}, {gallerytype}, 0, \'\', 0
			FROM {TABLE_PREFIX}album AS al INNER JOIN {TABLE_PREFIX}user AS u ON u.userid = al.userid
			WHERE al.albumid > {startat} AND al.albumid < ({startat} + {batchsize})'
			),
		'importAlbums2Gallery' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}gallery(nodeid, caption)
			SELECT nodeid, title
			FROM {TABLE_PREFIX}node WHERE oldcontenttypeid = {albumtype}
			AND oldid > {startat} AND oldid < ({startat} + {batchsize})'
		),
		'getMaxvB4Photo' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(attachmentid) AS maxid
			FROM {TABLE_PREFIX}attachment WHERE contenttypeid = {albumtype}' ),
		'importPhotoNodes' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}node(publishdate, title, userid, authorname,htmltitle,
			parentid, starter, created, oldid, oldcontenttypeid,`open`,
			showopen, approved, showapproved, showpublished, protected,
			routeid, contenttypeid, deleteuserid, deletereason, sticky )
			SELECT at.dateline,CASE when at.caption IS NULL then at.filename ELSE at.caption END,
			at.userid, u.username,	CASE when at.caption IS NULL then at.filename ELSE at.caption END,
			n.nodeid AS parentid, n.nodeid AS starter, at.dateline, at.attachmentid, 9986, 1,
			1, 1, 1, 1, 0,
			n.routeid, {phototype}, 0, \'\', 0
			FROM {TABLE_PREFIX}attachment AS at
			INNER JOIN {TABLE_PREFIX}user AS u ON u.userid = at.userid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = at.contentid AND n.oldcontenttypeid = {albumtype} AND at.contenttypeid = {albumtype}
			WHERE at.attachmentid > {startat} AND at.attachmentid < ({startat} + {batchsize})'
		),
		'importPhotos2Gallery' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}photo(nodeid, filedataid, caption, height, width)
			SELECT n.nodeid, at.filedataid, CASE when at.caption IS NULL then at.filename ELSE at.caption END,
				f.height, f.width
			FROM {TABLE_PREFIX}attachment AS at
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = at.attachmentid AND n.oldcontenttypeid = 9986
			INNER JOIN {TABLE_PREFIX}filedata AS f ON f.filedataid = at.filedataid
			WHERE n.oldid > {startat} AND oldid < ({startat} + {batchsize})'
		),
		'createGenChannel' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}channel (nodeid, guid) SELECT nodeid, {guid}
			FROM {TABLE_PREFIX}node
			WHERE oldcontenttypeid = {oldcontenttypeid}'),
		'setModeratorNodeid' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}moderator AS m ON m.forumid = n.oldid AND n.oldcontenttypeid ={forumTypeId}
			 AND m.nodeid IS NULL
			SET m.nodeid = n.nodeid' ),
		'setModeratorlogThreadid' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}moderatorlog AS m ON m.threadid = n.oldid AND n.oldcontenttypeid ={threadTypeId}
			 AND m.nodeid IS NULL
			SET m.nodeid = n.nodeid' ),
		'setAccessNodeid' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}access AS a ON a.forumid = n.oldid AND n.oldcontenttypeid ={forumTypeId}
			 AND a.nodeid IS NULL
			SET a.nodeid = n.nodeid'),
		'getRootForumPerms' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT fp.usergroupid, f.forumid, fp.forumpermissions FROM
			{TABLE_PREFIX}forum AS f INNER JOIN {TABLE_PREFIX}forumpermission AS fp ON fp.forumid = f.forumid
			WHERE f.parentid < 1
			ORDER BY usergroupid, forumid' ),
		'getMaxImportedPost' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(oldid) AS maxid FROM {TABLE_PREFIX}node WHERE oldcontenttypeid = {contenttypeid}'),
		'getMaxFixedPMResponse' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(oldid) AS maxid
			FROM {TABLE_PREFIX}node
			WHERE oldcontenttypeid = {contenttypeid} AND starter = parentid'),
		'getMaxNodeRecordFixed' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(nodeid) AS maxid
			FROM {TABLE_PREFIX}node
			WHERE oldcontenttypeid = {contenttypeid} AND starter = parentid'),
		'getMaxImportedSGPhoto' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(n.oldid) AS maxid
			FROM {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}attachment AS a ON node.oldid = a.attachmentid AND a.contenttypeid = {grouptypeid}
			WHERE n.oldcontenttypeid = {phototypeid}'
		),
		'getMaxBlogUserId' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(userid) AS maxuserId FROM {TABLE_PREFIX}node WHERE oldcontenttypeid = {contenttypeid}'),
		'getBlogs4Import' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT b.userid, u.username, b.title, b.dateline, b.blogid
			FROM {TABLE_PREFIX}blog AS b
			INNER JOIN {TABLE_PREFIX}blog_text AS bt ON bt.blogtextid = b.firstblogtextid
			INNER JOIN {TABLE_PREFIX}user u ON b.userid = u.userid
			WHERE b.userid > {maxexisting}
			GROUP BY b.userid LIMIT {blocksize}'),
		'importBlogStarters' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}node(contenttypeid, parentid, title, keywords, htmltitle,
			publishdate, userid, authorname, oldid, oldcontenttypeid,
			showpublished, inlist, routeid, showapproved, textcount,
			totalcount, textunpubcount, totalunpubcount, lastcontent, lastcontentauthor,
			lastauthorid, created)
			SELECT {texttype}, parent.nodeid, bt.title, bt.title, bt.title,
			case WHEN bt.state = \'visible\' THEN bt.dateline else 0 end, blog.userid, blog.username, bt.blogtextid, 9985,
			case WHEN bt.state = \'visible\' THEN 1 else 0 end , case WHEN bt.state = \'visible\' THEN 1 else 0 end,
			parent.routeid, 1, blog.comments_visible,
			blog.comments_visible, blog.comments_moderation, blog.comments_moderation, blog.lastcomment, blog.lastcommenter,
			bt.username, bt.dateline
			FROM {TABLE_PREFIX}blog AS blog
			INNER JOIN {TABLE_PREFIX}blog_text AS bt ON bt.blogtextid = blog.firstblogtextid
			INNER JOIN {TABLE_PREFIX}node AS parent ON parent.userid = blog.userid AND parent.parentid = {bloghome}
				AND parent.oldcontenttypeid = 9999
			LEFT JOIN {TABLE_PREFIX}blog_text AS last ON last.blogtextid = blog.lastblogtextid
			WHERE bt.blogtextid > {startat} AND bt.blogtextid <({startat} + {batchsize} -1)
			ORDER BY bt.blogtextid'),
		'setStarter' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node
			SET starter = nodeid
			WHERE oldcontenttypeid = {contenttypeid} AND oldid > {startat}'),
		'addClosureSelf' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}closure (parent, child, depth)
			SELECT node.nodeid, node.nodeid, 0
			FROM {TABLE_PREFIX}node AS node
			WHERE node.oldcontenttypeid = {contenttypeid} AND node.oldid > {startat} AND node.oldid < ({startat} + {batchsize} -1)'),
		'addClosureParents' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}closure (parent, child, depth)
			SELECT parent.parent, node.nodeid, parent.depth + 1
			FROM {TABLE_PREFIX}node AS node
			INNER JOIN {TABLE_PREFIX}closure AS parent ON parent.child = node.parentid
			WHERE node.oldcontenttypeid = {contenttypeid} AND node.oldid > {startat} AND node.oldid < ({startat} + {batchsize} -1)'),
		'getProcessedCount' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT ROW_COUNT() AS recs'),
		'importBlogResponses' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}node(contenttypeid, parentid, starter, title, keywords, htmltitle,
			publishdate, userid, authorname, oldid, oldcontenttypeid, showpublished, showapproved, inlist, routeid, created)
			SELECT DISTINCT {texttypeid}, starter.nodeid, starter.nodeid,
			CASE WHEN IFNULL(bt.title, \'\') = \'\' THEN starter.title ELSE bt.title END,
			CASE WHEN IFNULL(bt.title, \'\') = \'\' THEN starter.title ELSE bt.title END,
			CASE WHEN IFNULL(bt.title, \'\') = \'\'THEN starter.title ELSE bt.title END,
			case WHEN bt.state = \'visible\' THEN bt.dateline else 0 end, bt.userid, bt.username, bt.blogtextid, 9984,
			case WHEN bt.state = \'visible\' THEN 1 else 0 end, case WHEN bt.state = \'visible\' THEN 1 else 0 end,
			1, starter.routeid, bt.dateline
			FROM {TABLE_PREFIX}blog AS blog
			INNER JOIN {TABLE_PREFIX}blog_text AS bt ON bt.blogid = blog.blogid AND bt.blogtextid <> blog.firstblogtextid
			INNER JOIN {TABLE_PREFIX}node AS starter ON starter.oldid = blog.firstblogtextid AND starter.oldcontenttypeid = 9985
			WHERE bt.blogtextid > {startat}
			ORDER BY bt.blogtextid
			LIMIT {batchsize}'),
		'importBlogTextNoState' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}text(nodeid, rawtext)
			SELECT n.nodeid, bt.pagetext
			FROM {TABLE_PREFIX}node AS n INNER JOIN {TABLE_PREFIX}blog_text AS bt
			ON bt.blogtextid = n.oldid AND n.oldcontenttypeid = {contenttypeid}
			WHERE bt.blogtextid > {startat}'),
		'importBlogText' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}text(nodeid, rawtext, htmlstate)
			SELECT n.nodeid, bt.pagetext, htmlstate
			FROM {TABLE_PREFIX}node AS n INNER JOIN {TABLE_PREFIX}blog_text AS bt
			ON bt.blogtextid = n.oldid AND n.oldcontenttypeid = {contenttypeid}
			WHERE bt.blogtextid > {startat}'),
		'updateForumLast' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}forum AS f ON f.forumid = n.oldid AND n.oldcontenttypeid = {forumTypeid}
			INNER JOIN {TABLE_PREFIX}node AS lp ON lp.oldid = f.lastpostid AND lp.oldcontenttypeid = {postTypeid}
			SET n.textcount = f.threadcount, n.totalcount = f.replycount, n.lastcontent = f.lastpost, n.lastcontentid = lp.nodeid'),
		'updateForumLastThreadOnly' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}forum AS f ON f.forumid = n.oldid AND n.oldcontenttypeid = {forumTypeid}
			INNER JOIN {TABLE_PREFIX}node AS lp ON lp.oldid = f.lastthreadid AND lp.oldcontenttypeid = {threadTypeid} AND n.lastcontent = 0
			SET n.textcount = f.threadcount, n.totalcount = f.replycount, n.lastcontent = f.lastpost, n.lastcontentid = lp.nodeid'),
		'updateThreadLast' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}thread AS th ON th.threadid = n.oldid
			INNER JOIN {TABLE_PREFIX}node AS lp ON lp.oldid = th.lastpostid AND lp.oldcontenttypeid = {postTypeid}
			INNER JOIN {TABLE_PREFIX}post AS last ON last.postid = th.lastpostid
			SET n.textcount = th.replycount, n.totalcount = th.replycount, n.textunpubcount = th.hiddencount, n.totalunpubcount = th.hiddencount,
			n.lastcontent = th.lastpost, n.lastcontentauthor = last.username, n.lastauthorid = last.userid, n.lastcontentid = lp.nodeid
			WHERE n.oldcontenttypeid = {threadTypeid} AND n.oldid > {startat} AND n.oldid < ({startat} + {batchsize} - 1)'),
		'insertCMSArticles' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}node(contenttypeid, parentid, title, description, keywords, htmltitle, urlident,
			publishdate, oldid, oldcontenttypeid, created, inlist, routeid, showpublished, showapproved, showopen)
			SELECT {textTypeId}, node.nodeid, ni.title, ni.description, ni.keywords, ni.html_title, n.url,
		 	n.publishdate, n.nodeid, n.contenttypeid, n.publishdate, 1, node.routeid, 1, 1, 1
			FROM {TABLE_PREFIX}cms_node AS n
			INNER JOIN {TABLE_PREFIX}cms_nodeinfo AS ni ON ni.nodeid = n.nodeid
			INNER JOIN {TABLE_PREFIX}node AS node ON node.oldid = n.parentnode AND node.oldcontenttypeid = {sectionTypeId}
			WHERE n.contenttypeid = {articleTypeId} AND n.nodeid > {startat} AND n.nodeid < ({startat} + {batchsize}) ORDER BY n.nodeid'),
		'insertCMSText' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}text(nodeid, previewtext,
			previewimage, previewvideo, imageheight, imagewidth,
			rawtext, htmlstate)
			SELECT node.nodeid, a.previewtext,
			a.previewimage, a.previewvideo, a.imageheight, a.imagewidth,
			a.pagetext, a.htmlstate
			FROM {TABLE_PREFIX}cms_node AS n
			INNER JOIN {TABLE_PREFIX}cms_nodeinfo AS ni ON ni.nodeid = n.nodeid
			INNER JOIN {TABLE_PREFIX}cms_article AS a ON a.contentid = n.contentid
			INNER JOIN {TABLE_PREFIX}node AS node ON node.oldid = n.nodeid AND node.oldcontenttypeid = {articleTypeId}
			WHERE n.nodeid > {startat} AND n.nodeid < ({startat} + {batchsize}) ORDER BY n.nodeid'),
		'updateChannelRoutes' =>array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}routenew AS pr ON pr.routeid = n.routeid AND pr.class = \'vB5_Route_Channel\'
			INNER JOIN {TABLE_PREFIX}routenew AS cr ON cr.prefix = pr.prefix AND cr.class = \'vB5_Route_Conversation\'
			SET n.routeid = cr.routeid
			WHERE n.oldcontenttypeid = {contenttypeid} AND n.oldid > {startat} AND n.oldid < ({startat} + {batchsize} + 1)'),
		'updateChannelCounts' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'Update {TABLE_PREFIX}node AS n INNER JOIN
			(
				SELECT parent.nodeid,
				SUM(CASE WHEN child.contenttypeid IN ({textTypeid}, {pollTypeid}) THEN child.showpublished ELSE 0 END) AS published,
				SUM(CASE WHEN child.contenttypeid IN ({textTypeid}, {pollTypeid}) AND child.showpublished=0 THEN 1 ELSE 0 END) AS unpublished,
				SUM(child.totalcount) AS totalcount, SUM(child.totalunpubcount) AS totalunpubcount
				FROM {TABLE_PREFIX}node AS parent INNER JOIN {TABLE_PREFIX}node AS child ON child.parentid = parent.nodeid
				WHERE parent.contenttypeid = {channelTypeid} AND child.contenttypeid IN ({textTypeid}, {pollTypeid}) GROUP BY parent.nodeid
			) AS sub ON sub.nodeid = n.nodeid
			SET n.textcount = sub.published,
			n.textunpubcount = sub.unpublished,
			n.totalcount = sub.published + sub.totalcount,
			n.textunpubcount = sub.unpublished + sub.totalunpubcount'),
		'updateChannelLast' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS parent
			INNER JOIN {TABLE_PREFIX}node AS child ON child.parentid = parent.nodeid
			SET parent.lastcontentid = CASE WHEN child.lastcontent >= parent.lastcontent THEN child.lastcontentid ELSE parent.lastcontentid END
			WHERE parent.contenttypeid = {channeltypeid}
			 AND child.nodeid > {startat} AND child.nodeid < ({startat} + {batchsize})'),
		'updateCategoryLast' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS parent
			INNER JOIN {TABLE_PREFIX}node AS child ON child.parentid = parent.nodeid AND child.contenttypeid = {channeltypeid}
			SET parent.lastcontentid = CASE WHEN child.lastcontent >= parent.lastcontent THEN child.lastcontentid ELSE parent.lastcontentid END
			WHERE parent.contenttypeid = {channeltypeid}'),
		'getMaxNodeid' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(nodeid) AS maxid FROM {TABLE_PREFIX}node'),
		'getMaxNodeidForOldContent' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(nodeid) AS maxid FROM {TABLE_PREFIX}node WHERE oldcontenttypeid IN ({oldcontenttypeid})'),
		'updateModeratorPermission' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}permission AS p
 			INNER JOIN {TABLE_PREFIX}usergroup AS ug ON ug.usergroupid = p.groupid
			SET p.moderatorpermissions = p.moderatorpermissions | {modPermissions} WHERE ug.systemgroupid IN ({systemgroups}) AND p.forumpermissions > 0'),
		'hidePasswordForums' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}permission (groupid, nodeid, forumpermissions, moderatorpermissions, createpermissions, edit_time,
				require_moderate, maxtags, maxstartertags, maxothertags, maxattachments)
			SELECT pwd.usergroupid, node.nodeid, 0, 0, 0, 5, 0, 0, 0, 0, 0
			FROM (SELECT ug.usergroupid, f.forumid
			FROM {TABLE_PREFIX}forum AS f, {TABLE_PREFIX}usergroup AS ug
			WHERE f.password IS NOT NULL AND f.password <> \'\') AS pwd
			INNER JOIN {TABLE_PREFIX}node AS node ON node.oldid = pwd.forumid AND node.oldcontenttypeid = {forumTypeid}
			LEFT JOIN {TABLE_PREFIX}permission AS ex ON ex.nodeid = node.nodeid AND ex.groupid = pwd.usergroupid
			WHERE ex.nodeid IS NULL'),
		'setForumPermissions' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}permission(groupid, nodeid, forumpermissions, moderatorpermissions, createpermissions, edit_time,
				require_moderate, maxtags, maxstartertags, maxothertags, maxattachments)
				SELECT fp.usergroupid, node.nodeid, fp.forumpermissions, 0,
				CASE WHEN (fp.forumpermissions & 16 > 0)
				THEN ( 2 | 2048 | 4096 | 8192 | 16384 | 32768 | 65536 | 131072 | 262144) ELSE 0 END AS createpermissions,
				CASE WHEN p.nodeid IS NULL THEN {editTime} ELSE p.edit_time END,
				CASE WHEN p.nodeid IS NULL THEN 0 ELSE p.require_moderate END,
				CASE WHEN p.nodeid IS NULL THEN {maxtags} ELSE p.maxtags END,
				CASE WHEN p.nodeid IS NULL THEN {maxstartertags} ELSE p.maxstartertags END,
				CASE WHEN p.nodeid IS NULL THEN {maxothertags} ELSE p.maxothertags END,
				CASE WHEN p.nodeid IS NULL THEN {maxattachments} ELSE p.maxattachments END
				FROM {TABLE_PREFIX}forumpermission AS fp
				INNER JOIN {TABLE_PREFIX}node AS node ON node.oldid = fp.forumid AND node.oldcontenttypeid = {forumTypeid}
				LEFT JOIN {TABLE_PREFIX}permission AS p ON p.groupid = fp.usergroupid AND p.nodeid = 1
				LEFT JOIN {TABLE_PREFIX}permission AS ex ON ex.nodeid = node.nodeid AND ex.groupid = fp.usergroupid
				WHERE ex.nodeid IS NULL ORDER BY node.nodeid, fp.usergroupid'),
		'clearUserStyle'=> array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}user SET styleid = 0'),
		'missingClosureByType'=> array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT nodeid
			FROM {TABLE_PREFIX}node AS node
			LEFT JOIN {TABLE_PREFIX}closure AS closure ON closure.child = node.nodeid AND closure.depth = 0
			WHERE closure.parent IS NULL AND oldcontenttypeid = {oldcontenttypeid} LIMIT {batchsize} '),
		'addClosureSelfForNodes' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}closure (parent, child, depth)
			SELECT node.nodeid, node.nodeid, 0
			FROM {TABLE_PREFIX}node AS node
			WHERE node.nodeid IN ({nodeid})'),
		'addClosureParentsForNodes' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}closure (parent, child, depth)
			SELECT parent.parent, node.nodeid, parent.depth + 1
			FROM {TABLE_PREFIX}node AS node
			INNER JOIN {TABLE_PREFIX}closure AS parent ON parent.child = node.parentid
			WHERE node.nodeid IN ({nodeid})'),
		'updateBlogModerated' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'update {TABLE_PREFIX}node AS node INNER JOIN {TABLE_PREFIX}blog_text AS bt
			ON bt.blogtextid = node.oldid AND node.oldcontenttypeid = 9984
			SET node.showpublished = 0, node.showapproved = 0, node.publishdate = 0
			WHERE bt.state <> \'visible\''),
		'updateBlogCounts' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'update {TABLE_PREFIX}node AS node INNER JOIN
			(
			 select parentid, count(*) AS count, sum(showpublished) AS textcount
			 FROM {TABLE_PREFIX}node WHERE oldcontenttypeid = 9984
			 GROUP BY parentid
			) as ch ON ch.parentid = node.nodeid
			SET node.textcount = ch.textcount,node.totalcount = ch.textcount,
			node.textunpubcount = (ch.count - ch.textcount),node.totalunpubcount = (ch.count - ch.textcount)'),
		'getMaxImportedSubscription' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(oldid) AS maxid
			FROM {TABLE_PREFIX}subscribediscussion
			WHERE oldtypeid = {oldtypeid}'),
		'getMaxGroupDiscussionSubscriptionId' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(sd.subscribediscussionid) AS maxid
			FROM {TABLE_PREFIX}subscribediscussion AS sd
			INNER JOIN {TABLE_PREFIX}discussion AS d ON sd.discussionid = d.discussionid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = d.discussionid AND n.oldcontenttypeid = {discussiontypeid}
			WHERE sd.oldtypeid = 0'),
		'importDiscussionSubscriptions' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}subscribediscussion(userid, discussionid, emailupdate, oldid, oldtypeid)
			SELECT sd.userid, n.nodeid, sd.emailupdate, sd.subscribediscussionid, {discussiontypeid}
			FROM {TABLE_PREFIX}subscribediscussion AS sd
			INNER JOIN {TABLE_PREFIX}discussion AS d ON sd.discussionid = d.discussionid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = d.discussionid AND n.oldcontenttypeid = {discussiontypeid}
			WHERE sd.oldtypeid = 0 AND sd.subscribediscussionid > {startat} AND sd.subscribediscussionid < ({startat} + {batchsize} + 1)"
		),
		'getMaxForumSubscriptionId' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(sf.subscribeforumid) AS maxid
			FROM {TABLE_PREFIX}subscribeforum AS sf
			INNER JOIN {TABLE_PREFIX}forum AS f ON f.forumid = sf.forumid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = f.forumid AND n.oldcontenttypeid = {forumtypeid}'),
		'importForumSubscriptions' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}subscribediscussion(userid, discussionid, emailupdate, oldid, oldtypeid)
			SELECT sf.userid, n.nodeid, sf.emailupdate, sf.subscribeforumid, {forumtypeid}
			FROM {TABLE_PREFIX}subscribeforum AS sf
			INNER JOIN {TABLE_PREFIX}forum AS f ON sf.forumid = f.forumid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = f.forumid AND n.oldcontenttypeid = {forumtypeid}
			WHERE sf.subscribeforumid > {startat} AND sf.subscribeforumid < ({startat} + {batchsize} + 1)"
		),
		'getMaxThreadSubscriptionId' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(st.subscribethreadid) AS maxid
			FROM {TABLE_PREFIX}subscribethread AS st
			INNER JOIN {TABLE_PREFIX}thread AS th ON th.threadid = st.threadid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = th.threadid AND n.oldcontenttypeid = {threadtypeid}'),
		'importThreadSubscriptions' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}subscribediscussion(userid, discussionid, emailupdate, oldid, oldtypeid)
			SELECT st.userid, n.nodeid, st.emailupdate, st.subscribethreadid, {threadtypeid}
			FROM {TABLE_PREFIX}subscribethread AS st
			INNER JOIN {TABLE_PREFIX}thread AS th ON th.threadid = st.threadid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = th.threadid AND n.oldcontenttypeid = {threadtypeid}
			WHERE st.subscribethreadid > {startat} AND st.subscribethreadid < ({startat} + {batchsize} + 1)"
		),
		'getMaxGroupSubscriptionId' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(sg.subscribegroupid) AS maxid
			FROM {TABLE_PREFIX}subscribegroup AS sg
			INNER JOIN {TABLE_PREFIX}socialgroup AS gr ON gr.groupid = sg.groupid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = gr.groupid AND n.oldcontenttypeid = {grouptypeid}'),
		'importGroupSubscriptions' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}subscribediscussion(userid, discussionid, emailupdate, oldid, oldtypeid)
			SELECT sg.userid, n.nodeid, (CASE sg.emailupdate
				WHEN 'none' THEN 0
				WHEN 'daily' THEN  2
				WHEN 'weekly' THEN 3 END) AS emailupdate, sg.subscribegroupid, {grouptypeid}
			FROM {TABLE_PREFIX}subscribegroup AS sg
			INNER JOIN {TABLE_PREFIX}socialgroup AS gr ON sg.groupid = gr.groupid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = gr.groupid AND n.oldcontenttypeid = {grouptypeid}
			WHERE sg.subscribegroupid > {startat} AND sg.subscribegroupid < ({startat} + {batchsize} + 1)"
		),
		'deleteGroupSubscribedDiscussion' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "DELETE sd.*
			FROM {TABLE_PREFIX}subscribediscussion AS sd
			INNER JOIN {TABLE_PREFIX}discussion AS d ON sd.discussionid = d.discussionid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = d.discussionid AND n.oldcontenttypeid = {discussiontypeid}
			WHERE sd.oldtypeid = 0"
		),
		'getNextBlogUserid' =>  array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT min(userid) AS userid FROM {TABLE_PREFIX}blog WHERE userid > {startat}'),
		'getMissedBlogStarters' =>  array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT bt.blogtextid, parent.nodeid, parent.routeid
			FROM {TABLE_PREFIX}blog AS blog
			INNER JOIN {TABLE_PREFIX}blog_text AS bt ON bt.blogtextid = blog.firstblogtextid
			INNER JOIN {TABLE_PREFIX}node AS parent ON parent.userid = blog.userid
				AND parent.oldcontenttypeid = 9999
			LEFT JOIN {TABLE_PREFIX}node AS existing ON existing.oldid = bt.blogtextid AND existing.oldcontenttypeid = 9985
			WHERE blog.userid = {userid} AND existing.nodeid IS NULL'),
		'importMissingBlogStarters' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}node(contenttypeid, parentid, title, keywords, htmltitle,
			publishdate, userid, authorname, oldid, oldcontenttypeid,
			showpublished, inlist, routeid, showapproved, textcount,
			totalcount, textunpubcount, totalunpubcount, lastcontent, lastcontentauthor,
			lastauthorid, created)
			SELECT {texttype}, {parentid}, bt.title, bt.title, bt.title,
			case WHEN bt.state = \'visible\' THEN bt.dateline else 0 end, blog.userid, blog.username, bt.blogtextid, 9985,
			case WHEN bt.state = \'visible\' THEN 1 else 0 end, case WHEN bt.state = \'visible\' THEN 1 else 0 end,
			{routeid}, 1, blog.comments_visible,
			blog.comments_visible, blog.comments_moderation, blog.comments_moderation, blog.lastcomment, blog.lastcommenter,
			bt.username, bt.dateline
			FROM {TABLE_PREFIX}blog AS blog
			INNER JOIN {TABLE_PREFIX}blog_text AS bt ON bt.blogtextid = blog.firstblogtextid
			LEFT JOIN {TABLE_PREFIX}blog_text AS last ON last.blogtextid = blog.lastblogtextid
			WHERE bt.blogtextid IN ({blogtextids})'),
		'fixMissingBlogStarter' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node
			SET starter = nodeid WHERE oldcontenttypeid = 9985 AND nodeid > {startnodeid}'),
		'importMissingBlogText' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}text(nodeid, rawtext, htmlstate)
			SELECT n.nodeid, bt.pagetext, htmlstate
			FROM {TABLE_PREFIX}node AS n INNER JOIN {TABLE_PREFIX}blog_text AS bt
			ON bt.blogtextid = n.oldid AND n.oldcontenttypeid in (9984, 9985)
			WHERE n.nodeid > {startnodeid}'),
		'createMissingBlogClosureSelf' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}closure(parent, child, depth, publishdate)
			SELECT node.nodeid, node.nodeid, 0, node.publishdate FROM {TABLE_PREFIX}node AS node
			WHERE node.nodeid > {startnodeid}'),
		'createMissingBlogClosurefromParent' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}closure(parent, child, depth, publishdate)
			SELECT parent.parent, node.nodeid, parent.depth + 1, node.publishdate FROM {TABLE_PREFIX}node AS node
 			 INNER JOIN {TABLE_PREFIX}closure AS parent ON parent.child = node.parentid
			LEFT JOIN {TABLE_PREFIX}closure AS existing on existing.child = node.nodeid AND existing.parent = parent.parent
			WHERE node.nodeid > {startnodeid} AND node.oldcontenttypeid = {oldcontenttypeid}'),
		'importMissingBlogResponses' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}node(contenttypeid, parentid, starter, title, keywords, htmltitle,
			publishdate, userid, authorname, oldid, oldcontenttypeid, showpublished, showapproved, inlist, routeid, created)
			SELECT DISTINCT {texttype}, starter.nodeid, starter.nodeid,
			CASE WHEN IFNULL(bt.title, \'\') = \'\' THEN starter.title ELSE bt.title END,
			CASE WHEN IFNULL(bt.title, \'\') = \'\' THEN starter.title ELSE bt.title END,
			CASE WHEN IFNULL(bt.title, \'\') = \'\'THEN starter.title ELSE bt.title END,
			case WHEN bt.state = \'visible\' THEN bt.dateline else 0 end, bt.userid, bt.username, bt.blogtextid, 9984,
			case WHEN bt.state = \'visible\' THEN 1 else 0 end, case WHEN bt.state = \'visible\' THEN 1 else 0 end,
			1, starter.routeid, bt.dateline
			FROM {TABLE_PREFIX}blog_text AS firstbt
			INNER JOIN {TABLE_PREFIX}blog AS blog ON blog.blogid = firstbt.blogid
			INNER JOIN {TABLE_PREFIX}blog_text AS bt ON bt.blogid = blog.blogid AND bt.blogtextid <> blog.firstblogtextid
			INNER JOIN {TABLE_PREFIX}node AS starter ON starter.oldid = blog.firstblogtextid AND starter.oldcontenttypeid = 9985
			WHERE firstbt.blogtextid  IN ({blogtextids})'),
		'fixBlogStarterLast' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS parent
			INNER JOIN {TABLE_PREFIX}blog AS blog ON blog.firstblogtextid = parent.oldid AND parent.oldcontenttypeid = 9985
			LEFT JOIN {TABLE_PREFIX}node AS node ON node.oldid = blog.lastblogtextid AND node.oldcontenttypeid = 9984
			SET parent.lastcontent = blog.lastcomment,
			parent.textcount = blog.comments_visible,
			parent.totalcount = blog.comments_visible,
			parent.textunpubcount = blog.comments_moderation,
			parent.totalunpubcount = blog.comments_moderation,
			parent.lastauthorid = CASE WHEN node.userid IS NULL THEN parent.userid ELSE node.userid END,
			parent.lastcontentauthor = blog.lastcommenter,
			parent.lastcontentid =  CASE WHEN node.nodeid IS NULL THEN parent.nodeid ELSE node.nodeid END'),
		'fixBlogChannelCount' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS blog INNER JOIN
			(SELECT parentid, max(publishdate) AS lastdate,
			sum(totalcount) AS totalcount,
			sum(totalunpubcount) AS totalunpubcount,
			sum(showpublished) AS published,
			count(nodeid) AS total
			FROM {TABLE_PREFIX}node WHERE oldcontenttypeid = 9985 AND showpublished = 1  GROUP BY parentid)
			AS blogstarter ON blogstarter.parentid = blog.nodeid
			SET blog.lastcontent = blogstarter.lastdate,
			blog.totalcount = blogstarter.totalcount + blogstarter.published,
			blog.totalunpubcount = blogstarter.totalunpubcount  + blogstarter.total - blogstarter.published,
			blog.textcount = blogstarter.published,
			blog.textunpubcount = blogstarter.total - blogstarter.published'),
		'fixBlogChannelLast' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS blog INNER JOIN
			{TABLE_PREFIX}node AS starter ON starter.parentid = blog.nodeid AND starter.lastcontent = blog.lastcontent AND starter.showpublished = 1
			AND starter.oldcontenttypeid = 9985
			SET blog.lastauthorid = starter.lastauthorid,
			blog.lastcontentauthor = starter.lastcontentauthor,
			blog.lastcontentid = starter.lastcontentid'),
		'getMaxImportedBlogUserSubscriptionId' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(bsu.blogsubscribeuserid) AS maxid
			FROM {TABLE_PREFIX}blog_subscribeuser AS bsu
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldcontenttypeid = 9999 AND n.userid = bsu.bloguserid AND n.contenttypeid = {channeltypeid}
			INNER JOIN {TABLE_PREFIX}groupintopic AS gt ON (gt.groupid = {membergroupid} AND gt.nodeid = n.nodeid AND gt.userid = bsu.userid)'),
		'getMaxBlogUserSubscriptionId' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(bsu.blogsubscribeuserid) AS maxid
			FROM {TABLE_PREFIX}blog_subscribeuser AS bsu
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldcontenttypeid = 9999 AND n.userid = bsu.bloguserid AND n.contenttypeid = {channeltypeid}'),
		'importBlogUserSubscriptions' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}groupintopic(userid, groupid, nodeid)
			SELECT bsu.userid, {membergroupid}, n.nodeid
			FROM {TABLE_PREFIX}blog_subscribeuser AS bsu
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldcontenttypeid = 9999 AND n.userid = bsu.bloguserid AND n.contenttypeid = {channeltypeid}
			WHERE bsu.blogsubscribeuserid > {startat} AND bsu.blogsubscribeuserid < ({startat} + {batchsize} + 1)"
		),
		'getMaxBlogEntrySubscriptionId' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(bse.blogsubscribeentryid) AS maxid
			FROM {TABLE_PREFIX}blog_subscribeentry AS bse
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = bse.blogid AND n.oldcontenttypeid = 9985'),
		'importBlogEntrySubscriptions' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}subscribediscussion(userid, discussionid, emailupdate, oldid, oldtypeid)
			SELECT bse.userid, n.nodeid,
			(CASE bse.type
				WHEN 'usercp' THEN 0
				WHEN 'email' THEN  1
				END) as emailupdate, bse.blogsubscribeentryid, {blogentryid}
			FROM {TABLE_PREFIX}blog_subscribeentry AS bse
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = bse.blogid AND n.oldcontenttypeid = 9985
			WHERE bse.blogsubscribeentryid > {startat} AND bse.blogsubscribeentryid < ({startat} + {batchsize} + 1)"
		),
		'fixNodeRouteid' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS node
			INNER JOIN {TABLE_PREFIX}node AS starter ON node.starter = starter.nodeid AND node.contenttypeid <> {channelContenttypeid}
			INNER JOIN {TABLE_PREFIX}node AS channel ON channel.nodeid = starter.parentid
			INNER JOIN {TABLE_PREFIX}routenew AS route ON route.routeid = channel.routeid
			INNER JOIN {TABLE_PREFIX}routenew AS convRoute ON convRoute.prefix = route.prefix AND convRoute.class =\'vB5_Route_Conversation\'
			SET node.routeid = convRoute.routeid
			WHERE node.nodeid > {startat} AND node.nodeid < ({startat} + {batchsize} + 1)'),
		'setUgpAsDefault' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}usergroup
			SET systemgroupid = usergroupid
			WHERE (~genericoptions & {bf_value}) AND usergroupid = {ugpid}'),
		'setDefaultUsergroups' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}usergroup
			SET systemgroupid = usergroupid
			WHERE usergroupid <= 7'),
		// @TODO Change QUERY_UPDATE to QUERY_ALTER when it gets working.
		'alterSystemgroupidField' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'ALTER TABLE {TABLE_PREFIX}usergroup
			MODIFY COLUMN `systemgroupid` SMALLINT UNSIGNED NOT NULL DEFAULT 0'),
		'getMaxvB5AlbumText' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(n.oldid) AS maxid
			FROM {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}gallery AS g ON n.nodeid = g.nodeid
			INNER JOIN {TABLE_PREFIX}text AS t ON t.nodeid = g.nodeid
			WHERE n.oldcontenttypeid = {albumtypeid}'),
		'getMaxvB4AlbumMissingText' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(a.albumid) AS maxid
			FROM {TABLE_PREFIX}album AS a
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = a.albumid
			LEFT JOIN {TABLE_PREFIX}text AS t ON t.nodeid = n.nodeid
			WHERE n.oldcontenttypeid = {albumtypeid} AND t.nodeid IS NULL'),
		'addMissingTextAlbumRecords' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}text(nodeid, rawtext)
			SELECT n.nodeid, a.description
			FROM {TABLE_PREFIX}album AS a
			INNER JOIN {TABLE_PREFIX}node AS n ON a.albumid = n.oldid
			LEFT JOIN {TABLE_PREFIX}text AS t ON t.nodeid = n.nodeid
			WHERE n.oldcontenttypeid = {albumtypeid} AND t.nodeid IS NULL
			AND a.albumid > {startat} AND a.albumid < ({startat} + {batchsize} + 1)"
		),
		'getMinvB5AlbumMissingStarter' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT min(oldid) AS minid
			FROM {TABLE_PREFIX}node
			WHERE oldcontenttypeid = {albumtypeid} AND (nodeid <> starter)"
		),
		'updateModeratorNodeid' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}moderator AS m
			INNER JOIN {TABLE_PREFIX}node AS n ON n.oldid = m.forumid AND n.oldcontenttypeid = {forumtype}
			SET m.nodeid = n.nodeid'
		),
		'getMinCustomAvatarToFix' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT min(ca.userid) AS minid
			FROM {TABLE_PREFIX}customavatar AS ca
			INNER JOIN {TABLE_PREFIX}user AS u ON u.userid = ca.userid
			WHERE ca.extension = ''"
		),
		'fixCustomAvatars' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}customavatar AS ca
			INNER JOIN {TABLE_PREFIX}user AS u ON u.userid = ca.userid
			SET ca.filename = CONCAT('avatar', ca.userid, '_', u.avatarrevision, '.gif'), ca.extension = 'gif'
			WHERE ca.extension = '' AND ca.userid > {startat} AND ca.userid < ({startat} + {batchsize} + 1)"
		),
		'getMaxImportedBlogStarter' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(oldid) AS maxid	FROM {TABLE_PREFIX}node WHERE oldcontenttypeid = 9985' ),
		'getMaxSGDiscussion' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT max(oldid) AS maxid	FROM {TABLE_PREFIX}node WHERE oldcontenttypeid = {discussionTypeid}' ),
		'updateRootChannelperm' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}permission AS p
			INNER JOIN {TABLE_PREFIX}usergroup AS ug ON ug.usergroupid = p.groupid AND p.nodeid = 1
			SET p.forumpermissions = p.forumpermissions |{bitmask} WHERE ug.forumpermissions & {bitmask} > 0;"
		),
		'importRedirectThreads' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}node(contenttypeid, parentid, routeid, title, htmltitle, userid, authorname,
				oldid, oldcontenttypeid, created,
				starter, inlist,
				publishdate,
			 	unpublishdate,
				showpublished,
				showopen,
				approved,
				showapproved,
				textcount, totalcount, textunpubcount, totalunpubcount, lastcontent,
				lastcontentauthor, lastauthorid, prefixid, iconid, sticky,
				deleteuserid, deletereason)
			SELECT {redirectTypeId}, node.nodeid, node.routeid, th.title, th.title, th.postuserid, th.postusername,
				th.threadid, 9980, th.dateline,
				1, 1,
				th.dateline,
				tr.expires,
				(CASE WHEN th.visible < 2 THEN 1 ELSE 0 END),
				th.open,
				(CASE th.visible WHEN 0 THEN 0 ELSE 1 END),
				(CASE th.visible WHEN 0 THEN 0 ELSE 1 END),
				th.replycount,th.replycount, th.hiddencount, th.hiddencount, th.lastpost,
				th.postuserid, th.postusername, th.prefixid, th.iconid, th.sticky,
				dl.userid, dl.reason
			FROM {TABLE_PREFIX}thread AS th
			INNER JOIN {TABLE_PREFIX}node AS node ON node.oldid = th.forumid AND node.oldcontenttypeid = {forumTypeId}
			LEFT JOIN {TABLE_PREFIX}node AS n ON n.oldid = th.threadid AND n.oldcontenttypeid = 9980
			LEFT JOIN {TABLE_PREFIX}threadredirect as tr ON tr.threadid = th.threadid
			LEFT JOIN {TABLE_PREFIX}deletionlog AS dl ON dl.primaryid = th.threadid AND dl.type = 'thread'
			WHERE th.open = 10 AND n.nodeid IS NULL
		"),
		'fetchRedirectThreads' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT nodeid, title, oldid
			FROM {TABLE_PREFIX}node
			WHERE oldcontenttypeid = 9980"
		),
		'updateNodeStarter' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}node
			SET starter = nodeid
			WHERE oldcontenttypeid = {contenttypeid}"
		),
		'insertNodeClosure' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}closure (parent, child, depth)
			SELECT node.nodeid, node.nodeid, 0
			FROM {TABLE_PREFIX}node AS node
			WHERE node.oldcontenttypeid = {contenttypeid}"
		),
		'insertNodeClosureRoot' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}closure (parent, child, depth)
			SELECT parent.parent, node.nodeid, parent.depth + 1
			FROM {TABLE_PREFIX}node AS node
			INNER JOIN {TABLE_PREFIX}closure AS parent ON parent.child = node.parentid
			WHERE node.oldcontenttypeid = {contenttypeid}"
		),
		'updateRedirectRoutes' =>array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}routenew AS pr ON pr.routeid = n.routeid AND pr.class = \'vB5_Route_Channel\'
			INNER JOIN {TABLE_PREFIX}routenew AS cr ON cr.prefix = pr.prefix AND cr.class = \'vB5_Route_Conversation\'
			SET n.routeid = cr.routeid
			WHERE n.oldcontenttypeid = {contenttypeid}'
		),
		'insertRedirectRecords' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}redirect(nodeid, tonodeid)
			SELECT node.nodeid, redirectto.nodeid
			FROM {TABLE_PREFIX}node AS node
			INNER JOIN {TABLE_PREFIX}thread AS th ON th.threadid = node.oldid
			INNER JOIN {TABLE_PREFIX}node AS redirectto ON redirectto.oldid = th.pollid AND redirectto.oldcontenttypeid = {contenttypeid}
			WHERE node.nodeid IN ({nodes})
		"),
		'removeSGSystemgroups' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "DELETE FROM {TABLE_PREFIX}usergroup WHERE systemgroupid IN (12, 13, 14)"
		),
		// @TODO Change QUERY_UPDATE to QUERY_ALTER when it gets working.
		'addMaxChannelsField' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'ALTER TABLE {TABLE_PREFIX}permission ADD COLUMN maxchannels SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0 AFTER maxattachments'
		),
		'getRootChannels' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT n.nodeid, n.title, n.routeid, c.category, c.guid, r2.routeid, r2.class
				FROM {TABLE_PREFIX}channel AS c
				INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = c.nodeid
				LEFT JOIN {TABLE_PREFIX}routenew AS r1 ON r1.routeid = n.routeid
				LEFT JOIN {TABLE_PREFIX}routenew AS r2 ON r1.prefix = r2.prefix AND r2.class = 'vB5_Route_Conversation'
				WHERE c.guid IN ({rootGuids})"),
		'getTotalUsersWithFolders' =>array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT COUNT(userid) AS totalusers
			FROM {TABLE_PREFIX}usertextfield WHERE pmfolders <> "";'),
		'getUsersWithFolders' =>array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT userid, pmfolders
			FROM {TABLE_PREFIX}usertextfield WHERE pmfolders <> ""
			LIMIT {startat}, {batchsize}'),
		// @TODO Change QUERY_UPDATE to QUERY_ALTER when it gets working.
		'alterRouteRegexSize' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'ALTER TABLE {TABLE_PREFIX}routenew
			MODIFY COLUMN `regex` VARCHAR({regexSize}) NOT NULL'
		),
		'updateNonCustomConversationRoutes' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}routenew
			SET regex = CONCAT(prefix, '/', {regex})
			WHERE class = 'vB5_Route_Conversation' AND (prefix != regex OR regex != CONCAT(prefix, '/', {regex}))"
		),
		// @TODO Change QUERY_UPDATE to QUERY_ALTER when it gets working.
		'fixStrikeIPFields' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'ALTER TABLE {TABLE_PREFIX}strikes
			MODIFY COLUMN ip_4 INT UNSIGNED NOT NULL DEFAULT 0,
			MODIFY COLUMN ip_3 INT UNSIGNED NOT NULL DEFAULT 0,
			MODIFY COLUMN ip_2 INT UNSIGNED NOT NULL DEFAULT 0,
			MODIFY COLUMN ip_1 INT UNSIGNED NOT NULL DEFAULT 0'
		),
		'500b28_updatePostHistory1' =>array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}postedithistory AS p
				 INNER JOIN {TABLE_PREFIX}node AS n ON (p.postid = n.oldid AND n.oldcontenttypeid = {posttypeid} AND p.postid <> 0)
				 SET p.nodeid = n.nodeid'
		),
		'500b28_updatePostHistory2' =>array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}postedithistory AS p
				 INNER JOIN {TABLE_PREFIX}thread_post AS tp ON (p.postid = tp.postid AND p.postid <> 0)
				 INNER JOIN {TABLE_PREFIX}node AS n ON (tp.threadid = n.oldid AND n.oldcontenttypeid = {threadtypeid})
				 SET p.nodeid = n.nodeid'
		),
		'500b28_updatePostHistory3' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => 'DELETE FROM {TABLE_PREFIX}postedithistory
				 WHERE nodeid = 0'
		),
		'500b28_updateFiledata1' =>array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT MAX(filedataid) AS maxid FROM {TABLE_PREFIX}filedata"
		),
		'500b28_updateFiledata2' =>array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT MAX(filedataid) AS maxid FROM {TABLE_PREFIX}filedataresize"
		),
		'500b28_updateFiledata3' =>array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "REPLACE INTO {TABLE_PREFIX}filedataresize
				(filedataid, resize_type, resize_filedata, resize_filesize, resize_dateline, resize_width, resize_height, reload)
			SELECT filedataid, 'thumb', thumbnail, thumbnail_filesize, thumbnail_dateline, thumbnail_width, thumbnail_height, '1'
			FROM {TABLE_PREFIX}filedata AS fd
			WHERE fd.filedataid > {maxvB5} AND fd.filedataid < {process}
			ORDER BY fd.filedataid"
		),
		'grantOwnerForumPerm' =>array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}permission AS perm 
			INNER JOIN {TABLE_PREFIX}usergroup AS ug ON ug.usergroupid = perm.groupid
			SET perm.forumpermissions2 = perm.forumpermissions2 | {permission}
			WHERE ug.systemgroupid = {systemgroupid} AND moderatorpermissions > 0"
		),
		'updateAllTextHtmlStateDefault' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}text AS text
			SET text.htmlstate = 'off'
			WHERE text.htmlstate = ''
			AND text.nodeid > {startat} and text.nodeid < ({startat} + {batchsize} + 1)"
		),
		'updateAllowHtmlChannelOption' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}channel AS channel INNER JOIN {TABLE_PREFIX}node AS node ON channel.nodeid = node.nodeid
			SET channel.options = channel.options | {allowhtmlpermission}
			WHERE !(channel.options & {allowhtmlpermission})"
		),
		'updateImportedForumPostHtmlState' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}text AS text
			INNER JOIN {TABLE_PREFIX}node AS node ON text.nodeid = node.nodeid
			INNER JOIN {TABLE_PREFIX}node AS starter ON starter.nodeid = node.starter
			INNER JOIN {TABLE_PREFIX}channel AS channel ON channel.nodeid = starter.parentid
			SET text.htmlstate = 'off'
			WHERE !(channel.options & {allowhtmlpermission}) AND node.oldcontenttypeid in ({oldcontenttypeids})
				AND text.nodeid > {startat} AND text.nodeid < ({startat} + {batchsize} + 1)"
		),
		'updateStarterPostHtmlState' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}text AS text
			INNER JOIN {TABLE_PREFIX}node AS node ON text.nodeid = node.nodeid
			INNER JOIN {TABLE_PREFIX}thread_post AS thread_post ON thread_post.nodeid = text.nodeid
			INNER JOIN {TABLE_PREFIX}post AS post ON post.postid = thread_post.postid
			SET text.htmlstate = post.htmlstate
			WHERE text.nodeid > {startat} AND text.nodeid < ({startat} + {batchsize} + 1) AND text.htmlstate IS NULL"
		),
		'updateImportedBlogPostHtmlState' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}text AS text
			INNER JOIN {TABLE_PREFIX}node AS node ON text.nodeid = node.nodeid
			SET text.htmlstate = 'off'
			WHERE node.oldcontenttypeid in ({oldcontenttypeids})
				AND text.nodeid > {startat} AND text.nodeid < ({startat} + {batchsize} + 1)"
		),
		// @TODO Change QUERY_UPDATE to QUERY_ALTER when it gets working.
		'alterChannelOptions' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'ALTER TABLE {TABLE_PREFIX}channel MODIFY COLUMN `options` INT(10) UNSIGNED NOT NULL DEFAULT 1984'
		),
		// @TODO Change QUERY_UPDATE to QUERY_ALTER when it gets working.
		'alterTextHtmlstate' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "ALTER TABLE {TABLE_PREFIX}text MODIFY COLUMN `htmlstate` ENUM('off', 'on', 'on_nl2br') NOT NULL DEFAULT 'off'"
		),
		'500rc1_checkDuplicateRequests' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => '
				SELECT p.aboutid
				FROM {TABLE_PREFIX}sentto AS s
				INNER JOIN {TABLE_PREFIX}node AS n ON (n.nodeid = s.nodeid)
				INNER JOIN {TABLE_PREFIX}privatemessage AS p ON (n.nodeid = p.nodeid)
				WHERE
					s.userid = {userid}
					AND
					s.folderid = {folderid}
					AND
					s.deleted = 0
					AND
					p.aboutid = {aboutid}
					AND
					p.about = {about}
			'
		),
		'fixRedirectContentTypeId' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}node AS node
			SET node.contenttypeid = {redirectContentTypeId}
			WHERE node.oldcontenttypeid = {redirectOldContentTypeId} AND node.contenttypeid = 0
			AND node.nodeid > {startat} and node.nodeid < ({startat} + {batchsize} + 1)"
		),
		'fixFperms2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}permission SET 
			forumpermissions2 = forumpermissions2 | IF(forumpermissions & {oldp1} = {oldp1}, {newp1}, 0),
			forumpermissions2 = forumpermissions2 | IF(forumpermissions & {oldp2} = {oldp2}, {newp2}, 0),
			forumpermissions2 = forumpermissions2 | IF(forumpermissions & {oldp3} = {oldp3}, {newp3}, 0)"
		),
		'getMaxBlogUserIdToFix' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => '
				SELECT max(bu.bloguserid) AS maxid
				FROM {TABLE_PREFIX}node n
				INNER JOIN {TABLE_PREFIX}blog_user bu ON bu.bloguserid = n.userid
				WHERE n.oldcontenttypeid = {contenttypeid} AND (bu.title <> \'\' AND bu.title <> n.title)
			'
		),
		'getMaxFixedBlogUserId' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => '
				SELECT max(bu.bloguserid) AS maxid
				FROM {TABLE_PREFIX}node n
				INNER JOIN {TABLE_PREFIX}blog_user bu ON bu.bloguserid = n.userid
				WHERE n.oldcontenttypeid = {contenttypeid} AND (bu.title <> \'\' AND bu.title = n.title)
			'
		),
		'getBlogsUserToFix' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => '
				SELECT n.nodeid, bu.bloguserid, bu.title, bu.description
				FROM {TABLE_PREFIX}node n
				INNER JOIN {TABLE_PREFIX}blog_user bu ON bu.bloguserid = n.userid
				WHERE n.oldcontenttypeid = {contenttypeid} AND (bu.title <> \'\' AND bu.title <> n.title)
				AND bu.bloguserid > {startat} AND bu.bloguserid <= ({startat} + {batchsize})
				ORDER BY bu.bloguserid
			'
		),
		'getMaxBlogUserIdToFixOptions' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => '
				SELECT max(bu.bloguserid) AS maxid
				FROM {TABLE_PREFIX}node n
				INNER JOIN {TABLE_PREFIX}blog_user bu ON bu.bloguserid = n.userid
				WHERE n.oldcontenttypeid = {contenttypeid}
				AND
				(
					(n.nodeoptions = 138 AND (bu.options <> 6 OR bu.allowsmilie <> 1))
					OR
					(
					    n.viewperms = 2 AND
					    ( ~bu.options_member & 1 OR ~bu.options_guest & 1 )
					)
					OR
					(
					    n.commentperms = 1 AND
					    (~bu.options_member & 2)
					)
				)
			'
		),
		'getMaxOptionsFixedBlogUserId' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => '
				SELECT max(bu.bloguserid) AS maxid
				FROM {TABLE_PREFIX}node n
				INNER JOIN {TABLE_PREFIX}blog_user bu ON bu.bloguserid = n.userid
				WHERE n.oldcontenttypeid = {contenttypeid}
				AND
				(
					(n.nodeoptions <> 138 OR n.commentperms <> 1 OR n.viewperms <> 2)
					OR
					(
						(n.nodeoptions = 138 AND (bu.options = 6 OR bu.allowsmilie = 1))
						AND
						(
						    n.viewperms = 2 AND
						    (bu.options_member & 1 OR bu.options_guest & 1)
						)
						AND
						(
						    n.commentperms = 1 AND
						    (bu.options_member & 2)
						)
					)
				)
			'
		),
		'getBlogsUserToFixOptions' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => '
				SELECT n.nodeid, bu.bloguserid, bu.title, bu.description, bu.options_member, bu.options_guest, 
				bu.allowsmilie, bu.options, n.commentperms, n.viewperms, n.nodeoptions
				FROM {TABLE_PREFIX}node n
				INNER JOIN {TABLE_PREFIX}blog_user bu ON bu.bloguserid = n.userid
				WHERE n.oldcontenttypeid = {contenttypeid}
				AND
				(
					(n.nodeoptions = 138 AND (bu.options <> 6 OR bu.allowsmilie <> 1))
					OR
					(
					    n.viewperms = 2 AND
					    ( ~bu.options_member & 1 OR ~bu.options_guest & 1 )
					)
					OR
					(
					    n.commentperms = 1 AND
					    (~bu.options_member & 2)
					)
				)
				AND bu.bloguserid > {startat} AND bu.bloguserid <= ({startat} + {batchsize})
				ORDER BY bu.bloguserid
			'
		),
		'getMaxPmStarterToCreate' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT max(node.nodeid) AS maxid
			FROM {TABLE_PREFIX}node AS node
			INNER JOIN {TABLE_PREFIX}node AS reply ON reply.parentid = node.nodeid
			INNER JOIN {TABLE_PREFIX}sentto AS s ON s.nodeid = reply.nodeid
			INNER JOIN {TABLE_PREFIX}messagefolder AS folder ON folder.userid = node.userid AND folder.folderid = s.folderid
			LEFT JOIN {TABLE_PREFIX}sentto AS existing ON existing.nodeid = node.nodeid AND
			        existing.userid = node.userid AND existing.folderid = folder.folderid
			WHERE existing.nodeid IS NULL
			AND node.contenttypeid = {pmtypeid} AND reply.oldcontenttypeid = {contenttypeid}"
		),
		'createStarterPmRecords' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}sentto (nodeid, userid, folderid, msgread)
			SELECT DISTINCT node.nodeid, node.userid, folder.folderid, 1
			FROM {TABLE_PREFIX}node AS node
			INNER JOIN {TABLE_PREFIX}node AS reply ON reply.parentid = node.nodeid
			INNER JOIN {TABLE_PREFIX}sentto AS s ON s.nodeid = reply.nodeid
			INNER JOIN {TABLE_PREFIX}messagefolder AS folder ON folder.userid = node.userid AND folder.folderid = s.folderid
			LEFT JOIN {TABLE_PREFIX}sentto AS existing ON existing.nodeid = node.nodeid AND
			        existing.userid = node.userid AND existing.folderid = folder.folderid
			WHERE existing.nodeid IS NULL
			AND node.contenttypeid = {pmtypeid} AND reply.oldcontenttypeid = {contenttypeid}
			LIMIT {batchsize}"
		),
	);

	public function updateUrlIdent($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			if (!isset($params['nodes']) OR !is_array($params['nodes']))
			{
				return false;
			}
			foreach ($params['nodes'] AS $node)
			{
				if (!isset($node['nodeid']) OR !isset($node['urlident']))
				{
					return false;
				}
			}
			return true;
		}

		$caseSql = "WHEN -1 THEN '' \n";
		foreach($params['nodes'] AS $node)
		{
			$caseSql .= "WHEN " . intval($node['nodeid']) . " THEN '" . $db->escape_string($node['urlident']) . "' \n";
		}
		$updateSql = "UPDATE " . TABLE_PREFIX . "node
			SET urlident = CASE nodeid
			$caseSql ELSE urlident END";

		return $db->query_write($updateSql);
	}

	public function updateWidgetDefs($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$temptable = TABLE_PREFIX . 'tempids';
		}

		// Make sure temp table doesnt exist
		$db->query_write("
			DROP TABLE IF EXISTS $temptable
		");

		// Create temp table
		$db->query_write("
			CREATE TABLE $temptable (
				widgetid INT(10) NULL,
				PRIMARY KEY (widgetid)
			)
		");

		// Populate temp table with orphan widget ids
		$db->query_write("
			INSERT INTO $temptable
			SELECT widgetid
			FROM " . TABLE_PREFIX . "widgetdefinition
			LEFT JOIN " . TABLE_PREFIX . "widget USING (widgetid)
			WHERE guid IS NULL
			GROUP BY widgetid
		");

		// Delete orphan records from widgetdefinition
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "widgetdefinition
			WHERE widgetid IN
			(
				SELECT widgetid
				FROM $temptable
			)
		");

		// Zap temp table
		$db->query_write("
			DROP TABLE $temptable
		");
	}

	/**
	 * Used to map maximumsocialgroups limit permission to the new maxchannels channel limit permission.
	 * We basically pass everything globally defined to sg channel node permissions.
	 */
	public function updateUGPMaxSGs($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			// only array to update
			if (!isset($params['groups']) OR !is_array($params['groups']) OR empty($params['groups']))
			{
				return false;
			}

			// ugp info should be ugpid => val
			foreach ($params['groups'] AS $ugpid => $param)
			{
				if (!is_numeric($ugpid))
				{
					return false;
				}

				if (!is_numeric($param))
				{
					return false;
				}
			}

			return true;
		}
		else
		{
			$sql = "UPDATE " . TABLE_PREFIX . "permission SET maxchannels = CASE groupid\n";
			foreach ($params['groups'] AS $id => $val)
			{
				$sql .= "WHEN $id THEN $val\n";
			}

			$sql .= "END
				WHERE groupid IN (" . implode(", ", array_keys($params['groups'])) . ") AND nodeid = " . vB_Api::instanceInternal('content_channel')->fetchChannelIdByGUID(vB_Channel::DEFAULT_SOCIALGROUP_PARENT) . "
			/** getSubscriptionUsersLog" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$config = vB::get_config();

			if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
			{
				echo "sql: $sql<br />\n";
			}

			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	/**
	 * Update regex for routes from vbulletin-routes.xml that have already been imported (yet still need to be updated).
	 */
	public function updateRouteRegex($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			if (!isset($params['routes']) OR !is_array($params['routes']))
			{
				return false;
			}
			foreach ($params['routes'] AS $route)
			{
				if (!isset($route['guid']) OR !isset($route['regex']))
				{
					return false;
				}
			}
			return true;
		}

		$caseSql = "WHEN -1 THEN '' \n";
		foreach($params['routes'] AS $route)
		{
			$caseSql .= "WHEN '" . $db->escape_string($route['guid']) . "' THEN '" . $db->escape_string($route['regex']) . "' \n";
		}
		$updateSql = "UPDATE " . TABLE_PREFIX . "routenew
			SET regex = CASE guid
			$caseSql ELSE regex END";

		return $db->query_write($updateSql);
	}
}
