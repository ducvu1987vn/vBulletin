<?php

/**
 * vB_Api_Api
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Api extends vB_Api
{
	protected $dbassertor;

	protected function __construct()
	{
		parent::__construct();

		$this->dbassertor = vB::getDbAssertor();
	}

	/**
	 * Init an API client
	 *
	 * @param int $api_c API Client ID
	 * @param array $apiclientdata 'clientname', 'clientversion', 'platformname', 'platformversion', 'uniqueid'
	 * @return array
	 */
	public function init($clientname, $clientversion, $platformname, $platformversion, $uniqueid, $api_c = 0)
	{
		$oldclientid = $api_c;

		if (!$api_c)
		{
			// The client doesn't have an ID yet. So we need to generate a new one.

			// All params are required.
			// uniqueid is the best to be a permanent unique id such as hardware ID (CPU ID,
			// Harddisk ID or Mobile IMIE). Some client can not get a such a uniqueid,
			// so it needs to generate an unique ID and save it in its local storage. If it
			// requires the client ID and Secret again, pass the same unique ID.
			if (!$clientname OR !$clientversion OR !$platformname OR !$platformversion OR !$uniqueid)
			{
				throw new vB_Exception_Api('apiclientinfomissing');
			}

			// Gererate clienthash.
			$clienthash = md5($clientname . $platformname . $uniqueid);

			// Generate a new secret
			$secret = fetch_random_password(32);

			// If the same clienthash exists, return secret back to the client.
			$client = $this->dbassertor->getRow('apiclient', array('clienthash' => $clienthash));

			$api_c = $client['apiclientid'];

			if ($api_c)
			{
				// Update secret
				// Also remove userid so it will logout previous loggedin and remembered user. (VBM-553)
				$this->dbassertor->update('apiclient',
					array(
						'secret' => $secret,
						'apiaccesstoken' => vB::getCurrentSession()->get('apiaccesstoken'),
						'lastactivity' => vB::getRequest()->getTimeNow(),
						'clientversion' => $clientversion,
						'platformversion' => $platformversion,
						'userid' => 0
					),
					array(
						'apiclientid' => $api_c,
					)
				);
			}
			else
			{
				$api_c = $this->dbassertor->insert('apiclient', array(
					'secret' => $secret,
					'clienthash' => $clienthash,
					'clientname' => $clientname,
					'clientversion' => $clientversion,
					'platformname' => $platformname,
					'platformversion' => $platformversion,
					'initialipaddress' => vB::getRequest()->getAltIp(),
					'apiaccesstoken' => vB::getCurrentSession()->get('apiaccesstoken'),
					'dateline' => vB::getRequest()->getTimeNow(),
					'lastactivity' => vB::getRequest()->getTimeNow(),
				));

				if (is_array($api_c))
				{
					$api_c = array_pop($api_c);
				}
				$api_c = (int) $api_c;
			}

			// Set session client ID
			vB::getCurrentSession()->set('apiclientid', $api_c);
		}
		else
		{
			// api_c and api_sig are verified in init.php so we don't need to verify here again.
			$api_c = intval($api_c);

			// Update lastactivity
			$this->dbassertor->update('apiclient',
				array(
					'lastactivity' => vB::getRequest()->getTimeNow(),
				),
				array(
					'apiclientid' => $api_c,
				)
			);
		}

		$contenttypescache = vB_Types::instance()->getContentTypes();

		$contenttypes = array();
		foreach ($contenttypescache as $contenttype)
		{
			$contenttypes[$contenttype['class']] = $contenttype['id'];
		}

		$products = vB::getDatastore()->getValue('products');
		$vboptions = vB::getDatastore()->getValue('options');
		$userinfo = vB::getCurrentSession()->fetch_userinfo();

		// Check the status of CMS and Blog
		$blogenabled = ($products['vbblog'] == '1');
		$cmsenabled = ($products['vbcms'] == '1');

		$data = array(
			'apiversion' => VB_API_VERSION,
			'apiaccesstoken' => vB::getCurrentSession()->get('apiaccesstoken'),
			'bbtitle' => $vboptions['bbtitle'],
			'bburl' => $vboptions['bburl'],
			'bbactive' => $vboptions['bbactive'],
			'forumhome' => $vboptions['forumhome'],
			'vbulletinversion' => $vboptions['templateversion'],
			'contenttypes' => $contenttypes,
			'features' => array(
				'blogenabled' => true,
				'cmsenabled' => false,
				'pmsenabled' => (bool)$vboptions['enablepms'],
				'searchesenabled' => (bool)$vboptions['enablesearches'],
				'groupsenabled' => true,
				'albumsenabled' => true,
				'multitypesearch' => true,
				'taggingenabled' => (bool)$vboptions['threadtagging'],
			),
			'permissions' => $userinfo['permissions'],
			'show' => array(
				'registerbutton' => 1,
			),

		);

		if (!$vboptions['bbactive'])
		{
			$data['bbclosedreason'] = $vboptions['bbclosedreason'];
		}

		$data['apiclientid'] = $api_c;
		if (!$oldclientid)
		{
			$data['secret'] = $secret;
		}

		return $data;
	}
}
