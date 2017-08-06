<?php

/**
 * vB_Api_Contactus
 * vBulletin Contact Us API
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Contactus extends vB_Api
{
	/**
	 * @var vB_dB_Assertor
	 */
	protected $assertor;


	public function __construct()
	{
		parent::__construct();

		$this->assertor = vB::getDbAssertor();
	}

	/**
	 * Fetch predefined contact us subjects
	 *
	 * @return array
	 */
	public function fetchPredefinedSubjects()
	{
		$vboptions = vB::getDatastore()->getValue('options');

		$options = array();
		if ($vboptions['contactusoptions'])
		{
			$options = explode("\n", trim($vboptions['contactusoptions']));
			foreach($options AS $index => $title)
			{
				// Look for the {(int)} or {(email)} identifier at the start and strip it out
				if (preg_match('#^({.*}) (.*)$#siU', $title, $matches))
				{
					$options[$index] = $matches[2];
				}
			}
		}

		return $options;
	}

	/**
	 * Send contact us mail
	 *
	 * @param array $maildata contact us mail data. Including name, email, subject, other_subject, message
	 * @param array $hvinput Human Verify input data. @see vB_Api_Hv::verifyToken()
	 * @throws vB_Exception_Api
	 */
	public function sendMail($maildata, $hvinput = array())
	{
		$vboptions = vB::getDatastore()->getValue('options');

		if (empty($maildata['name']) || empty($maildata['email']) || empty($maildata['message']))
		{
			throw new vB_Exception_Api('please_complete_required_fields');
		}

		if ($vboptions['contactusoptions']
			AND $maildata['subject'] == 'other'
			AND ($maildata['other_subject'] == '' OR !$vboptions['contactusother']))
		{
			throw new vB_Exception_Api('nosubject');
		}

		if (!is_valid_email($maildata['email']))
		{
			throw new vB_Exception_Api('bademail');
		}

		vB_Api::instanceInternal('hv')->verifyToken($hvinput, 'contactus');

		// No Errors. Send mail.
		$languageid = -1;
		if ($vboptions['contactusoptions'])
		{
			if ($maildata['subject'] == 'other')
			{
				$maildata['subject'] = $maildata['other_subject'];
			}
			else
			{
				$options = explode("\n", trim($vboptions['contactusoptions']));
				foreach ($options AS $index => $title)
				{
					if ($index == $maildata['subject'])
					{
						if (preg_match('#^{(.*)} (.*)$#siU', $title, $matches))
						{
							$title =& $matches[2];
							if (is_numeric($matches[1]) AND intval($matches[1]) !== 0)
							{
								$userinfo = vB_User::fetchUserinfo($matches[1]);
								$alt_email =& $userinfo['email'];
								$languageid =& $userinfo['languageid'];
							}
							else
							{
								$alt_email = $matches[1];
							}
						}
						$maildata['subject'] = $title;
						break;
					}
				}
			}
		}

		if (!empty($alt_email))
		{
			if ($alt_email == $vboptions['webmasteremail'] OR $alt_email == $vboptions['contactusemail'])
			{
				$ip = vB::getRequest()->getIpAddress();
			}
			else
			{
				$ip =& $vbphrase['n_a'];
			}
			$destemail =& $alt_email;
		}
		else
		{
			$ip = vB::getRequest()->getIpAddress();
			if ($vboptions['contactusemail'])
			{
				$destemail =& $vboptions['contactusemail'];
			}
			else
			{
				$destemail =& $vboptions['webmasteremail'];
			}
		}

		$currentuser = vB::getCurrentSession()->fetch_userinfo();

		$mailcontent = vB_Api::instanceInternal('phrase')
			->fetchEmailPhrases('contactus',
				array($vboptions['bbtitle'], $maildata['name'], $maildata['email'], $maildata['message'], $ip, $currentuser['username'], $currentuser['userid']),
				array($vboptions['bbtitle'], $maildata['subject']), $languageid);

		$flood = vB_Mail::vbmail($destemail, $mailcontent['subject'], $mailcontent['message'], true, $maildata['email']);

		if(is_array($flood))
		{
			throw new vB_Exception_Api($flood[0], $flood[1]);
		}
		return true;

	}

}
