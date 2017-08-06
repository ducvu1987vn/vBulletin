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

class vB_Upgrade_500a24 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a24';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 24';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 23';

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

	/** we want lastcontent and lastcontentid to always have a value except for channels. **/
	public function step_1($data)
	{
		$channelType = vB_Types::instance()->getContentTypeId('vBForum_Channel');
		if (empty($data) OR empty($data['startat']))
		{
			$startat = 0;
		}
		else
		{
			$startat = intval($data['startat']);
		}
		$batchsize = 20000;

		$maxvB5 = $this->db->query_first("SELECT max(nodeid) AS maxid FROM " . TABLE_PREFIX . "node");
		$maxvB5 = intval($maxvB5['maxid']);

		if ($startat > $maxvB5)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}

		$this->run_query(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'node'),
		'UPDATE ' . TABLE_PREFIX . "node SET lastcontentid = nodeid, lastcontent = publishdate
			WHERE lastcontentid = 0 AND contenttypeid <>$channelType AND nodeid > $startat AND nodeid <= ($startat + $batchsize);
		");
		return array('startat' => $startat + $batchsize);
	}

	/** adding ipv6 fields to strike table **/
	public function step_2()
	{
		$this->skip_message();
	}

	/** update new ip fields with IPv4 addresses **/
	public function step_3()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'strikes'));

		$strikeIPs = $this->db->query_read("SELECT DISTINCT strikeip FROM " . TABLE_PREFIX . "strikes WHERE ip_1 = 0 AND ip_2 = 0 AND ip_3 = 0 AND ip_4 = 0");
		while ($strike = $this->db->fetch_array($strikeIPs))
		{
			if (vB_Ip::isValidIPv4($strike['strikeip']))
			{
				$ipFields = vB_Ip::getIpFields($strike['strikeip']);
				vB::getDbAssertor()->update('vBForum:strikes',
						array(
							'ip_4' => vB_dB_Type_UInt::instance($ipFields['ip_4']),
							'ip_3' => vB_dB_Type_UInt::instance($ipFields['ip_3']),
							'ip_2' => vB_dB_Type_UInt::instance($ipFields['ip_2']),
							'ip_1' => vB_dB_Type_UInt::instance($ipFields['ip_1'])
						),
						array('strikeip' => $strike['strikeip'])
				);
			}
		}
	}

	/** renaming the filter_conversations widget item**/
	public function step_4()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'widgetdefinition'));

		$home_template = vB::getDbAssertor()->getRow('pagetemplate', array('guid' => 'vbulletin-4ecbdac9370e30.09770013'));
		$home_activity_widget = vB::getDbAssertor()->getRow('widget', array('guid' => 'vbulletin-widget_4-4eb423cfd69899.61732480'));
		$existing = vB::getDbAssertor()->getRows('widgetdefinition', array('name' => 'filter_conversations'), false, 'widgetid');

		vB::getDbAssertor()->update('widgetdefinition', array('name' => 'filter_new_topics', 'defaultvalue' => '0', 'label' => 'Show New Topics?'), array('name' => 'filter_conversations'));
		vB::getDbAssertor()->update('widgetdefinition', array('defaultvalue' => '1'), array('name' => 'filter_new_topics', 'widgetid' => $home_activity_widget['widgetid']));

		if (!empty($existing))
		{
			$instances = vB::getDbAssertor()->assertQuery('widgetinstance', array('widgetid' => array_keys($existing)));
			foreach ($instances as $instance)
			{
				if (isset($adminconfig['filter_conversations']))
				{
					unset($adminconfig['filter_conversations']);
					$adminconfig['filter_new_topics'] = $instance['pagetemplateid'] == $home_template['pagetemplateid'] ? 1 : 0;
					$instances = vB::getDbAssertor()->update('widgetinstance', array('adminconfig' => serialize($adminconfig)), array('widgetinstanceid' => $instance['widgetinstanceid']));
				}
			}
		}
	}

	// Add ispublic field
	public function step_5()
	{
		$this->skip_message();
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
