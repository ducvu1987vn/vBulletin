<?php

/**
 * vB_Api_Cron
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Cron extends vB_Api
{
	protected $disableWhiteList = array('nextRun');

	protected $styles = array();

	protected function __construct()
	{
		parent::__construct();
	}

	/**
	 * Run cron
	 *
	 * @param bool $noshutdownfunc Set to true to run directly rather than to run in shutdown function
	 * @return bool
	 */
	public function run($noshutdownfunc = false)
	{
		require_once(DIR . '/includes/functions_cron.php');

		$options = vB::getDatastore()->get_value('options');

		if (!$noshutdownfunc AND empty($options['crontab']))
		{
			vB_Shutdown::instance()->add('exec_cron');
		}
		else
		{
			$cronid = NULL;
			if (!empty($options['crontab']) AND php_sapi_name() == 'cli')
			{
				$cronid = intval($_SERVER['argv'][1]);
				// if its a negative number or 0 set it to NULL so it just grabs the next task
				if ($cronid < 1)
				{
					$cronid = NULL;
				}
			}

			exec_cron($cronid);
		}

		return true;
	}

	/**
	 * Run a cron by its ID or varname
	 *
	 * @param int $cronid Cron Id
	 * @param string $varname Varname
	 * @return void
	 */
	public function runOne($cronid = 0, $varname = '')
	{
		$this->checkHasAdminPermission('canadmincron');

		$nextitem = null;
		$cronid = intval($cronid);
		$varname = trim($varname);

		if ($cronid)
		{
			$nextitem = vB::getDbAssertor()->getRow('cron', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'cronid' => $cronid,
			));
		}
		else if ($varname)
		{
			$nextitem = vB::getDbAssertor()->getRow('cron', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'varname' => $varname,
			));
		}

		if ($nextitem)
		{
			ignore_user_abort(1);
			@set_time_limit(0);

			// Force custom scripts to use $vbulletin->db to follow function standards of only globaling $vbulletin
			// This will cause an error to be thrown when a script is run manually since it will silently fail when cron.php runs if $db-> is accessed

			require_once(DIR . '/includes/functions_cron.php');
			include(DIR . '/' . $nextitem['filename']);
		}
		else
		{
			throw new vB_Exception_Api('invalid_action_specified_gerror');
		}
	}

	/**
	 * Fetch a cron by its ID
	 *
	 * @param int $cronid
	 * @return array Cron information
	 */
	public function fetchById($cronid)
	{
		$this->checkHasAdminPermission('canadmincron');

		$cron = vB::getDbAssertor()->getRow('cron', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'cronid' => $cronid,
		));

		return $this->loadCron($cron);
	}

	public function nextRun()
	{
		$nextrun = vB::getDatastore()->getValue('cron');
		return $nextrun ? $nextrun : 0;
	}

	private function loadCron(&$cron)
	{
		if (!$cron)
		{
			$vboptions = vB::getDatastore()->get_value('options');
			throw new vB_Exception_Api('invalidid', array('cronid'));
		}

		$title = 'task_' . $cron['varname'] . '_title';
		$desc = 'task_' . $cron['varname'] . '_desc';
		$logphrase = 'task_' . $cron['varname'] . '_log';

		if (is_numeric($cron['minute']))
		{
			$cron['minute'] = array(0 => $cron['minute']);
		}
		else
		{
			$cron['minute'] = unserialize($cron['minute']);
		}

		$phrases = vB::getDbAssertor()->assertQuery('cron_fetchphrases', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'languageid' => ($cron['volatile'] ? -1 : 0),
			'title' => $title,
			'desc' => $desc,
			'logphrase' => $logphrase,
		));
		foreach ($phrases as $phrase)
		{
			if ($phrase['varname'] == $title)
			{
				$cron['title'] = $phrase['text'];
				$cron['titlevarname'] = $title;
			}
			else if ($phrase['varname'] == $desc)
			{
				$cron['description'] = $phrase['text'];
				$cron['descvarname'] = $desc;
			}
			else if ($phrase['varname'] == $logphrase)
			{
				$cron['logphrase'] = $phrase['text'];
				$cron['logvarname'] = $logphrase;
			}
		}

		return $cron;
	}


	public function fetchByVarName($varName)
	{
		$this->checkHasAdminPermission('canadmincron');

		$cron = vB::getDbAssertor()->getRow('cron', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'varname' => $varName,
		));
		return $this->loadCron($cron);
	}

	/**
	 * Fetch All crons
	 *
	 * @return array Crons
	 */
	public function fetchAll()
	{
		$this->checkHasAdminPermission('canadmincron');

		$crons = vB::getDbAssertor()->getRows('cron_fetchall', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
		));

		return $crons;
	}

	/**
	 * Insert a new cron or Update an existing cron
	 *
	 * @param array $data Cron data to be inserted or updated
	 *              'varname'     => Varname
	 *              'filename'    => Filename
	 *              'title'       => Title
	 *              'description' => Description
	 *              'logphrase'   => Log Phrase
	 *              'weekday'     => Day of the Week (Note: this overrides the 'day of the month' option)
	 *              'day'         => Day of the Month
	 *              'hour'        => Hour
	 *              'minute'      => Minute
	 *              'active'      => Active. Boolean.
	 *              'loglevel'    => Log Entries. Boolean.
	 *              'product'     => Product
	 *              'volatile'    => vBulletin Default. Boolean.
	 * @param int $cronid If not 0, it's the cron ID to be updated
	 * @return int New cron ID or updated Cron's ID
	 */
	public function save($data, $cronid = 0)
	{
		$this->checkHasAdminPermission('canadmincron');

		$cronid = intval($cronid);
		$vb5_config = vB::getConfig();
		$userinfo = vB::getDatastore()->get_value('userinfo');

		if (empty($cronid))
		{
			if (empty($data['varname']))
			{
				throw new vB_Exception_Api('please_complete_required_fields');
			}

			if (!preg_match('#^[a-z0-9_]+$#i', $data['varname'])) // match a-z, A-Z, 0-9, _ only
			{
				throw new vB_Exception_Api('invalid_phrase_varname');
			}

			if (vB::getDbAssertor()->getRow('cron', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'varname' => $data['varname'],
				)))
			{
				throw new vB_Exception_Api('there_is_already_option_named_x', array($data['varname']));
			}

			if (empty($data['title']))
			{
				throw new vB_Exception_Api('please_complete_required_fields');
			}
		}
		else
		{
			$cron = vB::getDbAssertor()->getRow('cron', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'cronid' => $cronid,
			));
			if (!$cron)
			{
				throw new vB_Exception_Api('invalid_option_specified');
			}

			if ((!$cron['volatile'] OR $vb5_config['Misc']['debug']) AND empty($data['title']))
			{
				// custom entry or in debug mode means the title is editable
				throw new vB_Exception_Api('please_complete_required_fields');
			}

			$data['varname'] = $cron['varname'];
		}

		if ($data['filename'] == '' OR $data['filename'] == './includes/cron/.php')
		{
			throw new vB_Exception_Api('invalid_filename_specified');
		}

		$data['weekday']	= str_replace('*', '-1', $data['weekday']);
		$data['day']		= str_replace('*', '-1', $data['day']);
		$data['hour']		= str_replace('*', '-1', $data['hour']);

		// need to deal with minute properly :)
		sort($data['minute'], SORT_NUMERIC);
		$newminute = array();
		foreach ($data['minute'] AS $time)
		{
			$newminute["$time"] = true;
		}

		unset($newminute["-2"]); // this is the "-" (don't run) entry

		if ($newminute["-1"])
		{ // its run every minute so lets just ignore every other entry
			$newminute = array(0 => -1);
		}
		else
		{
			// $newminute's keys are the values of the GPC variable, so get the values back
			$newminute = array_keys($newminute);
		}

		if (empty($cronid))
		{
			/*insert query*/
			$cronid = vB::getDbAssertor()->assertQuery('cron', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'varname' => trim($data['varname']),
			));
			$cronid = $cronid[0];
		}
		else
		{
			// updating an entry. If we're changing the volatile status, we
			// need to remove the entries in the opposite language id.
			// Only possible in debug mode.
			if ($data['volatile'] != $cron['volatile'])
			{
				$old_languageid = ($cron['volatile'] ? -1 : 0);
				vB::getDbAssertor()->assertQuery('phrase', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
					'languageid' => $old_languageid,
					'fieldname' => 'cron',
					'varname' => array('task_$cron[varname]_title', 'task_$cron[varname]_desc', 'task_$cron[varname]_log'),
				));
			}
		}

		$escaped_product = $data['product'];

		// update
		vB::getDbAssertor()->assertQuery('cron', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'loglevel' => intval($data['loglevel']),
			'weekday' => intval($data['weekday']),
			'day' => intval($data['day']),
			'hour' => intval($data['hour']),
			'minute' => serialize($newminute),
			'filename' => $data['filename'],
			'active' => $data['active'],
			'volatile' => $data['volatile'],
			'product' => $data['product'],
			vB_dB_Query::CONDITIONS_KEY => array(
				'cronid' => $cronid,
			)
		));

		$new_languageid = ($data['volatile'] ? -1 : 0);

		require_once(DIR . '/includes/adminfunctions.php');
		$full_product_info = fetch_product_list(true);
		$product_version = $full_product_info["$escaped_product"]['version'];

		if (!$data['volatile'] OR $vb5_config['Misc']['debug'])
		{
			/*insert_query*/
			vB::getDbAssertor()->assertQuery('cron_insertphrases', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
				'new_languageid' => $new_languageid,
				'varname' => $data['varname'],
				'product' => $data['product'],
				'username' => $userinfo['username'],
				'timenow' => vB::getRequest()->getTimeNow(),
				'product_version' => $product_version,
				'title' => trim($data['title']),
				'description' => trim($data['description']),
				'logphrase' => trim($data['logphrase']),
			));

			require_once(DIR . '/includes/adminfunctions_language.php');
			build_language();
		}

		require_once(DIR . '/includes/functions_cron.php');
		build_cron_item($cronid);
		build_cron_next_run();

		return $cronid;
	}

	/**
	 * Update enable status of crons
	 *
	 * @param array $crons An array with cronid as key and status as value
	 * @return void
	 */
	public function updateEnabled($crons)
	{
		$this->checkHasAdminPermission('canadmincron');

		$updates = array();

		$crons_result = vB::getDbAssertor()->getRows('cron', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT));
		foreach ($crons_result as $cron)
		{
			if (isset($crons["$cron[cronid]"]))
			{
				$old = $cron['active'] ? 1 : 0;
				$new = $crons["$cron[cronid]"] ? 1 : 0;

				if ($old != $new)
				{
					$updates["$cron[varname]"] = $new;
				}
			}
		}

		if (!empty($updates))
		{
			vB::getDbAssertor()->assertQuery('updateCronEnabled', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'updates' => $updates,
			));
		}

	}

	/**
	 * Delete a cron
	 *
	 * @param int $cronid Cron ID to be deleted
	 * @return void
	 */
	public function delete($cronid)
	{
		$this->checkHasAdminPermission('canadmincron');

		$cronid = intval($cronid);

		$cron = vB::getDbAssertor()->getRow('cron', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'cronid' => $cronid,
		));

		// delete phrases
		vB::getDbAssertor()->assertQuery('phrase', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'fieldname' => 'cron',
			'varname' => array('task_{$escaped_varname}_title', 'task_{$escaped_varname}_desc', 'task_{$escaped_varname}_log'),
		));

		vB::getDbAssertor()->assertQuery('cron', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE,
			'cronid' => $cronid,
		));

		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language();
	}

	/**
	 * Toggle the enable/disable status of a cron
	 *
	 * @param int $cronid Cron ID
	 * @return void
	 */
	public function switchActive($cronid)
	{
		$this->checkHasAdminPermission('canadmincron');

		$cronid = intval($cronid);

		$cron = vB::getDbAssertor()->getRow('cron_fetchswitch', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'cronid' => $cronid
		));

		if (!$cron)
		{
			$vboptions = vB::getDatastore()->get_value('options');
			throw new vB_Exception_Api('invalidid', array('cronid'));
		}
		else if (!$cron['product_active'])
		{
			throw new vB_Exception_Api('task_not_enabled_product_x_disabled', array(htmlspecialchars_uni($cron['product_title'])));
		}

		vB::getDbAssertor()->assertQuery('cron_switchactive', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED,
			'cronid' => $cronid
		));

		require_once(DIR . '/includes/functions_cron.php');
		build_cron_item($cronid);
		build_cron_next_run();

	}

	/**
	 * Fetch cron log
	 *
	 * @param string $varname Show Only Entries Generated By the cron with this varname. '0' means show all crons' log.
	 * @param $orderby Cron log show order
	 * @param $page Page of the cron log list
	 * @param int $perpage Number of entries to show per page
	 * @return array Cron log information
	 */
	public function fetchLog($varname = '', $orderby = '', $page = 1, $perpage = 15)
	{
		$this->checkHasAdminPermission('canadmincron');

		if (empty($perpage))
		{
			$perpage = 15;
		}

		$total = vB::getDbAssertor()->getField('fetchCronLogCount', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'varname' => $varname,
		));

		$totalpages = ceil($total / $perpage);

		$logs = vB::getDbAssertor()->getRows('fetchCronLog', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'varname' => $varname,
			'orderby' => $orderby,
			vB_dB_Query::PARAM_LIMITPAGE => $page,
			vB_dB_Query::PARAM_LIMIT => $perpage,
		));

		return array(
			'logs' => $logs,
			'total' => $total,
		);
	}

	/**
	 * Prune Cron
	 *
	 * @param string $varname Remove Entries Relating to Action.
	 * @param int $daysprune Remove Entries Older Than (Days)
	 * @return void
	 */
	public function pruneLog($varname = '', $daysprune = 30)
	{
		$this->checkHasAdminPermission('canadmincron');

		$datecut = vB::getRequest()->getTimeNow() - (86400 * $daysprune);

		vB::getDbAssertor()->assertQuery('pruneCronLog', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'varname' => trim($varname),
			'datecut' => $datecut,
		));

	}
}
