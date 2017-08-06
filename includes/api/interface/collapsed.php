<?php

class Api_Interface_Collapsed extends Api_InterfaceAbstract
{
	protected $initialized = false;

	public function init()
	{
		if ($this->initialized)
		{
			return true;
		}

		//initialize core
		$core_path = vB5_Config::instance()->core_path;
		require_once($core_path . '/vb/vb.php');
		vB::init();

		//todo -- this is a hack to communicate the base url to the core for links and redirects.
		$_SERVER['x-vb-presentation-base'] = vB5_Config::instance()->baseurl;
		$request = new vB_Request_WebApi();
		vB::setRequest($request);

		$sessionhash = vB5_Cookie::get('sessionhash', vB5_Cookie::TYPE_STRING);
		$session = $request->createSession($sessionhash,
			vB5_Cookie::get('userid', vB5_Cookie::TYPE_STRING),
			vB5_Cookie::get('password', vB5_Cookie::TYPE_STRING));

		$languageid = vB5_User::getLanguageId();
		if ($languageid)
		{
			vB::getCurrentSession()->set('languageid', $languageid);
		}

		$styleid = vB5_Template_Stylevar::instance()->getPreferredStyleId();
		if ($styleid)
		{
			vB::getCurrentSession()->set('styleid', $styleid);
		}

		if ($session['sessionhash'] !== $sessionhash)
		{
			vB5_Cookie::set('sessionhash', $session['sessionhash'], 0, true);
		}

		// Try to set cpsession hash to session object if exists
		vB::getCurrentSession()->setCpsessionHash(vB5_Cookie::get('cpsession', vB5_Cookie::TYPE_STRING));

		// Update lastvisit/lastactivity
		$info = vB::getCurrentSession()->doLastVisitUpdate(vB5_Cookie::get('lastvisit', vB5_Cookie::TYPE_UINT), vB5_Cookie::get('lastactivity', vB5_Cookie::TYPE_UINT));
		if (!empty($info))
		{
			// for guests we need to set some cookies
			if (isset($info['lastvisit']))
			{
				vB5_Cookie::set('lastvisit', $info['lastvisit']);
			}

			if (isset($info['lastactivity']))
			{
				vB5_Cookie::set('lastactivity', $info['lastactivity']);
			}
		}

		$this->initialized = true;
	}

	public function callApi($controller, $method, array $arguments = array(), $useNamedParams = false, $byTemplate = false)
	{
		try
		{
			$c = vB_Api::instance($controller);
		}
		catch (vB_Exception_Api $e)
		{
			throw new vB5_Exception_Api($controller, $method, $arguments, array('Failed to create API controller.'));
		}

		if ($useNamedParams)
		{
			$result = $c->callNamed($method, $arguments);
		}
		else
		{
			$result = call_user_func_array(array(&$c, $method), $arguments);
		}

		// The api call sets error/exception handlers appropriate to core. We need to reset.
		// But if the API is called by template ({vb:data}), we should use the core exception handler.
		// Otherwise we will have endless loop. See VBV-1682.
		if (!$byTemplate)
		{
			set_exception_handler(array('vB5_ApplicationAbstract', 'handleException'));
			set_error_handler(array('vB5_ApplicationAbstract', 'handleError'), E_WARNING);
		}
		return $result;

	}

	public function relay($file)
	{
		//todo -- this is a hack to communicate the base url to the core for links and redirects.
		$_SERVER['x-vb-presentation-base'] = vB5_Config::instance()->baseurl;

		$filePath = vB5_Config::instance()->core_path . '/' . $file;

		if ($file AND file_exists($filePath))
		{
			require_once($filePath);
		}
		else
		{
			// todo: redirect to 404 page instead
			throw new vB5_Exception_404("invalid_page_url");
		}
	}

	//quick passthrough for the backend cache.  We'll need to do something fancier for the
	//non collapsed mode, but we will want to connect to the cache directly rather than
	//go through the API.
	public function cacheInstance($type)
	{
		return vB_Cache::instance($type);
	}
}
