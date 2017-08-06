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
global $phrasegroups, $specialtemplates;
$phrasegroups = array('cpuser', 'cpoption');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once(dirname(__FILE__) . '/global.php');
require_once(DIR . '/includes/adminfunctions_options.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminsettings'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'questionid' => vB_Cleaner::TYPE_UINT,
	'answerid'   => vB_Cleaner::TYPE_UINT,
));
log_admin_action(!empty($vbulletin->GPC['questionid']) ? 'question id = ' . $vbulletin->GPC['questionid'] : '');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['human_verification_manager_gcpuser']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'intro';
}

// ###################### Intro Screen #######################
if ($_REQUEST['do'] == 'intro')
{
		$getsettings = array(
			'hv_type',
			'regimagetype',
			'regimageoption',
			'hv_recaptcha_publickey',
			'hv_recaptcha_privatekey',
			'hv_recaptcha_theme',
		);
		$varnames = array();
		foreach ($getsettings AS $setting)
		{
			$varnames[] = 'setting_' . $setting . '_title';
			$varnames[] = 'setting_' . $setting . '_desc';
		}

		// Legacy Hook 'admin_humanverify_intro_start' Removed //

		global $settingphrase;
		$settingphrase = array();
		$phrases = vB::getDbAssertor()->assertQuery('vBForum:phrase',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'fieldname' => 'vbsettings',
				'languageid' => array(-1, 0, LANGUAGEID),
				'varname' => $varnames
			),
			array('field' => 'languageid', 'direction' => vB_dB_Query::SORT_ASC)
		);
		if ($phrases AND $phrases->valid())
		{
			foreach ($phrases AS $phrase)
			{
				$settingphrase["$phrase[varname]"] = $phrase['text'];
			}
		}

		$cache = array();
		$settings = vB::getDbAssertor()->assertQuery('setting',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'varname' => $getsettings
			),
			array('field' => 'displayorder', 'direction' => vB_dB_Query::SORT_ASC)
		);
		if ($settings AND $settings->valid())
		{
			foreach ($settings AS $setting)
			{
				if ($setting['varname'] == 'hv_type')
				{
					$thesetting = $setting;
				}
				else
				{
					$cache[] = $setting;
				}
			}
		}

		// Legacy Hook 'admin_humanverify_intro_setting' Removed //

		print_form_header('verify', 'updateoptions');
		print_column_style_code(array('width:60%', 'width:40%; white-space:nowrap'));
		print_table_header($vbphrase['human_verification_options']);

		print_setting_row($thesetting, $settingphrase);
		print_submit_row($vbphrase['save']);


		switch($vbulletin->options['hv_type'])
		{
			case 'Image':

				print_form_header('verify', 'updateoptions');
				print_column_style_code(array('width:60%', 'width:40%; white-space:nowrap'));
				print_table_header($vbphrase['image_verification_options']);

				foreach($cache AS $setting)
				{
					if ($setting['varname'] == 'regimagetype' OR $setting['varname'] == 'regimageoption')
					{
						print_setting_row($setting, $settingphrase);
					}
				}
				print_submit_row($vbphrase['save']);
				break;

			case 'Question':

				?>
				<script type="text/javascript">
				function js_jump(id, obj)
				{
					task = obj.options[obj.selectedIndex].value;
					switch (task)
					{
						case 'modifyquestion': window.location = "verify.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=modifyquestion&questionid=" + id; break;
						case 'killquestion': window.location = "verify.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=removequestion&questionid=" + id; break;
						default: return false; break;
					}
				}
				</script>
				<?php

				$options = array(
					'modifyquestion' => $vbphrase['edit'],
					'killquestion'   => $vbphrase['delete'],
				);

				$questions = vB::getDbAssertor()->assertQuery('vBForum:fetchQuestions', array());

				print_form_header('verify', 'modifyquestion');
				print_table_header($vbphrase['question_verification_options'], 5);

				if ($db->num_rows($questions))
				{
					print_cells_row(array($vbphrase['question'], $vbphrase['answers'], $vbphrase['regex'], $vbphrase['date'], $vbphrase['controls']), 1);
				}
				else
				{
					print_description_row($vbphrase['not_specified_questions_no_validation'], false, 5);
				}

				if ($questions AND $questions->valid())
				{
					foreach ($questions AS $question)
					{
						print_cells_row(array(
							$question['text'],
							$question['answerid'] ? $question['answers'] : 0,
							$question['regex'] ? $vbphrase['yes'] : $vbphrase['no'],
							vbdate($vbulletin->options['logdateformat'], $question['dateline']),
							"<span style=\"white-space:nowrap\"><select name=\"q$question[questionid]\" onchange=\"js_jump($question[questionid], this);\" class=\"bginput\">" . construct_select_options($options) . "</select><input type=\"button\" value=\"" . $vbphrase['go'] . "\" onclick=\"js_jump($question[questionid], this.form.q$question[questionid]);\" class=\"button\" /></span>"
						));
					}
				}
				print_submit_row($vbphrase['add_new_question'], 0, 5);

				break;

			case 'Recaptcha':

				print_form_header('verify', 'updateoptions');
				print_table_header($vbphrase['recaptcha_verification_options']);

				foreach($cache AS $setting)
				{
					if (preg_match('#^hv_recaptcha_#si', $setting['varname']))
					{
						print_setting_row($setting, $settingphrase);
					}
				}
				print_submit_row($vbphrase['save']);
				break;

			default:

				// Legacy Hook 'admin_humanverify_intro_output' Removed //
		}

}

// ###################### Edit/Add Question #######################
if ($_REQUEST['do'] == 'modifyquestion')
{
	print_form_header('verify', 'updatequestion');
	if (empty($vbulletin->GPC['questionid']))
	{
		print_table_header($vbphrase['add_new_question']);
	}
	else
	{
		$question = vB::getDbAssertor()->getRow('vBForum:fetchQuestionById', array('questionid' => $vbulletin->GPC['questionid']));

		if (!$question)
		{
			print_stop_message2(array('invalid_x_specified', $vbphrase['question']));
		}

		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['question'], htmlspecialchars_uni($question['text']), $vbulletin->GPC['questionid']), 2, 0);
		construct_hidden_code('questionid', $vbulletin->GPC['questionid']);
	}

	if ($question['text'])
	{
		print_input_row($vbphrase['question'] . '<dfn>' . construct_link_code($vbphrase['translations'], "phrase.php?" . vB::getCurrentSession()->get('sessionurl') . "do=edit&fieldname=hvquestion&varname=question{$vbulletin->GPC['questionid']}&t=1", 1)  . '</dfn>', 'question', $question['text']);
	}
	else
	{
		print_input_row($vbphrase['question_dfn'], 'question');
	}
	print_input_row($vbphrase['regular_expression_require_match_gcpuser'], 'regex', $question['regex']);
	print_submit_row($vbphrase['save']);

	if (!empty($vbulletin->GPC['questionid']))
	{
		?>
		<script type="text/javascript">
		function js_jump(aid, qid, obj)
		{
			task = obj.options[obj.selectedIndex].value;
			switch (task)
			{
				case 'modifyanswer': window.location = "verify.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=modifyanswer&answerid=" + aid + "&questionid=" + qid; break;
				case 'killanswer': window.location = "verify.php?<?php echo vB::getCurrentSession()->get('sessionurl_js'); ?>do=removeanswer&answerid=" + aid + "&questionid=" + qid; break;
				default: return false; break;
			}
		}
		</script>
		<?php

		$answers = vB::getDbAssertor()->assertQuery('vBForum:hvanswer',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'questionid' => $vbulletin->GPC['questionid']
			),
			array('field' => 'dateline', 'direction' => vB_dB_Query::SORT_ASC)
		);
		print_form_header('verify', 'modifyanswer');
		print_table_header($vbphrase['answers'], 2);
		construct_hidden_code('questionid', $vbulletin->GPC['questionid']);

		if($answers AND $answers->valid())
		{
			print_cells_row(array($vbphrase['answer'], $vbphrase['controls']), 1);
		}

		$options = array(
			'modifyanswer' => $vbphrase['edit'],
			'killanswer'   => $vbphrase['delete'],
		);

		//while ($answer = $db->fetch_array($answers))
		foreach ($answers AS $answer)
		{
			print_cells_row(array(
				$answer['answer'],
				"\n\t<select name=\"a$answer[answerid]\" onchange=\"js_jump($answer[answerid], $answer[questionid], this);\" class=\"bginput\">\n" . construct_select_options($options) . "\t</select>\n\t<input type=\"button\" value=\"" . $vbphrase['go'] . "\" onclick=\"js_jump($answer[answerid], $answer[questionid], this.form.a$answer[answerid]);\" />\n\t"
			));
		}
		print_submit_row($vbphrase['add_new_answer'], 0, 2);
	}
}

// ###################### Save Question #######################
if ($_POST['do'] == 'updatequestion')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'question' => vB_Cleaner::TYPE_STR,
		'regex'    => vB_Cleaner::TYPE_STR,
	));
	if (empty($vbulletin->GPC['question']))
	{
		print_stop_message2('please_complete_required_fields');
	}

	if (empty($vbulletin->GPC['questionid']))
	{
		$questionid = vB::getDbAssertor()->assertQuery('vBForum:hvquestion',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'regex' => $vbulletin->GPC['regex'],
				'dateline' => TIMENOW
			)
		);
	}
	else
	{
		$questionid = $vbulletin->GPC['questionid'];
		$updateQuestion = vB::getDbAssertor()->assertQuery('vBForum:hvquestion',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'regex' => $vbulletin->GPC['regex'],
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'questionid', 'value' => $vbulletin->GPC['questionid'], 'operator' => vB_dB_Query::OPERATOR_EQ)
				)
			)
		);
	}

	/*insert_query*/
	$updatePhrase = vB::getDbAssertor()->assertQuery('replaceIntoPhrases',
		array(
			'text' => $vbulletin->GPC['question'],
			'fieldname' => 'hvquestion',
			'languageid' => 0,
			'varname' => "question" . $questionid,
			'product' => 'vbulletin',
			'enteredBy' => $vbulletin->userinfo['username'],
			'dateline' => TIMENOW,
			'version' => $vbulletin->options['templateversion'],
		)
	);

	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language();

	print_stop_message2('updated_question_successfully', 'verify',
		array('do' => 'modifyquestion', 'questionid' =>  $questionid));
}

// ###################### Edit/Add Answer #######################
if ($_REQUEST['do'] == 'modifyanswer')
{
	print_form_header('verify', 'updateanswer');
	$question = vB::getDbAssertor()->getRow('vBForum:fetchQuestionByAnswer', array('questionid' => $vbulletin->GPC['questionid']));

	if (!$question)
	{
		print_stop_message2(array('invalid_x_specified', $vbphrase['question']));
	}

	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['question'], htmlspecialchars_uni($question['text']), $question['questionid']), 2, 0);

	if (empty($vbulletin->GPC['answerid']))
	{
		print_table_header($vbphrase['add_new_answer']);
		construct_hidden_code('questionid', $vbulletin->GPC['questionid']);
	}
	else
	{
		$answer = vB::getDbAssertor()->getRow('vBForum:hvanswer',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'answerid' => $vbulletin->GPC['answerid'])
		);
		construct_hidden_code('answerid', $answer['answerid']);
		construct_hidden_code('questionid', $answer['questionid']);
	}

	print_input_row($vbphrase['answer'], 'answer', $answer['answer']);
	print_submit_row($vbphrase['save']);
}

// ###################### Save Question #######################
if ($_POST['do'] == 'updateanswer')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'answer' => vB_Cleaner::TYPE_STR,
	));
	if ($vbulletin->GPC['answer'] === '')
	{
		print_stop_message2('please_complete_required_fields');
	}

	$question = vB::getDbAssertor()->getRow('vBForum:hvquestion',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'questionid' => $vbulletin->GPC['questionid'])
	);
	if (!$question)
	{
		print_stop_message2(array('invalid_x_specified', $vbphrase['question']));
	}

	if (empty($vbulletin->GPC['answerid']))
	{
		$insertAnswer = vB::getDbAssertor()->assertQuery('vBForum:hvanswer',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_INSERT,
				'questionid' => $vbulletin->GPC['questionid'],
				'answer' => $vbulletin->GPC['answer'],
				'dateline' => TIMENOW
			)
		);
		$vbulletin->GPC['answerid'] = $db->insert_id();
	}
	else
	{
		$updateAnswer = vB::getDbAssertor()->assertQuery('vBForum:hvanswer',
			array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'answer' => $vbulletin->GPC['answer'],
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'answerid', 'value' => $vbulletin->GPC['answerid'], 'operator' => vB_dB_Query::OPERATOR_EQ)
				)
			)
		);
	}

	print_stop_message2('updated_answer_successfully', 'verify',
		array('do' => 'modifyquestion', 'questionid' => $vbulletin->GPC['questionid']));
}

// ###################### Remove Answer #######################
if ($_REQUEST['do'] == 'removeanswer')
{
	$answer = vB::getDbAssertor()->getRow('vBForum:hvanswer',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'answerid' => $vbulletin->GPC['answerid'])
	);

	if (!$answer)
	{
		print_stop_message2(array('invalid_x_specified', $vbphrase['answer']));
	}

	print_form_header('verify', 'killanswer');
	construct_hidden_code('answerid', $answer['answerid']);
	print_table_header(construct_phrase($vbphrase['confirm_deletion_x'], htmlspecialchars_uni($answer['answer'])));
	print_description_row($vbphrase['are_you_sure_you_want_to_delete_this_answer']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
}

// ###################### Kill Answer #######################
if ($_POST['do'] == 'killanswer')
{
	$answer = vB::getDbAssertor()->getRow('vBForum:hvanswer',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'answerid' => $vbulletin->GPC['answerid'])
	);

	if (!$answer)
	{
		print_stop_message2(array('invalid_x_specified', $vbphrase['answer']));
	}

	$deleteAnswer = vB::getDbAssertor()->assertQuery('vBForum:hvanswer',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, 'answerid' => $answer['answerid'])
	);

	print_stop_message2('deleted_answer_successfully', 'verify',
		array('do' => 'modifyquestion', 'questionid' =>  $answer['questionid']));
}

// ###################### Remove Question #######################
if ($_REQUEST['do'] == 'removequestion')
{
	$question = vB::getDbAssertor()->getRow('vBForum:fetchQuestionByPhrase', array('questionid' => $vbulletin->GPC['questionid']));

	if (!$question)
	{
		print_stop_message2(array('invalid_x_specified', $vbphrase['question']));
	}

	print_form_header('verify', 'killquestion');
	construct_hidden_code('questionid', $question['questionid']);
	print_table_header(construct_phrase($vbphrase['confirm_deletion_x'], htmlspecialchars_uni($question['text'])));
	print_description_row($vbphrase['are_you_sure_you_want_to_delete_this_question']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
}

// ###################### Kill Answer #######################
if ($_POST['do'] == 'killquestion')
{
	$question = vB::getDbAssertor()->getRow('vBForum:fetchQuestionByPhrase', array('questionid' => $vbulletin->GPC['questionid']));

	if (!$question)
	{
		print_stop_message2(array('invalid_x_specified', $vbphrase['question']));
	}

	$deleteAnswer = vB::getDbAssertor()->assertQuery('vBForum:hvanswer',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, 'questionid' => $question['questionid'])
	);

	$deleteQuestion = vB::getDbAssertor()->assertQuery('vBForum:hvquestion',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, 'questionid' => $question['questionid'])
	);

	$deletePhrase = vB::getDbAssertor()->assertQuery('vBForum:phrase',
		array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_DELETE, 'fieldname' => 'hvquestion', 'varname' => 'question' . $question['questionid'])
	);

	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language();

	print_stop_message2('deleted_question_successfully', 'verify');
}

// ###################### Intro Screen #######################
if ($_POST['do'] == 'updateoptions')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'setting'  => vB_Cleaner::TYPE_ARRAY,
	));

	save_settings($vbulletin->GPC['setting']);
	print_stop_message2('saved_settings_successfully', 'verify');
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>