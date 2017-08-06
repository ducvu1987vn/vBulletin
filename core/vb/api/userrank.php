<?php

/**
 * vB_Api_Userrank
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Userrank extends vB_Api
{
	protected $styles = array();

	protected function __construct()
	{
		parent::__construct();
	}

	/**
	 * Fetch Userrank By RankID
	 *
	 * @param int $rankid Rank ID
	 * @return array User rank information
	 */
	public function fetchById($rankid)
	{
		$this->checkHasAdminPermission('canadminusers');

		$rank = vB::getDbAssertor()->getRow('vBForum:ranks', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'rankid' => intval($rankid),
		));

		if (!$rank)
		{
			throw new vB_Exception_Api('invalid_rankid');
		}

		return $rank;
	}

	/**
	 * Fetch All user ranks
	 *
	 * @return array Array of user ranks
	 */
	public function fetchAll()
	{
		$this->checkHasAdminPermission('canadminusers');

		return vB::getDbAssertor()->getRows('userrank_fetchranks', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
		));
	}

	/**
	 * Insert a new user rank or update existing user rank
	 *
	 * @param array $data User rank data to be inserted or updated
	 *              'ranklevel'   => Number of times to repeat rank
	 *              'usergroupid' => Usergroup
	 *              'minposts'    => Minimum Posts
	 *              'stack'       => Stack Rank. Boolean.
	 *              'display'     => Display Type. 0 - Always, 1 - If Displaygroup = This Group
	 *              'rankimg'     => User Rank File Path
	 *              'rankhtml'    => User Rank HTML Text
	 * @param int $rankid If not 0, it's the ID of the user rank to be updated
	 * @return int New rank's ID or updated rank's ID
	 */
	public function save($data, $rankid = 0)
	{
		$this->checkHasAdminPermission('canadminusers');

		$rankid = intval($rankid);
		require_once(DIR . '/includes/functions_ranks.php');

		if (!$data['ranklevel'] OR (!$data['rankimg'] AND !$data['rankhtml']))
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}

		if ($data['usergroupid'] == -1)
		{
			$data['usergroupid'] = 0;
		}

		if ($data['rankhtml'])
		{
			$type = 1;
			$data['rankimg'] = $data['rankhtml'];
		}
		else
		{
			$type = 0;
			if (!(@is_file(DIR . '/' . $data['rankimg'])))
			{
				throw new vB_Exception_Api('invalid_file_path_specified');
			}
		}

		if (!$rankid)
		{
			/*insert query*/
			$rankid = vB::getDbAssertor()->assertQuery('vBForum:ranks', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'ranklevel' => intval($data['ranklevel']),
				'minposts' => intval($data['minposts']),
				'rankimg' => trim($data['rankimg']),
				'usergroupid' => intval($data['usergroupid']),
				'type' => $type,
				'stack' => intval($data['stack']),
				'display' => intval($data['display']),
			));
			$rankid = $rankid[0];
		}
		else
		{
			/*update query*/
			$rankid = vB::getDbAssertor()->assertQuery('vBForum:ranks', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'ranklevel' => intval($data['ranklevel']),
				'minposts' => intval($data['minposts']),
				'rankimg' => trim($data['rankimg']),
				'usergroupid' => intval($data['usergroupid']),
				'type' => $type,
				'stack' => intval($data['stack']),
				'display' => intval($data['display']),
				vB_dB_Query::CONDITIONS_KEY => array(
					'rankid' => $rankid,
				)
			));

		}

		build_ranks();

		return $rankid;

	}

	/**
	 * Delete an user rank
	 *
	 * @param int $rankid The ID of user rank to be deleted
	 * @return void
	 */
	public function delete($rankid)
	{
		$this->checkHasAdminPermission('canadminusers');

		require_once(DIR . '/includes/functions_ranks.php');

		vB::getDbAssertor()->assertQuery('vBForum:ranks', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'rankid' => intval($rankid),
		));

		build_ranks();
	}
}
