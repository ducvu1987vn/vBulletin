<?php //if (!defined('VB_ENTRY')) die('Access denied.');
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
 * The vB core class.
 * Everything required at the core level should be accessible through this.
 *
 * The core class performs initialisation for error handling, exception handling,
 * application instatiation and optionally debug handling.
 *
 * @TODO: Much of what goes on in global.php and init.php will be handled, or at
 * least called here during the initialisation process.  This will be moved over as
 * global.php is refactored.
 *
 * @package vBulletin
 * @version $Revision: 28823 $
 * @since $Date: 2008-12-16 17:43:04 +0000 (Tue, 16 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB
{
	/*Properties====================================================================*/

	/**
	 * Whether the framework has been initialized.
	 *
	 * @var bool
	 */
	private static $initialized;

	/**
	 * Array of classname => filename for autoloading.
	 * This is purely for performance reasons and is optional.  Each entry should
	 * specify the class name and the absolute path to the file to include.
	 *
	 * It is also useful for any classes residing in files that do not meet the
	 * class name => file location pattern that is resolved by the autoloader.
	 *
	 * @var array classname => filename
	 */
	private static $load_map = array();

	/**
	 * Whether content headers have been sent.
	 * This only tracks the Content-Type and Content-Length headers.  When a view or
	 * other system sends content headers, it should let vB know with
	 * vB::contentHeadersSent(true).
	 *
	 * @var bool
	 */
	private static $content_headers_sent = false;


	/**
	 * Location of the config file relative to the site root -- mostly here to overload for testing
	 * @var string
	 */
	private static $config_file = "includes/config.php";

	/**
	 *
	 * @var array
	 */
	private static $config = null;

	/**
	 *
	 * @var vB_dB_Assertor
	 */
	private static $db_assertor = null;

	/**
	 *
	 * @var vB_Datastore
	 */
	private static $datastore = null;

	private static $usercontexts = array();

	/**
	 *
	 * @var vB_Session
	 */
	private static $currentSession = null;

	/**
	 *
	 * @var vB_Request
	 */
	private static $request = null;

	/**
	 *
	 * @var vB_Cleaner
	 */
	private static $cleaner = null;

	/*Initialisation================================================================*/

	/**
	 * Initializes the vB framework.
	 * All framework level objects and services are created so that they are available
	 * throughout the application.  This is only done once per actual request.
	 *
	 * Note: If the framework is used this way then there are several limitations.
	 *  - If no vB_Bootstrap was created (ie, the admincp), then you cannot render any
	 *    views created by the framework.
	 *  - VB_ENTRY must be defined to a valid request script that runs the framework
	 *    with vB::Main()
	 *  - If you are rendering views, try to create all of the views that will be
	 *    used before rendering any of them.  This optimises template and phrase
	 *    fetching.
	 */
	public static function init($relative_path = false)
	{
		if (self::$initialized)
		{
			return;
		}

/**
 *	Set a bunch of constants.
 */

		//We were getting CWD defined as something like '<install location>/vb5/..'
		//This causes problems when uploading a default avatar, where we need to parse the path
		//. It's much easier to decode if the path is just <install location>
		if (!defined('CWD'))
		{
			$cwd = dirname(__FILE__);
			if (($pos = strrpos($cwd, DIRECTORY_SEPARATOR)) === false)
			{
				//we can't figure this out.
				define('CWD', dirname(__FILE__) . DIRECTORY_SEPARATOR . '..');
			}
			else
			{
				define('CWD', substr($cwd, 0, $pos) );
			}
		}

		if (!defined('DIR'))
		{
			define('DIR', CWD);
		}

		if (!defined('VB_PATH'))
		{
			define('VB_PATH', DIR . '/vb/');
		}

		if (!defined('VB5_PATH'))
		{
			define('VB5_PATH', DIR . '/vb5/');
		}

		if (!defined('VB_PKG_PATH'))
		{
			define('VB_PKG_PATH', realpath(VB_PATH . '../packages') . '/');
		}

		if (!defined('VB_ENTRY'))
		{
			define('VB_ENTRY', 1);
		}

		if (!defined('SIMPLE_VERSION'))
		{
			define('SIMPLE_VERSION', '500');
		}

/***
 *	Clean up the php environment
 */
		if (isset($_REQUEST['GLOBALS']) OR isset($_FILES['GLOBALS']))
		{
			echo 'Request tainting attempted.';
			exit;
		}

		@ini_set('pcre.backtrack_limit', -1);

		/* The min requirement for vB5 is 5.3.0,
		   so version checking here isnt necessary */
		@date_default_timezone_set(date_default_timezone_get());

		// Disabling magic quotes at runtime
		// Code copied from PHP Manual: http://www.php.net/manual/en/security.magicquotes.disabling.php
		if (get_magic_quotes_gpc())
		{
			$process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);

			while (list($key, $val) = each($process))
			{
				foreach ($val as $k => $v)
				{
					unset($process[$key][$k]);

					if (is_array($v))
					{
						$process[$key][stripslashes($k)] = $v;
						$process[] = &$process[$key][stripslashes($k)];
					}
					else
					{
						$process[$key][stripslashes($k)] = stripslashes($v);
					}
				}
			}

			unset($process);
		}

		// overwrite GET[x] and REQUEST[x] with POST[x] if it exists (overrides server's GPC order preference)
		if (isset($_SERVER['REQUEST_METHOD']) AND $_SERVER['REQUEST_METHOD'] == 'POST')
		{
			foreach (array_keys($_POST) AS $key)
			{
				if (isset($_GET["$key"]))
				{
					$_GET["$key"] = $_REQUEST["$key"] = $_POST["$key"];
				}
			}
		}

		// deal with cookies that may conflict with _GET and _POST data, and create our own _REQUEST with no _COOKIE input
		foreach (array_keys($_COOKIE) AS $varname)
		{
			unset($_REQUEST["$varname"]);
			if (isset($_POST["$varname"]))
			{
				$_REQUEST["$varname"] =& $_POST["$varname"];
			}
			else if (isset($_GET["$varname"]))
			{
				$_REQUEST["$varname"] =& $_GET["$varname"];
			}
		}

/***
 *	Register Callback functions
 */

		//we want to have an exception handler defined (the default handler has bad habit of
		//leaking information that shouldn't be leaked), but we don't want to override any frontend handler,
		//that might be set.  PHP doesn't allow us to check the current handler, but we can change it and
		//then change it back if we don't like the result.
		if (null !== set_exception_handler(array('vB', 'handleException')))
		{
			restore_exception_handler();
		}

		// Set unserializer to use spl registered autoloader
		ini_set('unserialize_callback_func', 'spl_autoload_call');

		// Set class autoloader
		spl_autoload_register(array('vB', 'autoload'));

		// Set shutdown function
		register_shutdown_function(array('vB', 'shutdown'));

		// Done
		self::$initialized = true;
	}



	/*Response======================================================================*/

	/**
	 * Gets or sets whether content headers have been sent.
	 *
	 * @param bool $sent						- If true, the headers have been sent
	 */
	public function contentHeadersSent($sent = false)
	{
		if ($sent)
		{
			self::$content_headers_sent = true;
		}

		return self::$content_headers_sent;
	}



	/*Autoload======================================================================*/

	/**
	 * Autloads a class file when required.
	 * Classnames are broken down into segments which are used to resolve the class
	 * directory and filename.
	 *
	 * If the first segment matches 'vB' then it is in /vB else it is in
	 * /packages/segment/
	 *
	 * An optional load map of classname => filename can be passed to skip the path
	 * resolution.
	 *
	 * @TODO: Investigate performance of file_exists and where $check_file could be
	 * used, defaulting to false.
	 *
	 * @param string $classname					- The name of the class to load
	 * @param array $load_map					- Assoc array of classname => filename for quick load
	 * @param bool $check_file					- Whether to check if the file exists and return false
	 * @return bool								- Success
	 */
	public static function autoload($classname, $load_map = false, $check_file = true)
	{
		if (!$classname)
		{
			return false;
		}

		$filename = false;
		$fclassname = strtolower($classname);

		if (preg_match('#\W#', $fclassname))
		{
			return false;
		}

		if (isset($load_map[$classname]))
		{
			$filename = $load_map[$classname];
		}
		else if (isset(self::$load_map[$classname]))
		{
			$filename = self::$load_map[$classname];
		}
		else
		{
			$segments = explode('_', $fclassname);

			switch($segments[0])
			{
				case 'vb':
					$vbPath = true;
					$filename = VB_PATH;
					break;
				case 'vb5':
					$vbPath = true;
					$filename = VB5_PATH;
					break;
				default:
					$vbPath = false;
					$filename = VB_PKG_PATH;
					break;
			}

			if (sizeof($segments) > ($vbPath ? 2 : 1))
			{
				$filename .= implode('/', array_slice($segments, ($vbPath ? 1 : 0), -1)) . '/';
			}

			$filename .= array_pop($segments) . '.php';
		}

		// Include the required class file
		if ($filename)
		{
			if ($check_file AND !file_exists($filename))
			{
				return false;
			}

			require($filename);

			if (class_exists($classname, false) OR interface_exists($classname, false))
			{
				return true;
			}
		}

		return false;
	}


	/**
	 * Allows autoloaders to be registered before the vb autoloader.
	 *
	 * @param callback $callback
	 */
	public static function autoloadPreregister($add_callback)
	{
		$registered = spl_autoload_functions();

		foreach ($registered AS $callback)
		{
			spl_autoload_unregister($callback);
		}

		array_unshift($registered, $add_callback);

		foreach ($registered AS $callback)
		{
			spl_autoload_register($callback);
		}
	}


	/**
	 * Allows entries to be added to the load map.
	 * This is especially useful for calling during application initialization.
	 *
	 * @param array class => file $load_map
	 */
	public static function autoloadMap(array $load_map)
	{
		self::$load_map = array_merge($load_map, self::$load_map);
	}

	public static function shutdown()
	{
		vB_Shutdown::instance()->shutdown();
	}

	/**
	 * This is a temporary getter for retrieving the vbulletin object while we still needed
	 * TODO: remove this method
	 *
	 * @global vB_Registry $vbulletin
	 * @return vB_Registry
	 */
	public static function &get_registry()
	{
		global $vbulletin;

		if(!isset($vbulletin))
		{
			//move some initialization of the registry from the legacy bootstrap.
			require_once(DIR . '/includes/class_core.php');
			$vbulletin = new vB_Registry();

			//this is the *only* place we should call getDBConnection!!!
			$vbulletin->db = &vB::getDBAssertor()->getDBConnection();

			// Add AdSense if present
			$vbulletin->adsense_pub_id = 'pub-1234567890123456';
			$vbulletin->adsense_host_id = 'pub-6543210987654321';

			$vbulletin->datastore = vB::getDatastore();

			//force load some values the vbulletin object.  Otherwise init.php can crap out.
			if (!$vbulletin->datastore->registerCount())
			{
				$vbulletin->datastore->getValue('options');
			}
			$vbulletin->datastore->init_registry();

			$request = self::getRequest();
			if ($request)
			{
				$vbulletin->ipaddress = $request->getIpAddress();
				$vbulletin->alt_ip = $request->getAltIp();

				//a bit of a hack, but we only have URL values if this comes in from the web
				//we may need to sort things out better so that we do something with these
				//functions when we don't have a web request, but this is simpler and this
				//is temporary code anyway.
				if ($request instanceof vB_Request_Web)
				{
					$cleaner = self::getCleaner();

					// store a relative path that includes the sessionhash for reloadurl
					$vbulletin->reloadurl = $request->addQuery($cleaner->xssClean($request->getVbUrlPath()), $request->getVbUrlQueryRaw());

					// store the current script
					$vbulletin->script = $_SERVER['SCRIPT_NAME'];

					// store the scriptpath
					$vbulletin->scriptpath = $request->getScriptPath();
				}
			}
		}

		return $vbulletin;
	}


	/**
	 * Returns a by-reference the config object
	 * @return array
	 */
	public static function &getConfig()
	{
		if (!isset(self::$config)) {
			self::fetch_config();
		}

		return self::$config;
	}

	public static function setConfigFile($config_file)
	{
		self::$config_file = $config_file;
	}

	/**
	* Fetches database/system configuration
	* Code extracted from vB_Registry::fetch_config (class_core)
	*/
	private static function fetch_config()
	{
		// Set the default values here.
		$default['Cache']['class'] = vB_Cache::getDefaults();

		// parse the config file
		if (file_exists(CWD . '/' . self::$config_file))
		{
			include(CWD . '/' . self::$config_file);
		}
		else
		{
			die('<br /><br /><strong>Configuration</strong>: includes/config.php does not exist. Please fill out the data in config.php.new and rename it to config.php');
		}

		// TODO: this should be handled with an exception, the backend shouldn't produce output
		if (sizeof($config) == 0)
		{
			// config.php exists, but does not define $config
			die('<br /><br /><strong>Configuration</strong>: includes/config.php exists, but is not in the 3.6+ format. Please convert your config file via the new config.php.new.');
		}

		self::$config = vB_Array::arrayReplaceRecursive($default, $config);
		// if a configuration exists for this exact HTTP host, use it
		if (isset($_SERVER['HTTP_HOST']) AND isset(self::$config["$_SERVER[HTTP_HOST]"]))
		{
			self::$config['MasterServer'] = self::$config["$_SERVER[HTTP_HOST]"];
		}

		// define table and cookie prefix constants
		define('TABLE_PREFIX', trim(self::$config['Database']['tableprefix']));
		define('COOKIE_PREFIX', (empty(self::$config['Misc']['cookieprefix']) ? 'bb' : self::$config['Misc']['cookieprefix']));

		// Set debug mode, always default this to false unless it is explicitly set to true (see VBV-2948).
		self::$config['Misc']['debug'] = ((isset(self::$config['Misc']['debug']) AND self::$config['Misc']['debug'] === true) ? true : false);

		// This will not exist if a pre vB5 config file is still in use. @TODO, change the default when everything can cope with a blank setting.
		if (!isset(self::$config['SpecialUsers']['superadmins']))
		{
			self::$config['SpecialUsers']['superadmins'] = '1'; // Not ideal, but some areas (and the upgrader) choke on a blank setting atm.
		}
	}

	/**
	 * Returns a by-reference the assertor object
	 * @return vB_dB_Assertor
	 */
	public static function &getDbAssertor()
	{
		if (!isset(self::$db_assertor))
		{
			vB_dB_Assertor::init(self::getConfig());
			self::$db_assertor = vB_dB_Assertor::instance();
		}

		return self::$db_assertor;
	}

	/**
	 * Returns a by-reference the config object
	 * @return vB_Datastore
	 */
	public static function &getDatastore()
	{
		if (!isset(self::$datastore)) {
			$vb5_config = self::getConfig();
			$datastore_class = (!empty($vb5_config['Datastore']['class'])) ? $vb5_config['Datastore']['class'] : 'vB_Datastore';
			self::$datastore = new $datastore_class($vb5_config, self::getDbAssertor());
		}

		return self::$datastore;
	}

	/**
	 * Checks if usercontext is set
	 *
	 * @param <type> $userId
	 * @return bool
	 */
	public static function isUserContextSet($userId = null)
	{
		if (!$userId)
		{
			$session = self::getCurrentSession();
			if (empty($session))
			{
				$return = null;
				return $return;
			}
			$userId = $session->get('userid');
		}

		return isset(self::$usercontexts[$userId]) ? true : false;
	}

	/**
	 * Returns a by-reference the usercontext object specified by $userId
	 * If no userId is specified, it uses the current session user
	 *
	 * @param <type> $userId
	 * @return vB_UserContext
	 */
	public static function &getUserContext($userId = null)
	{
		if (!$userId)
		{
			$session = self::getCurrentSession();
			if (empty($session))
			{
				$return = null;
				return $return;
			}
			$userId = $session->get('userid');
		}

		if (!isset(self::$usercontexts[$userId]))
		{
			self::$usercontexts[$userId] = new vB_UserContext($userId, self::getDbAssertor(), self::getDatastore(), self::getConfig());
		}

		return self::$usercontexts[$userId];
	}

	/**
	 *
	 * @param vB_Request $request
	 */
	public static function setRequest(vB_Request &$request)
	{
		self::$request = & $request;
	}

	/**
	 *
	 * @return vB_Request
	 */
	public static function &getRequest()
	{
		return self::$request;
	}

	/**
	 *
	 * @param vB_Session $session
	 */
	public static function setCurrentSession(vB_Session $session)
	{
		if (self::$currentSession !== null)
		{
			//if we are changing to a new user, let's reload the permissions. It may be slower, but it should
			//be safer and shouldn't be that common.
			unset(self::$usercontexts[$session->get('userid')]);
		}

		self::$currentSession = &$session;

		// this should be the ONLY way of setting $vbulletin->session and $vbulletin->userinfo attributes
		// old code may set attributes inside session and userinfo, but as we have references the session object should be updated as well
		$vbulletin = & self::get_registry();
		$vbulletin->session = & $session;
		$vbulletin->userinfo = & $session->fetch_userinfo();
	}

	/**
	 * @return vB_Session
	 */
	public static function &getCurrentSession()
	{
		return self::$currentSession;
	}


	/**
	 *
	 * @return vB_Cleaner 
	 */
	public static function &getCleaner()
	{
		if (!isset(self::$cleaner))
		{
			self::$cleaner = new vB_Cleaner();
		}

		return self::$cleaner;
	}


	/**
	*	Intended for unit tests, this resets the portion of the test needed for
	* testing to avoid cross contamination
	*/
	public static function reset()
	{
		self::$usercontexts = array();
	}

	public static function getLogger($name)
	{
		static $needConfigure = true;

		if ($needConfigure)
		{
			require_once(DIR . '/libraries/log4php/src/main/php/Logger.php');
			Logger::configure(DIR . '/includes/xml/logger.xml');
			$needConfigure = false;
		}

		return Logger::getLogger($name);
	}

	//this is a public function because it needs to be, however it should not be
	//called except as the exception handler.
	public static function handleException($e)
	{
		$config = self::getConfig();
		echo $e->getMessage();

		if ($config['Misc']['debug'])
		{
			echo $e->getTraceAsString();
		}
	}

	/*
		Old Style names. These are all deprecated and should be changed where used.
	*/

	/**
	 *	@deprecated
	 */
	public static function &get_db_assertor()
	{
		return self::getDbAssertor();
	}

	/**
	 *	@deprecated
	 */
	public static function &get_config()
	{
		return self::getConfig();
	}

	/**
	 *	@deprecated
	 */
	public static function &get_cleaner()
	{
		return self::getCleaner();
	}

	/**
	 *	@deprecated
	 */
	public static function &get_datastore()
	{
		return self::getDatastore();
	}



}

/*======================================================================*\
   || ####################################################################
   || # SVN: $Revision: 28823 $
   || ####################################################################
   \*======================================================================*/
