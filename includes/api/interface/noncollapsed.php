<?php

class Api_Interface_Noncollapsed extends Api_InterfaceAbstract
{

	public function callApi($controller, $method, array $arguments = array(), $useNamedParams = false)
	{
		$config = vB5_Config::instance();

		// FETCHING INFO FROM API
		$api = new VB_API_CALLS(
						$config->api_host . '/api.php',
						$config->api_client, $config->api_client_version,
						$config->api_platform,
						$config->api_platform_version,
						$config->api_key);
		$response = $api->call($controller . '_' . $method, array(), $arguments);
		$api_info = $this->obj2array(json_decode($response));

		//the api call sets error/exception handlers appropriate to core. We need to reset.
		set_exception_handler(array('vB5_ApplicationAbstract','handleException'));
		set_error_handler(array('vB5_ApplicationAbstract','handleError'),E_WARNING );
		return $api_info;
	}

	public function relay($file)
	{
		throw new Exception('relay not implemented in noncollapsed mode');
	}

	private function obj2array($var)
	{
		if (is_object($var))
		{
			$array = get_object_vars($var);
			foreach ($array as $key => $i)
			{
				$array[$key] = $this->obj2array($i);
			}
			return $array;
		}
		else if (is_array($var))
		{
			foreach ($var as $key => $i)
			{
				$var[$key] = $this->obj2array($i);
			}
			return $var;
		}
		else
		{
			return $var;
		}
	}

}

class CurlException extends Exception
{

}

class cCurl
{

	var $response_meta_info = array();

	public function __construct()
	{
		$this->open();
	}

	public function __destruct()
	{
		if ($this->curl_handle)
		{
			$this->close();
		}
	}

	private function open()
	{
		if ($this->curl_handle)
		{
			$this->close();
		}

		$handle = curl_init();
		if (!$handle)
		{
			throw CurlException("Option set operation failed");
		}

		$this->curl_handle = $handle;

		//set some option defaults to sanity
		$this->setOption(CURLOPT_RETURNTRANSFER, true);
		$this->setFollowLocation(true);

		$defaultCertFile = dirname(__FILE__) . "/cacert.pem";
		if (file_exists($defaultCertFile))
		{
			$this->setOption(CURLOPT_CAINFO, $defaultCertFile);
		}
	}

	public function exec($url)
	{
		$this->setUrl($url);
		$result = curl_exec($this->curl_handle);
		if ($result == false)
		{
			throw new CurlException($this->getErrorMessage() . ' ' . $this->getErrorNumber());
		}
		return $result;
	}

	public function get($url, $data=null)
	{
		$this->setOption(CURLOPT_HTTPGET, true);
		$this->setOption(CURLOPT_HEADERFUNCTION, array(&$this, 'readHeader'));
		if (is_array($data))
		{
			$result = @require_once(dirname(__FILE__) . "/cUrlBuilder.php");
			if (!$result)
			{
				throw new CurlException("cUrlbuilder is required for query string data merging.");
			}

			$urlObj = new cUrlBuilder($url);
			$urlObj->setParameters($data);
			$url = $urlObj->getUrl();
		}

		return $this->exec($url);
	}

	public function post($url, $data, $uploadedFiles=array())
	{
//    if(is_array($data)) {
//      $data = $this->queryEncode($data);
//    }


		if (empty($uploadedFiles))
		{
			// we are not uploading file(s), so keep cURL from automatically
			// adding the multipart/form-data content type header which can cause problems
			$strdata = array();
			foreach ($data AS $param => $datum)
			{
				$strdata[] = "$param=" . urlencode($datum);
				;
			}
			$data = implode('&', $strdata);
			unset($strdata);
		}
		else
		{
			foreach ($uploadedFiles as $name => $file)
			{
				$data[$name] = "@$file";
			}
		}

		$this->setOption(CURLOPT_POST, true);
		$this->setOption(CURLOPT_POSTFIELDS, $data);
		$result = $this->exec($url);
		$this->setOption(CURLOPT_POSTFIELDS, "");

		return $result;
	}

	public function download($url, $destination, $data=null)
	{
		$fhandle = @fopen($destination, "w");
		if (!$fhandle)
		{
			throw new CurlException("Could not open file '$destination'");
		}

		$this->setOption(CURLOPT_FILE, $fhandle);
		$result = $this->get($url, $data);
		$this->setOption(CURLOPT_RETURNTRANSFER, $this->returnTransfer);

		fclose($fhandle);
		return $result;
	}

	public function downloadPost($url, $destination, $data)
	{
		$fhandle = @fopen($destination, "w");
		if (!$fhandle)
		{
			throw new CurlException("Could not open file '$destination'");
		}

		$this->setOption(CURLOPT_FILE, $fhandle);
		$result = $this->post($url, $data);
		$this->setOption(CURLOPT_RETURNTRANSFER, $this->returnTransfer);

		fclose($fhandle);
		return $result;
	}

	public function getErrorNumber()
	{
		return curl_errno($this->curl_handle);
	}

	public function getErrorMessage()
	{
		return curl_error($this->curl_handle);
	}

	public function close()
	{
		//intentionally ignore error.  Not much we can do about it, anyway.
		curl_close($this->curl_handle);
		$this->curl_handle = null;
		$this->cleanTempCookieJar();
	}

	/*
	  Higher level option interfaces
	 */

	public function setUrl($url)
	{
		$this->setOption(CURLOPT_URL, $url);
	}

	public function startCookieSession($file="")
	{
		if (!$file)
		{
			$file = $this->makeTempCookieJar();
		}
		$this->setOption(CURLOPT_COOKIEJAR, $file);
		$this->setOption(CURLOPT_COOKIEFILE, $file);
	}

	public function setIncludeHeaders($flag)
	{
		$this->setOption(CURLOPT_HEADER, $flag);
	}

	public function setFollowLocation($flag)
	{
		$this->setOption(CURLOPT_FOLLOWLOCATION, $flag);
	}

	/*
	  We'll usually want to set these to the same value.
	  The setOption function can be used to set them
	  independantly
	 */

	public function setTimeout($seconds)
	{
		$this->setOption(CURLOPT_CONNECTTIMEOUT, $seconds);
		$this->setOption(CURLOPT_TIMEOUT, $seconds);
	}

	public function setLogin($username, $password)
	{
		$this->setOption(CURLOPT_USERPWD, "$username:$password");
	}

	/*
	  Low level option function
	 */

	public function setOption($option, $value)
	{
		if (!is_numeric($option))
		{
			throw new CurlException("Invalid Option");
		}

		//a hack to track the return transfer state.  Setting the output file
		//is fairly permanent and can only be cleared by setting the transfer state
		//however, curl doesn't give us any way to check it, so we track it ourselves.
		if ($option == CURLOPT_RETURNTRANSFER)
		{
			$this->returnTransfer = $value;
		}

		$result = curl_setopt($this->curl_handle, $option, $value);
		if (!$result)
		{
			throw CurlException("Option set operation failed");
		}
	}

	/*
	  Get access to the handle to use bit of the curl interface not implemented here.
	  Please consider extended the implementation rather than use the function.

	  Note that using this to change the state of handle can cause problems with the
	  rest of the library, use with care.
	 */

	public function getHandle()
	{
		return $this->curl_handle;
	}

	/*
	  Private helper functions
	 */

	private function makeTempCookieJar()
	{
		$this->cleanTempCookieJar();
		$file = tempnam(sys_get_temp_dir(), "curl_cookie_");
		$file = realpath($file);
		$this->tempCookieJar = $file;
		return $file;
	}

	private function cleanTempCookieJar()
	{
		if ($this->tempCookieJar)
		{
			unlink($this->tempCookieJar);
		}
	}

	private function queryEncode($urlArray)
	{
		$urlData = array();
		foreach ($urlArray as $key => $val)
		{
			if (!is_array($val))
			{
				array_push($urlData, "$key=" . urlencode($val));
			}
			else
			{
				foreach ($val as $subval)
				{
					array_push($urlData, "$key=" . urlencode($subval));
				}
			}
		}

		return join('&', $urlData);
	}

	private function extractCustomHeader($start, $end, $header)
	{
		$pattern = '/' . $start . '(.*?)' . $end . '/';
		if (preg_match($pattern, $header, $result))
		{
			return $result[1];
		}
		else
		{
			return false;
		}
	}

	public function readHeader($ch, $header)
	{
		//extracting example data: filename from header field Content-Disposition
		$filename = $this->extractCustomHeader('Content-Disposition: attachment; filename=', '\n', $header);
		if ($filename)
		{
			$this->response_meta_info['content_disposition'] = trim($filename);
		}
		return strlen($header);
	}

	public function getHeaders()
	{
		return $this->response_meta_info;
	}

	private $curl_handle = null;
	private $tempCookieJar = null;
	private $returnTransfer = true;

}

class VB_API_CALLS
{

	public function __construct($site, $clientname, $clientversion, $platformname, $platformversion, $apikey)
	{
		$this->curl = new cCurl();
		$this->curl->startCookieSession();

		$this->site = $site;
		$this->apikey = $apikey;
		$this->api_s = '';
		$this->api_c = '';
		$this->api_sig = '';
		$this->api_v = '';
		$this->json_response = "";
		$this->response = "";

		$uniqueid = md5("1478545698");
		$relative_url = "?api_m=api_init&clientname=" . $clientname . "&clientversion=" . $clientversion .
				"&platformname=" . $platformname . "&platformversion=" . $platformversion . "&uniqueid=" . $uniqueid;

		$this->json_response = $this->get($relative_url);
		$response = json_decode($this->json_response);
		$this->api_s = $response->apiaccesstoken;
		$this->api_v = $response->apiversion;
		$this->api_c = $response->apiclientid;
		$this->secret = $response->secret;
	}

	public function call($method, $get, $post)
	{
		$get['api_m'] = $method;
		$apiparams['api_s'] = $this->api_s;
		$apiparams['api_c'] = $this->api_c;
		$apiparams['api_v'] = 3;
		$apiparams['api_sig'] = $this->sign($get);

		$url = '?' . http_build_query($apiparams, '', '&') . '&' . http_build_query($get, '', '&');
		$response = $this->post($url, $post);

		return $response;
	}

	private function sign($params)
	{
		ksort($params);
		$signstr = http_build_query($params, '', '&');
		$sign = md5($signstr . $this->api_s . $this->api_c . $this->secret . $this->apikey);

		return $sign;
	}

	public function api_login($username, $password)
	{
		$login = $this->call('login_login', array(), array('vb_login_username' => $username, 'vb_login_password' => $password));
		$aux_response = json_decode($login);
		$this->userid = @$aux_response->session->userid;

		return $login;
	}

	function do_search($keywords)
	{

		$result = $this->call('search_process', array(), array('query' => $keywords));
		$threads_info = $this->get_last_threads($result);

		return $threads_info;
	}

	function activity_stream()
	{
		$result = $this->call('search_getnew', array(), array());

		$threads_info = $this->get_last_threads($result);

		return $threads_info;
	}

	function popular_topics()
	{
		$result = $this->call('search_getdaily', array(), array('days' => 3, 'showpost' => 0, 'sortby' => 'replycount'));

		$threads_info = $this->get_last_threads($result);

		return $threads_info;
	}

	function current_members()
	{
		$result = $this->call('forum', array(), array());
		$response = json_decode($result);
		$users_array = array();
		$users_array = array();
		$user_string = "";
		$users_info_array = array();
		if (@$response->response->activeusers)
		{
			foreach ($response->response->activeusers as $user)
			{
				$user_string = $user->userid . '-/= ' . $user->username;
				$users_array[] = $user_string;
			}
		}
		$users_info_array[0] = count(@$response->response->activeusers);
		$users_info_array[1] = $users_array;
		return $users_info_array;
	}

	public function blog_list()
	{
		return $this->call('blog_list', array(), array('blogtype' => 'best', 'nohtml' => '1'));
	}

	public function forums()
	{
		$result = $this->call('forum', array(), array());

		$forum_response = json_decode($result);
		$forumbits_array = $forum_response->response->forumbits;

		$subforum_array = array();
		$forums_data_array = array();
		$super_forum_title_array = array();
		$master_forum_array = array();
		$sep = '';
		$sep1 = '';
		$forum_title_string = '';
		$subforum_title = '';
		$subforum_string = '';
		$isempty = true;
		foreach ($forumbits_array as $super_forum)
		{

			$childforumbits = $super_forum->childforumbits;
			//print_r($childforumbits);
			foreach ($childforumbits as $forum)
			{


				if (@$forum->forum->title != '')
				{
					//echo '<br>forum_title_string = '.
					$forum_title_string .= $sep . @$forum->forum->title . '-/=' . @$forum->forum->threadcount . '-/=' . @$forum->forum->replycount . '-/=' .
							@$forum->forum->description . '-/=' . @$forum->forum->lastpostinfo->lastpostinfo->lastposter . '-/=' . @$forum->forum->lastpostinfo->lastpostinfo->lastposttime .
							'-/=' . @$forum->forum->lastpostinfo->lastpostinfo->lastposterid . '-/=' . @$forum->forum->lastpostinfo->lastpostinfo->lastpostdate . '-/=' . @$forum->forum->forumid;
				}
				//echo "<br> subforum = ".$forum->forum->subforum;
				if (@$forum->forum->subforums != '')
				{
					//echo "<br>there is a subforum ";
					if (count(@$forum->forum->subforums) > 1)
					{
						foreach ($forum->forum->subforums as $subforum)
						{

							//echo "<br>subforum_title = " . @$subforum->forum->title;
							if (@$subforum->forum->forumid)
							{
								$subforum_string .= $sep1 . @$subforum->forum->forumid . '-/=' . @$subforum->forum->title . '-/=' . @$subforum->forum->threadcount;
								$sep1 = '|';
							}
						}
					}
					else
					{
						$subforum_string = '|' . @$forum->forum->subforums->forum->forumid . '-/=' . @$forum->forum->subforums->forum->title . '-/=' . @$forum->forum->subforums->forum->threadcount;
					}
					//echo "<br>forum_id_padre = " .@$forum->forum->forumid. " subforum_string = " . $subforum_string;
					$subforum_array[@$forum->forum->forumid] = $subforum_string;
					$subforum_string = '';
					$sep1 = '';
				}

				$sep = '|';
			}
			if (@$forum->forum->title != '')
				$forums_data_array[@$super_forum->forum->title] = $forum_title_string;
			$sep = '';
			$forum_title_string = '';
		}
		$master_forum_array[0] = $forums_data_array;
		$master_forum_array[1] = $subforum_array;
		return $master_forum_array;
	}

	function forumdisplay($forumid, $page, $records_per_page)
	{

		$result = $this->call('forumdisplay', array('forumid' => $forumid, 'perpage' => $records_per_page, 'pagenumber' => $page, 'daysprune' => -1), array());
		$fdisplay_response = json_decode($result);

		$forum_array = array();
		$thread_array = array();
		$sticky_thread_array = array();
		$forum_string = '';
		$thread_string = '';
		$sticky_thread_string = '';
		$sep = '';

		//$forum_array['title'] = $fdisplay_response->response->foruminfo->title;
		$forumbits = @$fdisplay_response->response->forumbits;
		//echo "<br>forumbits_count = " .
		if ($forumbits)
		{
			if (count($forumbits) > 1)
			{
				foreach ($forumbits as $sforum)
				{
					$forum_string = @$sforum->forum->forumid . '-/=' . @$sforum->forum->threadcount . '-/=' . @$sforum->forum->title . '-/=' .
							@$sforum->forum->replycount . '-/=' . @$sforum->forum->lastpostinfo->lastpostinfo->lastposter . '-/=' .
							@$sforum->forum->lastpostinfo->lastpostinfo->lastposterid . '-/=' . @$sforum->forum->lastpostinfo->lastpostinfo->lastpostdate .
							'-/=' . @$sforum->forum->lastpostinfo->lastpostinfo->lastposttime . '-/=' . @$sforum->forum->description;

					$forum_array[] = $forum_string;
				}
			}
			else
			{
				$sforum = $forumbits;
				@$forum_string = @$sforum->forum->forumid . '-/=' . @$sforum->forum->threadcount . '-/=' . @$sforum->forum->title . '-/=' .
						@$sforum->forum->replycount . '-/=' . @$sforum->forum->lastpostinfo->lastpostinfo->lastposter . '-/=' .
						@$sforum->forum->lastpostinfo->lastpostinfo->lastposterid . '-/=' . @$sforum->forum->lastpostinfo->lastpostinfo->lastpostdate .
						'-/=' . @$sforum->forum->lastpostinfo->lastpostinfo->lastposttime . '-/=' . @$sforum->forum->description;

				$forum_array[0] = $forum_string;
			}
		}
		if (@$fdisplay_response->response->threadbits)
		{
			//echo "<br>threads_count = " . count($fdisplay_response->response->threadbits);
			if (count($fdisplay_response->response->threadbits) > 1)
			{
				foreach ($fdisplay_response->response->threadbits as $threads)
				{
					//echo "<br>threadtitle = " . @$threads->thread->threadtitle;
					$thread_string = @$threads->thread->threadid . '-/=' . @$threads->thread->threadtitle . '-/=' . @$threads->thread->postusername .
							'-/=' . @$threads->thread->postuserid . '-/=' . @$threads->thread->lastpostdate . '-/=' . @$threads->thread->lastposttime . '-/=' . @$threads->thread->replycount;
					$thread_array[] = $thread_string;
					$thread_string = '';
				}
			}
			else
			{
				$threads = $fdisplay_response->response->threadbits;
				$thread_string = @$threads->thread->threadid . '-/=' . @$threads->thread->threadtitle . '-/=' . @$threads->thread->postusername .
						'-/=' . @$threads->thread->postuserid . '-/=' . @$threads->thread->lastpostdate . '-/=' . @$threads->thread->lastposttime . '-/=' . @$threads->thread->replycount;
				$thread_array[] = $thread_string;
				$thread_string = '';
			}
		}
		if (@$fdisplay_response->response->threadbits_sticky)
		{
			//echo "<br>threads_count = " . count($fdisplay_response->response->threadbits_sticky);
			if (count($fdisplay_response->response->threadbits_sticky) > 1)
			{
				foreach ($fdisplay_response->response->threadbits_sticky as $threads)
				{
					//echo "<br>threadtitle1 = " . @$threads->thread->threadtitle;
					//echo "<br>threadid = " . @$threads->thread->threadid;
					$sticky_thread_string = @$threads->thread->threadid . '-/=' . @$threads->thread->threadtitle . '-/=' . @$threads->thread->postusername .
							'-/=' . @$threads->thread->postuserid . '-/=' . @$threads->thread->lastpostdate . '-/=' . @$threads->thread->lastposttime . '-/=' . @$threads->thread->replycount;
					$sticky_thread_array[] = $sticky_thread_string;
					$sticky_thread_string = '';
				}
			}
			else
			{
				$threads = $fdisplay_response->response->threadbits_sticky;
				$sticky_thread_string = @$threads->thread->threadid . '-/=' . @$threads->thread->threadtitle . '-/=' . @$threads->thread->postusername .
						'-/=' . @$threads->thread->postuserid . '-/=' . @$threads->thread->lastpostdate . '-/=' . @$threads->thread->lastposttime . '-/=' . @$threads->thread->replycount;
				$sticky_thread_array[] = $sticky_thread_string;
			}
		}
		$master_fdisplay[0] = $forum_array;
		$master_fdisplay[1] = $thread_array;
		$master_fdisplay[2] = $sticky_thread_array;

		return $master_fdisplay;
	}

	function get_breadcrumbs_links($root_forumid, $last_branch_forum_id)
	{

		do
		{

			$result = $this->call('forumdisplay', array('forumid' => $root_forumid, 'perpage' => 20, 'pagenumber' => $page, 'daysprune' => -1), array());
			$fdisplay_response = json_decode($result);

			$forumbits = @$fdisplay_response->response->forumbits;
			//echo "<br>forumbits_count = " .
			if ($forumbits)
			{
				if (count($forumbits) > 1)
				{
					foreach ($forumbits as $sforum)
					{
						$forum_string = @$sforum->forum->forumid . '-/=' . @$sforum->forum->threadcount . '-/=' . @$sforum->forum->title . '-/=' .
								@$sforum->forum->replycount . '-/=' . @$sforum->forum->lastpostinfo->lastpostinfo->lastposter . '-/=' .
								@$sforum->forum->lastpostinfo->lastpostinfo->lastposterid . '-/=' . @$sforum->forum->lastpostinfo->lastpostinfo->lastpostdate .
								'-/=' . @$sforum->forum->lastpostinfo->lastpostinfo->lastposttime . '-/=' . @$sforum->forum->description;

						$forum_array[] = $forum_string;
					}
				}
				else
				{
					$sforum = $forumbits;
					@$forum_string = @$sforum->forum->forumid . '-/=' . @$sforum->forum->threadcount . '-/=' . @$sforum->forum->title . '-/=' .
							@$sforum->forum->replycount . '-/=' . @$sforum->forum->lastpostinfo->lastpostinfo->lastposter . '-/=' .
							@$sforum->forum->lastpostinfo->lastpostinfo->lastposterid . '-/=' . @$sforum->forum->lastpostinfo->lastpostinfo->lastpostdate .
							'-/=' . @$sforum->forum->lastpostinfo->lastpostinfo->lastposttime . '-/=' . @$sforum->forum->description;

					$forum_array[0] = $forum_string;
				}
			}
			$result_links = "<a href='#' onclick='send_request(\'#main-area\',\'forum.php?forumid=$forumid&forum_forumname=" . str_replace("'", "\'", str_replace(' ', '%20', $forum_title_aux)) . "&threadcount=$threadcount \')'>$forum_title_aux</a>";
		} while ($root_forumid != $last_branch_forum_id);
	}

	function showthread($threadid, $page)
	{

		$result = $this->call('showthread', array('threadid' => $threadid, 'perpage' => 20, 'pagenumber' => $page), array());
		$showthread_response = json_decode($result);
		$postbits = @$showthread_response->response->postbits;
		$post_array = array();
		$post_string = '';

		if ($postbits)
		{
			if (count($postbits) > 1)
			{
				foreach ($postbits as $post)
				{
					$post_string = @$post->post->postid . '-/=' . @$post->post->postdate . '-/=' . @$post->post->posttime . '-/=' . @$post->post->username . '-/=' . @$post->post->message . '-/=' . @$post->post->userid . '-/=' . @$post->post->title . '-/=' . @$post->post->avatarurl;
					$post_array[] = $post_string;
					$post_string = '';
				}
			}
			else
			{
				$post_string = @$postbits->post->postid . '-/=' . @$postbits->post->postdate . '-/=' . @$postbits->post->posttime . '-/=' . @$postbits->post->username . '-/=' . @$postbits->post->message . '-/=' . @$postbits->post->userid . '-/=' . @$post->post->title . '-/=' . @$post->post->avatarurl;
				$post_array[0] = $post_string;
			}
		}
		return $post_array;
	}

	function profile($username)
	{

		/* echo '<br>username = '.$username;
		  echo '<br>userid = '.$this->userid; */
		$user_profile_array = array();

		$json_profile_response = $this->call('member', array(), array('userid' => $this->userid));
		$profile_response = json_decode($json_profile_response);
		//print_r($profile_response);

		if (@$profile_response->response->errormessage[0] != 'unregistereduser')
		{
			$post = @$profile_response->response->prepared->posts;
			$lastactivitydate = @$profile_response->response->prepared->lastactivitydate;
			$joindate = @$profile_response->response->prepared->joindate;
			$profileurl = @$profile_response->response->prepared->profileurl;
			$fields = @$profile_response->response->blocks->aboutme->block_data->fields->category->fields;
			$location = "";
			if ($fields != '')
			{
				foreach (@$profile_response->response->blocks->aboutme->block_data->fields->category->fields as $field)
				{
					if (@$field->profilefield->title == 'Location')
						$location = @$field->profilefield->value;
				}
			}
			$user_profile_array = array('post' => $post, 'lastactivitydate' => $lastactivitydate, 'joindate' => $joindate, 'location' => $location, 'profileurl' => $profileurl);
		}
		//else echo '<br>ERROR: Unregistered user';

		return $user_profile_array;
	}

	function user_activity($username)
	{

		$result = $this->call('search_finduser', array('userid' => $this->userid), array());
		$threads_info = $this->get_last_threads($result);

		return $threads_info;
	}

	function get_last_threads($result)
	{

		$search_response = json_decode($result);

		$searchid = @$search_response->show->searchid;

		$result_search_showresults = $this->call('search_showresults', array(), array('searchid' => $searchid));

		$php_response = json_decode($result_search_showresults);
		$searchbits = @$php_response->response->searchbits;

		$thread_info_array = array();
		$aux = '';
		if (is_array($searchbits))
		{
			foreach ($searchbits as $search)
			{
				if (@$search->thread->lastposttime != '')
					$aux = @$search->thread->lastposttime;
				// $search->thread->highlight[0];
				$thread_info_array[] = @$search->thread->postusername . '|' . @$search->thread->threadtitle . '|' . $aux . '|' . @$search->thread->threadid .
						'|' . @$search->thread->postuserid . '|' . @$search->thread->lastpostdate;
			}
		}
		return $thread_info_array;
	}

	function reply_postreply($threadid, $msg, $title)
	{

		$msg = str_replace("%20", " ", $msg);
		$msg = str_replace("<br>", "\n", $msg);
		$title = str_replace("%20", " ", $title);
		$result = $this->call('newreply_postreply', array(), array('threadid' => $threadid, 'message' => $msg, 'loggedinuser' => $this->userid, 'title' => $title));
		if (@$result->response->errormessage == 'redirect_postthanks')
		{

			return true;
		}

		return false;
	}

	protected function get($relative_url)
	{
		return $this->curl->get($this->site . $relative_url);
	}

	protected function post($relative_url, $data)
	{
		return $this->curl->post($this->site . $relative_url, $data);
	}

}
