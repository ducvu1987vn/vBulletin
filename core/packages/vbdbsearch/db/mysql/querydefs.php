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
 * The vB core class.
 * Everything required at the core level should be accessible through this.
 *
 * The core class performs initialisation for error handling, exception handling,
 * application instatiation and optionally debug handling.
 *
 * @TODO: Much of what goes on in global.php and init.php will be handled, or at
 * least called here during the initialisation process.  This will be moved over as
 * global.php is refactored.
 *
 * @package vBulletin
 * @version $Revision: 28823 $
 * @since $Date: 2008-12-16 17:43:04 +0000 (Tue, 16 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vBDBSearch_dB_MYSQL_QueryDefs extends  vB_dB_MYSQL_QueryDefs
{
	/**
	 * This class is called by the new vB_dB_Assertor database class
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
	 **/
	/** @TODO remove this line when debugging is not required so often anymore */
	const DEBUG = false;
	/*Properties====================================================================*/
	private static $temp_table_created = false;
	protected $db_type = 'MYSQL';

	/** This is the definition for tables we will process through.  It saves a
	* database query to put them here.
	* **/
	protected $table_data = array(
		'words' => array(
			'key' => 'wordid',
			'structure' => array('wordid', 'word'),
			'forcetext' => array('word')
			),
		'searchlog' => array(
			'key' => 'searchlogid',
			'structure' => array('searchlogid','userid','ipaddress','searchhash','sortby','sortorder','searchtime','dateline','completed','json','results','results_count'),
		),
		'tagsearch' => array(
			'key' => 'tagid',
			'structure' => array('tagid','dateline')),
		'indexqueue' => array(
			'key' => 'queueid',
			'structure' => array('queueid', 'contenttype', 'newid', 'id2', 'package', 'operation', 'data'),
		),
		/**
		 * searchtowords_x table data is populated in the constructor
		 */
	);

	/** This is the definition for queries we will process through.  We could also
	 * put them in the database, but this eliminates a query.
	 * **/
	protected $query_data = array(
		'index_existing_words' => array
			(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "
					SELECT *
					FROM {TABLE_PREFIX}words as words
					WHERE {wordwhere}",
				'forcetext' => array('wordwhere')
			),
		'words_multi_insert' => array
			(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'query_string' => '
					INSERT INTO {TABLE_PREFIX}words
					(word)
					VALUES
					{values}',
				'forcetext' => array('values')
			),
		'searchtowords_multi_insert' => array
			(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'query_string' => '
					INSERT IGNORE INTO {TABLE_PREFIX}searchtowords_{suffix}
					(nodeid, wordid, is_title, score, position)
					VALUES
					{values}',
				'forcetext' => array('values')
			),
		'delete_searchtowords' => array
			(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'query_string' => '
					DELETE FROM {TABLE_PREFIX}searchtowords_{suffix}
					WHERE nodeid = {nodeid}'
			),
		'delete_searchtowords_specific' => array
			(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'query_string' => '
					DELETE FROM {TABLE_PREFIX}searchtowords_{suffix}
					WHERE wordid = {wordid} AND nodeid = {nodeid}'
			),
		'delete_searchtowords_bulk' => array
			(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DELETE,
				'query_string' => '
					DELETE FROM {TABLE_PREFIX}searchtowords_{suffix}
					WHERE nodeid IN
					(
						{nodeids}
					)'
			),
		'partial_user_lookup' => array
			(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "
					SELECT userid
					FROM {TABLE_PREFIX}user as user
					WHERE username LIKE {username}"
			),
		'textToIndex' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT node.nodeid, node.title, node.CRC32, text.rawtext
				FROM {TABLE_PREFIX}node AS node
				INNER JOIN {TABLE_PREFIX}text AS text ON text.nodeid = node.nodeid
				WHERE contenttypeid = {contenttypeid}"
		),
		'textToIndexEmptyCRC32' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT node.nodeid, node.title, node.CRC32, text.rawtext
				FROM {TABLE_PREFIX}node AS node
				INNER JOIN {TABLE_PREFIX}text AS text ON text.nodeid = node.nodeid
				WHERE contenttypeid = {contenttypeid} AND CRC32 = ''"
		),
		'textToIndexEmptyCRC32Limit' => array(vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "SELECT node.nodeid, node.title, node.CRC32, text.rawtext
				FROM {TABLE_PREFIX}node AS node
				INNER JOIN {TABLE_PREFIX}text AS text ON text.nodeid = node.nodeid
				WHERE contenttypeid = {contenttypeid} AND CRC32 = ''
				LIMIT 690000,1000"
		),
		'fetchAttachments' => array(
				vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'query_string' => "
				SELECT
				IFNULL(a.caption, IFNULL(l.url_title, IFNULL(p.caption, ''))) AS caption,
				n.nodeid, n.parentid, l.url, l.url_title, l.meta, a.filename, n.CRC32, parent.title as parenttitle
				FROM {TABLE_PREFIX}node AS n
				INNER JOIN {TABLE_PREFIX}closure AS c ON c.child = n.nodeid
				INNER JOIN {TABLE_PREFIX}node as parent ON parent.nodeid = c.parent AND parent.starter = parent.nodeid
				LEFT JOIN {TABLE_PREFIX}attach AS a ON a.nodeid = n.nodeid
				LEFT JOIN {TABLE_PREFIX}photo AS p ON p.nodeid = n.nodeid
				LEFT JOIN {TABLE_PREFIX}link AS l ON l.nodeid = n.nodeid
				WHERE n.contenttypeid = {contenttypeid} AND n.CRC32 = ''"
		),

	);

	public function __construct()
	{
		$prefixes = vB_Api_Search::get_table_name_suffixes();
		foreach ($prefixes as $prefix)
		{
			$tablename = 'searchtowords_' . $prefix;
			if (array_key_exists($tablename, $this->table_data))
			{
				continue;
			}
			$this->table_data[$tablename] = array(
				'key' => array('wordid','nodeid'),
				'structure' => array('wordid', 'nodeid', 'is_title', 'score', 'position')
			);
		}
	}

	public function getSearchResults($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['criteria']);
		}
		$this->db = $db;
		$criteria = &$params['criteria'];

		$this->filters = array(
				'make_equals_filter' => 	$criteria->get_equals_filters(),
				'make_notequals_filter' => 	$criteria->get_notequals_filters(),
				'make_range_filter' => 		$criteria->get_range_filters(),
		);

		$this->process_sort($criteria);

		$this->process_keywords_filters($criteria);

		if (!empty($this->filters['make_equals_filter']['view']))
		{
			$this->process_view_filters($this->filters['make_equals_filter']['view']);
			unset($this->filters['make_equals_filter']['view']);
		}

		if (!empty($this->filters['make_equals_filter']['follow']))
		{
			$this->process_follow_filters($this->filters['make_equals_filter']['follow'], $criteria);
			unset($this->filters['make_equals_filter']['follow']);
		}

		//handle equals filters
		$this->process_filters($criteria, 'make_equals_filter', $db);
		//handle notequals filters
		$this->process_filters($criteria, 'make_notequals_filter', $db);
		//handle range filters
		$this->process_filters($criteria, 'make_range_filter', $db);

		$query_joins = "";
		if (count($this->join))
		{
			$query_joins = implode(" \n\t\t\t\t", $this->join) . " ";
		}
		if (!$this->done_permission_check)
		{
			$permflags = $this->getNodePermTerms();
			if ((strpos($query_joins, 'AS starter') !== false) AND (!empty($permflags['joins']['starter'])))
			{
				//we don't need the starter join. We already have that.
				unset($permflags['joins']['starter']);
			}

			if (!empty($permflags['joins']))
			{
				$query_joins .= implode("\n", $permflags['joins']) . "\n";
			}
		}
		else
		{
			$permflags = array('joins' => false, 'where' => false);
		}
		$query_where = "";
		if (count($this->where))
		{
			$query_where = "WHERE " . implode(" AND \n\t\t\t\t", $this->where);
		}
		else if (!empty($permflags['where']))
		{
			$query_where = " WHERE " ;
		}
		$query_where .= $permflags['where'] . "\n";

		$query_limit = false;

		if (!empty($params['rawlimit']))
		{
			$query_limit = "LIMIT " . $params['rawlimit'];
		}
		elseif(!$criteria->getNoLimit())
		{
			$options = vB::getDatastore()->get_value('options');
			if (!empty($options['maxresults']))
			{
				$query_limit = "LIMIT " . $options['maxresults'];
			}
		}

		$query_what = $this->what;

		$query_order = false;
		$ordercriteria = array();
		if ($criteria->get_include_sticky())
		{
			if (!empty($this->join['closure']))
			{
				$ordercriteria[] = 'closure.depth ASC';
			}
			$ordercriteria[] = 'node.sticky DESC';
		}

		if ($criteria->getIncludeStarter())
		{
			$ordercriteria[] = 'isstarter DESC';
			$query_what .= ', IF(node.nodeid = node.starter, 1, 0) as isstarter';
		}
		foreach ($this->sort as $field => $dir)
		{
			//fall back to default search when no table is joined in that contains score fields
			if ($field == 'rank' AND strpos($query_joins, 'temp_search') === false AND strpos($query_joins, 'searchtowords_') === false)
			{
				$field = 'node.created';
			}
			if ($field != 'rank')
			{
				$ordercriteria[] = $field . " " . $dir;
			}
			else
			{
				$ordercriteria[] = "(
					score *
					(words_nr / (words_nr + " . $this->occurance_factor. ")) *
					GREATEST(1, 5 - ((UNIX_TIMESTAMP() - node.created)/" . $this->date_factor . ")) *
					IF(is_title > 0 , " . $this->title_boost . ", 1) / GREATEST(
						(4 * (words_nr - 1) / distance) + 1
						, 1)
				) " . $dir;

			}
		}
		// Adding to be always ordered by nodeid in the end. See VBV-4898
		$ordercriteria[] = "node.nodeid ASC";

		if (!empty($ordercriteria))
		{
			$query_order = "ORDER BY " . implode(',', $ordercriteria);
		}

		$query = "
			SELECT $query_what
			FROM " . TABLE_PREFIX . $this->from . "
			$query_joins
			$query_where
			$query_order
			$query_limit

		" . "\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

		$resultclass = 'vB_dB_' . $this->db_type . '_Result';
		$config = vB::getConfig();

		if (!empty($config['Misc']['debug_sql']) OR self::DEBUG)
		{
			echo "$query\n/** " . print_r($params['criteria']->getJSON(),true) . "**/\n";
		}
		$post_processors = $criteria->get_post_processors();

		if (empty($post_processors))
		{
			return new $resultclass($db, $query);
		}

		$resultset = new $resultclass($db, $query);
		foreach ($post_processors as $post_processor)
		{
			// get the node ids from the current $resultset
			$results = array();
			foreach ($resultset as $result)
			{
				$results[] = $result['nodeid'];
			}
			// create new $resultset based on those node ids
			$resultset = vB::getDbAssertor()->assertQuery(
					'vBDBSearch:' . $post_processor,
					array(
						'nodeids' => $results,
						'criteria' => $criteria
					)
			);
		}

		return $resultset;
	}

	public function getNodesWithSubChannel($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['nodeids']);
		}
		$channelcontentypeid = vB_Api::instanceInternal('contenttype')->fetchContentTypeIdFromClass('Channel');
		$query = "
					SELECT IF( node.contenttypeid = $channelcontentypeid, channelnode.nodeid, node.nodeid ) AS nodeid
					FROM " . TABLE_PREFIX . "node as node
					JOIN " . TABLE_PREFIX . "closure AS channel_closure ON (node.nodeid = channel_closure.parent)
					LEFT JOIN " . TABLE_PREFIX . "node AS channelnode ON (
						node.contenttypeid = $channelcontentypeid AND
						channelnode.nodeid = channel_closure.child AND
						channelnode.nodeid <> channel_closure.parent
					)
					WHERE
					" . $this->make_equals_filter('node', 'nodeid', $params['nodeids']) . " AND
						(
							node.contenttypeid <> $channelcontentypeid OR
							channelnode.nodeid IS NOT NULL
						)";
		$resultclass = 'vB_dB_' . $this->db_type . '_Result';
		$config = vB::getConfig();

		if (!empty($config['Misc']['debug_sql']))
		{
			echo "$query\n";
		}

		return new $resultclass($db, $query);
	}

	public function PostProcessorPreferLastContent($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['nodeids']);
		}

		if (empty($params['order']) OR $params['order'] == 'score')
		{
			$params['order'] = 'node.created';
		}

		$query_order = 'ORDER BY ';
		if ($params['include_sticky'])
		{
			$query_order .= 'node.sticky DESC, ';
		}

		if (empty($params['dir']))
		{
			$params['dir'] = 'DESC';
		}

		$query_order .= $params['order'] . " " . $params['dir'];

		$query = "
		SELECT IF(node.lastcontentid = 0, node.nodeid, node.lastcontentid) AS nodeid
		FROM " . TABLE_PREFIX . "node as node
		WHERE
		" . $this->make_equals_filter('node', 'nodeid', $params['nodeids']) . "
		$query_order
		";
		$resultclass = 'vB_dB_' . $this->db_type . '_Result';
		$config = vB::getConfig();
		if (!empty($config['Misc']['debug_sql']))
		{
			echo "$query\n";
		}

		return new $resultclass($db, $query);
	}

	public function PostProcessorAddComments($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['nodeids']) AND !empty($params['criteria']);
		}
		$this->db = $db;
		$this->process_sort($params['criteria']);
		$this->process_keywords_filters($params['criteria']);

		$query_joins = "";
		if (count($this->join))
		{
			$query_joins = implode(" \n\t\t\t\t", $this->join) . " ";
		}

		$query_where = "WHERE " . $this->make_equals_filter('node', 'parentid', $params['nodeids']);

		if (count($this->where))
		{
			$query_where .= implode(" AND \n\t\t\t\t", $this->where);
		}

		$query_what = $this->what;

		$query = "
		(SELECT node.nodeid
			FROM " . TABLE_PREFIX . "node as node
			WHERE " . $this->make_equals_filter('node', 'nodeid', $params['nodeids']) . "
		)UNION(
		SELECT node.nodeid
			FROM " . TABLE_PREFIX . "node as node
			$query_joins
			$query_where
		) \n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";

		$resultclass = 'vB_dB_' . $this->db_type . '_Result';
		$config = vB::getConfig();

		if (!empty($config['Misc']['debug_sql']) OR self::DEBUG)
		{
			echo "$query;\n/** " . print_r($params['criteria']->getJSON(),true) . "**/\n";
		}

		return new $resultclass($db, $query);
	}

	public function cacheResults($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			if (isset($params['fields']) /*AND isset($params['user'])*/)
			{
				return true;
			}
			return false;
		}
		$params['fields'] = self::quote_smart($params['fields']);

		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "searchlog
				(" . implode(',', array_keys($params['fields'])) . ")
			VALUES
				(" . implode(',', $params['fields']) . ")
		");

		return $db->insert_id();
	}

	/**
	 * gets the list of ids for indexed words in a text
	 * @param int $nodeid
	 * @return array word ids
	 */
	public function fetch_indexed_words($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			if (isset($params['nodeid']))
			{
				return true;
			}
			return false;
		}
		$nodeid = $params['nodeid'];
		$suffixes = vB_Api_Search::get_table_name_suffixes();
		$unions = array();
		foreach ($suffixes as $suffix) {
			$unions[] = "
				SELECT $suffix.*,'$suffix' as suffix
				FROM " . TABLE_PREFIX . "searchtowords_$suffix $suffix
				WHERE $suffix.nodeid = $nodeid";
		}
		$query = implode("\nUNION\n", $unions) . "\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
		$resultclass = 'vB_dB_' . $this->db_type . '_Result';
		$config = vB::getConfig();

		if (!empty($config['Misc']['debug_sql']))
		{
			echo "$query\n";
		}

		return new $resultclass($db, $query);
	}

	public function updateSearchtowords($params, $db, $check_only = false)
	{
		if ($check_only)
		{
			return !empty($params['suffix']) AND !empty($params['nodeid']) AND !empty($params['wordid']);
		}
		$accepted_fields = array('score', 'is_title','position');
		$fields = array_intersect_key($params, array_flip($accepted_fields));
		if (empty($fields))
		{
			return false;
		}
		$field_values = '';
		foreach ($fields as $key => $value) {
			$field_values[] = $key . '=' . $this->quote_smart($value);
		}
		$sql = "
				UPDATE " . TABLE_PREFIX . "searchtowords_" .$params['suffix']. "
				SET
					" .implode(",\n\t\t\t\t\t", $field_values) . "
				WHERE
					wordid = " . $params['wordid'] . " AND
					nodeid = " . $params['nodeid'] . "
		" . "\n/**" . __FUNCTION__ . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
		$config = vB::getConfig();

		if (!empty($config['Misc']['debug_sql']) OR self::DEBUG)
		{
			echo "$sql\n";
		}

		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($db, $sql);
		return $result;
	}

	/**
	 *	Handle processing for the equals / range filters
	 * @param array $types content types -- used to handle default split fields
	 * @param array $filters an array of "searchfields" => values to process
	 * @param array $filter_method string The name of the method to call to create a
	 *		where snippet for this kind of filter (currently equals and range -- not planning
	 *		to add more).  This should be the name of a private method on this class.
	 */
	private function process_filters(vB_Search_Criteria &$criteria, $filter_method, &$db)
	{
		foreach ($this->filters[$filter_method] as $field => $value)
		{
			//if this is a null filter we that forces a 0-result query
			switch ($field)
			{
				case 'null':
					$this->where[] = "false /** " . $db->escape_string(str_replace(array('*', '/', '\\'), ' ', $value)). " **/";
				break;
				case 'tag':
					$this->process_tag_filters($value);
				break;
				case 'channelid':
					$this->process_channel_filters($value, $criteria->getDepth(), $criteria->getIncludeStarter(), $criteria->getDepthExact());
				break;
				case 'exclude':
					$this->process_exclude_filters($value);
				break;
				case 'follow':
					$this->process_follow_filters($value, $criteria);
				break;
				case 'unpublishdate':
					$this->process_unpublished_filters($value);
				break;
				case 'publishdate':
					// skip adding a date restriction, workaround for activity stream
					if ($value != 'all')
					{
						$this->where[] = $this->$filter_method('node', 'publishdate', $value);
					}
				break;
				case 'starter_only':
					$this->process_starter_only_filter($value);
				break;
				case 'include_visitor_messages':
					$this->process_visitor_message_filter($value, 'include');
				break;
				case 'visitor_messages_only':
					$this->process_visitor_message_filter($value, 'only');
				break;
				case 'sentto':
					$this->process_visitor_message_filter($value, 'for');
				break;
				case 'exclude_visitor_messages':
					$this->process_visitor_message_filter($value, 'exclude');
				break;
				case 'include_private_messages':
					$this->process_private_message_filter($value, 'include');
				break;
				case 'private_messages_only':
					$this->process_private_message_filter($value, 'only');
				break;
				case 'OR':
					foreach ($value as $fld => $val)
					{
						$qbits[] = $this->make_equals_filter('node', $fld, $val);
					}
					$this->where[''] = "(" . implode(' OR ', $qbits) . ")";
				break;
				case 'marked':
					$this->process_marked_filter($value);
				break;
				default:
					$dbfield = $field;
					if (isset(self::$field_map[$field]))
					{
						$dbfield = self::$field_map[$field];
					}
					$where = $this->$filter_method('node', $dbfield, $value);
					$this->where[] = $where;
				break;
			}
		}
	}

	private function process_one_word_rank($word, $is_title_only = false)
	{
		$add_where = $is_title_only ? " AND searchtowords_$word[suffix].is_title = 1" : "";

		$this->join["temp_search"] = "JOIN " . TABLE_PREFIX . "searchtowords_$word[suffix] AS searchtowords_$word[suffix] ON searchtowords_$word[suffix].nodeid = node.nodeid $add_where";
	}

	/**
	 * building the query for the case when there is an OR joiner between the words or when the results are sorted by relevance
	 * @param array $searchwords the list of words to search for, keys are word ids
	 * @param boolean $is_title_only search in title only
	 */
	private function process_existing_words_or($searchwords, $is_title_only = false)
	{
		// this will contain the list of distances in case of a rank sort
		$nodeids = array();
		$not_nodeids = array();
		$prev_node_ids = array();
		$first_run = true;
		if (!self::$temp_table_created)
		{
			$this->db->query_write($query = "
					CREATE TEMPORARY TABLE IF NOT EXISTS " . TABLE_PREFIX . "temp_search (
					nodeid INT(11) NOT NULL DEFAULT 0,
					score INT(11) NOT NULL DEFAULT 0,
					prev_position INT(11) NOT NULL DEFAULT 0,
					words_nr TINYINT(2) NOT NULL DEFAULT 1,
					distance DECIMAL(6,4) NOT NULL DEFAULT 1,
					is_title TINYINT(1) NOT NULL DEFAULT 0,
					PRIMARY KEY USING HASH (nodeid)
					) ENGINE = MEMORY");
		}
		else
		{
			$this->db->query_write($query = "TRUNCATE TABLE " . TABLE_PREFIX . "temp_search");
		}

		$config = vB::getConfig();
		if (!empty($config['Misc']['debug_sql']) OR self::DEBUG)
		{
			echo "$query;\n";
		}
		self::$temp_table_created = true;
		foreach ($searchwords as $wordid => $word)
		{
			$add_where = $is_title_only ? " AND searchtowords_$word[suffix].is_title = 1" : "";
			// limit the current matches to the list of existing matches (skip this if it is the first iteration)
			if (!$first_run AND $word['joiner'] != 'OR' AND $word['joiner'] != 'NOT' AND is_numeric($wordid))
			{
				$this->db->query_write($query = "
					DELETE FROM " . TABLE_PREFIX . "temp_search
					WHERE nodeid NOT IN (
						SELECT nodeid
						FROM " . TABLE_PREFIX . "searchtowords_$word[suffix]
						WHERE wordid = $wordid $add_where
					)");

				if (!empty($config['Misc']['debug_sql']) OR self::DEBUG)
				{
					echo "$query;\n";
				}
			}

			// there migh be words that are not found in the words table; there is no point to look those up in the searchtowords tables
			if (is_numeric($wordid))
			{
				if ($word['joiner'] == 'NOT')
				{
					$this->where[] = "node.nodeid NOT IN (
						SELECT nodeid FROM " . TABLE_PREFIX . "searchtowords_$word[suffix] WHERE wordid = $wordid $add_where
					)";
				}
				else
				{
					$query = "
					INSERT INTO " . TABLE_PREFIX . "temp_search
						(nodeid, score, prev_position, is_title)
						SELECT nodeid, score, position, is_title
						FROM " . TABLE_PREFIX . "searchtowords_$word[suffix] AS searchtowords_$word[suffix]
						WHERE wordid = $wordid $add_where
					";
					if (!$first_run)
					{
						$query .= "ON DUPLICATE KEY UPDATE";
						$temptable = TABLE_PREFIX . "temp_search";
						if ($word['joiner'] != 'OR')
						{
							$query.= "
							$temptable.distance = $temptable.distance +
								EXP((ABS($temptable.prev_position - VALUES(prev_position))-1)/" . $this->word_distance_factor . ")
							,
							$temptable.prev_position = VALUES(prev_position),
							$temptable.words_nr = $temptable.words_nr + 1,";
						}
						$query .= "
						$temptable.score = $temptable.score + VALUES(score),
						$temptable.is_title = LEAST($temptable.is_title, VALUES(is_title))
					";
					}
					$this->db->query_write($query);

					if (self::DEBUG)
					{
						echo "$query;\n";
					}
					if (!$first_run AND $word['joiner'] != 'OR' AND $word['joiner'] != 'NOT')
					{
						$this->db->query_write($query = "
							DELETE FROM " . TABLE_PREFIX . "temp_search
							WHERE words_nr = 1
						");

						if (!empty($config['Misc']['debug_sql']) OR self::DEBUG)
						{
						echo "$query;\n";
						}

					}

				}
			}
			$first_run = false;
		}

		$where = "";
		if ($is_title_only)
		{
			$where .= " AND temp_search.is_title = 1";
		}

		$this->join['temp_search'] = "JOIN " . TABLE_PREFIX . "temp_search AS temp_search ON temp_search.nodeid = node.nodeid $where";
	}

	/**
	 *
	 * computes the relevancy score
	 * @param array $distances the relative distance of each word to the start of the text
	 * @param array $weight_sums the indexed score
	 * @param array $title_words list of words that are in the title
	 * @param boolean $is_and_joiner flag for joiner type, true if AND joiner, false otherwise
	 * @return array
	 */
	private function compute_sort_score(&$distances, &$weight_sums, &$title_words, $is_and_joiner)
	{
		$scores = array();
		$result_set = $this->db->query_read_slave("
			SELECT node.nodeid, node.created
			FROM " . TABLE_PREFIX . "node AS node
			WHERE " . self::make_equals_filter('node', 'nodeid', array_keys($distances)));

		while($node_details = $this->db->fetch_array($result_set))
		{
			$weight_sum = isset($weight_sums[$node_details['nodeid']]) ? $weight_sums[$node_details['nodeid']] : 1;
			if (!empty($distances[$node_details['nodeid']]))
			{
				$words_distances = explode(',', $distances[$node_details['nodeid']]);
			}
			else
			{
				$scores[$node_details['nodeid']] = 1;
				continue;
			}
			//in case of AND joiner compute the distance, otherwise the distance factor is 1
			$distance = $is_and_joiner ? $this->compute_distance($words_distances, $this->word_distance_factor) : 1;
			$timenow = vB::getRequest()->getTimeNow();

			$date_weight = max(1, 5 - (($timenow - $node_details['created'])/(913 * 86400)));
			$scores[$node_details['nodeid']] = (int) round($weight_sum * $date_weight * (empty($title_words[$node_details['nodeid']]) ? 1 : $this->title_boost) / max($distance, 1));
		}
		$this->db->free_result($result_set);
		return $scores;
	}

	/**
	 *
	 * computes the distance factor based on the relative distances of each word
	 * @param array $words_distances
	 * @param int $a distance factor influence
	 * @return int distance
	 */
	function compute_distance($words_distances, $a)
	{
		$distance = 0;
		$prev_position = 0;
		$words_distances = array();
		foreach ($words_distances as $position)
		{
			//$weight_sum += 	$score_details['score'];
			//$is_title += 	$score_details['is_title'];
			if ($prev_position > 0)
			{
				$distance += exp((abs($prev_position - $position)-1)/$a);
			}
			$prev_position = $position;
		}
		if ($distance == 0)
		{
			return 1;
		}
		return ((4 * (count($words_distances) - 1)) / $distance) + 1;
	}

	// building the query for for the case when there is no OR joiner
	private function process_existing_words_and($searchwords, $is_title_only = false)
	{
		//$prev_tablename = false;
		foreach ($searchwords as $wordid => $word)
		{
			$results = array();
			$tablename = $word['suffix'] . '_' . $wordid;

			if ($word['joiner'] == 'NOT')
			{
				$this->where[] = "node.nodeid NOT IN
				(
					SELECT $tablename.nodeid
					FROM " . TABLE_PREFIX . "searchtowords_$word[suffix] $tablename
					WHERE $tablename.wordid = $wordid
				)";
			}
			elseif ($word['joiner'] != 'OR') //in case of an AND (or missing joiner)
			{
				$criteria = "JOIN " . TABLE_PREFIX . "searchtowords_$word[suffix] $tablename ON $tablename.nodeid = node.nodeid AND $tablename.wordid = $wordid";
				if ($is_title_only)
				{
					$criteria .= " AND $tablename.is_title = 1";
				}
				$this->join[$tablename] = $criteria;
			}
		}
	}

	/**
	 * Process the filters for the query string
	 *
	 * @param vB_Legacy_Current_User $user user requesting the search
	 * @param vB_Search_Criteria $criteria search criteria to process
	 */
	protected function process_keywords_filters(vB_Search_Criteria &$criteria)
	{
		$keywords = $criteria->get_keywords();
		// nothing to process
		if (empty($keywords))
		{
			return;
		}
		$words = array();
		// get the map table names for the keywords. these tables will be joined into the search query
		$has_or_joiner = false;
		foreach ($keywords as $word_details) {
			$suffix = vB_Api_Search::get_table_name($word_details['word']);
			//$words[$suffix][$clean_word] = array('wordid'=>false,'joiner'=>$word['joiner']);
			$words[$word_details['word']] = array
				(
					'suffix'=>$suffix,
					'word'=>$word_details['word'],
					'joiner'=>$word_details['joiner']
				);
			if ($word_details['joiner'] == "OR")
			{
				$has_or_joiner = true;
			}
		}
		// nothing to process
		if (empty($words))
		{
			return;
		}
		$set = $this->db->query_read_slave($query = "
					SELECT *
					FROM " . TABLE_PREFIX . "words as words
					WHERE " . self::make_equals_filter('words', 'word', array_keys($words)));

		$config = vB::getConfig();
		if (!empty($config['Misc']['debug_sql']) OR self::DEBUG)
		{
			echo "$query;\n";
		}

		$wordids = array();
		while($word_details = $this->db->fetch_array($set))
		{
			$wordids[$word_details['word']] = $word_details['wordid'];
		}
		$this->db->free_result($set);
		$word_details = array();
		foreach ($words as $word => $details)
		{
			// if the word was not found
			if (!isset($wordids[$word])){
				// and it's not with a NOT or OR operator
				if (!$has_or_joiner AND $details['joiner'] != 'NOT')
				{
					// this word is not indexed so there is nothing to return
					$this->where[] = "0 /** $word is not indexed **/";
					$this->sort = array('node.created' => 'ASC');
					return;
				}
				// still need to add this word to the mix (either as a NOT operator or maybe as an OR). we use the word itself as a key to make it unique
				$key = $word;
			}
			else
			{
				$key = $wordids[$word];
			}
			$word_details[$key] = $details;
		}
		unset($wordids);
		unset($words);
		/*if (count($word_details) == 1)
		{
			$this->process_one_word_rank(array_pop($word_details), $criteria->is_title_only());
		}
		else*/
		if ($has_or_joiner OR isset($this->sort['rank']))
		{
			$this->process_existing_words_or($word_details, $criteria->is_title_only());
		}
		else
		{
			$this->process_existing_words_and($word_details, $criteria->is_title_only());
		}
	}

	/**
	 *	Process the filters for the requested tag
	 *
	 *	This processing makes the assumption that if the type is groupable the tags
	 *	will apply only to the group
	 *
	 *	@param array $tagids the ids of the tags to filter on.
	 */
	protected function process_tag_filters($tagids)
	{
		if (empty($tagids))
		{
			return;
		}
		if (is_numeric($tagids))
		{
			$tagids = array($tagids);
		}
		foreach ($tagids as $index => $tagid)
		{
			$this->join["tag$tagid"] = "JOIN " . TABLE_PREFIX . "tagnode AS tagnode$tagid ON
					(node.nodeid = tagnode$tagid.nodeid)";
			$this->where[] = $this->make_equals_filter("tagnode$tagid", 'tagid', $tagid);
		}
	}

	/**
	 *	Process the exclude filter
	 *
	 *	@param array $nodeids the ids of the nodes (and it's children) to exclude
	 */
	protected function process_exclude_filters($nodeids)
	{
		if (empty($nodeids))
		{
			return;
		}
		if (is_numeric($nodeids))
		{
			$nodeids = array($nodeids);
		}

		if (empty($this->join['closure']))
		{
			$this->join['closure'] = "JOIN " . TABLE_PREFIX . "closure AS closure ON node.nodeid = closure.child";
		}
		$this->join['exclude_closure'] = "LEFT JOIN  " . TABLE_PREFIX . "closure AS exclude_closure
			ON (exclude_closure.child = closure.child AND
				exclude_closure.parent IN (" . implode(',',$nodeids) . " ))\n";

		$this->where[] = "exclude_closure.child IS NULL ";
	}

	protected function process_channel_filters($channelid, $depth = false, $include_starter = false, $depth_exact = false)
	{
		//first let's see if this is valid.
		$userdata = vB::getUserContext()->getAllChannelAccess();

		if (!empty($depth) AND ($depth == 1) AND is_numeric($channelid) AND
			(in_array($channelid, $userdata['canview']) OR in_array($channelid, $userdata['canalwaysview']) OR in_array($channelid, $userdata['canmoderate']) OR
				in_array($channelid, $userdata['selfonly'])))
		{
			// The user can moderate the channel.
			if (in_array($channelid, $userdata['canmoderate']))
			{
				$this->where[] = $this->make_equals_filter('node', 'parentid', $channelid);
				$this->done_permission_check = true;
				return;
			}
			$userId = vB::getUserContext()->fetchUserId();
			$gitWhere = '';

			// The user can't moderate but is a channel member.
			if (!empty($userId))
			{
				$gitInfo = vB_Api::instanceInternal('user')->getGroupInTopic($userId);

				if (is_array($gitInfo) AND array_key_exists($channelid, $gitInfo))
				{
					$this->where[] = $this->make_equals_filter('node', 'parentid', $channelid);
					if (!vB::getUserContext()->getChannelPermission('forumpermissions', 'canseedelnotice', $channelid))
					{
						$this->where[] = "node.showpublished > 0";
					}
					$this->where[] = "node.showapproved > 0";

					if (!vB::getUserContext()->getChannelPermission('forumpermissions', 'canviewthreads', $channelid))
					{
						$this->where[] = "(node.starter = 0 OR node.nodeid = node.starter)";
					}
					$this->done_permission_check = true;
					return;
				}

				if (!empty($gitInfo))
				{
					$gitWhere = " OR node.nodeid IN (" . implode(',', array_keys($gitInfo)) . ") ";
				}
			}

			//if the user can't moderate, and doesn't have canviewthread, they just get titles and for the starters
			if (!empty($userId) AND in_array($channelid, $userdata['canview']) AND !vB::getUserContext()->getChannelPermission('forumpermissions', 'canviewthreads', $channelid))
			{
				$this->where[] = $this->make_equals_filter('node', 'parentid', $channelid);
				if (!vB::getUserContext()->getChannelPermission('forumpermissions', 'canseedelnotice', $channelid))
				{
					$this->where[] = "node.showpublished > 0";
				}
				$this->where[] = "node.showapproved  > 0";
				$this->where[] = "(node.viewperms > 0 $gitWhere)";
				$this->where[] = "(node.starter = 0 OR node.nodeid = node.starter)";
				$this->done_permission_check = true;
				return;
			}

			// The user can't moderate, is logged in, isn't a channel member, and has forum permission "can view others".
			if (!empty($userId) AND in_array($channelid, $userdata['canview']))
			{
				$this->where[] = $this->make_equals_filter('node', 'parentid', $channelid);
				if (!vB::getUserContext()->getChannelPermission('forumpermissions', 'canseedelnotice', $channelid))
				{
					$this->where[] = "node.showpublished > 0";
				}
				$this->where[] = "node.showapproved  > 0";
				$this->where[] = "(node.viewperms > 0 $gitWhere)";
				$this->done_permission_check = true;
				return;
			}

			// The user can't moderate, isn't a channel member,  is logged in, and has forum permission "can view posts" but not"can view others".
			if (!empty($userId) AND in_array($channelid, $userdata['selfonly']))
			{
				$this->where[] = $this->make_equals_filter('node', 'parentid', $channelid);
				if (!vB::getUserContext()->getChannelPermission('forumpermissions', 'canseedelnotice', $channelid))
				{
					$this->where[] = "node.showpublished > 0";
				}
				$this->where[] = "node.showapproved  > 0";
				$this->where[] = "node.userid = $userId";
				$this->done_permission_check = true;
				return;
			}

			// The user can't moderate, isn't logged in, and has forum permission "can view others"
			if (empty($userId) AND in_array($channelid, $userdata['canview']))
			{
				$this->where[] = $this->make_equals_filter('node', 'parentid', $channelid);
				if (!vB::getUserContext()->getChannelPermission('forumpermissions', 'canseedelnotice', $channelid))
				{
					$this->where[] = "node.showpublished > 0";
				}
				$this->where[] = "node.showapproved  > 0";
				$this->where[] = "node.viewperms = 2";
				$this->done_permission_check = true;
				return;
			}
		}
		// if it got here we need to do it the slow way
		$this->join['closure'] = "JOIN " . TABLE_PREFIX . "closure AS closure ON node.nodeid = closure.child AND " . $this->make_equals_filter('closure', 'parent', $channelid);

		if (empty($include_starter))
		{
			$this->where[] = 'node.nodeid <> closure.parent';
		}

		if (!empty($depth))
		{
			if ($depth_exact !== false)
			{
				$this->where[] = 'closure.depth = ' . $depth;
			}
			else
			{
				$this->where[] = 'closure.depth <= ' . $depth;
			}
		}
		$canviewthreads = true;
		if (is_array($channelid))
		{
			foreach ($channelid as $chid)
			{
				if(!vB::getUserContext()->getChannelPermission('forumpermissions', 'canviewthreads', $chid))
				{
					$canviewthreads = false;
					break;
				}
			}
		}
		elseif (is_numeric($channelid) AND !vB::getUserContext()->getChannelPermission('forumpermissions', 'canviewthreads', $channelid))
		{
			$canviewthreads = false;
		}
		if (!$canviewthreads)
		{
			$this->where[] = "(node.starter = 0 OR node.nodeid = node.starter)";
		}
	}

	protected function process_view_filters($view)
	{
		if (empty($view))
		{
			return;
		}
		switch ($view)
		{
			/**
			 * only include the latest reply or comment (or the starter itself if no replies/comments yet) per starter in all the channels.
			 * Filters out the Channel nodes from the Search API nodes results.
			 * @include replies/comments in the second phase
			 */
			case vB_Api_Search::FILTER_VIEW_ACTIVITY :
				$datecheck = false;
				// search for publishdate through filters
				foreach ($this->filters AS $type)
				{
					if (is_array($type) AND !$datecheck)
					{
						$datecheck = array_key_exists('publishdate', $type);
					}
				}

				$channelAccess = vb::getUserContext()->getAllChannelAccess();

				if (!empty($this->filters['make_equals_filter']['starter_only']))
				{
					$this->what = "DISTINCT node.nodeid";
				}
				else if ((!empty($channelAccess['starteronly'])))
				{
					$starterOnly = implode(',', $channelAccess['starteronly']);
					$this->what = "DISTINCT CASE WHEN starter.nodeid IN ($starterOnly) OR starter.parentid in ($starterOnly)
					THEN starter.nodeid ELSE node.lastcontentid END as nodeid";
				}
				else
				{
					$this->what = "DISTINCT node.lastcontentid as nodeid";
				}
				$this->where[] = "node.nodeid = node.starter AND node.contenttypeid <> " .
					vB_Api::instanceInternal('contenttype')->fetchContentTypeIdFromClass('Channel');
				unset($channelAccess);

				if (!$datecheck)
				{
					$age = vB::getDatastore()->getOption('max_age_channel');

					if (empty($age))
					{
						$age = 60;
					}

					$this->where[] = "node.created > " . (vB::getRequest()->getTimeNow() - ($age * 86400));
				}
				// in activity stream we don't want deleted content even if viewed by a moderator
				$this->where[] = "node.showpublished > 0";
			break;
			/**
			 * The Topic view should only display the starter nodes for the specified channel.
			 * Filters out the Channel nodes from the Search API nodes results.
			 */
			case vB_Api_Search::FILTER_VIEW_TOPIC :
				$this->what = "DISTINCT node.starter as nodeid";
				$this->where[] = "node.contenttypeid <> " . vB_Api::instanceInternal('contenttype')->fetchContentTypeIdFromClass('Channel');
			break;
			/**
			 * The Conversation Detail view should only display the descendant nodes of (and including) the specified starter.
			 * Filters out the Comment node from the Search API nodes results.
			 */
			case vB_Api_Search::FILTER_VIEW_CONVERSATION_THREAD :
				if (empty($this->join['closure']))
				{
					$this->join['closure'] = "JOIN " . TABLE_PREFIX . "closure AS closure ON node.nodeid = closure.child";
				}
				$this->where[] = "(node.starter = node.nodeid OR node.starter = node.parentid)";
				$this->where[] = 'closure.depth <= 1';
			break;
			case vB_Api_Search::FILTER_VIEW_CONVERSATION_THREAD_SEARCH :
				if (empty($this->join['closure']))
				{
					$this->join['closure'] = "JOIN " . TABLE_PREFIX . "closure AS closure ON node.nodeid = closure.child";
				}
				$this->what = " DISTINCT IF (node.parentid = 0 OR node.parentid = node.starter OR node.nodeid = node.starter, node.nodeid, node.parentid) AS nodeid";
			break;
			/**
			 * The Conversation Detail view should only display the descendant nodes of (and including) the specified starter.
			 * the Comment nodes are not filtered out.
			 * This should be handled by the channel filter
			 */
			case vB_Api_Search::FILTER_VIEW_CONVERSATION_STREAM :
			break;
		}
	}

	protected function process_follow_filters($value, vB_Search_Criteria &$criteria)
	{
		$type = $value['type'];
		$userid = $value['userid'];
		$assertor = vB::getDbAssertor();

		if (
				$type == vB_Api_Search::FILTER_FOLLOWING_BOTH OR
				$type == vB_Api_Search::FILTER_FOLLOWING_ALL OR
				$type == vB_Api_Search::FILTER_FOLLOWING_CHANNEL OR
				$type == vB_Api_Search::FILTER_FOLLOWING_CONTENT
			)
		{
			/* following nodes */
			$subscriptions = $assertor->getRows('vBForum:subscribediscussion', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					vB_dB_Query::CONDITIONS_KEY => array('userid' => $userid)),
					false,
					'discussionid'
			);
			$nodes = (!$subscriptions OR !empty($subscriptions['errors'])) ? array() : $subscriptions;
			$nodeids = array_keys($nodes);
			if (empty($nodeids) AND (
						$type == vB_Api_Search::FILTER_FOLLOWING_CHANNEL OR
						$type == vB_Api_Search::FILTER_FOLLOWING_CONTENT OR
						$type == vB_Api_Search::FILTER_FOLLOWING_BOTH
					)
			)
			{
				$this->where[] = "0 /** no subscriptions for user $userid **/";
				return;
			}
		}
		if ($type == vB_Api_Search::FILTER_FOLLOWING_ALL OR $type == vB_Api_Search::FILTER_FOLLOWING_USERS)
		{
			/* following users */
			$follows = vB_Api::instanceInternal('Follow')->getUserList($userid, 'following', 'follow');
			$cache = vB_Cache::instance(vB_Cache::CACHE_FAST);
			$follows = $cache->read("vbUserlistFollowFriend_$userid");
			if (empty($follows))
			{
				$follows = $assertor->getRows('userlist', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					vB_dB_Query::CONDITIONS_KEY => array(
							'userid' => $userid, 'type' => 'follow', 'friend' => 'yes'
						)
					),
					false,
					'relationid'
				);
				$cache->write("vbUserlistFollowFriend_$userid", $follows, 'followChg_' . $userid);
			}
			$userids = empty($follows) ? array() : array_keys($follows);
			if (empty($userids) AND $type == vB_Api_Search::FILTER_FOLLOWING_USERS)
			{
				$this->where[] = "0 /** no following for user $userid **/";
				return;
			}
		}

		if ($type == vB_Api_Search::FILTER_FOLLOWING_ALL AND empty($nodeids) AND empty($userids))
		{
			$this->where[] = "0 /** no following for user $userid **/";
			return;
		}

		if ($type == vB_Api_Search::FILTER_FOLLOWING_CHANNEL)
		{
			$this->filters['make_equals_filter']['channelid'] = $nodeids;
			$channelcontentypeid = vB_Api::instanceInternal('contenttype')->fetchContentTypeIdFromClass('Channel');
			$this->where[] = $this->make_notequals_filter('node', 'contenttypeid', $channelcontentypeid);
			return;
		}

		if ($type == vB_Api_Search::FILTER_FOLLOWING_CONTENT)
		{
			$channelcontentypeid = vB_Api::instanceInternal('contenttype')->fetchContentTypeIdFromClass('Channel');

			$this->where[] = $this->make_equals_filter('node', 'nodeid', $nodeids);
			$this->where[] = $this->make_notequals_filter('node', 'contenttypeid', $channelcontentypeid);
			return;
		}

		if ($type == vB_Api_Search::FILTER_FOLLOWING_USERS OR ($type == vB_Api_Search::FILTER_FOLLOWING_ALL AND empty($nodeids)))
		{
			$this->where[] = $this->make_equals_filter('node', 'userid', $userids);
			return;
		}

		if ($type == vB_Api_Search::FILTER_FOLLOWING_BOTH)
		{
			$nodes = $assertor->assertQuery('vBDBSearch:getNodesWithSubChannel', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'nodeids'=>$nodeids
			));
			$allNodeIds = array();
			foreach ($nodes as $nodeid)
			{
				$allNodeIds[] = $nodeid['nodeid'];
			}
			if (empty($allNodeIds))
			{
				$this->where[] = "0 /** no following for user $userid **/";
				return;
			}
			$this->filters['make_equals_filter']['nodeid'] = $allNodeIds;
			return;
		}

		if ($type == vB_Api_Search::FILTER_FOLLOWING_ALL)
		{

			$nodes = $assertor->assertQuery('vBDBSearch:getNodesWithSubChannel', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
					'nodeids'=>$nodeids
			));
			$allNodeIds = array();
			foreach ($nodes as $nodeid)
			{
				$allNodeIds[] = $nodeid['nodeid'];
			}
			if (empty($allNodeIds))
			{
				$this->where[] = "0 /** no following for user $userid **/";
				return;
			}
			$this->filters['make_equals_filter']['OR'] = array('nodeid' => $allNodeIds, 'userid' => $userids);
			return;
		}
	}

	protected function process_starter_only_filter($starter)
	{
		if ($starter)
		{
			$this->where[] = "node.starter = node.nodeid";
		}
	}

	protected function process_visitor_message_filter($userid, $type)
	{
		if ($type == 'include')
		{
			$this->where[] = "(node.userid = $userid OR node.setfor = $userid)";
		}
		elseif ($type == 'only')
		{
			$this->where[] = "((node.userid = $userid AND node.setfor <> 0) OR (node.userid <> 0 AND node.setfor  = $userid))";
		}
		elseif ($type == 'for')
		{
			$this->where[] = $this->make_equals_filter('node', 'setfor', $userid);
		}
		elseif ($type == 'exclude')
		{
			$this->where[] = $this->make_equals_filter('node', 'setfor', 0);
		}
	}

	protected function process_private_message_filter($userid, $type)
	{
		$pmcontentypeid = vB_Api::instanceInternal('contenttype')->fetchContentTypeIdFromClass('PrivateMessage');
		if ($type == 'include')
		{
			if (empty($userid))
			{
				$this->where[] = "node.contenttypeid <> $pmcontentypeid";
			}
			else
			{
				$this->join['sentto'] = "LEFT JOIN " . TABLE_PREFIX . "sentto AS sentto ON node.nodeid = sentto.nodeid AND sentto.userid = $userid AND sentto.deleted = 0";
				$this->where[] = "( node.contenttypeid <> $pmcontentypeid OR sentto.nodeid IS NOT NULL) ";
			}
		}
		elseif ($type == 'only')
		{
			if (empty($userid))
			{
				$this->where[] = "0 /** login to see private messages **/";
			}
			$this->join['sentto'] = "INNER JOIN " . TABLE_PREFIX . "sentto AS sentto ON node.contenttypeid = $pmcontentypeid AND node.nodeid = sentto.nodeid AND sentto.userid = $userid AND sentto.deleted = 0";
		}
	}

	protected function process_sort(vB_Search_Criteria &$criteria)
	{
		$sort_array = $criteria->get_sort();

		$this->sort = array();

		$sort_map = array
		(
			'user' => 'authorname',
			'author' => 'authorname',
			'publishdate' => 'publishdate',
			'created' => 'created',
			'started' => 'created',
			'last_post' => 'lastcontent',
			'lastcontent' => 'lastcontent',
			'title'  => 'title',
			'textcount' => 'textcount',
			'replies' => 'textcount',
			'displayorder' => 'displayorder',
			'rank'  => 'score',
			'relevance'  => 'score',

			'votes' => 'votes',
		);

		foreach ($sort_array as $sort => $direction)
		{
			// if we don't have a sort, or we have an unrecognized sort type default to relevance descending
			if (!$sort OR (!isset($sort_map[$sort])))
			{
				$sort = 'relevance';
				$direction = 'desc';
			}

			//look for a core sort option
			if (isset($sort_map[$sort]))
			{
				$sort_field = $sort_map[$sort];
				// process rank sortings
				if ($sort_field == 'score')
				{
					$sort_field = 'rank';
				}
				else
				{
					$sort_field = "node.$sort_field";
				}

				$this->sort[$sort_field] = $direction;
			}
		}
	}

	protected function process_unpublished_filters($time)
	{
		$this->where[] = "(node.showpublished = 1)";
	}

	protected function process_published_filters($time, $operator)
	{
		$this->where[] = "(node.showpublished " .  $this->operators[$operator]  . " $time)";
	}

	protected function process_marked_filter($marked)
	{
		$currentUserId = vB::getCurrentSession()->get('userid');
		if ($marked == vB_API_Search::FILTER_MARKED_READ AND !empty($currentUserId))
		{
			$nodeids = vB::getDbAssertor()->getColumn('noderead', 'nodeid',
					array(vB_dB_Query::CONDITIONS_KEY => array('userid' => $currentUserId)),
					false,
					'nodeid'
			);

			$this->where[] = $this->make_equals_filter('node', 'nodeid', $nodeids);
		}
		if ($marked == vB_API_Search::FILTER_MARKED_UNREAD AND !empty($currentUserId))
		{
			$this->join['noderead'] = "LEFT JOIN " . TABLE_PREFIX . "noderead AS noderead ON noderead.nodeid = node.nodeid";
			$this->where[] = "noderead.nodeid IS NULL";
		}
	}

	private static function make_equals_filter($table, $field, $value, $quote = true)
	{
		if (is_array($value) AND count($value)==1)
		{
			$value = array_pop($value);
		}
		if ($quote)
		{
			$value = self::quote_smart($value);
		}
		if (is_array($value))
		{
			return "$table.$field IN (" . implode(',', $value) . ")";
		}
		elseif ($table == 'in_set')
		{
			return "$field";
		}
		else
		{
			return "$table.$field = $value";
		}
	}

	private static function make_notequals_filter($table, $field, $value)
	{
		if (is_array($value) AND count($value)==1)
		{
			$value = array_pop($value);
		}
		$value = self::quote_smart($value);
		if (is_array($value))
		{
			return "$table.$field NOT IN (" . implode(',', $value) . ")";
		}
		elseif ($table == 'in_set')
		{
			return "NOT $field";
		}
		else
		{
			return "$table.$field <> $value";
		}
	}

	private static function make_range_filter($table, $field, $values)
	{
		//null mean infinity in a given direction
		if (!is_null($values[0]) AND !is_null($values[1]))
		{
			$values = self::quote_smart($values);
			return "($table.$field BETWEEN $values[0] AND $values[1])";
		}

		else if (!is_null($values[0]))
		{
			$value = self::quote_smart($values[0]);
			return "$table.$field >= $value";
		}

		else if (!is_null($values[1]))
		{
			$value = self::quote_smart($values[1]);
			return "$table.$field <= $value";
		}
	}

	/**
	 *	Function to turn a php variable into a database constant
	 *
	 *	Checks the type of the variable and handles accordingly.
	 * numeric types are left unaffected, they don't need special handling.
	 * booleans are converted to 0/1
	 * strings are escaped and quoted
	 * nulls are converted to the string 'null'
	 * arrays are recursively quoted and returned as an array.
	 *
	 *	@param $db object, used for quoting strings
	 * @param $value value to be quoted.
	 */
	private static function quote_smart($value)
	{
		if (is_string($value))
		{
			return "'" . vB::getDbAssertor()->escape_string($value) . "'";
		}

		//numeric types are safe.
		else if (is_int($value) OR is_float($value))
		{
			return $value;
		}

		else if (is_null($value))
		{
			return 'null';
		}

		else if (is_bool($value))
		{
			return $value ?  1 : 0;
		}

		else if (is_array($value))
		{
			foreach ($value as $key => $item)
			{
				$value[$key] = self::quote_smart($item);
			}
			return $value;
		}

		//unhandled type
		//this is likely to cause as sql error and unlikely to cause db corruption
		//might be better to throw an exception.
		else
		{
			return false;
		}
	}

	protected $rawlimit = "";
	protected $word_distance_factor = 7; //between 3 to 25
	protected $date_factor = 78883200; //913*24*3600
	protected $title_boost = 2;
	protected $occurance_factor = 1.5;
	protected $corejoin = array();
	protected $join = array();
	protected $what = "DISTINCT node.nodeid";
	protected $maintable = "node";
	protected $where = array();
	protected $from = "node as node";
	protected $score_order = array();
	protected $sort = "";
	protected $ranksort = "";
	protected $direction = "";
	protected $filtes = array();
	protected $done_permission_check = false;
	private static $field_map = array
	(
		'rank' => 'score',
		'relevance' => 'score',
		'author' => 'userid'
	);
}

/*======================================================================*\
|| ####################################################################
|| # SVN=> $Revision=> 28823 $
|| ####################################################################
\*======================================================================*/
