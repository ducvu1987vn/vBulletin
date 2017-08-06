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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
// if (!is_object($vbulletin->db))
// {
// 	exit;
// }

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################
$timenow = vB::getRequest()->getTimeNow();
vB::getDbAssertor()->delete('session',
		array(
				array('field'=>'lastactivity', 'value' => $timenow - vB::getDatastore()->getOption('cookietimeout'), vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_LT)
		)
);

vB::getDbAssertor()->delete('cpsession',
		array(
				array('field'=>'dateline', 'value' => vB::getDatastore()->getOption('timeoutcontrolpanel') ? ($timenow - vB::getDatastore()->getOption('cookietimeout')) : $timenow - 3600, vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_LT)
		)
);

vB_Api::instanceInternal('search')->clean();

// expired lost passwords and email confirmations after 4 days
vB::getDbAssertor()->assertQuery('cleanupUA',array('time' => $timenow - 345600));

vB::getDbAssertor()->delete('noderead',
		array(
				array('field'=>'readtime', 'value' => $timenow - (vB::getDatastore()->getOption('markinglimit') * 86400), vB_Db_Query::OPERATOR_KEY => vB_Db_Query::OPERATOR_LT)
		)
);

vB_Api_Wol::buildSpiderList();

// Remove expired cache items
vB_Cache::resetCache(true);

log_cron_action('', $nextitem, 1);

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 67762 $
|| ####################################################################
\*======================================================================*/
