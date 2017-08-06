<?php

//
//  Extend site, as a convinient location to
//  add our hook. This corresponds to the old
//  request.php code.
//

class Forumrunner_Api_Site extends vB_Api_Extensions
{
	protected $product = 'forumrunner';
	protected $title = 'Forum Runner Product Request.php';

	protected $minver = '5.0.0 Alpha';
	protected $maxver = '5.0.99';

	protected $AutoInstall = 1;
	protected $extensionOrder = 10;

	function forumrunner_request($default)
	{
		define('MCWD', DIR . '/packages/forumrunner');
		define('IN_FRNR', true);

		header('Content-type: application/json');
		if (isset($_REQUEST['d'])) {
			error_reporting(E_ALL);
		} else {
			error_reporting(0);
		}

		require_once(MCWD . '/version.php');
		require_once(MCWD . '/support/utils.php');
		require_once(MCWD . '/support/JSON.php');
		require_once(MCWD . '/include/general_vb.php');

		if (file_exists(MCWD . '/branded.php')) {
			require_once(MCWD .'/branded.php');
		}

		$processed = process_input(array('cmd' => STRING, 'frv' => STRING, 'frp' => STRING));
		if (!$processed['cmd']) {
			return json_error(ERR_NO_PERMISSION);
		}

		$frcl_version = '1.3.3';
		$frcl_platform = 'ip';
		if (isset($processed['frv'])) {
			$frcl_version = $processed['frv'];
		}
		if (isset($processed['frp'])) {
			$frcl_platform = $processed['frp'];
		}

		require_once(MCWD . '/support/common_methods.php');
		require_once(MCWD . '/support/vbulletin_methods.php');
		if (file_exists(MCWD . '/support/other_methods.php')) {
			require_once(MCWD . '/support/other_methods.php');
		}

		$json = new Services_JSON();

		if (!isset($methods[$processed['cmd']])) {
			return json_error(ERR_NO_PERMISSION);
		}

		if ($methods[$processed['cmd']]['include']) {
			require_once(MCWD . '/include/' . $methods[$processed['cmd']]['include']);
		}

		if (isset($_REQUEST['d'])) {
			error_reporting(E_ALL);
		}

		$out = call_user_func($methods[$processed['cmd']]['function']);

		if (is_string($out)) 
		{
			return $out;
		}
		else if (is_array($out))
		{
			$data = $out;
		}
		else if (is_bool($out) && $out)
		{
			$data = array(
				'success' => true,
			);
		}
		else
		{
			return json_error(ERR_NO_PERMISSION);
		}

		// If we're here, we have success!
		$json_out = array();
		$json_out['success'] = true;
		$json_out['data'] = $data;
		$json_out['ads'] = fr_show_ad();

		$userinfo = vB_Api::instance('user')->fetchUserInfo();
		// Return Unread PM/Subscribed Threads count
		if ($userinfo['userid'] > 0 &&
			$processed['cmd'] != 'get_new_updates' &&
			$processed['cmd'] != 'logout' &&
			$processed['cmd'] != 'login')
		{
			if ($userinfo['userid'] > 0) {
				$json_out['pm_notices'] = get_pm_unread();
				$json_out['sub_notices'] = get_sub_thread_updates();
			}
		}

		vB5_Cookie::set('lastvisit', vB::getRequest()->getTimeNow(), 365, true);
		return $json->encode($json_out);
	}

	function forumrunner_image($default)
	{
		define('MCWD', DIR . '/packages/forumrunner');

		require_once(MCWD . '/support/utils.php');
		require_once(MCWD . '/support/Snoopy.class.php');

		$args = process_input(
			array(
				'url' => STRING,
				'w' => INTEGER,
				'h' => INTEGER,
			)
		);

		if (isset($args['w']) && ($args['w'] > 1024 || $args['w'] <= 0)) {
			$args['w'] = 75;
		}
		if (isset($args['h']) && ($args['h'] > 1024 || $args['h'] <= 0)) {
			$args['h'] = 75;
		}

		if (!isset($args['url'])) {
			die();
		}

		if (!extension_loaded('gd') && !extension_loaded('gd2')) {
			trigger_error("GD is not loaded", E_USER_ERROR);
			die();
		}

		$snoopy = new snoopy();

		$snoopy->cookies = $_COOKIE;

		$args['url'] = trim(str_replace(' ', '%20', $args['url']));

		if ($snoopy->fetch($args['url'])) {
			$image = @imagecreatefromstring($snoopy->results);

			if ($image) {
				if (isset($args['w']) && isset($args['h'])) {
					$oldwidth = imagesx($image);
					$oldheight = imagesy($image);
					$newwidth = $oldwidth;
					$newheight = $oldheight;
					if ($oldwidth > $oldheight) {
						$newwidth = $args['w'];
						$newheight = ((float)$newwidth / (float)$oldwidth) * $oldheight;
					} else {
						$newheight = $args['h'];
						$newwidth = ((float)$newheight / (float)$oldheight) * $oldwidth;
					}
					$new_image = imagecreatetruecolor($newwidth, $newheight);
					imagecopyresampled($new_image, $image, 0, 0, 0, 0, $newwidth, $newheight, imagesx($image), imagesy($image));
					header('Content-type: image/jpeg');
					imagejpeg($new_image);
					exit;
				} else {
					header('Content-type: image/jpeg');
					imagejpeg($image);
					exit;
				}
			}
		}
	}

	function forumrunner_ad($default)
	{
		$options = vB::get_datastore()->get_value('options');
		$agent = strtolower($_SERVER['HTTP_USER_AGENT']);

		if (strpos($agent, 'iphone') === false && strpos($agent, 'ipad') === false &&
			strpos($agent, 'ipod') === false && strpos($agent, 'android') === false)
		{
			die();
		}

		$kw = $options['keywords'] . ' ' . $options['description'];
		echo "<html><head><style>* {margin:0; padding:0;}</style></head><body>
			<span style='display:none'>$kw</span>
			<center>";
		echo $options['forumrunner_googleads_javascript'];
		echo "</center>
			</body></html>";
	}
}
