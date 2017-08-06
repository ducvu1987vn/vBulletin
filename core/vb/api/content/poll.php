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
 * vB_Api_Content_Poll
 *
 * @package vBApi
 * @author ebrown
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Api_Content_Poll extends vB_Api_Content_Text
{
	//override in client- the text name
	protected $contenttype = 'vBForum_Poll';

	//The table for the type-specific data.
	protected $tablename = array('poll', 'text');

	//When we parse the page.
	protected $bbcode_parser = false;

	//Whether we change the parent's text count- 1 or zero
	protected $textCountChange = 1;

	protected $tableFields = array();
	//for spam checking
	protected $spamType = false;
	protected $spamKey = false;
	protected $akismet;

	protected function __construct()
	{
		parent::__construct();
		$this->library = vB_Library::instance('Content_Poll');

		//see if we have spam checking set.
		if (isset($this->options['vb_antispam_type']) AND $this->options['vb_antispam_type'] > 0 AND !empty($this->options['vb_antispam_key']))
		{
			$this->spamType = $this->options['vb_antispam_type'];
			$this->spamKey = $this->options['vb_antispam_key'];
		}
	}

	/*** Permanently deletes a node
	 *	@param	integer	The nodeid of the record to be deleted
	 *
	 *	@return	boolean
	 ***/
	public function delete($nodeid)
	{
		$loginuser = &vB::getCurrentSession()->fetch_userinfo();
		$usercontext = &vB::getUserContext($loginuser['userid']);

		if (!$usercontext->getCanModerate($nodeid))
		{
			throw new Exception('no_permission');
		}
		return $this->library->delete($nodeid);
	}

	public function vote($polloptionids)
	{
		$usercontext = &vB::getUserContext();

		if (is_numeric($polloptionids))
		{
			$polloptionids = array($polloptionids);
		}
		elseif (!is_array($polloptionids))
		{
			throw new Exception('invalidparameter');
		}

		$options = array();
		$nodeid = 0;
		foreach ($polloptionids as $polloptionid)
		{
			$option = $this->assertor->getRow('vBForum:polloption', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'polloptionid' => intval($polloptionid),
			));

			if ($nodeid AND ($nodeid != $option['nodeid']))
			{
				throw new Exception('invalidvote');
			}

			if (!$usercontext->getChannelPermission('forumpermissions', 'canvote', $option['nodeid']))
			{
				throw new Exception('no_permission');
			}

			$options[] = $option;
			$nodeid = $option['nodeid'];
		}
		unset($option);

		$polls = $this->getContent($nodeid);
		if(empty($polls) OR empty($polls[$nodeid]))
		{
			return false;
		}

		// Check if the poll is timeout
		if ($polls[$nodeid]['timeout'] AND $polls[$nodeid]['timeout'] < vB::getRequest()->getTimeNow())
		{
			return false;
		}

		// Check if the user has voted the poll
		if ($this->checkVoted($nodeid))
		{
			return false;
		}

		$nodeid = $this->library->vote($options);

		// All options should be in a same poll
		$this->updatePollCache($nodeid, true);

		return $nodeid;
	}

	public function updatePollCache($nodeid, $updatelastvote = false)
	{
		$pollInfo = $this->assertor->getRows('vBForum:poll', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'nodeid' => $nodeid
		));

		// Update poll table's options
		// Get options
		$options = $this->assertor->getRows('vBForum:polloption', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'nodeid' => $nodeid
		));
		$queryDef = 'vBForum:poll_fetchvotes';
		if($pollInfo[0]['multiple'] == 1)
		{
			$queryDef = 'vBForum:poll_fetchvotes_multiple';
		}

		$optionstosave = array();
		$totalvotes = 0;
		foreach ($options as $option)
		{
			$option['voters'] = @unserialize($option['voters']);
			$totalvotes += $option['votes'];
			$optionstosave[$option['polloptionid']] = $option;
		}
		unset($options, $option);
		$voters = $this->assertor->getField($queryDef, array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'nodeid' => $nodeid,
		));

		foreach ($optionstosave as &$option)
		{
			if ($totalvotes)
			{
				$option['percentage'] = number_format(($option['votes'] / $totalvotes) * 100, 2);
			}
			else
			{
				$option['percentage'] = 0;
			}
		}

		$data = array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'options' => serialize($optionstosave),
			'votes'   => $voters,
			vB_dB_Query::CONDITIONS_KEY => array(
				'nodeid' => $nodeid
			)
		);

		if ($updatelastvote)
		{
			$data['lastvote'] = vB::getRequest()->getTimeNow();
		}

		$this->assertor->assertQuery('vBForum:poll', $data);
		$nodelib = vB_Library::instance('node');
		$nodelib->clearCacheEvents(array($nodeid));
	}

	protected function checkVoted($nodeid)
	{
		$loginuser = &vB::getCurrentSession()->fetch_userinfo();
		if (!$loginuser['userid'])
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
		$uservoteinfo = vB::getDbAssertor()->getRow('vBForum:pollvote', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'userid' => $loginuser['userid'],
			'nodeid' => $nodeid,
		));

		if ($uservoteinfo)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Adds content info to $result so that merged content can be edited.
	 * @param array $result
	 * @param array $content
	 */
	public function mergeContentInfo(&$result, $content)
	{
		if (vb::getUserContext()->getChannelPermission('forumpermissions', 'canviewthreads', $result['nodeid']))
		{
			$this->library->mergeContentInfo($result, $content);
		}
	}

	/**
	 * Performs the merge of content and updates the node.
	 * @param type $data
	 * @return type
	 */
	public function mergeContent($data)
	{
		return $this->library->mergeContent($data);
	}
}
