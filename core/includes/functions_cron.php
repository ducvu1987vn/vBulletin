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

// ###################### Start fetch_cron_next_run #######################
// gets next run time today after $hour, $minute
// returns -1,-1 if not again today
function fetch_cron_next_run($crondata, $hour = -2, $minute = -2)
{
	if ($hour == -2)
	{
		$hour = intval(date('H', vB::getRequest()->getTimeNow()));
	}
	if ($minute == -2)
	{
		$minute = intval(date('i', vB::getRequest()->getTimeNow()));
	}

	### REMOVE BEFORE 3.1.0
	### CODE IS HERE FOR THOSE WHO DONT RUN UPGRADE SCRIPTS
	if (is_numeric($crondata['minute']))
	{
		$crondata['minute'] = array(0 => $crondata['minute']);
	}
	else
	{
		$crondata['minute'] = unserialize($crondata['minute']);
	}
	### END REMOVE
	if ($crondata['hour'] == -1 AND $crondata['minute'][0] == -1)
	{
		$newdata['hour'] = $hour;
		$newdata['minute'] = $minute + 1;
	}
	else if ($crondata['hour'] == -1 AND $crondata['minute'][0] != -1)
	{
		$newdata['hour'] = $hour;
		$nextminute = fetch_next_minute($crondata['minute'], $minute);
		if ($nextminute === false)
		{
			++$newdata['hour'];
			$nextminute = $crondata['minute'][0];
		}
		$newdata['minute'] = $nextminute;
	}
	else if ($crondata['hour'] != -1 AND $crondata['minute'][0] == -1)
	{
		if ($crondata['hour'] < $hour)
		{ // too late for today!
			$newdata['hour'] = -1;
			$newdata['minute'] = -1;
		}
		else if ($crondata['hour'] == $hour)
		{ // this hour
			$newdata['hour'] = $crondata['hour'];
			$newdata['minute'] = $minute + 1;
		}
		else
		{ // some time in future, so launch at 0th minute
			$newdata['hour'] = $crondata['hour'];
			$newdata['minute'] = 0;
		}
	}
	else if ($crondata['hour'] != -1 AND $crondata['minute'][0] != -1)
	{
		$nextminute = fetch_next_minute($crondata['minute'], $minute);
		if ($crondata['hour'] < $hour OR ($crondata['hour'] == $hour AND $nextminute === false))
		{ // it's not going to run today so return -1,-1
			$newdata['hour'] = -1;
			$newdata['minute'] = -1;
		}
		else
		{
			// all good!
			$newdata['hour'] = $crondata['hour'];
			$newdata['minute'] = $nextminute;
		}
	}

	return $newdata;
}

// ###################### Start fetch_next_minute #######################
// takes an array of numbers and a number and returns the next highest value in the array
function fetch_next_minute($minutedata, $minute)
{
	foreach ($minutedata AS $nextminute)
	{
		if ($nextminute > $minute)
		{
			return $nextminute;
		}
	}
	return false;
}

// ###################### Start build_cron_item #######################
// updates an entry in the cron table to determine the next run time
function build_cron_item($cronid, $crondata = '')
{
	if (!is_array($crondata))
	{
		$crondata = vB::getDbAssertor()->getRow('cron', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'cronid' => intval($cronid),
		));
	}
	$timenow = vB::getRequest()->getTimeNow();
	$minutenow = intval(date('i', $timenow));
	$hournow = intval(date('H', $timenow));
	$daynow = intval(date('d', $timenow));
	$monthnow = intval(date('m', $timenow));
	$yearnow = intval(date('Y', $timenow));
	$weekdaynow = intval(date('w', $timenow));

	// ok need to work out, date and time of 1st and 2nd next opportunities to run
	if ($crondata['weekday'] == -1)
	{ // any day of week:
		if ($crondata['day'] == -1)
		{ // any day of month:
			$firstday = $daynow;
			$secondday = $daynow + 1;
		}
		else
		{	// specific day of month:
			$firstday = $crondata['day'];
			$secondday = $crondata['day'] + date('t', $timenow); // number of days this month
		}
	}
	else
	{ // specific day of week:
		$firstday = $daynow + ($crondata['weekday'] - $weekdaynow);
		$secondday = $firstday + 7;
	}

	if ($firstday < $daynow)
	{
		$firstday = $secondday;
	}

	if ($firstday == $daynow)
	{ // next run is due today?
		$todaytime = fetch_cron_next_run($crondata); // see if possible to run again today
		if ($todaytime['hour'] == -1 AND $todaytime['minute'] == -1)
		{
			// can't run today
			$crondata['day'] = $secondday;

			$newtime = fetch_cron_next_run($crondata, 0, -1);
			$crondata['hour'] = $newtime['hour'];
			$crondata['minute'] = $newtime['minute'];
		}
		else
		{
			$crondata['day'] = $firstday;
			$crondata['hour'] = $todaytime['hour'];
			$crondata['minute'] = $todaytime['minute'];
		}
	}
	else
	{
		$crondata['day'] = $firstday;

		$newtime = fetch_cron_next_run($crondata, 0, -1); // work out first run time that day
		$crondata['hour'] = $newtime['hour'];
		$crondata['minute'] = $newtime['minute'];
	}

	$nextrun = mktime($crondata['hour'], $crondata['minute'], 0, $monthnow, $crondata['day'], $yearnow);

	// save it
	$affectedrows = vB::getDbAssertor()->assertQuery('cron', array(
		vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
		'nextrun' => $nextrun,
		vB_dB_Query::CONDITIONS_KEY => array(
			'cronid' => intval($cronid),
			'nextrun' => $crondata['nextrun'],
		)
	));
	$not_run = ($affectedrows > 0);

	build_cron_next_run($nextrun);
	return iif($not_run, $nextrun, 0);
}

// ###################### Start build_cron_next_run #######################
function build_cron_next_run($nextrun = '')
{
	// get next one to run
	if (!$nextcron = vB::getDbAssertor()->getRow('cron_fetchnext', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED)))
	{
		$nextcron['nextrun'] = vB::getRequest()->getTimeNow() + 60 * 60;
	}

	// update DB details
	build_datastore('cron', $nextcron['nextrun']);

	return $nextrun;
}

// ###################### Start log_cron_action #######################
// description = action that was performed
// $nextitem is an array containing the information for this cronjob
// $phrased is set to true if this action is phrased
function log_cron_action($description, $nextitem, $phrased = 0)
{
	if (defined('ECHO_CRON_LOG'))
	{
		echo "<p>$description</p>";
	}

	if ($nextitem['loglevel'])
	{
		/*insert query*/
		$insertCronLog = vB::getDbAssertor()->assertQuery('vBForum:cronlog',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'varname' => $nextitem['varname'],
				'dateline' => vB::getRequest()->getTimeNow(),
				'description' => $description,
				'type' => $phrased
			)
		);
	}
}

// ###################### Start exec_cron #######################
function exec_cron($cronid = NULL)
{
	if ($cronid = intval($cronid))
	{
		$nextitem = vB::getDbAssertor()->getRow('cron', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'cronid' => $cronid));
	}
	else
	{
		$nextitem = vB::getDbAssertor()->getRow('vBForum:fetchCronByDate', array('date' => vB::getRequest()->getTimeNow()));
	}

	if ($nextitem)
	{
		if ($nextrun = build_cron_item($nextitem['cronid'], $nextitem))
		{
			include_once(DIR . '/' . $nextitem['filename']);
		}
	}
	else
	{
		build_cron_next_run();
	}

	//make sure that shutdown functions are called on script exit.
	$GLOBALS['vbulletin']->shutdown->shutdown();
	// Legacy Hook 'cron_complete' Removed //
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>
