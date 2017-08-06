<?php

/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 5.0.0
  || # ---------------------------------------------------------------- # ||
  || # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

class vB5_Route_Legacy_Bloghome extends vB5_Route_Legacy_GenerationOnly
{
	protected $titlekey = '';
	protected $idkey = '';
	protected $script = 'blog.php';
	protected $script_base_option_name = 'vbblog_url';

	public function getRedirect301()
	{
		$blogHomeChannelId = vB_Api::instance('blog')->getBlogChannel();
		$blogHomeChannel = vB_Library::instance('content_channel')->getContent($blogHomeChannelId);
		$blogHomeChannel = $blogHomeChannel[$blogHomeChannelId];

		return $blogHomeChannel['routeid'];
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/