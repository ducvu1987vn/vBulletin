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

class vB5_Route_Newcontent extends vB5_Route
{
	public static function exportArguments($arguments)
	{
		$data = unserialize($arguments);

		$page = vB::getDbAssertor()->getRow('page', array('pageid' => $data['pageid']));
		if (empty($page))
		{
			throw new Exception('Couldn\'t find page');
		}
		$data['pageGuid'] = $page['guid'];
		unset($data['pageid']);

		return serialize($data);
	}

	public static function importArguments($arguments)
	{
		$data = unserialize($arguments);

		$page = vB::getDbAssertor()->getRow('page', array('guid' => $data['pageGuid']));
		if (empty($page))
		{
			throw new Exception('Couldn\'t find page');
		}
		$data['pageid'] = $page['pageid'];
		unset($data['pageGuid']);

		return serialize($data);
	}

	public static function importContentId($arguments)
	{
		return $arguments['pageid'];
	}

	/**
	 * Sets the breadcrumbs for the route
	 *
	 * @return	array
	 */
	protected function setBreadcrumbs()
	{
		$this->breadcrumbs = array();

		if (isset($this->arguments['nodeid']) && $this->arguments['nodeid'])
		{
			$this->addParentNodeBreadcrumbs($this->arguments['nodeid']);
		}

		$this->breadcrumbs[] = array(
			'phrase' => 'create_new_topic',
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/