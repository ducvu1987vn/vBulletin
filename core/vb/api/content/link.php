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


/**
 * vB_Api_Content_link
 *
 * @package vBApi
 * @author xiaoyu
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Api_Content_Link extends vB_Api_Content_Text
{
	//override in client- the text name
	protected $contenttype = 'vBForum_Link';

	//The table for the type-specific data.
	protected $tablename = array('link', 'text');

	protected $providers = array();


	/** normal protector- protected to prevent direct instantiation **/
	protected function __construct()
	{
		parent::__construct();
		$this->library = vB_Library::instance('Content_Link');
	}

	/*** Permanently deletes a node
	 *	@param	integer	The nodeid of the record to be deleted
	 *
	 *	@return	boolean
	 ***/
	public function delete($nodeid)
	{
		return $this->library->delete($nodeid);
	}

	/*** Adds a new node.
	 *
	 *	@param	mixed		Array of field => value pairs which define the record.
	 *  @param	array		Array of options for the content being created.
	 *						Available options include:
	 *
	 * 	@return	integer		the new nodeid
	 ***/
	public function add($data, $options = array())
	{
		vB_Api::instanceInternal('hv')->verifyToken($data['hvinput'], 'post');
		return parent::add($data, $options);
	}

	/**
	 * Parse HTML Page and get its title/meta and images
	 *
	 * @param $url URL of the Page
	 * @return array
	 */
	public function parsePage($url)
	{
		// Validate url
		if (!preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url))
		{
			throw new vB_Exception_Api('upload_invalid_url');
		}

		if (($urlparts = vB_String::parseUrl($url)) === false)
		{
			throw new vB_Exception_Api('upload_invalid_url');
		}

		// Try to fetch the url
		$vurl = new vB_vURL();
		$vurl->set_option(VURL_URL, $url);
		// Use IE8's User-Agent for the best compatibility
		$vurl->set_option(VURL_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0)');
		$vurl->set_option(VURL_RETURNTRANSFER, 1);
		$vurl->set_option(VURL_CLOSECONNECTION, 1);
		$vurl->set_option(VURL_FOLLOWLOCATION, 1);

		$page = $vurl->exec();

		return $this->extractData($page, $urlparts);
	}

	protected function extractData($text, $urlparts)
	{
		if (!$text)
		{
			// Don't throw exception here. Just return empty results
			return array(
				'title' => '',
				'meta' => '',
				'images' => null,
			);
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		if (!$dom->loadHTML($text))
		{
			// Invalid HTML. return empty results.
			return array(
				'title' => '',
				'meta' => '',
				'images' => null,
			);
		}

		// Get title
		$title = '';
		if ($titlenode = $dom->getElementsByTagName("title")->item(0))
		{
			$title = $titlenode->nodeValue;
		}
		if (!$title)
		{
			// If no title, try to get meta open graph title
			try
			{
				foreach ($dom->getElementsByTagName("meta") as $metanode)
				{
					if ($metanode->hasAttributes())
					{
						$metaItem = $metanode->attributes->getNamedItem('property');
						if (!empty($metaItem))
						{
							if ($metaItem->nodeValue == 'og:title')
							{
								$title = $metanode->attributes->getNamedItem('content')->nodeValue;
								break;
							}
						}
					}
				}
			}
			catch(exception $e)
			{	}//nothing we can do- just continue;
		}
		// Get Meta
		$meta = '';
		foreach ($dom->getElementsByTagName("meta") as $metanode)
		{
			if ($metanode->hasAttributes())
			{
				try
				{
					$metaItem = $metanode->attributes->getNamedItem('name');
					if (!empty($metaItem))
					{
						if ($metaItem->nodeValue == 'description')
						{
							$meta = $metanode->attributes->getNamedItem('content')->nodeValue;
							break;
						}
					}
				}
				catch(exception $e)
				{	}//nothing we can do- just continue;
			}
		}

		if (!$meta)
		{
			// If no meta description, try to get meta open graph og:description
			try
			{
				foreach ($dom->getElementsByTagName("meta") as $metanode)
				{
					if ($metanode->hasAttributes())
					{
						$metaItem = $metanode->attributes->getNamedItem('property');
						if (!empty($metaItem))
						{
							if ($metaItem->nodeValue == 'og:description')
							{
								$meta = $metanode->attributes->getNamedItem('content')->nodeValue;
								break;
							}
						}
					}
				}
			}
			catch(exception $e)
			{	}//nothing we can do- just continue;
		}

		if (!$meta)
		{
			// If no meta og:description, try to get meta keywords
			try
			{
				foreach ($dom->getElementsByTagName("meta") as $metanode)
				{
					if ($metanode->hasAttributes())
					{
						$metaItem = $metanode->attributes->getNamedItem('name');
						if (!empty($metaItem))
						{
							if ($metaItem->nodeValue == 'keywords')
							{
								$meta = $metanode->attributes->getNamedItem('content')->nodeValue;
								break;
							}
						}
					}
				}
			}
			catch(exception $e)
			{	}//nothing we can do- just continue;
		}

		// Get baseurl
		$baseurl = '';
		if ($basenode = $dom->getElementsByTagName("base")->item(0))
		{
			if ($basenode->hasAttributes())
			{
				$item = $basenode->attributes->getNamedItem('href');

				if (!empty($item))
				{
					$baseurl = $item->nodeValue;
				}
			}
		}
		if (!$baseurl)
		{
			// We assume that the baseurl is domain+path of $url
			$baseurl = $urlparts['scheme'] . '://';
			if (!empty($urlparts['user']))
			{
				$baseurl .= $urlparts['user'] . ':' . $urlparts['pass'] . '@';
			}
			$baseurl .= $urlparts['host'];
			if (!empty($urlparts['port']))
			{
				$baseurl .= ':' . $urlparts['port'];
			}

			if (!empty($urlparts['path']))
			{
				$path = $urlparts['path'];
				// Remove filename from path
				$pos = strrpos($path, '/');
				if ($pos !== false AND $pos !== 0)
				{
					$path = substr($path, 0, $pos);
				}
				$baseurl .= $path;
			}
		}

		$baseurl = rtrim($baseurl, '/');


		// Get images
		$imgurls = array();

		// We need to add og:image if exists
		try
		{
			foreach ($dom->getElementsByTagName("meta") as $metanode)
			{
				if ($metanode->hasAttributes())
				{
					$metaItem = $metanode->attributes->getNamedItem('property');
					if (!empty($metaItem))
					{
						if ($metaItem->nodeValue == 'og:image')
						{
							$imgurls[] = $metanode->attributes->getNamedItem('content')->nodeValue;
							// Don't break here. Because Open Graph allows multiple og:image tags
						}
					}
				}
			}
		}
		catch(exception $e)
		{	}//nothing we can do- just continue;


		foreach ($dom->getElementsByTagName("img") as $imgnode)
		{
			if ($imgnode->hasAttributes() && $imgnode->attributes->getNamedItem('src'))
			{
				if ($imgurl = $imgnode->attributes->getNamedItem('src')->nodeValue)
				{
					$imgurls[] = $imgurl;
				}
			}
		}

		foreach ($imgurls as &$imgurl)
		{
			if (!$imgurl)
			{
				unset($imgurl);
			}

			// protocol-relative URL (//domain.com/logo.png)
			if (preg_match('|^//[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $imgurl))
			{
				// We add url scheme to the url
				$imgurl = $urlparts['scheme'] . ':' . $imgurl;
			}

			// relative url? make it absolute
			$imgurl = $this->rel2abs($imgurl, $baseurl);
		}

		$imgurls = array_unique($imgurls);

		return array(
			'title' => $title,
			'meta' => $meta,
			'images' => $imgurls,
		);
	}

	/*** This returns a link image by nodeid
	 *
	 *	@param	int		nodeid
	 * 	@param	thumb	boolean- should I render the thumbnail?
	 *
	 * 	@return	mixed	array of filedataid,filesize, extension, filedata, htmltype.
	 ***/
	public function fetchImageByLinkId($linkid, $type = vB_Api_Filedata::SIZE_FULL)
	{
		$link = $this->getContent($linkid);
		$link = $link[$linkid];
		if (empty($link))
		{
			return array();
		}
		//First validate permission.
		if ($link['userid'] !=  vB::getUserContext()->fetchUserId())
		{
			if (!$link['showpublished'])
			{
				if (!vB::getUserContext()->hasChannelPermission('moderatorpermissions', 'caneditposts', $linkid, false, $link['parentid']))
				{
					throw new vB_Exception_Api('no_permission');
				}
			}
			else if (!vB::getUserContext()->getChannelPermission('forumpermissions', 'canview', $linkid, false, $link['parentid']))
			{
				throw new vB_Exception_Api('no_permission');
			}

		}
		//if we got here, this user is authorized to see this. image.
		$params = array('filedataid' => $link['filedataid'], 'type' => $type);
		$image = vB::getDbAssertor()->getRow('vBForum:getFiledataContent', $params);

		if (empty($image))
		{
			return false;
		}

		$imageHandler = vB_Image::instance();
		return $imageHandler->loadFileData($image, $type, true);
	}

	/**
	 * Function to convert relative URL to absolute given a base URL
	 * From http://bsd-noobz.com/blog/php-script-for-converting-relative-to-absolute-url
	 *
	 * @param   string   the relative URL
	 * @param   string   the base URL
	 * @return  string   the absolute URL
	 */
	protected function rel2abs($rel, $base)
	{
		if (vB_String::parseUrl($rel, PHP_URL_SCHEME) != '')
		{
			return $rel;
		}
		else if ($rel[0] == '#' || $rel[0] == '?')
		{
			return $base.$rel;
		}

		$parsed_base = vB_String::parseUrl($base);
		$abs = (($rel[0] == '/' OR empty($parsed_base['path'])) ? '' : preg_replace('#/[^/]*$#', '', $parsed_base['path']))."/$rel";
		$re  = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');

		for ($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n));
		return $parsed_base['scheme'].'://'.$parsed_base['host'].str_replace('../', '', $abs);
	}

	/**
	 * Adds content info to $result so that merged content can be edited.
	 * @param array $result
	 * @param array $content
	 */
	public function mergeContentInfo(&$result, $content)
	{
		if (vb::getUserContext()->getChannelPermission('forumpermissions', 'canviewthreads', $result['nodeid']))
		{
			$this->library->mergeContentInfo($result, $content);
		}
	}

	/**
	 * Performs the merge of content and updates the node.
	 * @param type $data
	 * @return type
	 */
	public function mergeContent($data)
	{
		return $this->library->mergeContent($data);
	}
	
	/** Does basic input cleaning for input data
	 	@param	mixed	array of fieldname => data pairs

	 	@return	mixed	the same data after cleaning.
	 */
	public function cleanInput(&$data, $nodeid = false)
	{
		parent::cleanInput($data, $nodeid);
		
		$cleaner = vB::getCleaner();
		
		if (isset($data['filedataid']))
		{
			$data['filedataid'] = intval($data['filedataid']);
		}
		
		if (isset($data['url']))
		{
			$data['url'] = $cleaner->clean($data['url'], vB_Cleaner::TYPE_STR);
		}
		
		foreach (array('url_title', 'meta') as $fieldname)
		{
			if (isset($data[$fieldname]))
			{
				$data[$fieldname] = $cleaner->clean($data[$fieldname], vB_Cleaner::TYPE_NOHTML);
			}
		}
	}
}
