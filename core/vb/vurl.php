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

define('VURL_URL',                 1);
define('VURL_TIMEOUT',             2);
define('VURL_POST',                4);
define('VURL_HEADER',              8);
define('VURL_POSTFIELDS',         16);
define('VURL_ENCODING',           32);
define('VURL_USERAGENT',          64);
define('VURL_RETURNTRANSFER',    128);
define('VURL_HTTPHEADER',        256);

define('VURL_CLOSECONNECTION',  1024);
define('VURL_FOLLOWLOCATION',   2048);
define('VURL_MAXREDIRS',        4096);
define('VURL_NOBODY',           8192);
define('VURL_CUSTOMREQUEST',   16384);
define('VURL_MAXSIZE',         32768);
define('VURL_DIEONMAXSIZE',    65536);
define('VURL_VALIDSSLONLY',   131072);

define('VURL_ERROR_MAXSIZE',       1);
define('VURL_ERROR_SSL',           2);
define('VURL_ERROR_URL',           4);
define('VURL_ERROR_NOLIB',         8);

define('VURL_HANDLED',             1);
define('VURL_NEXT',                2);

define('VURL_STATE_HEADERS',  1);
define('VURL_STATE_LOCATION', 2);
define('VURL_STATE_BODY',     3);

/**
* vBulletin remote url class
*
* This class handles sending and returning data to remote urls via cURL and fsockopen
*
* @package 		vBulletin
* @version		$Revision: 40651 $
* @date 		$Date: 2010-11-16 16:23:46 -0800 (Tue, 16 Nov 2010) $
*
*/
class vB_vURL
{
	/**
	* Error code
	*
	* @var	int
	*/
	var $error = 0;

	/**
	* Options bitfield
	*
	* @var	integer
	*/
	var $bitoptions = 0;

	/**
	* List of headers by key
	*
	* @var	array
	*/
	var $headerkey = array();

	/**
	* Options Array
	*
	* @var	array
	*/
	var $options = array();

	/**
	* Transport Object Array
	*
	* @var	array
	*/
	var $classnames = array('cURL', 'fsockopen');

	/**
	* Transport Object Array
	*
	* @var	array
	*/
	var $transports = array();

	/**
	* Temporary filename for storing result
	*
	* @var	string
	*/
	var $tmpfile = null;

	/**
	 * Resets the class to initial settings
	 *
	 */
	function reset()
	{
		$this->bitoptions = 0;
		$this->headerkey = array();
		$this->error = 0;

		$this->options = array(
			VURL_TIMEOUT    => 15,
			VURL_POSTFIELDS => '',
			VURL_ENCODING   => '',
			VURL_USERAGENT  => '',
			VURL_URL        => '',
			VURL_HTTPHEADER => array(),
			VURL_MAXREDIRS  => 5,
			VURL_USERAGENT  => 'vBulletin via PHP',
			VURL_DIEONMAXSIZE => 1
		);

		foreach (array_keys($this->transports) AS $tname)
		{
			$transport =& $this->transports[$tname];
			$transport->reset();
		}

	}

	/**
	* Constructor
	*
	*/
	function __construct()
	{
		$this->options = vB::getDatastore()->get_value('options');

		// create the objects we need
		foreach ($this->classnames AS $classname)
		{
			$fullclass = 'vB_vURL_' . $classname;
			$this->transports["$classname"] = new $fullclass($this);
		}
		$this->reset();
	}

	/**
	* Destructor for PHP 5+, this deals with the case that
	* people forget to either unlink or move the file.
	*/
	function __destruct()
	{
		if (file_exists($this->tmpfile))
		{
			@unlink($this->tmpfile);
		}
	}

	/**
	* On/Off options
	*
	* @param		integer	one of the VURL_* defines
	* @param		mixed		option to set
	*
	*/
	function set_option($option, $extra)
	{
		switch ($option)
		{
			case VURL_POST:
			case VURL_HEADER:
			case VURL_NOBODY:
			case VURL_FOLLOWLOCATION:
			case VURL_RETURNTRANSFER:
			case VURL_CLOSECONNECTION:
			case VURL_VALIDSSLONLY:
				if ($extra == 1 OR $extra == true)
				{
					$this->bitoptions = $this->bitoptions | $option;
				}
				else
				{
					$this->bitoptions = $this->bitoptions & ~$option;
				}
				break;
			case VURL_TIMEOUT:
				if ($extra == 1 OR $extra == true)
				{
					$this->options[VURL_TIMEOUT] = intval($extra);
				}
				else
				{
					$this->options[VURL_TIMEOUT] = 15;
				}
				break;
			case VURL_POSTFIELDS:
				if ($extra == 1 OR $extra == true)
				{
					$this->options[VURL_POSTFIELDS] = $extra;
				}
				else
				{
					$this->options[VURL_POSTFIELDS] = '';
				}
				break;
			case VURL_ENCODING:
			case VURL_USERAGENT:
			case VURL_URL:
			case VURL_CUSTOMREQUEST:
				$this->options["$option"] = $extra;
				break;
			case VURL_HTTPHEADER:
				if (is_array($extra))
				{
					$this->headerkey = array();
					$this->options[VURL_HTTPHEADER] = $extra;
					foreach ($extra AS $line)
					{
						list($header, $value) = explode(': ', $line, 2);
						$this->headerkey[strtolower($header)] = $value;
					}
				}
				else
				{
					$this->options[VURL_HTTPHEADER] = array();
					$this->headerkey = array();
				}
				break;
			case VURL_MAXSIZE:
			case VURL_MAXREDIRS:
			case VURL_DIEONMAXSIZE:
				$this->options["$option"]	= intval($extra);
				break;
		}
	}

	/**
	* The do it all function
	*
	* @return	mixed		false on failure, array or string on success
	*/
	function exec()
	{
		$result = $this->exec2();

		if (is_array($result))
		{
			if (empty($result['body']) AND file_exists($result['body_file']))
			{
				$result['body'] = file_get_contents($result['body_file']);
				@unlink($result['body_file']);
			}
			if (!($this->bitoptions & VURL_HEADER))
			{
				return $result['body'];
			}
		}

		return $result;
	}

	/**
	* The function which formats the response array, removing what isn't required
	*
	* @param	array		response containing headers and body / body_file
	*
	* @return	mixed		true or array depending on response requested
	*/
	function format_response($response)
	{
		if ($this->bitoptions & VURL_RETURNTRANSFER)
		{
			if ($this->bitoptions & VURL_HEADER)
			{
				$response['headers'] = $this->build_headers($response['headers']);

				if ($this->bitoptions & VURL_NOBODY)
				{
					return $response['headers'];
				}
				else
				{
					return $response;
				}
			}
			else if ($this->bitoptions & VURL_NOBODY)
			{
				@unlink($response['body_file']);
				return true;
			}
			else
			{
				unset($response['headers']);
				return $response;
			}
		}
		else
		{
			@unlink($response['body_file']);
			return true;
		}
	}

	/**
	* new vURL method which stores items in a file if it can until needed
	*
	* @return	mixed		false on failure, true or array depending on response requested
	*/
	function exec2()
	{
		if (!empty($this->options['safeupload']))
		{
			$this->tmpfile = $this->options['tmppath'] . '/vbupload' . vB::getCurrentSession()->get('userid') . substr(TIMENOW, -4);
		}
		else
		{
			$this->tmpfile = @tempnam(ini_get('upload_tmp_dir'), 'vbupload');
		}

		if (empty($this->options[VURL_URL]))
		{
			trigger_error('Must set URL with set_option(VURL_URL, $url)', E_USER_ERROR);
		}

		if ($this->options[VURL_USERAGENT])
		{
			$this->options[VURL_HTTPHEADER][] = 'User-Agent: ' . $this->options[VURL_USERAGENT];
		}
		if ($this->bitoptions & VURL_CLOSECONNECTION)
		{
			$this->options[VURL_HTTPHEADER][] = 'Connection: close';
		}

		foreach (array_keys($this->transports) AS $tname)
		{
			$transport =& $this->transports[$tname];
			if (($result = $transport->exec()) === VURL_HANDLED  AND !$this->fetch_error())
			{
				return $this->format_response(array('headers' => $transport->response_header, 'body' => (isset($transport->response_text)? $transport->response_text : ""), 'body_file' => $this->tmpfile));
			}

			if ($this->fetch_error())
			{
				return false;
			}

		}

		@unlink($this->tmpfile);
		$this->set_error(VURL_ERROR_NOLIB);
		return false;
	}

	/**
	* Build the headers array
	*
	* @param		string	string of headers split by "\r\n"
	*
	* @return	array
	*/
	function build_headers($data)
	{
			$returnedheaders = explode("\r\n", $data);
			$headers = array();
			foreach ($returnedheaders AS $line)
			{
				list($header, $value) = explode(': ', $line, 2);
				if (preg_match('#^http/(1\.[012]) ([12345]\d\d) (.*)#i', $header, $httpmatches))
				{
					$headers['http-response']['version'] = $httpmatches[1];
					$headers['http-response']['statuscode'] = $httpmatches[2];
					$headers['http-response']['statustext'] = $httpmatches[3];
				}
				else if (!empty($header))
				{
					$headers[strtolower($header)] = $value;
				}
			}

			return $headers;
	}

	/**
	* Set Error
	*
	* @param		integer	Error Code
	*
	*/
	function set_error($errorcode)
	{
		$this->error = $errorcode;
	}

	/**
	* Return Error
	*
	* @return	integer
	*/
	function fetch_error()
	{
		return $this->error;
	}

	/**
	 * Does a HTTP HEAD Request
	 *
	 * @param	string	The URL to do the head request on
	 *
	 * @return	mixed	False on Failure, Array or String on Success
	 *
	 */
	function fetch_head($url)
	{
		$this->reset();
		$this->set_option(VURL_URL, $url);
		$this->set_option(VURL_RETURNTRANSFER, true);
		$this->set_option(VURL_HEADER, true);
		$this->set_option(VURL_NOBODY, true);
		$this->set_option(VURL_CUSTOMREQUEST, 'HEAD');
		$this->set_option(VURL_CLOSECONNECTION, 1);
		return $this->exec();
	}

	/**
	 * Does a HTTP Request, returning the body of the document
	 *
	 * @param	string	The URL
	 * @param	integer	The Maximum Size to get
	 * @param	boolean	Die when we reach the maximum Size?
	 * @param	boolean	Also Get headers?
	 *
	 * @return	mixed	False on Failure, Array or String on Success
	 *
	 */
	function fetch_body($url, $maxsize, $dieonmaxsize, $returnheaders)
	{
		$this->reset();
		$this->set_option(VURL_URL, $url);
		$this->set_option(VURL_RETURNTRANSFER, true);
		if (intval($maxsize))
		{
			$this->set_option(VURL_MAXSIZE, $maxsize);
		}
		if ($returnheaders)
		{
			$this->set_option(VURL_HEADER, true);
		}
		if (!$dieonmaxsize)
		{
			$this->set_option(VURL_DIEONMAXSIZE, false);
		}
		return $this->exec();
	}
}


/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40651 $
|| ####################################################################
\*======================================================================*/
