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

require_once(DIR . '/includes/class_vurl.php');

/**
* Human Verification class for reCAPTCHA Verification (http://recaptcha.net)
*
* @package 		vBulletin
* @version		$Revision: 69887 $
* @date 		$Date: 2012-12-27 11:20:34 -0800 (Thu, 27 Dec 2012) $
*
*/
class vB_HumanVerify_Recaptcha extends vB_HumanVerify_Abstract
{
	/**
	* Constructor
	*
	* @return	void
	*/
	function vB_HumanVerify_Recaptcha(&$registry)
	{
		parent::vB_HumanVerify_Abstract($registry);
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
		if (!isset($input['recaptcha_challenge_field']))
		{
			$input['recaptcha_challenge_field'] = '';
		}
		if (!isset($input['recaptcha_response_field']))
		{
			$input['recaptcha_response_field'] = '';
		}
		
		if ($input['recaptcha_response_field'] AND $input['recaptcha_challenge_field'])
		{	// Contact recaptcha.net
			$private_key = ($this->registry->options['hv_recaptcha_privatekey'] ? $this->registry->options['hv_recaptcha_privatekey'] : '6LfHsgMAAAAAACYsFwZz6cqcG-WWnfay7NIrciyU');
			$query = array(
				'privatekey=' . urlencode($private_key),
				'remoteip=' . urlencode(IPADDRESS),
				'challenge=' . urlencode($input['recaptcha_challenge_field']),
				'response=' . urlencode($input['recaptcha_response_field']),
			);

			$vurl = new vB_vURL($this->registry);
			$vurl->set_option(VURL_URL, 'http://api-verify.recaptcha.net/verify');
			$vurl->set_option(VURL_USERAGENT, 'vBulletin ' . FILE_VERSION);
			$vurl->set_option(VURL_POST, 1);
			$vurl->set_option(VURL_POSTFIELDS, implode('&', $query));
			$vurl->set_option(VURL_RETURNTRANSFER, 1);
			$vurl->set_option(VURL_CLOSECONNECTION, 1);

			if (($result = $vurl->exec()) === false)
			{
				$this->error = 'humanverify_recaptcha_unreachable';
				return false;
			}
			else
			{
				$result = explode("\n", $result);
				if ($result[0] === 'true')
				{
					return true;
				}

				switch ($result[1])
				{
					case 'invalid-site-public-key':
						$this->error = 'humanverify_recaptcha_publickey';
						break;
					case 'invalid-site-private-key':
						$this->error = 'humanverify_recaptcha_privatekey';
						break;
					case 'invalid-referrer':
						$this->error = 'humanverify_recaptcha_referrer';
						break;
					case 'invalid-request-cookie':
						$this->error = 'humanverify_recaptcha_challenge';
						break;
					case 'verify-params-incorrect':
						$this->error = 'humanverify_recaptcha_parameters';
						break;
					default:
						$this->error = 'humanverify_image_wronganswer';
				}

				return false;
			}
		}
		else
		{
			$this->error = 'humanverify_image_wronganswer';
			return false;
		}
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

		if (vB::getRequest()->getVbUrlScheme() === 'https')
		{
			$show['recaptcha_ssl'] = true;
		}

		$humanverify['publickey'] = ($this->registry->options['hv_recaptcha_publickey'] ? $this->registry->options['hv_recaptcha_publickey'] : '6LfHsgMAAAAAAMVjkB1nC_nI5qfAjVk0qxz4VtPV');
		$humanverify['theme'] = $this->registry->options['hv_recaptcha_theme'];

		if (preg_match('#^([a-z]{2})-?#i', vB_Template_Runtime::fetchStyleVar('languagecode'), $matches))
		{
			$humanverify['langcode'] = strtolower($matches[1]);
		}

		if(THIS_SCRIPT === 'ajax')
		{
			$humanverify['load_js'] = false;
		}
		else
		{
			$humanverify['load_js'] = true;
		}
		$templater = vB_Template::create('humanverify_recaptcha');
			$templater->register('humanverify', $humanverify);
			$templater->register('var_prefix', $var_prefix);
		$output = $templater->render();

		return $output;
	}

	/**
	* expected answer - with this class, we don't know the answer
	*
	* @return	string
	*/
	function fetch_answer()
	{
		return '';
	}
	
	/**
	 * generate token - Normally we want to generate a token to validate against. However, 
	 * 		Recaptcha is doing that work for us.
	 * 
	 * @param	boolean	Delete the previous hash generated
	 * 
	 * @return	array	an array consisting of the hash, and the answer
	 */
	function generate_token($deletehash = true)
	{
		return array(
			'hash' => '', 
			'answer' => $this->fetch_answer(),
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 69887 $
|| ####################################################################
\*======================================================================*/
?>
