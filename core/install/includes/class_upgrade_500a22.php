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
/*
if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}
*/

class vB_Upgrade_500a22 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500a22';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Alpha 22';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Alpha 21';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '';

	/** migrate blog channels. First a blog channel per user **/
	public function step_1($data = NULL)
	{
		if (isset($this->registry->products['vbblog']) AND $this->registry->products['vbblog'])
		{
			$this->show_message(sprintf($this->phrase['vbphrase']['importing_from_x'], 'blog'));
			$startat = intval($data['startat']);
			$batchsize = 500;
			//we create a blog channel per user. So get a list of blogposts since our last update
			$assertor = vB::getDbAssertor();

			if ($startat == 0)
			{
				$query = $assertor->getRow('vBInstall:getMaxBlogUserId', array('contenttypeid' => 9985));
				$startat = intval($query['maxuserId']);
			}
			$blogs  = $assertor->assertQuery('vBInstall:getBlogs4Import', array('maxexisting' => $startat,
				'blocksize' => $batchsize));

			if (!$blogs->valid())
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}
			$toImport = array();
			$owners = array();

			foreach($blogs AS $blog)
			{
				$toImport[$blog['blogid']] = $blog;
				$owners[$blog['userid']] = 0;
			}

			if (count($owners) < 1)
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}

			vB_Upgrade::createAdminSession();
			$checkExisting = $assertor->assertQuery('vBForum:node', array(vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				'parentid' => vB_Library::instance('blog')->getBlogChannel() , 'userid' => array_keys($owners)));
			foreach ($checkExisting AS $existing)
			{
				$owners[$existing['userid']] = 1;
			}

			$blogLib = vB_Library::instance('blog');
			$channelLib = vB_Library::instance('content_channel');
			foreach ($toImport AS $blog)
			{
				if ($owners[$blog['userid']] == 0)
				{
					$blog['oldid'] = $blog['blogid'];
					$blog['oldcontenttypeid'] = '9999';
					$blog['publishdate'] = $blog['dateline'];
					$blog['showpublished'] = 1;
					$blog['created'] = $blog['dateline'];
					$blog['title'] = $blog['username'];
					$blog['urlident'] = $channelLib->getUniqueUrlIdent($blog['username']);
					$blogLib->createBlog($blog);
					$owners[$blog['userid']] = 1;
				}
			}

			$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
			return array('startat' => max(array_keys($owners)));
		}
		else
		{
			$this->skip_message();
		}
	}

	/** migrate blog post starters **/
	public function step_2($data = NULL )
	{
		if (isset($this->registry->products['vbblog']) AND $this->registry->products['vbblog'])
		{
			vB_Upgrade::createAdminSession();
			$this->show_message(sprintf($this->phrase['vbphrase']['importing_from_x'], 'blog'));
			$batchsize = 500;
			//Get the highest post we've inserted.
			$assertor = vB::getDbAssertor();

			//we create a blog channel per user. So get a list of blogposts since our last update
			$assertor = vB::getDbAssertor();

			$query = $assertor->getRow('vBInstall:getMaxImportedPost', array('contenttypeid' => 9985));

			$startat = intval($query['maxid']);
			$textTypeId = vB_Types::instance()->getContentTypeID('vBForum_Text');

			/*** Blog starters. We need to insert the node records, text records, and closure records ***/
			$assertor->assertQuery('vBInstall:importBlogStarters', array('bloghome' => vB_Library::instance('blog')->getBlogChannel(),
				'batchsize' => $batchsize, 'startat' => $startat, 'texttype' => $textTypeId));

			$processed = $assertor->getRow('vBInstall:getProcessedCount', array());
			//set the starter
			$assertor->assertQuery('vBInstall:setStarter', array('startat' => $startat, 'contenttypeid' => 9985));
			$assertor->assertQuery('vBInstall:updateChannelRoutes', array('contenttypeid' => 9985, 'startat' => $startat,
				'batchsize' => 999999));

			//Now populate the text table
			if ($this->field_exists('blog_text', 'htmlstate'))
			{
				$assertor->assertQuery('vBInstall:importBlogText', array('contenttypeid' => 9985, 'startat' => $startat));
			}
			else
			{
				$assertor->assertQuery('vBInstall:importBlogTextNoState', array('contenttypeid' => 9985, 'startat' => $startat));
			}

			//Now the closure record for depth=0
			$assertor->assertQuery('vBInstall:addClosureSelf', array('contenttypeid' => 9985, 'startat' => $startat, 'batchsize' => $batchsize));

			//Add the closure records to root
			$assertor->assertQuery('vBInstall:addClosureParents', array('contenttypeid' => 9985, 'startat' => $startat, 'batchsize' => $batchsize));

			if (!$processed OR !empty($processed['errors']) OR (intval($processed['recs']) < 1))
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}
			else
			{
				$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
				return array('startat' => ($startat + 1));
			}
		}
		else
		{
			$this->skip_message();
		}
	}

	/** migrate blog post responses **/
	public function step_3($data = NULL )
	{
		if (isset($this->registry->products['vbblog']) AND $this->registry->products['vbblog'])
		{
			$this->show_message(sprintf($this->phrase['vbphrase']['importing_from_x'], 'blog'));
			$batchsize = 500;
			//Get the highest post we've inserted.
			$assertor = vB::getDbAssertor();

			$query = $assertor->getRow('vBInstall:getMaxImportedPost', array('contenttypeid' => 9984));
			$startat = intval($query['maxid']);
			$textTypeId = vB_Types::instance()->getContentTypeID('vBForum_Text');

			/*** Blog Responses. We need to insert the node records, text records, and closure records ***/
			$assertor->assertQuery('vBInstall:importBlogResponses', array('batchsize' => $batchsize,
				'startat' => $startat, 'texttypeid' => $textTypeId));

			$processed = $assertor->getRow('vBInstall:getProcessedCount', array());

			//Now populate the text table
			if ($this->field_exists('blog_text', 'htmlstate'))
			{
				$assertor->assertQuery('vBInstall:importBlogText', array('contenttypeid' => 9984, 'startat' => $startat));
			}
			else
			{
				$assertor->assertQuery('vBInstall:importBlogTextNoState', array('contenttypeid' => 9984, 'startat' => $startat));
			}


			//Now the closure record for depth=0
			$assertor->assertQuery('vBInstall:addClosureSelf', array('contenttypeid' => 9984, 'startat' => $startat, 'batchsize' => $batchsize));

			//Add the closure records to root
			$assertor->assertQuery('vBInstall:addClosureParents', array('contenttypeid' => 9984, 'startat' => $startat, 'batchsize' => $batchsize));
			if (!$processed OR !empty($processed['errors']) OR (intval($processed['recs']) < 1))
			{
				$this->show_message(sprintf($this->phrase['core']['process_done']));
				return;
			}
			else
			{
				$this->show_message(sprintf($this->phrase['core']['processed_records_x'], $batchsize));
				return array('startat' => ($startat + 1));
			}
		}
		else
		{
			$this->skip_message();
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
