<?php if (!defined('VB_ENTRY')) die('Access denied.');
/* ======================================================================*\
 || #################################################################### ||
 || # vBulletin 5.0.0
 || # ---------------------------------------------------------------- # ||
 || # Copyright  2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
 || # This file may not be redistributed in whole or significant part. # ||
 || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
 || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
 || #################################################################### ||
 \*====================================================================== */
/**
 * @package vBDatabase
 */

/**
 * @package vBDatabase
 */
class vB_dB_MYSQL_QueryDefs extends vB_dB_QueryDefs
{

	/** This class is called by the new vB_dB_Assertor database class
	* It does the actual execution. See the vB_dB_Assertor class for more information

	* $queryid can be either the id of a query from the dbqueries table, or the
	* name of a table.
	*
	* if it is the name of a table , $params MUST include 'type' of either update, insert, select, or delete.
	*
	* $params includes a list of parameters. Here's how it gets interpreted.
	*
	* If the queryid was the name of a table and type was "update", one of the params
	* must be the primary key of the table. All the other parameters will be matched against
	* the table field names, and appropriate fields will be updated. The return value will
	* be false if an error is generated and true otherwise
	*
	* If the queryid was the name of a table and type was "delete", one of the params
	* must be the primary key of the table. All the other parameters will be ignored
	* The return value will be false if an error is generated and true otherwise
	*
	* If the queryid was the name of a table and type was "insert", all the parameters will be
	* matched against the table field names, and appropriate fields will be set in the insert.
	* The return value is the primary key of the inserted record.
	*
	* If the queryid was the name of a table and type was "select", all the parameters will be
	* matched against the table field names, and appropriate fields will be part of the
	* "where" clause of the select. The return value will be a vB_dB_Result object
	* The return value is the primary key of the inserted record.
	*
	* If the queryid is the key of a record in the dbqueries table then each params
	* value will be matched to the query. If there are missing parameters we will return false.
	* If the query generates an error we return false, and otherwise we return either true,
	* or an inserted id, or a recordset.
	*
	* */
	/* Properties==================================================================== */

	protected $db_type = 'MYSQL';
	/** This is the definition for tables we will process through. It saves a
	* database query to put them here.
	* * */
	protected $table_data = array(
		//these should be in alpha order for readability.
		'adminutil' => array(
			'key' => 'title',
			'structure' => array('title', 'text')
		),
		'album' => array('key' => 'albumid', 'structure' => array('albumid', 'userid',
				'createdate', 'lastpicturedate', 'visible', 'moderation', 'title', 'description', 'state',
				'coverattachmentid')
		),

		'customavatar' => array('key' => 'customavatarid', 'structure' => array('customavatarid',
			'userid', 'filedata', 'dateline', 'filename', 'visible', 'filesize', 'width', 'height',
			'filedata_thumb', 'width_thumb', 'height_thumb')
		),
		'widget' => array(
			'key' => 'widgetid',
			'structure' => array('widgetid', 'title', 'template', 'admintemplate', 'icon', 'isthirdparty', 'category', 'cloneable', 'guid', 'canbemultiple'),
		),
		'widgetdefinition' => array(
			'key' => '',
			'structure' => array('widgetid', 'field', 'name', 'label', 'defaultvalue', 'isusereditable', 'isrequired', 'displayorder',
				'validationtype', 'validationmethod', 'data'),
		),
		'widgetinstance' => array(
			'key' => 'widgetinstanceid',
			'structure' => array('widgetinstanceid', 'pagetemplateid', 'widgetid', 'displaysection', 'displayorder', 'adminconfig', 'guid', 'parent'),
		),
		'widgetuserconfig' => array(
			'key' => 'widgetinstanceid',
			'structure' => array('widgetinstanceid', 'userid', 'userconfig'),
		),
		'widgetchannelconfig' => array(
			'key' => array('widgetinstanceid','nodeid'),
			'structure' => array('widgetinstanceid', 'nodeid', 'channelconfig'),
		),
		'cache' => array(
			'key' => 'cacheid',
			'structure' => array('cacheid', 'expires', 'created', 'locktime', 'serialized', 'data'),
			'forcetext' => array('serialized')
		),

		'cacheevent' => array(
			'key' => array('cacheid', 'event'),
			'structure' => array('cacheid', 'event')
		),

		'cacheevent_log' => array(
			'key' => 'event',
			'structure' => array('event', 'eventtime')
		),

		'datastore' => array('key' => 'title', 'structure' => array('title', 'data', 'unserialize')
		),

		'externalcache' => array(
			'key' => 'cachehash',
			'structure' => array('cachehash', 'text', 'headers', 'dateline', 'forumid')
		),

		'filedata' => array('key' => 'filedataid', 'structure' => array('filedataid', 'userid', 'dateline',
			'filedata', 'filesize', 'filehash', 'extension', 'refcount', 'width', 'height', 'publicview')
		),

		'filedataresize' => array('key' => array('filedataid', 'type'), 'structure' => array('filedataid', 'resize_type', 'resize_filedata',
			'resize_dateline', 'resize_dateline', 'resize_width', 'resize_height', 'reload')),

		'setting' => array('key' => 'varname', 'structure' => array('varname', 'grouptitle', 'value', 'defaultvalue',
			'optioncode', 'displayorder', 'advanced', 'volatile', 'datatype', 'product', 'validationcode', 'blacklist')
		),

		'settinggroup' => array('key' => 'grouptitle', 'structure' => array('grouptitle',
			'displayorder', 'volatile', 'product')
		),

		'smilie' => array(
			'key' => 'smilieid',
			'structure' => array('smilieid', 'title', 'smilietext', 'smiliepath', 'imagecategoryid', 'displayorder')
		),

		'session' => array('key' => 'sessionhash', 'structure' => array('sessionhash', 'userid', 'host', 'idhash',
			'lastactivity', 'location', 'useragent', 'styleid', 'languageid', 'loggedin', 'inforum', 'inthread',
			'incalendar', 'badlocation', 'bypass', 'profileupdate', 'apiclientid', 'apiaccesstoken')
		),

		'style' => array(
			'key'=> 'styleid',
			'structure' => array('styleid','parentid', 'title',
				'parentlist','templatelist','newstylevars', 'replacements',
				'editorstyles', 'userselect', 'displayorder', 'dateline')
		),

		'template' => array('key' => 'templateid', 'forcetext' => array('templatetype',
			'mergestatus'), 'structure' => array('templateid', 'styleid', 'title', 'template',
			'template_un', 'templatetype', 'dateline', 'username', 'version', 'product',
			'mergestatus')
		),

		'templatehistory' => array('key' => 'templatehistoryid', 'structure' =>
			array('templatehistoryid', 'styleid', 'title', 'template', 'dateline', 'username',
			'version', 'comment')
		),

		'templatemerge' => array('key' => 'templateid', 'structure' => array('templateid',
			'template', 'version', 'savedtemplateid')
		),

		'user' => array('key' => 'userid', 'structure' => array('userid', 'usergroupid',
			'membergroupids', 'displaygroupid', 'username', 'password', 'passworddate', 'email',
			'styleid', 'parentemail', 'homepage', 'icq', 'aim', 'yahoo', 'msn', 'skype', 'google', 'status',
			'showvbcode', 'showbirthday', 'usertitle', 'customtitle', 'joindate', 'daysprune',
			'lastvisit', 'lastactivity', 'lastpost', 'lastpostid', 'posts', 'reputation',
			'reputationlevelid', 'timezoneoffset', 'pmpopup', 'avatarid', 'avatarrevision',
			'profilepicrevision', 'sigpicrevision', 'options', 'privacy_options', 'notification_options', 'birthday', 'birthday_search',
			'maxposts', 'startofweek', 'ipaddress', 'referrerid', 'languageid', 'emailstamp', 'threadedmode',
			'autosubscribe', 'pmtotal', 'pmunread', 'salt', 'ipoints', 'infractions', 'warnings',
			'infractiongroupids', 'infractiongroupid', 'adminoptions', 'profilevisits', 'friendcount',
			'friendreqcount', 'vmunreadcount', 'vmmoderatedcount', 'socgroupinvitecount', 'socgroupreqcount',
			'pcunreadcount', 'pcmoderatedcount', 'gmmoderatedcount', 'assetposthash', 'fbuserid',
			'fbjoindate', 'fbname', 'logintype', 'fbaccesstoken'),
			'forcetext' => array('username', 'status')
		),

		'usergroup' => array('key' => 'usergroupid', 'structure' => array( 'usergroupid',
			'title','description','usertitle','passwordexpires','passwordhistory','pmquota',
			'pmsendmax','opentag','closetag','canoverride','ispublicgroup','forumpermissions',
			'pmpermissions','calendarpermissions','wolpermissions','adminpermissions','genericpermissions',
			'genericpermissions2','genericoptions','signaturepermissions','visitormessagepermissions',
			'attachlimit','avatarmaxwidth','avatarmaxheight', 'avatarmaxsize','profilepicmaxwidth',
			'profilepicmaxheight','profilepicmaxsize','sigpicmaxwidth','sigpicmaxheight','sigpicmaxsize',
			'sigmaximages','sigmaxsizebbcode','sigmaxchars','sigmaxrawchars','sigmaxlines',
			'usercsspermissions','albumpermissions','albumpicmaxwidth','albumpicmaxheight',
			'albummaxpics','albummaxsize','socialgrouppermissions','pmthrottlequantity',
			'groupiconmaxsize','maximumsocialgroups', 'systemgroupid')
		),

		'userlist' => array(
			'key' => array('userid', 'relationid','type'),
			'structure' => array('type', 'userid', 'relationid', 'friend'),
		),

		'routenew' => array('key' => 'routeid', 'structure' => array('routeid', 'name', 'redirect301',
			'prefix', 'regex', 'class', 'controller', 'action', 'template', 'arguments', 'contentid', 'guid')
		),

		'page' => array('key' => 'pageid', 'structure' => array('pageid', 'parentid', 'pagetemplateid',
			'title', 'metakeywords', 'metadescription', 'routeid', 'moderatorid', 'displayorder',
			'pagetype', 'guid')
		),

		'pagetemplate' => array('key' => 'pagetemplateid', 'structure' => array('pagetemplateid', 'title',
			'screenlayoutid', 'content', 'guid')
		),

		'humanverify' => array('key' => 'hash', 'structure' => array('hash', 'answer',
			'dateline', 'viewed')
		),

		'hvanswer' => array('key' => 'answerid', 'structure' => array('answerid', 'questionid',
			'answer', 'dateline')
		),

		'hvquestion' => array('key' => 'questionid', 'structure' => array('questionid', 'regex', 'dateline')),

		'useractivation' => array('key' => 'useractivationid', 'structure' => array('useractivationid', 'userid',
			'dateline', 'activationid', 'type', 'usergroupid', 'emailchange')
		),

		'cron' => array( 'key' => 'cronid', 'structure' => array('cronid', 'nextrun', 'weekday',
			'day', 'hour', 'minute', 'filename', 'loglevel', 'active', 'varname', 'volatile', 'product')
		),

		'userban' => array('key' => 'userid', 'structure' => array(
			'userid', 'usergroupid', 'displaygroupid', 'usertitle', 'customtitle',
			'adminid', 'bandate', 'liftdate', 'reason')
		),

		'apiclient' => array('key' => 'apiclientid', 'structure' => array(
			'apiclientid', 'secret', 'apiaccesstoken', 'userid', 'clienthash',
			'clientname', 'clientversion', 'platformname', 'platformversion',
			'uniqueid', 'initialipaddress', 'dateline', 'lastactivity')
		),

		'apilog' => array('key' => 'apilogid', 'structure' => array(
			'apilogid', 'apiclientid', 'dateline', 'method', 'paramget',
			'parampost', 'ipaddress')
		),

		'phrase' => array('key' => 'phraseid', 'structure' => array('phraseid',
			'languageid','varname','fieldname','text','product','username','dateline','version')
		),

		'phrasetype' => array('key' => 'fieldname', 'structure' => array('fieldname', 'title',
				'editrows', 'prodiuct', 'special')
		),
		'noderead' => array('key' => array('userid', 'nodeid'), 'structure' => array(
			'userid', 'nodeid', 'readtime'
		)),
		'mailqueue' => array('key' => 'mailqueueid', 'structure' => array(
			'mailqueueid', 'dateline', 'toemail', 'fromemail', 'subject', 'message', 'header'
		)),
		'ad' => array('key' => 'adid', 'structure' => array(
			'adid', 'title', 'adlocation', 'displayorder', 'active', 'snippet'
		)),
		'adcriteria' => array('key' => 'adid', 'structure' => array(
			'adid', 'criteriaid', 'condition1', 'condition2', 'condition3'
		)),
		'infraction' => array('key' => 'infractionid', 'structure' => array(
			'infractionid', 'infractionlevelid', 'nodeid', 'postid', 'userid', 'whoadded', 'points',
			'dateline', 'note', 'action', 'actiondateline', 'actionuserid', 'actionreason', 'expires',
			'channelid', 'threadid', 'customreason'
		)),
		'cpsession' => array('key' => array('userid', 'hash'), 'structure' => array(
			'userid', 'hash', 'dateline'
		)),
		'product' => array('key' => 'productid', 'structure' => array(
			'productid', 'title', 'description', 'version', 'active', 'url', 'versioncheckurl'
		)),
		'productcode' => array('key' => 'productcodeid', 'structure' => array(
			'productcodeid', 'productid', 'version', 'installcode', 'uninstallcode'
		)),
		'productdependency' => array('key' => 'productdependencyid', 'structure' => array(
			'productdependency', 'productid', 'dependencytype', 'parentproductid', 'minversion', 'maxversion'
		)),
		'hook' => array(
			'key' => 'hookid',
			'structure' => array('hookid', 'product', 'hookname', 'title', 'active', 'hookorder', 'template', 'arguments')
		),
		'cron' => array('key' => 'cronid', 'structure' => array(
			'cronid', 'nextrun', 'weekday', 'day', 'hour', 'minute', 'filename', 'loglevel', 'active', 'varname', 'volatile', 'product'
		)),
		'adminmessage' => array('key' => 'adminmessageid', 'structure' => array(
			'adminmessageid', 'varname', 'dismissable', 'script', 'action', 'execurl', 'method',
			'dateline', 'status', 'statususerid', 'args'
		)),
		'adminlog' => array('key' => 'adminlogid', 'structure' => array(
			'adminlogid', 'userid', 'dateline', 'script', 'action', 'extrainfo', 'ipaddress'
		)),
		'usertitle' => array('key' => 'usertitleid', 'structure' => array('usertitleid',
			'minposts', 'title'
		)),
		'moderatorlog' => array('key' => 'moderatorlogid', 'structure' => array('moderatorlogid',
			'dateline', 'userid', 'nodeid', 'action', 'type', 'nodetitle', 'ipaddress', 'product', 'id1', 'id2', 'id3', 'id4', 'id5'
		)),
		'screenlayout' => array('key' => 'screenlayoutid', 'structure' => array('screenlayoutid',
			'varname', 'title', 'displayorder', 'columncount', 'template', 'admintemplate'
		)),
		'language' => array('key' => 'languageid', 'structure' => array('languageid',
			'title', 'userselect', 'options', 'languagecode', 'charset', 'imagesoverride', 'dateoverride', 'timeoverride',
			'registereddateoverride', 'calformat1override', 'calformat2override', 'logdateoverride', 'locale', 'decimalsep',
			'thousandsep', 'phrasegroup_global', 'phrasegroup_cpglobal', 'phrasegroup_cppermission', 'phrasegroup_forum',
			'phrasegroup_calendar', 'phrasegroup_attachment_image', 'phrasegroup_style', 'phrasegroup_logging',
			'phrasegroup_cphome', 'phrasegroup_promotion', 'phrasegroup_user', 'phrasegroup_help_faq', 'phrasegroup_sql',
			'phrasegroup_subscription', 'phrasegroup_language', 'phrasegroup_bbcode', 'phrasegroup_stats', 'phrasegroup_diagnostic',
			'phrasegroup_maintenance', 'phrasegroup_profilefield', 'phrasegroup_thread', 'phrasegroup_timezone', 'phrasegroup_banning',
			'phrasegroup_reputation', 'phrasegroup_wol', 'phrasegroup_threadmanage', 'phrasegroup_pm', 'phrasegroup_cpuser',
			'phrasegroup_accessmask', 'phrasegroup_cron', 'phrasegroup_moderator', 'phrasegroup_cpoption', 'phrasegroup_cprank',
			'phrasegroup_cpusergroup', 'phrasegroup_holiday', 'phrasegroup_posting', 'phrasegroup_poll', 'phrasegroup_fronthelp',
			'phrasegroup_register', 'phrasegroup_search', 'phrasegroup_showthread', 'phrasegroup_postbit', 'phrasegroup_forumdisplay',
			'phrasegroup_messaging', 'phrasegroup_inlinemod', 'phrasegroup_hooks', 'phrasegroup_cprofilefield', 'phrasegroup_reputationlevel',
			'phrasegroup_infraction', 'phrasegroup_infractionlevel', 'phrasegroup_notice', 'phrasegroup_prefix', 'phrasegroup_prefixadmin',
			'phrasegroup_album', 'phrasegroup_socialgroups', 'phrasegroup_advertising', 'phrasegroup_tagscategories',
			'phrasegroup_contenttypes', 'phrasegroup_vbblock', 'phrasegroup_vbblocksettings', 'phrasegroup_vb5blog', 'phrasegroup_navbarlinks'
		)),
		'bbcode' => array('key' => 'bbcodeid', 'structure' => array('bbcodeid',
			'bbcodetag', 'bbcodereplacement', 'bbcodeexample', 'bbcodeexplanation', 'twoparams', 'title', 'buttonimage', 'options'
		)),
		'icon' => array('key' => 'iconid', 'structure' => array('iconid',
			'title', 'iconpath', 'imagecategoryid', 'displayorder')),
			'userstylevar' => array('key' => array('stylevarid', 'userid'), 'structure' => array(
			'stylevarid', 'userid', 'value', 'dateline')
		),
		'bbcode_video' => array('key' => 'providerid', 'structure' => array('providerid',
			'tagoption', 'provider', 'url', 'regex_url', 'regex_scrape', 'embed', 'priority' )),
			'package' => array('key' => 'packageid', 'structure' => array('packageid', 'productid', 'class')),
		'userchangelog' => array('key' => 'changeid', 'structure' => array('changeid',
			'userid', 'fieldname', 'newvalue', 'oldvalue', 'adminid', 'change_time', 'change_uniq', 'ipaddress'
		)),
		'stylevar' => array('key' => 'stylevarid', 'structure' => array('stylevarid',
			'styleid', 'value', 'dateline', 'username'
		)),
		'videoitem' => array('key' => 'videoitemid', 'structure' => array('videoitemid',
			'nodeid', 'provider', 'code', 'url'
		)),
		'usernote' => array('key' =>  array('usernoteid', 'userid', 'posterid'), 'structure' => array('usernoteid',
			'userid', 'posterid', 'username', 'dateline', 'message', 'title', 'allowsmilies'
		)),
		'access' => array('key' =>  array('userid', 'nodeid'), 'structure' => array('userid',
			'nodeid', 'accessmask'
		)),
		'event' => array('key' =>  array('eventid', 'userid', 'calendarid', 'visible', 'dateline_to'), 'structure' => array('eventid', 'userid',
				'event', 'title', 'allowsmilies', 'recurring', 'recuroption', 'calendarid', 'customfields', 'visible', 'dateline', 'utc',
				'dst', 'dateline_from', 'dateline_to'
		)),
		'subscribeevent' => array('key' =>  array('subscribeeventid', 'userid', 'eventid'), 'structure' => array('subscribeeventid',
			'userid', 'eventid', 'lastreminder', 'reminder'
		)),
		'profileblockprivacy' => array('key' =>  array('userid', 'blockid'), 'structure' => array('userid','blockid', 'requirement')),
		'deletionlog' => array('key' => array('primaryid', 'type'), 'structure' => array('primaryid','type','userid','username','reason','dateline')),
		'infractionlevel' => array(
			'key' => 'infractionlevelid',
			'structure' => array('infractionlevelid', 'points', 'expires', 'period', 'warning', 'extend')
		),
		'infractiongroup' => array(
			'key' => 'infractiongroupid',
			'structure' => array('infractiongroupid', 'usergroupid', 'orusergroupid', 'pointlevel', 'override')
		),
		'infractionban' => array(
			'key' => 'infractionbanid',
			'structure' => array('infractionbanid', 'usergroupid', 'banusergroupid', 'amount', 'period', 'method')
		),
		'stats' => array(
			'key' => 'dateline',
			'structure' => array('dateline', 'nuser', 'nthread', 'npost', 'ausers')
		),
		'profilevisitor' => array(
			'key' => array('userid', 'visitorid'),
			'structure' => array('userid', 'visitorid', 'dateline', 'visible')
		),
		'spamlog' => array(
			'key' => 'postid',
			'structure' => array('postid',)
		),
		'editlog' => array(
			'key' => 'postid',
			'structure' => array('postid', 'userid', 'username', 'dateline', 'reason', 'hashistory')
		),
		'contentpriority' => array(
			'key' => array('contenttypeid', 'sourceid'),
			'structure' => array('contenttypeid', 'sourceid', 'prioritylevel')
		),
	);

	/** This is the definition for queries we will process through. We could also
	* put them in the database, but this eliminates a query.
	* * */
	protected $query_data = array(
		'mysqlVersion' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT version() AS version"
		),
		'getFoundRows' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT FOUND_ROWS()"
		),
		'dropTableBlogTrackbackCount' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DROP,
				'query_string' => "DROP TABLE IF EXISTS {TABLE_PREFIX}{tablename}"
		),
		'createTableBlogTrackbackCount' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_CREATE,
				'query_string' => "CREATE TABLE {TABLE_PREFIX}{tablename} (
					bid INT UNSIGNED NOT NULL DEFAULT '0',
					bstate ENUM('moderation','visible') NOT NULL DEFAULT 'visible',
					btotal INT UNSIGNED NOT NULL DEFAULT '0',
					KEY blogid (bid, state)
				) ENGINE = MEMORY
				SELECT blog_trackback.blogid, blog_trackback.state AS state, COUNT(*) AS total
				FROM {TABLE_PREFIX}blog_trackback AS blog_trackback
				INNER JOIN {TABLE_PREFIX}blog AS blog USING (blogid)
				GROUP BY state, blog_trackback.blogid"
		),
		'updt_nodeconfig' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}cms_nodeconfig SET value={value} WHERE nodeid={nodeid} AND name={name}"),
		'del_nodeconfig' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "DELETE FROM {TABLE_PREFIX}cms_nodeconfig WHERE nodeid={nodeid} AND name={name}"),
		'ins_nodeconfig' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}cms_nodeconfig (nodeid, name, value, serialized)
			VALUES({nodeid}, {name},{value}, {serialized})"),
		'sel_nodeconfig' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT * FROM {TABLE_PREFIX}cms_nodeconfig WHERE nodeid={nodeid} ORDER BY name"),
		'findAttachmentIdFromFileData' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT al.state, al.albumid,
				at.* FROM {TABLE_PREFIX}attachment AS at LEFT JOIN {TABLE_PREFIX}album AS al ON al.albumid = at.contentid
				WHERE at.filedataid = {filedataid} AND at.contenttypeid ={contenttypeid} AND (al.albumid IS NULL OR al.state='public')
				AND at.state = 'visible' ORDER BY albumid, posthash"),
		'firstPublicAlbum' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT albumid FROM {TABLE_PREFIX}album WHERE state='public' AND userid={userid}
				ORDER BY moderation ASC LIMIT 1"),
		'PublicAlbums' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT albumid, title, description FROM {TABLE_PREFIX}album WHERE state='public' AND userid={userid}
				ORDER BY moderation ASC"),
		'CustomProfileAlbums' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT albumid, title, description FROM {TABLE_PREFIX}album WHERE state in ('public', 'profile') AND userid={userid}
					ORDER BY moderation ASC"
		),
		'GetAlbumContents' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "	SELECT a.*, fdr.resize_dateline AS dateline,
				album.state AS albumstate,
				IF (fdr.resize_filesize > 0, 1, 0) AS hasthumbnail, fd.extension, fd.filesize
				FROM {TABLE_PREFIX}attachment AS a
				INNER JOIN {TABLE_PREFIX}filedata AS fd ON (a.filedataid = fd.filedataid)
				LEFT JOIN {TABLE_PREFIX}filedataresize AS fdr ON (fd.filedataid = fdr.filedataid AND fdr.type = 'thumb')
				INNER JOIN {TABLE_PREFIX}album AS album ON (album.albumid = a.contentid)
				WHERE
					a.contentid = {albumid} and a.contenttypeid = {contenttypeid}	AND
					fd.extension IN ({extensions}) AND album.state in ('public', 'profile') ORDER BY album.title"
		),
		'fetch_options' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT * FROM {TABLE_PREFIX}datastore WHERE title in ({option_names})'
		),
		'datastore_lock' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}adminutil SET text = UNIX_TIMESTAMP() WHERE title = 'datastorelock' AND text < UNIX_TIMESTAMP() - 15"
		),
		'fetch_usergroups' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT usergroupid, membergroupids, infractiongroupids FROM {TABLE_PREFIX}user WHERE userid = {userid}'
		),
		'verifyUsername' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT userid, username FROM {TABLE_PREFIX}user
			WHERE userid != {userid}
			AND
			(
				username = {username}
				OR
				username = {username_raw}
			)"
		),
		'lastPostTime' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT MAX(dateline) AS dateline
				FROM {TABLE_PREFIX}blog AS blog
				WHERE blogid = {blogid}
					AND dateline < {dateline}
					AND state = '{state}'"
		),
		'replaceBlogSubscribeEntry' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "REPLACE INTO {TABLE_PREFIX}blog_subscribeentry
				(blogid, dateline, type, userid)
				VALUES
				({blogid}, {dateline}, '{type}', {userid})"
		),
		'userEmailsBlogSubscribeUser' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT
					user.*,
					blog_subscribeuser.blogsubscribeuserid,
					bm.blogmoderatorid,
					ignored.relationid AS ignoreid, buddy.relationid AS buddyid,
					bu.isblogmoderator, IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid
				FROM {TABLE_PREFIX}blog_subscribeuser AS blog_subscribeuser
				INNER JOIN {TABLE_PREFIX}user AS user ON (blog_subscribeuser.userid = user.userid)
				LEFT JOIN {TABLE_PREFIX}blog_moderator AS bm ON (bm.userid = user.userid)
				LEFT JOIN {TABLE_PREFIX}userlist AS buddy ON (buddy.userid = {userid} AND buddy.relationid = user.userid AND buddy.type = 'buddy')
				LEFT JOIN {TABLE_PREFIX}userlist AS ignored ON (ignored.userid = {userid} AND ignored.relationid = user.userid AND ignored.type = 'ignore')
				LEFT JOIN {TABLE_PREFIX}blog_user AS bu ON (bu.bloguserid = user.userid)
				WHERE
					blog_subscribeuser.bloguserid = {userid}
						AND
					blog_subscribeuser.userid <> {userid} AND
					blog_subscribeuser.type = 'email'
						AND
					user.usergroupid <> 3
						AND
					user.lastactivity >= {dateline}"
		),
		'userEmails' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT
					user.*,
					blog_subscribeuser.blogsubscribeuserid,
					bm.blogmoderatorid,
					ignored.relationid AS ignoreid, buddy.relationid AS buddyid,
					bu.isblogmoderator, IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid
				FROM {TABLE_PREFIX}blog_subscribeuser AS blog_subscribeuser
				INNER JOIN {TABLE_PREFIX}user AS user ON (blog_subscribeuser.userid = user.userid)
				LEFT JOIN {TABLE_PREFIX}blog_moderator AS bm ON (bm.userid = user.userid)
				LEFT JOIN {TABLE_PREFIX}userlist AS buddy ON (buddy.userid = {userid} AND buddy.relationid = user.userid AND buddy.type = 'buddy')
				LEFT JOIN {TABLE_PREFIX}userlist AS ignored ON (ignored.userid = {userid} AND ignored.relationid = user.userid AND ignored.type = 'ignore')
				LEFT JOIN {TABLE_PREFIX}blog_user AS bu ON (bu.bloguserid = user.userid)
				WHERE
					blog_subscribeuser.bloguserid = {userid}
						AND
					blog_subscribeuser.type = 'email'
						AND
					user.usergroupid <> 3
						AND
					user.lastactivity >= {dateline}"
		),
		'deleteUserBlog' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "DELETE
						{TABLE_PREFIX}blog_text,
						{TABLE_PREFIX}blog_textparsed,
						{TABLE_PREFIX}blog_editlog,
						{TABLE_PREFIX}blog_moderation,
						{TABLE_PREFIX}blog_deletionlog
						FROM {TABLE_PREFIX}blog_text
						LEFT JOIN {TABLE_PREFIX}blog_textparsed ON {TABLE_PREFIX}blog_textparsed.blogtextid = {TABLE_PREFIX}blog_text.blogtextid)
						LEFT JOIN {TABLE_PREFIX}blog_editlog ON ({TABLE_PREFIX}blog_editlog.blogtextid = {TABLE_PREFIX}blog_text.blogtextid)
						LEFT JOIN {TABLE_PREFIX}blog_moderation ON ({TABLE_PREFIX}blog_moderation.primaryid = {TABLE_PREFIX}blog_text.blogtextid AND {TABLE_PREFIX}blog_moderation.type = 'blogtextid')
						LEFT JOIN {TABLE_PREFIX}blog_deletionlog ON ({TABLE_PREFIX}blog_deletionlog.primaryid = {TABLE_PREFIX}blog_text.blogtextid AND {TABLE_PREFIX}blog_deletionlog.type = 'blogtextid')
						WHERE {TABLE_PREFIX}blog_text.blogid IN ({blogids})"
		),
		'deleteUserReadStatus' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "DELETE FROM {TABLE_PREFIX}blog_userread
						WHERE userid = {userid} OR bloguserid = {bloguserid}"
		),
		'blogUserGroups' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}blog
						SET
						postedby_userid = userid,
						postedby_username = username
						WHERE postedby_userid = {postedby_userid}"
		),
		'replaceBlogTexts' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "REPLACE INTO {TABLE_PREFIX}blog_tachyentry
							(userid, blogid, lastcomment, lastcommenter, lastblogtextid)
						VALUES
							({userid}, {blogid}, '{lastcomment}', {lastblogtextid})
"
		),
		'selectMaxBlogTextDateline' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT MAX(dateline) AS dateline
						FROM {TABLE_PREFIX}blog_text AS blog_text
						WHERE blogid = {blogid}
						AND dateline < {dateline}
						AND state = 'visible'"
		),
		'userEmailsBlogText' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT
								user.*,
								blog_subscribeentry.blogsubscribeentryid,
								blog_moderator.blogmoderatorid,
								ignored.relationid AS ignoreid,
								buddy.relationid AS buddyid,
								blog.categories,
								blog.options
							FROM {TABLE_PREFIX}blog_subscribeentry AS blog_subscribeentry
							INNER JOIN {TABLE_PREFIX}user AS user ON (blog_subscribeentry.userid = user.userid)
							LEFT JOIN {TABLE_PREFIX}blog_moderator AS blog_moderator ON (blog_moderator.userid = user.userid)
							LEFT JOIN {TABLE_PREFIX}userlist AS buddy ON (buddy.userid = {userinfouserid} AND buddy.relationid = user.userid AND buddy.type = 'buddy')
							LEFT JOIN {TABLE_PREFIX}userlist AS ignored ON (ignored.userid = {userinfouserid} AND ignored.relationid = user.userid AND ignored.type = 'ignore')
							LEFT JOIN {TABLE_PREFIX}blog AS blog ON (blog.blogid = blog_subscribeentry.blogid)
							WHERE blog_subscribeentry.blogid = {blogid} AND
								blog_subscribeentry.type = 'email' AND
								user.usergroupid <> 3 AND
								user.userid <> {userid} AND
								user.lastactivity >= {lastactivity}"
		),
		'replaceSubscribeEntryBlogText' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "REPLACE INTO {TABLE_PREFIX}blog_subscribeentry
				(blogid, dateline, type, userid)
				VALUES
				({blogid}, {dateline}, '{type}', {userid})"
		),
		'deleteBlogText' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "DELETE {TABLE_PREFIX}blog_text, {TABLE_PREFIX}blog_textparsed
					FROM {TABLE_PREFIX}blog_text
					LEFT JOIN {TABLE_PREFIX}blog_textparsed ON ({TABLE_PREFIX}blog_textparsed.blogtextid = {TABLE_PREFIX}blog_text.blogtextid)
					WHERE {TABLE_PREFIX}blog_text.blogtextid = {blogtextid}"
		),
		'replaceDeletionLogBlogText' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "REPLACE INTO {TABLE_PREFIX}blog_deletionlog
						(primaryid, type, userid, username, reason, dateline)
					VALUES
						({blogtextid}, 'blogtextid', {userid}, '{username}', '{reason}', {dateline})"
		),
		'updatePasswordForum' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}forum
				SET password = {password'}
				WHERE FIND_IN_SET({forumid}, parentlist)"
		),
		'replaceTachyforumcounterForum' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "REPLACE INTO {TABLE_PREFIX}tachyforumcounter
						(userid, forumid, threadcount, replycount)
					VALUES
						({userid}), {forumid}, {threadcount}, {replycount})"
		),
		'replaceTachyforumpostForum' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "REPLACE INTO {TABLE_PREFIX}tachyforumpost
						(userid, forumid, lastpost, lastposter, lastposterid, lastpostid, lastthread, lastthreadid, lasticonid, lastprefixid)
					VALUES
						({userid}, {forumid}, {lastpost}, {lastposter}, {lastposterid}, {lastpostid}, {lastthread}, {lastthreadid}, {lasticonid}, {lastprefixid})"
		),
		'getDatelineGMHash' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT dateline
				FROM {TABLE_PREFIX}groupmessage_hash
				WHERE postuserid = {postuserid}
					AND dateline > {dateline}
				ORDER BY dateline DESC
				LIMIT 1"
		),
		'getDupleHashGM' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT hash.groupid
				FROM {TABLE_PREFIX}groupmessage_hash AS hash
				WHERE hash.postuserid = {postuserid} AND
					hash.dupehash = {dupehash} AND
					hash.dateline > {dateline}"
		),
		'getRecipientsPM' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT usertextfield.*, user.*
						FROM {TABLE_PREFIX}user AS user
						LEFT JOIN {TABLE_PREFIX}usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
						WHERE username IN({username})
						ORDER BY user.username"
		),
		/* Template API SQL Start */
		'template_get_existing' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT templateid, styleid, product FROM {TABLE_PREFIX}template
								WHERE title = {title}
								AND templatetype = 'template'",
		),
		'template_insert' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}template
										(styleid, title, template, template_un, dateline, username, version, product)
								VALUES
										({dostyleid}, {title}, {template}, {template_un}, {dateline}, {username}, {version}, {product})",
		),
		'template_savehistory' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}templatehistory
										(styleid, title, template, dateline, username, version, comment)
								VALUES
										({dostyleid}, {title}, {template_un}, {dateline}, {username}, {version}, {comment})",
		),
		'template_fetchbyid' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT title, styleid, dateline, username, template, template_un, version
								FROM {TABLE_PREFIX}template
								WHERE templateid = {templateid}",
		),
		'template_fetchbystyleandtitle' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT templateid
								FROM {TABLE_PREFIX}template
								WHERE styleid = {styleid} AND title = {title}",
		),
		'template_fetchbystyleandtitle2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT templateid, title, styleid, dateline, username, version
								FROM {TABLE_PREFIX}template
								WHERE title = {title}
										AND styleid IN (-1, {styleid})",
																					),
		'template_deletehistory2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "DELETE FROM {TABLE_PREFIX}templatehistory WHERE styleid = {styleid}",
		),
		'template_update' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}template SET
										title = {title},
										template = {template},
										template_un = {template_un},
										dateline = {dateline},
										username = {username},
										version = {version},
										product = {product},
										mergestatus = 'none'
								WHERE templateid = {templateid} AND
										(
												MD5(template_un) = {hash} OR
												template_un = {template_un}
										)"
		),
		'template_update2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}template SET
										template = {template},
										template_un = {template_un},
										dateline = {dateline},
										username = {username},
										version = {version},
										mergestatus = 'none'
								WHERE templateid = {templateid}"
		),
		'template_delete2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "DELETE FROM {TABLE_PREFIX}template
								WHERE styleid = {styleid}",
		),

		'template_deletefrom_templatemerge2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "
								DELETE FROM {TABLE_PREFIX}templatemerge
								WHERE templateid IN (
										SELECT templateid
										FROM {TABLE_PREFIX}template
										WHERE styleid = {styleid}
								)
						",
		),
		'template_updatecustom_product' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}template
								SET product = {product}
								WHERE title = {title}
										AND styleid <> -1"
		),
		'template_fetchoriginal' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT templateid, styleid, title, template_un
								FROM {TABLE_PREFIX}template
								WHERE styleid IN (-1,0) AND title = {title}",
		),
		'template_update_mergestatus' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}template
						SET mergestatus = 'none'
						WHERE templateid IN ({templateids})",
		),
		'template_getrevertingtemplates' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT DISTINCT t1.templateid, t1.title
						FROM {TABLE_PREFIX}template AS t1
						INNER JOIN {TABLE_PREFIX}template AS t2 ON
								(t2.styleid IN ({styleparentlist}) AND t2.styleid <> {styleid} AND t2.title = t1.title)
						WHERE t1.templatetype = 'template'
								AND t1.styleid = {styleid}",
		),
		'template_getmasters' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT templateid, title
								FROM {TABLE_PREFIX}template
								WHERE templatetype = 'template'
										AND styleid IN (-1,0)
								ORDER BY title",
		),
		'template_getmasters2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT title, templateid FROM {TABLE_PREFIX}template WHERE styleid IN (-1,0)",
		),
		'template_table_query_drop' => array(
			// TODO: Querytype
			vB_dB_Query::QUERYTYPE_KEY => '',
			'query_string' => "DROP TABLE IF EXISTS {TABLE_PREFIX}template_temp",
		),
		'template_table_query' => array(
			// TODO: Querytype
			vB_dB_Query::QUERYTYPE_KEY => '',
			'query_string' => "CREATE TABLE {TABLE_PREFIX}template_temp (
								templateid INT UNSIGNED NOT NULL AUTO_INCREMENT,
								styleid SMALLINT NOT NULL DEFAULT '0',
								title VARCHAR(100) NOT NULL DEFAULT '',
								template MEDIUMTEXT,
								template_un MEDIUMTEXT,
								templatetype ENUM('template','stylevar','css','replacement') NOT NULL DEFAULT 'template',
								dateline INT UNSIGNED NOT NULL DEFAULT '0',
								username VARCHAR(100) NOT NULL DEFAULT '',
								version VARCHAR(30) NOT NULL DEFAULT '',
								product VARCHAR(25) NOT NULL DEFAULT '',
								mergestatus ENUM('none', 'merged', 'conflicted') NOT NULL DEFAULT 'none',
								PRIMARY KEY (templateid),
								UNIQUE KEY title (title, styleid, templatetype),
								KEY styleid (styleid)
						)",
		),
		'template_table_query_insert' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}template_temp
								(styleid, title, template, template_un, templatetype, dateline, username, version, product, mergestatus)
								SELECT {styleid, title, template, template_un, templatetype, dateline, username, version, product, mergestatus} FROM {TABLE_PREFIX}template ORDER BY styleid, templatetype, title
						",
		),
		'template_table_query_alter' => array(
			// TODO: Querytype
			vB_dB_Query::QUERYTYPE_KEY => '',
			'query_string' => "ALTER TABLE {TABLE_PREFIX}template_temp RENAME {TABLE_PREFIX}template",
		),
		'template_drop' => array(
			// TODO: Querytype
			vB_dB_Query::QUERYTYPE_KEY => '',
			'query_string' => "DROP TABLE {TABLE_PREFIX}template",
		),
		/* Template API SQL End */

		/* Style API SQL Start */
		'style_count' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT COUNT(*) AS styles FROM {TABLE_PREFIX}style WHERE userselect = 1",
		),
		'style_checklast' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT userselect FROM {TABLE_PREFIX}style WHERE styleid = {styleid}",
		),
		'style_delete' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "DELETE FROM {TABLE_PREFIX}style WHERE styleid = {styleid}",
		),
		'style_deletestylevar' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "DELETE FROM {TABLE_PREFIX}stylevar WHERE styleid = {styleid}",
		),
		'style_updateparent' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}style
				SET parentid = {parentid},
				parentlist = {parentlist}
				WHERE parentid = {styleid}
			",
		),
		'style_fetchrecord' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT * FROM {TABLE_PREFIX}style
				WHERE (styleid = {styleid} AND userselect = 1)
					OR styleid = {defaultstyleid}
			",
		),
		/* Style API SQL End */

		/* User API SQL Start */
		'user_fetchidbyusername' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT userid, username
				FROM {TABLE_PREFIX}user
				WHERE username = {username}",
			),
		'user_fetchforupdating' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT user.*, avatar.avatarpath, customavatar.dateline AS avatardateline, customavatar.width AS avatarwidth, customavatar.height AS avatarheight,
				NOT ISNULL(customavatar.userid) AS hascustomavatar, usertextfield.signature,
				customprofilepic.width AS profilepicwidth, customprofilepic.height AS profilepicheight,
				customprofilepic.dateline AS profilepicdateline, usergroup.adminpermissions,
				NOT ISNULL(customprofilepic.userid) AS hasprofilepic,
				NOT ISNULL(sigpic.userid) AS hassigpic,
				sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight,
				sigpic.userid AS profilepic, sigpic.dateline AS sigpicdateline,
				usercsscache.cachedcss
				FROM {TABLE_PREFIX}user AS user
				LEFT JOIN {TABLE_PREFIX}avatar AS avatar ON(avatar.avatarid = user.avatarid)
				LEFT JOIN {TABLE_PREFIX}customavatar AS customavatar ON(customavatar.userid = user.userid)
				LEFT JOIN {TABLE_PREFIX}customprofilepic AS customprofilepic ON(customprofilepic.userid = user.userid)
				LEFT JOIN {TABLE_PREFIX}sigpic AS sigpic ON(sigpic.userid = user.userid)
				LEFT JOIN {TABLE_PREFIX}usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
				LEFT JOIN {TABLE_PREFIX}usergroup AS usergroup ON(usergroup.usergroupid = user.usergroupid)
				LEFT JOIN {TABLE_PREFIX}usercsscache AS usercsscache ON (user.userid = usercsscache.userid)
				WHERE user.userid = {userid}
			",
		),
		'user_fetchaccesslist' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT * FROM {TABLE_PREFIX}access WHERE userid = {userid}
			",
		),
		'user_fetchmoderate' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT userid, username, email, ipaddress
				FROM {TABLE_PREFIX}user
				WHERE usergroupid = 4
				ORDER BY username
			",
		),
		'user_fetchusergroup' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT title
				FROM {TABLE_PREFIX}usergroup
				WHERE usergroupid = {usergroupid}
			",
		),
		'user_updateusergroup' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}user
				SET displaygroupid = IF(displaygroupid = usergroupid, 0, displaygroupid),
					usergroupid = {usergroupid}
				WHERE userid IN({userids})
			",
		),
		'user_fetch' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT userid, username
				FROM {TABLE_PREFIX}user
				WHERE userid IN ({userids})
				LIMIT {startat}, 50
			",
		),
		'user_updatethread' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}thread SET
					postuserid = 0,
					postusername = {username}
				WHERE postuserid = {userid}
			",
		),
		'user_updatepost' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}post SET
					userid = 0,
					username = {username}
				WHERE userid = {userid}
			",
		),
		'user_deleteusertextfield' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "
				DELETE FROM {TABLE_PREFIX}usertextfield WHERE userid IN({userids})
			",
		),
		'user_deleteuserfield' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "
				DELETE FROM {TABLE_PREFIX}userfield WHERE userid IN({userids})
			",
		),
		'user_deleteuser' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "
				DELETE FROM {TABLE_PREFIX}user WHERE userid IN({userids})
			",
		),
		'user_fetchwithtextfield' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT *
				FROM {TABLE_PREFIX}user AS user
				LEFT JOIN {TABLE_PREFIX}usertextfield AS usertextfield USING(userid)
				WHERE user.userid = {userid}
			",
		),
		'user_updatesubscribethread' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}subscribethread
				SET folderid = 0
				WHERE userid = {userid}
			",
		),
		'user_insertsubscribediscussion' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				INSERT IGNORE INTO {TABLE_PREFIX}subscribediscussion
					(userid, discussionid, emailupdate)
				SELECT {destuserid}, discussionid, emailupdate
				FROM {TABLE_PREFIX}subscribediscussion AS src
				WHERE src.userid = {sourceuserid}
			",
		),
		'user_insertsubscribegroup' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				INSERT IGNORE INTO {TABLE_PREFIX}subscribegroup
					(userid, groupid)
				SELECT {destuserid}, groupid
				FROM {TABLE_PREFIX}subscribegroup AS src
				WHERE src.userid = {sourceuserid}
			",
		),
		'user_insertuserlist' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				INSERT IGNORE INTO {TABLE_PREFIX}userlist
					(userid, relationid, type, friend)
				SELECT {destuserid}, relationid, type, friend
				FROM {TABLE_PREFIX}userlist
				WHERE userid = {sourceuserid}
			",
		),
		'user_updateuserlist' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE IGNORE {TABLE_PREFIX}userlist
				SET relationid = {destuserid}
				WHERE relationid = {sourceuserid}
					AND relationid <> {destuserid}
			",
		),
		'user_fetchuserlistcount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*) FROM {TABLE_PREFIX}userlist
				WHERE userid = {userid}
					AND type = 'buddy'
					AND friend = 'yes'
			",
		),
		'user_fetchinfractiongroup' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT usergroupid, orusergroupid, pointlevel, override
				FROM {TABLE_PREFIX}infractiongroup
				ORDER BY pointlevel
			",
		),
		'user_updateannouncement' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}announcement
				SET userid = {destuserid}
				WHERE userid = {sourceuserid}
			",
		),
		'user_updateattachment' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}attachment
				SET userid = {destuserid}
				WHERE userid = {sourceuserid}
			",
		),
		'user_updatepost2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}post SET
					userid = {destuserid},
					username = {destusername}
				WHERE userid = {sourceuserid}
			",
		),
		'user_updatethread2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}thread SET
					postuserid = {destuserid},
					postusername = {destusername}
				WHERE postuserid = {sourceuserid}
			",
		),
		'user_updatedeletionlog' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}deletionlog SET
					userid = {destuserid},
					username = {destusername}
				WHERE userid = {sourceuserid}
			",
		),
		'user_updatepostedithistory' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}postedithistory SET
					userid = {destuserid},
					username = {destusername}
				WHERE userid = {sourceuserid}
			",
		),
		'user_updateeditlog' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}editlog SET
					userid = {destuserid},
					username = {destusername}
				WHERE userid = {sourceuserid}
			",
		),
		'user_fetchpollvote' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT DISTINCT poll.*
				FROM {TABLE_PREFIX}pollvote AS sourcevote
				INNER JOIN {TABLE_PREFIX}poll AS poll ON (sourcevote.nodeid = poll.nodeid)
				INNER JOIN {TABLE_PREFIX}pollvote AS destvote ON (destvote.nodeid = poll.nodeid AND destvote.userid = {destuserid})
				WHERE sourcevote.userid = {sourceuserid}
			",
		),
		'user_updatepollvote' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}pollvote SET
					userid = {destuserid}
				WHERE userid = {sourceuserid}
			",
		),
		'user_deletepollvote' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "
				DELETE FROM {TABLE_PREFIX}pollvote
				WHERE userid = {userid}
			",
		),
		'user_fetchpollvote2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT polloptionid, votedate
				FROM {TABLE_PREFIX}pollvote
				WHERE nodeid = {nodeid}
			",
		),
		'user_updatethreadrate' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}threadrate
				SET userid = {destuserid}
				WHERE userid = {sourceuserid}
			",
		),
		'user_updateusernote' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}usernote
				SET posterid = {destuserid}
				WHERE posterid = {sourceuserid}
			",
		),
		'user_updateusernote2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}usernote
				SET userid = {destuserid}
				WHERE userid = {sourceuserid}
			",
		),
		'user_updateevent' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}event
				SET userid = {destuserid}
				WHERE userid = {sourceuserid}
			",
		),
		'user_updatereputation' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}reputation
				SET userid = {destuserid}
				WHERE userid = {sourceuserid}
			",
		),
		'user_updatereputation2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}reputation
				SET whoadded = {destuserid}
				WHERE whoadded = {sourceuserid}
			",
		),
		'user_updateinfraction' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}infraction
				SET userid = {destuserid}
				WHERE userid = {sourceuserid}
			",
		),
		'user_updateinfraction2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}infraction
				SET whoadded = {destuserid}
				WHERE whoadded = {sourceuserid}
			",
		),
		'user_updateusergrouprequest' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}usergrouprequest
				SET userid = {destuserid}
				WHERE userid = {sourceuserid}
			",
		),
		'user_updatesocgroupreqcount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}user SET
				socgroupreqcount = {socgroupreqcount}
				WHERE userid = {userid}
			",
		),
		'user_updatepaymentinfo' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}paymentinfo
				SET userid = {destuserid}
				WHERE userid = {sourceuserid}
			",
		),
		'user_updatesubscriptionlog' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}subscriptionlog
				SET userid = {destuserid}
				WHERE userid = {sourceuserid}
			",
		),
		'user_fetchsubscriptionlog' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT
					subscriptionlogid, subscriptionid, expirydate
				FROM {TABLE_PREFIX}subscriptionlog
				WHERE
					userid = {userid}
						AND
					status = 1
			",
		),
		'user_deletesubscriptionlog' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "
				DELETE FROM {TABLE_PREFIX}subscriptionlog
				WHERE subscriptionlogid IN ({ids})
			",
		),
		'user_updatesubscriptionlog2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}subscriptionlog
				SET expirydate = {expirydate}
				WHERE subscriptionlogid = {subscriptionlogid}
			",
		),
		'user_searchpostip' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT DISTINCT ipaddress
				FROM {TABLE_PREFIX}node
				WHERE userid = {userid} AND
				ipaddress <> {ipaddress} AND
				ipaddress <> ''
				ORDER BY ipaddress
			",
		),
		'user_fetchcontacts' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT type, friend
				FROM {TABLE_PREFIX}userlist AS userlist
				WHERE userlist.userid = {user1}
					AND userlist.relationid = {user2}
			",
		),
		'user_fetchlogin' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT userid, usergroupid, membergroupids, infractiongroupids, username, password, salt
				FROM {TABLE_PREFIX}user WHERE username = {username}
			",
		),
		'user_fetchemailpassword' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT userid, username, email, languageid
				FROM {TABLE_PREFIX}user
				WHERE email = {email}
			",
		),
		'user_useractivation' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT activationid, dateline
				FROM {TABLE_PREFIX}useractivation
				WHERE type = 1
					AND userid = {userid}
			",
		),
		'user_deleteactivationid' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "
				DELETE FROM {TABLE_PREFIX}useractivation
				WHERE userid = {userid} AND type = 1
			",
		),
		'user_replaceuseractivation' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				REPLACE INTO {TABLE_PREFIX}useractivation
					(userid, dateline, activationid, type, usergroupid, emailchange)
				VALUES
					({userid}, {timenow}, {activateid} , {type}, {usergroupid}, {emailchange})
			",
		),
		'user_replaceuseractivation2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				REPLACE INTO {TABLE_PREFIX}useractivation
					(userid, dateline, activationid, type, usergroupid)
				VALUES
					({userid}, {timenow}, {activateid} , {type}, {usergroupid})
			",
		),
		'user_fetchstrikes' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*) AS strikes, MAX(striketime) AS lasttime
				FROM {TABLE_PREFIX}strikes
				WHERE ip_4 = {ip_4} AND ip_3 = {ip_3} AND ip_2 = {ip_2} AND ip_1 = {ip_1}"
		),
		'user_fetchprofilefieldsforregistration' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT *
				FROM {TABLE_PREFIX}profilefield
				WHERE editable > 0 AND required <> 0
				ORDER BY displayorder"
			),
		'user_fetchcurrentbans' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT user.userid, userban.liftdate, userban.bandate
				FROM {TABLE_PREFIX}user AS user
				LEFT JOIN {TABLE_PREFIX}userban AS userban ON(userban.userid = user.userid)
				WHERE user.userid IN ({userids})"
		),
		/* User API SQL End */

		/* Userrank API SQL Start */
		'userrank_fetchranks' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT rankid, ranklevel, minposts, rankimg, ranks.usergroupid, title, type, display, stack
				FROM {TABLE_PREFIX}ranks AS ranks
				LEFT JOIN {TABLE_PREFIX}usergroup AS usergroup USING(usergroupid)
				ORDER BY ranks.usergroupid, minposts
			",
		),
		/* Userrank API SQL End */


		/* User Datamanager Start */
		'userdm_reputationlevel' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT reputationlevelid
				FROM {TABLE_PREFIX}reputationlevel
				WHERE {reputation} >= minimumreputation
				ORDER BY minimumreputation DESC
				LIMIT 1",
		),
		'userdm_verifyusername' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT userid, username FROM {TABLE_PREFIX}user
			WHERE userid != {userid}
			AND
			(
				username = {username}
				OR
				username = {username_raw}
				)",
		),
		'userdm_unregisteredphrases' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT text
				FROM {TABLE_PREFIX}phrase
				WHERE varname = 'unregistered'
					AND fieldname = 'global'",
		),
		'userdm_showusercol' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SHOW COLUMNS FROM {TABLE_PREFIX}user LIKE 'username'",
		),
		'userdm_deletepasswordhistory' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "
				DELETE FROM {TABLE_PREFIX}passwordhistory
				WHERE userid = {userid}
				AND passworddate <= FROM_UNIXTIME({passworddate})
			",
		),
		'userdm_historycheck' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT UNIX_TIMESTAMP(passworddate) AS passworddate
				FROM {TABLE_PREFIX}passwordhistory
				WHERE userid = {userid}
				AND password = {password}
			",
		),
		'userdm_usertitle' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT title
				FROM {TABLE_PREFIX}usertitle
				WHERE minposts <= {minposts}
				ORDER BY minposts DESC
				LIMIT 1
			",
		),
		'userdm_profilefields' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT profilefieldid
				FROM {TABLE_PREFIX}profilefield
				WHERE editable > 0 AND required <> 0
			",
		),
		'userdm_updateuseractivation' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
					UPDATE {TABLE_PREFIX}useractivation
					SET usergroupid = {usergroupid}
					WHERE userid = {userid}
						AND type = 0"
		),
		'userdm_friendlist' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT relationid, friend
				FROM {TABLE_PREFIX}userlist
				WHERE userid = {userid}
					AND type = 'buddy'
					AND friend IN('pending','yes')
			"
		),
		'userdm_updatefriendreqcount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}user
				SET friendreqcount = IF(friendreqcount > 0, friendreqcount - 1, 0)
				WHERE userid IN ({userids})
			"
		),
		'userdm_updatefriendcount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}user
				SET friendcount = IF(friendcount > 0, friendcount - 1, 0)
				WHERE userid IN ({userids})
			"
		),
		'userdm_groupmemeberships' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT socialgroup.*
				FROM {TABLE_PREFIX}socialgroupmember AS socialgroupmember
				INNER JOIN {TABLE_PREFIX}socialgroup AS socialgroup ON
					(socialgroup.groupid = socialgroupmember.groupid)
				WHERE socialgroupmember.userid = {userid}
			"
		),
		'userdm_picture' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT a.attachmentid, a.filedataid, a.userid
				FROM {TABLE_PREFIX}attachment AS a
				WHERE
					a.userid = {userid}
						AND
					a.contenttypeid IN ({contenttypeids})
			"
		),
		'userdm_moderatedmembers' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT SUM(moderatedmembers) FROM {TABLE_PREFIX}socialgroup
				WHERE creatoruserid = {creatoruserid}
			"
		),
		'userdm_updatefriendcount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}pmtext SET
					touserarray = REPLACE(touserarray,
						'i:{userid};s:{usernamelength}:\"{username}\";',
						'i:{userid};s:{username2length}:\"{username2}\";'
					)
				WHERE touserarray LIKE '%i:{userid};s:{usernamelength}:\"{username}\";%'
			"
		),
		'userdm_threadsubscription' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT subscribethread.canview, subscribethreadid, thread.forumid
				FROM {TABLE_PREFIX}subscribethread AS subscribethread
				INNER JOIN {TABLE_PREFIX}thread AS thread ON (thread.threadid = subscribethread.threadid)
				WHERE subscribethread.userid = {userid}
					AND thread.forumid IN ({forumids})
			"
		),
		'userdm_infractiongroup' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT orusergroupid, override
				FROM {TABLE_PREFIX}infractiongroup AS infractiongroup
				WHERE infractiongroup.usergroupid IN (-1, {usergroupid})
					AND infractiongroup.pointlevel <= {ipoints}
				ORDER BY pointlevel
			"
		),
			/* User Datamanager End */

		/* Usergroup API SQL Start */
		'usergroup_fetchperms' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT usergroup.usergroupid, title,
					(COUNT(forumpermission.forumpermissionid) + COUNT(calendarpermission.calendarpermissionid)) AS permcount
				FROM {TABLE_PREFIX}usergroup AS usergroup
				LEFT JOIN {TABLE_PREFIX}forumpermission AS forumpermission ON (usergroup.usergroupid = forumpermission.usergroupid)
				LEFT JOIN {TABLE_PREFIX}calendarpermission AS calendarpermission ON (usergroup.usergroupid = calendarpermission.usergroupid)
				GROUP BY usergroup.usergroupid
				HAVING permcount > 0
				ORDER BY title
			"
		),
		'usergroup_checkadmin' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
					SELECT COUNT(*) AS usergroups
					FROM {TABLE_PREFIX}usergroup
					WHERE (adminpermissions & {cancontrolpanel}) AND
						usergroupid <> {usergroupid}
			"
		),
		'usergroup_makeuservisible' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}user
				SET options = (options & ~{invisible})
				WHERE usergroupid = {usergroupid}
			"
		),
		'usergroup_fetchausers' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT user.userid
				FROM {TABLE_PREFIX}user AS user
				LEFT JOIN {TABLE_PREFIX}administrator as administrator ON (user.userid = administrator.userid)
				WHERE administrator.userid IS NULL AND
					user.usergroupid = {usergroupid}
			"
		),
		'usergroup_fetchausers2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT userid FROM {TABLE_PREFIX}user
				WHERE usergroupid NOT IN ({ausergroupids})
					AND NOT FIND_IN_SET('{ausergroupids}', membergroupids)
					AND (usergroupid = {usergroupid}
					OR FIND_IN_SET('{usergroupid}', membergroupids))
			"
		),
		'usergroup_insertprefixpermission' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				REPLACE INTO {TABLE_PREFIX}prefixpermission (usergroupid, prefixid)
				SELECT {newugid}, prefixid FROM {TABLE_PREFIX}prefix
				WHERE options & {deny_by_default}
			"
		),
		'usergroup_fetchmarkups' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT usergroupid, opentag, closetag
				FROM {TABLE_PREFIX}usergroup
				WHERE opentag <> '' OR
				closetag <> ''
			"
		),
		'usergroup_deleteuserpromotion' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "
				DELETE FROM {TABLE_PREFIX}userpromotion
				WHERE usergroupid = {usergroupid} OR joinusergroupid = {usergroupid}
			"
		),
		'usergroup_fetchmemberstoremove' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT userid, username, membergroupids
				FROM {TABLE_PREFIX}user
				WHERE FIND_IN_SET({usergroupid}, membergroupids)
			"
		),
		'usergroup_fetchwithjoinrequests' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT req.usergroupid, COUNT(req.usergrouprequestid) AS requests,
				IF(usergroup.usergroupid IS NULL, 0, 1) AS validgroup
				FROM {TABLE_PREFIX}usergrouprequest AS req
				LEFT JOIN {TABLE_PREFIX}usergroup AS usergroup ON (usergroup.usergroupid = req.usergroupid)
				LEFT JOIN {TABLE_PREFIX}user AS user ON (user.userid = req.userid)
				WHERE user.userid IS NOT NULL
				GROUP BY req.usergroupid
			"
		),
		'usergroup_fetchleaders' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT usergroupleader.userid, user.username
				FROM {TABLE_PREFIX}usergroupleader AS usergroupleader
				INNER JOIN {TABLE_PREFIX}user AS user USING(userid)
				WHERE usergroupleader.usergroupid = {usergroupid}
			"
		),
		'usergroup_fetchallleaders' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT ugl.*, user.username
				FROM {TABLE_PREFIX}usergroupleader AS ugl
				INNER JOIN {TABLE_PREFIX}user AS user USING(userid)
			"
		),
		'usergroup_fetchjoinrequests' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT req.*, user.username
				FROM {TABLE_PREFIX}usergrouprequest AS req
				INNER JOIN {TABLE_PREFIX}user AS user USING(userid)
				WHERE req.usergroupid = {usergroupid}
				ORDER BY user.username
			"
		),
		'usergroup_fetchjoinrequests2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT req.userid, user.username, user.usergroupid, user.membergroupids, req.usergrouprequestid
				FROM {TABLE_PREFIX}usergrouprequest AS req
				INNER JOIN {TABLE_PREFIX}user AS user USING(userid)
				WHERE usergrouprequestid IN ({auth})
				ORDER BY user.username
			"
		),
		'usergroup_fetchjoinrequests3' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT usergroup.title, usergroup.opentag, usergroup.closetag, usergroup.usergroupid, COUNT(usergrouprequestid) AS requests
				FROM {TABLE_PREFIX}usergroup AS usergroup
				LEFT JOIN {TABLE_PREFIX}usergrouprequest AS req USING(usergroupid)
				WHERE usergroup.usergroupid IN({usergroupids})
				GROUP BY usergroup.usergroupid
				ORDER BY usergroup.title
			"
		),
		'usergroup_updatemembergroup' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}user SET
				membergroupids = IF(membergroupids = '', {usergroupid}, CONCAT(membergroupids, ',{usergroupid}'))
				WHERE userid IN ({auth})
			"
		),
		'usergroup_fetchusertitle' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT *
				FROM {TABLE_PREFIX}usertitle
				WHERE minposts < {posts}
				ORDER BY minposts DESC
				LIMIT 1
			"
		),
		/* Usergroup API SQL End */

		/* Phrase API SQL Start */
		'phrase_fetchorphans' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT orphan.varname, orphan.languageid, orphan.fieldname
				FROM {TABLE_PREFIX}phrase AS orphan
				LEFT JOIN {TABLE_PREFIX}phrase AS phrase ON (phrase.languageid IN(-1, 0) AND phrase.varname = orphan.varname AND phrase.fieldname = orphan.fieldname)
				WHERE orphan.languageid NOT IN (-1, 0)
					AND phrase.phraseid IS NULL
				ORDER BY orphan.varname
			"
		),
		'phrase_fetchupdates' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT pGlobal.phraseid, pCustom.varname, pCustom.languageid,
					pCustom.username AS customuser, pCustom.dateline AS customdate, pCustom.version AS customversion,
					pGlobal.username AS globaluser, pGlobal.dateline AS globaldate, pGlobal.version AS globalversion,
					pGlobal.product, phrasetype.title AS phrasetype_title
				FROM {TABLE_PREFIX}phrase AS pCustom
				INNER JOIN {TABLE_PREFIX}phrase AS pGlobal ON (pGlobal.languageid = -1 AND pGlobal.varname = pCustom.varname AND pGlobal.fieldname = pCustom.fieldname)
				LEFT JOIN {TABLE_PREFIX}phrasetype AS phrasetype ON (phrasetype.fieldname = pGlobal.fieldname)
				WHERE pCustom.languageid <> -1
				ORDER BY pCustom.varname
			"
		),
		'phrase_replace' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				REPLACE INTO {TABLE_PREFIX}phrase
					(languageid, varname, text, fieldname, product, username, dateline, version)
				VALUES
					({languageid}, {varname}, {text}, {fieldname}, {product}, {username}, {dateline}, {version})
			"
		),
		'phrase_fetchid' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT phraseid FROM {TABLE_PREFIX}phrase
				WHERE varname = {varname} AND
					languageid IN(0,-1)
			"
		),
		/* Phrase API SQL End */

		/* Language API SQL Start */
		'language_count' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*) AS total FROM {TABLE_PREFIX}language
			"
		),

		/* Language API SQL End */

		/* Cron API SQL Start */
		'cron_fetchphrases' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT varname, text
				FROM {TABLE_PREFIX}phrase
				WHERE languageid = {languageid} AND
					fieldname = 'cron' AND
					varname IN ({title}, {desc}, {logphrase})
			"
		),
		'cron_fetchall' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT cron.*, IF(product.productid IS NULL OR product.active = 1, cron.active, 0) AS effective_active
				FROM {TABLE_PREFIX}cron AS cron
				LEFT JOIN {TABLE_PREFIX}product AS product ON (cron.product = product.productid)
				ORDER BY effective_active DESC, nextrun
			"
		),
		'cron_insertphrases' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				REPLACE INTO {TABLE_PREFIX}phrase
					(languageid, fieldname, varname, text, product, username, dateline, version)
				VALUES
					(
						{new_languageid},
						'cron',
						CONCAT('task_', {varname}, '_title'),
						{title},
						{product},
						{username},
						{timenow},
						{product_version}
					),
					(
						{new_languageid},
						'cron',
						CONCAT('task_', {varname}, '_desc'),
						{description},
						{product},
						{username},
						{timenow},
						{product_version}
					),
					(
						{new_languageid},
						'cron',
						CONCAT('task_', {varname}, '_log'),
						{logphrase},
						{product},
						{username},
						{timenow},
						{product_version}
					)
			"
		),
		'cron_fetchswitch' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT cron.*,
					IF(product.productid IS NULL OR product.active = 1, 1, 0) AS product_active,
					product.title AS product_title
				FROM {TABLE_PREFIX}cron AS cron
				LEFT JOIN {TABLE_PREFIX}product AS product ON (cron.product = product.productid)
				WHERE cronid = {cronid}
			"
		),
		'cron_switchactive' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}cron SET
					active = IF(active = 1, 0, 1)
				WHERE cronid = {cronid}
			"
		),
		'cron_fetchnext' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT MIN(nextrun) AS nextrun
				FROM {TABLE_PREFIX}cron AS cron
					LEFT JOIN {TABLE_PREFIX}product AS product ON (cron.product = product.productid)
					WHERE cron.active = 1
					AND (product.productid IS NULL OR product.active = 1)
			"
		),
		/* Cron API SQL End */

		/* Video API SQL Start */
		'video_fetchproviders' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT
					provider, url, regex_url, regex_scrape, tagoption
				FROM {TABLE_PREFIX}bbcode_video
				ORDER BY priority
			"
		),
		/* Video API SQL End */

		'fetch_page_pagetemplate_screenlayout' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => '
				SELECT page.*, pagetemplate.screenlayoutid, screenlayout.template AS screenlayouttemplate, pagetemplate.title as templatetitle
				FROM {TABLE_PREFIX}page AS page
				LEFT JOIN {TABLE_PREFIX}pagetemplate AS pagetemplate ON(page.pagetemplateid = pagetemplate.pagetemplateid)
				LEFT JOIN {TABLE_PREFIX}screenlayout AS screenlayout ON(pagetemplate.screenlayoutid = screenlayout.screenlayoutid)
				WHERE page.pageid = {pageid}
			'
		),
		'fetch_homepage_route' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => '
				SELECT routenew.*
				FROM {TABLE_PREFIX}routenew AS routenew
				WHERE routenew.regex = \'\'
			'
		),

		'update_route_301'	=> array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => '
				UPDATE {TABLE_PREFIX}routenew
				SET redirect301 = {newrouteid}, name = \'\'
				WHERE redirect301 = {oldrouteid} OR routeid = {oldrouteid}
			'
		),

		'getChannelRoutes'	=> array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => '
				SELECT r.*
				FROM {TABLE_PREFIX}routenew r
				WHERE r.contentid IN ({channelids})
				AND class IN (\'vB5_Route_Channel\',\'vB5_Route_Conversation\')
			'
		),

		'getPageWidgets'	=> array(
			vB_dB_Query::QUERYTYPE_KEY	=> vB_dB_Query::QUERY_SELECT,
			'query_string'	=> '
				SELECT w.*
				FROM {TABLE_PREFIX}page p
				INNER JOIN {TABLE_PREFIX}pagetemplate t ON p.pagetemplateid = t.pagetemplateid
				INNER JOIN {TABLE_PREFIX}widgetinstance w ON t.pagetemplateid = w.pagetemplateid
				WHERE p.pageid={pageid}
			'
		),

		'getPageWidgetsByType'	=> array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string'	=> '
				SELECT w.*
				FROM {TABLE_PREFIX}page p
				INNER JOIN {TABLE_PREFIX}pagetemplate t ON p.pagetemplateid = t.pagetemplateid
				INNER JOIN {TABLE_PREFIX}widgetinstance w ON t.pagetemplateid = w.pagetemplateid
				WHERE p.pageid={pageid} AND w.widgetid IN ({widgetids})
			'
		),

		'getPageInfoExport' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => '
				SELECT p.*, t.guid as pageTemplateGuid, r.guid as routeGuid, p2.guid as parentGuid
				FROM {TABLE_PREFIX}page p
				LEFT JOIN {TABLE_PREFIX}page p2 ON p.parentid = p2.pageid
				INNER JOIN {TABLE_PREFIX}pagetemplate t ON p.pagetemplateid = t.pagetemplateid
				INNER JOIN {TABLE_PREFIX}routenew r ON p.routeid = r.routeid
			'
		),

		'fetchPageList' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT page.* FROM {TABLE_PREFIX}page as page, {TABLE_PREFIX}routenew as routenew
				WHERE routenew.routeid = page.routeid
				AND routenew.prefix = routenew.regex"
		),

		'getUsernameAndId' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT userid, username FROM {TABLE_PREFIX}user
					WHERE userid != {userid}
					AND (username = {username}	OR username = {username_raw})"
		),
		'getColumnUsername' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SHOW COLUMNS FROM {TABLE_PREFIX}user LIKE {field}"
		),
		'delPasswordHistory' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'query_string' => "DELETE FROM {TABLE_PREFIX}passwordhistory
			WHERE userid = {userid}
			AND passworddate <= FROM_UNIXTIME({passworddate}"
		),
		'getHistoryCheck' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT UNIX_TIMESTAMP(passworddate) AS passworddate
			FROM {TABLE_PREFIX}passwordhistory
			WHERE userid = {userid}
			AND password = {password}"
		),
		'getInfractiongroups' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT orusergroupid, override
					FROM {TABLE_PREFIX}infractiongroup AS infractiongroup
					WHERE infractiongroup.usergroupid IN (-1, {usergroupid})
						AND infractiongroup.pointlevel <= {pointlevel}
					ORDER BY pointlevel"
		),
		'updFriendReqCount' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'query_string' => "UPDATE {TABLE_PREFIX}user
					SET friendreqcount = IF(friendreqcount > 0, friendreqcount - 1, 0)
					WHERE userid IN ({userid})"
		),
		'updFriendCount' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'query_string' => "UPDATE {TABLE_PREFIX}user
					SET friendcount = IF(friendcount > 0, friendcount - 1, 0)
					WHERE userid IN ({userid})"
		),
		'delUserList' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'query_string' => "DELETE FROM {TABLE_PREFIX}userlist
					WHERE userid = {userid} OR relationid = {relationid}"
		),
		'getGroupMemberships' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT socialgroup.*
					FROM {TABLE_PREFIX}socialgroupmember AS socialgroupmember
					INNER JOIN {TABLE_PREFIX}socialgroup AS socialgroup ON (socialgroup.groupid = socialgroupmember.groupid)
					WHERE socialgroupmember.userid = {userid}"
		),
		'updPmText' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'query_string' => "UPDATE {TABLE_PREFIX}pmtext SET
						touserarray = REPLACE(touserarray,
							'i:{userid};s:{exusrstrlen}:\"{exusername}\";',
							'i:{userid};s:{usrstrlen}:\"{username}\";'
						)
					WHERE touserarray LIKE '%i:{userid};s:{usrstrlen}:\"{username}\";%'"
		),
		'getSubscribedThreads' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT subscribethread.canview, subscribethreadid, thread.forumid
				FROM {TABLE_PREFIX}subscribethread AS subscribethread
				INNER JOIN {TABLE_PREFIX}thread AS thread ON (thread.threadid = subscribethread.threadid)
				WHERE subscribethread.userid = {userid}
					AND thread.forumid IN ({forumid})"
		),
		'updRemoveSubscribedThreads' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'query_string' => "UPDATE {TABLE_PREFIX}subscribethread
					SET canview =
					CASE
						WHEN subscribethreadid IN ({subscribethreadid}) THEN 0
					ELSE canview
					END
					WHERE userid = {userid}
					AND subscribethreadid IN ({subscribethreadid})"
		),
		'updAddSubscribedThreads' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'query_string' => "UPDATE {TABLE_PREFIX}subscribethread
					SET canview =
					CASE
						WHEN subscribethreadid IN ({subscribethreadid}) THEN 0
					ELSE canview
					END
					WHERE userid = {userid}
					AND subscribethreadid IN ({subscribethreadid})"
		),
		'insPasswordHistory' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'query_string' => "INSERT INTO {TABLE_PREFIX}passwordhistory (userid, password, passworddate)
				VALUES ({userid}, {password}, FROM_UNIXTIME({passworddate}))"
		),
		'countOtherAdmins' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT COUNT(*) AS users
					FROM {TABLE_PREFIX}user
					WHERE userid <> {userid}
					AND usergroupid IN({usergroupid})"
		),
		'countOtherAdminsGroups' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT COUNT(*) AS users
					FROM {TABLE_PREFIX}user
					WHERE userid <> {userid}
					AND
					(
						usergroupid IN({usergroupid}) OR
						FIND_IN_SET({groupids}, membergroupids)
					)"
		),
		'replaceUserCssCache' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'query_string' => "REPLACE INTO {TABLE_PREFIX}usercsscache
					(userid, cachedcss, buildpermissions)
					VALUES
					({userid}, {cachedcss}, {buildpermissions})"
		),
		'getUserPictures' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT album.userid
					FROM {TABLE_PREFIX}attachment AS a
					INNER JOIN {TABLE_PREFIX}album AS album ON (a.contentid = album.albumid)
					WHERE a.attachmentid = {attachmentid AND
					a.contenttypeid = {contenttypeid} AND
					album.state IN ({state}) AND
					album.userid = {userid} AND
					album.albumid = {albumid}"
		),
		'fetch_page_template_list' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => '
				SELECT pagetemplate.*
				FROM {TABLE_PREFIX}pagetemplate AS pagetemplate
				WHERE pagetemplate.title <> \'\'
			',
		),
		'updateFiledataRefCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => '
				UPDATE {TABLE_PREFIX}filedata SET refcount = refcount + {countChange}
				WHERE filedataid = {filedataid}',
		),
		'phrase_fetchorphans' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT orphan.varname, orphan.languageid, orphan.fieldname
				FROM {TABLE_PREFIX}phrase AS orphan
				LEFT JOIN {TABLE_PREFIX}phrase AS phrase ON (phrase.languageid IN(-1, 0) AND phrase.varname = orphan.varname AND phrase.fieldname = orphan.fieldname)
				WHERE orphan.languageid NOT IN (-1, 0)
					AND phrase.phraseid IS NULL
				ORDER BY orphan.varname
			",
		),
		'getAttachments' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT ba.*, bt.pagetext, bt.blogtextid
					FROM {TABLE_PREFIX}blog_attachment AS ba
					LEFT JOIN {TABLE_PREFIX}blog AS blog ON (ba.blogid = blog.blogid)
					LEFT JOIN {TABLE_PREFIX}blog_text AS bt ON (blog.firstblogtextid = bt.blogtextid)
					WHERE
					ba.attachmentid >= {attachmentid}
					ORDER BY ba.attachmentid
					LIMIT {#limit}",
		),
		'replaceBlogAttachment' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "REPLACE INTO {TABLE_PREFIX}blog_attachmentlegacy
					(oldattachmentid, newattachmentid)
				VALUES
					({oldattachmentid}, {newattachmentid})",
		),
		'getAttachmentId' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT attachmentid FROM {TABLE_PREFIX}blog_attachment WHERE attachmentid >= {attachmentid} LIMIT 1",
		),
		'dropBlogAttachment' => array(
				vB_dB_Query::QUERYTYPE_KEY => 'dr',
				'query_string' => "DROP TABLE IF EXISTS {TABLE_PREFIX}blog_attachment",
		),
		'dropBlogAttachmentViews' => array(
				vB_dB_Query::QUERYTYPE_KEY => 'dr',
				'query_string' => "DROP TABLE IF EXISTS {TABLE_PREFIX}blog_attachmentviews",
		),
		'blogAdminUsers' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT userid
					FROM {TABLE_PREFIX}user AS user
					INNER JOIN {TABLE_PREFIX}blog_user AS blog_user ON (user.userid = blog_user.bloguserid)
					WHERE userid >= {userid}
					ORDER BY userid
					LIMIT {#limit}",
		),
		'getSuperGroups' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT user.*, usergroup.usergroupid
					FROM {TABLE_PREFIX}usergroup AS usergroup
					INNER JOIN {TABLE_PREFIX}user AS user ON(user.usergroupid = usergroup.usergroupid OR FIND_IN_SET(usergroup.usergroupid, user.membergroupids))
					WHERE (usergroup.adminpermissions & {ismoderator})
					GROUP BY user.userid
					ORDER BY user.username",
		),
		'getBlogAdminModerators' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT blog_moderator.blogmoderatorid, user.userid, user.username, user.lastactivity
					FROM {TABLE_PREFIX}blog_moderator AS blog_moderator
					INNER JOIN {TABLE_PREFIX}user AS user ON (user.userid = blog_moderator.userid)
					WHERE blog_moderator.type = 'normal'
					ORDER BY user.username",
		),
		'getBlogAdminModerator' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT user.username, user.userid,
					bm.permissions, bm.blogmoderatorid
					FROM {TABLE_PREFIX}user AS user
					LEFT JOIN {TABLE_PREFIX}blog_moderator AS bm ON (bm.userid = user.userid AND bm.type = 'super')
					WHERE user.userid = {userid}",
		),
		'getBlogModerator' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT blogmoderatorid, bm.userid, permissions, user.username, bm.type
					FROM {TABLE_PREFIX}blog_moderator AS bm
					LEFT JOIN {TABLE_PREFIX}user AS user ON (user.userid = bm.userid)
					WHERE blogmoderatorid = {blogmoderatorid}",
		),
		'getBlogAdminUserinfo' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT user.userid, bloguserid, blog_moderator.userid AS bmuserid
					FROM {TABLE_PREFIX}user AS user
					LEFT JOIN {TABLE_PREFIX}blog_user AS blog_user ON (user.userid = blog_user.bloguserid)
					LEFT JOIN {TABLE_PREFIX}blog_moderator AS blog_moderator ON (user.userid = blog_moderator.userid AND type = {mod_type})
					WHERE username = {username}",
		),
		'getBlogAdminUserid' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT user.userid, usergroupid
					FROM {TABLE_PREFIX}blog_moderator AS blog_moderator
					LEFT JOIN {TABLE_PREFIX}user AS user USING (userid)
					WHERE blogmoderatorid = {blogmoderatorid}",
		),
		'getBlogAdminCustomBlocks' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT customblockid, title, type, location, displayorder
					FROM {TABLE_PREFIX}blog_custom_block
					WHERE
					userid = {userid}
					AND
					type = {blocktype}
					ORDER BY displayorder",
		),
		'truncateBlogTextparsed' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "TRUNCATE TABLE {TABLE_PREFIX}blog_textparsed"
		),
		'updateBlogTrackbackCountVisible' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'query_string' => "UPDATE {TABLE_PREFIX}blog AS blog, {TABLE_PREFIX}{tablename} AS blog_trackback_count
					SET blog.trackback_visible = blog_trackback_count.btotal
					WHERE blog.blogid = blog_trackback_count.bid AND blog_trackback_count.bstate = 'visible'"
		),
		'updateBlogTrackbackCountModeration' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'query_string' => "UPDATE {TABLE_PREFIX}blog AS blog, {TABLE_PREFIX}{tablename} AS blog_trackback_count
					SET blog.trackback_moderation = blog_trackback_count.btotal
					WHERE blog.blogid = blog_trackback_count.bid AND blog_trackback_count.bstate = 'moderation'"
		),
		'getFirstCustomProfilePic' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT MIN(userid) AS min FROM {TABLE_PREFIX}customprofilepic WHERE width = 0 OR height = 0"
		),
		'getUserCustomProfilePics' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT cpp.userid, cpp.filedata, u.profilepicrevision, u.username
					FROM {TABLE_PREFIX}customprofilepic AS cpp
					LEFT JOIN {TABLE_PREFIX}user AS u USING (userid)
					WHERE cpp.userid >= {userid}
					AND (cpp.width = 0 OR cpp.height = 0)
					ORDER BY cpp.userid
					LIMIT {#limit}"
		),
		'getFeaturedBlogEntries' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT fe.*, user.username
					FROM {TABLE_PREFIX}blog_featured AS fe
					LEFT JOIN {TABLE_PREFIX}user AS user USING (userid)
					ORDER BY fe.displayorder"
		),
		'getFeaturedBlogEntryById' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT fe.*, user.username
					FROM {TABLE_PREFIX}blog_featured AS fe
					LEFT JOIN {TABLE_PREFIX}user AS user USING (userid)
					WHERE featureid = {featureid}"
		),
		'getCategoryTitleDesc' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT blog_category.*, phrase1.text AS title, phrase2.text AS description
					FROM {TABLE_PREFIX}blog_category AS blog_category
					LEFT JOIN {TABLE_PREFIX}phrase AS phrase1 ON (phrase1.varname = {title} AND phrase1.fieldname = 'vbblogcat' AND phrase1.languageid = 0)
					LEFT JOIN {TABLE_PREFIX}phrase AS phrase2 ON (phrase2.varname = '{desc}' AND phrase2.fieldname = 'vbblogcat' AND phrase2.languageid = 0)
					WHERE blog_category.blogcategoryid = {blogcategoryid}"
		),
		'getCategoryPermissions' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT bcp.usergroupid, bc.blogcategoryid, bcp.categorypermissions, bcp.categorypermissionid,
					NOT (ISNULL(bcp.blogcategoryid)) AS hasdata, bcp.blogcategoryid
					FROM {TABLE_PREFIX}blog_category AS bc
					LEFT JOIN {TABLE_PREFIX}blog_categorypermission AS bcp ON (bcp.blogcategoryid = bc.blogcategoryid)"
		),
		'getCategoryPermissionsById' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT bcp.*, usergroup.title AS grouptitle, phrase.text AS title
					FROM {TABLE_PREFIX}blog_categorypermission AS bcp
					INNER JOIN {TABLE_PREFIX}blog_category AS bc ON (bc.blogcategoryid = bcp.blogcategoryid)
					LEFT JOIN {TABLE_PREFIX}phrase AS phrase ON (phrase.varname = CONCAT(CONCAT('category', bc.blogcategoryid), '_title') AND phrase.fieldname = 'vbblogcat' AND phrase.languageid = 0)
					INNER JOIN {TABLE_PREFIX}usergroup AS usergroup ON (usergroup.usergroupid = bcp.usergroupid)
					WHERE bcp.categorypermissionid = {categorypermissionid}"
		),
		'getCategoryTitle' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT text AS title
					FROM {TABLE_PREFIX}phrase
					WHERE varname = CONCAT(CONCAT('category', {category}), '_title') AND fieldname = 'vbblogcat' AND languageid = 0"
		),
		'replaceBlogGroupMembership' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'query_string' => "REPLACE INTO {TABLE_PREFIX}blog_groupmembership
					(bloguserid, userid, state, dateline)
					VALUES
					({bloguserid}, {userid}, {state}, {dateline})",
		),
		'getInviteInfo' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT user.*, gm.state, bu.bloguserid,
					IF (bu.title <> '', bu.title, user.username) AS title
					FROM {TABLE_PREFIX}blog_groupmembership AS gm
					LEFT JOIN {TABLE_PREFIX}blog_user AS bu ON (bu.bloguserid = gm.bloguserid)
					LEFT JOIN {TABLE_PREFIX}user AS user ON (user.userid = gm.bloguserid)
					WHERE
					gm.bloguserid = {bloguserid}
					AND
					gm.userid = {userid}
					AND
					gm.state <> 'ignored'",
		),
		'getBlogsUserGroupMembership' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT user.*, gm.bloguserid, gm.dateline, gm.state, IF (bu.title <> '', bu.title, user.username) AS title
					FROM {TABLE_PREFIX}blog_groupmembership AS gm
					LEFT JOIN {TABLE_PREFIX}blog_user AS bu ON (bu.bloguserid = gm.bloguserid)
					LEFT JOIN {TABLE_PREFIX}user AS user ON (user.userid = gm.bloguserid)
					WHERE gm.userid = {userid} AND state IN ('active', 'pending')
					ORDER BY state",
		),
		'getProfileAlbums' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT
					album.title, album.albumid,
					a.dateline, a.attachmentid, a.caption,
					fd.filesize, fdr.resize_filesize, fdr.resize_dateline, fdr.resize_width, fdr.resize_height, IF(fdr.resize_filesize > 0, 1, 0) AS hasthumbnail
					FROM {TABLE_PREFIX}album AS album
					INNER JOIN {TABLE_PREFIX}attachment AS a ON (a.contentid = album.albumid)
					INNER JOIN {TABLE_PREFIX}filedata AS fd ON (fd.filedataid = a.filedataid)
					LEFT JOIN {TABLE_PREFIX}filedataresize AS fdr ON (fd.filedataid = fdr.filedataid AND fdr.type = 'thumb')
					WHERE
					album.state = 'profile'
					AND
					album.userid = {userid}
					AND
					a.state = 'visible'
					AND
					a.contenttypeid = {contenttypeid}
					ORDER BY
					album.albumid, a.attachmentid",
		),
		'sumModeratedMembers' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT SUM(moderatedmembers) FROM {TABLE_PREFIX}socialgroup
			WHERE creatoruserid = {creatoruserid}",
		),
		'listInvitedGroups' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT COUNT(*) FROM {TABLE_PREFIX}socialgroupmember WHERE
			userid = {userid}	AND TYPE = {invited}",
		),
		'getForumAds' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT ad.*
					FROM {TABLE_PREFIX}ad AS ad
					LEFT JOIN {TABLE_PREFIX}adcriteria AS adcriteria ON(adcriteria.adid = ad.adid)
					WHERE (adcriteria.criteriaid = 'browsing_forum_x' OR adcriteria.criteriaid = 'browsing_forum_x_and_children')",
		),
		'replaceForumPodcast' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'query_string' => "REPLACE INTO {TABLE_PREFIX}podcast
					(nodeid, enabled, categoryid, category, author, image, explicit, keywords, owneremail, ownername, subtitle, summary)
					VALUES ({nodeid}, {enabled}, {categoryid}, {category}, {author}, {image}, {explicit}, {keywords}, {owneremail}, {ownername}, {subtitle}, {summary})",
		),
		'getRealTitle' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT *, title AS realtitle
					FROM {TABLE_PREFIX}blog_category
					WHERE blogcategoryid = {blogcategoryid}",
		),
		'fetchSmilies' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT *, LENGTH(smilietext) AS smilielen
				FROM {TABLE_PREFIX}smilie
				ORDER BY smilielen DESC
			",
		),

		'getCounts' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT
					SUM(IF(a.state = 'visible', 1, 0)) AS visible,
					SUM(IF(a.state = 'moderation', 1, 0)) AS moderation,
					MAX(IF(a.state = 'visible', a.dateline, 0)) AS lastpicturedate
					FROM {TABLE_PREFIX}attachment AS a
					WHERE
					a.contentid = {contentid}
					AND
					a.contenttypeid = {contenttypeid}"
		),
		'getSumModerationDiscussion' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT SUM(moderation) FROM {TABLE_PREFIX}socialgroup
					WHERE creatoruserid = {ownerid}"
		),
		'delMessagesModerationAndLogs' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'query_string' => "DELETE {TABLE_PREFIX}groupmessage, {TABLE_PREFIX}deletionlog, {TABLE_PREFIX}moderation
				FROM {TABLE_PREFIX}groupmessage
				LEFT JOIN {TABLE_PREFIX}deletionlog
				ON {TABLE_PREFIX}deletionlog.primaryid = {TABLE_PREFIX}groupmessage.gmid
				AND {TABLE_PREFIX}deletionlog.type = 'groupmessage'
				LEFT JOIN {TABLE_PREFIX}moderation
				ON {TABLE_PREFIX}moderation.primaryid = {TABLE_PREFIX}groupmessage.gmid
				AND {TABLE_PREFIX}moderation.type = 'groupmessage'
				WHERE {TABLE_PREFIX}groupmessage.discussionid = {discussionid}"
		),
		'updPollvoteByPollId' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'query_string' => "UPDATE {TABLE_PREFIX}pollvote SET
						voteoption = voteoption - 1,
						votetype = IF(votetype = 0, 0, votetype - 1)
					WHERE pollid = {pollid}
						AND voteoption > {deloption}
					ORDER BY voteoption"
		),
		'fetchUncachablePhrase' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT text, languageid
				FROM {TABLE_PREFIX}phrase AS phrase
				INNER JOIN {TABLE_PREFIX}phrasetype USING(fieldname)
				WHERE phrase.fieldname = {phrasegroup}
				AND varname = {phrasekey} AND languageid IN (-1, 0, {languageid})"
		),
		'perminfoquery' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT node.nodeid, node.htmltitle AS nodetitle,usergroup.title AS grouptitle
					FROM {TABLE_PREFIX}permission AS permission
					INNER JOIN {TABLE_PREFIX}node AS node ON (node.nodeid = permission.nodeid)
					INNER JOIN {TABLE_PREFIX}usergroup AS usergroup ON (usergroup.usergroupid = permission.groupid)
					WHERE permissionid = {permissionid}
			"
		),
		'fetchpermgroups' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT usergroup.usergroupid, title, COUNT(permission.permissionid) AS permcount
					FROM {TABLE_PREFIX}usergroup AS usergroup
					LEFT JOIN {TABLE_PREFIX}permission AS permission ON (usergroup.usergroupid = permission.groupid)
					GROUP BY usergroup.usergroupid
					HAVING permcount > 0
					ORDER BY title
			"
		),
		'fetchinherit' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT groupid, closure.parent as nodeid, IF(permission.nodeid = closure.parent, 0, 1) AS inherited
					FROM {TABLE_PREFIX}permission AS permission
					INNER JOIN {TABLE_PREFIX}closure AS closure ON (closure.child = permission.nodeid)
			"
		),
		'replacePermissions' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				REPLACE INTO {TABLE_PREFIX}permission
					(nodeid, groupid, forumpermissions, moderatorpermissions, createpermissions, edit_time, require_moderate, maxtags, maxstartertags, maxothertags, maxattachments)
				VALUES
					({nodeid}, {usergroupid}, '{forumpermissions}', '{moderatorpermissions}', '{createpermissions}', '{edit_time}', '{require_moderate}', '{maxtags}', '{maxstartertags}', '{maxothertags}', '{maxattachments}')
			"
		),
		'fetchperms' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT permissionid, usergroup.title AS ug_title, node.htmltitle AS node_title, IF({order_first} = 'usergroup', CONCAT(usergroup.title, node.htmltitle), CONCAT(node.htmltitle, usergroup.title)) as sortfield
					FROM {TABLE_PREFIX}permission AS permission
					INNER JOIN {TABLE_PREFIX}usergroup AS usergroup ON (usergroup.usergroupid = permission.groupid)
					INNER JOIN {TABLE_PREFIX}node AS node ON (node.nodeid = permission.nodeid)
					GROUP BY usergroup.usergroupid
					ORDER BY sortfield
			"
		),
		'fetchExistingPermsForGroup' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT permission.nodeid
				FROM {TABLE_PREFIX}permission AS permission
				INNER JOIN {TABLE_PREFIX}node AS node ON (node.nodeid = permission.nodeid)
				WHERE permission.groupid = {groupid}
			"
		),
		'fetchExistingPermsForGroupLimit' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT permission.nodeid
				FROM {TABLE_PREFIX}permission AS permission
				INNER JOIN {TABLE_PREFIX}node AS node ON (node.nodeid = permission.nodeid)
				INNER JOIN {TABLE_PREFIX}closure AS closure ON (closure.child = permission.nodeid)
				WHERE permission.groupid = {groupid}
					AND closure.parent = {parentid}
			"
		),
		'accesscount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*) AS count,nodeid,accessmask FROM {TABLE_PREFIX}access GROUP BY nodeid,accessmask
			"
		),
		'accessUserCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*) AS masks FROM {TABLE_PREFIX}access WHERE userid = {userid}
			"
		),
		'fetchUserAccessMask' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT user.username, user.userid, node.nodeid, node.htmltitle AS node_title, accessmask, IF({order_first} = 'channel', CONCAT(username, node.htmltitle), CONCAT(node.htmltitle, username)) as sortfield
					FROM {TABLE_PREFIX}access AS access,
						{TABLE_PREFIX}user AS user,
						{TABLE_PREFIX}node AS node
					WHERE access.userid = user.userid AND
						access.nodeid = node.nodeid
					ORDER BY sortfield
			"
		),
		'fetchAccessMaskForUser' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT access.*, node.nodeid, closure.depth as ordercontrol
					FROM {TABLE_PREFIX}node AS node
					INNER JOIN {TABLE_PREFIX}closure AS closure ON (node.nodeid = closure.parent)
					INNER JOIN {TABLE_PREFIX}access AS access ON (access.userid = {userid} AND access.nodeid = closure.child)
					ORDER BY closure.depth DESC
			"
		),
		'maskDelete' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'query_string' => "UPDATE {TABLE_PREFIX}user
					SET options = (options - {hasaccessmask})
					WHERE userid IN ({maskdelete}) AND (options & {hasaccessmask})"
		),
		'maskAdd' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'query_string' => "UPDATE {TABLE_PREFIX}user
					SET options = (options - {hasaccessmask})
					WHERE userid IN ({updateuserids}) AND NOT (options & {hasaccessmask})"
		),
		'insertAccess' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				INSERT IGNORE INTO {TABLE_PREFIX}access
					(userid, nodeid, accessmask)
				VALUES
					({userid}, {nodeid}, '{accessmask}')
			"
		),
		'fetchAccessMaskForUser' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT user.*, COUNT(*) AS masks
				FROM {TABLE_PREFIX}access AS access, {TABLE_PREFIX}user AS user
				WHERE access.userid = {userid}
					AND user.userid = access.userid
				GROUP BY access.userid
			"
		),
		'fetchTemplateWithStyle' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT template.*, style.title AS style, IF(template.styleid = 0, -1, template.styleid) AS styleid, MD5(template.template_un) AS hash
				FROM {TABLE_PREFIX}template AS template
				LEFT JOIN {TABLE_PREFIX}style AS style USING(styleid)
				WHERE templateid = {templateid}
			"
		),
		'replaceIntoPhrases' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				REPLACE INTO {TABLE_PREFIX}phrase (languageid, fieldname, varname, text, product, username, dateline, version)
				VALUES ({languageid}, {fieldname}, {varname}, {text}, {product}, {enteredBy}, {dateline}, {version})
			"
		),
		'fetchInfractionsByUser' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT infraction.*, user.username AS whoadded_username, user2.username
				FROM {TABLE_PREFIX}infraction AS infraction
				LEFT JOIN {TABLE_PREFIX}user AS user ON (infraction.whoadded = user.userid)
				LEFT JOIN {TABLE_PREFIX}user AS user2 ON (infraction.userid = user2.userid)
				WHERE infractionid = {infractionid}
			"
		),
		'fetchInfractionsByUser2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT infraction.*, user.username AS whoadded_username, user2.username, user3.username AS action_username
				FROM {TABLE_PREFIX}infraction AS infraction
				LEFT JOIN {TABLE_PREFIX}user AS user ON (infraction.whoadded = user.userid)
				LEFT JOIN {TABLE_PREFIX}user AS user2 ON (infraction.userid = user2.userid)
				LEFT JOIN {TABLE_PREFIX}user AS user3 ON (infraction.actionuserid = user3.userid)
				WHERE infractionid = {infractionid}
			"
		),
		'fetchCountInfractionsByInfractionLvl' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*) AS count, infractionlevelid
				FROM {TABLE_PREFIX}infraction
				GROUP BY infractionlevelid
				ORDER BY count DESC
			"
		),
		/* Class BBCode */
		'fetchSmilies' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT *, LENGTH(smilietext) AS smilielen
				FROM {TABLE_PREFIX}smilie
				ORDER BY smilielen DESC
			"
		),

		/* Human Verify Question */
		'hv_question_fetch_answer' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT hvquestion.questionid, COUNT(*) AS answers
				FROM {TABLE_PREFIX}hvquestion AS hvquestion
				LEFT JOIN {TABLE_PREFIX}hvanswer AS hvanswer
					ON (hvquestion.questionid = hvanswer.questionid)
				WHERE hvanswer.answerid IS NOT NULL
					OR hvquestion.regex <> ''
				GROUP BY hvquestion.questionid
				ORDER BY RAND()
				LIMIT 1
			"
		),

		'hv_question_fetch' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT question.questionid, question.regex
				FROM {TABLE_PREFIX}humanverify AS hv
				LEFT JOIN {TABLE_PREFIX}hvquestion AS question ON (hv.answer = question.questionid)
				WHERE hash = {hash}
					AND viewed = 1
			"
		),

		//PM Recipients
		'fetchPmRecipients' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT usertextfield.*, user.*, userlist.type
				FROM {TABLE_PREFIX}user AS user
				LEFT JOIN {TABLE_PREFIX}usertextfield AS usertextfield ON(usertextfield.userid=user.userid)
				LEFT JOIN {TABLE_PREFIX}userlist AS userlist ON(user.userid = userlist.userid AND userlist.relationid = {userid} AND userlist.type = 'buddy')
				WHERE user.username IN({usernames})
			"
		),
		'chooseModLog' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT DISTINCT moderatorlog.userid, user.username
				FROM {TABLE_PREFIX}moderatorlog AS moderatorlog
				INNER JOIN {TABLE_PREFIX}user AS user USING(userid)
				ORDER BY username
			"
		),

		/* Admincp API Log */
		'api_fetchclientnames' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT DISTINCT clientname
				FROM {TABLE_PREFIX}apiclient AS apiclient
				ORDER BY clientname
			"
		),
		'api_fetchclientusers' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT DISTINCT apiclient.userid, user.username
				FROM {TABLE_PREFIX}apiclient AS apiclient
				LEFT JOIN {TABLE_PREFIX}user AS user USING(userid)
				ORDER BY username
			"
		),
		'api_fetchclientbyid' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT apiclient.*, user.username FROM {TABLE_PREFIX}apiclient AS apiclient
				LEFT JOIN {TABLE_PREFIX}user AS user using(userid)
				WHERE apiclientid = {apiclientid}
			"
		),

		/* Admincp API Stats */
		'api_fetchmaxclient' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT apilog.apiclientid, apiclient.clientname, COUNT(*) as c
				FROM {TABLE_PREFIX}apilog AS apilog
				LEFT JOIN {TABLE_PREFIX}apiclient AS apiclient using(apiclientid)
				GROUP BY apilog.apiclientid
				ORDER BY c DESC
			"
		),
		'api_fetchmaxmethod' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT apilog.method, COUNT(*) as c
				FROM {TABLE_PREFIX}apilog AS apilog
				GROUP BY apilog.method
				ORDER BY c DESC
			"
		),
		'api_methodcount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*) AS total FROM (
					SELECT method, COUNT(*) FROM {TABLE_PREFIX}apilog AS apilog
					GROUP BY method
				) AS t
			"
		),
		'api_methodlogs' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT method, COUNT(*) AS c
				FROM {TABLE_PREFIX}apilog AS apilog
				GROUP BY method
				ORDER BY c DESC
				LIMIT {startat}, {#limit}
			"
		),
		'api_clientcount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*) AS total FROM (
					SELECT apiclientid, COUNT(*)
					FROM {TABLE_PREFIX}apilog AS apilog
					GROUP BY apiclientid
				) AS t
			"
		),
		'api_clientlogs' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT apilog.apiclientid, apiclient.userid, apiclient.clientname, user.username, COUNT(*) as c
				FROM {TABLE_PREFIX}apilog AS apilog
				LEFT JOIN {TABLE_PREFIX}apiclient AS apiclient ON (apiclient.apiclientid = apilog.apiclientid)
				LEFT JOIN {TABLE_PREFIX}user AS user ON (apiclient.userid = user.userid)
				GROUP BY apilog.apiclientid
				ORDER BY c DESC
				LIMIT {startat}, {#limit}
			"
		),
		'updt_style_parentlist' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'query_string' => "
					UPDATE {TABLE_PREFIX}style SET
						parentid = -1,
						parentlist = CONCAT(styleid,',-1')
					WHERE parentid = 0
			"
		),
		'api_clientlogs' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT user.userid, user.usergroupid, username, email, activationid, user.languageid
				FROM {TABLE_PREFIX}user AS user
				LEFT JOIN {TABLE_PREFIX}useractivation AS useractivation ON (user.userid=useractivation.userid AND type = 0)
				WHERE user.usergroupid = 3
					AND ((joindate >= {time1} AND joindate <= {time2}) OR (joindate >= {time3} AND joindate <= {time4}))
					AND NOT (user.options & {noactivationmails})

			"
		),
		'fetchPhrasesDataToDisplay' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT phrase.*, language.title
				FROM {TABLE_PREFIXphrase AS phrase
				LEFT JOIN {TABLE_PREFIX}language AS language USING(languageid)
				WHERE phrase.phraseid IN({phraseids})"
		),
		'fetchPhrassesByLanguage' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT * FROM {TABLE_PREFIX}phrase
				WHERE varname = {varname} AND
				fieldname = {fieldname}
				ORDER BY languageid
				LIMIT 1"
		),
		'countUserGroups' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "
				SELECT COUNT('groupid') AS total FROM {TABLE_PREFIX}socialgroup
				WHERE creatoruserid = {userid}"
		),
		'fetchGroupmemberInfo' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "
				SELECT socialgroup.groupid AS sgroupid, sgmember.*
				FROM {TABLE_PREFIX}socialgroup AS socialgroup
				LEFT JOIN {TABLE_PREFIX}socialgroupmember AS sgmember
				ON sgmember.groupid = socialgroup.groupid
				AND sgmember.userid = {user}
				WHERE creatoruserid = {creator}"
		),
		'fetchProfileFields' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "
				SELECT * FROM {TABLE_PREFIX}profilefield AS profilefield
				LEFT JOIN {TABLE_PREFIX}profilefieldcategory AS profilefieldcategory ON
				(profilefield.profilefieldcategoryid = profilefieldcategory.profilefieldcategoryid)
				ORDER BY profilefield.form, profilefieldcategory.displayorder, profilefield.displayorder"
		),
		'fetchActiveSubscriptions' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "
				SELECT status, regdate, expirydate, subscriptionlogid, subscription.subscriptionid
				FROM {TABLE_PREFIX}subscriptionlog AS subscriptionlog
				INNER JOIN {TABLE_PREFIX}subscription AS subscription USING (subscriptionid)
				WHERE userid = {userid}
				ORDER BY status DESC, regdate"
		),

		'node_markread' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				REPLACE INTO {TABLE_PREFIX}noderead
					(nodeid, userid, readtime)
				VALUES
					({nodeid}, {userid}, {readtime})
			"
		),
		'node_checktopicread' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*) AS count
				FROM {TABLE_PREFIX}node AS node
				LEFT JOIN {TABLE_PREFIX}noderead AS noderead ON (noderead.nodeid = node.nodeid AND noderead.userid = {userid})
				WHERE node.parentid = {parentid}
					AND node.inlist = 1
					AND node.protected = 0
					AND IF(node.lastcontent >0, node.lastcontent, node.created) > {noderead}
					AND (noderead.nodeid IS NULL OR noderead.readtime < IF(node.lastcontent >0, node.lastcontent, node.created))
			"
		),
		'node_checktopicreadinchannels' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*) AS count
				FROM {TABLE_PREFIX}node AS node
				LEFT JOIN {TABLE_PREFIX}noderead AS topicread ON (topicread.nodeid = node.nodeid AND topicread.userid = {userid})
				LEFT JOIN {TABLE_PREFIX}noderead AS channelread ON (channelread.nodeid = node.parentid AND channelread.userid = {userid})
				WHERE node.parentid IN ({children})
					AND node.nodeid = node.starter
					AND node.inlist = 1
					AND node.protected = 0
					AND IF(node.lastcontent >0, node.lastcontent, node.created) > IF(topicread.readtime IS NULL, {cutoff}, topicread.readtime)
					AND IF(node.lastcontent >0, node.lastcontent, node.created) > IF(channelread.readtime IS NULL, {cutoff}, channelread.readtime)
					AND IF(node.lastcontent >0, node.lastcontent, node.created) > {cutoff}
			"
		),
		'mailqueue_updatecount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}datastore SET data = data + {counter} WHERE title = 'mailqueue'
			"
		),
		'mailqueue_updatecount2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}datastore SET
					data = {newmail},
					data = IF(data < 0, 0, data)
				WHERE title = 'mailqueue'
			"
		),
		'mailqueue_locktable' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				LOCK TABLES {TABLE_PREFIX}mailqueue WRITE
			"
		),
		'mailqueue_fetch' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT *
				FROM {TABLE_PREFIX}mailqueue
				ORDER BY mailqueueid
				LIMIT {limit}
			"
		),
		'unlock_tables' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UNLOCK TABLES
			"
		),

		/* AD API */
		'ad_replaceadtemplate' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				REPLACE INTO {TABLE_PREFIX}template SET
					styleid = {styleid},
					title = {title},
					template = {template},
					template_un = {template_un},
					templatetype = 'template',
					dateline = {timenow},
					username = {username},
					version = {templateversion},
					product = {product}
			"
		),
		/* needed for assert_cp_sessionhash() */
		'cpSessionUpdate' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE LOW_PRIORITY {TABLE_PREFIX}cpsession
				SET dateline = {timenow}
				WHERE userid = {userid}
					AND hash = {hash}
			"
		),
		// assertor in admincp/language.php [START]
		'getLanguagePhrases' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT phrase.varname, phrase.text, phrase.fieldname
				FROM {TABLE_PREFIX}phrase AS phrase
				LEFT JOIN {TABLE_PREFIX}phrasetype AS phrasetype USING (fieldname)
				WHERE languageid = -1 AND phrasetype.special = 0
				ORDER BY varname
			"
		),
		// assertor in admincp/language.php [END]

		'getNewThreads' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT
					user.userid, user.salt, user.username, user.email, user.languageid, user.usergroupid, user.membergroupids,
						user.timezoneoffset, IF(user.options & {dstonoff}, 1, 0) AS dstonoff,
						IF(user.options & {hasaccessmask}, 1, 0) AS hasaccessmask, user.autosubscribe,
					node.nodeid, node.htmltitle, node.publishdate, node.parentid, node.lastcontentid, node.lastcontent,node.userid AS authorid, node.authorname
					open, totalcount, lastcontentauthor, lastauthorid, subscribediscussionid,
						language.dateoverride AS lang_dateoverride, language.timeoverride AS lang_timeoverride, language.locale AS lang_locale
					FROM {TABLE_PREFIX}subscribediscussion AS subscribediscussion
					INNER JOIN {TABLE_PREFIX}node AS node ON (node.nodeid = subscribediscussion.discussionid)
					INNER JOIN {TABLE_PREFIX}user AS user ON (user.userid = subscribediscussion.userid)
					LEFT JOIN {TABLE_PREFIX}usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
					LEFT JOIN {TABLE_PREFIX}language AS language ON (language.languageid = IF(user.languageid = 0, {languageid}, user.languageid))
					WHERE
						node.lastcontent > {lastdate} AND
						node.showpublished = 1 AND
						user.usergroupid <> 3 AND
						(usergroup.genericoptions & {isnotbannedgroup})
			"
		),
		'getNewPosts' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT
					node.*, IFNULL(user.username,node.authorname) AS postusername,
					user.*
					FROM {TABLE_PREFIX}node AS node
					LEFT JOIN {TABLE_PREFIX}user AS user ON (user.userid = node.userid)
					INNER JOIN {TABLE_PREFIX}closure AS closure ON ( closure.child = node.nodeid )
					WHERE closure.parent = {threadid} AND closure.depth = 1 AND
						node.showpublished = 1 AND
						user.usergroupid <> 3 AND
						node.publishdate > {lastdate}
					ORDER BY node.publishdate
			"
		),
		'getNewForums' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT user.userid, user.salt, user.username, user.email, user.languageid, user.usergroupid, user.membergroupids,
					user.timezoneoffset, IF(user.options & {dstonoff}, 1, 0) AS dstonoff,
					IF(user.options & {hasaccessmask}, 1, 0) AS hasaccessmask,
					node.nodeid AS forumid, node.htmltitle AS title_clean, node.title,
					language.dateoverride AS lang_dateoverride, language.timeoverride AS lang_timeoverride, language.locale AS lang_locale
				FROM {TABLE_PREFIX}subscribediscussion AS subscribediscussion
				INNER JOIN {TABLE_PREFIX}node AS node ON (node.nodeid = subscribediscussion.discussionid AND node.contenttypeid = {channelcontenttype})

				INNER JOIN {TABLE_PREFIX}user AS user ON (user.userid = subscribediscussion.userid)
				LEFT JOIN {TABLE_PREFIX}usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
				LEFT JOIN {TABLE_PREFIX}language AS language ON (language.languageid = IF(user.languageid = 0, {languageid}, user.languageid))
				WHERE subscribediscussion.emailupdate = {type} AND
					node.lastcontent > {lastdate} AND
					user.usergroupid <> 3 AND
					(usergroup.genericoptions & {isnotbannedgroup})
				"
		),

		'getSubscriptionsReminders' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT subscriptionlog.subscriptionid, subscriptionlog.userid, subscriptionlog.expirydate, user.username, user.email, user.languageid
				FROM {TABLE_PREFIX}subscriptionlog AS subscriptionlog
				LEFT JOIN {TABLE_PREFIX}user AS user ON (user.userid = subscriptionlog.userid)
				WHERE subscriptionlog.expirydate >= {time1}
					AND subscriptionlog.expirydate <= {time2}
					AND subscriptionlog.status = 1
			"
		),
		'getBannedUsers' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT user.*,
					userban.usergroupid AS banusergroupid, userban.displaygroupid AS bandisplaygroupid, userban.customtitle AS bancustomtitle, userban.usertitle AS banusertitle
				FROM {TABLE_PREFIX}userban AS userban
				INNER JOIN {TABLE_PREFIX}user AS user USING(userid)
				WHERE liftdate <> 0 AND liftdate < {liftdate}
			"
		),
		'cleanupUA' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "
				DELETE FROM {TABLE_PREFIX}useractivation
				WHERE dateline < {time} AND
					(type = 1 OR (type = 0 and usergroupid = 2))
			"
		),
		'fetchEvents' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT event.eventid, event.title, recurring, recuroption, dateline_from, dateline_to, IF (dateline_to = 0, 1, 0) AS singleday,
					dateline_from AS dateline_from_user, dateline_to AS dateline_to_user, utc, dst, event.calendarid,
					subscribeevent.userid, subscribeevent.lastreminder, subscribeevent.subscribeeventid, subscribeevent.reminder,
					user.email, user.languageid, user.usergroupid, user.username, user.timezoneoffset, IF(user.options & 128, 1, 0) AS dstonoff,
					calendar.title AS calendar_title
				FROM {TABLE_PREFIX}event AS event
				INNER JOIN {TABLE_PREFIX}subscribeevent AS subscribeevent ON (subscribeevent.eventid = event.eventid)
				INNER JOIN {TABLE_PREFIX}user AS user ON (user.userid = subscribeevent.userid)
				LEFT JOIN {TABLE_PREFIX}calendar AS calendar ON (event.calendarid = calendar.calendarid)
				WHERE ((dateline_to >= {beginday} AND dateline_from < {endday}) OR (dateline_to = 0 AND dateline_from >= {beginday} AND dateline_from <= {endday} ))
					AND event.visible = 1
			"
		),
		'fetchFeeds' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT rssfeed.*, rssfeed.options AS rssoptions, user.*, channel.nodeid as channelid
					FROM {TABLE_PREFIX}rssfeed AS rssfeed
					INNER JOIN {TABLE_PREFIX}user AS user ON (user.userid = rssfeed.userid)
					INNER JOIN {TABLE_PREFIX}node AS channel ON(channel.nodeid = rssfeed.nodeid)
					WHERE rssfeed.options & {bf_misc_feedoptions_enabled}
			"
		),
		'fetchRSSFeeds' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT *, node.title AS threadtitle
				FROM {TABLE_PREFIX}rsslog AS rsslog
				INNER JOIN {TABLE_PREFIX}rssfeed AS rssfeed ON(rssfeed.rssfeedid = rsslog.rssfeedid)
				INNER JOIN {TABLE_PREFIX}node AS node ON(node.nodeid = rsslog.itemid AND node.starter = node.nodeid)
				WHERE rsslog.topicactioncomplete = 0
					AND rsslog.itemtype = 'topic'
					AND rsslog.topicactiontime <> 0
					AND rsslog.topicactiontime < {TIMENOW}
				"
		),
		'fetchForumThreads' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT forum.htmltitle AS forumhtmltitle, thread.nodeid AS threadid, thread.htmltitle,
					thread.publishdate, thread.parent AS forumid, thread.lastcontent AS lastpost, thread.open, thread.textcount AS replycount,
					thread.authorname AS postusername, threas.userid AS postuserid, thread.lastcontentauthor AS lastposter, thread.publishdate AS dateline
				FROM {TABLE_PREFIX}node AS thread
				INNER JOIN {TABLE_PREFIX}closure AS closure ON ( closure.child = thread.nodeid )
				INNER JOIN {TABLE_PREFIX}node AS forum ON ( forum.nodeid = {forumid})
				WHERE closure.parent = {forumid} AND closure.depth = 1 AND
					thread.lastpost > {lastdate} AND
					thread.visible = 1
				"
		),
		/** @todo review this query*/
		'fetchSocialGroupDigests' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT user.userid, user.salt, user.username, user.email, user.languageid, user.usergroupid, user.membergroupids,
					user.timezoneoffset, IF(user.options & {dstonoff}, 1, 0) AS dstonoff,
					IF(user.options & {hasaccessmask}, 1, 0) AS hasaccessmask,
					language.dateoverride AS lang_dateoverride, language.timeoverride AS lang_timeoverride, language.locale AS lang_locale
				FROM {TABLE_PREFIX}subscribegroup AS subscribegroup
				INNER JOIN {TABLE_PREFIX}user AS user ON (user.userid = subscribegroup.userid)
				LEFT JOIN {TABLE_PREFIX}usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
				LEFT JOIN {TABLE_PREFIX}language AS language ON (language.languageid = IF(user.languageid = 0, {languageid}, user.languageid))
				WHERE subscribegroup.emailupdate = {type} AND
					socialgroup.lastpost > {lastdate} AND
					user.usergroupid <> 3 AND
					(usergroup.genericoptions & {isnotbannedgroup})
			"
		),
		/** @todo review this query*/
		'fetchGroupDiscussions' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT discussion.*, firstmessage.dateline,
					firstmessage.title, firstmessage.postuserid, firstmessage.postusername
				FROM {TABLE_PREFIX}discussion AS discussion
				INNER JOIN {TABLE_PREFIX}node AS firstmessage ON
					(node.nodeid = discussion.firstpostid)
				WHERE discussion.groupid = {groupid}
					AND discussion.lastpost > {lastdate}
					AND firstmessage.state = 'visible'
				"
		),
		'fetchUsersToActivate' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT user.userid, user.usergroupid, username, email, activationid, user.languageid
				FROM {TABLE_PREFIX}user AS user
				LEFT JOIN {TABLE_PREFIX}useractivation AS useractivation ON (user.userid=useractivation.userid AND type = 0)
				WHERE user.usergroupid = 3
					AND ((joindate >= {time1} AND joindate <= {time2}) OR (joindate >= {time3} AND joindate <= {time4}))
					AND NOT (user.options & {noactivationmails})
				"
		),
		'removeProfileVisits' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT userid
				FROM {TABLE_PREFIX}profilevisitor
				WHERE visible = 1
				GROUP BY userid
				HAVING COUNT(*) > {profilemaxvisitors}
				"
		),
		'getUserExpiredInfractions' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT infractionid, infraction.userid, points, username
				FROM {TABLE_PREFIX}infraction AS infraction
				LEFT JOIN {TABLE_PREFIX}user AS user USING (userid)
				WHERE expires <= {timenow}
					AND expires <> 0
					AND action = 0
				"
		),
		'updateSettingsDefault' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'query_string' => "
				UPDATE {TABLE_PREFIX}setting
				SET value = defaultvalue
				WHERE varname = 'templateversion'
				"
		),

		// admincp - index [START]
		'getTableStatus' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SHOW TABLE STATUS
			"
		),
		'getCustomAvatarFilesizeSum' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT SUM(filesize) AS size FROM {TABLE_PREFIX}customavatar
			"
		),
		'showVariablesLike' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SHOW VARIABLES LIKE {var}
			"
		),
		'getIncompleteAdminMessages' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT adminmessage.adminmessageid
			FROM {TABLE_PREFIX}adminmessage AS adminmessage
			INNER JOIN {TABLE_PREFIX}adminlog AS adminlog ON (adminlog.script = adminmessage.script AND adminlog.action = adminmessage.action)
			WHERE adminmessage.status = 'undone'
				AND adminmessage.script <> ''
				AND adminlog.dateline > adminmessage.dateline
			GROUP BY adminmessage.adminmessageid
			"
		),
		'getUserSessionsCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT DISTINCT userid FROM {TABLE_PREFIX}session WHERE userid <> 0 AND lastactivity > {datecut}
			"
		),
		// admincp - index [END]

		// admincp - email [START]
		'emailReplaceUserActivation' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				REPLACE INTO {TABLE_PREFIX}useractivation
					(userid, dateline, activationid, type, usergroupid)
				VALUES
					({userid}, {dateline}, {activateid} , {type}, {usergroupid})
			",
		),
		// admincp - email [END]

		// admincp - deployads [START]
		'updateTemplateAdDeploy' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}template
				SET
					template = {template},
					template_un = {template_un},
					dateline = {dateline},
					username = {username}
				WHERE
					title = {title} AND
					styleid = -1 AND
					product IN ('', 'vbulletin')
			",
		),
		'replaceDatastoreAdSenseDeployed' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				REPLACE INTO {TABLE_PREFIX}datastore
					(title, data, unserialize)
				VALUES
					('adsensedeployed', {data}, 0)
			",
		),
		// admincp - deployads [END]

		// admincp - plugin [START]
		'getMaxPluginId' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT MAX(pluginid) AS max FROM {TABLE_PREFIX}plugin
			",
		),
		'getHookInfo' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT hook.*,
					IF(product.productid IS NULL, 0, 1) AS foundproduct,
					IF(hook.product = 'vbulletin', 1, product.active) AS productactive
				FROM {TABLE_PREFIX}hook AS hook
				LEFT JOIN {TABLE_PREFIX}product AS product ON(product.productid = hook.product)
				WHERE hookid = {hookid}
			",
		),
		'getHooktypePhrases' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT varname
				FROM {TABLE_PREFIX}phrase
				WHERE varname LIKE 'hooktype_%'
			",
		),
		'getHookProductInfo' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT hook.*, IF(hook.product = '', 'vbulletin', product.title) AS producttitle,
				description, version, url, versioncheckurl, product.active AS productactive
				FROM {TABLE_PREFIX}hook AS hook
				LEFT JOIN {TABLE_PREFIX}product AS product ON (hook.product = product.productid)
				ORDER BY producttitle, hook.title
			",
		),
		'getHookProductList' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT hook.hookname, hook.template, hook.arguments, hook.hookid, hook.product
				FROM {TABLE_PREFIX}hook AS hook
				LEFT JOIN {TABLE_PREFIX}product AS product ON (hook.product = product.productid)
				WHERE hook.active = 1 AND (IFNULL(product.active, 0) = 1 OR hook.product = 'vbulletin')
				ORDER BY hook.hookname, hook.hookorder
			",
		),
		// @TODO define how to remove package related info
		'removePackage' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "
				DELETE package, route, contenttype
				FROM {TABLE_PREFIX}package AS package
				LEFT JOIN {TABLE_PREFIX}routenew AS route
					ON route.packageid = package.packageid
				LEFT JOIN {TABLE_PREFIX}contenttype AS contenttype
					ON contenttype.packageid = package.packageid
				WHERE productid = {productid}
			",
		),
		'removePackageTemplate' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "
				DELETE t1
				FROM {TABLE_PREFIX}template AS t1
				INNER JOIN {TABLE_PREFIX}template AS t2 ON (t1.title = t2.title AND t2.product = {productid} AND t2.styleid = -1)
				WHERE t1.styleid = -10
			",
		),
		'removePackageTypesFetch' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT contenttypeid
				FROM {TABLE_PREFIX}contenttype AS c
				INNER JOIN {TABLE_PREFIX}package AS p ON (c.packageid = p.packageid)
				WHERE
					p.productid = {productid}
						AND
					c.canattach = 1
			",
		),
		'installProductPhraseTypeInsert' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				INSERT IGNORE INTO {TABLE_PREFIX}phrasetype
					(fieldname, title, editrows, product)
				VALUES
					({fieldname}, {title}, {editrows}, {product})
			",
		),
		'installProductSettingGroupInsert' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				INSERT IGNORE INTO {TABLE_PREFIX}settinggroup
					(grouptitle, displayorder, volatile, product)
				VALUES
					({grouptitle}, {displayorder}, {volatile}, {product})
			",
		),
		// admincp - plugin [END]

		// we assume that there's only one instance of this type of container
		'getBlogSidebarModules' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT m.*, w2.title, w2.guid
				FROM {TABLE_PREFIX}widgetinstance container
				INNER JOIN {TABLE_PREFIX}widget w ON w.widgetid = container.widgetid AND w.guid = 'vbulletin-widget_container-4eb423cfd6dea7.34930867'
				INNER JOIN {TABLE_PREFIX}widgetinstance m ON m.parent = container.widgetinstanceid
				INNER JOIN {TABLE_PREFIX}widget w2 ON w2.widgetid = m.widgetid
				WHERE container.pagetemplateid = {blogPageTemplate}
			",
		),

		'getWidgetTemplates' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT m.widgetinstanceid, w.widgetid, w.title, w.template
				FROM {TABLE_PREFIX}widgetinstance m
				INNER JOIN {TABLE_PREFIX}widget w ON w.widgetid = m.widgetid
				WHERE m.widgetinstanceid IN ({modules})
			",
		),

		'fetchUserFields' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SHOW COLUMNS FROM {TABLE_PREFIX}userfield
			",
		),

		// mostly needed for unit test.
		'addUserField' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				ALTER TABLE {TABLE_PREFIX}userfield ADD field{profilefieldid} MEDIUMTEXT NOT NULL
			",
		),

		'cacheExpireSelect' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT cacheid
				FROM {TABLE_PREFIX}cache AS cache
				WHERE expires BETWEEN {timefrom} AND {timeto}
			",
		),

		'cacheExpireDelete' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "
				DELETE cache, cacheevent
				FROM {TABLE_PREFIX}cache AS cache
				LEFT JOIN {TABLE_PREFIX}cacheevent AS cacheevent USING (cacheid)
				WHERE cache.expires BETWEEN {timefrom} AND {timeto}
			",
		),

		'cacheDeleteAll' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "
				DELETE cache, cacheevent
				FROM {TABLE_PREFIX}cache AS cache
				LEFT JOIN {TABLE_PREFIX}cacheevent AS cacheevent USING (cacheid)
			",
		),

		'cacheAndEventDelete' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "
				DELETE cache, cacheevent
				FROM {TABLE_PREFIX}cache AS cache
				LEFT JOIN {TABLE_PREFIX}cacheevent AS cacheevent USING (cacheid)
				WHERE cache.cacheid IN ({cacheid})
			",
		),

		'getStylesForMaster' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT styleid
				FROM {TABLE_PREFIX}style
				WHERE INSTR(CONCAT(',', parentlist, ','), {masterid})
			",
		),

		'getStylevarData' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT stylevar.*
				FROM {TABLE_PREFIX}stylevar AS stylevar
				INNER JOIN {TABLE_PREFIX}stylevardfn AS stylevardfn
				ON (stylevar.stylevarid = stylevardfn.stylevarid)
				WHERE stylevar.styleid IN ({styles})
				AND stylevardfn.styleid = {masterid}
			",
		),

		'deleteStylevarData' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				DELETE stylevar, stylevardfn
				FROM {TABLE_PREFIX}stylevar AS stylevar
				INNER JOIN {TABLE_PREFIX}stylevardfn AS stylevardfn
				ON (stylevar.stylevarid = stylevardfn.stylevarid)
				WHERE stylevardfn.stylevarid = {stylevar}
				AND stylevardfn.product IN ({products})
				AND stylevar.styleid IN ({styles})
			",
		),

		'deleteStylevarPhrases' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				DELETE FROM {TABLE_PREFIX}phrase
				WHERE fieldname = 'style'
				AND product IN ({products})
				AND varname IN ({phrases})
			",
		),
		'replace_adminutil'=> array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "	REPLACE INTO {TABLE_PREFIX}adminutil(title, text)
				VALUES
					('datastore', {text})"),
		'updateLastVisit' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}user
				SET lastvisit = lastactivity,
				lastactivity = {timenow}
				WHERE userid = {userid}
			",
		),
		'decrementFiledataRefcount' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}filedata
			SET refcount = (refcount - 1)
			WHERE filedataid IN ({filedataid})"
		),
		'incrementFiledataRefcount' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}filedata
			SET refcount = (refcount + 1),
			publicview = 1
			WHERE filedataid = {filedataid}"
		),
		'attachmentsByContentType' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT a.attachmentid, a.contenttypeid
				FROM {TABLE_PREFIX}filedata AS fd
				LEFT JOIN {TABLE_PREFIX}attachment AS a ON (a.filedataid = fd.filedataid)
				WHERE contenttypeid = {ctypeid}
			",
		),
		'getModLogs' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT type, username, dateline
				FROM {TABLE_PREFIX}moderatorlog AS modlog
				INNER JOIN {TABLE_PREFIX}user AS user USING(userid)
				WHERE nodeid = {nodeid}
				ORDER BY modlog.dateline DESC
			"
		),
		'editlog_replacerecord' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				REPLACE INTO {TABLE_PREFIX}editlog
					(nodeid, userid, username, dateline, reason, hashistory)
				VALUES
					({nodeid}, {userid}, {username}, {timenow}, {reason}, {hashistory})
			",
		),
		'getModeratedTopics' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*) AS count
				FROM {TABLE_PREFIX}node
				INNER JOIN {TABLE_PREFIX}closure
				WHERE showapproved = 0
 				AND child = nodeid
				AND starter = nodeid
 				AND parent IN ({rootids})
				AND contenttypeid NOT IN ({typeids})
			",
		),
		'getModeratedReplies' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*) AS count
				FROM {TABLE_PREFIX}node
				INNER JOIN {TABLE_PREFIX}closure
				WHERE showapproved = 0
 				AND child = nodeid
				AND starter != nodeid
 				AND parent IN ({rootids})
				AND contenttypeid NOT IN ({typeids})
			",
		),
		'getModeratedAttachments' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(nodeid) AS count
				FROM {TABLE_PREFIX}node
				WHERE showapproved = 0
				AND contenttypeid IN({typeids})
			",
		),
		'getModeratedVisitorMessages' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(nodeid) AS count
				FROM {TABLE_PREFIX}node
				WHERE showapproved = 0
				AND parentid = {typeid}
			",
		),
		'getRootChannels' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT nodeid, title
				FROM {TABLE_PREFIX}channel
				INNER JOIN {TABLE_PREFIX}node USING (nodeid)
				WHERE guid IN ({guids})
			",
		),
		'getSiteForums' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT n.nodeid, n.title, n.htmltitle AS title_clean, n.lastcontent AS lastpost, n.routeid
				FROM {TABLE_PREFIX}node n
				WHERE n.nodeid IN ({viewable_forums}) AND n.nodeid >= {startat} AND n.showapproved > 0 AND n.showpublished > 0 AND n.open = 1 AND n.inlist = 1
				ORDER BY n.nodeid
				LIMIT {perpage}
			",
		),
		'writeAdminUtilSession' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				REPLACE INTO {TABLE_PREFIX}adminutil
					(title, text)
				VALUES
					('sitemapsession', {session})
			",
		),
		'getReplacementTemplates' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT templateid, title, styleid, template
				FROM {TABLE_PREFIX}template
				WHERE templatetype = 'replacement'
				AND templateid IN({templateids})
				ORDER BY title
			",
		),
		'userstylevarCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*)
				FROM {TABLE_PREFIX}userstylevar
				WHERE userid = {userid}
			",
		),
	);

	public function getModeratorInfo($params, $db, $check_only = false) {
		if ($check_only) {
			return !empty($params['condition']);
		} else {
			$className = 'vB_Db_' . $this->db_type . '_QueryBuilder';
			$queryBuilder = new $className($db, false);
			$where = $queryBuilder->conditionsToFilter($params['condition']);


			$sql = "SELECT user.userid, usergroupid, username, displaygroupid, moderatorid ";
			$sql.= "FROM " . TABLE_PREFIX . "moderator AS moderator ";
			$sql.= "INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid) ";
			$sql.= "WHERE " . $where;

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function getIds($params, $db, $check_only = false) {
		if ($check_only) {
			return !empty($params['table']);
		} else {
			$tableas = ($params['table'] == 'filedata') ? "fd" : "a";
			$joinas = ($tableas == 'fd') ? 'a' : 'fd';
			$sql = "SELECT a.attachmentid, fd.userid, fd.filedataid, a.userid AS auserid, a.contenttypeid ";
			$sql.= "FROM " . TABLE_PREFIX . $params['table'] . " AS $tableas ";
			$sql.= "LEFT JOIN " . TABLE_PREFIX . $params['join'] . " AS $joinas ON (a.filedataid = fd.filedataid) ";
			$sql.= "WHERE " . $params['condition'];

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function getBlogUserSummaryStats($params, $db, $check_only = false) {
		if ($check_only) {
			return !empty($params['sumtype']);
		} else {
			$table = (!empty($params['userid']) ? "user" : "summary") . "stats";
			$sql = "SELECT SUM({$params['type']}) AS total, ";
			$sql.= "DATE_FORMAT(from_unixtime(dateline), '{$params['sqlformat']}') AS formatted_date, ";
			$sql.= "MAX(dateline) AS dateline ";
			$sql.= "FROM " . TABLE_PREFIX . $table . " ";
			$sql.= "WHERE dateline >= {$params['start_time']} ";
			$sql.= "AND dateline <= {$params['end_time']} ";
			$sql.= (!empty($params['userid']) ? "AND userid = {$params['userid']} " : "");
			$sql.= "GROUP BY formatted_date ";
			$sql.= (empty($params['nullvalue']) ? " HAVING total > 0 " : "");
			$sql.= "ORDER BY {$params['orderby']}";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function getChangelogData($params, $db, $check_only = false) {
		if ($check_only) {
			return isset($params[vB_dB_Query::PARAM_LIMITPAGE]);
		} else {
			$count = count($params);
			$query = "SELECT ".($params['just_count'] ? "COUNT(userchangelog.changeid) AS change_count" : "user.*, userchangelog.*, adminuser.username AS admin_username ") . "
				FROM " . TABLE_PREFIX . "userchangelog AS userchangelog
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = userchangelog.userid)
				LEFT JOIN " . TABLE_PREFIX . "user AS adminuser ON(adminuser.userid = userchangelog.adminid)
				WHERE ";
			$i = 0;
			foreach ($params as $key => $value) {
				switch ($key) {
					case 'userchangelog.oldvalue':
						$query.= " ($key = '$value' OR userchangelog.newvalue = '$value') ";
						break;
					case 'time_start':
						$query.= " change_time >= $value ";
						break;
					case 'time_end':
						$query.= " change_time <= $value ";
						break;
					case 'userchangelog.newvalue':
					case 'just_count':
					case vB_dB_Query::PARAM_LIMITSTART:
					case vB_dB_Query::PARAM_LIMITPAGE:
					case vB_dB_Query::PARAM_LIMIT:
					case vB_dB_Query::TYPE_KEY:
						break;
					default:
						$query.= " $key = '$value' ";
						break;
				}

				if ($i < $count && ($key != 'userchangelog.newvalue' && $key != 'just_count' && $key != 'page' && $key != vB_dB_Query::PARAM_LIMIT && $key != vB_dB_Query::TYPE_KEY)) {
					$query.= "AND";
					$i++;
				}
			}
			$query.= ($params['just_count'] ? "" : " ORDER BY userchangelog.change_time DESC, userchangelog.change_uniq ASC, userchangelog.fieldname DESC ");
			$query.= ($params['just_count'] ? "" : " LIMIT " . ($params[vB_dB_Query::PARAM_LIMIT]*$params[vB_dB_Query::PARAM_LIMITPAGE]) . ", " . $params[vB_dB_Query::PARAM_LIMIT]);

			while(strpos($query, 'ANDAND') !== false)
			{
				$query = str_replace("ANDAND", "AND", $query);

			}
			$query = str_replace("AND ORDER", " ORDER", $query);

			if (substr($query, -4) == ' AND') {
				$query = substr($query, 0, -4);
			}

			//echo $query;
			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $query);
			return $result;
		}
	}

	public function getBlogMembers($params, $db, $check_only = false) {
		if ($check_only) {
			return !empty($params['userid']);
		} else {
			$sql = "SELECT user.*, gm.state \n";
			$sql.= ($params['avatarenabled'] ? ", avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline, customavatar.width_thumb AS avwidth_thumb, customavatar.height_thumb AS avheight_thumb, customavatar.width as avwidth, customavatar.height as avheight, customavatar.filedata_thumb \n" : "");
			$sql.= "FROM " . TABLE_PREFIX . "blog_groupmembership AS gm \n";
			$sql.= "LEFT JOIN " . TABLE_PREFIX . "user AS user on (user.userid = gm.userid) \n";
			$sql.= ($params['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON (avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON (customavatar.userid = user.userid) \n" : "");
			$sql.= "WHERE bloguserid = " . $params['userid'] . "\n";
			$sql.= "ORDER BY gm.state, user.username";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function getBlogAdminEntries($params, $db, $check_only = false) {
		if ($check_only) {
			return !empty($params['wheresql']);
		} else {
			$sql = "SELECT SQL_CALC_FOUND_ROWS blogid, dateline, title, blog.userid, user.username, state, pending \n";
			$sql.= "FROM " . TABLE_PREFIX . "blog AS blog \n";
			$sql.= "LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid) \n";
			$sql.= "WHERE \n";
			$sql.= implode(" AND ", $params['wheresql']);
			$sql.= " ORDER BY " . $params['orderby'] . " \n";
			$sql.= "LIMIT " . $params['start'] . ", " . $params[vB_dB_Query::PARAM_LIMIT];

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function getStyle($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//I can't work without a styleid
			return !empty($params['styleid']);
		}
		else
		{
			//Note the opening paren is here, and the closing paren is several lines down.
			$sql = "SELECT * FROM " . TABLE_PREFIX . "style AS style WHERE (styleid = " . $params['styleid'];
			if ($params['userselect'])
			{
				$sql .= " AND userselect = 1 ";
			}
			if ($params['defaultstyleid'])
			{
				$sql .= " OR styleid = " . $params['defaultstyleid'];
			}
			$sql .= ") ORDER BY styleid ";

			if (empty($params['direction']) OR ($params['direction'] == 'asc'))
			{
				$sql .= 'ASC';
			}
			else
			{
				$sql .= 'DESC';
			}

			$sql .= " LIMIT 1";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function searchTemplates($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['templateids']);
		}
		else
		{
			if (empty($params['searchstring']))
			{
				$searchconds = '';
			}
			elseif ($params['titlesonly'])
			{
				$searchconds = "AND t1.title LIKE('%" . $db->escape_string_like($params['searchstring']) . "%')";
			}
			else
			{
				$searchconds = "AND ( t1.title LIKE('%" . $db->escape_string_like($params['searchstring']) . "%') OR template_un LIKE('%" . $db->escape_string_like($params['searchstring']) . "%') ) ";
			}

			$sql = "
				SELECT
					templateid, IF(t1.title LIKE '%.css', CONCAT('css_', t1.title), title) AS title, styleid, templatetype, dateline, username
				FROM " . TABLE_PREFIX . "template AS t1
				WHERE
					templatetype IN('template', 'replacement') $searchconds
						AND
					templateid IN($params[templateids])
				ORDER BY title
			";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	//todo fix conds so we aren't passing sql directly to this function
	public function getStyleByConds($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['conds']) AND isset($params['limit_style']);
		}
		else
		{
			$className = 'vB_Db_' . $this->db_type . '_QueryBuilder';
			$queryBuilder = new $className($db, false);
			$where = $queryBuilder->conditionsToFilter($params['conds']);

			$sql = "SELECT styleid, title, templatelist FROM " . TABLE_PREFIX .
				"style WHERE $where LIMIT $params[limit_style], 1";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function getTemplatesForDump($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['styleid']);
		}
		else
		{
			$sql = "SELECT title, templatetype, username, dateline, template_un AS template
						FROM " . TABLE_PREFIX . "template
						WHERE styleid = " . intval($params['styleid']) . "
								AND templatetype = 'template'
								" . iif($params['templateids'], "AND templateid IN($params[templateids])") . "
						ORDER BY title";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function fetchUserinfo($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['userid']);
		}
		else
		{
			if (!isset($params['option']) OR !$params['option'])
			{
				$params['option'] = array();
			}

			if (!isset($params['currentuserid']) OR !$params['currentuserid'])
			{
				if ($session = vB::getCurrentSession())
				{
					$params['currentuserid'] = $session->get('userid');
				}
				else
				{
					$params['currentuserid'] = 0;
				}
			}

			if(!is_array($params['userid']))
			{
				$params['userid'] = intval($params['userid']);
			}
			else
			{
				$params['userid'] = implode(",", $params['userid']);
			}

			$vboptions = vB::getDatastore()->get_value('options');

			$sql = "SELECT " .
					(in_array(vB_Api_User::USERINFO_ADMIN, $params['option']) ? ' administrator.*, ' : '') . "
					userfield.*, usertextfield.*, user.*, UNIX_TIMESTAMP(passworddate) AS passworddate, user.languageid AS saved_languageid,
					IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid" .
					((in_array(vB_Api_User::USERINFO_AVATAR, $params['option']) AND $vboptions['avatarenabled']) ? ', avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline, customavatar.width AS avwidth, customavatar.height AS avheight, customavatar.height_thumb AS avheight_thumb, customavatar.width_thumb AS avwidth_thumb, customavatar.filedata_thumb' : '').
					(in_array(vB_Api_User::USERINFO_PROFILEPIC, $params['option']) ? ', customprofilepic.userid AS profilepic, customprofilepic.dateline AS profilepicdateline, customprofilepic.width AS ppwidth, customprofilepic.height AS ppheight' : '') .
					(in_array(vB_Api_User::USERINFO_SIGNPIC, $params['option']) ? ', sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight' : '') .
					(in_array(vB_Api_User::USERINFO_USERCSS, $params['option']) ? ', usercsscache.cachedcss, IF(usercsscache.cachedcss IS NULL, 0, 1) AS hascachedcss, usercsscache.buildpermissions AS cssbuildpermissions' : '') .
					(($params['currentuserid'] AND in_array(vB_Api_User::USERINFO_ISFRIEND, $params['option'])) ?
						", IF(userlist1.friend = 'yes', 1, 0) AS isfriend, IF (userlist1.friend = 'pending' OR userlist1.friend = 'denied', 1, 0) AS ispendingfriend" .
						", IF(userlist1.userid IS NOT NULL, 1, 0) AS u_iscontact_of_bbuser, IF (userlist2.friend = 'pending', 1, 0) AS requestedfriend" .
						", IF(userlist2.userid IS NOT NULL, 1, 0) AS bbuser_iscontact_of_user" : "") . "
				FROM " . TABLE_PREFIX . "user AS user
				LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (user.userid = userfield.userid)
				LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid) " .
				((in_array(vB_Api_User::USERINFO_AVATAR, $params['option']) AND $vboptions['avatarenabled']) ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON (avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON (customavatar.userid = user.userid) " : '') .
				(in_array(vB_Api_User::USERINFO_PROFILEPIC, $params['option']) ? "LEFT JOIN " . TABLE_PREFIX . "customprofilepic AS customprofilepic ON (user.userid = customprofilepic.userid) " : '') .
				(in_array(vB_Api_User::USERINFO_ADMIN, $params['option']) ? "LEFT JOIN " . TABLE_PREFIX . "administrator AS administrator ON (administrator.userid = user.userid) " : '') .
				(in_array(vB_Api_User::USERINFO_SIGNPIC, $params['option']) ? "LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON (user.userid = sigpic.userid) " : '') .
				(in_array(vB_Api_User::USERINFO_USERCSS, $params['option']) ? 'LEFT JOIN ' . TABLE_PREFIX . 'usercsscache AS usercsscache ON (user.userid = usercsscache.userid)' : '') .
				(($params['currentuserid'] AND in_array(vB_Api_User::USERINFO_ISFRIEND, $params['option'])) ?
					"LEFT JOIN " . TABLE_PREFIX . "userlist AS userlist1 ON (userlist1.relationid = user.userid AND userlist1.type = 'buddy' AND userlist1.userid = " . $params['currentuserid'] . ")" .
					"LEFT JOIN " . TABLE_PREFIX . "userlist AS userlist2 ON (userlist2.userid = user.userid AND userlist2.type = 'buddy' AND userlist2.relationid = " . $params['currentuserid'] . ")" : "") .
					"WHERE user.userid IN ($params[userid])
					/** fetchUserinfo **/
					";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function fetchLanguage($params, $dbobject, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}

		if (empty($params['phrasegroups']))
		{
			$phrasegroups = array();
		}
		else if (is_array($params['phrasegroups']))
		{
			$phrasegroups = $params['phrasegroups'];
		}
		else
		{
			$phrasegroups = array($params['phrasegroups']);
		}

		if (!in_array('global', $phrasegroups))
		{
			$phrasegroups[]= 'global';
		}
		$sql = 'SELECT ';
		$fields = array();
		foreach ($phrasegroups AS $group)
		{
			$fields[] = 'phrasegroup_' . preg_replace('#[^a-z0-9_]#i', '', $group); // just to be safe...
		}
		$sql .= implode(",\n ", $fields);
		$sql .= ",
		options AS lang_options,
		languagecode AS lang_code,
		charset AS lang_charset,
		locale AS lang_locale,
		imagesoverride AS lang_imagesoverride,
		dateoverride AS lang_dateoverride,
		timeoverride AS lang_timeoverride,
		registereddateoverride AS lang_registereddateoverride,
		calformat1override AS lang_calformat1override,
		calformat2override AS lang_calformat2override,
		logdateoverride AS lang_logdateoverride,
		decimalsep AS lang_decimalsep,
		thousandsep AS lang_thousandsep";

		if (!empty($params['languageid']))
		{
			$sql .= "\n FROM " . TABLE_PREFIX . "language WHERE languageid = " . $params['languageid'];
		}
		else
		{
			$options = vB::getDatastore()->getValue('options');
			$sql .= "\n FROM " . TABLE_PREFIX . "language WHERE languageid = " . intval($options['languageid']);
		}

		$sql .= "/* fetchLanguage */";
		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($dbobject, $sql);
		return $result;
	}

	public function datamanagerUpdate($params, $dbobject, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['table']) AND !empty($params['tableData']));
		}

		$table = $params['table'];
		$tableData = $params['tableData'];
		$bitfields = $params['bitfields'];
		$rawfields = $params['rawfields'];
		$condition = $params['condition'];

		if (sizeof($tableData) == 0)
		{
			return '';
		}

		$sql = "UPDATE " . TABLE_PREFIX . "{$table} SET";

		foreach ($tableData AS $fieldname => $value)
		{
			if (isset($bitfields["$fieldname"]) AND is_array($value) AND sizeof($value) > 0)
			{
				$sql .= "\r\n\t### Bitfield: " . TABLE_PREFIX . "{$table}.$fieldname ###";
				foreach ($value AS $bitvalue => $bool)
				{
					$sql .= "\r\n\t\t$fieldname = IF($fieldname & $bitvalue, " . ($bool ? "$fieldname, $fieldname + $bitvalue" : "$fieldname - $bitvalue, $fieldname") . "),";
				}
			}
			else
			{
				$sql .= "\r\n\t$fieldname = " . (isset($rawfields["$fieldname"]) ? ($value === null ? 'NULL' : $value) : $dbobject->sql_prepare($value)) . ",";
			}
		}

		$sql = substr($sql, 0, -1);
		if (!is_array($condition)) {
			$sql .= "\r\nWHERE $condition";
		} elseif(count($condition == 1)) {
			$sql .= "\r\nWHERE ";
			foreach ($condition as $condkey => $condval) {
				$sql .= $condkey . " = " . $condval;
			}
		}

		$resultClass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultClass($dbobject, $sql);

		return $result;
	}

	public function datamanagerInsert($params, $dbobject, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['table']) AND !empty($params['tableData']));
		}

		$table = $params['table'];
		$tableData = $params['tableData'];
		$bitfields = $params['bitfields'];
		$rawfields = $params['rawfields'];
		$replace = $params['replace'];
		$ignore = $params['ignore'];

		$sql = ($replace ? "REPLACE" : ($ignore ? "INSERT IGNORE" : "INSERT")) . " INTO " . TABLE_PREFIX . "{$table}\r\n\t(" . implode(', ', array_keys($tableData)) . ")\r\nVALUES\r\n\t(";

		foreach ($tableData AS $fieldname => $value)
		{
			if (isset($bitfields["$fieldname"]))
			{
				$bits = 0;
				foreach ($value AS $bitvalue => $bool)
				{
					$bits += $bool ? $bitvalue : 0;
				}
				$value = $bits;
			}

			$sql .= ( isset($rawfields["$fieldname"]) ? ($value === NULL ? 'NULL' : $value) : $dbobject->sql_prepare($value)) . ', ';
		}

		$sql = substr($sql, 0, -2);
		$sql .= ')';

		//match the behavior of a normal insert query and return the result string
		$result = $dbobject->query_write($sql);

		if ($dbobject->error())
		{
			throw new Exception($dbobject->error());
		}
		else if (!empty($result['errors']))
		{
			throw new Exception($result['errors']);
		}
		$results[] = $dbobject->insert_id();
		return $results;
	}

	public function datamanagerDelete($params, $dbobject, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['table']) AND !empty($params['condition']));
		}

		$table = $params['table'];
		$condition = $params['condition'];

		$sql = "DELETE FROM " . TABLE_PREFIX . "{$table} WHERE $condition";
		if (!$check_only)
		{
			$resultClass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultClass($dbobject, $sql);

			return $result;
		}
	}

	public function userdmFetchProfilefields($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['field_ids']) AND $params['all_fields'] != 'register';
		}
		else
		{
			switch ($params['all_fields'])
			{
				case 'admin':
					$all_fields_sql = "WHERE profilefieldid IN(" . implode(', ', $params['field_ids']) . ")";
					break;

				default:
					$all_fields_sql = "WHERE profilefieldid IN(" . implode(', ', $params['field_ids']) . ") AND editable IN (1,2)";
					break;
			}

			$sql = "SELECT profilefieldid, required, size, maxlength, type, data, optional, regex, def, editable
								FROM " . TABLE_PREFIX . "profilefield
								$all_fields_sql
								ORDER BY displayorder";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function userdmUpdateSubscribethread($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['userid']);
		}
		else
		{

			$sql = "
								UPDATE " . TABLE_PREFIX . "subscribethread
								SET canview =
								CASE
										" . (!empty($params['remove_thread']) ? " WHEN subscribethreadid IN (" . implode(', ', $params['remove_thread']) . ") THEN 0" : "") . "
										" . (!empty($params['add_thread']) ? " WHEN subscribethreadid IN (" . implode(', ', $params['add_thread']) . ") THEN 1" : "") . "
								ELSE canview
								END
								WHERE userid = $params[userid]
								AND subscribethreadid IN (" . implode(',', array_unique(array_merge($params['remove_thread'], $params['add_thread']))) . ")
						";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function userFind($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['filters']) OR !empty($params['exceptions']));
		}
		else
		{
			//
			switch ($params['orderby'])
			{
				//user id is mostly used for tests, but its a valid option so it can
				//be included here.
				case 'userid':
				case 'username':
				case 'email':
				case 'joindate':
				case 'lastactivity':
				case 'lastpost':
				case 'posts':
				case 'birthday_search':
				case 'reputation':
				case 'warnings':
				case 'infractions':
				case 'ipoints':
					//qualify the field so that we don't get DB errors
					$params['orderby'] = '`user`.`' . $params['orderby'] . '`';
					break;
				default:
					$params['orderby'] = 'username';
			}

			if ($params['direction'] != 'DESC')
			{
				$params['direction'] = 'ASC';
			}

			if (empty($params['limitstart']))
			{
				$params['limitstart'] = 0;
			}
			else
			{
				$params['limitstart']--;
			}

			if (empty($params[vB_dB_Query::PARAM_LIMIT]) OR $params[vB_dB_Query::PARAM_LIMIT] == 0)
			{
				$params[vB_dB_Query::PARAM_LIMIT] = 25;
			}

			$className = 'vB_Db_' . $this->db_type . '_QueryBuilder';
			$queryBuilder = new $className($db, false);
			$where = $queryBuilder->conditionsToFilter($params['filters']);

			$exceptions = $params['exception'];
			if (isset($exceptions['membergroup']) AND is_array($exceptions['membergroup']))
			{
				foreach ($exceptions['membergroup'] AS $id)
				{
					$where .= " AND FIND_IN_SET(" . intval($id) . ", " . TABLE_PREFIX . "membergroupids)";
				}
			}

			if ($exceptions['aim'])
			{
				$where .= " AND REPLACE(" . TABLE_PREFIX . "aim, ' ', '') LIKE '%" .
					$db->escape_string_like(str_replace(' ', '', $exceptions['aim'])) . "%'";
			}

			if (!empty($where))
			{
				$where = "WHERE $where";
			}


			$bf_misc_useroptions = vB::getDatastore()->getValue('bf_misc_useroptions');
			$sql = "
				SELECT
					user.userid, reputation, username, usergroupid, birthday_search, email,
					parentemail, (options & " . $bf_misc_useroptions['coppauser'] . ") AS coppauser,
					homepage, icq, aim, yahoo, msn, skype, signature,
					usertitle, joindate, lastpost, posts, ipaddress, lastactivity, userfield.*, infractions, ipoints, warnings
				FROM " . TABLE_PREFIX . "user AS user LEFT JOIN " .
					TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid) LEFT JOIN " .
					TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
				$where
				ORDER BY " . $db->escape_string($params['orderby']) . " " . $db->escape_string($params['direction']) . "
				LIMIT " . $params['limitstart'] . ", " . $params[vB_dB_Query::PARAM_LIMIT]
			;

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function userFindCount($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['filters']) OR !empty($params['exceptions']));
		}
		else
		{
			$className = 'vB_Db_' . $this->db_type . '_QueryBuilder';
			$queryBuilder = new $className($db, false);
			$where = $queryBuilder->conditionsToFilter($params['filters']);
			$exceptions = $params['exception'];
			if (isset($exceptions['membergroup']) AND is_array($exceptions['membergroup']))
			{
				foreach ($exceptions['membergroup'] AS $id)
				{
					$where .= " AND FIND_IN_SET(" . intval($id) . ", " . TABLE_PREFIX . "membergroupids)";
				}
			}

			if ($exceptions['aim'])
			{
				$where .= " AND REPLACE(" . TABLE_PREFIX . "aim, ' ', '') LIKE '%" .
					$db->escape_string_like(str_replace(' ', '', $exceptions['aim'])) . "%'";
			}

			$sql = "
				SELECT COUNT(*) AS users
				FROM " . TABLE_PREFIX . "user AS user LEFT JOIN " .
					TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid) LEFT JOIN " .
					TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
			";

			if (!empty($where))
			{
				$sql .= "
				WHERE $where";
			}
			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function userProfileFields($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['formtype']);
		}
		else
		{
			$sql = "
								SELECT * FROM " . TABLE_PREFIX . "profilefield
								WHERE editable IN (1,2)
										AND form " . ($params['formtype'] ? '>= 1' : '= 0') . "
								ORDER BY displayorder
						";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function userInsertAccess($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['accessupdate']) AND !empty($params['userid']);
		}
		else
		{
			$insert_mask_sql = array();

			foreach ($params['accessupdate'] AS $forumid => $val)
			{
				$forumid = intval($forumid);
				if ($val >= 0)
				{
					$insert_mask_sql[] = '(' . $params['userid'] . ", $forumid, $val)";
				}
			}
			if (!empty($insert_mask_sql))
			{
				/* insert query */
				$sql = "
										INSERT INTO " . TABLE_PREFIX . "access
												(userid, nodeid, accessmask)
										VALUES
												" . implode(",\n\t", $insert_mask_sql)
				;

				$resultclass = 'vB_dB_' . $this->db_type . '_result';
				$result = new $resultclass($db, $sql);
				return $result;
			}
		}
	}

	public function userFetchPruneUsers($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			if ($params['usergroupid'] != -1)
			{
				$sqlconds = "WHERE user.usergroupid = " . $params['usergroupid'] . ' ';
			}
			if ($params['daysprune'])
			{
				$daysprune = intval(TIMENOW - $params['daysprune'] * 86400);
				if ($daysprune < 0)
				{ // if you have a negative number you're never going to find a value
					return null;
				}
				$sqlconds .= iif(empty($sqlconds), 'WHERE', 'AND') . " lastactivity < $daysprune ";
			}
			if ($params['joindate']['month'] AND $params['joindate']['year'])
			{
				$joindateunix = mktime(0, 0, 0, $params['joindate']['month'], $params['joindate']['day'], $params['joindate']['year']);
				if ($joindateunix)
				{
					$sqlconds .= iif(empty($sqlconds), 'WHERE', 'AND') . " joindate < $joindateunix ";
				}
			}
			if ($params['minposts'])
			{
				$sqlconds .= iif(empty($sqlconds), 'WHERE', 'AND') . " posts < " . $params['minposts'] . ' ';
			}

			switch ($params['order'])
			{
				case 'username':
					$orderby = 'ORDER BY username ASC';
					break;
				case 'email':
					$orderby = 'ORDER BY email ASC';
					break;
				case 'usergroup':
					$orderby = 'ORDER BY usergroup.title ASC';
					break;
				case 'posts':
					$orderby = 'ORDER BY posts DESC';
					break;
				case 'lastactivity':
					$orderby = 'ORDER BY lastactivity DESC';
					break;
				case 'joindate':
					$orderby = 'ORDER BY joindate DESC';
					break;
				default:
					$orderby = 'ORDER BY username ASC';
			}

			$sql = "
								SELECT DISTINCT user.userid, username, email, posts, lastactivity, joindate,
								user.usergroupid, moderator.moderatorid, usergroup.title
								FROM " . TABLE_PREFIX . "user AS user
								LEFT JOIN " . TABLE_PREFIX . "moderator AS moderator ON(moderator.userid = user.userid)
								LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON(usergroup.usergroupid = user.usergroupid)
								$sqlconds
								GROUP BY user.userid $orderby
						";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function userInsertSubscribeforum($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['sourceuserid']) AND !empty($params['destuserid']);
		}
		else
		{
			$insertsql = '';
			$subforums = $db->query_read("
								SELECT forumid
								FROM " . TABLE_PREFIX . "subscribeforum
								WHERE userid = $params[destuserid]
						");
			while ($forums = $db->fetch_array($subforums))
			{
				$subscribedforums["$forums[forumid]"] = 1;
			}

			$subforums = $db->query_read("
								SELECT forumid, emailupdate
								FROM " . TABLE_PREFIX . "subscribeforum
								WHERE userid = $params[sourceuserid]
						");
			while ($forums = $db->fetch_array($subforums))
			{
				if (!isset($subscribedforums["$forums[forumid]"]))
				{
					if ($insertsql)
					{
						$insertsql .= ',';
					}
					$insertsql .= "($params[destuserid], $forums[forumid], $forums[emailupdate])";
				}
			}
			if ($insertsql)
			{
				/* insert sql */
				$sql = "
										INSERT INTO " . TABLE_PREFIX . "subscribeforum
												(userid, forumid, emailupdate)
										VALUES
												$insertsql
								";

				$resultclass = 'vB_dB_' . $this->db_type . '_result';
				$result = new $resultclass($db, $sql);
				return $result;
			}
			return null;
		}
	}

	public function userInsertSubscribethread($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['sourceuserid']) AND !empty($params['destuserid']);
		}
		else
		{
			$insertsql = '';
			$subthreads = $db->query_read("
								SELECT threadid, emailupdate
								FROM " . TABLE_PREFIX . "subscribethread
								WHERE userid = $params[destuserid]
						");
			while ($threads = $db->fetch_array($subthreads))
			{
				$subscribedthreads["$threads[threadid]"] = 1;
				$status["$threads[threadid]"] = $threads['emailupdate'];
			}

			$subthreads = $db->query_read("
								SELECT threadid, emailupdate
								FROM " . TABLE_PREFIX . "subscribethread
								WHERE userid = $params[sourceuserid]
						");
			while ($threads = $db->fetch_array($subthreads))
			{
				if (!isset($subscribedthreads["$threads[threadid]"]))
				{
					if ($insertsql)
					{
						$insertsql .= ',';
					}
					$insertsql .= "($params[destuserid], 0, $threads[threadid], $threads[emailupdate])";
				}
				else
				{
					if ($status["$threads[threadid]"] != $threads['emailupdate'])
					{
						$db->query_write("
														UPDATE " . TABLE_PREFIX . "subscribethread
														SET emailupdate = $threads[emailupdate]
														WHERE userid = $params[destuserid]
																AND threadid = $threads[threadid]
												");
					}
				}
			}

			if ($insertsql)
			{
				/* insert sql */
				$sql = "
										INSERT " . TABLE_PREFIX . "subscribethread
												(userid, folderid, threadid, emailupdate)
										VALUES
												$insertsql
								";

				$resultclass = 'vB_dB_' . $this->db_type . '_result';
				$result = new $resultclass($db, $sql);
				return $result;
			}
			return null;
		}
	}

	public function userInsertSubscribeevent($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['sourceuserid']) AND !empty($params['destuserid']);
		}
		else
		{
			$insertsql = '';
			$events = $db->query_read("
								SELECT eventid, reminder
								FROM " . TABLE_PREFIX . "subscribeevent
								WHERE userid = $params[sourceuserid]
						");
			while ($event = $db->fetch_array($events))
			{
				if (!empty($insertsql))
				{
					$insertsql .= ',';
				}
				$insertsql .= "($params[destuserid], $event[eventid], $event[reminder])";
			}

			if ($insertsql)
			{
				/* insert sql */
				$sql = "
										INSERT IGNORE INTO " . TABLE_PREFIX . "subscribeevent
												(userid, eventid, reminder)
										VALUES
												$insertsql
								";

				$resultclass = 'vB_dB_' . $this->db_type . '_result';
				$result = new $resultclass($db, $sql);
				return $result;
			}
			return null;
		}
	}

	public function userInsertAnnouncementread($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['sourceuserid']) AND !empty($params['destuserid']);
		}
		else
		{
			$insertsql = array();
			$announcements = $db->query_read("
								SELECT announcementid
								FROM " . TABLE_PREFIX . "announcementread
								WHERE userid = $params[sourceuserid]
						");
			while ($announcement = $db->fetch_array($announcements))
			{
				$insertsql[] = "($params[destuserid], $announcement[announcementid])";
			}

			if ($insertsql)
			{
				/* insert sql */
				$sql = "
										INSERT IGNORE INTO " . TABLE_PREFIX . "announcementread
												(userid, announcementid)
										VALUES
												" . implode(', ', $insertsql) . "
								";

				$resultclass = 'vB_dB_' . $this->db_type . '_result';
				$result = new $resultclass($db, $sql);
				return $result;
			}
			return null;
		}
	}

	public function userUpdatePoll($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !!empty($params['nodeid']);
		}
		else
		{
			/* insert sql */
			$sql = "
				UPDATE " . TABLE_PREFIX . "poll
				SET
					votes = IF(votes > 0, votes - 1, 0)
					" . ($params['lastvote'] ? ", lastvote = $params[lastvote]" : "") . "
				WHERE nodeid = $params[nodeid]
			";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function userDeleteUsergrouprequest($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['sourceuserid']) AND !empty($params['destusergroupid']);
		}
		else
		{
			/* insert sql */
			$sql = "
								DELETE FROM " . TABLE_PREFIX . "usergrouprequest
								WHERE userid = $params[sourceuserid] AND
										(usergroupid = $params[destusergroupid] " . ($params['destmembergroupids'] != '' ? "OR usergroupid IN (0,$params[destmembergroupids])" : '') . ")
						";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function userSearchRegisterIP($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['ipaddress']) AND isset($params['prevuserid']);
		}
		else
		{
			if (substr($params['ipaddress'], -1) == '.' OR substr_count($params['ipaddress'], '.') < 3)
			{
				// ends in a dot OR less than 3 dots in IP -> partial search
				$ipaddress_match = "ipaddress LIKE '" . $db->escape_string_like($params['ipaddress']) . "%'";
			}
			else
			{
				// exact match
				$ipaddress_match = "ipaddress = '" . $db->escape_string($params['ipaddress']) . "'";
			}

			/* insert sql */
			$sql = "
								SELECT userid, username, ipaddress
								FROM " . TABLE_PREFIX . "user AS user
								WHERE $ipaddress_match AND
										ipaddress <> '' AND
										userid <> $params[prevuserid]
								ORDER BY username
						";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function userSearchIPUsage($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['ipaddress']) AND isset($params['prevuserid']);
		}
		else
		{
			if (substr($params['ipaddress'], -1) == '.' OR substr_count($params['ipaddress'], '.') < 3)
			{
				// ends in a dot OR less than 3 dots in IP -> partial search
				$ipaddress_match = "node.ipaddress LIKE '" . $db->escape_string_like($params['ipaddress']) . "%'";
			}
			else
			{
				// exact match
				$ipaddress_match = "node.ipaddress = '" . $db->escape_string($params['ipaddress']) . "'";
			}

			/* insert sql */
			$sql = "
				SELECT DISTINCT user.userid, user.username, node.ipaddress
				FROM " . TABLE_PREFIX . "node AS node,
					" . TABLE_PREFIX . "user AS user
				WHERE user.userid = node.userid AND
					$ipaddress_match AND
				node.ipaddress <> '' AND
					user.userid <> " . intval($params['prevuserid']) . "
				ORDER BY user.username
			";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function userReferrers($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['startdate']) AND !empty($params['enddate']);
		}
		else
		{
			require_once(DIR . '/includes/functions_misc.php');
			if ($params['startdate']['month'])
			{
				$params['startdate'] = vbmktime(intval($params['startdate']['hour']), intval($params['startdate']['minute']), 0, intval($params['startdate']['month']), intval($params['startdate']['day']), intval($params['startdate']['year']));
				$datequery = " AND users.joindate >= " . $params['startdate'];
			}
			else
			{
				$params['startdate'] = 0;
			}

			if ($params['enddate']['month'])
			{
				$params['enddate'] = vbmktime(intval($params['enddate']['hour']), intval($params['enddate']['minute']), 0, intval($params['enddate']['month']), intval($params['enddate']['day']), intval($params['enddate']['year']));
				$datequery .= " AND users.joindate <= " . $params['enddate'];
			}
			else
			{
				$params['enddate'] = 0;
			}


			/* insert sql */
			$sql = "
								SELECT COUNT(*) AS count, user.username, user.userid
								FROM " . TABLE_PREFIX . "user AS users
								INNER JOIN " . TABLE_PREFIX . "user AS user ON(users.referrerid = user.userid)
								WHERE users.referrerid <> 0
										AND users.usergroupid NOT IN (3,4)
										$datequery
								GROUP BY users.referrerid
								ORDER BY count DESC, username ASC
						";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function fetchPhraseInfo($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['languageId']);
		}
		else
		{
			$sql = "
					SELECT languageid" . $params['languageFields'] . "
					FROM " . TABLE_PREFIX . "language
					WHERE languageid = " . intval($params['languageId']);

			$resultClass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultClass($db, $sql);
		}
		return $result;
	}

	// find all groups allowed to be invisible - don't change people with those as secondary groups
	public function updateInvisible($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['caninvisible']) AND !empty($params['invisible']) AND !empty($params['usergroupid']);
		}
		else
		{
			$invisible_groups = '';
			$invisible_sql = $db->query_read("
				SELECT usergroupid
				FROM " . TABLE_PREFIX . "usergroup
				WHERE genericpermissions & " . $params['caninvisible']
			);
			while ($invisible_group = $db->fetch_array($invisible_sql))
			{
				$invisible_groups .= "\nAND NOT FIND_IN_SET($invisible_group[usergroupid], membergroupids)";
			}

			$sql ="
				UPDATE " . TABLE_PREFIX . "user
				SET options = (options & ~" . $params['invisible'] . ")
				WHERE usergroupid = " . $params['usergroupid'] . "
					$invisible_groups
			";


			return $db->query_write($sql);
		}
	}

	public function disableProducts($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['products']);
		}
		else
		{
			$reason = $params['reason'];
			$products = $params['products'];

			if ($reason)
			{
				$reason = $db->escape_string($reason) . ' ';
			}

			$products = array_map(array($db, 'escape_string'), $products);
			$list = "'" . implode("','", $products) . "'";

			$sql ="
				UPDATE " . TABLE_PREFIX . "product
				SET active = 0,
				description = CONCAT($reason, description)
				WHERE productid IN ($list) AND active = 1
			";

			return $db->query_write($sql);
		}
	}

	public function updateMemberForDeletedUsergroup($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['users']) AND !empty($params['usergroupid']);
		}
		else
		{
			$casesql = '';
			foreach ($params['users'] as $user)
			{
				$membergroups = fetch_membergroupids_array($user, false);
				foreach($membergroups AS $key => $val)
				{
					if ($val == $params['usergroupid'])
					{
						unset($membergroups["$key"]);
					}
				}
				$user['membergroupids'] = implode(',', $membergroups);
				$casesql .= "WHEN $user[userid] THEN '$user[membergroupids]' ";
				$updateusers[] = $user['userid'];
			}

			// do a big update to get rid of this usergroup from matched members' membergroupids
			$sql = "
				UPDATE " . TABLE_PREFIX . "user SET
				membergroupids = CASE userid
				$casesql
				ELSE '' END
				WHERE userid IN(" . implode(',', $updateusers) . ")
			";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function fetchPromotions($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['usergroupid']);
		}
		else
		{
			$sql = "
				SELECT userpromotion.*, joinusergroup.title
				FROM " . TABLE_PREFIX . "userpromotion AS userpromotion
				LEFT JOIN " . TABLE_PREFIX . "usergroup AS joinusergroup ON (userpromotion.joinusergroupid = joinusergroup.usergroupid)
				" . ($params['usergroupid']?"WHERE userpromotion.usergroupid = " . $params['usergroupid']:'');

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function deleteOrphans($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['del']);
		}
		else
		{
			$delcondition = array();

			foreach ($params['del'] AS $key)
			{
				fetch_varname_fieldname($key, $varname, $fieldname);
				$delcondition[] = "(varname = '" . $db->escape_string($varname) . "' AND fieldname = '" . $db->escape_string($fieldname) . "')";
			}

			$sql = "
				DELETE FROM " . TABLE_PREFIX . "phrase
				WHERE " . implode("\nOR ", $delcondition);

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function keepOrphans($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['keep']);
		}
		else
		{
			$insertsql = array();

			$phrases = $db->query_read("
				SELECT *
				FROM " . TABLE_PREFIX . "phrase
				WHERE phraseid IN(" . implode(', ', $params['keep']) . ")
			");
			while ($phrase = $db->fetch_array($phrases))
			{
				$insertsql[] = "
					(0,
					'" . $db->escape_string($phrase['fieldname']) . "',
					'" . $db->escape_string($phrase['varname']) . "',
					'" . $db->escape_string($phrase['text']) . "',
					'" . $db->escape_string($phrase['product']) . "',
					'" . $db->escape_string($phrase['username']) . "',
					$phrase[dateline],
					'" . $db->escape_string($phrase['version']) . "')
				";
			}

			$sql = "
				REPLACE INTO " . TABLE_PREFIX . "phrase
					(languageid, fieldname, varname, text, product, username, dateline, version)
				VALUES
					" . implode(', ', $insertsql);

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function searchPhrases($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['criteria']['searchstring']);
		}
		else
		{
			$criteria = $params['criteria'];
			$vb5_config =& vB::getConfig();

			if ($criteria['exactmatch'])
			{
				$sql = ($criteria['casesensitive'] ? 'BINARY ' : '');

				switch($criteria['searchwhere'])
				{
					case 0: $sql .= "text = '" . $db->escape_string($criteria['searchstring']) . "'"; break;
					case 1: $sql .= "varname = '" . $db->escape_string($criteria['searchstring']) . "'"; break;
					case 10: $sql .= "(text = '" . $db->escape_string($criteria['searchstring']) . "' OR $sql varname = '" . $db->escape_string($criteria['searchstring']) . "')"; break;
					default: $sql .= '';
				}
			}
			else
			{
// 				$className = 'vB_Db_' . $this->db_type . '_QueryBuilder';
// 				$queryBuilder = new $className($db, false);
// 				switch($criteria['searchwhere'])
// 				{
// 					case 0:
// 						$sql = $queryBuilder->conditionsToFilter(array(
// 							array('field' => 'text', 'value' => $criteria['searchstring'], vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_INCLUDES)
// 						));
// 						break;
// 					case 1:
// 						$sql = $queryBuilder->conditionsToFilter(array(
// 							array('field' => 'varname', 'value' => $criteria['searchstring'], vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_INCLUDES)
// 						));
// 						break;
// 					case 10:
// 						$sql = '(' . $queryBuilder->conditionsToFilter(array(
// 								array('field' => 'text', 'value' => $criteria['searchstring'], vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_INCLUDES)
// 								)) .
// 							') OR (' .
// 							$queryBuilder->conditionsToFilter(array(
// 								array('field' => 'varname', 'value' => $criteria['searchstring'], vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_INCLUDES)
// 							));
// 						break;
// 					default: $sql = '';
// 				}

				$this->db = $db;
				switch($criteria['searchwhere'])
				{
					case 0: $sql = $this->fetch_field_like_sql($criteria['searchstring'], 'text', false, $criteria['casesensitive']); break;
					case 1: $sql = $this->fetch_field_like_sql($criteria['searchstring'], 'varname', true, $criteria['casesensitive']); break;
					case 10: $sql = '(' . $this->fetch_field_like_sql($criteria['searchstring'], 'text', false, $criteria['casesensitive']) . ' OR ' . $this->fetch_field_like_sql($criteria['searchstring'], 'varname', true, $criteria['casesensitive']) . ')'; break;
					default: $sql = '';
				}
			}

			if (!empty($criteria['phrasetype']) AND trim(implode($criteria['phrasetype'])) != '')
			{
				$phrasetype_sql = "'" . implode("', '", array_map(array(&$db, 'escape_string'), $criteria['phrasetype'])) . "'";
			}
			else
			{
				$phrasetype_sql = '';
			}

			if ($criteria['languageid'] == -10)
			{
				// query ALL languages
				if ($vb5_config['Misc']['debug'])
				{
					// searches all phrases
					$sql = "
						SELECT phrase.*, language.title
						FROM " . TABLE_PREFIX . "phrase AS phrase
						LEFT JOIN " . TABLE_PREFIX . "language AS language USING(languageid)
						WHERE $sql
						" . ($phrasetype_sql ? "AND phrase.fieldname IN($phrasetype_sql)" : "") . "
						" . ($criteria['product'] ? "AND phrase.product = '" . $db->escape_string($criteria['product']) . "'" : "") . "
						ORDER BY languageid DESC, fieldname DESC
					";
				}
				else
				{
					// searches all phrases that are in use. Translated master phrases will not be searched
					$sql = "
						SELECT IF (pcustom.fieldname IS NOT NULL, pcustom.fieldname, pmaster.fieldname) AS fieldname,
							IF (pcustom.varname IS NOT NULL, pcustom.varname, pmaster.varname) AS varname,
							IF (pcustom.languageid IS NOT NULL, pcustom.languageid, pmaster.languageid) AS languageid,
							IF (pcustom.text IS NOT NULL, pcustom.text, pmaster.text) AS text,
							language.title
						FROM " . TABLE_PREFIX . "language AS language
						INNER JOIN " . TABLE_PREFIX . "phrase AS pmaster ON
							(pmaster.languageid IN (-1, 0))
						LEFT JOIN " . TABLE_PREFIX . "phrase AS pcustom ON
							(pcustom.languageid = language.languageid AND pcustom.varname = pmaster.varname AND pcustom.fieldname = pmaster.fieldname)
						WHERE 1=1
							" . ($phrasetype_sql ? "AND pmaster.fieldname IN($phrasetype_sql)" : '') . "
							" . ($criteria['product'] ? "AND pmaster.product = '" . $db->escape_string($criteria['product']) . "'" : "") . "
						" . ($sql ? "HAVING $sql" : '') . "
						ORDER BY languageid DESC, fieldname DESC
					";
				}

			}
			else if ($criteria['languageid'] > 0 AND !$criteria['transonly'])
			{
				// query specific translation AND master/custom master languages
				$sql = "
					SELECT IF (pcustom.fieldname IS NOT NULL, pcustom.fieldname, pmaster.fieldname) AS fieldname,
						IF (pcustom.varname IS NOT NULL, pcustom.varname, pmaster.varname) AS varname,
						IF (pcustom.languageid IS NOT NULL, pcustom.languageid, pmaster.languageid) AS languageid,
						IF (pcustom.text IS NOT NULL, pcustom.text, pmaster.text) AS text,
						language.title
					FROM " . TABLE_PREFIX . "phrase AS pmaster
					LEFT JOIN " . TABLE_PREFIX . "phrase AS pcustom ON (pcustom.languageid = " . $criteria['languageid'] . " AND pcustom.varname = pmaster.varname)
					LEFT JOIN " . TABLE_PREFIX . "language AS language ON (pcustom.languageid = language.languageid)
					WHERE pmaster.languageid IN (-1, 0)
					" . ($phrasetype_sql ? "AND pmaster.fieldname IN($phrasetype_sql)" : '') . "
					" . ($criteria['product'] ? "AND pmaster.product = '" . $db->escape_string($criteria['product']) . "'" : "") . "
					" . ($sql ? "HAVING $sql" : '') . "
					ORDER BY languageid DESC, fieldname DESC
				";
			}
			else
			{
				// query ONLY specific language
				$sql = "
					SELECT phrase.*, language.title
					FROM " . TABLE_PREFIX . "phrase AS phrase
					LEFT JOIN " . TABLE_PREFIX . "language AS language USING(languageid)
					WHERE $sql
					" . ($phrasetype_sql ? "AND phrase.fieldname IN($phrasetype_sql)" : '') . "
					" . ($criteria['product'] ? "AND phrase.product = '" . $db->escape_string($criteria['product']) . "'" : "") . "
					AND phrase.languageid = " . $criteria['languageid'] . "
					ORDER BY fieldname DESC
				";
			}
			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function deleteOldPhrases($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['varname']) AND !empty($params['fieldname']);
		}
		else
		{
			$sql = "
				DELETE FROM " . TABLE_PREFIX . "phrase
				WHERE varname = '" . $db->escape_string($params['varname']) . "' AND
						fieldname = '" . $db->escape_string($params['fieldname']) . "'
				" . ($params['t'] ? " AND languageid NOT IN(-1,0)" : "") . "
				" . (!$params['debug'] ? ' AND languageid <> -1' : '') . "
			";

			$result = $db->query_write($sql);
		}
	}

	public function fetchLanguages($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['baseonly']) AND !empty($params['direction']);
		}
		else
		{
			$sql = "
				SELECT languageid, title
				" . iif($params['baseonly'] == false, ', userselect, options, languagecode, charset, imagesoverride, dateoverride, timeoverride, registereddateoverride,
					calformat1override, calformat2override, logdateoverride, decimalsep, thousandsep, locale,
					IF(options & ' . $params['direction'] . ', \'ltr\', \'rtl\') AS direction'
				) . "
				FROM " . TABLE_PREFIX . "language
				" . ((!empty($params['languageid'])) ? 'WHERE languageid = ' . intval($params['languageid']) : 'ORDER BY title')
			;

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function fetchPhrasesForExport($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['languageid']) AND !empty($params['product']);
		}
		else
		{
			$sql = "
				SELECT phrase.varname, phrase.text, phrase.fieldname, phrase.languageid,
					phrase.username, phrase.dateline, phrase.version
					" . (($params['languageid'] != -1) ? ", IF(ISNULL(phrase2.phraseid), 1, 0) AS iscustom" : "") . "
				FROM " . TABLE_PREFIX . "phrase AS phrase
				" . (($params['languageid'] != -1) ? "LEFT JOIN " . TABLE_PREFIX . "phrase AS phrase2 ON (phrase.varname = phrase2.varname AND phrase2.languageid = -1 AND phrase.fieldname = phrase2.fieldname)" : "") . "
				WHERE phrase.languageid IN (" . $params['languageid'] . ($params['custom'] ? ", 0" : "") . ")
					AND (phrase.product = '" . $db->escape_string($params['product']) . "'" .
					iif($params['product'] == 'vbulletin', " OR phrase.product = ''") . ")
					" . (($params['languageid'] == -1 AND !empty($params['default_skipped_groups'])) ? "AND fieldname NOT IN ('" . implode("', '", $params['default_skipped_groups']) . "')" : '') . "
				ORDER BY phrase.languageid, phrase.fieldname, phrase.varname
			";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function updateLanguagePhrases($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['languageid']) AND !empty($params['def']) AND !empty($params['fieldname']);
		}
		else
		{
			$sql = array();

			require_once(DIR . '/includes/adminfunctions.php');
			$full_product_info = fetch_product_list(true);
			$userinfo = vB::getCurrentSession()->fetch_userinfo();

			foreach (array_keys($params['def']) AS $varname)
			{
				$defphrase =& $params['def']["$varname"];
				$newphrase =& $params['phr']["$varname"];
				$product	=& $params['prod']["$varname"];
				$product_version = $full_product_info["$product"]['version'];

				if ($newphrase != $defphrase)
				{
					$sql[] = "
						(" . $params['languageid'] . ",
						'" . $db->escape_string($params['fieldname']) . "',
						'" . $db->escape_string($varname) . "',
						'" . $db->escape_string($newphrase) . "',
						'" . $db->escape_string($product) . "',
						'" . $db->escape_string($userinfo['username']) . "',
						" . TIMENOW . ",
						'" . $db->escape_string($product_version) . "')
					";
				}
			}


			if (!empty($sql))
			{
				$query = "
					### UPDATE CHANGED PHRASES FROM LANGUAGE:" . $vbulletin->GPC['dolanguageid'] . ", PHRASETYPE:" . $vbulletin->GPC['fieldname'] . " ###
					REPLACE INTO " . TABLE_PREFIX . "phrase
						(languageid, fieldname, varname, text, product, username, dateline, version)
					VALUES
						" . implode(",\n\t\t\t\t", $sql) . "
				";

				$resultclass = 'vB_dB_' . $this->db_type . '_result';
				$result = new $resultclass($db, $query);
				return $result;
			}
		}
	}

	public function updateCronEnabled($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['updates']);
		}
		else
		{
			$cases = '';
			foreach ($params['updates'] AS $varname => $status)
			{
				$cases .= "WHEN '" . $db->escape_string($varname) . "' THEN $status ";
			}

			$sql = "
				UPDATE " . TABLE_PREFIX . "cron SET active = CASE varname $cases ELSE active END
			";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function fetchCronLogCount($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['varname']);
		}
		else
		{
			$sqlconds = '';
			if (!empty($params['varname']))
			{
				$sqlconds = "WHERE cronlog.varname = '" . $db->escape_string($params['varname']) . "'";
			}

			$sql = "
				SELECT COUNT(*) AS total
				FROM " . TABLE_PREFIX . "cronlog AS cronlog
				$sqlconds
			";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function fetchCronLog($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['varname']) AND !empty($params[vB_dB_Query::PARAM_LIMIT]);
		}
		else
		{
			$sqlconds = '';
			if (!empty($params['varname']))
			{
				$sqlconds = "WHERE cronlog.varname = '" . $db->escape_string($params['varname']) . "'";
			}

			if (empty($params[vB_dB_Query::PARAM_LIMITPAGE]))
			{
				$params[vB_dB_Query::PARAM_LIMITPAGE] = 1;
			}

			$startat = ($params[vB_dB_Query::PARAM_LIMITPAGE] - 1) * $params[vB_dB_Query::PARAM_LIMIT];

			switch ($params['orderby'])
			{
				case 'action':
					$order = 'cronlog.varname ASC, cronlog.dateline DESC';
					break;

				case 'date':
				default:
					$order = 'cronlog.dateline DESC';
			}

			$sql = "
				SELECT cronlog.*
				FROM " . TABLE_PREFIX . "cronlog AS cronlog
				LEFT JOIN " . TABLE_PREFIX . "cron AS cron ON (cronlog.varname = cron.varname)
				$sqlconds
				ORDER BY $order
				LIMIT $startat, " . $params[vB_dB_Query::PARAM_LIMIT]
			;

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function pruneCronLog($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['varname']) AND !empty($params['datecut']);
		}
		else
		{
			$sqlconds = '';
			if (!empty($params['varname']))
			{
				$sqlconds = "WHERE cronlog.varname = '" . $db->escape_string($params['varname']) . "'";
			}

			$sql = "
				DELETE FROM " . TABLE_PREFIX . "cronlog
				WHERE dateline < " . $params['datecut'] . "
					$sqlconds
			";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function fetchWolAllUsers($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$avatarenabled = vB::getDatastore()->getOption('avatarenabled');
			$sortorder = (isset($params['sortorder']) AND $params['sortorder'] == 'desc') ? 'desc' : 'asc';

			$sqlsort = 'user.username';
			if (isset($params['sortfield']))
			{
				switch ($params['sortfield'])
				{
					case 'location':
						$sqlsort = 'session.location';
						break;
					case 'time':
						$sqlsort = 'session.lastactivity';
						break;
					case 'host':
						$sqlsort = 'session.host';
						break;
					case 'posts':
						$sqlsort = 'user.posts';
						break;
					case 'username':
						$sqlsort = 'user.username';
						break;
					default:
						$sqlsort = 'user.username';
				}
			}

			$sqlsort .= ' ' . $sortorder;

			if (isset($params['sortfield']) AND $params['sortfield'] != 'time')
			{
				$sqlsort .= ', session.lastactivity DESC';
			}

			$wheresql = (isset($params['who']) AND $params['who'] == 'members') ? ' WHERE session.userid > 0' : '';

			if (isset($params['pagekey']) AND !empty($params['pagekey']))
			{
				$wheresql = ($wheresql == '' ?  "WHERE session.pagekey = '" . $db->escape_string($params['pagekey']) . "'" : " AND session.pagekey = '" . $db->escape_string($params['pagekey']) . "'");
			}

			$perpage = (isset($params[vB_dB_Query::PARAM_LIMIT])) ? intval($params[vB_dB_Query::PARAM_LIMIT]) : 0;

			if ($perpage == 0)
			{
				$perpage = 200;
			}
			else if ($perpage < 1)
			{
				$perpage = 1;
			}

			if (empty($params['pagenumber']))
			{
				$params['pagenumber'] = 1;
			}

			$limitlower = ($params['pagenumber'] - 1) * $perpage;
			$limitupper = $perpage;

			$sql = "
				SELECT user.username, user.usergroupid AS usergroupid, session.useragent, session.wol, session.lastactivity, session.location,
					session.userid, user.options, user.posts, user.joindate, user.reputationlevelid, user.reputation,
					session.host, session.badlocation, session.incalendar, session.inthread,
					user.aim, user.icq, user.msn, user.yahoo, user.skype,
				IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid, user.usergroupid
				" . ($avatarenabled ? ', avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline, customavatar.width AS avwidth, customavatar.height AS avheight, customavatar.height_thumb AS avheight_thumb, customavatar.width_thumb AS avwidth_thumb' : ''). "
				FROM " . TABLE_PREFIX . "session AS session
				" . (($params['who'] == 'guest' OR !$params['who']) ? "LEFT JOIN" : "INNER JOIN") . " " . TABLE_PREFIX . "user AS user ON session.userid = user.userid" ."
				" . ($avatarenabled ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON (avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON (customavatar.userid = user.userid) " : '') . "
				$wheresql
				ORDER BY $sqlsort
				LIMIT $limitlower, $limitupper
			";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}


	public function fetchWol($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['userid']);
		}
		else
		{
			$vboptions = vB::getDatastore()->get_value('options');
			$datecut = vB::getRequest()->getTimeNow() - $vboptions['cookietimeout'];
			$params['userid'] = intval($params['userid']);

			$sql = "
				SELECT user.username, session.useragent, session.location, session.lastactivity,
					user.userid, user.options,
					session.host, session.badlocation, session.incalendar, session.inthread,
					user.aim, user.icq, user.msn, user.yahoo, user.skype,
				IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid, user.usergroupid
				FROM " . TABLE_PREFIX . "session AS session
				". ", " . TABLE_PREFIX . "user AS user" ."
				WHERE session.lastactivity > $datecut
					AND session.userid = $params[userid]
				ORDER BY lastactivity DESC
			";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function fetchWolCount($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$vboptions = vB::getDatastore()->get_value('options');
			$datecut = vB::getRequest()->getTimeNow() - $vboptions['cookietimeout'];

			$idsql = '';
			if (!empty($params['pagekey']))
			{
				$idsql = " AND session.pagekey = '" . $db->escape_string($params['pagekey']) . "'";
			}

			switch ($params['who'])
			{
				case 'members':
					$whosql = ' AND session.userid > 0';
					break;
				case 'guests':
					$whosql = ' AND session.userid = 0';
					break;
				default:
					$whosql = '';
			}

			$sql = "
				SELECT COUNT(DISTINCT session.userid)
				FROM " . TABLE_PREFIX . "session AS session
				WHERE session.lastactivity > $datecut
					$idsql
					$whosql
			";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function fetchTagsForCloud($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params[vB_dB_Query::PARAM_LIMIT]);
		}
		else
		{
			$sql = "
				SELECT tagnode.tagid, tag.tagtext, COUNT(*) AS searchcount
				FROM " . TABLE_PREFIX . "tagnode AS tagnode
				INNER JOIN " . TABLE_PREFIX . "tag AS tag ON (tagnode.tagid = tag.tagid)
				GROUP BY tagnode.tagid, tag.tagtext
				ORDER BY searchcount DESC
				LIMIT " . intval($params[vB_dB_Query::PARAM_LIMIT])
			;

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function fetchSearchTagsForCloud($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params[vB_dB_Query::PARAM_LIMIT]);
		}
		else
		{
			$options = vB::getDatastore()->get_value('options');
			$timenow = vB::getRequest()->getTimeNow();
			$sql = "
				SELECT tagsearch.tagid, tag.tagtext, COUNT(*) AS searchcount
				FROM " . TABLE_PREFIX . "tagsearch AS tagsearch
				INNER JOIN " . TABLE_PREFIX . "tag AS tag ON (tagsearch.tagid = tag.tagid)
				" . ($options['tagcloud_searchhistory'] ?
					"WHERE tagsearch.dateline > " . ($timenow - (60 * 60 * 24 * $options['tagcloud_searchhistory'])) :
					'') . "
				GROUP BY tagsearch.tagid, tag.tagtext
				ORDER BY searchcount DESC
				LIMIT " . intval($params[vB_dB_Query::PARAM_LIMIT])
			;
			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function newAccessMask($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['newmask']);
		}
		$sql = array();

		foreach ($params['newmask'] as $newmask)
		{
			$sql[] = "
				(" . $newmask['userid'] . ",
				" . $newmask['nodeid'] . ",
				" . $newmask['accessmask'] . ")
			";
		}

		if (!empty($sql))
		{
			$query = "
				REPLACE INTO " . TABLE_PREFIX . "access
					(userid, nodeid, accessmask)
				VALUES
					" . implode(",\n\t\t\t\t", $sql) . "
			";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $query);
			return $result;
		}
	}

	public function fetchAccessMasksForChannel($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['nodeid']);
		}
		else
		{
			$where_and = "";
			if (!empty($params['accessmask']))
			{
				$where_and = " AND accessmask='" . $db->escape_string($params['accessmask']) . "'";
			}
			$sql = "
				SELECT access.*, user.userid, user.username
				FROM " . TABLE_PREFIX . "access AS access
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = access.userid
				WHERE nodeid = " . $params['nodeid'] . $where_and . "
				ORDER BY user.username"
			;
			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function fetchCountInfractionsByCond($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['status']);
		}
		else
		{
			$condition = " 1 = 1";
			if ($params['whoadded'])
			{
				$condition .= " AND infraction.whoadded = " . $params['whoadded'];
			}
			if ($params['userid'])
			{
				$condition .= " AND infraction.userid = " . $params['userid'];
			}
			if ($params['start'])
			{
				$condition .= " AND infraction.dateline >= " . $params['start'];
			}
			if ($params['end'])
			{
				$condition .= " AND infraction.dateline <= " . $params['end'];
			}
			if ($params['infractionlevelid'] != -1)
			{
				$condition .= " AND infraction.infractionlevelid = " . intval($params['infractionlevelid']);
			}

			switch ($params['status'])
			{
				case 'active': $condition .= " AND action = 0"; break;
				case 'expired': $condition .= " AND action = 1"; break;
				case 'reversed': $condition .= " AND action = 2"; break;
			}
			$sql = "SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "infraction AS infraction WHERE" . $condition;
			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function fetchInfractionsByCondLimit($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['status']);
		}
		else
		{
			$condition = "1 = 1";
			if ($params['whoadded'])
			{
				$condition .= " AND infraction.whoadded = " . $params['whoadded'];
			}
			if ($params['userid'])
			{
				$condition .= " AND infraction.userid = " . $params['userid'];
			}
			if ($params['start'])
			{
				$condition .= " AND infraction.dateline >= " . $params['start'];
			}
			if ($params['end'])
			{
				$condition .= " AND infraction.dateline <= " . $params['end'];
			}
			if ($params['infractionlevelid'] != -1)
			{
				$condition .= " AND infraction.infractionlevelid = " . intval($params['infractionlevelid']);
			}

			switch ($params['status'])
			{
				case 'active': $condition .= " AND action = 0"; break;
				case 'expired': $condition .= " AND action = 1"; break;
				case 'reversed': $condition .= " AND action = 2"; break;
			}

			switch($params['orderby'])
			{
				case 'points':		$orderby = 'points DESC'; break;
				case 'expires':		$orderby = 'action, expires'; break;
				case 'username':		$orderby = 'node.authorname'; break;
				case 'leftby_username': $orderby = 'leftby_username'; break;
				default: $orderby = 'infraction.dateline DESC';
			}

			$sql = "SELECT infraction.*, user2.username, user.username AS leftby_username,
				IF(ISNULL(node.nodeid) AND infraction.nodeid != 0, 1, 0) AS postdeleted, node.parentid AS postthreadid
				FROM " . TABLE_PREFIX . "infraction AS infraction
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (infraction.whoadded = user.userid)
				LEFT JOIN " . TABLE_PREFIX . "user AS user2 ON (infraction.userid = user2.userid)
				LEFT JOIN " . TABLE_PREFIX . "node AS node ON (infraction.nodeid = node.nodeid)
				WHERE $condition
				ORDER BY $orderby
				LIMIT " . $params[vB_dB_Query::PARAM_LIMITSTART] . ", " . $params[vB_dB_Query::PARAM_LIMIT];
			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}
/**
 * "Magic" Function that builds all the information regarding infractions
 * (only used in Cron)
 *
 * @param	array	Infraction Points Array
 * @param	array	Infractions Array
 * @param	array	Warnings Array
 *
 * @return	boolean	Whether infractions info was updated.
 *
 */

	public function buildUserInfractions($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			if (!isset($params['points']) OR !isset($params['infractions']) OR !isset($params['warnings']))
			{
				return false;
			}
			return true;
		}
		$warningsql = array();
		$infractionsql = array();
		$ipointssql = array();
		$querysql = array();
		$userids = array();

		// ############################ WARNINGS #################################
		$wa = array();
		foreach($params['warnings'] AS $userid => $warning)
		{
			$wa["$warning"][] = $userid;
			$userids["$userid"] = $userid;
		}
		unset($params['warnings']);

		foreach($wa AS $warning => $users)
		{
			$warningsql[] = "WHEN userid IN(" . implode(', ', $users) . ") THEN $warning";
		}
		unset($wa);
		if (!empty($warningsql))
		{
			$querysql[] = "
			warnings = CAST(warnings AS SIGNED) -
			CASE
				" . implode(" \r\n", $warningsql) . "
			ELSE 0
			END";
		}
		unset($warningsql);

		// ############################ INFRACTIONS ##############################
		$if = array();
		foreach($params['infractions'] AS $userid => $infraction)
		{
			$if["$infraction"][] = $userid;
			$userids["$userid"] = $userid;
		}
		unset($params['infractions']);
		foreach($if AS $infraction => $users)
		{
			$infractionsql[] = "WHEN userid IN(" . implode(', ', $users) . ") THEN $infraction";
		}
		unset($if);
		if (!empty($infractionsql))
		{
			$querysql[] = "
			infractions = CAST(infractions AS SIGNED) -
			CASE
				" . implode(" \r\n", $infractionsql) . "
			ELSE 0
			END";
		}
		unset($infractionsql);

		// ############################ POINTS ###################################
		$ip = array();
		foreach($params['points'] AS $userid => $point)
		{
			$ip["$point"][] = $userid;
		}
		unset($params['points']);
		foreach($ip AS $point => $users)
		{
			$ipointssql[] = "WHEN userid IN(" . implode(', ', $users) . ") THEN $point";
		}
		unset($ip);
		if (!empty($ipointssql))
		{
			$querysql[] = "
			ipoints = CAST(ipoints AS SIGNED) -
			CASE
				" . implode(" \r\n", $ipointssql) . "
			ELSE 0
			END";
		}
		unset($ipointssql);

		if (!empty($querysql))
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "user
				SET " . implode(', ', $querysql) . "
				WHERE userid IN (" . implode(', ', $userids) . ")
			");

			return true;
		}
		else
		{
			return false;
		}
	}

	public function fetchUsersInfractionGroups($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['override_groupid']);
		}
		$sql = "SELECT userid
			FROM " . TABLE_PREFIX . "user
			WHERE FIND_IN_SET('" . intval($params['override_groupid']) . "', infractiongroupids)";
		if (isset($params['point_level']))
		{
			$sql .= "\n OR (ipoints >= " . intval($params['point_level']);
			if (isset($params['point_level']))
			{
				$sql .= " AND usergroupid = " . intval($params['applies_groupid']);
			}
			$sql .= ')';
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;

	}

	public function fetchModlogCount($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}

		if ($params['userid'] OR $params['modaction'])
		{
			if ($params['userid'])
			{
				$sqlconds[] = "moderatorlog.userid = " . intval($params['userid']);
			}
			if ($params['modaction'])
			{
				$sqlconds[] = "moderatorlog.action LIKE '%" . $vbulletin->db->escape_string_like($params['modaction']) . "%'";
			}
		}

		if ($params['startdate'])
		{
			$sqlconds[] = "moderatorlog.dateline >= " . intval($params['startdate']);
		}

		if ($params['enddate'])
		{
			$sqlconds[] = "moderatorlog.dateline <= " . intval($params['enddate']);
		}

		if ($params['product'])
		{
			if ($params['product'] == 'vbulletin')
			{
				$sqlconds[] = "moderatorlog.product IN ('', 'vbulletin')";
			}
			else
			{
				$sqlconds[] = "moderatorlog.product = '" . $vbulletin->db->escape_string($params['product']) . "'";
			}
		}
	/** @todo call hook */
	// Legacy Hook 'admin_modlogviewer_query' Removed //

		$sql = "
			SELECT COUNT(*) AS total
			FROM " . TABLE_PREFIX . "moderatorlog AS moderatorlog
			" . (!empty($sqlconds) ? "WHERE " . implode("\r\n\tAND ", $sqlconds) : "") . "
		";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	public function fetchModlogs($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}

		if(empty($params[vB_dB_Query::PARAM_LIMIT]))
		{
			$params[vB_dB_Query::PARAM_LIMIT] = intval($params['perpage']);
		}

		if ($params['userid'] OR $params['modaction'])
		{
			if ($params['userid'])
			{
				$sqlconds[] = "moderatorlog.userid = " . intval($params['userid']);
			}
			if ($params['modaction'])
			{
				$sqlconds[] = "moderatorlog.action LIKE '%" . $db->escape_string_like($params['modaction']) . "%'";
			}
		}

		if ($params['startdate'])
		{
			$sqlconds[] = "moderatorlog.dateline >= " . intval($params['startdate']);
		}

		if ($params['enddate'])
		{
			$sqlconds[] = "moderatorlog.dateline <= " . intval($params['enddate']);
		}

		if ($params['product'])
		{
			if ($params['product'] == 'vbulletin')
			{
				$sqlconds[] = "moderatorlog.product IN ('', 'vbulletin')";
			}
			else
			{
				$sqlconds[] = "moderatorlog.product = '" . $db->escape_string($params['product']) . "'";
			}
		}

	// Legacy Hook 'admin_modlogviewer_query' Removed //

		$startat = ($params['pagenumber'] - 1) * intval($params[vB_dB_Query::PARAM_LIMIT]);

		switch($params['orderby'])
		{
			case 'user':
				$order = 'username ASC, dateline DESC';
				break;
			case 'modaction':
				$order = 'action ASC, dateline DESC';
				break;
			case 'date':
			default:
				$order = 'dateline DESC';
		}
		$sql = "
				SELECT moderatorlog.*, user.username,
				node.title AS node_title
				FROM " . TABLE_PREFIX . "moderatorlog AS moderatorlog
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderatorlog.userid)
				LEFT JOIN " . TABLE_PREFIX . "node AS node ON (node.nodeid = moderatorlog.nodeid)
				" . (!empty($sqlconds) ? "WHERE " . implode("\r\n\tAND ", $sqlconds) : "") . "
				ORDER BY $order
				LIMIT $startat, " . intval($params[vB_dB_Query::PARAM_LIMIT]) . "
				";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	public function getModLogsByConds($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['conds']);
		}
		$className = 'vB_Db_' . $this->db_type . '_QueryBuilder';
		$queryBuilder = new $className($db, false);
		$where = $queryBuilder->conditionsToFilter($params['conds']);

		$sql = "
			SELECT COUNT(*) AS total
			FROM " . TABLE_PREFIX ."moderatorlog
			WHERE $where
		";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	public function fetchApiLogs($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}

		$sqlconds = $this->fetchApiLogsSqlconds($params, $db);

		switch ($params['orderby'])
		{
			case 'user':
				$order = 'user.username ASC, apilog.apilogid DESC';
				break;
			case 'clientname':
				$order = 'apiclient.clientname ASC, apilog.apiclientid ASC, apilog.apilogid DESC';
				break;
			default:	// Date
				$order = 'apilogid DESC';
		}

		$sql = "
			SELECT apilog.*, user.username, apiclient.clientname, apiclient.userid
			FROM " . TABLE_PREFIX . "apilog AS apilog
			LEFT JOIN " . TABLE_PREFIX . "apiclient AS apiclient ON (apiclient.apiclientid = apilog.apiclientid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (apiclient.userid = user.userid)
			$sqlconds
			ORDER BY $order
			LIMIT " . intval($params[vB_dB_Query::PARAM_LIMITSTART]) . ", " . intval($params[vB_dB_Query::PARAM_LIMIT]);

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;

	}

	public function fetchApiLogsCount($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}

		$sqlconds = $this->fetchApiLogsSqlconds($params, $db);

		$sql = "SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "apilog AS apilog
			LEFT JOIN " . TABLE_PREFIX . "apiclient AS apiclient ON (apiclient.apiclientid = apilog.apiclientid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (apiclient.userid = user.userid)
		$sqlconds";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;

	}

	protected function fetchApiLogsSqlconds($params, $db)
	{
		if ($params['userid'] >= 0 OR $params['apiclientid'] OR $params['apiclientuniqueid'] OR $params['apiclientname'] OR $params['startdate'] OR $params['enddate'])
		{
			$sqlconds = 'WHERE 1=1 ';
			if ($params['apiclientid'])
			{
				$sqlconds .= " AND apilog.apiclientid = " . intval($params['apiclientid']);
			}
			elseif ($params['apiclientuniqueid'])
			{
				$sqlconds .= " AND apiclient.uniqueid = '" . $db->escape_string($params['apiclientuniqueid']) . "'";
			}
			else
			{
				if ($params['userid'] >= 0)
				{
					$sqlconds .= " AND apiclient.userid = " . intval($params['userid']);
				}
				if ($params['apiclientname'])
				{
					$sqlconds .= " AND apiclient.clientname = '" . $db->escape_string($params['apiclientname']) . "'";
				}
			}
			if ($params['startdate'])
			{
				$sqlconds .= " AND apilog.dateline >= " . intval($params['startdate']);
			}
			if ($params['enddate'])
			{
				$sqlconds .= " AND apilog.dateline <= " . intval($params['enddate']);
			}
		}
		else
		{
			$sqlconds = '';
		}

		return $sqlconds;
	}


	public function fetchApiLogsCountDatecut($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['datecut']);
		}

		$sql = "SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "apilog AS apilog WHERE dateline < " . intval($params['datecut']);

		if ($params['apiclientid'])
		{
			$sql .= "\nAND apiclientid = " . intval($params['apiclientid']);
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;

	}


	public function fetchApiActivity($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['start_time']) AND !empty($params['end_time']);
		}

		switch ($params['sort'])
		{
			case 'date_asc':
				$orderby = 'dateline ASC';
				break;
			case 'date_desc':
				$orderby = 'dateline DESC';
				break;
			case 'total_asc':
				$orderby = 'total ASC';
				break;
			case 'total_desc':
				$orderby = 'total DESC';
				break;
			default:
				$orderby = 'dateline DESC';
		}

		switch ($params['scope'])
		{
			case 'weekly':
				$sqlformat = '%U %Y';
				break;
			case 'monthly':
				$sqlformat = '%m %Y';
				break;
			default:
				$sqlformat = '%w %U %m %Y';
				break;
		}

		$sql = "
			SELECT COUNT(*) AS total,
			DATE_FORMAT(from_unixtime(dateline), '$sqlformat') AS formatted_date,
			AVG(dateline) AS dateline
			FROM " . TABLE_PREFIX . "apilog
			WHERE dateline >= " . intval($params['start_time']) . "
				AND dateline <= " . intval($params['end_time']) . "
			GROUP BY formatted_date
			" . (empty($params['nullvalue']) ? " HAVING total > 0 " : "") . "
			ORDER BY $orderby
		";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;

	}
	public function fetchStylevarsArray($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['parentlist']);
		}
		$sortdir = empty($params['sortdir']) ? 'ASC' : $params['sortdir'];
		$clause = empty($params['stylevars']) ? '' : ' AND stylevar.stylevarid IN ("' . implode('", "', $params['stylevars']) . '")';
		$sql = "
		SELECT stylevardfn.*, stylevar.styleid AS stylevarstyleid, stylevar.value, stylevar.stylevarid
			FROM " . TABLE_PREFIX . "stylevar AS stylevar
			INNER JOIN " . TABLE_PREFIX . "stylevardfn AS stylevardfn ON(stylevar.stylevarid = stylevardfn.stylevarid)
			WHERE stylevar.styleid IN (" . implode(',', $params['parentlist']) . ") $clause
			ORDER by stylevar.stylevarid, stylevar.styleid $sortdir
		";
		$config = vB::getConfig();
		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	public function isFreeLock($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['table']);
		}

		$vb5_config =& vB::get_config();

		// Don't lock tables if we know we might get stuck with them locked (pconnect = true)
		// mysqli doesn't support pconnect! YAY!
		if (strtolower($vb5_config['Database']['dbtype']) != 'mysqli' AND $vb5_config['MasterServer']['usepconnect'])
		{
			return;
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, "SELECT IS_FREE_LOCK('" . TABLE_PREFIX . $params['table'] . "')");
		return $result;
	}

	public function getLock($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['table']);
		}

		$vb5_config =& vB::get_config();

		// Don't lock tables if we know we might get stuck with them locked (pconnect = true)
		// mysqli doesn't support pconnect! YAY!
		if (strtolower($vb5_config['Database']['dbtype']) != 'mysqli' AND $vb5_config['MasterServer']['usepconnect'])
		{
			return;
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, "SELECT GET_LOCK('" . TABLE_PREFIX . $params['table'] . "', 2)");
		return $result;
	}
	public function releaseLock($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['table']);
		}

		$vb5_config =& vB::get_config();

		// Don't lock tables if we know we might get stuck with them locked (pconnect = true)
		// mysqli doesn't support pconnect! YAY!
		if (strtolower($vb5_config['Database']['dbtype']) != 'mysqli' AND $vb5_config['MasterServer']['usepconnect'])
		{
			return;
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, "SELECT RELEASE_LOCK('" . TABLE_PREFIX . $params['table'] . "')");
		return $result;
	}

	/**
	* Lock tables
	*/

	public function lockTables($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['tablelist']) AND is_array($params['tablelist']);
		}

		$vb5_config =& vB::getConfig();

		// Don't lock tables if we know we might get stuck with them locked (pconnect = true)
		// mysqli doesn't support pconnect! YAY!
		if (strtolower($vb5_config['Database']['dbtype']) != 'mysqli' AND $vb5_config['MasterServer']['usepconnect'])
		{
			return;
		}

		$sql = '';
		foreach($params['tablelist'] AS $name => $type)
		{
			$sql .= (!empty($sql) ? ', ' : '') . TABLE_PREFIX . $name . " " . $type;
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, "LOCK TABLES $sql");
		return $result;

	}

	/**
	* Unlock tables
	*
	*/
	public function unlockTables($params, $db, $check_only = false)
	{
		# must be called from exec_shutdown as tables can get stuck locked if pconnects are enabled
		if ($check_only)
		{
			return true;
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, "UNLOCK TABLES");
		return $result;
	}

	public function fetchUsersWithBirthday($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['adminemail']) AND !empty($params['today']) AND !empty($params['usergroupids']);
		}

		$sql = "
		SELECT username, email, languageid
			FROM " . TABLE_PREFIX . "user
			WHERE birthday LIKE '" . $params['today'] . "-%' AND
			(options & " . $params['adminemail'] . ") AND
			usergroupid IN (" . implode(',', $params['usergroupids']) . ")
		";
		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	public function updateCron($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['updates']);
		}

		$cases = '';
		foreach ($params['updates'] AS $varname => $status)
		{
			$cases .= "WHEN '" . $db->escape_string($varname) . "' THEN $status ";
		}

		$sql = "
			UPDATE " . TABLE_PREFIX . "cron SET active = CASE varname $cases ELSE active END
		";
		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}
	/**
	* Fetch SQL clause for haystack LIKE needle
	*
	* @param	string	Needle
	* @param	string	Field to search (varname or text)
	* @param	boolean	Search field is binary?
	* @param	boolean	Do case-sensitive search?
	*
	* @return	string	'haystack LIKE needle' variant
	*/
	private function fetch_field_like_sql($searchstring, $field, $isbinary = false, $casesensitive = false)
	{
		if ($casesensitive)
		{
			return "BINARY $field LIKE '%" . $this->db->escape_string_like($searchstring) . "%'";
		}
		else if ($isbinary)
		{
			return "UPPER($field) LIKE UPPER('%" . $this->db->escape_string_like($searchstring) . "%')";
		}
		else
		{
			return "$field LIKE '%" . $this->db->escape_string_like($searchstring) . "%'";
		}
	}

	public function fetchPhrasesForDisplay($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['searchstring']) AND isset($params['languageid']);
		}
		$this->db = &$db;
		$phrases = $db->query_read("
			SELECT
			IF(pcust.phraseid IS NULL, pmast.phraseid, pcust.phraseid) AS phraseid,
			IF(pcust.phraseid IS NULL, pmast.text, pcust.text) AS xtext
			FROM " . TABLE_PREFIX . "phrase AS pmast
			LEFT JOIN " . TABLE_PREFIX . "phrase AS pcust ON (
					pcust.varname = pmast.varname AND
					pcust.fieldname = pmast.fieldname AND
					pcust.languageid = " . $params['languageid'] . "
			)
			WHERE pmast.languageid = -1
			HAVING " . $this->fetch_field_like_sql($params['searchstring'], 'xtext', false, true) . "
		");

		$phraseids = '0';

		while ($phrase = $db->fetch_array($phrases))
		{
			$phraseids .= ",$phrase[phraseid]";
		}

		$db->free_result($phrases);
		$sql = "
			SELECT phrase.*, language.title
			FROM " .TABLE_PREFIX . "phrase AS phrase
			LEFT JOIN " . TABLE_PREFIX . "language AS language USING(languageid)
			WHERE phrase.phraseid IN($phraseids)
		";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	public function fetchPhrases($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['languageid']) AND isset($params['type']);
		}
		$phrasetypeSQL = '';
		if (!empty($params['fieldname']))
		{
			$phrasetypeSQL = $params['fieldname'] == -1 ? 'AND special = 0' : ("AND p1.fieldname = '" . $db->escape_string($params['fieldname']) . "'");
		}

		$sql = "
		SELECT p1.varname AS p1var, p1.text AS default_text, p1.fieldname, IF(p1.languageid = -1, 'MASTER', 'USER') AS type,
		p2.phraseid, p2.varname AS p2var, p2.text, NOT ISNULL(p2.phraseid) AS found,
		p1.product
		FROM " . TABLE_PREFIX . "phrase AS p1
		LEFT JOIN " . TABLE_PREFIX . "phrasetype AS phrasetype ON (p1.fieldname = phrasetype.fieldname)
		LEFT JOIN " . TABLE_PREFIX . "phrase AS p2 ON (p2.varname = p1.varname AND p2.fieldname = p1.fieldname AND p2.languageid = $params[languageid])
		WHERE p1.languageid = $params[type] $phrasetypeSQL
		ORDER BY p1.varname
		";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}


	public function fetchKeepNames($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['keepnames']);
		}
		$keepnames= array();
		foreach ($params['keepnames'] as $value)
		{
		$keepnames[] = "\n\t\t\t\t\t(varname = '" . $db->escape_string($value['varname']) . "' AND fieldname = '" . $db->escape_string($value['fieldname']) . "')";
		}
		$sql = "
		SELECT *
		FROM " . TABLE_PREFIX . "phrase
		WHERE " . implode("\nOR ", $keepnames);

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	public function fetchCountPhrasesByLang($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
	}
		$sql = "SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "phrase AS phrase
		WHERE languageid IN(-1, 0)";
		if ($params['fieldname'])
		{
			$sql .= " AND fieldname = '" . $params['fieldname'] . "'";
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}


	public function fetchPhrasesOrderedPaged($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		$sql = "SELECT varname, fieldname FROM " . TABLE_PREFIX . "phrase AS phrase
		WHERE languageid IN(-1, 0)";

		if ($params['fieldname'])
		{
			$sql .= " AND fieldname = '" . $params['fieldname'] . "'";
		}
		$sql .= " ORDER BY fieldname, varname
		LIMIT " . $params[vB_dB_Query::PARAM_LIMITPAGE] * $params[vB_dB_Query::PARAM_LIMIT] . ", " . $params[vB_dB_Query::PARAM_LIMIT];

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}


	public function updatePhraseDefLanguage($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['product']) ? true : false ;
		}
		$sql = "UPDATE " . TABLE_PREFIX . "phrase SET languageid = -10
			WHERE languageid = -1
			AND (product = '" . $db->escape_string($params['product']) . "'";
		if ($params['product'] == 'vbulletin')
		{
			$sql .= " OR product = ''";
		}
		$sql .= ") ";
		if ($params['skipped_groups'])
		{
			$sql .= " AND " . TABLE_PREFIX . "phrase.fieldname NOT IN ('" . implode("', '", $params['skipped_groups']) . "')";
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}


	public function updatePhraseByProduct($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['product']) ? true : false ;
		}
		$sql = "UPDATE " . TABLE_PREFIX . "phrase, " . TABLE_PREFIX . "phrase AS phrase2
		SET " . TABLE_PREFIX . "phrase.languageid = -11
		WHERE " . TABLE_PREFIX . "phrase.languageid = " . $params['languageid'] . "
		AND (" . TABLE_PREFIX . "phrase.product = '" . $db->escape_string($params['product']) . "'";
		if ($params['product'] == 'vbulletin')
		{
			$sql .= " OR product = ''";
		}
		$sql .= ")
			AND (phrase2.product = '" . $db->escape_string($params['product']) . "'";
		if ($params['product'] == 'vbulletin')
		{
			$sql .= " OR phrase2.product = ''";
		}
		$sql .= ")
		AND " . TABLE_PREFIX . "phrase.varname = phrase2.varname
		AND phrase2.languageid = 0
		AND " . TABLE_PREFIX . "phrase.fieldname = phrase2.fieldname";
		if ($params['skipped_groups'])
		{
			$sql .= " AND " . TABLE_PREFIX . "phrase.fieldname NOT IN ('" . implode("', '", $params['skipped_groups']) . "')";
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}


	public function updatePhraseLanguage($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['product']) AND isset($params['languageid']);
		}
		$sql = "UPDATE " . TABLE_PREFIX . "phrase SET languageid = -10
		WHERE languageid = $params[languageid]
		AND (product = '" . $db->escape_string($params['product']) . "'";
		if ($params['product'] == 'vbulletin')
		{
			$sql .= " OR product = ''";
		}

		if ($params['skipped_groups'])
		{
			$sql .= " AND " . TABLE_PREFIX . "phrase.fieldname NOT IN ('" . implode("', '", $params['skipped_groups']) . "')";
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	/** Fetch list of users to prune
	*
	*	@param	mixed
	*	@param	mixed 	a db pointer
	*	@param	bool
	*
	*	@result	mixed
	*/
	public function fetchPruneUsers($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['usergroupid'])
			) ? true : false;
		}
		else
		{
			$sqlcond = array();
			if ($params['usergroupid'] != -1)
			{
				$sqlcond[] = "user.usergroupid = " . $params['usergroupid'];
			}
			if ($params['daysprune'])
			{
				$daysprune = intval(TIMENOW - $params['daysprune'] * 86400);
				if ($daysprune < 0)
				{
					//return array(false, 'no_users_matched_your_query');
					throw new vB_Exception_Database('no_users_matched_your_query ');
				}
				$sqlcond[] = "lastactivity < $daysprune";
			}
			if ($params['joindate']['month'] AND $params['joindate']['year'])
			{
				$joindateunix = mktime(0, 0, 0, $params['joindate']['month'], $params['joindate']['day'], $params['joindate']['year']);
				if ($joindateunix)
				{
					$sqlcond[] = "joindate < $joindateunix";
				}
			}
			if ($params['minposts'])
			{
				$sqlcond[] = "posts < " . $params['minposts'];
			}
			switch($params['order'])
			{
				case 'username':
					$orderby = 'ORDER BY username ASC';
					break;
				case 'email':
					$orderby = 'ORDER BY email ASC';
					break;
				case 'usergroup':
					$orderby = 'ORDER BY usergroup.title ASC';
					break;
				case 'posts':
					$orderby = 'ORDER BY posts DESC';
					break;
				case 'lastactivity':
					$orderby = 'ORDER BY lastactivity DESC';
					break;
				case 'joindate':
					$orderby = 'ORDER BY joindate DESC';
					break;
				default:
					$orderby = 'ORDER BY username ASC';
					break;
			}
			$sql = "
				SELECT DISTINCT
					user.userid, username, email, posts, lastactivity, joindate,
					user.usergroupid, moderator.moderatorid, usergroup.title
				FROM " . TABLE_PREFIX . "user AS user
				LEFT JOIN " . TABLE_PREFIX . "moderator AS moderator ON(moderator.userid = user.userid)
				LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON(usergroup.usergroupid = user.usergroupid)
					" . ($sqlcond ? "WHERE " . implode($sqlcond, " AND ") : '') . "
				GROUP BY user.userid $orderby";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$config = vB::getConfig();

			if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
			{
				echo "sql: $sql<br />\n";
			}

			$result = new $resultclass($db, $sql);
			return $result;
		}
	}
	public function replaceSetting($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			if (
					!isset($params['product']) OR
					!isset($params['varname']) OR
					!isset($params['grouptitle']) OR
					!isset($params['value']) OR
					!isset($params['datatype']) OR
					!isset($params['optioncode'])
			)
			{
				return false;
			}
			return true;
		}

		$fields = array('product', 'varname', 'grouptitle', 'value', 'optioncode', 'volatile');
		$values = array(
			"'" . $db->escape_string($params['product']) . "'",
			"'" . $db->escape_string($params['varname']) . "'",
			"'" . $db->escape_string($params['grouptitle']) . "'",
			"'" . $db->escape_string($params['value']) . "'",
			"'" . $db->escape_string($params['optioncode']) . "'",
			1
		);

		if (!empty($params['default_value']))
		{
			$fields[] = 'default_value';
			$values[] = "'" . $params['default_value'] . "'";
		}

		if (!empty($params['datatype']))
		{
				$fields[] = 'datatype';
				$values[] = "'" . $db->escape_string($params['datatype']) . "'";
		}

		$sql = "REPLACE INTO " . TABLE_PREFIX . "setting
		(" . implode(', ', $fields) .
		")VALUES(
		" . implode(', ', $values) .
		")";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}

		$result = new $resultclass($db, $sql);
		return $result;
	}

	public function replaceTemplates($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['querybits']);
		}

		$sql = "
		REPLACE INTO " . TABLE_PREFIX . "template
		(" . implode(', ', array_keys($params['querybits'][0])) . ")
		VALUES
		";
		$rows = array();
		foreach ($params['querybits'] as $querybit)
		{
			$values = array();
			foreach ($querybit as $val)
			{
				$values[] = "'" . $db->escape_string($val) ."'";
			}
			$rows[] = implode(", ", $values);
		}
		$sql .= "
			(" . implode("),
			(", $rows) . ")";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}

		return $db->query_write($sql);

	}

	public function fetchSubs2Del($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['transactionids']);
		}
		$sql = "
			SELECT paymentinfo.subscriptionsubid, subscription.subscriptionid, subscription.cost,
				paymentinfo.userid, paymentinfo.paymentinfoid, paymenttransaction.amount, paymenttransaction.transactionid,
				paymenttransaction.paymenttransactionid
			FROM " . TABLE_PREFIX . "paymenttransaction AS paymenttransaction
			INNER JOIN " . TABLE_PREFIX . "paymentinfo AS paymentinfo ON (paymentinfo.paymentinfoid = paymenttransaction.paymentinfoid)
			INNER JOIN " . TABLE_PREFIX . "subscription AS subscription ON (paymentinfo.subscriptionid = subscription.subscriptionid)
			INNER JOIN " . TABLE_PREFIX . "subscriptionlog AS subscriptionlog ON (subscriptionlog.subscriptionid = subscription.subscriptionid AND subscriptionlog.userid = paymentinfo.userid)
			WHERE transactionid IN ('" . implode("','", $params['transactionids']) . "')
				AND subscriptionlog.status = 1
				AND paymenttransaction.reversed = 0
			";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	public function fetchUsersSubscriptions($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['userid']) AND !empty($params['subscriptionid']) AND isset($params['avatarenabled']) AND isset($params['isnotbannedgroup']);
		}
		$sql = "
			SELECT user.*, subscriptionlog.pusergroupid, subscriptionlog.expirydate,
			IF (user.displaygroupid=0, user.usergroupid, user.displaygroupid) AS displaygroupid,
			IF (usergroup.genericoptions & " . $params['isnotbannedgroup'] . ", 0, 1) AS isbanned,
			userban.usergroupid AS busergroupid, userban.displaygroupid AS bandisplaygroupid
			" . (($params['avatarenabled'] AND $params['adminoption']) ? ",IF(avatar.avatarid = 0 AND NOT ISNULL(customavatar.userid), 1, 0) AS hascustomavatar" : "") . "
			" . (($params['adminoption']) ? ",NOT ISNULL(customprofilepic.userid) AS hasprofilepic" : "") . "
			FROM " . TABLE_PREFIX . "subscriptionlog AS subscriptionlog
			INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
			INNER JOIN " . TABLE_PREFIX . "usergroup AS usergroup USING (usergroupid)
			LEFT JOIN " . TABLE_PREFIX . "userban AS userban ON (userban.userid = user.userid)
			" . (($params['avatarenabled'] AND $params['adminoption']) ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			" . (($params[adminoption]) ? "LEFT JOIN " . TABLE_PREFIX . "customprofilepic AS customprofilepic ON (user.userid = customprofilepic.userid)" : "") . "
			WHERE subscriptionlog.userid = $params[userid] AND
				subscriptionlog.subscriptionid = $params[subscriptionid]
		";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	public function fetchUsersForPromotion($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['time']);
		}
		$sql = "
			SELECT user.joindate, user.userid, user.membergroupids, user.posts, user.reputation,
				user.usergroupid, user.displaygroupid, user.customtitle, user.username, user.ipoints,
				userpromotion.joinusergroupid, userpromotion.reputation AS jumpreputation, userpromotion.posts AS jumpposts,
				userpromotion.date AS jumpdate, userpromotion.type, userpromotion.strategy,
				usergroup.title, usergroup.usertitle AS ug_usertitle,
				usertextfield.rank
			FROM " . TABLE_PREFIX . "user AS user
			INNER JOIN " . TABLE_PREFIX . "userpromotion AS userpromotion ON (user.usergroupid = userpromotion.usergroupid)
			LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (userpromotion.joinusergroupid = usergroup.usergroupid)
			LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
			" . iif(VB_AREA != 'AdminCP', "WHERE user.lastactivity >= " . $params['time']);

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	public function updateUserInfractions($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['joinusergroupid']) AND isset($params['pointlevel']) AND isset($params['ids']);
		}

		foreach ($params['pointlevel'] AS $points => $info)
		{
			$sqlval[] = "WHEN ipoints >= $points THEN '$info[ids]'";
			$sql_id[] = "WHEN ipoints >= $points THEN $info[id]";
		}

		$sql = "
			UPDATE " . TABLE_PREFIX . "user
			SET displaygroupid = IF(displaygroupid = usergroupid, $params[joinusergroupid], displaygroupid),
			usergroupid = $params[joinusergroupid],

			infractiongroupid =
			" . (!empty($sql_id) ? "
			CASE
				" . implode(" \r\n", $sql_id) . "
			ELSE 0
			END" : "0") . "

			,infractiongroupids =
			" . (!empty($sqlval) ? "
			CASE
				" . implode(" \r\n", $sqlval) . "
			ELSE ''
			END" : "''") . "

			WHERE userid IN (0$params[ids])
		";

		return $db->query_write($sql);

	}

	public function updateSubscribeEvent($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['conditions']) AND isset($params['subscribeeventids']);
		}

		foreach ($params['conditions'] AS $subscribeeventid => $dateline_from)
		{
			$sql[] = " WHEN subscribeeventid = $subscribeeventid THEN $dateline_from ";
		}

		$sql = "
			UPDATE " . TABLE_PREFIX . "subscribeevent
			SET lastreminder =
			CASE
			" . implode(" \r\n", $sql) . "
			ELSE lastreminder
			END
			WHERE subscribeeventid IN (" . implode(', ', $params['subscribeeventids']) . ")
		";

		return $db->query_write($sql);

	}

	public function replaceValues($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['values']) AND isset($params['table']);
		}
		if (empty($params['values']))
		{
			return;
		}
		$sql = "
		REPLACE INTO " . TABLE_PREFIX . $params['table'] . "
		(" . implode(', ', array_keys($params['values'][0])) . ")
		VALUES
		";
		$rows = array();
		foreach ($params['values'] as $querybit)
		{
			$values = array();
			foreach ($querybit as $val)
			{
				$values[] = "'" . $db->escape_string($val) ."'";
			}
			$rows[] = implode(", ", $values);
		}
		$sql .= "
			(" . implode("),
			(", $rows) . ")";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::get_config();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}

		$result = $db->query_write($sql);

		if (empty($params['returnId']))
		{
			return $result;
		}
		else
		{
			return $db->insert_id();
		}
	}

	/**
	* Creates an INSERT IGNORE query based on the params that are passed
	*
	*	@param	mixed
	*	@param	mixed 	a db pointer
	*	@param	bool
	*
	*	@result	mixed
	*/
	public function insertignoreValues($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['values']) AND isset($params['table']);
		}
		if (empty($params['values']))
		{
			return;
		}
		$sql = "
		INSERT IGNORE INTO " . TABLE_PREFIX . $params['table'] . "
		(" . implode(', ', array_keys($params['values'][0])) . ")
		VALUES
		";
		$rows = array();
		foreach ($params['values'] as $querybit)
		{
			$values = array();
			foreach ($querybit as $val)
			{
				$values[] = "'" . $db->escape_string($val) ."'";
			}
			$rows[] = implode(", ", $values);
		}
		$sql .= "
			(" . implode("),
			(", $rows) . ")";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::get_config();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}

		$result = $db->query_write($sql);

		if (empty($params['returnId']))
		{
			return $result;
		}
		else
		{
			return $db->insert_id();
		}
	}

	/**
	* Fetches the mailing list for users regarding the user adminemail option.
	*
	*	@param	mixed
	*	@param	mixed 	a db pointer
	*	@param	bool
	*
	*	@result	mixed
	*/
	public function fetchMailingList($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			// @TODO implement better validation here
			return true;
		}

		$where = "WHERE user.email <> ''\n";
		if (!empty($params['filters']))
		{
			$className = 'vB_Db_' . $this->db_type . '_QueryBuilder';
			$queryBuilder = new $className($db, false);
			$where .= " AND " . $queryBuilder->conditionsToFilter($params['filters']);
		}

		// only using useroptions at the moment... we can extend this later...
		$options = vB::getDatastore()->getValue('bf_misc_useroptions');
		if ($params['options'] AND empty($params['options']['adminemail']))
		{
			$where .= "\n AND (options & $options[adminemail])\n";
		}
		$sql = "SELECT DISTINCT user.email " . ($params['activation'] ? ", user.userid, user.usergroupid, user.username, user.joindate, useractivation.activationid\n" : '')
		. " FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (userfield.userid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
			" . ($params['activation'] ?
			"LEFT JOIN " . TABLE_PREFIX . "useractivation AS useractivation ON (useractivation.userid = user.userid AND useractivation.type = 0)\n" : '')
		. " $where
			" . ($params['activation'] ?
			"ORDER BY userid
			LIMIT " . $params[vB_dB_Query::PARAM_LIMITPAGE] . ", " . $params[vB_dB_Query::PARAM_LIMIT] ."\n" : '')
		. "
			/** fetchMailingList" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	/**
	* Gets count of accounts with vulnerable passwords.
	*
	*	@param	mixed
	*	@param	mixed 	a db pointer
	*	@param	bool
	*
	*	@result	mixed
	*/
	public function getAffectedAccountsCount($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			if (isset($params['period']) AND !is_numeric($params['period']))
			{
				return false;
			}

			return true;
		}

		$sql = "SELECT COUNT(userid) AS total_affected
				FROM " . TABLE_PREFIX . "user
				WHERE password = MD5(CONCAT(MD5(username),salt)) " .
				($params['period'] ? 'AND lastvisit < ' . (vB::getRequest()->getTimeNow() - $params['period']) : '') . "
				/** getAffectedAccountsCount" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	/**
	* Gets the accounts with vulnerable passwords.
	*
	*	@param	mixed
	*	@param	mixed 	a db pointer
	*	@param	bool
	*
	*	@result	mixed
	*/
	public function getAffectedAccounts($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			foreach (array('period', 'lastuser', 'quantity', 'languageid') AS $param)
			{
				if (isset($params["$param"]) AND !is_numeric($params["$param"]))
				{
					return false;
				}
			}

			return true;
		}

		$sql = "SELECT user.userid, userban.liftdate
				FROM " . TABLE_PREFIX . "user AS user
				LEFT JOIN " . TABLE_PREFIX . "userban AS userban ON (user.userid = userban.userid)
				WHERE user.password = MD5(CONCAT(MD5(user.username),user.salt)) " .
				($params['period'] ? ' AND user.lastvisit < ' . (vB::getRequest()->getTimeNow() - $params['period']) : '') . "
				AND user.userid > " . intval($params[lastuser]) .
				($params['languageid'] ? " AND user.languageid = " . intval($params['languageid']) : '') . "
				LIMIT 0, " . intval($params['quantity']) . "
				/** getAffectedAccounts" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/
		";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	/**
	* Updates the plugin active status
	*
	*	@param	mixed
	*	@param	mixed 	a db pointer
	*	@param	bool
	*
	*	@result	mixed
	*/
	public function updateHookStatus($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			foreach ($params['hookdata'] AS $pluginid => $info)
			{
				if (!is_numeric($pluginid))
				{
					return false;
				}

				if (!isset($info['active']))
				{
					return false;
				}

				if (!isset($info['hookorder']))
				{
					return false;
				}
			}

			return true;
		}

		$cond1 = $cond2 = "";
		foreach ($params['hookdata'] AS $hookid => $info)
		{
			$cond1 .= "\n WHEN $hookid THEN " . ( ((bool)$info['active']) ? 1 : 0);
			$cond2 .= "\n WHEN $hookid THEN " . intval($info['hookorder']);
		}

		$sql = "UPDATE " . TABLE_PREFIX . "hook
		SET active = CASE hookid
				$cond1
				\n ELSE active END,
		\n hookorder = CASE hookid
				$cond2
				\n ELSE hookorder END
		";

		return $db->query_write($sql);
	}

	/**
	* Remove the language columns from a package
	*
	*	@param	mixed
	*	@param	mixed 	a db pointer
	*	@param	bool
	*
	*	@result	mixed
	*/
	public function removeLanguageFromPackage($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (isset($params['productid']) AND is_string($params['productid'])) ? true : false;
		}

		$phrasetypes = $db->query_read("
			SELECT fieldname
			FROM " . TABLE_PREFIX . "phrasetype
			WHERE product = '" . $db->escape_string($params['productid']) . "'
		");

		$drops = array();
		while ($phrasetype = $db->fetch_array($phrasetypes))
		{
			$drops[] = 'DROP COLUMN phrasegroup_' . $phrasetype['fieldname'];
		}

		if (empty($drops))
		{
			return true;
		}
		$sql = "ALTER TABLE " . TABLE_PREFIX . "language\n
				" . implode(", ", $drops) . "
				/** removeLanguageFromPackage" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/
		";

		return $db->query_write($sql);
	}

	/**
	* Add the language columns from a package
	*
	*	@param	mixed
	*	@param	mixed 	a db pointer
	*	@param	bool
	*
	*	@result	mixed
	*/
	public function addLanguageFromPackage($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['fieldname'])) ? true : false;
		}

		if (!$db->query_first("
			SHOW COLUMNS FROM " . TABLE_PREFIX . "language
			LIKE 'phrasegroup_" . $db->escape_string($params['fieldname']) . "'"
		))
		{
			$sql = "ALTER TABLE " . TABLE_PREFIX . "language
				ADD COLUMN phrasegroup_" . $params['fieldname'] . " MEDIUMTEXT NOT NULL
				/** addLanguageFromPackage" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/
			";

			return $db->query_write($sql);
		}
	}

	/**
	* Fetches userlist from a given criteria.
	*
	*	@param	mixed
	*	@param	mixed 	a db pointer
	*	@param	bool
	*
	*	@result	mixed
	*/
	public function fetchUsersFromCriteria($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			// @TODO implement better validation here
			return true;
		}

		$where = "";
		if (!empty($params['filters']))
		{
			$className = 'vB_Db_' . $this->db_type . '_QueryBuilder';
			$queryBuilder = new $className($db, false);
			$where .= "WHERE " . $queryBuilder->conditionsToFilter($params['filters']);
		}

		$paginateSql = "";
		if (!empty($params[vB_dB_Query::PARAM_LIMIT]) OR !empty($params[vB_dB_Query::PARAM_LIMITPAGE]))
		{
			if (isset($params[vB_dB_Query::PARAM_LIMIT]) AND intval(vB_dB_Query::PARAM_LIMIT))
			{
				$perpage = intval($params[vB_dB_Query::PARAM_LIMIT]);
			}
			else
			{
				$perpage = 50;
			}

			if (isset($params[vB_dB_Query::PARAM_LIMITPAGE]) AND intval($params[vB_dB_Query::PARAM_LIMITPAGE]) AND (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) > 1))
			{
				$startat = ($perpage * (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) - 1)) . ',';
			}
			else
			{
				$startat = '0, ';
			}

			$paginateSql = $startat . $perpage;
		}

		$sql = "SELECT user.username
				FROM " . TABLE_PREFIX . "user AS user
				LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (userfield.userid = user.userid)
				LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
				$where
				$paginateSql
			/** fetchUsersFromCriteria" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	/**
	* This gets the users from a given sql conditions.
	* @TODO this might get replaced by getUsersByCriteria method... need what condition is being passed in admincp - verticalresponse.php
	*
	*	@param	mixed
	*	@param	mixed 	a db pointer
	*	@param	bool
	*
	*	@result	mixed
	*/
	public function fetchUsersForVerticalResponse($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			// @TODO implement better validation here
			return true;
		}

		$paginateSql = "";
		if (!empty($params[vB_dB_Query::PARAM_LIMIT]) OR !empty($params[vB_dB_Query::PARAM_LIMITPAGE]))
		{
			if (isset($params[vB_dB_Query::PARAM_LIMIT]) AND intval(vB_dB_Query::PARAM_LIMIT))
			{
				$perpage = intval($params[vB_dB_Query::PARAM_LIMIT]);
			}
			else
			{
				$perpage = 50;
			}

			if (isset($params[vB_dB_Query::PARAM_LIMITPAGE]) AND intval($params[vB_dB_Query::PARAM_LIMITPAGE]) AND (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) > 1))
			{
				$startat = ($perpage * (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) - 1)) . ',';
			}
			else
			{
				$startat = '0, ';
			}

			$paginateSql = $startat . $perpage;
		}

		if (empty($params['condition']))
		{
			$params['condition'] = '';
		}

		$sql = "SELECT user.userid, user.username, user.email AS email_address
				FROM " . TABLE_PREFIX . "user AS user
				LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (userfield.userid = user.userid)
				LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
				$params[condition]
				$paginateSql
			/** fetchUsersForVerticalResponse" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	/**
	* Get referrals from a userid.
	* This can be also limited startdate and enddate (datelines).
	*
	*	@param	mixed
	*	@param	mixed 	a db pointer
	*	@param	bool
	*
	*	@result	mixed
	*/
	public function userReferrals($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['referrerid']);
		}
		else
		{
			$where = "WHERE referrerid = " . $params['referrerid'] . " AND usergroupid NOT IN (3, 4)";
			if (!empty($params['startdate']))
			{
				$where .= " AND joindate >= " . $params['startdate'];
			}

			if (!empty($params['enddate']))
			{
				$where .= " AND joindate <= " . $params['enddate'];
			}

			if (!empty($params['enddate']))
			{
			}
			$sql = "SELECT username, posts, userid, joindate, lastvisit, email
					FROM " . TABLE_PREFIX . "user
					$where
					ORDER BY joindate DESC
					/** userReferrals" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function fetchImageInfo($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['table']) AND !empty($params['filedata']) AND !empty($params['userid']);
		}

		$sql = "
			SELECT " . $db->escape_string($params['filedata']) . " AS filedata, dateline, filename
			FROM " . TABLE_PREFIX . $db->escape_string($params['table']) . "
			WHERE userid = " . intval($params['userid']) . " AND visible = 1
			HAVING filedata <> ''
		";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}

		$result = new $resultclass($db, $sql);
		return $result;
	}

	public function fetchSocialgroupIcon($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['filedata']) AND !empty($params['groupid']);
		}

		$sql = "
			SELECT " . $db->escape_string($params['filedata']) . " AS filedata, dateline, extension
			FROM " . TABLE_PREFIX . "socialgroupicon
			WHERE groupid = " . intval($params['groupid']) . "
			HAVING filedata <> ''
		";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}

		$result = new $resultclass($db, $sql);
		return $result;
	}

	/**
	 * Your basic table truncate
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function truncateTable($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['table'])) ? true : false;
		}

		$sql = "TRUNCATE TABLE " . TABLE_PREFIX . $params['table'] . "\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

		$config = vB::getConfig();

		if (!empty($config['Misc']['debug_sql']))
		{
			echo "$sql\n";
		}

		return $db->query_write($sql);
	}

	public function getFiledataBatch($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['filedataid']) AND
					!empty($params['type']) AND
					!empty($params['startbyte']) AND
					!empty($params['readsize']);
		}

		$sql = "
			SELECT fd.filedataid, SUBSTRING(" . $db->escape_string($params['filedata']) . ", " .
				intval($params['startbyte']) . ", " . intval($params['readsize']) . ") AS filedata
			FROM " . TABLE_PREFIX . "filedata AS fd
			LEFT JOIN " . TABLE_PREFIX . "filedataresize AS fdr ON (fd.filedataid = fdr.filedataid AND fdr.resize_type = '" . $db->escape_string($params['type']) . "')
			WHERE fd.filedataid = " . intval($params['filedataid']) . "
		";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}

		$result = new $resultclass($db, $sql);
		return $result;
	}

	/** Composes the terms for the flags to enforce the starter-node-specific permissions.
	 *
	 **/
	protected function getNodePermTerms()
	{
		$userContext = vB::getUserContext();

		if (empty($userContext))
		{
			$canModerate = false;
			$membersOf = false;
			$userid = -1;
		}
		else
		{
			if ($userContext->isSuperAdmin())
			{
				return array('joins' => array(), 'where' => '');
			}

			$where = array();
			$userid = vB::getCurrentSession()->get('userid');
			$channelAccess = $userContext->getAllChannelAccess();

			if (!empty($channelAccess['canmoderate']))
			{
				$where[] = "( starter.parentid in (" . implode(',', $channelAccess['canmoderate']) . ") OR starter.nodeid IN (" . implode(',', $channelAccess['canmoderate']) . "))\n";
			}

			if (!empty($channelAccess['canseedelnotice']))
			{
				$starterAnd = "AND (starter.parentid IN (" . implode(',', $channelAccess['canseedelnotice']) . ") OR node.showpublished > 0)";
			}
			else
			{
				$starterAnd = '';
			}

			if (!empty($channelAccess['canview']))
			{
				$showParams = array(
					"node.showapproved > 0",
					$userid > 0 ? "node.viewperms > 0" : "node.viewperms > 1",
				);

				if (empty($channelAccess['canseedelnotice']))
				{
					$showParams[] = "node.showpublished > 0";
				}

				$where[] = "( (  (starter.parentid IN (" . implode(',', $channelAccess['canview']) .
					") $starterAnd) AND " . implode(" AND ", $showParams) . " ))\n";
			}

			if (!empty($channelAccess['canalwaysview']))
			{
				$where[] = "(starter.parentid IN (" . implode(',', $channelAccess['canalwaysview']) .
					"))\n";
			}

			if (!empty($channelAccess['starteronly']))
			{
				$starterOnly = implode(',', $channelAccess['starteronly']);
				$where[] = "( node.nodeid IN ($starterOnly) OR node.parentid in ($starterOnly) )\n";
			}

			if (!empty($channelAccess['selfonly']))
			{
				$where[] = "( starter.parentid in (" . implode(',', $channelAccess['selfonly']) . ") AND starter.userid = $userid )\n";
			}

			if (!empty($channelAccess['owndeleted']))
			{
				$where[] = "( starter.parentid in (" . implode(',', $channelAccess['owndeleted']) . ") AND node.userid = $userid )\n";
			}


			if (!empty($channelAccess['member']))
			{
				$showParams = array(
					"node.showapproved > 0"
				);

				if (empty($channelAccess['canseedelnotice']))
				{
					$showParams[] = "node.showpublished > 0";
				}

				$where[] = "( (starter.parentid in (" . implode(',', $channelAccess['member']) .
					") $starterAnd) AND " . implode(" AND ", $showParams) . " )\n";
			}
			$joins = array('starter' => " LEFT JOIN " . TABLE_PREFIX . "node AS starter ON starter.nodeid = IF(node.starter = 0, node.nodeid, node.starter)");
		}
		if (empty($where))
		{
			return array('where' => "\nAND ( nodeid = 0 )\n",
				'joins' => array());
		}

		$terms = array('where' => "\nAND ( " . implode (" OR ", $where) . ")\n",
			'joins' => $joins);

		return $terms;

	}


	public function saveDbCache($params, $db, $check_only = false)
	{
		$fields = array('cacheid', 'expires', 'created', 'locktime', 'serialized', 'data');
		if ($check_only)
		{
			if (empty($params['cache']) OR !is_array($params['cache']))
			{
				return false;
			}

			foreach($params['cache'] AS $key => $cacheData)
			{
				foreach($fields AS $field)
				{
					if (!isset($cacheData[$field]))
					{
						return false;
					}
				}

				if (!is_numeric($cacheData['expires']) OR !is_numeric($cacheData['created'])
					OR !is_numeric($cacheData['serialized']))
				{
					return false;
				}
			}
			//if we got here we're good.
			return true;
		}

		//First we need to find what cache events are already set for the current cacheid;
		$cacheInfo = $params['cache'];

		$sql = "/** saveDbCache */ SELECT * FROM " . TABLE_PREFIX . "cacheevent WHERE cacheid IN ('" .
			$db->escape_string(implode("','",  array_keys($cacheInfo))). "') ";
		$deleteCacheEvents = array();
		$results = $db->query_read($sql);

		//We need to compare the existing cacheevent records against what we were passed.
		while ($eventInfo = $db->fetch_array($results))
		{
			$cacheid = $eventInfo['cacheid'];
			$event = $eventInfo['event'];

			if (!empty($params['cache'][$cacheid]) AND isset($params['cache'][$cacheid]['events'][$event]))
			{
				//This cache record already exists. We don't need to do a  new insert.
				unset($params['cache'][$cacheid]['events'][$event]);
			}
			else
			{
				$deleteCacheEvents[] = "(cacheid = '$cacheid' AND event = '$event')";
			}
		}

		//Delete the unnecessary events.
		if (!empty($deleteCacheEvents))
		{
			$sql = "/** saveDbCache */ DELETE FROM " . TABLE_PREFIX . "cacheevent WHERE " . implode(" OR \n", $deleteCacheEvents);
			$db->query_write($sql);
		}
		//Now it is just possible that we could have several really large cache inserts. So we need to be careful to keep the
		//length of the sql under a quarter megabyte.
		$replace = "/** saveDbCache */ REPLACE INTO " . TABLE_PREFIX . "cache (" . implode(',', $fields) . ") values \n";
		$havemore = false;
		$values = array();
		$addCacheEvents = array();

		foreach ($cacheInfo AS $cacheid => $cache)
		{
			$values[] = "('" . $db->escape_string($cache['cacheid']) . "', " . intval($cache['expires']) . ", "  . intval($cache['created']) .
				", 0, '" . intval($cache['serialized']) . "', '". $db->escape_string($cache['data']) . "')\n";
			$havemore = true;


			if (strlen($replace) > 125000)
			{
				$db->query_write($replace . implode(",\n", $values));
				$values = array();
				$havemore = false;
			}

			if (!empty($cache['events']))
			{
				if (is_array($cache['events']))
				{
					foreach(array_unique($cache['events']) AS $thisEvent)
					{
						$addCacheEvents[] = "('" . $db->escape_string($cache['cacheid']) . "','" .
							$db->escape_string($thisEvent) . "')";
					}
				}
				else
				{
					$addCacheEvents[] = "('" . $db->escape_string($cache['cacheid']) . "','" .
						$db->escape_string($cache['events']) . "')";
				}
			}
		}

		if ($havemore)
		{
			$db->query_write($replace . implode(",\n", $values));
		}

		//add the cache events
		if (!empty($addCacheEvents))
		{
			$sql = "/** saveDbCache */REPLACE INTO " . TABLE_PREFIX . "cacheevent (cacheid, event) values\n " .
				implode(",\n", $addCacheEvents);
			$db->query_write($sql);
		}
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}

		return true;
	}


	public function saveDbCacheEvents($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['events']));
		}
		$addCacheEvents = array();
		foreach ($params['events'] AS $cacheid => $events)
		{
			if (!empty($events))
			{
				if (is_array($events))
				{
					foreach(array_unique($events) AS $thisEvent)
					{
						$addCacheEvents[] = "('" . $db->escape_string($cacheid) . "','" .
							$db->escape_string($thisEvent) . "')";
					}
				}
				else
				{
					$addCacheEvents[] = "('" . $db->escape_string($cacheid) . "','" .
						$db->escape_string($events) . "')";
				}
			}
		}

		//add the cache events
		if (!empty($addCacheEvents))
		{
			$sql = "/** saveDbCache */REPLACE INTO " . TABLE_PREFIX . "cacheevent (cacheid, event) values\n " .
				implode(",\n", $addCacheEvents);
			$db->query_write($sql);
		}
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}

		return true;
	}

	/** Get all the site threads limitted by the given parentids.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getSiteThreads($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			foreach (array('startat', vB_dB_Query::PARAM_LIMIT) AS $param)
			{
				if (!isset($params[$param]) OR !is_numeric($params[$param]) AND ($params[$param] < 1))
				{
					return false;
				}
			}

			foreach (array('parents', 'exclude_ids') AS $param)
			{
				if (!isset($params[$param]) OR !is_array($params[$param]))
				{
					return false;
				}

				foreach ($params[$param] AS $val)
				{
					if (!is_numeric($val))
					{
						return false;
					}
				}
			}

			return true;
		}

		$sql = "
			SELECT nodeid, routeid, title, lastcontent
			FROM " . TABLE_PREFIX . "node
			WHERE nodeid = starter AND parentid IN ( " . implode(", ", $params['parents']) . ") AND showapproved > 0
				AND showpublished > 0 AND open = 1 AND inlist = 1 AND nodeid >= " . $params['startat'] . "
				" . (!empty($params['exclude_ids']) ? "AND userid NOT IN (" . implode(", ", $params['exclude_ids']) . ")" : "") . "
			ORDER BY nodeid
			LIMIT " . $params[vB_dB_Query::PARAM_LIMIT] . "
			/** getSiteThreads" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . '**/';

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$config = vB::get_config();

			if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
			{
				echo "sql: $sql<br />\n";
			}

			$result = new $resultclass($db, $sql);
			return $result;
	}

	public function fetchPermsOrdered($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		$channelcontentypeid = vB_Api::instanceInternal('contenttype')->fetchContentTypeIdFromClass('Channel');
		$usergroupcond = empty($params['usergroupid']) ? '' : ' AND permission.groupid = ' . intval($params['usergroupid']);
		$nodecond = empty($params['nodeid']) ? '' : ' AND closure.parent = ' . intval($params['nodeid']);
		$sql = "
				SELECT permission.groupid, permission.forumpermissions, node.nodeid, node.htmltitle
				FROM " . TABLE_PREFIX . "node AS node
				INNER JOIN " . TABLE_PREFIX . "closure AS closure ON ( closure.child = node.nodeid $nodecond)
				INNER JOIN " . TABLE_PREFIX . "permission AS permission ON ( closure.parent = permission.nodeid $usergroupcond)
				WHERE node.contenttypeid = $channelcontentypeid
				ORDER BY closure.depth DESC
		";

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

/* ======================================================================*\
 || ####################################################################
 || # SVN=> $Revision=> 28823 $
 || ####################################################################
 \*====================================================================== */
