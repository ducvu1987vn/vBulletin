<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright  2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
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
class vBForum_dB_MYSQL_QueryDefs extends vB_dB_MYSQL_QueryDefs
{

	/**
	 * This class is called by the new vB_dB_Assertor database class
	 * It does the actual execution. See the vB_dB_Assertor class for more information
	 *
	 * $queryid can be either the id of a query from the dbqueries table, or the
	 * name of a table.
	 *
	 * if it is the name of a table , $params MUST include vB_dB_Query::TYPE_KEY of either update, insert, select, or delete.
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
	 **/

	/*Properties====================================================================*/

	//type-specific

	protected $home_page = 1;

	protected $db_type = 'MYSQL';

	protected static $permission_string = false;

	/** This is the definition for tables we will process through. It saves a
	* database query to put them here.
	* **/
	protected $table_data = array(
		'closure' => array('key'=> false,'structure' => array( 'parent','child','depth', 'displayorder', 'publishdate')),

		'node' => array('key'=> 'nodeid','structure' => array('nodeid','routeid','contenttypeid','publishdate','unpublishdate','userid',
			'groupid','authorname','description','keywords','title','htmltitle','parentid',
			'urlident','displayorder', 'created', 'lastcontent', 'lastcontentid',
			'lastcontentauthor', 'lastauthorid', 'lastprefixid', 'textcount',
			'textunpubcount', 'totalcount', 'totalunpubcount', 'ipaddress', 'showpublished', 'oldid', 'oldcontenttypeid',
			'nextupdate', 'lastupdate','featured', 'starter', 'CRC32', 'taglist', 'inlist', 'protected', 'setfor',
			'votes', 'hasphoto', 'hasvideo', 'deleteuserid', 'deletereason', 'open', 'showopen','sticky', 'approved',
			'showapproved', 'viewperms', 'commentperms', 'nodeoptions', 'prefixid', 'iconid'),
			'forcetext' => array('title', 'htmltitle', 'urlident', 'description')),

		'channel' => array('key'=> 'nodeid','structure' => array('nodeid', 'styleid',
			'daysprune', 'newcontentemail','defaultsortfield','defaultsortorder','imageprefix', 'guid', 'options', 'filedataid', 'category')),

		'attach' => array('key'=> 'nodeid','structure' => array('nodeid',
			'filedataid','visible','counter','posthash','filename','caption', 'reportthreadid', 'settings')),

		'attachmenttype' => array('key' => 'extension', 'structure' => array('extension', 'mimetype','size','width','height','display','contenttypes')),

		'filedata' => array('key' => 'filedataid', 'structure' => array('userid', 'dateline', 'filedata', 'filesize',
			'filehash', 'extension', 'refcount', 'width', 'height', 'publicview')),

		'filedataresize' => array('key' => array('filedataid', 'type'), 'structure' => array('filedataid', 'resize_type', 'resize_filedata',
			'resize_dateline', 'resize_dateline', 'resize_width', 'resize_height', 'reload')),

		'text' =>array('key'=> 'nodeid','structure' => array('nodeid',
			'previewtext','previewimage','previewvideo','imageheight','imagewidth', 'rawtext', 'moderated',
			'pagetextimages', 'pagetext', 'htmlstate', 'allowsmilie','showsignature','attach',
			'infraction','reportnodeid',)),

		'permission' => array('key'=> 'permissionid','structure' => array('permissionid','nodeid',
			'groupid','forumpermissions', 'forumpermissions2', 'moderatorpermissions','createpermissions','edit_time','require_moderate',
			'maxtags', 'maxstartertags', 'maxothertags', 'maxattachments')),

		'style' => array('key'=> 'styleid','structure' => array('styleid','parentid', 'title',
			'parentlist','templatelist','newstylevars', 'replacements',
			'editorstyles', 'userselect', 'displayorder', 'dateline')),

		'contenttype' => array(
			'key' => 'contenttypeid',
			'structure' => array('contenttypeid','class','packageid','canplace','cansearch','cantag',
				'canattach','isaggregator'),
			'forcetext'=>array('canplace','cansearch','cantag','canattach','isaggregator')
		),

		'gallery' => array('key' => 'nodeid', 'structure' => array('nodeid', 'caption')),

		'photo' => array('key' => 'nodeid', 'structure' => array('nodeid', 'filedataid',
			'caption', 'height', 'width', 'style')),

		'tag' => array('key' => 'tagid', 'structure' => array('tagid', 'tagtext', 'dateline', 'canonicaltagid')),

		'poll' => array('key' => 'nodeid', 'structure' => array('nodeid', 'options', 'active',
			'numberoptions', 'timeout', 'multiple', 'votes', 'public', 'lastvote' )),

		'video' => array('key' => 'nodeid', 'structure' => array('nodeid', 'url', 'url_title', 'meta')),

		'videoitem' => array('key' => 'videoitemid', 'structure' => array(
			'videoitemid', 'nodeid', 'provider', 'code', 'url')),

		'sentto' => array('key' => array('nodeid', 'userid', 'folderid'), 'structure' => array('nodeid',
			'userid', 'folderid', 'deleted', 'read')),

		'messagefolder' => array('key' => 'folderid', 'structure' => array('folderid',
			'userid', 'title', 'titlephrase'),
			'forcetext' => array('title')),

		'privatemessage' => array('key' => 'nodeid', 'structure' => array('nodeid',
			'msgtype', 'about', 'aboutid')),

		'link' => array(
			'key' => 'nodeid',
			'structure' => array('nodeid', 'filedataid', 'url', 'url_title', 'meta')
		),

		'report' => array(
			'key' => 'nodeid',
			'structure' => array('nodeid', 'reportnodeid', 'closed'),
		),

		'nodevote' => array(
			'key' => 'nodevoteid',
			'structure' => array('nodevoteid', 'nodeid', 'userid', 'votedate')
		),

		'faq' => array('key' => 'faqname', 'structure' => array('faqname',
			'faqparent', 'displayorder', 'volatile','product')
		),

		'phrase' => array('key' => 'phraseid', 'structure' => array('phraseid',
			'languageid','varname','fieldname','text','product','username','dateline','version')
		),

		'adminhelp' => array('key' => 'adminhelpid', 'structure' => array('adminhelpid',
			'script','action','optionname','displayorder','volatile','product')
		),

		'reputation' => array(
			'key' => 'reputationid',
			'structure' => array('reputationid','nodeid','userid','reputation','whoadded','dateline'),
		),

		'usergroup' => array('key' => 'usergroupid', 'structure' =>array('usergroupid',
			'usergroupid', 'title', 'description', 'usertitle', 'passwordexpires', 'passwordhistory', 'pmquota', 'pmsendmax', 'opentag', 'closetag',
			'canoverride', 'ispublicgroup', 'forumpermissions', 'pmpermissions', 'calendarpermissions', 'wolpermissions', 'adminpermissions',
			'genericpermissions', 'genericpermissions2', 'genericoptions', 'signaturepermissions',  'visitormessagepermissions', 'attachlimit',
			'avatarmaxwidth', 'avatarmaxheight', 'avatarmaxsize', 'profilepicmaxwidth', 'profilepicmaxheight', 'profilepicmaxsize', 'sigpicmaxwidth',
			'sigpicmaxheight', 'sigpicmaxsize', 'sigmaximages', 'sigmaxsizebbcode', 'sigmaxchars', 'sigmaxrawchars', 'sigmaxlines', 'usercsspermissions',
			'albumpermissions', 'albumpicmaxwidth', 'albumpicmaxheight', 'albummaxpics', 'albummaxsize', 'socialgrouppermissions',
			'pmthrottlequantity', 'groupiconmaxsize', 'maximumsocialgroups')
		),

		'forumpermission' => array('key' => 'forumpermissionid', 'structure' =>array('forumpermissionid',
			'forumid', 'usergroupid', 'forumpermissions')
		),

		'calendarpermission' => array('key' => 'calendarpermissionid', 'structure' =>array('calendarpermissionid',
			'calendarid', 'usergroupid', 'calendarpermissions')
		),

		'sigparsed' => array('key' => array('userid', 'styleid', 'languageid'), 'structure' =>array('userid', 'styleid', 'languageid',
			'signatureparsed', 'hasimages')
		),

		'useractivation' => array('key' => 'useractivationid', 'structure' =>array('useractivationid',
			'userid', 'dateline', 'activationid', 'type', 'usergroupid', 'emailchange')
		),

		'subscription' => array('key' => 'subscriptionid', 'structure' =>array('subscriptionid',
			'varname', 'cost', 'forums', 'nusergroupid', 'membergroupids', 'active', 'options', 'displayorder', 'adminoptions')
		),

		'subscriptionlog' => array('key' => 'subscriptionlogid', 'structure' =>array('subscriptionlogid',
			'subscriptionid', 'userid', 'pusergroupid', 'status', 'regdate', 'expirydate')
		),
		'userban' => array('key' => 'userid', 'structure' =>array('userid',
			'usergroupid', 'displaygroupid', 'usertitle', 'customtitle', 'adminid', 'bandate', 'liftdate','reason')
		),
		'ranks' => array('key' => 'rankid', 'structure' =>array('rankid',
			'minposts', 'ranklevel', 'rankimg', 'usergroupid', 'type', 'stack', 'display')
		),
		'imagecategorypermission' => array('key' => 'imagecategoryid', 'structure' =>array('imagecategoryid',
			'usergroupid')
		),
		'attachmentpermission' => array('key' => 'attachmentpermissionid', 'structure' =>array('attachmentpermissionid',
			'extension', 'usergroupid', 'size', 'width', 'height', 'attachmentpermissions')
		),
		'prefixpermission' => array('key' => 'prefixid', 'structure' =>array('prefixid',
			'usergroupid')
		),
		'usergroupleader' => array('key' => 'usergroupleaderid', 'structure' =>array('usergroupleaderid',
			'userid', 'usergroupid')
		),

		'userpromotion' => array('key' => 'userpromotionid', 'structure' =>array('userpromotionid',
			'usergroupid', 'joinusergroupid', 'reputation', 'date', 'posts', 'strategy', 'type')
		),

		'usergrouprequest' => array('key' => 'usergrouprequestid', 'structure' =>array('usergrouprequestid',
			'userid', 'usergroupid', 'reason', 'dateline')
		),

		'bookmarksite' => array('key' => 'bookmarksiteid', 'structure' => array('bookmarksiteid',
			'title','iconpath','active','displayorder','url','utf8encode')
		),

		'imagecategory' => array('key' => 'imagecategoryid', 'structure' => array('imagecategoryid',
			'title','imagetype','displayorder')
		),

		'imagecategorypermission' => array('key' => array('imagecategoryid', 'usergroupid'), 'structure' => array('imagecategoryid',
			'usergroupid')
		),

		'smilie' => array('key' => 'smilieid', 'structure' => array('smilieid',
			'title', 'smilietext', 'smiliepath', 'imagecategoryid', 'displayorder')
		),

		'icon' => array('key' =>  'iconid', 'structure' => array('iconid',
			'title', 'iconpath', 'imagecategoryid', 'displayorder')
		),

		'avatar' => array('key' => 'avatarid', 'structure' => array('avatarid',
			'title', 'minimumposts', 'avatarpath', 'imagecategoryid', 'displayorder')
		),

		'customavatar' => array('key' => 'userid', 'structure' => array('userid',
			'filedata', 'dateline', 'filename', 'visible', 'filesize', 'width', 'height', 'filedata_thumb',
			'width_thumb', 'height_thumb', 'extension')
		),

		'customprofilepic' => array('key' => 'userid', 'structure' => array('userid',
			'filedata', 'dateline', 'filename', 'visible', 'filesize', 'width', 'height')
		),

		'sigpic' => array('key' => 'userid', 'structure' => array('userid',
			'filedata', 'dateline', 'filename', 'visible', 'filesize', 'width', 'height')
		),
		'adminlog' => array('key' => 'adminlogid', 'structure' => array('adminlogid', 'userid', 'dateline', 'script', 'action',
				'extrainfo', 'ipaddress')
		),
		'tag' => array('key' => 'tagid', 'structure' => array('tagid',
			'tagtext','dateline','canonicaltagid')
		),

		'tagnode' => array('key' => 'tagid', 'structure' => array('tagid',
			'nodeid','userid', 'dateline')
		),

		'tagsearch' => array('key' => 'tagid', 'structure' => array('tagid',
			'dateline')
		),

		'groupintopic' => array('key' => array('userid','groupid','nodeid'),
			'structure' => array('userid','groupid','nodeid')
		),

		'profilefield' => array('key' => 'profilefieldid', 'structure' => array('profilefieldid',
			'profilefieldcategoryid', 'required', 'hidden', 'maxlength', 'size', 'displayorder', 'editable', 'type', 'data',
			'height', 'def', 'optional', 'searchable', 'memberlist', 'regex', 'form', 'html', 'perline')
		),

		'hvanswer' => array('key' => 'answerid', 'structure' => array('answerid',
			'questionid', 'answer', 'dateline')
		),

		'hvquestion' => array('key' => 'questionid', 'structure' => array('questionid',
			'regex', 'dateline')
		),

		'nodestats' => array('key' => 'nodestatsid', 'structure' => array('nodestatsid',
			'nodeid', 'dateline', 'replies', 'visitors')
		),

		'nodevisits' => array('key' => 'nodevisitid', 'structure' => array('nodevisitid',
			'nodeid', 'visitorid', 'dateline', 'totalcount')
		),

		'cronlog' => array('key' => 'cronlogid', 'structure' => array('cronlogid',
			'vaname', 'dateline', 'description', 'type')
		),

		'strikes' => array('key' => array('striketime', 'strikeip'), 'structure' => array('striketime',
			'strikeip', 'ip_4', 'ip_3', 'ip_2', 'ip_1', 'username')
		),

		'announcement' => array('key' => 'announcementid', 'structure' => array('announcementid',
			'title', 'userid', 'startdate', 'enddate', 'pagetext', 'nodeid', 'views', 'announcementoptions')
		),

		'announcementread' => array('key' => array('announcementid', 'userid'), 'structure' => array('announcementid',
			'userid')
		),
		'subscribediscussion' => array('key' => 'subscribediscussionid', 'structure' => array('subscribediscussionid',
			'userid', 'discussionid', 'emailupdate')
		),
		'ranks' => array('key' => 'rankid', 'structure' => array('rankid',
			'minposts', 'ranklevel', 'rankimg', 'usergroupid', 'type', 'stack', 'display')
		),
		'prefix' => array('key' => 'prefixid', 'structure' => array('prefixid',
			'prefixsetid', 'displayorder', 'options'
		)),
		'prefixset' => array('key' => 'prefixsetid', 'structure' => array('prefixsetid',
			'displayorder'
		)),
		'channelprefixset' => array('key' => 'prefixsetid', 'structure' => array(
			'nodeid', 'prefixsetid'
		)),
		'stylevar' => array('key' => array('stylevarid', 'styleid'), 'structure' => array('stylevarid',
			'styleid', 'value', 'dateline', 'username'
		)),
		'moderator' => array('key' => 'moderatorid', 'structure' => array('moderatorid',
			'userid', 'nodeid', 'permissions', 'permissions2'
		)),
		'stylevardfn' => array('key' => 'stylevarid', 'structure' => array('stylevarid',
			'styleid', 'parentid', 'parentlist', 'stylevargroup', 'product', 'datatype', 'validation', 'failsafe', 'units', 'uneditable'
		)),
		'reputationlevel' => array('key' => 'reputationlevelid', 'structure' => array('reputationlevelid',
			'minimumreputation'
		)),
		'reputation' => array('key' => array('reputationid', 'nodeid', 'userid', 'whoadded', 'dateline'), 'structure' => array('reputationid',
			'nodeid', 'userid', 'reputation', 'whoadded', 'reason', 'dateline'
		)),
		'administrator' => array('key' => 'userid', 'structure' => array('userid',
			'adminpermissions', 'navprefs', 'cssprefs', 'notes', 'dismissednews', 'languageid'
		)),
		'noticecriteria' => array('key' => array('noticeid', 'criteriaid'), 'structure' => array('noticeid',
			'criteriaid', 'condition1', 'condition2', 'condition3'
		)),
		'noticedismissed' => array('key' => array('noticeid', 'userid'), 'structure' => array('noticeid',
			'userid'
		)),
		'notice' => array('key' => 'noticeid', 'structure' => array('noticeid',
			'title', 'displayorder', 'persistent', 'active', 'dismissible'
		)),
		'rssfeed' => array('key' => 'rssfeedid', 'structure' => array('rssfeedid',
			'title', 'url', 'port', 'ttl', 'maxresults', 'userid', 'nodeid', 'prefixid', 'iconid', 'titletemplate',
			'bodytemplate', 'searchwords', 'itemtype', 'topicactiondelay', 'endannouncement', 'options', 'lastrun'
		)),
		'rsslog' => array('key' => 'rssfeedid', 'structure' => array('rssfeedid',
			'itemid', 'itemtype', 'uniquehash', 'contenthash', 'dateline', 'topicactiontime', 'topicactioncomplete'
		)),
		'subscriptionpermission' => array('key' => 'subscriptionpermissionid', 'structure' => array('subscriptionpermissionid',
			'subscriptionid', 'usergroupid'
		)),
		'paymentapi' => array('key' => 'paymentapiid', 'structure' => array('paymentapiid',
			'title', 'currency', 'recurring', 'classname', 'active', 'settings'
		)),
		'paymentinfo' => array('key' => array('paymentinfoid', 'hash'), 'structure' => array('paymentinfoid',
			'hash', 'subscriptionid', 'subscriptionsubid', 'userid', 'completed'
		)),
		'paymenttransaction' => array('key' => 'paymenttransactionid', 'structure' => array('paymenttransactionid',
			'paymentinfoid', 'transactionid', 'state', 'amount', 'currency', 'dateline', 'paymentapiid', 'request', 'reversed'
		)),
		'postedithistory' => array('key' => 'postedithistoryid', 'structure' => array('postedithistoryid', 'nodeid', 'userid', 'username',
			'title', 'dateline', 'reason', 'pagetext', 'original',
		)),
		'site' => array('key' => 'siteid', 'structure' => array('siteid', 'title', 'headernavbar', 'footernavbar')
		),
		'userfield' => array('key' => 'userid', 'structure' => array('userid', 'temp', 'field1', 'field2', 'field3', 'field4'),
			'forcetext' => array('field1', 'field2', 'field3', 'field4')
		),
		'pollvote' => array('key' => 'pollvoteid', 'structure' => array('pollvoteid', 'nodeid', 'polloptionid', 'userid', 'votedate')
		),
		'polloption' => array('key' => 'polloptionid', 'structure' => array('polloptionid', 'nodeid', 'title', 'votes', 'voters')
		),
		'customprofile' => array('key' => array('customprofileid', 'userid'), 'structure' => array('customprofileid', 'title', 'thumbnail',
				'userid', 'themeid', 'font_family', 'fontsize', 'title_text_color', 'page_background_color', 'page_background_image',
				'page_background_repeat', 'module_text_color', 'module_link_color', 'module_background_color', 'module_background_image',
				'module_background_repeat', 'module_border', 'content_text_color', 'content_link_color', 'content_background_color',
				'content_background_image', 'content_background_repeat', 'content_border', 'button_text_color', 'button_background_color', 'button_background_image',
				'button_background_repeat', 'button_border', 'moduleinactive_text_color', 'moduleinactive_link_color', 'moduleinactive_background_color',
				'moduleinactive_background_image', 'moduleinactive_background_repeat', 'moduleinactive_border', 'headers_text_color', 'headers_link_color',
				'headers_background_color', 'headers_background_image', 'headers_background_repeat', 'headers_border', 'page_link_color'
			)
		),
		'usertextfield' => array('key' => 'userid', 'structure' => array('userid', 'subfolders', 'pmfolders', 'buddylist', 'ignorelist',
			'signature', 'searchprefs', 'rank')
		),
		'redirect' => array(
			'key' => 'nodeid',
			'structure' => array(
				'nodeid',
				'tonodeid',
			),
		),
		'nodehash' => array(
			'key' => array('userid', 'dupehash', 'dateline'),
			'structure' => array(
				'userid',
				'nodeid',
				'dupehash',
				'dateline'
			),
		),
	);
	/** This is the definition for queries.
	 * **/
	protected $query_data = array
	(
		'getParents' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' =>  "SELECT cl.*, node.nodeid, node.parentid, node.routeid, node.title, node.urlident, node.contenttypeid, node.publishdate, node.unpublishdate, node.showpublished, node.starter
			FROM {TABLE_PREFIX}closure AS cl
			INNER JOIN {TABLE_PREFIX}node AS node ON node.nodeid = cl.parent
			WHERE cl.child IN ({nodeid})
			ORDER by cl.child ASC, cl.depth ASC"),

		'getDescendants' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' =>  "SELECT cl.*, node.contenttypeid, node.publishdate, node.unpublishdate
			FROM {TABLE_PREFIX}closure AS cl
			INNER JOIN {TABLE_PREFIX}node AS node ON node.nodeid = cl.child
			WHERE cl.parent = {nodeid}
			ORDER by cl.child ASC, cl.depth ASC"),

		'getDescendantChannelNodeIds' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT closure.child
				FROM {TABLE_PREFIX}closure AS closure
				INNER JOIN {TABLE_PREFIX}node AS node ON node.nodeid = closure.child
				WHERE closure.parent = {parentnodeid} AND node.contenttypeid ={channelType}
			"),

		'truncate_cache' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' =>  "TRUNCATE TABLE {TABLE_PREFIX}cache"),

		'getContentTypes' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' =>  "(SELECT 'package' AS classtype, package.packageid AS typeid, package.packageid AS packageid,
					package.productid AS productid, if(package.productid = 'vbulletin', 1, product.active) AS enabled,
					package.class AS class, -1 as isaggregator, -1 AS cansearch, -1 AS canattach
			FROM {TABLE_PREFIX}package AS package
		LEFT JOIN {TABLE_PREFIX}product AS product
		ON product.productid = package.productid
		WHERE product.active = 1
		OR package.productid = 'vbulletin'
		) 	UNION
		(SELECT 'contenttype' AS classtype, contenttypeid AS typeid, contenttype.packageid AS packageid,
		1, 1, contenttype.class AS class , contenttype.isaggregator, contenttype.cansearch, contenttype.canattach
		FROM {TABLE_PREFIX}contenttype AS contenttype
		INNER JOIN {TABLE_PREFIX}package AS package ON package.packageid = contenttype.packageid
		LEFT JOIN {TABLE_PREFIX}product AS product ON product.productid = package.productid
		WHERE product.active = 1
		OR package.productid = 'vbulletin')
		"),
		'getUserDetails' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
		'query_string' =>  "
			SELECT u.*, ut.rank, ut.signature, av.avatarpath, NOT ISNULL(cu.userid) AS hascustomavatar, cu.dateline AS avatardateline,
				cu.width AS avwidth, cu.height AS avheight, cu.height_thumb AS avheight_thumb, cu.width_thumb AS avwidth_thumb
			FROM {TABLE_PREFIX}user AS u
			LEFT JOIN {TABLE_PREFIX}usertextfield AS ut ON (ut.userid = u.userid)
			LEFT JOIN {TABLE_PREFIX}customavatar AS cu ON (cu.userid = u.userid)
			LEFT JOIN {TABLE_PREFIX}avatar AS av ON (av.avatarid = u.avatarid)
			WHERE u.userid IN ({userid})
		"),
		'getNeedUpdate' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
		'query_string' => "SELECT cl.child AS nodeid FROM {TABLE_PREFIX}node AS node INNER JOIN {TABLE_PREFIX}closure AS cl
		ON cl.child = node.nodeid WHERE cl.parent = {nodeid} AND cl.depth > 0 AND node.nextupdate < {timenow}  AND node.nextupdate > 0"),
		'UpdateParentCount' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node SET
				textcount =
					CASE WHEN {textChange} > 0 OR textcount > -1 * {textChange}
						THEN textcount + ({textChange})
					ELSE 0 END,
				textunpubcount =
					CASE WHEN {textUnpubChange} > 0 OR textunpubcount > -1 * {textUnpubChange}
						THEN textunpubcount + ({textUnpubChange})
					ELSE 0 END
				WHERE nodeid = {nodeid}'),
		'UpdateAncestorCount' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node SET
				totalcount =
					CASE WHEN {totalChange} > 0 OR totalcount > -1 * {totalChange}
						THEN totalcount + ({totalChange})
					ELSE 0 END,
				totalunpubcount =
					CASE WHEN {totalUnpubChange} > 0 OR totalunpubcount > -1 * {totalUnpubChange}
						THEN totalunpubcount + ({totalUnpubChange})
					ELSE 0 END
				WHERE nodeid IN ({nodeid})'),
		'UpdateParentTextCount' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node SET
				textcount =
					CASE WHEN {textChange} > 0 OR textcount > -1 * {textChange}
						THEN textcount + ({textChange})
					ELSE 0 END,
				textunpubcount =
					CASE WHEN {textUnpubChange} > 0 OR textunpubcount > -1 * {textUnpubChange}
						THEN textunpubcount + ({textUnpubChange})
					ELSE 0 END
				WHERE nodeid IN ({nodeid})'),
		'getChildContentTypes' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT DISTINCT contenttypeid FROM {TABLE_PREFIX}node AS node INNER JOIN {TABLE_PREFIX}closure AS cl ON cl.child = node.nodeid
			WHERE cl.parent IN ({nodeid})'),
		'updateUserPostCountsOnDelete' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' =>'UPDATE {TABLE_PREFIX}user AS user INNER JOIN
				(
				SELECT node.userid, count(nodeid) AS deleted FROM {TABLE_PREFIX}node AS node
				INNER JOIN {TABLE_PREFIX}closure AS cl ON cl.child = node.nodeid
				WHERE cl.parent = {nodeid} AND node.contenttypeid IN ({contenttypes}) AND node.showpublished AND node.showapproved AND
				(node.parentid = node.starter OR node.nodeid = node.starter)
				GROUP BY node.userid) AS deleting ON deleting. userid = user.userid
				SET user.posts =  CASE WHEN deleting.deleted > user.posts then 0 ELSE (user.posts - deleted) END'),
		'setLastData' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS node INNER JOIN {TABLE_PREFIX}closure AS cl
				ON node.nodeid = cl.parent
				SET node.lastcontent = CASE WHEN {lastcontent} >= node.lastcontent THEN {lastcontent} ELSE node.lastcontent END,
				node.lastcontentid = CASE WHEN {lastcontent} >= node.lastcontent THEN {nodeid} ELSE node.lastcontentid END,
				node.lastcontentauthor = CASE WHEN {lastcontent} >= node.lastcontent THEN {lastcontentauthor} ELSE node.lastcontentauthor END,
				node.lastauthorid = CASE WHEN {lastcontent} >= node.lastcontent THEN {lastauthorid} ELSE node.lastauthorid END
				WHERE cl.child = {nodeid} AND cl.depth > 0'),
		'updateLastData' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}node AS node
			LEFT JOIN (
				SELECT nodeid, starter, parentid, authorname, userid, n2.publishdate, created FROM {TABLE_PREFIX}node AS n2
				INNER JOIN {TABLE_PREFIX}closure AS cl2 USE INDEX (parent_2, publishdate) ON cl2.child = n2.nodeid
				WHERE cl2.parent = {parentid} AND cl2.depth > 0
					AND n2.inlist > 0 AND n2.publishdate <= {timenow} AND n2.showpublished > 0 AND n2.showapproved > 0
				ORDER BY cl2.publishdate DESC, cl2.child DESC LIMIT 1
				) AS latest ON (latest.parentid = node.nodeid OR latest.starter = node.nodeid)
			SET node.lastcontent = GREATEST(node.created, IFNULL(latest.created, 0)),
				node.lastcontentid = COALESCE(latest.nodeid, CASE WHEN node.showapproved > 0 AND node.showpublished > 0 THEN node.nodeid ELSE 0 END),
				node.lastcontentauthor = COALESCE(latest.authorname, CASE WHEN node.showapproved > 0 AND node.showpublished > 0 THEN node.authorname ELSE '' END),
				node.lastauthorid = COALESCE(latest.userid, CASE WHEN node.showapproved > 0 AND node.showpublished > 0 THEN node.userid ELSE 0 END),
				node.lastupdate = {timenow}
			WHERE node.nodeid = {parentid}"),
		'fixNodeLast' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => 'UPDATE {TABLE_PREFIX}node AS node LEFT JOIN
			( SELECT lastcontent, lastcontentid, lastcontentauthor, lastauthorid, parentid  FROM {TABLE_PREFIX}node WHERE parentid = {nodeid}
			ORDER BY lastcontent DESC, lastcontentid DESC LIMIT 1 )
			AS lc ON lc.parentid = node.nodeid
			SET node.lastcontent = lc.lastcontent,
			node.lastcontentauthor = lc.lastcontentauthor,
			node.lastcontentid = lc.lastcontentid, node.lastauthorid = lc.lastauthorid
			WHERE nodeid = {nodeid}'),
		'getLastData' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT nodeid, authorname, userid, n.publishdate
			FROM {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}closure AS cl ON cl.child = n.nodeid
			WHERE cl.parent = {parentid} AND cl.depth > 0
				AND n.inlist > 0 AND n.publishdate <= {timenow} AND n.showpublished > 0 AND n.showapproved > 0
			ORDER BY n.publishdate DESC, n.nodeid DESC LIMIT 1'),

		'addClosure' => array (vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
		'query_string' => 'INSERT INTO {TABLE_PREFIX}closure (parent, child, depth, publishdate)
			SELECT parent.parent, node.nodeid, parent.depth + 1, node.publishdate
			FROM {TABLE_PREFIX}node AS node
			INNER JOIN {TABLE_PREFIX}closure AS parent ON parent.child = node.parentid
			WHERE  node.nodeid = {nodeid}'),

		'getCanonicalTags' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
		'query_string' => "SELECT t.tagtext, p.tagtext as canonicaltagtext
			FROM {TABLE_PREFIX}tag t JOIN
			{TABLE_PREFIX}tag p ON t.canonicaltagid = p.tagid
			WHERE t.tagtext IN ({tags})"),

		'addTagContent' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}tagnode (nodeid, tagid, userid, dateline)
			SELECT {nodeid}, tag.tagid, {userid}, {dateline}
			FROM {TABLE_PREFIX}tag AS tag
			LEFT JOIN {TABLE_PREFIX}tagnode AS tn ON
				tn.nodeid = {nodeid} AND tn.tagid = tag.tagid AND tn.userid = {userid}
			WHERE tagtext IN ({tags})  AND tn.tagid IS NULL'
			),

		'copyTagContent' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}tagnode
				(nodeid, tagid, userid, dateline)
			SELECT {nodeid}, tn.tagid, tn.userid, tn.dateline
			FROM {TABLE_PREFIX}tagnode AS tn
			LEFT JOIN {TABLE_PREFIX}tagnode AS tn2 ON tn2.nodeid = {nodeid}
			 AND tn.tagid = tn2.tagid
			WHERE tn.nodeid IN ({sourceid}) AND tn2.tagid IS NULL LIMIT {#limit}'
			),

		'mergeTagContent' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => 'INSERT INTO {TABLE_PREFIX}tagnode
				(nodeid, tagid, userid, dateline)
			SELECT {nodeid}, tn.tagid, tn.userid, tn.dateline
			FROM {TABLE_PREFIX}tagnode AS tn
			LEFT JOIN {TABLE_PREFIX}tagnode AS tn2 ON tn2.nodeid = {nodeid}
			 AND tn2.tagid = tn.tagid
			WHERE tn.nodeid IN ({sourceid}) AND tn2.tagid IS NULL LIMIT {#limit}'
			),

		'getNodeTagList' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => '
				SELECT node.taglist
				FROM {TABLE_PREFIX}node as node
				WHERE node.nodeid = {nodeid}
			'),

		'getTagContent' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => ' SELECT tag.tagtext, IF(tagnode.tagid IS NULL, 0, 1) AS tagincontent, tagnode.userid
				FROM {TABLE_PREFIX}tag AS tag
				LEFT JOIN {TABLE_PREFIX}tagnode AS tagnode ON
				(tag.tagid = tagnode.tagid AND tagnode.nodeid = {nodeid})
				WHERE tag.tagtext IN ({tags})
			'),

		'getTagCount' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => " SELECT COUNT(*) AS count
					FROM {TABLE_PREFIX}tagnode AS tagnode
					WHERE nodeid = {nodeid}	AND userid = {userid}
			"),

		'getTags' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT tag.tagtext, tagnode.userid, tag.tagid
			FROM {TABLE_PREFIX}tag AS tag
			JOIN {TABLE_PREFIX}tagnode AS tagnode ON (tag.tagid = tagnode.tagid)
			WHERE tagnode.nodeid = {nodeid} ORDER BY tag.tagtext" ),
		'fetchproduct' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT * FROM {TABLE_PREFIX}product ORDER BY title
			"
		),

		'fetchchangedtemplates' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT tCustom.templateid, tCustom.title, tCustom.styleid,
				tCustom.username AS customuser, tCustom.dateline AS customdate, tCustom.version AS customversion,
				tCustom.mergestatus AS custommergestatus,
				tGlobal.username AS globaluser, tGlobal.dateline AS globaldate, tGlobal.version AS globalversion,
				tGlobal.product, templatemerge.savedtemplateid
				FROM {TABLE_PREFIX}template AS tCustom
				INNER JOIN {TABLE_PREFIX}template AS tGlobal ON
					(tGlobal.styleid = -1 AND tGlobal.title = tCustom.title)
				LEFT JOIN {TABLE_PREFIX}templatemerge AS templatemerge ON
					(templatemerge.templateid = tCustom.templateid)
				WHERE tCustom.styleid <> -1
					AND tCustom.templatetype = 'template' AND tCustom.mergestatus IN ('merged', 'conflicted')
				ORDER BY tCustom.title
			"
		),
		'fetchstyles2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT styleid, title, parentlist, parentid, userselect
				FROM {TABLE_PREFIX}style
				ORDER BY parentid
			"
		),
		'fetchstylebyid' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT * FROM {TABLE_PREFIX}style WHERE styleid = {styleid}
			"
		),
		'updatestyleparent' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}style
				SET parentlist = {parentlist}
				WHERE styleid = {styleid}
			"
		),
		'updatestyletemplatelist' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}style
				SET templatelist = {templatelist}
				WHERE styleid = {styleid}
			"
		),
		'fetchprofilefields' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT profilefieldid, type, data, optional
				FROM {TABLE_PREFIX}profilefield
			"
		),
		'fetchCustomProfileFields' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT pf.profilefieldcategoryid, pfc.location, pf.*
				FROM {TABLE_PREFIX}profilefield AS pf
				LEFT JOIN {TABLE_PREFIX}profilefieldcategory AS pfc ON(pfc.profilefieldcategoryid = pf.profilefieldcategoryid)
				WHERE pf.form = 0 AND hidden IN ({hidden})
				ORDER BY pfc.displayorder, pf.displayorder
			"
		),

		// cache_templates()
		'fetchtemplates' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT title, template
				FROM {TABLE_PREFIX}template
				WHERE templateid IN ({templateids})
			"
		),

		// fetch_dismissed_notice()
		'fetchdismissednotices' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT noticeid
				FROM {TABLE_PREFIX}noticedismissed AS noticedismissed
				WHERE noticedismissed.userid = {userid}
			"
		),

		// Notice API
		'dismissnotice' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				REPLACE INTO {TABLE_PREFIX}noticedismissed
					(noticeid, userid)
				VALUES
					({noticeid}, {userid})
			"
		),

		// Update filedataresize
		'replaceIntoFiledataResize' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				REPLACE INTO {TABLE_PREFIX}filedataresize
					(filedataid, resize_type, resize_filedata, resize_filesize, resize_dateline, resize_width, resize_height)
				VALUES
					({filedataid}, {resize_type}, {resize_filedata}, {resize_filesize}, {resize_dateline}, {resize_width}, {resize_height})
			"
		),

		// vBTemplate::fetch_template
		'fetchtemplate' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT template
				FROM {TABLE_PREFIX}template
				WHERE templateid = {templateid}
			"
		),

		// can_moderate()
		'supermodcheck' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT usergroupid
				FROM {TABLE_PREFIX}usergroup
				WHERE usergroupid IN ({usergroupids})
					AND (adminpermissions & {ismoderator}) != 0
				LIMIT 1
			"
		),
		'fetchusermembergroups' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT usergroupid, membergroupids
				FROM {TABLE_PREFIX}user
				WHERE userid = {userid}
			"
		),
		'fetchLegacyAttachments' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT n.oldid, n.nodeid, fd.*, fdr.resize_filesize, IF (fdr.resize_filesize > 0, 1, 0) AS hasthumbnail,
				fdr.resize_dateline
				FROM {TABLE_PREFIX}attach AS a INNER JOIN {TABLE_PREFIX}node AS n
				ON n.nodeid = a.nodeid
				INNER JOIN {TABLE_PREFIX}filedata AS fd ON fd.filedataid = a.filedataid
				LEFT JOIN {TABLE_PREFIX}filedataresize AS fdr ON (fd.filedataid = fdr.filedataid AND fdr.resize_type = 'thumb')
				WHERE n.oldid IN ({oldids}) AND n.oldcontenttypeid IN ({oldcontenttypeid})
				ORDER BY n.displayorder
			"
		),
		'fetchLegacyPostIds' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				(SELECT nodeid, oldid, starter, routeid
				FROM {TABLE_PREFIX}node
				WHERE oldid IN ({oldids}) AND oldcontenttypeid = {postContentTypeId})
				UNION
				(SELECT t.nodeid, t.postid as oldid, n.starter, n.routeid
				FROM {TABLE_PREFIX}thread_post t
				INNER JOIN {TABLE_PREFIX}node n ON n.nodeid = t.nodeid
				WHERE t.postid IN ({oldids}))
			"
		),
		'fetchAttach' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT a.*
				FROM {TABLE_PREFIX}attach AS a INNER JOIN {TABLE_PREFIX}node AS n
				ON n.nodeid = a.nodeid
				WHERE n.nodeid IN ({nodeid})
				ORDER BY n.displayorder
			"
		),
		'fetchAttach2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT a.*, f.userid, f.extension, f.dateline, fdr.resize_dateline, f.filesize, f.filehash,
					fdr.resize_filesize, f.width, f.height, fdr.resize_width, fdr.resize_height, f.refcount,
					f.publicview
				FROM {TABLE_PREFIX}attach AS a
				INNER JOIN {TABLE_PREFIX}filedata AS f ON (a.filedataid = f.filedataid)
				LEFT JOIN {TABLE_PREFIX}filedataresize AS fdr ON (f.filedataid = fdr.filedataid AND fdr.resize_type = 'thumb')
				ON f.filedataid = a.filedataid
				WHERE a.filedataid IN ({filedataid})
			"
		),
		'fetchNodeAttachments' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT
					IFNULL(a.filedataid, IFNULL(p.filedataid, 0)) AS filedataid,
					n.nodeid, n.parentid,
					a.visible, a.counter, a.posthash, a.filename, a.caption, reportthreadid, a.settings,
					p.caption, p.height, p.width, p.style
				FROM {TABLE_PREFIX}node AS n
				LEFT JOIN {TABLE_PREFIX}attach AS a ON a.nodeid = n.nodeid
				LEFT JOIN {TABLE_PREFIX}photo AS p ON p.nodeid = n.nodeid
				WHERE n.parentid IN ({nodeid})
				AND (a.nodeid IS NOT NULL OR p.nodeid IS NOT NULL)
				ORDER BY n.displayorder
			"
		),

		'filteredTagsCount' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT count(tn.tagid) as filteredTags
			FROM {TABLE_PREFIX}tagnode AS tn
			LEFT JOIN {TABLE_PREFIX}tagnode AS tn2 ON (tn.tagid = tn2.tagid AND tn2.nodeid = {targetid})
			WHERE tn.nodeid = {sourceid} AND tn2.tagid IS NULL" ),

		'deleteTags' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "DELETE tn.*
			FROM {TABLE_PREFIX}tag AS tag
			LEFT JOIN {TABLE_PREFIX}tagnode AS tn ON (tag.tagid = tn.tagid)
			WHERE tn.nodeid = {nodeid} AND tag.tagtext NOT IN ({ignoredTags})"),

		// build_datastore()
		'insertdatastore' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "
				REPLACE INTO {TABLE_PREFIX}datastore
					(title, data, unserialize)
				VALUES
					({title}, {data}, {unserialize})
			"
		),

		// build_style()
		'fetchtemplatewithspecial' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT title, template, templatetype
				FROM {TABLE_PREFIX}template
				WHERE templateid IN ({templateids})
					AND (templatetype <> 'template' OR title IN({specialtemplates}))
			"
		),
		'fetchphrasetypes' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => ' SELECT fieldname
		    FROM {TABLE_PREFIX}phrasetype
		    WHERE editrows <> 0 AND
		     special = 0',
				),

		// build_language()
		'fetchphrasetypes' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT fieldname
				FROM {TABLE_PREFIX}phrasetype
				WHERE editrows <> 0 AND
					special = 0
			",
		),
		'fetchphrasesbyfieldname' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT fieldname, varname, text
				FROM {TABLE_PREFIX}phrase
				WHERE languageid IN(-1,0) AND
					fieldname IN ({gettypes})
			",
		),
		'fetchphrasesbylanguageandfield' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT varname, text, fieldname
				FROM {TABLE_PREFIX}phrase
				WHERE languageid = {languageid} AND fieldname IN ({gettypes})
			",
		),

		// build_ranks()
		'fetchranks' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT ranklevel AS l, minposts AS m, rankimg AS i, type AS t, stack AS s, display AS d, ranks.usergroupid AS u
				FROM {TABLE_PREFIX}ranks AS ranks
				LEFT JOIN {TABLE_PREFIX}usergroup AS usergroup USING (usergroupid)
				ORDER BY ranks.usergroupid DESC, minposts DESC
			",
		),

		// build_user_statistics()
		'fetchnewuserstats' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT userid, username FROM {TABLE_PREFIX}user WHERE userid = {userid} AND usergroupid NOT IN (3,4)
			",
		),

		// build_userlist()
		'fetchuserlists' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT user.*, userlist.type FROM {TABLE_PREFIX}userlist AS userlist
					INNER JOIN {TABLE_PREFIX}user AS user ON (user.userid = userlist.relationid)
				WHERE userlist.userid = {userid}
			",
		),

		// build_picture_comment_counters()
		'updateattachpicturecomment' => array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}attachment AS attachment
				INNER JOIN {TABLE_PREFIX}picturecomment AS picturecomment ON (attachment.filedataid = picturecomment.filedataid AND attachment.userid = picturecomment.userid)
				SET
					picturecomment.messageread = 1
				WHERE
					attachment.contenttypeid = {contenttypeid}
						AND
					attachment.userid = {userid}
						AND
					picturecomment.postuserid IN ({coventry})
			",
		),
		'fetchunreadpicturecommentcount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*) AS unread
				FROM {TABLE_PREFIX}attachment AS attachment
				INNER JOIN {TABLE_PREFIX}picturecomment AS picturecomment ON (attachment.filedataid = picturecomment.filedataid AND attachment.userid = picturecomment.userid)
				WHERE
					attachment.contenttypeid = {contenttypeid}
						AND
					attachment.userid = {userid}
						AND
					picturecomment.state = 'visible'
						AND
					picturecomment.messageread = 0
			",
		),
		'setStarter' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}closure AS c ON n.nodeid = c.child AND c.parent = {nodeid}
			SET n.starter = {starter}",
				),

		// Poll api
		'poll_fetchvotes' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*)
				FROM {TABLE_PREFIX}pollvote as pollvote
				WHERE pollvote.nodeid = {nodeid}
			",
		),
		'poll_fetchvotes_multiple'=> array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(DISTINCT(userid))
				FROM {TABLE_PREFIX}pollvote as pollvote
				WHERE pollvote.nodeid = {nodeid}
			",
		),
		'getDefaultStyleVars' =>  array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT stylevardfn.stylevarid, stylevardfn.datatype, stylevar.value
				FROM {TABLE_PREFIX}stylevardfn AS stylevardfn
				LEFT JOIN {TABLE_PREFIX}stylevar AS stylevar ON (stylevardfn.stylevarid = stylevar.stylevarid AND stylevar.styleid = -1)"),
		'getStylesFromList' =>  array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT stylevarid, styleid, value, INSTR(CONCAT(',' , {parentlist} , ','), CONCAT(',', styleid, ',') ) AS ordercontrol
				FROM {TABLE_PREFIX}stylevar
				WHERE styleid IN ({stylelist})
				ORDER BY ordercontrol DESC"),
		'getPhotos' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT p.*, node.routeid, node.contenttypeid, node.publishdate, node.unpublishdate,
		node.userid, node.groupid, node.authorname, node.description, node.keywords,
		node.title, node.htmltitle, node.parentid, node.urlident, node.displayorder,
		node.created,node.lastcontent,node.lastcontentid,node.lastcontentauthor,node.lastauthorid,
		node.lastprefixid,node.textcount,node.textunpubcount,node.totalcount,node.totalunpubcount,node.ipaddress,
		node.nextupdate, node.lastupdate, node.showpublished, node.featured, node.starter, node.crc32 FROM
		 {TABLE_PREFIX}photo AS p INNER JOIN {TABLE_PREFIX}node AS node ON node.nodeid = p.nodeid
		WHERE node.parentid IN ({parentid})"),
		// channel info for widgets
		'getChannelInfo' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT parent.title, parent.routeid
				FROM {TABLE_PREFIX}node AS parent INNER JOIN {TABLE_PREFIX}node AS child ON child.parentid =parent.nodeid
				WHERE child.nodeid IN ({nodeid})
			"),
		'getChannelInfoExport' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => '
				SELECT c.*, n.*, r.guid AS routeguid, parent.guid AS parentguid
				FROM {TABLE_PREFIX}channel c
				INNER JOIN {TABLE_PREFIX}node n ON c.nodeid = n.nodeid
				LEFT JOIN {TABLE_PREFIX}channel parent ON n.parentid = parent.nodeid
				INNER JOIN {TABLE_PREFIX}routenew r ON r.routeid = n.routeid
				ORDER BY n.parentid, n.nodeid
			'
		),
		'getAvailableBlogChannelParents' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => '
				SELECT c.nodeid, n.title
				  FROM {TABLE_PREFIX}channel c
        	INNER JOIN {TABLE_PREFIX}node n ON c.nodeid = n.nodeid
			 LEFT JOIN {TABLE_PREFIX}channel as pc ON pc.nodeid = n.parentid
				 WHERE pc.guid <> {defBlogParent}
			  ORDER BY n.parentid, n.nodeid
			'
		),
		'getChannelWidgetInfo' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => '
				SELECT c.*, n.*
				FROM {TABLE_PREFIX}channel c
				INNER JOIN {TABLE_PREFIX}node n ON c.nodeid = n.nodeid
				WHERE c.nodeid > 1
				ORDER BY n.nodeid
			'
		),
		'incrementUserPostCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}user
				SET posts = posts + 1, lastpost = {timenow}
				WHERE userid = {userid}
			",
		),
		'decrementUserPostCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
				UPDATE {TABLE_PREFIX}user
				SET posts = posts - 1
				WHERE userid = {userid} AND posts > 0
			",
		),
		'fetchUserPostsCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*) as posts FROM {TABLE_PREFIX}node
				WHERE userid = {userid}
			",
		),

		/*
		// user socialgroups
		'getUserSocialGroups' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT count(sgm.userid) AS socialgroups
						FROM {TABLE_PREFIX}socialgroupmember AS sgm
						WHERE sgm.userid = {userid} AND sgm.type='member'"
		),
		*/
		// user referral count
		'getReferralsCount' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT count(referrerid) AS referrals
						FROM {TABLE_PREFIX}user
						WHERE referrerid = {userid}"
		),
		'getNodeFollowers' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT u.username, u.userid, sd.discussionid as nodeid
				FROM {TABLE_PREFIX}subscribediscussion AS sd
				INNER JOIN {TABLE_PREFIX}user AS u ON sd.userid = u.userid
				WHERE sd.discussionid IN ({nodeid})
				ORDER BY u.username ASC
				LIMIT {#limit_start}, {#limit}
				"
		),
		'getNodeFollowersCount' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT COUNT(*) as nr
				FROM {TABLE_PREFIX}subscribediscussion AS sd
				INNER JOIN {TABLE_PREFIX}user AS u ON sd.userid = u.userid
				WHERE sd.discussionid = {nodeid}
				"
		),

		// get user followers
		'getFollowers' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT u.userid, u.username AS username, IFNULL(u.lastactivity, u.joindate) as lastactivity,
				IFNULL((SELECT userid FROM {TABLE_PREFIX}userlist AS ul2 WHERE ul2.userid = {userid} AND ul2.relationid = u.userid AND ul2.type = 'follow' AND ul2.friend = 'yes'), 0) as isFollowing,
				IFNULL((SELECT userid FROM {TABLE_PREFIX}userlist AS ul2 WHERE ul2.userid = {userid} AND ul2.relationid = u.userid AND ul2.type = 'follow' AND ul2.friend = 'pending'), 0) as isPending
				FROM {TABLE_PREFIX}user AS u
				INNER JOIN {TABLE_PREFIX}userlist AS ul ON (u.userid = ul.userid AND ul.relationid = {userid})
				WHERE ul.type = 'follow' AND ul.friend = 'yes'
				ORDER BY lastactivity DESC, username ASC
				LIMIT {#limit_start}, {#limit}
				"
		),

		// delete following from user with all his/her posts
		'deleteMemberFollowing' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'query_string' => "DELETE sd.*
				FROM {TABLE_PREFIX}subscribediscussion AS sd
				INNER JOIN {TABLE_PREFIX}node AS n ON (n.nodeid = sd.discussionid)
				WHERE n.userid = {memberid} AND sd.userid = {userid}
				"
		),

		// delete following from channel with all related posts
		'deleteChannelFollowing' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'query_string' => "DELETE sd.*
				FROM {TABLE_PREFIX}node AS n
				INNER JOIN {TABLE_PREFIX}subscribediscussion AS sd ON (n.nodeid = sd.discussionid)
				WHERE n.parentid = {channelid} AND sd.userid = {userid}
				"
		),
		// summary of unread messages
		'messageSummary' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT f.folderid, f.titlephrase, f.title, count(node.nodeid)-count(i.type) AS qty
			FROM {TABLE_PREFIX}messagefolder AS f
			LEFT JOIN {TABLE_PREFIX}sentto AS s ON s.folderid = f.folderid AND s.deleted = 0 AND s.msgread = 0
			LEFT JOIN {TABLE_PREFIX}node AS node ON s.nodeid = node.nodeid AND node.nodeid = node.starter
			LEFT JOIN {TABLE_PREFIX}userlist AS i ON (i.userid = f.userid AND i.relationid = node.userid AND i.type = 'ignore')
			WHERE f.userid = {userid}
			GROUP BY f.folderid
			ORDER BY f.titlephrase, f.title"
		),
		// Get the last nodeid for a PM thread. (This should be the latest reply)
		'lastNodeids' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT MAX(node.nodeid) AS nodeid
			FROM {TABLE_PREFIX}node AS node
			INNER JOIN {TABLE_PREFIX}sentto AS s ON s.nodeid = node.nodeid
			INNER JOIN {TABLE_PREFIX}privatemessage AS pm ON pm.nodeid = node.nodeid
			WHERE s.userid = {userid} AND s.msgread=0 AND s.deleted = 0 AND s.folderid NOT IN ({excludeFolders}) AND pm.msgtype = 'message'
			GROUP BY node.starter"
		),

		//Get the ignored user id
		'getIgnoredUserids' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT i.relationid AS userid FROM {TABLE_PREFIX}userlist AS i WHERE i.userid = {userid} AND i.type = 'ignore'"
		),

		//Get the preview page for messages.
		'pmPreview' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "(SELECT n.nodeid, n.routeid, n.publishdate, n.unpublishdate, n.userid , n.authorname, n.title, n.starter, n.lastcontent, n.lastcontentid,
			n.lastcontentauthor, n.lastauthorid, n.textcount, n.totalcount, n.ipaddress, text.rawtext, text.pagetext, text.previewtext, pm.msgtype, s.folderid, 'messages' AS titlephrase, u.username, starter.routeid,
			s.msgread, pm.about, pm.aboutid, n.routeid AS aboutrouteid, starter.title AS abouttitle, starter.userid AS starteruserid, starter.created AS startercreated, starter.lastcontent AS lastpublishdate, poll.votes, poll.lastvote, starter.starter AS starter_parent,
			NULL AS aboutstarterid, NULL as aboutstartertitle, NULL AS aboutstarterrouteid
			FROM {TABLE_PREFIX}privatemessage AS pm
			INNER JOIN {TABLE_PREFIX}text AS text ON text.nodeid = pm.nodeid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = pm.nodeid
			INNER JOIN {TABLE_PREFIX}node AS starter ON starter.nodeid = n.starter
			INNER JOIN {TABLE_PREFIX}sentto AS s ON s.nodeid = pm.nodeid
			INNER JOIN {TABLE_PREFIX}messagefolder AS f ON f.folderid = s.folderid
			INNER JOIN {TABLE_PREFIX}user AS u ON u.userid = n.userid
			LEFT JOIN {TABLE_PREFIX}poll AS poll ON poll.nodeid = pm.aboutid
			WHERE s.userid = {userid} AND s.msgread=0 AND s.deleted = 0 AND n.userid NOT IN ({ignoreUsers}) AND s.folderid NOT IN ({excludeFolders}) AND pm.msgtype = 'message'
			AND ifnull(f.title, '') = '' AND n.nodeid IN ({nodeids})
			ORDER BY n.created DESC
			LIMIT 5)
			UNION
			(SELECT n.nodeid, n.routeid, n.publishdate, n.unpublishdate, n.userid , n.authorname, n.title, n.starter, n.lastcontent, n.lastcontentid,
			n.lastcontentauthor, n.lastauthorid, n.textcount, n.totalcount, n.ipaddress, text.rawtext, text.pagetext, text.previewtext, pm.msgtype, s.folderid,'requests' AS titlephrase, u.username, starter.routeid,
			s.msgread, pm.about, pm.aboutid, n.routeid AS aboutrouteid, starter.title AS abouttitle, starter.userid AS starteruserid, starter.created AS startercreated, starter.lastcontent AS lastpublishdate, poll.votes, poll.lastvote, starter.starter AS starter_parent,
			NULL AS aboutstarterid, NULL as aboutstartertitle, NULL AS aboutstarterrouteid
			FROM {TABLE_PREFIX}privatemessage AS pm
			INNER JOIN {TABLE_PREFIX}text AS text ON text.nodeid = pm.nodeid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = pm.nodeid
			INNER JOIN {TABLE_PREFIX}node AS starter ON starter.nodeid = n.starter
			INNER JOIN {TABLE_PREFIX}sentto AS s ON s.nodeid = pm.nodeid
			INNER JOIN {TABLE_PREFIX}user AS u ON u.userid = n.userid
			LEFT JOIN {TABLE_PREFIX}poll AS poll ON poll.nodeid = pm.aboutid
			WHERE s.userid = {userid} AND s.msgread=0 AND s.deleted = 0 AND n.userid NOT IN ({ignoreUsers}) AND pm.msgtype = 'request'
			ORDER BY n.created DESC
			LIMIT 5)
			UNION
			(SELECT n.nodeid, about.routeid, n.publishdate, n.unpublishdate, n.userid , n.authorname, about.title, about.nodeid, about.lastcontent, about.lastcontentid,
			about.lastcontentauthor, about.lastauthorid, about.textcount, about.totalcount, n.ipaddress, text.rawtext, text.pagetext, text.previewtext, pm.msgtype, s.folderid, 'notifications' AS titlephrase, u.username, about.routeid,
			s.msgread, pm.about, pm.aboutid, about.routeid AS aboutrouteid, about.title AS abouttitle, about.userid AS aboutuserid, about.created AS aboutcreated,
			about.lastcontent AS lastpublishdate, poll.votes, poll.lastvote, about.starter AS about_parent,
			about.starter AS aboutstarterid, starter.title as aboutstartertitle, starter.routeid AS aboutstarterrouteid
			FROM {TABLE_PREFIX}privatemessage AS pm
			INNER JOIN {TABLE_PREFIX}text AS text ON text.nodeid = pm.nodeid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = pm.nodeid
			INNER JOIN {TABLE_PREFIX}sentto AS s ON s.nodeid = pm.nodeid
			LEFT JOIN {TABLE_PREFIX}node AS about ON about.nodeid = pm.aboutid
			LEFT JOIN {TABLE_PREFIX}node AS starter ON about.starter = starter.nodeid
			LEFT JOIN {TABLE_PREFIX}user AS u ON u.userid = about.lastauthorid
			LEFT JOIN {TABLE_PREFIX}poll AS poll ON poll.nodeid = pm.aboutid
			WHERE s.userid = {userid} AND s.msgread=0 AND s.deleted = 0 AND n.userid NOT IN ({ignoreUsers}) AND pm.msgtype = 'notification'
			ORDER BY n.created DESC
			LIMIT 5)"
		),
		// count of undeleted messages for this user
		'getUnreadMsgCount' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT count(node.nodeid) AS qty
			FROM {TABLE_PREFIX}sentto AS s
			JOIN {TABLE_PREFIX}node AS node ON s.nodeid = node.nodeid AND node.nodeid = node.starter
			LEFT JOIN {TABLE_PREFIX}userlist AS i ON i.userid = s.userid AND i.relationid = node.userid AND i.type = 'ignore'
			WHERE s.userid = {userid} AND s.deleted = 0 AND s.msgread = 0 AND i.type is NULL"
		),
		// count of undeleted system (No Pms) messages for this user
		'getUnreadSystemMsgCount' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT count(node.nodeid) AS qty
			FROM {TABLE_PREFIX}sentto AS s
			JOIN {TABLE_PREFIX}messagefolder AS f ON f.folderid = s.folderid AND f.titlephrase IN ('your_notifications', 'requests', 'pending_posts')
			JOIN {TABLE_PREFIX}node AS node ON node.nodeid = s.nodeid AND node.nodeid = node.starter
			LEFT JOIN {TABLE_PREFIX}userlist AS i ON i.userid = s.userid AND i.relationid = node.userid AND i.type = 'ignore'
			WHERE s.userid = {userid} AND s.deleted = 0 AND s.msgread = 0 AND i.type is NULL"
		),
		// count of open reports.
		// TODO: Change count(report.nodeid) to count(distinct(report.reportnodeid)) when implementing grouping reports via nodeid
		'getOpenReportsCount' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT count(report.nodeid) AS qty FROM {TABLE_PREFIX}report AS report
			WHERE  report.closed = 0"
		),
		// count of undeleted messages in this folder
		'getMsgCountInFolder' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT CASE WHEN s.deleted = 1 THEN 1 ELSE 0 END as deleted, n.nodeid
			FROM {TABLE_PREFIX}sentto AS s
			INNER JOIN {TABLE_PREFIX}messagefolder AS m ON m.folderid = s.folderid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = s.nodeid
			WHERE  m.folderid = {folderid}
			GROUP BY n.starter
			HAVING deleted = 0"
		),
		// count of undeleted messages in this folder with an about limit- for notifications
		'getMsgCountInFolderAbout' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT CASE WHEN s.deleted = 1 THEN 1 ELSE 0 END as deleted, n.nodeid
			FROM {TABLE_PREFIX}sentto AS s
			INNER JOIN {TABLE_PREFIX}messagefolder AS m ON m.folderid = s.folderid
			INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = s.nodeid
			INNER JOIN {TABLE_PREFIX}privatemessage AS pm ON pm.nodeid = n.nodeid
			WHERE  m.folderid = {folderid} AND pm.about IN ({about})
			GROUP BY n.starter
			HAVING deleted = 0"
		),
		// Id and name of all "other" recipients of an email.
		'getPMRecipients' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT s.nodeid, s.userid, u.username FROM
			{TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}sentto AS s ON s.nodeid = n.starter
			INNER JOIN {TABLE_PREFIX}user AS u ON u.userid = s.userid
			WHERE n.nodeid IN ({nodeid}) AND u.userid NOT IN ({userid})
			ORDER BY s.nodeid"
		),
		'getRecipientsForNode' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT s.nodeid, s.userid, folder.titlephrase AS folder
			FROM {TABLE_PREFIX}sentto AS s
			INNER JOIN {TABLE_PREFIX}messagefolder AS folder ON folder.folderid = s.folderid
			WHERE s.nodeid IN ({nodeid})"
		),
		'getPMRecipientsForMessage' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT s.userid, u.username, n.nodeid AS starter
			FROM {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}sentto AS s ON s.nodeid = n.nodeid AND n.nodeid = n.starter
			INNER JOIN {TABLE_PREFIX}user AS u ON u.userid = s.userid
			WHERE n.nodeid IN ({nodeid})
			ORDER BY n.nodeid"
		),
		'getPMRecipientsForMessageOverlay' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT s.userid, u.username, n.nodeid AS starter
			FROM {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}sentto AS s ON s.nodeid = n.nodeid AND n.nodeid = n.starter
			INNER JOIN {TABLE_PREFIX}user AS u ON u.userid = s.userid
			WHERE n.nodeid IN ({nodeid})
			ORDER BY s.nodeid, u.username"
		),
		'getPMLastAuthor' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT nodeinfo.nodeid, (CASE when u1.username IS NULL THEN u.username ELSE u1.username END) AS username,
			(CASE when u1.userid IS NULL THEN u.userid ELSE u1.userid END) AS userid
			FROM
			(
				SELECT node.nodeid, MAX(cl.child) AS child
				FROM {TABLE_PREFIX}node AS node
				LEFT JOIN {TABLE_PREFIX}closure AS cl ON cl.parent = node.nodeid
				LEFT JOIN {TABLE_PREFIX}node AS child ON child.nodeid = cl.child
				WHERE node.nodeid IN ({nodeid}) AND child.userid <> {userid}
				GROUP BY node.nodeid
			) AS nodeinfo
			INNER JOIN {TABLE_PREFIX}sentto AS sentto ON (sentto.nodeid = nodeinfo.nodeid AND sentto.userid <> {userid})
			INNER JOIN {TABLE_PREFIX}user AS u ON u.userid = sentto.userid
			LEFT JOIN {TABLE_PREFIX}node AS reply ON reply.nodeid = nodeinfo.child
			INNER JOIN {TABLE_PREFIX}user AS u1 ON u1.userid = reply.userid
			GROUP BY nodeinfo.nodeid"
		),
		'getPrivateMessageTree' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT node.*, u.username, cl.depth, t.pagetext, t.rawtext, t.previewtext,
				pm.msgtype, pm.about, pm.aboutid, s.msgread
				FROM {TABLE_PREFIX}node AS node
				INNER JOIN {TABLE_PREFIX}closure AS cl ON cl.child = node.nodeid
				INNER JOIN {TABLE_PREFIX}user AS u ON u.userid = node.userid
				INNER JOIN {TABLE_PREFIX}text AS t ON t.nodeid = node.nodeid
				INNER JOIN {TABLE_PREFIX}privatemessage AS pm ON pm.nodeid = node.nodeid
				INNER JOIN {TABLE_PREFIX}sentto AS s ON s.nodeid = node.nodeid
				WHERE cl.parent = {nodeid} AND s.userid = {userid}
				ORDER BY cl.depth, node.publishdate"
		),
		'getPrivateMessageForward' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
		'query_string' => "SELECT messagenode.nodeid AS messageid, node.*, t.rawtext, t.pagetext, u.username
		FROM {TABLE_PREFIX}node AS messagenode
		INNER JOIN {TABLE_PREFIX}node AS node ON node.nodeid = messagenode.starter
		INNER JOIN {TABLE_PREFIX}text AS t ON t.nodeid = messagenode.nodeid
		LEFT JOIN {TABLE_PREFIX}sentto AS s ON s.nodeid = messagenode.nodeid AND s.userid <> messagenode.userid
		LEFT JOIN {TABLE_PREFIX}user AS u ON u.userid = s.userid
		WHERE messagenode.nodeid IN ({nodeid})
		ORDER BY messagenode.nodeid, u.username"),
		'fetchNotification' => array(
		vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
		'query_string' => "SELECT pm.nodeid, pm.deleted, s.msgread FROM {TABLE_PREFIX}privatemessage AS pm
				INNER JOIN {TABLE_PREFIX}sentto AS s ON s.nodeid = pm.nodeid
				WHERE pm.aboutid = {aboutid} AND pm.about = {about} AND s.userid = {userid}"
		),
		'fetchThreadNotification' => array(
		vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
		'query_string' => "SELECT pm.nodeid, pm.deleted, s.msgread FROM {TABLE_PREFIX}privatemessage AS pm
				INNER JOIN {TABLE_PREFIX}sentto AS s ON s.nodeid = pm.nodeid
				INNER JOIN {TABLE_PREFIX}node AS replywitholdcomment ON replywitholdcomment.nodeid = pm.aboutid
				INNER JOIN {TABLE_PREFIX}node AS replywithnewcomment ON replywithnewcomment.starter = replywitholdcomment.starter
				WHERE replywithnewcomment.nodeid = {aboutid} AND pm.about = {about} AND s.userid = {userid}"
		),
		'fetchParticipants' => array(
		vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT u.*, CASE WHEN list.userid IS NULL then 0 WHEN list.friend = 'pending' then 2 ELSE 1 END as following
		FROM {TABLE_PREFIX}closure AS cl
		INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = cl.child AND cl.depth > 0
		INNER JOIN {TABLE_PREFIX}user AS u ON u.userid = n.userid
		LEFT JOIN {TABLE_PREFIX}userlist AS list ON list.userid = {currentuser} AND list.relationid = n.userid AND type='follow'
		WHERE cl.parent = {nodeid} AND NOT n.userid IN ({exclude})"
		),
		'getNodeVotes' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT nodeid FROM {TABLE_PREFIX}reputation WHERE whoadded = {userid} AND nodeid IN ({nodeid})"
		),
		'updateNodeVotes' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}node
				SET votes = (SELECT COUNT(*) FROM {TABLE_PREFIX}reputation WHERE nodeid={nodeid})
				WHERE nodeid = {nodeid}"
		),
		//AdminCP - FAQ Queries
		'getDistinctProduct' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT DISTINCT product FROM {TABLE_PREFIX}phrase
			WHERE varname IN ({phraseDeleteNamesSql}) AND fieldname IN ('faqtitle', 'faqtext')"
		),
		'replaceIntoFaq' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "REPLACE INTO {TABLE_PREFIX}faq (faqname, faqparent, displayorder, volatile, product)
			VALUES ({faqname}, {faqparent}, {displayorder}, {volatile}, {product})"
		),
		'getDistinctProductFAQ' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT DISTINCT product FROM {TABLE_PREFIX}faq AS faq
			WHERE faqname IN ({faqnames})"
		),
		'getDistinctScriptHelp' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT DISTINCT script FROM {TABLE_PREFIX}adminhelp"
		),
		//AdminCP - USERGROUP Queries
		'getUserGroupPermissions' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT usergroup.usergroupid, title,
			(COUNT(permission.permissionid) /**+ COUNT(calendarpermission.calendarpermissionid)**/) AS permcount
			FROM {TABLE_PREFIX}usergroup AS usergroup
			LEFT JOIN {TABLE_PREFIX}permission AS permission ON (usergroup.usergroupid = permission.groupid)
			/**LEFT JOIN {TABLE_PREFIX}calendarpermission AS calendarpermission ON (usergroup.usergroupid = calendarpermission.usergroupid)**/
			GROUP BY usergroup.usergroupid
			HAVING permcount > 0
			ORDER BY title"
		),
		'getUserGroupCountById' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT COUNT(*) AS usergroups FROM {TABLE_PREFIX}usergroup
			WHERE (adminpermissions & {cancontrolpanel}) AND usergroupid <> {usergroupid}"
		),
		'updateUserOptions' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}user SET options = (options & ~{bf_misc_useroptions})
			WHERE usergroupid = {usergroupid}"
		),
		'getUserGroupId' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT usergroupid FROM {TABLE_PREFIX}usergroup WHERE genericpermissions & {bf_ugp_genericpermissions}"
		),
		'getUserIdByAdministrator' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT user.userid FROM {TABLE_PREFIX}user AS user
			LEFT JOIN {TABLE_PREFIX}administrator as administrator ON (user.userid = administrator.userid)
			WHERE administrator.userid IS NULL AND user.usergroupid = {usergroupid}"
		),
		'getUserIdNotIn' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT userid FROM {TABLE_PREFIX}user WHERE usergroupid NOT IN {ausergroupids}
			AND NOT FIND_IN_SET('{ausergroupids}', membergroupids)
			AND (usergroupid = {usergroupid} OR FIND_IN_SET('{usergroupid}', membergroupids))"
		),
		'replaceIntoPrefixPermission' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "REPLACE INTO {TABLE_PREFIX}prefixpermission (usergroupid, prefixid)
			SELECT {newugid}, prefixid FROM {TABLE_PREFIX}prefix
			WHERE options & {bf_misc_prefixoptions}"
		),

		'getUsergroupWithTags' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT usergroupid, opentag, closetag FROM {TABLE_PREFIX}usergroup
			WHERE opentag <> '' OR closetag <> ''"
		),
		'deleteUserPromotion' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'query_string' => "DELETE FROM {TABLE_PREFIX}userpromotion WHERE usergroupid = {usergroupid}
			OR joinusergroupid = {usergroupid}"
		),
		'getUsersByMemberGroups' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT userid, username, membergroupids
			FROM {TABLE_PREFIX}user
			WHERE FIND_IN_SET('{usergroupid}', membergroupids)"
		),
		'getPrimaryUsersCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT user.usergroupid, COUNT(user.userid) AS total
			FROM {TABLE_PREFIX}user AS user
			LEFT JOIN {TABLE_PREFIX}usergroup AS usergroup USING (usergroupid)
			WHERE usergroup.usergroupid IS NOT NULL
			GROUP BY usergroupid"
		),
		'getUserGroupReqeustCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT usergroupid, COUNT(userid) AS total
			FROM {TABLE_PREFIX}usergrouprequest AS usergrouprequest
			GROUP BY usergroupid"
		),
		'getLeadersByUser' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT usergroupleader.*, username
			FROM {TABLE_PREFIX}usergroupleader AS usergroupleader
			INNER JOIN {TABLE_PREFIX}user AS user USING(userid)"
		),
		'getUserGroupIdCountByPromotion' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT COUNT(*) AS count, usergroupid
			FROM {TABLE_PREFIX}userpromotion GROUP BY usergroupid"
		),
		'getUserPromotionsAndUserGroups' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT userpromotion.*, usergroup.title
			FROM {TABLE_PREFIX}userpromotion AS userpromotion, {TABLE_PREFIX}usergroup AS usergroup
			WHERE userpromotionid = {userpromotionid} AND userpromotion.usergroupid = usergroup.usergroupid"
		),
		'getUserGroupRequests' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT req.userid, user.username, user.usergroupid, user.membergroupids, req.usergrouprequestid
			FROM {TABLE_PREFIX}usergrouprequest AS req
			INNER JOIN {TABLE_PREFIX}user AS user USING(userid)
			WHERE usergrouprequestid IN ({auth})
			ORDER BY user.username"
		),
		'updateUserMemberGroupsByUserId' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}user SET
			membergroupids = IF(membergroupids = '', {usergroupid}, CONCAT(membergroupids, ',{usergroupid}'))
			WHERE userid IN ({auth})"
		),
		'getUserGroupsWithJoinRequests' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT req.usergroupid, COUNT(req.usergrouprequestid) AS requests,
			IF(usergroup.usergroupid IS NULL, 0, 1) AS validgroup
			FROM {TABLE_PREFIX}usergrouprequest AS req
			LEFT JOIN {TABLE_PREFIX}usergroup AS usergroup ON (usergroup.usergroupid = req.usergroupid)
			LEFT JOIN {TABLE_PREFIX}user AS user ON (user.userid = req.userid)
			WHERE user.userid IS NOT NULL
			GROUP BY req.usergroupid"
		),
		'getUserGroupLeaders' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT usergroupleader.userid, user.username
			FROM {TABLE_PREFIX}usergroupleader AS usergroupleader
			INNER JOIN {TABLE_PREFIX}user AS user USING(userid)
			WHERE usergroupleader.usergroupid = {usergroupid}"
		),
		'getUserGroupRequests' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT req.*, user.username
			FROM {TABLE_PREFIX}usergrouprequest AS req
			INNER JOIN {TABLE_PREFIX}user AS user USING(userid)
			WHERE req.usergroupid = {usergroupid}
			ORDER BY user.username"
		),
		// Reputation API
		'reputation_userreputationlevel' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT reputationlevelid
				FROM {TABLE_PREFIX}reputationlevel
				WHERE {reputation} >= minimumreputation
				ORDER BY minimumreputation
				DESC LIMIT 1
			"
		),
		'reputation_fetchwhovoted' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT reputation.reputation, user.userid, user.username, user.usergroupid
				FROM {TABLE_PREFIX}reputation AS reputation
				INNER JOIN {TABLE_PREFIX}user AS user ON (user.userid = reputation.whoadded)
				WHERE reputation.nodeid = {nodeid}
				ORDER BY reputation.dateline DESC
			"
		),
		'reputation_privatemsg_fetchwhovoted' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT reputation.reputation, user.userid, user.username, CASE WHEN list.userid IS NULL then 0 WHEN list.friend = 'pending' then 2 ELSE 1 END as following
				FROM {TABLE_PREFIX}reputation AS reputation
				INNER JOIN {TABLE_PREFIX}user AS user ON (user.userid = reputation.whoadded)
				LEFT JOIN {TABLE_PREFIX}userlist AS list ON list.userid = {currentuser} AND list.relationid = reputation.whoadded AND type='follow'
				WHERE reputation.nodeid = {nodeid}
				ORDER BY reputation.dateline DESC
			"
		),
		'reputation_votecount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT COUNT(*)
				FROM {TABLE_PREFIX}reputation
				WHERE nodeid = {nodeid}
			"
		),
		'getDeletedMsgs' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT pm.nodeid
					FROM {TABLE_PREFIX}privatemessage AS pm
					WHERE pm.deleted > 0 AND pm.deleted <= {deleteLimit}
					LIMIT {#limit}"
		),
		//AdminCP - BOOKMARK SITES Queries
		'getMaxDisplayOrder' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT MAX(displayorder) AS displayorder FROM {TABLE_PREFIX}bookmarksite"
		),
		//AdminCP - IMAGES Queries
		'fetchUsergroupImageCategories' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT usergroup.*, imagecategoryid AS nopermission FROM {TABLE_PREFIX}usergroup AS usergroup
			LEFT JOIN {TABLE_PREFIX}imagecategorypermission AS imgperm ON
			(imgperm.usergroupid = usergroup.usergroupid AND imgperm.imagecategoryid = {imagecategoryid})
			ORDER BY title"
		),
		'fetchSmilieId' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT smilieid
			FROM {TABLE_PREFIX}smilie WHERE BINARY smilietext = {smilietext}"
		),
		'fetchAvatarsPermissions' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT imagecategory.imagecategoryid, COUNT(avatarid) AS avatars
			FROM {TABLE_PREFIX}imagecategory AS imagecategory
			LEFT JOIN {TABLE_PREFIX}avatar AS avatar ON (avatar.imagecategoryid=imagecategory.imagecategoryid)
			WHERE imagetype = 1
			GROUP BY imagecategory.imagecategoryid
			HAVING avatars > 0"
		),
		'fetchImagesWithoutPermissions' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT usergroupid, COUNT(*) AS count
			FROM {TABLE_PREFIX}imagecategorypermission
			WHERE imagecategoryid IN ({cats})
			GROUP BY usergroupid
			HAVING count = {catsCount}"
		),
		'updateSettingValues' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}setting SET value =
			CASE varname
				WHEN {path} THEN {imagepath}
				WHEN {url} THEN {imageurl}
			ELSE value END
			WHERE varname IN({path}, {url})"
		),
		'updateSettingValuesByVarname' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}setting SET value =
			CASE varname
				WHEN 'avatarpath' THEN {avatarpath}
				WHEN 'avatarurl' THEN {avatarurl}
				WHEN 'profilepicpath' THEN {profilepicpath}
				WHEN 'profilepicurl' THEN {profilepicurl}
				WHEN 'sigpicpath' THEN {sigpicpath}
				WHEN 'sigpicurl' THEN {sigpicurl}
			ELSE value END
			WHERE varname IN('avatarpath', 'avatarurl', 'profilepicurl', 'profilepicpath', 'sigpicurl', 'sigpicpath')"
		),
		'fetchAvatarsForUsers' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => 'SELECT user.userid, avatar.avatarid, avatar.avatarpath, user.avatarrevision,
					customavatar.dateline, customavatar.width, customavatar.height, customavatar.height_thumb, customavatar.width_thumb, customavatar.filename
			FROM {TABLE_PREFIX}user as user
				LEFT JOIN {TABLE_PREFIX}customavatar AS customavatar ON customavatar.userid = user.userid
				LEFT JOIN {TABLE_PREFIX}avatar AS avatar ON avatar.avatarid = user.avatarid
			WHERE user.userid IN ({userid})'
		),
		'fetchAvatarInfo' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT user.userid, user.avatarrevision, user.profilepicrevision, user.sigpicrevision,
				customavatar.filename AS afilename, customavatar.filedata AS afiledata, customavatar.extension AS aextension,
				customavatar.filedata_thumb AS afiledata_thumb,
				customprofilepic.filename AS pfilename, customprofilepic.filedata AS pfiledata,
				sigpic.filename AS sfilename, sigpic.filedata AS sfiledata
			FROM {TABLE_PREFIX}user AS user
			LEFT JOIN {TABLE_PREFIX}customavatar AS customavatar ON (user.userid = customavatar.userid)
			LEFT JOIN {TABLE_PREFIX}customprofilepic AS customprofilepic ON (user.userid = customprofilepic.userid)
			LEFT JOIN {TABLE_PREFIX}sigpic AS sigpic ON (user.userid = sigpic.userid)
			WHERE NOT ISNULL(customavatar.userid) OR NOT ISNULL(customprofilepic.userid) OR NOT ISNULL(sigpic.userid)
			ORDER BY user.userid ASC
			LIMIT {#limit_start}, {#limit}"
		),
		'fetchUserIdByAvatar' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT user.userid
			FROM {TABLE_PREFIX}user AS user
			LEFT JOIN {TABLE_PREFIX}customavatar AS customavatar ON (user.userid = customavatar.userid)
			LEFT JOIN {TABLE_PREFIX}customprofilepic AS customprofilepic ON (user.userid = customprofilepic.userid)
			LEFT JOIN {TABLE_PREFIX}sigpic AS sigpic ON (user.userid = sigpic.userid)
			WHERE user.userid > {lastuser}
			AND (NOT ISNULL(customavatar.userid) OR NOT ISNULL(customprofilepic.userid) OR NOT ISNULL(sigpic.userid))
			LIMIT 1"
		),
		'closeNode' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}closure AS c
				INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = c.child
				SET n.showopen = 0
				WHERE c.parent = {nodeid}
				"
		),
		'openNode' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}closure AS c
			INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = c.child
			LEFT JOIN (SELECT child.child AS nodeid
			  FROM {TABLE_PREFIX}node AS cls INNER JOIN {TABLE_PREFIX}closure AS chk ON
			  chk.child = cls.nodeid AND cls.open = 0 AND chk.parent = {nodeid}
			  INNER JOIN  {TABLE_PREFIX}closure AS child ON child.parent = cls.nodeid)
			AS closed ON closed.nodeid = n.nodeid
			SET n.showopen = 1
			WHERE c.parent = {nodeid} AND closed.nodeid IS NULL AND n.open > 0"
		),
		'unapproveNode' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'query_string' => "UPDATE {TABLE_PREFIX}closure AS c
				INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = c.child
				SET n.showapproved = 0
				WHERE c.parent IN ({nodeid})
				"
		),
		// approve Node
		'approveNode' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'query_string' => "UPDATE {TABLE_PREFIX}closure AS c
				INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = c.child
				LEFT JOIN (
					SELECT DISTINCT child.child AS nodeid
					FROM {TABLE_PREFIX}closure AS child
					INNER JOIN  {TABLE_PREFIX}closure AS parent ON parent.child = child.child AND child.parent IN ({nodeid})
					INNER JOIN {TABLE_PREFIX}node AS chknode ON chknode.nodeid = parent.parent AND chknode.approved = 0
				) AS chk ON chk.nodeid = n.nodeid
				SET n.showapproved = 1
				WHERE c.parent IN ({nodeid}) AND chk.nodeid IS NULL AND n.approved = 1"
		),
		'unPublishNode' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'query_string' => "UPDATE {TABLE_PREFIX}closure AS c
				INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = c.child
				SET n.showpublished = 0
				WHERE c.parent IN({nodeid})
				"
		),
		'publishNode' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'query_string' => "UPDATE {TABLE_PREFIX}closure AS c
				INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = c.child
				LEFT JOIN (
					SELECT child.child AS nodeid
					FROM {TABLE_PREFIX}node AS cls
					INNER JOIN {TABLE_PREFIX}closure AS chk ON
						chk.child = cls.nodeid AND cls.deleteuserid <> 0 AND cls.deleteuserid IS NOT NULL AND chk.parent = {nodeid}
					INNER JOIN  {TABLE_PREFIX}closure AS child ON
						child.parent = cls.nodeid
				) AS deleted ON deleted.nodeid = n.nodeid
				SET n.showpublished = 1
				WHERE c.parent = {nodeid}"
		),

		//AdminCP - TAG Queries
		'getTagsBySynonym' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT t.tagtext, p.tagtext as canonicaltagtext
			FROM {TABLE_PREFIX}tag t JOIN {TABLE_PREFIX}tag p ON t.canonicaltagid = p.tagid
			WHERE t.tagtext IN ({tags})"
		),
		'insertIgnoreTagContent2' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT IGNORE INTO {TABLE_PREFIX}tagnode (nodeid, tagid, userid, dateline)
			VALUES({id}, {tagid}, {userid}, {time})"
		),
		'getContentCounts' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT
				SUM(CASE WHEN showpublished = 1 AND showapproved = 1 AND node.parentid = {parentid} THEN 1 ELSE 0 END) AS textcount,
				SUM(CASE WHEN (showpublished = 0 OR showapproved = 0) AND node.parentid = {parentid} THEN 1 ELSE 0 END) AS textunpubcount,
				SUM(CASE WHEN showpublished = 1 AND showapproved = 1 THEN 1 ELSE 0 END) AS totalcount,
				SUM(CASE WHEN (showpublished = 0 OR showapproved = 0) THEN 1 ELSE 0 END) AS totalunpubcount
				FROM {TABLE_PREFIX}node AS node INNER JOIN {TABLE_PREFIX}closure AS cl ON cl.child = node.nodeid
				WHERE node.contenttypeid NOT IN ({excludeTypes})
				AND cl.parent = {parentid} AND node.nodeid <> {parentid}"
		),
		'getDirectContentCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT COUNT(*)
				FROM {TABLE_PREFIX}node AS node INNER JOIN {TABLE_PREFIX}closure AS cl ON cl.child = node.nodeid AND cl.depth = 1
				WHERE node.contenttypeid NOT IN ({excludeTypes})
				AND cl.parent = {parentid} AND node.nodeid <> {parentid}"
		),
		'fetchQuestions' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT question.questionid, question.regex, question.dateline, COUNT(*) AS answers, phrase.text, answer.answerid
			FROM {TABLE_PREFIX}hvquestion AS question
			LEFT JOIN {TABLE_PREFIX}phrase AS phrase ON (phrase.varname = CONCAT('question', question.questionid) AND phrase.fieldname = 'hvquestion' and languageid = 0)
			LEFT JOIN {TABLE_PREFIX}hvanswer AS answer ON (question.questionid = answer.questionid)
			GROUP BY question.questionid
			ORDER BY dateline"
		),
		'fetchQuestionById' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT question.questionid, question.regex, question.dateline, phrase.text
			FROM {TABLE_PREFIX}hvquestion AS question
			LEFT JOIN {TABLE_PREFIX}phrase AS phrase ON (phrase.varname = CONCAT('question', question.questionid) AND phrase.fieldname = 'hvquestion' and languageid = 0)
			LEFT JOIN {TABLE_PREFIX}hvanswer AS answer ON (question.questionid = answer.questionid)
			WHERE question.questionid = {questionid}"
		),
		'fetchQuestionByAnswer' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT question.questionid, phrase.text
			FROM {TABLE_PREFIX}hvquestion AS question
			LEFT JOIN {TABLE_PREFIX}phrase AS phrase ON (phrase.varname = CONCAT('question', question.questionid) AND phrase.fieldname = 'hvquestion' and languageid = 0)
			WHERE question.questionid = {questionid}"
		),
		'fetchQuestionByPhrase' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT questionid, phrase.text
			FROM {TABLE_PREFIX}hvquestion AS question
			LEFT JOIN {TABLE_PREFIX}phrase AS phrase ON (phrase.varname = CONCAT('question', question.questionid) AND phrase.fieldname = 'hvquestion' and languageid = 0)
			WHERE questionid = {questionid}"
		),
		'fetchAttachStatsAvarage' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT COUNT(*) AS count, SUM(filesize) AS totalsize, SUM(counter) AS downloads
			FROM {TABLE_PREFIX}attach AS a
			INNER JOIN {TABLE_PREFIX}filedata AS fd ON (a.filedataid = fd.filedataid)"
		),
		'fetchAttachStatsTotal' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT COUNT(*) AS count, SUM(filesize) AS totalsize
			FROM {TABLE_PREFIX}filedata AS fd"
		),
		'fetchAttachStatsLargestUser' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT COUNT(*) AS count, SUM(filesize) AS totalsize, user.userid, username
			FROM {TABLE_PREFIX}attach AS a
			INNER JOIN {TABLE_PREFIX}filedata AS fd ON (a.filedataid = fd.filedataid)
			LEFT JOIN {TABLE_PREFIX}user AS user ON (fd.userid = user.userid)
			GROUP BY fd.userid
			HAVING totalsize > 0
			ORDER BY totalsize DESC
			LIMIT 5"
		),
		'fetchTopAttachmentsCounter' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT a.nodeid, a.counter, a.filedataid, fd.dateline, a.filename, node.authorname
			FROM {TABLE_PREFIX}attach AS a
			INNER JOIN {TABLE_PREFIX}filedata AS fd ON (a.filedataid = fd.filedataid)
			LEFT JOIN {TABLE_PREFIX}attachmenttype AS at ON (at.extension = fd.extension)
			INNER JOIN {TABLE_PREFIX}node AS node ON (node.nodeid = a.nodeid)
			LEFT JOIN {TABLE_PREFIX}user AS user ON (fd.userid = user.userid)
			ORDER BY a.counter DESC
			LIMIT 5"
		),
		'fetchTopAttachmentsSize' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT a.nodeid, fd.filesize, a.filedataid, fd.dateline, a.filename, node.authorname
			FROM {TABLE_PREFIX}attach AS a
			INNER JOIN {TABLE_PREFIX}filedata AS fd ON (a.filedataid = fd.filedataid)
			LEFT JOIN {TABLE_PREFIX}attachmenttype AS at ON (at.extension = fd.extension)
			INNER JOIN {TABLE_PREFIX}node AS node ON (node.nodeid = a.nodeid)
			LEFT JOIN {TABLE_PREFIX}user AS user ON (fd.userid = user.userid)
			ORDER BY fd.filesize DESC
			LIMIT 5"
		),
		'fetchAttach' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT node.*, fd.filesize, a.filedataid, a.filename, a.visible, a.counter
				FROM {TABLE_PREFIX}node AS node
				INNER JOIN {TABLE_PREFIX}attach AS a ON (node.nodeid = a.nodeid)
				INNER JOIN {TABLE_PREFIX}filedata AS fd ON (a.filedataid = fd.filedataid)
				WHERE node.nodeid = {nodeid}"
		),
		'fetchAttachPerms' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT attachmentpermission.*
				FROM {TABLE_PREFIX}attachmentpermission AS attachmentpermission
				INNER JOIN {TABLE_PREFIX}usergroup AS usergroup ON (usergroup.usergroupid = attachmentpermission.usergroupid)
				WHERE attachmentpermissionid = {attachmentpermissionid}"
		),
		'fetchAttachPermsByExtension' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT atype.extension, atype.height AS default_height,
					atype.width AS default_width,
					atype.size AS default_size,
					atype.mimetype AS mimetype,
					aperm.height AS custom_height,
					aperm.width AS custom_width,
					aperm.size AS custom_size,
					aperm.attachmentpermissions AS custom_permissions, aperm.usergroupid
				FROM {TABLE_PREFIX}attachmenttype AS atype
				LEFT JOIN {TABLE_PREFIX}attachmentpermission AS aperm
				ON atype.extension = aperm.extension
				WHERE atype.extension={extension}"
		),
		'replaceAttachPerms' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'query_string' => "REPLACE INTO {TABLE_PREFIX}attachmentpermission
			(usergroupid, extension, attachmentpermissions, height, width, size)
			VALUES
			({usergroupid}, {extension}, {attachmentpermissions}, {height}, {width}, {size})
			"
		),
		'fetchAllAttachPerms' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT
					atype.extension, atype.height AS default_height, atype.width AS default_width, atype.size AS default_size, atype.contenttypes,
					aperm.height AS custom_height, aperm.width AS custom_width, aperm.size AS custom_size,
					aperm.attachmentpermissions AS custom_permissions, aperm.usergroupid
				FROM {TABLE_PREFIX}attachmenttype AS atype
				LEFT JOIN {TABLE_PREFIX}attachmentpermission AS aperm USING (extension)
				ORDER BY extension"
		),
		'fetchMinFiledataId' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT MIN(filedataid) AS min FROM {TABLE_PREFIX}filedata"
		),
		'fetchTotalAttach' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT COUNT(*) AS count FROM {TABLE_PREFIX}filedata"
		),
		'fetchFiledataLimit' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT filedataid, userid
					FROM {TABLE_PREFIX}filedata
					ORDER BY userid DESC, filedataid ASC
					LIMIT {#limit_start}, {#limit}"
		),
		//Node Stats
		'fetchVisitsByNodeDate' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT nodeid, dateline, count(*) AS count
			FROM {TABLE_PREFIX}nodevisits
			WHERE dateline >= {starddate} AND dateline <= {enddate}
			GROUP BY nodeid, dateline
			ORDER BY dateline DESC"
		),
		'fetchCronByDate' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT cron.*
			FROM {TABLE_PREFIX}cron AS cron
			LEFT JOIN {TABLE_PREFIX}product AS product ON (cron.product = product.productid)
			WHERE cron.nextrun <= {date} AND cron.active = 1
			AND (product.productid IS NULL OR product.active = 1)
			ORDER BY cron.nextrun
			LIMIT 1"
		),
		'trackNodeVisits' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "INSERT INTO {TABLE_PREFIX}nodevisits (nodeid, visitorid, dateline,totalcount)
			SELECT nodeid, {userid}, {dateline}, totalcount
			FROM {TABLE_PREFIX}node AS node
			WHERE nodeid = {nodeid} AND node.starter = node.nodeid
			ON DUPLICATE KEY UPDATE totalcount = node.totalcount"
		),

		'fetchActiveChannelContributors' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT git.userid, u.username, ug.systemgroupid, ug.usergroupid
			FROM {TABLE_PREFIX}groupintopic git
			INNER JOIN {TABLE_PREFIX}usergroup ug ON (git.groupid = ug.usergroupid)
			INNER JOIN {TABLE_PREFIX}user u ON (git.userid = u.userid)
			WHERE git.nodeid = {nodeid}
			ORDER BY u.username"
		),

		'fetchPendingChannelContributors' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT pm.about, s.userid as recipientid, u.username
			FROM {TABLE_PREFIX}privatemessage pm
			INNER JOIN {TABLE_PREFIX}sentto s ON (pm.nodeid = s.nodeid)
			INNER JOIN {TABLE_PREFIX}user u ON (s.userid = u.userid)
			INNER JOIN {TABLE_PREFIX}node n ON (pm.nodeid = n.nodeid)
			WHERE pm.aboutid = {nodeid} AND pm.msgtype = 'request'
			AND pm.about in ('owner_to', 'moderator_to', 'owner_from', 'moderator',
							 'sg_owner_to', 'sg_moderator_to', 'sg_owner_from', 'sg_moderator')
				AND s.userid != n.userid
			ORDER BY u.username"
		),

		'fetchPendingChannelRequestUser' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT pm.nodeid
			FROM {TABLE_PREFIX}privatemessage pm
			INNER JOIN {TABLE_PREFIX}sentto s ON (pm.nodeid = s.nodeid)
			INNER JOIN {TABLE_PREFIX}user u ON (s.userid = u.userid)
			INNER JOIN {TABLE_PREFIX}node n ON (pm.nodeid = n.nodeid)
			WHERE pm.aboutid = {aboutid}
				AND s.userid != n.userid
				AND s.userid = {userid}
				AND pm.about IN ({about})
			ORDER BY u.username"
		),

		'updateNodePerms' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}closure AS cl ON cl.child = n.nodeid AND cl.parent IN({nodeid})
			SET n.viewperms = {viewperms}, n.commentperms = {commentperms}"
		),
		'groupintopicCount' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT COUNT(*) AS count FROM {TABLE_PREFIX}groupintopic WHERE groupid = {groupid} AND nodeid = {nodeid} "
		),
		'groupintopicPage' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT distinct u.* FROM {TABLE_PREFIX}groupintopic AS g
				INNER JOIN {TABLE_PREFIX}user AS u ON u.userid = g.userid
				WHERE g.nodeid = {nodeid} AND g.groupid = {groupid} ORDER BY u.username LIMIT {#limit_start},{#limit} "
		),
		//AdminCP - ADMINLOG Queries
		'fetchDistinctScript' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT DISTINCT adminlog.script
				FROM {TABLE_PREFIX}adminlog AS adminlog
				ORDER BY script"
		),
		'fetchDistinctUsers' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT DISTINCT adminlog.userid, user.username
				FROM {TABLE_PREFIX}adminlog AS adminlog
				LEFT JOIN {TABLE_PREFIX}user AS user USING(userid)
				ORDER BY username"
		),
		//AdminCP - MODLOG Queries
		'fetchStylesById' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT styleid, newstylevars
				FROM {TABLE_PREFIX}style
				WHERE styleid = {styleid}
				LIMIT 1"
		),

		//AdminCP - ADMINLOG Queries
		'fetchDistinctScript' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT DISTINCT adminlog.script
				FROM {TABLE_PREFIX}adminlog AS adminlog
				ORDER BY script"
		),
		'fetchDistinctUsers' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT DISTINCT adminlog.userid, user.username
				FROM {TABLE_PREFIX}adminlog AS adminlog
				LEFT JOIN {TABLE_PREFIX}user AS user USING(userid)
				ORDER BY username"
		),
		//AdminCP - MODLOG Queries
		'fetchStylesById' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT styleid, newstylevars
				FROM {TABLE_PREFIX}style
				WHERE styleid = {styleid}
				LIMIT 1"
		),
		// approve Node
		'fetchModifyAnnouncements' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT a.announcementid, a.title, a.startdate, a.enddate, a.nodeid, u.username
				FROM {TABLE_PREFIX}announcement AS a
				LEFT JOIN {TABLE_PREFIX}user AS u USING (userid)
				ORDER BY a.startdate
				"
		),
		// get nodes with attachments
		'fetchNodesWithAttachments' => array(
		vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT DISTINCT parent.nodeid FROM
				{TABLE_PREFIX}closure AS cl
				INNER JOIN {TABLE_PREFIX}node AS parent ON parent.nodeid = cl.child
				INNER JOIN {TABLE_PREFIX}node AS image ON image.parentid = parent.nodeid
				WHERE image.contenttypeid in ({contenttypeid}) AND cl.parent IN({channel})"
		),
		// get Albums in a channel
		'fetchGalleriesInChannel' => array(
		vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT DISTINCT node.nodeid FROM
				{TABLE_PREFIX}closure AS cl
				INNER JOIN {TABLE_PREFIX}node AS node ON node.nodeid = cl.child
				WHERE node.contenttypeid in ({contenttypeid}) AND cl.parent IN({channel})"
		),
		'fetchAttachInfo' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT
					fd.filedataid, fd.dateline, fdr.resize_dateline, fd.filesize, IF(fdr.resize_filesize > 0, 1, 0) AS hasthumbnail, fdr.resize_filesize, fd.userid,
					a.nodeid, a.counter, a.filename, a.settings, a.visible, a.caption,
					n.showpublished, n.parentid, n.title
				FROM {TABLE_PREFIX}attach AS a
				INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = a.nodeid
				INNER JOIN {TABLE_PREFIX}filedata AS fd ON fd.filedataid = a.filedataid
				INNER JOIN {TABLE_PREFIX}filedataresize AS fdr ON (fd.filedataid = fdr.filedataid AND fdr.resize_type = 'thumb')
				WHERE n.parentid IN ({parentId}) ORDER BY n.parentid, n.title"
		),
		// needed for print_delete_confirmation [START]
		'getModeratorBasicFields' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT moderator.moderatorid, user.username, node.title
				FROM {TABLE_PREFIX}moderator AS moderator
				INNER JOIN {TABLE_PREFIX}user AS user ON (moderator.userid = user.userid)
				INNER JOIN {TABLE_PREFIX}node AS node ON (node.nodeid = moderator.nodeid)
				WHERE moderatorid = {moderatorid}"
		),
		'getCalendarModeratorBasicFields' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT calendarmoderatorid, username, title
				FROM {TABLE_PREFIX}calendarmoderator AS calendarmoderator
				INNER JOIN {TABLE_PREFIX}user AS user ON (user.userid = calendarmoderator.userid)
				INNER JOIN {TABLE_PREFIX}calendar AS calendar ON (calendar.calendarid = calendarmoderator.calendarid)
				WHERE calendarmoderatorid = {calendarmoderatorid}"
		),
		'getUserPromotionBasicFields' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT userpromotionid, usergroup.title
				FROM {TABLE_PREFIX}userpromotion AS userpromotion
				INNER JOIN {TABLE_PREFIX}usergroup AS usergroup ON (userpromotion.usergroupid = usergroup.usergroupid)
				WHERE userpromotionid = {userpromotionid}"
		),
		'getUserGroupLeaderBasicFields' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT usergroupleaderid, username AS title
				FROM {TABLE_PREFIX}usergroupleader AS usergroupleader
				INNER JOIN {TABLE_PREFIX}user AS user USING (userid)
				WHERE usergroupleaderid = {usergroupleaderid}
			"
		),
		'getAdminHelpBasicFields' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT adminhelpid, phrase.text AS title
				FROM {TABLE_PREFIX}adminhelp AS adminhelp
				LEFT JOIN {TABLE_PREFIX}phrase AS phrase ON (phrase.varname = CONCAT(adminhelp.script, IF(adminhelp.action != '', CONCAT('_', REPLACE(adminhelp.action, ',', '_')), ''), IF(adminhelp.optionname != '', CONCAT('_', adminhelp.optionname), ''), '_title') AND phrase.fieldname = 'cphelptext' AND phrase.languageid IN (-1, 0))
				WHERE adminhelpid = {adminhelpid}
			"
		),
		'getAdminHelpBasicFields' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT adminhelpid, phrase.text AS title
				FROM {TABLE_PREFIX}adminhelp AS adminhelp
				LEFT JOIN {TABLE_PREFIX}phrase AS phrase ON (phrase.varname = CONCAT(adminhelp.script, IF(adminhelp.action != '', CONCAT('_', REPLACE(adminhelp.action, ',', '_')), ''), IF(adminhelp.optionname != '', CONCAT('_', adminhelp.optionname), ''), '_title') AND phrase.fieldname = 'cphelptext' AND phrase.languageid IN (-1, 0))
				WHERE adminhelpid = {adminhelpid}
			"
		),
		'getFaqBasicFields' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT faqname, IF(phrase.text IS NOT NULL, phrase.text, faq.faqname) AS title
				FROM {TABLE_PREFIX}faq AS faq
				LEFT JOIN {TABLE_PREFIX}phrase AS phrase ON (phrase.varname = faq.faqname AND phrase.fieldname = 'faqtitle' AND phrase.languageid IN(-1, 0))
				WHERE faqname = {faqname}
			"
		),
		// needed for print_delete_confirmation [END]

		// adminreputation moving [START]
		'reputationLevelPhraseReplace' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
			'query_string' => "REPLACE INTO {TABLE_PREFIX}phrase
				(languageid, fieldname, varname, text, product, username, dateline, version)
				VALUES
				({languageid}, {fieldname}, {varname}, {text}, {product}, {username}, {dateline}, {version})
			"
		),
		'editReputationInfo' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT rep.*, whoadded.username as whoadded_username, user.username, starter.title, starter.nodeid
				FROM {TABLE_PREFIX}reputation AS rep
				LEFT JOIN {TABLE_PREFIX}user AS user ON (rep.userid = user.userid)
				LEFT JOIN {TABLE_PREFIX}user AS whoadded ON (rep.whoadded = whoadded.userid)
				LEFT JOIN {TABLE_PREFIX}node AS node ON (rep.nodeid = node.nodeid)
				LEFT JOIN {TABLE_PREFIX}node AS starter ON (starter.nodeid = node.starter)
				WHERE reputationid = {reputationid}
			"
		),
		// adminreputation moving [END]

		// admincp - index [START]
		'getFiledataFilesizeSum' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT SUM(filesize) AS size FROM {TABLE_PREFIX}filedata
			"
		),
		'getUserFiledataFilesizeSum' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT SUM(filesize) AS size FROM {TABLE_PREFIX}filedata WHERE userid = {userid}
			"
		),
		'getCustomProfilePicFilesizeSum' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT SUM(filesize) AS size FROM {TABLE_PREFIX}customprofilepic
			"
		),
		'getChangedTemplatesCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT count(*) AS count
				FROM {TABLE_PREFIX}template AS tCustom
				INNER JOIN {TABLE_PREFIX}template AS tGlobal ON
					(tGlobal.styleid = -1 AND tGlobal.title = tCustom.title)
				LEFT JOIN {TABLE_PREFIX}templatemerge AS templatemerge ON
					(templatemerge.templateid = tCustom.templateid)
				WHERE tCustom.styleid <> -1
					AND tCustom.templatetype = 'template' AND tCustom.mergestatus IN ('merged', 'conflicted')
				ORDER BY tCustom.title
			"
		),
		'getIndexNewStartersCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT COUNT(*) AS count
				FROM {TABLE_PREFIX}node
				WHERE showpublished IN (0,1,2)
					AND sticky IN (0,1)
					AND open <> 10
					AND created >= {starttime}
					AND nodeid = starter
			"
		),
		// admincp - index [END]

		// admincp - image [START]
		'getSmilieTextCmp' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT smilieid
				FROM {TABLE_PREFIX}smilie
				WHERE BINARY smilietext = {smilietext}
			"
		),
		// admincp - image [END]

		// admincp - moderator [START]
		'getModGlobalEdit' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT user.username, user.userid,
				moderator.nodeid, moderator.permissions, moderator.permissions2, moderator.moderatorid
				FROM {TABLE_PREFIX}user AS user
				LEFT JOIN {TABLE_PREFIX}moderator AS moderator ON (moderator.userid = user.userid AND moderator.nodeid = 0)
				WHERE user.userid = {userid}
			"
		),
		'getModeratorInfoToUpdate' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT moderator.*,
				user.username, user.usergroupid, user.membergroupids
				FROM {TABLE_PREFIX}moderator AS moderator
				INNER JOIN {TABLE_PREFIX}user AS user USING(userid)
				WHERE moderator.moderatorid = {moderatorid}
			"
		),
		'getSuperGroups' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT user.*, usergroup.usergroupid
				FROM {TABLE_PREFIX}usergroup AS usergroup
				INNER JOIN {TABLE_PREFIX}user AS user ON(user.usergroupid = usergroup.usergroupid OR FIND_IN_SET(usergroup.usergroupid, user.membergroupids))
				WHERE (usergroup.adminpermissions & {ismodpermission})
				GROUP BY user.userid
				ORDER BY user.username
			"
		),
		'getModsFromNodeShowList' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT moderator.moderatorid, user.userid, user.username, user.lastactivity, node.nodeid, node.htmltitle, node.routeid
				FROM {TABLE_PREFIX}node AS node
				INNER JOIN {TABLE_PREFIX}moderator AS moderator ON (moderator.nodeid = node.nodeid)
				INNER JOIN {TABLE_PREFIX}user AS user ON (user.userid = moderator.userid)
				ORDER BY user.username, node.htmltitle
			"
		),
		'checkUserMod' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT username FROM {TABLE_PREFIX}moderator AS moderator
				LEFT JOIN {TABLE_PREFIX}user AS user USING(userid)
				WHERE moderator.userid = {userid}
			"
		),
		'getModUserInfoKillAll' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT user.*,
				IF (user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid
				FROM {TABLE_PREFIX}moderator AS moderator
				LEFT JOIN {TABLE_PREFIX}user AS user USING(userid)
				WHERE moderator.userid = {userid}
					AND nodeid <> -1
			"
		),
		// admincp - moderator [END]

		// admincp - notice [START]
		'doNoticeSwap' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "
			UPDATE {TABLE_PREFIX}notice
			SET displayorder = CASE noticeid
				WHEN {orig_noticeid} THEN {swap_displayorder}
				WHEN {swap_noticeid} THEN {orig_displayorder}
				ELSE displayorder END
			WHERE noticeid IN({orig_noticeid}, {swap_noticeid})
			"
		),
		// admincp - notice [END]

		// admincp - permission [START]
		'getChannelPermissionsByGroup' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT permission.*
			FROM {TABLE_PREFIX}permission AS permission
			INNER JOIN {TABLE_PREFIX}closure AS closure ON closure.parent = permission.nodeid
			WHERE permission.groupid IN ({groupid}) AND closure.child IN ({nodeid})
			ORDER BY closure.depth ASC LIMIT 1
			"
		),
		'getChannelPermissionsForAllGroups' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT permission.*
			FROM {TABLE_PREFIX}permission AS permission
			INNER JOIN {TABLE_PREFIX}closure AS closure ON closure.parent = permission.nodeid
			WHERE closure.child IN ({nodeid})
			ORDER BY permission.groupid ASC, closure.depth ASC
			"
		),
		// admincp - permission [END]

		// admincp - rssfeed [START]
		'getUserRssFeed' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT rssfeed.*, user.username
			FROM {TABLE_PREFIX}rssfeed AS rssfeed
			INNER JOIN {TABLE_PREFIX}user AS user ON(user.userid = rssfeed.userid)
			WHERE rssfeed.rssfeedid = {rssfeedid}
			"
		),
		'getRssFeedsDetailed' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT rssfeed.*, user.username, channel.title AS channeltitle
			FROM {TABLE_PREFIX}rssfeed AS rssfeed
			LEFT JOIN {TABLE_PREFIX}user AS user ON(user.userid = rssfeed.userid)
			LEFT JOIN {TABLE_PREFIX}node AS channel ON(channel.nodeid = rssfeed.nodeid)
			ORDER BY rssfeed.title
			"
		),
		// admincp - rssfeed [END]

		'getDescendantAttachCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT cl.parent AS nodeid, count(a.nodeid) AS count
				FROM {TABLE_PREFIX}closure AS cl
				INNER JOIN {TABLE_PREFIX}attach AS a ON (a.nodeid = cl.child)
				WHERE cl.parent in ({nodeid})
				GROUP BY cl.parent
			"),
		'getDescendantPhotoCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT cl.parent AS nodeid, count(child.nodeid) AS count
				FROM {TABLE_PREFIX}closure AS cl
				INNER JOIN {TABLE_PREFIX}node AS child USE INDEX (nodeid, node_ctypid_userid_dispo_idx) ON (child.nodeid = cl.child)
				WHERE cl.parent in ({nodeid}) AND child.contenttypeid = {photoTypeid}
				GROUP BY cl.parent
			"),

		// admincp - stylevar [START]
		'getExistingStylevars' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT stylevardfn.*, stylevar.styleid AS stylevarstyleid, stylevar.value
			FROM {TABLE_PREFIX}stylevardfn AS stylevardfn
			LEFT JOIN {TABLE_PREFIX}stylevar AS stylevar ON(stylevardfn.stylevarid = stylevar.stylevarid)
			WHERE stylevardfn.stylevarid IN ({stylevarids})
			ORDER BY stylevardfn.stylevargroup, stylevardfn.stylevarid
			"
		),
		'getStylevarsToRevert' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT DISTINCT s1.stylevarid
			FROM {TABLE_PREFIX}stylevar AS s1
			INNER JOIN {TABLE_PREFIX}stylevar AS s2 ON
				(s2.styleid IN ({parentlist}) AND s2.styleid <> {styleid} AND s2.stylevarid = s1.stylevarid)
			WHERE s1.styleid = {styleid}
			"
		),
		// admincp - stylevar [END]

		// admincp - subscriptionpermission [START]
		'getSubscriptionPermissionInfo' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT subscriptionpermission.*
			FROM {TABLE_PREFIX}subscriptionpermission AS subscriptionpermission
			INNER JOIN {TABLE_PREFIX}usergroup AS usergroup ON (usergroup.usergroupid = subscriptionpermission.usergroupid)
			WHERE subscriptionid = {subscriptionid} AND subscriptionpermission.usergroupid = {usergroupid}
			"
		),
		// admincp - subscriptionpermission [END]

		// admincp - subscriptions [START]
		'getSubscriptionLogCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT COUNT(*) as total, subscriptionid
			FROM {TABLE_PREFIX}subscriptionlog
			GROUP BY subscriptionid
			"
		),
		'getActiveSubscriptionLogCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT COUNT(*) as total, subscriptionid
			FROM {TABLE_PREFIX}subscriptionlog
			WHERE status = 1
			GROUP BY subscriptionid
			"
		),
		// admincp - subscriptions [END]

		// admincp - usertools [START]
		'getAvatarLimit' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT *
			FROM {TABLE_PREFIX}avatar
			ORDER BY title LIMIT {startat}, {perpage}
			"
		),
		'getUserPmFolders' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT user.userid, user.username, folder.*
			FROM {TABLE_PREFIX}user AS user
			INNER JOIN {TABLE_PREFIX}messagefolder AS folder ON user.userid = folder.userid
			WHERE user.userid = {userid}
			"
		),
		'getUserPmFoldersCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT COUNT(*) AS messages, folderid
			FROM {TABLE_PREFIX}sentto
			WHERE userid = {userid}
			GROUP BY folderid
			"
		),
		// admincp - usertools [END]
		'getOtherParticipants' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT parent.nodeid, count(distinct child.userid) AS qty FROM {TABLE_PREFIX}node AS parent
			INNER JOIN {TABLE_PREFIX}closure AS cl ON cl.parent = parent.nodeid AND cl.child <> parent.nodeid
			INNER JOIN {TABLE_PREFIX}node AS child ON child.nodeid = cl.child
			WHERE parent.nodeid IN ({nodeids})
			GROUP BY parent.nodeid"),
		'getParticipantsList' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT child.parentid AS parent, child.nodeid, child.userid, user.username, pm.about
			FROM {TABLE_PREFIX}privatemessage AS pm
			INNER JOIN {TABLE_PREFIX}node AS notification ON notification.nodeid = pm.nodeid
			INNER JOIN {TABLE_PREFIX}node AS last_post ON last_post.nodeid = pm.aboutid
			INNER JOIN {TABLE_PREFIX}closure AS cl ON cl.parent = last_post.parentid AND depth = 1
			INNER JOIN {TABLE_PREFIX}node AS child ON cl.child = child.nodeid
			INNER JOIN {TABLE_PREFIX}user as user ON user.userid = child.userid
			WHERE pm.aboutid IN ({nodeids}) AND
				child.nodeid <> child.starter AND child.publishdate >= notification.publishdate
			GROUP BY child.nodeid, pm.about
			ORDER BY child.nodeid DESC
			"),
		'getNodePendingRequest' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT pm.nodeid
			FROM {TABLE_PREFIX}privatemessage AS pm
			INNER JOIN {TABLE_PREFIX}node AS msg ON msg.nodeid = pm.nodeid
			WHERE pm.aboutid IN ({nodeid}) AND msg.userid IN ({userid}) AND pm.about IN ({request})
			"
		),
		'getExistingRequest' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT pm.nodeid, msg.userid
			FROM {TABLE_PREFIX}privatemessage AS pm
			INNER JOIN {TABLE_PREFIX}sentto AS msg ON msg.nodeid = pm.nodeid
			WHERE pm.aboutid IN({nodeid}) AND msg.userid IN ({userid}) AND pm.about = {request}
			"
		),
		'getFolderInfoFromId' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT folderid,
			(CASE WHEN titlephrase IS NOT NULL THEN titlephrase ELSE title END) AS title,
			(CASE WHEN titlephrase IS NOT NULL THEN 0 ELSE 1 END) AS iscustom
			FROM {TABLE_PREFIX}messagefolder
			WHERE folderid IN ({folderid})
			"
		),
		'getTotalUserPhotos' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT COUNT(*) AS total
			FROM {TABLE_PREFIX}node AS gallery
			INNER JOIN {TABLE_PREFIX}node AS photo ON photo.parentid = gallery.nodeid
			WHERE gallery.parentid = {channelid} AND photo.contenttypeid = {contenttypeid}
			AND gallery.userid = {userid}
			"
		),
		'getNumberAlbumPhotos' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT COUNT(*) AS total
			FROM {TABLE_PREFIX}node AS gallery
			INNER JOIN {TABLE_PREFIX}node AS photo ON photo.parentid = gallery.nodeid
			WHERE gallery.nodeid = {albumid} AND photo.contenttypeid = {contenttypeid}
			"
		),
		'getNumberPosthotos' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT COUNT(*) AS total
			FROM {TABLE_PREFIX}node AS gallery
			INNER JOIN {TABLE_PREFIX}node AS photo ON photo.parentid = gallery.nodeid
			WHERE gallery.nodeid = {nodeid} AND photo.contenttypeid = {contenttypeid}
			"
		),
		'getUserPhotosSize' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
			SELECT IFNULL(SUM(fd.filesize), 0) AS totalsize
			FROM {TABLE_PREFIX}node as gallery
			INNER JOIN {TABLE_PREFIX}node as pic ON pic.parentid = gallery.nodeid
			INNER JOIN {TABLE_PREFIX}photo as photo ON photo.nodeid = pic.nodeid
			INNER JOIN {TABLE_PREFIX}filedata as fd ON photo.filedataid = fd.filedataid
			WHERE gallery.parentid = {channelid} and pic.contenttypeid = {contenttypeid}
			AND gallery.userid = {userid}
			"
		),
		'getUserChannelsCount' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT COUNT(node.nodeid) as totalcount
			FROM {TABLE_PREFIX}node AS node
			INNER JOIN {TABLE_PREFIX}closure as cl ON cl.child = node.nodeid
			INNER JOIN {TABLE_PREFIX}channel AS ch ON ch.nodeid = node.nodeid
			WHERE cl.parent = {parent} AND node.userid = {userid} AND ch.category = 0"
		),
		'getChannelTree' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT nodeid FROM {TABLE_PREFIX}node AS node
			INNER JOIN {TABLE_PREFIX}closure AS cl ON cl.child = node.nodeid
			WHERE node.displayorder <> 0 AND cl.parent = {parentid} AND node.contenttypeid = {channelType}
			AND cl.depth <= {depth} AND node.nodeid <> {parentid}
			ORDER BY node.displayorder ASC, node.title ASC "
		),
		'updateChildsViewPerms' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}node AS child
			INNER JOIN {TABLE_PREFIX}node AS starter ON starter.nodeid = child.starter
			SET child.viewperms = {viewperms}
			WHERE starter.parentid = {parentid}",
		),
		'updateChildsNodeoptions' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'query_string' => "UPDATE {TABLE_PREFIX}node AS child
			INNER JOIN {TABLE_PREFIX}closure AS cl ON cl.child = child.nodeid
			RIGHT JOIN {TABLE_PREFIX}node AS father ON cl.parent = father.nodeid
			SET child.nodeoptions = father.nodeoptions
			WHERE cl.parent = {parentid} AND cl.depth > 0",
		),
		'getDataForParse' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT node.*, channel.nodeid AS channelid, channel.options,
				text.rawtext, text.htmlstate
			FROM {TABLE_PREFIX}node AS node
			INNER JOIN {TABLE_PREFIX}node AS starter ON starter.nodeid = node.starter
			INNER JOIN {TABLE_PREFIX}channel AS channel ON channel.nodeid = starter.parentid
			INNER JOIN {TABLE_PREFIX}text AS text ON text.nodeid = node.nodeid
			WHERE node.nodeid IN ({nodeid}) "
		),
		'getRepliesAfterCutoff' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT nodeid, publishdate
				FROM {TABLE_PREFIX}node AS node
				WHERE starter = {starter} AND publishdate > {cutoff}
				ORDER BY publishdate ASC
				LIMIT 10
			",
		),
		'getNodeOptionsList' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "
				SELECT DISTINCT node.nodeid, node.nodeoptions,
				CASE when starter.nodeid IS NULL then -1 ELSE starter.nodeoptions END AS starternodeoptions,
				CASE when channel.nodeid IS NULL then -1 ELSE channel.nodeoptions END AS channelnodeoptions
				FROM {TABLE_PREFIX}node AS node
				LEFT JOIN {TABLE_PREFIX}node AS starter ON starter.nodeid = node.starter
				LEFT JOIN {TABLE_PREFIX}node AS channel ON channel.nodeid = starter.parentid
				WHERE node.nodeid IN ({nodeid})
			",
		),
		'getNotificationPollVoters' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT pv.userid, u.username, n.nodeid AS starter
			FROM {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}pollvote AS pv ON pv.nodeid = n.nodeid AND n.nodeid = n.starter
			INNER JOIN {TABLE_PREFIX}user AS u ON u.userid = pv.userid
			WHERE n.nodeid IN ({nodeid})
			ORDER BY pv.nodeid, u.username"
		),
		'getGitCanStartThreads' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT n.nodeid, n.title FROM {TABLE_PREFIX}closure AS cl
			INNER JOIN {TABLE_PREFIX}node AS n ON n.nodeid = cl.child
			INNER JOIN {TABLE_PREFIX}groupintopic AS git ON git.nodeid = n.nodeid
			WHERE cl.parent = {parentnodeId} AND git.groupid IN ({contributors}) and git.userid = {userid}
			ORDER BY title"
		),
		'verifySubscriberRequest' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT n.nodeid
			FROM {TABLE_PREFIX}node AS n
			INNER JOIN {TABLE_PREFIX}privatemessage AS pm ON n.nodeid = pm.nodeid
			WHERE n.userid = {userid} AND pm.aboutid IN ({nodeid}) and pm.about = ({about})"
		),
		// This query will only work if called immediately after SQL_CALC_FOUND_ROWS
		'getNodeSubscribersTotalCount' => array(
			vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'query_string' => "SELECT FOUND_ROWS() AS total"
		),
	);

	/** Gets the channel children list
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 * **/
	public function getChannel($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//I don't need anything;
			return true;
		}
		else
		{
			//We never return from a protected channel
			$extraFields = "";
			if (!empty($params['showChannel']))
			{
				$extraFields = ', starter.title AS startertitle, starter.totalcount as totalreplies, starter.created AS discussion_started,
 				starter.routeid AS starterrouteid, ch.title AS channeltitle, ch.routeid AS channelrouteid';
			}

			// Let's add following flags
			if (!empty($params[vB_Api_Search::FILTER_FOLLOW]))
			{
				$extraFields .= ', IFNULL(ul.relationid, 0) AS isFollowingMember';
				if ($params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Search::FILTER_FOLLOWING_CHANNEL OR $params[vB_Api_Search::FILTER_FOLLOW] == vB_Api_Search::FILTER_FOLLOWING_BOTH)
				{
					$extraFields .= ', IFNULL(p_sd.discussionid, 0) AS isFollowingContent, IFNULL(sd.discussionid, 0) AS isFollowingChannel';
				}
				else
				{
					$extraFields .= ', IFNULL(p_sd.discussionid, 0) AS isFollowingChannel, IFNULL(sd.discussionid, 0) AS isFollowingContent';
				}

			}

			$permflags = $this->getNodePermTerms();
			//we don't need the starter join. We already have that.

			unset($permflags['joins']['starter']);


			if (empty($params['channel']))
			{
				$params['channel'] = $this->home_page;
			}
			$sql = "SELECT node.*, cl.parent, cl.child, cl.depth, cl.displayorder AS clorder, type.class AS contenttypeclass $extraFields
			FROM " . TABLE_PREFIX . "closure AS cl
			INNER JOIN " . TABLE_PREFIX . "node AS node ON node.nodeid = cl.child
			INNER JOIN " . TABLE_PREFIX . "contenttype AS type ON type.contenttypeid = node.contenttypeid
			LEFT JOIN " . TABLE_PREFIX . "node AS starter ON starter.nodeid = node.starter
			LEFT JOIN " . TABLE_PREFIX . "node AS ch ON ch.nodeid = starter.parentid
			 ";

			if (!empty($permflags['joins']))
			{
				$sql .= implode("\n", $permflags['joins']) . "\n";

			}

			if (!empty($params['exclude']))
			{
				if (!is_array($params['exclude']))
				{
					$params['exclude'] = array($params['exclude']);
				}
				$sql .= "LEFT JOIN  " . TABLE_PREFIX . "closure AS cl2 ON cl2.child = cl.child AND cl2.parent IN (" .
					implode(',',$params['exclude']) . " )\n";
			}

			if (!empty($params['showChannel']))
			{
				$sql .= " LEFT JOIN " . TABLE_PREFIX . "node AS starter ON starter.nodeid = node.starter \n LEFT JOIN " .
					 TABLE_PREFIX . "node AS ch ON ch.nodeid = starter.parentid ";
			}

			/** if we are filtering with following */
			/*
			if (!empty($params[vB_Api_Follow::FOLLOWTYPE]))
			{
				echo "<!--++ -->";
				switch ($params[vB_Api_Follow::FOLLOWTYPE])
				{
					case vB_Api_Follow::FOLLOWTYPE_USERS:
						echo "<!--A -->";
						$sql .= " INNER JOIN " . TABLE_PREFIX . "userlist AS ul ON node.userid = ul.relationid
								  LEFT JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON node.nodeid = sd.discussionid
								  LEFT JOIN " . TABLE_PREFIX . "subscribediscussion AS p_sd ON p_sd.discussionid = node.nodeid";
						break;
					case vB_Api_Search::FILTER_FOLLOWING_CONTENT:
						echo "<!--B -->";
						$sql .= " INNER JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON sd.discussionid = node.nodeid
								  LEFT JOIN " . TABLE_PREFIX . "userlist AS ul ON node.userid = ul.relationid AND ul.userid = " . $params['followerid'] . "
								  LEFT JOIN " . TABLE_PREFIX . "subscribediscussion AS p_sd ON p_sd.discussionid = node.nodeid";
						break;
					case vB_Api_Search::FILTER_FOLLOWING_CHANNEL:
						echo "<!--C -->";
						$sql .= " INNER JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON sd.discussionid = ch.nodeid
								  LEFT JOIN " . TABLE_PREFIX . "userlist AS ul ON node.userid = ul.relationid AND ul.userid = " . $params['followerid'] . "
								  LEFT JOIN " . TABLE_PREFIX . "subscribediscussion AS p_sd ON p_sd.discussionid = node.nodeid";
						break;
					case vB_Api_Search::FILTER_FOLLOWING_BOTH:
						echo "<!--D -->";
						$sql .= " LEFT JOIN " . TABLE_PREFIX . "userlist AS ul ON node.userid = ul.relationid AND ul.userid = " . $params['followerid'] . "\n
								  LEFT JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON ch.nodeid = sd.discussionid AND sd.userid = " . $params['followerid'] . "
								  LEFT JOIN " . TABLE_PREFIX . "subscribediscussion AS p_sd ON p_sd.discussionid = node.nodeid
								  LEFT JOIN " . TABLE_PREFIX . "subscribediscussion AS subscription ON node.nodeid = subscription.discussionid AND subscription.userid = " . $params['followerid'];
					default:
						echo "<!--E -->";
						// just ignore
						break;
				}
			}
			*/
			$sql .= " WHERE cl.parent  = " . $params['channel'] . " AND node.inlist > 0 \n";

			if (empty($params['includeProtected']))
			{
				$sql .= "AND node.protected = 0 \n";
			}
			else
			{
				$currentUserId = vB::getCurrentSession()->get('userid');
				if (empty($currentUserId))
				{
					$sql .= "AND node.protected = 0 \n";
				}
				else
				{
					$sql .= "AND ((node.protected = 0 OR node.userid = $currentUserId))\n";
				}
			}

 			if (empty($params['include_parent']))
 			{
 				$sql .= "AND node.nodeid <> " . $params['channel'] . " \n";
 			}

			if (!empty($params['contenttypeid']))
			{
				if (is_array($params['contenttypeid']))
				{
					$sql .= "AND node.contenttypeid IN (" . implode(', ', $params['contenttypeid']) .") \n";
				}
				else
				{
					$sql .= "AND node.contenttypeid = " . $params['contenttypeid'] . " \n";
				}
			}

			if (!empty($this->params['perm_string']))
			{
				$sql .= " AND (" .$this->params['$perm_string'] . ")\n";
			}

			if (!empty($params['exclude']))
			{
				$sql .= " AND cl2.child IS NULL \n";
			}

			if (!empty($params['depth']) AND intval($params['depth']))
			{
				$sql .= " AND cl.depth <= " . intval($params['depth']) . "\n";
			}

			if (!empty($params['featured']) )
			{
				if ($params['featured'])
				{
					$sql .= " AND node.featured > 0 \n";
				}
				else
				{
					$sql .= " AND node.featured < 1 \n";
				}
			}

			if (!empty($params['userid']))
			{
				$sql .= " AND (node.userid = " . $params['userid'] . ")\n";
			}

			//block people on the global ignore list.
			$options = vB::getDatastore()->get_value('options');
			if (trim($options['globalignore']) != '')
			{
				$blocked = preg_split('#\s+#s', $options['globalignore'], -1, PREG_SPLIT_NO_EMPTY);
				//the user can always see their own posts, so if they are in the blocked list we remove them
				$bbuserkey = array_search(vB::getCurrentSession()->get('userid') , $blocked);

				if ($bbuserkey !== FALSE AND $bbuserkey !== NULL)
				{
					unset($blocked["$bbuserkey"]);
				}

				//Make sure we didn't just empty the list
				if (!empty($blocked))
				{
					$sql .= " AND node.userid NOT IN (" . implode(',', $blocked) . ")";
				}
			}

			/** Date filter */
			if (!empty($params['time']))
			{
				$datenow = vB::getRequest()->getTimeNow();
				switch ($params['time'])
				{
					case vB_Api_Search::FILTER_LASTDAY:
						$timeVal = $datenow - (24 * 60 * 60);
						break;
					case vB_Api_Search::FILTER_LASTWEEK:
						$timeVal = $datenow - (7 * 24 * 60 * 60);
						break;
					case vB_Api_Search::FILTER_LASTMONTH:
					    $timeVal = strtotime(date("Y-m-d H:i:s", $datenow) . " - 1 month");
						break;
					default:
						$timeVal = 0;
						break;
				}
				$sql .= " AND node.publishdate >= $timeVal";
			}

			/** Follow filter */
			if (!empty($params[vB_Api_Follow::FOLLOWTYPE]))
			{
				switch ($params[vB_Api_Follow::FOLLOWTYPE])
				{
					case vB_Api_Follow::FOLLOWTYPE_USERS:
						$sql .= " AND ul.userid = " . $params['followerid'];
						break;
					case vB_Api_Search::FILTER_FOLLOWING_CONTENT:
						$sql .= " AND sd.userid = " . $params['followerid'];
						break;
					case vB_Api_Search::FILTER_FOLLOWING_CHANNEL:
						$sql .= " AND sd.userid = " . $params['followerid'];
						break;
					case vB_Api_Search::FILTER_FOLLOWING_BOTH:
						$sql .= " AND (ul.userid IS NOT NULL OR sd.discussionid IS NOT NULL OR subscription.discussionid IS NOT NULL)";
						break;
					default:
						// just ignore
						break;
				}
				$sql .=  " AND type.class <> 'Channel'";
			}

			if (isset($params['sort']))
			{
				if (is_array($params['sort']))
				{
					$sorts = array();
					foreach ($params['sort'] as $key => $value)
					{
						//we may have something like 'publishdate' => 'desc'
						if (
							($key == 'publishdate')
							OR
							($key == 'unpublishdate')
							OR
							($key == 'authorname')
							OR
							($key == 'displayorder')
						)
						{
							if (strtolower($value) == 'desc')
							{
								$sorts[] = "node.$key DESC";
							}
							else
							{
								$sorts[] = "node.$key ASC";
							}
						}
						else if (
							($value == 'publishdate')
							OR
							($value == 'unpublishdate')
							OR
							($value == 'authorname')
							OR
							($value == 'displayorder')
						)
						{
							$sorts[] = "node.$value ASC";
						}
						else if (
							is_array($value)
							AND
							isset($value['sortby'])
							AND
							(
								($value['sortby'] == 'publishdate')
								OR
								($value['sortby'] == 'unpublishdate')
								OR
								($value['sortby'] == 'authorname')
								OR
								($value['sortby'] == 'displayorder')
							)
						)
						{
							if (
								isset($value['direction'])
								AND
								(strtolower($value['direction']) == 'desc')
							)
							{
								$sorts[] = 'node.' . $value['sortby'] . " DESC";
							}
							else
							{
								$sorts[] = 'node.' . $value['sortby'] . " ASC";
							}

						}

						if (!empty($sorts))
						{
							$sort = implode(', ', $sorts);
						}
					}
				}
				else if (
					($params['sort'] == 'publishdate')
					OR
					($params['sort'] == 'unpublishdate')
					OR
					($params['sort'] == 'authorname')
					OR
					($params['sort'] == 'displayorder')
				)
				{
					$sort = 'node.' . $params['sort'] . ' ASC';
				}
			}

			if (empty($sort))
			{
				$sql .= " ORDER BY cl.displayorder, node.publishdate LIMIT ";
			}
			else
			{
				$sql .= " ORDER BY $sort";
			}

			if (empty($params['no_limit']))
			{
				$sql .= ' LIMIT ';
				if (isset($params[vB_dB_Query::PARAM_LIMIT]) AND intval($params[vB_dB_Query::PARAM_LIMIT]))
				{
					$perpage = intval($params[vB_dB_Query::PARAM_LIMIT]);
				}
				else
				{
					$perpage = 20;
				}

				if (isset($params[vB_dB_Query::PARAM_LIMITPAGE]) AND intval($params[vB_dB_Query::PARAM_LIMITPAGE]) AND (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) > 1))
				{
					$sql .=  ($perpage * (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) - 1)) . ',';
				}
				else if (!empty($params['offset']))
				{
					$sql .=  intval($params['offset']) . ',';
				}
				$sql .= $perpage;
			}
			$sql .= "\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
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

	/*
	 * Get filedata record
	 */
	public function getFiledataContent($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//We need a nodeid
			if (empty($params['filedataid']))
			{
				return false;
			}
			return true;
		}
		else
		{
			$joinfields = $joinsql = '';
			if ($params['type'])
			{
				$params['type'] = vB_Api::instanceInternal('filedata')->sanitizeFiletype($params['type']);
				if ($params['type'] != vB_Api_Filedata::SIZE_FULL)
				{
					$joinfields = ", fdr.*, f.filedataid";
					$joinsql = "LEFT JOIN " . TABLE_PREFIX . "filedataresize AS fdr ON (fdr.filedataid = f.filedataid AND fdr.resize_type = '" . $db->escape_string($params['type']) . "')";
				}
			}

			$sql = "
				SELECT
					f.* {$joinfields}
				FROM " . TABLE_PREFIX . "filedata AS f
				{$joinsql}
				WHERE f.filedataid = " . intval($params['filedataid']);

			$sql .= "\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
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

	public function getFiledataWithThumb($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//We need something to query by..
			if (empty($params['filedataid']) AND empty($params['filehash']))
			{
				return false;
			}
			return true;
		}
		else
		{
			$wheresql = array();
			foreach ($params AS $key => $value)
			{
				switch ($key)
				{
					case 'filedataid':
					case 'userid':
					case 'dateline':
					case 'filesize':
						if (!is_array($value))
						{
							$value = array($value);
						}
						$value = array_map('intval', $value);
						$wheresql[] = "f.{$key} IN (" . implode(', ', $value) . ")";
						break;
					case 'filehash':
						if (!is_array($value))
						{
							$value = array($value);
						}
						foreach ($value AS $_key => $_value)
						{
							$value[$_key] = $db->escape_string($_value);
						}
						$wheresql[] = "f.{$key} IN ('" . implode("', '", $value) . "')";
						break;
				}
			}

			if (!$wheresql)
			{
				return false;
			}

			$sql = "
				SELECT f.*, fdr.resize_type, fdr.resize_filesize, fdr.resize_dateline, fdr.resize_width, fdr.resize_height, fdr.resize_filedata, fdr.reload
				FROM " . TABLE_PREFIX . "filedata AS f
				LEFT JOIN " . TABLE_PREFIX . "filedataresize AS fdr ON (fdr.filedataid = f.filedataid AND fdr.resize_type = 'thumb')
				WHERE " . implode(" AND ", $wheresql);

			$sql .= "\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
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

	/*
	 * Get photo record
	 */
	public function getPhotoContent($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//We need a nodeid
			if (empty($params['nodeid']))
			{
				return false;
			}
			return true;
		}
		else
		{
			$joinfields = $joinsql = '';
			if ($params['type'])
			{
				$params['type'] = vB_Api::instanceInternal('filedata')->sanitizeFiletype($params['type']);
				if ($params['type'] != vB_Api_Filedata::SIZE_FULL)
				{
					$joinfields = ", fdr.*, f.filedataid";
					$joinsql = "LEFT JOIN " . TABLE_PREFIX . "filedataresize AS fdr ON (fdr.filedataid = f.filedataid AND fdr.resize_type = '" . $db->escape_string($params['type']) . "')";
				}
			}

			$sql = "
				SELECT
					f.*, p.nodeid, p.caption, p.width as displaywidth, p.height as displayheight {$joinfields}
				FROM " . TABLE_PREFIX . "photo AS p
				INNER JOIN " . TABLE_PREFIX . "filedata AS f ON (f.filedataid = p.filedataid)
				{$joinsql}
				WHERE p.nodeid IN (" . intval($params['nodeid']) . ")";

			$sql .= "\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
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

	/** Gets the Activity for the profile page.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *  @param	bool

	 *
	 *	@result	mixed
	 * **/
	public function getActivity($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//We need either a userid or a setfor
			if (empty($params['setfor']) AND empty($params['userid']))
			{
				return false;
			}
			return true;
		}
		else
		{
			$nodeApi = vB_Api::instanceInternal('node');
			$VMChannel = $nodeApi->fetchVMChannel();
			$sql = "SELECT node.nodeid
			FROM " . TABLE_PREFIX . "node AS node
			INNER JOIN " . TABLE_PREFIX . "contenttype AS type ON type.contenttypeid = node.contenttypeid
			INNER JOIN " . TABLE_PREFIX . "node AS starter ON starter.nodeid = node.starter
			LEFT JOIN " . TABLE_PREFIX . "user AS postfor ON postfor.userid = node.setfor
			LEFT JOIN " . TABLE_PREFIX . "closure AS vmcheck ON vmcheck.child = node.nodeid AND vmcheck.parent=$VMChannel\n";


			if (!empty($params['exclude']))
			{
				if (!is_array($params['exclude']))
				{
					$params['exclude'] = array($params['exclude']);
				}
				$sql .= "LEFT JOIN  " . TABLE_PREFIX . "closure AS cl2 ON cl2.child = node.nodeid AND cl2.parent IN (" .
					implode(',',$params['exclude']) . " )\n";
			}

			if (!empty($params['userid']))
			{
				switch ($params[vB_Api_Node::FILTER_SOURCE])
				{
					case vB_Api_Node::FILTER_SOURCEUSER:
						$sql .= "WHERE (starter.userid =" .  intval($params['userid']) . " OR starter.lastauthorid = " . intval($params['userid']) . ")
							AND (node.protected = 0 OR vmcheck.child IS NOT NULL) \n";
						break;
					case vB_Api_Node::FILTER_SOURCEVM:
						$sql .= "WHERE (starter.setfor=" . intval($params['userid']) . " OR starter.lastauthorid = " . intval($params['userid']) . ") AND vmcheck.child IS NOT NULL \n";
						break;
					default:
						$sql .= "WHERE (starter.setfor=" . intval($params['userid']). " OR starter.userid =" .  intval($params['userid']) . " OR starter.lastauthorid = " . intval($params['userid']) . ")
							AND (node.protected = 0 OR vmcheck.child IS NOT NULL) \n";
						break;
				}
			}
			else
			{
				throw new vB_Exception_Api('invalid_data');
			}
			$sql .= " AND ((node.starter = node.nodeid AND starter.totalcount = 0) OR (starter.lastcontentid = node.nodeid)) AND node.inlist > 0 AND type.class <> 'Channel'\n";

			if (!empty($params['contenttypeid']))
			{
				if (is_array($params['contenttypeid']))
				{
					$sql .= "AND node.contenttypeid IN (" . implode(', ', $params['contenttypeid']) .") \n";
				}
				else
				{
					$sql .= "AND node.contenttypeid = " . $params['contenttypeid'] . " \n";
				}
			}

			if (!empty($params['exclude']))
			{
				$sql .= " AND cl2.child IS NULL \n";
			}

			//block people on the global ignore list
			$options = vB::getDatastore()->getValue('options');
			if (trim($options['globalignore']) != '')
			{
				$blocked = preg_split('#\s+#s', $options['globalignore'], -1, PREG_SPLIT_NO_EMPTY);
				//the user can always see their own posts, so if they are in the blocked list we remove them
				$bbuserkey = array_search(vB::getCurrentSession()->get('userid') , $blocked);

				if ($bbuserkey !== FALSE AND $bbuserkey !== NULL)
				{
					unset($blocked["$bbuserkey"]);
				}

				//Make sure we didn't just empty the list
				if (!empty($blocked))
				{
					$sql .= " AND node.userid NOT IN (" . implode(',', $blocked) . ")";
				}
			}

			/** Date filter */
			if (!empty($params['time']))
			{
				$datenow = vB::getRequest()->getTimeNow();
				switch ($params['time'])
				{
					case vB_Api_Search::FILTER_LASTDAY:
						$timeVal = $datenow - (24 * 60 * 60);
						break;
					case vB_Api_Search::FILTER_LASTWEEK:
						$timeVal = $datenow - (7 * 24 * 60 * 60);
						break;
					case vB_Api_Search::FILTER_LASTMONTH:
						$timeVal = strtotime(date("Y-m-d H:i:s", $datenow) . " - 1 month");
						break;
					default:
						$timeVal = 0;
						break;
				}
				$sql .= " AND node.publishdate >= $timeVal";
			}

			if (isset($params['sort']))
			{
				if (is_array($params['sort']))
				{
					$sorts = array();
					foreach ($params['sort'] as $key => $value)
					{
						//we may have something like 'publishdate' => 'desc'
						if (
							($key == 'publishdate')
							OR
							($key == 'unpublishdate')
							OR
							($key == 'authorname')
							OR
							($key == 'displayorder')
							)
						{
							if (strtolower($value) == 'desc')
							{
								$sorts[] = "node.$key DESC";
							}
							else
							{
								$sorts[] = "node.$key ASC";
							}
						}
						else if (
							($value == 'publishdate')
							OR
							($value == 'unpublishdate')
							OR
							($value == 'authorname')
							OR
							($value == 'displayorder')
							)
						{
							$sorts[] = "node.$value ASC";
						}
						else if (
							is_array($value)
							AND
							isset($value['sortby'])
							AND
							(
							($value['sortby'] == 'publishdate')
							OR
							($value['sortby'] == 'unpublishdate')
							OR
							($value['sortby'] == 'authorname')
							OR
							($value['sortby'] == 'displayorder')
							)
							)
						{
							if (
								isset($value['direction'])
								AND
								(strtolower($value['direction']) == 'desc')
								)
							{
								$sorts[] = 'node.' . $value['sortby'] . " DESC";
							}
							else
							{
								$sorts[] = 'node.' . $value['sortby'] . " ASC";
							}

						}

						if (!empty($sorts))
						{
							$sort = implode(', ', $sorts);
						}
					}
				}
				else if (
					($params['sort'] == 'publishdate')
					OR
					($params['sort'] == 'unpublishdate')
					OR
					($params['sort'] == 'authorname')
					OR
					($params['sort'] == 'displayorder')
					)
				{
					$sort = 'node.' . $params['sort'] . ' ASC';
				}
			}

			if (empty($sort))
			{
				$sql .= " ORDER BY node.publishdate DESC LIMIT ";
			}
			else
			{
				$sql .= " ORDER BY $sort LIMIT ";
			}

			if (isset($params[vB_dB_Query::PARAM_LIMIT]) AND intval($params[vB_dB_Query::PARAM_LIMIT]))
			{
				$perpage = intval($params[vB_dB_Query::PARAM_LIMIT]);
			}
			else if (isset($params[vB_dB_Query::PARAM_LIMITPAGE]))
			{
				$perpage = 20;
			}
			else
			{
				$perpage = 500;
			}

			if (isset($params[vB_dB_Query::PARAM_LIMITPAGE]) AND intval($params[vB_dB_Query::PARAM_LIMITPAGE]) AND (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) > 1))
			{
				$sql .=  ($perpage * (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) - 1)) . ',';
			}
			$sql .= $perpage . "\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** fetchNodeWithContent
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *  @param	bool
	 *
	 *	@result	mixed
	 * **/
	public function fetchNodeWithContent($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//We need a nodeid
			if (empty($params['nodeid']))
			{
				return false;
			}
			return true;
		}
		else
		{
			// clean $params
			if (!isset($params['userid']))
			{
				$params['userid'] = vB::getCurrentSession()->get('userid');
			}

			if (!is_array($params['nodeid']))
			{
				$params['nodeid'] = array($params['nodeid']);
			}
			
			$params = vB::getCleaner()->cleanArray($params, array(
				'userid' => vB_Cleaner::TYPE_UINT,
				'nodeid' => vB_Cleaner::TYPE_ARRAY_UINT,
			));
			
			$sqlJoin = array();
			$sqlFields = array("node.*");
			if (!defined('VB_AREA') OR VB_AREA != 'Upgrade')
			{
				$sqlJoin[] = "LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON (editlog.nodeid = node.nodeid)";
				$sqlFields[] = "editlog.reason AS edit_reason, editlog.userid AS edit_userid, editlog.username AS edit_username, editlog.dateline AS edit_dateline, editlog.hashistory";
			}

			if ($params['userid'])
			{
				$sqlFields[] = "IF (vote.nodeid, 1, 0) AS nodeVoted";
				$sqlJoin[] = "LEFT JOIN " . TABLE_PREFIX . "reputation AS vote ON (node.nodeid = vote.nodeid AND vote.whoadded = {$params['userid']})";
				if ($threadmarking = vB::getDatastore()->getOption('threadmarking'))
				{
					$sqlFields[] = "IF (noderead.readtime, noderead.readtime, 0) AS readtime";
					$sqlJoin[] = "LEFT JOIN " . TABLE_PREFIX . "noderead AS noderead ON (node.nodeid = noderead.nodeid AND noderead.userid = {$params['userid']})";
				}
			}
			else
			{
				$sqlFields[] = "0 AS nodeVoted";
				$sqlFields[] = "0 AS readtime";
			}

			$ids = implode(',', $params['nodeid']);

			$sql = "SELECT " . implode(", ", $sqlFields) . "
			FROM " . TABLE_PREFIX . "node AS node
			" . implode("\n", $sqlJoin) . "
			 WHERE node.nodeid IN ({$ids})";

			$sql .= " \n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** Gets the Count of photos for the posted photos slideshow.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *  @param	bool
	 *
	 *	@result	mixed
	 * **/
	public function fetchPostedPhotoCount($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//We need a userid or channelid
			if (empty($params['userid']) AND empty($params['channelid']))
			{
				return false;
			}
			return true;
		}
		else
		{
			$attachType = vB_Types::instance()->getContentTypeID('vBForum_Attach');
			$photoType = vB_Types::instance()->getContentTypeID('vBForum_Photo');
			$albumChannel = vB_Library::instance('node')->fetchAlbumChannel();
			$permflags = $this->getNodePermTerms();
			$assertor = vB::getDbAssertor();

			$sqlJoin = array();
			$sqlWhere = array('TRUE');
			if (isset($params['userid']) AND !empty($params['userid']))
			{
				$sqlWhere[] = "node.userid = {$params['userid']}";
			}

			if (isset($params['channelid']) AND !empty($params['channelid']))
			{
				$sqlJoin[] = "INNER JOIN " . TABLE_PREFIX . "closure AS cl ON cl.child = node.nodeid AND cl.parent = {$params['channelid']}";
			}
			else
			{
				$sqlJoin[] = "LEFT JOIN " . TABLE_PREFIX . "closure AS cl ON cl.child = node.nodeid AND cl.parent = $albumChannel";
				$sqlWhere[] = 'cl.child IS NULL';
			}


			switch ($params['dateFilter'])
			{
				case 'today':
				{
				$sqlWhere[] = "node.publishdate > " . vB::getRequest()->getTimeNow() . " - 86400";

				break;
				}

				case 'lastweek':
				{
					$sqlWhere[] = "node.publishdate > " . vB::getRequest()->getTimeNow() . " - (7 * 86400)";
					break;
				}
				case 'lastmonth':
				{
					$sqlWhere[] = "node.publishdate > " . vB::getRequest()->getTimeNow() . " - (30 * 86400)";
					break;
				}
			}
			$sql = "SELECT count(node.nodeid) AS count
			FROM " . TABLE_PREFIX . "node AS node
			INNER JOIN " . TABLE_PREFIX . "node AS parent ON parent.nodeid = node.parentid
			" . implode("\n", $sqlJoin) . "
			  " . implode("\n", $permflags['joins']) . "
			  WHERE node.contenttypeid IN($attachType,$photoType) AND " . implode(' AND ', $sqlWhere) . "
			  " . $permflags['where'] ;

			$sql .= " \n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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


	/** Gets the Media  for the profile page.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *  @param	bool
	 *
	 *	@result	mixed
	 * **/
	public function fetchGalleryPhotos($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//We need a userid or channelid
			if (empty($params['userid']) AND empty($params['channelid']))
			{
				return false;
			}
			return true;
		}
		else
		{

			$attachType = vB_Types::instance()->getContentTypeID('vBForum_Attach');
			$photoType = vB_Types::instance()->getContentTypeID('vBForum_Photo');
			$albumChannel = vB_Library::instance('node')->fetchAlbumChannel();
			$permflags = $this->getNodePermTerms();
			$assertor = vB::getDbAssertor();

			$sqlJoin = array();
			$sqlWhere = array('TRUE');
			if (isset($params['userid']) AND !empty($params['userid']))
			{
				$sqlWhere[] = "node.userid = {$params['userid']}";
			}
			if (isset($params['channelid']) AND !empty($params['channelid']))
			{
				$sqlJoin[] = "INNER JOIN " . TABLE_PREFIX . "closure AS cl ON cl.child = node.nodeid AND cl.parent = {$params['channelid']}";
			}
			else
			{
				$sqlJoin[] = "LEFT JOIN " . TABLE_PREFIX . "closure AS cl ON cl.child = node.nodeid AND cl.parent = $albumChannel";
				$sqlWhere[] = 'cl.child IS NULL';
			}

			if (isset($params['dateFilter']))
			{
				switch ($params['dateFilter'])
				{
					case 'today':
						{
						$sqlWhere[] = "node.publishdate > " . vB::getRequest()->getTimeNow() . " - 86400";
						break;
						}

					case 'lastweek':
						{
						$sqlWhere[] = "node.publishdate > " . vB::getRequest()->getTimeNow() . " - (7 * 86400)";
						break;
						}
					case 'lastmonth':
						{
						$sqlWhere[] = "node.publishdate > " . vB::getRequest()->getTimeNow() . " - (30 * 86400)";
						break;
					}
				}
			}

			$sql = "SELECT node.nodeid, node.title,
			node.description, node.contenttypeid, node.publishdate, parent.nodeid AS parentnode, parent.title AS parenttitle, parent.authorname, parent.routeid, parent.userid, parent.setfor AS parentsetfor
			FROM " . TABLE_PREFIX . "node AS node
			INNER JOIN " . TABLE_PREFIX . "node AS parent ON parent.nodeid = node.parentid
			" . implode("\n", $sqlJoin) . "
			  " . implode("\n", $permflags['joins']) . "
			  WHERE node.contenttypeid IN($attachType,$photoType) AND " . implode(' AND ', $sqlWhere) . "
			  " . $permflags['where'] ;

			$sql .= " ORDER BY node.publishdate DESC, node.nodeid ASC \n";

			if (isset($params[vB_dB_Query::PARAM_LIMIT]) AND intval($params[vB_dB_Query::PARAM_LIMIT]))
			{
				$perpage = intval($params[vB_dB_Query::PARAM_LIMIT]);
			}
			else
			{
				$perpage = 60;
			}

			if (isset($params[vB_dB_Query::PARAM_LIMITPAGE]) AND intval($params[vB_dB_Query::PARAM_LIMITPAGE]) AND (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) > 1))
			{
				$start=  ($perpage * (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) - 1)) ;
			}
			else
			{
				$start = 0 ;
			}

			$sql .= "LIMIT $start, $perpage \n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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


	/** Gets the Media outside the profile page.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *  @param	bool
	 *
	 *	@result	mixed
	 * **/
	public function fetchMedia($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//We need a userid or a channelId
			if (empty($params['userId']) AND empty($params['channelId']))
			{
				return false;
			}
			return true;
		}
		else
		{
			$videoType = vB_Types::instance()->getContentTypeID('vBForum_Video');
			$galleryType = vB_Types::instance()->getContentTypeID('vBForum_Gallery');

			if (empty($params['countOnly']))
			{
				$sql = "SELECT node.nodeid FROM " . TABLE_PREFIX . "node AS node ";
			}
			else
			{
				//we need just the count, not the quantity
				$sql = "SELECT COUNT(node.nodeid) AS qty FROM " . TABLE_PREFIX . "node AS node ";
			}
			if (!empty($params['exclude']))
			{
				if (!is_array($params['exclude']))
				{
					$params['exclude'] = array($params['exclude']);
				}
				$sql .= "LEFT JOIN  " . TABLE_PREFIX . "closure AS cl2 ON cl2.child = node.nodeid AND cl2.parent IN (" .
					implode(',',$params['exclude']) . " )\n";
				$sqlWhere[] = "cl2.child IS NULL \n";
			}

			if (!empty($params['channelId']))
			{
				$sql .= "INNER JOIN " . TABLE_PREFIX . "closure AS cl3 ON cl3.child = node.nodeid AND cl3.parent = " . intval($params['channelId']) . "\n";
			}

			if (!empty($params['userId']))
			{
				$sqlWhere[] = "node.userid =" .  intval($params['userId']) . "\n";
			}

			if (!empty($params['type']) AND ($params['type'] == 'video'))
			{
				$sqlWhere[] = "(node.contenttypeid = $videoType  OR node.hasvideo > 0)";
			}
			else if (!empty($params['type']) AND ($params['type'] == 'gallery'))
			{
				$sqlWhere[] = "(node.contenttypeid = $galleryType OR node.hasphoto > 0)\n";
			}
			else
			{
				$sqlWhere[] = "(node.contenttypeid in ($galleryType, $videoType) OR node.hasphoto > 0 OR node.hasvideo > 0) \n";
			}

			//block people on the global ignore list.
			/** Date filter */
			if (!empty($params['time']))
			{
				$datenow = vB::getRequest()->getTimeNow();
				switch ($params['time'])
				{
					case vB_Api_Search::FILTER_LASTDAY:
						$timeVal = $datenow - (24 * 60 * 60);
						break;
					case vB_Api_Search::FILTER_LASTWEEK:
						$timeVal = $datenow - (7 * 24 * 60 * 60);
						break;
					case vB_Api_Search::FILTER_LASTMONTH:
						$timeVal = strtotime(date("Y-m-d H:i:s", $datenow) . " - 1 month");
						break;
					default:
						$timeVal = 0;
						break;
				}
				$sqlWhere[] = "node.publishdate >= $timeVal";
			}

			$sql .= " WHERE " . implode(' AND ', $sqlWhere);

			if (empty($params['countOnly']))
			{
				if (isset($params['sort']) and ($params['sort'] == 'recent'))
				{
					$sql .= " ORDER BY node.publishdate DESC LIMIT ";
				}
				else if (isset($params['sort']) and ($params['sort'] == 'votes'))
				{
					$sql .= " ORDER BY node.votes DESC LIMIT ";
				}
				else if (isset($params['sortdir']) and ($params['sortdir'] == 'ASC'))
				{
					$sql .= " ORDER BY node.publishdate ASC LIMIT ";
				}
				else
				{
					$sql .= " ORDER BY node.publishdate DESC LIMIT ";
				}

				if (isset($params[vB_dB_Query::PARAM_LIMIT]) AND intval($params[vB_dB_Query::PARAM_LIMIT]))
				{
					$perpage = intval($params[vB_dB_Query::PARAM_LIMIT]);
				}
				else
				{
					$perpage = 10;
				}

				if (isset($params[vB_dB_Query::PARAM_LIMITPAGE]) AND intval($params[vB_dB_Query::PARAM_LIMITPAGE]) AND (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) > 1))
				{
					$sql .=  ($perpage * (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) - 1)) . ',';
				}

				$sql .= $perpage;
			}
			$sql .= "\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** Gets the Media  for the profile page.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *  @param	bool
	 *
	 *	@result	mixed
	 * **/
	public function fetchProfileMedia($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//We need a userid or a nodeid
			if (empty($params['userId']) AND empty($params['channelId']))
			{
				return false;
			}
			return true;
		}
		else
		{
			$attachType = vB_Types::instance()->getContentTypeID('vBForum_Attach');
			$photoType = vB_Types::instance()->getContentTypeID('vBForum_Photo');
			$phrases = vB_Api::instanceInternal('phrase')->fetch(array('posted_photos', 'videos'));
			$albumChannel = vB_Library::instance('node')->fetchAlbumChannel();
			$permflags = $this->getNodePermTerms();
			$assertor = vB::getDbAssertor();

			$join = $where = array();

			if (!empty($params['channelId']))
			{
				$join[] = "INNER JOIN " . TABLE_PREFIX . "closure AS channelClosure ON channelClosure.child = node.nodeid AND channelClosure.parent = " . intval($params['channelId']) . "\n";
			}

			if (!empty($params['userId']))
			{
				$where[] = "node.userid =" .  intval($params['userId']) . "\n";
			}

			$sqlJoin = implode("\n", $join);
			$sqlWhere = empty($where) ? 'TRUE' : implode(' AND ', $where);

			$sql = '';
			$concat = false;
			if (!isset($params['type']) OR ($params['type'] == 'gallery'))
			{
				$sql .= "SELECT -2 AS nodeid, '" . $assertor->escape_string($phrases['posted_photos']) . "' AS title,
				'" . $assertor->escape_string($phrases['posted_photos']) . "' AS htmltitle, count(node.nodeid) AS qty, NULL as starter,
				NULL as starterroute, NULL as startertitle, max(node.nodeid) AS childnode, NULL as provider, NULL as code, cl.parent as albumid
				FROM " . TABLE_PREFIX . "node AS node
				 $sqlJoin
				 LEFT JOIN " . TABLE_PREFIX . "closure AS cl ON cl.child = node.nodeid AND cl.parent = $albumChannel
				  " . implode("\n", $permflags['joins']) . "
				  WHERE node.contenttypeid IN($attachType,$photoType) AND $sqlWhere AND cl.child IS NULL
				  " . $permflags['where'] . "
				  HAVING count(node.nodeid) > 0\n";
				 $concat =  true;
			}

			if (!isset($params['type']) OR ($params['type'] == 'video'))
			{
				$sql .= ($concat) ? "UNION ALL\n" : '';
				$sql .= "(SELECT -1 AS nodeid, '" . $assertor->escape_string($phrases['videos']) . "' AS title,
				  '" . $assertor->escape_string($phrases['videos']) . "' AS htmltitle, count(node.nodeid) AS qty,
				  NULL as starter, NULL as starterroute, NULL as startertitle, 0 AS childnode, v.provider, v.code, node.parentid as albumid
			  	FROM " . TABLE_PREFIX . "node AS node
				$sqlJoin
			  	INNER JOIN " . TABLE_PREFIX . "videoitem AS v ON v.nodeid = node.nodeid
				  " . implode("\n", $permflags['joins']) . "
				  WHERE $sqlWhere
				  " . $permflags['where'] . "
				  HAVING COUNT(node.nodeid) > 0)\n";
				$concat =  true;
			}

			if (!isset($params['type']) OR ($params['type'] == 'gallery'))
			{
				$sql .= ($concat) ? "UNION ALL\n" : '';
				$sql .= "(SELECT node.nodeid, node.title, node.htmltitle, count(child.nodeid) AS qty, node.starter,
					ns.routeid AS starterroute, ns.title AS startertitle, max(child.nodeid) AS childnode, NULL as provider, NULL as code, node.parentid as albumid
					FROM " . TABLE_PREFIX . "node AS node
					$sqlJoin
					INNER JOIN " . TABLE_PREFIX . "node AS child ON child.parentid = node.nodeid
					INNER JOIN " . TABLE_PREFIX . "node AS ns ON ns.nodeid = node.starter
				  " . implode("\n", $permflags['joins']) . "
				  WHERE node.parentid = $albumChannel AND child.showpublished > 0
					AND $sqlWhere AND child.contenttypeid  = $photoType
					" . $permflags['where'] . "
					GROUP BY node.nodeid, node.title
				ORDER BY node.publishdate) ";
			}

			$sql .= "\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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
	/** Gets the Media  for the profile page.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *  @param	bool
	 *
	 *	@result	mixed
	 * **/
	public function fetchVideoNodes($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//We need a userid or a nodeid
			if (empty($params['userid']) AND empty($params['nodeid']))
			{
				return false;
			}
			return true;
		}
		else
		{
			$permflags = $this->getNodePermTerms();
			$sql = "SELECT node.nodeid
		  	FROM " . TABLE_PREFIX . "node AS node ";

			if (!empty($params['nodeid']))
			{
				$sql .= "INNER JOIN ". TABLE_PREFIX . "closure AS clLimit ON clLimit.child = node.nodeid \n";
			}
			$sql .=  implode("\n", $permflags['joins']) ;
			$sql .=  " WHERE node.contenttypeid = " .vB_Types::instance()->getContentTypeId('vBForum_Video') . "\n";

			if (!empty($params['nodeid']))
			{
				$sql .= " AND clLimit.parent = " . $params['nodeid'] . "\n";
			}

			if (!empty($params['userid']))
			{
				$sql .= " AND node.userid = " . $params['userid'] . "\n";
			}

			switch ($params['dateFilter'])
			{
				case 'today':
				{
					$sql .= " AND node.publishdate > " . vB::getRequest()->getTimeNow() . ' - 86400';
					break;
				}

				case 'lastweek':
				{
					$sql .= " AND node.publishdate > "  . vB::getRequest()->getTimeNow() . ' - (7 * 86400)';
					break;
				}
				case 'lastmonth':
				{
					$sql .= " AND node.publishdate > " . vB::getRequest()->getTimeNow() . ' - (30 * 86400)';
					break;
				}
			}

			$sql .=  $permflags['where'] . "
			ORDER BY node.publishdate LIMIT ";

			if (isset($params[vB_dB_Query::PARAM_LIMIT]) AND intval($params[vB_dB_Query::PARAM_LIMIT]))
			{
				$perpage = intval($params[vB_dB_Query::PARAM_LIMIT]);
			}
			else
			{
				$perpage = 10;
			}

			if (isset($params[vB_dB_Query::PARAM_LIMITPAGE]) AND intval($params[vB_dB_Query::PARAM_LIMITPAGE]) AND (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) > 1))
			{
				$sql .=  ($perpage * (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) - 1)) . ',';
			}

			$sql .= $perpage;
			$sql .= "\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
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


	/** gets the count of videos for a video album page.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *  @param	bool
	 *
	 *	@result	mixed
	 * **/
	public function fetchVideoCount($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//We need a userid or a nodeid
			if (empty($params['userid']) AND empty($params['nodeid']))
			{
				return false;
			}
			return true;
		}
		else
		{
			$permflags = $this->getNodePermTerms();
			$sql = "SELECT count( vi.videoitemid) AS count
		  	FROM " . TABLE_PREFIX . "node AS node
		  	INNER JOIN " . TABLE_PREFIX . "videoitem AS vi ON vi.nodeid = node.nodeid \n";
			if (!empty($params['nodeid']))
			{
				$sql .= "INNER JOIN ". TABLE_PREFIX . "closure AS clLimit ON clLimit.child = node.nodeid \n";
			}
			$sql .=  implode("\n", $permflags['joins']) ;
			$wheres = array();

			if (!empty($params['nodeid']))
			{
				$wheres[] = "clLimit.parent = " . $params['nodeid'];
			}

			if (!empty($params['userid']))
			{
				$wheres[] = "node.userid = " . $params['userid'];
			}

			switch ($params['dateFilter'])
			{
				case 'today':
				{
					$wheres[] = "node.publishdate > " . vB::getRequest()->getTimeNow() . " - 86400";
					break;
				}

				case 'lastweek':
				{
					$wheres[] = "node.publishdate > " . vB::getRequest()->getTimeNow() . " - (7 * 86400)";
					break;
				}
				case 'lastmonth':
				{
					$wheres[] = "node.publishdate > " . vB::getRequest()->getTimeNow() . " - (30 * 86400)";
					break;
				}
			}

			$sql .=  " WHERE " . implode(" AND ", $wheres) ."\n " . $permflags['where'] ;
			$sql .= "\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
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

	/** Lists messages from a PM folder.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *  @param	bool
	 *
	 *	@result	mixed
	 * **/
	public function listPrivateMessages($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//We need at least a userid and a folderid.
			if (empty($params['userid']) OR empty($params['folderid']) OR !isset($params['showdeleted']))
			{
				return false;
			}
			return true;
		}
		else
		{
			$sql = "SELECT folder.folderid, folder.titlephrase, folder.title AS folder, node.nodeid, node.title,
			(CASE WHEN node.lastauthorid <> 0 THEN node.lastauthorid ELSE node.userid END) AS userid, node.created, s.msgread, text.rawtext, text.pagetext, node.lastcontent AS publishdate,
			(CASE WHEN node.lastcontentauthor <> 0 THEN node.lastcontentauthor ELSE node.authorname END) AS username,
			node.lastcontentauthor AS lastauthor, node.lastauthorid AS lastauthorid, node.textcount AS responses
			FROM " . TABLE_PREFIX . "messagefolder AS folder
			INNER JOIN " . TABLE_PREFIX . "sentto AS s ON s.folderid = folder.folderid AND s.deleted = " . $params['showdeleted'] . "\n
			INNER JOIN " . TABLE_PREFIX . "node AS node ON node.nodeid = s.nodeid AND node.nodeid = node.starter
			INNER JOIN " . TABLE_PREFIX . "text AS text ON text.nodeid = node.lastcontentid
			LEFT JOIN " . TABLE_PREFIX . "userlist AS i ON i.userid = s.userid AND i.relationid = node.userid AND i.type = 'ignore'
			WHERE s.userid = " . $params['userid'] . " AND s.folderid =  " . $params['folderid'] . " AND i.type IS NULL  \n";
			$sql .= "GROUP BY node.nodeid\n";

			//block people on the global ignore list.
			if (isset($params['sortDir']) AND ($params['sortDir'] == "ASC"))
			{
				$sql .= " ORDER BY publishdate ASC ";
			}
			else
			{
				$sql .= " ORDER BY publishdate DESC ";
			}

			if (isset($params[vB_dB_Query::PARAM_LIMIT]) AND intval($params[vB_dB_Query::PARAM_LIMIT]))
			{
				$perpage = intval($params[vB_dB_Query::PARAM_LIMIT]);
			}
			else
			{
				$perpage = 20;
			}

			if (isset($params[vB_dB_Query::PARAM_LIMITPAGE]) AND intval($params[vB_dB_Query::PARAM_LIMITPAGE]) AND (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) > 1))
			{
				$start=  ($perpage * (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) - 1)) ;
			}
			else
			{
				$start = 0 ;
			}

			$sql .= "LIMIT $start, $perpage \n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** Lists messages from a PM folder.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *  @param	bool
	 *
	 *	@result	mixed
	 * **/
	public function listSentMessages($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//We need at least a userid and a folderid.
			if (empty($params['userid']) OR empty($params['folderid']) OR !isset($params['showdeleted']))
			{
				return false;
			}
			return true;
		}
		else
		{
			$sql = "SELECT folder.folderid, folder.titlephrase, folder.title AS folder, starter.nodeid, starter.title,
			(CASE WHEN starter.lastauthorid <> 0 THEN starter.lastauthorid ELSE starter.userid END) AS userid, starter.created, s.msgread, text.rawtext, text.pagetext, starter.lastcontent AS publishdate,
			(CASE WHEN starter.lastcontentauthor <> 0 THEN starter.lastcontentauthor ELSE starter.authorname END) AS username,
			starter.lastcontentauthor AS lastauthor, starter.lastauthorid AS lastauthorid, starter.textcount AS responses,
			SUM(CASE WHEN s_starter.deleted = 1 AND s_starter.userid = " . $params['userid']. " THEN 1 ELSE 0 END) AS deleted
			FROM " . TABLE_PREFIX . "messagefolder AS folder
			INNER JOIN " . TABLE_PREFIX . "sentto AS s ON s.folderid = folder.folderid\n
			INNER JOIN " . TABLE_PREFIX . "node AS node ON node.nodeid = s.nodeid
			INNER JOIN " . TABLE_PREFIX . "node AS starter ON starter.nodeid = node.starter
			INNER JOIN " . TABLE_PREFIX . "text AS text ON text.nodeid = starter.lastcontentid
			INNER JOIN " . TABLE_PREFIX . "sentto AS s_starter ON s_starter.nodeid = starter.nodeid
			WHERE s.userid = " . $params['userid'] . " AND s.folderid =  " . $params['folderid'] . "  \n";
			$sql .= "GROUP BY starter.nodeid\n";

			if ($params['showdeleted'])
			{
				$sql .= " HAVING deleted >= 1\n";
			}
			else
			{
				$sql .= " HAVING deleted = 0\n";
			}

			//block people on the global ignore list.
			if (isset($params['sortDir']) AND ($params['sortDir'] == "ASC"))
			{
				$sql .= " ORDER BY publishdate ASC ";
			}
			else
			{
				$sql .= " ORDER BY publishdate DESC ";
			}

			if (isset($params[vB_dB_Query::PARAM_LIMIT]) AND intval($params[vB_dB_Query::PARAM_LIMIT]))
			{
				$perpage = intval($params[vB_dB_Query::PARAM_LIMIT]);
			}
			else
			{
				$perpage = 20;
			}

			if (isset($params[vB_dB_Query::PARAM_LIMITPAGE]) AND intval($params[vB_dB_Query::PARAM_LIMITPAGE]) AND (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) > 1))
			{
				$start=  ($perpage * (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) - 1)) ;
			}
			else
			{
				$start = 0 ;
			}

			$sql .= "LIMIT $start, $perpage \n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** Lists either notifications or requests
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *  @param	bool
	 *
	 *	@result	mixed
	 * **/
	public function listSpecialMessages($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//We need at least a userid and a folderid.
			if (empty($params['userid']) OR empty($params['folderid']))
			{
				return false;
			}
			return true;
		}
		else
		{
			$sql = "SELECT node.*, s.msgread, text.rawtext, text.pagetext, message.about, message.aboutid
			FROM " . TABLE_PREFIX . "sentto AS s
			INNER JOIN " . TABLE_PREFIX . "node AS node ON node.nodeid = s.nodeid
			INNER JOIN " . TABLE_PREFIX . "privatemessage AS message ON node.nodeid = message.nodeid
			INNER JOIN " . TABLE_PREFIX . "text AS text ON text.nodeid = s.nodeid
			WHERE s.userid = " . $params['userid'] . " AND s.folderid =  " . $params['folderid'] . " AND s.deleted = 0\n";

			//block people on the global ignore list.

			if (isset($params['sortdir']) AND ($params['sortdir'] == "ASC"))
			{
				$sql .= " ORDER BY node.publishdate ASC";
			}
			else
			{
				$sql .= " ORDER BY node.publishdate DESC ";
			}

			if (isset($params[vB_dB_Query::PARAM_LIMIT]) AND intval($params[vB_dB_Query::PARAM_LIMIT]))
			{
				$perpage = intval($params[vB_dB_Query::PARAM_LIMIT]);
			}
			else
			{
				$perpage = 20;
			}

			if (isset($params[vB_dB_Query::PARAM_LIMITPAGE]) AND intval($params[vB_dB_Query::PARAM_LIMITPAGE]) AND (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) > 1))
			{
				$start=  ($perpage * (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) - 1)) ;
			}
			else
			{
				$start = 0 ;
			}

			$sql .= "LIMIT $start, $perpage \n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** Lists either notifications or requests
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *  @param	bool
	 *
	 *	@result	mixed
	 * **/
	public function listNotifications($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//We need at least a userid and a folderid.
			if (empty($params['userid']) OR empty($params['folderid']))
			{
				return false;
			}
			return true;
		}
		else
		{

			$sql = "SELECT node.*, pm.msgtype, pm.about, pm.aboutid,
			(CASE WHEN about.textcount > 0 THEN about.textcount - 1 ELSE 0 END) AS aboutcount,
			(CASE WHEN about.nodeid = about.starter THEN 1 ELSE 0 END) AS is_conversation,
			about.lastcontentauthor AS lastauthor, user.username AS aboutuser, about.title AS abouttitle, about.routeid AS aboutrouteid,
			r.prefix, r.contentid, poll.votes, poll.lastvote, user.userid AS aboutuserid, about.lastauthorid AS lastauthorid,
			about.starter AS aboutstarterid, starter.title as aboutstartertitle, starter.routeid AS aboutstarterrouteid
			FROM " . TABLE_PREFIX . "sentto AS s
			INNER JOIN " . TABLE_PREFIX . "node AS node ON node.nodeid = s.nodeid
			INNER JOIN " . TABLE_PREFIX . "privatemessage AS pm ON pm.nodeid = s.nodeid
			LEFT JOIN " . TABLE_PREFIX . "node AS about ON about.nodeid = pm.aboutid
			LEFT JOIN " . TABLE_PREFIX . "node AS starter ON about.starter = starter.nodeid
			LEFT JOIN " . TABLE_PREFIX . "routenew AS r ON r.routeid = about.routeid
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = pm.aboutid
			LEFT JOIN " . TABLE_PREFIX . "poll AS poll ON poll.nodeid = pm.aboutid
			WHERE s.folderid =  " . $params['folderid'] . " AND s.deleted = 0\n";


			if (!empty($params['about']))
			{
				if (is_array($params['about']))
				{
					$about = $params['about'];
				}
				else
				{
					$about = array($params['about']);
				}

				foreach ($about as $key => $value)
				{
					$about[$key] = "'" . $db->escape_string($value) . "'";
				}

				$sql .= " AND pm.about in("  . implode(',', $about) . ") ";
			}

			if (isset($params['sortDir']) AND ($params['sortDir'] == "ASC"))
			{
				$sql .= " ORDER BY node.publishdate ASC ";
			}
			else
			{
				$sql .= " ORDER BY node.publishdate DESC ";
			}

			if (isset($params[vB_dB_Query::PARAM_LIMIT]) AND intval($params[vB_dB_Query::PARAM_LIMIT]))
			{
				$perpage = intval($params[vB_dB_Query::PARAM_LIMIT]);
			}
			else
			{
				$perpage = 20;
			}

			if (isset($params[vB_dB_Query::PARAM_LIMITPAGE]) AND intval($params[vB_dB_Query::PARAM_LIMITPAGE]) AND (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) > 1))
			{
				$start = ($perpage * (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) - 1)) ;
			}
			else
			{
				$start = 0 ;
			}

			$sql .= "LIMIT $start, $perpage \n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/*** Adds a node
	 *
	 *	@param	mixed		the query parameters
	 * 	@param	object		the database object
	 * 	@param	bool		whether we run the query, or just validate that we can run it.
	 *
	 *	@return	int
	 *
	 ***/
	function addNode($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['contenttypeid'])
			AND !empty($params['parentid']) AND !empty($params['title']));
		}
		$params[vB_dB_Query::TYPE_KEY] = vB_dB_Query::QUERY_INSERT;

		//We must set the protected field.
		$parent = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "node WHERE nodeid =" . $params['parentid']);
		$params['protected'] = $parent['protected'];

		$nodeid =  vB_dB_Assertor::instance()->assertQuery('vBForum:node', $params);
		$config = vB::getConfig();

		if ($nodeid)
		{
			$nodeid = $nodeid[0];
			$sql = "INSERT INTO " . TABLE_PREFIX . "closure(parent, child, depth)
				VALUES($nodeid, $nodeid, 0) \n/**" . __FUNCTION__ .
			      (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/" ;

			if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
			{
				echo "sql: $sql <br />\n";
			}
			$db->query_write($sql);

			$sql = "INSERT INTO " . TABLE_PREFIX . "closure(parent, child, depth)
				SELECT p.parent, $nodeid, p.depth+1
			  	FROM " . TABLE_PREFIX . "closure p
			 	WHERE p.child=". $params['parentid'] . "\n/**" . __FUNCTION__ .
				(defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

			if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
			{
				echo "sql: $sql <br />\n";
			}

			$db->query_write($sql);

			return $nodeid;
		}
		else
		{
			return false;
		}
	}

	/*** Deletes a node
	 *
	 *	@param	mixed		the query parameters
	 * 	@param	object		the database object
	 * 	@param	bool		whether we run the query, or just validate that we can run it.
	 *
	 *	@return	int
	 *
	 ***/
	function deleteNode($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['nodeid']));
		}

		//If we have any children and delete_subnodes is not set positive, we abort.
		if (empty($params['delete_subnodes']) OR !$params['delete_subnodes'])
		{
			$children = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "closure WHERE parent = " .
			$params['nodeid'] . " AND depth > 0 LIMIT 1");
			if ($children)
			{
				throw new vB_Exception_Database('cannot_delete_with_subnodes');
			}
		}

		$sql = "DELETE node, cl2 FROM " . TABLE_PREFIX . "closure AS cl
			INNER JOIN " . TABLE_PREFIX . "node AS node on node.nodeid = cl.child
			INNER JOIN " . TABLE_PREFIX . "closure AS cl2 on node.nodeid = cl2.child
			WHERE cl.parent = " . $params['nodeid']. "\n/**" . __FUNCTION__ .
		(defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql <br />\n";
		}

		$result = $db->query_write($sql);

		return $result;
	}

	function deleteNodes($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['nodeids']));
		}

		//If we have any children and delete_subnodes is not set positive, we abort.
		if (empty($params['delete_subnodes']) OR !$params['delete_subnodes'])
		{
			$children = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "closure WHERE parent IN (" .
			implode(',',$params['nodeids']) . ") AND depth > 0 LIMIT 1");
			if ($children)
			{
				throw new vB_Exception_Database('cannot_delete_with_subnodes');
			}
		}

		$sql = "DELETE node, cl2 FROM " . TABLE_PREFIX . "closure AS cl
			INNER JOIN " . TABLE_PREFIX . "node AS node on node.nodeid = cl.child
			INNER JOIN " . TABLE_PREFIX . "closure AS cl2 on node.nodeid = cl2.child
			WHERE cl.parent IN (" . implode(',',$params['nodeids']) . ")\n/**" . __FUNCTION__ .
		(defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql <br />\n";
		}

		$result = $db->query_write($sql);

		return $result;
	}


	/*** Moves a list of nodes and their subnodes
	 *
	*	@param	mixed		the query parameters
	* 	@param	object		the database object
	* 	@param	bool		whether we run the query, or just validate that we can run it.
	*
	*	@return	int
	*
	***/
	function moveNodes($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			if (empty($params['nodeids']) OR empty($params['to_parent']))
			{
				return false;
			}
			//If the "to" is a child of "from", we can't do the move.
			$children = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "closure WHERE parent IN (" .
					implode(',',$params['nodeids']) . ") AND child = " .
					$params['to_parent'] . " LIMIT 1 ". "\n/**" . __FUNCTION__ .
					(defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/");
			if (!empty($children))
			{
				throw new vB_Exception_Database('You cannot move a node to one of its children ') ;
			}
		}

		//First delete the closure records from this to the top;
		$db->query_write("DELETE cl3 FROM " . TABLE_PREFIX . "closure AS cl
				INNER JOIN " . TABLE_PREFIX . "closure AS cl2 ON cl2.parent = cl.child AND cl2.depth > 0
				INNER JOIN " . TABLE_PREFIX . "closure AS cl3 ON cl3.child = cl2.child AND cl3.parent = cl.parent
				where cl.child IN (" .	implode(',',$params['nodeids']) . ") AND cl.depth > 0; " . "\n/**" .
				__FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/");

		$db->query_write("DELETE FROM " . TABLE_PREFIX . "closure
				WHERE child IN (" .implode(',',$params['nodeids']) . ") AND depth > 0" . "\n/**" . __FUNCTION__ .
				(defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/");

		//Now set the parentid for the node being moved.
		$db->query_write("UPDATE " . TABLE_PREFIX . "node SET parentid = " . $params['to_parent'] . "
				WHERE nodeid IN (" .implode(',',$params['nodeids']) . ")\n/**" . __FUNCTION__ .
				(defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/");

		//Now create the new closure records- the moved node itself;
		//Next the children of the moved node;
		foreach ($params['nodeids'] as $nodeid)
		{
			$result = $db->query_write("INSERT INTO " . TABLE_PREFIX . "closure (parent, child, depth, publishdate)
					SELECT cl2.parent, " . $nodeid . ", cl2.depth + 1, node.publishdate FROM
					" . TABLE_PREFIX . "closure AS cl2 JOIN " . TABLE_PREFIX . "node AS node
					WHERE cl2.child = "  . $params['to_parent'] . " AND node.nodeid = " . $nodeid . "\n/**" . __FUNCTION__ .
					(defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/");

			$sql ="INSERT INTO " . TABLE_PREFIX . "closure (parent, child, depth, publishdate)
			SELECT cl3.parent, cl2.child, cl2.depth + cl3.depth, cl2.publishdate FROM " . TABLE_PREFIX . "closure AS cl2
			JOIN " . TABLE_PREFIX . "closure AS cl3 WHERE cl2.depth > 0 AND
			cl2.parent = " . $nodeid . " AND cl3.depth > 0 AND cl3.child = "  . $nodeid .
			"\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
			$result = $db->query_write($sql);
		}

		//We need to set starter and routeids.
		//We need to get the parent.
		$parent = $db->query_read("SELECT contenttypeid, routeid, starter FROM " . TABLE_PREFIX . "node WHERE nodeid = " . $params['to_parent']);
		$parent = $db->fetch_array($parent);
		$channelTypeid = vB_Types::instance()->getContentTypeId('vBForum_Channel');

		if ($parent['contenttypeid'] == vB_Types::instance()->getContentTypeId('vBForum_Channel'))
		{
			//this is a channel
			//each node is a starter, and their children are responses.
			// We shouldn't update the starter node, so we update them separately
			//Do non-channel nodes
			$sql ="UPDATE " . TABLE_PREFIX . "node AS node
			SET node.starter = node.nodeid
			WHERE node.nodeid in (" . implode(',',$params['nodeids']) . ") AND node.contenttypeid <> $channelTypeid\n/**" .
			__FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
			$result = $db->query_write($sql);

			$newrouteid = vB_Api::instanceInternal('route')->getChannelConversationRoute($params['to_parent']);

			// We also need to update starter's routeid. See VBV-4806.
			$sql ="UPDATE " . TABLE_PREFIX . "node AS node
			INNER JOIN " . TABLE_PREFIX . "closure AS cl ON (cl.parent = node.nodeid)
			INNER JOIN " . TABLE_PREFIX . "node AS child ON child.nodeid = cl.child
			SET child.starter = node.nodeid, child.routeid = " . $newrouteid . "
			WHERE node.nodeid in (" . implode(',',$params['nodeids']) . ") AND node.contenttypeid <> $channelTypeid\n/**" .
			__FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

			$result = $db->query_write($sql);

			//Note that we don't need to change anything about children of channels. They are already starters.
		}
		else
		{
			//this is not a channel, so it has a starter. Each node should inherit this starter.
			$sql ="UPDATE " . TABLE_PREFIX . "node AS node
			INNER JOIN " . TABLE_PREFIX . "closure AS cl ON cl.parent = node.nodeid
			INNER JOIN " . TABLE_PREFIX . "node AS child ON child.nodeid = cl.child
			SET child.starter =" . $parent['starter'] . ", child.routeid = " . $parent['routeid'] . "
			WHERE node.nodeid in (" . implode(',',$params['nodeids']) . ")\n/**" .
			__FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
			$result = $db->query_write($sql);
		}

		return $result;
	}

	/*** Clone a node
	 *
	 *	@param	mixed		the query parameters
	 * 	@param	object		the database object
	 * 	@param	bool		whether we run the query, or just validate that we can run it.
	 *
	 *	@return	int
	 *
	 ***/
	public function cloneNodes($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['nodeids']) AND is_array($params['nodeids']) AND !empty($params['parentid']));
		}

		$oldnewnodes = array(); // A var that stores the relationship 'oldnodeid' => 'newcopiednodeid'

		$nodes = vB::getDbAssertor()->getRows('vBForum:node',
			array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'nodeid' => $params['nodeids']
			),
			array(
				'field' => array('publishdate'),
				'direction' => array(vB_dB_Query::SORT_ASC)
			)
		);

		$newtitleset = false;
		foreach ($nodes as $node)
		{
			$children = vB::getDbAssertor()->getRows('vBForum:closure',
				array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'parent' => intval($node['nodeid']),
				),
				array(
					'field' => array('depth'),
					'direction' => array(vB_dB_Query::SORT_ASC)
				)
			);

			$node_api = vB_Api::instanceInternal('node');

			foreach ($children as $k => $closure)
			{
				$child = $node_api->getNode($closure['child'], false, false);

				// Clone node record
				$newnodeid = $this->cloneNodeRecord($db, 'node', $child['nodeid']);
				if (!$newnodeid)
				{
					continue;
				}

				$oldnewnodes[$child['nodeid']] = $newnodeid;

				// Make sure that level 0 closure of this node exists.
				$db->query_write("INSERT INTO " . TABLE_PREFIX . "closure (parent, child, depth, publishdate)
					VALUES
					($newnodeid, $newnodeid, 0, $closure[publishdate])
				");
				//Now the remaining records
				$db->query_write("INSERT INTO " . TABLE_PREFIX . "closure (parent, child, depth, publishdate)
					SELECT cl.parent, $newnodeid, cl.depth + 1, $closure[publishdate] FROM  " . TABLE_PREFIX . "closure AS cl
					WHERE child = " . $params['parentid'] );

				// Attach. Always clone.
				$this->cloneNodeRecord($db, 'attach', $child['nodeid'], $newnodeid);

				switch ($child['contenttypeid'])
				{
					case vB_Types::instance()->getContentTypeID('vBForum_Channel'):
						// Channel
						$this->cloneNodeRecord($db, 'channel', $child['nodeid'], $newnodeid);
						break;

					case vB_Types::instance()->getContentTypeID('vBForum_Gallery'):
						// Gallery
						$this->cloneNodeRecord($db, 'text', $child['nodeid'], $newnodeid);
						$this->cloneNodeRecord($db, 'gallery', $child['nodeid'], $newnodeid);
						break;

					case vB_Types::instance()->getContentTypeID('vBForum_Link'):
						// Link
						$this->cloneNodeRecord($db, 'text', $child['nodeid'], $newnodeid);
						$this->cloneNodeRecord($db, 'link', $child['nodeid'], $newnodeid);
						break;

					case vB_Types::instance()->getContentTypeID('vBForum_Photo'):
						// Photo
						$this->cloneNodeRecord($db, 'photo', $child['nodeid'], $newnodeid);
						break;

					case vB_Types::instance()->getContentTypeID('vBForum_Poll'):
						// Poll
						$this->cloneNodeRecord($db, 'text', $child['nodeid'], $newnodeid);
						$this->cloneNodeRecord($db, 'poll', $child['nodeid'], $newnodeid);
						break;

					case vB_Types::instance()->getContentTypeID('vBForum_PrivateMessage'):
						// PrivateMessage
						$this->cloneNodeRecord($db, 'text', $child['nodeid'], $newnodeid);
						$this->cloneNodeRecord($db, 'link', $child['nodeid'], $newnodeid);
						$this->cloneNodeRecord($db, 'privatemessage', $child['nodeid'], $newnodeid);
						break;

					case vB_Types::instance()->getContentTypeID('vBForum_Report'):
						// Report
						$this->cloneNodeRecord($db, 'text', $child['nodeid'], $newnodeid);
						$this->cloneNodeRecord($db, 'report', $child['nodeid'], $newnodeid);
						break;

					case vB_Types::instance()->getContentTypeID('vBForum_Text'):
						// Text
						$this->cloneNodeRecord($db, 'text', $child['nodeid'], $newnodeid);
						break;

					case vB_Types::instance()->getContentTypeID('vBForum_Video'):
						// Video
						$this->cloneNodeRecord($db, 'text', $child['nodeid'], $newnodeid);
						$this->cloneNodeRecord($db, 'video', $child['nodeid'], $newnodeid);
						break;
				}

				if ($k == 0)
				{
					// Whereever the first node ($k == 0) is moved to, it should be a new starter.
					$db->query_write("UPDATE " . TABLE_PREFIX . "node
						SET starter = nodeid
						WHERE nodeid = $newnodeid
					");

					// Move it to the new parent
					vB_Cache::instance(vB_Cache::CACHE_FAST)->event("nodeChg_$newnodeid");
					vB_Cache::instance()->event("nodeChg_$newnodeid");
					$result = $node_api->moveNodes(array($newnodeid), $params['parentid']);
				}
				else
				{
					// Get current parentid of the node
					$thisnode = $node_api->getNode($newnodeid, false, false);

					// Move it to the new parent
					// $oldnewnodes[$thisnode['parentid']] should always exist here as the new parent id of the node
					if ($oldnewnodes[$thisnode['parentid']])
					{
						$node_api->moveNodes(array($newnodeid), $oldnewnodes[$thisnode['parentid']]);
					}
				}
			}

			if (!$newtitleset AND !empty($params['newtitle']) AND intval($node['inlist']) AND !intval($node['protected']))
			{
				// Update the title of the oldest inlist node
				vB::getDbAssertor()->assertQuery('vBForum:node', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					'title' => $params['newtitle'],
					'htmltitle' => vB_String::htmlSpecialCharsUni(vB_String::stripTags($params['newtitle']), false),
					'urlident' => vB_String::getUrlIdent($params['newtitle']),
					vB_dB_Query::CONDITIONS_KEY => array(
						'nodeid' => $oldnewnodes[$node['nodeid']],
					)
				));

				$newtitleset = true;
			}

		}


		return $oldnewnodes;
	}

	protected function cloneNodeRecord($db, $table, $oldnodeid, $newnodeid = 0)
	{
		$newnodeid = intval($newnodeid);
		$oldnodeid = intval($oldnodeid);

		$nodefields = $this->table_data[$table]['structure'];
		// Unset Nodeid
		foreach ($nodefields as $k => $v)
		{
			if ($v == 'nodeid')
			{
				unset ($nodefields[$k]);
				break;
			}
		}

		if ($newnodeid)
		{
			$sql = "INSERT INTO " . TABLE_PREFIX . "$table (nodeid, " . implode(',', $nodefields) . ")
				SELECT $newnodeid, " . implode(',', $nodefields) . " FROM " . TABLE_PREFIX . "$table
				WHERE nodeid = " . $oldnodeid;
		}
		else
		{
			$sql = "INSERT INTO " . TABLE_PREFIX . "$table (" . implode(',', $nodefields) . ")
				SELECT " . implode(',', $nodefields) . " FROM " . TABLE_PREFIX . "$table
				WHERE nodeid = " . $oldnodeid;
		}

		$db->query_write($sql);

		return $db->insert_id();
	}

	/*** Returns a Content record
	 *
	 *	@param	mixed		the query parameters
	 * 	@param	object		the database object
	 * 	@param	bool		whether we run the query, or just validate that we can run it.
	 *
	 *	@return	mixed		array of node data
	 *
	 ***/
	function getContent($params, $db, $check_only = false, $dbSlave)
	{
		//This is not just an alias
		return $this->getFullContent($params, $db, $check_only, $dbSlave);
	}


	/*** Returns a Content record
	 *
	 *	@param	mixed		the query parameters
	 * 	@param	object		the database object
	 * 	@param	bool		whether we run the query, or just validate that we can run it.
	 *
	 *	@return	mixed		array of node data
	 *
	 ***/
	function getFullContent($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['tablename']) AND !empty($params['nodeid']);
		}

		if (is_array($params['nodeid']))
		{
			$ids = implode(',',$params['nodeid']);
			$idArray = $params['nodeid'];
		}
		else
		{
			$ids = $params['nodeid'];
			$idArray = array($ids);
		}
		$userContext = vB::getUserContext();
		$joins = '';

		if (is_array($params['tablename']))
		{
			$tables = $params['tablename'];
		}
		else
		{
			$tables = array($params['tablename']);
		}

		//Let's build the fields list. We'll add all the fields of the node table.
		//For the other tables, the first field gets its own name. subsequent fields
		//with the same name will be table_field.
		$selectedFields = array('node.*' => 'node.*');
		$nodeFields = array();
		foreach ($this->table_data['node']['structure'] AS $field)
		{
			$nodeFields[$field] = 'node.' . $field;
		}

		foreach ($tables as $table)
		{
			$joins .= "
			INNER JOIN  " . TABLE_PREFIX . "$table AS $table
			ON $table.nodeid = node.nodeid\n";
			foreach ($this->table_data[$table]['structure'] AS $field)
			{
				//nodeid is common to all these tables
				if ($field != 'nodeid')
				{
					if (array_key_exists($field, $nodeFields) OR array_key_exists($field, $selectedFields))
					{
						$selectedFields[] = $table . '.' . $field . " AS $table" . '_' . $field;
					}
					else
					{
						$selectedFields[$field] = $table. '.' . $field;
					}
				}
			}
		}


		$sql = "SELECT " . implode(',' ,$selectedFields) . ", ch.routeid AS channelroute, ch.title AS channeltitle, ch.nodeid AS channelid,
		 starter.routeid AS starterroute, starter.title AS startertitle, starter.authorname as starterauthorname, starter.prefixid as starterprefixid,
		 starter.userid as starteruserid, starter.lastcontentid as starterlastcontentid, starter.totalcount+1 as startertotalcount, starter.urlident AS starterurlident,
		 deleteuser.username AS deleteusername, lastauthor.username AS lastauthorname, editlog.reason AS edit_reason, editlog.userid AS edit_userid, editlog.username AS edit_username,
		 editlog.dateline AS edit_dateline, editlog.hashistory, starter.nodeoptions as starternodeoptions, ch.nodeoptions as channelnodeoptions
		 FROM " . TABLE_PREFIX . "node AS node
		 $joins
		 LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON (editlog.nodeid = node.nodeid)
		 LEFT JOIN " . TABLE_PREFIX . "node AS starter ON (starter.nodeid = node.starter)
		 LEFT JOIN " . TABLE_PREFIX . "node AS ch ON (ch.nodeid = starter.parentid)
		 LEFT JOIN " . TABLE_PREFIX . "user AS deleteuser ON (node.deleteuserid > 0 AND node.deleteuserid = deleteuser.userid)
		 LEFT JOIN " . TABLE_PREFIX . "user AS lastauthor ON (node.lastauthorid = lastauthor.userid)
		" ;

		$sql .= "
		 WHERE node.nodeid IN ($ids)\n/** getFullContent" .
			(defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') .
			 "**/";
		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}

		if (!empty($params[vB_dB_Query::PRIORITY_QUERY]))
		{
			$result = new $resultclass($db, $sql, false);
		}
		else
		{
			$result = new $resultclass($db, $sql);
		}
		return $result;
	}
	/** Returns the popular tags
	 * pass searchStr in the parameters to narrow the tags
	 *
	 *	@param	mixed		the query parameters
	 * 	@param	object		the database object
	 * 	@param	bool		whether we run the query, or just validate that we can run it.
	 *
	 *	@return	mixed		array of node data
	 *
	 **/
	function getPopularTags($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}

		$where = false;

		if (!empty($params['searchStr']))
		{
			$where = " WHERE tag.tagtext LIKE '" . $db->escape_string($params['searchStr']) . "%'";
		}

		$sql = "SELECT tag.tagtext, tagnode.userid, tag.tagid, count(tag.tagid) AS nr
			FROM " . TABLE_PREFIX . "tag AS tag
			JOIN " . TABLE_PREFIX. "tagnode AS tagnode ON (tag.tagid = tagnode.tagid)
			$where
			GROUP BY tag.tagid ORDER BY nr DESC,tag.tagtext ASC \n/** getPopularTags" .
			(defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') .
			 "**/";
		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}
		$result = new $resultclass($db, $sql);
		return $result;
	}


	/*** This updates the lastupdated when a node records publish status is updated
	 *
	 *	@param	mixed		the query parameters
	 * 	@param	object		the database object
	 * 	@param	bool		whether we run the query, or just validate that we can run it.
	 *
	 *	@return
	 *
	 ***/
	function setNextUpdate($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return  !empty($params['nodeid']) AND (isset($params['publishdate']) OR isset($params['unpublishdate']));
		}

		$params['publishdate'] = intval($params['publishdate']);
		$params['unpublishdate'] = intval($params['unpublishdate']);


		if (empty($params['publishdate']))
		{
			$nextupdate = $params['unpublishdate'];

		}
		else if (empty($params['unpublishdate']))
		{
			$nextupdate = $params['publishdate'];
		}
		else
		{
			$nextupdate = min($params['publishdate'], $params['unpublishdate']);
		}

		$sql = "UPDATE " . TABLE_PREFIX . "node AS node INNER JOIN " . TABLE_PREFIX . "closure
		AS cl ON node.nodeid = cl.parent AND cl.depth > 0 AND cl.child = " . $params['nodeid'] .
		" SET node.nextupdate = CASE WHEN node.nextupdate > 0 AND node.nextupdate < $nextupdate
		THEN node.nextupdate ELSE $nextupdate END";
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}

		$result = $db->query_write($sql);
		return true;
	}


	/*** Returns an array of objects needing moderation
	 *
	 *	@param	mixed		the query parameters
	 * 	@param	object		the database object
	 * 	@param	bool		whether we run the query, or just validate that we can run it.
	 *
	 *	@return	mixed		array of node data
	 *
	 ***/
	function getModeration($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['canModerate']);
		}

		if (is_array($params['canModerate']))
		{
			$ids = implode(',',$params['canModerate']);
		}
		else
		{
			$ids = $params['canModerate'];
		}

		$sql = "SELECT node.* FROM " . TABLE_PREFIX . "node
		 AS node INNER JOIN " . TABLE_PREFIX . "closure AS cl ON cl.child = node.nodeid AND
		 cl.parent in ($ids) \n";
		$where = array();

		if (!empty($params['cantModerate']))
		{
			if (is_array($params['cantModerate']))
			{
				$ids = implode(',',$params['cantModerate']);
			}
			else
			{
				$ids = $params['cantModerate'];
			}
			$sql .= "LEFT JOIN "  . TABLE_PREFIX . "closure AS cl2 ON cl2.child = node.nodeid AND cl2.parent in
			($ids)";
			$where[] = ' cl2.child IS NULL';
		}

		if (!empty($params['contenttypeid']))
		{

			if (is_array($params['contenttypeid']))
			{
				$ids = implode(',',$params['contenttypeid']);
			}
			else
			{
				$ids = $params['contenttypeid'];
			}

			$where[] =" contenttypeid IN ($ids)";
		}

		if (!empty($params['userid']))
		{

			if (is_array($params['userid']))
			{
				$ids = implode(',',$params['userid']);
			}
			else
			{
				$ids = $params['userid'];
			}

			$where[] = " userid IN ($ids)";
		}

		if (empty($params['fetchall']) OR !$params['fetchall'])
		{
			$where[] = ' node.publishdate = 0';
		}
		else
		{
			$where[] = ' node.publishdate < 1';
		}



		if (!empty($where))
		{
			$sql .= " WHERE " . implode(' AND ', $where);
 		}

		if (!empty($params['sort']))
		{
			if (is_array($params['sort']))
			{
				if (isset($params['sort']['sortby']) AND
					(($params['sort']['sortby'] == 'username') OR ($params['sort']['sortby'] == 'title')
					OR ($params['sort']['sortby'] == 'created') OR ($params['sort']['sortby'] == 'contenttypeid'))
					)
				{
					$sort = " ORDER BY node." . $params['sort']['sortby'];

					if (isset($params['sort']['direction'])	AND ($params['sort']['direction'] == 'desc'))
					{
						$sort .= " DESC ";
					}
					else
					{
						$sort .= " ASC ";
					}
				}
			}
			else if (($params['sort'] == 'username') OR ($params['sort'] == 'title')
			OR ($params['sort'] == 'created') OR ($params['sort'] == 'contenttypeid'))
			{
				$sort = " ORDER BY node." . $params['sort'] . " ASC";
			}
		}

		if (empty($sort))
		{
			$sort = " ORDER BY node.created ASC";
		}

		if (!empty($params[vB_dB_Query::PARAM_LIMIT]) AND (intval($params[vB_dB_Query::PARAM_LIMIT]) > 0))
		{
			$sql .= $sort . ' LIMIT ' . intval($params[vB_dB_Query::PARAM_LIMIT]);
		}
		else
		{
			$sql .= $sort . ' LIMIT 100';
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}
		$result = new $resultclass($db, $sql);
		return $result;
	}


	/*** Returns an array of users that meet criteria
	 *
	 *	@param	mixed		the query parameters
	 * 	@param	object		the database object
	 * 	@param	bool		whether we run the query, or just validate that we can run it.
	 *
	 *	@return	mixed		array of users
	 *
	 ***/
	function getSubscriptionUsers($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['threadids']) OR !empty($params['userids']);
		}

		$sql = array();
		if (!empty($params['threadids']))
		{
			$sql[] = "subscribethread.threadid IN(" . implode(', ', $params['threadids']) . ")";
		}
		if (!empty($params['userids']))
		{
			$sql[] = "subscribethread.userid IN (" . implode(', ', $params['userids']) . ")";
		}

		if ($sql)
		{
			$bf_misc_useroptions = vB::getDatastore()->get_value('bf_misc_useroptions');
			// unsubscribe users who can't view the forum the threads are now in
			$sql2 = "
				SELECT user.userid, usergroupid, membergroupids, infractiongroupids, IF(options & " . $bf_misc_useroptions['hasaccessmask'] . ", 1, 0) AS hasaccessmask,
					thread.postuserid, subscribethread.canview, subscribethreadid, thread.forumid
				FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
				INNER JOIN " . TABLE_PREFIX . "user AS user ON (subscribethread.userid = user.userid)
				INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (subscribethread.threadid = thread.threadid)
				WHERE
					" . implode(" AND ", $sql) . "
			";
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql2<br />\n";
		}
		$result = new $resultclass($db, $sql2);
		return $result;
	}

	function updateSubscriptionUsers($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['deleteuser']) OR !empty($params['adduser']);
		}

		// unsubscribe users who can't view the forum the threads are now in
		$sql = "
			UPDATE " . TABLE_PREFIX . "subscribethread
			SET canview =
			CASE
				" . (!empty($params['deleteuser']) ? " WHEN subscribethreadid IN (" . implode(', ', $params['deleteuser']) . ") THEN 0" : "") . "
				" . (!empty($params['adduser']) ? " WHEN subscribethreadid IN (" . implode(', ', $params['adduser']) . ") THEN 1" : "") . "
			ELSE canview
			END
			WHERE subscribethreadid IN (" . implode(', ', array_merge($params['deleteuser'], $params['adduser'])) . ")
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

	function isModAll($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['userid']);
		}

		$params['userid'] = intval($params['userid']);
		$sql = "
			SELECT nodeid, moderatorid, permissions, permissions2
			FROM " . TABLE_PREFIX . "moderator
			WHERE userid = $params[userid]" . (!$params['issupermod'] ? ' AND nodeid != -1' : '');

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}
		$result = new $resultclass($db, $sql);
		return $result;
	}


	public function fetchTemplateIdsByParentlist($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return isset($params['parentlist']);
		}
		else
		{
			$parents = explode(',', $params['parentlist']);
			$i = sizeof($parents);
			$querySele = '';
			$queryJoin = '';
			foreach($parents AS $setid)
			{
				if ($setid != -1 AND $i > 1)
				{
					$querySele = ",\nt$i.templateid AS templateid_$i, t$i.title AS title$i, t$i.styleid AS styleid_$i $querySele";
					$queryJoin = "\nLEFT JOIN " . TABLE_PREFIX . "template AS t$i ON (t1.title=t$i.title AND t$i.styleid=$setid)$queryJoin";
					$i--;
				}
			}

			$sql = "
				SELECT t1.templateid AS templateid_1, t1.title $querySele
				FROM " . TABLE_PREFIX . "template AS t1 $queryJoin
				WHERE t1.styleid IN (-1,0)
				ORDER BY t1.title
			";
			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}

	public function fetchCustomtempsByParentlist($params, $db, $check_only = false)
	{
		if (empty($params['parentlist']))
		{
			return false;
		}

		$styleids = trim($params['parentlist']);

		if (strlen($styleids) > 3)
		{
			$styleids = substr($styleids, 0, -3);
		}

		if (empty($styleids))
		{
			return false;
		}

		if ($check_only)
		{
			return true;
		}

		$sql = "
			SELECT t1.templateid, t1.title, INSTR(',$params[parentlist],', CONCAT(',', t1.styleid, ',') ) AS ordercontrol, t1.styleid
			FROM " . TABLE_PREFIX . "template AS t1
			LEFT JOIN " . TABLE_PREFIX . "template AS t2 ON (t2.title=t1.title AND t2.styleid=-1)
			WHERE t1.styleid IN ($styleids)
			ORDER BY title, ordercontrol
		";

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	public function updateStyle($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['query']) AND !empty($params['styleid']);
		}
		else
		{

			$sql = "
				UPDATE " . TABLE_PREFIX . "style SET\n" . implode(",\n", $params['query']) . "\nWHERE styleid = $params[styleid]
			";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$result = new $resultclass($db, $sql);
			return $result;
		}
	}


	function rebuildLanguage($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['phrasearray']) AND !empty($params['languageid']);
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::getConfig();
		$result = null;
		$params['languageid'] = (int) $params['languageid'];

		foreach($params['phrasearray'] as $fieldname => $phrases)
		{
			$result = null;
			ksort($phrases);
			$cachefield = $fieldname;
			$phrases = preg_replace('/\{([0-9]+)\}/siU', '%\\1$s', $phrases);
			$cachetext = $db->escape_string(serialize($phrases));
			$sql = "
				UPDATE " . TABLE_PREFIX . "language
				SET phrasegroup_$cachefield = '$cachetext'
				WHERE languageid = $params[languageid]
			";
			if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
			{
				echo "sql: $sql<br />\n";
			}
			$result = $db->query_write($sql);
		}

		if ($result === null) // shouldn't return null
		{
			$sql = "
				UPDATE " . TABLE_PREFIX . "language
				SET title = title
				WHERE languageid = $params[languageid]
			";
			if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
			{
				echo "sql: $sql<br />\n";
			}
			$result = $db->query_write($sql);
		}

		return $result;
	}


	function fetchPhrase($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['fieldname']) AND !empty($params['phrasename']);
		}

		//make sure we have a languageid
		if (empty($params['languageid']) AND !$params['alllanguages'])
		{
			$session = vB::getCurrentSession();

			if (empty($session))
			{
				$options = vB::getDataStore()->getValue('options');
				$languageid = $options['languageid'];
			}
			else
			{
				$languageid = $session->get('languageid');
			}
		}
		else
		{
			$languageid = $params['languageid'];
		}

		$sql = "
			SELECT text, languageid, special
			FROM " . TABLE_PREFIX . "phrase AS phrase
			LEFT JOIN " . TABLE_PREFIX . "phrasetype USING (fieldname)
			WHERE phrase.fieldname = '" . $db->escape_string($params['fieldname']) . "'
			AND varname = '" . $db->escape_string($params['phrasename']) . "' "
			. iif(!$params['alllanguages'], "AND languageid IN (-1, 0, $languageid)")
		;

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}
		$result = new $resultclass($db, $sql);
		return $result;
	}

	// build_user_statistics()
	function fetchUserStats($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}

		$options = vB::getDatastore()->get_value('options');

		$sumsql = '';

		$sql = "
			SELECT
			$sumsql
			COUNT(*) AS users,
			MAX(userid) AS maxid
			FROM " . TABLE_PREFIX . "user
			WHERE
			usergroupid NOT IN (3,4)
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


	// build_birthdays()
	function fetchBirthdays($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['todayneggmt']) AND !empty($params['todayposgmt']);
		}

		$options = vB::getDatastore()->get_value('options');
		$usergroupcache = vB::getDatastore()->get_value('usergroupcache');
		$bf_ugp_genericoptions = vB::getDatastore()->get_value('bf_ugp_genericoptions');

		// Seems quicker to grab the ids rather than doing a JOIN
		$usergroupids = 0;
		foreach($usergroupcache AS $usergroupid => $usergroup)
		{
			if ($usergroup['genericoptions'] & $bf_ugp_genericoptions['showbirthday'])
			{
				$usergroupids .= ", $usergroupid";
			}
		}

		$activitycut = '';

		$sql = "
			SELECT username, userid, birthday, showbirthday
			FROM " . TABLE_PREFIX . "user
			WHERE (birthday LIKE '$params[todayneggmt]-%' OR birthday LIKE '$params[todayposgmt]-%')
			AND usergroupid IN ($usergroupids)
			AND showbirthday IN (2, 3)
			$activitycut
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

	// build_picture_comment_counters()
	function fetchModeratedPicCommentCount($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['contenttypeid']) AND !empty($params['userid']);
		}

		$sql = "
			SELECT COUNT(*) AS moderation
			FROM " . TABLE_PREFIX . "attachment AS attachment
			INNER JOIN " . TABLE_PREFIX . "picturecomment AS picturecomment ON (attachment.filedataid = picturecomment.filedataid AND attachment.userid = picturecomment.userid)
			WHERE
				attachment.contenttypeid = $params[contenttypeid]
					AND
				attachment.userid = $params[userid]
					AND
				picturecomment.state = 'moderation'
			" . ($params['coventry'] ? "AND (picturecomment.postuserid NOT IN ($params[coventry]) OR picturecomment.postuserid = $params[userid])" : '')
		;

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
	 * Used for getFollowing follow API method
	 */
	function getUserFollowing($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['userid']) AND !empty($params[vB_Api_Follow::FOLLOWTYPE])) ? true : false;
		}
		else
		{
			$cleaner = vB::getCleaner();
			$cleaned = $cleaner->cleanArray($params, array('userid' => vB_Cleaner::TYPE_UINT, vB_dB_Query::PARAM_LIMIT  => vB_Cleaner::TYPE_UINT,
				vB_dB_Query::PARAM_LIMITPAGE  => vB_Cleaner::TYPE_UINT));

			if (!empty($params['contenttypeid']))
			{
				if (is_array($params['contenttypeid']))
				{
					$cleaned['contenttypeid'] = $cleaner->clean($params['contenttypeid'], vB_Cleaner::TYPE_ARRAY_INT);
				}
				else
				{
					$cleaned['contenttypeid'] = array($cleaner->clean($params['contenttypeid'],  vB_Cleaner::TYPE_ARRAY_INT));
				}
			}
			$contenttypeid = vB_Types::instance()->getContentTypeId('vBForum_Channel');

			if ($params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_ALL	)
			{

				$sql[] = "
					### Channels ###
					SELECT follow.title AS title, follow.nodeid AS keyval, 'node' AS sourcetable, IF(follow.lastcontent = 0, follow.lastupdate, follow.lastcontent) AS lastactivity,
					follow.totalcount AS activity, 'Channel' AS type
					FROM " . TABLE_PREFIX . "node AS follow
					INNER JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON sd.discussionid = follow.nodeid AND sd.userid = " . $cleaned['userid'];
			}
			else if ($params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_CHANNELS)
			{
				$sql[] = "
					### Channels ###
					SELECT follow.title AS title, follow.nodeid AS keyval, 'node' AS sourcetable, IF(follow.lastcontent = 0, follow.lastupdate, follow.lastcontent) AS lastactivity,
					follow.totalcount AS activity, 'Channel' AS type
					FROM " . TABLE_PREFIX . "node AS follow
					INNER JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON sd.discussionid = follow.nodeid AND sd.userid = " . $cleaned['userid'] . "
					WHERE follow.contenttypeid = " . $contenttypeid;
			}
			else if ($params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_CONTENT)
			{
				if (!empty($cleaned['contenttypeid']) AND !is_array($cleaned['contenttypeid']))
				{
					$cleaned['contenttypeid'] = array($cleaned['contenttypeid']);
				}
				$thisSql = "
					### Content ###
					SELECT follow.title AS title, follow.nodeid AS keyval, 'node' AS sourcetable, IF(follow.lastcontent = 0, follow.lastupdate, follow.lastcontent) AS lastactivity,
					follow.totalcount AS activity, type.class AS type
					FROM " . TABLE_PREFIX . "node AS follow
					INNER JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON sd.discussionid = follow.nodeid AND sd.userid = " . $cleaned['userid'] . "
					INNER JOIN " . TABLE_PREFIX . "contenttype AS type ON type.contenttypeid = follow.contenttypeid \n";

				if (empty($cleaned['contenttypeid']))
				{
					$thisSql .= "WHERE follow.contenttypeid NOT IN ($contenttypeid)\n";

				}
				else
				{
					$thisSql .= "WHERE follow.contenttypeid IN (" . implode(", ", $cleaned['contenttypeid']) . ")\n" ;
				}
				$sql[] = $thisSql;
			}

			if (
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_USERS
				OR
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_ALL
			)
			{
				$sql[] = "
					### Users ###
					SELECT follow.username AS title, follow.userid AS keyval, 'user' AS sourcetable, IFNULL(follow.lastactivity, follow.joindate) AS lastactivity, follow.posts as activity, 'Member' AS type
					FROM " . TABLE_PREFIX . "user AS follow
					INNER JOIN " . TABLE_PREFIX . "userlist AS ul ON ul.relationid = follow.userid AND ul.userid = " . $cleaned['userid'] ."
					WHERE ul.type = 'follow' AND ul.friend = 'yes'";
			}

			if (
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_CHANNELS
				OR
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_ALL
			)
			{
				$blogChannel = vB_Api::instanceInternal('blog')->getBlogChannel();
				$sql[] = "
					### Blog Channels ###
					SELECT follow.title AS title, follow.nodeid AS keyval, 'node' AS sourcetable, IF(follow.lastcontent = 0, follow.lastupdate, follow.lastcontent) AS lastactivity,
					follow.totalcount AS activity, 'Channel' AS type
					FROM " . TABLE_PREFIX . "node AS follow
					INNER JOIN " . TABLE_PREFIX . "groupintopic AS git ON git.nodeid = follow.nodeid AND git.userid = " . $cleaned['userid'] . "
					INNER JOIN " . TABLE_PREFIX . "closure AS blog_check ON (blog_check.child = git.nodeid AND blog_check.parent = $blogChannel)
					WHERE follow.contenttypeid = " . $contenttypeid;
			}

			if (isset($params[vB_Api_Follow::FOLLOWFILTERTYPE_SORT]))
			{
				if (is_array($params[vB_Api_Follow::FOLLOWFILTERTYPE_SORT]))
				{
					$sorts = array();
					foreach ($params[vB_Api_Follow::FOLLOWFILTERTYPE_SORT] as $key => $value)
					{
						//we may have something like 'publishdate' => 'desc'
						if (
							($key == 'title')
							OR
							($key == 'keyval')
							OR
							($key == 'lastactivity')
							OR
							($key == 'activity')
						)
						{
							if (strtolower($value) == 'desc')
							{
								$sorts[] = "$key DESC";
							}
							else
							{
								$sorts[] = "$key ASC";
							}
						}
						else if (
							($value == 'title')
							OR
							($value == 'keyval')
							OR
							($value == 'lastactivity')
							OR
							($value == 'activity')
						)
						{
							$sorts[] = "$value ASC";
						}
						else if (
							is_array($value)
							AND
							isset($value['sortby'])
							AND
							(
								($value['sortby'] == 'title')
								OR
								($value['sortby'] == 'keyval')
								OR
								($value['sortby'] == 'lastactivity')
								OR
								($value['sortby'] == 'activity')
							)
						)
						{
							if (
								isset($value['direction'])
								AND
								(strtolower($value['direction']) == 'desc')
							)
							{
								$sorts[] = $value['sortby'] . " DESC";
							}
							else
							{
								$sorts[] = $value['sortby'] . " ASC";
							}
							$sortfields[] = "node.$value AS sort_$value";
						}
					}
				}
				else if (
					($params['sort'] == 'title')
					OR
					($params['sort'] == 'keyval')
					OR
					($params['sort'] == 'lastactivity')
					OR
					($params['sort'] == 'activity')
				)
				{
					$sorts[] = $params['sort'] . ' ASC';
				}
			}

			if (empty($sorts))
			{
				$sort = " ORDER BY title DESC LIMIT ";
			}
			else
			{
				$sort = " ORDER BY " . implode(", ", $sorts) . " LIMIT ";
			}

			$limit = "";
			if (isset($cleaned[vB_dB_Query::PARAM_LIMIT]) AND intval($cleaned[vB_dB_Query::PARAM_LIMIT]))
			{
				$perpage = intval($cleaned[vB_dB_Query::PARAM_LIMIT]);
			}
			else
			{
				$perpage = 100;
			}

			if (isset($cleaned[vB_dB_Query::PARAM_LIMITPAGE]) AND (intval($cleaned[vB_dB_Query::PARAM_LIMITPAGE]) > 1))
			{
				$limit .=  ($perpage * (intval($cleaned[vB_dB_Query::PARAM_LIMITPAGE]) - 1)) . ',';
			}

			$sql = implode("\n UNION  ALL \n", $sql) . " \r\n" . $sort . $limit . $perpage;
			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$config = vB::getConfig();

			if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
			{
				echo "sql: $sql\n /**" . var_export($params, true) . "**/\n";
			}
			$result = new $resultclass($db, $sql);

			return $result;
		}
	}

	/**
	 * Used to get the total count for getFollowing follow API method
	 */
	function getUserFollowingCount($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['userid']) AND !empty($params[vB_Api_Follow::FOLLOWTYPE])) ? true : false;
		}
		else
		{
			$cleaner = vB::getCleaner();
			$cleaned = $cleaner->cleanArray($params, array('userid' => vB_Cleaner::TYPE_UINT));

			if (!empty($params['contenttypeid']))
			{
				if (is_array($params['contenttypeid']))
				{
					$cleaned['contenttypeid'] = $cleaner->clean($params['contenttypeid'], vB_Cleaner::TYPE_ARRAY_INT);
				}
				else
				{
					$cleaned['contenttypeid'] = array($cleaner->clean($params['contenttypeid'],  vB_Cleaner::TYPE_ARRAY_INT));
				}
			}
			$contenttypeid = vB_Types::instance()->getContentTypeId('vBForum_Channel');

			if ($params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_ALL	)
			{
				$sql[] = "### All Content ###
					SELECT follow.nodeid AS keyval
					FROM " . TABLE_PREFIX . "node AS follow
					INNER JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON sd.discussionid = follow.nodeid AND sd.userid = " . intval($cleaned['userid']) ;
			}
			else if ($params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_CHANNELS)
			{
				$sql[] = "
					### Channels ###
					SELECT follow.nodeid AS keyval
					FROM " . TABLE_PREFIX . "node AS follow
					INNER JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON sd.discussionid = follow.nodeid AND sd.userid = " . $cleaned['userid'] . "
					WHERE follow.contenttypeid = " . $contenttypeid;
			}

			else  if ($params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_CONTENT)
			{
				$thisSql = "### Content ###
					SELECT follow.nodeid AS keyval
					FROM " . TABLE_PREFIX . "node AS follow
					INNER JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON sd.discussionid = follow.nodeid AND sd.userid = " . $cleaned['userid'] . "
					INNER JOIN " . TABLE_PREFIX . "contenttype AS type ON type.contenttypeid = follow.contenttypeid \n";


				if (empty($cleaned['contenttypeid']))
				{
					$thisSql .= "WHERE follow.contenttypeid NOT IN ($contenttypeid)\n";

				}
				else
				{
					$thisSql .= "WHERE follow.contenttypeid IN (" . implode(", ", $cleaned['contenttypeid']) . ")\n" ;
				}
				$sql[] = $thisSql;
			}

			if (
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_USERS
				OR
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_ALL
			)
			{
				$sql[] = "
					### Users ###
					SELECT follow.userid AS keyval
					FROM " . TABLE_PREFIX . "user AS follow
					INNER JOIN " . TABLE_PREFIX . "userlist AS ul ON ul.relationid = follow.userid AND ul.userid = " . $cleaned['userid'] ."
					WHERE ul.type = 'follow' AND ul.friend = 'yes'";
			}

			if (
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_CHANNELS
				OR
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_ALL
			)
			{
				$blogChannel = vB_Api::instanceInternal('blog')->getBlogChannel();
				$sql[] = "
					### Blog Channels ###
					SELECT follow.nodeid AS keyval
					FROM " . TABLE_PREFIX . "node AS follow
					INNER JOIN " . TABLE_PREFIX . "groupintopic AS git ON git.nodeid = follow.nodeid AND git.userid = " . $cleaned['userid'] . "
					INNER JOIN " . TABLE_PREFIX . "closure AS blog_check ON (blog_check.child = git.nodeid AND blog_check.parent = $blogChannel)
					WHERE follow.contenttypeid = " . $contenttypeid;
			}

			$innersql = "(" . implode(") UNION ALL (", $sql) . ")\r\n";
			$sql = "SELECT COUNT(userfollowing.keyval) AS total
			FROM
			(" . $innersql . ") AS userfollowing";
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

	/**
	 * Gets the user followers
	 */
	function getUserFollowers($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$cleaner = vB::getCleaner();
			$cleaned = $cleaner->cleanArray($params, array('userid' => vB_Cleaner::TYPE_UINT, vB_dB_Query::PARAM_LIMITPAGE => vB_Cleaner::TYPE_UINT,
				vB_dB_Query::PARAM_LIMIT => vB_Cleaner::TYPE_UINT));

			if (!empty($params['contenttypeid']))
			{
				if (is_array($params['contenttypeid']))
				{
					$cleaned['contenttypeid'] = $cleaner->clean($params['contenttypeid'], vB_Cleaner::TYPE_ARRAY_INT);
				}
				else
				{
					$cleaned['contenttypeid'] = array($cleaner->clean($params['contenttypeid'],  vB_Cleaner::TYPE_ARRAY_INT));
				}
			}
			$contenttypeid = vB_Types::instance()->getContentTypeId('vBForum_Channel');
			$select = "SELECT u.userid, u.username AS username, u.usergroupid AS usergroupid, IFNULL(u.lastactivity, u.joindate) as lastactivity,
				IFNULL((SELECT userid FROM " . TABLE_PREFIX . "userlist AS ul2 WHERE ul2.userid = " . $cleaned['userid'] . " AND ul2.relationid = u.userid AND ul2.type = 'follow' AND ul2.friend = 'yes'), 0) as isFollowing,
				IFNULL((SELECT userid FROM " . TABLE_PREFIX . "userlist AS ul2 WHERE ul2.userid = " . $cleaned['userid'] . " AND ul2.relationid = u.userid AND ul2.type = 'follow' AND ul2.friend = 'pending'), 0) as isPending\n";
			$queryFrom = "FROM " . TABLE_PREFIX . "user AS u
				INNER JOIN " . TABLE_PREFIX . "userlist AS ul ON (u.userid = ul.userid AND ul.relationid = " . $cleaned['userid'] . ")\n
			";
			$queryWhere = "WHERE ul.type = 'follow' AND ul.friend = 'yes'\n";
			$queryExtra = "";

			if (isset($params[vB_Api_Follow::FOLLOWFILTERTYPE_SORT]) AND !empty($params[vB_Api_Follow::FOLLOWFILTERTYPE_SORT]))
			{
				switch ($params[vB_Api_Follow::FOLLOWFILTERTYPE_SORT])
				{
					case vB_Api_Follow::FOLLOWFILTER_SORTMOST:
						$queryExtra .= "ORDER BY lastactivity DESC, username ASC\n";
						break;
					case vB_Api_Follow::FOLLOWFILTER_SORTLEAST:
						$queryExtra .= "ORDER BY lastactivity ASC, username ASC\n";
						break;
					default:
						$queryExtra .= "ORDER BY username ASC\n";
						break;
				}
			}

			if (isset($cleaned[vB_dB_Query::PARAM_LIMITPAGE]) AND isset($cleaned[vB_dB_Query::PARAM_LIMIT]))
			{
				$queryExtra .= "LIMIT " . ( ($cleaned[vB_dB_Query::PARAM_LIMITPAGE] - 1) * intval($cleaned[vB_dB_Query::PARAM_LIMIT]) ) .
					 ", " . intval($cleaned[vB_dB_Query::PARAM_LIMIT]) . "\n";
			}

			$sql = $select . $queryFrom . $queryWhere . $queryExtra;
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



	/** Gets the user following content for the profile page- this is the settings, not the content
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *  @param	bool
	 *
	 *	@result	mixed
	 */
	public function getFollowing($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$nodeApi = vB_Api::instanceInternal('node');
			$VMChannel = $nodeApi->fetchVMChannel();
			$extraFields = '';
			$cleaner = vB::getCleaner();
			$cleaned = $cleaner->cleanArray($params, array('followerid' => vB_Cleaner::TYPE_UINT, vB_dB_Query::PARAM_LIMITPAGE => vB_Cleaner::TYPE_UINT,
				vB_dB_Query::PARAM_LIMIT => vB_Cleaner::TYPE_UINT));

			if (!empty($params['contenttypeid']))
			{
				if (is_array($params['contenttypeid']))
				{
					$cleaned['contenttypeid'] = $cleaner->clean($params['contenttypeid'], vB_Cleaner::TYPE_ARRAY_INT);
				}
				else
				{
					$cleaned['contenttypeid'] = array($cleaner->clean($params['contenttypeid'],  vB_Cleaner::TYPE_ARRAY_INT));
				}
			}

			if ($params['following'] == vB_Api_Search::FILTER_FOLLOWING_CHANNEL OR $params['following'] == vB_Api_Search::FILTER_FOLLOWING_BOTH)
			{
				$extraFields .= ', IFNULL(p_sd.discussionid, 0) AS isFollowingContent, IFNULL(sd.discussionid, 0) AS isFollowingChannel, IFNULL(ul.relationid, 0) AS isFollowingMember';
			}
			else
			{
				$extraFields .= ', IFNULL(p_sd.discussionid, 0) AS isFollowingChannel, IFNULL(sd.discussionid, 0) AS isFollowingContent';
			}
			$permflags = $this->getNodePermTerms();
			unset($permflags['joins']['starter']);

			$sql = "SELECT node.*, type.class AS contenttypeclass, postfor.username AS postfor, IFNULL(ul.relationid, 0) AS isFollowingMember $extraFields
			FROM " . TABLE_PREFIX . "node AS node
				INNER JOIN " . TABLE_PREFIX . "contenttype AS type ON type.contenttypeid = node.contenttypeid
				LEFT JOIN " . TABLE_PREFIX . "user AS postfor ON postfor.userid = node.setfor
				LEFT JOIN " . TABLE_PREFIX . "closure AS vmcheck ON vmcheck.child = node.nodeid AND vmcheck.parent = $VMChannel
				LEFT JOIN " . TABLE_PREFIX . "node AS starter ON starter.nodeid = node.starter
				LEFT JOIN " . TABLE_PREFIX . "node AS ch ON ch.nodeid = starter.parentid
				" . implode("\n", $permflags['joins']) . "\n";

			switch ($params['following'])
			{
				case vB_Api_Search::FILTER_FOLLOWING_USERS:
					$sql .= " INNER JOIN " . TABLE_PREFIX . "userlist AS ul ON node.userid = ul.relationid AND ul.type = 'follow' AND ul.friend = 'yes'
					LEFT JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON node.nodeid = sd.discussionid AND sd.userid = " . $cleaned['followerid'] . "
					LEFT JOIN " . TABLE_PREFIX . "subscribediscussion AS p_sd ON p_sd.discussionid = ch.nodeid AND p_sd.userid = " . $cleaned['followerid'];
					break;
				case vB_Api_Search::FILTER_FOLLOWING_CONTENT:
					$sql .= " INNER JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON sd.discussionid = node.nodeid AND sd.userid = " . $cleaned['followerid'] . "
					LEFT JOIN " . TABLE_PREFIX . "userlist AS ul ON node.userid = ul.relationid AND ul.userid = " . $cleaned['followerid'] . "
					LEFT JOIN " . TABLE_PREFIX . "subscribediscussion AS p_sd ON p_sd.discussionid = ch.nodeid AND p_sd.userid = " . $cleaned['followerid'];
					break;
				case vB_Api_Search::FILTER_FOLLOWING_CHANNEL:
					$sql .= " INNER JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON sd.discussionid = ch.nodeid AND sd.userid = " . $cleaned['followerid'] . "
					LEFT JOIN " . TABLE_PREFIX . "userlist AS ul ON node.userid = ul.relationid AND ul.userid = " . $cleaned['followerid'] . "
					LEFT JOIN " . TABLE_PREFIX . "subscribediscussion AS p_sd ON p_sd.discussionid = node.nodeid AND p_sd.userid = " . $cleaned['followerid'];
					break;
				case vB_Api_Search::FILTER_FOLLOWING_BOTH:
					$sql .= " LEFT JOIN " . TABLE_PREFIX . "userlist AS ul ON node.userid = ul.relationid AND ul.userid = " . $cleaned['followerid'] . " AND ul.type = 'follow' AND ul.friend = 'yes'\n
					LEFT JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON ch.nodeid = sd.discussionid AND sd.userid = " . $cleaned['followerid'] . "
					LEFT JOIN " . TABLE_PREFIX . "subscribediscussion AS p_sd ON p_sd.discussionid = node.nodeid AND p_sd.userid = " . $cleaned['followerid'] . "
					LEFT JOIN " . TABLE_PREFIX . "subscribediscussion AS subscription ON node.nodeid = subscription.discussionid AND subscription.userid = " . $cleaned['followerid'];
					break;
				default:
					// just ignore
					break;
			}

			$sql .= " WHERE node.inlist > 0 AND type.class NOT IN ('Channel', 'PrivateMessage')"
				. $permflags['where'] . "\n";

			if (!empty($cleaned['contenttypeid']))
			{
				$sql .= "AND node.contenttypeid IN (" . implode(', ', $cleaned['contenttypeid']) .") \n";
			}

			//block people on the global ignore list.
			$options = vB::getDatastore()->getValue('options');
			if (trim($options['globalignore']) != '')
			{
				$blocked = preg_split('#\s+#s', $options['globalignore'], -1, PREG_SPLIT_NO_EMPTY);
				//the user can always see their own posts, so if they are in the blocked list we remove them
				$bbuserkey = array_search(vB::getCurrentSession()->get('userid') , $blocked);

				if ($bbuserkey !== FALSE AND $bbuserkey !== NULL)
				{
					unset($blocked["$bbuserkey"]);
				}

				//Make sure we didn't just empty the list
				if (!empty($blocked))
				{
					$sql .= " AND node.userid NOT IN (" . implode(',', $blocked) . ")";
				}
			}

			/** Follow filter */
			if (!empty($params['following']))
			{
				switch ($params['following'])
				{
					case vB_Api_Search::FILTER_FOLLOWING_USERS:
						$sql .= " AND ul.userid = " . $cleaned['followerid'];
						break;
					case vB_Api_Search::FILTER_FOLLOWING_CONTENT:
						$sql .= " AND sd.userid = " . $cleaned['followerid'];
						break;
					case vB_Api_Search::FILTER_FOLLOWING_CHANNEL:
						$sql .= " AND sd.userid = " . $cleaned['followerid'];
						break;
					case vB_Api_Search::FILTER_FOLLOWING_BOTH:
						$sql .= " AND (ul.userid IS NOT NULL OR sd.discussionid IS NOT NULL OR subscription.discussionid IS NOT NULL)";
						break;
					default:
						// just ignore
						break;
				}
			}

			/** Date filter */
			if (!empty($params[vB_Api_Node::FILTER_TIME]))
			{
				$datenow = vB::getRequest()->getTimeNow();
				switch ($params[vB_Api_Node::FILTER_TIME])
				{
					case vB_Api_Search::FILTER_LASTDAY:
						$timeVal = $datenow - (24 * 60 * 60);
						break;
					case vB_Api_Search::FILTER_LASTWEEK:
						$timeVal = $datenow - (7 * 24 * 60 * 60);
						break;
					case vB_Api_Search::FILTER_LASTMONTH:
						$timeVal = strtotime(date("Y-m-d H:i:s", $datenow) . " - 1 month");
						break;
					default:
						$timeVal = 0;
						break;
				}
				$sql .= " AND node.publishdate >= $timeVal";
			}

			if (isset($params[vB_Api_Node::FILTER_SORT]))
			{
				if (is_array($params[vB_Api_Node::FILTER_SORT]))
				{
					$sorts = array();
					foreach ($params[vB_Api_Node::FILTER_SORT] as $key => $value)
					{
						//we may have something like 'publishdate' => 'desc'
						if (
							($key == 'publishdate')
							OR
							($key == 'unpublishdate')
							OR
							($key == 'authorname')
							OR
							($key == 'displayorder')
							OR
							($key == 'votes')
						)
						{
							if (strtolower($value) == 'desc')
							{
								$sorts[] = "node.$key DESC";
							}
							else
							{
								$sorts[] = "node.$key ASC";
							}
						}
						else if (
							($value == 'publishdate')
							OR
							($value == 'unpublishdate')
							OR
							($value == 'authorname')
							OR
							($value == 'displayorder')
						)
						{
							$sorts[] = "node.$value ASC";
						}
						else if (
							is_array($value)
							AND
							isset($value['sortby'])
							AND
							(
								($value['sortby'] == 'publishdate')
								OR
								($value['sortby'] == 'unpublishdate')
								OR
								($value['sortby'] == 'authorname')
								OR
								($value['sortby'] == 'displayorder')
							)
						)
						{
							if (
								isset($value['direction'])
								AND
								(strtolower($value['direction']) == 'desc')
							)
							{
								$sorts[] = 'node.' . $value['sortby'] . " DESC";
							}
							else
							{
								$sorts[] = 'node.' . $value['sortby'] . " ASC";
							}

						}

						if (!empty($sorts))
						{
							$sort = implode(', ', $sorts);
						}
					}
				}
				else if (
					($params[vB_Api_Node::FILTER_SORT] == 'publishdate')
					OR
					($params[vB_Api_Node::FILTER_SORT] == 'unpublishdate')
					OR
					($params[vB_Api_Node::FILTER_SORT] == 'authorname')
					OR
					($params[vB_Api_Node::FILTER_SORT] == 'displayorder')
				)
				{
					$sort = 'node.' . $params[vB_Api_Node::FILTER_SORT] . ' ASC';
				}
			}

			if (empty($sort))
			{
				$sql .= " ORDER BY node.publishdate DESC LIMIT ";
			}
			else
			{
				$sql .= " ORDER BY $sort LIMIT ";
			}

			if (isset($params[vB_dB_Query::PARAM_LIMIT]) AND intval($params[vB_dB_Query::PARAM_LIMIT]))
			{
				$perpage = intval($cleaned[vB_dB_Query::PARAM_LIMIT]);
			}
			else if (isset($cleaned[vB_dB_Query::PARAM_LIMITPAGE]))
			{
				$perpage = 20;
			}
			else
			{
				$perpage = 500;
			}

			if (isset($cleaned[vB_dB_Query::PARAM_LIMITPAGE]) AND (intval($cleaned[vB_dB_Query::PARAM_LIMITPAGE]) > 1))
			{
				$sql .=  ($perpage * (intval($cleaned[vB_dB_Query::PARAM_LIMITPAGE]) - 1)) . ',';
			}
			$sql .= $perpage . "\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** Gets the content for the profile page based on the user's following settings.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *  @param	bool
	 *
	 *	@result	mixed
	 */
	public function getFollowingContent($params, $db, $check_only = false)
	{
		$validTypes = array(vB_Api_Follow::FOLLOWTYPE_USERS, vB_Api_Follow::FOLLOWTYPE_CONTENT,
			vB_Api_Follow::FOLLOWTYPE_CHANNELS, vB_Api_Follow::FOLLOWTYPE_ALL, vB_Api_Follow::FOLLOWTYPE_ACTIVITY);
		if ($check_only)
		{
			$parentCheck = (isset($params['parentid']) AND !is_numeric($params['parentid'])) ? false : true;
			return (!empty($params['followerid']) AND (intval($params['followerid']) > 0) AND !empty($params[vB_Api_Follow::FOLLOWTYPE])
				AND in_array($params[vB_Api_Follow::FOLLOWTYPE], $validTypes) AND $parentCheck);
		}
		else

		{
			$cleaner = vB::getCleaner();
			$cleaned = $cleaner->cleanArray($params, array('followerid' => vB_Cleaner::TYPE_UINT, 'parentid' => vB_Cleaner::TYPE_UINT,
				vB_dB_Query::PARAM_LIMITPAGE => vB_Cleaner::TYPE_UINT, vB_dB_Query::PARAM_LIMIT => vB_Cleaner::TYPE_UINT,
				'pageseemore' => vB_Cleaner::TYPE_UINT));

			if (!empty($params['contenttypeid']))
			{
				if (is_array($params['contenttypeid']))
				{
					$cleaned['contenttypeid'] = $cleaner->clean($params['contenttypeid'], vB_Cleaner::TYPE_ARRAY_INT);
				}
				else
				{
					$cleaned['contenttypeid'] = array($cleaner->clean($params['contenttypeid'],  vB_Cleaner::TYPE_ARRAY_INT));
				}
			}
			$nodeApi = vB_Api::instanceInternal('node');
			$VMChannel = $nodeApi->fetchVMChannel();
			$channelType = vB_Types::instance()->getContentTypeid('vBForum_Channel');
			$PMType = vB_Types::instance()->getContentTypeid('vBForum_PrivateMessage');
			$permflags = $this->getNodePermTerms();

			$sortfields = array();
			$outersorts = array();
			if (isset($params[vB_Api_Follow::FOLLOWFILTERTYPE_SORT]))
			{
				if (is_array($params[vB_Api_Follow::FOLLOWFILTERTYPE_SORT]))
				{
					$sorts = array();
					foreach ($params[vB_Api_Follow::FOLLOWFILTERTYPE_SORT] as $key => $value)
					{
						//we may have something like 'publishdate' => 'desc'
						if (
							($key == 'publishdate')
							OR
							($key == 'unpublishdate')
							OR
							($key == 'authorname')
							OR
							($key == 'displayorder')
							OR
							($key == 'votes')
							OR
							($key == 'title')
						)
						{
							if (strtolower($value) == 'desc')
							{
								$sorts[] = "$key DESC";
								$outersorts[] = "followingContent.sort_$key DESC";
							}
							else
							{
								$sorts[] = "$key ASC";
								$outersorts[] = "followingContent.sort_$key ASC";
							}
							$sortfields[] = "node.$key AS sort_$key";
						}
						else if (
							($value == 'publishdate')
							OR
							($value == 'unpublishdate')
							OR
							($value == 'authorname')
							OR
							($value == 'displayorder')
							OR
							($value == 'title')
						)
						{
							$sorts[] = "$value ASC";
							$sortfields[] = "node.$value AS sort_$value";
							$outersorts[] = "followingContent.sort_$value ASC";
						}
						else if (
							is_array($value)
							AND
							isset($value['sortby'])
							AND
							(
								($value['sortby'] == 'publishdate')
								OR
								($value['sortby'] == 'unpublishdate')
								OR
								($value['sortby'] == 'authorname')
								OR
								($value['sortby'] == 'displayorder')
								OR
								($value['sortby'] == 'title')
							)
						)
						{
							if (
								isset($value['direction'])
								AND
								(strtolower($value['direction']) == 'desc')
							)
							{
								$sorts[] = $value['sortby'] . " DESC";
								$outersorts[] = "followingContent.sort_{$value['sortby']} DESC";
							}
							else
							{
								$sorts[] = $value['sortby'] . " ASC";
								$outersorts[] = "followingContent.sort_{$value['sortby']} ASC";
							}
							$sortfields[] = "node.$value AS sort_$value";
						}
					}
				}
				else if (
					($params['sort'] == 'publishdate')
					OR
					($params['sort'] == 'unpublishdate')
					OR
					($params['sort'] == 'authorname')
					OR
					($params['sort'] == 'displayorder')
					OR
					($params['sort'] == 'title')
				)
				{
					$sorts[] = $params['sort'] . ' ASC';
					$sortfields[] = "node.{$params['sort']} AS sort_{$params['sort']}";
					$outersorts[] = "followingContent.sort_{$params['sort']} ASC";
				}
			}

			if (empty($sorts))
			{
				$sortfields[] = "node.publishdate AS sort_publishdate";
				$outersorts[] = "followingContent.sort_publishdate DESC";
			}

			if (
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_CHANNELS
					OR
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_ACTIVITY
					OR
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_ALL
			)
			{
				$sql[] = "
					### Following Channel ###
					SELECT node.nodeid, node.contenttypeid, node.lastcontentid, latest.contenttypeid AS lastcontenttypeid,
						postfor.username AS postfor, 0 AS isFollowingContent, sd.discussionid AS isFollowingChannel,
						0 AS isFollowingMember,
						" . implode(",", $sortfields) . "
					FROM " . TABLE_PREFIX . "node AS node
					" . ( !empty($cleaned['parentid']) ?
					"INNER JOIN " . TABLE_PREFIX . "closure AS cl ON (cl.child = node.nodeid)" : "") . "
					LEFT JOIN " . TABLE_PREFIX . "node AS latest ON (latest.nodeid = node.lastcontentid)
					LEFT JOIN " . TABLE_PREFIX . "user AS postfor ON (postfor.userid = node.setfor)
					INNER JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON (sd.discussionid = node.parentid
						AND sd.userid = " . $cleaned['followerid'] . "  AND node.nodeid = node.starter)
					" . (implode("\n", $permflags['joins']));
			}

			if ($params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_CONTENT
					OR
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_ACTIVITY
					OR
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_ALL
			)
			{
				$sql[] = "
					### Following Content ###
					SELECT node.nodeid, node.contenttypeid, node.lastcontentid, latest.contenttypeid AS lastcontenttypeid,
						postfor.username AS postfor, sd.discussionid AS isFollowingContent, 0 AS isFollowingChannel,
						0 AS isFollowingMember,
						" . implode(",", $sortfields) . "
					FROM " . TABLE_PREFIX . "node AS node
					" . ( !empty($cleaned['parentid']) ?
					"INNER JOIN " . TABLE_PREFIX . "closure AS cl ON (cl.child = node.nodeid)" : "") . "
					LEFT JOIN " . TABLE_PREFIX . "node AS latest ON (latest.nodeid = node.lastcontentid)
					LEFT JOIN " . TABLE_PREFIX . "user AS postfor ON (postfor.userid = node.setfor)
					INNER JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON (sd.discussionid = node.nodeid
						AND sd.userid = " . $params['followerid'] . " AND node.nodeid = node.starter)
					" . (implode("\n", $permflags['joins']));
			}

			if (
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_USERS
					OR
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_ALL
			)
			{
				$sql[] = "
					### Following Users ###
					SELECT node.nodeid, node.contenttypeid, node.lastcontentid, latest.contenttypeid AS lastcontenttypeid,
						postfor.username AS postfor, 0 AS isFollowingContent, 0 AS isFollowingChannel,
						ul.relationid AS isFollowingMember,
						" . implode(",", $sortfields) . "
					FROM " . TABLE_PREFIX . "node AS node
					" . ( !empty($cleaned['parentid']) ?
					"INNER JOIN " . TABLE_PREFIX . "closure AS cl ON (cl.child = node.nodeid)" : "") . "
					LEFT JOIN " . TABLE_PREFIX . "node AS latest ON (latest.nodeid = node.lastcontentid)
					LEFT JOIN " . TABLE_PREFIX . "user AS postfor ON (postfor.userid = node.setfor)
					INNER JOIN " . TABLE_PREFIX . "userlist AS ul ON (node.userid = ul.relationid
						AND ul.userid = " . $params['followerid'] . " AND ul.type = 'follow' AND ul.friend = 'yes'
						AND node.nodeid = node.starter)
					" . (implode("\n", $permflags['joins']));
			}

			if (
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_CHANNELS
					OR
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_ACTIVITY
					OR
				$params[vB_Api_Follow::FOLLOWTYPE] == vB_Api_Follow::FOLLOWTYPE_ALL
			)
			{
				$blogChannel = vB_Api::instanceInternal('blog')->getBlogChannel();
				$sql[] = "
					### Following Blog Channel ###
					SELECT node.nodeid, node.contenttypeid, node.lastcontentid, latest.contenttypeid AS lastcontenttypeid,
						postfor.username AS postfor, 0 AS isFollowingContent, git.nodeid AS isFollowingChannel,
						0 AS isFollowingMember,
						" . implode(",", $sortfields) . "
					FROM " . TABLE_PREFIX . "node AS node
					" . (!empty($cleaned['parentid']) ?
					"INNER JOIN " . TABLE_PREFIX . "closure AS cl ON (cl.child = node.nodeid)" : "") . "
					LEFT JOIN " . TABLE_PREFIX . "node AS latest ON (latest.nodeid = node.lastcontentid)
					LEFT JOIN " . TABLE_PREFIX . "user AS postfor ON (postfor.userid = node.setfor)
					INNER JOIN " . TABLE_PREFIX . "groupintopic AS git ON (git.nodeid = node.parentid
						AND git.userid = " . $cleaned['followerid'] . "  AND node.nodeid = node.starter)
					INNER JOIN " . TABLE_PREFIX . "closure AS blog_check ON (blog_check.child = git.nodeid AND blog_check.parent = $blogChannel)
					" . (implode("\n", $permflags['joins']));
			}

			$sortprefix = count($sql) == 1 ? 'node.' : 'sort_';

			if (empty($sorts))
			{
				$sort = " ORDER BY {$sortprefix}publishdate DESC LIMIT ";
			}
			else
			{
				array_walk($sorts, function(&$value, $key) use ($sortprefix)
				{
					$value = $sortprefix . $value;
				});
				$sort = " ORDER BY " . implode(", ", $sorts) . " LIMIT ";
			}

			$wheresql = array();

			$wheresql[] = "node.inlist > 0 AND node.ContentTypeid NOT IN ($channelType, $PMType)";
			if (!empty($cleaned['parentid']))
			{
				$wheresql[] = "cl.parent = " . $cleaned['parentid'];
			}

			if (!empty($cleaned['contenttypeid']))
			{
				$wheresql[] = "node.contenttypeid IN (" . implode(', ', $cleaned['contenttypeid']) .") \n";
			}

			//block people on the global ignore list.
			$options = vB::getDatastore()->get_value('options');
			if (trim($options['globalignore']) != '')
			{
				$blocked = preg_split('#\s+#s', $options['globalignore'], -1, PREG_SPLIT_NO_EMPTY);
				//the user can always see their own posts, so if they are in the blocked list we remove them
				$bbuserkey = array_search(vB::getCurrentSession()->get('userid') , $blocked);

				if ($bbuserkey !== FALSE AND $bbuserkey !== NULL)
				{
					unset($blocked["$bbuserkey"]);
				}

				//Make sure we didn't just empty the list
				if (!empty($blocked))
				{
					$wheresql[] = "node.userid NOT IN (" . implode(',', $blocked) . ")";
				}
			}

			/** Date filter */
			if (!empty($params[vB_Api_Follow::FOLLOWFILTERTYPE_TIME]))
			{
				$datenow = vB::getRequest()->getTimeNow();
				switch ($params[vB_Api_Follow::FOLLOWFILTERTYPE_TIME])
				{
					case vB_Api_Follow::FOLLOWFILTER_LASTDAY:
						$timeVal = $datenow - (24 * 60 * 60);
						break;
					case vB_Api_Follow::FOLLOWFILTER_LASTWEEK:
						$timeVal = $datenow - (7 * 24 * 60 * 60);
						break;
					case vB_Api_Follow::FOLLOWFILTER_LASTMONTH:
						$timeVal = strtotime(date("Y-m-d H:i:s", $datenow) . " - 1 month");
						break;
					default:
						if (is_numeric($params[vB_Api_Follow::FOLLOWFILTERTYPE_TIME]))
						{
							$timeVal = $params[vB_Api_Follow::FOLLOWFILTERTYPE_TIME];
						}
						else
						{
							$timeVal = 0;
						}
						break;
				}
				$wheresql[] = "node.publishdate >= $timeVal";
			}

			$wheresql = implode(" AND ", $wheresql);

			if (!empty($permflags['where']))
			{
				$wheresql .= $permflags['where'];
			}

			$limit = "";
			if (isset($params[vB_dB_Query::PARAM_LIMIT]) AND intval($params[vB_dB_Query::PARAM_LIMIT]))
			{
				$perpage = intval($params[vB_dB_Query::PARAM_LIMIT]);
			}
			else
			{
				$perpage = 30;
			}

			if (!empty($cleaned['pageseemore']) )
			{
				// needed for seemore button
				$limit .= ((($perpage - 1) * (intval($cleaned['pageseemore']) - 1)) . ',');
			}
			else if (isset($params[vB_dB_Query::PARAM_LIMITPAGE]) AND intval($params[vB_dB_Query::PARAM_LIMITPAGE]) AND (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) > 1))
			{
				$limit .=  ($perpage * (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) - 1)) . ',';
			}

			if ($wheresql)
			{
				foreach ($sql AS $key => $statement)
				{
					$sql[$key] .= " WHERE $wheresql";
				}
			}

			$innersql = "(" . implode(")UNION(", $sql) . ")\r\n";
			$sql = "SELECT followingContent.nodeid, followingContent.contenttypeid, followingContent.lastcontentid,
			followingContent.lastcontenttypeid, followingContent.postfor,
			SUM(CASE WHEN followingContent.isFollowingContent <> 0 THEN  followingContent.isFollowingContent ELSE 0 END) AS isFollowingContent,
			SUM(CASE WHEN followingContent.isFollowingChannel <> 0 THEN  followingContent.isFollowingChannel ELSE 0 END) AS isFollowingChannel,
			SUM(CASE WHEN followingContent.isFollowingMember <> 0 THEN  followingContent.isFollowingMember ELSE 0 END) AS isFollowingMember
			FROM
			(" . $innersql . ") AS followingContent
			GROUP BY followingContent.nodeid\r\n
			ORDER BY " . implode(", ", $outersorts) . "\r\n
			LIMIT " . $limit .$perpage;
			$sql .= "\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** deletes data for the selected user. Called after deleteing the user record
	 *
	 *	@param	mixed		the query parameters
	 * 	@param	object		the database object
	 * 	@param	bool		whether we run the query, or just validate that we can run it.
	 *
	 *	@return	mixed		array of node data
	 *
	 ***/
	public function deleteProtectedUserData($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (intval($params['userid']));
		}
		$config = vB::getConfig();
		//First we should delete from the filedata table. That can be used by attachments or photos
		$sql = "DELETE " . TABLE_PREFIX . "filedata
			FROM " . TABLE_PREFIX . "attach AS a
			INNER JOIN " . TABLE_PREFIX . "node AS n ON n.nodeid = a.nodeid
			INNER JOIN " . TABLE_PREFIX . "filedata ON " . TABLE_PREFIX . "filedata.filedataid = a.filedataid
			WHERE n.userid = " . intval($params['userid']) . " AND n.protected = 1";

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}
		$db->query_write($sql);
		$sql = "DELETE " . TABLE_PREFIX . "filedata FROM " . TABLE_PREFIX . "photo AS p
			INNER JOIN " . TABLE_PREFIX . "node AS n ON n.nodeid = p.nodeid
			INNER JOIN " . TABLE_PREFIX . "filedata ON " . TABLE_PREFIX . "filedata.filedataid = p.filedataid
			WHERE n.userid = " . intval($params['userid']) . " AND n.protected = 1";

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}
		$db->query_write($sql);
		//Now the subsidiary tables.
		foreach (array('text', 'video', 'photo', 'gallery') as $table)
		{
			$sql = "DELETE  " . TABLE_PREFIX . "$table FROM " . TABLE_PREFIX . "$table
			INNER JOIN " . TABLE_PREFIX . "node AS n ON n.nodeid =  " . TABLE_PREFIX . "$table.nodeid
			WHERE n.userid = " . intval($params['userid']) . " AND n.protected = 1";

			if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
			{
				echo "sql: $sql<br />\n";
			}
			$db->query_write($sql);
		}

		//Two private message tables

		//And last is the node table

		$sql = "DELETE  FROM " . TABLE_PREFIX . "node
			WHERE userid = " . intval($params['userid'] . " AND protected = 1");
		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}
		$db->query_write($sql);

		return true;
	}
	/** Gets plain nodes
	 *
	 *	@param	mixed
	 *	@param	mixed	a db pointer
	 *  @param	bool
	 *
	 *	@result	mixed
	 * **/
	public function getNodes($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//I don't need anything;
			return true;
		}

		$sql = "SELECT * FROM " . TABLE_PREFIX . "node";
		if (!empty($params['contenttypeid']))
		{
			$sql .= "\nWHERE contenttypeid = '" . intval($params['contenttypeid']) . "'";
		}
		if (!empty($params['excludecontenttypeids']))
		{
			$sql .= "\nWHERE contenttypeid NOT IN (" . implode(',',$params['excludecontenttypeids']) . ")";
		}

		if (!empty($params[vB_dB_Query::PARAM_LIMIT]) OR !empty($params[vB_dB_Query::PARAM_LIMITSTART]))
		{
			$sql .= "\nLIMIT ";
			if (!empty($params[vB_dB_Query::PARAM_LIMITSTART]))
			{
				$sql .= intval($params[vB_dB_Query::PARAM_LIMITSTART]);
			}
			if (!empty($params[vB_dB_Query::PARAM_LIMIT]) AND !empty($params[vB_dB_Query::PARAM_LIMITSTART]))
			{
				$sql .= ',';
			}
			if (!empty($params[vB_dB_Query::PARAM_LIMIT]))
			{
				$sql .= intval($params[vB_dB_Query::PARAM_LIMIT]);
			}
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}
		$result = new $resultclass($db, $sql);
		return $result;

	}

	/** Gets adminhelp items with specific existing actions and options.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getHelpLength($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return ( !empty($params['pagename']) AND !empty($params['action']) ) ? true : false;
		}
		else
		{
			$sql = "
				SELECT *, LENGTH(action) AS length
				FROM " . TABLE_PREFIX  . "adminhelp
				WHERE script = '" . $db->escape_string($params['pagename']) . "'
					AND (action = '' OR FIND_IN_SET('" . $db->escape_string($params['action']) . "', action))";

			if (!empty($params['option']))
			{
				$sql .= " AND optionname = '" . $db->escape_string($params['option']) . "'";
			}
			$sql .= " AND displayorder <> 0 ORDER BY displayorder";

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

	/** Update active and display order for bookmark sites in quick update.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function quickUpdateBookmarkSites($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return ( !isset($params['active']) AND !isset($params['displayorder']) ) ? true : false;
		}
		else
		{
			$changes = false;
			$update_ids = '0';
			$update_active = '';
			$update_displayorder = '';

			$bookmarksites_result = $db->query_read("SELECT bookmarksiteid, displayorder, active FROM " . TABLE_PREFIX . "bookmarksite");
			while ($bookmarksite = $db->fetch_array($bookmarksites_result))
			{
				if (intval($bookmarksite['active']) != $params['active']["$bookmarksite[bookmarksiteid]"] OR $bookmarksite['displayorder'] != $params['displayorder']["$bookmarksite[bookmarksiteid]"])
				{
					$update_ids .= ",$bookmarksite[bookmarksiteid]";
					$update_active .= " WHEN $bookmarksite[bookmarksiteid] THEN " . intval($params['active']["$bookmarksite[bookmarksiteid]"]);
					$update_displayorder .= " WHEN $bookmarksite[bookmarksiteid] THEN " . $params['displayorder']["$bookmarksite[bookmarksiteid]"];
				}
			}
			$db->free_result($bookmarksites_result);

			if (strlen($update_ids) > 1)
			{
				$db->query_write("UPDATE " . TABLE_PREFIX . "bookmarksite SET
					active = CASE bookmarksiteid
					$update_active ELSE active END,
					displayorder = CASE bookmarksiteid
					$update_displayorder ELSE displayorder END
					WHERE bookmarksiteid IN($update_ids)
				");

				// tell the datastore to update
				$changes = true;
			}
			return $changes;
		}
	}

	/** Update swapping for bookmark sites in quick update.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function quickUpdateBookmarkSitesSwap($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return ( !empty($params['swap_direction']) AND !empty($params['bookmarksite_orig']) ) ? true : false;
		}
		else
		{
			switch ($params['swap_direction'])
			{
				case 'lower':
					$comp = '<';
					$sort = 'DESC';
					break;
				case 'higher':
					$comp = '>';
					$sort = 'ASC';
					break;
				default:
					$comp = false;
					$sort = false;
			}
			if ($comp AND $sort AND $bookmarksite_swap = $db->query_first("SELECT bookmarksiteid, displayorder FROM " . TABLE_PREFIX . "bookmarksite WHERE displayorder ". $comp . " " . $params['bookmarksite_orig']['displayorder'] . " ORDER BY displayorder $sort, title ASC LIMIT 1"))
			{
				$db->query_write("
					UPDATE " . TABLE_PREFIX . "bookmarksite
					SET displayorder = CASE bookmarksiteid
						WHEN " . $params['bookmarksite_orig']['bookmarksiteid'] . " THEN $bookmarksite_swap[displayorder]
						WHEN $bookmarksite_swap[bookmarksiteid] THEN " . $params['bookmarksite_orig']['displayorder'] . "
						ELSE displayorder END
					WHERE bookmarksiteid IN(" . $params['bookmarksite_orig']['bookmarksiteid'] . " , $bookmarksite_swap[bookmarksiteid])
				");

				// tell the datastore to update
				$changes = true;
			}
			return $changes;
		}
	}

	/** fetchImagesSortedLimited
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function fetchImagesSortedLimited($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return ( !empty($params['table']) ) ? true : false;
		}
		else
		{
			$sql = "SELECT";
			if ($params['categoryinfo'])
			{
				$sql .= " * FROM " . TABLE_PREFIX . $params['table'] .
					" WHERE imagecategoryid = " . $params['categoryinfo']['imagecategoryid'];
				$sql .= " ORDER BY";
				if ($params['table'] == 'avatar')
				{
					$sql .= " minimumposts,";
				}
				$sql .= " displayorder";
				$sql .= " LIMIT " . $params[vB_dB_Query::PARAM_LIMITSTART] . ", " . $params[vB_dB_Query::PARAM_LIMIT];
			}
			else
			{
				$sql .= " " . $params['table'] . ".*, imagecategory.title AS category";
				$sql .= " FROM " . TABLE_PREFIX .  $params['table'] . " AS " .  $params['table'];
				$sql .= " LEFT JOIN " . TABLE_PREFIX . "imagecategory AS imagecategory USING(imagecategoryid)";
				if ($params['imagecategoryid'])
				{
					$sql .= " WHERE " . $params['table'] . ".imagecategoryid = " . $params['imagecategoryid'];
				}
				$sql .=  " ORDER BY";
				if ($params['table'] == 'avatar')
				{
				    $sql .= ' minimumposts,';
				}
				$sql .= " imagecategory.displayorder, imagecategory.title, " . $params['table'] . ".displayorder";
				$sql .= " LIMIT " . $params[vB_dB_Query::PARAM_LIMITSTART] . ", " . $params[vB_dB_Query::PARAM_LIMIT];
			}

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


	/** fetchCategoryImages
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function fetchCategoryImages($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return ( !empty($params['table']) AND !empty($params['itemid']) AND !empty($params['catid']) ) ? true : false;
		}
		else
		{
			$sql = "SELECT imagecategory.*, COUNT(" . $params['table'] .  "." . $params['itemid'] . ") AS items
			FROM " . TABLE_PREFIX . "imagecategory AS imagecategory
			LEFT JOIN " . TABLE_PREFIX . $params['table'] . " AS " . $params['table'] . " USING(imagecategoryid)
			WHERE imagetype = " . $params['catid'] . "
			GROUP BY imagecategoryid
			ORDER BY displayorder";

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


	/** fetchCategoryImages
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function countImagesByImgCategory($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return ( !empty($params['table']) ) ? true : false;
		}
		else
		{
			$sql = "SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . $params['table'];
			if (isset($params['imagecategoryid']))
			{
				$sql .= " WHERE imagecategoryid=" . $params['imagecategoryid'];
			}

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


	/** Optimize tables for Avatar Script
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function optimizePictureTables($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$db->hide_errors();
			$db->query_write("OPTIMIZE TABLE " . TABLE_PREFIX . "customavatar");
			$db->query_write("OPTIMIZE TABLE " . TABLE_PREFIX . "customprofilepic");
			$db->query_write("OPTIMIZE TABLE " . TABLE_PREFIX . "sigpic");
			$db->show_errors();
			return true;
		}
	}

	/** Get Count of tags depending on sorting optional parameters.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getTagCountSort($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$sql = "SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "tag";
			if ($params['sort'] == 'dateline')
			{
				$sql .= ' WHERE canonicaltagid = 0';
			}
			else if ($params['sort'] == 'alphaall')
			{
				$sql .= '';
			}
			else
			{
				$sql .= ' WHERE canonicaltagid = 0';
			}

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


	public function replaceIntoTagContent($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return is_array($params['values']);
		}
		else
		{
			$recordLines = array();
			foreach ($params['values'] AS $record)
			{
				//all of the values should be integers, so we can just use array_map.
				$safeRecord = array_map('intval', $record);
				$recordLines[] = "($safeRecord[tagid], $safeRecord[nodeid], $safeRecord[userid], $safeRecord[dateline])";
			}

			$sql =
				'REPLACE INTO ' . TABLE_PREFIX . 'tagnode (tagid, nodeid, userid, dateline) ' .
				'VALUES ' . implode(', ', $recordLines) .
				"\n/** getPopularTags" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . '**/';

			if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
			{
				echo "sql: $sql<br />\n";
			}

			$db->query_write($sql);
			return true;
		}
	}

	/**
	 * Get tags depending on sorting optional parameters.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getTagsSort($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$sql = "SELECT * FROM " . TABLE_PREFIX . "tag";
			if ($params['sort'] == 'dateline')
			{
				$where = ' WHERE canonicaltagid = 0';
				$order = ' dateline DESC';
			}
			else if ($params['sort'] == 'alphaall')
			{
				$where = '';
				$order = ' tagtext ASC';
			}
			else
			{
				$where = ' WHERE canonicaltagid = 0';
				$order = ' tagtext ASC';
			}
			$sql .= $where;
			$sql .= " ORDER BY " . $order;
			$sql .= " LIMIT " . $params['start'] . ", " . $params[vB_dB_Query::PARAM_LIMIT];

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

	/** Fetch settings by product
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function fetchSettingsByProduct($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$sql = "SELECT * FROM " . TABLE_PREFIX . "setting
				WHERE (product = '" . $db->escape_string($params['product']) . "'";
			if ($params['product'] == 'vbulletin')
			{
				$sql .= " OR product = ''";
			}
			$sql .= ")";
			if ($params['blacklist'])
			{
				$sql .= " AND blacklist = 0";
			}
			$sql .= " ORDER BY displayorder, varname";

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

	/** Fetch settings by group
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function fetchSettingsByGroup($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$sql = "SELECT setting.*, settinggroup.grouptitle
				FROM " . TABLE_PREFIX . "settinggroup AS settinggroup
				LEFT JOIN " . TABLE_PREFIX . "setting AS setting USING(grouptitle)";
			if (!$params['debug'])
			{
				$sql .= "
				    WHERE settinggroup.displayorder <> 0
				";
			}
			$sql .= "ORDER BY settinggroup.displayorder, setting.displayorder";

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

	/** Delete settings by product
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function deleteSettingGroupByProduct($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$sql = "DELETE FROM " . TABLE_PREFIX . "settinggroup WHERE volatile = 1 AND (product = '" .  $db->escape_string($params['product']) . "'";
			if ($params['product'] == 'vbulletin')
			{
				$sql .= " OR product = ''";
			}
			$sql .= ")";

			$config = vB::getConfig();

			if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
			{
				echo "sql: $sql<br />\n";
			}
			return $db->query_write($sql);
		}
	}

	/** Delete settings by product
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function deleteSettingByProduct($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$sql = "DELETE FROM " . TABLE_PREFIX . "setting WHERE volatile = 1 AND (product = '" .  $db->escape_string($params['product']) . "'";
			if ($params['product'] == 'vbulletin')
			{
				$sql .= " OR product = ''";
			}
			$sql .= ")";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$config = vB::getConfig();

			if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
			{
				echo "sql: $sql<br />\n";
			}

			return $db->query_write($sql);
		}
	}

	/** Insert Settings Group
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function insertSettingGroup($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$sql = "INSERT IGNORE INTO " . TABLE_PREFIX . "settinggroup
				(grouptitle, displayorder, volatile, product)
				VALUES
				('" .
				$db->escape_string($params['grouptitle']) . "', " .
				intval($params['displayorder']) . ", " .
				intval($params['volatile']) . ", '" .
				$db->escape_string($params['product']) . "')";

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$config = vB::getConfig();

			if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
			{
				echo "sql: $sql<br />\n";
			}

			return $db->query_write($sql);
		}
	}

	/** Insert Setting
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function insertSetting($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$sql = "INSERT INTO " . TABLE_PREFIX . "setting
				(varname, grouptitle, value, defaultvalue, datatype, optioncode, displayorder, advanced, volatile";
			if ($params['upgrade_compat'])
			{
				$sql .= ', validationcode, blacklist, product';
			}
			$sql .= ") VALUES (";
			$sql .= "'" . $db->escape_string($params['varname']) . "', ";
			$sql .= "'" . $db->escape_string($params['grouptitle']) . "', ";
			$sql .= "'" . $db->escape_string($params['value']) . "', ";
			$sql .= "'" . $db->escape_string($params['defaultvalue']) . "', ";
			$sql .= "'" . $db->escape_string($params['datatype']) . "', ";
			$sql .= "'" . $db->escape_string($params['optioncode']) . "', ";
			$sql .= intval($params['displayorder']) . ", ";
			$sql .= intval($params['advanced']) . ", ";
			$sql .= intval($params['volatile']);
			if ($params['upgrade_compat'])
			{
				$sql .= ", '" . $db->escape_string($params['validationcode']) . "', ";
				$sql .= intval($params['blacklist']) . ", ";
				$sql .= "'" . $db->escape_string($params['product']) . "'";
			}
			$sql .= ")";

			$config = vB::getConfig();

			if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
			{
				echo "sql: $sql<br />\n";
			}

			return $db->query_write($sql);
		}
	}

	function searchAttach($params, vB_Database $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		$query = array(
				"a.nodeid <> 0"
		);

		if ($params['search']['filename'])
		{
			$query[] = "a.filename LIKE '%" . $db->escape_string_like($params['search']['filename']) . "%' ";
		}

		if ($params['search']['attachedbyuser'])
		{
			$query[] = "node.userid=" . $params['search']['attachedbyuser'] . " ";
		}

		if ($params['search']['datelinebefore'] AND $params['search']['datelineafter'])
		{
			$query[] = "(fd.dateline BETWEEN UNIX_TIMESTAMP('" . $db->escape_string($params['search']['datelineafter']) . "') AND UNIX_TIMESTAMP('" . $db->escape_string($params['search']['datelinebefore']) . "')) ";
		}
		else if ($params['search']['datelinebefore'])
		{
			$query[] = "fd.dateline < UNIX_TIMESTAMP('" . $db->escape_string($params['search']['datelinebefore']) . "') ";
		}
		else if ($params['search']['datelineafter'])
		{
			$query[] = "a.dateline > UNIX_TIMESTAMP('" . $db->escape_string($params['search']['datelineafter']) . "') ";
		}

		if ($params['search']['downloadsmore'] AND $params['search']['downloadsless'])
		{
			$query[] = "(a.counter BETWEEN " . $params['search']['downloadsmore'] ." AND " . $params['search']['downloadsless'] . ") ";
		}
		else if ($params['search']['downloadsless'])
		{
			$query[] = "a.counter < " . $params['search']['downloadsless'] . " ";
		}
		else if ($params['search']['downloadsmore'])
		{
			$query[] = "a.counter > " . $params['search']['downloadsmore']. " ";
		}

		if ($params['search']['sizemore'] AND $params['search']['sizeless'])
		{
			$query[] = "(fd.filesize BETWEEN " . $params['search']['sizemore'] . " AND " . $params['search']['sizeless'] . ") ";
		}
		else if ($params['search']['sizeless'])
		{
			$query[] = "fd.filesize < " . $params['search']['sizeless'] . " ";
		}
		else if ($params['search']['sizemore'])
		{
			$query[] = "fd.filesize > " . $params['search']['sizemore'] . " ";
		}

// 		if ($params['search']['visible'] != -1)
// 		{
// 			$query[] = "a.state = '" . ($params['search']['visible'] ? 'visible' : 'moderation') . "' ";
// 		}

		$tables = "FROM " . TABLE_PREFIX . "node AS node
				INNER JOIN " . TABLE_PREFIX . "attach AS a ON (node.nodeid = a.nodeid)
				INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (a.filedataid = fd.filedataid)
		";
		$where = "WHERE " . implode(" AND ", $query);
		$limit = "";
		$order = "";
		if (!empty($params['countonly']))
		{
			$fields = "COUNT(*) AS count, SUM(fd.filesize) AS sum";
		}
		else
		{
			$fields = "node.*, fd.filesize, a.filedataid, a.filename, fd.dateline";
			$limit = "LIMIT " . (($params['pagenum'] - 1) * $params['search']['results']) .", " . $params['search']['results'];
			$order = 'ORDER BY ' . $params['search']['orderby'] . ' ' . $params['search']['ordering'];
		}
		$sql = "
				SELECT $fields
				$tables
				$where
				$order
				$limit
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

	function replacePerms($params, vB_Database $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['fields']);
		}
		$sql = "REPLACE INTO " . TABLE_PREFIX . "attachmentpermission
						(extension, usergroupid, size, width, height, attachmentpermissions)
					VALUES
						" . implode(',', $params['fields']);

		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
				echo "sql: $sql<br />\n";
		}
		$db->query_write($sql);
	}

	/** Calculate statistics for Nodes
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function calculateStats($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			//Get the day for which we are going to run
			$firstDate = $db->query_first("SELECT min(dateline) as firstdate FROM " . TABLE_PREFIX . "nodevisits");
			$firstDate = empty($firstDate['firstdate']) ? 0 : $firstDate['firstdate'];

			$timenow = vB::getRequest()->getTimeNow();

			//This should never happen, but just in case somebody turns off this cron job for months or years
			$today = $timenow - ($timenow % 86400);
			$cutoff = $timenow - (60 * 86400);
			if ($firstDate < $cutoff )
			{
				$runagain = true;
				//We don't need detail over 60 days. But we do
				// need to update the nodestatreplies table.
				$sql = "INSERT INTO " . TABLE_PREFIX . "nodestatreplies (nodeid, replies)
					SELECT nodeid, qry.totalcount FROM
					 (SELECT nv.parentid AS nodeid, SUM(nv.totalcount) AS totalcount
					  FROM (SELECT node.parentid, max(visits.totalcount) AS totalcount
					    FROM " . TABLE_PREFIX . "nodevisits AS visits
					    INNER JOIN " . TABLE_PREFIX . "node AS node ON node.nodeid = visits.nodeid WHERE dateline < $cutoff  GROUP BY node.nodeid
					  ) AS nv
					 GROUP BY parentid) AS qry
					ON DUPLICATE KEY UPDATE replies = replies + qry.totalcount;
					 /**vBForum:calculateStats called from cron **/";
				$db->query_write($sql);

				//delete everything over 60 days old .
				$db->query_write("DELETE FROM " . TABLE_PREFIX . "nodevisits where dateline < $cutoff; /**vBForum:calculateStats called from cron **/");
				//And reset the date.
				$firstDate = $db->query_first("SELECT min(dateline) as firstdate FROM " . TABLE_PREFIX . "nodevisits /**vBForum:calculateStats called from cron **/");
				$firstDate = empty($firstDate['firstdate']) ? 0 : $firstDate['firstdate'];
			}
			else if ($firstDate > $today)
			{
				//not time yet
				return true;
			}
			else if ($firstDate < $today - 86400)
			{
				$runagain = true;
			}
			//We delete anything from nodestats over sixty days old
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "nodestats where dateline < $cutoff;/**vBForum:calculateStats called from cron **/");

			//create a temp table.
			$db->query_write("CREATE TABLE tmp_nodestats AS SELECT node.parentid AS nodeid, count(nv.nodeid) AS tmpvisitors,
				nv.totalcount - IFNULL(existing.replies, 0) AS tmpreplies FROM " . TABLE_PREFIX . "nodevisits AS nv
				INNER JOIN " . TABLE_PREFIX . "node AS node ON node.nodeid = nv.nodeid
				LEFT JOIN " . TABLE_PREFIX . "nodestatreplies AS existing ON existing.nodeid = node.parentid
				WHERE nv.dateline >= $firstDate AND nv.dateline < ($firstDate + 86400)
				GROUP BY node.parentid, existing.replies; /**vBForum:calculateStats called from cron **/");

			//insert/update the new records. And some additional tweaking in case it gets run twice for the same day.
			$sql = "INSERT INTO  " . TABLE_PREFIX . "nodestats(nodeid, visitors, replies, dateline)
				SELECT nodeid, tmpvisitors,  tmpreplies, " . $firstDate . " AS dateline
				FROM tmp_nodestats AS tmp
				ON DUPLICATE KEY UPDATE visitors = visitors + tmpvisitors, replies = replies + tmpreplies; /**vBForum:calculateStats called from cron **/";
			$db->query_write($sql);

			$sql = "INSERT INTO  " . TABLE_PREFIX . "nodestatreplies (nodeid, replies) SELECT nodeid, tmpreplies FROM tmp_nodestats
				ON DUPLICATE KEY UPDATE replies = replies + tmpreplies; /**vBForum:calculateStats called from cron **/";
			$db->query_write($sql);
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "nodevisits where dateline <= ($firstDate + 86400); /**vBForum:calculateStats called from cron **/");
			$db->query_write("DROP TABLE tmp_nodestats; /**vBForum:calculateStats called from cron **/");

			if ($runagain)
			{
				return false;
				}
			return $db->errors ? false : true;
		}
	}

				/**
	 * This fetch all the pending posts (posts awaiting for approval)
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
				 */
	public function fetchPendingPosts($params, $db, $check_only)
	{
		if ($check_only)
		{
			return (!empty($params['canModerate']) AND (is_array($params['canModerate']) OR is_string($params['canModerate']))) ? true : false;
		}
		else
		{
			$sql = "SELECT node.nodeid, node.contenttypeid
			FROM " . TABLE_PREFIX . "node AS starter INNER JOIN " .
					TABLE_PREFIX . "node AS node ON (node.starter = starter.nodeid)
					WHERE starter.parentid IN ";

			if (is_array($params['canModerate']))
			{
				$sql .= "(" . implode(',', $params['canModerate']) . ")\n";
			}
			else
			{
				$sql .= "(" . $params['canModerate'] . ")\n";
			}


			$pmId = vB_Types::instance()->getContentTypeID('vBForum_PrivateMessage');
			$channelId = vB_Types::instance()->getContentTypeID('vBForum_Channel');
			$excludeTypes = array();
			if (intval($pmId))
			{
				$excludeTypes[] = $pmId;
			}
			if (intval($channelId))
			{
				$excludeTypes[] = $channelId;
			}

			if (!empty($excludeTypes))
			{
				$sql .= "AND node.contenttypeid NOT IN (" . implode(',', $excludeTypes) . ")\n";
			}

			// fetch not approved
			$sql .= "AND node.showapproved = 0 AND node.showpublished <> 0 \n";
			$sql .= "ORDER BY node.publishdate DESC LIMIT\n";

			if (isset($params[vB_dB_Query::PARAM_LIMIT]) AND intval($params[vB_dB_Query::PARAM_LIMIT]))
			{
				$perpage = intval($params[vB_dB_Query::PARAM_LIMIT]);
			}
			else
			{
				$perpage = 20;
			}

			if (isset($params[vB_dB_Query::PARAM_LIMITPAGE]) AND intval($params[vB_dB_Query::PARAM_LIMITPAGE]) AND (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) > 1) AND $perpage)
			{
				$sql .=  " " . ($perpage * (intval($params[vB_dB_Query::PARAM_LIMITPAGE]) - 1)) . ",\n";
			}

			$sql .= " " . $perpage;

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

	/**
	 * Same as fetchPendingPosts but will only return the totalcount.
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function fetchPendingPostsCount($params, $db, $check_only)
	{
		if ($check_only)
		{
			return (!empty($params['canModerate']) AND (is_array($params['canModerate']) OR is_string($params['canModerate']))) ? true : false;
		}
		else
		{
			$sql = "SELECT COUNT(DISTINCT node.nodeid, node.contenttypeid) AS ppCount
			FROM " . TABLE_PREFIX . "node AS starter INNER JOIN " .
			TABLE_PREFIX . "node AS node ON (node.starter = starter.nodeid)
			WHERE starter.parentid IN ";

			if (is_array($params['canModerate']))
			{
				$sql .= "(" . implode(',', $params['canModerate']) . ")\n";
			}
			else
			{
				$sql .= "(" . $params['canModerate'] . ")\n";
			}


			$pmId = vB_Types::instance()->getContentTypeID('vBForum_PrivateMessage');
			$channelId = vB_Types::instance()->getContentTypeID('vBForum_Channel');
			$excludeTypes = array();
			if (intval($pmId))
			{
				$excludeTypes[] = $pmId;
			}
			if (intval($channelId))
			{
				$excludeTypes[] = $channelId;
			}

			if (!empty($excludeTypes))
			{
				$sql .= "AND node.contenttypeid NOT IN (" . implode(',', $excludeTypes) . ")\n";
			}

			// fetch not approved
			$sql .= "AND node.showapproved = 0 AND node.showpublished <> 0 \n";

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

	function deleteProductTemplates($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['products']));
		}

		$products = array();
		foreach ($params['products'] as $product)
		{
			$products[] = $db->escape_string($product);
		}

		$sql = "DELETE FROM " . TABLE_PREFIX . "template
				WHERE styleid = -10 AND (product = '". implode("' OR product = '", $products) . "')" .
				"\n/**" . __FUNCTION__ .
		(defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql <br />\n";
		}

		$result = $db->query_write($sql);

		return $result;
	}

	function updateProductTemplates($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['products']));
		}
		$products = array();
		foreach ($params['products'] as $product)
		{
			$products[] = $db->escape_string($product);
		}
		$sql = "UPDATE " . TABLE_PREFIX . "template
				SET styleid = -10
				WHERE styleid = -1 AND (product = '". implode("' OR product = '", $products) . "')" .
				"\n/**" . __FUNCTION__ .
		(defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql <br />\n";
		}

		$result = $db->query_write($sql);

		return $result;
	}

	function fetchParentTemplates($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['parentlist']));
		}
		$sql = "SELECT DISTINCT title
		FROM " . TABLE_PREFIX . "template
		WHERE styleid IN (" . implode(',',$params['parentlist']) . ") AND title LIKE '%.css'" .
		"\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql <br />\n";
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';

		$result = new $resultclass($db, $sql);
		return $result;
	}

	/** Get Stats ordered
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function fetchStats($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$sql = "SELECT SUM(" . $params['type'] . ") AS total,
				DATE_FORMAT(from_unixtime(dateline), '" . $params['sqlformat'] . "') AS formatted_date,
				AVG(dateline) AS dateline
				FROM " . TABLE_PREFIX . "stats
				WHERE dateline >= " . $params['start_time'] . " AND dateline <= " . $params['end_time'] . "
				GROUP BY formatted_date
				" . (empty($params['nullvalue']) ? " HAVING total > 0 " : "");
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
			$sql .= " ORDER BY " . $orderby;


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


	/** Fetch Admin Log info
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function fetchAdminLogCount($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$sql = "SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "adminlog AS adminlog ";
			if ($params['userid'] OR $params['script'] OR $params['startdate'] OR $params['enddate'])
			{
				$sql .= 'WHERE 1=1 ';
				if ($params['userid'])
				{
					$sql .= " AND adminlog.userid = " . intval($params['userid']);
				}
				if ($params['script'])
				{
					$sql .= " AND adminlog.script = '" . $db->escape_string($params['script']) . "' ";
				}
				if ($params['startdate'])
				{
					$sql .= " AND adminlog.dateline >= " . $params['startdate'];
				}
				if ($params['enddate'])
				{
					$sql .= " AND adminlog.dateline <= " . $params['enddate'];
				}
			}


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

	/** Fetch Admin Log info
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function fetchAdminLog($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$sql = "SELECT adminlog.*, user.username FROM " . TABLE_PREFIX . "adminlog AS adminlog
				LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid) ";
			if ($params['userid'] OR $params['script'] OR $params['startdate'] OR $params['enddate'])
			{
				$sql .= 'WHERE 1=1 ';
				if ($params['userid'])
				{
					$sql .= " AND adminlog.userid = " . intval($params['userid']);
				}
				if ($params['script'])
				{
					$sql .= " AND adminlog.script = '" . $db->escape_string($params['script']) . "' ";
				}
				if ($params['startdate'])
				{
					$sql .= " AND adminlog.dateline >= " . $params['startdate'];
				}
				if ($params['enddate'])
				{
					$sql .= " AND adminlog.dateline <= " . $params['enddate'];
				}
			}
			switch ($params['orderby'])
			{
				case 'user':
					$sql .= ' ORDER BY username ASC,adminlogid DESC';
					break;
				case 'script':
					$sql = ' ORDER BY script ASC,adminlogid DESC';
					break;
				// Date
				default:
					$sql .= ' ORDER BY adminlogid DESC';
					break;
			}
			$sql .= " LIMIT " . intval($params[vB_dB_Query::PARAM_LIMITSTART]) . ", " .  intval($params[vB_dB_Query::PARAM_LIMIT]);


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

	/** Fetch Admin Log Count by Cut Date
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@return	mixed
	 */
	public function countAdminLogByDateCut($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return ( !empty($params['datecut']) ) ? true : false;
		}
		else
		{
			$sql = "SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "adminlog AS adminlog WHERE dateline < " . $params['datecut'];
			if ($params['userid'])
			{
				$sql .= " AND userid = " . intval($params['userid']);
			}
			if ($params['script'] != '')
			{
				$sql .= " AND script = '" . $db->escape_string->escape_string($params['script']) . "'";
			}
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

	/** Delete Admin Log by Cut Date
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function deleteAdminLogByDateCut($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return ( !empty($params['datecut']) ) ? true : false;
		}
		else
		{
			$sql = "DELETE FROM " . TABLE_PREFIX . "adminlog WHERE dateline < " . $params['datecut'];
			if ($params['userid'])
			{
				$sql .= " AND userid = " . intval($params['userid']);
			}
			if ($params['script'] != '')
			{
				$sql .= " AND script = '" . $db->escape_string->escape_string($params['script']) . "'";
			}
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

	/** Get Max Posts from a thread
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getMaxPosts($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			//Original Query
			//$maxposts = $vbulletin->db->query_first("SELECT userid, username, posts FROM " . TABLE_PREFIX . "user ORDER BY posts DESC");

			//New Query
			$sql = "SELECT userid, username, posts FROM " . TABLE_PREFIX . "user ORDER BY posts DESC LIMIT 1";

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

	/** Get Largest Thread
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getMaxThread($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			//Original Query
			//$maxthread = $vbulletin->db->query_first("SELECT * FROM " . TABLE_PREFIX . "thread ORDER BY replycount DESC");

			//New Query
			$sql = "
				SELECT * FROM " . TABLE_PREFIX . "node
				WHERE protected = 0 AND inlist > 0
				AND contenttypeid != " . vB_Types::instance()->getContenttypeId('vBForum_Channel') . "
				ORDER BY totalcount DESC LIMIT 1
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
	}

	/** Get Most Popular Thread
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getMostPopularThread($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			//Original Query
			//$mostpopular = $vbulletin->db->query_first("SELECT * FROM " . TABLE_PREFIX . "thread ORDER BY views DESC");

			//New Query
			/**TODO
			 * There is no actual way to retrive this
			 * SELECT * FROM " . TABLE_PREFIX . "node
			 * WHERE protected = 0 AND inlist>  0
			 * AND contenttypeid != " .
			 * vB_Types::instance()->getContenttypeId('vBForum_Channel') . "
			 * ORDER BY views DESC LIMIT 1
			 */
			$sql = "SELECT * FROM " . TABLE_PREFIX . "node
				WHERE protected = 0 AND inlist > 0
				AND contenttypeid != " . vB_Types::instance()->getContenttypeId('vBForum_Channel') . "
				ORDER BY views DESC LIMIT 1
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
	}

	/** Get Most Popular Forum
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getMostPopularForum($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			//Original Query
			//$popularforum = $vbulletin->db->query_first("SELECT * FROM " . TABLE_PREFIX . "forum ORDER BY replycount DESC");
			//(this is for channels)

			//New Query
			$sql = "
				SELECT * FROM " . TABLE_PREFIX . "node
				WHERE contenttypeid = " . vB_Types::instance()->getContenttypeId('vBForum_Channel') . "
				ORDER BY totalcount DESC LIMIT 1
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
	}

	/** Fetch styles that are forced in channels
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function fetchForcedStyles($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$forumOptions = vB::getDatastore()->getValue('bf_misc_forumoptions');

			$sql = 'SELECT styleid
				FROM ' . TABLE_PREFIX . 'channel
				WHERE styleid > 0';

			if (isset($params['styles']) AND is_array($params['styles']))
			{
				$sql .= ' AND styleid IN (' . implode(',', array_map('intval', $params['styles'])) .')';
			}

			$sql .= ' AND (options & ' . $forumOptions['styleoverride'] . ')';

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

	/** This is used for cache_moderators().
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getCacheModerators($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return ((!$params['userid']) OR ($params['userid'] AND is_numeric($params['userid']))) ? true : false;
		}
		else
		{
			$sql = "SELECT moderator.*, user.username,
				IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid
				FROM " . TABLE_PREFIX . "moderator AS moderator
				INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
				" . ($params['userid'] ? "WHERE moderator.userid = " . $params['userid'] : "") . "\n"
				. "/** getCacheModerators" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** This is used for get_stylevars_for_export().
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getStylevarsForExport($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (is_array($params['product']) AND is_array($params['stylelist'])) ? true : false;
		}
		else
		{
			$sql = "SELECT stylevar.*,
				INSTR('," . implode(',', $params['stylelist']) . ",', CONCAT(',', stylevar.styleid, ',') ) AS ordercontrol
				FROM " . TABLE_PREFIX . "stylevar AS stylevar
				INNER JOIN " . TABLE_PREFIX . "stylevardfn AS stylevardfn ON (stylevardfn.stylevarid = stylevar.stylevarid AND stylevardfn.product IN ('" . implode("','", $params['product']) . "'))
				WHERE stylevar.styleid IN (" . implode(',', $params['stylelist']) . ")
				ORDER BY ordercontrol DESC\n
				/** getStylevarsForExport" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** This is used for get_stylevars_for_export().
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getStylevarsDfnForExport($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (is_array($params['product']) AND is_array($params['stylelist'])) ? true : false;
		}
		else
		{
			$sql = "SELECT *,
				INSTR('," . implode(',', $params['stylelist']) . ",', CONCAT(',', styleid, ',') ) AS ordercontrol
				FROM " . TABLE_PREFIX . "stylevardfn
				WHERE styleid IN (" . implode(',', $params['stylelist']) . ")
				AND product IN ('" . implode("','", $params['product']) . "')
				ORDER BY stylevargroup, stylevarid, ordercontrol\n
				/** getStylevarsDfnForExport" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** This is used for build_reputationids().
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function buildReputationIds($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (is_array($params['ourreputation']) AND !empty($params['ourreputation'])) ? true : false;
		}
		else
		{
			$sqlIf = $this->fetchEventRecurrenceSql($params['ourreputation']);
			$sql = "UPDATE " . TABLE_PREFIX . "user
				SET reputationlevelid = $sqlIf
				/** buildReputationIds" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	// this is recursive for building  user reputation ids
	protected function fetchEventRecurrenceSql($reputation)
	{
		//@TODO might want to remove the static var usage and implement something more fancy...
		static $count = 0;
		$count++;

		if ($count == sizeof($reputation))
		{ // last item
			// if we make it to the end than either the reputation is greather than our greatest value or it is less than our least value
			return 'IF (reputation >= ' . $reputation[$count]['value'] . ', ' . $reputation[$count]['index'] . ', ' . $reputation[1]['index'] . ')';
		}
		else
		{
			return 'IF (reputation >= ' . $reputation[$count]['value'] . ' AND reputation < ' . $reputation[($count + 1)]['value'] . ', ' . $reputation[$count]['index']. ',' . $this->fetchEventRecurrenceSql($reputation) . ')';
		}
	}

	/** This is used in language manager update block.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function updatePhrasesFromLanguage($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			//@TODO validate each phrase record to be as expected
			return (is_array($params['phraserecords']) AND !empty($params['phraserecords'])) ? true : false;
		}
		else
		{
			$sql = "
				REPLACE INTO " . TABLE_PREFIX . "phrase
					(languageid, fieldname, varname, text, product, username, dateline, version)
				VALUES\n
				";

			$records = array();
			foreach ($params['phraserecords'] AS $phrase)
			{
				$records[] = "(
					$phrase[languageid],
					'" . $db->escape_string($phrase['fieldname']) . "',
					'" . $db->escape_string($phrase['varname']) . "',
					'" . $db->escape_string($phrase['newphrase']) . "',
					'" . $db->escape_string($phrase['product']) . "',
					'" . $db->escape_string($phrase['username']) . "',
					" . intval($phrase['dateline']) . ",
					'" . $db->escape_string($phrase['version']) . "'
				)";
			}

			$sql .= implode(",\n\t\t\t\t", $records) . "\n
			/** updatePhrasesFromLanguage" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** This is used to get special and common templates.
	 * 	Used in admincp css.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getSpecialTemplates($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return ( (isset($params['styleid']) AND is_numeric($params['styleid']))
						OR
					(!isset($params['styleid']) AND !empty($params['templateids'])) )
				? true : false;
		}
		else
		{
			if (isset($params['styleid']))
			{
				$where = 'styleid = ' . $params['styleid'];
			}
			else
			{
				$where = 'templateid IN(' . implode(', ', $params['templateids']) . ')';
			}

			$sql = "SELECT templateid, title, template, template_un, styleid, templatetype
			FROM " . TABLE_PREFIX . "template
			WHERE $where
				AND (templatetype <> 'template' OR title IN('" .
				implode("', '", vB_Api::instanceInternal('template')->fetchCommonTemplates()) .
				"', '" .
				implode("', '", vB_Api::instanceInternal('template')->fetchSpecialTemplates()) .
			"'))
			/** getSpecialTemplates" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** Gets mod information including mod's channel as well.
	 *
	 * 	Used in admincp moderator.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getModeratorChannelInfo($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			// field checks
			if (isset($params['sortby']))
			{
				$params['sortby'] = @array_pop($params['sortby']);
				if (isset($params['sortby']['field']) AND !empty($params['sortby']['field']))
				{
					if (!$this->checkSortingFields($params['sortby']['field']))
					{
						return false;
					}
				}
			}

			return true;
		}
		else
		{
			if (!empty($params['sortby']))
			{
				$sortorder = @array_pop($params['sortby']);
			}

			$orderBy = $this->getSortingFields($sortorder, $db);

			$sql = "
				SELECT moderator.moderatorid, user.userid, user.username, user.lastactivity, node.nodeid, node.htmltitle,
				moderator.permissions, moderator.permissions2, node.routeid
				FROM " . TABLE_PREFIX . "moderator AS moderator
				INNER JOIN " . TABLE_PREFIX . "node AS node ON (moderator.nodeid = node.nodeid)
				INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderator.userid)
				" . ((isset($params['nodeid']) AND intval($params['nodeid'])) ? "WHERE moderator.nodeid = " . $params['nodeid'] : '') . "
				" . ((isset($params['moderatorid']) AND intval($params['moderatorid'])) ? "WHERE moderator.moderatorid = " . $params['moderatorid'] : '') . "
				$orderBy
				/** getModeratorChannelInfo" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** asdasdasdad
	 * 	Used in admincp css.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getCronLog($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			// field checks
			if (isset($params['sortby']))
			{
				$params['sortby'] = @array_pop($params['sortby']);
				if (isset($params['sortby']['field']) AND !empty($params['sortby']['field']))
				{
					if (!$this->checkSortingFields($params['sortby']['field']))
					{
						return false;
					}
				}
			}

			if (!empty($params[vB_Db_Query::PARAM_LIMITSTART]) AND (intval($params[vB_Db_Query::PARAM_LIMITSTART]) < 0))
			{
				return false;
			}

			if (!empty($params[vB_Db_Query::PARAM_LIMIT]) AND (intval($params[vB_Db_Query::PARAM_LIMIT]) < 0))
			{
				return false;
			}

			return true;
		}
		else
		{
			$paginate = false;
			if (!empty($params['sortby']))
			{
				$sortorder = @array_pop($params['sortby']);
			}

			if (isset($params['varname']))
			{
				$varname = $db->escape_string($params['varname']);
			}

			if (!empty($params[vB_Db_Query::PARAM_LIMITSTART]) AND !empty($params[vB_Db_Query::PARAM_LIMIT]))
			{
				$paginate = true;
			}

			$orderBy = $this->getSortingFields($sortorder, $db);

			$sql = "SELECT cronlog.*
			FROM " . TABLE_PREFIX . "cronlog AS cronlog
			" . ((!empty($params['checkCron'])) ?
			"INNER JOIN " . TABLE_PREFIX . "cron AS cron ON (cronlog.varname = cron.varname)" : "") . "
			" . (!empty($varname) ? "WHERE cronlog.varname = '" . $varname . "'" : '') . "
			$orderBy
			LIMIT " . intval($params[vB_Db_Query::PARAM_LIMITSTART]) . ", " . intval($params[vB_Db_Query::PARAM_LIMIT]) . "
			/** getCronLog" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** Saves generic information of notices (active, persistent, dismissible, displayorder)
	 * 	Used in admincp notice manager.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function noticeQuickUpdate($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			// ensure we update the same elements here...
			$notices = array();
			foreach ($params['notice'] AS $notice)
			{
				$notices['ids'][] = $notice['noticeid'];
			}
			$notices['count'] = count($params['notice']);

			if (!$notices['count'])
			{
				return false;
			}

			foreach (array('active', 'persistent', 'dismissible', 'displayorder') AS $field)
			{
				foreach (array_keys($params[$field]) AS $val)
				{
					if (!in_array($val, $notices['ids']))
					{
						return false;
					}
				}
			}

			return true;
		}
		else
		{
			$update_ids = array();
			$notices_undismiss = array();
			foreach ($params['notice'] AS $notice)
			{
				$update_ids[] = $notice['noticeid'];
				$update_active .= " WHEN $notice[noticeid] THEN " . intval($params['active']["$notice[noticeid]"]);
				$update_persistent .= " WHEN $notice[noticeid] THEN " . intval($params['persistent']["$notice[noticeid]"]);
				$update_dismissible .= " WHEN $notice[noticeid] THEN " . intval($params['dismissible']["$notice[noticeid]"]);
				$update_displayorder .= " WHEN $notice[noticeid] THEN " . $params['displayorder']["$notice[noticeid]"];

				if (!$params['dismissible']["$notice[noticeid]"])
				{
					$notices_undismiss[] = $notice['noticeid'];
				}
			}

			$sql = "UPDATE " . TABLE_PREFIX . "notice
					SET	active = CASE noticeid
						$update_active ELSE active END,
						persistent = CASE noticeid
						$update_persistent ELSE persistent END,
						dismissible = CASE noticeid
						$update_dismissible ELSE dismissible END,
						displayorder = CASE noticeid
						$update_displayorder ELSE displayorder END
					WHERE noticeid IN( " . implode(",", $update_ids) . ")
			";

			if (count($notices_undismiss))
			{
				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "noticedismissed
					WHERE noticeid IN( " . implode(",", $notices_undismiss) . ")
				");
			}

			$resultclass = 'vB_dB_' . $this->db_type . '_result';
			$config = vB::get_config();

			if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
			{
				echo "sql: $sql<br />\n";
			}

			$result = new $resultclass($db, $sql);
			return $db->affected_rows();
		}
	}

	/**
	 * Mysql implementation of order by clause.
	 *
	 * @param	array	An array of fields and sort direction.
	 * 					Should be an array containing fields to be sorted are placed in the 'field' index and
	 * 					'direction' index to specify if ASC or DESC.
	 * 					The field index should be in tablename.fieldname form to get the tablename and check against.
	 *	@param	mixed 	a db pointer
	 *
	 * @return	string	The order by string clause.
	 */
	protected function getSortingFields($sortFields, $db)
	{
		$orderBy = "";
		if (!empty($sortFields) AND is_array($sortFields))
		{
			if (isset($sortFields['field']) AND is_array($sortFields['field']))
			{
				$sorts = array();
				foreach ($sortFields['field'] as $key => $field)
				{
					$sort = $db->escape_string($field);
					if (strpos('aliasField', $sort) !== -1)
					{
						$sort = explode('.', $sort);
						$sort = $sort[1];
					}

					if (!empty($sortFields['direction']) AND !empty($sortFields['direction'][$key])
							AND (strtoupper( $sortFields['direction'][$key]) == vB_dB_Query::SORT_DESC)
					)
					{
						$sort .=  ' ' . vB_dB_Query::SORT_DESC;
					}
					else
					{
						$sort .=  ' ' . vB_dB_Query::SORT_ASC;
					}

					$sorts[] = $sort;
				}

				if (!empty($sorts))
				{
					$orderBy .= "\n ORDER BY " . implode(', ', $sorts);
				}
			}
			else if (!empty($sortFields['field']))
			{
				$orderBy .= "\n ORDER BY " . $db->escape_string($sortFields['field']);
				if (!empty($sortFields['direction']) AND (strtoupper($sortFields['direction']) == vB_dB_Query::SORT_DESC))
				{
					$orderBy .= " " . $sortFields['direction'];
				}
			}
		}

		return $orderBy;
	}

	/**
	 * Validate the sorting fields passed.
	 *
	 * @param	array	An array of fields.
	 * 					Should be tablename.fieldname to get the tablename and check against.
	 *
	 * @return	bool	True - valid, False - not valid
	 */
	protected function checkSortingFields($sortFields)
	{
		foreach ($sortFields AS $val)
		{
			$dbField = explode('.', $val);
			$tableStructure = vB::getDbAssertor()->fetchTableStructure($dbField[0]);

			// try getting from vBForum package
			if ($dbField[0] == 'aliasField')
			{
				return true;
			}

			if (empty($tableStructure))
			{
				$tableStructure = vB::getDbAssertor()->fetchTableStructure('vBForum:' . $dbField[0]);
			}

			if (empty($tableStructure))
			{
				return false;
			}

			$tableStructure = $tableStructure['structure'];
			if (!in_array($dbField[1], $tableStructure))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 *  Get the user subscription log
	 * 	Used in admincp subscription manager.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getSubscriptionUsersLog($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			// field checks
			if (isset($params['sortby']))
			{
				$params['sortby'] = @array_pop($params['sortby']);
				if (isset($params['sortby']['field']) AND !empty($params['sortby']['field']))
				{
					if (!$this->checkSortingFields($params['sortby']['field']))
					{
						return false;
					}
				}
			}

			if (isset($params[vB_Db_Query::PARAM_LIMIT]) AND !is_numeric($params[vB_Db_Query::PARAM_LIMIT]))
			{
				return false;
			}

			if (isset($params[vB_Db_Query::PARAM_LIMITSTART]) AND !is_numeric($params[vB_Db_Query::PARAM_LIMITSTART]))
			{
				return false;
			}

			return true;
		}
		else
		{
			$where = '';
			if (!empty($params['conditions']))
			{
				$className = 'vB_Db_' . $this->db_type  . '_QueryBuilder';
				$queryBuilder = new $className($db, false);
				$where .= "WHERE " . $queryBuilder->conditionsToFilter($params['conditions']);
			}
			if (!empty($params['sortby']))
			{
				$sortorder = @array_pop($params['sortby']);
			}

			$orderBy = $this->getSortingFields($sortorder, $db);
			if (!empty($params[vB_Db_Query::PARAM_LIMITSTART]) AND !empty($params[vB_Db_Query::PARAM_LIMIT]))
			{
				$paginate = true;
			}

			$orderBy = $this->getSortingFields($sortorder, $db);

			$sql = "
				SELECT " . ($params['count'] ? "COUNT(*) AS users" : "*") . "
				FROM " . TABLE_PREFIX . "subscriptionlog AS subscriptionlog
				LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid)
				$where
				" . ($orderBy ? "$orderBy" : "" ) . "
				" . ($paginate ? "LIMIT " . $params[vB_Db_Query::PARAM_LIMITSTART] . ", " . $params[vB_Db_Query::PARAM_LIMIT] : "") . "
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
	 *  Do the subscription log display order
	 * 	Used in admincp subscription manager.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function doSubscriptionLogOrder($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			// field checks
			if (empty($params['subscriptions']) OR empty($params['displayorder']))
			{
				return false;
			}

			foreach ($params['subscriptions'] AS $subId => $sub)
			{
				if (!isset($params['displayorder'][$subId]))
				{
					continue;
				}

				if (!is_numeric($params['displayorder'][$subId]) OR ($params['displayorder'][$subId] < 0))
				{
					return false;
				}
			}

			return true;
		}
		else
		{
			$casesql = '';
			$subscriptionids = '';

			foreach($params['subscriptions'] AS $sub)
			{
				if (!isset($params['displayorder']["$sub[subscriptionid]"]))
				{
					continue;
				}

				$displayorder = intval($params['displayorder']["$sub[subscriptionid]"]);
				$displayorder = ($displayorder < 0) ? 0 : $displayorder;
				if ($sub['displayorder'] != $displayorder)
				{
					$casesql .= "WHEN subscriptionid = $sub[subscriptionid] THEN $displayorder\n";
					$subscriptionids .= ",$sub[subscriptionid]";
				}
			}

			if (empty($casesql))
			{
				return false;
			}

			$sql = "
				UPDATE " . TABLE_PREFIX . "subscription
					SET displayorder =
						CASE
							$casesql
							ELSE 1
						END
					WHERE subscriptionid IN (-1$subscriptionids)
				/** doSubscriptionLogOrder" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
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
	 *  Gets the pms for the users
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getUsersPms($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			// field checks
			if (isset($params['sortby']))
			{
				$params['sortby'] = @array_pop($params['sortby']);
				if (isset($params['sortby']['field']) AND !empty($params['sortby']['field']))
				{
					if (!$this->checkSortingFields($params['sortby']['field']))
					{
						return false;
					}
				}
			}

			if (isset($params['total']) AND !is_numeric($params['total']))
			{
				return false;
			}

			return true;
		}
		else
		{
			$pmType = vB_Types::instance()->getContentTypeId('vBForum_PrivateMessage');
			$where = "WHERE contenttypeid = $pmType AND (folder.titlephrase NOT IN ('" . implode("', '", array(vB_Library_Content_Privatemessage::TRASH_FOLDER, vB_Library_Content_Privatemessage::REQUEST_FOLDER, vB_Library_Content_Privatemessage::NOTIFICATION_FOLDER, vB_Library_Content_Privatemessage::PENDING_FOLDER)) . "') OR folder.title <> '')\n";
			if (!empty($params[vB_dB_Query::CONDITIONS_KEY]))
			{
				$className = 'vB_Db_' . $this->db_type  . '_QueryBuilder';
				$queryBuilder = new $className($db, false);
				$where .= " " . $queryBuilder->conditionsToFilter($params[vB_dB_Query::CONDITIONS_KEY]);
			}
			if (!empty($params['sortby']))
			{
				$sortorder = @array_pop($params['sortby']);
			}

			$having = "";
			if ($params['total'])
			{
				$having = "HAVING total = " . $params['total'];
			}

			$orderBy = $this->getSortingFields($sortorder, $db);

			$sql = "
				SELECT u.userid, u.username, u.lastactivity, u.email, count(s.nodeid) AS total
				FROM " . TABLE_PREFIX . "sentto AS s
				INNER JOIN " . TABLE_PREFIX . "node AS n ON (s.nodeid = n.nodeid)
				INNER JOIN " . TABLE_PREFIX . "user AS u ON (s.userid = u.userid)
				INNER JOIN " . TABLE_PREFIX . "messagefolder AS folder ON (s.folderid = folder.folderid)
				$where
				GROUP BY s.userid
				$having
				$orderBy
				/** getUsersPms" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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
	 *  Remove user pms
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function deleteUserPms($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			// field checks
			return !empty($params['userid']);
		}
		else
		{
			$sql = "";
			$pmType = vB_Types::instance()->getContentTypeId('vBForum_PrivateMessage');
			$pmNodes = $db->query_read($sql = "SELECT nodeid, userid
				FROM " . TABLE_PREFIX . "node
				WHERE userid = " . $params['userid'] . " AND contenttypeid = " . $pmType . "
				/** getUserPms **/
			");

			if (empty($pmsNodes))
			{
				return false;
			}

			$pmRecords = $db->query_read($sql .= "SELECT userid, nodeid, msgread
				FROM " . TABLE_PREFX . "sentto
				WHERE nodeid IN ( " . implode(", ", $pmNodes) . ")
					AND	deleted = 1
				/** getSentToRecords **/
			");

			if (empty($pmRecords))
			{
				return false;
			}

			$users = array();
			foreach ($pmRecords AS $pmRecord)
			{
				if (!isset($users[$pmRecord['userid']]))
				{
					$users[$pmRecord['userid']] = array('pmtotal' => 0, 'pmunread' => 0);
				}

				if ($pmRecord['msgread'] == 0)
				{
					$users[$pmRecord['userid']]['pmunread']++;
				}

				$users[$pmRecord['userid']]['pmtotal']++;
			}

			$db->query_write("DELETE FROM " . TABLE_PREFIX . "sentto
				WHERE nodeid IN ( " . implode(", ", $pmNodes) . ")
					AND deleted = 1
				/** removeSenttoRecs **/
			");

			if (!empty($users))
			{
				$pmtotalsql = 'CASE userid ';
				$pmunreadsql = 'CASE userid ';
				foreach($users AS $id => $x)
				{
					$pmtotalsql .= "WHEN $id THEN pmtotal - $x[pmtotal] ";
					$pmunreadsql .= "WHEN $id THEN pmunread - $x[pmunread] ";
				}
				$pmtotalsql .= 'ELSE pmtotal END';
				$pmunreadsql .= 'ELSE pmunread END';

				$userids = implode(', ', array_keys($users));

				$db->query_write($sql .= "
					UPDATE " . TABLE_PREFIX . "user
					SET pmtotal = $pmtotalsql,
					pmunread = $pmunreadsql
					WHERE userid IN($userids)
					/** updatePmCounts **/
				");

				$db->query_write($sql .= "
					UPDATE " . TABLE_PREFIX . "user
					SET pmpopup = IF(pmpopup=2 AND pmunread = 0, 1, pmpopup)
					WHERE userid IN($userids)
					/** updatePmPopupCounts **/
				");
			}

			$config = vB::get_config();

			if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
			{
				echo "sql: $sql<br />\n";
			}

			return true;
		}
	}

	/**
	 *  Gets the payment transaction statistics info
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getTransactionStats($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			// field checks
			if (isset($params['sortby']))
			{
				$params['sortby'] = @array_pop($params['sortby']);
				if (isset($params['sortby']['field']) AND !empty($params['sortby']['field']))
				{
					if (!$this->checkSortingFields($params['sortby']['field']))
					{
						return false;
					}
				}
			}

			return !empty($params['sqlformat']);
		}
		else
		{
			$where = "";
			if (!empty($params[vB_db_Query::CONDITIONS_KEY]))
			{
				$className = 'vB_Db_' . $this->db_type  . '_QueryBuilder';
				$queryBuilder = new $className($db, false);
				$where .= "WHERE " . $queryBuilder->conditionsToFilter($params[vB_db_Query::CONDITIONS_KEY]);
			}

			if (!empty($params['sortby']))
			{
				$sortorder = @array_pop($params['sortby']);
			}

			$orderBy = $this->getSortingFields($sortorder, $db);

			$sql = "
				SELECT COUNT(*) AS total,
				DATE_FORMAT(from_unixtime(dateline), '" . $params['sqlformat'] . "') AS formatted_date,
				MAX(dateline) AS dateline
				FROM " . TABLE_PREFIX . "paymenttransaction AS paymenttransaction
				LEFT JOIN " . TABLE_PREFIX . "paymentinfo AS paymentinfo ON (paymenttransaction.paymentinfoid = paymentinfo.paymentinfoid)
				$where
				GROUP BY formatted_date
				$orderBy
				/** getTransactionStats" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/
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

	/**
	 *  Gets the payment transaction log info
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getTransactionLog($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			// field checks
			if (isset($params['sortby']))
			{
				$params['sortby'] = @array_pop($params['sortby']);
				if (isset($params['sortby']['field']) AND !empty($params['sortby']['field']))
				{
					if (!$this->checkSortingFields($params['sortby']['field']))
					{
						return false;
					}
				}
			}

			if (isset($params[vB_Db_Query::PARAM_LIMIT]) AND !is_numeric($params[vB_Db_Query::PARAM_LIMIT]))
			{
				return false;
			}

			if (isset($params[vB_Db_Query::PARAM_LIMITSTART]) AND !is_numeric($params[vB_Db_Query::PARAM_LIMITSTART]))
			{
				return false;
			}

			return true;
		}
		else
		{
			$where = "";
			if (!empty($params[vB_db_Query::CONDITIONS_KEY]))
			{
				$className = 'vB_Db_' . $this->db_type  . '_QueryBuilder';
				$queryBuilder = new $className($db, false);
				$where .= "WHERE " . $queryBuilder->conditionsToFilter($params[vB_db_Query::CONDITIONS_KEY]);
			}

			if (!empty($params['sortby']))
			{
				$sortorder = @array_pop($params['sortby']);
			}

			$orderBy = $this->getSortingFields($sortorder, $db);

			if (!empty($params[vB_Db_Query::PARAM_LIMITSTART]) AND !empty($params[vB_Db_Query::PARAM_LIMIT]))
			{
				$paginate = true;
			}

			$sql = "
				SELECT paymenttransaction.*,
					paymentinfo.subscriptionid, paymentinfo.userid,
					paymentapi.title,
					user.username
				FROM " . TABLE_PREFIX . "paymenttransaction AS paymenttransaction
				LEFT JOIN " . TABLE_PREFIX . "paymentinfo AS paymentinfo ON (paymenttransaction.paymentinfoid = paymentinfo.paymentinfoid)
				LEFT JOIN " . TABLE_PREFIX . "paymentapi AS paymentapi ON (paymenttransaction.paymentapiid = paymentapi.paymentapiid)
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (paymentinfo.userid = user.userid)
				$where
				$orderBy
				" . ($paginate ? "LIMIT " . $params[vB_Db_Query::PARAM_LIMITSTART] . ", " . $params[vB_Db_Query::PARAM_LIMIT] : "") . "
				/** getTransactionLog" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/
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

	/**
	 *  Gets the payment transaction log total count
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getTransactionLogCount($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$where = "";
			if (!empty($params[vB_db_Query::CONDITIONS_KEY]))
			{
				$className = 'vB_Db_' . $this->db_type  . '_QueryBuilder';
				$queryBuilder = new $className($db, false);
				$where .= "WHERE " . $queryBuilder->conditionsToFilter($params[vB_db_Query::CONDITIONS_KEY]);
			}

			$sql = "
				SELECT COUNT(*) AS trans
				FROM " . TABLE_PREFIX . "paymenttransaction AS paymenttransaction
				LEFT JOIN " . TABLE_PREFIX . "paymentinfo AS paymentinfo ON (paymenttransaction.paymentinfoid = paymentinfo.paymentinfoid)
				$where
				/** getTransactionLogCount" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/
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

	/** Get all channel members count if no nodeid is specified.
	 * 	Used in admincp css.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getChannelMembersCount($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			if (isset($params['nodeid']))
			{
				if (!is_array($params['nodeid']))
				{
					$params['nodeid'] = array($params['nodeid']);
				}

				foreach ($params['nodeid'] AS $node)
				{
					if (!is_numeric($node))
					{
						return false;
					}
				}
			}

			if (isset($params['groupid']))
			{
				if (!is_array($params['groupid']))
				{
					$params['groupid'] = array($params['groupid']);
				}

				foreach ($params['groupid'] AS $node)
				{
					if (!is_numeric($node))
					{
						return false;
					}
				}
			}

			return true;
		}
		else
		{
			$where = "";
			if (!empty($params['nodeid']))
			{
				$where .= "WHERE nodeid IN (" . implode(',', $params['nodeid']) . ")";
			}

			if (!empty($params['groupid']))
			{
				$where .= ($where) ? " AND groupid IN (" . implode(',', $params['groupid']) . ")" : "WHERE groupid IN (" . implode(',' , $params['groupid']) . ")";
			}

			$sql = "
			SELECT COUNT(*) AS members, nodeid
			FROM " . TABLE_PREFIX . "groupintopic
			$where
			GROUP BY nodeid
			/** getChannelMembersCount" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** Get the all socialgroups count.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getSocialGroupsTotalCount($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			if (isset($params['userid']) AND !intval($params['userid']))
			{
				return false;
			}

			if (!isset($params['sgParentChannel']) OR !intval($params['sgParentChannel']))
			{
				return false;
			}

			if (!isset($params['depth']) OR !intval($params['depth']))
			{
				return false;
			}

			return true;
		}
		else
		{
			$where = "WHERE cl.depth = " . intval($params['depth']) . " AND cl.parent = " . intval($params['sgParentChannel']);
			if (!empty($params['userid']))
			{
				$where .= " AND node.userid = " . intval($params['userid']);
			}

			$permflags = $this->getNodePermTerms();
			$sql = "
			SELECT COUNT(node.nodeid) AS totalcount
			FROM " . TABLE_PREFIX . "node AS node
			INNER JOIN " . TABLE_PREFIX . "closure AS cl ON (node.nodeid = cl.child)
			" . (!empty($permflags['joins']['starter']) ? $permflags['joins']['starter'] : '') . "
			" . (!empty($permflags['joins']['blocked']) ? $permflags['joins']['blocked'] : '') . "
			$where
			" . (!empty($permflags['where']) ? $permflags['where'] : '') . "
			/** getSocialGroupsTotalCount" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/** Get the socialgroups categories.
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *	@param	bool
	 *
	 *	@result	mixed
	 */
	public function getSocialGroupsCategories($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true;
		}
		else
		{
			$sgChannel = intval(vB_Api::instanceInternal('socialgroup')->getSGChannel());
			$channelContentTypeId = intval(vB_Types::instance()->getContentTypeID('vBForum_Channel'));

			$sqlSelect = $sqlJoins = $sqlWhere = $sqlGroupBy = array();

			$sqlSelect[] = 'n.*';
			$sqlWhere[] = "n.parentid = $sgChannel AND n.contenttypeid = $channelContentTypeId";

			if (isset($params['doCount']) AND !empty($params['doCount']))
			{
				$sqlSelect[] = 'COUNT(c.child) AS groupcount';
				$sqlJoins += array(
					'LEFT JOIN ' . TABLE_PREFIX . 'closure AS c ON n.nodeid = c.parent AND c.depth = 1',
					'LEFT JOIN ' . TABLE_PREFIX . "node AS n2 ON c.child = n2.nodeid AND n2.contenttypeid = $channelContentTypeId"
				);
				$sqlGroupBy[] = 'n.nodeid';
			}

			if (isset($params['fetchCreator']) AND !empty($params['fetchCreator']))
			{
				$sqlSelect[] = 'u.username';
				$sqlJoins[] = 'LEFT JOIN ' . TABLE_PREFIX . 'user u ON n.userid = u.userid';
			}

			if (isset($params['categoryId']) AND !empty($params['categoryId']))
			{
				if (!is_array($params['categoryId']))
				{
					$params['categoryId']= array($params['categoryId']);
				}
				$sqlWhere[] = "n.nodeid IN (" . implode(',', array_map('intval', $params['categoryId'])) .  ")";
			}

			$sql = "
			SELECT " . implode(', ', $sqlSelect) . "
			FROM " . TABLE_PREFIX . "node AS n
			" . (empty($sqlJoins) ? '' : implode(" \n", $sqlJoins)) . "
			WHERE "  . implode("\n AND ", $sqlWhere) . "
			" . (empty($sqlGroupBy) ? '' : ('GROUP BY ' . implode(', ', $sqlGroupBy))) . "
			ORDER BY n.title
			/** getSocialGroupsCategories" . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . '**/';

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

	public function getTLChannelInfo($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['channelid']) AND isset($params['from']) AND !empty($params['perpage']);
		}

		$types[] = vB_Types::instance()->getContentTypeID('vBForum_Text');
		$types[] = vB_Types::instance()->getContentTypeID('vBForum_Poll');
		$types[] = vB_Types::instance()->getContentTypeID('vBForum_Gallery');
		$types[] = vB_Types::instance()->getContentTypeID('vBForum_Video');
/*
 				SELECT parent.* , count(*) AS count
				FROM ' . TABLE_PREFIX . 'closure AS cl
				INNER JOIN ' . TABLE_PREFIX . 'node AS parent ON parent.nodeid = cl.child AND parent.contenttypeid =23 AND cl.depth >0
				INNER JOIN ' . TABLE_PREFIX . 'closure AS cl2 ON cl2.parent = parent.nodeid
				INNER JOIN ' . TABLE_PREFIX . 'node AS node ON cl2.child = node.nodeid
				INNER JOIN ' . TABLE_PREFIX . 'channel AS c ON c.nodeid = parent.nodeid
				WHERE cl.parent = ' . intval($params['channelid']) . '
					AND node.contenttypeid IN ( ' . implode(',', $types) . ' )
					AND (
						node.parentid = node.starter
						OR node.nodeid = node.starter
						)
					AND c.category =0
					GROUP BY parent.nodeid
					ORDER BY parent.title ASC
				LIMIT ' . intval($params['from']) . ' , ' . intval($params['perpage']) . '

 */
		$sql = '
				SELECT parent.* , (
							SELECT count(*)
							FROM ' . TABLE_PREFIX . 'closure AS cl2
							INNER JOIN ' . TABLE_PREFIX . 'node AS node ON cl2.child = node.nodeid
							WHERE
								cl2.parent = parent.nodeid
								AND node.contenttypeid IN ( ' . implode(',', $types) . ' )
								AND (
									node.parentid = node.starter
									OR node.nodeid = node.starter
									)
						) AS count
				FROM ' . TABLE_PREFIX . 'closure AS cl
				INNER JOIN ' . TABLE_PREFIX . 'node AS parent ON parent.nodeid = cl.child AND parent.contenttypeid =23 AND cl.depth >0
				INNER JOIN ' . TABLE_PREFIX . 'channel AS c ON c.nodeid = parent.nodeid
				WHERE cl.parent = ' . intval($params['channelid']) . '
					AND c.category =0
					ORDER BY parent.title ASC
				LIMIT ' . intval($params['from']) . ' , ' . intval($params['perpage']) . '

		/** getTLChannelInfo' . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . '**/';
		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::get_config();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}

		$result = new $resultclass($db, $sql);
		return $result;
	}

	public function getTLChannelCount($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['channelid']);
		}
		$sql = '
		SELECT count(*) AS count
		FROM ' . TABLE_PREFIX . 'channel c
		INNER JOIN ' . TABLE_PREFIX . 'node n ON c.nodeid = n.nodeid
		INNER JOIN ' . TABLE_PREFIX . 'closure cl ON cl.child = n.nodeid
		WHERE
			cl.parent = ' . intval($params['channelid']) . '
			AND cl.depth > 0
			AND c.category = 0
		/** getTLChannelCount' . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . '**/';

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$config = vB::get_config();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}

		$result = new $resultclass($db, $sql);
		return $result;
	}

	public function clearPictureData($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return true; // Nothing really to check, so just return true.
		}

		$db->query_write("UPDATE " . TABLE_PREFIX . "customavatar SET filedata = '', filedata_thumb = ''");
		$db->query_write("UPDATE " . TABLE_PREFIX . "customprofilepic SET filedata = ''");
		$db->query_write("UPDATE " . TABLE_PREFIX . "sigpic SET filedata = ''");

		return true;
	}

	public function deleteChildContentTableRecords($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			if (!empty($params['nodeid']))
			{
				if (is_array($params['nodeid']))
				{
					foreach($params['nodeid'] AS $key => $nodeid)
					{
						if (!is_numeric($nodeid))
						{
							unset($params['nodeid'][$key]);
						}
					}
				}
				else
				{
					if (!is_numeric($params['nodeid']))
					{
						unset($params['nodeid']);
					}
				}
			}
			return !empty($params['tablename']) AND !empty($params['nodeid']);
		}

		if (is_array($params['nodeid']))
		{
			foreach($params['nodeid'] AS $key => $nodeid)
			{
				if (!is_numeric($nodeid))
				{
					unset($params['nodeid'][$key]);
				}
			}
			$nodeids = implode(',', $params['nodeid']);
		}
		else
		{
			if (!is_numeric($params['nodeid']))
			{
				unset($params['nodeid']);
			}
			$nodeids = $params['nodeid'];
		}
		$sql = "DELETE type.* FROM " . TABLE_PREFIX . $params['tablename'] . " AS type INNER JOIN " . TABLE_PREFIX .
			"closure AS cl ON cl.child = type.nodeid
			WHERE cl.parent IN ($nodeids)/** deleteChildContentTableRecords **/ " ;
		$config = vB::getConfig();

		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql<br />\n";
		}
		return $db->query_write($sql);
	}

	/*** Rebuilds the pmtotal for the given userids
	 *
	 *	@param	mixed		the query parameters
	 * 	@param	object		the database object
	 * 	@param	bool		whether we run the query, or just validate that we can run it.
	 *
	 *	@return	int
	 *
	 ***/
	public function buildPmTotals($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['userid']));
		}

		$userids = array_map('intval', $params['userid']);

		$pmtotalsql = "";
		$users = array();

		$sql1 = "
			SELECT COUNT(DISTINCT node.nodeid) AS pmtotal, sentto.userid
			FROM " . TABLE_PREFIX . "sentto AS sentto
			INNER JOIN " . TABLE_PREFIX . "node AS node ON (node.nodeid = sentto.nodeid AND node.nodeid = node.starter)
			WHERE
				sentto.userid IN (" . implode(', ', $userids) . ")
			GROUP BY sentto.userid
			/**" . __FUNCTION__ . ' (Fetch Totals) ' . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/\n";

		$results = $db->query_read($sql1);
		while ($result = $db->fetch_array($results))
		{
			$users[] = $result['userid'];
			$pmtotalsql .= "WHEN {$result['userid']} THEN {$result['pmtotal']} ";
		}

		$sql2 = '';
		if (!empty($pmtotalsql))
		{
			$sql2 = "
				UPDATE " . TABLE_PREFIX . "user
				SET pmtotal = CASE userid
					$pmtotalsql
					ELSE pmtotal END
				WHERE userid IN (" . implode(', ', $users) . ")
				/**" . __FUNCTION__ . ' (Update Totals) ' . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/\n";

			$db->query_write($sql2);
		}

		$config = vB::getConfig();
		if (isset($config['Misc']['debug_sql']) AND $config['Misc']['debug_sql'])
		{
			echo "sql: $sql1\n$sql2 <br />\n";
		}

		return $result;
	}

	/** Gets subscribers from a given nodeid
	 *
	 *	@param	mixed
	 *	@param	mixed 	a db pointer
	 *  @param	bool
	 *
	 *	@result	mixed
	 */
	public function fetchNodeSubscribers($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			if (!isset($params['nodeid']))
			{
				return false;
			}

			foreach (array('nodeid', vB_dB_Query::PARAM_LIMITPAGE, vB_dB_Query::PARAM_LIMIT) AS $param)
			{
				if (isset($params[$param]) AND (!is_numeric($params[$param]) OR ($params[$param] < 1)))
				{
					return false;
				}
			}

			if (isset($params['sort']) AND !is_array($params['sort']))
			{
				if (!is_array($params['sort']))
				{
					return false;
				}

				foreach ($params['sort'] AS $field => $direction)
				{
					if (!in_array($field, array('username', 'userid', 'lastactivity')))
					{
						return false;
					}

					if (!in_array($direction, array(vB_dB_Query::SORT_ASC, vB_dB_Query::SORT_DESC)))
					{
						return false;
					}
				}
			}

			return true;
		}
		else
		{
			$sql = "SELECT SQL_CALC_FOUND_ROWS u.userid, u.username
			FROM " . TABLE_PREFIX . "subscribediscussion sd
			INNER JOIN " . TABLE_PREFIX . "user u ON sd.userid = u.userid
			WHERE sd.discussionid = " . $params['nodeid'] . "\n";

			$sorts = array();
			if (isset($params['sort']))
			{
				foreach ($params['sort'] AS $field => $direction)
				{
					$sorts[] = 'u.' . $field . ' ' . $direction;
				}

				$sql .= "ORDER BY " . implode(", ", $sorts) . "\n";
			}

			$limit = "";
			if (isset($params[vB_dB_Query::PARAM_LIMIT]))
			{
				$perpage = $params[vB_dB_Query::PARAM_LIMIT];
			}
			else
			{
				$perpage = 20;
			}

			if (isset($params[vB_dB_Query::PARAM_LIMITPAGE]))
			{
				$limit .=  ($perpage * ($params[vB_dB_Query::PARAM_LIMITPAGE] - 1)) . ',';
			}

			$sql .= "LIMIT " . $limit .$perpage;
			$sql .= "\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

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

	/*** Updates node totals based on supplied array
	 *
	 *	@param	mixed		the query parameters
	 * 	@param	object		the database object
	 * 	@param	bool		whether we run the query, or just validate that we can run it.
	 *
	 ***/
	public function updateNodeTotals($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return (!empty($params['updates']));
		}

		$sql = '';
		$nodes = array();
		$multiple = $flag = false;
		$updates =& $params['updates'];

		if ($updates)
		{
			foreach ($updates AS $field => $values)
			{
				if ($values)
				{
					$sql .= $multiple ? ",\n" : "\n";
					$sql .= " $field = CASE nodeid \n";

					foreach ($values AS $nodeid => $value)
					{
						$flag = true;
						$nodes[] = $nodeid;

						if ($value > -1)
						{
							$sql .= " WHEN $nodeid THEN $field + $value \n";
						}
						else
						{
							$sql .= " WHEN $nodeid THEN (CASE WHEN $field > " . abs($value) . " THEN $field + ($value) ELSE 0 END) \n";
						}
					}

					$multiple = true;
					$sql .= " ELSE $field END";
				}
			}
		}

		if ($flag)
		{
			$nodes = implode(',', array_unique($nodes));

			$db->query_write(" UPDATE " . TABLE_PREFIX . "node SET \n $sql \n WHERE nodeid IN ($nodes)");
		}
	}
}
/*======================================================================*\
|| ####################################################################
|| # SVN=> $Revision=> 28823 $
|| ####################################################################
\*======================================================================*/
