<?php

class vB5_Template_Cache
{
	const PLACEHOLDER_PREFIX = '<!-- ##template_';
	const PLACEHOLDER_SUFIX = '## -->';

	protected static $instance;
	protected $cache = array();
	protected $renderTemplatesInReverseOrder = false;

	protected $preloadHashKey = '';
	protected $preloadTemplates = array();

	/**
	 *
	 * @var array Stores the template info for direct descendants of the parent template.
	 */
	protected $pending = array();

	public static function instance()
	{
		if (!isset(self::$instance))
		{
			$c = __CLASS__;

			if (vB5_Template_Options::instance()->get('options.cache_templates_as_files'))
			{
				$c .= '_Filesystem';
			}
			self::$instance = new $c;
		}

		return self::$instance;
	}

	public function isTemplateText()
	{
		return true;
	}

	/**
	 * Stores template info for deferred fetching & rendering, and returns a placeholder
	 * @param string $templateName
	 * @param array $args
	 * @return string
	 */
	public function register($templateName, $args)
	{
		$pos = isset($this->pending[$templateName]) ? count($this->pending[$templateName]) : 0;
		$placeholder = $this->getPlaceholder($templateName, $pos);

		$this->pending[$templateName][$placeholder] = $args;

		return $placeholder;
	}


	/**
	 *	Preload templates used in the past to render this page.
	 *
	 *	@param $pageid The id of page that we are preloading the templates for.
	 */
	public function preload($pageid)
	{
		// see if we know what templates to preload
		$this->preloadHashKey = 'preloadTemplates_' . $pageid;
		$this->preloadTemplates = vB_Cache::instance()->read($this->preloadHashKey);

		if (!empty($this->preloadTemplates) AND is_array($this->preloadTemplates))
		{
			// preload the templates
			vB5_Template_Cache::instance()->getTemplate($this->preloadTemplates);
		}
		else
		{
			$this->preloadTemplates = array();
		}
	}

	/**
	 *	Store the additional templates loaded on this page in the cache
	 *
	 *	@param $loadedTemplates.  The templates loaded on this page.
	 */
	public function storePreload($pageid, $loadedTemplates)
	{
		// do we need to update the cache?
		$newTemplates = array_diff($loadedTemplates, $this->preloadTemplates);
		if ((empty($this->preloadTemplates) AND (!empty($loadedTemplates)) OR count($newTemplates)))
		{
			// cache the template names for this page
			$loadedTemplates += $newTemplates;
			vB_Cache::instance()->write($this->preloadHashKey, $loadedTemplates, 86400, 'pageChg_' . $pageid);
		}
	}

	/**
	 * Register variables in template
	 * @param vB5_Template $templater
	 * @param array $templateArgs
	 */
	protected function registerTemplateVariables($templater, $templateArgs)
	{
		// this is for allowing indexed access to variables
		$templater->register('arg', array_values($templateArgs));

		// also registered named variables
		foreach ($templateArgs as $key=>$value)
		{
			if (is_string($key))
			{
				$templater->register($key, $value);
			}
		}
	}

	/**
	 * Instructs the class to render the templates in reverse order or not
	 *
	 * @param	bool
	 */
	public function setRenderTemplatesInReverseOrder($value)
	{
		$this->renderTemplatesInReverseOrder = (bool) $value;
	}

	/**
	 * Replaces all template placeholders in $content with the rendered templates
	 * @param string $content
	 */
	public function replacePlaceholders(&$content)
	{
		// This function procceses subtemplates by level

		$missing = array_diff(array_keys($this->pending), array_keys($this->cache));
		if (!empty($missing))
		{
			$this->fetchTemplate($missing);
		}

		// move pending templates to a new variable, so they are not re-processed by subtemplates
		$levelPending = & $this->pending;
		unset($this->pending);
		$this->pending = array();

		// This line is important. In BBCode parser, the templates of inner BBCode are registered firstly
		// So they should be replaced later than the outer BBCode templates. See VBV-4834.
		if ($this->renderTemplatesInReverseOrder)
		{
			$levelPending = array_reverse($levelPending);
		}

		foreach ($levelPending as $templateName => $templates)
		{
			foreach ($templates as $placeholder => $templateArgs)
			{
				$templater = new vB5_Template($templateName);
				$this->registerTemplateVariables($templater, $templateArgs);

				try
				{
					$replace = $templater->render(false);
				}
				catch (vB5_Exception_Api $e)
				{
					$e->prependTemplate($templateName);

					if (isset($templateArgs['isWidget']) AND $templateArgs['isWidget'])
					{
						$errorTemplate = new vB5_Template(vB5_Template::WIDGET_ERROR_TEMPLATE);

						// we want to make the registered variables available to error template
						$this->registerTemplateVariables($errorTemplate, $templateArgs);

						if(vB5_Config::instance()->debug)
						{
							$errorTemplate->register('template', $e->getTemplate());
							$errorTemplate->register('controller', $e->getController());
							$errorTemplate->register('method', $e->getMethod());
							$errorTemplate->register('arguments', print_r($e->getArguments(), true));
							$errorTemplate->register('errors', print_r($e->getErrors(), true));
						}

						$err = $e->getErrors();
						$isPermissionError = (isset($err[0]) AND isset($err[0][0]) AND $err[0][0] == 'no_permission');
						$errorTemplate->register('isPermissionError', $isPermissionError);

						$replace = $errorTemplate->render(false);
					}
					else
					{
						throw $e;
					}
				}

				$content = str_replace($placeholder, $replace, $content);
				unset($templater);
			}
		}
	}

	public function getTemplate($templateId)
	{

		if (is_array($templateId))
		{
			return $this->fetchTemplate($templateId);
		}

		if (!isset($this->cache[$templateId]))
		{
			$this->fetchTemplate($templateId);
		}

		if (isset($this->cache[$templateId]))
		{
			return $this->cache[$templateId];
		}

		throw new Exception('Non-existent template requested: ' . htmlspecialchars($templateId));
	}

	protected function getPlaceholder($templateName, $pos)
	{
		return self::PLACEHOLDER_PREFIX . $templateName . '_' . $pos . self::PLACEHOLDER_SUFIX;
	}

	/**
	 * Receives either a template name or an array of template names to be fetched from the API
	 * @param mixed $templateName
	 */
	protected function fetchTemplate($templateName)
	{
		if (is_array($templateName))
		{
			$method = 'fetchBulk';
			$arguments = array('template_names' => $templateName);
		}
		else
		{
			$method = 'fetch';
			$arguments = array('name' => $templateName);
		}

		if ($styleId = vB5_Template_Stylevar::instance()->getPreferredStyleId())
		{
			$arguments['styleid'] = $styleId;
		}
		$response = Api_InterfaceAbstract::instance()->callApi('template', $method, $arguments);
		// this api call may return a bunch of subtemplates as well
		if (is_array($response))
		{
			foreach ($response AS $id => $code)
			{
				// in this layer we need to use vB5_Template_Runtime instead of vB_Template_Runtime
				$code = str_replace('vB_Template_Runtime', 'vB5_Template_Runtime', $code);

				$this->cache[$id] = $code;
			}
		}
		else
		{
			//This probably doesn't do what you think it does.  The response is always going to be an
			//array of some kind unless its a value like false (which is shouldn't be)
			// in this layer we need to use vB5_Template_Runtime instead of vB_Template_Runtime
			$response = str_replace('vB_Template_Runtime', 'vB5_Template_Runtime', $response);
			$this->cache[$templateName] = $response;
		}
	}

}
