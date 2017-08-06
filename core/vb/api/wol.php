<?php

/**
 * vB_Api_Wol
 * Who is online API
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Wol extends vB_Api
{
	protected $onlineusers = array();

	protected function __construct()
	{
		parent::__construct();
	}

	/**
	 * Register an online action
	 * Example:
	 *   vB_Api::instanceInternal('Wol')->register('viewing_x', array(array('nodeid', $nodeid)));
	 *
	 * @param string $action
	 * @param array $params Parameters of the action
	 *        It's an array of parameters that will be used in the phrase
	 *        The key of a parameter is the index-1 of a phrase brace var
	 *        The value of a parameter may be a string which will directly replace brance var
	 *        Other types of id may be added later
	 * @param string $pagekey Pagekey of the page where the user is
	 * @return void
	 */
	public function register($action, $params = array(), $pagekey = '',  $location = '')
	{
		$actiondata = array(
			'action' => $action,
			'params' => $params,
		);

		$sessionhash = vB::getCurrentSession()->get('dbsessionhash');
		$data = array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_UPDATE,
			'wol' => @serialize($actiondata),
			'pagekey' => $pagekey,
			'location' => $location,
			vB_dB_Query::CONDITIONS_KEY => array(
				'sessionhash' => $sessionhash,
			)
		);

		// Update action field of session table
		vB::getDbAssertor()->assertQuery('session', $data);
	}

	/**
	 * Fetch who is online records
	 *
	 * @param string $pagekey Fetch users who are only on the page with this pagekey
	 * @param string $who Show 'members', 'guests', 'spiders' or all ('')
	 * @param int $pagenumber
	 * @param int $perpage
	 * @param string $sortfield
	 * @param string $sortorder
	 * @return array Who is online information
	 */
	public function fetchAll($pagekey = '', $who = '', $pagenumber = 1, $perpage = 0, $sortfield = 'username', $sortorder = 'asc', $resolveIp = false)
	{
		$currentUserContext = vB::getUserContext();
		if (!$currentUserContext->hasPermission('wolpermissions', 'canwhosonline'))
		{
			throw new vB_Exception_Api('no_permission');
		}

		$vboptions = vB::getDatastore()->getValue('options');
		$bf_misc_useroptions = vB::getDatastore()->getValue('bf_misc_useroptions');
		// check permissions
		$canSeeIp = $currentUserContext->hasPermission('wolpermissions', 'canwhosonlineip');
		$canViewFull = $currentUserContext->hasPermission('wolpermissions', 'canwhosonlinefull');
		$canViewBad = $currentUserContext->hasPermission('wolpermissions', 'canwhosonlinebad');
		$canViewlocationUser = $currentUserContext->hasPermission('wolpermissions', 'canwhosonlinelocation');

		$data = array(
			'who' => $who,
			'pagenumber' => $pagenumber,
			vB_dB_Query::PARAM_LIMIT => $perpage,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
		);

		if ($pagekey)
		{
			$data['pagekey'] = $pagekey;
		}

		$allusers = vB::getDbAssertor()->assertQuery('fetchWolAllUsers', $data);

		$onlineUsers = array();
		$onlineGuests = array();
		foreach ($allusers as $userRecord)
		{
			$usergroupidAux = $userRecord['usergroupid'];
			$userRecord = array_merge($userRecord, convert_bits_to_array($userRecord['options'] , $bf_misc_useroptions));
			$resolved = false;
			
			if ($userRecord['invisible'])
			{
				if (!($currentUserContext->hasPermission('genericpermissions', 'canseehidden') OR $userRecord['userid'] == vB::getCurrentSession()->fetch_userinfo_value('userid')))
				{
					continue;
				}
			}

			if (($userRecord['userid'] > 0 AND (empty($onlineUsers[$userRecord['userid']]) OR $onlineUsers[$userRecord['userid']]['rawlastactivity'] < $userRecord['lastactivity']))
				OR $userRecord['userid'] == 0)
			{

				//We only want the most recent record
				if (($userRecord['userid'] > 0) AND isset($onlineUsers[$userRecord['userid']]))
				{
					continue;
				}

				if ($canViewFull)
				{
					$user = $userRecord;
					$user['usergroupid'] = $usergroupidAux;
					$user['musername'] = vB_Api::instanceInternal("user")->fetchMusername($user);

					if (isset($user['wol']))
					{
						$user['wol'] = @unserialize($user['wol']);
					}
				}
				else
				{
					$user = array(
						'username' => $userRecord['username'],
						'userid' => $userRecord['userid'],
					);

					if ($canSeeIp)
					{
						$user['host'] = $userRecord['host'];
					}

					if ($canViewBad)
					{
						$user['bad'] =  $userRecord['badlocation'];
					}

					if (isset($userRecord['wol']))
					{
						$wol =  @unserialize($userRecord['wol']);

						if (!empty($wol['action']))
						{
							$user['wol']['action'] = $wol['action'];
						}
					}

					if ($canViewlocationUser)
					{
						$user['location'] = $userRecord['location'];
					}
				}

				// Last activity is always shown
				$user['rawlastactivity'] = $user['lastactivity'];
				$user['lastactivity'] = vbdate($vboptions['dateformat'] . ' ' . $vboptions['timeformat'], $user['lastactivity']);

				// We need the avatars as per the wireframes
				$avatar = vB_Api::instanceInternal('user')->fetchAvatar($user['userid']);
				$user['avatarpath'] = $avatar['avatarpath'];

				if (!$canViewlocationUser)
				{
					unset($user['location']);
				}

				if (!$canSeeIp)
				{
					unset($user['host']);
				}

				if (!$user['username'])
				{
					$phrase = vB_Api::instanceInternal('phrase')->fetch('guest');
					$user['username'] = $phrase['guest'];
				}

				if ($resolveIp AND $canSeeIp)
				{
					$user['host'] = @gethostbyaddr($user['host']);
				}
				
				// guests don't have reputation
				if($user['userid'] > 0)
				{
					$user['reputationimg'] = vB_Library::instance('reputation')->fetchReputationImageInfo($userRecord);
				}

				$resolved = true;
			}

			if ($user['userid'] == 0)
			{
				$onlineGuests[] = $user;
			}
			else if ($resolved)
			{
				$onlineUsers[$user['userid']] = $user;
			}
		}

		return array_merge($onlineUsers, $onlineGuests);
	}

	public function refreshUsers($pagekey = '', $who = '', $pagenumber = 1, $perpage = 0, $sortfield = 'username', $sortorder = 'asc', $resolveIp = false)
	{
		$result = array();

		$onlineUsers = $this->fetchAll($pagekey, $who, $pagenumber, $perpage, $sortfield , $sortorder, $resolveIp);

		$template = new vB5_Template('onlineuser_details');
		$template->register('onlineUsers', $onlineUsers);
		$template = $template->render();

		$userCounts = $this->fetchCounts($pagekey);

		$result['template'] = $template;
		$result['userCounts'] = $userCounts;
		return $result;
	}

	/**
	 * Fetch an user's who is online info
	 *
	 * @param $userid Userid
	 * @return array User's who is online information
	 */
	public function fetch($userid)
	{
		$user = vB::getDbAssertor()->getRow('fetchWol', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
			'userid' => $userid,
		));

		if ($user)
		{
//			$this->updateWolParams($user);
			$user['wol'] = @unserialize($user['wol']);
		}

		return $user;
	}

	/**
	 * Fetch online user counts
	 *
	 * @param string $pagekey Fetch users who are only on the page with this pagekey
	 * @return array Counts
	 */
	public function fetchCounts($pagekey = '')
	{
		$currentUserContext = vB::getUserContext();
		if (!$currentUserContext->hasPermission('wolpermissions', 'canwhosonline'))
		{
			throw new vB_Exception_Api('no_permission');
		}

		if ($pagekey)
		{
			$members = vB::getDbAssertor()->getField('fetchWolCount', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'pagekey' => $pagekey,
				'who' => 'members',
			));
			$guests = vB::getDbAssertor()->getField('fetchWolCount', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'pagekey' => $pagekey,
				'who' => 'guests',
			));
		}
		else
		{
			$members = vB::getDbAssertor()->getField('fetchWolCount', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'who' => 'members',
			));
			$guests = vB::getDbAssertor()->getField('fetchWolCount', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				'who' => 'guests',
			));
		}

		$maxloggedin = vB::getDatastore()->get_value('maxloggedin');
		$vboptions = vB::getDatastore()->get_value('options');

		$totalonline = $members + $guests;

		// Update max loggedin users
		if (intval($maxloggedin['maxonline']) <= $totalonline)
		{
			$maxloggedin['maxonline'] = $totalonline;
			$maxloggedin['maxonlinedate'] = vB::getRequest()->getTimeNow();
			build_datastore('maxloggedin', serialize($maxloggedin), 1);
		}

		$recordusers = vb_number_format($maxloggedin['maxonline']);
		$recorddate = vbdate($vboptions['dateformat'], $maxloggedin['maxonlinedate']);
		$recordtime = vbdate($vboptions['timeformat'], $maxloggedin['maxonlinedate']);

		return array(
			'total' => $members + $guests,
			'members' => $members,
			'guests' => $guests,
			'recordusers' => $recordusers,
			'recorddate' => $recorddate,
			'recordtime' => $recordtime,
		);
	}

	protected function checkWOLPermission($permission)
	{
		$loginuser = &vB::getCurrentSession()->fetch_userinfo();
		$usercontext = &vB::getUserContext($loginuser['userid']);
		return $usercontext->hasPermission('wolpermissions', $permission);
	}

	public static function buildSpiderList()
	{
		$spiders = array();
		require_once(DIR . '/includes/class_xml.php');

		$files = vB_Api_Product::loadProductXmlList('spiders');

		foreach ($files AS $file)
		{
			$xmlobj = new vB_XML_Parser(false, $file);
			$spiderdata = $xmlobj->parse();

			if (is_array($spiderdata['spider']))
			{
				foreach ($spiderdata['spider'] AS $spiderling)
				{
					$addresses = array();
					$identlower = strtolower($spiderling['ident']);
					$spiders['agents']["$identlower"]['name'] = $spiderling['name'];
					$spiders['agents']["$identlower"]['type'] = $spiderling['type'];
					if (is_array($spiderling['addresses']['address']) AND !empty($spiderling['addresses']['address']))
					{
						if (empty($spiderling['addresses']['address'][0]))
						{
							$addresses[0] = $spiderling['addresses']['address'];
						}
						else
						{
							$addresses = $spiderling['addresses']['address'];
						}

						foreach ($addresses AS $key => $address)
						{
							if (in_array($address['type'], array('range', 'single', 'CIDR')))
							{
								$address['type'] = strtolower($address['type']);

								switch($address['type'])
								{
									case 'single':
										$ip2long = ip2long($address['value']);
										if ($ip2long != -1 AND $ip2long !== false)
										{
											$spiders['agents']["$identlower"]['lookup'][] = array(
												'startip' => $ip2long,
											);
										}
										break;

									case 'range':
										$ips = explode('-', $address['value']);
										$startip = ip2long(trim($ips[0]));
										$endip = ip2long(trim($ips[1]));
										if ($startip != -1 AND $startip !== false AND $endip != -1 AND $endip !== false AND $startip <= $endip)
										{
											$spiders['agents']["$identlower"]['lookup'][] = array(
												'startip' => $startip,
												'endip'   => $endip,
											);
										}
										break;

									case 'cidr':
										$ipsplit = explode('/', $address['value']);
										$startip = ip2long($ipsplit[0]);
										$mask = $ipsplit[1];
										if ($startip != -1 AND $startip !== false AND $mask <= 31 AND $mask >= 0)
										{
											$hostbits = 32 - $mask;
											$hosts = pow(2, $hostbits) - 1; // Number of specified IPs
											$endip = $startip + $hosts;
											$spiders['agents']["$identlower"]['lookup'][] = array(
												'startip' => $startip,
												'endip'   => $endip,
											);
										}
										break;
								}
							}
						}
					}

					$spiders['spiderstring'] .= ($spiders['spiderstring'] ? '|' : '') . preg_quote($spiderling['ident'], '#');
				}
			}

			unset($spiderdata, $xmlobj);
		}

		vB::getDatastore()->build('spiders', serialize($spiders), 1);

		return vB::getDatastore()->getValue('spiders');
	}
}
