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
/*
if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}
*/

class vB_Upgrade_500b22 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500b22';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Beta 22';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Beta 21';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '';

	/*
		Set activity stream values based on vB 4.2 values, if they exist.
	*/
	public function step_1()
	{
		$assertor = vB::getDbAssertor();

		/* These were saved in alpha 1 step */
		$as_expire = $assertor->getRow('adminutil', array('title' => 'as_expire'));
		$as_perpage = $assertor->getRow('adminutil', array('title' => 'as_perpage'));

		if ($as_expire AND $as_perpage)
		{
			/* vB5 time filtering is very limited
			So we translate the value as best we can
			1 - 4 days = today
			5 - 14 days = last week
			15 - 89 days = last month
			90+ days = all time */
			$filter = $as_expire['text'] < 5 ? 'time_today' : 'time_lastweek';
			$filter = $as_expire['text'] < 15 ? $filter : 'time_lastmonth';
			$filter = $as_expire['text'] < 90 ? $filter : 'time_all';
			
			/* Limit perpage between 10 and 60 */			
			$perpage = $as_perpage['text'] < 10 ? 10 : $as_perpage['text'];
			$perpage = $as_perpage['text'] > 60 ? 60 : $perpage;

			$widget = $assertor->getRow('widget', array('guid' => 'vbulletin-widget_4-4eb423cfd69899.61732480'));
			$widgetInstance = $assertor->getRow('widgetinstance', array('widgetid' => $widget['widgetid']));

			if ($widgetInstance)
			{
				$data = unserialize($widgetInstance['adminconfig']);
				$widgetInstanceid = $widgetInstance['widgetinstanceid'];

				$data['filtertime_activitystream'] = $filter;
				$data['resultsperpage_activitystream'] = $perpage;

				$savedata = serialize($data);
				
				$assertor->update('widgetinstance', 
					array('adminconfig' => $savedata),	
					array('widgetinstanceid' => $widgetInstanceid)
				);

				$assertor->delete('adminutil', array('title' => 'as_expire')); 
				$assertor->delete('adminutil', array('title' => 'as_perpage')); 

				$this->show_message($this->phrase['version']['500b22']['activity_update']);
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
	
	//Add thread_post table
	public function step_2()
	{
		if (!$this->tableExists('thread_post'))
		{
			$this->run_query(
					sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'thread_post'),
					"CREATE TABLE " . TABLE_PREFIX . "thread_post (
						nodeid INT UNSIGNED NOT NULL,
						threadid INT UNSIGNED NOT NULL,
						postid INT UNSIGNED NOT NULL,
						PRIMARY KEY (nodeid),
						UNIQUE KEY thread_post (threadid, postid),
						KEY threadid (threadid),
						KEY postid (postid)
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
	
	//Now we can import threads, which come to vB5 as starters
	function step_3($data = NULL)
	{
		if ($this->tableExists('post')) 
		{
			vB_Types::instance()->reloadTypes();
			$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'thread_post'));
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

			$maxvB5 = $this->db->query_first("SELECT MAX(threadid) AS maxid FROM " . TABLE_PREFIX . "thread_post");

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

			$query = "
				INSERT INTO " . TABLE_PREFIX . "thread_post(nodeid, threadid, postid)
				SELECT n.nodeid, th.threadid, th.firstpostid
				FROM " . TABLE_PREFIX . "thread AS th
				INNER JOIN " . TABLE_PREFIX . "node AS n ON n.oldid = th.threadid AND n.oldcontenttypeid = $threadTypeId
				WHERE th.threadid > $maxvB5 AND th.threadid < ($maxvB5 + $process) 
				ORDER BY th.threadid
			";

			$this->db->query_write($query);
			$this->show_message(sprintf($this->phrase['core']['processed_records_x_y'], $maxvB5 + 1, $maxvB5 + $process - 1));

			return array('startat' => ($maxvB5 + $process - 1), 'maxvB4' => $maxvB4);
		}
		else
		{
			$this->skip_message();
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
