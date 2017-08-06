<?php if (!defined('VB_ENTRY')) die('Access denied.');
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

/*

########## Work in Progress ##########

The idea is to move everything product related from adminfunctions,
and adminfunctions_product into here, so the legacy functions can be deleted.
 */

/**
 * vB_Api_Product
 *
 * @package vBApi
 * @access public
 */
class vB_Api_Product extends vB_Api
{
	private $assertor;

	protected function __construct()
	{
		parent::__construct();

		$this->assertor = vB::getDbAssertor();
	}

	/**
	 * Displays a message.
	 */
	public function message($text, $red = 0, $crlf = 1, $delay = 1)
	{
		if (!$red)
		{
			$line = "<center>$text</center>";
		}
		else
		{
			$line = "<center><font color=\"red\">$text</font></center>";
		}

		echo $line . str_repeat('<br />', $crlf);

		vbflush();
		sleep($delay);
	}

	/**
	 * Disables or deletes a set of products (does not run any uninstall code.
	 */
	public function removeProducts($products, $versions = array(), $echo = false, $disable_only = false, $reason = '')
	{
		if (!$products OR !$this->hasPermission())
		{
			return false;
		}

		if (!$versions)
		{
			$versions = array();
		}

		if ($disable_only)
		{
			$this->assertor->assertQuery(
				'disableProducts',
				array(
					'reason' => $reason,
					'products' => $products,
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_METHOD,
				)
			);
		}
		else
		{
			$first = true;

			foreach ($products as $pid)
			{
				$result = delete_product($pid);

				if ($result AND $echo)
				{
					if ($first)
					{
						$first = false;
						$msg = new vB_Phrase('hooks', 'products_removed');
						$this->message($msg, 1);
					}

					if ($versions[$pid])
					{
						$this->message($versions[$pid]['title'].' - '.$versions[$pid]['version'], 1);
					}
					else
					{
						$this->message($versions[$pid]['title'], 1);
					}
				}
			}
		}
	}

	public static function loadProductXmlListParsed($type = '', $typekey = false)
	{
		$list = self::loadProductXmlList($type, $typekey);
		foreach ($list as $product => $file)
		{
			$xmlobj = new vB_XML_Parser(false, $file);
			$data = $xmlobj->parse();
			$list[$product] = $data;
		}
		return $list;
	}

	/**
	 * Loads an array of all package xml files (optionally of one type).
	 */
	public static function loadProductXmlList($type = '', $typekey = false)
	{
		$rootDir = DIR . DIRECTORY_SEPARATOR . 'includes';
		$packagesDir = DIR . DIRECTORY_SEPARATOR . 'packages';

		$folders = vB_Api_Extensions::getPackages($packagesDir, $rootDir);

		$list = array();

		if ($folders)
		{
			foreach ($folders AS $package)
			{
				if (strrpos($package, DIRECTORY_SEPARATOR))
				{
					$xmlDir = $package . DIRECTORY_SEPARATOR . 'xml' ;
				}
				else
				{
					$xmlDir = $packagesDir . DIRECTORY_SEPARATOR . $package . DIRECTORY_SEPARATOR . 'xml' ;
				}

				$res = self::loadProductXml($xmlDir, $package, $type, $typekey);
				$list = array_merge($list, $res);
			}
		}

		return $list;
	}

	/**
	 * gets the list of xml files in a given folder (and of optional type).
	 */
	private static function loadProductXml($eDir, $package, $xml = '', $typekey = true)
	{
		$folders = array();

		if (is_dir($eDir))
		{
			if ($handle = opendir($eDir))
			{
				while (($file = readdir($handle)) !== false)
				{
					if (substr($file,0,1) != '.')
					{
						list($name, $ext) = explode('.', $file);
						$types = explode('_', $name);
						$type = $types[0];
						if (count($types) > 1)
						{
							$subtype = preg_replace('#[^a-z0-9]#i', '', $types[1]);
						}
						else
						{
							$subtype = 'none';
						}

						if ($ext != 'xml' OR ($xml AND $type != $xml))
						{
							continue;
						}

						if ($type)
						{
							if (!$typekey)
							{
								$folders[] = $eDir . DIRECTORY_SEPARATOR . $file;
							}
							else
							{
								$folders[$subtype] = $eDir . DIRECTORY_SEPARATOR . $file;
							}
						}
						else
						{
							if (!$typekey)
							{
								$folders[$type][] = $eDir . DIRECTORY_SEPARATOR . $file;
							}
							else
							{
								$folders[$type][$subtype] = $eDir . DIRECTORY_SEPARATOR . $file;
							}
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

		return $folders;
	}

	/**
	 * Saves the list of currently installed products into the datastore.
	 */
	public function buildProductDatastore()
	{
		$products = array('vbulletin' => 1);

		$productList = vB::getDbAssertor()->getRows(
			'product',
			array(
				vB_dB_Query::COLUMNS_KEY => array('productid', 'active')
			)
		);

		foreach ($productList AS $product)
		{
			$products[$product['productid']] = $product['active'];
		}

		vB::getDatastore()->build('products', serialize($products), 1);

		vB_Api_Wol::buildSpiderList();

		vB_Api::instanceInternal("Hook")->buildHookDatastore();
	}

	/**
	 * Checks the user is an admin with product/plugin permission.
	 */
	public function hasPermission()
	{
		$userid = vB::getCurrentSession()->get('userid');
		$userContext = vB::getUserContext($userid);
		$allowed = $userContext->hasAdminPermission('canadminproducts');
		unset($userid, $userContext);

		return $allowed ? true : false;
	}
}

/*======================================================================*\
|| ####################################################################
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
