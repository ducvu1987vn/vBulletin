<?php
if (!defined('VB_ENTRY')) die('Access denied.');
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
 * vB_Api_Content_Report
 *
 * @package vBApi
 * @author xiaoyu
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Library_Content_Report extends vB_Library_Content_Text
{
	//override in client- the text name
	protected $contenttype = 'vBForum_Report';

	//The table for the type-specific data.
	protected $tablename = array('report', 'text');

	protected $ReportChannel;

	/**
	 * If true, then creating a node of this content type will increment
	 * the user's post count. If false, it will not. Generally, this should be
	 * true for topic starters and replies, and false for everything else.
	 *
	 * @var	bool
	 */
	protected $includeInUserPostCount = false;

	protected function __construct()
	{
		parent::__construct();
		$this->ReportChannel = $this->nodeApi->fetchReportChannel();
	}

	/*** Adds a new node.
	 *
	 *	@param	mixed		Array of field => value pairs which define the record.
	 *  @param	array		Array of options for the content being created.
	 *						Available options include:
	 *
	 * 	@return	integer		the new nodeid
	 ***/
	public function add($data, array $options = array(), $convertWysiwygTextToBbcode = true)
	{
		$data['reportnodeid'] = intval($data['reportnodeid']);
		// Build node title based on reportnodeid
		if (!$data['reportnodeid'])
		{
			throw new vB_Exception_Api('invalid_report_node');
		}

		$data['parentid'] = $this->ReportChannel;

		if (empty($data['title']))
		{
			$reportnode = $this->nodeApi->getNodeFullContent($data['reportnodeid']);
			$reportnode = $reportnode[$data['reportnodeid']];

			$phraseapi = vB_Api::instanceInternal('phrase');

			if ($reportnode['nodeid'] == $reportnode['starter'])
			{
				// Thread starter
				$data['title'] = $reportnode['title'];
			}
			elseif ($reportnode['parentid'] == $reportnode['starter'])
			{
				$phrases = $phraseapi->fetch(array('reply_to'));
				$data['title'] = $phrases['reply_to'] . ' ' . $reportnode['startertitle'];
			}
			else
			{
				$phrases = $phraseapi->fetch(array('comment_in_a_topic'));
				$data['title'] = $phrases['comment_in_a_topic'] . ' ' . $reportnode['startertitle'];
			}
		}

		$nodeid = parent::add($data, $options, $convertWysiwygTextToBbcode);
		$this->nodeApi->clearCacheEvents(array($nodeid, $data['parentid']));
		return $nodeid;
	}

	public function getFullContent($nodeid, $permissions = false)
	{
		if (empty($nodeid))
		{
			return array();
		}

		$results = parent::getFullContent($nodeid, $permissions);
		$reportparentnode = array();

		foreach ($results as $key => $result)
		{
			try
			{
				$reportnode = $this->nodeApi->getNodeFullContent($results[$key]['reportnodeid']);
			}
			catch (vB_Exception_Api $e)
			{
				// The node probably does not exist.
				$results[$key]['reportnodeid'] = NULL;
				$results[$key]['reportnodetype'] = NULL;
				$results[$key]['reportparentnode'] = NULL;
				$results[$key]['reportnodetitle'] = NULL;
				$results[$key]['reportnoderouteid'] = NULL;
				continue;
			}
			if ($reportnode[$results[$key]['reportnodeid']]['nodeid'] == $reportnode[$results[$key]['reportnodeid']]['starter'])
			{
				$results[$key]['reportnodetype'] = 'starter';
			}
			elseif ($reportnode[$results[$key]['reportnodeid']]['parentid'] == $reportnode[$results[$key]['reportnodeid']]['starter'])
			{
				$results[$key]['reportnodetype'] = 'reply';

				//fetch parent info of reply (starter)
				$parentid = $reportnode[$results[$key]['reportnodeid']]['parentid'];
				if (!isset($reportparentnode[$parentid]))
				{
					$reportparentnode[$parentid] = $this->nodeApi->getNodeFullContent($parentid);
					$reportparentnode[$parentid] = $reportparentnode[$parentid][$parentid];
				}
				$results[$key]['reportparentnode'] = $reportparentnode[$parentid];
			}
			else
			{
				$results[$key]['reportnodetype'] = 'comment';

				//fetch parent info of comment (reply)
				$parentid = $reportnode[$results[$key]['reportnodeid']]['parentid'];
				if (!isset($reportparentnode[$parentid]))
				{
					$reportparentnode[$parentid] = $this->nodeApi->getNodeFullContent($parentid);
					$reportparentnode[$parentid] = $reportparentnode[$parentid][$parentid];
				}
				$results[$key]['reportparentnode'] = $reportparentnode[$parentid];
			}
			$results[$key]['reportnodetitle'] = $reportnode[$results[$key]['reportnodeid']]['title'];
			$results[$key]['reportnoderouteid'] = $reportnode[$results[$key]['reportnodeid']]['routeid'];
		}

		return $results;
	}

	/**
	 * Report is not allowed to be updated.
	 *
	 * @throws vB_Exception_Api
	 * @param $nodeid
	 * @param $data
	 * @return void
	 */
	public function update($nodeid, $data, $convertWysiwygTextToBbcode = true)
	{
		throw new vB_Exception_Api('not_implemented');
	}

	/**
	 * Open or close reports
	 *
	 * @param array $nodeids Array of node IDs
	 * @param string $op 'open' or 'close'
	 * @return void
	 */
	public function openClose($nodeids, $op)
	{
		if (is_numeric($nodeids))
		{
			$nodeids = array($nodeids);
		}

		if (is_string($nodeids) AND strpos($nodeids, ','))
		{
			$nodeids = explode(',', $nodeids);
		}

		if (!$nodeids OR !is_array($nodeids))
		{
			throw new vB_Exception_Api('invalid_param', array('nodeid'));
		}

		// Not sure why it doesn't work
//		foreach ($nodeids as &$nodeid)
//		{
//			$nodeid = intval($nodeid);
//		}
//
//		$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
//			vB_dB_Query::CONDITIONS_KEY =>array('nodeid' => $nodeids),
//			'closed' => ($op == 'open'? 0 : 1));
//
//		$this->assertor->assertQuery('vBForum:report', $data);

		foreach ($nodeids as $nodeid)
		{
			$data = array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				vB_dB_Query::CONDITIONS_KEY =>array('nodeid' => intval($nodeid)),
				'closed' => ($op == 'open'? 0 : 1));

			$this->assertor->assertQuery('vBForum:report', $data);
		}

		$this->nodeApi->clearCacheEvents($nodeids);
	}

	/**
	 * Delete one or more reports
	 *
	 * @throws vB_Exception_Api
	 * @param $nodeids
	 * @return void
	 */
	public function bulkdelete($nodeids)
	{
		if (is_numeric($nodeids))
		{
			$nodeids = array($nodeids);
		}

		if (is_string($nodeids) AND strpos($nodeids, ','))
		{
			$nodeids = explode(',', $nodeids);
		}

		if (!$nodeids OR !is_array($nodeids))
		{
			throw new vB_Exception_Api('invalid_param', array('nodeid'));
		}

		foreach ($nodeids as $nodeid)
		{
			$this->delete($nodeid);
		}
	}
}
