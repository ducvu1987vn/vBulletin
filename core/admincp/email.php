<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright 2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 70726 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
global $phrasegroups, $specialtemplates, $vbphrase, $vbulletin;
$phrasegroups = array('user', 'cpuser', 'messaging', 'cprofilefield', 'profilefield');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/adminfunctions_profilefield.php');
require_once(DIR . '/includes/adminfunctions_user.php');
$assertor = vB::getDbAssertor();

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['email_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'start';
}

// *************************** Send a page of emails **********************
if ($_POST['do'] == 'dosendmail' OR $_POST['do'] == 'makelist')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'user'              => vB_Cleaner::TYPE_ARRAY,
		'profile'           => vB_Cleaner::TYPE_ARRAY,
		'serializeduser'    => vB_Cleaner::TYPE_STR,
		'serializedprofile' => vB_Cleaner::TYPE_STR,
		'septext'           => vB_Cleaner::TYPE_NOTRIM,
		'perpage'           => vB_Cleaner::TYPE_UINT,
		'startat'           => vB_Cleaner::TYPE_UINT,
		'test'              => vB_Cleaner::TYPE_BOOL,
		'from'              => vB_Cleaner::TYPE_STR,
		'subject'           => vB_Cleaner::TYPE_STR,
		'message'           => vB_Cleaner::TYPE_STR,
	));

	$vbulletin->GPC['septext'] = nl2br(htmlspecialchars_uni($vbulletin->GPC['septext']));

	// ensure that we don't send blank emails by mistake
	if ($_POST['do'] == 'dosendmail')
	{
		if ($vbulletin->GPC['subject'] == '' OR $vbulletin->GPC['message'] == '' OR !is_valid_email($vbulletin->GPC['from']))
		{
			print_stop_message2('please_complete_required_fields');
		}
	}

	if (!empty($vbulletin->GPC['serializeduser']))
	{
		$vbulletin->GPC['user'] = @unserialize(verify_client_string($vbulletin->GPC['serializeduser']));
		$vbulletin->GPC['profile'] = @unserialize(verify_client_string($vbulletin->GPC['serializedprofile']));
	}

	$users = vB_Api::instanceInternal('user')->generateMailingList($vbulletin->GPC['user'], $vbulletin->GPC['profile']);
	if ($_POST['do'] == 'makelist')
	{
		if ($users['totalcount'] > 0)
		{
			foreach ($users['list'] AS $user)
			{
				echo $user['email'] . $vbulletin->GPC['septext'];
				vbflush();
			}
		}
		else
		{
			print_stop_message2('no_users_matched_your_query');
		}
	}
	else
	{
		if (empty($vbulletin->GPC['perpage']))
		{
			$vbulletin->GPC['perpage'] = 500;
		}

		@set_time_limit(0);

		if ($users['totalcount'] == 0)
		{
			print_stop_message2('no_users_matched_your_query');
		}
		else
		{
			$users = vB_Api::instanceInternal('user')->generateMailingList($vbulletin->GPC['user'], $vbulletin->GPC['profile'],
				array('activation' => 1, vB_dB_Query::PARAM_LIMITPAGE => $vbulletin->GPC['startat'], vB_dB_Query::PARAM_LIMIT => $vbulletin->GPC['perpage'])
			);

			if ($users['totalcount'])
			{
				$page = $vbulletin->GPC['startat'] / $vbulletin->GPC['perpage'] + 1;
				$totalpages = ceil($counter['total'] / $vbulletin->GPC['perpage']);

				if (strpos($vbulletin->GPC['message'], '$activateid') !== false OR strpos($vbulletin->GPC['message'], '$activatelink') !== false)
				{
					$hasactivateid = 1;
				}
				else
				{
					$hasactivateid = 0;
				}

				echo '<p><b>' . $vbphrase['emailing'] . '<br />' . construct_phrase($vbphrase['showing_users_x_to_y_of_z'], vb_number_format($vbulletin->GPC['startat'] + 1), iif ($vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'] > $counter['total'], vb_number_format($counter['total']), vb_number_format($vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'])), vb_number_format($counter['total'])) . '</b></p>';

				foreach ($users['list'] AS $user)
				{
					echo "$user[userid] - $user[username] .... \n";
					vbflush();

					$userid = $user['userid'];
					$sendmessage = $vbulletin->GPC['message'];
					$sendmessage = str_replace(
						array('$email', '$username', '$userid'),
						array($user['email'], $user['username'], $user['userid']),
						$vbulletin->GPC['message']
					);
					if ($hasactivateid)
					{
						if ($user['usergroupid'] == 3)
						{
							// if in correct usergroup
							if (empty($user['activationid']))
							{
								//none exists so create one
								$activate['activationid'] = fetch_random_string(40);
								/*insert query*/
								$assertor->assertQuery('emailReplaceUserActivation', array(
									'userid' => $user['userid'],
									'dateline' => vB::getRequest()->getTimeNow(),
									'activateid' => $activate['activationid'],
									'type' => 0,
									'usergroupid' => 2
								));
							}
							else
							{
								$activate['activationid'] = fetch_random_string(40);
								$assertor->update('useractivation',
									array('dateline' => vB::getRequest()->getTimeNow(), 'activationid' => $activate['activationid']),
									array('userid' => $user['userid'], 'type' => 0)
								);
							}
							$activate['link'] = $vbulletin->options['bburl'] . "/register.php?a=act&u=$userid&i=$activate[activationid]";
						}
						else
						{
							$activate = array();
						}

						$sendmessage = str_replace(
							array('$activateid', '$activatelink'),
							array($activate['activationid'], $activate['link']),
							$sendmessage
						);

					}
					$sendmessage = str_replace(
						array('$bburl', '$bbtitle'),
						array($vbulletin->options['bburl'], $vbulletin->options['bbtitle']),
						$sendmessage
					);

					if (!$vbulletin->GPC['test'])
					{
						echo $vbphrase['emailing'] . " \n";
						vbmail($user['email'], $vbulletin->GPC['subject'], $sendmessage, true, $vbulletin->GPC['from']);
					}
					else
					{
						echo $vbphrase['test'] . " ... \n";
					}

					echo $vbphrase['okay'] . "<br />\n";
					vbflush();

				}
				$_REQUEST['do'] = 'donext';
			}
			else
			{
				parse_str(vB::getCurrentSession()->get('sessionurl'),$extra);
				print_stop_message2('emails_sent_successfully', 'email',$extra);
			}
		}
	}
}

// *************************** Link to next page of emails to send **********************
if ($_REQUEST['do'] == 'donext')
{

	$vbulletin->GPC['startat'] += $vbulletin->GPC['perpage'];

	print_form_header('email', 'dosendmail', false, true, 'cpform_dosendmail');
	construct_hidden_code('test', $vbulletin->GPC['test']);
	construct_hidden_code('serializeduser', sign_client_string(serialize($vbulletin->GPC['user'])));
	construct_hidden_code('serializedprofile', sign_client_string(serialize($vbulletin->GPC['profile'])));
	construct_hidden_code('from', $vbulletin->GPC['from']);
	construct_hidden_code('subject', $vbulletin->GPC['subject']);
	construct_hidden_code('message', $vbulletin->GPC['message']);
	construct_hidden_code('startat', $vbulletin->GPC['startat']);
	construct_hidden_code('perpage', $vbulletin->GPC['perpage']);

	print_submit_row($vbphrase['next_page'], 0);

	?>
	<script type="text/javascript">
	<!--
	if (document.cpform_dosendmail)
	{
		function send_submit()
		{
			var submits = YAHOO.util.Dom.getElementsBy(
				function(element) { return (element.type == "submit") },
				"input", this
			);
			var submit_button;

			for (var i = 0; i < submits.length; i++)
			{
				submit_button = submits[i];
				submit_button.disabled = true;
				setTimeout(function() { submit_button.disabled = false; }, 10000);
			}

			return false;
		}

		YAHOO.util.Event.on(document.cpform_dosendmail, 'submit', send_submit);
		send_submit.call(document.cpform_dosendmail);
		document.cpform_dosendmail.submit();
	}
	// -->
	</script>
	<?php
	vbflush();
}

// *************************** Main email form **********************
if ($_REQUEST['do'] == 'start' OR $_REQUEST['do'] == 'genlist')
{
?>
<script type="text/javascript">
function check_all_usergroups(formobj, toggle_status)
{
	for (var i = 0; i < formobj.elements.length; i++)
	{
		var elm = formobj.elements[i];
		if (elm.type == "checkbox" && elm.name == 'user[usergroupid][]')
		{
			elm.checked = toggle_status;
		}
	}
}
</script>
<?php
	if ($_REQUEST['do'] == 'start')
	{
		print_form_header('email', 'dosendmail');
		print_table_header($vbphrase['email_manager']);
		print_yes_no_row($vbphrase['test_email_only'], 'test', 0);
		print_input_row($vbphrase['email_to_send_at_once_gcpuser'], 'perpage', 500);
		print_input_row($vbphrase['from_gmessaging'], 'from', $vbulletin->options['webmasteremail']);
		print_input_row($vbphrase['subject'], 'subject');
		print_textarea_row($vbphrase['message_email'], 'message', '', 10, 50);
		$text = $vbphrase['send'];

	}
	else
	{
		print_form_header('email', 'makelist');
		print_table_header($vbphrase['generate_mailing_list']);
		print_textarea_row($vbphrase['text_to_separate_addresses_by'], 'septext', ' ');
		$text = $vbphrase['go'];
	}

	print_table_break();
	print_table_header($vbphrase['search_criteria']);
	print_user_search_rows(true);

	print_table_break();
	print_submit_row($text);
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 70726 $
|| ####################################################################
\*======================================================================*/
?>