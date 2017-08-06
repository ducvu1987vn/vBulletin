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

define('FILE_VERSION', '5.0.0'); // this should match installsteps.php
if (!defined('SIMPLE_VERSION')) define('SIMPLE_VERSION', '500'); // see vB_Datastore::check_options()
define('YUI_VERSION', '2.7.0'); // define the YUI version we bundle
define('JQUERY_VERSION', '1.7.2'); // define the jQuery version we use

/**#@+
* The maximum sizes for the "small" profile avatars
*/
define('FIXED_SIZE_AVATAR_WIDTH',  60);
define('FIXED_SIZE_AVATAR_HEIGHT', 80);
/**#@-*/

/**#@+
* These make up the bit field to disable specific types of BB codes.
*/
define('ALLOW_BBCODE_BASIC',  1);
define('ALLOW_BBCODE_COLOR',  2);
define('ALLOW_BBCODE_SIZE',   4);
define('ALLOW_BBCODE_FONT',   8);
define('ALLOW_BBCODE_ALIGN',  16);
define('ALLOW_BBCODE_LIST',   32);
define('ALLOW_BBCODE_URL',    64);
define('ALLOW_BBCODE_CODE',   128);
define('ALLOW_BBCODE_PHP',    256);
define('ALLOW_BBCODE_HTML',   512);
define('ALLOW_BBCODE_IMG',    1024);
define('ALLOW_BBCODE_QUOTE',  2048);
define('ALLOW_BBCODE_CUSTOM', 4096);
/**#@-*/

/**#@+
* These make up the bit field to control what "special" BB codes are found in the text.
*/
define('BBCODE_HAS_IMG',    1);
define('BBCODE_HAS_ATTACH', 2);
define('BBCODE_HAS_SIGPIC', 4);
define('BBCODE_HAS_RELPATH',8);
/**#@-*/

/**#@+
* Bitfield values for the inline moderation javascript selector which should be self-explanitory
*/
define('POST_FLAG_INVISIBLE', 1);
define('POST_FLAG_DELETED',   2);
define('POST_FLAG_ATTACH',    4);
define('POST_FLAG_GUEST',     8);
/**#@-*/


/**
* Class to handle and sanitize variables from GET, POST and COOKIE etc
*
* @package	vBulletin
* @version	$Revision: 71754 $
* @date		$Date: 2013-02-15 11:50:11 -0800 (Fri, 15 Feb 2013) $
*/
class vB_Input_Cleaner
{
	/**
	* Translation table for short name to long name
	*
	* @var    array
	*/
	var $shortvars = array(
		'n'     => 'nodeid',
		'f'     => 'forumid',
		't'     => 'threadid',
		'p'     => 'postid',
		'u'     => 'userid',
		'a'     => 'announcementid',
		'c'     => 'calendarid',
		'e'     => 'eventid',
		'q'     => 'query',
		'pp'    => 'perpage',
	);

	/**
	* Translation table for short superglobal name to long superglobal name
	*
	* @var     array
	*/
	var $superglobal_lookup = array(
		'g' => '_GET',
		'p' => '_POST',
		'r' => '_REQUEST',
		'c' => '_COOKIE',
		's' => '_SERVER',
		'e' => '_ENV',
		'f' => '_FILES'
	);

	/**
	* System state. The complete URL of the current page, without sessionhash
	*
	* @var	string
	*/
	var $scriptpath = '';

	/**
	* Reload URL. Complete URL of the current page including sessionhash
	*
	* @var	string
	*/
	var $reloadurl = '';

	/**
	* System state. The complete URL of the page for Who's Online purposes
	*
	* @var	string
	*/
	var $wolpath = '';

	/**
	* System state. The complete URL of the referring page
	*
	* @var	string
	*/
	var $url = '';

	/**
	* System state. The IP address of the current visitor
	*
	* @var	string
	*/
	var $ipaddress = '';

	/**
	* System state. An attempt to find a second IP for the current visitor (proxy etc)
	*
	* @var	string
	*/
	var $alt_ip = '';

	/**
	* A reference to the main registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Keep track of variables that have already been cleaned
	*
	* @var	array
	*/
	var $cleaned_vars = array();

	/**
	* Constructor
	*
	* First, reverses the effects of magic quotes on GPC
	* Second, translates short variable names to long (u --> userid)
	* Third, deals with $_COOKIE[userid] conflicts
	*
	* @param	vB_Registry	The instance of the vB_Registry object
	*/
	function vB_Input_Cleaner(&$registry)
	{
		$this->registry =& $registry;

		if (!is_array($GLOBALS))
		{
			die('<strong>Fatal Error:</strong> Invalid URL.');
		}

		// deal with session bypass situation
		if (!defined('SESSION_BYPASS'))
		{
			define('SESSION_BYPASS', !empty($_REQUEST['bypass']));
		}

		foreach (array('_GET', '_POST') AS $arrayname)
		{
			if (isset($GLOBALS["$arrayname"]['do']))
			{
				$GLOBALS["$arrayname"]['do'] = trim($GLOBALS["$arrayname"]['do']);
			}

			$this->convert_shortvars($GLOBALS["$arrayname"]);
		}

		// fetch url of current page for Who's Online
		if (!defined('SKIP_WOLPATH') OR !SKIP_WOLPATH)
		{
			$registry->wolpath = $this->fetch_wolpath();
			define('WOLPATH', $registry->wolpath);
		}
	}

	/**
	 * Fetches a value from $_SERVER or $_ENV
	 *
	 * @param string $name
	 * @return string
	 */
	function fetch_server_value($name)
	{
		if (isset($_SERVER[$name]) AND $_SERVER[$name])
		{
			return $_SERVER[$name];
		}

		if (isset($_ENV[$name]) AND $_ENV[$name])
		{
			return $_ENV[$name];
		}

		return false;
	}


	/**
	 * Adds a query string to a path, fixing the query characters.
	 *
	 * @param 	string		The path to add the query to
	 * @param 	string		The query string to add to the path
	 *
	 * @return	string		The resulting string
	 */
	function add_query($path, $query = false)
	{
		if (false === $query)
		{
			$query = VB_URL_QUERY;
		}

		if (!$query OR !($query = trim($query, '?&')))
		{
			return $path;
		}

		return $path . '?' . $query;
	}

	/**
	 * Adds a fragment to a path
	 *
	 * @param 	string		The path to add the fragment to
	 * @param 	string		The fragment to add to the path
	 *
	 * @return	string		The resulting string
	 */
	function add_fragment($path, $fragment = false)
	{
		if (!$fragment)
		{
			return $path;
		}

		return $path . '#' . $fragment;
	}

	/**
	* Makes GPC variables safe to use
	*
	* @param	string	Either, g, p, c, r or f (corresponding to get, post, cookie, request and files)
	* @param	array	Array of variable names and types we want to extract from the source array
	*
	* @return	array
	*/
	function clean_array_gpc($source, $variables)
	{
		$sg =& $GLOBALS[$this->superglobal_lookup["$source"]];

		foreach ($variables AS $varname => $vartype)
		{
			// clean a variable only once unless its a different type
			if (!isset($this->cleaned_vars["$varname"]) OR $this->cleaned_vars["$varname"] != $vartype)
			{
				$this->registry->GPC_exists["$varname"] = isset($sg["$varname"]);
				$this->registry->GPC["$varname"] =& $this->registry->cleaner->clean(
					$sg["$varname"],
					$vartype,
					isset($sg["$varname"])
				);
				// All STR type passed from API client should be in UTF-8 encoding and we need to convert it back to vB's current encoding.
				// We also need to do this this for the ajax requests for the mobile style.
				// Checking the forcenoajax flag isn't ideal, but it works and limits the scope of the fix (and the risk).
				if ((defined('VB_API') AND VB_API === true) OR !empty($GLOBALS[$this->superglobal_lookup['r']]['forcenoajax']))
				{
					switch ($vartype) {
						case vB_Cleaner::TYPE_STR:
						case vB_Cleaner::TYPE_NOTRIM:
						case vB_Cleaner::TYPE_NOHTML:
						case vB_Cleaner::TYPE_NOHTMLCOND:
							if (!($charset = vB_Template_Runtime::fetchStyleVar('charset')))
							{
								$charset = $this->registry->userinfo['lang_charset'];
							}

							$lower_charset = strtolower($charset);
							if ($lower_charset != 'utf-8')
							{
								if ($lower_charset == 'iso-8859-1')
								{
									$this->registry->GPC["$varname"] = to_charset(ncrencode($this->registry->GPC["$varname"], true, true), 'utf-8');
								}
								else
								{
									$this->registry->GPC["$varname"] = to_charset($this->registry->GPC["$varname"], 'utf-8');
								}
							}
					}
				}
				$this->cleaned_vars["$varname"] = $vartype;
			}
		}
	}

	/**
	* Makes a single GPC variable safe to use and returns it
	*
	* @param	array	The source array containing the data to be cleaned
	* @param	string	The name of the variable in which we are interested
	* @param	integer	The type of the variable in which we are interested
	*
	* @return	mixed
	*/
	function &clean_gpc($source, $varname, $vartype = vB_Cleaner::TYPE_NOCLEAN)
	{
		// clean a variable only once unless its a different type
		if (!isset($this->cleaned_vars["$varname"]) OR $this->cleaned_vars["$varname"] != $vartype)
		{
			$sg =& $GLOBALS[$this->superglobal_lookup["$source"]];

			$this->registry->GPC_exists["$varname"] = isset($sg["$varname"]);
			$this->registry->GPC["$varname"] =& $this->registry->cleaner->clean(
				$sg["$varname"],
				$vartype,
				isset($sg["$varname"])
			);
			$this->cleaned_vars["$varname"] = $vartype;
		}

		return $this->registry->GPC["$varname"];
	}

	/**
	 * Cleans a query string.
	 * Unicode is decoded, url entities are kept encoded, and slashes are preserved.
	 *
	 * @param string $path
	 * @return string
	 */
	function utf8_clean_path($path, $reencode = true)
	{
		$path = explode('/', $path);
		$path = array_map('urldecode', $path);

		if ($reencode)
		{
			$path = array_map('urlencode_uni', $path);
		}

		$path = implode('/', $path);

		return $path;
	}

	/**
	* Turns $_POST['t'] into $_POST['threadid'] etc.
	*
	* @param	array	The name of the array
	*/
	function convert_shortvars(&$array, $setglobals = true)
	{
		// extract long variable names from short variable names
		foreach ($this->shortvars AS $shortname => $longname)
		{
			if (isset($array["$shortname"]) AND !isset($array["$longname"]))
			{
				$array["$longname"] =& $array["$shortname"];
				if ($setglobals)
				{
					$GLOBALS['_REQUEST']["$longname"] =& $array["$shortname"];
				}
			}
		}
	}

	/**
	* Strips out the s=gobbledygook& rubbish from URLs
	*
	* @param	string	The URL string from which to remove the session stuff
	*
	* @return	string
	*/
	function strip_sessionhash($string)
	{
		$string = preg_replace('/(s|sessionhash)=[a-z0-9]{32}?&?/', '', $string);
		return $string;
	}

	/**
	 * Fetches the 'basepath' variable that can be used as <base>.
	 *
	 * @return string
	 */
	function fetch_basepath($rel_modifier = false)
	{
		if ($this->registry->basepath != '')
		{
			return $this->registry->basepath;
		}

		if ($this->registry->options['bburl_basepath'])
		{
			$basepath = trim($this->registry->options['bburl'], '/\\') . '/';
		}
		else
		{
			$basepath = VB_URL_BASE_PATH;
		}

		return $basepath = $basepath . ($rel_modifier ? $this->registry->cleaner->xssClean($rel_modifier) : '');
	}

	/**
	 * Fetches the path for the current request relative to the basepath.
	 * This is useful for local anchors (<a href="{vb:raw relpath}#post">).
	 *
	 * Substracts any overlap between basepath and path with the following results:
	 *
	 * 		base:		http://www.example.com/forums/
	 * 		path:		/forums/content.php
	 * 		result:		content.php
	 *
	 * 		base:		http://www.example.com/forums/admincp
	 * 		path:		/forums/content/1-Article
	 * 		result:		../content/1-Article
	 *
	 * @return string
	 */
	function fetch_relpath($path = false)
	{
		if (!$path AND (isset($this->registry->relpath) AND $this->registry->relpath != ''))
		{
			return $this->registry->relpath;
		}

		// if no path specified, use the request path
		if (!$path)
		{
			if ($_SERVER['REQUEST_METHOD'] == 'POST' AND isset($_SERVER['HTTP_X_REQUESTED_WITH']) AND
			 $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' AND $_POST['relpath'])
			{
				$relpath = $_POST['relpath'];
				$query = '';
			}
			else
			{
				$relpath = VB_URL_PATH;
				$query = VB_URL_QUERY;
				$fragment = "";
			}
		}
		else
		{
			// if the path is already absolute there's nothing to do
			if (strpos($path, '://'))
			{
				return $path;
			}

			if (!$path)
			{
				return $path;
			}

			$relpath = vB_String::parseUrl($path, PHP_URL_PATH);
			$query = vB_String::parseUrl($path, PHP_URL_QUERY);
			$fragment = vB_String::parseUrl($path, PHP_URL_FRAGMENT);
		}

		$relpath = ltrim($relpath, '/');
		$basepath = @vB_String::parseUrl($this->fetch_basepath(), PHP_URL_PATH);
		$basepath = trim($basepath, '/');

		// get path segments for comparison
		$relpath = explode('/', $relpath);
		$basepath = explode('/', $basepath);

		// remove segments that basepath and relpath share
		foreach ($basepath AS $segment)
		{
			if ($segment == current($relpath))
			{
				array_shift($basepath);
				array_shift($relpath);
			}
			else
			{
				break;
			}
		}

		// rebuild the relpath
		$relpath = implode('/', $relpath);

		// add the query string if the current path is being used
		if ($query)
		{
			$relpath = $this->add_query($relpath, $query);
		}

		// add the fragment back
		if ($fragment)
		{
			$relpath = $this->add_fragment($relpath, $fragment);
		}

		return $relpath;
	}


	/**
	* Fetches the 'wolpath' variable - ie: the same as 'scriptpath' but with a handler for the POST request method
	*
	* @return	string
	*/
	function fetch_wolpath()
	{
		$wolpath = vB::getRequest()->getScriptPath();

		if (!empty($_SERVER['REQUEST_METHOD']) AND ($_SERVER['REQUEST_METHOD'] == 'POST'))
		{
			// Tag the variables back on to the filename if we are coming from POST so that WOL can access them.
			$tackon = '';

			if (is_array($_POST))
			{
				foreach ($_POST AS $varname => $value)
				{
					switch ($varname)
					{
						case 'forumid':
						case 'threadid':
						case 'postid':
						case 'userid':
						case 'eventid':
						case 'calendarid':
						case 'do':
						case 'method': // postings.php
						case 'dowhat': // private.php
						{
							$tackon .= ($tackon == '' ? '' : '&amp;') . $varname . '=' . $value;
							break;
						}
					}
				}
			}
			if ($tackon != '')
			{
				$wolpath .= (strpos($wolpath, '?') !== false ? '&amp;' : '?') . "$tackon";
			}
		}

		return $wolpath;
	}

	/**
	* Fetches the 'url' variable - usually the URL of the previous page in the history
	*
	* @return	string
	*/
	function fetch_url()
	{
		$scriptpath = vB::getRequest()->getScriptPath();

		//note regarding the default url if not set or inappropriate.
		//started out as index.php then moved to options['forumhome'] . '.php' when that option was added.
		//now we've changed to to the forumhome url since there is now quite a bit of logic around that.
		//Its not clear, however, with the expansion of vb if that's the most appropriate generic landing
		//place (perhaps it *should* be index.php).
		//In any case there are several places in the code that check for the default page url and change it
		//to something more appropriate.  If the default url changes, so do those checks.
		//The solution is, most likely, to make some note when vbulletin->url is the default so it can be overridden
		//without worrying about what the exact text is.
		if (empty($_REQUEST['url']))
		{
			$url = (!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
		}
		else
		{
			$temp_url = $_REQUEST['url'];
			if (!empty($_SERVER['HTTP_REFERER']) AND $temp_url == $_SERVER['HTTP_REFERER'])
			{
				//$url = 'index.php';
				$url = vB5_Route::buildUrl('home|nosession|fullurl');
			}
			else
			{
				$url = $temp_url;
			}
		}

		if ($url == $scriptpath OR empty($url))
		{
			//$url = 'index.php';
			$url = vB5_Route::buildUrl('home|nosession|fullurl');
		}

		$url = $this->registry->cleaner->xssClean($url);
		return $url;
	}

	/**
	* Fetches the IP address of the current visitor
	*
	* @return	string
	*/
	function fetch_ip()
	{
		return $_SERVER['REMOTE_ADDR'];
	}

	/**
	* Fetches an alternate IP address of the current visitor, attempting to detect proxies etc.
	*
	* @return	string
	*/
	function fetch_alt_ip()
	{
		$alt_ip = $_SERVER['REMOTE_ADDR'];

		if (isset($_SERVER['HTTP_CLIENT_IP']))
		{
			$alt_ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) AND preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches))
		{
			// try to avoid using an internal IP address, its probably a proxy
			$ranges = array(
				'10.0.0.0/8' => array(ip2long('10.0.0.0'), ip2long('10.255.255.255')),
				'127.0.0.0/8' => array(ip2long('127.0.0.0'), ip2long('127.255.255.255')),
				'169.254.0.0/16' => array(ip2long('169.254.0.0'), ip2long('169.254.255.255')),
				'172.16.0.0/12' => array(ip2long('172.16.0.0'), ip2long('172.31.255.255')),
				'192.168.0.0/16' => array(ip2long('192.168.0.0'), ip2long('192.168.255.255')),
			);
			foreach ($matches[0] AS $ip)
			{
				$ip_long = ip2long($ip);
				if ($ip_long === false)
				{
					continue;
				}

				$private_ip = false;
				foreach ($ranges AS $range)
				{
					if ($ip_long >= $range[0] AND $ip_long <= $range[1])
					{
						$private_ip = true;
						break;
					}
				}

				if (!$private_ip)
				{
					$alt_ip = $ip;
					break;
				}
			}
		}
		else if (isset($_SERVER['HTTP_FROM']))
		{
			$alt_ip = $_SERVER['HTTP_FROM'];
		}

		return $alt_ip;
	}
}

// #############################################################################
// data registry class

/**
* Class to store commonly-used variables
*
* @package	vBulletin
* @version	$Revision: 71754 $
* @date		$Date: 2013-02-15 11:50:11 -0800 (Fri, 15 Feb 2013) $
*/
class vB_Registry
{
	// general objects
	/**
	* Datastore object.
	*
	* @var	vB_Datastore
	*/
	var $datastore;

	/**
	* Input cleaner object.
	*
	* @var	vB_Input_Cleaner
	*/
	var $input;

	/**
	* Database object.
	*
	* @var	vB_Database
	*/
	var $db;

	// user/session related
	/**
	* Array of info about the current browsing user. In the case of a registered
	* user, this will be results of fetch_userinfo(). A guest will have slightly
	* different entries.
	*
	* @var	array
	*/
	var $userinfo;

	/**
	* Session object.
	*
	* @var vB_Session
	*/
	var $session;

	/**
	* Array of do actions that are exempt from checks
	*
	* @var array
	*/
	var $csrf_skip_list = array();

	// configuration
	/**
	* Array of data from config.php.
	*
	* @var	array
	*/
	var $config;

	// GPC input
	/**
	* Array of data that has been cleaned by the input cleaner.
	*
	* @var	array
	*/
	var $GPC = array();

	/**
	* Array of booleans. When cleaning a variable, you often lose the ability
	* to determine if it was specified in the user's input. Entries in this
	* array are true if the variable existed before cleaning.
	*
	* @var	array
	*/
	var $GPC_exists = array();

	/**
	* The size of the super global arrays.
	*
	* @var	array
	*/
	var $superglobal_size = array();

	// single variables
	/**
	* IP Address of the current browsing user.
	*
	* @var	string
	*/
	var $ipaddress;

	/**
	* Alternate IP for the browsing user. This attempts to use various HTTP headers
	* to find the real IP of a user that may be behind a proxy.
	*
	* @var	string
	*/
	var $alt_ip;

	/**
	* The URL of the currently browsed page.
	*
	* @var	string
	*/
	var $scriptpath;

	/**
	 * The request basepath.
	 * Use for <base>
	 *
	 * @var string
	 */
	var $basepath;

	/**
	* Similar to the URL of the current page, but expands some items and includes
	* data submitted via POST. Used for Who's Online purposes.
	*
	* @var	string
	*/
	var $wolpath;

	/**
	* The URL of the current page, without anything after the '?'.
	*
	* @var	string
	*/
	var $script;

	/**
	* Generally the URL of the referring page if there is one, though it is often
	* set in various places of the code. Used to determine the page to redirect
	* to, if necessary.
	*
	* @var	string
	*/
	var $url;

	// usergroup permission bitfields
	/**#@+
	* Bitfield arrays for usergroup permissions.
	*
	* @var	array
	*/
	var $bf_ugp;
	// $bf_ugp_x is a reference to $bf_ugp['x']
	var $bf_ugp_adminpermissions;
	var $bf_ugp_calendarpermissions;
	var $bf_ugp_forumpermissions;
	var $bf_ugp_genericoptions;
	var $bf_ugp_genericpermissions;
	var $bf_ugp_pmpermissions;
	var $bf_ugp_wolpermissions;
	var $bf_ugp_visitormessagepermissions;
	/**#@-*/

	// misc bitfield arrays
	/**#@+
	* Bitfield arrays for miscellaneous permissions and options.
	*
	* @var	array
	*/
	var $bf_misc;
	// $bf_misc_x is a reference to $bf_misc['x']
	var $bf_misc_calmoderatorpermissions;
	var $bf_misc_forumoptions;
	var $bf_misc_intperms;
	var $bf_misc_languageoptions;
	var $bf_misc_moderatorpermissions;
	var $bf_misc_useroptions;
	var $bf_misc_hvcheck;
	/**#@-*/

	/**#@+
	* Results for specific entries in the datastore.
	*
	* @var	mixed	Mixed, though mostly arrays.
	*/
	var $options = null;
	var $attachmentcache = null;
	var $avatarcache = null;
	var $birthdaycache = null;
	var $eventcache = null;
	var $forumcache = null;
	var $iconcache = null;
	var $markupcache = null;
	var $stylecache = null;
	var $languagecache = null;
	var $smiliecache = null;
	var $usergroupcache = null;
	var $bbcodecache = null;
	var $socialsitecache = null;
	var $cron = null;
	var $mailqueue = null;
	var $banemail = null;
	var $maxloggedin = null;
	var $products = null;
	var $ranks = null;
	var $statement = null;
	var $userstats = null;
	var $wol_spiders = null;
	var $loadcache = null;
	var $noticecache = null;
	var $prefixcache = null;
	/**#@-*/

	/**#@+
	* Miscellaneous variables
	*
	* @var	mixed
	*/
	var $bbcode_style = array('code' => -1, 'html' => -1, 'php' => -1, 'quote' => -1);
	var $templatecache = array();
	var $iforumcache = array();
	var $versionnumber;
	var $nozip;
	var $debug;
	var $noheader;
	public $stylevars;

	/**
	 * Shutdown handler
	 *
	 * @var vB_Shutdown
	 */
	var $shutdown;
	/**#@-*/

	/**
	* For storing global information specific to the CMS
	*
	* @var	array
	*/
	var $vbcms = array();


	/**
	* For storing information of the API Client
	*
	* @var	array
	*/
	var $apiclient = array();

	var $cleaner = null;

	/**
	* Constructor - initializes the nozip system,
	* and calls and instance of the vB_Input_Cleaner class
	*/
	function vB_Registry()
	{
		// variable to allow bypassing of gzip compression
		$this->nozip = defined('NOZIP') ? true : (@ini_get('zlib.output_compression') ? true : false);
		// variable that controls HTTP header output
		$this->noheader = defined('NOHEADER') ? true : false;

		@ini_set('zend.ze1_compatibility_mode', 0);

		// initialize the input handler
		$this->cleaner =& vB::getCleaner();
		$this->input = new vB_Input_Cleaner($this);

		// initialize the shutdown handler
		$this->shutdown = vB_Shutdown::instance();

		$this->config =& vB::getConfig();

		$this->csrf_skip_list = (defined('CSRF_SKIP_LIST') ? explode(',', CSRF_SKIP_LIST) : array());
	}

	/**
	*	Check if a user has a specific permission
	*
	*	This is intended to replace direct acces to the userinfo['permissions'] array.
	*
	* For example:
	* $vbulletin->check_user_permission('genericpermissions', 'cancreatetag')
	*
	* which replaces
  * ($vbulletin->userinfo['permissions']['genericpermissions'] &
	*  $vbulletin->bf_ugp_genericpermissions['cancreatetag'])
	*
	*	@param string $group the permission group to check
	* @param string $permission the permission to check within the group
	* @return bool If the user has the requested permission
	*/
	public function check_user_permission($group, $permission)
	{
		return (bool) ($this->userinfo['permissions'][$group] &
			$this->{'bf_ugp_' . $group}[$permission]);
	}
}

/**
* This class implements variable-registration-based template evaluation,
* wrapped around the legacy template format. It will be extended in the
* future to support the new format/syntax without requiring changes to
* code written with it.
*
* Currently these vars are automatically registered: $vbphrase
*    $show, $bbuserinfo, $session, $vboptions
*
* @package	vBulletin
*/
class vB_Template
{
	/**
	 * Preregistered variables.
	 * Variables can be preregistered before a template is created and will be
	 * imported and reset when the template is created.
	 * The array should be in the form array(template_name => array(key => variable))
	 *
	 * @var array mixed
	 */
	protected static $pre_registered = array();

	/**
	* Name of the template to render
	*
	* @var	string
	*/
	protected $template = '';

	/**
	 * Array of registered variables.
	 * @see vB_Template::preRegister()
	*
	* @var	array
	*/
	protected $registered = array();

	/**
	 * Whether the globally accessible vars have been registered.
	 *
	 * @var bool
	 */
	protected $registered_globals;

	/**
	* Debug helper to count how many times a template was used on a page.
	*
	* @var	array
	*/
	public static $template_usage = array();

	/**
	* Debug helper to list the templates that were fetched out of the database (not cached properly).
	*
	* @var	array
	*/
	public static $template_queries = array();

	/**
	 * Factory method to create the template object.
	 * Will choose the correct template type based on the request. Any preregistered
	 * variables are also registered and cleared from the preregister cache.
	*
	* @param	string	Name of the template to be evaluated
	* @return	vB_Template	Template object
	*/
	public static function create($template_name, $forcenoapi = false)
	{
		static $output_type;

		if (defined('VB_API') AND VB_API AND !$forcenoapi)
		{
			// TODO: Use an option to enable/disable the api output

			if (!isset($output_type))
			{
				$output_type = 'json';
			}

			if ($output_type == 'xml')
			{
				$template = new vB_Template_XML($template_name);
			}
			else
			{
				$template = new vB_Template_JSON($template_name);
			}

			if (!VB_API_CMS)
			{
				global $show;
				$copyofshow = $show;
				self::remove_common_show($copyofshow);
				$template->register('show', $copyofshow);
			}
		}
		else
		{
			$template = new vB_Template($template_name);
		}

		if (isset(self::$pre_registered[$template_name]))
		{
			$template->quickRegister(self::$pre_registered[$template_name]);
			// TODO: Reinstate once search uses a single template object
			// unset(self::$pre_registered[$template_name]);
		}

		return $template;
	}

	/**
	 * Unset common items in $show array for API
	 */
	protected static function remove_common_show(&$show)
	{
		// Unset common show variables
		unset(
			$show['old_explorer'], $show['rtl'], $show['admincplink'], $show['modcplink'],
			$show['registerbutton'], $show['searchbuttons'], $show['quicksearch'],
			$show['memberslist'], $show['guest'], $show['member'], $show['popups'],
			$show['nojs_link'], $show['pmwarning'], $show['pmstats'], $show['pmmainlink'],
			$show['pmtracklink'], $show['pmsendlink'], $show['siglink'], $show['avatarlink'],
			$show['detailedtime'], $show['profilepiclink'], $show['wollink'], $show['spacer'],
			$show['dst_correction'], $show['contactus'], $show['nopasswordempty'],
			$show['quick_links_groups'], $show['quick_links_albums'], $show['friends_and_contacts'],
			$show['communitylink'], $show['search_engine'], $show['editor_css']
		);
	}

	/**
	 * Protected constructor to enforce the factory pattern.
	 * Ensures the chrome templates have been processed.
	*/
	protected function __construct($template_name)
	{
		$this->template = $template_name;
	}

	/**
	* Returns the name of the template that will be rendered.
	*
	* @return	string
	*/
	public function get_template_name()
	{
		return $this->template;
	}

	/**
	 * Preregisters variables before template instantiation.
	 *
	 * @param	string	The name of the template to register for
	 * @param	array	The variables to register
	 */
	public static function preRegister($template_name, array $variables = NULL)
	{
		if ($variables)
		{
			if (!isset(self::$pre_registered[$template_name]))
			{
				self::$pre_registered[$template_name] = array();
			}

			self::$pre_registered[$template_name] = array_merge(self::$pre_registered[$template_name], $variables);
		}
	}

	/**
	* Register a variable with the template.
	*
	* @param	string	Name of the variable to be registered
	* @param	mixed	Value to be registered. This may be a scalar or an array.
	 * @param	bool	Whether to overwrite existing vars
	 * @return	bool	Whether the var was registered
	*/
	public function register($name, $value, $overwrite = true)
	{
		if (!$overwrite AND $this->is_registered($name))
		{
			return false;
		}

		$this->registered[$name] = $value;

		return true;
	}

	/**
	 * Registers an array of variables with the template.
	 *
	 * @param	mixed	Assoc array of name => value to be registered
	 */
	public function quickRegister($values, $overwrite = true)
	{
		if (!is_array($values))
		{
			return;
		}

		foreach ($values AS $name => $value)
		{
			$this->register($name, $value, $overwrite);
		}
	}

	/**
	 * Registers a named global variable with the template.
	 *
	 * @param	string	The global to register
	 * @param	bool	Whether to overwrite on a name collision
	 */
	public function register_global($name, $overwrite = true)
	{
		if (!$overwrite AND $this->is_registered($name))
		{
			return false;
		}

		return isset($GLOBALS[$name]) ? $this->register_ref($name, $GLOBALS[$name]) : false;
	}

	/**
	 * Registers a reference to a variable.
	 *
	 * @param	string	Name of the variable to be registered
	 * @param	mixed	Value to be registered. This may be a scalar or an array
	 * @param	bool	Whether to overwrite existing vars
	 * @return	bool	Whether the var was registered
	 */
	public function register_ref($name, &$value, $overwrite = true)
	{
		if (!$overwrite AND $this->is_registered($name))
		{
			return false;
		}

		$this->registered[$name] =& $value;

		return true;
	}

	/**
	* Unregisters a previously registered variable.
	*
	* @param	string	Name of variable to be unregistered
	* @return	mixed	Null if the variable wasn't registered, otherwise the value of the variable
	*/
	public function unregister($name)
	{
		if (isset($this->registered[$name]))
		{
			$value = $this->registered[$name];
			unset($this->registered[$name]);
			return $value;
		}
		else
		{
			return null;
		}
	}

	/**
	 * Determines if a named variable is registered.
	*
	* @param	string	Name of variable to check
	* @return	bool
	*/
	public function is_registered($name)
	{
		return isset($this->registered[$name]);
	}

	/**
	* Return the value of a registered variable or all registered values
	 * If no variable name is specified then all variables are returned.
	*
	* @param	string	The name of the variable to get the value for.
	* @return	mixed	If a name is specified, the value of the variable or null if it doesn't exist.
	*/
	public function registered($name = '')
	{
		if ($name !== '')
		{
			return (isset($this->registered[$name]) ? $this->registered[$name] : null);
		}
		else
		{
			return $this->registered;
		}
	}

	/**
	* Automatically register the page-level templates footer, header,
	* and headinclude based on their global values.
	*/
	public function register_page_templates()
	{
		// Only method forum requires these templates
		if (defined('VB_API') AND VB_API === true AND VB_ENTRY !== 'forum.php')
		{
			return true;
		}

		$this->register_global('footer');
		$this->register_global('header');
		$this->register_global('headinclude');
		$this->register_global('headinclude_bottom');
	}

	/**
	 * Register globally accessible vars.
	 *
	 * @param bool $final_render				- Whether we are rendering the final response
	*/
	protected function register_globals($final_render = false)
	{
		if ($this->registered_globals)
		{
			return;
		}
		$this->registered_globals = true;

		global $vbulletin, $style;

		$session = vB::getCurrentSession();
		$this->register_ref('bbuserinfo', $session->fetch_userinfo());
		$this->register_ref('vboptions', $vbulletin->options);

		$allvars = $session->getAllVars();
		$this->register_ref('session', $allvars);
		$this->register('relpath', htmlspecialchars($vbulletin->input->fetch_relpath()));

		$this->register_global('vbphrase');
		$this->register_global('vbcollapse');
		$this->register_global('style');

		$this->register_global('show', false);

		$vbcsspath = $this->fetch_css_path();
		$this->register('vbcsspath', $vbcsspath);

		if (isset($vbulletin->products['vbcms']) AND $vbulletin->products['vbcms'])
		{
			$this->register('vb_suite_installed', true);
		}

		// If we're using bgclass, we might be using exec_switch_bg()
		// but we can only be sure if we match the global value.
		// A hack that will hopefully go away.
		if (isset($bgclass) AND $bgclass == $GLOBALS['bgclass'])
		{
			$this->register_ref('bgclass', $GLOBALS['bgclass']);
		}
	}


	/**
	 * Renders the template.
	 *
	 * @param	boolean	Whether to suppress the HTML comment surrounding option (for JS, etc)
	 * @return	string	Rendered version of the template
	 */
	public function render($suppress_html_comments = false, $final_render = false, $nopermissioncheck = false)
	{
		// Register globally accessible data
		$this->register_globals($final_render);

		// Render the output in the appropriate format
		return $this->render_output($suppress_html_comments, $nopermissioncheck);
	}


	/**
	 * Renders the output after preperation.
	 * @see vB_Template::render()
	 *
	 * @param boolean	Whether to suppress the HTML comment surrounding option (for JS, etc)
	 * @return string
	 */
	protected function render_output($suppress_html_comments = false, $nopermissioncheck = false)
	{
		//This global statement is here to expose $vbulletin to the templates.
		//It must remain in the same function as the template eval
		global $vbulletin;
		extract($this->registered, EXTR_SKIP | EXTR_REFS);

		$template_code = vB_Library::instance('Template')->fetch($this->template, vB::getCurrentSession()->get('styleid'), $nopermissioncheck);
		if (strpos($template_code, '$final_rendered') !== false)
		{
			eval($template_code);
		}
		else
		{
			eval('$final_rendered = "' . $template_code . '";');
		}

		if ($vbulletin->options['addtemplatename'] AND !$suppress_html_comments)
		{
			$template_name = preg_replace('#[^a-z0-9_]#i', '', $this->template);
			$final_rendered = "<!-- BEGIN TEMPLATE: $template_name -->\n$final_rendered\n<!-- END TEMPLATE: $template_name -->";
		}

		return $final_rendered;
	}


	/**
	* Returns the CSS path needed for the {vb:cssfile} template tag
	*
	* @return	string	CSS path
	*/
	public function fetch_css_path()
	{
		global $vbulletin, $style, $foruminfo;

		if ($vbulletin->options['storecssasfile'])
		{
			$vbcsspath = 'clientscript/vbulletin_css/style' . str_pad($style['styleid'], 5, '0', STR_PAD_LEFT) . $vbulletin->stylevars['textdirection']['string'][0] . '/';
		}
		else
		{
			// Forum ID added when in forums with style overrides and the "Allow Users To Change Styles"
			// option is off, otherwise the requested styleid will be denied. Not added across the board
			// to ensure the highest cache hit rate possible. Not needed when CSS is stored as files.
			// See bug: VBIV-5647
			if (!empty($foruminfo))
			{
				$forumid = intval($foruminfo['forumid']);
			$forum_styleid = intval($foruminfo['styleid']);
				if (!$vbulletin->options['allowchangestyles'] AND $forumid > 0 AND $forum_styleid > 0)
				{
					$add_forumid = '&amp;forumid=' . $forumid;
				}
			}
			else
			{
				$add_forumid = '';
			}

			// textdirection var added to prevent cache if admin modified language text_direction. See bug #32640
			$vbcsspath = 'css.php?styleid=' . $style['styleid'] . $add_forumid . '&amp;langid=' . LANGUAGEID . '&amp;d=' . $style['dateline'] . '&amp;td=' . $vbulletin->stylevars['textdirection']['string'] . '&amp;sheet=';
		}

		return $vbcsspath;
	}

	/**
	* Returns a single template from the templatecache or the database and returns
	* the raw contents of it. Note that text will be escaped for eval'ing.
	*
	* @param	string	Name of template to be fetched
	*
	* @return	string
	*/
	public static function fetch_template_raw($template_name)
	{
		$template_code = vB_Api::instanceInternal('template')->fetch($template_name);

		if (strpos($template_code, '$final_rendered') !== false)
		{
			return preg_replace('#^\$final_rendered = \'(.*)\';$#s', '\\1', $template_code);
		}
		else
		{
			return $template_code;
		}
	}
}

abstract class vB_Template_Data extends vB_Template
{
	/**
	 * Registered templates and their local vars.
	 * The array should be in the form:
	 * 	array(template_name => array(registered, registered [,...]))
	 *
	 * @var array
	 */
	protected static $registered_templates = array();

	/**
	 * Prefix for the template token.
	 * If this is matched as the prefix of a registered variable then the value is
	 * picked up from $registered_templates.
	 */
	protected static $token_prefix = '_-_-template-_-_';

	/**
	 * Register a variable with the template.
	 * If the variable is prefixed with the template token then it is assumed as a
	 * child template and picked up from $registered_templates.
	 *
	 * @param	string	Name of the variable to be registered
	 * @param	mixed	Value to be registered. This may be a scalar or an array.
	 * @param	bool	Whether to overwrite existing vars
	 * @return	bool	Whether the var was registered
	 */
	public function register($name, $value, $overwrite = true)
	{
		if (!$overwrite AND $this->is_registered($name))
		{
			return false;
		}

		if (defined('VB_API_CMS') AND VB_API_CMS === true)
		{
			$value = $this->escapeView($value);
		}

		// Convert any tokenised templates into the local vars
		$this->parse_token($value);

		$this->registered[$name] = $value;

		return true;
	}


	/**
	 * Identical to register, but registers a value as a reference.
	 *
	 * @param	string	Name of the variable to be registered
	 * @param	mixed	Value to be registered. This may be a scalar or an array.
	 * @param	bool	Whether to overwrite existing vars
	 * @return	bool	Whether the var was registered
	 */
	public function register_ref($name, &$value, $overwrite = true)
	{
		if (!$overwrite AND $this->is_registered($name))
		{
			return false;
		}

		if (defined('VB_API_CMS') AND VB_API_CMS === true)
		{
			$value = $this->escapeView($value);
		}

		// Convert any tokenised templates into the local vars
		$this->parse_token($value);

		$this->registered[$name] = &$value;

		return true;
	}


	/**
	 * Checks if a registered value is a template token.
	 * If it is, the registered vars of the child template are picked up and
	 * assigned to this template.
	 *
	 * @param	string	Name of the variable to be registered
	 * @param	mixed	Value to be registered. This may be a scalar or an array.
	 * @return	bool	Whether the value was picked up as a token, or the resovled value
	 */
	public function parse_token(&$value)
	{
		if (is_array($value))
		{
			array_walk($value, array($this, 'parse_token'));
		}
		else
		{
			$matched = false;
			$matches = array();
			if (is_string($value) AND preg_match_all('#' . preg_quote(self::$token_prefix) . '(.+?):(\d+)#', $value, $matches, PREG_SET_ORDER))
			{
				$old_value = $value;
				$value = array();

				foreach ($matches AS $match)
				{
					$template_name = $match[1];
					$index = intval($match[2]);

					if (isset(self::$registered_templates[$template_name][$index]))
					{
						$value[] = self::$registered_templates[$template_name][$index];
						$matched = true;
					}
				}

				if (sizeof($value) <= 1)
				{
					$value = current($value);
				}
			}
		}

		return $matched;
	}

	protected function whitelist_filter()
	{
		global $VB_API_WHITELIST;

		// errormessage should be always added to the whitelist
		$VB_API_WHITELIST['response']['errormessage'] = '*';
		if (!$VB_API_WHITELIST['show'] AND !is_array($VB_API_WHITELIST['show']))
		{
			$VB_API_WHITELIST['show'] = '*';
		}

		$temp = array();
		$this->whitelist_filter_recur($VB_API_WHITELIST, $temp, $this->registered);
		$this->registered = $temp;

	}

	protected function whitelist_filter_recur($whitelist, &$arr, &$registered)
	{
		foreach ($whitelist as $k => $v)
		{
			if ($k !== '*')
			{
				if (is_numeric($k) AND isset($registered[$v]))
				{
					if (is_array($registered[$v]))
					{
						$this->removeShow($registered[$v]);
					}
					$arr[$v] = $registered[$v];
				}
				elseif (array_key_exists($k, (array)$registered))
				{
					if ($v === '*')
					{
						if (is_array($registered[$v]))
						{
							$this->removeShow($registered[$v]);
						}
						$arr[$k] = $registered[$k];
					}
					elseif (is_array($v))
					{
						$arr[$k] = array();
						$this->whitelist_filter_recur($whitelist[$k], $arr[$k], $registered[$k]);
						if (empty($arr[$k]))
						{
							unset($arr[$k]);
						}
					}
				}
			}
			elseif ($k === '*')
			{
				if (is_array($registered))
				{
					$registeredkeys = array_keys($registered);
					if (is_numeric($registeredkeys[0]))
					{
						foreach ($registered as $k2 => $v2)
						{
							if (is_array($whitelist[$k]) AND !in_array('show', array_keys($whitelist[$k])))
							{
								if (is_array($registered[$k2]))
								{
									$this->removeShow($registered[$k2]);
								}
							}
							$arr[$k2] = array();
							$this->whitelist_filter_recur($whitelist[$k], $arr[$k2], $registered[$k2]);
						}
					}
					else
					{
						if (is_array($whitelist[$k]) AND !in_array('show', array_keys($whitelist[$k])))
						{
							if (is_array($registered))
							{
								$this->removeShow($registered);
							}
						}
						$this->whitelist_filter_recur($whitelist[$k], $arr, $registered);
					}
				}
				else
				{
					$arr = $registered;
					unset($registered);
				}
			}
		}
	}

	protected function removeShow(&$arr)
	{
		if (is_array($arr))
		{
			unset($arr['show']);
			foreach($arr as &$v)
			{
				$this->removeShow($v);
			}
		}
	}

	protected function escapeView($value)
	{
		if (is_array($value))
		{
			foreach ($value AS &$el)
			{
				$el = $this->escapeView($el);
			}
		}

		if ($value instanceof vB_View)
		{
			$value = $value->render();
		}
		else if ($value instanceof vB_Phrase)
		{
			$value = (string)$value;
		}

		return $value;
	}


	/**
	 * Renders the template.
	 *
	 * @param	boolean	Whether to suppress the HTML comment surrounding option (for JS, etc)
	 * @return	string	Rendered version of the template
	 */
	public function render($suppress_html_comments = false, $final = false, $nopermissioncheck = false)
	{
		global $vbulletin, $show;

		$vb5_config =& vB::getConfig();

		$callback = vB_APICallback::instance();

		if ($final)
		{
			self::remove_common_show($show);

			// register whitelisted globals
			$this->register_globals();

			$callback->setname('result_prewhitelist');
			$callback->addParamRef(0, $this->registered);
			$callback->callback();

			if (!($vb5_config['Misc']['debug'] AND $vbulletin->GPC['showall']))
			{
				$this->whitelist_filter();
			}

			$callback->setname('result_overwrite');
			$callback->addParamRef(0, $this->registered);
			$callback->callback();

			if ($vb5_config['Misc']['debug'] AND $vbulletin->GPC['debug'])
			{
				return '<pre>'.htmlspecialchars(var_export($this->registered, true)).'</pre>' . '<br />' . number_format((memory_get_usage() / 1024)) . 'KB';
			}
			else
			{
				// only render data on final render
				return $this->render_output($suppress_html_comments, $nopermissioncheck);
			}
		}
		else
		{
			$callback->setname('result_prerender');
			$callback->addParam(0, $this->template);
			$callback->addParamRef(1, $this->registered);
			$callback->callback();
		}


		return $this->render_token();
	}


	/**
	 * Buffers locally registered vars and returns a token representation of the template.
	 *
	 * @return string
	 */
	protected function render_token()
	{
		if (!isset(self::$registered_templates[$this->template]))
		{
			self::$registered_templates[$this->template] = array();
		}

		// Buffer local vars to be picked up by the parent template
		self::$registered_templates[$this->template][] = $this->registered;

		$index = sizeof(self::$registered_templates[$this->template])-1;

		return self::$token_prefix . $this->template . ':' . $index;
	}


	/**
	 * Renders the output after preperation.
	 * @see vB_Template::render()
	 *
	 * @param boolean	Whether to suppress the HTML comment surrounding option (for JS, etc)
	 * @return string
	 */
	protected function render_output($suppress_html_comments = false, $nopermissioncheck = false)
	{
		return false;
	}

	public static function dump_templates()
	{
		return print_r(self::$registered_templates,1);
	}
}


class vB_Template_XML extends vB_Template_Data
{
	/**
	 * Renders the output after preperation.
	 * @see vB_Template::render()
	 *
	 * @param boolean	Whether to suppress the HTML comment surrounding option (for JS, etc)
	 * @return string
	 */
	protected function render_output($suppress_html_comments = false, $nopermissioncheck = false)
	{
		return xmlrpc_encode($this->registered);
	}
}

class vB_Template_JSON extends vB_Template_Data
{
	/**
	 * Renders the output after preperation.
	 * @see vB_Template::render()
	 *
	 * @param boolean	Whether to suppress the HTML comment surrounding option (for JS, etc)
	 * @return string
	 */
	protected function render_output($suppress_html_comments = false, $nopermissioncheck = false)
	{
		if (!($charset = vB_Template_Runtime::fetchStyleVar('charset')))
		{
			global $vbulletin;
			$charset = $vbulletin->userinfo['lang_charset'];
		}

		$lower_charset = strtolower($charset);
		if ($lower_charset != 'utf-8')
		{
			// Browsers tend to interpret character set iso-8859-1 as windows-1252
			if ($lower_charset == 'iso-8859-1')
			{
				$lower_charset = 'windows-1252';
			}
			$this->processregistered($this->registered, $lower_charset);
		}

		return json_encode($this->registered);
	}

	private function processregistered(&$value, $charset)
	{
		global $VB_API_REQUESTS;

		if (is_array($value))
		{
			foreach ($value AS &$el)
			{
				$this->processregistered($el, $charset);
			}
		}

		if (is_string($value))
		{
			$value = unhtmlspecialchars(to_utf8($value, $charset, true), true);
			$trimmed = trim($value);
			if ($VB_API_REQUESTS['api_version'] > 1 AND ($trimmed == 'checked="checked"' OR $trimmed == 'selected="selected"'))
			{
				$value = 1;
			}
		}

		if ($VB_API_REQUESTS['api_version'] > 1 AND is_bool($value))
		{
			if ($value)
			{
				$value = 1;
			}
			else
			{
				$value = 0;
			}
		}
	}
}


// #############################################################################
// misc functions

// #############################################################################
/**
* Feeds database connection errors into the halt() method of the vB_Database class.
*
* @param	integer	Error number
* @param	string	PHP error text string
* @param	strig	File that contained the error
* @param	integer	Line in the file that contained the error
*/
function catch_db_error($errno, $errstr, $errfile, $errline)
{
	global $db;
	static $failures;

	if (strstr($errstr, 'Lost connection') AND $failures < 5)
	{
		$failures++;
		return;
	}

	if (is_object($db))
	{
		$db->halt("$errstr\r\n$errfile on line $errline");
	}
	else
	{
		vb_error_handler($errno, $errstr, $errfile, $errline);
	}
}

// #############################################################################
/**
* Removes the full path from being disclosed on any errors
*
* @param	integer	Error number
* @param	string	PHP error text string
* @param	strig	File that contained the error
* @param	integer	Line in the file that contained the error
*/
function vb_error_handler($errno, $errstr, $errfile, $errline)
{
	global $vbulletin;

	switch ($errno)
	{
		case E_WARNING:
		case E_USER_WARNING:
			/* Don't log warnings due to to the false bug reports about valid warnings that we suppress, but still appear in the log
			require_once(DIR . '/includes/functions_log_error.php');
			$message = "Warning: $errstr in $errfile on line $errline";
			log_vbulletin_error($message, 'php');
			*/

			if (!error_reporting() OR !ini_get('display_errors'))
			{
				return;
			}
			$errfile = str_replace(DIR, '[path]', $errfile);
			$errstr = str_replace(DIR, '[path]', $errstr);
			echo "<br /><strong>Warning</strong>: $errstr in <strong>$errfile</strong> on line <strong>$errline</strong><br />";
		break;

		case E_USER_ERROR:
			require_once(DIR . '/includes/functions_log_error.php');
			$message = "Fatal error: $errstr in $errfile on line $errline";
			log_vbulletin_error($message, 'php');

			if (!headers_sent())
			{
				if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
				{
					header('Status: 500 Internal Server Error');
				}
				else
				{
					header('HTTP/1.1 500 Internal Server Error');
				}
			}

			if (error_reporting() OR ini_get('display_errors'))
			{
				$errfile = str_replace(DIR, '[path]', $errfile);
				$errstr = str_replace(DIR, '[path]', $errstr);
				echo "<br /><strong>Fatal error:</strong> $errstr in <strong>$errfile</strong> on line <strong>$errline</strong><br />";
				if (function_exists('debug_print_backtrace') AND ($vbulletin->userinfo['usergroupid'] == 6 OR ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions)))
				{
					// This is needed so IE doesn't show the pretty error messages
					echo str_repeat(' ', 512);
					debug_print_backtrace();
				}
			}
			exit;
		break;
	}
}

// #############################################################################
// TODO: replace with vB_String::htmlSpecialCharsUni
/**
* Unicode-safe version of htmlspecialchars()
*
* @param	string	Text to be made html-safe
*
* @return	string
*/
function htmlspecialchars_uni($text, $entities = true)
{
	if ($entities)
	{
		$text = preg_replace_callback(
			'/&((#([0-9]+)|[a-z]+);)?/si',
			'htmlspecialchars_uni_callback',
			$text
		);
	}
	else
	{
		$text = preg_replace(
			// translates all non-unicode entities
			'/&(?!(#[0-9]+|[a-z]+);)/si',
			'&amp;',
			$text
		);
	}

	return str_replace(
		// replace special html characters
		array('<', '>', '"'),
		array('&lt;', '&gt;', '&quot;'),
			$text
	);
}

function htmlspecialchars_uni_callback($matches)
{
 	if (count($matches) == 1)
 	{
 		return '&amp;';
 	}

	if (strpos($matches[2], '#') === false)
	{
		// &gt; like
		if ($matches[2] == 'shy')
		{
			return '&shy;';
		}
		else
		{
			return "&amp;$matches[2];";
		}
	}
	else
	{
		// Only convert chars that are in ISO-8859-1
		if (($matches[3] >= 32 AND $matches[3] <= 126)
			OR
			($matches[3] >= 160 AND $matches[3] <= 255))
		{
			return "&amp;#$matches[3];";
		}
		else
		{
			return "&#$matches[3];";
		}
	}
}


function css_escape_string($string)
{
	static $map = null;
	//url(<something>) is valid.

	$checkstr = strtolower(trim($string));
	$add_url = false;
	if ((substr($checkstr, 0, 4) == 'url(') AND (substr($checkstr,-1,1) == ')'))
	{
		//we need to leave the "url()" part alone.
		$add_url = true;
		$string = trim($string);
		$string = substr($string,4, strlen($string)- 5);
		if ((($string[0] == '"') AND (substr($checkstr,-1,1) == '"'))
			OR
			(($string[0] == "'") AND (substr($checkstr,-1,1) == "'")))
		{
			$string = substr($string,1, strlen($string)- 2);
		}
	}

	if(is_null($map))
	{
		$chars = array(
			'\\', '!', '@', '#', '$', '%', '^',  '*', '"', "'",
			'<', '>', ',', '`', '~','/','&', '.',':', ')','(', ';'
		);

		foreach ($chars as $char)
		{
			$map[$char] = '\\' . dechex(ord($char)) . ' ';
		}
	}

	$string = str_replace(array_keys($map), $map, $string);

	//add back the url() if we need it.
	if ($add_url)
	{
		$string = 'url(\'' . $string . '\')';
	}
	return $string;
}
/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 71754 $
|| ####################################################################
\*======================================================================*/
