<?php

class vB5_Template_Stylesheet
{

	protected static $instance;
	protected $pending = array();
	protected $cssBundles = null;

	public static function instance()
	{
		if (!isset(self::$instance))
		{
			$c = __CLASS__;
			self::$instance = new $c;
		}

		return self::$instance;
	}

	public function register($args)
	{
		$this->pending = array_unique(array_merge($this->pending, $args));
	}

	public function resetPending()
	{
		$this->pending = array();
	}

	public function insertCss(&$content)
	{
		if (empty($this->pending))
		{
			return;
		}
		$options = vB5_Template_Options::instance();
		$storecssasfile = $options->get('options.storecssasfile');
		$cssdate = intval($options->get('miscoptions.cssdate'));

		if (!$cssdate)
		{
			$cssdate = time(); // fallback so we get the latest css
		}

		$user = vB5_User::instance();
		$textdirection = ($user['lang_options']['direction'] ? 'ltr' : 'rtl');
		// we cannot query user directly for styleid, we need to consider other parameters
		$styleid = vB5_Template_Stylevar::instance()->getPreferredStyleId();
		$vbcsspath = $this->getCssPath($storecssasfile, $textdirection, $styleid);

		$replace = '';
		//if user style customization is enabled we need to hand css_profile specially. It can never come from disk
		//regardless of the option setting.
		$userprofilecss = '';
		// TODO: we cannot read the datastore directly
		$options = vB::getDatastore()->getValue('options');
		$bf_misc_socnet = vB::getDatastore()->getValue('bf_misc_socnet');

		if (isset($options['enable_profile_styling']) AND $options['enable_profile_styling'])
		{
			//we look for the css_profile.css file
			foreach ($this->pending as $key => $css)
			{
				if (substr($css, 0, 15) == 'css_profile.css')
				{
					if ($storecssasfile)
					{
						$css =  preg_replace('/&/', '?', $css, 1);
					}
					$joinChar = (strpos($vbcsspath . $css, '?') === false) ? '?' : '&amp;';
					$userprofilecss = '<link rel="stylesheet" type="text/css" href="' .
						htmlspecialchars($vbcsspath . $css) . "{$joinChar}ts=$cssdate \" />\n";
					unset ($this->pending[$key]);
				}
			}
		}

		if ($storecssasfile)
		{
			foreach($this->pending as $css)
			{
				$replace .= '<link rel="stylesheet" type="text/css" href="' . htmlspecialchars($vbcsspath . $css) . "\" />\n";
			}
		}
		else
		{
			// Deconstruct bundle logic
			if ($this->cssBundles == null)
			{
				$this->loadCSSBundles();
			}
			
			$joinChar = (strpos($vbcsspath, '?') === false) ? '?' : '&amp;';
			$templates = array(); //for dupe checking
			$ieLinks = '';
			$nonIeLinks = '';
			
			// We're using css bunldes instead of combining everything into a single css.php call
			// to take advantage of client side caching. We're also incoporating the rollup system into
			// css files stored on the db by linking css.php with all the templates of that bundle in one call.
			// And we're also avoiding single templates having their own <link> tag if they're already used 
			// in a bundle or elsewhere.
			foreach ($this->pending as $bundle)
			{
				if (isset($this->cssBundles[$bundle]))
				{
					$templates = array_merge($templates, $this->cssBundles[$bundle]);
					
					// Output the stylesheets twice-- once for IE, once for the rest.
					// For IE, we split into groups of 5 so we don't exceed IE's limit
					// on the number of CSS rules in a file. See VBV-7077
					$pendingChunks = array_chunk($this->cssBundles[$bundle], 5);
					foreach ($pendingChunks AS $pendingSheets)
					{
						$ieLinks .= '<link rel="stylesheet" type="text/css" href="' .
							htmlspecialchars($vbcsspath . implode(',', $pendingSheets)) . "{$joinChar}ts=$cssdate \" />\n";
					}
					$nonIeLinks .= '<link rel="stylesheet" type="text/css" href="' .
						htmlspecialchars($vbcsspath . implode(',', $this->cssBundles[$bundle])) . "{$joinChar}ts=$cssdate \" />\n";
				}
				else if (!in_array($bundle, $templates))
				{
					// we have a single template. that wasn't caught before. link it.
					$templates[] = $bundle;
					$ieLinks .= '<link rel="stylesheet" type="text/css" href="' .
						htmlspecialchars($vbcsspath . $bundle) . "{$joinChar}ts=$cssdate \" />\n";
					$nonIeLinks .= '<link rel="stylesheet" type="text/css" href="' .
						htmlspecialchars($vbcsspath . $bundle) . "{$joinChar}ts=$cssdate \" />\n";
				}
			}
			unset ($templates);
			
			$replace .= "\n<!--[if IE]>\n";
			$replace .= $ieLinks;
			$replace .= "<![endif]-->\n<!--[if !IE]><!-->\n";
			$replace .= $nonIeLinks;
			$replace .= "<!--<![endif]-->\n";
		}

		// Note: This places user profile customized css after css_additional.css.
		$replace .= $userprofilecss . "\n";

		// insert the css before the first <script> tag in head element
		// if there is no script tag in <head>, then insert it at the
		// end of <head>
		$scriptPos = stripos($content, '<script');
		$headPos = stripos($content, '</head>');
		if ($scriptPos !== false && $scriptPos < (($headPos === false) ? PHP_INT_MAX : $headPos))
		{
			$top = substr($content, 0, $scriptPos);
			$bottom = substr($content, $scriptPos);
			$content = $top . $replace . $bottom;
		}
		else if ($headPos !== false)
		{
			$replace .= '</head>';
			$content = str_replace('</head>', $replace, $content);
		}
		else
		{	// specifically in here to accomidate fetching style sheets <link>s in ajax calls
			$content .= $replace;
		}
	}

	public function getCssFile($filename)
	{
		$options = vB5_Template_Options::instance();
		$storecssasfile = $options->get('options.storecssasfile');
		$cssdate = intval($options->get('miscoptions.cssdate'));

		if (!$cssdate)
		{
			$cssdate = time(); // fallback so we get the latest css
		}

		$user = vB5_User::instance();
		$textdirection = ($user['lang_options']['direction'] ? 'ltr' : 'rtl');
		// we cannot query user directly for styleid, we need to consider other parameters
		$styleid = vB5_Template_Stylevar::instance()->getPreferredStyleId();
		$vbcsspath = $this->getCssPath($storecssasfile, $textdirection, $styleid);

		if ($storecssasfile)
		{
			$file = htmlspecialchars($vbcsspath . $filename);
		}
		else
		{
			$joinChar = (strpos($vbcsspath, '?') === false) ? '?' : '&';
			$file = htmlspecialchars($vbcsspath . $filename . "{$joinChar}ts=$cssdate");
		}

		return $file;
	}

	private function getCssPath($storecssasfile, $textdirection, $styleid)
	{
		$config = vB5_Config::instance();
		$csspath = "";
		if ($storecssasfile)
		{
			$csspath = 'core/clientscript/vbulletin_css/style' . str_pad($styleid, 5, '0', STR_PAD_LEFT) . $textdirection[0] . '/';
		}
		else
		{
			$csspath = 'css.php?styleid=' . $styleid . '&td=' . $textdirection . '&sheet=';
		}

		$vboptions = vB5_Template_Options::instance()->getOptions();
		$vboptions = $vboptions['options'];	

		$baseurl = $vboptions['cdnurl'];
		if(!$baseurl)
		{
			$baseurl = $config->baseurl;
		}

		return $baseurl . '/' . $csspath;
	}

	private function loadCSSBundles()
	{
		$cssFileList = Api_InterfaceAbstract::instance()->callApi('product', 'loadProductXmlListParsed', array('type' => 'cssrollup', 'typekey' => true));
		$vBDefaultCss = array();

		if (empty($cssFileList['vbulletin']))
		{
			return false;
		}
		else
		{
			$data = $cssFileList['vbulletin'];
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
				$vBDefaultCss["$file[name]"] = $file['template'];
			}
		}

		$this->cssBundles = $vBDefaultCss;

		// TODO: Add product xml handling here if we need it.

		return true;
	}
}
