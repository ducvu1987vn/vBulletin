<?php if (!defined('VB_ENTRY')) die('Access denied.');
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
 * vB_Api_Vb4_register
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Vb4_register extends vB_Api
{
	public function addmember(
		$agree,
		$username,
		$email,
		$emailconfirm,
		$password = null,
		$password_md5 = null,
		$passwordconfirm = null,
		$passwordconfirm_md5 = null,
		$userfield = null)
	{
		$cleaner = vB::getCleaner();
		$agree = $cleaner->clean($agree, vB_Cleaner::TYPE_UINT);
		$username = $cleaner->clean($username, vB_Cleaner::TYPE_STR);
		$email = $cleaner->clean($email, vB_Cleaner::TYPE_STR);
		$emailconfirm = $cleaner->clean($emailconfirm, vB_Cleaner::TYPE_STR);
		$password = $cleaner->clean($password, vB_Cleaner::TYPE_STR);
		$password_md5 = $cleaner->clean($password_md5, vB_Cleaner::TYPE_STR);
		$passwordconfirm_md5 = $cleaner->clean($passwordconfirm_md5, vB_Cleaner::TYPE_STR);
		$passwordconfirm = $cleaner->clean($passwordconfirm, vB_Cleaner::TYPE_STR);
		$userfield = $cleaner->clean($userfield, vB_Cleaner::TYPE_ARRAY);

		if (empty($agree))
		{
			return array('response' => array('errormessage' => array('register_not_agreed')));
		}

		if (empty($username) ||
			empty($email) ||
			empty($emailconfirm) ||
			empty($agree))
		{
			return array('response' => array('errormessage' => array('invalidid')));
		}

		if ((empty($password) ||
			empty($passwordconfirm)) &&
			(empty($password_md5) ||
			empty($passwordconfirm_md5)))
		{
			return array('response' => array('errormessage' => array('invalidid')));
		}

		if (!empty($password) && $password != $passwordconfirm)
		{
			return array('response' => array('errormessage' => array('invalidid')));
		}
		else
		{
			$password = $password;
		}

		if (!empty($password_md5) && $password_md5 != $passwordconfirm_md5)
		{
			return array('response' => array('errormessage' => array('invalidid')));
		}
		else
		{
			$password = $password_md5;
		}

		if ($email != $emailconfirm)
		{
			return array('response' => array('errormessage' => array('invalidid')));
		}

		$hv = vB_Library::instance('vb4_functions')->getHVToken();
		$result = vB_Api::instance('user')->save(0, $password, array('username' => $username, 'email' => $email), array(), array(), $userinput, array(), $hv);

		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}

		return array(
			'response' => array('errormessage' => array('registration_complete')),
			'session' => array('sessionhash' => $result['dbsessionhash']),
		);
	}

	public function call()
	{
		$result = vB_Api::instance('user')->fetchProfileFieldsForRegistration(array());
		if ($result === null || isset($result['errors']))
		{
			return array('response' => array('errormessage' => array('invalidid')));
		}

		$custom_fields_profile = array();
		foreach ($result['profile'] as $field)
		{
			$custom_fields_profile[] = $this->parseCustomField($field);
		}

		$custom_fields_other = array();
		foreach ($result['other'] as $field)
		{
			$custom_fields_other[] = $this->parseCustomField($field);
		}

		$custom_fields_option = array();
		foreach ($result['option'] as $field)
		{
			$custom_fields_option[] = $this->parseCustomField($field);
		}

		$result = vB_Api::instance('phrase')->fetch(array('site_terms_and_rules'));
		if ($result === null || isset($result['errors']))
		{
			return vB_Library::instance('vb4_functions')->getErrorResponse($result);
		}
		$rules = $result['site_terms_and_rules'];

		$out = array(
			'vbphrase' => array(
				'forum_rules_description' => $rules,
			),
			'response' => array(
				'customfields_other' => $custom_fields_other,
				'customfields_profile' => $custom_fields_profile,
				'customfields_option' => $custom_fields_option,
			),
		);
		return $out;
	}

	private function parseCustomField($data)
	{
		$field = array(
			'custom_field_holder' => array(
				'profilefield' => array(
					'type' => $data['type'],
					'title' => $data['title'],
					'description' => $data['description'],
					'currentvalue' => $data['currentvalue'],
				),
				'profilefieldname' => $data['fieldname'],
			),
		);

		if ($data['type'] == 'select' || $data['type'] == 'select_multiple')
		{
			$selectbits = array();
			foreach ($data['bits'] as $key => $bit)
			{
				$selectbits[] = array(
					'key' => $key,
					'val' => $bit['val'],
					'selected' => '',
				);
			}
			$field['custom_field_holder']['selectbits'] = $selectbits;
		}

		if ($data['type'] == 'radio' || $data['type'] == 'checkbox')
		{
			$radiobits = array();
			foreach ($data['bits'] as $key => $bit)
			{
				$radiobits[] = array(
					'key' => $key,
					'val' => $bit['val'],
					'checked' => '',
				);
			}
			$field['custom_field_holder']['radiobits'] = $radiobits;
		}

		return $field;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
