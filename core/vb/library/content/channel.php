<?php
if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
   || #################################################################### ||
   || # vBulletin 5.0.0
   || # ---------------------------------------------------------------- # ||
   || # Copyright  2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
   || # This file may not be redistributed in whole or significant part. # ||
   || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
   || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
   || #################################################################### ||
   \*======================================================================*/


/**
 * vB_Api_Content_Channel
 *
 * @package vBApi
 * @author ebrown
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class vB_Library_Content_Channel extends vB_Library_Content
{
	//override in client- the text name
	protected $contenttype = 'vBForum_Channel';

	//The table for the type-specific data.
	protected $tablename = 'channel';

	protected function buildChannelOptions($nodeid, $options)
	{
		if ($nodeid)
		{
			$prior = vB::getDbAssertor()->getRow('vBForum:channel', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array('nodeid' => $nodeid)
			));
		}

		$value = ($nodeid AND $prior) ? $prior['options'] : 0;
		$forumOptions = vB::getDatastore()->getValue('bf_misc_forumoptions');

		foreach($options AS $name => $on)
		{
			if (isset($forumOptions[$name]))
			{
				if ($on)
				{
					$value = $value | $forumOptions[$name];
				}
				else
				{
					$value = $value & (~$forumOptions[$name]);
				}
			}
		}

		return $value;
	}

	/*** Adds a new channel.
	 *
	 *	@param	mixed		Array of field => value pairs which define the record.
	 *  @param	array		Array of options for the content being created.
	 *						Available options include:
	 *							- nodeonly:	Boolean indicating whether extra info for channel should be created (page, routes, etc). Used for importing channels
	 *
	 * 	@return	integer		the new nodeid
	 ***/
	public function add($data, array $options = array('nodeonly' => false))
	{
		$options += array('skipDupCheck' => true);

		// VBV-833: we allow interfaces to not specify a parent. Main channel should be used in that case
		if (!isset($data['parentid']) OR $data['parentid'] <= 0)
		{
			$data['parentid'] = vB::getDbAssertor()->getField('vBForum:channel', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array('guid' => vB_Channel::DEFAULT_CHANNEL_PARENT)
			));
		}
		else
		{
			// if we are not using the default channel parent, we need to check for pagetemplates
			if (!isset($data['templates']))
			{
				$parent = vB::getDbAssertor()->getRow('vBForum:channel', array(
					vB_dB_Query::COLUMNS_KEY => array('nodeid', 'guid'),
					vB_dB_Query::CONDITIONS_KEY => array('nodeid' => $data['parentid'])
				));

				switch($parent['guid'])
				{
					case vB_Channel::DEFAULT_SOCIALGROUP_PARENT:
						// This is done only when saving from activity stream configuration, once it is removed we can get rid of this
						$data['templates']['vB5_Route_Channel'] = vB_Page::getSGCategoryPageTemplate();
						$data['templates']['vB5_Route_Conversation'] = vB_Page::getSGCategoryConversPageTemplate();
						$data['category'] = 1;
						break;
					default:
						// use inherited from parent channel
						break;
				}
			}
		}

		if (!isset($data['guid'])) // when importing channels, we already have a guid
		{
			// creating guid
			$data['guid'] = vB_Xml_Export_Channel::createGUID($data);
		}

		// parse options array
		if (isset($data['options']))
		{
			if (is_array($data['options']))
			{
				$value = $this->buildChannelOptions(0, $data['options']);

				if ($value !== FALSE)
				{
					$data['options'] = $value;
				}
				else
				{
					// do not update field
					unset($data['options']);
				}
			}
			else
			{
				// should we accept raw ints as updates?
				unset($data['options']);
			}
		}

		if (empty($data['urlident']) AND !empty($data['title']))
		{
			$data['urlident'] = $this->getUniqueUrlIdent($data['title']);
		}
		
		if (!isset($options['nodeonly']) || !$options['nodeonly'])
		{
			// if we are going to create pages, verify that prefix/regex generated is valid BEFORE creating the node
			vB5_Route_Channel::validatePrefix($data);
		}

		$nodeid = parent::add($data, $options);
		$parentPageIds = array();

		if (!isset($options['nodeonly']) || !$options['nodeonly'])
		{
			$this->createChannelPages($nodeid, $data);
		}

		$this->nodeApi->clearCacheEvents(array($nodeid, $data['parentid']));
		//and announce that the cached channel structure has changed.
		vB_Cache::instance()->event('vB_ChannelStructure_chg');
		
		if (!defined('VB_AREA') OR !in_array(VB_AREA, array('Install', 'Upgrade')))
		{
			vB::getUserContext()->rebuildGroupAccess();
			vB::getUserContext()->clearChannelPermissions();
		}

		return $nodeid;
	}

	public function update($nodeid, $data)
	{
		// parse options array
		if (isset($data['options']))
		{
			if (is_array($data['options']))
			{
				$data['options'] = $this->buildChannelOptions($nodeid, $data['options']);
			}
			else
			{
				// should we accept raw ints as updates?
				unset($data['options']);
			}
		}

		$success = parent::update($nodeid, $data);

		// Update page title for the channel
		if (!empty($data['title']))
		{
			$existingRouteId = (int) vB::getDbAssertor()->getField('vBForum:node',
				array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					vB_dB_Query::COLUMNS_KEY => array('routeid'),
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'nodeid', 'value' => $nodeid, 'operator' => vB_dB_Query::OPERATOR_EQ),
					),
				)
			);
			if ($existingRouteId > 0)
			{
				$existingPage = vB::getDbAssertor()->getRow('page',
					array(
						vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
						vB_dB_Query::CONDITIONS_KEY => array(
							array('field' => 'routeid', 'value' => $existingRouteId, 'operator' => vB_dB_Query::OPERATOR_EQ),
						),
					)
				);

				vB::getDbAssertor()->update('page', array('title' => $data['title']), array('pageid' => $existingPage['pageid']));
				vB::getDbAssertor()->update('page', array('title' => $data['title']), array('parentid' => $existingPage['pageid']));
			}
		}

		vB_Cache::instance()->event('vB_ChannelStructure_chg');
		vB::getUserContext()->rebuildGroupAccess();
		return $success;
	}

	/*** Permanently deletes a channel
	 *	@param	integer	The nodeid of the record to be deleted
	 *
	 *	@return	boolean
	 ***/
	function delete($nodeid)
	{
		if (empty($nodeid))
		{
			return false;
		}
		// prevent deleting of top level channels
		if (in_array($nodeid, vB_Api::instanceInternal('content_channel')->fetchTopLevelChannelIds()))
		{
			throw new vB_Exception_Api('cant_delete_top_level');
		}
		// deleting the children.
		$deleteIds = vB::getDbAssertor()->getRows('vBForum:closure', array(
				vB_dB_Query::COLUMNS_KEY => array('child'),
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'parent', 'value' => $nodeid, 'operator' => vB_dB_Query::OPERATOR_EQ),
					array('field' => 'depth', 'value' => 0, 'operator' => vB_dB_Query::OPERATOR_GT)
				)
			),
			array('field' => 'child', 'direction' => vB_dB_Query::SORT_DESC),
			'child'
		);

		$nodes = vB_Api::instance('node')->getNodes(array_keys($deleteIds));
		foreach ($nodes as $node)
		{
			vB_Library::instance('content_' . vB_Types::instance()->getContentTypeClass($node['contenttypeid']))->delete($node['nodeid']);
		}

		// deleting the node
		$success = parent::delete($nodeid);

		// delete pages and routes
		$this->deleteChannelPages($nodeid);

		vB_Cache::instance()->event('vB_ChannelStructure_chg');
		vB::getUserContext()->rebuildGroupAccess();
		return $success;
	}

	/*** Returns the node content as an associative array
	 *	@param	integer	The id in the primary table
	 *	@param	mixed	array of permissions request- (array group, permission)

	 *	@return	int
	 ***/
	public function getFullContent($nodeids, $permissions = false)
	{
		$result = parent::getFullContent($nodeids, $permissions);

		if ($result)
		{
			$forumOptions = vB::getDatastore()->getValue('bf_misc_forumoptions');
			foreach ($result AS $key => $channel)
			{
				$options = array();
				foreach($forumOptions AS $name => $bitfield)
				{
					$options[$name] = ($channel['options'] & $bitfield) ? 1 : 0;
				}

				$result[$key]['options'] = $options;
			}
		}

		return $result;
	}

	/**
	 * Toggles the channel between acting like a forum (can have threads in it)
	 * and acting like a category (can only have subchannels in it)
	 *
	 * @param bool $makeCategory
	 * @param int $nodeId
	 * @param bool $force - If true, it will force page recreation even if the category field matches
	 * @return boolean
	 * @throws vB_Exception_Api
	 */
	public function switchForumCategory($makeCategory, $nodeId, $force = false)
	{
		$node = vB_Library::instance('node')->getNodeContent($nodeId);
		if (empty($node))
		{
			throw new vB_Exception_Api('invalid_request');
		}
		else
		{
			$node = $node[$nodeId];
			if ( !$force AND
				(($makeCategory AND $node['category'] == 1) OR (!$makeCategory AND $node['category'] == 0)) )
			{
				// we don't need to do anything
				return true;
			}
		}

		$data = $pageData = array();

		if ($makeCategory)
		{
			if (!$force)
			{
				// check that the node doesn't have any content other than subchannels
				$count = (int) vB::getDbAssertor()->getField('vBForum:getDirectContentCount', array(
					'parentid' => $nodeId,
					'excludeTypes' => array($this->contenttypeid),
				));

				if ($count > 0)
				{
					throw new vB_Exception_Api('cannot_convert_channel');
				}
			}
		
			$data['category'] = 1;
			$data['options']['cancontainthreads'] = 0;
		}
		else
		{
			$data['category'] = 0;
			$data['options']['cancontainthreads'] = 1;
		}

		// Change the channel to a category or vice-versa
		$response = $this->update($nodeId, $data);

		// If that was successful, modify the pages and routes for the channel/category
		if ($response)
		{
			// Categories have one page and one route (for the channel)
			// Channels/forums have two pages and two routes (one for the channel and one for conversations in the channel)

			if ($makeCategory)
			{
				// Changing from a forum to category-- delete
				// the "Conversation" page and route
				// leaving the "Channel" page and route
				$this->deleteChannelPages($nodeId, false);
			}

			// The "Channel" page and route are already there.
			// If making this a category, the channel page will be updated.
			// If making this a forum, the channel page will be updated and
			// the "Conversation" page & route will be created
			$pageData['title'] = $node['title'];
			$pageData['parentid'] = $node['parentid'];
			$pageData['category'] = $makeCategory ? 1 : 0;
			$this->createChannelPages($nodeId, $pageData);

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Creates pagetemplate, pages and routes for a channel
	 * @param int nodeid
	 * @param array $data - Must contain the following fields:
	 *		- templates
	 *			- vB5_Route_Channel
	 *			- vB5_Route_Conversation (optional)
	 *		- parentid
	 *		- title
	 *		- page_parentid
	 */
	protected function createChannelPages($nodeid, $data)
	{
		$db = vB::getDbAssertor();

		// default to creating a forum/channel, not a category
		if (!isset($data['category']))
		{
			$data['category'] = 0;
		}

		if (
			!isset($data['templates'])
			||
			!isset($data['templates']['vB5_Route_Channel'])
			||
			!isset($data['templates']['vB5_Route_Conversation'])
		)
		{
			$parentChannel = $this->getContent($data['parentid']);
			if (isset($parentChannel[$data['parentid']]))
			{
				$parentChannel = $parentChannel[$data['parentid']];
			}
			if ($parentChannel['category'] != $data['category'])
			{
				// we cannot inherit the templates, use the default ones
				if ($data['category'] > 0)
				{
					$templates['vB5_Route_Channel'] = vB_Page::getCategoryChannelPageTemplate();
				}
				else
				{
					$templates['vB5_Route_Channel'] = vB_Page::getChannelPageTemplate();
					$templates['vB5_Route_Conversation'] = vB_Page::getConversPageTemplate();
				}
			}
			else
			{
				// Get page templates used by parent
				$templates = array();
				$parentRoutes = $db->assertQuery('routenew', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'class', 'value' => array('vB5_Route_Channel', 'vB5_Route_Conversation')),
						array('field' => 'contentid', 'value' => $data['parentid']),
						array('field' => 'redirect301', 'operator' =>vB_dB_Query::OPERATOR_ISNULL)
					)
				));
				$routeInfo = array();
				foreach($parentRoutes AS $parentRoute)
				{
					$args = unserialize($parentRoute['arguments']);
					$routeInfo[$parentRoute['class']] = $args['pageid'];
				}
				$parentPages = $db->assertQuery('page', array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'pageid', 'value' => array_values($routeInfo))
					)
				));
				foreach($parentPages as $parentPage)
				{
					foreach($routeInfo AS $class => $pageId)
					{
						if ($pageId == $parentPage['pageid'])
						{
							// don't use template from forum homepage
							if (($class == 'vB5_Route_Channel') AND ($parentPage['pagetemplateid'] == 1))
							{
								$masterTemplate = vB::getDbAssertor()->getRow('pagetemplate', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
									'guid' => vB_Page::TEMPLATE_CHANNEL));
								$templates[$class] = $masterTemplate['pagetemplateid'];
							}
							else
							{
								$templates[$class] = $parentPage['pagetemplateid'];
							}
							$parentPageIds[$class] = $pageId;
						}
					}
				}
			}
		}
		else
		{
			$templates = $data['templates'];
			unset($data['templates']);
		}

		// check if the main channel page already exists
		$existingRouteId = (int) vB::getDbAssertor()->getField('vBForum:node',
			array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::COLUMNS_KEY => array('routeid'),
				vB_dB_Query::CONDITIONS_KEY => array(
					array('field' => 'nodeid', 'value' => $nodeid, 'operator' => vB_dB_Query::OPERATOR_EQ),
				),
			)
		);
		if ($existingRouteId > 0)
		{
			$existingPage = vB::getDbAssertor()->getRow('page',
				array(
					vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
					vB_dB_Query::CONDITIONS_KEY => array(
						array('field' => 'routeid', 'value' => $existingRouteId, 'operator' => vB_dB_Query::OPERATOR_EQ),
					),
				)
			);
		}
		else
		{
			$existingPage = array();
		}

		if (empty($existingPage))
		{
			// Create main channel page
			$page['pagetemplateid'] = $templates['vB5_Route_Channel'];
			$page['title'] = $data['title'];
			$page['pagetype'] = vB_Page::TYPE_CUSTOM;
			$page['parentid'] = isset($data['page_parentid']) ? $data['page_parentid'] : (isset($parentPageIds['vB5_Route_Channel']) ? $parentPageIds['vB5_Route_Channel'] : 0);
			$pageid = $db->insert('page', $page);
			if (is_array($pageid))
			{
				$pageid = (int) array_pop($pageid);
			}

			// Create route for main channel page
			$route_data = array(
				'nodeid' => $nodeid,
				'pageid' => $pageid,
			);

			if (!empty($data['routeguid']))
			{
				$route_data['guid'] = $data['routeguid'];
			}

			if (!empty($data['urlident']))
			{
				$route_data['urlident'] = $data['urlident'];
			}

			$channelRouteId = vB_Api::instanceInternal('route')->createRoute('vB5_Route_Channel', $route_data);
			if (is_array($channelRouteId))
			{
				$channelRouteId = (int) array_pop($channelRouteId);
			}

			$db->update('vBForum:node', array('routeid' => $channelRouteId), array('nodeid' => $nodeid));
			$db->update('page', array('routeid' => $channelRouteId), array('pageid' => $pageid));
		}
		else
		{
			//Update the existing main channel page
			$page['pagetemplateid'] = $existingPage['pagetemplateid'];
			$page['title'] = $data['title'];
			$page['pagetype'] = $existingPage['pagetype'];
			$page['parentid'] = isset($data['page_parentid']) ? $data['page_parentid'] : (isset($parentPageIds['vB5_Route_Channel']) ? $parentPageIds['vB5_Route_Channel'] : 0);

			$pageid = $existingPage['pageid'];
			$db->update('page', $page, array('pageid' => $pageid));
		}

		vB_Cache::instance(vB_Cache::CACHE_FAST)->event("nodeChg_$nodeid");
		vB_Cache::instance()->event("nodeChg_$nodeid");

		if ($data['category'] == 0 AND isset($templates['vB5_Route_Conversation']) AND !empty($templates['vB5_Route_Conversation']))
		{
			// Create the conversation page
			$page['pagetemplateid'] = $templates['vB5_Route_Conversation'];
			$page['title'] = $data['title'];
			$page['pagetype'] = vB_Page::TYPE_DEFAULT;
			$page['parentid'] = $pageid;
			$pageid = $db->insert('page', $page);
			if (is_array($pageid))
			{
				$pageid = (int) array_pop($pageid);
			}

			// Create route for conversation page
			$conversationRouteId = vB_Api::instanceInternal('route')->createRoute('vB5_Route_Conversation', array(
				'channelid'	=> $nodeid,
				'pageid'	=> $pageid
			));
			if (is_array($conversationRouteId))
			{
				$conversationRouteId = (int) array_pop($conversationRouteId);
			}

			$db->update('page', array('routeid' => $conversationRouteId), array('pageid' => $pageid));
		}
	}

	/**
	 * Deletes pages and routes for a channel
	 *
	 * @param	int	$nodeId
	 * @param	bool	true to delete both channel and conversation pages/routes
	 *			false to only delete the conversation page/route, and leave the channel page/route
	 *			false is used when converting a channel to a category
	 */
	protected function deleteChannelPages($nodeId, $deleteAll = true)
	{
		if ($deleteAll)
		{
			$routeClasses = array(
				'vB5_Route_Channel',
				'vB5_Route_Conversation',
			);
		}
		else
		{
			$routeClasses = array(
				'vB5_Route_Conversation',
			);
		}

		// get the routes involved with this channel
		$routes = vB::getDbAssertor()->getRows('routenew', array(
			vB_dB_Query::CONDITIONS_KEY => array(
				array('field' => 'contentid', 'value' => $nodeId, 'operator' => vB_dB_Query::OPERATOR_EQ),
				array('field' => 'class', 'value' => $routeClasses, 'operator' => vB_dB_Query::OPERATOR_EQ)
			)
		), false, 'routeid');

		if (!empty($routes))
		{
			// delete the routes
			vB::getDbAssertor()->delete('routenew', array(
				array('field' => 'routeid', 'value' => array_keys($routes), 'operator' => vB_dB_Query::OPERATOR_EQ)
			));

			// delete the pages
			vB::getDbAssertor()->delete('page', array(
				array('field' => 'routeid', 'value' => array_keys($routes), 'operator' => vB_dB_Query::OPERATOR_EQ)
			));

			// We do not remove the routeid in the associated channel node record here
			// since we are calling this because we have already deleted the node,
			// or because we are switching the channel from acting as a forum/channel
			// to acting as a category (or the other way around), and the main
			// channel page/route still exist and will be used
		}
	}

	public function getForumHomeChannel()
	{
		$forumChannel = vB_Api::instanceInternal('Content_Channel')->fetchChannelByGUID(vB_Channel::DEFAULT_FORUM_PARENT);
		return vB_Library::instance('node')->getNodeBare($forumChannel['nodeid']);
	}

	public function getMainChannel()
	{
		$forumChannel = vB_Api::instanceInternal('Content_Channel')->fetchChannelByGUID(vB_Channel::MAIN_CHANNEL);
		return vB_Library::instance('node')->getNodeBare($forumChannel['nodeid']);
	}

	/** This creates an urlident from a title, and guarantees it will not be a duplicate
	 *
	 * @param	string	the title

	 * @return	string
	 **/
	public function getUniqueUrlIdent($title)
	{
		//first see if this is good. Note that we don't care if it's used for a node that isn't a channel.
		$candidate = vB_String::getUrlIdent($title);
		$check = $this->assertor->getRow('vBForum:node', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT, 'urlident' => $candidate,
			'contenttypeid' => $this->contenttypeid));

		if (empty($check) OR !empty($check['errors']))
		{
			return $candidate;
		}
		//So now we try adding _a, b, c, etc.  If we get to z we'll throw an exception.
		$charVal = ord('a');
		$charVal2 = ord('a');
		while (true)
		{
			if ($charVal >= ord('z') AND $charVal2 >= ord('z'))
			{
				throw new vB_Exception_Api('invalid_data' . $candidate . '_' . chr($charVal) . chr($charVal2) );
			}
			$check = $this->assertor->getRow('vBForum:node', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'urlident' => $candidate . '_' . chr($charVal) . chr($charVal2),
				'contenttypeid' => $this->contenttypeid));

			if (empty($check) OR !empty($check['errors']))
			{
				return $candidate . '_' . chr($charVal) . chr($charVal2);
			}

			$charVal2++;

			if ($charVal2 >= ord('z'))
			{
				$charVal++;
				$charVal2 = ord('a');
			}
		}
	}
}
