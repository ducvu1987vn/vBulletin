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

class vB_vURL_cURL
{
	/**
	* String that holds the cURL callback data
	*
	* @var	string
	*/
	var $response_text = '';

	/**
	* String that holds the cURL callback data
	*
	* @var	string
	*/
	var $response_header = '';

	/**
	* cURL Handler
	*
	* @var	resource
	*/
	var $ch = null;

	/**
	* vB_vURL object
	*
	* @var	object
	*/
	var $vurl = null;

	/**
	* Filepointer to the temporary file
	*
	* @var	resource
	*/
	var $fp = null;

	/**
	* Length of the current response
	*
	* @var	integer
	*/
	var $response_length = 0;

	/**
	* Private variable when we request headers. Values are one of VURL_STATE_* constants.
	*
	* @var	int
	*/
	var $__finished_headers = VURL_STATE_HEADERS;

	/**
	* If the current result is when the max limit is reached
	*
	* @var	integer
	*/
	var $max_limit_reached = false;

	/**
	* Constructor
	*
	* @param	object	Instance of a vB_vURL Object
	*/
	function vB_vURL_cURL(&$vurl)
	{
		if (!is_a($vurl, 'vB_vURL'))
		{
			throw new Exception('Direct Instantiation of ' . __CLASS__ . ' prohibited.');
		}
		$this->vurl =& $vurl;
	}

	/**
	* Callback for handling headers
	*
	* @param	resource	cURL object
	* @param	string		Request
	*
	* @return	integer		length of the request
	*/
	function curl_callback_header(&$ch, $string)
	{
		if (trim($string) !== '')
		{
			$this->response_header .= $string;
		}
		return strlen($string);
	}

	/**
	* Callback for handling the request body
	*
	* @param	resource	cURL object
	* @param	string		Request
	*
	* @return	integer		length of the request
	*/
	function curl_callback_response(&$ch, $response)
	{
		$chunk_length = strlen($response);

		/* We receive both headers + body */
		if ($this->vurl->bitoptions & VURL_HEADER)
		{
			if ($this->__finished_headers != VURL_STATE_BODY)
			{
				if ($this->vurl->bitoptions & VURL_FOLLOWLOCATION AND preg_match('#(?<=\r\n|^)Location:#i', $response))
				{
					$this->__finished_headers = VURL_STATE_LOCATION;
				}

				if ($response === "\r\n")
				{
					if ($this->__finished_headers == VURL_STATE_LOCATION)
					{
						// found a location -- still following it; reset the headers so they only match the new request
						$this->response_header = '';
						$this->__finished_headers = VURL_STATE_HEADERS;
					}
					else
					{
						// no location -- we're done
						$this->__finished_headers = VURL_STATE_BODY;
					}
				}

				return $chunk_length;
			}
		}

		// no filepointer and we're using or about to use more than 100k
		if (!$this->fp AND $this->response_length + $chunk_length >= 1024*100)
		{
			if ($this->fp = @fopen($this->vurl->tmpfile, 'wb'))
			{
				fwrite($this->fp, $this->response_text);
				unset($this->response_text);
			}
		}

		if ($this->fp AND $response)
		{
			fwrite($this->fp, $response);
		}
		else
		{
			$this->response_text .= $response;

		}

		$this->response_length += $chunk_length;

		if (!empty($this->vurl->options[VURL_MAXSIZE]) AND $this->response_length > $this->vurl->options[VURL_MAXSIZE])
		{
			$this->max_limit_reached = true;
			$this->vurl->set_error(VURL_ERROR_MAXSIZE);
			return false;
		}

		return $chunk_length;
	}

	/**
	* Clears all previous request info
	*/
	function reset()
	{
		$this->response_text = '';
		$this->response_header = '';
		$this->response_length = 0;
		$this->__finished_headers = VURL_STATE_HEADERS;
		$this->max_limit_reached = false;
	}

	/**
	* Performs fetching of the file if possible
	*
	* @return	integer		Returns one of two constants, VURL_NEXT or VURL_HANDLED
	*/
	function exec()
	{
		$urlinfo = @vB_String::parseUrl($this->vurl->options[VURL_URL]);
		if (empty($urlinfo['port']))
		{
			if ($urlinfo['scheme'] == 'https')
			{
				$urlinfo['port'] = 443;
			}
			else
			{
				$urlinfo['port'] = 80;
			}
		}

		if (!function_exists('curl_init') OR ($this->ch = curl_init()) === false)
		{
			return VURL_NEXT;
		}

		if ($urlinfo['scheme'] == 'https')
		{
			// curl_version crashes if no zlib support in cURL (php <= 5.2.5)
			$curlinfo = curl_version();
			if (empty($curlinfo['ssl_version']))
			{
				curl_close($this->ch);
				return VURL_NEXT;
			}
		}

		curl_setopt($this->ch, CURLOPT_URL, $this->vurl->options[VURL_URL]);
		curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->vurl->options[VURL_TIMEOUT]);
		if (!empty($this->vurl->options[VURL_CUSTOMREQUEST]))
		{
			curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $this->vurl->options[VURL_CUSTOMREQUEST]);
		}
		else if ($this->vurl->bitoptions & VURL_POST)
		{
			curl_setopt($this->ch, CURLOPT_POST, 1);
			curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->vurl->options[VURL_POSTFIELDS]);
		}
		else
		{
			curl_setopt($this->ch, CURLOPT_POST, 0);
		}
		curl_setopt($this->ch, CURLOPT_HEADER, ($this->vurl->bitoptions & VURL_HEADER) ? 1 : 0);
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->vurl->options[VURL_HTTPHEADER]);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, ($this->vurl->bitoptions & VURL_RETURNTRANSFER) ? 1 : 0);
		if ($this->vurl->bitoptions & VURL_NOBODY)
		{
			curl_setopt($this->ch, CURLOPT_NOBODY, 1);
		}

		if ($this->vurl->bitoptions & VURL_FOLLOWLOCATION)
		{
			if (@curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1) === false) // disabled in safe_mode/open_basedir in PHP 5.1.6/4.4.4
			{
				curl_close($this->ch);
				return VURL_NEXT;
			}
			curl_setopt($this->ch, CURLOPT_MAXREDIRS, $this->vurl->options[VURL_MAXREDIRS]);
		}
		else
		{
			curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 0);
		}

		if ($this->vurl->options[VURL_ENCODING])
		{
			@curl_setopt($this->ch, CURLOPT_ENCODING, $this->vurl->options[VURL_ENCODING]); // this will work on versions of cURL after 7.10, though was broken on PHP 4.3.6/Win32
		}

		$this->reset();

		curl_setopt($this->ch, CURLOPT_WRITEFUNCTION, array(&$this, 'curl_callback_response'));
		curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, array(&$this, 'curl_callback_header'));

		if (!($this->vurl->bitoptions & VURL_VALIDSSLONLY))
		{
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
		}

		$result = curl_exec($this->ch);

		if ($urlinfo['scheme'] == 'https' AND $result === false AND curl_errno($this->ch) == '60') ## CURLE_SSL_CACERT problem with the CA cert (path? access rights?)
		{
			curl_setopt($this->ch, CURLOPT_CAINFO, DIR . '/includes/paymentapi/ca-bundle.crt');
			$result = curl_exec($this->ch);
		}

		curl_close($this->ch);
		if ($this->fp)
		{
			fclose($this->fp);
			$this->fp = null;
		}

		if ($result !== false OR (!$this->vurl->options[VURL_DIEONMAXSIZE] AND $this->max_limit_reached))
		{
			return VURL_HANDLED;
		}
		return VURL_NEXT;
	}
}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40651 $
|| ####################################################################
\*======================================================================*/
