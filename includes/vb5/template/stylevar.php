<?php

class vB5_Template_Stylevar
{

	protected static $instance;
	protected $cache = array();
	protected $stylePreference = array();

	/**
	 *
	 * @return vB5_Template_Stylevar 
	 */
	public static function instance()
	{
		if (!isset(self::$instance))
		{
			$c = __CLASS__;
			self::$instance = new $c;
		}

		return self::$instance;
	}

	protected function __construct()
	{
		$this->getStylePreference();
		$this->fetchStyleVars();
	}

	/**
	 * Returns the styleid that should be used on this request
	 */
	public function getPreferredStyleId()
	{
		return intval(reset($this->stylePreference));
	}

	/**
	 * Gets the styles to be used ordered by preference
	 */
	protected function getStylePreference()
	{
		$this->stylePreference = array();

		try
		{
			$router = vB5_ApplicationAbstract::instance()->getRouter();
			$arguments = $router->getArguments();

			// #1 check for a forced style in current route
			if (!empty($arguments) AND !empty($arguments['forceStyleId']) AND is_int($arguments['forceStyleId']))
			{
				$this->stylePreference[] = $arguments['forceStyleId'];
			}
		}
		catch (vB5_Exception $e)
		{
			// the application instance might not be initialized yet, so just ignore this first check
		}

		// #2 check for a style cookie (style chooser in footer)
		// If style is set in querystring, the routing component will set this cookie (VBV-3322)
		$cookieStyleId = vB5_Cookie::get('userstyleid', vB5_Cookie::TYPE_UINT);
		if (!empty($cookieStyleId))
		{
			$this->stylePreference[] = $cookieStyleId;
		}

		// #3 check for user defined style
		$userStyleId = vB5_User::get('styleid');
		if (!empty($userStyleId))
		{
			$this->stylePreference[] = $userStyleId;
		}

		// #4 check for a route style which is not forced
		if (isset($arguments['routeStyleId']) AND is_int($arguments['routeStyleId']))
		{
			$this->stylePreference[] = $arguments['routeStyleId'];
		}

		// #5 check for the overall site default style
		$defaultStyleId = vB5_Template_Options::instance()->get('options.styleid');
		if ($defaultStyleId)
		{
			$this->stylePreference[] = $defaultStyleId;
		}
	}

	public function get($name)
	{
		$path = explode('.', $name);

		$var = $this->cache;
		foreach ($path as $t)
		{
			if (isset($var[$t]))
			{
				$var = $var[$t];
			}
			else
			{
				return NULL;
			}
		}

		return $var;
	}

	protected function fetchStyleVars()
	{
		$res = Api_InterfaceAbstract::instance()->callApi('style', 'fetchStyleVars', array($this->stylePreference)); // api method returns unserealized stylevars

		if (empty($res) OR !empty($res['errors']))
		{
			return;
		}

		$user = vB5_User::instance();
		if (is_null($user['lang_options']) OR $user['lang_options']['direction'])
		{
			// if user has a LTR language selected
			$res['textdirection'] = array('datatype' => 'string', 'string' => 'ltr');
			$res['left'] = array('datatype' => 'string', 'string' => 'left');
			$res['right'] = array('datatype' => 'string', 'string' => 'right');
		}
		else
		{
			// if user has a RTL language selected
			$res['textdirection'] = array('datatype' => 'string', 'string' => 'rtl');
			$res['left'] = array('datatype' => 'string', 'string' => 'right');
			$res['right'] = array('datatype' => 'string', 'string' => 'left');
		}

		foreach ($res as $key => $value)
		{
			$this->cache[$key] = $value;
		}
	}

}
