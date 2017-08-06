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
 * vB_Library_Reputation
 *
 * @package vBApi
 * @access public
 */
class vB_Library_Reputation extends vB_Library
{
	/**
	 * Fetch reputation image info for displaying it in a node
	 * Ported from vB4's fetch_reputation_image() function
	 *
	 * @param @param mixed		userinfo record from user api fetchuserinfo()
	 * @return array Contains 3 items:
	 *               1) type  - image type. Possible values: balance, neg, highneg, pos, highpos, off
	 *               2) level - Reputation level's phrase name
	 *               3) bars  - Number of image bars to be displayed. Maximum 10.
	 */
	public function fetchReputationImageInfo($userinfo)
	{
		$vboptions = vB::getDatastore()->getValue('options');

		if (!$userinfo['userid'])
		{
			throw new vB_Exception_Api('invalidid', 'User');
		}
		$usercontext = &vB::getUserContext($userinfo['userid']);
		$reputation_value = $userinfo['reputation'];
		if ($userinfo['reputation'] == 0)
		{
			$reputationgif = 'balance';
			$reputation_value = 0;
		}
		else if ($userinfo['reputation'] < 0)
		{
			$reputationgif = 'neg';
			$reputationhighgif = 'highneg';
			$reputation_value = $userinfo['reputation'] * -1;
		}
		else
		{
			$reputationgif = 'pos';
			$reputationhighgif = 'highpos';
		}

		if ($reputation_value > 500)
		{  // bright green bars take 200 pts not the normal 100
			$reputation_value = ($reputation_value / 2) + 250;
		}

		$reputationbars = intval($reputation_value / 100); // award 1 reputation bar for every 100 points
		if ($reputationbars > 10)
		{
			$reputationbars = 10;
		}

		if (!isset($userinfo['showreputation']))
		{
			$bf_misc_useroptions = vB::getDatastore()->getValue('bf_misc_useroptions');
			$userinfo['showreputation'] = $userinfo['options'] & $bf_misc_useroptions['showreputation'];
		}

		if (!$userinfo['showreputation'] AND $usercontext->hasPermission('genericpermissions', 'canhiderep'))
		{
			$posneg = 'off';
			$level = 'reputation_disabled';
		}
		else
		{
			if (!$userinfo['reputationlevelid'])
			{
				$level = $vboptions['reputationundefined'];
			}
			for ($i = 0; $i <= $reputationbars; $i++)
			{
				if ($i >= 5)
				{
					$posneg = $reputationhighgif;
				}
				else
				{
					$posneg = $reputationgif;
				}

				$level = 'reputation' . $userinfo['reputationlevelid'];
			}
		}

		return array(
			'type'  => $posneg,
			'level' => $level,
			'bars'  => $posneg == 'off' ? 0 : $reputationbars,
		);
	}


	/**
	 * Fetch Reputation Power of an user
	 *
	 * @param mixed		userinfo record from user api fetchuserinfo()
	 * @return int|mixed|string Reputation Power
	 */
	public function fetchReppower($userinfo)
	{
		$vboptions = vB::getDatastore()->getValue('options');

		$usercontext = &vB::getUserContext($userinfo['userid']);

		if (!$usercontext->hasPermission('genericpermissions', 'canuserep'))
		{
			$reppower = 0;
		}
		else if ($usercontext->hasAdminPermission('cancontrolpanel') AND $vboptions['adminpower'])
		{
			$reppower = $vboptions['adminpower'];
		}
		else if (($userinfo['posts'] < $vboptions['minreputationpost']) OR ($userinfo['reputation'] < $vboptions['minreputationcount']))
		{
			$reppower = 0;
		}
		else
		{
			$reppower = 1;

			if ($vboptions['pcpower'])
			{
				$reppower += intval($userinfo['posts'] / $vboptions['pcpower']);
			}
			if ($vboptions['kppower'])
			{
				$reppower += intval($userinfo['reputation'] / $vboptions['kppower']);
			}
			if ($vboptions['rdpower'])
			{
				$reppower += intval(intval((vB::getRequest()->getTimeNow() - $userinfo['joindate']) / 86400) / $vboptions['rdpower']);
			}
		}

		return $reppower;
	}

}
