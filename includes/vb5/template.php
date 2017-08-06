<?php

/**
 * This class is a simplified version of the one implemented in includes/class_core.php
 */
class vB5_Template
{
	const WIDGET_ERROR_TEMPLATE = 'widget_error';

	/**
	 * Name of the template to render
	 *
	 * @var	string
	 */
	protected $template = '';

	/**
	 * Array of registered variables.
	 * @see vB5_Template::preRegister()
	 *
	 * @var	array
	 */
	protected $registered = array();

	/**
	 * Array of global registered variables.
	 * Global registered variables are available in main templates and child templates
	 * included with {vb:template}
	 *
	 * @var array
	 */
	protected static $globalRegistered = array();

	/**
	 * List of templates rendered (for debugging output)
	 *
	 * @var array
	 */
	protected static $renderedTemplates = array();
	protected static $renderedTemplateNames = array();
	protected static $renderedTemplatesStack = array();

	/*
	 * jQuery URL
	 */
	protected static $jQueryUrl = '';
	protected static $jQueryUrlLogin = '';

	/**
	 * Constructor
	 */
	public function __construct($templateName)
	{
		$this->template = $templateName;

		$this->register('admincpdir', vB5_Config::instance()->admincpdir);
		$this->registerjQuery();
	}

	/**
	 * Register a variable with the template.
	 * Global registered variables are available in main templates and child templates
	 * included with {vb:template}
	 *
	 * @param	string	Name of the variable to be registered
	 * @param	mixed	Value to be registered. This may be a scalar or an array.
	 * @param	bool	Whether to overwrite existing vars
	 * @return	bool	Whether the var was registered
	 */
	public function register($name, $value, $overwrite = true)
	{
		if (!$overwrite AND $this->isRegistered($name))
		{
			return false;
		}

		$this->registered[$name] = $value;

		return true;
	}

	/**
	 * Register a global variable with the template.
	 *
	 * @param	string	Name of the variable to be registered
	 * @param	mixed	Value to be registered. This may be a scalar or an array.
	 * @param	bool	Whether to overwrite existing vars
	 * @return	bool	Whether the var was registered
	 */
	public function registerGlobal($name, $value, $overwrite = true)
	{
		if (!$overwrite AND $this->isGlobalRegistered($name))
		{
			return false;
		}

		self::$globalRegistered[$name] = $value;

		return true;
	}

	/**
	 * Determines if a named variable is registered.
	 *
	 * @param	string	Name of variable to check
	 * @return	bool
	 */
	public function isRegistered($name)
	{
		return isset($this->registered[$name]);
	}

	/**
	 * Determines if a named variable is global registered.
	 *
	 * @param	string	Name of variable to check
	 * @return	bool
	 */
	public function isGlobalRegistered($name)
	{
		return isset(self::$globalRegistered[$name]);
	}

	protected function registerjQuery()
	{
		if (!self::$jQueryUrl)
		{
			// create the path to jQuery depending on the version
			$customjquery_path = vB::getDatastore()->getOption('customjquery_path');
			$remotejquery = vB::getDatastore()->getOption('remotejquery');

			$session = vB::getCurrentSession();

			if ($session)
			{
				$protocol = vB::getRequest()->getVbUrlScheme();
			}
			else  if (!empty($_SERVER['HTTPS'])) //session isn't set.
			{
				$protocol = 'https';
			}
			else
			{
				$protocol = 'http';
			}

			if ($customjquery_path)
			{
				$path = str_replace('{version}', JQUERY_VERSION, $customjquery_path);
				if (!preg_match('#^https?://#si', $customjquery_path))
				{
					$path = $protocol . '://' . $path;
				}
				self::$jQueryUrlLogin = self::$jQueryUrl = $path;
			}
			else if ($remotejquery == 1)
			{	// Google CDN
				self::$jQueryUrlLogin = self::$jQueryUrl = $protocol . '://ajax.googleapis.com/ajax/libs/jquery/' . JQUERY_VERSION . '/jquery.min.js';
			}
			else if ($remotejquery == 2)
			{	// jQuery CDN
				self::$jQueryUrlLogin = self::$jQueryUrl = $protocol . '://code.jquery.com/jquery-' . JQUERY_VERSION . '.min.js';
			}
			else if ($remotejquery == 3)
			{	// Microsoft CDN
				self::$jQueryUrlLogin = self::$jQueryUrl = $protocol . '://ajax.aspnetcdn.com/ajax/jquery/jquery-' . JQUERY_VERSION . '.min.js';
			}
			else
			{
				self::$jQueryUrl = vB5_Config::instance()->baseurl . '/js/jquery/jquery-' . JQUERY_VERSION . '.min.js';
				self::$jQueryUrlLogin = vB5_Config::instance()->baseurl_login . '/js/jquery/jquery-' . JQUERY_VERSION . '.min.js';
			}
		}

		$this->register('jqueryurl', self::$jQueryUrl);
		$this->register('jqueryurl_login', self::$jQueryUrlLogin);
		$this->register('jqueryversion', JQUERY_VERSION);
	}

	/**
	 * Renders the output after preperation.
	 * @see vB5_Template::render()
	 *
	 * @param boolean	Whether to suppress the HTML comment surrounding option (for JS, etc)
	 * @return string
	 */
	public function render($isParentTemplate = true)
	{
		static $user = false;

		if (!$user)
		{
			$user = vB5_User::instance();
		}

		$config = vB5_Config::instance();

		$this->register('user', $user, true);
		extract(self::$globalRegistered, EXTR_SKIP | EXTR_REFS);
		extract($this->registered, EXTR_OVERWRITE | EXTR_REFS);
		$baseurl = $config->baseurl;
		$baseurl_core = $config->baseurl_core;
		$baseurl_login = $config->baseurl_login;

		$baseurl_data = vB5_String::parseUrl($baseurl);

		if (isset($baseurl_data['path']))
		{
			$baseurl_path = $baseurl_data['path'];
		}
		$baseurl_path = isset($baseurl_path) ? ($baseurl_path . (substr($baseurl_path, -1) != '/' ? '/' : '')) : '/'; //same as cookie path

		$cookie_prefix = $config->cookie_prefix;


		$vboptions = vB5_Template_Options::instance()->getOptions();
		$vboptions = $vboptions['options'];

		//this assumes that core is in the core directory which is not something we've generally assumed
		//however as noncollapsed mode look unlikely to be as useful as we thought, we'll start making that
		//assumption.  However setting a seperate variable means we don't spread that assumption all through
		//the template code.	
		$baseurl_cdn = $vboptions['cdnurl'];
		if($baseurl_cdn)
		{
			$baseurl_corecdn = $baseurl_cdn . '/core';		
		}
		else
		{
			//if we haven't set a cdn url, then let's default to the actual site urls.
			$baseurl_cdn = $baseurl;
			$baseurl_corecdn = $baseurl_core;
		}
		
		
		$vbproducts = vB::getDatastore()->getValue('products');

		$preferred_styleid = vB5_Template_Stylevar::instance()->getPreferredStyleId() > 0 ? vB5_Template_Stylevar::instance()->getPreferredStyleId() : $vboptions['styleid'];
		$preferred_languageid  = vB5_User::getLanguageId() > 0 ? vB5_User::getLanguageId() : $vboptions['languageid'];

		$timenow = time();
		self::$renderedTemplateNames[] = $this->template;
		// debug info for the templates that have been used
		if ($config->debug)
		{
			self::$renderedTemplates[$this->template] = array(
				'isParentTemplate' => (bool) $isParentTemplate,
				'indent' => str_repeat('|----', count(self::$renderedTemplatesStack)),
			);
			self::$renderedTemplatesStack[] = $this->template;
		}

		// todo: remove this once we can remove notices from template code
		// allow developers to turn notices off for templates -- to avoid having them turn off notices entirely
		if ($config->no_template_notices)
		{
			$oldReporting = error_reporting(E_ALL & ~E_NOTICE);
		}

		if ($config->render_debug)
		{
			set_exception_handler(null);
			set_error_handler('vberror');

			// Show which template is being rendered.
			echo 'Template: ' . $this->template . '<br />';
		}

		$templateCache = vB5_Template_Cache::instance();
		$templateCode = $templateCache->getTemplate($this->template);
		if($templateCache->isTemplateText())
		{
			@eval($templateCode);
		}
		else
		{
			if ($templateCode !== false)
			{
				@include($templateCode);
			}
		}

		if ($config->render_debug)
		{
			restore_error_handler();
			restore_exception_handler();
		}

		if ($config->no_template_notices)
		{
			error_reporting($oldReporting);
		}


		// always replace placeholder for templates, as they are process by levels
		$templateCache->replacePlaceholders($final_rendered);

		if ($isParentTemplate)
		{
			// we only replace phrases/urls/nodetext, insert javascript and stylesheets at the parent template
			$this->renderDelayed($final_rendered);
		}

		// debug info for the templates that have been used
		if ($config->debug)
		{
			array_pop(self::$renderedTemplatesStack);
		}

		// add template name to HTML source for debugging
		if (!empty($vboptions['addtemplatename']) AND $vboptions['addtemplatename'])
		{
			$final_rendered = "<!-- BEGIN: $this->template -->$final_rendered<!-- END: $this->template -->";
		}
		return $final_rendered;
	}

	/** Handle any delayed rendering. Currently delayed urls and node texts.
	*
	* @param	string
	*
	* @return	string
	**/
	protected function renderDelayed(&$final_rendered_orig)
	{
		$javascript = vB5_Template_Javascript::instance();
		$javascript->insertJs($final_rendered_orig);
		$javascript->resetPending();

		$stylesheet = vB5_Template_Stylesheet::instance();
		$stylesheet->insertCss($final_rendered_orig);
		$stylesheet->resetPending();

		$phrase = vB5_Template_Phrase::instance();
		$phrase->replacePlaceholders($final_rendered_orig);
		$phrase->resetPending();

		// we do not reset pending urls, since they may be required by nodetext
		vB5_Template_Url::instance()->replacePlaceholders($final_rendered_orig);

		$nodeText = vB5_Template_NodeText::instance();
		$nodeText->replacePlaceholders($final_rendered_orig);
		$nodeText->resetPending();

		//We should keep the debug info for truly last.
		if (vB5_Frontend_Controller_Bbcode::needDebug())
		{
			$config = vB5_Config::instance();

			if (!$config->debug)
			{
				return $final_rendered_orig;
			}

			self::$renderedTemplates['debug_info'] = array(
				'isParentTemplate' => (bool) 0,
				'indent' => str_repeat('|----', 2),
			);

			$user = vB5_User::instance();
			$this->register('user', $user, true);
			extract(self::$globalRegistered, EXTR_SKIP | EXTR_REFS);
			extract($this->registered, EXTR_OVERWRITE | EXTR_REFS);
			$vboptions = vB5_Template_Options::instance()->getOptions();
			$vboptions = $vboptions['options'];
			$renderedTemplates = array(
				'count' => count(self::$renderedTemplates),
				'templates' => self::$renderedTemplates,
				'styleid' => vB5_Template_Stylevar::instance()->getPreferredStyleId(),
			);
			$facebookDebugLog = vB5_Facebook::getDebugLog();

			$templateCache = vB5_Template_Cache::instance();
			$templateCode = $templateCache->getTemplate('debug_info');
			if($templateCache->isTemplateText())
			{
				@eval($templateCode);
			}
			else
			{
				@include($templateCode);
			}
			$final_rendered_orig = str_replace('<!-DebugInfo-->', $final_rendered, $final_rendered_orig);
		}
	}

	public static function getRenderedTemplates()
	{
		return self::$renderedTemplateNames;
	}

	/**
	 * Returns a string containing the rendered template
	 * @see vB5_Frontend_Controller_Ajax::actionRender
	 * @see vB5_Frontend_Controller_Page::renderTemplate
	 * @param string $templateName
	 * @param array $data
	 * @param bool $isParentTemplate
	 * @return string
	 */
	public static function staticRender($templateName, $data = array(), $isParentTemplate=true)
	{
		if (empty($templateName))
		{
			return null;
		}

		$templater = new vB5_Template($templateName);

		foreach ($data as $varname => $value)
		{
			$templater->register($varname, $value);
		}

		$core_path = vB5_Config::instance()->core_path;
		vB5_Autoloader::register($core_path);

		$result = $templater->render($isParentTemplate);
		return $result;
	}
}
