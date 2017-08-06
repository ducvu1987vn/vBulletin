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

class vB_Upgrade_4110a3 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '4110a3';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.1.10 Alpha 3';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.1.10 Alpha 2';

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

	/** In general, upgrade files between 4.1.5 and 500a1 are likely to be different in vB5 from their equivalent in vB4.
	 *  Since large portions of vB4 code were removed in vB5, the upgrades to ensure that code works is unnecessary. If
	 *  there are actual errors that affect vB5, those must be included of course. If there are changes whose absence would
	 *  break a later step, those are required.
	 *
	 * But since these files will only be used to upgrade to versions after 5.0.0 alpha 1, most of the upgrade steps can be
	 * omitted. We could use skip_message(), but that takes up a redirect and, in the cli upgrade, a recursion. We would rather
	 * avoid those. So we have removed those steps,
	 * step 1 in the original is not needed because it deals with stylevar mapping, which is a later vB4 concept which doesn't exist in vB5
	 * steps 2 and 4 in the original are not needed because permissions in vB5 are done differently and come from a different table
	 * step 3 in the original is not needed because this field is not present and not used in vB5
	 *
	 * Which leaves steps 1 and 5
	 */

	/*
	 * VBIV-5472 : Convert old encoded filenames
	 */
	function step_1($data = null) //was step 5
	{
		$process = 1000;
		$startat = intval($data['startat']);

		if ($startat == 0)
		{
			$attachments = $this->db->query_first_slave("
				SELECT COUNT(*) AS attachments
				FROM " . TABLE_PREFIX . "attachment
			");

			$total = $attachments['attachments'];

			if ($total)
			{
				$this->show_message(sprintf($this->phrase['version']['4110a3']['processing_filenames'],$total));
				return array('startat' => 1);
			}
			else
			{
				$this->skip_message();
				return;
			}
		}
		else
		{
			$first = $startat - 1;
		}

		$attachments = $this->db->query_read_slave("
			SELECT filename, attachmentid
			FROM " . TABLE_PREFIX . "attachment
			LIMIT $first, $process
		");

		$rows = $this->db->num_rows($attachments);

		if ($rows)
		{
			while ($attachment = $this->db->fetch_array($attachments))
			{
				$aid = $attachment['attachmentid'];
				$filename = $attachment['filename'];
				$newfilename = $this->db->escape_string(html_entity_decode($filename, ENT_QUOTES));

				if ($filename != $newfilename)
				{
					$this->db->query_write("
						UPDATE " . TABLE_PREFIX . "attachment
						SET filename = '$newfilename'
						WHERE attachmentid = $aid
					");
				}
			}

			$this->db->free_result($attachments);
			$this->show_message(sprintf($this->phrase['version']['4110a3']['updated_attachments'],$first + $rows));

			return array('startat' => $startat + $process);
		}
		else
		{
			$this->show_message($this->phrase['version']['4110a3']['updated_attachments_complete']);
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
