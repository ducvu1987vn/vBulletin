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

class vB5_Route_Modcp extends vB5_Route
{
	public function getUrl()
	{
		$bburl = vB::getDatastore()->getOption('bburl');
		$url = $bburl . '/' . $this->prefix . '/' . $this->arguments['file'] . '.php';
		
		if (strtolower(vB_String::getCharset()) != 'utf-8')
		{
			$url = vB_String::encodeUtf8Url($url);
		}
		
		return $url;
	}

	public static function resolvePath($path)
	{
		$currentDir = getcwd();
		chdir(DIR . '/' . vB::getDatastore()->getValue('modcpdir'));
		$path = realpath($path);
		chdir($currentDir);
		return $path;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 02:49, Sat Feb 23rd 2013
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
