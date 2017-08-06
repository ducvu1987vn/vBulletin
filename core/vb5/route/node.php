<?php

/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 5.0.0
  || # ---------------------------------------------------------------- # ||
  || # Copyright  2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

/**
 * This class is used as a proxy for the actual node route. It enables us to hide
 * the node title and URL path until we verify permissions.
 */
class vB5_Route_Node extends vB5_Route
{
	public function getUrl()
	{
		return "/{$this->prefix}/{$this->arguments['nodeid']}";
	}

	public function getCanonicalRoute()
	{
		if (!isset($this->canonicalRoute))
		{
			if (empty($this->arguments['nodeid']))
			{
				throw new vB_Exception_Api('no_permission');
			}
			
			$nodeApi = vB_Api::instance('node');
			
			// this method will return an error if the user does not have permission			
			$node = $nodeApi->getNode($this->arguments['nodeid']);
			
			$contentApi = vB_Api_Content::getContentApi($node['contenttypeid']);
			if (!$contentApi->validate($node, vB_Api_Content::ACTION_VIEW, $node['nodeid'], array($node['nodeid'] => $node)))
			{
				throw new vB_Exception_NodePermission($node['nodeid']);
			}
			
			$parent = $nodeApi->getNode($node['starter']);
			$parent['innerPost'] = $this->arguments['nodeid'];
			
			$this->canonicalRoute = self::getRoute($node['routeid'], $parent);
		}

		return $this->canonicalRoute;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
