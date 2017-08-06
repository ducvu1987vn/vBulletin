<?php
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
* Class to do data save/delete operations for infractions
*
* @package	vBulletin
* @version	$Revision: 41072 $
* @date		$Date: 2010-12-14 14:36:30 -0200 (Tue, 14 Dec 2010) $
*/
class vB_DataManager_Infraction extends vB_DataManager
{
	/**
	* Array of recognised and required fields for infractions, and their types
	*
	* @var	array
	*/
	public $validfields = array(
		'infractionid'      => array(vB_Cleaner::TYPE_UINT,      vB_DataManager_Constants::REQ_INCR, vB_DataManager_Constants::VF_METHOD, 'verify_nonzero'),
		'nodeid'            => array(vB_Cleaner::TYPE_UINT,      vB_DataManager_Constants::REQ_YES),
		'infractionlevelid' => array(vB_Cleaner::TYPE_UINT,      vB_DataManager_Constants::REQ_NO,   vB_DataManager_Constants::VF_METHOD),
		'userid'            => array(vB_Cleaner::TYPE_UINT,      vB_DataManager_Constants::REQ_YES),
		'whoadded'          => array(vB_Cleaner::TYPE_UINT,      vB_DataManager_Constants::REQ_YES),
		'points'            => array(vB_Cleaner::TYPE_UINT,      vB_DataManager_Constants::REQ_YES),
		'dateline'          => array(vB_Cleaner::TYPE_UNIXTIME,  vB_DataManager_Constants::REQ_AUTO),
		'note'              => array(vB_Cleaner::TYPE_NOHTML,    vB_DataManager_Constants::REQ_NO),
		'action'            => array(vB_Cleaner::TYPE_UINT,      vB_DataManager_Constants::REQ_NO),
		'actiondateline'    => array(vB_Cleaner::TYPE_UNIXTIME,  vB_DataManager_Constants::REQ_NO),
		'actionuserid'      => array(vB_Cleaner::TYPE_UINT,      vB_DataManager_Constants::REQ_NO),
		'actionreason'      => array(vB_Cleaner::TYPE_NOHTML,    vB_DataManager_Constants::REQ_NO),
		'postid'            => array(vB_Cleaner::TYPE_UINT,      vB_DataManager_Constants::REQ_NO),
		'expires'           => array(vB_Cleaner::TYPE_UNIXTIME,  vB_DataManager_Constants::REQ_NO),
		'threadid'          => array(vB_Cleaner::TYPE_UINT,      vB_DataManager_Constants::REQ_NO),
		'channelid'          => array(vB_Cleaner::TYPE_UINT,     vB_DataManager_Constants::REQ_NO),
		'customreason'      => array(vB_Cleaner::TYPE_NOHTML,    vB_DataManager_Constants::REQ_NO),
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	public $table = 'infraction';

	protected $nodeid;
	protected $node;
	protected $contentApi;
	protected $userInfo;
	protected $keyField = 'infractionid';
	protected $infraction = array();

	/** flag for vb5 transition. Setting this to false tells the parent not to load $vbulletin **/
	protected $needRegistry = false;
	/**
	* Verifies that the infractionlevelid is valid and set points and expires if user hasn't explicitly set them
	*
	* @param	integer	infractionleveid key
	*
	* @return	boolean
	*/
	public function verify_infractionlevelid(&$infractionlevelid)
	{
		if (empty($this->existing) OR empty($this->existing['infractionlevelid'])
			OR ($infractionlevelid != $this->existing['infractionlevelid']))
		{
			$infractionlevel = $this->assertor->getRow('infractionlevel', array(vB_dB_Query::TYPE_KEY =>vB_dB_Query::QUERY_SELECT,
				'infractionlevelid' => $infractionlevelid) );

			if (!$infractionlevel)
			{
				$this->error('invalidid');
				return false;
			}
			else if (!empty($infractionlevel['errors']))
			{
				$this->error($infractionlevel['errors']);
				return false;
			}
			else
			{
				if (!isset($this->setfields['points']) OR  !$this->setfields['points'])
				{
					$points = intval($infractionlevel['points']);
					if ($infractionlevel['warning'] AND (empty($this->info)
						OR $this->info['warning']))
					{
						$points = 0;
					}
					$this->set('points', $points);
				}

				if (!isset($this->setfields['expires']))
				{
					switch($infractionlevel['period'])
					{
						case 'H': $expires = vB::getRequest()->getTimeNow() + $infractionlevel['expires'] * 3600; break;     # HOURS
						case 'D': $expires = vB::getRequest()->getTimeNow() + $infractionlevel['expires'] * 86400; break;    # DAYS
						case 'M': $expires = vB::getRequest()->getTimeNow() + $infractionlevel['expires'] * 2592000; break;  # MONTHS
						case 'N': $expires = 0; break;                                                # NEVER
					}
					$this->set('expires', $expires);
				}
			}
		}

		return true;
	}

	/**
	* Updates user's infraction group ids. Call whenever user.ipoints is modified, Do not call from pre_save()
	*
	* @param	integer	Action status of infraction before save
	* @param	integer	Points awarded for this infraction
	*
	*/
	public function update_infraction_groups($action, $points)
	{
		if ($action OR !$points)
		{	// Don't go forward if this item didn't start out active or doesn't have any points (warning)
			return false;
		}

		if ($userinfo = $this->info['userinfo'] OR ($this->existing['userid'] AND
			$userinfo = $this->assertor->getRow('user', array('userid' =>$this->existing['userid']))))		{
			// Fetch latest total points for this user

			;
			if ($pointinfo = $this->assertor->getRow('user', array('userid' => $userinfo[userid])))
			{
				$infractiongroupid = 0;
				$infractiongroupids = array();

				if (empty($pointinfo['usergroups']))
				{
					$usergroups = array($pointinfo['usergroupid']);
				}
				else
				{
					$usergroups = explode(',', $pointinfo['usergroups']);
					$usergroups[] = $pointinfo['usergroupid'];

				}
				$groups = $this->assertor->assertQuery('infractiongroup',
					array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					vB_dB_Query::CONDITIONS_KEY => array
					(
						array('field' => 'usergroupid',
						'value' => $usergroups,
						'operator' => vB_dB_Query::OPERATOR_EQ),
						array('field' => 'pointlevel',
						'value' => $pointinfo[ipoints],
						'operator' => vB_dB_Query::OPERATOR_LTE)

					)), 'pointlevel');

				if ($groups->valid())
				{
					$group = $groups->current();
				}

				while ($groups->valid())
				{
					if ($group['override'])
					{
						$infractiongroupid = $group['orusergroupid'];
					}
					$infractiongroupids["$group[orusergroupid]"] = true;

					$group = $groups->next();
				}
				$userdata = new vB_Datamanager_User(vB_DataManager_Constants::ERRTYPE_SILENT);
				$userdata->set_existing($userinfo);
				$userdata->set('infractiongroupids', !empty($infractiongroupids) ? implode(',', array_keys($infractiongroupids)) : '');
				$userdata->set('infractiongroupid', $infractiongroupid);
				$userdata->save();
				unset($userdata);
			}
		}
	}

	/**
	* Resets infraction information in user and post record after a reversal or removal
	*
	*/
	public function reset_infraction()
	{
		if (empty($this->existing) OR empty($this->existing['action']))
		{
			$this->existing = $this->assertor->getRow('infraction', array('infractionid' => $this->infraction['infractionid']));
		}

		if ($this->existing['action'] != 0)
		{	// Only reset infraction information for an active infraction. Expired and reversed infractions have already done this
			return;
		}

		if ($this->existing['nodeid'])
		{
			if (empty($this->contentApi))
			{
				$node = $this->assertor->getRow('vBForum:node', array('nodeid' => $this->existing['nodeid']));
				$class = vB_Types::instance()->getClassName($node['contenttypeid']);
				$this->contentApi = vB_Api::instanceInternal('Content_' . $class);
			}
			$this->contentApi->update($this->existing['nodeid'], array('infraction' => 0));
			$this->nodeid = $this->existing['nodeid'];
		}

		if (empty($this->info['userinfo']))
		{
			$this->info['userinfo'] = $this->assertor->getRow('user', array('userid' => $this->existing['userid']));
		}

		if ($userinfo = $this->info['userinfo'] OR ($this->existing['userid'] AND
			$userinfo = $this->assertor->getRow('user', array('userid' =>$this->existing['userid']))))
		{	// Decremement infraction counters and remove any points
			$userdata = new vB_Datamanager_User(vB_DataManager_Constants::ERRTYPE_SILENT);
			$userdata->set_existing($userinfo);
			if ($points = $this->existing['points'])
			{
				$userdata->set('ipoints', "ipoints - $points", false);
				$userdata->set('infractions', 'infractions - 1', false);
			}
			else
			{
				$userdata->set('warnings', 'warnings - 1', false);
			}
			$userdata->save();
			unset($userdata);
		}
	}

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	mixed		We are removing references to registry, but for now we need to keep
	* @param	integer		One of the ERRTYPE_x constants
	*/
	public function __construct($registry = NULL, $errtype = NULL)
	{
		parent::vB_DataManager($errtype);

		// Legacy Hook 'infractiondata_start' Removed //
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	public function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->fetch_field('userid') AND $this->info['userinfo']['userid'])
		{
			$this->set('userid', $this->info['userinfo']['userid']);
		}

		if (!$this->fetch_field('dateline') AND !$this->condition)
		{
			$this->set('dateline', TIMENOW);
		}

		if (!$this->fetch_field('action') AND !$this->condition)
		{	// active infraction
			$this->set('action', 0);
		}

		$return_value = true;
		// Legacy Hook 'infractiondata_presave' Removed //

		$this->presave_called = $return_value;
		return $return_value;
	}

	public function post_save_each($doquery = true)
	{
		global $vbphrase;


		if (empty($this->existing))
		{
			//
			if (!empty($this->infraction['nodeid']))
			{
				$nodeid = $this->infraction['nodeid'];
				if (empty($this->contentApi))
				{
					$node = $this->assertor->getRow('vBForum:node', array('nodeid' => $nodeid));
					$class = vB_Types::instance()->getContentTypeClass($node['contenttypeid']);
					if ($class = 'Text')
					{
						$this->contentApi = vB_Api::instanceInternal('Content_' . $class);
					}
					$this->contentApi->update($this->infraction['nodeid'], array('infraction' => ($this->fetch_field('points') == 0) ? 1 : 2,
						'publishdate'=> 0, 'showpublished' => 0));
				}

			}

			if (empty( $this->info['userinfo']) OR ($this->info['userinfo'] !== $this->infraction['userid']))
			{
				$this->info['userinfo'] = $this->assertor->getRow('user', array('userid' => $this->infraction['userid']));
			}


			if ($userinfo = $this->info['userinfo'])
			{
				$userdata = new vB_Datamanager_User(vB_DataManager_Constants::ERRTYPE_SILENT);
				$userdata->set_existing($userinfo);
				if ($points = $this->fetch_field('points'))
				{
					$userdata->set('ipoints', "ipoints + $points", false);
					$userdata->set('infractions', 'infractions + 1', false);
				}
				else
				{
					$userdata->set('warnings', 'warnings + 1', false);
				}
				$userdata->save();
				unset($userdata);

				if ($points)
				{
					$this->update_infraction_groups($this->fetch_field('action'), $points);
				}

				// Insert starter node
				$options = $this->datastore->get_value('options');
				if (!empty($options['uichannelid']))
				{

					$phraseAux = vB_Api::instanceInternal('phrase')->fetch(array('infractionlevel' . $this->fetch_field('infractionlevelid') . '_title'));
					$infractioninfo = array(
						'title'       => $this->fetch_field('customreason') ? unhtmlspecialchars($this->fetch_field('customreason')) : $phraseAux['infractionlevel' . $this->fetch_field('infractionlevelid') . '_title'],
						'points'      => $points,
						'note'        => unhtmlspecialchars($this->fetch_field('note')),
						'message'     => $this->info['message'],
						'username'    => unhtmlspecialchars($userinfo['username']),
						'threadtitle' => unhtmlspecialchars($threadinfo['title']),
					);

					$channelinfo = $this->assertor->getRow('vBForum:channel',
						array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						'nodeid' => $this->options['uichannelid']));
					if ($channelinfo['prefixid'])
					{
						// need prefix in correct language
						$phraseAux = vB_Api::instanceInternal('phrase')->fetch(array("prefix_$threadinfo[prefixid]_title_plain"));
						$infractioninfo['prefix_plain'] = $phraseAux["prefix_$threadinfo[prefixid]_title_plain"] . ' ';
					}
					else
					{
						$infractioninfo['prefix_plain'] = '';
					}

					//variables for phrase eval below
					if ($postinfo)
					{
						$infractioninfo['postlink'] = fetch_seo_url('thread|nosession|bburl|js',
							array('threadid' => $threadinfo['threadid'], 'title' => $infractioninfo['threadtitle']),
							array('p' => $postinfo['postid'])) . "#post$postinfo[postid]";
					}

					$infractioninfo['userlink'] = vB5_Route::buildUrl('profile|nosession|bburl|js',
						array('userid' => $userinfo['userid'], 'username' => $infractioninfo['username']));

					//creates magic vars $subject and $message -- uses variables from current scope.
					eval(fetch_email_phrases($postinfo ? 'infraction_thread_post' : 'infraction_thread_profile', 0,
						$points > 0 ? 'infraction_thread_infraction' : 'infraction_thread_warning'));

					$api = vB_Api::instanceInternal('Content_Text');
					$updates = array('title' => $subject,
						'userid' => $this->fetch_field('whoadded'),
						'pagetext' => $message, 'parentid' => $this->options['uichannelid'],
						'publishdate' => vB::getRequest()->getTimeNow());

					$this->nodeid = $api->add($updates);

					$this->assertor->assertQuery('infraction', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
					'infractionid' => $this->existing['infractionid'], 'starterid' => $this->nodeid));
				}

			}
		}
		else if ($this->setfields['action'] AND ($this->fetch_field('action') == 1 OR $this->fetch_field('action') == 2))
		{
			$this->reset_infraction();
			$this->update_infraction_groups($this->existing['action'], $this->existing['points']);

			if ($this->fetch_field('action') == 2)
			{	// Reversed
				if (empty($this->nodeid) and !empty($this->existing['nodeid']))
				{
					$this->nodeid = $this->existing['nodeid'];
				}
				$api = vB_Api::instanceInternal('Content_Text');
				$api->setPublished($this->nodeid);
			}
		}

		// Legacy Hook 'infractiondata_postsave' Removed //
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	public function post_delete($doquery = true)
	{
		$this->reset_infraction();
		$this->update_infraction_groups($this->existing['action'], $this->existing['points']);

		// Legacy Hook 'infractiondata_delete' Removed //
		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 41072 $
|| ####################################################################
\*======================================================================*/

