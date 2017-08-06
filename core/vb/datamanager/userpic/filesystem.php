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

class vB_DataManager_Userpic_Filesystem extends vB_DataManager_Userpic
{
	function fetch_path($userid, $revision, $thumb = false, $ext = 'gif')
	{
		$table = $this->fetchTableBase($this->table);
		$path = $this->filepath . DIRECTORY_SEPARATOR . ($thumb ? 'thumbs' . DIRECTORY_SEPARATOR : '') . preg_replace("#^custom#si", '', $table) . $userid . "_" . $revision . ".$ext";
		return $path;
	}

	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if ($file =& $this->fetch_field('filedata'))
		{
			$this->setr_info('filedata', $file);
			$this->do_unset('filedata');
			$this->set('filesize', strlen($this->info['filedata']));
			chdir(DIR);
			if (!is_writable($this->filepath))
			{
				$this->error('upload_invalid_imagepath');
				return false;
			}

			if ($thumb =& $this->fetch_field('filedata_thumb'))
			{
				$this->setr_info('filedata_thumb', $thumb);
				$this->do_unset('filedata_thumb');
			}

			$image =& vB_Image::instance();
		}

		return parent::pre_save($doquery);
	}

	function post_save_each($doquery = true)
	{
		// Check if revision was passed as an info object or as existing
		if (isset($this->info["{$this->revision}"]))
		{
			$revision = $this->info["{$this->revision}"];
		}
		else if ($this->fetch_field($this->revision) !== null)
		{
			$revision = $this->fetch_field($this->revision);
		}

		// We were given an image and a revision number so write out a new image.
		if (!empty($this->info['filedata']) AND isset($revision))
		{
			$ext = empty($this->existing['aextension']) ? 'gif' : $this->existing['aextension'];

			$oldfilename = $this->fetch_path($this->fetch_field('userid'), $revision, false, $ext);
			$oldthumbfilename = $this->fetch_path($this->fetch_field('userid'), $revision, true, $ext);
			$newfilename = $this->fetch_path($this->fetch_field('userid'), $revision + 1, false, $ext);
			$thumbfilename = $this->fetch_path($this->fetch_field('userid'), $revision + 1, true, $ext);

			if ($filenum = fopen($newfilename, 'wb'))
			{
				$table = $this->fetchTableBase($this->table);
				if ($revision)
				{
					$this->deleteFile($oldfilename);
					if ($table == 'customavatar')
					{
						$this->deleteFile($oldthumbfilename);
					}
				}
				@fwrite($filenum, $this->info['filedata']);
				@fclose($filenum);

				// init user data manager
				$userdata = new vB_Datamanager_User($this->registry, vB_DataManager_Constants::ERRTYPE_SILENT);
				$userdata->setr('userid', $this->fetch_field('userid'));
				$userdata->condition = array('userid' => $this->fetch_field('userid'));
				$userdata->set($this->revision, $revision + 1);

				$userdata->save();
				unset($userdata);

				if ($table == 'customavatar')
				{
					if ($this->info['filedata_thumb'])
					{
						$thumbnail['filedata'] =& $this->info['filedata_thumb'];
					}
					else
					{
						$thumbnail = $this->fetch_thumbnail($newfilename, true);
					}

					require_once(DIR . '/includes/functions_file.php');
					$newfiledetails = pathinfo($newfilename);
					$fields = " filename = '$newfiledetails[basename]'";
					if ($thumbnail['filedata'] AND vbmkdir(dirname($thumbfilename)) AND $filenum = @fopen($thumbfilename, 'wb'))
					{
						@fwrite($filenum, $thumbnail['filedata']);
						@fclose($filenum);

						if ($thumbnail['height'] AND $thumbnail['width'])
						{
							$fields .= ", width_thumb = $thumbnail[width], height_thumb = $thumbnail[height]";
						}

						unset($thumbnail);
					}
					$this->registry->db->query_write("
						UPDATE " . TABLE_PREFIX . "customavatar
						SET $fields
						WHERE userid = " . $this->fetch_field('userid')
					);
				}

				return true;
			}
			else
			{
				$this->error('upload_invalid_imagepath');
				return false;
			}
		}
		else
		{
			return true;
		}
	}

	/**
	* Any code to run after deleting
	*
	* @param	Boolean Do the query?
	*/
	function post_delete($doquery = true)
	{

		$users = $this->registry->db->query_read_slave("
			SELECT
				userid, {$this->revision} AS revision
			FROM " . TABLE_PREFIX . "user
			WHERE " . $this->condition
		);
		while ($user = $this->registry->db->fetch_array($users))
		{
			$this->deleteFile($this->fetch_path($user['userid'], $user['revision']));
			$this->deleteFile($this->fetch_path($user['userid'], $user['revision'], true));
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
