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
 * vB_Api_Content_Video
 *
 * @package vBApi
 * @author ebrown
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Api_Content_Video extends vB_Api_Content_Text
{
	//override in client- the text name
	protected $contenttype = 'vBForum_Video';

	//The table for the type-specific data.
	protected $tablename = array('video', 'text');
	
	//Whether we handle showapproved,approved fields internally or not
	protected $handleSpecialFields = 1;

	protected $providers = array();
	
	protected function __construct()
	{
		parent::__construct();
		$this->library = vB_Library::instance('Content_Video');
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
		
		if ((vB_Api::instanceInternal('node')->fetchAlbumChannel() == $data['parentid']) AND (!vB::getUserContext()->hasPermission('albumpermissions', 'picturefollowforummoderation')))
		{
			$data['approved'] = 0;
			$data['showapproved'] = 0;
		}
		
		return parent::add($data, $options);
	}

	public function getIndexableFromNode($node, $include_attachments = true)
	{
		$indexableContent['title'] = $node['title'];
		$indexableContent['rawtext'] = $node['rawtext'];
		return $indexableContent;
	}

	/**
	 * Get information from video's URL.
	 * This method makes use of bbcode_video table to get provider information
	 * @param $url
	 * @return array|bool Video data. False if the url is not supported or invalid
	 */
	public function getVideoFromUrl($url)
	{
		static $scraped = 0;

		$vboptions = vB::getDatastore()->get_value('options');

		if (!$this->providers)
		{
			$bbcodes = $this->assertor->assertQuery("video_fetchproviders", array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_STORED
			));
			foreach ($bbcodes as $bbcode)
			{
				$this->providers["$bbcode[tagoption]"] = $bbcode;
			}
		}

		if (!empty($this->providers))
		{
			$match = false;
			foreach ($this->providers AS $provider)
			{
				$addcaret = ($provider['regex_url'][0] != '^') ? '^' : '';
				if (preg_match('#' . $addcaret . $provider['regex_url'] . '#si', $url, $match))
				{
					break;
				}
			}
			if ($match)
			{
				if (!$provider['regex_scrape'] AND $match[1])
				{
					$data = array(
						'provider' => $provider['tagoption'],
						'code' => $match[1],
						'url' => $url,
					);
				}
				else if ($provider['regex_scrape'] AND $vboptions['bbcode_video_scrape'] > 0 AND $scraped < $vboptions['bbcode_video_scrape'])
				{
					require_once(DIR . '/includes/functions_file.php');
					$result = fetch_body_request($url);
					if (preg_match('#' . $provider['regex_scrape'] . '#si', $result, $scrapematch))
					{
						$data = array(
							'provider' => $provider['tagoption'],
							'code' => $scrapematch[1],
							'url' => $url,
						);
					}
					$scraped++;
				}
			}

			if (!empty($data))
			{
				return $data;
			}
			else
			{
				return false;
			}
		}

		return false;
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
	
	/**
	 * grabs the thumbnail from og:image meta data
	 * @param url of video
	 * @return url or false
	 */
	public function getVideoThumbnail($url)
	{
		// TODO just grab image from meta without going through unnecessary steps in
		// content_link->parsePage
		$api = Api_InterfaceAbstract::instance();
		$data = $api->callApi('content_link', 'parsePage', array($url));
		
		if ($data['images'])
		{
			$thumbnail = $data['images'];
			// only return the first image. May want to change this later after product audit?
			if (is_array($thumbnail))
			{
				$thumbnail = $thumbnail[0];
			}
			return $thumbnail;
		}
		
		// we should probably have a default placeholder 
		// we can return in case no image is found..
		return false;
	}
}
