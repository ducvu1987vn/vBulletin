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
* Human Verification class for Question & Answer Verification
*
* @package 		vBulletin
* @version		$Revision: 68365 $
* @date 		$Date: 2012-11-12 10:27:40 -0800 (Mon, 12 Nov 2012) $
*
*/
class vB_HumanVerify_Question extends vB_HumanVerify_Abstract
{
	/**
	* Constructor
	*
	* @return	void
	*/
	function vB_HumanVerify_Question(&$registry)
	{
		parent::vB_HumanVerify_Abstract($registry);
	}

	/**
	 * Returns the HTML to be displayed to the user for Human Verification
	 *
	 * @param	string	Passed to template
	 *
	 * @return 	string	HTML to output
	 *
	 */
	function output_token($var_prefix = 'humanverify')
	{
		global $vbphrase, $show;
		$vbulletin =& $this->registry;

		$humanverify = $this->generate_token();

		$phraseAux = vB_Api::instanceInternal('phrase')->fetch(array('question' . $humanverify['answer']));
		$humanverify['question'] = $phraseAux['question' . $humanverify['answer']];

		$templater = vB_Template::create('humanverify_question');
			$templater->register('humanverify', $humanverify);
			$templater->register('var_prefix', $var_prefix);
		$output = $templater->render();

		return $output;
	}

	/**
	 * Fetches a random question ID from the database
	 *
	 * @return	integer
	 *
	 */
	function fetch_answer()
	{
		$question = vB::getDbAssertor()->getRow('hv_question_fetch_answer');

		return $question['questionid'];
	}

	/**
	* Verify is supplied token/reponse is valid
	*
	*	@param	array	Values given by user 'input' and 'hash'
	*
	* @return	bool
	*/
	function verify_token($input)
	{
		$input['input'] = trim($input['input']);
		vB::getDbAssertor()->assertQuery('humanverify', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'viewed' => 1,
			vB_dB_Query::CONDITIONS_KEY => array(
				'hash' => $input['hash'],
				'viewed' => 0
			)
		));
		if (vB::getDbAssertor()->affected_rows())
		{
			// Hitting the master since we just updated this value
			$question = vB::getDbAssertor()->getRow('hv_question_fetch', array(
				'hash' => $input['hash'],
			));

			// Remove token since we no longer need it.
			$this->delete_token($input['hash']);

			if (!$question)
			{
				// this happens if the hash gets killed somewhere between the update and select
				$this->error = 'humanverify_question_wronganswer';
				return false;
			}
			else if (!$question['questionid'])
			{
				// this happens if no question was available, so we need to just accept their answer
				// otherwise it'd be impossible to get past
				return true;
			}
			else
			{	// Check answer!
				if ($question['regex'] AND preg_match('#' . str_replace('#', '\#', $question['regex']) . '#siU', $input['input']))
				{
					return true;
				}
				else if (
					vB::getDbAssertor()->getRow('hvanswer', array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						'questionid' => intval($question['questionid']),
						'answer' => $input['input']
					))
				)
				{
					return true;
				}
				else
				{
					$this->error = 'humanverify_question_wronganswer';
					return false;
				}
			}
		}
		else
		{
			$this->delete_token($input['hash'], NULL, 0);
			$this->error = 'humanverify_question_wronganswer';
			return false;
		}
	}

}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 68365 $
|| ####################################################################
\*======================================================================*/
?>
