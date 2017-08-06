<?php

/**
 * vB_Api_Hv
 * vBulletin Human Verification API
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Hv extends vB_Api
{
	protected $disableWhiteList = array('fetchRequireHvcheck');
	
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
	 * Fetch Current Hv Type
	 * Possible Values:
	 *   - Image
	 *   - Question
	 *   - Recaptcha
	 *   - Disabled
	 *
	 * @return string Hv Type
	 */
	public function fetchHvType()
	{
		$vboptions = vB::getDatastore()->getValue('options');
		return ($vboptions['hv_type'] ? $vboptions['hv_type'] : 'Disabled');
	}

	/**
	 * Generate a HV token
	 *
	 * @return array It contains 2 items: answer - The correct answer hash - Token hash
	 */
	public function generateToken()
	{
		require_once(DIR . '/includes/class_humanverify.php');
		$verify =& vB_HumanVerify::fetch_library(vB::get_registry());
		$token = $verify->generate_token();
		if (!defined('VB_UNITTEST'))
		{
			unset($token['answer']);
		}
		return $token;
	}

	/**
	 * Verify a HV token and its answer
	 *
	 * @param string $input HV answer user input ('input') and other data (for example, 'hash')
	 * @param string $action The name of the action to check. register, lostpw etc.
	 * @param bool $return Whether to return a bool value instead of throwing an Exception.
	 *
	 * @throws vB_Exception_Api
	 * @return bool Whether the input answer/hash is correct
	 */
	public function verifyToken($input, $action, $return = false)
	{
		if (!$this->fetchRequireHvcheck($action))
		{
			return true;
		}

		//If we are running in phpunit test mode we just return
		//TODO- We need a better solution. This works for now but is homely
		if (defined('VB_UNITTEST'))
		{
			//we have to find out if we are running hvtest.php
			$stacktrace = debug_backtrace();
			$inHVTest = false;
			foreach ($stacktrace as $caller)
			{
				if (basename($caller['file']) == 'hvTest.php')
				{
					$inHVTest = true;
					break;
				}
			}

			if (!$inHVTest)
			{
				//We are not testing hVTest. So just take the human verify test as passed.
				return true;
			}
		}

		require_once(DIR . '/includes/class_humanverify.php');
		$verify =& vB_HumanVerify::fetch_library(vB::get_registry());
		$data = $verify->verify_token($input);
		if ($data)
		{
			return true;
		}
		else
		{
			if ($return)
			{
				return false;
			}
			else
			{
				throw new vB_Exception_Api($verify->fetch_error());
			}
		}
	}

	/**
	 * Fetch Human Verification Image Data
	 *
	 * @param $hash
	 * @return array 'type' => Image type 'data' => Image binary data
	 */
	public function fetchHvImage($hash = '')
	{
		$vboptions = vB::getDatastore()->getValue('options');

		$moveabout = true;
		if (!$hash OR $hash == 'test' OR $vboptions['hv_type'] != 'Image')
		{
			$imageinfo = array(
				'answer' => 'vBulletin',
			);

			$moveabout = $hash == 'test' ? true : false;
		}
		else if (
			!(
				$imageinfo = $this->assertor->getRow('humanverify', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					'hash' => $hash,
					'viewed' => 0,
				))
			)
		)
		{
			return array(
				'type' => 'gif',
				'data' => file_get_contents(DIR . '/' . $vboptions['cleargifurl'])
			);
		}
		else
		{
			$this->assertor->assertQuery('humanverify', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
				'viewed' => 1,
				vB_dB_Query::CONDITIONS_KEY => array(
					'hash' => $hash,
					'viewed' => 0,
				)
			));

			if ($this->assertor->affected_rows() == 0)
			{	// image managed to get viewed by someone else between the $imageinfo query above and now
				return array(
					'type' => 'gif',
					'data' => file_get_contents(DIR . '/' . $vboptions['cleargifurl'])
				);
			}

		}

		$image = vB_Image::instance();

		$imageInfo = $image->getImageFromString($imageinfo['answer'], $moveabout);

		return array(
			'type' => $imageInfo['filetype'],
			'data' => $imageInfo['filedata']
		);

	}

	/**
	 * Fetch Human Verification Question Data
	 *
	 * @param $hash
	 * @return string Question
	 */
	public function fetchHvQuestion($hash = '')
	{
		if (!$hash) {
			throw new vB_Exception_Api('invalid_hash');
		}

		$hv = $this->assertor->getRow('humanverify', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			'hash' => $hash,
		));

		$questionid = $hv['answer'];

		if (!$questionid) {
			throw new vB_Exception_Api('invalid_hash');
		}

		$phrases = vB_Api::instanceInternal('phrase')->fetch(array('question' . $questionid));

		return $phrases['question' . $questionid];
	}

	/**
	 * Fetch Human Verification reCAPTCHA Data
	 *
	 * @return array reCAPTCHA required data
	 */
	public function fetchHvRecaptcha()
	{
		$data = array();
		$vboptions = vB::getDatastore()->getValue('options');

		$data['publickey'] = ($vboptions['hv_recaptcha_publickey'] ? $vboptions['hv_recaptcha_publickey'] : '6LfHsgMAAAAAAMVjkB1nC_nI5qfAjVk0qxz4VtPV');
		$data['theme'] = $vboptions['hv_recaptcha_theme'];

		if (preg_match('#^([a-z]{2})-?#i', vB_Template_Runtime::fetchStyleVar('languagecode'), $matches))
		{
			$data['langcode'] = strtolower($matches[1]);
		}

		return $data;
	}

	/**
	 * Returns whether or not the user requires a human verification test to complete the specified action
	 *
     * @param string $action The name of the action to check. Possible values: register, post, search, contactus, lostpw
	 * @return boolean Whether a hv check is required
	 */
	public function fetchRequireHvcheck($action)
	{
		static $results = array();

		if (!empty($results[$action]))
		{
			return $results[$action];
		}

		$results[$action] = fetch_require_hvcheck($action);
		return $results[$action];
	}
}
