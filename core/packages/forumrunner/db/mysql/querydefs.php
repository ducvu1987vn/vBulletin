<?php if (!defined('VB_ENTRY')) die('Access denied.');

class Forumrunner_dB_MYSQL_QueryDefs extends vB_dB_QueryDefs
{
	protected $db_type = 'MYSQL';

	protected $table_data = array();

	protected $query_data = array(
		'deleteAttachmentMarker' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "
			DELETE FROM {TABLE_PREFIX}forumrunner_attachment
			WHERE id = {id}
			",
		),
		'getAttachmentMarkerById' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT * FROM {TABLE_PREFIX}forumrunner_attachment
			WHERE id = {id}
			",
		),
		'getAttachmentMarker' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT * FROM {TABLE_PREFIX}forumrunner_attachment
			WHERE vb_userid = {userid}
			AND poststarttime = {poststarttime}
			",
		),
		'updateAttachmentMarker' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
			UPDATE {TABLE_PREFIX}forumrunner_attachment SET
			attachmentid = {attachmentid}
			WHERE id = {id}
			",
		),
		'addAttachmentMarker' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
			INSERT INTO {TABLE_PREFIX}forumrunner_attachment
			(vb_userid, poststarttime, filedataid, attachmentid)
			VALUES
			({userid}, {poststarttime}, {filedataid}, NULL)
			",
		),
		'getNewPmsForPushUser' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' =>  "
			SELECT n.nodeid, user.username AS fromusername FROM {TABLE_PREFIX}sentto AS s
			INNER JOIN {TABLE_PREFIX}messagefolder AS m ON m.folderid = s.folderid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = s.nodeid AND n.nodeid = n.starter
			INNER JOIN {TABLE_PREFIX}user AS user ON n.userid = user.userid
			WHERE  1=1
			AND m.userid 			= {userid}
			AND s.deleted 			= 0
			AND s.msgread 			= 0
			AND m.titlephrase 		IN ('messages')
			",
		),
		'getNewSubsForPushUser' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' =>  "
			SELECT sd.discussionid AS nodeid, sn.title, sn.lastupdate FROM {TABLE_PREFIX}sentto AS s
			INNER JOIN {TABLE_PREFIX}messagefolder AS m ON m.folderid = s.folderid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = s.nodeid AND n.nodeid = n.starter
			INNER JOIN {TABLE_PREFIX}privatemessage AS pm ON pm.nodeid = n.nodeid
			INNER JOIN {TABLE_PREFIX}subscribediscussion AS sd ON sd.discussionid = pm.aboutid
			INNER JOIN {TABLE_PREFIX}node AS sn ON sn.nodeid = sd.discussionid
			WHERE  1=1
			AND sd.userid 		= {userid}
			AND m.userid 		= {userid}
			AND s.deleted 		= 0
			AND s.msgread 		= 0
			AND pm.about 		IN ('reply')
			AND m.titlephrase 	IN ('your_notifications')
			",
		),
		'subscribedContentUpdateCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' =>  "
			SELECT count(s.nodeid) AS qty FROM {TABLE_PREFIX}sentto AS s
			INNER JOIN {TABLE_PREFIX}messagefolder AS m ON m.folderid = s.folderid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = s.nodeid AND n.nodeid = n.starter
			INNER JOIN {TABLE_PREFIX}privatemessage AS pm ON pm.nodeid = n.nodeid
			INNER JOIN {TABLE_PREFIX}subscribediscussion AS sd ON sd.discussionid = pm.aboutid
			WHERE  1=1
			AND sd.userid 		= {userid}
			AND m.userid 		= {userid}
			AND s.deleted 		= 0
			AND s.msgread 		= 0
			AND pm.about 		IN ('reply')
			AND m.titlephrase 	IN ('your_notifications')
			",
		),
		'countMembers' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' =>  "
			SELECT count(s.userid) AS count FROM {TABLE_PREFIX}user AS s
			",
		),
		'getNewestUser' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' =>  "
			SELECT u.username, u.userid FROM {TABLE_PREFIX}user AS u
			ORDER BY u.userid DESC
			LIMIT 1
			",
		),
	);
}
