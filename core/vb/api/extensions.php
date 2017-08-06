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

/**
 * vB_Api_Extensions
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Extensions extends vB_Api
{
	/**
	 * Contains extensions for all API classes
	 * @var array
	 */
	protected static $extensions;

	/**
	 * stores the packages folder list
	 * @var array
	 */
	protected static $folders = array();

	/**
	 * Array of flags for controller loading
	 * @var array
	 */
	protected static $checked = array();

	/**
	 * Folders loaded flag
	 * @var array
	 */
	protected static $foldersLoaded = false;

	/**
	 * Name of the API module
	 * @var string
	 */
	protected $controller;

	/**
	 * Extension class name
	 * @var string
	 */
	public $extensionClass;

/*
	Default Extension Values
	The should normally be overridded by the each extension class
*/

	// Product id
	protected $product = 'vbulletin';

	// Extension Version
	// Defaults to current version.
	protected $version = '5.0.0';

	// Extension Developer
	protected $developer = 'Internet Brands';

	// Extension Title
	protected $title = 'Extensions Default Title';

	// Minimum required vB version (> 5.0.0 Alpha 1)
	protected $minver = '5.0.0 Alpha 1';

	// Maximum Compatible vB version
	protected $maxver = '5.9.99';

	// Url for extension information (e.g. vb.org thread)
	protected $infourl = 'http://www.vbulletin.com';

	// Version check url
	protected $checkurl = '';

	// Extension Execution Order
	protected $extensionOrder = 10;

	// Auto Install Product XML if product not detected
	protected $AutoInstall = 0;

	// Varname used for passing results from core API or other extensions. All extended methods must use this name.
	protected $resultVarName = 'prevResult';

	// ********************
	// ** STATIC METHODS **
	// ********************

	/**
	 * Builds the class name for an extension using the file name
	 * @param string $extension
	 * @return string
	 */
	private static function getExtensionClass($controller, $product)
	{
		$safeid = preg_replace('#[^a-z0-9]#', '', strtolower($product));
		$c = ucfirst($safeid) . '_Api_' . implode('_', array_map('ucfirst', array_map('strtolower', explode('_',$controller)))) ;

		if (!class_exists($c))
		{
			throw new Exception(sprintf("Cannot find class %s", htmlspecialchars($c)));
		}

		if (!is_subclass_of($c, 'vB_Api_Extensions'))
		{
			throw new Exception(sprintf('Class %s is not a subclass of vB_Api_Extensions', htmlspecialchars($c)));
		}

		return $c;
	}

	/**gets the list of packages (folder names).
	* **/
	public static function getPackages($packagesDir, $folders = array())
	{
		if (!is_array($folders))
		{
			$folders = array($folders);
		}

		if (is_dir($packagesDir))
		{
			if ($handle = opendir($packagesDir))
			{
				$prefix = $packagesDir . DIRECTORY_SEPARATOR;

				while (($file = readdir($handle)) !== false)
				{
					if (substr($file,0,1) != '.' and filetype($prefix . $file) == 'dir')
					{
						$folders[] = $file;
					}
				}

				closedir($handle);
			}
			else
			{
				throw new Exception("Could not open $packagesDir");
			}
		}

		return $folders;
	}

	/**Check if correct product, and compatible with current vB version.
	* **/
	private static function isCompatible($class, $package, $options)
	{
		if (strtolower($class->product) != strtolower($package))
		{
			return false;
		}

		$MinOK = vB_Library_Functions::isNewerVersion($options['templateversion'], $class->minver, true);
		$MaxOK = vB_Library_Functions::isNewerVersion($class->maxver, $options['templateversion'], true);

		return ($MinOK AND $MaxOK);
	}

	/**	Check if product is enabled OR not installed.
	* **/
	private static function isEnabled($product, $products)
	{
		if(isset($products[$product]) AND $products[$product] == 0)
		{
			return false;
		}

		return true;
	}

	/**	Check if product is installed and install if option is set.
	* **/
	private static function autoInstall($product, $class, $xmlDir = '', $products)
	{
		if ($class->AutoInstall AND !isset($products[$product]))
		{
			vB_Library_Functions::installProduct($product, $xmlDir);
		}
	}

	/**load the actual extension for a package / $controller.
	* **/
	private static function loadExtension($packagesDir, $package, $controller, $options)
	{
		$xmlDir = $packagesDir . DIRECTORY_SEPARATOR . $package . DIRECTORY_SEPARATOR . 'xml' ;
		$apiDir = $packagesDir . DIRECTORY_SEPARATOR . $package . DIRECTORY_SEPARATOR . 'api' ;

		if (!is_dir($apiDir))
		{
			return;
		}

		$file = $apiDir . DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR, strtolower($controller)) . '.php';

		if (file_exists($file))
		{
			$products = vB::getDatastore()->getValue('products');

			$eClass = self::getExtensionClass($controller, $package);

			$class = new $eClass($controller);
			$enabled = self::isEnabled($package, $products);
			$compatible = self::isCompatible($class, $package, $options);

			if ($enabled AND $compatible)
			{
				self::$extensions[$controller][] = $class;

				self::autoInstall($package, $class, $xmlDir, $products);
			}

			unset($class);
		}
	}

	/**
	 * Loads extensions for a given controller
	 * @param string $controller
	 */
	private static function loadExtensions($controller)
	{
		$options = vB::getDatastore()->getValue('options');

		if (!$options['enablehooks'] OR defined('DISABLE_HOOKS'))
		{
			return false;
		}

		$packagesDir = DIR . DIRECTORY_SEPARATOR . 'packages';

		if (!isset(self::$checked[$controller]))
		{
			if (!self::$foldersLoaded)
			{
				self::$foldersLoaded = true;
				self::$folders = self::getPackages($packagesDir);
			}

			if (self::$folders)
			{
				foreach (self::$folders AS $package)
				{
					self::loadExtension($packagesDir, $package, $controller, $options);
				}

				if (isset(self::$extensions[$controller]))
				{
					uasort(self::$extensions[$controller], array('self', 'arraySort'));
				}
			}

			self::$checked[$controller] = true;
		}
	}

	/**
	 * Loads an array of all extensions
	 */
	public static function loadAllExtensions()
	{
		$options = vB::getDatastore()->getValue('options');
		$products = vB::getDatastore()->getValue('products');
		$packagesDir = DIR . DIRECTORY_SEPARATOR . 'packages';

		$folders = self::getPackages($packagesDir);

		$list = array();

		if ($folders)
		{
			foreach ($folders AS $package)
			{
				$apiDir = $packagesDir . DIRECTORY_SEPARATOR . $package . DIRECTORY_SEPARATOR . 'api' ;
				$res = self::loadExtensionList($apiDir, $package, $options, $products);
				$list = array_merge($list, $res);
			}
		}

		return $list;
	}

	/**gets the class data for a given extension file.
	* **/
	private static function loadExtensionListFile($eDir, $file, $package, $options, $products, $prefix)
	{
		$check = $eDir . DIRECTORY_SEPARATOR . $file;

		if (is_dir($check))
		{
			return array('status' => 'folder', 'folderid' => $check);
		}
		else
		{
			list($controller) = explode('.', $file); // remove .php
			$controller = $prefix . $controller;

			$eClass = self::getExtensionClass($controller, $package);

			$class = new $eClass($controller);
			$enabled = self::isEnabled($package, $products);
			$compatible = self::isCompatible($class, $package, $options);
			$hasproduct = (isset($products[$class->product]));

			$extension = array(
				'class' => $class->extensionClass,
				'product' => $class->product,
				'version' => $class->version,
				'developer' => $class->developer,
				'title' => $class->title,
				'minver' => $class->minver,
				'maxver' => $class->maxver,
				'order' => $class->extensionOrder,
				'infourl' => $class->infourl,
				'controller' => $class->controller,
				'enabled' => $enabled,
				'compatible' => $compatible,
				'hasproduct' => $hasproduct,
				'package' => $package,
			);
		}

		return array('status' => 'file', 'extension' => $extension);
	}

	/**gets the list of api classes in a given folder.
	* **/
	private static function loadExtensionListFolder($eDir, $package, $options, $products, &$folders, $prefix = '')
	{
		if (is_dir($eDir))
		{
			if ($handle = opendir($eDir))
			{
				while (($file = readdir($handle)) !== false)
				{
					if (substr($file,0,1) != '.')
					{
						$result = self::loadExtensionListFile($eDir, $file, $package, $options, $products, $prefix);

						if ($result['status'] == 'file')
						{
							$folders[] = $result['extension'];
						}
						else if ($result['status'] == 'folder')
						{
							// Directory ......
							self::loadExtensionListFolder($result['folderid'], $package, $options, $products, $folders, $prefix . $file . '_');
						}
						else
						{
							throw new Exception("Invalid status for $eDir, $file");
						}
					}
				}

				closedir($handle);
			}
			else
			{
				throw new Exception("Could not open $eDir");
			}
		}
	}

	/**gets the list of api classes in a given package.
	* **/
	private static function loadExtensionList($eDir, $package, $options, $products)
	{
		$folders = array();

		self::loadExtensionListFolder($eDir, $package, $options, $products, $folders);

		return $folders;
	}

	/** extensionOrder
	 * Returns comparison value for array sorter
	 * @param mixed value 1
	 * @param mixed value 2
	 * @return int -1, 0 or 1
	 */
	private static function arraySort($a, $b)
	{
	    return ($a->extensionOrder == $b->extensionOrder ? 0 : ($a->extensionOrder > $b->extensionOrder ? 1 : -1));
	}

	/**
	 * Returns all the extensions for a controller.
	 * @param string $controller
	 * @return array The extensions.
	 */
	public static function getExtensions($controller, $options = array())
	{
		if (!$options)
		{
			$options = vB::getDatastore()->getValue('options');
		}

		self::loadExtensions($controller, $options);

		if (isset(self::$extensions[$controller]) AND !empty(self::$extensions[$controller]))
		{
			return self::$extensions[$controller];
		}
		else
		{
			return false;
		}
	}

	// ************************
	// ** NON-STATIC METHODS **
	// ************************

	protected function __construct($controller)
	{
		$this->controller = $controller;
		$this->extensionClass = get_class($this);
	}

	protected function getExtensionOrder()
	{
		// Extensions with no order should be set high (so run last)
		return isset($this->extensionOrder) ? $this->extensionOrder : 999 ;
	}

	/**
	 * @see vB_Api::callNamed
	 * @param mixed $current
	 * @param string $method -- the name of the method to call
	 * @param array $args -- The list of args to call.  This is a name => value map that will
	 *   be matched up to the names of the API method.  Order is not important.  The names are
	 *   case sensitive.
	 * @return mixed
	 */
	public function callNamed()
	{
		// since the parent method has different arguments,
		// we need to do this to avoid strict standards notice
		list($current, $method, $args) = func_get_args();

		if (!is_callable(array($this, $method)))
		{
			/* if the method does not exist then dont check anything,
			 just return the current value. */
			return $current;
		}

		// Check if the result varname is present
		$foundResultVarName = false;
		$reflection = new ReflectionMethod($this, $method);
		foreach($reflection->getParameters() as $param)
		{
			if($param->getName() == $this->resultVarName)
			{
				$foundResultVarName = true;
				break;
			}
		}
		if (!$foundResultVarName)
		{
			// The method is not using the result varname, so it's better to ignore it and return previous result
			// todo: log this error somewhere?
			return $current;
		}

		$args[$this->resultVarName] = $current;
		return parent::callNamed($method, $args);
	}


	// ************************************************
	// ** HELPER METHODS - Mostly useful for testing **
	// ************************************************

	/**clears the loaded folders & extensions.
	* **/
	public static function resetExtensions()
	{
		self::clearFolders();
		self::clearExtensions();
	}

	/**
	 * Returns all the currently loaded extension.
	 */
	public static function getExtensionList()
	{
		return self::$extensions;
	}

	/**clears the loaded extensions.
	* **/
	private static function clearExtensions()
	{
		self::$checked = array();
		self::$extensions = array();
	}

	/**clears the loaded folders.
	* **/
	private static function clearFolders()
	{
		self::$folders = array();
		self::$foldersLoaded = false;
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
