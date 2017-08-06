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
 * vB_Library_Usergroup
 *
 * @package vBApi
 * @access public
 */
class vB_Library_Usergroup extends vB_Library
{
	protected $userGroups = false;
	protected $bitfields = false;
	protected $groupPerms = false;

	protected function __construct()
	{
		parent::__construct();
		$this->assertor = vB::getDbAssertor();
		$this->blogChannel = $this->getBlogChannel();
	}

	public function createBlog($input)
	{
		return $this->createChannel($input, $this->getBlogChannel(), vB_Page::getBlogConversPageTemplate(), vB_Page::getBlogChannelPageTemplate(), vB_Api_UserGroup::CHANNEL_OWNER_SYSGROUPID);
	}

	public function createChannel($input, $channelid, $channelConvTemplateid, $channelPgTemplateId, $ownerSystemGroupId)
	{
		$input['parentid'] = $channelid;
		$input['inlist'] = 1; // we don't want it to be shown in channel list, but we want to move them
		$input['protected'] = 0;

		if (!isset($input['publishdate']))
		{
			$input['publishdate'] = vB::getRequest()->getTimeNow();
		}

		$input['templates']['vB5_Route_Channel'] = $channelPgTemplateId;
		$input['templates']['vB5_Route_Conversation'] = $channelConvTemplateid;

		// add channel node
		$channelLib = vB_Library::instance('content_channel');
		$input['page_parentid'] = 0;
		$nodeid = $channelLib->add($input);
		//Make the current user the channel owner.
		$userApi = vB_Api::instanceInternal('user');
		$usergroup = vB::getDbAssertor()->getRow('usergroup', array('systemgroupid' => $ownerSystemGroupId));

		vB_Cache::instance()->event(array('userPerms_' . $input['userid'] , 'nodeChg_' .$this->blogChannel));
		vB_Cache::instance(vB_Cache::CACHE_FAST)->event(array('userPerms_' . $input['userid'] , 'nodeChg_' .$this->blogChannel));
		vB_User::setGroupInTopic(vB::getCurrentSession()->get('userid'), $nodeid, $usergroup['usergroupid']);
		return $nodeid;
	}

	/**
	 * @uses fetch the id of the global Blog Channel
	 * @return int nodeid of actual Main Blog Channel
	 */
	public function getBlogChannel()
	{
		if ($this->blogChannel)
		{
			return $this->blogChannel;
		}
		$options = vB::getDatastore()->getValue('options');

		if (isset($options['blog_parentchannel']) AND !empty($options['blog_parentchannel']))
		{
			$this->blogChannel = $options['blog_parentchannel'];
		}
		else
		{
			// use default pagetemplate for blogs
			$this->blogChannel = vB::getDbAssertor()->getField('vBForum:channel', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array('guid' => vB_Channel::DEFAULT_BLOG_PARENT)
			));
		}

		if (empty($this->blogChannel))
		{
			throw new vB_Exception_Api('blog_channel_not_set');
		}
		return $this->blogChannel;
	}

}
