<?php

class vB5_Template_Javascript
{

	protected static $instance;
	protected $pending = array();

	public static function instance()
	{
		if (!isset(self::$instance))
		{
			$c = __CLASS__;
			self::$instance = new $c;
		}

		return self::$instance;
	}

	public function register($files)
	{
		foreach ($files as $file)
		{
			if (!in_array($file, $this->pending))
			{
				$this->pending[] = $file;
			}
		}
	}

	public function resetPending()
	{
		$this->existing = array();
		$this->pending = array();
	}

	public function insertJsInclude($scripts)
	{
		$config = vB5_Config::instance();
		if (!isset($this->jsbundles))
		{
			$this->loadJsBundles();
		}
		if ($config->no_js_bundles)
		{
			foreach ($scripts as $bundle)
			{
				$removed = false;
				if (strpos($bundle, 'js/') === 0)
				{
					$removed = true;
					$bundle = substr($bundle, 3);
				}
				if (isset($this->jsbundles[$bundle]))
				{
					foreach ($this->jsbundles[$bundle] as $jsfile)
					{
						$expanded[] = $jsfile;
					}
				}
				else
				{
					if ($removed)
					{
						$expanded[] = 'js/' . $bundle;
					}
					else
					{
						$expanded[] = $bundle;
					}
				}
			}
			if (!empty($expanded))
			{
				$scripts = $expanded;
			}
		}

		$simpleversion = vB5_Template_Options::instance()->get('options.simpleversion');
		$prescripts = $scripts;
		$scripts = array();
		foreach ($prescripts as &$js)
		{
			$rollupname = substr($js, 3);
			if (isset($this->jsbundles[$rollupname]))
			{
				$scripts[] = preg_replace("#/([^\.]+).js#", "/$1-$simpleversion.js", $js);
			}
			else
			{
				$scripts[] = $js . '?v=' . $simpleversion;
			}
		}

		$replace = '';
		$loaded = array();
		foreach($scripts as $js)
		{
			if (!in_array($js, $loaded))
			{
				$replace .= '<script type="text/javascript" src="' . $config->baseurl . "/$js\"></script>\n";
				$loaded[] = $js;
			}
		}
		return $replace;
	}

	public function insertJs(&$content)
	{
		$replace = $this->insertJsInclude($this->pending);

		if (stripos($content, '</body>') !== FALSE)
		{
			$replace .= '</body>';
			$content = str_replace('</body>', $replace, $content);
		}
		else
		{
			$content .= $replace;
		}
	}

	private function loadJsBundles()
	{
		$jsfilelist = Api_InterfaceAbstract::instance()->callApi('product', 'loadProductXmlListParsed', array('type' => 'jsrollup', 'typekey' => true));

		if (empty($jsfilelist['vbulletin']))
		{
			return false;
		}
		else
		{
			$data = $jsfilelist['vbulletin'];
		}

		if (!is_array($data['rollup'][0]))
		{
			$data['rollup'] = array($data['rollup']);
		}

		foreach ($data['rollup'] AS $file)
		{
			if (!is_array($file['template']))
			{
				$file['template'] = array($file['template']);
			}
			foreach ($file['template'] AS $name)
			{
				$vbdefaultjs["$file[name]"] = $file['template'];
			}
		}

		$this->jsbundles = $vbdefaultjs;

		// TODO: Add product xml handling here if we need it.

		return true;
	}
}
