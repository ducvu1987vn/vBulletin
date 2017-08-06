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

class vB_Upgrade_500b12 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '500b12';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '5.0.0 Beta 12';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '5.0.0 Beta 11';

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


	/** Add two indices to the route table
	 */
	public function step_1()
	{
		// Add new index
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'routenew', 1, 2),
			'routenew',
			'route_name',
			'name'
		);

	}

	/** Add two indices to the route table
	 */
	public function step_2()
	{
		// Add new index
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'routenew', 2, 2),
			'routenew',
			'route_class_cid',
			array('class, contentid')
		);
	}
	
	/** Make sure every node has a routed
	 */
	public function step_3($data = array())
	{
		$batchsize = 10000;
		if (empty($data['startat']))
		{
			$startat = 0;
		}
		else
		{
			$startat = $data['startat'];
		}
		$this->show_message(sprintf($this->phrase['version']['500b12']['updating_content_routes'], $startat));
		$assertor = vB::getDbAssertor();
		$maxNodeId = $assertor->getRow('vBInstall:getMaxNodeid', array());
		$maxNodeId = $maxNodeId['maxid'];

		if ($startat >= $maxNodeId)
		{
			$this->show_message(sprintf($this->phrase['core']['process_done']));
			return;
		}
		$assertor->assertQuery('vBInstall:fixNodeRouteid', array('startat' => $startat,
			'batchsize' => $batchsize, 'channelContenttypeid' =>  vB_Types::instance()->getContentTypeID('vBForum_Channel')));

		return array('startat' => $startat + $batchsize);
	}

	/** Remove blogcategories from the widgetinstance table	 */
	public function step_4()
	{
		$this->show_message($this->phrase['version']['500b12']['deleting_blog_categories_widget']);
		$assertor = vB::getDbAssertor();
		$widget = $assertor->getRow('widget', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array('guid' => 'vbulletin-widget_blogcategories-4eb423cfd6dea7.34930850')
		));

		$assertor->delete('widgetinstance', array('widgetid' => $widget['widgetid']));
	}

	/** Fix routeid in page table for social group home if needed */
	public function step_5()
	{

		$assertor = vB::getDbAssertor();
		$sgPage = $assertor->getRow('page', array(
			vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
			vB_dB_Query::CONDITIONS_KEY => array('routeid' => 0, 'guid' => 'vbulletin-4ecbdac82f2c27.60323372')
		));

		if ($sgPage)
		{
			$this->show_message($this->phrase['version']['500b12']['fix_sghome_routeid']);

			$route = $assertor->getRow('routenew', array(
				vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
				vB_dB_Query::CONDITIONS_KEY => array('guid' => 'vbulletin-4ecbdac93742a5.43676037')
			));

			$assertor->update('page',
				array('routeid' => $route['routeid']),
				array('pageid' => $sgPage['pageid'])
			);
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
