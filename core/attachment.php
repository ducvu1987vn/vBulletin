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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
@ini_set('zlib.output_compression', 'Off');
@set_time_limit(0);
if (@ini_get('output_handler') == 'ob_gzhandler' AND @ob_get_length() !== false)
{	// if output_handler = ob_gzhandler, turn it off and remove the header sent by PHP
	@ob_end_clean();
	header('Content-Encoding:');
}

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'attachment');
define('CSRF_PROTECTION', true);
define('NOHEADER', 1);
define('NOZIP', 1);
define('NOCOOKIES', 1);
define('NOPMPOPUP', 1);
define('NONOTICES', 1);

// attachment.php/$attachmentid/file.mp3 -- for podcast and confused clients that determine file type in <enclosure> by the url extension <iTunes, I'm looking in your direction>
if (!$_REQUEST['attachmentid'])
{
	$url_info = $_SERVER['REQUEST_URI'] ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF'];

	if ($url_info != '')
	{
		preg_match('#attachment\.php/(\d+)/#si', $url_info, $matches);
		$_REQUEST['attachmentid'] = intval($matches[1]);
	}
}

if (empty($_REQUEST['attachmentid']) AND empty($_REQUEST['filedataid']))
{
	// return not found header
	$sapi_name = php_sapi_name();
	if ($sapi_name == 'cgi' OR $sapi_name == 'cgi-fcgi')
	{
		header('Status: 404 Not Found');
	}
	else
	{
		header('HTTP/1.1 404 Not Found');
	}
	exit;
}

if ($_REQUEST['stc'] == 1) // we were called as <img src=> from showthread.php
{
	define('NOSHUTDOWNFUNC', 1);
}

// Immediately send back the 304 Not Modified header if this image is cached, don't load global.php
// 3.5.x allows overwriting of attachments so we add the dateline to attachment links to avoid caching
if (!isset($_SERVER['HTTP_RANGE']) AND (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) OR !empty($_SERVER['HTTP_IF_NONE_MATCH'])))
{
	$sapi_name = php_sapi_name();
	if ($sapi_name == 'cgi' OR $sapi_name == 'cgi-fcgi')
	{
		header('Status: 304 Not Modified');
	}
	else
	{
		header('HTTP/1.1 304 Not Modified');
	}
	// remove the content-type and X-Powered headers to emulate a 304 Not Modified response as close as possible
	header('Content-Type:');
	header('X-Powered-By:');
	if (!empty($_REQUEST['attachmentid']))
	{
		header('ETag: "' . intval($_REQUEST['attachmentid']) . '"');
	}
	exit;
}

// if $_POST['ajax'] is set, we need to set a $_REQUEST['do'] so we can precache the lightbox template
if (!empty($_POST['ajax']) AND isset($_POST['uniqueid']))
{
	$_REQUEST['do'] = 'lightbox';
}

// #################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array('lightbox' => array('lightbox'));

/*
The following headers are usually handled internally but we do our own thing
with attachments, the cache-control is to stop caches keeping private attachments
and the Vary header is to deal with the fact the filename encoding changes.
 */
header('Cache-Control: private');
header('Vary: User-Agent');

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vbulletin->input->clean_array_gpc('r', array(
	'attachmentid' => vB_Cleaner::TYPE_UINT,
	'thumb'        => vB_Cleaner::TYPE_BOOL,
	'cid'          => vB_Cleaner::TYPE_UINT,
	'filedataid'   => vB_Cleaner::TYPE_UINT,
	's'        	   => vB_Cleaner::TYPE_STR,
	'userid'       => vB_Cleaner::TYPE_UINT,
	'type'         => vB_Cleaner::TYPE_STR,
));

$vbulletin->input->clean_array_gpc('p', array(
	'ajax'     => vB_Cleaner::TYPE_BOOL,
	'uniqueid' => vB_Cleaner::TYPE_UINT
));

$type = '';
if (!empty($vbulletin->GPC['type']))
{
	$type = $vbulletin->GPC['type'];
}
else if (!empty($vbulletin->GPC['thumb']) AND intval($vbulletin->GPC['thumb']))
{
	$type = 'thumb';
}
$type = vB_Api::instanceInternal('filedata')->sanitizeFiletype($type);

//
//	This change has been made since vB5 won't allow unauthenticated
//	access to attachments. All calls to this should include a
//	sessionhash and userid pair. (userid may be 0 for guest)
//
//	The preferred way of accessing this is through attachmentid,
//	since it does permissions. Filedataid only checks if the user
//	is the poster, or if a special public flag is set on the
//	record.
//
if ($vbulletin->GPC_exists['s'])
{
	vB::init();
	vB::setCurrentSession(vB_Session_WebApi::getSession($vbulletin->GPC['userid'], $vbulletin->GPC['s']));
	$api = vB_Api::instance('content_attach');

	if ($vbulletin->GPC_exists['filedataid'])
	{
		$attachmentinfo = $api->fetchImageByFiledataid($vbulletin->GPC['filedataid'], $type);
	}
	else if ($vbulletin->GPC_exists['attachmentid'])
	{
		$attachmentinfo = $api->fetchImage($vbulletin->GPC['attachmentid'], $type);
	}

	if ($attachmentinfo === null OR isset($attachmentinfo['errors']))
	{
		$sapi_name = php_sapi_name();
		if ($sapi_name == 'cgi' OR $sapi_name == 'cgi-fcgi')
		{
			header('Status: 404 Not Found');
		}
		else
		{
			header('HTTP/1.1 404 Not Found');
		}
		exit;
	}

	$attachmentinfo['attachmentid'] = 0;
	$mimetype = $attachmentinfo['headers'];
}
//The vB5 attachment handling is a lot different from the vB3/4
else if (!($attach =& vB_Attachment_Display_Single_Library::fetch_library($vbulletin, $vbulletin->GPC['cid'], $vbulletin->GPC['thumb'], $vbulletin->GPC['attachmentid'])))
{
	//See if we are authorized in vB5;
	$api = vB_Api::instance('content_attach');
	$attachmentinfo = $api->fetchImage($vbulletin->GPC['attachmentid'], $vbulletin->GPC['thumb'], false);
	$attachmentinfo['attachmentid'] = $vbulletin->GPC['attachmentid'];
	$mimetype = $attachmentinfo['headers'];
}
else
{
	$result = $attach->verify_attachment();
	if ($result === false)
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['attachment'])));
	}
	else if ($result === 0)
	{
		header('Content-type: image/gif');
		readfile(DIR . '/' . $vbulletin->options['cleargifurl']);
		exit;
	}
	else if ($result === -1)
	{
		print_no_permission();
	}

	$attachmentinfo = $attach->fetch_attachmentinfo();
	// this convoluted mess sets the $threadinfo/$foruminfo arrays for the session.inthread and session.inforum values
	if ($browsinginfo = $attach->fetch_browsinginfo())
	{
		foreach ($browsinginfo AS $arrayname => $values)
		{
			$$arrayname = array();
			foreach ($values AS $index => $value)
			{
				$$arrayname[$$index] = $value;
			}
		}
	}
}


// handle lightbox requests
if (isset($_REQUEST['do']) AND $_REQUEST['do'] == 'lightbox')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'width'   => vB_Cleaner::TYPE_UINT,
		'height'  => vB_Cleaner::TYPE_UINT,
		'first'   => vB_Cleaner::TYPE_BOOL,
		'last'    => vB_Cleaner::TYPE_BOOL,
		'current' => vB_Cleaner::TYPE_UINT,
		'total'   => vB_Cleaner::TYPE_UINT
	));
	$width = $vbulletin->GPC['width'];
	$height = $vbulletin->GPC['height'];
	$first = $vbulletin->GPC['first'];
	$last = $vbulletin->GPC['last'];
	$current = $vbulletin->GPC['current'];
	$total = $vbulletin->GPC['total'];

	require_once(DIR . '/includes/class_xml.php');
	$xml = new vB_XML_Builder_Ajax('text/xml');

	if (in_array(strtolower($attachmentinfo['extension']), array('jpg', 'jpeg', 'jpe', 'gif', 'png', 'bmp')))
	{
		$uniqueid = $vbulletin->GPC['uniqueid'];
		$imagelink = 'attachment.php?' . vB::getCurrentSession()->get('sessionurl') . 'attachmentid=' . $attachmentinfo['attachmentid'] . '&d=' . $attachmentinfo['dateline'];
		$attachmentinfo['date_string'] = vbdate($vbulletin->options['dateformat'], $attachmentinfo['dateline']);
		$attachmentinfo['time_string'] = vbdate($vbulletin->options['timeformat'], $attachmentinfo['dateline']);
		$show['newwindow'] = ($attachmentinfo['newwindow'] ? true : false);

		// Legacy Hook 'attachment_lightbox' Removed //

		$templater = vB_Template::create('lightbox');
		$templater->register('attachmentinfo', $attachmentinfo);
		$templater->register('current', $current);
		$templater->register('first', $first);
		$templater->register('height', $height);
		$templater->register('imagelink', $imagelink);
		$templater->register('last', $last);
		$templater->register('total', $total);
		$templater->register('uniqueid', $uniqueid);
		$templater->register('width', $width);
		$html = $templater->render(true);

		$xml->add_group('img');
		$xml->add_tag('html', process_replacement_vars($html));
		$xml->add_tag('link', $imagelink);
		$xml->add_tag('name', $attachmentinfo['filename']);
		$xml->add_tag('date', $attachmentinfo['date_string']);
		$xml->add_tag('time', $attachmentinfo['time_string']);
		$xml->close_group();
	}
	else
	{
		$xml->add_group('errormessage');
		$xml->add_tag('error', 'notimage');
		$xml->add_tag('extension', $attachmentinfo['extension']);
		$xml->close_group();
	}
	$xml->print_xml();
}

if ($attachmentinfo['extension'])
{
	$extension = strtolower($attachmentinfo['extension']);
}
else
{
	$extension = strtolower(file_extension($attachmentinfo['filename']));
}

if ($vbulletin->options['attachfile'] AND empty($attachmentinfo['filedata']))
{
	require_once(DIR . '/includes/functions_file.php');
	$attachpath = fetch_attachment_path($attachmentinfo['uploader'], $attachmentinfo['filedataid'], $type);

	if ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
	{
		if (!($fp = fopen($attachpath, 'rb')))
		{
			exit;
		}
	}
	else if (!($fp = @fopen($attachpath, 'rb')))
	{
		$filedata = base64_decode('R0lGODlhAQABAIAAAMDAwAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
		$filesize = strlen($filedata);
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');             // Date in the past
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
		header('Cache-Control: no-cache, must-revalidate');           // HTTP/1.1
		header('Pragma: no-cache');                                   // HTTP/1.0
		header("Content-disposition: inline; filename=clear.gif");
		header('Content-transfer-encoding: binary');
		header("Content-Length: $filesize");
		header('Content-type: image/gif');
		echo $filedata;
		exit;
	}
}

$startbyte = 0;
$lastbyte = $attachmentinfo['filesize'] - 1;

if (isset($_SERVER['HTTP_RANGE']))
{
	preg_match('#^bytes=(-?([0-9]+))(-([0-9]*))?$#', $_SERVER['HTTP_RANGE'], $matches);

	if (intval($matches[1]) < 0)
	{ // its negative so we want to take this value from last byte
		$startbyte = $attachmentinfo['filesize'] - $matches[2];
	}
	else
	{
		$startbyte = intval($matches[2]);
		if ($matches[4])
		{
			$lastbyte = $matches[4];
		}
	}

	if ($startbyte < 0 OR $startbyte >= $attachmentinfo['filesize'])
	{
		if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
		{
			header('Status: 416 Requested Range Not Satisfiable');
		}
		else
		{
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
		}
		header('Accept-Ranges: bytes');
		header('Content-Range: bytes */'. $attachmentinfo['filesize']);
		exit;
	}
}

// send jpeg header for PDF, BMP, TIF, TIFF, and PSD thumbnails as they are jpegs
if ($type != vB_Api_Filedata::SIZE_FULL AND in_array($extension, array('bmp', 'tif', 'tiff', 'psd', 'pdf')))
{
	$attachmentinfo['filename'] = preg_replace('#.(bmp|tiff?|psd|pdf)$#i', '.jpg', $attachmentinfo['filename']);
	$mimetype = array('Content-type: image/jpeg');
}
else if (empty($mimetype))
{
	$mimetype= unserialize($attachmentinfo['mimetype']);
}

header('Cache-control: max-age=31536000, private');
header('Expires: ' . gmdate("D, d M Y H:i:s", TIMENOW + 31536000) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $attachmentinfo['dateline']) . ' GMT');
header('ETag: "' . $attachmentinfo['attachmentid'] . '"');
header('Accept-Ranges: bytes');

// look for entities in the file name, and if found try to convert
// the filename to UTF-8
$filename = $attachmentinfo['filename'];
if (preg_match('~&#([0-9]+);~', $filename))
{
	if (function_exists('iconv'))
	{
		$filename_conv = @iconv(vB_Template_Runtime::fetchStyleVar('charset'), 'UTF-8//IGNORE', $filename);
		if ($filename_conv !== false)
		{
			$filename = $filename_conv;
		}
	}

	$filename = preg_replace(
		'~&#([0-9]+);~e',
		"convert_int_to_utf8('\\1')",
		$filename
	);
	$filename_charset = 'utf-8';
}
else
{
	$filename_charset = vB_Template_Runtime::fetchStyleVar('charset');
}

$filename = preg_replace('#[\r\n]#', '', $filename);

// Opera and IE have not a clue about this, mozilla puts on incorrect extensions.
if (is_browser('mozilla'))
{
	$filename = "filename*=" . $filename_charset . "''" . rawurlencode($filename);
}
else
{
	// other browsers seem to want names in UTF-8
	if ($filename_charset != 'utf-8' AND function_exists('iconv'))
	{
		$filename_conv = iconv($filename_charset, 'UTF-8//IGNORE', $filename);
		if ($filename_conv !== false)
		{
			$filename = $filename_conv;
		}
	}

	if (is_browser('opera') OR is_browser('konqueror') OR is_browser('safari'))
	{
		// Opera / Konqueror does not support encoded file names
		$filename = 'filename="' . str_replace('"', '', $filename) . '"';
	}
	else
	{
		// encode the filename to stay within spec
		$filename = 'filename="' . rawurlencode($filename) . '"';
	}
}

if (in_array($extension, array('jpg', 'jpe', 'jpeg', 'gif', 'png')))
{
	header("Content-disposition: inline; $filename");
	header('Content-transfer-encoding: binary');
}
else
{
	// force files to be downloaded because of a possible XSS issue in IE
	header("Content-disposition: attachment; $filename");
}

if ($startbyte != 0 OR $lastbyte != ($attachmentinfo['filesize'] - 1))
{
	if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
	{
		header('Status: 206 Partial Content');
	}
	else
	{
		header('HTTP/1.1 206 Partial Content');
	}
	header('Content-Range: bytes '. $startbyte .'-'. $lastbyte .'/'. $attachmentinfo['filesize']);
}

header('Content-Length: ' . (($lastbyte + 1) - $startbyte));

if (is_array($mimetype))
{
	foreach ($mimetype AS $header)
	{
		if (!empty($header))
		{
			header($header);
		}
	}
}
else
{
	header('Content-type: unknown/unknown');
}

// This is new in IE8 and tells the browser not to try and guess
header('X-Content-Type-Options: nosniff');

// prevent flash from ever considering this to be a cross domain file
header('X-Permitted-Cross-Domain-Policies: none');

// Legacy Hook 'attachment_display' Removed //

// update views counter
if ($type != vB_Api_Filedata::SIZE_FULL AND connection_status() == 0 AND $lastbyte == ($attachmentinfo['filesize'] - 1))
{
	if ($vbulletin->options['attachmentviewslive'])
	{
		// doing it as they happen; not using a DM to avoid overhead
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "attachment SET
			counter = counter + 1
			WHERE attachmentid = $attachmentinfo[attachmentid]
			");
	}
	else
	{
		// XXX: This no longer works, we need to add attachmentviews to the assertor
		// vB::getDbAssertor()->insert('vBForum:attachmentviews', array('attachmentid' => $attachmentinfo[$attachmentid]));
	}
}

if ($vbulletin->options['attachfile'] AND empty($attachmentinfo['filedata']))
{
	if (defined('NOSHUTDOWNFUNC'))
	{
		if ($_GET['stc'] == 1)
		{
			$db->close();
		}
		else
		{
			exec_shut_down();
		}
	}

	if ($startbyte > 0)
	{
		fseek($fp, $startbyte);
	}

	while (connection_status() == 0 AND $startbyte <= $lastbyte)
	{	// You can limit bandwidth by decreasing the values in the read size call, they must be equal.
		$size = $lastbyte - $startbyte;
		$readsize = ($size > 1048576) ? 1048576 : $size + 1;
		echo @fread($fp, $readsize);
		$startbyte += $readsize;
		flush();
	}
	@fclose($fp);
}
else
{
	// start grabbing the filedata in batches of 2mb
	while (connection_status() == 0 AND $startbyte <= $lastbyte)
	{
		$size = $lastbyte - $startbyte;
		$readsize = ($size > 2097152) ? 2097152 : $size + 1;

		$attachmentinfo = vB::getDbAssertor()->getRow('getFiledataBatch', array(
			'type'       => $type,
			'filedata'   => ($type != vB_Api_Filedata::SIZE_FULL ? 'resize_filedata' : 'filedata'),
			'filedataid' => $attachmentinfo['filedataid'],
			'startbyte'  => $startbyte + 1,
			'readsize'   => $readsize
		));
		echo $attachmentinfo['filedata'];
		$startbyte += $readsize;
		flush();
	}

	if (defined('NOSHUTDOWNFUNC'))
	{
		if (isset($_GET['stc']) AND $_GET['stc'] == 1)
		{
			$db->close();
		}
		else
		{
			exec_shut_down();
		}
	}
}

// Legacy Hook 'attachment_complete' Removed //

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 71092 $
|| ####################################################################
\*======================================================================*/
?>
