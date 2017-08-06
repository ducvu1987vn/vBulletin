<?php
if (!defined('VB_ENTRY')) die('Access denied.');
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

abstract class vB_Api
{
	/**
	 * We want API subclasses to access the instances only through getters
	 * @var array
	 */
	private static $instancesRaw;
	private static $instances;
	private static $wrappers;
	// configuration

	/**
	 * Indicates whether the API was disabled
	 * @var bool
	 */
	protected $disableWhiteList = array();
	protected $disabled = false;

	/**
	 * Database object.
	 *
	 * @var	vB_Database
	 */
	protected $db;

	protected static function getApiClass($controller, $errorCheck = true)
	{
		if (!$controller)
		{
			//The error originally referred to php 5.2, but the minumum requirement for vB5 is php 5.3
			throw new Exception("The API should be called as vB_Api::instance('Name'), not vB_Api_Name::instance()");
		}
		else
		{
			$c = 'vB_Api_' . ucfirst($controller);

			// Need to bypass this if not internal as calls may be to a custom API extension class.
			if ($errorCheck)
			{
				if (!class_exists($c))
				{
					throw new Exception(sprintf("Can't find class %s", htmlspecialchars($c)));
				}

				if (!is_subclass_of($c, 'vB_Api'))
				{
					throw new Exception(sprintf('Class %s is not a subclass of vB_Api', htmlspecialchars($c)));
				}
			}
		}

		return $c;
	}

	/**
	 *	Wrap the api object with the log wrapper class if needed.
	 */
	private static function wrapLoggerIfNeeded($controller, $api_object)
	{
		//only check the options once
		static $needLog = NULL;
		if (is_null($needLog))
		{
			$config = vB::getConfig();
			$needLog = (!empty($config['Misc']['debuglogging']));
		}

		if ($needLog)
		{
			return new vB_Api_Logwrapper($controller,  $api_object);
		}
		else
		{
			return $api_object;
		}
	}

	/**
	 * Returns an instance of the API object which doesn't handle exceptions
	 * This should only be used in other API objects, not for clients of the API
	 * @param string $controller -- name of the API controller to load
	 * @param bool $refresh_cache -- true if we want to force the cache to update with a new api object
	 *   primarily intended for testing
	 * @return vB_Api
	 */
	public static function instanceInternal($controller, $refresh_cache = false)
	{
		$c = self::getApiClass($controller);

		if (!isset(self::$instances[$c]) OR $refresh_cache)
		{
			if (!isset(self::$instancesRaw[$c]) OR $refresh_cache)
			{
				self::$instancesRaw[$c] = new $c;
			}

			self::$instances[$c] = self::wrapLoggerIfNeeded($controller, self::$instancesRaw[$c]);
		}

		return self::$instances[$c];
	}

	/**
	 * Returns an instance of the API object which translates exceptions to an array
	 * Use this method for API clients.
	 * @param string $controller -- name of the API controller to load
	 * @param bool $refresh_cache -- true if we want to force the cache to update with a new api object
	 *   primarily intended for testing
	 * @return vB_Api
	 */
	public static function instance($controller, $refresh_cache = false)
	{
		$c = self::getApiClass($controller, false);

		if (!isset(self::$wrappers[$c]) OR $refresh_cache)
		{
			if (!isset(self::$instancesRaw[$c]) OR $refresh_cache)
			{
				if (class_exists($c))
				{
					self::$instancesRaw[$c] = new $c;
				}
				else
				{
					self::$instancesRaw[$c] = new vB_Api_Null();
				}
			}

			self::$wrappers[$c] = new vB_Api_Wrapper($controller, self::$instancesRaw[$c]);
			self::$wrappers[$c] = self::wrapLoggerIfNeeded($controller, self::$wrappers[$c]);
		}

		return self::$wrappers[$c];
	}


	/**
	*	Clears all previously loaded API objects.
	*
	* Intended for use in tests where the loading pattern can cause issues
	*	with objects that cache thier own data.
	*
	*/
	public static function clearCache()
	{
		self::$wrappers = array();
		self::$instances = array();
		self::$instancesRaw = array();
		vB_Api_Extensions::resetExtensions();
	}

	/**
	 * Call the given api function by name with a named arguments list.
	 * Used primarily to translate REST requests into API calls.
	 *
	 * @param string $method -- the name of the method to call
	 * @param array $args -- The list of args to call.  This is a name => value map that will
	 *   be matched up to the names of the API method.  Order is not important.  The names are
	 *   case sensitive.
	 *
	 * @return The return of the method or an error if the method doesn't exist, or is
	 *   static, a constructor or destructor, or otherwise shouldn't be callable as
	 *   and API method.  It is also an error if the value of a paramater is not provided
	 *   and that parameter doesn't have a default value.
	 */
	public function callNamed()
	{
		list ($method, $args) = func_get_args();

		if (!is_callable(array($this, $method)))
		{
			// if the method does not exist, an extension might define it
			return;
		}

		$reflection = new ReflectionMethod($this, $method);

		if($reflection->isConstructor() || $reflection->isDestructor() ||
			$reflection->isStatic() || $method == "callNamed"
		)
		{
			//todo return error message
			return;
		}

		$php_args = array();
		foreach($reflection->getParameters() as $param)
		{
			// the param value can be null, so don't use isset
			if(array_key_exists($param->getName(), $args))
			{
				$php_args[] = &$args[$param->getName()];
			}
			else
			{
				if ($param->isDefaultValueAvailable())
				{
					$php_args[] = $param->getDefaultValue();
				}
				else
				{
					throw new Exception('Required argument missing: ' . htmlspecialchars($param->getName()));
					//todo: return error message
					return;
				}
			}
		}

		return $reflection->invokeArgs($this, $php_args);
	}

	/**
	 * Returns vb5 api method name.
	 * May alter request array.
	 * @param string $method -- vb4 method name
	 * @param array $request -- $_REQUEST array for this api request
	 * @return string
     */
    public static function map_vb4_input_to_vb5($method, &$request)
    {
        if(array_key_exists($method, vB_Api::$vb4_input_mappings))
        {
            $mapping = vB_Api::$vb4_input_mappings[$method];
            if(array_key_exists('request_mappings', $mapping))
            {
                $request_mappings = $mapping['request_mappings'];
                foreach($request_mappings as $mapping_from => $mapping_to)
                {
                    if(!empty($request[$mapping_from]))
                    {
                        $request[$mapping_to] = $request[$mapping_from];
                        unset($request[$mapping_from]);
                    }
                }
            }
            if(array_key_exists('method', $mapping))
            {
                return $mapping['method'];
            }
        }
        return vB_Api::default_vb4_to_vb5_method_mapping($method);
    }

    private static $vb4_input_mappings = array(
		'blog.post_comment' => array(
			'method' => 'vb4_blog.post_comment'
		),
		'blog.post_postcomment' => array(
			'method' => 'vb4_blog.post_postcomment'
		),
		'blog.post_updateblog' => array(
			'method' => 'vb4_blog.post_updateblog'
		),
		'blog.post_newblog' => array(
			'method' => 'vb4_blog.post_newblog'
		),
		'blog_list' => array(
			'method' => 'vb4_blog.bloglist'
		),
		'api_init' => array(
			'method' => 'api.init'
		),
		'login_login' => array(
			'method' => 'user.login',
			'request_mappings' => array(
				'vb_login_username' => 'username',
				'vb_login_password' => 'password',
				'vb_login_md5password' => 'md5password',
				'vb_login_md5password_utf' => 'md5passwordutf'
			)
		),
		'login_logout' => array(
			'method' => 'user.logout'
		)
    );

    private static function default_vb4_to_vb5_method_mapping($method)
    {
        $methodsegments = explode("_", $method);
        $methodsegments[0] = "VB4_" . $methodsegments[0];
        if(count($methodsegments) < 2)
        {
            $methodsegments[] = "call";
        }
        elseif(count($methodsegments) > 2)
        {
            // Handle strangeness
        }
        return implode(".", $methodsegments);
    }

    /**
     * Alters the output array in any way necessary to interface correctly
     * with vb4.
	 * @param string $method -- vb4 method name
	 * @param array $data -- output array from vb5
	 */
	public static function map_vb5_output_to_vb4($method, &$data)
	{
        if(strstr($method, "login_login"))
        {
            $copy_data = $data;
            $copy_data['dbsessionhash'] = $copy_data['sessionhash'];
            unset($copy_data['sessionhash']);
            $data = array();
            $data["session"] = $copy_data;
            unset($copy_data);
            $data["response"]["errormessage"][0] = "redirect_login";
        }

        if(strstr($method, "login_logout"))
        {
            $copy_data = $data;
            $copy_data['dbsessionhash'] = $copy_data['sessionhash'];
            unset($copy_data['sessionhash']);
            $data = array();
            $data["session"] = $copy_data;
            unset($copy_data);
            $data["response"]["errormessage"][0] = "cookieclear";
        }

		self::remove_nulls($data);
	}

	private static function remove_nulls(&$data)
	{
		foreach ($data as $key => &$value)
		{
			if (is_array($value)) 
			{
				self::remove_nulls($value);
			}
			else if ($value === null)
			{
				$value = '';
			}
		}
	}

    /**
     * Alters the error array in any way necessary to interface correctly
     * with vb4.
	 * @param string $method -- vb4 method name
	 * @param array $data -- error array from vb5
	 */
	public static function map_vb5_errors_to_vb4($method, &$data)
	{
        if(strstr($method,"api_init"))
        {
            $data = array();
            $data["response"]["errormessage"] = array("apiclientinfomissing");
        }
        else if(strstr($method, "login_login"))
        {
            $data = array();
            $data["response"]["errormessage"] = array("badlogin");
        }
        else if(strstr($method, "forumdisplay"))
        {
            if($data[0][0] == 'invalid_node_id')
            {
                $data = array();
                $data["response"]["errormessage"] = array("invalidid");
            }
            else
            {
                $data = array();
                $data["response"]["errormessage"] = array("invalidid");
            }
        }
        else if(strstr($method, "private_showpm"))
        {
            $data = array();
            $data["response"]["errormessage"] = array("invalidid");
        }
        else if(strstr($method, "showthread"))
        {
            if($data[0][0] == 'invalid_node_id')
            {
                $data = array();
                $data["response"]["errormessage"] = array("invalidid");
            }
            else
            {
                $data = array();
                $data["response"]["errormessage"] = array("invalidid");
            }
        }
	}

	// THIS CODE IS WAS EXTRACTED FROM DIFFERENT FILES OF VB4 BOOTSTRAP AND IS DUPLICATED
	protected function __construct()
	{
		// This is a dummy object $vbulletin just to avoid rewriting all code
		global $vbulletin;

		if (empty($vbulletin))
		{
			$vbulletin = vB::get_registry();
		}
		if (empty($vbulletin->db) AND class_exists('vB') AND !empty(vB::$db))
		{
			$vbulletin->db = vB::$db;
		}
	}

	/**
	 * This method checks whether the API method is enabled
	 */
	public function checkApiState($method)
	{
		$result = vB_Api::instanceInternal('state')->checkBeforeView();

		if ($result !== FALSE)
		{
			$this->disabled = TRUE;

			if (!in_array($method, $this->disableWhiteList))
			{
				throw new vB_Exception_Api_Disabled($result['msg']);
			}
		}
	}

	/**
	 * Replaces special characters in a given string with dashes to make the string SEO friendly
	 *
	 * @param	string	The string to be converted
	 */
	protected function toSeoFriendly($str)
	{
		if (!empty($str))
		{
			return vB_String::getUrlIdent($str);
		}
		return $str;
	}

	/**
	 * Determines if the calling user has the given admin permission, and if not throws an exception
	 *
	 * @param	string	The admin permission to check
	 */
	protected function checkHasAdminPermission($adminPermission)
	{
		$session = vB::getCurrentSession();
		if (!$session->validateCpsession())
		{
			throw new vB_Exception_Api('auth_required');
		}

		if (!vB::getUserContext()->hasAdminPermission($adminPermission))
		{
			$user = &vB::getCurrentSession()->fetch_userinfo();
			if ($user['userid'] > 0)
			{
				throw new vB_Exception_Api('nopermission_loggedin',
					array(
						$user['username'],
						vB_Template_Runtime::fetchStyleVar('right'),
						vB::getCurrentSession()->get('sessionurl'),
						$user['securitytoken'],
						class_exists('vB5_Config') ? vB5_Config::instance()->baseurl : vB::getDatastore()->getOption['bburl']
					)
				);
			}
			else
			{
				throw new vB_Exception_Api('no_permission');
			}
		}

	}

	/**
	 * Determines if the calling user has the given admin permission, and if not throws an exception
	 *
	 * @param	string	The admin permission to check
	 */
	protected function checkIsLoggedIn()
	{
		$userId = (int) vB::getUserContext()->fetchUserId();
		if ($userId < 1)
		{
			throw new vB_Exception_Api('not_logged_no_permission');
		}
	}
}

