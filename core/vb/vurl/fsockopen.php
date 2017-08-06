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

class vB_vURL_fsockopen
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
	function vB_vURL_fsockopen(&$vurl)
	{
		if (!is_a($vurl, 'vB_vURL'))
		{
			throw new Exception('Direct Instantiation of ' . __CLASS__ . ' prohibited.');
		}
		$this->vurl =& $vurl;
	}

	/**
	* Clears all previous request info
	*/
	function reset()
	{
		$this->response_text = '';
		$this->response_header = '';
		$this->response_length = 0;
		$this->max_limit_reached = false;
	}

	/**
	* Inflates the response if its gzip or deflate
	*/
	function inflate_response($type)
	{
		if (!empty($this->response_text))
		{
			switch($type)
			{
				case 'gzip':
					if ($this->response_text[0] == "\x1F" AND $this->response_text[1] == "\x8b")
					{
						if ($inflated = @gzinflate(substr($this->response_text, 10)))
						{
							$this->response_text = $inflated;
						}
					}
				break;
				case 'deflate':

					if ($this->response_text[0] == "\x78" AND $this->response_text[1] == "\x9C" AND $inflated = @gzinflate(substr($this->response_text, 2)))
					{
						$this->response_text = $inflated;
					}
					else if ($inflated = @gzinflate($this->response_text))
					{
						$this->response_text = $inflated;
					}
				break;
			}
		}
		else
		{
			$compressed_file = $this->vurl->tmpfile;
			if ($gzfp = @gzopen($compressed_file, 'r'))
			{
				if ($newfp = @fopen($this->vurl->tmpfile . 'u', 'w'))
				{
					$this->vurl->tmpfile = $this->vurl->tmpfile . 'u';
					if (function_exists('stream_copy_to_stream'))
					{
						stream_copy_to_stream($gzfp, $newfp);
					}
					else
					{
						while(!gzeof($gzfp))
						{
							fwrite($fp, gzread($gzfp, 20480));
						}
					}

					fclose($newfp);
				}

				fclose($gzfp);
				@unlink($compressed_file);
			}
		}
	}

	/**
	* Callback for handling the request body
	*
	* @param	string		Request
	*
	* @return	integer		length of the request
	*/
	function callback_response($response)
	{
		$chunk_length = strlen($response);

		// no filepointer and we're using or about to use more than 100k
		if (!$this->fp AND $this->response_length + $chunk_length >= 1024*100)
		{
			if ($this->fp = @fopen($this->vurl->tmpfile, 'wb'))
			{
				fwrite($this->fp, $this->response_text);
				unset($this->response_text);
			}
		}

		if ($response)
		{
			if ($this->fp)
			{
				fwrite($this->fp, $response);
			}
			else
			{
				$this->response_text .= $response;

			}
		}

		$this->response_length += $chunk_length;

		if (isset($this->vurl->options[VURL_MAXSIZE]) AND $this->response_length > $this->vurl->options[VURL_MAXSIZE])
		{
			$this->max_limit_reached = true;
			$this->vurl->set_error(VURL_ERROR_MAXSIZE);
			return false;
		}

		return $chunk_length;
	}

	/**
	* Performs fetching of the file if possible
	*
	* @return	integer		Returns one of two constants, VURL_NEXT or VURL_HANDLED
	*/
	function exec()
	{
		static $location_following_count = 0;

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

		if (empty($urlinfo['path']))
		{
			$urlinfo['path'] = '/';
		}

		if ($urlinfo['scheme'] == 'https')
		{
			if (!function_exists('openssl_open'))
			{
				$this->vurl->set_error(VURL_ERROR_SSL);
				return VURL_NEXT;
			}
			$scheme = 'ssl://';
		}

		if ($request_resource = @fsockopen($scheme . $urlinfo['host'], $urlinfo['port'], $errno, $errstr, $this->vurl->options[VURL_TIMEOUT]))
		{
			$headers = array();
			if ($this->vurl->bitoptions & VURL_NOBODY)
			{
				$this->vurl->options[VURL_CUSTOMREQUEST] = 'HEAD';
			}
			if (isset($this->vurl->options[VURL_CUSTOMREQUEST]))
			{
				$headers[] = $this->vurl->options[VURL_CUSTOMREQUEST] . " $urlinfo[path]" . ($urlinfo['query'] ? "?$urlinfo[query]" : '') . " HTTP/1.0";
			}
			else if ($this->vurl->bitoptions & VURL_POST)
			{
				$headers[] = "POST $urlinfo[path]" . ($urlinfo['query'] ? "?$urlinfo[query]" : '') . " HTTP/1.0";
				if (empty($this->vurl->headerkey['content-type']))
				{
					$headers[] = 'Content-Type: application/x-www-form-urlencoded';
				}
				if (empty($this->vurl->headerkey['content-length']))
				{
					$headers[] = 'Content-Length: ' . strlen($this->vurl->options[VURL_POSTFIELDS]);
				}
			}
			else
			{
				$headers[] = "GET $urlinfo[path]" . ((isset($urlinfo['query']) && $urlinfo['query']) ? "?$urlinfo[query]" : '') . " HTTP/1.0";
			}
			$headers[] = "Host: $urlinfo[host]";
			if (!empty($this->vurl->options[VURL_HTTPHEADER]))
			{
				$headers = array_merge($headers, $this->vurl->options[VURL_HTTPHEADER]);
			}
			if ($this->vurl->options[VURL_ENCODING])
			{
				$encodemethods = explode(',', $this->vurl->options[VURL_ENCODING]);
				$finalmethods = array();
				foreach ($encodemethods AS $type)
				{
					$type = strtolower(trim($type));
					if ($type == 'gzip' AND function_exists('gzinflate'))
					{
						$finalmethods[] = 'gzip';
					}
					else if ($type == 'deflate' AND function_exists('gzinflate'))
					{
						$finalmethods[] = 'deflate';
					}
					else
					{
						$finalmethods[] = $type;
					}
				}

				if (!empty($finalmethods))
				{
					$headers[] = "Accept-Encoding: " . implode(', ', $finalmethods);
				}
			}

			$output = implode("\r\n", $headers) . "\r\n\r\n";
			if ($this->vurl->bitoptions & VURL_POST)
			{
				$output .= $this->vurl->options[VURL_POSTFIELDS];
			}

			$result = false;

			if (fputs($request_resource, $output, strlen($output)))
			{
				stream_set_timeout($request_resource, $this->vurl->options[VURL_TIMEOUT]);
				$in_header = true;
				$result = true;

				while (!feof($request_resource))
				{
					$response = @fread($request_resource, 2048);

					if ($in_header)
					{
						$header_end_position = strpos($response, "\r\n\r\n");

						if ($header_end_position === false)
						{
							$this->response_header .= $response;
						}
						else
						{
							$this->response_header .= substr($response, 0, $header_end_position);
							$in_header = false;
							$response = substr($response, $header_end_position + 4);
						}
					}

					if ($this->callback_response($response) != strlen($response))
					{
						$result = false;
						break;
					}
				}
				fclose($request_resource);
			}

			if ($this->fp)
			{
				fclose($this->fp);
				$this->fp = null;
			}

			if ($result !== false OR (!$this->vurl->options[VURL_DIEONMAXSIZE] AND $this->max_limit_reached))
			{
				if ($this->vurl->bitoptions & VURL_FOLLOWLOCATION AND preg_match("#\r\nLocation: (.*)(\r\n|$)#siU", $this->response_header, $location) AND $location_following_count < $this->vurl->options[VURL_MAXREDIRS])
				{
					$location_following_count++;
					$this->vurl->set_option(VURL_URL, trim($location[1]));
					$this->reset();
					return $this->exec();
				}

				// need to handle gzip if it was used
				if (function_exists('gzinflate'))
				{
					if (stristr($this->response_header, "Content-encoding: gzip\r\n") !== false)
					{
						$this->inflate_response('gzip');
					}
					else if (stristr($this->response_header, "Content-encoding: deflate\r\n") !== false)
					{
						$this->inflate_response('deflate');
					}
				}

				return VURL_HANDLED;
			}
		}
		return VURL_NEXT;
	}

}

/*======================================================================*\
|| ####################################################################
|| # CVS: $RCSfile$ - $Revision: 40651 $
|| ####################################################################
\*======================================================================*/
