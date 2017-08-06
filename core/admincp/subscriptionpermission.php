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

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 68365 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbphrase, $vbulletin;
$phrasegroups = array('subscription');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/class_paid_subscription.php');
$assertor = vB::getDbAssertor();

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'subscriptionpermissionid' => vB_Cleaner::TYPE_INT,
	'subscriptionid'           => vB_Cleaner::TYPE_INT,
	'usergroupid'              => vB_Cleaner::TYPE_INT
));
log_admin_action(iif($vbulletin->GPC['subscriptionpermissionid'] != 0, "subscriptionpermission id = " . $vbulletin->GPC['subscriptionpermissionid'],
					iif($vbulletin->GPC['subscriptionid'] != 0, "subscription id = ". $vbulletin->GPC['subscriptionid'] .
						iif($vbulletin->GPC['usergroupid'] != 0, " / usergroup id = " . $vbulletin->GPC['usergroupid']))));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['subscription_permissions_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

$subobj = new vB_PaidSubscription($vbulletin);
$subobj->cache_user_subscriptions();

// ###################### Start edit #######################
if ($_REQUEST['do'] == 'edit')
{
	print_form_header('subscriptionpermission', 'doupdate');

	if (empty($subobj->subscriptioncache[$vbulletin->GPC['subscriptionid']]))
	{
		print_stop_message2(array('invalid_x_specified',  $vbphrase['subscription']));
	}

	if (empty($vbulletin->usergroupcache[$vbulletin->GPC['usergroupid']]))
	{
		print_stop_message2(array('invalid_x_specified',  $vbphrase['usergroup']));
	}

	$getperms = $assertor->getRow('vBForum:getSubscriptionPermissionInfo', array(
		'subscriptionid' => $vbulletin->GPC['subscriptionid'], 'usergroupid' => $vbulletin->GPC['usergroupid']
	));
	$usergroup = $vbulletin->usergroupcache[$vbulletin->GPC['usergroupid']];

	$subtitle = $vbphrase['sub' . $vbulletin->GPC['subscriptionid'] . '_title'];
	construct_hidden_code('subscriptionid', $vbulletin->GPC['subscriptionid']);
	construct_hidden_code('usergroupid', $vbulletin->GPC['usergroupid']);
	print_table_header(construct_phrase($vbphrase['edit_usergroup_permissions_for_usergroup_x_in_subscription_y'], $usergroup['title'], $subtitle));
	print_yes_no_row($vbphrase['can_use_subscription'], 'usesub', !$getperms);

	print_submit_row($vbphrase['save']);
}

// ###################### Start do update #######################
if ($_POST['do'] == 'doupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'usesub'                   => vB_Cleaner::TYPE_BOOL,
		'subscriptionpermissionid' => vB_Cleaner::TYPE_INT,
		'subscriptionid'           => vB_Cleaner::TYPE_INT,
		'usergroupid'              => vB_Cleaner::TYPE_INT

	));

	if (empty($subobj->subscriptioncache[$vbulletin->GPC['subscriptionid']]))
	{
		print_stop_message2(array('invalid_x_specified',  $vbphrase['subscription']));
	}

	if (empty($vbulletin->usergroupcache[$vbulletin->GPC['usergroupid']]))
	{
		print_stop_message2(array('invalid_x_specified',  $vbphrase['usergroup']));
	}

	if ($vbulletin->GPC['usesub'])
	{
		$affected = $assertor->delete('vBForum:subscriptionpermission', array(
			'subscriptionid' => $vbulletin->GPC['subscriptionid'], 'usergroupid' => $vbulletin->GPC['usergroupid']
		));
		if ($affected)
		{
			print_stop_message2('deleted_subscription_permissions_successfully','subscriptionpermission', array('do' => 'modify', '#' => "subscription" . $vbulletin->GPC['subscriptionid']));
		}
		else
		{
			print_stop_message2('updated_subscription_permissions_successfully','subscriptionpermission', array('do' => 'modify', '#' => "subscription" . $vbulletin->GPC['subscriptionid']));
		}
	}
	else
	{
		$subPerms[] = array(
			'usergroupid' => $vbulletin->GPC['usergroupid'],
			'subscriptionid' => $vbulletin->GPC['subscriptionid']
		);
		$assertor->assertQuery('replaceValues', array('values' => $subPerms, 'table' => 'subscriptionpermission'));

		print_stop_message2('saved_usergroup_permissions_successfully','subscriptionpermission', array('do' => 'modify', '#' => "subscription" . $vbulletin->GPC['subscriptionid']));
	}
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{

	print_form_header('', '');
	print_table_header($vbphrase['subscription_permissions_gsubscription']);
	print_description_row('
		<div class="darkbg" style="border: 2px inset">	<ul class="darkbg">
		<li><b>' . $vbphrase['color_key'] . '</b></li>
		<li class="col-g">' . $vbphrase['allowed_can_access_subscription'] . '</li>
		<li class="col-c">' . $vbphrase['denied_can_not_access_subscription'] . '</li>
		</ul></div>
	');

	print_table_footer();

	if (empty($subobj->subscriptioncache))
	{
		print_stop_message2(array('nosubscriptions',  $vbulletin->options['bbtitle']));
	}

	// query subscription permissions
	$subscriptionpermissions = $assertor->getRows('vBForum:subscriptionpermission');

	$permscache = array();
	foreach ($subscriptionpermissions AS $sperm)
	{
		$permscache["{$sperm['subscriptionid']}"]["{$sperm['usergroupid']}"] = true;
	}

	echo '<center><div class="tborder" style="width: 100%">';
	echo '<div class="alt1" style="padding: 8px">';
	echo '<div class="darkbg" style="padding: 4px; border: 2px inset; text-align: ' . vB_Template_Runtime::fetchStyleVar('left') . '">';

	$indent = '   ';
	echo "$indent<ul class=\"lsq\">\n";
	foreach ($subobj->subscriptioncache AS $subscriptionid => $subscription)
	{
		$title = $vbphrase['sub' . $subscriptionid . '_title'];
		// forum title and links
		echo "$indent<li><b><a name=\"subscription$subscriptionid\" href=\"subscriptions.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;subscriptionid=$subscriptionid\">$title</a></b>";
		echo "$indent\t<ul class=\"usergroups\">\n";
		foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
		{
			if (!empty($permscache["$subscriptionid"]["$usergroupid"]))
			{
				$class = ' class="col-c"';
			}
			else
			{
				$class = '';
			}
			$link = "subscriptionid=$subscriptionid&amp;usergroupid=$usergroupid";

			echo "$indent\t<li$class>" . construct_link_code($vbphrase['edit'], "subscriptionpermission.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&amp;$link") . $usergroup['title'] . "</li>\n";

			unset($permscache["$subscriptionid"]["$usergroupid"]);
		}
		echo "$indent\t</ul><br />\n";
		echo "$indent</li>\n";
	}
	echo "$indent</ul>\n";

	echo "</div></div></div></center>";
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>
