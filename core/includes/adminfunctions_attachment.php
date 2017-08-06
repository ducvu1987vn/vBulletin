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

error_reporting(E_ALL & ~E_NOTICE);

// ###################### Start checkpath #######################
function verify_upload_folder($attachpath)
{
	if ($attachpath == '')
	{
		print_stop_message2('please_complete_required_fields');
	}

	// Get realpath.
	$test = realpath($attachpath);

	if (!$test)
	{
		// If above fails, try relative path instead.
		$test = realpath(DIR . DIRECTORY_SEPARATOR . $attachpath);
	}

	if (!is_dir($test) OR !is_writable($test))
	{
		print_stop_message2(array('test_file_write_failed',  $attachpath));
	}

	if (!is_dir($test . '/test'))
	{
		@umask(0);
		if (!@mkdir($test . '/test', 0777))
		{
			print_stop_message2(array('test_file_write_failed',  $attachpath));
		}
	}

	@chmod($test . '/test', 0777);

	if ($fp = @fopen($test . '/test/test.attach', 'wb'))
	{
		fclose($fp);
		if (!@unlink($test . '/test/test.attach'))
		{
			print_stop_message2(array('test_file_write_failed',  $attachpath));
		}
		@rmdir($test . '/test');
	}
	else
	{
		print_stop_message2(array('test_file_write_failed',  $attachpath));
	}
}

// ###################### Start updateattachmenttypes #######################
function build_attachment_permissions()
{
	$data = array();
	$types = vB::getDbAssertor()->assertQuery('vBForum:fetchAllAttachPerms');

	foreach ($types as $type)
	{
		if (empty($data["$type[extension]"]))
		{
			$contenttypes = unserialize($type['contenttypes']);
			$data["$type[extension]"] = array(
				'size'         => $type['default_size'],
				'width'        => $type['default_width'],
				'height'       => $type['default_height'],
				'contenttypes' => $contenttypes,
			);
		}

		if (!empty($type['usergroupid']))
		{
			$data["$type[extension]"]['custom']["$type[usergroupid]"] = array(
				'size'         => $type['custom_size'],
				'width'        => $type['custom_width'],
				'height'       => $type['custom_height'],
				'permissions'  => $type['custom_permissions'],
			);
		}
	}

	build_datastore('attachmentcache', serialize($data), true);
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 67970 $
|| ####################################################################
\*======================================================================*/
?>
