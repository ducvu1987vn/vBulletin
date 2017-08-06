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
// if (!is_object($vbulletin->db))
// {
// 	exit;
// }

// ########################## REQUIRE BACK-END ############################
require_once(DIR . '/includes/class_paid_subscription.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vb5_config =& vB::getConfig();

$api = vB::getDbAssertor()->getRow('vBForum:paymentapi', array('classname' => 'ccbill'));

$subobj = new vB_PaidSubscription();
$settings = $subobj->construct_payment_settings($api['settings']);

if (!$api['active'] OR !$settings['clientAccnum'] OR !$settings['clientAccnum'] OR !$settings['username'] OR !$settings['password'])
{
	exit;
}

$timenow = vB::getRequest()->getTimeNow();
$args = array(
	'startTime'        => date('YmdHis', $timenow - 86400),
	'endTime'          => date('YmdHis', $timenow),
	'transactionTypes' => 'REFUND,VOID,CHARGEBACK',
	'clientAccnum'     => $settings['clientAccnum'],
	'clientSubacc'     => $settings['clientSubacc'],
	'username'         => $settings['username'],
	'password'         => $settings['password'],
#	'testMode'         => 1,
);

$params = '';
$result = '';
if (function_exists('curl_init') AND $ch = curl_init())
{
	$params = '';
	foreach($args AS $key => $value)
	{
		$params .= "$key=$value&";
	}

	curl_setopt($ch, CURLOPT_URL, 'https://datalink.ccbill.com/data/main.cgi');
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, 'vBulletin via cURL/PHP');

	$result = curl_exec($ch);
	if ($result === false AND curl_errno($ch) == '60') ## CURLE_SSL_CACERT problem with the CA cert (path? access rights?)
	{
		curl_setopt($ch, CURLOPT_CAINFO, DIR . '/includes/paymentapi/ca-bundle.crt');
		$result = curl_exec($ch);
	}

	if ($result === false)
	{
		echo 'CURL Failed<pre>' . curl_error($ch) . '</pre>';
	}
	else
	{
		$used_curl = true;
	}
	curl_close($ch);
}

if (!$used_curl AND function_exists('openssl_open'))
{
	if ($fp = fsockopen('ssl://datalink.ccbill.com', 443, $errno, $errstr, 15))
	{
		stream_set_timeout($fp, 15);

		$params = 'GET /data/main.cgi?';
		foreach($args AS $key => $value)
		{
			$params .= "$key=$value&";
		}

		$params .= " HTTP/1.0\r\n";
		$params .= "Host: datalink.ccbill.com\r\n";
		$params .= "User-Agent: PHP via fsockopen\r\n";
		$params .= "Connection: close\r\n\r\n";

		fwrite($fp, $params, strlen($params));

		while (!feof($fp))
		{
			$results = fgets($fp);
			if (preg_match('#^("|Error:)#', $results))
			{
				$result .= $results;
			}
		}
		fclose($fp);
	}
	else if (VB_AREA == 'AdminCP')
	{
		echo htmlspecialchars_uni("$errstr ($errno)");
	}
}

// Example Results
/*
$result =
'"REFUND","931045","0005","2000000001","20041201105542","1.99"
"REFUND","931045","0005","2000000002","20041201100542","4.32"
"REFUND","931045","0005","2000000003","20041201105542","2.90"
"VOID","931045","0005","2000000001","","1.99"
"VOID","931045","0005","2000000002","","4.32"
"VOID","931045","0005","2000000003","","2.90"
"CHARGEBACK","931045","0005","2000000001","20041201105542","1.99"
"CHARGEBACK","931045","0005","2000000002","20041201100542","4.32"
"CHARGEBACK","931045","0005","2000000003","20041201105542","2.90"
"CHARGEBACK","931045","0005","2000867333","20041201105542","2.90"';

$result = 'Error: Authentication failed.714';
*/

if ($vb5_config['Misc']['debug'] AND VB_AREA == 'AdminCP')
{
	echo "<pre>$params</pre>";
	if ($result)
	{
		echo "<pre>$result</pre>";
	}
}

$log = '';
$count = 0;
if ($result)
{
	if (!preg_match('#^Error:#', $result))
	{
		$result = str_replace('"', '', $result);

		$ids = array();
		$trans = explode("\n", $result);

		foreach($trans AS $value)
		{
			$options = explode(',', $value);
			if (!empty($options[3]))
			{
				$ids[] = $options[3];
			}
		}

		if (!empty($ids))
		{

			$insert = array();
			$updatetrans = array();
			$subs = vB::getDbAssertor()->assertQuery('fetchSubs2Del', array('transactionid' => $ids));
			foreach ($subs as $sub)
			{
				$subobj->delete_user_subscription($sub['subscriptionid'], $sub['userid'], $sub['subscriptionsubid']);
				$insert[] = array(2, $timenow , 'usd', $sub['amount'], $sub['transactionid'] . 'R', $sub['paymentinfoid'], $api['paymentapiid']);
				$updatetrans[] = $sub['paymenttransactionid'];
				$count++;
			}

			if (!empty($insert))
			{
				vB::getDbAssertor()->insertMultiple('paymenttransaction', array('state', 'dateline', 'currency', 'amount', 'transactionid', 'paymentinfoid', 'paymentapiid'), $insert);
				vB::getDbAssertor()->update('paymenttransaction', array('reversed' => 1), array('paymenttransactionid' => $updatetrans));
			}
		}
		$log = $count;
	}
	else
	{	// Error
		$log = htmlspecialchars_uni($result);
	}
}

log_cron_action($log, $nextitem, 1);


/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 67762 $
|| ####################################################################
\*======================================================================*/
?>