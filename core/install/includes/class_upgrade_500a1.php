<?php
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

class vB_Upgrade_500a1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.2.0';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '4.0.0';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '';

	/*** create table steps
	first page */
	function step_1()
	{
		if (!$this->tableExists('page'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'page'),
				"
				CREATE TABLE " . TABLE_PREFIX . "page (
				  pageid int(10) unsigned NOT NULL AUTO_INCREMENT,
				  parentid int(10) unsigned NOT NULL,
				  pagetemplateid int(10) unsigned NOT NULL,
				  title varchar(200) NOT NULL,
				  metakeywords varchar(200) NOT NULL,
				  metadescription varchar(200) NOT NULL,
				  urlprefix varchar(200) NOT NULL,
				  routeid int(10) unsigned NOT NULL,
				  moderatorid int(10) unsigned NOT NULL,
				  displayorder int(11) NOT NULL,
				  pagetype enum('default','custom') NOT NULL DEFAULT 'custom',
				  guid char(150) DEFAULT NULL,
				  PRIMARY KEY (pageid)
				) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/***	pagetemplate table
	*/
	function step_2()
	{
		if (!$this->tableExists('pagetemplate'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'pagetemplate'),
				"
				CREATE TABLE " . TABLE_PREFIX . "pagetemplate (
				  pagetemplateid int(10) unsigned NOT NULL AUTO_INCREMENT,
				  title varchar(200) NOT NULL,
				  screenlayoutid int(10) unsigned NOT NULL,
				  content text NOT NULL,
				  guid char(150) DEFAULT NULL,
				  PRIMARY KEY (pagetemplateid)
				) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/***	routenew table
	*/
	function step_3()
	{
		if (!$this->tableExists('routenew'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'routenew'),
				"
				CREATE TABLE " . TABLE_PREFIX . "routenew (
				  routeid int(10) unsigned NOT NULL AUTO_INCREMENT,
				  name varchar(100) DEFAULT NULL,
				  redirect301 int(10) unsigned DEFAULT NULL,
				  prefix varchar(" . vB5_Route::PREFIX_MAXSIZE . ") NOT NULL,
				  regex varchar(" . vB5_Route::REGEX_MAXSIZE . ") NOT NULL,
				  class varchar(100) DEFAULT NULL,
				  controller varchar(100) NOT NULL,
				  action varchar(100) NOT NULL,
				  template varchar(100) NOT NULL,
				  arguments mediumtext NOT NULL,
				  contentid int(10) unsigned NOT NULL,
				  guid char(150) DEFAULT NULL,
				  PRIMARY KEY (routeid),
				  KEY regex (regex),
				  KEY prefix (prefix),
				  KEY route_name (name),
				  KEY route_class_cid (class, contentid)
				) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);

		}
		else
		{
			$this->skip_message();
		}
	}

	/***	screenlayout table*/
	function step_4()
	{
		if (!$this->tableExists('screenlayout'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'screenlayout'),
				"
				CREATE TABLE " . TABLE_PREFIX . "screenlayout (
				  screenlayoutid int(10) unsigned NOT NULL AUTO_INCREMENT,
				  varname varchar(20) NOT NULL,
				  title varchar(200) NOT NULL,
				  displayorder smallint(5) unsigned NOT NULL,
				  columncount tinyint(3) unsigned NOT NULL,
				  template varchar(200) NOT NULL,
				  admintemplate varchar(200) NOT NULL,
				  PRIMARY KEY (screenlayoutid)
				) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/***	widget table*/
	function step_5()
	{
		if (!$this->tableExists('widget'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'widget'),
				"
				CREATE TABLE " . TABLE_PREFIX . "widget (
				  widgetid int(10) unsigned NOT NULL AUTO_INCREMENT,
				  title varchar(200) NOT NULL,
				  template varchar(200) NOT NULL,
				  admintemplate varchar(200) NOT NULL,
				  icon varchar(200) NOT NULL,
				  isthirdparty tinyint(3) unsigned NOT NULL,
				  category varchar(100) NOT NULL DEFAULT 'uncategorized',
				  cloneable tinyint(3) unsigned NOT NULL DEFAULT '1',
				  canbemultiple tinyint(3) unsigned NOT NULL DEFAULT '1',
				  product VARCHAR(25) NOT NULL DEFAULT 'vbulletin',
				  guid char(150) DEFAULT NULL,
				  PRIMARY KEY (widgetid)
				) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/***	widgetdefinition*/
	function step_6()
	{
		if (!$this->tableExists('widgetdefinition'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'widgetdefinition'),
				"
				CREATE TABLE " . TABLE_PREFIX . "widgetdefinition (
				  widgetid int(10) unsigned NOT NULL,
				  field varchar(50) NOT NULL,
				  name varchar(50) NOT NULL,
				  label varchar(200) NOT NULL,
				  defaultvalue blob NOT NULL,
				  isusereditable tinyint(4) NOT NULL DEFAULT '1',
				  isrequired tinyint(4) NOT NULL DEFAULT '0',
				  displayorder smallint(6) NOT NULL,
				  validationtype enum('force_datatype','regex','method') NOT NULL,
				  validationmethod varchar(200) NOT NULL,
				  product VARCHAR(25) NOT NULL DEFAULT 'vbulletin',
				  data text NOT NULL,
				  KEY (widgetid),
				  KEY product (product)
				) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}

	}

	/***	widgetinstance table*/
	function step_7()
	{
		if (!$this->tableExists('widgetinstance'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'widgetinstance'),
				"
				CREATE TABLE " . TABLE_PREFIX . "widgetinstance (
				  widgetinstanceid int(10) unsigned NOT NULL AUTO_INCREMENT,
				  parent int(10) unsigned NOT NULL DEFAULT '0',
				  pagetemplateid int(10) unsigned NOT NULL,
				  widgetid int(10) unsigned NOT NULL,
				  displaysection tinyint(3) unsigned NOT NULL,
				  displayorder smallint(5) unsigned NOT NULL,
				  adminconfig blob NOT NULL,
				  PRIMARY KEY (widgetinstanceid),
				  KEY pagetemplateid (pagetemplateid,widgetid)
				) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/***	widgetuserconfig table*/
	function step_8()
	{
		if (!$this->tableExists('widgetuserconfig'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'widgetuserconfig'),
				"
				CREATE TABLE " . TABLE_PREFIX . "widgetuserconfig (
				  widgetinstanceid int(10) unsigned NOT NULL,
				  userid int(10) unsigned NOT NULL,
				  userconfig blob NOT NULL,
				  UNIQUE KEY widgetinstanceid (widgetinstanceid,userid)
				) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/***	node table*/
	function step_9()
	{
		if (!$this->tableExists('node'))
		{
			$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'node'),
			"
			CREATE TABLE " . TABLE_PREFIX . "node (
			nodeid INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			routeid INT UNSIGNED NOT NULL DEFAULT 0,
			contenttypeid SMALLINT NOT NULL,
			publishdate INTEGER,
			unpublishdate INTEGER,
			userid INT UNSIGNED ,
			groupid INT UNSIGNED,
			authorname VARCHAR(100),
			description VARCHAR(1024),
			keywords VARCHAR(1024),
			title VARCHAR(512),
			htmltitle VARCHAR(512),
			parentid INTEGER NOT NULL,
			urlident VARCHAR(512),
			displayorder SMALLINT,
			starter INT NOT NULL DEFAULT '0',
			created INT,
			lastcontent INT NOT NULL DEFAULT '0',
			lastcontentid INT NOT NULL DEFAULT '0',
			lastcontentauthor VARCHAR(100) NOT NULL DEFAULT '',
			lastauthorid INT UNSIGNED NOT NULL DEFAULT '0',
			lastprefixid VARCHAR(25) NOT NULL DEFAULT '',
			textcount mediumint UNSIGNED NOT NULL DEFAULT '0',
			textunpubcount mediumint UNSIGNED NOT NULL DEFAULT '0',
			totalcount mediumint UNSIGNED NOT NULL DEFAULT '0',
			totalunpubcount mediumint UNSIGNED NOT NULL DEFAULT '0',
			ipaddress CHAR(15) NOT NULL DEFAULT '',
			showpublished SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			oldid INT UNSIGNED,
			oldcontenttypeid INT UNSIGNED,
			nextupdate INTEGER,
			lastupdate INTEGER,
			featured SMALLINT NOT NULL DEFAULT 0,
			CRC32 VARCHAR(10) NOT NULL DEFAULT '',
			taglist MEDIUMTEXT,
			inlist SMALLINT UNSIGNED NOT NULL DEFAULT '1',
			protected SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			setfor INTEGER NOT NULL DEFAULT 0,
			votes SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
			hasphoto SMALLINT NOT NULL DEFAULT '0',
			hasvideo SMALLINT NOT NULL DEFAULT '0',
			deleteuserid  INT UNSIGNED,
			deletereason VARCHAR(125),
			open SMALLINT NOT NULL DEFAULT '1',
			showopen SMALLINT NOT NULL DEFAULT '1',
			sticky TINYINT(1) NOT NULL DEFAULT '0',
			approved TINYINT(1) NOT NULL DEFAULT '1',
			showapproved TINYINT(1) NOT NULL DEFAULT '1',
			viewperms TINYINT NOT NULL DEFAULT 2,
			commentperms TINYINT NOT NULL DEFAULT 1,
			nodeoptions SMALLINT UNSIGNED NOT NULL DEFAULT 138,
			prefixid VARCHAR(25) NOT NULL DEFAULT '',
			iconid SMALLINT NOT NULL DEFAULT '0',
			INDEX node_lastauthorid(lastauthorid),
			INDEX node_lastcontent(lastcontent),
			INDEX node_textcount(textcount),
			INDEX node_ip(ipaddress),
			INDEX node_pubdate(publishdate, nodeid),
			INDEX node_unpubdate(unpublishdate),
			INDEX node_parent(parentid),
			INDEX node_nextupdate(nextupdate),
			INDEX node_lastupdate(lastupdate),
			INDEX node_user(userid),
			INDEX node_oldinfo(oldcontenttypeid, oldid),
			INDEX node_urlident(urlident),
			INDEX node_sticky(sticky),
			INDEX node_starter(starter),
			INDEX node_approved(approved),
			INDEX node_showapproved(showapproved),
			INDEX node_ctypid_userid_dispo_idx(contenttypeid, userid, displayorder),
			INDEX node_setfor_pubdt_idx(setfor, publishdate),
			INDEX prefixid (prefixid, nodeid),
			INDEX nodeid (nodeid, contenttypeid)
			) ENGINE = " . $this->hightrafficengine . "
						",
			self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			// we need to reset any reference to deleted routes (page table is dropped, so only do it for nodes)
			/* Dont really see why we would need this, commented out for now, unless someone can explain the logic here.
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'),
				'UPDATE ' . TABLE_PREFIX . 'node SET routeid = 0');
			*/
			$this->skip_message();
		}
	}

	public function step_10()
	{
		$this->skip_message();
	}

	function step_11()
	{
		$this->skip_message();
	}

	function step_12()
	{
		/* See Step 36 */
		$this->skip_message();
	}

	/***	closure table*/
	function step_13()
	{
		if (!$this->tableExists('closure'))
		{
			$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'closure'),
			"
			CREATE TABLE " . TABLE_PREFIX . "closure (
				parent INT UNSIGNED NOT NULL,
				child INT UNSIGNED NOT NULL,
				depth SMALLINT NULL,
				displayorder SMALLINT NOT NULL DEFAULT 0,
				publishdate INT,
				KEY parent_2 (parent, depth, publishdate, child),
				KEY publishdate (publishdate, child),
				KEY child (child, depth),
				KEY (displayorder),
				UNIQUE KEY closure_uniq (parent, child)
				) ENGINE = " . $this->hightrafficengine . "
				",
			self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/***	text table*/
	function step_14()
	{
		if (!$this->tableExists('text'))
		{
			$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'text'),
			"
			CREATE TABLE " . TABLE_PREFIX . "text (
				nodeid INT UNSIGNED NOT NULL PRIMARY KEY,
				previewtext VARCHAR(2048),
				previewimage VARCHAR(256),
				previewvideo TEXT,
				imageheight SMALLINT,
				imagewidth SMALLINT,
				rawtext MEDIUMTEXT,
				pagetextimages TEXT,
				moderated smallint,
				pagetext MEDIUMTEXT,
				htmlstate ENUM('off', 'on', 'on_nl2br'),
				allowsmilie SMALLINT NOT NULL DEFAULT '0',
				showsignature SMALLINT NOT NULL DEFAULT '0',
				attach SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				infraction SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				reportnodeid INT UNSIGNED NOT NULL DEFAULT '0'
				) ENGINE = " . $this->hightrafficengine . "
				",
			self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/***	channel table*/
	function step_15()
	{
		if (!$this->tableExists('channel'))
		{
			$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'channel'),
			"
			CREATE TABLE " . TABLE_PREFIX . "channel (
				nodeid INT UNSIGNED NOT NULL PRIMARY KEY,
				styleid SMALLINT NOT NULL DEFAULT '0',
				options INT(10) UNSIGNED NOT NULL DEFAULT 1728,
				daysprune SMALLINT NOT NULL DEFAULT '0',
				newcontentemail TEXT,
				defaultsortfield VARCHAR(50) NOT NULL DEFAULT 'lastcontent',
				defaultsortorder ENUM('asc', 'desc') NOT NULL DEFAULT 'desc',
				imageprefix VARCHAR(100) NOT NULL DEFAULT '',
				guid char(150) DEFAULT NULL,
				filedataid INT,
				category SMALLINT UNSIGNED NOT NULL DEFAULT '0'
			) ENGINE = " . $this->hightrafficengine . "
				",
			self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/***	attach table*/
	function step_16()
	{
		if (!$this->tableExists('attach'))
		{
			$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'attach'),
			"
			CREATE TABLE " . TABLE_PREFIX . "attach (
				nodeid INT UNSIGNED NOT NULL,
				filedataid INT UNSIGNED NOT NULL,
				visible SMALLINT NOT NULL DEFAULT 1,
				counter INT UNSIGNED NOT NULL DEFAULT '0',
				posthash VARCHAR(32) NOT NULL DEFAULT '',
				filename VARCHAR(255) NOT NULL DEFAULT '',
				caption TEXT,
				reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
				settings MEDIUMTEXT,
				KEY attach_nodeid(nodeid),
				KEY attach_filedataid(filedataid)
				) ENGINE = " . $this->hightrafficengine . "
				",
			self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			if ($this->field_exists('node', 'attachid'))
			{
				$this->drop_field(
					sprintf($this->phrase['core']['altering_x_table'], 'attach', 1, 1),
					'attach',
					'attachid'
				);
			}
			else
			{
				$this->skip_message();
			}

		}
	}

	/***	permission table*/
	function step_17()
	{
		if (!$this->tableExists('permission'))
		{
			$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'permission'),
			"
			CREATE TABLE " . TABLE_PREFIX . "permission (
				permissionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				nodeid INT UNSIGNED NOT NULL,
				groupid INT UNSIGNED NOT NULL,
				forumpermissions INT UNSIGNED NOT NULL DEFAULT 0,
				forumpermissions2 INT UNSIGNED NOT NULL DEFAULT 0,
				moderatorpermissions INT UNSIGNED NOT NULL DEFAULT 0,
				createpermissions INT UNSIGNED NOT NULL DEFAULT 0,
				edit_time INT UNSIGNED NOT NULL DEFAULT 0,
				require_moderate SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				maxtags SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				maxstartertags SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				maxothertags SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				maxattachments SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				maxchannels SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				channeliconmaxsize INT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (permissionid),
				KEY perm_nodeid (nodeid),
				KEY perm_groupid (nodeid),
				KEY perm_group_node (groupid, nodeid)
				) ENGINE = " . $this->hightrafficengine . "
				",
			self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/***	contentpriority table*/
	function step_18()
	{
		if (!$this->tableExists('contentpriority'))
		{
			$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'contentpriority'),
			"
			CREATE TABLE " . TABLE_PREFIX . "contentpriority (
				contenttypeid VARCHAR(20) NOT NULL,
				sourceid INT(10) UNSIGNED NOT NULL,
				prioritylevel DOUBLE(2,1) UNSIGNED NOT NULL,
				PRIMARY KEY (contenttypeid, sourceid)
				) ENGINE = " . $this->hightrafficengine . "
				",
			self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}


	/***	tagnode table*/
	function step_19()
	{
		if (!$this->tableExists('tagnode'))
		{
			$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'tagnode'),
			"
			CREATE TABLE " . TABLE_PREFIX . "tagnode (
				tagid INT UNSIGNED NOT NULL DEFAULT 0,
				nodeid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY tag_type_cid (tagid, nodeid),
				KEY id_type_user (nodeid, userid),
				KEY id_type_node (nodeid),
				KEY id_type_tag (tagid),
				KEY user (userid),
				KEY dateline (dateline)
				) ENGINE = " . $this->hightrafficengine . "
				",
			self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_20()
	{
		$this->skip_message();
	}

	function step_21()
	{
		$this->skip_message();
	}

	/** Make sure we have channel, text and poll type**/
	function step_22()
	{
		$contenttype = $this->db->query_first("
			SELECT contenttypeid FROM " . TABLE_PREFIX . "contenttype
			WHERE class = 'Text'");
		if (empty($contenttype) OR empty($contenttype['contenttypeid']))
		{
			$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'contenttype'),
			"INSERT INTO " . TABLE_PREFIX . "contenttype(class,
			packageid,	canplace,	cansearch,	cantag,	canattach,	isaggregator)
			SELECT 'Text', packageid, '0', '1', '1', '1', '0'  FROM " . TABLE_PREFIX . "package where class = 'vBForum';");
			$textTypeId = $this->db->insert_id();
		}
		//If this is the first time upgrade has been run we won't have channel and text types
		$contenttype = $this->db->query_first("
			SELECT contenttypeid FROM " . TABLE_PREFIX . "contenttype
			WHERE class = 'Channel'");

		if (empty($contenttype) OR empty($contenttype['contenttypeid']))
		{
			$this->db->query_write(
				"INSERT INTO " . TABLE_PREFIX . "contenttype(class,
			packageid,	canplace,	cansearch,	cantag,	canattach,	isaggregator)
			SELECT 'Channel', packageid, '0','1', '0', '0', '1' FROM " . TABLE_PREFIX . "package where class = 'vBForum';");
			vB_Types::instance()->reloadTypes();
			$contenttypeid = vB_Types::instance()->getContentTypeID('vBForum_Channel');
		}



		$contenttype = $this->db->query_first("
			SELECT contenttypeid FROM " . TABLE_PREFIX . "contenttype
			WHERE class = 'Poll'");
		if (empty($contenttype) OR empty($contenttype['contenttypeid']))
		{
			$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'contenttype'),
			"INSERT INTO " . TABLE_PREFIX . "contenttype(class,
			packageid,	canplace,	cansearch,	cantag,	canattach,	isaggregator)
			SELECT 'Poll', packageid, '0', '1', '0', '0', '0'  FROM " . TABLE_PREFIX . "package where class = 'vBForum';");
			$pollTypeId = $this->db->insert_id();

		}
		else
		{
			$this->skip_message();
		}
	}
	/***	Create the home page record */
	function step_23()
	{
		vB_Types::instance()->reloadTypes();
		$existingRecords = $this->db->query_first("
			SELECT pageid FROM " . TABLE_PREFIX . "page"
		);

		if (empty($existingRecords) OR empty($existingRecords['pageid']))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'page'),
				"
				INSERT INTO " . TABLE_PREFIX . "page
				(pageid, parentid, pagetemplateid, title, metakeywords, metadescription, routeid, moderatorid, displayorder, pagetype)
				VALUES
				(1, 0, 1, 'Forums', 'forum, discussion board, discussion forum', 'vBulletin Forums', 9, 0, 1, '" . vB_Page::TYPE_CUSTOM . "'),
				(2, 0, 4, 'Search', 'search, search results, search results', 'vBulletin Search', 24, 0, 1, 'default'),
				(3, 1, 3, 'Forums', '', '', 30, 0, 0, '" . vB_Page::TYPE_DEFAULT .  "')
				"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_24()
	{
		$this->skip_message();
	}

	function step_25()
	{
		$this->skip_message();
	}

	function step_26()
	{
		$this->skip_message();
	}

	function step_27()
	{
		$this->skip_message();
	}

	function step_28()
	{
		$this->skip_message();
	}

	function step_29()
	{
		$this->skip_message();
	}

	/**
	 * create attach table
	 *
	 */
	function step_30()
	{
		if (!$this->tableExists('attach'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'attach'),
				"
				CREATE TABLE " . TABLE_PREFIX . "attach (
					attachid INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
					nodeid INT UNSIGNED NOT NULL,
					filedataid INT UNSIGNED NOT NULL,
					visible SMALLINT NOT NULL DEFAULT 1,
					counter INT UNSIGNED NOT NULL DEFAULT '0',
					posthash VARCHAR(32) NOT NULL DEFAULT '',
					filename VARCHAR(255) NOT NULL DEFAULT '',
					caption TEXT,
					reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
					settings MEDIUMTEXT,
				  	KEY attach_nodeid(nodeid),
				  	KEY attach_filedataid(filedataid)
				 ) ENGINE = " . $this->hightrafficengine . "",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_31()
	{
		if (!$this->tableExists('words'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'words'),
				"CREATE TABLE " . TABLE_PREFIX . "words (
					wordid int(11) NOT NULL AUTO_INCREMENT,
					word varchar(50) NOT NULL,
					PRIMARY KEY (wordid),
					UNIQUE KEY word (word)
				) ENGINE = " . $this->hightrafficengine . ";"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_32()
	{
		$created = false;
		for ($i=ord('a'); $i<=ord('z'); $i++)
		{
			if (!$this->tableExists("searchtowords_".chr($i)))
			{
				$this->run_query(
					sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "searchtowords_".chr($i)),
					"CREATE TABLE " . TABLE_PREFIX . "searchtowords_".chr($i)." (
						wordid int(11) NOT NULL,
						nodeid int(11) NOT NULL,
						is_title TINYINT(1) NOT NULL DEFAULT '0',
						score INT NOT NULL DEFAULT '0',
						position INT NOT NULL DEFAULT '0',
						UNIQUE (wordid, nodeid),
						UNIQUE (nodeid, wordid)
					) ENGINE = " . $this->hightrafficengine . ""
				);

				$created = true;
			}
		}
		if(!$created)
		{
			$this->skip_message();
		}
	}

	function step_33()
	{
		if (!$this->tableExists('searchtowords_other'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "searchtowords_other"),
				"CREATE TABLE " . TABLE_PREFIX . "searchtowords_other (
					wordid int(11) NOT NULL,
					nodeid int(11) NOT NULL,
					is_title TINYINT(1) NOT NULL DEFAULT '0',
					score INT NOT NULL DEFAULT '0',
					position INT NOT NULL DEFAULT '0',
					UNIQUE (wordid, nodeid),
					UNIQUE (nodeid, wordid)
				) ENGINE = " . $this->hightrafficengine . ""
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	// Poll upgrade
	/** Add polloptions table **/
	function step_34()
	{
		if (!$this->tableExists('polloption'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'polloption'),
				"
					CREATE TABLE " . TABLE_PREFIX . "polloption (
					polloptionid int(10) unsigned NOT NULL AUTO_INCREMENT,
					nodeid int(10) unsigned NOT NULL DEFAULT '0',
					title text,
					votes int(10) unsigned NOT NULL DEFAULT '0',
					voters text,
					PRIMARY KEY (polloptionid),
					KEY nodeid (nodeid)
    			) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** remove the auto-increment from pollid **/
	function step_35()
	{
		if ($this->field_exists('poll', 'pollid'))
		{
			// Poll table
			// Remove pollid's AUTO_INCREMENT
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'poll', 1, 7),
				"ALTER TABLE " . TABLE_PREFIX . "poll CHANGE pollid pollid INT UNSIGNED NOT NULL DEFAULT '0'"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**  Add index to pollid for better performance**/
	function step_36()
	{
		if ($this->field_exists('poll', 'pollid'))
		{
			$this->add_index(
				sprintf($this->phrase['core']['altering_x_table'], 'poll', 2, 7),
				'poll',
				'oldpollid',
				'pollid'
			);
		}
		else
		{
			$this->skip_message();
		}

	}

	/**  Change timeout to an INT **/
	function step_37()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'poll', 3, 7),
			"ALTER TABLE " . TABLE_PREFIX . "poll CHANGE timeout timeout INT UNSIGNED NOT NULL DEFAULT '0'"
		);
	}


	/**Drop poll table's primary key **/
	function step_38()
	{
		$polldescr = $this->db->query_first("SHOW COLUMNS FROM " . TABLE_PREFIX . "poll LIKE 'pollid'");

		if (!empty($polldescr['Key']) AND ($polldescr['Key'] == 'PRI'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'poll', 4, 7),
				"ALTER TABLE " . TABLE_PREFIX . "poll DROP PRIMARY KEY",
				MYSQL_ERROR_DROP_KEY_COLUMN_MISSING);
		}
		else
		{
			$this->skip_message();
		}

	}

	/** Add nodeid to the poll table **/
	function step_39()
	{
		if (!$this->field_exists('poll', 'nodeid'))
		{
			// Create nodeid field
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'poll', 5, 7),
				'poll',
				'nodeid',
				'INT',
				array(
					'extra' => ' AFTER pollid',
					'default' => null,
				)
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** change the votes field **/
	function step_40()
	{
		// Rename old voters field to votes
		if ($this->field_exists('poll', 'voters'))
		{
			// Drop old votes field
			$this->drop_field(
				sprintf($this->phrase['core']['altering_x_table'], 'poll', 7, 7),
				'poll',
				'votes'
			);

			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'poll', 1, 1),
				"ALTER TABLE " . TABLE_PREFIX . "poll CHANGE voters votes SMALLINT UNSIGNED NOT NULL DEFAULT '0'"
			);
		}
	}

	/**  set the timeout field to be seconds not days **/
	function step_41()
	{
		if ($this->field_exists('poll', 'dateline'))
		{
			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'poll'),
				'UPDATE ' . TABLE_PREFIX . 'poll SET timeout = dateline + timeout * 3600 * 24 WHERE timeout < 99999 AND timeout > 0');
		}
		else
		{
			$this->skip_message();
		}
	}


	/** Add polloptionid and nodeid field to pollvote table **/
	function step_42()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'pollvote', 1, 6),
			'pollvote',
			'nodeid',
			'INT',
			self::FIELD_DEFAULTS
		);
	}

	/** Add polloptionid and nodeid field to pollvote table**/
	function step_43()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'pollvote', 2, 6),
			'pollvote',
			'polloptionid',
			'INT',
			self::FIELD_DEFAULTS
		);

		// For step_150
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'pollvote', 3, 6),
			'pollvote',
			'polloptionid',
			array('polloptionid')
		);
	}

	/** Add index to pollvote table**/
	function step_44()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'pollvote', 4, 6),
			'pollvote',
			'nodeid',
			array('nodeid', 'userid', 'polloptionid')
		);
	}

	/** drop an unnecessary index **/
	function step_45()
	{
		// poll table
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'pollvote', 5, 6),
			'pollvote',
			'pollid'
		);

		// For step_150
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'pollvote', 6, 6),
			'pollvote',
			'pollid',
			array('pollid', 'voteoption')
		);
	}

	/** drop the votetype field in pollvote **/
	function step_46()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'pollvote', 1, 1),
			'pollvote',
			'votetype'
		);
	}

	function step_47()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'gallery'),
			"
				CREATE TABLE " . TABLE_PREFIX . "gallery (
				nodeid INT UNSIGNED NOT NULL,
				caption VARCHAR(512),
				PRIMARY KEY (nodeid)
				) ENGINE = " . $this->hightrafficengine . "
				",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
		//If this is the first time upgrade has been run we won't have Gallery type
		$contenttype = $this->db->query_first("
			SELECT contenttypeid FROM " . TABLE_PREFIX . "contenttype
			WHERE class = 'Gallery'");

		if (empty($contenttype) OR empty($contenttype['contenttypeid']))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'contenttype'),
				"INSERT INTO " . TABLE_PREFIX . "contenttype(class,
			packageid,	canplace,	cansearch,	cantag,	canattach,	isaggregator)
			SELECT 'Gallery', packageid,  '1', '0', '1', '1', '1' FROM " . TABLE_PREFIX . "package where class = 'vBForum';");

			$contenttype = $this->db->query_first("
				SELECT contenttypeid FROM " . TABLE_PREFIX . "contenttype
				WHERE class = 'Photo'");

			if (empty($contenttype) OR empty($contenttype['contenttypeid']))
			{
				$this->run_query(
					sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'contenttype'),
					"INSERT INTO " . TABLE_PREFIX . "contenttype(class,
				packageid,	canplace,	cansearch,	cantag,	canattach,	isaggregator)
				SELECT 'Photo', packageid,  '0', '0', '1', '1', '1' FROM " . TABLE_PREFIX . "package where class = 'vBForum';");
			}
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_48()
	{
		vB_Types::instance()->reloadTypes();
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'photo'),
			"
			CREATE TABLE " . TABLE_PREFIX . "photo (
			photoid  INT UNSIGNED NOT NULL AUTO_INCREMENT,
			nodeid INT UNSIGNED NOT NULL,
			filedataid INT UNSIGNED NOT NULL,
			caption VARCHAR(512),
			height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			style varchar(512),
			PRIMARY KEY (photoid),
			KEY (nodeid)
			) ENGINE = " . $this->hightrafficengine . "
		",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/** Update Infraction Data
	 *
	 **/
	function step_49()
	{
		if ($this->field_exists('infraction', 'nodeid'))
		{
			$this->skip_message();
		}
		else
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'infraction', 1, 2),
				'infraction',
				'nodeid',
				'INT',
				self::FIELD_DEFAULTS
			);

			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'infraction', 2, 2),
				'infraction',
				'channelid',
				'INT',
				self::FIELD_DEFAULTS
			);
		}
	}

	/**
	 * add field  publicview in filedata table
	 *
	 */
	function step_50()
	{
		if ($this->tableExists('filedata') AND !$this->field_exists('filedata', 'publicview'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'filedata ', 1, 1),
				'filedata',
				'publicview',
				'smallint',
				self::FIELD_DEFAULTS
			);
		}
		else
		{
			$this->skip_message();
		}

	}

	/** Create initial screen layouts
	 *
	 */
	function step_51()
	{
		$screenLayOutRecords = $this->db->query_first("
		SELECT screenlayoutid FROM " . TABLE_PREFIX . "screenlayout");

		if (empty($screenLayOutRecords) OR empty($screenLayOutRecords['screenlayoutid']))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'screenlayout'),
				"INSERT INTO " . TABLE_PREFIX . "screenlayout
			(screenlayoutid, varname, title, displayorder, columncount, template, admintemplate)
			VALUES
			(1, '100', '100', 4, 1, 'screenlayout_1', 'admin_screenlayout_1'),
			(2, '70-30', '70/30', 1, 2, 'screenlayout_2', 'admin_screenlayout_2'),
			(3, '50-50', '50/50', 2, 2, 'screenlayout_3', 'admin_screenlayout_3'),
			(4, '30-70', '30/70', 3, 2, 'screenlayout_4', 'admin_screenlayout_4');
			"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Fixing the contenttype table
	 */
	function step_52()
	{
		$not_searchable = array(
			"Post",
			"Thread",
			"Forum",
			"Announcement",
			"SocialGroupMessage",
			"SocialGroupDiscussion",
			"SocialGroup",
			"Album",
			"Picture",
			"PictureComment",
			"VisitorMessage",
			"User",
			"Event",
			"Calendar",
			"BlogEntry",
			"Channel",
			"BlogComment"
		);
		$searchable = array(
			"Text",
			"Attach",
			"Poll",
			"Photo",
			"Gallery"
		);
		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "contenttype"),
			"UPDATE " . TABLE_PREFIX  . "contenttype SET cansearch = '0' WHERE class IN (\"" . implode('","',$not_searchable) . "\");");

		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "contenttype"),
			"UPDATE " . TABLE_PREFIX  . "contenttype SET cansearch = '1' WHERE class IN (\"" . implode('","',$searchable) . "\") AND packageid = 1;");
	}

	/***	Adding who is online fields to session table*/
	function step_53()
	{
		// Clear all sessions first, otherwise we can fail with "table full" error.
		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], 'session'),
			"TRUNCATE TABLE " . TABLE_PREFIX . "session"
		);

		if ( !$this->field_exists('session', 'wol'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'session', 1, 5),
				'session',
				'wol',
				'char',
				array('length' => 255)
			);
		}
		else
		{
			$this->skip_message();
		}

		if ( !$this->field_exists('session', 'nodeid'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'session', 2, 5),
				'session',
				'nodeid',
				'int',
				self::FIELD_DEFAULTS
			);
			$this->add_index(
				sprintf($this->phrase['core']['altering_x_table'], 'session', 3, 5),
				'session',
				'nodeid',
				'nodeid'
			);
		}
		else
		{
			$this->skip_message();
		}

		if ( !$this->field_exists('session', 'pageid'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'session', 4, 5),
				'session',
				'pageid',
				'int',
				self::FIELD_DEFAULTS
			);
			$this->add_index(
				sprintf($this->phrase['core']['altering_x_table'], 'session', 5, 5),
				'session',
				'pageid',
				'pageid'
			);
		}

		else
		{
			$this->skip_message();
		}

	}

	/***	Setting inlist to the node table*/
	function step_54()
	{
		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'),
			"UPDATE " . TABLE_PREFIX . "node AS node INNER JOIN " . TABLE_PREFIX . "contenttype AS t ON t.contenttypeid = node.contenttypeid
		SET node.inlist = 0 WHERE t.canplace = '0';");
	}

	/** Creating site table */
	function step_55()
	{
		if (!$this->tableExists('site'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'site'),
				"
				CREATE TABLE " . TABLE_PREFIX . "site (
					siteid INT NOT NULL AUTO_INCREMENT,
					title VARCHAR(100) NOT NULL,
					headernavbar MEDIUMTEXT NULL,
					footernavbar MEDIUMTEXT NULL,
					PRIMARY KEY (siteid)
				) ENGINE = " . $this->hightrafficengine . "
			",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** If this is a 3.X blog there's some changes we need to make. **/

	/** We need to handle any blog table changes **/
	function step_56()
	{
		if (!$this->tableExists('blog'))
		{
			$this->skip_message();
		}
		else
		{
			if (! $this->field_exists('blog', 'categories'))
			{
				$this->add_field(
					sprintf($this->phrase['core']['altering_x_table'], 'blog', 1, 3),
					'blog',
					'categories',
					'mediumtext',
					self::FIELD_DEFAULTS
				);
			}
			else
			{
				$this->skip_message();
			}


			if (! $this->field_exists('blog', 'taglist'))
			{
				$this->add_field(
					sprintf($this->phrase['core']['altering_x_table'], 'blog', 2, 3),
					'blog',
					'taglist',
					'mediumtext',
					self::FIELD_DEFAULTS
				);
			}
			else
			{
				$this->skip_message();
			}

			if (! $this->field_exists('blog', 'postedby_userid'))
			{
				$this->add_field(
					sprintf($this->phrase['core']['altering_x_table'], 'blog', 3, 3),
					'blog',
					'postedby_userid',
					'int',
					self::FIELD_DEFAULTS
				);
			}
			else
			{
				$this->skip_message();
			}

		}
	}

	/***	Adding htmlstate to the cms_article table (a vB4 table).
			Apparently this is to avoid problems with the CMS import later on */
	function step_57()
	{
		if (isset($this->registry->products['vbcms']) AND $this->registry->products['vbcms'])
		{
			if ($this->tableExists('cms_article') AND !$this->field_exists('cms_article', 'htmlstate'))
			{
				$this->add_field(
					sprintf($this->phrase['core']['altering_x_table'], 'cms_article', 1, 1),
					'cms_article',
					'htmlstate',
					'enum',
					array('attributes' => "('off', 'on', 'on_nl2br')", 'null' => false, 'default' => 'on_nl2br')
				);

			}
			else
			{
				$this->skip_message();
			}
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_58()
	{
		$this->skip_message();
	}

	function step_59()
	{
		// updating searchlog
		if (!$this->field_exists('searchlog', 'json'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'searchlog ', 1, 1),
				'searchlog',
				'json',
				'text',
				self::FIELD_DEFAULTS
			);
			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'searchlog'));
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_60()
	{
		// updating searchlog
		if (!$this->field_exists('searchlog', 'results_count'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'searchlog ', 1, 1),
				'searchlog',
				'results_count',
				'text',
				self::FIELD_DEFAULTS
			);

			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'searchlog'));
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_61()
	{
		// updating searchlog
		if ($this->field_exists('searchlog', 'criteria'))
		{
			$this->drop_field(
				sprintf($this->phrase['core']['altering_x_table'], 'searchlog ', 1, 1),
				'searchlog',
				'criteria'
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/*** create the cacheeventlog table
	 **/
	function step_62()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'cacheevent_log'),
			"
			CREATE TABLE " . TABLE_PREFIX . "cacheevent_log (
				event VARBINARY(50) NOT NULL,
				eventtime INT UNSIGNED NOT NULL,
				PRIMARY KEY (event)
			) ENGINE = " . $this->hightrafficengine . "
			",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}


	/** Adding systemgroupid to usergroup table **/
	function step_63()
	{
		if (!$this->field_exists('usergroup', 'systemgroupid'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 1),
				'usergroup',
				'systemgroupid',
				'SMALLINT',
				array('attributes' => vB_Upgrade_Version::FIELD_DEFAULTS)
			);
		}

		// we need this step to be run before 155 cause we set sitebuilder permission based on systemgroupid there
		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'usergroup'),
			"UPDATE " . TABLE_PREFIX . "usergroup
			SET systemgroupid = usergroupid
			WHERE usergroupid <= 7"
		);
	}

	/** Make sure we have the six system groups */
	function step_64()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'permission'));
		$this->createSystemGroups();
	}

	function step_65()
	{
		$this->skip_message();
	}

	public function step_66()
	{
		$this->skip_message();
	}

	public function step_67()
	{
		$this->skip_message();
	}

	public function step_68()
	{
		$this->skip_message();
	}


	public function step_69()
	{
		$this->skip_message();
	}

	function step_70()
	{
		if (!$this->tableExists('nodevote'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'nodevote'),
				"
				CREATE TABLE " . TABLE_PREFIX . "nodevote (
					nodevoteid INT UNSIGNED NOT NULL AUTO_INCREMENT,
					nodeid INT UNSIGNED NOT NULL DEFAULT '0',
					userid INT UNSIGNED NULL DEFAULT NULL,
					votedate INT UNSIGNED NOT NULL DEFAULT '0',
					PRIMARY KEY (nodevoteid),
					UNIQUE KEY nodeid (nodeid, userid),
					KEY userid (userid)
				) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_71()
	{
		$this->skip_message();
	}

	/*** Create the groupintopic table
	 */
	public function step_72()
	{
		if (!$this->tableExists('groupintopic'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'groupintopic'),
				"
			CREATE TABLE " . TABLE_PREFIX . "groupintopic (
				userid INT UNSIGNED NOT NULL,
				groupid INT UNSIGNED NOT NULL,
				nodeid INT UNSIGNED NOT NULL,
				UNIQUE KEY (userid, groupid, nodeid),
				KEY (userid)
			) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** create nodestats table **/
	function step_73()
	{
		if (!$this->tableExists('nodestats'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'nodestats'),
				"
			CREATE TABLE " . TABLE_PREFIX . "nodestats (
				nodestatsid int(10) NOT NULL AUTO_INCREMENT,
				nodeid int(10) unsigned NOT NULL,
				dateline int(10) unsigned NOT NULL,
				replies int(10) unsigned NOT NULL,
				visitors int(10) unsigned NOT NULL,
				PRIMARY KEY (nodestatsid),
				UNIQUE KEY nodeid (nodeid, dateline)
			) ENGINE = " . $this->hightrafficengine . "",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** create nodevisits table **/
	function step_74()
	{
		if (!$this->tableExists('nodevisits'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'nodevisits'),
				"
			CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "nodevisits (
				nodevisitid int(10) NOT NULL AUTO_INCREMENT,
				nodeid int(10) unsigned NOT NULL,
				visitorid int(10) unsigned NOT NULL DEFAULT '0',
				dateline int(10) unsigned NOT NULL,
				totalcount int(10) unsigned,
				PRIMARY KEY (nodevisitid),
				UNIQUE KEY nodeid (nodeid, visitorid, dateline)
			) ENGINE = " . $this->hightrafficengine . "",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** create nodestatsmax table **/
	function step_75()
	{
		if (!$this->tableExists('nodestatreplies'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'nodestatreplies'),
				"
			CREATE TABLE " . TABLE_PREFIX . "nodestatreplies (
			nodeid int(10) unsigned NOT NULL PRIMARY KEY,
			replies int(10) unsigned NOT NULL
			) ENGINE = " . $this->hightrafficengine . "",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Create noderead table
	 */
	function step_76()
	{
		if (!$this->tableExists('noderead'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'noderead'),
				"
				CREATE TABLE " . TABLE_PREFIX . "noderead (
					userid int(10) unsigned NOT NULL default '0',
					nodeid int(10) unsigned NOT NULL default '0',
					readtime int(10) unsigned NOT NULL default '0',
					PRIMARY KEY  (userid, nodeid),
					KEY readtime (readtime)
				) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/* Get paymenttransaction table */
	function step_77()
	{
		if (!$this->tableExists('paymenttransaction'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'paymenttransaction'),
				"
				CREATE TABLE " . TABLE_PREFIX . "paymenttransaction (
					paymenttransactionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
					paymentinfoid INT UNSIGNED NOT NULL DEFAULT '0',
					transactionid VARCHAR(250) NOT NULL DEFAULT '',
					state SMALLINT UNSIGNED NOT NULL DEFAULT '0',
					amount DOUBLE UNSIGNED NOT NULL DEFAULT '0',
					currency VARCHAR(5) NOT NULL DEFAULT '',
					dateline INT UNSIGNED NOT NULL DEFAULT '0',
					paymentapiid INT UNSIGNED NOT NULL DEFAULT '0',
					request MEDIUMTEXT,
					reversed INT UNSIGNED NOT NULL DEFAULT '0',
					PRIMARY KEY (paymenttransactionid),
					KEY dateline (dateline),
					KEY transactionid (transactionid),
					KEY paymentapiid (paymentapiid)
				) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	public function step_78()
	{
		if (!$this->tableExists('privatemessage'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'privatemessage'),
				"CREATE TABLE " . TABLE_PREFIX . "privatemessage (
				nodeid INT UNSIGNED NOT NULL,
				msgtype enum('message','notification','request') NOT NULL default 'message',
				about enum('vote', 'vote_reply', 'rate', 'reply', 'follow', 'vm', 'comment' ),
				aboutid INT,
				PRIMARY KEY (nodeid)
			) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}

	}

	//Add sentto table for private messages
	public function step_79()
	{
		if (!$this->tableExists('sentto'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'sentto'),
				"CREATE TABLE " . TABLE_PREFIX . "sentto (
				nodeid INT NOT NULL,
				userid INT NOT NULL,
				folderid INT NOT NULL,
				deleted SMALLINT NOT NULL DEFAULT 0,
				msgread SMALLINT NOT NULL DEFAULT 0,
				PRIMARY KEY(nodeid, userid, folderid),
				KEY (nodeid),
				KEY (userid),
				KEY (folderid)
			) ENGINE = " . $this->hightrafficengine . "",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	//Add messagefolder table for private messages
	public function step_80()
	{
		if (!$this->tableExists('messagefolder'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'messagefolder'),
				"CREATE TABLE " . TABLE_PREFIX . "messagefolder (
				folderid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				userid INT UNSIGNED NOT NULL,
				title varchar(512),
				titlephrase varchar(250),
				PRIMARY KEY (folderid),
				KEY (userid)
				) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	//Add google provider to user table
	public function step_81()
	{
		if (!$this->field_exists('user', 'google'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'user ', 1, 1),
				'user',
				'google',
				'char',
				array('length' => 32, 'default' => '', 'extra' => 'after skype')
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/*** Add the nodeid to the moderators table */
	function step_82()
	{
		if (!$this->field_exists('moderator', 'nodeid'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'moderator', 1, 3),
				'moderator',
				'nodeid',
				'INT',
				self::FIELD_DEFAULTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}


	/*** Add the nodeid to the moderatorlog table */
	function step_83()
	{
		if (!$this->field_exists('moderatorlog', 'nodeid'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'moderatorlog', 1, 3),
				'moderatorlog',
				'nodeid',
				'INT',
				self::FIELD_DEFAULTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/*** Add the nodeid to the access table */
	function step_84()
	{
		if (!$this->field_exists('access', 'nodeid'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'access', 1, 3),
				'access',
				'nodeid',
				'INT',
				self::FIELD_DEFAULTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	// Update old reputation table
	function step_85()
	{
		if (!$this->field_exists('reputation', 'nodeid'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'reputation', 1, 1),
				'reputation',
				'nodeid',
				'INT',
				self::FIELD_DEFAULTS
			);
		}
		else
		{
			$this->skip_message();
		}


	}

	public function step_86()
	{
		$this->skip_message();
	}

	public function step_87()
	{
		$this->skip_message();
	}

	public function step_88()
	{
		$this->skip_message();
	}

	//For handling private message deletion
	public function step_89()
	{
		if (!$this->field_exists('privatemessage', 'deleted'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'privatemessage', 1, 1),
				'privatemessage',
				'deleted',
				'INT',
				self::FIELD_DEFAULTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	public function step_90()
	{
		$this->skip_message();
	}

	public function step_91()
	{
		$this->skip_message();
	}

	function step_92()
	{
		$this->skip_message();
	}

	function step_93()
	{
		$this->skip_message();
	}

	public function step_94()
	{
		$this->skip_message();
	}

	public function step_95()
	{
		$this->skip_message();
	}

	public function step_96()
	{
		$this->skip_message();
	}

	public function step_97()
	{
		$this->skip_message();
	}

	public function step_98()
	{
		$this->skip_message();
	}

	public function step_99()
	{
		$this->skip_message();
	}

	public function step_100()
	{
		$this->skip_message();
	}

	public function step_101()
	{
		$this->skip_message();
	}

	public function step_102()
	{
		if (!$this->tableExists('widgetchannelconfig'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'widgetchannelconfig'),
				"
				CREATE TABLE " . TABLE_PREFIX . "widgetchannelconfig (
				  widgetinstanceid int(10) unsigned NOT NULL,
				  nodeid int(10) unsigned NOT NULL,
				  channelconfig blob NOT NULL,
				  UNIQUE KEY widgetinstanceid (widgetinstanceid,nodeid)
				) ENGINE = " . $this->hightrafficengine . "
				",
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_103()
	{
		$this->skip_message();
	}

	/** Adding blog phrase type for language table **/
	public function step_104()
	{
		if (!$this->field_exists('language', 'phrasegroup_vb5blog'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'language', 1, 1),
				'language',
				'phrasegroup_vb5blog',
				'mediumtext',
				self::FIELD_DEFAULTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	public function step_105()
	{
		$this->skip_message();
	}

	public function step_106()
	{
		$this->skip_message();
	}

	public function step_107()
	{
		$this->skip_message();
	}

	public function step_108()
	{
		$this->skip_message();
	}

	public function step_109()
	{
		$this->skip_message();
	}

	public function step_110()
	{
		$this->skip_message();
	}

	public function step_111()
	{
		$this->skip_message();
	}

	/** adding ipv6 fields to strike table **/
	public function step_112()
	{
		if (!$this->field_exists('strikes', 'ip_4'))
		{
			// add new IP fields for IPv4-mapped IPv6 addresses
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'strikes', 1, 6),
				'strikes',
				'ip_4',
				'INT UNSIGNED',
				array(
					'null' => false,
					'default' => 0
				)
			);
		}
		else
		{
			$this->skip_message();
		}
	}
	/** adding ipv6 fields to strike table **/
	public function step_113()
	{
		if (!$this->field_exists('strikes', 'ip_3'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'strikes', 2, 6),
				'strikes',
				'ip_3',
				'INT UNSIGNED',
				array(
					'null' => false,
					'default' => 0
				)
			);
		}
		else
		{
			$this->skip_message();
		}
	}
	/** adding ipv6 fields to strike table **/
	public function step_114()
	{
		if (!$this->field_exists('strikes', 'ip_2'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'strikes', 3, 6),
				'strikes',
				'ip_2',
				'INT UNSIGNED',
				array(
					'null' => false,
					'default' => 0
				)
			);
		}
		else
		{
			$this->skip_message();
		}
	}
	/** adding ipv6 fields to strike table **/
	public function step_115()
	{
		if (!$this->field_exists('strikes', 'ip_1'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'strikes', 4, 6),
				'strikes',
				'ip_1',
				'INT UNSIGNED',
				array(
					'null' => false,
					'default' => 0
				)
			);

			// add indexes
			$this->add_index(
				sprintf($this->phrase['core']['altering_x_table'], 'strikes', 5, 6),
				'strikes',
				'ip',
				array('ip_4', 'ip_3', 'ip_2', 'ip_1')
			);

		}
		else
		{
			$this->skip_message();
		}
	}
	/** adding ipv6 fields to strike table **/
	public function step_116()
	{
		// increase length for IPv6 addresses
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'strikes', 6, 6),
			"ALTER TABLE " . TABLE_PREFIX . "strikes MODIFY COLUMN strikeip char(39) NOT NULL"
		);
	}

	// Add ispublic field
	public function step_117()
	{
		if (!$this->field_exists('setting', 'ispublic'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'setting', 1, 2),
				'setting',
				'ispublic',
				'SMALLINT',
				self::FIELD_DEFAULTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	// Add ispublic index
	public function step_118()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'setting', 1, 1),
			'setting',
			'ispublic',
			'ispublic'
		);
	}

	public function step_119()
	{
		if (!$this->field_exists('moderatorlog', 'nodetitle'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'moderatorlog', 1, 1),
				'moderatorlog',
				'nodetitle',
				'VARCHAR',
				array('length' => 256, 'attributes' => self::FIELD_DEFAULTS)
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	public function step_120()
	{
		if (!$this->field_exists('customavatar', 'extension'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'customavatar', 1, 1),
				'customavatar',
				'extension',
				'VARCHAR',
				array('length' => 10, 'null' => false, 'default' => '')
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	public function step_121()
	{
		if (!$this->field_exists('user', 'status'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'user ', 1, 3),
				'user',
				'status',
				'mediumtext',
				array('null' => true, 'extra' => 'after google')
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	public function step_122()
	{
		if (!$this->field_exists('user', 'notification_options'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'user ', 2, 3),
				'user',
				'notification_options',
				'int',
				array('attributes' => 'UNSIGNED', 'default' => '134217722', 'extra' => 'after options')
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_123()
	{
		if (!$this->field_exists('user', 'privacy_options'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'user ', 3, 3),
				'user',
				'privacy_options',
				'mediumtext',
				array('null' => true, 'extra' => 'after options')
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	public function step_124()
	{
		$this->skip_message();
	}

	public function step_125()
	{
		$this->skip_message();
	}

	public function step_126()
	{
		$this->skip_message();
	}

	public function step_127()
	{
		/* Need to save these for later,
		as the steps below wipe them out */
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'adminutil'),
			"INSERT INTO " . TABLE_PREFIX . "adminutil
			(title,	text)
			SELECT varname, value
			FROM " . TABLE_PREFIX . "setting
			WHERE varname IN ('as_expire', 'as_perpage') ");
	}

	/** Add the private message content type if needed **/
	public function step_128()
	{
		$contenttype = $this->db->query_first("
			SELECT contenttypeid FROM " . TABLE_PREFIX . "contenttype
			WHERE class = 'PrivateMessage'");

		if (empty($contenttype) OR empty($contenttype['contenttypeid']))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'contenttype'),
				"INSERT INTO " . TABLE_PREFIX . "contenttype
				(class,	packageid,	canplace,	cansearch,	cantag,	canattach,	isaggregator)
			SELECT 'PrivateMessage', packageid, '0', '1', '0', '0', '0'  FROM " . TABLE_PREFIX . "package WHERE class = 'vBForum';");
		}
		else
		{
			$this->skip_message();
		}
	}

	/** drop unneeded indices.
	 **/
	public function step_129()
	{
		// Drop old indexes
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 2, 3),
			'reputation',
			'whoadded_postid'
		);

		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 3, 3),
			'reputation',
			'multi'
		);
	}

	/** We need to import the initial information from the xml files. This is part of final upgrade **/
	function step_130()
	{
		require_once(DIR . "/install/includes/class_upgrade_final.php");
		$finalUpgrader = new vB_Upgrade_final($this->registry, $this->phrase,$this->maxversion);
		$finalUpgrader->step_1();
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widget'));
	}

	/** We need to import the initial information from the xml files. This is part of final upgrade **/
	function step_131()
	{
		vB_Upgrade::createAdminSession();
		require_once(DIR . "/install/includes/class_upgrade_final.php");
		$finalUpgrader = new vB_Upgrade_final($this->registry, $this->phrase,$this->maxversion);
		$finalUpgrader->step_2();
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'pagetemplate'));
	}

	/* Add editlog.nodeid -- this will get non first posts.
	*/
	function step_132()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'editlog', 2, 7),
			'editlog',
			'nodeid',
			'int',
			self::FIELD_DEFAULTS
		);
	}


	/** We need to import the initial information from the xml files. This is part of final upgrade **/
	function step_133()
	{
		vB_Upgrade::createAdminSession();
		require_once(DIR . "/install/includes/class_upgrade_final.php");
		$finalUpgrader = new vB_Upgrade_final($this->registry, $this->phrase,$this->maxversion);
		$finalUpgrader->step_3();
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'page'));
	}

	/** We need to import the initial information from the xml files. This is part of final upgrade **/
	function step_134()
	{
		vB_Upgrade::createAdminSession();
		require_once(DIR . "/install/includes/class_upgrade_final.php");
		$finalUpgrader = new vB_Upgrade_final($this->registry, $this->phrase,$this->maxversion);
		$finalUpgrader->step_4();
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetinstance'));
	}

	/** We need to import the initial information from the xml files. This is part of final upgrade **/
	function step_135()
	{
		vB_Upgrade::createAdminSession();
		require_once(DIR . "/install/includes/class_upgrade_final.php");
		$finalUpgrader = new vB_Upgrade_final($this->registry, $this->phrase,$this->maxversion);
		$finalUpgrader->step_5();
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'routenew'));
	}

	/** We need to import the initial information from the xml files. This is part of final upgrade **/
	function step_136()
	{
		vB_Upgrade::createAdminSession();
		require_once(DIR . "/install/includes/class_upgrade_final.php");
		$finalUpgrader = new vB_Upgrade_final($this->registry, $this->phrase,$this->maxversion);
		$finalUpgrader->step_6();
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'channel'));
	}

	/** Convert any vB3 API Settings to vbulletin product **/
	function step_137()
	{
		$query = "
			UPDATE " . TABLE_PREFIX . "setting
			SET product = 'vbulletin' WHERE product = 'vbapi'
			";

		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], 'setting'), $query);
	}

	/**
	 * Adding routes for legacy URLs. This needs to be done before removing some deprecated settings.
	 */
	function step_138()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], 'routenew'));
		$legacyUrls = array('thread', 'member', 'forum', 'usercp', 'post', 'vbcms',
			'forumhome', 'poll', 'subscription', 'blog', 'entry', 'bloghome', 'threadprint',
			'archive');

		$db = vB::getDbAssertor();

		foreach ($legacyUrls as $class)
		{
			$className = 'vB5_Route_Legacy_' . ucfirst($class);

			if (class_exists($className))
			{
				try
				{
					$route = new $className;
					$prefix = $route->getPrefix();
					$regex = $route->getRegex();
					$arguments = $route->getArguments();

					// prefix and regex will be empty if we are using rewrite setting
					if (!empty($prefix) AND !empty($regex))
					{
						$db->delete('routenew', array('class' => $className));

						$route = array(
							'prefix'	=> $prefix,
							'regex'		=> $regex,
							'class'		=> $className,
							'arguments'	=> serialize($arguments),
						);
						$route['guid'] = vB_Xml_Export_Route::createGUID($route);
						$db->insert('routenew', $route);
					}
				}
				catch (Exception $e) {

				}
			}
		}
	}

	/** We need to import the initial information from the xml files. This is part of final upgrade.
	 * At this point, any removed setting will be removed from setting table and from datastore**/
	function step_139()
	{
		vB_Upgrade::createAdminSession();
		require_once(DIR . "/install/includes/class_upgrade_final.php");
		$finalUpgrader = new vB_Upgrade_final($this->registry, $this->phrase,$this->maxversion);
		$finalUpgrader->step_7();
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'setting'));
	}

	function step_140()
	{
		$this->skip_message();
	}

	/**
	 * load top-level forums from vb4. These are now channels.
	 *
	 */
	 function step_141()
	 {
		$channelTypeId = vB_Types::instance()->getContentTypeID('vBForum_Channel');
		$forumTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Forum');
		vB_Upgrade::createAdminSession();
		$query =
			"/*** now forums  top level  ***/
			SELECT f.title, f.title_clean, f.description, f.forumid, f.displayorder, f.options,
			IF(isnull(lp.userid),0,lp.userid) AS lastauthorid,
			IF(isnull(lp.username),'',lp.username) AS lastcontentauthor
			FROM " . TABLE_PREFIX . "forum AS f
			LEFT JOIN " . TABLE_PREFIX . "post AS lp ON lp.postid = f.lastpostid
			LEFT JOIN " . TABLE_PREFIX . "node AS existing ON existing.oldid = f.forumid AND existing.oldcontenttypeid = $forumTypeId
			WHERE f.parentid < 1 AND existing.nodeid IS NULL";
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'));
		$needed = $this->db->query_read($query);

		if ($needed)
		{
			$parentid = vB::getDbAssertor()->getField('vBForum:channel', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array('guid' => vB_Channel::DEFAULT_FORUM_PARENT)
			));
			$channelLib = vB_Library::instance('content_channel');
			while($channel =  $this->db->fetch_array($needed))
			{
				$channel['oldid'] = $channel['forumid'];
				$channel['oldcontenttypeid'] = $forumTypeId;
				$channel['parentid'] = $parentid;
				$channel['htmltitle'] = $channel['title_clean'];
				$channel['urlident'] = vB_Library::instance('content_channel')->getUniqueUrlIdent($channel['title']);
				unset($channel['forumid']);
				/* Forum options bit 1 is the forum active flag.
				If the forum is not active, we set its display order to zero.
				This is a necessary fudge atm because in vB5 there is no way to set/reset the active status,
				but the forum should be hidden on upgrade as non active forums did not display in vB3 or vB4 */
				if (($channel['options'] & 1) == 0)
				{
					$channel['displayorder'] = 0;
				}
				$response = $channelLib->add($channel, array('skipNotifications' => true, 'skipFloodCheck' => true, 'skipDupCheck' => true));
			}
		}
	 }

	/**
	 * load remaining forums from vb4. These are also channels.
	 *
	 */
	function step_142($data = NULL)
	{
		//first we need to channel content type id's.
		$channelTypeId = vB_Types::instance()->getContentTypeID('vBForum_Channel');
		$forumTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Forum');

		$process = 200;
		$startat = intval($data['startat']);
		$checkArray = $this->db->query_first("
			SELECT f.forumid
			FROM " . TABLE_PREFIX . "forum AS f
			LEFT JOIN " . TABLE_PREFIX . "node AS existing ON existing.oldid = f.forumid AND existing.oldcontenttypeid = $forumTypeId
			JOIN " . TABLE_PREFIX . "node AS node ON node.oldid = f.parentid AND node.oldcontenttypeid = $forumTypeId
			WHERE f.parentid > 0 AND existing.nodeid IS NULL LIMIT 1
		");

		if (empty($checkArray) AND !startat)
		{
			$this->skip_message();
			return;
		}
		else if (empty($checkArray))
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}
		else if (!$startat)
		{
			// @TODO add its own phrase
			$this->show_message(sprintf($this->phrase['version']['500a1']['importing_x_records'], 'forum'));
			$this->show_message(sprintf($this->phrase['core']['processing_records_x'], $process));
			return array('startat' => 1); // Go back and actually process
		}

		$query = "/*** and forums below root ***/
		SELECT node.nodeid AS parentid, f.title, f.title_clean, f.description, f.forumid, f.displayorder, f.options,
		IF(isnull(lp.userid),0,lp.userid) AS lastauthorid,
		IF(isnull(lp.username),'',lp.username) AS lastcontentauthor
		FROM " . TABLE_PREFIX . "forum AS f
		LEFT JOIN " . TABLE_PREFIX . "post AS lp ON lp.postid = f.lastpostid
		LEFT JOIN " . TABLE_PREFIX . "node AS existing ON existing.oldid = f.forumid AND existing.oldcontenttypeid = $forumTypeId
		JOIN " . TABLE_PREFIX . "node AS node ON node.oldid = f.parentid AND node.oldcontenttypeid = $forumTypeId
		WHERE f.parentid > 0 AND existing.nodeid IS NULL
		LIMIT $process";
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'));
		$needed = $this->db->query_read($query);

		if (!empty($needed))
		{
			vB_Upgrade::createAdminSession();
			$channelLib = vB_Library::instance('content_channel');
			while($channel =  $this->db->fetch_array($needed))
			{
				$channel['oldid'] = $channel['forumid'];
				$channel['oldcontenttypeid'] = $forumTypeId;
				$channel['htmltitle'] = $channel['title_clean'];
				$channel['urlident'] = vB_Library::instance('content_channel')->getUniqueUrlIdent($channel['title']);
				unset($channel['forumid']);
				/* See explanation in previous step. */
				if (($channel['options'] & 1) == 0)
				{
					$channel['displayorder'] = 0;
				}
				$channelLib->add($channel, array('skipNotifications' => true, 'skipFloodCheck' => true, 'skipDupCheck' => true));
			}
		}

		$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $process));

		return array('startat' => ($startat + 1));
	}

	function step_143()
	{
		/*
		This step removes duff thread records that have no first postid.
		These threads have no posts, so adding them would be pointless and cause issues down the line.
		Exclude the threads with open == 10 which are thread redirects
		*/
		$query = "
			DELETE FROM " . TABLE_PREFIX . "thread
			WHERE firstpostid = 0 AND open <> 10
		";

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], 'thread'), $query
		);
	}

	/** Make sure we have attach type**/
	function step_144()
	{
		$contenttype = $this->db->query_first("
			SELECT contenttypeid FROM " . TABLE_PREFIX . "contenttype
			WHERE class = 'Attach'");

		if (empty($contenttype) OR empty($contenttype['contenttypeid']))
		{
			$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'contenttype'),
			"INSERT INTO " . TABLE_PREFIX . "contenttype(class,
			packageid,	canplace,	cansearch,	cantag,	canattach,	isaggregator)
			SELECT 'Attach', packageid, '0','1', '0', '0', '0' FROM " . TABLE_PREFIX . "package where class = 'vBForum';");

		}
		else
		{
			$this->skip_message();
		}
	}

	//Now we can import threads, which come to vB5 as starters
	function step_145($data = NULL)
	{
		vB_Types::instance()->reloadTypes();
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'));
		$forumTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Forum');
		$threadTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Thread');
		$process = 500; /* In my testing, larger cycles get bogged down in temporary table copying -freddie */
		$startat = intval($data['startat']);
		//First see if we need to do something. Maybe we're O.K.

		if (!empty($data['maxvB4']))
		{
			$maxvB4 = $data['maxvB4'];
		}
		else
		{
			$maxvB4 = $this->db->query_first("SELECT MAX(threadid) AS maxid FROM " . TABLE_PREFIX . "post");
			$maxvB4 = $maxvB4['maxid'];

			//If we don't have any posts, we're done.
			if (intval($maxvB4) < 1)
			{
				$this->skip_message();
				return;
			}

		}
		$maxvB5 = $this->db->query_first("SELECT MAX(oldid) AS maxid FROM " . TABLE_PREFIX . "node WHERE oldcontenttypeid = $threadTypeId");

		if (!empty($maxvB5) AND !empty($maxvB5['maxid']))
		{
			$maxvB5 = $maxvB5['maxid'];
		}
		else
		{
			$maxvB5 = 0;
		}

		$maxvB5 = max($startat, $maxvB5);
		if (($maxvB4 <= $maxvB5) AND !$startat)
		{
			$this->skip_message();
			return;
		}
		else if ($maxvB4 <= $maxvB5)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}

		$textTypeId = vB_Types::instance()->getContentTypeID('vBForum_Text');

		/***
		 * 	Thread starters. We need to insert the node records, text records, and closure records
		 *
		 *	visible = 0 means unapproved/moderated
		 *	visible = 1 normal/visible
		 *	visible = 2 deleted.
		 * 	***/
		$query = "
		INSERT INTO " . TABLE_PREFIX . "node(contenttypeid, parentid, routeid, title, htmltitle, userid, authorname,
			oldid, oldcontenttypeid, created, ipaddress,
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
		SELECT $textTypeId, node.nodeid, node.routeid, th.title, th.title, th.postuserid, th.postusername,
			th.threadid, $threadTypeId, th.dateline, p.ipaddress,
			1, 1,
			th.dateline,
			(CASE WHEN th.visible < 2 THEN 0 ELSE 1 END),
			(CASE WHEN th.visible < 2 THEN 1 ELSE 0 END),
			th.open,
			(CASE th.visible WHEN 0 THEN 0 ELSE 1 END),
			(CASE th.visible WHEN 0 THEN 0 ELSE 1 END),
			th.replycount,th.replycount, th.hiddencount, th.hiddencount, th.lastpost,
			lp.username, lp.userid, th.prefixid, th.iconid, th.sticky,
			dl.userid, dl.reason
		FROM " . TABLE_PREFIX . "thread AS th
		INNER JOIN " . TABLE_PREFIX . "post AS p ON p.postid = th.firstpostid
		INNER JOIN " . TABLE_PREFIX . "node AS node ON node.oldid = th.forumid AND node.oldcontenttypeid = $forumTypeId
		LEFT JOIN " . TABLE_PREFIX . "post AS lp ON lp.postid = th.lastpostid
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS dl ON dl.primaryid = th.threadid AND dl.type = 'thread'
		WHERE th.threadid > $maxvB5 AND th.threadid < ($maxvB5 + $process) ORDER BY th.threadid
		";
		$result = $this->db->query_write($query);

		//read the new nodes for processing
		$query = "SELECT nodeid, title, oldid
			FROM " . TABLE_PREFIX . "node
			WHERE oldcontenttypeid = $threadTypeId
			AND oldid > $maxvB5 AND oldid < ($maxvB5 + $process)
		";
		$nodes = $this->db->query_read($query);

		$records = $this->db->num_rows($nodes);

		/* Only bother with the rest of the processing if we
		actually added some more nodes, otherwise just move on. */
		if ($records)
		{
			$sql = '';
			while ($node = $this->db->fetch_array($nodes))
			{
				$ident = vB_String::getUrlIdent($node['title']);
				$sql .= "WHEN " . intval($node['nodeid']) . " THEN '" . $this->db->escape_string($ident) . "' \n";
			}

			//Set the urlident values
			$query = "UPDATE " . TABLE_PREFIX . "node
				SET urlident = CASE nodeid
				$sql ELSE urlident END
			";
			$this->db->query_write($query);

			//Now fix the starter
			$query = "UPDATE " . TABLE_PREFIX . "node
				SET starter = nodeid
				WHERE oldcontenttypeid = $threadTypeId
				AND oldid > $maxvB5 AND oldid < ($maxvB5 + $process)
			";
			$this->db->query_write($query);

			//Now populate the text table
			$query = "INSERT INTO " . TABLE_PREFIX . "text(nodeid, rawtext)
			SELECT node.nodeid, p.pagetext AS rawtext
			FROM " . TABLE_PREFIX . "thread AS th
			INNER JOIN " . TABLE_PREFIX . "post AS p ON p.postid = th.firstpostid
			INNER JOIN " . TABLE_PREFIX . "node AS node ON node.oldid = th.threadid AND node.oldcontenttypeid = $threadTypeId
				WHERE th.threadid > $maxvB5
			";
			$this->db->query_write($query);

			//Now the closure record for depth=0
			$query = "INSERT INTO " . TABLE_PREFIX . "closure (parent, child, depth)
				SELECT node.nodeid, node.nodeid, 0
				FROM " . TABLE_PREFIX . "node AS node
				WHERE node.oldcontenttypeid = $threadTypeId AND node.oldid > $maxvB5 AND node.oldid < ($maxvB5 + $process)";
			$this->db->query_write($query);

			//Add the closure records to root
			$query = "INSERT INTO " . TABLE_PREFIX . "closure (parent, child, depth)
				SELECT parent.parent, node.nodeid, parent.depth + 1
				FROM " . TABLE_PREFIX . "node AS node
				INNER JOIN " . TABLE_PREFIX . "closure AS parent ON parent.child = node.parentid
				WHERE node.oldcontenttypeid = $threadTypeId AND node.oldid > $maxvB5 AND node.oldid < ($maxvB5 + $process)";
			$this->db->query_write($query);

			vB::getDbAssertor()->assertQuery('vBInstall:updateChannelRoutes', array('contenttypeid' => $threadTypeId,
			'startat' => $maxvB5, 'batchsize' => $process));
		}

		$this->show_message(sprintf($this->phrase['core']['processed_records_x_y'], $maxvB5 + 1, $maxvB5 + $process - 1));

		return array('startat' => ($maxvB5 + $process - 1), 'maxvB4' => $maxvB4);
	}

	//Now non-starter posts, which come in as responses
	function step_146($data = NULL)
	{
		$startat = intval($data['startat']);
		$threadTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Thread');
		$postTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Post');
		$process = 6000;
		$startat = intval($data['startat']);
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'));
		//First see if we need to do something. Maybe we're O.K.
		if (!empty($data['maxvB4']))
		{
			$maxvB4 = $data['maxvB4'];
		}
		else
		{
			$maxImportedThread = $this->db->query_first("SELECT MAX(oldid) AS maxid FROM " . TABLE_PREFIX . "node WHERE oldcontenttypeid = $threadTypeId");
			$maxImportedThread = $maxImportedThread['maxid'];
			//If we don't have any threads, we're done.
			if (intval($maxImportedThread) < 1)
			{
				$this->skip_message();
				return;
			}

			$maxvB4 = $this->db->query_first("
				SELECT MAX(p.postid) AS maxid
				FROM " . TABLE_PREFIX . "post AS p
				INNER JOIN " . TABLE_PREFIX . "thread AS t ON (t.threadid = p.threadid)
				WHERE
					p.threadid <= $maxImportedThread
						AND
					p.postid <> t.firstpostid
			");
			$maxvB4 = $maxvB4['maxid'];

			//If we don't have any posts, we're done.
			if (intval($maxvB4) < 1)
			{
				$this->skip_message();
				return;
			}
		}

		$maxvB5 = $this->db->query_first("SELECT MAX(oldid) AS maxid FROM " . TABLE_PREFIX . "node WHERE oldcontenttypeid = $postTypeId");
		if (!empty($maxvB5) AND !empty($maxvB5['maxid']))
		{
			$maxvB5 = $maxvB5['maxid'];
		}
		else
		{
			$maxvB5 = 0;
		}

		$maxvB5 = max($startat, $maxvB5);
		if (($maxvB4 <= $maxvB5) AND !$startat)
		{
			$this->skip_message();
			return;
		}
		else if ($maxvB4 <= $maxvB5)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}
		// @TODO add its own phrase

		$textTypeId = vB_Types::instance()->getContentTypeID('vBForum_Text');

		/*** posts ***/
		$query = "
		INSERT INTO " . TABLE_PREFIX . "node(contenttypeid, parentid, routeid, title,  htmltitle,
			oldid, oldcontenttypeid, created, ipaddress, starter, inlist, userid, authorname,
			publishdate,
			unpublishdate,
			showpublished,
			showopen,
			approved,
			showapproved,
			iconid,
			deleteuserid, deletereason
		)
		SELECT $textTypeId, node.nodeid, node.routeid, p.title, p.title,
			p.postid, $postTypeId, p.dateline, p.ipaddress, node.nodeid, 1, p.userid, p.username,
			p.dateline,
			(CASE WHEN p.visible < 2 THEN 0 ELSE 1 END),
			(CASE WHEN t.visible < 2 AND p.visible < 2 THEN 1 ELSE 0 END),
			1,
			(CASE WHEN p.visible = 0 THEN 0 ELSE 1 END),
			(CASE WHEN t.visible = 0 OR p.visible = 0 THEN 0 ELSE 1 END),
			p.iconid,
			dl.userid, dl.reason
		FROM " . TABLE_PREFIX . "post AS p
		INNER JOIN " . TABLE_PREFIX . "thread AS t ON (p.threadid = t.threadid)
		INNER JOIN " . TABLE_PREFIX . "node AS node ON node.oldid = p.threadid AND node.oldcontenttypeid = $threadTypeId
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS dl ON dl.primaryid = p.postid AND dl.type = 'post'
		WHERE p.postid > $maxvB5  AND p.postid < ($maxvB5 + $process) AND t.firstpostid <> p.postid ORDER BY p.postid";
		$this->db->query_write($query);

		//Now populate the text table
		if ($this->field_exists('post', 'htmlstate'))
		{
			$query = "INSERT INTO " . TABLE_PREFIX . "text(nodeid, rawtext, htmlstate)
			SELECT node.nodeid, IF(p.title <> '', CONCAT(p.title, '\r\n\r\n', p.pagetext) , p.pagetext) AS rawtext, htmlstate \n";
		}
		else
		{
			$query = "INSERT INTO " . TABLE_PREFIX . "text(nodeid, rawtext)
			SELECT node.nodeid, IF(p.title <> '', CONCAT(p.title, '\r\n\r\n', p.pagetext) , p.pagetext) AS rawtext\n ";
		}
		$query .= "
		FROM " . TABLE_PREFIX . "post AS p
		INNER JOIN " . TABLE_PREFIX . "node AS node ON node.oldid = p.postid AND node.oldcontenttypeid = $postTypeId
			WHERE p.postid > $maxvB5  AND p.postid < ($maxvB5 + $process)
		";
		$this->db->query_write($query);

		//Now the closure record for the node
		$query = "INSERT INTO " . TABLE_PREFIX . "closure (parent, child, depth)
			SELECT node.nodeid, node.nodeid, 0
			FROM " . TABLE_PREFIX . "node AS node
			WHERE node.oldcontenttypeid = $postTypeId AND node.oldid > $maxvB5  AND node.oldid < ($maxvB5 + $process)";
		$this->db->query_write($query);

		//Add the closure records to root
		$query = "INSERT INTO " . TABLE_PREFIX . "closure (parent, child, depth)
			SELECT parent.parent, node.nodeid, parent.depth + 1
			FROM " . TABLE_PREFIX . "node AS node
			INNER JOIN " . TABLE_PREFIX . "closure AS parent ON parent.child = node.parentid
			WHERE node.oldcontenttypeid = $postTypeId AND node.oldid > $maxvB5 AND node.oldid < ($maxvB5 + $process)";
		$this->db->query_write($query);
		$this->show_message(sprintf($this->phrase['core']['processed_records_x_y'], $maxvB5 + 1, $maxvB5 + $process - 1));

		return array('startat' => ($maxvB5 + $process - 1), 'maxvB4' => $maxvB4);
	}

	//Now attachments from posts (not starters)
	function step_147($data = NULL)
	{
		if ($this->tableExists('attachment') AND $this->tableExists('filedata') AND $this->tableExists('thread') AND $this->tableExists('post'))
		{
			$process = 5000;
			$startat = intval($data['startat']);
			$attachTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Attach');
			$postTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Post');
			//First see if we need to do something. Maybe we're O.K.
			if (empty($data['maxvB4']))
			{
				$maxvB4 = $this->db->query_first("SELECT MAX(a.attachmentid) AS maxid
					FROM " . TABLE_PREFIX . "attachment AS a
					INNER JOIN " . TABLE_PREFIX . "post AS p ON a.contentid = p.postid
					INNER JOIN " . TABLE_PREFIX . "thread AS th ON p.threadid = th.threadid AND th.firstpostid <> p.postid
				");
				$maxvB4 = $maxvB4['maxid'];

				//If we don't have any attachments, we're done.
				if (intval($maxvB4) < 1)
				{
					$this->skip_message();
					return;
				}
			}
			else
			{
				$maxvB4 = $data['maxvB4'];
			}

			$maxvB5 = $this->db->query_first("SELECT MAX(oldid) AS maxid FROM " . TABLE_PREFIX . "node WHERE oldcontenttypeid = " . vB_Api_ContentType::OLDTYPE_POSTATTACHMENT);
			if (empty($maxvB5) OR empty($maxvB5['maxid']))
			{
				$maxvB5 = 0;
			}
			else
			{
				$maxvB5 = $maxvB5['maxid'];
			}

			$maxvB5 = max($maxvB5, $startat);
			if (($maxvB4 <= $maxvB5) AND !$startat)
			{
				$this->skip_message();
				return;
			}
			else if ($maxvB4 <= $maxvB5)
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}

			/*** first the nodes ***/
				$query = "
			INSERT INTO " . TABLE_PREFIX . "node(contenttypeid, parentid, routeid, title,  htmltitle,
				publishdate, oldid, oldcontenttypeid, created,
				starter, inlist, showpublished, showapproved, showopen)
			SELECT $attachTypeId, node.nodeid, node.routeid, '', '',
				a.dateline, a.attachmentid,	" . vB_Api_ContentType::OLDTYPE_POSTATTACHMENT . ", a.dateline,
				node.starter, 0, 1, 1, 1
			FROM " . TABLE_PREFIX . "attachment AS a
			INNER JOIN " . TABLE_PREFIX . "node AS node ON node.oldid = a.contentid AND node.oldcontenttypeid = $postTypeId
			INNER JOIN " . TABLE_PREFIX . "post AS p ON a.contentid = p.postid
			INNER JOIN " . TABLE_PREFIX . "thread AS th ON p.threadid = th.threadid AND th.firstpostid <> p.postid
			WHERE a.attachmentid > $maxvB5 AND a.attachmentid < ($maxvB5 + $process) ORDER BY a.attachmentid
			LIMIT $process;";
			$this->db->query_write($query);

			//Now populate the attach table
			$query = "
			INSERT INTO ". TABLE_PREFIX . "attach
			(nodeid, filedataid, visible, counter, posthash, filename, caption, reportthreadid, settings)
				SELECT n.nodeid, a.filedataid,
				 CASE WHEN a.state = 'moderation' then 0 else 1 end AS visible, a.counter, a.posthash, a.filename, a.caption, a.reportthreadid, a.settings
			FROM ". TABLE_PREFIX . "attachment AS a INNER JOIN ". TABLE_PREFIX . "node AS n ON n.oldid = a.attachmentid AND n.oldcontenttypeid = " . vB_Api_ContentType::OLDTYPE_POSTATTACHMENT . "
			WHERE a.attachmentid > $maxvB5  AND a.attachmentid < ($maxvB5 + $process);
			";
			$this->db->query_write($query);

			//Now the closure record for the node
			$query = "INSERT INTO " . TABLE_PREFIX . "closure (parent, child, depth)
			SELECT node.nodeid, node.nodeid, 0
			FROM " . TABLE_PREFIX . "node AS node
			WHERE node.oldcontenttypeid = " . vB_Api_ContentType::OLDTYPE_POSTATTACHMENT . " AND node.oldid > $maxvB5 AND node.oldid< ($maxvB5 + $process)";
			$this->db->query_write($query);

			//Add the closure records to root
			$query = "INSERT INTO " . TABLE_PREFIX . "closure (parent, child, depth)
			SELECT parent.parent, node.nodeid, parent.depth + 1
			FROM " . TABLE_PREFIX . "node AS node
			INNER JOIN " . TABLE_PREFIX . "closure AS parent ON parent.child = node.parentid
			WHERE node.oldcontenttypeid = " . vB_Api_ContentType::OLDTYPE_POSTATTACHMENT . " AND node.oldid > $maxvB5 AND node.oldid< ($maxvB5 + $process) ";
			$this->db->query_write($query);

			$this->show_message(sprintf($this->phrase['core']['processed_records_x_y'], $maxvB5 + 1, $maxvB5 + $process - 1));

			return array('startat' => ($maxvB5 + $process - 1), 'maxvB4' => $maxvB4);
		}
	}

	function step_148()
	{
		$timenow = time();
		$query = "
			UPDATE " . TABLE_PREFIX . "node
			SET created = $timenow
			WHERE created IS NULL";

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'),
			$query);
	}

	/** Insert Poll data into the node table **/
	function step_149()
	{
		$threadTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Thread');
		if ($this->field_exists('poll', 'pollid'))
		{
			// Create new nodes
			$pollTypeId = vB_Types::instance()->getContentTypeID('vBForum_Poll');

			$query = "
				INSERT INTO " . TABLE_PREFIX . "node(contenttypeid, parentid, title, publishdate, oldid, oldcontenttypeid,
				inlist, showpublished, showapproved, showopen)
				SELECT $pollTypeId, threadnode.nodeid, poll.question, poll.dateline, poll.pollid, 9011, 1, 1, 1, 1
				FROM " . TABLE_PREFIX . "poll AS poll
				JOIN " . TABLE_PREFIX . "thread AS thread ON (poll.pollid = thread.pollid)
				JOIN " . TABLE_PREFIX . "node AS threadnode ON (thread.threadid = threadnode.oldid AND oldcontenttypeid = $threadTypeId)
				LEFT JOIN " . TABLE_PREFIX . "node AS existing ON (existing.oldid =  poll.pollid AND existing.oldcontenttypeid = 9011)
				WHERE existing.nodeid IS NULL
				ORDER BY poll.pollid;
			";
			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'));
			$this->db->query_write($query);
			vB::getDbAssertor()->assertQuery('vBInstall:setStarter', array('contenttypeid' => 9011,
				'startat' => 0));
			vB::getDbAssertor()->assertQuery('vBInstall:updateChannelRoutes', array('contenttypeid' => 9011,
				'startat' => 0, 'batchsize' => 999999));
		}
		else
		{
			$this->skip_message();
		}
	}

	/** set the nodeid **/
	function step_150()
	{
		if ($this->field_exists('poll', 'pollid') AND $this->field_exists('poll', 'nodeid'))
		{
			$query = "UPDATE " . TABLE_PREFIX . "poll AS poll
			INNER JOIN " . TABLE_PREFIX . "node AS node ON node.oldid = poll.pollid
			AND node.oldcontenttypeid = 9011
			SET poll.nodeid = node.nodeid
			WHERE poll.nodeid IS NULL OR poll.nodeid = 0;";

			$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'poll'),
				$query);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** make nodeid the primary key**/
	function step_151()
	{
		if ($this->field_exists('poll', 'pollid') AND $this->field_exists('poll', 'nodeid'))
		{
			vB::getDbAssertor()->assertQuery('vBForum:poll', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, 'nodeid' => 0));
			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'poll'));
		}
		else
		{
			$this->skip_message();
		}

		// Add nodeid as primary key
		$polldescr = $this->db->query_first("SHOW COLUMNS FROM " . TABLE_PREFIX . "poll LIKE 'nodeid'");

		if (!empty($polldescr['Key']) AND ($polldescr['Key'] == 'PRI'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'poll', 6, 7),
				"ALTER TABLE " . TABLE_PREFIX . "poll DROP PRIMARY KEY, ADD PRIMARY KEY(nodeid)",
				MYSQL_ERROR_DROP_KEY_COLUMN_MISSING
			);
		}
		else
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'poll', 7, 7),
				"ALTER TABLE " . TABLE_PREFIX . "poll ADD PRIMARY KEY(nodeid)"
			);
		}
	}

	/** set the polloptions nodeid **/
	function step_152()
	{
		if ($this->field_exists('poll', 'pollid'))
		{
			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'polloptions'));

			// Insert polloptions
			$polls = $this->db->query_read("
				SELECT poll.*, pollnode.nodeid
				FROM " . TABLE_PREFIX . "poll AS poll
				JOIN " . TABLE_PREFIX . "node AS pollnode ON (poll.pollid = pollnode.oldid AND pollnode.oldcontenttypeid = 9011)
			");
			while ($poll = $this->db->fetch_array($polls))
			{
				// Poll options
				$polloptions = explode('|||', $poll['options']);
				$optionstosave = array();
				foreach ($polloptions as $k => $polloption)
				{
					$this->db->query_write("
						INSERT INTO " . TABLE_PREFIX . "polloption
						(nodeid, title)
						VALUES
						($poll[nodeid], '" . $this->db->escape_string(trim($polloption)) . "')
					");

					$polloptionid = $this->db->insert_id();

					// Update nodeid and polloptionid
					$v = $k + 1;
					$this->db->query_write("
						UPDATE " . TABLE_PREFIX . "pollvote
						SET nodeid = $poll[nodeid], polloptionid = $polloptionid
						WHERE voteoption = $v AND pollid = $poll[pollid] "
					);

					// Get a list of votes
					$pollvotes = $this->db->query_read("
						SELECT * FROM " . TABLE_PREFIX . "pollvote AS pollvote
						WHERE polloptionid = $polloptionid
					");

					$votecount = 0;
					$voters = array();
					while ($pollvote = $this->db->fetch_array($pollvotes))
					{
						$votecount++;
						$voters[] = $pollvote['userid'];
					}

					// Update polloption
					$this->db->query_write("
						UPDATE " . TABLE_PREFIX . "polloption
						SET
							voters = '" . $this->db->escape_string(serialize($voters)) . "',
							votes = $votecount
						WHERE polloptionid = $polloptionid
					");

					$optionstosave[$polloptionid] = array(
						'polloptionid' => $polloptionid,
						'nodeid'       => $poll['nodeid'],
						'title'        => trim($polloption),
						'votes'        => $votecount,
						'voters'       => $voters,
					);
				}

				// Total votes for this poll
				$votes = $this->db->fetch_field("
					SELECT COUNT(*)
					FROM " . TABLE_PREFIX . "pollvote as pollvote
					WHERE pollvote.nodeid = $poll[nodeid]
				");

				$votes = intval($votes);

				// Update poll cache
				$this->db->query_write("
					UPDATE " . TABLE_PREFIX . "poll
					SET
						options = '" . $this->db->escape_string(serialize($optionstosave)) . "',
						votes = $votes
					WHERE nodeid = $poll[nodeid]
			");
			}
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_153()
	{
		$forumTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Forum');
		$threadTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Thread');
		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "infraction"),
		"UPDATE " . TABLE_PREFIX  . "infraction AS i INNER JOIN " . TABLE_PREFIX  . "node AS p ON p.oldid = i.postid AND p.oldcontenttypeid = $forumTypeId
			LEFT JOIN " . TABLE_PREFIX  . "node AS t ON t.oldid = i.threadid AND t.oldcontenttypeid = $threadTypeId
			SET i.nodeid = p.nodeid, i.channelid = t.nodeid;");
	}

	/**
	 * Fixing the contenttype table
	 */
	function step_154()
	{
		$not_searchable = array(
			"Post",
			"Thread",
			"Forum",
			"Announcement",
			"SocialGroupMessage",
			"SocialGroupDiscussion",
			"SocialGroup",
			"Album",
			"Picture",
			"PictureComment",
			"VisitorMessage",
			"User",
			"Event",
			"Calendar",
			"BlogEntry",
			"Channel",
			"BlogComment"
		);
		$searchable = array(
			"Text",
			"Attach",
			"Poll",
			"Photo",
			"Gallery"
		);
		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "contenttype"),
		"UPDATE " . TABLE_PREFIX  . "contenttype SET cansearch = '0' WHERE class IN (\"" . implode('","',$not_searchable) . "\");");

		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "contenttype"),
		"UPDATE " . TABLE_PREFIX  . "contenttype SET cansearch = '1' WHERE class IN (\"" . implode('","',$searchable) . "\") AND packageid = 1;");
	}

	/***	Set canplace properly*/
	function step_155()
	{
		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'contenttype'),
			"UPDATE " . TABLE_PREFIX . "contenttype SET canplace = '0' where NOT class IN ('Text','Channel','Poll','Gallery')");
		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'contenttype'),
			"UPDATE " . TABLE_PREFIX . "contenttype SET canplace = '1' where class IN ('Text','Channel','Poll','Gallery')");
	}

	/** Insert default data in site table */
	function step_156()
	{
		$siteRecords = $this->db->query_first("
			SELECT siteid FROM " . TABLE_PREFIX . "site
		");

		if (empty($siteRecords) OR empty($siteRecords['siteid']))
		{
			$navbars = get_default_navbars();
			$headernavbar = serialize($navbars['header']);
			$footernavbar = serialize($navbars['footer']);

			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'site'),
				"
				INSERT INTO " . TABLE_PREFIX . "site
				(title, headernavbar, footernavbar)
				VALUES
				('Default Site','$headernavbar','$footernavbar');
			"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/** Adding sitebuild perm to usergroup.adminpermissions */
	function step_157()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'usergroup'),
			"
				UPDATE " . TABLE_PREFIX . "usergroup
				SET adminpermissions = (3 | 16777216)
				WHERE systemgroupid = 6;
			"
		);
	}


	/**
	 * load vb4 dataCMS Sections to vb5 channels- First CMS Home
	 */
	function step_158()
	{
		//If we don't have CMS installed we can skip this step;
		if (isset($this->registry->products['vbcms']) AND $this->registry->products['vbcms'])
		{
			$this->show_message(sprintf($this->phrase['core']['processing_vbcms']));
			$channelTypeId = vB_Types::instance()->getContentTypeID('vBForum_Channel');
			$sectionTypeId = vB_Types::instance()->getContentTypeID('vBCms_Section');

			$query = "
			 /** Now CMS sections - Home First***/
			SELECT $sectionTypeId AS oldcontenttypeid, node.nodeid AS oldid, ni.title,ni.description, ni.keywords, ni.html_title, n.url,
				n.publishdate,n.publishdate, n.userid, u.username AS authorname
			FROM " . TABLE_PREFIX . "cms_node  AS n
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS ni ON n.nodeid = ni.nodeid
			LEFT JOIN " . TABLE_PREFIX . "user AS u ON u.userid = n.userid
			LEFT JOIN " . TABLE_PREFIX . "node AS new ON new.oldid = n.nodeid AND new.oldcontenttypeid = n.contenttypeid
			JOIN " . TABLE_PREFIX . "node AS node
			WHERE n.nodeid = 1 AND node.parentid = 0 AND new.nodeid IS NULL
			 order by n.nodeid;";

			$home = $this->db->query_read($query);

			if ($home AND $homeSection = $this->db->fetch_array($home))
			{
				vB_Upgrade::createAdminSession();
				$channelLib = vB_Library::instance('content_channel');
				foreach(array('showpublished', 'open', 'approved', 'showopen', 'showapproved', 'inlist') AS $field)
				{
					$homeSection[$field] = 1;
				}
				$homeSection['urlident'] = $channelLib->getUniqueUrlIdent($homeSection['title']);
				$nodeid = $channelLib->add($homeSection, array('skipNotifications' => true, 'skipFloodCheck' => true, 'skipDupCheck' => true));
			}
		}
		else
		{
			$this->skip_message();
		}

	}

	/*Now we import sub-channels. If they are nested we will run several times.abstract
	**/
	function step_159()
	{
		if (isset($this->registry->products['vbcms']) AND $this->registry->products['vbcms'])
		{
			$process = 50;
			$sectionTypeId = vB_Types::instance()->getContentTypeID('vBCms_Section');
			$query = "SELECT node.nodeid AS parentid, ni.title,ni.description, ni.keywords, ni.html_title, n.url,
			n.publishdate, n.nodeid AS oldid
			FROM " . TABLE_PREFIX . "cms_node  AS n
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS ni ON n.nodeid = ni.nodeid
			LEFT JOIN " . TABLE_PREFIX . "node AS new ON new.oldid = n.nodeid AND new.oldcontenttypeid = n.contenttypeid
			LEFT JOIN " . TABLE_PREFIX . "node AS node ON node.oldid = n.parentnode AND node.oldcontenttypeid = $sectionTypeId
			WHERE n.contenttypeid = $sectionTypeId AND n.parentnode = 1 AND new.nodeid IS NULL
			order by n.nodeid LIMIT $process;";

			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'));
			$toImport = $this->db->query_read($query);
			if ($toImport)
			{
				vB_Upgrade::createAdminSession();
				$channelLib = vB_Library::instance('content_channel');
				while ($newSection = $this->db->fetch_array($toImport))
				{
					$newSection['urlident'] = $channelLib->getUniqueUrlIdent($newSection['title']);
					$newSection['oldcontenttypeid'] = $sectionTypeId;
					$channelLib->add($newSection, array('skipNotifications' => true, 'skipFloodCheck' => true, 'skipDupCheck' => true));
				}
				$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $process));
			}
			else
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
			}

		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Now import articles
	 *
	 */
	function step_160($data = NULL)
	{
		if (isset($this->registry->products['vbcms']) AND $this->registry->products['vbcms'])
		{

			$batchsize = 4000;
			$startat = intval($data['startat']);
			$articleTypeId = vB_Types::instance()->getContentTypeID('vBCms_Article');
			$textTypeId = vB_Types::instance()->getContentTypeID('vBForum_Text');
			//First see if we need to do something. Maybe we're O.K.

			if (!empty($data['maxvB4']))
			{
				$maxvB4 = $data['maxvB4'];
			}
			else
			{
				$maxvB4 = $this->db->query_first("SELECT MAX(nodeid) AS maxid FROM " . TABLE_PREFIX . "cms_node WHERE contenttypeid = $articleTypeId");
				$maxvB4 = $maxvB4['maxid'];

				//If we don't have any posts, we're done.
				if (intval($maxvB4) < 1)
				{
					$this->skip_message();
					return;
				}

			}

			if (empty($startat))
			{
				$maxvB5 = $this->db->query_first("SELECT MAX(oldid) AS maxid FROM " . TABLE_PREFIX . "node WHERE oldcontenttypeid = $articleTypeId");

				if (!empty($maxvB5) AND !empty($maxvB5['maxid']))
				{
					$startat = $maxvB5['maxid'];
				}
				else
				{
					$startat = 0;
				}
			}

			if (($maxvB4 <= $startat) AND !$startat)
			{
				$this->skip_message();
				return;
			}
			else if ($maxvB4 <= $startat)
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}
			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'));
			$assertor = vB::getDbAssertor();
			$assertor->assertQuery('vBInstall:insertCMSArticles', array('startat' => $startat, 'batchsize' => $batchsize,
				'textTypeId' =>$textTypeId, 'articleTypeId' => $articleTypeId,
				'sectionTypeId' =>  vB_Types::instance()->getContentTypeID('vBCms_Section')));

			/* Note insertCMSTextNoState removed as Step 57 above means the field will always exist */
			$assertor->assertQuery('vBInstall:insertCMSText', array('startat' => $startat, 'batchsize' => $batchsize,
				'articleTypeId' =>$articleTypeId));

			$assertor->assertQuery('vBInstall:addClosureSelf',
				array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => $articleTypeId));
			$assertor->assertQuery('vBInstall:addClosureParents',
				array('startat' => $startat, 'batchsize' => $batchsize, 'contenttypeid' => $articleTypeId));
			$assertor->assertQuery('vBInstall:updateChannelRoutes', array('contenttypeid' => $articleTypeId,
				'startat' => $startat, 'batchsize' => $batchsize));
			$assertor->assertQuery('vBInstall:setStarter', array('contenttypeid' => $articleTypeId,
					'startat' => $startat));

			return array('startat' => $startat + $batchsize, 'maxvB4' => $maxvB4 );
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Now attachments
	 *
	 */
	function step_161($data = NULL)
	{
		if ($this->tableExists('attachment') AND $this->tableExists('filedata'))
		{
			$process = 200;
			$startat = intval($data['startat']);
			$checkArray = $this->db->query_first("
				SELECT a.attachmentid
				FROM ". TABLE_PREFIX . "attachment AS a
				INNER JOIN ". TABLE_PREFIX . "node AS n ON n.oldid = a.contentid AND n.oldcontenttypeid = a.contenttypeid
				LEFT JOIN ". TABLE_PREFIX . "node AS existing ON existing.oldid = a.attachmentid AND existing.oldcontenttypeid = " . vB_Api_ContentType::OLDTYPE_POSTATTACHMENT . "
				WHERE existing.nodeid IS NULL LIMIT 1
			");

			if (empty($checkArray) AND !$startat)
			{
				$this->skip_message();
				return;
			}
			else if (empty($checkArray))
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}
			else if (!$startat)
			{
				// @TODO add its own phrase
				$this->show_message(sprintf($this->phrase['version']['500a1']['importing_x_records'], 'attachment'));
				$this->show_message(sprintf($this->phrase['core']['processing_records_x'], $process));
				return array('startat' => 1); // Go back and actually process
			}

			$attachTypeId = vB_Types::instance()->getContentTypeID('vBForum_Attach');
			$timenow = time();

			$this->run_query('adding attachment records',
				"/** Attachments **/
				INSERT INTO " . TABLE_PREFIX . "node(contenttypeid, parentid, description, publishdate, showpublished, showapproved, showopen, routeid, oldid, oldcontenttypeid)
				SELECT $attachTypeId, n.nodeid, a.caption, $timenow, 1, 1, 1, n.routeid, a.attachmentid, " . vB_Api_ContentType::OLDTYPE_POSTATTACHMENT . "
				FROM ". TABLE_PREFIX . "attachment AS a
				INNER JOIN ". TABLE_PREFIX . "node AS n ON n.oldid = a.contentid AND n.oldcontenttypeid = a.contenttypeid
				LEFT JOIN ". TABLE_PREFIX . "node AS existing ON existing.oldid = a.attachmentid AND existing.oldcontenttypeid = " . vB_Api_ContentType::OLDTYPE_POSTATTACHMENT . "
				WHERE existing.nodeid IS NULL LIMIT $process;
			");

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $process));
			return array('startat' => ($startat + 1));
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	 * Now attachments
	 *
	 */
	function step_162($data = NULL)
	{
		$process = 200;
		$startat = intval($data['startat']);
		if ($this->tableExists('attachment') AND $this->tableExists('attachment') AND $this->tableExists('filedata'))
		{
			$checkArray = $this->db->query_first("
			SELECT a.attachmentid
			FROM ". TABLE_PREFIX . "attachment AS a INNER JOIN ". TABLE_PREFIX . "node AS n ON n.oldid = a.attachmentid AND n.oldcontenttypeid = " . vB_Api_ContentType::OLDTYPE_POSTATTACHMENT . "

			LEFT JOIN ". TABLE_PREFIX . "attach AS existing ON existing.nodeid = n.nodeid AND existing.filedataid = a.filedataid
			WHERE existing.nodeid IS NULL LIMIT 1
		");

			if (empty($checkArray) AND !$startat)
			{
				$this->skip_message();
				return;
			}
			else if (empty($checkArray))
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}
			else if (!$startat)
			{
				// @TODO add its own phrase
				$this->show_message(sprintf($this->phrase['version']['500a1']['importing_x_records'], 'attachment'));
				$this->show_message(sprintf($this->phrase['core']['processing_records_x'], $process));
				return array('startat' => 1); // Go back and actually process
			}

			$this->run_query('adding attachment records', "INSERT INTO ". TABLE_PREFIX . "attach
			(nodeid, filedataid,visible, counter,posthash,filename,caption,reportthreadid,settings)
			SELECT n.nodeid, a.filedataid,
			CASE WHEN a.state = 'moderation' then 0 else 1 end AS visible, a.counter, a.posthash, a.filename, a.caption, a.reportthreadid, a.settings
			FROM ". TABLE_PREFIX . "attachment AS a INNER JOIN ". TABLE_PREFIX . "node AS n ON n.oldid = a.attachmentid AND n.oldcontenttypeid = " . vB_Api_ContentType::OLDTYPE_POSTATTACHMENT . "
			LEFT JOIN ". TABLE_PREFIX . "attach AS existing ON existing.nodeid = n.nodeid AND existing.filedataid = a.filedataid
			WHERE existing.nodeid IS NULL LIMIT $process;"
			);

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $process));
			return array('startat' => ($startat + 1));
		}
		else
		{
			$this->skip_message();
		}
	}


	/**
	 * Update the "last" data for forums.
	 *
	 */
	function step_163()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'));
		vB::getDbAssertor()->assertQuery('vBInstall:updateForumLast', array('forumTypeid' => vB_Types::instance()->getContentTypeID('vBForum_Forum'),
			'postTypeid' => vB_Types::instance()->getContentTypeID('vBForum_Post')));
	}

	/**
	 * The above step will fail if the last post is a thread with no replies. That requires a different query.
	 *
	 */
	function step_164()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'));
		vB::getDbAssertor()->assertQuery('vBInstall:updateForumLastThreadOnly', array('threadTypeid' => vB_Types::instance()->getContentTypeID('vBForum_Thread'),
			'forumTypeid' => vB_Types::instance()->getContentTypeID('vBForum_Forum')));
	}

	/**
	 * Update "last" data for threads. We need to do this in blocks because the there could potentially be hundreds of thousands.
	 *
	 */
	function step_165($data = NULL)
	{
		$threadTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Thread');
		$postTypeId = vB_Types::instance()->getContentTypeID('vBForum_Post');
		$batchsize = 4000;
		$startat = intval($data['startat']);
		$assertor = vB::getDbAssertor();

		$maxvB5 = $assertor->getRow('vBInstall:getMaxImportedPost', array('contenttypeid' => $threadTypeId));
		$maxvB5 = intval($maxvB5['maxid']);

		if ($startat == 0)
		{
			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'));
		}
		else if ($startat >= $maxvB5)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}
		$assertor->assertQuery('vBInstall:updateThreadLast', array('threadTypeid' => $threadTypeId, 'postTypeid' => $postTypeId, 'startat' => $startat, 'batchsize' => $batchsize));
		$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
		return array('startat' => $startat + $batchsize);
	}

	//Now attachments from threads
	function step_166($data = NULL)
	{
		if ($this->tableExists('attachment') AND $this->tableExists('filedata') AND $this->tableExists('thread') AND $this->tableExists('post'))
		{
			$process = 5000;
			$startat = intval($data['startat']);
			$attachTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Attach');
			$threadTypeId =  vB_Types::instance()->getContentTypeID('vBForum_Thread');
			//First see if we need to do something. Maybe we're O.K.
			if (empty($data['maxvB4']))
			{
				$maxvB4 = $this->db->query_first("SELECT MAX(a.attachmentid) AS maxid
					FROM " . TABLE_PREFIX . "attachment AS a
					INNER JOIN " . TABLE_PREFIX . "post AS p ON a.contentid = p.postid
					INNER JOIN " . TABLE_PREFIX . "thread AS th ON p.threadid = th.threadid AND th.firstpostid = p.postid
				");
				$maxvB4 = $maxvB4['maxid'];

				//If we don't have any attachments, we're done.
				if (intval($maxvB4) < 1)
				{
					$this->skip_message();
					return;
				}
			}
			else
			{
				$maxvB4 = $data['maxvB4'];
			}

			$maxvB5 = $this->db->query_first("SELECT MAX(oldid) AS maxid FROM " . TABLE_PREFIX . "node WHERE oldcontenttypeid = " . vB_Api_ContentType::OLDTYPE_THREADATTACHMENT);
			if (empty($maxvB5) OR empty($maxvB5['maxid']))
			{
				$maxvB5 = 0;
			}
			else
			{
				$maxvB5 = $maxvB5['maxid'];
			}

			if (($maxvB4 <= $maxvB5) AND !$startat)
			{
				$this->skip_message();
				return;
			}
			else if ($maxvB4 <= $maxvB5)
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}
			$maxvB5 = max($maxvB5, $startat);

			/*** first the nodes ***/
				$query = "
			INSERT INTO " . TABLE_PREFIX . "node(contenttypeid, parentid, routeid, title,  htmltitle,
				publishdate, oldid, oldcontenttypeid, created,
				starter, inlist, showpublished, showapproved, showopen)
			SELECT $attachTypeId, n.nodeid, n.routeid, '', '',
				a.dateline, a.attachmentid,	" . vB_Api_ContentType::OLDTYPE_THREADATTACHMENT . ", a.dateline,
				n.starter, 0, 1, 1, 1
			FROM " . TABLE_PREFIX . "attachment AS a
			INNER JOIN " . TABLE_PREFIX . "thread AS th ON a.contentid = th.firstpostid
			INNER JOIN " . TABLE_PREFIX . "node AS n ON n.oldid = th.threadid AND n.oldcontenttypeid = " . $threadTypeId . "
			WHERE a.attachmentid > $maxvB5 AND a.attachmentid < ($maxvB5 + $process)
			ORDER BY a.attachmentid;";
			$this->db->query_write($query);

			//Now populate the attach table
			$query = "
			INSERT INTO ". TABLE_PREFIX . "attach
			(nodeid, filedataid, visible, counter, posthash, filename, caption, reportthreadid, settings)
				SELECT n.nodeid, a.filedataid,
				 CASE WHEN a.state = 'moderation' then 0 else 1 end AS visible, a.counter, a.posthash, a.filename, a.caption, a.reportthreadid, a.settings
			FROM ". TABLE_PREFIX . "attachment AS a
			INNER JOIN ". TABLE_PREFIX . "node AS n ON n.oldid = a.attachmentid AND n.oldcontenttypeid = " . vB_Api_ContentType::OLDTYPE_THREADATTACHMENT . "
			WHERE a.attachmentid > $maxvB5  AND a.attachmentid < ($maxvB5 + $process);";
			$this->db->query_write($query);

			//Now the closure record for the node
			$query = "INSERT INTO " . TABLE_PREFIX . "closure (parent, child, depth)
			SELECT node.nodeid, node.nodeid, 0
			FROM " . TABLE_PREFIX . "node AS node
			WHERE node.oldcontenttypeid = " . vB_Api_ContentType::OLDTYPE_THREADATTACHMENT . " AND node.oldid > $maxvB5 AND node.oldid < ($maxvB5 + $process);";
			$this->db->query_write($query);

			//Add the closure records to root
			$query = "INSERT INTO " . TABLE_PREFIX . "closure (parent, child, depth)
			SELECT parent.parent, node.nodeid, parent.depth + 1
			FROM " . TABLE_PREFIX . "node AS node
			INNER JOIN " . TABLE_PREFIX . "closure AS parent ON parent.child = node.parentid
			WHERE node.oldcontenttypeid = " . vB_Api_ContentType::OLDTYPE_THREADATTACHMENT . " AND node.oldid > $maxvB5 AND node.oldid < ($maxvB5 + $process);";
			$this->db->query_write($query);

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $process));

			return array('startat' => ($maxvB5 + $process - 1), 'maxvB4' => $maxvB4);
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
